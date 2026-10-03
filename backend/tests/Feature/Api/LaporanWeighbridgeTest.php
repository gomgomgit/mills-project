<?php

/**
 * LaporanWeighbridgeTest (Feature/Api) — screen-143--laporan-weighbridge-web /
 * usecase-146--laporan-weighbridge-web (Laporan Periode Weighbridge).
 *
 * Integration tests for the four GET endpoints under
 * /api/weighbridge-reports (App\Http\Controllers\Api\
 * WeighbridgeReportController) plus the REUSED
 * /api/production-lines/options-for-report — ONE TEST PER test_scenarios
 * ENTRY (all 35), running each scenario's `api_test` steps IN ORDER and
 * feeding the real response of step N into step N+1 exactly as the
 * `{{stepN.field}}` references prescribe. Exercises the real route ->
 * 'auth:web,sanctum' + 'role:supervisor,mill_management,admin' -> controller
 * -> WeighbridgeReportService -> Eloquent chain, mirroring
 * tests/Feature/Api/LaporanStorageTankTest.php (screen-133).
 *
 * =====================================================================
 * WHAT MAKES THIS SCREEN DIFFERENT FROM THE FIVE STATION REPORTS BEFORE IT
 * =====================================================================
 *  1. TWO FLOWS THAT ARE NEVER SUMMED. `receive` (FFB arriving) and
 *     `dispatch` (shipments leaving) are two independent metric sets, and NOT
 *     ONE KEY in the payload totals them. Scenario 23 asserts that
 *     structurally — the exact top-level key set plus the exact per-flow key
 *     set — because "there is no such number" cannot be proven by searching
 *     for a number.
 *  2. NO IN-PLANT DURATION ANYWHERE, deliberately. A trip carries exactly ONE
 *     timestamp since migration 2026_08_19_000010 merged the two old time
 *     columns and dropped both, so a duration cannot be computed at all.
 *     Scenario 29 asserts the absence over the PAYLOAD KEYS and over the CSV
 *     HEADER structurally — never as a text search, because the screen
 *     deliberately EXPLAINS the absence in prose and a grep would trip on the
 *     explanation.
 *  3. `production_line_id` IS REQUIRED on /summary and /export — unlike the
 *     five siblings, where it stayed optional so their already-shipped mobile
 *     twins would not break. Weighbridge has no shipped mobile twin
 *     (screen-144 is unbuilt), so a missing one is 422 and there is NO
 *     all-lines fallback (scenarios 3 and 5).
 *
 * THREE GUARDS, THREE DIFFERENT ANSWERS — asserted separately on purpose:
 *   - `business_unit_id` from the client is IGNORED for Supervisor / Mill
 *     Management: probing another mill answers 200 with the caller's OWN
 *     data, deliberately NOT 403 (a 403 would confirm the other mill exists).
 *   - a `production_line_id` of another mill IS refused, 403 FORBIDDEN.
 *   - a `period_id` of another mill IS refused, 403 FORBIDDEN — never 404, so
 *     the refusal never doubles as an existence oracle.
 *
 * A BOUND ACCOUNT WITH NO MILL IS 422 VALIDATION_ERROR ON business_unit_id,
 * NEVER 403 (scenario 18). Corrected in spec v2: the four places that said
 * 403 were uniformly wrong rather than ambiguous. The reason is the one
 * written in the service — INCOMPLETE INPUT, NOT REFUSED ACCESS: nothing is
 * being denied to this user, the master data on their own account is
 * unfinished. The fail-closed half is proven with a SPY bound into the
 * container, because "the all-mills list was never built" is a claim about
 * something that did NOT happen.
 *
 * OPERATOR IS REFUSED ON ALL FOUR ROUTES (scenario 20), and that absence has
 * a date on it: the sibling report prefixes all carry `operator` because
 * their mobile twin reuses them, and screen-144 is not built yet. The 403
 * raised by EnsureRole carries `message` only and no `code` — that middleware
 * builds its own JSON response and never reaches ApiExceptionHandler (a
 * documented repo-wide known issue since screen-128), so these tests assert
 * the status and the message rather than inventing a code the response does
 * not carry. The SERVICE-raised FORBIDDEN, which DOES carry the code, is
 * asserted in tests/Unit/Services/WeighbridgeReportServiceTest.php.
 *
 * ---------------------------------------------------------------------
 * HOW A NULL net_weight IS SEEDED — READ THIS BEFORE ADDING A FIXTURE
 * ---------------------------------------------------------------------
 * WeighbridgeRecord::booted() recomputes net_weight = gross - tare on EVERY
 * save when both are non-null, so `->create(['net_weight' => null])` is
 * SILENTLY OVERWRITTEN and yields an ordinary weighed trip. gross_weight is
 * NOT NULL in the schema, so nulling gross and tare together is refused by
 * the database outright. The one shape that works is the shape the domain
 * produces — GROSS TAKEN, TARE NOT YET — and that is what
 * laporanWeighbridgeTrip() does when handed net_weight null.
 *
 * WEIGHTS ARE RAW KILOGRAMS. No conversion happens anywhere in the service
 * and none is asserted; the screen mock's "ton" is not the repo convention.
 */

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\StationType;
use App\Models\User;
use App\Models\WeighbridgeRecord;
use App\Services\StationReportService;
use App\Services\WeighbridgeReportService;
use Illuminate\Support\Collection;

/**
 * The all-mills spy for the "akun belum terhubung ke mill" scenario. Bound
 * into the container so the CONTROLLER resolves it.
 */
class LaporanWeighbridgeApiAllBusinessUnitsSpy extends WeighbridgeReportService
{
    public int $allBusinessUnitsCalls = 0;

    public function allBusinessUnits(): Collection
    {
        $this->allBusinessUnitsCalls++;

        return parent::allBusinessUnits();
    }
}

/**
 * One weighbridge_records row. `record_datetime` is POSITIONAL because it
 * decides period membership and must never be left to the factory's random
 * default; pass null for an UNDATED trip.
 *
 * `net_weight` is the one attribute interpreted rather than passed through —
 * see the file header for why nulling it alone does nothing.
 */
function laporanWeighbridgeTrip(Station $station, ?string $recordDatetime, array $attributes = []): WeighbridgeRecord
{
    $net = array_key_exists('net_weight', $attributes) ? $attributes['net_weight'] : 1000.0;
    unset($attributes['net_weight']);

    $weights = $net === null
        ? ['gross_weight' => 12000.0, 'tare_weight' => null, 'net_weight' => null]
        : ['gross_weight' => (float) $net + 2000.0, 'tare_weight' => 2000.0, 'net_weight' => (float) $net];

    return WeighbridgeRecord::factory()->forStation($station)->create(array_merge([
        'record_datetime' => $recordDatetime,
        'weighbridge_type' => WeighbridgeReportService::FLOW_RECEIVE,
        'estate_supplier' => 'Estate A',
        'destination' => null,
        'status' => RecordStatus::Saved,
    ], $weights, $attributes));
}

/** An ARUS MASUK trip. */
function laporanWeighbridgeReceive(
    Station $station,
    ?string $recordDatetime,
    float|int|null $netWeight = 1000.0,
    string $origin = 'Estate A',
    array $attributes = [],
): WeighbridgeRecord {
    return laporanWeighbridgeTrip($station, $recordDatetime, array_merge([
        'weighbridge_type' => WeighbridgeReportService::FLOW_RECEIVE,
        'estate_supplier' => $origin,
        'destination' => null,
        'net_weight' => $netWeight,
    ], $attributes));
}

/** An ARUS KELUAR trip. */
function laporanWeighbridgeDispatch(
    Station $station,
    ?string $recordDatetime,
    float|int|null $netWeight = 1000.0,
    ?string $destination = 'Refinery X',
    array $attributes = [],
): WeighbridgeRecord {
    return laporanWeighbridgeTrip($station, $recordDatetime, array_merge([
        'weighbridge_type' => WeighbridgeReportService::FLOW_DISPATCH,
        'estate_supplier' => 'Estate A',
        'destination' => $destination,
        'net_weight' => $netWeight,
    ], $attributes));
}

/** A query-string URL for one of the four endpoints. */
function laporanWeighbridgeUrl(string $path, array $query = []): string
{
    return '/api/weighbridge-reports/'.$path.($query === [] ? '' : '?'.http_build_query($query));
}

/** The by_origin / by_destination entry carrying $label, or null. */
function laporanWeighbridgeGroup(array $rows, string $key, ?string $label): ?array
{
    foreach ($rows as $row) {
        if ($row[$key] === $label) {
            return $row;
        }
    }

    return null;
}

/** Every key name appearing ANYWHERE in a nested array, flattened. */
function laporanWeighbridgeAllKeys(array $payload): array
{
    $keys = [];

    foreach ($payload as $key => $value) {
        if (is_string($key)) {
            $keys[] = $key;
        }

        if (is_array($value)) {
            $keys = array_merge($keys, laporanWeighbridgeAllKeys($value));
        }
    }

    return array_values(array_unique($keys));
}

/**
 * The body of a StreamedResponse, captured.
 *
 * STREAM ONCE. sendContent() marks itself as already streamed, so a SECOND
 * read of the SAME response object yields an empty string and a line-count
 * assertion would silently see zero lines instead of the real file.
 */
function laporanWeighbridgeStreamed($response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

/** An already-captured CSV body split into non-empty lines. */
function laporanWeighbridgeCsvLinesOf(string $body): array
{
    return array_values(array_filter(explode("\n", trim($body))));
}

/** One parsed CSV row. */
function laporanWeighbridgeCsvRow(string $line): array
{
    return str_getcsv($line, ',', '"', '\\');
}

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->weighbridge()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->weighbridge()->create();

    // Exactly ONE production line per mill at setup, so the chained
    // `{{step1.data.0.id}}` references below resolve deterministically. The
    // scenarios that need a second line create it themselves.
    $this->lineA = (string) $this->stationA->production_line_id;
    $this->lineB = (string) $this->stationB->production_line_id;

    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    // Admin is bound to no mill at all — hence the mill picker.
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('weighbridge')
        ->range('2026-09-01', '2026-09-30')
        ->named('Periode September Alpha')
        ->open()
        ->create();
});

