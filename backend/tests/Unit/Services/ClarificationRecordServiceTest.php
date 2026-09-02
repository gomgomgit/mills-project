<?php

/**
 * ClarificationRecordServiceTest — screen-099--data-browser-clarification-web
 * / screen-109--detail-clarification-web / screen-119--form-clarification-web.
 *
 * Unit tests for App\Services\ClarificationRecordService, mirroring
 * BoilerRoomRecordServiceTest.php's structure — Clarification follows the
 * same hourly-grid (dynamic add-row/remove-row, 1..24 rows, unique +
 * strictly ascending canonical time_slot) pattern as Boiler Room/Engine
 * Room/Storage Tank/Effluent Plant, but has NO operational-target
 * reference table AND NO enum/status columns at all — the simplest
 * station in the project. This station has 7 non-time_slot columns per
 * detail row (6 numeric + Findings).
 */

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Exceptions\InvalidDateRangeException;
use App\Exceptions\NoActiveClarificationStationException;
use App\Models\ClarificationDetail;
use App\Models\ClarificationRecord;
use App\Models\BusinessUnit;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use App\Services\ClarificationRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function clarificationFormPayload(array $overrides = []): array
{
    return array_merge([
        'clarification_id' => 'CLR-'.Str::random(6),
        'date' => '2026-08-31',
        'note' => null,
        'details' => [['time_slot' => '07:00', 'clarification_tank_temp_c' => 65.5]],
    ], $overrides);
}

beforeEach(function () {
    $this->service = new ClarificationRecordService();
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    $this->clarificationStation = Station::factory()->forBusinessUnit($this->businessUnit)->clarification()->create();
    $this->creator = User::factory()->create();
});

it('returns 24 canonical time slots starting at 07:00 and wrapping through 06:00', function () {
    $slots = ClarificationRecordService::canonicalTimeSlots();

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
    ClarificationRecord::factory()->forStation($this->station)->onDate('2026-01-01')->create();

    $result = $this->service->listRecords(['date_from' => '2020-01-01', 'date_to' => '2020-01-02'], 1, 20);

    expect($result['data'])->toBe([]);
    expect($result['meta']['total'])->toBe(0);
});

it('returns a paginated, filtered list with the shared pagination meta shape', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create();
    $otherStation = Station::factory()->forBusinessUnit($otherBusinessUnit)->create();

    ClarificationRecord::factory()->forStation($this->station)->onDate('2026-02-05')->count(3)->create();
    ClarificationRecord::factory()->forStation($this->station)->onDate('2026-03-01')->create();
    ClarificationRecord::factory()->forStation($otherStation)->onDate('2026-02-05')->create();

    $result = $this->service->listRecords([
        'date_from' => '2026-02-01',
        'date_to' => '2026-02-10',
        'business_unit_id' => $this->businessUnit->id,
    ], 1, 2);

    expect($result['meta'])->toBe(['page' => 1, 'per_page' => 2, 'total' => 3, 'total_pages' => 2]);
    expect($result['data'])->toHaveCount(2);
    expect(array_keys($result['data'][0]))->toBe(['id', 'clarification_id', 'date', 'filled_slot_count', 'status']);
});

it('computes filled_slot_count as the number of detail rows with at least one non-null column', function () {
    $withTwoFilled = ClarificationRecord::factory()->forStation($this->station)->onDate('2026-02-05')->create();
    ClarificationDetail::factory()->forRecord($withTwoFilled)->timeSlot('07:00')->filled()->create();
    ClarificationDetail::factory()->forRecord($withTwoFilled)->timeSlot('08:00')->create(['findings' => 'Perlu cek ulang']);
    ClarificationDetail::factory()->forRecord($withTwoFilled)->timeSlot('09:00')->create(); // unfilled

    $withNone = ClarificationRecord::factory()->forStation($this->station)->onDate('2026-02-05')->create();
    ClarificationDetail::factory()->forRecord($withNone)->timeSlot('07:00')->create();

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
    $limit = ClarificationRecordService::EXPORT_ROW_LIMIT;
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
                'clarification_id' => 'CLR-BULK-'.($inserted + $i),
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

        DB::table('clarification_records')->insert($rows);
        $inserted += $batch;
    }

    expect(ClarificationRecord::count())->toBe($total);
    expect(fn () => $this->service->export([], 'csv'))->toThrow(ExportFailedException::class);
});

