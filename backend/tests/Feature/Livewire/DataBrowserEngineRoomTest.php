<?php

/**
 * DataBrowserEngineRoomTest (Feature/Livewire) —
 * screen-097--data-browser-engine-room-web /
 * usecase-100--data-browser-engine-room-web.
 *
 * Component tests for App\Livewire\Data\DataBrowserEngineRoom, mirroring
 * tests/Feature/Livewire/DataBrowserStorageTankTest.php's structure
 * exactly.
 */

use App\Enums\UserRole;
use App\Livewire\Data\DataBrowserEngineRoom;
use App\Models\BusinessUnit;
use App\Models\EngineRoomDetail;
use App\Models\EngineRoomRecord;
use App\Models\Station;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    // FIXTURE DIPERBAIKI 2026-09-28. Sebelumnya tanpa forBusinessUnit():
    // default UserFactory adalah `'business_unit_id' => BusinessUnit::factory()`,
    // jadi Supervisor ini lahir di MILL LAIN — dan test tetap hijau justru
    // karena layar ini belum punya cakupan mill. Fixture-nya yang salah,
    // bukan asersinya.
    $this->user = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();

    // Mill kedua + stasiunnya: pembanding untuk keempat test cakupan mill
    // di bagian bawah berkas ini.
    $this->otherBusinessUnit = BusinessUnit::factory()->create();
    $this->otherStation = Station::factory()->forBusinessUnit($this->otherBusinessUnit)->create();
});

it('success: shows filtered rows and export links after setting filter properties', function () {
    $recordWithFilledRows = EngineRoomRecord::factory()->forStation($this->station)->onDate('2026-08-05')->create();
    EngineRoomDetail::factory()->forRecord($recordWithFilledRows)->timeSlot('07:00')->filled()->create();
    EngineRoomDetail::factory()->forRecord($recordWithFilledRows)->timeSlot('08:00')->filled()->create();

    EngineRoomRecord::factory()->forStation($this->station)->onDate('2026-08-06')->create();
    EngineRoomRecord::factory()->forStation($this->station)->onDate('2026-09-01')->create();

    Livewire::actingAs($this->user)
        ->test(DataBrowserEngineRoom::class)
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
    EngineRoomRecord::factory()->forStation($this->station)->onDate('2026-08-05')->create();

    Livewire::actingAs($this->user)
        ->test(DataBrowserEngineRoom::class)
        ->set('date_from', '2020-01-01')
        ->set('date_to', '2020-01-02')
        ->assertSet('errorMessage', null)
        ->assertViewHas('records', fn ($records) => count($records) === 0)
        ->assertSee('Tidak ada data');
});

it('Rentang Tanggal Tidak Valid: shows a validation error and does not apply the filter', function () {
    Livewire::actingAs($this->user)
        ->test(DataBrowserEngineRoom::class)
        ->set('date_from', '2026-08-20')
        ->set('date_to', '2026-08-10')
        ->assertSet('errorMessage', 'Rentang tanggal tidak valid: tanggal awal harus sebelum atau sama dengan tanggal akhir.')
        ->assertSee('Rentang tanggal tidak valid');
});

it('Ekspor Gagal: export links are always built from the current filters', function () {
    Livewire::actingAs($this->user)
        ->test(DataBrowserEngineRoom::class)
        ->set('date_from', '2026-01-01')
        ->set('date_to', '2026-12-31')
        ->assertViewHas('exportCsvUrl', fn ($url) => str_contains($url, '/api/engine-room-records/export')
            && str_contains($url, 'date_from=2026-01-01')
            && str_contains($url, 'date_to=2026-12-31'));
});

it('Klik Baris Membuka Detail: rows render with a clickable-row link to the real detail route', function () {
    $record = EngineRoomRecord::factory()->forStation($this->station)->onDate('2026-08-05')->create();

    Livewire::actingAs($this->user)
        ->test(DataBrowserEngineRoom::class)
        ->assertSeeHtml("onclick=\"window.location.href='".route('data.engine-room.detail', ['id' => $record->id])."'\"");
});

