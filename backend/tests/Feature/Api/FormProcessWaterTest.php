<?php

/**
 * FormProcessWaterTest (Feature/Api) — screen-112--form-process-water-web /
 * usecase-072--form-process-water-web.
 *
 * Integration tests for POST /api/process-water-records and PATCH
 * /api/process-water-records/{id}, mirroring
 * tests/Feature/Api/FormThreshingTest.php's structure exactly.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\ProcessWaterDetail;
use App\Models\ProcessWaterRecord;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->processWaterStation = Station::factory()->forBusinessUnit($this->businessUnit)->processWater()->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->create();
});

function processWaterApiPayload(array $overrides = []): array
{
    return array_merge([
        'process_water_id' => 'PW-API-001',
        'date' => '2026-08-31',
        'details' => [['time_slot' => '07:00', 'raw_water_flow_m3h' => 45.5]],
    ], $overrides);
}

it('berhasil: creates a new record with status=saved, resolved station_id, and only the given detail rows', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/process-water-records', processWaterApiPayload([
        'production_line_id' => $this->processWaterStation->production_line_id,
    ]));

    $response->assertCreated();
    $response->assertJsonFragment(['station_id' => $this->processWaterStation->id, 'status' => 'saved']);
    expect(ProcessWaterRecord::where('process_water_id', 'PW-API-001')->exists())->toBeTrue();
    expect($response->json('details'))->toHaveCount(1);
});

it('returns 422 VALIDATION_ERROR when a required field is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/process-water-records', processWaterApiPayload([
        'production_line_id' => $this->processWaterStation->production_line_id,
        'process_water_id' => '',
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('process_water_id');
});

it('returns 422 VALIDATION_ERROR when details is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/process-water-records', processWaterApiPayload([
        'production_line_id' => $this->processWaterStation->production_line_id,
        'details' => [],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when a detail row uses a time_slot outside the 24 canonical slots', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/process-water-records', processWaterApiPayload([
        'production_line_id' => $this->processWaterStation->production_line_id,
        'details' => [['time_slot' => '07:15', 'raw_water_flow_m3h' => 1]],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when time_slot is not ascending across detail rows', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/process-water-records', processWaterApiPayload([
        'production_line_id' => $this->processWaterStation->production_line_id,
        'details' => [
            ['time_slot' => '09:00', 'raw_water_flow_m3h' => 1],
            ['time_slot' => '07:00', 'raw_water_flow_m3h' => 2],
        ],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when zero rows have any reading filled', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/process-water-records', processWaterApiPayload([
        'production_line_id' => $this->processWaterStation->production_line_id,
        'details' => [['time_slot' => '07:00']], // no reading columns
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 when production_line_id has no active process-water station', function () {
    $otherProductionLine = ProductionLine::factory()->create();

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/process-water-records', processWaterApiPayload([
        'production_line_id' => $otherProductionLine->id,
    ]));

    $response->assertStatus(422);
});

it('sets checked_by when checked=true and requester role=supervisor', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/process-water-records', processWaterApiPayload([
        'production_line_id' => $this->processWaterStation->production_line_id,
        'checked' => true,
    ]));

    $response->assertCreated();
    expect(ProcessWaterRecord::where('process_water_id', 'PW-API-001')->first()->checked_by)->toBe($this->supervisor->id);
});

it('sets acknowledged_by when acknowledged=true and requester role=mill_management', function () {
    $response = $this->actingAs($this->millManagement, 'web')->postJson('/api/process-water-records', processWaterApiPayload([
        'production_line_id' => $this->processWaterStation->production_line_id,
        'acknowledged' => true,
    ]));

    $response->assertCreated();
    expect(ProcessWaterRecord::where('process_water_id', 'PW-API-001')->first()->acknowledged_by)->toBe($this->millManagement->id);
});

it('allows the Operator role to create (mobile sync)', function () {
    $response = $this->actingAs($this->operator, 'web')->postJson('/api/process-water-records', processWaterApiPayload([
        'production_line_id' => $this->processWaterStation->production_line_id,
    ]));

    $response->assertCreated();
});

it('berhasil: updates an existing record and upserts its detail rows (insert new, update existing, delete removed)', function () {
    $record = ProcessWaterRecord::factory()->forStation($this->processWaterStation)->create(['process_water_id' => 'PW-OLD']);
    $keptDetail = ProcessWaterDetail::factory()->forRecord($record)->timeSlot('07:00')->filled()->create();
    $removedDetail = ProcessWaterDetail::factory()->forRecord($record)->timeSlot('12:00')->filled()->create();

    $payload = processWaterApiPayload([
        'process_water_id' => 'PW-NEW',
        'details' => [
            ['id' => $keptDetail->id, 'time_slot' => '07:00', 'raw_water_flow_m3h' => 99.9],
            ['time_slot' => '15:00', 'raw_water_flow_m3h' => 5],
        ],
    ]);
    unset($payload['production_line_id']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/process-water-records/{$record->id}", $payload);

    $response->assertOk();
    $response->assertJsonFragment(['process_water_id' => 'PW-NEW']);
    expect($record->fresh()->process_water_id)->toBe('PW-NEW');
    expect(ProcessWaterDetail::where('process_water_record_id', $record->id)->count())->toBe(2);
    expect(ProcessWaterDetail::find($removedDetail->id))->toBeNull();
});

it('does not change station_id even if production_line_id is sent on update', function () {
    $record = ProcessWaterRecord::factory()->forStation($this->processWaterStation)->create();
    $existingDetail = ProcessWaterDetail::factory()->forRecord($record)->timeSlot('07:00')->filled()->create();
    $otherProductionLine = ProductionLine::factory()->create();
    Station::factory()->forProductionLine($otherProductionLine)->processWater()->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/process-water-records/{$record->id}", processWaterApiPayload([
        'production_line_id' => $otherProductionLine->id,
        'details' => [
            ['id' => $existingDetail->id, 'time_slot' => $existingDetail->time_slot, 'raw_water_flow_m3h' => $existingDetail->raw_water_flow_m3h],
        ],
    ]));

    $response->assertOk();
    expect($record->fresh()->station_id)->toBe($this->processWaterStation->id);
});

it('returns 404 RECORD_NOT_FOUND when updating a non-existent id', function () {
    $response = $this->actingAs($this->admin, 'web')->patchJson(
        '/api/process-water-records/00000000-0000-0000-0000-000000000000',
        processWaterApiPayload()
    );

    $response->assertStatus(404);
});

it('rejects unauthenticated requests on create and update', function () {
    $this->postJson('/api/process-water-records', processWaterApiPayload([
        'production_line_id' => $this->processWaterStation->production_line_id,
    ]))->assertStatus(401);

    $record = ProcessWaterRecord::factory()->forStation($this->processWaterStation)->create();
    $this->patchJson("/api/process-water-records/{$record->id}", processWaterApiPayload())->assertStatus(401);
});
