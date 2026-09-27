<?php

/**
 * DetailPeriodePelaporanTest (Feature/Api) —
 * screen-142--detail-periode-pelaporan /
 * usecase-145--lihat-detail-periode-pelaporan +
 * usecase-140--tutup-buka-periode-pelaporan +
 * usecase-144--buka-periode-pelaporan.
 *
 * Integration tests for this screen's 4 endpoints
 * (App\Http\Controllers\Api\PeriodController):
 *   GET    /api/periods/{id}                              (usecase-145, NEW)
 *   GET    /api/period-stations/{id}/unverified-count     (usecase-140)
 *   POST   /api/period-stations/{id}/close                (usecase-140)
 *   POST   /api/period-stations/{id}/reopen               (usecase-140)
 *   POST   /api/period-stations/{id}/open                 (usecase-144)
 *
 * WHERE THESE TESTS CAME FROM (2026-09-27). The four /period-stations tests
 * used to live in tests/Feature/Api/KelolaPeriodePelaporanTest.php, back when
 * the per-station actions were part of the period LIST screen. Their contracts
 * did not change by one byte — only the screen that owns them did, so they
 * moved here with screen-142. The CRUD endpoints (/api/periods, POST/PATCH/
 * DELETE) stay with screen-128, and so do the four usecase-141 scenarios that
 * are skipped there.
 *
 * THE ID IS NOT THE SAME THING IN BOTH PREFIXES. /periods/{id} takes a PERIOD
 * id. /period-stations/{id}/... takes a `period_stations` id — the
 * `stations[].id` of PeriodService::toRow(), one row per station type inside a
 * period. detailApiStationId() below is the only place these tests derive one,
 * so no test can post a period id at a per-station action and pass for the
 * wrong reason.
 *
 * GET /api/periods/{id} RETURNS toRow() VERBATIM — byte for byte one entry of
 * GET /api/periods' `data[]`. The tests below assert that shape directly
 * (including the absence of the parent-level `status`/`station_type`/
 * `closed_by` keys that no longer exist), because the whole point of the
 * endpoint is that the detail screen and the list screen never hold two
 * different representations of the same period.
 *
 * ERROR CODE ASSERTIONS: ApiExceptionHandler emits the machine-readable `code`
 * field for NOT_FOUND / UNAUTHENTICATED and for every exception implementing
 * App\Exceptions\HasErrorCode (PERIOD_ALREADY_CLOSED / PERIOD_NOT_CLOSED /
 * PERIOD_NOT_DRAFT) — those are asserted here. FORBIDDEN is the one exception:
 * App\Http\Middleware\EnsureRole builds its own JSON response and never
 * reaches ApiExceptionHandler, so a role rejection carries `message` only. The
 * 403 tests therefore assert the status and the unchanged data, not `code`.
 *
 * PERIOD LOCK ENFORCEMENT DOES NOT EXIST YET and is not asserted here. Closing
 * a station sets a status; refusing record input/edit/verification inside a
 * closed period is usecase-141--kunci-input-periode-tertutup, whose four
 * scenarios live skipped in tests/Feature/Api/KelolaPeriodePelaporanTest.php.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\Station;
use App\Models\SterilizerRecord;
use App\Models\ThreshingRecord;
use App\Models\User;

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->admin = User::factory()->role(UserRole::Admin)->create(['name' => 'Admin X']);
    $this->adminA = User::factory()->role(UserRole::Admin)->create(['name' => 'Admin A']);
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->create();

    $this->sterilizerStation = Station::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->sterilizer()
        ->create();
});

/**
 * THE ONLY PLACE THESE TESTS TURN A PERIOD INTO A period_stations ID. Every
 * /api/period-stations/... call goes through it, so "did I pass the right kind
 * of id" is answered once here instead of in 20 test bodies.
 */
function detailApiStationId(Period|string $period, string $stationType = 'sterilizer'): string
{
    return PeriodStation::query()
        ->where('period_id', $period instanceof Period ? $period->id : $period)
        ->where('station_type', $stationType)
        ->firstOrFail()
        ->id;
}

// ── usecase-145 (GET /api/periods/{id}) ─────────────────────────────────

