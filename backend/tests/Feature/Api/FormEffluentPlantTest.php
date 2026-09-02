<?php

/**
 * FormEffluentPlantTest (Feature/Api) — screen-115--form-effluent-plant-web /
 * usecase-090--form-effluent-plant-web.
 *
 * Integration tests for POST /api/effluent-plant-records and PATCH
 * /api/effluent-plant-records/{id}, mirroring
 * tests/Feature/Api/FormThreshingTest.php's structure exactly.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\EffluentPlantDetail;
use App\Models\EffluentPlantRecord;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->effluentPlantStation = Station::factory()->forBusinessUnit($this->businessUnit)->effluentPlant()->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->create();
});

function effluentPlantApiPayload(array $overrides = []): array
{
    return array_merge([
        'effluent_plant_id' => 'EP-API-001',
        'date' => '2026-08-31',
        'details' => [['time_slot' => '07:00', 'anaerobic_pond_1_ph' => 45.5]],
    ], $overrides);
}

it('berhasil: creates a new record with status=saved, resolved station_id, and only the given detail rows', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/effluent-plant-records', effluentPlantApiPayload([
        'production_line_id' => $this->effluentPlantStation->production_line_id,
    ]));

    $response->assertCreated();
    $response->assertJsonFragment(['station_id' => $this->effluentPlantStation->id, 'status' => 'saved']);
    expect(EffluentPlantRecord::where('effluent_plant_id', 'EP-API-001')->exists())->toBeTrue();
    expect($response->json('details'))->toHaveCount(1);
});

it('returns 422 VALIDATION_ERROR when a required field is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/effluent-plant-records', effluentPlantApiPayload([
        'production_line_id' => $this->effluentPlantStation->production_line_id,
        'effluent_plant_id' => '',
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('effluent_plant_id');
});

it('returns 422 VALIDATION_ERROR when details is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/effluent-plant-records', effluentPlantApiPayload([
        'production_line_id' => $this->effluentPlantStation->production_line_id,
        'details' => [],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when a detail row uses a time_slot outside the 24 canonical slots', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/effluent-plant-records', effluentPlantApiPayload([
        'production_line_id' => $this->effluentPlantStation->production_line_id,
        'details' => [['time_slot' => '07:15', 'anaerobic_pond_1_ph' => 1]],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when time_slot is not ascending across detail rows', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/effluent-plant-records', effluentPlantApiPayload([
        'production_line_id' => $this->effluentPlantStation->production_line_id,
        'details' => [
            ['time_slot' => '09:00', 'anaerobic_pond_1_ph' => 1],
            ['time_slot' => '07:00', 'anaerobic_pond_1_ph' => 2],
        ],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when zero rows have any reading filled', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/effluent-plant-records', effluentPlantApiPayload([
        'production_line_id' => $this->effluentPlantStation->production_line_id,
        'details' => [['time_slot' => '07:00']], // no reading columns
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 when production_line_id has no active effluent-plant station', function () {
    $otherProductionLine = ProductionLine::factory()->create();

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/effluent-plant-records', effluentPlantApiPayload([
        'production_line_id' => $otherProductionLine->id,
    ]));

    $response->assertStatus(422);
});

it('sets checked_by when checked=true and requester role=supervisor', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/effluent-plant-records', effluentPlantApiPayload([
        'production_line_id' => $this->effluentPlantStation->production_line_id,
        'checked' => true,
    ]));

    $response->assertCreated();
    expect(EffluentPlantRecord::where('effluent_plant_id', 'EP-API-001')->first()->checked_by)->toBe($this->supervisor->id);
});

it('sets acknowledged_by when acknowledged=true and requester role=mill_management', function () {
    $response = $this->actingAs($this->millManagement, 'web')->postJson('/api/effluent-plant-records', effluentPlantApiPayload([
        'production_line_id' => $this->effluentPlantStation->production_line_id,
        'acknowledged' => true,
    ]));

    $response->assertCreated();
    expect(EffluentPlantRecord::where('effluent_plant_id', 'EP-API-001')->first()->acknowledged_by)->toBe($this->millManagement->id);
});

it('allows the Operator role to create (mobile sync)', function () {
    $response = $this->actingAs($this->operator, 'web')->postJson('/api/effluent-plant-records', effluentPlantApiPayload([
        'production_line_id' => $this->effluentPlantStation->production_line_id,
    ]));

    $response->assertCreated();
});

it('berhasil: updates an existing record and upserts its detail rows (insert new, update existing, delete removed)', function () {
    $record = EffluentPlantRecord::factory()->forStation($this->effluentPlantStation)->create(['effluent_plant_id' => 'EP-OLD']);
    $keptDetail = EffluentPlantDetail::factory()->forRecord($record)->timeSlot('07:00')->filled()->create();
    $removedDetail = EffluentPlantDetail::factory()->forRecord($record)->timeSlot('12:00')->filled()->create();

    $payload = effluentPlantApiPayload([
        'effluent_plant_id' => 'EP-NEW',
        'details' => [
            ['id' => $keptDetail->id, 'time_slot' => '07:00', 'anaerobic_pond_1_ph' => 99.9],
            ['time_slot' => '15:00', 'anaerobic_pond_1_ph' => 5],
        ],
    ]);
    unset($payload['production_line_id']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/effluent-plant-records/{$record->id}", $payload);

    $response->assertOk();
    $response->assertJsonFragment(['effluent_plant_id' => 'EP-NEW']);
    expect($record->fresh()->effluent_plant_id)->toBe('EP-NEW');
    expect(EffluentPlantDetail::where('effluent_plant_record_id', $record->id)->count())->toBe(2);
    expect(EffluentPlantDetail::find($removedDetail->id))->toBeNull();
});

it('does not change station_id even if production_line_id is sent on update', function () {
    $record = EffluentPlantRecord::factory()->forStation($this->effluentPlantStation)->create();
    $existingDetail = EffluentPlantDetail::factory()->forRecord($record)->timeSlot('07:00')->filled()->create();
    $otherProductionLine = ProductionLine::factory()->create();
    Station::factory()->forProductionLine($otherProductionLine)->effluentPlant()->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/effluent-plant-records/{$record->id}", effluentPlantApiPayload([
        'production_line_id' => $otherProductionLine->id,
        'details' => [
            ['id' => $existingDetail->id, 'time_slot' => $existingDetail->time_slot, 'anaerobic_pond_1_ph' => $existingDetail->anaerobic_pond_1_ph],
        ],
    ]));

    $response->assertOk();
    expect($record->fresh()->station_id)->toBe($this->effluentPlantStation->id);
});

it('returns 404 RECORD_NOT_FOUND when updating a non-existent id', function () {
    $response = $this->actingAs($this->admin, 'web')->patchJson(
        '/api/effluent-plant-records/00000000-0000-0000-0000-000000000000',
        effluentPlantApiPayload()
    );

    $response->assertStatus(404);
});

it('rejects unauthenticated requests on create and update', function () {
    $this->postJson('/api/effluent-plant-records', effluentPlantApiPayload([
        'production_line_id' => $this->effluentPlantStation->production_line_id,
    ]))->assertStatus(401);

    $record = EffluentPlantRecord::factory()->forStation($this->effluentPlantStation)->create();
    $this->patchJson("/api/effluent-plant-records/{$record->id}", effluentPlantApiPayload())->assertStatus(401);
});
