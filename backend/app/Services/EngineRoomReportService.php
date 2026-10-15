<?php

namespace App\Services;

use App\Enums\PeriodStatus;
use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Models\BusinessUnit;
use App\Models\EngineRoomDetail;
use App\Models\EngineRoomRecord;
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
 * EngineRoomReportService — screen-158--laporan-engine-room-web /
 * usecase-164--laporan-engine-room-web (Laporan Periode Engine Room).
 *
 * Shared by the API controller (App\Http\Controllers\Api\
 * EngineRoomReportController) and the Livewire component (App\Livewire\
 * Dashboard\LaporanEngineRoom) — the same split the twelve station reports
 * before it use, so the web page and the API can never disagree on a figure.
 *
 * READ-ONLY BY CONSTRUCTION: every public method is a SELECT, and the
 * /api/engine-room-reports prefix carries no POST/PUT/PATCH/DELETE.
 *
 * ------------------------------------------------------------------
 * WHAT MAKES THIS REPORT DIFFERENT FROM ITS TWELVE SIBLINGS
 * ------------------------------------------------------------------
 *  1. THIS IS THE WIDEST STATION REPORT SO FAR, and its size decides its
 *     shape. TWENTY-THREE numeric measurement columns — past Effluent
 *     Plant's fourteen and more than three times Kernel Plant's seven — all
 *     nullable and filled independently, so EVERY metric publishes its OWN
 *     denominator. Twenty-three uniform cards with no grouping are
 *     unreadable, so each entry carries a `group`
 *     (steam_turbine | diesel_gen_1 | diesel_gen_2 | electrical_sync |
 *     compressor | auxiliary) and the blade builds its six titled blocks
 *     FROM THE RESPONSE rather than from a column list nailed into the
 *     template.
 *  2. A FILLED ROW IS DECIDED OVER TWENTY-SEVEN COLUMNS, NOT TWENTY-THREE.
 *     `filled` reuses EngineRoomRecordService::isRowFilled() over its
 *     READING_FIELDS — the definition the input screens already enforce —
 *     so a slot that only wrote `findings` counts as recorded in coverage
 *     while adding to NO metric's denominator. That is why
 *     coverage.filled_slots can EXCEED every per-metric reading_count, and
 *     it is not a bug; it is stated on the screen.
 *  3. TWO PAIRS THAT MUST STAY APART, and this is the decisive difference.
 *     Genset 1 and Genset 2 carry FOUR IDENTICAL COLUMNS each — load kW,
 *     amperage, jacket water temperature, lube oil pressure — the same
 *     shape as ripple mill / kernel silo in Kernel Plant. THE EIGHT GENSET
 *     COLUMNS STAY EIGHT SEPARATE KEYS: this payload carries NO combined
 *     average, NO total, NO delta, NO imbalance, NO ratio for any of the
 *     four parallel pairs, because a combined average HIDES the load
 *     imbalance that is the whole reason those parameters are measured. One
 *     genset carrying 80% of the load while the other idles is a FINDING;
 *     averaging the two publishes it as "normal". WHERE THIS DIFFERS FROM
 *     KERNEL PLANT: there the pair shares one operational-target master
 *     row, so the response carries `shares_standard_with`. HERE THERE IS NO
 *     MASTER TARGET AT ALL, so there is no such key and the screen's note
 *     states the PHYSICAL parallelism of the two machines — never a shared
 *     standard.
 *  4. TWO CATEGORICAL STATUS COLUMNS WITH THREE VALUES, not two.
 *     diesel_gen_1_status and diesel_gen_2_status are each run | standby |
 *     off, and 'STANDBY' IS NOT 'OFF': a standby genset is ready to take
 *     load, a dead one is not. Folding standby into off would report a
 *     generator outage that never happened — a wrong conclusion about the
 *     mill's readiness. Both are published as a DISTRIBUTION of slot counts
 *     per value (lowest/average/highest have no meaning for a categorical
 *     column), with known values that never occurred filled in as 0, and
 *     NULL slots on a SEPARATE `not_recorded` key that is never folded into
 *     run, standby OR off. Shares use recorded_slots as the denominator,
 *     never expected_slots.
 *  5. ONE COLUMN THAT IS NOT TREATED LIKE THE OTHER TWENTY-TWO:
 *     daily_energy_export_kwh. Its name says 'daily' while it is stored PER
 *     TIME SLOT, and that mismatch is unexplained in every source — not the
 *     code, not the six existing Engine Room screen specs. Two readings are
 *     equally plausible: (a) a CUMULATIVE DAILY METER re-recorded every
 *     slot, or (b) the energy exported DURING that slot, with 'daily' a
 *     misnomer. Under (a), averaging or summing it across 24 slots yields a
 *     number that looks entirely valid and is entirely wrong. So this
 *     report publishes NO PERIOD AVERAGE for it at all: it is absent from
 *     `metrics` and published on its own `daily_energy_export` key as a
 *     per-date series of the LAST value of that day — safe under BOTH
 *     readings — with the uncertainty stated on screen instead of one
 *     reading being chosen silently.
 *  6. THERE IS NO DOWNTIME BLOCK. engine_room_details has no downtime
 *     column at all; the absence is deliberate, stated in the payload
 *     (`_note_absences`) and on the screen, so it does not read as a
 *     section that failed to load.
 *  7. THERE IS NO THRESHOLD FLAGGING OF ANY KIND, and that statement is
 *     harder here than in any report before it. Engine Room has NO
 *     operational-target master table, while several of its metrics have
 *     limits that are VERY widely known OUTSIDE this application — 50 Hz
 *     system frequency, power factor, nominal busbar voltage — so the
 *     temptation to flag a deviation is greatest exactly here. Deriving a
 *     threshold from the period's own data would produce a statistic that
 *     reads like an ELECTRICAL SAFETY LIMIT. Not one key in this payload
 *     says out_of_range, threshold, target, violation or severity.
 *
 * ------------------------------------------------------------------
 * ALL AGGREGATION HAPPENS IN PHP — none of it in SQL
 * ------------------------------------------------------------------
 * One query plus an eager load, then everything in PHP: no AVG/MIN/MAX/SUM,
 * no selectRaw, no DB::raw, no driver-specific date function. SQL aggregate
 * behaviour over NULLABLE columns is NOT identical between SQLite (the test
 * suite) and PostgreSQL (production), and the separate-denominator and
 * null-is-not-zero rules above are exactly what gets lost in that
 * difference. WITH TWENTY-THREE NULLABLE COLUMNS THIS IS THE LARGEST SUCH
 * EXPOSURE IN THE CODEBASE.
 *
 * ------------------------------------------------------------------
 * ACCESS: OPERATOR IS ADMITTED FROM THE FIRST COMMIT
 * ------------------------------------------------------------------
 * The three DATA routes (/periods, /summary, /export) admit Operator from
 * the first commit, because screen-159--laporan-engine-room-mobile is built
 * in the SAME series — the Grading / Kernel Plant precedent, not the
 * Weighbridge / Effluent Plant one where the web screen landed first and
 * Operator had to be deferred and then added. Choosing the first precedent
 * avoids an expensive ripple: three test files whose assertions have to be
 * inverted and four artifact fields that have to be updated.
 *
 * THAT ADMISSION TOUCHES THREE PLACES AT ONCE, and that is the whole point:
 * the role list in routes/api.php, the role list in guardAccess(), AND the
 * MILL-BOUND branch of resolveBusinessUnit(). Widening the first two alone
 * would drop Operator into the Admin branch, where a client-supplied
 * business_unit_id IS honoured — an Operator could then read any mill's
 * report. That is a cross-mill leak, not a display defect.
 *
 * WHAT STAYS CLOSED: businessUnitOptions() still refuses Operator,
 * Supervisor and Mill Management with 403 (Admin only, raised HERE inside
 * the service rather than by the route middleware, precisely because this
 * prefix's middleware admits four roles for the sake of the three data
 * routes), and the WEB route /reports/engine-room is NOT widened — API
 * access for the mobile twin must not widen the web screen.
 *
 * ------------------------------------------------------------------
 * CROSS-MILL SECURITY IS CLOSED AT THREE DIFFERENT POINTS, on purpose
 * ------------------------------------------------------------------
 *   1. resolveBusinessUnit() IGNORES the client's business_unit_id for
 *      Operator / Supervisor / Mill Management — not validated, not
 *      compared, discarded. Probing another mill's id returns 200 with the
 *      CALLER'S OWN data, deliberately not a 403: a 403 would confirm the
 *      other mill exists, and there is no access attempt to refuse because
 *      the parameter is never used for those roles.
 *   2. resolveProductionLine() REFUSES a line belonging to another mill
 *      with 403, and a MISSING line with 422 — both BEFORE a single
 *      engine_room_records query runs. The WEB screen calls
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
class EngineRoomReportService
{
    /**
     * Export row ceiling, counted in EXPORTED LINES (= engine_room_details
     * rows), not header records: one daily record carries up to 24 time-slot
     * rows, so counting headers would sail 24x past the real limit.
     *
     * Read through `static::` below so a unit test can lower it in a
     * subclass instead of seeding fifty thousand rows.
     */
    public const EXPORT_ROW_LIMIT = 50000;

