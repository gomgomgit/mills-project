<?php

/**
 * DataBrowserDepricarpingTest (Feature/Api) —
 * screen-051--data-browser-depricarping-web /
 * usecase-051--data-browser-depricarping-web.
 *
 * Integration tests for GET /api/depricarping-records and GET
 * /api/depricarping-records/export, mirroring
 * tests/Feature/Api/DataBrowserPressingTest.php's structure exactly.
 */

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\DepricarpingDetail;
use App\Models\DepricarpingRecord;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use App\Services\DepricarpingRecordService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

it('success: lists filtered/paginated records then exports them as csv', function () {
    $recordWithFilledRow = DepricarpingRecord::factory()->forStation($this->station)->onDate('2026-08-05')->create();
    DepricarpingDetail::factory()->forRecord($recordWithFilledRow)->timeSlot('07:00')->filled()->create();

    DepricarpingRecord::factory()->forStation($this->station)->onDate('2026-08-06')->count(2)->create();
    DepricarpingRecord::factory()->forStation($this->station)->onDate('2026-09-01')->create();

    $listResponse = $this->actingAs($this->admin, 'web')->getJson('/api/depricarping-records?'.http_build_query([
        'date_from' => '2026-08-01',
        'date_to' => '2026-08-15',
        'business_unit_id' => $this->businessUnit->id,
        'page' => 1,
        'per_page' => 20,
    ]));

    $listResponse->assertOk();
    $listResponse->assertJsonCount(3, 'data');
    $listResponse->assertJson(['meta' => ['page' => 1, 'per_page' => 20, 'total' => 3, 'total_pages' => 1]]);

    $rows = collect($listResponse->json('data'))->keyBy('id');
    expect($rows[$recordWithFilledRow->id]['filled_slot_count'])->toBe(1);

    $exportResponse = $this->actingAs($this->admin, 'web')->get('/api/depricarping-records/export?'.http_build_query([
        'date_from' => '2026-08-01',
        'date_to' => '2026-08-15',
        'business_unit_id' => $this->businessUnit->id,
        'format' => 'csv',
    ]));

    $exportResponse->assertOk();
    expect($exportResponse->headers->get('Content-Type'))->toStartWith('text/csv');
});

it('Tidak Ada Data Sesuai Filter: returns 200 with an empty data list and meta.total = 0', function () {
    DepricarpingRecord::factory()->forStation($this->station)->onDate('2026-08-05')->create();

    $response = $this->actingAs($this->supervisor, 'web')->getJson('/api/depricarping-records?'.http_build_query([
        'date_from' => '2026-01-01',
        'date_to' => '2026-01-02',
        'business_unit_id' => $this->businessUnit->id,
    ]));

    $response->assertOk();
    $response->assertExactJson([
        'data' => [],
        'meta' => ['page' => 1, 'per_page' => 20, 'total' => 0, 'total_pages' => 1],
    ]);
});

it('Rentang Tanggal Tidak Valid: returns 422 INVALID_DATE_RANGE when date_from is after date_to', function () {
    $response = $this->actingAs($this->millManagement, 'web')->getJson('/api/depricarping-records?'.http_build_query([
        'date_from' => '2026-08-20',
        'date_to' => '2026-08-10',
    ]));

    $response->assertStatus(422);
    $response->assertJson(['message' => 'Rentang tanggal tidak valid: tanggal awal harus sebelum atau sama dengan tanggal akhir.']);
    $response->assertJsonMissing(['errors']);
});

it('Klik Baris Membuka Detail: baseline list call succeeds (row-click detail nav is a FE concern)', function () {
    DepricarpingRecord::factory()->forStation($this->station)->create();

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/depricarping-records?'.http_build_query(['page' => 1, 'per_page' => 20]));

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
});

it('Ekspor Gagal: returns 422 EXPORT_FAILED when the filtered dataset exceeds the export row limit', function () {
    $limit = app(DepricarpingRecordService::class)::EXPORT_ROW_LIMIT;
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
                'id' => (string) Str::uuid(),
                'station_id' => $this->station->id,
                'production_line_id' => $this->station->production_line_id,
                'presser_id' => 'DP-BULK-'.($inserted + $i),
                'date' => $now->toDateString(),
                'note' => null,
                'checked_by' => null,
                'acknowledged_by' => null,
                'status' => RecordStatus::Synced->value,
                'created_by' => $creator->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('depricarping_records')->insert($rows);
        $inserted += $batch;
    }

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/depricarping-records/export?format=excel');

    $response->assertStatus(422);
    $response->assertJson(['message' => 'Ekspor gagal: data terlalu banyak atau terjadi kesalahan saat membuat berkas.']);
});

