<?php

/**
 * StorageTankReportServiceTest — screen-133--laporan-storage-tank-web /
 * usecase-133--laporan-storage-tank-web (Laporan Periode Storage Tank).
 *
 * One test per unit_test_case in the screen's tech spec — ALL 44 cases, in
 * the spec's own order, one discrete `it()` each — against
 * App\Services\StorageTankReportService. Mirrors
 * tests/Unit/Services/ClarificationReportServiceTest.php (screen-132) in
 * structure and conventions.
 *
 * `uses(TestCase::class, RefreshDatabase::class)` is MANDATORY here:
 * tests/Pest.php binds Tests\TestCase only to Feature/, so without this line
 * the Unit suite has no application container at all — auth()->user(),
 * Eloquent and the factories would every one of them blow up.
 *
 * ----------------------------------------------------------------------
 * WHY THIS REPORT'S TRAPS ARE DIFFERENT FROM THE OTHER FOUR
 * ----------------------------------------------------------------------
 * Cages Track / Sterilizer / Boiler Room / Clarification all summarise
 * EVENTS: they count, sum and average, and every extra reading refines the
 * answer. THIS report answers a question about STATE AND ITS CHANGE, and
 * that answer is a COMPARISON OF TWO READINGS. Every trap below follows
 * from that one difference, and each fixture is shaped so that a WRONG
 * implementation produces a DIFFERENT NUMBER rather than the same one:
 *
 *  1. OPENING/CLOSING ARE CHOSEN BY TIME, NOT BY VALUE, and not by storage
 *     order. Cases 1-4 seed 100,0 -> 250,0 -> 80,0 and insert them in a
 *     DELIBERATELY SCRAMBLED order, so min/max (80/250), "first row stored"
 *     (250) and "first row in time" (100) are three different answers.
 *     Case 5 pushes it further: the earlier DATE carries the later TIME
 *     SLOT, so an ordering that looks only at time_slot picks the wrong row.
 *
 *  2. MOVEMENT IS SUMMED PER TANK, never derived from the combined stock.
 *     Case 6 seeds a tank recorded ONLY at the end of the period so the two
 *     methods give +10,0 and +410,0 — numbers that cannot be confused.
 *
 *  3. AVERAGE TEMPERATURE IS READ, NOT RECOMPUTED. Case 10 seeds
 *     top/middle/bottom = 50/60/70 (arithmetic mean 60,0) while
 *     average_temperature_c says 55,0. An implementation that recomputes
 *     answers 60,0 and fails. Case 11 seeds the column empty and asserts
 *     the report derives NOTHING.
 *
 *  4. ONE STOCK READING IS NOT ZERO MOVEMENT (case 7): movement_mt null,
 *     movement_computable false, and no contribution to the period total.
 *     0,0 would claim the stock did not change — a claim never measured.
 *
 *  5. AN UNFILLED FIRST READING DOES NOT MAKE THE OPENING NULL (case 8):
 *     the opening comes from the next FILLED reading and opening_at points
 *     at THAT reading, which is what makes the missing days visible.
 *
 *  6. NEGATIVE MOVEMENT IS ORDINARY (case 9): -180,0 as-is, never abs(),
 *     never clamped to 0.
 *
 *  7. A SEPARATE DENOMINATOR PER METRIC, and NULL IS NEVER ZERO (cases
 *     12-14). One row may record FFA and leave DOBI empty.
 *
 * NO THRESHOLD ASSERTIONS ANYWHERE — the opposite: case 31 asserts the
 * ABSENCE BY NAME. Storage Tank has no operational-target master table, so
 * any flag here would be a statistic dressed up as a process limit.
 *
 * ----------------------------------------------------------------------
 * CROSS-MILL SECURITY IS ASSERTED AT ITS TWO DIFFERENT SHAPES
 * ----------------------------------------------------------------------
 *   - resolveBusinessUnit() IGNORES the client's business_unit_id for
 *     Supervisor / Mill Management — the caller's own mill, and a payload,
 *     NOT a refusal (case 34). A refusal would confirm the other mill
 *     exists.
 *   - authorizePeriod() REFUSES another mill's period_id (case 35) — and
 *     since the screen-139 widening (below) that refusal is what case 39
 *     proves for OPERATOR too: another mill's period is 403, never 404, so
 *     the refusal never doubles as an existence oracle.
 *
 * ----------------------------------------------------------------------
 * OPERATOR WAS WIDENED IN ON 2026-09-25 (screen-139), AND CASES 38-39 SAY SO
 * ----------------------------------------------------------------------
 * The header of this file used to say the report refused Operator outright.
 * It does not any more: screen-139--laporan-storage-tank-mobile REUSES these
 * four endpoints verbatim instead of getting its own, so the phone and the
 * web report can never disagree about a figure. Case 38 asserts the
 * acceptance AND — the half that matters — that Operator lands in the
 * MILL-BOUND branch of resolveBusinessUnit() where the client's
 * business_unit_id is discarded, not in the Admin branch where it is
 * honoured. businessUnitOptions() stays 403 for Operator, and the WEB route
 * /reports/storage-tank was not touched at all.
 *
 * And the fail-closed rule (case 36) is asserted as a SPY, because "the
 * all-mills list was never built" is a claim about something that did NOT
 * happen: only a recorded call count of zero can prove it.
 *
 * ----------------------------------------------------------------------
 * SIGNATURE NOTE ON export()
 * ----------------------------------------------------------------------
 * unit_test_case 27 writes `export(periodId, businessUnitId)` in its loose
 * "given" prose, but the real signature is
 * export($period, string $format = 'csv', ?string $requestedBusinessUnitId)
 * — the format sits in the MIDDLE, uniformly across all five station report
 * services. These tests call the real three-parameter signature; the "given"
 * text is prose, not a contract.
 */

use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\Station;
use App\Models\StorageTankDetail;
use App\Models\StorageTankRecord;
use App\Models\User;
use App\Services\StorageTankRecordService;
use App\Services\StorageTankReportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Exceptions\StreamedResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * THE SPY for case 36.
 *
 * allBusinessUnits() is public on the service precisely so it can be
 * overridden here: the fail-closed rule for a Supervisor / Mill Management
 * account with no business_unit_id is only meaningful if it can be PROVEN
 * the whole-mill list was never even read, and a recorded call count of
 * zero is that proof. Asserting only the 422 would pass just as happily
 * against an implementation that built the list first and threw afterwards.
 */
class StorageTankReportAllBusinessUnitsSpy extends StorageTankReportService
{
    public int $allBusinessUnitsCalls = 0;

    public function allBusinessUnits(): Collection
    {
        $this->allBusinessUnitsCalls++;

        return parent::allBusinessUnits();
    }
}

/**
 * THE SPY for case 30.
 *
 * "The export fails WHILE writing" cannot be provoked from the data — the
 * row-limit refusal happens before a byte is streamed, which is the whole
 * point of buildExportRows() being eager. So the failure is injected in the
 * only place it can genuinely occur: mid-generator, after the first row has
 * already been handed to fputcsv().
 */
class StorageTankReportFailingStreamService extends StorageTankReportService
{
    protected function streamExportRows(Builder $recordQuery): Generator
    {
        yield array_fill(0, count(self::EXPORT_HEADER), 'x');

        throw new RuntimeException('penulisan stream gagal di tengah jalan');
    }
}

/**
 * One storage_tank_records header for $tankId on $date, plus one
 * storage_tank_details row per entry of $rows.
 *
 * Each entry is a plain column => value map. `time_slot` is filled in from
 * StorageTankRecordService::canonicalTimeSlots() by position unless the
 * entry supplies its own, so a fixture that does not care about slots never
 * has to spell them out — while UNIQUE(storage_tank_record_id, time_slot)
 * is still respected.
 *
 * @param  list<array<string, mixed>>  $rows
 */
function storageTankReportRecord(Station $station, string $date, string $tankId, array $rows = [], array $overrides = []): StorageTankRecord
{
    $record = StorageTankRecord::factory()->forStation($station)->onDate($date)->create(array_merge([
        'storage_tank_id' => $tankId,
        'note' => null,
    ], $overrides));

    $slots = StorageTankRecordService::canonicalTimeSlots();

    foreach (array_values($rows) as $index => $row) {
        StorageTankDetail::factory()->forRecord($record)->create(array_merge([
            'time_slot' => $slots[$index % count($slots)],
        ], $row));
    }

    return $record;
}

/**
 * THE SCRAMBLED FIXTURE behind cases 1-4, and the reason those cases can
 * fail at all.
 *
 * TK-01 reads 100,0 @01 Sep 06:00 -> 250,0 @05 Sep 12:00 -> 80,0 @10 Sep
 * 18:00, and the three headers are WRITTEN in the order 05 Sep, 10 Sep,
 * 01 Sep. So:
 *   - by VALUE          opening would be 80,0  and closing 250,0;
 *   - by STORAGE ORDER  opening would be 250,0 and closing 100,0;
 *   - by TIME (correct) opening is   100,0 and closing 80,0.
 * Three different answers, which is exactly what a fixture inserted in tidy
 * order can never distinguish.
 */
function storageTankReportScrambledTank(Station $station, string $tankId = 'TK-01'): void
{
    storageTankReportRecord($station, '2026-09-05', $tankId, [
        ['time_slot' => '12:00', 'calculated_weight_mt' => 250.0],
    ]);
    storageTankReportRecord($station, '2026-09-10', $tankId, [
        ['time_slot' => '18:00', 'calculated_weight_mt' => 80.0],
    ]);
    storageTankReportRecord($station, '2026-09-01', $tankId, [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 100.0],
    ]);
}

/** The by_tank entry for one storage_tank_id, or null. */
function storageTankReportByTank(array $summary, string $tankId): ?array
{
    foreach ($summary['by_tank'] as $tank) {
        if ($tank['storage_tank_id'] === $tankId) {
            return $tank;
        }
    }

    return null;
}

/** Every SQL statement run inside $callback, for the "no SQL aggregate" case. */
function storageTankReportQueriesDuring(callable $callback): array
{
    $queries = [];

    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    $callback();

    return $queries;
}

