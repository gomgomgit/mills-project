<?php

/**
 * ThreshingRecordServiceTest — screen-049--data-browser-threshing-web /
 * screen-053--detail-threshing-web / screen-057--form-threshing-web.
 *
 * Unit tests for App\Services\ThreshingRecordService, mirroring
 * CagesTrackRecordServiceTest.php's structure. REVISED 2026-08-24
 * (entity-catalog v12): threshing_detail is now a DYNAMIC add-row/
 * remove-row grid (the user explicitly rejected the original FIXED 24-row
 * design) — create()/update() now upsert whatever rows are given (1..24,
 * unique + strictly ascending canonical time_slot order), exactly like
 * CagesTrackRecordService's cages_tipped_time upsert pattern.
 */

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Exceptions\InvalidDateRangeException;
use App\Exceptions\NoActiveThreshingStationException;
use App\Models\BusinessUnit;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\ThreshingDetail;
use App\Models\ThreshingRecord;
use App\Models\User;
use App\Services\ThreshingRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function threshingFormPayload(array $overrides = []): array
{
    return array_merge([
        'thresher_id' => 'TH-'.Str::random(6),
        'date' => '2026-08-24',
        'note' => null,
        'details' => [['time_slot' => '07:00', 'ffb_throughput_mt_hour' => 45.5]],
    ], $overrides);
}

beforeEach(function () {
    $this->service = new ThreshingRecordService();
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->threshingStation = Station::factory()->forBusinessUnit($this->businessUnit)->threshing()->create();
    $this->creator = User::factory()->create();
});

it('returns 24 canonical time slots starting at 07:00 and wrapping through 06:00', function () {
    $slots = ThreshingRecordService::canonicalTimeSlots();

    expect($slots)->toHaveCount(24);
    expect($slots[0])->toBe('07:00');
    expect($slots[16])->toBe('23:00');
    expect($slots[17])->toBe('00:00');
    expect($slots[23])->toBe('06:00');
});

it('throws InvalidDateRangeException when date_from is after date_to on listRecords()', function () {
    expect(fn () => $this->service->listRecords([
        'date_from' => '2026-02-10',
        'date_to' => '2026-02-01',
    ], 1, 20))->toThrow(InvalidDateRangeException::class);
});

it('returns an empty data list and meta.total = 0 when no records match the filter', function () {
    ThreshingRecord::factory()->forStation($this->station)->onDate('2026-01-01')->create();

    $result = $this->service->listRecords(['date_from' => '2020-01-01', 'date_to' => '2020-01-02'], 1, 20);

    expect($result['data'])->toBe([]);
    expect($result['meta']['total'])->toBe(0);
});

it('returns a paginated, filtered list with the shared pagination meta shape', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create();
    $otherStation = Station::factory()->forBusinessUnit($otherBusinessUnit)->create();

    ThreshingRecord::factory()->forStation($this->station)->onDate('2026-02-05')->count(3)->create();
    ThreshingRecord::factory()->forStation($this->station)->onDate('2026-03-01')->create();
    ThreshingRecord::factory()->forStation($otherStation)->onDate('2026-02-05')->create();

    $result = $this->service->listRecords([
        'date_from' => '2026-02-01',
        'date_to' => '2026-02-10',
        'business_unit_id' => $this->businessUnit->id,
    ], 1, 2);

    expect($result['meta'])->toBe(['page' => 1, 'per_page' => 2, 'total' => 3, 'total_pages' => 2]);
    expect($result['data'])->toHaveCount(2);
    expect(array_keys($result['data'][0]))->toBe(['id', 'thresher_id', 'date', 'filled_slot_count', 'status']);
});

