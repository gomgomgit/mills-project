<?php

/**
 * ClarificationReportServiceTest — screen-132--laporan-clarification-web /
 * usecase-132--laporan-clarification-web (Laporan Periode Clarification).
 *
 * One test per unit_test_case in the screen's tech spec — ALL 45 cases, in
 * the spec's own order, one discrete `it()` each — against
 * App\Services\ClarificationReportService. Mirrors
 * tests/Unit/Services/BoilerRoomReportServiceTest.php (screen-131) in
 * structure and conventions.
 *
 * `uses(TestCase::class, RefreshDatabase::class)` is MANDATORY here:
 * tests/Pest.php binds Tests\TestCase only to Feature/, so without this line
 * the Unit suite has no application container at all — auth()->user(),
 * Eloquent and the factories would every one of them blow up.
 *
 * ----------------------------------------------------------------------
 * THE SIX TRAPS THIS FILE EXISTS TO PIN DOWN
 * ----------------------------------------------------------------------
 *  1. PRODUCTION IS DERIVED, NOT RECORDED. `clarification_details` has NO
 *     production column. production.total_ton is SUM(
 *     pure_oil_production_rate_ton_hour) over the rows that carry one,
 *     because the hourly grid makes one reading stand for one hour. Case 1
 *     locks it: rates 10,0 / 12,5 / 8,0 / 9,5 -> 40,0 ton with
 *     production.reading_count 4.
 *
 *  2. AN HOUR WITH NO RATE READING IS NOT AN HOUR THAT PRODUCED ZERO — the
 *     most dangerous trap on this screen BECAUSE BOTH READINGS GIVE THE
 *     SAME TOTAL. Only the average differs. Case 3 locks it with 10 filled
 *     rows of which only 4 carry a rate: the total stays 40,0 AND the rate
 *     average is 40,0/4 = 10,0, explicitly NOT 40,0/10 = 4,0. That
 *     `->not->toBe(4.0)` is the single most important assertion in this
 *     file: without it, an implementation that treats a missing rate as 0,0
 *     passes every total assertion here while deflating every average, and
 *     a mill with gappy recording becomes indistinguishable from a mill
 *     that stopped producing.
 *
 *  3. RATE AND DOWNTIME DO NOT NET EACH OTHER OFF. One hour recording rate
 *     10,0 ton/jam AND downtime 20 minutes still contributes 10,0 ton, not
 *     10 x 40/60 = 6,67 — see case 12, and read its comment: that formula
 *     is STILL PENDING the process owner and this suite asserts CURRENT
 *     BEHAVIOUR without deciding the question.
 *
 *  4. DOWNTIME ZERO IS NOT DOWNTIME UNRECORDED. total_mins is null when
 *     reading_count is 0 ("nobody measured") and 0 when it was measured and
 *     genuinely was zero ("it never stopped"). Cases 9, 10 and 11 assert
 *     both states AND assert they differ from each other — merging them
 *     publishes a reliability figure that was never taken.
 *
 *  5. A SEPARATE DENOMINATOR PER METRIC. All six numeric columns are
 *     nullable and one row may carry a sludge temperature and no rate at
 *     all, so every metric is averaged over ONLY the rows where THAT column
 *     is non-null and carries its own reading_count (cases 6 and 7). A
 *     shared denominator deflates every rarely-filled metric while still
 *     producing an entirely plausible number.
 *
 *  6. NULL IS NEVER ZERO, per metric, independently (cases 4, 8, 25).
 *
 * NO THRESHOLD ASSERTIONS ANYWHERE, on purpose. Clarification has no
 * operational-target master (there is no ClarificationOperationalTarget —
 * only Threshing / Pressing / Depricarping / Kernel Plant have one), so any
 * threshold here would be a statistic dressed up as a process limit. Case
 * 26 asserts the ABSENCE BY NAME, because a "do not flag" rule only
 * survives if something guards it.
 *
 * ----------------------------------------------------------------------
 * CROSS-MILL SECURITY IS ASSERTED AT ITS TWO DIFFERENT SHAPES
 * ----------------------------------------------------------------------
 *   - resolveBusinessUnit() IGNORES the client's business_unit_id for the
 *     THREE mill-bound roles — Supervisor / Mill Management / OPERATOR —
 *     answering the caller's own mill with 200, NOT a 403 (a 403 would
 *     confirm the other mill exists);
 *   - authorizePeriod() REFUSES another mill's period_id with 403, never
 *     404, so the refusal does not confirm that period exists (case 38).
 * Collapsing those into one assertion would hide whichever one broke.
 *
 * OPERATOR WAS WIDENED IN 2026-09-25, for
 * screen-138--laporan-clarification-mobile. Cases 37 and 38 used to assert
 * a blanket 403 for Operator on the grounds that the mobile Clarification
 * report did not exist; it exists now, and the four
 * /api/clarification-reports/* endpoints admit Operator. The widening is
 * THREE lines of code — the route middleware, guardAccess(), and
 * resolveBusinessUnit()'s MILL-BOUND branch — and the third is the one that
 * matters: without it Operator falls into the Admin branch, where the
 * client's business_unit_id is honoured, and can read any mill. Case 37 is
 * what keeps that line honest, by asserting the BINDING rather than the
 * acceptance. businessUnitOptions() stays Admin-only and still answers 403
 * for Operator; the WEB route /reports/clarification is untouched.
 *
 * And the fail-closed rule (case 33) is asserted as a SPY, because "the
 * all-mills list was never built" is a claim about something that did NOT
 * happen: only a recorded call count of zero can prove it.
 *
 * ----------------------------------------------------------------------
 * NOTES ON TWO SPEC/SCHEMA FRICTIONS THESE TESTS DELIBERATELY LIVE WITH
 * ----------------------------------------------------------------------
 *   - "filled row" is SEVEN columns, not six: ClarificationRecordService
 *     ::READING_FIELDS includes `findings`, so a row carrying nothing but a
 *     finding counts toward coverage while contributing to no metric. The
 *     report DELEGATES to that definition rather than carrying its own copy
 *     — asserted as a spy in case 15.
 *   - the canonical slot grid starts at 07:00 and wraps, so the raw-TIME
 *     slots of case 23 ('00:30:00', '22:15:00') cannot be entered through
 *     the write path. They are seeded directly here because the case is
 *     about ORDERING BY THE TIME VALUE, and '00:30' vs '10:00' is the only
 *     pair that proves no integer-hour cast happened.
 */

use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Models\BusinessUnit;
use App\Models\ClarificationDetail;
use App\Models\ClarificationRecord;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\Station;
use App\Models\User;
use App\Services\ClarificationRecordService;
use App\Services\ClarificationReportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * THE SPY for case 33.
 *
 * allBusinessUnits() is public on the service precisely so it can be
 * overridden here: the fail-closed rule for a Supervisor / Mill Management
 * account with no business_unit_id is only meaningful if it can be PROVEN
 * the whole-mill list was never even read, and a recorded call count of
 * zero is that proof. Asserting only the 422 would pass just as happily
 * against an implementation that built the list first and threw afterwards.
 */
class ClarificationReportAllBusinessUnitsSpy extends ClarificationReportService
{
    public int $allBusinessUnitsCalls = 0;

    public function allBusinessUnits(): Collection
    {
        $this->allBusinessUnitsCalls++;

        return parent::allBusinessUnits();
    }
}

/**
 * THE SPY for case 15.
 *
 * "The report delegates the definition of a filled row" is, again, a claim
 * about what the code DID — so it is proven by counting the calls that
 * reached ClarificationRecordService::isRowFilled(). Bound into the
 * container, which is where ClarificationReportService::recordService()
 * resolves it from.
 */
class ClarificationReportRowFilledSpy extends ClarificationRecordService
{
    public int $isRowFilledCalls = 0;

    /** @var list<array<string, mixed>> */
    public array $isRowFilledArgs = [];

    public function isRowFilled(array $row): bool
    {
        $this->isRowFilledCalls++;
        $this->isRowFilledArgs[] = $row;

        return parent::isRowFilled($row);
    }
}

/**
 * One clarification_records header on $date for $station, plus one
 * clarification_details row per entry of $rows.
 *
 * Each entry is a plain column => value map. `time_slot` is filled in from
 * ClarificationRecordService::canonicalTimeSlots() by position unless the
 * entry supplies its own, so a fixture that does not care about slots never
 * has to spell them out — while UNIQUE(clarification_record_id, time_slot)
 * is still respected.
 *
 * @param  list<array<string, mixed>>  $rows
 */
function clarificationReportRecord(Station $station, string $date, array $rows = [], array $overrides = []): ClarificationRecord
{
    $record = ClarificationRecord::factory()->forStation($station)->onDate($date)->create(array_merge([
        'clarification_id' => 'CLF-01',
        'note' => null,
    ], $overrides));

    $slots = ClarificationRecordService::canonicalTimeSlots();

    foreach (array_values($rows) as $index => $row) {
        ClarificationDetail::factory()->forRecord($record)->create(array_merge([
            'time_slot' => $slots[$index % count($slots)],
        ], $row));
    }

    return $record;
}

/**
 * Shorthand: $count detail rows, each filling only sludge_tank_temp_c with
 * $temp — the "this row counts as filled but carries NO rate" shape that
 * every derived-production trap needs.
 *
 * @return list<array<string, mixed>>
 */
function clarificationReportSludgeRows(int $count, float $temp = 87.0, array $extra = []): array
{
    $rows = [];

    for ($index = 0; $index < $count; $index++) {
        $rows[] = array_merge(['sludge_tank_temp_c' => $temp], $extra);
    }

    return $rows;
}

/**
 * The 10-row fixture behind cases 3, 6 and 7: TEN filled rows of which only
 * FOUR carry a production rate, and every metric filled a different number
 * of times.
 *
 *   clarification_tank_temp_c        rows 0..6   ->  7 readings
 *   oil_tank_temperature_c           rows 0..4   ->  5 readings
 *   sludge_tank_temp_c               rows 1..9   ->  9 readings
 *   buffer_tank_level_percent        rows 0..2   ->  3 readings
 *   pure_oil_production_rate_ton_hour rows 0..3  ->  4 readings (sum 40,0)
 *   downtime_mins                    rows 0..5   ->  6 readings
 *
 * Every one of the ten rows fills at least one column, so filled_slots is
 * 10 while NOT ONE metric's denominator is 10 — which is the whole point.
 *
 * @return list<array<string, mixed>>
 */
function clarificationReportMixedRows(bool $withRate = true): array
{
    $rates = [10.0, 12.5, 8.0, 9.5];
    $rows = [];

    for ($index = 0; $index < 10; $index++) {
        $rows[] = [
            'clarification_tank_temp_c' => $index <= 6 ? 93.0 : null,
            'oil_tank_temperature_c' => $index <= 4 ? 97.0 : null,
            'sludge_tank_temp_c' => $index >= 1 ? 87.0 : null,
            'buffer_tank_level_percent' => $index <= 2 ? 72.0 : null,
            'pure_oil_production_rate_ton_hour' => ($withRate && $index <= 3) ? $rates[$index] : null,
            'downtime_mins' => $index <= 5 ? 10.0 : null,
        ];
    }

    return $rows;
}

