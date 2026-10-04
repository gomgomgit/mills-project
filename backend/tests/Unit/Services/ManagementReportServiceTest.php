<?php

/**
 * ManagementReportServiceTest — screen-026--laporan-manajemen /
 * usecase-026--laporan-manajemen.
 *
 * Unit tests for App\Services\ManagementReportService::getBreakdown()/
 * export(), covering the unit_test_cases derived from this screen's
 * business_logic (steps 1-6). Mirrors DashboardServiceTest's pragmatic
 * RefreshDatabase + factory approach.
 */

use App\Exceptions\InvalidDateRangeException;
use App\Models\BusinessUnit;
use App\Models\CagesTippedTime;
use App\Models\CagesTrackRecord;
use App\Models\Station;
use App\Models\WeighbridgeRecord;
use App\Services\ManagementReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new ManagementReportService;
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
});

it('returns 422 INVALID_DATE_RANGE when date_from > date_to', function () {
    expect(fn () => $this->service->getBreakdown($this->businessUnit->id, '2026-03-10', '2026-03-01'))
        ->toThrow(InvalidDateRangeException::class);
});

it('defaults date_from to start of current month and date_to to today when not provided', function () {
    $breakdown = $this->service->getBreakdown($this->businessUnit->id, null, null);

    $expectedFrom = Carbon::today()->startOfMonth()->toDateString();
    $expectedTo = Carbon::today()->toDateString();

    expect($breakdown['rows'][0]['date'])->toBe($expectedFrom);
    expect($breakdown['rows'][count($breakdown['rows']) - 1]['date'])->toBe($expectedTo);
});

it('returns one row per date in the range with correct aggregates', function () {
    $day1 = '2026-02-01';
    $day2 = '2026-02-02';
    $day3 = '2026-02-03';

    WeighbridgeRecord::factory()->forStation($this->station)->arrivedAt($day1.' 08:00:00')->ofType('receive')
        ->create(['gross_weight' => 6000, 'tare_weight' => 1000]);
    WeighbridgeRecord::factory()->forStation($this->station)->arrivedAt($day3.' 08:00:00')->ofType('receive')
        ->create(['gross_weight' => 4000, 'tare_weight' => 1000]);

    $breakdown = $this->service->getBreakdown($this->businessUnit->id, $day1, $day3);

    expect($breakdown['rows'])->toHaveCount(3);
    expect($breakdown['rows'][0]['date'])->toBe($day1);
    expect($breakdown['rows'][1]['date'])->toBe($day2);
    expect($breakdown['rows'][2]['date'])->toBe($day3);
    expect($breakdown['rows'][0]['weighbridge']['receive'])->toBe(['count' => 1, 'total_net_weight' => 5000.0]);
    expect($breakdown['rows'][2]['weighbridge']['receive'])->toBe(['count' => 1, 'total_net_weight' => 3000.0]);
});

it('includes a zero-value row for a date with no matching records', function () {
    $day1 = '2026-02-01';
    $day2 = '2026-02-02';

    WeighbridgeRecord::factory()->forStation($this->station)->arrivedAt($day1.' 08:00:00')->ofType('receive')->create();

    $breakdown = $this->service->getBreakdown($this->businessUnit->id, $day1, $day2);

    expect($breakdown['rows'][1]['date'])->toBe($day2);
    expect($breakdown['rows'][1]['weighbridge'])->toBe(ManagementReportService::emptyWeighbridge());
    expect($breakdown['rows'][1]['grading'])->toBe(['count' => 0, 'total_netto' => 0.0, 'total_quantity' => 0.0]);
    expect($breakdown['rows'][1]['cages_track'])->toBe(['count' => 0, 'total_cages_tipped' => 0]);
});

it('computes total as the sum of all daily rows', function () {
    $day1 = '2026-02-01';
    $day2 = '2026-02-02';

    WeighbridgeRecord::factory()->forStation($this->station)->arrivedAt($day1.' 08:00:00')->ofType('receive')
        ->create(['gross_weight' => 6000, 'tare_weight' => 1000]);
    WeighbridgeRecord::factory()->forStation($this->station)->arrivedAt($day2.' 08:00:00')->ofType('receive')
        ->create(['gross_weight' => 4000, 'tare_weight' => 1000]);

    $breakdown = $this->service->getBreakdown($this->businessUnit->id, $day1, $day2);

    expect($breakdown['total']['weighbridge']['receive'])->toBe(['count' => 2, 'total_net_weight' => 8000.0]);
});

it("scopes aggregation to the acting user's own business_unit_id only", function () {
    $day = '2026-02-01';
    $otherBusinessUnit = BusinessUnit::factory()->create();
    $otherStation = Station::factory()->forBusinessUnit($otherBusinessUnit)->create();

    WeighbridgeRecord::factory()->forStation($this->station)->arrivedAt($day.' 08:00:00')->ofType('receive')->create();
    WeighbridgeRecord::factory()->forStation($otherStation)->arrivedAt($day.' 08:00:00')->ofType('receive')->create();

    $breakdown = $this->service->getBreakdown($this->businessUnit->id, $day, $day);

    expect($breakdown['rows'][0]['weighbridge']['receive']['count'])->toBe(1);
});

