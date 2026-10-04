<?php

/**
 * LaporanSterilizerTest (Feature/Api) — screen-129--laporan-sterilizer-web /
 * usecase-129--laporan-sterilizer-web (Laporan Periode Sterilizer).
 *
 * Integration tests for the four GET endpoints under
 * /api/sterilizer-reports (App\Http\Controllers\Api\
 * SterilizerReportController), one test per test_scenarios entry, running
 * each scenario's api_test steps IN ORDER and feeding the real response of
 * step N into step N+1 exactly as the `{{stepN.field}}` references
 * prescribe. Exercises the real route -> 'auth:web,sanctum' +
 * 'role:supervisor,mill_management,admin' -> controller ->
 * SterilizerReportService -> Eloquent chain, mirroring
 * tests/Feature/Api/ManagementReportTest.php's conventions.
 *
 * TWO GUARDS, TWO SHAPES — asserted separately on purpose:
 *   - business_unit_id from the client is IGNORED for Supervisor / Mill
 *     Management: probing another mill answers 200 with the caller's OWN
 *     data, deliberately NOT 403 (a 403 would confirm the other mill
 *     exists). The two-step shape of that scenario is load-bearing and
 *     must not be collapsed into one request.
 *   - period_id belonging to another mill IS refused with 403 FORBIDDEN by
 *     SterilizerReportService::authorizePeriod().
 *
 * ON ASSERTING `code` FOR 403: a refusal raised by EnsureRole (at the route
 * layer) carries only { message } — that middleware builds its own JSON
 * without going through ApiExceptionHandler — so those tests assert the
 * status alone. A refusal raised by the service (AuthorizationException)
 * does carry code = 'FORBIDDEN' and is asserted in full.
 *
 * 2026-09-23 — OPERATOR IS NO LONGER REFUSED HERE. The API role list was
 * widened to supervisor / mill_management / admin / operator for
 * screen-135--laporan-sterilizer-mobile; Operator now reads the report
 * for their OWN mill (200), on the same self-scoping as Supervisor. The
 * WEB route /reports/sterilizer was deliberately NOT widened — Operator
 * has no web UI — and that half is asserted in
 * e2e-web/tests/laporan-sterilizer.spec.ts.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\SterilizerDetail;
use App\Models\SterilizerRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** One Sterilizer cycle, all six triple-peak times filled, 90 minutes. */
function laporanSterilizerCycle(array $overrides = []): array
{
    return array_merge([
        'sterilizer_no' => '1',
        'close_door_time' => '07:00',
        'peak_1_time' => '07:15',
        'exhaust_1_time' => '07:20',
        'peak_2_time' => '07:35',
        'exhaust_2_time' => '07:40',
        'peak_3_time' => '07:55',
        'exhaust_3_time' => '08:00',
        'open_door_time' => '08:30',
        'duration_minutes' => 90,
        'number_of_cages' => 10,
        'cages_status' => 'Baik',
        'checked_by_spv' => true,
        'remarks' => null,
    ], $overrides);
}

function laporanSterilizerRecord(Station $station, string $date, array $cycles = [], array $recordOverrides = []): SterilizerRecord
{
    $record = SterilizerRecord::factory()->forStation($station)->onDate($date)->create($recordOverrides);

    foreach ($cycles as $cycle) {
        SterilizerDetail::factory()->forRecord($record)->create(laporanSterilizerCycle($cycle));
    }

    return $record;
}

function laporanSterilizerDurations(Station $station, string $date, array $durations): SterilizerRecord
{
    return laporanSterilizerRecord($station, $date, array_map(
        fn ($duration) => ['duration_minutes' => $duration],
        $durations,
    ));
}

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->sterilizer()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->sterilizer()->create();

    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    // Admin is bound to no mill at all — hence the mill picker.
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->range('2026-09-01', '2026-09-30')
        ->named('Periode September Alpha')
        ->open()
        ->create();
});

