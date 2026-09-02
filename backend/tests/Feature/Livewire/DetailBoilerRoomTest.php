<?php

/**
 * DetailBoilerRoomTest (Feature/Livewire) —
 * screen-108--detail-boiler-room-web / usecase-107--detail-boiler-room-web.
 *
 * Component tests for App\Livewire\Data\DetailBoilerRoom, mirroring
 * tests/Feature/Livewire/DetailEngineRoomTest.php's structure — MINUS the
 * operational-target table (this station has none).
 */

use App\Enums\UserRole;
use App\Livewire\Data\DetailBoilerRoom;
use App\Models\BoilerRoomDetail;
use App\Models\BoilerRoomRecord;
use App\Models\BusinessUnit;
use App\Models\Station;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->user = User::factory()->role(UserRole::Supervisor)->create();
});

it('berhasil: renders all header fields and the Boiler Room Detail grid, read-only', function () {
    $record = BoilerRoomRecord::factory()->forStation($this->station)->create();
    BoilerRoomDetail::factory()->forRecord($record)->timeSlot('10:00')->create(['steam_pressure_bar' => 12.5]);

    Livewire::actingAs($this->user)
        ->test(DetailBoilerRoom::class, ['id' => $record->id])
        ->assertSee($record->boiler_room_id)
        ->assertSee('12.5')
        ->assertDontSee('Record tidak ditemukan');
});

it('shows Checked By and Acknowledged By both', function () {
    $checker = User::factory()->role(UserRole::Supervisor)->create(['name' => 'Checker Person']);
    $acknowledger = User::factory()->role(UserRole::MillManagement)->create(['name' => 'Acknowledger Person']);
    $record = BoilerRoomRecord::factory()->forStation($this->station)->create([
        'checked_by' => $checker->id,
        'acknowledged_by' => $acknowledger->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(DetailBoilerRoom::class, ['id' => $record->id])
        ->assertSee('Checker Person')
        ->assertSee('Acknowledger Person');
});

it('Record Tidak Ditemukan: shows an error message with a Back button', function () {
    Livewire::actingAs($this->user)
        ->test(DetailBoilerRoom::class, ['id' => '00000000-0000-0000-0000-000000000000'])
        ->assertSet('notFound', true)
        ->assertSee('Record tidak ditemukan')
        ->assertSeeHtml('data-testid="back-button"');
});

it('Back button links to the Data Browser Boiler Room route', function () {
    $record = BoilerRoomRecord::factory()->forStation($this->station)->create();

    Livewire::actingAs($this->user)
        ->test(DetailBoilerRoom::class, ['id' => $record->id])
        ->assertSeeHtml(route('data.boiler-room'));
});

it('shows an empty-state row when the record has zero detail rows', function () {
    $record = BoilerRoomRecord::factory()->forStation($this->station)->create();

    Livewire::actingAs($this->user)
        ->test(DetailBoilerRoom::class, ['id' => $record->id])
        ->assertSeeHtml('data-testid="boiler-room-detail-rows-empty"');
});
