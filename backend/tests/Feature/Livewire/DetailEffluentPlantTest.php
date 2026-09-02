<?php

/**
 * DetailEffluentPlantTest (Feature/Livewire) —
 * screen-105--detail-effluent-plant-web / usecase-089--detail-effluent-plant-web.
 *
 * Component tests for App\Livewire\Data\DetailEffluentPlant, mirroring
 * tests/Feature/Livewire/DetailThreshingTest.php's structure — MINUS the
 * operational-target table (this station has none).
 */

use App\Enums\UserRole;
use App\Livewire\Data\DetailEffluentPlant;
use App\Models\BusinessUnit;
use App\Models\EffluentPlantDetail;
use App\Models\EffluentPlantRecord;
use App\Models\Station;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->user = User::factory()->role(UserRole::Supervisor)->create();
});

it('berhasil: renders all header fields and the Effluent Plant Detail grid, read-only', function () {
    $record = EffluentPlantRecord::factory()->forStation($this->station)->create();
    EffluentPlantDetail::factory()->forRecord($record)->timeSlot('10:00')->create(['anaerobic_pond_1_ph' => 42.5]);

    Livewire::actingAs($this->user)
        ->test(DetailEffluentPlant::class, ['id' => $record->id])
        ->assertSee($record->effluent_plant_id)
        ->assertSee('42.5')
        ->assertDontSee('Record tidak ditemukan');
});

it('shows Checked By and Acknowledged By both', function () {
    $checker = User::factory()->role(UserRole::Supervisor)->create(['name' => 'Checker Person']);
    $acknowledger = User::factory()->role(UserRole::MillManagement)->create(['name' => 'Acknowledger Person']);
    $record = EffluentPlantRecord::factory()->forStation($this->station)->create([
        'checked_by' => $checker->id,
        'acknowledged_by' => $acknowledger->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(DetailEffluentPlant::class, ['id' => $record->id])
        ->assertSee('Checker Person')
        ->assertSee('Acknowledger Person');
});

it('Record Tidak Ditemukan: shows an error message with a Back button', function () {
    Livewire::actingAs($this->user)
        ->test(DetailEffluentPlant::class, ['id' => '00000000-0000-0000-0000-000000000000'])
        ->assertSet('notFound', true)
        ->assertSee('Record tidak ditemukan')
        ->assertSeeHtml('data-testid="back-button"');
});

it('Back button links to the Data Browser Effluent Plant route', function () {
    $record = EffluentPlantRecord::factory()->forStation($this->station)->create();

    Livewire::actingAs($this->user)
        ->test(DetailEffluentPlant::class, ['id' => $record->id])
        ->assertSeeHtml(route('data.effluent-plant'));
});

it('shows an empty-state row when the record has zero detail rows', function () {
    $record = EffluentPlantRecord::factory()->forStation($this->station)->create();

    Livewire::actingAs($this->user)
        ->test(DetailEffluentPlant::class, ['id' => $record->id])
        ->assertSeeHtml('data-testid="effluent-plant-detail-rows-empty"');
});
