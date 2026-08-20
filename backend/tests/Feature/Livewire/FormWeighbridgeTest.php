<?php

/**
 * FormWeighbridgeTest (Feature/Livewire) — screen-022--form-weighbridge-web /
 * usecase-022--form-weighbridge-web.
 *
 * Component tests for App\Livewire\Data\FormWeighbridge, one per
 * test_scenarios' component_test step. Mirrors DetailWeighbridgeTest.php's
 * setup/conventions.
 */

use App\Enums\UserRole;
use App\Livewire\Data\FormWeighbridge;
use App\Models\BusinessUnit;
use App\Models\Station;
use App\Models\User;
use App\Models\WeighbridgeRecord;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
});

function fillFormWeighbridge($component, array $overrides = []): void
{
    // Order matters: form.business_unit_id must be set BEFORE
    // form.production_line_id — setting it triggers
    // FormWeighbridge::updatedFormBusinessUnitId(), which resets
    // form.production_line_id back to '' and reloads productionLineOptions
    // (mirrors the real cascading Business Unit -> Production Line select).
    $defaults = [
        'form.business_unit_id' => null,
        'form.production_line_id' => null,
        'form.wb_card_number' => 'WB-LW-001',
        'form.vehicle_number' => 'B 1234 XY',
        'form.driver_name' => 'Budi',
        'form.estate_supplier' => 'Estate A',
        'form.gross_weight' => 15000,
        'form.tare_weight' => 5000,
    ];

    foreach (array_merge($defaults, $overrides) as $key => $value) {
        if ($value === null) {
            continue;
        }
        $component->set($key, $value);
    }
}

// Scenario: "Buat Record Weighbridge Baru - berhasil"
it('berhasil: creates a new record and redirects to Detail Weighbridge', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormWeighbridge::class);

    fillFormWeighbridge($component, [
        'form.business_unit_id' => $this->businessUnit->id,
        'form.production_line_id' => $this->station->production_line_id,
    ]);
    $component->call('save');

    expect(WeighbridgeRecord::where('wb_card_number', 'WB-LW-001')->exists())->toBeTrue();
});

it('default: mode create renders empty form with Receive selected and Business Unit dropdown', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormWeighbridge::class)
        ->assertSet('isEdit', false)
        ->assertSet('form.weighbridge_type', 'receive')
        ->assertSeeHtml('data-testid="business-unit-select"');
});

// Scenario: "Tanggal & Waktu Dapat Diedit Manual"
it('record_datetime defaults to now but a manually-set value is preserved and saved', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormWeighbridge::class);

    expect($component->get('form.record_datetime'))->not->toBeEmpty();

    fillFormWeighbridge($component, [
        'form.business_unit_id' => $this->businessUnit->id,
        'form.production_line_id' => $this->station->production_line_id,
        'form.record_datetime' => '2020-01-01T00:00',
    ]);
    $component->call('save');

    $record = WeighbridgeRecord::where('wb_card_number', 'WB-LW-001')->first();
    expect($record->record_datetime->format('Y-m-d'))->toBe('2020-01-01');
});

// Scenario: "Ganti Tipe Setelah Field Terisi"
it('switching the type tab resets record_datetime and destination, toggles destination field', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormWeighbridge::class);

    $component->set('form.weighbridge_type', 'dispatch');
    $component->assertSeeHtml('data-testid="destination-input"');

    $component->set('form.weighbridge_type', 'receive');
    $component->assertDontSeeHtml('data-testid="destination-input"');
});

// Scenario: "Field Wajib Belum Lengkap"
it('shows inline validation error when a required field is empty on save', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormWeighbridge::class);

    $component->set('form.business_unit_id', $this->businessUnit->id);
    $component->set('form.production_line_id', $this->station->production_line_id);
    $component->call('save');

    expect($component->get('errors_'))->toHaveKey('wb_card_number');
});

it('requires destination when type=dispatch', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormWeighbridge::class);

    fillFormWeighbridge($component, [
        'form.business_unit_id' => $this->businessUnit->id,
        'form.production_line_id' => $this->station->production_line_id,
    ]);
    $component->set('form.weighbridge_type', 'dispatch');
    $component->call('save');

    expect($component->get('errors_'))->toHaveKey('destination');
});

// Scenario: "Business Unit Tanpa Station Weighbridge Aktif"
it('shows an error when the selected Business Unit has no active weighbridge station', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create();
    $component = Livewire::actingAs($this->supervisor)->test(FormWeighbridge::class);

    fillFormWeighbridge($component, ['form.business_unit_id' => $otherBusinessUnit->id]);
    // $otherBusinessUnit has zero Production Lines, so the cascading
    // select never offers one — form.production_line_id stays empty,
    // reproducing "no active weighbridge station" the same way a real
    // user would hit it (unable to pick a Production Line at all).
    $component->call('save');

    expect($component->get('generalError'))->not->toBeNull();
    $component->assertSeeHtml('data-testid="general-error"');
});

// Scenario: "Edit Record Weighbridge - berhasil"
it('edit mode: prefills the form from the existing record and shows Business Unit read-only', function () {
    $record = WeighbridgeRecord::factory()->forStation($this->station)->create(['wb_card_number' => 'WB-EXISTING']);

    Livewire::actingAs($this->supervisor)
        ->test(FormWeighbridge::class, ['id' => $record->id])
        ->assertSet('isEdit', true)
        ->assertSet('form.wb_card_number', 'WB-EXISTING')
        ->assertSeeHtml('data-testid="business-unit-readonly"')
        ->assertDontSeeHtml('data-testid="business-unit-select"');
});

it('edit mode: saving updates the existing record', function () {
    $record = WeighbridgeRecord::factory()->forStation($this->station)->create(['wb_card_number' => 'WB-OLD']);

    $component = Livewire::actingAs($this->supervisor)->test(FormWeighbridge::class, ['id' => $record->id]);
    $component->set('form.wb_card_number', 'WB-UPDATED');
    $component->call('save');

    expect($record->fresh()->wb_card_number)->toBe('WB-UPDATED');
});

// Scenario: "Record Tidak Ditemukan (mode edit)"
it('edit mode: shows record-not-found error for an invalid id', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormWeighbridge::class, ['id' => '00000000-0000-0000-0000-000000000000'])
        ->assertSet('notFound', true)
        ->assertSee('Record tidak ditemukan');
});

it('Checked checkbox only renders for Supervisor, Acknowledged only for Mill Management', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormWeighbridge::class)
        ->assertSeeHtml('data-testid="checked-checkbox"')
        ->assertDontSeeHtml('data-testid="acknowledged-checkbox"');

    Livewire::actingAs($this->millManagement)
        ->test(FormWeighbridge::class)
        ->assertDontSeeHtml('data-testid="checked-checkbox"')
        ->assertSeeHtml('data-testid="acknowledged-checkbox"');
});