/** The body of a StreamedResponse, captured. */
function storageTankReportStreamed(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

/** The streamed CSV split into non-empty lines. */
function storageTankReportCsvLines(StreamedResponse $response): array
{
    return array_values(array_filter(explode("\n", trim(storageTankReportStreamed($response)))));
}

beforeEach(function () {
    $this->service = new StorageTankReportService;

    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->storageTank()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->storageTank()->create();

    $this->supervisorA = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagementA = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operatorA = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    // Admin is the one role not bound to a mill — users.business_unit_id is
    // NULL, which is exactly why the mill picker exists.
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('storage-tank')
        ->range('2026-09-01', '2026-09-30')
        ->open()
        ->named('Periode September Alpha')
        ->create();

    /** Slots per tank per day — the canonical grid the input screens use. */
    $this->slotsPerDay = count(StorageTankRecordService::canonicalTimeSlots());

    /** A ten-day window, for the per-tank movement fixtures. */
    $this->periodShort = fn () => Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('storage-tank')
        ->range('2026-09-01', '2026-09-10')
        ->open()
        ->named('Periode Sepuluh Hari')
        ->create();
});

// =====================================================================
// GROUP A — STOCK IS A COMPARISON OF TWO READINGS (cases 1-9)
// =====================================================================

// ---------------------------------------------------------------------
// Case 1 — JEBAKAN 1a: opening is the FIRST reading by time, not the lowest
// ---------------------------------------------------------------------
it('case 1 — JEBAKAN 1a: stok awal adalah pembacaan PERTAMA menurut waktu, bukan nilai terendah', function () {
    $this->actingAs($this->supervisorA);

    storageTankReportScrambledTank($this->stationA);

    $summary = $this->service->summary($this->periodA);
    $tank = storageTankReportByTank($summary, 'TK-01');

    expect($tank['opening_mt'])->toBe(100.0);
    // The two wrong answers, named so a regression says WHICH rule broke.
    expect($tank['opening_mt'])->not->toBe(80.0);   // the lowest value
    expect($tank['opening_mt'])->not->toBe(250.0);  // the first row stored

    expect($summary['stock']['opening_mt'])->toBe(100.0);
    expect($summary['stock']['opening_mt'])->not->toBe(250.0);
});

// ---------------------------------------------------------------------
// Case 2 — JEBAKAN 1b: closing is the LAST reading by time, not the highest
// ---------------------------------------------------------------------
it('case 2 — JEBAKAN 1b: stok akhir adalah pembacaan TERAKHIR menurut waktu, bukan nilai tertinggi', function () {
    $this->actingAs($this->supervisorA);

    storageTankReportScrambledTank($this->stationA);

    $summary = $this->service->summary($this->periodA);
    $tank = storageTankReportByTank($summary, 'TK-01');

    expect($tank['closing_mt'])->toBe(80.0);
    expect($tank['closing_mt'])->not->toBe(250.0); // the highest value
    expect($tank['closing_mt'])->not->toBe(100.0); // the last row stored

    expect($summary['stock']['closing_mt'])->toBe(80.0);
});

// ---------------------------------------------------------------------
// Case 3 — JEBAKAN 1c: the timestamps point at the readings used
// ---------------------------------------------------------------------
it('case 3 — JEBAKAN 1c: opening_at dan closing_at menunjuk waktu pembacaan yang dipakai, bukan ujung periode', function () {
    $this->actingAs($this->supervisorA);

    storageTankReportScrambledTank($this->stationA);

    $summary = $this->service->summary($this->periodA);
    $tank = storageTankReportByTank($summary, 'TK-01');

    expect($tank['opening_at'])->toBe('2026-09-01 06:00');
    expect($tank['closing_at'])->toBe('2026-09-10 18:00');

    expect($summary['stock']['opening_at'])->toBe('2026-09-01 06:00');
    expect($summary['stock']['closing_at'])->toBe('2026-09-10 18:00');

    // NOT the bare period bound: a timestamp without the hour cannot say
    // how far apart the two compared readings actually sit.
    expect($tank['opening_at'])->not->toBe($summary['period']['start_date']);
    expect($tank['closing_at'])->not->toBe($summary['period']['end_date']);
    expect($tank['closing_at'])->not->toBe('2026-09-30 18:00');
});

// ---------------------------------------------------------------------
// Case 4 — JEBAKAN 1d: movement is closing - opening BY TIME, not max - min
// ---------------------------------------------------------------------
it('case 4 — JEBAKAN 1d: pergerakan tangki adalah closing - opening menurut waktu, bukan max - min', function () {
    $this->actingAs($this->supervisorA);

    storageTankReportScrambledTank($this->stationA);

    $tank = storageTankReportByTank($this->service->summary($this->periodA), 'TK-01');

    expect($tank['movement_mt'])->toBe(-20.0);
    expect($tank['movement_mt'])->not->toBe(170.0); // 250 - 80, the spread
    expect($tank['movement_mt'])->not->toBe(150.0); // 250 - 100
    expect($tank['movement_computable'])->toBeTrue();
});

// ---------------------------------------------------------------------
// Case 5 — JEBAKAN 2: ordering is (date, time_slot), never time_slot alone
// ---------------------------------------------------------------------
it('case 5 — JEBAKAN 2: pengurutan memakai (date, time_slot), bukan time_slot saja', function () {
    $this->actingAs($this->supervisorA);

    // The EARLIER date carries the LATER slot, on purpose.
    storageTankReportRecord($this->stationA, '2026-09-01', 'TK-02', [
        ['time_slot' => '18:00', 'calculated_weight_mt' => 120.0],
    ]);
    storageTankReportRecord($this->stationA, '2026-09-02', 'TK-02', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 90.0],
    ]);

    $tank = storageTankReportByTank($this->service->summary($this->periodA), 'TK-02');

    // An ordering that looks only at time_slot answers 90,0 here.
    expect($tank['opening_mt'])->toBe(120.0);
    expect($tank['opening_mt'])->not->toBe(90.0);
    expect($tank['opening_at'])->toBe('2026-09-01 18:00');
    expect($tank['closing_mt'])->toBe(90.0);
    expect($tank['closing_at'])->toBe('2026-09-02 06:00');
    expect($tank['movement_mt'])->toBe(-30.0);
});

// ---------------------------------------------------------------------
// Case 6 — JEBAKAN 3: movement is summed PER TANK, never combined-stock
// ---------------------------------------------------------------------
it('case 6 — JEBAKAN 3: stock.movement_mt adalah jumlah pergerakan PER TANGKI, bukan selisih stok gabungan', function () {
    $this->actingAs($this->supervisorA);

    $period = ($this->periodShort)();

    // TK-01 spans the period: -10,0.
    storageTankReportRecord($this->stationA, '2026-09-01', 'TK-01', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 100.0],
    ]);
    storageTankReportRecord($this->stationA, '2026-09-10', 'TK-01', [
        ['time_slot' => '18:00', 'calculated_weight_mt' => 90.0],
    ]);

    // TK-02 is recorded ONLY at the end of the period: +20,0 — and it is
    // this tank that makes the two methods disagree.
    storageTankReportRecord($this->stationA, '2026-09-09', 'TK-02', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 400.0],
    ]);
    storageTankReportRecord($this->stationA, '2026-09-10', 'TK-02', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 420.0],
    ]);

    $summary = $this->service->summary($period);

    expect($summary['stock']['movement_mt'])->toBe(10.0);
    expect($summary['stock']['movement_mt'])->not->toBe(410.0); // combined-stock arithmetic
    expect($summary['stock']['movement_mt'])->not->toBe(310.0);

    // It IS, exactly, the column sum of the per-tank table beneath it.
    $computable = array_filter($summary['by_tank'], fn ($tank) => $tank['movement_computable']);
    expect(round(array_sum(array_column($computable, 'movement_mt')), 2))
        ->toBe($summary['stock']['movement_mt']);

    expect($summary['stock']['tanks_with_movement'])->toBe(2);
    expect($summary['stock']['tanks_without_movement'])->toBe(0);
});

// ---------------------------------------------------------------------
// Case 7 — JEBAKAN 4: one stock reading means NULL movement, never 0
// ---------------------------------------------------------------------
it('case 7 — JEBAKAN 4: tangki dengan SATU pembacaan stok punya movement null (bukan nol) dan tidak menyumbang ke total', function () {
    $this->actingAs($this->supervisorA);

    $period = ($this->periodShort)();

    storageTankReportRecord($this->stationA, '2026-09-01', 'TK-01', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 100.0],
    ]);
    storageTankReportRecord($this->stationA, '2026-09-10', 'TK-01', [
        ['time_slot' => '18:00', 'calculated_weight_mt' => 130.0],
    ]);

    // ONE stock reading, and a large one — so an implementation that folded
    // it in would be unmistakable (530,0).
    storageTankReportRecord($this->stationA, '2026-09-10', 'TK-03', [
        ['time_slot' => '12:00', 'calculated_weight_mt' => 500.0],
    ]);

    $summary = $this->service->summary($period);
    $tank = storageTankReportByTank($summary, 'TK-03');

    expect($tank['movement_mt'])->toBeNull();
    expect($tank['movement_mt'])->not->toBe(0);
    expect($tank['movement_mt'])->not->toBe(0.0);
    expect($tank['movement_computable'])->toBeFalse();

    // Opening and closing are the SAME row — which is precisely why their
    // difference measures nothing.
    expect($tank['opening_mt'])->toBe(500.0);
    expect($tank['closing_mt'])->toBe(500.0);
    expect($tank['opening_at'])->toBe('2026-09-10 12:00');
    expect($tank['closing_at'])->toBe('2026-09-10 12:00');

    expect($summary['stock']['movement_mt'])->toBe(30.0);
    expect($summary['stock']['movement_mt'])->not->toBe(530.0);
    expect($summary['stock']['tanks_without_movement'])->toBe(1);
    expect($summary['stock']['tanks_with_movement'])->toBe(1);
});

// ---------------------------------------------------------------------
// Case 8 — JEBAKAN 5: an empty first reading defers the opening
// ---------------------------------------------------------------------
it('case 8 — JEBAKAN 5: pembacaan paling awal tanpa stok membuat opening diambil dari pembacaan terisi berikutnya', function () {
    $this->actingAs($this->supervisorA);

    storageTankReportRecord($this->stationA, '2026-09-01', 'TK-04', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => null, 'ffa_percent' => 3.2],
    ]);
    storageTankReportRecord($this->stationA, '2026-09-03', 'TK-04', [
        ['time_slot' => '12:00', 'calculated_weight_mt' => 150.0],
    ]);
    storageTankReportRecord($this->stationA, '2026-09-09', 'TK-04', [
        ['time_slot' => '18:00', 'calculated_weight_mt' => 140.0],
    ]);

    $summary = $this->service->summary($this->periodA);
    $tank = storageTankReportByTank($summary, 'TK-04');

    expect($tank['opening_mt'])->toBe(150.0);
    expect($tank['opening_mt'])->not->toBe(0.0);
    expect($tank['opening_mt'])->not->toBeNull();

    // The SECOND reading's instant — the two days before it were never
    // measured, and that is what this timestamp exists to say.
    expect($tank['opening_at'])->toBe('2026-09-03 12:00');
    expect($tank['opening_at'])->not->toBe('2026-09-01 06:00');

    expect($tank['movement_mt'])->toBe(-10.0);

    // The stock-less first row is still a reading: it counts for FFA.
    expect($summary['metrics']['ffa_percent']['reading_count'])->toBe(1);
    expect($summary['metrics']['ffa_percent']['avg'])->toBe(3.2);
});

