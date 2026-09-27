<?php

/**
 * LaporanStorageTankTest (Feature/Api) — screen-133--laporan-storage-tank-web /
 * usecase-133--laporan-storage-tank-web (Laporan Periode Storage Tank).
 *
 * Integration tests for the four GET endpoints under
 * /api/storage-tank-reports (App\Http\Controllers\Api\
 * StorageTankReportController), one test per test_scenarios entry, running
 * each scenario's api_test steps IN ORDER and feeding the real response of
 * step N into step N+1 exactly as the `{{stepN.field}}` references
 * prescribe. Exercises the real route -> 'auth:web,sanctum' +
 * 'role:supervisor,mill_management,admin' -> controller ->
 * StorageTankReportService -> Eloquent chain, mirroring
 * tests/Feature/Api/LaporanClarificationTest.php (screen-132).
 *
 * WHAT MAKES THIS SCREEN DIFFERENT FROM THE FOUR STATION REPORTS BEFORE IT:
 * STOCK IS A COMPARISON OF TWO READINGS, NOT AN AGGREGATE. `summary`
 * returns, per tank, the FIRST and the LAST reading whose
 * calculated_weight_mt is non-null — ordered by (record date, detail time
 * slot) — together with the TIMESTAMP of each, and the net movement is the
 * SUM OF THE PER-TANK MOVEMENTS rather than the difference of the combined
 * stocks. A tank with one stock reading reports movement_mt null and
 * movement_computable false, never 0.
 *
 * THE FIXTURES ARE SHAPED SO A WRONG IMPLEMENTATION GIVES A DIFFERENT
 * NUMBER, never the same one by luck:
 *   - the scrambled tank (100,0 / 250,0 / 80,0 written out of order) so
 *     min/max, storage order and chronological order are three answers;
 *   - a tank recorded ONLY at the end of the period so per-tank movement
 *     (+10,0) and combined-stock movement (+410,0) cannot be confused;
 *   - top/middle/bottom 50/60/70 with average_temperature_c 55,0, so a
 *     recomputed average is visibly wrong.
 *
 * TWO GUARDS, TWO SHAPES — asserted separately on purpose:
 *   - business_unit_id from the client is IGNORED for Supervisor / Mill
 *     Management: probing another mill answers 200 with the caller's OWN
 *     data, deliberately NOT 403 (a 403 would confirm the other mill
 *     exists). The two-step shape of that scenario is load-bearing and must
 *     not be collapsed into one request.
 *   - period_id belonging to another mill IS refused with 403 FORBIDDEN by
 *     StorageTankReportService::authorizePeriod().
 *
 * OPERATOR IS ADMITTED ON THREE OF THE FOUR ROUTES SINCE 2026-09-25, AND
 * BOUND TO ITS OWN MILL. screen-139--laporan-storage-tank-mobile reuses
 * these endpoints verbatim rather than shipping its own, so the phone and
 * this web report can never disagree about a figure. Scenario 16 asserts the
 * acceptance by CONTENT, not by status: it sends Mill Beta's
 * business_unit_id on purpose and demands Mill Alpha's tanks and figures
 * back, because the Admin-branch leak would answer 200 too.
 *
 * /business-units/options is the fourth route and is NOT part of the
 * widening: it still answers 403 for Operator, now raised by the SERVICE
 * rather than by EnsureRole, so it carries code = 'FORBIDDEN' through
 * ApiExceptionHandler and scenario 16 asserts that code rather than the bare
 * status. The WEB route /reports/storage-tank was not touched at all —
 * Operator has no web UI, and e2e-web/tests/laporan-storage-tank.spec.ts
 * still proves the widening stops at the API.
 *
 * NOTHING IS FLAGGED OUT OF RANGE, and the absence is asserted BY NAME
 * (scenario 25). Storage Tank has no operational-target master table.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\Station;
use App\Models\StationType;
use App\Models\StorageTankDetail;
use App\Models\StorageTankRecord;
use App\Models\User;
use App\Services\StationReportService;
use App\Services\StorageTankRecordService;
use App\Services\StorageTankReportService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The all-mills spy for the "akun belum terhubung ke mill" scenario. Bound
 * into the container so the CONTROLLER resolves it — "the whole-mill list
 * was never built" is a claim about something that did not happen, and only
 * a recorded call count of zero proves it.
 */
class LaporanStorageTankApiAllBusinessUnitsSpy extends StorageTankReportService
{
    public int $allBusinessUnitsCalls = 0;

    public function allBusinessUnits(): Collection
    {
        $this->allBusinessUnitsCalls++;

        return parent::allBusinessUnits();
    }
}

/**
 * One storage_tank_records header for $tankId on $date, plus one
 * storage_tank_details row per entry of $rows. `time_slot` is filled in from
 * the canonical grid by position unless the entry supplies its own, so
 * UNIQUE(storage_tank_record_id, time_slot) holds without every fixture
 * having to spell the slots out.
 *
 * @param  list<array<string, mixed>>  $rows
 */
function laporanStorageTankRecord(Station $station, string $date, string $tankId, array $rows = [], array $overrides = []): StorageTankRecord
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
 * THE SCRAMBLED FIXTURE: TK-01 reads 100,0 @01 Sep 06:00 -> 250,0 @05 Sep
 * 12:00 -> 80,0 @10 Sep 18:00, with the headers WRITTEN 05 Sep, 10 Sep,
 * 01 Sep. Opening by value would be 80,0; by storage order 250,0; by time
 * (correct) 100,0.
 */
function laporanStorageTankScrambledTank(Station $station, string $tankId = 'TK-01'): void
{
    laporanStorageTankRecord($station, '2026-09-05', $tankId, [
        ['time_slot' => '12:00', 'calculated_weight_mt' => 250.0],
    ]);
    laporanStorageTankRecord($station, '2026-09-10', $tankId, [
        ['time_slot' => '18:00', 'calculated_weight_mt' => 80.0],
    ]);
    laporanStorageTankRecord($station, '2026-09-01', $tankId, [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 100.0],
    ]);
}

/** The by_tank entry for one storage_tank_id inside a decoded payload. */
function laporanStorageTankByTank(array $byTank, string $tankId): ?array
{
    foreach ($byTank as $tank) {
        if ($tank['storage_tank_id'] === $tankId) {
            return $tank;
        }
    }

    return null;
}

/** The body of a StreamedResponse, captured. */
function laporanStorageTankStreamed($response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

/** An already-captured CSV body split into non-empty lines. */
function laporanStorageTankCsvLinesOf(string $body): array
{
    return array_values(array_filter(explode("\n", trim($body))));
}

/**
 * The streamed CSV split into non-empty lines.
 *
 * STREAM ONCE. StreamedResponse::sendContent() marks itself as already
 * streamed, so a SECOND read of the SAME response object yields an empty
 * string — and an assertion on the line count would silently see zero lines
 * instead of the real file. Never call both this and
 * laporanStorageTankStreamed() on one response: capture the body once with
 * laporanStorageTankStreamed() and feed it to laporanStorageTankCsvLinesOf().
 */
function laporanStorageTankCsvLines($response): array
{
    return laporanStorageTankCsvLinesOf(laporanStorageTankStreamed($response));
}

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->storageTank()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->storageTank()->create();

    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    // Admin is bound to no mill at all — hence the mill picker.
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('storage-tank')
        ->range('2026-09-01', '2026-09-30')
        ->named('Periode September Alpha')
        ->open()
        ->create();

    $this->slotsPerDay = count(StorageTankRecordService::canonicalTimeSlots());
});

