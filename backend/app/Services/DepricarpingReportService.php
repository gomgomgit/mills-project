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
use App\Models\DepricarpingDetail;
use App\Models\DepricarpingOperationalTarget;
use App\Models\DepricarpingRecord;
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
 * DepricarpingReportService — screen-152--laporan-depricarping-web /
 * usecase-155--laporan-depricarping-web (Laporan Periode Depricarping), and
 * from the same series screen-153--laporan-depricarping-mobile, which reuses
 * these endpoints verbatim.
 *
 * Shared by the API controller (App\Http\Controllers\Api\
 * DepricarpingReportController) and the Livewire component (App\Livewire\
 * Dashboard\LaporanDepricarping), so the web page and the API can never
 * disagree on a figure.
 *
 * READ-ONLY BY CONSTRUCTION: every public method is a SELECT, and the
 * /api/depricarping-reports prefix carries no POST/PUT/PATCH/DELETE.
 *
 * STRUCTURALLY THIS FOLLOWS THE PRESSING REPORT, and deliberately so:
 * depricarping_records and depricarping_details have the same SKELETON as
 * their Pressing counterparts — a daily record per unit (presser_id, a
 * STRING on the header rather than a foreign key), one row per time slot,
 * nullable measurement columns, and the same 24 canonical slots. So
 * resolveBusinessUnit / businessUnitOptions / productionLineOptions /
 * resolveProductionLine / authorizePeriod / listPeriods / buildSummary /
 * export, and the per-metric statistics, are the Pressing shapes.
 *
 * FOUR THINGS ARE DIFFERENT, and each one is new code rather than a rename.
 * What follows is only those four, plus the rules inherited from the series.
 *
 * ------------------------------------------------------------------
 * 1. DOWNTIME IS A NUMBER HERE, NOT TEXT
 * ------------------------------------------------------------------
 * On Threshing and Pressing downtime exists only as `downtime_reason`, a
 * text column, so all a report could publish was a list of causes and how
 * many slots named each. "How long did the station stop" could not be
 * answered at all. depricarping_details has `downtime_minutes`, an INTEGER.
 *
 * So this is the first station report that publishes TOTAL MINUTES STOPPED
 * over a period — a figure that can be compared between periods — together
 * with how many slots recorded it and the average per recording slot. See
 * downtimeOf().
 *
 * THE SUM IS OVER THE SLOTS THAT RECORDED IT, and the denominator is those
 * slots too. Two mistakes are possible and both are tested:
 *   - treating an unrecorded slot as zero minutes, which shrinks the
 *     average as more slots go unrecorded — the report would look better
 *     precisely because less was written down;
 *   - treating a recorded 0 as unrecorded, which throws away an Operator's
 *     statement that the station did not stop.
 * total_minutes is NULL, never 0, when nothing was recorded at all: a total
 * of 0 minutes reads as "the station never stopped", when the truth is
 * "nobody wrote it down".
 *
 * The free text lives in a SEPARATE column, `findings`, and is grouped
 * literally — exactly as downtime_reason was on the two earlier reports.
 * The two are published side by side but NEVER MERGED: one answers "how
 * long", the other "what was seen", and merging them would count a slot
 * carrying both twice under one heading.
 *
 * ------------------------------------------------------------------
 * 2. ONE STANDARD GOVERNS TWO COLUMNS
 * ------------------------------------------------------------------
 * The master holds a single 'Nut Silo Temperature' row (target
 * '60°C - 70°C') while the detail table has nut_silo_1_temp_c AND
 * nut_silo_2_temp_c. This is the first report where the column-to-parameter
 * map is not one-to-one.
 *
 * Both stay SEPARATE metrics with their own denominators: two physical
 * silos, and averaging them into one figure would hide a drifting silo
 * behind a normal one — the same error as summing Grading's two unit
 * blocks. But they share one standard, and the relationship is PUBLISHED
 * (target.shares_standard_with) rather than left to prose, because the same
 * standard printed on two consecutive rows with no explanation reads like
 * duplicated data and someone will "clean it up".
 *
 * Consequences for code reading COLUMN_TARGET_PARAMETER are spelled out on
 * that constant; the load-bearing one is that targetsWithoutMetric() must
 * compare against the SET of mapped parameters, never against count().
 *
 * ------------------------------------------------------------------
 * 3. THE MASTER HAS FOUR COLUMNS, AND THE FOURTH IS NEW
 * ------------------------------------------------------------------
 * threshing_operational_targets has `parameter`,
 * `standard_operational_target`, `action_plan_on_deviation`.
 * pressing_operational_targets has `parameter_metric`,
 * `target_operating_range`, `critical_trigger_action_limit`.
 * depricarping_operational_targets has `parameter_metric`, `target_range`,
 * `critical_limit`, AND `operational_consequence_justification`.
 *
 * NOT ONE COLUMN NAME IS SHARED ACROSS THE THREE MASTERS. Copying either
 * neighbour's field names into targetsByParameter() yields a target block
 * that is entirely null with NO error raised at all — the screen renders
 * normally with every standard missing. A test asserts the three correct
 * names explicitly for that reason.
 *
 * The fourth column states WHAT IS AT STAKE when a limit is crossed
 * ('Direct operational revenue loss', 'Low pressure drops fibre early
 * (heavy losses)'). It is published because a reader looking at a deviating
 * figure needs to know the consequence, not only that it deviates: without
 * it, three parameters all past their limits look equally urgent, and they
 * are not. It is also the column most easily dropped for space.
 *
 * ------------------------------------------------------------------
 * 4. EVERY COLUMN NOW HAS A STANDARD — AND THE ONE THAT DID NOT IS WORTH
 *    REMEMBERING
 * ------------------------------------------------------------------
 * As of 2026-10-06 all seven measurement columns map to a master parameter
 * and all six master parameters have a measurement, so targets_without_metric
 * is normally EMPTY. That looks unremarkable, which is exactly why the
 * history belongs here.
 *
 * Until that date the seventh column was named
 * `kernel_recovery_in_fibre_percent` and all four Depricarping input/detail
 * screens labelled it a RECOVERY, while its own master standard is
 * 'Kernel Loss in Fibre' with target '< 0.50%' and critical '> 1.00%' —
 * unmistakably a LOSS, smaller is better. Opposite framings of one quantity;
 * only one could be right.
 *
 * WHY THAT WAS NOT A NAMING NICETY. The two readings demand number scales
 * about TWO HUNDRED TIMES apart: 0-2% for a loss, 90-100% for a recovery. So
 * pairing the figure with that standard would have made this report announce
 * "98%, far past the critical limit > 1.00%" about a station performing very
 * well — a judgement with the direction REVERSED, on a number that still
 * looked plausible. Nothing in the system settled it either: the factory
 * wrote null, no seeder filled it, and depricarping_details held zero rows.
 *
 * So the report refused to map it and PUBLISHED THE GAP instead — the figure
 * under its own label with null targets, the standard in
 * targets_without_metric. The user settled it on 2026-10-06 in the master's
 * favour; see migration
 * 2026_10_06_000001_rename_kernel_recovery_to_kernel_loss_on_depricarping_details
 * for the full reasoning, and for why reverting the COLUMN rather than the
 * MASTER would reopen exactly this hole.
 *
 * ------------------------------------------------------------------
 * AND IT STILL FLAGS NOTHING — WITH THE SHARPEST REASON YET
 * ------------------------------------------------------------------
 * There is no severity key, no is_out_of_range key, no colouring anywhere
 * in the payload, and a unit test sweeps the whole thing to keep it that
 * way.
 *
 * The temptation is larger here than on any earlier report:
 * depricarping_operational_targets.critical_limit is the TIDIEST of the
 * three masters — '< 35 or > 55 mmH2O', '> 40%', '> 1.00%',
 * '< 55°C or > 75°C'. Five of six carry an explicit numeric comparator,
 * several on both sides. So inheriting the refusal is not enough; it has to
 * be argued:
 *
 *   1. ACROSS THE THREE MASTERS THE VALUES FOLLOW SIX DIFFERENT GRAMMARS,
 *      not one: a range ('40 - 50 mmH2O'); a range plus a third statement
 *      ('75% - 80% (Minimum 3/4 full)'); one-sided ('> 40%'); TWO-SIDED
 *      ('< 35 or > 55 mmH2O'), which needs a different parser from
 *      one-sided; TWO NUMBERS MEANING DIFFERENT THINGS IN ONE STRING
 *      ('Below 70°C (Check if >75°C)' — is the limit 70 or 75?); and prose
 *      with no number at all ('Within motor rated full-load current (FLC)',
 *      'As per mill capacity design'), which defers to a document outside
 *      this system.
 *   2. THE UNIT LIVES INSIDE THE TEXT — eight of them (MT/hr, RPM, °C, %,
 *      Amperes, Bar, mmH2O, m/s) — and one parameter spells its own unit two
 *      ways between its range and its limit ('35 - 45 Amperes' versus
 *      '> 50 Amps'). One Pressing value is ALREADY ambiguous today:
 *      '< 10% to 12%'. There is no honest way to decide whether that bound
 *      is 10 or 12.
 *   3. THE FAILURE IS SILENT, and that is what settles it. These are free
 *      text; nothing in the schema constrains their shape, so a parser's
 *      input set is not fixed at build time — a seeder run or a direct edit
 *      can introduce a seventh grammar tomorrow and no test will catch it.
 *      A parser meeting a shape it cannot read either throws (breaking the
 *      page for everyone over one master cell) or skips (STOPS WARNING
 *      without raising anything). Any reasonable implementation skips — and
 *      "no warning" cannot be told apart from "all clear". For a quality
 *      indicator that is the worst possible failure direction.
 *   4. COLOUR CARRIES MEANING BEYOND STATISTICS. A figure rendered red in a
 *      period report reads as a breach — audit material. Deriving that from
 *      prose means the system asserts a breach nobody ever defined.
 *
 * So all four figures — measurement, target range, critical limit,
 * operational consequence — are published side by side and verbatim, and
 * the judgement is left to a person. The absence is STATED on the screen,
 * because an unexplained absence reads as an unfinished feature. If
 * flagging is wanted, the honest route is splitting the master into NUMERIC
 * limit columns (min, max, comparator direction, unit) beside the text —
 * not parsing the text at render time — and deciding what is shown when
 * those are blank, because silence is not a safe answer.
 *
 * ------------------------------------------------------------------
 * RULES INHERITED FROM THE SERIES, STATED BECAUSE THEY ARE LOAD-BEARING
 * ------------------------------------------------------------------
 * PER-METRIC DENOMINATORS. All seven measurement columns are nullable and
 * filled independently, so each metric publishes its OWN filled_slot_count.
 * One shared denominator would be wrong for at least six of the seven.
 *
 * coverage_percent is NULL, never 0.0, when expected_slots is zero: 0%
 * claims something was measured and came out at zero.
 *
 * A "FILLED" SLOT IS BORROWED FROM THE INPUT SCREEN —
 * DepricarpingRecordService::isRowFilled() over its nine READING_FIELDS,
 * not a definition derived here. Two definitions that disagree would mean
 * the coverage figure does not match what the input screen accepts, and
 * nobody could tell which was wrong. THE CONSEQUENCE: downtime_minutes and
 * findings are among those nine, so coverage.filled_slots CAN EXCEED every
 * metric's own denominator. That is correct, not an inconsistency — stated
 * here so the next reader does not "fix" a gap that is right.
 *
 * ALL AGGREGATION IN PHP, NONE IN SQL. SQL aggregate behaviour over
 * NULLABLE columns differs between SQLite (the test suite) and PostgreSQL
 * (production), and the separate-denominator and null-is-not-zero rules are
 * exactly what gets lost in that difference. Date filtering uses
 * whereDate(), not a bare where().
 *
 * PRODUCTION LINE ISOLATION. Filtering is on
 * depricarping_records.production_line_id — the record's own snapshot
 * column — never through a join to `stations`, so a station later moved to
 * another line does not rewrite the readings it already produced. The line
 * is REQUIRED: the refusal is raised in the controller AND again here, so a
 * direct service call cannot skip it.
 *
 * OPERATOR IS ADMITTED ON THE THREE DATA ROUTES from day one, because the
 * mobile twin (screen-153) is built in the same series. Operator belongs in
 * the MILL-BOUND branch of resolveBusinessUnit() — admitting it in
 * guardAccess() alone would drop it into the Admin branch, where a
 * client-supplied business_unit_id IS honoured. businessUnitOptions() stays
 * ADMIN ONLY — a role bound to one mill has no picker, and handing it the
 * list of every mill is the leak this must not open.
 *
 * THE ADMISSION STOPS AT THE API. The WEB route /reports/depricarping and
 * App\Livewire\Dashboard\LaporanDepricarping::canAccess() deliberately stay
 * without Operator; canAccess() keeps its own role list precisely so this
 * service can admit a role without dragging the web page along.
 */
class DepricarpingReportService
{
    /**
     * Export row ceiling, counted in EXPORTED LINES (= depricarping_details
     * rows), not header records — one daily record carries up to 24
     * time-slot rows, so a ceiling counted per record would wave through a
     * file 24x larger than intended.
     *
     * Read through `static::` everywhere below, never `self::`, so a test
     * subclass can lower it instead of seeding 50.000 rows.
     */
    public const EXPORT_ROW_LIMIT = 50000;

    /** The station type this screen reports — the period_stations row key. */
    protected const STATION_TYPE = StationTypeEnum::Depricarping->value;

    /** Export formats this report understands. Anything else is 422. */
    public const SUPPORTED_FORMATS = ['csv', 'excel'];

    /**
     * The SEVEN numeric measurement columns, in the order the screen reads
     * them — two more than Threshing or Pressing. Every one is nullable,
     * and every one is averaged over its OWN non-null slots (metricsOf()).
     *
     * TWO READING FIELDS ARE DELIBERATELY ABSENT, and each for its own
     * reason (see DepricarpingRecordService::READING_FIELDS, nine fields):
     *   - downtime_minutes IS numeric, but SUMMED rather than averaged —
     *     see downtimeOf(). It is the first station report where downtime
     *     can be quantified at all, so it gets its own block rather than a
     *     row among the parameters, because it has no master standard.
     *   - findings is free text, so it is grouped — see findingsOf().
     * Listed as exclusions here so neither omission looks like an oversight
     * someone should "fix".
     */
    public const NUMERIC_METRICS = [
        'fan_static_pressure_mmh2o',
        'polishing_drum_speed_rpm',
        'air_velocity_ms',
        'fibre_moisture_percent',
        'kernel_loss_in_fibre_percent',
        'nut_silo_1_temp_c',
        'nut_silo_2_temp_c',
    ];

    /**
     * Human label and unit per measurement column. Kept here rather than in
     * the blade so the API and the web page publish the same wording.
     *
     * 'Kehilangan Kernel di Fibre' is the Indonesian label for the column the
     * master calls 'Kernel Loss in Fibre'. UNTIL 2026-10-06 the column was
     * named `kernel_recovery_in_fibre_percent` and every screen labelled it a
     * RECOVERY, contradicting its own master standard; the rename migration
     * 2026_10_06_000001 settled that in the master's favour. See
     * COLUMN_TARGET_PARAMETER below.
     *
     * @var array<string, array{label: string, unit: string}>
     */
    public const METRIC_LABELS = [
        'fan_static_pressure_mmh2o' => ['label' => 'Tekanan Statis Fan', 'unit' => 'mmH2O'],
        'polishing_drum_speed_rpm' => ['label' => 'Putaran Polishing Drum', 'unit' => 'RPM'],
        'air_velocity_ms' => ['label' => 'Kecepatan Udara Aspirator', 'unit' => 'm/s'],
        'fibre_moisture_percent' => ['label' => 'Kadar Air Fibre', 'unit' => '%'],
        'kernel_loss_in_fibre_percent' => ['label' => 'Kehilangan Kernel di Fibre', 'unit' => '%'],
        'nut_silo_1_temp_c' => ['label' => 'Suhu Nut Silo 1', 'unit' => 'C'],
        'nut_silo_2_temp_c' => ['label' => 'Suhu Nut Silo 2', 'unit' => 'C'],
    ];

    /**
     * THE FIXED MAP from measurement column to the master's
     * `parameter_metric` string. SEVEN entries for SEVEN columns — every
     * measurement column now has a standard, and every master parameter now
     * has a measurement.
     *
     * STILL NOT ONE-TO-ONE, and that is the one thing code reading this
     * constant must handle: nut_silo_1_temp_c AND nut_silo_2_temp_c BOTH
     * POINT AT THE SAME PARAMETER. The master holds one 'Nut Silo
     * Temperature' row while the detail table has two silo columns. They
     * stay two separate metrics with their own denominators — two physical
     * silos, and averaging them into one figure would hide a drifting silo
     * behind a normal one — but they share one standard. Consequences:
     * targetFor() returning the same master row twice is CORRECT, and
     * targetsWithoutMetric() must compare against the SET of values here,
     * never against count(), or 'Nut Silo Temperature' looks used twice and
     * some other standard looks unused. So seven entries name six distinct
     * parameters, and six is exactly how many rows the master has.
     *
     * kernel_loss_in_fibre_percent WAS DELIBERATELY ABSENT UNTIL 2026-10-06,
     * and the history matters because the mapping looks unremarkable now.
     * The column used to be named `kernel_recovery_in_fibre_percent` and all
     * four Depricarping input/detail screens labelled it a RECOVERY, while
     * its own master standard is 'Kernel Loss in Fibre' with target
     * '< 0.50%' and critical '> 1.00%' — unmistakably a LOSS. The two
     * readings demand number scales about two hundred times apart (0-2% for
     * a loss, 90-100% for a recovery), so pairing the figure with that
     * standard would have made the report judge in the REVERSE direction on
     * numbers that still looked plausible. The report therefore refused to
     * map it and published the gap instead. Settled by the user on
     * 2026-10-06 in the master's favour — see migration
     * 2026_10_06_000001_rename_kernel_recovery_to_kernel_loss_on_depricarping_details
     * for the full reasoning and for why reverting the COLUMN rather than
     * the MASTER would reopen exactly this hole.
     *
     * Fixed rather than matched by text at render time, because a typo fix
     * on the master must not be able to silently detach a figure from its
     * standard. If a parameter here is renamed in the master, the figure
     * keeps its numbers and the standard moves into targets_without_metric
     * with reason 'no_column' — visible, not silent.
     *
     * @var array<string, string>
     */
    public const COLUMN_TARGET_PARAMETER = [
        'fan_static_pressure_mmh2o' => 'Fan Static Pressure',
        'polishing_drum_speed_rpm' => 'Polishing Drum Speed',
        'air_velocity_ms' => 'Air Velocity (Aspirator)',
        'fibre_moisture_percent' => 'Fibre Moisture Content',
        'kernel_loss_in_fibre_percent' => 'Kernel Loss in Fibre',
        'nut_silo_1_temp_c' => 'Nut Silo Temperature',
        'nut_silo_2_temp_c' => 'Nut Silo Temperature',
    ];

    /**
     * Why a master standard has no measurement, as published per row of
     * targets_without_metric.
     *
     * ONE REASON, not two. Until 2026-10-06 there was a second,
     * 'direction_unresolved', for the single case where a measurement column
     * existed but its direction contradicted its own standard
     * (kernel_recovery_in_fibre_percent versus 'Kernel Loss in Fibre'). The
     * rename migration 2026_10_06_000001 settled that, so no column can be
     * in that state any more and the value is gone rather than kept as a
     * branch nothing can reach. 'no_column' remains reachable: a master
     * parameter whose name is edited, or a genuinely new parameter with no
     * column, both land here.
     */
    public const UNMAPPED_NO_COLUMN = 'no_column';

    /**
     * Export column headers — context columns first, repeated on every
     * line, then the time slot, then all NINE reading columns in
     * DepricarpingRecordService::READING_FIELDS order.
     *
     * Dropping downtime or findings here would make the file unable to
     * stand in for the report, which is the whole point of exporting it.
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
        'Tekanan Statis Fan (mmH2O)',
        'Putaran Polishing Drum (RPM)',
        'Kecepatan Udara Aspirator (m/s)',
        'Kadar Air Fibre (%)',
        'Kehilangan Kernel di Fibre (%)',
        'Suhu Nut Silo 1 (C)',
        'Suhu Nut Silo 2 (C)',
        'Downtime (Menit)',
        'Temuan',
    ];

    /**
     * code => name from the `station_types` master table, memoised per
     * service instance.
     *
     * @var array<string, string>|null
     */
    protected ?array $stationTypeNames = null;

    protected ?DepricarpingRecordService $recordService = null;

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
     * A period covers Depricarping when it HAS a `period_stations` row for
     * station_type 'depricarping'. Newest first. An empty array is a valid
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
     * Membership is decided by depricarping_records.date — the date the
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
        $slotsPerPresserPerDay = count(DepricarpingRecordService::canonicalTimeSlots());
        // PENYEBUT BERHENTI DI HARI INI untuk periode yang masih berjalan —
        // hari yang belum terjadi tidak mungkin tercatat.
        $daysCounted = ReportPeriodDays::counted($period);
        $expectedSlots = $presserCount * $daysCounted * $slotsPerPresserPerDay;

        $targets = $this->targetsByParameter();
        $targetsWithoutMetric = $this->targetsWithoutMetric($targets);

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
            'targets_without_metric' => $targetsWithoutMetric,
            'targets_master_empty' => $targets === [],
            // Lets the screen DRAW the standards-without-measurement
            // section with an explanation instead of hiding it when empty.
            // A section that disappears cannot be told apart from a section
            // nobody built — and on this screen that section carries the
            // finding most in need of a human reader.
            'all_targets_measured' => $targetsWithoutMetric === [],
            'by_presser' => $this->byPresserOf($pressers),
            'daily' => $this->dailyOf($dates),
            'daily_total' => [
                // Recomputed over EVERY filled row, never an average of the
                // daily averages — see dailyTotalOf().
                'filled_slot_count' => $filledSlots,
                'downtime_minutes' => $this->downtimeMinutesOf($filledRows),
                'averages' => $this->averagesOf($filledRows),
            ],
            // TWO SEPARATE BLOCKS ON PURPOSE. downtime answers "how long",
            // findings answers "what was seen". Merging them would count a
            // slot carrying both twice under one heading.
            'downtime' => $this->downtimeOf($filledRows),
            'findings' => $this->findingsOf($filledRows),
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
     * (depricarping_details row), with the record's context columns repeated
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

        $detailRowCount = DepricarpingDetail::query()
            ->whereIn('depricarping_record_id', (clone $recordQuery)->select('depricarping_records.id'))
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
        return DepricarpingOperationalTarget::query()
            ->orderBy('sort_order')
            ->get(['parameter_metric', 'target_range', 'critical_limit', 'operational_consequence_justification'])
            ->mapWithKeys(fn (DepricarpingOperationalTarget $target) => [
                (string) $target->parameter_metric => [
                    'parameter_metric' => (string) $target->parameter_metric,
                    'target_range' => (string) $target->target_range,
                    'critical_limit' => (string) $target->critical_limit,
                    'operational_consequence_justification' => (string) $target->operational_consequence_justification,
                ],
            ])
            ->all();
    }

    /**
     * The FOUR target columns attached to one measurement column, resolved
     * through the FIXED map — never by matching the parameter's text at
     * render time.
     *
     * NOT ONE OF THESE COLUMN NAMES IS SHARED WITH threshing_operational_targets
     * OR pressing_operational_targets. Copying either neighbour's field
     * names here yields a target block that is entirely null with no error
     * raised at all, because they simply are not properties that exist.
     *
     * ALL FOUR ARE PUBLISHED, because they answer different questions.
     * `target_range` answers "where should it be" ('40 - 50 mmH2O').
     * `critical_limit` answers "when is it too far" ('< 35 or > 55 mmH2O').
     * `operational_consequence_justification` answers "what is at stake"
     * ('Direct operational revenue loss') — the fourth column exists on
     * NEITHER neighbouring master, and it is the one most easily dropped
     * for space and most costly to drop: without it, three parameters all
     * past their limits look equally urgent, and they are not.
     *
     * `shares_standard_with` names the OTHER columns governed by the same
     * master parameter — one entry on each nut silo column, empty
     * elsewhere. DERIVED FROM THE CONSTANT, not written by hand: written by
     * hand it would start lying the moment a third silo column is added.
     *
     * THERE IS NO `unmapped_reason` KEY ANY MORE. It existed until 2026-10-06
     * for the one column deliberately left out of the map, and the rename
     * migration 2026_10_06_000001 removed that case. A key that can only ever
     * be null is worse than no key: the screen would keep a branch nothing
     * reaches, and a later reader would look for the case it was built for.
     *
     * A column still gets all-null targets when the master has no row for its
     * parameter — that is a different situation, and the screen says so by
     * rendering "belum terisi pada master" rather than a blank cell.
     *
     * @param  array<string, array<string, string>>  $targets
     * @return array{parameter_metric: string|null, target_range: string|null, critical_limit: string|null, operational_consequence_justification: string|null, shares_standard_with: list<string>}
     */
    protected function targetFor(string $column, array $targets): array
    {
        $parameter = static::COLUMN_TARGET_PARAMETER[$column] ?? null;
        $target = $parameter === null ? null : ($targets[$parameter] ?? null);

        return [
            'parameter_metric' => $target['parameter_metric'] ?? null,
            'target_range' => $target['target_range'] ?? null,
            'critical_limit' => $target['critical_limit'] ?? null,
            'operational_consequence_justification' => $target['operational_consequence_justification'] ?? null,
            'shares_standard_with' => $this->sharesStandardWith($column),
        ];
    }

    /**
     * The other measurement columns governed by the SAME master parameter
     * as $column.
     *
     * Exists because this is the first station report where one standard
     * governs more than one column: the master holds a single
     * 'Nut Silo Temperature' row while depricarping_details has
     * nut_silo_1_temp_c and nut_silo_2_temp_c. Publishing the relationship
     * matters — the same standard printed on two consecutive rows with no
     * explanation reads like duplicated data, and someone will "clean it
     * up".
     *
     * Derived from COLUMN_TARGET_PARAMETER so it stays true if a third silo
     * column is ever added.
     *
     * @return list<string>
     */
    protected function sharesStandardWith(string $column): array
    {
        $parameter = static::COLUMN_TARGET_PARAMETER[$column] ?? null;

        if ($parameter === null) {
            return [];
        }

        return array_values(array_keys(
            array_filter(
                static::COLUMN_TARGET_PARAMETER,
                fn (string $candidate, string $candidateColumn) => $candidate === $parameter
                    && $candidateColumn !== $column,
                ARRAY_FILTER_USE_BOTH
            )
        ));
    }

    /**
     * Master parameters that no measurement column reliably maps to, each
     * WITH THE REASON — because on Depricarping the reason is not the one
     * the two earlier reports had.
     *
     * Today this is exactly ONE: 'Kernel Loss in Fibre', reason
     * `direction_unresolved`. Not "there is no column" — there IS a column,
     * kernel_loss_in_fibre_percent — but the master calls the quantity a
     * LOSS ('< 0.50%', smaller is better) while the column and all four
     * Depricarping input/detail screens call it a RECOVERY. Nothing settles
     * it: the factory writes null, no seeder fills it, the dev database
     * holds zero non-null values. So the figure is published without a
     * standard and the standard is published without a figure, and the
     * contradiction becomes visible to a human instead of being resolved by
     * a guess that could invert every judgement made from this screen.
     *
     * A parameter renamed on the master also falls in here, with reason
     * `no_column` — which makes the rename visible instead of silently
     * detaching a figure from its standard.
     *
     * PUBLISHED RATHER THAN DROPPED, because a standard that is never
     * measured reads as satisfied when it is merely absent.
     *
     * COMPARED AGAINST THE SET of mapped parameters, never against
     * count(COLUMN_TARGET_PARAMETER): SEVEN entries name only SIX distinct
     * parameters, so counting would make 'Nut Silo Temperature' look used
     * twice and leave one real standard looking unused.
     *
     * NORMALLY EMPTY SINCE 2026-10-06. The master's six parameters are all
     * mapped, so this list is empty until someone edits a parameter name on
     * the master or adds a parameter with no column. The screen still DRAWS
     * the section when it is empty (all_targets_measured) — a section that
     * disappears cannot be told apart from a section nobody built, and this
     * one is exactly where such an edit would show up.
     *
     * @param  array<string, array<string, string>>  $targets
     * @return list<array{parameter_metric: string, target_range: string, critical_limit: string, operational_consequence_justification: string, reason: string}>
     */
    protected function targetsWithoutMetric(array $targets): array
    {
        $mapped = array_values(array_unique(array_values(static::COLUMN_TARGET_PARAMETER)));

        return collect($targets)
            ->reject(fn (array $target, string $parameter) => in_array($parameter, $mapped, true))
            ->map(fn (array $target) => $target + ['reason' => static::UNMAPPED_NO_COLUMN])
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
     * Every Depricarping record inside the period, flattened to plain objects
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
            ->with(['depricarpingDetails' => fn ($query) => $query->orderBy('time_slot')])
            ->orderBy('depricarping_records.date')
            ->orderBy('depricarping_records.presser_id')
            ->get()
            ->map(fn (DepricarpingRecord $record) => (object) [
                'date' => $this->dateStringOf($record->date),
                'presser_id' => (string) ($record->presser_id ?? ''),
                'status' => $this->recordStatusValue($record),
                'note' => $record->note,
                'checked' => $record->checked_by !== null,
                'acknowledged' => $record->acknowledged_by !== null,
                'rows' => $record->depricarpingDetails
                    ->map(fn (DepricarpingDetail $detail) => $this->rowOf($detail, $record))
                    ->values(),
            ])
            ->values();
    }

    /**
     * One detail row, reduced to exactly what the aggregation needs.
     *
     * `filled` REUSES DepricarpingRecordService::isRowFilled() over its
     * READING_FIELDS — the definition the input screens already enforce.
     * Writing a second "is this row filled?" rule here is how the report
     * and the form start disagreeing about what was recorded.
     */
    protected function rowOf(DepricarpingDetail $detail, DepricarpingRecord $record): object
    {
        $attributes = $detail->only(DepricarpingRecordService::READING_FIELDS);

        $values = [];

        foreach (static::NUMERIC_METRICS as $metric) {
            $raw = $detail->{$metric};
            // NULL STAYS NULL. Never coerced to 0.0 — a missing reading
            // must not be able to drag an average down.
            $values[$metric] = $raw === null || $raw === '' ? null : (float) $raw;
        }

        $finding = $detail->findings === null ? null : trim((string) $detail->findings);

        // DOWNTIME IS A NUMBER HERE, not text — the first station report
        // where that is true. null means NOBODY RECORDED IT; 0 means
        // somebody recorded that the station did not stop. Those are
        // different statements and downtimeOf() keeps them apart.
        $downtimeMinutes = $detail->downtime_minutes === null || $detail->downtime_minutes === ''
            ? null
            : (int) $detail->downtime_minutes;

        return (object) [
            'date' => $this->dateStringOf($record->date),
            'presser_id' => (string) ($record->presser_id ?? ''),
            'time_slot' => (string) $detail->time_slot,
            'filled' => $this->recordService()->isRowFilled($attributes),
            'values' => $values,
            'downtime_minutes' => $downtimeMinutes,
            // Edge whitespace only. A finding of '   ' becomes null rather
            // than a blank-labelled group.
            'findings' => $finding === '' ? null : $finding,
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
                    // null, not 0, when no slot that day recorded downtime
                    // — same rule as downtimeOf().
                    'downtime_minutes' => $this->downtimeMinutesOf($filled),
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
                    'downtime_minutes' => $this->downtimeMinutesOf($filled),
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
                'downtime_minutes' => $row->downtime_minutes,
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
                'downtime_minutes' => $row->downtime_minutes,
                'averages' => $row->averages,
            ])
            ->values()
            ->all();
    }

    /**
     * The downtime block — THE PART WITH NO COUNTERPART on Threshing or
     * Pressing, because there downtime exists only as free text and "how
     * long did the station stop" simply cannot be answered.
     *
     * TOTAL IS SUMMED OVER THE SLOTS THAT RECORDED IT, and the denominator
     * is those slots too. Two mistakes are possible here and both are
     * tested:
     *
     *   - Treating an unrecorded slot as zero minutes. That shrinks the
     *     average as more slots go unrecorded — the report would look
     *     better precisely because less was written down.
     *   - Treating a recorded 0 as unrecorded. That throws away an
     *     Operator's statement that the station did not stop.
     *
     * total_minutes is NULL, never 0, when nothing was recorded at all: a
     * total of 0 minutes reads as "the station never stopped", when the
     * truth is "nobody wrote it down".
     *
     * has_standard is always false — downtime_minutes has no row on the
     * master. Published as a key so the absence is STATED rather than
     * showing up as an empty target cell, which would read as a master
     * nobody has filled in yet.
     *
     * @param  Collection<int, object>  $filledRows
     * @return array{total_minutes: int|null, recorded_slot_count: int, avg_minutes_per_recorded_slot: float|null, has_standard: bool}
     */
    protected function downtimeOf(Collection $filledRows): array
    {
        $recorded = $filledRows
            ->map(fn ($row) => $row->downtime_minutes)
            ->filter(fn ($minutes) => $minutes !== null)
            ->values();

        $recordedCount = $recorded->count();

        if ($recordedCount === 0) {
            return [
                'total_minutes' => null,
                'recorded_slot_count' => 0,
                'avg_minutes_per_recorded_slot' => null,
                'has_standard' => false,
            ];
        }

        $total = (int) $recorded->sum();

        return [
            'total_minutes' => $total,
            'recorded_slot_count' => $recordedCount,
            'avg_minutes_per_recorded_slot' => round($total / $recordedCount, 1),
            'has_standard' => false,
        ];
    }

    /**
     * Total recorded downtime minutes for one bucket (a date or a presser),
     * following the same rule as downtimeOf(): null when no slot in the
     * bucket recorded anything, never 0.
     *
     * @param  Collection<int, object>  $filledRows
     */
    protected function downtimeMinutesOf(Collection $filledRows): ?int
    {
        $recorded = $filledRows
            ->map(fn ($row) => $row->downtime_minutes)
            ->filter(fn ($minutes) => $minutes !== null);

        return $recorded->isEmpty() ? null : (int) $recorded->sum();
    }

    /**
     * The findings recap — the free-text half of what the two earlier
     * reports published as downtime_reason, grouped the same way: LITERALLY,
     * with no spelling or case normalisation. Two spellings of one thing
     * stay two rows, and the screen says so. Normalising would merge causes
     * the writer meant to keep apart.
     *
     * DELIBERATELY NOT MERGED WITH downtimeOf(). One answers "how long",
     * the other "what was seen"; merging them would count a slot carrying
     * both twice under one heading and would destroy the ability to answer
     * either question on its own.
     *
     * @param  Collection<int, object>  $filledRows
     * @return list<array{finding: string, slot_count: int}>
     */
    protected function findingsOf(Collection $filledRows): array
    {
        return $filledRows
            ->map(fn ($row) => $row->findings)
            ->filter(fn ($finding) => $finding !== null && $finding !== '')
            ->countBy()
            ->map(fn (int $count, string $finding) => ['finding' => $finding, 'slot_count' => $count])
            ->values()
            // One comparator, two keys: count descending, then the finding
            // text ascending. The tie-break is what makes the order stable
            // instead of dependent on the engine's row order. Note this is a
            // single sort() with a comparator — Laravel's
            // sortBy([closure, closure]) is NOT that API.
            ->sort(fn (array $a, array $b) => $b['slot_count'] <=> $a['slot_count']
                ?: strcmp($a['finding'], $b['finding']))
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
            ->with(['depricarpingDetails' => fn ($detailQuery) => $detailQuery->orderBy('time_slot')])
            ->orderBy('depricarping_records.date')
            ->orderBy('depricarping_records.presser_id')
            ->orderBy('depricarping_records.id');

        foreach ($query->lazy(200) as $record) {
            /** @var DepricarpingRecord $record */
            $context = array_merge($exportContext, [
                optional($record->date)->toDateString(),
                $record->presser_id,
                // Label Indonesia, bukan enum mentah.
                ExportValue::status($this->recordStatusValue($record)),
                $record->note,
            ]);

            foreach ($record->depricarpingDetails as $detail) {
                /** @var DepricarpingDetail $detail */
                // Slot selalu HH:MM.
                $reading = [ExportValue::time($detail->time_slot)];

                foreach (DepricarpingRecordService::READING_FIELDS as $field) {
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
     * Base query over the period's Depricarping records (header rows).
     *
     * Scoped through `stations` to the PERIOD'S business unit and to the
     * depricarping station type, and bounded INCLUSIVELY on
     * depricarping_records.date — the event date, not created_at and not the
     * sync time. depricarping_records carries neither period_id nor
     * business_unit_id, so the join is the only way to scope it.
     */
    protected function recordQueryFor(Period $period, ?string $productionLineId = null): Builder
    {
        $query = DepricarpingRecord::query()
            ->join('stations', 'stations.id', '=', 'depricarping_records.station_id')
            ->where('stations.business_unit_id', $period->business_unit_id)
            ->where('stations.type', StationTypeEnum::Depricarping->value)
            // whereDate, bukan where mentah: suite berjalan di SQLite
            // sementara produksi PostgreSQL.
            ->whereDate('depricarping_records.date', '>=', $period->start_date->toDateString())
            ->whereDate('depricarping_records.date', '<=', $period->end_date->toDateString())
            ->select('depricarping_records.*');

        $this->scopeToProductionLine($query, $productionLineId);

        return $query;
    }

    /**
     * Penyaringan per production line, DI KOLOM TABEL RECORD — bukan lewat
     * join ke `stations`.
     *
     * `depricarping_records.production_line_id` adalah kolom nyata NOT NULL
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

        $query->where('depricarping_records.production_line_id', $productionLineId);
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
     * THE ADMISSION STOPS AT THE API. The WEB route /reports/depricarping and
     * App\Livewire\Dashboard\LaporanDepricarping::canAccess() deliberately
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

    protected function recordService(): DepricarpingRecordService
    {
        return $this->recordService ??= app(DepricarpingRecordService::class);
    }

    /**
     * Satu opsi periode UNTUK LAYAR INI. Bentuknya sengaja DATAR, karena
     * layar mobile dan blade membacanya apa adanya.
     *
     * Yang diminta layar ini bukan periode telanjang melainkan pasangan
     * (periode, depricarping). Karena itu `status` adalah status STASIUN INI di
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

    protected function recordStatusValue(DepricarpingRecord $record): string
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
                "laporan-depricarping_{$slug}_{$timestamp}.xlsx",
            ];
        }

        return [
            'text/csv',
            "laporan-depricarping_{$slug}_{$timestamp}.csv",
        ];
    }
}