/** Every SQL statement run inside $callback, for the "no SQL aggregate" case. */
function clarificationReportQueriesDuring(callable $callback): array
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
function clarificationReportBulkSeed(Station $station, User $author, string $dateBase, int $totalDetailRows): int
{
    $now = now()->toDateTimeString();
    $slots = ClarificationRecordService::canonicalTimeSlots();

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
            'production_line_id' => $station->production_line_id,
            'clarification_id' => 'CLF-BULK-'.$recordIndex,
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
                'clarification_record_id' => $recordId,
                'time_slot' => $slot,
                'clarification_tank_temp_c' => 93.0,
                'oil_tank_temperature_c' => null,
                'sludge_tank_temp_c' => null,
                'buffer_tank_level_percent' => null,
                'pure_oil_production_rate_ton_hour' => null,
                'downtime_mins' => null,
                'findings' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $written++;
        }

        $recordIndex++;
    }

    foreach (array_chunk($recordRows, 500) as $chunk) {
        DB::table('clarification_records')->insert($chunk);
    }

    foreach (array_chunk($detailRows, 1000) as $chunk) {
        DB::table('clarification_details')->insert($chunk);
    }

    return $recordIndex;
}

beforeEach(function () {
    $this->service = new ClarificationReportService;

    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->clarification()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->clarification()->create();

    $this->supervisorA = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagementA = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operatorA = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    // Admin is the one role not bound to a mill — users.business_unit_id is
    // NULL, which is exactly why the mill picker exists.
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('clarification')
        ->range('2026-03-01', '2026-03-31')
        ->open()
        ->named('Periode Maret Alpha')
        ->create();

    /** Slots per unit per day — the canonical grid the input screens use. */
    $this->slotsPerDay = count(ClarificationRecordService::canonicalTimeSlots());
});

// =====================================================================
// GROUP A — DERIVED PRODUCTION (the behaviour that defines this screen)
// =====================================================================

// ---------------------------------------------------------------------
// Case 1
// ---------------------------------------------------------------------
it('derives production.total_ton as the SUM of the hourly rate: 10,0 + 12,5 + 8,0 + 9,5 = 40,0 over 4 readings', function () {
    $this->actingAs($this->supervisorA);

    clarificationReportRecord($this->stationA, '2026-03-02', [
        ['time_slot' => '06:00:00', 'pure_oil_production_rate_ton_hour' => 10.0],
        ['time_slot' => '07:00:00', 'pure_oil_production_rate_ton_hour' => 12.5],
        ['time_slot' => '08:00:00', 'pure_oil_production_rate_ton_hour' => 8.0],
        ['time_slot' => '09:00:00', 'pure_oil_production_rate_ton_hour' => 9.5],
    ]);

    $summary = $this->service->buildSummary($this->periodA);

    expect($summary['production']['total_ton'])->toBe(40.0);
    expect($summary['production']['reading_count'])->toBe(4);

    // THE PROOF THAT IT IS SUMMED FROM THE RATES rather than read from some
    // production column: change one rate by 1,0 and the total moves by 1,0.
    ClarificationDetail::query()
        ->where('pure_oil_production_rate_ton_hour', 10.0)
        ->update(['pure_oil_production_rate_ton_hour' => 11.0]);

    $again = (new ClarificationReportService)->buildSummary($this->periodA);

    expect($again['production']['total_ton'])->toBe(41.0);
    expect($again['production']['reading_count'])->toBe(4);
});

// ---------------------------------------------------------------------
// Case 2
// ---------------------------------------------------------------------
it('reads no production column at all, because the schema has none — only the hourly rate column', function () {
    $this->actingAs($this->supervisorA);

    clarificationReportRecord($this->stationA, '2026-03-02', [
        ['pure_oil_production_rate_ton_hour' => 10.0],
    ]);

    // The schema itself: there is nothing to read.
    foreach (['production', 'production_ton', 'total_production', 'produksi_ton', 'produksi'] as $column) {
        expect(Schema::hasColumn('clarification_details', $column))->toBeFalse();
        expect(Schema::hasColumn('clarification_records', $column))->toBeFalse();
    }

    // And the service names exactly one source for it.
    expect(ClarificationReportService::PRODUCTION_RATE_COLUMN)->toBe('pure_oil_production_rate_ton_hour');
    expect(ClarificationReportService::NUMERIC_METRICS)->toContain('pure_oil_production_rate_ton_hour');

    foreach (ClarificationReportService::NUMERIC_METRICS as $metric) {
        expect($metric)->not->toContain('production_ton');
        expect($metric)->not->toContain('produksi');
    }

    $queries = clarificationReportQueriesDuring(function () {
        $this->service->buildSummary($this->periodA);
    });

    foreach ($queries as $sql) {
        $normalised = strtolower($sql);

        expect($normalised)->not->toContain('total_production');
        expect($normalised)->not->toContain('production_ton');
        expect($normalised)->not->toContain('produksi_ton');
    }
});

// ---------------------------------------------------------------------
// Case 3 — THE MOST IMPORTANT TEST IN THIS FILE
// ---------------------------------------------------------------------
it('never treats an hour without a rate reading as an hour that produced zero: 10 filled rows, 4 rates, avg 10,0 NOT 4,0', function () {
    $this->actingAs($this->supervisorA);

    // TEN filled rows; only FOUR of them carry a rate (10,0 / 12,5 / 8,0 /
    // 9,5 = 40,0). The other six are filled through other columns.
    clarificationReportRecord($this->stationA, '2026-03-04', clarificationReportMixedRows());

    $summary = $this->service->buildSummary($this->periodA);

    // All ten rows ARE filled — the coverage figure sees them.
    expect($summary['coverage']['filled_slots'])->toBe(10);
    expect($summary['total']['reading_rows'])->toBe(10);

    // The total is the same under BOTH readings, which is exactly why this
    // trap hides itself: summing 40,0 over 4 rates and summing 40,0 over 4
    // rates plus six zeroes both give 40,0.
    expect($summary['production']['total_ton'])->toBe(40.0);
    expect($summary['production']['reading_count'])->toBe(4);

    // ONLY THE AVERAGE TELLS THEM APART. 40,0 / 4 = 10,0 — never 40,0 / 10.
    expect($summary['production']['avg_rate_ton_hour'])->toBe(10.0);
    // THE DECISIVE ASSERTION OF THIS ENTIRE SUITE: a rate-less row must not
    // contribute 0,0 to the denominator. 4,0 is the number an
    // implementation that zero-fills would produce, and it looks entirely
    // plausible — a mill with gappy recording would read as a mill that had
    // nearly stopped producing.
    expect($summary['production']['avg_rate_ton_hour'])->not->toBe(4.0);
    expect($summary['metrics']['pure_oil_production_rate_ton_hour']['avg'])->toBe(10.0);
    expect($summary['metrics']['pure_oil_production_rate_ton_hour']['avg'])->not->toBe(4.0);
    expect($summary['metrics']['pure_oil_production_rate_ton_hour']['reading_count'])->toBe(4);

    // And the six rate-less rows contributed no 0,0 to the extremes either.
    expect($summary['metrics']['pure_oil_production_rate_ton_hour']['min'])->toBe(8.0);
    expect($summary['metrics']['pure_oil_production_rate_ton_hour']['min'])->not->toBe(0.0);
});

// ---------------------------------------------------------------------
// Case 4
// ---------------------------------------------------------------------
it('returns null production, never zero, when the rate was never recorded in the whole period', function () {
    $this->actingAs($this->supervisorA);

    // Nine filled rows, not one of them carrying a rate.
    clarificationReportRecord($this->stationA, '2026-03-05', clarificationReportSludgeRows(9, 87.0));

    $summary = $this->service->buildSummary($this->periodA);

    expect($summary['production']['total_ton'])->toBeNull();
    expect($summary['production']['total_ton'])->not->toBe(0);
    expect($summary['production']['total_ton'])->not->toBe(0.0);
    // The spec's derived cases name this figure avg_production_per_day_ton;
    // the endpoint schema names it avg_per_day_ton. Both are emitted as
    // aliases of one computed value, so both are asserted.
    expect($summary['production']['avg_production_per_day_ton'])->toBeNull();
    expect($summary['production']['avg_per_day_ton'])->toBeNull();
    expect($summary['production']['min_rate_ton_hour'])->toBeNull();
    expect($summary['production']['avg_rate_ton_hour'])->toBeNull();
    expect($summary['production']['max_rate_ton_hour'])->toBeNull();
    expect($summary['production']['reading_count'])->toBe(0);

    expect($summary['metrics']['pure_oil_production_rate_ton_hour'])->toBe([
        'min' => null, 'avg' => null, 'max' => null, 'reading_count' => 0,
    ]);
});

// ---------------------------------------------------------------------
// Case 5
// ---------------------------------------------------------------------
it('keeps the temperature metrics entirely normal even when the rate column is empty across the whole period', function () {
    $this->actingAs($this->supervisorA);

    // Nine rows of sludge temperature 80,0 .. 88,0, and no rate anywhere.
    $rows = [];

    for ($index = 0; $index < 9; $index++) {
        $rows[] = ['sludge_tank_temp_c' => 80.0 + $index];
    }

    clarificationReportRecord($this->stationA, '2026-03-06', $rows);

    $summary = $this->service->buildSummary($this->periodA);

    // Independent per metric: an empty rate column leaves sludge untouched.
    expect($summary['metrics']['sludge_tank_temp_c']['reading_count'])->toBe(9);
    expect($summary['metrics']['sludge_tank_temp_c']['min'])->toBe(80.0);
    expect($summary['metrics']['sludge_tank_temp_c']['avg'])->toBe(84.0);
    expect($summary['metrics']['sludge_tank_temp_c']['max'])->toBe(88.0);

    expect($summary['production']['reading_count'])->toBe(0);
    expect($summary['production']['total_ton'])->toBeNull();
});

// =====================================================================
// GROUP B — SEPARATE DENOMINATORS / NULL IS NOT ZERO
// =====================================================================

// ---------------------------------------------------------------------
// Case 6
// ---------------------------------------------------------------------
it('averages the rate over its own 4 rows and sludge over its own 9, never over the 10 filled rows they share', function () {
    $this->actingAs($this->supervisorA);

    clarificationReportRecord($this->stationA, '2026-03-07', clarificationReportMixedRows());

    $summary = $this->service->buildSummary($this->periodA);

    expect($summary['production']['reading_count'])->toBe(4);
    expect($summary['metrics']['sludge_tank_temp_c']['reading_count'])->toBe(9);

    // Each average divided by ITS OWN denominator. 40,0/4 = 10,0 and
    // (9 x 87,0)/9 = 87,0 — a shared denominator of 10 would give 4,0 and
    // 78,3, both of which look perfectly reasonable.
    expect($summary['production']['avg_rate_ton_hour'])->toBe(10.0);
    expect($summary['metrics']['sludge_tank_temp_c']['avg'])->toBe(87.0);
    expect($summary['production']['avg_rate_ton_hour'])->not->toBe(4.0);
    expect($summary['metrics']['sludge_tank_temp_c']['avg'])->not->toBe(78.3);

    // There is deliberately NO shared reading_count anywhere in the payload.
    expect($summary['metrics'])->not->toHaveKey('reading_count');
    expect($summary)->not->toHaveKey('reading_count');
    expect($summary['coverage']['filled_slots'])->toBe(10);
});

