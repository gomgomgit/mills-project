<?php

/**
 * DetailWeighbridgeTest (Feature/Livewire) — screen-019--detail-weighbridge-web /
 * usecase-019--detail-weighbridge-web.
 *
 * Component tests for App\Livewire\Data\DetailWeighbridge, one per
 * test_scenarios' component_test step. Mirrors
 * tests/Feature/Livewire/DataBrowserWeighbridgeTest.php's setup/conventions
 * (Livewire::actingAs, RefreshDatabase via tests/Pest.php).
 */

use App\Enums\UserRole;
use App\Livewire\Data\DetailWeighbridge;
use App\Models\BusinessUnit;
use App\Models\Station;
use App\Models\User;
use App\Models\WeighbridgeRecord;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->user = User::factory()->role(UserRole::Supervisor)->create();
});

// Scenario: "Lihat Detail Weighbridge — berhasil"
it('berhasil: renders all fields grouped, label sesuai tipe, read-only', function () {
    $record = WeighbridgeRecord::factory()
        ->forStation($this->station)
        ->ofType('dispatch')
        ->create(['destination' => 'PKS Sukamaju']);

    Livewire::actingAs($this->user)
        ->test(DetailWeighbridge::class, ['id' => $record->id])
        ->assertSee('Dispatch')
        ->assertSee('Tanggal & Waktu Dispatch')
        ->assertSee('PKS Sukamaju')
        ->assertSee($record->wb_card_number)
        ->assertDontSee('Record tidak ditemukan');
});

it('Tipe Receive menyembunyikan Tujuan Muatan dan memakai label Arrival', function () {
    $record = WeighbridgeRecord::factory()->forStation($this->station)->ofType('receive')->create();

    Livewire::actingAs($this->user)
        ->test(DetailWeighbridge::class, ['id' => $record->id])
        ->assertSee('Tanggal & Waktu Arrival')
        ->assertDontSee('Tujuan Muatan');
});

// Scenario: "Lihat Detail Weighbridge — Record Tidak Ditemukan"
it('Record Tidak Ditemukan: shows an error message with a Back button', function () {
    Livewire::actingAs($this->user)
        ->test(DetailWeighbridge::class, ['id' => '00000000-0000-0000-0000-000000000000'])
        ->assertSet('notFound', true)
        ->assertSee('Record tidak ditemukan')
        ->assertSeeHtml('data-testid="back-button"');
});

it('Back button links to the Data Browser Weighbridge route', function () {
    $record = WeighbridgeRecord::factory()->forStation($this->station)->create();

    Livewire::actingAs($this->user)
        ->test(DetailWeighbridge::class, ['id' => $record->id])
        ->assertSeeHtml(route('data.weighbridge'));
});
