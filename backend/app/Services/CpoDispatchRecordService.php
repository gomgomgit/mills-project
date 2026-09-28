<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Exceptions\InvalidDateRangeException;
use App\Exceptions\NoActiveCpoDispatchStationException;
use App\Models\CpoDispatchDetail;
use App\Models\CpoDispatchRecord;
use App\Models\User;
use App\Support\Concerns\ScopesToActorMill;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * CpoDispatchRecordService — screen-094--data-browser-cpo-dispatch-web
 * / screen-104--detail-cpo-dispatch-web / screen-114--form-cpo-dispatch-web.
 *
 * Shared by both the API controller (App\Http\Controllers\Api\
 * CpoDispatchRecordController) and the Livewire components (App\
 * Livewire\Data\{DataBrowserCpoDispatch,DetailCpoDispatch,
 * FormCpoDispatch}), mirroring KernelDispatchRecordService's
 * structure — event-log pattern: unbounded detail rows added manually per
 * event, no fixed grid/no per-row time-slot uniqueness constraint.
 */
class CpoDispatchRecordService
{
    use ScopesToActorMill;

    public const EXPORT_ROW_LIMIT = 50000;

    protected const FORM_FIELDS = [
        'cpo_dispatch_id', 'date', 'note',
    ];

    protected const DETAIL_FIELDS = [
        'event_date', 'shift', 'time_in', 'time_out', 'waybill_number', 'tanker_plate_no',
        'transport_company', 'driver_name', 'storage_tank_source', 'seal_no_top', 'seal_no_bottom',
        'gross_weight_mt', 'tare_weight_mt', 'ffa_percent', 'moisture_percent', 'impurities_percent',
        'dobi', 'destination_buyer', 'weighbridge_operator', 'findings',
    ];

    /**
     * create() — resolve the active CPO Dispatch station from
     * production_line_id, validate header + details, then INSERT the
     * record and its details inside a DB transaction.
     */
    public function create(array $data, User $actor): array
    {
        $attributes = $this->normalizeFormFields($data);
        $details = $this->normalizeDetails($data['details'] ?? []);

        $this->validateForm($attributes);
        $this->validateDetails($details);

        $station = $this->resolveActiveStationForActor(
            $data['production_line_id'] ?? null,
            'cpo-dispatch',
            $actor,
        );

        if ($station === null) {
            throw new NoActiveCpoDispatchStationException();
        }

        $attributes['station_id'] = $station->id;
        $attributes['created_by'] = $actor->id;
        $attributes['status'] = 'saved';
        $this->applyVerification($attributes, $data, $actor);

        $record = DB::transaction(function () use ($attributes, $details) {
            $record = CpoDispatchRecord::create($attributes);
            $this->upsertDetails($record, $details);

            return $record;
        });

        $record->load(['station', 'createdBy', 'checkedBy', 'acknowledgedBy', 'cpoDispatchDetails']);

        return $this->toDetailRow($record);
    }

    /**
     * update() — validate header + details (production_line_id/station_id
     * never accepted), UPDATE the record, then upsert its detail rows
     * (insert rows without an id, update rows with an id still present,
     * delete rows previously in the DB but no longer in the array) inside
     * a DB transaction.
     */
    public function update(string $id, array $data, User $actor): array
    {
        $record = CpoDispatchRecord::findOrFail($id);

        $this->assertRecordWritableByActor($record, $actor);

        $attributes = $this->normalizeFormFields($data);
        $details = $this->normalizeDetails($data['details'] ?? []);

        $this->validateForm($attributes);
        $this->validateDetails($details);

        $this->applyVerification($attributes, $data, $actor);

        DB::transaction(function () use ($record, $attributes, $details) {
            $record->update($attributes);
            $this->upsertDetails($record, $details);
        });

        $record->load(['station', 'createdBy', 'checkedBy', 'acknowledgedBy', 'cpoDispatchDetails']);

        return $this->toDetailRow($record);
    }

