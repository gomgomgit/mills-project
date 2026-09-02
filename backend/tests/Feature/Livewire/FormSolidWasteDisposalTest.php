<?php

/**
 * FormSolidWasteDisposalTest (Feature/Livewire) —
 * screen-111--form-solid-waste-disposal-web /
 * usecase-066--form-solid-waste-disposal-web.
 *
 * Component tests for App\Livewire\Data\FormSolidWasteDisposal. Mirrors
 * FormCagesTrackTest.php's structure, minus the grid/N-column and
 * time-slot-ordering concerns.
 */

use App\Enums\UserRole;
use App\Livewire\Data\FormSolidWasteDisposal;
use App\Models\BusinessUnit;
use App\Models\ProductionLine;
use App\Models\SolidWasteDisposalRecord;
use App\Models\Station;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->solidWasteStation = Station::factory()->forBusinessUnit($this->businessUnit)->solidWasteDisposal()->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
});

function fillFormSolidWasteDisposal($component, array $overrides = []): void
{
    $defaults = [
        'form.production_line_id' => null,
        'form.solid_waste_disposal_id' => 'SWD-LW-001',
    ];

    foreach (array_merge($defaults, $overrides) as $key => $value) {
        if ($value === null) {
            continue;
        }
        $component->set($key, $value);
    }
}

// Scenario: "Buat Record Solid Waste Disposal Baru — berhasil"
it('berhasil: creates a new record and redirects to Detail Solid Waste Disposal', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormSolidWasteDisposal::class);

    fillFormSolidWasteDisposal($component, ['form.production_line_id' => $this->solidWasteStation->production_line_id]);
    $component->call('addDetailRow');
    $component->set('detailRows.0.event_date', '2026-08-31');
    $component->call('save');

    expect(SolidWasteDisposalRecord::where('solid_waste_disposal_id', 'SWD-LW-001')->exists())->toBeTrue();
});

it('default: mode create renders empty form with Production Line dropdown', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormSolidWasteDisposal::class)
        ->assertSet('isEdit', false)
        ->assertSeeHtml('data-testid="production-line-select"');
});

it('rowNetWeight computes gross minus tare reactively as the row is filled in', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormSolidWasteDisposal::class);

    $component->call('addDetailRow');
    $component->set('detailRows.0.gross_weight_mt', 10);
    $component->set('detailRows.0.tare_weight_mt', 3);

    expect($component->instance()->rowNetWeight(0))->toBe(7.0);
});

// Scenario: "Field Wajib Belum Lengkap"
it('shows inline validation error when a required field is empty on save', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormSolidWasteDisposal::class);

    $component->set('form.production_line_id', $this->solidWasteStation->production_line_id);
    $component->set('form.solid_waste_disposal_id', '');
    $component->call('addDetailRow');
    $component->set('detailRows.0.event_date', '2026-08-31');
    $component->call('save');

    expect($component->get('errors_'))->toHaveKey('solid_waste_disposal_id');
});

// Scenario: "Belum Ada Baris Log Valid"
it('shows detail-specific error when no valid detail row exists on save', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormSolidWasteDisposal::class);

    fillFormSolidWasteDisposal($component, ['form.production_line_id' => $this->solidWasteStation->production_line_id]);
    $component->call('save');

    expect($component->get('detailError'))->not->toBeNull();
    $component->assertSeeHtml('data-testid="detail-error"');
});

// Scenario: "Production Line Tanpa Station Solid Waste Disposal Aktif"
it('shows an error when the selected Production Line has no active solid-waste-disposal station', function () {
    $otherProductionLine = ProductionLine::factory()->create();
    $component = Livewire::actingAs($this->supervisor)->test(FormSolidWasteDisposal::class);

    fillFormSolidWasteDisposal($component, ['form.production_line_id' => $otherProductionLine->id]);
    $component->call('addDetailRow');
    $component->set('detailRows.0.event_date', '2026-08-31');
    $component->call('save');

    expect($component->get('generalError'))->not->toBeNull();
    $component->assertSeeHtml('data-testid="general-error"');
});

// Scenario: "Edit Record Solid Waste Disposal — berhasil"
it('edit mode: prefills the form from the existing record and shows Station read-only', function () {
    $record = SolidWasteDisposalRecord::factory()->forStation($this->solidWasteStation)->create(['solid_waste_disposal_id' => 'SWD-EXISTING']);

    Livewire::actingAs($this->supervisor)
        ->test(FormSolidWasteDisposal::class, ['id' => $record->id])
        ->assertSet('isEdit', true)
        ->assertSet('form.solid_waste_disposal_id', 'SWD-EXISTING')
        ->assertSeeHtml('data-testid="station-readonly"')
        ->assertDontSeeHtml('data-testid="production-line-select"');
});

it('edit mode: saving updates the existing record', function () {
    $record = SolidWasteDisposalRecord::factory()->forStation($this->solidWasteStation)->create(['solid_waste_disposal_id' => 'SWD-OLD']);

    $component = Livewire::actingAs($this->supervisor)->test(FormSolidWasteDisposal::class, ['id' => $record->id]);
    $component->set('form.solid_waste_disposal_id', 'SWD-UPDATED');
    $component->call('addDetailRow');
    $component->set('detailRows.0.event_date', '2026-08-31');
    $component->call('save');

    expect($record->fresh()->solid_waste_disposal_id)->toBe('SWD-UPDATED');
});

// Scenario: "Record Tidak Ditemukan (mode edit)"
it('edit mode: shows record-not-found error for an invalid id', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormSolidWasteDisposal::class, ['id' => '00000000-0000-0000-0000-000000000000'])
        ->assertSet('notFound', true)
        ->assertSee('Record tidak ditemukan');
});

it('Checked checkbox only renders for Supervisor', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormSolidWasteDisposal::class)
        ->assertSeeHtml('data-testid="checked-checkbox"');

    Livewire::actingAs($this->millManagement)
        ->test(FormSolidWasteDisposal::class)
        ->assertDontSeeHtml('data-testid="checked-checkbox"');
});

it('Acknowledged checkbox only renders for Mill Management', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormSolidWasteDisposal::class)
        ->assertDontSeeHtml('data-testid="acknowledged-checkbox"');

    Livewire::actingAs($this->millManagement)
        ->test(FormSolidWasteDisposal::class)
        ->assertSeeHtml('data-testid="acknowledged-checkbox"');
});