// Scenario: "Lihat Detail Periode Pelaporan — sukses"
it('mengembalikan satu periode dalam bentuk toRow() yang sama persis dengan satu entri daftar', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->noStations()
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->create();

    // Inserted in an order that is neither the master's nor alphabetical.
    PeriodStation::factory()->forPeriod($period)->stationType('clarification')->open()->create();
    PeriodStation::factory()->forPeriod($period)->stationType('boiler-room')->draft()->create();
    PeriodStation::factory()->forPeriod($period)->stationType('sterilizer')
        ->closed($this->adminA, '2026-11-01 09:14:00')->create();

    $detail = $this->actingAs($this->admin, 'web')->getJson("/api/periods/{$period->id}");
    $detail->assertOk();

    $detail->assertJsonPath('id', $period->id);
    $detail->assertJsonPath('name', 'Oktober 2026');
    $detail->assertJsonPath('business_unit_name', 'Mill Alpha');
    $detail->assertJsonPath('start_date', '2026-10-01');
    $detail->assertJsonPath('end_date', '2026-10-31');
    $detail->assertJsonPath('station_count', 3);
    $detail->assertJsonPath('closed_station_count', 1);
    $detail->assertJsonPath('is_immutable', true);
    $detail->assertJsonPath('status_summary', 'mixed');

    // sort_order: sterilizer 40 < clarification 70 < boiler-room 90 — exactly
    // the reverse of alphabetical, so an accidental sort would show.
    expect(collect($detail->json('stations'))->pluck('station_type')->all())
        ->toBe(['sterilizer', 'clarification', 'boiler-room']);

    $detail->assertJsonPath('stations.0.id', detailApiStationId($period, 'sterilizer'));
    $detail->assertJsonPath('stations.0.station_type_label', 'Sterilizer');
    $detail->assertJsonPath('stations.0.status', 'closed');
    $detail->assertJsonPath('stations.0.closed_by', $this->adminA->id);
    $detail->assertJsonPath('stations.0.closed_by_name', 'Admin A');
    expect($detail->json('stations.0.closed_at'))->not->toBeNull();
    // A station that is not closed carries neither a closer nor a time.
    $detail->assertJsonPath('stations.1.closed_by', null);
    $detail->assertJsonPath('stations.1.closed_by_name', null);
    $detail->assertJsonPath('stations.1.closed_at', null);

    // THE PARENT HAS NO status / station_type / closed_by / closed_at. They
    // are plural now — one set per station — and a parent key named `status`
    // would invite `$row['status'] === 'closed'` to live on.
    foreach (['status', 'station_type', 'station_type_label', 'closed_by', 'closed_by_name', 'closed_at'] as $goneKey) {
        expect(array_key_exists($goneKey, $detail->json()))->toBeFalse("kunci induk `$goneKey` seharusnya sudah tidak ada");
    }

    // AND IT IS THE SAME OBJECT THE LIST RETURNS — byte for byte.
    $list = $this->actingAs($this->admin, 'web')->getJson('/api/periods');
    $list->assertOk();
    $fromList = collect($list->json('data'))->firstWhere('id', $period->id);
    expect($detail->json())->toBe($fromList);
});

// Scenario: "Lihat Detail Periode Pelaporan — Periode tidak ditemukan"
it('mengembalikan 404 NOT_FOUND untuk periode yang tidak ada atau sudah dihapus', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->range('2026-10-01', '2026-10-31')
        ->create();

    $id = $period->id;
    Period::whereKey($id)->delete();

    $gone = $this->actingAs($this->admin, 'web')->getJson("/api/periods/{$id}");
    $gone->assertStatus(404);
    $gone->assertJsonPath('code', 'NOT_FOUND');

    $never = $this->actingAs($this->admin, 'web')
        ->getJson('/api/periods/00000000-0000-4000-8000-000000000000');
    $never->assertStatus(404);
    $never->assertJsonPath('code', 'NOT_FOUND');
});

// Scenario: "Lihat Detail Periode Pelaporan — Periode tanpa baris stasiun"
it('mengembalikan stations kosong, status_summary empty dan is_immutable false untuk periode tanpa stasiun', function () {
    // Mill Beta has no station at all.
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->noStations()
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->create();

    $detail = $this->actingAs($this->admin, 'web')->getJson("/api/periods/{$period->id}");
    $detail->assertOk();
    $detail->assertJsonPath('stations', []);
    $detail->assertJsonPath('station_count', 0);
    $detail->assertJsonPath('closed_station_count', 0);
    $detail->assertJsonPath('status_summary', 'empty');
    $detail->assertJsonPath('is_immutable', false);
});

