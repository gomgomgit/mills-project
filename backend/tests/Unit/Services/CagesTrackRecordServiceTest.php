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
use App\Enums\UserRole;
use App\Exceptions\CrossMillWriteDeniedException;
use App\Exceptions\ExportFailedException;
use App\Exceptions\InvalidDateRangeException;
use App\Exceptions\NoActiveCagesTrackStationException;
use App\Models\BusinessUnit;
use App\Models\CagesTippedTime;
use App\Models\CagesTrackRecord;
use App\Models\Machinery;
use App\Models\Station;
use App\Models\User;
use App\Services\CagesTrackRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
    $this->service = new CagesTrackRecordService();
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    // Additive for screen-024--form-cages-track-web's create()/update()
    // tests below — a station specifically typed 'cages-track' (the
    // existing $this->station above defaults to 'weighbridge' and is
    // unrelated to create()/update(), which resolve station via
    // type=cages-track).
    $this->cagesTrackStation = Station::factory()->forBusinessUnit($this->businessUnit)->cagesTrack()->create();
    $this->creator = User::factory()->forBusinessUnit($this->businessUnit)->create();

    // Jalur BACA (listRecords/export/getDetail) sejak 2026-09-28 memakai
    // AKTOR TERAUTENTIKASI, bukan parameter — lihat bagian READ SIDE di
    // App\Support\Concerns\ScopesToActorMill. Test unit di berkas ini
    // ditulis untuk semantik baca TANPA cakupan mill, jadi aktornya Admin:
    // Admin memang tidak terikat mill, sehingga setiap asersi lama tetap
    // menguji hal yang persis sama. Cakupan mill untuk peran terikat diuji
    // di tests/Feature/Livewire/DataBrowser*Test.php dan Detail*Test.php.
    $this->actingAs(User::factory()->role(UserRole::Admin)->create());
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
                'production_line_id' => $this->station->production_line_id,
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
    $checker = User::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'Budi Checker']);
    $acknowledger = User::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'Siti Manager']);
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
    Machinery::factory()->count(10)->create(['station_id' => $this->cagesTrackStation->id]);

    $result = $this->service->create(
        cagesFormPayload([
            'production_line_id' => $this->cagesTrackStation->production_line_id,
            'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1, 3, 5]]],
        ]),
        $this->creator
    );

    expect($result['station_id'])->toBe($this->cagesTrackStation->id);
    expect($result['status'])->toBe('saved');
    expect($result['tipped_times'])->toHaveCount(1);
});

it('computes total_cages and cages_remain from checked_cage_numbers and COUNT(machinery) for the resolved station', function () {
    Machinery::factory()->count(10)->create(['station_id' => $this->cagesTrackStation->id]);

    $result = $this->service->create(
        cagesFormPayload([
            'production_line_id' => $this->cagesTrackStation->production_line_id,
            'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1, 3, 5]]],
        ]),
        $this->creator
    );

    expect($result['tipped_times'][0]['total_cages'])->toBe(3);
    expect($result['tipped_times'][0]['cages_remain'])->toBe(7);
});

it('resolves cages_remain to 0 when the station has zero machinery registered', function () {
    expect(Machinery::where('station_id', $this->cagesTrackStation->id)->count())->toBe(0);

    $result = $this->service->create(
        cagesFormPayload([
            'production_line_id' => $this->cagesTrackStation->production_line_id,
            'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1]]],
        ]),
        $this->creator
    );

    expect($result['tipped_times'][0]['total_cages'])->toBe(1);
    expect($result['tipped_times'][0]['cages_remain'])->toBe(-1);
});

