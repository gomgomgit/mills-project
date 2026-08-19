<?php

/**
 * DataBrowserCagesTrackTest (Feature/Api) —
 * screen-018--data-browser-cages-track-web / usecase-018--data-browser-
 * cages-track-web.
 *
 * Integration tests for GET /api/cages-track-records and GET
 * /api/cages-track-records/export (App\Http\Controllers\Api\
 * CagesTrackRecordController), one per test_scenarios' api_test step(s).
 * Mirrors tests/Feature/Api/DataBrowserGradingTest.php's structure/
 * conventions exactly, adapted to cages-track fields. Exercises the real
 * route -> 'auth:web' + 'role' middleware -> controller ->
 * CagesTrackRecordService -> Eloquent chain against the sqlite in-memory
 * testing DB (RefreshDatabase, bound in tests/Pest.php for the Feature
 * suite).
 *
 * Session auth: authenticated via $this->actingAs($user, 'web') — matches
 * config/auth.php's 'web' session guard, the same guard this screen's
 * routes are gated by ('auth:web' in routes/api.php).
 *
 * Response shape note (mirrors DataBrowserGradingTest.php): shared_
 * decisions.error_format is `{ "message": ..., "errors": {...} }` —
 * ApiExceptionHandler does not put a machine-readable error_code in the
 * response body (InvalidDateRangeException / ExportFailedException are
 * plain HttpExceptions, rendered via the HttpExceptionInterface branch), so
 * these tests assert HTTP status + message text rather than an error_code
 * field. expected_error_code from test_scenarios (INVALID_DATE_RANGE /
 * EXPORT_FAILED) is asserted indirectly via the exception's default
 * message text, which is shared/generic (not cages-track-specific) and
 * stable — the exact same InvalidDateRangeException/ExportFailedException
 * classes are reused across screen-016/017/018.
 *
 * Empty-filter meta shape: total_pages = 1 for an empty result set — the
 * current (corrected) App\Support\Pagination::format() uses standard
 * LengthAwarePaginator::lastPage() behavior (always >= 1), not the older
 * total_pages = 0 special-case. See app/Support/Pagination.php's docblock.
 *
 * CSV export Content-Type: asserted with toStartWith('text/csv') rather
 * than an exact string match, since Laravel's StreamedResponse may append
 * a '; charset=utf-8' suffix — both are valid per HTTP.
 *
 * tipped_time_count coverage: seeds a CagesTippedTime row (via database/
 * factories/CagesTippedTimeFactory.php) so the success scenario's list
 * response is asserted to contain the correct computed count end-to-end
 * through the real HTTP route.
 */

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\CagesTippedTime;
use App\Models\CagesTrackRecord;
use App\Models\Station;
use App\Models\User;
use App\Services\CagesTrackRecordService;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->create();
});

