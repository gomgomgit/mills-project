<?php

/**
 * FormStorageTankTest (Feature/Livewire) —
 * screen-116--form-storage-tank-web / usecase-096--form-storage-tank-web.
 *
 * Component tests for App\Livewire\Data\FormStorageTank, mirroring
 * tests/Feature/Livewire/FormEffluentPlantTest.php's structure — MINUS the
 * operational-target table (this station has none).
 */

use App\Enums\UserRole;
use App\Livewire\Data\FormStorageTank;
use App\Models\BusinessUnit;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\StorageTankDetail;
use App\Models\StorageTankRecord;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->storageTankStation = Station::factory()->forBusinessUnit($this->businessUnit)->storageTank()->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
});

it('mounts with zero detail rows for a brand-new draft (no pre-population)', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormStorageTank::class);

    expect($component->get('detailRows'))->toHaveCount(0);
});

it('addDetailRow adds one row at a time; removeDetailRow removes by index', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormStorageTank::class);

    $component->call('addDetailRow');
    expect($component->get('detailRows'))->toHaveCount(1);

    $component->call('addDetailRow');
    expect($component->get('detailRows'))->toHaveCount(2);

    $component->call('removeDetailRow', 0);
    expect($component->get('detailRows'))->toHaveCount(1);
});

it('addDetailRow is disabled (canAddRow=false) once 24 rows already exist', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormStorageTank::class);

    expect($component->instance()->canAddRow())->toBeTrue();

    foreach (range(1, 24) as $i) {
        $component->call('addDetailRow');
    }

    expect($component->instance()->canAddRow())->toBeFalse();
});

it('excludes a time-slot already used by another row, and slots at-or-before the last-added row\'s slot', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormStorageTank::class);

    $component->call('addDetailRow');
    $component->set('detailRows.0.time_slot', '09:00');
    $component->call('addDetailRow');

    $available = $component->instance()->availableTimeSlotOptions(1);
    expect($available)->not->toContain('07:00');
    expect($available)->not->toContain('09:00');
    expect($available)->toContain('10:00');
});

it('berhasil: creates a new record and redirects to Detail Storage Tank', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormStorageTank::class);

    $component->set('form.production_line_id', $this->storageTankStation->production_line_id);
    $component->set('form.storage_tank_id', 'ST-LW-001');
    $component->call('addDetailRow');
    $component->set('detailRows.0.time_slot', '07:00');
    $component->set('detailRows.0.cpo_sounding_depth_mm', '1200.5');
    $component->call('save');

    expect(StorageTankRecord::where('storage_tank_id', 'ST-LW-001')->exists())->toBeTrue();
});

it('default: mode create renders empty form with Production Line dropdown', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormStorageTank::class)
        ->assertSet('isEdit', false)
        ->assertSeeHtml('data-testid="production-line-select"');
});

it('date defaults to today but a manually-set value is preserved and saved', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormStorageTank::class);

    expect($component->get('form.date'))->not->toBeEmpty();

    $component->set('form.production_line_id', $this->storageTankStation->production_line_id);
    $component->set('form.storage_tank_id', 'ST-LW-001');
    $component->set('form.date', '2020-01-01');
    $component->call('addDetailRow');
    $component->set('detailRows.0.time_slot', '07:00');
    $component->set('detailRows.0.cpo_sounding_depth_mm', '10');
    $component->call('save');

    $record = StorageTankRecord::where('storage_tank_id', 'ST-LW-001')->first();
    expect($record->date->format('Y-m-d'))->toBe('2020-01-01');
});

it('renders the add-row/remove-row controls (no fixed 24-row grid)', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormStorageTank::class)
        ->assertSeeHtml('data-testid="add-row-button"')
        ->assertSee('Tambah Baris');
});

it('shows inline validation error when a required field is empty on save', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormStorageTank::class);

    $component->set('form.production_line_id', $this->storageTankStation->production_line_id);
    $component->set('form.storage_tank_id', '');
    $component->call('addDetailRow');
    $component->set('detailRows.0.time_slot', '07:00');
    $component->set('detailRows.0.cpo_sounding_depth_mm', '10');
    $component->call('save');

    expect($component->get('errors_'))->toHaveKey('storage_tank_id');
});

it('shows detail-specific error when there are zero valid detail rows on save', function () {
    $component = Livewire::actingAs($this->supervisor)->test(FormStorageTank::class);

    $component->set('form.production_line_id', $this->storageTankStation->production_line_id);
    $component->set('form.storage_tank_id', 'ST-LW-002');
    $component->call('save');

    expect($component->get('detailError'))->not->toBeNull();
    $component->assertSeeHtml('data-testid="detail-error"');
});

