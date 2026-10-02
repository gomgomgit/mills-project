<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Exceptions\InvalidDateRangeException;
use App\Exceptions\NoActiveSterilizerStationException;
use App\Models\SterilizerDetail;
use App\Models\SterilizerRecord;
use App\Models\User;
use App\Support\Concerns\EnforcesPeriodLock;
use App\Support\Concerns\ScopesToActorMill;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * SterilizerRecordService — screen-124--data-browser-sterilizer-web /
 * screen-125--detail-sterilizer-web / screen-126--form-sterilizer-web.
 *
 * Shared by both the API controller (App\Http\Controllers\Api\
 * SterilizerRecordController) and the Livewire components (App\
 * Livewire\Data\{DataBrowserSterilizer,DetailSterilizer,FormSterilizer}),
 * mirroring CpoDispatchRecordService's structure exactly — event-log
 * pattern: unbounded detail rows added manually per sterilization cycle,
 * no fixed grid/no per-row time-slot uniqueness constraint.
 *
 * `duration_minutes` is ALWAYS (re)computed here server-side from
 * close_door_time/open_door_time — never trusted from client input even
 * if sent (see computeDurationMinutes()).
 */
class SterilizerRecordService
{
    use EnforcesPeriodLock, ScopesToActorMill;

    public const EXPORT_ROW_LIMIT = 50000;

    protected const FORM_FIELDS = [
        'sterilizer_id', 'date', 'note',
    ];

    protected const DETAIL_FIELDS = [
        'sterilizer_no', 'close_door_time', 'peak_1_time', 'exhaust_1_time', 'peak_2_time',
        'exhaust_2_time', 'peak_3_time', 'exhaust_3_time', 'open_door_time',
        'number_of_cages', 'cages_status', 'checked_by_spv', 'remarks',
    ];

    /**
     * create() — resolve the active Sterilizer station from
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
            'sterilizer',
            $actor,
        );

        if ($station === null) {
            throw new NoActiveSterilizerStationException();
        }

        // KUNCI PERIODE (usecase-141) — sebelum satu baris pun ditulis, supaya
        // penolakan tidak menyisakan induk tanpa detail. Jenis stasiun dan mill
        // diambil dari stasiun yang SUDAH di-resolve, bukan dari request.
        $this->assertPeriodOpenForWrite('sterilizer', $station->business_unit_id, $attributes['date'] ?? null);

        $attributes['station_id'] = $station->id;
        // Snapshot the line from the RESOLVED STATION, never from the
        // request: the client sends `production_line_id` only to SELECT the
        // station, and trusting it back would let a record store a line
        // different from its own station's — the class of bug the mill-scope
        // guard above just closed. Stored (not derived at read time) so that
        // moving this station to another line later cannot rewrite this
        // record's history. See 2026_09_28_000041.
        $attributes['production_line_id'] = $station->production_line_id;
        $attributes['created_by'] = $actor->id;
        $attributes['status'] = 'saved';
        $this->applyVerification($attributes, $data, $actor);

        $record = DB::transaction(function () use ($attributes, $details) {
            $record = SterilizerRecord::create($attributes);
            $this->upsertDetails($record, $details);

            return $record;
        });

        $record->load(['station', 'createdBy', 'checkedBy', 'acknowledgedBy', 'sterilizerDetails']);

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
        $record = SterilizerRecord::findOrFail($id);

        $this->assertRecordWritableByActor($record, $actor);

        $attributes = $this->normalizeFormFields($data);
        $details = $this->normalizeDetails($data['details'] ?? []);

        $this->validateForm($attributes);
        $this->validateDetails($details);

        // KUNCI PERIODE (usecase-141) — DUA tanggal diperiksa, bukan satu.
        // Mengubah tanggal sebuah record berarti mengeluarkannya dari periode
        // lama dan memasukkannya ke periode baru, dan mengeluarkan satu baris
        // dari periode yang sudah ditutup menggeser angka laporannya sama
        // nyatanya dengan menambah baris ke dalamnya. Jadi kedua ujung
        // perpindahan harus berada di periode yang terbuka. Memverifikasi
        // (checked/acknowledged) lewat jalur ini ikut terkunci, sesuai spec.
        $record->loadMissing('station');
        $millId = $record->station->business_unit_id;

        $this->assertPeriodOpenForWrite('sterilizer', $millId, optional($record->date)->toDateString());
        $this->assertPeriodOpenForWrite('sterilizer', $millId, $attributes['date'] ?? null);

        $this->applyVerification($attributes, $data, $actor);

        DB::transaction(function () use ($record, $attributes, $details) {
            $record->update($attributes);
            $this->upsertDetails($record, $details);
        });

        $record->load(['station', 'createdBy', 'checkedBy', 'acknowledgedBy', 'sterilizerDetails']);

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
            'sterilizer_id' => ['required', 'string'],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ], [
            'sterilizer_id.required' => 'Sterilizer ID wajib diisi.',
            'date.required' => 'Tanggal wajib diisi.',
        ])->validate();
    }

    /**
     * validateDetails() — at least one valid detail row (close_door_time
     * filled in) must exist. Unlike a grid station, there is no time-slot
     * uniqueness or ascending-order constraint — rows are a free event
     * log, added manually per occurrence, unbounded per day.
     */
    protected function validateDetails(array $details): void
    {
        $validRows = collect($details)->filter(fn ($row) => filled($row['close_door_time'] ?? null));

        if ($validRows->isEmpty()) {
            throw ValidationException::withMessages([
                'details' => 'Minimal satu baris log Sterilizer (Close Door Time wajib) harus diisi.',
            ]);
        }
    }

