<?php

/**
 * KelolaPeriodePelaporanTest (Feature/Api) —
 * screen-128--kelola-periode-pelaporan /
 * usecase-128--kelola-periode-pelaporan.
 *
 * Integration tests for the screen's 5 endpoints
 * (App\Http\Controllers\Api\PeriodController):
 *   GET    /api/periods
 *   GET    /api/periods/business-units/options
 *   POST   /api/periods
 *   PATCH  /api/periods/{id}
 *   DELETE /api/periods/{id}
 *
 * THE FOUR /period-stations ENDPOINTS MOVED OUT (2026-09-27), together with
 * GET /api/periods/{id}: they belong to screen-142--detail-periode-pelaporan
 * now, and their tests live in tests/Feature/Api/DetailPeriodePelaporanTest.php
 * — unchanged, because their contracts did not change, only the screen that
 * owns them. What stays here is the period CRUD, plus the four usecase-141
 * scenarios below.
 *
 * THE ID IS NOT THE SAME THING IN BOTH PREFIXES. The /periods routes take a
 * PERIOD id. The /period-stations routes take a `period_stations` id — the
 * `stations[].id` of PeriodService::toRow(), one row per station type inside a
 * period. periodStationId() below is the only place these tests derive one, so
 * no test can accidentally post a period id at a per-station action and pass
 * for the wrong reason.
 *
 * WHAT A PERIOD LOOKS LIKE NOW. It has no station type, no status, no closer
 * and no closing time of its own: it has `stations[]`, plus the summary
 * PeriodService::toRow() derives from them (`station_count`,
 * `closed_station_count`, `is_immutable`, `status_summary`). The absence of
 * the old flat keys is asserted, not assumed — see the dedicated test right
 * after scenario 1.
 *
 * One test per test_scenarios entry of the screen tech-spec, each executing
 * that scenario's api_test steps IN ORDER and asserting every step's
 * expected_status and expected_error_code, plus the tests the old one-row-
 * per-station-type model made impossible to write (mixed status, update()'s
 * station backfill). Exercises the real route -> EnsureRole -> controller ->
 * PeriodService -> Eloquent chain against the sqlite in-memory testing DB.
 * Mirrors tests/Feature/Api/KelolaProductionLineTest.php's structure.
 *
 * ERROR CODE ASSERTIONS: ApiExceptionHandler emits the machine-readable
 * `code` field for VALIDATION_ERROR / NOT_FOUND / UNAUTHENTICATED and for
 * every exception implementing App\Exceptions\HasErrorCode
 * (PERIOD_OVERLAP / PERIOD_CLOSED_IMMUTABLE) — those are asserted here.
 * FORBIDDEN is the one exception: App\Http\Middleware\EnsureRole builds its own JSON response
 * and never reaches ApiExceptionHandler, so a role rejection carries
 * `message` only (screen 4-implement known_issue). The 403 scenarios below
 * therefore assert the status and the unchanged data, not `code`.
 *
 * FOUR SCENARIOS ARE SKIPPED, NOT DELETED: they assert a
 * 422 PERIOD_CLOSED from POST/PATCH /api/sterilizer-records once a period
 * is closed. That enforcement lives in the 18 *RecordService classes and
 * the mobile sync path — it is explicitly OUT OF SCOPE for screen-128 and
 * for screen-142 (see PeriodClosureService's docblock and both screen
 * tech-specs' implementation_notes; tracked by
 * usecase-141--kunci-input-periode-tertutup). The tests are written in
 * full so they become runnable the moment that use case lands — they must
 * not be "made green" by weakening the assertion, and they were deliberately
 * left in this file untouched when the closure tests moved to screen-142.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\SterilizerRecord;
use App\Models\User;
use Illuminate\Support\Carbon;

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

    // AKTOR YANG TERIKAT MILL, khusus untuk jalur TULIS data stasiun.
    // $this->supervisor dan $this->operator di atas sengaja dibiarkan TANPA mill
    // karena test akses memakai keduanya begitu — tetapi sejak aturan mill-scope
    // mendarat (2026-09-28, commit a5ccfba) setiap penulisan record menuntut aktor
    // yang terikat mill, dan tanpa itu permintaan berhenti di 403
    // CrossMillWriteDeniedException sebelum kunci periode tersentuh sama sekali.
    // Itulah yang membuat keempat test kontrak usecase-141 di bawah gagal 403
    // ketika skip-nya dilepas: mereka ditulis 2026-09-27, sehari SEBELUM aturan
    // itu ada.
    $this->supervisorA = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->operatorA = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
});

afterEach(function () {
    // open-summary tests freeze the date; without this a mid-test failure leaks
    // a fake clock into the next test.
    Carbon::setTestNow();
});

/**
 * Valid POST /api/periods body for Mill Alpha; override what matters.
 *
 * NO `station_type` KEY. A period takes no station choice any more — the
 * service derives the station rows from the mill's own inventory.
 */
function periodApiPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Oktober 2026',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-31',
    ], $overrides);
}

/**
 * THE ONLY PLACE THESE TESTS TURN A PERIOD INTO A period_stations ID. Every
 * /api/period-stations/... call goes through it, so "did I pass the right
 * kind of id" is answered once here instead of in 20 test bodies.
 */
function periodStationId(Period|string $period, string $stationType = 'sterilizer'): string
{
    return PeriodStation::query()
        ->where('period_id', $period instanceof Period ? $period->id : $period)
        ->where('station_type', $stationType)
        ->firstOrFail()
        ->id;
}

