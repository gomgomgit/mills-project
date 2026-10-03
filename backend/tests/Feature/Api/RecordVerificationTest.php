<?php

/**
 * RecordVerificationTest (Feature/Api) — PATCH
 * /api/records/{stationType}/{id}/verification, the mobile counterpart of
 * the Detail screens' approve/un-approve action (2026-09-14).
 *
 * One generic endpoint serves all 18 stations, so the role rule, the
 * station-type whitelist, and the "only touches the verification column"
 * guarantee are tested here once rather than per station.
 */

use App\Enums\PeriodStatus;
use App\Enums\StationType;
use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\CagesTrackRecord;
use App\Models\GradingRecord;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\Station;
use App\Models\User;

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
    $this->operator = User::factory()->role(UserRole::Operator)->create();

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

function verifyUrl(string $stationType, string $id): string
{
    return "/api/records/{$stationType}/{$id}/verification";
}

it('supervisor sets checked, and the response echoes the resolved name', function () {
    $this->actingAs($this->supervisor, 'web')
        ->patchJson(verifyUrl('cages-track', $this->record->id), ['level' => 'checked', 'value' => true])
        ->assertOk()
        ->assertJsonPath('checked_by', $this->supervisor->id)
        ->assertJsonPath('checked_by_name', $this->supervisor->name);

    expect($this->record->fresh()->checked_by)->toBe($this->supervisor->id);
});

it('supervisor clears checked again with value=false', function () {
    $this->record->update(['checked_by' => $this->supervisor->id]);

    $this->actingAs($this->supervisor, 'web')
        ->patchJson(verifyUrl('cages-track', $this->record->id), ['level' => 'checked', 'value' => false])
        ->assertOk()
        ->assertJsonPath('checked_by', null);

    expect($this->record->fresh()->checked_by)->toBeNull();
});

it('mill management sets acknowledged but is refused for checked', function () {
    $this->actingAs($this->millManagement, 'web')
        ->patchJson(verifyUrl('cages-track', $this->record->id), ['level' => 'acknowledged', 'value' => true])
        ->assertOk();

    $this->actingAs($this->millManagement, 'web')
        ->patchJson(verifyUrl('cages-track', $this->record->id), ['level' => 'checked', 'value' => true])
        ->assertForbidden();

    $fresh = $this->record->fresh();
    expect($fresh->acknowledged_by)->toBe($this->millManagement->id);
    expect($fresh->checked_by)->toBeNull();
});

it('admin may set both levels', function () {
    $this->actingAs($this->admin, 'web')
        ->patchJson(verifyUrl('cages-track', $this->record->id), ['level' => 'checked', 'value' => true])
        ->assertOk();
    $this->actingAs($this->admin, 'web')
        ->patchJson(verifyUrl('cages-track', $this->record->id), ['level' => 'acknowledged', 'value' => true])
        ->assertOk();

    $fresh = $this->record->fresh();
    expect($fresh->checked_by)->toBe($this->admin->id);
    expect($fresh->acknowledged_by)->toBe($this->admin->id);
});

it('operator is rejected by the route guard before reaching the service', function () {
    $this->actingAs($this->operator, 'web')
        ->patchJson(verifyUrl('cages-track', $this->record->id), ['level' => 'checked', 'value' => true])
        ->assertForbidden();

    expect($this->record->fresh()->checked_by)->toBeNull();
});

it('rejects an unauthenticated request', function () {
    $this->patchJson(verifyUrl('cages-track', $this->record->id), ['level' => 'checked', 'value' => true])
        ->assertUnauthorized();
});

it('grading refuses checked for everyone, acknowledged still works', function () {
    $grading = GradingRecord::factory()->forStation($this->station)->create(['acknowledged_by' => null]);

    $this->actingAs($this->admin, 'web')
        ->patchJson(verifyUrl('grading', $grading->id), ['level' => 'checked', 'value' => true])
        ->assertForbidden();

    $this->actingAs($this->admin, 'web')
        ->patchJson(verifyUrl('grading', $grading->id), ['level' => 'acknowledged', 'value' => true])
        ->assertOk();

    $fresh = $grading->fresh();
    expect($fresh->checked_by)->toBeNull();
    expect($fresh->acknowledged_by)->toBe($this->admin->id);
});

it('returns 404 for an unknown station type instead of resolving a class from input', function () {
    $this->actingAs($this->admin, 'web')
        ->patchJson(verifyUrl('User', $this->record->id), ['level' => 'checked', 'value' => true])
        ->assertNotFound();
});

