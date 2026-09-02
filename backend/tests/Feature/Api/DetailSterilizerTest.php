<?php

/**
 * DetailSterilizerTest (Feature/Api) —
 * screen-125--detail-sterilizer-web /
 * usecase-125--detail-sterilizer-web.
 *
 * Integration tests for GET /api/sterilizer-records/{id}. Mirrors
 * DetailCpoDispatchTest.php's structure exactly.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\Station;
use App\Models\SterilizerDetail;
use App\Models\SterilizerRecord;
use App\Models\User;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->sterilizer()->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->create();
});

// Scenario: "Lihat Detail Sterilizer - berhasil"
it('berhasil: returns the full record with resolved names and the detail cycle log', function () {
    $record = SterilizerRecord::factory()->forStation($this->station)->create();
    SterilizerDetail::factory()->forRecord($record)->create(['sterilizer_no' => '3']);

    $response = $this->actingAs($this->supervisor, 'web')->getJson("/api/sterilizer-records/{$record->id}");

    $response->assertOk();
    $response->assertJsonFragment([
        'id' => $record->id,
        'sterilizer_id' => $record->sterilizer_id,
        'station_name' => $this->station->name,
    ]);
    $response->assertJsonFragment(['sterilizer_no' => '3']);
});

it('includes duration_minutes in the detail rows', function () {
    $record = SterilizerRecord::factory()->forStation($this->station)->create();
    SterilizerDetail::factory()->forRecord($record)->create(['close_door_time' => '07:00', 'open_door_time' => '08:10', 'duration_minutes' => 70]);

    $response = $this->actingAs($this->admin, 'web')->getJson("/api/sterilizer-records/{$record->id}");

    $response->assertOk();
    $response->assertJsonFragment(['duration_minutes' => 70]);
});

it('Mill Management and Admin can also access the detail endpoint', function () {
    $record = SterilizerRecord::factory()->forStation($this->station)->create();

    $this->actingAs($this->millManagement, 'web')->getJson("/api/sterilizer-records/{$record->id}")->assertOk();
    $this->actingAs($this->admin, 'web')->getJson("/api/sterilizer-records/{$record->id}")->assertOk();
});

it('returns 403 for the Operator role (route-level role gate)', function () {
    $record = SterilizerRecord::factory()->forStation($this->station)->create();

    $response = $this->actingAs($this->operator, 'web')->getJson("/api/sterilizer-records/{$record->id}");

    $response->assertStatus(403);
});

// Scenario: "Lihat Detail Sterilizer - Record Tidak Ditemukan"
it('returns 404 when the id does not exist', function () {
    $response = $this->actingAs($this->admin, 'web')->getJson('/api/sterilizer-records/00000000-0000-0000-0000-000000000000');

    $response->assertStatus(404);
});

it('returns null checked_by_name and acknowledged_by_name when not set', function () {
    $record = SterilizerRecord::factory()->forStation($this->station)->create(['checked_by' => null, 'acknowledged_by' => null]);

    $response = $this->actingAs($this->admin, 'web')->getJson("/api/sterilizer-records/{$record->id}");

    $response->assertOk();
    $response->assertJsonFragment(['checked_by_name' => null, 'acknowledged_by_name' => null]);
});

it('resolves checked_by_name and acknowledged_by_name to user names when present', function () {
    $checker = User::factory()->role(UserRole::Supervisor)->create(['name' => 'Checker Person']);
    $acknowledger = User::factory()->role(UserRole::MillManagement)->create(['name' => 'Acknowledger Person']);
    $record = SterilizerRecord::factory()->forStation($this->station)->create([
        'checked_by' => $checker->id,
        'acknowledged_by' => $acknowledger->id,
    ]);

    $response = $this->actingAs($this->admin, 'web')->getJson("/api/sterilizer-records/{$record->id}");

    $response->assertOk();
    $response->assertJsonFragment(['checked_by_name' => 'Checker Person', 'acknowledged_by_name' => 'Acknowledger Person']);
});

it('rejects unauthenticated requests', function () {
    $record = SterilizerRecord::factory()->forStation($this->station)->create();

    $response = $this->getJson("/api/sterilizer-records/{$record->id}");

    $response->assertStatus(401);
});
