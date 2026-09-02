<?php

/**
 * FormCpoDispatchTest (Feature/Livewire) —
 * screen-114--form-cpo-dispatch-web /
 * usecase-084--form-cpo-dispatch-web.
 *
 * Component tests for App\Livewire\Data\FormCpoDispatch. Mirrors
 * FormKernelDispatchTest.php's structure, minus the grid/N-column and
 * time-slot-ordering concerns.
 */

use App\Enums\UserRole;
use App\Livewire\Data\FormCpoDispatch;
use App\Models\BusinessUnit;
use App\Models\CpoDispatchRecord;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->cpoDispatchStation = Station::factory()->forBusinessUnit($this->businessUnit)->cpoDispatch()->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
});

function fillFormCpoDispatch($component, array $overrides = []): void
{
    $defaults = [
        'form.production_line_id' => null,
        'form.cpo_dispatch_id' => 'CD-LW-001',
    ];

    foreach (array_merge($defaults, $overrides) as $key => $value) {
        if ($value === null) {
            continue;
        }
        $component->set($key, $value);
    }
}

// Scenario: "Buat Record CPO Dispatch Baru — berhasil"
it('berhasil: creates a new record and redirects to Detail CPO Dispatch', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormCpoDispatch::class);

    fillFormCpoDispatch($component, ['form.production_line_id' => $this->cpoDispatchStation->production_line_id]);
    $component->call('addDetailRow');
    $component->set('detailRows.0.event_date', '2026-08-31');
    $component->call('save');

    expect(CpoDispatchRecord::where('cpo_dispatch_id', 'CD-LW-001')->exists())->toBeTrue();
});

it('default: mode create renders empty form with Production Line dropdown', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormCpoDispatch::class)
        ->assertSet('isEdit', false)
        ->assertSeeHtml('data-testid="production-line-select"');
});

it('rowNetWeight computes gross minus tare reactively as the row is filled in', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormCpoDispatch::class);

    $component->call('addDetailRow');
    $component->set('detailRows.0.gross_weight_mt', 10);
    $component->set('detailRows.0.tare_weight_mt', 3);

    expect($component->instance()->rowNetWeight(0))->toBe(7.0);
});

// Scenario: "Field Wajib Belum Lengkap"
it('shows inline validation error when a required field is empty on save', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormCpoDispatch::class);

    $component->set('form.production_line_id', $this->cpoDispatchStation->production_line_id);
    $component->set('form.cpo_dispatch_id', '');
    $component->call('addDetailRow');
    $component->set('detailRows.0.event_date', '2026-08-31');
    $component->call('save');

    expect($component->get('errors_'))->toHaveKey('cpo_dispatch_id');
});

// Scenario: "Belum Ada Baris Log Valid"
it('shows detail-specific error when no valid detail row exists on save', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormCpoDispatch::class);

    fillFormCpoDispatch($component, ['form.production_line_id' => $this->cpoDispatchStation->production_line_id]);
    $component->call('save');

    expect($component->get('detailError'))->not->toBeNull();
    $component->assertSeeHtml('data-testid="detail-error"');
});

// Scenario: "Production Line Tanpa Station CPO Dispatch Aktif"
it('shows an error when the selected Production Line has no active cpo-dispatch station', function () {
    $otherProductionLine = ProductionLine::factory()->create();
    $component = Livewire::actingAs($this->supervisor)->test(FormCpoDispatch::class);

    fillFormCpoDispatch($component, ['form.production_line_id' => $otherProductionLine->id]);
    $component->call('addDetailRow');
    $component->set('detailRows.0.event_date', '2026-08-31');
    $component->call('save');

    expect($component->get('generalError'))->not->toBeNull();
    $component->assertSeeHtml('data-testid="general-error"');
});

// Scenario: "Edit Record CPO Dispatch — berhasil"
it('edit mode: prefills the form from the existing record and shows Station read-only', function () {
    $record = CpoDispatchRecord::factory()->forStation($this->cpoDispatchStation)->create(['cpo_dispatch_id' => 'CD-EXISTING']);

    Livewire::actingAs($this->supervisor)
        ->test(FormCpoDispatch::class, ['id' => $record->id])
        ->assertSet('isEdit', true)
        ->assertSet('form.cpo_dispatch_id', 'CD-EXISTING')
        ->assertSeeHtml('data-testid="station-readonly"')
        ->assertDontSeeHtml('data-testid="production-line-select"');
});

it('edit mode: saving updates the existing record', function () {
    $record = CpoDispatchRecord::factory()->forStation($this->cpoDispatchStation)->create(['cpo_dispatch_id' => 'CD-OLD']);

    $component = Livewire::actingAs($this->supervisor)->test(FormCpoDispatch::class, ['id' => $record->id]);
    $component->set('form.cpo_dispatch_id', 'CD-UPDATED');
    $component->call('addDetailRow');
    $component->set('detailRows.0.event_date', '2026-08-31');
    $component->call('save');

    expect($record->fresh()->cpo_dispatch_id)->toBe('CD-UPDATED');
});

// Scenario: "Record Tidak Ditemukan (mode edit)"
it('edit mode: shows record-not-found error for an invalid id', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormCpoDispatch::class, ['id' => '00000000-0000-0000-0000-000000000000'])
        ->assertSet('notFound', true)
        ->assertSee('Record tidak ditemukan');
});

it('Checked checkbox only renders for Supervisor', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormCpoDispatch::class)
        ->assertSeeHtml('data-testid="checked-checkbox"');

    Livewire::actingAs($this->millManagement)
        ->test(FormCpoDispatch::class)
        ->assertDontSeeHtml('data-testid="checked-checkbox"');
});

it('Acknowledged checkbox only renders for Mill Management', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormCpoDispatch::class)
        ->assertDontSeeHtml('data-testid="acknowledged-checkbox"');

    Livewire::actingAs($this->millManagement)
        ->test(FormCpoDispatch::class)
        ->assertSeeHtml('data-testid="acknowledged-checkbox"');
});
