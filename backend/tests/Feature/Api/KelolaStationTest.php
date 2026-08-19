<?php

/**
 * KelolaStationTest (Feature/Api) — screen-030--kelola-station /
 * usecase-030--kelola-station.
 *
 * Integration tests for GET /api/stations, GET /api/business-units/options,
 * POST/PATCH/DELETE /api/stations (App\Http\Controllers\Api\
 * StationController). Exercises the real route -> 'auth:web' +
 * 'role:admin' middleware -> controller -> StationService -> Eloquent
 * chain against the sqlite in-memory testing DB (RefreshDatabase, bound
 * in tests/Pest.php for the Feature suite). Mirrors tests/Feature/Api/
 * KelolaCompanyTest.php's structure exactly (both are fully admin-gated
 * at every endpoint, including index() — unlike KelolaBusinessUnitTest.php
 * whose GET /api/business-units is a deliberate public exception).
 *
 * Session auth: authenticated via $this->actingAs($user, 'web') — matches
 * config/auth.php's 'web' session guard, the same guard this screen's
 * routes are gated by ('auth:web' in routes/api.php).
 *
 * Response shape note: shared_decisions.error_format is
 * `{ "message": ..., "errors": {...} }` — ApiExceptionHandler only adds
 * `errors` for 422 ValidationException responses; 404 (ModelNotFoundException)
 * and 409 (StationHasMachineryException, a plain HttpException) render as
 * `{ "message": ... }` only.
 *
 * CRITICAL divergence from KelolaCompanyTest.php/KelolaBusinessUnitTest.php:
 * Station has no logo field at all — no multipart/file-upload tests here.
 * `code` is OPTIONAL (nullable) rather than required, but still globally
 * unique when present. `is_active` carries an extra cross-field rule on
 * top of the plain `boolean` rule: it may only be true when `type` is one
 * of weighbridge/grading/cages-track — never true when `type` is "other".
 * See the dedicated tests below; this is the most important edge case for
 * this screen, exercised here at the Api layer (also covered at the
 * Service unit level and the Livewire component level).
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\Machinery;
use App\Models\MachineryGroup;
use App\Models\Station;
use App\Models\User;

beforeEach(function () {
    // Shared business unit for all beforeEach users (mirrors
    // tests/Feature/Api/KelolaCompanyTest.php) — without this, each
    // User::factory() call's default business_unit_id => BusinessUnit::factory()
    // would create one incidental BusinessUnit row per user, inflating
    // any meta.total assertion below.
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->admin = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->businessUnit)->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnit)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnit)->create();
});

// Scenario: "Kelola Station — success"
it('berhasil: loads business unit options then creates a station, returns 201 with the expected row shape', function () {
    $optionsResponse = $this->actingAs($this->admin, 'web')->getJson('/api/business-units/options');
    $optionsResponse->assertOk();
    $optionsResponse->assertJsonFragment(['name' => $this->businessUnit->name]);

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/stations', [
        'business_unit_id' => $this->businessUnit->id,
        'name' => 'Weighbridge Utama',
        'type' => 'weighbridge',
        'is_active' => true,
        'code' => 'STA-API-001',
        'description' => 'Weighbridge utama pabrik',
    ]);

    $response->assertStatus(201);
    $response->assertJsonFragment([
        'name' => 'Weighbridge Utama',
        'business_unit_id' => $this->businessUnit->id,
        'business_unit_name' => $this->businessUnit->name,
        'type' => 'weighbridge',
        'is_active' => true,
        'code' => 'STA-API-001',
        'machinery_group_count' => 0,
    ]);
    expect(Station::where('name', 'Weighbridge Utama')->exists())->toBeTrue();
});

// Scenario: "Kelola Station — Edit Station"
it('Edit Station: updates the name, type, and business unit then returns 200 with the updated row', function () {
    $businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Unit Tujuan']);
    $station = Station::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'Weighbridge Lama']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/stations/{$station->id}", [
        'business_unit_id' => $businessUnitB->id,
        'name' => 'Weighbridge Baru',
        'type' => 'grading',
        'is_active' => true,
        'code' => $station->code,
        'description' => $station->description,
    ]);

    $response->assertOk();
    $response->assertJsonFragment([
        'id' => $station->id,
        'name' => 'Weighbridge Baru',
        'type' => 'grading',
        'business_unit_id' => $businessUnitB->id,
        'business_unit_name' => 'Mill Unit Tujuan',
    ]);
    expect($station->fresh()->name)->toBe('Weighbridge Baru');
    expect($station->fresh()->business_unit_id)->toBe($businessUnitB->id);
});

// Scenario: "Kelola Station — Hapus Station — berhasil"
it('Hapus Station berhasil: deletes a station with no related machinery, returns 200', function () {
    $station = Station::factory()->forBusinessUnit($this->businessUnit)->create();

    $response = $this->actingAs($this->admin, 'web')->deleteJson("/api/stations/{$station->id}");

    $response->assertOk();
    $response->assertExactJson(['deleted' => true]);
    expect(Station::find($station->id))->toBeNull();
});

// Scenario: "Kelola Station — Hapus Station — ditolak (MachineryGroup)"
it('Hapus Station ditolak: returns 409 STATION_HAS_MACHINERY and keeps the row when it has a related MachineryGroup', function () {
    $station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    MachineryGroup::factory()->forStation($station)->create();

    $response = $this->actingAs($this->admin, 'web')->deleteJson("/api/stations/{$station->id}");

    $response->assertStatus(409);
    $response->assertJsonStructure(['message']);
    $response->assertJsonMissing(['deleted' => true]);
    expect(Station::find($station->id))->not->toBeNull();
});

// Scenario: "Kelola Station — Hapus Station — ditolak (Machinery)"
it('Hapus Station ditolak: returns 409 STATION_HAS_MACHINERY and keeps the row when it has a related Machinery', function () {
    $station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    Machinery::factory()->create(['station_id' => $station->id]);

    $response = $this->actingAs($this->admin, 'web')->deleteJson("/api/stations/{$station->id}");

    $response->assertStatus(409);
    $response->assertJsonStructure(['message']);
    expect(Station::find($station->id))->not->toBeNull();
});

// Kode duplikat (create): 422 under errors.code.
it('Kode duplikat (create): returns 422 with errors.code when the code already exists', function () {
    Station::factory()->forBusinessUnit($this->businessUnit)->withCode('STA-API-DUP')->create();

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/stations', [
        'business_unit_id' => $this->businessUnit->id,
        'name' => 'Weighbridge Kode Duplikat',
        'type' => 'weighbridge',
        'is_active' => true,
        'code' => 'STA-API-DUP',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['code']);
    expect(Station::where('name', 'Weighbridge Kode Duplikat')->exists())->toBeFalse();
});

// Kode duplikat (edit): 422 under errors.code.
it('Kode duplikat (edit): returns 422 with errors.code when updating to a code taken by another station', function () {
    Station::factory()->forBusinessUnit($this->businessUnit)->withCode('STA-API-OTHER')->create();
    $target = Station::factory()->forBusinessUnit($this->businessUnit)->withCode('STA-API-TARGET')->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/stations/{$target->id}", [
        'business_unit_id' => $this->businessUnit->id,
        'name' => $target->name,
        'type' => 'weighbridge',
        'is_active' => true,
        'code' => 'STA-API-OTHER',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['code']);
    expect($target->fresh()->code)->toBe('STA-API-TARGET');
});

// Keep-own-code-unchanged on update must succeed (no false-positive
// uniqueness violation against self).
it('succeeds when updating a station and keeping its own code unchanged', function () {
    $station = Station::factory()->forBusinessUnit($this->businessUnit)->withCode('STA-API-SELF')->create(['name' => 'Weighbridge Lama']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/stations/{$station->id}", [
        'business_unit_id' => $this->businessUnit->id,
        'name' => 'Weighbridge Baru Nama',
        'type' => 'weighbridge',
        'is_active' => true,
        'code' => 'STA-API-SELF',
    ]);

    $response->assertOk();
    $response->assertJsonFragment(['code' => 'STA-API-SELF', 'name' => 'Weighbridge Baru Nama']);
});

// CRITICAL — the cross-field rule (create branch): is_active=true with
// type=other -> 422 under is_active, mentions "Other".
it('Status/Type invalid (create): returns 422 with errors.is_active when is_active=true and type=other', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/stations', [
        'business_unit_id' => $this->businessUnit->id,
        'name' => 'Station Other Aktif',
        'type' => 'other',
        'is_active' => true,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['is_active']);
    expect($response->json('errors.is_active.0'))->toContain('Other');
    expect(Station::where('name', 'Station Other Aktif')->exists())->toBeFalse();
});

// CRITICAL — the cross-field rule (edit branch): same rule enforced on
// update().
it('Status/Type invalid (edit): returns 422 with errors.is_active when updating to is_active=true and type=other', function () {
    $station = Station::factory()->forBusinessUnit($this->businessUnit)->create(['type' => \App\Enums\StationType::Weighbridge, 'is_active' => true]);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/stations/{$station->id}", [
        'business_unit_id' => $this->businessUnit->id,
        'name' => $station->name,
        'type' => 'other',
        'is_active' => true,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['is_active']);
    expect($station->fresh()->type->value)->toBe('weighbridge');
});

// Allows is_active=true for weighbridge/grading/cages-track, and
// is_active=false for type=other.
it('allows is_active=true for non-other types and is_active=false for type=other', function (string $type, bool $isActive) {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/stations', [
        'business_unit_id' => $this->businessUnit->id,
        'name' => "Station Valid $type $isActive",
        'type' => $type,
        'is_active' => $isActive,
    ]);

    $response->assertStatus(201);
    $response->assertJsonFragment(['type' => $type, 'is_active' => $isActive]);
})->with([
    'weighbridge active' => ['weighbridge', true],
    'grading active' => ['grading', true],
    'cages-track active' => ['cages-track', true],
    'other inactive' => ['other', false],
]);

// Nama kosong -> 422 under errors.name.
it('returns 422 with errors.name when creating with an empty name', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/stations', [
        'business_unit_id' => $this->businessUnit->id,
        'name' => '',
        'type' => 'weighbridge',
        'is_active' => true,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name']);
});

// business_unit_id tidak ada -> 422 under errors.business_unit_id.
it('returns 422 with errors.business_unit_id when creating with a non-existent business_unit_id', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/stations', [
        'business_unit_id' => '00000000-0000-0000-0000-000000000000',
        'name' => 'Station Tanpa BU',
        'type' => 'weighbridge',
        'is_active' => true,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['business_unit_id']);
});

// type tidak ada -> 422 under errors.type.
it('returns 422 with errors.type when creating without a type', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/stations', [
        'business_unit_id' => $this->businessUnit->id,
        'name' => 'Station Tanpa Type',
        'is_active' => true,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['type']);
});

// type invalid -> 422 under errors.type.
it('returns 422 with errors.type when creating with an invalid type', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/stations', [
        'business_unit_id' => $this->businessUnit->id,
        'name' => 'Station Type Salah',
        'type' => 'invalid-type',
        'is_active' => true,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['type']);
});

// is_active tidak ada -> 422 under errors.is_active.
it('returns 422 with errors.is_active when creating without is_active', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/stations', [
        'business_unit_id' => $this->businessUnit->id,
        'name' => 'Station Tanpa Status',
        'type' => 'weighbridge',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['is_active']);
});

// is_active non-boolean -> 422 under errors.is_active.
it('returns 422 with errors.is_active when is_active is not a boolean', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/stations', [
        'business_unit_id' => $this->businessUnit->id,
        'name' => 'Station Status Salah',
        'type' => 'weighbridge',
        'is_active' => 'bukan-boolean',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['is_active']);
});

// Happy path — create: minimal fields, code/description omitted -> null.
it('creates a station successfully when code and description are omitted', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/stations', [
        'business_unit_id' => $this->businessUnit->id,
        'name' => 'Weighbridge Minimal',
        'type' => 'weighbridge',
        'is_active' => true,
    ]);

    $response->assertStatus(201);
    $response->assertJsonFragment(['code' => null, 'description' => null]);
});

// 404 — update a non-existent station.
it('returns 404 when updating a non-existent station', function () {
    $response = $this->actingAs($this->admin, 'web')->patchJson('/api/stations/00000000-0000-0000-0000-000000000000', [
        'business_unit_id' => $this->businessUnit->id,
        'name' => 'Any',
        'type' => 'weighbridge',
        'is_active' => true,
    ]);

    $response->assertStatus(404);
    $response->assertJsonStructure(['message']);
});

// 404 — delete a non-existent station.
it('returns 404 when deleting a non-existent station', function () {
    $response = $this->actingAs($this->admin, 'web')->deleteJson('/api/stations/00000000-0000-0000-0000-000000000000');

    $response->assertStatus(404);
    $response->assertJsonStructure(['message']);
});

// businessUnitOptions(): empty list when no business units exist at all
// (deletes the shared beforeEach business unit too — a genuinely empty
// state).
it('Belum ada Business Unit: returns an empty business unit options list when none exist', function () {
    BusinessUnit::query()->delete();

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/business-units/options');

    $response->assertOk();
    $response->assertExactJson(['data' => []]);
});

// Actor-permission: index() is admin-only for this screen (unlike
// BusinessUnitController::index()'s deliberate public exception).
it('akses ditolak: returns 403 for the list endpoint when the user is not an admin', function (string $role) {
    $user = match ($role) {
        'supervisor' => $this->supervisor,
        'mill_management' => $this->millManagement,
        'operator' => $this->operator,
    };

    $response = $this->actingAs($user, 'web')->getJson('/api/stations');

    $response->assertStatus(403);
    $response->assertJsonStructure(['message']);
})->with([
    'supervisor' => ['supervisor'],
    'mill management' => ['mill_management'],
    'operator' => ['operator'],
]);

// Actor-permission: businessUnitOptions()/create/update/delete are also
// admin-only (same 'role:admin' middleware group in routes/api.php).
it('akses ditolak: returns 403 for options/create/update/delete when the user is not an admin', function () {
    $station = Station::factory()->forBusinessUnit($this->businessUnit)->create();

    $optionsResponse = $this->actingAs($this->supervisor, 'web')->getJson('/api/business-units/options');
    $optionsResponse->assertStatus(403);

    $createResponse = $this->actingAs($this->supervisor, 'web')->postJson('/api/stations', [
        'business_unit_id' => $this->businessUnit->id,
        'name' => 'Station Tidak Boleh',
        'type' => 'weighbridge',
        'is_active' => true,
    ]);
    $createResponse->assertStatus(403);

    $updateResponse = $this->actingAs($this->supervisor, 'web')->patchJson("/api/stations/{$station->id}", [
        'business_unit_id' => $this->businessUnit->id,
        'name' => 'Station Tidak Boleh',
        'type' => 'weighbridge',
        'is_active' => true,
    ]);
    $updateResponse->assertStatus(403);

    $deleteResponse = $this->actingAs($this->supervisor, 'web')->deleteJson("/api/stations/{$station->id}");
    $deleteResponse->assertStatus(403);

    expect(Station::find($station->id))->not->toBeNull();
});

// Auth-guard coverage: unauthenticated requests must not reach the service
// at all, for every endpoint.
it('returns 401 for every endpoint when there is no authenticated session', function () {
    $station = Station::factory()->forBusinessUnit($this->businessUnit)->create();

    $this->getJson('/api/stations')->assertStatus(401);
    $this->getJson('/api/business-units/options')->assertStatus(401);
    $this->postJson('/api/stations', [
        'business_unit_id' => $this->businessUnit->id,
        'name' => 'Tanpa Sesi',
        'type' => 'weighbridge',
        'is_active' => true,
    ])->assertStatus(401);
    $this->patchJson("/api/stations/{$station->id}", [
        'business_unit_id' => $this->businessUnit->id,
        'name' => 'Tanpa Sesi',
        'type' => 'weighbridge',
        'is_active' => true,
    ])->assertStatus(401);
    $this->deleteJson("/api/stations/{$station->id}")->assertStatus(401);
});

// Baseline happy-path list coverage: business_unit_name +
// machinery_group_count per row, pagination meta shape, and the optional
// business_unit_id filter.
it('lists stations paginated with business_unit_name and machinery_group_count per row for an admin', function () {
    $station = Station::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'Weighbridge Alpha']);
    MachineryGroup::factory()->forStation($station)->count(2)->create();

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/stations');

    $response->assertOk();
    $response->assertJson([
        'meta' => [
            'page' => 1,
            'per_page' => 20,
            'total' => Station::count(),
            'total_pages' => 1,
        ],
    ]);
    $response->assertJsonFragment([
        'name' => 'Weighbridge Alpha',
        'business_unit_name' => $this->businessUnit->name,
        'machinery_group_count' => 2,
    ]);
});

// List filtered by business_unit_id query param returns only that
// business unit's stations.
it('filters the list by business_unit_id when the query param is provided', function () {
    $businessUnitB = BusinessUnit::factory()->create();
    Station::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'Weighbridge A1']);
    Station::factory()->forBusinessUnit($businessUnitB)->create(['name' => 'Weighbridge B1']);

    $response = $this->actingAs($this->admin, 'web')->getJson("/api/stations?business_unit_id={$this->businessUnit->id}");

    $response->assertOk();
    $response->assertJsonFragment(['name' => 'Weighbridge A1']);
    $response->assertJsonMissing(['name' => 'Weighbridge B1']);
});

// List pagination — page/per_page query params respected.
it('paginates the station list by page and per_page query params', function () {
    Station::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'Weighbridge A']);
    Station::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'Weighbridge B']);

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/stations?page=2&per_page=1');

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
