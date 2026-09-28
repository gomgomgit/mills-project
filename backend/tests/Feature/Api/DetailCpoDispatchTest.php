<?php

/**
 * DetailCpoDispatchTest (Feature/Api) —
 * screen-104--detail-cpo-dispatch-web /
 * usecase-083--detail-cpo-dispatch-web.
 *
 * Integration tests for GET /api/cpo-dispatch-records/{id}. Mirrors
 * DetailKernelDispatchTest.php's structure exactly.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\CpoDispatchDetail;
use App\Models\CpoDispatchRecord;
use App\Models\Station;
use App\Models\User;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->cpoDispatch()->create();
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
    $this->otherStation = Station::factory()->forBusinessUnit($this->otherBusinessUnit)->cpoDispatch()->create();
});

// Scenario: "Lihat Detail CPO Dispatch - berhasil"
it('berhasil: returns the full record with resolved names and the detail event log', function () {
    $record = CpoDispatchRecord::factory()->forStation($this->station)->create();
    CpoDispatchDetail::factory()->forRecord($record)->create(['destination_buyer' => 'PT CPO Buyer']);

    $response = $this->actingAs($this->supervisor, 'web')->getJson("/api/cpo-dispatch-records/{$record->id}");

    $response->assertOk();
    $response->assertJsonFragment([
        'id' => $record->id,
        'cpo_dispatch_id' => $record->cpo_dispatch_id,
        'station_name' => $this->station->name,
    ]);
    $response->assertJsonFragment(['destination_buyer' => 'PT CPO Buyer']);
});

it('returns details ordered by event_date regardless of creation order', function () {
    $record = CpoDispatchRecord::factory()->forStation($this->station)->create();
    CpoDispatchDetail::factory()->forRecord($record)->create(['event_date' => '2026-08-14']);
    CpoDispatchDetail::factory()->forRecord($record)->create(['event_date' => '2026-08-08']);

    $response = $this->actingAs($this->admin, 'web')->getJson("/api/cpo-dispatch-records/{$record->id}");

    $response->assertOk();
    $dates = array_column($response->json('details'), 'event_date');
    expect($dates)->toBe(['2026-08-08', '2026-08-14']);
});

it('Mill Management and Admin can also access the detail endpoint', function () {
    $record = CpoDispatchRecord::factory()->forStation($this->station)->create();

    $this->actingAs($this->millManagement, 'web')->getJson("/api/cpo-dispatch-records/{$record->id}")->assertOk();
    $this->actingAs($this->admin, 'web')->getJson("/api/cpo-dispatch-records/{$record->id}")->assertOk();
});

it('returns 403 for the Operator role (route-level role gate)', function () {
    $record = CpoDispatchRecord::factory()->forStation($this->station)->create();

    $response = $this->actingAs($this->operator, 'web')->getJson("/api/cpo-dispatch-records/{$record->id}");

    $response->assertStatus(403);
});

// Scenario: "Lihat Detail CPO Dispatch - Record Tidak Ditemukan"
it('returns 404 when the id does not exist', function () {
    $response = $this->actingAs($this->admin, 'web')->getJson('/api/cpo-dispatch-records/00000000-0000-0000-0000-000000000000');

    $response->assertStatus(404);
});

it('returns null checked_by_name and acknowledged_by_name when not set', function () {
    $record = CpoDispatchRecord::factory()->forStation($this->station)->create(['checked_by' => null, 'acknowledged_by' => null]);

    $response = $this->actingAs($this->admin, 'web')->getJson("/api/cpo-dispatch-records/{$record->id}");

    $response->assertOk();
    $response->assertJsonFragment(['checked_by_name' => null, 'acknowledged_by_name' => null]);
});

it('resolves checked_by_name and acknowledged_by_name to user names when present', function () {
    $checker = User::factory()->role(UserRole::Supervisor)->create(['name' => 'Checker Person']);
    $acknowledger = User::factory()->role(UserRole::MillManagement)->create(['name' => 'Acknowledger Person']);
    $record = CpoDispatchRecord::factory()->forStation($this->station)->create([
        'checked_by' => $checker->id,
        'acknowledged_by' => $acknowledger->id,
    ]);

    $response = $this->actingAs($this->admin, 'web')->getJson("/api/cpo-dispatch-records/{$record->id}");

    $response->assertOk();
    $response->assertJsonFragment(['checked_by_name' => 'Checker Person', 'acknowledged_by_name' => 'Acknowledger Person']);
});

it('rejects unauthenticated requests', function () {
    $record = CpoDispatchRecord::factory()->forStation($this->station)->create();

    $response = $this->getJson("/api/cpo-dispatch-records/{$record->id}");

    $response->assertStatus(401);
});

// ─── CAKUPAN MILL (2026-09-28) ──────────────────────────────────────────────
// Sampai hari ini getDetail() hanya findOrFail() tanpa cakupan mill apa pun:
// UUID record mill lain mengembalikan RECORD LENGKAP, bukan 404. Sekarang
// cakupannya ada di QUERY, jadi record itu tidak ada sama sekali bagi aktor
// mill lain — 404, dan tidak ada konfirmasi bahwa record itu memang ada.
it('cakupan mill: UUID record Mill B mengembalikan 404 bagi Supervisor Mill A, bukan datanya', function () {
    $theirs = CpoDispatchRecord::factory()->forStation($this->otherStation)->create();

    $response = $this->actingAs($this->supervisor, 'web')->getJson("/api/cpo-dispatch-records/{$theirs->id}");

    $response->assertStatus(404);
    $response->assertJsonPath('code', 'NOT_FOUND');
    // ISI, bukan sekadar status: tidak satu pun field record itu ikut keluar.
    $response->assertJsonMissing(['id' => $theirs->id]);
});

it('cakupan mill: Admin tetap bisa membaca record mill mana pun', function () {
    $theirs = CpoDispatchRecord::factory()->forStation($this->otherStation)->create();

    $this->actingAs($this->admin, 'web')->getJson("/api/cpo-dispatch-records/{$theirs->id}")
        ->assertOk()
        ->assertJsonFragment(['id' => $theirs->id]);
});

it('cakupan mill: aktor terikat mill tanpa business_unit_id gagal-tertutup 422', function () {
    $record = CpoDispatchRecord::factory()->forStation($this->station)->create();
    $millless = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $this->actingAs($millless, 'web')->getJson("/api/cpo-dispatch-records/{$record->id}")
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonMissing(['id' => $record->id]);
});
