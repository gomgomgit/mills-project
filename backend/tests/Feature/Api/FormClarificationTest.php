<?php

/**
 * FormClarificationTest (Feature/Api) — screen-119--form-clarification-web /
 * usecase-114--form-clarification-web.
 *
 * Integration tests for POST /api/clarification-records and PATCH
 * /api/clarification-records/{id}, mirroring
 * tests/Feature/Api/FormBoilerRoomTest.php's structure exactly.
 */

use App\Enums\UserRole;
use App\Models\ClarificationDetail;
use App\Models\ClarificationRecord;
use App\Models\BusinessUnit;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->clarificationStation = Station::factory()->forBusinessUnit($this->businessUnit)->clarification()->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->create();
});

function clarificationApiPayload(array $overrides = []): array
{
    return array_merge([
        'clarification_id' => 'CLR-API-001',
        'date' => '2026-08-31',
        'details' => [['time_slot' => '07:00', 'clarification_tank_temp_c' => 65.5]],
    ], $overrides);
}

it('berhasil: creates a new record with status=saved, resolved station_id, and only the given detail rows', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/clarification-records', clarificationApiPayload([
        'production_line_id' => $this->clarificationStation->production_line_id,
    ]));

    $response->assertCreated();
    $response->assertJsonFragment(['station_id' => $this->clarificationStation->id, 'status' => 'saved']);
    expect(ClarificationRecord::where('clarification_id', 'CLR-API-001')->exists())->toBeTrue();
    expect($response->json('details'))->toHaveCount(1);
});

it('returns 422 VALIDATION_ERROR when a required field is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/clarification-records', clarificationApiPayload([
        'production_line_id' => $this->clarificationStation->production_line_id,
        'clarification_id' => '',
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('clarification_id');
});

it('returns 422 VALIDATION_ERROR when details is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/clarification-records', clarificationApiPayload([
        'production_line_id' => $this->clarificationStation->production_line_id,
        'details' => [],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when a detail row uses a time_slot outside the 24 canonical slots', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/clarification-records', clarificationApiPayload([
        'production_line_id' => $this->clarificationStation->production_line_id,
        'details' => [['time_slot' => '07:15', 'clarification_tank_temp_c' => 1]],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when time_slot is not ascending across detail rows', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/clarification-records', clarificationApiPayload([
        'production_line_id' => $this->clarificationStation->production_line_id,
        'details' => [
            ['time_slot' => '09:00', 'clarification_tank_temp_c' => 1],
            ['time_slot' => '07:00', 'clarification_tank_temp_c' => 2],
        ],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when zero rows have any column filled', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/clarification-records', clarificationApiPayload([
        'production_line_id' => $this->clarificationStation->production_line_id,
        'details' => [['time_slot' => '07:00']], // no columns
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 when production_line_id has no active clarification station', function () {
    $otherProductionLine = ProductionLine::factory()->create();

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/clarification-records', clarificationApiPayload([
        'production_line_id' => $otherProductionLine->id,
    ]));

    $response->assertStatus(422);
});

it('sets checked_by when checked=true and requester role=supervisor', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/clarification-records', clarificationApiPayload([
        'production_line_id' => $this->clarificationStation->production_line_id,
        'checked' => true,
    ]));

    $response->assertCreated();
    expect(ClarificationRecord::where('clarification_id', 'CLR-API-001')->first()->checked_by)->toBe($this->supervisor->id);
});

it('sets acknowledged_by when acknowledged=true and requester role=mill_management', function () {
    $response = $this->actingAs($this->millManagement, 'web')->postJson('/api/clarification-records', clarificationApiPayload([
        'production_line_id' => $this->clarificationStation->production_line_id,
        'acknowledged' => true,
    ]));

    $response->assertCreated();
    expect(ClarificationRecord::where('clarification_id', 'CLR-API-001')->first()->acknowledged_by)->toBe($this->millManagement->id);
});

it('allows the Operator role to create (mobile sync)', function () {
    $response = $this->actingAs($this->operator, 'web')->postJson('/api/clarification-records', clarificationApiPayload([
        'production_line_id' => $this->clarificationStation->production_line_id,
    ]));

    $response->assertCreated();
});

it('berhasil: updates an existing record and upserts its detail rows (insert new, update existing, delete removed)', function () {
    $record = ClarificationRecord::factory()->forStation($this->clarificationStation)->create(['clarification_id' => 'CLR-OLD']);
    $keptDetail = ClarificationDetail::factory()->forRecord($record)->timeSlot('07:00')->filled()->create();
    $removedDetail = ClarificationDetail::factory()->forRecord($record)->timeSlot('12:00')->filled()->create();

    $payload = clarificationApiPayload([
        'clarification_id' => 'CLR-NEW',
        'details' => [
            ['id' => $keptDetail->id, 'time_slot' => '07:00', 'clarification_tank_temp_c' => 99.9],
            ['time_slot' => '15:00', 'clarification_tank_temp_c' => 5],
        ],
    ]);
    unset($payload['production_line_id']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/clarification-records/{$record->id}", $payload);

    $response->assertOk();
    $response->assertJsonFragment(['clarification_id' => 'CLR-NEW']);
    expect($record->fresh()->clarification_id)->toBe('CLR-NEW');
    expect(ClarificationDetail::where('clarification_record_id', $record->id)->count())->toBe(2);
    expect(ClarificationDetail::find($removedDetail->id))->toBeNull();
});

it('does not change station_id even if production_line_id is sent on update', function () {
    $record = ClarificationRecord::factory()->forStation($this->clarificationStation)->create();
    $existingDetail = ClarificationDetail::factory()->forRecord($record)->timeSlot('07:00')->filled()->create();
    $otherProductionLine = ProductionLine::factory()->create();
    Station::factory()->forProductionLine($otherProductionLine)->clarification()->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/clarification-records/{$record->id}", clarificationApiPayload([
        'production_line_id' => $otherProductionLine->id,
        'details' => [
            ['id' => $existingDetail->id, 'time_slot' => $existingDetail->time_slot, 'clarification_tank_temp_c' => $existingDetail->clarification_tank_temp_c],
        ],
    ]));

    $response->assertOk();
    expect($record->fresh()->station_id)->toBe($this->clarificationStation->id);
});

it('returns 404 RECORD_NOT_FOUND when updating a non-existent id', function () {
    $response = $this->actingAs($this->admin, 'web')->patchJson(
        '/api/clarification-records/00000000-0000-0000-0000-000000000000',
        clarificationApiPayload()
    );

    $response->assertStatus(404);
});

it('rejects unauthenticated requests on create and update', function () {
    $this->postJson('/api/clarification-records', clarificationApiPayload([
        'production_line_id' => $this->clarificationStation->production_line_id,
    ]))->assertStatus(401);

    $record = ClarificationRecord::factory()->forStation($this->clarificationStation)->create();
    $this->patchJson("/api/clarification-records/{$record->id}", clarificationApiPayload())->assertStatus(401);
});
