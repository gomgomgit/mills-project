<?php

/**
 * BoilerRoomReportServiceTest — screen-131--laporan-boiler-room-web /
 * usecase-131--laporan-boiler-room-web (Laporan Periode Boiler Room).
 *
 * One test per unit_test_case in the screen's tech spec — 41 cases, in the
 * spec's own order, one discrete `it()` each — against
 * App\Services\BoilerRoomReportService. Mirrors
 * tests/Unit/Services/CagesTrackReportServiceTest.php (screen-130) in
 * structure and conventions.
 *
 * `uses(TestCase::class, RefreshDatabase::class)` is MANDATORY here:
 * tests/Pest.php binds Tests\TestCase only to Feature/, so without this
 * line the Unit suite has no application container at all — auth()->user(),
 * Eloquent and the factories would every one of them blow up.
 *
 * ------------------------------------------------------------------
 * THE SIX TRAPS THIS FILE EXISTS TO PIN DOWN
 * ------------------------------------------------------------------
 *  1. SEPARATE DENOMINATOR. All nine numeric columns are nullable, and one
 *     detail row may fill steam_pressure_bar while leaving water_ph empty.
 *     Each metric is averaged over ITS OWN non-null rows, with ITS OWN
 *     reading_count. A shared denominator deflates every rarely-filled
 *     metric and still produces a number that looks entirely reasonable
 *     (28.0 / 10 = 2.8 instead of 28.0 / 4 = 7.0).
 *  2. RAW EXTREMES. metrics[].min/max come from the RAW per-slot readings,
 *     never from the daily averages. A day whose readings are 12.0 and 28.0
 *     recaps as 20.0 while the card reads 12.0 / 28.0. The two genuinely
 *     CANNOT be reconciled, and that is the contract, not a bug — the
 *     screen carries a label saying so (data-testid="raw-extremes-note").
 *  3. THREE MAINTENANCE STATES. blowdown_executed / sootblowing_executed
 *     are NULLABLE enum('y','n'): executed = COUNT('y'), not_executed =
 *     COUNT('n'), not_recorded = COUNT(NULL), and the three ALWAYS sum to
 *     the number of reading rows. NULL never increments not_executed —
 *     doing so reports a maintenance lapse that never happened.
 *  4. NULL IS NOT ZERO. A metric never filled is min/avg/max = null with
 *     reading_count 0, NOT 0/0/0 (which reads as "measured, and it was
 *     zero"). Asserted per metric, independently of every other metric.
 *  5. THREE FREE-TEXT COLUMNS. fuel_feed_rate / id_fan_load / sa_fan_load
 *     are strings with mixed units on the paper form. They are NEVER
 *     aggregated — absent from metrics, daily, by_unit and the trend — and
 *     appear verbatim in the export only. Their ABSENCE is asserted.
 *  6. time_slot IS A TIME, not an integer hour like Cages & Tracks'
 *     tipped_hour. 06:00 and 06:30 are two different slots and must sort
 *     in that order; casting to an integer hour collapses them.
 *
 * NO THRESHOLD ASSERTIONS ANYWHERE, on purpose. Boiler Room has no
 * operational-target master (there is no BoilerRoomOperationalTarget), so
 * any threshold here would be a statistic dressed up as a safety limit.
 * Case 41 asserts the ABSENCE of every threshold-flavoured key.
 *
 * ------------------------------------------------------------------
 * CROSS-MILL SECURITY IS ASSERTED AT ITS TWO DIFFERENT SHAPES
 * ------------------------------------------------------------------
 *   - resolveBusinessUnit() IGNORES the client's business_unit_id for
 *     Supervisor / Mill Management — the caller's own mill, and HTTP 200,
 *     NOT a 403 (a 403 would confirm the other mill exists);
 *   - authorizePeriod() REFUSES another mill's period_id with 403, and
 *     refuses OPERATOR BEFORE it looks the period up — so an Operator
 *     naming a period id that does not exist gets 403, not 404. That
 *     ordering is what stops a refused role from probing which ids exist.
 * Collapsing those into one assertion would hide whichever one broke.
 *
 * And the fail-closed rule (case 34) is asserted as a SPY, because "the
 * all-mills list was never built" is a claim about something that did NOT
 * happen: only a recorded call count of zero can prove it.
 */

use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Models\BoilerRoomDetail;
use App\Models\BoilerRoomRecord;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\Station;
use App\Models\User;
use App\Services\BoilerRoomRecordService;
use App\Services\BoilerRoomReportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * THE SPY for case 34.
 *
 * allBusinessUnits() is public on the service precisely so it can be
 * overridden here: the fail-closed rule for a Supervisor / Mill Management
 * account with no business_unit_id is only meaningful if it can be PROVEN
 * the whole-mill list was never even read, and a recorded call count of
 * zero is that proof. Asserting only the 422 would pass just as happily
 * against an implementation that built the list first and threw afterwards.
 */
class BoilerRoomReportAllBusinessUnitsSpy extends BoilerRoomReportService
{
    public int $allBusinessUnitsCalls = 0;

    public function allBusinessUnits(): Collection
    {
        $this->allBusinessUnitsCalls++;

        return parent::allBusinessUnits();
    }
}

/**
 * One boiler_room_records header on $date for $station, plus one
 * boiler_room_details row per entry of $rows.
 *
 * Each entry is a plain column => value map. `time_slot` is filled in from
 * BoilerRoomRecordService::canonicalTimeSlots() by position unless the
 * entry supplies its own, so a fixture that does not care about slots never
 * has to spell them out — while UNIQUE(boiler_room_record_id, time_slot)
 * is still respected.
 *
 * @param  list<array<string, mixed>>  $rows
 */
function boilerRoomReportRecord(Station $station, string $date, array $rows = [], array $overrides = []): BoilerRoomRecord
{
    $record = BoilerRoomRecord::factory()->forStation($station)->onDate($date)->create(array_merge([
        'boiler_room_id' => 'BLR-1',
        'note' => null,
    ], $overrides));

    $slots = BoilerRoomRecordService::canonicalTimeSlots();

    foreach (array_values($rows) as $index => $row) {
        BoilerRoomDetail::factory()->forRecord($record)->create(array_merge([
            'time_slot' => $slots[$index % count($slots)],
        ], $row));
    }

    return $record;
}

/**
 * Shorthand: $count detail rows, each filling only steam_pressure_bar with
 * $pressure — the "this row counts as filled" shape most maintenance and
 * coverage cases need.
 *
 * @return list<array<string, mixed>>
 */
function boilerRoomReportPressureRows(int $count, float $pressure = 20.0, array $extra = []): array
{
    $rows = [];

    for ($index = 0; $index < $count; $index++) {
        $rows[] = array_merge(['steam_pressure_bar' => $pressure], $extra);
    }

    return $rows;
}

/** Every SQL statement run inside $callback, for the "no SQL aggregate" case. */
function boilerRoomReportQueriesDuring(callable $callback): array
{
    $queries = [];

    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    $callback();

    return $queries;
}

/**
 * Bulk-seeds $totalDetailRows detail rows across as many headers as the
 * UNIQUE(record, time_slot) constraint requires (24 slots per record), via
 * raw inserts — the only way to reach the 50.000-row export ceiling inside
 * a test at all.
 *
 * @return int the number of HEADER records written
 */