    /**
     * computeDurationMinutes() — server-side ONLY derivation of
     * duration_minutes = open_door_time - close_door_time (in minutes),
     * per entity-catalog: "BUKAN kolom yang diinput user secara langsung".
     * Never trusts a client-supplied duration_minutes value even if sent.
     * Handles a cycle crossing midnight (open < close) by wrapping to the
     * next day. Returns null when either time is missing/unparseable.
     */
    protected function computeDurationMinutes(?string $closeDoorTime, ?string $openDoorTime): ?int
    {
        if (blank($closeDoorTime) || blank($openDoorTime)) {
            return null;
        }

        try {
            $close = Carbon::createFromFormat('H:i', substr($closeDoorTime, 0, 5));
            $open = Carbon::createFromFormat('H:i', substr($openDoorTime, 0, 5));
        } catch (Throwable) {
            return null;
        }

        $minutes = $close->diffInMinutes($open, false);

        if ($minutes < 0) {
            $minutes += 24 * 60;
        }

        return $minutes;
    }

    /**
     * upsertDetails() — for each valid detail row, compute duration_minutes
     * server-side (never accepted from client), then insert rows without
     * an id, update rows whose id still appears in $details, and delete
     * any existing row whose id is no longer present.
     *
     * `checked_by_spv` is coerced to a plain boolean (never left as an
     * empty-string, which SQLite would reject for a BOOLEAN/INTEGER
     * column).
     */
    protected function upsertDetails(SterilizerRecord $record, array $details): void
    {
        $validRows = collect($details)->filter(fn ($row) => filled($row['close_door_time'] ?? null));

        $keptIds = [];

        foreach ($validRows as $row) {
            $durationMinutes = $this->computeDurationMinutes($row['close_door_time'] ?? null, $row['open_door_time'] ?? null);

            $detailAttributes = [
                'sterilizer_record_id' => $record->id,
                'sterilizer_no' => $row['sterilizer_no'],
                'close_door_time' => $row['close_door_time'] ?: null,
                'peak_1_time' => $row['peak_1_time'] ?: null,
                'exhaust_1_time' => $row['exhaust_1_time'] ?: null,
                'peak_2_time' => $row['peak_2_time'] ?: null,
                'exhaust_2_time' => $row['exhaust_2_time'] ?: null,
                'peak_3_time' => $row['peak_3_time'] ?: null,
                'exhaust_3_time' => $row['exhaust_3_time'] ?: null,
                'open_door_time' => $row['open_door_time'] ?: null,
                'duration_minutes' => $durationMinutes,
                'number_of_cages' => $row['number_of_cages'] !== null && $row['number_of_cages'] !== ''
                    ? (int) $row['number_of_cages']
                    : null,
                'cages_status' => $row['cages_status'],
                'checked_by_spv' => (bool) ($row['checked_by_spv'] ?? false),
                'remarks' => $row['remarks'],
            ];

            if (! empty($row['id']) && SterilizerDetail::where('id', $row['id'])->where('sterilizer_record_id', $record->id)->exists()) {
                SterilizerDetail::where('id', $row['id'])->update($detailAttributes);
                $keptIds[] = $row['id'];
            } else {
                $detail = SterilizerDetail::create($detailAttributes);
                $keptIds[] = $detail->id;
            }
        }

        SterilizerDetail::where('sterilizer_record_id', $record->id)
            ->whereNotIn('id', $keptIds)
            ->delete();
    }

