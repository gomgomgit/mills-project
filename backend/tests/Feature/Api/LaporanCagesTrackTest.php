<?php

/**
 * LaporanCagesTrackTest (Feature/Api) — screen-130--laporan-cages-track-web /
 * usecase-130--laporan-cages-track-web (Laporan Periode Cages & Tracks).
 *
 * Integration tests for the four GET endpoints under
 * /api/cages-track-reports (App\Http\Controllers\Api\
 * CagesTrackReportController), one test per test_scenarios entry, running
 * each scenario's api_test steps IN ORDER and feeding the real response of
 * step N into step N+1 exactly as the `{{stepN.field}}` references
 * prescribe. Exercises the real route -> 'auth:web,sanctum' +
 * 'role:supervisor,mill_management,admin,operator' -> controller ->
 * CagesTrackReportService -> Eloquent chain, mirroring
 * tests/Feature/Api/LaporanSterilizerTest.php (screen-129, this screen's
 * twin).
 *
 * TWO GUARDS, TWO SHAPES — asserted separately on purpose:
 *   - business_unit_id from the client is IGNORED for Supervisor / Mill
 *     Management: probing another mill answers 200 with the caller's OWN
 *     data, deliberately NOT 403 (a 403 would confirm the other mill
 *     exists). The two-step shape of that scenario is load-bearing and
 *     must not be collapsed into one request.
 *   - period_id belonging to another mill IS refused with 403 FORBIDDEN by
 *     CagesTrackReportService::authorizePeriod().
 *
 * ON ASSERTING `code` FOR 403: a refusal raised by EnsureRole (at the route
 * layer) carries only { message } — that middleware builds its own JSON
 * without going through ApiExceptionHandler — so those tests assert the
 * status alone. A refusal raised by the service (AuthorizationException)
 * does carry code = 'FORBIDDEN' and is asserted in full.
 *
 * OPERATOR IS ACCEPTED SINCE 2026-09-24 (screen-136--laporan-cages-track-
 * mobile), mirroring the widening /api/sterilizer-reports/* received on
 * 2026-09-23 for screen-135. It is treated exactly like Supervisor / Mill
 * Management: MILL-BOUND. The widening covers this API prefix ONLY — the
 * web route /reports/cages-track and its Livewire page stay without
 * Operator, and business-units/options stays Admin-only inside the service.
 * Since the route middleware now admits the role, an Operator's 403 on that
 * picker is raised by the service and therefore carries code = 'FORBIDDEN'.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\CagesTippedTime;
use App\Models\CagesTrackRecord;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * One cages_track_records header plus one cages_tipped_times row per entry
 * of $details.
 *
 * `cages_tipped` defaults to 999 ON PURPOSE: it is the header summary field
 * the report must NEVER read, so every fixture carries a value that makes a
 * regression reading it obvious instead of plausible.
 *
 * @param  list<array{hour: int, cages?: int, remain?: int}>  $details
 */
function laporanCagesTrackRecord(Station $station, string $date, array $details = [], array $overrides = []): CagesTrackRecord
{
    $record = CagesTrackRecord::factory()->forStation($station)->onDate($date)->create(array_merge([
        'tippler_start_time' => $date.' 06:00:00',
        'tippler_stop_time' => $date.' 18:00:00',
        'cages_out' => 0,
        'cages_tipped' => 999,
    ], $overrides));

    foreach ($details as $detail) {
        CagesTippedTime::factory()->forRecord($record)->create([
            'tipped_hour' => $detail['hour'],
            'total_cages' => $detail['cages'] ?? 1,
            'cages_remain' => $detail['remain'] ?? 10,
            'checked_cage_numbers' => $detail['numbers'] ?? '1,2',
        ]);
    }

    return $record;
}

/** Shorthand: tipping hours with one cage each. */
function laporanCagesTrackHours(Station $station, string $date, array $hours, array $overrides = []): CagesTrackRecord
{
    return laporanCagesTrackRecord($station, $date, array_map(
        fn ($hour) => ['hour' => $hour],
        $hours,
    ), $overrides);
}

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->cagesTrack()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->cagesTrack()->create();

    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    // Admin is bound to no mill at all — hence the mill picker.
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('cages-track')
        ->range('2026-03-01', '2026-03-31')
        ->named('Periode Maret Alpha')
        ->open()
        ->create();
});

// =====================================================================
// Scenario 1: "berhasil sebagai Supervisor atau Mill Management"
// =====================================================================
it('berhasil: periods -> summary for a Supervisor and a Mill Management, chained on the real responses', function () {
    laporanCagesTrackRecord($this->stationA, '2026-03-02', [
        ['hour' => 6, 'cages' => 30, 'remain' => 12],
        ['hour' => 7, 'cages' => 20, 'remain' => 16],
        ['hour' => 8, 'cages' => 10, 'remain' => 20],
    ], ['cages_out' => 55]);
    laporanCagesTrackRecord($this->stationA, '2026-03-05', [
        ['hour' => 6, 'cages' => 25, 'remain' => 14],
        ['hour' => 14, 'cages' => 15, 'remain' => 18],
    ], ['cages_out' => 35]);

    foreach ([$this->supervisor, $this->millManagement] as $user) {
        // Step 1 — GET /api/cages-track-reports/periods
        $periods = $this->actingAs($user, 'web')->getJson('/api/cages-track-reports/periods');
        $periods->assertOk();
        $periods->assertJsonStructure([
            'data' => [['id', 'name', 'start_date', 'end_date', 'status', 'station_type', 'station_type_label']],
        ]);

        $periodId = $periods->json('data.0.id');

        // Step 2 — GET /api/cages-track-reports/summary?period_id={{step1.data[0].id}}
        $summary = $this->actingAs($user, 'web')
            ->getJson('/api/cages-track-reports/summary?'.http_build_query(['period_id' => $periodId]));

        $summary->assertOk();
        $summary->assertJsonStructure([
            'period' => ['id', 'name', 'start_date', 'end_date', 'status', 'business_unit_name'],
            'kpi' => [
                'total_cages_tipped', 'total_cages_out', 'avg_cages_per_day', 'peak_hour',
                'peak_hour_cages', 'idle_operating_hours', 'longest_gap_hours', 'longest_gap_date',
                'avg_tippler_duration_hours', 'days_with_records', 'days_without_valid_window',
            ],
            'hourly' => [['hour', 'cages', 'within_operating_window']],
            'daily' => [['date', 'cages_tipped', 'cages_out', 'operating_hours', 'idle_operating_hours', 'longest_gap_hours', 'min_remaining']],
            'queue' => ['min_remaining', 'avg_remaining'],
            'total' => ['cages_tipped', 'cages_out', 'days'],
        ]);

        $summary->assertJsonPath('period.business_unit_name', 'Mill Alpha');
        $summary->assertJsonPath('kpi.total_cages_tipped', 100);
        $summary->assertJsonPath('kpi.total_cages_out', 90);
        $summary->assertJsonPath('kpi.avg_cages_per_day', 50);
        $summary->assertJsonPath('kpi.peak_hour', 6);
        $summary->assertJsonPath('kpi.peak_hour_cages', 55);
        $summary->assertJsonPath('kpi.longest_gap_hours', 8);
        $summary->assertJsonPath('kpi.longest_gap_date', '2026-03-05');
        $summary->assertJsonPath('kpi.avg_tippler_duration_hours', 12);
        $summary->assertJsonPath('kpi.days_without_valid_window', 0);
        $summary->assertJsonCount(24, 'hourly');
        $summary->assertJsonCount(2, 'daily');
        $summary->assertJsonPath('queue.min_remaining', 12);
        $summary->assertJsonPath('queue.avg_remaining', 16);
    }
});