// ---------------------------------------------------------------------
// Case 9 — JEBAKAN 6: a negative movement is reported as-is
// ---------------------------------------------------------------------
it('case 9 — JEBAKAN 6: pergerakan negatif ditampilkan apa adanya', function () {
    $this->actingAs($this->supervisorA);

    storageTankReportRecord($this->stationA, '2026-09-02', 'TK-05', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 500.0],
    ]);
    storageTankReportRecord($this->stationA, '2026-09-08', 'TK-05', [
        ['time_slot' => '18:00', 'calculated_weight_mt' => 320.0],
    ]);

    $summary = $this->service->summary($this->periodA);
    $tank = storageTankReportByTank($summary, 'TK-05');

    expect($tank['movement_mt'])->toBe(-180.0);
    expect($tank['movement_mt'])->not->toBe(180.0); // no abs()
    expect($tank['movement_mt'])->not->toBe(0.0);   // no clamping
    expect($tank['movement_mt'])->not->toBe(0);
    expect($tank['movement_computable'])->toBeTrue();

    expect($summary['stock']['movement_mt'])->toBe(-180.0);
});

// =====================================================================
// GROUP B — METRICS: READ, NEVER DERIVED; EACH WITH ITS OWN DENOMINATOR
// =====================================================================

// ---------------------------------------------------------------------
// Case 10 — JEBAKAN 7: average temperature comes from its own column
// ---------------------------------------------------------------------
it('case 10 — JEBAKAN 7: suhu rata-rata diambil dari kolom average_temperature_c, tidak dihitung ulang dari tiga suhu', function () {
    $this->actingAs($this->supervisorA);

    // 50/60/70 has arithmetic mean 60,0 — and the Operator recorded 55,0.
    // An implementation that recomputes answers 60,0 and fails here.
    storageTankReportRecord($this->stationA, '2026-09-04', 'TK-01', [
        [
            'oil_temperature_top_c' => 50.0,
            'oil_temperature_middle_c' => 60.0,
            'oil_temperature_bottom_c' => 70.0,
            'average_temperature_c' => 55.0,
        ],
    ]);

    $metrics = $this->service->summary($this->periodA)['metrics'];

    expect($metrics['average_temperature_c']['avg'])->toBe(55.0);
    expect($metrics['average_temperature_c']['avg'])->not->toBe(60.0);
    expect($metrics['average_temperature_c']['min'])->toBe(55.0);
    expect($metrics['average_temperature_c']['max'])->toBe(55.0);
    expect($metrics['average_temperature_c']['reading_count'])->toBe(1);

    // The three positional temperatures remain three metrics of their own.
    expect($metrics['oil_temperature_top_c']['avg'])->toBe(50.0);
    expect($metrics['oil_temperature_middle_c']['avg'])->toBe(60.0);
    expect($metrics['oil_temperature_bottom_c']['avg'])->toBe(70.0);
});

// ---------------------------------------------------------------------
// Case 11 — JEBAKAN 7b: an empty average column derives nothing
// ---------------------------------------------------------------------
it('case 11 — JEBAKAN 7b: kolom average_temperature_c kosong membuat suhu rata-rata tidak tersedia', function () {
    $this->actingAs($this->supervisorA);

    storageTankReportRecord($this->stationA, '2026-09-04', 'TK-01', [
        [
            'oil_temperature_top_c' => 50.0,
            'oil_temperature_middle_c' => 60.0,
            'oil_temperature_bottom_c' => 70.0,
            'average_temperature_c' => null,
        ],
    ]);

    $summary = $this->service->summary($this->periodA);

    expect($summary['metrics']['average_temperature_c'])
        ->toBe(['min' => null, 'avg' => null, 'max' => null, 'reading_count' => 0]);
    expect($summary['metrics']['average_temperature_c']['avg'])->not->toBe(60.0);

    // Nothing anywhere is derived from the three positional temperatures.
    foreach ($summary['by_tank'] as $tank) {
        expect($tank['average_temperature_avg'])->toBeNull();
    }

    foreach ($summary['daily'] as $day) {
        expect($day['temperature_avg'])->toBeNull();
    }
});

// ---------------------------------------------------------------------
// Case 12 — a separate denominator per metric
// ---------------------------------------------------------------------
it('case 12 — penyebut terpisah: setiap metrik punya reading_count sendiri', function () {
    $this->actingAs($this->supervisorA);

    storageTankReportRecord($this->stationA, '2026-09-04', 'TK-01', [
        ['ffa_percent' => 3.0, 'moisture_content_percent' => 0.2, 'dobi_index' => null],
        ['ffa_percent' => 5.0, 'moisture_content_percent' => null, 'dobi_index' => null],
        ['ffa_percent' => null, 'moisture_content_percent' => null, 'dobi_index' => 2.4],
    ]);

    $metrics = $this->service->summary($this->periodA)['metrics'];

    expect($metrics['ffa_percent'])->toBe(['min' => 3.0, 'avg' => 4.0, 'max' => 5.0, 'reading_count' => 2]);
    expect($metrics['moisture_content_percent'])->toBe(['min' => 0.2, 'avg' => 0.2, 'max' => 0.2, 'reading_count' => 1]);
    expect($metrics['dobi_index'])->toBe(['min' => 2.4, 'avg' => 2.4, 'max' => 2.4, 'reading_count' => 1]);

    // NOT ONE of the three carries the shared filled-row count of 3 — a
    // single shared denominator would deflate every rarely-filled metric
    // while still producing a plausible number.
    foreach (['ffa_percent', 'moisture_content_percent', 'dobi_index'] as $metric) {
        expect($metrics[$metric]['reading_count'])->not->toBe(3);
    }
});

// ---------------------------------------------------------------------
// Case 13 — null is not zero: it does not drag an average down
// ---------------------------------------------------------------------
it('case 13 — NULL bukan nol: nilai null tidak menurunkan rata-rata metrik', function () {
    $this->actingAs($this->supervisorA);

    storageTankReportRecord($this->stationA, '2026-09-04', 'TK-01', [
        ['moisture_content_percent' => 0.2],
        ['moisture_content_percent' => null, 'ffa_percent' => 4.0],
    ]);

    $metrics = $this->service->summary($this->periodA)['metrics'];

    expect($metrics['moisture_content_percent']['avg'])->toBe(0.2);
    expect($metrics['moisture_content_percent']['avg'])->not->toBe(0.1); // 0,2 / 2
    expect($metrics['moisture_content_percent']['reading_count'])->toBe(1);
    expect($metrics['moisture_content_percent']['reading_count'])->not->toBe(2);
    expect($metrics['moisture_content_percent']['min'])->not->toBe(0.0);
    expect($metrics['moisture_content_percent']['min'])->toBe(0.2);
});

// ---------------------------------------------------------------------
// Case 14 — a never-filled metric is unavailable, and only that metric
// ---------------------------------------------------------------------
it('case 14 — metrik yang tidak pernah diisi: reading_count 0 dan nilai null, metrik lain tetap normal', function () {
    $this->actingAs($this->supervisorA);

    storageTankReportRecord($this->stationA, '2026-09-04', 'TK-01', [
        ['ffa_percent' => 3.0, 'impurities_dirt_percent' => null],
        ['ffa_percent' => 4.0, 'impurities_dirt_percent' => null],
        ['ffa_percent' => 5.0, 'impurities_dirt_percent' => null],
    ]);

    $metrics = $this->service->summary($this->periodA)['metrics'];

    expect($metrics['impurities_dirt_percent'])
        ->toBe(['min' => null, 'avg' => null, 'max' => null, 'reading_count' => 0]);
    expect($metrics['impurities_dirt_percent']['avg'])->not->toBe(0);
    expect($metrics['impurities_dirt_percent']['avg'])->not->toBe(0.0);

    // Untouched neighbour.
    expect($metrics['ffa_percent']['reading_count'])->toBe(3);
    expect($metrics['ffa_percent']['avg'])->toBe(4.0);
});

// ---------------------------------------------------------------------
// Case 15 — exactly ten metric keys, in order, each with four sub-keys
// ---------------------------------------------------------------------
it('case 15 — kunci metrics persis sepuluh metrik yang disepakati, tidak lebih dan tidak kurang', function () {
    $this->actingAs($this->supervisorA);

    storageTankReportRecord($this->stationA, '2026-09-04', 'TK-01', [
        [
            'cpo_sounding_depth_mm' => 1500.0,
            'water_dip_bottom_depth_mm' => 120.0,
            'net_oil_depth_mm' => 1380.0,
            'oil_temperature_top_c' => 50.0,
            'oil_temperature_middle_c' => 60.0,
            'oil_temperature_bottom_c' => 70.0,
            'average_temperature_c' => 55.0,
            'calculated_volume_m3' => 1200.0,
            'calculated_weight_mt' => 1080.0,
            'ffa_percent' => 4.0,
            'moisture_content_percent' => 0.2,
            'impurities_dirt_percent' => 0.02,
            'dobi_index' => 3.0,
        ],
    ]);

    $metrics = $this->service->summary($this->periodA)['metrics'];

    // Derived from the service constant rather than a literal list, so the
    // contract lives in exactly one place.
    expect(array_keys($metrics))->toBe(StorageTankReportService::NUMERIC_METRICS);
    expect(array_keys($metrics))->toBe([
        'ffa_percent',
        'moisture_content_percent',
        'impurities_dirt_percent',
        'dobi_index',
        'average_temperature_c',
        'calculated_weight_mt',
        'calculated_volume_m3',
        'oil_temperature_top_c',
        'oil_temperature_middle_c',
        'oil_temperature_bottom_c',
    ]);
    expect($metrics)->toHaveCount(10);

    foreach ($metrics as $metric => $stats) {
        expect(array_keys($stats))->toBe(['min', 'avg', 'max', 'reading_count']);
    }

    // The three raw sounding depths are inputs, not reported metrics.
    foreach (['cpo_sounding_depth_mm', 'water_dip_bottom_depth_mm', 'net_oil_depth_mm'] as $absent) {
        expect($metrics)->not->toHaveKey($absent);
    }
});

