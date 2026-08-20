<?php

/**
 * DashboardServiceTest — screen-025--dashboard-web / usecase-025--dashboard-web.
 *
 * Unit tests for App\Services\DashboardService::getSummary(), covering the
 * unit_test_cases derived from this screen's business_logic (steps 1-6).
 * Calls the service directly (no HTTP layer), mirroring
 * WeighbridgeRecordServiceTest's pragmatic RefreshDatabase + factory
 * approach.
 */

use App\Exceptions\InvalidDateRangeException;
use App\Models\BusinessUnit;
use App\Models\CagesTippedTime;
use App\Models\CagesTrackRecord;
use App\Models\GradingRecord;
use App\Models\Station;
use App\Models\WeighbridgeRecord;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new DashboardService();
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
});

it('returns 422 INVALID_DATE_RANGE when date_from > date_to', function () {
    expect(fn () => $this->service->getSummary([
        'date_from' => '2026-02-10',
        'date_to' => '2026-02-01',
    ]))->toThrow(InvalidDateRangeException::class);
});

it('defaults date_from/date_to to today when not provided', function () {
    $today = Carbon::today()->toDateString();

    WeighbridgeRecord::factory()->forStation($this->station)->arrivedAt($today.' 08:00:00')->create();
    WeighbridgeRecord::factory()->forStation($this->station)->arrivedAt('2020-01-01 08:00:00')->create();

    $summary = $this->service->getSummary([]);

    expect($summary['weighbridge']['count'])->toBe(1);
});

it('returns success result with correct counts and sums when all conditions pass', function () {
    $today = Carbon::today()->toDateString();

    WeighbridgeRecord::factory()->forStation($this->station)->arrivedAt($today.' 08:00:00')
        ->create(['gross_weight' => 6000, 'tare_weight' => 1000]);
    WeighbridgeRecord::factory()->forStation($this->station)->arrivedAt($today.' 09:00:00')
        ->create(['gross_weight' => 4000, 'tare_weight' => 1000]);

    GradingRecord::factory()->forStation($this->station)->onDate($today)
        ->create(['netto' => 4000, 'quantity' => 100]);

    $cagesTrack = CagesTrackRecord::factory()->forStation($this->station)->onDate($today)->create();
    CagesTippedTime::factory()->forRecord($cagesTrack)->create(['total_cages' => 5]);
    CagesTippedTime::factory()->forRecord($cagesTrack)->create(['total_cages' => 3]);

    $summary = $this->service->getSummary(['date_from' => $today, 'date_to' => $today]);

    expect($summary['weighbridge'])->toBe(['count' => 2, 'total_net_weight' => 8000.0]);
    expect($summary['grading'])->toBe(['count' => 1, 'total_netto' => 4000.0, 'total_quantity' => 100.0]);
    expect($summary['cages_track'])->toBe(['count' => 1, 'total_cages_tipped' => 8]);
});

it('filters by business_unit_id when provided', function () {
    $today = Carbon::today()->toDateString();
    $otherBusinessUnit = BusinessUnit::factory()->create();
    $otherStation = Station::factory()->forBusinessUnit($otherBusinessUnit)->create();

    WeighbridgeRecord::factory()->forStation($this->station)->arrivedAt($today.' 08:00:00')->create();
    WeighbridgeRecord::factory()->forStation($otherStation)->arrivedAt($today.' 08:00:00')->create();

    $summary = $this->service->getSummary([
        'date_from' => $today,
        'date_to' => $today,
        'business_unit_id' => $this->businessUnit->id,
    ]);

    expect($summary['weighbridge']['count'])->toBe(1);
});

it('returns zero counts/sums when no matching records exist', function () {
    $summary = $this->service->getSummary(['date_from' => '2020-01-01', 'date_to' => '2020-01-02']);

    expect($summary['weighbridge'])->toBe(['count' => 0, 'total_net_weight' => 0.0]);
    expect($summary['grading'])->toBe(['count' => 0, 'total_netto' => 0.0, 'total_quantity' => 0.0]);
    expect($summary['cages_track'])->toBe(['count' => 0, 'total_cages_tipped' => 0]);
});

it('aggregates cages_track total_cages_tipped across cages-tipped-time rows correctly', function () {
    $today = Carbon::today()->toDateString();

    $record1 = CagesTrackRecord::factory()->forStation($this->station)->onDate($today)->create();
    CagesTippedTime::factory()->forRecord($record1)->create(['total_cages' => 4]);
    CagesTippedTime::factory()->forRecord($record1)->create(['total_cages' => 2]);

    $record2 = CagesTrackRecord::factory()->forStation($this->station)->onDate($today)->create();
    CagesTippedTime::factory()->forRecord($record2)->create(['total_cages' => 6]);

    $summary = $this->service->getSummary(['date_from' => $today, 'date_to' => $today]);

    expect($summary['cages_track'])->toBe(['count' => 2, 'total_cages_tipped' => 12]);
});
