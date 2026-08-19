<?php

/**
 * KelolaMachineryTest (Feature/Api) — screen-031--kelola-machinery /
 * usecase-031--kelola-machinery.
 *
 * Integration tests for GET/POST/PATCH/DELETE /api/machinery,
 * GET /api/machinery-groups/options, and GET /api/machinery/{id}
 * (App\Http\Controllers\Api\MachineryController). Exercises the real
 * route -> 'auth:web' + 'role:admin' middleware -> controller ->
 * MachineryService -> Eloquent chain against the sqlite in-memory testing
 * DB (RefreshDatabase, bound in tests/Pest.php for the Feature suite).
 * Mirrors tests/Feature/Api/KelolaMachineryGroupTest.php's structure and
 * tests/Feature/Api/KelolaCorporateTest.php's multipart/`logo` (here:
 * `picture`) upload conventions exactly.
 *
 * CRITICAL — the structural rules this screen exists to enforce, each
 * with a dedicated test below: `station_id`/`business_unit_id` are
 * NEVER trusted from the request body, and are always independently
 * re-derived from the selected MachineryGroup's own values; the
 * `insurances`/`tax_purchases` child-row collections are replaced
 * wholesale on update() only when their key is present in the request
 * body; delete() has NO guard of any kind.
 *
 * `$request->only()`-then-multipart caveat: nested array fields
 * (insurances/tax_purchases) are sent as JSON via ->postJson()/
 * ->patchJson() whenever no file upload is part of the same request
 * (plain JSON body correctly parses into nested arrays); requests that
 * also carry a `picture` upload use ->post()/->patch() with the standard
 * `field[index][subfield]` multipart array-encoding.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\Machinery;
use App\Models\MachineryGroup;
use App\Models\MachineryInsurance;
use App\Models\MachineryTaxPurchase;
use App\Models\Station;
use App\Models\User;
use App\Services\MachineryService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->admin = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->businessUnit)->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnit)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnit)->create();

    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->group = MachineryGroup::factory()->forStation($this->station)->create([
        'business_unit_id' => $this->businessUnit->id,
    ]);
});

// --- create ------------------------------------------------------------------

it('berhasil: creates a machinery and returns 201 with the expected row shape', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/machinery', [
        'machinery_group_id' => $this->group->id,
        'equipment_code' => 'EQ-API-001',
        'name' => 'Boiler Utama',
    ]);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'id', 'machinery_group_id', 'machinery_group_code', 'station_id', 'business_unit_id',
        'equipment_code', 'name', 'picture', 'picture_url', 'insurances', 'tax_purchases', 'created_at',
    ]);
    $response->assertJson([
        'equipment_code' => 'EQ-API-001',
        'name' => 'Boiler Utama',
        'station_id' => $this->station->id,
        'business_unit_id' => $this->businessUnit->id,
    ]);

    expect(Machinery::where('equipment_code', 'EQ-API-001')->exists())->toBeTrue();
});

it('ignores spoofed station_id/business_unit_id sent in the request body', function () {
    $spoofedStation = Station::factory()->create();
    $spoofedBusinessUnit = BusinessUnit::factory()->create();

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/machinery', [
        'machinery_group_id' => $this->group->id,
        'station_id' => $spoofedStation->id,
        'business_unit_id' => $spoofedBusinessUnit->id,
        'equipment_code' => 'EQ-API-SPOOF',
        'name' => 'Mesin Spoof',
    ]);

    $response->assertStatus(201);
    $response->assertJson([
        'station_id' => $this->station->id,
        'business_unit_id' => $this->businessUnit->id,
    ]);

    $machinery = Machinery::where('equipment_code', 'EQ-API-SPOOF')->firstOrFail();
    expect($machinery->station_id)->toBe($this->station->id);
    expect($machinery->business_unit_id)->toBe($this->businessUnit->id);
});

it('creates a machinery with N insurance and M tax_purchase rows', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/machinery', [
        'machinery_group_id' => $this->group->id,
        'equipment_code' => 'EQ-API-CHILD',
        'name' => 'Mesin Berbaris',
        'insurances' => [
            ['ownership' => 'Perusahaan', 'insurance_policy_no' => 'POL-1'],
            ['ownership' => 'Perusahaan', 'insurance_policy_no' => 'POL-2'],
        ],
        'tax_purchases' => [
            ['policy_type' => 'Cash'],
        ],
    ]);

    $response->assertStatus(201);
    expect($response->json('insurances'))->toHaveCount(2);
    expect($response->json('tax_purchases'))->toHaveCount(1);
});

it('returns 422 with errors.name when creating with an empty name', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/machinery', [
        'machinery_group_id' => $this->group->id,
        'equipment_code' => 'EQ-API-002',
        'name' => '',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name']);
});

it('returns 422 with errors.equipment_code when creating without an equipment_code', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/machinery', [
        'machinery_group_id' => $this->group->id,
        'name' => 'Mesin Tanpa Kode',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['equipment_code']);
});

it('returns 422 with errors.equipment_code when the equipment_code already exists', function () {
    Machinery::factory()->forFullMachineryGroup($this->group)->withEquipmentCode('EQ-API-DUP')->create();

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/machinery', [
        'machinery_group_id' => $this->group->id,
        'equipment_code' => 'EQ-API-DUP',
        'name' => 'Mesin Duplikat',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['equipment_code']);
});

it('returns 422 with errors.machinery_group_id when machinery_group_id does not exist', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/machinery', [
        'machinery_group_id' => '00000000-0000-0000-0000-000000000000',
        'equipment_code' => 'EQ-API-003',
        'name' => 'Mesin Group Tak Ada',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['machinery_group_id']);
});

it('uploads a valid picture and returns a picture_url, storing the file', function () {
    Storage::fake(MachineryService::PICTURE_DISK);

    $response = $this->actingAs($this->admin, 'web')->post('/api/machinery', [
        'machinery_group_id' => $this->group->id,
        'equipment_code' => 'EQ-API-PIC',
        'name' => 'Mesin Berfoto',
        'picture' => UploadedFile::fake()->create('picture.jpg', 500, 'image/jpeg'),
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('picture_url', fn ($url) => ! empty($url));

    $storedPath = $response->json('picture');
    expect($storedPath)->not->toBeNull();
    Storage::disk(MachineryService::PICTURE_DISK)->assertExists($storedPath);
});

// --- detail --------------------------------------------------------------------

it('returns 200 with insurances/tax_purchases arrays on GET /api/machinery/{id}', function () {
    $machinery = Machinery::factory()->forFullMachineryGroup($this->group)->create();
    MachineryInsurance::factory()->forMachinery($machinery)->create(['insurance_policy_no' => 'POL-DETAIL']);
    MachineryTaxPurchase::factory()->forMachinery($machinery)->create();

    $response = $this->actingAs($this->admin, 'web')->getJson("/api/machinery/{$machinery->id}");

    $response->assertOk();
    $response->assertJsonCount(1, 'insurances');
    $response->assertJsonCount(1, 'tax_purchases');
    $response->assertJsonPath('insurances.0.insurance_policy_no', 'POL-DETAIL');
});

it('returns 404 when fetching detail for a non-existent machinery id', function () {
    $response = $this->actingAs($this->admin, 'web')->getJson('/api/machinery/00000000-0000-0000-0000-000000000000');

    $response->assertStatus(404);
});

// --- list / options --------------------------------------------------------------

it('lists machinery without child arrays and with machinery_group_code', function () {
    Machinery::factory()->forFullMachineryGroup($this->group)->create(['name' => 'Mesin List']);

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/machinery');

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
    $response->assertJsonPath('data.0.machinery_group_code', $this->group->group_code);
    expect($response->json('data.0'))->not->toHaveKey('insurances');
});

it('filters the list by machinery_group_id', function () {
    $otherGroup = MachineryGroup::factory()->forStation($this->station)->create(['business_unit_id' => $this->businessUnit->id]);
    Machinery::factory()->forFullMachineryGroup($this->group)->create();
    Machinery::factory()->forFullMachineryGroup($otherGroup)->create();

    $response = $this->actingAs($this->admin, 'web')->getJson("/api/machinery?machinery_group_id={$this->group->id}");

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
});

it('returns machinery-groups/options with id/group_code/station_id/business_unit_id', function () {
    $response = $this->actingAs($this->admin, 'web')->getJson('/api/machinery-groups/options');

    $response->assertOk();
    $response->assertJsonFragment([
        'id' => $this->group->id,
        'group_code' => $this->group->group_code,
        'station_id' => $this->station->id,
        'business_unit_id' => $this->businessUnit->id,
    ]);
});

// --- update --------------------------------------------------------------------

it('updates a machinery and returns 200 with the updated row', function () {
    $machinery = Machinery::factory()->forFullMachineryGroup($this->group)->create(['name' => 'Lama']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/machinery/{$machinery->id}", [
        'machinery_group_id' => $this->group->id,
        'equipment_code' => $machinery->equipment_code,
        'name' => 'Baru',
    ]);

    $response->assertOk();
    $response->assertJson(['id' => $machinery->id, 'name' => 'Baru']);
    expect($machinery->fresh()->name)->toBe('Baru');
});

it('updates a machinery keeping its own equipment_code unchanged without errors', function () {
    $machinery = Machinery::factory()->forFullMachineryGroup($this->group)->withEquipmentCode('EQ-API-SELF')->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/machinery/{$machinery->id}", [
        'machinery_group_id' => $this->group->id,
        'equipment_code' => 'EQ-API-SELF',
        'name' => 'Nama Baru',
    ]);

    $response->assertOk();
});

it('returns 422 with errors.equipment_code when updating to an equipment_code taken by another machinery', function () {
    Machinery::factory()->forFullMachineryGroup($this->group)->withEquipmentCode('EQ-API-TAKEN')->create();
    $target = Machinery::factory()->forFullMachineryGroup($this->group)->withEquipmentCode('EQ-API-TARGET')->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/machinery/{$target->id}", [
        'machinery_group_id' => $this->group->id,
        'equipment_code' => 'EQ-API-TAKEN',
        'name' => $target->name,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['equipment_code']);
});

it('returns 404 when updating a non-existent machinery', function () {
    $response = $this->actingAs($this->admin, 'web')->patchJson('/api/machinery/00000000-0000-0000-0000-000000000000', [
        'machinery_group_id' => $this->group->id,
        'equipment_code' => 'EQ-API-404',
        'name' => 'Tidak Ada',
    ]);

    $response->assertStatus(404);
});

it('replaces all child rows on update when insurances/tax_purchases keys are present', function () {
    $machinery = Machinery::factory()->forFullMachineryGroup($this->group)->create();
    MachineryInsurance::factory()->forMachinery($machinery)->count(2)->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/machinery/{$machinery->id}", [
        'machinery_group_id' => $this->group->id,
        'equipment_code' => $machinery->equipment_code,
        'name' => $machinery->name,
        'insurances' => [
            ['ownership' => 'Baru', 'insurance_policy_no' => 'POL-REPLACED'],
        ],
    ]);

    $response->assertOk();
    $response->assertJsonCount(1, 'insurances');
    expect(MachineryInsurance::where('machinery_id', $machinery->id)->count())->toBe(1);
});

it('leaves child rows untouched on update when insurances/tax_purchases keys are absent', function () {
    $machinery = Machinery::factory()->forFullMachineryGroup($this->group)->create();
    MachineryInsurance::factory()->forMachinery($machinery)->count(2)->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/machinery/{$machinery->id}", [
        'machinery_group_id' => $this->group->id,
        'equipment_code' => $machinery->equipment_code,
        'name' => 'Nama Saja',
    ]);

    $response->assertOk();
    expect(MachineryInsurance::where('machinery_id', $machinery->id)->count())->toBe(2);
});

// --- delete ----------------------------------------------------------------------

it('deletes a machinery with no child rows and returns 200', function () {
    $machinery = Machinery::factory()->forFullMachineryGroup($this->group)->create();

    $response = $this->actingAs($this->admin, 'web')->deleteJson("/api/machinery/{$machinery->id}");

    $response->assertOk();
    $response->assertExactJson(['deleted' => true]);
    expect(Machinery::find($machinery->id))->toBeNull();
});

it('deletes a machinery with child rows successfully, with no 409/guard of any kind', function () {
    $machinery = Machinery::factory()->forFullMachineryGroup($this->group)->create();
    MachineryInsurance::factory()->forMachinery($machinery)->count(2)->create();
    MachineryTaxPurchase::factory()->forMachinery($machinery)->count(2)->create();

    $response = $this->actingAs($this->admin, 'web')->deleteJson("/api/machinery/{$machinery->id}");

    $response->assertOk();
    $response->assertExactJson(['deleted' => true]);
    expect(Machinery::find($machinery->id))->toBeNull();
    expect(MachineryInsurance::where('machinery_id', $machinery->id)->count())->toBe(0);
    expect(MachineryTaxPurchase::where('machinery_id', $machinery->id)->count())->toBe(0);
});

it('returns 404 when deleting a non-existent machinery', function () {
    $response = $this->actingAs($this->admin, 'web')->deleteJson('/api/machinery/00000000-0000-0000-0000-000000000000');

    $response->assertStatus(404);
});

// --- access control ----------------------------------------------------------------

it('akses ditolak: returns 403 for the list endpoint when the user is not an admin', function (string $role) {
    $user = match ($role) {
        'supervisor' => $this->supervisor,
        'mill_management' => $this->millManagement,
        'operator' => $this->operator,
    };

    $response = $this->actingAs($user, 'web')->getJson('/api/machinery');

    $response->assertStatus(403);
})->with([
    'supervisor' => ['supervisor'],
    'mill management' => ['mill_management'],
    'operator' => ['operator'],
]);

it('returns 401 for the list endpoint when there is no authenticated session', function () {
    $response = $this->getJson('/api/machinery');

    $response->assertStatus(401);
});