// ---------------------------------------------------------------------
// Case 7
// ---------------------------------------------------------------------
it('gives every one of the six metrics its own reading_count: 7 / 5 / 9 / 3 / 4 / 6 over the same ten rows', function () {
    $this->actingAs($this->supervisorA);

    clarificationReportRecord($this->stationA, '2026-03-08', clarificationReportMixedRows());

    $summary = $this->service->buildSummary($this->periodA);

    $expected = [
        'clarification_tank_temp_c' => 7,
        'oil_tank_temperature_c' => 5,
        'sludge_tank_temp_c' => 9,
        'buffer_tank_level_percent' => 3,
        'pure_oil_production_rate_ton_hour' => 4,
        'downtime_mins' => 6,
    ];

    foreach ($expected as $metric => $count) {
        expect($summary['metrics'][$metric]['reading_count'])->toBe($count);
        // None of them is the shared 10 — that is the failure shape.
        expect($summary['metrics'][$metric]['reading_count'])->not->toBe(10);
    }

    // Each average taken over its own denominator, with a constant value per
    // metric so the denominator is the only thing that could move it.
    expect($summary['metrics']['clarification_tank_temp_c']['avg'])->toBe(93.0);
    expect($summary['metrics']['oil_tank_temperature_c']['avg'])->toBe(97.0);
    expect($summary['metrics']['sludge_tank_temp_c']['avg'])->toBe(87.0);
    expect($summary['metrics']['buffer_tank_level_percent']['avg'])->toBe(72.0);
    expect($summary['metrics']['pure_oil_production_rate_ton_hour']['avg'])->toBe(10.0);
    expect($summary['metrics']['downtime_mins']['avg'])->toBe(10.0);
});

// ---------------------------------------------------------------------
// Case 8
// ---------------------------------------------------------------------
it('returns null, never zero, for a metric never filled — independently of every other metric', function () {
    $this->actingAs($this->supervisorA);

    // Twelve rows: sludge everywhere, buffer level never.
    clarificationReportRecord($this->stationA, '2026-03-09', clarificationReportSludgeRows(12, 86.5));

    $summary = $this->service->buildSummary($this->periodA);

    $buffer = $summary['metrics']['buffer_tank_level_percent'];

    expect($buffer['min'])->toBeNull();
    expect($buffer['avg'])->toBeNull();
    expect($buffer['max'])->toBeNull();
    // Explicitly NOT the zero that would read as "we measured the buffer
    // level and it came out at 0 %".
    expect($buffer['min'])->not->toBe(0);
    expect($buffer['avg'])->not->toBe(0);
    expect($buffer['max'])->not->toBe(0);
    expect($buffer['min'])->not->toBe(0.0);
    expect($buffer['avg'])->not->toBe(0.0);
    expect($buffer['max'])->not->toBe(0.0);
    expect($buffer['reading_count'])->toBe(0);

    // Untouched by the empty one.
    expect($summary['metrics']['sludge_tank_temp_c']['reading_count'])->toBe(12);
    expect($summary['metrics']['sludge_tank_temp_c']['avg'])->toBe(86.5);
});

// =====================================================================
// GROUP C — DOWNTIME: NULL vs 0 vs POSITIVE, AND NO NETTING
// =====================================================================

// ---------------------------------------------------------------------
// Case 9
// ---------------------------------------------------------------------
it('returns downtime.total_mins null — NOT 0 — when downtime was never recorded', function () {
    $this->actingAs($this->supervisorA);

    clarificationReportRecord($this->stationA, '2026-03-10', clarificationReportSludgeRows(6, 87.0));

    $summary = $this->service->buildSummary($this->periodA);

    // null means "nobody measured". 0 would mean "it never stopped" — a
    // reliability claim that was never taken.
    expect($summary['downtime']['total_mins'])->toBeNull();
    expect($summary['downtime']['total_mins'])->not->toBe(0);
    expect($summary['downtime']['total_mins'])->not->toBe(0.0);
    expect($summary['downtime']['avg_downtime_per_day_mins'])->toBeNull();
    expect($summary['downtime']['avg_per_day_mins'])->toBeNull();
    expect($summary['downtime']['hours_with_downtime'])->toBe(0);
    expect($summary['downtime']['reading_count'])->toBe(0);

    expect($summary['metrics']['downtime_mins'])->toBe([
        'min' => null, 'avg' => null, 'max' => null, 'reading_count' => 0,
    ]);
});

// ---------------------------------------------------------------------
// Case 10
// ---------------------------------------------------------------------
it('returns downtime.total_mins 0 with reading_count 5 when zero was recorded, and that state differs from unrecorded', function () {
    $this->actingAs($this->supervisorA);

    $rows = [];

    for ($index = 0; $index < 5; $index++) {
        $rows[] = ['downtime_mins' => 0.0];
    }

    clarificationReportRecord($this->stationA, '2026-03-11', $rows);

    $summary = $this->service->buildSummary($this->periodA);

    // Recorded, and genuinely zero: the mill never stopped.
    expect($summary['downtime']['total_mins'])->toBe(0);
    expect($summary['downtime']['total_mins'])->not->toBeNull();
    expect($summary['downtime']['reading_count'])->toBe(5);
    expect($summary['downtime']['hours_with_downtime'])->toBe(0);
    expect($summary['metrics']['downtime_mins']['reading_count'])->toBe(5);
    expect($summary['metrics']['downtime_mins']['avg'])->toBe(0.0);

    // THE PAIR (total_mins, reading_count) IS WHAT MAKES THE TWO STATES
    // DISTINGUISHABLE FROM THE RESPONSE — asserted here side by side against
    // a period where downtime was never written down at all, because "they
    // are different" is the contract, not merely "each is correct".
    $neverRecorded = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('clarification')
        ->range('2026-05-01', '2026-05-31')->named('Periode Mei Tanpa Downtime')->open()->create();

    clarificationReportRecord($this->stationA, '2026-05-10', clarificationReportSludgeRows(5, 87.0), [
        'clarification_id' => 'CLF-MEI',
    ]);

    $other = (new ClarificationReportService)->buildSummary($neverRecorded);

    expect($other['downtime']['total_mins'])->toBeNull();
    expect($other['downtime']['reading_count'])->toBe(0);

    expect($summary['downtime']['total_mins'])->not->toBe($other['downtime']['total_mins']);
    expect($summary['downtime']['reading_count'])->not->toBe($other['downtime']['reading_count']);
});

// ---------------------------------------------------------------------
// Case 11
// ---------------------------------------------------------------------
it('mixes recorded-zero, recorded-positive and unrecorded downtime without ever confusing them', function () {
    $this->actingAs($this->supervisorA);

    // 0, 0, 15 and one row with NO downtime at all (filled via sludge).
    clarificationReportRecord($this->stationA, '2026-03-12', [
        ['downtime_mins' => 0.0],
        ['downtime_mins' => 0.0],
        ['downtime_mins' => 15.0],
        ['sludge_tank_temp_c' => 87.0],
    ]);

    $summary = $this->service->buildSummary($this->periodA);

    expect($summary['downtime']['total_mins'])->toBe(15);
    // Non-null rows only — the fourth row is not a zero.
    expect($summary['downtime']['reading_count'])->toBe(3);
    // Strictly positive rows only — the two recorded zeroes are readings,
    // not hours with downtime.
    expect($summary['downtime']['hours_with_downtime'])->toBe(1);
    expect($summary['metrics']['downtime_mins']['min'])->toBe(0.0);
    expect($summary['metrics']['downtime_mins']['max'])->toBe(15.0);
    expect($summary['metrics']['downtime_mins']['reading_count'])->toBe(3);
    expect($summary['coverage']['filled_slots'])->toBe(4);
});

// ---------------------------------------------------------------------
// Case 12
// ---------------------------------------------------------------------
it('does not net rate against downtime: one hour of rate 10,0 and downtime 20 still contributes 10,0 ton', function () {
    $this->actingAs($this->supervisorA);

    clarificationReportRecord($this->stationA, '2026-03-13', [
        ['pure_oil_production_rate_ton_hour' => 10.0, 'downtime_mins' => 20.0],
    ]);

    $summary = $this->service->buildSummary($this->periodA);

    // >> OPEN QUESTION, DELIBERATELY NOT DECIDED HERE.
    // >> This asserts CURRENT BEHAVIOUR, which follows the formula the user
    // >> fixed during scoping ("sum laju x 1 jam"). The screen spec records
    // >> an OPEN question — still pending the process owner — about whether
    // >> downtime should be subtracted when the Operator's rate is an
    // >> INSTANTANEOUS rate rather than the mean across the hour. If that is
    // >> ever answered the other way the formula becomes
    // >> rate x (60 - downtime_mins) / 60 and THIS TEST must change with it.
    // >> Until the owner answers, neither the code nor this test decides.
    expect($summary['production']['total_ton'])->toBe(10.0);
    // 10 x 40/60 = 6,67 is the netted figure. It is not what this report
    // publishes.
    expect($summary['production']['total_ton'])->not->toBe(6.67);
    expect($summary['production']['reading_count'])->toBe(1);

    // Which is precisely why total downtime is published BESIDE production:
    // without it the reader cannot judge the production figure at all.
    expect($summary['downtime']['total_mins'])->toBe(20);
    expect($summary['downtime']['hours_with_downtime'])->toBe(1);
    expect($summary['downtime']['reading_count'])->toBe(1);
});

// =====================================================================
// GROUP D — COVERAGE AND THE FILLED-ROW DEFINITION
// =====================================================================

// ---------------------------------------------------------------------
// Case 13
// ---------------------------------------------------------------------
it('computes coverage.expected_slots as units x days x 24: two units over a three-day period is 144', function () {
    $this->actingAs($this->supervisorA);

    $period = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('clarification')
        ->range('2026-04-01', '2026-04-03')->named('Periode Tiga Hari')->open()->create();

    clarificationReportRecord($this->stationA, '2026-04-01', clarificationReportSludgeRows(2, 87.0), [
        'clarification_id' => 'CLF-01',
    ]);
    clarificationReportRecord($this->stationA, '2026-04-02', clarificationReportSludgeRows(2, 88.0), [
        'clarification_id' => 'CLF-02',
    ]);

    $summary = $this->service->buildSummary($period);

    expect($summary['coverage']['unit_count'])->toBe(2);
    expect($summary['coverage']['days_in_period'])->toBe(3);
    expect($summary['coverage']['slots_per_unit_per_day'])->toBe(24);
    expect($summary['coverage']['expected_slots'])->toBe(144);
    expect($summary['coverage']['expected_slots'])->toBe(2 * 3 * 24);
});

// ---------------------------------------------------------------------
// Case 14
// ---------------------------------------------------------------------
it('counts only rows with at least one non-time_slot column filled: 9 of 12, the three all-null rows excluded', function () {
    $this->actingAs($this->supervisorA);

    $period = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('clarification')
        ->range('2026-04-01', '2026-04-03')->named('Periode Tiga Hari')->open()->create();

    $rows = [];

    for ($index = 0; $index < 12; $index++) {
        // Rows 9, 10 and 11 are ENTIRELY null — an untouched slot, already
        // reported as missing by coverage, never counted as recorded.
        $rows[] = $index < 9 ? ['sludge_tank_temp_c' => 87.0] : [];
    }

    clarificationReportRecord($this->stationA, '2026-04-01', $rows, ['clarification_id' => 'CLF-01']);
    // A second unit so expected_slots is the 144 of case 13 and 16.
    clarificationReportRecord($this->stationA, '2026-04-02', [], ['clarification_id' => 'CLF-02']);

    $summary = $this->service->buildSummary($period);

    expect(ClarificationDetail::count())->toBe(12);
    expect($summary['coverage']['filled_slots'])->toBe(9);
    expect($summary['coverage']['filled_slots'])->not->toBe(12);
    expect($summary['total']['reading_rows'])->toBe(9);
    expect($summary['coverage']['expected_slots'])->toBe(144);
});