/** The full /summary response shape, used by several scenarios. */
function laporanWeighbridgeSummaryStructure(): array
{
    return [
        'business_unit' => ['id', 'name'],
        'production_line' => ['id', 'name'],
        'period' => ['id', 'name', 'start_date', 'end_date', 'status'],
        'receive' => [
            'trip_count', 'net_weight_total', 'net_weight_avg', 'net_weight_trip_count',
            'missing_net_weight_trip_count', 'hourly', 'busiest_hour',
            'busiest_hour_trip_count', 'empty_hour_count', 'by_origin',
        ],
        'dispatch' => [
            'trip_count', 'net_weight_total', 'net_weight_avg', 'net_weight_trip_count',
            'missing_net_weight_trip_count', 'hourly', 'busiest_hour',
            'busiest_hour_trip_count', 'empty_hour_count', 'by_destination',
        ],
        'draft_trip_count', 'undated_trip_count',
        'daily_total' => [
            'receive_trip_count', 'receive_net_weight_total',
            'dispatch_trip_count', 'dispatch_net_weight_total',
        ],
        'completeness' => ['days_in_period', 'days_with_trip'],
    ];
}

// =====================================================================
// Scenario 1: "sukses sebagai Supervisor atau Mill Management"
// =====================================================================
it('skenario 1 — sukses sebagai Supervisor atau Mill Management: lines -> periods -> summary -> export, dirantai dari respons sungguhan', function () {
    laporanWeighbridgeReceive($this->stationA, '2026-09-02 07:10', 10000.0, 'Estate A');
    laporanWeighbridgeReceive($this->stationA, '2026-09-02 07:40', 5000.0, 'Supplier B');
    laporanWeighbridgeReceive($this->stationA, '2026-09-03 09:00', null, 'Estate A');
    laporanWeighbridgeDispatch($this->stationA, '2026-09-04 20:00', 20000.0, 'Refinery X');
    laporanWeighbridgeDispatch($this->stationA, '2026-09-05 21:00', 4000.0, null);

    foreach ([$this->supervisor, $this->millManagement] as $user) {
        // Step 1 — GET /api/production-lines/options-for-report. No
        // business_unit_id: it comes from the account and the client is never
        // asked for it.
        $lines = $this->actingAs($user, 'web')->getJson('/api/production-lines/options-for-report');
        $lines->assertOk();
        $lines->assertJsonStructure(['data' => [['id', 'name', 'code']]]);

        $productionLineId = $lines->json('data.0.id');
        expect($productionLineId)->toBe($this->lineA);

        // Step 2 — GET /weighbridge-reports/periods
        $periods = $this->actingAs($user, 'web')->getJson(laporanWeighbridgeUrl('periods'));
        $periods->assertOk();
        $periods->assertJsonStructure([
            'data' => [['id', 'name', 'start_date', 'end_date', 'status', 'station_type', 'station_type_label']],
        ]);

        $periodId = $periods->json('data.0.id');
        expect($periodId)->toBe((string) $this->periodA->id);
        $periods->assertJsonPath('data.0.station_type', 'weighbridge');

        // Step 3 — GET /summary?period_id={{step2.data.0.id}}&production_line_id={{step1.data.0.id}}
        $summary = $this->actingAs($user, 'web')->getJson(laporanWeighbridgeUrl('summary', [
            'period_id' => $periodId,
            'production_line_id' => $productionLineId,
        ]));

        $summary->assertOk();
        $summary->assertJsonStructure(laporanWeighbridgeSummaryStructure());
        $summary->assertJsonPath('business_unit.name', 'Mill Alpha');
        $summary->assertJsonPath('period.id', $periodId);
        $summary->assertJsonPath('production_line.id', $productionLineId);

        // Two independent metric sets, each with its own denominator.
        $summary->assertJsonPath('receive.trip_count', 3);
        expect($summary->json('receive.net_weight_total'))->toEqual(15000.0);
        $summary->assertJsonPath('receive.net_weight_trip_count', 2);
        expect($summary->json('receive.net_weight_avg'))->toEqual(7500.0);
        $summary->assertJsonPath('receive.missing_net_weight_trip_count', 1);
        $summary->assertJsonCount(24, 'receive.hourly');

        $summary->assertJsonPath('dispatch.trip_count', 2);
        expect($summary->json('dispatch.net_weight_total'))->toEqual(24000.0);
        $summary->assertJsonCount(24, 'dispatch.hourly');
        $summary->assertJsonCount(2, 'dispatch.by_destination');

        $summary->assertJsonPath('completeness.days_in_period', 30);
        $summary->assertJsonPath('completeness.days_with_trip', 4);

        // Step 4 — GET /export?...&format=csv, and no station row may change.
        $before = WeighbridgeRecord::query()->orderBy('id')->get()->toJson();

        $export = $this->actingAs($user, 'web')->get(laporanWeighbridgeUrl('export', [
            'period_id' => $periodId,
            'production_line_id' => $productionLineId,
            'format' => 'csv',
        ]));

        $export->assertOk();
        expect($export->headers->get('Content-Type'))->toContain('text/csv');

        $lines = laporanWeighbridgeCsvLinesOf(laporanWeighbridgeStreamed($export->baseResponse));

        // Header + one line per TRIP.
        expect($lines)->toHaveCount(6);
        expect(laporanWeighbridgeCsvRow($lines[0]))->toBe(WeighbridgeReportService::EXPORT_HEADER);

        expect(WeighbridgeRecord::query()->orderBy('id')->get()->toJson())->toBe($before);
    }
});

// =====================================================================
// Scenario 2: "sukses sebagai Admin"
// =====================================================================
it('skenario 2 — sukses sebagai Admin: options -> lines -> periods -> summary -> export, seluruhnya terbatas pada mill terpilih', function () {
    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('weighbridge')
        ->range('2026-09-01', '2026-09-30')->named('Periode September Beta')->open()->create();

    laporanWeighbridgeReceive($this->stationA, '2026-09-10 08:00', 1000.0, 'Estate Alpha');
    laporanWeighbridgeReceive($this->stationB, '2026-09-10 08:00', 99999.0, 'Estate Beta');

    // Step 1 — GET /business-units/options (Admin only)
    $options = $this->actingAs($this->admin, 'web')->getJson(laporanWeighbridgeUrl('business-units/options'));
    $options->assertOk();
    $options->assertJsonStructure(['data' => [['id', 'name']]]);

    $millId = collect($options->json('data'))->firstWhere('name', 'Mill Alpha')['id'];
    expect($millId)->toBe((string) $this->businessUnitA->id);

    // Step 2 — GET /production-lines/options-for-report?business_unit_id={{step1}}
    $lines = $this->actingAs($this->admin, 'web')
        ->getJson('/api/production-lines/options-for-report?'.http_build_query(['business_unit_id' => $millId]));
    $lines->assertOk();
    $productionLineId = $lines->json('data.0.id');
    expect($productionLineId)->toBe($this->lineA);

    // Step 3 — GET /periods?business_unit_id={{step1}}
    $periods = $this->actingAs($this->admin, 'web')
        ->getJson(laporanWeighbridgeUrl('periods', ['business_unit_id' => $millId]));
    $periods->assertOk();
    $periodId = $periods->json('data.0.id');
    expect($periodId)->toBe((string) $this->periodA->id);
    // Mill Beta's period is not in Mill Alpha's list.
    expect(array_column($periods->json('data'), 'id'))->not->toContain((string) $periodB->id);

    // Step 4 — GET /summary with all three ids
    $summary = $this->actingAs($this->admin, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'business_unit_id' => $millId,
        'production_line_id' => $productionLineId,
        'period_id' => $periodId,
    ]));

    $summary->assertOk();
    $summary->assertJsonStructure(laporanWeighbridgeSummaryStructure());
    $summary->assertJsonPath('business_unit.id', $millId);
    $summary->assertJsonPath('business_unit.name', 'Mill Alpha');
    $summary->assertJsonPath('receive.trip_count', 1);
    expect($summary->json('receive.net_weight_total'))->toEqual(1000.0);
    // Mill Beta's unmistakable figure is nowhere in the answer.
    expect($summary->json('receive.net_weight_total'))->not->toEqual(99999.0);
    expect(array_column($summary->json('receive.by_origin'), 'estate_supplier'))->toBe(['Estate Alpha']);

    // Step 5 — GET /export with all three ids
    $export = $this->actingAs($this->admin, 'web')->get(laporanWeighbridgeUrl('export', [
        'business_unit_id' => $millId,
        'production_line_id' => $productionLineId,
        'period_id' => $periodId,
        'format' => 'csv',
    ]));

    $export->assertOk();
    $body = laporanWeighbridgeStreamed($export->baseResponse);
    $lines = laporanWeighbridgeCsvLinesOf($body);

    expect($lines)->toHaveCount(2);
    expect($body)->toContain('Estate Alpha');
    expect($body)->not->toContain('Estate Beta');
});

// =====================================================================
// Scenario 3: "Production Line belum dipilih"
// =====================================================================
it('skenario 3 — Production Line belum dipilih: 422 VALIDATION_ERROR dan TIDAK ADA angka seluruh mill sebagai pengganti', function () {
    laporanWeighbridgeReceive($this->stationA, '2026-09-04 08:00', 1000.0);

    // Step 1 — GET /summary?period_id=... with NO production_line_id
    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanWeighbridgeUrl('summary', ['period_id' => (string) $this->periodA->id]));

    $summary->assertStatus(422);
    $summary->assertJsonPath('code', 'VALIDATION_ERROR');
    $summary->assertJsonStructure(['errors' => ['production_line_id']]);

    // NOT a silently widened report: no figure block is returned at all.
    $summary->assertJsonMissingPath('receive');
    $summary->assertJsonMissingPath('dispatch');
    $summary->assertJsonMissingPath('daily_total');
    $summary->assertJsonMissingPath('completeness');

    // The export path refuses identically — a file that mixed every line of
    // the mill would be worse than no file.
    $export = $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanWeighbridgeUrl('export', ['period_id' => (string) $this->periodA->id, 'format' => 'csv']));

    $export->assertStatus(422);
    $export->assertJsonPath('code', 'VALIDATION_ERROR');
    $export->assertJsonStructure(['errors' => ['production_line_id']]);
});