it('aggregates cages_track total_cages_tipped across cages-tipped-time rows per day, not cages_out/cages_tipped', function () {
    $day = '2026-02-01';

    $record = CagesTrackRecord::factory()->forStation($this->station)->onDate($day)
        ->create(['cages_out' => 99, 'cages_tipped' => 99]);
    CagesTippedTime::factory()->forRecord($record)->create(['total_cages' => 4]);
    CagesTippedTime::factory()->forRecord($record)->create(['total_cages' => 2]);

    $breakdown = $this->service->getBreakdown($this->businessUnit->id, $day, $day);

    expect($breakdown['rows'][0]['cages_track'])->toBe(['count' => 1, 'total_cages_tipped' => 6]);
});

it('export() returns 422 INVALID_DATE_RANGE when date_from > date_to', function () {
    expect(fn () => $this->service->export($this->businessUnit->id, '2026-03-10', '2026-03-01', 'csv'))
        ->toThrow(InvalidDateRangeException::class);
});

it('export() generates a CSV with one row per date plus a Total row', function () {
    $day1 = '2026-02-01';
    $day2 = '2026-02-02';

    WeighbridgeRecord::factory()->forStation($this->station)->arrivedAt($day1.' 08:00:00')->ofType('receive')
        ->create(['gross_weight' => 6000, 'tare_weight' => 1000]);

    $response = $this->service->export($this->businessUnit->id, $day1, $day2, 'csv');

    ob_start();
    $response->sendContent();
    $body = ob_get_clean();

    $lines = array_filter(explode("\n", trim($body)));
    expect($lines)->toHaveCount(4); // header + 2 daily rows + total
    expect($lines[0])->toContain('Tanggal');
    expect($lines[3])->toContain('Total');
});

// Temuan audit 2026-10-04 #2a: arus masuk dan arus keluar Weighbridge
// DIPISAH, tidak pernah dijumlahkan menjadi satu Count/Net Weight.
it('memisahkan Weighbridge masuk dan keluar — tidak pernah dijumlahkan', function () {
    $day = '2026-02-01';

    WeighbridgeRecord::factory()->forStation($this->station)->arrivedAt($day.' 08:00:00')->ofType('receive')
        ->create(['gross_weight' => 6000, 'tare_weight' => 1000]);
    WeighbridgeRecord::factory()->forStation($this->station)->arrivedAt($day.' 09:00:00')->ofType('dispatch')
        ->create(['gross_weight' => 30000, 'tare_weight' => 10000]);

    $breakdown = $this->service->getBreakdown($this->businessUnit->id, $day, $day);

    expect($breakdown['rows'][0]['weighbridge'])->toBe([
        'receive' => ['count' => 1, 'total_net_weight' => 5000.0],
        'dispatch' => ['count' => 1, 'total_net_weight' => 20000.0],
    ]);
    expect($breakdown['total']['weighbridge']['dispatch'])->toBe(['count' => 1, 'total_net_weight' => 20000.0]);

    $response = $this->service->export($this->businessUnit->id, $day, $day, 'csv');
    ob_start();
    $response->sendContent();
    $header = str_getcsv(strtok((string) ob_get_clean(), "\n"), ',', '"', '\\');

    expect($header)->toBe(array_merge(['Production Line'], ManagementReportService::EXPORT_HEADER));
    expect($header)->not->toContain('WB Count');
});

// Temuan audit 2026-10-04 #2b: angka dibatasi production line yang dipilih
// (kolom line milik record), dan line mill lain ditolak.
it('menyaring per production line yang dipilih dan menolak line mill lain', function () {
    $day = '2026-02-01';
    $otherLineStation = Station::factory()->forBusinessUnit($this->businessUnit)->create();

    WeighbridgeRecord::factory()->forStation($this->station)->arrivedAt($day.' 08:00:00')->ofType('receive')->create();
    WeighbridgeRecord::factory()->forStation($otherLineStation)->arrivedAt($day.' 08:00:00')->ofType('receive')->count(2)->create();

    expect($otherLineStation->production_line_id)->not->toBe($this->station->production_line_id);

    $lineA = $this->service->getBreakdown($this->businessUnit->id, $day, $day, (string) $this->station->production_line_id);
    $lineB = $this->service->getBreakdown($this->businessUnit->id, $day, $day, (string) $otherLineStation->production_line_id);

    expect($lineA['total']['weighbridge']['receive']['count'])->toBe(1);
    expect($lineB['total']['weighbridge']['receive']['count'])->toBe(2);

    $foreignMill = BusinessUnit::factory()->create();
    $foreignStation = Station::factory()->forBusinessUnit($foreignMill)->create();

    expect($this->service->resolveProductionLineOrNull($this->businessUnit->id, (string) $foreignStation->production_line_id))->toBeNull();
    expect($this->service->resolveProductionLineOrNull($this->businessUnit->id, 'bukan-uuid'))->toBeNull();
    expect(fn () => $this->service->resolveProductionLine($this->businessUnit->id, null))
        ->toThrow(ValidationException::class);
});
