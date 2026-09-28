<?php

/**
 * DetailDepricarpingTest (Feature/Livewire) —
 * screen-055--detail-depricarping-web /
 * usecase-055--detail-depricarping-web.
 *
 * Component tests for App\Livewire\Data\DetailDepricarping, mirroring
 * tests/Feature/Livewire/DetailPressingTest.php's structure exactly.
 */

use App\Enums\UserRole;
use App\Livewire\Data\DetailDepricarping;
use App\Models\BusinessUnit;
use App\Models\DepricarpingDetail;
use App\Models\DepricarpingRecord;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\DepricarpingOperationalTargetSeeder;
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
    // DetailDepricarping's render() queries DepricarpingOperationalTarget
    // live — RefreshDatabase migrates but does not run seeders, so the 6
    // reference rows must be seeded explicitly here for tests asserting
    // their content.
    $this->seed(DepricarpingOperationalTargetSeeder::class);
});

it('berhasil: renders all header fields, the 24-row Depricarping Detail grid, and the 4-column operational target table, read-only', function () {
    $record = DepricarpingRecord::factory()->forStation($this->station)->create();
    DepricarpingDetail::factory()->forRecord($record)->timeSlot('10:00')->create(['fan_static_pressure_mmh2o' => 45.5]);

    Livewire::actingAs($this->user)
        ->test(DetailDepricarping::class, ['id' => $record->id])
        ->assertSee($record->presser_id)
        ->assertSee('45.5')
        ->assertSee('Fan Static Pressure')
        ->assertSee('40 - 50 mmH2O')
        ->assertSee('Low pressure drops fibre early')
        ->assertDontSee('Record tidak ditemukan');
});

it('shows Checked By and Acknowledged By both', function () {
    $checker = User::factory()->role(UserRole::Supervisor)->create(['name' => 'Checker Person']);
    $acknowledger = User::factory()->role(UserRole::MillManagement)->create(['name' => 'Acknowledger Person']);
    $record = DepricarpingRecord::factory()->forStation($this->station)->create([
        'checked_by' => $checker->id,
        'acknowledged_by' => $acknowledger->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(DetailDepricarping::class, ['id' => $record->id])
        ->assertSee('Checker Person')
        ->assertSee('Acknowledger Person');
});

it('Record Tidak Ditemukan: shows an error message with a Back button', function () {
    Livewire::actingAs($this->user)
        ->test(DetailDepricarping::class, ['id' => '00000000-0000-0000-0000-000000000000'])
        ->assertSet('notFound', true)
        ->assertSee('Record tidak ditemukan')
        ->assertSeeHtml('data-testid="back-button"');
});

it('Back button links to the Data Browser Depricarping route', function () {
    $record = DepricarpingRecord::factory()->forStation($this->station)->create();

    Livewire::actingAs($this->user)
        ->test(DetailDepricarping::class, ['id' => $record->id])
        ->assertSeeHtml(route('data.depricarping'));
});

// ─── CAKUPAN MILL (2026-09-28) ──────────────────────────────────────────────
// Sampai hari ini getDetail() hanya findOrFail() tanpa cakupan mill apa pun,
// jadi layar ini memuat record mill lain SECARA UTUH bila UUID-nya diketahui
// (mis. dari tautan yang dibagikan) — 403 baru muncul jauh kemudian, saat
// save. Sekarang cakupannya ada di QUERY, sehingga record itu tidak ada sama
// sekali bagi aktor Mill A dan layar jatuh ke state $notFound yang sudah
// ditangani, bukan halaman 403 dan bukan konfirmasi bahwa record itu ada.
it('cakupan mill: UUID record Mill B tidak bisa dibuka Supervisor Mill A', function () {
    $theirs = DepricarpingRecord::factory()->forStation($this->otherStation)->create();

    Livewire::actingAs($this->user)
        ->test(DetailDepricarping::class, ['id' => $theirs->id])
        ->assertSet('notFound', true)
        ->assertSet('record', null)
        ->assertSee('Record tidak ditemukan');
});

it('cakupan mill: Admin tetap bisa membuka record mill mana pun', function () {
    $theirs = DepricarpingRecord::factory()->forStation($this->otherStation)->create();
    $admin = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->businessUnit)->create();

    Livewire::actingAs($admin)
        ->test(DetailDepricarping::class, ['id' => $theirs->id])
        ->assertSet('notFound', false)
        ->assertSet('record', fn ($record) => is_array($record) && $record['id'] === $theirs->id);
});
