<?php

/**
 * LaporanBoilerRoomTest (Feature/Api) — screen-131--laporan-boiler-room-web /
 * usecase-131--laporan-boiler-room-web (Laporan Periode Boiler Room).
 *
 * Integration tests for the four GET endpoints under
 * /api/boiler-room-reports (App\Http\Controllers\Api\
 * BoilerRoomReportController), one test per test_scenarios entry, running
 * each scenario's api_test steps IN ORDER and feeding the real response of
 * step N into step N+1 exactly as the `{{stepN.field}}` references
 * prescribe. Exercises the real route -> 'auth:web,sanctum' +
 * 'role:supervisor,mill_management,admin' -> controller ->
 * BoilerRoomReportService -> Eloquent chain, mirroring
 * tests/Feature/Api/LaporanCagesTrackTest.php (screen-130).
 *
 * TWO GUARDS, TWO SHAPES — asserted separately on purpose:
 *   - business_unit_id from the client is IGNORED for Supervisor / Mill
 *     Management: probing another mill answers 200 with the caller's OWN
 *     data, deliberately NOT 403 (a 403 would confirm the other mill
 *     exists). The two-step shape of that scenario is load-bearing and must
 *     not be collapsed into one request.
 *   - period_id belonging to another mill IS refused with 403 FORBIDDEN by
 *     BoilerRoomReportService::authorizePeriod().
 *
 * OPERATOR IS ACCEPTED ON THE THREE REPORT ROUTES SINCE 2026-09-25 — the
 * widening for screen-137--laporan-boiler-room-mobile, mirroring
 * /api/cages-track-reports/* (widened 2026-09-24 for screen-136) and
 * /api/sterilizer-reports/* (2026-09-23 for screen-135). Operator is
 * MILL-BOUND, exactly like Supervisor and Mill Management, so its scenario
 * below asserts the CONTENT of what comes back, not just the status:
 *   - /periods, /summary, /export answer 200 with the Operator's OWN mill;
 *   - a business_unit_id naming ANOTHER mill answers 200 carrying the
 *     caller's own data — asserted by the boiler unit id in the payload, not
 *     by the status code. That content assertion is what locks
 *     resolveBusinessUnit(): had Operator been admitted in guardAccess()
 *     alone it would have fallen into the unbound Admin branch, where the
 *     client's business_unit_id IS honoured, and a status-only assertion
 *     would have passed straight through that cross-mill leak;
 *   - another mill's period_id is still a hard 403 FORBIDDEN;
 *   - an Operator account with no mill still fails closed with 422.
 * /business-units/options STAYS 403 for Operator: it is the Admin mill
 * picker, and an Operator bound to its own mill has no use for the list of
 * every mill. Now that the route middleware admits `operator`, that refusal
 * is raised by the SERVICE (AuthorizationException), so it carries
 * code = 'FORBIDDEN' and is asserted in full rather than by status alone.
 *
 * THE WIDENING STOPS AT THE API. The WEB route /reports/boiler-room keeps
 * refusing Operator — LaporanBoilerRoom::canAccess() has its own role list
 * and never calls this service. That refusal is asserted by
 * tests/Feature/Livewire/LaporanBoilerRoomTest.php and
 * e2e-web/tests/laporan-boiler-room.spec.ts, both of which must stay green
 * UNCHANGED; nothing in this file may be read as relaxing them.
 *
 * THE FIGURES ARE THE POINT, not just the status codes: every scenario that
 * has a number in its spec asserts that number, because this screen's
 * failure mode is a plausible-looking figure, not an error.
 */

use App\Enums\UserRole;
use App\Models\BoilerRoomDetail;
use App\Models\BoilerRoomRecord;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\Station;
use App\Models\StationType;
use App\Models\User;
use App\Services\BoilerRoomRecordService;
use App\Services\BoilerRoomReportService;
use App\Services\StationReportService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One boiler_room_records header plus one boiler_room_details row per entry
 * of $rows. `time_slot` is filled in from the canonical grid by position
 * unless the entry supplies its own, so UNIQUE(record, time_slot) holds
 * without every fixture having to spell the slots out.
 *
 * @param  list<array<string, mixed>>  $rows
 */
function laporanBoilerRoomRecord(Station $station, string $date, array $rows = [], array $overrides = []): BoilerRoomRecord
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
 * $count rows each filling only steam_pressure_bar (plus $extra) — the
 * "this row counts as filled" shape most coverage/maintenance fixtures need.
 *
 * @return list<array<string, mixed>>
 */
function laporanBoilerRoomPressureRows(int $count, float $pressure = 20.0, array $extra = []): array
{
    $rows = [];

    for ($index = 0; $index < $count; $index++) {
        $rows[] = array_merge(['steam_pressure_bar' => $pressure], $extra);
    }

    return $rows;
}

/**
 * Raw bulk seed of $totalDetailRows detail rows, spread over as many
 * headers as UNIQUE(record, time_slot) requires (24 slots each) — the only
 * way to reach the export ceiling inside a test.
 */
function laporanBoilerRoomBulkSeed(Station $station, User $author, string $monthPrefix, int $totalDetailRows): void
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
            'production_line_id' => $station->production_line_id,
            'boiler_room_id' => 'BLR-BULK-'.$recordIndex,
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
}

/** The body of a StreamedResponse, captured. */
function laporanBoilerRoomStreamed($response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->boilerRoom()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->boilerRoom()->create();

    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    // Admin is bound to no mill at all — hence the mill picker.
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('boiler-room')
        ->range('2026-03-01', '2026-03-31')
        ->named('Periode Maret Alpha')
        ->open()
        ->create();

    $this->slotsPerDay = count(BoilerRoomRecordService::canonicalTimeSlots());
});

