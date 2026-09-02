<?php

/**
 * FormProcessQualityControlTest (Feature/Api) — screen-120--form-process-quality-control-web /
 * usecase-120--form-process-quality-control-web.
 *
 * Integration tests for POST /api/process-quality-control-records and PATCH
 * /api/process-quality-control-records/{id}, mirroring
 * tests/Feature/Api/FormClarificationTest.php's structure exactly.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\ProcessQualityControlDetail;
use App\Models\ProcessQualityControlRecord;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->pqcStation = Station::factory()->forBusinessUnit($this->businessUnit)->processQualityControl()->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->create();
});

function processQualityControlApiPayload(array $overrides = []): array
{
    return array_merge([
        'process_qc_id' => 'PQC-API-001',
        'date' => '2026-08-31',
        'details' => [['time_slot' => '07:00', 'fruit_press_oil_loss_in_sludge_percent' => 0.85]],
    ], $overrides);
}

it('berhasil: creates a new record with status=saved, resolved station_id, and only the given detail rows', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/process-quality-control-records', processQualityControlApiPayload([
        'production_line_id' => $this->pqcStation->production_line_id,
    ]));

    $response->assertCreated();
    $response->assertJsonFragment(['station_id' => $this->pqcStation->id, 'status' => 'saved']);
    expect(ProcessQualityControlRecord::where('process_qc_id', 'PQC-API-001')->exists())->toBeTrue();
    expect($response->json('details'))->toHaveCount(1);
});

it('returns 422 VALIDATION_ERROR when a required field is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/process-quality-control-records', processQualityControlApiPayload([
        'production_line_id' => $this->pqcStation->production_line_id,
        'process_qc_id' => '',
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('process_qc_id');
});

it('returns 422 VALIDATION_ERROR when details is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/process-quality-control-records', processQualityControlApiPayload([
        'production_line_id' => $this->pqcStation->production_line_id,
        'details' => [],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when a detail row uses a time_slot outside the 24 canonical slots', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/process-quality-control-records', processQualityControlApiPayload([
        'production_line_id' => $this->pqcStation->production_line_id,
        'details' => [['time_slot' => '07:15', 'fruit_press_oil_loss_in_sludge_percent' => 1]],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when time_slot is not ascending across detail rows', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/process-quality-control-records', processQualityControlApiPayload([
        'production_line_id' => $this->pqcStation->production_line_id,
        'details' => [
            ['time_slot' => '09:00', 'fruit_press_oil_loss_in_sludge_percent' => 1],
            ['time_slot' => '07:00', 'fruit_press_oil_loss_in_sludge_percent' => 2],
        ],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when zero rows have any of the 14 reading/keterangan columns filled', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/process-quality-control-records', processQualityControlApiPayload([
        'production_line_id' => $this->pqcStation->production_line_id,
        'details' => [['time_slot' => '07:00', 'shift' => 'Shift 1', 'qc_inspector_id' => 'INS-01']], // identity columns only
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 when production_line_id has no active process quality control station', function () {
    $otherProductionLine = ProductionLine::factory()->create();

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/process-quality-control-records', processQualityControlApiPayload([
        'production_line_id' => $otherProductionLine->id,
    ]));

    $response->assertStatus(422);
});

it('sets checked_by when checked=true and requester role=supervisor', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/process-quality-control-records', processQualityControlApiPayload([
        'production_line_id' => $this->pqcStation->production_line_id,
        'checked' => true,
    ]));

    $response->assertCreated();
    expect(ProcessQualityControlRecord::where('process_qc_id', 'PQC-API-001')->first()->checked_by)->toBe($this->supervisor->id);
});

it('sets acknowledged_by when acknowledged=true and requester role=mill_management', function () {
    $response = $this->actingAs($this->millManagement, 'web')->postJson('/api/process-quality-control-records', processQualityControlApiPayload([
        'production_line_id' => $this->pqcStation->production_line_id,
        'acknowledged' => true,
    ]));

    $response->assertCreated();
    expect(ProcessQualityControlRecord::where('process_qc_id', 'PQC-API-001')->first()->acknowledged_by)->toBe($this->millManagement->id);
});

it('allows the Operator role to create (mobile sync)', function () {
    $response = $this->actingAs($this->operator, 'web')->postJson('/api/process-quality-control-records', processQualityControlApiPayload([
        'production_line_id' => $this->pqcStation->production_line_id,
    ]));

    $response->assertCreated();
});

it('berhasil: updates an existing record and upserts its detail rows (insert new, update existing, delete removed)', function () {
    $record = ProcessQualityControlRecord::factory()->forStation($this->pqcStation)->create(['process_qc_id' => 'PQC-OLD']);
    $keptDetail = ProcessQualityControlDetail::factory()->forRecord($record)->timeSlot('07:00')->filled()->create();
    $removedDetail = ProcessQualityControlDetail::factory()->forRecord($record)->timeSlot('12:00')->filled()->create();

    $payload = processQualityControlApiPayload([
        'process_qc_id' => 'PQC-NEW',
        'details' => [
            ['id' => $keptDetail->id, 'time_slot' => '07:00', 'fruit_press_oil_loss_in_sludge_percent' => 0.99],
            ['time_slot' => '15:00', 'fruit_press_oil_loss_in_sludge_percent' => 0.5],
        ],
    ]);
    unset($payload['production_line_id']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/process-quality-control-records/{$record->id}", $payload);

    $response->assertOk();
    $response->assertJsonFragment(['process_qc_id' => 'PQC-NEW']);
    expect($record->fresh()->process_qc_id)->toBe('PQC-NEW');
    expect(ProcessQualityControlDetail::where('process_quality_control_record_id', $record->id)->count())->toBe(2);
    expect(ProcessQualityControlDetail::find($removedDetail->id))->toBeNull();
});

it('does not change station_id even if production_line_id is sent on update', function () {
    $record = ProcessQualityControlRecord::factory()->forStation($this->pqcStation)->create();
    $existingDetail = ProcessQualityControlDetail::factory()->forRecord($record)->timeSlot('07:00')->filled()->create();
    $otherProductionLine = ProductionLine::factory()->create();
    Station::factory()->forProductionLine($otherProductionLine)->processQualityControl()->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/process-quality-control-records/{$record->id}", processQualityControlApiPayload([
        'production_line_id' => $otherProductionLine->id,
        'details' => [
            ['id' => $existingDetail->id, 'time_slot' => $existingDetail->time_slot, 'fruit_press_oil_loss_in_sludge_percent' => $existingDetail->fruit_press_oil_loss_in_sludge_percent],
        ],
    ]));

    $response->assertOk();
    expect($record->fresh()->station_id)->toBe($this->pqcStation->id);
});

it('returns 404 RECORD_NOT_FOUND when updating a non-existent id', function () {
    $response = $this->actingAs($this->admin, 'web')->patchJson(
        '/api/process-quality-control-records/00000000-0000-0000-0000-000000000000',
        processQualityControlApiPayload()
    );

    $response->assertStatus(404);
});

it('rejects unauthenticated requests on create and update', function () {
    $this->postJson('/api/process-quality-control-records', processQualityControlApiPayload([
        'production_line_id' => $this->pqcStation->production_line_id,
    ]))->assertStatus(401);

    $record = ProcessQualityControlRecord::factory()->forStation($this->pqcStation)->create();
    $this->patchJson("/api/process-quality-control-records/{$record->id}", processQualityControlApiPayload())->assertStatus(401);
});
