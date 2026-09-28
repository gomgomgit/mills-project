<?php

/**
 * DataBrowserGradingTest (Feature/Api) — screen-017--data-browser-grading-web
 * / usecase-017--data-browser-grading-web.
 *
 * Integration tests for GET /api/grading-records and GET
 * /api/grading-records/export (App\Http\Controllers\Api\
 * GradingRecordController), one per test_scenarios' api_test step(s).
 * Mirrors tests/Feature/Api/DataBrowserWeighbridgeTest.php's structure/
 * conventions exactly, adapted to grading fields. Exercises the real route
 * -> 'auth:web' + 'role' middleware -> controller -> GradingRecordService
 * -> Eloquent chain against the sqlite in-memory testing DB
 * (RefreshDatabase, bound in tests/Pest.php for the Feature suite).
 *
 * Session auth: authenticated via $this->actingAs($user, 'web') — matches
 * config/auth.php's 'web' session guard, the same guard this screen's
 * routes are gated by ('auth:web' in routes/api.php).
 *
 * Response shape note (mirrors DataBrowserWeighbridgeTest.php): shared_
 * decisions.error_format is `{ "message": ..., "errors": {...} }` —
 * ApiExceptionHandler does not put a machine-readable error_code in the
 * response body (InvalidDateRangeException / ExportFailedException are
 * plain HttpExceptions, rendered via the HttpExceptionInterface branch), so
 * these tests assert HTTP status + message text rather than an error_code
 * field. expected_error_code from test_scenarios (INVALID_DATE_RANGE /
 * EXPORT_FAILED) is asserted indirectly via the exception's default
 * message text, which is shared/generic (not grading-specific) and stable.
 *
 * Empty-filter meta shape: total_pages = 1 for an empty result set — the
 * current (corrected) App\Support\Pagination::format() uses standard
 * LengthAwarePaginator::lastPage() behavior (always >= 1), not the older
 * total_pages = 0 special-case. See app/Support/Pagination.php's docblock.
 *
 * CSV export Content-Type: asserted with toStartWith('text/csv') rather
 * than an exact string match, since Laravel's StreamedResponse may append
 * a '; charset=utf-8' suffix — both are valid per HTTP.
 */

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\GradingRecord;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use App\Models\WeighbridgeRecord;
use App\Services\GradingRecordService;

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

// Scenario: "Telusuri & Ekspor Data Grading — berhasil"
it('berhasil: lists filtered/paginated records then exports them as csv', function () {
    GradingRecord::factory()
        ->forStation($this->station)
        ->onDate('2026-02-05')
        ->count(3)
        ->create();

    // Outside the date range — must not be included.
    GradingRecord::factory()
        ->forStation($this->station)
        ->onDate('2026-03-01')
        ->create();

    // Step 1: GET /api/grading-records
    $listResponse = $this->actingAs($this->admin, 'web')->getJson('/api/grading-records?'.http_build_query([
        'date_from' => '2026-02-01',
        'date_to' => '2026-02-10',
        'business_unit_id' => $this->businessUnit->id,
        'page' => 1,
        'per_page' => 20,
    ]));

    $listResponse->assertOk();
    $listResponse->assertJsonCount(3, 'data');
    $listResponse->assertJson([
        'meta' => [
            'page' => 1,
            'per_page' => 20,
            'total' => 3,
            'total_pages' => 1,
        ],
    ]);

    // Step 2: GET /api/grading-records/export (same filters, format=csv)
    $exportResponse = $this->actingAs($this->admin, 'web')->get('/api/grading-records/export?'.http_build_query([
        'date_from' => '2026-02-01',
        'date_to' => '2026-02-10',
        'business_unit_id' => $this->businessUnit->id,
        'format' => 'csv',
    ]));

    $exportResponse->assertOk();
    // 'text/csv; charset=utf-8' is accepted as equivalent to 'text/csv' —
    // both are valid per HTTP, and the charset suffix is Laravel's
    // StreamedResponse default when a charset is not explicitly stripped.
    expect($exportResponse->headers->get('Content-Type'))->toStartWith('text/csv');
});

