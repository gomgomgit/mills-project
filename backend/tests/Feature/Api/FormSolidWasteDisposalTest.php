<?php

/**
 * FormSolidWasteDisposalTest (Feature/Api) —
 * screen-111--form-solid-waste-disposal-web /
 * usecase-066--form-solid-waste-disposal-web.
 *
 * Integration tests for POST /api/solid-waste-disposal-records and
 * PATCH /api/solid-waste-disposal-records/{id}. Mirrors
 * FormCagesTrackTest.php's structure, minus the grid/N-column and
 * time-slot-ordering concerns — Solid Waste Disposal's detail rows are a
 * free event log.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\ProductionLine;
use App\Models\SolidWasteDisposalRecord;
use App\Models\Station;
use App\Models\User;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->solidWasteStation = Station::factory()->forBusinessUnit($this->businessUnit)->solidWasteDisposal()->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->create();
});

function solidWasteApiPayload(array $overrides = []): array
{
    return array_merge([
        'solid_waste_disposal_id' => 'SWD-API-001',
        'date' => '2026-08-31',
    ], $overrides);
}

// Scenario: "Buat Record Solid Waste Disposal Baru — berhasil"
it('berhasil: creates a new record with status=saved, resolved station_id, and inserted details', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/solid-waste-disposal-records', solidWasteApiPayload([
        'production_line_id' => $this->solidWasteStation->production_line_id,
        'details' => [['event_date' => '2026-08-31', 'gross_weight_mt' => 10, 'tare_weight_mt' => 2]],
    ]));

    $response->assertCreated();
    $response->assertJsonFragment(['station_id' => $this->solidWasteStation->id, 'status' => 'saved']);
    expect(SolidWasteDisposalRecord::where('solid_waste_disposal_id', 'SWD-API-001')->exists())->toBeTrue();
});

it('computes net_weight_mt as gross_weight_mt minus tare_weight_mt', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/solid-waste-disposal-records', solidWasteApiPayload([
        'production_line_id' => $this->solidWasteStation->production_line_id,
        'details' => [['event_date' => '2026-08-31', 'gross_weight_mt' => 10.5, 'tare_weight_mt' => 2.5]],
    ]));

    $response->assertCreated();
    $response->assertJsonFragment(['net_weight_mt' => 8.0]);
});

// Scenario: "Field Wajib Belum Lengkap"
it('returns 422 VALIDATION_ERROR when a required field is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/solid-waste-disposal-records', solidWasteApiPayload([
        'production_line_id' => $this->solidWasteStation->production_line_id,
        'solid_waste_disposal_id' => '',
        'details' => [['event_date' => '2026-08-31']],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('solid_waste_disposal_id');
});

// Scenario: "Belum Ada Baris Log Valid"
it('returns 422 VALIDATION_ERROR when details array is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/solid-waste-disposal-records', solidWasteApiPayload([
        'production_line_id' => $this->solidWasteStation->production_line_id,
        'details' => [],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

it('returns 422 VALIDATION_ERROR when no detail row has an event_date', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/solid-waste-disposal-records', solidWasteApiPayload([
        'production_line_id' => $this->solidWasteStation->production_line_id,
        'details' => [['shift' => 'Shift 1']],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

// Scenario: "Production Line Tanpa Station Solid Waste Disposal Aktif"
it('returns 422 when production_line_id has no active solid-waste-disposal station', function () {
    $otherProductionLine = ProductionLine::factory()->create();

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/solid-waste-disposal-records', solidWasteApiPayload([
        'production_line_id' => $otherProductionLine->id,
        'details' => [['event_date' => '2026-08-31']],
    ]));

    $response->assertStatus(422);
});

it('sets checked_by when checked=true and requester role=supervisor', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/solid-waste-disposal-records', solidWasteApiPayload([
        'production_line_id' => $this->solidWasteStation->production_line_id,
        'checked' => true,
        'details' => [['event_date' => '2026-08-31']],
    ]));

    $response->assertCreated();
    expect(SolidWasteDisposalRecord::where('solid_waste_disposal_id', 'SWD-API-001')->first()->checked_by)->toBe($this->supervisor->id);
});

it('sets acknowledged_by when acknowledged=true and requester role=mill_management', function () {
    $response = $this->actingAs($this->millManagement, 'web')->postJson('/api/solid-waste-disposal-records', solidWasteApiPayload([
        'production_line_id' => $this->solidWasteStation->production_line_id,
        'acknowledged' => true,
        'details' => [['event_date' => '2026-08-31']],
    ]));

    $response->assertCreated();
    expect(SolidWasteDisposalRecord::where('solid_waste_disposal_id', 'SWD-API-001')->first()->acknowledged_by)->toBe($this->millManagement->id);
});

// Mobile sync allowance — same convention as FormCagesTrackTest.php/
// FormWeighbridgeTest.php: Operator is allowed to POST for offline sync.
it('allows the Operator role to create (mobile sync)', function () {
    $response = $this->actingAs($this->operator, 'web')->postJson('/api/solid-waste-disposal-records', solidWasteApiPayload([
        'production_line_id' => $this->solidWasteStation->production_line_id,
        'details' => [['event_date' => '2026-08-31']],
    ]));

    $response->assertCreated();
});

// Scenario: "Edit Record Solid Waste Disposal — berhasil"
it('berhasil: updates an existing record and its details', function () {
    $record = SolidWasteDisposalRecord::factory()->forStation($this->solidWasteStation)->create(['solid_waste_disposal_id' => 'SWD-OLD']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/solid-waste-disposal-records/{$record->id}", solidWasteApiPayload([
        'solid_waste_disposal_id' => 'SWD-NEW',
        'details' => [['event_date' => '2026-08-31']],
    ]));

    $response->assertOk();
    $response->assertJsonFragment(['solid_waste_disposal_id' => 'SWD-NEW']);
    expect($record->fresh()->solid_waste_disposal_id)->toBe('SWD-NEW');
});

it('does not change station_id even if production_line_id is sent on update', function () {
    $record = SolidWasteDisposalRecord::factory()->forStation($this->solidWasteStation)->create();
    $otherProductionLine = ProductionLine::factory()->create();
    Station::factory()->forProductionLine($otherProductionLine)->solidWasteDisposal()->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/solid-waste-disposal-records/{$record->id}", solidWasteApiPayload([
        'production_line_id' => $otherProductionLine->id,
        'details' => [['event_date' => '2026-08-31']],
    ]));

    $response->assertOk();
    expect($record->fresh()->station_id)->toBe($this->solidWasteStation->id);
});

// Scenario: "Record Tidak Ditemukan (mode edit)"
it('returns 404 RECORD_NOT_FOUND when updating a non-existent id', function () {
    $response = $this->actingAs($this->admin, 'web')->patchJson(
        '/api/solid-waste-disposal-records/00000000-0000-0000-0000-000000000000',
        solidWasteApiPayload(['details' => [['event_date' => '2026-08-31']]])
    );

    $response->assertStatus(404);
});

it('rejects unauthenticated requests on create and update', function () {
    $this->postJson('/api/solid-waste-disposal-records', solidWasteApiPayload([
        'production_line_id' => $this->solidWasteStation->production_line_id,
        'details' => [['event_date' => '2026-08-31']],
    ]))->assertStatus(401);

    $record = SolidWasteDisposalRecord::factory()->forStation($this->solidWasteStation)->create();
    $this->patchJson("/api/solid-waste-disposal-records/{$record->id}", solidWasteApiPayload())->assertStatus(401);
});