// ── usecase-128 ─────────────────────────────────────────────────────────

// Scenario 1: "Kelola Periode Pelaporan — success"
it('berhasil: membuat periode baru lalu menemukannya pada daftar terfilter status draft', function () {
    // Step 1 — POST /api/periods -> 201
    $create = $this->actingAs($this->admin, 'web')->postJson('/api/periods', periodApiPayload([
        'business_unit_id' => $this->businessUnitA->id,
    ]));

    $create->assertStatus(201);
    $create->assertJsonFragment([
        'name' => 'Oktober 2026',
        'business_unit_id' => $this->businessUnitA->id,
        'business_unit_name' => 'Mill Alpha',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-31',
        'status_summary' => 'draft',
        'station_count' => 1,
        'closed_station_count' => 0,
        'is_immutable' => false,
    ]);

    // The station rows are DERIVED, never sent: Mill Alpha has exactly one
    // active station (a sterilizer), so the period gets exactly one row.
    $create->assertJsonPath('stations.0.station_type', 'sterilizer');
    $create->assertJsonPath('stations.0.station_type_label', 'Sterilizer');
    $create->assertJsonPath('stations.0.status', 'draft');
    $create->assertJsonPath('stations.0.closed_by', null);
    $create->assertJsonPath('stations.0.closed_at', null);
    expect($create->json('stations.0.id'))->not->toBe($create->json('id'));

    // Step 2 — GET /api/periods?business_unit_id={{bu_a}}&status=draft -> 200
    $list = $this->actingAs($this->admin, 'web')
        ->getJson("/api/periods?business_unit_id={$this->businessUnitA->id}&status=draft");

    $list->assertOk();
    $list->assertJsonStructure(['data', 'meta' => ['page', 'per_page', 'total']]);
    $list->assertJsonPath('meta.total', 1);
    $list->assertJsonPath('data.0.name', 'Oktober 2026');
    $list->assertJsonPath('data.0.status_summary', 'draft');
    $list->assertJsonPath('data.0.stations.0.station_type_label', 'Sterilizer');
    $list->assertJsonPath('data.0.stations.0.closed_by_name', null);
});

/**
 * The flat keys are GONE FROM THE PARENT, and that is a contract, not an
 * accident: a `status` key here would invite `$row['status'] === 'closed'` to
 * live on, reading as if it still answered "is this period closed" while
 * being silently false forever. See PeriodService::toRow().
 */
it('respons periode tidak lagi memuat status/station_type/closed_by/closed_at di level induk', function () {
    $create = $this->actingAs($this->admin, 'web')->postJson('/api/periods', periodApiPayload([
        'business_unit_id' => $this->businessUnitA->id,
    ]));
    $create->assertStatus(201);

    foreach (['status', 'station_type', 'station_type_label', 'closed_by', 'closed_by_name', 'closed_at'] as $goneKey) {
        $create->assertJsonMissingPath($goneKey);
    }

    $list = $this->actingAs($this->admin, 'web')->getJson('/api/periods');
    $list->assertOk();

    foreach (['status', 'station_type', 'station_type_label', 'closed_by', 'closed_by_name', 'closed_at'] as $goneKey) {
        $list->assertJsonMissingPath("data.0.$goneKey");
    }

    // They exist on the STATION rows, where they are plural and answerable.
    $list->assertJsonPath('data.0.stations.0.status', 'draft');
    $list->assertJsonPath('data.0.stations.0.station_type', 'sterilizer');
});

// Scenario 2: "Rentang tanggal tumpang tindih"
it('menolak rentang yang tumpang tindih pada mill yang sama, apa pun stasiunnya', function () {
    // Step 1 — 201
    $this->actingAs($this->admin, 'web')->postJson('/api/periods', periodApiPayload([
        'business_unit_id' => $this->businessUnitA->id,
    ]))->assertStatus(201);

    // Step 2 — overlapping range, same mill -> 422 PERIOD_OVERLAP
    $overlap = $this->actingAs($this->admin, 'web')->postJson('/api/periods', periodApiPayload([
        'business_unit_id' => $this->businessUnitA->id,
        'name' => 'Oktober Tambahan',
        'start_date' => '2026-10-15',
        'end_date' => '2026-11-15',
    ]));

    $overlap->assertStatus(422);
    $overlap->assertJsonPath('code', 'PERIOD_OVERLAP');
    // The message names the conflicting period.
    expect($overlap->json('message'))->toContain('Oktober 2026');

    // Step 3 — the station type no longer takes part in the check at all: a
    // range sharing ONE day with the existing period is refused, full stop.
    // (Both bounds are inclusive — this range starts on the other's end date.)
    $overlapOneDay = $this->actingAs($this->admin, 'web')->postJson('/api/periods', periodApiPayload([
        'business_unit_id' => $this->businessUnitA->id,
        'name' => 'Satu Hari Beririsan',
        'start_date' => '2026-10-31',
        'end_date' => '2026-11-20',
    ]));

    $overlapOneDay->assertStatus(422);
    $overlapOneDay->assertJsonPath('code', 'PERIOD_OVERLAP');

    // Step 4 — the SAME range on another mill is fine: the rule is per mill.
    $this->actingAs($this->admin, 'web')->postJson('/api/periods', periodApiPayload([
        'business_unit_id' => $this->businessUnitB->id,
        'start_date' => '2026-10-15',
        'end_date' => '2026-11-15',
    ]))->assertStatus(201);

    expect(Period::where('business_unit_id', $this->businessUnitA->id)->count())->toBe(1);
});