// =====================================================================
// Scenario 4: "Admin mengganti mill setelah memilih line"
// =====================================================================
it('skenario 4 — Admin mengganti mill: line mill lama menjadi 403 pada mill baru, dan daftar line ikut berganti', function () {
    laporanWeighbridgeReceive($this->stationA, '2026-09-04 08:00', 1000.0);

    // Step 1 — mill A + line A1 + period A1: 200.
    $ok = $this->actingAs($this->admin, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'business_unit_id' => (string) $this->businessUnitA->id,
        'production_line_id' => $this->lineA,
        'period_id' => (string) $this->periodA->id,
    ]));

    $ok->assertOk();
    $ok->assertJsonPath('business_unit.id', (string) $this->businessUnitA->id);
    $ok->assertJsonPath('production_line.id', $this->lineA);

    // Step 2 — the SAME line against mill B: 403. The line is a concrete
    // handle on another mill's data, so it is refused outright rather than
    // silently reinterpreted.
    $forbidden = $this->actingAs($this->admin, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'business_unit_id' => (string) $this->businessUnitB->id,
        'production_line_id' => $this->lineA,
        'period_id' => (string) $this->periodA->id,
    ]));

    $forbidden->assertStatus(403);
    $forbidden->assertJsonPath('code', 'FORBIDDEN');
    $forbidden->assertJsonMissingPath('receive');
    $forbidden->assertJsonMissingPath('dispatch');

    // Step 3 — the line option list reloads with mill B's lines.
    $lines = $this->actingAs($this->admin, 'web')
        ->getJson('/api/production-lines/options-for-report?'.http_build_query([
            'business_unit_id' => (string) $this->businessUnitB->id,
        ]));

    $lines->assertOk();
    expect(array_column($lines->json('data'), 'id'))->toBe([$this->lineB]);
    expect(array_column($lines->json('data'), 'id'))->not->toContain($this->lineA);
});

// =====================================================================
// Scenario 5: "mill hanya punya satu Production Line"
// =====================================================================
it('skenario 5 — mill berline-tunggal: daftar line berisi satu opsi, dan summary tanpa production_line_id tetap 422', function () {
    laporanWeighbridgeReceive($this->stationA, '2026-09-04 08:00', 1000.0);

    // Step 1 — exactly one option, and it is NOT auto-applied downstream.
    $lines = $this->actingAs($this->supervisor, 'web')->getJson('/api/production-lines/options-for-report');
    $lines->assertOk();
    $lines->assertJsonCount(1, 'data');
    expect($lines->json('data.0.id'))->toBe($this->lineA);

    // Step 2 — a single option is still a CHOICE: the API does not make it on
    // the caller's behalf, because guessing produces figures nobody asked for.
    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanWeighbridgeUrl('summary', ['period_id' => (string) $this->periodA->id]));

    $summary->assertStatus(422);
    $summary->assertJsonPath('code', 'VALIDATION_ERROR');
    $summary->assertJsonStructure(['errors' => ['production_line_id']]);
    $summary->assertJsonMissingPath('receive');
});

// =====================================================================
// Scenario 6: "Admin memilih mill lebih dulu"
// =====================================================================
it('skenario 6 — Admin memilih mill lebih dulu: pemilih mill terisi, dan tanpa mill terpilih periods/summary 422', function () {
    // Step 1 — GET /business-units/options
    $options = $this->actingAs($this->admin, 'web')->getJson(laporanWeighbridgeUrl('business-units/options'));

    $options->assertOk();
    $options->assertJsonStructure(['data' => [['id', 'name']]]);
    expect(array_column($options->json('data'), 'name'))->toContain('Mill Alpha');
    expect(array_column($options->json('data'), 'name'))->toContain('Mill Beta');

    // And until a mill IS chosen there is nothing to list or report: 422, not
    // an empty result set, which would read as "this mill has no data".
    $periods = $this->actingAs($this->admin, 'web')->getJson(laporanWeighbridgeUrl('periods'));
    $periods->assertStatus(422);
    $periods->assertJsonPath('code', 'VALIDATION_ERROR');
    $periods->assertJsonStructure(['errors' => ['business_unit_id']]);
    expect($periods->json('errors.business_unit_id.0'))->toContain('Pilih mill');

    $summary = $this->actingAs($this->admin, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'production_line_id' => $this->lineA,
        'period_id' => (string) $this->periodA->id,
    ]));
    $summary->assertStatus(422);
    $summary->assertJsonPath('code', 'VALIDATION_ERROR');
    $summary->assertJsonMissingPath('receive');
});

// =====================================================================
// Scenario 7: "mill belum punya periode pelaporan"
// =====================================================================
it('skenario 7 — mill belum punya periode yang mencakup Weighbridge: /periods menjawab 200 dengan data kosong, bukan 404', function () {
    // The mill has a line, but its only period registers another station.
    PeriodStation::query()->where('period_id', $this->periodA->id)->delete();
    $this->periodA->delete();

    Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('boiler-room')
        ->range('2026-10-01', '2026-10-31')->named('Periode Boiler Saja')->open()->create();

    // Step 1 — the line list is populated: the mill is fine, the periods are
    // what is missing.
    $lines = $this->actingAs($this->supervisor, 'web')->getJson('/api/production-lines/options-for-report');
    $lines->assertOk();
    $lines->assertJsonCount(1, 'data');

    // Step 2 — an empty picker plus a UI hint, never a 404.
    $periods = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('periods'));
    $periods->assertOk();
    $periods->assertJsonPath('data', []);
    $periods->assertJsonCount(0, 'data');
});

// =====================================================================
// Scenario 8: "periode tanpa data"
// =====================================================================
it('skenario 8 — periode tanpa data: 200 dengan total dan rata-rata null (bukan nol) dan nol angka dikarang', function () {
    $empty = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('weighbridge')
        ->range('2026-11-01', '2026-11-30')->named('Periode Tanpa Data')->open()->create();

    // Rows that exist, but not in this period — so "empty" is a real answer
    // rather than an empty table.
    laporanWeighbridgeReceive($this->stationA, '2026-09-04 08:00', 9999.0);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'period_id' => (string) $empty->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    foreach (['receive', 'dispatch'] as $flow) {
        $summary->assertJsonPath($flow.'.trip_count', 0);
        $summary->assertJsonPath($flow.'.net_weight_total', null);
        $summary->assertJsonPath($flow.'.net_weight_avg', null);
        $summary->assertJsonPath($flow.'.net_weight_trip_count', 0);
        $summary->assertJsonPath($flow.'.busiest_hour', null);
        $summary->assertJsonPath($flow.'.empty_hour_count', 24);
        $summary->assertJsonCount(24, $flow.'.hourly');
    }

    $summary->assertJsonPath('receive.by_origin', []);
    $summary->assertJsonPath('dispatch.by_destination', []);
    $summary->assertJsonPath('daily', []);
    $summary->assertJsonPath('completeness.days_with_trip', 0);
    $summary->assertJsonPath('daily_total.receive_net_weight_total', null);
    // null, NOT 0,0 — zero would claim a measured total of nothing. Asserted
    // with toBeNull()/toBe() rather than toEqual(), because toEqual() is loose
    // and `null == 0.0` is TRUE in PHP: a loose comparison here would pass
    // against the very implementation this line exists to catch.
    expect($summary->json('receive.net_weight_total'))->toBeNull();
    expect($summary->json('receive.net_weight_total'))->not->toBe(0.0);
    expect($summary->json('receive.net_weight_avg'))->not->toBe(0.0);
});

// =====================================================================
// Scenario 9: "trip tersinkron terlambat dari mobile"
// =====================================================================
it('skenario 9 — trip tersinkron terlambat: ikut pada periode tempat penimbangan terjadi, dan tidak pada periode berjalan', function () {
    $ended = $this->periodA;
    $current = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('weighbridge')
        ->range('2026-10-01', '2026-10-31')->named('Periode Oktober Berjalan')->open()->create();

    // Weighed INSIDE the ended period, row written long after it closed.
    $late = laporanWeighbridgeReceive($this->stationA, '2026-09-20 08:00', 7000.0, 'Tersinkron Terlambat');
    $late->forceFill(['created_at' => '2026-10-15 02:00:00', 'updated_at' => '2026-10-15 02:00:00'])->saveQuietly();

    laporanWeighbridgeReceive($this->stationA, '2026-10-05 08:00', 1000.0, 'Estate Oktober');

    // Step 1 — the ended period counts it.
    $endedSummary = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'period_id' => (string) $ended->id,
        'production_line_id' => $this->lineA,
    ]));

    $endedSummary->assertOk();
    $endedSummary->assertJsonPath('receive.trip_count', 1);
    expect($endedSummary->json('receive.net_weight_total'))->toEqual(7000.0);
    expect(array_column($endedSummary->json('daily'), 'date'))->toBe(['2026-09-20']);

    // Step 2 — the current period does not.
    $currentSummary = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'period_id' => (string) $current->id,
        'production_line_id' => $this->lineA,
    ]));

    $currentSummary->assertOk();
    $currentSummary->assertJsonPath('receive.trip_count', 1);
    expect($currentSummary->json('receive.net_weight_total'))->toEqual(1000.0);
    expect(array_column($currentSummary->json('daily'), 'date'))->toBe(['2026-10-05']);
});