// status_summary follows the single status when every row agrees, and only
// `mixed` when they do not.
it('mengembalikan status_summary tunggal ketika seluruh baris stasiun berstatus sama', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->noStations()
        ->range('2026-10-01', '2026-10-31')
        ->create();

    PeriodStation::factory()->forPeriod($period)->stationType('sterilizer')->draft()->create();
    PeriodStation::factory()->forPeriod($period)->stationType('clarification')->draft()->create();

    $this->actingAs($this->admin, 'web')->getJson("/api/periods/{$period->id}")
        ->assertJsonPath('status_summary', 'draft')
        ->assertJsonPath('is_immutable', false);

    PeriodStation::where('period_id', $period->id)->update(['status' => 'open']);
    $this->actingAs($this->admin, 'web')->getJson("/api/periods/{$period->id}")
        ->assertJsonPath('status_summary', 'open')
        ->assertJsonPath('is_immutable', false);

    PeriodStation::where('period_id', $period->id)->update([
        'status' => 'closed',
        'closed_by' => $this->adminA->id,
        'closed_at' => now(),
    ]);
    $this->actingAs($this->admin, 'web')->getJson("/api/periods/{$period->id}")
        ->assertJsonPath('status_summary', 'closed')
        ->assertJsonPath('closed_station_count', 2)
        ->assertJsonPath('is_immutable', true);
});

// Scenario: "Lihat Detail Periode Pelaporan — Bukan Admin membuka detail"
it('menolak 403 FORBIDDEN untuk non-Admin dan 401 UNAUTHENTICATED tanpa sesi pada detail periode', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Periode Rahasia')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    // No session at all FIRST — actingAs() sticks for the rest of the test,
    // and a 401 asserted after it would only be measuring the last actor.
    $anon = $this->getJson("/api/periods/{$period->id}");
    $anon->assertStatus(401);
    $anon->assertJsonPath('code', 'UNAUTHENTICATED');
    expect($anon->json('name'))->toBeNull();

    foreach ([$this->supervisor, $this->millManagement, $this->operator] as $user) {
        $denied = $this->actingAs($user, 'web')->getJson("/api/periods/{$period->id}");
        $denied->assertStatus(403);
        // Not one field of the period leaks.
        expect($denied->json('name'))->toBeNull();
        expect($denied->json('stations'))->toBeNull();
    }
});

/**
 * 401 WITHOUT A SESSION, ON ALL FOUR ENDPOINTS. This screen has a web route
 * that can be opened without one, so "no session" is a real case here rather
 * than a theoretical one — the tech-spec lists UNAUTHENTICATED on every
 * endpoint of screen-142 for that reason. Nothing is asserted about the actor
 * beforehand: the request carries no session at all.
 */
it('menolak 401 tanpa sesi pada seluruh endpoint layar detail', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->range('2026-10-01', '2026-10-31')
        ->draft()
        ->create();

    $stationId = detailApiStationId($period);

    foreach ([
        ['get', "/api/periods/{$period->id}"],
        ['get', "/api/period-stations/{$stationId}/unverified-count"],
        ['post', "/api/period-stations/{$stationId}/open"],
        ['post', "/api/period-stations/{$stationId}/close"],
        ['post', "/api/period-stations/{$stationId}/reopen"],
    ] as [$verb, $url]) {
        $response = $verb === 'get' ? $this->getJson($url) : $this->postJson($url);
        $response->assertStatus(401);
        $response->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    // Nothing moved.
    expect(PeriodStation::findOrFail($stationId)->status->value)->toBe('draft');
});

// The literal /periods/business-units/options must keep winning over the new
// parameterised GET /periods/{id} — the route-order trap the tech-spec calls
// out by name.
it('rute literal /periods/business-units/options tetap menang atas /periods/{id}', function () {
    $options = $this->actingAs($this->admin, 'web')->getJson('/api/periods/business-units/options');
    $options->assertOk();
    $options->assertJsonStructure(['data' => [['id', 'name']]]);
    // Not a 404 from findOrFail('business-units'), and not a period body.
    expect($options->json('stations'))->toBeNull();
});

// ── usecase-140 (Tutup & Buka Kembali) ──────────────────────────────────

// Scenario 10: "Tutup & Buka Kembali Periode Pelaporan — success"
it('menutup satu stasiun periode setelah menampilkan jumlah data belum terverifikasi', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    $stationId = detailApiStationId($period);

    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-10-10')->count(2)->create();

    // Step 1 — GET unverified-count -> 200
    $count = $this->actingAs($this->admin, 'web')->getJson("/api/period-stations/{$stationId}/unverified-count");
    $count->assertOk();
    $count->assertJsonStructure(['unverified_count', 'breakdown']);
    $count->assertJsonPath('unverified_count', 2);
    $count->assertJsonPath('breakdown.0.station_type', 'sterilizer');

    // Step 2 — POST close -> 200
    $close = $this->actingAs($this->admin, 'web')->postJson("/api/period-stations/{$stationId}/close");
    $close->assertOk();
    $close->assertJsonPath('period_station_id', $stationId);
    $close->assertJsonPath('period_id', $period->id);
    $close->assertJsonPath('station_type', 'sterilizer');
    $close->assertJsonPath('station_type_label', 'Sterilizer');
    $close->assertJsonPath('status', 'closed');
    $close->assertJsonPath('closed_by', $this->admin->id);
    $close->assertJsonPath('closed_by_name', 'Admin X');
    expect($close->json('closed_at'))->not->toBeNull();

    // Step 3 — GET list filtered status=closed -> 200
    $list = $this->actingAs($this->admin, 'web')
        ->getJson("/api/periods?business_unit_id={$this->businessUnitA->id}&status=closed");
    $list->assertOk();
    $list->assertJsonPath('meta.total', 1);
    $list->assertJsonPath('data.0.id', $period->id);
    $list->assertJsonPath('data.0.status_summary', 'closed');
    $list->assertJsonPath('data.0.stations.0.closed_by_name', 'Admin X');
});

