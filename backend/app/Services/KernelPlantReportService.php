<?php

namespace App\Services;

use App\Enums\PeriodStatus;
use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Models\BusinessUnit;
use App\Models\KernelPlantDetail;
use App\Models\KernelPlantOperationalTarget;
use App\Models\KernelPlantRecord;
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
 * KernelPlantReportService — screen-154--laporan-kernel-plant-web /
 * usecase-160--laporan-kernel-plant-web (Laporan Periode Kernel Plant), and
 * from the same series screen-155--laporan-kernel-plant-mobile, which reuses
 * these endpoints verbatim.
 *
 * Shared by the API controller (App\Http\Controllers\Api\
 * KernelPlantReportController) and the Livewire component (App\Livewire\
 * Dashboard\LaporanKernelPlant), so the web page and the API can never
 * disagree on a figure.
 *
 * READ-ONLY BY CONSTRUCTION: every public method is a SELECT, and the
 * /api/kernel-plant-reports prefix carries no POST/PUT/PATCH/DELETE.
 *
 * STRUCTURALLY THIS FOLLOWS THE DEPRICARPING REPORT — the SIXTH period
 * condition report, and deliberately a port rather than a new design:
 * kernel_plant_records and kernel_plant_details have the same SKELETON as
 * their Depricarping counterparts — a daily record per unit (kernel_plant_id,
 * a STRING on the header rather than a foreign key), one row per time slot,
 * nullable measurement columns, the same 24 canonical slots, AND the same
 * two-column downtime shape (`downtime_minutes` INTEGER beside `findings`
 * free text). So resolveBusinessUnit / businessUnitOptions /
 * productionLineOptions / resolveProductionLine / authorizePeriod /
 * listPeriods / buildSummary / export, the per-metric statistics, the
 * downtime block and the findings recap are the Depricarping shapes.
 *
 * THREE THINGS ARE DIFFERENT, and each one is new code rather than a rename.
 * What follows is only those three, plus the rules inherited from the series.
 *
 * ------------------------------------------------------------------
 * 1. THE STATION UNIT IS THE KERNEL PLANT ITSELF, NOT A PRESSER
 * ------------------------------------------------------------------
 * Pressing and Depricarping both record a `presser_id` on the header.
 * kernel_plant_records carries `kernel_plant_id` instead — the SAME kind of
 * column (a plain `string`, required, free text; see
 * 2026_08_23_000011_create_kernel_plant_records_table and
 * KernelPlantRecordService::validateForm()), naming a different kind of
 * thing. So the per-unit recap is by_kernel_plant, the coverage denominator
 * is kernel_plant_count x days_counted x slots_per_kernel_plant_per_day, and
 * every "presser" in the ported names became "kernel plant".
 *
 * BECAUSE IT IS A LABEL AND NOT A FOREIGN KEY, kernel_plant_name is that
 * same stored string. There is no `kernel_plants` master table in this
 * schema (only kernel_plant_records / _details / _operational_targets), so
 * resolving a name through a lookup would mean inventing a table. The key is
 * published anyway, as the screen's display field, so that introducing such
 * a master later changes one method here and nothing on the screen.
 *
 * ------------------------------------------------------------------
 * 2. TWO STANDARDS EACH GOVERN TWO COLUMNS — NOT ONE
 * ------------------------------------------------------------------
 * On Depricarping exactly one master row ('Nut Silo Temperature') governed
 * two columns, and it was tempting to treat that as "the silo pair". HERE
 * THERE ARE TWO SUCH PAIRS: 'Ripple Mill (Cracker)' governs
 * ripple_mill_1_amps AND ripple_mill_2_amps, and 'Kernel Silo 1 & 2'
 * governs kernel_silo_1_temp_c AND kernel_silo_2_temp_c. Code that
 * special-cases a SINGLE shared pair passes every Depricarping test and is
 * wrong here — which is why sharesStandardWith() is derived from the map and
 * contains no column name at all in its body.
 *
 * So SEVEN measurement columns name FIVE distinct parameters, against a
 * master of SIX rows. Every consequence of a non-one-to-one map is sharper
 * than it was on Depricarping and spelled out on COLUMN_TARGET_PARAMETER;
 * the load-bearing one is unchanged: targetsWithoutMetric() must compare
 * against the SET of mapped parameters (five), NEVER against
 * count(COLUMN_TARGET_PARAMETER) (seven). Counting makes 'Kernel Silo 1 & 2'
 * look used twice and leaves a real standard looking unused.
 *
 * Both columns of each pair stay SEPARATE metrics with their own
 * denominators: two physical ripple mills and two physical silos, and
 * averaging a pair into one figure would hide a drifting unit behind a
 * normal one — the same error as summing Grading's two unit blocks. But each
 * pair shares one standard, and the relationship is PUBLISHED
 * (target.shares_standard_with) rather than left to prose, because the same
 * standard printed on two consecutive rows with no explanation reads like
 * duplicated data and someone will "clean it up".
 *
 * ------------------------------------------------------------------
 * 3. THE MASTER HAS THREE COLUMNS, NOT FOUR — AND NONE OF THEM IS A
 *    CRITICAL LIMIT
 * ------------------------------------------------------------------
 * threshing_operational_targets has `parameter`,
 * `standard_operational_target`, `action_plan_on_deviation`.
 * pressing_operational_targets has `parameter_metric`,
 * `target_operating_range`, `critical_trigger_action_limit`.
 * depricarping_operational_targets has four: `parameter_metric`,
 * `target_range`, `critical_limit`, `operational_consequence_justification`.
 * kernel_plant_operational_targets has THREE AND THEY ARE NAMED NOTHING LIKE
 * ANY OF THEM: `equipment_parameter`, `target_benchmark`,
 * `corrective_action_plan`.
 *
 * NOT ONE COLUMN NAME IS SHARED ACROSS THE FOUR MASTERS. Copying a
 * neighbour's field names into targetsByParameter() yields a target block
 * that is entirely null with NO error raised at all — the screen renders
 * normally, every measured figure is still right, and every standard cell is
 * simply blank. A unit test asserts the three correct names explicitly for
 * that reason.
 *
 * AND THE TARGET BLOCK IS THEREFORE SHORTER BY DESIGN: there is no
 * `critical_limit` key and no `operational_consequence_justification` key,
 * because this master holds neither. Inventing them as permanently-null fields to make
 * the payload "match" Depricarping's would publish two columns that can only
 * ever be empty — the screen would keep two cells nobody can fill and a
 * later reader would go looking for the data that was supposed to be in
 * them. What this master has instead of a critical limit is
 * `corrective_action_plan`, which answers "what do I DO about it" ('Adjust
 * rotor-vane clearance if uncracked nut rate >5%.') rather than "when is it
 * too far" — a different question, published verbatim.
 *
 * ------------------------------------------------------------------
 * ONE STANDARD HAS NO MEASUREMENT, AND THAT IS THE NORMAL STATE HERE
 * ------------------------------------------------------------------
 * targets_without_metric normally holds EXACTLY ONE row: 'Final Kernel
 * Dirt' (target '≤ 6.0% (Standard quality premium)'), reason `no_column`.
 * Unlike Depricarping — where as of 2026-10-06 the list is normally EMPTY —
 * this gap is not an accident of mapping: kernel dirt has no measurement
 * column ANYWHERE in this schema, not merely none in kernel_plant_details.
 * Nothing in the log sheet records it, so no map could close the gap.
 *
 * PUBLISHED RATHER THAN DROPPED, because a standard that is never measured
 * reads as satisfied when it is merely absent — and this one names the
 * quality premium the mill is paid on.
 *
 * ------------------------------------------------------------------
 * AND IT STILL FLAGS NOTHING
 * ------------------------------------------------------------------
 * There is no severity key, no is_out_of_range key, no colouring anywhere in
 * the payload, and a unit test sweeps the whole thing to keep it that way.
 *
 * The temptation is smaller here than on Depricarping and the refusal is
 * easier to argue, but it is the same refusal:
 *
 *   1. THERE IS NO CRITICAL LIMIT COLUMN AT ALL on this master. The only
 *      numbers available are inside `target_benchmark`, and every one of the
 *      six carries something else in the same string: a range plus a
 *      parenthetical claim about a DIFFERENT quantity ('20 - 25 Amps (Nut
 *      Breakage >95%)' — the Amps are the target, the >95% is not a limit on
 *      the Amps); a range with its unit spelled as a prefix ('Specific
 *      Gravity 1.18 - 1.24'); a range plus a zone qualifier ('70°C - 80°C
 *      (Top/Middle zones)'); and three one-sided bounds each with a reason
 *      attached ('≤ 7.0% (Prevents mold growth)'). A parser has to decide
 *      which number in the string is the bound, and on the very first row it
 *      would decide wrongly.
 *   2. THE UNIT LIVES INSIDE THE TEXT — Amps, SG, °C and % across six rows,
 *      one of them written as a leading phrase rather than a suffix.
 *   3. THE FAILURE IS SILENT, and that is what settles it. These are free
 *      text; nothing in the schema constrains their shape, so a parser's
 *      input set is not fixed at build time — a seeder run or a direct edit
 *      can introduce a new grammar tomorrow and no test will catch it. A
 *      parser meeting a shape it cannot read either throws (breaking the
 *      page for everyone over one master cell) or skips (STOPS WARNING
 *      without raising anything). Any reasonable implementation skips — and
 *      "no warning" cannot be told apart from "all clear". For a quality
 *      indicator that is the worst possible failure direction.
 *   4. COLOUR CARRIES MEANING BEYOND STATISTICS. A figure rendered red in a
 *      period report reads as a breach — audit material. Deriving that from
 *      prose means the system asserts a breach nobody ever defined.
 *
 * So the measurement, the target benchmark and the corrective action plan
 * are published side by side and verbatim, and the judgement is left to a
 * person. The absence is STATED on the screen, because an unexplained
 * absence reads as an unfinished feature. If flagging is wanted, the honest
 * route is adding NUMERIC limit columns (min, max, comparator direction,
 * unit) beside the text on this master — not parsing the text at render time
 * — and deciding what is shown when those are blank, because silence is not
 * a safe answer.
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
 * KernelPlantRecordService::isRowFilled() over its nine READING_FIELDS, not
 * a definition derived here (both were made public for exactly this). Two
 * definitions that disagree would mean the coverage figure does not match
 * what the input screen accepts, and nobody could tell which was wrong. THE
 * CONSEQUENCE: downtime_minutes and findings are among those nine, so
 * coverage.filled_slots CAN EXCEED every metric's own denominator — a
 * findings-only slot counts as filled and adds to no metric's denominator.
 * That is correct, not an inconsistency — stated here so the next reader
 * does not "fix" a gap that is right.
 *
 * DOWNTIME AND FINDINGS ARE PUBLISHED SIDE BY SIDE AND NEVER MERGED. One
 * answers "how long", the other "what was seen"; merging them would count a
 * slot carrying both twice under one heading and would destroy the ability
 * to answer either question on its own.
 *
 * ALL AGGREGATION IN PHP, NONE IN SQL. SQL aggregate behaviour over
 * NULLABLE columns differs between SQLite (the test suite) and PostgreSQL
 * (production), and the separate-denominator and null-is-not-zero rules are
 * exactly what gets lost in that difference. Date filtering uses
 * whereDate(), not a bare where().
 *
 * PRODUCTION LINE ISOLATION. Filtering is on
 * kernel_plant_records.production_line_id — the record's own snapshot
 * column — never through a join to `stations`, so a station later moved to
 * another line does not rewrite the readings it already produced. The line
 * is REQUIRED: the refusal is raised in the controller AND again here, so a
 * direct service call cannot skip it.
 *
 * OPERATOR IS ADMITTED ON THE THREE DATA ROUTES from day one, because the
 * mobile twin (screen-155) is built in the same series. Operator belongs in
 * the MILL-BOUND branch of resolveBusinessUnit() — admitting it in
 * guardAccess() alone would drop it into the Admin branch, where a
 * client-supplied business_unit_id IS honoured. businessUnitOptions() stays
 * ADMIN ONLY — a role bound to one mill has no picker, and handing it the
 * list of every mill is the leak this must not open.
 *
 * THE ADMISSION STOPS AT THE API. The WEB route /reports/kernel-plant and
 * App\Livewire\Dashboard\LaporanKernelPlant::canAccess() deliberately stay
 * without Operator; canAccess() keeps its own role list precisely so this
 * service can admit a role without dragging the web page along.
 */
class KernelPlantReportService
{
    /**
     * Export row ceiling, counted in EXPORTED LINES (= kernel_plant_details
     * rows), not header records — one daily record carries up to 24
     * time-slot rows, so a ceiling counted per record would wave through a
     * file 24x larger than intended.
     *
     * Read through `static::` everywhere below, never `self::`, so a test
     * subclass can lower it instead of seeding 50.000 rows.
     */
    public const EXPORT_ROW_LIMIT = 50000;

    /**
     * The station type this screen reports — the period_stations row key.
     *
     * 'kernel-plant', WITH A HYPHEN (App\Enums\StationType::KernelPlant).
     * Taken from the enum rather than written as a literal: an underscore
     * or a space here would match no period_stations row, so listPeriods()
     * would answer [] for every mill — an empty picker, with no error.
     */
    protected const STATION_TYPE = StationTypeEnum::KernelPlant->value;

    /** Export formats this report understands. Anything else is 422. */
    public const SUPPORTED_FORMATS = ['csv', 'excel'];

    /**
     * The SEVEN numeric measurement columns, in the order the screen reads
     * them. Every one is nullable, and every one is averaged over its OWN
     * non-null slots (metricsOf()).
     *
     * TWO READING FIELDS ARE DELIBERATELY ABSENT, and each for its own
     * reason (see KernelPlantRecordService::READING_FIELDS, nine fields):
     *   - downtime_minutes IS numeric, but SUMMED rather than averaged —
     *     see downtimeOf(). It gets its own block rather than a row among
     *     the parameters, because it has no master standard: there is no
     *     kernel_plant_operational_targets row for downtime at all.
     *   - findings is free text, so it is grouped — see findingsOf().
     * Listed as exclusions here so neither omission looks like an oversight
     * someone should "fix".
     */
    public const NUMERIC_METRICS = [
        'ripple_mill_1_amps',
        'ripple_mill_2_amps',
        'claybath_hydro_sg',
        'kernel_silo_1_temp_c',
        'kernel_silo_2_temp_c',
        'kernel_moisture_percent',
        'shell_loss_percent',
    ];

    /**
     * Human label and unit per measurement column. Kept here rather than in
     * the blade so the API and the web page publish the same wording.
     *
     * The two PAIRS are labelled with their own number — 'Arus Ripple Mill
     * 1' / '2', 'Suhu Kernel Silo 1' / '2' — even though each pair shares
     * one master standard. Two physical units, two figures, two labels; the
     * shared standard is expressed in target.shares_standard_with instead,
     * not by collapsing the labels.
     *
     * @var array<string, array{label: string, unit: string}>
     */
    public const METRIC_LABELS = [
        'ripple_mill_1_amps' => ['label' => 'Arus Ripple Mill 1', 'unit' => 'Amps'],
        'ripple_mill_2_amps' => ['label' => 'Arus Ripple Mill 2', 'unit' => 'Amps'],
        'claybath_hydro_sg' => ['label' => 'Claybath / Hydrocyclone', 'unit' => 'SG'],
        'kernel_silo_1_temp_c' => ['label' => 'Suhu Kernel Silo 1', 'unit' => 'C'],
        'kernel_silo_2_temp_c' => ['label' => 'Suhu Kernel Silo 2', 'unit' => 'C'],
        'kernel_moisture_percent' => ['label' => 'Kadar Air Kernel', 'unit' => '%'],
        'shell_loss_percent' => ['label' => 'Shell Bin Kernel Loss', 'unit' => '%'],
    ];

    /**
     * THE FIXED MAP from measurement column to the master's
     * `equipment_parameter` string. SEVEN entries for SEVEN columns — every
     * measurement column has a standard.
     *
     * FAR FROM ONE-TO-ONE, and more so than on any earlier report: TWO
     * PARAMETERS EACH GOVERN TWO COLUMNS. 'Ripple Mill (Cracker)' is the
     * standard for ripple_mill_1_amps AND ripple_mill_2_amps; 'Kernel Silo
     * 1 & 2' — a master row that names both silos in its own title — is the
     * standard for kernel_silo_1_temp_c AND kernel_silo_2_temp_c. So seven
     * entries name FIVE distinct parameters, against a master of SIX rows.
     *
     * DEPRICARPING HAD EXACTLY ONE SUCH PAIR, AND THAT IS THE TRAP. Code
     * that special-cases a single shared pair — "if the column ends in
     * _2_temp_c" — passes every Depricarping test and is wrong here.
     * Consequences for code reading this constant:
     *   - targetFor() returning the same master row twice is CORRECT, and
     *     four times over this map (twice for each pair).
     *   - sharesStandardWith() is DERIVED from this map and names no column
     *     in its body, so a third ripple mill or a third silo column is
     *     marked correctly without touching it.
     *   - targetsWithoutMetric() must compare against the SET of values
     *     here (five), NEVER against count() (seven). Counting makes
     *     'Kernel Silo 1 & 2' look used twice and leaves a real standard
     *     looking unused.
     *
     * Each pair stays TWO metrics with their own denominators: two physical
     * ripple mills and two physical silos, and averaging a pair into one
     * figure would hide a drifting unit behind a normal one.
     *
     * NO ENTRY FOR 'Final Kernel Dirt', and that is not a gap in this map:
     * kernel dirt has no measurement column anywhere in this schema. It
     * surfaces in targets_without_metric with reason 'no_column', which is
     * why that list is normally exactly one row long rather than empty.
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
        'ripple_mill_1_amps' => 'Ripple Mill (Cracker)',
        'ripple_mill_2_amps' => 'Ripple Mill (Cracker)',
        'claybath_hydro_sg' => 'Claybath / Hydrocyclone',
        'kernel_silo_1_temp_c' => 'Kernel Silo 1 & 2',
        'kernel_silo_2_temp_c' => 'Kernel Silo 1 & 2',
        'kernel_moisture_percent' => 'Final Kernel Moisture',
        'shell_loss_percent' => 'Shell Bin Kernel Loss',
    ];

    /**
     * Why a master standard has no measurement, as published per row of
     * targets_without_metric.
     *
     * ONE REASON, and on this station one reason is all that can occur: a
     * master parameter either has a measurement column mapped to it or has
     * none at all. Depricarping once carried a second value,
     * 'direction_unresolved', for a column whose direction contradicted its
     * own standard; nothing in kernel_plant_details is in that state — every
     * mapped column agrees with its standard's direction — so the value is
     * not invented here. A key with no reachable branch is worse than no
     * key: the screen would keep a case nobody can produce and a later
     * reader would look for the data it was built for.
     */
    public const UNMAPPED_NO_COLUMN = 'no_column';

    /**
     * Export column headers — context columns first, repeated on every
     * line, then the time slot, then all NINE reading columns in
     * KernelPlantRecordService::READING_FIELDS order.
     *
     * Dropping downtime or findings here would make the file unable to
     * stand in for the report, which is the whole point of exporting it.
     *
     * 'Unit Kernel Plant' rather than 'Presser': the header column carries
     * kernel_plant_id, the label of the kernel plant that produced the
     * readings.
     *
     * @var array<int, string>
     */
    public const EXPORT_HEADER = [
        'Periode',
        'Mill',
        'Production Line',
        'Tanggal',
        'Unit Kernel Plant',
        'Status',
        'Catatan',
        'Slot Waktu',
        'Ripple Mill 1 (Amps)',
        'Ripple Mill 2 (Amps)',
        'Claybath/Hydrocyclone (SG)',
        'Suhu Kernel Silo 1 (C)',
        'Suhu Kernel Silo 2 (C)',
        'Kadar Air Kernel (%)',
        'Shell Bin Kernel Loss (%)',
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

    protected ?KernelPlantRecordService $recordService = null;

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
     * A period covers Kernel Plant when it HAS a `period_stations` row for
     * station_type 'kernel-plant' (hyphen). Newest first. An empty array is
     * a valid answer — a mill with no period yet gets [] with HTTP 200 and a
     * UI hint pointing at Kelola Periode Pelaporan, never a 404.
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
     * period: the period header, recording coverage, the seven metrics with
     * their OWN denominators and their operational standards, the standard
     * that has no measurement at all, the per-kernel-plant recap, the daily
     * recap, the downtime block, the findings recap, and the period totals.
     *
     * Membership is decided by kernel_plant_records.date — the date the
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
        // "Filled rows" are the only ones that count. A slot whose nine
        // reading columns are all empty is an untouched slot, already
        // reported as missing by coverage; counting it again would report
        // the same emptiness twice.
        $filledRows = $rows->filter(fn ($row) => $row->filled)->values();

        $dates = $this->datesOf($records);
        $kernelPlants = $this->kernelPlantsOf($records);

        $daysInPeriod = $this->daysInPeriod($period);
        $filledSlots = $filledRows->count();
        // UNIT COUNT IS WHAT ACTUALLY RAN, not what is registered. A
        // denominator built from registered stations would punish a mill
        // for owning a kernel plant it deliberately did not operate.
        $kernelPlantCount = $kernelPlants->count();
        // Expected slots per unit per day comes from the canonical grid the
        // input screens themselves use — one definition, one answer.
        $slotsPerKernelPlantPerDay = count(KernelPlantRecordService::canonicalTimeSlots());
        // PENYEBUT BERHENTI DI HARI INI untuk periode yang masih berjalan —
        // hari yang belum terjadi tidak mungkin tercatat.
        $daysCounted = ReportPeriodDays::counted($period);
        $expectedSlots = $kernelPlantCount * $daysCounted * $slotsPerKernelPlantPerDay;

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
            'production_line' => $this->productionLineInfo($productionLineId, (string) $period->business_unit_id),
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
                'kernel_plant_count' => $kernelPlantCount,
                'slots_per_kernel_plant_per_day' => $slotsPerKernelPlantPerDay,
                'days_in_period' => $daysInPeriod,
                'days_counted' => $daysCounted,
                // Published beside days_counted, not instead of it: the
                // screen must check days_counted === 0 BEFORE reading
                // period_running, because a period that starts tomorrow is
                // "not started", not "running with nothing recorded".
                'period_running' => ReportPeriodDays::isRunning($period),
            ],
            'metrics' => $this->metricsOf($filledRows, $targets),
            'targets_without_metric' => $targetsWithoutMetric,
            'targets_master_empty' => $targets === [],
            // Lets the screen DRAW the standards-without-measurement
            // section with an explanation instead of hiding it when empty.
            // A section that disappears cannot be told apart from a section
            // nobody built — and on this screen that section normally holds
            // exactly one row ('Final Kernel Dirt'), the finding most in
            // need of a human reader.
            'all_targets_measured' => $targetsWithoutMetric === [],
            'by_kernel_plant' => $this->byKernelPlantOf($kernelPlants),
            'daily' => $this->dailyOf($dates),
            'daily_total' => [
                // Recomputed over EVERY filled row, never an average of the
                // daily averages — see averagesOf().
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
     * same way as the sibling report services at the call sites. One
     * implementation, two names — never two implementations.
     */
    public function summary(Period|string|null $period = null, ?string $requestedBusinessUnitId = null, ?string $productionLineId = null): array
    {
        return $this->buildSummary($period, $requestedBusinessUnitId, $productionLineId);
    }

    /**
     * business_logic step 25 — ONE EXPORTED LINE PER TIME SLOT
     * (kernel_plant_details row), with the record's context columns repeated
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

        $detailRowCount = KernelPlantDetail::query()
            ->whereIn('kernel_plant_record_id', (clone $recordQuery)->select('kernel_plant_records.id'))
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
            (string) ($this->productionLineInfo($productionLineId, (string) $period->business_unit_id)['name'] ?? ''),
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
     * The master, keyed by its `equipment_parameter` string, in sort_order.
     *
     * NOTE THE COLUMN NAMES. This master shares them with NEITHER
     * depricarping_operational_targets NOR pressing_operational_targets NOR
     * threshing_operational_targets: here they are `equipment_parameter`,
     * `target_benchmark` and `corrective_action_plan` — and there are only
     * THREE of them, with no critical-limit column of any kind. Copying a
     * neighbour's field names produces a target block that is entirely null
     * with NOT ONE error raised — the screen still renders, every figure is
     * still right, and every standard column is simply blank. There is a
     * unit test for exactly that.
     *
     * An empty master is a normal state (seeder not run yet), and it must
     * NOT remove the measured figures from the screen — see
     * targets_master_empty.
     *
     * @return array<string, array{equipment_parameter: string, target_benchmark: string, corrective_action_plan: string}>
     */
    protected function targetsByParameter(): array
    {
        return KernelPlantOperationalTarget::query()
            ->orderBy('sort_order')
            ->get(['equipment_parameter', 'target_benchmark', 'corrective_action_plan'])
            ->mapWithKeys(fn (KernelPlantOperationalTarget $target) => [
                (string) $target->equipment_parameter => [
                    'equipment_parameter' => (string) $target->equipment_parameter,
                    'target_benchmark' => (string) $target->target_benchmark,
                    'corrective_action_plan' => (string) $target->corrective_action_plan,
                ],
            ])
            ->all();
    }

    /**
     * The THREE target columns attached to one measurement column, resolved
     * through the FIXED map — never by matching the parameter's text at
     * render time — plus the derived `shares_standard_with`.
     *
     * NOT ONE OF THESE COLUMN NAMES IS SHARED WITH ANY OTHER STATION'S
     * MASTER. Copying a neighbour's field names here yields a target block
     * that is entirely null with no error raised at all, because they simply
     * are not properties that exist.
     *
     * ALL THREE ARE PUBLISHED, because they answer different questions.
     * `equipment_parameter` names what is being judged ('Kernel Silo 1 &
     * 2'). `target_benchmark` answers "where should it be" ('70°C - 80°C
     * (Top/Middle zones)') and is rendered WHOLE, parenthetical and all —
     * the qualifier is part of the standard, not decoration.
     * `corrective_action_plan` answers "what do I do about it" ('Check
     * heater elements/steam valves if temperature drops below 65°C.'), which
     * is this master's answer in place of a critical limit.
     *
     * THERE IS NO `critical_limit` AND NO
     * `operational_consequence_justification` KEY, unlike Depricarping's
     * four-key block. kernel_plant_operational_targets has no such columns,
     * and publishing them as permanently-null keys would hand the screen two
     * cells nobody can ever fill.
     *
     * `shares_standard_with` names the OTHER columns governed by the same
     * master parameter — one entry on each ripple mill column and one on
     * each kernel silo column, empty on the other three. DERIVED FROM THE
     * CONSTANT, not written by hand: written by hand it would start lying
     * the moment a third ripple mill or a third silo column is added. It
     * stays NESTED UNDER `target`, exactly where the five earlier condition
     * reports put it — the relationship is a property of the standard, not
     * of the measurement.
     *
     * A column gets all-null targets when the master has no row for its
     * parameter; the screen says so by rendering "belum terisi pada master"
     * rather than a blank cell.
     *
     * @param  array<string, array<string, string>>  $targets
     * @return array{equipment_parameter: string|null, target_benchmark: string|null, corrective_action_plan: string|null, shares_standard_with: list<string>}
     */
    protected function targetFor(string $column, array $targets): array
    {
        $parameter = static::COLUMN_TARGET_PARAMETER[$column] ?? null;
        $target = $parameter === null ? null : ($targets[$parameter] ?? null);

        return [
            'equipment_parameter' => $target['equipment_parameter'] ?? null,
            'target_benchmark' => $target['target_benchmark'] ?? null,
            'corrective_action_plan' => $target['corrective_action_plan'] ?? null,
            'shares_standard_with' => $this->sharesStandardWith($column),
        ];
    }

    /**
     * The other measurement columns governed by the SAME master parameter
     * as $column.
     *
     * Exists because one standard can govern more than one column, and on
     * Kernel Plant that happens TWICE, not once: 'Ripple Mill (Cracker)'
     * governs ripple_mill_1_amps and ripple_mill_2_amps, 'Kernel Silo 1 & 2'
     * governs kernel_silo_1_temp_c and kernel_silo_2_temp_c. Publishing the
     * relationship matters — the same standard printed on two consecutive
     * rows with no explanation reads like duplicated data, and someone will
     * "clean it up".
     *
     * DERIVED ENTIRELY FROM COLUMN_TARGET_PARAMETER: this body names no
     * column and no parameter. Depricarping had a single shared pair, and an
     * implementation that hardcoded that one pair would pass there and fail
     * here on the second. A third ripple mill or a third silo is marked
     * correctly without touching this method.
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
     * Master parameters that no measurement column maps to, each WITH THE
     * REASON.
     *
     * NORMALLY EXACTLY ONE ROW: 'Final Kernel Dirt' (target '≤ 6.0%
     * (Standard quality premium)'), reason `no_column`. Kernel dirt has no
     * measurement column ANYWHERE in this schema — not merely none in
     * kernel_plant_details — so nothing in the log sheet records it and no
     * mapping could close the gap. Unlike Depricarping, where this list is
     * normally empty, here a non-empty list is the correct steady state.
     *
     * A parameter renamed on the master also falls in here, with the same
     * reason — which makes the rename visible instead of silently detaching
     * a figure from its standard.
     *
     * PUBLISHED RATHER THAN DROPPED, because a standard that is never
     * measured reads as satisfied when it is merely absent — and this one
     * names the quality premium the mill is paid on.
     *
     * COMPARED AGAINST THE SET of mapped parameters, never against
     * count(COLUMN_TARGET_PARAMETER): SEVEN entries name only FIVE distinct
     * parameters, so counting would make 'Ripple Mill (Cracker)' and
     * 'Kernel Silo 1 & 2' each look used twice and leave two real standards
     * looking unused — and 'Final Kernel Dirt', the one row that genuinely
     * belongs here, could drop out of the list entirely.
     *
     * The screen still DRAWS the section when it is empty
     * (all_targets_measured) — a section that disappears cannot be told
     * apart from a section nobody built.
     *
     * @param  array<string, array<string, string>>  $targets
     * @return list<array{equipment_parameter: string, target_benchmark: string, corrective_action_plan: string, reason: string}>
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
     * Every Kernel Plant record inside the period, flattened to plain
     * objects with their time-slot detail rows attached, ascending by
     * time_slot.
     *
     * ONE DATE CAN HAVE SEVERAL RECORDS — one per kernel plant, because
     * kernel_plant_id is a STRING column on the HEADER, not a foreign key.
     * The per-date aggregation merges them; the per-kernel-plant aggregation
     * splits them apart again.
     *
     * @return Collection<int, object>
     */
    protected function recordsFor(Period $period, ?string $productionLineId = null): Collection
    {
        return $this->recordQueryFor($period, $productionLineId)
            // time_slot is a TIME column: ordering uses the TIME value as
            // it stands, never cast to an integer hour.
            ->with(['kernelPlantDetails' => fn ($query) => $query->orderBy('time_slot')])
            ->orderBy('kernel_plant_records.date')
            ->orderBy('kernel_plant_records.kernel_plant_id')
            ->get()
            ->map(fn (KernelPlantRecord $record) => (object) [
                'date' => $this->dateStringOf($record->date),
                'kernel_plant_id' => (string) ($record->kernel_plant_id ?? ''),
                'status' => $this->recordStatusValue($record),
                'note' => $record->note,
                'checked' => $record->checked_by !== null,
                'acknowledged' => $record->acknowledged_by !== null,
                'rows' => $record->kernelPlantDetails
                    ->map(fn (KernelPlantDetail $detail) => $this->rowOf($detail, $record))
                    ->values(),
            ])
            ->values();
    }

    /**
     * One detail row, reduced to exactly what the aggregation needs.
     *
     * `filled` REUSES KernelPlantRecordService::isRowFilled() over its
     * READING_FIELDS — the definition the input screens already enforce,
     * and the reason both were made public. Writing a second "is this row
     * filled?" rule here, or re-listing the nine columns in this file, is
     * how the report and the form start disagreeing about what was
     * recorded.
     */
    protected function rowOf(KernelPlantDetail $detail, KernelPlantRecord $record): object
    {
        $attributes = $detail->only(KernelPlantRecordService::READING_FIELDS);

        $values = [];

        foreach (static::NUMERIC_METRICS as $metric) {
            $raw = $detail->{$metric};
            // NULL STAYS NULL. Never coerced to 0.0 — a missing reading
            // must not be able to drag an average down.
            $values[$metric] = $raw === null || $raw === '' ? null : (float) $raw;
        }

        $finding = $detail->findings === null ? null : trim((string) $detail->findings);

        // DOWNTIME IS A NUMBER on this station, not text — the Depricarping
        // shape rather than the Threshing/Pressing one. null means NOBODY
        // RECORDED IT; 0 means somebody recorded that the station did not
        // stop. Those are different statements and downtimeOf() keeps them
        // apart.
        $downtimeMinutes = $detail->downtime_minutes === null || $detail->downtime_minutes === ''
            ? null
            : (int) $detail->downtime_minutes;

        return (object) [
            'date' => $this->dateStringOf($record->date),
            'kernel_plant_id' => (string) ($record->kernel_plant_id ?? ''),
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
     * THE TWO SHARED-STANDARD PAIRS EACH PRODUCE TWO ROWS carrying the SAME
     * `target` block — expected, not duplicated data. Each row keeps its own
     * numbers and its own denominator, and target.shares_standard_with names
     * the sibling so the repetition reads as deliberate.
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
     * Each metric averaged over one bucket (a date, a kernel plant, or the
     * whole period) — again, each with ITS OWN denominator inside that
     * bucket.
     *
     * THE PERIOD TOTAL CALLS THIS OVER EVERY FILLED ROW, never over the
     * daily averages: averaging averages weights a day with one filled slot
     * the same as a day with twenty-four.
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
     * merging every kernel plant that ran on that date.
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
     * One aggregate per KERNEL PLANT (kernel_plant_id) over every record in
     * the period.
     *
     * kernel_plant_id is the UNIT'S LABEL, not a row key: the same value on
     * two dates is one kernel plant with two days of records, and day_count
     * says two. It is also why kernel_plant_name is that same string — there
     * is no master table to resolve it against (see the class docblock).
     *
     * A kernel plant that has a record but not one filled slot STILL
     * APPEARS, with filled_slot_count 0 and null averages. Dropping it would
     * hide exactly the unit that was never written down — the one worth
     * seeing.
     *
     * @param  Collection<int, object>  $records
     * @return Collection<int, object>
     */
    protected function kernelPlantsOf(Collection $records): Collection
    {
        return $records
            ->groupBy('kernel_plant_id')
            ->sortKeys()
            ->map(function (Collection $group, string $kernelPlantId) {
                $filled = $group->flatMap(fn ($record) => $record->rows)
                    ->filter(fn ($row) => $row->filled)
                    ->values();

                return (object) [
                    'kernel_plant_id' => $kernelPlantId,
                    'kernel_plant_name' => $this->kernelPlantNameOf($kernelPlantId),
                    'day_count' => $group->pluck('date')->unique()->count(),
                    'filled_slot_count' => $filled->count(),
                    'downtime_minutes' => $this->downtimeMinutesOf($filled),
                    'averages' => $this->averagesOf($filled),
                ];
            })
            ->values();
    }

    /**
     * The display name of one kernel plant unit.
     *
     * IT IS THE STORED STRING ITSELF, and that is a statement about the
     * schema rather than a shortcut: kernel_plant_records.kernel_plant_id is
     * a plain `string` column, required and free text
     * (KernelPlantRecordService::validateForm()), exactly like
     * pressing/depricarping `presser_id`. There is no `kernel_plants` master
     * table in this schema — only kernel_plant_records, kernel_plant_details
     * and kernel_plant_operational_targets — so resolving a name through a
     * lookup would mean inventing a table, and returning null or '' would
     * leave the screen with an empty column beside a populated id.
     *
     * Kept as its own method so that introducing such a master later is one
     * change here and none on the screen or in the payload shape.
     */
    protected function kernelPlantNameOf(string $kernelPlantId): string
    {
        return $kernelPlantId;
    }

    /**
     * The daily recap table.
     *
     * @param  Collection<int, object>  $dates
     * @return list<array{date: string, filled_slot_count: int, downtime_minutes: int|null, averages: array<string, float|null>}>
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
     * The per-kernel-plant recap table.
     *
     * @param  Collection<int, object>  $kernelPlants
     * @return list<array{kernel_plant_id: string, kernel_plant_name: string, day_count: int, filled_slot_count: int, downtime_minutes: int|null, averages: array<string, float|null>}>
     */
    protected function byKernelPlantOf(Collection $kernelPlants): array
    {
        return $kernelPlants
            ->map(fn ($row) => [
                'kernel_plant_id' => $row->kernel_plant_id,
                'kernel_plant_name' => $row->kernel_plant_name,
                'day_count' => $row->day_count,
                'filled_slot_count' => $row->filled_slot_count,
                'downtime_minutes' => $row->downtime_minutes,
                'averages' => $row->averages,
            ])
            ->values()
            ->all();
    }

    /**
     * The downtime block — quantified minutes, the Depricarping shape, not
     * the free-text-only shape Threshing and Pressing are stuck with.
     *
     * TOTAL IS SUMMED OVER THE SLOTS THAT RECORDED IT, and the denominator
     * is those slots too. Two mistakes are possible here and both are
     * tested:
     *
     *   - Treating an unrecorded slot as zero minutes. That shrinks the
     *     average as more slots go unrecorded — the report would look
     *     better precisely because less was written down.
     *   - Treating a recorded 0 as unrecorded. That throws away an
     *     Operator's statement that the station did not stop, which is
     *     exactly why KernelPlantRecordService::isRowFilled() lets a 0 in
     *     downtime_minutes make a row filled while an empty `findings` does
     *     not.
     *
     * total_minutes is NULL, never 0, when nothing was recorded at all: a
     * total of 0 minutes reads as "the station never stopped", when the
     * truth is "nobody wrote it down".
     *
     * has_standard is always false — downtime_minutes has no row on
     * kernel_plant_operational_targets, whose six parameters are all
     * measurements. Published as a key so the absence is STATED rather than
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
     * Total recorded downtime minutes for one bucket (a date or a kernel
     * plant), following the same rule as downtimeOf(): null when no slot in
     * the bucket recorded anything, never 0.
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
     * The findings recap — the free-text half of what downtime answers in
     * numbers, grouped LITERALLY, with no trimming beyond the edges applied
     * in rowOf(), no case folding and no whitespace normalisation. Two
     * spellings of one thing stay two rows, and the screen says so.
     * Normalising would merge causes the writer meant to keep apart.
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
     * Ordered by date, then kernel plant, then time_slot — compared as the
     * TIME value it is, never as an integer hour.
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
            ->with(['kernelPlantDetails' => fn ($detailQuery) => $detailQuery->orderBy('time_slot')])
            ->orderBy('kernel_plant_records.date')
            ->orderBy('kernel_plant_records.kernel_plant_id')
            ->orderBy('kernel_plant_records.id');

        foreach ($query->lazy(200) as $record) {
            /** @var KernelPlantRecord $record */
            $context = array_merge($exportContext, [
                optional($record->date)->toDateString(),
                $record->kernel_plant_id,
                // Label Indonesia, bukan enum mentah.
                ExportValue::status($this->recordStatusValue($record)),
                $record->note,
            ]);

            foreach ($record->kernelPlantDetails as $detail) {
                /** @var KernelPlantDetail $detail */
                // Slot selalu HH:MM.
                $reading = [ExportValue::time($detail->time_slot)];

                foreach (KernelPlantRecordService::READING_FIELDS as $field) {
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
     * Base query over the period's Kernel Plant records (header rows).
     *
     * Scoped through `stations` to the PERIOD'S business unit and to the
     * 'kernel-plant' station type, and bounded INCLUSIVELY on
     * kernel_plant_records.date — the event date, not created_at and not the
     * sync time. kernel_plant_records carries neither period_id nor
     * business_unit_id, so the join is the only way to scope it.
     */
    protected function recordQueryFor(Period $period, ?string $productionLineId = null): Builder
    {
        $query = KernelPlantRecord::query()
            ->join('stations', 'stations.id', '=', 'kernel_plant_records.station_id')
            ->where('stations.business_unit_id', $period->business_unit_id)
            ->where('stations.type', StationTypeEnum::KernelPlant->value)
            // whereDate, bukan where mentah: suite berjalan di SQLite
            // sementara produksi PostgreSQL.
            ->whereDate('kernel_plant_records.date', '>=', $period->start_date->toDateString())
            ->whereDate('kernel_plant_records.date', '<=', $period->end_date->toDateString())
            ->select('kernel_plant_records.*');

        $this->scopeToProductionLine($query, $productionLineId);

        return $query;
    }

    /**
     * Penyaringan per production line, DI KOLOM TABEL RECORD — bukan lewat
     * join ke `stations`.
     *
     * `kernel_plant_records.production_line_id` adalah kolom nyata NOT NULL
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

        $query->where('kernel_plant_records.production_line_id', $productionLineId);
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
     * THE ADMISSION STOPS AT THE API. The WEB route /reports/kernel-plant
     * and App\Livewire\Dashboard\LaporanKernelPlant::canAccess()
     * deliberately stay without Operator — canAccess() keeps its own role
     * list precisely so this service can admit a role without dragging the
     * web page along.
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
     * DIBATASI KE MILL-NYA, dan itu bukan hiasan. Tanpa klausa
     * business_unit_id di bawah, memanggil /summary dengan
     * production_line_id milik mill LAIN menjawab 200 dengan id DAN NAMA
     * line itu — angkanya aman (scopeToProductionLine() menyaringnya ke nol
     * baris, dan query-nya tetap terkurung pada mill periode), tetapi
     * keberadaan serta nama manusiawi sebuah line yang pemanggil tidak
     * berhak melihatnya tetap terbit. Itu justru yang hendak dicegah ketika
     * line milik mill lain DIABAIKAN alih-alih ditolak 403: penolakan
     * ditahan supaya tidak memastikan line itu ada, lalu blok ini
     * memastikannya juga — lengkap dengan namanya.
     *
     * Dibuktikan lewat permintaan HTTP sungguhan sebelum klausa ini
     * ditambahkan, bukan disimpulkan dari membaca kode.
     *
     * CATATAN UNTUK SIAPA PUN YANG MENYENTUH KELUARGA LAPORAN INI: bentuk
     * tanpa scope itu ada IDENTIK di KESEPULUH report service lainnya
     * (Depricarping, Pressing, Threshing, BoilerRoom, StorageTank,
     * Clarification, Grading, Weighbridge, CagesTrack, Sterilizer), dan di
     * sana masih terbuka. Diperbaiki di sini saja karena mengubah sepuluh
     * laporan yang sudah berjalan adalah keputusan pemilik produk, bukan
     * efek samping penambahan laporan kesebelas.
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

    /** Inclusive day count of the period — both ends belong to it. */
    protected function daysInPeriod(Period $period): int
    {
        return (int) $period->start_date->copy()->startOfDay()
            ->diffInDays($period->end_date->copy()->startOfDay()) + 1;
    }

    protected function recordService(): KernelPlantRecordService
    {
        return $this->recordService ??= app(KernelPlantRecordService::class);
    }

    /**
     * Satu opsi periode UNTUK LAYAR INI. Bentuknya sengaja DATAR, karena
     * layar mobile dan blade membacanya apa adanya.
     *
     * Yang diminta layar ini bukan periode telanjang melainkan pasangan
     * (periode, kernel plant). Karena itu `status` adalah status STASIUN INI
     * di periode itu (period_stations.status).
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

    protected function recordStatusValue(KernelPlantRecord $record): string
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
                "laporan-kernel-plant_{$slug}_{$timestamp}.xlsx",
            ];
        }

        return [
            'text/csv',
            "laporan-kernel-plant_{$slug}_{$timestamp}.csv",
        ];
    }
}