// =====================================================================
// Scenario: "Lihat Laporan Periode Sterilizer (Web) — success"
// =====================================================================
it('berhasil: periods -> summary -> export for a Supervisor, chained on the real responses', function () {
    laporanSterilizerRecord($this->stationA, '2026-09-05', array_map(
        fn ($duration) => ['duration_minutes' => $duration, 'sterilizer_no' => '1'],
        [88, 90, 91, 92, 93],
    ));
    laporanSterilizerRecord($this->stationA, '2026-09-12', array_map(
        fn ($duration) => ['duration_minutes' => $duration, 'sterilizer_no' => '2'],
        [94, 95, 96, 97, 200],
    ));

    // Step 1 — GET /api/sterilizer-reports/periods
    $periods = $this->actingAs($this->supervisor, 'web')->getJson('/api/sterilizer-reports/periods');
    $periods->assertOk();
    $periods->assertJsonStructure([
        'data' => [['id', 'name', 'start_date', 'end_date', 'status', 'station_type', 'station_type_label']],
    ]);

    $periodId = $periods->json('data.0.id');

    // Step 2 — GET /api/sterilizer-reports/summary?period_id={{step1.data[0].id}}
    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/sterilizer-reports/summary?'.http_build_query(['period_id' => $periodId]));

    $summary->assertOk();
    $summary->assertJsonStructure([
        'period' => ['id', 'name', 'start_date', 'end_date', 'status', 'business_unit_name'],
        'kpi' => [
            'total_cycles', 'total_cages', 'avg_duration_minutes', 'min_duration_minutes',
            'max_duration_minutes', 'cycles_without_duration', 'triple_peak_compliance_percent',
        ],
        'daily' => [['date', 'cycles', 'cages', 'avg_duration', 'min_duration', 'max_duration']],
        'by_unit' => [['sterilizer_no', 'cycles', 'cages', 'avg_duration']],
        'outliers' => ['method', 'lower_bound', 'upper_bound', 'min_sample_size', 'sample_size', 'insufficient_data', 'items'],
        'total' => ['cycles', 'cages', 'avg_duration'],
    ]);
    $summary->assertJsonPath('kpi.total_cycles', 10);
    $summary->assertJsonPath('kpi.total_cages', 100);
    $summary->assertJsonPath('kpi.triple_peak_compliance_percent', 100);
    $summary->assertJsonPath('outliers.method', 'iqr');

    // Step 3 — GET /api/sterilizer-reports/export?period_id=...&format=csv
    $export = $this->actingAs($this->supervisor, 'web')
        ->get('/api/sterilizer-reports/export?'.http_build_query(['period_id' => $periodId, 'format' => 'csv']));

    $export->assertOk();
    expect($export->headers->get('Content-Type'))->toContain('text/csv');
    expect($export->headers->get('Content-Disposition'))->toContain('attachment');

    $body = $export->streamedContent();
    expect($body)->toContain('ID Sterilizer');
    // One line per CYCLE (header + 10 cycles), not one per daily record.
    expect(array_values(array_filter(explode("\n", trim($body)))))->toHaveCount(11);
});

// =====================================================================
// Scenario: "Admin belum memilih mill"
// =====================================================================
it('admin tanpa mill: options returns the mill list, periods answers 422 VALIDATION_ERROR', function () {
    // Step 1 — GET /api/sterilizer-reports/business-units/options
    $options = $this->actingAs($this->admin, 'web')->getJson('/api/sterilizer-reports/business-units/options');

    $options->assertOk();
    $options->assertJsonStructure(['data' => [['id', 'name']]]);

    // Step 2 — GET /api/sterilizer-reports/periods (no business_unit_id)
    $periods = $this->actingAs($this->admin, 'web')->getJson('/api/sterilizer-reports/periods');

    // Never a silently empty list: an Admin who has not picked a mill is
    // told so on the field that is missing.
    $periods->assertStatus(422);
    $periods->assertJsonPath('code', 'VALIDATION_ERROR');
    $periods->assertJsonStructure(['message', 'code', 'errors' => ['business_unit_id']]);
});

// =====================================================================
// Scenario: "Admin memilih mill lalu melihat laporannya"
// =====================================================================
it('admin memilih mill: options -> periods of that mill -> summary computed only from that mill', function () {
    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('sterilizer')
        ->range('2026-09-01', '2026-09-30')->named('Periode September Beta')->create();

    laporanSterilizerDurations($this->stationA, '2026-09-10', [90, 100]);
    laporanSterilizerDurations($this->stationB, '2026-09-10', [200, 210, 220]);

    // Step 1
    $options = $this->actingAs($this->admin, 'web')->getJson('/api/sterilizer-reports/business-units/options');
    $options->assertOk();

    $millAId = collect($options->json('data'))->firstWhere('name', 'Mill Alpha')['id'];

    // Step 2 — periods?business_unit_id={{step1.data[...].id}}
    $periods = $this->actingAs($this->admin, 'web')
        ->getJson('/api/sterilizer-reports/periods?'.http_build_query(['business_unit_id' => $millAId]));

    $periods->assertOk();
    $periods->assertJsonCount(1, 'data');
    $periods->assertJsonPath('data.0.id', (string) $this->periodA->id);
    expect(collect($periods->json('data'))->pluck('id')->all())->not->toContain((string) $periodB->id);

    // Step 3 — summary?period_id={{step2.data[0].id}}
    $summary = $this->actingAs($this->admin, 'web')
        ->getJson('/api/sterilizer-reports/summary?'.http_build_query(['period_id' => $periods->json('data.0.id')]));

    $summary->assertOk();
    $summary->assertJsonPath('period.business_unit_name', 'Mill Alpha');
    $summary->assertJsonPath('kpi.total_cycles', 2);
    $summary->assertJsonPath('kpi.avg_duration_minutes', 95);
});

