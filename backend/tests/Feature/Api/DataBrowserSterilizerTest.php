<?php

/**
 * DataBrowserSterilizerTest (Feature/Api) —
 * screen-124--data-browser-sterilizer-web /
 * usecase-124--data-browser-sterilizer-web.
 *
 * Integration tests for GET /api/sterilizer-records and GET
 * /api/sterilizer-records/export. Mirrors DataBrowserCpoDispatchTest.php's
 * structure exactly.
 */

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\Station;
use App\Models\SterilizerDetail;
use App\Models\SterilizerRecord;
use App\Models\User;
use App\Services\SterilizerRecordService;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->sterilizer()->create();
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
    $this->otherStation = Station::factory()->forBusinessUnit($this->otherBusinessUnit)->sterilizer()->create();
});

// Scenario: "Telusuri & Ekspor Data Sterilizer — success"
it('success: lists filtered/paginated records then exports them as csv', function () {
    $recordWithDetails = SterilizerRecord::factory()->forStation($this->station)->onDate('2026-08-05')->create();
    SterilizerDetail::factory()->forRecord($recordWithDetails)->count(2)->create();

    SterilizerRecord::factory()->forStation($this->station)->onDate('2026-08-06')->count(2)->create();

    // Outside the date range — must not be included.
    SterilizerRecord::factory()->forStation($this->station)->onDate('2026-09-01')->create();

    $listResponse = $this->actingAs($this->admin, 'web')->getJson('/api/sterilizer-records?'.http_build_query([
        'date_from' => '2026-08-01',
        'date_to' => '2026-08-15',
        'business_unit_id' => $this->businessUnit->id,
        'page' => 1,
        'per_page' => 20,
    ]));

    $listResponse->assertOk();
    $listResponse->assertJsonCount(3, 'data');
    $listResponse->assertJson([
        'meta' => ['page' => 1, 'per_page' => 20, 'total' => 3, 'total_pages' => 1],
    ]);

    $rows = collect($listResponse->json('data'))->keyBy('id');
    expect($rows[$recordWithDetails->id]['cycle_count'])->toBe(2);

    $exportResponse = $this->actingAs($this->admin, 'web')->get('/api/sterilizer-records/export?'.http_build_query([
        'date_from' => '2026-08-01',
        'date_to' => '2026-08-15',
        'business_unit_id' => $this->businessUnit->id,
        'format' => 'csv',
    ]));

    $exportResponse->assertOk();
    expect($exportResponse->headers->get('Content-Type'))->toStartWith('text/csv');
});

// Scenario: "Telusuri & Ekspor Data Sterilizer — Tidak Ada Data Sesuai Filter"
it('Tidak Ada Data Sesuai Filter: returns 200 with an empty data list and meta.total = 0', function () {
    SterilizerRecord::factory()->forStation($this->station)->onDate('2026-08-05')->create();

    $response = $this->actingAs($this->supervisor, 'web')->getJson('/api/sterilizer-records?'.http_build_query([
        'date_from' => '2026-01-01',
        'date_to' => '2026-01-02',
        'business_unit_id' => $this->businessUnit->id,
        'page' => 1,
        'per_page' => 20,
    ]));

    $response->assertOk();
    $response->assertExactJson([
        'data' => [],
        'meta' => ['page' => 1, 'per_page' => 20, 'total' => 0, 'total_pages' => 1],
    ]);
});

// Scenario: "Telusuri & Ekspor Data Sterilizer — Rentang Tanggal Tidak Valid"
it('Rentang Tanggal Tidak Valid: returns 422 INVALID_DATE_RANGE when date_from is after date_to', function () {
    $response = $this->actingAs($this->millManagement, 'web')->getJson('/api/sterilizer-records?'.http_build_query([
        'date_from' => '2026-08-20',
        'date_to' => '2026-08-10',
    ]));

    $response->assertStatus(422);
    $response->assertJson([
        'message' => 'Rentang tanggal tidak valid: tanggal awal harus sebelum atau sama dengan tanggal akhir.',
    ]);
});

// Scenario: "Telusuri & Ekspor Data Sterilizer — Klik Baris Membuka Detail"
it('Klik Baris Membuka Detail: baseline list call succeeds (row-click detail nav is a FE concern)', function () {
    SterilizerRecord::factory()->forStation($this->station)->create();

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/sterilizer-records?'.http_build_query([
        'page' => 1,
        'per_page' => 20,
    ]));

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
});