it('returns a StreamedResponse with the correct content-type for csv and excel formats', function (string $format, string $expectedContentType) {
    ClarificationRecord::factory()->forStation($this->station)->onDate('2026-02-05')->create();
    ClarificationRecord::factory()->forStation($this->station)->onDate('2026-02-05')->create();

    $response = $this->service->export(['date_from' => '2026-02-01', 'date_to' => '2026-02-10'], $format);

    expect($response)->toBeInstanceOf(StreamedResponse::class);
    expect($response->headers->get('Content-Type'))->toBe($expectedContentType);

    ob_start();
    $response->sendContent();
    $body = ob_get_clean();

    expect($body)->toContain('Clarification ID');
    expect(substr_count($body, "\n"))->toBeGreaterThanOrEqual(2);
})->with([
    'csv' => ['csv', 'text/csv'],
    'excel' => ['excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
]);

it('throws ModelNotFoundException when getDetail id does not exist', function () {
    $this->service->getDetail((string) Str::uuid());
})->throws(ModelNotFoundException::class);

it('returns the full record with resolved station_name when id exists', function () {
    $record = ClarificationRecord::factory()->forStation($this->station)->create();

    $result = $this->service->getDetail($record->id);

    expect($result['id'])->toBe($record->id);
    expect($result['station_name'])->toBe($this->station->name);
});

it('returns details array sorted into canonical time-slot order, not alphabetical', function () {
    $record = ClarificationRecord::factory()->forStation($this->station)->create();
    ClarificationDetail::factory()->forRecord($record)->timeSlot('00:00')->create();
    ClarificationDetail::factory()->forRecord($record)->timeSlot('09:00')->create();
    ClarificationDetail::factory()->forRecord($record)->timeSlot('07:00')->create();

    $result = $this->service->getDetail($record->id);

    expect(array_column($result['details'], 'time_slot'))->toBe(['07:00', '09:00', '00:00']);
});

it('returns null checked_by_name and acknowledged_by_name when not set', function () {
    $record = ClarificationRecord::factory()->forStation($this->station)->create(['checked_by' => null, 'acknowledged_by' => null]);

    $result = $this->service->getDetail($record->id);

    expect($result['checked_by_name'])->toBeNull();
    expect($result['acknowledged_by_name'])->toBeNull();
});

it('resolves created_by_name, checked_by_name, acknowledged_by_name to user names when present', function () {
    $checker = User::factory()->create(['name' => 'Budi Checker']);
    $acknowledger = User::factory()->create(['name' => 'Siti Manager']);
    $record = ClarificationRecord::factory()->forStation($this->station)->create([
        'created_by' => $this->creator->id,
        'checked_by' => $checker->id,
        'acknowledged_by' => $acknowledger->id,
    ]);

    $result = $this->service->getDetail($record->id);

    expect($result['created_by_name'])->toBe($this->creator->name);
    expect($result['checked_by_name'])->toBe('Budi Checker');
    expect($result['acknowledged_by_name'])->toBe('Siti Manager');
});

// screen-119--form-clarification-web: create()/update() tests below.

it('creates record with resolved station_id and only the given detail rows inserted (no forced 24)', function () {
    $result = $this->service->create(
        clarificationFormPayload(['production_line_id' => $this->clarificationStation->production_line_id]),
        $this->creator
    );

    expect($result['station_id'])->toBe($this->clarificationStation->id);
    expect($result['status'])->toBe('saved');
    expect($result['details'])->toHaveCount(1);
    expect(ClarificationDetail::where('clarification_record_id', $result['id'])->count())->toBe(1);
});

it('creates a record with multiple detail rows when given several valid, ascending time slots', function () {
    $result = $this->service->create(
        clarificationFormPayload([
            'production_line_id' => $this->clarificationStation->production_line_id,
            'details' => [
                ['time_slot' => '07:00', 'clarification_tank_temp_c' => 60],
                ['time_slot' => '09:00', 'oil_tank_temperature_c' => 55.5],
                ['time_slot' => '14:00', 'findings' => 'Perlu ditinjau'],
            ],
        ]),
        $this->creator
    );

    expect($result['details'])->toHaveCount(3);
    expect(array_column($result['details'], 'time_slot'))->toBe(['07:00', '09:00', '14:00']);
});

it('throws ValidationException when a required field is empty', function () {
    expect(fn () => $this->service->create(
        clarificationFormPayload(['production_line_id' => $this->clarificationStation->production_line_id, 'clarification_id' => '']),
        $this->creator
    ))->toThrow(ValidationException::class);
});

it('throws ValidationException when details is empty', function () {
    expect(fn () => $this->service->create(
        clarificationFormPayload(['production_line_id' => $this->clarificationStation->production_line_id, 'details' => []]),
        $this->creator
    ))->toThrow(ValidationException::class);
});

it('throws ValidationException when a detail row has a time_slot not in the 24 canonical slots', function () {
    expect(fn () => $this->service->create(
        clarificationFormPayload([
            'production_line_id' => $this->clarificationStation->production_line_id,
            'details' => [['time_slot' => '07:30', 'clarification_tank_temp_c' => 1]],
        ]),
        $this->creator
    ))->toThrow(ValidationException::class);
});

it('throws ValidationException when time_slot is not strictly ascending across detail rows', function () {
    expect(fn () => $this->service->create(
        clarificationFormPayload([
            'production_line_id' => $this->clarificationStation->production_line_id,
            'details' => [
                ['time_slot' => '09:00', 'clarification_tank_temp_c' => 1],
                ['time_slot' => '07:00', 'clarification_tank_temp_c' => 2],
            ],
        ]),
        $this->creator
    ))->toThrow(ValidationException::class);
});

it('throws ValidationException when two detail rows share the same time_slot', function () {
    expect(fn () => $this->service->create(
        clarificationFormPayload([
            'production_line_id' => $this->clarificationStation->production_line_id,
            'details' => [
                ['time_slot' => '07:00', 'clarification_tank_temp_c' => 1],
                ['time_slot' => '07:00', 'clarification_tank_temp_c' => 2],
            ],
        ]),
        $this->creator
    ))->toThrow(ValidationException::class);
});

it('throws ValidationException when zero rows have any column filled', function () {
    expect(fn () => $this->service->create(
        clarificationFormPayload([
            'production_line_id' => $this->clarificationStation->production_line_id,
            'details' => [['time_slot' => '07:00']],
        ]),
        $this->creator
    ))->toThrow(ValidationException::class);
});

it('treats a row with only findings filled as satisfying the minimum-one-row rule', function () {
    $result = $this->service->create(
        clarificationFormPayload([
            'production_line_id' => $this->clarificationStation->production_line_id,
            'details' => [['time_slot' => '10:00', 'findings' => 'Perlu pemeriksaan lanjut']],
        ]),
        $this->creator
    );

    expect($result['status'])->toBe('saved');
    expect($result['details'][0]['findings'])->toBe('Perlu pemeriksaan lanjut');
});

it('throws NoActiveClarificationStationException when production_line_id has no active clarification station', function () {
    $otherProductionLine = ProductionLine::factory()->create();

    expect(fn () => $this->service->create(
        clarificationFormPayload(['production_line_id' => $otherProductionLine->id]),
        $this->creator
    ))->toThrow(NoActiveClarificationStationException::class);
});

it('sets checked_by to requester id when checked=true and requester role=supervisor', function () {
    $supervisor = User::factory()->role(UserRole::Supervisor)->create();

    $result = $this->service->create(
        clarificationFormPayload(['production_line_id' => $this->clarificationStation->production_line_id, 'checked' => true]),
        $supervisor
    );

    expect($result['checked_by_name'])->toBe($supervisor->name);
});

it('ignores checked=true when requester role is not supervisor', function () {
    $millManagement = User::factory()->role(UserRole::MillManagement)->create();

    $result = $this->service->create(
        clarificationFormPayload(['production_line_id' => $this->clarificationStation->production_line_id, 'checked' => true]),
        $millManagement
    );

    expect($result['checked_by_name'])->toBeNull();
});

it('sets acknowledged_by to requester id when acknowledged=true and requester role=mill_management', function () {
    $millManagement = User::factory()->role(UserRole::MillManagement)->create();

    $result = $this->service->create(
        clarificationFormPayload(['production_line_id' => $this->clarificationStation->production_line_id, 'acknowledged' => true]),
        $millManagement
    );

    expect($result['acknowledged_by_name'])->toBe($millManagement->name);
});

it('updates record and upserts details: inserts new row, updates existing row, deletes removed row', function () {
    $record = ClarificationRecord::factory()->forStation($this->clarificationStation)->create();
    $keptDetail = ClarificationDetail::factory()->forRecord($record)->timeSlot('07:00')->create(['clarification_tank_temp_c' => 60]);
    ClarificationDetail::factory()->forRecord($record)->timeSlot('12:00')->filled()->create();

    $result = $this->service->update(
        $record->id,
        clarificationFormPayload([
            'clarification_id' => 'CLR-EDITED',
            'details' => [
                ['id' => $keptDetail->id, 'time_slot' => '07:00', 'clarification_tank_temp_c' => 88.8],
                ['time_slot' => '15:00', 'clarification_tank_temp_c' => 5],
            ],
        ]),
        $this->creator
    );

    expect($result['clarification_id'])->toBe('CLR-EDITED');
    expect($result['details'])->toHaveCount(2);
    expect(ClarificationDetail::where('clarification_record_id', $record->id)->count())->toBe(2);
    expect(ClarificationDetail::find($keptDetail->id)->clarification_tank_temp_c)->toBe(88.8);
    expect(ClarificationDetail::where('time_slot', '12:00')->exists())->toBeFalse();
});

it('updates record without accepting a production_line_id change', function () {
    $record = ClarificationRecord::factory()->forStation($this->clarificationStation)->create();
    $existingDetail = ClarificationDetail::factory()->forRecord($record)->timeSlot('07:00')->filled()->create();
    $otherProductionLine = ProductionLine::factory()->create();

    $result = $this->service->update(
        $record->id,
        clarificationFormPayload([
            'production_line_id' => $otherProductionLine->id,
            'clarification_id' => 'CLR-EDITED',
            'details' => [
                ['id' => $existingDetail->id, 'time_slot' => $existingDetail->time_slot, 'clarification_tank_temp_c' => $existingDetail->clarification_tank_temp_c],
            ],
        ]),
        $this->creator
    );

    expect($result['station_id'])->toBe($this->clarificationStation->id);
    expect($result['clarification_id'])->toBe('CLR-EDITED');
});

it('throws ModelNotFoundException when updating a non-existent id', function () {
    expect(fn () => $this->service->update(
        (string) Str::uuid(),
        clarificationFormPayload(),
        $this->creator
    ))->toThrow(ModelNotFoundException::class);
});
