<?php

/**
 * DetailGradingTest (Feature/Api) — screen-020--detail-grading-web /
 * usecase-020--detail-grading-web.
 *
 * Integration tests for GET /api/grading-records/{id}
 * (App\Http\Controllers\Api\GradingRecordController::show()), one per
 * test_scenarios' api_test step(s). Exercises the real route -> 'auth:web'
 * + 'role' middleware -> controller -> GradingRecordService -> Eloquent
 * chain against the sqlite in-memory testing DB, mirroring
 * DetailWeighbridgeTest.php's setup/conventions.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\GradingDetail;
use App\Models\GradingParameter;
use App\Models\GradingRecord;
use App\Models\Station;
use App\Models\User;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->create();
});

// Scenario: "Lihat Detail Grading — berhasil"
it('berhasil: returns the full record with resolved names and details grid', function () {
    $record = GradingRecord::factory()->forStation($this->station)->create();
    $parameter = GradingParameter::factory()->create(['name' => 'Masak']);
    GradingDetail::factory()->forGradingRecord($record)->forGradingParameter($parameter)->create();

    $response = $this->actingAs($this->supervisor, 'web')->getJson("/api/grading-records/{$record->id}");

    $response->assertOk();
    $response->assertJsonFragment([
        'id' => $record->id,
        'grading_number' => $record->grading_number,
        'station_name' => $this->station->name,
    ]);
    $response->assertJsonFragment(['grading_parameter_name' => 'Masak']);
});

it('Mill Management and Admin can also access the detail endpoint', function () {
    $record = GradingRecord::factory()->forStation($this->station)->create();

    $this->actingAs($this->millManagement, 'web')->getJson("/api/grading-records/{$record->id}")->assertOk();
    $this->actingAs($this->admin, 'web')->getJson("/api/grading-records/{$record->id}")->assertOk();
});

it('returns 403 for the Operator role (route-level role gate)', function () {
    $record = GradingRecord::factory()->forStation($this->station)->create();

    $response = $this->actingAs($this->operator, 'web')->getJson("/api/grading-records/{$record->id}");

    $response->assertStatus(403);
});

// Scenario: "Lihat Detail Grading — Record Tidak Ditemukan"
it('returns 404 when the id does not exist', function () {
    $response = $this->actingAs($this->admin, 'web')->getJson('/api/grading-records/00000000-0000-0000-0000-000000000000');

    $response->assertStatus(404);
});

it('returns null acknowledged_by_name when not set', function () {
    $record = GradingRecord::factory()->forStation($this->station)->create(['acknowledged_by' => null]);

    $response = $this->actingAs($this->admin, 'web')->getJson("/api/grading-records/{$record->id}");

    $response->assertOk();
    $response->assertJsonFragment(['acknowledged_by_name' => null]);
});

it('rejects unauthenticated requests', function () {
    $record = GradingRecord::factory()->forStation($this->station)->create();

    $response = $this->getJson("/api/grading-records/{$record->id}");

    $response->assertStatus(401);
});
