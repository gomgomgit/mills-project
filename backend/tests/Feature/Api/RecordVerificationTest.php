<?php

/**
 * RecordVerificationTest (Feature/Api) — PATCH
 * /api/records/{stationType}/{id}/verification, the mobile counterpart of
 * the Detail screens' approve/un-approve action (2026-09-14).
 *
 * One generic endpoint serves all 18 stations, so the role rule, the
 * station-type whitelist, and the "only touches the verification column"
 * guarantee are tested here once rather than per station.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\CagesTrackRecord;
use App\Models\GradingRecord;
use App\Models\Station;
use App\Models\User;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->record = CagesTrackRecord::factory()->forStation($this->station)->create([
        'checked_by' => null,
        'acknowledged_by' => null,
    ]);

    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->create();
});

function verifyUrl(string $stationType, string $id): string
{
    return "/api/records/{$stationType}/{$id}/verification";
}

it('supervisor sets checked, and the response echoes the resolved name', function () {
    $this->actingAs($this->supervisor, 'web')
        ->patchJson(verifyUrl('cages-track', $this->record->id), ['level' => 'checked', 'value' => true])
        ->assertOk()
        ->assertJsonPath('checked_by', $this->supervisor->id)
        ->assertJsonPath('checked_by_name', $this->supervisor->name);

    expect($this->record->fresh()->checked_by)->toBe($this->supervisor->id);
});

it('supervisor clears checked again with value=false', function () {
    $this->record->update(['checked_by' => $this->supervisor->id]);

    $this->actingAs($this->supervisor, 'web')
        ->patchJson(verifyUrl('cages-track', $this->record->id), ['level' => 'checked', 'value' => false])
        ->assertOk()
        ->assertJsonPath('checked_by', null);

    expect($this->record->fresh()->checked_by)->toBeNull();
});

it('mill management sets acknowledged but is refused for checked', function () {
    $this->actingAs($this->millManagement, 'web')
        ->patchJson(verifyUrl('cages-track', $this->record->id), ['level' => 'acknowledged', 'value' => true])
        ->assertOk();

    $this->actingAs($this->millManagement, 'web')
        ->patchJson(verifyUrl('cages-track', $this->record->id), ['level' => 'checked', 'value' => true])
        ->assertForbidden();

    $fresh = $this->record->fresh();
    expect($fresh->acknowledged_by)->toBe($this->millManagement->id);
    expect($fresh->checked_by)->toBeNull();
});

it('admin may set both levels', function () {
    $this->actingAs($this->admin, 'web')
        ->patchJson(verifyUrl('cages-track', $this->record->id), ['level' => 'checked', 'value' => true])
        ->assertOk();
    $this->actingAs($this->admin, 'web')
        ->patchJson(verifyUrl('cages-track', $this->record->id), ['level' => 'acknowledged', 'value' => true])
        ->assertOk();

    $fresh = $this->record->fresh();
    expect($fresh->checked_by)->toBe($this->admin->id);
    expect($fresh->acknowledged_by)->toBe($this->admin->id);
});

it('operator is rejected by the route guard before reaching the service', function () {
    $this->actingAs($this->operator, 'web')
        ->patchJson(verifyUrl('cages-track', $this->record->id), ['level' => 'checked', 'value' => true])
        ->assertForbidden();

    expect($this->record->fresh()->checked_by)->toBeNull();
});

it('rejects an unauthenticated request', function () {
    $this->patchJson(verifyUrl('cages-track', $this->record->id), ['level' => 'checked', 'value' => true])
        ->assertUnauthorized();
});

it('grading refuses checked for everyone, acknowledged still works', function () {
    $grading = GradingRecord::factory()->forStation($this->station)->create(['acknowledged_by' => null]);

    $this->actingAs($this->admin, 'web')
        ->patchJson(verifyUrl('grading', $grading->id), ['level' => 'checked', 'value' => true])
        ->assertForbidden();

    $this->actingAs($this->admin, 'web')
        ->patchJson(verifyUrl('grading', $grading->id), ['level' => 'acknowledged', 'value' => true])
        ->assertOk();

    $fresh = $grading->fresh();
    expect($fresh->checked_by)->toBeNull();
    expect($fresh->acknowledged_by)->toBe($this->admin->id);
});

it('returns 404 for an unknown station type instead of resolving a class from input', function () {
    $this->actingAs($this->admin, 'web')
        ->patchJson(verifyUrl('User', $this->record->id), ['level' => 'checked', 'value' => true])
        ->assertNotFound();
});

it('returns 404 for a record id that does not exist', function () {
    $this->actingAs($this->supervisor, 'web')
        ->patchJson(verifyUrl('cages-track', '00000000-0000-0000-0000-000000000000'), ['level' => 'checked', 'value' => true])
        ->assertNotFound();
});

it('validates the payload', function () {
    $this->actingAs($this->supervisor, 'web')
        ->patchJson(verifyUrl('cages-track', $this->record->id), ['level' => 'approved', 'value' => true])
        ->assertStatus(422);

    $this->actingAs($this->supervisor, 'web')
        ->patchJson(verifyUrl('cages-track', $this->record->id), ['level' => 'checked'])
        ->assertStatus(422);
});

it('never touches record data, only the verification column', function () {
    $before = $this->record->fresh()->only(['cages_track_number', 'cages_out', 'cages_tipped', 'note', 'status']);

    $this->actingAs($this->supervisor, 'web')
        ->patchJson(verifyUrl('cages-track', $this->record->id), ['level' => 'checked', 'value' => true])
        ->assertOk();

    expect($this->record->fresh()->only(array_keys($before)))->toBe($before);
});