    /**
     * applyVerification() — Checked By (Supervisor) and Acknowledged By
     * (Mill Management) self-attestation checkboxes, mirroring
     * CpoDispatchRecordService. NOTE: unrelated to detail-row
     * `checked_by_spv`, which is a plain boolean, not a user reference.
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
     * (with cycle_count computed via withCount()), paginate, and return
     * the {data, meta} shape.
     *
     * @param  array{date_from?: ?string, date_to?: ?string, business_unit_id?: ?string, production_line_id?: ?string}  $filters
     */
    public function listRecords(array $filters, int $page, int $perPage): array
    {
        $query = $this->buildFilteredQuery($filters)
            ->withCount('sterilizerDetails')
            ->orderByDesc('date');

        // `productionLine` dimuat di sini, bukan lewat relasi `station`:
        // kolom line milik record sendiri adalah sumber kebenarannya.
        $paginator = $query->with('productionLine:id,name')->paginate(perPage: $perPage, page: $page);

        $formatted = Pagination::format($paginator);
        $formatted['data'] = collect($formatted['data'])
            ->map(fn (SterilizerRecord $record) => $this->toListRow($record))
            ->all();

        return $formatted;
    }

    /**
     * export() — re-run the same filter query (no pagination), enforce
     * the row limit, generate a CSV body, and return it as a
     * StreamedResponse for download.
     *
     * @param  array{date_from?: ?string, date_to?: ?string, business_unit_id?: ?string, production_line_id?: ?string}  $filters
     */
    public function export(array $filters, string $format): StreamedResponse
    {
        $baseQuery = $this->buildFilteredQuery($filters);

        // The row limit counts EXPORTED lines, not header records: the file
        // writes one line per detail row, plus a single line for a record that
        // has no detail rows at all so an empty day stays visible.
        $detailRowCount = SterilizerDetail::query()
            ->whereIn('sterilizer_record_id', (clone $baseQuery)->select('id'))
            ->count();
        $recordsWithoutDetails = (clone $baseQuery)->doesntHave('sterilizerDetails')->count();

        if ($detailRowCount + $recordsWithoutDetails > self::EXPORT_ROW_LIMIT) {
            throw new ExportFailedException();
        }

        try {
            $query = $baseQuery
                ->withCount('sterilizerDetails')
                ->with([
                    'productionLine:id,name',
                    'checkedBy:id,name',
                    'acknowledgedBy:id,name',
                    'sterilizerDetails' => fn ($detailQuery) => $detailQuery->orderBy('sterilizer_no'),
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
                    'Production Line',
                    'Sterilizer ID',
                    'Date',
                    'Note',
                    'Checked By',
                    'Acknowledged By',
                    'Jumlah Siklus',
                    'Status',
                    'Sterilizer No',
                    'Close Door Time',
                    'Peak 1 Time',
                    'Exhaust 1 Time',
                    'Peak 2 Time',
                    'Exhaust 2 Time',
                    'Peak 3 Time',
                    'Exhaust 3 Time',
                    'Open Door Time',
                    'Duration (Minutes)',
                    'Number of Cages',
                    'Cages Status',
                    'Checked by SPV',
                    'Remarks',
                ], ',', '"', '\\');

                $query->chunk(200, function ($records) use ($handle) {
                    foreach ($records as $record) {
                        /** @var SterilizerRecord $record */
                        $context = [
                            $record->productionLine?->name,
                            $record->sterilizer_id,
                            optional($record->date)->toDateString(),
                            $record->note,
                            $record->checkedBy?->name,
                            $record->acknowledgedBy?->name,
                            $record->sterilizer_details_count,
                            $record->status?->value,
                        ];

                        $details = $record->sterilizerDetails;

                        if ($details->isEmpty()) {
                            fputcsv($handle, array_merge($context, array_fill(0, 14, null)), ',', '"', '\\');

                            continue;
                        }

                        foreach ($details as $detail) {
                            /** @var SterilizerDetail $detail */
                            fputcsv($handle, array_merge($context, [
                                $detail->sterilizer_no,
                                $detail->close_door_time,
                                $detail->peak_1_time,
                                $detail->exhaust_1_time,
                                $detail->peak_2_time,
                                $detail->exhaust_2_time,
                                $detail->peak_3_time,
                                $detail->exhaust_3_time,
                                $detail->open_door_time,
                                $detail->duration_minutes,
                                $detail->number_of_cages,
                                $detail->cages_status,
                                $detail->checked_by_spv,
                                $detail->remarks,
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
                "sterilizer-records_{$timestamp}.xlsx",
            ];
        }

        return [
            'text/csv',
            "sterilizer-records_{$timestamp}.csv",
        ];
    }

    /**
     * @param  array{date_from?: ?string, date_to?: ?string, business_unit_id?: ?string, production_line_id?: ?string}  $filters
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
        $productionLineId = $filters['production_line_id'] ?? null;

        if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
            throw new InvalidDateRangeException();
        }

        $query = SterilizerRecord::query();

        if ($businessUnitId) {
            $query->whereHas('station', function (Builder $stationQuery) use ($businessUnitId) {
                $stationQuery->where('business_unit_id', $businessUnitId);
            });
        }

        // PENYARINGAN PER LINE LANGSUNG DI TABEL RECORD, bukan lewat
        // whereHas('station', ...). Sejak 2026_09_28_000041 setiap tabel
        // record punya kolom `production_line_id` sendiri (NOT NULL,
        // di-snapshot dari stasiun saat create), jadi tidak perlu subquery
        // per halaman — dan, yang jauh lebih penting, nilainya PERMANEN:
        // stasiun yang kemudian dipindah ke line lain tidak menarik record
        // lamanya ikut pindah. Menyaring lewat station akan menyaring
        // menurut konfigurasi HARI INI, bukan menurut line tempat data itu
        // benar-benar dihasilkan.
        //
        // Nilainya sudah dijepit ke mill aktor oleh scopeFiltersToActorMill()
        // di atas: line milik mill lain sudah menjadi null di sana (jatuh ke
        // "semua line"), jadi baris ini tidak pernah bisa memperluas cakupan.
        if ($productionLineId) {
            $query->where('production_line_id', $productionLineId);
        }

        if ($dateFrom) {
            $query->whereDate('date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('date', '<=', $dateTo);
        }

        return $query;
    }

    protected function toListRow(SterilizerRecord $record): array
    {
        return [
            'id' => $record->id,
            'sterilizer_id' => $record->sterilizer_id,
            'date' => optional($record->date)->toDateString(),
            'cycle_count' => (int) $record->sterilizer_details_count,
            'production_line_name' => $record->productionLine?->name,
            'status' => $record->status?->value,
        ];
    }

    /**
     * getDetail() — findOrFail (404 via ModelNotFoundException, handled
     * globally by ApiExceptionHandler) then resolve station/createdBy/
     * checkedBy/acknowledgedBy to display names and the event-log detail
     * rows (ordered by created_at).
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
            SterilizerRecord::with([
                'station',
                'createdBy',
                'checkedBy',
                'acknowledgedBy',
                'sterilizerDetails',
            ])
        )->findOrFail($id);

        return $this->toDetailRow($record);
    }

    protected function toDetailRow(SterilizerRecord $record): array
    {
        return [
            'id' => $record->id,
            'station_id' => $record->station_id,
            'station_name' => $record->station?->name,
            'sterilizer_id' => $record->sterilizer_id,
            'date' => optional($record->date)->toDateString(),
            'note' => $record->note,
            'created_by_name' => $record->createdBy?->name,
            'checked_by_name' => $record->checkedBy?->name,
            'acknowledged_by_name' => $record->acknowledgedBy?->name,
            'status' => $record->status?->value,
            'created_at' => optional($record->created_at)->toIso8601String(),
            'updated_at' => optional($record->updated_at)->toIso8601String(),
            'details' => $record->sterilizerDetails
                ->sortBy('created_at')
                ->values()
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'sterilizer_no' => $row->sterilizer_no,
                    'close_door_time' => $row->close_door_time,
                    'peak_1_time' => $row->peak_1_time,
                    'exhaust_1_time' => $row->exhaust_1_time,
                    'peak_2_time' => $row->peak_2_time,
                    'exhaust_2_time' => $row->exhaust_2_time,
                    'peak_3_time' => $row->peak_3_time,
                    'exhaust_3_time' => $row->exhaust_3_time,
                    'open_door_time' => $row->open_door_time,
                    'duration_minutes' => $row->duration_minutes,
                    'number_of_cages' => $row->number_of_cages,
                    'cages_status' => $row->cages_status,
                    'checked_by_spv' => (bool) $row->checked_by_spv,
                    'remarks' => $row->remarks,
                ])->all(),
        ];
    }
}
