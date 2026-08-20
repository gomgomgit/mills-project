<?php

/**
 * FormCagesTrackTest (Feature/Livewire) — screen-024--form-cages-track-web /
 * usecase-024--form-cages-track-web.
 *
 * Component tests for App\Livewire\Data\FormCagesTrack, one per
 * test_scenarios' component_test step. Mirrors FormGradingTest.php
 * (screen-023)'s setup/conventions, with the Cages Tipped Time grid
 * (tipped_hour dropdown exclusion + toggleCage() checkbox grid sized to
 * COUNT(machinery WHERE station_id = the production line's active Cages
 * Track station)) layered on top, plus FormWeighbridgeTest.php's dual
 * Checked/Acknowledged checkbox coverage (unlike Grading, which only has
 * Acknowledged).
 */

use App\Enums\UserRole;
use App\Livewire\Data\FormCagesTrack;
use App\Models\BusinessUnit;
use App\Models\CagesTrackRecord;
use App\Models\Machinery;
use App\Models\Station;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->cagesTrackStation = Station::factory()->forBusinessUnit($this->businessUnit)->cagesTrack()->create();
    Machinery::factory()->count(10)->create(['station_id' => $this->cagesTrackStation->id]);
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
});

function fillFormCagesTrack($component, array $overrides = []): void
{
    $defaults = [
        'form.production_line_id' => null,
        'form.cages_track_number' => 'CT-LW-001',
        'form.cages_out' => 12,
        'form.cages_tipped' => 10,
    ];

    foreach (array_merge($defaults, $overrides) as $key => $value) {
        if ($value === null) {
            continue;
        }
        $component->set($key, $value);
    }
}

// Scenario: "Buat Record Cages Track Baru — berhasil"
it('berhasil: creates a new record and redirects to Detail Cages Track', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormCagesTrack::class);

    fillFormCagesTrack($component, ['form.production_line_id' => $this->cagesTrackStation->production_line_id]);
    $component->call('addDetailRow');
    $component->set('detailRows.0.tipped_hour', 8);
    $component->call('toggleCage', 0, 1);
    $component->call('toggleCage', 0, 2);
    $component->call('save');

    expect(CagesTrackRecord::where('cages_track_number', 'CT-LW-001')->exists())->toBeTrue();
});

it('default: mode create renders empty form with Production Line dropdown', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormCagesTrack::class)
        ->assertSet('isEdit', false)
        ->assertSeeHtml('data-testid="production-line-select"');
});

// Scenario: "Tanggal & Tippler Time Dapat Diedit Manual"
it('date/tippler times default to now but a manually-set value is preserved and saved', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormCagesTrack::class);

    expect($component->get('form.date'))->not->toBeEmpty();
    expect($component->get('form.tippler_start_time'))->not->toBeEmpty();
    expect($component->get('form.tippler_stop_time'))->not->toBeEmpty();

    fillFormCagesTrack($component, ['form.production_line_id' => $this->cagesTrackStation->production_line_id]);
    $component->set('form.date', '2020-01-01');
    $component->call('addDetailRow');
    $component->set('detailRows.0.tipped_hour', 8);
    $component->call('toggleCage', 0, 1);
    $component->call('save');

    $record = CagesTrackRecord::where('cages_track_number', 'CT-LW-001')->first();
    expect($record->date->format('Y-m-d'))->toBe('2020-01-01');
});

// Scenario: "Jumlah Kolom Grid Mengikuti Machinery Count, Bukan Cages Tipped Header"
it('resolves jumlahCages from machinery count when Production Line is selected, independent of the cages_tipped header value', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormCagesTrack::class);

    expect($component->get('jumlahCages'))->toBe(0);

    $component->set('form.production_line_id', $this->cagesTrackStation->production_line_id);
    $component->set('form.cages_tipped', 999);

    expect($component->get('jumlahCages'))->toBe(10);
});

// Scenario: "Time Tidak Bisa Duplikat Atau Mundur"
it('excludes hours already used by other rows and hours <= the last-added row\'s hour', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormCagesTrack::class);
    $component->set('form.production_line_id', $this->cagesTrackStation->production_line_id);

    $component->call('addDetailRow');
    $component->set('detailRows.0.tipped_hour', 7);
    $component->call('addDetailRow');

    $available = $component->instance()->availableHourOptions(1);
    expect($available)->not->toContain(7);
    expect($available)->not->toContain(5);
    expect($available)->toContain(8);
});

