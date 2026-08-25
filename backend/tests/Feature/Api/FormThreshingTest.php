<?php

/**
 * FormThreshingTest (Feature/Api) — screen-057--form-threshing-web /
 * usecase-057--form-threshing-web.
 *
 * Integration tests for POST /api/threshing-records and PATCH
 * /api/threshing-records/{id}, mirroring
 * tests/Feature/Api/FormCagesTrackTest.php's structure. REVISED 2026-08-24
 * (entity-catalog v12): `details` is now a dynamic array of 1..24 rows
 * (unique + strictly ascending canonical time_slot order) rather than
 * always exactly 24 entries.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\ThreshingDetail;
use App\Models\ThreshingRecord;
use App\Models\User;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->threshingStation = Station::factory()->forBusinessUnit($this->businessUnit)->threshing()->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->create();
});

function threshingApiPayload(array $overrides = []): array
{
    return array_merge([
        'thresher_id' => 'TH-API-001',
        'date' => '2026-08-24',
        'details' => [['time_slot' => '07:00', 'ffb_throughput_mt_hour' => 45.5]],
    ], $overrides);
}

it('berhasil: creates a new record with status=saved, resolved station_id, and only the given detail rows', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/threshing-records', threshingApiPayload([
        'production_line_id' => $this->threshingStation->production_line_id,
    ]));

    $response->assertCreated();
    $response->assertJsonFragment(['station_id' => $this->threshingStation->id, 'status' => 'saved']);
    expect(ThreshingRecord::where('thresher_id', 'TH-API-001')->exists())->toBeTrue();
    expect($response->json('details'))->toHaveCount(1);
});

it('returns 422 VALIDATION_ERROR when a required field is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/threshing-records', threshingApiPayload([
        'production_line_id' => $this->threshingStation->production_line_id,
        'thresher_id' => '',
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('thresher_id');
});

it('returns 422 VALIDATION_ERROR when details is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/threshing-records', threshingApiPayload([
        'production_line_id' => $this->threshingStation->production_line_id,
        'details' => [],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when a detail row uses a time_slot outside the 24 canonical slots', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/threshing-records', threshingApiPayload([
        'production_line_id' => $this->threshingStation->production_line_id,
        'details' => [['time_slot' => '07:15', 'ffb_throughput_mt_hour' => 1]],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when time_slot is not ascending across detail rows', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/threshing-records', threshingApiPayload([
        'production_line_id' => $this->threshingStation->production_line_id,
        'details' => [
            ['time_slot' => '09:00', 'ffb_throughput_mt_hour' => 1],
            ['time_slot' => '07:00', 'ffb_throughput_mt_hour' => 2],
        ],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when zero rows have any reading filled', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/threshing-records', threshingApiPayload([
        'production_line_id' => $this->threshingStation->production_line_id,
        'details' => [['time_slot' => '07:00']], // no reading columns
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 when production_line_id has no active threshing station', function () {
    $otherProductionLine = ProductionLine::factory()->create();

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/threshing-records', threshingApiPayload([
        'production_line_id' => $otherProductionLine->id,
    ]));

    $response->assertStatus(422);
});

it('sets checked_by when checked=true and requester role=supervisor', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/threshing-records', threshingApiPayload([
        'production_line_id' => $this->threshingStation->production_line_id,
        'checked' => true,
    ]));

    $response->assertCreated();
    expect(ThreshingRecord::where('thresher_id', 'TH-API-001')->first()->checked_by)->toBe($this->supervisor->id);
});

it('sets acknowledged_by when acknowledged=true and requester role=mill_management', function () {
    $response = $this->actingAs($this->millManagement, 'web')->postJson('/api/threshing-records', threshingApiPayload([
        'production_line_id' => $this->threshingStation->production_line_id,
        'acknowledged' => true,
    ]));

    $response->assertCreated();
    expect(ThreshingRecord::where('thresher_id', 'TH-API-001')->first()->acknowledged_by)->toBe($this->millManagement->id);
});

it('allows the Operator role to create (mobile sync)', function () {
    $response = $this->actingAs($this->operator, 'web')->postJson('/api/threshing-records', threshingApiPayload([
        'production_line_id' => $this->threshingStation->production_line_id,
    ]));

    $response->assertCreated();
});

it('berhasil: updates an existing record and upserts its detail rows (insert new, update existing, delete removed)', function () {
    $record = ThreshingRecord::factory()->forStation($this->threshingStation)->create(['thresher_id' => 'TH-OLD']);
    $keptDetail = ThreshingDetail::factory()->forRecord($record)->timeSlot('07:00')->filled()->create();
    $removedDetail = ThreshingDetail::factory()->forRecord($record)->timeSlot('12:00')->filled()->create();

    $payload = threshingApiPayload([
        'thresher_id' => 'TH-NEW',
        'details' => [
            ['id' => $keptDetail->id, 'time_slot' => '07:00', 'ffb_throughput_mt_hour' => 99.9],
            ['time_slot' => '15:00', 'ffb_throughput_mt_hour' => 5],
        ],
    ]);
    unset($payload['production_line_id']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/threshing-records/{$record->id}", $payload);

    $response->assertOk();
    $response->assertJsonFragment(['thresher_id' => 'TH-NEW']);
    expect($record->fresh()->thresher_id)->toBe('TH-NEW');
    expect(ThreshingDetail::where('threshing_record_id', $record->id)->count())->toBe(2);
    expect(ThreshingDetail::find($removedDetail->id))->toBeNull();
});

it('does not change station_id even if production_line_id is sent on update', function () {
    $record = ThreshingRecord::factory()->forStation($this->threshingStation)->create();
    $existingDetail = ThreshingDetail::factory()->forRecord($record)->timeSlot('07:00')->filled()->create();
    $otherProductionLine = ProductionLine::factory()->create();
    Station::factory()->forProductionLine($otherProductionLine)->threshing()->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/threshing-records/{$record->id}", threshingApiPayload([
        'production_line_id' => $otherProductionLine->id,
        'details' => [
            ['id' => $existingDetail->id, 'time_slot' => $existingDetail->time_slot, 'ffb_throughput_mt_hour' => $existingDetail->ffb_throughput_mt_hour],
        ],
    ]));

    $response->assertOk();
    expect($record->fresh()->station_id)->toBe($this->threshingStation->id);
});

it('returns 404 RECORD_NOT_FOUND when updating a non-existent id', function () {
    $response = $this->actingAs($this->admin, 'web')->patchJson(
        '/api/threshing-records/00000000-0000-0000-0000-000000000000',
        threshingApiPayload()
    );

    $response->assertStatus(404);
});

it('rejects unauthenticated requests on create and update', function () {
    $this->postJson('/api/threshing-records', threshingApiPayload([
        'production_line_id' => $this->threshingStation->production_line_id,
    ]))->assertStatus(401);

    $record = ThreshingRecord::factory()->forStation($this->threshingStation)->create();
    $this->patchJson("/api/threshing-records/{$record->id}", threshingApiPayload())->assertStatus(401);
});
