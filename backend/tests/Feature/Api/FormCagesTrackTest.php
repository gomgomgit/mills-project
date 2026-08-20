<?php

/**
 * FormCagesTrackTest (Feature/Api) — screen-024--form-cages-track-web /
 * usecase-024--form-cages-track-web.
 *
 * Integration tests for POST /api/cages-track-records and
 * PATCH /api/cages-track-records/{id} (App\Http\Controllers\Api\
 * CagesTrackRecordController::store()/update()), one per test_scenarios'
 * api_test step(s). Mirrors FormGradingTest.php's setup/conventions
 * (screen-023), with CagesTrackRecord's tipped_hour/checked_cage_numbers
 * detail grid + COUNT(machinery WHERE station_id = the production line's
 * active Cages Track station) resolution layered on top — unlike Grading
 * (Acknowledged By only), this screen also has Checked By, mirroring
 * FormWeighbridgeTest.php's dual checkbox coverage.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\CagesTrackRecord;
use App\Models\Machinery;
use App\Models\Station;
use App\Models\User;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->cagesTrackStation = Station::factory()->forBusinessUnit($this->businessUnit)->cagesTrack()->create();
    Machinery::factory()->count(10)->create(['station_id' => $this->cagesTrackStation->id]);
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->create();
});

function cagesApiPayload(array $overrides = []): array
{
    return array_merge([
        'cages_track_number' => 'CT-API-001',
        'date' => '2026-08-20',
        'tippler_start_time' => '2026-08-20T08:00:00Z',
        'tippler_stop_time' => '2026-08-20T09:00:00Z',
        'cages_out' => 12,
        'cages_tipped' => 10,
    ], $overrides);
}

// Scenario: "Buat Record Cages Track Baru — berhasil"
it('berhasil: creates a new record with status=saved, resolved station_id, and inserted details', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/cages-track-records', cagesApiPayload([
        'production_line_id' => $this->cagesTrackStation->production_line_id,
        'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1, 2, 3]]],
    ]));

    $response->assertCreated();
    $response->assertJsonFragment(['station_id' => $this->cagesTrackStation->id, 'status' => 'saved']);
    expect(CagesTrackRecord::where('cages_track_number', 'CT-API-001')->exists())->toBeTrue();
});

// Scenario: "Jumlah Kolom Grid Mengikuti Machinery Count, Bukan Cages Tipped Header"
it('computes total_cages/cages_remain from machinery count, not the cages_tipped header value', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/cages-track-records', cagesApiPayload([
        'production_line_id' => $this->cagesTrackStation->production_line_id,
        'cages_tipped' => 15,
        'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1, 2, 3, 4, 5, 6, 7, 8]]],
    ]));

    $response->assertCreated();
    $response->assertJsonFragment(['total_cages' => 8, 'cages_remain' => 2]);
});

// Scenario: "Field Wajib Belum Lengkap"
it('returns 422 VALIDATION_ERROR when a required field is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/cages-track-records', cagesApiPayload([
        'production_line_id' => $this->cagesTrackStation->production_line_id,
        'cages_track_number' => '',
        'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1]]],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('cages_track_number');
});

// Scenario: "Belum Ada Baris Cages Tipped Time Valid"
it('returns 422 VALIDATION_ERROR when details array is empty', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/cages-track-records', cagesApiPayload([
        'production_line_id' => $this->cagesTrackStation->production_line_id,
        'details' => [],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

// Scenario: "Time Tidak Bisa Duplikat Atau Mundur"
it('returns 422 VALIDATION_ERROR when tipped_hour is not ascending across detail rows', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/cages-track-records', cagesApiPayload([
        'production_line_id' => $this->cagesTrackStation->production_line_id,
        'details' => [
            ['tipped_hour' => 7, 'checked_cage_numbers' => [1]],
            ['tipped_hour' => 5, 'checked_cage_numbers' => [2]],
        ],
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('details');
});

// Scenario: "Business Unit Tanpa Station Cages Track Aktif"
it('returns 422 when production_line_id has no active cages-track station', function () {
    $otherProductionLine = \App\Models\ProductionLine::factory()->create();

    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/cages-track-records', cagesApiPayload([
        'production_line_id' => $otherProductionLine->id,
        'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1]]],
    ]));

    $response->assertStatus(422);
});

it('sets checked_by when checked=true and requester role=supervisor', function () {
    $response = $this->actingAs($this->supervisor, 'web')->postJson('/api/cages-track-records', cagesApiPayload([
        'production_line_id' => $this->cagesTrackStation->production_line_id,
        'checked' => true,
        'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1]]],
    ]));

    $response->assertCreated();
    expect(CagesTrackRecord::where('cages_track_number', 'CT-API-001')->first()->checked_by)->toBe($this->supervisor->id);
});

it('sets acknowledged_by when acknowledged=true and requester role=mill_management', function () {
    $response = $this->actingAs($this->millManagement, 'web')->postJson('/api/cages-track-records', cagesApiPayload([
        'production_line_id' => $this->cagesTrackStation->production_line_id,
        'acknowledged' => true,
        'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1]]],
    ]));

    $response->assertCreated();
    expect(CagesTrackRecord::where('cages_track_number', 'CT-API-001')->first()->acknowledged_by)->toBe($this->millManagement->id);
});

// TEMPORARY (2026-08-20, mobile syncService.ts): Operator is now allowed
// on this route (was 403) — see FormWeighbridgeTest.php's matching test
// for the full rationale.
it('allows the Operator role to create (mobile sync)', function () {
    $response = $this->actingAs($this->operator, 'web')->postJson('/api/cages-track-records', cagesApiPayload([
        'production_line_id' => $this->cagesTrackStation->production_line_id,
        'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1]]],
    ]));

    $response->assertCreated();
});

// Scenario: "Edit Record Cages Track — berhasil"
it('berhasil: updates an existing record and its details', function () {
    $record = CagesTrackRecord::factory()->forStation($this->cagesTrackStation)->create(['cages_track_number' => 'CT-OLD']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/cages-track-records/{$record->id}", cagesApiPayload([
        'cages_track_number' => 'CT-NEW',
        'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1]]],
    ]));

    $response->assertOk();
    $response->assertJsonFragment(['cages_track_number' => 'CT-NEW']);
    expect($record->fresh()->cages_track_number)->toBe('CT-NEW');
});

it('does not change station_id even if production_line_id is sent on update', function () {
    $record = CagesTrackRecord::factory()->forStation($this->cagesTrackStation)->create();
    $otherProductionLine = \App\Models\ProductionLine::factory()->create();
    Station::factory()->forProductionLine($otherProductionLine)->cagesTrack()->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/cages-track-records/{$record->id}", cagesApiPayload([
        'production_line_id' => $otherProductionLine->id,
        'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1]]],
    ]));

    $response->assertOk();
    expect($record->fresh()->station_id)->toBe($this->cagesTrackStation->id);
});

// Scenario: "Record Tidak Ditemukan (mode edit)"
it('returns 404 RECORD_NOT_FOUND when updating a non-existent id', function () {
    $response = $this->actingAs($this->admin, 'web')->patchJson(
        '/api/cages-track-records/00000000-0000-0000-0000-000000000000',
        cagesApiPayload(['details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1]]]])
    );

    $response->assertStatus(404);
});

it('rejects unauthenticated requests on create and update', function () {
    $this->postJson('/api/cages-track-records', cagesApiPayload([
        'production_line_id' => $this->cagesTrackStation->production_line_id,
        'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1]]],
    ]))->assertStatus(401);

    $record = CagesTrackRecord::factory()->forStation($this->cagesTrackStation)->create();
    $this->patchJson("/api/cages-track-records/{$record->id}", cagesApiPayload())->assertStatus(401);
});