    /**
     * Jenis stasiun yang dilaporkan layar ini — kunci baris `period_stations`
     * yang statusnya dipakai di seluruh payload layar ini.
     *
     * BERTANDA HUBUNG ('engine-room'), karena itulah yang disimpan
     * StationTypeEnum::EngineRoom->value dan tabel master `station_types`.
     * Kunci snake_case akan ter-compile, tampak benar, dan tidak pernah cocok
     * dengan satu baris master pun — daftar periodenya lalu kosong untuk
     * setiap mill tanpa satu galat.
     */
    protected const STATION_TYPE = StationTypeEnum::EngineRoom->value;

    /** Export formats this report understands. Anything else is 422. */
    public const SUPPORTED_FORMATS = ['csv', 'excel'];

    /**
     * THE TWENTY-TWO NUMERIC METRICS THAT PUBLISH A PERIOD AVERAGE, in the
     * order the six layout blocks on the screen read them in.
     *
     * TWENTY-TWO, NOT TWENTY-THREE: `daily_energy_export_kwh` is the
     * twenty-third measurement column and is DELIBERATELY ABSENT from this
     * list — see ENERGY_EXPORT_METRIC and energyExportSeriesOf(). Adding it
     * here is the single easiest way to break this report: it would publish
     * a period average for a column whose semantics are unverified, and that
     * average would look entirely valid.
     *
     * Every one is nullable, and every one is averaged over ITS OWN non-null
     * rows — see metricsOf(). The two status columns and the two free-text
     * columns are deliberately absent too; see STATUS_COLUMNS and
     * FREE_TEXT_COLUMNS.
     */
    public const NUMERIC_METRICS = [
        // Turbin Uap (6)
        'steam_turbine_inlet_pressure_bar',
        'steam_turbine_inlet_temp_c',
        'steam_turbine_exhaust_pressure_bar',
        'steam_turbine_rpm',
        'steam_turbine_alternator_bearing_temp_1_c',
        'steam_turbine_alternator_bearing_temp_2_c',
        // Genset 1 (4)
        'diesel_gen_1_load_kw',
        'diesel_gen_1_amperage_a',
        'diesel_gen_1_jacket_water_temp_c',
        'diesel_gen_1_lube_oil_pressure_bar',
        // Genset 2 (4) — THE SAME FOUR COLUMNS, and they stay separate.
        'diesel_gen_2_load_kw',
        'diesel_gen_2_amperage_a',
        'diesel_gen_2_jacket_water_temp_c',
        'diesel_gen_2_lube_oil_pressure_bar',
        // Kelistrikan Tersinkron (4)
        'electrical_sync_total_factory_load_kw',
        'electrical_sync_system_frequency_hz',
        'electrical_sync_power_factor',
        'electrical_sync_busbar_voltage_v',
        // Kompresor (2) — named inconsistently in the database on purpose
        // (see the open question); the service does NOT normalise or merge
        // the two names, and the screen's labels are written by hand rather
        // than derived from them.
        'air_compressor_1_pressure_bar',
        'compressor_2_pressure_bar',
        // Penunjang (2 of 3 — the third is daily_energy_export_kwh)
        'battery_charger_ups_voltage_v',
        'fuel_tank_level',
    ];