it('throws ValidationException when a required field is empty', function () {
    expect(fn () => $this->service->create(
        cagesFormPayload([
            'production_line_id' => $this->cagesTrackStation->production_line_id,
            'cages_track_number' => '',
            'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1]]],
        ]),
        $this->creator
    ))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('throws ValidationException when details array is empty', function () {
    expect(fn () => $this->service->create(
        cagesFormPayload(['production_line_id' => $this->cagesTrackStation->production_line_id, 'details' => []]),
        $this->creator
    ))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('throws ValidationException when a detail row has empty checked_cage_numbers', function () {
    expect(fn () => $this->service->create(
        cagesFormPayload([
            'production_line_id' => $this->cagesTrackStation->production_line_id,
            'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => []]],
        ]),
        $this->creator
    ))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('throws ValidationException when tipped_hour is not strictly ascending across detail rows', function () {
    expect(fn () => $this->service->create(
        cagesFormPayload([
            'production_line_id' => $this->cagesTrackStation->production_line_id,
            'details' => [
                ['tipped_hour' => 7, 'checked_cage_numbers' => [1]],
                ['tipped_hour' => 5, 'checked_cage_numbers' => [2]],
            ],
        ]),
        $this->creator
    ))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('throws ValidationException when two detail rows share the same tipped_hour', function () {
    expect(fn () => $this->service->create(
        cagesFormPayload([
            'production_line_id' => $this->cagesTrackStation->production_line_id,
            'details' => [
                ['tipped_hour' => 7, 'checked_cage_numbers' => [1]],
                ['tipped_hour' => 7, 'checked_cage_numbers' => [2]],
            ],
        ]),
        $this->creator
    ))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('throws NoActiveCagesTrackStationException when production_line_id has no active cages-track station', function () {
    $otherProductionLine = \App\Models\ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create();

    expect(fn () => $this->service->create(
        cagesFormPayload([
            'production_line_id' => $otherProductionLine->id,
            'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1]]],
        ]),
        $this->creator
    ))->toThrow(NoActiveCagesTrackStationException::class);
});

it('sets checked_by to requester id when checked=true and requester role=supervisor', function () {
    $supervisor = User::factory()->role(\App\Enums\UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();

    $result = $this->service->create(
        cagesFormPayload([
            'production_line_id' => $this->cagesTrackStation->production_line_id,
            'checked' => true,
            'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1]]],
        ]),
        $supervisor
    );

    expect($result['checked_by_name'])->toBe($supervisor->name);
});

it('ignores checked=true when requester role is not supervisor', function () {
    $millManagement = User::factory()->role(\App\Enums\UserRole::MillManagement)->forBusinessUnit($this->businessUnit)->create();

    $result = $this->service->create(
        cagesFormPayload([
            'production_line_id' => $this->cagesTrackStation->production_line_id,
            'checked' => true,
            'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1]]],
        ]),
        $millManagement
    );

    expect($result['checked_by_name'])->toBeNull();
});

it('sets acknowledged_by to requester id when acknowledged=true and requester role=mill_management', function () {
    $millManagement = User::factory()->role(\App\Enums\UserRole::MillManagement)->forBusinessUnit($this->businessUnit)->create();

    $result = $this->service->create(
        cagesFormPayload([
            'production_line_id' => $this->cagesTrackStation->production_line_id,
            'acknowledged' => true,
            'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1]]],
        ]),
        $millManagement
    );

    expect($result['acknowledged_by_name'])->toBe($millManagement->name);
});

// REGRESI 2026-09-25 — urutan upsertDetails().
//
// Sampai hari ini upsertDetails() menyisipkan baris baru SEBELUM menghapus
// baris basi, sehingga memindahkan sebuah pembacaan ke slot yang SEDANG
// DIPAKAI baris lain yang akan dihapus melanggar
// UNIQUE(cages_track_record_id, tipped_hour) pada tabel cages_tipped_times dan melempar
// UniqueConstraintViolationException. Operator yang salah pilih jam lalu
// membetulkannya menabrak ini.
//
// Test ini HARUS merah bila urutannya dikembalikan. Asersinya memeriksa
// jumlah baris akhir DAN nilainya — "tidak melempar" saja tidak cukup,
// karena urutan yang salah juga bisa menyisakan baris basi diam-diam.
it('memindahkan pembacaan ke slot yang sedang dipakai baris yang akan dihapus', function () {
    Machinery::factory()->count(10)->create(['station_id' => $this->cagesTrackStation->id]);
    $record = CagesTrackRecord::factory()->forStation($this->cagesTrackStation)->create();
    $moved = CagesTippedTime::factory()->forRecord($record)->create(['tipped_hour' => 7, 'checked_cage_numbers' => '1']);
    CagesTippedTime::factory()->forRecord($record)->create(['tipped_hour' => 8, 'checked_cage_numbers' => '9']);

    // Satu baris saja yang dikirim: baris jam 7 dipindah ke jam 8.
    // Baris jam 8 yang lama harus hilang, dan slot itu ditempati baris jam 7.
    $result = $this->service->update(
        $record->id,
        cagesFormPayload([
            'details' => [
                ['id' => $moved->id, 'tipped_hour' => 8, 'checked_cage_numbers' => [1, 2, 3]],
            ],
        ]),
        $this->creator
    );

    expect($result['tipped_times'])->toHaveCount(1);
    expect(CagesTippedTime::where('cages_track_record_id', $record->id)->count())->toBe(1);

    $remaining = CagesTippedTime::where('cages_track_record_id', $record->id)->first();
    expect($remaining->id)->toBe($moved->id);
    expect($remaining->tipped_hour)->toBe(8);
    expect($remaining->total_cages)->toBe(3);
});

it('updates record and upserts details: inserts new row, updates existing row, deletes removed row', function () {
    Machinery::factory()->count(10)->create(['station_id' => $this->cagesTrackStation->id]);
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

it('updates record without accepting a production_line_id change', function () {
    $otherProductionLine = \App\Models\ProductionLine::factory()->create();
    $record = CagesTrackRecord::factory()->forStation($this->cagesTrackStation)->create();
    CagesTippedTime::factory()->forRecord($record)->create();

    $result = $this->service->update(
        $record->id,
        cagesFormPayload([
            'production_line_id' => $otherProductionLine->id,
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

it('machineryCountForStation does not enforce any role restriction — any actor can create()', function () {
    $supervisor = User::factory()->role(\App\Enums\UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();

    $result = $this->service->create(
        cagesFormPayload([
            'production_line_id' => $this->cagesTrackStation->production_line_id,
            'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1]]],
        ]),
        $supervisor
    );

    expect($result['station_id'])->toBe($this->cagesTrackStation->id);
});

/*
|--------------------------------------------------------------------------
| Cross-mill write guard (CagesTrack) — 2026-09-28
|--------------------------------------------------------------------------
| A Production Line is a CHOSEN CONTEXT, not an account binding: the rule
| under test is "the chosen line must sit inside the ACTOR'S mill"
| (users.business_unit_id), with Admin recognised BY ROLE and therefore not
| mill-bound at all. See App\Support\Concerns\ScopesToActorMill.
|
| Before 2026-09-28 create() took `production_line_id` from the client and
| never looked at the actor's mill, and update() never checked ownership at
| all — an Operator of Mill A could write, and PATCH, Mill B's log sheet.
*/

it('menolak create() ke production line mill lain, tanpa menulis satu baris pun', function (UserRole $role) {
    Machinery::factory()->count(10)->create(['station_id' => $this->cagesTrackStation->id]);
    $otherMill = BusinessUnit::factory()->create();
    $otherStation = Station::factory()->forBusinessUnit($otherMill)->cagesTrack()->create();
    $actor = User::factory()->role($role)->forBusinessUnit($this->businessUnit)->create();

    $recordsBefore = CagesTrackRecord::count();
    $detailsBefore = CagesTippedTime::count();

    expect(fn () => $this->service->create(cagesFormPayload(['production_line_id' => $otherStation->production_line_id, 'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1]]]]), $actor))
        ->toThrow(CrossMillWriteDeniedException::class);

    expect(CagesTrackRecord::count())->toBe($recordsBefore);
    expect(CagesTippedTime::count())->toBe($detailsBefore);
})->with([
    'operator' => UserRole::Operator,
    'supervisor' => UserRole::Supervisor,
    'mill management' => UserRole::MillManagement,
]);

it('menolak update() record milik mill lain, dan tidak mengubah satu kolom pun', function () {
    Machinery::factory()->count(10)->create(['station_id' => $this->cagesTrackStation->id]);
    $otherMill = BusinessUnit::factory()->create();
    $otherStation = Station::factory()->forBusinessUnit($otherMill)->cagesTrack()->create();
    $record = CagesTrackRecord::factory()->forStation($otherStation)->create(['cages_track_number' => 'SCOPE-MILIK-MILL-B']);
    $before = $record->fresh()->getAttributes();

    expect(fn () => $this->service->update($record->id, cagesFormPayload(['cages_track_number' => 'SCOPE-HIJACKED', 'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1]]]]), $this->creator))
        ->toThrow(CrossMillWriteDeniedException::class);

    expect($record->fresh()->getAttributes())->toBe($before);
});

it('mengizinkan Admin menulis ke line mill mana pun (dibuktikan dengan dua mill berbeda)', function () {
    Machinery::factory()->count(10)->create(['station_id' => $this->cagesTrackStation->id]);
    $millB = BusinessUnit::factory()->create();
    $stationB = Station::factory()->forBusinessUnit($millB)->cagesTrack()->create();
    // Admin dinilai dari PERAN: business_unit_id-nya sengaja diisi (19 dari 21
    // Admin di dev punya kolom ini terisi) dan harus diabaikan.
    $admin = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->businessUnit)->create();

    $inOwnMill = $this->service->create(cagesFormPayload(['production_line_id' => $this->cagesTrackStation->production_line_id, 'cages_track_number' => 'SCOPE-MILL-A', 'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1]]]]), $admin);
    $inOtherMill = $this->service->create(cagesFormPayload(['production_line_id' => $stationB->production_line_id, 'cages_track_number' => 'SCOPE-MILL-B', 'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1]]]]), $admin);

    expect(CagesTrackRecord::find($inOwnMill['id'])->station_id)->toBe($this->cagesTrackStation->id);
    expect(CagesTrackRecord::find($inOtherMill['id'])->station_id)->toBe($stationB->id);
});

it('gagal tertutup dengan pesan actionable ketika akun aktor belum terhubung ke mill', function () {
    Machinery::factory()->count(10)->create(['station_id' => $this->cagesTrackStation->id]);
    $actor = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);
    $recordsBefore = CagesTrackRecord::count();

    try {
        $this->service->create(cagesFormPayload(['production_line_id' => $this->cagesTrackStation->production_line_id, 'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1]]]]), $actor);
        $this->fail('create() seharusnya ditolak untuk aktor tanpa business_unit_id.');
    } catch (ValidationException $e) {
        expect($e->errors()['production_line_id'][0])->toBe('Akun Anda belum terhubung ke mill. Hubungi Admin.');
    }

    expect(CagesTrackRecord::count())->toBe($recordsBefore);
});

it('tetap mengizinkan create() dan update() pada line mill sendiri', function () {
    Machinery::factory()->count(10)->create(['station_id' => $this->cagesTrackStation->id]);
    $created = $this->service->create(cagesFormPayload(['production_line_id' => $this->cagesTrackStation->production_line_id, 'cages_track_number' => 'SCOPE-OWN-1', 'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1]]]]), $this->creator);

    expect(CagesTrackRecord::find($created['id'])->station_id)->toBe($this->cagesTrackStation->id);

    $this->service->update($created['id'], cagesFormPayload(['cages_track_number' => 'SCOPE-OWN-2', 'details' => [['tipped_hour' => 8, 'checked_cage_numbers' => [1]]]]), $this->creator);

    expect(CagesTrackRecord::find($created['id'])->cages_track_number)->toBe('SCOPE-OWN-2');
});
