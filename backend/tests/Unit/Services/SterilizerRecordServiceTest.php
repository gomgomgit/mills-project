<?php

/**
 * SterilizerRecordServiceTest — screen-124--data-browser-sterilizer-web
 * / screen-125--detail-sterilizer-web / screen-126--form-sterilizer-web.
 *
 * Mirrors CpoDispatchRecordServiceTest.php's structure — TestCase +
 * RefreshDatabase (sqlite in-memory), fixture data via model factories.
 * Like CPO Dispatch, this station has no grid/N-column concept and no
 * per-row time-slot ordering constraint — detail rows are a free event log.
 *
 * This is the FINAL station of this project (Sterilizer promoted 2026-09-01,
 * the 18th and last of the 18 canonical stations).
 */

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Exceptions\InvalidDateRangeException;
use App\Exceptions\NoActiveSterilizerStationException;
use App\Models\BusinessUnit;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\SterilizerDetail;
use App\Models\SterilizerRecord;
use App\Models\User;
use App\Services\SterilizerRecordService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function sterilizerFormPayload(array $overrides = []): array
{
    return array_merge([
        'sterilizer_id' => 'STR-'.Str::random(6),
        'date' => '2026-08-31',
        'note' => null,
        'details' => [],
    ], $overrides);
}

beforeEach(function () {
    $this->service = new SterilizerRecordService();
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->sterilizer()->create();
    $this->creator = User::factory()->create();
});

it('throws InvalidDateRangeException when date_from is after date_to on listRecords()', function () {
    expect(fn () => $this->service->listRecords([
        'date_from' => '2026-02-10',
        'date_to' => '2026-02-01',
    ], 1, 20))->toThrow(InvalidDateRangeException::class);
});

it('returns an empty data list and meta.total = 0 when no records match the filter', function () {
    SterilizerRecord::factory()->forStation($this->station)->onDate('2026-01-01')->create();

    $result = $this->service->listRecords([
        'date_from' => '2020-01-01',
        'date_to' => '2020-01-02',
    ], 1, 20);

    expect($result['data'])->toBe([]);
    expect($result['meta']['total'])->toBe(0);
});

it('returns a paginated, filtered list with the shared pagination meta shape', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create();
    $otherStation = Station::factory()->forBusinessUnit($otherBusinessUnit)->sterilizer()->create();

    SterilizerRecord::factory()->forStation($this->station)->onDate('2026-02-05')->count(3)->create();
    SterilizerRecord::factory()->forStation($this->station)->onDate('2026-03-01')->create();
    SterilizerRecord::factory()->forStation($otherStation)->onDate('2026-02-05')->create();

    $result = $this->service->listRecords([
        'date_from' => '2026-02-01',
        'date_to' => '2026-02-10',
        'business_unit_id' => $this->businessUnit->id,
    ], 1, 2);

    expect($result['meta'])->toBe(['page' => 1, 'per_page' => 2, 'total' => 3, 'total_pages' => 2]);
    expect($result['data'])->toHaveCount(2);
    expect(array_keys($result['data'][0]))->toBe([
        'id', 'sterilizer_id', 'date', 'cycle_count', 'status',
    ]);
});

it('computes cycle_count as the number of related SterilizerDetail rows', function () {
    $withTwo = SterilizerRecord::factory()->forStation($this->station)->onDate('2026-02-05')->create();
    SterilizerDetail::factory()->forRecord($withTwo)->count(2)->create();

    $withNone = SterilizerRecord::factory()->forStation($this->station)->onDate('2026-02-05')->create();

    $result = $this->service->listRecords(['date_from' => '2026-02-01', 'date_to' => '2026-02-10'], 1, 20);
    $rows = collect($result['data'])->keyBy('id');

    expect($rows[$withTwo->id]['cycle_count'])->toBe(2);
    expect($rows[$withNone->id]['cycle_count'])->toBe(0);
});

it('throws InvalidDateRangeException when date_from is after date_to on export()', function () {
    expect(fn () => $this->service->export([
        'date_from' => '2026-02-10',
        'date_to' => '2026-02-01',
    ], 'csv'))->toThrow(InvalidDateRangeException::class);
});

it('throws ExportFailedException when the filtered dataset exceeds the export row limit', function () {
    $limit = SterilizerRecordService::EXPORT_ROW_LIMIT;
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
                'sterilizer_id' => 'STR-BULK-'.($inserted + $i),
                'date' => $now->toDateString(),
                'note' => null,
                'checked_by' => null,
                'acknowledged_by' => null,
                'status' => RecordStatus::Saved->value,
                'created_by' => $this->creator->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('sterilizer_records')->insert($rows);
        $inserted += $batch;
    }

    expect(SterilizerRecord::count())->toBe($total);

    expect(fn () => $this->service->export([], 'csv'))->toThrow(ExportFailedException::class);
});