// =====================================================================
// Scenario 1: "berhasil sebagai Supervisor atau Mill Management"
// =====================================================================
it('berhasil: periods -> summary -> export for a Supervisor and a Mill Management, chained on the real responses', function () {
    laporanBoilerRoomRecord($this->stationA, '2026-03-02', laporanBoilerRoomPressureRows(12, 20.0, [
        'steam_temp_c' => 260.0,
        'water_tds_ppm' => 2000.0,
        'water_ph' => 10.5,
        'exhaust_gas_temp_c' => 210.0,
        'blowdown_executed' => 'y',
    ]), ['boiler_room_id' => 'BLR-1']);

    laporanBoilerRoomRecord($this->stationA, '2026-03-03', laporanBoilerRoomPressureRows(8, 24.0, [
        'steam_temp_c' => 280.0,
        'water_tds_ppm' => 2200.0,
        'water_ph' => 11.5,
        'exhaust_gas_temp_c' => 230.0,
        'sootblowing_executed' => 'n',
    ]), ['boiler_room_id' => 'BLR-2']);

    foreach ([$this->supervisor, $this->millManagement] as $user) {
        // Step 1 — GET /api/boiler-room-reports/periods (no business_unit_id:
        // it comes from the account).
        $periods = $this->actingAs($user, 'web')->getJson('/api/boiler-room-reports/periods');
        $periods->assertOk();
        $periods->assertJsonStructure([
            'data' => [['id', 'name', 'start_date', 'end_date', 'status', 'station_type', 'station_type_label']],
        ]);

        $periodId = $periods->json('data.0.id');

        // Step 2 — GET /summary?period_id={{step1.data.0.id}}
        $summary = $this->actingAs($user, 'web')
            ->getJson('/api/boiler-room-reports/summary?'.http_build_query(['period_id' => $periodId]));

        $summary->assertOk();
        $summary->assertJsonStructure([
            'period' => ['id', 'name', 'start_date', 'end_date', 'status', 'business_unit_name'],
            'coverage' => [
                'filled_slots', 'expected_slots', 'coverage_percent',
                'boiler_unit_count', 'slots_per_unit_per_day', 'days_in_period',
            ],
            'metrics' => [
                'steam_pressure_bar' => ['min', 'avg', 'max', 'reading_count'],
                'steam_temp_c' => ['min', 'avg', 'max', 'reading_count'],
                'water_tds_ppm' => ['min', 'avg', 'max', 'reading_count'],
                'water_ph' => ['min', 'avg', 'max', 'reading_count'],
                'exhaust_gas_temp_c' => ['min', 'avg', 'max', 'reading_count'],
            ],
            'maintenance' => [
                'blowdown' => ['executed', 'not_executed', 'not_recorded', 'avg_per_day'],
                'sootblowing' => ['executed', 'not_executed', 'not_recorded', 'avg_per_day'],
            ],
            'daily' => [[
                'date', 'filled_slots', 'steam_pressure_avg', 'steam_temp_avg', 'water_tds_avg',
                'water_ph_avg', 'exhaust_gas_temp_avg', 'blowdown_executed', 'sootblowing_executed',
            ]],
            'by_unit' => [[
                'boiler_room_id', 'reading_count', 'steam_pressure_avg', 'steam_temp_avg',
                'water_tds_avg', 'water_ph_avg', 'blowdown_executed', 'sootblowing_executed',
            ]],
            'total' => ['days_with_records', 'reading_rows'],
        ]);

        $summary->assertJsonPath('period.business_unit_name', 'Mill Alpha');
        // 12 x 20.0 + 8 x 24.0 over 20 readings = 21.6.
        expect($summary->json('metrics.steam_pressure_bar.avg'))->toEqual(21.6);
        $summary->assertJsonPath('metrics.steam_pressure_bar.reading_count', 20);
        $summary->assertJsonPath('maintenance.blowdown.executed', 12);
        $summary->assertJsonPath('maintenance.sootblowing.not_executed', 8);
        $summary->assertJsonPath('coverage.filled_slots', 20);
        $summary->assertJsonPath('coverage.expected_slots', 2 * 31 * $this->slotsPerDay);
        $summary->assertJsonCount(2, 'daily');
        $summary->assertJsonCount(2, 'by_unit');
        $summary->assertJsonPath('total.reading_rows', 20);

        // Step 3 — GET /export?period_id={{step1.data.0.id}}, and nothing
        // about the station data may change because of it.
        $recordsBefore = BoilerRoomRecord::count();
        $detailsBefore = BoilerRoomDetail::count();

        $export = $this->actingAs($user, 'web')
            ->get('/api/boiler-room-reports/export?'.http_build_query(['period_id' => $periodId]));

        $export->assertOk();
        expect($export->headers->get('Content-Type'))->toContain('text/csv');

        $body = laporanBoilerRoomStreamed($export->baseResponse);
        $lines = array_values(array_filter(explode("\n", trim($body))));

        // Header + one line per TIME SLOT.
        expect($lines)->toHaveCount(21);
        expect($lines[0])->toContain('Slot Waktu');
        expect($lines[0])->toContain('Tekanan Uap (bar)');

        expect(BoilerRoomRecord::count())->toBe($recordsBefore);
        expect(BoilerRoomDetail::count())->toBe($detailsBefore);
    }
});

// =====================================================================
// Scenario 2: "berhasil sebagai Admin"
// =====================================================================
it('admin: options -> periods of the chosen mill -> summary computed only from that mill', function () {
    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('boiler-room')
        ->range('2026-03-01', '2026-03-31')->named('Periode Maret Beta')->open()->create();

    laporanBoilerRoomRecord($this->stationA, '2026-03-10', [['steam_pressure_bar' => 20.0]]);
    laporanBoilerRoomRecord($this->stationB, '2026-03-10', [['steam_pressure_bar' => 900.0]]);

    // Step 1 — GET /business-units/options
    $options = $this->actingAs($this->admin, 'web')->getJson('/api/boiler-room-reports/business-units/options');
    $options->assertOk();
    $options->assertJsonStructure(['data' => [['id', 'name']]]);

    $millId = collect($options->json('data'))->firstWhere('name', 'Mill Alpha')['id'];
    expect($millId)->toBe((string) $this->businessUnitA->id);

    // Step 2 — GET /periods?business_unit_id={{step1.data.0.id}}
    $periods = $this->actingAs($this->admin, 'web')
        ->getJson('/api/boiler-room-reports/periods?'.http_build_query(['business_unit_id' => $millId]));
    $periods->assertOk();

    $periodIds = collect($periods->json('data'))->pluck('id')->all();
    expect($periodIds)->toContain((string) $this->periodA->id);
    expect($periodIds)->not->toContain((string) $periodB->id);

    // Step 3 — GET /summary?business_unit_id=..&period_id={{step2.data.0.id}}
    $summary = $this->actingAs($this->admin, 'web')->getJson('/api/boiler-room-reports/summary?'.http_build_query([
        'business_unit_id' => $millId,
        'period_id' => $periods->json('data.0.id'),
    ]));

    $summary->assertOk();
    $summary->assertJsonPath('period.business_unit_name', 'Mill Alpha');
    expect($summary->json('metrics.steam_pressure_bar.avg'))->toEqual(20.0);
    $summary->assertJsonPath('metrics.steam_pressure_bar.reading_count', 1);
});