// ---------------------------------------------------------------------
// Case 15 — THE DELEGATION SPY
// ---------------------------------------------------------------------
it('delegates the definition of a filled row to ClarificationRecordService instead of defining a second one', function () {
    $this->actingAs($this->supervisorA);

    $spy = new ClarificationReportRowFilledSpy;
    $this->app->instance(ClarificationRecordService::class, $spy);

    clarificationReportRecord($this->stationA, '2026-03-14', [
        ['sludge_tank_temp_c' => 87.0],
        ['pure_oil_production_rate_ton_hour' => 10.0],
        [],
    ]);

    $summary = (new ClarificationReportService)->buildSummary($this->periodA);

    // THE DECISIVE ASSERTION: the arbiter was actually consulted, once per
    // detail row. A report carrying its own copy of the predicate would
    // produce the same filled_slots today and drift from the input screens
    // the moment either definition changes.
    expect($spy->isRowFilledCalls)->toBe(3);
    expect($summary['coverage']['filled_slots'])->toBe(2);

    // It is asked about the SAME seven columns the input screens enforce —
    // including `findings`, which counts toward coverage while contributing
    // to no metric.
    foreach ($spy->isRowFilledArgs as $args) {
        expect(array_keys($args))->toEqualCanonicalizing(ClarificationRecordService::READING_FIELDS);
    }

    expect(ClarificationRecordService::READING_FIELDS)->toContain('findings');

    // And there is no second copy of the field list on the report service.
    $constants = (new ReflectionClass(ClarificationReportService::class))->getConstants();

    foreach ($constants as $name => $value) {
        expect($value)->not->toBe(ClarificationRecordService::READING_FIELDS);
        expect($name)->not->toBe('READING_FIELDS');
    }
});

// ---------------------------------------------------------------------
// Case 16
// ---------------------------------------------------------------------
it('computes coverage_percent from filled_slots over expected_slots: 9 of 144 is 6.25 per cent', function () {
    $this->actingAs($this->supervisorA);

    $period = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('clarification')
        ->range('2026-04-01', '2026-04-03')->named('Periode Tiga Hari')->open()->create();

    clarificationReportRecord($this->stationA, '2026-04-01', clarificationReportSludgeRows(9, 87.0), [
        'clarification_id' => 'CLF-01',
    ]);
    clarificationReportRecord($this->stationA, '2026-04-02', [], ['clarification_id' => 'CLF-02']);

    $summary = $this->service->buildSummary($period);

    expect($summary['coverage']['filled_slots'])->toBe(9);
    expect($summary['coverage']['expected_slots'])->toBe(144);
    // Two decimals, not one: rounding 6,25 to 6,3 loses the only digit that
    // separates a badly recorded period from a catastrophic one.
    expect($summary['coverage']['coverage_percent'])->toBe(6.25);
});

// =====================================================================
// GROUP E — daily[]: THREE TEMPERATURES ON ONE ROW, AND DAILY PRODUCTION
// =====================================================================

// ---------------------------------------------------------------------
// Case 17
// ---------------------------------------------------------------------
it('ships all three tank temperatures on the SAME daily[] object per date, so one chart with one axis can be drawn', function () {
    $this->actingAs($this->supervisorA);

    clarificationReportRecord($this->stationA, '2026-03-15', [
        ['clarification_tank_temp_c' => 93.0, 'oil_tank_temperature_c' => 97.0, 'sludge_tank_temp_c' => 87.0],
        ['clarification_tank_temp_c' => 95.0, 'oil_tank_temperature_c' => 99.0, 'sludge_tank_temp_c' => 89.0],
    ], ['clarification_id' => 'CLF-01']);

    clarificationReportRecord($this->stationA, '2026-03-16', [
        ['clarification_tank_temp_c' => 91.0, 'oil_tank_temperature_c' => 95.0, 'sludge_tank_temp_c' => 85.0],
    ], ['clarification_id' => 'CLF-01']);

    $summary = $this->service->buildSummary($this->periodA);

    expect($summary['daily'])->toHaveCount(2);

    foreach ($summary['daily'] as $row) {
        // THE POINT: date plus ALL THREE temperatures on ONE object. What is
        // read is the DIFFERENCE between the tanks, and three separate
        // payload shapes would invite three charts — which satisfies "tren
        // suhu antar tangki" literally while destroying its meaning.
        expect($row)->toHaveKey('date');
        expect($row)->toHaveKeys([
            'avg_clarification_tank_temp_c',
            'avg_oil_tank_temperature_c',
            'avg_sludge_tank_temp_c',
        ]);
        // The endpoint-schema names of the same three values.
        expect($row)->toHaveKeys([
            'clarification_tank_temp_avg',
            'oil_tank_temperature_avg',
            'sludge_tank_temp_avg',
        ]);

        expect($row['avg_clarification_tank_temp_c'])->toBe($row['clarification_tank_temp_avg']);
        expect($row['avg_oil_tank_temperature_c'])->toBe($row['oil_tank_temperature_avg']);
        expect($row['avg_sludge_tank_temp_c'])->toBe($row['sludge_tank_temp_avg']);
    }

    $first = $summary['daily'][0];

    expect($first['date'])->toBe('2026-03-15');
    expect($first['avg_clarification_tank_temp_c'])->toBe(94.0);
    expect($first['avg_oil_tank_temperature_c'])->toBe(98.0);
    expect($first['avg_sludge_tank_temp_c'])->toBe(88.0);
    // The gap between the tanks — the thing the chart exists to show —
    // survives on one comparable axis.
    expect($first['avg_oil_tank_temperature_c'] - $first['avg_clarification_tank_temp_c'])->toBe(4.0);
    expect($first['avg_clarification_tank_temp_c'] - $first['avg_sludge_tank_temp_c'])->toBe(6.0);
});

// ---------------------------------------------------------------------
// Case 18
// ---------------------------------------------------------------------
it('carries daily production with its own reading count, and null — not 0,0 ton — on a day with no rate', function () {
    $this->actingAs($this->supervisorA);

    clarificationReportRecord($this->stationA, '2026-03-17', [
        ['pure_oil_production_rate_ton_hour' => 10.0],
        ['pure_oil_production_rate_ton_hour' => 12.0],
        ['pure_oil_production_rate_ton_hour' => 8.0],
    ], ['clarification_id' => 'CLF-01']);

    clarificationReportRecord($this->stationA, '2026-03-18', clarificationReportSludgeRows(4, 87.0), [
        'clarification_id' => 'CLF-01',
    ]);

    $summary = $this->service->buildSummary($this->periodA);

    expect($summary['daily'])->toHaveCount(2);

    expect($summary['daily'][0]['date'])->toBe('2026-03-17');
    expect($summary['daily'][0]['production']['total_ton'])->toBe(30.0);
    expect($summary['daily'][0]['production']['reading_count'])->toBe(3);
    expect($summary['daily'][0]['production_ton'])->toBe(30.0);

    // Day two recorded four filled slots and NOT ONE rate. On the daily
    // trend that is the difference between "we produced nothing that day"
    // and "nobody wrote the rate down that day".
    expect($summary['daily'][1]['date'])->toBe('2026-03-18');
    expect($summary['daily'][1]['production']['total_ton'])->toBeNull();
    expect($summary['daily'][1]['production']['total_ton'])->not->toBe(0.0);
    expect($summary['daily'][1]['production']['reading_count'])->toBe(0);
    expect($summary['daily'][1]['production_ton'])->toBeNull();
    // The day still counts as a day with records.
    expect($summary['daily'][1]['filled_slots'])->toBe(4);
    expect($summary['total']['days_with_records'])->toBe(2);
});

// =====================================================================
// GROUP F — DATE RANGE AND MULTI-UNIT GROUPING
// =====================================================================

// ---------------------------------------------------------------------
// Case 19
// ---------------------------------------------------------------------
it('includes both period bounds and excludes the day before and the day after, filtering on record.date', function () {
    $this->actingAs($this->supervisorA);

    $period = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('clarification')
        ->range('2026-03-01', '2026-03-05')->named('Periode Awal Maret')->open()->create();

    clarificationReportRecord($this->stationA, '2026-03-01', [
        ['pure_oil_production_rate_ton_hour' => 10.0],
    ], ['clarification_id' => 'CLF-01']);
    clarificationReportRecord($this->stationA, '2026-03-05', [
        ['pure_oil_production_rate_ton_hour' => 20.0],
    ], ['clarification_id' => 'CLF-01']);
    // Outside, on both sides, with unmistakable values.
    clarificationReportRecord($this->stationA, '2026-02-28', [
        ['pure_oil_production_rate_ton_hour' => 999.0],
    ], ['clarification_id' => 'CLF-OUT-A']);
    clarificationReportRecord($this->stationA, '2026-03-06', [
        ['pure_oil_production_rate_ton_hour' => 888.0],
    ], ['clarification_id' => 'CLF-OUT-B']);

    $summary = $this->service->buildSummary($period);

    $dates = array_column($summary['daily'], 'date');

    // INCLUSIVE on both ends.
    expect($dates)->toContain('2026-03-01');
    expect($dates)->toContain('2026-03-05');
    expect($dates)->not->toContain('2026-02-28');
    expect($dates)->not->toContain('2026-03-06');

    expect($summary['production']['total_ton'])->toBe(30.0);
    expect($summary['production']['reading_count'])->toBe(2);
    expect($summary['metrics']['pure_oil_production_rate_ton_hour']['max'])->toBe(20.0);
    expect($summary['coverage']['unit_count'])->toBe(1);

    // The filter is on clarification_records.date via whereDate — never on
    // created_at and never on the mobile sync time.
    $queries = clarificationReportQueriesDuring(function () use ($period) {
        (new ClarificationReportService)->buildSummary($period);
    });

    $recordQuery = collect($queries)->first(fn ($sql) => str_contains($sql, 'clarification_records')
        && str_contains($sql, 'stations'));

    expect($recordQuery)->not->toBeNull();

    $lowered = strtolower($recordQuery);

    expect($lowered)->toContain('clarification_records"."date');
    expect($lowered)->not->toContain('created_at');
});

// ---------------------------------------------------------------------
// Case 20
// ---------------------------------------------------------------------
it('merges several clarification units recorded on the same date into one period figure', function () {
    $this->actingAs($this->supervisorA);

    // ONE DATE, TWO RECORDS — clarification_id is a string column on the
    // HEADER, so a date legitimately carries one record per unit.
    clarificationReportRecord($this->stationA, '2026-03-02', [
        ['pure_oil_production_rate_ton_hour' => 20.0],
    ], ['clarification_id' => 'CLF-01']);
    clarificationReportRecord($this->stationA, '2026-03-02', [
        ['pure_oil_production_rate_ton_hour' => 15.0],
    ], ['clarification_id' => 'CLF-02']);

    $summary = $this->service->buildSummary($this->periodA);

    expect($summary['production']['total_ton'])->toBe(35.0);
    expect($summary['production']['reading_count'])->toBe(2);

    // One daily row for that date, combining both units.
    expect($summary['daily'])->toHaveCount(1);
    expect($summary['daily'][0]['date'])->toBe('2026-03-02');
    expect($summary['daily'][0]['production']['total_ton'])->toBe(35.0);
    expect($summary['daily'][0]['production']['reading_count'])->toBe(2);
    expect($summary['daily'][0]['filled_slots'])->toBe(2);

    expect($summary['total']['days_with_records'])->toBe(1);
    expect($summary['coverage']['unit_count'])->toBe(2);
});

