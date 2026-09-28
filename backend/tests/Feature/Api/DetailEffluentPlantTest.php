<?php

/**
 * DetailEffluentPlantTest (Feature/Api) — screen-105--detail-effluent-plant-web /
 * usecase-089--detail-effluent-plant-web.
 *
 * Integration tests for GET /api/effluent-plant-records/{id}, mirroring
 * tests/Feature/Api/DetailThreshingTest.php's structure exactly.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\EffluentPlantDetail;
use App\Models\EffluentPlantRecord;
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

it('berhasil: returns the full record with resolved names and its details grid', function () {
    $record = EffluentPlantRecord::factory()->forStation($this->station)->create();
    EffluentPlantDetail::factory()->forRecord($record)->timeSlot('09:00')->filled()->create();

    $response = $this->actingAs($this->supervisor, 'web')->getJson("/api/effluent-plant-records/{$record->id}");

    $response->assertOk();
    $response->assertJsonFragment([
        'id' => $record->id,
        'effluent_plant_id' => $record->effluent_plant_id,
        'station_name' => $this->station->name,
    ]);
    $response->assertJsonFragment(['time_slot' => '09:00']);
});

it('returns details ordered by canonical time-slot, not creation order', function () {
    $record = EffluentPlantRecord::factory()->forStation($this->station)->create();
    EffluentPlantDetail::factory()->forRecord($record)->timeSlot('14:00')->create();
    EffluentPlantDetail::factory()->forRecord($record)->timeSlot('08:00')->create();
    EffluentPlantDetail::factory()->forRecord($record)->timeSlot('00:00')->create();

    $response = $this->actingAs($this->admin, 'web')->getJson("/api/effluent-plant-records/{$record->id}");

    $response->assertOk();
    $slots = array_column($response->json('details'), 'time_slot');
    expect($slots)->toBe(['08:00', '14:00', '00:00']);
});

it('Mill Management and Admin can also access the detail endpoint', function () {
    $record = EffluentPlantRecord::factory()->forStation($this->station)->create();

    $this->actingAs($this->millManagement, 'web')->getJson("/api/effluent-plant-records/{$record->id}")->assertOk();
    $this->actingAs($this->admin, 'web')->getJson("/api/effluent-plant-records/{$record->id}")->assertOk();
});

it('returns 403 for the Operator role (route-level role gate)', function () {
    $record = EffluentPlantRecord::factory()->forStation($this->station)->create();

    $this->actingAs($this->operator, 'web')->getJson("/api/effluent-plant-records/{$record->id}")->assertStatus(403);
});

it('returns 404 when the id does not exist', function () {
    $this->actingAs($this->admin, 'web')->getJson('/api/effluent-plant-records/00000000-0000-0000-0000-000000000000')->assertStatus(404);
});

it('returns null checked_by_name and acknowledged_by_name when not set', function () {
    $record = EffluentPlantRecord::factory()->forStation($this->station)->create(['checked_by' => null, 'acknowledged_by' => null]);

    $response = $this->actingAs($this->admin, 'web')->getJson("/api/effluent-plant-records/{$record->id}");

    $response->assertOk();
    $response->assertJsonFragment(['checked_by_name' => null, 'acknowledged_by_name' => null]);
});

it('resolves checked_by_name and acknowledged_by_name to user names when present', function () {
    $checker = User::factory()->role(UserRole::Supervisor)->create(['name' => 'Checker Person']);
    $acknowledger = User::factory()->role(UserRole::MillManagement)->create(['name' => 'Acknowledger Person']);
    $record = EffluentPlantRecord::factory()->forStation($this->station)->create([
        'checked_by' => $checker->id,
        'acknowledged_by' => $acknowledger->id,
    ]);

    $response = $this->actingAs($this->admin, 'web')->getJson("/api/effluent-plant-records/{$record->id}");

    $response->assertOk();
    $response->assertJsonFragment(['checked_by_name' => 'Checker Person', 'acknowledged_by_name' => 'Acknowledger Person']);
});

it('rejects unauthenticated requests', function () {
    $record = EffluentPlantRecord::factory()->forStation($this->station)->create();

    $this->getJson("/api/effluent-plant-records/{$record->id}")->assertStatus(401);
});

// ─── CAKUPAN MILL (2026-09-28) ──────────────────────────────────────────────
// Sampai hari ini getDetail() hanya findOrFail() tanpa cakupan mill apa pun:
// UUID record mill lain mengembalikan RECORD LENGKAP, bukan 404. Sekarang
// cakupannya ada di QUERY, jadi record itu tidak ada sama sekali bagi aktor
// mill lain — 404, dan tidak ada konfirmasi bahwa record itu memang ada.
it('cakupan mill: UUID record Mill B mengembalikan 404 bagi Supervisor Mill A, bukan datanya', function () {
    $theirs = EffluentPlantRecord::factory()->forStation($this->otherStation)->create();

    $response = $this->actingAs($this->supervisor, 'web')->getJson("/api/effluent-plant-records/{$theirs->id}");

    $response->assertStatus(404);
    $response->assertJsonPath('code', 'NOT_FOUND');
    // ISI, bukan sekadar status: tidak satu pun field record itu ikut keluar.
    $response->assertJsonMissing(['id' => $theirs->id]);
});

it('cakupan mill: Admin tetap bisa membaca record mill mana pun', function () {
    $theirs = EffluentPlantRecord::factory()->forStation($this->otherStation)->create();

    $this->actingAs($this->admin, 'web')->getJson("/api/effluent-plant-records/{$theirs->id}")
        ->assertOk()
        ->assertJsonFragment(['id' => $theirs->id]);
});

it('cakupan mill: aktor terikat mill tanpa business_unit_id gagal-tertutup 422', function () {
    $record = EffluentPlantRecord::factory()->forStation($this->station)->create();
    $millless = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $this->actingAs($millless, 'web')->getJson("/api/effluent-plant-records/{$record->id}")
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonMissing(['id' => $record->id]);
});