it('addDetailRow is disabled (canAddRow=false) until a Production Line with jumlahCages>0 is selected', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormCagesTrack::class);

    expect($component->instance()->canAddRow())->toBeFalse();

    $component->set('form.production_line_id', $this->cagesTrackStation->production_line_id);

    expect($component->instance()->canAddRow())->toBeTrue();
});

// Scenario: "Field Wajib Belum Lengkap"
it('shows inline validation error when a required field is empty on save', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormCagesTrack::class);

    $component->set('form.production_line_id', $this->cagesTrackStation->production_line_id);
    $component->set('form.cages_track_number', '');
    $component->call('addDetailRow');
    $component->set('detailRows.0.tipped_hour', 8);
    $component->call('toggleCage', 0, 1);
    $component->call('save');

    expect($component->get('errors_'))->toHaveKey('cages_track_number');
});

// Scenario: "Belum Ada Baris Cages Tipped Time Valid"
it('shows detail-specific error when no valid Cages Tipped Time row exists on save', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormCagesTrack::class);

    fillFormCagesTrack($component, ['form.production_line_id' => $this->cagesTrackStation->production_line_id]);
    $component->call('save');

    expect($component->get('detailError'))->not->toBeNull();
    $component->assertSeeHtml('data-testid="detail-error"');
});

// Scenario: "Business Unit Tanpa Station Cages Track Aktif"
it('shows an error when the selected Production Line has no active cages-track station', function () {
    $otherProductionLine = \App\Models\ProductionLine::factory()->create();
    $component = Livewire::actingAs($this->supervisor)->test(FormCagesTrack::class);

    fillFormCagesTrack($component, ['form.production_line_id' => $otherProductionLine->id]);
    $component->call('addDetailRow');
    $component->set('detailRows.0.tipped_hour', 8);
    $component->call('toggleCage', 0, 1);
    $component->call('save');

    expect($component->get('generalError'))->not->toBeNull();
    $component->assertSeeHtml('data-testid="general-error"');
});

// Scenario: "Edit Record Cages Track — berhasil"
it('edit mode: prefills the form from the existing record and shows Business Unit read-only', function () {
    $record = CagesTrackRecord::factory()->forStation($this->cagesTrackStation)->create(['cages_track_number' => 'CT-EXISTING']);

    Livewire::actingAs($this->supervisor)
        ->test(FormCagesTrack::class, ['id' => $record->id])
        ->assertSet('isEdit', true)
        ->assertSet('form.cages_track_number', 'CT-EXISTING')
        ->assertSet('jumlahCages', 10)
        ->assertSeeHtml('data-testid="business-unit-readonly"')
        ->assertDontSeeHtml('data-testid="production-line-select"');
});

it('edit mode: saving updates the existing record', function () {
    $record = CagesTrackRecord::factory()->forStation($this->cagesTrackStation)->create(['cages_track_number' => 'CT-OLD']);

    $component = Livewire::actingAs($this->supervisor)->test(FormCagesTrack::class, ['id' => $record->id]);
    $component->set('form.cages_track_number', 'CT-UPDATED');
    $component->call('addDetailRow');
    $component->set('detailRows.0.tipped_hour', 8);
    $component->call('toggleCage', 0, 1);
    $component->call('save');

    expect($record->fresh()->cages_track_number)->toBe('CT-UPDATED');
});

// Scenario: "Record Tidak Ditemukan (mode edit)"
it('edit mode: shows record-not-found error for an invalid id', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormCagesTrack::class, ['id' => '00000000-0000-0000-0000-000000000000'])
        ->assertSet('notFound', true)
        ->assertSee('Record tidak ditemukan');
});

it('Checked checkbox only renders for Supervisor', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormCagesTrack::class)
        ->assertSeeHtml('data-testid="checked-checkbox"');

    Livewire::actingAs($this->millManagement)
        ->test(FormCagesTrack::class)
        ->assertDontSeeHtml('data-testid="checked-checkbox"');
});

it('Acknowledged checkbox only renders for Mill Management', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormCagesTrack::class)
        ->assertDontSeeHtml('data-testid="acknowledged-checkbox"');

    Livewire::actingAs($this->millManagement)
        ->test(FormCagesTrack::class)
        ->assertSeeHtml('data-testid="acknowledged-checkbox"');
});
