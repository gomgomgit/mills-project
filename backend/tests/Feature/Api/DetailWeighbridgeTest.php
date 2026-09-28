<?php

/**
 * DetailWeighbridgeTest (Feature/Api) — screen-019--detail-weighbridge-web /
 * usecase-019--detail-weighbridge-web.
 *
 * Integration tests for GET /api/weighbridge-records/{id}
 * (App\Http\Controllers\Api\WeighbridgeRecordController::show()), one per
 * test_scenarios' api_test step(s). Exercises the real route -> 'auth:web'
 * + 'role' middleware -> controller -> WeighbridgeRecordService -> Eloquent
 * chain against the sqlite in-memory testing DB, mirroring
 * DataBrowserWeighbridgeTest.php's setup/conventions.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\Station;
use App\Models\User;
use App\Models\WeighbridgeRecord;

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

// Scenario: "Lihat Detail Weighbridge — berhasil"
it('berhasil: returns the full record with resolved names', function () {
    $checker = User::factory()->create(['name' => 'Budi Supervisor']);
    $record = WeighbridgeRecord::factory()
        ->forStation($this->station)
        ->ofType('dispatch')
        ->create(['checked_by' => $checker->id, 'destination' => 'PKS Sukamaju']);

    $response = $this->actingAs($this->supervisor, 'web')->getJson("/api/weighbridge-records/{$record->id}");

    $response->assertOk();
    $response->assertJsonFragment([
        'id' => $record->id,
        'weighbridge_type' => 'dispatch',
        'destination' => 'PKS Sukamaju',
        'checked_by_name' => 'Budi Supervisor',
        'station_name' => $this->station->name,
    ]);
});

it('Mill Management and Admin can also access the detail endpoint', function () {
    $record = WeighbridgeRecord::factory()->forStation($this->station)->create();

    $this->actingAs($this->millManagement, 'web')->getJson("/api/weighbridge-records/{$record->id}")->assertOk();
    $this->actingAs($this->admin, 'web')->getJson("/api/weighbridge-records/{$record->id}")->assertOk();
});

it('returns 403 for the Operator role (route-level role gate)', function () {
    $record = WeighbridgeRecord::factory()->forStation($this->station)->create();

    $response = $this->actingAs($this->operator, 'web')->getJson("/api/weighbridge-records/{$record->id}");

    $response->assertStatus(403);
});

// Scenario: "Lihat Detail Weighbridge — Record Tidak Ditemukan"
it('returns 404 when the id does not exist', function () {
    $response = $this->actingAs($this->admin, 'web')->getJson('/api/weighbridge-records/00000000-0000-0000-0000-000000000000');

    $response->assertStatus(404);
});

it('returns null checked_by_name/acknowledged_by_name and station_name still resolves when not set', function () {
    $record = WeighbridgeRecord::factory()->forStation($this->station)->create(['checked_by' => null, 'acknowledged_by' => null]);

    $response = $this->actingAs($this->admin, 'web')->getJson("/api/weighbridge-records/{$record->id}");

    $response->assertOk();
    $response->assertJsonFragment(['checked_by_name' => null, 'acknowledged_by_name' => null]);
});

it('rejects unauthenticated requests', function () {
    $record = WeighbridgeRecord::factory()->forStation($this->station)->create();

    $response = $this->getJson("/api/weighbridge-records/{$record->id}");

    $response->assertStatus(401);
});

// ─── CAKUPAN MILL (2026-09-28) ──────────────────────────────────────────────
// Sampai hari ini getDetail() hanya findOrFail() tanpa cakupan mill apa pun:
// UUID record mill lain mengembalikan RECORD LENGKAP, bukan 404. Sekarang
// cakupannya ada di QUERY, jadi record itu tidak ada sama sekali bagi aktor
// mill lain — 404, dan tidak ada konfirmasi bahwa record itu memang ada.
it('cakupan mill: UUID record Mill B mengembalikan 404 bagi Supervisor Mill A, bukan datanya', function () {
    $theirs = WeighbridgeRecord::factory()->forStation($this->otherStation)->create();

    $response = $this->actingAs($this->supervisor, 'web')->getJson("/api/weighbridge-records/{$theirs->id}");

    $response->assertStatus(404);
    $response->assertJsonPath('code', 'NOT_FOUND');
    // ISI, bukan sekadar status: tidak satu pun field record itu ikut keluar.
    $response->assertJsonMissing(['id' => $theirs->id]);
});

it('cakupan mill: Admin tetap bisa membaca record mill mana pun', function () {
    $theirs = WeighbridgeRecord::factory()->forStation($this->otherStation)->create();

    $this->actingAs($this->admin, 'web')->getJson("/api/weighbridge-records/{$theirs->id}")
        ->assertOk()
        ->assertJsonFragment(['id' => $theirs->id]);
});

it('cakupan mill: aktor terikat mill tanpa business_unit_id gagal-tertutup 422', function () {
    $record = WeighbridgeRecord::factory()->forStation($this->station)->create();
    $millless = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $this->actingAs($millless, 'web')->getJson("/api/weighbridge-records/{$record->id}")
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonMissing(['id' => $record->id]);
});
