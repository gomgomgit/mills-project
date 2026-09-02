<?php

/**
 * DetailStorageTankTest (Feature/Livewire) —
 * screen-106--detail-storage-tank-web / usecase-095--detail-storage-tank-web.
 *
 * Component tests for App\Livewire\Data\DetailStorageTank, mirroring
 * tests/Feature/Livewire/DetailEffluentPlantTest.php's structure — MINUS the
 * operational-target table (this station has none).
 */

use App\Enums\UserRole;
use App\Livewire\Data\DetailStorageTank;
use App\Models\BusinessUnit;
use App\Models\Station;
use App\Models\StorageTankDetail;
use App\Models\StorageTankRecord;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->user = User::factory()->role(UserRole::Supervisor)->create();
});

it('berhasil: renders all header fields and the Storage Tank Detail grid, read-only', function () {
    $record = StorageTankRecord::factory()->forStation($this->station)->create();
    StorageTankDetail::factory()->forRecord($record)->timeSlot('10:00')->create(['cpo_sounding_depth_mm' => 1242.5]);

    Livewire::actingAs($this->user)
        ->test(DetailStorageTank::class, ['id' => $record->id])
        ->assertSee($record->storage_tank_id)
        ->assertSee('1242.5')
        ->assertDontSee('Record tidak ditemukan');
});

it('shows Checked By and Acknowledged By both', function () {
    $checker = User::factory()->role(UserRole::Supervisor)->create(['name' => 'Checker Person']);
    $acknowledger = User::factory()->role(UserRole::MillManagement)->create(['name' => 'Acknowledger Person']);
    $record = StorageTankRecord::factory()->forStation($this->station)->create([
        'checked_by' => $checker->id,
        'acknowledged_by' => $acknowledger->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(DetailStorageTank::class, ['id' => $record->id])
        ->assertSee('Checker Person')
        ->assertSee('Acknowledger Person');
});

it('Record Tidak Ditemukan: shows an error message with a Back button', function () {
    Livewire::actingAs($this->user)
        ->test(DetailStorageTank::class, ['id' => '00000000-0000-0000-0000-000000000000'])
        ->assertSet('notFound', true)
        ->assertSee('Record tidak ditemukan')
        ->assertSeeHtml('data-testid="back-button"');
});

it('Back button links to the Data Browser Storage Tank route', function () {
    $record = StorageTankRecord::factory()->forStation($this->station)->create();

    Livewire::actingAs($this->user)
        ->test(DetailStorageTank::class, ['id' => $record->id])
        ->assertSeeHtml(route('data.storage-tank'));
});

it('shows an empty-state row when the record has zero detail rows', function () {
    $record = StorageTankRecord::factory()->forStation($this->station)->create();

    Livewire::actingAs($this->user)
        ->test(DetailStorageTank::class, ['id' => $record->id])
        ->assertSeeHtml('data-testid="storage-tank-detail-rows-empty"');
});