// ---------------------------------------------------------------------
// Case 21
// ---------------------------------------------------------------------
it('splits by_unit per clarification_id, each unit carrying its own production and reading count', function () {
    $this->actingAs($this->supervisorA);

    clarificationReportRecord($this->stationA, '2026-03-02', [
        ['pure_oil_production_rate_ton_hour' => 20.0],
    ], ['clarification_id' => 'CLF-01']);
    clarificationReportRecord($this->stationA, '2026-03-02', [
        ['pure_oil_production_rate_ton_hour' => 15.0],
    ], ['clarification_id' => 'CLF-02']);

    $summary = $this->service->buildSummary($this->periodA);

    expect($summary['by_unit'])->toHaveCount(2);

    $byId = collect($summary['by_unit'])->keyBy('clarification_id');

    expect($byId->keys()->all())->toEqualCanonicalizing(['CLF-01', 'CLF-02']);

    expect($byId['CLF-01']['production']['total_ton'])->toBe(20.0);
    expect($byId['CLF-01']['production']['reading_count'])->toBe(1);
    expect($byId['CLF-01']['production_ton'])->toBe(20.0);
    expect($byId['CLF-01']['reading_count'])->toBe(1);

    expect($byId['CLF-02']['production']['total_ton'])->toBe(15.0);
    expect($byId['CLF-02']['production']['reading_count'])->toBe(1);
    expect($byId['CLF-02']['production_ton'])->toBe(15.0);

    // The period total is the combination of the two, never one of them.
    expect($summary['production']['total_ton'])->toBe(35.0);
});

// ---------------------------------------------------------------------
// Case 22
// ---------------------------------------------------------------------
it('keeps a clarification unit that has a record but not one filled reading, with reading_count 0', function () {
    $this->actingAs($this->supervisorA);

    clarificationReportRecord($this->stationA, '2026-03-02', [
        ['pure_oil_production_rate_ton_hour' => 20.0],
    ], ['clarification_id' => 'CLF-01']);
    clarificationReportRecord($this->stationA, '2026-03-02', [
        ['pure_oil_production_rate_ton_hour' => 15.0],
    ], ['clarification_id' => 'CLF-02']);
    // CLF-03 filed a record and two entirely empty slot rows.
    clarificationReportRecord($this->stationA, '2026-03-03', [[], []], ['clarification_id' => 'CLF-03']);

    $summary = $this->service->buildSummary($this->periodA);

    $byId = collect($summary['by_unit'])->keyBy('clarification_id');

    // Dropping it would hide exactly the unit that was never written down —
    // the one worth seeing.
    expect($byId->keys()->all())->toContain('CLF-03');
    expect($summary['by_unit'])->toHaveCount(3);

    expect($byId['CLF-03']['production']['reading_count'])->toBe(0);
    expect($byId['CLF-03']['production']['total_ton'])->toBeNull();
    expect($byId['CLF-03']['production']['total_ton'])->not->toBe(0.0);
    expect($byId['CLF-03']['reading_count'])->toBe(0);
    expect($byId['CLF-03']['rate_avg'])->toBeNull();
    expect($byId['CLF-03']['downtime_mins'])->toBeNull();

    // And it still counts toward the expected-slot denominator.
    expect($summary['coverage']['unit_count'])->toBe(3);
});

// =====================================================================
// GROUP G — IMPLEMENTATION-TECHNIQUE LOCKS
// =====================================================================

// ---------------------------------------------------------------------
// Case 23
// ---------------------------------------------------------------------
it('orders slots by their TIME value, so 00:30:00 precedes 09:00:00 and 10:00:00 is never cast to an integer hour', function () {
    $this->actingAs($this->supervisorA);

    // Inserted in deliberately scrambled order. These four slots are NOT on
    // the canonical 07:00-06:00 grid — they are seeded directly because the
    // case is about ORDERING BY THE TIME VALUE, and the 00:30 / 10:00 pair
    // is the only proof that no integer-hour cast happened: an hour cast
    // would collapse 00:30 to 0 and order it against 10 by the hour alone,
    // and would collapse 22:15 to 22.
    $record = clarificationReportRecord($this->stationA, '2026-03-19', [
        ['time_slot' => '10:00:00', 'pure_oil_production_rate_ton_hour' => 3.0],
        ['time_slot' => '22:15:00', 'pure_oil_production_rate_ton_hour' => 4.0],
        ['time_slot' => '00:30:00', 'pure_oil_production_rate_ton_hour' => 1.0],
        ['time_slot' => '09:00:00', 'pure_oil_production_rate_ton_hour' => 2.0],
    ]);

    $rows = iterator_to_array($this->service->buildExportRows($this->periodA), false);

    // Column 4 is the Slot Waktu column of EXPORT_HEADER.
    expect(array_column($rows, 4))->toBe(['00:30:00', '09:00:00', '10:00:00', '22:15:00']);

    // 00:30 sorts FIRST, ahead of 09:00 and 10:00 — impossible under an
    // integer-hour cast that would have to compare 0 against 9 and 10 after
    // truncating the minutes away.
    expect($rows[0][4])->toBe('00:30:00');
    expect($rows[3][4])->toBe('22:15:00');

    // The minutes survive: nothing was rounded to the top of the hour.
    expect($rows[0][4])->not->toBe('00:00:00');
    expect($rows[3][4])->not->toBe('22:00:00');

    // Four distinct slots on one record, none of them collapsed together.
    expect($record->clarificationDetails()->count())->toBe(4);
    expect($this->service->buildSummary($this->periodA)['coverage']['filled_slots'])->toBe(4);
});

// ---------------------------------------------------------------------
// Case 24
// ---------------------------------------------------------------------
it('aggregates in PHP over raw rows and issues no sum/avg/min/max/group by at the SQL layer', function () {
    $this->actingAs($this->supervisorA);

    clarificationReportRecord($this->stationA, '2026-03-20', clarificationReportMixedRows());

    $queries = clarificationReportQueriesDuring(function () {
        $this->service->buildSummary($this->periodA);
    });

    expect($queries)->not->toBeEmpty();

    foreach ($queries as $sql) {
        $normalised = strtolower($sql);

        // SQL aggregate behaviour over NULLABLE columns is NOT the same in
        // SQLite (the test connection) and PostgreSQL (production), and the
        // separate-denominator and null-is-not-zero rules above are exactly
        // what gets lost in that difference.
        expect($normalised)->not->toContain('sum(');
        expect($normalised)->not->toContain('avg(');
        expect($normalised)->not->toContain('min(');
        expect($normalised)->not->toContain('max(');
        expect($normalised)->not->toContain('group by');
    }
});

// =====================================================================
// GROUP H — EMPTY PERIOD AND THE NO-THRESHOLD GUARANTEE
// =====================================================================

// ---------------------------------------------------------------------
// Case 25
// ---------------------------------------------------------------------
it('returns null everywhere, empty tables and has_data false for a period with no reading at all', function () {
    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA);

    foreach (ClarificationReportService::NUMERIC_METRICS as $metric) {
        expect($summary['metrics'][$metric]['min'])->toBeNull();
        expect($summary['metrics'][$metric]['avg'])->toBeNull();
        expect($summary['metrics'][$metric]['max'])->toBeNull();
        expect($summary['metrics'][$metric]['reading_count'])->toBe(0);
        expect($summary['metrics'][$metric]['avg'])->not->toBe(0);
        expect($summary['metrics'][$metric]['avg'])->not->toBe(0.0);
    }

    expect($summary['production']['total_ton'])->toBeNull();
    expect($summary['production']['reading_count'])->toBe(0);
    expect($summary['downtime']['total_mins'])->toBeNull();
    expect($summary['downtime']['reading_count'])->toBe(0);

    expect($summary['coverage']['filled_slots'])->toBe(0);
    expect($summary['daily'])->toBe([]);
    expect($summary['by_unit'])->toBe([]);
    // has_data distinguishes "there is nothing to report" from "the figures
    // happen to be zero" — the screen uses it to refuse to draw an empty
    // chart, which would read as a measured flat line.
    expect($summary['has_data'])->toBeFalse();
});

// ---------------------------------------------------------------------
// Case 26 — NO THRESHOLD FLAGGING ANYWHERE, ASSERTED BY NAME
// ---------------------------------------------------------------------
it('returns extreme readings as-is, with no threshold, outlier, severity or alert key anywhere in the payload', function () {
    $this->actingAs($this->supervisorA);

    clarificationReportRecord($this->stationA, '2026-03-21', [
        ['sludge_tank_temp_c' => 250.0, 'buffer_tank_level_percent' => 0.5],
        ['sludge_tank_temp_c' => 87.0, 'buffer_tank_level_percent' => 72.0],
    ]);

    $summary = $this->service->buildSummary($this->periodA);

    // The values come back untouched — they are simply never judged.
    expect($summary['metrics']['sludge_tank_temp_c']['max'])->toBe(250.0);
    expect($summary['metrics']['buffer_tank_level_percent']['min'])->toBe(0.5);

    // Clarification has NO operational-target master (there is no
    // ClarificationOperationalTarget), so any threshold here would be a
    // statistic derived from the period itself, dressed up as a PROCESS
    // limit — and a tank temperature rendered in red is read as a breach.
    // The absence is asserted BY NAME because a "do not flag" rule only
    // survives if something guards it.
    $encoded = strtolower(json_encode($summary));

    foreach ([
        'threshold', 'target', 'limit', 'outlier', 'iqr', 'fence',
        'is_danger', 'is_warning', 'severity', 'status_flag',
        'is_out_of_range', 'out_of_range', 'alert', 'violation', 'danger',
    ] as $forbidden) {
        expect($encoded)->not->toContain($forbidden);
    }
});

// =====================================================================
// GROUP I — PERIOD LIST FILTERING AND CLOSED PERIODS
// =====================================================================

// ---------------------------------------------------------------------
// Case 27
// ---------------------------------------------------------------------
it('lists periods covering Clarification, including every-station-type ones, as the clarification pair — exactly two of four', function () {
    $supervisorB = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitB)->create();

    $this->actingAs($supervisorB);

    $clarification = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('clarification')
        ->range('2026-05-01', '2026-05-31')->named('Periode Clarification')->open()->create();
    $allTypes = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType(null)
        ->range('2026-06-01', '2026-06-30')->named('Periode Semua Stasiun')->open()->create();
    $sterilizer = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('sterilizer')
        ->range('2026-07-01', '2026-07-31')->named('Periode Sterilizer')->open()->create();
    $boilerRoom = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('boiler-room')
        ->range('2026-08-01', '2026-08-31')->named('Periode Boiler Room')->open()->create();

    $periods = $this->service->listPeriods();

    expect($periods)->toHaveCount(2);

    $ids = array_column($periods, 'id');

    expect($ids)->toContain((string) $clarification->id);
    expect($ids)->toContain((string) $allTypes->id);
    expect($ids)->not->toContain((string) $sterilizer->id);
    expect($ids)->not->toContain((string) $boilerRoom->id);

    // Newest first.
    expect($ids[0])->toBe((string) $allTypes->id);

    $all = collect($periods)->firstWhere('id', (string) $allTypes->id);

    // stationType(null) tidak lagi berarti `station_type` NULL — kolom itu
    // hilang 2026-09-25, dan cakupan semua-stasiun kini berarti satu baris
    // period_stations per jenis stasiun. Opsi ini adalah pasangan
    // (periode, clarification), jadi jenis stasiunnya terisi dan berlabel
    // stasiun layar ini; 'Semua Stasiun' sudah tidak ada.
    expect($all['station_type'])->toBe('clarification');
    expect($all['station_type_label'])->toBe('Clarification');
});

