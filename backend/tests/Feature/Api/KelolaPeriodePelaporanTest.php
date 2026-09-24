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
 *   GET    /api/periods/{id}/unverified-count
 *   POST   /api/periods/{id}/close
 *   POST   /api/periods/{id}/reopen
 *   POST   /api/periods/{id}/open
 *
 * One test per test_scenarios entry of the screen tech-spec (28 total),
 * each executing that scenario's api_test steps IN ORDER and asserting
 * every step's expected_status and expected_error_code. Exercises the real
 * route -> EnsureRole -> controller -> PeriodService / PeriodClosureService
 * -> Eloquent chain against the sqlite in-memory testing DB. Mirrors
 * tests/Feature/Api/KelolaProductionLineTest.php's structure.
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
use App\Models\Station;
use App\Models\SterilizerRecord;
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
 */
function periodApiPayload(array $overrides = []): array
{
    return array_merge([
        'station_type' => 'sterilizer',
        'name' => 'Oktober 2026',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-31',
    ], $overrides);
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
        'station_type' => 'sterilizer',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-31',
        'status' => 'draft',
        'closed_by' => null,
        'closed_at' => null,
    ]);

    // Step 2 — GET /api/periods?business_unit_id={{bu_a}}&status=draft -> 200
    $list = $this->actingAs($this->admin, 'web')
        ->getJson("/api/periods?business_unit_id={$this->businessUnitA->id}&status=draft");

    $list->assertOk();
    $list->assertJsonStructure(['data', 'meta' => ['page', 'per_page', 'total']]);
    $list->assertJsonPath('meta.total', 1);
    $list->assertJsonPath('data.0.name', 'Oktober 2026');
    $list->assertJsonPath('data.0.station_type_label', 'Sterilizer');
    $list->assertJsonPath('data.0.closed_by_name', null);
});

// Scenario 2: "Rentang tanggal tumpang tindih"
it('menolak rentang yang tumpang tindih pada cakupan sama maupun pada cakupan semua stasiun', function () {
    // Step 1 — 201
    $this->actingAs($this->admin, 'web')->postJson('/api/periods', periodApiPayload([
        'business_unit_id' => $this->businessUnitA->id,
    ]))->assertStatus(201);

    // Step 2 — overlapping range, same (mill, station_type) -> 422 PERIOD_OVERLAP
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

    // Step 3 — an all-station-types period covers every type, so it clashes
    // with the typed one too -> 422 PERIOD_OVERLAP
    $overlapAll = $this->actingAs($this->admin, 'web')->postJson('/api/periods', periodApiPayload([
        'business_unit_id' => $this->businessUnitA->id,
        'station_type' => null,
        'name' => 'Semua Stasiun Okt',
        'start_date' => '2026-10-10',
        'end_date' => '2026-10-20',
    ]));

    $overlapAll->assertStatus(422);
    $overlapAll->assertJsonPath('code', 'PERIOD_OVERLAP');

    expect(Period::count())->toBe(1);
});

// Scenario 3: "Tanggal selesai lebih awal dari tanggal mulai"
it('menolak 422 VALIDATION_ERROR ketika tanggal selesai mendahului tanggal mulai', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/periods', periodApiPayload([
        'business_unit_id' => $this->businessUnitA->id,
        'station_type' => null,
        'name' => 'Periode Terbalik',
        'start_date' => '2026-10-31',
        'end_date' => '2026-10-01',
    ]));

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'VALIDATION_ERROR');
    $response->assertJsonValidationErrors(['end_date']);
    expect(Period::count())->toBe(0);
});

// Scenario 4: "Nama periode sudah dipakai"
it('menolak 422 VALIDATION_ERROR ketika nama periode sudah dipakai pada cakupan yang sama', function () {
    // Step 1 — 201
    $this->actingAs($this->admin, 'web')->postJson('/api/periods', periodApiPayload([
        'business_unit_id' => $this->businessUnitA->id,
    ]))->assertStatus(201);

    // Step 2 — same name, same scope, non-overlapping range -> 422
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
});

// Scenario 5: "Mengubah atau menghapus periode yang sudah tertutup"
it('menolak 409 PERIOD_CLOSED_IMMUTABLE untuk edit maupun hapus pada periode tertutup', function () {
    // Step 1 — 201
    $create = $this->actingAs($this->admin, 'web')->postJson('/api/periods', periodApiPayload([
        'business_unit_id' => $this->businessUnitA->id,
    ]));
    $create->assertStatus(201);
    $periodId = $create->json('id');

    // Step 2 — close -> 200
    $this->actingAs($this->admin, 'web')->postJson("/api/periods/{$periodId}/close")->assertOk();

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
    expect($fresh->status->value)->toBe('closed');
});