// Scenario 3: "Tanggal selesai lebih awal dari tanggal mulai"
it('menolak 422 VALIDATION_ERROR ketika tanggal selesai mendahului tanggal mulai', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/periods', periodApiPayload([
        'business_unit_id' => $this->businessUnitA->id,
        'name' => 'Periode Terbalik',
        'start_date' => '2026-10-31',
        'end_date' => '2026-10-01',
    ]));

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'VALIDATION_ERROR');
    $response->assertJsonValidationErrors(['end_date']);
    expect(Period::count())->toBe(0);
    // Nothing half-written: no orphan station rows either.
    expect(PeriodStation::count())->toBe(0);
});

// Scenario 4: "Nama periode sudah dipakai"
it('menolak 422 VALIDATION_ERROR ketika nama periode sudah dipakai pada mill yang sama', function () {
    // Step 1 — 201
    $this->actingAs($this->admin, 'web')->postJson('/api/periods', periodApiPayload([
        'business_unit_id' => $this->businessUnitA->id,
    ]))->assertStatus(201);

    // Step 2 — same name, same mill, non-overlapping range -> 422
    $duplicate = $this->actingAs($this->admin, 'web')->postJson('/api/periods', periodApiPayload([
        'business_unit_id' => $this->businessUnitA->id,
        'name' => 'Oktober 2026',
        'start_date' => '2026-12-01',
        'end_date' => '2026-12-31',
    ]));

    $duplicate->assertStatus(422);
    $duplicate->assertJsonPath('code', 'VALIDATION_ERROR');
    $duplicate->assertJsonValidationErrors(['name']);
    expect(Period::count())->toBe(1);

    // Step 3 — the SAME name on another mill is accepted: uniqueness is
    // scoped to the mill alone now, no longer to (mill, station_type).
    $this->actingAs($this->admin, 'web')->postJson('/api/periods', periodApiPayload([
        'business_unit_id' => $this->businessUnitB->id,
        'name' => 'Oktober 2026',
    ]))->assertStatus(201);
});

// Scenario 5: "Mengubah atau menghapus periode yang sudah tertutup"
it('menolak 409 PERIOD_CLOSED_IMMUTABLE untuk edit maupun hapus ketika ada satu stasiun tertutup', function () {
    // Step 1 — 201
    $create = $this->actingAs($this->admin, 'web')->postJson('/api/periods', periodApiPayload([
        'business_unit_id' => $this->businessUnitA->id,
    ]));
    $create->assertStatus(201);
    $periodId = $create->json('id');
    $stationId = $create->json('stations.0.id');

    // Step 2 — close THAT STATION -> 200
    $this->actingAs($this->admin, 'web')
        ->postJson("/api/period-stations/{$stationId}/close")
        ->assertOk();

    // Step 3 — PATCH -> 409 PERIOD_CLOSED_IMMUTABLE
    $patch = $this->actingAs($this->admin, 'web')->patchJson("/api/periods/{$periodId}", [
        'name' => 'Oktober 2026 Revisi',
    ]);
    $patch->assertStatus(409);
    $patch->assertJsonPath('code', 'PERIOD_CLOSED_IMMUTABLE');
    expect($patch->json('message'))->toContain('Buka kembali periode terlebih dahulu');

    // Step 4 — DELETE -> 409 PERIOD_CLOSED_IMMUTABLE
    $delete = $this->actingAs($this->admin, 'web')->deleteJson("/api/periods/{$periodId}");
    $delete->assertStatus(409);
    $delete->assertJsonPath('code', 'PERIOD_CLOSED_IMMUTABLE');

    // Neither attempt changed anything.
    $fresh = Period::findOrFail($periodId);
    expect($fresh->name)->toBe('Oktober 2026');
    expect(PeriodStation::findOrFail($stationId)->status->value)->toBe('closed');

    // And the list agrees: one closed station out of one makes the period
    // immutable.
    $list = $this->actingAs($this->admin, 'web')->getJson('/api/periods');
    $list->assertJsonPath('data.0.is_immutable', true);
    $list->assertJsonPath('data.0.closed_station_count', 1);
    $list->assertJsonPath('data.0.status_summary', 'closed');
});

/**
 * THE IMMUTABILITY RULE IS "ANY", NOT "ALL" — one closed station out of two
 * freezes the whole period, and the list says so through `is_immutable`
 * instead of leaving the screen to work it out.
 */
it('satu stasiun tertutup dari dua sudah membuat periode immutable dan status_summary mixed', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->noStations()
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->create();

    PeriodStation::factory()->forPeriod($period)->stationType('sterilizer')
        ->closed($this->adminA, '2026-11-01 09:14:00')->create();
    PeriodStation::factory()->forPeriod($period)->stationType('clarification')->open()->create();

    $list = $this->actingAs($this->admin, 'web')->getJson('/api/periods');
    $list->assertOk();
    $list->assertJsonPath('data.0.status_summary', 'mixed');
    $list->assertJsonPath('data.0.station_count', 2);
    $list->assertJsonPath('data.0.closed_station_count', 1);
    $list->assertJsonPath('data.0.is_immutable', true);

    // Ordered by the master table's sort_order, not by insertion or status.
    expect(collect($list->json('data.0.stations'))->pluck('status', 'station_type')->all())
        ->toBe(['sterilizer' => 'closed', 'clarification' => 'open']);

    // And update()/delete() really do refuse, which is what the flag claims.
    $this->actingAs($this->admin, 'web')
        ->patchJson("/api/periods/{$period->id}", ['name' => 'Coba Ubah'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'PERIOD_CLOSED_IMMUTABLE');

    $this->actingAs($this->admin, 'web')
        ->deleteJson("/api/periods/{$period->id}")
        ->assertStatus(409)
        ->assertJsonPath('code', 'PERIOD_CLOSED_IMMUTABLE');
});

