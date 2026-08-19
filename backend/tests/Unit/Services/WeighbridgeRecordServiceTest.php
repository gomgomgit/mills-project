<?php

/**
 * WeighbridgeRecordServiceTest — screen-016--data-browser-weighbridge-web /
 * usecase-016--data-browser-weighbridge-web.
 *
 * Unit tests for App\Services\WeighbridgeRecordService::listRecords() and
 * ::export(), covering the unit_test_cases derived from this screen's
 * business_logic (steps 1-6). Calls the service directly (no HTTP layer),
 * mirroring tests/Unit/Services/AuthServiceChangePasswordTest.php's
 * pragmatic deviation from test_strategy.unit_test.mock_policy ("mock all
 * I/O"): this service persists/queries via Eloquent (WeighbridgeRecord::
 * query(), no injectable repository abstraction exists in this codebase),
 * so this suite binds Tests\TestCase + RefreshDatabase (sqlite in-memory,
 * per phpunit.xml) and seeds fixture data via model factories — fast/
 * isolated in practice, while exercising the real query-building/CSV
 * generation logic, which is the behavior actually worth covering here.
 *
 * unit_test_case 5 (export row-limit): rather than mocking
 * WeighbridgeRecordService::EXPORT_ROW_LIMIT (a `public const`, not
 * overridable without Reflection hacks that would diverge from the real
 * constant used by controller/Livewire callers too), this test bulk-inserts
 * EXPORT_ROW_LIMIT + 1 rows directly via DB::table()->insert() in chunks
 * (bypassing Eloquent model events/hydration for speed) so the real
 * ::EXPORT_ROW_LIMIT is exercised end-to-end. Bulk insert keeps this fast
 * even at 50,001 rows against the sqlite in-memory testing connection.
 */

use App\Enums\RecordStatus;
use App\Exceptions\ExportFailedException;
use App\Exceptions\InvalidDateRangeException;
use App\Models\BusinessUnit;
use App\Models\Station;
use App\Models\User;
use App\Models\WeighbridgeRecord;
use App\Services\WeighbridgeRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new WeighbridgeRecordService();
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->creator = User::factory()->create();
});

// unit_test_case 1: returns 422 INVALID_DATE_RANGE when date_from > date_to
// on the list endpoint's underlying query builder.
it('throws InvalidDateRangeException when date_from is after date_to on listRecords()', function () {
    expect(fn () => $this->service->listRecords([
        'date_from' => '2026-02-10',
        'date_to' => '2026-02-01',
    ], 1, 20))->toThrow(InvalidDateRangeException::class);
});

// unit_test_case 2: returns an empty list when no records match the filter.
it('returns an empty data list and meta.total = 0 when no records match the filter', function () {
    WeighbridgeRecord::factory()
        ->forStation($this->station)
        ->arrivedAt('2026-01-01 08:00:00')
        ->create();

    $result = $this->service->listRecords([
        'date_from' => '2020-01-01',
        'date_to' => '2020-01-02',
    ], 1, 20);

    expect($result['data'])->toBe([]);
    expect($result['meta']['total'])->toBe(0);
});

