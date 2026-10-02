<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Exceptions\InvalidDateRangeException;
use App\Exceptions\NoActiveCagesTrackStationException;
use App\Models\CagesTippedTime;
use App\Models\CagesTrackRecord;
use App\Models\Machinery;
use App\Models\Station;
use App\Models\User;
use App\Support\Concerns\EnforcesPeriodLock;
use App\Support\Concerns\ScopesToActorMill;
use App\Support\Pagination;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * CagesTrackRecordService — screen-018--data-browser-cages-track-web /
 * usecase-018--data-browser-cages-track-web (Data Browser Cages Track, web).
 *
 * Shared by both the API controller (App\Http\Controllers\Api\
 * CagesTrackRecordController) and the Livewire component (App\Livewire\
 * Data\DataBrowserCagesTrack), so filtering/pagination/export rules stay
 * identical between the two entry points (mirrors the
 * GradingRecordService pattern used by screen-017).
 *
 * Filters accepted by both listRecords() and export(): 'date_from',
 * 'date_to' (both nullable date strings, filtered against
 * CagesTrackRecord's `date` column — a plain date, NOT a datetime like
 * weighbridge's arrival_datetime), 'business_unit_id' (nullable uuid,
 * filtered via the station->business_unit_id relationship).
 *
 * tipped_time_count (list success_schema + implementation_notes): NOT a
 * column on cages_track_records — computed via Eloquent's withCount()
 * against CagesTrackRecord::cagesTippedTimes(), which yields a
 * `cages_tipped_times_count` attribute on each row (Laravel's default
 * withCount() column-naming convention: Str::snake(relation) . '_count').
 */
class CagesTrackRecordService
{
    use EnforcesPeriodLock, ScopesToActorMill;

    /**
     * Row limit enforced on export() (business_logic step 5) — same
     * pragmatic MVP ceiling as GradingRecordService::EXPORT_ROW_LIMIT /
     * WeighbridgeRecordService::EXPORT_ROW_LIMIT (see those classes'
     * implementation_notes).
     */
    public const EXPORT_ROW_LIMIT = 50000;

    /**
     * Header fields accepted from the create()/update() form payload —
     * screen-024--form-cages-track-web. Mirrors GradingRecordService::
     * FORM_FIELDS's role (normalizeFormFields() below reads only these
     * keys from the raw $data array).
     */
    protected const FORM_FIELDS = [
        'cages_track_number', 'date', 'tippler_start_time', 'tippler_stop_time',
        'cages_out', 'cages_tipped', 'note',
    ];

    /**
     * create() — screen-024--form-cages-track-web business_logic steps
     * 1-6: validate header + details, resolve station from
     * production_line_id (2026-08-20: was business_unit_id — Production
     * Line inserted into the hierarchy between Business Unit and Station,
     * see entity-catalog v9), resolve N as
     * COUNT(machinery WHERE station_id = the resolved station) (2026-08-20:
     * mill-setting.jumlah_cages was removed — see
     * machineryCountForStation()), compute each detail row's
     * total_cages/cages_remain, then INSERT the record and its
     * cages_tipped_time rows inside a DB transaction.
     */
    public function create(array $data, User $actor): array
    {
        $attributes = $this->normalizeFormFields($data);
        $details = $this->normalizeDetails($data['details'] ?? []);

        $this->validateForm($attributes);
        $this->validateDetails($details);

        $station = $this->resolveActiveStationForActor(
            $data['production_line_id'] ?? null,
            'cages-track',
            $actor,
        );

        if ($station === null) {
            throw new NoActiveCagesTrackStationException();
        }

        $jumlahCages = $this->machineryCountForStation($station->id);

        // KUNCI PERIODE (usecase-141) — sebelum satu baris pun ditulis, supaya
        // penolakan tidak menyisakan induk tanpa detail. Jenis stasiun dan mill
        // diambil dari stasiun yang SUDAH di-resolve, bukan dari request.
        $this->assertPeriodOpenForWrite('cages-track', $station->business_unit_id, $attributes['date'] ?? null);

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

        $record = DB::transaction(function () use ($attributes, $details, $jumlahCages) {
            // CagesTrackRecord::booted()'s `saving` guard rejects
            // status=saved on a brand-new record with zero CagesTippedTime
            // rows — and at this point none exist yet (they're inserted by
            // upsertDetails() right after). Create as Synced (same
            // guard-satisfying placeholder pattern GradingRecordService::
            // create() uses), insert the details, THEN flip status to
            // saved — by then cagesTippedTimes()->count() is > 0 so the
            // guard passes.
            $attributes['status'] = \App\Enums\RecordStatus::Synced;
            $record = CagesTrackRecord::create($attributes);
            $this->upsertDetails($record, $details, jumlahCages: $jumlahCages);
            $record->update(['status' => 'saved']);

            return $record;
        });

        $record->load(['station', 'createdBy', 'checkedBy', 'acknowledgedBy', 'cagesTippedTimes']);

        return $this->toDetailRow($record);
    }

