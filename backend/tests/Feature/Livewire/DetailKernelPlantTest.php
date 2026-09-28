<?php

/**
 * DetailKernelPlantTest (Feature/Livewire) —
 * screen-056--detail-kernel-plant-web /
 * usecase-056--detail-kernel-plant-web.
 *
 * Component tests for App\Livewire\Data\DetailKernelPlant, mirroring
 * tests/Feature/Livewire/DetailDepricarpingTest.php's structure exactly,
 * adjusted for the 3-column (not 4-column) operational target table.
 */

use App\Enums\UserRole;
use App\Livewire\Data\DetailKernelPlant;
use App\Models\BusinessUnit;
use App\Models\KernelPlantDetail;
use App\Models\KernelPlantRecord;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\KernelPlantOperationalTargetSeeder;
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
    // DetailKernelPlant's render() queries KernelPlantOperationalTarget
    // live — RefreshDatabase migrates but does not run seeders, so the 6
    // reference rows must be seeded explicitly here for tests asserting
    // their content.
    $this->seed(KernelPlantOperationalTargetSeeder::class);
});

it('berhasil: renders all header fields, the 24-row Kernel Plant Detail grid, and the 3-column operational target table, read-only', function () {
    $record = KernelPlantRecord::factory()->forStation($this->station)->create();
    KernelPlantDetail::factory()->forRecord($record)->timeSlot('10:00')->create(['ripple_mill_1_amps' => 23.5]);

    Livewire::actingAs($this->user)
        ->test(DetailKernelPlant::class, ['id' => $record->id])
        ->assertSee($record->kernel_plant_id)
        ->assertSee('23.5')
        ->assertSee('Ripple Mill (Cracker)')
        ->assertSee('20 - 25 Amps')
        ->assertSee('Adjust rotor-vane clearance')
        ->assertDontSee('Record tidak ditemukan');
});

it('shows Checked By and Acknowledged By both', function () {
    $checker = User::factory()->role(UserRole::Supervisor)->create(['name' => 'Checker Person']);
    $acknowledger = User::factory()->role(UserRole::MillManagement)->create(['name' => 'Acknowledger Person']);
    $record = KernelPlantRecord::factory()->forStation($this->station)->create([
        'checked_by' => $checker->id,
        'acknowledged_by' => $acknowledger->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(DetailKernelPlant::class, ['id' => $record->id])
        ->assertSee('Checker Person')
        ->assertSee('Acknowledger Person');
});

it('Record Tidak Ditemukan: shows an error message with a Back button', function () {
    Livewire::actingAs($this->user)
        ->test(DetailKernelPlant::class, ['id' => '00000000-0000-0000-0000-000000000000'])
        ->assertSet('notFound', true)
        ->assertSee('Record tidak ditemukan')
        ->assertSeeHtml('data-testid="back-button"');
});

it('Back button links to the Data Browser Kernel Plant route', function () {
    $record = KernelPlantRecord::factory()->forStation($this->station)->create();

    Livewire::actingAs($this->user)
        ->test(DetailKernelPlant::class, ['id' => $record->id])
        ->assertSeeHtml(route('data.kernel-plant'));
});

// ─── CAKUPAN MILL (2026-09-28) ──────────────────────────────────────────────
// Sampai hari ini getDetail() hanya findOrFail() tanpa cakupan mill apa pun,
// jadi layar ini memuat record mill lain SECARA UTUH bila UUID-nya diketahui
// (mis. dari tautan yang dibagikan) — 403 baru muncul jauh kemudian, saat
// save. Sekarang cakupannya ada di QUERY, sehingga record itu tidak ada sama
// sekali bagi aktor Mill A dan layar jatuh ke state $notFound yang sudah
// ditangani, bukan halaman 403 dan bukan konfirmasi bahwa record itu ada.
it('cakupan mill: UUID record Mill B tidak bisa dibuka Supervisor Mill A', function () {
    $theirs = KernelPlantRecord::factory()->forStation($this->otherStation)->create();

    Livewire::actingAs($this->user)
        ->test(DetailKernelPlant::class, ['id' => $theirs->id])
        ->assertSet('notFound', true)
        ->assertSet('record', null)
        ->assertSee('Record tidak ditemukan');
});

it('cakupan mill: Admin tetap bisa membuka record mill mana pun', function () {
    $theirs = KernelPlantRecord::factory()->forStation($this->otherStation)->create();
    $admin = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->businessUnit)->create();

    Livewire::actingAs($admin)
        ->test(DetailKernelPlant::class, ['id' => $theirs->id])
        ->assertSet('notFound', false)
        ->assertSet('record', fn ($record) => is_array($record) && $record['id'] === $theirs->id);
});
