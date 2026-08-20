<?php

/**
 * ManagementReportTest (Feature/Livewire) — screen-026--laporan-manajemen /
 * usecase-026--laporan-manajemen.
 *
 * Component tests for App\Livewire\Dashboard\ManagementReport, one per
 * test_scenarios' component_test step. Mirrors
 * tests/Feature/Livewire/DashboardHomeTest.php's conventions.
 */

use App\Enums\UserRole;
use App\Livewire\Dashboard\ManagementReport;
use App\Models\BusinessUnit;
use App\Models\Station;
use App\Models\User;
use App\Models\WeighbridgeRecord;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->user = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnit)->create();
});

// Scenario: "Lihat Laporan Manajemen — berhasil"
it('berhasil: mounts with start-of-month..today filter and renders the breakdown table', function () {
    $startOfMonth = Carbon::today()->startOfMonth()->toDateString();
    $today = Carbon::today()->toDateString();
    WeighbridgeRecord::factory()->forStation($this->station)->arrivedAt($today.' 08:00:00')->create();

    Livewire::actingAs($this->user)
        ->test(ManagementReport::class)
        ->assertSet('date_from', $startOfMonth)
        ->assertSet('date_to', $today)
        ->assertSee('Laporan Manajemen')
        ->assertSee('Total');
});

// Scenario: "Lihat Laporan Manajemen — Filter Diterapkan"
it('updates the breakdown when the date range filter is changed', function () {
    WeighbridgeRecord::factory()->forStation($this->station)->arrivedAt('2026-02-05 08:00:00')->create();

    Livewire::actingAs($this->user)
        ->test(ManagementReport::class)
        ->set('date_from', '2026-02-01')
        ->set('date_to', '2026-02-10')
        ->assertSeeHtml('data-testid="report-row-2026-02-05"');
});

// Scenario: "Lihat Laporan Manajemen — Tidak Ada Data Sesuai Filter"
it('shows the empty-data message with zero-value rows when no data matches the filter', function () {
    Livewire::actingAs($this->user)
        ->test(ManagementReport::class)
        ->set('date_from', '2020-01-01')
        ->set('date_to', '2020-01-02')
        ->assertSet('errorMessage', null)
        ->assertSeeHtml('data-testid="report-empty"');
});

// Scenario: "Lihat Laporan Manajemen — Rentang Tanggal Tidak Valid"
it('shows a validation error when date_from is later than date_to', function () {
    Livewire::actingAs($this->user)
        ->test(ManagementReport::class)
        ->set('date_from', '2026-02-10')
        ->set('date_to', '2026-02-01')
        ->assertSet('errorMessage', fn ($value) => $value !== null);
});

// Scenario: "Lihat Laporan Manajemen — Ekspor Laporan"
it('export buttons point at the API export endpoint with the active filter', function () {
    Livewire::actingAs($this->user)
        ->test(ManagementReport::class)
        ->set('date_from', '2026-02-01')
        ->set('date_to', '2026-02-10')
        ->assertSeeHtml('data-testid="report-export-csv"')
        ->assertSeeHtml('/api/reports/management-summary/export?date_from=2026-02-01&amp;date_to=2026-02-10&amp;format=csv');
});
