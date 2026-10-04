<?php

namespace App\Services;

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Exceptions\InvalidDateRangeException;
use App\Exceptions\NoActivePressingStationException;
use App\Models\PressingDetail;
use App\Models\PressingRecord;
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
 * PressingRecordService — screen-050--data-browser-pressing-web /
 * screen-054--detail-pressing-web / screen-058--form-pressing-web.
 *
 * Shared by both the API controller (App\Http\Controllers\Api\
 * PressingRecordController) and the Livewire components (App\Livewire\
 * Data\DataBrowserPressing / DetailPressing / FormPressing), so
 * filtering/pagination/export/create/update rules stay identical between
 * entry points — mirrors ThreshingRecordService's pattern exactly
 * (Pressing's structural sibling among the 4 new MVP stations).
 *
 * REVISED 2026-08-24 (entity-catalog v12): pressing_detail is now a
 * DYNAMIC add-row/remove-row grid — the user explicitly rejected the
 * original FIXED 24-row design (all 24 canonical time-slot rows always
 * inserted at once, no add/remove concept at all) as wasting screen space.
 * This now mirrors ThreshingRecordService's `threshing_detail`
 * upsert/delete pattern exactly: `details` may contain any number of rows
 * (1..24), each with a unique `time_slot` in strictly ascending canonical
 * order (validateDetails()); create() INSERTs exactly the given rows (no
 * longer all 24); update() upserts (insertDetails()/updateDetails() are
 * replaced by a single `upsertDetails()` — rows with an existing `id` kept
 * in `$details` are UPDATEd, rows without one are INSERTed, and any
 * existing row whose id is no longer present in `$details` is DELETEd).
 * `canonicalTimeSlots()` is KEPT — no longer used to force exactly 24 rows,
 * but still the source of truth for "which slots exist, in what order" for
 * both `validateDetails()`'s ascending-order check and `toDetailRow()`'s
 * sort order.
 *
 * "Filled" row (filled_slot_count / minimum-one-row validation): a
 * pressing_detail row counts as filled when at least one of its 6 reading
 * columns (digester_temp_c, digester_level_percent,
 * press_motor_current_amps, cone_hydraulic_pressure_bar,
 * dilution_water_temp_c, downtime_reason) is non-null. The model's own
 * `saving` guard (PressingRecord::booted(), "minimal satu pressing-detail
 * sebelum status=saved") checks ROW EXISTENCE, satisfied once at least one
 * row has been added — the real "minimal satu baris terisi" business rule
 * is enforced by THIS service's validateDetails(), not by that guard.
 */
class PressingRecordService
{
    use EnforcesPeriodLock, NormalizesTimeSlot, ScopesToActorMill;

    public const EXPORT_ROW_LIMIT = 50000;

    protected const FORM_FIELDS = ['presser_id', 'date', 'note'];

    /**
     * The 24 canonical hourly time-slot labels, in order: 07:00, 08:00,
     * ..., 23:00, 00:00, ..., 06:00. Mirrors mobile's
     * pressingRecordRepo.ts's canonicalTimeSlots() exactly (`for i in
     * 0..23 -> hour = (7 + i) % 24`), and ThreshingRecordService's
     * identical helper, so web and mobile always agree on row
     * order/coverage.
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
     * create() — screen-058--form-pressing-web business_logic steps 1-3:
     * validate header + details (at least one valid row, unique + strictly
     * ascending canonical time_slot order), resolve station from
     * production_line_id, then INSERT the record and its pressing_detail
     * rows inside a DB transaction.
     */
    public function create(array $data, User $actor): array
    {
        $attributes = $this->normalizeFormFields($data);
        $details = $this->normalizeDetails($data['details'] ?? []);

        $this->validateForm($attributes);
        $this->validateDetails($details);

        $station = $this->resolveActiveStationForActor(
            $data['production_line_id'] ?? null,
            'pressing',
            $actor,
        );

        if ($station === null) {
            throw new NoActivePressingStationException;
        }

        // KUNCI PERIODE (usecase-141) — sebelum satu baris pun ditulis, supaya
        // penolakan tidak menyisakan induk tanpa detail. Jenis stasiun dan mill
        // diambil dari stasiun yang SUDAH di-resolve, bukan dari request.
        // BATAS ATAS TANGGAL (2026-10-04) — lihat EnforcesPeriodLock::assertEventDateNotTooFarAhead().
        $this->assertEventDateNotTooFarAhead($attributes['date'] ?? null, 'date', 'Tanggal');
        $this->assertPeriodOpenForWrite('pressing', $station->business_unit_id, $attributes['date'] ?? null);

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
            // PressingRecord::booted()'s `saving` guard rejects
            // status=saved on a brand-new record with zero PressingDetail
            // rows — and at this point none exist yet (inserted by
            // upsertDetails() right after). Create as Synced (same
            // guard-satisfying placeholder pattern
            // ThreshingRecordService::create() uses), insert the details,
            // THEN flip status to saved.
            $attributes['status'] = RecordStatus::Synced;
            $record = PressingRecord::create($attributes);
            $this->upsertDetails($record, $details);
            $record->update(['status' => 'saved']);

            return $record;
        });

        $record->load(['station', 'createdBy', 'checkedBy', 'acknowledgedBy', 'pressingDetails']);

        return $this->toDetailRow($record);
    }

    /**
     * update() — screen-058--form-pressing-web business_logic steps 5-7:
     * validate header + details (at least one valid row, unique + strictly
     * ascending canonical time_slot order), UPDATE the record, then upsert
     * its pressing_detail rows (insert rows without an id, update rows
     * whose id still appears in $details, delete any existing row whose id
     * is no longer present) inside a DB transaction — mirrors
     * ThreshingRecordService::update() exactly.
     */
    public function update(string $id, array $data, User $actor): array
    {
        $record = PressingRecord::findOrFail($id);

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

        $this->assertPeriodOpenForWrite('pressing', $periodLockMillId, optional($record->date)->toDateString());
        // BATAS ATAS TANGGAL (2026-10-04) — lihat EnforcesPeriodLock::assertEventDateNotTooFarAhead().
        $this->assertEventDateNotTooFarAhead($attributes['date'] ?? null, 'date', 'Tanggal');
        $this->assertPeriodOpenForWrite('pressing', $periodLockMillId, $attributes['date'] ?? null);

        $this->applyVerification($attributes, $data, $actor);

        DB::transaction(function () use ($record, $attributes, $details) {
            $record->update($attributes);
            $this->upsertDetails($record, $details);
        });

        $record->load(['station', 'createdBy', 'checkedBy', 'acknowledgedBy', 'pressingDetails']);

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
     * @return array<int, array{id: ?string, time_slot: ?string, digester_temp_c: mixed, digester_level_percent: mixed, press_motor_current_amps: mixed, cone_hydraulic_pressure_bar: mixed, dilution_water_temp_c: mixed, downtime_reason: mixed}>
     */
    protected function normalizeDetails(array $rawDetails): array
    {
        return collect($rawDetails)
            ->map(fn ($row) => [
                'id' => $row['id'] ?? null,
                'time_slot' => $this->canonicalTimeSlot($row['time_slot'] ?? null),
                'digester_temp_c' => $row['digester_temp_c'] ?? null,
                'digester_level_percent' => $row['digester_level_percent'] ?? null,
                'press_motor_current_amps' => $row['press_motor_current_amps'] ?? null,
                'cone_hydraulic_pressure_bar' => $row['cone_hydraulic_pressure_bar'] ?? null,
                'dilution_water_temp_c' => $row['dilution_water_temp_c'] ?? null,
                'downtime_reason' => $row['downtime_reason'] ?? null,
            ])
            ->values()
            ->all();
    }

    protected function validateForm(array $attributes): void
    {
        Validator::make($attributes, [
            'presser_id' => ['required', 'string'],
            'date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
        ], [
            'presser_id.required' => 'Presser ID wajib diisi.',
            'date.required' => 'Tanggal wajib diisi.',
        ])->validate();
    }

    /**
     * validateDetails() — business_logic step 1: at least one valid detail
     * row (a `time_slot` from the 24 canonical slots AND at least one of
     * its 6 reading columns non-null) must exist, and every row's
     * `time_slot` must be strictly ascending (canonical order) and unique
     * across the array — mirrors ThreshingRecordService::validateDetails()
     * exactly, adapted to Pressing's own 6 reading columns.
     */
    protected function validateDetails(array $details): void
    {
        $canonicalOrder = array_flip(self::canonicalTimeSlots());

        $validRows = collect($details)->filter(
            fn ($row) => $row['time_slot'] !== null && $row['time_slot'] !== '' && $this->isRowFilled($row)
        )->values();

        if ($validRows->isEmpty()) {
            throw ValidationException::withMessages([
                'details' => 'Minimal satu baris Pressing Detail (Time-Slot terpilih + minimal 1 kolom bacaan terisi) harus diisi.',
            ]);
        }

        $slots = $validRows->pluck('time_slot');

        if ($slots->contains(fn ($slot) => ! array_key_exists($slot, $canonicalOrder))) {
            throw ValidationException::withMessages([
                'details' => 'Time-Slot Pressing Detail harus salah satu dari 24 slot kanonis (07:00-06:00).',
            ]);
        }

        if ($slots->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'details' => 'Time-Slot tiap baris Pressing Detail tidak boleh duplikat.',
            ]);
        }

        $sortedIndexes = $slots->map(fn ($slot) => $canonicalOrder[$slot])->values();

        foreach ($sortedIndexes as $index => $slotIndex) {
            if ($index > 0 && $slotIndex <= $sortedIndexes[$index - 1]) {
                throw ValidationException::withMessages([
                    'details' => 'Time-Slot tiap baris Pressing Detail harus lebih besar dari baris sebelumnya (urutan menaik).',
                ]);
            }
        }
    }

    /**
     * @param  array{digester_temp_c: mixed, digester_level_percent: mixed, press_motor_current_amps: mixed, cone_hydraulic_pressure_bar: mixed, dilution_water_temp_c: mixed, downtime_reason: mixed}  $row
     */
    protected function isRowFilled(array $row): bool
    {
        return $row['digester_temp_c'] !== null
            || $row['digester_level_percent'] !== null
            || $row['press_motor_current_amps'] !== null
            || $row['cone_hydraulic_pressure_bar'] !== null
            || $row['dilution_water_temp_c'] !== null
            || ($row['downtime_reason'] !== null && $row['downtime_reason'] !== '');
    }

    /**
     * upsertDetails() — business_logic steps 3 + 7: for each valid detail
     * row (a selected `time_slot` AND at least one reading column filled —
     * same filter as validateDetails()), insert rows without an id, update
     * rows whose id still appears in $details, and delete any existing row
     * whose id is no longer present — mirrors
     * ThreshingRecordService::upsertDetails() exactly.
     */
    protected function upsertDetails(PressingRecord $record, array $details): void
    {
        $validRows = collect($details)->filter(
            fn ($row) => $row['time_slot'] !== null && $row['time_slot'] !== '' && $this->isRowFilled($row)
        );

        // URUTAN MENENTUKAN — baris basi DIHAPUS SEBELUM baris baru
        // disisipkan. Sampai 2026-09-25 urutannya terbalik, dan dengan
        // UNIQUE(pressing_record_id, time_slot) pada tabel detail itu berarti memindahkan
        // sebuah pembacaan ke slot yang SEDANG DIPAKAI baris lain yang akan
        // dihapus melanggar constraint dan melempar
        // UniqueConstraintViolationException. Itu operasi harian: Operator
        // salah pilih slot lalu membetulkannya.
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

        PressingDetail::where('pressing_record_id', $record->id)
            ->whereNotIn('id', $keptIds)
            ->delete();

        foreach ($validRows as $row) {
            $detailAttributes = [
                'pressing_record_id' => $record->id,
                'time_slot' => $row['time_slot'],
                'digester_temp_c' => $row['digester_temp_c'],
                'digester_level_percent' => $row['digester_level_percent'],
                'press_motor_current_amps' => $row['press_motor_current_amps'],
                'cone_hydraulic_pressure_bar' => $row['cone_hydraulic_pressure_bar'],
                'dilution_water_temp_c' => $row['dilution_water_temp_c'],
                'downtime_reason' => $row['downtime_reason'],
            ];

            if (! empty($row['id']) && Str::isUuid((string) $row['id']) && PressingDetail::where('id', $row['id'])->where('pressing_record_id', $record->id)->exists()) {
                PressingDetail::where('id', $row['id'])->update($detailAttributes);
            } else {
                PressingDetail::create($detailAttributes);
            }
        }

    }

    /**
     * applyVerification() — BOTH Checked By (Supervisor) and Acknowledged
     * By (Mill Management) are self-attestation checkboxes, mirrors
     * ThreshingRecordService::applyVerification() exactly.
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
     * listRecords() — business_logic steps 1-4: validate the date range,
     * build the filtered query (with filled_slot_count computed via
     * withCount()), paginate, return {data, meta}.
     *
     * @param  array{date_from?: ?string, date_to?: ?string, business_unit_id?: ?string, production_line_id?: ?string}  $filters
     */
    public function listRecords(array $filters, int $page, int $perPage): array
    {
        $query = $this->buildFilteredQuery($filters)
            ->withCount(['pressingDetails as filled_slot_count' => function (Builder $detailQuery) {
                $detailQuery->where(function (Builder $q) {
                    $q->whereNotNull('digester_temp_c')
                        ->orWhereNotNull('digester_level_percent')
                        ->orWhereNotNull('press_motor_current_amps')
                        ->orWhereNotNull('cone_hydraulic_pressure_bar')
                        ->orWhereNotNull('dilution_water_temp_c')
                        ->orWhereNotNull('downtime_reason');
                });
            }])
            ->orderByDesc('date');

        // `productionLine` dimuat di sini, bukan lewat relasi `station`:
        // kolom line milik record sendiri adalah sumber kebenarannya.
        $paginator = $query->with('productionLine:id,name')->paginate(perPage: $perPage, page: $page);

        $formatted = Pagination::format($paginator);
        $formatted['data'] = collect($formatted['data'])
            ->map(fn (PressingRecord $record) => $this->toListRow($record))
            ->all();

        return $formatted;
    }

    /**
     * export() — business_logic steps 5-6: re-run the filter query
     * (unpaginated), enforce the row limit, generate a CSV body.
     *
     * @param  array{date_from?: ?string, date_to?: ?string, business_unit_id?: ?string, production_line_id?: ?string}  $filters
     */
    public function export(array $filters, string $format): StreamedResponse
    {
        $baseQuery = $this->buildFilteredQuery($filters);

        // The row limit counts EXPORTED lines, not header records: the file
        // writes one line per detail row, plus a single line for a record that
        // has no detail rows at all so an empty day stays visible.
        $detailRowCount = PressingDetail::query()
            ->whereIn('pressing_record_id', (clone $baseQuery)->select('id'))
            ->count();
        $recordsWithoutDetails = (clone $baseQuery)->doesntHave('pressingDetails')->count();

        if ($detailRowCount + $recordsWithoutDetails > self::EXPORT_ROW_LIMIT) {
            throw new ExportFailedException;
        }

        try {
            $query = $baseQuery
                ->with([
                    'productionLine:id,name',
                    'checkedBy:id,name',
                    'acknowledgedBy:id,name',
                    'pressingDetails' => fn ($detailQuery) => $detailQuery->orderBy('time_slot'),
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
                    'Presser ID',
                    'Date',
                    'Note',
                    'Checked By',
                    'Acknowledged By',
                    'Status',
                    'Time-Slot',
                    'Digester Temp (°C)',
                    'Digester Level (%)',
                    'Press Motor Current (A)',
                    'Cone Hydraulic Pressure (bar)',
                    'Dilution Water Temp (°C)',
                    'Downtime Reason',
                ]);

                $query->chunk(200, function ($records) use ($handle) {
                    foreach ($records as $record) {
                        /** @var PressingRecord $record */
                        $context = [
                            $record->productionLine?->name,
                            $record->presser_id,
                            optional($record->date)->toDateString(),
                            $record->note,
                            $record->checkedBy?->name,
                            $record->acknowledgedBy?->name,
                            ExportValue::status($record->status),
                        ];

                        $details = $record->pressingDetails;

                        if ($details->isEmpty()) {
                            $handle->row(array_merge($context, array_fill(0, 7, null)));

                            continue;
                        }

                        foreach ($details as $detail) {
                            /** @var PressingDetail $detail */
                            $handle->row(array_merge($context, [
                                ExportValue::time($detail->time_slot),
                                $detail->digester_temp_c,
                                $detail->digester_level_percent,
                                $detail->press_motor_current_amps,
                                $detail->cone_hydraulic_pressure_bar,
                                $detail->dilution_water_temp_c,
                                $detail->downtime_reason,
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
                "pressing-records_{$timestamp}.xlsx",
            ];
        }

        return [
            'text/csv',
            "pressing-records_{$timestamp}.csv",
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

        $query = PressingRecord::query();

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
     * Maps a PressingRecord (with filled_slot_count pre-loaded via
     * withCount()) to the list endpoint's success_schema row shape.
     */
    protected function toListRow(PressingRecord $record): array
    {
        return [
            'id' => $record->id,
            'presser_id' => $record->presser_id,
            'date' => optional($record->date)->toDateString(),
            'filled_slot_count' => (int) $record->filled_slot_count,
            'production_line_name' => $record->productionLine?->name,
            'status' => $record->status?->value,
        ];
    }

    /**
     * getDetail() — screen-054--detail-pressing-web business_logic steps
     * 1-4: findOrFail (404 via ModelNotFoundException) then resolve
     * station/createdBy/checkedBy/acknowledgedBy to display names and the
     * pressingDetails grid, sorted into canonical time-slot order (not
     * alphabetical — '00:00' would otherwise sort before '07:00').
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
            PressingRecord::with([
                'station',
                'createdBy',
                'checkedBy',
                'acknowledgedBy',
                'pressingDetails',
            ])
        )->findOrFail($id);

        return $this->toDetailRow($record);
    }

    /**
     * Maps a PressingRecord to the detail endpoint's success_schema —
     * every header field plus station_name/created_by_name/
     * checked_by_name/acknowledged_by_name, plus the `details` array
     * (however many rows exist, canonical time-slot order, rendered
     * directly from stored columns — historical data, not recomputed).
     */
    protected function toDetailRow(PressingRecord $record): array
    {
        $canonicalOrder = array_flip(self::canonicalTimeSlots());

        $details = $record->pressingDetails
            ->sortBy(fn (PressingDetail $row) => $canonicalOrder[$row->time_slot] ?? 999)
            ->values()
            ->map(fn (PressingDetail $row) => [
                'id' => $row->id,
                'time_slot' => $row->time_slot,
                'digester_temp_c' => $row->digester_temp_c,
                'digester_level_percent' => $row->digester_level_percent,
                'press_motor_current_amps' => $row->press_motor_current_amps,
                'cone_hydraulic_pressure_bar' => $row->cone_hydraulic_pressure_bar,
                'dilution_water_temp_c' => $row->dilution_water_temp_c,
                'downtime_reason' => $row->downtime_reason,
            ])
            ->all();

        return [
            'id' => $record->id,
            'station_id' => $record->station_id,
            'station_name' => $record->station?->name,
            'presser_id' => $record->presser_id,
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
