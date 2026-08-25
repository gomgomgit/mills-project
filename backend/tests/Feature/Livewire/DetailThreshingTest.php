<?php

/**
 * DetailThreshingTest (Feature/Livewire) — screen-053--detail-threshing-web
 * / usecase-053--detail-threshing-web.
 *
 * Component tests for App\Livewire\Data\DetailThreshing, mirroring
 * tests/Feature/Livewire/DetailCagesTrackTest.php's structure exactly.
 */

use App\Enums\UserRole;
use App\Livewire\Data\DetailThreshing;
use App\Models\BusinessUnit;
use App\Models\Station;
use App\Models\ThreshingDetail;
use App\Models\ThreshingRecord;
use App\Models\User;
use Database\Seeders\ThreshingOperationalTargetSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->user = User::factory()->role(UserRole::Supervisor)->create();
    // DetailThreshing's render() queries ThreshingOperationalTarget live —
    // RefreshDatabase migrates but does not run seeders, so the 6 reference
    // rows must be seeded explicitly here for tests asserting their content.
    $this->seed(ThreshingOperationalTargetSeeder::class);
});

it('berhasil: renders all header fields, the Threshing Detail grid, and the operational target table, read-only', function () {
    $record = ThreshingRecord::factory()->forStation($this->station)->create();
    ThreshingDetail::factory()->forRecord($record)->timeSlot('10:00')->create(['ffb_throughput_mt_hour' => 42.5]);

    Livewire::actingAs($this->user)
        ->test(DetailThreshing::class, ['id' => $record->id])
        ->assertSee($record->thresher_id)
        ->assertSee('42.5')
        ->assertSee('Thresher Drum Speed')
        ->assertSee('21 - 23 RPM')
        ->assertDontSee('Record tidak ditemukan');
});

it('shows Checked By and Acknowledged By both', function () {
    $checker = User::factory()->role(UserRole::Supervisor)->create(['name' => 'Checker Person']);
    $acknowledger = User::factory()->role(UserRole::MillManagement)->create(['name' => 'Acknowledger Person']);
    $record = ThreshingRecord::factory()->forStation($this->station)->create([
        'checked_by' => $checker->id,
        'acknowledged_by' => $acknowledger->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(DetailThreshing::class, ['id' => $record->id])
        ->assertSee('Checker Person')
        ->assertSee('Acknowledger Person');
});

it('Record Tidak Ditemukan: shows an error message with a Back button', function () {
    Livewire::actingAs($this->user)
        ->test(DetailThreshing::class, ['id' => '00000000-0000-0000-0000-000000000000'])
        ->assertSet('notFound', true)
        ->assertSee('Record tidak ditemukan')
        ->assertSeeHtml('data-testid="back-button"');
});

it('Back button links to the Data Browser Threshing route', function () {
    $record = ThreshingRecord::factory()->forStation($this->station)->create();

    Livewire::actingAs($this->user)
        ->test(DetailThreshing::class, ['id' => $record->id])
        ->assertSeeHtml(route('data.threshing'));
});
