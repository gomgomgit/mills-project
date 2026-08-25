<?php

/**
 * FormKernelPlantTest (Feature/Api) — screen-060--form-kernel-plant-web /
 * usecase-060--form-kernel-plant-web.
 *
 * Integration tests for POST /api/kernel-plant-records and PATCH
 * /api/kernel-plant-records/{id}, mirroring
 * tests/Feature/Api/FormDepricarpingTest.php's structure. REVISED
 * 2026-08-24 (entity-catalog v12): `details` is now a dynamic array of
 * 1..24 rows (unique + strictly ascending canonical time_slot order)
 * rather than always exactly 24 entries.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\KernelPlantDetail;
use App\Models\KernelPlantRecord;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->kernelPlantStation = Station::factory()->forBusinessUnit($this->businessUnit)->kernelPlant()->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->create();
});

function kernelPlantApiPayload(array $overrides = []): array
{
    return array_merge([
        'kernel_plant_id' => 'KP-API-001',
        'date' => '2026-08-24',
        'details' => [['time_slot' => '07:00', 'ripple_mill_1_amps' => 22]],
    ], $overrides);
}

it('berhasil: creates a new record with status=saved, resolved station_id, and only the given detail rows', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/kernel-plant-records', kernelPlantApiPayload([
        'production_line_id' => $this->kernelPlantStation->production_line_id,
    ]));

    $response->assertCreated();
    $response->assertJsonFragment(['station_id' => $this->kernelPlantStation->id, 'status' => 'saved']);
    expect(KernelPlantRecord::where('kernel_plant_id', 'KP-API-001')->exists())->toBeTrue();
    expect($response->json('details'))->toHaveCount(1);
});

it('returns 422 VALIDATION_ERROR when a required field is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/kernel-plant-records', kernelPlantApiPayload([
        'production_line_id' => $this->kernelPlantStation->production_line_id,
        'kernel_plant_id' => '',
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('kernel_plant_id');
});

it('returns 422 VALIDATION_ERROR when details is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/kernel-plant-records', kernelPlantApiPayload([
        'production_line_id' => $this->kernelPlantStation->production_line_id,
        'details' => [],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when a detail row uses a time_slot outside the 24 canonical slots', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/kernel-plant-records', kernelPlantApiPayload([
        'production_line_id' => $this->kernelPlantStation->production_line_id,
        'details' => [['time_slot' => '07:15', 'ripple_mill_1_amps' => 1]],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when time_slot is not ascending across detail rows', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/kernel-plant-records', kernelPlantApiPayload([
        'production_line_id' => $this->kernelPlantStation->production_line_id,
        'details' => [
            ['time_slot' => '09:00', 'ripple_mill_1_amps' => 1],
            ['time_slot' => '07:00', 'ripple_mill_1_amps' => 2],
        ],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when zero rows have any reading filled', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/kernel-plant-records', kernelPlantApiPayload([
        'production_line_id' => $this->kernelPlantStation->production_line_id,
        'details' => [['time_slot' => '07:00']], // no reading columns
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('treats a row with only downtime_minutes filled as satisfying the minimum-one-row rule', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/kernel-plant-records', kernelPlantApiPayload([
        'production_line_id' => $this->kernelPlantStation->production_line_id,
        'details' => [['time_slot' => '07:00', 'downtime_minutes' => 15]],
    ]));

    $response->assertCreated();
});

it('treats a row with only findings filled as satisfying the minimum-one-row rule', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/kernel-plant-records', kernelPlantApiPayload([
        'production_line_id' => $this->kernelPlantStation->production_line_id,
        'details' => [['time_slot' => '07:00', 'findings' => 'Ripple mill vibration']],
    ]));

    $response->assertCreated();
});

it('returns 422 when production_line_id has no active kernel-plant station', function () {
    $otherProductionLine = ProductionLine::factory()->create();

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/kernel-plant-records', kernelPlantApiPayload([
        'production_line_id' => $otherProductionLine->id,
    ]));

    $response->assertStatus(422);
});

it('sets checked_by when checked=true and requester role=supervisor', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/kernel-plant-records', kernelPlantApiPayload([
        'production_line_id' => $this->kernelPlantStation->production_line_id,
        'checked' => true,
    ]));

    $response->assertCreated();
    expect(KernelPlantRecord::where('kernel_plant_id', 'KP-API-001')->first()->checked_by)->toBe($this->supervisor->id);
});

it('sets acknowledged_by when acknowledged=true and requester role=mill_management', function () {
    $response = $this->actingAs($this->millManagement, 'web')->postJson('/api/kernel-plant-records', kernelPlantApiPayload([
        'production_line_id' => $this->kernelPlantStation->production_line_id,
        'acknowledged' => true,
    ]));

    $response->assertCreated();
    expect(KernelPlantRecord::where('kernel_plant_id', 'KP-API-001')->first()->acknowledged_by)->toBe($this->millManagement->id);
});

it('allows the Operator role to create (mobile sync)', function () {
    $response = $this->actingAs($this->operator, 'web')->postJson('/api/kernel-plant-records', kernelPlantApiPayload([
        'production_line_id' => $this->kernelPlantStation->production_line_id,
    ]));

    $response->assertCreated();
});

it('berhasil: updates an existing record and upserts its detail rows (insert new, update existing, delete removed)', function () {
    $record = KernelPlantRecord::factory()->forStation($this->kernelPlantStation)->create(['kernel_plant_id' => 'KP-OLD']);
    $keptDetail = KernelPlantDetail::factory()->forRecord($record)->timeSlot('07:00')->filled()->create();
    $removedDetail = KernelPlantDetail::factory()->forRecord($record)->timeSlot('12:00')->filled()->create();

    $payload = kernelPlantApiPayload([
        'kernel_plant_id' => 'KP-NEW',
        'details' => [
            ['id' => $keptDetail->id, 'time_slot' => '07:00', 'ripple_mill_1_amps' => 21.4],
            ['time_slot' => '15:00', 'ripple_mill_1_amps' => 23],
        ],
    ]);
    unset($payload['production_line_id']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/kernel-plant-records/{$record->id}", $payload);

    $response->assertOk();
    $response->assertJsonFragment(['kernel_plant_id' => 'KP-NEW']);
    expect($record->fresh()->kernel_plant_id)->toBe('KP-NEW');
    expect(KernelPlantDetail::where('kernel_plant_record_id', $record->id)->count())->toBe(2);
    expect(KernelPlantDetail::find($removedDetail->id))->toBeNull();
});

it('does not change station_id even if production_line_id is sent on update', function () {
    $record = KernelPlantRecord::factory()->forStation($this->kernelPlantStation)->create();
    $existingDetail = KernelPlantDetail::factory()->forRecord($record)->timeSlot('07:00')->filled()->create();
    $otherProductionLine = ProductionLine::factory()->create();
    Station::factory()->forProductionLine($otherProductionLine)->kernelPlant()->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/kernel-plant-records/{$record->id}", kernelPlantApiPayload([
        'production_line_id' => $otherProductionLine->id,
        'details' => [
            ['id' => $existingDetail->id, 'time_slot' => $existingDetail->time_slot, 'ripple_mill_1_amps' => $existingDetail->ripple_mill_1_amps],
        ],
    ]));

    $response->assertOk();
    expect($record->fresh()->station_id)->toBe($this->kernelPlantStation->id);
});

it('returns 404 RECORD_NOT_FOUND when updating a non-existent id', function () {
    $response = $this->actingAs($this->admin, 'web')->patchJson(
        '/api/kernel-plant-records/00000000-0000-0000-0000-000000000000',
        kernelPlantApiPayload()
    );

    $response->assertStatus(404);
});

it('rejects unauthenticated requests on create and update', function () {
    $this->postJson('/api/kernel-plant-records', kernelPlantApiPayload([
        'production_line_id' => $this->kernelPlantStation->production_line_id,
    ]))->assertStatus(401);

    $record = KernelPlantRecord::factory()->forStation($this->kernelPlantStation)->create();
    $this->patchJson("/api/kernel-plant-records/{$record->id}", kernelPlantApiPayload())->assertStatus(401);
});
