<?php

/**
 * DetailDepricarpingTest (Feature/Livewire) —
 * screen-055--detail-depricarping-web /
 * usecase-055--detail-depricarping-web.
 *
 * Component tests for App\Livewire\Data\DetailDepricarping, mirroring
 * tests/Feature/Livewire/DetailPressingTest.php's structure exactly.
 */

use App\Enums\UserRole;
use App\Livewire\Data\DetailDepricarping;
use App\Models\BusinessUnit;
use App\Models\DepricarpingDetail;
use App\Models\DepricarpingRecord;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\DepricarpingOperationalTargetSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->user = User::factory()->role(UserRole::Supervisor)->create();
    // DetailDepricarping's render() queries DepricarpingOperationalTarget
    // live — RefreshDatabase migrates but does not run seeders, so the 6
    // reference rows must be seeded explicitly here for tests asserting
    // their content.
    $this->seed(DepricarpingOperationalTargetSeeder::class);
});

it('berhasil: renders all header fields, the 24-row Depricarping Detail grid, and the 4-column operational target table, read-only', function () {
    $record = DepricarpingRecord::factory()->forStation($this->station)->create();
    DepricarpingDetail::factory()->forRecord($record)->timeSlot('10:00')->create(['fan_static_pressure_mmh2o' => 45.5]);

    Livewire::actingAs($this->user)
        ->test(DetailDepricarping::class, ['id' => $record->id])
        ->assertSee($record->presser_id)
        ->assertSee('45.5')
        ->assertSee('Fan Static Pressure')
        ->assertSee('40 - 50 mmH2O')
        ->assertSee('Low pressure drops fibre early')
        ->assertDontSee('Record tidak ditemukan');
});

it('shows Checked By and Acknowledged By both', function () {
    $checker = User::factory()->role(UserRole::Supervisor)->create(['name' => 'Checker Person']);
    $acknowledger = User::factory()->role(UserRole::MillManagement)->create(['name' => 'Acknowledger Person']);
    $record = DepricarpingRecord::factory()->forStation($this->station)->create([
        'checked_by' => $checker->id,
        'acknowledged_by' => $acknowledger->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(DetailDepricarping::class, ['id' => $record->id])
        ->assertSee('Checker Person')
        ->assertSee('Acknowledger Person');
});

it('Record Tidak Ditemukan: shows an error message with a Back button', function () {
    Livewire::actingAs($this->user)
        ->test(DetailDepricarping::class, ['id' => '00000000-0000-0000-0000-000000000000'])
        ->assertSet('notFound', true)
        ->assertSee('Record tidak ditemukan')
        ->assertSeeHtml('data-testid="back-button"');
});

it('Back button links to the Data Browser Depricarping route', function () {
    $record = DepricarpingRecord::factory()->forStation($this->station)->create();

    Livewire::actingAs($this->user)
        ->test(DetailDepricarping::class, ['id' => $record->id])
        ->assertSeeHtml(route('data.depricarping'));
});
