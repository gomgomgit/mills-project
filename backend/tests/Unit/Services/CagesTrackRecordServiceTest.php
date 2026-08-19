<?php

/**
 * CagesTrackRecordServiceTest — screen-018--data-browser-cages-track-web /
 * usecase-018--data-browser-cages-track-web.
 *
 * Unit tests for App\Services\CagesTrackRecordService::listRecords() and
 * ::export(), covering the unit_test_cases derived from this screen's
 * business_logic (steps 1-6). Mirrors tests/Unit/Services/
 * GradingRecordServiceTest.php's pragmatic deviation from
 * test_strategy.unit_test.mock_policy ("mock all I/O"): this service
 * persists/queries via Eloquent (CagesTrackRecord::query(), no injectable
 * repository abstraction exists in this codebase), so this suite binds
 * Tests\TestCase + RefreshDatabase (sqlite in-memory, per phpunit.xml) and
 * seeds fixture data via model factories — fast/isolated in practice,
 * while exercising the real query-building/CSV generation logic, which is
 * the behavior actually worth covering here.
 *
 * unit_test_case (export row-limit): same approach as
 * GradingRecordServiceTest.php — rather than mocking
 * CagesTrackRecordService::EXPORT_ROW_LIMIT (a `public const`, not
 * overridable without Reflection hacks that would diverge from the real
 * constant used by controller/Livewire callers too), this test
 * bulk-inserts EXPORT_ROW_LIMIT + 1 rows directly via
 * DB::table()->insert() in chunks (bypassing Eloquent model events/
 * hydration for speed, and — for CagesTrackRecord specifically —
 * bypassing the booted() `saving` guard that would otherwise reject
 * status=saved rows with zero CagesTippedTime children) so the real
 * ::EXPORT_ROW_LIMIT is exercised end-to-end. Bulk insert keeps this fast
 * even at 50,001 rows against the sqlite in-memory testing connection.
 *
 * tipped_time_count coverage: CagesTrackRecordService computes this via
 * withCount('cagesTippedTimes') (Eloquent's default
 * `cages_tipped_times_count` column, mapped to the `tipped_time_count`
 * response key by CagesTrackRecordService::toListRow()) — not a stored
 * column, so it is exercised directly here by seeding real CagesTippedTime
 * rows (via database/factories/CagesTippedTimeFactory.php, created
 * alongside this test suite) rather than asserted only implicitly.
 */

use App\Enums\RecordStatus;
use App\Exceptions\ExportFailedException;
use App\Exceptions\InvalidDateRangeException;
use App\Models\BusinessUnit;
use App\Models\CagesTippedTime;
use App\Models\CagesTrackRecord;
use App\Models\Station;
use App\Models\User;
use App\Services\CagesTrackRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new CagesTrackRecordService();
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->creator = User::factory()->create();
});

// unit_test_case: returns 422 INVALID_DATE_RANGE when date_from > date_to
// on the list endpoint's underlying query builder.
it('throws InvalidDateRangeException when date_from is after date_to on listRecords()', function () {
    expect(fn () => $this->service->listRecords([
        'date_from' => '2026-02-10',
        'date_to' => '2026-02-01',
    ], 1, 20))->toThrow(InvalidDateRangeException::class);
});

// unit_test_case: returns an empty list when no records match the filter.
it('returns an empty data list and meta.total = 0 when no records match the filter', function () {
    CagesTrackRecord::factory()
        ->forStation($this->station)
        ->onDate('2026-01-01')
        ->create();

    $result = $this->service->listRecords([
        'date_from' => '2020-01-01',
        'date_to' => '2020-01-02',
    ], 1, 20);

    expect($result['data'])->toBe([]);
    expect($result['meta']['total'])->toBe(0);
});

// unit_test_case: success — list with valid filters, paginated correctly.
it('returns a paginated, filtered list with the shared pagination meta shape', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create();
    $otherStation = Station::factory()->forBusinessUnit($otherBusinessUnit)->create();

    // 3 records matching the filter (business unit + date range).
    CagesTrackRecord::factory()
        ->forStation($this->station)
        ->onDate('2026-02-05')
        ->count(3)
        ->create();

    // 1 record outside the date range (should be excluded).
    CagesTrackRecord::factory()
        ->forStation($this->station)
        ->onDate('2026-03-01')
        ->create();

    // 1 record on a different business unit (should be excluded).
    CagesTrackRecord::factory()
        ->forStation($otherStation)
        ->onDate('2026-02-05')
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
        'id', 'cages_track_number', 'date', 'tipped_time_count', 'status',
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

// unit_test_case: tipped_time_count is computed via withCount() against
// the record's related CagesTippedTime rows, not a stored column.
it('computes tipped_time_count as the number of related CagesTippedTime rows', function () {
    $withTwo = CagesTrackRecord::factory()
        ->forStation($this->station)
        ->onDate('2026-02-05')
        ->create();
    CagesTippedTime::factory()->forRecord($withTwo)->count(2)->create();

    $withNone = CagesTrackRecord::factory()
        ->forStation($this->station)
        ->onDate('2026-02-05')
        ->create();

    $result = $this->service->listRecords([
        'date_from' => '2026-02-01',
        'date_to' => '2026-02-10',
    ], 1, 20);

    $rows = collect($result['data'])->keyBy('id');

    expect($rows[$withTwo->id]['tipped_time_count'])->toBe(2);
    expect($rows[$withNone->id]['tipped_time_count'])->toBe(0);
});

// unit_test_case: returns 422 INVALID_DATE_RANGE on the export endpoint
// when date_from > date_to (same validation step, shared buildFilteredQuery()).
it('throws InvalidDateRangeException when date_from is after date_to on export()', function () {
    expect(fn () => $this->service->export([
        'date_from' => '2026-02-10',
        'date_to' => '2026-02-01',
    ], 'csv'))->toThrow(InvalidDateRangeException::class);
});

// unit_test_case: returns 422 EXPORT_FAILED when the filtered dataset
// exceeds CagesTrackRecordService::EXPORT_ROW_LIMIT.
it('throws ExportFailedException when the filtered dataset exceeds the export row limit', function () {
    $limit = CagesTrackRecordService::EXPORT_ROW_LIMIT;
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
                'created_by' => $this->creator->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('cages_track_records')->insert($rows);
        $inserted += $batch;
    }

    expect(CagesTrackRecord::count())->toBe($total);

    expect(fn () => $this->service->export([], 'csv'))->toThrow(ExportFailedException::class);
});

// unit_test_case: success — export with a valid filter, for both csv and
// excel formats, returns a StreamedResponse with the correct content-type.
it('returns a StreamedResponse with the correct content-type for csv and excel formats', function (string $format, string $expectedContentType) {
    $record = CagesTrackRecord::factory()
        ->forStation($this->station)
        ->onDate('2026-02-05')
        ->create();
    CagesTippedTime::factory()->forRecord($record)->create();

    CagesTrackRecord::factory()
        ->forStation($this->station)
        ->onDate('2026-02-05')
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

    expect($body)->toContain('Cages Track Number');
    // 1 header row + 2 data rows.
    expect(substr_count($body, "\n"))->toBeGreaterThanOrEqual(2);
})->with([
    'csv' => ['csv', 'text/csv'],
    'excel' => ['excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
]);