// =====================================================================
// Scenario 2: "berhasil sebagai Admin"
// =====================================================================
it('admin: options -> periods of the chosen mill -> summary computed only from that mill', function () {
    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('cages-track')
        ->range('2026-03-01', '2026-03-31')->named('Periode Maret Beta')->create();

    laporanCagesTrackRecord($this->stationA, '2026-03-10', [['hour' => 6, 'cages' => 12]], ['cages_out' => 9]);
    laporanCagesTrackRecord($this->stationB, '2026-03-10', [['hour' => 6, 'cages' => 500]], ['cages_out' => 500]);

    // Step 1 — GET /api/cages-track-reports/business-units/options
    $options = $this->actingAs($this->admin, 'web')->getJson('/api/cages-track-reports/business-units/options');
    $options->assertOk();
    $options->assertJsonStructure(['data' => [['id', 'name']]]);

    $millAId = collect($options->json('data'))->firstWhere('name', 'Mill Alpha')['id'];

    // Step 2 — periods?business_unit_id={{step1.data[...].id}}
    $periods = $this->actingAs($this->admin, 'web')
        ->getJson('/api/cages-track-reports/periods?'.http_build_query(['business_unit_id' => $millAId]));

    $periods->assertOk();
    $periods->assertJsonCount(1, 'data');
    $periods->assertJsonPath('data.0.id', (string) $this->periodA->id);
    expect(collect($periods->json('data'))->pluck('id')->all())->not->toContain((string) $periodB->id);

    // Step 3 — summary?period_id={{step2.data[0].id}}&business_unit_id={{step1...}}
    $summary = $this->actingAs($this->admin, 'web')
        ->getJson('/api/cages-track-reports/summary?'.http_build_query([
            'period_id' => $periods->json('data.0.id'),
            'business_unit_id' => $millAId,
        ]));

    $summary->assertOk();
    $summary->assertJsonPath('period.business_unit_name', 'Mill Alpha');
    $summary->assertJsonPath('kpi.total_cages_tipped', 12);
    $summary->assertJsonPath('kpi.total_cages_out', 9);
});

// =====================================================================
// Scenario 3: "ekspor rincian per jam ke CSV"
// =====================================================================
it('ekspor: periods -> export streams one CSV line per tipping hour with the record context repeated', function () {
    laporanCagesTrackRecord($this->stationA, '2026-03-02', [
        ['hour' => 6, 'cages' => 3],
        ['hour' => 7, 'cages' => 2],
        ['hour' => 8, 'cages' => 1],
    ], ['cages_track_number' => 'CT-EXPORT-001', 'cages_out' => 12, 'note' => 'Catatan harian']);

    // Step 1
    $periods = $this->actingAs($this->supervisor, 'web')->getJson('/api/cages-track-reports/periods');
    $periods->assertOk();

    // Step 2 — export?period_id={{step1.data[0].id}}&format=csv
    $export = $this->actingAs($this->supervisor, 'web')
        ->get('/api/cages-track-reports/export?'.http_build_query([
            'period_id' => $periods->json('data.0.id'),
            'format' => 'csv',
        ]));

    $export->assertOk();
    expect($export->headers->get('Content-Type'))->toContain('text/csv');
    expect($export->headers->get('Content-Disposition'))->toContain('attachment');

    $body = $export->streamedContent();
    $lines = array_values(array_filter(explode("\n", trim($body))));

    // Header + one line per TIPPING HOUR, not one per daily record.
    expect($lines)->toHaveCount(4);
    expect($lines[0])->toContain('Nomor Cages Track');
    expect($lines[0])->toContain('Lori Ditumpahkan');

    foreach (array_slice($lines, 1) as $line) {
        // Context columns repeated verbatim on every hourly line.
        expect($line)->toContain('CT-EXPORT-001');
        expect($line)->toContain('2026-03-02');
        expect($line)->toContain('Catatan harian');
    }

    // Jam HH:MM seperti slot ekspor lain (temuan audit 2026-10-04 #8d).
    expect($lines[1])->toContain('06:00');
    expect($lines[2])->toContain('07:00');
    expect($lines[3])->toContain('08:00');
});