    /**
     * Which titled block each metric belongs to. PUBLISHED ON EVERY METRIC
     * ENTRY so the blade composes its six blocks from the RESPONSE rather
     * than from a hardcoded column list — a column added by a later
     * migration then lands in the right block without editing the blade,
     * and twenty-three hand-written cards are twenty-three chances to lose a
     * denominator.
     *
     * PURELY LAYOUT: not one figure is computed per block and there is no
     * per-block total — units differ even inside a block.
     *
     * @var array<string, string>
     */
    public const METRIC_GROUPS = [
        'steam_turbine_inlet_pressure_bar' => 'steam_turbine',
        'steam_turbine_inlet_temp_c' => 'steam_turbine',
        'steam_turbine_exhaust_pressure_bar' => 'steam_turbine',
        'steam_turbine_rpm' => 'steam_turbine',
        'steam_turbine_alternator_bearing_temp_1_c' => 'steam_turbine',
        'steam_turbine_alternator_bearing_temp_2_c' => 'steam_turbine',
        'diesel_gen_1_load_kw' => 'diesel_gen_1',
        'diesel_gen_1_amperage_a' => 'diesel_gen_1',
        'diesel_gen_1_jacket_water_temp_c' => 'diesel_gen_1',
        'diesel_gen_1_lube_oil_pressure_bar' => 'diesel_gen_1',
        'diesel_gen_2_load_kw' => 'diesel_gen_2',
        'diesel_gen_2_amperage_a' => 'diesel_gen_2',
        'diesel_gen_2_jacket_water_temp_c' => 'diesel_gen_2',
        'diesel_gen_2_lube_oil_pressure_bar' => 'diesel_gen_2',
        'electrical_sync_total_factory_load_kw' => 'electrical_sync',
        'electrical_sync_system_frequency_hz' => 'electrical_sync',
        'electrical_sync_power_factor' => 'electrical_sync',
        'electrical_sync_busbar_voltage_v' => 'electrical_sync',
        'air_compressor_1_pressure_bar' => 'compressor',
        'compressor_2_pressure_bar' => 'compressor',
        'battery_charger_ups_voltage_v' => 'auxiliary',
        'fuel_tank_level' => 'auxiliary',
    ];

    /**
     * THE TWENTY-THIRD MEASUREMENT COLUMN, AND THE ONE THAT IS HANDLED
     * DIFFERENTLY. It is NOT in NUMERIC_METRICS and has NO period average
     * anywhere in the payload; it is published on `daily_energy_export` as a
     * per-date series of the LAST value of that day. Full reasoning in the
     * class docblock (#5) and in energyExportSeriesOf().
     */
    public const ENERGY_EXPORT_METRIC = 'daily_energy_export_kwh';

    /**
     * THE TWO CATEGORICAL STATUS COLUMNS and their KNOWN value sets — THREE
     * values each, not two.
     *
     * The sets really are closed in the database: both columns are
     * `$table->enum(['run', 'standby', 'off'])`, which on PostgreSQL is a
     * varchar WITH a CHECK constraint, and on SQLite a CHECK constraint too.
     *
     * They are listed here anyway, and statusDistributionOf() still groups
     * the values that are actually present LITERALLY before filling the
     * missing known ones with 0: literal grouping means a value added by a
     * later migration cannot silently disappear from the report, and the
     * zero-fill means a known value that never occurred still renders as a
     * zero row rather than as a missing one.
     *
     * 'STANDBY' IS NEVER FOLDED INTO 'OFF', and NULL is never folded into
     * any of the three. NEITHER column ever appears in `metrics` or in
     * `daily_trend`.
     */
    public const STATUS_COLUMNS = [
        'diesel_gen_1_status' => ['run', 'standby', 'off'],
        'diesel_gen_2_status' => ['run', 'standby', 'off'],
    ];

    /**
     * NEVER AGGREGATED, NEVER MERGED. The two free-text columns are
     * published as two SEPARATE lists of {date, time_slot, text};
     * action_taken_maintenance_remark answers "what was done", findings
     * answers "what was seen". A slot filling both appears in BOTH, which is
     * correct — merging them would count that slot twice inside one list.
     */
    public const FREE_TEXT_COLUMNS = [
        'action_taken_maintenance_remark',
        'findings',
    ];

    /**
     * THE ONLY DAILY TREND, and it is an AVERAGE per date — deliberately a
     * DIFFERENT aggregation from `daily_energy_export`, which carries the
     * LAST value of the day. The difference is stated on the screen so the
     * two charts cannot read as the same kind of figure. An average is
     * legitimate here because total factory load is an instantaneous
     * quantity per slot, not a cumulative meter.
     */
    public const TREND_METRICS = [
        'electrical_sync_total_factory_load_kw',
    ];