// =====================================================================
// Scenario 1: "berhasil sebagai Supervisor atau Mill Management"
// =====================================================================
it('berhasil: periods -> summary -> export sebagai Supervisor dan Mill Management, dirantai dari respons sungguhan', function () {
    laporanStorageTankScrambledTank($this->stationA);
    laporanStorageTankRecord($this->stationA, '2026-09-02', 'TK-02', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 400.0, 'ffa_percent' => 4.0,
            'average_temperature_c' => 55.0, 'moisture_content_percent' => 0.2,
            'impurities_dirt_percent' => 0.02, 'dobi_index' => 3.0],
        ['time_slot' => '18:00', 'calculated_weight_mt' => 450.0, 'ffa_percent' => 4.4],
    ]);

    foreach ([$this->supervisor, $this->millManagement] as $user) {
        // Step 1 — GET /periods (no business_unit_id: it comes from the
        // account, and the client is never asked for it).
        $periods = $this->actingAs($user, 'web')->getJson('/api/storage-tank-reports/periods');
        $periods->assertOk();
        $periods->assertJsonStructure([
            'data' => [['id', 'name', 'start_date', 'end_date', 'status', 'station_type', 'station_type_label']],
        ]);

        $periodId = $periods->json('data.0.id');
        expect($periodId)->toBe((string) $this->periodA->id);

        // Step 2 — GET /summary?period_id={{step1.data.0.id}}
        $summary = $this->actingAs($user, 'web')
            ->getJson('/api/storage-tank-reports/summary?'.http_build_query(['period_id' => $periodId]));

        $summary->assertOk();
        $summary->assertJsonStructure([
            'period' => ['id', 'name', 'start_date', 'end_date', 'status', 'business_unit_name'],
            'business_unit' => ['id', 'name'],
            'coverage' => [
                'filled_slots', 'expected_slots', 'coverage_percent',
                'tank_count', 'slots_per_tank_per_day', 'days_in_period',
            ],
            'stock' => [
                'opening_mt', 'opening_at', 'closing_mt', 'closing_at',
                'movement_mt', 'tanks_with_movement', 'tanks_without_movement',
            ],
            'metrics' => [
                'ffa_percent' => ['min', 'avg', 'max', 'reading_count'],
                'moisture_content_percent' => ['min', 'avg', 'max', 'reading_count'],
                'impurities_dirt_percent' => ['min', 'avg', 'max', 'reading_count'],
                'dobi_index' => ['min', 'avg', 'max', 'reading_count'],
                'average_temperature_c' => ['min', 'avg', 'max', 'reading_count'],
            ],
            'by_tank' => [[
                'storage_tank_id', 'reading_count', 'opening_mt', 'opening_at',
                'closing_mt', 'closing_at', 'movement_mt', 'movement_computable',
                'ffa_avg', 'average_temperature_avg',
            ]],
            'daily' => [[
                'date', 'filled_slots', 'stock_total_mt',
                'ffa_avg', 'moisture_avg', 'impurities_avg', 'dobi_avg', 'temperature_avg',
            ]],
            'total' => ['days_with_records', 'reading_rows'],
        ]);

        $summary->assertJsonPath('period.business_unit_name', 'Mill Alpha');

        // Exactly the ten agreed metric keys, in order, from the constant.
        expect(array_keys($summary->json('metrics')))->toBe(StorageTankReportService::NUMERIC_METRICS);

        // STOCK BY TIME, WITH THE TIMESTAMPS OF THE TWO READINGS IT CAME FROM.
        $tk01 = laporanStorageTankByTank($summary->json('by_tank'), 'TK-01');
        expect($tk01['opening_mt'])->toEqual(100.0);
        expect($tk01['opening_at'])->toBe('2026-09-01 06:00');
        expect($tk01['closing_mt'])->toEqual(80.0);
        expect($tk01['closing_at'])->toBe('2026-09-10 18:00');
        expect($tk01['movement_mt'])->toEqual(-20.0);

        // Per-tank movements summed: -20,0 + 50,0 = +30,0.
        expect($summary->json('stock.movement_mt'))->toEqual(30.0);
        $summary->assertJsonPath('stock.tanks_with_movement', 2);
        $summary->assertJsonPath('stock.tanks_without_movement', 0);

        // Average temperature READ, never recomputed from 50/60/70.
        expect($summary->json('metrics.average_temperature_c.avg'))->toEqual(55.0);

        $summary->assertJsonPath('coverage.filled_slots', 5);
        $summary->assertJsonPath('coverage.expected_slots', 2 * 30 * $this->slotsPerDay);
        $summary->assertJsonCount(4, 'daily');
        $summary->assertJsonCount(2, 'by_tank');
        $summary->assertJsonPath('total.reading_rows', 5);

        // Step 3 — GET /export?period_id={{step1.data.0.id}}, and nothing
        // about the station data may change because of it.
        $recordsBefore = StorageTankRecord::count();
        $detailsBefore = StorageTankDetail::count();

        $export = $this->actingAs($user, 'web')
            ->get('/api/storage-tank-reports/export?'.http_build_query(['period_id' => $periodId]));

        $export->assertOk();
        expect($export->headers->get('Content-Type'))->toContain('text/csv');

        $lines = laporanStorageTankCsvLines($export->baseResponse);

        // Header + one line per TIME SLOT.
        expect($lines)->toHaveCount(6);
        expect(str_getcsv($lines[0], ',', '"', '\\'))->toBe(StorageTankReportService::EXPORT_HEADER);

        expect(StorageTankRecord::count())->toBe($recordsBefore);
        expect(StorageTankDetail::count())->toBe($detailsBefore);
    }
});

// =====================================================================
// Scenario 2: "berhasil sebagai Admin"
// =====================================================================
it('admin: options -> periods of the chosen mill -> summary -> export, dihitung hanya dari mill itu', function () {
    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('storage-tank')
        ->range('2026-09-01', '2026-09-30')->named('Periode September Beta')->open()->create();

    laporanStorageTankRecord($this->stationA, '2026-09-10', 'TK-01', [
        ['calculated_weight_mt' => 100.0, 'ffa_percent' => 4.0],
    ]);
    laporanStorageTankRecord($this->stationB, '2026-09-10', 'TK-BETA', [
        ['calculated_weight_mt' => 900.0, 'ffa_percent' => 99.0],
    ]);

    // Step 1 — GET /business-units/options
    $options = $this->actingAs($this->admin, 'web')->getJson('/api/storage-tank-reports/business-units/options');
    $options->assertOk();
    $options->assertJsonStructure(['data' => [['id', 'name']]]);

    $millId = collect($options->json('data'))->firstWhere('name', 'Mill Alpha')['id'];
    expect($millId)->toBe((string) $this->businessUnitA->id);

    // Step 2 — GET /periods?business_unit_id={{step1.data.0.id}}
    $periods = $this->actingAs($this->admin, 'web')
        ->getJson('/api/storage-tank-reports/periods?'.http_build_query(['business_unit_id' => $millId]));
    $periods->assertOk();

    $periodIds = collect($periods->json('data'))->pluck('id')->all();
    expect($periodIds)->toContain((string) $this->periodA->id);
    expect($periodIds)->not->toContain((string) $periodB->id);

    // Step 3 — GET /summary?business_unit_id=..&period_id={{step2.data.0.id}}
    $summary = $this->actingAs($this->admin, 'web')->getJson('/api/storage-tank-reports/summary?'.http_build_query([
        'business_unit_id' => $millId,
        'period_id' => $periods->json('data.0.id'),
    ]));

    $summary->assertOk();
    $summary->assertJsonPath('period.business_unit_name', 'Mill Alpha');
    $summary->assertJsonPath('business_unit.id', $millId);
    expect($summary->json('stock.opening_mt'))->toEqual(100.0);
    // Mill Beta's 900,0 / 99,0 are unmistakable if they ever surface.
    expect($summary->json('metrics.calculated_weight_mt.max'))->not->toEqual(900.0);
    expect($summary->json('metrics.ffa_percent.max'))->not->toEqual(99.0);
    expect(array_column($summary->json('by_tank'), 'storage_tank_id'))->toBe(['TK-01']);

    // Step 4 — GET /export, identical blocks for Admin as for anyone else.
    $export = $this->actingAs($this->admin, 'web')->get('/api/storage-tank-reports/export?'.http_build_query([
        'business_unit_id' => $millId,
        'period_id' => $periods->json('data.0.id'),
    ]));

    $export->assertOk();

    $body = laporanStorageTankStreamed($export->baseResponse);
    expect($body)->toContain('2026-09-10');
    expect($body)->toContain('TK-01');
    expect($body)->not->toContain('TK-BETA');
});

// =====================================================================
// Scenario 3: "Admin memilih mill lebih dulu"
// =====================================================================
it('admin tanpa mill: summary menjawab 422 VALIDATION_ERROR pada business_unit_id dan tidak mengembalikan satu angka pun', function () {
    laporanStorageTankRecord($this->stationA, '2026-09-10', 'TK-01', [['calculated_weight_mt' => 100.0]]);

    $summary = $this->actingAs($this->admin, 'web')
        ->getJson('/api/storage-tank-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertStatus(422);
    $summary->assertJsonPath('code', 'VALIDATION_ERROR');
    $summary->assertJsonStructure(['errors' => ['business_unit_id']]);

    // Not one figure, and no mill silently picked on the Admin's behalf.
    $summary->assertJsonMissingPath('stock');
    $summary->assertJsonMissingPath('metrics');
    $summary->assertJsonMissingPath('by_tank');
    $summary->assertJsonMissingPath('coverage');

    // The same rule on the listing and export paths.
    $this->actingAs($this->admin, 'web')->getJson('/api/storage-tank-reports/periods')
        ->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
    $this->actingAs($this->admin, 'web')
        ->getJson('/api/storage-tank-reports/export?'.http_build_query(['period_id' => $this->periodA->id]))
        ->assertStatus(422);
});

// =====================================================================
// Scenario 4: "mill belum punya periode"
// =====================================================================
it('mill tanpa periode Storage Tank: /periods menjawab 200 dengan data kosong, bukan 404', function () {
    $supervisorB = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitB)->create();

    // Mill Beta has a period, but for another station type only.
    Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('sterilizer')
        ->range('2026-09-01', '2026-09-30')->named('Periode Sterilizer Beta')->open()->create();

    $periods = $this->actingAs($supervisorB, 'web')->getJson('/api/storage-tank-reports/periods');

    $periods->assertOk();
    $periods->assertExactJson(['data' => []]);
});