/**
 * THE WHOLE POINT OF THE SPLIT: closing Sterilizer must leave Clarification
 * exactly as it was — same status, same (empty) closure record, same row id.
 */
it('menutup satu stasiun tidak mengubah stasiun lain pada periode yang sama', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationTypes(['sterilizer', 'clarification'])
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    $sterilizerRow = detailApiStationId($period, 'sterilizer');
    $clarificationRow = detailApiStationId($period, 'clarification');

    $this->actingAs($this->admin, 'web')
        ->postJson("/api/period-stations/{$sterilizerRow}/close")
        ->assertOk();

    $list = $this->actingAs($this->admin, 'web')->getJson('/api/periods');
    $list->assertOk();
    $list->assertJsonPath('data.0.status_summary', 'mixed');
    $list->assertJsonPath('data.0.station_count', 2);
    $list->assertJsonPath('data.0.closed_station_count', 1);

    $stations = collect($list->json('data.0.stations'))->keyBy('station_type');

    expect($stations['sterilizer']['status'])->toBe('closed');
    expect($stations['sterilizer']['closed_by_name'])->toBe('Admin X');

    expect($stations['clarification']['id'])->toBe($clarificationRow);
    expect($stations['clarification']['status'])->toBe('open');
    expect($stations['clarification']['closed_by'])->toBeNull();
    expect($stations['clarification']['closed_by_name'])->toBeNull();
    expect($stations['clarification']['closed_at'])->toBeNull();

    // And closing the other one afterwards is still allowed.
    $this->actingAs($this->admin, 'web')
        ->postJson("/api/period-stations/{$clarificationRow}/close")
        ->assertOk()
        ->assertJsonPath('station_type', 'clarification');
});

// Scenario 11: "buka kembali periode yang sudah tertutup"
it('membuka kembali satu stasiun tertutup dan mengosongkan catatan penutupan', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->closed($this->adminA, '2026-11-01 09:14:00')
        ->create();

    $stationId = detailApiStationId($period);

    // Step 1 — POST reopen -> 200
    $reopen = $this->actingAs($this->admin, 'web')->postJson("/api/period-stations/{$stationId}/reopen");
    $reopen->assertOk();
    $reopen->assertJsonPath('period_station_id', $stationId);
    $reopen->assertJsonPath('status', 'open');
    $reopen->assertJsonPath('closed_by', null);
    $reopen->assertJsonPath('closed_at', null);

    // Step 2 — GET list filtered status=open -> 200
    $list = $this->actingAs($this->admin, 'web')->getJson('/api/periods?status=open');
    $list->assertOk();
    $list->assertJsonPath('meta.total', 1);
    $list->assertJsonPath('data.0.id', $period->id);
    $list->assertJsonPath('data.0.status_summary', 'open');
    $list->assertJsonPath('data.0.is_immutable', false);
    $list->assertJsonPath('data.0.stations.0.closed_by_name', null);
    $list->assertJsonPath('data.0.stations.0.closed_at', null);
});

// Scenario 12: "Admin membatalkan penutupan"
it('tidak mengubah status stasiun ketika Admin hanya melihat jumlah lalu membatalkan penutupan', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    $stationId = detailApiStationId($period);

    // Step 1 — GET unverified-count -> 200 (dialog opened)
    $this->actingAs($this->admin, 'web')
        ->getJson("/api/period-stations/{$stationId}/unverified-count")
        ->assertOk();

    // Step 2 — the Admin cancels: only the list is re-read, close is never
    // called -> 200 and the row is untouched.
    $list = $this->actingAs($this->admin, 'web')
        ->getJson("/api/periods?business_unit_id={$this->businessUnitA->id}");
    $list->assertOk();
    $list->assertJsonPath('data.0.status_summary', 'open');
    $list->assertJsonPath('data.0.stations.0.status', 'open');
    $list->assertJsonPath('data.0.stations.0.closed_by', null);
    $list->assertJsonPath('data.0.stations.0.closed_at', null);

    expect(PeriodStation::findOrFail($stationId)->status->value)->toBe('open');
});

