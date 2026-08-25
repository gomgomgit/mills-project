<?php

/**
 * DetailPressingTest (Feature/Api) — screen-054--detail-pressing-web /
 * usecase-054--detail-pressing-web.
 *
 * Integration tests for GET /api/pressing-records/{id}, mirroring
 * tests/Feature/Api/DetailThreshingTest.php's structure exactly.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\PressingDetail;
use App\Models\PressingRecord;
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

it('berhasil: returns the full record with resolved names and 24-row details grid', function () {
    $record = PressingRecord::factory()->forStation($this->station)->create();
    PressingDetail::factory()->forRecord($record)->timeSlot('09:00')->filled()->create();

    $response = $this->actingAs($this->supervisor, 'web')->getJson("/api/pressing-records/{$record->id}");

    $response->assertOk();
    $response->assertJsonFragment([
        'id' => $record->id,
        'presser_id' => $record->presser_id,
        'station_name' => $this->station->name,
    ]);
    $response->assertJsonFragment(['time_slot' => '09:00']);
});

it('returns details ordered by canonical time-slot, not creation order', function () {
    $record = PressingRecord::factory()->forStation($this->station)->create();
    PressingDetail::factory()->forRecord($record)->timeSlot('14:00')->create();
    PressingDetail::factory()->forRecord($record)->timeSlot('08:00')->create();
    PressingDetail::factory()->forRecord($record)->timeSlot('00:00')->create();

    $response = $this->actingAs($this->admin, 'web')->getJson("/api/pressing-records/{$record->id}");

    $response->assertOk();
    $slots = array_column($response->json('details'), 'time_slot');
    expect($slots)->toBe(['08:00', '14:00', '00:00']);
});

it('Mill Management and Admin can also access the detail endpoint', function () {
    $record = PressingRecord::factory()->forStation($this->station)->create();

    $this->actingAs($this->millManagement, 'web')->getJson("/api/pressing-records/{$record->id}")->assertOk();
    $this->actingAs($this->admin, 'web')->getJson("/api/pressing-records/{$record->id}")->assertOk();
});

it('returns 403 for the Operator role (route-level role gate)', function () {
    $record = PressingRecord::factory()->forStation($this->station)->create();

    $this->actingAs($this->operator, 'web')->getJson("/api/pressing-records/{$record->id}")->assertStatus(403);
});

it('returns 404 when the id does not exist', function () {
    $this->actingAs($this->admin, 'web')->getJson('/api/pressing-records/00000000-0000-0000-0000-000000000000')->assertStatus(404);
});

it('returns null checked_by_name and acknowledged_by_name when not set', function () {
    $record = PressingRecord::factory()->forStation($this->station)->create(['checked_by' => null, 'acknowledged_by' => null]);

    $response = $this->actingAs($this->admin, 'web')->getJson("/api/pressing-records/{$record->id}");

    $response->assertOk();
    $response->assertJsonFragment(['checked_by_name' => null, 'acknowledged_by_name' => null]);
});

it('resolves checked_by_name and acknowledged_by_name to user names when present', function () {
    $checker = User::factory()->role(UserRole::Supervisor)->create(['name' => 'Checker Person']);
    $acknowledger = User::factory()->role(UserRole::MillManagement)->create(['name' => 'Acknowledger Person']);
    $record = PressingRecord::factory()->forStation($this->station)->create([
        'checked_by' => $checker->id,
        'acknowledged_by' => $acknowledger->id,
    ]);

    $response = $this->actingAs($this->admin, 'web')->getJson("/api/pressing-records/{$record->id}");

    $response->assertOk();
    $response->assertJsonFragment(['checked_by_name' => 'Checker Person', 'acknowledged_by_name' => 'Acknowledger Person']);
});

it('rejects unauthenticated requests', function () {
    $record = PressingRecord::factory()->forStation($this->station)->create();

    $this->getJson("/api/pressing-records/{$record->id}")->assertStatus(401);
});