// =====================================================================
// Scenario: "Belum ada Periode Pelaporan"
// =====================================================================
it('belum ada periode: periods answers 200 with an empty list, not a 404', function () {
    $this->periodA->delete();
    // A period of another station type must not rescue the picker.
    Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('boiler-room')
        ->range('2026-09-01', '2026-09-30')->create();

    $periods = $this->actingAs($this->supervisor, 'web')->getJson('/api/sterilizer-reports/periods');

    $periods->assertOk();
    $periods->assertExactJson(['data' => []]);
});

// =====================================================================
// Scenario: "Periode tanpa data Sterilizer"
// =====================================================================
it('periode tanpa data: summary answers 200 with zeroed KPI and empty collections', function () {
    // Data outside the period's range only.
    laporanSterilizerDurations($this->stationA, '2026-08-31', [90]);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/sterilizer-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();
    $summary->assertJsonPath('kpi.total_cycles', 0);
    $summary->assertJsonPath('kpi.total_cages', 0);
    $summary->assertJsonPath('kpi.avg_duration_minutes', null);
    $summary->assertJsonPath('kpi.triple_peak_compliance_percent', 0);
    $summary->assertJsonPath('daily', []);
    $summary->assertJsonPath('by_unit', []);
    $summary->assertJsonPath('outliers.items', []);
});

// =====================================================================
// Scenario: "Sebagian siklus belum punya durasi"
// =====================================================================
it('sebagian siklus tanpa durasi: total_cycles keeps them, avg/min/max exclude them, the count is reported', function () {
    laporanSterilizerDurations($this->stationA, '2026-09-10', [90, 100, 110, null, null]);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/sterilizer-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();
    $summary->assertJsonPath('kpi.total_cycles', 5);
    $summary->assertJsonPath('kpi.cycles_without_duration', 2);
    $summary->assertJsonPath('kpi.avg_duration_minutes', 100);
    $summary->assertJsonPath('kpi.min_duration_minutes', 90);
    $summary->assertJsonPath('kpi.max_duration_minutes', 110);
});

// =====================================================================
// Scenario: "Seluruh durasi seragam"
// =====================================================================
it('durasi seragam: outliers returns real bounds with an empty item list and insufficient_data false', function () {
    laporanSterilizerDurations($this->stationA, '2026-09-10', array_fill(0, 10, 95));

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/sterilizer-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();
    $summary->assertJsonPath('outliers.insufficient_data', false);
    $summary->assertJsonPath('outliers.sample_size', 10);
    $summary->assertJsonPath('outliers.lower_bound', 95);
    $summary->assertJsonPath('outliers.upper_bound', 95);
    $summary->assertJsonPath('outliers.items', []);
});

// =====================================================================
// Scenario: "Supervisor dan Mill Management hanya melihat millnya sendiri"
// =====================================================================
it('mill lain: business_unit_id is ignored (200, own data) while another mill\'s period_id is refused 403', function () {
    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('sterilizer')
        ->range('2026-09-01', '2026-09-30')->named('Periode September Beta')->create();

    // Step 1 — periods?business_unit_id=<BU-B>: 200 with the caller's OWN
    // periods. Deliberately not a 403: a 403 would confirm BU-B exists.
    $periods = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/sterilizer-reports/periods?'.http_build_query(['business_unit_id' => $this->businessUnitB->id]));

    $periods->assertOk();
    $periods->assertJsonCount(1, 'data');
    $periods->assertJsonPath('data.0.id', (string) $this->periodA->id);

    // Step 2 — summary?period_id=<BU-B period>: 403 FORBIDDEN. THIS is the
    // real leak path, so it is refused outright.
    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/sterilizer-reports/summary?'.http_build_query(['period_id' => $periodB->id]));

    $summary->assertStatus(403);
    $summary->assertJsonPath('code', 'FORBIDDEN');
    $summary->assertJsonMissingPath('kpi');

    // Mill Management is bound the same way.
    $this->actingAs($this->millManagement, 'web')
        ->getJson('/api/sterilizer-reports/summary?'.http_build_query(['period_id' => $periodB->id]))
        ->assertStatus(403);
});