// =====================================================================
// Scenario 3: "Admin memilih mill lebih dulu"
// =====================================================================
it('admin tanpa mill: options is fine, summary answers 422 VALIDATION_ERROR on business_unit_id', function () {
    // Step 1 — the mill picker loads first.
    $this->actingAs($this->admin, 'web')
        ->getJson('/api/boiler-room-reports/business-units/options')
        ->assertOk();

    // Step 2 — summary without a mill: incomplete input, never 403 and never
    // an empty report that would read as "this mill has no data".
    $summary = $this->actingAs($this->admin, 'web')
        ->getJson('/api/boiler-room-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertStatus(422);
    $summary->assertJsonPath('code', 'VALIDATION_ERROR');
    $summary->assertJsonStructure(['errors' => ['business_unit_id']]);
    $summary->assertJsonMissingPath('metrics');

    // And the periods list refuses the same way.
    $periods = $this->actingAs($this->admin, 'web')->getJson('/api/boiler-room-reports/periods');
    $periods->assertStatus(422);
    $periods->assertJsonPath('code', 'VALIDATION_ERROR');
});

// =====================================================================
// Scenario 4: "mill belum punya periode"
// =====================================================================
it('mill tanpa periode: periods answers 200 with an empty list, and a made-up period_id is 404', function () {
    $supervisorB = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitB)->create();

    // Step 1 — an empty picker is a valid answer, not an error.
    $periods = $this->actingAs($supervisorB, 'web')->getJson('/api/boiler-room-reports/periods');
    $periods->assertOk();
    expect($periods->json('data'))->toBe([]);

    // Step 2 — forcing a summary with a period id that does not exist.
    $summary = $this->actingAs($supervisorB, 'web')
        ->getJson('/api/boiler-room-reports/summary?'.http_build_query(['period_id' => (string) Str::uuid()]));

    $summary->assertStatus(404);
    $summary->assertJsonPath('code', 'NOT_FOUND');
});

// =====================================================================
// Scenario 5: "periode tanpa data"
// =====================================================================
it('periode tanpa data: 200 with every metric null and reading_count 0, never 0/0/0', function () {
    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/boiler-room-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();

    foreach (BoilerRoomReportService::NUMERIC_METRICS as $metric) {
        $summary->assertJsonPath("metrics.{$metric}.min", null);
        $summary->assertJsonPath("metrics.{$metric}.avg", null);
        $summary->assertJsonPath("metrics.{$metric}.max", null);
        $summary->assertJsonPath("metrics.{$metric}.reading_count", 0);
        // The distinction the whole card rests on.
        expect($summary->json("metrics.{$metric}.avg"))->not->toBe(0);
    }

    $summary->assertJsonPath('coverage.filled_slots', 0);
    $summary->assertJsonPath('has_data', false);
    expect($summary->json('daily'))->toBe([]);
    expect($summary->json('by_unit'))->toBe([]);
});

// =====================================================================
// Scenario 6: "sebuah metrik tidak pernah diisi"
// =====================================================================
it('satu metrik kosong: water_ph is null with reading_count 0 while steam pressure keeps its own 20', function () {
    laporanBoilerRoomRecord($this->stationA, '2026-03-07', laporanBoilerRoomPressureRows(20, 18.0));

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/boiler-room-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();

    $summary->assertJsonPath('metrics.water_ph.min', null);
    $summary->assertJsonPath('metrics.water_ph.avg', null);
    $summary->assertJsonPath('metrics.water_ph.max', null);
    $summary->assertJsonPath('metrics.water_ph.reading_count', 0);

    // Independent per metric.
    expect($summary->json('metrics.steam_pressure_bar.avg'))->toEqual(18.0);
    $summary->assertJsonPath('metrics.steam_pressure_bar.reading_count', 20);
});

// =====================================================================
// Scenario 7: "perawatan tidak tercatat"
// =====================================================================
it('perawatan: 3 executed, 2 not executed, 5 not recorded — and the three sum to the reading rows', function () {
    $states = ['y', 'y', 'y', 'n', 'n', null, null, null, null, null];
    $rows = [];

    foreach ($states as $state) {
        $rows[] = ['steam_pressure_bar' => 20.0, 'blowdown_executed' => $state];
    }

    laporanBoilerRoomRecord($this->stationA, '2026-03-08', $rows);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/boiler-room-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();
    $summary->assertJsonPath('maintenance.blowdown.executed', 3);
    $summary->assertJsonPath('maintenance.blowdown.not_executed', 2);
    $summary->assertJsonPath('maintenance.blowdown.not_recorded', 5);

    // NULL never became a maintenance lapse.
    expect($summary->json('maintenance.blowdown.not_executed'))->not->toBe(7);
    expect(
        $summary->json('maintenance.blowdown.executed')
        + $summary->json('maintenance.blowdown.not_executed')
        + $summary->json('maintenance.blowdown.not_recorded')
    )->toBe(10);
    $summary->assertJsonPath('total.reading_rows', 10);
});

// =====================================================================
// Scenario 8: "pencatatan sangat tidak lengkap"
// =====================================================================
it('kelengkapan rendah: coverage carries filled_slots 6 alongside expected_slots, and the figures still come back', function () {
    $period = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('boiler-room')
        ->range('2026-04-01', '2026-04-30')->named('Periode April Tipis')->open()->create();

    // Six filled rows, plus four rows whose fifteen columns are ALL null —
    // those must not be counted as filled.
    laporanBoilerRoomRecord($this->stationA, '2026-04-10', array_merge(
        laporanBoilerRoomPressureRows(6, 21.0),
        array_fill(0, 4, []),
    ));

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/boiler-room-reports/summary?'.http_build_query(['period_id' => $period->id]));

    $summary->assertOk();
    $summary->assertJsonPath('coverage.filled_slots', 6);
    $summary->assertJsonPath('coverage.expected_slots', 1 * 30 * $this->slotsPerDay);
    $summary->assertJsonPath('coverage.days_in_period', 30);

    // The figures are still computed, never withheld.
    expect($summary->json('metrics.steam_pressure_bar.avg'))->toEqual(21.0);
    $summary->assertJsonPath('metrics.steam_pressure_bar.reading_count', 6);
});

