<?php

/**
 * DataBrowserKernelPlantTest (Feature/Livewire) —
 * screen-052--data-browser-kernel-plant-web /
 * usecase-052--data-browser-kernel-plant-web.
 *
 * Component tests for App\Livewire\Data\DataBrowserKernelPlant, mirroring
 * tests/Feature/Livewire/DataBrowserDepricarpingTest.php's structure exactly.
 */

use App\Enums\UserRole;
use App\Livewire\Data\DataBrowserKernelPlant;
use App\Models\BusinessUnit;
use App\Models\KernelPlantDetail;
use App\Models\KernelPlantRecord;
use App\Models\Station;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->user = User::factory()->role(UserRole::Supervisor)->create();
});

it('success: shows filtered rows and export links after setting filter properties', function () {
    $recordWithFilledRows = KernelPlantRecord::factory()->forStation($this->station)->onDate('2026-08-05')->create();
    KernelPlantDetail::factory()->forRecord($recordWithFilledRows)->timeSlot('07:00')->filled()->create();
    KernelPlantDetail::factory()->forRecord($recordWithFilledRows)->timeSlot('08:00')->filled()->create();

    KernelPlantRecord::factory()->forStation($this->station)->onDate('2026-08-06')->create();
    KernelPlantRecord::factory()->forStation($this->station)->onDate('2026-09-01')->create();

    Livewire::actingAs($this->user)
        ->test(DataBrowserKernelPlant::class)
        ->set('date_from', '2026-08-01')
        ->set('date_to', '2026-08-15')
        ->set('business_unit_id', $this->businessUnit->id)
        ->assertSet('errorMessage', null)
        ->assertViewHas('records', function ($records) use ($recordWithFilledRows) {
            $byId = collect($records)->keyBy('id');

            return count($records) === 2 && $byId[$recordWithFilledRows->id]['filled_slot_count'] === 2;
        })
        ->assertViewHas('meta', fn ($meta) => $meta['total'] === 2)
        ->assertViewHas('exportCsvUrl', fn ($url) => str_contains($url, 'format=csv')
            && str_contains($url, 'business_unit_id='.$this->businessUnit->id))
        ->assertViewHas('exportExcelUrl', fn ($url) => str_contains($url, 'format=excel'))
        ->assertSee('Ekspor CSV')
        ->assertSee('Ekspor Excel');
});

it('Tidak Ada Data Sesuai Filter: shows the empty state when no records match the filter', function () {
    KernelPlantRecord::factory()->forStation($this->station)->onDate('2026-08-05')->create();

    Livewire::actingAs($this->user)
        ->test(DataBrowserKernelPlant::class)
        ->set('date_from', '2020-01-01')
        ->set('date_to', '2020-01-02')
        ->assertSet('errorMessage', null)
        ->assertViewHas('records', fn ($records) => count($records) === 0)
        ->assertSee('Tidak ada data');
});

it('Rentang Tanggal Tidak Valid: shows a validation error and does not apply the filter', function () {
    Livewire::actingAs($this->user)
        ->test(DataBrowserKernelPlant::class)
        ->set('date_from', '2026-08-20')
        ->set('date_to', '2026-08-10')
        ->assertSet('errorMessage', 'Rentang tanggal tidak valid: tanggal awal harus sebelum atau sama dengan tanggal akhir.')
        ->assertSee('Rentang tanggal tidak valid');
});

it('Ekspor Gagal: export links are always built from the current filters', function () {
    Livewire::actingAs($this->user)
        ->test(DataBrowserKernelPlant::class)
        ->set('date_from', '2026-01-01')
        ->set('date_to', '2026-12-31')
        ->assertViewHas('exportCsvUrl', fn ($url) => str_contains($url, '/api/kernel-plant-records/export')
            && str_contains($url, 'date_from=2026-01-01')
            && str_contains($url, 'date_to=2026-12-31'));
});

it('Klik Baris Membuka Detail: rows render with a clickable-row link to the real detail route', function () {
    $record = KernelPlantRecord::factory()->forStation($this->station)->onDate('2026-08-05')->create();

    Livewire::actingAs($this->user)
        ->test(DataBrowserKernelPlant::class)
        ->assertSeeHtml("onclick=\"window.location.href='".route('data.kernel-plant.detail', ['id' => $record->id])."'\"");
});