// =====================================================================
// Scenario 4: "Periode tanpa data"
// =====================================================================
it('periode tanpa data: summary answers 200 with zeroed figures, explicit nulls and 24 empty hours', function () {
    // Data outside the period's range only.
    laporanCagesTrackHours($this->stationA, '2026-02-28', [6]);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/cages-track-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();
    $summary->assertJsonPath('kpi.total_cages_tipped', 0);
    $summary->assertJsonPath('kpi.total_cages_out', 0);
    $summary->assertJsonPath('kpi.avg_cages_per_day', 0);
    $summary->assertJsonPath('kpi.peak_hour', null);
    $summary->assertJsonPath('kpi.peak_hour_cages', 0);
    $summary->assertJsonPath('kpi.idle_operating_hours', 0);
    // null, not 0 — "cannot be computed", not "no gap" / "never ran".
    $summary->assertJsonPath('kpi.longest_gap_hours', null);
    $summary->assertJsonPath('kpi.longest_gap_date', null);
    $summary->assertJsonPath('kpi.avg_tippler_duration_hours', null);
    $summary->assertJsonPath('queue.min_remaining', null);
    $summary->assertJsonPath('queue.avg_remaining', null);
    $summary->assertJsonPath('daily', []);
    $summary->assertJsonPath('total.days', 0);
    $summary->assertJsonCount(24, 'hourly');
    expect(collect($summary->json('hourly'))->pluck('cages')->unique()->all())->toBe([0]);
});

// =====================================================================
// Scenario 5: "Hari dengan record tetapi tanpa rincian per jam"
// =====================================================================
it('hari tanpa rincian: the date still gets a daily row of 0 and still divides the daily average', function () {
    laporanCagesTrackRecord($this->stationA, '2026-03-02', [['hour' => 6, 'cages' => 40]]);
    // A record, but no hourly rows at all.
    laporanCagesTrackRecord($this->stationA, '2026-03-03', []);
    laporanCagesTrackRecord($this->stationA, '2026-03-04', [['hour' => 6, 'cages' => 50]]);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/cages-track-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();
    $summary->assertJsonCount(3, 'daily');
    $summary->assertJsonPath('daily.1.date', '2026-03-03');
    $summary->assertJsonPath('daily.1.cages_tipped', 0);
    // 90 / 3 = 30, not 90 / 2 = 45. Dropping the empty day would make the
    // daily average look better than reality.
    $summary->assertJsonPath('kpi.days_with_records', 3);
    $summary->assertJsonPath('kpi.avg_cages_per_day', 30);
});

// =====================================================================
// Scenario 6: "Seluruh hari tanpa waktu berhenti tippler"
// =====================================================================
it('tanpa waktu berhenti: avg_tippler_duration_hours is null and days_without_valid_window counts them all', function () {
    foreach (['2026-03-02', '2026-03-03', '2026-03-04'] as $date) {
        laporanCagesTrackRecord($this->stationA, $date, [['hour' => 6, 'cages' => 5]], [
            'tippler_start_time' => $date.' 06:00:00',
            'tippler_stop_time' => null,
        ]);
    }

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/cages-track-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();
    // null, NOT 0 — 0 would read as "the tippler never ran".
    $summary->assertJsonPath('kpi.avg_tippler_duration_hours', null);
    $summary->assertJsonPath('kpi.days_without_valid_window', 3);
    $summary->assertJsonPath('kpi.days_with_records', 3);
    // Dates with no computable window contribute ZERO idle hours, never an
    // estimate.
    $summary->assertJsonPath('kpi.idle_operating_hours', 0);
    $summary->assertJsonPath('daily.0.operating_hours', null);
    $summary->assertJsonPath('daily.0.idle_operating_hours', null);
    // The tipping figures themselves are unaffected.
    $summary->assertJsonPath('kpi.total_cages_tipped', 15);
});

// =====================================================================
// Scenario 7: "Penumpahan hanya pada satu jam"
// =====================================================================
it('satu jam saja: longest_gap_hours and longest_gap_date are null rather than zero', function () {
    laporanCagesTrackRecord($this->stationA, '2026-03-02', [['hour' => 10, 'cages' => 8]]);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/cages-track-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();
    $summary->assertJsonPath('kpi.longest_gap_hours', null);
    $summary->assertJsonPath('kpi.longest_gap_date', null);
    $summary->assertJsonPath('daily.0.longest_gap_hours', null);
    // Explicitly not the falsy zero the screen would print as "0 jam".
    expect($summary->json('kpi.longest_gap_hours'))->not->toBe(0);
});

