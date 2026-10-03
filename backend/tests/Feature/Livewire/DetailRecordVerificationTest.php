<?php

/**
 * DetailRecordVerificationTest — the direct approve/un-approve action on the
 * Detail screens (2026-09-14 product decision).
 *
 * Before this, verification could only be set by opening the Form in edit
 * mode and re-saving the whole record; the Detail screens now carry the
 * action themselves via App\Livewire\Data\Concerns\HandlesRecordVerification
 * + App\Services\RecordVerificationService.
 *
 * Cages Track is used as the representative station (it exposes BOTH
 * levels), plus Grading as the documented exception that never collects
 * Checked By. The behaviour is shared by all 18 Detail components through
 * the trait, so it is tested once here rather than duplicated 18 times.
 */

use App\Enums\PeriodStatus;
use App\Enums\StationType;
use App\Enums\UserRole;
use App\Livewire\Data\DetailCagesTrack;
use App\Livewire\Data\DetailGrading;
use App\Models\BusinessUnit;
use App\Models\CagesTrackRecord;
use App\Models\GradingRecord;
use App\Models\PeriodStation;
use App\Models\Station;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->record = CagesTrackRecord::factory()->forStation($this->station)->create([
        'checked_by' => null,
        'acknowledged_by' => null,
    ]);

    // FIXTURE DIPERBAIKI 2026-09-28. Tanpa forBusinessUnit() aktor-aktor ini
    // lahir di MILL LAIN (default UserFactory membuat BusinessUnit baru), dan
    // test tetap hijau justru karena setVerification() belum memeriksa mill
    // sama sekali. Fixture-nya yang salah, bukan asersinya. Admin sengaja
    // dibiarkan apa adanya: Admin dinilai dari PERAN, kolom mill-nya diabaikan.
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnit)->create();

    // Mill kedua + record-nya: pembanding untuk test cakupan mill di bawah.
    $this->otherBusinessUnit = BusinessUnit::factory()->create();
    $this->otherStation = Station::factory()->forBusinessUnit($this->otherBusinessUnit)->create();
    $this->otherRecord = CagesTrackRecord::factory()->forStation($this->otherStation)->create([
        'checked_by' => null,
        'acknowledged_by' => null,
    ]);
    $this->admin = User::factory()->role(UserRole::Admin)->create();

    // Prasyarat kunci periode (usecase-141): verifikasi adalah jalur tulis
    // KEEMPAT dan ikut terkunci bersama penutupan periode — menyetujui atau
    // membatalkan persetujuan menggeser angka yang dibaca laporan periode sama
    // nyatanya dengan mengubah nilainya. Berkas ini menguji aturan verifikasinya
    // (peran + cakupan mill), bukan kunci periodenya, jadi prasyaratnya dipenuhi
    // di sini untuk KEDUA mill.
    //
    // openPeriodForStation() membaca tipe dari stasiunnya sendiri, dan itu
    // penting di berkas ini: $this->station dibuat TANPA tipe sehingga tipenya
    // acak, sementara record-nya CagesTrackRecord. Guard-nya mempercayai stasiun
    // milik record, jadi periodenya harus dibuka untuk tipe stasiun itu — bukan
    // untuk 'cages-track'.
    openPeriodForStation($this->station);
    openPeriodForStation($this->otherStation);
});

it('supervisor: approves Checked By straight from the Detail screen, storing their own id', function () {
    Livewire::actingAs($this->supervisor)
        ->test(DetailCagesTrack::class, ['id' => $this->record->id])
        ->assertSee('Tandai sudah diperiksa (Checked)')
        ->call('toggleChecked')
        ->assertSee('Batalkan tanda diperiksa');

    expect($this->record->fresh()->checked_by)->toBe($this->supervisor->id);
});