// Scenario: "Telusuri & Ekspor Data Sterilizer — Ekspor Gagal"
it('Ekspor Gagal: returns 422 EXPORT_FAILED when the filtered dataset exceeds the export row limit', function () {
    $limit = app(SterilizerRecordService::class)::EXPORT_ROW_LIMIT;
    $total = $limit + 1;

    $creator = User::factory()->create();
    $now = now();
    $chunkSize = 2000;
    $inserted = 0;

    while ($inserted < $total) {
        $batch = min($chunkSize, $total - $inserted);
        $rows = [];

        for ($i = 0; $i < $batch; $i++) {
            $rows[] = [
                'id' => (string) \Illuminate\Support\Str::uuid(),
                'station_id' => $this->station->id,
                'production_line_id' => $this->station->production_line_id,
                'sterilizer_id' => 'STR-BULK-'.($inserted + $i),
                'date' => $now->toDateString(),
                'note' => null,
                'checked_by' => null,
                'acknowledged_by' => null,
                'status' => RecordStatus::Saved->value,
                'created_by' => $creator->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        \Illuminate\Support\Facades\DB::table('sterilizer_records')->insert($rows);
        $inserted += $batch;
    }

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/sterilizer-records/export?'.http_build_query([
        'format' => 'excel',
    ]));

    $response->assertStatus(422);
    $response->assertJson([
        'message' => 'Ekspor gagal: data terlalu banyak atau terjadi kesalahan saat membuat berkas.',
    ]);
});

it('returns 401 for both endpoints when there is no authenticated session', function () {
    $this->getJson('/api/sterilizer-records')->assertStatus(401);
    $this->getJson('/api/sterilizer-records/export?format=csv')->assertStatus(401);
});

it('returns 403 for both endpoints when the authenticated user is an operator', function () {
    $this->actingAs($this->operator, 'web')->getJson('/api/sterilizer-records')->assertStatus(403);
    $this->actingAs($this->operator, 'web')->getJson('/api/sterilizer-records/export?format=csv')->assertStatus(403);
});

// ─── CAKUPAN MILL (2026-09-28) ──────────────────────────────────────────────
// Sampai hari ini `business_unit_id` dipakai mentah dari query string, dan
// nilai KOSONG berarti TANPA cakupan — jadi Supervisor mill mana pun bisa
// melihat dan mengekspor record seluruh mill lewat endpoint ini.
//
// Asersinya memeriksa ISI (id record mana yang keluar), bukan jumlah baris:
// kebocoran yang mengembalikan data mill lain juga menghasilkan "ada baris".

it('cakupan mill: business_unit_id Mill B di query string diabaikan, Supervisor Mill A tetap melihat mill sendiri', function () {
    $mine = SterilizerRecord::factory()->forStation($this->station)->create();
    $theirs = SterilizerRecord::factory()->forStation($this->otherStation)->create();

    $response = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/sterilizer-records?'.http_build_query(['business_unit_id' => $this->otherBusinessUnit->id]));

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($mine->id);
    expect($ids)->not->toContain($theirs->id);
});

it('cakupan mill: tanpa business_unit_id sama sekali, Supervisor tetap hanya melihat mill sendiri', function () {
    $mine = SterilizerRecord::factory()->forStation($this->station)->create();
    $theirs = SterilizerRecord::factory()->forStation($this->otherStation)->create();

    $response = $this->actingAs($this->supervisor, 'web')->getJson('/api/sterilizer-records');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($mine->id);
    expect($ids)->not->toContain($theirs->id);
});

it('cakupan mill: ekspor ikut tercakup — mill lain tidak ikut ke dalam CSV', function () {
    SterilizerRecord::factory()->forStation($this->station)->create();
    SterilizerRecord::factory()->forStation($this->otherStation)->create();

    $response = $this->actingAs($this->supervisor, 'web')
        ->get('/api/sterilizer-records/export?'.http_build_query(['business_unit_id' => $this->otherBusinessUnit->id, 'format' => 'csv']));

    $response->assertOk();

    ob_start();
    $response->sendContent();
    $body = ob_get_clean();

    // Record tanpa detail tetap menghasilkan tepat satu baris, jadi: 1 header
    // + 1 baris = hanya record mill aktor yang ikut terekspor.
    $lines = array_values(array_filter(explode("\n", trim($body)), fn ($line) => $line !== ''));
    expect($lines)->toHaveCount(2);
});

it('cakupan mill: Admin tanpa business_unit_id tetap melihat semua mill', function () {
    $a = SterilizerRecord::factory()->forStation($this->station)->create();
    $b = SterilizerRecord::factory()->forStation($this->otherStation)->create();

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/sterilizer-records');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($a->id);
    expect($ids)->toContain($b->id);
});

it('cakupan mill: aktor terikat mill tanpa business_unit_id gagal-tertutup 422, bukan daftar kosong', function () {
    SterilizerRecord::factory()->forStation($this->station)->create();
    $millless = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $this->actingAs($millless, 'web')->getJson('/api/sterilizer-records')
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR');
});