// Scenario 13: "masih banyak data belum terverifikasi"
it('tetap menutup stasiun meski masih banyak data belum terverifikasi, dan jumlahnya tidak berubah', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    $stationId = detailApiStationId($period);

    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-10-12')->count(5)->create();

    // Step 1 — GET unverified-count -> 200 with an explicit figure
    $before = $this->actingAs($this->admin, 'web')->getJson("/api/period-stations/{$stationId}/unverified-count");
    $before->assertOk();
    $before->assertJsonPath('unverified_count', 5);
    $before->assertJsonPath('breakdown.0.station_type', 'sterilizer');
    $before->assertJsonPath('breakdown.0.count', 5);

    // Step 2 — POST close -> 200 (the figure warns, it never blocks)
    $this->actingAs($this->admin, 'web')->postJson("/api/period-stations/{$stationId}/close")->assertOk();

    // Step 3 — GET unverified-count again -> 200, unchanged: verification
    // is locked too, so those records stay unverified until reopen.
    $after = $this->actingAs($this->admin, 'web')->getJson("/api/period-stations/{$stationId}/unverified-count");
    $after->assertOk();
    $after->assertJsonPath('unverified_count', 5);
});

/**
 * THE WARNING MUST BE ABOUT THE STATION BEING CLOSED, NOTHING ELSE. Before the
 * split there was one count per period, so closing Sterilizer showed a figure
 * that included Clarification's unverified records — a number with nothing to
 * do with the action being confirmed, permanently non-zero on a busy mill, and
 * therefore trained to be clicked past.
 */
it('jumlah belum-terverifikasi hanya menghitung jenis stasiun yang bersangkutan', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationTypes(['sterilizer', 'threshing'])
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    $threshingStation = Station::factory()->forBusinessUnit($this->businessUnitA)->threshing()->create();

    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-10-10')->count(5)->create();
    ThreshingRecord::factory()->forStation($threshingStation)->onDate('2026-10-11')->count(2)->create();

    $sterilizerCount = $this->actingAs($this->admin, 'web')
        ->getJson('/api/period-stations/'.detailApiStationId($period, 'sterilizer').'/unverified-count');
    $sterilizerCount->assertOk();
    $sterilizerCount->assertJsonPath('unverified_count', 5);
    $sterilizerCount->assertJsonCount(1, 'breakdown');
    $sterilizerCount->assertJsonPath('breakdown.0.station_type', 'sterilizer');

    $threshingCount = $this->actingAs($this->admin, 'web')
        ->getJson('/api/period-stations/'.detailApiStationId($period, 'threshing').'/unverified-count');
    $threshingCount->assertOk();
    $threshingCount->assertJsonPath('unverified_count', 2);
    $threshingCount->assertJsonCount(1, 'breakdown');
    $threshingCount->assertJsonPath('breakdown.0.station_type', 'threshing');
});

// Scenario 16: "menutup periode yang sudah tertutup"
it('menolak 409 PERIOD_ALREADY_CLOSED saat menutup stasiun yang sudah tertutup', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->range('2026-10-01', '2026-10-31')
        ->closed($this->adminA, '2026-11-01 09:14:00')
        ->create();

    $stationId = detailApiStationId($period);

    // Step 1 — POST close on an already-closed station -> 409
    $close = $this->actingAs($this->admin, 'web')->postJson("/api/period-stations/{$stationId}/close");
    $close->assertStatus(409);
    $close->assertJsonPath('code', 'PERIOD_ALREADY_CLOSED');

    // Step 2 — the list still shows it closed by the FIRST closer -> 200
    $list = $this->actingAs($this->admin, 'web')->getJson('/api/periods?status=closed');
    $list->assertOk();
    $list->assertJsonPath('meta.total', 1);
    $list->assertJsonPath('data.0.stations.0.closed_by_name', 'Admin A');
});

