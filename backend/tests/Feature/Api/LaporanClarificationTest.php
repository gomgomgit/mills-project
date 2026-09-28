<?php

/**
 * LaporanClarificationTest (Feature/Api) — screen-132--laporan-clarification-web /
 * usecase-132--laporan-clarification-web (Laporan Periode Clarification).
 *
 * Integration tests for the four GET endpoints under
 * /api/clarification-reports (App\Http\Controllers\Api\
 * ClarificationReportController), one test per test_scenarios entry, running
 * each scenario's api_test steps IN ORDER and feeding the real response of
 * step N into step N+1 exactly as the `{{stepN.field}}` references
 * prescribe. Exercises the real route -> 'auth:web,sanctum' +
 * 'role:supervisor,mill_management,admin' -> controller ->
 * ClarificationReportService -> Eloquent chain, mirroring
 * tests/Feature/Api/LaporanBoilerRoomTest.php (screen-131).
 *
 * WHAT MAKES THIS SCREEN DIFFERENT FROM THE THREE STATION REPORTS BEFORE IT:
 * PRODUCTION IS DERIVED, NOT RECORDED. There is no production column;
 * production.total_ton is SUM(pure_oil_production_rate_ton_hour) over the
 * rows that carry one, and production.reading_count travels beside it on
 * every response. An hour with no rate reading is NOT an hour that produced
 * zero — and that trap hides itself, because both readings give the SAME
 * total and only the average differs. Scenario 7 asserts the pair.
 *
 * TWO GUARDS, TWO SHAPES — asserted separately on purpose:
 *   - business_unit_id from the client is IGNORED for Supervisor / Mill
 *     Management: probing another mill answers 200 with the caller's OWN
 *     data, deliberately NOT 403 (a 403 would confirm the other mill
 *     exists). The two-step shape of that scenario is load-bearing and must
 *     not be collapsed into one request.
 *   - period_id belonging to another mill IS refused with 403 FORBIDDEN by
 *     ClarificationReportService::authorizePeriod().
 *
 * OPERATOR IS ADMITTED ON THREE OF THE FOUR ROUTES SINCE 2026-09-25, for
 * screen-138--laporan-clarification-mobile. The route middleware now carries
 * `operator`, so /periods, /summary and /export answer 200 — bound to the
 * Operator's OWN mill. /business-units/options still answers 403, but the
 * refusal now comes from the SERVICE rather than from EnsureRole, so it
 * carries code = 'FORBIDDEN' and scenario 14 asserts that code, not just the
 * status. (Refusals raised by EnsureRole build their own JSON without going
 * through ApiExceptionHandler and carry only { message }; that shape no
 * longer applies to any Operator request on this prefix.)
 *
 * AND THE STATUS CODE IS NOT THE ASSERTION THAT MATTERS THERE. Admitting
 * Operator in guardAccess() alone would drop it into the unbound Admin
 * branch of resolveBusinessUnit(), where the client's business_unit_id IS
 * honoured — and that leak answers 200 just as happily. Only the CONTENT of
 * the payload tells the widening apart from the leak, so scenario 14 sends
 * Mill Beta's id and asserts Mill Alpha's figures come back.
 *
 * THE WEB ROUTE /reports/clarification IS UNCHANGED and still carries no
 * `operator` — LaporanClarification::canAccess() keeps its own role list and
 * never calls this service, so the web layer stays closed independently.
 * e2e-web/tests/laporan-clarification.spec.ts asserts that, unmodified.
 *
 * THE FIGURES ARE THE POINT, not just the status codes: every scenario that
 * has a number in its spec asserts that number, because this screen's
 * failure mode is a plausible-looking figure, not an error.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\ClarificationDetail;
use App\Models\ClarificationRecord;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use App\Services\ClarificationRecordService;
use App\Services\ClarificationReportService;
use App\Services\StationReportService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The all-mills spy for the "akun belum terhubung ke mill" scenario. Bound
 * into the container so the CONTROLLER resolves it — "the whole-mill list
 * was never built" is a claim about something that did not happen, and only
 * a recorded call count of zero proves it.
 */
class LaporanClarificationApiAllBusinessUnitsSpy extends ClarificationReportService
{
    public int $allBusinessUnitsCalls = 0;

    public function allBusinessUnits(): Collection
    {
        $this->allBusinessUnitsCalls++;

        return parent::allBusinessUnits();
    }
}

/**
 * One clarification_records header plus one clarification_details row per
 * entry of $rows. `time_slot` is filled in from the canonical grid by
 * position unless the entry supplies its own, so
 * UNIQUE(clarification_record_id, time_slot) holds without every fixture
 * having to spell the slots out.
 *
 * @param  list<array<string, mixed>>  $rows
 */
function laporanClarificationRecord(Station $station, string $date, array $rows = [], array $overrides = []): ClarificationRecord
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
 * $count rows each filling ONLY sludge_tank_temp_c — a filled row that
 * carries NO production rate, which is the shape every derived-production
 * trap needs.
 *
 * @return list<array<string, mixed>>
 */
function laporanClarificationSludgeRows(int $count, float $temp = 87.0, array $extra = []): array
{
    $rows = [];

    for ($index = 0; $index < $count; $index++) {
        $rows[] = array_merge(['sludge_tank_temp_c' => $temp], $extra);
    }

    return $rows;
}

/**
 * TEN filled rows of which only FOUR carry a rate (10,0 / 12,5 / 8,0 / 9,5
 * = 40,0), and every metric filled a different number of times:
 * clarification 7, oil 5, sludge 9, buffer 3, rate 4, downtime 6. Not one
 * denominator is the shared 10 — which is the point.
 *
 * @return list<array<string, mixed>>
 */
function laporanClarificationMixedRows(bool $withRate = true): array
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

/**
 * Raw bulk seed of $totalDetailRows detail rows, spread over as many
 * headers as UNIQUE(record, time_slot) requires (24 slots each) — the only
 * way to reach the export ceiling inside a test.
 */
function laporanClarificationBulkSeed(Station $station, User $author, string $monthPrefix, int $totalDetailRows): void
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
            'date' => $monthPrefix.'-'.$day,
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
}

/** The body of a StreamedResponse, captured. */
function laporanClarificationStreamed($response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->clarification()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->clarification()->create();

    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    // Admin is bound to no mill at all — hence the mill picker.
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('clarification')
        ->range('2026-03-01', '2026-03-31')
        ->named('Periode Maret Alpha')
        ->open()
        ->create();

    $this->slotsPerDay = count(ClarificationRecordService::canonicalTimeSlots());
});

