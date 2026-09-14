<?php

/**
 * DetailRecordVerificationTest — the direct approve/un-approve action on the
 * Detail screens (2026-09-14 product decision).
 *
 * Before this, verification could only be set by opening the Form in edit
 * mode and re-saving the whole record; the Detail screens now carry the
 * action themselves via App\Livewire\Data\Concerns\HandlesRecordVerification
 * + App\Services\RecordVerificationService.
 *
 * Cages Track is used as the representative station (it exposes BOTH
 * levels), plus Grading as the documented exception that never collects
 * Checked By. The behaviour is shared by all 18 Detail components through
 * the trait, so it is tested once here rather than duplicated 18 times.
 */

use App\Enums\UserRole;
use App\Livewire\Data\DetailCagesTrack;
use App\Livewire\Data\DetailGrading;
use App\Models\BusinessUnit;
use App\Models\CagesTrackRecord;
use App\Models\GradingRecord;
use App\Models\Station;
use App\Models\User;
use Livewire\Livewire;

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
});

it('supervisor: approves Checked By straight from the Detail screen, storing their own id', function () {
    Livewire::actingAs($this->supervisor)
        ->test(DetailCagesTrack::class, ['id' => $this->record->id])
        ->assertSee('Tandai sudah diperiksa (Checked)')
        ->call('toggleChecked')
        ->assertSee('Batalkan tanda diperiksa');

    expect($this->record->fresh()->checked_by)->toBe($this->supervisor->id);
});

it('supervisor: un-approves again, clearing the column back to null', function () {
    $this->record->update(['checked_by' => $this->supervisor->id]);

    Livewire::actingAs($this->supervisor)
        ->test(DetailCagesTrack::class, ['id' => $this->record->id])
        ->call('toggleChecked');

    expect($this->record->fresh()->checked_by)->toBeNull();
});

it('mill management: approves Acknowledged By, and never sees the Checked By action', function () {
    Livewire::actingAs($this->millManagement)
        ->test(DetailCagesTrack::class, ['id' => $this->record->id])
        ->assertSee('Tandai sudah dikonfirmasi (Acknowledged)')
        ->assertDontSee('Tandai sudah diperiksa (Checked)')
        ->call('toggleAcknowledged');

    expect($this->record->fresh()->acknowledged_by)->toBe($this->millManagement->id);
});

it('admin: may write BOTH levels (product decision 2026-09-14)', function () {
    Livewire::actingAs($this->admin)
        ->test(DetailCagesTrack::class, ['id' => $this->record->id])
        ->assertSee('Tandai sudah diperiksa (Checked)')
        ->assertSee('Tandai sudah dikonfirmasi (Acknowledged)')
        ->call('toggleChecked')
        ->call('toggleAcknowledged');

    $fresh = $this->record->fresh();
    expect($fresh->checked_by)->toBe($this->admin->id);
    expect($fresh->acknowledged_by)->toBe($this->admin->id);
});

it('supervisor cannot write Acknowledged By even by calling the action directly', function () {
    Livewire::actingAs($this->supervisor)
        ->test(DetailCagesTrack::class, ['id' => $this->record->id])
        ->call('toggleAcknowledged')
        ->assertSee('Anda tidak berhak melakukan verifikasi ini.');

    expect($this->record->fresh()->acknowledged_by)->toBeNull();
});

it('mill management cannot write Checked By even by calling the action directly', function () {
    Livewire::actingAs($this->millManagement)
        ->test(DetailCagesTrack::class, ['id' => $this->record->id])
        ->call('toggleChecked')
        ->assertSee('Anda tidak berhak melakukan verifikasi ini.');

    expect($this->record->fresh()->checked_by)->toBeNull();
});

// Grading never collects Checked By — see GradingRecordService::applyVerification()
// and RecordVerificationService::NO_CHECKED_BY_MODELS.
it('grading: no Checked By action for anyone, not even Admin; Acknowledged By still works', function () {
    $grading = GradingRecord::factory()
        ->forStation($this->station)
        ->create(['acknowledged_by' => null]);

    Livewire::actingAs($this->admin)
        ->test(DetailGrading::class, ['id' => $grading->id])
        ->assertDontSee('Tandai sudah diperiksa (Checked)')
        ->assertSee('Tandai sudah dikonfirmasi (Acknowledged)')
        ->call('toggleChecked')
        ->assertSee('Anda tidak berhak melakukan verifikasi ini.');

    expect($grading->fresh()->checked_by)->toBeNull();

    Livewire::actingAs($this->admin)
        ->test(DetailGrading::class, ['id' => $grading->id])
        ->call('toggleAcknowledged');

    expect($grading->fresh()->acknowledged_by)->toBe($this->admin->id);
});

it('approving touches ONLY the verification column, leaving record data untouched', function () {
    $before = $this->record->fresh()->only(['cages_track_number', 'cages_out', 'cages_tipped', 'note', 'status']);

    Livewire::actingAs($this->supervisor)
        ->test(DetailCagesTrack::class, ['id' => $this->record->id])
        ->call('toggleChecked');

    expect($this->record->fresh()->only(array_keys($before)))->toBe($before);
});
