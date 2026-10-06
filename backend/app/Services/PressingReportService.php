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
use App\Models\PressingDetail;
use App\Models\PressingOperationalTarget;
use App\Models\PressingRecord;
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
 * PressingReportService — screen-150--laporan-pressing-web /
 * usecase-153--laporan-pressing-web (Laporan Periode Pressing), and from the
 * same series screen-151--laporan-pressing-mobile, which reuses these
 * endpoints verbatim.
 *
 * Shared by the API controller (App\Http\Controllers\Api\
 * PressingReportController) and the Livewire component (App\Livewire\
 * Dashboard\LaporanPressing), so the web page and the API can never
 * disagree on a figure.
 *
 * READ-ONLY BY CONSTRUCTION: every public method is a SELECT, and the
 * /api/pressing-reports prefix carries no POST/PUT/PATCH/DELETE.
 *
 * STRUCTURALLY THIS IS THE THRESHING REPORT, and deliberately so:
 * pressing_records and pressing_details have the SAME shape as their
 * threshing counterparts — a daily record per unit (presser_id, a STRING on
 * the header rather than a foreign key), one row per time slot, five nullable
 * measurement columns plus one free-text column (downtime_reason), and the
 * same 24 canonical slots. resolveBusinessUnit / businessUnitOptions /
 * productionLineOptions / resolveProductionLine / authorizePeriod /
 * listPeriods / buildSummary / export, and the per-metric statistics, are
 * therefore the Threshing shapes. What follows is only what is DIFFERENT.
 *
 * ------------------------------------------------------------------
 * 1. THE MASTER HAS TWO TARGET COLUMNS, AND THEY DO NOT SHARE NAMES
 *    WITH THRESHING'S
 * ------------------------------------------------------------------
 * threshing_operational_targets has `parameter`, `standard_operational_target`
 * and `action_plan_on_deviation`. pressing_operational_targets has
 * `parameter_metric`, `target_operating_range` and
 * `critical_trigger_action_limit`. Not one of the three names carries over.
 *
 * Copying the Threshing names here produces a target block that is ENTIRELY
 * NULL with not one error raised: the screen still renders, every measured
 * figure is still correct, and every standard column is simply blank. That is
 * the failure this docblock exists to prevent, and there is a unit test that
 * reproduces it.
 *
 * AND THE TWO COLUMNS MEAN DIFFERENT THINGS, so both are published side by
 * side with the figure:
 *   - target_operating_range     — where it SHOULD be ('90C - 95C',
 *                                  '35 - 45 Amperes', '45 - 55 Bar')
 *   - critical_trigger_action_limit — when someone MUST act, and what happens
 *                                  if nobody does ('< 85C (Leads to poor oil
 *                                  liberation)', '> 50 Amps (Indicates choke
 *                                  or heavy load)')
 * Publishing only one of them deletes half of what the reader decides with.
 * Both pass through VERBATIM, parenthetical and all — that parenthetical is
 * the only place the consequence of deviating is written down.
 *
 * ------------------------------------------------------------------
 * 2. STILL NO AUTOMATIC FLAGGING — AND HERE THE REASON HAS TO BE SHARPER
 * ------------------------------------------------------------------
 * On Threshing the standard was prose with no comparator at all ('Within
 * motor rated full-load current (FLC)'), so parsing it was plainly
 * impossible. On Pressing, critical_trigger_action_limit DOES carry a tidy
 * comparator on five of the seven parameters — '< 85C', '> 50 Amps',
 * '> 60 Bar', '< 80C', '< 50%'. The temptation is real, so the refusal has to
 * be argued rather than asserted:
 *
 *   a. BOTH COLUMNS ARE VARCHAR THAT ADMIN / MILL MANAGEMENT MAY EDIT AT ANY
 *      TIME. The next value could be '~ 90C', 'per SOP rev 3', or '< 85C at
 *      full load'. A parser that fails on the new shape STOPS WARNING without
 *      raising anything — and a warning that disappears reads as "everything
 *      is fine". For a quality indicator that is the worst possible direction
 *      to fail in.
 *   b. ONE VALUE ON THE MASTER IS ALREADY UNPARSEABLE WITHOUT GUESSING: Nut
 *      Breakage Rate's range reads '< 10% to 12%', and there is no honest way
 *      to decide whether the limit is 10 or 12.
 *   c. SOME RANGES CARRY A THIRD STATEMENT INSIDE PARENTHESES
 *      ('75% - 80% (Minimum 3/4 full)') which is neither part of the range
 *      nor the consequence of leaving it.
 *
 * So this report publishes three figures — measured, target range, action
 * limit — side by side and verbatim, and leaves the judgement to a human.
 * There is a unit test asserting the payload carries no severity /
 * is_out_of_range / flag key at all. The honest way to get flagging is
 * recorded as an open question on the business spec: split the master into
 * NUMERIC limit columns beside the text, never parse the text at render time.
 *
 * ------------------------------------------------------------------
 * 3. SEVEN TARGETS, FIVE MEASURED COLUMNS — AND THE TWO MISSING ONES
 *    MATTER MOST
 * ------------------------------------------------------------------
 * The master lists seven parameters; pressing_details provides five
 * measurement columns. The two left over are 'Nut Breakage Rate' and 'Press
 * Cake Moisture', and they are a sharper finding than Threshing's single
 * 'Bearing Temperature': neither has a measurement column ANYWHERE in this
 * schema, not merely in pressing_details. Verified by sweeping every
 * migration — there is no nut/breakage/press_cake column at all, and the
 * nearest thing (depricarping_details.fibre_moisture_percent) is a different
 * material at a different station.
 *
 * They are also exactly the two parameters that decide how well the station
 * pressed. They are published as `targets_without_metric` rather than
 * dropped, because a standard that is never measured looks satisfied when it
 * is simply absent.
 *
 * THE COLUMN -> PARAMETER MAP IS FIXED (COLUMN_TARGET_PARAMETER below) and is
 * never a text match performed at render time. Matching on the parameter name
 * would break the moment somebody fixes a typo on the master — and break
 * SILENTLY, by detaching a figure from its standard.
 *
 * ------------------------------------------------------------------
 * 4. EVERY AVERAGE PUBLISHES ITS OWN DENOMINATOR
 * ------------------------------------------------------------------
 * All five measurement columns are nullable and a slot counts as filled when
 * ANY ONE of the six reading columns is filled, so one slot can carry a
 * digester temperature and no cone pressure at all. Each metric is therefore
 * averaged over ONLY the slots where THAT column is non-null and carries its
 * own filled_slot_count.
 *
 * ------------------------------------------------------------------
 * 5. WHAT COUNTS AS A FILLED SLOT COMES FROM THE INPUT SCREEN
 * ------------------------------------------------------------------
 * `filled` calls PressingRecordService::isRowFilled() over its READING_FIELDS
 * — the rule the form itself enforces, made public in the same change. Note
 * the consequence: downtime_reason is one of those six fields, so a slot
 * carrying only "Cone macet" IS filled, and coverage.filled_slots can
 * therefore exceed every single metric's own denominator. That is correct
 * rather than inconsistent.
 *
 * ------------------------------------------------------------------
 * 6. coverage_percent IS NULL, NOT 0.0, WHEN NOTHING IS EXPECTED YET
 * ------------------------------------------------------------------
 * For a period that has not started, 0% claims something was measured and
 * came out at zero. null says there is no denominator, and the screen renders
 * a dash. The screen also checks days_counted BEFORE period_running, because
 * ReportPeriodDays::isRunning() is true for a not-yet-started period too.
 *
 * ------------------------------------------------------------------
 * 7. DOWNTIME REASONS ARE GROUPED LITERALLY
 * ------------------------------------------------------------------
 * No case folding, no spelling normalisation — only an edge-whitespace trim.
 * Normalising would merge causes whose authors meant them differently, and
 * the screen states the grouping is literal so two near-identical rows are
 * not read as a defect in the report.
 *
 * ------------------------------------------------------------------
 * CROSS-MILL SECURITY IS CLOSED AT TWO DIFFERENT POINTS, on purpose
 * ------------------------------------------------------------------
 *   1. resolveBusinessUnit() IGNORES the client's business_unit_id for
 *      Supervisor / Mill Management / Operator — not validated, not
 *      compared, discarded. Probing another mill's id returns 200 with the
 *      CALLER'S OWN data, deliberately not a 403: a 403 would confirm the
 *      other mill exists.
 *   2. authorizePeriod() REFUSES a period belonging to another mill with
 *      403. Here there IS a concrete handle on another mill's data.
 * A mill-bound account whose users.business_unit_id is NULL FAILS CLOSED with
 * 422 and the whole-mill list is never even read — see allBusinessUnits(),
 * public and trivial precisely so a spy can prove it was never called.
 *
 * OPERATOR IS ADMITTED FROM DAY ONE on the three data routes, because the
 * mobile twin (screen-151) is built in the same series — the pattern proved
 * by screen-146/147 and screen-148/149. Note WHICH line carries the weight:
 * admitting Operator in guardAccess() alone would drop it into the unbound
 * Admin branch where a client-supplied business_unit_id IS honoured.
 *
 * businessUnitOptions() stays ADMIN ONLY — a role bound to one mill has no
 * picker, and handing it the list of every mill is the leak this must not
 * open.
 *
 * THE ADMISSION STOPS AT THE API. The WEB route /reports/pressing and
 * App\Livewire\Dashboard\LaporanPressing::canAccess() deliberately stay
 * without Operator; canAccess() keeps its own role list precisely so this
 * service can admit a role without dragging the web page along.
 */
class PressingReportService
{
    /**
     * Export row ceiling, counted in EXPORTED LINES (= pressing_details
     * rows), not header records — one daily record carries up to 24
     * time-slot rows, so a ceiling counted per record would wave through a
     * file 24x larger than intended.
     *
     * Read through `static::` everywhere below, never `self::`, so a test
     * subclass can lower it instead of seeding 50.000 rows.
     */
    public const EXPORT_ROW_LIMIT = 50000;

    /** The station type this screen reports — the period_stations row key. */
    protected const STATION_TYPE = StationTypeEnum::Pressing->value;

    /** Export formats this report understands. Anything else is 422. */
    public const SUPPORTED_FORMATS = ['csv', 'excel'];

    /**
     * The FIVE numeric measurement columns, in the order the screen reads
     * them. Every one is nullable, and every one is averaged over its OWN
     * non-null slots — see metricsOf().
     *
     * downtime_reason is deliberately absent: it is the sixth reading field
     * (see PressingRecordService::READING_FIELDS) but it is free text, so
     * it is grouped rather than averaged. Listed as an exclusion here so
     * the omission is explicit rather than something someone "fixes".
     */
    public const NUMERIC_METRICS = [
        'digester_temp_c',
        'digester_level_percent',
        'press_motor_current_amps',
        'cone_hydraulic_pressure_bar',
        'dilution_water_temp_c',
    ];

    /**
     * Human label and unit per measurement column. Kept here rather than in
     * the blade so the API and the web page publish the same wording.
     *
     * @var array<string, array{label: string, unit: string}>
     */
    public const METRIC_LABELS = [
        'digester_temp_c' => ['label' => 'Suhu Digester', 'unit' => 'C'],
        'digester_level_percent' => ['label' => 'Level Isi Digester', 'unit' => '%'],
        'press_motor_current_amps' => ['label' => 'Arus Motor Screw Press', 'unit' => 'A'],
        'cone_hydraulic_pressure_bar' => ['label' => 'Tekanan Hidrolik Cone', 'unit' => 'bar'],
        'dilution_water_temp_c' => ['label' => 'Suhu Air Pengencer', 'unit' => 'C'],
    ];

    /**
     * THE FIXED MAP from measurement column to the master's `parameter`
     * string. Five entries for five columns; the master holds six
     * parameters, so whichever one is not named here lands in
     * targets_without_metric.
     *
     * Fixed rather than matched by text at render time, because a typo fix
     * on the master must not be able to silently detach a figure from its
     * standard. If a parameter here is renamed in the master, the figure
     * keeps its numbers and the standard moves into
     * targets_without_metric — visible, not silent.
     *
     * @var array<string, string>
     */
    public const COLUMN_TARGET_PARAMETER = [
        'digester_temp_c' => 'Digester Temperature',
        'digester_level_percent' => 'Digester Fill Level',
        'press_motor_current_amps' => 'Screw Press Motor Current',
        'cone_hydraulic_pressure_bar' => 'Cone Hydraulic Pressure',
        'dilution_water_temp_c' => 'Dilution Water Temperature',
    ];

    /**
     * Export column headers — context columns first, repeated on every
     * line, then the time slot, then all SIX reading columns in
     * PressingRecordService::READING_FIELDS order.
     *
     * @var array<int, string>
     */
    public const EXPORT_HEADER = [
        'Periode',
        'Mill',
        'Production Line',
        'Tanggal',
        'Presser',
        'Status',
        'Catatan',
        'Slot Waktu',
        'Suhu Digester (C)',
        'Level Isi Digester (%)',
        'Arus Motor Screw Press (A)',
        'Tekanan Hidrolik Cone (bar)',
        'Suhu Air Pengencer (C)',
        'Alasan Downtime',
    ];

    /**
     * code => name from the `station_types` master table, memoised per
     * service instance.
     *
     * @var array<string, string>|null
     */
    protected ?array $stationTypeNames = null;

    protected ?PressingRecordService $recordService = null;

    // ------------------------------------------------------------------
    // Access
    // ------------------------------------------------------------------

    /**
     * business_logic step 2 — which mill the caller is allowed to look at.
     *
     * Supervisor / Mill Management / Operator: ALWAYS their own
     * business_unit_id; the argument is ignored outright, so probing
     * another mill's id is a no-op that still returns the caller's own data
     * with HTTP 200.
     *
     * Admin: the value MUST come from the caller. Missing is 422
     * VALIDATION_ERROR — never a silent null and never an empty result set,
     * which would read as "this mill has no data".
     *
     * OPERATOR BELONGS IN THE MILL-BOUND BRANCH, and that is the
     * load-bearing line of admitting it at all: admitting it in
     * guardAccess() alone drops it into the Admin branch below, where a
     * client-supplied business_unit_id IS honoured.
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
                // FAIL CLOSED, and fail EARLY: this return happens before
                // any repository call, so allBusinessUnits() is provably
                // never reached from this path (asserted with a spy).
                throw ValidationException::withMessages([
                    'business_unit_id' => ['Akun Anda belum terhubung ke mill. Hubungi Admin.'],
                ]);
            }

            return $businessUnitId;
        }

        // Admin — the only unbound role, and the only one that reaches here.
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
     * This is the one route on the prefix that Operator does NOT reach, and
     * the mobile screen must therefore never call it for a bound role.
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
     * Public and deliberately trivial so it can be spied on: "fail closed"
     * for a bound account with no business_unit_id only means something if
     * it can be PROVEN the all-mills list was never built.
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
     * Berlaku untuk SEMUA peran, tidak seperti businessUnitOptions():
     * production line BUKAN ikatan akun (tidak ada users.production_line_id,
     * dan tidak boleh ada) melainkan KONTEKS YANG DIPILIH. Satu mill di
     * lapangan punya belasan line dengan jenis stasiun yang sama berulang.
     *
     * Daftar ini SELALU dibatasi mill yang berlaku, sehingga line mill lain
     * tidak pernah menjadi opsi.
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
     * Line yang benar-benar berlaku, atau null bila belum ada pilihan sah.
     *
     * MEMILIH LINE WAJIB di layar laporan — berbeda dari Data Browser yang
     * punya opsi "Semua Line". Laporan menghasilkan ANGKA GABUNGAN, dan
     * total yang mencampur belasan line bukan angka yang bisa
     * ditindaklanjuti siapa pun. Karena itu null di sini berarti "jangan
     * tampilkan angka apa pun", BUKAN "tampilkan semua line".
     *
     * Line milik mill lain dipulangkan sebagai null, sehingga hasilnya
     * adalah layar yang meminta memilih line — bukan 403 (yang justru
     * memastikan line itu ada), dan tidak pernah data mill lain.
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
     * business_logic step 9 — load a period and prove the caller may read
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
     * business_logic step 5 — the periods selectable for this mill.
     *
     * A period covers Pressing when it HAS a `period_stations` row for
     * station_type 'pressing'. Newest first. An empty array is a valid
     * answer — a mill with no period yet gets [] with HTTP 200 and a UI
     * hint pointing at Kelola Periode Pelaporan, never a 404.
     *
     * STATUS NEVER FILTERS THIS LIST: closed periods are listed and remain
     * fully readable and exportable. The period lock governs writing data,
     * not reading a report.
     *
     * @return list<array{id: string, name: string, start_date: string, end_date: string, status: string, station_type: string, station_type_label: string}>
     */
    public function listPeriods(?string $businessUnitId = null): array
    {
        $businessUnitId = $this->resolveBusinessUnit($businessUnitId);

        return Period::query()
            ->where('business_unit_id', $businessUnitId)
            ->whereHas('stations', fn (Builder $query) => $query->where('station_type', static::STATION_TYPE))
            // Dimuat terbatas pada jenis stasiun ini supaya statusValue()
            // tidak menembak satu kueri per periode (N+1).
            ->with(['stations' => fn ($query) => $query->where('station_type', static::STATION_TYPE)])
            ->orderByDesc('start_date')
            ->orderBy('name')
            ->get()
            ->map(fn (Period $period) => $this->periodOption($period))
            ->all();
    }

    /**
     * business_logic steps 6-24 — every figure on the screen for one
     * period: the period header, recording coverage, the five metrics with
     * their OWN denominators and their operational standards, the targets
     * that have no measurement at all, the per-presser recap, the daily
     * recap, the downtime-reason recap, and the period totals.
     *
     * Membership is decided by pressing_records.date — the date the
     * readings belong to — INCLUSIVE on both bounds, and never by
     * created_at or the mobile sync time. A row entered late still belongs
     * to the period it happened in.
     *
     * @param  Period|string|null  $period  model or id (both accepted so callers
     *                                      that already authorised the period do
     *                                      not have to re-read it)
     *
     * @throws ValidationException 422 VALIDATION_ERROR
     * @throws AuthorizationException 403 FORBIDDEN
     * @throws ModelNotFoundException 404 NOT_FOUND
     */
    public function buildSummary(Period|string|null $period = null, ?string $requestedBusinessUnitId = null, ?string $productionLineId = null): array
    {
        // Ordered deliberately: role first (403), then mill (422), then the
        // period id (422), then the period itself (404 / 403). A caller who
        // may not be here at all never learns which period ids exist.
        $this->guardAccess();
        $this->resolveBusinessUnit($requestedBusinessUnitId);

        $period = $this->requirePeriod($period);

        $records = $this->recordsFor($period, $productionLineId);
        $rows = $this->rowsOf($records);
        // "Filled rows" are the only ones that count. A slot whose six
        // reading columns are all empty is an untouched slot, already
        // reported as missing by coverage; counting it again would report
        // the same emptiness twice.
        $filledRows = $rows->filter(fn ($row) => $row->filled)->values();

        $dates = $this->datesOf($records);
        $pressers = $this->pressersOf($records);

        $daysInPeriod = $this->daysInPeriod($period);
        $filledSlots = $filledRows->count();
        // THRESHER COUNT IS WHAT ACTUALLY RAN, not what is registered. A
        // denominator built from registered stations would punish a mill
        // for owning a presser it deliberately did not operate.
        $presserCount = $pressers->count();
        // Expected slots per unit per day comes from the canonical grid the
        // input screens themselves use — one definition, one answer.
        $slotsPerPresserPerDay = count(PressingRecordService::canonicalTimeSlots());
        // PENYEBUT BERHENTI DI HARI INI untuk periode yang masih berjalan —
        // hari yang belum terjadi tidak mungkin tercatat.
        $daysCounted = ReportPeriodDays::counted($period);
        $expectedSlots = $presserCount * $daysCounted * $slotsPerPresserPerDay;

        $targets = $this->targetsByParameter();

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
            'production_line' => $this->productionLineInfo($productionLineId),
            // has_data distinguishes "there is nothing to report" from "the
            // figures happen to be zero". The screen uses it to refuse to
            // draw an empty table, which would read as a measured zero.
            'has_data' => $filledSlots > 0,
            // RECORDING COVERAGE IS PART OF THE REPORT, NOT METADATA. A
            // period filled to a fifth still produces tidy-looking
            // averages, and the reader must see that before trusting them —
            // which is why the screen renders this ABOVE every other figure.
            'coverage' => [
                'filled_slots' => $filledSlots,
                'expected_slots' => $expectedSlots,
                // NULL, not 0.0 — see the class docblock. 0% claims
                // something was measured and came out at zero.
                'coverage_percent' => $expectedSlots === 0
                    ? null
                    : round(100 * $filledSlots / $expectedSlots, 1),
                'presser_count' => $presserCount,
                'slots_per_presser_per_day' => $slotsPerPresserPerDay,
                'days_in_period' => $daysInPeriod,
                'days_counted' => $daysCounted,
                'period_running' => ReportPeriodDays::isRunning($period),
            ],
            'metrics' => $this->metricsOf($filledRows, $targets),
            'targets_without_metric' => $this->targetsWithoutMetric($targets),
            'targets_master_empty' => $targets === [],
            'by_presser' => $this->byPresserOf($pressers),
            'daily' => $this->dailyOf($dates),
            'daily_total' => [
                // Recomputed over EVERY filled row, never an average of the
                // daily averages — see dailyTotalOf().
                'filled_slot_count' => $filledSlots,
                'averages' => $this->averagesOf($filledRows),
            ],
            'downtime_reasons' => $this->downtimeReasonsOf($filledRows),
            'total' => [
                'record_count' => $records->count(),
                'days_with_records' => $dates->count(),
                'draft_record_count' => $records->filter(fn ($record) => $this->isDraft($record->status))->count(),
                'records_not_checked' => $records->filter(fn ($record) => ! $record->checked)->count(),
                'records_not_acknowledged' => $records->filter(fn ($record) => ! $record->acknowledged)->count(),
            ],
        ];
    }

    /**
     * Repo-convention alias of buildSummary(), so this service reads the
     * same way as the seven sibling report services at the call sites. One
     * implementation, two names — never two implementations.
     */
    public function summary(Period|string|null $period = null, ?string $requestedBusinessUnitId = null, ?string $productionLineId = null): array
    {
        return $this->buildSummary($period, $requestedBusinessUnitId, $productionLineId);
    }

    /**
     * business_logic step 25 — ONE EXPORTED LINE PER TIME SLOT
     * (pressing_details row), with the record's context columns repeated
     * on every line so the file can be pivoted directly in a spreadsheet.
     *
     * THE GUARD AND THE ROW-LIMIT CHECK RUN EAGERLY, at call time, while
     * the rows themselves are yielded lazily from a chunked query. Making
     * this method itself a generator would defer the 403/422 until the
     * first iteration, so a refused export would look like a successful
     * call that produced nothing.
     *
     * The ceiling counts DETAIL ROWS, not header records.
     *
     * @return Generator<int, array<int, string|float|null>>
     *
     * @throws AuthorizationException 403 FORBIDDEN
     * @throws ModelNotFoundException 404 NOT_FOUND
     * @throws ValidationException 422 VALIDATION_ERROR
     * @throws ExportFailedException 422 EXPORT_FAILED
     */
    public function buildExportRows(Period|string|null $period = null, ?string $requestedBusinessUnitId = null, ?string $productionLineId = null): Generator
    {
        $this->guardAccess();
        $this->resolveBusinessUnit($requestedBusinessUnitId);

        $period = $this->requirePeriod($period);

        $recordQuery = $this->recordQueryFor($period, $productionLineId);

        $detailRowCount = PressingDetail::query()
            ->whereIn('pressing_record_id', (clone $recordQuery)->select('pressing_records.id'))
            ->count();

        // Strictly greater than: exactly EXPORT_ROW_LIMIT rows still
        // export. static:: and not self:: so a test subclass can lower the
        // ceiling instead of seeding 50.000 rows.
        if ($detailRowCount > static::EXPORT_ROW_LIMIT) {
            throw new ExportFailedException;
        }

        $exportContext = [
            (string) $period->name,
            (string) ($period->businessUnit?->name ?? ''),
            (string) ($this->productionLineInfo($productionLineId)['name'] ?? ''),
        ];

        return $this->streamExportRows($recordQuery, $exportContext);
    }

    /**
     * The streamed file around buildExportRows().
     *
     * @throws ValidationException 422 VALIDATION_ERROR (unsupported format)
     * @throws ExportFailedException 422 EXPORT_FAILED
     */
    public function export(Period|string|null $period = null, string $format = 'csv', ?string $requestedBusinessUnitId = null, ?string $productionLineId = null): StreamedResponse
    {
        $this->guardAccess();

        if (! in_array($format, static::SUPPORTED_FORMATS, true)) {
            throw ValidationException::withMessages([
                'format' => ['Format ekspor harus csv atau excel.'],
            ]);
        }

        $resolvedPeriod = $this->requirePeriod($period);

        // Runs the guard + the row-limit check NOW, before a single byte of
        // the response is committed — a refused export must never begin
        // streaming.
        $rows = $this->buildExportRows($resolvedPeriod, $requestedBusinessUnitId, $productionLineId);

        try {
            [$contentType, $filename] = $this->fileMetaFor($format, $resolvedPeriod);

            return response()->streamDownload(function () use ($rows, $format) {
                $handle = SheetWriter::open($format);
                $handle->row(static::EXPORT_HEADER);

                foreach ($rows as $row) {
                    $handle->row($row);
                }

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
    // Operational targets — the part that exists in no sibling report
    // ------------------------------------------------------------------

    /**
     * The master, keyed by its `parameter_metric` string, in sort_order.
     *
     * NOTE THE COLUMN NAMES. This master does NOT share them with
     * threshing_operational_targets: here they are `parameter_metric`,
     * `target_operating_range` and `critical_trigger_action_limit`, not
     * `parameter` / `standard_operational_target` /
     * `action_plan_on_deviation`. Copying the Threshing names produces a
     * target block that is entirely null with NOT ONE error raised — the
     * screen still renders, every figure is still right, and every standard
     * column is simply blank. There is a unit test for exactly that.
     *
     * An empty master is a normal state (seeder not run yet), and it must
     * NOT remove the measured figures from the screen — see
     * targets_master_empty.
     *
     * @return array<string, array{parameter_metric: string, target_operating_range: string, critical_trigger_action_limit: string}>
     */
    protected function targetsByParameter(): array
    {
        return PressingOperationalTarget::query()
            ->orderBy('sort_order')
            ->get(['parameter_metric', 'target_operating_range', 'critical_trigger_action_limit'])
            ->mapWithKeys(fn (PressingOperationalTarget $target) => [
                (string) $target->parameter_metric => [
                    'parameter_metric' => (string) $target->parameter_metric,
                    'target_operating_range' => (string) $target->target_operating_range,
                    'critical_trigger_action_limit' => (string) $target->critical_trigger_action_limit,
                ],
            ])
            ->all();
    }

    /**
     * The TWO target columns attached to one measurement column, resolved
     * through the FIXED map — never by matching the parameter's text at
     * render time.
     *
     * BOTH ARE PUBLISHED, because they answer different questions.
     * `target_operating_range` answers "where should it be" ('90C - 95C',
     * '35 - 45 Amperes'). `critical_trigger_action_limit` answers "when must
     * someone act, and what happens if nobody does" ('< 85C (Leads to poor
     * oil liberation)'). Publishing only one of them deletes half of what the
     * reader decides with. Both pass through VERBATIM, parenthetical and all
     * — the parenthetical is the only place the consequence is stated.
     *
     * All three fields null when the master has no row for that parameter,
     * so the screen can say "no standard recorded" without the figure
     * disappearing.
     *
     * @param  array<string, array<string, string>>  $targets
     * @return array{parameter_metric: string|null, target_operating_range: string|null, critical_trigger_action_limit: string|null}
     */
    protected function targetFor(string $column, array $targets): array
    {
        $parameter = static::COLUMN_TARGET_PARAMETER[$column] ?? null;
        $target = $parameter === null ? null : ($targets[$parameter] ?? null);

        return [
            'parameter_metric' => $target['parameter_metric'] ?? null,
            'target_operating_range' => $target['target_operating_range'] ?? null,
            'critical_trigger_action_limit' => $target['critical_trigger_action_limit'] ?? null,
        ];
    }

    /**
     * Master parameters that NO measurement column maps to.
     *
     * Today this is exactly TWO — 'Nut Breakage Rate' and 'Press Cake
     * Moisture'. And they are a sharper finding than Threshing's single one:
     * neither has a measurement column ANYWHERE in this schema, not merely
     * in pressing_details (verified by sweeping every migration; the nearest
     * thing, depricarping_details.fibre_moisture_percent, is a different
     * material at a different station). Both are also exactly the parameters
     * that decide how well the station pressed.
     *
     * They are published rather than dropped because a standard that is
     * never measured reads as satisfied when it is merely absent.
     *
     * It also catches the other direction: a parameter renamed on the
     * master falls in here, which makes the rename visible instead of
     * silently detaching a figure from its standard.
     *
     * @param  array<string, array<string, string>>  $targets
     * @return list<array{parameter_metric: string, target_operating_range: string, critical_trigger_action_limit: string}>
     */
    protected function targetsWithoutMetric(array $targets): array
    {
        $mapped = array_values(static::COLUMN_TARGET_PARAMETER);

        return collect($targets)
            ->reject(fn (array $target, string $parameter) => in_array($parameter, $mapped, true))
            ->values()
            ->all();
    }

    // ------------------------------------------------------------------
    // Aggregation — ALL OF IT IN PHP, none of it in SQL
    //
    // Deliberate, and the reason is decisive: SQL aggregate behaviour over
    // NULLABLE columns is NOT the same in SQLite (the test suite) and
    // PostgreSQL (production), and the separate-denominator and
    // null-is-not-zero rules are exactly what gets lost in that difference.
    // No avg()/sum()/groupBy() is issued at the SQL layer anywhere below.
    // The row set is bounded by one reporting period; the export path — the
    // only unbounded one — streams in chunks instead.
    // ------------------------------------------------------------------

    /**
     * Every Pressing record inside the period, flattened to plain objects
     * with their time-slot detail rows attached, ascending by time_slot.
     *
     * ONE DATE CAN HAVE SEVERAL RECORDS — one per presser, because
     * presser_id is a STRING column on the HEADER, not a foreign key. The
     * per-date aggregation merges them; the per-presser aggregation splits
     * them apart again.
     *
     * @return Collection<int, object>
     */
    protected function recordsFor(Period $period, ?string $productionLineId = null): Collection
    {
        return $this->recordQueryFor($period, $productionLineId)
            // time_slot is a TIME column: ordering uses the TIME value as
            // it stands, never cast to an integer hour.
            ->with(['pressingDetails' => fn ($query) => $query->orderBy('time_slot')])
            ->orderBy('pressing_records.date')
            ->orderBy('pressing_records.presser_id')
            ->get()
            ->map(fn (PressingRecord $record) => (object) [
                'date' => $this->dateStringOf($record->date),
                'presser_id' => (string) ($record->presser_id ?? ''),
                'status' => $this->recordStatusValue($record),
                'note' => $record->note,
                'checked' => $record->checked_by !== null,
                'acknowledged' => $record->acknowledged_by !== null,
                'rows' => $record->pressingDetails
                    ->map(fn (PressingDetail $detail) => $this->rowOf($detail, $record))
                    ->values(),
            ])
            ->values();
    }

    /**
     * One detail row, reduced to exactly what the aggregation needs.
     *
     * `filled` REUSES PressingRecordService::isRowFilled() over its
     * READING_FIELDS — the definition the input screens already enforce.
     * Writing a second "is this row filled?" rule here is how the report
     * and the form start disagreeing about what was recorded.
     */
    protected function rowOf(PressingDetail $detail, PressingRecord $record): object
    {
        $attributes = $detail->only(PressingRecordService::READING_FIELDS);

        $values = [];

        foreach (static::NUMERIC_METRICS as $metric) {
            $raw = $detail->{$metric};
            // NULL STAYS NULL. Never coerced to 0.0 — a missing reading
            // must not be able to drag an average down.
            $values[$metric] = $raw === null || $raw === '' ? null : (float) $raw;
        }

        $reason = $detail->downtime_reason === null ? null : trim((string) $detail->downtime_reason);

        return (object) [
            'date' => $this->dateStringOf($record->date),
            'presser_id' => (string) ($record->presser_id ?? ''),
            'time_slot' => (string) $detail->time_slot,
            'filled' => $this->recordService()->isRowFilled($attributes),
            'values' => $values,
            // Edge whitespace only. A reason of '   ' becomes null rather
            // than a blank-labelled group.
            'downtime_reason' => $reason === '' ? null : $reason,
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
     * THE HEART OF THIS REPORT: one entry per metric, each with ITS OWN
     * denominator, each carrying its own operational standard.
     *
     * A metric is averaged over ONLY the slots where that column is
     * non-null, and reports how many slots that was. There is deliberately
     * no shared denominator anywhere in the payload.
     *
     * A metric never filled in the whole period returns min/avg/max null
     * with filled_slot_count 0 — NOT 0/0/0, which reads as "we measured it
     * and it was zero". Its row STAYS in the list: a missing row reads as
     * "there is no such parameter", when the truth is "nobody measured it".
     *
     * NOTE WHAT IS ABSENT: no comparison against `target`, no severity, no
     * flag. See the class docblock.
     *
     * @param  Collection<int, object>  $filledRows
     * @param  array<string, array<string, string>>  $targets
     * @return list<array{column: string, label: string, unit: string, min: float|null, avg: float|null, max: float|null, filled_slot_count: int, target: array}>
     */
    protected function metricsOf(Collection $filledRows, array $targets): array
    {
        $metrics = [];

        foreach (static::NUMERIC_METRICS as $metric) {
            $values = $this->valuesOf($filledRows, $metric);
            $stats = $this->statsOf($values);

            $metrics[] = [
                'column' => $metric,
                'label' => static::METRIC_LABELS[$metric]['label'] ?? $metric,
                'unit' => static::METRIC_LABELS[$metric]['unit'] ?? '',
                'min' => $stats['min'],
                'avg' => $stats['avg'],
                'max' => $stats['max'],
                'filled_slot_count' => $stats['filled_slot_count'],
                'target' => $this->targetFor($metric, $targets),
            ];
        }

        return $metrics;
    }

    /**
     * One metric's non-null values across a bucket of rows.
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
     * min / avg / max / filled_slot_count over one metric's own values.
     *
     * Empty input is null/null/null/0, never 0/0/0/0.
     *
     * @param  array<int, float>  $values
     * @return array{min: float|null, avg: float|null, max: float|null, filled_slot_count: int}
     */
    protected function statsOf(array $values): array
    {
        if ($values === []) {
            return ['min' => null, 'avg' => null, 'max' => null, 'filled_slot_count' => 0];
        }

        return [
            'min' => round(min($values), 2),
            'avg' => round(array_sum($values) / count($values), 2),
            'max' => round(max($values), 2),
            'filled_slot_count' => count($values),
        ];
    }

    /**
     * Each metric averaged over one bucket (a date or a presser) — again,
     * each with ITS OWN denominator inside that bucket.
     *
     * null when the bucket has no value at all for that metric.
     *
     * @param  Collection<int, object>  $filledRows
     * @return array<string, float|null>
     */
    protected function averagesOf(Collection $filledRows): array
    {
        $averages = [];

        foreach (static::NUMERIC_METRICS as $metric) {
            $values = $this->valuesOf($filledRows, $metric);

            $averages[$metric] = $values === []
                ? null
                : round(array_sum($values) / count($values), 2);
        }

        return $averages;
    }

    /**
     * One aggregate per DATE that has at least one record, ascending,
     * merging every presser that ran on that date.
     *
     * A date whose rows are all empty for a given metric yields null in
     * that column but STILL COUNTS as a date with records — dropping it
     * would make the period look better recorded than it was. A date with
     * no record at all gets no entry: a padded zero row would read as "we
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
                $filled = $group->flatMap(fn ($record) => $record->rows)
                    ->filter(fn ($row) => $row->filled)
                    ->values();

                return (object) [
                    'date' => $date,
                    'filled_slot_count' => $filled->count(),
                    'averages' => $this->averagesOf($filled),
                ];
            })
            ->values();
    }

    /**
     * One aggregate per THRESHER (presser_id) over every record in the
     * period.
     *
     * presser_id is the UNIT'S NAME, not a row key: the same id on two
     * dates is one presser with two days of records, and day_count says
     * two.
     *
     * A presser that has a record but not one filled slot STILL APPEARS,
     * with filled_slot_count 0 and null averages. Dropping it would hide
     * exactly the unit that was never written down — the one worth seeing.
     *
     * @param  Collection<int, object>  $records
     * @return Collection<int, object>
     */
    protected function pressersOf(Collection $records): Collection
    {
        return $records
            ->groupBy('presser_id')
            ->sortKeys()
            ->map(function (Collection $group, string $presserId) {
                $filled = $group->flatMap(fn ($record) => $record->rows)
                    ->filter(fn ($row) => $row->filled)
                    ->values();

                return (object) [
                    'presser_id' => $presserId,
                    'day_count' => $group->pluck('date')->unique()->count(),
                    'filled_slot_count' => $filled->count(),
                    'averages' => $this->averagesOf($filled),
                ];
            })
            ->values();
    }

    /**
     * The daily recap table.
     *
     * @param  Collection<int, object>  $dates
     * @return list<array{date: string, filled_slot_count: int, averages: array<string, float|null>}>
     */
    protected function dailyOf(Collection $dates): array
    {
        return $dates
            ->map(fn ($row) => [
                'date' => $row->date,
                'filled_slot_count' => $row->filled_slot_count,
                'averages' => $row->averages,
            ])
            ->values()
            ->all();
    }

    /**
     * The per-presser recap table.
     *
     * @param  Collection<int, object>  $pressers
     * @return list<array{presser_id: string, day_count: int, filled_slot_count: int, averages: array<string, float|null>}>
     */
    protected function byPresserOf(Collection $pressers): array
    {
        return $pressers
            ->map(fn ($row) => [
                'presser_id' => $row->presser_id,
                'day_count' => $row->day_count,
                'filled_slot_count' => $row->filled_slot_count,
                'averages' => $row->averages,
            ])
            ->values()
            ->all();
    }

    /**
     * Downtime reasons, GROUPED LITERALLY.
     *
     * No case folding and no spelling normalisation — only the edge-whitespace
     * trim already applied in rowOf(). 'Belt kendur' and 'belt kendur' are
     * two rows, and the screen says the grouping is literal so that is read
     * as the data's shape rather than the report's defect.
     *
     * Ordered by count descending, then alphabetically, so the result is
     * stable across database engines rather than dependent on row order.
     *
     * A slot carrying a reason AND measurements contributes to both: a
     * downtime note is extra information about that slot, never a filter.
     *
     * @param  Collection<int, object>  $filledRows
     * @return list<array{reason: string, slot_count: int}>
     */
    protected function downtimeReasonsOf(Collection $filledRows): array
    {
        return $filledRows
            ->map(fn ($row) => $row->downtime_reason)
            ->filter(fn ($reason) => $reason !== null && $reason !== '')
            ->countBy()
            ->map(fn (int $count, string $reason) => ['reason' => $reason, 'slot_count' => $count])
            ->values()
            // One comparator, two keys: count descending, then the reason
            // text ascending. The tie-break is what makes the order stable
            // instead of dependent on the engine's row order.
            ->sort(fn (array $a, array $b) => $b['slot_count'] <=> $a['slot_count']
                ?: strcmp($a['reason'], $b['reason']))
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
     * Ordered by date, then presser, then time_slot — compared as the TIME
     * value it is, never as an integer hour.
     *
     * A slot whose measurement columns are all empty STILL YIELDS A ROW,
     * with empty cells. Dropping it would make the file disagree with the
     * coverage figure the same report publishes.
     *
     * @return Generator<int, array<int, string|float|null>>
     */
    protected function streamExportRows(Builder $recordQuery, array $exportContext = ['', '', '']): Generator
    {
        $query = (clone $recordQuery)
            ->with(['pressingDetails' => fn ($detailQuery) => $detailQuery->orderBy('time_slot')])
            ->orderBy('pressing_records.date')
            ->orderBy('pressing_records.presser_id')
            ->orderBy('pressing_records.id');

        foreach ($query->lazy(200) as $record) {
            /** @var PressingRecord $record */
            $context = array_merge($exportContext, [
                optional($record->date)->toDateString(),
                $record->presser_id,
                // Label Indonesia, bukan enum mentah.
                ExportValue::status($this->recordStatusValue($record)),
                $record->note,
            ]);

            foreach ($record->pressingDetails as $detail) {
                /** @var PressingDetail $detail */
                // Slot selalu HH:MM.
                $reading = [ExportValue::time($detail->time_slot)];

                foreach (PressingRecordService::READING_FIELDS as $field) {
                    // VERBATIM: no unit normalisation and no rounding —
                    // exactly what the Operator typed.
                    $reading[] = $detail->{$field};
                }

                yield array_merge($context, $reading);
            }
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Base query over the period's Pressing records (header rows).
     *
     * Scoped through `stations` to the PERIOD'S business unit and to the
     * pressing station type, and bounded INCLUSIVELY on
     * pressing_records.date — the event date, not created_at and not the
     * sync time. pressing_records carries neither period_id nor
     * business_unit_id, so the join is the only way to scope it.
     */
    protected function recordQueryFor(Period $period, ?string $productionLineId = null): Builder
    {
        $query = PressingRecord::query()
            ->join('stations', 'stations.id', '=', 'pressing_records.station_id')
            ->where('stations.business_unit_id', $period->business_unit_id)
            ->where('stations.type', StationTypeEnum::Pressing->value)
            // whereDate, bukan where mentah: suite berjalan di SQLite
            // sementara produksi PostgreSQL.
            ->whereDate('pressing_records.date', '>=', $period->start_date->toDateString())
            ->whereDate('pressing_records.date', '<=', $period->end_date->toDateString())
            ->select('pressing_records.*');

        $this->scopeToProductionLine($query, $productionLineId);

        return $query;
    }

    /**
     * Penyaringan per production line, DI KOLOM TABEL RECORD — bukan lewat
     * join ke `stations`.
     *
     * `pressing_records.production_line_id` adalah kolom nyata NOT NULL
     * yang di-SNAPSHOT dari stasiun saat record dibuat dan tidak pernah
     * berubah sesudahnya. Membaca dari kolom record itulah yang benar
     * secara semantik: untuk record lama yang stasiunnya sudah DIPINDAH ke
     * line lain, kolom record menunjuk line tempat data itu benar-benar
     * dihasilkan, sedangkan `stations.production_line_id` menunjuk line
     * stasiun itu SEKARANG. Menyaring lewat join ke `stations` akan menulis
     * ulang sejarah setiap kali sebuah stasiun dipindahkan.
     */
    protected function scopeToProductionLine(mixed $query, ?string $productionLineId): void
    {
        if ($productionLineId === null || $productionLineId === '') {
            return;
        }

        $query->where('pressing_records.production_line_id', $productionLineId);
    }

    /**
     * Session + role gate shared by every entry point.
     *
     * Two layers deeper than the route middleware on purpose: clearing the
     * middleware must never be enough by itself.
     *
     * OPERATOR IS ADMITTED HERE FROM DAY ONE, and the matching line in the
     * MILL-BOUND branch of resolveBusinessUnit() is what makes that safe.
     * The two are one change, never two.
     *
     * THE ADMISSION STOPS AT THE API. The WEB route /reports/pressing and
     * App\Livewire\Dashboard\LaporanPressing::canAccess() deliberately
     * stay without Operator — canAccess() keeps its own role list precisely
     * so this service can admit a role without dragging the web page along.
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
     * line yang berlaku.
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

    /** Inclusive day count of the period — both ends belong to it. */
    protected function daysInPeriod(Period $period): int
    {
        return (int) $period->start_date->copy()->startOfDay()
            ->diffInDays($period->end_date->copy()->startOfDay()) + 1;
    }

    protected function recordService(): PressingRecordService
    {
        return $this->recordService ??= app(PressingRecordService::class);
    }

    /**
     * Satu opsi periode UNTUK LAYAR INI. Bentuknya sengaja DATAR, karena
     * layar mobile dan blade membacanya apa adanya.
     *
     * Yang diminta layar ini bukan periode telanjang melainkan pasangan
     * (periode, pressing). Karena itu `status` adalah status STASIUN INI di
     * periode itu (period_stations.status).
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
            'station_type' => static::STATION_TYPE,
            'station_type_label' => $this->stationTypeLabel(static::STATION_TYPE),
        ];
    }

    /**
     * Status yang dilaporkan layar ini adalah status BARIS period_stations
     * untuk jenis stasiun ini, bukan status periode: periode tidak punya
     * status sendiri, karena stasiun tidak ditutup serentak.
     *
     * Tanpa baris untuk jenis ini jawabannya 'draft' — arti draft memang
     * "stasiun ini belum dipakai di periode ini". Sengaja bukan string
     * kosong: nilainya harus tetap salah satu dari draft/open/closed karena
     * blade dan layar mobile mencocokkannya.
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
     * yang sudah di-eager-load bila ada, dan baru menembak kueri sendiri
     * bila belum.
     */
    protected function stationRowOf(Period $period): ?PeriodStation
    {
        if ($period->relationLoaded('stations')) {
            return $period->stations->firstWhere('station_type', static::STATION_TYPE);
        }

        return $period->stations()
            ->where('station_type', static::STATION_TYPE)
            ->first();
    }

    protected function recordStatusValue(PressingRecord $record): string
    {
        return $record->status instanceof \BackedEnum
            ? $record->status->value
            : (string) $record->status;
    }

    /**
     * Draft covers BOTH draft states. They are counted together because
     * what the reader needs to know is "how much of this report stands on
     * unfinished data", and an ongoing draft and a paused one are equally
     * unfinished.
     */
    protected function isDraft(string $status): bool
    {
        return $status === 'draft_ongoing' || $status === 'draft_paused';
    }

    /**
     * Label resolved from the `station_types` master table, not from
     * App\Enums\StationType — station types are DATA, so a type added by
     * INSERT must render its real name without a code change.
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
     * App\Support\SheetWriter.
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
                "laporan-pressing_{$slug}_{$timestamp}.xlsx",
            ];
        }

        return [
            'text/csv',
            "laporan-pressing_{$slug}_{$timestamp}.csv",
        ];
    }
}