// =====================================================================
// Scenario 1: "berhasil sebagai Supervisor atau Mill Management"
// =====================================================================
it('berhasil: periods -> summary -> export for a Supervisor and a Mill Management, chained on the real responses', function () {
    laporanClarificationRecord($this->stationA, '2026-03-02', [
        ['clarification_tank_temp_c' => 93.0, 'oil_tank_temperature_c' => 97.0, 'sludge_tank_temp_c' => 87.0,
            'buffer_tank_level_percent' => 72.0, 'pure_oil_production_rate_ton_hour' => 10.0, 'downtime_mins' => 6.0],
        ['clarification_tank_temp_c' => 95.0, 'oil_tank_temperature_c' => 99.0, 'sludge_tank_temp_c' => 89.0,
            'buffer_tank_level_percent' => 74.0, 'pure_oil_production_rate_ton_hour' => 12.0, 'downtime_mins' => 4.0],
    ], ['clarification_id' => 'CLF-01']);

    laporanClarificationRecord($this->stationA, '2026-03-03', [
        ['clarification_tank_temp_c' => 91.0, 'oil_tank_temperature_c' => 95.0, 'sludge_tank_temp_c' => 85.0,
            'buffer_tank_level_percent' => 70.0, 'pure_oil_production_rate_ton_hour' => 8.0, 'downtime_mins' => 10.0],
    ], ['clarification_id' => 'CLF-02']);

    foreach ([$this->supervisor, $this->millManagement] as $user) {
        // Step 1 — GET /periods (no business_unit_id: it comes from the
        // account, and the client is never asked for it).
        $periods = $this->actingAs($user, 'web')->getJson('/api/clarification-reports/periods');
        $periods->assertOk();
        $periods->assertJsonStructure([
            'data' => [['id', 'name', 'start_date', 'end_date', 'status', 'station_type', 'station_type_label']],
        ]);

        $periodId = $periods->json('data.0.id');

        // Step 2 — GET /summary?period_id={{step1.data.0.id}}
        $summary = $this->actingAs($user, 'web')
            ->getJson('/api/clarification-reports/summary?'.http_build_query(['period_id' => $periodId]));

        $summary->assertOk();
        $summary->assertJsonStructure([
            'period' => ['id', 'name', 'start_date', 'end_date', 'status', 'business_unit_name'],
            'coverage' => [
                'filled_slots', 'expected_slots', 'coverage_percent',
                'unit_count', 'slots_per_unit_per_day', 'days_in_period',
            ],
            'production' => ['total_ton', 'avg_per_day_ton', 'reading_count'],
            'downtime' => ['total_mins', 'avg_per_day_mins', 'hours_with_downtime', 'reading_count'],
            'metrics' => [
                'pure_oil_production_rate_ton_hour' => ['min', 'avg', 'max', 'reading_count'],
                'clarification_tank_temp_c' => ['min', 'avg', 'max', 'reading_count'],
                'oil_tank_temperature_c' => ['min', 'avg', 'max', 'reading_count'],
                'sludge_tank_temp_c' => ['min', 'avg', 'max', 'reading_count'],
                'buffer_tank_level_percent' => ['min', 'avg', 'max', 'reading_count'],
                'downtime_mins' => ['min', 'avg', 'max', 'reading_count'],
            ],
            'daily' => [[
                'date', 'filled_slots', 'production_ton', 'rate_avg',
                'clarification_tank_temp_avg', 'oil_tank_temperature_avg',
                'sludge_tank_temp_avg', 'buffer_tank_level_avg', 'downtime_mins',
            ]],
            'by_unit' => [[
                'clarification_id', 'reading_count', 'production_ton', 'rate_avg',
                'clarification_tank_temp_avg', 'oil_tank_temperature_avg',
                'sludge_tank_temp_avg', 'downtime_mins',
            ]],
            'total' => ['days_with_records', 'reading_rows'],
        ]);

        $summary->assertJsonPath('period.business_unit_name', 'Mill Alpha');

        // DERIVED PRODUCTION — 10,0 + 12,0 + 8,0 = 30,0 ton, and the reading
        // count that licenses it travels on the same object.
        expect($summary->json('production.total_ton'))->toEqual(30.0);
        $summary->assertJsonPath('production.reading_count', 3);
        expect($summary->json('production.avg_rate_ton_hour'))->toEqual(10.0);
        // Downtime is published BESIDE production because the two are never
        // netted against each other.
        expect($summary->json('downtime.total_mins'))->toEqual(20);
        $summary->assertJsonPath('downtime.reading_count', 3);

        $summary->assertJsonPath('coverage.filled_slots', 3);
        $summary->assertJsonPath('coverage.expected_slots', 2 * 31 * $this->slotsPerDay);
        $summary->assertJsonCount(2, 'daily');
        $summary->assertJsonCount(2, 'by_unit');
        $summary->assertJsonPath('total.reading_rows', 3);

        // ALL THREE TANK TEMPERATURES ON ONE daily[] ROW — one axis, one
        // chart; the gap between the tanks is what is read.
        foreach ($summary->json('daily') as $row) {
            expect($row)->toHaveKeys([
                'clarification_tank_temp_avg', 'oil_tank_temperature_avg', 'sludge_tank_temp_avg',
            ]);
        }

        // Step 3 — GET /export?period_id={{step1.data.0.id}}, and nothing
        // about the station data may change because of it.
        $recordsBefore = ClarificationRecord::count();
        $detailsBefore = ClarificationDetail::count();

        $export = $this->actingAs($user, 'web')
            ->get('/api/clarification-reports/export?'.http_build_query(['period_id' => $periodId]));

        $export->assertOk();
        expect($export->headers->get('Content-Type'))->toContain('text/csv');

        $body = laporanClarificationStreamed($export->baseResponse);
        $lines = array_values(array_filter(explode("\n", trim($body))));

        // Header + one line per TIME SLOT.
        expect($lines)->toHaveCount(4);
        expect($lines[0])->toContain('Slot Waktu');
        expect($lines[0])->toContain('Laju Produksi Minyak Murni (ton/jam)');

        expect(ClarificationRecord::count())->toBe($recordsBefore);
        expect(ClarificationDetail::count())->toBe($detailsBefore);
    }
});

// =====================================================================
// Scenario 2: "berhasil sebagai Admin"
// =====================================================================
it('admin: options -> periods of the chosen mill -> summary -> export, computed only from that mill', function () {
    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('clarification')
        ->range('2026-03-01', '2026-03-31')->named('Periode Maret Beta')->open()->create();

    laporanClarificationRecord($this->stationA, '2026-03-10', [
        ['pure_oil_production_rate_ton_hour' => 20.0],
    ]);
    laporanClarificationRecord($this->stationB, '2026-03-10', [
        ['pure_oil_production_rate_ton_hour' => 900.0],
    ], ['clarification_id' => 'CLF-BETA']);

    // Step 1 — GET /business-units/options
    $options = $this->actingAs($this->admin, 'web')->getJson('/api/clarification-reports/business-units/options');
    $options->assertOk();
    $options->assertJsonStructure(['data' => [['id', 'name']]]);

    $millId = collect($options->json('data'))->firstWhere('name', 'Mill Alpha')['id'];
    expect($millId)->toBe((string) $this->businessUnitA->id);

    // Step 2 — GET /periods?business_unit_id={{step1.data.0.id}}
    $periods = $this->actingAs($this->admin, 'web')
        ->getJson('/api/clarification-reports/periods?'.http_build_query(['business_unit_id' => $millId]));
    $periods->assertOk();

    $periodIds = collect($periods->json('data'))->pluck('id')->all();
    expect($periodIds)->toContain((string) $this->periodA->id);
    expect($periodIds)->not->toContain((string) $periodB->id);

    // Step 3 — GET /summary?business_unit_id=..&period_id={{step2.data.0.id}}
    $summary = $this->actingAs($this->admin, 'web')->getJson('/api/clarification-reports/summary?'.http_build_query([
        'business_unit_id' => $millId,
        'period_id' => $periods->json('data.0.id'),
    ]));

    $summary->assertOk();
    $summary->assertJsonPath('period.business_unit_name', 'Mill Alpha');
    expect($summary->json('production.total_ton'))->toEqual(20.0);
    $summary->assertJsonPath('production.reading_count', 1);
    // Mill Beta's 900,0 is unmistakable if it ever surfaces.
    expect($summary->json('metrics.pure_oil_production_rate_ton_hour.max'))->not->toEqual(900.0);

    // Step 4 — GET /export, identical blocks for Admin as for anyone else.
    $export = $this->actingAs($this->admin, 'web')->get('/api/clarification-reports/export?'.http_build_query([
        'business_unit_id' => $millId,
        'period_id' => $periods->json('data.0.id'),
    ]));

    $export->assertOk();
    expect(laporanClarificationStreamed($export->baseResponse))->toContain('2026-03-10');
});

// =====================================================================
// Scenario 3: "Admin memilih mill lebih dulu"
// =====================================================================
it('admin tanpa mill: summary answers 422 VALIDATION_ERROR on business_unit_id and returns no figure', function () {
    laporanClarificationRecord($this->stationA, '2026-03-10', [['pure_oil_production_rate_ton_hour' => 20.0]]);

    // Step 1 — /summary with a period_id but NO business_unit_id.
    // Incomplete input, never 403 and never an empty report that would read
    // as "this mill has no data".
    $summary = $this->actingAs($this->admin, 'web')
        ->getJson('/api/clarification-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertStatus(422);
    $summary->assertJsonPath('code', 'VALIDATION_ERROR');
    $summary->assertJsonStructure(['errors' => ['business_unit_id']]);
    $summary->assertJsonMissingPath('production');
    $summary->assertJsonMissingPath('metrics');
    $summary->assertJsonMissingPath('coverage');

    // The period list refuses the same way; the mill picker itself is fine.
    $this->actingAs($this->admin, 'web')
        ->getJson('/api/clarification-reports/business-units/options')->assertOk();

    $periods = $this->actingAs($this->admin, 'web')->getJson('/api/clarification-reports/periods');
    $periods->assertStatus(422);
    $periods->assertJsonPath('code', 'VALIDATION_ERROR');
});

// =====================================================================
// Scenario 4: "mill belum punya periode"
// =====================================================================
it('mill tanpa periode: periods answers 200 with an empty list, never a 404', function () {
    $supervisorB = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitB)->create();

    // Another station type's period exists — it must not be offered.
    Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('sterilizer')
        ->range('2026-07-01', '2026-07-31')->named('Periode Sterilizer')->open()->create();

    // An empty picker is a valid answer, not an error.
    $periods = $this->actingAs($supervisorB, 'web')->getJson('/api/clarification-reports/periods');
    $periods->assertOk();
    expect($periods->json('data'))->toBe([]);

    // Forcing a summary with a period id that does not exist is a clean 404.
    $summary = $this->actingAs($supervisorB, 'web')
        ->getJson('/api/clarification-reports/summary?'.http_build_query(['period_id' => (string) Str::uuid()]));

    $summary->assertStatus(404);
    $summary->assertJsonPath('code', 'NOT_FOUND');
});