// =====================================================================
// Scenario 10: "trip tanpa penanda waktu penimbangan"
// =====================================================================
it('skenario 10 — trip tanpa penanda waktu: undated_trip_count berdiri sendiri dan trip itu tidak muncul di angka mana pun', function () {
    foreach (['2026-09-02 08:00', '2026-09-03 08:00', '2026-09-04 08:00'] as $dt) {
        laporanWeighbridgeReceive($this->stationA, $dt, 1000.0, 'Estate Bertanggal');
    }

    laporanWeighbridgeReceive($this->stationA, null, 50000.0, 'Estate Tanpa Waktu');
    laporanWeighbridgeDispatch($this->stationA, null, 60000.0, 'Tujuan Tanpa Waktu');

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    $summary->assertJsonPath('undated_trip_count', 2);

    // Nowhere else: not in the counts, not in the weights, not in the
    // breakdowns, not in the hourly buckets, not in the daily recap.
    $summary->assertJsonPath('receive.trip_count', 3);
    expect($summary->json('receive.net_weight_total'))->toEqual(3000.0);
    $summary->assertJsonPath('dispatch.trip_count', 0);
    expect(laporanWeighbridgeGroup($summary->json('receive.by_origin'), 'estate_supplier', 'Estate Tanpa Waktu'))->toBeNull();
    expect(laporanWeighbridgeGroup($summary->json('dispatch.by_destination'), 'destination', 'Tujuan Tanpa Waktu'))->toBeNull();
    expect(array_sum(array_column($summary->json('receive.hourly'), 'trip_count')))->toBe(3);
    $summary->assertJsonCount(3, 'daily');
});

// =====================================================================
// Scenario 11: "periode hanya memuat satu jenis arus"
// =====================================================================
it('skenario 11 — periode satu arus saja: kelompok arus keluar TETAP hadir utuh dengan nilai null, tidak dihilangkan', function () {
    foreach (range(1, 6) as $i) {
        laporanWeighbridgeReceive($this->stationA, sprintf('2026-09-%02d 08:00', $i), 1000.0);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    // An absent section reads as "this does not exist"; a present section of
    // nulls reads as "there were none", and only the second is true.
    $summary->assertJsonStructure(laporanWeighbridgeSummaryStructure());
    $summary->assertJsonPath('dispatch.trip_count', 0);
    $summary->assertJsonPath('dispatch.net_weight_total', null);
    $summary->assertJsonPath('dispatch.net_weight_avg', null);
    $summary->assertJsonPath('dispatch.net_weight_trip_count', 0);
    $summary->assertJsonPath('dispatch.busiest_hour', null);
    $summary->assertJsonPath('dispatch.empty_hour_count', 24);
    $summary->assertJsonCount(24, 'dispatch.hourly');
    $summary->assertJsonPath('dispatch.by_destination', []);

    $summary->assertJsonPath('receive.trip_count', 6);
    expect($summary->json('receive.net_weight_total'))->toEqual(6000.0);
});

// =====================================================================
// Scenario 12: "seluruh trip beratnya kosong"
// =====================================================================
it('skenario 12 — seluruh trip beratnya kosong: total dan rata-rata null, penyebut 0, jumlah trip apa adanya', function () {
    foreach (range(1, 6) as $i) {
        laporanWeighbridgeReceive($this->stationA, sprintf('2026-09-%02d 08:00', $i), null);
    }

    foreach (range(1, 2) as $i) {
        laporanWeighbridgeDispatch($this->stationA, sprintf('2026-09-%02d 14:00', $i), null);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    $summary->assertJsonPath('receive.trip_count', 6);
    $summary->assertJsonPath('receive.net_weight_total', null);
    $summary->assertJsonPath('receive.net_weight_avg', null);
    $summary->assertJsonPath('receive.net_weight_trip_count', 0);
    $summary->assertJsonPath('receive.missing_net_weight_trip_count', 6);

    $summary->assertJsonPath('dispatch.trip_count', 2);
    $summary->assertJsonPath('dispatch.net_weight_total', null);
    $summary->assertJsonPath('dispatch.missing_net_weight_trip_count', 2);

    // The breakdown and the daily recap carry null weights too — "never
    // weighed" is not "weighed nothing".
    expect(laporanWeighbridgeGroup($summary->json('receive.by_origin'), 'estate_supplier', 'Estate A')['net_weight_total'])
        ->toBeNull();
    $summary->assertJsonPath('daily_total.receive_net_weight_total', null);
});

// =====================================================================
// Scenario 13: "penimbangan belum selesai pada sebagian trip"
// =====================================================================
it('skenario 13 — penimbangan belum selesai sebagian: rata-rata memakai penyebutnya sendiri dan trip kosong tidak menurunkannya', function () {
    // receive: 5 trips, 3 weighed (1000 + 2000 + 3000).
    foreach ([1000.0, 2000.0, 3000.0, null, null] as $i => $net) {
        laporanWeighbridgeReceive($this->stationA, sprintf('2026-09-%02d 08:00', $i + 1), $net);
    }

    // dispatch: 3 trips, 2 weighed (4000 + 6000).
    foreach ([4000.0, 6000.0, null] as $i => $net) {
        laporanWeighbridgeDispatch($this->stationA, sprintf('2026-09-%02d 14:00', $i + 1), $net);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    // 6000 / 3, NOT 6000 / 5.
    expect($summary->json('receive.net_weight_avg'))->toEqual(2000.0);
    expect($summary->json('receive.net_weight_avg'))->not->toEqual(1200.0);
    $summary->assertJsonPath('receive.net_weight_trip_count', 3);
    $summary->assertJsonPath('receive.trip_count', 5);
    $summary->assertJsonPath('receive.missing_net_weight_trip_count', 2);

    expect($summary->json('dispatch.net_weight_avg'))->toEqual(5000.0);
    $summary->assertJsonPath('dispatch.net_weight_trip_count', 2);
    $summary->assertJsonPath('dispatch.missing_net_weight_trip_count', 1);

    // PER FLOW, never merged into one figure.
    $summary->assertJsonMissingPath('missing_net_weight_trip_count');
});

// =====================================================================
// Scenario 14: "tujuan belum diisi pada sebagian trip arus keluar"
// =====================================================================
it('skenario 14 — sebagian tujuan belum diisi: kelompok null tetap ada dan jumlah trip per tujuan menjumlah tepat ke trip arus keluar', function () {
    foreach (range(1, 6) as $i) {
        laporanWeighbridgeDispatch($this->stationA, sprintf('2026-09-%02d 14:00', $i), 3000.0, 'Refinery X');
    }

    foreach (range(1, 4) as $i) {
        laporanWeighbridgeDispatch($this->stationA, sprintf('2026-09-%02d 15:00', $i + 10), 1000.0, null);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    $summary->assertJsonPath('dispatch.trip_count', 10);

    $nullGroup = laporanWeighbridgeGroup($summary->json('dispatch.by_destination'), 'destination', null);

    expect($nullGroup)->not->toBeNull();
    expect($nullGroup['trip_count'])->toBe(4);
    // Dropping it would leave the reader with a difference they cannot explain.
    expect(array_sum(array_column($summary->json('dispatch.by_destination'), 'trip_count')))->toBe(10);
});

// =====================================================================
// Scenario 15: "seluruh trip terjadi pada jam yang sama"
// =====================================================================
it('skenario 15 — seluruh trip pada satu jam: satu ember penuh, 23 ember nol, dan sebarannya tetap dihasilkan', function () {
    foreach (range(1, 9) as $i) {
        laporanWeighbridgeReceive($this->stationA, sprintf('2026-09-%02d 06:%02d', $i, $i * 5), 1000.0);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    $summary->assertJsonCount(24, 'receive.hourly');
    $summary->assertJsonPath('receive.hourly.6.trip_count', 9);
    $summary->assertJsonPath('receive.busiest_hour', 6);
    $summary->assertJsonPath('receive.busiest_hour_trip_count', 9);
    $summary->assertJsonPath('receive.empty_hour_count', 23);

    foreach (array_diff(range(0, 23), [6]) as $hour) {
        $summary->assertJsonPath('receive.hourly.'.$hour.'.trip_count', 0);
    }
});

// =====================================================================
// Scenario 16: "seluruh trip berstatus draft"
// =====================================================================
it('skenario 16 — seluruh trip draft: draft_trip_count sama dengan jumlah trip dan tak satu angka pun disembunyikan', function () {
    foreach (range(1, 5) as $i) {
        laporanWeighbridgeReceive($this->stationA, sprintf('2026-09-%02d 08:00', $i), 1000.0, 'Estate A', [
            'status' => $i % 2 === 0 ? RecordStatus::DraftPaused : RecordStatus::DraftOngoing,
        ]);
    }

    foreach (range(1, 3) as $i) {
        laporanWeighbridgeDispatch($this->stationA, sprintf('2026-09-%02d 14:00', $i), 2000.0, 'Refinery X', [
            'status' => RecordStatus::DraftOngoing,
        ]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    $summary->assertJsonPath('draft_trip_count', 8);
    expect($summary->json('receive.trip_count') + $summary->json('dispatch.trip_count'))->toBe(8);

    // Every figure still produced: the draft count is a disclosure, not a filter.
    expect($summary->json('receive.net_weight_total'))->toEqual(5000.0);
    expect($summary->json('dispatch.net_weight_total'))->toEqual(6000.0);
    $summary->assertJsonCount(1, 'receive.by_origin');
    $summary->assertJsonCount(1, 'dispatch.by_destination');
    $summary->assertJsonCount(5, 'daily');
    $summary->assertJsonPath('completeness.days_with_trip', 5);
});

// =====================================================================
// Scenario 17: "satu asal menyumbang hampir seluruh arus masuk"
// =====================================================================
it('skenario 17 — satu asal dominan: seluruh asal tetap ditampilkan tanpa pemangkasan dan tanpa ember lain-lain', function () {
    laporanWeighbridgeReceive($this->stationA, '2026-09-01 08:00', 50000.0, 'Estate Dominan');

    foreach (range(1, 11) as $i) {
        laporanWeighbridgeReceive($this->stationA, sprintf('2026-09-%02d 09:00', $i + 1), 100.0, 'Supplier Kecil '.$i);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    $summary->assertJsonCount(12, 'receive.by_origin');
    $summary->assertJsonPath('receive.by_origin.0.estate_supplier', 'Estate Dominan');

    $labels = array_column($summary->json('receive.by_origin'), 'estate_supplier');

    foreach (range(1, 11) as $i) {
        expect($labels)->toContain('Supplier Kecil '.$i);
    }

    foreach ($labels as $label) {
        expect(strtolower($label))->not->toContain('lain-lain');
        expect(strtolower($label))->not->toContain('others');
    }

    expect(array_sum(array_column($summary->json('receive.by_origin'), 'trip_count')))
        ->toBe($summary->json('receive.trip_count'));
});

// =====================================================================
// Scenario 18: "akun belum terhubung ke mill"
//
// 422 VALIDATION_ERROR on business_unit_id, NEVER 403 — corrected in spec v2.
// INCOMPLETE INPUT, NOT REFUSED ACCESS: the user is not being denied anything,
// the master data on their own account is unfinished.
// =====================================================================
it('skenario 18 — akun belum terhubung ke mill: 422 VALIDATION_ERROR (bukan 403) dan daftar seluruh mill TIDAK PERNAH disusun', function () {
    $noMill = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    // The spy replaces the service the CONTROLLER resolves, so "the list was
    // never built" is proven by a recorded call count of zero rather than
    // inferred from the status code.
    $spy = new LaporanWeighbridgeApiAllBusinessUnitsSpy;
    $this->app->instance(WeighbridgeReportService::class, $spy);

    // Step 1 — GET /periods
    $periods = $this->actingAs($noMill, 'web')->getJson(laporanWeighbridgeUrl('periods'));

    $periods->assertStatus(422);
    // NOT 403 — the user is admitted, their account is incomplete.
    $periods->assertStatus(422);
    $periods->assertJsonPath('code', 'VALIDATION_ERROR');
    $periods->assertJsonStructure(['errors' => ['business_unit_id']]);
    expect($periods->json('errors.business_unit_id.0'))
        ->toBe('Akun Anda belum terhubung ke mill. Hubungi Admin.');

    // Step 2 — GET /summary with both required ids present
    $summary = $this->actingAs($noMill, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertStatus(422);
    $summary->assertJsonPath('code', 'VALIDATION_ERROR');
    $summary->assertJsonStructure(['errors' => ['business_unit_id']]);
    $summary->assertJsonMissingPath('receive');
    $summary->assertJsonMissingPath('dispatch');

    // THE DECISIVE ASSERTION: falling back to "every mill" would turn one
    // broken master-data row into a cross-mill leak, and no mill picker is
    // offered to a role that is supposed to be bound to exactly one.
    expect($spy->allBusinessUnitsCalls)->toBe(0);
});

// =====================================================================
// Scenario 19: "mencoba melihat mill atau Production Line milik mill lain"
// =====================================================================
it('skenario 19 — lintas mill: business_unit_id klien diabaikan (200, data sendiri), sedangkan line dan periode mill lain 403', function () {
    laporanWeighbridgeReceive($this->stationA, '2026-09-10 08:00', 1000.0, 'Estate Alpha');
    laporanWeighbridgeReceive($this->stationB, '2026-09-10 08:00', 99999.0, 'Estate Beta');

    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('weighbridge')
        ->range('2026-09-01', '2026-09-30')->named('Periode September Beta')->open()->create();

    // Step 1 — naming another mill with an OWN line and period: 200 with own
    // data. Deliberately not 403 — a 403 would confirm Mill Beta exists, and
    // there is no access attempt to refuse because the parameter is discarded.
    $ignored = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'business_unit_id' => (string) $this->businessUnitB->id,
        'production_line_id' => $this->lineA,
        'period_id' => (string) $this->periodA->id,
    ]));

    $ignored->assertOk();
    $ignored->assertJsonPath('business_unit.id', (string) $this->businessUnitA->id);
    $ignored->assertJsonPath('business_unit.name', 'Mill Alpha');
    $ignored->assertJsonPath('receive.trip_count', 1);
    expect($ignored->json('receive.net_weight_total'))->toEqual(1000.0);
    expect($ignored->json('receive.net_weight_total'))->not->toEqual(99999.0);
    expect(array_column($ignored->json('receive.by_origin'), 'estate_supplier'))->toBe(['Estate Alpha']);

    // Step 2 — another mill's PRODUCTION LINE: a hard 403, and no report body.
    $foreignLine = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'production_line_id' => $this->lineB,
        'period_id' => (string) $this->periodA->id,
    ]));

    $foreignLine->assertStatus(403);
    $foreignLine->assertJsonPath('code', 'FORBIDDEN');
    $foreignLine->assertJsonMissingPath('receive');
    $foreignLine->assertJsonMissingPath('dispatch');

    // Step 3 — another mill's PERIOD: a hard 403 as well. 403 and never 404,
    // so the refusal cannot double as an existence oracle.
    $foreignPeriod = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'production_line_id' => $this->lineA,
        'period_id' => (string) $periodB->id,
    ]));

    $foreignPeriod->assertStatus(403);
    $foreignPeriod->assertJsonPath('code', 'FORBIDDEN');
    $foreignPeriod->assertJsonMissingPath('receive');

    // And the export refuses both the same way — a downloaded file must never
    // carry another mill's line.
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanWeighbridgeUrl('export', ['production_line_id' => $this->lineB, 'period_id' => (string) $this->periodA->id]))
        ->assertStatus(403);
    $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanWeighbridgeUrl('export', ['production_line_id' => $this->lineA, 'period_id' => (string) $periodB->id]))
        ->assertStatus(403);
});

