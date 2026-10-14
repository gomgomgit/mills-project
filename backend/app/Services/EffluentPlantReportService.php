<?php

namespace App\Services;

use App\Enums\PeriodStatus;
use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Models\BusinessUnit;
use App\Models\EffluentPlantDetail;
use App\Models\EffluentPlantRecord;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\ProductionLine;
use App\Models\StationType;
use App\Support\ExportValue;
use App\Support\ReportPeriodDays;
use App\Support\SheetWriter;
use DateTimeInterface;
use Generator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * EffluentPlantReportService — screen-156--laporan-effluent-plant-web /
 * usecase-162--laporan-effluent-plant-web (Laporan Periode Effluent Plant).
 *
 * Shared by the API controller (App\Http\Controllers\Api\
 * EffluentPlantReportController) and the Livewire component (App\Livewire\
 * Dashboard\LaporanEffluentPlant) — the same split the eleven station
 * reports before it use, so the web page and the API can never disagree on
 * a figure.
 *
 * READ-ONLY BY CONSTRUCTION: every public method is a SELECT, and the
 * /api/effluent-plant-reports prefix carries no POST/PUT/PATCH/DELETE.
 *
 * ------------------------------------------------------------------
 * WHAT MAKES THIS REPORT DIFFERENT FROM ITS ELEVEN SIBLINGS
 * ------------------------------------------------------------------
 *  1. FOURTEEN NUMERIC METRICS, FOURTEEN DENOMINATORS. Every one of the 14
 *     measurement columns is nullable and filled independently, so each is
 *     averaged over ONLY the rows where THAT column is non-null and carries
 *     its own `reading_count`. THREE of them (final_discharge_bod/cod/tss
 *     _mgl_lab) are LABORATORY values sampled every few days rather than
 *     instrument readings per slot, so their denominators are legitimately
 *     an order of magnitude smaller — `is_lab` is published so the screen
 *     can say so where the figure is, and so that nobody "fixes" them onto
 *     a shared denominator. ONE SHARED DENOMINATOR FOR ALL FOURTEEN IS THE
 *     PRIMARY DEFECT THIS CLASS EXISTS TO PREVENT: it would deflate every
 *     rarely-filled metric while still producing a number nobody questions.
 *  2. A FILLED ROW IS DECIDED OVER NINETEEN COLUMNS, NOT FOURTEEN.
 *     `filled` reuses EffluentPlantRecordService::isRowFilled() over its
 *     READING_FIELDS — the definition the input screens already enforce —
 *     so a slot that only wrote `findings` counts as recorded in coverage
 *     while adding to NO metric's denominator. That is why
 *     coverage.filled_slots can exceed every per-metric reading_count, and
 *     it is not a bug.
 *  3. THREE CATEGORICAL STATUS COLUMNS are published as a DISTRIBUTION of
 *     slot counts per value, never as min/avg/max and never on a trend —
 *     lowest/average/highest have no meaning for a categorical column.
 *     Values that exist are grouped LITERALLY, then known values that never
 *     occurred are filled in with 0 so a row cannot silently vanish from
 *     the distribution; a NULL slot lands in `not_recorded`, which is NEVER
 *     folded into 'off' or 'stop' — doing so would report a dead flare or a
 *     stopped pump that never happened.
 *  4. TWO FREE-TEXT COLUMNS, TWO SEPARATE LISTS. remarks_maintenance_actions
 *     answers "what was done", findings answers "what was seen". A slot
 *     filling both appears in BOTH lists, and that is correct: merging them
 *     would count that slot twice inside one list. Neither is ever averaged
 *     or charted.
 *  5. THERE IS NO DOWNTIME BLOCK. effluent_plant_details has no downtime
 *     column at all; the absence is deliberate, stated in the payload
 *     (`_note`) and on the screen, so it does not read as a section that
 *     failed to load.
 *  6. THERE IS NO THRESHOLD FLAGGING AND NO COMPLIANCE VERDICT. Effluent
 *     Plant has NO operational-target master table (only Threshing,
 *     Pressing, Depricarping and Kernel Plant have one), and the four final
 *     discharge parameters (pH/BOD/COD/TSS) are ENVIRONMENTAL figures whose
 *     regulatory limits live OUTSIDE this application. This payload
 *     publishes the numbers and carries NOT ONE key saying compliant or
 *     non-compliant; deriving a threshold from the period's own data would
 *     produce a statistic that reads like a legal limit.
 *
 * ------------------------------------------------------------------
 * ALL AGGREGATION HAPPENS IN PHP — none of it in SQL
 * ------------------------------------------------------------------
 * One query plus an eager load, then everything in PHP: no AVG/MIN/MAX/SUM,
 * no selectRaw, no DB::raw, no driver-specific date function. SQL aggregate
 * behaviour over NULLABLE columns is NOT identical between SQLite (the test
 * suite) and PostgreSQL (production), and the separate-denominator and
 * null-is-not-zero rules above are exactly what gets lost in that
 * difference. The risk is larger here than anywhere else in the codebase —
 * fourteen nullable columns, three of them almost always NULL.
 *
 * ------------------------------------------------------------------
 * ACCESS: OPERATOR IS ADMITTED SINCE screen-157 (2026-10-13)
 * ------------------------------------------------------------------
 * Operator was refused 403 on ALL FOUR endpoints until the mobile twin
 * existed: screen-156 (web) landed first, and admitting a role before its
 * caller exists is access nobody can review. That delay followed the
 * Weighbridge precedent (screen-143 first, Operator added when screen-144
 * was built) and has now ENDED — screen-157--laporan-effluent-plant-mobile
 * is the legitimate Operator caller, and the widening landed WITH it rather
 * than after it.
 *
 * THE WIDENING TOUCHED THREE PLACES AT ONCE, and that is the whole point:
 * the role list in routes/api.php, the role list in guardAccess(), AND the
 * MILL-BOUND branch of resolveBusinessUnit(). Widening the first two alone
 * would drop Operator into the Admin branch, where a client-supplied
 * business_unit_id IS honoured — an Operator could then read any mill's
 * report. That is a cross-mill leak, not a display defect.
 *
 * WHAT STAYED CLOSED: businessUnitOptions() still refuses Operator,
 * Supervisor and Mill Management with 403 (Admin only, raised HERE inside
 * the service rather than by the route middleware, precisely so widening the
 * middleware cannot loosen it), and the WEB route /reports/effluent-plant
 * was NOT widened — widening API access must not widen the web screen.
 *
 * ------------------------------------------------------------------
 * CROSS-MILL SECURITY IS CLOSED AT THREE DIFFERENT POINTS, on purpose
 * ------------------------------------------------------------------
 *   1. resolveBusinessUnit() IGNORES the client's business_unit_id for
 *      Operator / Supervisor / Mill Management — not validated, not
 *      compared, discarded. Probing another mill's id returns 200 with the CALLER'S
 *      OWN data, deliberately not a 403: a 403 would confirm the other mill
 *      exists, and there is no access attempt to refuse because the
 *      parameter is never used for those roles.
 *   2. resolveProductionLine() REFUSES a line belonging to another mill
 *      with 403, and a MISSING line with 422 — both BEFORE a single
 *      effluent_plant_records query runs. Same shape as
 *      GradingReportService: the WEB screen calls
 *      resolveProductionLineOrNull() instead, because "no line chosen yet"
 *      is the normal opening state there and must render a prompt.
 *   3. authorizePeriod() REFUSES a period belonging to another mill with
 *      403, AFTER the line guard, so a caller who may not be here never
 *      learns which period ids exist.
 *
 * A mill-bound account whose users.business_unit_id is NULL FAILS CLOSED
 * with 422 and the whole-mill list is never even read — see
 * allBusinessUnits(), which is public and trivial precisely so a spy can
 * prove it was never called.
 */