// =====================================================================
// Scenario 5: "periode tanpa data"
// =====================================================================
it('periode tanpa data: 200 with every figure null and reading_count 0 — never 0, and has_data false', function () {
    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/clarification-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();

    foreach (ClarificationReportService::NUMERIC_METRICS as $metric) {
        $summary->assertJsonPath("metrics.{$metric}.min", null);
        $summary->assertJsonPath("metrics.{$metric}.avg", null);
        $summary->assertJsonPath("metrics.{$metric}.max", null);
        $summary->assertJsonPath("metrics.{$metric}.reading_count", 0);
        // The distinction the whole card rests on.
        expect($summary->json("metrics.{$metric}.avg"))->not->toBe(0);
        expect($summary->json("metrics.{$metric}.avg"))->not->toBe(0.0);
    }

    $summary->assertJsonPath('production.total_ton', null);
    $summary->assertJsonPath('production.reading_count', 0);
    $summary->assertJsonPath('downtime.total_mins', null);
    $summary->assertJsonPath('downtime.reading_count', 0);
    $summary->assertJsonPath('coverage.filled_slots', 0);
    $summary->assertJsonPath('has_data', false);
    expect($summary->json('daily'))->toBe([]);
    expect($summary->json('by_unit'))->toBe([]);
});

// =====================================================================
// Scenario 6: "laju produksi tidak pernah tercatat"
// =====================================================================
it('laju tidak pernah tercatat: production null with reading_count 0 while the temperatures keep their own counts', function () {
    laporanClarificationRecord($this->stationA, '2026-03-07', laporanClarificationSludgeRows(9, 87.0));

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/clarification-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();

    // null, never 0,0 ton: nothing was measured, which is not the same as a
    // mill that produced nothing.
    $summary->assertJsonPath('production.total_ton', null);
    $summary->assertJsonPath('production.reading_count', 0);
    $summary->assertJsonPath('production.avg_rate_ton_hour', null);
    expect($summary->json('production.total_ton'))->not->toBe(0);
    expect($summary->json('production.total_ton'))->not->toBe(0.0);

    // Independent per metric — the sludge card is entirely unaffected.
    $summary->assertJsonPath('metrics.sludge_tank_temp_c.reading_count', 9);
    expect($summary->json('metrics.sludge_tank_temp_c.avg'))->toEqual(87.0);
    expect($summary->json('metrics.sludge_tank_temp_c.reading_count'))->toBeGreaterThan(0);
});

// =====================================================================
// Scenario 7: "jam tanpa catatan laju" — THE TRAP THAT HIDES ITSELF
// =====================================================================
it('jam tanpa laju: 10 filled rows and 4 rates give 40,0 ton with reading_count 4 and avg 10,0 — never 4,0', function () {
    laporanClarificationRecord($this->stationA, '2026-03-08', laporanClarificationMixedRows());

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/clarification-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();

    // The total is identical under both readings — which is exactly why the
    // average is the only thing that catches a zero-filling implementation.
    expect($summary->json('production.total_ton'))->toEqual(40.0);
    $summary->assertJsonPath('production.reading_count', 4);
    expect($summary->json('production.avg_rate_ton_hour'))->toEqual(10.0);
    expect($summary->json('production.avg_rate_ton_hour'))->not->toEqual(4.0);
    expect($summary->json('metrics.pure_oil_production_rate_ton_hour.avg'))->toEqual(10.0);
    expect($summary->json('metrics.pure_oil_production_rate_ton_hour.avg'))->not->toEqual(4.0);

    // Ten filled slots against four rate readings: the coverage figure and
    // the reading count disagree ON PURPOSE, and both are published.
    $summary->assertJsonPath('coverage.filled_slots', 10);
    $summary->assertJsonPath('metrics.pure_oil_production_rate_ton_hour.reading_count', 4);
});