// =====================================================================
// Scenario 5: "periode tanpa data"
// =====================================================================
it('periode tanpa data: 200 dengan seluruh angka null, bukan nol', function () {
    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/storage-tank-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();
    $summary->assertJsonPath('has_data', false);
    $summary->assertJsonPath('stock.opening_mt', null);
    $summary->assertJsonPath('stock.closing_mt', null);
    $summary->assertJsonPath('stock.movement_mt', null);
    $summary->assertJsonPath('stock.opening_at', null);
    $summary->assertJsonPath('stock.closing_at', null);

    // null, and NEVER 0 — "measured and it was zero" is a different answer
    // from "nobody ever measured it".
    //
    // toBe, NOT toEqual: toEqual compares loosely and `null == 0.0` is true
    // in PHP, so `->not->toEqual(0.0)` on a null can never pass, whatever
    // the implementation does. The assertJsonPath(..., null) calls directly
    // above already pin the value to null exactly, so strict identity here
    // loses nothing — it only makes the "never the number 0.0" half of the
    // claim expressible. Do not revert this to toEqual.
    expect($summary->json('stock.opening_mt'))->not->toBe(0.0);
    expect($summary->json('stock.movement_mt'))->not->toBe(0.0);

    foreach (StorageTankReportService::NUMERIC_METRICS as $metric) {
        $summary->assertJsonPath("metrics.{$metric}.avg", null);
        $summary->assertJsonPath("metrics.{$metric}.reading_count", 0);
    }

    $summary->assertJsonPath('by_tank', []);
    $summary->assertJsonPath('daily', []);
    $summary->assertJsonPath('total.days_with_records', 0);
    $summary->assertJsonPath('total.reading_rows', 0);
    $summary->assertJsonPath('coverage.filled_slots', 0);
    $summary->assertJsonPath('coverage.tank_count', 0);
});

// =====================================================================
// Scenario 6: "tangki hanya punya satu pembacaan stok"
// =====================================================================
it('tangki berpembacaan tunggal: movement null dan movement_computable false, dan tidak menyumbang ke total', function () {
    laporanStorageTankRecord($this->stationA, '2026-09-01', 'TK-01', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 100.0],
    ]);
    laporanStorageTankRecord($this->stationA, '2026-09-10', 'TK-01', [
        ['time_slot' => '18:00', 'calculated_weight_mt' => 130.0],
    ]);
    laporanStorageTankRecord($this->stationA, '2026-09-10', 'TK-03', [
        ['time_slot' => '12:00', 'calculated_weight_mt' => 500.0],
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/storage-tank-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();

    $tk03 = laporanStorageTankByTank($summary->json('by_tank'), 'TK-03');

    expect($tk03['movement_mt'])->toBeNull();
    // toBe, NOT toEqual — `null == 0.0` is true under toEqual's loose
    // comparison, which would make this assertion unpassable. toBeNull()
    // above already pins the exact value; this line adds "and never the
    // number 0.0". Do not revert to toEqual.
    expect($tk03['movement_mt'])->not->toBe(0.0);
    expect($tk03['movement_computable'])->toBeFalse();
    // Opening and closing are the SAME row, at the same instant.
    expect($tk03['opening_mt'])->toEqual(500.0);
    expect($tk03['closing_mt'])->toEqual(500.0);
    expect($tk03['opening_at'])->toBe('2026-09-10 12:00');
    expect($tk03['closing_at'])->toBe('2026-09-10 12:00');

    expect($summary->json('stock.movement_mt'))->toEqual(30.0);
    expect($summary->json('stock.movement_mt'))->not->toEqual(530.0);
    $summary->assertJsonPath('stock.tanks_without_movement', 1);
    $summary->assertJsonPath('stock.tanks_with_movement', 1);
});

// =====================================================================
// Scenario 7: "pembacaan paling awal tidak mencatat stok"
// =====================================================================
it('pembacaan paling awal tanpa stok: opening dari pembacaan terisi berikutnya, dengan waktunya', function () {
    laporanStorageTankRecord($this->stationA, '2026-09-01', 'TK-04', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => null, 'ffa_percent' => 3.2],
    ]);
    laporanStorageTankRecord($this->stationA, '2026-09-03', 'TK-04', [
        ['time_slot' => '12:00', 'calculated_weight_mt' => 150.0],
    ]);
    laporanStorageTankRecord($this->stationA, '2026-09-09', 'TK-04', [
        ['time_slot' => '18:00', 'calculated_weight_mt' => 140.0],
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/storage-tank-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();

    $tk04 = laporanStorageTankByTank($summary->json('by_tank'), 'TK-04');

    expect($tk04['opening_mt'])->toEqual(150.0);
    expect($tk04['opening_mt'])->not->toEqual(0.0);
    // The SECOND reading's instant — the two days before it went unrecorded,
    // and that is exactly what this timestamp exists to reveal.
    expect($tk04['opening_at'])->toBe('2026-09-03 12:00');
    expect($tk04['opening_at'])->not->toBe('2026-09-01 06:00');
    expect($tk04['movement_mt'])->toEqual(-10.0);

    // The stock-less first row is still a reading: it counts for FFA.
    $summary->assertJsonPath('metrics.ffa_percent.reading_count', 1);
    expect($summary->json('stock.opening_at'))->toBe('2026-09-03 12:00');
    expect($summary->json('period.start_date'))->toBe('2026-09-01');
});

// =====================================================================
// Scenario 8: "tangki tanpa satu pun pembacaan stok"
// =====================================================================
it('tangki tanpa pembacaan stok sama sekali: tetap muncul pada by_tank dengan nilai null', function () {
    laporanStorageTankRecord($this->stationA, '2026-09-04', 'TK-06', [
        ['ffa_percent' => 4.0, 'calculated_weight_mt' => null],
        ['ffa_percent' => 4.4, 'calculated_weight_mt' => null],
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/storage-tank-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();

    $tk06 = laporanStorageTankByTank($summary->json('by_tank'), 'TK-06');

    // NOT dropped — dropping it would hide exactly the tank nobody measured.
    expect($tk06)->not->toBeNull();
    expect($tk06['reading_count'])->toBe(2);
    expect($tk06['opening_mt'])->toBeNull();
    expect($tk06['closing_mt'])->toBeNull();
    expect($tk06['opening_at'])->toBeNull();
    expect($tk06['closing_at'])->toBeNull();
    expect($tk06['movement_mt'])->toBeNull();
    expect($tk06['movement_computable'])->toBeFalse();
    expect($tk06['ffa_avg'])->toEqual(4.2);

    // The invariant that keeps the movement card honest.
    expect($summary->json('stock.tanks_with_movement') + $summary->json('stock.tanks_without_movement'))
        ->toBe(count($summary->json('by_tank')));
});

// =====================================================================
// Scenario 9: "jumlah tangki berbeda antara awal dan akhir periode"
// =====================================================================
it('jumlah tangki berbeda di kedua ujung: pergerakan tetap dijumlahkan per tangki (+10,0), bukan +410,0', function () {
    $period = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('storage-tank')
        ->range('2026-09-01', '2026-09-10')->named('Periode Sepuluh Hari')->open()->create();

    laporanStorageTankRecord($this->stationA, '2026-09-01', 'TK-01', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 100.0],
    ]);
    laporanStorageTankRecord($this->stationA, '2026-09-10', 'TK-01', [
        ['time_slot' => '18:00', 'calculated_weight_mt' => 90.0],
    ]);
    // Recorded ONLY at the end of the period — the tank that makes the two
    // methods disagree.
    laporanStorageTankRecord($this->stationA, '2026-09-09', 'TK-02', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 400.0],
    ]);
    laporanStorageTankRecord($this->stationA, '2026-09-10', 'TK-02', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 420.0],
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/storage-tank-reports/summary?'.http_build_query(['period_id' => $period->id]));

    $summary->assertOk();

    expect($summary->json('stock.movement_mt'))->toEqual(10.0);
    expect($summary->json('stock.movement_mt'))->not->toEqual(410.0);

    // It IS the column sum of the per-tank table beneath it.
    $computable = array_filter($summary->json('by_tank'), fn ($tank) => $tank['movement_computable']);
    expect(round(array_sum(array_column($computable, 'movement_mt')), 2))
        ->toEqual($summary->json('stock.movement_mt'));

    // TK-02 is visibly a tank that only appears at the end of the period.
    $tk02 = laporanStorageTankByTank($summary->json('by_tank'), 'TK-02');
    expect($tk02['opening_at'])->toBe('2026-09-09 06:00');
    expect($tk02['opening_at'])->not->toBe('2026-09-01 06:00');
});