class EffluentPlantReportService
{
    /**
     * Export row ceiling, counted in EXPORTED LINES (= effluent_plant_details
     * rows), not header records: one daily record carries up to 24 time-slot
     * rows, so counting headers would sail 24x past the real limit. Same trap
     * that was fixed for the 18 station exports in commit 8611974.
     *
     * Read through `static::` below so a unit test can lower it in a
     * subclass instead of seeding fifty thousand rows.
     */
    public const EXPORT_ROW_LIMIT = 50000;

    /**
     * Jenis stasiun yang dilaporkan layar ini — kunci baris `period_stations`
     * yang statusnya dipakai di seluruh payload layar ini.
     *
     * BERTANDA HUBUNG ('effluent-plant'), karena itulah yang disimpan
     * StationTypeEnum::EffluentPlant->value dan tabel master `station_types`.
     * Kunci snake_case akan ter-compile, tampak benar, dan tidak pernah cocok
     * dengan satu baris master pun — daftar periodenya lalu kosong untuk
     * setiap mill tanpa satu galat.
     */
    protected const STATION_TYPE = StationTypeEnum::EffluentPlant->value;

    /** Export formats this report understands. Anything else is 422. */
    public const SUPPORTED_FORMATS = ['csv', 'excel'];

    /**
     * THE FOURTEEN NUMERIC MEASUREMENT COLUMNS, in the order
     * business_logic step 12 lists them — which is also the order the six
     * layout blocks on the screen read them in (Kolam Anaerobik 1, Kolam
     * Anaerobik 2, Cooling Pond, Aliran, Buangan Akhir, Bahan Kimia).
     *
     * Every one is nullable, and every one is averaged over ITS OWN
     * non-null rows — see metricsOf(). The three status columns and the two
     * free-text columns are deliberately absent; see STATUS_COLUMNS and
     * FREE_TEXT_COLUMNS.
     */
    public const NUMERIC_METRICS = [
        'anaerobic_pond_1_ph',
        'anaerobic_pond_1_temp_c',
        'anaerobic_pond_2_ph',
        'anaerobic_pond_2_temp_c',
        'cooling_pond_ph',
        'cooling_pond_temp_c',
        'biogas_flow_rate_m3h',
        'raw_pome_feed_rate_m3h',
        'effluent_discharge_flow_rate_m3h',
        'final_discharge_ph',
        'final_discharge_bod_mgl_lab',
        'final_discharge_cod_mgl_lab',
        'final_discharge_tss_mgl_lab',
        'chemical_consumed_kgl',
    ];

    /**
     * LABORATORY metrics — sampled every few days from a lab result, not
     * read off an instrument once per slot. A SMALL DENOMINATOR ON THESE
     * THREE IS NORMAL, NOT MISSING DATA, and `is_lab` exists so the screen
     * can say that where the figure is rather than in a footnote.
     */
    public const LAB_METRICS = [
        'final_discharge_bod_mgl_lab',
        'final_discharge_cod_mgl_lab',
        'final_discharge_tss_mgl_lab',
    ];

    /**
     * THE THREE CATEGORICAL STATUS COLUMNS and their KNOWN value sets.
     *
     * The sets really are closed in the database: all three columns are
     * `$table->enum(...)` (migration 2026_08_31_000011), which on
     * PostgreSQL is a varchar WITH a CHECK constraint, and on SQLite a
     * CHECK constraint too.
     *
     * They are listed here anyway, and statusDistributionOf() still groups
     * the values that are actually present LITERALLY before filling the
     * missing known ones with 0: literal grouping means a value added by a
     * later migration cannot silently disappear from the report, and the
     * zero-fill means a known value that never occurred still renders as a
     * zero row rather than as a missing one.
     *
     * NONE of them ever appears in `metrics` or in `daily_trend`.
     */
    public const STATUS_COLUMNS = [
        'biogas_flare_status' => ['on', 'off', 'fault'],
        'dosing_pump_1_status' => ['run', 'stop'],
        'sludge_dewatering_status' => ['run', 'stop'],
    ];

    /**
     * NEVER AGGREGATED, NEVER MERGED. The two free-text columns are
     * published as two SEPARATE lists of {date, time_slot, text}; a slot
     * filling both appears in both, which is correct — they answer
     * different questions.
     */
    public const FREE_TEXT_COLUMNS = [
        'remarks_maintenance_actions',
        'findings',
    ];

    /**
     * THE ONLY TWO DAILY TRENDS. The three laboratory values are
     * deliberately NOT charted daily: with denominators that small the line
     * would have more holes than data, and a segment joining two distant
     * points implies a measurement nobody made.
     */
    public const TREND_METRICS = [
        'raw_pome_feed_rate_m3h',
        'final_discharge_ph',
    ];

    /**
     * Export column headers — context columns first, repeated verbatim on
     * every line, then the time slot, then ALL NINETEEN columns in
     * EffluentPlantRecordService::READING_FIELDS order, including the three
     * status columns and the two free-text ones.
     *
     * @var array<int, string>
     */
    public const EXPORT_HEADER = [
        // Kolom konteks diulang di setiap baris, sama dengan ekspor sebelas
        // laporan periode sebelumnya (temuan audit 2026-10-04 #8b).
        'Periode',
        'Mill',
        'Production Line',
        'Tanggal',
        'Unit Effluent Plant',
        'Status',
        'Catatan',
        'Slot Waktu',
        'pH Kolam Anaerobik 1',
        'Suhu Kolam Anaerobik 1 (C)',
        'pH Kolam Anaerobik 2',
        'Suhu Kolam Anaerobik 2 (C)',
        'pH Cooling Pond',
        'Suhu Cooling Pond (C)',
        'Status Biogas Flare',
        'Laju Biogas (m3/jam)',
        'Laju POME Masuk (m3/jam)',
        'Laju Buangan Keluar (m3/jam)',
        'pH Buangan Akhir',
        'BOD Buangan Akhir (mg/L)',
        'COD Buangan Akhir (mg/L)',
        'TSS Buangan Akhir (mg/L)',
        'Status Pompa Dosing 1',
        'Konsumsi Bahan Kimia (kg/L)',
        'Status Sludge Dewatering',
        'Catatan Perawatan',
        'Temuan',
    ];

    /**
     * code => name from the `station_types` master table, memoised per
     * service instance (one request / one Livewire render).
     *
     * @var array<string, string>|null
     */
    protected ?array $stationTypeNames = null;

    protected ?EffluentPlantRecordService $recordService = null;

    // ------------------------------------------------------------------
    // Access
    // ------------------------------------------------------------------