// Scenario: "Telusuri & Ekspor Data Grading — Tidak Ada Data Sesuai Filter"
it('Tidak Ada Data Sesuai Filter: returns 200 with an empty data list and meta.total = 0', function () {
    GradingRecord::factory()
        ->forStation($this->station)
        ->onDate('2026-02-05')
        ->create();

    $response = $this->actingAs($this->supervisor, 'web')->getJson('/api/grading-records?'.http_build_query([
        'date_from' => '2020-01-01',
        'date_to' => '2020-01-02',
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

// Scenario: "Telusuri & Ekspor Data Grading — Rentang Tanggal Tidak Valid"
it('Rentang Tanggal Tidak Valid: returns 422 INVALID_DATE_RANGE when date_from is after date_to', function () {
    $response = $this->actingAs($this->millManagement, 'web')->getJson('/api/grading-records?'.http_build_query([
        'date_from' => '2026-02-10',
        'date_to' => '2026-02-01',
    ]));

    $response->assertStatus(422);
    $response->assertJson([
        'message' => 'Rentang tanggal tidak valid: tanggal awal harus sebelum atau sama dengan tanggal akhir.',
    ]);
    $response->assertJsonMissing(['errors']);
});

// Scenario: "Telusuri & Ekspor Data Grading — Klik Baris Membuka Detail"
// (row-click navigation to the Detail Grading screen is a FE/browser
// concern — screen-020 does not exist yet, see route('data.grading.
// detail') guard in the Blade view. For integration coverage this scenario
// only needs the baseline list call to succeed.)
it('Klik Baris Membuka Detail: baseline list call succeeds (row-click detail nav is a FE concern)', function () {
    GradingRecord::factory()->forStation($this->station)->create();

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/grading-records?'.http_build_query([
        'page' => 1,
        'per_page' => 20,
    ]));

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
});

// Scenario: "Telusuri & Ekspor Data Grading — Ekspor Gagal"
it('Ekspor Gagal: returns 422 EXPORT_FAILED when the filtered dataset exceeds the export row limit', function () {
    // Bulk-inserting EXPORT_ROW_LIMIT + 1 real rows (as tests/Unit/
    // Services/GradingRecordServiceTest.php does) is the faithful way to
    // exercise this end-to-end through the real HTTP route; done here via
    // the DB facade for speed, and to bypass GradingRecord::booted()'s
    // `saving` guard (status=saved with zero GradingDetail rows would
    // otherwise be rejected — inserting status=synced via DB::table()
    // bypasses Eloquent events entirely).
    $limit = app(GradingRecordService::class)::EXPORT_ROW_LIMIT;
    $total = $limit + 1;

    $creator = User::factory()->create();
    $weighbridgeRecord = WeighbridgeRecord::factory()->forStation($this->station)->create();
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
                'grading_number' => 'GR-BULK-'.($inserted + $i),
                'date' => $now,
                'weighbridge_record_id' => $weighbridgeRecord->id,
                'license_plate_no' => 'B 1234 XX',
                'vehicle_code' => 'TR-01',
                'estate_supplier' => 'Bulk Estate',
                'division' => null,
                'netto' => 9000.0,
                'quantity' => 120.0,
                'note' => null,
                'checked_by' => null,
                'acknowledged_by' => null,
                'status' => RecordStatus::Synced->value,
                'created_by' => $creator->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        \Illuminate\Support\Facades\DB::table('grading_records')->insert($rows);
        $inserted += $batch;
    }

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/grading-records/export?'.http_build_query([
        'format' => 'csv',
    ]));

    $response->assertStatus(422);
    $response->assertJson([
        'message' => 'Ekspor gagal: data terlalu banyak atau terjadi kesalahan saat membuat berkas.',
    ]);
});

// Auth-guard coverage (route-level, both endpoints): unauthenticated
// requests must not reach the service at all.
it('returns 401 for both endpoints when there is no authenticated session', function () {
    $listResponse = $this->getJson('/api/grading-records');
    $listResponse->assertStatus(401);

    $exportResponse = $this->getJson('/api/grading-records/export?format=csv');
    $exportResponse->assertStatus(401);
});

// Actor-permission coverage: operator has can_access = false for this
// screen (actor_permissions: supervisor, mill_management, admin only).
it('returns 403 for both endpoints when the authenticated user is an operator', function () {
    $listResponse = $this->actingAs($this->operator, 'web')->getJson('/api/grading-records');
    $listResponse->assertStatus(403);

    $exportResponse = $this->actingAs($this->operator, 'web')->getJson('/api/grading-records/export?format=csv');
    $exportResponse->assertStatus(403);
});

// ─── CAKUPAN MILL (2026-09-28) ──────────────────────────────────────────────
// Sampai hari ini `business_unit_id` dipakai mentah dari query string, dan
// nilai KOSONG berarti TANPA cakupan — jadi Supervisor mill mana pun bisa
// melihat dan mengekspor record seluruh mill lewat endpoint ini.
//
// Asersinya memeriksa ISI (id record mana yang keluar), bukan jumlah baris:
// kebocoran yang mengembalikan data mill lain juga menghasilkan "ada baris".

it('cakupan mill: business_unit_id Mill B di query string diabaikan, Supervisor Mill A tetap melihat mill sendiri', function () {
    $mine = GradingRecord::factory()->forStation($this->station)->create();
    $theirs = GradingRecord::factory()->forStation($this->otherStation)->create();

    $response = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/grading-records?'.http_build_query(['business_unit_id' => $this->otherBusinessUnit->id]));

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($mine->id);
    expect($ids)->not->toContain($theirs->id);
});

