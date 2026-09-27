<?php

namespace App\Services;

use App\Enums\PeriodStatus;
use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Models\BusinessUnit;
use App\Models\CagesTippedTime;
use App\Models\CagesTrackRecord;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\StationType;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * CagesTrackReportService — screen-130--laporan-cages-track-web /
 * usecase-130--laporan-cages-track-web (Laporan Periode Cages & Tracks).
 *
 * Shared by the API controller (App\Http\Controllers\Api\
 * CagesTrackReportController) and the Livewire component (App\Livewire\
 * Dashboard\LaporanCagesTrack), the same split used by
 * SterilizerReportService / SterilizerReportController /
 * LaporanSterilizer — so the web page and the API can never disagree on a
 * figure.
 *
 * READ-ONLY BY CONSTRUCTION: every public method is a SELECT, and the
 * /api/cages-track-reports prefix carries no POST/PUT/PATCH/DELETE. A
 * report must never be able to mutate the data it reports on.
 *
 * ------------------------------------------------------------------
 * WHAT IS DIFFERENT FROM THE STERILIZER REPORT — and why
 * ------------------------------------------------------------------
 * Sterilizer works in discrete cycles; Cages & Tracks is an HOURLY
 * station. The interesting figure is not the total but the SHAPE OF THE
 * DAY: when tipping peaks, when it stops, how long the longest lull is,
 * how many cages are still queued. That forces five rules that are easy
 * to get silently wrong, each of which has a dedicated unit test:
 *
 *  1. GRAIN. `cages_out` lives on the HEADER (cages_track_records, one per
 *     record); `total_cages` lives on the HOURLY DETAIL
 *     (cages_tipped_times, one per hour). Summing cages_out over a JOIN to
 *     the details multiplies it by the number of hour rows — 50 becomes
 *     400 for a record with 8 detail rows. cages_out is therefore summed
 *     by iterating RECORDS, never details.
 *  2. `cages_track_records.cages_tipped` (the header summary field) is
 *     NEVER a source of any tipping figure. Every tipping figure comes
 *     from cages_tipped_times.total_cages — the two can differ when an
 *     Operator corrects one of them, and the hourly rows are the ones
 *     recorded per event.
 *  3. DURATION comes from the difference of FULL TIMESTAMPS, never from
 *     the hour components. tippler_start_time / tippler_stop_time are
 *     datetimes entered through datetime-local inputs, so a night shift
 *     already carries the next date on its stop. Using hour components
 *     yields -18 hours for 22:00 -> 04:00.
 *  4. MODULAR ARITHMETIC IS CORRECT IN EXACTLY ONE PLACE: the set of
 *     OPERATING HOURS. `tipped_hour` is a bare integer 0..23 with no date,
 *     so the set is walked forward from the start hour, ceil(minutes/60)
 *     steps, modulo 24, capped at 24 distinct hours. 22:00 -> 04:00 gives
 *     {22,23,0,1,2,3}.
 *  5. NULL IS NOT ZERO. longest_gap_hours is null when a period has fewer
 *     than 2 distinct tipping hours; avg_tippler_duration_hours is null
 *     when no date has a computable window. Zero means "no gap" / "the
 *     tippler never ran"; null means "cannot be computed".
 *
 * ------------------------------------------------------------------
 * CROSS-MILL SECURITY IS CLOSED AT TWO DIFFERENT POINTS, on purpose
 * ------------------------------------------------------------------
 *   1. resolveBusinessUnit() IGNORES the client's business_unit_id for
 *      Supervisor / Mill Management — not validated, not compared,
 *      discarded. Probing another mill's id returns 200 with the CALLER'S
 *      OWN data, deliberately not a 403: a 403 would confirm the other
 *      mill exists, and there is no access attempt to refuse because the
 *      parameter is never used for those roles.
 *   2. authorizePeriod() REFUSES a period belonging to another mill with
 *      403. Here there IS a concrete handle to another mill's data, so it
 *      is refused outright rather than silently rewritten.
 * Folding these two into one uniform 403 is the mistake this class exists
 * to avoid.
 *
 * A Supervisor / Mill Management account whose users.business_unit_id is
 * NULL FAILS CLOSED with 422 and the whole-mill list is never even read —
 * see allBusinessUnits().
 *
 * OPERATOR IS ACCEPTED SINCE 2026-09-24 (screen-136 — the mobile Cages &
 * Tracks report), mirroring the widening SterilizerReportService received
 * on 2026-09-23 for screen-135. Operator is treated exactly like
 * Supervisor / Mill Management: MILL-BOUND. Three sites had to change in
 * the same breath, and skipping any one of them opens a hole:
 *   a. routes/api.php — the four /api/cages-track-reports routes gained
 *      the `sanctum` guard (mobile carries a token, not a session cookie)
 *      and `operator` in the role list;
 *   b. guardAccess() below — Operator added to the accepted-role list;
 *   c. resolveBusinessUnit() below — Operator added to the MILL-BOUND
 *      branch. Doing (b) WITHOUT (c) would have dropped Operator into the
 *      unbound Admin branch, where a client-supplied business_unit_id is
 *      honoured — i.e. an Operator could have read any mill's report.
 *      That is the single most important line of this widening.
 *
 * THE WIDENING STOPS AT THE API. The WEB route /reports/cages-track and
 * App\Livewire\Dashboard\LaporanCagesTrack::canAccess() deliberately stay
 * without Operator, which has no web UI at all — canAccess() keeps its own
 * role list precisely so that this service can widen without dragging the
 * web page along.
 *
 * WHAT DID NOT CHANGE, on purpose — this service stays STRICTER than
 * SterilizerReportService on two points, and they must not be "aligned"
 * away: (1) the mill-bound branch here FAILS CLOSED with 422 when the
 * account has no business_unit_id, where the Sterilizer version returns
 * (string) null silently; (2) a missing session is 401
 * (AuthenticationException) here, not 403.
 */
