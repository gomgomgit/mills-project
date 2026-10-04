<?php

namespace App\Services;

use App\Enums\PeriodStatus;
use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\ProductionLine;
use App\Models\StationType;
use App\Models\SterilizerDetail;
use App\Models\SterilizerRecord;
use App\Support\ExportValue;
use App\Support\SheetWriter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * SterilizerReportService — screen-129--laporan-sterilizer-web /
 * usecase-129--laporan-sterilizer-web (Laporan Periode Sterilizer).
 *
 * Shared by the API controller (App\Http\Controllers\Api\
 * SterilizerReportController) and the Livewire component (App\Livewire\
 * Dashboard\LaporanSterilizer), exactly like ManagementReportService /
 * ManagementReportController / ManagementReport — so the web page and the
 * API can never disagree on a figure.
 *
 * READ-ONLY BY CONSTRUCTION: every public method is a SELECT. There is no
 * write path here and no POST/PUT/PATCH/DELETE route on the
 * /api/sterilizer-reports prefix — a report must never be able to mutate
 * the Sterilizer data it reports on.
 *
 * CROSS-MILL SECURITY IS CLOSED AT TWO DIFFERENT POINTS, on purpose:
 *
 *   1. resolveBusinessUnit() IGNORES the client's business_unit_id for
 *      Supervisor / Mill Management. Sending another mill's id returns 200
 *      with the caller's OWN mill data — deliberately not a 403, because a
 *      403 would confirm that the other mill exists.
 *   2. authorizePeriod() REJECTS a period belonging to another mill with
 *      403. This is the real leak path: a period id is a concrete handle to
 *      another mill's data, so it must be refused rather than silently
 *      rewritten.
 *
 * Admin is the only role not bound to one mill (users.business_unit_id is
 * NULL for Admin), which is why businessUnitOptions() exists and is
 * Admin-only: without picking a mill an Admin would never see any data at
 * all.
 */
class SterilizerReportService
{
    /**
     * Judul kolom ekspor — Bahasa Indonesia seperti kelima laporan stasiun
     * lain, dengan kolom konteks Periode/Mill/Production Line di depan
     * (temuan audit 2026-10-04 #8). Satu baris per siklus.
     */
    public const EXPORT_HEADER = [
        'Periode',
        'Mill',
        'Production Line',
        'Tanggal',
        'ID Sterilizer',
        'Catatan',
        'Diperiksa Oleh',
        'Diketahui Oleh',
        'Status',
        'No. Sterilizer',
        'Jam Tutup Pintu',
        'Jam Puncak 1',
        'Jam Buang 1',
        'Jam Puncak 2',
        'Jam Buang 2',
        'Jam Puncak 3',
        'Jam Buang 3',
        'Jam Buka Pintu',
        'Durasi (menit)',
        'Jumlah Lori',
        'Status Lori',
        'Diperiksa SPV',
        'Keterangan',
    ];

    /**
     * Export row ceiling, counted in EXPORTED LINES (= sterilization
     * cycles), not header records — one daily record can carry a dozen
     * cycles, so counting headers would sail straight past the real limit.
     * Same trap that was fixed for the 18 station exports in commit
     * 8611974.
     */
    public const EXPORT_ROW_LIMIT = 50000;

    /**
     * Minimum number of cycles WITH a duration before the Tukey fence is
     * computed at all. Below this, quartiles carry no meaning and flagging
     * an "outlier" out of a handful of rows misleads more than it informs —
     * the UI says the sample is too small instead.
     */
    public const OUTLIER_MIN_SAMPLE_SIZE = 8;

    /**
     * Jenis stasiun yang dilaporkan layar ini — kunci baris
     * `period_stations` yang statusnya dipakai di seluruh payload layar ini.
     *
     * Menggantikan ALL_STATION_TYPES_LABEL ('Semua Stasiun'), yang hilang
     * bersama `periods.station_type` pada 2026-09-25: sebuah periode tidak
     * lagi bisa berlaku "untuk semua jenis stasiun" lewat station_type NULL —
     * cakupan itu kini dinyatakan lewat ADANYA satu baris period_stations per
     * jenis stasiun. Karena itu daftar periode layar ini adalah daftar
     * pasangan (periode, sterilizer), dan tidak ada lagi opsi tanpa jenis stasiun.
     */
    protected const STATION_TYPE = StationTypeEnum::Sterilizer->value;

    /**
     * code => name from the `station_types` master table, memoised per
     * service instance (one request / one Livewire render).
     *
     * @var array<string, string>|null
     */
    protected ?array $stationTypeNames = null;