it('cakupan mill: tanpa business_unit_id sama sekali, Supervisor tetap hanya melihat mill sendiri', function () {
    $mine = GradingRecord::factory()->forStation($this->station)->create();
    $theirs = GradingRecord::factory()->forStation($this->otherStation)->create();

    $response = $this->actingAs($this->supervisor, 'web')->getJson('/api/grading-records');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($mine->id);
    expect($ids)->not->toContain($theirs->id);
});

it('cakupan mill: ekspor ikut tercakup — mill lain tidak ikut ke dalam CSV', function () {
    GradingRecord::factory()->forStation($this->station)->create();
    GradingRecord::factory()->forStation($this->otherStation)->create();

    $response = $this->actingAs($this->supervisor, 'web')
        ->get('/api/grading-records/export?'.http_build_query(['business_unit_id' => $this->otherBusinessUnit->id, 'format' => 'csv']));

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
    $a = GradingRecord::factory()->forStation($this->station)->create();
    $b = GradingRecord::factory()->forStation($this->otherStation)->create();

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/grading-records');

    $response->assertOk();
    $ids = collect($response->json('data'))->pluck('id')->all();

    expect($ids)->toContain($a->id);
    expect($ids)->toContain($b->id);
});

it('cakupan mill: aktor terikat mill tanpa business_unit_id gagal-tertutup 422, bukan daftar kosong', function () {
    GradingRecord::factory()->forStation($this->station)->create();
    $millless = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $this->actingAs($millless, 'web')->getJson('/api/grading-records')
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
    $recordA = GradingRecord::factory()->forStation(Station::factory()->forProductionLine($lineA)->create())->create();
    $recordB = GradingRecord::factory()->forStation(Station::factory()->forProductionLine($lineB)->create())->create();

    // Tanpa filter line: kedua line terlihat — sisi "ADA".
    $all = $this->actingAs($this->supervisor, 'web')->getJson('/api/grading-records');
    $all->assertOk();
    $allRows = collect($all->json('data'))->keyBy('id');
    expect($allRows->has($recordA->id))->toBeTrue();
    expect($allRows->has($recordB->id))->toBeTrue();
    expect($allRows[$recordA->id]['production_line_name'])->toBe('LINE-ALPHA');
    expect($allRows[$recordB->id]['production_line_name'])->toBe('LINE-BETA');

    // Dengan filter line: hanya line itu. `production_line_id` WAJIB ada di
    // $request->only() controller — kalau tidak, ia ditelan tanpa bunyi dan
    // test ini gagal di baris berikutnya, bukan di produksi.
    $filtered = $this->actingAs($this->supervisor, 'web')->getJson('/api/grading-records?'.http_build_query([
        'production_line_id' => $lineA->id,
    ]));
    $filtered->assertOk();
    $filteredIds = collect($filtered->json('data'))->pluck('id')->all();
    expect($filteredIds)->toContain($recordA->id);
    expect($filteredIds)->not->toContain($recordB->id);

    // Ekspor: kolom line ada, dan filternya ikut — konvensi ekspor repo ini
    // satu baris per detail dengan kolom konteks diulang, jadi Production Line
    // adalah kolom konteks yang muncul di setiap baris.
    $exportAll = $this->actingAs($this->supervisor, 'web')->get('/api/grading-records/export?'.http_build_query(['format' => 'csv']));
    $exportAll->assertOk();
    $bodyAll = $exportAll->streamedContent();
    expect($bodyAll)->toContain('Production Line');
    expect($bodyAll)->toContain('LINE-ALPHA');
    expect($bodyAll)->toContain('LINE-BETA');

    $exportFiltered = $this->actingAs($this->supervisor, 'web')->get('/api/grading-records/export?'.http_build_query([
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
    $recordA = GradingRecord::factory()->forStation(Station::factory()->forProductionLine($lineA)->create())->create();

    $foreignLine = ProductionLine::factory()->forBusinessUnit($this->otherBusinessUnit)->create(['name' => 'LINE-ASING']);
    $foreignRecord = GradingRecord::factory()->forStation(Station::factory()->forProductionLine($foreignLine)->create())->create();

    $list = $this->actingAs($this->supervisor, 'web')->getJson('/api/grading-records?'.http_build_query([
        'production_line_id' => $foreignLine->id,
    ]));
    $list->assertOk();
    $ids = collect($list->json('data'))->pluck('id')->all();
    expect($ids)->toContain($recordA->id);
    expect($ids)->not->toContain($foreignRecord->id);

    $export = $this->actingAs($this->supervisor, 'web')->get('/api/grading-records/export?'.http_build_query([
        'format' => 'csv',
        'production_line_id' => $foreignLine->id,
    ]));
    $export->assertOk();
    $body = $export->streamedContent();
    expect($body)->toContain('LINE-ALPHA');
    expect($body)->not->toContain('LINE-ASING');
});
