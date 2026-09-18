<?php

/**
 * DashboardHomeTest (Feature/Livewire) — screen-025--dashboard-web.
 *
 * The dashboard now renders only the daily mill report (dummy figures); the
 * filterable Weighbridge/Grading/Cages Track KPI block was removed 2026-09-17.
 */

use App\Enums\UserRole;
use App\Livewire\Dashboard\DashboardHome;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->role(UserRole::Supervisor)->create();
});

it('renders the daily mill report with every section', function () {
    Livewire::actingAs($this->user)
        ->test(DashboardHome::class)
        ->assertSeeHtml('data-testid="daily-mill-report"')
        ->assertSee('Dashboard Operasional Mill')
        ->assertSee('Penerimaan FFB vs Budget')
        ->assertSee('Stok FFB')
        ->assertSee('Milling per Line')
        ->assertSee('Distribusi Jam per Line')
        ->assertSee('Kualitas & Stok Tangki')
        ->assertSee('Energi & Air')
        ->assertSee('Oil Extraction Rate')
        ->assertSee('Tabel lengkap laporan harian');
});

it('labels the figures as dummy data', function () {
    Livewire::actingAs($this->user)
        ->test(DashboardHome::class)
        ->assertSee('Data dummy');
});

it('no longer shows the station input summary block', function () {
    Livewire::actingAs($this->user)
        ->test(DashboardHome::class)
        ->assertDontSee('Ringkasan Input Stasiun')
        ->assertDontSeeHtml('data-testid="dash-card-weighbridge"')
        ->assertDontSeeHtml('id="date_from"');
});