// =====================================================================
// Scenario 20: "Operator mencoba membuka layar web ini"
//
// Operator is refused on ALL FOUR routes, and the refusal is closed TWICE:
// the route middleware ('role:supervisor,mill_management,admin') stops it
// first, and WeighbridgeReportService::guardAccess() refuses it two layers
// deeper as well. There is no Operator widening on this screen — the mobile
// Weighbridge report (screen-144) does not exist yet, and widening it would
// have to touch routes/api.php, guardAccess() AND the mill-bound branch of
// resolveBusinessUnit() together, or Operator falls into the Admin branch
// where the client's business_unit_id IS honoured.
//
// The 403 raised by EnsureRole carries `message` only, no `code`: that
// middleware builds its own JSON response and never reaches
// ApiExceptionHandler. That is a repo-wide documented known issue (screen-128),
// not a defect of this screen, so the assertion is on the status and the
// message. The code-carrying, SERVICE-raised FORBIDDEN is asserted in the unit
// test file.
// =====================================================================
it('skenario 20 — Operator: 403 pada keempat rute dan tidak satu angka Weighbridge pun terlihat', function () {
    laporanWeighbridgeReceive($this->stationA, '2026-09-04 08:00', 1000.0, 'Estate Rahasia');

    // THE GUEST CASE RUNS FIRST, ON PURPOSE: actingAs() persists for the rest
    // of the test case, so a request made after it would silently be an
    // authenticated one and would answer 403 where 401 is meant.
    $this->getJson(laporanWeighbridgeUrl('periods'))->assertStatus(401);
    $this->getJson(laporanWeighbridgeUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]))->assertStatus(401);

    // Step 1 — GET /periods
    $periods = $this->actingAs($this->operator, 'web')->getJson(laporanWeighbridgeUrl('periods'));
    $periods->assertStatus(403);
    $periods->assertJsonMissingPath('data');
    expect($periods->json('message'))->toBe('Anda tidak memiliki akses untuk aksi ini.');

    // Step 2 — GET /summary
    $summary = $this->actingAs($this->operator, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertStatus(403);
    $summary->assertJsonMissingPath('receive');
    $summary->assertJsonMissingPath('dispatch');
    $summary->assertJsonMissingPath('daily');
    expect($summary->content())->not->toContain('Estate Rahasia');

    // Step 3 — GET /export
    $export = $this->actingAs($this->operator, 'web')->getJson(laporanWeighbridgeUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));
    $export->assertStatus(403);
    expect($export->content())->not->toContain('Estate Rahasia');

    // The fourth route, the Admin-only mill picker, is refused too.
    $this->actingAs($this->operator, 'web')
        ->getJson(laporanWeighbridgeUrl('business-units/options'))
        ->assertStatus(403);
});

// =====================================================================
// Scenario 21: "periode berstatus tertutup"
// =====================================================================
it('skenario 21 — periode tertutup: tetap terdaftar, terbaca penuh, dan tetap dapat diekspor', function () {
    $closed = Period::factory()->forBusinessUnit($this->businessUnitA)->noStations()
        ->range('2026-11-01', '2026-11-30')->named('Periode November Tertutup')->create();
    PeriodStation::factory()->forPeriod($closed)->stationType('weighbridge')->closed()->create();

    laporanWeighbridgeReceive($this->stationA, '2026-11-04 08:00', 1000.0);
    laporanWeighbridgeDispatch($this->stationA, '2026-11-05 14:00', 2000.0);

    // Step 1 — status never filters the list.
    $periods = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('periods'));
    $periods->assertOk();

    $byId = collect($periods->json('data'))->keyBy('id');
    expect($byId)->toHaveKey((string) $closed->id);
    expect($byId[(string) $closed->id]['status'])->toBe('closed');

    // Step 2 — a closed period reads exactly like an open one.
    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'period_id' => (string) $closed->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    $summary->assertJsonStructure(laporanWeighbridgeSummaryStructure());
    $summary->assertJsonPath('period.status', 'closed');
    $summary->assertJsonPath('receive.trip_count', 1);
    $summary->assertJsonPath('dispatch.trip_count', 1);

    // Step 3 — and exports exactly like one. The period lock governs WRITING
    // data, not READING a report.
    $export = $this->actingAs($this->supervisor, 'web')->get(laporanWeighbridgeUrl('export', [
        'period_id' => (string) $closed->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));

    $export->assertOk();
    expect(laporanWeighbridgeCsvLinesOf(laporanWeighbridgeStreamed($export->baseResponse)))->toHaveCount(3);
});

