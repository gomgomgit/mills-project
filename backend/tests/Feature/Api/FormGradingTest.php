<?php

/**
 * FormGradingTest (Feature/Api) — screen-023--form-grading-web /
 * usecase-023--form-grading-web.
 *
 * Integration tests for POST /api/grading-records and
 * PATCH /api/grading-records/{id} (App\Http\Controllers\Api\
 * GradingRecordController::store()/update()), one per test_scenarios'
 * api_test step(s). Mirrors FormWeighbridgeTest.php's setup/conventions
 * (screen-022), with GradingRecord's extra details[] array + weighbridge
 * reference layered on top.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\GradingParameter;
use App\Models\GradingRecord;
use App\Models\Station;
use App\Models\User;
use App\Models\WeighbridgeRecord;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->weighbridgeStation = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->gradingStation = Station::factory()->forBusinessUnit($this->businessUnit)->grading()->create();
    $this->weighbridgeRecord = WeighbridgeRecord::factory()->forStation($this->weighbridgeStation)->create();
    $this->gradingParameter = GradingParameter::factory()->create(['uom' => \App\Enums\Uom::Kg]);
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->create();
});

function gradingApiPayload(array $overrides = []): array
{
    return array_merge([
        'grading_number' => 'GR-API-001',
        'date' => '2026-08-20',
        'license_plate_no' => 'B 1234 XY',
        'estate_supplier' => 'Estate A',
        'netto' => 1000,
        'quantity' => 120,
    ], $overrides);
}

// Scenario: "Buat Record Grading Baru — berhasil"
it('berhasil: creates a new record with status=saved, resolved station_id, and inserted details', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/grading-records', gradingApiPayload([
        'business_unit_id' => $this->businessUnit->id,
        'weighbridge_record_id' => $this->weighbridgeRecord->id,
        'details' => [['grading_parameter_id' => $this->gradingParameter->id, 'quantity' => 250]],
    ]));

    $response->assertCreated();
    $response->assertJsonFragment(['station_id' => $this->gradingStation->id, 'status' => 'saved']);
    expect(GradingRecord::where('grading_number', 'GR-API-001')->exists())->toBeTrue();
});

// Scenario: "Field Wajib Belum Lengkap"
it('returns 422 VALIDATION_ERROR when a required field is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/grading-records', gradingApiPayload([
        'business_unit_id' => $this->businessUnit->id,
        'weighbridge_record_id' => $this->weighbridgeRecord->id,
        'grading_number' => '',
        'details' => [['grading_parameter_id' => $this->gradingParameter->id, 'quantity' => 5]],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('grading_number');
});

// Scenario: "Belum Ada Baris Grading Detail Valid"
it('returns 422 VALIDATION_ERROR when details array is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/grading-records', gradingApiPayload([
        'business_unit_id' => $this->businessUnit->id,
        'weighbridge_record_id' => $this->weighbridgeRecord->id,
        'details' => [],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

// Scenario: "Quality Parameter Tidak Bisa Duplikat Antar Baris"
it('returns 422 VALIDATION_ERROR when two detail rows share the same grading_parameter_id', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/grading-records', gradingApiPayload([
        'business_unit_id' => $this->businessUnit->id,
        'weighbridge_record_id' => $this->weighbridgeRecord->id,
        'details' => [
            ['grading_parameter_id' => $this->gradingParameter->id, 'quantity' => 5],
            ['grading_parameter_id' => $this->gradingParameter->id, 'quantity' => 3],
        ],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

// Scenario: "Business Unit Tanpa Station Grading Aktif"
it('returns 422 when business_unit_id has no active grading station', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create();

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/grading-records', gradingApiPayload([
        'business_unit_id' => $otherBusinessUnit->id,
        'weighbridge_record_id' => $this->weighbridgeRecord->id,
        'details' => [['grading_parameter_id' => $this->gradingParameter->id, 'quantity' => 5]],
    ]));

    $response->assertStatus(422);
});

it('returns 403 for the Operator role on create', function () {
    $response = $this->actingAs($this->operator, 'web')->postJson('/api/grading-records', gradingApiPayload([
        'business_unit_id' => $this->businessUnit->id,
        'weighbridge_record_id' => $this->weighbridgeRecord->id,
        'details' => [['grading_parameter_id' => $this->gradingParameter->id, 'quantity' => 5]],
    ]));

    $response->assertStatus(403);
});

// Scenario: "Edit Record Grading — berhasil"
it('berhasil: updates an existing record and its details', function () {
    $record = GradingRecord::factory()->forStation($this->gradingStation)->create(['grading_number' => 'GR-OLD']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/grading-records/{$record->id}", gradingApiPayload([
        'weighbridge_record_id' => $this->weighbridgeRecord->id,
        'grading_number' => 'GR-NEW',
        'details' => [['grading_parameter_id' => $this->gradingParameter->id, 'quantity' => 5]],
    ]));

    $response->assertOk();
    $response->assertJsonFragment(['grading_number' => 'GR-NEW']);
    expect($record->fresh()->grading_number)->toBe('GR-NEW');
});

it('does not change station_id even if business_unit_id is sent on update', function () {
    $record = GradingRecord::factory()->forStation($this->gradingStation)->create();
    $otherBusinessUnit = BusinessUnit::factory()->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/grading-records/{$record->id}", gradingApiPayload([
        'business_unit_id' => $otherBusinessUnit->id,
        'weighbridge_record_id' => $this->weighbridgeRecord->id,
        'details' => [['grading_parameter_id' => $this->gradingParameter->id, 'quantity' => 5]],
    ]));

    $response->assertOk();
    expect($record->fresh()->station_id)->toBe($this->gradingStation->id);
});

// Scenario: "Record Tidak Ditemukan (mode edit)"
it('returns 404 RECORD_NOT_FOUND when updating a non-existent id', function () {
    $response = $this->actingAs($this->admin, 'web')->patchJson(
        '/api/grading-records/00000000-0000-0000-0000-000000000000',
        gradingApiPayload([
            'weighbridge_record_id' => $this->weighbridgeRecord->id,
            'details' => [['grading_parameter_id' => $this->gradingParameter->id, 'quantity' => 5]],
        ])
    );

    $response->assertStatus(404);
});

it('rejects unauthenticated requests on create and update', function () {
    $this->postJson('/api/grading-records', gradingApiPayload([
        'business_unit_id' => $this->businessUnit->id,
        'weighbridge_record_id' => $this->weighbridgeRecord->id,
        'details' => [['grading_parameter_id' => $this->gradingParameter->id, 'quantity' => 5]],
    ]))->assertStatus(401);

    $record = GradingRecord::factory()->forStation($this->gradingStation)->create();
    $this->patchJson("/api/grading-records/{$record->id}", gradingApiPayload())->assertStatus(401);
});