    /**
     * business_logic step 3 — which mill the caller is allowed to look at.
     *
     * Operator / Supervisor / Mill Management: ALWAYS their own
     * business_unit_id; the `business_unit_id` argument is ignored outright,
     * so probing another mill's id is a no-op that still returns the caller's
     * own data with HTTP 200 — not validated, not compared, discarded.
     *
     * Admin: the value MUST come from the caller. Missing is 422
     * VALIDATION_ERROR with errors.business_unit_id — never a silent null
     * and never an empty result set, which would read as "this mill has no
     * data".
     *
     * A bound account whose users.business_unit_id is NULL FAILS CLOSED with
     * 422, BEFORE any repository call, so allBusinessUnits() is provably
     * never reached from this path.
     *
     * OPERATOR (admitted with screen-157) BELONGS IN THE MILL-BOUND BRANCH
     * BELOW AND NOWHERE ELSE — see the class docblock. Admitting it in
     * guardAccess() / routes/api.php alone drops it into the Admin branch,
     * where the client's business_unit_id IS honoured: an Operator could then
     * read any mill's report. The line below is what makes admitting Operator
     * in guardAccess() safe.
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
            // Operator belongs HERE and nowhere else: the branch below treats
            // whatever reaches it as Admin and HONOURS the client's
            // business_unit_id. This line is what makes admitting Operator in
            // guardAccess() / routes/api.php safe — without it, widening the
            // role lists is a cross-mill leak, not a display defect.
            || $role === UserRole::Operator->value) {
            // Client-supplied business_unit_id is deliberately DISCARDED.
            $businessUnitId = (string) (auth()->user()->business_unit_id ?? '');

            if ($businessUnitId === '') {
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
     * business_logic step 4 — mill picker options, ADMIN ONLY.
     *
     * Operator, Supervisor and Mill Management are each bound to a single
     * mill and have no picker at all, so asking for this list is a 403
     * rather than a filtered list of one. ENFORCED HERE, INSIDE THE SERVICE,
     * not by the route middleware — which is why the screen-157 widening of
     * that middleware did NOT loosen it.
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
     * "fail closed" rule for a bound account with no business_unit_id is
     * only meaningful if it can be PROVEN that the all-mills list was never
     * built, and a spy that records zero calls to this method is that
     * proof.
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
     * Pemilih Production Line — opsi line DI DALAM mill yang berlaku.
     *
     * Berlaku untuk SEMUA peran, tidak seperti businessUnitOptions() yang
     * khusus Admin: production line BUKAN ikatan akun (tidak ada
     * `users.production_line_id`, dan tidak boleh ada) melainkan KONTEKS
     * YANG DIPILIH. Supervisor pun memilih line, karena satu mill di
     * lapangan punya belasan production line dengan jenis stasiun yang sama
     * berulang di tiap line.
     *
     * Daftar ini SELALU dibatasi mill yang berlaku, sehingga line mill lain
     * tidak pernah menjadi opsi — separuh pertama dari jaminan "line mill
     * lain ditolak"; separuhnya lagi ada di resolveProductionLine(), yang
     * menutup jalur properti/query string.
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
     * business_logic step 5 — the production line this report is actually
     * read on. 422 when absent, 403 when it belongs to another mill, and in
     * BOTH cases ZERO effluent_plant_records queries have run.
     *
     * MEMILIH LINE WAJIB di layar laporan — berbeda dari Data Browser, yang
     * punya opsi "Semua Line". Alasannya menentukan: laporan menghasilkan
     * ANGKA GABUNGAN, dan sebuah total yang mencampur belasan line bukan
     * angka yang bisa ditindaklanjuti siapa pun. TIDAK ADA jalur mundur ke
     * "seluruh line mill": melebarkan cakupan sebuah laporan diam-diam
     * karena satu parameter terlupa adalah yang terburuk dari dua dunia —
     * ia menjawab 200 dengan angka yang tidak diminta siapa pun.
     *
     * @throws ValidationException 422 VALIDATION_ERROR
     * @throws AuthorizationException 403 FORBIDDEN
     */
    public function resolveProductionLine(string $businessUnitId, ?string $requestedProductionLineId): string
    {
        if ($requestedProductionLineId === null || $requestedProductionLineId === '') {
            throw ValidationException::withMessages([
                'production_line_id' => ['Production Line wajib dipilih untuk menampilkan laporan.'],
            ]);
        }

        $belongsToMill = ProductionLine::query()
            ->whereKey($requestedProductionLineId)
            ->where('business_unit_id', $businessUnitId)
            ->exists();

        if (! $belongsToMill) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk aksi ini.');
        }

