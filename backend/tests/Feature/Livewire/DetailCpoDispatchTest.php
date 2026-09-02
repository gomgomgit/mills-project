<?php

/**
 * DetailCpoDispatchTest (Feature/Livewire) —
 * screen-104--detail-cpo-dispatch-web /
 * usecase-083--detail-cpo-dispatch-web.
 *
 * Component tests for App\Livewire\Data\DetailCpoDispatch. Mirrors
 * DetailKernelDispatchTest.php's setup/conventions.
 */

use App\Enums\UserRole;
use App\Livewire\Data\DetailCpoDispatch;
use App\Models\BusinessUnit;
use App\Models\CpoDispatchDetail;
use App\Models\CpoDispatchRecord;
use App\Models\Station;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->cpoDispatch()->create();
    $this->user = User::factory()->role(UserRole::Supervisor)->create();
});

// Scenario: "Lihat Detail CPO Dispatch - berhasil"
it('berhasil: renders all header fields grouped and the event-log table, read-only', function () {
    $record = CpoDispatchRecord::factory()->forStation($this->station)->create();
    CpoDispatchDetail::factory()->forRecord($record)->create(['tanker_plate_no' => 'B 1234 XY']);

    Livewire::actingAs($this->user)
        ->test(DetailCpoDispatch::class, ['id' => $record->id])
        ->assertSee($record->cpo_dispatch_id)
        ->assertSee('B 1234 XY')
        ->assertDontSee('Record tidak ditemukan');
});

it('shows Checked By and Acknowledged By both', function () {
    $checker = User::factory()->role(UserRole::Supervisor)->create(['name' => 'Checker Person']);
    $acknowledger = User::factory()->role(UserRole::MillManagement)->create(['name' => 'Acknowledger Person']);
    $record = CpoDispatchRecord::factory()->forStation($this->station)->create([
        'checked_by' => $checker->id,
        'acknowledged_by' => $acknowledger->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(DetailCpoDispatch::class, ['id' => $record->id])
        ->assertSee('Checker Person')
        ->assertSee('Acknowledger Person');
});

// Scenario: "Lihat Detail CPO Dispatch - Record Tidak Ditemukan"
it('Record Tidak Ditemukan: shows an error message with a Back button', function () {
    Livewire::actingAs($this->user)
        ->test(DetailCpoDispatch::class, ['id' => '00000000-0000-0000-0000-000000000000'])
        ->assertSet('notFound', true)
        ->assertSee('Record tidak ditemukan')
        ->assertSeeHtml('data-testid="back-button"');
});

it('Back button links to the Data Browser CPO Dispatch route', function () {
    $record = CpoDispatchRecord::factory()->forStation($this->station)->create();

    Livewire::actingAs($this->user)
        ->test(DetailCpoDispatch::class, ['id' => $record->id])
        ->assertSeeHtml(route('data.cpo-dispatch'));
});