    /**
     * update() — screen-024--form-cages-track-web business_logic steps
     * 7-9: validate header + details (business_unit_id/station_id never
     * accepted), UPDATE the record, then upsert its cages_tipped_time rows
     * (insert rows without an id, update rows with an id still present,
     * delete rows previously in the DB but no longer in the array) inside
     * a DB transaction.
     */
    public function update(string $id, array $data, User $actor): array
    {
        $record = CagesTrackRecord::findOrFail($id);

        $this->assertRecordWritableByActor($record, $actor);

        $attributes = $this->normalizeFormFields($data);
        $details = $this->normalizeDetails($data['details'] ?? []);

        $this->validateForm($attributes);
        $this->validateDetails($details);

        $jumlahCages = $this->machineryCountForStation($record->station_id);

        // KUNCI PERIODE (usecase-141) — DUA tanggal diperiksa, bukan satu.
        // Mengubah tanggal sebuah record berarti mengeluarkannya dari periode
        // lama dan memasukkannya ke periode baru, dan mengeluarkan satu baris
        // dari periode yang sudah ditutup menggeser angka laporannya sama
        // nyatanya dengan menambah baris ke dalamnya. Jadi kedua ujung
        // perpindahan harus berada di periode yang terbuka. Verifikasi
        // (checked/acknowledged) lewat jalur ini ikut terkunci, sesuai spec.
        $record->loadMissing('station');
        $periodLockMillId = $record->station->business_unit_id;

        $this->assertPeriodOpenForWrite('cages-track', $periodLockMillId, optional($record->date)->toDateString());
        $this->assertPeriodOpenForWrite('cages-track', $periodLockMillId, $attributes['date'] ?? null);

        $this->applyVerification($attributes, $data, $actor);

        DB::transaction(function () use ($record, $attributes, $details, $jumlahCages) {
            $record->update($attributes);
            $this->upsertDetails($record, $details, jumlahCages: $jumlahCages);
        });

        $record->load(['station', 'createdBy', 'checkedBy', 'acknowledgedBy', 'cagesTippedTimes']);

        return $this->toDetailRow($record);
    }

    /**
     * jumlahCagesForProductionLine() — screen-024--form-cages-track-web's
     * Livewire form preview (App\Livewire\Data\FormCagesTrack): resolves
     * the SAME active Cages Track station create()/update() would resolve
     * from a production_line_id, and returns its machinery count — so the
     * grid's column count preview (before the record is actually saved)
     * matches exactly what create() will compute. Returns 0 when
     * production_line_id is blank or has no active Cages Track station
     * (mirrors the pre-2026-08-20 behavior of always resolving to SOME
     * int, never throwing, for a preview-only lookup).
     */
    public function jumlahCagesForProductionLine(?string $productionLineId): int
    {
        if ($productionLineId === null || $productionLineId === '') {
            return 0;
        }

        $station = Station::query()
            ->where('production_line_id', $productionLineId)
            ->where('type', 'cages-track')
            ->where('is_active', true)
            ->first();

        return $station !== null ? $this->machineryCountForStation($station->id) : 0;
    }