// Scenario 6: "Belum ada Business Unit"
it('mengembalikan opsi Business Unit kosong dan menolak 422 saat belum ada Business Unit sama sekali', function () {
    Period::query()->delete();
    Station::query()->delete();
    ProductionLine::query()->delete();
    User::query()->update(['business_unit_id' => null]);
    BusinessUnit::query()->delete();

    // Step 1 — GET options -> 200, empty
    $options = $this->actingAs($this->admin, 'web')->getJson('/api/periods/business-units/options');
    $options->assertOk();
    $options->assertExactJson(['data' => []]);

    // Step 2 — POST without a business unit -> 422 VALIDATION_ERROR
    $create = $this->actingAs($this->admin, 'web')->postJson('/api/periods', periodApiPayload([
        'business_unit_id' => null,
    ]));

    $create->assertStatus(422);
    $create->assertJsonPath('code', 'VALIDATION_ERROR');
    $create->assertJsonValidationErrors(['business_unit_id']);
});

// Scenario 7: "Periode sudah dihapus pengguna lain"
it('mengembalikan 404 NOT_FOUND saat mengubah atau menghapus periode yang sudah dihapus', function () {
    // Step 1 — 201
    $create = $this->actingAs($this->admin, 'web')->postJson('/api/periods', periodApiPayload([
        'business_unit_id' => $this->businessUnitA->id,
        'name' => 'Periode Sementara',
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-30',
    ]));
    $create->assertStatus(201);
    $periodId = $create->json('id');
    $stationId = $create->json('stations.0.id');

    // Step 2 — DELETE -> 200
    $delete = $this->actingAs($this->admin, 'web')->deleteJson("/api/periods/{$periodId}");
    $delete->assertOk();
    $delete->assertExactJson(['deleted' => true]);

    // The station rows went with it (cascadeOnDelete) — nothing is left
    // pointing at a period that no longer exists.
    expect(PeriodStation::find($stationId))->toBeNull();

    // Step 3 — PATCH the now-gone period -> 404 NOT_FOUND
    $patch = $this->actingAs($this->admin, 'web')->patchJson("/api/periods/{$periodId}", [
        'name' => 'Periode Sementara Revisi',
    ]);
    $patch->assertStatus(404);
    $patch->assertJsonPath('code', 'NOT_FOUND');

    // Step 4 — DELETE again -> 404 NOT_FOUND
    $deleteAgain = $this->actingAs($this->admin, 'web')->deleteJson("/api/periods/{$periodId}");
    $deleteAgain->assertStatus(404);
    $deleteAgain->assertJsonPath('code', 'NOT_FOUND');
});

// Scenario 8: "pengguna non-Admin mencoba mengakses"
it('menolak 403 FORBIDDEN pada seluruh endpoint CRUD untuk pengguna non-Admin', function (string $role) {
    $user = match ($role) {
        'supervisor' => $this->supervisor,
        'mill_management' => $this->millManagement,
        'operator' => $this->operator,
    };

    $seeded = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Periode Seed')
        ->range('2026-10-01', '2026-10-31')
        ->create();

    // Step 1 — GET /api/periods
    $this->actingAs($user, 'web')->getJson('/api/periods')->assertStatus(403);

    // Step 2 — GET /api/periods/business-units/options
    $this->actingAs($user, 'web')->getJson('/api/periods/business-units/options')->assertStatus(403);

    // Step 3 — POST /api/periods
    $this->actingAs($user, 'web')->postJson('/api/periods', periodApiPayload([
        'business_unit_id' => $this->businessUnitA->id,
        'name' => 'Coba',
    ]))->assertStatus(403);

    // Step 4 — PATCH /api/periods/{seed.period_id}
    $this->actingAs($user, 'web')->patchJson("/api/periods/{$seeded->id}", [
        'name' => 'Coba Ubah',
    ])->assertStatus(403);

    // Step 5 — DELETE /api/periods/{seed.period_id}
    $this->actingAs($user, 'web')->deleteJson("/api/periods/{$seeded->id}")->assertStatus(403);

    // Nothing leaked and nothing changed.
    expect(Period::count())->toBe(1);
    expect($seeded->fresh()->name)->toBe('Periode Seed');
})->with([
    'supervisor' => ['supervisor'],
    'mill management' => ['mill_management'],
    'operator' => ['operator'],
]);

// Scenario 9: "Business Unit tidak diisi"
it('menolak 422 VALIDATION_ERROR ketika Business Unit tidak diisi', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/periods', periodApiPayload([
        'business_unit_id' => null,
    ]));

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'VALIDATION_ERROR');
    $response->assertJsonValidationErrors(['business_unit_id']);
    expect(Period::count())->toBe(0);
});

