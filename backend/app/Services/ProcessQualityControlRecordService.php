<?php

namespace App\Services;

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Exceptions\InvalidDateRangeException;
use App\Exceptions\NoActiveProcessQualityControlStationException;
use App\Models\ProcessQualityControlDetail;
use App\Models\ProcessQualityControlRecord;
use App\Models\User;
use App\Support\Concerns\EnforcesPeriodLock;
use App\Support\Concerns\NormalizesTimeSlot;
use App\Support\Concerns\ScopesToActorMill;
use App\Support\ExportValue;
use App\Support\Pagination;
use App\Support\SheetWriter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * ProcessQualityControlRecordService — screen-100--data-browser-process-quality-control-web /
 * screen-110--detail-process-quality-control-web / screen-120--form-process-quality-control-web.
 *
 * Shared by both the API controller (App\Http\Controllers\Api\
 * ProcessQualityControlRecordController) and the Livewire components (App\Livewire\
 * Data\{DataBrowserProcessQualityControl,DetailProcessQualityControl,FormProcessQualityControl}),
 * mirroring ClarificationRecordService/ProcessWaterRecordService's structure exactly — Process
 * Quality Control follows the same "hourly grid" pattern as Clarification/Boiler Room/Engine
 * Room/Storage Tank/Effluent Plant: `details` may contain any number of rows (1..24), each with
 * a unique `time_slot` in strictly ascending canonical order (validateDetails()); create()
 * INSERTs exactly the given rows; update() upserts (rows with an existing `id` kept in `$details`
 * are UPDATEd, rows without one are INSERTed, and any existing row whose id is no longer present
 * in `$details` is DELETEd).
 *
 * UNLIKE Threshing/Pressing/Depricarping/Kernel Plant: Process Quality Control has NO
 * operational-target reference table (no seeder/model, no Target Operasional section on any of
 * its 6 screens) — same deliberate scope difference as Clarification/Boiler Room/Engine
 * Room/Storage Tank/Effluent Plant.
 *
 * "Filled" row (filled_slot_count / minimum-one-row validation): a process_quality_control_detail
 * row counts as filled when at least one of its READING_FIELDS (12 numeric columns across Fruit
 * Press/Purifier & Clarification Balance/Vacuum Drying Station/Decanter-Centrifuge/Final Storage,
 * plus qc_engineering_corrective_actions and findings) is non-null — `shift` and `qc_inspector_id`
 * are identifying/context columns, not readings, so they are NOT part of the "filled" check
 * (mirrors ProcessWaterRecordService's exclusion of shift/inspector_id).
 *
 * THIS STATION HAS THE MOST NON-TIME_SLOT COLUMNS IN THE PROJECT (16) — all 12 numeric readings
 * are genuine floats, shift/qc_inspector_id/qc_engineering_corrective_actions/findings are plain
 * strings — there are NO enum/status columns at all, so no empty-string-to-null enum coercion is
 * needed anywhere in this service.
 */
class ProcessQualityControlRecordService
{
    use EnforcesPeriodLock, NormalizesTimeSlot, ScopesToActorMill;

    public const EXPORT_ROW_LIMIT = 50000;

    protected const FORM_FIELDS = ['process_qc_id', 'date', 'note'];

    protected const READING_FIELDS = [
        'fruit_press_oil_loss_in_sludge_percent', 'fruit_press_oil_loss_in_fibre_percent',
        'purifier_clarification_balance_inlet_temp_c', 'purifier_clarification_balance_backpressure_bar',
        'vacuum_drying_station_drier_temp_c', 'vacuum_drying_station_vacuum_pressure_bar',
        'decanter_centrifuge_feed_rate_mth', 'decanter_centrifuge_oil_loss_in_cake_percent',
        'final_storage_ffa_percent', 'final_storage_moisture_content_percent',
        'final_storage_impurities_dirt_percent', 'final_storage_dobi_index',
        'qc_engineering_corrective_actions', 'findings',
    ];

    protected const DETAIL_FIELDS = ['shift', 'qc_inspector_id', ...self::READING_FIELDS];

    /**
     * The 24 canonical hourly time-slot labels, in order: 07:00, 08:00,
     * ..., 23:00, 00:00, ..., 06:00. Mirrors mobile's
     * processQualityControlRecordRepo.ts's canonicalTimeSlots() exactly (`for i in
     * 0..23 -> hour = (7 + i) % 24`), so web and mobile always agree on
     * row order/coverage.
     *
     * @return array<int, string>
     */
    public static function canonicalTimeSlots(): array
    {
        return collect(range(0, 23))
            ->map(fn (int $i) => sprintf('%02d:00', (7 + $i) % 24))
            ->all();
    }

    /**
     * create() — validate header + details (at least one valid row, unique
     * + strictly ascending canonical time_slot order), resolve station from
     * production_line_id, then INSERT the record and its
     * process_quality_control_detail rows inside a DB transaction.
     */
    public function create(array $data, User $actor): array
    {
        $attributes = $this->normalizeFormFields($data);
        $details = $this->normalizeDetails($data['details'] ?? []);

        $this->validateForm($attributes);
        $this->validateDetails($details);

        $station = $this->resolveActiveStationForActor(
            $data['production_line_id'] ?? null,
            'process-quality-control',
            $actor,
        );

        if ($station === null) {
            throw new NoActiveProcessQualityControlStationException;
        }

        // KUNCI PERIODE (usecase-141) — sebelum satu baris pun ditulis, supaya
        // penolakan tidak menyisakan induk tanpa detail. Jenis stasiun dan mill
        // diambil dari stasiun yang SUDAH di-resolve, bukan dari request.
        // BATAS ATAS TANGGAL (2026-10-04) — lihat EnforcesPeriodLock::assertEventDateNotTooFarAhead().
        $this->assertEventDateNotTooFarAhead($attributes['date'] ?? null, 'date', 'Tanggal');
        $this->assertPeriodOpenForWrite('process-quality-control', $station->business_unit_id, $attributes['date'] ?? null);

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
        $this->applyVerification($attributes, $data, $actor);

        $record = DB::transaction(function () use ($attributes, $details) {
            $attributes['status'] = RecordStatus::Synced;
            $record = ProcessQualityControlRecord::create($attributes);
            $this->upsertDetails($record, $details);
            $record->update(['status' => 'saved']);

            return $record;
        });

        $record->load(['station', 'createdBy', 'checkedBy', 'acknowledgedBy', 'processQualityControlDetails']);

        return $this->toDetailRow($record);
    }

    /**
     * update() — validate header + details (at least one valid row, unique
     * + strictly ascending canonical time_slot order), UPDATE the record,
     * then upsert its process_quality_control_detail rows (insert rows without an
     * id, update rows whose id still appears in $details, delete any existing
     * row whose id is no longer present) inside a DB transaction — mirrors
     * ClarificationRecordService::update() exactly.
     */
    public function update(string $id, array $data, User $actor): array
    {
        $record = ProcessQualityControlRecord::findOrFail($id);

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
        // perpindahan harus berada di periode yang terbuka. Verifikasi
        // (checked/acknowledged) lewat jalur ini ikut terkunci, sesuai spec.
        $record->loadMissing('station');
        $periodLockMillId = $record->station->business_unit_id;

        $this->assertPeriodOpenForWrite('process-quality-control', $periodLockMillId, optional($record->date)->toDateString());
        // BATAS ATAS TANGGAL (2026-10-04) — lihat EnforcesPeriodLock::assertEventDateNotTooFarAhead().
        $this->assertEventDateNotTooFarAhead($attributes['date'] ?? null, 'date', 'Tanggal');
        $this->assertPeriodOpenForWrite('process-quality-control', $periodLockMillId, $attributes['date'] ?? null);

        $this->applyVerification($attributes, $data, $actor);

        DB::transaction(function () use ($record, $attributes, $details) {
            $record->update($attributes);
            $this->upsertDetails($record, $details);
        });

        $record->load(['station', 'createdBy', 'checkedBy', 'acknowledgedBy', 'processQualityControlDetails']);

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
                $normalized = [
                    'id' => $row['id'] ?? null,
                    'time_slot' => $this->canonicalTimeSlot($row['time_slot'] ?? null),
                ];

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
            'process_qc_id' => ['required', 'string'],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ], [
            'process_qc_id.required' => 'Process QC ID wajib diisi.',
            'date.required' => 'Tanggal wajib diisi.',
        ])->validate();
    }

    /**
     * validateDetails() — at least one valid detail row (a `time_slot` from
     * the 24 canonical slots AND at least one of its READING_FIELDS non-null)
     * must exist, and every row's `time_slot` must be strictly ascending
     * (canonical order) and unique across the array — mirrors
     * ClarificationRecordService::validateDetails() exactly.
     */
    protected function validateDetails(array $details): void
    {
        $canonicalOrder = array_flip(self::canonicalTimeSlots());

        $validRows = collect($details)->filter(
            fn ($row) => $row['time_slot'] !== null && $row['time_slot'] !== '' && $this->isRowFilled($row)
        )->values();

        if ($validRows->isEmpty()) {
            throw ValidationException::withMessages([
                'details' => 'Minimal satu baris Process Quality Control Detail (Time-Slot terpilih + minimal 1 kolom bacaan/keterangan terisi) harus diisi.',
            ]);
        }

        $slots = $validRows->pluck('time_slot');

        if ($slots->contains(fn ($slot) => ! array_key_exists($slot, $canonicalOrder))) {
            throw ValidationException::withMessages([
                'details' => 'Time-Slot Process Quality Control Detail harus salah satu dari 24 slot kanonis (07:00-06:00).',
            ]);
        }

        if ($slots->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'details' => 'Time-Slot tiap baris Process Quality Control Detail tidak boleh duplikat.',
            ]);
        }

        $sortedIndexes = $slots->map(fn ($slot) => $canonicalOrder[$slot])->values();

        foreach ($sortedIndexes as $index => $slotIndex) {
            if ($index > 0 && $slotIndex <= $sortedIndexes[$index - 1]) {
                throw ValidationException::withMessages([
                    'details' => 'Time-Slot tiap baris Process Quality Control Detail harus lebih besar dari baris sebelumnya (urutan menaik).',
                ]);
            }
        }
    }

    /**
     * A row counts as "filled" when at least one of its READING_FIELDS
     * (excludes `shift`/`qc_inspector_id`, which are context/identifying
     * columns, not readings) is non-null/non-empty.
     */
    protected function isRowFilled(array $row): bool
    {
        foreach (self::READING_FIELDS as $field) {
            $value = $row[$field] ?? null;

            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * upsertDetails() — for each valid detail row (a selected `time_slot`
     * AND at least one reading column filled — same filter as
     * validateDetails()), insert rows without an id, update rows whose id
     * still appears in $details, and delete any existing row whose id is
     * no longer present — mirrors ClarificationRecordService::upsertDetails()
     * exactly.
     */
    protected function upsertDetails(ProcessQualityControlRecord $record, array $details): void
    {
        $validRows = collect($details)->filter(
            fn ($row) => $row['time_slot'] !== null && $row['time_slot'] !== '' && $this->isRowFilled($row)
        );

        // URUTAN MENENTUKAN — baris basi DIHAPUS SEBELUM baris baru
        // disisipkan. Sampai 2026-09-28 urutannya terbalik di stasiun ini,
        // dan dengan UNIQUE(process_quality_control_record_id, time_slot)
        // (`pqc_details_record_time_slot_unique`) pada tabel detail itu
        // berarti memindahkan sebuah pembacaan ke slot yang SEDANG DIPAKAI
        // baris lain yang akan dihapus melanggar constraint dan melempar
        // UniqueConstraintViolationException. Itu operasi harian: Operator
        // salah pilih slot lalu membetulkannya.
        //
        // Pola ini sudah diperbaiki di 11 service lain pada 2026-09-25;
        // Process Quality Control terlewat karena nama index-nya menyimpang
        // dari pola penamaan default (dipendekkan jadi `pqc_details_...`
        // di 2026_08_31_000021), sehingga pencarian berbasis nama tidak
        // menemukannya.
        //
        // Keep-set dihitung dari ID yang SUDAH ADA di payload saja. Baris
        // baru belum punya ID pada titik ini dan memang tidak perlu
        // dipertahankan — tidak ada baris lama yang mewakilinya. Memasukkan
        // ID hasil create() ke sini (bentuk lama) itulah yang memaksa
        // delete berjalan belakangan.
        $keptIds = $validRows
            ->pluck('id')
            ->filter()
            ->values()
            ->all();

        ProcessQualityControlDetail::where('process_quality_control_record_id', $record->id)
            ->whereNotIn('id', $keptIds)
            ->delete();

        foreach ($validRows as $row) {
            $detailAttributes = ['process_quality_control_record_id' => $record->id, 'time_slot' => $row['time_slot']];

            foreach (self::DETAIL_FIELDS as $field) {
                $detailAttributes[$field] = $row[$field];
            }

            if (! empty($row['id']) && Str::isUuid((string) $row['id']) && ProcessQualityControlDetail::where('id', $row['id'])->where('process_quality_control_record_id', $record->id)->exists()) {
                ProcessQualityControlDetail::where('id', $row['id'])->update($detailAttributes);
            } else {
                ProcessQualityControlDetail::create($detailAttributes);
            }
        }
    }

    /**
     * applyVerification() — BOTH Checked By (Supervisor) and Acknowledged
     * By (Mill Management) are self-attestation checkboxes, mirrors
     * ClarificationRecordService::applyVerification() exactly.
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
     * (with filled_slot_count computed via withCount()), paginate, return
     * the {data, meta} shape.
     *
     * @param  array{date_from?: ?string, date_to?: ?string, business_unit_id?: ?string, production_line_id?: ?string}  $filters
     */
    public function listRecords(array $filters, int $page, int $perPage): array
    {
        $readingFields = self::READING_FIELDS;

        $query = $this->buildFilteredQuery($filters)
            ->withCount(['processQualityControlDetails as filled_slot_count' => function (Builder $detailQuery) use ($readingFields) {
                $detailQuery->where(function (Builder $q) use ($readingFields) {
                    foreach ($readingFields as $field) {
                        $q->orWhereNotNull($field);
                    }
                });
            }])
            ->orderByDesc('date');

        // `productionLine` dimuat di sini, bukan lewat relasi `station`:
        // kolom line milik record sendiri adalah sumber kebenarannya.
        $paginator = $query->with('productionLine:id,name')->paginate(perPage: $perPage, page: $page);

        $formatted = Pagination::format($paginator);
        $formatted['data'] = collect($formatted['data'])
            ->map(fn (ProcessQualityControlRecord $record) => $this->toListRow($record))
            ->all();

        return $formatted;
    }

    /**
     * export() — re-run the filter query (unpaginated), enforce the row
     * limit, generate a CSV body.
     *
     * @param  array{date_from?: ?string, date_to?: ?string, business_unit_id?: ?string, production_line_id?: ?string}  $filters
     */
    public function export(array $filters, string $format): StreamedResponse
    {
        $baseQuery = $this->buildFilteredQuery($filters);

        // The row limit counts EXPORTED lines, not header records: the file
        // writes one line per detail row, plus a single line for a record that
        // has no detail rows at all so an empty day stays visible.
        $detailRowCount = ProcessQualityControlDetail::query()
            ->whereIn('process_quality_control_record_id', (clone $baseQuery)->select('id'))
            ->count();
        $recordsWithoutDetails = (clone $baseQuery)->doesntHave('processQualityControlDetails')->count();

        if ($detailRowCount + $recordsWithoutDetails > self::EXPORT_ROW_LIMIT) {
            throw new ExportFailedException;
        }

        try {
            $query = $baseQuery
                ->with([
                    'productionLine:id,name',
                    'checkedBy:id,name',
                    'acknowledgedBy:id,name',
                    'processQualityControlDetails' => fn ($detailQuery) => $detailQuery->orderBy('time_slot'),
                ])
                ->orderByDesc('date')
                ->orderBy('id');

            [$contentType, $filename] = $this->fileMetaFor($format);

            return response()->streamDownload(function () use ($query, $format) {
                $handle = SheetWriter::open($format);

                // Header row. The
                // record's context columns repeat on every detail line, so the
                // file can be pivoted and filtered directly in a spreadsheet.
                $handle->row([
                    'Production Line',
                    'Process QC ID',
                    'Date',
                    'Note',
                    'Checked By',
                    'Acknowledged By',
                    'Status',
                    'Time-Slot',
                    'Shift',
                    'Fruit Press Oil Loss in Sludge (%)',
                    'Fruit Press Oil Loss in Fibre (%)',
                    'Purifier & Clarification Balance Inlet Temp (°C)',
                    'Purifier & Clarification Balance Backpressure (Bar)',
                    'Vacuum Drying Station Drier Temp (°C)',
                    'Vacuum Drying Station Vacuum Pressure (Bar)',
                    'Decanter/Centrifuge Feed Rate (MT/h)',
                    'Decanter/Centrifuge Oil Loss in Cake (%)',
                    'Final Storage FFA (%)',
                    'Final Storage Moisture Content (%)',
                    'Final Storage Impurities/Dirt (%)',
                    'Final Storage DOBI Index',
                    'QC Inspector ID',
                    'QC Engineering Corrective Actions/Remarks',
                    'Findings',
                ]);

                $query->chunk(200, function ($records) use ($handle) {
                    foreach ($records as $record) {
                        /** @var ProcessQualityControlRecord $record */
                        $context = [
                            $record->productionLine?->name,
                            $record->process_qc_id,
                            optional($record->date)->toDateString(),
                            $record->note,
                            $record->checkedBy?->name,
                            $record->acknowledgedBy?->name,
                            ExportValue::status($record->status),
                        ];

                        $details = $record->processQualityControlDetails;

                        if ($details->isEmpty()) {
                            $handle->row(array_merge($context, array_fill(0, 17, null)));

                            continue;
                        }

                        foreach ($details as $detail) {
                            /** @var ProcessQualityControlDetail $detail */
                            $handle->row(array_merge($context, [
                                ExportValue::time($detail->time_slot),
                                $detail->shift,
                                $detail->fruit_press_oil_loss_in_sludge_percent,
                                $detail->fruit_press_oil_loss_in_fibre_percent,
                                $detail->purifier_clarification_balance_inlet_temp_c,
                                $detail->purifier_clarification_balance_backpressure_bar,
                                $detail->vacuum_drying_station_drier_temp_c,
                                $detail->vacuum_drying_station_vacuum_pressure_bar,
                                $detail->decanter_centrifuge_feed_rate_mth,
                                $detail->decanter_centrifuge_oil_loss_in_cake_percent,
                                $detail->final_storage_ffa_percent,
                                $detail->final_storage_moisture_content_percent,
                                $detail->final_storage_impurities_dirt_percent,
                                $detail->final_storage_dobi_index,
                                $detail->qc_inspector_id,
                                $detail->qc_engineering_corrective_actions,
                                $detail->findings,
                            ]));
                        }
                    }
                });

                $handle->close();
            }, $filename, [
                'Content-Type' => $contentType,
            ]);
        } catch (ExportFailedException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new ExportFailedException;
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
                "process-quality-control-records_{$timestamp}.xlsx",
            ];
        }

        return [
            'text/csv',
            "process-quality-control-records_{$timestamp}.csv",
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
            throw new InvalidDateRangeException;
        }

        $query = ProcessQualityControlRecord::query();

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

    /**
     * Maps a ProcessQualityControlRecord (with filled_slot_count pre-loaded via
     * withCount()) to the list endpoint's success_schema row shape.
     */
    protected function toListRow(ProcessQualityControlRecord $record): array
    {
        return [
            'id' => $record->id,
            'process_qc_id' => $record->process_qc_id,
            'date' => optional($record->date)->toDateString(),
            'filled_slot_count' => (int) $record->filled_slot_count,
            'production_line_name' => $record->productionLine?->name,
            'status' => $record->status?->value,
        ];
    }

    /**
     * getDetail() — findOrFail (404 via ModelNotFoundException) then
     * resolve station/createdBy/checkedBy/acknowledgedBy to display names
     * and the processQualityControlDetails grid, sorted into canonical time-slot
     * order (not alphabetical — '00:00' would otherwise sort before
     * '07:00').
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
            ProcessQualityControlRecord::with([
                'station',
                'createdBy',
                'checkedBy',
                'acknowledgedBy',
                'processQualityControlDetails',
            ])
        )->findOrFail($id);

        return $this->toDetailRow($record);
    }

    /**
     * Maps a ProcessQualityControlRecord to the detail endpoint's success_schema —
     * every header field plus station_name/created_by_name/
     * checked_by_name/acknowledged_by_name, plus the `details` array
     * (however many rows exist, canonical time-slot order, rendered
     * directly from stored columns — historical data, not recomputed).
     */
    protected function toDetailRow(ProcessQualityControlRecord $record): array
    {
        $canonicalOrder = array_flip(self::canonicalTimeSlots());

        $details = $record->processQualityControlDetails
            ->sortBy(fn (ProcessQualityControlDetail $row) => $canonicalOrder[$row->time_slot] ?? 999)
            ->values()
            ->map(function (ProcessQualityControlDetail $row) {
                $mapped = ['id' => $row->id, 'time_slot' => $row->time_slot];

                foreach (self::DETAIL_FIELDS as $field) {
                    $mapped[$field] = $row->{$field};
                }

                return $mapped;
            })
            ->all();

        return [
            'id' => $record->id,
            'station_id' => $record->station_id,
            'station_name' => $record->station?->name,
            'process_qc_id' => $record->process_qc_id,
            'date' => optional($record->date)->toDateString(),
            'note' => $record->note,
            'created_by_name' => $record->createdBy?->name,
            'checked_by_name' => $record->checkedBy?->name,
            'acknowledged_by_name' => $record->acknowledgedBy?->name,
            'status' => $record->status?->value,
            'created_at' => optional($record->created_at)->toIso8601String(),
            'updated_at' => optional($record->updated_at)->toIso8601String(),
            'details' => $details,
        ];
    }
}