it('supervisor: un-approves again, clearing the column back to null', function () {
    $this->record->update(['checked_by' => $this->supervisor->id]);

    Livewire::actingAs($this->supervisor)
        ->test(DetailCagesTrack::class, ['id' => $this->record->id])
        ->call('toggleChecked');

    expect($this->record->fresh()->checked_by)->toBeNull();
});

it('mill management: approves Acknowledged By, and never sees the Checked By action', function () {
    Livewire::actingAs($this->millManagement)
        ->test(DetailCagesTrack::class, ['id' => $this->record->id])
        ->assertSee('Tandai sudah dikonfirmasi (Acknowledged)')
        ->assertDontSee('Tandai sudah diperiksa (Checked)')
        ->call('toggleAcknowledged');

    expect($this->record->fresh()->acknowledged_by)->toBe($this->millManagement->id);
});

it('admin: may write BOTH levels (product decision 2026-09-14)', function () {
    Livewire::actingAs($this->admin)
        ->test(DetailCagesTrack::class, ['id' => $this->record->id])
        ->assertSee('Tandai sudah diperiksa (Checked)')
        ->assertSee('Tandai sudah dikonfirmasi (Acknowledged)')
        ->call('toggleChecked')
        ->call('toggleAcknowledged');

    $fresh = $this->record->fresh();
    expect($fresh->checked_by)->toBe($this->admin->id);
    expect($fresh->acknowledged_by)->toBe($this->admin->id);
});

it('supervisor cannot write Acknowledged By even by calling the action directly', function () {
    Livewire::actingAs($this->supervisor)
        ->test(DetailCagesTrack::class, ['id' => $this->record->id])
        ->call('toggleAcknowledged')
        ->assertSee('Anda tidak berhak melakukan verifikasi ini.');

    expect($this->record->fresh()->acknowledged_by)->toBeNull();
});

it('mill management cannot write Checked By even by calling the action directly', function () {
    Livewire::actingAs($this->millManagement)
        ->test(DetailCagesTrack::class, ['id' => $this->record->id])
        ->call('toggleChecked')
        ->assertSee('Anda tidak berhak melakukan verifikasi ini.');

    expect($this->record->fresh()->checked_by)->toBeNull();
});

// Grading never collects Checked By — see GradingRecordService::applyVerification()
// and RecordVerificationService::NO_CHECKED_BY_MODELS.
it('grading: no Checked By action for anyone, not even Admin; Acknowledged By still works', function () {
    $grading = GradingRecord::factory()
        ->forStation($this->station)
        ->create(['acknowledged_by' => null]);

    Livewire::actingAs($this->admin)
        ->test(DetailGrading::class, ['id' => $grading->id])
        ->assertDontSee('Tandai sudah diperiksa (Checked)')
        ->assertSee('Tandai sudah dikonfirmasi (Acknowledged)')
        ->call('toggleChecked')
        ->assertSee('Anda tidak berhak melakukan verifikasi ini.');

    expect($grading->fresh()->checked_by)->toBeNull();

    Livewire::actingAs($this->admin)
        ->test(DetailGrading::class, ['id' => $grading->id])
        ->call('toggleAcknowledged');

    expect($grading->fresh()->acknowledged_by)->toBe($this->admin->id);
});

it('approving touches ONLY the verification column, leaving record data untouched', function () {
    $before = $this->record->fresh()->only(['cages_track_number', 'cages_out', 'cages_tipped', 'note', 'status']);

    Livewire::actingAs($this->supervisor)
        ->test(DetailCagesTrack::class, ['id' => $this->record->id])
        ->call('toggleChecked');

    expect($this->record->fresh()->only(array_keys($before)))->toBe($before);
});