/**
 * update()'s BACKFILL (keputusan user 2026-09-26). A period's station list is
 * a snapshot taken at create time, so a station type the mill gains AFTERWARDS
 * had no row: it could not be closed and its records were never locked — a
 * silent hole in the guarantee the whole period model exists for. A successful
 * update() re-derives the mill's active types and adds the missing rows.
 */
it('update menambahkan baris stasiun yang kurang setelah mill menambah stasiun, tanpa menyentuh baris lama', function () {
    $create = $this->actingAs($this->admin, 'web')->postJson('/api/periods', periodApiPayload([
        'business_unit_id' => $this->businessUnitA->id,
    ]));
    $create->assertStatus(201);
    $periodId = $create->json('id');
    $sterilizerStationRowId = $create->json('stations.0.id');
    $create->assertJsonPath('station_count', 1);

    // The mill gains a Clarification station AFTER the period exists.
    Station::factory()->forBusinessUnit($this->businessUnitA)->clarification()->create();

    // Until the period is saved again it still has one row — the snapshot has
    // drifted, and that is exactly the state the backfill repairs.
    $before = $this->actingAs($this->admin, 'web')->getJson('/api/periods');
    $before->assertJsonPath('data.0.station_count', 1);

    $patch = $this->actingAs($this->admin, 'web')->patchJson("/api/periods/{$periodId}", [
        'business_unit_id' => $this->businessUnitA->id,
        'name' => 'Oktober 2026',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-31',
    ]);

    $patch->assertOk();
    $patch->assertJsonPath('station_count', 2);
    expect(collect($patch->json('stations'))->pluck('status', 'station_type')->all())
        ->toBe(['sterilizer' => 'draft', 'clarification' => 'draft']);

    // The pre-existing row is the SAME row — backfill adds, it never replaces.
    expect(collect($patch->json('stations'))->firstWhere('station_type', 'sterilizer')['id'])
        ->toBe($sterilizerStationRowId);
});

it('backfill tidak pernah menghapus baris stasiun, termasuk untuk jenis yang sudah tidak ada di mill', function () {
    // A period whose station list covers a type the mill no longer has an
    // active station of: the row must survive every save.
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationTypes(['sterilizer', 'clarification'])
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    $clarificationRowId = periodStationId($period, 'clarification');

    // Mill Alpha has a sterilizer only — activeStationTypesForMill() would
    // never derive 'clarification' for it.
    $patch = $this->actingAs($this->admin, 'web')->patchJson("/api/periods/{$period->id}", [
        'business_unit_id' => $this->businessUnitA->id,
        'name' => 'Oktober 2026 (revisi)',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-31',
    ]);

    $patch->assertOk();
    $patch->assertJsonPath('station_count', 2);
    expect(PeriodStation::find($clarificationRowId))->not->toBeNull();
    // Its status was not reset to draft either.
    expect(PeriodStation::findOrFail($clarificationRowId)->status->value)->toBe('open');
});

// ── usecase-141 — KUNCI INPUT PERIODE TERTUTUP (SUDAH DITEGAKKAN) ────────
//
// Keempat test di bawah adalah kontrak
// usecase-141--kunci-input-periode-tertutup, ditulis 2026-09-27 saat kuncinya
// belum ada dan ber-skip sampai 2026-10-02. Sejak hari itu kuncinya ditegakkan
// di ke-18 app/Services/*RecordService.php lewat trait
// App\Support\Concerns\EnforcesPeriodLock, jadi skip-nya dilepas.
//
// SATU DI ANTARANYA DITULIS ULANG, DAN ITU PERLU DISEBUT. Keempatnya dirancang
// terhadap model BLACKLIST: "yang diblokir hanyalah tanggal DI DALAM periode
// yang tertutup". Aturan yang diratifikasi user pada 2026-10-01 adalah WHITELIST
// — "input data hanya bisa pada rentang waktu periode yang terbuka untuk stasiun
// tersebut" — dan di bawahnya sebuah tanggal yang tidak dicakup periode terbuka
// mana pun DITOLAK, bukan diterima. Test keempat dulu mengasersi 201 untuk
// tanggal di luar rentang; sekarang ia mengasersi aturan yang sama yang menjadi
// maksud aslinya (keanggotaan periode ditentukan TANGGAL KEJADIAN, bukan waktu
// input) dengan cara yang sah di bawah whitelist: periode Oktober ditutup,
// periode November dibuka, lalu record bertanggal November diterima sementara
// yang bertanggal Oktober ditolak.
//
// Mereka tetap di berkas ini saat test penutupan pindah ke
// tests/Feature/Api/DetailPeriodePelaporanTest.php.