// =====================================================================
// Scenario 22: "rekap harian sangat panjang"
// =====================================================================
it('skenario 22 — periode panjang: daily memuat satu baris per tanggal ber-trip, puluhan baris tanpa masalah', function () {
    $long = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('weighbridge')
        ->range('2026-11-01', '2026-12-30')->named('Periode 60 Hari')->open()->create();

    // 40 distinct dates across a 60-day window.
    for ($day = 1; $day <= 30; $day++) {
        laporanWeighbridgeReceive($this->stationA, sprintf('2026-11-%02d 08:00', $day), 1000.0);
    }

    for ($day = 1; $day <= 10; $day++) {
        laporanWeighbridgeDispatch($this->stationA, sprintf('2026-12-%02d 14:00', $day), 2000.0);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'period_id' => (string) $long->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    $summary->assertJsonCount(40, 'daily');
    $summary->assertJsonPath('completeness.days_in_period', 60);
    $summary->assertJsonPath('completeness.days_with_trip', 40);

    // The headline figures are unaffected by the recap's length, and the two
    // flows stay in separate columns right down to the footer.
    $summary->assertJsonPath('receive.trip_count', 30);
    $summary->assertJsonPath('dispatch.trip_count', 10);
    $summary->assertJsonPath('daily_total.receive_trip_count', 30);
    $summary->assertJsonPath('daily_total.dispatch_trip_count', 10);

    // Dates ascend, and each appears exactly once.
    $dates = array_column($summary->json('daily'), 'date');
    $sorted = $dates;
    sort($sorted);
    expect($dates)->toBe($sorted);
    expect($dates)->toBe(array_values(array_unique($dates)));
});

// =====================================================================
// Scenario 23: "arus masuk dan arus keluar tidak pernah dijumlahkan"
// =====================================================================
it('skenario 23 — kedua arus tidak pernah dijumlahkan: tidak ada satu pun kunci gabungan di payload maupun di CSV', function () {
    laporanWeighbridgeReceive($this->stationA, '2026-09-04 08:00', 1000.0);
    laporanWeighbridgeReceive($this->stationA, '2026-09-05 08:00', 2000.0);
    laporanWeighbridgeDispatch($this->stationA, '2026-09-04 14:00', 4000.0);

    // Step 1 — /summary
    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    $payload = $summary->json();

    // THE EXACT top-level key set leaves no room for a combined key to hide.
    // draft_trip_count and undated_trip_count are COMPLETENESS disclosures
    // spanning both flows, deliberately NOT a sum of two metric figures.
    expect(array_keys($payload))->toBe([
        'business_unit', 'production_line', 'period',
        'receive', 'dispatch',
        'draft_trip_count', 'undated_trip_count',
        'daily', 'daily_total', 'completeness',
    ]);

    expect(array_keys($payload['receive']))->toBe([
        'trip_count', 'net_weight_total', 'net_weight_avg', 'net_weight_trip_count',
        'missing_net_weight_trip_count', 'hourly', 'busiest_hour',
        'busiest_hour_trip_count', 'empty_hour_count', 'by_origin',
    ]);
    expect(array_keys($payload['dispatch']))->toBe([
        'trip_count', 'net_weight_total', 'net_weight_avg', 'net_weight_trip_count',
        'missing_net_weight_trip_count', 'hourly', 'busiest_hour',
        'busiest_hour_trip_count', 'empty_hour_count', 'by_destination',
    ]);

    // daily and daily_total keep the flows in separate columns.
    expect(array_keys($payload['daily_total']))->toBe([
        'receive_trip_count', 'receive_net_weight_total',
        'dispatch_trip_count', 'dispatch_net_weight_total',
    ]);

    foreach ($payload['daily'] as $row) {
        expect(array_keys($row))->toBe([
            'date', 'receive_trip_count', 'receive_net_weight_total',
            'dispatch_trip_count', 'dispatch_net_weight_total',
        ]);
    }

    foreach (array_map('strtolower', laporanWeighbridgeAllKeys($payload)) as $key) {
        foreach (['combined', 'gabungan', 'grand', 'overall', 'both_flows', 'all_flows'] as $forbidden) {
            expect($key)->not->toContain($forbidden);
        }
    }

    // Step 2 — /export. The CSV keeps the flows apart through the flow-type
    // column and carries no summed column at all.
    $export = $this->actingAs($this->supervisor, 'web')->get(laporanWeighbridgeUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));

    $export->assertOk();

    $lines = laporanWeighbridgeCsvLinesOf(laporanWeighbridgeStreamed($export->baseResponse));
    $header = laporanWeighbridgeCsvRow($lines[0]);

    expect($header)->toBe(WeighbridgeReportService::EXPORT_HEADER);
    expect($header)->toContain('Jenis Arus');

    foreach ($header as $column) {
        foreach (['gabungan', 'combined', 'total keseluruhan', 'grand total'] as $forbidden) {
            expect(strtolower($column))->not->toContain($forbidden);
        }
    }

    // One row per trip, each labelled with its own flow — never a summary row.
    expect($lines)->toHaveCount(4);
});

// =====================================================================
// Scenario 24: "rata-rata berat hanya memakai trip yang beratnya terisi"
// =====================================================================
it('skenario 24 — rata-rata memakai trip yang beratnya terisi sebagai penyebut: 10 trip, 6 tertimbang', function () {
    // 6 weighed trips totalling 60000 kg, 4 unweighed.
    foreach (range(1, 6) as $i) {
        laporanWeighbridgeReceive($this->stationA, sprintf('2026-09-%02d 08:00', $i), 10000.0);
    }

    foreach (range(7, 10) as $i) {
        laporanWeighbridgeReceive($this->stationA, sprintf('2026-09-%02d 08:00', $i), null);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    $summary->assertJsonPath('receive.trip_count', 10);
    $summary->assertJsonPath('receive.net_weight_trip_count', 6);
    expect($summary->json('receive.net_weight_total'))->toEqual(60000.0);
    // 60000 / 6, NOT 60000 / 10.
    expect($summary->json('receive.net_weight_avg'))->toEqual(10000.0);
    expect($summary->json('receive.net_weight_avg'))->not->toEqual(6000.0);
    // The four weightless trips reconcile the two numbers.
    $summary->assertJsonPath('receive.missing_net_weight_trip_count', 4);
});

// =====================================================================
// Scenario 25: "jumlah penimbangan yang belum selesai ditampilkan per arus"
// =====================================================================
it('skenario 25 — penimbangan belum selesai dilaporkan per arus dan tidak pernah dijumlahkan jadi satu', function () {
    foreach ([1000.0, null, null] as $i => $net) {
        laporanWeighbridgeReceive($this->stationA, sprintf('2026-09-%02d 08:00', $i + 1), $net);
    }

    foreach ([2000.0, 3000.0, null, null, null] as $i => $net) {
        laporanWeighbridgeDispatch($this->stationA, sprintf('2026-09-%02d 14:00', $i + 1), $net);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    // Two separate figures, each beside its own flow's trip count — so the
    // share of incomplete data per flow is readable.
    $summary->assertJsonPath('receive.missing_net_weight_trip_count', 2);
    $summary->assertJsonPath('receive.trip_count', 3);
    $summary->assertJsonPath('dispatch.missing_net_weight_trip_count', 3);
    $summary->assertJsonPath('dispatch.trip_count', 5);

    // NEVER summed into one: 5 appears nowhere as a single missing-weight key.
    $summary->assertJsonMissingPath('missing_net_weight_trip_count');
});

// =====================================================================
// Scenario 26: "penyaringan line memakai line yang melekat pada trip itu"
// =====================================================================
it('skenario 26 — penyaringan line memakai line yang MELEKAT pada trip, bukan line stasiun saat ini', function () {
    $lineRecorded = $this->lineA;
    $lineCurrent = ProductionLine::factory()->forBusinessUnit($this->businessUnitA)->create(['name' => 'Line Kedua']);

    laporanWeighbridgeReceive($this->stationA, '2026-09-04 08:00', 1000.0, 'Estate Asal');
    laporanWeighbridgeDispatch($this->stationA, '2026-09-04 14:00', 2000.0, 'Refinery X');

    // The station is LATER moved to the other line. Moving a station must not
    // rewrite the trips it already produced.
    $this->stationA->update(['production_line_id' => $lineCurrent->id]);

    // Step 1 — the line recorded ON the trip still carries it.
    $onRecorded = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $lineRecorded,
    ]));

    $onRecorded->assertOk();
    $onRecorded->assertJsonPath('receive.trip_count', 1);
    $onRecorded->assertJsonPath('dispatch.trip_count', 1);
    expect($onRecorded->json('receive.net_weight_total'))->toEqual(1000.0);
    expect(array_column($onRecorded->json('receive.by_origin'), 'estate_supplier'))->toBe(['Estate Asal']);
    $onRecorded->assertJsonCount(1, 'daily');

    // Step 2 — the station's CURRENT line shows none of it.
    $onCurrent = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => (string) $lineCurrent->id,
    ]));

    $onCurrent->assertOk();
    $onCurrent->assertJsonPath('receive.trip_count', 0);
    $onCurrent->assertJsonPath('dispatch.trip_count', 0);
    $onCurrent->assertJsonPath('receive.by_origin', []);
    $onCurrent->assertJsonPath('daily', []);
});

// =====================================================================
// Scenario 27: "keanggotaan periode ditentukan waktu kejadian penimbangan"
// =====================================================================
it('skenario 27 — keanggotaan periode dari waktu penimbangan, bukan waktu baris dibuat maupun waktu sinkronisasi', function () {
    // Weighed INSIDE, row written long after the period ended.
    $inside = laporanWeighbridgeReceive($this->stationA, '2026-09-20 08:00', 1000.0, 'Ditimbang Di Dalam');
    $inside->forceFill(['created_at' => '2026-12-01 01:00:00', 'updated_at' => '2026-12-01 01:00:00'])->saveQuietly();

    // Weighed OUTSIDE, row written inside the range.
    $outside = laporanWeighbridgeReceive($this->stationA, '2026-07-20 08:00', 9000.0, 'Ditimbang Di Luar');
    $outside->forceFill(['created_at' => '2026-09-15 01:00:00', 'updated_at' => '2026-09-15 01:00:00'])->saveQuietly();

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    $summary->assertJsonPath('receive.trip_count', 1);
    expect($summary->json('receive.net_weight_total'))->toEqual(1000.0);
    expect(array_column($summary->json('receive.by_origin'), 'estate_supplier'))->toBe(['Ditimbang Di Dalam']);
    expect(array_column($summary->json('daily'), 'date'))->toBe(['2026-09-20']);

    // Altering created_at changes nothing at all.
    $inside->forceFill(['created_at' => '2026-09-20 08:00:00'])->saveQuietly();

    $again = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    expect($again->json())->toEqual($summary->json());
});

