<?php

/**
 * DetailSolidWasteDisposalTest (Feature/Livewire) —
 * screen-101--detail-solid-waste-disposal-web /
 * usecase-065--detail-solid-waste-disposal-web.
 *
 * Component tests for App\Livewire\Data\DetailSolidWasteDisposal. Mirrors
 * DetailCagesTrackTest.php's setup/conventions.
 */

use App\Enums\UserRole;
use App\Livewire\Data\DetailSolidWasteDisposal;
use App\Models\BusinessUnit;
use App\Models\SolidWasteDisposalDetail;
use App\Models\SolidWasteDisposalRecord;
use App\Models\Station;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->solidWasteDisposal()->create();
    $this->user = User::factory()->role(UserRole::Supervisor)->create();
});

// Scenario: "Lihat Detail Solid Waste Disposal - berhasil"
it('berhasil: renders all header fields grouped and the event-log table, read-only', function () {
    $record = SolidWasteDisposalRecord::factory()->forStation($this->station)->create();
    SolidWasteDisposalDetail::factory()->forRecord($record)->create(['vehicle_no' => 'B 1234 XY']);

    Livewire::actingAs($this->user)
        ->test(DetailSolidWasteDisposal::class, ['id' => $record->id])
        ->assertSee($record->solid_waste_disposal_id)
        ->assertSee('B 1234 XY')
        ->assertDontSee('Record tidak ditemukan');
});

it('shows Checked By and Acknowledged By both', function () {
    $checker = User::factory()->role(UserRole::Supervisor)->create(['name' => 'Checker Person']);
    $acknowledger = User::factory()->role(UserRole::MillManagement)->create(['name' => 'Acknowledger Person']);
    $record = SolidWasteDisposalRecord::factory()->forStation($this->station)->create([
        'checked_by' => $checker->id,
        'acknowledged_by' => $acknowledger->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(DetailSolidWasteDisposal::class, ['id' => $record->id])
        ->assertSee('Checker Person')
        ->assertSee('Acknowledger Person');
});

// Scenario: "Lihat Detail Solid Waste Disposal - Record Tidak Ditemukan"
it('Record Tidak Ditemukan: shows an error message with a Back button', function () {
    Livewire::actingAs($this->user)
        ->test(DetailSolidWasteDisposal::class, ['id' => '00000000-0000-0000-0000-000000000000'])
        ->assertSet('notFound', true)
        ->assertSee('Record tidak ditemukan')
        ->assertSeeHtml('data-testid="back-button"');
});

it('Back button links to the Data Browser Solid Waste Disposal route', function () {
    $record = SolidWasteDisposalRecord::factory()->forStation($this->station)->create();

    Livewire::actingAs($this->user)
        ->test(DetailSolidWasteDisposal::class, ['id' => $record->id])
        ->assertSeeHtml(route('data.solid-waste-disposal'));
});
