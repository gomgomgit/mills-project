<?php

/**
 * DetailKernelDispatchTest (Feature/Livewire) —
 * screen-103--detail-kernel-dispatch-web /
 * usecase-077--detail-kernel-dispatch-web.
 *
 * Component tests for App\Livewire\Data\DetailKernelDispatch. Mirrors
 * DetailSolidWasteDisposalTest.php's setup/conventions.
 */

use App\Enums\UserRole;
use App\Livewire\Data\DetailKernelDispatch;
use App\Models\BusinessUnit;
use App\Models\KernelDispatchDetail;
use App\Models\KernelDispatchRecord;
use App\Models\Station;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->kernelDispatch()->create();
    $this->user = User::factory()->role(UserRole::Supervisor)->create();
});

// Scenario: "Lihat Detail Kernel Dispatch - berhasil"
it('berhasil: renders all header fields grouped and the event-log table, read-only', function () {
    $record = KernelDispatchRecord::factory()->forStation($this->station)->create();
    KernelDispatchDetail::factory()->forRecord($record)->create(['vehicle_plate_no' => 'B 1234 XY']);

    Livewire::actingAs($this->user)
        ->test(DetailKernelDispatch::class, ['id' => $record->id])
        ->assertSee($record->kernel_dispatch_id)
        ->assertSee('B 1234 XY')
        ->assertDontSee('Record tidak ditemukan');
});

it('shows Checked By and Acknowledged By both', function () {
    $checker = User::factory()->role(UserRole::Supervisor)->create(['name' => 'Checker Person']);
    $acknowledger = User::factory()->role(UserRole::MillManagement)->create(['name' => 'Acknowledger Person']);
    $record = KernelDispatchRecord::factory()->forStation($this->station)->create([
        'checked_by' => $checker->id,
        'acknowledged_by' => $acknowledger->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(DetailKernelDispatch::class, ['id' => $record->id])
        ->assertSee('Checker Person')
        ->assertSee('Acknowledger Person');
});

// Scenario: "Lihat Detail Kernel Dispatch - Record Tidak Ditemukan"
it('Record Tidak Ditemukan: shows an error message with a Back button', function () {
    Livewire::actingAs($this->user)
        ->test(DetailKernelDispatch::class, ['id' => '00000000-0000-0000-0000-000000000000'])
        ->assertSet('notFound', true)
        ->assertSee('Record tidak ditemukan')
        ->assertSeeHtml('data-testid="back-button"');
});

it('Back button links to the Data Browser Kernel Dispatch route', function () {
    $record = KernelDispatchRecord::factory()->forStation($this->station)->create();

    Livewire::actingAs($this->user)
        ->test(DetailKernelDispatch::class, ['id' => $record->id])
        ->assertSeeHtml(route('data.kernel-dispatch'));
});
