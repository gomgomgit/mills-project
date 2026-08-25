<?php

/**
 * PressingRecordServiceTest — screen-050--data-browser-pressing-web /
 * screen-054--detail-pressing-web / screen-058--form-pressing-web.
 *
 * Unit tests for App\Services\PressingRecordService, mirroring
 * ThreshingRecordServiceTest.php's structure. REVISED 2026-08-24
 * (entity-catalog v12): pressing_detail is now a DYNAMIC add-row/
 * remove-row grid (the user explicitly rejected the original FIXED 24-row
 * design) — create()/update() now upsert whatever rows are given (1..24,
 * unique + strictly ascending canonical time_slot order), exactly like
 * ThreshingRecordService's threshing_detail upsert pattern, adapted for
 * Pressing's own header field (presser_id) and 6 reading columns.
 */

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Exceptions\InvalidDateRangeException;
use App\Exceptions\NoActivePressingStationException;
use App\Models\BusinessUnit;
use App\Models\PressingDetail;
use App\Models\PressingRecord;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use App\Services\PressingRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function pressingFormPayload(array $overrides = []): array
{
    return array_merge([
        'presser_id' => 'PR-'.Str::random(6),
        'date' => '2026-08-24',
        'note' => null,
        'details' => [['time_slot' => '07:00', 'digester_temp_c' => 92]],
    ], $overrides);
}

beforeEach(function () {
    $this->service = new PressingRecordService();
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->pressingStation = Station::factory()->forBusinessUnit($this->businessUnit)->pressing()->create();
    $this->creator = User::factory()->create();
});

it('returns 24 canonical time slots starting at 07:00 and wrapping through 06:00', function () {
    $slots = PressingRecordService::canonicalTimeSlots();

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
    PressingRecord::factory()->forStation($this->station)->onDate('2026-01-01')->create();

    $result = $this->service->listRecords(['date_from' => '2020-01-01', 'date_to' => '2020-01-02'], 1, 20);

    expect($result['data'])->toBe([]);
    expect($result['meta']['total'])->toBe(0);
});

it('returns a paginated, filtered list with the shared pagination meta shape', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create();
    $otherStation = Station::factory()->forBusinessUnit($otherBusinessUnit)->create();

    PressingRecord::factory()->forStation($this->station)->onDate('2026-02-05')->count(3)->create();
    PressingRecord::factory()->forStation($this->station)->onDate('2026-03-01')->create();
    PressingRecord::factory()->forStation($otherStation)->onDate('2026-02-05')->create();

    $result = $this->service->listRecords([
        'date_from' => '2026-02-01',
        'date_to' => '2026-02-10',
        'business_unit_id' => $this->businessUnit->id,
    ], 1, 2);

    expect($result['meta'])->toBe(['page' => 1, 'per_page' => 2, 'total' => 3, 'total_pages' => 2]);
    expect($result['data'])->toHaveCount(2);
    expect(array_keys($result['data'][0]))->toBe(['id', 'presser_id', 'date', 'filled_slot_count', 'status']);
});

it('computes filled_slot_count as the number of detail rows with at least one non-null reading column', function () {
    $withTwoFilled = PressingRecord::factory()->forStation($this->station)->onDate('2026-02-05')->create();
    PressingDetail::factory()->forRecord($withTwoFilled)->timeSlot('07:00')->filled()->create();
    PressingDetail::factory()->forRecord($withTwoFilled)->timeSlot('08:00')->create(['downtime_reason' => 'Maintenance']);
    PressingDetail::factory()->forRecord($withTwoFilled)->timeSlot('09:00')->create(); // unfilled

    $withNone = PressingRecord::factory()->forStation($this->station)->onDate('2026-02-05')->create();
    PressingDetail::factory()->forRecord($withNone)->timeSlot('07:00')->create();

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
    $limit = PressingRecordService::EXPORT_ROW_LIMIT;
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
                'presser_id' => 'PR-BULK-'.($inserted + $i),
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

        DB::table('pressing_records')->insert($rows);
        $inserted += $batch;
    }

    expect(PressingRecord::count())->toBe($total);
    expect(fn () => $this->service->export([], 'csv'))->toThrow(ExportFailedException::class);
});

