<?php

/**
 * DataBrowserSterilizerTest (Feature/Livewire) —
 * screen-124--data-browser-sterilizer-web /
 * usecase-124--data-browser-sterilizer-web.
 *
 * Component tests for App\Livewire\Data\DataBrowserSterilizer. Mirrors
 * DataBrowserCpoDispatchTest.php's structure/conventions exactly.
 */

use App\Enums\UserRole;
use App\Livewire\Data\DataBrowserSterilizer;
use App\Models\BusinessUnit;
use App\Models\Station;
use App\Models\SterilizerDetail;
use App\Models\SterilizerRecord;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->sterilizer()->create();
    $this->user = User::factory()->role(UserRole::Supervisor)->create();
});

// Scenario: "Telusuri & Ekspor Data Sterilizer — success"
it('success: shows filtered rows and export links after setting filter properties', function () {
    $recordWithDetails = SterilizerRecord::factory()->forStation($this->station)->onDate('2026-08-05')->create();
    SterilizerDetail::factory()->forRecord($recordWithDetails)->count(3)->create();

    SterilizerRecord::factory()->forStation($this->station)->onDate('2026-08-06')->create();

    // Outside the filter range — must not appear.
    SterilizerRecord::factory()->forStation($this->station)->onDate('2026-09-01')->create();

    Livewire::actingAs($this->user)
        ->test(DataBrowserSterilizer::class)
        ->set('date_from', '2026-08-01')
        ->set('date_to', '2026-08-15')
        ->set('business_unit_id', $this->businessUnit->id)
        ->assertSet('errorMessage', null)
        ->assertViewHas('records', function ($records) use ($recordWithDetails) {
            $byId = collect($records)->keyBy('id');

            return count($records) === 2
                && $byId[$recordWithDetails->id]['cycle_count'] === 3;
        })
        ->assertViewHas('meta', fn ($meta) => $meta['total'] === 2)
        ->assertViewHas('exportCsvUrl', fn ($url) => str_contains($url, 'format=csv')
            && str_contains($url, 'business_unit_id='.$this->businessUnit->id))
        ->assertViewHas('exportExcelUrl', fn ($url) => str_contains($url, 'format=excel'))
        ->assertSee('Ekspor CSV')
        ->assertSee('Ekspor Excel');
});

// Scenario: "Telusuri & Ekspor Data Sterilizer — Tidak Ada Data Sesuai Filter"
it('Tidak Ada Data Sesuai Filter: shows the empty state when no records match the filter', function () {
    SterilizerRecord::factory()->forStation($this->station)->onDate('2026-08-05')->create();

    Livewire::actingAs($this->user)
        ->test(DataBrowserSterilizer::class)
        ->set('date_from', '2020-01-01')
        ->set('date_to', '2020-01-02')
        ->assertSet('errorMessage', null)
        ->assertViewHas('records', fn ($records) => count($records) === 0)
        ->assertViewHas('meta', fn ($meta) => $meta['total'] === 0)
        ->assertSee('Tidak ada data');
});

// Scenario: "Telusuri & Ekspor Data Sterilizer — Rentang Tanggal Tidak Valid"
it('Rentang Tanggal Tidak Valid: shows a validation error and does not apply the filter', function () {
    SterilizerRecord::factory()->forStation($this->station)->onDate('2026-08-05')->create();

    Livewire::actingAs($this->user)
        ->test(DataBrowserSterilizer::class)
        ->set('date_from', '2026-08-20')
        ->set('date_to', '2026-08-10')
        ->assertSet('errorMessage', 'Rentang tanggal tidak valid: tanggal awal harus sebelum atau sama dengan tanggal akhir.')
        ->assertViewHas('records', fn ($records) => count($records) === 0)
        ->assertSee('Rentang tanggal tidak valid');
});

// Scenario: "Telusuri & Ekspor Data Sterilizer — Ekspor Gagal"
it('Ekspor Gagal: export links are always built from the current filters (no client-side size guard)', function () {
    Livewire::actingAs($this->user)
        ->test(DataBrowserSterilizer::class)
        ->set('date_from', '2026-01-01')
        ->set('date_to', '2026-12-31')
        ->assertViewHas('exportCsvUrl', fn ($url) => str_contains($url, '/api/sterilizer-records/export')
            && str_contains($url, 'date_from=2026-01-01')
            && str_contains($url, 'date_to=2026-12-31'));
});

// Scenario: "Telusuri & Ekspor Data Sterilizer — Klik Baris Membuka Detail"
it('Klik Baris Membuka Detail: rows render with a clickable-row link to the real detail route', function () {
    $record = SterilizerRecord::factory()->forStation($this->station)->onDate('2026-08-05')->create();

    Livewire::actingAs($this->user)
        ->test(DataBrowserSterilizer::class)
        ->assertSeeHtml("onclick=\"window.location.href='".route('data.sterilizer.detail', ['id' => $record->id])."'\"");
});
