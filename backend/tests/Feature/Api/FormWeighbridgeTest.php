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
use Laravel\Sanctum\Sanctum;

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
        'production_line_id' => $this->station->production_line_id,
    ]));

    $response->assertCreated();
    $response->assertJsonFragment(['station_id' => $this->station->id, 'status' => 'saved']);
    expect(WeighbridgeRecord::where('wb_card_number', 'WB-API-001')->exists())->toBeTrue();
});

it('creates a record with destination when type=dispatch', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/weighbridge-records', weighbridgeApiPayload([
        'production_line_id' => $this->station->production_line_id,
        'weighbridge_type' => 'dispatch',
        'destination' => 'PKS Sukamaju',
    ]));

    $response->assertCreated();
    $response->assertJsonFragment(['destination' => 'PKS Sukamaju']);
});

// Scenario: "Field Wajib Belum Lengkap"
it('returns 422 VALIDATION_ERROR when destination is empty and type=dispatch', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/weighbridge-records', weighbridgeApiPayload([
        'production_line_id' => $this->station->production_line_id,
        'weighbridge_type' => 'dispatch',
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('destination');
});

it('returns 422 VALIDATION_ERROR when a required field is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/weighbridge-records', weighbridgeApiPayload([
        'production_line_id' => $this->station->production_line_id,
        'wb_card_number' => '',
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('wb_card_number');
});

// Scenario: "Business Unit Tanpa Station Weighbridge Aktif"
it('returns 422 when production_line_id has no active weighbridge station', function () {
    $otherProductionLine = \App\Models\ProductionLine::factory()->create();

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/weighbridge-records', weighbridgeApiPayload([
        'production_line_id' => $otherProductionLine->id,
    ]));

    $response->assertStatus(422);
});

// TEMPORARY (2026-08-20, mobile syncService.ts): Operator is now allowed
// on this route (was 403) — the route's role list gained 'operator' and
// its guard became 'auth:web,sanctum' so the mobile app's manual
// "Sinkronisasi" button (Station List) can push locally-entered
// Weighbridge records here via a Sanctum token. See routes/api.php's
// screen-022 route comment for the full rationale.
it('allows the Operator role to create (mobile sync)', function () {
    $response = $this->actingAs($this->operator, 'web')->postJson('/api/weighbridge-records', weighbridgeApiPayload([
        'production_line_id' => $this->station->production_line_id,
    ]));

    $response->assertCreated();
});

// TEMPORARY (2026-08-20, mobile syncService.ts): reachable via the
// Sanctum guard too, not just 'web' — this is the real auth path mobile
// actually uses (apiClient.ts attaches a Bearer token, never a session
// cookie). station_id is never sent; the server resolves the Station from
// business_unit_id + weighbridge_type, so syncService.ts never needs to
// reconcile mobile's synthetic local station ids with real ones.
it('allows a Sanctum-authenticated Operator (mobile) to create, without sending station_id', function () {
    Sanctum::actingAs($this->operator, ['*']);

    $response = $this->postJson('/api/weighbridge-records', weighbridgeApiPayload([
        'production_line_id' => $this->station->production_line_id,
    ]));

    $response->assertCreated();
    expect(WeighbridgeRecord::first()->station_id)->toBe($this->station->id);
});

// Scenario: "Edit Record Weighbridge - berhasil"
it('berhasil: updates an existing record', function () {
    $record = WeighbridgeRecord::factory()->forStation($this->station)->create(['wb_card_number' => 'WB-OLD']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/weighbridge-records/{$record->id}", weighbridgeApiPayload(['wb_card_number' => 'WB-NEW']));

    $response->assertOk();
    $response->assertJsonFragment(['wb_card_number' => 'WB-NEW']);
    expect($record->fresh()->wb_card_number)->toBe('WB-NEW');
});

it('does not change station_id even if production_line_id is sent on update', function () {
    $record = WeighbridgeRecord::factory()->forStation($this->station)->create();
    $otherProductionLine = \App\Models\ProductionLine::factory()->create();
    Station::factory()->forProductionLine($otherProductionLine)->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/weighbridge-records/{$record->id}", weighbridgeApiPayload([
        'production_line_id' => $otherProductionLine->id,
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
    $this->postJson('/api/weighbridge-records', weighbridgeApiPayload(['production_line_id' => $this->station->production_line_id]))->assertStatus(401);

    $record = WeighbridgeRecord::factory()->forStation($this->station)->create();
    $this->patchJson("/api/weighbridge-records/{$record->id}", weighbridgeApiPayload())->assertStatus(401);
});
