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
use App\Exceptions\NoActiveCagesTrackStationException;
use App\Models\BusinessUnit;
use App\Models\CagesTippedTime;
use App\Models\CagesTrackRecord;
use App\Models\MillSetting;
use App\Models\Station;
use App\Models\User;
use App\Services\CagesTrackRecordService;
use App\Services\MillSettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * cagesFormPayload() — screen-024--form-cages-track-web test helper,
 * mirrors GradingRecordServiceTest.php's gradingFormPayload() exactly: a
 * complete, valid create()/update() payload with sensible defaults,
 * overridable per test via $overrides.
 */
function cagesFormPayload(array $overrides = []): array
{
    return array_merge([
        'cages_track_number' => 'CT-'.Str::random(6),
        'date' => '2026-08-20',
        'tippler_start_time' => '2026-08-20T08:00:00Z',
        'tippler_stop_time' => '2026-08-20T09:00:00Z',
        'cages_out' => 12,
        'cages_tipped' => 10,
        'details' => [],
    ], $overrides);
}

beforeEach(function () {
    $this->service = new CagesTrackRecordService(new MillSettingService());
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    // Additive for screen-024--form-cages-track-web's create()/update()
    // tests below — a station specifically typed 'cages-track' (the
    // existing $this->station above defaults to 'weighbridge' and is
    // unrelated to create()/update(), which resolve station via
    // type=cages-track).
    $this->cagesTrackStation = Station::factory()->forBusinessUnit($this->businessUnit)->cagesTrack()->create();
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

/**
 * getDetail() — screen-021--detail-cages-track-web unit test cases.
 */
it('throws ModelNotFoundException when the id does not exist', function () {
    $this->service->getDetail((string) Str::uuid());
})->throws(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

it('returns the full record with resolved station_name when id exists', function () {
    $record = CagesTrackRecord::factory()->forStation($this->station)->create();

    $result = $this->service->getDetail($record->id);

    expect($result['id'])->toBe($record->id);
    expect($result['station_name'])->toBe($this->station->name);
});

it('returns tipped_times array ordered by tipped_hour', function () {
    $record = CagesTrackRecord::factory()->forStation($this->station)->create();
    CagesTippedTime::factory()->forRecord($record)->create(['tipped_hour' => 15]);
    CagesTippedTime::factory()->forRecord($record)->create(['tipped_hour' => 3]);
    CagesTippedTime::factory()->forRecord($record)->create(['tipped_hour' => 9]);

    $result = $this->service->getDetail($record->id);

    expect(array_column($result['tipped_times'], 'tipped_hour'))->toBe([3, 9, 15]);
});

it('returns null checked_by_name and acknowledged_by_name when not set', function () {
    $record = CagesTrackRecord::factory()->forStation($this->station)->create(['checked_by' => null, 'acknowledged_by' => null]);

    $result = $this->service->getDetail($record->id);

    expect($result['checked_by_name'])->toBeNull();
    expect($result['acknowledged_by_name'])->toBeNull();
});

it('resolves created_by_name, checked_by_name, acknowledged_by_name to user names when present', function () {
    $checker = User::factory()->create(['name' => 'Budi Checker']);
    $acknowledger = User::factory()->create(['name' => 'Siti Manager']);
    $record = CagesTrackRecord::factory()->forStation($this->station)->create([
        'created_by' => $this->creator->id,
        'checked_by' => $checker->id,
        'acknowledged_by' => $acknowledger->id,
    ]);

    $result = $this->service->getDetail($record->id);

    expect($result['created_by_name'])->toBe($this->creator->name);
    expect($result['checked_by_name'])->toBe('Budi Checker');
    expect($result['acknowledged_by_name'])->toBe('Siti Manager');
});

// screen-024--form-cages-track-web: create()/update() tests below.

it('creates record with resolved station_id and inserted details when valid', function () {
    MillSetting::factory()->forBusinessUnit($this->businessUnit)->withJumlahCages(10)->create();

    $result = $this->service->create(
        cagesFormPayload([
            'business_unit_id' => $this->businessUnit->id,
            'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1, 3, 5]]],
        ]),
        $this->creator
    );

    expect($result['station_id'])->toBe($this->cagesTrackStation->id);
    expect($result['status'])->toBe('saved');
    expect($result['tipped_times'])->toHaveCount(1);
});

it('computes total_cages and cages_remain from checked_cage_numbers and mill_setting.jumlah_cages', function () {
    MillSetting::factory()->forBusinessUnit($this->businessUnit)->withJumlahCages(10)->create();

    $result = $this->service->create(
        cagesFormPayload([
            'business_unit_id' => $this->businessUnit->id,
            'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1, 3, 5]]],
        ]),
        $this->creator
    );

    expect($result['tipped_times'][0]['total_cages'])->toBe(3);
    expect($result['tipped_times'][0]['cages_remain'])->toBe(7);
});

it('auto-creates default mill_setting when business unit has none yet', function () {
    expect(MillSetting::where('business_unit_id', $this->businessUnit->id)->exists())->toBeFalse();

    $result = $this->service->create(
        cagesFormPayload([
            'business_unit_id' => $this->businessUnit->id,
            'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1]]],
        ]),
        $this->creator
    );

    expect(MillSetting::where('business_unit_id', $this->businessUnit->id)->first()->jumlah_cages)->toBe(1);
    expect($result['tipped_times'][0]['cages_remain'])->toBe(0);
});

it('throws ValidationException when a required field is empty', function () {
    MillSetting::factory()->forBusinessUnit($this->businessUnit)->withJumlahCages(10)->create();

    expect(fn () => $this->service->create(
        cagesFormPayload([
            'business_unit_id' => $this->businessUnit->id,
            'cages_track_number' => '',
            'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1]]],
        ]),
        $this->creator
    ))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('throws ValidationException when details array is empty', function () {
    MillSetting::factory()->forBusinessUnit($this->businessUnit)->withJumlahCages(10)->create();

    expect(fn () => $this->service->create(
        cagesFormPayload(['business_unit_id' => $this->businessUnit->id, 'details' => []]),
        $this->creator
    ))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('throws ValidationException when a detail row has empty checked_cage_numbers', function () {
    MillSetting::factory()->forBusinessUnit($this->businessUnit)->withJumlahCages(10)->create();

    expect(fn () => $this->service->create(
        cagesFormPayload([
            'business_unit_id' => $this->businessUnit->id,
            'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => []]],
        ]),
        $this->creator
    ))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('throws ValidationException when tipped_hour is not strictly ascending across detail rows', function () {
    MillSetting::factory()->forBusinessUnit($this->businessUnit)->withJumlahCages(10)->create();

    expect(fn () => $this->service->create(
        cagesFormPayload([
            'business_unit_id' => $this->businessUnit->id,
            'details' => [
                ['tipped_hour' => 7, 'checked_cage_numbers' => [1]],
                ['tipped_hour' => 5, 'checked_cage_numbers' => [2]],
            ],
        ]),
        $this->creator
    ))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('throws ValidationException when two detail rows share the same tipped_hour', function () {
    MillSetting::factory()->forBusinessUnit($this->businessUnit)->withJumlahCages(10)->create();

    expect(fn () => $this->service->create(
        cagesFormPayload([
            'business_unit_id' => $this->businessUnit->id,
            'details' => [
                ['tipped_hour' => 7, 'checked_cage_numbers' => [1]],
                ['tipped_hour' => 7, 'checked_cage_numbers' => [2]],
            ],
        ]),
        $this->creator
    ))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('throws NoActiveCagesTrackStationException when business_unit_id has no active cages-track station', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create();
    MillSetting::factory()->forBusinessUnit($otherBusinessUnit)->withJumlahCages(10)->create();

    expect(fn () => $this->service->create(
        cagesFormPayload([
            'business_unit_id' => $otherBusinessUnit->id,
            'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1]]],
        ]),
        $this->creator
    ))->toThrow(NoActiveCagesTrackStationException::class);
});

it('sets checked_by to requester id when checked=true and requester role=supervisor', function () {
    MillSetting::factory()->forBusinessUnit($this->businessUnit)->withJumlahCages(10)->create();
    $supervisor = User::factory()->role(\App\Enums\UserRole::Supervisor)->create();

    $result = $this->service->create(
        cagesFormPayload([
            'business_unit_id' => $this->businessUnit->id,
            'checked' => true,
            'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1]]],
        ]),
        $supervisor
    );

    expect($result['checked_by_name'])->toBe($supervisor->name);
});

it('ignores checked=true when requester role is not supervisor', function () {
    MillSetting::factory()->forBusinessUnit($this->businessUnit)->withJumlahCages(10)->create();
    $millManagement = User::factory()->role(\App\Enums\UserRole::MillManagement)->create();

    $result = $this->service->create(
        cagesFormPayload([
            'business_unit_id' => $this->businessUnit->id,
            'checked' => true,
            'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1]]],
        ]),
        $millManagement
    );

    expect($result['checked_by_name'])->toBeNull();
});

it('sets acknowledged_by to requester id when acknowledged=true and requester role=mill_management', function () {
    MillSetting::factory()->forBusinessUnit($this->businessUnit)->withJumlahCages(10)->create();
    $millManagement = User::factory()->role(\App\Enums\UserRole::MillManagement)->create();

    $result = $this->service->create(
        cagesFormPayload([
            'business_unit_id' => $this->businessUnit->id,
            'acknowledged' => true,
            'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1]]],
        ]),
        $millManagement
    );

    expect($result['acknowledged_by_name'])->toBe($millManagement->name);
});

it('updates record and upserts details: inserts new row, updates existing row, deletes removed row', function () {
    MillSetting::factory()->forBusinessUnit($this->businessUnit)->withJumlahCages(10)->create();
    $record = CagesTrackRecord::factory()->forStation($this->cagesTrackStation)->create();
    $keptDetail = CagesTippedTime::factory()->forRecord($record)->create(['tipped_hour' => 5, 'checked_cage_numbers' => '1,2']);
    CagesTippedTime::factory()->forRecord($record)->create(['tipped_hour' => 9]);

    $result = $this->service->update(
        $record->id,
        cagesFormPayload([
            'details' => [
                ['id' => $keptDetail->id, 'tipped_hour' => 5, 'checked_cage_numbers' => [1, 2, 3]],
                ['tipped_hour' => 12, 'checked_cage_numbers' => [4]],
            ],
        ]),
        $this->creator
    );

    expect($result['tipped_times'])->toHaveCount(2);
    expect(CagesTippedTime::where('cages_track_record_id', $record->id)->count())->toBe(2);
    expect(CagesTippedTime::find($keptDetail->id)->total_cages)->toBe(3);
    expect(CagesTippedTime::where('tipped_hour', 9)->exists())->toBeFalse();
});

it('updates record without accepting a business_unit_id change', function () {
    MillSetting::factory()->forBusinessUnit($this->businessUnit)->withJumlahCages(10)->create();
    $otherBusinessUnit = BusinessUnit::factory()->create();
    $record = CagesTrackRecord::factory()->forStation($this->cagesTrackStation)->create();
    CagesTippedTime::factory()->forRecord($record)->create();

    $result = $this->service->update(
        $record->id,
        cagesFormPayload([
            'business_unit_id' => $otherBusinessUnit->id,
            'cages_track_number' => 'CT-EDITED',
            'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1]]],
        ]),
        $this->creator
    );

    expect($result['station_id'])->toBe($this->cagesTrackStation->id);
    expect($result['cages_track_number'])->toBe('CT-EDITED');
});

it('throws ModelNotFoundException when updating a non-existent id', function () {
    expect(fn () => $this->service->update(
        (string) Str::uuid(),
        cagesFormPayload(['details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1]]]]),
        $this->creator
    ))->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('getJumlahCages does not enforce MillSettingService::checkAccess role restriction', function () {
    MillSetting::factory()->forBusinessUnit($this->businessUnit)->withJumlahCages(10)->create();
    $supervisor = User::factory()->role(\App\Enums\UserRole::Supervisor)->create();

    $result = $this->service->create(
        cagesFormPayload([
            'business_unit_id' => $this->businessUnit->id,
            'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1]]],
        ]),
        $supervisor
    );

    expect($result['station_id'])->toBe($this->cagesTrackStation->id);
});