    /**
     * business_logic step 1 — resolve which mill the caller is allowed to
     * look at.
     *
     * Supervisor / Mill Management: ALWAYS their own business_unit_id; the
     * `business_unit_id` query param is ignored outright (not validated,
     * not compared — ignored), so probing another mill's id is a no-op that
     * still returns the caller's own data with HTTP 200.
     *
     * Admin: the value MUST come from the query. A missing value is a 422
     * VALIDATION_ERROR with errors.business_unit_id — never a silent null
     * or an empty result set, which would read as "this mill has no data".
     *
     * Operator: same mill-bound treatment as Supervisor / Mill Management —
     * the mill comes from the account and the query param is discarded.
     * Widened 2026-09-23 for screen-135 (Laporan Sterilizer Mobile): the
     * people who key the data in are entitled to read it back. Note the
     * split this creates — the API endpoints admit Operator, the WEB route
     * /reports/sterilizer still does not, because Operator has no web UI at
     * all. Widening the route middleware alone was NOT enough: Operator
     * cleared the middleware and was then refused here, two layers deeper.
     *
     * @throws ValidationException 422 VALIDATION_ERROR (admin, no mill picked)
     * @throws AuthorizationException 403 FORBIDDEN (no session, or a role
     *                                outside supervisor / mill_management / operator / admin)
     */
    public function resolveBusinessUnit(?string $requestedBusinessUnitId): string
    {
        $user = auth()->user();

        if ($user === null) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk aksi ini.');
        }

        $role = $this->roleOf($user);

        if ($role === UserRole::Supervisor->value
            || $role === UserRole::MillManagement->value
            || $role === UserRole::Operator->value) {
            // Client-supplied business_unit_id is deliberately discarded.
            return (string) $user->business_unit_id;
        }

        if ($role === UserRole::Admin->value) {
            if ($requestedBusinessUnitId === null || $requestedBusinessUnitId === '') {
                throw ValidationException::withMessages([
                    'business_unit_id' => ['Pilih mill terlebih dahulu untuk menampilkan laporan.'],
                ]);
            }

            return $requestedBusinessUnitId;
        }

