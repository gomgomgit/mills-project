<?php

/**
 * DetailCagesTrackTest (Feature/Livewire) — screen-021--detail-cages-track-web /
 * usecase-021--detail-cages-track-web.
 *
 * Component tests for App\Livewire\Data\DetailCagesTrack, one per
 * test_scenarios' component_test step. Mirrors
 * tests/Feature/Livewire/DetailGradingTest.php's setup/conventions
 * (Livewire::actingAs, RefreshDatabase via tests/Pest.php).
 */

use App\Enums\UserRole;
use App\Livewire\Data\DetailCagesTrack;
use App\Models\BusinessUnit;
use App\Models\CagesTippedTime;
use App\Models\CagesTrackRecord;
use App\Models\Station;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->user = User::factory()->role(UserRole::Supervisor)->create();
});

// Scenario: "Lihat Detail Cages Track - berhasil"
it('berhasil: renders all header fields grouped and the cages tipped time grid, read-only', function () {
    $record = CagesTrackRecord::factory()->forStation($this->station)->create();
    CagesTippedTime::factory()->forRecord($record)->create(['tipped_hour' => 10, 'checked_cage_numbers' => '1,2,3']);

    Livewire::actingAs($this->user)
        ->test(DetailCagesTrack::class, ['id' => $record->id])
        ->assertSee($record->cages_track_number)
        ->assertSee('1,2,3')
        ->assertDontSee('Record tidak ditemukan');
});

it('shows Checked By and Acknowledged By both, unlike Grading which hides Checked By', function () {
    $checker = User::factory()->role(UserRole::Supervisor)->create(['name' => 'Checker Person']);
    $acknowledger = User::factory()->role(UserRole::MillManagement)->create(['name' => 'Acknowledger Person']);
    $record = CagesTrackRecord::factory()->forStation($this->station)->create([
        'checked_by' => $checker->id,
        'acknowledged_by' => $acknowledger->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(DetailCagesTrack::class, ['id' => $record->id])
        ->assertSee('Checker Person')
        ->assertSee('Acknowledger Person');
});

// Scenario: "Lihat Detail Cages Track - Record Tidak Ditemukan"
it('Record Tidak Ditemukan: shows an error message with a Back button', function () {
    Livewire::actingAs($this->user)
        ->test(DetailCagesTrack::class, ['id' => '00000000-0000-0000-0000-000000000000'])
        ->assertSet('notFound', true)
        ->assertSee('Record tidak ditemukan')
        ->assertSeeHtml('data-testid="back-button"');
});

it('Back button links to the Data Browser Cages Track route', function () {
    $record = CagesTrackRecord::factory()->forStation($this->station)->create();

    Livewire::actingAs($this->user)
        ->test(DetailCagesTrack::class, ['id' => $record->id])
        ->assertSeeHtml(route('data.cages-track'));
});
