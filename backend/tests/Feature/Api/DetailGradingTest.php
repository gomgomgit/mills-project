<?php

/**
 * DetailGradingTest (Feature/Api) — screen-020--detail-grading-web /
 * usecase-020--detail-grading-web.
 *
 * Integration tests for GET /api/grading-records/{id}
 * (App\Http\Controllers\Api\GradingRecordController::show()), one per
 * test_scenarios' api_test step(s). Exercises the real route -> 'auth:web'
 * + 'role' middleware -> controller -> GradingRecordService -> Eloquent
 * chain against the sqlite in-memory testing DB, mirroring
 * DetailWeighbridgeTest.php's setup/conventions.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\GradingDetail;
use App\Models\GradingParameter;
use App\Models\GradingRecord;
use App\Models\Station;
use App\Models\User;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    // FIXTURE DIPERBAIKI 2026-09-28. Tanpa forBusinessUnit() ketiga aktor
    // terikat mill ini lahir di MILL LAIN (default UserFactory membuat
    // BusinessUnit baru), dan test tetap hijau justru karena jalur baca ini
    // belum punya cakupan mill. Fixture-nya yang salah, bukan asersinya.
    // Admin sengaja dibiarkan apa adanya: Admin dinilai dari PERAN, kolom
    // `business_unit_id`-nya memang diabaikan.
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnit)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnit)->create();

    // Mill kedua + stasiunnya: pembanding untuk test cakupan mill di bawah.
    $this->otherBusinessUnit = BusinessUnit::factory()->create();
    $this->otherStation = Station::factory()->forBusinessUnit($this->otherBusinessUnit)->create();
});

// Scenario: "Lihat Detail Grading — berhasil"
it('berhasil: returns the full record with resolved names and details grid', function () {
    $record = GradingRecord::factory()->forStation($this->station)->create();
    $parameter = GradingParameter::factory()->create(['name' => 'Masak']);
    GradingDetail::factory()->forGradingRecord($record)->forGradingParameter($parameter)->create();

    $response = $this->actingAs($this->supervisor, 'web')->getJson("/api/grading-records/{$record->id}");

    $response->assertOk();
    $response->assertJsonFragment([
        'id' => $record->id,
        'grading_number' => $record->grading_number,
        'station_name' => $this->station->name,
    ]);
    $response->assertJsonFragment(['grading_parameter_name' => 'Masak']);
});

it('Mill Management and Admin can also access the detail endpoint', function () {
    $record = GradingRecord::factory()->forStation($this->station)->create();

    $this->actingAs($this->millManagement, 'web')->getJson("/api/grading-records/{$record->id}")->assertOk();
    $this->actingAs($this->admin, 'web')->getJson("/api/grading-records/{$record->id}")->assertOk();
});

it('returns 403 for the Operator role (route-level role gate)', function () {
    $record = GradingRecord::factory()->forStation($this->station)->create();

    $response = $this->actingAs($this->operator, 'web')->getJson("/api/grading-records/{$record->id}");

    $response->assertStatus(403);
});

// Scenario: "Lihat Detail Grading — Record Tidak Ditemukan"
it('returns 404 when the id does not exist', function () {
    $response = $this->actingAs($this->admin, 'web')->getJson('/api/grading-records/00000000-0000-0000-0000-000000000000');

    $response->assertStatus(404);
});

it('returns null acknowledged_by_name when not set', function () {
    $record = GradingRecord::factory()->forStation($this->station)->create(['acknowledged_by' => null]);

    $response = $this->actingAs($this->admin, 'web')->getJson("/api/grading-records/{$record->id}");

    $response->assertOk();
    $response->assertJsonFragment(['acknowledged_by_name' => null]);
});

it('rejects unauthenticated requests', function () {
    $record = GradingRecord::factory()->forStation($this->station)->create();

    $response = $this->getJson("/api/grading-records/{$record->id}");

    $response->assertStatus(401);
});

// ─── CAKUPAN MILL (2026-09-28) ──────────────────────────────────────────────
// Sampai hari ini getDetail() hanya findOrFail() tanpa cakupan mill apa pun:
// UUID record mill lain mengembalikan RECORD LENGKAP, bukan 404. Sekarang
// cakupannya ada di QUERY, jadi record itu tidak ada sama sekali bagi aktor
// mill lain — 404, dan tidak ada konfirmasi bahwa record itu memang ada.
it('cakupan mill: UUID record Mill B mengembalikan 404 bagi Supervisor Mill A, bukan datanya', function () {
    $theirs = GradingRecord::factory()->forStation($this->otherStation)->create();

    $response = $this->actingAs($this->supervisor, 'web')->getJson("/api/grading-records/{$theirs->id}");

    $response->assertStatus(404);
    $response->assertJsonPath('code', 'NOT_FOUND');
    // ISI, bukan sekadar status: tidak satu pun field record itu ikut keluar.
    $response->assertJsonMissing(['id' => $theirs->id]);
});

it('cakupan mill: Admin tetap bisa membaca record mill mana pun', function () {
    $theirs = GradingRecord::factory()->forStation($this->otherStation)->create();

    $this->actingAs($this->admin, 'web')->getJson("/api/grading-records/{$theirs->id}")
        ->assertOk()
        ->assertJsonFragment(['id' => $theirs->id]);
});

it('cakupan mill: aktor terikat mill tanpa business_unit_id gagal-tertutup 422', function () {
    $record = GradingRecord::factory()->forStation($this->station)->create();
    $millless = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $this->actingAs($millless, 'web')->getJson("/api/grading-records/{$record->id}")
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonMissing(['id' => $record->id]);
});