class CagesTrackReportService
{
    /**
     * Export row ceiling, counted in EXPORTED LINES (= cages_tipped_times
     * hourly rows), not header records — one daily record carries up to 24
     * hourly rows, so counting headers would sail straight past the real
     * limit. Same trap that was fixed for the 18 station exports in commit
     * 8611974.
     */
    public const EXPORT_ROW_LIMIT = 50000;

    /**
     * Jenis stasiun yang dilaporkan layar ini — kunci baris
     * `period_stations` yang statusnya dipakai di seluruh payload layar ini.
     *
     * Menggantikan ALL_STATION_TYPES_LABEL ('Semua Stasiun'), yang hilang
     * bersama `periods.station_type` pada 2026-09-25: sebuah periode tidak
     * lagi bisa berlaku "untuk semua jenis stasiun" lewat station_type NULL —
     * cakupan itu kini dinyatakan lewat ADANYA satu baris period_stations per
     * jenis stasiun. Karena itu daftar periode layar ini adalah daftar
     * pasangan (periode, cages-track), dan tidak ada lagi opsi tanpa jenis stasiun.
     */
    protected const STATION_TYPE = StationTypeEnum::CagesTrack->value;

    /** Export formats this report understands. Anything else is 422. */
    public const SUPPORTED_FORMATS = ['csv', 'excel'];

    /**
     * code => name from the `station_types` master table, memoised per
     * service instance (one request / one Livewire render).
     *
     * @var array<string, string>|null
     */
    protected ?array $stationTypeNames = null;

    // ------------------------------------------------------------------
    // Access
    // ------------------------------------------------------------------

    /**
     * business_logic step 1 — which mill the caller is allowed to look at.
     *
     * Supervisor / Mill Management / Operator: ALWAYS their own
     * business_unit_id; the `business_unit_id` argument is ignored
     * outright, so probing another mill's id is a no-op that still returns
     * the caller's own data with HTTP 200.
     *
     * Admin: the value MUST come from the caller. Missing is 422
     * VALIDATION_ERROR with errors.business_unit_id — never a silent null
     * and never an empty result set, which would read as "this mill has no
     * data".
     *
     * 2026-09-24 (screen-136) — Operator joined the MILL-BOUND branch, not
     * the Admin branch. Admitting it in guardAccess() alone would have let
     * it fall through to the Admin branch below, where the client's
     * business_unit_id IS honoured: an Operator could then have read any
     * mill's report. The bound branch is where Operator belongs, and the
     * 422 fail-closed inside it applies to Operator too — an account with
     * no mill gets "Hubungi Admin", never the whole-mill list.
     *
     * @throws AuthenticationException 401 UNAUTHENTICATED
     * @throws AuthorizationException 403 FORBIDDEN
     * @throws ValidationException 422 VALIDATION_ERROR
     */
    public function resolveBusinessUnit(?string $requestedBusinessUnitId): string
    {
        $role = $this->guardAccess();

        if ($role === UserRole::Supervisor->value
            || $role === UserRole::MillManagement->value
            || $role === UserRole::Operator->value) {
            // Client-supplied business_unit_id is deliberately DISCARDED —
            // not validated, not compared, discarded.
            $businessUnitId = (string) (auth()->user()->business_unit_id ?? '');

            if ($businessUnitId === '') {
                // FAIL CLOSED. No fallback to "every mill": that would turn
                // one broken master-data row into a cross-mill leak.
                // allBusinessUnits() is NOT reached from this path, and a
                // spy on it proves so.
                throw ValidationException::withMessages([
                    'business_unit_id' => ['Akun Anda belum terhubung ke mill. Hubungi Admin.'],
                ]);
            }

            return $businessUnitId;
        }

        // Admin — the only role not bound to one mill, and the only role
        // that can reach this point: guardAccess() admits exactly four
        // roles and the other three are handled above.
        if ($requestedBusinessUnitId === null || $requestedBusinessUnitId === '') {
            // Incomplete input, not refused access — 422, never 403.
            throw ValidationException::withMessages([
                'business_unit_id' => ['Pilih mill terlebih dahulu untuk menampilkan laporan.'],
            ]);
        }

        return $requestedBusinessUnitId;
    }