// =====================================================================
// Scenario 9: "mill punya beberapa unit boiler"
// =====================================================================
it('beberapa unit: the period figures merge every unit, and a unit with no filled reading still appears', function () {
    laporanBoilerRoomRecord($this->stationA, '2026-03-12', [
        ['time_slot' => '08:00', 'steam_pressure_bar' => 20.0],
    ], ['boiler_room_id' => 'BLR-1']);

    // Same date AND the same time slot on another unit — allowed, because
    // UNIQUE(record, time_slot) is per record and each unit has its own.
    laporanBoilerRoomRecord($this->stationA, '2026-03-12', [
        ['time_slot' => '08:00', 'steam_pressure_bar' => 24.0],
    ], ['boiler_room_id' => 'BLR-2']);

    // A record with not one filled reading.
    laporanBoilerRoomRecord($this->stationA, '2026-03-12', array_fill(0, 3, []), [
        'boiler_room_id' => 'BLR-3',
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/boiler-room-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();

    // Combined across units.
    expect($summary->json('metrics.steam_pressure_bar.avg'))->toEqual(22.0);
    $summary->assertJsonPath('metrics.steam_pressure_bar.reading_count', 2);
    $summary->assertJsonPath('coverage.boiler_unit_count', 3);

    expect(array_column($summary->json('by_unit'), 'boiler_room_id'))->toBe(['BLR-1', 'BLR-2', 'BLR-3']);

    $blank = collect($summary->json('by_unit'))->firstWhere('boiler_room_id', 'BLR-3');
    expect($blank['reading_count'])->toBe(0);
    expect($blank['steam_pressure_avg'])->toBeNull();
});

// =====================================================================
// Scenario 10: "akun belum terhubung ke mill"
// =====================================================================
it('akun tanpa mill: 422 VALIDATION_ERROR on periods and on summary, and never the all-mills list', function () {
    $noMillSupervisor = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    // Step 1 — periods.
    $periods = $this->actingAs($noMillSupervisor, 'web')->getJson('/api/boiler-room-reports/periods');
    $periods->assertStatus(422);
    $periods->assertJsonPath('code', 'VALIDATION_ERROR');
    $periods->assertJsonStructure(['errors' => ['business_unit_id']]);
    expect($periods->json('errors.business_unit_id.0'))->toContain('Hubungi Admin');
    $periods->assertJsonMissingPath('data');

    // Step 2 — summary refuses the same way, rather than showing every mill.
    $summary = $this->actingAs($noMillSupervisor, 'web')
        ->getJson('/api/boiler-room-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));
    $summary->assertStatus(422);
    $summary->assertJsonPath('code', 'VALIDATION_ERROR');
    $summary->assertJsonMissingPath('metrics');

    // And it is never silently upgraded into the Admin mill picker.
    $picker = $this->actingAs($noMillSupervisor, 'web')
        ->getJson('/api/boiler-room-reports/business-units/options');
    $picker->assertStatus(403);
    $picker->assertJsonPath('code', 'FORBIDDEN');
    $picker->assertJsonMissingPath('data');
});

// =====================================================================
// Scenario 11: "mencoba melihat mill lain"
// =====================================================================
it('mill lain: the client business_unit_id is ignored (200, own data), but another mill period_id is 403', function () {
    laporanBoilerRoomRecord($this->stationA, '2026-03-10', [['steam_pressure_bar' => 20.0]]);
    laporanBoilerRoomRecord($this->stationB, '2026-03-10', [['steam_pressure_bar' => 900.0]]);

    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('boiler-room')
        ->range('2026-03-01', '2026-03-31')->named('Periode Maret Beta')->open()->create();

    // Step 1 — naming another mill with an OWN period: 200 with own data.
    // Deliberately not 403 — a 403 would confirm Mill Beta exists, and there
    // is no access attempt to refuse because the parameter is never used.
    $summary = $this->actingAs($this->supervisor, 'web')->getJson('/api/boiler-room-reports/summary?'.http_build_query([
        'business_unit_id' => $this->businessUnitB->id,
        'period_id' => $this->periodA->id,
    ]));

    $summary->assertOk();
    $summary->assertJsonPath('period.business_unit_name', 'Mill Alpha');
    expect($summary->json('metrics.steam_pressure_bar.avg'))->toEqual(20.0);
    expect($summary->json('metrics.steam_pressure_bar.max'))->not->toBe(900.0);

    // Step 2 — another mill's PERIOD ID is a hard 403: a concrete handle to
    // another mill's data.
    $forbidden = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/boiler-room-reports/summary?'.http_build_query(['period_id' => $periodB->id]));
    $forbidden->assertStatus(403);
    $forbidden->assertJsonPath('code', 'FORBIDDEN');
    $forbidden->assertJsonMissingPath('metrics');

    // Step 3 — export over the same period is refused identically.
    $export = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/boiler-room-reports/export?'.http_build_query(['period_id' => $periodB->id]));
    $export->assertStatus(403);
    $export->assertJsonPath('code', 'FORBIDDEN');
});

// =====================================================================
// Scenario 12: "Operator membuka laporan" (screen-137, mobile)
// =====================================================================
it('operator: 200 on periods/summary/export bound to its OWN mill, 403 on the mill picker, and 401 with no session at all', function () {
    // The no-session step is run FIRST, on purpose: actingAs() persists for
    // the rest of the test case, so a guest request made after it would
    // silently be an authenticated one.
    $this->getJson('/api/boiler-room-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]))
        ->assertStatus(401);

    // Two mills, told apart by BOILER UNIT ID rather than by figures alone:
    // the assertions below have to be able to say "this is Mill Alpha's
    // data" out of the payload itself, not merely "the status was 200".
    laporanBoilerRoomRecord($this->stationA, '2026-03-10', [
        ['steam_pressure_bar' => 20.0, 'water_ph' => 10.0],
    ], ['boiler_room_id' => 'BLR-ALPHA']);

    laporanBoilerRoomRecord($this->stationB, '2026-03-10', [
        ['steam_pressure_bar' => 900.0, 'water_ph' => 3.0],
    ], ['boiler_room_id' => 'BLR-BETA']);

    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('boiler-room')
        ->range('2026-03-01', '2026-03-31')->named('Periode Maret Beta')->open()->create();

    // Step 1 — GET /periods: 200, and the list is the Operator's own mill's
    // periods only. No business_unit_id is sent: it comes from the account.
    $periods = $this->actingAs($this->operator, 'web')->getJson('/api/boiler-room-reports/periods');
    $periods->assertOk();
    $periods->assertJsonStructure([
        'data' => [['id', 'name', 'start_date', 'end_date', 'status', 'station_type', 'station_type_label']],
    ]);

    $periodIds = array_column($periods->json('data'), 'id');
    expect($periodIds)->toContain($this->periodA->id);
    expect($periodIds)->not->toContain($periodB->id);

    // Step 2 — GET /summary while NAMING ANOTHER MILL: 200 carrying the
    // caller's OWN data, deliberately not 403 (a 403 would confirm Mill
    // Beta exists, and there is no access attempt to refuse because the
    // parameter is never used for a mill-bound role).
    //
    // THIS IS THE ASSERTION THAT LOCKS resolveBusinessUnit(). Admitting
    // Operator in guardAccess() alone drops it into the unbound Admin
    // branch, where the client's business_unit_id IS honoured — and that
    // leak answers 200 too. Only the CONTENT tells the two apart.
    $summary = $this->actingAs($this->operator, 'web')->getJson('/api/boiler-room-reports/summary?'.http_build_query([
        'business_unit_id' => $this->businessUnitB->id,
        'period_id' => $this->periodA->id,
    ]));

    $summary->assertOk();
    $summary->assertJsonPath('period.business_unit_name', 'Mill Alpha');
    $summary->assertJsonPath('business_unit.name', 'Mill Alpha');

    expect(array_column($summary->json('by_unit'), 'boiler_room_id'))->toBe(['BLR-ALPHA']);
    expect(array_column($summary->json('by_unit'), 'boiler_room_id'))->not->toContain('BLR-BETA');
    expect($summary->json('metrics.steam_pressure_bar.avg'))->toEqual(20.0);
    expect($summary->json('metrics.steam_pressure_bar.max'))->toEqual(20.0);
    expect($summary->json('metrics.steam_pressure_bar.max'))->not->toEqual(900.0);
    expect($summary->json('metrics.water_ph.avg'))->toEqual(10.0);
    expect($summary->json('metrics.water_ph.min'))->not->toEqual(3.0);
    expect(json_encode($summary->json()))->not->toContain('Mill Beta');

    // Step 3 — GET /export: 200, and the streamed CSV carries the
    // Operator's own mill's rows, not the other mill's.
    $export = $this->actingAs($this->operator, 'web')
        ->get('/api/boiler-room-reports/export?'.http_build_query([
            'period_id' => $this->periodA->id,
            'format' => 'csv',
        ]));

    $export->assertOk();

    $csv = laporanBoilerRoomStreamed($export->baseResponse);
    expect($csv)->toContain('BLR-ALPHA');
    expect($csv)->not->toContain('BLR-BETA');

    // Step 4 — ANOTHER MILL'S PERIOD ID is still a hard 403: a concrete
    // handle on another mill's data, refused outright rather than silently
    // rewritten. authorizePeriod() needed no change for this widening —
    // only Admin is unbound there.
    $forbidden = $this->actingAs($this->operator, 'web')
        ->getJson('/api/boiler-room-reports/summary?'.http_build_query(['period_id' => $periodB->id]));
    $forbidden->assertStatus(403);
    $forbidden->assertJsonPath('code', 'FORBIDDEN');
    $forbidden->assertJsonMissingPath('metrics');

    $forbiddenExport = $this->actingAs($this->operator, 'web')
        ->getJson('/api/boiler-room-reports/export?'.http_build_query(['period_id' => $periodB->id]));
    $forbiddenExport->assertStatus(403);
    $forbiddenExport->assertJsonPath('code', 'FORBIDDEN');

    // Step 5 — an Operator whose account has no mill FAILS CLOSED with 422,
    // exactly like the other mill-bound roles: never the all-mills list.
    $noMillOperator = User::factory()->role(UserRole::Operator)->create(['business_unit_id' => null]);

    $noMillPeriods = $this->actingAs($noMillOperator, 'web')->getJson('/api/boiler-room-reports/periods');
    $noMillPeriods->assertStatus(422);
    $noMillPeriods->assertJsonPath('code', 'VALIDATION_ERROR');
    $noMillPeriods->assertJsonStructure(['errors' => ['business_unit_id']]);
    expect($noMillPeriods->json('errors.business_unit_id.0'))->toContain('Hubungi Admin');
    $noMillPeriods->assertJsonMissingPath('data');

    $noMillSummary = $this->actingAs($noMillOperator, 'web')
        ->getJson('/api/boiler-room-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));
    $noMillSummary->assertStatus(422);
    $noMillSummary->assertJsonPath('code', 'VALIDATION_ERROR');
    $noMillSummary->assertJsonMissingPath('metrics');

    // Step 6 — the mill picker STAYS 403 for Operator. It is the Admin
    // picker; an Operator bound to its own mill has no use for the list of
    // every mill, and handing it over would be the very leak the widening
    // avoided. Raised by the service now, so it carries code = 'FORBIDDEN'.
    $picker = $this->actingAs($this->operator, 'web')
        ->getJson('/api/boiler-room-reports/business-units/options');
    $picker->assertStatus(403);
    $picker->assertJsonPath('code', 'FORBIDDEN');
    $picker->assertJsonMissingPath('data');
    expect(json_encode($picker->json()))->not->toContain('Mill Beta');
});

// =====================================================================
// Scenario 13: "periode tertutup"
// =====================================================================
it('periode tertutup: summary is complete with status closed, and the export still runs', function () {
    $closed = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('boiler-room')
        ->range('2026-05-01', '2026-05-31')->named('Periode Mei Tertutup')->closed()->create();

    laporanBoilerRoomRecord($this->stationA, '2026-05-10', laporanBoilerRoomPressureRows(4, 20.0));

    // Step 1 — the period lock governs writing data, not reading a report.
    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/boiler-room-reports/summary?'.http_build_query(['period_id' => $closed->id]));

    $summary->assertOk();
    $summary->assertJsonPath('period.status', 'closed');
    $summary->assertJsonPath('metrics.steam_pressure_bar.reading_count', 4);

    // Step 2 — and the export is not blocked either.
    $export = $this->actingAs($this->supervisor, 'web')
        ->get('/api/boiler-room-reports/export?'.http_build_query(['period_id' => $closed->id]));

    $export->assertOk();

    $lines = array_values(array_filter(explode("\n", trim(laporanBoilerRoomStreamed($export->baseResponse)))));
    expect($lines)->toHaveCount(5);
});

// =====================================================================
// Scenario 14: "rekap harian panjang"
// =====================================================================
it('rekap panjang: daily carries one entry per dated record, and an oversized export is 422 EXPORT_FAILED', function () {
    // Step 1 — one entry per date with a record, the headline figures stay
    // one object regardless of how long the recap gets.
    foreach (range(1, 20) as $day) {
        $date = sprintf('2026-03-%02d', $day);
        laporanBoilerRoomRecord($this->stationA, $date, [['steam_pressure_bar' => 20.0 + $day]], [
            'boiler_room_id' => 'BLR-1',
        ]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/boiler-room-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();
    $summary->assertJsonCount(20, 'daily');
    $summary->assertJsonPath('total.days_with_records', 20);
    $summary->assertJsonPath('metrics.steam_pressure_bar.reading_count', 20);

    // Step 2 — a period whose DETAIL ROWS exceed 50.000.
    $huge = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('boiler-room')
        ->range('2026-06-01', '2026-06-30')->named('Periode Juni Raksasa')->open()->create();

    laporanBoilerRoomBulkSeed($this->stationA, $this->supervisor, '2026-06', BoilerRoomReportService::EXPORT_ROW_LIMIT + 1);

    $export = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/boiler-room-reports/export?'.http_build_query(['period_id' => $huge->id]));

    $export->assertStatus(422);
    $export->assertJsonPath('code', 'EXPORT_FAILED');
});

// =====================================================================
// Scenario 15: "layar hanya membaca"
// =====================================================================
it('baca saja: repeated summary calls change nothing and no write verb is routed on the prefix', function () {
    laporanBoilerRoomRecord($this->stationA, '2026-03-10', laporanBoilerRoomPressureRows(5, 20.0, [
        'water_ph' => 7.0,
        'blowdown_executed' => 'y',
    ]));

    $recordsBefore = BoilerRoomRecord::count();
    $detailsBefore = BoilerRoomDetail::count();

    $first = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/boiler-room-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));
    $first->assertOk();

    $second = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/boiler-room-reports/summary?'.http_build_query(['period_id' => $first->json('period.id')]));
    $second->assertOk();

    expect($second->json())->toEqual($first->json());
    expect(BoilerRoomRecord::count())->toBe($recordsBefore);
    expect(BoilerRoomDetail::count())->toBe($detailsBefore);

    // Every mutating verb on the prefix is simply not registered.
    foreach (['postJson', 'putJson', 'patchJson', 'deleteJson'] as $verb) {
        foreach ([
            '/api/boiler-room-reports/summary',
            '/api/boiler-room-reports/periods',
            '/api/boiler-room-reports/export',
            '/api/boiler-room-reports/business-units/options',
        ] as $path) {
            $response = $this->actingAs($this->supervisor, 'web')->{$verb}($path, []);

            expect($response->getStatusCode())->toBeIn([404, 405]);
        }
    }
});

// =====================================================================
// Scenario 16: "daftar periode hanya yang mencakup Boiler Room"
// =====================================================================
it('daftar periode: hanya yang punya baris period_stations boiler-room yang ditawarkan', function () {
    $allTypes = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType(null)
        ->range('2026-06-01', '2026-06-30')->named('Periode Semua Stasiun')->open()->create();
    $otherType = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('sterilizer')
        ->range('2026-07-01', '2026-07-31')->named('Periode Sterilizer')->open()->create();

    $periods = $this->actingAs($this->supervisor, 'web')->getJson('/api/boiler-room-reports/periods');
    $periods->assertOk();

    $ids = collect($periods->json('data'))->pluck('id')->all();

    expect($ids)->toContain((string) $this->periodA->id);
    expect($ids)->toContain((string) $allTypes->id);
    expect($ids)->not->toContain((string) $otherType->id);

    // Newest first.
    expect($ids[0])->toBe((string) $allTypes->id);

    // KONTRAK API TETAP DATAR setelah pemisahan periods/period_stations
    // (2026-09-25): stationType(null) kini berarti "satu baris per jenis
    // stasiun", jadi opsi ini adalah pasangan (periode, boiler-room) —
    // station_type selalu terisi dan label 'Semua Stasiun' sudah tidak ada.
    $all = collect($periods->json('data'))->firstWhere('id', (string) $allTypes->id);
    expect($all['station_type'])->toBe('boiler-room');
    expect($all['station_type_label'])->toBe('Boiler Room');
});

// =====================================================================
// Scenario (BARU 2026-09-26): periode tanpa baris period_stations untuk
// boiler-room tidak boleh muncul — perilaku yang DULU dijamin cabang
// orWhereNull('station_type') pada listPeriods() dan kini sengaja dibuang.
// Cakupan "semua stasiun" hanya ada lewat ADANYA baris per jenis stasiun.
// =====================================================================
it('daftar periode: periode tanpa baris boiler-room tidak ditawarkan', function () {
    $otherTypesOnly = Period::factory()->forBusinessUnit($this->businessUnitA)
        ->stationTypes(['sterilizer', 'clarification'])
        ->range('2026-05-01', '2026-05-31')->named('Periode Tanpa Boiler Room')->open()->create();
    $noStations = Period::factory()->forBusinessUnit($this->businessUnitA)
        ->noStations()->range('2026-06-01', '2026-06-30')->named('Periode Tanpa Stasiun')->create();

    $periods = $this->actingAs($this->supervisor, 'web')->getJson('/api/boiler-room-reports/periods');
    $periods->assertOk();

    $ids = collect($periods->json('data'))->pluck('id')->all();

    expect($ids)->not->toContain((string) $otherTypesOnly->id);
    expect($ids)->not->toContain((string) $noStations->id);
});

// =====================================================================
// Scenario (BARU 2026-09-26): status yang dilaporkan adalah status STASIUN
// INI di dalam periode itu, bukan status periode — periode tidak punya status
// lagi. Bentuk ini (Boiler Room terbuka sementara Sterilizer tertutup di periode
// yang sama) sebelumnya mustahil dinyatakan.
// =====================================================================
it('daftar periode: status yang ditampilkan adalah status boiler-room, bukan status stasiun lain di periode yang sama', function () {
    $period = Period::factory()->forBusinessUnit($this->businessUnitA)
        ->noStations()->range('2026-05-01', '2026-05-31')->named('Periode Campuran')->create();

    PeriodStation::factory()->forPeriod($period)->stationType('boiler-room')->open()->create();
    PeriodStation::factory()->forPeriod($period)->stationType('sterilizer')->closed()->create();

    $periods = $this->actingAs($this->supervisor, 'web')->getJson('/api/boiler-room-reports/periods');
    $periods->assertOk();

    expect(collect($periods->json('data'))->firstWhere('id', (string) $period->id)['status'])->toBe('open');
});

// =====================================================================
// Scenario 17: "rentang periode inklusif di kedua ujung"
// =====================================================================
it('rentang inklusif: both bounds are counted, the days either side are not, and the export agrees', function () {
    laporanBoilerRoomRecord($this->stationA, '2026-03-01', [['steam_pressure_bar' => 10.0]]);
    laporanBoilerRoomRecord($this->stationA, '2026-03-31', [['steam_pressure_bar' => 30.0]]);
    laporanBoilerRoomRecord($this->stationA, '2026-02-28', [['steam_pressure_bar' => 99.0]]);
    laporanBoilerRoomRecord($this->stationA, '2026-04-01', [['steam_pressure_bar' => 88.0]]);

    // Step 1 — summary.
    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/boiler-room-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();
    $summary->assertJsonPath('metrics.steam_pressure_bar.reading_count', 2);
    expect($summary->json('metrics.steam_pressure_bar.min'))->toEqual(10.0);
    expect($summary->json('metrics.steam_pressure_bar.max'))->toEqual(30.0);
    expect(array_column($summary->json('daily'), 'date'))->toBe(['2026-03-01', '2026-03-31']);

    // Step 2 — the export carries the same two dates and neither neighbour.
    $export = $this->actingAs($this->supervisor, 'web')
        ->get('/api/boiler-room-reports/export?'.http_build_query(['period_id' => $this->periodA->id]));

    $export->assertOk();

    $body = laporanBoilerRoomStreamed($export->baseResponse);

    expect($body)->toContain('2026-03-01');
    expect($body)->toContain('2026-03-31');
    expect($body)->not->toContain('2026-02-28');
    expect($body)->not->toContain('2026-04-01');
});

// =====================================================================
// Scenario 18: "setiap metrik punya penyebutnya sendiri"
// =====================================================================
it('penyebut terpisah: pH is 28.0/4 = 7.0 with reading_count 4, and the extremes come from the raw readings', function () {
    // 10 rows fill steam_pressure_bar; only 4 fill water_ph (6.0, 7.0, 7.0,
    // 8.0). Two of the pressure readings are the raw extremes 12.0 and 28.0,
    // on a date whose DAILY average is 20.0.
    $pressures = [12.0, 28.0, 20.0, 20.0, 20.0, 20.0, 20.0, 20.0, 20.0, 20.0];
    $phValues = [6.0, 7.0, 7.0, 8.0, null, null, null, null, null, null];
    $rows = [];

    foreach ($pressures as $index => $pressure) {
        $rows[] = ['steam_pressure_bar' => $pressure, 'water_ph' => $phValues[$index]];
    }

    laporanBoilerRoomRecord($this->stationA, '2026-03-02', $rows);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/boiler-room-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();

    // 28.0 / 4, never 28.0 / 10.
    expect($summary->json('metrics.water_ph.avg'))->toEqual(7.0);
    expect($summary->json('metrics.water_ph.avg'))->not->toBe(2.8);
    $summary->assertJsonPath('metrics.water_ph.reading_count', 4);
    $summary->assertJsonPath('metrics.steam_pressure_bar.reading_count', 10);

    // Raw extremes, not daily averages.
    expect($summary->json('metrics.steam_pressure_bar.min'))->toEqual(12.0);
    expect($summary->json('metrics.steam_pressure_bar.max'))->toEqual(28.0);

    // And the recap row for the same date reads its DAILY average, which
    // cannot be reconciled with those extremes — deliberately.
    $recap = collect($summary->json('daily'))->firstWhere('date', '2026-03-02');
    expect($recap['steam_pressure_avg'])->toEqual(20.0);
    expect($recap['steam_pressure_avg'])->not->toBe($summary->json('metrics.steam_pressure_bar.min'));
});

// =====================================================================
// Scenario 19: "jumlah pembacaan ditampilkan berdampingan dengan angkanya"
// =====================================================================
it('reading_count: every metric entry carries its own, with no shared global denominator', function () {
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

    laporanBoilerRoomRecord($this->stationA, '2026-03-05', $rows);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/boiler-room-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();

    foreach (BoilerRoomReportService::NUMERIC_METRICS as $metric) {
        expect($summary->json("metrics.{$metric}"))->toHaveKey('reading_count');
    }

    $summary->assertJsonPath('metrics.steam_pressure_bar.reading_count', 10);
    $summary->assertJsonPath('metrics.steam_temp_c.reading_count', 7);
    $summary->assertJsonPath('metrics.water_tds_ppm.reading_count', 5);
    $summary->assertJsonPath('metrics.water_ph.reading_count', 4);
    $summary->assertJsonPath('metrics.exhaust_gas_temp_c.reading_count', 2);

    // No single shared reading_count anywhere in the payload.
    expect($summary->json('metrics'))->not->toHaveKey('reading_count');
    expect($summary->json())->not->toHaveKey('reading_count');

    // Coverage is present alongside, because the metric counts alone do not
    // say how much of the period was ever written down.
    expect($summary->json('coverage'))->toHaveKeys(['filled_slots', 'expected_slots']);
});

// =====================================================================
// Scenario 20: "laju bahan bakar dan beban fan tidak pernah dirata-rata"
// =====================================================================
it('teks bebas: absent from the summary payload entirely, present verbatim in the CSV', function () {
    laporanBoilerRoomRecord($this->stationA, '2026-03-11', [
        [
            'steam_pressure_bar' => 20.0,
            'fuel_feed_rate' => '12 ton/jam',
            'id_fan_load' => '80%',
            'sa_fan_load' => 'sedang',
        ],
    ]);

    // Step 1 — the aggregate payload must not mention them at all.
    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/boiler-room-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();

    foreach (['fuel_feed_rate', 'id_fan_load', 'sa_fan_load'] as $column) {
        expect($summary->json('metrics'))->not->toHaveKey($column);
        $summary->assertJsonMissingPath("metrics.{$column}");
    }

    foreach ($summary->json('daily') as $row) {
        expect(array_keys($row))->not->toContain('fuel_feed_rate', 'id_fan_load', 'sa_fan_load');
    }

    foreach ($summary->json('by_unit') as $row) {
        expect(array_keys($row))->not->toContain('fuel_feed_rate', 'id_fan_load', 'sa_fan_load');
    }

    $encoded = json_encode($summary->json());
    expect($encoded)->not->toContain('12 ton/jam');
    expect($encoded)->not->toContain('sedang');

    // Step 2 — the CSV, however, MUST carry them, verbatim.
    $export = $this->actingAs($this->supervisor, 'web')
        ->get('/api/boiler-room-reports/export?'.http_build_query(['period_id' => $this->periodA->id]));

    $export->assertOk();

    $body = laporanBoilerRoomStreamed($export->baseResponse);

    expect($body)->toContain('Laju Bahan Bakar');
    expect($body)->toContain('12 ton/jam');
    expect($body)->toContain('80%');
    expect($body)->toContain('sedang');
});

// =====================================================================
// Scenario 21: "tidak ada penandaan nilai di luar batas"
// =====================================================================
it('tanpa ambang: extreme readings come back neutral, with no threshold/severity/alert key anywhere', function () {
    laporanBoilerRoomRecord($this->stationA, '2026-03-18', [
        ['steam_pressure_bar' => 5.0],
        ['steam_pressure_bar' => 95.0],
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/boiler-room-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();
    expect($summary->json('metrics.steam_pressure_bar.min'))->toEqual(5.0);
    expect($summary->json('metrics.steam_pressure_bar.max'))->toEqual(95.0);

    // Boiler Room has no operational-target master, so a threshold here
    // would be a statistic read as a SAFETY limit. Judgement is the
    // reader's — which is why the ABSENCE is what gets asserted.
    $encoded = strtolower(json_encode($summary->json()));

    foreach ([
        'is_out_of_range', 'out_of_range', 'severity', 'threshold', 'alert',
        'warning', 'violation', 'danger', 'outlier', 'iqr', 'fence',
    ] as $forbidden) {
        expect($encoded)->not->toContain($forbidden);
    }
});

// =====================================================================
// Endpoint error codes not reachable from a BDD scenario
// =====================================================================

it('summary: 422 VALIDATION_ERROR when period_id is missing', function () {
    $response = $this->actingAs($this->supervisor, 'web')->getJson('/api/boiler-room-reports/summary');

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'VALIDATION_ERROR');
    $response->assertJsonStructure(['errors' => ['period_id']]);
});

it('export: 422 when period_id is missing, 404 when it does not exist, 422 for an unsupported format', function () {
    $this->actingAs($this->supervisor, 'web')->getJson('/api/boiler-room-reports/export')->assertStatus(422);

    $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/boiler-room-reports/export?'.http_build_query(['period_id' => (string) Str::uuid()]))
        ->assertStatus(404);

    $badFormat = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/boiler-room-reports/export?'.http_build_query([
            'period_id' => $this->periodA->id,
            'format' => 'xlsx',
        ]));

    $badFormat->assertStatus(422);
    $badFormat->assertJsonPath('code', 'VALIDATION_ERROR');
    $badFormat->assertJsonStructure(['errors' => ['format']]);
});

it('business-units/options: 403 FORBIDDEN for Supervisor and Mill Management — Admin only', function () {
    // Refused by the service, so this 403 does carry the code.
    $supervisor = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/boiler-room-reports/business-units/options');
    $supervisor->assertStatus(403);
    $supervisor->assertJsonPath('code', 'FORBIDDEN');

    $millManagement = $this->actingAs($this->millManagement, 'web')
        ->getJson('/api/boiler-room-reports/business-units/options');
    $millManagement->assertStatus(403);
    $millManagement->assertJsonPath('code', 'FORBIDDEN');
});

it('rejects unauthenticated requests on every endpoint', function () {
    $this->getJson('/api/boiler-room-reports/business-units/options')->assertStatus(401);
    $this->getJson('/api/boiler-room-reports/periods')->assertStatus(401);
    $this->getJson('/api/boiler-room-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]))
        ->assertStatus(401);
    $this->getJson('/api/boiler-room-reports/export?'.http_build_query(['period_id' => $this->periodA->id]))
        ->assertStatus(401);
});

// =====================================================================
// REACHABILITY — the one line without which this whole screen is dead code
//
// The report exists, every test in this file passes, and the Boiler Room
// tile on screen-140 (/reports) stays greyed out, because
// StationReportService::REPORT_ROUTES is the single source of truth for
// report_available / report_path. This asserts the map AND the order:
// screen-140's own API test compares the available codes (returned in
// station_types.sort_order) against array_keys(REPORT_ROUTES) with a
// strict, ordered toBe(), so a station report added out of sort_order fails
// there for a reason that has nothing to do with the new report.
// =====================================================================
it('reachability: REPORT_ROUTES stays in station_types.sort_order and carries boiler-room', function () {
    // NO LITERAL STATION LIST HERE — third time today this is decided the
    // same way (e2e-web/tests/laporan-stasiun.spec.ts and
    // mobile/tests/e2e/reporting-pilih-stasiun.spec.ts already went this
    // route). Four more station reports are coming; a hardcoded
    // ['cages-track', ...] reddens this file on every one of them without
    // teaching anything about Boiler Room. The order contract is asserted
    // against the master instead, so the map still has to be maintained in
    // sort_order. Strength is unchanged: toBe is strict AND ordered.
    //
    // This assertion and the $available one below are a PAIR — they compare
    // the same list from two directions. Repairing one alone just moves the
    // identical failure down a few lines.
    $sortOrderCodes = StationType::whereIn('code', array_keys(StationReportService::REPORT_ROUTES))
        ->orderBy('sort_order')
        ->pluck('code')
        ->all();

    expect(array_keys(StationReportService::REPORT_ROUTES))->toBe($sortOrderCodes);

    expect(StationReportService::REPORT_ROUTES)->toHaveKey('boiler-room');
    expect(StationReportService::REPORT_ROUTES['boiler-room'])->toBe('reports.boiler-room');

    // And the endpoint the tile grid reads agrees, in the same order.
    $stations = $this->actingAs($this->supervisor, 'web')->getJson('/api/station-reports/stations');
    $stations->assertOk();

    $available = collect($stations->json('data.stations'))
        ->where('report_available', true)
        ->pluck('code')
        ->values()
        ->all();

    // Same pairing, other direction: the API returns available codes in
    // station_types.sort_order, so it must equal the map's keys exactly and
    // in order. Still toBe — strict and ordered on purpose.
    expect($available)->toBe(array_keys(StationReportService::REPORT_ROUTES));

    $boilerRoom = collect($stations->json('data.stations'))->firstWhere('code', 'boiler-room');
    expect($boilerRoom['report_available'])->toBeTrue();
    expect($boilerRoom['report_path'])->not->toBeNull();
});
