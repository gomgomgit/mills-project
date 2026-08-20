<?php

/**
 * DetailWeighbridgeTest (Feature/Api) — screen-019--detail-weighbridge-web /
 * usecase-019--detail-weighbridge-web.
 *
 * Integration tests for GET /api/weighbridge-records/{id}
 * (App\Http\Controllers\Api\WeighbridgeRecordController::show()), one per
 * test_scenarios' api_test step(s). Exercises the real route -> 'auth:web'
 * + 'role' middleware -> controller -> WeighbridgeRecordService -> Eloquent
 * chain against the sqlite in-memory testing DB, mirroring
 * DataBrowserWeighbridgeTest.php's setup/conventions.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\Station;
use App\Models\User;
use App\Models\WeighbridgeRecord;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->create();
});

// Scenario: "Lihat Detail Weighbridge — berhasil"
it('berhasil: returns the full record with resolved names', function () {
    $checker = User::factory()->create(['name' => 'Budi Supervisor']);
    $record = WeighbridgeRecord::factory()
        ->forStation($this->station)
        ->ofType('dispatch')
        ->create(['checked_by' => $checker->id, 'destination' => 'PKS Sukamaju']);

    $response = $this->actingAs($this->supervisor, 'web')->getJson("/api/weighbridge-records/{$record->id}");

    $response->assertOk();
    $response->assertJsonFragment([
        'id' => $record->id,
        'weighbridge_type' => 'dispatch',
        'destination' => 'PKS Sukamaju',
        'checked_by_name' => 'Budi Supervisor',
        'station_name' => $this->station->name,
    ]);
});

it('Mill Management and Admin can also access the detail endpoint', function () {
    $record = WeighbridgeRecord::factory()->forStation($this->station)->create();

    $this->actingAs($this->millManagement, 'web')->getJson("/api/weighbridge-records/{$record->id}")->assertOk();
    $this->actingAs($this->admin, 'web')->getJson("/api/weighbridge-records/{$record->id}")->assertOk();
});

it('returns 403 for the Operator role (route-level role gate)', function () {
    $record = WeighbridgeRecord::factory()->forStation($this->station)->create();

    $response = $this->actingAs($this->operator, 'web')->getJson("/api/weighbridge-records/{$record->id}");

    $response->assertStatus(403);
});

// Scenario: "Lihat Detail Weighbridge — Record Tidak Ditemukan"
it('returns 404 when the id does not exist', function () {
    $response = $this->actingAs($this->admin, 'web')->getJson('/api/weighbridge-records/00000000-0000-0000-0000-000000000000');

    $response->assertStatus(404);
});

it('returns null checked_by_name/acknowledged_by_name and station_name still resolves when not set', function () {
    $record = WeighbridgeRecord::factory()->forStation($this->station)->create(['checked_by' => null, 'acknowledged_by' => null]);

    $response = $this->actingAs($this->admin, 'web')->getJson("/api/weighbridge-records/{$record->id}");

    $response->assertOk();
    $response->assertJsonFragment(['checked_by_name' => null, 'acknowledged_by_name' => null]);
});

it('rejects unauthenticated requests', function () {
    $record = WeighbridgeRecord::factory()->forStation($this->station)->create();

    $response = $this->getJson("/api/weighbridge-records/{$record->id}");

    $response->assertStatus(401);
});
