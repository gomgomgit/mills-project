<?php

/**
 * FormWeighbridgeTest (Feature/Api) — screen-022--form-weighbridge-web /
 * usecase-022--form-weighbridge-web.
 *
 * Integration tests for POST /api/weighbridge-records and
 * PATCH /api/weighbridge-records/{id} (App\Http\Controllers\Api\
 * WeighbridgeRecordController::store()/update()), one per test_scenarios'
 * api_test step(s). Mirrors DetailWeighbridgeTest.php's setup/conventions.
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
    $this->operator = User::factory()->role(UserRole::Operator)->create();
});

function weighbridgeApiPayload(array $overrides = []): array
{
    return array_merge([
        'wb_card_number' => 'WB-API-001',
        'weighbridge_type' => 'receive',
        'record_datetime' => '2026-08-20T08:00:00',
        'vehicle_number' => 'B 1234 XY',
        'driver_name' => 'Budi',
        'estate_supplier' => 'Estate A',
        'gross_weight' => 15000,
    ], $overrides);
}

// Scenario: "Buat Record Weighbridge Baru - berhasil"
it('berhasil: creates a new record with status=saved and resolved station_id', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/weighbridge-records', weighbridgeApiPayload([
        'business_unit_id' => $this->businessUnit->id,
    ]));

    $response->assertCreated();
    $response->assertJsonFragment(['station_id' => $this->station->id, 'status' => 'saved']);
    expect(WeighbridgeRecord::where('wb_card_number', 'WB-API-001')->exists())->toBeTrue();
});

it('creates a record with destination when type=dispatch', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/weighbridge-records', weighbridgeApiPayload([
        'business_unit_id' => $this->businessUnit->id,
        'weighbridge_type' => 'dispatch',
        'destination' => 'PKS Sukamaju',
    ]));

    $response->assertCreated();
    $response->assertJsonFragment(['destination' => 'PKS Sukamaju']);
});

// Scenario: "Field Wajib Belum Lengkap"
it('returns 422 VALIDATION_ERROR when destination is empty and type=dispatch', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/weighbridge-records', weighbridgeApiPayload([
        'business_unit_id' => $this->businessUnit->id,
        'weighbridge_type' => 'dispatch',
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('destination');
});

it('returns 422 VALIDATION_ERROR when a required field is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/weighbridge-records', weighbridgeApiPayload([
        'business_unit_id' => $this->businessUnit->id,
        'wb_card_number' => '',
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('wb_card_number');
});

// Scenario: "Business Unit Tanpa Station Weighbridge Aktif"
it('returns 422 when business_unit_id has no active weighbridge station', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create();

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/weighbridge-records', weighbridgeApiPayload([
        'business_unit_id' => $otherBusinessUnit->id,
    ]));

    $response->assertStatus(422);
});

it('returns 403 for the Operator role on create', function () {
    $response = $this->actingAs($this->operator, 'web')->postJson('/api/weighbridge-records', weighbridgeApiPayload([
        'business_unit_id' => $this->businessUnit->id,
    ]));

    $response->assertStatus(403);
});

// Scenario: "Edit Record Weighbridge - berhasil"
it('berhasil: updates an existing record', function () {
    $record = WeighbridgeRecord::factory()->forStation($this->station)->create(['wb_card_number' => 'WB-OLD']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/weighbridge-records/{$record->id}", weighbridgeApiPayload(['wb_card_number' => 'WB-NEW']));

    $response->assertOk();
    $response->assertJsonFragment(['wb_card_number' => 'WB-NEW']);
    expect($record->fresh()->wb_card_number)->toBe('WB-NEW');
});

it('does not change station_id even if business_unit_id is sent on update', function () {
    $record = WeighbridgeRecord::factory()->forStation($this->station)->create();
    $otherBusinessUnit = BusinessUnit::factory()->create();
    Station::factory()->forBusinessUnit($otherBusinessUnit)->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/weighbridge-records/{$record->id}", weighbridgeApiPayload([
        'business_unit_id' => $otherBusinessUnit->id,
    ]));

    $response->assertOk();
    expect($record->fresh()->station_id)->toBe($this->station->id);
});

// Scenario: "Record Tidak Ditemukan (mode edit)"
it('returns 404 RECORD_NOT_FOUND when updating a non-existent id', function () {
    $response = $this->actingAs($this->admin, 'web')->patchJson(
        '/api/weighbridge-records/00000000-0000-0000-0000-000000000000',
        weighbridgeApiPayload()
    );

    $response->assertStatus(404);
});

it('rejects unauthenticated requests on create and update', function () {
    $this->postJson('/api/weighbridge-records', weighbridgeApiPayload(['business_unit_id' => $this->businessUnit->id]))->assertStatus(401);

    $record = WeighbridgeRecord::factory()->forStation($this->station)->create();
    $this->patchJson("/api/weighbridge-records/{$record->id}", weighbridgeApiPayload())->assertStatus(401);
});