// Scenario 6: "Belum ada Business Unit"
it('mengembalikan opsi Business Unit kosong dan menolak 422 saat belum ada Business Unit sama sekali', function () {
    Period::query()->delete();
    Station::query()->delete();
    \App\Models\ProductionLine::query()->delete();
    User::query()->update(['business_unit_id' => null]);
    BusinessUnit::query()->delete();

    // Step 1 — GET options -> 200, empty
    $options = $this->actingAs($this->admin, 'web')->getJson('/api/periods/business-units/options');
    $options->assertOk();
    $options->assertExactJson(['data' => []]);

    // Step 2 — POST without a business unit -> 422 VALIDATION_ERROR
    $create = $this->actingAs($this->admin, 'web')->postJson('/api/periods', periodApiPayload([
        'business_unit_id' => null,
        'station_type' => null,
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
        'station_type' => null,
        'name' => 'Periode Sementara',
        'start_date' => '2026-11-01',
        'end_date' => '2026-11-30',
    ]));
    $create->assertStatus(201);
    $periodId = $create->json('id');

    // Step 2 — DELETE -> 200
    $delete = $this->actingAs($this->admin, 'web')->deleteJson("/api/periods/{$periodId}");
    $delete->assertOk();
    $delete->assertExactJson(['deleted' => true]);

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

// ── usecase-140 ─────────────────────────────────────────────────────────

// Scenario 10: "Tutup & Buka Kembali Periode Pelaporan — success"
it('menutup periode terbuka setelah menampilkan jumlah data belum terverifikasi', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-10-10')->count(2)->create();

    // Step 1 — GET unverified-count -> 200
    $count = $this->actingAs($this->admin, 'web')->getJson("/api/periods/{$period->id}/unverified-count");
    $count->assertOk();
    $count->assertJsonStructure(['unverified_count', 'breakdown']);
    $count->assertJsonPath('unverified_count', 2);

    // Step 2 — POST close -> 200
    $close = $this->actingAs($this->admin, 'web')->postJson("/api/periods/{$period->id}/close");
    $close->assertOk();
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
    $list->assertJsonPath('data.0.closed_by_name', 'Admin X');
});

// Scenario 11: "buka kembali periode yang sudah tertutup"
it('membuka kembali periode tertutup dan mengosongkan catatan penutupan', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->closed($this->adminA, '2026-11-01 09:14:00')
        ->create();

    // Step 1 — POST reopen -> 200
    $reopen = $this->actingAs($this->admin, 'web')->postJson("/api/periods/{$period->id}/reopen");
    $reopen->assertOk();
    $reopen->assertJsonPath('status', 'open');
    $reopen->assertJsonPath('closed_by', null);
    $reopen->assertJsonPath('closed_at', null);

    // Step 2 — GET list filtered status=open -> 200
    $list = $this->actingAs($this->admin, 'web')->getJson('/api/periods?status=open');
    $list->assertOk();
    $list->assertJsonPath('meta.total', 1);
    $list->assertJsonPath('data.0.id', $period->id);
    $list->assertJsonPath('data.0.closed_by_name', null);
    $list->assertJsonPath('data.0.closed_at', null);
});

// Scenario 12: "Admin membatalkan penutupan"
it('tidak mengubah status periode ketika Admin hanya melihat jumlah lalu membatalkan penutupan', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    // Step 1 — GET unverified-count -> 200 (dialog opened)
    $this->actingAs($this->admin, 'web')
        ->getJson("/api/periods/{$period->id}/unverified-count")
        ->assertOk();

    // Step 2 — the Admin cancels: only the list is re-read, close is never
    // called -> 200 and the row is untouched.
    $list = $this->actingAs($this->admin, 'web')
        ->getJson("/api/periods?business_unit_id={$this->businessUnitA->id}");
    $list->assertOk();
    $list->assertJsonPath('data.0.status', 'open');
    $list->assertJsonPath('data.0.closed_by', null);
    $list->assertJsonPath('data.0.closed_at', null);

    expect($period->fresh()->status->value)->toBe('open');
});

