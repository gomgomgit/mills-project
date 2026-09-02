<?php

/**
 * FormEngineRoomTest (Feature/Api) — screen-117--form-engine-room-web /
 * usecase-102--form-engine-room-web.
 *
 * Integration tests for POST /api/engine-room-records and PATCH
 * /api/engine-room-records/{id}, mirroring
 * tests/Feature/Api/FormStorageTankTest.php's structure exactly.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\EngineRoomDetail;
use App\Models\EngineRoomRecord;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->engineRoomStation = Station::factory()->forBusinessUnit($this->businessUnit)->engineRoom()->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->create();
});

function engineRoomApiPayload(array $overrides = []): array
{
    return array_merge([
        'engine_room_id' => 'ER-API-001',
        'date' => '2026-08-31',
        'details' => [['time_slot' => '07:00', 'steam_turbine_inlet_pressure_bar' => 12.5]],
    ], $overrides);
}

it('berhasil: creates a new record with status=saved, resolved station_id, and only the given detail rows', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/engine-room-records', engineRoomApiPayload([
        'production_line_id' => $this->engineRoomStation->production_line_id,
    ]));

    $response->assertCreated();
    $response->assertJsonFragment(['station_id' => $this->engineRoomStation->id, 'status' => 'saved']);
    expect(EngineRoomRecord::where('engine_room_id', 'ER-API-001')->exists())->toBeTrue();
    expect($response->json('details'))->toHaveCount(1);
});

it('returns 422 VALIDATION_ERROR when a required field is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/engine-room-records', engineRoomApiPayload([
        'production_line_id' => $this->engineRoomStation->production_line_id,
        'engine_room_id' => '',
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('engine_room_id');
});

it('returns 422 VALIDATION_ERROR when details is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/engine-room-records', engineRoomApiPayload([
        'production_line_id' => $this->engineRoomStation->production_line_id,
        'details' => [],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when a detail row uses a time_slot outside the 24 canonical slots', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/engine-room-records', engineRoomApiPayload([
        'production_line_id' => $this->engineRoomStation->production_line_id,
        'details' => [['time_slot' => '07:15', 'steam_turbine_inlet_pressure_bar' => 1]],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when time_slot is not ascending across detail rows', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/engine-room-records', engineRoomApiPayload([
        'production_line_id' => $this->engineRoomStation->production_line_id,
        'details' => [
            ['time_slot' => '09:00', 'steam_turbine_inlet_pressure_bar' => 1],
            ['time_slot' => '07:00', 'steam_turbine_inlet_pressure_bar' => 2],
        ],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when zero rows have any reading filled', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/engine-room-records', engineRoomApiPayload([
        'production_line_id' => $this->engineRoomStation->production_line_id,
        'details' => [['time_slot' => '07:00']], // no reading columns
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 when production_line_id has no active engine-room station', function () {
    $otherProductionLine = ProductionLine::factory()->create();

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/engine-room-records', engineRoomApiPayload([
        'production_line_id' => $otherProductionLine->id,
    ]));

    $response->assertStatus(422);
});

it('sets checked_by when checked=true and requester role=supervisor', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/engine-room-records', engineRoomApiPayload([
        'production_line_id' => $this->engineRoomStation->production_line_id,
        'checked' => true,
    ]));

    $response->assertCreated();
    expect(EngineRoomRecord::where('engine_room_id', 'ER-API-001')->first()->checked_by)->toBe($this->supervisor->id);
});

it('sets acknowledged_by when acknowledged=true and requester role=mill_management', function () {
    $response = $this->actingAs($this->millManagement, 'web')->postJson('/api/engine-room-records', engineRoomApiPayload([
        'production_line_id' => $this->engineRoomStation->production_line_id,
        'acknowledged' => true,
    ]));

    $response->assertCreated();
    expect(EngineRoomRecord::where('engine_room_id', 'ER-API-001')->first()->acknowledged_by)->toBe($this->millManagement->id);
});

it('allows the Operator role to create (mobile sync)', function () {
    $response = $this->actingAs($this->operator, 'web')->postJson('/api/engine-room-records', engineRoomApiPayload([
        'production_line_id' => $this->engineRoomStation->production_line_id,
    ]));

    $response->assertCreated();
});

it('berhasil: updates an existing record and upserts its detail rows (insert new, update existing, delete removed)', function () {
    $record = EngineRoomRecord::factory()->forStation($this->engineRoomStation)->create(['engine_room_id' => 'ER-OLD']);
    $keptDetail = EngineRoomDetail::factory()->forRecord($record)->timeSlot('07:00')->filled()->create();
    $removedDetail = EngineRoomDetail::factory()->forRecord($record)->timeSlot('12:00')->filled()->create();

    $payload = engineRoomApiPayload([
        'engine_room_id' => 'ER-NEW',
        'details' => [
            ['id' => $keptDetail->id, 'time_slot' => '07:00', 'steam_turbine_inlet_pressure_bar' => 99.9],
            ['time_slot' => '15:00', 'steam_turbine_inlet_pressure_bar' => 5],
        ],
    ]);
    unset($payload['production_line_id']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/engine-room-records/{$record->id}", $payload);

    $response->assertOk();
    $response->assertJsonFragment(['engine_room_id' => 'ER-NEW']);
    expect($record->fresh()->engine_room_id)->toBe('ER-NEW');
    expect(EngineRoomDetail::where('engine_room_record_id', $record->id)->count())->toBe(2);
    expect(EngineRoomDetail::find($removedDetail->id))->toBeNull();
});

it('does not change station_id even if production_line_id is sent on update', function () {
    $record = EngineRoomRecord::factory()->forStation($this->engineRoomStation)->create();
    $existingDetail = EngineRoomDetail::factory()->forRecord($record)->timeSlot('07:00')->filled()->create();
    $otherProductionLine = ProductionLine::factory()->create();
    Station::factory()->forProductionLine($otherProductionLine)->engineRoom()->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/engine-room-records/{$record->id}", engineRoomApiPayload([
        'production_line_id' => $otherProductionLine->id,
        'details' => [
            ['id' => $existingDetail->id, 'time_slot' => $existingDetail->time_slot, 'steam_turbine_inlet_pressure_bar' => $existingDetail->steam_turbine_inlet_pressure_bar],
        ],
    ]));

    $response->assertOk();
    expect($record->fresh()->station_id)->toBe($this->engineRoomStation->id);
});

it('returns 404 RECORD_NOT_FOUND when updating a non-existent id', function () {
    $response = $this->actingAs($this->admin, 'web')->patchJson(
        '/api/engine-room-records/00000000-0000-0000-0000-000000000000',
        engineRoomApiPayload()
    );

    $response->assertStatus(404);
});

it('rejects unauthenticated requests on create and update', function () {
    $this->postJson('/api/engine-room-records', engineRoomApiPayload([
        'production_line_id' => $this->engineRoomStation->production_line_id,
    ]))->assertStatus(401);

    $record = EngineRoomRecord::factory()->forStation($this->engineRoomStation)->create();
    $this->patchJson("/api/engine-room-records/{$record->id}", engineRoomApiPayload())->assertStatus(401);
});
