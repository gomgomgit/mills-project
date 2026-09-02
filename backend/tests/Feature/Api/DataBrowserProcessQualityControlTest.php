<?php

/**
 * DataBrowserProcessQualityControlTest (Feature/Api) —
 * screen-100--data-browser-process-quality-control-web /
 * usecase-118--data-browser-process-quality-control-web.
 *
 * Integration tests for GET /api/process-quality-control-records and GET
 * /api/process-quality-control-records/export, mirroring
 * tests/Feature/Api/DataBrowserClarificationTest.php's structure exactly.
 */

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\ProcessQualityControlDetail;
use App\Models\ProcessQualityControlRecord;
use App\Models\Station;
use App\Models\User;
use App\Services\ProcessQualityControlRecordService;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->create();
});

it('success: lists filtered/paginated records then exports them as csv', function () {
    $recordWithFilledRow = ProcessQualityControlRecord::factory()->forStation($this->station)->onDate('2026-08-05')->create();
    ProcessQualityControlDetail::factory()->forRecord($recordWithFilledRow)->timeSlot('07:00')->filled()->create();

    ProcessQualityControlRecord::factory()->forStation($this->station)->onDate('2026-08-06')->count(2)->create();
    ProcessQualityControlRecord::factory()->forStation($this->station)->onDate('2026-09-01')->create();

    $listResponse = $this->actingAs($this->admin, 'web')->getJson('/api/process-quality-control-records?'.http_build_query([
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

    $exportResponse = $this->actingAs($this->admin, 'web')->get('/api/process-quality-control-records/export?'.http_build_query([
        'date_from' => '2026-08-01',
        'date_to' => '2026-08-15',
        'business_unit_id' => $this->businessUnit->id,
        'format' => 'csv',
    ]));

    $exportResponse->assertOk();
    expect($exportResponse->headers->get('Content-Type'))->toStartWith('text/csv');
});

it('Tidak Ada Data Sesuai Filter: returns 200 with an empty data list and meta.total = 0', function () {
    ProcessQualityControlRecord::factory()->forStation($this->station)->onDate('2026-08-05')->create();

    $response = $this->actingAs($this->supervisor, 'web')->getJson('/api/process-quality-control-records?'.http_build_query([
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
    $response = $this->actingAs($this->millManagement, 'web')->getJson('/api/process-quality-control-records?'.http_build_query([
        'date_from' => '2026-08-20',
        'date_to' => '2026-08-10',
    ]));

    $response->assertStatus(422);
    $response->assertJson(['message' => 'Rentang tanggal tidak valid: tanggal awal harus sebelum atau sama dengan tanggal akhir.']);
    $response->assertJsonMissing(['errors']);
});

it('Klik Baris Membuka Detail: baseline list call succeeds (row-click detail nav is a FE concern)', function () {
    ProcessQualityControlRecord::factory()->forStation($this->station)->create();

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/process-quality-control-records?'.http_build_query(['page' => 1, 'per_page' => 20]));

    $response->assertOk();
    $response->assertJsonCount(1, 'data');
});

it('Ekspor Gagal: returns 422 EXPORT_FAILED when the filtered dataset exceeds the export row limit', function () {
    $limit = app(ProcessQualityControlRecordService::class)::EXPORT_ROW_LIMIT;
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
                'process_qc_id' => 'PQC-BULK-'.($inserted + $i),
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

        \Illuminate\Support\Facades\DB::table('process_quality_control_records')->insert($rows);
        $inserted += $batch;
    }

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/process-quality-control-records/export?format=excel');

    $response->assertStatus(422);
    $response->assertJson(['message' => 'Ekspor gagal: data terlalu banyak atau terjadi kesalahan saat membuat berkas.']);
});

it('returns 401 for both endpoints when there is no authenticated session', function () {
    $this->getJson('/api/process-quality-control-records')->assertStatus(401);
    $this->getJson('/api/process-quality-control-records/export?format=csv')->assertStatus(401);
});

it('returns 403 for both endpoints when the authenticated user is an operator', function () {
    $this->actingAs($this->operator, 'web')->getJson('/api/process-quality-control-records')->assertStatus(403);
    $this->actingAs($this->operator, 'web')->getJson('/api/process-quality-control-records/export?format=csv')->assertStatus(403);
});