// =====================================================================
// GROUP C — COVERAGE, ORDERING AND RANGE
// =====================================================================

// ---------------------------------------------------------------------
// Case 16 — an all-null row is not a filled slot
// ---------------------------------------------------------------------
it('case 16 — kelengkapan: baris dengan seluruh 17 kolom non-time_slot NULL tidak dihitung sebagai slot terisi', function () {
    $this->actingAs($this->supervisorA);

    $period = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('storage-tank')
        ->range('2026-09-01', '2026-09-02')->open()->named('Periode Dua Hari')->create();

    storageTankReportRecord($this->stationA, '2026-09-01', 'TK-01', [
        ['time_slot' => '07:00', 'calculated_weight_mt' => 100.0],
        ['time_slot' => '08:00', 'ffa_percent' => 4.0],
        // Every one of the SEVENTEEN non-time_slot columns left null — the
        // factory default. An untouched slot, not a measured zero.
        ['time_slot' => '09:00'],
    ]);

    $summary = $this->service->summary($period);

    expect($summary['coverage']['filled_slots'])->toBe(2);
    expect($summary['coverage']['filled_slots'])->not->toBe(3);
    expect($summary['total']['reading_rows'])->toBe(2);

    // The definition is DELEGATED to the input screens' own rule rather
    // than re-implemented here.
    $recordService = app(StorageTankRecordService::class);
    expect($recordService->isRowFilled(array_fill_keys(StorageTankRecordService::READING_FIELDS, null)))->toBeFalse();
    expect(StorageTankRecordService::READING_FIELDS)->toHaveCount(17);
});

// ---------------------------------------------------------------------
// Case 17 — expected_slots = tanks x days x 24
// ---------------------------------------------------------------------
it('case 17 — kelengkapan: expected_slots = tank_count x days_in_period x 24', function () {
    $this->actingAs($this->supervisorA);

    $period = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('storage-tank')
        ->range('2026-09-01', '2026-09-05')->open()->named('Periode Lima Hari')->create();

    storageTankReportRecord($this->stationA, '2026-09-01', 'TK-01', [
        ['time_slot' => '07:00', 'calculated_weight_mt' => 100.0],
        ['time_slot' => '08:00', 'calculated_weight_mt' => 110.0],
        ['time_slot' => '09:00', 'ffa_percent' => 4.0],
    ]);
    storageTankReportRecord($this->stationA, '2026-09-02', 'TK-02', [
        ['time_slot' => '07:00', 'calculated_weight_mt' => 200.0],
        ['time_slot' => '08:00', 'calculated_weight_mt' => 210.0],
        ['time_slot' => '09:00', 'ffa_percent' => 4.2],
    ]);

    $coverage = $this->service->summary($period)['coverage'];

    expect($coverage['tank_count'])->toBe(2);
    expect($coverage['days_in_period'])->toBe(5);
    expect($coverage['slots_per_tank_per_day'])->toBe(24);
    expect($coverage['slots_per_tank_per_day'])->toBe($this->slotsPerDay);
    expect($coverage['expected_slots'])->toBe(240);
    expect($coverage['expected_slots'])->toBe(2 * 5 * $this->slotsPerDay);
    expect($coverage['filled_slots'])->toBe(6);
    expect($coverage['coverage_percent'])->toBe(2.5);
});

// ---------------------------------------------------------------------
// Case 18 — very low coverage still yields figures, never a refusal
// ---------------------------------------------------------------------
it('case 18 — kelengkapan sangat rendah tetap menghasilkan angka, bukan penolakan', function () {
    $this->actingAs($this->supervisorA);

    $period = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('storage-tank')
        ->range('2026-09-01', '2026-09-05')->open()->named('Periode Tipis')->create();

    storageTankReportRecord($this->stationA, '2026-09-01', 'TK-01', [
        ['time_slot' => '07:00', 'calculated_weight_mt' => 100.0, 'ffa_percent' => 4.0],
        ['time_slot' => '08:00', 'calculated_weight_mt' => 90.0],
    ]);
    storageTankReportRecord($this->stationA, '2026-09-03', 'TK-02', [
        ['time_slot' => '07:00', 'calculated_weight_mt' => 300.0],
    ]);

    $summary = $this->service->summary($period);

    expect($summary['coverage']['expected_slots'])->toBe(240);
    expect($summary['coverage']['filled_slots'])->toBe(3);
    // Two decimals, not one: 1,25 % and 1,3 % are different answers about
    // how badly a period was recorded.
    expect($summary['coverage']['coverage_percent'])->toBe(1.25);

    // The figures are still computed from those three rows.
    expect($summary['stock']['opening_mt'])->toBe(400.0);  // 100 + 300
    expect($summary['stock']['closing_mt'])->toBe(390.0);  // 90 + 300
    expect($summary['stock']['movement_mt'])->toBe(-10.0); // TK-02 not computable
    expect($summary['metrics']['ffa_percent']['avg'])->toBe(4.0);
    expect($summary['has_data'])->toBeTrue();

    // No warning marker of any kind rides along on a badly recorded period.
    $json = strtolower(json_encode($summary));
    foreach (['warning', 'severity', 'alert', 'is_danger'] as $flag) {
        expect($json)->not->toContain($flag);
    }
});

// ---------------------------------------------------------------------
// Case 19 — time_slot stays a TIME and orders within one date
// ---------------------------------------------------------------------
it('case 19 — time_slot tetap TIME dan menentukan urutan dalam satu tanggal', function () {
    $this->actingAs($this->supervisorA);

    $record = StorageTankRecord::factory()->forStation($this->stationA)->onDate('2026-09-04')
        ->create(['storage_tank_id' => 'TK-01', 'note' => null]);

    // Written OUT OF ORDER: 18:00 first, 06:00 last.
    StorageTankDetail::factory()->forRecord($record)->create(['time_slot' => '18:00', 'calculated_weight_mt' => 300.0]);
    StorageTankDetail::factory()->forRecord($record)->create(['time_slot' => '12:00', 'calculated_weight_mt' => 200.0]);
    StorageTankDetail::factory()->forRecord($record)->create(['time_slot' => '06:00', 'calculated_weight_mt' => 100.0]);

    $summary = $this->service->summary($this->periodA);
    $tank = storageTankReportByTank($summary, 'TK-01');

    expect($tank['opening_mt'])->toBe(100.0);  // 06:00
    expect($tank['closing_mt'])->toBe(300.0);  // 18:00
    expect($tank['opening_at'])->toBe('2026-09-04 06:00');
    expect($tank['closing_at'])->toBe('2026-09-04 18:00');

    // Hour-and-minute, never a full datetime and never an integer hour —
    // an integer cast would collapse 06:00 with 06:30 and sort 00:30 after
    // 10:00.
    expect(substr($tank['opening_at'], 10))->toBe(' 06:00');
    expect($tank['opening_at'])->toMatch('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/');

    foreach (StorageTankDetail::all() as $detail) {
        expect($detail->time_slot)->toBeString();
        expect($detail->time_slot)->toMatch('/^\d{2}:\d{2}(:\d{2})?$/');
        expect($detail->time_slot)->not->toBeInt();
    }
});

// ---------------------------------------------------------------------
// Case 20 — one date, several tanks, each aggregated on its own
// ---------------------------------------------------------------------
it('case 20 — satu tanggal beberapa tangki: tiap tangki diurutkan dan dihitung sendiri', function () {
    $this->actingAs($this->supervisorA);

    storageTankReportRecord($this->stationA, '2026-09-04', 'TK-01', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 100.0],
        ['time_slot' => '18:00', 'calculated_weight_mt' => 90.0],
    ]);
    storageTankReportRecord($this->stationA, '2026-09-04', 'TK-02', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 400.0],
        ['time_slot' => '18:00', 'calculated_weight_mt' => 450.0],
    ]);

    $summary = $this->service->summary($this->periodA);

    expect($summary['by_tank'])->toHaveCount(2);
    expect(storageTankReportByTank($summary, 'TK-01')['movement_mt'])->toBe(-10.0);
    expect(storageTankReportByTank($summary, 'TK-02')['movement_mt'])->toBe(50.0);
    expect($summary['stock']['movement_mt'])->toBe(40.0);

    // No reading ever crosses from one storage_tank_id to another.
    expect(storageTankReportByTank($summary, 'TK-01')['opening_mt'])->toBe(100.0);
    expect(storageTankReportByTank($summary, 'TK-01')['closing_mt'])->toBe(90.0);
    expect(storageTankReportByTank($summary, 'TK-02')['opening_mt'])->toBe(400.0);
    expect(storageTankReportByTank($summary, 'TK-02')['closing_mt'])->toBe(450.0);
});

// ---------------------------------------------------------------------
// Case 21 — a tank with records but no stock reading still appears
// ---------------------------------------------------------------------
it('case 21 — tangki punya record tetapi tidak satu pun pembacaan stok terisi: tetap muncul dengan nilai null', function () {
    $this->actingAs($this->supervisorA);

    storageTankReportRecord($this->stationA, '2026-09-04', 'TK-06', [
        ['ffa_percent' => 4.0, 'calculated_weight_mt' => null],
        ['ffa_percent' => 4.4, 'calculated_weight_mt' => null],
    ]);

    $summary = $this->service->summary($this->periodA);
    $tank = storageTankReportByTank($summary, 'TK-06');

    // NOT dropped — dropping it would hide exactly the tank that was never
    // measured.
    expect($tank)->not->toBeNull();
    expect($tank['reading_count'])->toBe(2);
    expect($tank['opening_mt'])->toBeNull();
    expect($tank['closing_mt'])->toBeNull();
    expect($tank['opening_at'])->toBeNull();
    expect($tank['closing_at'])->toBeNull();
    expect($tank['movement_mt'])->toBeNull();
    expect($tank['movement_computable'])->toBeFalse();
    expect($tank['ffa_avg'])->toBe(4.2);

    // The invariant that keeps the movement card honest.
    expect($summary['stock']['tanks_with_movement'] + $summary['stock']['tanks_without_movement'])
        ->toBe(count($summary['by_tank']));
});