// =====================================================================
// Scenario 8: "Operasi melewati tengah malam"
// =====================================================================
it('lintas tengah malam: the duration is positive and the operating hour set wraps to {22,23,0,1,2,3}', function () {
    laporanCagesTrackRecord($this->stationA, '2026-03-02', [
        ['hour' => 1, 'cages' => 3],
        ['hour' => 22, 'cages' => 2],
    ], [
        'tippler_start_time' => '2026-03-02 22:00:00',
        'tippler_stop_time' => '2026-03-03 04:00:00',
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/cages-track-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();
    // 6.0, never -18: a timestamp difference, not an hour-component one.
    $summary->assertJsonPath('daily.0.operating_hours', 6);
    $summary->assertJsonPath('kpi.avg_tippler_duration_hours', 6);
    expect($summary->json('daily.0.operating_hours'))->toBeGreaterThan(0);

    $withinWindow = collect($summary->json('hourly'))
        ->filter(fn ($row) => $row['within_operating_window'])
        ->pluck('hour')
        ->sort()
        ->values()
        ->all();

    expect($withinWindow)->toBe([0, 1, 2, 3, 22, 23]);
    // Idle inside that circular set: {23, 0, 2, 3} = 4.
    $summary->assertJsonPath('kpi.idle_operating_hours', 4);
});

// =====================================================================
// Scenario 9: "Admin belum memilih mill"
// =====================================================================
it('admin tanpa mill: periods answers 422 VALIDATION_ERROR on business_unit_id, not an empty list', function () {
    $periods = $this->actingAs($this->admin, 'web')->getJson('/api/cages-track-reports/periods');

    // Never a silently empty list: an Admin who has not picked a mill is
    // told so on the field that is missing.
    $periods->assertStatus(422);
    $periods->assertJsonPath('code', 'VALIDATION_ERROR');
    $periods->assertJsonStructure(['message', 'code', 'errors' => ['business_unit_id']]);
    $periods->assertJsonMissingPath('data');
});

// =====================================================================
// Scenario 10: "Akun terikat mill tetapi mill-nya kosong"
// =====================================================================
it('akun tanpa mill: a Supervisor whose business_unit_id is NULL gets 422 telling them to contact Admin', function () {
    $noMill = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $periods = $this->actingAs($noMill, 'web')->getJson('/api/cages-track-reports/periods');

    // FAIL CLOSED — never a fallback to the all-mills list. That the list
    // is not even built is proven by the spy in
    // tests/Unit/Services/CagesTrackReportServiceTest.php (case 7).
    $periods->assertStatus(422);
    $periods->assertJsonPath('code', 'VALIDATION_ERROR');
    $periods->assertJsonStructure(['errors' => ['business_unit_id']]);
    expect($periods->json('errors.business_unit_id.0'))->toContain('Hubungi Admin');
    // And it is not silently upgraded into the Admin mill picker.
    $this->actingAs($noMill, 'web')
        ->getJson('/api/cages-track-reports/business-units/options')
        ->assertStatus(403);
});

// =====================================================================
// Scenario 11: "Mill belum punya periode"
// =====================================================================
it('belum ada periode: periods answers 200 with an empty list, not a 404', function () {
    $this->periodA->delete();
    // A period of another station type must not rescue the picker.
    Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('sterilizer')
        ->range('2026-03-01', '2026-03-31')->create();

    $periods = $this->actingAs($this->supervisor, 'web')->getJson('/api/cages-track-reports/periods');

    $periods->assertOk();
    $periods->assertExactJson(['data' => []]);
});

// =====================================================================
// Scenario 12: "Mencoba melihat mill lain"
// =====================================================================
it('mill lain: business_unit_id is ignored (200, own data) while another mill\'s period_id is refused 403', function () {
    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('cages-track')
        ->range('2026-03-01', '2026-03-31')->named('Periode Maret Beta')->create();

    laporanCagesTrackRecord($this->stationA, '2026-03-10', [['hour' => 6, 'cages' => 10]]);
    laporanCagesTrackRecord($this->stationB, '2026-03-10', [['hour' => 6, 'cages' => 900]]);

    // Step 1 — periods?business_unit_id=<BU-B>: 200 with the caller's OWN
    // periods. Deliberately not a 403: a 403 would confirm BU-B exists, and
    // there is no access attempt to refuse because the parameter is never
    // used for this role.
    $periods = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/cages-track-reports/periods?'.http_build_query([
            'business_unit_id' => $this->businessUnitB->id,
        ]));

    $periods->assertOk();
    $periods->assertJsonCount(1, 'data');
    $periods->assertJsonPath('data.0.id', (string) $this->periodA->id);

    // Step 2 — summary?period_id=<BU-B period>: 403 FORBIDDEN. THIS is the
    // real leak path, so it is refused outright.
    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/cages-track-reports/summary?'.http_build_query([
            'period_id' => $periodB->id,
            'business_unit_id' => $this->businessUnitB->id,
        ]));

    $summary->assertStatus(403);
    $summary->assertJsonPath('code', 'FORBIDDEN');
    $summary->assertJsonMissingPath('kpi');

    // Mill Management is bound the same way, on summary and on export.
    $this->actingAs($this->millManagement, 'web')
        ->getJson('/api/cages-track-reports/summary?'.http_build_query(['period_id' => $periodB->id]))
        ->assertStatus(403);
    $this->actingAs($this->millManagement, 'web')
        ->getJson('/api/cages-track-reports/export?'.http_build_query(['period_id' => $periodB->id]))
        ->assertStatus(403);

    // And the caller's own figures never picked up BU-B's 900 cages.
    $own = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/cages-track-reports/summary?'.http_build_query([
            'period_id' => $this->periodA->id,
            'business_unit_id' => $this->businessUnitB->id,
        ]));
    $own->assertOk();
    $own->assertJsonPath('kpi.total_cages_tipped', 10);
});

// =====================================================================
// Scenario 13: "Operator membuka laporan dari ponsel"
// (screen-136--laporan-cages-track-mobile — this prefix's role list was
// widened to include `operator` on 2026-09-24, and its guard became
// 'auth:web,sanctum' because mobile carries a Sanctum token rather than a
// session cookie. Product decision, same as screen-135 for Sterilizer: the
// person who enters the data is entitled to see the result of their own
// work.)
//
// WHAT CHANGED AND WHAT DID NOT — the distinction is the whole point:
//   - API  /api/cages-track-reports/*  : Operator is now ACCEPTED (200),
//     scoped to their OWN mill exactly like Supervisor / Mill Management.
//   - WEB  /reports/cages-track        : Operator is still REFUSED. There
//     is no web UI for this actor, so there is nothing to widen — that
//     half lives in tests/Feature/Livewire/LaporanCagesTrackTest.php and
//     e2e-web/tests/laporan-cages-track.spec.ts, and must stay red-free
//     without being touched.
//   - business-units/options           : still 403 for Operator. It is the
//     ADMIN mill picker (CagesTrackReportService::businessUnitOptions(),
//     deliberately NOT widened); a mill-bound actor has nothing to pick,
//     and handing them every mill's name is the leak that endpoint exists
//     to avoid. Only the SOURCE of that 403 moved — the service raises it
//     now that the route middleware admits the role — so unlike before it
//     carries code = 'FORBIDDEN' and is asserted in full.
// =====================================================================
it('operator: api answers 200 scoped to the operator own mill, and the admin mill picker stays refused', function () {
    laporanCagesTrackRecord($this->stationA, '2026-03-10', [['hour' => 6, 'cages' => 10]], ['cages_out' => 8]);

    // Another mill's data must never surface, whatever the request says.
    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('cages-track')
        ->range('2026-03-01', '2026-03-31')->named('Periode Maret Beta')->open()->create();
    laporanCagesTrackRecord($this->stationB, '2026-03-10', [['hour' => 6, 'cages' => 900]], ['cages_out' => 900]);

    // Sanctum token, not a session cookie — the mobile path, which is the
    // reason the group's guard is 'auth:web,sanctum' at all.
    Sanctum::actingAs($this->operator, ['*']);

    // Step 1 — periods: own mill only, even when ANOTHER mill is asked for.
    // 200 with the caller's own list, deliberately not 403: a 403 would
    // confirm BU-B exists, and there is no access attempt to refuse because
    // the parameter is never used for this role.
    $periods = $this->getJson('/api/cages-track-reports/periods?'.http_build_query([
        'business_unit_id' => $this->businessUnitB->id,
    ]));
    $periods->assertStatus(200);

    $periodIds = collect($periods->json('data'))->pluck('id')->all();
    expect($periodIds)->toContain((string) $this->periodA->id);
    expect($periodIds)->not->toContain((string) $periodB->id);

    // Step 2 — summary while naming another mill: 200 with ITS OWN data.
    // This is what locks Operator into resolveBusinessUnit()'s MILL-BOUND
    // branch; in the Admin branch the client's business_unit_id would be
    // honoured and BU-B's 900 would come back here.
    $summary = $this->getJson('/api/cages-track-reports/summary?'.http_build_query([
        'period_id' => $this->periodA->id,
        'business_unit_id' => $this->businessUnitB->id,
    ]));
    $summary->assertStatus(200);
    $summary->assertJsonPath('period.business_unit_name', 'Mill Alpha');
    $summary->assertJsonPath('kpi.total_cages_tipped', 10);
    $summary->assertJsonPath('kpi.total_cages_out', 8);

    // Step 3 — export streams, same as for any other report reader.
    $this->getJson('/api/cages-track-reports/export?'.http_build_query([
        'period_id' => $this->periodA->id,
    ]))->assertStatus(200);

    // Another mill's PERIOD ID is still a hard 403 — the one guard that
    // must answer no, because a period id is a concrete handle to another
    // mill's data (authorizePeriod(), unchanged by the widening).
    $forbidden = $this->getJson('/api/cages-track-reports/summary?'.http_build_query([
        'period_id' => $periodB->id,
    ]));
    $forbidden->assertStatus(403);
    $forbidden->assertJsonPath('code', 'FORBIDDEN');
    $forbidden->assertJsonMissingPath('kpi');

    $this->getJson('/api/cages-track-reports/export?'.http_build_query(['period_id' => $periodB->id]))
        ->assertStatus(403);

    // The Admin-only mill picker stays refused — see the header note.
    $picker = $this->getJson('/api/cages-track-reports/business-units/options');
    $picker->assertStatus(403);
    $picker->assertJsonPath('code', 'FORBIDDEN');
    $picker->assertJsonMissingPath('data');
});

