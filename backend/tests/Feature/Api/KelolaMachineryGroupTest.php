<?php

/**
 * KelolaMachineryGroupTest (Feature/Api) — screen-033--kelola-machinery-group /
 * usecase-033--kelola-machinery-group.
 *
 * Integration tests for GET /api/machinery-groups, GET /api/stations/options,
 * POST/PATCH/DELETE /api/machinery-groups (App\Http\Controllers\Api\
 * MachineryGroupController). Exercises the real route -> 'auth:web' +
 * 'role:admin' middleware -> controller -> MachineryGroupService ->
 * Eloquent chain against the sqlite in-memory testing DB (RefreshDatabase,
 * bound in tests/Pest.php for the Feature suite). Mirrors tests/Feature/Api/
 * KelolaStationTest.php's structure exactly (both are fully admin-gated at
 * every endpoint, including index()).
 *
 * Session auth: authenticated via $this->actingAs($user, 'web') — matches
 * config/auth.php's 'web' session guard, the same guard this screen's
 * routes are gated by ('auth:web' in routes/api.php).
 *
 * CRITICAL divergence from KelolaStationTest.php — the structural rule
 * this screen exists to enforce: `production_line_id` is NEVER accepted
 * from the request body. See the dedicated "spoofed production_line_id"
 * tests below; this is the most important behaviour for this screen,
 * exercised here at the Api layer (also covered at the Service unit level
 * and the Livewire component level). `group_code` is REQUIRED (unlike
 * Station's optional `code`) and globally unique — mirrors Corporate/
 * Company/BusinessUnit's required `*_code` fields rather than Station's
 * nullable one. There is no is_active/type cross-field rule at all on
 * this entity.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\Machinery;
use App\Models\MachineryGroup;
use App\Models\Station;
use App\Models\User;

beforeEach(function () {
    // Shared business unit/station for all beforeEach users and fixtures
    // (mirrors tests/Feature/Api/KelolaStationTest.php) — avoids inflating
    // meta.total assertions with incidental factory-created rows.
    $this->businessUnit = BusinessUnit::factory()->create(['name' => 'Mill Unit Awal']);
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'Weighbridge Awal']);
    $this->admin = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->businessUnit)->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnit)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnit)->create();
});

// Scenario: "Kelola Machinery Group — success"
it('berhasil: loads station options then creates a machinery group, returns 201 with the expected row shape', function () {
    $optionsResponse = $this->actingAs($this->admin, 'web')->getJson('/api/stations/options');
    $optionsResponse->assertOk();
    $optionsResponse->assertJsonFragment([
        'name' => $this->station->name,
        'production_line_id' => $this->station->production_line_id,
    ]);

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/machinery-groups', [
        'station_id' => $this->station->id,
        'group_code' => 'MG-API-001',
        'description' => 'Kelompok mesin utama',
        'unit' => 'unit',
        'workshop_factor' => 1.5,
        'cost_per_equipment' => 2500000,
    ]);

    $response->assertStatus(201);
    $response->assertJsonFragment([
        'group_code' => 'MG-API-001',
        'station_id' => $this->station->id,
        'station_name' => $this->station->name,
        'production_line_id' => $this->station->production_line_id,
        'description' => 'Kelompok mesin utama',
        'unit' => 'unit',
        'workshop_factor' => 1.5,
        'cost_per_equipment' => 2500000.0,
        'machinery_count' => 0,
    ]);
    expect(MachineryGroup::where('group_code', 'MG-API-001')->exists())->toBeTrue();
});

// CRITICAL — the structural rule: a spoofed production_line_id in the
// request body is silently ignored on create(); the real one is always
// derived from the selected Station.
it('mengabaikan production_line_id yang dikirim manual: selalu diturunkan dari Station (create)', function () {
    $otherProductionLine = \App\Models\ProductionLine::factory()->create();

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/machinery-groups', [
        'station_id' => $this->station->id,
        'production_line_id' => $otherProductionLine->id,
        'group_code' => 'MG-API-SPOOF',
    ]);

    $response->assertStatus(201);
    $response->assertJsonFragment(['production_line_id' => $this->station->production_line_id]);
    $response->assertJsonMissing(['production_line_id' => $otherProductionLine->id]);

    $fresh = MachineryGroup::where('group_code', 'MG-API-SPOOF')->firstOrFail();
    expect($fresh->production_line_id)->toBe($this->station->production_line_id);
});

// CRITICAL — same rule on update(): a spoofed production_line_id is
// ignored, and production_line_id is re-derived from a NEW station_id
// when the station is changed.
it('mengabaikan production_line_id yang dikirim manual: selalu diturunkan dari Station (edit)', function () {
    $otherStation = Station::factory()->forBusinessUnit(BusinessUnit::factory()->create())->create(['name' => 'Weighbridge Tujuan']);
    $machineryGroup = MachineryGroup::factory()->forStation($this->station)->create();

    $spoofedProductionLine = \App\Models\ProductionLine::factory()->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/machinery-groups/{$machineryGroup->id}", [
        'station_id' => $otherStation->id,
        'production_line_id' => $spoofedProductionLine->id,
        'group_code' => $machineryGroup->group_code,
    ]);

    $response->assertOk();
    $response->assertJsonFragment([
        'station_id' => $otherStation->id,
        'production_line_id' => $otherStation->production_line_id,
    ]);
    expect($machineryGroup->fresh()->production_line_id)->toBe($otherStation->production_line_id);
});

// Scenario: "Kelola Machinery Group — Edit Machinery Group"
it('Edit Machinery Group: updates the group_code, description, and station then returns 200 with the updated row', function () {
    $stationB = Station::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'Weighbridge Tujuan']);
    $machineryGroup = MachineryGroup::factory()->forStation($this->station)->withGroupCode('MG-API-LAMA')->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/machinery-groups/{$machineryGroup->id}", [
        'station_id' => $stationB->id,
        'group_code' => 'MG-API-BARU',
        'description' => 'Deskripsi baru',
    ]);

    $response->assertOk();
    $response->assertJsonFragment([
        'id' => $machineryGroup->id,
        'group_code' => 'MG-API-BARU',
        'station_id' => $stationB->id,
        'station_name' => 'Weighbridge Tujuan',
        'description' => 'Deskripsi baru',
    ]);
    expect($machineryGroup->fresh()->group_code)->toBe('MG-API-BARU');
    expect($machineryGroup->fresh()->station_id)->toBe($stationB->id);
});

// Scenario: "Kelola Machinery Group — Hapus — berhasil"
it('Hapus berhasil: deletes a machinery group with no related machinery, returns 200', function () {
    $machineryGroup = MachineryGroup::factory()->forStation($this->station)->create();

    $response = $this->actingAs($this->admin, 'web')->deleteJson("/api/machinery-groups/{$machineryGroup->id}");

    $response->assertOk();
    $response->assertExactJson(['deleted' => true]);
    expect(MachineryGroup::find($machineryGroup->id))->toBeNull();
});

// Scenario: "Kelola Machinery Group — Hapus — ditolak (Machinery)"
it('Hapus ditolak: returns 409 MACHINERY_GROUP_HAS_MACHINERY and keeps the row when it has related Machinery', function () {
    $machineryGroup = MachineryGroup::factory()->forStation($this->station)->create();
    Machinery::factory()->forMachineryGroup($machineryGroup)->create(['station_id' => $this->station->id]);

    $response = $this->actingAs($this->admin, 'web')->deleteJson("/api/machinery-groups/{$machineryGroup->id}");

    $response->assertStatus(409);
    $response->assertJsonStructure(['message']);
    $response->assertJsonMissing(['deleted' => true]);
    expect(MachineryGroup::find($machineryGroup->id))->not->toBeNull();
});

// Kode duplikat (create): 422 under errors.group_code.
it('Kode duplikat (create): returns 422 with errors.group_code when the group_code already exists', function () {
    MachineryGroup::factory()->forStation($this->station)->withGroupCode('MG-API-DUP')->create();

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/machinery-groups', [
        'station_id' => $this->station->id,
        'group_code' => 'MG-API-DUP',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['group_code']);
    expect(MachineryGroup::where('station_id', $this->station->id)->count())->toBe(1);
});

// Kode duplikat (edit): 422 under errors.group_code.
it('Kode duplikat (edit): returns 422 with errors.group_code when updating to a group_code taken by another machinery group', function () {
    MachineryGroup::factory()->forStation($this->station)->withGroupCode('MG-API-OTHER')->create();
    $target = MachineryGroup::factory()->forStation($this->station)->withGroupCode('MG-API-TARGET')->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/machinery-groups/{$target->id}", [
        'station_id' => $this->station->id,
        'group_code' => 'MG-API-OTHER',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['group_code']);
    expect($target->fresh()->group_code)->toBe('MG-API-TARGET');
});

// Keep-own-code-unchanged on update must succeed (no false-positive
// uniqueness violation against self).
it('succeeds when updating a machinery group and keeping its own group_code unchanged', function () {
    $machineryGroup = MachineryGroup::factory()->forStation($this->station)->withGroupCode('MG-API-SELF')->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/machinery-groups/{$machineryGroup->id}", [
        'station_id' => $this->station->id,
        'group_code' => 'MG-API-SELF',
        'unit' => 'set',
    ]);

    $response->assertOk();
    $response->assertJsonFragment(['group_code' => 'MG-API-SELF', 'unit' => 'set']);
});

// station_id tidak ada -> 422 under errors.station_id.
it('returns 422 with errors.station_id when creating without a station_id', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/machinery-groups', [
        'group_code' => 'MG-API-NOSTATION',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['station_id']);
});

// station_id tidak ditemukan -> 422 under errors.station_id.
it('returns 422 with errors.station_id when creating with a non-existent station_id', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/machinery-groups', [
        'station_id' => '00000000-0000-0000-0000-000000000000',
        'group_code' => 'MG-API-BADSTATION',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['station_id']);
});

// Kode kosong -> 422 under errors.group_code.
it('returns 422 with errors.group_code when creating with an empty group_code', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/machinery-groups', [
        'station_id' => $this->station->id,
        'group_code' => '',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['group_code']);
});

// workshop_factor non-numeric -> 422 under errors.workshop_factor.
it('returns 422 with errors.workshop_factor when workshop_factor is not numeric', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/machinery-groups', [
        'station_id' => $this->station->id,
        'group_code' => 'MG-API-BADWF',
        'workshop_factor' => 'bukan-angka',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['workshop_factor']);
});

// Happy path — create: minimal fields, optional fields omitted -> null.
it('creates a machinery group successfully when optional fields are omitted', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/machinery-groups', [
        'station_id' => $this->station->id,
        'group_code' => 'MG-API-MINIMAL',
    ]);

    $response->assertStatus(201);
    $response->assertJsonFragment(['description' => null, 'unit' => null, 'workshop_factor' => null, 'cost_per_equipment' => null]);
});

// 404 — update a non-existent machinery group.
it('returns 404 when updating a non-existent machinery group', function () {
    $response = $this->actingAs($this->admin, 'web')->patchJson('/api/machinery-groups/00000000-0000-0000-0000-000000000000', [
        'station_id' => $this->station->id,
        'group_code' => 'MG-API-ANY',
    ]);

    $response->assertStatus(404);
    $response->assertJsonStructure(['message']);
});

// 404 — delete a non-existent machinery group.
it('returns 404 when deleting a non-existent machinery group', function () {
    $response = $this->actingAs($this->admin, 'web')->deleteJson('/api/machinery-groups/00000000-0000-0000-0000-000000000000');

    $response->assertStatus(404);
    $response->assertJsonStructure(['message']);
});

// stationOptions(): empty list when no stations exist at all.
it('Belum ada Station: returns an empty station options list when none exist', function () {
    Station::query()->delete();

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/stations/options');

    $response->assertOk();
    $response->assertExactJson(['data' => []]);
});

// Actor-permission: index() is admin-only for this screen.
it('akses ditolak: returns 403 for the list endpoint when the user is not an admin', function (string $role) {
    $user = match ($role) {
        'supervisor' => $this->supervisor,
        'mill_management' => $this->millManagement,
        'operator' => $this->operator,
    };

    $response = $this->actingAs($user, 'web')->getJson('/api/machinery-groups');

    $response->assertStatus(403);
    $response->assertJsonStructure(['message']);
})->with([
    'supervisor' => ['supervisor'],
    'mill management' => ['mill_management'],
    'operator' => ['operator'],
]);

// Actor-permission: stationOptions()/create/update/delete are also
// admin-only (same 'role:admin' middleware group in routes/api.php).
it('akses ditolak: returns 403 for options/create/update/delete when the user is not an admin', function () {
    $machineryGroup = MachineryGroup::factory()->forStation($this->station)->create();

    $optionsResponse = $this->actingAs($this->supervisor, 'web')->getJson('/api/stations/options');
    $optionsResponse->assertStatus(403);

    $createResponse = $this->actingAs($this->supervisor, 'web')->postJson('/api/machinery-groups', [
        'station_id' => $this->station->id,
        'group_code' => 'MG-API-TIDAKBOLEH',
    ]);
    $createResponse->assertStatus(403);

    $updateResponse = $this->actingAs($this->supervisor, 'web')->patchJson("/api/machinery-groups/{$machineryGroup->id}", [
        'station_id' => $this->station->id,
        'group_code' => 'MG-API-TIDAKBOLEH',
    ]);
    $updateResponse->assertStatus(403);

    $deleteResponse = $this->actingAs($this->supervisor, 'web')->deleteJson("/api/machinery-groups/{$machineryGroup->id}");
    $deleteResponse->assertStatus(403);

    expect(MachineryGroup::find($machineryGroup->id))->not->toBeNull();
});

// Auth-guard coverage: unauthenticated requests must not reach the
// service at all, for every endpoint.
it('returns 401 for every endpoint when there is no authenticated session', function () {
    $machineryGroup = MachineryGroup::factory()->forStation($this->station)->create();

    $this->getJson('/api/machinery-groups')->assertStatus(401);
    $this->getJson('/api/stations/options')->assertStatus(401);
    $this->postJson('/api/machinery-groups', [
        'station_id' => $this->station->id,
        'group_code' => 'MG-API-TANPASESI',
    ])->assertStatus(401);
    $this->patchJson("/api/machinery-groups/{$machineryGroup->id}", [
        'station_id' => $this->station->id,
        'group_code' => 'MG-API-TANPASESI',
    ])->assertStatus(401);
    $this->deleteJson("/api/machinery-groups/{$machineryGroup->id}")->assertStatus(401);
});

// Baseline happy-path list coverage: station_name + production_line_id +
// machinery_count per row, pagination meta shape, and the optional
// station_id filter.
it('lists machinery groups paginated with station_name, production_line_id, and machinery_count per row for an admin', function () {
    $machineryGroup = MachineryGroup::factory()->forStation($this->station)->withGroupCode('MG-API-LIST')->create();
    Machinery::factory()->forMachineryGroup($machineryGroup)->count(2)->create(['station_id' => $this->station->id]);

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/machinery-groups');

    $response->assertOk();
    $response->assertJson([
        'meta' => [
            'page' => 1,
            'per_page' => 20,
            'total' => MachineryGroup::count(),
        ],
    ]);
    $response->assertJsonFragment([
        'group_code' => 'MG-API-LIST',
        'station_name' => $this->station->name,
        'production_line_id' => $this->station->production_line_id,
        'machinery_count' => 2,
    ]);
});

// List filtered by station_id query param returns only that station's
// machinery groups.
it('filters the list by station_id when the query param is provided', function () {
    $stationB = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    MachineryGroup::factory()->forStation($this->station)->withGroupCode('MG-API-A1')->create();
    MachineryGroup::factory()->forStation($stationB)->withGroupCode('MG-API-B1')->create();

    $response = $this->actingAs($this->admin, 'web')->getJson("/api/machinery-groups?station_id={$this->station->id}");

    $response->assertOk();
    $response->assertJsonFragment(['group_code' => 'MG-API-A1']);
    $response->assertJsonMissing(['group_code' => 'MG-API-B1']);
});

// List pagination — page/per_page query params respected.
it('paginates the machinery group list by page and per_page query params', function () {
    MachineryGroup::factory()->forStation($this->station)->withGroupCode('MG-API-PA')->create();
    MachineryGroup::factory()->forStation($this->station)->withGroupCode('MG-API-PB')->create();

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/machinery-groups?page=2&per_page=1');

    $response->assertOk();
    $response->assertJson([
        'meta' => [
            'page' => 2,
            'per_page' => 1,
            'total' => 2,
            'total_pages' => 2,
        ],
    ]);
    expect($response->json('data'))->toHaveCount(1);
});