it('returns a StreamedResponse with the correct content-type for csv and excel formats', function (string $format, string $expectedContentType) {
    $record = SterilizerRecord::factory()->forStation($this->station)->onDate('2026-02-05')->create();
    SterilizerDetail::factory()->forRecord($record)->create();

    SterilizerRecord::factory()->forStation($this->station)->onDate('2026-02-05')->create();

    $response = $this->service->export(['date_from' => '2026-02-01', 'date_to' => '2026-02-10'], $format);

    expect($response)->toBeInstanceOf(StreamedResponse::class);
    expect($response->headers->get('Content-Type'))->toBe($expectedContentType);

    ob_start();
    $response->sendContent();
    $body = ob_get_clean();

    expect($body)->toContain('Sterilizer ID');
    expect(substr_count($body, "\n"))->toBeGreaterThanOrEqual(2);
})->with([
    'csv' => ['csv', 'text/csv'],
    'excel' => ['excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
]);

it('throws ModelNotFoundException when the id does not exist', function () {
    $this->service->getDetail((string) Str::uuid());
})->throws(ModelNotFoundException::class);

it('returns the full record with resolved station_name when id exists', function () {
    $record = SterilizerRecord::factory()->forStation($this->station)->create();

    $result = $this->service->getDetail($record->id);

    expect($result['id'])->toBe($record->id);
    expect($result['station_name'])->toBe($this->station->name);
});

it('returns null checked_by_name and acknowledged_by_name when not set', function () {
    $record = SterilizerRecord::factory()->forStation($this->station)->create(['checked_by' => null, 'acknowledged_by' => null]);

    $result = $this->service->getDetail($record->id);

    expect($result['checked_by_name'])->toBeNull();
    expect($result['acknowledged_by_name'])->toBeNull();
});

it('resolves created_by_name, checked_by_name, acknowledged_by_name to user names when present', function () {
    $checker = User::factory()->create(['name' => 'Budi Checker']);
    $acknowledger = User::factory()->create(['name' => 'Siti Manager']);
    $record = SterilizerRecord::factory()->forStation($this->station)->create([
        'created_by' => $this->creator->id,
        'checked_by' => $checker->id,
        'acknowledged_by' => $acknowledger->id,
    ]);

    $result = $this->service->getDetail($record->id);

    expect($result['created_by_name'])->toBe($this->creator->name);
    expect($result['checked_by_name'])->toBe('Budi Checker');
    expect($result['acknowledged_by_name'])->toBe('Siti Manager');
});

// screen-126--form-sterilizer-web: create()/update() tests below.

it('creates record with resolved station_id and inserted details when valid', function () {
    $result = $this->service->create(
        sterilizerFormPayload([
            'production_line_id' => $this->station->production_line_id,
            'details' => [['close_door_time' => '07:00', 'open_door_time' => '08:10']],
        ]),
        $this->creator
    );

    expect($result['station_id'])->toBe($this->station->id);
    expect($result['status'])->toBe('saved');
    expect($result['details'])->toHaveCount(1);
});

// duration_minutes computation (task requirement — computed server-side,
// NEVER accepted from client input even if sent).

it('computes duration_minutes as open_door_time minus close_door_time', function () {
    $result = $this->service->create(
        sterilizerFormPayload([
            'production_line_id' => $this->station->production_line_id,
            'details' => [['close_door_time' => '07:00', 'open_door_time' => '08:10']],
        ]),
        $this->creator
    );

    expect($result['details'][0]['duration_minutes'])->toBe(70);
});

it('ignores a client-supplied duration_minutes and always recomputes it server-side', function () {
    $result = $this->service->create(
        sterilizerFormPayload([
            'production_line_id' => $this->station->production_line_id,
            'details' => [[
                'close_door_time' => '07:00',
                'open_door_time' => '08:10',
                'duration_minutes' => 999999,
            ]],
        ]),
        $this->creator
    );

    expect($result['details'][0]['duration_minutes'])->toBe(70);
    expect(SterilizerDetail::first()->duration_minutes)->toBe(70);
});

it('returns null duration_minutes when open_door_time is missing', function () {
    $result = $this->service->create(
        sterilizerFormPayload([
            'production_line_id' => $this->station->production_line_id,
            'details' => [['close_door_time' => '07:00', 'open_door_time' => null]],
        ]),
        $this->creator
    );

    expect($result['details'][0]['duration_minutes'])->toBeNull();
});

it('wraps duration_minutes across midnight when open_door_time is earlier than close_door_time', function () {
    $result = $this->service->create(
        sterilizerFormPayload([
            'production_line_id' => $this->station->production_line_id,
            'details' => [['close_door_time' => '23:30', 'open_door_time' => '00:15']],
        ]),
        $this->creator
    );

    expect($result['details'][0]['duration_minutes'])->toBe(45);
});

it('persists checked_by_spv as a plain boolean, not a user reference', function () {
    $result = $this->service->create(
        sterilizerFormPayload([
            'production_line_id' => $this->station->production_line_id,
            'details' => [['close_door_time' => '07:00', 'open_door_time' => '08:00', 'checked_by_spv' => true]],
        ]),
        $this->creator
    );

    expect($result['details'][0]['checked_by_spv'])->toBeTrue();
    expect(SterilizerDetail::first()->checked_by_spv)->toBeTrue();
});

it('throws ValidationException when a required field is empty', function () {
    expect(fn () => $this->service->create(
        sterilizerFormPayload([
            'production_line_id' => $this->station->production_line_id,
            'sterilizer_id' => '',
            'details' => [['close_door_time' => '07:00']],
        ]),
        $this->creator
    ))->toThrow(ValidationException::class);
});

it('throws ValidationException when details array is empty', function () {
    expect(fn () => $this->service->create(
        sterilizerFormPayload(['production_line_id' => $this->station->production_line_id, 'details' => []]),
        $this->creator
    ))->toThrow(ValidationException::class);
});

it('throws ValidationException when no detail row has a close_door_time', function () {
    expect(fn () => $this->service->create(
        sterilizerFormPayload([
            'production_line_id' => $this->station->production_line_id,
            'details' => [['close_door_time' => null, 'sterilizer_no' => '1']],
        ]),
        $this->creator
    ))->toThrow(ValidationException::class);
});

it('throws NoActiveSterilizerStationException when production_line_id has no active station', function () {
    $otherProductionLine = ProductionLine::factory()->create();

    expect(fn () => $this->service->create(
        sterilizerFormPayload([
            'production_line_id' => $otherProductionLine->id,
            'details' => [['close_door_time' => '07:00']],
        ]),
        $this->creator
    ))->toThrow(NoActiveSterilizerStationException::class);
});

it('sets checked_by to requester id when checked=true and requester role=supervisor', function () {
    $supervisor = User::factory()->role(UserRole::Supervisor)->create();

    $result = $this->service->create(
        sterilizerFormPayload([
            'production_line_id' => $this->station->production_line_id,
            'checked' => true,
            'details' => [['close_door_time' => '07:00']],
        ]),
        $supervisor
    );

    expect($result['checked_by_name'])->toBe($supervisor->name);
});

it('ignores checked=true when requester role is not supervisor', function () {
    $millManagement = User::factory()->role(UserRole::MillManagement)->create();

    $result = $this->service->create(
        sterilizerFormPayload([
            'production_line_id' => $this->station->production_line_id,
            'checked' => true,
            'details' => [['close_door_time' => '07:00']],
        ]),
        $millManagement
    );

    expect($result['checked_by_name'])->toBeNull();
});

it('sets acknowledged_by to requester id when acknowledged=true and requester role=mill_management', function () {
    $millManagement = User::factory()->role(UserRole::MillManagement)->create();

    $result = $this->service->create(
        sterilizerFormPayload([
            'production_line_id' => $this->station->production_line_id,
            'acknowledged' => true,
            'details' => [['close_door_time' => '07:00']],
        ]),
        $millManagement
    );

    expect($result['acknowledged_by_name'])->toBe($millManagement->name);
});

it('updates record and upserts details: inserts new row, updates existing row, deletes removed row', function () {
    $record = SterilizerRecord::factory()->forStation($this->station)->create();
    $keptDetail = SterilizerDetail::factory()->forRecord($record)->create(['close_door_time' => '07:00', 'open_door_time' => '08:00']);
    SterilizerDetail::factory()->forRecord($record)->create(['close_door_time' => '09:00']);

    $result = $this->service->update(
        $record->id,
        sterilizerFormPayload([
            'details' => [
                ['id' => $keptDetail->id, 'close_door_time' => '07:00', 'open_door_time' => '09:30'],
                ['close_door_time' => '10:00', 'open_door_time' => '11:00'],
            ],
        ]),
        $this->creator
    );

    expect($result['details'])->toHaveCount(2);
    expect(SterilizerDetail::where('sterilizer_record_id', $record->id)->count())->toBe(2);
    expect(SterilizerDetail::find($keptDetail->id)->duration_minutes)->toBe(150);
    expect(SterilizerDetail::where('close_door_time', '09:00')->exists())->toBeFalse();
});

it('updates record without accepting a production_line_id change', function () {
    $otherProductionLine = ProductionLine::factory()->create();
    $record = SterilizerRecord::factory()->forStation($this->station)->create();
    SterilizerDetail::factory()->forRecord($record)->create();

    $result = $this->service->update(
        $record->id,
        sterilizerFormPayload([
            'production_line_id' => $otherProductionLine->id,
            'sterilizer_id' => 'STR-EDITED',
            'details' => [['close_door_time' => '07:00']],
        ]),
        $this->creator
    );

    expect($result['station_id'])->toBe($this->station->id);
    expect($result['sterilizer_id'])->toBe('STR-EDITED');
});

it('throws ModelNotFoundException when updating a non-existent id', function () {
    expect(fn () => $this->service->update(
        (string) Str::uuid(),
        sterilizerFormPayload(['details' => [['close_door_time' => '07:00']]]),
        $this->creator
    ))->toThrow(ModelNotFoundException::class);
});