// unit_test_case 3: success — list with valid filters, paginated correctly.
it('returns a paginated, filtered list with the shared pagination meta shape', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create();
    $otherStation = Station::factory()->forBusinessUnit($otherBusinessUnit)->create();

    // 3 records matching the filter (business unit + date range).
    WeighbridgeRecord::factory()
        ->forStation($this->station)
        ->arrivedAt('2026-02-05 09:00:00')
        ->count(3)
        ->create();

    // 1 record outside the date range (should be excluded).
    WeighbridgeRecord::factory()
        ->forStation($this->station)
        ->arrivedAt('2026-03-01 09:00:00')
        ->create();

    // 1 record on a different business unit (should be excluded).
    WeighbridgeRecord::factory()
        ->forStation($otherStation)
        ->arrivedAt('2026-02-05 09:00:00')
        ->create();

    $result = $this->service->listRecords([
        'date_from' => '2026-02-01',
        'date_to' => '2026-02-10',
        'business_unit_id' => $this->businessUnit->id,
    ], 1, 2);

    expect($result['meta'])->toBe([
        'page' => 1,
        'per_page' => 2,
        'total' => 3,
        'total_pages' => 2,
    ]);
    expect($result['data'])->toHaveCount(2);
    expect(array_keys($result['data'][0]))->toBe([
        'id', 'wb_card_number', 'weighbridge_type', 'record_datetime', 'vehicle_number', 'driver_name', 'destination', 'net_weight', 'status',
    ]);

    // Page 2 has the remaining 1 matching record.
    $page2 = $this->service->listRecords([
        'date_from' => '2026-02-01',
        'date_to' => '2026-02-10',
        'business_unit_id' => $this->businessUnit->id,
    ], 2, 2);
    expect($page2['data'])->toHaveCount(1);
    expect($page2['meta']['page'])->toBe(2);
});

// unit_test_case 3b: filters by weighbridge_type when provided.
it('filters by weighbridge_type when provided', function () {
    WeighbridgeRecord::factory()
        ->forStation($this->station)
        ->ofType('receive')
        ->count(2)
        ->create();

    WeighbridgeRecord::factory()
        ->forStation($this->station)
        ->ofType('dispatch')
        ->count(3)
        ->create();

    $result = $this->service->listRecords(['weighbridge_type' => 'dispatch'], 1, 20);

    expect($result['meta']['total'])->toBe(3);
    foreach ($result['data'] as $row) {
        expect($row['weighbridge_type'])->toBe('dispatch');
    }
});

// unit_test_case 4: returns 422 INVALID_DATE_RANGE on the export endpoint
// when date_from > date_to (same validation step, shared buildFilteredQuery()).
it('throws InvalidDateRangeException when date_from is after date_to on export()', function () {
    expect(fn () => $this->service->export([
        'date_from' => '2026-02-10',
        'date_to' => '2026-02-01',
    ], 'csv'))->toThrow(InvalidDateRangeException::class);
});

// unit_test_case 5: returns 422 EXPORT_FAILED when the filtered dataset
// exceeds WeighbridgeRecordService::EXPORT_ROW_LIMIT.
it('throws ExportFailedException when the filtered dataset exceeds the export row limit', function () {
    $limit = WeighbridgeRecordService::EXPORT_ROW_LIMIT;
    $total = $limit + 1;

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
                'created_by' => $this->creator->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('weighbridge_records')->insert($rows);
        $inserted += $batch;
    }

    expect(WeighbridgeRecord::count())->toBe($total);

    expect(fn () => $this->service->export([], 'csv'))->toThrow(ExportFailedException::class);
});

// unit_test_case 6: success — export with a valid filter, for both csv and
// excel formats, returns a StreamedResponse with the correct content-type.
it('returns a StreamedResponse with the correct content-type for csv and excel formats', function (string $format, string $expectedContentType) {
    WeighbridgeRecord::factory()
        ->forStation($this->station)
        ->arrivedAt('2026-02-05 09:00:00')
        ->count(2)
        ->create();

    $response = $this->service->export([
        'date_from' => '2026-02-01',
        'date_to' => '2026-02-10',
    ], $format);

    expect($response)->toBeInstanceOf(StreamedResponse::class);
    expect($response->headers->get('Content-Type'))->toBe($expectedContentType);

    ob_start();
    $response->sendContent();
    $body = ob_get_clean();

    expect($body)->toContain('WB Card Number');
    // 1 header row + 2 data rows.
    expect(substr_count($body, "\n"))->toBeGreaterThanOrEqual(2);
})->with([
    'csv' => ['csv', 'text/csv'],
    'excel' => ['excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
]);
