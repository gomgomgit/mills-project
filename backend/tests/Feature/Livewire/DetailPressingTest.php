<?php

/**
 * DetailPressingTest (Feature/Livewire) — screen-054--detail-pressing-web
 * / usecase-054--detail-pressing-web.
 *
 * Component tests for App\Livewire\Data\DetailPressing, mirroring
 * tests/Feature/Livewire/DetailThreshingTest.php's structure exactly.
 */

use App\Enums\UserRole;
use App\Livewire\Data\DetailPressing;
use App\Models\BusinessUnit;
use App\Models\PressingDetail;
use App\Models\PressingRecord;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\PressingOperationalTargetSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->user = User::factory()->role(UserRole::Supervisor)->create();
    // DetailPressing's render() queries PressingOperationalTarget live —
    // RefreshDatabase migrates but does not run seeders, so the 7
    // reference rows must be seeded explicitly here for tests asserting
    // their content.
    $this->seed(PressingOperationalTargetSeeder::class);
});

it('berhasil: renders all header fields, the 24-row Pressing Detail grid, and the operational target table, read-only', function () {
    $record = PressingRecord::factory()->forStation($this->station)->create();
    PressingDetail::factory()->forRecord($record)->timeSlot('10:00')->create(['digester_temp_c' => 92.5]);

    Livewire::actingAs($this->user)
        ->test(DetailPressing::class, ['id' => $record->id])
        ->assertSee($record->presser_id)
        ->assertSee('92.5')
        ->assertSee('Cone Hydraulic Pressure')
        ->assertSee('45 - 55 Bar')
        ->assertDontSee('Record tidak ditemukan');
});

it('shows Checked By and Acknowledged By both', function () {
    $checker = User::factory()->role(UserRole::Supervisor)->create(['name' => 'Checker Person']);
    $acknowledger = User::factory()->role(UserRole::MillManagement)->create(['name' => 'Acknowledger Person']);
    $record = PressingRecord::factory()->forStation($this->station)->create([
        'checked_by' => $checker->id,
        'acknowledged_by' => $acknowledger->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(DetailPressing::class, ['id' => $record->id])
        ->assertSee('Checker Person')
        ->assertSee('Acknowledger Person');
});

it('Record Tidak Ditemukan: shows an error message with a Back button', function () {
    Livewire::actingAs($this->user)
        ->test(DetailPressing::class, ['id' => '00000000-0000-0000-0000-000000000000'])
        ->assertSet('notFound', true)
        ->assertSee('Record tidak ditemukan')
        ->assertSeeHtml('data-testid="back-button"');
});

it('Back button links to the Data Browser Pressing route', function () {
    $record = PressingRecord::factory()->forStation($this->station)->create();

    Livewire::actingAs($this->user)
        ->test(DetailPressing::class, ['id' => $record->id])
        ->assertSeeHtml(route('data.pressing'));
});