// ─── CAKUPAN MILL (2026-09-28) ──────────────────────────────────────────────
// Sampai hari ini layar ini TIDAK punya cakupan mill sama sekali:
// $business_unit_id default '' dan buildFilteredQuery() memperlakukan nilai
// kosong sebagai "tanpa filter", sehingga Supervisor mill mana pun bisa
// melihat — dan mengekspor — record seluruh mill.
//
// Asersinya memeriksa ISI (id record mana yang muncul), bukan jumlah baris:
// kebocoran yang mengembalikan data mill lain juga menghasilkan "ada baris",
// jadi menghitung baris saja tidak membuktikan apa pun.

it('cakupan mill: Supervisor Mill A tidak melihat record Mill B', function () {
    $mine = EngineRoomRecord::factory()->forStation($this->station)->create();
    $theirs = EngineRoomRecord::factory()->forStation($this->otherStation)->create();

    Livewire::actingAs($this->user)
        ->test(DataBrowserEngineRoom::class)
        ->assertViewHas('records', function ($records) use ($mine, $theirs) {
            $ids = collect($records)->pluck('id')->all();

            return in_array($mine->id, $ids, true) && ! in_array($theirs->id, $ids, true);
        });
});

it('cakupan mill: memaksa business_unit_id Mill B lewat properti Livewire tidak mengubah apa pun', function () {
    $mine = EngineRoomRecord::factory()->forStation($this->station)->create();
    $theirs = EngineRoomRecord::factory()->forStation($this->otherStation)->create();

    Livewire::actingAs($this->user)
        ->test(DataBrowserEngineRoom::class)
        ->set('business_unit_id', $this->otherBusinessUnit->id)
        // Properti dipaku kembali ke mill aktor — <select> dan tautan ekspor
        // menampilkan kenyataan, bukan pilihan yang sudah dibuang diam-diam.
        ->assertSet('business_unit_id', $this->businessUnit->id)
        ->assertViewHas('records', function ($records) use ($mine, $theirs) {
            $ids = collect($records)->pluck('id')->all();

            return in_array($mine->id, $ids, true) && ! in_array($theirs->id, $ids, true);
        })
        // Dropdown mill TERSARING, bukan label statis: hanya mill aktor yang
        // ada di dalamnya, jadi mill lain tidak bisa dipilih sejak awal.
        ->assertViewHas('businessUnits', fn ($units) => $units->pluck('id')->all() === [$this->businessUnit->id])
        // Tautan ekspor ikut memakai mill aktor, bukan mill yang disuntikkan.
        ->assertViewHas('exportCsvUrl', fn ($url) => str_contains($url, 'business_unit_id='.$this->businessUnit->id)
            && ! str_contains($url, 'business_unit_id='.$this->otherBusinessUnit->id));
});

it('cakupan mill: Admin tetap melihat semua mill', function () {
    $a = EngineRoomRecord::factory()->forStation($this->station)->create();
    $b = EngineRoomRecord::factory()->forStation($this->otherStation)->create();

    // `business_unit_id` kolom Admin sengaja diabaikan — Admin dinilai dari
    // PERAN, konsisten dengan ScopesToActorMill::actorReadMillId().
    $admin = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->businessUnit)->create();

    Livewire::actingAs($admin)
        ->test(DataBrowserEngineRoom::class)
        ->assertViewHas('records', function ($records) use ($a, $b) {
            $ids = collect($records)->pluck('id')->all();

            return in_array($a->id, $ids, true) && in_array($b->id, $ids, true);
        })
        ->assertViewHas('businessUnits', fn ($units) => $units->count() >= 2);
});

it('cakupan mill: aktor terikat mill tanpa business_unit_id gagal-tertutup dengan pesan', function () {
    EngineRoomRecord::factory()->forStation($this->station)->create();
    EngineRoomRecord::factory()->forStation($this->otherStation)->create();

    $millless = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    Livewire::actingAs($millless)
        ->test(DataBrowserEngineRoom::class)
        // Pesan yang bisa ditindaklanjuti, bukan daftar kosong tanpa sebab —
        // dan bukan pula pelebaran diam-diam ke semua mill.
        ->assertSet('errorMessage', 'Akun Anda belum terhubung ke mill. Hubungi Admin.')
        ->assertViewHas('records', fn ($records) => $records === []);
});
