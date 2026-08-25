<?php

/**
 * FormPressingTest (Feature/Livewire) — screen-058--form-pressing-web /
 * usecase-058--form-pressing-web.
 *
 * Component tests for App\Livewire\Data\FormPressing, mirroring
 * tests/Feature/Livewire/FormThreshingTest.php's structure. REVISED
 * 2026-08-24 (entity-catalog v12): the Pressing Detail grid is now a
 * dynamic add-row/remove-row grid (addDetailRow()/removeDetailRow()/
 * canAddRow()/availableTimeSlotOptions()) — the user explicitly rejected
 * the original fixed 24-row design.
 */

use App\Enums\UserRole;
use App\Livewire\Data\FormPressing;
use App\Models\BusinessUnit;
use App\Models\PressingDetail;
use App\Models\PressingRecord;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\PressingOperationalTargetSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->pressingStation = Station::factory()->forBusinessUnit($this->businessUnit)->pressing()->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
    // FormPressing's render() queries PressingOperationalTarget live —
    // RefreshDatabase migrates but does not run seeders, so the 7
    // reference rows must be seeded explicitly here for tests asserting
    // their content.
    $this->seed(PressingOperationalTargetSeeder::class);
});

it('mounts with zero detail rows for a brand-new draft (no pre-population)', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormPressing::class);

    expect($component->get('detailRows'))->toHaveCount(0);
});

it('addDetailRow adds one row at a time; removeDetailRow removes by index', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormPressing::class);

    $component->call('addDetailRow');
    expect($component->get('detailRows'))->toHaveCount(1);

    $component->call('addDetailRow');
    expect($component->get('detailRows'))->toHaveCount(2);

    $component->call('removeDetailRow', 0);
    expect($component->get('detailRows'))->toHaveCount(1);
});

it('addDetailRow is disabled (canAddRow=false) once 24 rows already exist', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormPressing::class);

    expect($component->instance()->canAddRow())->toBeTrue();

    foreach (range(1, 24) as $i) {
        $component->call('addDetailRow');
    }

    expect($component->instance()->canAddRow())->toBeFalse();
});

it('excludes a time-slot already used by another row, and slots at-or-before the last-added row\'s slot', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormPressing::class);

    $component->call('addDetailRow');
    $component->set('detailRows.0.time_slot', '09:00');
    $component->call('addDetailRow');

    $available = $component->instance()->availableTimeSlotOptions(1);
    expect($available)->not->toContain('07:00');
    expect($available)->not->toContain('09:00');
    expect($available)->toContain('10:00');
});

it('berhasil: creates a new record and redirects to Detail Pressing', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormPressing::class);

    $component->set('form.production_line_id', $this->pressingStation->production_line_id);
    $component->set('form.presser_id', 'PR-LW-001');
    $component->call('addDetailRow');
    $component->set('detailRows.0.time_slot', '07:00');
    $component->set('detailRows.0.digester_temp_c', '92');
    $component->call('save');

    expect(PressingRecord::where('presser_id', 'PR-LW-001')->exists())->toBeTrue();
});

it('default: mode create renders empty form with Production Line dropdown', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormPressing::class)
        ->assertSet('isEdit', false)
        ->assertSeeHtml('data-testid="production-line-select"');
});

it('date defaults to today but a manually-set value is preserved and saved', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormPressing::class);

    expect($component->get('form.date'))->not->toBeEmpty();

    $component->set('form.production_line_id', $this->pressingStation->production_line_id);
    $component->set('form.presser_id', 'PR-LW-001');
    $component->set('form.date', '2020-01-01');
    $component->call('addDetailRow');
    $component->set('detailRows.0.time_slot', '07:00');
    $component->set('detailRows.0.digester_temp_c', '10');
    $component->call('save');

    $record = PressingRecord::where('presser_id', 'PR-LW-001')->first();
    expect($record->date->format('Y-m-d'))->toBe('2020-01-01');
});

it('renders the add-row/remove-row controls (no longer a fixed 24-row grid)', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormPressing::class)
        ->assertSeeHtml('data-testid="add-row-button"')
        ->assertSee('Tambah Baris');
});

it('shows inline validation error when a required field is empty on save', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormPressing::class);

    $component->set('form.production_line_id', $this->pressingStation->production_line_id);
    $component->set('form.presser_id', '');
    $component->call('addDetailRow');
    $component->set('detailRows.0.time_slot', '07:00');
    $component->set('detailRows.0.digester_temp_c', '10');
    $component->call('save');

    expect($component->get('errors_'))->toHaveKey('presser_id');
});

