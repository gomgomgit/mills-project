<?php

/**
 * DataBrowserWeighbridgeTest (Feature/Api) —
 * screen-016--data-browser-weighbridge-web / usecase-016--data-browser-
 * weighbridge-web.
 *
 * Integration tests for GET /api/weighbridge-records and GET
 * /api/weighbridge-records/export (App\Http\Controllers\Api\
 * WeighbridgeRecordController), one per test_scenarios' api_test step(s).
 * Exercises the real route -> 'auth:web' + 'role' middleware -> controller
 * -> WeighbridgeRecordService -> Eloquent chain against the sqlite
 * in-memory testing DB (RefreshDatabase, bound in tests/Pest.php for the
 * Feature suite).
 *
 * Session auth: authenticated via $this->actingAs($user, 'web') (mirrors
 * tests/Feature/Api/ChangePasswordWebTest.php) — matches config/auth.php's
 * 'web' session guard, the same guard this screen's routes are gated by
 * ('auth:web' in routes/api.php).
 *
 * Response shape note (mirrors ChangePasswordWebTest.php): shared_
 * decisions.error_format is `{ "message": ..., "errors": {...} }` —
 * ApiExceptionHandler does not put a machine-readable error_code in the
 * response body (InvalidDateRangeException / ExportFailedException are
 * plain HttpExceptions, rendered via the HttpExceptionInterface branch), so
 * these tests assert HTTP status + message text rather than an error_code
 * field. expected_error_code from test_scenarios (INVALID_DATE_RANGE /
 * EXPORT_FAILED) is asserted indirectly via the exception's default
 * message text, which is stable and specific to each error.
 */

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\Station;
use App\Models\User;
use App\Models\WeighbridgeRecord;
use App\Services\WeighbridgeRecordService;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->create();
});