    /**
     * machineryCountForStation() — replaces mill-setting.jumlah_cages
     * (removed 2026-08-20, entity-catalog v9) as the source for N, the
     * Cages Tipped Time grid's checklist column count: simply
     * COUNT(machinery WHERE station_id = $stationId). No mill-setting
     * lookup/auto-create involved anymore. Public (not protected) since
     * App\Livewire\Data\FormCagesTrack also calls it directly, to resolve
     * N in edit mode from the record's already-known station_id.
     */
    public function machineryCountForStation(string $stationId): int
    {
        return Machinery::where('station_id', $stationId)->count();
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
     * @return array<int, array{id: ?string, tipped_hour: mixed, checked_cage_numbers: array}>
     */
    protected function normalizeDetails(array $rawDetails): array
    {
        return collect($rawDetails)
            ->map(fn ($row) => [
                'id' => $row['id'] ?? null,
                'tipped_hour' => $row['tipped_hour'] ?? null,
                'checked_cage_numbers' => is_array($row['checked_cage_numbers'] ?? null) ? $row['checked_cage_numbers'] : [],
            ])
            ->values()
            ->all();
    }

    protected function validateForm(array $attributes): void
    {
        Validator::make($attributes, [
            'cages_track_number' => ['required', 'string'],
            'date' => ['required', 'date'],
            'tippler_start_time' => ['required', 'date'],
            'tippler_stop_time' => ['required', 'date'],
            'cages_out' => ['required', 'integer'],
            'cages_tipped' => ['required', 'integer'],
            'note' => ['nullable', 'string'],
        ], [
            'cages_track_number.required' => 'Cages Track Number wajib diisi.',
            'date.required' => 'Tanggal wajib diisi.',
            'tippler_start_time.required' => 'Tippler Start Time wajib diisi.',
            'tippler_stop_time.required' => 'Tippler Stop Time wajib diisi.',
            'cages_out.required' => 'Cages Out wajib diisi.',
            'cages_tipped.required' => 'Cages Tipped wajib diisi.',
        ])->validate();
    }

    /**
     * validateDetails() — business_logic step 1: at least one valid detail
     * row (tipped_hour + at least one checked cage) must exist, and every
     * row's tipped_hour must be strictly ascending and unique across the
     * array (entity-catalog: "tipped_hour baris baru harus > tipped_hour
     * baris terakhir yang ditambahkan").
     */
    protected function validateDetails(array $details): void
    {
        $validRows = collect($details)->filter(
            fn ($row) => $row['tipped_hour'] !== null && $row['tipped_hour'] !== '' && ! empty($row['checked_cage_numbers'])
        )->values();

        if ($validRows->isEmpty()) {
            throw ValidationException::withMessages([
                'details' => 'Minimal satu baris Cages Tipped Time (Time + minimal 1 cage tercentang) harus diisi.',
            ]);
        }

        $hours = $validRows->pluck('tipped_hour')->map(fn ($hour) => (int) $hour);

        if ($hours->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'details' => 'Time tiap baris Cages Tipped Time tidak boleh duplikat.',
            ]);
        }