// ---------------------------------------------------------------------
// Case 22 — the period range is inclusive at both ends
// ---------------------------------------------------------------------
it('case 22 — rentang periode inklusif di kedua ujung (whereDate)', function () {
    $this->actingAs($this->supervisorA);

    $period = ($this->periodShort)();

    storageTankReportRecord($this->stationA, '2026-08-31', 'TK-01', [
        ['time_slot' => '23:00', 'calculated_weight_mt' => 999.0],
    ]);
    storageTankReportRecord($this->stationA, '2026-09-01', 'TK-01', [
        ['time_slot' => '00:00', 'calculated_weight_mt' => 111.0],
    ]);
    storageTankReportRecord($this->stationA, '2026-09-10', 'TK-01', [
        ['time_slot' => '23:00', 'calculated_weight_mt' => 222.0],
    ]);
    storageTankReportRecord($this->stationA, '2026-09-11', 'TK-01', [
        ['time_slot' => '00:00', 'calculated_weight_mt' => 888.0],
    ]);

    $summary = $this->service->summary($period);
    $tank = storageTankReportByTank($summary, 'TK-01');

    expect($summary['total']['reading_rows'])->toBe(2);
    expect($tank['opening_mt'])->toBe(111.0);
    expect($tank['opening_at'])->toBe('2026-09-01 00:00');
    expect($tank['closing_mt'])->toBe(222.0);
    expect($tank['closing_at'])->toBe('2026-09-10 23:00');

    // The out-of-range readings never become an endpoint, and never appear.
    expect($tank['opening_mt'])->not->toBe(999.0);
    expect($tank['closing_mt'])->not->toBe(888.0);
    expect(array_column($summary['daily'], 'date'))->toBe(['2026-09-01', '2026-09-10']);
});

// ---------------------------------------------------------------------
// Case 23 — daily[] has one entry per date that has a record
// ---------------------------------------------------------------------
it('case 23 — daily[] memuat satu entri per tanggal ber-record, dengan rata-rata per metrik', function () {
    $this->actingAs($this->supervisorA);

    $period = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('storage-tank')
        ->range('2026-09-01', '2026-09-03')->open()->named('Periode Tiga Hari')->create();

    storageTankReportRecord($this->stationA, '2026-09-01', 'TK-01', [
        ['time_slot' => '07:00', 'ffa_percent' => 3.0, 'calculated_weight_mt' => 100.0],
    ]);
    // 2026-09-02 has no record at all — and gets NO row: a padded zero row
    // would read as "we measured zero".
    storageTankReportRecord($this->stationA, '2026-09-03', 'TK-01', [
        ['time_slot' => '07:00', 'moisture_content_percent' => 0.2],
    ]);

    $summary = $this->service->summary($period);

    expect($summary['daily'])->toHaveCount(2);
    expect(array_column($summary['daily'], 'date'))->toBe(['2026-09-01', '2026-09-03']);

    foreach ($summary['daily'] as $row) {
        expect(array_keys($row))->toBe([
            'date', 'filled_slots', 'stock_total_mt',
            'ffa_avg', 'moisture_avg', 'impurities_avg', 'dobi_avg', 'temperature_avg',
        ]);
    }

    // A metric unfilled on a date is null on that date — never 0,0.
    expect($summary['daily'][0]['ffa_avg'])->toBe(3.0);
    expect($summary['daily'][0]['moisture_avg'])->toBeNull();
    expect($summary['daily'][0]['moisture_avg'])->not->toBe(0.0);
    expect($summary['daily'][0]['stock_total_mt'])->toBe(100.0);
    expect($summary['daily'][1]['moisture_avg'])->toBe(0.2);
    expect($summary['daily'][1]['ffa_avg'])->toBeNull();
    expect($summary['daily'][1]['ffa_avg'])->not->toBe(0.0);
    expect($summary['daily'][1]['stock_total_mt'])->toBeNull();

    expect($summary['total']['days_with_records'])->toBe(2);
});

// ---------------------------------------------------------------------
// Case 24 — total{} is counted from rows that really exist
// ---------------------------------------------------------------------
it('case 24 — total{days_with_records, reading_rows} dihitung dari baris yang benar-benar ada', function () {
    $this->actingAs($this->supervisorA);

    // 5 + 4 + 4 + 4 = 17 filled rows across 4 dates.
    foreach ([['2026-09-01', 5], ['2026-09-02', 4], ['2026-09-03', 4], ['2026-09-04', 4]] as [$date, $count]) {
        $rows = [];

        for ($index = 0; $index < $count; $index++) {
            $rows[] = ['ffa_percent' => 4.0];
        }

        storageTankReportRecord($this->stationA, $date, 'TK-01', $rows);
    }

    $summary = $this->service->summary($this->periodA);

    expect($summary['total']['days_with_records'])->toBe(4);
    expect($summary['total']['reading_rows'])->toBe(17);

    // Neither figure is derived from the expected-slot grid.
    expect($summary['total']['reading_rows'])->not->toBe($summary['coverage']['expected_slots']);
    expect($summary['total']['days_with_records'])->not->toBe($summary['coverage']['days_in_period']);
});

// ---------------------------------------------------------------------
// Case 25 — a period with no reading is null everywhere, never zero
// ---------------------------------------------------------------------
it('case 25 — periode tanpa satu pun pembacaan: seluruh angka null, bukan nol', function () {
    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    expect($summary['stock']['opening_mt'])->toBeNull();
    expect($summary['stock']['closing_mt'])->toBeNull();
    expect($summary['stock']['movement_mt'])->toBeNull();
    expect($summary['stock']['opening_mt'])->not->toBe(0.0);
    expect($summary['stock']['closing_mt'])->not->toBe(0.0);
    expect($summary['stock']['movement_mt'])->not->toBe(0.0);
    expect($summary['stock']['opening_at'])->toBeNull();
    expect($summary['stock']['closing_at'])->toBeNull();

    foreach (StorageTankReportService::NUMERIC_METRICS as $metric) {
        expect($summary['metrics'][$metric])
            ->toBe(['min' => null, 'avg' => null, 'max' => null, 'reading_count' => 0]);
    }

    expect($summary['by_tank'])->toBe([]);
    expect($summary['daily'])->toBe([]);
    expect($summary['total']['days_with_records'])->toBe(0);
    expect($summary['total']['reading_rows'])->toBe(0);
    expect($summary['coverage']['filled_slots'])->toBe(0);
    expect($summary['coverage']['tank_count'])->toBe(0);
    expect($summary['has_data'])->toBeFalse();
});

// =====================================================================
// GROUP D — WHAT NEVER APPEARS IN THE SUMMARY, AND THE EXPORT
// =====================================================================

// ---------------------------------------------------------------------
// Case 26 — the valve enum and the three text columns are never aggregated
// ---------------------------------------------------------------------
it('case 26 — enum katup uap dan tiga kolom teks tidak pernah diagregasi pada summary', function () {
    $this->actingAs($this->supervisorA);

    storageTankReportRecord($this->stationA, '2026-09-04', 'TK-01', [
        [
            'steam_heating_valve_status' => 'open_1_2',
            'tank_structural_condition' => 'baik',
            'inspector_name' => 'Budi',
            'findings' => 'tidak ada kebocoran',
            'calculated_weight_mt' => 100.0,
        ],
    ]);

    $summary = $this->service->summary($this->periodA);
    $json = json_encode($summary);

    foreach (['steam_heating_valve_status', 'tank_structural_condition', 'inspector_name', 'findings'] as $column) {
        expect($summary['metrics'])->not->toHaveKey($column);
        expect($json)->not->toContain($column);
    }

    expect($json)->not->toContain('open_1_2');
    expect($json)->not->toContain('Budi');
    expect($json)->not->toContain('tidak ada kebocoran');

    // A row carrying only those four IS still a filled slot for coverage —
    // something WAS recorded in that slot — while contributing to no metric.
    expect($summary['coverage']['filled_slots'])->toBe(1);
});

// ---------------------------------------------------------------------
// Case 27 — those four columns appear ONLY in the export, one line per slot
//
// SIGNATURE NOTE: the spec's "given" writes export(periodId,
// businessUnitId); the real signature puts $format in the MIDDLE, uniformly
// across all five station report services. The three-parameter call below
// is the contract.
// ---------------------------------------------------------------------
it('case 27 — kolom enum dan teks HANYA muncul pada ekspor, satu baris per slot waktu', function () {
    $this->actingAs($this->supervisorA);

    storageTankReportRecord($this->stationA, '2026-09-04', 'TK-01', [
        [
            'time_slot' => '07:00',
            'steam_heating_valve_status' => 'open_1_2',
            'tank_structural_condition' => 'baik',
            'inspector_name' => 'Budi',
            'findings' => 'tidak ada kebocoran',
            'calculated_weight_mt' => 100.0,
        ],
        [
            'time_slot' => '08:00',
            'steam_heating_valve_status' => 'closed',
            'calculated_weight_mt' => 110.0,
        ],
    ], ['note' => 'catatan record']);

    $response = $this->service->export($this->periodA->id, 'csv', null);

    expect($response)->toBeInstanceOf(StreamedResponse::class);

    $lines = storageTankReportCsvLines($response);

    // Header + ONE LINE PER TIME SLOT (storage_tank_details row).
    expect($lines)->toHaveCount(3);

    $header = str_getcsv($lines[0], ',', '"', '\\');
    expect($header)->toBe(StorageTankReportService::EXPORT_HEADER);
    // context + 1 slot + 17 readings — derived from the constants, never
    // counted by hand, so a change to the export shape lands here loudly
    // instead of leaving a stale literal behind.
    expect($header)->toHaveCount(
        StorageTankReportService::EXPORT_CONTEXT_COLUMN_COUNT + 1 + count(StorageTankRecordService::READING_FIELDS)
    );

    $first = str_getcsv($lines[1], ',', '"', '\\');
    $second = str_getcsv($lines[2], ',', '"', '\\');

    // The record's context columns are repeated VERBATIM on every one of
    // that record's time-slot lines, so the file pivots in a spreadsheet.
    $context = StorageTankReportService::EXPORT_CONTEXT_COLUMN_COUNT;
    expect(array_slice($first, 0, $context))->toBe(array_slice($second, 0, $context));
    // The spec's context block is exactly four columns — tanggal, tangki,
    // status, catatan — matching all four sibling station report services.
    expect(array_slice($first, 0, $context))
        ->toBe(['2026-09-04', 'TK-01', 'synced', 'catatan record']);

    // time_slot as hour-and-minute, right after the context block.
    expect($first[$context])->toBe('07:00');
    expect($second[$context])->toBe('08:00');

    // The four columns the summary never carries, at the positions
    // READING_FIELDS puts them in.
    foreach (['steam_heating_valve_status' => 'open_1_2',
        'tank_structural_condition' => 'baik',
        'inspector_name' => 'Budi',
        'findings' => 'tidak ada kebocoran'] as $field => $value) {
        $index = $context + 1 + array_search($field, StorageTankRecordService::READING_FIELDS, true);

        expect($first[$index])->toBe($value);
    }
});