// =====================================================================
// Scenario 28: "rentang periode inklusif di kedua ujung"
// =====================================================================
it('skenario 28 — rentang inklusif: trip hari pertama dan hari terakhir ikut, H-1 dan H+1 tidak, di summary maupun CSV', function () {
    laporanWeighbridgeReceive($this->stationA, '2026-09-01 00:05', 1000.0, 'Hari Pertama');
    laporanWeighbridgeReceive($this->stationA, '2026-09-30 23:50', 2000.0, 'Hari Terakhir');
    laporanWeighbridgeReceive($this->stationA, '2026-08-31 23:50', 9000.0, 'Sehari Sebelum');
    laporanWeighbridgeReceive($this->stationA, '2026-10-01 00:10', 8000.0, 'Sehari Sesudah');

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    $summary->assertJsonPath('receive.trip_count', 2);
    expect($summary->json('receive.net_weight_total'))->toEqual(3000.0);
    expect(array_column($summary->json('daily'), 'date'))->toBe(['2026-09-01', '2026-09-30']);
    $summary->assertJsonPath('completeness.days_with_trip', 2);

    $labels = array_column($summary->json('receive.by_origin'), 'estate_supplier');
    expect($labels)->toContain('Hari Pertama');
    expect($labels)->toContain('Hari Terakhir');
    expect($labels)->not->toContain('Sehari Sebelum');
    expect($labels)->not->toContain('Sehari Sesudah');

    // The CSV obeys the same bounds — the file and the screen cannot disagree.
    $export = $this->actingAs($this->supervisor, 'web')->get(laporanWeighbridgeUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));

    $export->assertOk();
    $body = laporanWeighbridgeStreamed($export->baseResponse);

    expect(laporanWeighbridgeCsvLinesOf($body))->toHaveCount(3);
    expect($body)->toContain('Hari Pertama');
    expect($body)->toContain('Hari Terakhir');
    expect($body)->not->toContain('Sehari Sebelum');
    expect($body)->not->toContain('Sehari Sesudah');
});

// =====================================================================
// Scenario 29: "tidak ada angka lama kendaraan berada di pabrik di mana pun"
//
// ASSERTED STRUCTURALLY, NEVER AS A TEXT SEARCH. The screen deliberately
// EXPLAINS the absence in prose ("Lama kendaraan di pabrik TIDAK dilaporkan
// ..."), so a grep for "durasi" would fail on the explanation itself. What is
// checkable is the SHAPE: no duration-named key in the payload, no
// duration-named column in the CSV, and EXACTLY ONE timestamp column.
// =====================================================================
it('skenario 29 — tidak ada angka lama kendaraan di pabrik: nol kunci durasi di payload dan TEPAT SATU kolom waktu di CSV', function () {
    foreach (range(1, 8) as $i) {
        laporanWeighbridgeReceive($this->stationA, sprintf('2026-09-%02d 0%d:30', $i, $i % 9), 1000.0);
        laporanWeighbridgeDispatch($this->stationA, sprintf('2026-09-%02d 1%d:30', $i, $i % 9), 2000.0);
    }

    // Step 1 — /summary
    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    foreach (array_map('strtolower', laporanWeighbridgeAllKeys($summary->json())) as $key) {
        foreach (['duration', 'durasi', 'lama', 'turnaround', 'dwell', 'elapsed', 'arrival_datetime', 'dispatch_datetime'] as $forbidden) {
            expect($key)->not->toContain($forbidden);
        }
    }

    // The ONLY time-based rendering is the hourly distribution with its
    // busiest hour and empty-hour count, all derivable from ONE timestamp.
    $summary->assertJsonCount(24, 'receive.hourly');
    $summary->assertJsonCount(24, 'dispatch.hourly');
    $summary->assertJsonStructure(['receive' => ['busiest_hour', 'busiest_hour_trip_count', 'empty_hour_count']]);

    // Step 2 — /export
    $export = $this->actingAs($this->supervisor, 'web')->get(laporanWeighbridgeUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));

    $export->assertOk();

    $lines = laporanWeighbridgeCsvLinesOf(laporanWeighbridgeStreamed($export->baseResponse));
    $header = laporanWeighbridgeCsvRow($lines[0]);

    foreach ($header as $column) {
        foreach (['durasi', 'duration', 'lama', 'turnaround', 'di pabrik'] as $forbidden) {
            expect(strtolower($column))->not->toContain($forbidden);
        }
    }

    // EXACTLY ONE timestamp column. A second time column would invite exactly
    // the subtraction this report must not offer.
    $timeColumns = array_values(array_filter(
        $header,
        fn ($column) => preg_match('/waktu|time|jam|tanggal|date/i', $column) === 1,
    ));

    expect($timeColumns)->toBe(['Waktu Penimbangan']);
    expect($lines)->toHaveCount(17);
});

// =====================================================================
// Scenario 30: "sebaran trip per jam dihitung dari penanda waktu tunggal"
// =====================================================================
it('skenario 30 — sebaran per jam dari penanda waktu tunggal tiap trip: 24 slot yang menjumlah tepat ke trip bertanggal arus itu', function () {
    // receive at hours 6, 6, 9, 14, 22; dispatch at hours 9, 20.
    foreach (['06:10', '06:50', '09:15', '14:40', '22:05'] as $i => $time) {
        laporanWeighbridgeReceive($this->stationA, sprintf('2026-09-%02d %s', $i + 1, $time), 1000.0);
    }

    foreach (['09:30', '20:10'] as $i => $time) {
        laporanWeighbridgeDispatch($this->stationA, sprintf('2026-09-%02d %s', $i + 10, $time), 2000.0);
    }

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    foreach (['receive', 'dispatch'] as $flow) {
        $hourly = $summary->json($flow.'.hourly');

        expect($hourly)->toHaveCount(24);
        expect(array_column($hourly, 'hour'))->toBe(range(0, 23));
        // The 24 slot counts sum EXACTLY to that flow's dated trip count.
        expect(array_sum(array_column($hourly, 'trip_count')))->toBe($summary->json($flow.'.trip_count'));
    }

    $summary->assertJsonPath('receive.hourly.6.trip_count', 2);
    $summary->assertJsonPath('receive.hourly.9.trip_count', 1);
    $summary->assertJsonPath('receive.hourly.14.trip_count', 1);
    $summary->assertJsonPath('receive.hourly.22.trip_count', 1);
    $summary->assertJsonPath('receive.busiest_hour', 6);
    $summary->assertJsonPath('receive.busiest_hour_trip_count', 2);
    $summary->assertJsonPath('receive.empty_hour_count', 20);

    $summary->assertJsonPath('dispatch.hourly.9.trip_count', 1);
    $summary->assertJsonPath('dispatch.hourly.20.trip_count', 1);
    // A TIE resolves to the EARLIEST hour, deterministically.
    $summary->assertJsonPath('dispatch.busiest_hour', 9);
    $summary->assertJsonPath('dispatch.empty_hour_count', 22);
});

// =====================================================================
// Scenario 31: "layar hanya membaca dan tidak mengubah data stasiun"
// =====================================================================
it('skenario 31 — hanya membaca: dua summary dan satu export tidak mengubah satu baris pun, dan prefix ini hanya menerima GET', function () {
    laporanWeighbridgeReceive($this->stationA, '2026-09-04 08:00', 1000.0);
    laporanWeighbridgeDispatch($this->stationA, '2026-09-05 14:00', 2000.0);

    $before = WeighbridgeRecord::query()->orderBy('id')->get()->toJson();
    $countBefore = WeighbridgeRecord::count();

    $query = ['period_id' => (string) $this->periodA->id, 'production_line_id' => $this->lineA];

    // Step 1 — /summary
    $first = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('summary', $query));
    $first->assertOk();

    // Step 2 — /export
    $export = $this->actingAs($this->supervisor, 'web')
        ->get(laporanWeighbridgeUrl('export', $query + ['format' => 'csv']));
    $export->assertOk();
    laporanWeighbridgeStreamed($export->baseResponse);

    // Step 3 — /summary again on the SAME period id: byte-identical answer.
    $second = $this->actingAs($this->supervisor, 'web')
        ->getJson(laporanWeighbridgeUrl('summary', ['period_id' => $first->json('period.id'), 'production_line_id' => $this->lineA]));
    $second->assertOk();
    expect($second->json())->toEqual($first->json());

    expect(WeighbridgeRecord::count())->toBe($countBefore);
    expect(WeighbridgeRecord::query()->orderBy('id')->get()->toJson())->toBe($before);

    // The prefix carries NO write verb at all — a report must not offer any
    // path that could alter the data it reports on.
    foreach (['summary', 'periods', 'export', 'business-units/options'] as $path) {
        foreach (['post', 'put', 'patch', 'delete'] as $verb) {
            $response = $this->actingAs($this->supervisor, 'web')
                ->json(strtoupper($verb), laporanWeighbridgeUrl($path));

            expect($response->status())->toBeIn([404, 405]);
        }
    }
});