    protected function normalizeFormFields(array $data): array
    {
        $attributes = [];

        foreach (self::FORM_FIELDS as $field) {
            $attributes[$field] = $data[$field] ?? null;
        }

        return $attributes;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeDetails(array $rawDetails): array
    {
        return collect($rawDetails)
            ->map(function ($row) {
                $normalized = ['id' => $row['id'] ?? null];

                foreach (self::DETAIL_FIELDS as $field) {
                    $normalized[$field] = $row[$field] ?? null;
                }

                return $normalized;
            })
            ->values()
            ->all();
    }

    protected function validateForm(array $attributes): void
    {
        Validator::make($attributes, [
            'cpo_dispatch_id' => ['required', 'string'],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ], [
            'cpo_dispatch_id.required' => 'CPO Dispatch ID wajib diisi.',
            'date.required' => 'Tanggal wajib diisi.',
        ])->validate();
    }

    /**
     * validateDetails() — at least one valid detail row (event_date filled
     * in) must exist. Unlike a grid station, there is no time-slot
     * uniqueness or ascending-order constraint — rows are a free event
     * log, added manually per occurrence, unbounded per day.
     */
    protected function validateDetails(array $details): void
    {
        $validRows = collect($details)->filter(fn ($row) => filled($row['event_date'] ?? null));

        if ($validRows->isEmpty()) {
            throw ValidationException::withMessages([
                'details' => 'Minimal satu baris log CPO Dispatch (Tanggal Kejadian wajib) harus diisi.',
            ]);
        }
    }

    /**
     * upsertDetails() — for each valid detail row, compute net_weight_mt =
     * gross_weight_mt - tare_weight_mt (server-side, never accepted from
     * client), then insert rows without an id, update rows whose id still
     * appears in $details, and delete any existing row whose id is no
     * longer present.
     *
     * Unlike KernelDispatchDetail, CpoDispatchDetail has no enum column —
     * no empty-string-vs-null CHECK-constraint normalization is needed for
     * any detail field here (time_in/time_out are plain nullable time
     * columns).
     */
    protected function upsertDetails(CpoDispatchRecord $record, array $details): void
    {
        $validRows = collect($details)->filter(fn ($row) => filled($row['event_date'] ?? null));

        $keptIds = [];

        foreach ($validRows as $row) {
            $gross = $row['gross_weight_mt'] !== null ? (float) $row['gross_weight_mt'] : null;
            $tare = $row['tare_weight_mt'] !== null ? (float) $row['tare_weight_mt'] : null;
            $netWeight = ($gross !== null && $tare !== null) ? $gross - $tare : null;

            $detailAttributes = [
                'cpo_dispatch_record_id' => $record->id,
                'event_date' => $row['event_date'],
                'shift' => $row['shift'],
                'time_in' => $row['time_in'] ?: null,
                'time_out' => $row['time_out'] ?: null,
                'waybill_number' => $row['waybill_number'],
                'tanker_plate_no' => $row['tanker_plate_no'],
                'transport_company' => $row['transport_company'],
                'driver_name' => $row['driver_name'],
                'storage_tank_source' => $row['storage_tank_source'],
                'seal_no_top' => $row['seal_no_top'],
                'seal_no_bottom' => $row['seal_no_bottom'],
                'gross_weight_mt' => $gross,
                'tare_weight_mt' => $tare,
                'net_weight_mt' => $netWeight,
                'ffa_percent' => $row['ffa_percent'],
                'moisture_percent' => $row['moisture_percent'],
                'impurities_percent' => $row['impurities_percent'],
                'dobi' => $row['dobi'],
                'destination_buyer' => $row['destination_buyer'],
                'weighbridge_operator' => $row['weighbridge_operator'],
                'findings' => $row['findings'],
            ];

            if (! empty($row['id']) && CpoDispatchDetail::where('id', $row['id'])->where('cpo_dispatch_record_id', $record->id)->exists()) {
                CpoDispatchDetail::where('id', $row['id'])->update($detailAttributes);
                $keptIds[] = $row['id'];
            } else {
                $detail = CpoDispatchDetail::create($detailAttributes);
                $keptIds[] = $detail->id;
            }
        }

        CpoDispatchDetail::where('cpo_dispatch_record_id', $record->id)
            ->whereNotIn('id', $keptIds)
            ->delete();
    }

    /**
     * applyVerification() — Checked By (Supervisor) and Acknowledged By
     * (Mill Management) self-attestation checkboxes, mirroring
     * KernelDispatchRecordService.
     */
    protected function applyVerification(array &$attributes, array $data, User $actor): void
    {
        if ($actor->role === UserRole::Supervisor) {
            $attributes['checked_by'] = ! empty($data['checked']) ? $actor->id : null;
        }

        if ($actor->role === UserRole::MillManagement) {
            $attributes['acknowledged_by'] = ! empty($data['acknowledged']) ? $actor->id : null;
        }
    }

    /**
     * listRecords() — validate the date range, build the filtered query
     * (with event_count computed via withCount()), paginate, and return
     * the {data, meta} shape.
     *
     * @param  array{date_from?: ?string, date_to?: ?string, business_unit_id?: ?string}  $filters
     */
    public function listRecords(array $filters, int $page, int $perPage): array
    {
        $query = $this->buildFilteredQuery($filters)
            ->withCount('cpoDispatchDetails')
            ->orderByDesc('date');

        $paginator = $query->paginate(perPage: $perPage, page: $page);

        $formatted = Pagination::format($paginator);
        $formatted['data'] = collect($formatted['data'])
            ->map(fn (CpoDispatchRecord $record) => $this->toListRow($record))
            ->all();

        return $formatted;
    }

    /**
     * export() — re-run the same filter query (no pagination), enforce
     * the row limit, generate a CSV body, and return it as a
     * StreamedResponse for download.
     *
     * @param  array{date_from?: ?string, date_to?: ?string, business_unit_id?: ?string}  $filters
     */
    public function export(array $filters, string $format): StreamedResponse
    {
        $baseQuery = $this->buildFilteredQuery($filters);

        // The row limit counts EXPORTED lines, not header records: the file
        // writes one line per detail row, plus a single line for a record that
        // has no detail rows at all so an empty day stays visible.
        $detailRowCount = CpoDispatchDetail::query()
            ->whereIn('cpo_dispatch_record_id', (clone $baseQuery)->select('id'))
            ->count();
        $recordsWithoutDetails = (clone $baseQuery)->doesntHave('cpoDispatchDetails')->count();

        if ($detailRowCount + $recordsWithoutDetails > self::EXPORT_ROW_LIMIT) {
            throw new ExportFailedException();
        }

        try {
            $query = $baseQuery
                ->withCount('cpoDispatchDetails')
                ->with([
                    'checkedBy:id,name',
                    'acknowledgedBy:id,name',
                    'cpoDispatchDetails' => fn ($detailQuery) => $detailQuery->orderBy('event_date'),
                ])
                ->orderByDesc('date')
                ->orderBy('id');

            [$contentType, $filename] = $this->fileMetaFor($format);

            return response()->streamDownload(function () use ($query) {
                $handle = fopen('php://output', 'w');

                // Header row. Explicit $separator/$enclosure/$escape (PHP 8.4
                // deprecates relying on fputcsv()'s default $escape). The
                // record's context columns repeat on every detail line, so the
                // file can be pivoted and filtered directly in a spreadsheet.
                fputcsv($handle, [
                    'CPO Dispatch ID',
                    'Date',
                    'Note',
                    'Checked By',
                    'Acknowledged By',
                    'Jumlah Kejadian',
                    'Status',
                    'Tanggal Kejadian',
                    'Shift',
                    'Time In',
                    'Time Out',
                    'Waybill Number',
                    'Tanker Plate No',
                    'Transport Company',
                    'Driver Name',
                    'Storage Tank Source',
                    'Seal No (Top)',
                    'Seal No (Bottom)',
                    'Gross Weight (MT)',
                    'Tare Weight (MT)',
                    'Net Weight (MT)',
                    'FFA (%)',
                    'Moisture (%)',
                    'Impurities (%)',
                    'DOBI',
                    'Destination/Buyer',
                    'Weighbridge Operator',
                    'Findings',
                ], ',', '"', '\\');

                $query->chunk(200, function ($records) use ($handle) {
                    foreach ($records as $record) {
                        /** @var CpoDispatchRecord $record */
                        $context = [
                            $record->cpo_dispatch_id,
                            optional($record->date)->toDateString(),
                            $record->note,
                            $record->checkedBy?->name,
                            $record->acknowledgedBy?->name,
                            $record->cpo_dispatch_details_count,
                            $record->status?->value,
                        ];

                        $details = $record->cpoDispatchDetails;

                        if ($details->isEmpty()) {
                            fputcsv($handle, array_merge($context, array_fill(0, 21, null)), ',', '"', '\\');

                            continue;
                        }

                        foreach ($details as $detail) {
                            /** @var CpoDispatchDetail $detail */
                            fputcsv($handle, array_merge($context, [
                                optional($detail->event_date)->toDateString(),
                                $detail->shift,
                                $detail->time_in,
                                $detail->time_out,
                                $detail->waybill_number,
                                $detail->tanker_plate_no,
                                $detail->transport_company,
                                $detail->driver_name,
                                $detail->storage_tank_source,
                                $detail->seal_no_top,
                                $detail->seal_no_bottom,
                                $detail->gross_weight_mt,
                                $detail->tare_weight_mt,
                                $detail->net_weight_mt,
                                $detail->ffa_percent,
                                $detail->moisture_percent,
                                $detail->impurities_percent,
                                $detail->dobi,
                                $detail->destination_buyer,
                                $detail->weighbridge_operator,
                                $detail->findings,
                            ]), ',', '"', '\\');
                        }
                    }
                });

                fclose($handle);
            }, $filename, [
                'Content-Type' => $contentType,
            ]);
        } catch (ExportFailedException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ExportFailedException();
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function fileMetaFor(string $format): array
    {
        $timestamp = now()->format('Ymd_His');

        if ($format === 'excel') {
            return [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                "cpo-dispatch-records_{$timestamp}.xlsx",
            ];
        }

        return [
            'text/csv',
            "cpo-dispatch-records_{$timestamp}.csv",
        ];
    }

    /**
     * @param  array{date_from?: ?string, date_to?: ?string, business_unit_id?: ?string}  $filters
     */
    protected function buildFilteredQuery(array $filters): Builder
    {
        // CAKUPAN MILL DULU, sebelum filter apa pun dibaca. Sampai
        // 2026-09-28 `business_unit_id` di sini datang mentah dari properti
        // Livewire Data Browser (default '') atau dari query string API,
        // dan nilai kosong berarti TANPA cakupan sama sekali — sehingga
        // Supervisor mana pun bisa melihat dan mengekspor record mill lain.
        // scopeFiltersToActorMill() MEMBUANG nilai kiriman klien untuk
        // aktor yang terikat mill dan menggantinya dengan mill aktor
        // sendiri, jadi mengirim mill lain lewat properti atau query string
        // tidak mengubah apa pun. Hanya Admin yang nilainya dipakai apa
        // adanya (kosong = semua mill, perilaku lama dipertahankan).
        //
        // Dipasang di buildFilteredQuery() karena listRecords() DAN
        // export() sama-sama lewat sini — satu titik untuk dua jalur baca.
        $filters = $this->scopeFiltersToActorMill($filters);

        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $businessUnitId = $filters['business_unit_id'] ?? null;

        if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
            throw new InvalidDateRangeException();
        }

        $query = CpoDispatchRecord::query();

        if ($businessUnitId) {
            $query->whereHas('station', function (Builder $stationQuery) use ($businessUnitId) {
                $stationQuery->where('business_unit_id', $businessUnitId);
            });
        }

        if ($dateFrom) {
            $query->whereDate('date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('date', '<=', $dateTo);
        }

        return $query;
    }

    protected function toListRow(CpoDispatchRecord $record): array
    {
        return [
            'id' => $record->id,
            'cpo_dispatch_id' => $record->cpo_dispatch_id,
            'date' => optional($record->date)->toDateString(),
            'event_count' => (int) $record->cpo_dispatch_details_count,
            'status' => $record->status?->value,
        ];
    }

    /**
     * getDetail() — findOrFail (404 via ModelNotFoundException, handled
     * globally by ApiExceptionHandler) then resolve station/createdBy/
     * checkedBy/acknowledgedBy to display names and the event-log detail
     * rows (ordered by event_date).
     */
    public function getDetail(string $id): array
    {
        // Cakupan mill diterapkan sebagai SCOPE QUERY, bukan cek 403
        // setelah row diambil: UUID milik mill lain jadi tidak ada sama
        // sekali, sehingga findOrFail() melempar ModelNotFoundException
        // yang semua pemanggil sudah tangani (API -> 404 NOT_FOUND, layar
        // Detail/Form Livewire -> state $notFound). Sampai 2026-09-28
        // jalur ini memuat record mill lain secara utuh bila UUID-nya
        // diketahui, dan 403 baru muncul saat save.
        $record = $this->scopeQueryToActorMill(
            CpoDispatchRecord::with([
                'station',
                'createdBy',
                'checkedBy',
                'acknowledgedBy',
                'cpoDispatchDetails',
            ])
        )->findOrFail($id);

        return $this->toDetailRow($record);
    }

    protected function toDetailRow(CpoDispatchRecord $record): array
    {
        return [
            'id' => $record->id,
            'station_id' => $record->station_id,
            'station_name' => $record->station?->name,
            'cpo_dispatch_id' => $record->cpo_dispatch_id,
            'date' => optional($record->date)->toDateString(),
            'note' => $record->note,
            'created_by_name' => $record->createdBy?->name,
            'checked_by_name' => $record->checkedBy?->name,
            'acknowledged_by_name' => $record->acknowledgedBy?->name,
            'status' => $record->status?->value,
            'created_at' => optional($record->created_at)->toIso8601String(),
            'updated_at' => optional($record->updated_at)->toIso8601String(),
            'details' => $record->cpoDispatchDetails
                ->sortBy('event_date')
                ->values()
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'event_date' => optional($row->event_date)->toDateString(),
                    'shift' => $row->shift,
                    'time_in' => $row->time_in,
                    'time_out' => $row->time_out,
                    'waybill_number' => $row->waybill_number,
                    'tanker_plate_no' => $row->tanker_plate_no,
                    'transport_company' => $row->transport_company,
                    'driver_name' => $row->driver_name,
                    'storage_tank_source' => $row->storage_tank_source,
                    'seal_no_top' => $row->seal_no_top,
                    'seal_no_bottom' => $row->seal_no_bottom,
                    'gross_weight_mt' => $row->gross_weight_mt,
                    'tare_weight_mt' => $row->tare_weight_mt,
                    'net_weight_mt' => $row->net_weight_mt,
                    'ffa_percent' => $row->ffa_percent,
                    'moisture_percent' => $row->moisture_percent,
                    'impurities_percent' => $row->impurities_percent,
                    'dobi' => $row->dobi,
                    'destination_buyer' => $row->destination_buyer,
                    'weighbridge_operator' => $row->weighbridge_operator,
                    'findings' => $row->findings,
                ])->all(),
        ];
    }
}