// Scenario 17: "dua Admin menutup periode bersamaan"
it('penutupan kedua tidak menimpa catatan penutup pertama saat dua Admin menutup stasiun yang sama', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    $stationId = detailApiStationId($period);

    // Step 1 — Admin A wins the race -> 200
    $first = $this->actingAs($this->adminA, 'web')->postJson("/api/period-stations/{$stationId}/close");
    $first->assertOk();
    $first->assertJsonPath('closed_by', $this->adminA->id);
    $closedAt = $first->json('closed_at');

    // Step 2 — Admin X confirms the same closure a moment later -> 409
    $second = $this->actingAs($this->admin, 'web')->postJson("/api/period-stations/{$stationId}/close");
    $second->assertStatus(409);
    $second->assertJsonPath('code', 'PERIOD_ALREADY_CLOSED');
    expect($second->json('message'))->toContain('Admin A');

    // Step 3 — the list shows Admin A, not Admin X -> 200
    $list = $this->actingAs($this->admin, 'web')->getJson('/api/periods?status=closed');
    $list->assertOk();
    $list->assertJsonPath('data.0.stations.0.closed_by', $this->adminA->id);
    $list->assertJsonPath('data.0.stations.0.closed_by_name', 'Admin A');
    $list->assertJsonPath('data.0.stations.0.closed_at', $closedAt);
});

// Scenario 18: "pengguna selain Admin menutup periode"
it('menolak 403 FORBIDDEN pada aksi tutup, buka kembali, dan unverified-count untuk non-Admin', function (string $role) {
    $user = match ($role) {
        'supervisor' => $this->supervisor,
        'mill_management' => $this->millManagement,
        'operator' => $this->operator,
    };

    $openPeriod = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Periode Terbuka')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    $closedPeriod = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType('sterilizer')
        ->named('Periode Tertutup')
        ->range('2026-10-01', '2026-10-31')
        ->closed($this->adminA, '2026-11-01 09:14:00')
        ->create();

    $openStationId = detailApiStationId($openPeriod);
    $closedStationId = detailApiStationId($closedPeriod);

    // Step 1 — close -> 403
    $this->actingAs($user, 'web')->postJson("/api/period-stations/{$openStationId}/close")->assertStatus(403);

    // Step 2 — reopen -> 403
    $this->actingAs($user, 'web')->postJson("/api/period-stations/{$closedStationId}/reopen")->assertStatus(403);

    // Step 3 — unverified-count -> 403
    $this->actingAs($user, 'web')
        ->getJson("/api/period-stations/{$openStationId}/unverified-count")
        ->assertStatus(403);

    // Step 4 — re-checked AS ADMIN (the scenario's 200): nothing changed.
    $list = $this->actingAs($this->admin, 'web')->getJson('/api/periods');
    $list->assertOk();
    expect(PeriodStation::findOrFail($openStationId)->status->value)->toBe('open');
    expect(PeriodStation::findOrFail($closedStationId)->closed_by)->toBe($this->adminA->id);
})->with([
    'supervisor' => ['supervisor'],
    'mill management' => ['mill_management'],
    'operator' => ['operator'],
]);

// ── usecase-144 (Buka Stasiun — POST /api/period-stations/{id}/open) ─────
//
// Scenarios 21–28 of the screen tech-spec. This endpoint lists 404 / 409 /
// 403 ONLY — there is deliberately no 401 case, exactly as close/reopen
// have none: session handling is middleware, not part of this contract.

/** A draft period on Mill Alpha, the only status "Buka Stasiun" accepts. */
function draftPeriodForDetailApi(BusinessUnit $businessUnit, string $name = 'Oktober 2026'): Period
{
    return Period::factory()
        ->forBusinessUnit($businessUnit)
        ->stationType('sterilizer')
        ->named($name)
        ->range('2026-10-01', '2026-10-31')
        ->draft()
        ->create();
}

// Scenario 21: "Buka Periode Pelaporan — sukses"
it('membuka satu stasiun draft menjadi open tanpa menyentuh satu pun data stasiun', function () {
    $period = draftPeriodForDetailApi($this->businessUnitA);
    $stationId = detailApiStationId($period);

    $records = SterilizerRecord::factory()
        ->forStation($this->sterilizerStation)
        ->onDate('2026-10-10')
        ->count(2)
        ->create();

    $before = SterilizerRecord::query()->orderBy('id')->get()
        ->map(fn ($r) => [$r->id, $r->checked_by, $r->acknowledged_by, (string) $r->updated_at])
        ->all();

    // Step 1 — POST /api/period-stations/{id}/open -> 200
    $open = $this->actingAs($this->admin, 'web')->postJson("/api/period-stations/{$stationId}/open");

    $open->assertOk();
    $open->assertExactJson([
        'period_station_id' => $stationId,
        'period_id' => $period->id,
        'station_type' => 'sterilizer',
        'station_type_label' => 'Sterilizer',
        'status' => 'open',
        'closed_by' => null,
        'closed_by_name' => null,
        'closed_at' => null,
    ]);

    $freshStation = PeriodStation::findOrFail($stationId);
    expect($freshStation->status->value)->toBe('open');
    // Opening is not closing.
    expect($freshStation->closed_by)->toBeNull();
    expect($freshStation->closed_at)->toBeNull();
    // The parent carries the "who last touched this period" stamp.
    expect($period->fresh()->updated_by)->toBe($this->admin->id);

    // Not one station record was read, validated or changed.
    $after = SterilizerRecord::query()->orderBy('id')->get()
        ->map(fn ($r) => [$r->id, $r->checked_by, $r->acknowledged_by, (string) $r->updated_at])
        ->all();
    expect($after)->toBe($before);
    expect(SterilizerRecord::count())->toBe($records->count());

    // The list shows the new status.
    $list = $this->actingAs($this->admin, 'web')->getJson('/api/periods?status=open');
    $list->assertOk();
    $list->assertJsonPath('meta.total', 1);
    $list->assertJsonPath('data.0.id', $period->id);
    $list->assertJsonPath('data.0.status_summary', 'open');
});