// ---------------------------------------------------------------------
// Case 28 — aggregation happens in PHP, never in SQL
// ---------------------------------------------------------------------
it('case 28 — agregasi dijalankan di PHP, bukan di SQL', function () {
    $this->actingAs($this->supervisorA);

    storageTankReportRecord($this->stationA, '2026-09-04', 'TK-01', [
        ['calculated_weight_mt' => 100.0, 'ffa_percent' => 4.0],
        ['calculated_weight_mt' => 90.0, 'ffa_percent' => 4.4],
    ]);

    $queries = storageTankReportQueriesDuring(function () {
        $this->service->summary($this->periodA);
    });

    expect($queries)->not->toBeEmpty();

    foreach ($queries as $sql) {
        $lower = strtolower($sql);

        foreach (['avg(', 'min(', 'max(', 'sum(', 'group by'] as $aggregate) {
            expect($lower)->not->toContain($aggregate);
        }
    }
});

// ---------------------------------------------------------------------
// Case 29 — the export refuses eager loading
// ---------------------------------------------------------------------
it('case 29 — ekspor menolak eager loading: kueri baris detail belum dijalankan sebelum generator mulai', function () {
    $this->actingAs($this->supervisorA);

    storageTankReportRecord($this->stationA, '2026-09-04', 'TK-01', [
        ['time_slot' => '07:00', 'calculated_weight_mt' => 100.0],
        ['time_slot' => '08:00', 'calculated_weight_mt' => 90.0],
    ]);

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = strtolower($query->sql);
    });

    $response = $this->service->export($this->periodA->id, 'csv', null);

    expect($response)->toBeInstanceOf(StreamedResponse::class);

    // NOT ONE DETAIL ROW HAS BEEN FETCHED YET. streamExportRows() is the
    // only place that selects `storage_tank_records.*` (and eager-loads the
    // detail rows behind it), and it has not run. The eager row-limit
    // COUNT *has* run — deliberately, because a refused export must never
    // begin streaming, and a count is not a row fetch.
    $beforeStreaming = $queries;
    $rowFetches = fn (array $sqls) => array_values(array_filter(
        $sqls,
        fn ($sql) => str_contains($sql, '"storage_tank_records".*')
    ));

    expect($rowFetches($beforeStreaming))->toBeEmpty();
    // ...while the ceiling check itself DID run up front.
    expect(array_filter($beforeStreaming, fn ($sql) => str_contains($sql, 'count(')))->not->toBeEmpty();

    storageTankReportStreamed($response);

    $afterStreaming = array_slice($queries, count($beforeStreaming));

    // The rows are pulled only once the callback runs...
    expect($rowFetches($afterStreaming))->not->toBeEmpty();

    // ...and in CHUNKS — lazy(200) issues `limit 200`, get() would not.
    expect(array_filter(
        $rowFetches($afterStreaming),
        fn ($sql) => str_contains($sql, 'limit')
    ))->not->toBeEmpty();
});

// ---------------------------------------------------------------------
// Case 30 — a failure WHILE writing the stream is EXPORT_FAILED 422
// ---------------------------------------------------------------------
it('case 30 — ekspor gagal saat penulisan stream: EXPORT_FAILED 422', function () {
    $this->actingAs($this->supervisorA);

    storageTankReportRecord($this->stationA, '2026-09-04', 'TK-01', [
        ['calculated_weight_mt' => 100.0],
    ]);

    $recordsBefore = StorageTankRecord::count();
    $detailsBefore = StorageTankDetail::count();

    $service = new StorageTankReportFailingStreamService;
    $response = $service->export($this->periodA->id, 'csv', null);

    // HOUSE PATTERN: all five station report services export through
    // response()->streamDownload(). Laravel wraps the download callback in
    // its own try/catch (Illuminate\Routing\ResponseFactory::streamDownload)
    // and rethrows ANY Throwable escaping it as a
    // StreamedResponseException, keeping the original reachable via
    // getInnerException(). So the service's ExportFailedException arrives
    // here inside that framework envelope — the envelope is Laravel's, the
    // payload is ours, and it is the payload this case is about. We unwrap
    // and assert on it in full rather than special-casing this one service
    // away from the shared pattern.
    //
    // NOTE for whoever gets here next: the four SIBLING report services
    // (sterilizer, clarification, boiler room, cages & tracks) have no
    // equivalent mid-stream-failure case yet. When you add one, it needs
    // this same unwrap.
    $thrown = null;

    try {
        ob_start();
        $response->sendContent();
    } catch (StreamedResponseException $exception) {
        $thrown = $exception->getInnerException();
    } catch (ExportFailedException $exception) {
        // Belt and braces: if a future Laravel stops wrapping, the bare
        // exception must still satisfy every assertion below unchanged.
        $thrown = $exception;
    } finally {
        ob_end_clean();
    }

    expect($thrown)->toBeInstanceOf(ExportFailedException::class);
    expect($thrown->getStatusCode())->toBe(422);
    expect($thrown->errorCode())->toBe('EXPORT_FAILED');

    // A half-written file must not have changed one row of station data.
    expect(StorageTankRecord::count())->toBe($recordsBefore);
    expect(StorageTankDetail::count())->toBe($detailsBefore);
});

// ---------------------------------------------------------------------
// Case 31 — no threshold flagging, asserted BY NAME
// ---------------------------------------------------------------------
it('case 31 — tidak ada penandaan ambang pada payload, menurut nama kunci', function () {
    $this->actingAs($this->supervisorA);

    storageTankReportRecord($this->stationA, '2026-09-04', 'TK-01', [
        ['ffa_percent' => 4.0, 'average_temperature_c' => 50.0],
        // The extremes, sitting among ordinary values.
        ['ffa_percent' => 42.0, 'average_temperature_c' => 150.0],
        ['ffa_percent' => 4.2, 'average_temperature_c' => 51.0],
    ]);

    $summary = $this->service->summary($this->periodA);
    $json = strtolower(json_encode($summary));

    foreach ([
        'threshold', 'outlier', 'iqr', 'fence', 'is_danger', 'is-danger',
        'text-red', 'severity', 'alert',
    ] as $forbidden) {
        expect($json)->not->toContain($forbidden);
    }

    // The extremes are returned exactly as recorded — judging them is the
    // reader's job, and Storage Tank has no operational-target master.
    expect($summary['metrics']['ffa_percent']['max'])->toBe(42.0);
    expect($summary['metrics']['average_temperature_c']['max'])->toBe(150.0);
    expect($summary['metrics']['ffa_percent']['min'])->toBe(4.0);
});

// =====================================================================
// GROUP E — PERIOD MEMBERSHIP, ACCESS AND ERROR SHAPES
// =====================================================================

// ---------------------------------------------------------------------
// Case 32 — only periods covering Storage Tank (or every type) are listed
// ---------------------------------------------------------------------
it('case 32 — daftar periode hanya yang mencakup Storage Tank atau berlaku semua jenis stasiun', function () {
    $this->actingAs($this->supervisorA);

    $periodOther = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('sterilizer')
        ->range('2026-10-01', '2026-10-31')->open()->named('Periode A Stasiun Lain')->create();
    $periodStorage = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('storage-tank')
        ->range('2026-11-01', '2026-11-30')->closed()->named('Periode B Storage Tank')->create();
    $periodAllTypes = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType(null)
        ->range('2026-12-01', '2026-12-31')->open()->named('Periode C Semua Stasiun')->create();

    $ids = array_column($this->service->listPeriods(), 'id');

    expect($ids)->toContain((string) $periodStorage->id);
    expect($ids)->toContain((string) $periodAllTypes->id);
    expect($ids)->not->toContain((string) $periodOther->id);

    // A CLOSED period is listed exactly like an open one: the period lock
    // governs writing data, not reading a report.
    $closed = collect($this->service->listPeriods())->firstWhere('id', (string) $periodStorage->id);
    expect($closed['status'])->toBe('closed');
});

// ---------------------------------------------------------------------
// Case 33 — a closed period restricts neither reading nor exporting
// ---------------------------------------------------------------------
it('case 33 — status periode tertutup tidak membatasi pembacaan maupun ekspor', function () {
    $this->actingAs($this->supervisorA);

    $closed = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('storage-tank')
        ->range('2026-11-01', '2026-11-30')->closed()->named('Periode Tertutup')->create();

    storageTankReportRecord($this->stationA, '2026-11-04', 'TK-01', [
        ['time_slot' => '07:00', 'calculated_weight_mt' => 100.0],
        ['time_slot' => '08:00', 'calculated_weight_mt' => 90.0],
    ]);

    $summary = $this->service->summary($closed);

    expect($summary['period']['status'])->toBe('closed');
    expect($summary['stock']['opening_mt'])->toBe(100.0);
    expect($summary['stock']['movement_mt'])->toBe(-10.0);
    expect($summary['has_data'])->toBeTrue();

    $response = $this->service->export($closed, 'csv', null);

    expect($response)->toBeInstanceOf(StreamedResponse::class);
    expect(storageTankReportCsvLines($response))->toHaveCount(3);
});

// ---------------------------------------------------------------------
// Case 34 — the client's business_unit_id is IGNORED, not refused
// ---------------------------------------------------------------------
it('case 34 — Supervisor/Mill Management: business_unit_id dari klien DIABAIKAN, bukan ditolak', function () {
    $this->actingAs($this->supervisorA);

    storageTankReportRecord($this->stationA, '2026-09-04', 'TK-01', [
        ['calculated_weight_mt' => 100.0, 'ffa_percent' => 4.0],
    ]);
    // Mill Beta's figure is unmistakable if it ever surfaces.
    storageTankReportRecord($this->stationB, '2026-09-04', 'TK-BETA', [
        ['calculated_weight_mt' => 999.0, 'ffa_percent' => 99.0],
    ]);

    // Another mill's id handed in — discarded, not validated, not compared.
    expect($this->service->resolveBusinessUnit((string) $this->businessUnitB->id))
        ->toBe((string) $this->businessUnitA->id);

    $summary = $this->service->summary($this->periodA, (string) $this->businessUnitB->id);

    expect($summary['business_unit']['id'])->toBe((string) $this->businessUnitA->id);
    expect($summary['business_unit']['name'])->toBe('Mill Alpha');
    expect($summary['period']['business_unit_name'])->toBe('Mill Alpha');

    // Beta's data is nowhere in the answer.
    expect($summary['metrics']['ffa_percent']['max'])->toBe(4.0);
    expect($summary['metrics']['ffa_percent']['max'])->not->toBe(99.0);
    expect($summary['stock']['opening_mt'])->toBe(100.0);
    expect(array_column($summary['by_tank'], 'storage_tank_id'))->toBe(['TK-01']);
});