// =====================================================================
// Scenario 8: "downtime tercatat bersamaan dengan laju"
// =====================================================================
it('laju dan downtime tidak saling mengurangi: rate 10,0 with downtime 20 stays 10,0 ton', function () {
    laporanClarificationRecord($this->stationA, '2026-03-09', [
        ['pure_oil_production_rate_ton_hour' => 10.0, 'downtime_mins' => 20.0],
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/clarification-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();

    // >> OPEN QUESTION, STILL PENDING THE PROCESS OWNER — not decided here.
    // >> The formula ("sum laju x 1 jam") was fixed by the user during
    // >> scoping. If the rate an Operator types is an INSTANTANEOUS rate
    // >> rather than the mean across the hour, period production comes out
    // >> higher than reality; should the owner ever decide downtime must be
    // >> subtracted, the formula becomes rate x (60 - downtime) / 60 and
    // >> this assertion must change with it.
    expect($summary->json('production.total_ton'))->toEqual(10.0);
    expect($summary->json('production.total_ton'))->not->toEqual(6.67);

    // Which is why downtime is published beside it: without both numbers the
    // production figure cannot be judged at all.
    expect($summary->json('downtime.total_mins'))->toEqual(20);
    $summary->assertJsonPath('downtime.reading_count', 1);
    $summary->assertJsonPath('downtime.hours_with_downtime', 1);
});

// =====================================================================
// Scenario 9: "downtime tidak pernah tercatat" (2 steps, 2 periods)
// =====================================================================
it('downtime null vs nol: the two states are distinguishable from the response, side by side', function () {
    $neverRecorded = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('clarification')
        ->range('2026-05-01', '2026-05-31')->named('Periode Mei Tanpa Downtime')->open()->create();
    $recordedZero = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('clarification')
        ->range('2026-06-01', '2026-06-30')->named('Periode Juni Downtime Nol')->open()->create();

    laporanClarificationRecord($this->stationA, '2026-05-10', laporanClarificationSludgeRows(5, 87.0), [
        'clarification_id' => 'CLF-MEI',
    ]);

    $zeroRows = [];

    for ($index = 0; $index < 5; $index++) {
        $zeroRows[] = ['downtime_mins' => 0.0];
    }

    laporanClarificationRecord($this->stationA, '2026-06-10', $zeroRows, ['clarification_id' => 'CLF-JUN']);

    // Step 1 — never recorded: null, NOT 0. null means "nobody measured".
    $first = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/clarification-reports/summary?'.http_build_query(['period_id' => $neverRecorded->id]));

    $first->assertOk();
    $first->assertJsonPath('downtime.total_mins', null);
    $first->assertJsonPath('downtime.reading_count', 0);
    $first->assertJsonPath('downtime.hours_with_downtime', 0);
    expect($first->json('downtime.total_mins'))->not->toBe(0);

    // Step 2 — recorded, and genuinely zero: 0 with reading_count > 0.
    // 0 means "it never stopped".
    $second = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/clarification-reports/summary?'.http_build_query(['period_id' => $recordedZero->id]));

    $second->assertOk();
    expect($second->json('downtime.total_mins'))->toEqual(0);
    expect($second->json('downtime.total_mins'))->not->toBeNull();
    expect($second->json('downtime.reading_count'))->toBeGreaterThan(0);
    $second->assertJsonPath('downtime.reading_count', 5);
    $second->assertJsonPath('downtime.hours_with_downtime', 0);

    // THE CONTRACT: the two states differ observably on the pair
    // (total_mins, reading_count). Merging them would publish a reliability
    // figure that was never taken.
    expect($first->json('downtime.total_mins'))->not->toBe($second->json('downtime.total_mins'));
    expect($first->json('downtime.reading_count'))->not->toBe($second->json('downtime.reading_count'));
});

// =====================================================================
// Scenario 10: "pencatatan sangat tidak lengkap"
// =====================================================================
it('kelengkapan rendah: 9 of 144 slots is coverage_percent 6.25 and every production figure still comes back', function () {
    $period = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('clarification')
        ->range('2026-04-01', '2026-04-03')->named('Periode Tiga Hari')->open()->create();

    laporanClarificationRecord($this->stationA, '2026-04-01', [
        ['pure_oil_production_rate_ton_hour' => 10.0],
        ['pure_oil_production_rate_ton_hour' => 12.0],
        ['pure_oil_production_rate_ton_hour' => 8.0],
        ['sludge_tank_temp_c' => 87.0],
        ['sludge_tank_temp_c' => 87.0],
        ['sludge_tank_temp_c' => 87.0],
        ['sludge_tank_temp_c' => 87.0],
        ['sludge_tank_temp_c' => 87.0],
        ['sludge_tank_temp_c' => 87.0],
    ], ['clarification_id' => 'CLF-01']);

    // A second unit that filed a record and nothing else — it still counts
    // toward the expected-slot denominator.
    laporanClarificationRecord($this->stationA, '2026-04-02', [], ['clarification_id' => 'CLF-02']);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/clarification-reports/summary?'.http_build_query(['period_id' => $period->id]));

    $summary->assertOk();
    $summary->assertJsonPath('coverage.filled_slots', 9);
    $summary->assertJsonPath('coverage.expected_slots', 144);
    expect($summary->json('coverage.coverage_percent'))->toEqual(6.25);
    $summary->assertJsonPath('coverage.unit_count', 2);
    $summary->assertJsonPath('coverage.days_in_period', 3);

    // The production figures are still published — but never without the
    // reading count that qualifies them, because on THIS screen a gap in the
    // recording lowers the production figure itself.
    expect($summary->json('production.total_ton'))->toEqual(30.0);
    $summary->assertJsonPath('production.reading_count', 3);
});

// =====================================================================
// Scenario 11: "mill punya beberapa unit Clarification"
// =====================================================================
it('beberapa unit: the period figure merges every unit, and a unit with no filled reading still appears', function () {
    laporanClarificationRecord($this->stationA, '2026-03-02', [
        ['pure_oil_production_rate_ton_hour' => 20.0],
    ], ['clarification_id' => 'CLF-01']);
    laporanClarificationRecord($this->stationA, '2026-03-02', [
        ['pure_oil_production_rate_ton_hour' => 15.0],
    ], ['clarification_id' => 'CLF-02']);
    laporanClarificationRecord($this->stationA, '2026-03-03', [[], []], ['clarification_id' => 'CLF-03']);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/clarification-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();
    expect($summary->json('production.total_ton'))->toEqual(35.0);
    $summary->assertJsonCount(3, 'by_unit');

    $byUnit = collect($summary->json('by_unit'))->keyBy('clarification_id');

    expect($byUnit->keys()->all())->toEqualCanonicalizing(['CLF-01', 'CLF-02', 'CLF-03']);
    expect($byUnit['CLF-01']['production_ton'])->toEqual(20.0);
    expect($byUnit['CLF-02']['production_ton'])->toEqual(15.0);

    // Dropping CLF-03 would hide exactly the unit that was never written
    // down — the one worth seeing.
    expect($byUnit['CLF-03']['reading_count'])->toBe(0);
    expect($byUnit['CLF-03']['production_ton'])->toBeNull();
    $summary->assertJsonPath('coverage.unit_count', 3);
});

// =====================================================================
// Scenario 12: "akun belum terhubung ke mill" (2 steps + the SPY)
// =====================================================================
it('akun tanpa mill: 422 VALIDATION_ERROR on periods and on summary, and never the all-mills list', function () {
    $noMillSupervisor = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    // The spy replaces the service the CONTROLLER resolves, so "the list was
    // never built" is proven by a recorded call count of zero rather than
    // inferred from the status code.
    $spy = new LaporanClarificationApiAllBusinessUnitsSpy;
    $this->app->instance(ClarificationReportService::class, $spy);

    // Step 1 — /periods
    $periods = $this->actingAs($noMillSupervisor, 'web')->getJson('/api/clarification-reports/periods');
    $periods->assertStatus(422);
    $periods->assertJsonPath('code', 'VALIDATION_ERROR');
    $periods->assertJsonStructure(['errors' => ['business_unit_id']]);
    expect($periods->json('errors.business_unit_id.0'))->toContain('Hubungi Admin');

    // Step 2 — /summary
    $summary = $this->actingAs($noMillSupervisor, 'web')
        ->getJson('/api/clarification-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));
    $summary->assertStatus(422);
    $summary->assertJsonPath('code', 'VALIDATION_ERROR');
    $summary->assertJsonMissingPath('production');
    $summary->assertJsonMissingPath('metrics');

    // THE DECISIVE ASSERTION: falling back to "every mill" would turn one
    // broken master-data row into a cross-mill leak.
    expect($spy->allBusinessUnitsCalls)->toBe(0);
});

// =====================================================================
// Scenario 13: "mencoba melihat mill lain" (2 steps, two different guards)
// =====================================================================
it('mill lain: the client business_unit_id is ignored (200, own data), but another mill period_id is 403', function () {
    laporanClarificationRecord($this->stationA, '2026-03-10', [['pure_oil_production_rate_ton_hour' => 20.0]]);
    laporanClarificationRecord($this->stationB, '2026-03-10', [
        ['pure_oil_production_rate_ton_hour' => 900.0],
    ], ['clarification_id' => 'CLF-BETA']);

    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('clarification')
        ->range('2026-03-01', '2026-03-31')->named('Periode Maret Beta')->open()->create();

    // Step 1 — naming another mill with an OWN period: 200 with own data.
    // Deliberately not 403 — a 403 would confirm Mill Beta exists, and there
    // is no access attempt to refuse because the parameter is never used.
    $summary = $this->actingAs($this->supervisor, 'web')->getJson('/api/clarification-reports/summary?'.http_build_query([
        'business_unit_id' => $this->businessUnitB->id,
        'period_id' => $this->periodA->id,
    ]));

    $summary->assertOk();
    $summary->assertJsonPath('period.business_unit_name', 'Mill Alpha');
    expect($summary->json('production.total_ton'))->toEqual(20.0);
    expect($summary->json('metrics.pure_oil_production_rate_ton_hour.max'))->not->toEqual(900.0);

    // Step 2 — another mill's PERIOD ID is a hard 403: a concrete handle to
    // another mill's data.
    $forbidden = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/clarification-reports/summary?'.http_build_query(['period_id' => $periodB->id]));
    $forbidden->assertStatus(403);
    $forbidden->assertJsonPath('code', 'FORBIDDEN');
    $forbidden->assertJsonMissingPath('production');
    $forbidden->assertJsonMissingPath('metrics');

    // And the export over the same period is refused identically.
    $export = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/clarification-reports/export?'.http_build_query(['period_id' => $periodB->id]));
    $export->assertStatus(403);
    $export->assertJsonPath('code', 'FORBIDDEN');
});

// =====================================================================
// Scenario 14: "Operator membuka laporan" (screen-138, mobile)
//
// WIDENED 2026-09-25. This scenario used to be "Operator mencoba membuka
// layar web ini" and asserted 403 on all four endpoints, because the mobile
// Clarification report did not exist. It exists now (screen-138), and the
// four /api/clarification-reports/* routes admit Operator.
//
// THE STATUS CODES ARE THE CHEAP HALF. A service that admitted Operator in
// guardAccess() only — leaving it to fall through resolveBusinessUnit() into
// the unbound ADMIN branch, where the client's business_unit_id is HONOURED
// — would answer 200 on every step below and pass a status-only test while
// serving any mill an Operator cares to name. So every step here asserts the
// CONTENT: Mill Beta's id is sent deliberately, and Mill Alpha's figures are
// what must come back.
// =====================================================================
it('operator: 200 on periods/summary/export bound to its OWN mill, 403 on the mill picker, and 401 with no session at all', function () {
    // The no-session step is run FIRST, on purpose: actingAs() persists for
    // the rest of the test case, so a guest request made after it would
    // silently be an authenticated one.
    $this->getJson('/api/clarification-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]))
        ->assertStatus(401);

    // Two mills, told apart by CLARIFICATION UNIT ID as well as by figures:
    // the assertions below have to be able to say "this is Mill Alpha's
    // data" out of the payload itself, not merely "the status was 200".
    laporanClarificationRecord($this->stationA, '2026-03-10', [
        ['pure_oil_production_rate_ton_hour' => 20.0, 'sludge_tank_temp_c' => 87.0],
    ], ['clarification_id' => 'CLF-ALPHA']);

    laporanClarificationRecord($this->stationB, '2026-03-10', [
        ['pure_oil_production_rate_ton_hour' => 900.0, 'sludge_tank_temp_c' => 300.0],
    ], ['clarification_id' => 'CLF-BETA']);

    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('clarification')
        ->range('2026-03-01', '2026-03-31')->named('Periode Maret Beta')->open()->create();

    // Step 1 — GET /periods: 200, and the list is the Operator's own mill's
    // periods only. No business_unit_id is sent: it comes from the account.
    $periods = $this->actingAs($this->operator, 'web')->getJson('/api/clarification-reports/periods');
    $periods->assertOk();
    $periods->assertJsonStructure([
        'data' => [['id', 'name', 'start_date', 'end_date', 'status', 'station_type', 'station_type_label']],
    ]);

    $periodIds = array_column($periods->json('data'), 'id');
    expect($periodIds)->toContain($this->periodA->id);
    expect($periodIds)->not->toContain($periodB->id);

    // Step 2 — GET /summary while NAMING ANOTHER MILL: 200 carrying the
    // caller's OWN data, deliberately not 403 (a 403 would confirm Mill Beta
    // exists, and there is no access attempt to refuse because the parameter
    // is never used for a mill-bound role).
    //
    // THIS IS THE ASSERTION THAT LOCKS resolveBusinessUnit(). The Admin-branch
    // leak answers 200 here too — only the CONTENT tells the two apart, so
    // the discriminating figures are asserted one by one: 900.0 ton/hour and
    // 300.0 °C exist ONLY in Mill Beta.
    $summary = $this->actingAs($this->operator, 'web')->getJson('/api/clarification-reports/summary?'.http_build_query([
        'business_unit_id' => $this->businessUnitB->id,
        'period_id' => $this->periodA->id,
    ]));

    $summary->assertOk();
    $summary->assertJsonPath('period.business_unit_name', 'Mill Alpha');
    $summary->assertJsonPath('business_unit.name', 'Mill Alpha');

    expect(array_column($summary->json('by_unit'), 'clarification_id'))->toBe(['CLF-ALPHA']);
    expect(array_column($summary->json('by_unit'), 'clarification_id'))->not->toContain('CLF-BETA');
    expect($summary->json('production.total_ton'))->toEqual(20.0);
    expect($summary->json('production.total_ton'))->not->toEqual(920.0);
    expect($summary->json('production.reading_count'))->toBe(1);
    expect($summary->json('metrics.pure_oil_production_rate_ton_hour.max'))->toEqual(20.0);
    expect($summary->json('metrics.pure_oil_production_rate_ton_hour.max'))->not->toEqual(900.0);
    expect($summary->json('metrics.sludge_tank_temp_c.max'))->toEqual(87.0);
    expect($summary->json('metrics.sludge_tank_temp_c.max'))->not->toEqual(300.0);
    // The blunt instrument, on the whole payload: Mill Beta's NAME must not
    // appear anywhere in it, under any key.
    expect(json_encode($summary->json()))->not->toContain('Mill Beta');

    // Step 3 — GET /export: 200, and the streamed CSV carries the Operator's
    // own mill's rows, not the other mill's.
    $export = $this->actingAs($this->operator, 'web')
        ->get('/api/clarification-reports/export?'.http_build_query([
            'period_id' => $this->periodA->id,
            'format' => 'csv',
        ]));

    $export->assertOk();

    $csv = laporanClarificationStreamed($export->baseResponse);
    expect($csv)->toContain('CLF-ALPHA');
    expect($csv)->not->toContain('CLF-BETA');

    // Step 4 — ANOTHER MILL'S PERIOD ID is still a hard 403: a concrete
    // handle on another mill's data, refused outright rather than silently
    // rewritten. authorizePeriod() needed no change for this widening —
    // only Admin is unbound there.
    $forbidden = $this->actingAs($this->operator, 'web')
        ->getJson('/api/clarification-reports/summary?'.http_build_query(['period_id' => $periodB->id]));
    $forbidden->assertStatus(403);
    $forbidden->assertJsonPath('code', 'FORBIDDEN');
    $forbidden->assertJsonMissingPath('production');
    $forbidden->assertJsonMissingPath('metrics');

    $forbiddenExport = $this->actingAs($this->operator, 'web')
        ->getJson('/api/clarification-reports/export?'.http_build_query(['period_id' => $periodB->id]));
    $forbiddenExport->assertStatus(403);
    $forbiddenExport->assertJsonPath('code', 'FORBIDDEN');

    // Step 5 — an Operator whose account has no mill FAILS CLOSED with 422,
    // exactly like the other mill-bound roles: never the all-mills list.
    $noMillOperator = User::factory()->role(UserRole::Operator)->create(['business_unit_id' => null]);

    $noMillPeriods = $this->actingAs($noMillOperator, 'web')->getJson('/api/clarification-reports/periods');
    $noMillPeriods->assertStatus(422);
    $noMillPeriods->assertJsonPath('code', 'VALIDATION_ERROR');
    $noMillPeriods->assertJsonStructure(['errors' => ['business_unit_id']]);
    expect($noMillPeriods->json('errors.business_unit_id.0'))->toContain('Hubungi Admin');
    $noMillPeriods->assertJsonMissingPath('data');

    $noMillSummary = $this->actingAs($noMillOperator, 'web')
        ->getJson('/api/clarification-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));
    $noMillSummary->assertStatus(422);
    $noMillSummary->assertJsonPath('code', 'VALIDATION_ERROR');
    $noMillSummary->assertJsonMissingPath('production');
    $noMillSummary->assertJsonMissingPath('metrics');

    // Step 6 — the mill picker STAYS 403 for Operator. It is the ADMIN
    // picker; an Operator bound to its own mill has no use for the list of
    // every mill, and handing it over would be the very leak the widening
    // avoided. Raised by the SERVICE now rather than by EnsureRole, so it
    // carries code = 'FORBIDDEN' — asserting the status alone would no
    // longer distinguish the two layers.
    $picker = $this->actingAs($this->operator, 'web')
        ->getJson('/api/clarification-reports/business-units/options');
    $picker->assertStatus(403);
    $picker->assertJsonPath('code', 'FORBIDDEN');
    $picker->assertJsonMissingPath('data');
    expect(json_encode($picker->json()))->not->toContain('Mill Beta');
});

// =====================================================================
// Scenario 15: "periode tertutup" (2 steps)
// =====================================================================
it('periode tertutup: summary is complete with status closed, and the export still runs', function () {
    $closed = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('clarification')
        ->range('2026-05-01', '2026-05-31')->named('Periode Mei Tertutup')->closed()->create();

    laporanClarificationRecord($this->stationA, '2026-05-10', [
        ['pure_oil_production_rate_ton_hour' => 10.0, 'sludge_tank_temp_c' => 87.0],
        ['pure_oil_production_rate_ton_hour' => 12.0, 'sludge_tank_temp_c' => 88.0],
    ], ['clarification_id' => 'CLF-MEI']);

    // Step 1 — the period lock governs writing data, not reading a report.
    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/clarification-reports/summary?'.http_build_query(['period_id' => $closed->id]));

    $summary->assertOk();
    $summary->assertJsonPath('period.status', 'closed');
    expect($summary->json('production.total_ton'))->toEqual(22.0);
    $summary->assertJsonPath('production.reading_count', 2);
    $summary->assertJsonPath('metrics.sludge_tank_temp_c.reading_count', 2);

    // Step 2 — /export?period_id={{step1.data.period.id}} is not blocked.
    $export = $this->actingAs($this->supervisor, 'web')
        ->get('/api/clarification-reports/export?'.http_build_query(['period_id' => $summary->json('period.id')]));

    $export->assertOk();

    $lines = array_values(array_filter(explode("\n", trim(laporanClarificationStreamed($export->baseResponse)))));
    expect($lines)->toHaveCount(3);
});

// =====================================================================
// Scenario 16: "rekap harian panjang"
// =====================================================================
it('rekap panjang: daily carries one entry per dated record while the headline figures stay one object', function () {
    foreach (range(1, 20) as $day) {
        $date = sprintf('2026-03-%02d', $day);
        laporanClarificationRecord($this->stationA, $date, [
            ['pure_oil_production_rate_ton_hour' => 10.0 + $day, 'sludge_tank_temp_c' => 80.0 + $day],
        ], ['clarification_id' => 'CLF-01']);
    }

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/clarification-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();
    $summary->assertJsonCount(20, 'daily');
    $summary->assertJsonPath('total.days_with_records', 20);
    $summary->assertJsonPath('production.reading_count', 20);

    // However long the recap gets, the summary numbers stay top-level — the
    // screen can close the table without losing them.
    expect($summary->json('production.total_ton'))->not->toBeNull();
    expect($summary->json('coverage.filled_slots'))->toBe(20);
});

// =====================================================================
// Scenario 17: "produksi diturunkan dari laju dan jumlah pembacaannya ditampilkan"
// =====================================================================
it('produksi turunan: 10,0 + 12,5 + 8,0 + 9,5 = 40,0 ton with reading_count 4, summed from the rate column', function () {
    laporanClarificationRecord($this->stationA, '2026-03-11', [
        ['pure_oil_production_rate_ton_hour' => 10.0],
        ['pure_oil_production_rate_ton_hour' => 12.5],
        ['pure_oil_production_rate_ton_hour' => 8.0],
        ['pure_oil_production_rate_ton_hour' => 9.5],
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/clarification-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();
    expect($summary->json('production.total_ton'))->toEqual(40.0);
    $summary->assertJsonPath('production.reading_count', 4);

    // The reading count is never optional: a total from 4 readings and a
    // total from 400 must not look equally convincing.
    expect($summary->json('production'))->toHaveKey('reading_count');
    expect($summary->json('production.reading_count'))->not->toBeNull();

    // It can only have come from summing the rate column, because the schema
    // has no production column to read.
    expect($summary->json('metrics.pure_oil_production_rate_ton_hour.reading_count'))->toBe(4);
    expect(array_sum([10.0, 12.5, 8.0, 9.5]))->toEqual($summary->json('production.total_ton'));
});

// =====================================================================
// Scenario 18: "setiap metrik punya penyebutnya sendiri"
// =====================================================================
it('penyebut terpisah: reading counts 4 / 9 / 6 / 3 with every average over its own denominator', function () {
    laporanClarificationRecord($this->stationA, '2026-03-12', laporanClarificationMixedRows());

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/clarification-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();

    $summary->assertJsonPath('metrics.pure_oil_production_rate_ton_hour.reading_count', 4);
    $summary->assertJsonPath('metrics.sludge_tank_temp_c.reading_count', 9);
    $summary->assertJsonPath('metrics.downtime_mins.reading_count', 6);
    $summary->assertJsonPath('metrics.buffer_tank_level_percent.reading_count', 3);
    $summary->assertJsonPath('metrics.clarification_tank_temp_c.reading_count', 7);
    $summary->assertJsonPath('metrics.oil_tank_temperature_c.reading_count', 5);

    // Every average over ITS OWN denominator, never the shared 10.
    expect($summary->json('metrics.pure_oil_production_rate_ton_hour.avg'))->toEqual(10.0);
    expect($summary->json('metrics.sludge_tank_temp_c.avg'))->toEqual(87.0);
    expect($summary->json('metrics.pure_oil_production_rate_ton_hour.avg'))->not->toEqual(4.0);
    expect($summary->json('metrics.sludge_tank_temp_c.avg'))->not->toEqual(78.3);

    // No shared global reading_count exists anywhere in the payload.
    expect($summary->json('metrics'))->not->toHaveKey('reading_count');
    expect($summary->json())->not->toHaveKey('reading_count');
});

// =====================================================================
// Scenario 19: "rentang periode inklusif di kedua ujung" (2 steps)
// =====================================================================
it('rentang inklusif: both bounds are counted, the days either side are not, and the export agrees', function () {
    $period = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('clarification')
        ->range('2026-03-01', '2026-03-05')->named('Periode Awal Maret')->open()->create();

    laporanClarificationRecord($this->stationA, '2026-03-01', [
        ['pure_oil_production_rate_ton_hour' => 10.0],
    ], ['clarification_id' => 'CLF-01']);
    laporanClarificationRecord($this->stationA, '2026-03-05', [
        ['pure_oil_production_rate_ton_hour' => 20.0],
    ], ['clarification_id' => 'CLF-01']);
    laporanClarificationRecord($this->stationA, '2026-02-28', [
        ['pure_oil_production_rate_ton_hour' => 999.0],
    ], ['clarification_id' => 'CLF-OUT-A']);
    laporanClarificationRecord($this->stationA, '2026-03-06', [
        ['pure_oil_production_rate_ton_hour' => 888.0],
    ], ['clarification_id' => 'CLF-OUT-B']);

    // Step 1 — summary.
    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/clarification-reports/summary?'.http_build_query(['period_id' => $period->id]));

    $summary->assertOk();

    $dates = collect($summary->json('daily'))->pluck('date')->all();

    expect($dates)->toContain('2026-03-01');
    expect($dates)->toContain('2026-03-05');
    expect($dates)->not->toContain('2026-02-28');
    expect($dates)->not->toContain('2026-03-06');

    expect($summary->json('production.total_ton'))->toEqual(30.0);
    expect($summary->json('metrics.pure_oil_production_rate_ton_hour.max'))->toEqual(20.0);

    // Step 2 — the CSV agrees with the page.
    $export = $this->actingAs($this->supervisor, 'web')
        ->get('/api/clarification-reports/export?'.http_build_query(['period_id' => $period->id]));

    $export->assertOk();

    $csv = laporanClarificationStreamed($export->baseResponse);

    expect($csv)->toContain('2026-03-01');
    expect($csv)->toContain('2026-03-05');
    expect($csv)->not->toContain('2026-02-28');
    expect($csv)->not->toContain('2026-03-06');
});

// =====================================================================
// Scenario 20: "ketiga suhu tangki pada satu grafik"
// =====================================================================
it('tiga suhu satu grafik: every daily[] element carries all three temperatures on one comparable object', function () {
    laporanClarificationRecord($this->stationA, '2026-03-15', [
        ['clarification_tank_temp_c' => 93.0, 'oil_tank_temperature_c' => 97.0, 'sludge_tank_temp_c' => 87.0],
        ['clarification_tank_temp_c' => 95.0, 'oil_tank_temperature_c' => 99.0, 'sludge_tank_temp_c' => 89.0],
    ]);
    laporanClarificationRecord($this->stationA, '2026-03-16', [
        ['clarification_tank_temp_c' => 91.0, 'oil_tank_temperature_c' => 95.0, 'sludge_tank_temp_c' => 85.0],
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/clarification-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();
    $summary->assertJsonCount(2, 'daily');

    foreach ($summary->json('daily') as $row) {
        // One object, one axis. Three separate payload shapes would invite
        // three charts, which satisfies "tren suhu antar tangki" literally
        // while destroying its point — the GAP between the tanks.
        expect($row)->toHaveKey('date');
        expect($row)->toHaveKeys([
            'clarification_tank_temp_avg', 'oil_tank_temperature_avg', 'sludge_tank_temp_avg',
        ]);
        expect($row['oil_tank_temperature_avg'])->toBeGreaterThan($row['clarification_tank_temp_avg']);
        expect($row['clarification_tank_temp_avg'])->toBeGreaterThan($row['sludge_tank_temp_avg']);
    }

    $first = $summary->json('daily.0');

    expect($first['clarification_tank_temp_avg'])->toEqual(94.0);
    expect($first['oil_tank_temperature_avg'])->toEqual(98.0);
    expect($first['sludge_tank_temp_avg'])->toEqual(88.0);
});

// =====================================================================
// Scenario 21: "tidak ada penandaan nilai di luar batas"
// =====================================================================
it('tanpa ambang: extreme readings come back as-is, with no threshold/outlier/severity key anywhere', function () {
    laporanClarificationRecord($this->stationA, '2026-03-18', [
        ['sludge_tank_temp_c' => 250.0, 'buffer_tank_level_percent' => 0.5],
        ['sludge_tank_temp_c' => 87.0, 'buffer_tank_level_percent' => 72.0],
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/clarification-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();

    // The values come back untouched — they are simply not judged.
    expect($summary->json('metrics.sludge_tank_temp_c.max'))->toEqual(250.0);
    expect($summary->json('metrics.buffer_tank_level_percent.min'))->toEqual(0.5);

    // Clarification has NO operational-target master, so any threshold here
    // would be a statistic dressed up as a PROCESS limit. The absence is
    // asserted BY NAME — a "do not flag" rule only survives if something
    // guards it.
    $encoded = strtolower($summary->getContent());

    foreach ([
        'threshold', 'target', 'limit', 'outlier', 'iqr', 'fence',
        'is_danger', 'is_warning', 'severity', 'status_flag',
        'is_out_of_range', 'out_of_range', 'alert', 'violation', 'danger',
    ] as $forbidden) {
        expect($encoded)->not->toContain($forbidden);
    }
});

// =====================================================================
// Scenario 22: "layar hanya membaca" (4 steps)
// =====================================================================
it('baca saja: repeated reads change nothing, and no write verb is routed on the prefix', function () {
    laporanClarificationRecord($this->stationA, '2026-03-10', laporanClarificationMixedRows());

    $recordsBefore = ClarificationRecord::count();
    $detailsBefore = ClarificationDetail::count();
    $checksumBefore = ClarificationDetail::query()->orderBy('id')->get()->toJson();

    // Step 1 — /periods.
    $periods = $this->actingAs($this->supervisor, 'web')->getJson('/api/clarification-reports/periods');
    $periods->assertOk();

    // Steps 2 and 3 — /summary twice.
    $first = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/clarification-reports/summary?'.http_build_query(['period_id' => $periods->json('data.0.id')]));
    $first->assertOk();

    $second = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/clarification-reports/summary?'.http_build_query(['period_id' => $first->json('period.id')]));
    $second->assertOk();

    expect($second->json())->toEqual($first->json());

    // Step 4 — /export.
    $this->actingAs($this->supervisor, 'web')
        ->get('/api/clarification-reports/export?'.http_build_query(['period_id' => $first->json('period.id')]))
        ->assertOk();

    expect(ClarificationRecord::count())->toBe($recordsBefore);
    expect(ClarificationDetail::count())->toBe($detailsBefore);
    expect(ClarificationDetail::query()->orderBy('id')->get()->toJson())->toBe($checksumBefore);

    // Every mutating verb on the prefix is simply not registered — a report
    // must not expose any path that could alter the data it reports on.
    foreach (['postJson', 'putJson', 'patchJson', 'deleteJson'] as $verb) {
        foreach ([
            '/api/clarification-reports/summary',
            '/api/clarification-reports/periods',
            '/api/clarification-reports/export',
            '/api/clarification-reports/business-units/options',
        ] as $path) {
            $response = $this->actingAs($this->supervisor, 'web')->{$verb}($path, []);

            expect($response->getStatusCode())->toBeIn([404, 405]);
        }
    }
});

// =====================================================================
// Scenario 23: "daftar periode hanya yang mencakup Clarification"
// =====================================================================
it('daftar periode: hanya yang punya baris period_stations clarification yang ditawarkan', function () {
    $supervisorB = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitB)->create();

    $clarification = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('clarification')
        ->range('2026-05-01', '2026-05-31')->named('Periode Clarification')->open()->create();
    $allTypes = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType(null)
        ->range('2026-06-01', '2026-06-30')->named('Periode Semua Stasiun')->open()->create();
    $sterilizer = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('sterilizer')
        ->range('2026-07-01', '2026-07-31')->named('Periode Sterilizer')->open()->create();
    $boilerRoom = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('boiler-room')
        ->range('2026-08-01', '2026-08-31')->named('Periode Boiler Room')->open()->create();

    $periods = $this->actingAs($supervisorB, 'web')->getJson('/api/clarification-reports/periods');
    $periods->assertOk();
    $periods->assertJsonCount(2, 'data');

    $ids = collect($periods->json('data'))->pluck('id')->all();

    expect($ids)->toContain((string) $clarification->id);
    expect($ids)->toContain((string) $allTypes->id);
    expect($ids)->not->toContain((string) $sterilizer->id);
    expect($ids)->not->toContain((string) $boilerRoom->id);

    // Newest first.
    expect($ids[0])->toBe((string) $allTypes->id);

    // KONTRAK API TETAP DATAR setelah pemisahan periods/period_stations
    // (2026-09-25): stationType(null) kini berarti "satu baris per jenis
    // stasiun", jadi opsi ini adalah pasangan (periode, clarification) —
    // station_type selalu terisi dan label 'Semua Stasiun' sudah tidak ada.
    $all = collect($periods->json('data'))->firstWhere('id', (string) $allTypes->id);

    expect($all['station_type'])->toBe('clarification');
    expect($all['station_type_label'])->toBe('Clarification');
});

// =====================================================================
// Scenario (BARU 2026-09-26): periode tanpa baris period_stations untuk
// clarification tidak boleh muncul — perilaku yang DULU dijamin cabang
// orWhereNull('station_type') pada listPeriods() dan kini sengaja dibuang.
// Cakupan "semua stasiun" hanya ada lewat ADANYA baris per jenis stasiun.
// =====================================================================
it('daftar periode: periode tanpa baris clarification tidak ditawarkan', function () {
    $supervisorB = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitB)->create();

    $otherTypesOnly = Period::factory()->forBusinessUnit($this->businessUnitB)
        ->stationTypes(['sterilizer', 'boiler-room'])
        ->range('2026-05-01', '2026-05-31')->named('Periode Tanpa Clarification')->open()->create();
    $noStations = Period::factory()->forBusinessUnit($this->businessUnitB)
        ->noStations()->range('2026-06-01', '2026-06-30')->named('Periode Tanpa Stasiun')->create();

    $periods = $this->actingAs($supervisorB, 'web')->getJson('/api/clarification-reports/periods');
    $periods->assertOk();

    $ids = collect($periods->json('data'))->pluck('id')->all();

    expect($ids)->not->toContain((string) $otherTypesOnly->id);
    expect($ids)->not->toContain((string) $noStations->id);
});

// =====================================================================
// Scenario (BARU 2026-09-26): status yang dilaporkan adalah status STASIUN
// INI di dalam periode itu, bukan status periode — periode tidak punya status
// lagi. Bentuk ini (Clarification terbuka sementara Sterilizer tertutup di periode
// yang sama) sebelumnya mustahil dinyatakan.
// =====================================================================
it('daftar periode: status yang ditampilkan adalah status clarification, bukan status stasiun lain di periode yang sama', function () {
    $supervisorB = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitB)->create();

    $period = Period::factory()->forBusinessUnit($this->businessUnitB)
        ->noStations()->range('2026-05-01', '2026-05-31')->named('Periode Campuran')->create();

    PeriodStation::factory()->forPeriod($period)->stationType('clarification')->open()->create();
    PeriodStation::factory()->forPeriod($period)->stationType('sterilizer')->closed()->create();

    $periods = $this->actingAs($supervisorB, 'web')->getJson('/api/clarification-reports/periods');
    $periods->assertOk();

    expect(collect($periods->json('data'))->firstWhere('id', (string) $period->id)['status'])->toBe('open');
});

// =====================================================================
// Endpoint error codes not reachable from a BDD scenario
// =====================================================================

it('summary: 422 VALIDATION_ERROR when period_id is missing', function () {
    $response = $this->actingAs($this->supervisor, 'web')->getJson('/api/clarification-reports/summary');

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'VALIDATION_ERROR');
    $response->assertJsonStructure(['errors' => ['period_id']]);
});

it('export: 422 when period_id is missing, 404 when it does not exist, 422 for an unsupported format', function () {
    $this->actingAs($this->supervisor, 'web')->getJson('/api/clarification-reports/export')->assertStatus(422);

    $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/clarification-reports/export?'.http_build_query(['period_id' => (string) Str::uuid()]))
        ->assertStatus(404);

    $badFormat = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/clarification-reports/export?'.http_build_query([
            'period_id' => $this->periodA->id,
            'format' => 'xlsx',
        ]));

    $badFormat->assertStatus(422);
    $badFormat->assertJsonPath('code', 'VALIDATION_ERROR');
    $badFormat->assertJsonStructure(['errors' => ['format']]);
});

it('export: 422 EXPORT_FAILED above 50.000 detail rows, refused EAGERLY rather than as an empty stream', function () {
    $huge = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('clarification')
        ->range('2026-06-01', '2026-06-30')->named('Periode Juni Raksasa')->open()->create();

    laporanClarificationBulkSeed(
        $this->stationA,
        $this->supervisor,
        '2026-06',
        ClarificationReportService::EXPORT_ROW_LIMIT + 1,
    );

    $export = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/clarification-reports/export?'.http_build_query(['period_id' => $huge->id]));

    // A 422 with a body — NOT a 200 carrying an empty file, which is what a
    // lazily-guarded export would produce.
    $export->assertStatus(422);
    $export->assertJsonPath('code', 'EXPORT_FAILED');
    expect($export->headers->get('Content-Type'))->not->toContain('text/csv');
});

it('business-units/options: 403 FORBIDDEN for Supervisor and Mill Management — Admin only', function () {
    // Refused by the service, so this 403 does carry the code.
    $supervisor = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/clarification-reports/business-units/options');
    $supervisor->assertStatus(403);
    $supervisor->assertJsonPath('code', 'FORBIDDEN');

    $millManagement = $this->actingAs($this->millManagement, 'web')
        ->getJson('/api/clarification-reports/business-units/options');
    $millManagement->assertStatus(403);
    $millManagement->assertJsonPath('code', 'FORBIDDEN');
});

it('rejects unauthenticated requests on every endpoint', function () {
    $this->getJson('/api/clarification-reports/business-units/options')->assertStatus(401);
    $this->getJson('/api/clarification-reports/periods')->assertStatus(401);
    $this->getJson('/api/clarification-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]))
        ->assertStatus(401);
    $this->getJson('/api/clarification-reports/export?'.http_build_query(['period_id' => $this->periodA->id]))
        ->assertStatus(401);
});

// =====================================================================
// REACHABILITY — the one line without which this whole screen is dead code
//
// StationReportService::REPORT_ROUTES is the single source of truth for
// report_available / report_path on screen-140 (/reports). Without the
// 'clarification' entry the report exists, every test in this file passes,
// and the tile stays greyed out.
//
// The ORDER matters as much as the presence: screen-140's own API test
// compares the available codes (returned in station_types.sort_order)
// against array_keys(REPORT_ROUTES) with a strict, ordered toBe(), and
// clarification (sort_order 70) sits between sterilizer (40) and
// boiler-room (90). This asserts the position RELATIVE to its neighbours
// rather than hardcoding the whole list, so adding the next station report
// does not falsely redden this one.
// =====================================================================
it('reachability: REPORT_ROUTES maps clarification, positioned between sterilizer and boiler-room', function () {
    $codes = array_keys(StationReportService::REPORT_ROUTES);

    expect($codes)->toContain('clarification');
    expect(StationReportService::REPORT_ROUTES['clarification'])->toBe('reports.clarification');

    $clarification = array_search('clarification', $codes, true);
    $sterilizer = array_search('sterilizer', $codes, true);
    $boilerRoom = array_search('boiler-room', $codes, true);

    expect($sterilizer)->toBeLessThan($clarification);
    expect($clarification)->toBeLessThan($boilerRoom);

    // And the endpoint the tile grid reads agrees, in the same order.
    $stations = $this->actingAs($this->supervisor, 'web')->getJson('/api/station-reports/stations');
    $stations->assertOk();

    $available = collect($stations->json('data.stations'))
        ->where('report_available', true)
        ->pluck('code')
        ->values()
        ->all();

    expect($available)->toBe($codes);

    $clarificationTile = collect($stations->json('data.stations'))->firstWhere('code', 'clarification');

    expect($clarificationTile['report_available'])->toBeTrue();
    expect($clarificationTile['report_path'])->not->toBeNull();
});

// =====================================================================
// PRODUCTION LINE — parameter permintaan `production_line_id` (2026-09-28)
//
// OPSIONAL DAN ADITIF, dengan sengaja: endpoint ini dibaca layar web DAN
// layar mobile, dan mewajibkannya sekarang akan mematahkan mobile sebelum ia
// sempat menumbuhkan pemilihnya. Tanpa parameter ini jawabannya persis
// seperti sebelum perubahan — itulah yang diasersikan skenario terakhir di
// bawah. Bentuk respons tidak berubah; ia hanya BERTAMBAH satu kunci
// `production_line`.
//
// Penyaringan dibuat DUA ARAH — data line terpilih ADA, data line lain TIDAK
// ADA — karena SQLite memperlakukan kolom yang tidak ada sebagai string
// literal dan akan menghijaukan filter yang menyaring habis.
// =====================================================================

function laporanClarificationApiSecondLine(BusinessUnit $businessUnit): Station
{
    $line = ProductionLine::factory()->create([
        'business_unit_id' => $businessUnit->id,
        'name' => 'Line Kedua',
    ]);

    return Station::factory()->forProductionLine($line)->clarification()->create();
}

it('production_line_id: menyaring ringkasan ke satu line, dua arah', function () {
    laporanClarificationRecord($this->stationA, '2026-03-05',
        laporanClarificationSludgeRows(2, 87.0), ['clarification_id' => 'CLF-LINE-A']);

    $stationC = laporanClarificationApiSecondLine($this->businessUnitA);
    $lineC = (string) $stationC->production_line_id;
    laporanClarificationRecord($stationC, '2026-03-06',
        laporanClarificationSludgeRows(5, 60.0), ['clarification_id' => 'CLF-LINE-C']);

    $lineA = (string) $this->stationA->production_line_id;
    $periodId = (string) $this->periodA->id;

    $a = $this->actingAs($this->supervisor, 'web')->getJson('/api/clarification-reports/summary?'.http_build_query([
        'period_id' => $periodId,
        'production_line_id' => $lineA,
    ]));

    $a->assertOk();
    expect($a->json('coverage.filled_slots'))->toBe(2);
    expect($a->json('metrics.sludge_tank_temp_c.avg'))->toEqual(87.0);

    $c = $this->actingAs($this->supervisor, 'web')->getJson('/api/clarification-reports/summary?'.http_build_query([
        'period_id' => $periodId,
        'production_line_id' => $lineC,
    ]));

    $c->assertOk();
    expect($c->json('coverage.filled_slots'))->toBe(5);
    expect($c->json('metrics.sludge_tank_temp_c.avg'))->toEqual(60.0);
});

it('production_line_id: menambah blok production_line tanpa mengubah satu pun kunci yang sudah ada', function () {
    laporanClarificationRecord($this->stationA, '2026-03-05',
        laporanClarificationSludgeRows(2, 87.0), ['clarification_id' => 'CLF-LINE-A']);

    $lineA = (string) $this->stationA->production_line_id;
    $periodId = (string) $this->periodA->id;

    $tanpa = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/clarification-reports/summary?'.http_build_query(['period_id' => $periodId]));

    $dengan = $this->actingAs($this->supervisor, 'web')->getJson('/api/clarification-reports/summary?'.http_build_query([
        'period_id' => $periodId,
        'production_line_id' => $lineA,
    ]));

    $tanpa->assertOk();
    $dengan->assertOk();

    // Satu-satunya kunci baru, dan ia null ketika parameternya tidak dikirim.
    expect($tanpa->json('production_line'))->toBeNull();
    expect($dengan->json('production_line'))->toBe([
        'id' => $lineA,
        'name' => ProductionLine::findOrFail($lineA)->name,
    ]);

    // Kunci teratas yang sudah ada tetap sama persis, dalam urutan yang sama.
    $lama = array_values(array_diff(array_keys($tanpa->json()), ['production_line']));
    $baru = array_values(array_diff(array_keys($dengan->json()), ['production_line']));

    expect($baru)->toBe($lama);
});

it('production_line_id: line mill lain tidak pernah memulangkan data mill itu', function () {
    laporanClarificationRecord($this->stationB, '2026-03-05',
        laporanClarificationSludgeRows(4, 99.0), ['clarification_id' => 'CLF-MILL-B']);

    $lineB = (string) $this->stationB->production_line_id;

    $response = $this->actingAs($this->supervisor, 'web')->getJson('/api/clarification-reports/summary?'.http_build_query([
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $lineB,
    ]));

    // Cakupan mill sudah ditegakkan lebih dulu, jadi menyaring ke line mill
    // lain menghasilkan laporan KOSONG — bukan data Mill Beta.
    $response->assertOk();
    expect($response->json('coverage.filled_slots'))->toBe(0);
    expect($response->json('has_data'))->toBeFalse();
});

it('production_line_id: ekspor ikut tersaring ke line terpilih', function () {
    laporanClarificationRecord($this->stationA, '2026-03-05',
        laporanClarificationSludgeRows(2, 87.0), ['clarification_id' => 'CLF-LINE-A']);

    $stationC = laporanClarificationApiSecondLine($this->businessUnitA);
    $lineC = (string) $stationC->production_line_id;
    laporanClarificationRecord($stationC, '2026-03-06',
        laporanClarificationSludgeRows(5, 60.0), ['clarification_id' => 'CLF-LINE-C']);

    $lineA = (string) $this->stationA->production_line_id;
    $periodId = (string) $this->periodA->id;

    $a = $this->actingAs($this->supervisor, 'web')->get('/api/clarification-reports/export?'.http_build_query([
        'period_id' => $periodId,
        'format' => 'csv',
        'production_line_id' => $lineA,
    ]));

    $a->assertOk();

    $bodyA = $a->streamedContent();

    expect($bodyA)->toContain('CLF-LINE-A');
    expect($bodyA)->not->toContain('CLF-LINE-C');

    $c = $this->actingAs($this->supervisor, 'web')->get('/api/clarification-reports/export?'.http_build_query([
        'period_id' => $periodId,
        'format' => 'csv',
        'production_line_id' => $lineC,
    ]));

    $c->assertOk();

    $bodyC = $c->streamedContent();

    expect($bodyC)->toContain('CLF-LINE-C');
    expect($bodyC)->not->toContain('CLF-LINE-A');
});

it('production_line_id: record yang stasiunnya sudah dipindah tetap terhitung di line asalnya', function () {
    laporanClarificationRecord($this->stationA, '2026-03-05',
        laporanClarificationSludgeRows(2, 87.0), ['clarification_id' => 'CLF-LINE-A']);

    $lineAsal = (string) $this->stationA->production_line_id;

    $lineBaru = ProductionLine::factory()->create([
        'business_unit_id' => $this->businessUnitA->id,
        'name' => 'Line Baru',
    ]);

    $this->stationA->update(['production_line_id' => $lineBaru->id]);

    $asal = $this->actingAs($this->supervisor, 'web')->getJson('/api/clarification-reports/summary?'.http_build_query([
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $lineAsal,
    ]));

    $asal->assertOk();
    // Kolom di TABEL RECORD, bukan join ke `stations` — kalau ia join,
    // angka ini pindah ke Line Baru dan sejarah periode lama tertulis ulang.
    expect($asal->json('coverage.filled_slots'))->toBe(2);
    expect($asal->json('metrics.sludge_tank_temp_c.avg'))->toEqual(87.0);

    $baru = $this->actingAs($this->supervisor, 'web')->getJson('/api/clarification-reports/summary?'.http_build_query([
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => (string) $lineBaru->id,
    ]));

    $baru->assertOk();
    expect($baru->json('coverage.filled_slots'))->toBe(0);
    expect($baru->json('has_data'))->toBeFalse();
});