        return $requestedProductionLineId;
    }

    /**
     * Null-returning variant for the WEB screen, where "no line chosen yet"
     * is the normal opening state and must render a prompt rather than an
     * error page. The API path keeps resolveProductionLine(), which throws,
     * so a request naming another mill's line is still refused there.
     */
    public function resolveProductionLineOrNull(string $businessUnitId, ?string $requestedProductionLineId): ?string
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
     * business_logic step 7 — load a period and prove the caller may read
     * it.
     *
     * 404 when the id does not exist. 403 when it belongs to another mill
     * and the caller is mill-bound. Admin passes for any mill.
     *
     * THE ROLE GUARD RUNS BEFORE THE LOOKUP, on purpose: a role that is not
     * admitted at all must not be able to learn whether a period id exists
     * by comparing a 403 against a 404.
     *
     * @throws AuthenticationException 401 UNAUTHENTICATED
     * @throws AuthorizationException 403 FORBIDDEN
     * @throws ModelNotFoundException 404 NOT_FOUND
     */
    public function authorizePeriod(string $periodId): Period
    {
        $this->guardAccess();

        /** @var Period $period */
        $period = Period::query()->with('businessUnit')->findOrFail($periodId);

        return $this->authorizePeriodModel($period);
    }

    // ------------------------------------------------------------------
    // Reads
    // ------------------------------------------------------------------

    /**
     * business_logic step 6 — the periods selectable for this mill.
     *
     * A period covers Effluent Plant when it HAS a `period_stations` row for
     * station_type 'effluent-plant'. Newest first. An empty array is a valid
     * answer — a mill with no such period yet gets [] with HTTP 200 and a UI
     * hint pointing at Kelola Periode Pelaporan, never a 404.
     *
     * STATUS NEVER FILTERS THIS LIST: closed periods are listed and remain
     * fully readable and exportable. The period lock governs WRITING data,
     * not reading a report.
     *
     * `production_line_id` is deliberately NOT a parameter: periods belong
     * to a MILL, not to a line. What a line filters is the DATA, not the
     * period list.
     *
     * @return list<array{id: string, name: string, start_date: string, end_date: string, status: string, station_type: string, station_type_label: string}>
     */
    public function listPeriods(?string $businessUnitId = null): array
    {
        $businessUnitId = $this->resolveBusinessUnit($businessUnitId);

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
     * business_logic steps 1-26 — every figure on the screen for one period
     * on one production line: the period header, recording coverage FIRST,
     * the fourteen metrics each with its own denominator and its own
     * is_lab marker, the three status distributions, the two separate
     * free-text lists, the two daily trends, the per-unit recap, the daily
     * recap, and the period totals.
     *
     * Guard order is deliberate: role (403) -> mill (422) -> production line
     * (422 / 403) -> period id (422) -> the period itself (404 / 403). A
     * caller who may not be here at all never learns which period ids exist,
     * and an incomplete request never turns into a 404/403 about a period it
     * was never entitled to ask about.
     *
     * Membership is decided by effluent_plant_records.date — the date the
     * readings belong to — INCLUSIVE on both bounds, through whereDate even
     * though the column is a DATE rather than a timestamp, so that no
     * station report spells period membership differently from another.
     *
     * @param  Period|string|null  $period  model or id (both accepted so callers
     *                                      that already authorised the period do
     *                                      not have to re-read it)
     *
     * @throws ValidationException 422 VALIDATION_ERROR
     * @throws AuthorizationException 403 FORBIDDEN
     * @throws ModelNotFoundException 404 NOT_FOUND
     */
    public function buildSummary(
        Period|string|null $period = null,
        ?string $requestedBusinessUnitId = null,
        ?string $productionLineId = null,
    ): array {
        $this->guardAccess();
        $businessUnitId = $this->resolveBusinessUnit($requestedBusinessUnitId);
        // 422 when absent, 403 when it belongs to another mill — and in
        // BOTH cases zero effluent_plant_records queries have run by this
        // point. The line guard runs BEFORE the period guard on purpose.
        $productionLineId = $this->resolveProductionLine($businessUnitId, $productionLineId);

        $period = $this->requirePeriod($period);

        // ONE query for the whole period+line, details eager-loaded.
        // Everything below is PHP.
        $records = $this->recordsFor($period, $productionLineId);
        $rows = $this->rowsOf($records);
        // "Filled rows" are the only ones that count. A slot whose 19
        // reading columns are ALL null is an untouched slot, already
        // reported as missing by coverage; counting it again would report
        // the same emptiness twice.
        $filledRows = $rows->filter(fn ($row) => $row->filled)->values();

        $dates = $this->datesOf($records);
        $units = $this->unitsOf($records);

        $daysInPeriod = ReportPeriodDays::inPeriod($period);
        // Hari ber-record, BUKAN hari ber-slot-terisi: sebuah hari yang
        // punya record tetapi seluruh barisnya kosong tetap sebuah hari yang
        // dicatat orang.
        $daysWithRecords = $dates->count();
        $filledSlots = $filledRows->count();
        $unitCount = $units->count();
        // 24 datang dari kisi slot kanonik yang dipakai layar input itu
        // sendiri (EffluentPlantRecordService::canonicalTimeSlots()), bukan
        // angka yang dikarang di sini — satu definisi, satu jawaban.
        $slotsPerUnitPerDay = count(EffluentPlantRecordService::canonicalTimeSlots());
        // PENYEBUT BERHENTI DI HARI INI untuk periode yang masih berjalan,
        // dan 0 bila periode belum mulai — hari yang belum terjadi tidak
        // mungkin tercatat. Lihat App\Support\ReportPeriodDays.
        $daysCounted = ReportPeriodDays::counted($period);
        $expectedSlots = $unitCount * $daysCounted * $slotsPerUnitPerDay;

        return [
            'period' => [
                'id' => (string) $period->id,
                'name' => (string) $period->name,
                'start_date' => $period->start_date->toDateString(),
                'end_date' => $period->end_date->toDateString(),
                'status' => $this->statusValue($period),
                'business_unit_name' => (string) ($period->businessUnit?->name ?? ''),
            ],
            'business_unit' => [
                'id' => (string) $period->business_unit_id,
                'name' => (string) ($period->businessUnit?->name ?? ''),
            ],
            'production_line' => $this->productionLineInfo($productionLineId, (string) $period->business_unit_id),
            // has_data distinguishes "there is nothing to report" from "the
            // figures happen to be zero". The screen uses it to REFUSE to
            // draw an empty chart, which would read as a measured flat line.
            'has_data' => $filledSlots > 0,
            // RECORDING COVERAGE IS PART OF THE REPORT, NOT METADATA. A
            // period filled to a fifth still produces tidy-looking averages,
            // and the reader must see that before trusting them — which is
            // why the screen renders this block ABOVE every other figure.
            'coverage' => [
                'filled_slots' => $filledSlots,
                'expected_slots' => $expectedSlots,
                // 0.0 ketika expected_slots 0 — tanpa DivisionByZeroError,
                // dan layar menampilkan '—' dengan 'belum ada slot yang
                // diharapkan' pada keadaan itu, bukan '0,0%'.
                'coverage_percent' => $expectedSlots === 0
                    ? 0.0
                    : round(100 * $filledSlots / $expectedSlots, 1),
                'effluent_plant_unit_count' => $unitCount,
                'slots_per_unit_per_day' => $slotsPerUnitPerDay,
                'days_in_period' => $daysInPeriod,
                'days_counted' => $daysCounted,
                'period_running' => ReportPeriodDays::isRunning($period),
            ],
            // THE HEART OF THIS REPORT: fourteen metrics, fourteen
            // denominators, three of them flagged is_lab.
            'metrics' => $this->metricsOf($filledRows),
            // Kategoris: sebaran jumlah slot per nilai. TIDAK PERNAH
            // min/avg/max, TIDAK PERNAH tren.
            'status_distribution' => $this->statusDistributionOf($filledRows),
            // DUA DAFTAR TERPISAH. Satu slot yang mengisi keduanya muncul di
            // kedua daftar, dan itu benar.
            'maintenance_remarks' => $this->textEntriesOf($filledRows, 'remarks_maintenance_actions'),
            'findings' => $this->textEntriesOf($filledRows, 'findings'),
            'daily_trend' => $this->dailyTrendOf($dates),
            'by_unit' => $this->byUnitOf($units),
            'daily' => $this->dailyOf($dates),
            'daily_total' => [
                // Satu definisi, satu jawaban: jumlah slot per hari dan
                // jumlah slot per unit keduanya menjumlah ke angka ini, dan
                // ke coverage.filled_slots.
                'reading_rows' => $filledSlots,
            ],
            'total' => [
                'days_with_records' => $daysWithRecords,
                'reading_rows' => $filledSlots,
            ],
            '_note' => 'TIDAK ADA kunci downtime — effluent_plant_details tidak punya satu pun kolom '
                .'downtime, dan ketiadaan itu disengaja, bukan terlewat. TIDAK ADA kunci target maupun '
                .'penanda di luar batas — Effluent Plant tidak punya tabel master target operasional. '
                .'Keempat parameter buangan akhir (final_discharge_ph/bod/cod/tss) adalah angka '
                .'KEPATUHAN LINGKUNGAN yang batas regulasinya ditetapkan di luar aplikasi: payload ini '
                .'menerbitkan angkanya tanpa satu pun kunci yang menyatakan patuh atau tidak patuh. '
                .'Ketiga kolom status TIDAK PERNAH muncul di `metrics` maupun `daily_trend`. Kedua '
                .'kolom teks bebas adalah dua daftar TERPISAH; satu slot yang mengisi keduanya muncul '
                .'di kedua daftar, dan itu benar.',
        ];
    }

    /**
     * Repo-convention alias of buildSummary(), so this service reads the
     * same way as its eleven siblings at the call sites. One implementation,
     * two names — never two implementations.
     */
    public function summary(
        Period|string|null $period = null,
        ?string $requestedBusinessUnitId = null,
        ?string $productionLineId = null,
    ): array {
        return $this->buildSummary($period, $requestedBusinessUnitId, $productionLineId);
    }

    /**
     * business_logic step 27 — ONE EXPORTED LINE PER TIME SLOT
     * (effluent_plant_details row), with the period / mill / production line
     * / date / unit / status / note context columns repeated verbatim on
     * every line so the file can be pivoted straight in a spreadsheet.
     *
     * ALL NINETEEN reading columns are emitted, the three status columns
     * and the two free-text ones included — dropping either would make the
     * export unable to stand in for the report, which is the whole point of
     * exporting it. An empty cell stays EMPTY: never 0, never '-'.
     *
     * THE GUARD, THE MILL/LINE RESOLUTION AND THE ROW-LIMIT CHECK RUN
     * EAGERLY, at call time, while the rows themselves are yielded lazily
     * from a chunked query. Making this method itself a generator would
     * defer the 403/422 until the first iteration, so a refused export would
     * look like a successful call that produced nothing.
     *
     * THE CEILING COUNTS SLOT ROWS, NOT RECORDS: one record carries up to 24
     * of them, so the limit is reached at a record count 24x smaller.
     *
     * @return Generator<int, array<int, string|float|null>>
     *
     * @throws AuthorizationException 403 FORBIDDEN
     * @throws ModelNotFoundException 404 NOT_FOUND
     * @throws ValidationException 422 VALIDATION_ERROR
     * @throws ExportFailedException 422 EXPORT_FAILED
     */
    public function buildExportRows(
        Period|string|null $period = null,
        ?string $requestedBusinessUnitId = null,
        ?string $productionLineId = null,
    ): Generator {
        $this->guardAccess();
        $businessUnitId = $this->resolveBusinessUnit($requestedBusinessUnitId);
        $productionLineId = $this->resolveProductionLine($businessUnitId, $productionLineId);

        $period = $this->requirePeriod($period);

        $recordQuery = $this->recordQueryFor($period, $productionLineId);

        // Counted over ROWS THAT WILL BE EMITTED — detail rows, not header
        // records. A COUNT(*) over a non-nullable key is not an aggregate
        // over a nullable column, so it is not the SQLite/PostgreSQL trap
        // the aggregation rule above guards against.
        $detailRowCount = EffluentPlantDetail::query()
            ->whereIn('effluent_plant_record_id', (clone $recordQuery)->select('effluent_plant_records.id'))
            ->count();

        // Strictly greater than: exactly EXPORT_ROW_LIMIT rows still export.
        // `static::`, not `self::` — the ceiling is a tunable, and a unit
        // test lowers it in a subclass to prove the check counts EMITTED
        // ROWS rather than records.
        if ($detailRowCount > static::EXPORT_ROW_LIMIT) {
            throw new ExportFailedException;
        }

        $exportContext = [
            (string) $period->name,
            (string) ($period->businessUnit?->name ?? ''),
            (string) ($this->productionLineInfo($productionLineId, (string) $period->business_unit_id)['name'] ?? ''),
        ];

        return $this->streamExportRows($recordQuery, $exportContext);
    }

    /**
     * business_logic step 27 — the streamed file around buildExportRows().
     *
     * @throws ValidationException 422 VALIDATION_ERROR (unsupported format)
     * @throws ExportFailedException 422 EXPORT_FAILED
     */
    public function export(
        Period|string|null $period = null,
        string $format = 'csv',
        ?string $requestedBusinessUnitId = null,
        ?string $productionLineId = null,
    ): StreamedResponse {
        $this->guardAccess();

        if (! in_array($format, self::SUPPORTED_FORMATS, true)) {
            throw ValidationException::withMessages([
                'format' => ['Format ekspor harus csv atau excel.'],
            ]);
        }

        $resolvedPeriod = $this->requirePeriod($period);

        // Runs the guard + the mill resolution + the line resolution + the
        // row-limit check NOW, before a single byte of the response is
        // committed — a refused export must never begin streaming.
        $rows = $this->buildExportRows($resolvedPeriod, $requestedBusinessUnitId, $productionLineId);

        try {
            [$contentType, $filename] = $this->fileMetaFor($format, $resolvedPeriod);

            return response()->streamDownload(function () use ($rows, $format) {
                // A failure WHILE writing is still EXPORT_FAILED (422), not
                // a half-written file reported as a success.
                try {
                    $handle = SheetWriter::open($format);
                    $handle->row(self::EXPORT_HEADER);

                    foreach ($rows as $row) {
                        $handle->row($row);
                    }

                    $handle->close();
                } catch (ExportFailedException $e) {
                    throw $e;
                } catch (Throwable $e) {
                    throw new ExportFailedException;
                }
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
    // NULLABLE columns is NOT the same in SQLite (the test suite) and
    // PostgreSQL (production), and the separate-denominator and
    // null-is-not-zero rules are exactly what gets lost in that difference.
    // No avg()/sum()/groupBy()/selectRaw is issued at the SQL layer anywhere
    // below; the queries only ever select raw rows, filter them, and order
    // them. The row set is bounded by one reporting period; the export path
    // — the only unbounded one — streams in chunks instead.
    // ------------------------------------------------------------------

    /**
     * Every Effluent Plant record inside the period+line, flattened to
     * plain objects with their time-slot detail rows attached, ascending by
     * time_slot.
     *
     * ONE DATE CAN HAVE SEVERAL RECORDS — one per Effluent Plant unit,
     * because effluent_plant_id is a STRING column on the HEADER, not a
     * foreign key (the same shape as boiler_room_id). The per-date
     * aggregation merges them; the per-unit aggregation splits them apart
     * again.
     *
     * @return Collection<int, object>
     */
    protected function recordsFor(Period $period, ?string $productionLineId = null): Collection
    {
        return $this->recordQueryFor($period, $productionLineId)
            // time_slot is a TIME column: ordering uses the TIME value as it
            // stands, never cast to an integer hour.
            ->with(['effluentPlantDetails' => fn ($query) => $query->orderBy('time_slot')])
            ->orderBy('effluent_plant_records.date')
            ->orderBy('effluent_plant_records.effluent_plant_id')
            ->get()
            ->map(fn (EffluentPlantRecord $record) => (object) [
                'date' => $this->dateStringOf($record->date),
                'effluent_plant_id' => (string) ($record->effluent_plant_id ?? ''),
                'status' => $this->recordStatusValue($record),
                'note' => $record->note,
                'rows' => $record->effluentPlantDetails
                    ->map(fn (EffluentPlantDetail $detail) => $this->rowOf($detail, $record))
                    ->values(),
            ])
            ->values();
    }

    /**
     * One detail row, reduced to exactly what the aggregation needs.
     *
     * `filled` REUSES EffluentPlantRecordService::isRowFilled() over its
     * READING_FIELDS — ALL NINETEEN of them, the definition the input
     * screens already enforce, and the reason both were made public.
     * Writing a second "is this row filled?" rule here, or re-listing the
     * nineteen columns in this file, is how the report and the form start
     * disagreeing about what was recorded. Note that it is NINETEEN, not the
     * fourteen in NUMERIC_METRICS: a slot that only wrote `findings` counts
     * as recorded and adds to no metric's denominator.
     */
    protected function rowOf(EffluentPlantDetail $detail, EffluentPlantRecord $record): object
    {
        $attributes = $detail->only(EffluentPlantRecordService::READING_FIELDS);

        $values = [];

        foreach (self::NUMERIC_METRICS as $metric) {
            $raw = $detail->{$metric};
            // NULL STAYS NULL. Never coerced to 0.0 — a missing reading must
            // not be able to drag an average down.
            $values[$metric] = $raw === null || $raw === '' ? null : (float) $raw;
        }

        $statuses = [];

        foreach (array_keys(self::STATUS_COLUMNS) as $column) {
            $statuses[$column] = $this->enumValueOf($detail->{$column});
        }

        $texts = [];

        foreach (self::FREE_TEXT_COLUMNS as $column) {
            $text = $detail->{$column} === null ? null : trim((string) $detail->{$column});
            // Edge whitespace only. A remark of '   ' becomes null rather
            // than a blank entry in the list.
            $texts[$column] = $text === '' ? null : $text;
        }

        return (object) [
            'date' => $this->dateStringOf($record->date),
            'effluent_plant_id' => (string) ($record->effluent_plant_id ?? ''),
            'time_slot' => (string) $detail->time_slot,
            'filled' => $this->recordService()->isRowFilled($attributes),
            'values' => $values,
            'statuses' => $statuses,
            'texts' => $texts,
        ];
    }

    /**
     * Every detail row in the period, flattened across records.
     *
     * @param  Collection<int, object>  $records
     * @return Collection<int, object>
     */
    protected function rowsOf(Collection $records): Collection
    {
        return $records->flatMap(fn ($record) => $record->rows)->values();
    }

    /**
     * FOURTEEN ENTRIES, FOURTEEN DENOMINATORS.
     *
     * A metric is averaged over ONLY the rows where that column is
     * non-null, and reports how many rows that was. There is deliberately NO
     * shared reading_count anywhere in the payload — a single shared
     * denominator would deflate every rarely-filled metric while still
     * producing a number that looks entirely reasonable (2 lab readings of
     * 300 and 400 over 100 rows would publish 7 instead of 350).
     *
     * A metric never filled in the whole period returns null/null/null with
     * reading_count 0 — NOT 0/0/0, which reads as "we measured it and it was
     * zero".
     *
     * is_lab is true for exactly the three LAB_METRICS; their small
     * denominators are normal, not missing data.
     *
     * @param  Collection<int, object>  $filledRows
     * @return array<string, array{min: float|null, avg: float|null, max: float|null, reading_count: int, is_lab: bool}>
     */
    protected function metricsOf(Collection $filledRows): array
    {
        $metrics = [];

        foreach (self::NUMERIC_METRICS as $metric) {
            $metrics[$metric] = $this->statsOf($this->valuesOf($filledRows, $metric))
                + ['is_lab' => in_array($metric, self::LAB_METRICS, true)];
        }

        return $metrics;
    }

    /**
     * One metric's own non-null values inside one bucket.
     *
     * @param  Collection<int, object>  $filledRows
     * @return array<int, float>
     */
    protected function valuesOf(Collection $filledRows, string $metric): array
    {
        return $filledRows
            ->map(fn ($row) => $row->values[$metric])
            ->filter(fn ($value) => $value !== null)
            ->values()
            ->all();
    }

    /**
     * min / avg / max / reading_count over one metric's own values.
     *
     * Empty input is null/null/null/0, never 0/0/0/0. ROUNDING HAPPENS HERE
     * AND NOWHERE ELSE, so the blade, the API and the export can never round
     * differently.
     *
     * @param  array<int, float>  $values
     * @return array{min: float|null, avg: float|null, max: float|null, reading_count: int}
     */
    protected function statsOf(array $values): array
    {
        if ($values === []) {
            return ['min' => null, 'avg' => null, 'max' => null, 'reading_count' => 0];
        }

        return [
            'min' => round(min($values), 2),
            'avg' => round(array_sum($values) / count($values), 2),
            'max' => round(max($values), 2),
            'reading_count' => count($values),
        ];
    }

    /**
     * Per-metric averages inside one bucket (a date or a unit), each with
     * ITS OWN reading_count inside that bucket.
     *
     * avg is null — never 0 — when the bucket holds no value at all for
     * that metric.
     *
     * @param  Collection<int, object>  $filledRows
     * @return array<string, array{avg: float|null, reading_count: int}>
     */
    protected function averagesOf(Collection $filledRows): array
    {
        $averages = [];

        foreach (self::NUMERIC_METRICS as $metric) {
            $values = $this->valuesOf($filledRows, $metric);

            $averages[$metric] = [
                'avg' => $values === []
                    ? null
                    : round(array_sum($values) / count($values), 2),
                'reading_count' => count($values),
            ];
        }

        return $averages;
    }

    /**
     * THE THREE STATUS DISTRIBUTIONS, and the invariant that proves each of
     * them:
     *
     *   sum(counts) + not_recorded === recorded_slots === filled rows
     *
     * Built by GROUPING THE VALUES THAT ARE ACTUALLY PRESENT first, then
     * filling in the known values that never occurred with 0 — so a value
     * added by a later migration cannot silently vanish from the report,
     * while a known value that never happened still renders as a zero row
     * rather than as a missing one.
     *
     * not_recorded counts filled slots whose status column is NULL, on its
     * OWN key. IT IS NEVER FOLDED INTO 'off' OR 'stop': that would report a
     * dead flare or a stopped pump that never happened, and on this station
     * "we did not write it down" and "it was not running" have completely
     * different consequences.
     *
     * recorded_slots is the number of FILLED SLOTS — the denominator the
     * screen uses for each value's share. expected_slots would mix untouched
     * slots into the share of a value that was recorded.
     *
     * @param  Collection<int, object>  $filledRows
     * @return array<string, array{known_values: list<string>, counts: array<string, int>, not_recorded: int, recorded_slots: int}>
     */
    protected function statusDistributionOf(Collection $filledRows): array
    {
        $distribution = [];

        foreach (self::STATUS_COLUMNS as $column => $knownValues) {
            $counts = [];
            $notRecorded = 0;

            // 1. Literal grouping of what is actually there.
            foreach ($filledRows as $row) {
                $value = $row->statuses[$column] ?? null;

                if ($value === null) {
                    $notRecorded++;

                    continue;
                }

                $counts[$value] = ($counts[$value] ?? 0) + 1;
            }

            // 2. Zero-fill the known values that never occurred, in the
            //    master order, so the distribution keeps a stable shape.
            $ordered = [];

            foreach ($knownValues as $knownValue) {
                $ordered[$knownValue] = $counts[$knownValue] ?? 0;
            }

            // 3. Anything grouped that is NOT in the known set stays — a
            //    value introduced by a migration must show up rather than
            //    disappear.
            foreach ($counts as $value => $count) {
                if (! array_key_exists($value, $ordered)) {
                    $ordered[$value] = $count;
                }
            }

            $distribution[$column] = [
                'known_values' => array_keys($ordered),
                'counts' => $ordered,
                'not_recorded' => $notRecorded,
                'recorded_slots' => $filledRows->count(),
            ];
        }

        return $distribution;
    }

    /**
     * One free-text column published as a list of {date, time_slot, text},
     * only for the slots where that column is not empty.
     *
     * CALLED TWICE, AND THE TWO RESULTS ARE NEVER FOLDED INTO A THIRD. A
     * slot that filled both columns appears in BOTH lists, which is correct:
     * merging them would count that slot twice inside one list, and the two
     * answer different questions — what was done, versus what was seen.
     * Neither is ever averaged or charted.
     *
     * @param  Collection<int, object>  $filledRows
     * @return list<array{date: string, time_slot: string, text: string}>
     */
    protected function textEntriesOf(Collection $filledRows, string $column): array
    {
        return $filledRows
            ->filter(fn ($row) => ($row->texts[$column] ?? null) !== null)
            ->map(fn ($row) => [
                'date' => $row->date,
                'time_slot' => ExportValue::time($row->time_slot) ?? (string) $row->time_slot,
                'text' => (string) $row->texts[$column],
            ])
            ->values()
            ->all();
    }

    /**
     * One aggregate per DATE that has at least one record, ascending,
     * merging every Effluent Plant unit that ran on that date.
     *
     * A date whose rows are all empty for a given metric yields null in that
     * column but STILL COUNTS as a date with records — dropping it would
     * make the period look better recorded than it was. A date with no
     * record at all gets no entry: a padded zero row would read as "we
     * measured nothing" when the mill simply did not run.
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
                $rows = $group->flatMap(fn ($record) => $record->rows)->values();
                $filled = $rows->filter(fn ($row) => $row->filled)->values();

                return (object) [
                    'date' => $date,
                    'reading_rows' => $filled->count(),
                    'averages' => $this->averagesOf($filled),
                ];
            })
            ->values();
    }

    /**
     * One aggregate per EFFLUENT PLANT UNIT (effluent_plant_id) over every
     * record in the period.
     *
     * effluent_plant_id is a STRING column on the header, so one line can
     * carry several units — the same shape as boiler_room_id. A unit that
     * has a record but not one filled reading STILL APPEARS, with
     * reading_rows 0 and null averages, and still counts towards
     * effluent_plant_unit_count so expected_slots stays correct. Dropping it
     * would hide exactly the unit that was never written down.
     *
     * @param  Collection<int, object>  $records
     * @return Collection<int, object>
     */
    protected function unitsOf(Collection $records): Collection
    {
        return $records
            ->groupBy('effluent_plant_id')
            ->sortKeys()
            ->map(function (Collection $group, string $effluentPlantId) {
                $rows = $group->flatMap(fn ($record) => $record->rows)->values();
                $filled = $rows->filter(fn ($row) => $row->filled)->values();

                return (object) [
                    'effluent_plant_id' => $effluentPlantId,
                    'reading_rows' => $filled->count(),
                    'metrics' => $this->metricsOf($filled),
                ];
            })
            ->values();
    }

    /**
     * THE TWO DAILY TRENDS — raw POME feed rate and final discharge pH, each
     * point carrying its own reading_count.
     *
     * A date whose rows never filled the charted column yields avg null, NOT
     * 0: a zero point would claim a measurement that was never made, and the
     * date still counts as a date with records.
     *
     * The three laboratory values are deliberately absent; see TREND_METRICS.
     *
     * @param  Collection<int, object>  $dates
     * @return array<string, list<array{date: string, avg: float|null, reading_count: int}>>
     */
    protected function dailyTrendOf(Collection $dates): array
    {
        $trend = [];

        foreach (self::TREND_METRICS as $metric) {
            $trend[$metric] = $dates
                ->filter(fn ($row) => $row->reading_rows > 0)
                ->map(fn ($row) => [
                    'date' => $row->date,
                    'avg' => $row->averages[$metric]['avg'],
                    'reading_count' => $row->averages[$metric]['reading_count'],
                ])
                ->values()
                ->all();
        }

        return $trend;
    }

    /**
     * The daily recap table — ONE ROW PER DATE THAT HAS AT LEAST ONE FILLED
     * ROW (business_logic step 26). A date with records but no filled row at
     * all still counts in total.days_with_records; it simply has no figures
     * to print.
     *
     * @param  Collection<int, object>  $dates
     * @return list<array{date: string, reading_rows: int, metrics_avg: array<string, array{avg: float|null, reading_count: int}>}>
     */
    protected function dailyOf(Collection $dates): array
    {
        return $dates
            ->filter(fn ($row) => $row->reading_rows > 0)
            ->map(fn ($row) => [
                'date' => $row->date,
                'reading_rows' => $row->reading_rows,
                'metrics_avg' => $row->averages,
            ])
            ->values()
            ->all();
    }

    /**
     * The per-unit recap table. `metrics` carries the SAME SHAPE as the
     * period-level `metrics`, scoped to this unit — fourteen entries, each
     * with its own denominator and its own is_lab marker.
     *
     * `reading_count` is published BESIDE `reading_rows` with the same
     * value: the two names are used interchangeably across the eleven
     * sibling reports, and publishing both keeps every caller reading the
     * key it already expects.
     *
     * @param  Collection<int, object>  $units
     * @return list<array<string, mixed>>
     */
    protected function byUnitOf(Collection $units): array
    {
        return $units
            ->map(fn ($row) => [
                'effluent_plant_id' => $row->effluent_plant_id,
                'reading_rows' => $row->reading_rows,
                'reading_count' => $row->reading_rows,
                'metrics' => $row->metrics,
            ])
            ->values()
            ->all();
    }

    // ------------------------------------------------------------------
    // Export streaming
    // ------------------------------------------------------------------

    /**
     * The lazy half of buildExportRows(): one array per time-slot row,
     * pulled in chunks so a long period never materialises as one
     * collection.
     *
     * Ordered by date, then by unit, then by time_slot — and time_slot is
     * compared as the TIME value it is, never as an integer hour.
     *
     * The three status columns are written with the SAME labels the Detail
     * screen and this station's own Data Browser export use
     * (ExportValue::option(), i.e. Display::OPTION_LABELS) rather than a
     * second mapping invented here; the two free-text columns pass through
     * verbatim. Empty cells stay empty.
     *
     * @return Generator<int, array<int, string|float|null>>
     */
    protected function streamExportRows(Builder $recordQuery, array $exportContext = ['', '', '']): Generator
    {
        $query = (clone $recordQuery)
            ->with(['effluentPlantDetails' => fn ($detailQuery) => $detailQuery->orderBy('time_slot')])
            ->orderBy('effluent_plant_records.date')
            ->orderBy('effluent_plant_records.effluent_plant_id')
            ->orderBy('effluent_plant_records.id');

        $statusColumns = array_keys(self::STATUS_COLUMNS);

        foreach ($query->lazy(200) as $record) {
            /** @var EffluentPlantRecord $record */
            $context = array_merge($exportContext, [
                optional($record->date)->toDateString(),
                $record->effluent_plant_id,
                // Label Indonesia, bukan enum mentah (temuan audit
                // 2026-10-04 #8c).
                ExportValue::status($this->recordStatusValue($record)),
                $record->note,
            ]);

            foreach ($record->effluentPlantDetails as $detail) {
                /** @var EffluentPlantDetail $detail */
                // Slot selalu HH:MM (temuan audit 2026-10-04 #8d).
                $reading = [ExportValue::time($detail->time_slot)];

                foreach (EffluentPlantRecordService::READING_FIELDS as $field) {
                    $reading[] = in_array($field, $statusColumns, true)
                        ? ExportValue::option($detail->{$field})
                        : $detail->{$field};
                }

                yield array_merge($context, $reading);
            }
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Base query over the period's Effluent Plant records (header rows).
     *
     * Scoped through `stations` to the PERIOD'S business unit and to the
     * effluent-plant station type — effluent_plant_records carries neither
     * period_id nor business_unit_id, so the join is the only way to scope
     * the MILL — and bounded INCLUSIVELY on effluent_plant_records.date, the
     * event date, never created_at and never the mobile sync time. A row
     * entered late still belongs to the period it happened in.
     *
     * whereDate, not a raw where: the column is a DATE here (unlike
     * grading_records' timestamp), but the suite runs on SQLite while
     * production is PostgreSQL, and this is the spelling the other eleven
     * reports use.
     */
    protected function recordQueryFor(Period $period, ?string $productionLineId = null): Builder
    {
        $query = EffluentPlantRecord::query()
            ->join('stations', 'stations.id', '=', 'effluent_plant_records.station_id')
            ->where('stations.business_unit_id', $period->business_unit_id)
            ->where('stations.type', StationTypeEnum::EffluentPlant->value)
            ->whereDate('effluent_plant_records.date', '>=', $period->start_date->toDateString())
            ->whereDate('effluent_plant_records.date', '<=', $period->end_date->toDateString())
            ->select('effluent_plant_records.*');

        $this->scopeToProductionLine($query, $productionLineId);

        return $query;
    }

    /**
     * Penyaringan per production line, DI KOLOM TABEL RECORD — bukan lewat
     * join ke `stations`.
     *
     * `effluent_plant_records.production_line_id` adalah kolom nyata NOT
     * NULL (migrasi 2026_09_28_000043) yang di-SNAPSHOT dari stasiun saat
     * record dibuat dan tidak pernah berubah sesudahnya. Membaca dari kolom
     * record itulah yang benar secara semantik: untuk record lama yang
     * stasiunnya sudah DIPINDAH ke line lain, kolom record menunjuk line
     * tempat data itu benar-benar dihasilkan, sedangkan
     * `stations.production_line_id` menunjuk line stasiun itu SEKARANG.
     * Menyaring lewat join ke `stations` akan menulis ulang sejarah setiap
     * kali sebuah stasiun dipindahkan.
     */
    protected function scopeToProductionLine(mixed $query, ?string $productionLineId): void
    {
        if ($productionLineId === null || $productionLineId === '') {
            return;
        }

        $query->where('effluent_plant_records.production_line_id', $productionLineId);
    }

    /**
     * business_logic steps 1-2 — session + role gate shared by every entry
     * point.
     *
     * This gate sits two layers deeper than the route middleware on purpose:
     * clearing the middleware must never be enough by itself. That is why
     * widening a role here and widening routes/api.php are always one
     * change, never two.
     *
     * OPERATOR IS ADMITTED since screen-157--laporan-effluent-plant-mobile
     * (2026-10-13) — the mobile twin that is its one legitimate caller. It
     * was deliberately absent before that, and the delay was a reviewable
     * decision rather than an oversight. Admitting the role HERE is only one
     * third of it: resolveBusinessUnit() must ALSO place Operator in the
     * MILL-BOUND branch and routes/api.php must admit it too, or this line
     * opens a cross-mill read. The one entry point that still refuses
     * Operator is businessUnitOptions(), which checks the role itself
     * precisely because this gate lets it through.
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

    /**
     * The mill check shared by authorizePeriod() and by every entry point
     * that accepts an already-loaded Period. Re-running it on a model that
     * was authorised a moment ago costs nothing and closes the gap where a
     * caller hands in a Period it never checked.
     *
     * @throws AuthorizationException 403 FORBIDDEN
     */
    protected function authorizePeriodModel(Period $period): Period
    {
        $role = $this->guardAccess();

        if ($role === UserRole::Admin->value) {
            return $period;
        }

        if ((string) $period->business_unit_id !== (string) (auth()->user()->business_unit_id ?? '')) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk aksi ini.');
        }

        return $period;
    }

    /**
     * Blok `production_line` pada respons ringkasan. null ketika tidak ada
     * line berlaku.
     *
     * DIBATASI KE MILL-NYA, dan itu bukan hiasan: tanpa klausa
     * business_unit_id, memanggil /summary dengan production_line_id milik
     * mill LAIN akan menerbitkan keberadaan serta NAMA MANUSIAWI line itu.
     * Di layar ini jalur itu sudah ditolak 403 oleh resolveProductionLine(),
     * dan scope di sini adalah lapis keduanya — ditemukan 2026-10-07 dan
     * diperbaiki identik di kesebelas report service sekaligus.
     *
     * @return array{id: string, name: string}|null
     */
    protected function productionLineInfo(?string $productionLineId, string $businessUnitId): ?array
    {
        if ($productionLineId === null || $productionLineId === '') {
            return null;
        }

        /** @var ProductionLine|null $line */
        $line = ProductionLine::query()
            ->where('business_unit_id', $businessUnitId)
            ->find($productionLineId, ['id', 'name']);

        if ($line === null) {
            return null;
        }

        return [
            'id' => (string) $line->id,
            'name' => (string) $line->name,
        ];
    }

    /**
     * A missing period_id is 422 VALIDATION_ERROR — incomplete input, not a
     * 404 for the empty string and not a silently empty report.
     *
     * @throws ValidationException 422 VALIDATION_ERROR
     * @throws ModelNotFoundException 404 NOT_FOUND
     * @throws AuthorizationException 403 FORBIDDEN
     */
    protected function requirePeriod(Period|string|null $period): Period
    {
        if ($period === null || $period === '') {
            throw ValidationException::withMessages([
                'period_id' => ['Periode Pelaporan wajib dipilih.'],
            ]);
        }

        if ($period instanceof Period) {
            $period = $period->relationLoaded('businessUnit') ? $period : $period->load('businessUnit');

            return $this->authorizePeriodModel($period);
        }

        return $this->authorizePeriod($period);
    }

    protected function recordService(): EffluentPlantRecordService
    {
        return $this->recordService ??= app(EffluentPlantRecordService::class);
    }

    /**
     * Satu opsi periode UNTUK LAYAR INI. Bentuknya sengaja DATAR, persis
     * seperti kesebelas laporan sebelumnya, karena blade membacanya apa
     * adanya — pemisahan periods/period_stations tidak merembes ke kontrak
     * API.
     *
     * Yang diminta layar ini bukan periode telanjang melainkan pasangan
     * (periode, effluent-plant). Karena itu `status` adalah status STASIUN
     * INI di periode itu (period_stations.status) — periode sendiri tidak
     * punya status lagi — dan `station_type` selalu terisi.
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
     * periode tidak punya status sendiri, karena stasiun tidak ditutup
     * serentak.
     *
     * Tanpa baris untuk jenis ini, jawabannya 'draft' — arti draft memang
     * "stasiun ini belum dipakai di periode ini". Sengaja bukan string
     * kosong: nilainya harus tetap salah satu dari draft/open/closed karena
     * blade mencocokkannya.
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

    protected function recordStatusValue(EffluentPlantRecord $record): string
    {
        return $record->status instanceof \BackedEnum
            ? $record->status->value
            : (string) $record->status;
    }

    /** Backed enums and plain strings both reduce to the stored value / null. */
    protected function enumValueOf(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $value instanceof \BackedEnum ? (string) $value->value : (string) $value;
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

    protected function roleOf(object $user): string
    {
        return $user->role instanceof UserRole ? $user->role->value : (string) $user->role;
    }

    /**
     * Same Content-Type/filename convention as every other export in this
     * codebase. format=excel is a real .xlsx written by
     * App\Support\SheetWriter (temuan audit 2026-10-04 #1 — previously a CSV
     * body under an xlsx name).
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
                "laporan-effluent-plant_{$slug}_{$timestamp}.xlsx",
            ];
        }

        return [
            'text/csv',
            "laporan-effluent-plant_{$slug}_{$timestamp}.csv",
        ];
    }
}