// Scenario 13b — FAIL CLOSED for an Operator whose account has no mill.
// The widening admits the role; it does not invent a mill for it. A broken
// master-data row must end as "Hubungi Admin", never as the all-mills list.
it('operator tanpa mill: fails closed with 422 VALIDATION_ERROR, never the all-mills list', function () {
    $noMillOperator = User::factory()->role(UserRole::Operator)->create(['business_unit_id' => null]);

    Sanctum::actingAs($noMillOperator, ['*']);

    $periods = $this->getJson('/api/cages-track-reports/periods');

    $periods->assertStatus(422);
    $periods->assertJsonPath('code', 'VALIDATION_ERROR');
    $periods->assertJsonStructure(['errors' => ['business_unit_id']]);
    expect($periods->json('errors.business_unit_id.0'))->toContain('Hubungi Admin');
    $periods->assertJsonMissingPath('data');

    // And it is not silently upgraded into the Admin mill picker.
    $this->getJson('/api/cages-track-reports/business-units/options')->assertStatus(403);
});

// =====================================================================
// Scenario 14: "Periode tertutup"
// =====================================================================
it('periode tertutup: summary renders in full and export still streams', function () {
    $closed = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('cages-track')
        ->range('2026-04-01', '2026-04-30')->named('Periode April Tertutup')->closed()->create();

    laporanCagesTrackRecord($this->stationA, '2026-04-10', [
        ['hour' => 6, 'cages' => 4],
        ['hour' => 9, 'cages' => 6],
    ], ['cages_out' => 11]);

    // Step 1
    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/cages-track-reports/summary?'.http_build_query(['period_id' => $closed->id]));

    $summary->assertOk();
    $summary->assertJsonPath('period.status', 'closed');
    $summary->assertJsonPath('kpi.total_cages_tipped', 10);
    $summary->assertJsonPath('kpi.total_cages_out', 11);

    // Step 2 — export?period_id={{step1.period.id}}
    $export = $this->actingAs($this->supervisor, 'web')
        ->get('/api/cages-track-reports/export?'.http_build_query([
            'period_id' => $summary->json('period.id'),
            'format' => 'csv',
        ]));

    // The period lock governs WRITING data, not reading a report — no
    // PERIOD_CLOSED here.
    $export->assertOk();
    expect($export->headers->get('Content-Disposition'))->toContain('attachment');
    expect(array_values(array_filter(explode("\n", trim($export->streamedContent())))))->toHaveCount(3);
});

// =====================================================================
// Scenario 15: "periode yang tidak mencakup Cages & Tracks tidak boleh muncul"
// =====================================================================
it('pemilih periode: only periods with a cages-track period_stations row are offered, newest first', function () {
    $allTypes = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType(null)
        ->range('2026-05-01', '2026-05-31')->named('Periode Mei Semua Stasiun')->create();
    $sterilizer = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('sterilizer')
        ->range('2026-02-01', '2026-02-28')->named('Periode Februari Sterilizer')->create();

    $periods = $this->actingAs($this->supervisor, 'web')->getJson('/api/cages-track-reports/periods');

    $periods->assertOk();

    $ids = collect($periods->json('data'))->pluck('id')->all();

    expect($ids)->toContain((string) $allTypes->id);
    expect($ids)->toContain((string) $this->periodA->id);
    expect($ids)->not->toContain((string) $sterilizer->id);
    // start_date descending: May before March.
    expect($ids)->toBe([(string) $allTypes->id, (string) $this->periodA->id]);

    // KONTRAK API TETAP DATAR setelah pemisahan periods/period_stations
    // (2026-09-25): stationType(null) kini berarti "satu baris per jenis
    // stasiun", jadi opsi ini adalah pasangan (periode, cages-track) —
    // station_type selalu terisi dan label 'Semua Stasiun' sudah tidak ada.
    $all = collect($periods->json('data'))->firstWhere('id', (string) $allTypes->id);

    expect($all['station_type'])->toBe('cages-track');
    expect($all['station_type_label'])->toBe('Cages Track');
});

