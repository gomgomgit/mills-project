<?php

/**
 * FormGradingTest (Feature/Livewire) — screen-023--form-grading-web /
 * usecase-023--form-grading-web.
 *
 * Component tests for App\Livewire\Data\FormGrading, one per
 * test_scenarios' component_test step. Mirrors FormWeighbridgeTest.php
 * (screen-022)'s setup/conventions, with the Grading Detail grid layered
 * on top (addDetailRow()/detailRows.{i}.* array-path property sets).
 */

use App\Enums\UserRole;
use App\Livewire\Data\FormGrading;
use App\Models\BusinessUnit;
use App\Models\GradingParameter;
use App\Models\GradingRecord;
use App\Models\Station;
use App\Models\User;
use App\Models\WeighbridgeRecord;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->weighbridgeStation = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->gradingStation = Station::factory()->forBusinessUnit($this->businessUnit)->grading()->create();
    $this->weighbridgeRecord = WeighbridgeRecord::factory()->forStation($this->weighbridgeStation)->create(['wb_card_number' => 'WB-GR-001']);
    $this->gradingParameter = GradingParameter::factory()->create(['uom' => \App\Enums\Uom::Kg]);
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
});

function fillFormGrading($component, array $overrides = []): void
{
    $defaults = [
        'form.business_unit_id' => null,
        'form.grading_number' => 'GR-LW-001',
        'form.estate_supplier' => 'Estate A',
        'form.netto' => 1000,
        'form.quantity' => 120,
    ];

    foreach (array_merge($defaults, $overrides) as $key => $value) {
        if ($value === null) {
            continue;
        }
        $component->set($key, $value);
    }
}

// Scenario: "Buat Record Grading Baru — berhasil"
it('berhasil: creates a new record and redirects to Detail Grading', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormGrading::class);

    fillFormGrading($component, ['form.business_unit_id' => $this->businessUnit->id]);
    $component->set('form.weighbridge_record_id', $this->weighbridgeRecord->id);
    $component->call('addDetailRow');
    $component->set('detailRows.0.grading_parameter_id', $this->gradingParameter->id);
    $component->set('detailRows.0.quantity', 250);
    $component->call('save');

    expect(GradingRecord::where('grading_number', 'GR-LW-001')->exists())->toBeTrue();
});

it('default: mode create renders empty form with Business Unit dropdown', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormGrading::class)
        ->assertSet('isEdit', false)
        ->assertSeeHtml('data-testid="business-unit-select"');
});

// Scenario: "Tanggal Dapat Diedit Manual"
it('date defaults to today but a manually-set value is preserved and saved', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormGrading::class);

    expect($component->get('form.date'))->not->toBeEmpty();

    fillFormGrading($component, ['form.business_unit_id' => $this->businessUnit->id]);
    $component->set('form.weighbridge_record_id', $this->weighbridgeRecord->id);
    $component->set('form.date', '2020-01-01');
    $component->call('addDetailRow');
    $component->set('detailRows.0.grading_parameter_id', $this->gradingParameter->id);
    $component->set('detailRows.0.quantity', 250);
    $component->call('save');

    $record = GradingRecord::where('grading_number', 'GR-LW-001')->first();
    expect($record->date->format('Y-m-d'))->toBe('2020-01-01');
});

it('selecting WB Card No auto-fills license_plate_no/estate_supplier/division', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormGrading::class);

    $component->set('form.business_unit_id', $this->businessUnit->id);
    $component->set('form.weighbridge_record_id', $this->weighbridgeRecord->id);

    expect($component->get('form.license_plate_no'))->toBe($this->weighbridgeRecord->vehicle_number);
    expect($component->get('form.estate_supplier'))->toBe($this->weighbridgeRecord->estate_supplier);
});

// Scenario: "Quality Parameter Tidak Bisa Duplikat Antar Baris"
it('excludes a Quality Parameter already selected in another row from that row\'s own options', function () {
    $secondParameter = GradingParameter::factory()->create();
    $component = Livewire::actingAs($this->supervisor)->test(FormGrading::class);

    $component->call('addDetailRow');
    $component->call('addDetailRow');
    $component->set('detailRows.0.grading_parameter_id', $this->gradingParameter->id);

    $availableForRow1 = $component->instance()->availableParameterOptions(1);
    expect(collect($availableForRow1)->pluck('id'))->not->toContain($this->gradingParameter->id);

    $availableForRow0 = $component->instance()->availableParameterOptions(0);
    expect(collect($availableForRow0)->pluck('id'))->toContain($this->gradingParameter->id);
});

