<?php

/**
 * MillSettingTest (Feature/Api) — screen-034--mills-setting /
 * usecase-034--mills-setting.
 *
 * Integration tests for GET/PATCH /api/mill-settings/{businessUnitId},
 * GET /api/mill-settings/{businessUnitId}/stations, and
 * PATCH /api/mill-settings/{businessUnitId}/stations/{stationId}
 * (App\Http\Controllers\Api\MillSettingController). Exercises the real
 * route -> 'auth:web' + 'role:admin,mill_management' middleware ->
 * controller -> MillSettingService -> Eloquent chain against the sqlite
 * in-memory testing DB.
 *
 * CRITICAL divergence from every other master-data Api test suite in
 * this codebase: this screen has TWO actors with different access scope
 * (not just one role) — Admin may act on any business_unit_id, Mill
 * Management only on their own. Every scenario below is exercised for
 * BOTH actors where relevant.
 *
 * File uploads: PATCH with multipart body uses `->post($url, ['_method'
 * => 'PATCH', ...])` (Laravel's PATCH+JSON test helper doesn't support
 * file uploads) — mirrors tests/Feature/Api/KelolaBusinessUnitTest.php's
 * pattern. No GD extension installed, so UploadedFile::fake()->image(...)
 * throws — uses ->create($name, $sizeInKb, $mime) instead.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\MillSetting;
use App\Models\Station;
use App\Models\User;
use App\Services\MillSettingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create(['name' => 'Mill Unit Alpha']);
    $this->admin = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->businessUnit)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnit)->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnit)->create();
});

// Scenario: "Mills Setting — Admin berhasil mengatur mill terpilih"
it('berhasil: Admin loads then updates mill-setting for a chosen business unit, returns 200', function () {
    $getResponse = $this->actingAs($this->admin, 'web')->getJson("/api/mill-settings/{$this->businessUnit->id}");
    $getResponse->assertOk();
    $getResponse->assertJsonFragment(['app_name' => 'Mill Unit Alpha']);

    $patchResponse = $this->actingAs($this->admin, 'web')->patchJson("/api/mill-settings/{$this->businessUnit->id}", [
        'app_name' => 'Mill Baru',
    ]);

    $patchResponse->assertOk();
    $patchResponse->assertJsonFragment(['app_name' => 'Mill Baru']);
    expect(MillSetting::where('business_unit_id', $this->businessUnit->id)->first()->app_name)->toBe('Mill Baru');
});

// Scenario: "Mills Setting — Mill Management langsung terarah ke mill sendiri"
it('Mill Management: loads their own business unit mill-setting successfully', function () {
    $response = $this->actingAs($this->millManagement, 'web')->getJson("/api/mill-settings/{$this->businessUnit->id}");

    $response->assertOk();
    $response->assertJsonFragment(['business_unit_id' => $this->businessUnit->id]);
});

// Scenario: "Mills Setting — Mill belum punya setting, dibuat default"
it('Mill belum punya setting: GET auto-creates a default row for a business unit with none yet', function () {
    expect(MillSetting::where('business_unit_id', $this->businessUnit->id)->exists())->toBeFalse();

    $response = $this->actingAs($this->admin, 'web')->getJson("/api/mill-settings/{$this->businessUnit->id}");

    $response->assertOk();
    $response->assertJsonFragment(['app_name' => 'Mill Unit Alpha']);
    expect(MillSetting::where('business_unit_id', $this->businessUnit->id)->count())->toBe(1);
});

// Scenario: "Mills Setting — Mill Management akses mill lain ditolak"
it('Mill Management akses mill lain: GET returns 403 for a business unit other than their own', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create();

    $response = $this->actingAs($this->millManagement, 'web')->getJson("/api/mill-settings/{$otherBusinessUnit->id}");

    $response->assertStatus(403);
});

it('Mill Management akses mill lain: PATCH returns 403 for a business unit other than their own', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create();

    $response = $this->actingAs($this->millManagement, 'web')->patchJson("/api/mill-settings/{$otherBusinessUnit->id}", [
        'app_name' => 'Hack',
    ]);

    $response->assertStatus(403);
});

it('returns 403 for Supervisor and Operator roles (route-level role gate)', function (string $role) {
    $user = match ($role) {
        'supervisor' => $this->supervisor,
        'operator' => $this->operator,
    };

    $response = $this->actingAs($user, 'web')->getJson("/api/mill-settings/{$this->businessUnit->id}");

    $response->assertStatus(403);
})->with(['supervisor', 'operator']);

// File uploads.
it('stores an uploaded logo via multipart PATCH and returns a resolved logo URL', function () {
    Storage::fake(MillSettingService::LOGO_DISK);
    $logo = UploadedFile::fake()->create('logo.jpg', 500, 'image/jpeg');

    $response = $this->actingAs($this->admin, 'web')->post("/api/mill-settings/{$this->businessUnit->id}", [
        '_method' => 'PATCH',
        'logo' => $logo,
    ]);

    $response->assertOk();
    expect($response->json('logo'))->not->toBeNull();
    $stored = MillSetting::where('business_unit_id', $this->businessUnit->id)->firstOrFail();
    Storage::disk(MillSettingService::LOGO_DISK)->assertExists($stored->logo);
});

it('returns 422 when the uploaded logo has an unsupported format', function () {
    Storage::fake(MillSettingService::LOGO_DISK);
    $badFile = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $response = $this->actingAs($this->admin, 'web')->post("/api/mill-settings/{$this->businessUnit->id}", [
        '_method' => 'PATCH',
        'logo' => $badFile,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('logo');
});

// Scenario: "Mills Setting — Pilih icon station"
it('Pilih icon station: lists stations then sets an icon override, returns 200', function () {
    $station = Station::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'Weighbridge 1']);

    $listResponse = $this->actingAs($this->admin, 'web')->getJson("/api/mill-settings/{$this->businessUnit->id}/stations");
    $listResponse->assertOk();
    $listResponse->assertJsonFragment(['name' => 'Weighbridge 1', 'icon' => null]);

    $patchResponse = $this->actingAs($this->admin, 'web')->patchJson(
        "/api/mill-settings/{$this->businessUnit->id}/stations/{$station->id}",
        ['icon' => 'truck']
    );

    $patchResponse->assertOk();
    $patchResponse->assertJsonFragment(['icon' => 'truck']);
    expect($station->fresh()->icon)->toBe('truck');
});

it('resets a station icon to default when icon is sent as null', function () {
    $station = Station::factory()->forBusinessUnit($this->businessUnit)->withIcon('truck')->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson(
        "/api/mill-settings/{$this->businessUnit->id}/stations/{$station->id}",
        ['icon' => null]
    );

    $response->assertOk();
    $response->assertJsonFragment(['icon' => null]);
    expect($station->fresh()->icon)->toBeNull();
});

it('returns 422 when the sent icon is not in the supported list', function () {
    $station = Station::factory()->forBusinessUnit($this->businessUnit)->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson(
        "/api/mill-settings/{$this->businessUnit->id}/stations/{$station->id}",
        ['icon' => 'not-a-real-icon']
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('icon');
});

// Scenario: "Mills Setting — Belum ada station terdaftar"
it('Belum ada station: returns an empty stations list, not an error', function () {
    $response = $this->actingAs($this->admin, 'web')->getJson("/api/mill-settings/{$this->businessUnit->id}/stations");

    $response->assertOk();
    $response->assertExactJson(['data' => []]);
});

it('returns 404 when setting the icon for a station that does not belong to the given business unit', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create();
    $station = Station::factory()->forBusinessUnit($otherBusinessUnit)->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson(
        "/api/mill-settings/{$this->businessUnit->id}/stations/{$station->id}",
        ['icon' => 'truck']
    );

    $response->assertStatus(404);
});

it('returns 404 when the business_unit_id itself does not exist', function () {
    $response = $this->actingAs($this->admin, 'web')->getJson('/api/mill-settings/00000000-0000-0000-0000-000000000000');

    $response->assertStatus(404);
});

/**
 * GET /api/mill-settings/current + /current/stations — self-scoped,
 * mobile-facing (screen-005--home, screen-012--form-cages-track,
 * screen-006--station-list). Authenticated via Sanctum (mobile's real
 * auth guard), open to operator/supervisor — NOT gated by
 * MillSettingService::checkAccess()'s Admin/Mill-Management restriction,
 * since self-scoping via the user's own business_unit_id already is the
 * access control.
 */