it('computes filled_slot_count as the number of detail rows with at least one non-null reading column', function () {
    $withTwoFilled = ThreshingRecord::factory()->forStation($this->station)->onDate('2026-02-05')->create();
    ThreshingDetail::factory()->forRecord($withTwoFilled)->timeSlot('07:00')->filled()->create();
    ThreshingDetail::factory()->forRecord($withTwoFilled)->timeSlot('08:00')->create(['downtime_reason' => 'Maintenance']);
    ThreshingDetail::factory()->forRecord($withTwoFilled)->timeSlot('09:00')->create(); // unfilled

    $withNone = ThreshingRecord::factory()->forStation($this->station)->onDate('2026-02-05')->create();
    ThreshingDetail::factory()->forRecord($withNone)->timeSlot('07:00')->create();

    $result = $this->service->listRecords(['date_from' => '2026-02-01', 'date_to' => '2026-02-10'], 1, 20);
    $rows = collect($result['data'])->keyBy('id');

    expect($rows[$withTwoFilled->id]['filled_slot_count'])->toBe(2);
    expect($rows[$withNone->id]['filled_slot_count'])->toBe(0);
});

it('throws InvalidDateRangeException when date_from is after date_to on export()', function () {
    expect(fn () => $this->service->export(['date_from' => '2026-02-10', 'date_to' => '2026-02-01'], 'csv'))
        ->toThrow(InvalidDateRangeException::class);
});

it('throws ExportFailedException when the filtered dataset exceeds the export row limit', function () {
    $limit = ThreshingRecordService::EXPORT_ROW_LIMIT;
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
                'thresher_id' => 'TH-BULK-'.($inserted + $i),
                'date' => $now->toDateString(),
                'note' => null,
                'checked_by' => null,
                'acknowledged_by' => null,
                'status' => RecordStatus::Synced->value,
                'created_by' => $this->creator->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('threshing_records')->insert($rows);
        $inserted += $batch;
    }

    expect(ThreshingRecord::count())->toBe($total);
    expect(fn () => $this->service->export([], 'csv'))->toThrow(ExportFailedException::class);
});