it('returns a StreamedResponse with the correct content-type for csv and excel formats', function (string $format, string $expectedContentType) {
    PressingRecord::factory()->forStation($this->station)->onDate('2026-02-05')->create();
    PressingRecord::factory()->forStation($this->station)->onDate('2026-02-05')->create();

    $response = $this->service->export(['date_from' => '2026-02-01', 'date_to' => '2026-02-10'], $format);

    expect($response)->toBeInstanceOf(StreamedResponse::class);
    expect($response->headers->get('Content-Type'))->toBe($expectedContentType);

    ob_start();
    $response->sendContent();
    $body = ob_get_clean();

    expect($body)->toContain('Presser ID');
    expect(substr_count($body, "\n"))->toBeGreaterThanOrEqual(2);
})->with([
    'csv' => ['csv', 'text/csv'],
    'excel' => ['excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
]);

it('throws ModelNotFoundException when getDetail id does not exist', function () {
    $this->service->getDetail((string) Str::uuid());
})->throws(ModelNotFoundException::class);

it('returns the full record with resolved station_name when id exists', function () {
    $record = PressingRecord::factory()->forStation($this->station)->create();

    $result = $this->service->getDetail($record->id);

    expect($result['id'])->toBe($record->id);
    expect($result['station_name'])->toBe($this->station->name);
});

it('returns details array sorted into canonical time-slot order, not alphabetical', function () {
    $record = PressingRecord::factory()->forStation($this->station)->create();
    PressingDetail::factory()->forRecord($record)->timeSlot('00:00')->create();
    PressingDetail::factory()->forRecord($record)->timeSlot('09:00')->create();
    PressingDetail::factory()->forRecord($record)->timeSlot('07:00')->create();

    $result = $this->service->getDetail($record->id);

    expect(array_column($result['details'], 'time_slot'))->toBe(['07:00', '09:00', '00:00']);
});

it('returns null checked_by_name and acknowledged_by_name when not set', function () {
    $record = PressingRecord::factory()->forStation($this->station)->create(['checked_by' => null, 'acknowledged_by' => null]);

    $result = $this->service->getDetail($record->id);

    expect($result['checked_by_name'])->toBeNull();
    expect($result['acknowledged_by_name'])->toBeNull();
});

it('resolves created_by_name, checked_by_name, acknowledged_by_name to user names when present', function () {
    $checker = User::factory()->create(['name' => 'Budi Checker']);
    $acknowledger = User::factory()->create(['name' => 'Siti Manager']);
    $record = PressingRecord::factory()->forStation($this->station)->create([
        'created_by' => $this->creator->id,
        'checked_by' => $checker->id,
        'acknowledged_by' => $acknowledger->id,
    ]);

    $result = $this->service->getDetail($record->id);

    expect($result['created_by_name'])->toBe($this->creator->name);
    expect($result['checked_by_name'])->toBe('Budi Checker');
    expect($result['acknowledged_by_name'])->toBe('Siti Manager');
});

// screen-058--form-pressing-web: create()/update() tests below.

it('creates record with resolved station_id and only the given detail rows inserted (no forced 24)', function () {
    $result = $this->service->create(
        pressingFormPayload(['production_line_id' => $this->pressingStation->production_line_id]),
        $this->creator
    );

    expect($result['station_id'])->toBe($this->pressingStation->id);
    expect($result['status'])->toBe('saved');
    expect($result['details'])->toHaveCount(1);
    expect(PressingDetail::where('pressing_record_id', $result['id'])->count())->toBe(1);
});

it('creates a record with multiple detail rows when given several valid, ascending time slots', function () {
    $result = $this->service->create(
        pressingFormPayload([
            'production_line_id' => $this->pressingStation->production_line_id,
            'details' => [
                ['time_slot' => '07:00', 'digester_temp_c' => 90],
                ['time_slot' => '09:00', 'digester_temp_c' => 91],
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
        pressingFormPayload(['production_line_id' => $this->pressingStation->production_line_id, 'presser_id' => '']),
        $this->creator
    ))->toThrow(ValidationException::class);
});

it('throws ValidationException when details is empty', function () {
    expect(fn () => $this->service->create(
        pressingFormPayload(['production_line_id' => $this->pressingStation->production_line_id, 'details' => []]),
        $this->creator
    ))->toThrow(ValidationException::class);
});

it('throws ValidationException when a detail row has a time_slot not in the 24 canonical slots', function () {
    expect(fn () => $this->service->create(
        pressingFormPayload([
            'production_line_id' => $this->pressingStation->production_line_id,
            'details' => [['time_slot' => '07:30', 'digester_temp_c' => 1]],
        ]),
        $this->creator
    ))->toThrow(ValidationException::class);
});

it('throws ValidationException when time_slot is not strictly ascending across detail rows', function () {
    expect(fn () => $this->service->create(
        pressingFormPayload([
            'production_line_id' => $this->pressingStation->production_line_id,
            'details' => [
                ['time_slot' => '09:00', 'digester_temp_c' => 1],
                ['time_slot' => '07:00', 'digester_temp_c' => 2],
            ],
        ]),
        $this->creator
    ))->toThrow(ValidationException::class);
});

it('throws ValidationException when two detail rows share the same time_slot', function () {
    expect(fn () => $this->service->create(
        pressingFormPayload([
            'production_line_id' => $this->pressingStation->production_line_id,
            'details' => [
                ['time_slot' => '07:00', 'digester_temp_c' => 1],
                ['time_slot' => '07:00', 'digester_temp_c' => 2],
            ],
        ]),
        $this->creator
    ))->toThrow(ValidationException::class);
});

it('throws ValidationException when zero rows have any reading column filled', function () {
    expect(fn () => $this->service->create(
        pressingFormPayload([
            'production_line_id' => $this->pressingStation->production_line_id,
            'details' => [['time_slot' => '07:00']],
        ]),
        $this->creator
    ))->toThrow(ValidationException::class);
});

it('treats a row with only downtime_reason filled as satisfying the minimum-one-row rule', function () {
    $result = $this->service->create(
        pressingFormPayload([
            'production_line_id' => $this->pressingStation->production_line_id,
            'details' => [['time_slot' => '10:00', 'downtime_reason' => 'Breakdown']],
        ]),
        $this->creator
    );

    expect($result['status'])->toBe('saved');
    expect($result['details'][0]['downtime_reason'])->toBe('Breakdown');
});

it('throws NoActivePressingStationException when production_line_id has no active pressing station', function () {
    $otherProductionLine = ProductionLine::factory()->create();

    expect(fn () => $this->service->create(
        pressingFormPayload(['production_line_id' => $otherProductionLine->id]),
        $this->creator
    ))->toThrow(NoActivePressingStationException::class);
});

it('sets checked_by to requester id when checked=true and requester role=supervisor', function () {
    $supervisor = User::factory()->role(UserRole::Supervisor)->create();

    $result = $this->service->create(
        pressingFormPayload(['production_line_id' => $this->pressingStation->production_line_id, 'checked' => true]),
        $supervisor
    );

    expect($result['checked_by_name'])->toBe($supervisor->name);
});

it('ignores checked=true when requester role is not supervisor', function () {
    $millManagement = User::factory()->role(UserRole::MillManagement)->create();

    $result = $this->service->create(
        pressingFormPayload(['production_line_id' => $this->pressingStation->production_line_id, 'checked' => true]),
        $millManagement
    );

    expect($result['checked_by_name'])->toBeNull();
});

it('sets acknowledged_by to requester id when acknowledged=true and requester role=mill_management', function () {
    $millManagement = User::factory()->role(UserRole::MillManagement)->create();

    $result = $this->service->create(
        pressingFormPayload(['production_line_id' => $this->pressingStation->production_line_id, 'acknowledged' => true]),
        $millManagement
    );

    expect($result['acknowledged_by_name'])->toBe($millManagement->name);
});

it('updates record and upserts details: inserts new row, updates existing row, deletes removed row', function () {
    $record = PressingRecord::factory()->forStation($this->pressingStation)->create();
    $keptDetail = PressingDetail::factory()->forRecord($record)->timeSlot('07:00')->create(['digester_temp_c' => 90]);
    PressingDetail::factory()->forRecord($record)->timeSlot('12:00')->filled()->create();

    $result = $this->service->update(
        $record->id,
        pressingFormPayload([
            'presser_id' => 'PR-EDITED',
            'details' => [
                ['id' => $keptDetail->id, 'time_slot' => '07:00', 'digester_temp_c' => 93.3],
                ['time_slot' => '15:00', 'digester_temp_c' => 91],
            ],
        ]),
        $this->creator
    );

    expect($result['presser_id'])->toBe('PR-EDITED');
    expect($result['details'])->toHaveCount(2);
    expect(PressingDetail::where('pressing_record_id', $record->id)->count())->toBe(2);
    expect(PressingDetail::find($keptDetail->id)->digester_temp_c)->toBe(93.3);
    expect(PressingDetail::where('time_slot', '12:00')->exists())->toBeFalse();
});

it('updates record without accepting a production_line_id change', function () {
    $record = PressingRecord::factory()->forStation($this->pressingStation)->create();
    $existingDetail = PressingDetail::factory()->forRecord($record)->timeSlot('07:00')->filled()->create();
    $otherProductionLine = ProductionLine::factory()->create();

    $result = $this->service->update(
        $record->id,
        pressingFormPayload([
            'production_line_id' => $otherProductionLine->id,
            'presser_id' => 'PR-EDITED',
            'details' => [
                ['id' => $existingDetail->id, 'time_slot' => $existingDetail->time_slot, 'digester_temp_c' => $existingDetail->digester_temp_c],
            ],
        ]),
        $this->creator
    );

    expect($result['station_id'])->toBe($this->pressingStation->id);
    expect($result['presser_id'])->toBe('PR-EDITED');
});

it('throws ModelNotFoundException when updating a non-existent id', function () {
    expect(fn () => $this->service->update(
        (string) Str::uuid(),
        pressingFormPayload(),
        $this->creator
    ))->toThrow(ModelNotFoundException::class);
});