// =====================================================================
// Scenario (BARU 2026-09-26): periode tanpa baris period_stations untuk
// cages-track tidak boleh muncul — perilaku yang DULU dijamin cabang
// orWhereNull('station_type') dan kini sengaja dibuang.
// =====================================================================
it('pemilih periode: periode tanpa baris cages-track tidak ditawarkan', function () {
    $otherTypesOnly = Period::factory()->forBusinessUnit($this->businessUnitA)
        ->stationTypes(['sterilizer', 'boiler-room'])
        ->range('2026-05-01', '2026-05-31')->named('Periode Tanpa Cages')->create();
    $noStations = Period::factory()->forBusinessUnit($this->businessUnitA)
        ->noStations()->range('2026-06-01', '2026-06-30')->named('Periode Tanpa Stasiun')->create();

    $periods = $this->actingAs($this->supervisor, 'web')->getJson('/api/cages-track-reports/periods');

    $periods->assertOk();

    $ids = collect($periods->json('data'))->pluck('id')->all();

    expect($ids)->not->toContain((string) $otherTypesOnly->id);
    expect($ids)->not->toContain((string) $noStations->id);
    expect($ids)->toBe([(string) $this->periodA->id]);
});

// =====================================================================
// Scenario (BARU 2026-09-26): status yang dilaporkan adalah status stasiun
// ini di dalam periode itu, bukan status periode.
// =====================================================================
it('status: payload memakai status cages-track, bukan status stasiun lain di periode yang sama', function () {
    $period = Period::factory()->forBusinessUnit($this->businessUnitA)
        ->noStations()->range('2026-04-01', '2026-04-30')->named('Periode Campuran')->create();

    PeriodStation::factory()->forPeriod($period)->stationType('cages-track')->open()->create();
    PeriodStation::factory()->forPeriod($period)->stationType('sterilizer')->closed()->create();

    $periods = $this->actingAs($this->supervisor, 'web')->getJson('/api/cages-track-reports/periods');
    $periods->assertOk();

    expect(collect($periods->json('data'))->firstWhere('id', (string) $period->id)['status'])->toBe('open');
});

// =====================================================================
// Scenario 16: "rentang periode harus inklusif"
// =====================================================================
it('rentang inklusif: both bounds count, and membership follows date rather than created_at', function () {
    laporanCagesTrackRecord($this->stationA, '2026-03-01', [['hour' => 6, 'cages' => 4]]);   // exactly on start_date
    laporanCagesTrackRecord($this->stationA, '2026-03-31', [['hour' => 7, 'cages' => 6]]);   // exactly on end_date
    laporanCagesTrackRecord($this->stationA, '2026-02-28', [['hour' => 6, 'cages' => 99]]);  // one day before
    laporanCagesTrackRecord($this->stationA, '2026-04-01', [['hour' => 6, 'cages' => 99]]);  // one day after

    $lateSync = laporanCagesTrackRecord($this->stationA, '2026-03-15', [['hour' => 6, 'cages' => 5]]);
    DB::table('cages_track_records')->where('id', $lateSync->id)->update(['created_at' => '2026-05-05 08:00:00']);

    $enteredDuringPeriod = laporanCagesTrackRecord($this->stationA, '2026-02-20', [['hour' => 6, 'cages' => 77]]);
    DB::table('cages_track_records')->where('id', $enteredDuringPeriod->id)->update(['created_at' => '2026-03-10 08:00:00']);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/cages-track-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();
    $summary->assertJsonPath('kpi.total_cages_tipped', 15);
    expect(collect($summary->json('daily'))->pluck('date')->all())
        ->toBe(['2026-03-01', '2026-03-15', '2026-03-31']);
});

// =====================================================================
// Scenario 17: "angka ringkasan record harian tidak boleh dipakai"
// =====================================================================
it('angka ringkasan: every tipping figure comes from the hourly rows, never from cages_track_records.cages_tipped', function () {
    laporanCagesTrackRecord($this->stationA, '2026-03-02', [
        ['hour' => 6, 'cages' => 30],
        ['hour' => 7, 'cages' => 40],
    ], ['cages_tipped' => 100]);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/cages-track-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();
    $summary->assertJsonPath('kpi.total_cages_tipped', 70);
    $summary->assertJsonPath('total.cages_tipped', 70);
    $summary->assertJsonPath('daily.0.cages_tipped', 70);
    // The header's 100 is not exposed anywhere in the payload.
    expect(json_encode($summary->json()))->not->toContain('100,');
    expect($summary->json('kpi.total_cages_tipped'))->not->toBe(100);
});

// =====================================================================
// Scenario 18: "lori keluar tidak boleh terkalikan jumlah baris rincian"
// =====================================================================
it('lori keluar: one record with cages_out 50 and 8 hourly rows reports 50, not 400', function () {
    laporanCagesTrackRecord($this->stationA, '2026-03-02', [
        ['hour' => 0, 'cages' => 1], ['hour' => 1, 'cages' => 1],
        ['hour' => 2, 'cages' => 1], ['hour' => 3, 'cages' => 1],
        ['hour' => 4, 'cages' => 1], ['hour' => 5, 'cages' => 1],
        ['hour' => 6, 'cages' => 1], ['hour' => 7, 'cages' => 1],
    ], ['cages_out' => 50]);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/cages-track-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();
    // The header grain: summed by iterating RECORDS, never over a JOIN.
    $summary->assertJsonPath('kpi.total_cages_out', 50);
    $summary->assertJsonPath('total.cages_out', 50);
    $summary->assertJsonPath('daily.0.cages_out', 50);
    expect($summary->json('kpi.total_cages_out'))->not->toBe(400);
});