// Scenario 13: "masih banyak data belum terverifikasi"
it('tetap menutup periode meski masih banyak data belum terverifikasi, dan jumlahnya tidak berubah', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-10-12')->count(5)->create();

    // Step 1 — GET unverified-count -> 200 with an explicit figure
    $before = $this->actingAs($this->admin, 'web')->getJson("/api/periods/{$period->id}/unverified-count");
    $before->assertOk();
    $before->assertJsonPath('unverified_count', 5);
    $before->assertJsonPath('breakdown.0.station_type', 'sterilizer');
    $before->assertJsonPath('breakdown.0.count', 5);

    // Step 2 — POST close -> 200 (the figure warns, it never blocks)
    $this->actingAs($this->admin, 'web')->postJson("/api/periods/{$period->id}/close")->assertOk();

    // Step 3 — GET unverified-count again -> 200, unchanged: verification
    // is locked too, so those records stay unverified until reopen.
    $after = $this->actingAs($this->admin, 'web')->getJson("/api/periods/{$period->id}/unverified-count");
    $after->assertOk();
    $after->assertJsonPath('unverified_count', 5);
});

// Scenario 14: "data mobile menyusul setelah periode ditutup"
it('menolak 422 PERIOD_CLOSED untuk data mobile yang menyusul, lalu menerimanya setelah periode dibuka kembali', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    // Step 1 — close -> 200
    $this->actingAs($this->admin, 'web')->postJson("/api/periods/{$period->id}/close")->assertOk();

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
    $this->actingAs($this->admin, 'web')->postJson("/api/periods/{$period->id}/reopen")->assertOk();

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

    $record = SterilizerRecord::factory()
        ->forStation($this->sterilizerStation)
        ->onDate('2026-10-15')
        ->create();

    // Step 1 — close -> 200
    $this->actingAs($this->admin, 'web')->postJson("/api/periods/{$period->id}/close")->assertOk();

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
it('menolak 409 PERIOD_ALREADY_CLOSED saat menutup periode yang sudah tertutup', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->range('2026-10-01', '2026-10-31')
        ->closed($this->adminA, '2026-11-01 09:14:00')
        ->create();

    // Step 1 — POST close on an already-closed period -> 409
    $close = $this->actingAs($this->admin, 'web')->postJson("/api/periods/{$period->id}/close");
    $close->assertStatus(409);
    $close->assertJsonPath('code', 'PERIOD_ALREADY_CLOSED');

    // Step 2 — the list still shows it closed by the FIRST closer -> 200
    $list = $this->actingAs($this->admin, 'web')->getJson('/api/periods?status=closed');
    $list->assertOk();
    $list->assertJsonPath('meta.total', 1);
    $list->assertJsonPath('data.0.closed_by_name', 'Admin A');
});