// ---------------------------------------------------------------------
// Case 35 — another mill's period_id IS refused
// ---------------------------------------------------------------------
it('case 35 — period_id milik mill lain: 403 FORBIDDEN', function () {
    $this->actingAs($this->supervisorA);

    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('storage-tank')
        ->range('2026-09-01', '2026-09-30')->open()->named('Periode Beta')->create();

    storageTankReportRecord($this->stationB, '2026-09-04', 'TK-BETA', [
        ['calculated_weight_mt' => 999.0],
    ]);

    expect(fn () => $this->service->summary($periodB))
        ->toThrow(AuthorizationException::class);

    // And no figure of mill Beta is produced by any other entry point.
    expect(fn () => $this->service->authorizePeriod((string) $periodB->id))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->export($periodB, 'csv', null))
        ->toThrow(AuthorizationException::class);
});

// ---------------------------------------------------------------------
// Case 36 — a bound account with no mill fails CLOSED, proven by a spy
// ---------------------------------------------------------------------
it('case 36 — akun Supervisor/Mill Management tanpa business_unit_id: 422 dan daftar seluruh mill TIDAK PERNAH disusun', function () {
    $orphan = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);
    $this->actingAs($orphan);

    $spy = new StorageTankReportAllBusinessUnitsSpy;

    $thrown = null;
    $payload = null;

    try {
        $payload = $spy->summary($this->periodA);
    } catch (ValidationException $exception) {
        $thrown = $exception;
    }

    expect($thrown)->toBeInstanceOf(ValidationException::class);
    expect($thrown->errors())->toHaveKey('business_unit_id');
    expect($thrown->errors()['business_unit_id'][0])->toContain('Hubungi Admin');
    expect($payload)->toBeNull();

    // THE PROOF: a claim about something that did NOT happen can only be
    // established by a recorded call count of zero.
    expect($spy->allBusinessUnitsCalls)->toBe(0);

    // Same rule on the listing path, same proof.
    expect(fn () => $spy->listPeriods())->toThrow(ValidationException::class);
    expect($spy->allBusinessUnitsCalls)->toBe(0);
});

// ---------------------------------------------------------------------
// Case 37 — Admin without a mill is 422, never a silent mill
// ---------------------------------------------------------------------
it('case 37 — Admin tanpa business_unit_id pada request: 422 VALIDATION_ERROR', function () {
    $this->actingAs($this->admin);

    storageTankReportRecord($this->stationA, '2026-09-04', 'TK-01', [
        ['calculated_weight_mt' => 100.0],
    ]);

    $thrown = null;
    $payload = null;

    try {
        $payload = $this->service->summary($this->periodA, null);
    } catch (ValidationException $exception) {
        $thrown = $exception;
    }

    expect($thrown)->toBeInstanceOf(ValidationException::class);
    expect($thrown->errors())->toHaveKey('business_unit_id');
    // Incomplete input, not refused access.
    expect($thrown)->not->toBeInstanceOf(AuthorizationException::class);
    expect($payload)->toBeNull();

    // And no mill is picked on the Admin's behalf.
    expect(fn () => $this->service->listPeriods(null))->toThrow(ValidationException::class);
});

// ---------------------------------------------------------------------
// Case 38 — WIDENED 2026-09-25 (screen-139--laporan-storage-tank-mobile)
// ---------------------------------------------------------------------

// This case used to assert the opposite: Operator refused with 403 on all
// four entry points, with a note saying "there is no Operator widening on
// this WEB screen — the mobile Storage Tank report is screen-139, with its
// own endpoints". THAT TURNED OUT NOT TO BE HOW screen-139 WAS BUILT. The
// mobile Storage Tank report reuses THESE four endpoints verbatim rather
// than getting its own — one source of figures for the web report and the
// phone — so on 2026-09-25 they were opened to Operator. The same widening
// SterilizerReportService got for screen-135, CagesTrackReportService for
// screen-136, BoilerRoomReportService for screen-137 and
// ClarificationReportService for screen-138. The people who key the readings
// in are entitled to read them back.
//
// ACCEPTANCE IS THE CHEAP HALF. Asserting only "it no longer throws" would
// leave the expensive half untested, so the decisive assertion is block 2:
// resolveBusinessUnit() must land Operator in the MILL-BOUND branch, where a
// client-supplied business_unit_id is DISCARDED — not in the Admin branch,
// where it is HONOURED. Adding Operator to guardAccess() ALONE would drop it
// through to the Admin branch and produce a service that answers 200 for ANY
// mill an Operator cares to name: a cross-mill leak, not a display defect.
// Those two lines of the widening are one change, never two, and this case
// is what keeps the second one honest.
//
// businessUnitOptions() is deliberately NOT part of the widening and its 403
// is KEPT BELOW VERBATIM: Operator is bound to one mill and has no picker,
// so the list of mills is still Admin-only and the mobile view must never
// call it.
//
// The old "Operator reads nothing" assertion keeps its SPIRIT rather than
// its letter: rows ARE read for Operator now, so what is asserted is that
// every query touching storage_tank_records is bound to the Operator's OWN
// business unit and to no other — proven from the recorded bindings, with
// the query list itself asserted non-empty, because an empty list would make
// every binding assertion below vacuously true.
it('case 38 — Operator DITERIMA pada keempat endpoint tetapi terkunci pada mill-nya sendiri', function () {
    // Mill Beta's figures are large and unmistakable: if any of them
    // surfaced below, they could not be mistaken for a rounding difference.
    storageTankReportRecord($this->stationB, '2026-09-04', 'TK-BETA', [
        ['calculated_weight_mt' => 9000.0, 'ffa_percent' => 99.0],
    ]);
    storageTankReportRecord($this->stationA, '2026-09-04', 'TK-ALPHA', [
        ['calculated_weight_mt' => 100.0, 'ffa_percent' => 4.0],
    ]);

    $otherMillPeriod = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType('storage-tank')
        ->range('2026-09-01', '2026-09-30')
        ->open()
        ->named('Periode September Beta')
        ->create();

    $this->actingAs($this->operatorA);

    $queries = [];

    DB::listen(function ($query) use (&$queries) {
        $queries[] = ['sql' => $query->sql, 'bindings' => $query->bindings];
    });

    // 1. ACCEPTED — not one AuthorizationException on any of the report
    //    paths that used to answer 403.
    $summary = $this->service->summary($this->periodA);
    $periods = $this->service->listPeriods();
    $rows = iterator_to_array($this->service->buildExportRows($this->periodA), false);
    $response = $this->service->export($this->periodA, 'csv', null);

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

    // 5. The old "reads nothing" assertion's SPIRIT: the queries that DO run
    //    are scoped to the Operator's own business unit, every one of them.
    $recordQueries = collect($queries)
        ->filter(fn ($query) => str_contains($query['sql'], 'storage_tank_records'))
        ->values();

    // Without this, every foreach assertion below would pass on an empty
    // list and prove precisely nothing.
    expect($recordQueries)->not->toBeEmpty();

    foreach ($recordQueries as $query) {
        $bindings = array_map(fn ($binding) => (string) $binding, $query['bindings']);

        expect($bindings)->toContain((string) $this->businessUnitA->id);
        expect($bindings)->not->toContain((string) $this->businessUnitB->id);
    }

    // 6. And the figures themselves are BU-A's, never BU-B's 9000,0 / 99,0.
    expect($summary['business_unit']['name'])->toBe('Mill Alpha');
    expect($summary['stock']['opening_mt'])->toBe(100.0);
    expect($summary['stock']['opening_mt'])->not->toBe(9000.0);
    expect($summary['metrics']['ffa_percent']['max'])->toBe(4.0);
    expect($summary['metrics']['ffa_percent']['max'])->not->toBe(99.0);
    expect(array_column($summary['by_tank'], 'storage_tank_id'))->toBe(['TK-ALPHA']);
    expect($rows)->toHaveCount(1);
    // listPeriods() answers with Mill Alpha's period only — Mill Beta's
    // period, created above with the same dates and station type, is absent.
    expect($periods)->toHaveCount(1);
    expect($periods[0]['id'])->toBe((string) $this->periodA->id);
});

// ---------------------------------------------------------------------
// Case 39 — THE NO-ORACLE PROOF, RE-EXPRESSED 2026-09-25 (screen-139)
// ---------------------------------------------------------------------

// This case used to read: "the role guard runs BEFORE findOrFail — an
// Operator naming a nonexistent period gets 403, never 404". Its POINT was
// never the exception ordering for its own sake — it was that A CALLER WHO
// IS REFUSED MUST NOT GET AN EXISTENCE ORACLE. Telling 403 apart from 404
// would have let a role that is not admitted at all enumerate which period
// ids exist.
//
// After the screen-139 widening, Operator IS an admitted caller. For an
// admitted caller a 404 over a genuinely nonexistent id leaks nothing at all
// — it is simply the correct answer, exactly as it already was for
// Supervisor (case 40). Keeping the old assertion would have meant keeping a
// 403 that no longer protects anything, and would have forced the
// implementation to refuse Operator somewhere.
//
// WHAT STILL MATTERS IS THE CROSS-MILL ORACLE, and that is what this case
// now asserts: an Operator of Mill Alpha naming a period belonging to Mill
// Beta gets 403 — not 404 — so the refusal never confirms whether that
// period exists. The mill guard still runs, and it still refuses without
// answering the question. The closing assertion documents the deliberate
// change: a period id that exists for nobody now answers 404 for an
// Operator, and that is intended rather than a regression.
it('case 39 — Operator tidak punya orakel keberadaan lintas mill: periode mill lain 403, bukan 404', function () {
    $otherMillPeriod = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType('storage-tank')
        ->range('2026-09-01', '2026-09-30')
        ->open()
        ->named('Periode September Beta')
        ->create();

    $this->actingAs($this->operatorA);

    // A period that DOES exist, in a mill the caller may not see: refused
    // with 403, and deliberately NOT with 404 — a 404 here would be a
    // statement about the other mill's data.
    expect(fn () => $this->service->authorizePeriod((string) $otherMillPeriod->id))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->authorizePeriod((string) $otherMillPeriod->id))
        ->not->toThrow(ModelNotFoundException::class);
    expect(fn () => $this->service->summary((string) $otherMillPeriod->id))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->summary((string) $otherMillPeriod->id))
        ->not->toThrow(ModelNotFoundException::class);
    expect(fn () => $this->service->export((string) $otherMillPeriod->id, 'csv', null))
        ->toThrow(AuthorizationException::class);

    // The exception TYPE is the proof, so it is captured and inspected
    // rather than merely matched.
    $thrown = null;

    try {
        $this->service->authorizePeriod((string) $otherMillPeriod->id);
    } catch (Throwable $exception) {
        $thrown = $exception;
    }

    expect($thrown)->toBeInstanceOf(AuthorizationException::class);
    expect($thrown)->not->toBeInstanceOf(ModelNotFoundException::class);

    // AND THE DOCUMENTED CHANGE: an id that exists for nobody now answers
    // 404 for an Operator, exactly as it does for Supervisor (case 40).
    // Operator is an admitted caller since screen-139, so there is no
    // refusal left to hide behind and nothing for the 404 to leak.
    $missingId = (string) Str::uuid();

    expect(fn () => $this->service->authorizePeriod($missingId))->toThrow(ModelNotFoundException::class);
    expect(fn () => $this->service->summary($missingId))->toThrow(ModelNotFoundException::class);
});