// Scenario: "Telusuri & Ekspor Data Weighbridge — berhasil"
it('berhasil: lists filtered/paginated records then exports them as csv', function () {
    WeighbridgeRecord::factory()
        ->forStation($this->station)
        ->arrivedAt('2026-02-05 09:00:00')
        ->count(3)
        ->create();

    // Outside the date range — must not be included.
    WeighbridgeRecord::factory()
        ->forStation($this->station)
        ->arrivedAt('2026-03-01 09:00:00')
        ->create();

    // Step 1: GET /api/weighbridge-records
    $listResponse = $this->actingAs($this->admin, 'web')->getJson('/api/weighbridge-records?'.http_build_query([
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

    // Step 2: GET /api/weighbridge-records/export (same filters, format=csv)
    $exportResponse = $this->actingAs($this->admin, 'web')->get('/api/weighbridge-records/export?'.http_build_query([
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

// Scenario: "Telusuri & Ekspor Data Weighbridge — Filter Berdasarkan Tipe"
it('Filter Berdasarkan Tipe: returns only records matching the weighbridge_type filter', function () {
    WeighbridgeRecord::factory()->forStation($this->station)->ofType('receive')->count(2)->create();
    WeighbridgeRecord::factory()->forStation($this->station)->ofType('dispatch')->count(3)->create();

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/weighbridge-records?'.http_build_query([
        'weighbridge_type' => 'dispatch',
        'page' => 1,
        'per_page' => 20,
    ]));

    $response->assertOk();
    $response->assertJsonCount(3, 'data');
    collect($response->json('data'))->each(
        fn (array $row) => expect($row['weighbridge_type'])->toBe('dispatch')
    );
});

// Scenario: "Telusuri & Ekspor Data Weighbridge — Tidak Ada Data Sesuai Filter"
it('Tidak Ada Data Sesuai Filter: returns 200 with an empty data list and meta.total = 0', function () {
    WeighbridgeRecord::factory()
        ->forStation($this->station)
        ->arrivedAt('2026-02-05 09:00:00')
        ->create();

    $response = $this->actingAs($this->supervisor, 'web')->getJson('/api/weighbridge-records?'.http_build_query([
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

// Scenario: "Telusuri & Ekspor Data Weighbridge — Rentang Tanggal Tidak Valid"
it('Rentang Tanggal Tidak Valid: returns 422 INVALID_DATE_RANGE when date_from is after date_to', function () {
    $response = $this->actingAs($this->millManagement, 'web')->getJson('/api/weighbridge-records?'.http_build_query([
        'date_from' => '2026-02-10',
        'date_to' => '2026-02-01',
    ]));

    $response->assertStatus(422);
    $response->assertJson([
        'message' => 'Rentang tanggal tidak valid: tanggal awal harus sebelum atau sama dengan tanggal akhir.',
    ]);
    $response->assertJsonMissing(['errors']);
});

// Scenario: "Telusuri & Ekspor Data Weighbridge — Klik Baris Membuka Detail"
// (row-click navigation to the Detail Weighbridge screen is a FE/browser
// concern — screen-019 does not exist yet, see route('data.weighbridge.
// detail') guard in the Blade view. For integration coverage this scenario
// only needs the baseline list call to succeed.)
it('Klik Baris Membuka Detail: baseline list call succeeds (row-click detail nav is a FE concern)', function () {
    WeighbridgeRecord::factory()->forStation($this->station)->create();

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/weighbridge-records?'.http_build_query([
        'page' => 1,
        'per_page' => 20,
    ]));

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
});

// Scenario: "Telusuri & Ekspor Data Weighbridge — Ekspor Gagal"
it('Ekspor Gagal: returns 422 EXPORT_FAILED when the filtered dataset exceeds the export row limit', function () {
    // Reduce the volume needed to trigger the limit for this HTTP-level
    // test by stubbing the shared service to a request-scoped subclass
    // with a lower EXPORT_ROW_LIMIT would require overriding a `public
    // const`, which is not possible in PHP without diverging from the real
    // constant. Bulk-inserting EXPORT_ROW_LIMIT + 1 real rows (as
    // tests/Unit/Services/WeighbridgeRecordServiceTest.php does) is the
    // faithful way to exercise this end-to-end through the real HTTP
    // route; done here via the DB facade for speed.
    $limit = app(WeighbridgeRecordService::class)::EXPORT_ROW_LIMIT;
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
                'wb_card_number' => 'WB-BULK-'.($inserted + $i),
                'weighbridge_type' => 'receive',
                'record_datetime' => $now,
                'vehicle_number' => 'B 1234 XX',
                'driver_name' => 'Bulk Driver',
                'estate_supplier' => 'Bulk Estate',
                'destination' => null,
                'division' => null,
                'block' => null,
                'gross_weight' => 10000,
                'tare_weight' => 2000,
                'net_weight' => 8000,
                'quantity' => 1,
                'checked_by' => null,
                'acknowledged_by' => null,
                'status' => RecordStatus::Saved->value,
                'created_by' => $creator->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        \Illuminate\Support\Facades\DB::table('weighbridge_records')->insert($rows);
        $inserted += $batch;
    }

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/weighbridge-records/export?'.http_build_query([
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
    $listResponse = $this->getJson('/api/weighbridge-records');
    $listResponse->assertStatus(401);

    $exportResponse = $this->getJson('/api/weighbridge-records/export?format=csv');
    $exportResponse->assertStatus(401);
});

// Actor-permission coverage: operator has can_access = false for this
// screen (actor_permissions: supervisor, mill_management, admin only).
it('returns 403 for both endpoints when the authenticated user is an operator', function () {
    $listResponse = $this->actingAs($this->operator, 'web')->getJson('/api/weighbridge-records');
    $listResponse->assertStatus(403);

    $exportResponse = $this->actingAs($this->operator, 'web')->getJson('/api/weighbridge-records/export?format=csv');
    $exportResponse->assertStatus(403);
});
