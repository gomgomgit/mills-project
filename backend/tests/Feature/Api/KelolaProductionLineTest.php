<?php

/**
 * KelolaProductionLineTest (Feature/Api) — screen-036--kelola-production-line /
 * usecase-036--kelola-production-line.
 *
 * Integration tests for GET /api/production-lines, GET
 * /api/production-lines/business-units/options, POST/PATCH/DELETE
 * /api/production-lines, and the self-scoped mobile-facing GET
 * /api/production-lines/current + /api/production-lines/current/stations
 * (App\Http\Controllers\Api\ProductionLineController). Exercises the real
 * route -> middleware -> controller -> ProductionLineService -> Eloquent
 * chain against the sqlite in-memory testing DB. Mirrors tests/Feature/Api/
 * KelolaMachineryGroupTest.php's structure.
 *
 * create() auto-provisions the 15 canonical DEFAULT_STATIONS rows (3
 * active + 12 placeholder) — covered here by asserting station_count and
 * a direct Station::where('production_line_id', ...) count.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\Machinery;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create(['name' => 'Mill Unit Awal']);
    $this->admin = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->businessUnit)->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnit)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnit)->create();
});

// Scenario: "Kelola Production Line — success"
it('berhasil: loads business unit options then creates a production line, auto-provisioning 15 stations', function () {
    $optionsResponse = $this->actingAs($this->admin, 'web')->getJson('/api/production-lines/business-units/options');
    $optionsResponse->assertOk();
    $optionsResponse->assertJsonFragment(['name' => $this->businessUnit->name]);

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/production-lines', [
        'business_unit_id' => $this->businessUnit->id,
        'name' => 'Line 01',
        'code' => 'PL-API-001',
        'description' => 'Lini produksi utama',
    ]);

    $response->assertStatus(201);
    $response->assertJsonFragment([
        'name' => 'Line 01',
        'code' => 'PL-API-001',
        'business_unit_id' => $this->businessUnit->id,
        'business_unit_name' => $this->businessUnit->name,
        'description' => 'Lini produksi utama',
        'station_count' => 15,
    ]);

    $productionLine = ProductionLine::where('code', 'PL-API-001')->firstOrFail();
    expect(Station::where('production_line_id', $productionLine->id)->count())->toBe(15);
    expect(Station::where('production_line_id', $productionLine->id)->where('type', 'weighbridge')->where('is_active', true)->count())->toBe(1);
    expect(Station::where('production_line_id', $productionLine->id)->where('is_active', false)->count())->toBe(12);
});

it('Edit: updates name/code/description, returns 200 with the updated row', function () {
    $productionLine = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/production-lines/{$productionLine->id}", [
        'business_unit_id' => $this->businessUnit->id,
        'name' => 'Line Baru',
        'code' => 'PL-API-BARU',
        'description' => 'Deskripsi baru',
    ]);

    $response->assertOk();
    $response->assertJsonFragment([
        'id' => $productionLine->id,
        'name' => 'Line Baru',
        'code' => 'PL-API-BARU',
        'description' => 'Deskripsi baru',
    ]);
    expect($productionLine->fresh()->name)->toBe('Line Baru');
});

it('Edit tidak membuat ulang station: jumlah station tetap 15 setelah update', function () {
    $productionLine = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create();
    foreach (range(1, 15) as $i) {
        Station::factory()->forProductionLine($productionLine)->create();
    }

    $this->actingAs($this->admin, 'web')->patchJson("/api/production-lines/{$productionLine->id}", [
        'business_unit_id' => $this->businessUnit->id,
        'name' => 'Line Update',
    ])->assertOk();

    expect(Station::where('production_line_id', $productionLine->id)->count())->toBe(15);
});

it('Hapus berhasil: deletes a production line with no related stations, returns 200', function () {
    $productionLine = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create();

    $response = $this->actingAs($this->admin, 'web')->deleteJson("/api/production-lines/{$productionLine->id}");

    $response->assertOk();
    $response->assertExactJson(['deleted' => true]);
    expect(ProductionLine::find($productionLine->id))->toBeNull();
});

it('Hapus ditolak: returns 409 PRODUCTION_LINE_HAS_STATIONS and keeps the row when it has related Station', function () {
    $productionLine = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create();
    Station::factory()->forProductionLine($productionLine)->create();

    $response = $this->actingAs($this->admin, 'web')->deleteJson("/api/production-lines/{$productionLine->id}");

    $response->assertStatus(409);
    $response->assertJsonStructure(['message']);
    $response->assertJsonMissing(['deleted' => true]);
    expect(ProductionLine::find($productionLine->id))->not->toBeNull();
});

it('Kode duplikat (create): returns 422 with errors.code when the code already exists', function () {
    ProductionLine::factory()->forBusinessUnit($this->businessUnit)->withCode('PL-API-DUP')->create();

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/production-lines', [
        'business_unit_id' => $this->businessUnit->id,
        'name' => 'Line Dup',
        'code' => 'PL-API-DUP',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['code']);
});

it('Kode duplikat (edit): returns 422 with errors.code when updating to a code taken by another production line', function () {
    ProductionLine::factory()->forBusinessUnit($this->businessUnit)->withCode('PL-API-OTHER')->create();
    $target = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->withCode('PL-API-TARGET')->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/production-lines/{$target->id}", [
        'business_unit_id' => $this->businessUnit->id,
        'name' => $target->name,
        'code' => 'PL-API-OTHER',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['code']);
    expect($target->fresh()->code)->toBe('PL-API-TARGET');
});

it('succeeds when updating a production line and keeping its own code unchanged', function () {
    $productionLine = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->withCode('PL-API-SELF')->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/production-lines/{$productionLine->id}", [
        'business_unit_id' => $this->businessUnit->id,
        'name' => $productionLine->name,
        'code' => 'PL-API-SELF',
    ]);

    $response->assertOk();
    $response->assertJsonFragment(['code' => 'PL-API-SELF']);
});

it('returns 422 with errors.business_unit_id when creating without a business_unit_id', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/production-lines', [
        'name' => 'Line Tanpa BU',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['business_unit_id']);
});

it('returns 422 with errors.business_unit_id when creating with a non-existent business_unit_id', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/production-lines', [
        'business_unit_id' => '00000000-0000-0000-0000-000000000000',
        'name' => 'Line BU Salah',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['business_unit_id']);
});

it('returns 422 with errors.name when creating with an empty name', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/production-lines', [
        'business_unit_id' => $this->businessUnit->id,
        'name' => '',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name']);
});

it('creates a production line successfully when optional fields are omitted', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/production-lines', [
        'business_unit_id' => $this->businessUnit->id,
        'name' => 'Line Minimal',
    ]);

    $response->assertStatus(201);
    $response->assertJsonFragment(['code' => null, 'description' => null]);
});

it('returns 404 when updating a non-existent production line', function () {
    $response = $this->actingAs($this->admin, 'web')->patchJson('/api/production-lines/00000000-0000-0000-0000-000000000000', [
        'business_unit_id' => $this->businessUnit->id,
        'name' => 'Line Any',
    ]);

    $response->assertStatus(404);
    $response->assertJsonStructure(['message']);
});

it('returns 404 when deleting a non-existent production line', function () {
    $response = $this->actingAs($this->admin, 'web')->deleteJson('/api/production-lines/00000000-0000-0000-0000-000000000000');

    $response->assertStatus(404);
    $response->assertJsonStructure(['message']);
});

it('Belum ada Business Unit: returns an empty options list when none exist', function () {
    Station::query()->delete();
    ProductionLine::query()->delete();
    User::query()->update(['business_unit_id' => null]);
    BusinessUnit::query()->delete();

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/production-lines/business-units/options');

    $response->assertOk();
    $response->assertExactJson(['data' => []]);
});

it('akses ditolak: returns 403 for the list endpoint when the user is not an admin', function (string $role) {
    $user = match ($role) {
        'supervisor' => $this->supervisor,
        'mill_management' => $this->millManagement,
        'operator' => $this->operator,
    };

    $response = $this->actingAs($user, 'web')->getJson('/api/production-lines');

    $response->assertStatus(403);
    $response->assertJsonStructure(['message']);
})->with([
    'supervisor' => ['supervisor'],
    'mill management' => ['mill_management'],
    'operator' => ['operator'],
]);

it('akses ditolak: returns 403 for options/create/update/delete when the user is not an admin', function () {
    $productionLine = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create();

    $this->actingAs($this->supervisor, 'web')->getJson('/api/production-lines/business-units/options')->assertStatus(403);

    $this->actingAs($this->supervisor, 'web')->postJson('/api/production-lines', [
        'business_unit_id' => $this->businessUnit->id,
        'name' => 'Tidak Boleh',
    ])->assertStatus(403);

    $this->actingAs($this->supervisor, 'web')->patchJson("/api/production-lines/{$productionLine->id}", [
        'business_unit_id' => $this->businessUnit->id,
        'name' => 'Tidak Boleh',
    ])->assertStatus(403);

    $this->actingAs($this->supervisor, 'web')->deleteJson("/api/production-lines/{$productionLine->id}")->assertStatus(403);

    expect(ProductionLine::find($productionLine->id))->not->toBeNull();
});

it('returns 401 for every admin CRUD endpoint when there is no authenticated session', function () {
    $productionLine = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create();

    $this->getJson('/api/production-lines')->assertStatus(401);
    $this->getJson('/api/production-lines/business-units/options')->assertStatus(401);
    $this->postJson('/api/production-lines', [
        'business_unit_id' => $this->businessUnit->id,
        'name' => 'Tanpa Sesi',
    ])->assertStatus(401);
    $this->patchJson("/api/production-lines/{$productionLine->id}", [
        'business_unit_id' => $this->businessUnit->id,
        'name' => 'Tanpa Sesi',
    ])->assertStatus(401);
    $this->deleteJson("/api/production-lines/{$productionLine->id}")->assertStatus(401);
});

it('lists production lines paginated with business_unit_name and station_count per row for an admin', function () {
    $productionLine = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->withCode('PL-API-LIST')->create();
    Station::factory()->forProductionLine($productionLine)->count(2)->create();

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/production-lines');

    $response->assertOk();
    $response->assertJson([
        'meta' => [
            'page' => 1,
            'per_page' => 20,
            'total' => ProductionLine::count(),
        ],
    ]);
    $response->assertJsonFragment([
        'code' => 'PL-API-LIST',
        'business_unit_name' => $this->businessUnit->name,
        'station_count' => 2,
    ]);
});

it('filters the list by business_unit_id when the query param is provided', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create();
    ProductionLine::factory()->forBusinessUnit($this->businessUnit)->withCode('PL-API-A1')->create();
    ProductionLine::factory()->forBusinessUnit($otherBusinessUnit)->withCode('PL-API-B1')->create();

    $response = $this->actingAs($this->admin, 'web')->getJson("/api/production-lines?business_unit_id={$this->businessUnit->id}");

    $response->assertOk();
    $response->assertJsonFragment(['code' => 'PL-API-A1']);
    $response->assertJsonMissing(['code' => 'PL-API-B1']);
});

// ── Self-scoped mobile-facing endpoints ────────────────────────────────

it('current(): returns only the authenticated user\'s own business unit\'s production lines', function () {
    $ownLine = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'Line Milikku']);
    $otherBusinessUnit = BusinessUnit::factory()->create();
    ProductionLine::factory()->forBusinessUnit($otherBusinessUnit)->create(['name' => 'Line Bukan Milikku']);

    $response = $this->actingAs($this->operator, 'web')->getJson('/api/production-lines/current');

    $response->assertOk();
    $response->assertJsonFragment(['id' => $ownLine->id, 'name' => 'Line Milikku']);
    $response->assertJsonMissing(['name' => 'Line Bukan Milikku']);
});

it('current(): returns 404 when the authenticated user has no business_unit_id', function () {
    $user = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    $response = $this->actingAs($user, 'web')->getJson('/api/production-lines/current');

    $response->assertStatus(404);
});

it('currentStations(): returns stations for the given production_line_id with machinery_count on the cages-track station', function () {
    $productionLine = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create();
    $cagesStation = Station::factory()->forProductionLine($productionLine)->cagesTrack()->create();
    Machinery::factory()->count(4)->create(['station_id' => $cagesStation->id]);
    $weighbridgeStation = Station::factory()->forProductionLine($productionLine)->weighbridge()->create();

    $response = $this->actingAs($this->operator, 'web')->getJson("/api/production-lines/current/stations?production_line_id={$productionLine->id}");

    $response->assertOk();
    $response->assertJsonFragment(['id' => $cagesStation->id, 'machinery_count' => 4]);
    $response->assertJsonFragment(['id' => $weighbridgeStation->id, 'machinery_count' => null]);
});

it('currentStations(): returns 404 when production_line_id belongs to a different business unit', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create();
    $otherLine = ProductionLine::factory()->forBusinessUnit($otherBusinessUnit)->create();

    $response = $this->actingAs($this->operator, 'web')->getJson("/api/production-lines/current/stations?production_line_id={$otherLine->id}");

    $response->assertStatus(404);
});

it('mobile sync (Sanctum): current()/currentStations() are reachable via Sanctum-authenticated Operator', function () {
    $productionLine = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create();
    \Laravel\Sanctum\Sanctum::actingAs($this->operator, ['*']);

    $this->getJson('/api/production-lines/current')->assertOk();
    $this->getJson("/api/production-lines/current/stations?production_line_id={$productionLine->id}")->assertOk();
});

it('returns 401 for current()/currentStations() when there is no authenticated session', function () {
    $productionLine = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create();

    $this->getJson('/api/production-lines/current')->assertStatus(401);
    $this->getJson("/api/production-lines/current/stations?production_line_id={$productionLine->id}")->assertStatus(401);
});