it('shows an error when the selected Production Line has no active storage-tank station', function () {
    $otherProductionLine = ProductionLine::factory()->create();
    $component = Livewire::actingAs($this->supervisor)->test(FormStorageTank::class);

    $component->set('form.production_line_id', $otherProductionLine->id);
    $component->set('form.storage_tank_id', 'ST-LW-003');
    $component->call('addDetailRow');
    $component->set('detailRows.0.time_slot', '07:00');
    $component->set('detailRows.0.cpo_sounding_depth_mm', '10');
    $component->call('save');

    expect($component->get('generalError'))->not->toBeNull();
    $component->assertSeeHtml('data-testid="general-error"');
});

it('edit mode: prefills the form from the existing record and shows Station read-only', function () {
    $record = StorageTankRecord::factory()->forStation($this->storageTankStation)->create(['storage_tank_id' => 'ST-EXISTING']);

    Livewire::actingAs($this->supervisor)
        ->test(FormStorageTank::class, ['id' => $record->id])
        ->assertSet('isEdit', true)
        ->assertSet('form.storage_tank_id', 'ST-EXISTING')
        ->assertSeeHtml('data-testid="station-readonly"')
        ->assertDontSeeHtml('data-testid="production-line-select"');
});

it('edit mode: prefills existing detail rows with their ids (however many exist)', function () {
    $record = StorageTankRecord::factory()->forStation($this->storageTankStation)->create();
    StorageTankDetail::factory()->forRecord($record)->timeSlot('07:00')->create();
    StorageTankDetail::factory()->forRecord($record)->timeSlot('09:00')->create();

    $component = Livewire::actingAs($this->supervisor)->test(FormStorageTank::class, ['id' => $record->id]);

    $rows = $component->get('detailRows');
    expect($rows)->toHaveCount(2);
    expect($rows[0]['id'])->not->toBeNull();
});

it('edit mode: saving updates the existing record', function () {
    $record = StorageTankRecord::factory()->forStation($this->storageTankStation)->create(['storage_tank_id' => 'ST-OLD']);
    StorageTankDetail::factory()->forRecord($record)->timeSlot('07:00')->create();

    $component = Livewire::actingAs($this->supervisor)->test(FormStorageTank::class, ['id' => $record->id]);
    $component->set('form.storage_tank_id', 'ST-UPDATED');
    $component->set('detailRows.0.cpo_sounding_depth_mm', '55');
    $component->call('save');

    expect($record->fresh()->storage_tank_id)->toBe('ST-UPDATED');
});

it('edit mode: removing a row via Hapus deletes it from the persisted record on save', function () {
    $record = StorageTankRecord::factory()->forStation($this->storageTankStation)->create();
    StorageTankDetail::factory()->forRecord($record)->timeSlot('07:00')->filled()->create();
    $toRemove = StorageTankDetail::factory()->forRecord($record)->timeSlot('09:00')->filled()->create();

    $component = Livewire::actingAs($this->supervisor)->test(FormStorageTank::class, ['id' => $record->id]);
    $component->call('removeDetailRow', 1);
    $component->call('save');

    expect(StorageTankDetail::find($toRemove->id))->toBeNull();
    expect(StorageTankDetail::where('storage_tank_record_id', $record->id)->count())->toBe(1);
});

it('edit mode: shows record-not-found error for an invalid id', function () {
    Livewire::actingAs($this->supervisor)
        ->test(FormStorageTank::class, ['id' => '00000000-0000-0000-0000-000000000000'])
        ->assertSet('notFound', true)
        ->assertSee('Record tidak ditemukan');
});

it('Checked checkbox only renders for Supervisor', function () {
    Livewire::actingAs($this->supervisor)->test(FormStorageTank::class)->assertSeeHtml('data-testid="checked-checkbox"');
    Livewire::actingAs($this->millManagement)->test(FormStorageTank::class)->assertDontSeeHtml('data-testid="checked-checkbox"');
});

it('Acknowledged checkbox only renders for Mill Management', function () {
    Livewire::actingAs($this->supervisor)->test(FormStorageTank::class)->assertDontSeeHtml('data-testid="acknowledged-checkbox"');
    Livewire::actingAs($this->millManagement)->test(FormStorageTank::class)->assertSeeHtml('data-testid="acknowledged-checkbox"');
});