// =====================================================================
// Scenario 32: "daftar periode dibatasi pada periode yang mencakup Weighbridge"
// =====================================================================
it('skenario 32 — daftar periode hanya yang MENDAFTARKAN weighbridge, dengan status baris weighbridge-nya sendiri', function () {
    // Registers weighbridge, OPEN — while another station type in the SAME
    // period is CLOSED, so a lookup that grabs "the period's status" is wrong.
    $open = Period::factory()->forBusinessUnit($this->businessUnitA)->noStations()
        ->range('2026-10-01', '2026-10-31')->named('Periode Weighbridge Terbuka')->create();
    PeriodStation::factory()->forPeriod($open)->stationType('weighbridge')->open()->create();
    PeriodStation::factory()->forPeriod($open)->stationType('boiler-room')->closed()->create();

    // Registers weighbridge, CLOSED — still listed.
    $closed = Period::factory()->forBusinessUnit($this->businessUnitA)->noStations()
        ->range('2026-11-01', '2026-11-30')->named('Periode Weighbridge Tertutup')->create();
    PeriodStation::factory()->forPeriod($closed)->stationType('weighbridge')->closed()->create();

    // Registers ONLY another station type — absent from the list.
    $other = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('boiler-room')
        ->range('2026-12-01', '2026-12-31')->named('Periode Boiler Saja')->open()->create();

    // Another mill's period — absent too.
    $otherMill = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('weighbridge')
        ->range('2026-10-01', '2026-10-31')->named('Periode Mill Lain')->open()->create();

    $periods = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('periods'));

    $periods->assertOk();

    $byId = collect($periods->json('data'))->keyBy('id');

    expect($byId)->toHaveKey((string) $open->id);
    expect($byId)->toHaveKey((string) $closed->id);
    expect($byId)->toHaveKey((string) $this->periodA->id);
    expect($byId)->not->toHaveKey((string) $other->id);
    expect($byId)->not->toHaveKey((string) $otherMill->id);

    // Each option's status is ITS OWN weighbridge period_stations row — never
    // another station type's status in the same period, and never
    // Period::$status, which no longer exists and THROWS.
    expect($byId[(string) $open->id]['status'])->toBe('open');
    expect($byId[(string) $closed->id]['status'])->toBe('closed');
    expect($byId[(string) $open->id]['station_type'])->toBe('weighbridge');
    expect(fn () => $open->status)->toThrow(LogicException::class);

    // Status never FILTERS the list: both open and closed are selectable.
    expect(array_column($periods->json('data'), 'status'))->toContain('open');
    expect(array_column($periods->json('data'), 'status'))->toContain('closed');
});

// =====================================================================
// Scenario 33: "tidak ada penandaan nilai di luar batas"
// =====================================================================
it('skenario 33 — nilai ekstrem dikembalikan apa adanya: nol kosakata penandaan di payload dan tetap ikut total serta rata-rata', function () {
    laporanWeighbridgeReceive($this->stationA, '2026-09-01 08:00', 1000.0, 'Estate Biasa');
    laporanWeighbridgeReceive($this->stationA, '2026-09-02 08:00', 999999.0, 'Estate Sangat Besar');
    laporanWeighbridgeReceive($this->stationA, '2026-09-03 08:00', 1.0, 'Estate Sangat Kecil');

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    $json = strtolower($summary->content());

    foreach ([
        'threshold', 'out_of_range', 'is_out_of_range', 'outlier', 'iqr', 'fence',
        'is_danger', 'severity', 'alert', 'flag', 'warning',
    ] as $forbidden) {
        expect($json)->not->toContain($forbidden);
    }

    // The extremes still contribute to the totals and the average like any
    // other trip — the report passes no judgement on plausibility.
    expect($summary->json('receive.net_weight_total'))->toEqual(1001000.0);
    expect($summary->json('receive.net_weight_avg'))->toEqual(round(1001000.0 / 3, 2));
    $summary->assertJsonPath('receive.net_weight_trip_count', 3);

    expect(laporanWeighbridgeGroup($summary->json('receive.by_origin'), 'estate_supplier', 'Estate Sangat Besar')['net_weight_total'])
        ->toEqual(999999.0);
    expect(laporanWeighbridgeGroup($summary->json('receive.by_origin'), 'estate_supplier', 'Estate Sangat Kecil')['net_weight_total'])
        ->toEqual(1.0);
});

// =====================================================================
// Scenario 34: "kelengkapan pencatatan tampil sebagai bagian laporan"
// =====================================================================
it('skenario 34 — kelengkapan pencatatan: keempat keadaan dilaporkan di satu tempat beserta days_in_period dan days_with_trip', function () {
    // unweighed on both flows, a draft, a destination-less dispatch, and an
    // undated trip — all four states at once.
    laporanWeighbridgeReceive($this->stationA, '2026-09-01 08:00', 1000.0);
    laporanWeighbridgeReceive($this->stationA, '2026-09-02 08:00', null);
    laporanWeighbridgeReceive($this->stationA, '2026-09-03 08:00', 2000.0, 'Estate A', ['status' => RecordStatus::DraftOngoing]);
    laporanWeighbridgeDispatch($this->stationA, '2026-09-03 14:00', null, 'Refinery X');
    laporanWeighbridgeDispatch($this->stationA, '2026-09-04 14:00', 3000.0, null);
    laporanWeighbridgeReceive($this->stationA, null, 7000.0);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();

    // PER FLOW — two facts, never one.
    $summary->assertJsonPath('receive.missing_net_weight_trip_count', 1);
    $summary->assertJsonPath('dispatch.missing_net_weight_trip_count', 1);

    // SINGLE numbers spanning both flows, exactly as the response schema
    // declares them — completeness disclosures, not metrics.
    $summary->assertJsonPath('draft_trip_count', 1);
    $summary->assertJsonPath('undated_trip_count', 1);

    // The destination-less dispatch group is reportable from by_destination.
    expect(laporanWeighbridgeGroup($summary->json('dispatch.by_destination'), 'destination', null)['trip_count'])->toBe(1);

    $summary->assertJsonPath('completeness.days_in_period', 30);
    $summary->assertJsonPath('completeness.days_with_trip', 4);
    expect(array_keys($summary->json('completeness')))->toBe(['days_in_period', 'days_with_trip']);
});

// =====================================================================
// Scenario 35: "unduh rincian seluruh trip sebagai CSV"
// =====================================================================
it('skenario 35 — unduh CSV: satu baris per trip, konteks diulang, kedua arus tetap terbedakan, dan tanpa kolom durasi', function () {
    laporanWeighbridgeReceive($this->stationA, '2026-09-01 08:00', 1000.0, 'Estate A');
    laporanWeighbridgeReceive($this->stationA, '2026-09-02 08:00', null, 'Estate B');
    laporanWeighbridgeDispatch($this->stationA, '2026-09-03 14:00', 5000.0, 'Refinery X');
    laporanWeighbridgeDispatch($this->stationA, '2026-09-04 14:00', 6000.0, null);

    $lineName = (string) ProductionLine::findOrFail($this->lineA)->name;

    // Step 1 — /summary, the figures the file must agree with.
    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanWeighbridgeUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $summary->assertOk();
    $summary->assertJsonPath('receive.trip_count', 2);
    $summary->assertJsonPath('dispatch.trip_count', 2);

    // Step 2 — /export
    $export = $this->actingAs($this->supervisor, 'web')->get(laporanWeighbridgeUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));

    $export->assertOk();
    expect($export->headers->get('Content-Type'))->toContain('text/csv');
    expect($export->headers->get('Content-Disposition'))->toContain('laporan-weighbridge');

    $lines = laporanWeighbridgeCsvLinesOf(laporanWeighbridgeStreamed($export->baseResponse));
    $header = laporanWeighbridgeCsvRow($lines[0]);

    expect($header)->toBe(WeighbridgeReportService::EXPORT_HEADER);
    // One row per trip, matching the two flows' trip counts exactly.
    expect($lines)->toHaveCount(5);

    $netIndex = array_search('Berat Bersih (kg)', $header, true);
    $flowIndex = array_search('Jenis Arus', $header, true);
    $flows = [];
    $netCells = [];

    foreach (array_slice($lines, 1) as $line) {
        $row = laporanWeighbridgeCsvRow($line);

        // The four context columns repeat verbatim on every row.
        expect($row[0])->toBe('Periode September Alpha');
        expect($row[1])->toBe('Mill Alpha');
        expect($row[2])->toBe($lineName);

        $flows[] = $row[$flowIndex];
        $netCells[] = $row[$netIndex];
    }

    // Receive and dispatch stay distinguishable and are never summed.
    expect(count(array_filter($flows, fn ($f) => $f === 'Arus Masuk')))->toBe(2);
    expect(count(array_filter($flows, fn ($f) => $f === 'Arus Keluar')))->toBe(2);

    // The unweighed trip is a ROW WITH AN EMPTY CELL — never dropped, never 0.
    expect(count(array_filter($netCells, fn ($cell) => $cell === '')))->toBe(1);
    expect($netCells)->not->toContain('0');

    // And the header carries no duration column.
    foreach ($header as $column) {
        foreach (['durasi', 'duration', 'lama', 'turnaround'] as $forbidden) {
            expect(strtolower($column))->not->toContain($forbidden);
        }
    }
});

// =====================================================================
// REACHABILITY — beyond the 35 scenarios, and the reason this report can be
// opened from the UI at all.
//
// StationReportService::REPORT_ROUTES is the single source of truth for which
// station tiles are live on Laporan Stasiun (screen-140). Without the
// 'weighbridge' => 'reports.weighbridge' entry this report exists, every test
// above passes, and the tile stays greyed out — the screen is simply
// unreachable. The map's ORDER is load-bearing too: it must stay in
// station_types.sort_order, and weighbridge (10) belongs at the FRONT.
// =====================================================================
it('reachability: REPORT_ROUTES memetakan weighbridge, menaruhnya PALING DEPAN, dan tetap urut menurut station_types.sort_order', function () {
    expect(StationReportService::REPORT_ROUTES)->toHaveKey('weighbridge');
    expect(StationReportService::REPORT_ROUTES['weighbridge'])->toBe('reports.weighbridge');

    // The route name actually resolves to a registered route.
    expect(route(StationReportService::REPORT_ROUTES['weighbridge'], [], false))->toBe('/reports/weighbridge');

    $sortOrderCodes = StationType::query()
        ->whereIn('code', array_keys(StationReportService::REPORT_ROUTES))
        ->orderBy('sort_order')
        ->pluck('code')
        ->all();

    expect(array_keys(StationReportService::REPORT_ROUTES))->toBe($sortOrderCodes);
    expect(array_key_first(StationReportService::REPORT_ROUTES))->toBe('weighbridge');

    // And the tile on screen-140 is live, with the mill travelling in the link
    // so this screen never has to ask for a mill a second time in one flow.
    $stations = $this->actingAs($this->supervisor, 'web')->getJson('/api/station-reports/stations');
    $stations->assertOk();

    $weighbridge = collect($stations->json('data.stations'))->firstWhere('code', 'weighbridge');

    expect($weighbridge)->not->toBeNull();
    expect($weighbridge['report_available'])->toBeTrue();
    expect($weighbridge['report_path'])->toContain('/reports/weighbridge');
});