    /**
     * Mill picker options — ADMIN ONLY. Supervisor, Mill Management and
     * Operator are bound to a single mill and have no picker at all, so
     * asking for this list is a 403 rather than a filtered list of one.
     * UNCHANGED by the screen-136 widening: Operator is admitted to the
     * prefix but still refused here, and the mobile view must therefore
     * not call this endpoint for a non-Admin.
     *
     * An empty master is a valid answer: [] with HTTP 200, never a 404.
     *
     * @return list<array{id: string, name: string}>
     *
     * @throws AuthenticationException 401 UNAUTHENTICATED
     * @throws AuthorizationException 403 FORBIDDEN
     */
    public function businessUnitOptions(): array
    {
        $user = auth()->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        if ($this->roleOf($user) !== UserRole::Admin->value) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk aksi ini.');
        }

        return $this->allBusinessUnits()
            ->map(fn (BusinessUnit $businessUnit) => [
                'id' => (string) $businessUnit->id,
                'name' => (string) $businessUnit->name,
            ])
            ->all();
    }

    /**
     * THE ONLY PLACE THIS SERVICE READS THE WHOLE-MILL LIST.
     *
     * Public and deliberately trivial so it can be spied on: the
     * "fail closed" rule for a Supervisor / Mill Management account with no
     * business_unit_id is only meaningful if it can be PROVEN that the
     * all-mills list was never built, and a spy that records zero calls to
     * this method is that proof.
     *
     * @return Collection<int, BusinessUnit>
     */
    public function allBusinessUnits(): Collection
    {
        return BusinessUnit::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * business_logic step 3 — load a period and prove the caller may read
     * it.
     *
     * 404 when the id does not exist. 403 when it belongs to another mill
     * and the caller is mill-bound (Supervisor / Mill Management /
     * Operator) — THIS is the real cross-mill leak path, so unlike the
     * ignored business_unit_id query param it is refused outright. Admin
     * passes for any mill.
     *
     * UNCHANGED by the screen-136 widening, and that is the point: only
     * Admin is treated as unbound here, so a newly admitted Operator falls
     * straight into the business_unit_id comparison below and gets 403 for
     * another mill's period, with no new code.
     *
     * The role guard still runs BEFORE the lookup, so a role that is not
     * admitted at all never learns whether a period id exists.
     *
     * @throws AuthenticationException 401 UNAUTHENTICATED
     * @throws AuthorizationException 403 FORBIDDEN
     * @throws ModelNotFoundException 404 NOT_FOUND
     */
    public function authorizePeriod(string $periodId): Period
    {
        $role = $this->guardAccess();

        /** @var Period $period */
        $period = Period::query()->with('businessUnit')->findOrFail($periodId);

        if ($role === UserRole::Admin->value) {
            return $period;
        }

        if ((string) $period->business_unit_id !== (string) (auth()->user()->business_unit_id ?? '')) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk aksi ini.');
        }

        return $period;
    }

    // ------------------------------------------------------------------
    // Reads
    // ------------------------------------------------------------------

    /**
     * business_logic step 2 — the periods selectable for this mill.
     *
     * A period covers Cages & Tracks when it HAS a `period_stations` row for
     * station_type 'cages-track'. The old second branch — station_type NULL,
     * meaning "this period applies to every station type" — is GONE with the
     * column itself (2026-09-25): all-station scope is now expressed by the
     * PRESENCE of one row per station type, so a period WITHOUT a 'cages-track'
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
     * business_logic steps 4-17 — every figure on the screen for one
     * period: period header, KPI, the 24-hour distribution, the daily
     * trend, the queue snapshot, the daily recap, and the period total.
     *
     * Membership is decided by cages_track_records.date — the date the
     * tipping actually happened — INCLUSIVE on both bounds, and never by
     * created_at or the mobile sync time. A row synced late still belongs
     * to the period it happened in.
     *
     * @param  Period|string  $period  model or id (both accepted so callers
     *                                 that already authorised the period do
     *                                 not have to re-read it)
     * @return array{period: array, kpi: array, hourly: list<array>, daily: list<array>, queue: array, total: array}
     */
    public function summary(Period|string $period): array
    {
        $this->guardAccess();

        $period = $this->resolvePeriod($period);

        $records = $this->recordsFor($period);
        $dates = $this->datesOf($records);
        $details = $this->detailsOf($records);

        $hourlyCages = $this->hourlyCagesOf($records);
        $totalCagesTipped = array_sum($hourlyCages);
        // GRAIN: summed by iterating RECORDS, never the hourly details.
        $totalCagesOut = (int) $records->sum('cages_out');

        [$peakHour, $peakHourCages] = $this->peakOf($hourlyCages, $totalCagesTipped);
        [$longestGapHours, $longestGapDate] = $this->longestGapAcross($dates);

        $daysWithRecords = $dates->count();
        $windowedDates = $dates->filter(fn ($row) => $row->window_computable);

        return [
            'period' => [
                'id' => (string) $period->id,
                'name' => (string) $period->name,
                'start_date' => $period->start_date->toDateString(),
                'end_date' => $period->end_date->toDateString(),
                'status' => $this->statusValue($period),
                'business_unit_name' => (string) ($period->businessUnit?->name ?? ''),
            ],
            'kpi' => [
                'total_cages_tipped' => $totalCagesTipped,
                'total_cages_out' => $totalCagesOut,
                // Guarded division: an empty period is 0, never a
                // DivisionByZeroError.
                'avg_cages_per_day' => $daysWithRecords === 0
                    ? 0.0
                    : round($totalCagesTipped / $daysWithRecords, 1),
                'peak_hour' => $peakHour,
                'peak_hour_cages' => $peakHourCages,
                // Only hours INSIDE an operating window count. Dates with
                // no computable window contribute zero — never an estimate.
                'idle_operating_hours' => (int) $windowedDates->sum('idle_operating_hours'),
                'longest_gap_hours' => $longestGapHours,
                'longest_gap_date' => $longestGapDate,
                // null, NOT 0, when no date has a computable window: 0 would
                // read as "the tippler never ran".
                'avg_tippler_duration_hours' => $windowedDates->isEmpty()
                    ? null
                    : round($windowedDates->sum('operating_hours') / $windowedDates->count(), 1),
                'days_with_records' => $daysWithRecords,
                'days_without_valid_window' => $daysWithRecords - $windowedDates->count(),
            ],
            'hourly' => $this->hourlyOf($hourlyCages, $dates),
            'daily' => $this->dailyOf($dates),
            'queue' => [
                // cages_remain is an HOURLY SNAPSHOT (the station's cage
                // fleet minus what was tipped in that hour), not a backlog
                // that accumulates — so it is reported as min/average and
                // NEVER summed.
                'min_remaining' => $details->isEmpty() ? null : (int) $details->min('cages_remain'),
                'avg_remaining' => $details->isEmpty()
                    ? null
                    : round($details->sum('cages_remain') / $details->count(), 1),
            ],
            'total' => [
                'cages_tipped' => $totalCagesTipped,
                'cages_out' => $totalCagesOut,
                'days' => $daysWithRecords,
            ],
        ];
    }

    /**
     * business_logic step 18 — one exported line per TIPPING HOUR
     * (cages_tipped_times row), with the record's context columns (date /
     * cages track number / tippler start / tippler stop / cages out /
     * status / note) repeated on every line so the file can be pivoted
     * directly in a spreadsheet. Same convention as the 18 station exports
     * (commit 8611974), scoped to a period instead of an ad-hoc filter.
     *
     * The 50.000 ceiling counts HOURLY ROWS, not header records.
     *
     * @throws ValidationException 422 VALIDATION_ERROR (unsupported format)
     * @throws ExportFailedException 422 EXPORT_FAILED
     */
    public function export(Period|string $period, string $format = 'csv', ?string $requestedBusinessUnitId = null): StreamedResponse
    {
        // DISERAGAMKAN 2026-09-25 — keempat service laporan kini menerima
        // mill yang berlaku dan memvalidasinya di lapis service. Sebelumnya
        // service ini tidak memanggil resolveBusinessUnit() di jalur ekspor
        // sama sekali, sehingga ekspor Admin lolos tanpa pernah memeriksa
        // "mill sudah dipilih" — kebalikan dari BoilerRoom, yang justru
        // menolak Admin karena pemanggilnya lupa meneruskan argumennya.
        // Ketidakseragaman itulah yang melahirkan kedua cacat sekaligus.
        $this->resolveBusinessUnit($requestedBusinessUnitId);
        $this->guardAccess();

        if (! in_array($format, self::SUPPORTED_FORMATS, true)) {
            throw ValidationException::withMessages([
                'format' => ['Format ekspor harus csv atau excel.'],
            ]);
        }

        $period = $this->resolvePeriod($period);

        $recordQuery = $this->recordQueryFor($period);

        $detailRowCount = CagesTippedTime::query()
            ->whereIn('cages_track_record_id', (clone $recordQuery)->select('cages_track_records.id'))
            ->count();

        if ($detailRowCount > self::EXPORT_ROW_LIMIT) {
            throw new ExportFailedException;
        }

        try {
            $query = (clone $recordQuery)
                ->with([
                    'cagesTippedTimes' => fn ($detailQuery) => $detailQuery->orderBy('tipped_hour'),
                ])
                ->orderBy('cages_track_records.date')
                ->orderBy('cages_track_records.id');

            [$contentType, $filename] = $this->fileMetaFor($format, $period);

            return response()->streamDownload(function () use ($query) {
                $handle = fopen('php://output', 'w');

                // Explicit $separator/$enclosure/$escape — PHP 8.4 deprecates
                // relying on fputcsv()'s default $escape.
                fputcsv($handle, [
                    'Tanggal',
                    'Nomor Cages Track',
                    'Tippler Mulai',
                    'Tippler Berhenti',
                    'Jam',
                    'Nomor Lori Diperiksa',
                    'Lori Ditumpahkan',
                    'Lori Tersisa',
                    'Lori Keluar (record)',
                    'Status',
                    'Catatan',
                ], ',', '"', '\\');

                $query->chunk(200, function ($records) use ($handle) {
                    foreach ($records as $record) {
                        /** @var CagesTrackRecord $record */
                        $context = [
                            optional($record->date)->toDateString(),
                            $record->cages_track_number,
                            optional($record->tippler_start_time)->format('Y-m-d H:i'),
                            optional($record->tippler_stop_time)->format('Y-m-d H:i'),
                        ];

                        $tail = [
                            // Header grain: repeated on every line, exactly
                            // like the other context columns. It is NOT a
                            // per-hour figure and must never be read as one.
                            $record->cages_out,
                            $record->status instanceof \BackedEnum ? $record->status->value : $record->status,
                            $record->note,
                        ];

                        foreach ($record->cagesTippedTimes as $detail) {
                            /** @var CagesTippedTime $detail */
                            fputcsv($handle, array_merge($context, [
                                $this->hourLabel((int) $detail->tipped_hour),
                                $detail->checked_cage_numbers,
                                $detail->total_cages,
                                $detail->cages_remain,
                            ], $tail), ',', '"', '\\');
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
            throw new ExportFailedException;
        }
    }

    // ------------------------------------------------------------------
    // Aggregation — ALL OF IT IN PHP, none of it in SQL
    //
    // Deliberate, and the reason is decisive: SQL aggregate behaviour over
    // nullable columns and over timestamp differences is NOT the same in
    // SQLite (used by the test suite) and PostgreSQL (production), and the
    // null-is-not-zero rules above are exactly what gets lost in that
    // difference. The row set is bounded by one reporting period; the
    // export path — the only unbounded one — streams in chunks instead.
    // ------------------------------------------------------------------

    /**
     * Every Cages & Tracks record inside the period, flattened to plain
     * objects with their hourly detail rows attached.
     *
     * ONE DATE CAN HAVE SEVERAL RECORDS (one per cages_track_number / per
     * station); the per-date aggregation below merges them.
     *
     * @return Collection<int, object>
     */
    protected function recordsFor(Period $period): Collection
    {
        return $this->recordQueryFor($period)
            ->with(['cagesTippedTimes' => fn ($query) => $query->orderBy('tipped_hour')])
            ->orderBy('cages_track_records.date')
            ->orderBy('cages_track_records.cages_track_number')
            ->get()
            ->map(function (CagesTrackRecord $record) {
                $durationMinutes = $this->windowMinutesOf(
                    $record->tippler_start_time,
                    $record->tippler_stop_time,
                );

                return (object) [
                    'date' => $this->dateStringOf($record->date),
                    'cages_track_number' => (string) ($record->cages_track_number ?? ''),
                    // HEADER grain — summed by iterating records only.
                    'cages_out' => (int) ($record->cages_out ?? 0),
                    'duration_minutes' => $durationMinutes,
                    // Kept so the DATE can anchor its hour ordering at the
                    // moment the window opened — see anchorHourOf().
                    'window_start_at' => $durationMinutes === null
                        ? null
                        : $record->tippler_start_time,
                    'operating_hour_set' => $durationMinutes === null
                        ? []
                        : $this->operatingHourSetOf($record->tippler_start_time, $durationMinutes),
                    'details' => $record->cagesTippedTimes
                        ->map(fn (CagesTippedTime $detail) => (object) [
                            'hour' => (int) $detail->tipped_hour,
                            'total_cages' => (int) ($detail->total_cages ?? 0),
                            // NOT NULL in the schema — no per-row null branch.
                            'cages_remain' => (int) $detail->cages_remain,
                        ])
                        ->values(),
                ];
            })
            ->values();
    }

    /**
     * One aggregate per DATE that has at least one record, ascending.
     *
     * A date whose records carry no hourly detail row at all still gets an
     * entry, with cages_tipped = 0 — dropping it would make the daily
     * average look better than reality. A date with NO record at all gets
     * no entry: a padded zero row would read as "we measured nothing" when
     * in fact the mill did not run.
     *
     * FAIL CLOSED on the operating window: a date is "windowed" only if
     * EVERY record on it has a computable window. One unusable record
     * disqualifies the whole date from duration AND from idle hours,
     * because a partial figure would silently lose part of the day.
     *
     * @param  Collection<int, object>  $records
     * @return Collection<int, object>
     */
    protected function datesOf(Collection $records): Collection
    {
        return $records
            ->groupBy('date')
            ->sortKeys()
            ->map(function (Collection $group, string $date) {
                $details = $group->flatMap(fn ($record) => $record->details)->values();

                $windowComputable = $group->every(fn ($record) => $record->duration_minutes !== null);

                // UNION + DEDUP of every record's operating hours on this
                // date, so two cages tracks running different shifts give
                // one combined window.
                $operatingHourSet = [];

                if ($windowComputable) {
                    foreach ($group as $record) {
                        foreach ($record->operating_hour_set as $hour) {
                            $operatingHourSet[$hour] = true;
                        }
                    }
                    ksort($operatingHourSet);
                }

                $operatingHourSet = array_keys($operatingHourSet);

                // UNION + DEDUP of the tipping hours across every record on
                // this date, BEFORE any gap is measured.
                $tippedHours = $details
                    ->map(fn ($detail) => $detail->hour)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();

                return (object) [
                    'date' => $date,
                    // Every tipping figure comes from the hourly rows —
                    // never from cages_track_records.cages_tipped.
                    'cages_tipped' => (int) $details->sum('total_cages'),
                    'cages_out' => (int) $group->sum('cages_out'),
                    'window_computable' => $windowComputable,
                    'operating_hours' => $windowComputable
                        ? round($group->sum('duration_minutes') / 60, 1)
                        : null,
                    'operating_hour_set' => $operatingHourSet,
                    // Only empty hours INSIDE the window. Counting all 24
                    // would make a one-shift mill look permanently idle for
                    // 16 hours — true, and deeply misleading.
                    'idle_operating_hours' => $windowComputable
                        ? count(array_diff($operatingHourSet, $tippedHours))
                        : null,
                    'longest_gap_hours' => $this->longestGapOf(
                        $tippedHours,
                        $windowComputable ? $this->anchorHourOf($group) : null,
                    ),
                    'min_remaining' => $details->isEmpty() ? null : (int) $details->min('cages_remain'),
                    'details' => $details,
                ];
            })
            ->values();
    }

    /**
     * Every hourly detail row in the period, flattened — the queue figures
     * are computed over this, and only over this.
     *
     * @param  Collection<int, object>  $records
     * @return Collection<int, object>
     */
    protected function detailsOf(Collection $records): Collection
    {
        return $records->flatMap(fn ($record) => $record->details)->values();
    }

    /**
     * cages tipped per hour-of-day, 0..23, over the whole period.
     *
     * @param  Collection<int, object>  $records
     * @return array<int, int>
     */
    protected function hourlyCagesOf(Collection $records): array
    {
        $hourly = array_fill(0, 24, 0);

        foreach ($records as $record) {
            foreach ($record->details as $detail) {
                if ($detail->hour < 0 || $detail->hour > 23) {
                    continue;
                }

                $hourly[$detail->hour] += $detail->total_cages;
            }
        }

        return $hourly;
    }

    /**
     * ALWAYS 24 entries, hour 0..23 in order, even when some or all of them
     * are zero — so the chart keeps the same shape from period to period
     * and a quiet hour is visibly quiet rather than absent.
     *
     * within_operating_window is true when the hour belongs to the
     * operating-hour set of AT LEAST ONE date in the period.
     *
     * @param  array<int, int>  $hourlyCages
     * @param  Collection<int, object>  $dates
     * @return list<array{hour: int, cages: int, within_operating_window: bool}>
     */
    protected function hourlyOf(array $hourlyCages, Collection $dates): array
    {
        $operating = [];

        foreach ($dates as $row) {
            foreach ($row->operating_hour_set as $hour) {
                $operating[$hour] = true;
            }
        }

        $hourly = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $hourly[] = [
                'hour' => $hour,
                'cages' => $hourlyCages[$hour],
                'within_operating_window' => isset($operating[$hour]),
            ];
        }

        return $hourly;
    }

    /**
     * The daily recap table — one row per date that has a record.
     *
     * @param  Collection<int, object>  $dates
     * @return list<array>
     */
    protected function dailyOf(Collection $dates): array
    {
        return $dates
            ->map(fn ($row) => [
                'date' => $row->date,
                'cages_tipped' => $row->cages_tipped,
                'cages_out' => $row->cages_out,
                'operating_hours' => $row->operating_hours,
                'idle_operating_hours' => $row->idle_operating_hours,
                'longest_gap_hours' => $row->longest_gap_hours,
                'min_remaining' => $row->min_remaining,
            ])
            ->values()
            ->all();
    }

    /**
     * Peak tipping hour. Ties go to the SMALLEST hour so the answer is
     * deterministic; an empty period has no peak at all (null), with
     * peak_hour_cages 0 rather than a fake hour 0.
     *
     * @param  array<int, int>  $hourlyCages
     * @return array{0: int|null, 1: int}
     */
    protected function peakOf(array $hourlyCages, int $totalCagesTipped): array
    {
        if ($totalCagesTipped === 0) {
            return [null, 0];
        }

        $peakHour = null;
        $peakCages = 0;

        foreach ($hourlyCages as $hour => $cages) {
            // Strict > keeps the FIRST (smallest) hour on a tie.
            if ($cages > $peakCages) {
                $peakCages = $cages;
                $peakHour = $hour;
            }
        }

        return [$peakHour, $peakCages];
    }

    /**
     * Largest gap between consecutive tipping hours WITHIN ONE DATE.
     *
     * Fewer than 2 distinct hours -> null, not 0: with a single tipping
     * hour there is no gap to measure at all, and 0 would claim there was
     * none.
     *
     * @param  list<int>  $hours  ascending, already deduplicated
     */
    protected function longestGapOf(array $hours, ?int $anchorHour = null): ?int
    {
        if (count($hours) < 2) {
            return null;
        }

        $hours = $this->hoursInWindowOrder($hours, $anchorHour);

        $longest = 0;

        for ($i = 1; $i < count($hours); $i++) {
            $longest = max($longest, $hours[$i] - $hours[$i - 1]);
        }

        return $longest;
    }

    /**
     * The hour at which a DATE's operating window opens — the hour
     * component of the EARLIEST tippler_start_time among that date's
     * records. Only meaningful when the date's window is computable; the
     * caller passes null otherwise.
     *
     * @param  Collection<int, object>  $group  every record on one date
     */
    protected function anchorHourOf(Collection $group): ?int
    {
        $starts = $group
            ->map(fn ($record) => $record->window_start_at)
            ->filter(fn ($start) => $start instanceof DateTimeInterface)
            ->sortBy(fn (DateTimeInterface $start) => $start->getTimestamp())
            ->values();

        if ($starts->isEmpty()) {
            return null;
        }

        return (int) Carbon::instance($starts->first())->format('G');
    }

    /**
     * Re-express tipping hours as ELAPSED HOURS SINCE THE WINDOW OPENED,
     * so that consecutive differences are real elapsed time.
     *
     * WHY THIS EXISTS. Ordering the bare 0..23 integers numerically is
     * correct for a day shift and WRONG for a night one. A tippler running
     * 22:00 -> 04:00 without a single pause records hours 22, 23, 0, 1;
     * sorted numerically that is [0, 1, 22, 23], whose largest step is 21 —
     * so a mill that never stopped was reported as having idled 21 hours,
     * on the screen's headline KPI. Anchored at the window start (22) the
     * same hours become offsets [0, 1, 2, 3] and the gap is 1, which is
     * what actually happened.
     *
     * The offset of an hour is (hour - anchor + 24) % 24, so a consecutive
     * difference in the ordered list equals (h2 - h1 + 24) % 24 — elapsed
     * clock hours. For a day shift every offset keeps the numeric order,
     * so those results are unchanged.
     *
     * A date whose window is NOT computable has no anchor to speak of, and
     * guessing one would invent a shift boundary that was never recorded.
     * Those dates keep the plain numeric order.
     *
     * @param  list<int>  $hours  ascending, deduplicated
     * @return list<int> offsets from the anchor, ascending
     */
    protected function hoursInWindowOrder(array $hours, ?int $anchorHour): array
    {
        if ($anchorHour === null) {
            return $hours;
        }

        $offsets = array_map(
            static fn (int $hour): int => ($hour - $anchorHour + 24) % 24,
            $hours,
        );

        sort($offsets);

        return $offsets;
    }

    /**
     * Period-wide longest gap + the date it happened on.
     *
     * NEVER across dates — an overnight pause is not an operational gap.
     * Ties go to the EARLIEST date ($dates is ascending and the comparison
     * is strict), so the answer is deterministic. No candidate at all ->
     * [null, null], not [0, something].
     *
     * @param  Collection<int, object>  $dates
     * @return array{0: int|null, 1: string|null}
     */
    protected function longestGapAcross(Collection $dates): array
    {
        $longest = null;
        $longestDate = null;

        foreach ($dates as $row) {
            if ($row->longest_gap_hours === null) {
                continue;
            }

            if ($longest === null || $row->longest_gap_hours > $longest) {
                $longest = $row->longest_gap_hours;
                $longestDate = $row->date;
            }
        }

        return [$longest, $longestDate];
    }

    // ------------------------------------------------------------------
    // The operating window — the two rules that must never be mixed up
    // ------------------------------------------------------------------

    /**
     * Window length in MINUTES, from the difference of the two FULL
     * TIMESTAMPS — never from their hour components. Both columns are
     * datetimes filled through datetime-local inputs, so a night shift
     * already carries the next date on its stop and the difference is
     * naturally positive without any modular arithmetic.
     *
     * The window is declared NOT COMPUTABLE (null) when:
     *   - tippler_stop_time is NULL (legacy / imported rows —
     *     CagesTrackRecordService::validate() requires it today, so this is
     *     defensive rather than a normal path), or
     *   - stop <= start. The validator carries no `after:` rule, so this
     *     really can reach the database. It is bad data, not a negative
     *     duration: a negative number must NEVER be emitted.
     *
     * diffInMinutes() is called with an EXPLICIT $absolute = false and in
     * the start->stop direction. Carbon 3 returns a signed difference where
     * Carbon 2 returned an absolute one, so both the direction and the flag
     * are written out rather than relying on a default that changed.
     */
    protected function windowMinutesOf(mixed $start, mixed $stop): ?int
    {
        if (! $start instanceof DateTimeInterface || ! $stop instanceof DateTimeInterface) {
            return null;
        }

        $minutes = (int) round(
            Carbon::instance($start)->diffInMinutes(Carbon::instance($stop), false)
        );

        return $minutes > 0 ? $minutes : null;
    }

    /**
     * THE SET OF OPERATING HOURS — the ONLY place modular arithmetic is
     * correct here.
     *
     * tipped_hour is a bare integer 0..23 with no date attached, so the set
     * is walked FORWARD from the start hour, ceil(minutes / 60) steps,
     * modulo 24, capped at 24 distinct hours (a window longer than a day
     * covers every hour, and covers each of them once).
     *
     * 22:00 -> 04:00 gives {22,23,0,1,2,3}. Using this arithmetic for the
     * DURATION instead would give -18 hours.
     *
     * @return list<int> ascending, deduplicated
     */
    protected function operatingHourSetOf(mixed $start, int $durationMinutes): array
    {
        if (! $start instanceof DateTimeInterface) {
            return [];
        }

        $steps = max(1, min(24, (int) ceil($durationMinutes / 60)));
        $firstHour = (int) Carbon::instance($start)->format('G');

        $hours = [];

        for ($step = 0; $step < $steps; $step++) {
            $hours[($firstHour + $step) % 24] = true;
        }

        ksort($hours);

        return array_keys($hours);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Base query over the period's Cages & Tracks records (header rows).
     *
     * Scoped through `stations` to the PERIOD'S business unit and to the
     * cages-track station type, and bounded INCLUSIVELY on
     * cages_track_records.date — the event date, not created_at and not the
     * sync time.
     */
    protected function recordQueryFor(Period $period): Builder
    {
        return CagesTrackRecord::query()
            ->join('stations', 'stations.id', '=', 'cages_track_records.station_id')
            ->where('stations.business_unit_id', $period->business_unit_id)
            ->where('stations.type', StationTypeEnum::CagesTrack->value)
            ->whereDate('cages_track_records.date', '>=', $period->start_date->toDateString())
            ->whereDate('cages_track_records.date', '<=', $period->end_date->toDateString())
            ->select('cages_track_records.*');
    }

    /**
     * Session + role gate shared by every entry point.
     *
     * This gate sits two layers deeper than the route middleware on
     * purpose: clearing the middleware must never be enough by itself.
     * That is why widening a role here and widening routes/api.php are
     * always one change, never two — the lesson from screen-129/135.
     *
     * 2026-09-24 (screen-136) — Operator admitted for the mobile Cages &
     * Tracks report. Admitting it HERE is only half the job: see
     * resolveBusinessUnit(), where Operator also had to be added to the
     * mill-bound branch so it cannot pass its own business_unit_id.
     * Operator is still refused by businessUnitOptions() (Admin-only) and
     * by authorizePeriod() for another mill's period — both unchanged.
     *
     * @return string the caller's role
     *
     * @throws AuthenticationException 401 UNAUTHENTICATED
     * @throws AuthorizationException 403 FORBIDDEN
     */
    protected function guardAccess(): string
    {
        $user = auth()->user();

        if ($user === null) {
            throw new AuthenticationException;
        }

        $role = $this->roleOf($user);

        if (! in_array($role, [
            UserRole::Supervisor->value,
            UserRole::MillManagement->value,
            UserRole::Admin->value,
            UserRole::Operator->value,
        ], true)) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk aksi ini.');
        }

        return $role;
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
     * pasangan (periode, cages-track). Karena itu `status` adalah status
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
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return substr((string) $value, 0, 10);
    }

    /** "09.00" — the same hour notation the screen uses. */
    protected function hourLabel(int $hour): string
    {
        return sprintf('%02d.00', $hour);
    }

    protected function roleOf(object $user): string
    {
        return $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
    }

    /**
     * Same Content-Type/filename convention as every other export in this
     * codebase — no XLSX writer package is installed, so format=excel
     * serves a CSV body under the xlsx mimetype/extension.
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
                "laporan-cages-track_{$slug}_{$timestamp}.xlsx",
            ];
        }

        return [
            'text/csv',
            "laporan-cages-track_{$slug}_{$timestamp}.csv",
        ];
    }
}