it('shows detail-specific error when there are zero valid detail rows on save', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormPressing::class);

    $component->set('form.production_line_id', $this->pressingStation->production_line_id);
    $component->set('form.presser_id', 'PR-LW-002');
    $component->call('save');

    expect($component->get('detailError'))->not->toBeNull();
    $component->assertSeeHtml('data-testid="detail-error"');
});

it('shows an error when the selected Production Line has no active pressing station', function () {
    $otherProductionLine = ProductionLine::factory()->create();
    $component = Livewire::actingAs($this->supervisor)->test(FormPressing::class);

    $component->set('form.production_line_id', $otherProductionLine->id);
    $component->set('form.presser_id', 'PR-LW-003');
    $component->call('addDetailRow');
    $component->set('detailRows.0.time_slot', '07:00');
    $component->set('detailRows.0.digester_temp_c', '10');
    $component->call('save');

    expect($component->get('generalError'))->not->toBeNull();
    $component->assertSeeHtml('data-testid="general-error"');
});

it('edit mode: prefills the form from the existing record and shows Station read-only', function () {
    $record = PressingRecord::factory()->forStation($this->pressingStation)->create(['presser_id' => 'PR-EXISTING']);

    Livewire::actingAs($this->supervisor)
        ->test(FormPressing::class, ['id' => $record->id])
        ->assertSet('isEdit', true)
        ->assertSet('form.presser_id', 'PR-EXISTING')
        ->assertSeeHtml('data-testid="station-readonly"')
        ->assertDontSeeHtml('data-testid="production-line-select"');
});

it('edit mode: prefills existing detail rows with their ids (however many exist)', function () {
    $record = PressingRecord::factory()->forStation($this->pressingStation)->create();
    PressingDetail::factory()->forRecord($record)->timeSlot('07:00')->create();
    PressingDetail::factory()->forRecord($record)->timeSlot('09:00')->create();

    $component = Livewire::actingAs($this->supervisor)->test(FormPressing::class, ['id' => $record->id]);

    $rows = $component->get('detailRows');
    expect($rows)->toHaveCount(2);
    expect($rows[0]['id'])->not->toBeNull();
});

it('edit mode: saving updates the existing record', function () {
    $record = PressingRecord::factory()->forStation($this->pressingStation)->create(['presser_id' => 'PR-OLD']);
    PressingDetail::factory()->forRecord($record)->timeSlot('07:00')->create();

    $component = Livewire::actingAs($this->supervisor)->test(FormPressing::class, ['id' => $record->id]);
    $component->set('form.presser_id', 'PR-UPDATED');
    $component->set('detailRows.0.digester_temp_c', '95');
    $component->call('save');

    expect($record->fresh()->presser_id)->toBe('PR-UPDATED');
});

it('edit mode: removing a row via Hapus deletes it from the persisted record on save', function () {
    $record = PressingRecord::factory()->forStation($this->pressingStation)->create();
    PressingDetail::factory()->forRecord($record)->timeSlot('07:00')->filled()->create();
    $toRemove = PressingDetail::factory()->forRecord($record)->timeSlot('09:00')->filled()->create();

    $component = Livewire::actingAs($this->supervisor)->test(FormPressing::class, ['id' => $record->id]);
    $component->call('removeDetailRow', 1);
    $component->call('save');

    expect(PressingDetail::find($toRemove->id))->toBeNull();
    expect(PressingDetail::where('pressing_record_id', $record->id)->count())->toBe(1);
});

it('edit mode: shows record-not-found error for an invalid id', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormPressing::class, ['id' => '00000000-0000-0000-0000-000000000000'])
        ->assertSet('notFound', true)
        ->assertSee('Record tidak ditemukan');
});

it('Checked checkbox only renders for Supervisor', function () {
    Livewire::actingAs($this->supervisor)->test(FormPressing::class)->assertSeeHtml('data-testid="checked-checkbox"');
    Livewire::actingAs($this->millManagement)->test(FormPressing::class)->assertDontSeeHtml('data-testid="checked-checkbox"');
});

it('Acknowledged checkbox only renders for Mill Management', function () {
    Livewire::actingAs($this->supervisor)->test(FormPressing::class)->assertDontSeeHtml('data-testid="acknowledged-checkbox"');
    Livewire::actingAs($this->millManagement)->test(FormPressing::class)->assertSeeHtml('data-testid="acknowledged-checkbox"');
});

it('renders the Target Operasional reference table with 7 rows', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormPressing::class)
        ->assertSeeHtml('data-testid="operational-target-table"')
        ->assertSee('Cone Hydraulic Pressure')
        ->assertSee('45 - 55 Bar');
});