    /**
     * Export column headers — context columns first, repeated verbatim on
     * every line, then the time slot, then ALL TWENTY-SEVEN columns in
     * EngineRoomRecordService::READING_FIELDS order, the two status columns
     * and the two free-text ones included.
     *
     * THE ORDER IS READING_FIELDS' ORDER, not NUMERIC_METRICS' — the two
     * genset status columns sit inside their genset's block there, and the
     * export mirrors the input screen's column order rather than the
     * report's layout order.
     *
     * @var array<int, string>
     */
    public const EXPORT_HEADER = [
        // Kolom konteks diulang di setiap baris, sama dengan ekspor dua
        // belas laporan periode sebelumnya (temuan audit 2026-10-04 #8b).
        'Periode',
        'Mill',
        'Production Line',
        'Tanggal',
        'Unit Engine Room',
        'Status',
        'Catatan',
        'Slot Waktu',
        'Tekanan Masuk Turbin Uap (bar)',
        'Suhu Masuk Turbin Uap (C)',
        'Tekanan Buang Turbin Uap (bar)',
        'Putaran Turbin Uap (rpm)',
        'Suhu Bearing Alternator 1 (C)',
        'Suhu Bearing Alternator 2 (C)',
        'Status Genset 1',
        'Beban Genset 1 (kW)',
        'Ampere Genset 1 (A)',
        'Suhu Jacket Water Genset 1 (C)',
        'Tekanan Lube Oil Genset 1 (bar)',
        'Status Genset 2',
        'Beban Genset 2 (kW)',
        'Ampere Genset 2 (A)',
        'Suhu Jacket Water Genset 2 (C)',
        'Tekanan Lube Oil Genset 2 (bar)',
        'Beban Total Pabrik (kW)',
        'Frekuensi Sistem (Hz)',
        'Power Factor',
        'Tegangan Busbar (V)',
        'Tekanan Kompresor Udara 1 (bar)',
        'Tekanan Kompresor 2 (bar)',
        'Tegangan UPS / Battery Charger (V)',
        // TANPA SATUAN, dan itu disengaja: kolomnya satu-satunya kolom
        // numerik stasiun ini tanpa satuan di namanya, dan persen atau liter
        // belum jelas (pertanyaan terbuka). Mengarang satuan di judul kolom
        // ekspor adalah cara tercepat agar angka itu salah dibaca di luar
        // aplikasi.
        'Level Tangki Bahan Bakar',
        // DIEKSPOR APA ADANYA PER SLOT: ekspor adalah rincian mentah, jadi
        // perlakuan 'nilai terakhir per tanggal' berlaku pada /summary saja.
        'Ekspor Energi Harian (kWh)',
        'Catatan Tindakan Perawatan',
        'Temuan',
    ];

    /**
     * code => name from the `station_types` master table, memoised per
     * service instance (one request / one Livewire render).
     *
     * @var array<string, string>|null
     */
    protected ?array $stationTypeNames = null;

    protected ?EngineRoomRecordService $recordService = null;

    /**
     * slot label => its position on the canonical grid, memoised.
     *
     * @var array<string, int>|null
     */
    protected ?array $slotOrder = null;

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
     * OPERATOR BELONGS IN THE MILL-BOUND BRANCH BELOW AND NOWHERE ELSE, and
     * that single line is what makes admitting Operator in guardAccess() /
     * routes/api.php safe. Widening those role lists WITHOUT this line drops
     * Operator into the Admin branch, where the client's business_unit_id IS
     * honoured: an Operator could then read ANY mill's report. That is a
     * cross-mill leak, not a display defect.
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
            // business_unit_id.
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
     * not by the route middleware — PRECISELY BECAUSE this prefix's
     * middleware admits four roles for the sake of the three data routes, so
     * a guard placed in the middleware would not close this route at all.
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
     * built, and a spy that records zero calls to this method is that proof.
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
     * BOTH cases ZERO engine_room_records queries have run.
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
     * business_logic step 7 — load a period and prove the caller may read it.
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
     * A period covers Engine Room when it HAS a `period_stations` row for
     * station_type 'engine-room'. Newest first. An empty array is a valid
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
     * business_logic steps 1-28 — every figure on the screen for one period
     * on one production line: the period header, recording coverage FIRST,
     * the twenty-two metrics each with its own denominator and its own
     * `group`, the energy-export series (LAST value per date, no period
     * average), the two three-valued status distributions, the two separate
     * free-text lists, the one daily trend (an AVERAGE per date), the
     * per-unit recap, the daily recap, and the period totals.
     *
     * Guard order is deliberate: role (403) -> mill (422) -> production line
     * (422 / 403) -> period id (422) -> the period itself (404 / 403). A
     * caller who may not be here at all never learns which period ids exist,
     * and an incomplete request never turns into a 404/403 about a period it
     * was never entitled to ask about.
     *
     * Membership is decided by engine_room_records.date — the date the
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
        // 422 when absent, 403 when it belongs to another mill — and in BOTH
        // cases zero engine_room_records queries have run by this point. The
        // line guard runs BEFORE the period guard on purpose.
        $productionLineId = $this->resolveProductionLine($businessUnitId, $productionLineId);

        $period = $this->requirePeriod($period);