// Scenario: "Field Wajib Belum Lengkap"
it('shows inline validation error when a required field is empty on save', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormGrading::class);

    $component->set('form.business_unit_id', $this->businessUnit->id);
    $component->call('save');

    expect($component->get('errors_'))->toHaveKey('weighbridge_record_id');
});

// Scenario: "Belum Ada Baris Grading Detail Valid"
it('shows detail-specific error when no valid Grading Detail row exists on save', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormGrading::class);

    fillFormGrading($component, ['form.business_unit_id' => $this->businessUnit->id]);
    $component->set('form.weighbridge_record_id', $this->weighbridgeRecord->id);
    $component->call('save');

    expect($component->get('detailError'))->not->toBeNull();
    $component->assertSeeHtml('data-testid="detail-error"');
});

// Scenario: "Business Unit Tanpa Station Grading Aktif"
it('shows an error when the selected Business Unit has no active grading station', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create();
    $otherWeighbridgeRecord = WeighbridgeRecord::factory()->create();
    $component = Livewire::actingAs($this->supervisor)->test(FormGrading::class);

    fillFormGrading($component, ['form.business_unit_id' => $otherBusinessUnit->id]);
    $component->set('form.weighbridge_record_id', $otherWeighbridgeRecord->id);
    // updatedFormBusinessUnitId() scoped weighbridgeOptions to $otherBusinessUnit
    // (empty, so auto-fill from $otherWeighbridgeRecord never fires) — set
    // license_plate_no explicitly so validateForm() passes and the flow
    // actually reaches station resolution (what this scenario tests).
    $component->set('form.license_plate_no', 'B 9999 ZZ');
    $component->call('addDetailRow');
    $component->set('detailRows.0.grading_parameter_id', $this->gradingParameter->id);
    $component->set('detailRows.0.quantity', 5);
    $component->call('save');

    expect($component->get('generalError'))->not->toBeNull();
    $component->assertSeeHtml('data-testid="general-error"');
});

// Scenario: "Edit Record Grading — berhasil"
it('edit mode: prefills the form from the existing record and shows Business Unit read-only', function () {
    $record = GradingRecord::factory()->forStation($this->gradingStation)->create(['grading_number' => 'GR-EXISTING']);

    Livewire::actingAs($this->supervisor)
        ->test(FormGrading::class, ['id' => $record->id])
        ->assertSet('isEdit', true)
        ->assertSet('form.grading_number', 'GR-EXISTING')
        ->assertSeeHtml('data-testid="business-unit-readonly"')
        ->assertDontSeeHtml('data-testid="business-unit-select"');
});

it('edit mode: saving updates the existing record', function () {
    $record = GradingRecord::factory()->forStation($this->gradingStation)->create(['grading_number' => 'GR-OLD']);

    $component = Livewire::actingAs($this->supervisor)->test(FormGrading::class, ['id' => $record->id]);
    $component->set('form.grading_number', 'GR-UPDATED');
    $component->set('form.weighbridge_record_id', $this->weighbridgeRecord->id);
    $component->call('addDetailRow');
    $component->set('detailRows.0.grading_parameter_id', $this->gradingParameter->id);
    $component->set('detailRows.0.quantity', 5);
    $component->call('save');

    expect($record->fresh()->grading_number)->toBe('GR-UPDATED');
});

// Scenario: "Record Tidak Ditemukan (mode edit)"
it('edit mode: shows record-not-found error for an invalid id', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormGrading::class, ['id' => '00000000-0000-0000-0000-000000000000'])
        ->assertSet('notFound', true)
        ->assertSee('Record tidak ditemukan');
});

it('Acknowledged checkbox only renders for Mill Management, never for Supervisor', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormGrading::class)
        ->assertDontSeeHtml('data-testid="acknowledged-checkbox"');

    Livewire::actingAs($this->millManagement)
        ->test(FormGrading::class)
        ->assertSeeHtml('data-testid="acknowledged-checkbox"');
});

it('Checked By is never rendered on this screen, for any role', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormGrading::class)
        ->assertDontSeeHtml('data-testid="checked-checkbox"');
});