// =====================================================================
// Scenario 10: "pergerakan bernilai negatif"
// =====================================================================
it('pergerakan negatif: dikembalikan apa adanya, tanpa abs() dan tanpa penanda apa pun', function () {
    laporanStorageTankRecord($this->stationA, '2026-09-02', 'TK-05', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 500.0],
    ]);
    laporanStorageTankRecord($this->stationA, '2026-09-08', 'TK-05', [
        ['time_slot' => '18:00', 'calculated_weight_mt' => 320.0],
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/storage-tank-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();

    $tk05 = laporanStorageTankByTank($summary->json('by_tank'), 'TK-05');
    expect($tk05['movement_mt'])->toEqual(-180.0);
    expect($tk05['movement_mt'])->not->toEqual(180.0);
    expect($tk05['movement_mt'])->not->toEqual(0.0);
    expect($tk05['movement_computable'])->toBeTrue();
    expect($summary->json('stock.movement_mt'))->toEqual(-180.0);

    // Oil leaving the tank is the ordinary case, not an alarm.
    $json = strtolower($summary->getContent());
    foreach (['warning', 'severity', 'alert', 'is_danger'] as $flag) {
        expect($json)->not->toContain($flag);
    }
});

// =====================================================================
// Scenario 11: "sebuah metrik mutu tidak pernah diisi"
// =====================================================================
it('metrik yang tidak pernah diisi: null dengan reading_count 0, sementara metrik lain tetap normal', function () {
    laporanStorageTankRecord($this->stationA, '2026-09-04', 'TK-01', [
        ['ffa_percent' => 3.0, 'impurities_dirt_percent' => null],
        ['ffa_percent' => 4.0, 'impurities_dirt_percent' => null],
        ['ffa_percent' => 5.0, 'impurities_dirt_percent' => null],
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/storage-tank-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();
    $summary->assertJsonPath('metrics.impurities_dirt_percent.min', null);
    $summary->assertJsonPath('metrics.impurities_dirt_percent.avg', null);
    $summary->assertJsonPath('metrics.impurities_dirt_percent.max', null);
    $summary->assertJsonPath('metrics.impurities_dirt_percent.reading_count', 0);
    // toBe, NOT toEqual — `null == 0.0` is true loosely, so toEqual could
    // never pass here. assertJsonPath(..., null) above pins the value; this
    // adds "an unmeasured metric is never reported as 0.0". Keep as toBe.
    expect($summary->json('metrics.impurities_dirt_percent.avg'))->not->toBe(0.0);

    // Untouched neighbour, with its OWN denominator.
    $summary->assertJsonPath('metrics.ffa_percent.reading_count', 3);
    expect($summary->json('metrics.ffa_percent.avg'))->toEqual(4.0);
});

// =====================================================================
// Scenario 12: "kolom suhu rata-rata kosong"
// =====================================================================
it('kolom suhu rata-rata kosong: tidak tersedia dengan reading_count 0, dan tidak diturunkan dari tiga suhu', function () {
    laporanStorageTankRecord($this->stationA, '2026-09-04', 'TK-01', [
        [
            'oil_temperature_top_c' => 50.0,
            'oil_temperature_middle_c' => 60.0,
            'oil_temperature_bottom_c' => 70.0,
            'average_temperature_c' => null,
        ],
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/storage-tank-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();
    $summary->assertJsonPath('metrics.average_temperature_c.avg', null);
    $summary->assertJsonPath('metrics.average_temperature_c.min', null);
    $summary->assertJsonPath('metrics.average_temperature_c.max', null);
    $summary->assertJsonPath('metrics.average_temperature_c.reading_count', 0);
    // The arithmetic mean of 50/60/70 — the answer a recomputing
    // implementation would give.
    expect($summary->json('metrics.average_temperature_c.avg'))->not->toEqual(60.0);

    // The three positional temperatures stay three separate metrics.
    expect($summary->json('metrics.oil_temperature_middle_c.avg'))->toEqual(60.0);

    foreach ($summary->json('by_tank') as $tank) {
        expect($tank['average_temperature_avg'])->toBeNull();
    }

    foreach ($summary->json('daily') as $day) {
        expect($day['temperature_avg'])->toBeNull();
    }
});

// =====================================================================
// Scenario 13: "pencatatan sangat tidak lengkap"
// =====================================================================
it('pencatatan sangat tidak lengkap: 3 dari 240 slot (1,25 %) dan angka utama tetap dihitung', function () {
    $period = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('storage-tank')
        ->range('2026-09-01', '2026-09-05')->named('Periode Tipis')->open()->create();

    laporanStorageTankRecord($this->stationA, '2026-09-01', 'TK-01', [
        ['time_slot' => '07:00', 'calculated_weight_mt' => 100.0, 'ffa_percent' => 4.0],
        ['time_slot' => '08:00', 'calculated_weight_mt' => 90.0],
    ]);
    laporanStorageTankRecord($this->stationA, '2026-09-03', 'TK-02', [
        ['time_slot' => '07:00', 'calculated_weight_mt' => 300.0],
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/storage-tank-reports/summary?'.http_build_query(['period_id' => $period->id]));

    $summary->assertOk();
    $summary->assertJsonPath('coverage.filled_slots', 3);
    $summary->assertJsonPath('coverage.expected_slots', 2 * 5 * $this->slotsPerDay);
    $summary->assertJsonPath('coverage.tank_count', 2);
    $summary->assertJsonPath('coverage.days_in_period', 5);
    // Two decimals, not one: 1,25 % and 1,3 % are different answers about
    // how badly a period was recorded.
    expect($summary->json('coverage.coverage_percent'))->toEqual(1.25);

    // Low coverage is reported, never a refusal: the figures are still there.
    expect($summary->json('stock.opening_mt'))->toEqual(400.0);
    expect($summary->json('stock.movement_mt'))->toEqual(-10.0);
    expect($summary->json('metrics.ffa_percent.avg'))->toEqual(4.0);
    $summary->assertJsonPath('has_data', true);
});

// =====================================================================
// Scenario 14: "akun belum terhubung ke mill" (with the SPY)
// =====================================================================
it('akun tanpa mill: 422 VALIDATION_ERROR dan daftar seluruh mill TIDAK PERNAH disusun', function () {
    $noMillSupervisor = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    // The spy replaces the service the CONTROLLER resolves, so "the list was
    // never built" is proven by a recorded call count of zero rather than
    // inferred from the status code.
    $spy = new LaporanStorageTankApiAllBusinessUnitsSpy;
    $this->app->instance(StorageTankReportService::class, $spy);

    $summary = $this->actingAs($noMillSupervisor, 'web')
        ->getJson('/api/storage-tank-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertStatus(422);
    $summary->assertJsonPath('code', 'VALIDATION_ERROR');
    $summary->assertJsonStructure(['errors' => ['business_unit_id']]);
    expect($summary->json('errors.business_unit_id.0'))->toContain('Hubungi Admin');
    $summary->assertJsonMissingPath('stock');
    $summary->assertJsonMissingPath('metrics');

    $periods = $this->actingAs($noMillSupervisor, 'web')->getJson('/api/storage-tank-reports/periods');
    $periods->assertStatus(422);
    $periods->assertJsonPath('code', 'VALIDATION_ERROR');

    // THE DECISIVE ASSERTION: falling back to "every mill" would turn one
    // broken master-data row into a cross-mill leak.
    expect($spy->allBusinessUnitsCalls)->toBe(0);
});

// =====================================================================
// Scenario 15: "mencoba melihat mill lain" (2 steps, two different guards)
// =====================================================================
it('mill lain: business_unit_id klien diabaikan (200, data sendiri), tetapi period_id mill lain 403', function () {
    laporanStorageTankRecord($this->stationA, '2026-09-10', 'TK-01', [
        ['calculated_weight_mt' => 100.0, 'ffa_percent' => 4.0],
    ]);
    laporanStorageTankRecord($this->stationB, '2026-09-10', 'TK-BETA', [
        ['calculated_weight_mt' => 900.0, 'ffa_percent' => 99.0],
    ]);

    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('storage-tank')
        ->range('2026-09-01', '2026-09-30')->named('Periode September Beta')->open()->create();

    // Step 1 — naming another mill with an OWN period: 200 with own data.
    // Deliberately not 403 — a 403 would confirm Mill Beta exists, and there
    // is no access attempt to refuse because the parameter is never used.
    $summary = $this->actingAs($this->supervisor, 'web')->getJson('/api/storage-tank-reports/summary?'.http_build_query([
        'business_unit_id' => $this->businessUnitB->id,
        'period_id' => $this->periodA->id,
    ]));

    $summary->assertOk();
    $summary->assertJsonPath('period.business_unit_name', 'Mill Alpha');
    $summary->assertJsonPath('business_unit.id', (string) $this->businessUnitA->id);
    expect($summary->json('stock.opening_mt'))->toEqual(100.0);
    expect($summary->json('metrics.ffa_percent.max'))->not->toEqual(99.0);
    expect(array_column($summary->json('by_tank'), 'storage_tank_id'))->toBe(['TK-01']);

    // Step 2 — another mill's PERIOD ID is a hard 403: a concrete handle to
    // another mill's data.
    $forbidden = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/storage-tank-reports/summary?'.http_build_query(['period_id' => $periodB->id]));
    $forbidden->assertStatus(403);
    $forbidden->assertJsonPath('code', 'FORBIDDEN');
    $forbidden->assertJsonMissingPath('stock');
    $forbidden->assertJsonMissingPath('by_tank');

    // And the export over the same period is refused identically.
    $export = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/storage-tank-reports/export?'.http_build_query(['period_id' => $periodB->id]));
    $export->assertStatus(403);
    $export->assertJsonPath('code', 'FORBIDDEN');
});

// =====================================================================
// Scenario 16: "Operator membuka laporan" (screen-139, mobile)
//
// WIDENED 2026-09-25. This scenario used to be "Operator mencoba membuka
// layar web ini" and asserted 403 on all four endpoints, on the assumption
// that the mobile Storage Tank report would ship its own endpoints. IT DID
// NOT: screen-139 reuses these four verbatim — one source of figures for the
// web report and the phone — so the routes now admit Operator.
//
// It also carried a second intent, "403 bukan 404 untuk period_id yang tidak
// ada". Both intents are preserved below, but the second one is
// re-expressed: for an ADMITTED caller a 404 over a genuinely unknown id
// leaks nothing, so what still matters — and what step 4 asserts — is the
// CROSS-MILL oracle: another mill's period is 403, never 404.
//
// THE STATUS CODES ARE THE CHEAP HALF. A service that admitted Operator in
// guardAccess() only — leaving it to fall through resolveBusinessUnit() into
// the unbound ADMIN branch, where the client's business_unit_id is HONOURED
// — would answer 200 on every step below and pass a status-only test while
// serving any mill an Operator cares to name. So every step here asserts the
// CONTENT: Mill Beta's id is sent deliberately, and Mill Alpha's figures are
// what must come back.
// =====================================================================
it('operator: 200 pada periods/summary/export terkunci pada mill sendiri, 403 pada pemilih mill, dan 401 tanpa sesi', function () {
    // The guest case is run FIRST, on purpose: actingAs() persists for the
    // rest of the test case, so a guest request made after it would silently
    // be an authenticated one.
    $this->getJson('/api/storage-tank-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]))
        ->assertStatus(401);

    // Two mills, told apart by TANK ID as well as by figures: the assertions
    // below have to be able to say "this is Mill Alpha's data" out of the
    // payload itself, not merely "the status was 200".
    laporanStorageTankRecord($this->stationA, '2026-09-04', 'STG-ALPHA', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 100.0, 'ffa_percent' => 4.0],
        ['time_slot' => '18:00', 'calculated_weight_mt' => 140.0, 'ffa_percent' => 4.2],
    ]);
    laporanStorageTankRecord($this->stationB, '2026-09-04', 'STG-BETA', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 9000.0, 'ffa_percent' => 99.0],
    ]);

    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('storage-tank')
        ->range('2026-09-01', '2026-09-30')->named('Periode September Beta')->open()->create();

    // Step 1 — GET /periods: 200, and the list is the Operator's own mill's
    // periods only. No business_unit_id is sent: it comes from the account.
    $periods = $this->actingAs($this->operator, 'web')->getJson('/api/storage-tank-reports/periods');
    $periods->assertOk();
    $periods->assertJsonStructure([
        'data' => [['id', 'name', 'start_date', 'end_date', 'status', 'station_type', 'station_type_label']],
    ]);

    $periodIds = array_column($periods->json('data'), 'id');
    expect($periodIds)->toContain((string) $this->periodA->id);
    expect($periodIds)->not->toContain((string) $periodB->id);

    // Step 2 — GET /summary while NAMING ANOTHER MILL: 200 carrying the
    // caller's OWN data, deliberately not 403 (a 403 would confirm Mill Beta
    // exists, and there is no access attempt to refuse because the parameter
    // is never used for a mill-bound role).
    //
    // THIS IS THE ASSERTION THAT LOCKS resolveBusinessUnit(). The Admin-branch
    // leak answers 200 here too — only the CONTENT tells the two apart, so
    // the discriminating figures are asserted one by one: 9000,0 MT and
    // 99,0 % FFA exist ONLY in Mill Beta.
    $summary = $this->actingAs($this->operator, 'web')->getJson('/api/storage-tank-reports/summary?'.http_build_query([
        'business_unit_id' => $this->businessUnitB->id,
        'period_id' => $this->periodA->id,
    ]));

    $summary->assertOk();
    $summary->assertJsonPath('period.business_unit_name', 'Mill Alpha');
    $summary->assertJsonPath('business_unit.name', 'Mill Alpha');
    $summary->assertJsonPath('business_unit.id', (string) $this->businessUnitA->id);

    expect(array_column($summary->json('by_tank'), 'storage_tank_id'))->toBe(['STG-ALPHA']);
    expect(array_column($summary->json('by_tank'), 'storage_tank_id'))->not->toContain('STG-BETA');
    expect($summary->json('stock.opening_mt'))->toEqual(100.0);
    expect($summary->json('stock.closing_mt'))->toEqual(140.0);
    // Not the combined figure either — 9100,0 would be both mills summed.
    expect($summary->json('stock.opening_mt'))->not->toEqual(9100.0);
    expect($summary->json('metrics.ffa_percent.max'))->toEqual(4.2);
    expect($summary->json('metrics.ffa_percent.max'))->not->toEqual(99.0);
    expect($summary->json('metrics.calculated_weight_mt.max'))->toEqual(140.0);
    expect($summary->json('metrics.calculated_weight_mt.max'))->not->toEqual(9000.0);
    // The blunt instrument, on the whole payload: Mill Beta's NAME must not
    // appear anywhere in it, under any key.
    expect(json_encode($summary->json()))->not->toContain('Mill Beta');
    expect(json_encode($summary->json()))->not->toContain('STG-BETA');

    // Step 3 — GET /export: 200, and the streamed CSV carries the Operator's
    // own mill's rows, not the other mill's.
    $export = $this->actingAs($this->operator, 'web')
        ->get('/api/storage-tank-reports/export?'.http_build_query([
            'period_id' => $this->periodA->id,
            'format' => 'csv',
        ]));

    $export->assertOk();

    $csv = laporanStorageTankStreamed($export->baseResponse);
    expect($csv)->toContain('STG-ALPHA');
    expect($csv)->not->toContain('STG-BETA');

    // Step 4 — THE RE-EXPRESSED "403 BUKAN 404". Another mill's period id is
    // a concrete handle on another mill's data: refused outright with 403,
    // and deliberately NOT with 404, so the refusal never confirms whether
    // that period exists. authorizePeriod() needed no change for this
    // widening — only Admin is unbound there.
    $forbidden = $this->actingAs($this->operator, 'web')
        ->getJson('/api/storage-tank-reports/summary?'.http_build_query(['period_id' => $periodB->id]));
    $forbidden->assertStatus(403);
    expect($forbidden->getStatusCode())->not->toBe(404);
    $forbidden->assertJsonPath('code', 'FORBIDDEN');
    $forbidden->assertJsonMissingPath('stock');
    $forbidden->assertJsonMissingPath('metrics');

    $forbiddenExport = $this->actingAs($this->operator, 'web')
        ->getJson('/api/storage-tank-reports/export?'.http_build_query(['period_id' => $periodB->id]));
    $forbiddenExport->assertStatus(403);
    $forbiddenExport->assertJsonPath('code', 'FORBIDDEN');

    // AND THE DOCUMENTED CHANGE: an id that exists for nobody now answers
    // 404 for an Operator, exactly as it does for an entitled caller. There
    // is no refusal left to hide behind and nothing for the 404 to leak.
    $this->actingAs($this->operator, 'web')
        ->getJson('/api/storage-tank-reports/summary?'.http_build_query(['period_id' => (string) Str::uuid()]))
        ->assertStatus(404);

    // Step 5 — an Operator whose account has no mill FAILS CLOSED with 422,
    // exactly like the other mill-bound roles: never the all-mills list.
    $noMillOperator = User::factory()->role(UserRole::Operator)->create(['business_unit_id' => null]);

    $noMillPeriods = $this->actingAs($noMillOperator, 'web')->getJson('/api/storage-tank-reports/periods');
    $noMillPeriods->assertStatus(422);
    $noMillPeriods->assertJsonPath('code', 'VALIDATION_ERROR');
    $noMillPeriods->assertJsonStructure(['errors' => ['business_unit_id']]);
    expect($noMillPeriods->json('errors.business_unit_id.0'))->toContain('Hubungi Admin');
    $noMillPeriods->assertJsonMissingPath('data');

    // Step 6 — the mill picker STAYS 403 for Operator. It is the ADMIN
    // picker; an Operator bound to its own mill has no use for the list of
    // every mill, and handing it over would be the very leak the widening
    // avoided. Raised by the SERVICE now rather than by EnsureRole, so it
    // carries code = 'FORBIDDEN' — asserting the status alone would no
    // longer distinguish the two layers.
    $picker = $this->actingAs($this->operator, 'web')
        ->getJson('/api/storage-tank-reports/business-units/options');
    $picker->assertStatus(403);
    $picker->assertJsonPath('code', 'FORBIDDEN');
    $picker->assertJsonMissingPath('data');
    expect(json_encode($picker->json()))->not->toContain('Mill Beta');

    // THE CONTRAST KEPT FROM THE OLD CASE: the same missing id, for the
    // other entitled caller, is a 404 as well.
    $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/storage-tank-reports/summary?'.http_build_query(['period_id' => (string) Str::uuid()]))
        ->assertStatus(404);
});

// =====================================================================
// Scenario 17: "periode tertutup" (2 steps)
// =====================================================================
it('periode tertutup: summary lengkap dengan status closed, dan ekspor tetap berjalan', function () {
    $closed = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('storage-tank')
        ->range('2026-11-01', '2026-11-30')->named('Periode November Tertutup')->closed()->create();

    laporanStorageTankRecord($this->stationA, '2026-11-04', 'TK-01', [
        ['time_slot' => '07:00', 'calculated_weight_mt' => 100.0, 'ffa_percent' => 4.0],
        ['time_slot' => '08:00', 'calculated_weight_mt' => 90.0, 'ffa_percent' => 4.4],
    ]);

    // Step 1 — the period lock governs writing data, not reading a report.
    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/storage-tank-reports/summary?'.http_build_query(['period_id' => $closed->id]));

    $summary->assertOk();
    $summary->assertJsonPath('period.status', 'closed');
    expect($summary->json('stock.opening_mt'))->toEqual(100.0);
    expect($summary->json('stock.movement_mt'))->toEqual(-10.0);
    $summary->assertJsonPath('metrics.ffa_percent.reading_count', 2);

    // Step 2 — /export?period_id={{step1.period.id}} is not blocked either.
    $export = $this->actingAs($this->supervisor, 'web')
        ->get('/api/storage-tank-reports/export?'.http_build_query(['period_id' => $summary->json('period.id')]));

    $export->assertOk();
    expect(laporanStorageTankCsvLines($export->baseResponse))->toHaveCount(3);
});

// =====================================================================
// Scenario 18: "rekap harian panjang"
// =====================================================================
it('periode panjang: daily[] memuat satu baris per tanggal ber-record, puluhan baris tanpa masalah', function () {
    // 20 consecutive dates, each with one filled reading.
    for ($day = 1; $day <= 20; $day++) {
        $date = sprintf('2026-09-%02d', $day);

        laporanStorageTankRecord($this->stationA, $date, 'TK-01', [
            ['time_slot' => '07:00', 'calculated_weight_mt' => 1000.0 + $day, 'ffa_percent' => 4.0],
        ]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/storage-tank-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();
    $summary->assertJsonCount(20, 'daily');
    $summary->assertJsonPath('total.days_with_records', 20);
    $summary->assertJsonPath('total.reading_rows', 20);

    // Ascending, one entry per date that HAS a record — the ten dates with
    // no record at all get no padded zero row.
    $dates = array_column($summary->json('daily'), 'date');
    expect($dates[0])->toBe('2026-09-01');
    expect($dates[19])->toBe('2026-09-20');
    expect($dates)->toBe(array_values(collect($dates)->sort()->all()));
    expect($dates)->not->toContain('2026-09-21');

    // The main figures are unaffected by the length of the recap.
    expect($summary->json('stock.opening_mt'))->toEqual(1001.0);
    expect($summary->json('stock.closing_mt'))->toEqual(1020.0);
    expect($summary->json('stock.movement_mt'))->toEqual(19.0);
});

// =====================================================================
// Scenario 19: "stok awal adalah pembacaan pertama dan stok akhir terakhir"
// =====================================================================
it('stok awal/akhir menurut waktu: 100,0 dan 80,0 walau urutan penyimpanan dan nilai mengatakan lain', function () {
    laporanStorageTankScrambledTank($this->stationA);

    // TK-02 locks the cross-date ordering: the earlier DATE carries the
    // later SLOT.
    laporanStorageTankRecord($this->stationA, '2026-09-01', 'TK-02', [
        ['time_slot' => '18:00', 'calculated_weight_mt' => 120.0],
    ]);
    laporanStorageTankRecord($this->stationA, '2026-09-02', 'TK-02', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 90.0],
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/storage-tank-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();

    $tk01 = laporanStorageTankByTank($summary->json('by_tank'), 'TK-01');
    expect($tk01['opening_mt'])->toEqual(100.0);
    expect($tk01['opening_mt'])->not->toEqual(80.0);   // the lowest value
    expect($tk01['opening_mt'])->not->toEqual(250.0);  // the first row stored
    expect($tk01['opening_at'])->toBe('2026-09-01 06:00');
    expect($tk01['closing_mt'])->toEqual(80.0);
    expect($tk01['closing_mt'])->not->toEqual(250.0);  // the highest value
    expect($tk01['closing_at'])->toBe('2026-09-10 18:00');

    $tk02 = laporanStorageTankByTank($summary->json('by_tank'), 'TK-02');
    expect($tk02['opening_mt'])->toEqual(120.0);
    expect($tk02['opening_mt'])->not->toEqual(90.0);
    expect($tk02['opening_at'])->toBe('2026-09-01 18:00');
});

// =====================================================================
// Scenario 20: "pergerakan dihitung per tangki lalu dijumlahkan"
// =====================================================================
it('pergerakan bersih adalah jumlah kolom pergerakan by_tank, bukan selisih stok gabungan', function () {
    $period = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('storage-tank')
        ->range('2026-09-01', '2026-09-10')->named('Periode Per Tangki')->open()->create();

    laporanStorageTankRecord($this->stationA, '2026-09-01', 'TK-01', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 100.0],
    ]);
    laporanStorageTankRecord($this->stationA, '2026-09-10', 'TK-01', [
        ['time_slot' => '18:00', 'calculated_weight_mt' => 90.0],
    ]);
    laporanStorageTankRecord($this->stationA, '2026-09-09', 'TK-02', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 400.0],
    ]);
    laporanStorageTankRecord($this->stationA, '2026-09-10', 'TK-02', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 420.0],
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/storage-tank-reports/summary?'.http_build_query(['period_id' => $period->id]));

    $summary->assertOk();

    $byTank = $summary->json('by_tank');

    expect(laporanStorageTankByTank($byTank, 'TK-01')['movement_mt'])->toEqual(-10.0);
    expect(laporanStorageTankByTank($byTank, 'TK-02')['movement_mt'])->toEqual(20.0);

    $columnSum = round(array_sum(array_column(
        array_filter($byTank, fn ($tank) => $tank['movement_computable']),
        'movement_mt'
    )), 2);

    expect($summary->json('stock.movement_mt'))->toEqual(10.0);
    expect($summary->json('stock.movement_mt'))->toEqual($columnSum);

    // The combined-stock answer (+410,0) is produced by NO figure in the
    // payload. Asserted over the numbers rather than as a substring of the
    // JSON, because period ids are UUIDs and a bare "410" could appear in
    // one by chance.
    expect($summary->json('stock.movement_mt'))->not->toEqual(410.0);

    foreach (array_column($summary->json('by_tank'), 'movement_mt') as $movement) {
        expect($movement)->not->toEqual(410.0);
    }

    foreach ($summary->json('metrics') as $stats) {
        foreach (['min', 'avg', 'max'] as $key) {
            expect($stats[$key])->not->toEqual(410.0);
        }
    }
});

// =====================================================================
// Scenario 21: "suhu rata-rata diambil dari kolom yang dicatat Operator"
// =====================================================================
it('suhu rata-rata: 55,0 dari kolom Operator, bukan 60,0 hasil hitung ulang atas/tengah/bawah', function () {
    laporanStorageTankRecord($this->stationA, '2026-09-04', 'TK-01', [
        [
            'oil_temperature_top_c' => 50.0,
            'oil_temperature_middle_c' => 60.0,
            'oil_temperature_bottom_c' => 70.0,
            'average_temperature_c' => 55.0,
        ],
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/storage-tank-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();
    expect($summary->json('metrics.average_temperature_c.avg'))->toEqual(55.0);
    // 60,0 is the arithmetic mean of 50/60/70 — the recomputed answer.
    expect($summary->json('metrics.average_temperature_c.avg'))->not->toEqual(60.0);
    expect($summary->json('metrics.average_temperature_c.min'))->toEqual(55.0);
    expect($summary->json('metrics.average_temperature_c.max'))->toEqual(55.0);
    $summary->assertJsonPath('metrics.average_temperature_c.reading_count', 1);

    // The three positional temperatures remain three metrics of their own.
    expect($summary->json('metrics.oil_temperature_top_c.avg'))->toEqual(50.0);
    expect($summary->json('metrics.oil_temperature_middle_c.avg'))->toEqual(60.0);
    expect($summary->json('metrics.oil_temperature_bottom_c.avg'))->toEqual(70.0);

    // And the per-tank / per-day figures come from the same column.
    expect(laporanStorageTankByTank($summary->json('by_tank'), 'TK-01')['average_temperature_avg'])->toEqual(55.0);
    expect($summary->json('daily.0.temperature_avg'))->toEqual(55.0);
});

// =====================================================================
// Scenario 22: "setiap metrik punya penyebutnya sendiri"
// =====================================================================
it('penyebut terpisah: FFA 2 pembacaan rata-rata 4,0, kadar air 1 rata-rata 0,2, DOBI 1', function () {
    laporanStorageTankRecord($this->stationA, '2026-09-04', 'TK-01', [
        ['ffa_percent' => 3.0, 'moisture_content_percent' => 0.2, 'dobi_index' => null],
        ['ffa_percent' => 5.0, 'moisture_content_percent' => null, 'dobi_index' => null],
        ['ffa_percent' => null, 'moisture_content_percent' => null, 'dobi_index' => 2.4],
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/storage-tank-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();

    $summary->assertJsonPath('metrics.ffa_percent.reading_count', 2);
    expect($summary->json('metrics.ffa_percent.avg'))->toEqual(4.0);
    $summary->assertJsonPath('metrics.moisture_content_percent.reading_count', 1);
    expect($summary->json('metrics.moisture_content_percent.avg'))->toEqual(0.2);
    // 0,2 / 3 = 0,07 — the answer a single shared denominator would give.
    expect($summary->json('metrics.moisture_content_percent.avg'))->not->toEqual(0.07);
    $summary->assertJsonPath('metrics.dobi_index.reading_count', 1);
    expect($summary->json('metrics.dobi_index.avg'))->toEqual(2.4);

    // NOT ONE metric carries the shared filled-row count of 3.
    $summary->assertJsonPath('coverage.filled_slots', 3);

    foreach (['ffa_percent', 'moisture_content_percent', 'dobi_index'] as $metric) {
        expect($summary->json("metrics.{$metric}.reading_count"))->not->toBe(3);
    }
});

// =====================================================================
// Scenario 23: "rentang periode inklusif di kedua ujung" (2 steps)
// =====================================================================
it('rentang inklusif: pembacaan pada start_date dan end_date ikut, H-1 dan H+1 tidak, di summary maupun CSV', function () {
    $period = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('storage-tank')
        ->range('2026-09-01', '2026-09-10')->named('Periode Inklusif')->open()->create();

    laporanStorageTankRecord($this->stationA, '2026-08-31', 'TK-01', [
        ['time_slot' => '23:00', 'calculated_weight_mt' => 999.0],
    ]);
    laporanStorageTankRecord($this->stationA, '2026-09-01', 'TK-01', [
        ['time_slot' => '00:00', 'calculated_weight_mt' => 111.0],
    ]);
    laporanStorageTankRecord($this->stationA, '2026-09-10', 'TK-01', [
        ['time_slot' => '23:00', 'calculated_weight_mt' => 222.0],
    ]);
    laporanStorageTankRecord($this->stationA, '2026-09-11', 'TK-01', [
        ['time_slot' => '00:00', 'calculated_weight_mt' => 888.0],
    ]);

    // Step 1 — summary
    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/storage-tank-reports/summary?'.http_build_query(['period_id' => $period->id]));

    $summary->assertOk();
    $summary->assertJsonPath('total.reading_rows', 2);
    expect(array_column($summary->json('daily'), 'date'))->toBe(['2026-09-01', '2026-09-10']);
    expect($summary->json('stock.opening_mt'))->toEqual(111.0);
    expect($summary->json('stock.opening_at'))->toBe('2026-09-01 00:00');
    expect($summary->json('stock.closing_mt'))->toEqual(222.0);
    expect($summary->json('stock.closing_at'))->toBe('2026-09-10 23:00');
    expect($summary->json('stock.opening_mt'))->not->toEqual(999.0);
    expect($summary->json('stock.closing_mt'))->not->toEqual(888.0);

    // Step 2 — export over the same period: both ends in, neighbours out.
    $export = $this->actingAs($this->supervisor, 'web')
        ->get('/api/storage-tank-reports/export?'.http_build_query(['period_id' => $period->id]));

    $export->assertOk();

    // ONE capture, then both assertions derive from it — re-streaming the
    // same response object would hand back an empty body (see
    // laporanStorageTankCsvLines' note).
    $body = laporanStorageTankStreamed($export->baseResponse);
    expect($body)->toContain('2026-09-01');
    expect($body)->toContain('2026-09-10');
    expect($body)->not->toContain('2026-08-31');
    expect($body)->not->toContain('2026-09-11');
    expect(laporanStorageTankCsvLinesOf($body))->toHaveCount(3);
});

// =====================================================================
// Scenario 24: "FFA, kadar air, dan DOBI pada satu grafik"
// =====================================================================
it('daily[] membawa ffa_avg, moisture_avg dan dobi_avg pada SATU baris per tanggal, dengan skala yang tidak sebanding', function () {
    // Three ranges that cannot share one linear axis: FFA ~3-5 %, moisture
    // ~0,1-0,3 %, DOBI ~2-4 without a unit.
    foreach ([
        ['2026-09-01', 3.2, 0.12, 3.8],
        ['2026-09-02', 4.1, 0.21, 3.1],
        ['2026-09-03', 4.9, 0.29, 2.4],
    ] as [$date, $ffa, $moisture, $dobi]) {
        laporanStorageTankRecord($this->stationA, $date, 'TK-01', [
            ['time_slot' => '07:00', 'ffa_percent' => $ffa, 'moisture_content_percent' => $moisture,
                'dobi_index' => $dobi, 'calculated_weight_mt' => 1000.0],
        ]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/storage-tank-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();
    $summary->assertJsonCount(3, 'daily');

    // The three series ship on the SAME ROW — that shared row is what lets
    // the screen draw one chart, because what says the oil is deteriorating
    // is the three of them moving together.
    foreach ($summary->json('daily') as $row) {
        expect($row)->toHaveKeys(['ffa_avg', 'moisture_avg', 'dobi_avg']);
        expect($row['ffa_avg'])->not->toBeNull();
        expect($row['moisture_avg'])->not->toBeNull();
        expect($row['dobi_avg'])->not->toBeNull();
    }

    // THE PAYLOAD STAYS RAW. Normalisation is a presentation decision that
    // belongs to the screen (and is stated in its legend); the API must not
    // pre-normalise, or the raw values under the chart would be a second
    // truth.
    expect($summary->json('daily.0.ffa_avg'))->toEqual(3.2);
    expect($summary->json('daily.0.moisture_avg'))->toEqual(0.12);
    expect($summary->json('daily.0.dobi_avg'))->toEqual(3.8);

    // And the payload carries no chart-scaling vocabulary at all.
    $json = strtolower($summary->getContent());
    foreach (['normalis', 'index_', 'axis', 'chart'] as $presentation) {
        expect($json)->not->toContain($presentation);
    }
});

// =====================================================================
// Scenario 25: "tidak ada penandaan nilai di luar batas"
// =====================================================================
it('nilai ekstrem: dikembalikan apa adanya, dan payload tidak memuat satu pun kosakata penandaan', function () {
    laporanStorageTankRecord($this->stationA, '2026-09-04', 'TK-01', [
        ['ffa_percent' => 4.0, 'average_temperature_c' => 50.0],
        ['ffa_percent' => 42.0, 'average_temperature_c' => 150.0],
        ['ffa_percent' => 4.2, 'average_temperature_c' => 51.0],
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/storage-tank-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();

    $json = strtolower($summary->getContent());

    foreach ([
        'threshold', 'outlier', 'iqr', 'fence', 'is_out_of_range', 'is_danger',
        'is-danger', 'is-warning', 'text-red', 'bg-red', 'severity', 'alert',
    ] as $forbidden) {
        expect($json)->not->toContain($forbidden);
    }

    // The extremes come back exactly as recorded — judging them is the
    // reader's job, and Storage Tank has no operational-target master.
    expect($summary->json('metrics.ffa_percent.max'))->toEqual(42.0);
    expect($summary->json('metrics.average_temperature_c.max'))->toEqual(150.0);
    expect($summary->json('metrics.ffa_percent.min'))->toEqual(4.0);
});

// =====================================================================
// Scenario 26: "layar hanya membaca" (3 steps + a snapshot)
// =====================================================================
it('hanya membaca: dua summary dan satu export tidak mengubah satu baris pun, dan prefix ini hanya menerima GET', function () {
    $otherPeriod = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('storage-tank')
        ->range('2026-11-01', '2026-11-30')->named('Periode November')->open()->create();

    laporanStorageTankRecord($this->stationA, '2026-09-04', 'TK-01', [
        ['time_slot' => '07:00', 'calculated_weight_mt' => 100.0, 'ffa_percent' => 4.0],
        ['time_slot' => '08:00', 'calculated_weight_mt' => 90.0],
    ]);
    laporanStorageTankRecord($this->stationA, '2026-11-04', 'TK-02', [
        ['time_slot' => '07:00', 'calculated_weight_mt' => 300.0],
    ]);

    $recordsBefore = StorageTankRecord::query()->orderBy('id')->get()->toArray();
    $detailsBefore = StorageTankDetail::query()->orderBy('id')->get()->toArray();

    // Step 1 + Step 2 — two different periods.
    $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/storage-tank-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]))
        ->assertOk();
    $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/storage-tank-reports/summary?'.http_build_query(['period_id' => $otherPeriod->id]))
        ->assertOk();

    // Step 3 — export.
    $export = $this->actingAs($this->supervisor, 'web')
        ->get('/api/storage-tank-reports/export?'.http_build_query(['period_id' => $otherPeriod->id]));
    $export->assertOk();
    laporanStorageTankStreamed($export->baseResponse);

    expect(StorageTankRecord::query()->orderBy('id')->get()->toArray())->toEqual($recordsBefore);
    expect(StorageTankDetail::query()->orderBy('id')->get()->toArray())->toEqual($detailsBefore);

    // THE WHOLE PREFIX IS GET-ONLY: there is no write verb to call in the
    // first place, which is a stronger guarantee than "the write verb
    // refuses".
    foreach (['postJson', 'putJson', 'patchJson', 'deleteJson'] as $verb) {
        foreach ([
            '/api/storage-tank-reports/summary',
            '/api/storage-tank-reports/export',
            '/api/storage-tank-reports/periods',
            '/api/storage-tank-reports/business-units/options',
        ] as $path) {
            $response = $this->actingAs($this->supervisor, 'web')->{$verb}($path, []);

            expect($response->getStatusCode())->toBeIn([404, 405]);
        }
    }
});

// =====================================================================
// Scenario 27: "daftar periode hanya yang mencakup Storage Tank"
// =====================================================================
it('daftar periode: hanya yang punya baris period_stations storage-tank, termasuk yang tertutup', function () {
    $periodOther = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('sterilizer')
        ->range('2026-10-01', '2026-10-31')->named('Periode A Stasiun Lain')->open()->create();
    $periodStorage = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('storage-tank')
        ->range('2026-11-01', '2026-11-30')->named('Periode B Storage Tank')->closed()->create();
    $periodAllTypes = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType(null)
        ->range('2026-12-01', '2026-12-31')->named('Periode C Semua Stasiun')->open()->create();

    $periods = $this->actingAs($this->supervisor, 'web')->getJson('/api/storage-tank-reports/periods');

    $periods->assertOk();

    $ids = array_column($periods->json('data'), 'id');

    expect($ids)->toContain((string) $periodStorage->id);
    expect($ids)->toContain((string) $periodAllTypes->id);
    expect($ids)->not->toContain((string) $periodOther->id);

    // A CLOSED period is listed exactly like an open one: the period lock
    // governs writing data, not reading a report.
    $closed = collect($periods->json('data'))->firstWhere('id', (string) $periodStorage->id);
    expect($closed['status'])->toBe('closed');

    // KONTRAK API TETAP DATAR setelah pemisahan periods/period_stations
    // (2026-09-25): stationType(null) kini berarti "satu baris per jenis
    // stasiun", jadi opsi ini adalah pasangan (periode, storage-tank) —
    // station_type selalu terisi dan label bersama 'Semua Stasiun' sudah tidak
    // ada (ALL_STATION_TYPES_LABEL ikut dihapus).
    $allTypes = collect($periods->json('data'))->firstWhere('id', (string) $periodAllTypes->id);
    expect($allTypes['station_type'])->toBe('storage-tank');
    expect($allTypes['station_type_label'])->toBe('Storage Tank');
});

// =====================================================================
// Scenario (BARU 2026-09-26): periode tanpa baris period_stations untuk
// storage-tank tidak boleh muncul — perilaku yang DULU dijamin cabang
// orWhereNull('station_type') pada listPeriods() dan kini sengaja dibuang.
// Cakupan "semua stasiun" hanya ada lewat ADANYA baris per jenis stasiun.
// =====================================================================
it('daftar periode: periode tanpa baris storage-tank tidak ditawarkan', function () {
    $otherTypesOnly = Period::factory()->forBusinessUnit($this->businessUnitA)
        ->stationTypes(['sterilizer', 'boiler-room'])
        ->range('2026-05-01', '2026-05-31')->named('Periode Tanpa Storage Tank')->open()->create();
    $noStations = Period::factory()->forBusinessUnit($this->businessUnitA)
        ->noStations()->range('2026-06-01', '2026-06-30')->named('Periode Tanpa Stasiun')->create();

    $periods = $this->actingAs($this->supervisor, 'web')->getJson('/api/storage-tank-reports/periods');
    $periods->assertOk();

    $ids = collect($periods->json('data'))->pluck('id')->all();

    expect($ids)->not->toContain((string) $otherTypesOnly->id);
    expect($ids)->not->toContain((string) $noStations->id);
});

// =====================================================================
// Scenario (BARU 2026-09-26): status yang dilaporkan adalah status STASIUN
// INI di dalam periode itu, bukan status periode — periode tidak punya status
// lagi. Bentuk ini (Storage Tank terbuka sementara Sterilizer tertutup di periode
// yang sama) sebelumnya mustahil dinyatakan.
// =====================================================================
it('daftar periode: status yang ditampilkan adalah status storage-tank, bukan status stasiun lain di periode yang sama', function () {
    $period = Period::factory()->forBusinessUnit($this->businessUnitA)
        ->noStations()->range('2026-05-01', '2026-05-31')->named('Periode Campuran')->create();

    PeriodStation::factory()->forPeriod($period)->stationType('storage-tank')->open()->create();
    PeriodStation::factory()->forPeriod($period)->stationType('sterilizer')->closed()->create();

    $periods = $this->actingAs($this->supervisor, 'web')->getJson('/api/storage-tank-reports/periods');
    $periods->assertOk();

    expect(collect($periods->json('data'))->firstWhere('id', (string) $period->id)['status'])->toBe('open');
});

// =====================================================================
// REACHABILITY — not one of the 27 scenarios, but the single line that
// decides whether any of them can ever be reached from the UI.
//
// StationReportService::REPORT_ROUTES is the single source of truth for
// report_available / report_path on screen-140 (/reports). A report whose
// code is missing from that map exists, passes every test in this file, and
// stays permanently greyed out. Screen-140's own API test compares the
// available codes — which arrive in station_types.sort_order — against
// array_keys(REPORT_ROUTES) with a strict, ORDERED toBe(), so a new report
// added out of sort_order breaks a test that has nothing to do with it.
// Every assertion below is derived from the constant, never from a literal.
// =====================================================================
it('reachability: REPORT_ROUTES memetakan storage-tank dan tetap urut menurut station_types.sort_order', function () {
    expect(StationReportService::REPORT_ROUTES)->toHaveKey('storage-tank');
    expect(StationReportService::REPORT_ROUTES['storage-tank'])->toBe('reports.storage-tank');

    // The route name actually resolves to a registered route.
    expect(route(StationReportService::REPORT_ROUTES['storage-tank'], [], false))->toBe('/reports/storage-tank');

    $sortOrderCodes = StationType::query()
        ->whereIn('code', array_keys(StationReportService::REPORT_ROUTES))
        ->orderBy('sort_order')
        ->pluck('code')
        ->all();

    expect(array_keys(StationReportService::REPORT_ROUTES))->toBe($sortOrderCodes);
});