it('returns 404 for a record id that does not exist', function () {
    $this->actingAs($this->supervisor, 'web')
        ->patchJson(verifyUrl('cages-track', '00000000-0000-0000-0000-000000000000'), ['level' => 'checked', 'value' => true])
        ->assertNotFound();
});

it('validates the payload', function () {
    $this->actingAs($this->supervisor, 'web')
        ->patchJson(verifyUrl('cages-track', $this->record->id), ['level' => 'approved', 'value' => true])
        ->assertStatus(422);

    $this->actingAs($this->supervisor, 'web')
        ->patchJson(verifyUrl('cages-track', $this->record->id), ['level' => 'checked'])
        ->assertStatus(422);
});

it('never touches record data, only the verification column', function () {
    $before = $this->record->fresh()->only(['cages_track_number', 'cages_out', 'cages_tipped', 'note', 'status']);

    $this->actingAs($this->supervisor, 'web')
        ->patchJson(verifyUrl('cages-track', $this->record->id), ['level' => 'checked', 'value' => true])
        ->assertOk();

    expect($this->record->fresh()->only(array_keys($before)))->toBe($before);
});

// ─── CAKUPAN MILL PADA VERIFIKASI (2026-09-28) ─────────────────────────────
// setVerification() sampai hari ini hanya memeriksa PERAN — nol pemeriksaan
// mill — sehingga Supervisor Mill A yang tahu UUID sebuah record Mill B bisa
// menyetujuinya, ATAU membatalkan persetujuan yang sudah sah di sana. Ini
// jalur tulis KEEMPAT, setara create()/update() yang ditutup tahap 1a.
//
// Endpoint API adalah vektornya yang sesungguhnya: ia tidak melewati
// getDetail(), jadi UUID lintas mill benar-benar sampai ke setVerification().
// Asersinya membandingkan NILAI KOLOM sebelum-sesudah, bukan sekadar status
// respons — penolakan yang tetap menulis kolom adalah kebocoran yang sama.

it('cakupan mill: Supervisor Mill A ditolak saat menyetujui record Mill B, dan kolomnya tidak berubah', function () {
    $before = $this->otherRecord->fresh()->only(['checked_by', 'acknowledged_by']);

    $this->actingAs($this->supervisor, 'web')
        ->patchJson(verifyUrl('cages-track', $this->otherRecord->id), ['level' => 'checked', 'value' => true])
        ->assertStatus(403)
        ->assertJsonPath('code', 'FORBIDDEN');

    expect($this->otherRecord->fresh()->only(['checked_by', 'acknowledged_by']))->toBe($before);
});

it('cakupan mill: Supervisor Mill A ditolak saat MEMBATALKAN persetujuan record Mill B, dan tanda lama tetap utuh', function () {
    // Arah `false` sama merusaknya: menghapus atestasi sah milik mill lain.
    $rightfulChecker = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->otherBusinessUnit)->create();
    $this->otherRecord->update(['checked_by' => $rightfulChecker->id]);

    $this->actingAs($this->supervisor, 'web')
        ->patchJson(verifyUrl('cages-track', $this->otherRecord->id), ['level' => 'checked', 'value' => false])
        ->assertStatus(403);

    expect($this->otherRecord->fresh()->checked_by)->toBe($rightfulChecker->id);
});

it('cakupan mill: Mill Management Mill A ditolak saat mengonfirmasi record Mill B, dan kolomnya tidak berubah', function () {
    $before = $this->otherRecord->fresh()->only(['checked_by', 'acknowledged_by']);

    $this->actingAs($this->millManagement, 'web')
        ->patchJson(verifyUrl('cages-track', $this->otherRecord->id), ['level' => 'acknowledged', 'value' => true])
        ->assertStatus(403);

    expect($this->otherRecord->fresh()->only(['checked_by', 'acknowledged_by']))->toBe($before);
});

it('cakupan mill: verifikasi record mill SENDIRI tetap berhasil (penjaga tidak memutus fiturnya)', function () {
    $this->actingAs($this->supervisor, 'web')
        ->patchJson(verifyUrl('cages-track', $this->record->id), ['level' => 'checked', 'value' => true])
        ->assertOk();

    expect($this->record->fresh()->checked_by)->toBe($this->supervisor->id);

    $this->actingAs($this->millManagement, 'web')
        ->patchJson(verifyUrl('cages-track', $this->record->id), ['level' => 'acknowledged', 'value' => true])
        ->assertOk();

    expect($this->record->fresh()->acknowledged_by)->toBe($this->millManagement->id);
});