it('returns 401 for both endpoints when there is no authenticated session', function () {
    $this->getJson('/api/depricarping-records')->assertStatus(401);
    $this->getJson('/api/depricarping-records/export?format=csv')->assertStatus(401);
});

it('returns 403 for both endpoints when the authenticated user is an operator', function () {
    $this->actingAs($this->operator, 'web')->getJson('/api/depricarping-records')->assertStatus(403);
    $this->actingAs($this->operator, 'web')->getJson('/api/depricarping-records/export?format=csv')->assertStatus(403);
});

// ─── CAKUPAN MILL (2026-09-28) ──────────────────────────────────────────────
// Sampai hari ini `business_unit_id` dipakai mentah dari query string, dan
// nilai KOSONG berarti TANPA cakupan — jadi Supervisor mill mana pun bisa
// melihat dan mengekspor record seluruh mill lewat endpoint ini.
//
// Asersinya memeriksa ISI (id record mana yang keluar), bukan jumlah baris:
// kebocoran yang mengembalikan data mill lain juga menghasilkan "ada baris".

it('cakupan mill: business_unit_id Mill B di query string diabaikan, Supervisor Mill A tetap melihat mill sendiri', function () {
    $mine = DepricarpingRecord::factory()->forStation($this->station)->create();
    $theirs = DepricarpingRecord::factory()->forStation($this->otherStation)->create();

    $response = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/depricarping-records?'.http_build_query(['business_unit_id' => $this->otherBusinessUnit->id]));

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($mine->id);
    expect($ids)->not->toContain($theirs->id);
});

it('cakupan mill: tanpa business_unit_id sama sekali, Supervisor tetap hanya melihat mill sendiri', function () {
    $mine = DepricarpingRecord::factory()->forStation($this->station)->create();
    $theirs = DepricarpingRecord::factory()->forStation($this->otherStation)->create();

    $response = $this->actingAs($this->supervisor, 'web')->getJson('/api/depricarping-records');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($mine->id);
    expect($ids)->not->toContain($theirs->id);
});

it('cakupan mill: ekspor ikut tercakup — mill lain tidak ikut ke dalam CSV', function () {
    DepricarpingRecord::factory()->forStation($this->station)->create();
    DepricarpingRecord::factory()->forStation($this->otherStation)->create();

    $response = $this->actingAs($this->supervisor, 'web')
        ->get('/api/depricarping-records/export?'.http_build_query(['business_unit_id' => $this->otherBusinessUnit->id, 'format' => 'csv']));

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
    $a = DepricarpingRecord::factory()->forStation($this->station)->create();
    $b = DepricarpingRecord::factory()->forStation($this->otherStation)->create();

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/depricarping-records');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($a->id);
    expect($ids)->toContain($b->id);
});

it('cakupan mill: aktor terikat mill tanpa business_unit_id gagal-tertutup 422, bukan daftar kosong', function () {
    DepricarpingRecord::factory()->forStation($this->station)->create();
    $millless = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $this->actingAs($millless, 'web')->getJson('/api/depricarping-records')
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR');
});

// ─── PRODUCTION LINE (2026-09-28) ───────────────────────────────────────────
// Production line adalah KONTEKS YANG DIPILIH, bukan ikatan akun: tidak ada
// `users.production_line_id` dan tidak boleh ada. Karena itu filternya
// default ke "Semua Line" — daftar ini daftar BARIS, bukan angka gabungan
// seperti laporan periode, jadi melihat semuanya memang berguna ASAL setiap
// baris menunjukkan line-nya. Itulah tugas kolom Production Line.
//
// Setiap asersi cakupan di bawah memeriksa ISI DUA ARAH: record yang
// seharusnya ADA memang ada, dan yang seharusnya TIDAK ADA memang tidak.
// Sisi "ADA" bukan hiasan — SQLite (driver test) memperlakukan WHERE pada
// kolom yang tidak ada sebagai string literal dan mengembalikan 0 baris TANPA
// error, sementara PostgreSQL (dev/produksi) melempar. Filter yang menyaring
// habis karena salah kolom akan tetap hijau kalau kita hanya mengasersi
// ketiadaan.