it('returns a StreamedResponse with the correct content-type for csv and excel formats', function (string $format, string $expectedContentType) {
    ThreshingRecord::factory()->forStation($this->station)->onDate('2026-02-05')->create();
    ThreshingRecord::factory()->forStation($this->station)->onDate('2026-02-05')->create();

    $response = $this->service->export(['date_from' => '2026-02-01', 'date_to' => '2026-02-10'], $format);

    expect($response)->toBeInstanceOf(StreamedResponse::class);
    expect($response->headers->get('Content-Type'))->toBe($expectedContentType);

    ob_start();
    $response->sendContent();
    $body = ob_get_clean();

    expect($body)->toContain('Thresher ID');
    expect(substr_count($body, "\n"))->toBeGreaterThanOrEqual(2);
})->with([
    'csv' => ['csv', 'text/csv'],
    'excel' => ['excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
]);

it('throws ModelNotFoundException when getDetail id does not exist', function () {
    $this->service->getDetail((string) Str::uuid());
})->throws(ModelNotFoundException::class);

it('returns the full record with resolved station_name when id exists', function () {
    $record = ThreshingRecord::factory()->forStation($this->station)->create();

    $result = $this->service->getDetail($record->id);

    expect($result['id'])->toBe($record->id);
    expect($result['station_name'])->toBe($this->station->name);
});

it('returns details array sorted into canonical time-slot order, not alphabetical', function () {
    $record = ThreshingRecord::factory()->forStation($this->station)->create();
    ThreshingDetail::factory()->forRecord($record)->timeSlot('00:00')->create();
    ThreshingDetail::factory()->forRecord($record)->timeSlot('09:00')->create();
    ThreshingDetail::factory()->forRecord($record)->timeSlot('07:00')->create();

    $result = $this->service->getDetail($record->id);

    expect(array_column($result['details'], 'time_slot'))->toBe(['07:00', '09:00', '00:00']);
});

it('returns null checked_by_name and acknowledged_by_name when not set', function () {
    $record = ThreshingRecord::factory()->forStation($this->station)->create(['checked_by' => null, 'acknowledged_by' => null]);

    $result = $this->service->getDetail($record->id);

    expect($result['checked_by_name'])->toBeNull();
    expect($result['acknowledged_by_name'])->toBeNull();
});

it('resolves created_by_name, checked_by_name, acknowledged_by_name to user names when present', function () {
    $checker = User::factory()->create(['name' => 'Budi Checker']);
    $acknowledger = User::factory()->create(['name' => 'Siti Manager']);
    $record = ThreshingRecord::factory()->forStation($this->station)->create([
        'created_by' => $this->creator->id,
        'checked_by' => $checker->id,
        'acknowledged_by' => $acknowledger->id,
    ]);

    $result = $this->service->getDetail($record->id);

    expect($result['created_by_name'])->toBe($this->creator->name);
    expect($result['checked_by_name'])->toBe('Budi Checker');
    expect($result['acknowledged_by_name'])->toBe('Siti Manager');
});

// screen-057--form-threshing-web: create()/update() tests below.

it('creates record with resolved station_id and only the given detail rows inserted (no forced 24)', function () {
    $result = $this->service->create(
        threshingFormPayload(['production_line_id' => $this->threshingStation->production_line_id]),
        $this->creator
    );

    expect($result['station_id'])->toBe($this->threshingStation->id);
    expect($result['status'])->toBe('saved');
    expect($result['details'])->toHaveCount(1);
    expect(ThreshingDetail::where('threshing_record_id', $result['id'])->count())->toBe(1);
});

it('creates a record with multiple detail rows when given several valid, ascending time slots', function () {
    $result = $this->service->create(
        threshingFormPayload([
            'production_line_id' => $this->threshingStation->production_line_id,
            'details' => [
                ['time_slot' => '07:00', 'ffb_throughput_mt_hour' => 10],
                ['time_slot' => '09:00', 'ffb_throughput_mt_hour' => 20],
                ['time_slot' => '14:00', 'downtime_reason' => 'Maintenance'],
            ],
        ]),
        $this->creator
    );

    expect($result['details'])->toHaveCount(3);
    expect(array_column($result['details'], 'time_slot'))->toBe(['07:00', '09:00', '14:00']);
});

it('throws ValidationException when a required field is empty', function () {
    expect(fn () => $this->service->create(
        threshingFormPayload(['production_line_id' => $this->threshingStation->production_line_id, 'thresher_id' => '']),
        $this->creator
    ))->toThrow(ValidationException::class);
});

it('throws ValidationException when details is empty', function () {
    expect(fn () => $this->service->create(
        threshingFormPayload(['production_line_id' => $this->threshingStation->production_line_id, 'details' => []]),
        $this->creator
    ))->toThrow(ValidationException::class);
});

it('throws ValidationException when a detail row has a time_slot not in the 24 canonical slots', function () {
    expect(fn () => $this->service->create(
        threshingFormPayload([
            'production_line_id' => $this->threshingStation->production_line_id,
            'details' => [['time_slot' => '07:30', 'ffb_throughput_mt_hour' => 1]],
        ]),
        $this->creator
    ))->toThrow(ValidationException::class);
});

it('throws ValidationException when time_slot is not strictly ascending across detail rows', function () {
    expect(fn () => $this->service->create(
        threshingFormPayload([
            'production_line_id' => $this->threshingStation->production_line_id,
            'details' => [
                ['time_slot' => '09:00', 'ffb_throughput_mt_hour' => 1],
                ['time_slot' => '07:00', 'ffb_throughput_mt_hour' => 2],
            ],
        ]),
        $this->creator
    ))->toThrow(ValidationException::class);
});

it('throws ValidationException when two detail rows share the same time_slot', function () {
    expect(fn () => $this->service->create(
        threshingFormPayload([
            'production_line_id' => $this->threshingStation->production_line_id,
            'details' => [
                ['time_slot' => '07:00', 'ffb_throughput_mt_hour' => 1],
                ['time_slot' => '07:00', 'ffb_throughput_mt_hour' => 2],
            ],
        ]),
        $this->creator
    ))->toThrow(ValidationException::class);
});

it('throws ValidationException when zero rows have any reading column filled', function () {
    expect(fn () => $this->service->create(
        threshingFormPayload([
            'production_line_id' => $this->threshingStation->production_line_id,
            'details' => [['time_slot' => '07:00']],
        ]),
        $this->creator
    ))->toThrow(ValidationException::class);
});

it('treats a row with only downtime_reason filled as satisfying the minimum-one-row rule', function () {
    $result = $this->service->create(
        threshingFormPayload([
            'production_line_id' => $this->threshingStation->production_line_id,
            'details' => [['time_slot' => '10:00', 'downtime_reason' => 'Breakdown']],
        ]),
        $this->creator
    );

    expect($result['status'])->toBe('saved');
    expect($result['details'][0]['downtime_reason'])->toBe('Breakdown');
});

it('throws NoActiveThreshingStationException when production_line_id has no active threshing station', function () {
    $otherProductionLine = ProductionLine::factory()->create();

    expect(fn () => $this->service->create(
        threshingFormPayload(['production_line_id' => $otherProductionLine->id]),
        $this->creator
    ))->toThrow(NoActiveThreshingStationException::class);
});

it('sets checked_by to requester id when checked=true and requester role=supervisor', function () {
    $supervisor = User::factory()->role(UserRole::Supervisor)->create();

    $result = $this->service->create(
        threshingFormPayload(['production_line_id' => $this->threshingStation->production_line_id, 'checked' => true]),
        $supervisor
    );

    expect($result['checked_by_name'])->toBe($supervisor->name);
});

it('ignores checked=true when requester role is not supervisor', function () {
    $millManagement = User::factory()->role(UserRole::MillManagement)->create();

    $result = $this->service->create(
        threshingFormPayload(['production_line_id' => $this->threshingStation->production_line_id, 'checked' => true]),
        $millManagement
    );

    expect($result['checked_by_name'])->toBeNull();
});

it('sets acknowledged_by to requester id when acknowledged=true and requester role=mill_management', function () {
    $millManagement = User::factory()->role(UserRole::MillManagement)->create();

    $result = $this->service->create(
        threshingFormPayload(['production_line_id' => $this->threshingStation->production_line_id, 'acknowledged' => true]),
        $millManagement
    );

    expect($result['acknowledged_by_name'])->toBe($millManagement->name);
});

it('updates record and upserts details: inserts new row, updates existing row, deletes removed row', function () {
    $record = ThreshingRecord::factory()->forStation($this->threshingStation)->create();
    $keptDetail = ThreshingDetail::factory()->forRecord($record)->timeSlot('07:00')->create(['ffb_throughput_mt_hour' => 10]);
    ThreshingDetail::factory()->forRecord($record)->timeSlot('12:00')->filled()->create();

    $result = $this->service->update(
        $record->id,
        threshingFormPayload([
            'thresher_id' => 'TH-EDITED',
            'details' => [
                ['id' => $keptDetail->id, 'time_slot' => '07:00', 'ffb_throughput_mt_hour' => 88.8],
                ['time_slot' => '15:00', 'ffb_throughput_mt_hour' => 5],
            ],
        ]),
        $this->creator
    );

    expect($result['thresher_id'])->toBe('TH-EDITED');
    expect($result['details'])->toHaveCount(2);
    expect(ThreshingDetail::where('threshing_record_id', $record->id)->count())->toBe(2);
    expect(ThreshingDetail::find($keptDetail->id)->ffb_throughput_mt_hour)->toBe(88.8);
    expect(ThreshingDetail::where('time_slot', '12:00')->exists())->toBeFalse();
});

it('updates record without accepting a production_line_id change', function () {
    $record = ThreshingRecord::factory()->forStation($this->threshingStation)->create();
    $existingDetail = ThreshingDetail::factory()->forRecord($record)->timeSlot('07:00')->filled()->create();
    $otherProductionLine = ProductionLine::factory()->create();

    $result = $this->service->update(
        $record->id,
        threshingFormPayload([
            'production_line_id' => $otherProductionLine->id,
            'thresher_id' => 'TH-EDITED',
            'details' => [
                ['id' => $existingDetail->id, 'time_slot' => $existingDetail->time_slot, 'ffb_throughput_mt_hour' => $existingDetail->ffb_throughput_mt_hour],
            ],
        ]),
        $this->creator
    );

    expect($result['station_id'])->toBe($this->threshingStation->id);
    expect($result['thresher_id'])->toBe('TH-EDITED');
});

it('throws ModelNotFoundException when updating a non-existent id', function () {
    expect(fn () => $this->service->update(
        (string) Str::uuid(),
        threshingFormPayload(),
        $this->creator
    ))->toThrow(ModelNotFoundException::class);
});
