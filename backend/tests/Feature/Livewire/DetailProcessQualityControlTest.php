<?php

/**
 * DetailProcessQualityControlTest (Feature/Livewire) —
 * screen-110--detail-process-quality-control-web / usecase-119--detail-process-quality-control-web.
 *
 * Component tests for App\Livewire\Data\DetailProcessQualityControl,
 * mirroring tests/Feature/Livewire/DetailClarificationTest.php's structure
 * — MINUS the operational-target table (this station has none).
 */

use App\Enums\UserRole;
use App\Livewire\Data\DetailProcessQualityControl;
use App\Models\BusinessUnit;
use App\Models\ProcessQualityControlDetail;
use App\Models\ProcessQualityControlRecord;
use App\Models\Station;
use App\Models\User;
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

it('berhasil: renders all header fields and the Process Quality Control Detail grid, read-only', function () {
    $record = ProcessQualityControlRecord::factory()->forStation($this->station)->create();
    ProcessQualityControlDetail::factory()->forRecord($record)->timeSlot('10:00')->create(['fruit_press_oil_loss_in_sludge_percent' => 0.85]);

    Livewire::actingAs($this->user)
        ->test(DetailProcessQualityControl::class, ['id' => $record->id])
        ->assertSee($record->process_qc_id)
        // Angka format id-ID sejak 2026-10-04 (App\\Support\\Display).
        ->assertSee('0,85')
        ->assertDontSee('Record tidak ditemukan');
});

it('shows Checked By and Acknowledged By both', function () {
    $checker = User::factory()->role(UserRole::Supervisor)->create(['name' => 'Checker Person']);
    $acknowledger = User::factory()->role(UserRole::MillManagement)->create(['name' => 'Acknowledger Person']);
    $record = ProcessQualityControlRecord::factory()->forStation($this->station)->create([
        'checked_by' => $checker->id,
        'acknowledged_by' => $acknowledger->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(DetailProcessQualityControl::class, ['id' => $record->id])
        ->assertSee('Checker Person')
        ->assertSee('Acknowledger Person');
});

it('Record Tidak Ditemukan: shows an error message with a Back button', function () {
    Livewire::actingAs($this->user)
        ->test(DetailProcessQualityControl::class, ['id' => '00000000-0000-0000-0000-000000000000'])
        ->assertSet('notFound', true)
        ->assertSee('Record tidak ditemukan')
        ->assertSeeHtml('data-testid="back-button"');
});

it('Back button links to the Data Browser Process Quality Control route', function () {
    $record = ProcessQualityControlRecord::factory()->forStation($this->station)->create();

    Livewire::actingAs($this->user)
        ->test(DetailProcessQualityControl::class, ['id' => $record->id])
        ->assertSeeHtml(route('data.process-quality-control'));
});

it('shows an empty-state row when the record has zero detail rows', function () {
    $record = ProcessQualityControlRecord::factory()->forStation($this->station)->create();

    Livewire::actingAs($this->user)
        ->test(DetailProcessQualityControl::class, ['id' => $record->id])
        ->assertSeeHtml('data-testid="process-quality-control-detail-rows-empty"');
});

// ─── CAKUPAN MILL (2026-09-28) ──────────────────────────────────────────────
// Sampai hari ini getDetail() hanya findOrFail() tanpa cakupan mill apa pun,
// jadi layar ini memuat record mill lain SECARA UTUH bila UUID-nya diketahui
// (mis. dari tautan yang dibagikan) — 403 baru muncul jauh kemudian, saat
// save. Sekarang cakupannya ada di QUERY, sehingga record itu tidak ada sama
// sekali bagi aktor Mill A dan layar jatuh ke state $notFound yang sudah
// ditangani, bukan halaman 403 dan bukan konfirmasi bahwa record itu ada.
it('cakupan mill: UUID record Mill B tidak bisa dibuka Supervisor Mill A', function () {
    $theirs = ProcessQualityControlRecord::factory()->forStation($this->otherStation)->create();

    Livewire::actingAs($this->user)
        ->test(DetailProcessQualityControl::class, ['id' => $theirs->id])
        ->assertSet('notFound', true)
        ->assertSet('record', null)
        ->assertSee('Record tidak ditemukan');
});

it('cakupan mill: Admin tetap bisa membuka record mill mana pun', function () {
    $theirs = ProcessQualityControlRecord::factory()->forStation($this->otherStation)->create();
    $admin = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->businessUnit)->create();

    Livewire::actingAs($admin)
        ->test(DetailProcessQualityControl::class, ['id' => $theirs->id])
        ->assertSet('notFound', false)
        ->assertSet('record', fn ($record) => is_array($record) && $record['id'] === $theirs->id);
});