// =====================================================================
// Scenario 19: "jam tanpa penumpahan tidak boleh dihitung di luar jam operasi"
// =====================================================================
it('jam menganggur: only the empty hours inside the tippler window count, and the rest are flagged out-of-window', function () {
    laporanCagesTrackRecord($this->stationA, '2026-03-02', [
        ['hour' => 6, 'cages' => 3],
        ['hour' => 7, 'cages' => 3],
        ['hour' => 8, 'cages' => 3],
    ], [
        'tippler_start_time' => '2026-03-02 06:00:00',
        'tippler_stop_time' => '2026-03-02 18:00:00',
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/cages-track-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();
    // Hours 9..17 only. Counting all 24 would give 21 and make a one-shift
    // mill look permanently idle.
    $summary->assertJsonPath('kpi.idle_operating_hours', 9);
    $summary->assertJsonPath('daily.0.idle_operating_hours', 9);
    expect($summary->json('kpi.idle_operating_hours'))->toBeLessThan(24);

    foreach ($summary->json('hourly') as $row) {
        expect($row['within_operating_window'])->toBe($row['hour'] >= 6 && $row['hour'] <= 17);
    }
});

// =====================================================================
// Scenario 20: "jeda terpanjang tidak boleh dihitung lintas hari"
// =====================================================================
it('jeda terpanjang: measured within a single date only, never between the end of one day and the start of the next', function () {
    // Day A ends in the morning, day B starts at night — a naive
    // cross-date gap would report 13 hours.
    laporanCagesTrackHours($this->stationA, '2026-03-02', [6, 7]);
    laporanCagesTrackHours($this->stationA, '2026-03-03', [20, 21]);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/cages-track-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();
    $summary->assertJsonPath('kpi.longest_gap_hours', 1);
    // One single date, never a two-day span.
    expect($summary->json('kpi.longest_gap_date'))->toBeIn(['2026-03-02', '2026-03-03']);
    $summary->assertJsonPath('daily.0.longest_gap_hours', 1);
    $summary->assertJsonPath('daily.1.longest_gap_hours', 1);
});

// =====================================================================
// Scenario 21: "antrean tersisa tidak boleh diakumulasi"
// =====================================================================
it('antrean tersisa: reported as min and average of the hourly snapshots, never as their sum', function () {
    laporanCagesTrackRecord($this->stationA, '2026-03-02', [
        ['hour' => 6, 'cages' => 2, 'remain' => 12],
        ['hour' => 7, 'cages' => 2, 'remain' => 5],
        ['hour' => 8, 'cages' => 2, 'remain' => 9],
    ]);

    $summary = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/cages-track-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));

    $summary->assertOk();
    $summary->assertJsonPath('queue.min_remaining', 5);
    $summary->assertJsonPath('queue.avg_remaining', 8.7);
    // 26 — the sum — must appear nowhere.
    expect($summary->json('queue.min_remaining'))->not->toBe(26);
    expect($summary->json('queue.avg_remaining'))->not->toBe(26);
    $summary->assertJsonPath('daily.0.min_remaining', 5);
});

// =====================================================================
// Endpoint error codes not reachable from a BDD scenario
// =====================================================================

it('summary: 422 VALIDATION_ERROR when period_id is missing', function () {
    $response = $this->actingAs($this->supervisor, 'web')->getJson('/api/cages-track-reports/summary');

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'VALIDATION_ERROR');
    $response->assertJsonStructure(['errors' => ['period_id']]);
});

it('summary: 404 NOT_FOUND when period_id does not exist', function () {
    $response = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/cages-track-reports/summary?'.http_build_query(['period_id' => (string) Str::uuid()]));

    $response->assertStatus(404);
    $response->assertJsonPath('code', 'NOT_FOUND');
});

it('export: 422 when period_id is missing, 404 when it does not exist, 422 for an unsupported format', function () {
    $this->actingAs($this->supervisor, 'web')->getJson('/api/cages-track-reports/export')->assertStatus(422);

    $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/cages-track-reports/export?'.http_build_query(['period_id' => (string) Str::uuid()]))
        ->assertStatus(404);

    $badFormat = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/cages-track-reports/export?'.http_build_query([
            'period_id' => $this->periodA->id,
            'format' => 'xlsx',
        ]));

    $badFormat->assertStatus(422);
    $badFormat->assertJsonPath('code', 'VALIDATION_ERROR');
    $badFormat->assertJsonStructure(['errors' => ['format']]);
});

it('business-units/options: 403 FORBIDDEN for Supervisor and Mill Management — Admin only', function () {
    // Refused by the service, so this 403 does carry the code.
    $supervisor = $this->actingAs($this->supervisor, 'web')->getJson('/api/cages-track-reports/business-units/options');
    $supervisor->assertStatus(403);
    $supervisor->assertJsonPath('code', 'FORBIDDEN');

    $this->actingAs($this->millManagement, 'web')
        ->getJson('/api/cages-track-reports/business-units/options')->assertStatus(403);
});