        $sorted = $hours->values();
        foreach ($sorted as $index => $hour) {
            if ($index > 0 && $hour <= $sorted[$index - 1]) {
                throw ValidationException::withMessages([
                    'details' => 'Time tiap baris Cages Tipped Time harus lebih besar dari baris sebelumnya (urutan menaik).',
                ]);
            }
        }
    }

    /**
     * upsertDetails() — business_logic step 4 + 9: for each valid detail
     * row, compute total_cages (count of checked_cage_numbers) and
     * cages_remain (jumlahCages - total_cages), serialize
     * checked_cage_numbers to CSV text (CagesTippedTime::$casts stores it
     * as a plain string), then insert rows without an id, update rows
     * whose id still appears in $details, and delete any existing row
     * whose id is no longer present.
     */
    protected function upsertDetails(CagesTrackRecord $record, array $details, int $jumlahCages): void
    {
        $validRows = collect($details)->filter(
            fn ($row) => $row['tipped_hour'] !== null && $row['tipped_hour'] !== '' && ! empty($row['checked_cage_numbers'])
        );

        // URUTAN MENENTUKAN — baris basi DIHAPUS SEBELUM baris baru
        // disisipkan. Sampai 2026-09-25 urutannya terbalik, dan dengan
        // UNIQUE(cages_track_record_id, tipped_hour) pada tabel detail itu berarti memindahkan
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

        CagesTippedTime::where('cages_track_record_id', $record->id)
            ->whereNotIn('id', $keptIds)
            ->delete();

        foreach ($validRows as $row) {
            $checkedCages = collect($row['checked_cage_numbers'])->map(fn ($n) => (int) $n)->values();
            $totalCages = $checkedCages->count();

            $detailAttributes = [
                'cages_track_record_id' => $record->id,
                'tipped_hour' => (int) $row['tipped_hour'],
                'checked_cage_numbers' => $checkedCages->implode(','),
                'total_cages' => $totalCages,
                'cages_remain' => $jumlahCages - $totalCages,
            ];

            if (! empty($row['id']) && CagesTippedTime::where('id', $row['id'])->where('cages_track_record_id', $record->id)->exists()) {
                CagesTippedTime::where('id', $row['id'])->update($detailAttributes);
            } else {
                CagesTippedTime::create($detailAttributes);
            }
        }


    }

    /**
     * applyVerification() — BOTH Checked By (Supervisor) and Acknowledged
     * By (Mill Management) are implemented on this screen as
     * self-attestation checkboxes — unlike Form Weighbridge/Grading Web,
     * this screen keeps Checked By (mirrors Form Cages Track mobile /
     * Detail Cages Track Web, which also expose both).
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
     * build the filtered query (with tipped_time_count computed via
     * withCount()), paginate via the shared Pagination helper, and return
     * the {data, meta} shape.
     *
     * @param  array{date_from?: ?string, date_to?: ?string, business_unit_id?: ?string, production_line_id?: ?string}  $filters
     */
    public function listRecords(array $filters, int $page, int $perPage): array
    {
        $query = $this->buildFilteredQuery($filters)
            ->withCount('cagesTippedTimes')
            ->orderByDesc('date');

        // `productionLine` dimuat di sini, bukan lewat relasi `station`:
        // kolom line milik record sendiri adalah sumber kebenarannya.
        $paginator = $query->with('productionLine:id,name')->paginate(perPage: $perPage, page: $page);

        $formatted = Pagination::format($paginator);
        $formatted['data'] = collect($formatted['data'])
            ->map(fn (CagesTrackRecord $record) => $this->toListRow($record))
            ->all();

        return $formatted;
    }

    /**
     * export() — business_logic step 5-6: re-run the same filter query
     * (no pagination, still with tipped_time_count computed), enforce the
     * row limit, generate a CSV (or CSV-served-as-xlsx fallback — see
     * implementation_notes) body, and return it as a StreamedResponse for
     * download.
     *
     * @param  array{date_from?: ?string, date_to?: ?string, business_unit_id?: ?string, production_line_id?: ?string}  $filters
     */
    public function export(array $filters, string $format): StreamedResponse
    {
        $baseQuery = $this->buildFilteredQuery($filters);

        // The row limit counts EXPORTED lines, not header records: the file
        // writes one line per detail row, plus a single line for a record that
        // has no detail rows at all so an empty day stays visible.
        $detailRowCount = CagesTippedTime::query()
            ->whereIn('cages_track_record_id', (clone $baseQuery)->select('id'))
            ->count();
        $recordsWithoutDetails = (clone $baseQuery)->doesntHave('cagesTippedTimes')->count();

        if ($detailRowCount + $recordsWithoutDetails > self::EXPORT_ROW_LIMIT) {
            throw new ExportFailedException();
        }

        try {
            $query = $baseQuery
                ->withCount('cagesTippedTimes')
                ->with([
                    'productionLine:id,name',
                    'checkedBy:id,name',
                    'acknowledgedBy:id,name',
                    'cagesTippedTimes' => fn ($detailQuery) => $detailQuery->orderBy('tipped_hour'),
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
                    'Cages Track Number',
                    'Date',
                    'Tippler Start Time',
                    'Tippler Stop Time',
                    'Cages Out',
                    'Cages Tipped',
                    'Note',
                    'Checked By',
                    'Acknowledged By',
                    'Jumlah Cage/Lori Tercatat',
                    'Status',
                    'Time',
                    'Cage Dicentang',
                    'Total Cages',
                    'Cages Remain',
                ], ',', '"', '\\');

                $query->chunk(200, function ($records) use ($handle) {
                    foreach ($records as $record) {
                        /** @var CagesTrackRecord $record */
                        $context = [
                            $record->productionLine?->name,
                            $record->cages_track_number,
                            optional($record->date)->toDateString(),
                            optional($record->tippler_start_time)->toDateTimeString(),
                            optional($record->tippler_stop_time)->toDateTimeString(),
                            $record->cages_out,
                            $record->cages_tipped,
                            $record->note,
                            $record->checkedBy?->name,
                            $record->acknowledgedBy?->name,
                            $record->cages_tipped_times_count,
                            $record->status?->value,
                        ];

                        $details = $record->cagesTippedTimes;

                        if ($details->isEmpty()) {
                            fputcsv($handle, array_merge($context, array_fill(0, 4, null)), ',', '"', '\\');

                            continue;
                        }

                        foreach ($details as $detail) {
                            /** @var CagesTippedTime $detail */
                            fputcsv($handle, array_merge($context, [
                                $detail->tipped_hour,
                                $detail->checked_cage_numbers,
                                $detail->total_cages,
                                $detail->cages_remain,
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

    protected function exportRowLimitLabel(): string
    {
        return number_format(self::EXPORT_ROW_LIMIT);
    }

    /**
     * Resolves the Content-Type + filename for the requested export format.
     *
     * No XLSX writer package is present in composer.json (spatie/laravel-excel
     * or maatwebsite/excel), and the tech-spec explicitly says not to add
     * one unless strictly necessary — so format=excel falls back to a CSV
     * body served with the xlsx mimetype/extension (pragmatic MVP; opens
     * correctly in Excel/most spreadsheet tools since they sniff CSV
     * content, though it is not a real OOXML file). Same approach as
     * GradingRecordService::fileMetaFor() / WeighbridgeRecordService::fileMetaFor()
     * — see implementation_notes.
     *
     * @return array{0: string, 1: string}
     */
    protected function fileMetaFor(string $format): array
    {
        $timestamp = now()->format('Ymd_His');

        if ($format === 'excel') {
            return [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                "cages-track-records_{$timestamp}.xlsx",
            ];
        }

        return [
            'text/csv',
            "cages-track-records_{$timestamp}.csv",
        ];
    }

    /**
     * buildFilteredQuery() — business_logic step 1-2: validate the date
     * range (date_from > date_to → INVALID_DATE_RANGE) then apply the
     * business_unit_id (via station->business_unit_id) and `date` BETWEEN
     * filters (CagesTrackRecord's `date` column is a plain date, not a
     * datetime — whereDate() still applies cleanly since it truncates both
     * sides to the date portion).
     *
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

        $query = CagesTrackRecord::query();

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
     * Maps a CagesTrackRecord (with tipped_time_count pre-loaded via
     * withCount()) to the list endpoint's success_schema row shape:
     * { id, cages_track_number, date, tipped_time_count, status }.
     */
    protected function toListRow(CagesTrackRecord $record): array
    {
        return [
            'id' => $record->id,
            'cages_track_number' => $record->cages_track_number,
            'date' => optional($record->date)->toDateString(),
            'tipped_time_count' => (int) $record->cages_tipped_times_count,
            'production_line_name' => $record->productionLine?->name,
            'status' => $record->status?->value,
        ];
    }

    /**
     * getDetail() — screen-021--detail-cages-track-web business_logic
     * steps 1-4: findOrFail (404 via ModelNotFoundException, handled
     * globally by ApiExceptionHandler) then resolve station/createdBy/
     * checkedBy/acknowledgedBy to display names and the cagesTippedTimes
     * grid (ordered by tipped_hour), mirroring
     * DataPreviewCagesTrackView.vue (mobile)'s detail mode field set/order
     * — UNLIKE screen-020 (Grading), Checked By IS resolved/exposed here,
     * consistent with that mobile screen showing both Checked By and
     * Acknowledged By with no role-based hiding.
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
            CagesTrackRecord::with([
                'station',
                'createdBy',
                'checkedBy',
                'acknowledgedBy',
                'cagesTippedTimes',
            ])
        )->findOrFail($id);

        return $this->toDetailRow($record);
    }

    /**
     * Maps a CagesTrackRecord to the detail endpoint's success_schema —
     * every header field plus station_name/created_by_name/
     * checked_by_name/acknowledged_by_name resolved via the relations
     * eager-loaded in getDetail(), plus the `tipped_times` array (each
     * cagesTippedTimes row, ordered by tipped_hour, rendered directly from
     * its stored columns — historical data, not recomputed).
     */
    protected function toDetailRow(CagesTrackRecord $record): array
    {
        return [
            'id' => $record->id,
            'station_id' => $record->station_id,
            'station_name' => $record->station?->name,
            'cages_track_number' => $record->cages_track_number,
            'date' => optional($record->date)->toDateString(),
            'tippler_start_time' => optional($record->tippler_start_time)->toIso8601String(),
            'tippler_stop_time' => optional($record->tippler_stop_time)->toIso8601String(),
            'cages_out' => $record->cages_out,
            'cages_tipped' => $record->cages_tipped,
            'note' => $record->note,
            'created_by_name' => $record->createdBy?->name,
            'checked_by_name' => $record->checkedBy?->name,
            'acknowledged_by_name' => $record->acknowledgedBy?->name,
            'status' => $record->status?->value,
            'created_at' => optional($record->created_at)->toIso8601String(),
            'updated_at' => optional($record->updated_at)->toIso8601String(),
            'tipped_times' => $record->cagesTippedTimes
                ->sortBy('tipped_hour')
                ->values()
                ->map(fn ($row) => [
                    'id' => $row->id,
                    'tipped_hour' => $row->tipped_hour,
                    'checked_cage_numbers' => $row->checked_cage_numbers,
                    'total_cages' => $row->total_cages,
                    'cages_remain' => $row->cages_remain,
                ])->all(),
        ];
    }
}