// =====================================================================
// Scenario: "Operator mengakses laporan dari mobile"
// (screen-135--laporan-sterilizer-mobile — the API role list was widened
// to include `operator` on 2026-09-23. Product decision, stated in
// screen-135 business_rules: the person who enters the data is entitled
// to see the result of their own work.)
//
// WHAT CHANGED AND WHAT DID NOT — the distinction is the whole point:
//   - API  /api/sterilizer-reports/*  : Operator is now ACCEPTED (200),
//     scoped to their OWN mill exactly like Supervisor / Mill Management.
//   - WEB  /reports/sterilizer        : Operator is still REFUSED (403).
//     There is no web UI for this actor, so there is nothing to widen.
//     That half lives in e2e-web/tests/laporan-sterilizer.spec.ts.
//   - business-units/options          : still 403 for Operator. It is the
//     ADMIN mill picker; a mill-bound actor has nothing to pick, and
//     handing them the full list of every mill would be the leak this
//     endpoint exists to avoid.
// =====================================================================
it('operator: api answers 200 scoped to the operator own mill, and the admin mill picker stays refused', function () {
    laporanSterilizerDurations($this->stationA, '2026-09-05', [88, 90, 92]);

    // Another mill's data must never surface, whatever the request says.
    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('sterilizer')
        ->range('2026-09-01', '2026-09-30')->named('Periode September Beta')->open()->create();
    laporanSterilizerDurations($this->stationB, '2026-09-06', [70, 71, 72]);

    // Step 1 — periods: own mill only, even when another mill is asked for.
    $periods = $this->actingAs($this->operator, 'web')
        ->getJson('/api/sterilizer-reports/periods?'.http_build_query([
            'business_unit_id' => $this->businessUnitB->id,
        ]));
    $periods->assertStatus(200);

    $periodIds = collect($periods->json('data'))->pluck('id')->all();
    expect($periodIds)->toContain((string) $this->periodA->id);
    expect($periodIds)->not->toContain((string) $periodB->id);

    // Step 2 — summary for the operator's own period: real figures.
    $summary = $this->actingAs($this->operator, 'web')
        ->getJson('/api/sterilizer-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));
    $summary->assertStatus(200);
    $summary->assertJsonPath('kpi.total_cycles', 3);

    // Step 3 — export streams, same as for any other report reader.
    $this->actingAs($this->operator, 'web')
        ->getJson('/api/sterilizer-reports/export?'.http_build_query(['period_id' => $this->periodA->id]))
        ->assertStatus(200);

    // Another mill's period id is still a hard 403 — the one guard that
    // must answer no, because a period id is a concrete handle to another
    // mill's data.
    $this->actingAs($this->operator, 'web')
        ->getJson('/api/sterilizer-reports/summary?'.http_build_query(['period_id' => $periodB->id]))
        ->assertStatus(403);

    // The Admin-only mill picker stays refused.
    $this->actingAs($this->operator, 'web')
        ->getJson('/api/sterilizer-reports/business-units/options')->assertStatus(403);
});

// =====================================================================
// Scenario: "Periode berstatus Tertutup"
// =====================================================================
it('periode tertutup: summary renders normally and export still works', function () {
    $closed = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('sterilizer')
        ->range('2026-11-01', '2026-11-30')->named('Periode November Tertutup')->closed()->create();

    laporanSterilizerDurations($this->stationA, '2026-11-10', [90, 95]);

    // Step 1
    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/sterilizer-reports/summary?'.http_build_query(['period_id' => $closed->id]));

    $summary->assertOk();
    $summary->assertJsonPath('period.status', 'closed');
    $summary->assertJsonPath('kpi.total_cycles', 2);

    // Step 2 — export?period_id={{step1.period.id}}
    $export = $this->actingAs($this->supervisor, 'web')
        ->get('/api/sterilizer-reports/export?'.http_build_query([
            'period_id' => $summary->json('period.id'),
            'format' => 'csv',
        ]));

    // Status is a caption, not a gate — no PERIOD_CLOSED here.
    $export->assertOk();
    expect($export->headers->get('Content-Disposition'))->toContain('attachment');
    expect(array_values(array_filter(explode("\n", trim($export->streamedContent())))))->toHaveCount(3);
});

// =====================================================================
// Scenario: "periode yang tidak mencakup jenis stasiun Sterilizer tidak
// dapat dipilih"
// =====================================================================
it('pemilih periode: only periods with a sterilizer period_stations row are offered, newest first', function () {
    $allTypes = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType(null)
        ->range('2026-10-01', '2026-10-31')->named('Periode Oktober Semua Stasiun')->create();
    $otherType = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('boiler-room')
        ->range('2026-08-01', '2026-08-31')->named('Periode Agustus Boiler')->create();

    $periods = $this->actingAs($this->supervisor, 'web')->getJson('/api/sterilizer-reports/periods');

    $periods->assertOk();

    $ids = collect($periods->json('data'))->pluck('id')->all();

    expect($ids)->toContain((string) $allTypes->id);
    expect($ids)->toContain((string) $this->periodA->id);
    expect($ids)->not->toContain((string) $otherType->id);
    // start_date descending: October before September.
    expect($ids)->toBe([(string) $allTypes->id, (string) $this->periodA->id]);

    // KONTRAK API TETAP DATAR setelah pemisahan periods/period_stations
    // (2026-09-25): station_type kini selalu terisi dengan jenis stasiun layar
    // ini — stationType(null) berarti "satu baris per jenis stasiun", bukan
    // lagi "tanpa jenis" — dan label 'Semua Stasiun' sudah tidak ada.
    $all = collect($periods->json('data'))->firstWhere('id', (string) $allTypes->id);

    expect($all['station_type'])->toBe('sterilizer');
    expect($all['station_type_label'])->toBe('Sterilizer');
});

// =====================================================================
// Scenario (BARU 2026-09-26): periode tanpa baris period_stations untuk
// sterilizer tidak boleh muncul. Ini perilaku yang DULU dijamin cabang
// orWhereNull('station_type') pada listPeriods() dan kini sengaja dibuang —
// cakupan "semua stasiun" hanya ada lewat ADANYA baris per jenis stasiun.
// =====================================================================
it('pemilih periode: periode tanpa baris sterilizer tidak ditawarkan', function () {
    $otherTypesOnly = Period::factory()->forBusinessUnit($this->businessUnitA)
        ->stationTypes(['boiler-room', 'clarification'])
        ->range('2026-10-01', '2026-10-31')->named('Periode Tanpa Sterilizer')->create();
    $noStations = Period::factory()->forBusinessUnit($this->businessUnitA)
        ->noStations()->range('2026-11-01', '2026-11-30')->named('Periode Tanpa Stasiun')->create();

    $periods = $this->actingAs($this->supervisor, 'web')->getJson('/api/sterilizer-reports/periods');

    $periods->assertOk();

    $ids = collect($periods->json('data'))->pluck('id')->all();

    expect($ids)->not->toContain((string) $otherTypesOnly->id);
    expect($ids)->not->toContain((string) $noStations->id);
    expect($ids)->toBe([(string) $this->periodA->id]);
});

// =====================================================================
// Scenario (BARU 2026-09-26): status yang dilaporkan adalah status STASIUN
// INI, bukan status periode — periode tidak punya status lagi. Bentuk ini
// (Sterilizer terbuka sementara Boiler Room tertutup di periode yang sama)
// sebelumnya mustahil dinyatakan, dan itulah alasan tabel period_stations ada.
// =====================================================================
it('status: payload memakai status sterilizer, bukan status stasiun lain di periode yang sama', function () {
    $period = Period::factory()->forBusinessUnit($this->businessUnitA)
        ->noStations()->range('2026-09-01', '2026-09-30')->named('Periode Campuran')->create();

    PeriodStation::factory()->forPeriod($period)->stationType('sterilizer')->open()->create();
    PeriodStation::factory()->forPeriod($period)->stationType('boiler-room')->closed()->create();

    $periods = $this->actingAs($this->supervisor, 'web')->getJson('/api/sterilizer-reports/periods');
    $periods->assertOk();

    $option = collect($periods->json('data'))->firstWhere('id', (string) $period->id);

    expect($option['status'])->toBe('open');

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/sterilizer-reports/summary?'.http_build_query(['period_id' => (string) $period->id]));

    $summary->assertOk();
    expect($summary->json('period.status'))->toBe('open');
});

// =====================================================================
// Scenario: "rentang inklusif memakai tanggal kejadian"
// =====================================================================
it('rentang inklusif: both bounds count, and membership follows date rather than created_at', function () {
    laporanSterilizerDurations($this->stationA, '2026-09-01', [90]);   // exactly on start_date
    laporanSterilizerDurations($this->stationA, '2026-09-30', [95]);   // exactly on end_date
    laporanSterilizerDurations($this->stationA, '2026-08-31', [100]);  // one day before

    $lateSync = laporanSterilizerDurations($this->stationA, '2026-09-15', [105]);
    DB::table('sterilizer_records')->where('id', $lateSync->id)->update(['created_at' => '2026-10-05 08:00:00']);

    $enteredDuringPeriod = laporanSterilizerDurations($this->stationA, '2026-08-20', [110]);
    DB::table('sterilizer_records')->where('id', $enteredDuringPeriod->id)->update(['created_at' => '2026-09-10 08:00:00']);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/sterilizer-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();
    $summary->assertJsonPath('kpi.total_cycles', 3);
    expect(collect($summary->json('daily'))->pluck('date')->all())
        ->toBe(['2026-09-01', '2026-09-15', '2026-09-30']);
});

// =====================================================================
// Scenario: "kepatuhan triple-peak turun saat waktu puncak atau
// pembuangan tidak lengkap"
// =====================================================================
it('triple-peak: one cycle missing exhaust_3_time pulls compliance down to 75%, never 100%', function () {
    laporanSterilizerRecord($this->stationA, '2026-09-10', [
        [],
        [],
        [],
        ['exhaust_3_time' => null],
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/sterilizer-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();
    $summary->assertJsonPath('kpi.total_cycles', 4);
    $summary->assertJsonPath('kpi.triple_peak_compliance_percent', 75);
});

// =====================================================================
// Scenario: "ambang siklus menyimpang wajib ditampilkan di layar"
// =====================================================================
it('ambang pencilan: the fence is returned with the flagged cycles and their full context', function () {
    laporanSterilizerRecord($this->stationA, '2026-09-10', array_map(
        fn ($duration) => ['duration_minutes' => $duration, 'sterilizer_no' => '3', 'number_of_cages' => 11],
        [88, 90, 91, 92, 93, 94, 95, 96, 97, 200],
    ));

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/sterilizer-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();
    $summary->assertJsonPath('outliers.method', 'iqr');
    $summary->assertJsonPath('outliers.insufficient_data', false);
    $summary->assertJsonPath('outliers.lower_bound', 84.5);
    $summary->assertJsonPath('outliers.upper_bound', 102.5);
    $summary->assertJsonCount(1, 'outliers.items');
    $summary->assertJsonPath('outliers.items.0.duration_minutes', 200);
    $summary->assertJsonPath('outliers.items.0.sterilizer_no', '3');
    $summary->assertJsonPath('outliers.items.0.number_of_cages', 11);
    $summary->assertJsonPath('outliers.items.0.date', '2026-09-10');
});

// =====================================================================
// Scenario: "laporan bersifat baca saja"
// =====================================================================
it('baca saja: repeated summary calls change nothing and no write verb is routed on the prefix', function () {
    laporanSterilizerDurations($this->stationA, '2026-09-10', [90, 95, 100]);

    $recordsBefore = SterilizerRecord::count();
    $detailsBefore = SterilizerDetail::count();

    // Step 1
    $first = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/sterilizer-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));
    $first->assertOk();

    // Step 2 — summary?period_id={{step1.period.id}}
    $second = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/sterilizer-reports/summary?'.http_build_query(['period_id' => $first->json('period.id')]));
    $second->assertOk();

    expect($second->json())->toEqual($first->json());
    expect(SterilizerRecord::count())->toBe($recordsBefore);
    expect(SterilizerDetail::count())->toBe($detailsBefore);

    // The report exposes no write path at all: every mutating verb on the
    // prefix is simply not registered (405 Method Not Allowed, or 404).
    foreach (['postJson', 'putJson', 'patchJson', 'deleteJson'] as $verb) {
        foreach (['/api/sterilizer-reports/summary', '/api/sterilizer-reports/periods', '/api/sterilizer-reports/export'] as $path) {
            $response = $this->actingAs($this->supervisor, 'web')->{$verb}($path, []);

            expect($response->getStatusCode())->toBeIn([404, 405]);
        }
    }
});

// =====================================================================
// Endpoint error codes not reachable from a BDD scenario
// =====================================================================
it('summary: 422 VALIDATION_ERROR when period_id is missing', function () {
    $response = $this->actingAs($this->supervisor, 'web')->getJson('/api/sterilizer-reports/summary');

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'VALIDATION_ERROR');
    $response->assertJsonStructure(['errors' => ['period_id']]);
});