// Scenario 22: "Periode sudah terbuka"
it('menolak 409 PERIOD_NOT_DRAFT ketika stasiun yang dibuka sudah berstatus open', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    $stationId = detailApiStationId($period);

    // Step 1 — POST open on an already-open station -> 409
    $open = $this->actingAs($this->admin, 'web')->postJson("/api/period-stations/{$stationId}/open");

    $open->assertStatus(409);
    $open->assertJsonPath('code', 'PERIOD_NOT_DRAFT');
    expect($open->json('message'))->toContain('sudah terbuka');
    // Only the closed case points at "Buka Kembali Periode".
    expect($open->json('message'))->not->toContain('Buka Kembali Periode');

    expect(PeriodStation::findOrFail($stationId)->status->value)->toBe('open');
    expect($period->fresh()->updated_by)->toBeNull();
});

// Scenario 23: "Periode sudah tertutup"
it('menolak 409 PERIOD_NOT_DRAFT untuk stasiun tertutup dan mengarahkan ke Buka Kembali Periode', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->closed($this->adminA, '2026-11-01 09:14:00')
        ->create();

    $stationId = detailApiStationId($period);

    // Step 1 — POST open on a closed station -> 409
    $open = $this->actingAs($this->admin, 'web')->postJson("/api/period-stations/{$stationId}/open");

    $open->assertStatus(409);
    $open->assertJsonPath('code', 'PERIOD_NOT_DRAFT');
    // A different message from the already-open one, naming the OTHER
    // action — the two are easy to confuse and a bare refusal makes the
    // Admin think the period is broken.
    expect($open->json('message'))->toContain('sudah tertutup');
    expect($open->json('message'))->toContain('Buka Kembali Periode');

    $freshStation = PeriodStation::findOrFail($stationId);
    expect($freshStation->status->value)->toBe('closed');
    expect($freshStation->closed_by)->toBe($this->adminA->id);

    // And the action that IS right for a closed station still works.
    $this->actingAs($this->admin, 'web')->postJson("/api/period-stations/{$stationId}/reopen")->assertOk();
});

// Scenario 24: "Periode tidak ditemukan"
it('mengembalikan 404 NOT_FOUND saat membuka stasiun periode yang sudah dihapus Admin lain', function () {
    $period = draftPeriodForDetailApi($this->businessUnitA, 'Periode Sementara');
    $periodId = $period->id;
    $stationId = detailApiStationId($period);

    // Another Admin deletes the period first — its station rows cascade away.
    $this->actingAs($this->admin, 'web')->deleteJson("/api/periods/{$periodId}")->assertOk();

    // Step 1 — POST open on the now-gone station row -> 404
    $open = $this->actingAs($this->admin, 'web')->postJson("/api/period-stations/{$stationId}/open");

    $open->assertStatus(404);
    $open->assertJsonPath('code', 'NOT_FOUND');

    expect(Period::find($periodId))->toBeNull();
    expect(PeriodStation::find($stationId))->toBeNull();
    expect(Period::count())->toBe(0);
});

