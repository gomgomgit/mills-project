<?php

use App\Enums\StationType as StationTypeEnum;
use App\Models\Station;
use App\Models\StationType;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Guards the two-sided arrangement introduced on 2026-09-22: the set of valid
 * station types is DATA (the station_types table, FK target for
 * stations.type and periods.station_type), while individual types are still
 * CODE (App\Enums\StationType, used where logic names one specific type).
 *
 * Both halves have to agree. Adding a case to the enum without seeding the row
 * — or the reverse — used to be invisible until a station failed to save in
 * production; these tests make it a red suite instead.
 */
it('has a station_types row for every StationType enum case', function () {
    $enumCodes = array_map(fn (StationTypeEnum $c) => $c->value, StationTypeEnum::cases());
    $tableCodes = StationType::pluck('code')->all();

    $missing = array_values(array_diff($enumCodes, $tableCodes));

    expect($missing)->toBe([], 'Enum cases with no station_types row: '.implode(', ', $missing));
});

it('has a StationType enum case for every station_types row', function () {
    $enumCodes = array_map(fn (StationTypeEnum $c) => $c->value, StationTypeEnum::cases());
    $tableCodes = StationType::pluck('code')->all();

    $extra = array_values(array_diff($tableCodes, $enumCodes));

    // A row without an enum case is allowed in principle — that is the whole
    // point of making types data — but while every type still has code naming
    // it, a silent divergence is far more likely to be a forgotten enum case.
    // If a genuinely code-less type is ever added, delete this test and say so.
    expect($extra)->toBe([], 'station_types rows with no enum case: '.implode(', ', $extra));
});

it('seeds the 18 canonical types plus the historical "other"', function () {
    expect(StationType::count())->toBe(19);
    expect(StationType::where('code', 'other')->exists())->toBeTrue();
    expect(StationType::where('code', '<>', 'other')->count())->toBe(18);
});

it('orders the canonical types by the physical process, not alphabetically', function () {
    $ordered = StationType::orderBy('sort_order')->pluck('code')->take(4)->all();

    expect($ordered)->toBe(['weighbridge', 'grading', 'cages-track', 'sterilizer']);
});

it('rejects a station whose type is not in the master table', function () {
    $station = Station::factory()->make()->getAttributes();
    $station['type'] = 'not-a-real-station-type';

    expect(fn () => DB::table('stations')->insert($station))
        ->toThrow(QueryException::class);
});

it('refuses to delete a station type that is still in use', function () {
    $station = Station::factory()->create(['type' => 'weighbridge']);

    expect(fn () => StationType::where('code', 'weighbridge')->delete())
        ->toThrow(QueryException::class);

    expect(Station::whereKey($station->id)->exists())->toBeTrue();
});
