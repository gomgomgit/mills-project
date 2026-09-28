<?php

/**
 * DetailCagesTrackTest (Feature/Api) — screen-021--detail-cages-track-web /
 * usecase-021--detail-cages-track-web.
 *
 * Integration tests for GET /api/cages-track-records/{id}
 * (App\Http\Controllers\Api\CagesTrackRecordController::show()), one per
 * test_scenarios' api_test step(s). Exercises the real route -> 'auth:web'
 * + 'role' middleware -> controller -> CagesTrackRecordService -> Eloquent
 * chain against the sqlite in-memory testing DB, mirroring
 * DetailGradingTest.php's setup/conventions.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\CagesTippedTime;
use App\Models\CagesTrackRecord;
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

// Scenario: "Lihat Detail Cages Track - berhasil"
it('berhasil: returns the full record with resolved names and tipped_times grid', function () {
    $record = CagesTrackRecord::factory()->forStation($this->station)->create();
    CagesTippedTime::factory()->forRecord($record)->create(['tipped_hour' => 9]);

    $response = $this->actingAs($this->supervisor, 'web')->getJson("/api/cages-track-records/{$record->id}");

    $response->assertOk();
    $response->assertJsonFragment([
        'id' => $record->id,
        'cages_track_number' => $record->cages_track_number,
        'station_name' => $this->station->name,
    ]);
    $response->assertJsonFragment(['tipped_hour' => 9]);
});

it('returns tipped_times ordered by tipped_hour regardless of creation order', function () {
    $record = CagesTrackRecord::factory()->forStation($this->station)->create();
    CagesTippedTime::factory()->forRecord($record)->create(['tipped_hour' => 14]);
    CagesTippedTime::factory()->forRecord($record)->create(['tipped_hour' => 8]);

    $response = $this->actingAs($this->admin, 'web')->getJson("/api/cages-track-records/{$record->id}");

    $response->assertOk();
    $hours = array_column($response->json('tipped_times'), 'tipped_hour');
    expect($hours)->toBe([8, 14]);
});

it('Mill Management and Admin can also access the detail endpoint', function () {
    $record = CagesTrackRecord::factory()->forStation($this->station)->create();

    $this->actingAs($this->millManagement, 'web')->getJson("/api/cages-track-records/{$record->id}")->assertOk();
    $this->actingAs($this->admin, 'web')->getJson("/api/cages-track-records/{$record->id}")->assertOk();
});

it('returns 403 for the Operator role (route-level role gate)', function () {
    $record = CagesTrackRecord::factory()->forStation($this->station)->create();

    $response = $this->actingAs($this->operator, 'web')->getJson("/api/cages-track-records/{$record->id}");

    $response->assertStatus(403);
});

// Scenario: "Lihat Detail Cages Track - Record Tidak Ditemukan"
it('returns 404 when the id does not exist', function () {
    $response = $this->actingAs($this->admin, 'web')->getJson('/api/cages-track-records/00000000-0000-0000-0000-000000000000');

    $response->assertStatus(404);
});

it('returns null checked_by_name and acknowledged_by_name when not set', function () {
    $record = CagesTrackRecord::factory()->forStation($this->station)->create(['checked_by' => null, 'acknowledged_by' => null]);

    $response = $this->actingAs($this->admin, 'web')->getJson("/api/cages-track-records/{$record->id}");

    $response->assertOk();
    $response->assertJsonFragment(['checked_by_name' => null, 'acknowledged_by_name' => null]);
});

it('resolves checked_by_name and acknowledged_by_name to user names when present', function () {
    $checker = User::factory()->role(UserRole::Supervisor)->create(['name' => 'Checker Person']);
    $acknowledger = User::factory()->role(UserRole::MillManagement)->create(['name' => 'Acknowledger Person']);
    $record = CagesTrackRecord::factory()->forStation($this->station)->create([
        'checked_by' => $checker->id,
        'acknowledged_by' => $acknowledger->id,
    ]);

    $response = $this->actingAs($this->admin, 'web')->getJson("/api/cages-track-records/{$record->id}");

    $response->assertOk();
    $response->assertJsonFragment(['checked_by_name' => 'Checker Person', 'acknowledged_by_name' => 'Acknowledger Person']);
});

it('rejects unauthenticated requests', function () {
    $record = CagesTrackRecord::factory()->forStation($this->station)->create();

    $response = $this->getJson("/api/cages-track-records/{$record->id}");

    $response->assertStatus(401);
});

// ─── CAKUPAN MILL (2026-09-28) ──────────────────────────────────────────────
// Sampai hari ini getDetail() hanya findOrFail() tanpa cakupan mill apa pun:
// UUID record mill lain mengembalikan RECORD LENGKAP, bukan 404. Sekarang
// cakupannya ada di QUERY, jadi record itu tidak ada sama sekali bagi aktor
// mill lain — 404, dan tidak ada konfirmasi bahwa record itu memang ada.
it('cakupan mill: UUID record Mill B mengembalikan 404 bagi Supervisor Mill A, bukan datanya', function () {
    $theirs = CagesTrackRecord::factory()->forStation($this->otherStation)->create();

    $response = $this->actingAs($this->supervisor, 'web')->getJson("/api/cages-track-records/{$theirs->id}");

    $response->assertStatus(404);
    $response->assertJsonPath('code', 'NOT_FOUND');
    // ISI, bukan sekadar status: tidak satu pun field record itu ikut keluar.
    $response->assertJsonMissing(['id' => $theirs->id]);
});

it('cakupan mill: Admin tetap bisa membaca record mill mana pun', function () {
    $theirs = CagesTrackRecord::factory()->forStation($this->otherStation)->create();

    $this->actingAs($this->admin, 'web')->getJson("/api/cages-track-records/{$theirs->id}")
        ->assertOk()
        ->assertJsonFragment(['id' => $theirs->id]);
});

it('cakupan mill: aktor terikat mill tanpa business_unit_id gagal-tertutup 422', function () {
    $record = CagesTrackRecord::factory()->forStation($this->station)->create();
    $millless = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $this->actingAs($millless, 'web')->getJson("/api/cages-track-records/{$record->id}")
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonMissing(['id' => $record->id]);
});