// Scenario: "Telusuri & Ekspor Data Cages Track — success"
it('success: lists filtered/paginated records then exports them as csv', function () {
    $recordWithTippedTimes = CagesTrackRecord::factory()
        ->forStation($this->station)
        ->onDate('2026-08-05')
        ->create();
    CagesTippedTime::factory()->forRecord($recordWithTippedTimes)->count(2)->create();

    CagesTrackRecord::factory()
        ->forStation($this->station)
        ->onDate('2026-08-06')
        ->count(2)
        ->create();

    // Outside the date range — must not be included.
    CagesTrackRecord::factory()
        ->forStation($this->station)
        ->onDate('2026-09-01')
        ->create();

    // Step 1: GET /api/cages-track-records
    $listResponse = $this->actingAs($this->admin, 'web')->getJson('/api/cages-track-records?'.http_build_query([
        'date_from' => '2026-08-01',
        'date_to' => '2026-08-15',
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

    $rows = collect($listResponse->json('data'))->keyBy('id');
    expect($rows[$recordWithTippedTimes->id]['tipped_time_count'])->toBe(2);

    // Step 2: GET /api/cages-track-records/export (same filters, format=csv)
    $exportResponse = $this->actingAs($this->admin, 'web')->get('/api/cages-track-records/export?'.http_build_query([
        'date_from' => '2026-08-01',
        'date_to' => '2026-08-15',
        'business_unit_id' => $this->businessUnit->id,
        'format' => 'csv',
    ]));

    $exportResponse->assertOk();
    // 'text/csv; charset=utf-8' is accepted as equivalent to 'text/csv' —
    // both are valid per HTTP, and the charset suffix is Laravel's
    // StreamedResponse default when a charset is not explicitly stripped.
    expect($exportResponse->headers->get('Content-Type'))->toStartWith('text/csv');
});

// Scenario: "Telusuri & Ekspor Data Cages Track — Tidak Ada Data Sesuai Filter"
it('Tidak Ada Data Sesuai Filter: returns 200 with an empty data list and meta.total = 0', function () {
    CagesTrackRecord::factory()
        ->forStation($this->station)
        ->onDate('2026-08-05')
        ->create();

    $response = $this->actingAs($this->supervisor, 'web')->getJson('/api/cages-track-records?'.http_build_query([
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

// Scenario: "Telusuri & Ekspor Data Cages Track — Rentang Tanggal Tidak Valid"
it('Rentang Tanggal Tidak Valid: returns 422 INVALID_DATE_RANGE when date_from is after date_to', function () {
    $response = $this->actingAs($this->millManagement, 'web')->getJson('/api/cages-track-records?'.http_build_query([
        'date_from' => '2026-08-20',
        'date_to' => '2026-08-10',
    ]));

    $response->assertStatus(422);
    $response->assertJson([
        'message' => 'Rentang tanggal tidak valid: tanggal awal harus sebelum atau sama dengan tanggal akhir.',
    ]);
    $response->assertJsonMissing(['errors']);
});

// Scenario: "Telusuri & Ekspor Data Cages Track — Klik Baris Membuka Detail"
// (row-click navigation to the Detail Cages Track screen is a FE/browser
// concern — screen-021 does not exist yet, see route('data.cages-track.
// detail') guard in the Blade view. For integration coverage this scenario
// only needs the baseline list call to succeed.)
it('Klik Baris Membuka Detail: baseline list call succeeds (row-click detail nav is a FE concern)', function () {
    CagesTrackRecord::factory()->forStation($this->station)->create();

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/cages-track-records?'.http_build_query([
        'page' => 1,
        'per_page' => 20,
    ]));

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
});

// Scenario: "Telusuri & Ekspor Data Cages Track — Ekspor Gagal"
it('Ekspor Gagal: returns 422 EXPORT_FAILED when the filtered dataset exceeds the export row limit', function () {
    // Bulk-inserting EXPORT_ROW_LIMIT + 1 real rows (as tests/Unit/
    // Services/CagesTrackRecordServiceTest.php does) is the faithful way
    // to exercise this end-to-end through the real HTTP route; done here
    // via the DB facade for speed, and to bypass CagesTrackRecord::
    // booted()'s `saving` guard (status=saved with zero CagesTippedTime
    // rows would otherwise be rejected — inserting status=synced via
    // DB::table() bypasses Eloquent events entirely).
    $limit = app(CagesTrackRecordService::class)::EXPORT_ROW_LIMIT;
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
                'cages_track_number' => 'CT-BULK-'.($inserted + $i),
                'date' => $now->toDateString(),
                'tippler_start_time' => $now,
                'tippler_stop_time' => null,
                'cages_out' => 10,
                'cages_tipped' => 10,
                'note' => null,
                'checked_by' => null,
                'acknowledged_by' => null,
                'status' => RecordStatus::Synced->value,
                'created_by' => $creator->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        \Illuminate\Support\Facades\DB::table('cages_track_records')->insert($rows);
        $inserted += $batch;
    }

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/cages-track-records/export?'.http_build_query([
        'format' => 'excel',
    ]));

    $response->assertStatus(422);
    $response->assertJson([
        'message' => 'Ekspor gagal: data terlalu banyak atau terjadi kesalahan saat membuat berkas.',
    ]);
});

// Auth-guard coverage (route-level, both endpoints): unauthenticated
// requests must not reach the service at all.
it('returns 401 for both endpoints when there is no authenticated session', function () {
    $listResponse = $this->getJson('/api/cages-track-records');
    $listResponse->assertStatus(401);

    $exportResponse = $this->getJson('/api/cages-track-records/export?format=csv');
    $exportResponse->assertStatus(401);
});

// Actor-permission coverage: operator has can_access = false for this
// screen (actor_permissions: supervisor, mill_management, admin only).
it('returns 403 for both endpoints when the authenticated user is an operator', function () {
    $listResponse = $this->actingAs($this->operator, 'web')->getJson('/api/cages-track-records');
    $listResponse->assertStatus(403);

    $exportResponse = $this->actingAs($this->operator, 'web')->getJson('/api/cages-track-records/export?format=csv');
    $exportResponse->assertStatus(403);
});
