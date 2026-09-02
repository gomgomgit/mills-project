<?php

/**
 * FormSterilizerTest (Feature/Livewire) —
 * screen-126--form-sterilizer-web /
 * usecase-126--form-sterilizer-web.
 *
 * Component tests for App\Livewire\Data\FormSterilizer. Mirrors
 * FormCpoDispatchTest.php's structure, minus the grid/N-column and
 * time-slot-ordering concerns. This is the FINAL station of this project.
 */

use App\Enums\UserRole;
use App\Livewire\Data\FormSterilizer;
use App\Models\BusinessUnit;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\SterilizerRecord;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->sterilizerStation = Station::factory()->forBusinessUnit($this->businessUnit)->sterilizer()->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
});

function fillFormSterilizer($component, array $overrides = []): void
{
    $defaults = [
        'form.production_line_id' => null,
        'form.sterilizer_id' => 'STR-LW-001',
    ];

    foreach (array_merge($defaults, $overrides) as $key => $value) {
        if ($value === null) {
            continue;
        }
        $component->set($key, $value);
    }
}

// Scenario: "Buat Record Sterilizer Baru — berhasil"
it('berhasil: creates a new record and redirects to Detail Sterilizer', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormSterilizer::class);

    fillFormSterilizer($component, ['form.production_line_id' => $this->sterilizerStation->production_line_id]);
    $component->call('addDetailRow');
    $component->set('detailRows.0.close_door_time', '07:00');
    $component->call('save');

    expect(SterilizerRecord::where('sterilizer_id', 'STR-LW-001')->exists())->toBeTrue();
});

it('default: mode create renders empty form with Production Line dropdown', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormSterilizer::class)
        ->assertSet('isEdit', false)
        ->assertSeeHtml('data-testid="production-line-select"');
});

it('rowDurationMinutes computes open minus close reactively as the row is filled in', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormSterilizer::class);

    $component->call('addDetailRow');
    $component->set('detailRows.0.close_door_time', '07:00');
    $component->set('detailRows.0.open_door_time', '08:10');

    expect($component->instance()->rowDurationMinutes(0))->toBe(70);
});

// Scenario: "Field Wajib Belum Lengkap"
it('shows inline validation error when a required field is empty on save', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormSterilizer::class);

    $component->set('form.production_line_id', $this->sterilizerStation->production_line_id);
    $component->set('form.sterilizer_id', '');
    $component->call('addDetailRow');
    $component->set('detailRows.0.close_door_time', '07:00');
    $component->call('save');

    expect($component->get('errors_'))->toHaveKey('sterilizer_id');
});

// Scenario: "Belum Ada Baris Log Valid"
it('shows detail-specific error when no valid detail row exists on save', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormSterilizer::class);

    fillFormSterilizer($component, ['form.production_line_id' => $this->sterilizerStation->production_line_id]);
    $component->call('save');

    expect($component->get('detailError'))->not->toBeNull();
    $component->assertSeeHtml('data-testid="detail-error"');
});

// Scenario: "Production Line Tanpa Station Sterilizer Aktif"
it('shows an error when the selected Production Line has no active sterilizer station', function () {
    $otherProductionLine = ProductionLine::factory()->create();
    $component = Livewire::actingAs($this->supervisor)->test(FormSterilizer::class);

    fillFormSterilizer($component, ['form.production_line_id' => $otherProductionLine->id]);
    $component->call('addDetailRow');
    $component->set('detailRows.0.close_door_time', '07:00');
    $component->call('save');

    expect($component->get('generalError'))->not->toBeNull();
    $component->assertSeeHtml('data-testid="general-error"');
});

// Scenario: "Edit Record Sterilizer — berhasil"
it('edit mode: prefills the form from the existing record and shows Station read-only', function () {
    $record = SterilizerRecord::factory()->forStation($this->sterilizerStation)->create(['sterilizer_id' => 'STR-EXISTING']);

    Livewire::actingAs($this->supervisor)
        ->test(FormSterilizer::class, ['id' => $record->id])
        ->assertSet('isEdit', true)
        ->assertSet('form.sterilizer_id', 'STR-EXISTING')
        ->assertSeeHtml('data-testid="station-readonly"')
        ->assertDontSeeHtml('data-testid="production-line-select"');
});

it('edit mode: saving updates the existing record', function () {
    $record = SterilizerRecord::factory()->forStation($this->sterilizerStation)->create(['sterilizer_id' => 'STR-OLD']);

    $component = Livewire::actingAs($this->supervisor)->test(FormSterilizer::class, ['id' => $record->id]);
    $component->set('form.sterilizer_id', 'STR-UPDATED');
    $component->call('addDetailRow');
    $component->set('detailRows.0.close_door_time', '07:00');
    $component->call('save');

    expect($record->fresh()->sterilizer_id)->toBe('STR-UPDATED');
});

// Scenario: "Record Tidak Ditemukan (mode edit)"
it('edit mode: shows record-not-found error for an invalid id', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormSterilizer::class, ['id' => '00000000-0000-0000-0000-000000000000'])
        ->assertSet('notFound', true)
        ->assertSee('Record tidak ditemukan');
});

it('Checked checkbox only renders for Supervisor', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormSterilizer::class)
        ->assertSeeHtml('data-testid="checked-checkbox"');

    Livewire::actingAs($this->millManagement)
        ->test(FormSterilizer::class)
        ->assertDontSeeHtml('data-testid="checked-checkbox"');
});

it('Acknowledged checkbox only renders for Mill Management', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormSterilizer::class)
        ->assertDontSeeHtml('data-testid="acknowledged-checkbox"');

    Livewire::actingAs($this->millManagement)
        ->test(FormSterilizer::class)
        ->assertSeeHtml('data-testid="acknowledged-checkbox"');
});