it('cakupan mill: Admin boleh memverifikasi di DUA mill berbeda', function () {
    $this->actingAs($this->admin, 'web')
        ->patchJson(verifyUrl('cages-track', $this->record->id), ['level' => 'checked', 'value' => true])
        ->assertOk();

    $this->actingAs($this->admin, 'web')
        ->patchJson(verifyUrl('cages-track', $this->otherRecord->id), ['level' => 'checked', 'value' => true])
        ->assertOk();

    expect($this->record->fresh()->checked_by)->toBe($this->admin->id);
    expect($this->otherRecord->fresh()->checked_by)->toBe($this->admin->id);
});

it('cakupan mill: aktor terikat mill tanpa business_unit_id gagal-tertutup 422, dan tidak menulis apa pun', function () {
    $millless = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);
    $before = $this->record->fresh()->only(['checked_by', 'acknowledged_by']);

    $this->actingAs($millless, 'web')
        ->patchJson(verifyUrl('cages-track', $this->record->id), ['level' => 'checked', 'value' => true])
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR');

    expect($this->record->fresh()->only(['checked_by', 'acknowledged_by']))->toBe($before);
});

// Grading adalah satu-satunya BENTUK jalur verifikasi yang berbeda (tidak
// pernah punya Checked By — RecordVerificationService::NO_CHECKED_BY_MODELS).
// Diuji sekali di sini untuk membuktikan penjaga mill berlaku juga pada
// bentuk itu; 16 stasiun sisanya dilayani trait/service yang sama persis,
// jadi 18 salinan identik tidak menambah informasi apa pun.
it('cakupan mill: Grading (bentuk verifikasi berbeda) juga dijaga — Mill Management Mill A ditolak di record Mill B', function () {
    $grading = GradingRecord::factory()->forStation($this->otherStation)->create(['acknowledged_by' => null]);

    $this->actingAs($this->millManagement, 'web')
        ->patchJson(verifyUrl('grading', $grading->id), ['level' => 'acknowledged', 'value' => true])
        ->assertStatus(403);

    expect($grading->fresh()->acknowledged_by)->toBeNull();
});

// ── kunci periode pada jalur verifikasi (usecase-141) ───────────────────────
//
// Jalur ini sempat LUPUT pada implementasi pertama kunci periode: ia tidak lewat
// satu pun dari 18 *RecordService, jadi guard di sana tidak menutupinya. Dua test
// di bawah menguji PERILAKU-nya; keberadaan pemanggilannya dijaga secara
// struktural di tests/Unit/Support/EnforcesPeriodLockTest.php.

it('menolak verifikasi 422 PERIOD_CLOSED ketika stasiun pada periode sudah ditutup', function () {
    $stationType = $this->station->type instanceof StationType
        ? $this->station->type->value
        : (string) $this->station->type;

    // Tutup baris stasiun pada periode prasyarat yang dibuka beforeEach.
    PeriodStation::query()
        ->whereIn('period_id', Period::query()
            ->where('business_unit_id', $this->businessUnit->id)->pluck('id'))
        ->where('station_type', $stationType)
        ->update(['status' => PeriodStatus::Closed->value]);

    $response = $this->actingAs($this->supervisor, 'web')
        ->patchJson(verifyUrl('cages-track', $this->record->id), ['level' => 'checked', 'value' => true]);

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'PERIOD_CLOSED');

    // Atestasinya tidak bergerak sedikit pun.
    expect($this->record->fresh()->checked_by)->toBeNull();
});

it('menolak PEMBATALAN verifikasi juga, karena arah false ikut menggeser angka laporan', function () {
    $stationType = $this->station->type instanceof StationType
        ? $this->station->type->value
        : (string) $this->station->type;

    // Verifikasi dulu selagi periodenya masih terbuka.
    $this->actingAs($this->supervisor, 'web')
        ->patchJson(verifyUrl('cages-track', $this->record->id), ['level' => 'checked', 'value' => true])
        ->assertOk();

    expect($this->record->fresh()->checked_by)->toBe($this->supervisor->id);

    PeriodStation::query()
        ->whereIn('period_id', Period::query()
            ->where('business_unit_id', $this->businessUnit->id)->pluck('id'))
        ->where('station_type', $stationType)
        ->update(['status' => PeriodStatus::Closed->value]);

    // Membatalkan atestasi yang sah sama merusaknya dengan menambahkannya:
    // keduanya mengubah apa yang dibaca laporan periode yang sudah ditutup.
    $response = $this->actingAs($this->supervisor, 'web')
        ->patchJson(verifyUrl('cages-track', $this->record->id), ['level' => 'checked', 'value' => false]);

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'PERIOD_CLOSED');
    expect($this->record->fresh()->checked_by)->toBe($this->supervisor->id);
});
