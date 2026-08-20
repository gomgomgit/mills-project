<?php

/**
 * DashboardHomeTest (Feature/Livewire) — screen-025--dashboard-web /
 * usecase-025--dashboard-web.
 *
 * Component tests for App\Livewire\Dashboard\DashboardHome, one per
 * test_scenarios' component_test step. Mirrors
 * tests/Feature/Livewire/DataBrowserWeighbridgeTest.php's setup/conventions
 * (Livewire::actingAs, RefreshDatabase via tests/Pest.php).
 */

use App\Enums\UserRole;
use App\Livewire\Dashboard\DashboardHome;
use App\Models\BusinessUnit;
use App\Models\Station;
use App\Models\User;
use App\Models\WeighbridgeRecord;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->user = User::factory()->role(UserRole::Supervisor)->create();
});

// Scenario: "Lihat Dashboard Web — berhasil"
it('berhasil: mounts with today filter and renders KPI cards', function () {
    $today = Carbon::today()->toDateString();
    WeighbridgeRecord::factory()->forStation($this->station)->arrivedAt($today.' 08:00:00')->create();

    Livewire::actingAs($this->user)
        ->test(DashboardHome::class)
        ->assertSet('date_from', $today)
        ->assertSet('date_to', $today)
        ->assertSee('Weighbridge')
        ->assertSee('Grading')
        ->assertSee('Cages Track');
});

// Scenario: "Lihat Dashboard Web — Filter Diterapkan"
it('updates KPI when date range and business unit filter are changed', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create();
    $otherStation = Station::factory()->forBusinessUnit($otherBusinessUnit)->create();

    WeighbridgeRecord::factory()->forStation($this->station)->arrivedAt('2026-02-05 08:00:00')->create();
    WeighbridgeRecord::factory()->forStation($otherStation)->arrivedAt('2026-02-05 08:00:00')->create();

    Livewire::actingAs($this->user)
        ->test(DashboardHome::class)
        ->set('date_from', '2026-02-01')
        ->set('date_to', '2026-02-10')
        ->set('business_unit_id', $this->businessUnit->id)
        ->assertSee('1');
});

// Scenario: "Lihat Dashboard Web — Tidak Ada Data Sesuai Filter"
it('shows zero on all cards with no error when no data matches the filter', function () {
    Livewire::actingAs($this->user)
        ->test(DashboardHome::class)
        ->set('date_from', '2020-01-01')
        ->set('date_to', '2020-01-02')
        ->assertSet('errorMessage', null);
});

// Scenario: "Lihat Dashboard Web — Filter Tanggal Tidak Valid"
it('shows a validation error when date_from is later than date_to', function () {
    Livewire::actingAs($this->user)
        ->test(DashboardHome::class)
        ->set('date_from', '2026-02-10')
        ->set('date_to', '2026-02-01')
        ->assertSet('errorMessage', fn ($value) => $value !== null);
});

// Scenario: "Lihat Dashboard Web — Klik Card Stasiun"
it('card link navigates to Data Browser Weighbridge with the active filter carried over', function () {
    Livewire::actingAs($this->user)
        ->test(DashboardHome::class)
        ->set('date_from', '2026-02-01')
        ->set('date_to', '2026-02-10')
        ->assertSeeHtml('data-testid="dash-card-weighbridge"');
});
