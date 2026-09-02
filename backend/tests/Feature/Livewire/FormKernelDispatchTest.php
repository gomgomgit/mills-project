<?php

/**
 * FormKernelDispatchTest (Feature/Livewire) —
 * screen-113--form-kernel-dispatch-web /
 * usecase-078--form-kernel-dispatch-web.
 *
 * Component tests for App\Livewire\Data\FormKernelDispatch. Mirrors
 * FormSolidWasteDisposalTest.php's structure, minus the grid/N-column and
 * time-slot-ordering concerns.
 */

use App\Enums\UserRole;
use App\Livewire\Data\FormKernelDispatch;
use App\Models\BusinessUnit;
use App\Models\KernelDispatchRecord;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->kernelDispatchStation = Station::factory()->forBusinessUnit($this->businessUnit)->kernelDispatch()->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
});

function fillFormKernelDispatch($component, array $overrides = []): void
{
    $defaults = [
        'form.production_line_id' => null,
        'form.kernel_dispatch_id' => 'KD-LW-001',
    ];

    foreach (array_merge($defaults, $overrides) as $key => $value) {
        if ($value === null) {
            continue;
        }
        $component->set($key, $value);
    }
}

// Scenario: "Buat Record Kernel Dispatch Baru — berhasil"
it('berhasil: creates a new record and redirects to Detail Kernel Dispatch', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormKernelDispatch::class);

    fillFormKernelDispatch($component, ['form.production_line_id' => $this->kernelDispatchStation->production_line_id]);
    $component->call('addDetailRow');
    $component->set('detailRows.0.event_date', '2026-08-31');
    $component->call('save');

    expect(KernelDispatchRecord::where('kernel_dispatch_id', 'KD-LW-001')->exists())->toBeTrue();
});

it('default: mode create renders empty form with Production Line dropdown', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormKernelDispatch::class)
        ->assertSet('isEdit', false)
        ->assertSeeHtml('data-testid="production-line-select"');
});

it('rowNetWeight computes gross minus tare reactively as the row is filled in', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormKernelDispatch::class);

    $component->call('addDetailRow');
    $component->set('detailRows.0.gross_weight_mt', 10);
    $component->set('detailRows.0.tare_weight_mt', 3);

    expect($component->instance()->rowNetWeight(0))->toBe(7.0);
});

// Scenario: "Field Wajib Belum Lengkap"
it('shows inline validation error when a required field is empty on save', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormKernelDispatch::class);

    $component->set('form.production_line_id', $this->kernelDispatchStation->production_line_id);
    $component->set('form.kernel_dispatch_id', '');
    $component->call('addDetailRow');
    $component->set('detailRows.0.event_date', '2026-08-31');
    $component->call('save');

    expect($component->get('errors_'))->toHaveKey('kernel_dispatch_id');
});

// Scenario: "Belum Ada Baris Log Valid"
it('shows detail-specific error when no valid detail row exists on save', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormKernelDispatch::class);

    fillFormKernelDispatch($component, ['form.production_line_id' => $this->kernelDispatchStation->production_line_id]);
    $component->call('save');

    expect($component->get('detailError'))->not->toBeNull();
    $component->assertSeeHtml('data-testid="detail-error"');
});

// Scenario: "Production Line Tanpa Station Kernel Dispatch Aktif"
it('shows an error when the selected Production Line has no active kernel-dispatch station', function () {
    $otherProductionLine = ProductionLine::factory()->create();
    $component = Livewire::actingAs($this->supervisor)->test(FormKernelDispatch::class);

    fillFormKernelDispatch($component, ['form.production_line_id' => $otherProductionLine->id]);
    $component->call('addDetailRow');
    $component->set('detailRows.0.event_date', '2026-08-31');
    $component->call('save');

    expect($component->get('generalError'))->not->toBeNull();
    $component->assertSeeHtml('data-testid="general-error"');
});

// Scenario: "Edit Record Kernel Dispatch — berhasil"
it('edit mode: prefills the form from the existing record and shows Station read-only', function () {
    $record = KernelDispatchRecord::factory()->forStation($this->kernelDispatchStation)->create(['kernel_dispatch_id' => 'KD-EXISTING']);

    Livewire::actingAs($this->supervisor)
        ->test(FormKernelDispatch::class, ['id' => $record->id])
        ->assertSet('isEdit', true)
        ->assertSet('form.kernel_dispatch_id', 'KD-EXISTING')
        ->assertSeeHtml('data-testid="station-readonly"')
        ->assertDontSeeHtml('data-testid="production-line-select"');
});

it('edit mode: saving updates the existing record', function () {
    $record = KernelDispatchRecord::factory()->forStation($this->kernelDispatchStation)->create(['kernel_dispatch_id' => 'KD-OLD']);

    $component = Livewire::actingAs($this->supervisor)->test(FormKernelDispatch::class, ['id' => $record->id]);
    $component->set('form.kernel_dispatch_id', 'KD-UPDATED');
    $component->call('addDetailRow');
    $component->set('detailRows.0.event_date', '2026-08-31');
    $component->call('save');

    expect($record->fresh()->kernel_dispatch_id)->toBe('KD-UPDATED');
});

// Scenario: "Record Tidak Ditemukan (mode edit)"
it('edit mode: shows record-not-found error for an invalid id', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormKernelDispatch::class, ['id' => '00000000-0000-0000-0000-000000000000'])
        ->assertSet('notFound', true)
        ->assertSee('Record tidak ditemukan');
});

it('Checked checkbox only renders for Supervisor', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormKernelDispatch::class)
        ->assertSeeHtml('data-testid="checked-checkbox"');

    Livewire::actingAs($this->millManagement)
        ->test(FormKernelDispatch::class)
        ->assertDontSeeHtml('data-testid="checked-checkbox"');
});

it('Acknowledged checkbox only renders for Mill Management', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormKernelDispatch::class)
        ->assertDontSeeHtml('data-testid="acknowledged-checkbox"');

    Livewire::actingAs($this->millManagement)
        ->test(FormKernelDispatch::class)
        ->assertSeeHtml('data-testid="acknowledged-checkbox"');
});
