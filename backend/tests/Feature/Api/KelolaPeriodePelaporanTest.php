<?php

/**
 * KelolaPeriodePelaporanTest (Feature/Api) —
 * screen-128--kelola-periode-pelaporan /
 * usecase-128--kelola-periode-pelaporan +
 * usecase-140--tutup-buka-periode-pelaporan +
 * usecase-144--buka-periode-pelaporan.
 *
 * Integration tests for the screen's 9 endpoints
 * (App\Http\Controllers\Api\PeriodController):
 *   GET    /api/periods
 *   GET    /api/periods/business-units/options
 *   POST   /api/periods
 *   PATCH  /api/periods/{id}
 *   DELETE /api/periods/{id}
 *   GET    /api/period-stations/{id}/unverified-count
 *   POST   /api/period-stations/{id}/close
 *   POST   /api/period-stations/{id}/reopen
 *   POST   /api/period-stations/{id}/open
 *
 * TWO PREFIXES, AND THE ID IS NOT THE SAME THING IN BOTH (2026-09-26). The
 * /periods routes take a PERIOD id. The /period-stations routes take a
 * `period_stations` id — the `stations[].id` of PeriodService::toRow(), one
 * row per station type inside a period. periodStationId() below is the only
 * place these tests derive one, so no test can accidentally post a period id
 * at a per-station action and pass for the wrong reason.
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
 * per-station-type model made impossible to write (mixed status, closing one
 * station leaving the others alone, update()'s station backfill). Exercises
 * the real route -> EnsureRole -> controller -> PeriodService /
 * PeriodClosureService -> Eloquent chain against the sqlite in-memory testing
 * DB. Mirrors tests/Feature/Api/KelolaProductionLineTest.php's structure.
 *
 * ERROR CODE ASSERTIONS: ApiExceptionHandler emits the machine-readable
 * `code` field for VALIDATION_ERROR / NOT_FOUND / UNAUTHENTICATED and for
 * every exception implementing App\Exceptions\HasErrorCode
 * (PERIOD_OVERLAP / PERIOD_CLOSED_IMMUTABLE / PERIOD_ALREADY_CLOSED /
 * PERIOD_NOT_CLOSED / PERIOD_NOT_DRAFT) — those are asserted here.
 * FORBIDDEN is the one exception: App\Http\Middleware\EnsureRole builds its own JSON response
 * and never reaches ApiExceptionHandler, so a role rejection carries
 * `message` only (screen 4-implement known_issue). The 403 scenarios below
 * therefore assert the status and the unchanged data, not `code`.
 *
 * FOUR SCENARIOS ARE SKIPPED, NOT DELETED (14, 15, 19, 20): they assert a
 * 422 PERIOD_CLOSED from POST/PATCH /api/sterilizer-records once a period
 * is closed. That enforcement lives in the 18 *RecordService classes and
 * the mobile sync path — it is explicitly OUT OF SCOPE for screen-128
 * (see PeriodClosureService's docblock and the screen tech-spec's
 * implementation_notes; tracked by
 * usecase-141--kunci-input-periode-tertutup). The tests are written in
 * full so they become runnable the moment that use case lands — they must
 * not be "made green" by weakening the assertion.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\ProductionLine;
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

// ── usecase-140 ─────────────────────────────────────────────────────────

// Scenario 10: "Tutup & Buka Kembali Periode Pelaporan — success"
it('menutup satu stasiun periode setelah menampilkan jumlah data belum terverifikasi', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    $stationId = periodStationId($period);

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

    $sterilizerRow = periodStationId($period, 'sterilizer');
    $clarificationRow = periodStationId($period, 'clarification');

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

    $stationId = periodStationId($period);

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

    $stationId = periodStationId($period);

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

    $stationId = periodStationId($period);

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
        ->getJson('/api/period-stations/'.periodStationId($period, 'sterilizer').'/unverified-count');
    $sterilizerCount->assertOk();
    $sterilizerCount->assertJsonPath('unverified_count', 5);
    $sterilizerCount->assertJsonCount(1, 'breakdown');
    $sterilizerCount->assertJsonPath('breakdown.0.station_type', 'sterilizer');

    $threshingCount = $this->actingAs($this->admin, 'web')
        ->getJson('/api/period-stations/'.periodStationId($period, 'threshing').'/unverified-count');
    $threshingCount->assertOk();
    $threshingCount->assertJsonPath('unverified_count', 2);
    $threshingCount->assertJsonCount(1, 'breakdown');
    $threshingCount->assertJsonPath('breakdown.0.station_type', 'threshing');
});

// Scenario 14: "data mobile menyusul setelah periode ditutup"
it('menolak 422 PERIOD_CLOSED untuk data mobile yang menyusul, lalu menerimanya setelah periode dibuka kembali', function () {
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
    $rejected = $this->actingAs($this->operator, 'web')->postJson('/api/sterilizer-records', $payload);
    $rejected->assertStatus(422);
    $rejected->assertJsonPath('code', 'PERIOD_CLOSED');

    // Step 3 — reopen -> 200
    $this->actingAs($this->admin, 'web')->postJson("/api/period-stations/{$stationId}/reopen")->assertOk();

    // Step 4 — the same record now goes through -> 201; the data was never lost.
    $this->actingAs($this->operator, 'web')->postJson('/api/sterilizer-records', $payload)->assertStatus(201);
})->skip('Penegakan PERIOD_CLOSED ada di service 18 stasiun — di luar screen-128, lihat usecase-141--kunci-input-periode-tertutup');

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
    $verify = $this->actingAs($this->supervisor, 'web')
        ->patchJson("/api/sterilizer-records/{$record->id}", ['checked' => true]);
    $verify->assertStatus(422);
    $verify->assertJsonPath('code', 'PERIOD_CLOSED');

    // Step 3 — the record itself is still readable, just frozen -> 200
    $show = $this->actingAs($this->supervisor, 'web')->getJson("/api/sterilizer-records/{$record->id}");
    $show->assertOk();
    expect($record->fresh()->checked_by)->toBeNull();
})->skip('Penegakan PERIOD_CLOSED ada di service 18 stasiun — di luar screen-128, lihat usecase-141--kunci-input-periode-tertutup');

// Scenario 16: "menutup periode yang sudah tertutup"
it('menolak 409 PERIOD_ALREADY_CLOSED saat menutup stasiun yang sudah tertutup', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->range('2026-10-01', '2026-10-31')
        ->closed($this->adminA, '2026-11-01 09:14:00')
        ->create();

    $stationId = periodStationId($period);

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

    $stationId = periodStationId($period);

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

    $openStationId = periodStationId($openPeriod);
    $closedStationId = periodStationId($closedPeriod);

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

// Scenario 19: "mengubah data stasiun pada periode tertutup"
it('menolak 422 PERIOD_CLOSED untuk input baru maupun perubahan data stasiun pada periode tertutup', function () {
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
    $create = $this->actingAs($this->supervisor, 'web')->postJson('/api/sterilizer-records', [
        'production_line_id' => $this->sterilizerStation->production_line_id,
        'sterilizer_id' => 'STR-CLOSED-001',
        'date' => '2026-10-15',
        'details' => [['close_door_time' => '08:00', 'open_door_time' => '09:10']],
    ]);
    $create->assertStatus(422);
    $create->assertJsonPath('code', 'PERIOD_CLOSED');

    // Step 3 — editing an existing record inside the range -> 422 PERIOD_CLOSED
    $update = $this->actingAs($this->supervisor, 'web')
        ->patchJson("/api/sterilizer-records/{$existing->id}", ['note' => 'diubah']);
    $update->assertStatus(422);
    $update->assertJsonPath('code', 'PERIOD_CLOSED');

    // Step 4 — still readable -> 200
    $this->actingAs($this->supervisor, 'web')
        ->getJson("/api/sterilizer-records/{$existing->id}")
        ->assertOk();
})->skip('Penegakan PERIOD_CLOSED ada di service 18 stasiun — di luar screen-128, lihat usecase-141--kunci-input-periode-tertutup');

// Scenario 20: "data diinput setelah periode ditutup namun tanggal kejadiannya di luar rentang"
it('menerima data yang tanggal kejadiannya di luar rentang periode tertutup', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    // Step 1 — close -> 200
    $this->actingAs($this->admin, 'web')
        ->postJson('/api/period-stations/'.periodStationId($period).'/close')
        ->assertOk();

    // Step 2 — event date AFTER the range -> 201; period membership uses
    // the event date, never the input or sync time.
    $this->actingAs($this->supervisor, 'web')->postJson('/api/sterilizer-records', [
        'production_line_id' => $this->sterilizerStation->production_line_id,
        'sterilizer_id' => 'STR-AFTER-001',
        'date' => '2026-11-02',
        'details' => [['close_door_time' => '08:00', 'open_door_time' => '09:10']],
    ])->assertStatus(201);

    // Step 3 — event date BEFORE the range -> 201
    $this->actingAs($this->supervisor, 'web')->postJson('/api/sterilizer-records', [
        'production_line_id' => $this->sterilizerStation->production_line_id,
        'sterilizer_id' => 'STR-BEFORE-001',
        'date' => '2026-09-30',
        'details' => [['close_door_time' => '09:00', 'open_door_time' => '10:10']],
    ])->assertStatus(201);
})->skip('Penegakan PERIOD_CLOSED ada di service 18 stasiun — di luar screen-128, lihat usecase-141--kunci-input-periode-tertutup');

// ── usecase-144 (Buka Stasiun — POST /api/period-stations/{id}/open) ─────
//
// Scenarios 21–28 of the screen tech-spec. This endpoint lists 404 / 409 /
// 403 ONLY — there is deliberately no 401 case, exactly as close/reopen
// have none: session handling is middleware, not part of this contract.

/** A draft period on Mill Alpha, the only status "Buka Stasiun" accepts. */
function draftPeriodForOpenApi(BusinessUnit $businessUnit, string $name = 'Oktober 2026'): Period
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
    $period = draftPeriodForOpenApi($this->businessUnitA);
    $stationId = periodStationId($period);

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

    $stationId = periodStationId($period);

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

    $stationId = periodStationId($period);

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
    $period = draftPeriodForOpenApi($this->businessUnitA, 'Periode Sementara');
    $periodId = $period->id;
    $stationId = periodStationId($period);

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
    $period = draftPeriodForOpenApi($this->businessUnitA);
    $stationId = periodStationId($period);

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
    $period = draftPeriodForOpenApi($this->businessUnitA);
    $stationId = periodStationId($period);

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
    $period = draftPeriodForOpenApi($this->businessUnitA);
    $stationId = periodStationId($period);

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
    $period = draftPeriodForOpenApi($this->businessUnitA, 'Periode Agustus 2026');
    $stationId = periodStationId($period);

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
