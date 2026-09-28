<?php

/**
 * DetailSolidWasteDisposalTest (Feature/Livewire) —
 * screen-101--detail-solid-waste-disposal-web /
 * usecase-065--detail-solid-waste-disposal-web.
 *
 * Component tests for App\Livewire\Data\DetailSolidWasteDisposal. Mirrors
 * DetailCagesTrackTest.php's setup/conventions.
 */

use App\Enums\UserRole;
use App\Livewire\Data\DetailSolidWasteDisposal;
use App\Models\BusinessUnit;
use App\Models\SolidWasteDisposalDetail;
use App\Models\SolidWasteDisposalRecord;
use App\Models\Station;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->solidWasteDisposal()->create();
    // FIXTURE DIPERBAIKI 2026-09-28. Sebelumnya tanpa forBusinessUnit():
    // default UserFactory adalah `'business_unit_id' => BusinessUnit::factory()`,
    // jadi Supervisor ini lahir di MILL LAIN — dan test tetap hijau justru
    // karena getDetail() belum punya cakupan mill. Fixture-nya yang salah,
    // bukan asersinya.
    $this->user = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();

    // Mill kedua + stasiunnya: pembanding untuk test cakupan mill di bawah.
    $this->otherBusinessUnit = BusinessUnit::factory()->create();
    $this->otherStation = Station::factory()->forBusinessUnit($this->otherBusinessUnit)->solidWasteDisposal()->create();
});

// Scenario: "Lihat Detail Solid Waste Disposal - berhasil"
it('berhasil: renders all header fields grouped and the event-log table, read-only', function () {
    $record = SolidWasteDisposalRecord::factory()->forStation($this->station)->create();
    SolidWasteDisposalDetail::factory()->forRecord($record)->create(['vehicle_no' => 'B 1234 XY']);

    Livewire::actingAs($this->user)
        ->test(DetailSolidWasteDisposal::class, ['id' => $record->id])
        ->assertSee($record->solid_waste_disposal_id)
        ->assertSee('B 1234 XY')
        ->assertDontSee('Record tidak ditemukan');
});

it('shows Checked By and Acknowledged By both', function () {
    $checker = User::factory()->role(UserRole::Supervisor)->create(['name' => 'Checker Person']);
    $acknowledger = User::factory()->role(UserRole::MillManagement)->create(['name' => 'Acknowledger Person']);
    $record = SolidWasteDisposalRecord::factory()->forStation($this->station)->create([
        'checked_by' => $checker->id,
        'acknowledged_by' => $acknowledger->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(DetailSolidWasteDisposal::class, ['id' => $record->id])
        ->assertSee('Checker Person')
        ->assertSee('Acknowledger Person');
});

// Scenario: "Lihat Detail Solid Waste Disposal - Record Tidak Ditemukan"
it('Record Tidak Ditemukan: shows an error message with a Back button', function () {
    Livewire::actingAs($this->user)
        ->test(DetailSolidWasteDisposal::class, ['id' => '00000000-0000-0000-0000-000000000000'])
        ->assertSet('notFound', true)
        ->assertSee('Record tidak ditemukan')
        ->assertSeeHtml('data-testid="back-button"');
});

it('Back button links to the Data Browser Solid Waste Disposal route', function () {
    $record = SolidWasteDisposalRecord::factory()->forStation($this->station)->create();

    Livewire::actingAs($this->user)
        ->test(DetailSolidWasteDisposal::class, ['id' => $record->id])
        ->assertSeeHtml(route('data.solid-waste-disposal'));
});

// ─── CAKUPAN MILL (2026-09-28) ──────────────────────────────────────────────
// Sampai hari ini getDetail() hanya findOrFail() tanpa cakupan mill apa pun,
// jadi layar ini memuat record mill lain SECARA UTUH bila UUID-nya diketahui
// (mis. dari tautan yang dibagikan) — 403 baru muncul jauh kemudian, saat
// save. Sekarang cakupannya ada di QUERY, sehingga record itu tidak ada sama
// sekali bagi aktor Mill A dan layar jatuh ke state $notFound yang sudah
// ditangani, bukan halaman 403 dan bukan konfirmasi bahwa record itu ada.
it('cakupan mill: UUID record Mill B tidak bisa dibuka Supervisor Mill A', function () {
    $theirs = SolidWasteDisposalRecord::factory()->forStation($this->otherStation)->create();

    Livewire::actingAs($this->user)
        ->test(DetailSolidWasteDisposal::class, ['id' => $theirs->id])
        ->assertSet('notFound', true)
        ->assertSet('record', null)
        ->assertSee('Record tidak ditemukan');
});

it('cakupan mill: Admin tetap bisa membuka record mill mana pun', function () {
    $theirs = SolidWasteDisposalRecord::factory()->forStation($this->otherStation)->create();
    $admin = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->businessUnit)->create();

    Livewire::actingAs($admin)
        ->test(DetailSolidWasteDisposal::class, ['id' => $theirs->id])
        ->assertSet('notFound', false)
        ->assertSet('record', fn ($record) => is_array($record) && $record['id'] === $theirs->id);
});