// ─── CAKUPAN MILL PADA VERIFIKASI (2026-09-28) ─────────────────────────────
// Di sisi Livewire ada DUA lapisan, dan keduanya diuji di sini:
//
//  1. getDetail() sudah tercakup mill, jadi record Mill B tidak pernah
//     termuat di layar Detail Mill A — tombol verifikasinya tidak ada untuk
//     ditekan. Ini lapisan pertama, bukan penjaganya.
//  2. Penjaga sesungguhnya ada di RecordVerificationService::setVerification().
//     Kalau toggle tetap dipanggil langsung (Livewire memanggilnya per aksi,
//     bukan per render), penolakannya harus muncul sebagai ALERT DI LAYAR —
//     pola yang sama dipakai ke-18 Form*::save() sejak tahap 1a — bukan
//     halaman 403.
//
// Vektor lengkap end-to-end-nya ada di tests/Feature/Api/RecordVerificationTest.php.

it('cakupan mill: layar Detail Mill A tidak memuat record Mill B sama sekali', function () {
    Livewire::actingAs($this->supervisor)
        ->test(DetailCagesTrack::class, ['id' => $this->otherRecord->id])
        ->assertSet('notFound', true)
        ->assertDontSee('Tandai sudah diperiksa (Checked)');

    expect($this->otherRecord->fresh()->checked_by)->toBeNull();
});

it('cakupan mill: memanggil toggleChecked() langsung pada record Mill B ditolak sebagai alert layar, dan kolomnya tidak berubah', function () {
    $rightfulChecker = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->otherBusinessUnit)->create();
    $this->otherRecord->update(['checked_by' => $rightfulChecker->id]);

    // Layar dibuka oleh Admin (yang boleh melihat lintas mill) supaya $record
    // terisi, lalu aksinya dijalankan sebagai Supervisor Mill A — meniru
    // request Livewire yang dibuat-buat, tanpa melewati mount() lagi.
    $component = Livewire::actingAs($this->admin)
        ->test(DetailCagesTrack::class, ['id' => $this->otherRecord->id])
        ->assertSet('notFound', false);

    $this->actingAs($this->supervisor);

    $component->call('toggleChecked')
        ->assertSet('verificationMessage', 'Record ini milik mill lain, Anda tidak dapat mengubahnya.');

    // Tanda sah milik Mill B utuh — penolakan tidak menulis apa pun.
    expect($this->otherRecord->fresh()->checked_by)->toBe($rightfulChecker->id);
});

it('cakupan mill: verifikasi record mill sendiri lewat layar Detail tetap berhasil', function () {
    Livewire::actingAs($this->supervisor)
        ->test(DetailCagesTrack::class, ['id' => $this->record->id])
        ->call('toggleChecked')
        ->assertSet('verificationMessage', 'Data ditandai sudah diperiksa.');

    expect($this->record->fresh()->checked_by)->toBe($this->supervisor->id);
});

// Kunci periode (usecase-141) pada jalur verifikasi, DILIHAT DARI LAYAR.
// Penolakannya sendiri sudah diuji di tests/Feature/Api/RecordVerificationTest.php;
// yang diuji di sini adalah bahwa layar Detail MENAMPILKANNYA. Sebelum
// 2026-10-03 PeriodClosedException (HttpException 422) lolos dari catch di
// HandlesRecordVerification, sehingga layar menerima respons 422 mentah dan
// verificationMessage tetap null — datanya aman, pesannya hilang.
it('kunci periode: verifikasi saat stasiun pada periode sudah ditutup muncul sebagai alert layar, dan kolomnya tidak berubah', function () {
    PeriodStation::query()
        ->where('station_type', $this->station->type instanceof StationType ? $this->station->type->value : (string) $this->station->type)
        ->whereHas('period', fn ($q) => $q->where('business_unit_id', $this->businessUnit->id))
        ->update(['status' => PeriodStatus::Closed->value]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(DetailCagesTrack::class, ['id' => $this->record->id])
        ->call('toggleChecked')
        ->assertStatus(200);

    expect($component->get('verificationMessage'))->toContain('sudah ditutup');
    expect($this->record->fresh()->checked_by)->toBeNull();
});