// Scenario 25: "Dua Admin membuka bersamaan"
it('pembukaan kedua ditolak 409 dan tidak menimpa catatan Admin pertama', function () {
    $period = draftPeriodForDetailApi($this->businessUnitA);
    $stationId = detailApiStationId($period);

    // Step 1 — Admin A wins the race -> 200
    $first = $this->actingAs($this->adminA, 'web')->postJson("/api/period-stations/{$stationId}/open");
    $first->assertOk();
    $first->assertJsonPath('status', 'open');

    expect($period->fresh()->updated_by)->toBe($this->adminA->id);

    // Step 2 — Admin X confirms the same opening a moment later -> 409;
    // the conditional UPDATE's WHERE status='draft' matched nothing.
    $second = $this->actingAs($this->admin, 'web')->postJson("/api/period-stations/{$stationId}/open");
    $second->assertStatus(409);
    $second->assertJsonPath('code', 'PERIOD_NOT_DRAFT');
    expect($second->json('message'))->toContain('sudah terbuka');

    // No silent overwrite — updated_by is still Admin A's.
    expect(PeriodStation::findOrFail($stationId)->status->value)->toBe('open');
    expect($period->fresh()->updated_by)->toBe($this->adminA->id);
});

// Scenario 26: "Bukan Admin mencoba membuka periode"
it('menolak 403 FORBIDDEN pada aksi buka stasiun untuk Supervisor dan Mill Management', function () {
    $period = draftPeriodForDetailApi($this->businessUnitA);
    $stationId = detailApiStationId($period);

    // Step 1 — Supervisor -> 403
    $this->actingAs($this->supervisor, 'web')
        ->postJson("/api/period-stations/{$stationId}/open")
        ->assertStatus(403);

    // Step 2 — Mill Management -> 403
    $this->actingAs($this->millManagement, 'web')
        ->postJson("/api/period-stations/{$stationId}/open")
        ->assertStatus(403);

    // Operator too, for completeness — every non-Admin role is refused.
    $this->actingAs($this->operator, 'web')
        ->postJson("/api/period-stations/{$stationId}/open")
        ->assertStatus(403);

    expect(PeriodStation::findOrFail($stationId)->status->value)->toBe('draft');
    expect($period->fresh()->updated_by)->toBeNull();
});

// Scenario 27: "status Terbuka tidak dapat dikembalikan ke Draft"
it('tidak menyediakan jalan kembali dari open ke draft lewat PATCH', function () {
    $period = draftPeriodForDetailApi($this->businessUnitA);
    $stationId = detailApiStationId($period);

    // Step 1 — open -> 200
    $this->actingAs($this->admin, 'web')
        ->postJson("/api/period-stations/{$stationId}/open")
        ->assertOk();

    // Step 2 — PATCH trying to push it back to draft -> 200.
    //
    // 200, not 422: `status` is simply NOT one of the fields the controller
    // forwards or PeriodService::validate() accepts — and since 2026-09-25 it
    // is not a `periods` column at all. The attempt is IGNORED rather than
    // refused. What matters is the outcome — the station is still open.
    $patch = $this->actingAs($this->admin, 'web')->patchJson("/api/periods/{$period->id}", [
        'business_unit_id' => $this->businessUnitA->id,
        'status' => 'draft',
        'station_type' => 'sterilizer',
        'name' => 'Percobaan Mundur',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-31',
    ]);

    $patch->assertOk();
    $patch->assertJsonPath('name', 'Percobaan Mundur');
    $patch->assertJsonPath('status_summary', 'open');
    $patch->assertJsonPath('stations.0.status', 'open');
    // The ignored `status` key did not sneak into the response either.
    $patch->assertJsonMissingPath('status');

    expect(PeriodStation::findOrFail($stationId)->status->value)->toBe('open');
});

// Scenario 28: "periode Terbuka tetap dapat diubah dan dihapus"
it('periode dengan stasiun Terbuka tetap dapat di-PATCH dan di-DELETE', function () {
    $period = draftPeriodForDetailApi($this->businessUnitA, 'Periode Agustus 2026');
    $stationId = detailApiStationId($period);

    // Step 1 — open -> 200
    $this->actingAs($this->admin, 'web')
        ->postJson("/api/period-stations/{$stationId}/open")
        ->assertOk();

    // Step 2 — PATCH -> 200, no PERIOD_CLOSED_IMMUTABLE: only 'closed' locks.
    $patch = $this->actingAs($this->admin, 'web')->patchJson("/api/periods/{$period->id}", [
        'business_unit_id' => $this->businessUnitA->id,
        'name' => 'Periode Agustus 2026 (revisi)',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-31',
    ]);
    $patch->assertOk();
    $patch->assertJsonPath('name', 'Periode Agustus 2026 (revisi)');
    $patch->assertJsonPath('status_summary', 'open');
    $patch->assertJsonPath('is_immutable', false);

    // Step 3 — DELETE -> 200
    $delete = $this->actingAs($this->admin, 'web')->deleteJson("/api/periods/{$period->id}");
    $delete->assertOk();
    $delete->assertExactJson(['deleted' => true]);

    expect(Period::find($period->id))->toBeNull();
    expect(PeriodStation::find($stationId))->toBeNull();
});