        // ONE query for the whole period+line, details eager-loaded.
        // Everything below is PHP.
        $records = $this->recordsFor($period, $productionLineId);
        $rows = $this->rowsOf($records);
        // "Filled rows" are the only ones that count. A slot whose 27
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
        // sendiri (EngineRoomRecordService::canonicalTimeSlots()), bukan
        // angka yang dikarang di sini — satu definisi, satu jawaban.
        $slotsPerUnitPerDay = count(EngineRoomRecordService::canonicalTimeSlots());
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
                'engine_room_unit_count' => $unitCount,
                'slots_per_unit_per_day' => $slotsPerUnitPerDay,
                'days_in_period' => $daysInPeriod,
                'days_counted' => $daysCounted,
                'period_running' => ReportPeriodDays::isRunning($period),
            ],
            // THE HEART OF THIS REPORT: twenty-two metrics, twenty-two
            // denominators, each tagged with the block it belongs to.
            // daily_energy_export_kwh IS NOT HERE — see below.
            'metrics' => $this->metricsOf($filledRows),
            // PUBLISHED SEPARATELY, and this is the single most
            // easily-mis-implemented decision on this screen. No period
            // average, no sum: the LAST value of each date, taken from the
            // slot's POSITION ON THE CANONICAL GRID (07:00..06:00) rather
            // than from a lexical hour comparison.
            'daily_energy_export' => $this->energyExportOf($dates),
            // Kategoris: sebaran jumlah slot per nilai, TIGA nilai dikenal
            // masing-masing. TIDAK PERNAH min/avg/max, TIDAK PERNAH tren.
            'status_distribution' => $this->statusDistributionOf($filledRows),
            // DUA DAFTAR TERPISAH. Satu slot yang mengisi keduanya muncul di
            // kedua daftar, dan itu benar.
            'maintenance_remarks' => $this->textEntriesOf($filledRows, 'action_taken_maintenance_remark'),
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
            '_note_absences' => 'TIDAK ADA kunci downtime — engine_room_details tidak punya satu pun '
                .'kolomnya, dan ketiadaan itu disengaja, bukan terlewat. TIDAK ADA kunci target maupun '
                .'penanda di luar batas — stasiun Engine Room tidak punya tabel master target, dan itu '
                .'berlaku MESKI frekuensi sistem, power factor, dan tegangan busbar punya batas yang '
                .'dikenal sangat luas di luar aplikasi: ambang yang diturunkan dari data periode itu '
                .'sendiri hanya turunan statistik, sementara pembaca akan membacanya sebagai batas '
                .'keselamatan kelistrikan. TIDAK ADA kunci gabungan, selisih, maupun rasio antar kedua '
                .'genset: kedelapan kolomnya tetap berdiri sendiri, karena rata-rata gabungan '
                .'menyembunyikan ketidakseimbangan beban yang justru menjadi alasan parameter itu '
                .'diukur — dan kesejajaran yang dinyatakan di layar adalah kesejajaran FISIK kedua '
                .'mesin, bukan standar bersama, sebab stasiun ini tidak punya master target. Kedua '
                .'kolom status TIDAK PERNAH muncul di `metrics` maupun `daily_trend`. Dan '
                .'daily_energy_export_kwh TIDAK punya rata-rata periode sama sekali: ia diterbitkan di '
                .'`daily_energy_export` sebagai nilai TERAKHIR per tanggal, karena namanya menyebut '
                .'\'daily\' sementara ia disimpan per slot waktu dan semantiknya belum terjelaskan.',
        ];
    }

    /**
     * Repo-convention alias of buildSummary(), so this service reads the
     * same way as its twelve siblings at the call sites. One implementation,
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
     * business_logic step 29 — ONE EXPORTED LINE PER TIME SLOT
     * (engine_room_details row), with the period / mill / production line /
     * date / unit / status / note context columns repeated verbatim on every
     * line so the file can be pivoted straight in a spreadsheet.
     *
     * ALL TWENTY-SEVEN reading columns are emitted, the two status columns
     * and the two free-text ones included — dropping either would make the
     * export unable to stand in for the report, which is the whole point of
     * exporting it. An empty cell stays EMPTY: never 0, never '-'.
     *
     * daily_energy_export_kwh IS EXPORTED AS IT STANDS, per slot: the export
     * is the raw detail, so the 'last value per date' treatment belongs to
     * /summary and nowhere else.
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
        $detailRowCount = EngineRoomDetail::query()
            ->whereIn('engine_room_record_id', (clone $recordQuery)->select('engine_room_records.id'))
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
     * business_logic step 29 — the streamed file around buildExportRows().
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
    // WITH TWENTY-THREE NULLABLE COLUMNS THIS IS THE LARGEST SUCH EXPOSURE
    // OF ANY REPORT IN THIS CODEBASE. No avg()/sum()/groupBy()/selectRaw is
    // issued at the SQL layer anywhere below; the queries only ever select
    // raw rows, filter them, and order them. The row set is bounded by one
    // reporting period; the export path — the only unbounded one — streams
    // in chunks instead.
    // ------------------------------------------------------------------

    /**
     * Every Engine Room record inside the period+line, flattened to plain
     * objects with their time-slot detail rows attached, ascending by
     * time_slot.
     *
     * ONE DATE CAN HAVE SEVERAL RECORDS — one per Engine Room unit, because
     * engine_room_id is a STRING column on the HEADER, not a foreign key
     * (the same shape as boiler_room_id and effluent_plant_id). The per-date
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
            ->with(['engineRoomDetails' => fn ($query) => $query->orderBy('time_slot')])
            ->orderBy('engine_room_records.date')
            ->orderBy('engine_room_records.engine_room_id')
            ->get()
            ->map(fn (EngineRoomRecord $record) => (object) [
                'date' => $this->dateStringOf($record->date),
                'engine_room_id' => (string) ($record->engine_room_id ?? ''),
                'status' => $this->recordStatusValue($record),
                'note' => $record->note,
                'rows' => $record->engineRoomDetails
                    ->map(fn (EngineRoomDetail $detail) => $this->rowOf($detail, $record))
                    ->values(),
            ])
            ->values();
    }

    /**
     * One detail row, reduced to exactly what the aggregation needs.
     *
     * `filled` REUSES EngineRoomRecordService::isRowFilled() over its
     * READING_FIELDS — ALL TWENTY-SEVEN of them, the definition the input
     * screens already enforce, and the reason both were made public. Writing
     * a second "is this row filled?" rule here, or re-listing the
     * twenty-seven columns in this file, is how the report and the form
     * start disagreeing about what was recorded. Note that it is
     * TWENTY-SEVEN, not the twenty-two in NUMERIC_METRICS and not
     * twenty-three: a slot that only wrote `findings` counts as recorded and
     * adds to no metric's denominator.
     *
     * `slot_order` is the slot's POSITION ON THE CANONICAL GRID, and it is
     * what the energy-export series orders by. Not a lexical hour
     * comparison: the grid starts at 07:00 and ends at 06:00 the NEXT day,
     * so sorting the labels as strings makes 06:00 the EARLIEST slot of the
     * day when it is in fact the LAST — and the resulting figure would look
     * perfectly plausible.
     */
    protected function rowOf(EngineRoomDetail $detail, EngineRoomRecord $record): object
    {
        $attributes = $detail->only(EngineRoomRecordService::READING_FIELDS);

        $values = [];

        // The twenty-two metrics PLUS the energy-export column: the latter
        // is read here (the series needs its values) and simply never
        // reaches metricsOf()/averagesOf(), which iterate NUMERIC_METRICS.
        foreach (array_merge(self::NUMERIC_METRICS, [self::ENERGY_EXPORT_METRIC]) as $metric) {
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

        $timeSlot = (string) $detail->time_slot;

        return (object) [
            'date' => $this->dateStringOf($record->date),
            'engine_room_id' => (string) ($record->engine_room_id ?? ''),
            'time_slot' => $timeSlot,
            'time_slot_label' => ExportValue::time($timeSlot) ?? $timeSlot,
            'slot_order' => $this->slotOrderOf($timeSlot),
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
     * TWENTY-TWO ENTRIES, TWENTY-TWO DENOMINATORS, each tagged with the
     * titled block it belongs to.
     *
     * A metric is averaged over ONLY the rows where that column is non-null,
     * and reports how many rows that was. There is deliberately NO shared
     * reading_count anywhere in the payload — a single shared denominator
     * would deflate every rarely-filled metric while still producing a
     * number that looks entirely reasonable, and it would be wrong for at
     * least twenty-one of the twenty-two.
     *
     * A metric never filled in the whole period returns null/null/null with
     * reading_count 0 — NOT 0/0/0, which reads as "we measured it and it was
     * zero".
     *
     * THE EIGHT GENSET COLUMNS COME BACK AS EIGHT SEPARATE ENTRIES and
     * nothing else: no combined average, no total, no delta, no imbalance,
     * no ratio. `group` marks them as two blocks and that is the only
     * relationship published.
     *
     * daily_energy_export_kwh IS NOT IN HERE. See ENERGY_EXPORT_METRIC.
     *
     * @param  Collection<int, object>  $filledRows
     * @return array<string, array{min: float|null, avg: float|null, max: float|null, reading_count: int, group: string}>
     */
    protected function metricsOf(Collection $filledRows): array
    {
        $metrics = [];

        foreach (self::NUMERIC_METRICS as $metric) {
            $metrics[$metric] = $this->statsOf($this->valuesOf($filledRows, $metric))
                + ['group' => self::METRIC_GROUPS[$metric]];
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
            'min' => round(min($values), 3),
            'avg' => round(array_sum($values) / count($values), 3),
            'max' => round(max($values), 3),
            'reading_count' => count($values),
        ];
    }

    /**
     * Per-metric averages inside one bucket (a date or a unit), each with
     * ITS OWN reading_count inside that bucket.
     *
     * avg is null — never 0 — when the bucket holds no value at all for that
     * metric. TWENTY-TWO entries: the energy-export column is not averaged
     * per date either, and the daily recap reads its cell from the
     * `daily_energy_export` series instead.
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
                    : round(array_sum($values) / count($values), 3),
                'reading_count' => count($values),
            ];
        }

        return $averages;
    }

    /**
     * THE TWO STATUS DISTRIBUTIONS, and the invariant that proves each of
     * them:
     *
     *   sum(counts) + not_recorded === recorded_slots === filled rows
     *
     * Built by GROUPING THE VALUES THAT ARE ACTUALLY PRESENT first, then
     * filling in the known values that never occurred with 0 — so a value
     * added by a later migration cannot silently vanish from the report,
     * while a known value that never happened still renders as a zero row
     * rather than as a missing one. "Never on standby" must not read the
     * same as "not reported".
     *
     * not_recorded counts filled slots whose status column is NULL, on its
     * OWN key. IT IS NEVER FOLDED INTO 'run', 'standby' OR 'off'. And
     * 'STANDBY' IS NEVER FOLDED INTO 'OFF': a standby genset is ready to
     * take load, a dead one is not, and folding them would report a
     * generator outage that never happened.
     *
     * recorded_slots is the number of FILLED SLOTS — the denominator the
     * screen uses for each value's share. expected_slots would mix untouched
     * slots into the share of a value that WAS recorded.
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
                'time_slot' => $row->time_slot_label,
                'text' => (string) $row->texts[$column],
            ])
            ->values()
            ->all();
    }

    /**
     * One aggregate per DATE that has at least one record, ascending,
     * merging every Engine Room unit that ran on that date.
     *
     * A date whose rows are all empty for a given metric yields null in that
     * column but STILL COUNTS as a date with records — dropping it would
     * make the period look better recorded than it was. A date with no
     * record at all gets no entry: a padded zero row would read as "we
     * measured nothing" when the mill simply did not run.
     *
     * `energy_export` carries THE LAST VALUE OF THAT DATE, never an average
     * — a deliberately different aggregation from `averages`, and the one
     * the screen states the difference about.
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
                    'energy_export' => $this->lastEnergyValueOf($filled),
                ];
            })
            ->values();
    }

    /**
     * One aggregate per ENGINE ROOM UNIT (engine_room_id) over every record
     * in the period.
     *
     * engine_room_id is a STRING column on the header, so one line can carry
     * several units. A unit that has a record but not one filled reading
     * STILL APPEARS, with reading_rows 0 and null averages, and still counts
     * towards engine_room_unit_count so expected_slots stays correct.
     * Dropping it would hide exactly the unit that was never written down.
     *
     * @param  Collection<int, object>  $records
     * @return Collection<int, object>
     */
    protected function unitsOf(Collection $records): Collection
    {
        return $records
            ->groupBy('engine_room_id')
            ->sortKeys()
            ->map(function (Collection $group, string $engineRoomId) {
                $rows = $group->flatMap(fn ($record) => $record->rows)->values();
                $filled = $rows->filter(fn ($row) => $row->filled)->values();
                $dates = $filled->pluck('date')->unique()->values();

                return (object) [
                    'engine_room_id' => $engineRoomId,
                    'reading_rows' => $filled->count(),
                    'days_with_rows' => $dates->count(),
                    'metrics' => $this->metricsOf($filled),
                    // LAST value for this unit, not an average for this unit
                    // — same rule as the per-date series, scoped to the unit.
                    'energy_export' => $this->lastEnergyValueOf($filled),
                ];
            })
            ->values();
    }

    /**
     * THE ENERGY-EXPORT SERIES — one point per date that actually filled
     * `daily_energy_export_kwh`, carrying the LAST value of that date and
     * the TIME SLOT it came from so the reader can check it.
     *
     * WHY IT IS NOT A PERIOD AVERAGE, and why this method exists at all: the
     * column's name says 'daily' while it is stored PER TIME SLOT, and that
     * mismatch is unexplained in every source. Under the reading "cumulative
     * daily meter", averaging or summing it across 24 slots produces a
     * number that looks entirely valid and is entirely wrong. The last value
     * of the day is safe under BOTH readings, so that is what is published,
     * and the uncertainty is stated on the screen rather than one reading
     * being chosen silently.
     *
     * A VALUE THAT FALLS inside one day (impossible if the column really is
     * a cumulative meter) is published AS IT STANDS — no correction, no
     * anomaly flag. That state is useful evidence for answering the open
     * question, not an error.
     *
     * has_data is false with an EMPTY series when the column was never
     * filled in the whole period: the screen then says there is no data
     * rather than drawing a flat line at zero.
     *
     * @param  Collection<int, object>  $dates
     * @return array{series: list<array{date: string, last_value: float|null, time_slot: string|null, reading_count: int}>, has_data: bool}
     */
    protected function energyExportOf(Collection $dates): array
    {
        $series = $dates
            // Only dates that actually filled THIS column get a point: a
            // point with value 0 would claim a meter reading nobody took.
            ->filter(fn ($row) => $row->energy_export['reading_count'] > 0)
            ->map(fn ($row) => [
                'date' => $row->date,
                'last_value' => $row->energy_export['last_value'],
                'time_slot' => $row->energy_export['time_slot'],
                'reading_count' => $row->energy_export['reading_count'],
            ])
            ->values()
            ->all();

        return [
            'series' => $series,
            'has_data' => $series !== [],
        ];
    }

    /**
     * The LAST filled `daily_energy_export_kwh` inside one bucket, with the
     * slot it came from and how many slots in that bucket filled the column.
     *
     * "LAST" IS TAKEN FROM THE SLOT'S POSITION ON THE CANONICAL GRID, and
     * for a multi-date bucket from (date, slot position) — NEVER from a
     * lexical hour comparison. The grid runs 07:00 .. 06:00 THE NEXT DAY, so
     * sorting the hour labels as strings picks 06:00 as the EARLIEST slot
     * when it is in fact the LAST one, and the resulting figure looks
     * perfectly plausible. This is the single trap this method exists to
     * avoid.
     *
     * A null in the last slot is SKIPPED rather than treated as the day's
     * value: the answer is the last slot that actually HAS a value.
     *
     * @param  Collection<int, object>  $filledRows
     * @return array{last_value: float|null, date: string|null, time_slot: string|null, reading_count: int}
     */
    protected function lastEnergyValueOf(Collection $filledRows): array
    {
        $withValue = $filledRows
            ->filter(fn ($row) => $row->values[self::ENERGY_EXPORT_METRIC] !== null)
            ->values();

        if ($withValue->isEmpty()) {
            return ['last_value' => null, 'date' => null, 'time_slot' => null, 'reading_count' => 0];
        }

        $last = $withValue
            // One comparator, two keys: date ascending, then canonical slot
            // POSITION ascending, so the final element is the last reading of
            // the bucket. Note this is a single sort() with a comparator —
            // Laravel's sortBy([closure, closure]) is NOT that API:
            // Collection::sortByMany() treats a callable as a comparator
            // ($a, $b), not as a value extractor, so a pair of extractor
            // closures silently REVERSES the collection and ->last() returns
            // the FIRST row of the eager-load order (lexical hour) — which is
            // exactly the 06:00 trap this method exists to avoid.
            ->sort(fn ($a, $b) => strcmp($a->date, $b->date)
                ?: $a->slot_order <=> $b->slot_order)
            ->last();

        return [
            'last_value' => round((float) $last->values[self::ENERGY_EXPORT_METRIC], 3),
            'date' => $last->date,
            'time_slot' => $last->time_slot_label,
            'reading_count' => $withValue->count(),
        ];
    }

    /**
     * THE ONE DAILY TREND — average total factory load per date, each point
     * carrying its own reading_count.
     *
     * AN AVERAGE, deliberately a DIFFERENT aggregation from the
     * energy-export series next to it on screen, which carries the LAST
     * value of the day. The difference is stated on the screen so the two
     * cannot read as the same kind of figure.
     *
     * A date whose rows never filled the charted column yields avg null, NOT
     * 0: a zero point would claim a measurement that was never made, and the
     * date still counts as a date with records.
     *
     * The two status columns can never appear here — they are categorical.
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
     * ROW (business_logic step 28). A date with records but no filled row at
     * all still counts in total.days_with_records; it simply has no figures
     * to print.
     *
     * `energy_export` is published beside `metrics_avg`, NOT inside it: the
     * daily recap's energy column carries the LAST value of that date, while
     * every other column on that row is an average. Two aggregations, two
     * keys, so neither can be read as the other.
     *
     * @param  Collection<int, object>  $dates
     * @return list<array{date: string, reading_rows: int, metrics_avg: array<string, array{avg: float|null, reading_count: int}>, energy_export: array{last_value: float|null, date: string|null, time_slot: string|null, reading_count: int}}>
     */
    protected function dailyOf(Collection $dates): array
    {
        return $dates
            ->filter(fn ($row) => $row->reading_rows > 0)
            ->map(fn ($row) => [
                'date' => $row->date,
                'reading_rows' => $row->reading_rows,
                'metrics_avg' => $row->averages,
                'energy_export' => $row->energy_export,
            ])
            ->values()
            ->all();
    }

    /**
     * The per-unit recap table. `metrics` carries the SAME SHAPE as the
     * period-level `metrics`, scoped to this unit — twenty-two entries, each
     * with its own denominator and its own `group`.
     *
     * `reading_count` is published BESIDE `reading_rows` with the same
     * value: the two names are used interchangeably across the twelve
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
                'engine_room_id' => $row->engine_room_id,
                'reading_rows' => $row->reading_rows,
                'reading_count' => $row->reading_rows,
                'days_with_rows' => $row->days_with_rows,
                'metrics' => $row->metrics,
                'energy_export' => $row->energy_export,
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
     * THE TWO MAPPERS THAT MUST NOT BE SWAPPED: the context 'Status' column
     * is the RECORD's status and uses ExportValue::status()
     * (Tersimpan/Tersinkron), while the two GENSET status columns use
     * ExportValue::option() (Display::OPTION_LABELS — Run/Standby/Off) so
     * they read identically to the Detail screen. The two helpers are named
     * alike, and swapping them produces labels that look right until they
     * are compared with that screen.
     *
     * The two free-text columns pass through verbatim. Empty cells stay
     * empty. daily_energy_export_kwh goes out AS IT STANDS per slot.
     *
     * @return Generator<int, array<int, string|float|null>>
     */
    protected function streamExportRows(Builder $recordQuery, array $exportContext = ['', '', '']): Generator
    {
        $query = (clone $recordQuery)
            ->with(['engineRoomDetails' => fn ($detailQuery) => $detailQuery->orderBy('time_slot')])
            ->orderBy('engine_room_records.date')
            ->orderBy('engine_room_records.engine_room_id')
            ->orderBy('engine_room_records.id');

        $statusColumns = array_keys(self::STATUS_COLUMNS);

        foreach ($query->lazy(200) as $record) {
            /** @var EngineRoomRecord $record */
            $context = array_merge($exportContext, [
                optional($record->date)->toDateString(),
                $record->engine_room_id,
                // Status RECORD, label Indonesia (temuan audit 2026-10-04
                // #8c) — ExportValue::status(), NOT ::option().
                ExportValue::status($this->recordStatusValue($record)),
                $record->note,
            ]);

            foreach ($record->engineRoomDetails as $detail) {
                /** @var EngineRoomDetail $detail */
                // Slot selalu HH:MM (temuan audit 2026-10-04 #8d).
                $reading = [ExportValue::time($detail->time_slot)];

                foreach (EngineRoomRecordService::READING_FIELDS as $field) {
                    $reading[] = in_array($field, $statusColumns, true)
                        // Label nilai enum genset — ExportValue::option(),
                        // NOT ::status().
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
     * Base query over the period's Engine Room records (header rows).
     *
     * Scoped through `stations` to the PERIOD'S business unit and to the
     * engine-room station type — engine_room_records carries neither
     * period_id nor business_unit_id, so the join is the only way to scope
     * the MILL — and bounded INCLUSIVELY on engine_room_records.date, the
     * event date, never created_at and never the mobile sync time. A row
     * entered late still belongs to the period it happened in.
     *
     * whereDate, not a raw where: the column is a DATE here, but the suite
     * runs on SQLite while production is PostgreSQL, and this is the
     * spelling the other twelve reports use.
     */
    protected function recordQueryFor(Period $period, ?string $productionLineId = null): Builder
    {
        $query = EngineRoomRecord::query()
            ->join('stations', 'stations.id', '=', 'engine_room_records.station_id')
            ->where('stations.business_unit_id', $period->business_unit_id)
            ->where('stations.type', StationTypeEnum::EngineRoom->value)
            ->whereDate('engine_room_records.date', '>=', $period->start_date->toDateString())
            ->whereDate('engine_room_records.date', '<=', $period->end_date->toDateString())
            ->select('engine_room_records.*');

        $this->scopeToProductionLine($query, $productionLineId);

        return $query;
    }

    /**
     * Penyaringan per production line, DI KOLOM TABEL RECORD — bukan lewat
     * join ke `stations`.
     *
     * `engine_room_records.production_line_id` adalah kolom nyata NOT NULL
     * (migrasi 2026_09_28_000043) yang di-SNAPSHOT dari stasiun saat record
     * dibuat dan tidak pernah berubah sesudahnya. Membaca dari kolom record
     * itulah yang benar secara semantik: untuk record lama yang stasiunnya
     * sudah DIPINDAH ke line lain, kolom record menunjuk line tempat data itu
     * benar-benar dihasilkan, sedangkan `stations.production_line_id`
     * menunjuk line stasiun itu SEKARANG. Menyaring lewat join ke `stations`
     * akan menulis ulang sejarah setiap kali sebuah stasiun dipindahkan.
     */
    protected function scopeToProductionLine(mixed $query, ?string $productionLineId): void
    {
        if ($productionLineId === null || $productionLineId === '') {
            return;
        }

        $query->where('engine_room_records.production_line_id', $productionLineId);
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
     * OPERATOR IS ADMITTED FROM THE FIRST COMMIT, because
     * screen-159--laporan-engine-room-mobile is built in the same series and
     * is its legitimate caller (the Grading / Kernel Plant precedent).
     * Admitting the role HERE is only one third of it: resolveBusinessUnit()
     * must ALSO place Operator in the MILL-BOUND branch and routes/api.php
     * must admit it too, or this line opens a cross-mill read. The one entry
     * point that still refuses Operator is businessUnitOptions(), which
     * checks the role itself precisely because this gate lets it through.
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
     * diperbaiki identik di seluruh report service sekaligus.
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

    protected function recordService(): EngineRoomRecordService
    {
        return $this->recordService ??= app(EngineRoomRecordService::class);
    }

    /**
     * A slot label's POSITION on the canonical 24-slot grid (07:00 .. 06:00
     * next day), memoised per service instance.
     *
     * The grid comes from EngineRoomRecordService::canonicalTimeSlots() —
     * the very grid the input screen renders — so "which slot is last" has
     * exactly one definition in this product. A slot outside the grid sorts
     * AFTER every canonical one rather than silently landing first.
     */
    protected function slotOrderOf(mixed $timeSlot): int
    {
        if ($this->slotOrder === null) {
            $this->slotOrder = array_flip(EngineRoomRecordService::canonicalTimeSlots());
        }

        $label = ExportValue::time($timeSlot);

        return $this->slotOrder[$label] ?? count($this->slotOrder);
    }

    /**
     * Satu opsi periode UNTUK LAYAR INI. Bentuknya sengaja DATAR, persis
     * seperti kedua belas laporan sebelumnya, karena blade membacanya apa
     * adanya — pemisahan periods/period_stations tidak merembes ke kontrak
     * API.
     *
     * Yang diminta layar ini bukan periode telanjang melainkan pasangan
     * (periode, engine-room). Karena itu `status` adalah status STASIUN INI
     * di periode itu (period_stations.status) — periode sendiri tidak punya
     * status lagi — dan `station_type` selalu terisi.
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

    protected function recordStatusValue(EngineRoomRecord $record): string
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
                "laporan-engine-room_{$slug}_{$timestamp}.xlsx",
            ];
        }

        return [
            'text/csv',
            "laporan-engine-room_{$slug}_{$timestamp}.csv",
        ];
    }
}