it('summary: 404 NOT_FOUND when period_id does not exist', function () {
    $response = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/sterilizer-reports/summary?'.http_build_query(['period_id' => (string) Str::uuid()]));

    $response->assertStatus(404);
    $response->assertJsonPath('code', 'NOT_FOUND');
});

it('export: 422 VALIDATION_ERROR when period_id is missing, 404 NOT_FOUND when it does not exist', function () {
    $this->actingAs($this->supervisor, 'web')->getJson('/api/sterilizer-reports/export')->assertStatus(422);

    $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/sterilizer-reports/export?'.http_build_query(['period_id' => (string) Str::uuid()]))
        ->assertStatus(404);
});

it('business-units/options: 403 FORBIDDEN for Supervisor and Mill Management — Admin only', function () {
    // Refused by the service, so this 403 does carry the code.
    $supervisor = $this->actingAs($this->supervisor, 'web')->getJson('/api/sterilizer-reports/business-units/options');
    $supervisor->assertStatus(403);
    $supervisor->assertJsonPath('code', 'FORBIDDEN');

    $this->actingAs($this->millManagement, 'web')
        ->getJson('/api/sterilizer-reports/business-units/options')->assertStatus(403);
});

it('rejects unauthenticated requests on every endpoint', function () {
    $this->getJson('/api/sterilizer-reports/business-units/options')->assertStatus(401);
    $this->getJson('/api/sterilizer-reports/periods')->assertStatus(401);
    $this->getJson('/api/sterilizer-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]))->assertStatus(401);
    $this->getJson('/api/sterilizer-reports/export?'.http_build_query(['period_id' => $this->periodA->id]))->assertStatus(401);
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

function laporanSterilizerApiSecondLine(BusinessUnit $businessUnit): Station
{
    $line = ProductionLine::factory()->create([
        'business_unit_id' => $businessUnit->id,
        'name' => 'Line Kedua',
    ]);

    return Station::factory()->forProductionLine($line)->sterilizer()->create();
}

it('production_line_id: menyaring ringkasan ke satu line, dua arah', function () {
    laporanSterilizerRecord($this->stationA, '2026-09-05', [
        ['duration_minutes' => 90, 'number_of_cages' => 10],
        ['duration_minutes' => 90, 'number_of_cages' => 10, 'sterilizer_no' => '2'],
    ], ['sterilizer_id' => 'STR-LINE-A']);

    $stationC = laporanSterilizerApiSecondLine($this->businessUnitA);
    $lineC = (string) $stationC->production_line_id;
    laporanSterilizerRecord($stationC, '2026-09-06', [
        ['duration_minutes' => 50, 'number_of_cages' => 100],
        ['duration_minutes' => 50, 'number_of_cages' => 100, 'sterilizer_no' => '2'],
        ['duration_minutes' => 50, 'number_of_cages' => 100, 'sterilizer_no' => '3'],
    ], ['sterilizer_id' => 'STR-LINE-C']);

    $lineA = (string) $this->stationA->production_line_id;
    $periodId = (string) $this->periodA->id;

    $a = $this->actingAs($this->supervisor, 'web')->getJson('/api/sterilizer-reports/summary?'.http_build_query([
        'period_id' => $periodId,
        'production_line_id' => $lineA,
    ]));

    $a->assertOk();
    expect($a->json('kpi.total_cycles'))->toBe(2);
    expect($a->json('kpi.total_cages'))->toBe(20);

    $c = $this->actingAs($this->supervisor, 'web')->getJson('/api/sterilizer-reports/summary?'.http_build_query([
        'period_id' => $periodId,
        'production_line_id' => $lineC,
    ]));

    $c->assertOk();
    expect($c->json('kpi.total_cycles'))->toBe(3);
    expect($c->json('kpi.total_cages'))->toBe(300);
});

it('production_line_id: menambah blok production_line tanpa mengubah satu pun kunci yang sudah ada', function () {
    laporanSterilizerRecord($this->stationA, '2026-09-05', [
        ['duration_minutes' => 90, 'number_of_cages' => 10],
        ['duration_minutes' => 90, 'number_of_cages' => 10, 'sterilizer_no' => '2'],
    ], ['sterilizer_id' => 'STR-LINE-A']);

    $lineA = (string) $this->stationA->production_line_id;
    $periodId = (string) $this->periodA->id;

    $tanpa = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/sterilizer-reports/summary?'.http_build_query(['period_id' => $periodId]));

    $dengan = $this->actingAs($this->supervisor, 'web')->getJson('/api/sterilizer-reports/summary?'.http_build_query([
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
    laporanSterilizerRecord($this->stationB, '2026-09-05', [
        ['duration_minutes' => 77, 'number_of_cages' => 55],
    ], ['sterilizer_id' => 'STR-MILL-B']);

    $lineB = (string) $this->stationB->production_line_id;

    $response = $this->actingAs($this->supervisor, 'web')->getJson('/api/sterilizer-reports/summary?'.http_build_query([
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $lineB,
    ]));

    // Cakupan mill sudah ditegakkan lebih dulu, jadi menyaring ke line mill
    // lain menghasilkan laporan KOSONG — bukan data Mill Beta.
    $response->assertOk();
    expect($response->json('kpi.total_cycles'))->toBe(0);
    expect($response->json('kpi.total_cages'))->toBe(0);
});

it('production_line_id: ekspor ikut tersaring ke line terpilih', function () {
    laporanSterilizerRecord($this->stationA, '2026-09-05', [
        ['duration_minutes' => 90, 'number_of_cages' => 10],
        ['duration_minutes' => 90, 'number_of_cages' => 10, 'sterilizer_no' => '2'],
    ], ['sterilizer_id' => 'STR-LINE-A']);

    $stationC = laporanSterilizerApiSecondLine($this->businessUnitA);
    $lineC = (string) $stationC->production_line_id;
    laporanSterilizerRecord($stationC, '2026-09-06', [
        ['duration_minutes' => 50, 'number_of_cages' => 100],
        ['duration_minutes' => 50, 'number_of_cages' => 100, 'sterilizer_no' => '2'],
        ['duration_minutes' => 50, 'number_of_cages' => 100, 'sterilizer_no' => '3'],
    ], ['sterilizer_id' => 'STR-LINE-C']);

    $lineA = (string) $this->stationA->production_line_id;
    $periodId = (string) $this->periodA->id;

    $a = $this->actingAs($this->supervisor, 'web')->get('/api/sterilizer-reports/export?'.http_build_query([
        'period_id' => $periodId,
        'format' => 'csv',
        'production_line_id' => $lineA,
    ]));

    $a->assertOk();

    $bodyA = $a->streamedContent();

    expect($bodyA)->toContain('STR-LINE-A');
    expect($bodyA)->not->toContain('STR-LINE-C');

    $c = $this->actingAs($this->supervisor, 'web')->get('/api/sterilizer-reports/export?'.http_build_query([
        'period_id' => $periodId,
        'format' => 'csv',
        'production_line_id' => $lineC,
    ]));

    $c->assertOk();

    $bodyC = $c->streamedContent();

    expect($bodyC)->toContain('STR-LINE-C');
    expect($bodyC)->not->toContain('STR-LINE-A');
});

it('production_line_id: record yang stasiunnya sudah dipindah tetap terhitung di line asalnya', function () {
    laporanSterilizerRecord($this->stationA, '2026-09-05', [
        ['duration_minutes' => 90, 'number_of_cages' => 10],
        ['duration_minutes' => 90, 'number_of_cages' => 10, 'sterilizer_no' => '2'],
    ], ['sterilizer_id' => 'STR-LINE-A']);

    $lineAsal = (string) $this->stationA->production_line_id;

    $lineBaru = ProductionLine::factory()->create([
        'business_unit_id' => $this->businessUnitA->id,
        'name' => 'Line Baru',
    ]);

    $this->stationA->update(['production_line_id' => $lineBaru->id]);

    $asal = $this->actingAs($this->supervisor, 'web')->getJson('/api/sterilizer-reports/summary?'.http_build_query([
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $lineAsal,
    ]));

    $asal->assertOk();
    // Kolom di TABEL RECORD, bukan join ke `stations` — kalau ia join,
    // angka ini pindah ke Line Baru dan sejarah periode lama tertulis ulang.
    expect($asal->json('kpi.total_cycles'))->toBe(2);
    expect($asal->json('kpi.total_cages'))->toBe(20);

    $baru = $this->actingAs($this->supervisor, 'web')->getJson('/api/sterilizer-reports/summary?'.http_build_query([
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => (string) $lineBaru->id,
    ]));

    $baru->assertOk();
    expect($baru->json('kpi.total_cycles'))->toBe(0);
    expect($baru->json('kpi.total_cages'))->toBe(0);
});
