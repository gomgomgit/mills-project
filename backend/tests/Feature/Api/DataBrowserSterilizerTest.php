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
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->create();
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