it('rejects unauthenticated requests on every endpoint', function () {
    $this->getJson('/api/cages-track-reports/business-units/options')->assertStatus(401);
    $this->getJson('/api/cages-track-reports/periods')->assertStatus(401);
    $this->getJson('/api/cages-track-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]))->assertStatus(401);
    $this->getJson('/api/cages-track-reports/export?'.http_build_query(['period_id' => $this->periodA->id]))->assertStatus(401);
});

it('baca saja: repeated summary calls change nothing and no write verb is routed on the prefix', function () {
    laporanCagesTrackRecord($this->stationA, '2026-03-10', [
        ['hour' => 6, 'cages' => 5],
        ['hour' => 9, 'cages' => 5],
    ], ['cages_out' => 9]);

    $recordsBefore = CagesTrackRecord::count();
    $detailsBefore = CagesTippedTime::count();

    $first = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/cages-track-reports/summary?'.http_build_query(['period_id' => $this->periodA->id]));
    $first->assertOk();

    $second = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/cages-track-reports/summary?'.http_build_query(['period_id' => $first->json('period.id')]));
    $second->assertOk();

    expect($second->json())->toEqual($first->json());
    expect(CagesTrackRecord::count())->toBe($recordsBefore);
    expect(CagesTippedTime::count())->toBe($detailsBefore);

    // The report exposes no write path at all: every mutating verb on the
    // prefix is simply not registered (405 Method Not Allowed, or 404).
    foreach (['postJson', 'putJson', 'patchJson', 'deleteJson'] as $verb) {
        foreach ([
            '/api/cages-track-reports/summary',
            '/api/cages-track-reports/periods',
            '/api/cages-track-reports/export',
            '/api/cages-track-reports/business-units/options',
        ] as $path) {
            $response = $this->actingAs($this->supervisor, 'web')->{$verb}($path, []);

            expect($response->getStatusCode())->toBeIn([404, 405]);
        }
    }
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

function laporanCagesTrackApiSecondLine(BusinessUnit $businessUnit): Station
{
    $line = ProductionLine::factory()->create([
        'business_unit_id' => $businessUnit->id,
        'name' => 'Line Kedua',
    ]);

    return Station::factory()->forProductionLine($line)->cagesTrack()->create();
}

it('production_line_id: menyaring ringkasan ke satu line, dua arah', function () {
    laporanCagesTrackRecord($this->stationA, '2026-03-05', [
        ['hour' => 6, 'cages' => 5],
        ['hour' => 7, 'cages' => 5],
    ], ['cages_track_number' => 'CT-LINE-A', 'cages_out' => 11]);

    $stationC = laporanCagesTrackApiSecondLine($this->businessUnitA);
    $lineC = (string) $stationC->production_line_id;
    laporanCagesTrackRecord($stationC, '2026-03-06', [
        ['hour' => 9, 'cages' => 40],
    ], ['cages_track_number' => 'CT-LINE-C', 'cages_out' => 44]);

    $lineA = (string) $this->stationA->production_line_id;
    $periodId = (string) $this->periodA->id;

    $a = $this->actingAs($this->supervisor, 'web')->getJson('/api/cages-track-reports/summary?'.http_build_query([
        'period_id' => $periodId,
        'production_line_id' => $lineA,
    ]));

    $a->assertOk();
    expect($a->json('kpi.total_cages_tipped'))->toBe(10);
    expect($a->json('kpi.total_cages_out'))->toBe(11);

    $c = $this->actingAs($this->supervisor, 'web')->getJson('/api/cages-track-reports/summary?'.http_build_query([
        'period_id' => $periodId,
        'production_line_id' => $lineC,
    ]));

    $c->assertOk();
    expect($c->json('kpi.total_cages_tipped'))->toBe(40);
    expect($c->json('kpi.total_cages_out'))->toBe(44);
});

it('production_line_id: menambah blok production_line tanpa mengubah satu pun kunci yang sudah ada', function () {
    laporanCagesTrackRecord($this->stationA, '2026-03-05', [
        ['hour' => 6, 'cages' => 5],
        ['hour' => 7, 'cages' => 5],
    ], ['cages_track_number' => 'CT-LINE-A', 'cages_out' => 11]);

    $lineA = (string) $this->stationA->production_line_id;
    $periodId = (string) $this->periodA->id;

    $tanpa = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/cages-track-reports/summary?'.http_build_query(['period_id' => $periodId]));

    $dengan = $this->actingAs($this->supervisor, 'web')->getJson('/api/cages-track-reports/summary?'.http_build_query([
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
    laporanCagesTrackRecord($this->stationB, '2026-03-05', [
        ['hour' => 8, 'cages' => 77],
    ], ['cages_track_number' => 'CT-MILL-B', 'cages_out' => 99]);

    $lineB = (string) $this->stationB->production_line_id;

    $response = $this->actingAs($this->supervisor, 'web')->getJson('/api/cages-track-reports/summary?'.http_build_query([
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $lineB,
    ]));

    // Cakupan mill sudah ditegakkan lebih dulu, jadi menyaring ke line mill
    // lain menghasilkan laporan KOSONG — bukan data Mill Beta.
    $response->assertOk();
    expect($response->json('kpi.total_cages_tipped'))->toBe(0);
    expect($response->json('kpi.total_cages_out'))->toBe(0);
});

it('production_line_id: ekspor ikut tersaring ke line terpilih', function () {
    laporanCagesTrackRecord($this->stationA, '2026-03-05', [
        ['hour' => 6, 'cages' => 5],
        ['hour' => 7, 'cages' => 5],
    ], ['cages_track_number' => 'CT-LINE-A', 'cages_out' => 11]);

    $stationC = laporanCagesTrackApiSecondLine($this->businessUnitA);
    $lineC = (string) $stationC->production_line_id;
    laporanCagesTrackRecord($stationC, '2026-03-06', [
        ['hour' => 9, 'cages' => 40],
    ], ['cages_track_number' => 'CT-LINE-C', 'cages_out' => 44]);

    $lineA = (string) $this->stationA->production_line_id;
    $periodId = (string) $this->periodA->id;

    $a = $this->actingAs($this->supervisor, 'web')->get('/api/cages-track-reports/export?'.http_build_query([
        'period_id' => $periodId,
        'format' => 'csv',
        'production_line_id' => $lineA,
    ]));

    $a->assertOk();

    $bodyA = $a->streamedContent();

    expect($bodyA)->toContain('CT-LINE-A');
    expect($bodyA)->not->toContain('CT-LINE-C');

    $c = $this->actingAs($this->supervisor, 'web')->get('/api/cages-track-reports/export?'.http_build_query([
        'period_id' => $periodId,
        'format' => 'csv',
        'production_line_id' => $lineC,
    ]));

    $c->assertOk();

    $bodyC = $c->streamedContent();

    expect($bodyC)->toContain('CT-LINE-C');
    expect($bodyC)->not->toContain('CT-LINE-A');
});

it('production_line_id: record yang stasiunnya sudah dipindah tetap terhitung di line asalnya', function () {
    laporanCagesTrackRecord($this->stationA, '2026-03-05', [
        ['hour' => 6, 'cages' => 5],
        ['hour' => 7, 'cages' => 5],
    ], ['cages_track_number' => 'CT-LINE-A', 'cages_out' => 11]);

    $lineAsal = (string) $this->stationA->production_line_id;

    $lineBaru = ProductionLine::factory()->create([
        'business_unit_id' => $this->businessUnitA->id,
        'name' => 'Line Baru',
    ]);

    $this->stationA->update(['production_line_id' => $lineBaru->id]);

    $asal = $this->actingAs($this->supervisor, 'web')->getJson('/api/cages-track-reports/summary?'.http_build_query([
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $lineAsal,
    ]));

    $asal->assertOk();
    // Kolom di TABEL RECORD, bukan join ke `stations` — kalau ia join,
    // angka ini pindah ke Line Baru dan sejarah periode lama tertulis ulang.
    expect($asal->json('kpi.total_cages_tipped'))->toBe(10);
    expect($asal->json('kpi.total_cages_out'))->toBe(11);

    $baru = $this->actingAs($this->supervisor, 'web')->getJson('/api/cages-track-reports/summary?'.http_build_query([
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => (string) $lineBaru->id,
    ]));

    $baru->assertOk();
    expect($baru->json('kpi.total_cages_tipped'))->toBe(0);
    expect($baru->json('kpi.total_cages_out'))->toBe(0);
});
