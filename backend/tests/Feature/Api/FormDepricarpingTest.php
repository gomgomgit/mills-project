<?php

/**
 * FormDepricarpingTest (Feature/Api) — screen-059--form-depricarping-web /
 * usecase-059--form-depricarping-web.
 *
 * Integration tests for POST /api/depricarping-records and PATCH
 * /api/depricarping-records/{id}, mirroring
 * tests/Feature/Api/FormPressingTest.php's structure. REVISED 2026-08-24
 * (entity-catalog v12): `details` is now a dynamic array of 1..24 rows
 * (unique + strictly ascending canonical time_slot order) rather than
 * always exactly 24 entries.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\DepricarpingDetail;
use App\Models\DepricarpingRecord;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->depricarpingStation = Station::factory()->forBusinessUnit($this->businessUnit)->depricarping()->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->create();
});

function depricarpingApiPayload(array $overrides = []): array
{
    return array_merge([
        'presser_id' => 'DP-API-001',
        'date' => '2026-08-24',
        'details' => [['time_slot' => '07:00', 'fan_static_pressure_mmh2o' => 45]],
    ], $overrides);
}

it('berhasil: creates a new record with status=saved, resolved station_id, and only the given detail rows', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/depricarping-records', depricarpingApiPayload([
        'production_line_id' => $this->depricarpingStation->production_line_id,
    ]));

    $response->assertCreated();
    $response->assertJsonFragment(['station_id' => $this->depricarpingStation->id, 'status' => 'saved']);
    expect(DepricarpingRecord::where('presser_id', 'DP-API-001')->exists())->toBeTrue();
    expect($response->json('details'))->toHaveCount(1);
});

it('returns 422 VALIDATION_ERROR when a required field is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/depricarping-records', depricarpingApiPayload([
        'production_line_id' => $this->depricarpingStation->production_line_id,
        'presser_id' => '',
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('presser_id');
});

it('returns 422 VALIDATION_ERROR when details is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/depricarping-records', depricarpingApiPayload([
        'production_line_id' => $this->depricarpingStation->production_line_id,
        'details' => [],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when a detail row uses a time_slot outside the 24 canonical slots', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/depricarping-records', depricarpingApiPayload([
        'production_line_id' => $this->depricarpingStation->production_line_id,
        'details' => [['time_slot' => '07:15', 'fan_static_pressure_mmh2o' => 1]],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when time_slot is not ascending across detail rows', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/depricarping-records', depricarpingApiPayload([
        'production_line_id' => $this->depricarpingStation->production_line_id,
        'details' => [
            ['time_slot' => '09:00', 'fan_static_pressure_mmh2o' => 1],
            ['time_slot' => '07:00', 'fan_static_pressure_mmh2o' => 2],
        ],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when zero rows have any reading filled', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/depricarping-records', depricarpingApiPayload([
        'production_line_id' => $this->depricarpingStation->production_line_id,
        'details' => [['time_slot' => '07:00']], // no reading columns
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('treats a row with only downtime_minutes filled as satisfying the minimum-one-row rule', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/depricarping-records', depricarpingApiPayload([
        'production_line_id' => $this->depricarpingStation->production_line_id,
        'details' => [['time_slot' => '07:00', 'downtime_minutes' => 15]],
    ]));

    $response->assertCreated();
});

it('treats a row with only findings filled as satisfying the minimum-one-row rule', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/depricarping-records', depricarpingApiPayload([
        'production_line_id' => $this->depricarpingStation->production_line_id,
        'details' => [['time_slot' => '07:00', 'findings' => 'Fan belt loose']],
    ]));

    $response->assertCreated();
});

it('returns 422 when production_line_id has no active depricarping station', function () {
    $otherProductionLine = ProductionLine::factory()->create();

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/depricarping-records', depricarpingApiPayload([
        'production_line_id' => $otherProductionLine->id,
    ]));

    $response->assertStatus(422);
});

it('sets checked_by when checked=true and requester role=supervisor', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/depricarping-records', depricarpingApiPayload([
        'production_line_id' => $this->depricarpingStation->production_line_id,
        'checked' => true,
    ]));

    $response->assertCreated();
    expect(DepricarpingRecord::where('presser_id', 'DP-API-001')->first()->checked_by)->toBe($this->supervisor->id);
});

it('sets acknowledged_by when acknowledged=true and requester role=mill_management', function () {
    $response = $this->actingAs($this->millManagement, 'web')->postJson('/api/depricarping-records', depricarpingApiPayload([
        'production_line_id' => $this->depricarpingStation->production_line_id,
        'acknowledged' => true,
    ]));

    $response->assertCreated();
    expect(DepricarpingRecord::where('presser_id', 'DP-API-001')->first()->acknowledged_by)->toBe($this->millManagement->id);
});

it('allows the Operator role to create (mobile sync)', function () {
    $response = $this->actingAs($this->operator, 'web')->postJson('/api/depricarping-records', depricarpingApiPayload([
        'production_line_id' => $this->depricarpingStation->production_line_id,
    ]));

    $response->assertCreated();
});

it('berhasil: updates an existing record and upserts its detail rows (insert new, update existing, delete removed)', function () {
    $record = DepricarpingRecord::factory()->forStation($this->depricarpingStation)->create(['presser_id' => 'DP-OLD']);
    $keptDetail = DepricarpingDetail::factory()->forRecord($record)->timeSlot('07:00')->filled()->create();
    $removedDetail = DepricarpingDetail::factory()->forRecord($record)->timeSlot('12:00')->filled()->create();

    $payload = depricarpingApiPayload([
        'presser_id' => 'DP-NEW',
        'details' => [
            ['id' => $keptDetail->id, 'time_slot' => '07:00', 'fan_static_pressure_mmh2o' => 48.8],
            ['time_slot' => '15:00', 'fan_static_pressure_mmh2o' => 46],
        ],
    ]);
    unset($payload['production_line_id']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/depricarping-records/{$record->id}", $payload);

    $response->assertOk();
    $response->assertJsonFragment(['presser_id' => 'DP-NEW']);
    expect($record->fresh()->presser_id)->toBe('DP-NEW');
    expect(DepricarpingDetail::where('depricarping_record_id', $record->id)->count())->toBe(2);
    expect(DepricarpingDetail::find($removedDetail->id))->toBeNull();
});

it('does not change station_id even if production_line_id is sent on update', function () {
    $record = DepricarpingRecord::factory()->forStation($this->depricarpingStation)->create();
    $existingDetail = DepricarpingDetail::factory()->forRecord($record)->timeSlot('07:00')->filled()->create();
    $otherProductionLine = ProductionLine::factory()->create();
    Station::factory()->forProductionLine($otherProductionLine)->depricarping()->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/depricarping-records/{$record->id}", depricarpingApiPayload([
        'production_line_id' => $otherProductionLine->id,
        'details' => [
            ['id' => $existingDetail->id, 'time_slot' => $existingDetail->time_slot, 'fan_static_pressure_mmh2o' => $existingDetail->fan_static_pressure_mmh2o],
        ],
    ]));

    $response->assertOk();
    expect($record->fresh()->station_id)->toBe($this->depricarpingStation->id);
});

it('returns 404 RECORD_NOT_FOUND when updating a non-existent id', function () {
    $response = $this->actingAs($this->admin, 'web')->patchJson(
        '/api/depricarping-records/00000000-0000-0000-0000-000000000000',
        depricarpingApiPayload()
    );

    $response->assertStatus(404);
});

it('rejects unauthenticated requests on create and update', function () {
    $this->postJson('/api/depricarping-records', depricarpingApiPayload([
        'production_line_id' => $this->depricarpingStation->production_line_id,
    ]))->assertStatus(401);

    $record = DepricarpingRecord::factory()->forStation($this->depricarpingStation)->create();
    $this->patchJson("/api/depricarping-records/{$record->id}", depricarpingApiPayload())->assertStatus(401);
});