// ---------------------------------------------------------------------
// Case 27b — BARU 2026-09-26, bersama pemisahan periods/period_stations.
// Perilaku yang DULU dijamin cabang orWhereNull('station_type') dan kini
// sengaja dibuang.
// ---------------------------------------------------------------------
it('does not list a period with no period_stations row for clarification', function () {
    $supervisorB = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitB)->create();

    $this->actingAs($supervisorB);

    $otherTypesOnly = Period::factory()->forBusinessUnit($this->businessUnitB)
        ->stationTypes(['sterilizer', 'boiler-room'])
        ->range('2026-05-01', '2026-05-31')->open()->create();
    $noStations = Period::factory()->forBusinessUnit($this->businessUnitB)
        ->noStations()->range('2026-06-01', '2026-06-30')->create();

    $ids = array_column($this->service->listPeriods(), 'id');

    expect($ids)->not->toContain((string) $otherTypesOnly->id);
    expect($ids)->not->toContain((string) $noStations->id);
});

// ---------------------------------------------------------------------
// Case 27c — BARU 2026-09-26. Inti pemisahan model ini: dua jenis stasiun
// berstatus berbeda di periode yang sama.
// ---------------------------------------------------------------------
it('reports the clarification status, not another station type status in the same period', function () {
    $supervisorB = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitB)->create();

    $this->actingAs($supervisorB);

    $period = Period::factory()->forBusinessUnit($this->businessUnitB)
        ->noStations()->range('2026-05-01', '2026-05-31')->named('Periode Campuran')->create();

    PeriodStation::factory()->forPeriod($period)->stationType('clarification')->open()->create();
    PeriodStation::factory()->forPeriod($period)->stationType('sterilizer')->closed()->create();

    $option = collect($this->service->listPeriods())->firstWhere('id', (string) $period->id);

    expect($option['status'])->toBe('open');
    expect($this->service->summary($period)['period']['status'])->toBe('open');
});

// ---------------------------------------------------------------------
// Case 28
// ---------------------------------------------------------------------
it('returns an empty list, not an error, for a mill with no period covering Clarification', function () {
    $supervisorB = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitB)->create();

    $this->actingAs($supervisorB);

    Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('sterilizer')
        ->range('2026-07-01', '2026-07-31')->named('Periode Sterilizer')->open()->create();

    $periods = $this->service->listPeriods();

    // An empty picker plus a UI hint pointing at Kelola Periode Pelaporan is
    // a valid answer — never a 404 and never an exception.
    expect($periods)->toBe([]);
    expect($periods)->toBeArray();
});

// ---------------------------------------------------------------------
// Case 29
// ---------------------------------------------------------------------
it('reads and exports a CLOSED period in full — the period lock governs writing, not reading', function () {
    $this->actingAs($this->supervisorA);

    $closed = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('clarification')
        ->range('2026-05-01', '2026-05-31')->named('Periode Mei Tertutup')->closed()->create();

    clarificationReportRecord($this->stationA, '2026-05-10', [
        ['pure_oil_production_rate_ton_hour' => 10.0, 'sludge_tank_temp_c' => 87.0],
        ['pure_oil_production_rate_ton_hour' => 12.0, 'sludge_tank_temp_c' => 88.0],
    ], ['clarification_id' => 'CLF-MEI']);

    $summary = $this->service->buildSummary($closed);

    expect($summary['period']['status'])->toBe('closed');
    expect($summary['production']['total_ton'])->toBe(22.0);
    expect($summary['production']['reading_count'])->toBe(2);
    expect($summary['metrics']['sludge_tank_temp_c']['reading_count'])->toBe(2);
    expect($summary['coverage']['filled_slots'])->toBe(2);
    expect($summary['has_data'])->toBeTrue();

    // The export is not refused on status grounds either.
    $rows = iterator_to_array($this->service->buildExportRows($closed), false);

    expect($rows)->toHaveCount(2);
    expect($this->service->export($closed, 'csv'))->toBeInstanceOf(StreamedResponse::class);
});

// =====================================================================
// GROUP J — AUTHORIZATION AND TENANCY
// =====================================================================

// ---------------------------------------------------------------------
// Case 30
// ---------------------------------------------------------------------
it('ignores the client business_unit_id for a Supervisor and answers with their own mill', function () {
    clarificationReportRecord($this->stationA, '2026-03-10', [
        ['pure_oil_production_rate_ton_hour' => 20.0],
    ]);
    // Mill Beta's figures are unmistakable if they ever surface.
    clarificationReportRecord($this->stationB, '2026-03-10', [
        ['pure_oil_production_rate_ton_hour' => 900.0],
    ], ['clarification_id' => 'CLF-BETA']);

    $this->actingAs($this->supervisorA);

    // Discarded, not validated, not compared — and answered with the
    // caller's own mill rather than a 403, which would confirm Mill Beta
    // exists.
    expect($this->service->resolveBusinessUnit((string) $this->businessUnitB->id))
        ->toBe((string) $this->businessUnitA->id);
    expect($this->service->resolveBusinessUnit((string) $this->businessUnitB->id))
        ->not->toBe((string) $this->businessUnitB->id);
    // A mill id that does not exist at all is discarded just the same.
    expect($this->service->resolveBusinessUnit('BU-TIDAK-ADA'))
        ->toBe((string) $this->businessUnitA->id);

    $summary = $this->service->buildSummary($this->periodA, (string) $this->businessUnitB->id);

    expect($summary['business_unit']['name'])->toBe('Mill Alpha');
    expect($summary['period']['business_unit_name'])->toBe('Mill Alpha');
    expect($summary['production']['total_ton'])->toBe(20.0);
    expect($summary['production']['total_ton'])->not->toBe(900.0);
});

// ---------------------------------------------------------------------
// Case 31
// ---------------------------------------------------------------------
it('ignores the client business_unit_id for Mill Management and answers with their own mill', function () {
    clarificationReportRecord($this->stationA, '2026-03-10', [
        ['pure_oil_production_rate_ton_hour' => 20.0],
    ]);
    clarificationReportRecord($this->stationB, '2026-03-10', [
        ['pure_oil_production_rate_ton_hour' => 900.0],
    ], ['clarification_id' => 'CLF-BETA']);

    $this->actingAs($this->millManagementA);

    expect($this->service->resolveBusinessUnit((string) $this->businessUnitB->id))
        ->toBe((string) $this->businessUnitA->id);

    $summary = $this->service->buildSummary($this->periodA, (string) $this->businessUnitB->id);

    expect($summary['business_unit']['name'])->toBe('Mill Alpha');
    expect($summary['production']['total_ton'])->toBe(20.0);
    expect($summary['metrics']['pure_oil_production_rate_ton_hour']['max'])->not->toBe(900.0);
});

// ---------------------------------------------------------------------
// Case 32
// ---------------------------------------------------------------------
it('throws 403 FORBIDDEN for a period belonging to another mill, before any record query runs', function () {
    clarificationReportRecord($this->stationB, '2026-03-10', [
        ['pure_oil_production_rate_ton_hour' => 900.0],
    ], ['clarification_id' => 'CLF-BETA']);

    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('clarification')
        ->range('2026-03-01', '2026-03-31')->named('Periode Maret Beta')->open()->create();

    $this->actingAs($this->supervisorA);

    // Here there IS a concrete handle on another mill's data, so unlike the
    // ignored business_unit_id it is refused outright.
    expect(fn () => $this->service->authorizePeriod((string) $periodB->id))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->buildSummary((string) $periodB->id))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->buildExportRows((string) $periodB->id))
        ->toThrow(AuthorizationException::class);

    // And not one figure of Mill Beta was read.
    $summary = null;

    try {
        $summary = $this->service->buildSummary((string) $periodB->id);
    } catch (AuthorizationException $e) {
        // expected
    }

    expect($summary)->toBeNull();
});

// ---------------------------------------------------------------------
// Case 33 — THE SPY. "Never built" can only be proven by a call count of 0.
// ---------------------------------------------------------------------
it('fails closed for a bound account with no mill and never calls allBusinessUnits()', function () {
    $noMillSupervisor = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $spy = new ClarificationReportAllBusinessUnitsSpy;

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
        fn () => $spy->buildExportRows($this->periodA, null),
    ] as $call) {
        try {
            $call();
        } catch (ValidationException|AuthorizationException $e) {
            // expected
        }
    }

    expect($spy->allBusinessUnitsCalls)->toBe(0);
});

// ---------------------------------------------------------------------
// Case 34
// ---------------------------------------------------------------------
it('throws 422 VALIDATION_ERROR when an Admin sends no business_unit_id, and computes nothing', function () {
    $this->actingAs($this->admin);

    clarificationReportRecord($this->stationA, '2026-03-10', [
        ['pure_oil_production_rate_ton_hour' => 20.0],
    ]);

    foreach ([
        fn () => $this->service->listPeriods(null),
        fn () => $this->service->buildSummary($this->periodA, null),
        fn () => $this->service->buildExportRows($this->periodA, null),
    ] as $call) {
        $exception = null;
        $result = null;

        try {
            $result = $call();
        } catch (ValidationException $e) {
            $exception = $e;
        }

        expect($exception)->not->toBeNull();
        // Incomplete input, not refused access — 422, never 403.
        expect($exception->status)->toBe(422);
        expect($exception->errors())->toHaveKey('business_unit_id');
        expect($exception->errors()['business_unit_id'][0])->toContain('Pilih mill');
        expect($result)->toBeNull();
    }
});

// ---------------------------------------------------------------------
// Case 35
// ---------------------------------------------------------------------
it('throws 422 VALIDATION_ERROR with errors.period_id when no period is given', function () {
    $this->actingAs($this->supervisorA);

    foreach ([null, ''] as $empty) {
        $exception = null;

        try {
            $this->service->buildSummary($empty);
        } catch (ValidationException $e) {
            $exception = $e;
        }

        expect($exception)->not->toBeNull();
        expect($exception->status)->toBe(422);
        expect($exception->errors())->toHaveKey('period_id');
    }

    expect(fn () => $this->service->buildExportRows(null))->toThrow(ValidationException::class);
});

// ---------------------------------------------------------------------
// Case 36
// ---------------------------------------------------------------------
it('throws 404 NOT_FOUND when an authorised role names a period id that does not exist', function () {
    $this->actingAs($this->supervisorA);

    // Every id in this schema is a uuid, so the "does not exist" id is a
    // well-formed uuid rather than the spec's illustrative integer.
    $missing = (string) Str::uuid();

    expect(fn () => $this->service->authorizePeriod($missing))->toThrow(ModelNotFoundException::class);
    expect(fn () => $this->service->buildSummary($missing))->toThrow(ModelNotFoundException::class);
    expect(fn () => $this->service->buildExportRows($missing))->toThrow(ModelNotFoundException::class);
});

// ---------------------------------------------------------------------
// Case 37 — WIDENED 2026-09-25 (screen-138--laporan-clarification-mobile)
// ---------------------------------------------------------------------