// Scenario 17: "dua Admin menutup periode bersamaan"
it('penutupan kedua tidak menimpa catatan penutup pertama saat dua Admin menutup bersamaan', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    // Step 1 — Admin A wins the race -> 200
    $first = $this->actingAs($this->adminA, 'web')->postJson("/api/periods/{$period->id}/close");
    $first->assertOk();
    $first->assertJsonPath('closed_by', $this->adminA->id);
    $closedAt = $first->json('closed_at');

    // Step 2 — Admin X confirms the same closure a moment later -> 409
    $second = $this->actingAs($this->admin, 'web')->postJson("/api/periods/{$period->id}/close");
    $second->assertStatus(409);
    $second->assertJsonPath('code', 'PERIOD_ALREADY_CLOSED');
    expect($second->json('message'))->toContain('Admin A');

    // Step 3 — the list shows Admin A, not Admin X -> 200
    $list = $this->actingAs($this->admin, 'web')->getJson('/api/periods?status=closed');
    $list->assertOk();
    $list->assertJsonPath('data.0.closed_by', $this->adminA->id);
    $list->assertJsonPath('data.0.closed_by_name', 'Admin A');
    $list->assertJsonPath('data.0.closed_at', $closedAt);
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

    // Step 1 — close -> 403
    $this->actingAs($user, 'web')->postJson("/api/periods/{$openPeriod->id}/close")->assertStatus(403);

    // Step 2 — reopen -> 403
    $this->actingAs($user, 'web')->postJson("/api/periods/{$closedPeriod->id}/reopen")->assertStatus(403);

    // Step 3 — unverified-count -> 403
    $this->actingAs($user, 'web')
        ->getJson("/api/periods/{$openPeriod->id}/unverified-count")
        ->assertStatus(403);

    // Step 4 — re-checked AS ADMIN (the scenario's 200): nothing changed.
    $list = $this->actingAs($this->admin, 'web')->getJson('/api/periods');
    $list->assertOk();
    expect($openPeriod->fresh()->status->value)->toBe('open');
    expect($closedPeriod->fresh()->closed_by)->toBe($this->adminA->id);
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

    $existing = SterilizerRecord::factory()
        ->forStation($this->sterilizerStation)
        ->onDate('2026-10-15')
        ->create();

    // Step 1 — close -> 200
    $this->actingAs($this->admin, 'web')->postJson("/api/periods/{$period->id}/close")->assertOk();

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
    $this->actingAs($this->admin, 'web')->postJson("/api/periods/{$period->id}/close")->assertOk();

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

// ── usecase-144 (Buka Periode — POST /api/periods/{id}/open) ────────────
//
// Scenarios 21–28 of the screen tech-spec. This endpoint lists 404 / 409 /
// 403 ONLY — there is deliberately no 401 case, exactly as close/reopen
// have none: session handling is middleware, not part of this contract.

/** A draft period on Mill Alpha, the only status "Buka Periode" accepts. */
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
it('membuka periode draft menjadi open tanpa menyentuh satu pun data stasiun', function () {
    $period = draftPeriodForOpenApi($this->businessUnitA);

    $records = SterilizerRecord::factory()
        ->forStation($this->sterilizerStation)
        ->onDate('2026-10-10')
        ->count(2)
        ->create();

    $before = SterilizerRecord::query()->orderBy('id')->get()
        ->map(fn ($r) => [$r->id, $r->checked_by, $r->acknowledged_by, (string) $r->updated_at])
        ->all();

    // Step 1 — POST /api/periods/{id}/open -> 200
    $open = $this->actingAs($this->admin, 'web')->postJson("/api/periods/{$period->id}/open");

    $open->assertOk();
    $open->assertExactJson([
        'id' => $period->id,
        'status' => 'open',
    ]);

    $fresh = $period->fresh();
    expect($fresh->status->value)->toBe('open');
    expect($fresh->updated_by)->toBe($this->admin->id);
    // Opening is not closing.
    expect($fresh->closed_by)->toBeNull();
    expect($fresh->closed_at)->toBeNull();

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
});

// Scenario 22: "Periode sudah terbuka"
it('menolak 409 PERIOD_NOT_DRAFT ketika periode yang dibuka sudah berstatus open', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    // Step 1 — POST open on an already-open period -> 409
    $open = $this->actingAs($this->admin, 'web')->postJson("/api/periods/{$period->id}/open");

    $open->assertStatus(409);
    $open->assertJsonPath('code', 'PERIOD_NOT_DRAFT');
    expect($open->json('message'))->toContain('sudah terbuka');
    // Only the closed case points at "Buka Kembali Periode".
    expect($open->json('message'))->not->toContain('Buka Kembali Periode');

    $fresh = $period->fresh();
    expect($fresh->status->value)->toBe('open');
    expect($fresh->updated_by)->toBeNull();
});

// Scenario 23: "Periode sudah tertutup"
it('menolak 409 PERIOD_NOT_DRAFT untuk periode tertutup dan mengarahkan ke Buka Kembali Periode', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->closed($this->adminA, '2026-11-01 09:14:00')
        ->create();

    // Step 1 — POST open on a closed period -> 409
    $open = $this->actingAs($this->admin, 'web')->postJson("/api/periods/{$period->id}/open");

    $open->assertStatus(409);
    $open->assertJsonPath('code', 'PERIOD_NOT_DRAFT');
    // A different message from the already-open one, naming the OTHER
    // action — the two are easy to confuse and a bare refusal makes the
    // Admin think the period is broken.
    expect($open->json('message'))->toContain('sudah tertutup');
    expect($open->json('message'))->toContain('Buka Kembali Periode');

    $fresh = $period->fresh();
    expect($fresh->status->value)->toBe('closed');
    expect($fresh->closed_by)->toBe($this->adminA->id);

    // And the action that IS right for a closed period still works.
    $this->actingAs($this->admin, 'web')->postJson("/api/periods/{$period->id}/reopen")->assertOk();
});

// Scenario 24: "Periode tidak ditemukan"
it('mengembalikan 404 NOT_FOUND saat membuka periode yang sudah dihapus Admin lain', function () {
    $period = draftPeriodForOpenApi($this->businessUnitA, 'Periode Sementara');
    $periodId = $period->id;

    // Another Admin deletes it first.
    $this->actingAs($this->admin, 'web')->deleteJson("/api/periods/{$periodId}")->assertOk();

    // Step 1 — POST open on the now-gone period -> 404
    $open = $this->actingAs($this->admin, 'web')->postJson("/api/periods/{$periodId}/open");

    $open->assertStatus(404);
    $open->assertJsonPath('code', 'NOT_FOUND');

    expect(Period::find($periodId))->toBeNull();
    expect(Period::count())->toBe(0);
});