// ---------------------------------------------------------------------
// Case 40 — an unknown period id for an ENTITLED caller is 404
// ---------------------------------------------------------------------
it('case 40 — period_id tidak ada untuk pengguna berwenang: 404 NOT_FOUND', function () {
    $this->actingAs($this->supervisorA);

    $missingId = (string) Str::uuid();

    $thrown = null;

    try {
        $this->service->summary($missingId);
    } catch (Throwable $exception) {
        $thrown = $exception;
    }

    // The CONTRAST with case 39: same id, different role, different answer.
    expect($thrown)->toBeInstanceOf(ModelNotFoundException::class);
    expect($thrown)->not->toBeInstanceOf(AuthorizationException::class);
});

// ---------------------------------------------------------------------
// Case 41 — no session at all is 401 on every entry point
// ---------------------------------------------------------------------
it('case 41 — permintaan tanpa autentikasi: 401 UNAUTHENTICATED', function () {
    expect(fn () => $this->service->businessUnitOptions())->toThrow(AuthenticationException::class);
    expect(fn () => $this->service->listPeriods())->toThrow(AuthenticationException::class);
    expect(fn () => $this->service->summary($this->periodA))->toThrow(AuthenticationException::class);
    expect(fn () => $this->service->export($this->periodA, 'csv', null))->toThrow(AuthenticationException::class);
    expect(fn () => $this->service->authorizePeriod((string) $this->periodA->id))->toThrow(AuthenticationException::class);
});

// ---------------------------------------------------------------------
// Case 42 — period_id is required on summary and export
// ---------------------------------------------------------------------
it('case 42 — period_id wajib pada summary dan export', function () {
    $this->actingAs($this->supervisorA);

    foreach ([null, ''] as $missing) {
        $thrown = null;

        try {
            $this->service->summary($missing);
        } catch (ValidationException $exception) {
            $thrown = $exception;
        }

        expect($thrown)->toBeInstanceOf(ValidationException::class);
        expect($thrown->errors())->toHaveKey('period_id');
        // Incomplete input — not a 404 for the empty string, and never a
        // silently empty report.
        expect($thrown)->not->toBeInstanceOf(ModelNotFoundException::class);
    }

    $thrownExport = null;

    try {
        $this->service->export(null, 'csv', null);
    } catch (ValidationException $exception) {
        $thrownExport = $exception;
    }

    expect($thrownExport)->toBeInstanceOf(ValidationException::class);
    expect($thrownExport->errors())->toHaveKey('period_id');
});

// ---------------------------------------------------------------------
// Case 43 — the screen only reads: no station row ever changes
// ---------------------------------------------------------------------
it('case 43 — layar hanya membaca: tidak ada data stasiun yang berubah', function () {
    $this->actingAs($this->supervisorA);

    $otherPeriod = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('storage-tank')
        ->range('2026-11-01', '2026-11-30')->open()->named('Periode November')->create();

    storageTankReportRecord($this->stationA, '2026-09-04', 'TK-01', [
        ['time_slot' => '07:00', 'calculated_weight_mt' => 100.0, 'ffa_percent' => 4.0],
        ['time_slot' => '08:00', 'calculated_weight_mt' => 90.0],
    ]);
    storageTankReportRecord($this->stationA, '2026-11-04', 'TK-02', [
        ['time_slot' => '07:00', 'calculated_weight_mt' => 300.0],
    ]);

    $snapshotRecords = StorageTankRecord::query()->orderBy('id')->get()->toArray();
    $snapshotDetails = StorageTankDetail::query()->orderBy('id')->get()->toArray();

    $queries = storageTankReportQueriesDuring(function () use ($otherPeriod) {
        $this->service->summary($this->periodA);
        $this->service->summary($otherPeriod);
        storageTankReportStreamed($this->service->export($this->periodA, 'csv', null));
        storageTankReportStreamed($this->service->export($otherPeriod, 'csv', null));
    });

    expect(StorageTankRecord::query()->orderBy('id')->get()->toArray())->toEqual($snapshotRecords);
    expect(StorageTankDetail::query()->orderBy('id')->get()->toArray())->toEqual($snapshotDetails);

    foreach ($queries as $sql) {
        $lower = strtolower(ltrim($sql));

        expect($lower)->not->toStartWith('insert');
        expect($lower)->not->toStartWith('update');
        expect($lower)->not->toStartWith('delete');
    }
});

// ---------------------------------------------------------------------
// Case 44 — happy path: the whole payload, key by key
// ---------------------------------------------------------------------
it('case 44 — happy path: seluruh kondisi terpenuhi, payload lengkap', function () {
    $this->actingAs($this->supervisorA);

    storageTankReportRecord($this->stationA, '2026-09-01', 'TK-01', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 1000.0, 'ffa_percent' => 4.0, 'average_temperature_c' => 47.0,
            'moisture_content_percent' => 0.2, 'impurities_dirt_percent' => 0.02, 'dobi_index' => 3.0],
        ['time_slot' => '18:00', 'calculated_weight_mt' => 990.0, 'ffa_percent' => 4.2],
    ]);
    storageTankReportRecord($this->stationA, '2026-09-01', 'TK-02', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 500.0, 'ffa_percent' => 4.4, 'average_temperature_c' => 48.0],
    ]);
    storageTankReportRecord($this->stationA, '2026-09-05', 'TK-01', [
        ['time_slot' => '12:00', 'calculated_weight_mt' => 950.0],
    ]);
    storageTankReportRecord($this->stationA, '2026-09-05', 'TK-02', [
        ['time_slot' => '12:00', 'calculated_weight_mt' => 520.0],
    ]);

    $summary = $this->service->summary($this->periodA);

    expect(array_keys($summary))->toBe([
        'period', 'business_unit', 'has_data', 'coverage', 'stock', 'metrics', 'by_tank', 'daily', 'total',
    ]);

    expect(array_keys($summary['period']))
        ->toBe(['id', 'name', 'start_date', 'end_date', 'status', 'business_unit_name']);
    expect(array_keys($summary['business_unit']))->toBe(['id', 'name']);

    expect(array_keys($summary['coverage']))->toBe([
        'filled_slots', 'expected_slots', 'coverage_percent',
        'tank_count', 'slots_per_tank_per_day', 'days_in_period',
    ]);
    expect(array_keys($summary['stock']))->toBe([
        'opening_mt', 'opening_at', 'closing_mt', 'closing_at',
        'movement_mt', 'tanks_with_movement', 'tanks_without_movement',
    ]);

    expect(array_keys($summary['metrics']))->toBe(StorageTankReportService::NUMERIC_METRICS);

    foreach ($summary['metrics'] as $stats) {
        expect(array_keys($stats))->toBe(['min', 'avg', 'max', 'reading_count']);
    }

    foreach ($summary['by_tank'] as $tank) {
        expect(array_keys($tank))->toBe([
            'storage_tank_id', 'reading_count', 'opening_mt', 'opening_at',
            'closing_mt', 'closing_at', 'movement_mt', 'movement_computable',
            'ffa_avg', 'average_temperature_avg',
        ]);
    }

    foreach ($summary['daily'] as $row) {
        expect(array_keys($row))->toBe([
            'date', 'filled_slots', 'stock_total_mt',
            'ffa_avg', 'moisture_avg', 'impurities_avg', 'dobi_avg', 'temperature_avg',
        ]);
    }

    expect(array_keys($summary['total']))->toBe(['days_with_records', 'reading_rows']);

    // And the figures themselves, because a plausible-looking wrong number
    // is this screen's real failure mode.
    expect($summary['by_tank'])->toHaveCount(2);
    expect(storageTankReportByTank($summary, 'TK-01')['opening_mt'])->toBe(1000.0);
    expect(storageTankReportByTank($summary, 'TK-01')['opening_at'])->toBe('2026-09-01 06:00');
    expect(storageTankReportByTank($summary, 'TK-01')['closing_mt'])->toBe(950.0);
    expect(storageTankReportByTank($summary, 'TK-01')['closing_at'])->toBe('2026-09-05 12:00');
    expect(storageTankReportByTank($summary, 'TK-01')['movement_mt'])->toBe(-50.0);
    expect(storageTankReportByTank($summary, 'TK-02')['movement_mt'])->toBe(20.0);

    expect($summary['stock']['opening_mt'])->toBe(1500.0);
    expect($summary['stock']['closing_mt'])->toBe(1470.0);
    expect($summary['stock']['movement_mt'])->toBe(-30.0);
    expect($summary['stock']['opening_at'])->toBe('2026-09-01 06:00');
    expect($summary['stock']['closing_at'])->toBe('2026-09-05 12:00');
    expect($summary['stock']['tanks_with_movement'])->toBe(2);
    expect($summary['stock']['tanks_without_movement'])->toBe(0);

    expect($summary['coverage']['tank_count'])->toBe(2);
    expect($summary['coverage']['days_in_period'])->toBe(30);
    expect($summary['coverage']['expected_slots'])->toBe(2 * 30 * $this->slotsPerDay);
    expect($summary['coverage']['filled_slots'])->toBe(5);

    expect($summary['total']['days_with_records'])->toBe(2);
    expect($summary['total']['reading_rows'])->toBe(5);
    expect($summary['has_data'])->toBeTrue();

    // Each metric over its OWN rows: FFA on 3, average temperature on 2.
    expect($summary['metrics']['ffa_percent']['reading_count'])->toBe(3);
    expect($summary['metrics']['average_temperature_c']['reading_count'])->toBe(2);
    expect($summary['metrics']['average_temperature_c']['avg'])->toBe(47.5);
});