// This case used to assert the opposite: Operator refused with 403 on every
// public method, reading no clarification rows at all, with a note saying
// "this is the WEB report and there is NO Operator widening: the mobile
// Clarification report is screen-138 and has not been built, so there is no
// caller to widen for". THAT CALLER NOW EXISTS. screen-138 is the mobile
// Clarification report, and on 2026-09-25 the four
// /api/clarification-reports/* endpoints were opened to Operator — the same
// widening SterilizerReportService got for screen-135 (2026-09-23),
// CagesTrackReportService for screen-136 (2026-09-24) and
// BoilerRoomReportService for screen-137 (2026-09-25). The people who key
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
// 403 is KEPT BELOW VERBATIM: Operator is bound to one mill and has no
// picker, so the list of mills is still Admin-only and the mobile view must
// never call it.
//
// The old "reads no clarification rows" assertion keeps its SPIRIT rather
// than its letter: rows ARE read for Operator now, so what is asserted is
// that every query that touches clarification_records is bound to the
// Operator's OWN business unit and to no other — proven from the recorded
// bindings, with the query list itself asserted non-empty, because an
// empty list would make every binding assertion below vacuously true.
it('accepts a Station Operator on every public method and binds it to its own mill instead of the Admin branch', function () {
    // Mill Beta's figures are large and unmistakable: if any of them
    // surfaced below, they could not be mistaken for a rounding difference.
    clarificationReportRecord($this->stationB, '2026-03-10', [
        ['pure_oil_production_rate_ton_hour' => 900.0, 'sludge_tank_temp_c' => 300.0],
    ], ['clarification_id' => 'CLF-BETA']);
    clarificationReportRecord($this->stationA, '2026-03-10', [
        ['pure_oil_production_rate_ton_hour' => 20.0, 'sludge_tank_temp_c' => 87.0],
    ], ['clarification_id' => 'CLF-ALPHA']);

    $otherMillPeriod = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType('clarification')
        ->range('2026-03-01', '2026-03-31')
        ->open()
        ->create();

    $this->actingAs($this->operatorA);

    $queries = [];

    DB::listen(function ($query) use (&$queries) {
        $queries[] = ['sql' => $query->sql, 'bindings' => $query->bindings];
    });

    // 1. ACCEPTED — not one AuthorizationException on any of the report
    //    paths that used to answer 403.
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
    expect(fn () => $this->service->businessUnitOptions())->toThrow(AuthorizationException::class);

    // 4. Widening the ROLE never widened the MILL: another mill's period id
    //    is still a flat 403, while the Operator's own period passes.
    //    authorizePeriod() needed no change for this — only Admin is unbound
    //    there, so Operator was closed out of other mills' periods the
    //    moment it entered the mill-bound branch.
    expect(fn () => $this->service->authorizePeriod((string) $otherMillPeriod->id))
        ->toThrow(AuthorizationException::class);
    expect($this->service->authorizePeriod((string) $this->periodA->id)->id)
        ->toBe($this->periodA->id);

    // 5. The old "reads no rows" assertion's SPIRIT: the queries that DO run
    //    are scoped to the Operator's own business unit, every one of them.
    $recordQueries = collect($queries)
        ->filter(fn ($query) => str_contains($query['sql'], 'clarification_records'))
        ->values();

    // Without this, every foreach assertion below would pass on an empty
    // list and prove precisely nothing.
    expect($recordQueries)->not->toBeEmpty();

    foreach ($recordQueries as $query) {
        $bindings = array_map(fn ($binding) => (string) $binding, $query['bindings']);

        expect($bindings)->toContain((string) $this->businessUnitA->id);
        expect($bindings)->not->toContain((string) $this->businessUnitB->id);
    }

    // 6. And the figures themselves are BU-A's, never BU-B's 900.0 / 300.0.
    expect($summary['business_unit']['name'])->toBe('Mill Alpha');
    expect($summary['production']['total_ton'])->toBe(20.0);
    expect($summary['production']['reading_count'])->toBe(1);
    expect($summary['metrics']['pure_oil_production_rate_ton_hour']['max'])->toBe(20.0);
    expect($summary['metrics']['pure_oil_production_rate_ton_hour']['max'])->not->toBe(900.0);
    expect($summary['metrics']['sludge_tank_temp_c']['max'])->toBe(87.0);
    expect($summary['metrics']['sludge_tank_temp_c']['max'])->not->toBe(300.0);
    expect(array_column($summary['by_unit'], 'clarification_id'))->toBe(['CLF-ALPHA']);
    expect($rows)->toHaveCount(1);
    // listPeriods() answers with Mill Alpha's period only — Mill Beta's
    // period, created above with the same dates and station type, is absent.
    expect($periods)->toHaveCount(1);
    expect($periods[0]['id'])->toBe((string) $this->periodA->id);
});

// ---------------------------------------------------------------------
// Case 38 — THE NO-ORACLE PROOF, RE-EXPRESSED 2026-09-25 (screen-138)
// ---------------------------------------------------------------------

// This case used to read: "runs the role guard BEFORE findOrFail: an
// Operator naming a nonexistent period gets 403, never 404". Its POINT was
// never the exception ordering for its own sake — it was that A CALLER WHO
// IS REFUSED MUST NOT GET AN EXISTENCE ORACLE. Telling 403 apart from 404
// would have let a role that is not admitted at all enumerate which period
// ids exist.
//
// After the screen-138 widening, Operator IS an admitted caller. For an
// admitted caller a 404 over a genuinely nonexistent id leaks nothing at
// all — it is simply the correct answer, exactly as it already was for
// Supervisor (case 36). Keeping the old assertion would have meant keeping
// a 403 that no longer protects anything, and would have forced the
// implementation to refuse Operator somewhere.
//
// WHAT STILL MATTERS IS THE CROSS-MILL ORACLE, and that is what this case
// now asserts: an Operator of Mill Alpha naming a period belonging to Mill
// Beta gets 403 — not 404 — so the refusal never confirms whether that
// period exists. The mill guard still runs, and it still refuses without
// answering the question. The second half documents the deliberate change:
// a period id that exists for nobody now answers 404 for an Operator, and
// that is intended rather than a regression.
it('gives an Operator no existence oracle across mills: another mill\'s period is 403, never 404', function () {
    $otherMillPeriod = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType('clarification')
        ->range('2026-03-01', '2026-03-31')
        ->open()
        ->named('Periode Maret Beta')
        ->create();

    $this->actingAs($this->operatorA);

    // A period that DOES exist, in a mill the caller may not see: refused
    // with 403, and deliberately NOT with 404 — a 404 here would be a
    // statement about the other mill's data.
    expect(fn () => $this->service->authorizePeriod((string) $otherMillPeriod->id))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->authorizePeriod((string) $otherMillPeriod->id))
        ->not->toThrow(ModelNotFoundException::class);
    expect(fn () => $this->service->buildSummary((string) $otherMillPeriod->id))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->buildSummary((string) $otherMillPeriod->id))
        ->not->toThrow(ModelNotFoundException::class);
    expect(fn () => $this->service->buildExportRows((string) $otherMillPeriod->id))
        ->toThrow(AuthorizationException::class);

    // The exception TYPE is the proof, so it is captured and inspected
    // rather than merely matched.
    $thrown = null;

    try {
        $this->service->authorizePeriod((string) $otherMillPeriod->id);
    } catch (Throwable $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeInstanceOf(AuthorizationException::class);
    expect($thrown)->not->toBeInstanceOf(ModelNotFoundException::class);

    // AND THE DOCUMENTED CHANGE: an id that exists for nobody now answers
    // 404 for an Operator, exactly as it does for Supervisor (case 36).
    // Operator is an admitted caller since screen-138, so there is no
    // refusal left to hide behind and nothing for the 404 to leak.
    $missing = (string) Str::uuid();

    expect(fn () => $this->service->authorizePeriod($missing))->toThrow(ModelNotFoundException::class);
    expect(fn () => $this->service->buildSummary($missing))->toThrow(ModelNotFoundException::class);
});

// ---------------------------------------------------------------------
// Case 39
// ---------------------------------------------------------------------
it('throws 401 UNAUTHENTICATED when there is no session at all', function () {
    // No actingAs() anywhere in this test, deliberately.
    expect(fn () => $this->service->businessUnitOptions())->toThrow(AuthenticationException::class);
    expect(fn () => $this->service->listPeriods())->toThrow(AuthenticationException::class);
    expect(fn () => $this->service->buildSummary($this->periodA))->toThrow(AuthenticationException::class);
    expect(fn () => $this->service->buildExportRows($this->periodA))->toThrow(AuthenticationException::class);
    expect(fn () => $this->service->export($this->periodA, 'csv'))->toThrow(AuthenticationException::class);
    expect(fn () => $this->service->authorizePeriod((string) $this->periodA->id))
        ->toThrow(AuthenticationException::class);
});

// =====================================================================
// GROUP K — EXPORT
// =====================================================================

// ---------------------------------------------------------------------
// Case 40
// ---------------------------------------------------------------------
it('exports one line per time slot with the record context columns repeated on every line', function () {
    $this->actingAs($this->supervisorA);

    clarificationReportRecord($this->stationA, '2026-03-04', [
        ['time_slot' => '07:00', 'clarification_tank_temp_c' => 93.0, 'pure_oil_production_rate_ton_hour' => 10.0],
        ['time_slot' => '08:00', 'clarification_tank_temp_c' => 94.0, 'pure_oil_production_rate_ton_hour' => 11.0],
        ['time_slot' => '09:00', 'clarification_tank_temp_c' => 95.0, 'pure_oil_production_rate_ton_hour' => 12.0],
    ], ['clarification_id' => 'CLF-01', 'note' => 'catatan satu']);

    clarificationReportRecord($this->stationA, '2026-03-05', [
        ['time_slot' => '07:00', 'sludge_tank_temp_c' => 87.0, 'downtime_mins' => 5.0, 'findings' => 'bocor kecil'],
        ['time_slot' => '08:00', 'sludge_tank_temp_c' => 88.0, 'downtime_mins' => 0.0],
        ['time_slot' => '09:00', 'sludge_tank_temp_c' => 89.0],
    ], ['clarification_id' => 'CLF-02', 'note' => 'catatan dua']);

    $rows = iterator_to_array($this->service->buildExportRows($this->periodA), false);

    // ONE LINE PER TIME SLOT — 2 records x 3 slots.
    expect($rows)->toHaveCount(6);

    // Context columns repeated verbatim on every line of the same record, so
    // the file can be pivoted directly in a spreadsheet.
    foreach (array_slice($rows, 0, 3) as $row) {
        expect($row[0])->toBe('2026-03-04');
        expect($row[1])->toBe('CLF-01');
        expect($row[2])->toBe('synced');
        expect($row[3])->toBe('catatan satu');
    }

    foreach (array_slice($rows, 3, 3) as $row) {
        expect($row[0])->toBe('2026-03-05');
        expect($row[1])->toBe('CLF-02');
        expect($row[3])->toBe('catatan dua');
    }

    // Slot column, then all SEVEN non-time_slot columns including `findings`
    // verbatim — 4 context + 1 slot + 7 = 12 columns, matching EXPORT_HEADER.
    expect(ClarificationReportService::EXPORT_HEADER)->toHaveCount(12);

    foreach ($rows as $row) {
        expect($row)->toHaveCount(12);
    }

    expect($rows[0][4])->toBe('07:00');
    expect($rows[0][5])->toBe(93.0);
    expect($rows[0][9])->toBe(10.0);
    expect($rows[3][11])->toBe('bocor kecil');
    // A recorded zero downtime is exported as 0, not as an empty cell.
    expect($rows[4][10])->toBe(0.0);
    // And a slot that recorded nothing for a column exports null, never 0.
    expect($rows[5][10])->toBeNull();
});