// Scenario: "data mobile menyusul setelah periode ditutup"
it('menolak 422 PERIOD_CLOSED untuk data mobile yang menyusul, lalu menerimanya setelah periode dibuka kembali', function () {
    // JAM DIBEKUKAN (2026-10-04): sejak batas atas tanggal kejadian (besok,
    // zona aplikasi) berlaku, tanggal skenario di bawah tidak boleh berada di
    // masa depan relatif terhadap "hari ini" — kalau tidak, penolakannya
    // VALIDATION_ERROR (tanggal tidak masuk akal), bukan PERIOD_CLOSED yang
    // sedang diuji. afterEach() sudah mereset setTestNow().
    Carbon::setTestNow(Carbon::create(2026, 10, 31, 12));
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    $stationId = periodStationId($period);

    // Step 1 — close -> 200
    $this->actingAs($this->admin, 'web')->postJson("/api/period-stations/{$stationId}/close")->assertOk();

    $payload = [
        'production_line_id' => $this->sterilizerStation->production_line_id,
        'sterilizer_id' => 'STR-OFFLINE-001',
        'date' => '2026-10-15',
        'details' => [['close_door_time' => '08:00', 'open_door_time' => '09:10']],
    ];

    // Step 2 — the offline record syncs into a closed period -> 422 PERIOD_CLOSED
    $rejected = $this->actingAs($this->operatorA, 'web')->postJson('/api/sterilizer-records', $payload);
    $rejected->assertStatus(422);
    $rejected->assertJsonPath('code', 'PERIOD_CLOSED');

    // Step 3 — reopen -> 200
    $this->actingAs($this->admin, 'web')->postJson("/api/period-stations/{$stationId}/reopen")->assertOk();

    // Step 4 — the same record now goes through -> 201; the data was never lost.
    $this->actingAs($this->operatorA, 'web')->postJson('/api/sterilizer-records', $payload)->assertStatus(201);
});

// Scenario 15: "upaya verifikasi pada periode tertutup"
it('menolak 422 PERIOD_CLOSED saat mencoba memverifikasi record di dalam periode tertutup', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    $stationId = periodStationId($period);

    $record = SterilizerRecord::factory()
        ->forStation($this->sterilizerStation)
        ->onDate('2026-10-15')
        ->create();

    // Step 1 — close -> 200
    $this->actingAs($this->admin, 'web')->postJson("/api/period-stations/{$stationId}/close")->assertOk();

    // Step 2 — verification attempt -> 422 PERIOD_CLOSED
    //
    // ENDPOINT DIPERBAIKI 2026-10-02. Bentuk lamanya memanggil
    // PATCH /api/sterilizer-records/{id} dengan hanya ['checked' => true].
    // Verifikasi TIDAK berjalan lewat route form itu: ia punya route generik
    // sendiri untuk ke-18 stasiun, PATCH /api/records/{stationType}/{id}/verification
    // (RecordVerificationService). Memanggil route form dengan payload sepotong
    // berhenti di 422 VALIDATION_ERROR karena field wajibnya kosong — gagal karena
    // alasan yang salah, dan menutupi fakta bahwa jalur verifikasi sesungguhnya
    // belum terkunci sama sekali. Kunci di jalur itu ditambahkan bersama
    // perbaikan test ini.
    $verify = $this->actingAs($this->supervisorA, 'web')
        ->patchJson("/api/records/sterilizer/{$record->id}/verification", [
            'level' => 'checked',
            'value' => true,
        ]);
    $verify->assertStatus(422);
    $verify->assertJsonPath('code', 'PERIOD_CLOSED');

    // Step 3 — the record itself is still readable, just frozen -> 200
    $show = $this->actingAs($this->supervisorA, 'web')->getJson("/api/sterilizer-records/{$record->id}");
    $show->assertOk();
    expect($record->fresh()->checked_by)->toBeNull();
});

// Scenario 19: "mengubah data stasiun pada periode tertutup"
it('menolak 422 PERIOD_CLOSED untuk input baru maupun perubahan data stasiun pada periode tertutup', function () {
    // JAM DIBEKUKAN (2026-10-04): sejak batas atas tanggal kejadian (besok,
    // zona aplikasi) berlaku, tanggal skenario di bawah tidak boleh berada di
    // masa depan relatif terhadap "hari ini" — kalau tidak, penolakannya
    // VALIDATION_ERROR (tanggal tidak masuk akal), bukan PERIOD_CLOSED yang
    // sedang diuji. afterEach() sudah mereset setTestNow().
    Carbon::setTestNow(Carbon::create(2026, 10, 31, 12));
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    $stationId = periodStationId($period);

    $existing = SterilizerRecord::factory()
        ->forStation($this->sterilizerStation)
        ->onDate('2026-10-15')
        ->create();

    // Step 1 — close -> 200
    $this->actingAs($this->admin, 'web')->postJson("/api/period-stations/{$stationId}/close")->assertOk();

    // Step 2 — new record inside the closed range -> 422 PERIOD_CLOSED
    $create = $this->actingAs($this->supervisorA, 'web')->postJson('/api/sterilizer-records', [
        'production_line_id' => $this->sterilizerStation->production_line_id,
        'sterilizer_id' => 'STR-CLOSED-001',
        'date' => '2026-10-15',
        'details' => [['close_door_time' => '08:00', 'open_door_time' => '09:10']],
    ]);
    $create->assertStatus(422);
    $create->assertJsonPath('code', 'PERIOD_CLOSED');

    // Step 3 — editing an existing record inside the range -> 422 PERIOD_CLOSED
    //
    // PAYLOAD DILENGKAPI 2026-10-02: PATCH pada route form menuntut payload utuh,
    // bukan satu field. Dengan hanya ['note' => ...] permintaannya berhenti di 422
    // VALIDATION_ERROR — benar statusnya, salah sebabnya, dan itu akan membuat
    // test ini "lulus" bahkan bila kunci periodenya tidak ada sama sekali.
    $update = $this->actingAs($this->supervisorA, 'web')
        ->patchJson("/api/sterilizer-records/{$existing->id}", [
            'production_line_id' => $this->sterilizerStation->production_line_id,
            'sterilizer_id' => 'STR-CLOSED-EDIT',
            'date' => '2026-10-15',
            'note' => 'diubah',
            'details' => [['close_door_time' => '08:00', 'open_door_time' => '09:10']],
        ]);
    $update->assertStatus(422);
    $update->assertJsonPath('code', 'PERIOD_CLOSED');

    // Step 4 — still readable -> 200
    $this->actingAs($this->supervisorA, 'web')
        ->getJson("/api/sterilizer-records/{$existing->id}")
        ->assertOk();
});

