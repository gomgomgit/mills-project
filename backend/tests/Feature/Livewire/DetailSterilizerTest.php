<?php

/**
 * DetailSterilizerTest (Feature/Livewire) —
 * screen-125--detail-sterilizer-web /
 * usecase-125--detail-sterilizer-web.
 *
 * Component tests for App\Livewire\Data\DetailSterilizer. Mirrors
 * DetailCpoDispatchTest.php's setup/conventions.
 */

use App\Enums\UserRole;
use App\Livewire\Data\DetailSterilizer;
use App\Models\BusinessUnit;
use App\Models\Station;
use App\Models\SterilizerDetail;
use App\Models\SterilizerRecord;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->sterilizer()->create();
    // FIXTURE DIPERBAIKI 2026-09-28. Sebelumnya tanpa forBusinessUnit():
    // default UserFactory adalah `'business_unit_id' => BusinessUnit::factory()`,
    // jadi Supervisor ini lahir di MILL LAIN — dan test tetap hijau justru
    // karena getDetail() belum punya cakupan mill. Fixture-nya yang salah,
    // bukan asersinya.
    $this->user = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();

    // Mill kedua + stasiunnya: pembanding untuk test cakupan mill di bawah.
    $this->otherBusinessUnit = BusinessUnit::factory()->create();
    $this->otherStation = Station::factory()->forBusinessUnit($this->otherBusinessUnit)->sterilizer()->create();
});

// Scenario: "Lihat Detail Sterilizer - berhasil"
it('berhasil: renders all header fields grouped and the cycle-log table, read-only', function () {
    $record = SterilizerRecord::factory()->forStation($this->station)->create();
    SterilizerDetail::factory()->forRecord($record)->create(['sterilizer_no' => '5']);

    Livewire::actingAs($this->user)
        ->test(DetailSterilizer::class, ['id' => $record->id])
        ->assertSee($record->sterilizer_id)
        ->assertSee('5')
        ->assertDontSee('Record tidak ditemukan');
});

it('shows duration_minutes in the cycle-log table', function () {
    $record = SterilizerRecord::factory()->forStation($this->station)->create();
    SterilizerDetail::factory()->forRecord($record)->create(['close_door_time' => '07:00', 'open_door_time' => '08:10', 'duration_minutes' => 70]);

    Livewire::actingAs($this->user)
        ->test(DetailSterilizer::class, ['id' => $record->id])
        ->assertSee('70');
});

it('shows Checked By and Acknowledged By both', function () {
    $checker = User::factory()->role(UserRole::Supervisor)->create(['name' => 'Checker Person']);
    $acknowledger = User::factory()->role(UserRole::MillManagement)->create(['name' => 'Acknowledger Person']);
    $record = SterilizerRecord::factory()->forStation($this->station)->create([
        'checked_by' => $checker->id,
        'acknowledged_by' => $acknowledger->id,
    ]);

    Livewire::actingAs($this->user)
        ->test(DetailSterilizer::class, ['id' => $record->id])
        ->assertSee('Checker Person')
        ->assertSee('Acknowledger Person');
});

// Scenario: "Lihat Detail Sterilizer - Record Tidak Ditemukan"
it('Record Tidak Ditemukan: shows an error message with a Back button', function () {
    Livewire::actingAs($this->user)
        ->test(DetailSterilizer::class, ['id' => '00000000-0000-0000-0000-000000000000'])
        ->assertSet('notFound', true)
        ->assertSee('Record tidak ditemukan')
        ->assertSeeHtml('data-testid="back-button"');
});

it('Back button links to the Data Browser Sterilizer route', function () {
    $record = SterilizerRecord::factory()->forStation($this->station)->create();

    Livewire::actingAs($this->user)
        ->test(DetailSterilizer::class, ['id' => $record->id])
        ->assertSeeHtml(route('data.sterilizer'));
});

// ─── CAKUPAN MILL (2026-09-28) ──────────────────────────────────────────────
// Sampai hari ini getDetail() hanya findOrFail() tanpa cakupan mill apa pun,
// jadi layar ini memuat record mill lain SECARA UTUH bila UUID-nya diketahui
// (mis. dari tautan yang dibagikan) — 403 baru muncul jauh kemudian, saat
// save. Sekarang cakupannya ada di QUERY, sehingga record itu tidak ada sama
// sekali bagi aktor Mill A dan layar jatuh ke state $notFound yang sudah
// ditangani, bukan halaman 403 dan bukan konfirmasi bahwa record itu ada.
it('cakupan mill: UUID record Mill B tidak bisa dibuka Supervisor Mill A', function () {
    $theirs = SterilizerRecord::factory()->forStation($this->otherStation)->create();

    Livewire::actingAs($this->user)
        ->test(DetailSterilizer::class, ['id' => $theirs->id])
        ->assertSet('notFound', true)
        ->assertSet('record', null)
        ->assertSee('Record tidak ditemukan');
});

it('cakupan mill: Admin tetap bisa membuka record mill mana pun', function () {
    $theirs = SterilizerRecord::factory()->forStation($this->otherStation)->create();
    $admin = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->businessUnit)->create();

    Livewire::actingAs($admin)
        ->test(DetailSterilizer::class, ['id' => $theirs->id])
        ->assertSet('notFound', false)
        ->assertSet('record', fn ($record) => is_array($record) && $record['id'] === $theirs->id);
});