        throw new AuthorizationException('Anda tidak memiliki akses untuk aksi ini.');
    }

    /**
     * Mill picker options — ADMIN ONLY. Supervisor and Mill Management are
     * bound to a single mill and have no use for this list, so asking for
     * it is a 403 rather than a filtered list.
     *
     * @return list<array{id: string, name: string}>
     *
     * @throws AuthorizationException 403 FORBIDDEN
     */
    public function businessUnitOptions(): array
    {
        $user = auth()->user();

        if ($user === null || $this->roleOf($user) !== UserRole::Admin->value) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk aksi ini.');
        }

        return BusinessUnit::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (BusinessUnit $businessUnit) => [
                'id' => (string) $businessUnit->id,
                'name' => (string) $businessUnit->name,
            ])
            ->all();
    }

    /**
     * business_logic step 2 — the periods selectable for this mill.
     *
     * A period covers Sterilizer when it HAS a `period_stations` row for
     * station_type 'sterilizer'. The old second branch — station_type NULL,
     * meaning "this period applies to every station type" — is GONE with the
     * column itself (2026-09-25): all-station scope is now expressed by the
     * PRESENCE of one row per station type, so a period WITHOUT a 'sterilizer'
     * row is deliberately not listed here at all. Newest first. An empty array
     * is a valid answer — a mill with no period yet gets [] with HTTP 200 and a
     * UI hint pointing at Kelola Periode Pelaporan, never a 404.
     *
     * @return list<array{id: string, name: string, start_date: string, end_date: string, status: string, station_type: string, station_type_label: string}>
     */
    public function listPeriods(string $businessUnitId): array
    {
        return Period::query()
            ->where('business_unit_id', $businessUnitId)
            ->whereHas('stations', fn (Builder $query) => $query->where('station_type', self::STATION_TYPE))
            // Dimuat terbatas pada jenis stasiun ini supaya statusValue()
            // tidak menembak satu kueri per periode (N+1).
            ->with(['stations' => fn ($query) => $query->where('station_type', self::STATION_TYPE)])
            ->orderByDesc('start_date')
            ->orderBy('name')
            ->get()
            ->map(fn (Period $period) => $this->periodOption($period))
            ->all();
    }

    /**
     * Pemilih Production Line — opsi line DI DALAM mill yang berlaku.
     *
     * Berlaku untuk SEMUA peran, tidak seperti businessUnitOptions() yang
     * khusus Admin: production line BUKAN ikatan akun (tidak ada
     * `users.production_line_id`, dan tidak boleh ada) melainkan KONTEKS
     * YANG DIPILIH. Supervisor pun memilih line, karena satu mill di
     * lapangan punya belasan production line dengan jenis stasiun yang
     * sama berulang di tiap line.
     *
     * Daftar ini SELALU dibatasi mill yang berlaku, sehingga line mill lain
     * tidak pernah menjadi opsi — itu separuh pertama dari jaminan "line
     * mill lain diabaikan"; separuhnya lagi ada di resolveProductionLine(),
     * yang menutup jalur properti/query string.
     *
     * @return list<array{id: string, name: string}>
     */
    public function productionLineOptions(string $businessUnitId): array
    {
        return ProductionLine::query()
            ->where('business_unit_id', $businessUnitId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (ProductionLine $line) => [
                'id' => (string) $line->id,
                'name' => (string) $line->name,
            ])
            ->all();
    }

    /**
     * Line yang benar-benar berlaku untuk laporan ini, atau null bila belum
     * ada pilihan yang sah.
     *
     * MEMILIH LINE WAJIB di layar laporan — berbeda dari Data Browser, yang
     * punya opsi "Semua Line". Alasannya menentukan: laporan menghasilkan
     * ANGKA GABUNGAN, dan sebuah total yang mencampur belasan line bukan
     * angka yang bisa ditindaklanjuti siapa pun. Karena itu null di sini
     * berarti "jangan tampilkan angka apa pun", BUKAN "tampilkan semua
     * line".
     *
     * Line milik mill lain DIABAIKAN, persis seperti business_unit_id
     * kiriman klien diabaikan untuk peran terikat mill: ia dipulangkan
     * sebagai null, sehingga hasilnya adalah layar yang meminta memilih
     * line — bukan 403 (yang justru memastikan line itu ada), dan tidak
     * pernah data mill lain.
     */
    public function resolveProductionLine(string $businessUnitId, ?string $requestedProductionLineId): ?string
    {
        if ($requestedProductionLineId === null || $requestedProductionLineId === '') {
            return null;
        }

        $belongsToMill = ProductionLine::query()
            ->whereKey($requestedProductionLineId)
            ->where('business_unit_id', $businessUnitId)
            ->exists();

        return $belongsToMill ? $requestedProductionLineId : null;
    }

    /**
     * business_logic step 3 — load a period and prove the caller may read
     * it.
     *
     * 404 when the id does not exist. 403 when it belongs to another mill
     * and the caller is Supervisor / Mill Management — THIS is the real
     * cross-mill leak path (a period id is a concrete handle to another
     * mill's data), so unlike the ignored business_unit_id query param it
     * is refused outright. Admin passes for any mill; Operator is treated
     * exactly like Supervisor / Mill Management (widened 2026-09-23) — it
     * passes only for its own mill.
     *
     * @throws ModelNotFoundException 404 NOT_FOUND
     * @throws AuthorizationException 403 FORBIDDEN
     */
    public function authorizePeriod(string $periodId): Period
    {
        /** @var Period $period */
        $period = Period::query()->with('businessUnit')->findOrFail($periodId);

        $user = auth()->user();

        if ($user === null) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk aksi ini.');
        }

        $role = $this->roleOf($user);

        if ($role === UserRole::Admin->value) {
            return $period;
        }

        if ($role !== UserRole::Supervisor->value
            && $role !== UserRole::MillManagement->value
            && $role !== UserRole::Operator->value) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk aksi ini.');
        }

        if ((string) $period->business_unit_id !== (string) $user->business_unit_id) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk aksi ini.');
        }

        return $period;
    }

    /**
     * business_logic steps 4-10 — every figure on the screen for one
     * period: period header, KPI, daily trend, per-unit comparison,
     * outliers, and the period total.
     *
     * Membership is decided by sterilizer_records.date — the date the
     * sterilization actually happened — INCLUSIVE on both bounds, and never
     * by created_at or the mobile sync time. A cycle entered late still
     * belongs to the period it happened in.
     *
     * @param  Period|string  $period  model or id (both accepted so callers
     *                                 that already authorised the period do
     *                                 not have to re-read it)
     * @return array{period: array, kpi: array, daily: list<array>, by_unit: list<array>, outliers: array, total: array}
     */
    public function summary(Period|string $period, ?string $productionLineId = null): array
    {
        $period = $this->resolvePeriod($period);

        $cycles = $this->cyclesFor($period, $productionLineId);

        return [
            'period' => [
                'id' => (string) $period->id,
                'name' => (string) $period->name,
                'start_date' => $period->start_date->toDateString(),
                'end_date' => $period->end_date->toDateString(),
                'status' => $this->statusValue($period),
                'business_unit_name' => (string) ($period->businessUnit?->name ?? ''),
            ],
            // TAMBAHAN, bukan perubahan bentuk: kunci baru di samping yang
            // sudah ada, sehingga pembaca lama tidak terpengaruh sama sekali.
            'production_line' => $this->productionLineInfo($productionLineId),
            'kpi' => $this->kpiOf($cycles),
            'daily' => $this->dailyOf($cycles),
            'by_unit' => $this->byUnitOf($cycles),
            'outliers' => $this->outliersOf($cycles),
            'total' => $this->totalOf($cycles),
        ];
    }

    /**
     * business_logic step 11 — one exported line per CYCLE, with the
     * record's context columns (Sterilizer ID / Date / Note / Checked By /
     * Acknowledged By / Status) repeated on every line so the file can be
     * pivoted directly in a spreadsheet. Same shape as
     * SterilizerRecordService::export() (commit 8611974), scoped to a
     * period instead of an ad-hoc filter.
     *
     * The 50.000 ceiling counts CYCLES, not header records.
     *
     * @throws ExportFailedException 422 EXPORT_FAILED
     */
    public function export(Period|string $period, string $format = 'csv', ?string $requestedBusinessUnitId = null, ?string $productionLineId = null): StreamedResponse
    {
        // DISERAGAMKAN 2026-09-25 — keempat service laporan kini menerima
        // mill yang berlaku dan memvalidasinya di lapis service. Sebelumnya
        // service ini tidak memanggil resolveBusinessUnit() di jalur ekspor
        // sama sekali, sehingga ekspor Admin lolos tanpa pernah memeriksa
        // "mill sudah dipilih" — kebalikan dari BoilerRoom, yang justru
        // menolak Admin karena pemanggilnya lupa meneruskan argumennya.
        // Ketidakseragaman itulah yang melahirkan kedua cacat sekaligus.
        $this->resolveBusinessUnit($requestedBusinessUnitId);
        $period = $this->resolvePeriod($period);

        $recordQuery = $this->recordQueryFor($period, $productionLineId);

        $cycleRowCount = SterilizerDetail::query()
            ->whereIn('sterilizer_record_id', (clone $recordQuery)->select('sterilizer_records.id'))
            ->count();

        if ($cycleRowCount > self::EXPORT_ROW_LIMIT) {
            throw new ExportFailedException;
        }

        try {
            $query = (clone $recordQuery)
                ->with([
                    'checkedBy:id,name',
                    'acknowledgedBy:id,name',
                    'sterilizerDetails' => fn ($detailQuery) => $detailQuery->orderBy('sterilizer_no'),
                ])
                ->orderBy('sterilizer_records.date')
                ->orderBy('sterilizer_records.id');

            [$contentType, $filename] = $this->fileMetaFor($format, $period);

            // Kolom konteks (Periode/Mill/Production Line) diulang di setiap
            // baris seperti ekspor Laporan Weighbridge; judul kolom Bahasa
            // Indonesia seperti kelima laporan lain; status berlabel
            // Indonesia; jam HH:MM (temuan audit 2026-10-04 #8a–d).
            $exportContext = [
                (string) $period->name,
                (string) ($period->businessUnit?->name ?? ''),
                (string) ($this->productionLineInfo($productionLineId)['name'] ?? ''),
            ];

            return response()->streamDownload(function () use ($query, $format, $exportContext) {
                $handle = SheetWriter::open($format);
                $handle->row(self::EXPORT_HEADER);

                $query->chunk(200, function ($records) use ($handle, $exportContext) {
                    foreach ($records as $record) {
                        /** @var SterilizerRecord $record */
                        $context = array_merge($exportContext, [
                            optional($record->date)->toDateString(),
                            $record->sterilizer_id,
                            $record->note,
                            $record->checkedBy?->name,
                            $record->acknowledgedBy?->name,
                            ExportValue::status($record->status),
                        ]);

                        foreach ($record->sterilizerDetails as $detail) {
                            /** @var SterilizerDetail $detail */
                            $handle->row(array_merge($context, [
                                $detail->sterilizer_no,
                                ExportValue::time($detail->close_door_time),
                                ExportValue::time($detail->peak_1_time),
                                ExportValue::time($detail->exhaust_1_time),
                                ExportValue::time($detail->peak_2_time),
                                ExportValue::time($detail->exhaust_2_time),
                                ExportValue::time($detail->peak_3_time),
                                ExportValue::time($detail->exhaust_3_time),
                                ExportValue::time($detail->open_door_time),
                                $detail->duration_minutes,
                                $detail->number_of_cages,
                                $detail->cages_status,
                                ExportValue::yesNo($detail->checked_by_spv),
                                $detail->remarks,
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

    // ------------------------------------------------------------------
    // Aggregation
    // ------------------------------------------------------------------

    /**
     * Every Sterilizer cycle inside the period, as plain rows.
     *
     * Aggregated in PHP rather than in SQL on purpose: the null semantics
     * this report demands (avg/min/max must be null — not 0 — when no cycle
     * has a duration; a cycle without a duration still counts towards
     * total_cycles) are easy to state here and easy to get subtly wrong in
     * portable SQL across sqlite/pgsql. The row set is bounded by one
     * reporting period, and the export path — the only unbounded one —
     * streams in chunks instead.
     *
     * @return Collection<int, object>
     */
    protected function cyclesFor(Period $period, ?string $productionLineId = null): Collection
    {
        $query = SterilizerDetail::query()
            ->join('sterilizer_records', 'sterilizer_records.id', '=', 'sterilizer_details.sterilizer_record_id')
            ->join('stations', 'stations.id', '=', 'sterilizer_records.station_id')
            ->where('stations.business_unit_id', $period->business_unit_id)
            ->where('stations.type', StationTypeEnum::Sterilizer->value);

        $this->scopeToProductionLine($query, $productionLineId);

        return $query
            // Inclusive on both bounds, on the event date — not created_at.
            ->whereDate('sterilizer_records.date', '>=', $period->start_date->toDateString())
            ->whereDate('sterilizer_records.date', '<=', $period->end_date->toDateString())
            ->orderBy('sterilizer_records.date')
            ->orderBy('sterilizer_details.sterilizer_no')
            ->get([
                'sterilizer_records.date as record_date',
                'sterilizer_records.sterilizer_id as record_sterilizer_id',
                'sterilizer_details.sterilizer_no as sterilizer_no',
                'sterilizer_details.duration_minutes as duration_minutes',
                'sterilizer_details.number_of_cages as number_of_cages',
                'sterilizer_details.cages_status as cages_status',
                'sterilizer_details.close_door_time as close_door_time',
                'sterilizer_details.open_door_time as open_door_time',
                'sterilizer_details.peak_1_time as peak_1_time',
                'sterilizer_details.peak_2_time as peak_2_time',
                'sterilizer_details.peak_3_time as peak_3_time',
                'sterilizer_details.exhaust_1_time as exhaust_1_time',
                'sterilizer_details.exhaust_2_time as exhaust_2_time',
                'sterilizer_details.exhaust_3_time as exhaust_3_time',
            ])
            ->map(fn ($row) => (object) [
                'date' => $this->dateStringOf($row->record_date),
                'sterilizer_no' => $row->sterilizer_no !== null ? (string) $row->sterilizer_no : '',
                'duration_minutes' => $row->duration_minutes !== null ? (int) $row->duration_minutes : null,
                'number_of_cages' => (int) ($row->number_of_cages ?? 0),
                'cages_status' => $row->cages_status,
                'close_door_time' => $row->close_door_time,
                'open_door_time' => $row->open_door_time,
                'triple_peak_complete' => $this->isTriplePeakComplete($row),
            ])
            ->values();
    }

    /**
     * KPI block. total_cycles counts EVERY cycle; avg/min/max come only
     * from cycles that have a duration, and are null (not 0) when there is
     * none — 0 would be indistinguishable from a real zero-minute duration.
     * cycles_without_duration is reported so the average can never be read
     * as covering more cycles than it does.
     *
     * @param  Collection<int, object>  $cycles
     */
    protected function kpiOf(Collection $cycles): array
    {
        $durations = $this->durationsOf($cycles);
        $totalCycles = $cycles->count();
        $compliant = $cycles->filter(fn ($cycle) => $cycle->triple_peak_complete)->count();

        return [
            'total_cycles' => $totalCycles,
            'total_cages' => (int) $cycles->sum('number_of_cages'),
            'avg_duration_minutes' => $this->avgOf($durations),
            'min_duration_minutes' => $durations->isEmpty() ? null : (int) $durations->min(),
            'max_duration_minutes' => $durations->isEmpty() ? null : (int) $durations->max(),
            'cycles_without_duration' => $cycles->filter(fn ($cycle) => $cycle->duration_minutes === null)->count(),
            // Guarded division: an empty period is 0%, never a
            // DivisionByZeroError.
            'triple_peak_compliance_percent' => $totalCycles === 0
                ? 0.0
                : round(100 * $compliant / $totalCycles, 1),
        ];
    }

    /**
     * One entry per date that actually has cycles. Dates with no cycle are
     * NOT padded with zero rows — an empty bar would read as "we measured
     * nothing that day" rather than "the mill did not run".
     *
     * @param  Collection<int, object>  $cycles
     * @return list<array>
     */
    protected function dailyOf(Collection $cycles): array
    {
        return $cycles
            ->groupBy('date')
            ->map(function (Collection $group, string $date) {
                $durations = $this->durationsOf($group);

                return [
                    'date' => $date,
                    'cycles' => $group->count(),
                    'cages' => (int) $group->sum('number_of_cages'),
                    'avg_duration' => $this->avgOf($durations),
                    'min_duration' => $durations->isEmpty() ? null : (int) $durations->min(),
                    'max_duration' => $durations->isEmpty() ? null : (int) $durations->max(),
                    'triple_peak_complete' => $group->filter(fn ($cycle) => $cycle->triple_peak_complete)->count(),
                    'cycles_without_duration' => $group->filter(fn ($cycle) => $cycle->duration_minutes === null)->count(),
                ];
            })
            ->sortKeys()
            ->values()
            ->all();
    }

    /**
     * Per Sterilizer unit (sterilizer_no). avg_duration per unit again only
     * from that unit's cycles that have a duration.
     *
     * @param  Collection<int, object>  $cycles
     * @return list<array>
     */
    protected function byUnitOf(Collection $cycles): array
    {
        return $cycles
            ->groupBy('sterilizer_no')
            ->map(function (Collection $group, string $sterilizerNo) {
                return [
                    'sterilizer_no' => $sterilizerNo,
                    'cycles' => $group->count(),
                    'cages' => (int) $group->sum('number_of_cages'),
                    'avg_duration' => $this->avgOf($this->durationsOf($group)),
                    'triple_peak_complete' => $group->filter(fn ($cycle) => $cycle->triple_peak_complete)->count(),
                ];
            })
            ->sortKeys()
            ->values()
            ->all();
    }

    /**
     * Tukey fence (quartile method), NOT mean +/- 2 standard deviations.
     * The reason is decisive: a standard deviation is inflated by the very
     * cycles it is meant to expose, so one 500-minute cycle widens the band
     * enough to hide itself. Quartiles are immune to that, and the bounds
     * they produce are readable by a human ("74 - 106 minutes") instead of
     * being an abstract sigma. There is no operational target master for
     * Sterilizer, so the threshold has to come from the period's own
     * spread.
     *
     * Below OUTLIER_MIN_SAMPLE_SIZE durations the fence is not computed at
     * all (insufficient_data = true, bounds null) — flagging outliers out
     * of a handful of rows misleads more than it informs.
     *
     * Uniform durations give IQR = 0, so lower = upper = that value, items
     * are empty, and insufficient_data stays FALSE: the bounds are still
     * returned so the screen can state the threshold it applied.
     *
     * @param  Collection<int, object>  $cycles
     */
    protected function outliersOf(Collection $cycles): array
    {
        $durations = $this->durationsOf($cycles)->sort()->values();
        $sampleSize = $durations->count();

        $result = [
            'method' => 'iqr',
            'lower_bound' => null,
            'upper_bound' => null,
            'min_sample_size' => self::OUTLIER_MIN_SAMPLE_SIZE,
            'sample_size' => $sampleSize,
            'insufficient_data' => $sampleSize < self::OUTLIER_MIN_SAMPLE_SIZE,
            'items' => [],
        ];

        if ($result['insufficient_data']) {
            return $result;
        }

        $q1 = $this->percentileOf($durations, 0.25);
        $q3 = $this->percentileOf($durations, 0.75);
        $iqr = $q3 - $q1;

        $result['q1'] = round($q1, 2);
        $result['q3'] = round($q3, 2);
        $result['iqr'] = round($iqr, 2);
        $result['lower_bound'] = round($q1 - 1.5 * $iqr, 2);
        $result['upper_bound'] = round($q3 + 1.5 * $iqr, 2);

        $result['items'] = $cycles
            ->filter(fn ($cycle) => $cycle->duration_minutes !== null
                && ($cycle->duration_minutes < $result['lower_bound'] || $cycle->duration_minutes > $result['upper_bound']))
            ->sortByDesc('duration_minutes')
            ->map(fn ($cycle) => [
                'date' => $cycle->date,
                'sterilizer_no' => $cycle->sterilizer_no,
                'duration_minutes' => $cycle->duration_minutes,
                'number_of_cages' => $cycle->number_of_cages,
                'cages_status' => $cycle->cages_status,
                'close_door_time' => $cycle->close_door_time,
                'open_door_time' => $cycle->open_door_time,
            ])
            ->values()
            ->all();

        return $result;
    }

    /**
     * Period total row shown under the daily recap table.
     *
     * @param  Collection<int, object>  $cycles
     */
    protected function totalOf(Collection $cycles): array
    {
        $durations = $this->durationsOf($cycles);

        return [
            'cycles' => $cycles->count(),
            'cages' => (int) $cycles->sum('number_of_cages'),
            'avg_duration' => $this->avgOf($durations),
            'min_duration' => $durations->isEmpty() ? null : (int) $durations->min(),
            'max_duration' => $durations->isEmpty() ? null : (int) $durations->max(),
            'triple_peak_complete' => $cycles->filter(fn ($cycle) => $cycle->triple_peak_complete)->count(),
            'cycles_without_duration' => $cycles->filter(fn ($cycle) => $cycle->duration_minutes === null)->count(),
        ];
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Linear-interpolated percentile over an already-sorted list, index =
     * p * (n - 1) — the same definition numpy/Excel's PERCENTILE.INC use,
     * so a reviewer recomputing the bounds in a spreadsheet gets the
     * identical number.
     *
     * @param  Collection<int, int>  $sorted
     */
    protected function percentileOf(Collection $sorted, float $percentile): float
    {
        $values = $sorted->values();
        $count = $values->count();

        if ($count === 0) {
            return 0.0;
        }

        if ($count === 1) {
            return (float) $values->first();
        }

        $position = $percentile * ($count - 1);
        $lowerIndex = (int) floor($position);
        $upperIndex = (int) ceil($position);
        $fraction = $position - $lowerIndex;

        $lower = (float) $values->get($lowerIndex);
        $upper = (float) $values->get($upperIndex);

        return $lower + ($upper - $lower) * $fraction;
    }

    /**
     * Durations of the cycles that HAVE one. Cycles whose open-door time is
     * still blank are excluded here and reported separately — they must not
     * drag an average down to a number nobody can reproduce.
     *
     * @param  Collection<int, object>  $cycles
     * @return Collection<int, int>
     */
    protected function durationsOf(Collection $cycles): Collection
    {
        return $cycles
            ->map(fn ($cycle) => $cycle->duration_minutes)
            ->filter(fn ($duration) => $duration !== null)
            ->map(fn ($duration) => (int) $duration)
            ->values();
    }

    /**
     * @param  Collection<int, int>  $durations
     */
    protected function avgOf(Collection $durations): ?float
    {
        if ($durations->isEmpty()) {
            return null;
        }

        return round($durations->sum() / $durations->count(), 1);
    }

    /**
     * Triple-peak compliance demands ALL SIX times — three pressure peaks
     * and three exhausts. One blank time makes the cycle non-compliant;
     * there is no partial credit, because a cycle that skipped an exhaust
     * did not follow the boiling pattern at all.
     */
    protected function isTriplePeakComplete(object $row): bool
    {
        foreach (['peak_1_time', 'peak_2_time', 'peak_3_time', 'exhaust_1_time', 'exhaust_2_time', 'exhaust_3_time'] as $column) {
            if (($row->{$column} ?? null) === null || $row->{$column} === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * Base query over the period's Sterilizer records (header rows) — used
     * by the export path, which streams headers and walks their cycles.
     */
    protected function recordQueryFor(Period $period, ?string $productionLineId = null): Builder
    {
        $query = SterilizerRecord::query()
            ->join('stations', 'stations.id', '=', 'sterilizer_records.station_id')
            ->where('stations.business_unit_id', $period->business_unit_id)
            ->where('stations.type', StationTypeEnum::Sterilizer->value)
            ->whereDate('sterilizer_records.date', '>=', $period->start_date->toDateString())
            ->whereDate('sterilizer_records.date', '<=', $period->end_date->toDateString())
            ->select('sterilizer_records.*');

        $this->scopeToProductionLine($query, $productionLineId);

        return $query;
    }

    /**
     * Blok `production_line` pada respons ringkasan — TAMBAHAN, bukan
     * perubahan bentuk: seluruh kunci yang sudah ada tetap di tempatnya dan
     * tetap datar, sehingga blade dan layar mobile yang membacanya apa
     * adanya nol perubahan. null ketika tidak ada line yang berlaku.
     *
     * @return array{id: string, name: string}|null
     */
    protected function productionLineInfo(?string $productionLineId): ?array
    {
        if ($productionLineId === null || $productionLineId === '') {
            return null;
        }

        /** @var ProductionLine|null $line */
        $line = ProductionLine::query()->find($productionLineId, ['id', 'name']);

        if ($line === null) {
            return null;
        }

        return [
            'id' => (string) $line->id,
            'name' => (string) $line->name,
        ];
    }

    /**
     * Penyaringan per production line, DI KOLOM TABEL RECORD — bukan lewat
     * join ke `stations`.
     *
     * Sejak commit ccc884d `sterilizer_records.production_line_id` adalah
     * kolom nyata NOT NULL yang di-SNAPSHOT dari stasiun saat record dibuat
     * dan tidak pernah berubah sesudahnya. Membaca dari kolom record itulah
     * yang benar secara semantik: untuk record lama yang stasiunnya sudah
     * DIPINDAH ke line lain, kolom record menunjuk line tempat data itu
     * benar-benar dihasilkan, sedangkan `stations.production_line_id`
     * menunjuk line stasiun itu SEKARANG. Menyaring lewat join ke
     * `stations` akan menulis ulang sejarah setiap kali sebuah stasiun
     * dipindahkan.
     *
     * Ia juga lebih murah: kolomnya ada di tabel record, jadi tidak perlu
     * join tambahan sama sekali.
     */
    protected function scopeToProductionLine(mixed $query, ?string $productionLineId): void
    {
        if ($productionLineId === null || $productionLineId === '') {
            return;
        }

        $query->where('sterilizer_records.production_line_id', $productionLineId);
    }

    protected function resolvePeriod(Period|string $period): Period
    {
        if ($period instanceof Period) {
            return $period->relationLoaded('businessUnit') ? $period : $period->load('businessUnit');
        }

        /** @var Period $model */
        $model = Period::query()->with('businessUnit')->findOrFail($period);

        return $model;
    }

    /**
     * Satu opsi periode UNTUK LAYAR INI. Bentuknya sengaja tetap DATAR,
     * persis seperti sebelum 2026-09-25, karena layar mobile dan blade
     * membacanya apa adanya — pemisahan periods/period_stations tidak
     * merembes ke kontrak API.
     *
     * Bacaannya: yang diminta layar ini bukan periode telanjang melainkan
     * pasangan (periode, sterilizer). Karena itu `status` adalah status
     * STASIUN INI di periode itu (period_stations.status) — periode sendiri
     * tidak punya status lagi — dan `station_type` selalu terisi: ia tidak
     * pernah null lagi karena hanya periode yang punya baris untuk jenis
     * ini yang sampai ke sini.
     *
     * @return array{id: string, name: string, start_date: string, end_date: string, status: string, station_type: string, station_type_label: string}
     */
    protected function periodOption(Period $period): array
    {
        return [
            'id' => (string) $period->id,
            'name' => (string) $period->name,
            'start_date' => $period->start_date->toDateString(),
            'end_date' => $period->end_date->toDateString(),
            'status' => $this->statusValue($period),
            'station_type' => self::STATION_TYPE,
            'station_type_label' => $this->stationTypeLabel(self::STATION_TYPE),
        ];
    }

    /**
     * Status yang dilaporkan layar ini adalah status BARIS period_stations
     * untuk jenis stasiun layar ini, bukan status periode: sejak 2026-09-25
     * periode tidak punya status sendiri (Period::$status melempar
     * LogicException), karena stasiun tidak ditutup serentak — Sterilizer
     * bisa tertutup sementara Clarification masih terbuka di periode yang
     * sama.
     *
     * Tanpa baris untuk jenis ini, jenis stasiun ini tidak dikelola periode
     * itu, dan jawabannya 'draft' — arti draft memang "stasiun ini belum
     * dipakai di periode ini". Sengaja bukan string kosong: nilai status
     * harus tetap salah satu dari draft/open/closed karena blade dan layar
     * mobile mencocokkannya. Bentuk ini hanya bisa muncul lewat summary()/
     * export(): listPeriods() tidak pernah memulangkan periode tanpa baris.
     */
    protected function statusValue(Period $period): string
    {
        $status = $this->stationRowOf($period)?->status;

        if ($status === null) {
            return PeriodStatus::Draft->value;
        }

        return is_object($status) ? $status->value : (string) $status;
    }

    /**
     * Baris period_stations untuk jenis stasiun layar ini. Memakai relasi
     * yang sudah di-eager-load bila ada — listPeriods() memuatnya terbatas
     * pada jenis ini — dan baru menembak kueri sendiri bila belum.
     */
    protected function stationRowOf(Period $period): ?PeriodStation
    {
        if ($period->relationLoaded('stations')) {
            return $period->stations->firstWhere('station_type', self::STATION_TYPE);
        }

        return $period->stations()
            ->where('station_type', self::STATION_TYPE)
            ->first();
    }

    /**
     * Label resolved from the `station_types` master table, not from
     * App\Enums\StationType — station types are DATA since 2026-09-22, so a
     * type added by INSERT must render its real name without a code change.
     */
    protected function stationTypeLabel(string $code): string
    {
        if ($this->stationTypeNames === null) {
            $this->stationTypeNames = StationType::query()
                ->get(['code', 'name'])
                ->pluck('name', 'code')
                ->all();
        }

        return $this->stationTypeNames[$code] ?? $code;
    }

    protected function dateStringOf(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return substr((string) $value, 0, 10);
    }

    protected function roleOf(object $user): string
    {
        return $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
    }

    /**
     * Same Content-Type/filename convention as every other export in this
     * codebase. format=excel is a real .xlsx written by App\Support\SheetWriter (temuan
     * audit 2026-10-04 #1 — previously a CSV body under an xlsx name).
     *
     * @return array{0: string, 1: string}
     */
    protected function fileMetaFor(string $format, Period $period): array
    {
        $slug = str($period->name !== '' ? $period->name : 'periode')->slug()->value();
        $timestamp = now()->format('Ymd_His');

        if ($format === 'excel') {
            return [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                "laporan-sterilizer_{$slug}_{$timestamp}.xlsx",
            ];
        }

        return [
            'text/csv',
            "laporan-sterilizer_{$slug}_{$timestamp}.csv",
        ];
    }
}