function boilerRoomReportBulkSeed(Station $station, User $author, string $dateBase, int $totalDetailRows): int
{
    $now = now()->toDateTimeString();
    $slots = BoilerRoomRecordService::canonicalTimeSlots();

    $written = 0;
    $recordIndex = 0;
    $recordRows = [];
    $detailRows = [];

    while ($written < $totalDetailRows) {
        $recordId = (string) Str::uuid();
        $day = str_pad((string) (($recordIndex % 28) + 1), 2, '0', STR_PAD_LEFT);

        $recordRows[] = [
            'id' => $recordId,
            'station_id' => $station->id,
            'boiler_room_id' => 'BLR-BULK-'.$recordIndex,
            'date' => $dateBase.'-'.$day,
            'note' => null,
            'checked_by' => null,
            'acknowledged_by' => null,
            'status' => 'synced',
            'created_by' => $author->id,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        foreach ($slots as $slot) {
            if ($written >= $totalDetailRows) {
                break;
            }

            $detailRows[] = [
                'id' => (string) Str::uuid(),
                'boiler_room_record_id' => $recordId,
                'time_slot' => $slot,
                'steam_pressure_bar' => 20.0,
                'steam_temp_c' => null,
                'feed_water_temp_c' => null,
                'feed_water_tank_level_percent' => null,
                'boiler_water_level_percent' => null,
                'water_tds_ppm' => null,
                'water_ph' => null,
                'fuel_feed_rate' => null,
                'id_fan_load' => null,
                'sa_fan_load' => null,
                'exhaust_gas_temp_c' => null,
                'dust_collector_differential_pressure_mmh2o' => null,
                'blowdown_executed' => null,
                'sootblowing_executed' => null,
                'findings' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $written++;
        }

        $recordIndex++;
    }

    foreach (array_chunk($recordRows, 500) as $chunk) {
        DB::table('boiler_room_records')->insert($chunk);
    }

    foreach (array_chunk($detailRows, 1000) as $chunk) {
        DB::table('boiler_room_details')->insert($chunk);
    }

    return $recordIndex;
}

beforeEach(function () {
    $this->service = new BoilerRoomReportService;

    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->boilerRoom()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->boilerRoom()->create();

    $this->supervisorA = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagementA = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operatorA = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    // Admin is the one role not bound to a mill — users.business_unit_id is
    // NULL, which is exactly why the mill picker exists.
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('boiler-room')
        ->range('2026-03-01', '2026-03-31')
        ->open()
        ->named('Periode Maret Alpha')
        ->create();

    /** Slots per unit per day — the canonical grid the input screens use. */
    $this->slotsPerDay = count(BoilerRoomRecordService::canonicalTimeSlots());
});

// =====================================================================
// Case 1 — the full success path
// =====================================================================

it('returns the complete summary structure when every condition passes, and writes nothing', function () {
    $this->actingAs($this->supervisorA);

    // BLR-1: 12 filled rows. BLR-2: 8 filled rows. 20 in total, as the case
    // describes, across two boiler units on two dates.
    boilerRoomReportRecord($this->stationA, '2026-03-02', boilerRoomReportPressureRows(12, 20.0, [
        'steam_temp_c' => 260.0,
        'water_tds_ppm' => 2000.0,
        'water_ph' => 10.5,
        'exhaust_gas_temp_c' => 210.0,
        'blowdown_executed' => 'y',
    ]), ['boiler_room_id' => 'BLR-1']);

    boilerRoomReportRecord($this->stationA, '2026-03-03', boilerRoomReportPressureRows(8, 22.0, [
        'steam_temp_c' => 270.0,
        'water_tds_ppm' => 2200.0,
        'water_ph' => 11.0,
        'exhaust_gas_temp_c' => 220.0,
        'sootblowing_executed' => 'n',
    ]), ['boiler_room_id' => 'BLR-2']);

    $recordsBefore = BoilerRoomRecord::count();
    $detailsBefore = BoilerRoomDetail::count();

    $summary = $this->service->buildSummary($this->periodA);

    expect($summary['period']['id'])->toBe((string) $this->periodA->id);
    expect($summary['period']['start_date'])->toBe('2026-03-01');
    expect($summary['period']['end_date'])->toBe('2026-03-31');
    expect($summary['period']['status'])->toBe('open');
    expect($summary['business_unit']['name'])->toBe('Mill Alpha');

    foreach ([
        'steam_pressure_bar', 'steam_temp_c', 'water_tds_ppm', 'water_ph', 'exhaust_gas_temp_c',
    ] as $metric) {
        expect($summary['metrics'])->toHaveKey($metric);
        expect($summary['metrics'][$metric])->toHaveKeys(['min', 'avg', 'max', 'reading_count']);
    }

    foreach (['blowdown', 'sootblowing'] as $column) {
        expect($summary['maintenance'][$column])
            ->toHaveKeys(['executed', 'not_executed', 'not_recorded', 'avg_per_day']);
    }

    expect($summary['coverage'])->toHaveKeys(['filled_slots', 'expected_slots']);
    expect($summary['coverage']['filled_slots'])->toBe(20);
    expect($summary['daily'])->toHaveCount(2);
    expect($summary['by_unit'])->toHaveCount(2);
    expect($summary['total']['reading_rows'])->toBe(20);

    // Not one write anywhere on the path.
    expect(BoilerRoomRecord::count())->toBe($recordsBefore);
    expect(BoilerRoomDetail::count())->toBe($detailsBefore);
});

// =====================================================================
// Case 2 — SEPARATE DENOMINATOR, the single most expensive trap here
// =====================================================================

it('averages each metric over its OWN filled rows: pH is 28.0/4 = 7.0, never 28.0/10 = 2.8', function () {
    $this->actingAs($this->supervisorA);

    // 10 rows fill steam_pressure_bar. Only FOUR of them fill water_ph, at
    // 6.0, 7.0, 7.0 and 8.0 — sum 28.0.
    $phValues = [6.0, 7.0, 7.0, 8.0, null, null, null, null, null, null];
    $rows = [];

    foreach ($phValues as $ph) {
        $rows[] = ['steam_pressure_bar' => 20.0, 'water_ph' => $ph];
    }

    boilerRoomReportRecord($this->stationA, '2026-03-04', $rows);

    $summary = $this->service->buildSummary($this->periodA);

    // 28.0 / 4 = 7.0. A shared denominator would give 28.0 / 10 = 2.8 —
    // a perfectly plausible-looking pH, which is precisely the danger.
    expect($summary['metrics']['water_ph']['avg'])->toBe(7.0);
    expect($summary['metrics']['water_ph']['avg'])->not->toBe(2.8);

    expect($summary['metrics']['water_ph']['reading_count'])->toBe(4);
    expect($summary['metrics']['steam_pressure_bar']['reading_count'])->toBe(10);
});

// =====================================================================
// Case 3
// =====================================================================

it('reports reading_count per metric with no shared global denominator', function () {
    $this->actingAs($this->supervisorA);

    // 10 rows; the five headline metrics are filled 10 / 7 / 5 / 4 / 2 times.
    $rows = [];

    for ($index = 0; $index < 10; $index++) {
        $rows[] = [
            'steam_pressure_bar' => 20.0,
            'steam_temp_c' => $index < 7 ? 260.0 : null,
            'water_tds_ppm' => $index < 5 ? 2000.0 : null,
            'water_ph' => $index < 4 ? 7.0 : null,
            'exhaust_gas_temp_c' => $index < 2 ? 210.0 : null,
        ];
    }

    boilerRoomReportRecord($this->stationA, '2026-03-05', $rows);

    $summary = $this->service->buildSummary($this->periodA);

    expect($summary['metrics']['steam_pressure_bar']['reading_count'])->toBe(10);
    expect($summary['metrics']['steam_temp_c']['reading_count'])->toBe(7);
    expect($summary['metrics']['water_tds_ppm']['reading_count'])->toBe(5);
    expect($summary['metrics']['water_ph']['reading_count'])->toBe(4);
    expect($summary['metrics']['exhaust_gas_temp_c']['reading_count'])->toBe(2);

    // There is deliberately NO shared reading_count anywhere in the payload.
    expect($summary['metrics'])->not->toHaveKey('reading_count');
    expect($summary)->not->toHaveKey('reading_count');
});

// =====================================================================
// Case 4
// =====================================================================

it('skips a metric null rows without touching any other metric', function () {
    $this->actingAs($this->supervisorA);

    boilerRoomReportRecord($this->stationA, '2026-03-06', [
        ['steam_pressure_bar' => 20.0, 'water_ph' => null],
        ['steam_pressure_bar' => null, 'water_ph' => 7.0],
        ['steam_pressure_bar' => 30.0, 'water_ph' => null],
    ]);

    $summary = $this->service->buildSummary($this->periodA);

    expect($summary['metrics']['steam_pressure_bar'])->toBe([
        'min' => 20.0, 'avg' => 25.0, 'max' => 30.0, 'reading_count' => 2,
    ]);
    expect($summary['metrics']['water_ph'])->toBe([
        'min' => 7.0, 'avg' => 7.0, 'max' => 7.0, 'reading_count' => 1,
    ]);
});

// =====================================================================
// Case 5 — NULL IS NOT ZERO
// =====================================================================

it('returns null, never zero, for a metric never filled in the whole period', function () {
    $this->actingAs($this->supervisorA);

    // 20 rows, steam pressure filled throughout, water_ph null throughout.
    boilerRoomReportRecord($this->stationA, '2026-03-07', boilerRoomReportPressureRows(20, 18.0));

    $summary = $this->service->buildSummary($this->periodA);

    $ph = $summary['metrics']['water_ph'];

    // Null identity, per key, and explicitly NOT the zero that would read as
    // "we measured pH and it came out at 0".
    expect($ph['min'])->toBeNull();
    expect($ph['avg'])->toBeNull();
    expect($ph['max'])->toBeNull();
    expect($ph['min'])->not->toBe(0);
    expect($ph['avg'])->not->toBe(0);
    expect($ph['max'])->not->toBe(0);
    expect($ph['min'])->not->toBe(0.0);
    expect($ph['avg'])->not->toBe(0.0);
    expect($ph['max'])->not->toBe(0.0);
    expect($ph['reading_count'])->toBe(0);

    // Independent per metric: the filled one is untouched by the empty one.
    expect($summary['metrics']['steam_pressure_bar']['reading_count'])->toBe(20);
    expect($summary['metrics']['steam_pressure_bar']['avg'])->toBe(18.0);
});

// =====================================================================
// Case 6
// =====================================================================

it('returns null metrics and empty tables for a period with no readings at all', function () {
    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA);

    foreach (BoilerRoomReportService::NUMERIC_METRICS as $metric) {
        expect($summary['metrics'][$metric]['min'])->toBeNull();
        expect($summary['metrics'][$metric]['avg'])->toBeNull();
        expect($summary['metrics'][$metric]['max'])->toBeNull();
        expect($summary['metrics'][$metric]['reading_count'])->toBe(0);
    }

    foreach (['blowdown', 'sootblowing'] as $column) {
        expect($summary['maintenance'][$column]['executed'])->toBe(0);
        expect($summary['maintenance'][$column]['not_executed'])->toBe(0);
        expect($summary['maintenance'][$column]['not_recorded'])->toBe(0);
    }

    expect($summary['coverage']['filled_slots'])->toBe(0);
    expect($summary['daily'])->toBe([]);
    expect($summary['by_unit'])->toBe([]);
    // has_data distinguishes "nothing to report" from "the figures are zero".
    expect($summary['has_data'])->toBeFalse();
});

// =====================================================================
// Case 7
// =====================================================================

it('keeps a date with records in the daily recap even when one metric is empty on every one of its rows', function () {
    $this->actingAs($this->supervisorA);

    boilerRoomReportRecord($this->stationA, '2026-03-05', [
        ['steam_pressure_bar' => 20.0, 'water_tds_ppm' => null],
        ['steam_pressure_bar' => 22.0, 'water_tds_ppm' => null],
        ['steam_pressure_bar' => 24.0, 'water_tds_ppm' => null],
        ['steam_pressure_bar' => 26.0, 'water_tds_ppm' => null],
    ]);

    $summary = $this->service->buildSummary($this->periodA);

    expect($summary['metrics']['water_tds_ppm']['reading_count'])->toBe(0);
    expect($summary['metrics']['water_tds_ppm']['avg'])->toBeNull();

    $daily = collect($summary['daily'])->firstWhere('date', '2026-03-05');

    // The date still counts as a date with records, and still carries the
    // metric it DOES have — dropping it would make the period look better
    // recorded than it was.
    expect($daily)->not->toBeNull();
    expect($daily['steam_pressure_avg'])->toBe(23.0);
    expect($daily['water_tds_avg'])->toBeNull();
    expect($summary['total']['days_with_records'])->toBe(1);
});

// =====================================================================
// Case 8 — THREE MAINTENANCE STATES (blowdown)
// =====================================================================

it('splits blowdown into executed/not_executed/not_recorded, and NULL never lands in not_executed', function () {
    $this->actingAs($this->supervisorA);

    // 10 rows: 3 'y', 2 'n', 5 NULL. Every row also fills steam_pressure_bar
    // so all ten count as reading rows regardless of the enum.
    $states = ['y', 'y', 'y', 'n', 'n', null, null, null, null, null];
    $rows = [];

    foreach ($states as $state) {
        $rows[] = ['steam_pressure_bar' => 20.0, 'blowdown_executed' => $state];
    }

    boilerRoomReportRecord($this->stationA, '2026-03-08', $rows);

    $summary = $this->service->buildSummary($this->periodA);
    $blowdown = $summary['maintenance']['blowdown'];

    expect($blowdown['executed'])->toBe(3);
    expect($blowdown['not_executed'])->toBe(2);
    expect($blowdown['not_recorded'])->toBe(5);

    // THE INVARIANT. The three states always account for every reading row;
    // if NULL had been absorbed into not_executed this would still sum to 10
    // while reporting 7 maintenance lapses that never happened, so the split
    // is asserted alongside the sum, never instead of it.
    expect($blowdown['executed'] + $blowdown['not_executed'] + $blowdown['not_recorded'])->toBe(10);
    expect($summary['total']['reading_rows'])->toBe(10);
    expect($blowdown['not_executed'])->not->toBe(7);
});

// =====================================================================
// Case 9 — THREE MAINTENANCE STATES (sootblowing)
// =====================================================================

it('splits sootblowing into three states that sum to the number of reading rows', function () {
    $this->actingAs($this->supervisorA);

    // 10 rows: 1 'y', 4 'n', 5 NULL.
    $states = ['y', 'n', 'n', 'n', 'n', null, null, null, null, null];
    $rows = [];

    foreach ($states as $state) {
        $rows[] = ['steam_pressure_bar' => 20.0, 'sootblowing_executed' => $state];
    }

    boilerRoomReportRecord($this->stationA, '2026-03-09', $rows);

    $summary = $this->service->buildSummary($this->periodA);
    $soot = $summary['maintenance']['sootblowing'];

    expect($soot['executed'])->toBe(1);
    expect($soot['not_executed'])->toBe(4);
    expect($soot['not_recorded'])->toBe(5);
    expect($soot['executed'] + $soot['not_executed'] + $soot['not_recorded'])->toBe(10);
    expect($summary['total']['reading_rows'])->toBe(10);
    // The five NULLs stayed out of not_executed.
    expect($soot['not_executed'])->not->toBe(9);
});

// =====================================================================
// Case 10
// =====================================================================

it('distinguishes an all-NULL maintenance period from maintenance genuinely never performed', function () {
    $this->actingAs($this->supervisorA);

    // 12 rows, both maintenance columns NULL throughout. Rows still count as
    // reading rows because steam_pressure_bar is filled.
    boilerRoomReportRecord($this->stationA, '2026-03-10', boilerRoomReportPressureRows(12, 19.0));

    $summary = $this->service->buildSummary($this->periodA);

    foreach (['blowdown', 'sootblowing'] as $column) {
        expect($summary['maintenance'][$column]['executed'])->toBe(0);
        expect($summary['maintenance'][$column]['not_executed'])->toBe(0);
        expect($summary['maintenance'][$column]['not_recorded'])->toBe(12);
        // Without this flag both "never done" and "never written down"
        // render as the same bare 0.
        expect($summary['maintenance'][$column]['all_unrecorded'])->toBeTrue();
        expect($summary['maintenance'][$column]['executed']
            + $summary['maintenance'][$column]['not_executed']
            + $summary['maintenance'][$column]['not_recorded'])->toBe(12);
    }
});

// =====================================================================
// Case 11 — avg_per_day divisor: RESOLVED (tech spec v2).
//
// The spec used to contradict itself: business_logic step 8 said
// "avg_per_day = executed / days_with_records", while unit_test_case 11
// said "0.5 (5 / 10 hari)" — i.e. the calendar length of the period.
//
// RESOLUTION: business_logic step 8 is authoritative. The divisor is
// DAYS WITH RECORDS. The calendar-length reading was the spec defect and
// has been corrected in tech spec v2; the implementation was right all
// along. This matches screen-130's already-approved avg_cages_per_day and
// the screen's own label, "per hari ber-record" — and the coverage card
// sits above the figure, so the divisor is never read without its count.
//
// The reason it matters: dividing by calendar length would make PATCHY
// RECORD-KEEPING read as NEGLECTED MAINTENANCE. Two different findings.
//
// The fixture keeps days_in_period (10) at exactly twice
// days_with_records (5) so the two readings can never coincide, and both
// are asserted here — the divisor is locked BY this test, not implied.
// =====================================================================

it('divides maintenance avg_per_day by the number of DAYS WITH RECORDS, per business_logic step 8 (tech spec v2)', function () {
    $this->actingAs($this->supervisorA);

    // Ten-day period, exactly as the case states.
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('boiler-room')
        ->range('2026-03-01', '2026-03-10')
        ->named('Periode Sepuluh Hari')
        ->open()
        ->create();

    // Five rows with blowdown_executed='y', spread one per date.
    foreach (['2026-03-01', '2026-03-02', '2026-03-03', '2026-03-04', '2026-03-05'] as $date) {
        boilerRoomReportRecord($this->stationA, $date, [
            ['steam_pressure_bar' => 20.0, 'blowdown_executed' => 'y'],
        ], ['boiler_room_id' => 'BLR-1']);
    }

    $summary = $this->service->buildSummary($period);

    expect($summary['maintenance']['blowdown']['executed'])->toBe(5);

    // The two candidate divisors, pinned apart: 10 calendar days vs the 5
    // days that actually carry a record.
    expect($summary['coverage']['days_in_period'])->toBe(10);
    expect($summary['total']['days_with_records'])->toBe(5);

    // 5 / 5 days-with-records = 1.0.
    expect($summary['maintenance']['blowdown']['avg_per_day'])->toBe(1.0);
    // ...and never 5 / 10 calendar days. Named explicitly so a regression to
    // the calendar divisor fails loudly instead of drifting past review.
    expect($summary['maintenance']['blowdown']['avg_per_day'])->not->toBe(0.5);
});

// =====================================================================
// Case 12 — the three free-text columns are NEVER aggregated
// =====================================================================

it('never aggregates fuel_feed_rate, id_fan_load or sa_fan_load into metrics, daily or by_unit', function () {
    $this->actingAs($this->supervisorA);

    boilerRoomReportRecord($this->stationA, '2026-03-11', [
        [
            'steam_pressure_bar' => 20.0,
            'fuel_feed_rate' => '12 ton/jam',
            'id_fan_load' => '80%',
            'sa_fan_load' => 'sedang',
        ],
        [
            'steam_pressure_bar' => 22.0,
            'fuel_feed_rate' => '9 ton/jam',
            'id_fan_load' => '60%',
            'sa_fan_load' => 'rendah',
        ],
    ]);

    $summary = $this->service->buildSummary($this->periodA);

    $freeText = ['fuel_feed_rate', 'id_fan_load', 'sa_fan_load'];

    // Absent from the metric list itself...
    foreach ($freeText as $column) {
        expect(BoilerRoomReportService::NUMERIC_METRICS)->not->toContain($column);
        expect($summary['metrics'])->not->toHaveKey($column);
    }

    // ...from every daily row (which is also the trend's data source)...
    foreach ($summary['daily'] as $row) {
        foreach ($freeText as $column) {
            expect($row)->not->toHaveKey($column);
            expect($row)->not->toHaveKey(str_replace('_rate', '', $column).'_avg');
        }
    }

    // ...and from every per-unit row.
    foreach ($summary['by_unit'] as $row) {
        foreach ($freeText as $column) {
            expect($row)->not->toHaveKey($column);
        }
    }

    // No numeric cast was ever attempted over that text: '12 ton/jam' would
    // cast to 12.0 and quietly become a metric.
    $encoded = json_encode($summary);
    expect($encoded)->not->toContain('12 ton/jam');
    expect($encoded)->not->toContain('sedang');
});

// =====================================================================
// Case 13
// =====================================================================

it('emits the three free-text columns verbatim on the export rows', function () {
    $this->actingAs($this->supervisorA);

    boilerRoomReportRecord($this->stationA, '2026-03-12', [
        [
            'steam_pressure_bar' => 20.0,
            'fuel_feed_rate' => '12 ton/jam',
            'id_fan_load' => '80%',
            'sa_fan_load' => 'sedang',
        ],
    ]);

    $rows = iterator_to_array($this->service->buildExportRows($this->periodA), false);

    expect($rows)->toHaveCount(1);

    // Exactly as typed — no unit normalisation, no rounding, no cast.
    expect($rows[0])->toContain('12 ton/jam');
    expect($rows[0])->toContain('80%');
    expect($rows[0])->toContain('sedang');
});

// =====================================================================
// Case 14 — RAW EXTREMES (and their deliberate disagreement with the recap)
// =====================================================================

it('takes period min and max from the RAW readings while the daily recap still reads the daily average', function () {
    $this->actingAs($this->supervisorA);

    // 2026-03-02 — two readings, 12.0 and 28.0, whose DAILY AVERAGE is 20.0.
    boilerRoomReportRecord($this->stationA, '2026-03-02', [
        ['steam_pressure_bar' => 12.0],
        ['steam_pressure_bar' => 28.0],
    ]);
    // Another date, well inside those two raw values.
    boilerRoomReportRecord($this->stationA, '2026-03-03', [
        ['steam_pressure_bar' => 19.0],
        ['steam_pressure_bar' => 21.0],
    ]);

    $summary = $this->service->buildSummary($this->periodA);

    // From the raw per-slot readings.
    expect($summary['metrics']['steam_pressure_bar']['min'])->toBe(12.0);
    expect($summary['metrics']['steam_pressure_bar']['max'])->toBe(28.0);
    // NOT the daily averages (20.0 / 20.0), and not the other day's raws.
    expect($summary['metrics']['steam_pressure_bar']['min'])->not->toBe(19.0);
    expect($summary['metrics']['steam_pressure_bar']['min'])->not->toBe(20.0);
    expect($summary['metrics']['steam_pressure_bar']['max'])->not->toBe(21.0);
    expect($summary['metrics']['steam_pressure_bar']['max'])->not->toBe(20.0);

    // AND, in the same breath, the recap row for that same date reads 20.0.
    // The two figures genuinely cannot be reconciled. That is the contract —
    // a pressure that dropped to 12 bar for one slot matters precisely
    // because the day's average hides it — and the screen labels it as such
    // (data-testid="raw-extremes-note").
    $recap = collect($summary['daily'])->firstWhere('date', '2026-03-02');
    expect($recap['steam_pressure_avg'])->toBe(20.0);
    expect($recap['steam_pressure_avg'])->not->toBe($summary['metrics']['steam_pressure_bar']['min']);
});

// =====================================================================
// Case 15
// =====================================================================

it('keeps the daily recap on daily averages even though the period extremes come from raw readings', function () {
    $this->actingAs($this->supervisorA);

    boilerRoomReportRecord($this->stationA, '2026-03-02', [
        ['steam_pressure_bar' => 12.0],
        ['steam_pressure_bar' => 28.0],
    ]);

    $summary = $this->service->buildSummary($this->periodA);

    $recap = collect($summary['daily'])->firstWhere('date', '2026-03-02');

    // (12.0 + 28.0) / 2 = 20.0 in the recap...
    expect($recap['steam_pressure_avg'])->toBe(20.0);
    // ...while the card keeps the raw 12.0 / 28.0. Both are correct, and
    // they are supposed to differ.
    expect($summary['metrics']['steam_pressure_bar']['min'])->toBe(12.0);
    expect($summary['metrics']['steam_pressure_bar']['max'])->toBe(28.0);
    expect($summary['metrics']['steam_pressure_bar']['min'])->not->toBe($recap['steam_pressure_avg']);
    expect($summary['metrics']['steam_pressure_bar']['max'])->not->toBe($recap['steam_pressure_avg']);
});

// =====================================================================
// Case 16
// =====================================================================

it('counts a slot as filled when ANY one of the 15 non-time_slot columns is filled', function () {
    $this->actingAs($this->supervisorA);

    boilerRoomReportRecord($this->stationA, '2026-03-13', [
        // A — only water_ph.
        ['water_ph' => 7.2],
        // B — only findings (a string column, still one of the fifteen).
        ['findings' => 'kerak ringan'],
        // C — only blowdown_executed = 'n' (an enum, still one of the fifteen).
        ['blowdown_executed' => 'n'],
        // D — all fifteen null. An untouched slot.
        [],
    ]);

    $summary = $this->service->buildSummary($this->periodA);

    expect($summary['coverage']['filled_slots'])->toBe(3);
    expect($summary['total']['reading_rows'])->toBe(3);
    // D contributed to nothing: it is not a maintenance "not recorded" row
    // either, since coverage already reports it as a missing slot.
    expect($summary['maintenance']['blowdown']['not_executed'])->toBe(1);
    expect($summary['maintenance']['blowdown']['not_recorded'])->toBe(2);
});

// =====================================================================
// Case 17
// =====================================================================

it('counts zero filled slots when every row is entirely null, while still reporting expected_slots', function () {
    $this->actingAs($this->supervisorA);

    boilerRoomReportRecord($this->stationA, '2026-03-14', array_fill(0, 10, []));

    $summary = $this->service->buildSummary($this->periodA);

    expect($summary['coverage']['filled_slots'])->toBe(0);
    // One boiler unit x 31 days x the canonical slot grid — still reported,
    // because "0 of N" is the whole point of the coverage card.
    expect($summary['coverage']['expected_slots'])->toBe(1 * 31 * $this->slotsPerDay);
    expect($summary['coverage']['boiler_unit_count'])->toBe(1);
    expect($summary['coverage']['days_in_period'])->toBe(31);
});

// =====================================================================
// Case 18
// =====================================================================

it('still returns every figure when coverage is very low, with expected_slots alongside filled_slots', function () {
    $this->actingAs($this->supervisorA);

    // A 30-day period with only six filled rows.
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('boiler-room')
        ->range('2026-04-01', '2026-04-30')
        ->named('Periode April Tipis')
        ->open()
        ->create();

    boilerRoomReportRecord($this->stationA, '2026-04-10', boilerRoomReportPressureRows(6, 21.0));

    $summary = $this->service->buildSummary($period);

    expect($summary['coverage']['filled_slots'])->toBe(6);
    expect($summary['coverage']['expected_slots'])->toBe(1 * 30 * $this->slotsPerDay);
    expect($summary['coverage']['days_in_period'])->toBe(30);

    // The figures are computed and returned, never withheld — the reader is
    // told how thin they are instead.
    expect($summary['metrics']['steam_pressure_bar']['avg'])->toBe(21.0);
    expect($summary['metrics']['steam_pressure_bar']['reading_count'])->toBe(6);
});

// =====================================================================
// Case 19 — time_slot is a TIME, not an integer hour
// =====================================================================

it('orders slots by their TIME value so 06:00 precedes 06:30, never collapsing them to one hour', function () {
    $this->actingAs($this->supervisorA);

    // Inserted out of order on purpose.
    boilerRoomReportRecord($this->stationA, '2026-03-15', [
        ['time_slot' => '06:30:00', 'steam_pressure_bar' => 21.0],
        ['time_slot' => '06:00:00', 'steam_pressure_bar' => 20.0],
        ['time_slot' => '18:00:00', 'steam_pressure_bar' => 22.0],
    ]);

    $rows = iterator_to_array($this->service->buildExportRows($this->periodA), false);

    // Column 4 of the export row is the time slot (after date / unit /
    // status / note).
    $slots = array_map(fn ($row) => $row[4], $rows);

    expect($slots)->toBe(['06:00:00', '06:30:00', '18:00:00']);
    // Three distinct slots: an integer-hour cast would have made the first
    // two the same slot and lost a reading.
    expect($rows)->toHaveCount(3);
    expect($this->service->buildSummary($this->periodA)['metrics']['steam_pressure_bar']['reading_count'])->toBe(3);
});

// =====================================================================
// Case 20
// =====================================================================

it('counts the same time slot on two different boiler units as two readings', function () {
    $this->actingAs($this->supervisorA);

    boilerRoomReportRecord($this->stationA, '2026-03-16', [
        ['time_slot' => '08:00:00', 'steam_pressure_bar' => 20.0],
    ], ['boiler_room_id' => 'BLR-1']);

    boilerRoomReportRecord($this->stationA, '2026-03-16', [
        ['time_slot' => '08:00:00', 'steam_pressure_bar' => 24.0],
    ], ['boiler_room_id' => 'BLR-2']);

    $summary = $this->service->buildSummary($this->periodA);

    // UNIQUE(boiler_room_record_id, time_slot) is per RECORD, and one date
    // has one record per boiler unit.
    expect($summary['metrics']['steam_pressure_bar']['reading_count'])->toBe(2);
    expect($summary['coverage']['filled_slots'])->toBe(2);

    $rows = iterator_to_array($this->service->buildExportRows($this->periodA), false);

    expect($rows)->toHaveCount(2);
    expect(array_map(fn ($row) => $row[1], $rows))->toBe(['BLR-1', 'BLR-2']);
});

// =====================================================================
// Case 21
// =====================================================================

it('merges every boiler unit on one date into the period figures', function () {
    $this->actingAs($this->supervisorA);

    boilerRoomReportRecord($this->stationA, '2026-03-03', [
        ['steam_pressure_bar' => 20.0],
    ], ['boiler_room_id' => 'BLR-1']);

    boilerRoomReportRecord($this->stationA, '2026-03-03', [
        ['steam_pressure_bar' => 24.0],
    ], ['boiler_room_id' => 'BLR-2']);

    $summary = $this->service->buildSummary($this->periodA);

    expect($summary['metrics']['steam_pressure_bar']['reading_count'])->toBe(2);
    expect($summary['metrics']['steam_pressure_bar']['avg'])->toBe(22.0);

    // No unit was silently filtered out of the period figures.
    expect($summary['coverage']['boiler_unit_count'])->toBe(2);
});

// =====================================================================
// Case 22
// =====================================================================

it('splits the recap per boiler_room_id with each unit own averages and counts', function () {
    $this->actingAs($this->supervisorA);

    boilerRoomReportRecord($this->stationA, '2026-03-04', boilerRoomReportPressureRows(12, 20.0, [
        'steam_temp_c' => 260.0,
        'blowdown_executed' => 'y',
    ]), ['boiler_room_id' => 'BLR-1']);

    boilerRoomReportRecord($this->stationA, '2026-03-05', boilerRoomReportPressureRows(8, 24.0, [
        'steam_temp_c' => 280.0,
        'sootblowing_executed' => 'y',
    ]), ['boiler_room_id' => 'BLR-2']);

    $summary = $this->service->buildSummary($this->periodA);

    expect(array_column($summary['by_unit'], 'boiler_room_id'))->toBe(['BLR-1', 'BLR-2']);

    $one = collect($summary['by_unit'])->firstWhere('boiler_room_id', 'BLR-1');
    $two = collect($summary['by_unit'])->firstWhere('boiler_room_id', 'BLR-2');

    expect($one['reading_count'])->toBe(12);
    expect($one['steam_pressure_avg'])->toBe(20.0);
    expect($one['steam_temp_avg'])->toBe(260.0);
    expect($one['blowdown_executed'])->toBe(12);
    expect($one['sootblowing_executed'])->toBe(0);

    expect($two['reading_count'])->toBe(8);
    expect($two['steam_pressure_avg'])->toBe(24.0);
    expect($two['steam_temp_avg'])->toBe(280.0);
    expect($two['blowdown_executed'])->toBe(0);
    expect($two['sootblowing_executed'])->toBe(8);
});

// =====================================================================
// Case 23
// =====================================================================

it('keeps a boiler unit that has a record but not one filled reading, with reading_count 0', function () {
    $this->actingAs($this->supervisorA);

    boilerRoomReportRecord($this->stationA, '2026-03-06', boilerRoomReportPressureRows(4, 20.0), [
        'boiler_room_id' => 'BLR-1',
    ]);

    // BLR-3 has a record, and four rows, but every one of the fifteen
    // columns on every row is null.
    boilerRoomReportRecord($this->stationA, '2026-03-06', array_fill(0, 4, []), [
        'boiler_room_id' => 'BLR-3',
    ]);

    $summary = $this->service->buildSummary($this->periodA);

    expect(array_column($summary['by_unit'], 'boiler_room_id'))->toBe(['BLR-1', 'BLR-3']);

    $blank = collect($summary['by_unit'])->firstWhere('boiler_room_id', 'BLR-3');

    // Dropping it would hide exactly the unit that was never written down —
    // the one worth seeing.
    expect($blank)->not->toBeNull();
    expect($blank['reading_count'])->toBe(0);
    expect($blank['steam_pressure_avg'])->toBeNull();
    expect($blank['steam_temp_avg'])->toBeNull();
    expect($blank['water_tds_avg'])->toBeNull();
    expect($blank['water_ph_avg'])->toBeNull();
});

// =====================================================================
// Case 24
// =====================================================================

it('includes both period bounds and excludes the day before and the day after, filtering on record.date', function () {
    $this->actingAs($this->supervisorA);

    boilerRoomReportRecord($this->stationA, '2026-03-01', [['steam_pressure_bar' => 10.0]]);
    boilerRoomReportRecord($this->stationA, '2026-03-31', [['steam_pressure_bar' => 30.0]]);
    // One day BEFORE and one day AFTER — neither may be counted.
    boilerRoomReportRecord($this->stationA, '2026-02-28', [['steam_pressure_bar' => 99.0]]);
    boilerRoomReportRecord($this->stationA, '2026-04-01', [['steam_pressure_bar' => 88.0]]);

    $summary = $this->service->buildSummary($this->periodA);

    expect($summary['metrics']['steam_pressure_bar']['reading_count'])->toBe(2);
    expect($summary['metrics']['steam_pressure_bar']['min'])->toBe(10.0);
    expect($summary['metrics']['steam_pressure_bar']['max'])->toBe(30.0);
    // The out-of-range values are nowhere near the figures.
    expect($summary['metrics']['steam_pressure_bar']['max'])->not->toBe(99.0);

    expect(array_column($summary['daily'], 'date'))->toBe(['2026-03-01', '2026-03-31']);
});

// =====================================================================
// Case 25
// =====================================================================

it('counts a record by its event date even when created_at falls outside the period', function () {
    $this->actingAs($this->supervisorA);

    $record = boilerRoomReportRecord($this->stationA, '2026-03-15', [
        ['steam_pressure_bar' => 23.0],
    ]);

    // Entered late — two days after the period ended.
    DB::table('boiler_room_records')
        ->where('id', $record->id)
        ->update(['created_at' => '2026-04-02 08:30:00', 'updated_at' => '2026-04-02 08:30:00']);

    $summary = $this->service->buildSummary($this->periodA);

    expect($summary['metrics']['steam_pressure_bar']['reading_count'])->toBe(1);
    expect($summary['metrics']['steam_pressure_bar']['avg'])->toBe(23.0);
    expect(array_column($summary['daily'], 'date'))->toBe(['2026-03-15']);
    expect(array_column($summary['by_unit'], 'boiler_room_id'))->toBe(['BLR-1']);

    $rows = iterator_to_array($this->service->buildExportRows($this->periodA), false);
    expect($rows)->toHaveCount(1);
    expect($rows[0][0])->toBe('2026-03-15');
});

// =====================================================================
// Case 26
// =====================================================================

it('aggregates in PHP over raw rows and issues no avg/sum/group by at the SQL layer', function () {
    $this->actingAs($this->supervisorA);

    boilerRoomReportRecord($this->stationA, '2026-03-17', boilerRoomReportPressureRows(5, 20.0, [
        'water_ph' => 7.0,
        'blowdown_executed' => 'y',
    ]));

    $queries = boilerRoomReportQueriesDuring(function () {
        $this->service->buildSummary($this->periodA);
    });

    expect($queries)->not->toBeEmpty();

    foreach ($queries as $sql) {
        $normalised = strtolower($sql);

        // SQL aggregate behaviour over NULLABLE columns differs between
        // SQLite (the test connection) and PostgreSQL (production), and it
        // is the separate-denominator and null-is-not-zero rules that get
        // lost in that difference.
        expect($normalised)->not->toContain('avg(');
        expect($normalised)->not->toContain('sum(');
        expect($normalised)->not->toContain('group by');
    }
});

// =====================================================================
// Case 27
// =====================================================================

it('lists periods that cover Boiler Room, including every-station-type ones, always as the boiler-room pair', function () {
    $this->actingAs($this->supervisorA);

    $boilerRoom = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('boiler-room')
        ->range('2026-05-01', '2026-05-31')->named('Periode Boiler Room')->open()->create();
    // stationType(null) tidak lagi berarti `station_type` NULL — kolom itu
    // hilang 2026-09-25. Cakupan semua-stasiun kini berarti satu baris
    // period_stations per jenis stasiun, dan baris 'boiler-room'-nya itulah
    // yang membuat periode ini terpungut.
    $allTypes = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType(null)
        ->range('2026-06-01', '2026-06-30')->named('Periode Semua Stasiun')->open()->create();
    $otherType = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('sterilizer')
        ->range('2026-07-01', '2026-07-31')->named('Periode Sterilizer')->open()->create();

    $periods = $this->service->listPeriods();
    $ids = array_column($periods, 'id');

    expect($ids)->toContain((string) $boilerRoom->id);
    expect($ids)->toContain((string) $allTypes->id);
    expect($ids)->not->toContain((string) $otherType->id);

    // Label 'Semua Stasiun' lenyap: setiap opsi adalah pasangan
    // (periode, boiler-room), jadi jenis stasiunnya selalu terisi.
    $option = collect($periods)->firstWhere('id', (string) $allTypes->id);

    expect($option['station_type'])->toBe('boiler-room');
    expect($option['station_type_label'])->toBe('Boiler Room');
});

// BARU 2026-09-26, bersama pemisahan periods/period_stations. Perilaku yang
// DULU dijamin cabang orWhereNull('station_type') dan kini sengaja dibuang:
// tanpa baris period_stations untuk boiler-room, sebuah periode bukan periode
// Boiler Room, sekalipun milik mill yang sama.
it('does NOT list a period with no period_stations row for boiler-room', function () {
    $this->actingAs($this->supervisorA);

    $otherTypesOnly = Period::factory()->forBusinessUnit($this->businessUnitA)
        ->stationTypes(['sterilizer', 'clarification'])
        ->range('2026-05-01', '2026-05-31')->open()->create();
    $noStations = Period::factory()->forBusinessUnit($this->businessUnitA)
        ->noStations()->range('2026-06-01', '2026-06-30')->create();

    $ids = array_column($this->service->listPeriods(), 'id');

    expect($ids)->not->toContain((string) $otherTypesOnly->id);
    expect($ids)->not->toContain((string) $noStations->id);
});

// BARU 2026-09-26. Inti pemisahan model ini, dan bentuk yang sebelumnya
// mustahil diuji: dua jenis stasiun berstatus berbeda di periode yang sama.
it('reports the boiler-room status, not another station type status in the same period', function () {
    $this->actingAs($this->supervisorA);

    $period = Period::factory()->forBusinessUnit($this->businessUnitA)
        ->noStations()->range('2026-05-01', '2026-05-31')->named('Periode Campuran')->create();

    PeriodStation::factory()->forPeriod($period)->stationType('boiler-room')->open()->create();
    PeriodStation::factory()->forPeriod($period)->stationType('sterilizer')->closed()->create();

    $option = collect($this->service->listPeriods())->firstWhere('id', (string) $period->id);

    expect($option['status'])->toBe('open');
    expect($this->service->summary($period)['period']['status'])->toBe('open');
});

// =====================================================================
// Case 28
// =====================================================================

it('returns an empty list, not an error, for a mill with no Boiler Room period', function () {
    $millManagementB = User::factory()->role(UserRole::MillManagement)
        ->forBusinessUnit($this->businessUnitB)->create();

    $this->actingAs($millManagementB);

    // BU-B has no period at all — an empty picker plus a UI hint, never a
    // 404 and never an exception.
    expect($this->service->listPeriods())->toBe([]);
});

// =====================================================================
// Case 29
// =====================================================================

it('reads and exports a CLOSED period in full — the period lock governs writing, not reading', function () {
    $this->actingAs($this->supervisorA);

    $closed = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('boiler-room')
        ->range('2026-08-01', '2026-08-31')->named('Periode Agustus Tertutup')->closed()->create();

    boilerRoomReportRecord($this->stationA, '2026-08-10', boilerRoomReportPressureRows(3, 20.0));

    $summary = $this->service->buildSummary($closed);

    expect($summary['period']['status'])->toBe('closed');
    expect($summary['metrics']['steam_pressure_bar']['reading_count'])->toBe(3);

    $rows = iterator_to_array($this->service->buildExportRows($closed), false);
    expect($rows)->toHaveCount(3);
});

// =====================================================================
// Case 30
// =====================================================================

it('throws 403 FORBIDDEN for a period belonging to another mill, before any record query runs', function () {
    $this->actingAs($this->supervisorA);

    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('boiler-room')
        ->range('2026-03-01', '2026-03-31')->named('Periode Maret Beta')->open()->create();

    boilerRoomReportRecord($this->stationB, '2026-03-10', [['steam_pressure_bar' => 99.0]]);

    $queries = [];

    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    // THIS is the real cross-mill leak path — a concrete handle to another
    // mill's data — so unlike the ignored business_unit_id query param it
    // is refused outright.
    expect(fn () => $this->service->buildSummary($periodB))
        ->toThrow(AuthorizationException::class);

    foreach ($queries as $sql) {
        expect(strtolower($sql))->not->toContain('boiler_room_records');
    }
});

// =====================================================================
// Case 31
// =====================================================================

it('throws 404 NOT_FOUND when the period id does not exist', function () {
    $this->actingAs($this->supervisorA);

    expect(fn () => $this->service->buildSummary((string) Str::uuid()))
        ->toThrow(ModelNotFoundException::class);
});

// =====================================================================
// Case 32
// =====================================================================

it('throws 422 VALIDATION_ERROR with errors.period_id when no period is given', function () {
    $this->actingAs($this->supervisorA);

    $exception = null;

    try {
        $this->service->buildSummary(null);
    } catch (ValidationException $e) {
        $exception = $e;
    }

    // Incomplete input — not a 404 for the empty string, and not a silently
    // empty report.
    expect($exception)->not->toBeNull();
    expect($exception->status)->toBe(422);
    expect($exception->errors())->toHaveKey('period_id');
});

// =====================================================================
// Case 33
// =====================================================================

it('throws 422 VALIDATION_ERROR when an Admin sends no business_unit_id', function () {
    $this->actingAs($this->admin);

    foreach ([
        fn () => $this->service->listPeriods(null),
        fn () => $this->service->buildSummary($this->periodA, null),
    ] as $call) {
        $exception = null;

        try {
            $call();
        } catch (ValidationException $e) {
            $exception = $e;
        }

        expect($exception)->not->toBeNull();
        expect($exception->status)->toBe(422);
        expect($exception->errors())->toHaveKey('business_unit_id');
        expect($exception->errors()['business_unit_id'][0])->toContain('Pilih mill');
    }
});

// =====================================================================
// Case 34 — THE SPY. "Never built" can only be proven by a call count of 0.
// =====================================================================

it('fails closed for a bound account with no mill and never calls allBusinessUnits()', function () {
    $noMillSupervisor = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $spy = new BoilerRoomReportAllBusinessUnitsSpy;

    $this->actingAs($noMillSupervisor);

    $exception = null;

    try {
        // Even while naming another mill, which is the input most likely to
        // tempt an implementation into "well, let me look the list up".
        $spy->resolveBusinessUnit((string) $this->businessUnitB->id);
    } catch (ValidationException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception->status)->toBe(422);
    expect($exception->errors())->toHaveKey('business_unit_id');
    expect($exception->errors()['business_unit_id'][0])->toContain('Hubungi Admin');

    // THE DECISIVE ASSERTION: zero recorded calls. Falling back to "every
    // mill" would turn one broken master-data row into a cross-mill leak,
    // and asserting only the 422 would pass just as happily against an
    // implementation that built the list first and threw afterwards.
    expect($spy->allBusinessUnitsCalls)->toBe(0);

    // The same on the report paths, not only on the resolver.
    foreach ([
        fn () => $spy->listPeriods(null),
        fn () => $spy->buildSummary($this->periodA, null),
    ] as $call) {
        try {
            $call();
        } catch (ValidationException|AuthorizationException $e) {
            // expected
        }
    }

    expect($spy->allBusinessUnitsCalls)->toBe(0);
});

// =====================================================================
// Case 35
// =====================================================================

it('ignores the client business_unit_id for Supervisor and Mill Management and answers with their own mill', function () {
    boilerRoomReportRecord($this->stationA, '2026-03-10', [['steam_pressure_bar' => 20.0]]);
    // Mill Beta's figures are unmistakable if they ever surface.
    boilerRoomReportRecord($this->stationB, '2026-03-10', [['steam_pressure_bar' => 900.0]]);

    foreach ([$this->supervisorA, $this->millManagementA] as $user) {
        $this->actingAs($user);

        // Discarded, not validated, not compared — and answered with 200
        // rather than 403, which would confirm the other mill exists.
        expect($this->service->resolveBusinessUnit((string) $this->businessUnitB->id))
            ->toBe((string) $this->businessUnitA->id);
        expect($this->service->resolveBusinessUnit((string) $this->businessUnitB->id))
            ->not->toBe((string) $this->businessUnitB->id);
        // A mill id that does not exist at all is discarded just the same.
        expect($this->service->resolveBusinessUnit('BU-LAIN'))
            ->toBe((string) $this->businessUnitA->id);

        $summary = $this->service->buildSummary($this->periodA, (string) $this->businessUnitB->id);

        expect($summary['business_unit']['name'])->toBe('Mill Alpha');
        expect($summary['metrics']['steam_pressure_bar']['avg'])->toBe(20.0);
        expect($summary['metrics']['steam_pressure_bar']['reading_count'])->toBe(1);
    }
});

// =====================================================================
// Case 36 — WIDENED 2026-09-25 (screen-137--laporan-boiler-room-mobile)
// =====================================================================

// This case used to assert the opposite: Operator refused with 403 on every
// public method and refused BEFORE the period lookup, with a note saying
// "unlike Cages & Tracks, this service was NOT widened for Operator: the
// mobile Boiler Room report (screen-137) does not exist, so there is no
// caller to widen for". THAT CALLER NOW EXISTS. screen-137 is the mobile
// Boiler Room report, and on 2026-09-25 the four /api/boiler-room-reports/*
// endpoints were opened to Operator — the same widening
// SterilizerReportService got for screen-135 (2026-09-23) and
// CagesTrackReportService for screen-136 (2026-09-24). The people who key
// the readings in are entitled to read them back.
//
// ACCEPTANCE IS THE CHEAP HALF. Asserting only "it no longer throws" would
// leave the expensive half untested, so the decisive assertion is block 2:
// resolveBusinessUnit() must land Operator in the MILL-BOUND branch, where
// a client-supplied business_unit_id is DISCARDED — not in the Admin
// branch, where it is HONOURED. Adding Operator to guardAccess() ALONE
// would drop it through to the Admin branch and produce a service that
// answers 200 for ANY mill an Operator cares to name: a cross-mill leak,
// not a display defect. Those two lines of the widening are one change,
// never two, and this case is what keeps the second one honest.
//
// businessUnitOptions() is deliberately NOT part of the widening and its
// 403 is kept below: Operator is bound to one mill and has no picker, so
// the list of mills is still Admin-only and the mobile view must never
// call it.
//
// The old "refused before the period is looked up" assertion keeps its
// SPIRIT rather than its letter: queries DO run for Operator now, so what
// is asserted is that every one of them is filtered to the Operator's OWN
// account business unit — and that another mill's period_id is still a
// flat 403.
it('accepts a Station Operator on every public method and binds it to its own mill instead of the Admin branch', function () {
    // Mill Beta's figures are large and unmistakable: if any of them
    // surfaced below, they could not be mistaken for a rounding difference.
    boilerRoomReportRecord($this->stationB, '2026-03-10', [['steam_pressure_bar' => 900.0]]);
    boilerRoomReportRecord($this->stationA, '2026-03-10', [['steam_pressure_bar' => 20.0]]);

    $otherMillPeriod = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType('boiler-room')
        ->range('2026-03-01', '2026-03-31')
        ->create();

    $this->actingAs($this->operatorA);

    $queries = [];

    DB::listen(function ($query) use (&$queries) {
        $queries[] = ['sql' => $query->sql, 'bindings' => $query->bindings];
    });

    // 1. ACCEPTED — not one AuthorizationException on any of the four
    //    public report paths that used to answer 403.
    $summary = $this->service->buildSummary($this->periodA);
    $periods = $this->service->listPeriods();
    $rows = iterator_to_array($this->service->buildExportRows($this->periodA), false);
    $response = $this->service->export($this->periodA, 'csv');

    expect($response)->toBeInstanceOf(StreamedResponse::class);

    // 2. THE POINT OF THIS CASE. A requested BU-B is DISCARDED and the
    //    ACCOUNT's mill comes back — the mill-bound branch, not the Admin
    //    branch (which would have echoed the requested id straight back).
    expect($this->service->resolveBusinessUnit((string) $this->businessUnitB->id))
        ->toBe((string) $this->businessUnitA->id);
    expect($this->service->resolveBusinessUnit((string) $this->businessUnitB->id))
        ->not->toBe((string) $this->businessUnitB->id);
    // Even a mill id that does not exist at all is discarded rather than
    // validated — the parameter is never used for this role.
    expect($this->service->resolveBusinessUnit('BU-LAIN'))
        ->toBe((string) $this->businessUnitA->id);

    // 3. NOT widened: the mill picker stays ADMIN ONLY. Kept from the old
    //    case verbatim.
    expect(fn () => $this->service->businessUnitOptions())
        ->toThrow(AuthorizationException::class);

    // 4. Widening the ROLE never widened the MILL: another mill's period id
    //    is still a flat 403, while the Operator's own period passes.
    expect(fn () => $this->service->authorizePeriod((string) $otherMillPeriod->id))
        ->toThrow(AuthorizationException::class);
    expect($this->service->authorizePeriod((string) $this->periodA->id)->id)
        ->toBe($this->periodA->id);

    // 5. The old ordering assertion's SPIRIT: the queries that DO run are
    //    scoped to the Operator's own business unit, every one of them.
    $recordQueries = collect($queries)
        ->filter(fn ($query) => str_contains($query['sql'], 'boiler_room_records'))
        ->values();

    expect($recordQueries)->not->toBeEmpty();

    foreach ($recordQueries as $query) {
        $bindings = array_map(fn ($binding) => (string) $binding, $query['bindings']);

        expect($bindings)->toContain((string) $this->businessUnitA->id);
        expect($bindings)->not->toContain((string) $this->businessUnitB->id);
    }

    // 6. And the figures themselves are BU-A's, never BU-B's 900.0.
    expect($summary['business_unit']['name'])->toBe('Mill Alpha');
    expect($summary['metrics']['steam_pressure_bar']['avg'])->toBe(20.0);
    expect($summary['metrics']['steam_pressure_bar']['reading_count'])->toBe(1);
    expect($rows)->toHaveCount(1);
    // listPeriods() answers with Mill Alpha's period only — Mill Beta's
    // period, created above with the same dates and station type, is absent.
    expect($periods)->toHaveCount(1);
    expect($periods[0]['id'])->toBe((string) $this->periodA->id);
});

// =====================================================================
// Case 37
// =====================================================================

it('exports one line per time slot with the record context columns repeated on every line', function () {
    $this->actingAs($this->supervisorA);

    boilerRoomReportRecord($this->stationA, '2026-03-04', [
        ['time_slot' => '07:00', 'steam_pressure_bar' => 20.0],
        ['time_slot' => '08:00', 'steam_pressure_bar' => 21.0],
        ['time_slot' => '09:00', 'steam_pressure_bar' => 22.0],
    ], ['boiler_room_id' => 'BLR-1', 'note' => 'uji', 'status' => 'synced']);

    $rows = iterator_to_array($this->service->buildExportRows($this->periodA), false);

    expect($rows)->toHaveCount(3);

    foreach ($rows as $row) {
        // Date / boiler unit / status / note, identical on all three lines.
        expect($row[0])->toBe('2026-03-04');
        expect($row[1])->toBe('BLR-1');
        expect($row[2])->toBe('synced');
        expect($row[3])->toBe('uji');
    }

    // Ordered by date, then by time_slot.
    expect(array_map(fn ($row) => $row[4], $rows))->toBe(['07:00', '08:00', '09:00']);

    // Every one of the fifteen measurement columns is emitted after the slot.
    expect($rows[0])->toHaveCount(4 + 1 + count(BoilerRoomRecordService::READING_FIELDS));
});

// =====================================================================
// Case 38
// =====================================================================

it('streams export rows lazily through a generator rather than one collection of everything', function () {
    $this->actingAs($this->supervisorA);

    // A few thousand rows — enough that materialising them all at once
    // would be a visible choice rather than an accident.
    boilerRoomReportBulkSeed($this->stationA, $this->supervisorA, '2026-03', 5000);

    $rows = $this->service->buildExportRows($this->periodA);

    // A Generator, not an array and not a Collection: the guard and the
    // row-limit check ran eagerly, the rows themselves did not.
    expect($rows)->toBeInstanceOf(Generator::class);
    expect($rows)->not->toBeInstanceOf(Collection::class);
    expect(is_array($rows))->toBeFalse();

    $count = 0;

    foreach ($rows as $row) {
        $count++;
    }

    expect($count)->toBe(5000);
});

// =====================================================================
// Case 39
// =====================================================================

it('throws 422 EXPORT_FAILED when the number of DETAIL rows exceeds 50.000', function () {
    $this->actingAs($this->supervisorA);

    // The ceiling counts DETAIL ROWS, not header records — one record
    // carries up to 24 of them, which is exactly the trap commit 8611974
    // fixed for the 18 station exports.
    $total = BoilerRoomReportService::EXPORT_ROW_LIMIT + 1;
    $headers = boilerRoomReportBulkSeed($this->stationA, $this->supervisorA, '2026-03', $total);

    expect($headers)->toBeLessThan(BoilerRoomReportService::EXPORT_ROW_LIMIT);
    expect(BoilerRoomDetail::count())->toBe($total);

    expect(fn () => $this->service->buildExportRows($this->periodA))
        ->toThrow(ExportFailedException::class);
    expect(fn () => $this->service->export($this->periodA, 'csv'))
        ->toThrow(ExportFailedException::class);
});

// =====================================================================
// Case 40
// =====================================================================

it('still exports at exactly the 50.000-row ceiling', function () {
    $this->actingAs($this->supervisorA);

    $total = BoilerRoomReportService::EXPORT_ROW_LIMIT;
    boilerRoomReportBulkSeed($this->stationA, $this->supervisorA, '2026-03', $total);

    expect(BoilerRoomDetail::count())->toBe($total);

    // Strictly greater than: exactly the limit still exports.
    $rows = $this->service->buildExportRows($this->periodA);

    $count = 0;

    foreach ($rows as $row) {
        $count++;
    }

    expect($count)->toBe($total);
    expect($this->service->export($this->periodA, 'csv'))->toBeInstanceOf(StreamedResponse::class);
});

// =====================================================================
// Case 41 — NO THRESHOLD FLAGGING ANYWHERE
// =====================================================================

it('returns extreme readings neutrally, with no threshold, severity or alert key anywhere in the payload', function () {
    $this->actingAs($this->supervisorA);

    boilerRoomReportRecord($this->stationA, '2026-03-18', [
        ['steam_pressure_bar' => 5.0],
        ['steam_pressure_bar' => 95.0],
    ]);

    $summary = $this->service->buildSummary($this->periodA);

    // The values themselves come back untouched.
    expect($summary['metrics']['steam_pressure_bar']['min'])->toBe(5.0);
    expect($summary['metrics']['steam_pressure_bar']['max'])->toBe(95.0);

    // Boiler Room has NO operational-target master (there is no
    // BoilerRoomOperationalTarget), so any threshold here would be a
    // statistic derived from the period itself and would read as a SAFETY
    // limit. Judgement belongs to the reader — so the absence is asserted.
    $encoded = strtolower(json_encode($summary));

    foreach ([
        'is_out_of_range', 'out_of_range', 'severity', 'threshold', 'alert',
        'warning', 'violation', 'danger', 'outlier', 'iqr', 'fence',
    ] as $forbidden) {
        expect($encoded)->not->toContain($forbidden);
    }
});