// Scenario 25: "Dua Admin membuka bersamaan"
it('pembukaan kedua ditolak 409 dan tidak menimpa catatan Admin pertama', function () {
    $period = draftPeriodForOpenApi($this->businessUnitA);

    // Step 1 — Admin A wins the race -> 200
    $first = $this->actingAs($this->adminA, 'web')->postJson("/api/periods/{$period->id}/open");
    $first->assertOk();
    $first->assertJsonPath('status', 'open');

    expect($period->fresh()->updated_by)->toBe($this->adminA->id);

    // Step 2 — Admin X confirms the same opening a moment later -> 409;
    // the conditional UPDATE's WHERE status='draft' matched nothing.
    $second = $this->actingAs($this->admin, 'web')->postJson("/api/periods/{$period->id}/open");
    $second->assertStatus(409);
    $second->assertJsonPath('code', 'PERIOD_NOT_DRAFT');
    expect($second->json('message'))->toContain('sudah terbuka');

    // No silent overwrite — updated_by is still Admin A's.
    $fresh = $period->fresh();
    expect($fresh->status->value)->toBe('open');
    expect($fresh->updated_by)->toBe($this->adminA->id);
});

// Scenario 26: "Bukan Admin mencoba membuka periode"
it('menolak 403 FORBIDDEN pada aksi buka periode untuk Supervisor dan Mill Management', function () {
    $period = draftPeriodForOpenApi($this->businessUnitA);

    // Step 1 — Supervisor -> 403
    $this->actingAs($this->supervisor, 'web')
        ->postJson("/api/periods/{$period->id}/open")
        ->assertStatus(403);

    // Step 2 — Mill Management -> 403
    $this->actingAs($this->millManagement, 'web')
        ->postJson("/api/periods/{$period->id}/open")
        ->assertStatus(403);

    // Operator too, for completeness — every non-Admin role is refused.
    $this->actingAs($this->operator, 'web')
        ->postJson("/api/periods/{$period->id}/open")
        ->assertStatus(403);

    $fresh = $period->fresh();
    expect($fresh->status->value)->toBe('draft');
    expect($fresh->updated_by)->toBeNull();
});

// Scenario 27: "status Terbuka tidak dapat dikembalikan ke Draft"
it('tidak menyediakan jalan kembali dari open ke draft lewat PATCH', function () {
    $period = draftPeriodForOpenApi($this->businessUnitA);

    // Step 1 — open -> 200
    $this->actingAs($this->admin, 'web')
        ->postJson("/api/periods/{$period->id}/open")
        ->assertOk();

    // Step 2 — PATCH trying to push it back to draft -> 200.
    //
    // 200, not 422: `status` is simply NOT one of the fields the controller
    // forwards or PeriodService::validate() accepts, so the attempt is
    // IGNORED rather than refused. What matters is the outcome — the period
    // is still open.
    $patch = $this->actingAs($this->admin, 'web')->patchJson("/api/periods/{$period->id}", [
        'business_unit_id' => $this->businessUnitA->id,
        'station_type' => 'sterilizer',
        'status' => 'draft',
        'name' => 'Percobaan Mundur',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-31',
    ]);

    $patch->assertOk();
    $patch->assertJsonPath('status', 'open');
    $patch->assertJsonPath('name', 'Percobaan Mundur');

    expect($period->fresh()->status->value)->toBe('open');
});

// Scenario 28: "periode Terbuka tetap dapat diubah dan dihapus"
it('periode yang baru dibuka tetap dapat di-PATCH dan di-DELETE', function () {
    $period = draftPeriodForOpenApi($this->businessUnitA, 'Periode Agustus 2026');

    // Step 1 — open -> 200
    $this->actingAs($this->admin, 'web')
        ->postJson("/api/periods/{$period->id}/open")
        ->assertOk();

    // Step 2 — PATCH -> 200, no PERIOD_CLOSED_IMMUTABLE: only 'closed' locks.
    $patch = $this->actingAs($this->admin, 'web')->patchJson("/api/periods/{$period->id}", [
        'business_unit_id' => $this->businessUnitA->id,
        'station_type' => 'sterilizer',
        'name' => 'Periode Agustus 2026 (revisi)',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-31',
    ]);
    $patch->assertOk();
    $patch->assertJsonPath('name', 'Periode Agustus 2026 (revisi)');
    $patch->assertJsonPath('status', 'open');

    // Step 3 — DELETE -> 200
    $delete = $this->actingAs($this->admin, 'web')->deleteJson("/api/periods/{$period->id}");
    $delete->assertOk();
    $delete->assertExactJson(['deleted' => true]);

    expect(Period::find($period->id))->toBeNull();
});
