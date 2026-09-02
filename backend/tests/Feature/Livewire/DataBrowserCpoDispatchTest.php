<?php

/**
 * DataBrowserCpoDispatchTest (Feature/Livewire) —
 * screen-094--data-browser-cpo-dispatch-web /
 * usecase-082--data-browser-cpo-dispatch-web.
 *
 * Component tests for App\Livewire\Data\DataBrowserCpoDispatch. Mirrors
 * DataBrowserKernelDispatchTest.php's structure/conventions exactly.
 */

use App\Enums\UserRole;
use App\Livewire\Data\DataBrowserCpoDispatch;
use App\Models\BusinessUnit;
use App\Models\CpoDispatchDetail;
use App\Models\CpoDispatchRecord;
use App\Models\Station;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->cpoDispatch()->create();
    $this->user = User::factory()->role(UserRole::Supervisor)->create();
});

// Scenario: "Telusuri & Ekspor Data CPO Dispatch — success"
it('success: shows filtered rows and export links after setting filter properties', function () {
    $recordWithDetails = CpoDispatchRecord::factory()->forStation($this->station)->onDate('2026-08-05')->create();
    CpoDispatchDetail::factory()->forRecord($recordWithDetails)->count(3)->create();

    CpoDispatchRecord::factory()->forStation($this->station)->onDate('2026-08-06')->create();

    // Outside the filter range — must not appear.
    CpoDispatchRecord::factory()->forStation($this->station)->onDate('2026-09-01')->create();

    Livewire::actingAs($this->user)
        ->test(DataBrowserCpoDispatch::class)
        ->set('date_from', '2026-08-01')
        ->set('date_to', '2026-08-15')
        ->set('business_unit_id', $this->businessUnit->id)
        ->assertSet('errorMessage', null)
        ->assertViewHas('records', function ($records) use ($recordWithDetails) {
            $byId = collect($records)->keyBy('id');

            return count($records) === 2
                && $byId[$recordWithDetails->id]['event_count'] === 3;
        })
        ->assertViewHas('meta', fn ($meta) => $meta['total'] === 2)
        ->assertViewHas('exportCsvUrl', fn ($url) => str_contains($url, 'format=csv')
            && str_contains($url, 'business_unit_id='.$this->businessUnit->id))
        ->assertViewHas('exportExcelUrl', fn ($url) => str_contains($url, 'format=excel'))
        ->assertSee('Ekspor CSV')
        ->assertSee('Ekspor Excel');
});

// Scenario: "Telusuri & Ekspor Data CPO Dispatch — Tidak Ada Data Sesuai Filter"
it('Tidak Ada Data Sesuai Filter: shows the empty state when no records match the filter', function () {
    CpoDispatchRecord::factory()->forStation($this->station)->onDate('2026-08-05')->create();

    Livewire::actingAs($this->user)
        ->test(DataBrowserCpoDispatch::class)
        ->set('date_from', '2020-01-01')
        ->set('date_to', '2020-01-02')
        ->assertSet('errorMessage', null)
        ->assertViewHas('records', fn ($records) => count($records) === 0)
        ->assertViewHas('meta', fn ($meta) => $meta['total'] === 0)
        ->assertSee('Tidak ada data');
});

// Scenario: "Telusuri & Ekspor Data CPO Dispatch — Rentang Tanggal Tidak Valid"
it('Rentang Tanggal Tidak Valid: shows a validation error and does not apply the filter', function () {
    CpoDispatchRecord::factory()->forStation($this->station)->onDate('2026-08-05')->create();

    Livewire::actingAs($this->user)
        ->test(DataBrowserCpoDispatch::class)
        ->set('date_from', '2026-08-20')
        ->set('date_to', '2026-08-10')
        ->assertSet('errorMessage', 'Rentang tanggal tidak valid: tanggal awal harus sebelum atau sama dengan tanggal akhir.')
        ->assertViewHas('records', fn ($records) => count($records) === 0)
        ->assertSee('Rentang tanggal tidak valid');
});

// Scenario: "Telusuri & Ekspor Data CPO Dispatch — Ekspor Gagal"
it('Ekspor Gagal: export links are always built from the current filters (no client-side size guard)', function () {
    Livewire::actingAs($this->user)
        ->test(DataBrowserCpoDispatch::class)
        ->set('date_from', '2026-01-01')
        ->set('date_to', '2026-12-31')
        ->assertViewHas('exportCsvUrl', fn ($url) => str_contains($url, '/api/cpo-dispatch-records/export')
            && str_contains($url, 'date_from=2026-01-01')
            && str_contains($url, 'date_to=2026-12-31'));
});

// Scenario: "Telusuri & Ekspor Data CPO Dispatch — Klik Baris Membuka Detail"
it('Klik Baris Membuka Detail: rows render with a clickable-row link to the real detail route', function () {
    $record = CpoDispatchRecord::factory()->forStation($this->station)->onDate('2026-08-05')->create();

    Livewire::actingAs($this->user)
        ->test(DataBrowserCpoDispatch::class)
        ->assertSeeHtml("onclick=\"window.location.href='".route('data.cpo-dispatch.detail', ['id' => $record->id])."'\"");
});
