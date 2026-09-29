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

/*
 * Barometer kartu KPI. Sejak 2026-09-29 setiap kartu wajib membawa DUA hal:
 * bar progres terhadap acuan tetap, dan badge deviasi terhadap pembanding waktu.
 * Sebelumnya hanya 'FFB Diterima' yang punya bar dan lima sisanya hanya punya
 * badge, sehingga kartu-kartu itu tidak bisa dibandingkan satu sama lain.
 */
it('gives every KPI card both a progress bar and a deviation badge', function () {
    $html = Livewire::actingAs($this->user)->test(DashboardHome::class)->html();

    $start = strpos($html, '<div class="md-kpis">');
    expect($start)->not->toBeFalse();
    $block = substr($html, $start, strpos($html, '<div class="md-row', $start) - $start);

    $cards = substr_count($block, '<article class="md-kpi">');
    expect($cards)->toBe(6);
    expect(substr_count($block, '<div class="md-bar">'))->toBe($cards);
    expect(substr_count($block, 'class="md-trend md-trend--'))->toBe($cards);
});

it('keeps each KPI progress bar consistent with its own label', function () {
    $html = Livewire::actingAs($this->user)->test(DashboardHome::class)->html();

    $start = strpos($html, '<div class="md-kpis">');
    $block = substr($html, $start, strpos($html, '<div class="md-row', $start) - $start);

    // Lebar bar (style="width: N%") harus sama dengan persentase yang ditulis
    // pada baris label tepat di bawahnya — bar dan angka tidak boleh berbeda.
    // Dibatasi pada blok KPI: bagian lain halaman juga memakai .md-bar.
    preg_match_all(
        '/<div class="md-bar"><span style="width: ([\d.]+)%"><\/span><\/div>\s*<p class="md-kpi__foot">([\d.]+)%/',
        $block,
        $m,
    );

    expect($m[1])->toHaveCount(6);
    expect($m[1])->toBe($m[2]);
});
