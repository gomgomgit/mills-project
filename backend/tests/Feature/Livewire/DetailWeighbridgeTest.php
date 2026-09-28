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
    // FIXTURE DIPERBAIKI 2026-09-28. Sebelumnya tanpa forBusinessUnit():
    // default UserFactory adalah `'business_unit_id' => BusinessUnit::factory()`,
    // jadi Supervisor ini lahir di MILL LAIN — dan test tetap hijau justru
    // karena getDetail() belum punya cakupan mill. Fixture-nya yang salah,
    // bukan asersinya.
    $this->user = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();

    // Mill kedua + stasiunnya: pembanding untuk test cakupan mill di bawah.
    $this->otherBusinessUnit = BusinessUnit::factory()->create();
    $this->otherStation = Station::factory()->forBusinessUnit($this->otherBusinessUnit)->create();
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

// ─── CAKUPAN MILL (2026-09-28) ──────────────────────────────────────────────
// Sampai hari ini getDetail() hanya findOrFail() tanpa cakupan mill apa pun,
// jadi layar ini memuat record mill lain SECARA UTUH bila UUID-nya diketahui
// (mis. dari tautan yang dibagikan) — 403 baru muncul jauh kemudian, saat
// save. Sekarang cakupannya ada di QUERY, sehingga record itu tidak ada sama
// sekali bagi aktor Mill A dan layar jatuh ke state $notFound yang sudah
// ditangani, bukan halaman 403 dan bukan konfirmasi bahwa record itu ada.
it('cakupan mill: UUID record Mill B tidak bisa dibuka Supervisor Mill A', function () {
    $theirs = WeighbridgeRecord::factory()->forStation($this->otherStation)->create();

    Livewire::actingAs($this->user)
        ->test(DetailWeighbridge::class, ['id' => $theirs->id])
        ->assertSet('notFound', true)
        ->assertSet('record', null)
        ->assertSee('Record tidak ditemukan');
});

it('cakupan mill: Admin tetap bisa membuka record mill mana pun', function () {
    $theirs = WeighbridgeRecord::factory()->forStation($this->otherStation)->create();
    $admin = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->businessUnit)->create();

    Livewire::actingAs($admin)
        ->test(DetailWeighbridge::class, ['id' => $theirs->id])
        ->assertSet('notFound', false)
        ->assertSet('record', fn ($record) => is_array($record) && $record['id'] === $theirs->id);
});