it('production line: filter ikut ke daftar DAN ke ekspor CSV, kolom line ada di file', function () {
    $lineA = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'LINE-ALPHA']);
    $lineB = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'LINE-BETA']);
    $recordA = DepricarpingRecord::factory()->forStation(Station::factory()->forProductionLine($lineA)->create())->create();
    $recordB = DepricarpingRecord::factory()->forStation(Station::factory()->forProductionLine($lineB)->create())->create();

    // Tanpa filter line: kedua line terlihat — sisi "ADA".
    $all = $this->actingAs($this->supervisor, 'web')->getJson('/api/depricarping-records');
    $all->assertOk();
    $allRows = collect($all->json('data'))->keyBy('id');
    expect($allRows->has($recordA->id))->toBeTrue();
    expect($allRows->has($recordB->id))->toBeTrue();
    expect($allRows[$recordA->id]['production_line_name'])->toBe('LINE-ALPHA');
    expect($allRows[$recordB->id]['production_line_name'])->toBe('LINE-BETA');

    // Dengan filter line: hanya line itu. `production_line_id` WAJIB ada di
    // $request->only() controller — kalau tidak, ia ditelan tanpa bunyi dan
    // test ini gagal di baris berikutnya, bukan di produksi.
    $filtered = $this->actingAs($this->supervisor, 'web')->getJson('/api/depricarping-records?'.http_build_query([
        'production_line_id' => $lineA->id,
    ]));
    $filtered->assertOk();
    $filteredIds = collect($filtered->json('data'))->pluck('id')->all();
    expect($filteredIds)->toContain($recordA->id);
    expect($filteredIds)->not->toContain($recordB->id);

    // Ekspor: kolom line ada, dan filternya ikut — konvensi ekspor repo ini
    // satu baris per detail dengan kolom konteks diulang, jadi Production Line
    // adalah kolom konteks yang muncul di setiap baris.
    $exportAll = $this->actingAs($this->supervisor, 'web')->get('/api/depricarping-records/export?'.http_build_query(['format' => 'csv']));
    $exportAll->assertOk();
    $bodyAll = $exportAll->streamedContent();
    expect($bodyAll)->toContain('Production Line');
    expect($bodyAll)->toContain('LINE-ALPHA');
    expect($bodyAll)->toContain('LINE-BETA');

    $exportFiltered = $this->actingAs($this->supervisor, 'web')->get('/api/depricarping-records/export?'.http_build_query([
        'format' => 'csv',
        'production_line_id' => $lineA->id,
    ]));
    $exportFiltered->assertOk();
    $bodyFiltered = $exportFiltered->streamedContent();
    expect($bodyFiltered)->toContain('LINE-ALPHA');
    expect($bodyFiltered)->not->toContain('LINE-BETA');
});

it('production line: line mill lain di query string diabaikan, bukan error', function () {
    $lineA = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'LINE-ALPHA']);
    $recordA = DepricarpingRecord::factory()->forStation(Station::factory()->forProductionLine($lineA)->create())->create();

    $foreignLine = ProductionLine::factory()->forBusinessUnit($this->otherBusinessUnit)->create(['name' => 'LINE-ASING']);
    $foreignRecord = DepricarpingRecord::factory()->forStation(Station::factory()->forProductionLine($foreignLine)->create())->create();

    $list = $this->actingAs($this->supervisor, 'web')->getJson('/api/depricarping-records?'.http_build_query([
        'production_line_id' => $foreignLine->id,
    ]));
    $list->assertOk();
    $ids = collect($list->json('data'))->pluck('id')->all();
    expect($ids)->toContain($recordA->id);
    expect($ids)->not->toContain($foreignRecord->id);

    $export = $this->actingAs($this->supervisor, 'web')->get('/api/depricarping-records/export?'.http_build_query([
        'format' => 'csv',
        'production_line_id' => $foreignLine->id,
    ]));
    $export->assertOk();
    $body = $export->streamedContent();
    expect($body)->toContain('LINE-ALPHA');
    expect($body)->not->toContain('LINE-ASING');
});