// Scenario 20: "keanggotaan periode ditentukan TANGGAL KEJADIAN, bukan waktu input"
//
// DITULIS ULANG 2026-10-02. Bentuk lamanya menanam SATU periode Oktober, menutupnya,
// lalu mengasersi 201 untuk record bertanggal 2 November dan 30 September — benar di
// bawah model blacklist, tetapi berlawanan dengan aturan whitelist yang diratifikasi
// user 2026-10-01: tanggal yang tidak dicakup periode terbuka mana pun ditolak.
//
// Maksud aslinya tetap dipertahankan dan justru diuji lebih tajam: yang menentukan
// periode mana yang mengikat sebuah record adalah TANGGAL KEJADIANNYA, bukan kapan ia
// diinput atau disinkronkan. Dibuktikan dengan dua periode berdampingan — Oktober
// tertutup, November terbuka — lalu memasukkan ketiga tanggal pada saat yang sama.
it('keanggotaan periode dari tanggal kejadian: November terbuka diterima, Oktober tertutup ditolak', function () {
    // JAM DIBEKUKAN (2026-10-04): sejak batas atas tanggal kejadian (besok,
    // zona aplikasi) berlaku, tanggal skenario di bawah tidak boleh berada di
    // masa depan relatif terhadap "hari ini" — kalau tidak, penolakannya
    // VALIDATION_ERROR (tanggal tidak masuk akal), bukan PERIOD_CLOSED yang
    // sedang diuji. afterEach() sudah mereset setTestNow().
    Carbon::setTestNow(Carbon::create(2026, 11, 30, 12));
    $october = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    $november = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('November 2026')
        ->range('2026-11-01', '2026-11-30')
        ->open()
        ->create();

    expect($november->id)->not->toBe($october->id);

    // Oktober ditutup; November dibiarkan TERBUKA.
    $this->actingAs($this->admin, 'web')
        ->postJson('/api/period-stations/'.periodStationId($october).'/close')
        ->assertOk();

    $payload = fn (string $date, string $id) => [
        'production_line_id' => $this->sterilizerStation->production_line_id,
        'sterilizer_id' => $id,
        'date' => $date,
        'details' => [['close_door_time' => '08:00', 'open_door_time' => '09:10']],
    ];

    // (a) Tanggal kejadian di NOVEMBER -> 201. Penutupan Oktober tidak
    // menghentikan pencatatan November, walau keduanya diinput pada saat yang sama:
    // yang mengikat adalah tanggal kejadian, bukan waktu input.
    $this->actingAs($this->supervisorA, 'web')
        ->postJson('/api/sterilizer-records', $payload('2026-11-02', 'STR-NOV-001'))
        ->assertStatus(201);

    // (b) Tanggal kejadian di OKTOBER yang sudah tertutup -> 422 PERIOD_CLOSED,
    // sehingga angka laporan Oktober yang sudah ditutup tidak bisa bergerak lagi.
    $rejected = $this->actingAs($this->supervisorA, 'web')
        ->postJson('/api/sterilizer-records', $payload('2026-10-15', 'STR-OKT-001'));
    $rejected->assertStatus(422);
    $rejected->assertJsonPath('code', 'PERIOD_CLOSED');
    expect($rejected->json('message'))->toContain('Oktober 2026');

    // (c) Tanggal yang TIDAK dicakup periode mana pun (September) -> 422 juga.
    // Inilah perbedaan nyata whitelist terhadap blacklist, dan konsekuensi yang
    // diterima user secara sadar: tanpa periode terbuka yang mencakupnya, data
    // tidak bisa masuk — termasuk untuk menyusulkan data lama.
    $outside = $this->actingAs($this->supervisorA, 'web')
        ->postJson('/api/sterilizer-records', $payload('2026-09-30', 'STR-SEP-001'));
    $outside->assertStatus(422);
    $outside->assertJsonPath('code', 'PERIOD_CLOSED');
    // Pesannya menyebut rentang yang menerima data, supaya pengguna tahu
    // tanggal mana yang sah tanpa meninggalkan form.
    expect($outside->json('message'))->toContain('01/11/2026');

    // Hanya satu record yang benar-benar tersimpan.
    expect(SterilizerRecord::count())->toBe(1);
});

/*
|--------------------------------------------------------------------------
| GET /api/periods/open-summary — panel "Periode Terbuka Hari Ini per Mill"
|--------------------------------------------------------------------------
|
| Covers unit_test_case 49 (401/403 — route middleware, never a service check,
| so it can only be asserted here), 60, 62 and 63 of the screen tech-spec v6,
| plus the route-ordering trap. The service-logic cases live in
| tests/Unit/Services/PeriodServiceTest.php.
*/

/**
 * A period on the given mill with explicit per-type station statuses.
 *
 * @param  array<string, string>  $statusByType
 */