it('current: operator gets their own business unit mill-setting via Sanctum auth', function () {
    Sanctum::actingAs($this->operator, ['*']);

    $response = $this->getJson('/api/mill-settings/current');

    $response->assertOk();
    $response->assertJsonFragment(['business_unit_id' => $this->businessUnit->id, 'app_name' => 'Mill Unit Alpha']);
});

it('current: auto-creates a default row if none exists yet, same as the admin path', function () {
    Sanctum::actingAs($this->supervisor, ['*']);
    expect(MillSetting::where('business_unit_id', $this->businessUnit->id)->exists())->toBeFalse();

    $response = $this->getJson('/api/mill-settings/current');

    $response->assertOk();
    expect(MillSetting::where('business_unit_id', $this->businessUnit->id)->count())->toBe(1);
});

it('current: a user with no business_unit_id gets 404', function () {
    $admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);
    Sanctum::actingAs($admin, ['*']);

    $response = $this->getJson('/api/mill-settings/current');

    $response->assertStatus(404);
});

it('current/stations: operator gets the station list (with icon) for their own business unit', function () {
    Station::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'WB 1', 'icon' => 'truck']);
    Sanctum::actingAs($this->operator, ['*']);

    $response = $this->getJson('/api/mill-settings/current/stations');

    $response->assertOk();
    $response->assertJsonFragment(['name' => 'WB 1', 'icon' => 'truck']);
});

it('current/stations: never leaks another business unit\'s stations', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create();
    Station::factory()->forBusinessUnit($otherBusinessUnit)->create(['name' => 'Other Mill Station']);
    Sanctum::actingAs($this->operator, ['*']);

    $response = $this->getJson('/api/mill-settings/current/stations');

    $response->assertOk();
    $response->assertJsonMissing(['name' => 'Other Mill Station']);
});

it('current: also reachable via the web session guard (auth:web,sanctum dual guard)', function () {
    $response = $this->actingAs($this->supervisor, 'web')->getJson('/api/mill-settings/current');

    $response->assertOk();
});

it('current: rejects unauthenticated requests', function () {
    $response = $this->getJson('/api/mill-settings/current');

    $response->assertStatus(401);
});
