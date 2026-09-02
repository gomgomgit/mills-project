<?php

/**
 * DetailEngineRoomTest (Feature/Livewire) —
 * screen-107--detail-engine-room-web / usecase-101--detail-engine-room-web.
 *
 * Component tests for App\Livewire\Data\DetailEngineRoom, mirroring
 * tests/Feature/Livewire/DetailStorageTankTest.php's structure — MINUS the
 * operational-target table (this station has none).
 */

use App\Enums\UserRole;
use App\Livewire\Data\DetailEngineRoom;
use App\Models\BusinessUnit;
use App\Models\EngineRoomDetail;
use App\Models\EngineRoomRecord;
use App\Models\Station;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->user = User::factory()->role(UserRole::Supervisor)->create();
});

it('berhasil: renders all header fields and the Engine Room Detail grid, read-only', function () {
    $record = EngineRoomRecord::factory()->forStation($this->station)->create();
    EngineRoomDetail::factory()->forRecord($record)->timeSlot('10:00')->create(['steam_turbine_inlet_pressure_bar' => 1242.5]);

    Livewire::actingAs($this->user)
        ->test(DetailEngineRoom::class, ['id' => $record->id])
        ->assertSee($record->engine_room_id)
        ->assertSee('1242.5')
        ->assertDontSee('Record tidak ditemukan');
});

it('shows Checked By and Acknowledged By both', function () {
    $checker = User::factory()->role(UserRole::Supervisor)->create(['name' => 'Checker Person']);
    $acknowledger = User::factory()->role(UserRole::MillManagement)->create(['name' => 'Acknowledger Person']);
    $record = EngineRoomRecord::factory()->forStation($this->station)->create([
        'checked_by' => $checker->id,
        'acknowledged_by' => $acknowledger->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(DetailEngineRoom::class, ['id' => $record->id])
        ->assertSee('Checker Person')
        ->assertSee('Acknowledger Person');
});

it('Record Tidak Ditemukan: shows an error message with a Back button', function () {
    Livewire::actingAs($this->user)
        ->test(DetailEngineRoom::class, ['id' => '00000000-0000-0000-0000-000000000000'])
        ->assertSet('notFound', true)
        ->assertSee('Record tidak ditemukan')
        ->assertSeeHtml('data-testid="back-button"');
});

it('Back button links to the Data Browser Engine Room route', function () {
    $record = EngineRoomRecord::factory()->forStation($this->station)->create();

    Livewire::actingAs($this->user)
        ->test(DetailEngineRoom::class, ['id' => $record->id])
        ->assertSeeHtml(route('data.engine-room'));
});

it('shows an empty-state row when the record has zero detail rows', function () {
    $record = EngineRoomRecord::factory()->forStation($this->station)->create();

    Livewire::actingAs($this->user)
        ->test(DetailEngineRoom::class, ['id' => $record->id])
        ->assertSeeHtml('data-testid="engine-room-detail-rows-empty"');
});