function apiPanelPeriod(BusinessUnit $businessUnit, string $name, string $start, string $end, array $statusByType): Period
{
    $period = Period::factory()
        ->forBusinessUnit($businessUnit)
        ->named($name)
        ->range($start, $end)
        ->noStations()
        ->create();

    foreach ($statusByType as $type => $status) {
        PeriodStation::factory()->forPeriod($period)->stationType($type)->create(['status' => $status]);
    }

    return $period;
}

// Case 49
it('open-summary: menolak 401 tanpa sesi dan 403 untuk actor bukan Admin', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 15));
    apiPanelPeriod($this->businessUnitA, 'Okt A', '2026-10-01', '2026-10-31', ['sterilizer' => 'open']);

    $this->getJson('/api/periods/open-summary')->assertStatus(401);

    foreach ([$this->supervisorA, $this->millManagement, $this->operator] as $actor) {
        $response = $this->actingAs($actor, 'web')->getJson('/api/periods/open-summary');

        $response->assertStatus(403);
        // No mill name leaks through the refusal.
        expect($response->getContent())->not->toContain('Mill Alpha');
    }
});

// Route-ordering trap: the literal segment must not be matched as {id}.
it('open-summary: rutenya cocok sebagai segmen literal, bukan sebagai /periods/{id}', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 15));

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/periods/open-summary');

    // Registered after /periods/{id} this would answer 404 NOT_FOUND, because
    // "open-summary" is a perfectly good-looking {id}. Same trap as
    // /periods/business-units/options.
    $response->assertStatus(200);
    $response->assertJsonStructure(['data', 'meta' => ['today']]);
    expect($response->json('meta.today'))->toBe('2026-10-15');
});

it('open-summary: mengembalikan satu entri per mill dengan bentuk yang disepakati', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 15));

    $period = apiPanelPeriod($this->businessUnitA, 'Okt A', '2026-10-01', '2026-10-31', [
        'sterilizer' => 'open',
        'boiler-room' => 'draft',
    ]);

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/periods/open-summary');

    $response->assertStatus(200);
    $alpha = collect($response->json('data'))->firstWhere('business_unit_id', $this->businessUnitA->id);
    $beta = collect($response->json('data'))->firstWhere('business_unit_id', $this->businessUnitB->id);

    expect($alpha['business_unit_name'])->toBe('Mill Alpha');
    expect($alpha['open_periods'])->toHaveCount(1);
    expect($alpha['open_periods'][0]['id'])->toBe($period->id);
    expect($alpha['open_periods'][0]['open_station_count'])->toBe(1);
    expect($alpha['open_periods'][0]['station_count'])->toBe(2);

    // Mill Beta has nothing open and is STILL present — the panel never hides a
    // mill, because "cannot take input today" is what it is there to say.
    expect($beta['open_periods'])->toBe([]);
});

// Case 63
it('open-summary: entri periode tidak memuat is_running maupun is_past_range', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 15));
    apiPanelPeriod($this->businessUnitA, 'Okt A', '2026-10-01', '2026-10-31', ['sterilizer' => 'open']);

    $entry = collect($this->actingAs($this->admin, 'web')->getJson('/api/periods/open-summary')->json('data'))
        ->firstWhere('business_unit_id', $this->businessUnitA->id)['open_periods'][0];

    expect(array_keys($entry))->toBe([
        'id', 'name', 'start_date', 'end_date', 'open_station_count', 'station_count',
    ]);
});

// Case 60
it('open-summary: business_unit_id yang tidak ada menjawab 200 dengan data kosong, bukan 404', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 15));

    $response = $this->actingAs($this->admin, 'web')
        ->getJson('/api/periods/open-summary?business_unit_id=11111111-2222-3333-4444-555555555555');

    // It summarises; it does not fetch one resource. An unmatched filter is a
    // legitimate empty result.
    $response->assertStatus(200);
    expect($response->json('data'))->toBe([]);
});

// Case 62
it('open-summary: tidak terpaginasi — page dan per_page diabaikan dan meta tidak memuatnya', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 15));
    apiPanelPeriod($this->businessUnitA, 'Okt A', '2026-10-01', '2026-10-31', ['sterilizer' => 'open']);

    $full = $this->actingAs($this->admin, 'web')->getJson('/api/periods/open-summary');
    $paged = $this->actingAs($this->admin, 'web')->getJson('/api/periods/open-summary?page=2&per_page=1');

    // Row count is bounded by the number of mills, not of periods; a panel that
    // paged would say different things about the same mill per page.
    expect($paged->json('data'))->toBe($full->json('data'));
    expect(array_keys($paged->json('meta')))->toBe(['today']);
});

it('open-summary: menyaring ke satu mill lewat business_unit_id', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 15));
    Station::factory()->forBusinessUnit($this->businessUnitB)->sterilizer()->create();
    apiPanelPeriod($this->businessUnitA, 'Okt A', '2026-10-01', '2026-10-31', ['sterilizer' => 'open']);
    apiPanelPeriod($this->businessUnitB, 'Okt B', '2026-10-01', '2026-10-31', ['sterilizer' => 'open']);

    $response = $this->actingAs($this->admin, 'web')
        ->getJson('/api/periods/open-summary?business_unit_id='.$this->businessUnitA->id);

    $response->assertStatus(200);
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.business_unit_id'))->toBe($this->businessUnitA->id);
    expect($response->getContent())->not->toContain('Okt B');
});