// ---------------------------------------------------------------------
// Case 41
// ---------------------------------------------------------------------
it('streams the export lazily through a generator, behind an EAGER permission and row-limit guard', function () {
    $this->actingAs($this->supervisorA);

    // A few thousand rows — enough that materialising them all at once
    // would be a visible choice rather than an accident.
    clarificationReportBulkSeed($this->stationA, $this->supervisorA, '2026-03', 5000);

    $rows = $this->service->buildExportRows($this->periodA);

    // A Generator, not an array and not a Collection: the guard and the
    // row-limit check ran EAGERLY, the rows themselves did not. Making the
    // method itself a generator would defer the 403/422 until the first
    // iteration, and a refused export would look like a successful call that
    // produced nothing.
    expect($rows)->toBeInstanceOf(Generator::class);
    expect($rows)->not->toBeInstanceOf(Collection::class);
    expect(is_array($rows))->toBeFalse();

    $count = 0;

    foreach ($rows as $row) {
        $count++;
    }

    expect($count)->toBe(5000);

    $response = $this->service->export($this->periodA, 'csv');

    expect($response)->toBeInstanceOf(StreamedResponse::class);
    expect($response->headers->get('Content-Type'))->toContain('text/csv');
    expect($response->headers->get('Content-Disposition'))->toContain('attachment');
    expect($response->headers->get('Content-Disposition'))->toContain('laporan-clarification');
});

// ---------------------------------------------------------------------
// Case 42
// ---------------------------------------------------------------------
it('throws 422 EXPORT_FAILED when the number of DETAIL rows exceeds 50.000, and streams nothing', function () {
    $this->actingAs($this->supervisorA);

    // The ceiling counts DETAIL ROWS, not header records — one record
    // carries up to 24 of them, so counting headers would sail straight past
    // the real limit.
    $total = ClarificationReportService::EXPORT_ROW_LIMIT + 1;
    $headers = clarificationReportBulkSeed($this->stationA, $this->supervisorA, '2026-03', $total);

    expect($headers)->toBeLessThan(ClarificationReportService::EXPORT_ROW_LIMIT);
    expect(ClarificationDetail::count())->toBe($total);

    expect(fn () => $this->service->buildExportRows($this->periodA))
        ->toThrow(ExportFailedException::class);
    expect(fn () => $this->service->export($this->periodA, 'csv'))
        ->toThrow(ExportFailedException::class);

    // Nothing was streamed: the refusal happened before a response object
    // existed at all.
    $response = null;

    try {
        $response = $this->service->export($this->periodA, 'csv');
    } catch (ExportFailedException $e) {
        expect($e->getStatusCode())->toBe(422);
        expect($e->errorCode())->toBe('EXPORT_FAILED');
    }

    expect($response)->toBeNull();

    // Strictly greater than: exactly the limit still exports.
    $oneRowId = ClarificationDetail::query()->value('id');
    ClarificationDetail::query()->whereKey($oneRowId)->delete();
    expect(ClarificationDetail::count())->toBe(ClarificationReportService::EXPORT_ROW_LIMIT);
    expect($this->service->export($this->periodA, 'csv'))->toBeInstanceOf(StreamedResponse::class);
});

// ---------------------------------------------------------------------
// Case 43
// ---------------------------------------------------------------------
it('exports the time slots on both period bounds and nothing outside the range', function () {
    $this->actingAs($this->supervisorA);

    $period = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('clarification')
        ->range('2026-03-01', '2026-03-05')->named('Periode Awal Maret')->open()->create();

    clarificationReportRecord($this->stationA, '2026-03-01', [
        ['time_slot' => '07:00', 'pure_oil_production_rate_ton_hour' => 10.0],
    ], ['clarification_id' => 'CLF-01']);
    clarificationReportRecord($this->stationA, '2026-03-05', [
        ['time_slot' => '07:00', 'pure_oil_production_rate_ton_hour' => 20.0],
    ], ['clarification_id' => 'CLF-01']);
    clarificationReportRecord($this->stationA, '2026-02-28', [
        ['time_slot' => '07:00', 'pure_oil_production_rate_ton_hour' => 999.0],
    ], ['clarification_id' => 'CLF-OUT-A']);
    clarificationReportRecord($this->stationA, '2026-03-06', [
        ['time_slot' => '07:00', 'pure_oil_production_rate_ton_hour' => 888.0],
    ], ['clarification_id' => 'CLF-OUT-B']);

    $rows = iterator_to_array($this->service->buildExportRows($period), false);

    $dates = array_column($rows, 0);

    expect($rows)->toHaveCount(2);
    expect($dates)->toContain('2026-03-01');
    expect($dates)->toContain('2026-03-05');
    expect($dates)->not->toContain('2026-02-28');
    expect($dates)->not->toContain('2026-03-06');

    expect(array_column($rows, 9))->toBe([10.0, 20.0]);
});

// =====================================================================
// GROUP L — READ-ONLY, AND THE FULL HAPPY PATH
// =====================================================================

// ---------------------------------------------------------------------
// Case 44
// ---------------------------------------------------------------------
it('writes nothing at all: no insert, update or delete on either clarification table on any path', function () {
    $this->actingAs($this->supervisorA);

    clarificationReportRecord($this->stationA, '2026-03-10', clarificationReportMixedRows());

    $recordsBefore = ClarificationRecord::count();
    $detailsBefore = ClarificationDetail::count();
    $checksumBefore = ClarificationDetail::query()->orderBy('id')->get()->toJson();

    $queries = clarificationReportQueriesDuring(function () {
        // Sequentially: mill options (refused for a bound role, which is
        // itself a read), period list, summary, export.
        try {
            $this->service->businessUnitOptions();
        } catch (AuthorizationException $e) {
            // expected for a bound role
        }

        $this->service->listPeriods();
        $this->service->buildSummary($this->periodA);
        iterator_to_array($this->service->buildExportRows($this->periodA), false);
    });

    expect($queries)->not->toBeEmpty();

    foreach ($queries as $sql) {
        $normalised = strtolower($sql);

        foreach (['insert into', 'update ', 'delete from'] as $verb) {
            expect($normalised)->not->toContain($verb);
        }
    }

    expect(ClarificationRecord::count())->toBe($recordsBefore);
    expect(ClarificationDetail::count())->toBe($detailsBefore);
    expect(ClarificationDetail::query()->orderBy('id')->get()->toJson())->toBe($checksumBefore);
});

// ---------------------------------------------------------------------
// Case 45 — the full success path
// ---------------------------------------------------------------------
it('returns the complete summary structure when every condition passes, with no threshold key anywhere', function () {
    $this->actingAs($this->supervisorA);

    $period = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('clarification')
        ->range('2026-04-01', '2026-04-03')->named('Periode April Lengkap')->open()->create();

    // Two units across three days, readings on all six metrics.
    foreach (['2026-04-01', '2026-04-02', '2026-04-03'] as $index => $date) {
        clarificationReportRecord($this->stationA, $date, [
            [
                'clarification_tank_temp_c' => 93.0,
                'oil_tank_temperature_c' => 97.0,
                'sludge_tank_temp_c' => 87.0,
                'buffer_tank_level_percent' => 72.0,
                'pure_oil_production_rate_ton_hour' => 10.0,
                'downtime_mins' => 6.0,
            ],
            [
                'clarification_tank_temp_c' => 95.0,
                'oil_tank_temperature_c' => 99.0,
                'sludge_tank_temp_c' => 89.0,
                'buffer_tank_level_percent' => 74.0,
                'pure_oil_production_rate_ton_hour' => 12.0,
                'downtime_mins' => 4.0,
            ],
        ], ['clarification_id' => 'CLF-01']);

        clarificationReportRecord($this->stationA, $date, [
            [
                'clarification_tank_temp_c' => 91.0,
                'oil_tank_temperature_c' => 95.0,
                'sludge_tank_temp_c' => 85.0,
                'buffer_tank_level_percent' => 70.0,
                'pure_oil_production_rate_ton_hour' => 8.0,
                'downtime_mins' => 10.0,
            ],
        ], ['clarification_id' => 'CLF-02']);
    }

    $summary = $this->service->buildSummary($period);

    // Period header.
    expect($summary['period']['id'])->toBe((string) $period->id);
    expect($summary['period']['name'])->toBe('Periode April Lengkap');
    expect($summary['period']['start_date'])->toBe('2026-04-01');
    expect($summary['period']['end_date'])->toBe('2026-04-03');
    expect($summary['period']['status'])->toBe('open');
    expect($summary['period']['business_unit_name'])->toBe('Mill Alpha');
    expect($summary['business_unit']['name'])->toBe('Mill Alpha');

    // DERIVED PRODUCTION, always beside its own reading count.
    // 3 days x (10 + 12 + 8) = 90,0 ton over 9 rate readings.
    expect($summary['production']['total_ton'])->toBe(90.0);
    expect($summary['production']['reading_count'])->toBe(9);
    expect($summary['production']['avg_production_per_day_ton'])->toBe(30.0);
    expect($summary['production']['avg_per_day_ton'])->toBe(30.0);
    expect($summary['production']['min_rate_ton_hour'])->toBe(8.0);
    expect($summary['production']['avg_rate_ton_hour'])->toBe(10.0);
    expect($summary['production']['max_rate_ton_hour'])->toBe(12.0);

    // DOWNTIME, beside its own reading count. 3 x (6 + 4 + 10) = 60.
    expect($summary['downtime']['total_mins'])->toBe(60);
    expect($summary['downtime']['reading_count'])->toBe(9);
    expect($summary['downtime']['avg_downtime_per_day_mins'])->toBe(20.0);
    expect($summary['downtime']['avg_per_day_mins'])->toBe(20.0);
    expect($summary['downtime']['hours_with_downtime'])->toBe(9);

    // Six metrics, each with min / avg / max / its own reading_count.
    foreach (ClarificationReportService::NUMERIC_METRICS as $metric) {
        expect($summary['metrics'])->toHaveKey($metric);
        expect($summary['metrics'][$metric])->toHaveKeys(['min', 'avg', 'max', 'reading_count']);
        expect($summary['metrics'][$metric]['reading_count'])->toBe(9);
    }

    expect($summary['metrics']['clarification_tank_temp_c']['min'])->toBe(91.0);
    expect($summary['metrics']['clarification_tank_temp_c']['max'])->toBe(95.0);
    expect($summary['metrics']['oil_tank_temperature_c']['min'])->toBe(95.0);
    expect($summary['metrics']['sludge_tank_temp_c']['max'])->toBe(89.0);
    expect($summary['metrics']['buffer_tank_level_percent']['min'])->toBe(70.0);

    // Coverage — part of the report body, not a footnote.
    expect($summary['coverage'])->toHaveKeys([
        'filled_slots', 'expected_slots', 'coverage_percent',
        'unit_count', 'slots_per_unit_per_day', 'days_in_period',
    ]);
    expect($summary['coverage']['filled_slots'])->toBe(9);
    expect($summary['coverage']['expected_slots'])->toBe(2 * 3 * 24);

    // Daily recap (three dates, all three temperatures on each row) and the
    // per-unit recap (two units).
    expect($summary['daily'])->toHaveCount(3);
    expect($summary['by_unit'])->toHaveCount(2);
    expect($summary['total']['days_with_records'])->toBe(3);
    expect($summary['total']['reading_rows'])->toBe(9);
    expect($summary['has_data'])->toBeTrue();

    // NO THRESHOLD / OUTLIER KEY WHATSOEVER.
    $encoded = strtolower(json_encode($summary));

    foreach ([
        'threshold', 'target', 'limit', 'outlier', 'iqr', 'fence',
        'is_danger', 'is_warning', 'severity', 'status_flag', 'alert',
    ] as $forbidden) {
        expect($encoded)->not->toContain($forbidden);
    }
});
