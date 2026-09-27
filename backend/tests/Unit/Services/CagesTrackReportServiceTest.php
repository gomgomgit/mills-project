<?php

/**
 * CagesTrackReportServiceTest — screen-130--laporan-cages-track-web /
 * usecase-130--laporan-cages-track-web (Laporan Periode Cages & Tracks).
 *
 * One test per unit_test_case in the screen's tech spec (52 cases, in the
 * spec's own order), against App\Services\CagesTrackReportService. Mirrors
 * tests/Unit/Services/SterilizerReportServiceTest.php (screen-129, this
 * screen's twin) in structure and conventions.
 *
 * `uses(TestCase::class, RefreshDatabase::class)` is MANDATORY here:
 * tests/Pest.php binds Tests\TestCase only to Feature/, so without this
 * line the Unit suite has no application container — auth()->user(),
 * Eloquent and the faker formats the factories rely on would all blow up.
 *
 * ------------------------------------------------------------------
 * THE FIVE TRAPS THIS FILE EXISTS TO PIN DOWN
 * ------------------------------------------------------------------
 *  1. GRAIN. `cages_out` lives on cages_track_records (HEADER, one per
 *     record); `total_cages` lives on cages_tipped_times (HOURLY DETAIL).
 *     Summing cages_out over a JOIN to the details multiplies it by the
 *     number of hour rows — 50 becomes 400 for a record with 8 of them.
 *  2. cages_track_records.cages_tipped (the header summary field) is NEVER
 *     a source of any tipping figure. Every case below that seeds it sets
 *     it to a MISLEADING value on purpose, so a regression that reads it
 *     shows up as a wrong number rather than a coincidence.
 *  3. DURATION comes from the difference of FULL TIMESTAMPS. A 22:00 ->
 *     04:00 night shift is 6 hours, never -18.
 *  4. MODULAR ARITHMETIC IS CORRECT IN EXACTLY ONE PLACE: the SET of
 *     operating hours. 22:00 -> 04:00 gives {22,23,0,1,2,3}.
 *  5. NULL IS NOT ZERO. longest_gap_hours is null below 2 distinct tipping
 *     hours; avg_tippler_duration_hours is null when no date has a
 *     computable window. Both are asserted as null EXPLICITLY, never as a
 *     falsy 0.
 *
 * ------------------------------------------------------------------
 * CROSS-MILL SECURITY IS ASSERTED AT ITS TWO DIFFERENT SHAPES
 * ------------------------------------------------------------------
 *   - resolveBusinessUnit() IGNORES the client's business_unit_id for
 *     Supervisor / Mill Management — the caller's own mill, NOT a 403 (a
 *     403 would confirm the other mill exists);
 *   - authorizePeriod() REFUSES another mill's period_id with 403.
 * Collapsing those two into one assertion would hide whichever one broke.
 *
 * And the fail-closed rule (case 7) is asserted as a SPY, because "the
 * all-mills list was never built" is a claim about something that did NOT
 * happen: only a recorded call count of zero can prove it.
 */

use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Models\BusinessUnit;
use App\Models\CagesTippedTime;
use App\Models\CagesTrackRecord;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\Station;
use App\Models\User;
use App\Services\CagesTrackReportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * THE SPY for case 7.
 *
 * allBusinessUnits() is public on the service precisely so it can be
 * overridden here: the fail-closed rule for a Supervisor / Mill Management
 * account with no business_unit_id is only meaningful if it can be PROVEN
 * that the whole-mill list was never even read, and a recorded call count
 * of zero is that proof. Asserting only the 422 would pass just as happily
 * against an implementation that built the list first and threw afterwards.
 */
class CagesTrackReportAllBusinessUnitsSpy extends CagesTrackReportService
{
    public int $allBusinessUnitsCalls = 0;

    public function allBusinessUnits(): Collection
    {
        $this->allBusinessUnitsCalls++;

        return parent::allBusinessUnits();
    }
}

/**
 * One cages_track_records header on $date for $station, plus one
 * cages_tipped_times row per entry of $details.
 *
 * Defaults are a healthy 06:00 -> 18:00 tippler window and a header
 * `cages_tipped` of 999 — DELIBERATELY WRONG. That field is the summary
 * figure the report must never read; leaving it at a plausible value would
 * let a regression that reads it look right by accident.
 *
 * @param  list<array{hour: int, cages?: int, remain?: int}>  $details
 */
function cagesTrackReportRecord(Station $station, string $date, array $details = [], array $overrides = []): CagesTrackRecord
{
    $record = CagesTrackRecord::factory()->forStation($station)->onDate($date)->create(array_merge([
        'tippler_start_time' => $date.' 06:00:00',
        'tippler_stop_time' => $date.' 18:00:00',
        'cages_out' => 0,
        // Never a source of any figure — see the file docblock, trap 2.
        'cages_tipped' => 999,
    ], $overrides));

    foreach ($details as $detail) {
        CagesTippedTime::factory()->forRecord($record)->create([
            'tipped_hour' => $detail['hour'],
            'total_cages' => $detail['cages'] ?? 1,
            // NOT NULL in the schema — there is no per-row null branch.
            'cages_remain' => $detail['remain'] ?? 10,
            'checked_cage_numbers' => $detail['numbers'] ?? '1,2',
        ]);
    }

    return $record;
}

/** Shorthand: hours with one cage each, the shape most gap cases need. */
function cagesTrackReportHours(Station $station, string $date, array $hours, array $overrides = []): CagesTrackRecord
{
    return cagesTrackReportRecord($station, $date, array_map(
        fn ($hour) => ['hour' => $hour],
        $hours,
    ), $overrides);
}

/** Every SQL statement run inside $callback, for the "no SQL aggregate" cases. */
function cagesTrackReportQueriesDuring(callable $callback): array
{
    $queries = [];

    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    $callback();

    return $queries;
}

beforeEach(function () {
    $this->service = new CagesTrackReportService;

    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->cagesTrack()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->cagesTrack()->create();

    $this->supervisorA = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagementA = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operatorA = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    // Admin is the one role not bound to a mill — users.business_unit_id is
    // NULL, which is exactly why the mill picker exists.
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('cages-track')
        ->range('2026-03-01', '2026-03-31')
        ->open()
        ->named('Periode Maret Alpha')
        ->create();
});

// =====================================================================
// Access — cases 1-10
// =====================================================================

// Case 1
it('throws 401 UNAUTHENTICATED when there is no session', function () {
    // No actingAs() at all.
    expect(fn () => $this->service->summary($this->periodA))
        ->toThrow(AuthenticationException::class);
});

// Case 2 — WIDENED 2026-09-24 (screen-136--laporan-cages-track-mobile).
//
// This case used to assert the opposite: Operator refused with 403 and no
// station data query run at all, with a note saying "widening it belongs to
// screen-136". THAT WIDENING HAPPENED HERE. Operator is now accepted by
// guardAccess(), exactly like Supervisor / Mill Management.
//
// Acceptance alone is the cheap half, and asserting only "it no longer
// throws" would have left the expensive half untested. The decisive
// assertion is the second one: resolveBusinessUnit() must put Operator in
// the MILL-BOUND branch, where a client-supplied business_unit_id is
// DISCARDED — not in the Admin branch, where it is HONOURED. Admitting
// Operator in guardAccess() without also adding it to the bound branch
// would leave a service that answers 200 for any mill an Operator cares to
// name; that is the single hole this case exists to keep shut.
//
// The old "no station data query at all" assertion keeps its spirit rather
// than its letter: queries DO run now, so what is asserted is that every
// one of them is filtered to the Operator's OWN account business unit.
it('accepts a station_operator and binds it to its own mill instead of the Admin branch', function () {
    // BU-B figures are large and unmistakable: if any of them surfaced, the
    // numbers below could not be confused for a rounding difference.
    cagesTrackReportRecord($this->stationB, '2026-03-10', [['hour' => 6, 'cages' => 900]], ['cages_out' => 900]);
    cagesTrackReportRecord($this->stationA, '2026-03-10', [['hour' => 6, 'cages' => 7]], ['cages_out' => 5]);

    $this->actingAs($this->operatorA);

    $queries = [];

    DB::listen(function ($query) use (&$queries) {
        $queries[] = ['sql' => $query->sql, 'bindings' => $query->bindings];
    });

    // 1. ACCEPTED — no AuthorizationException anywhere on this path.
    $summary = $this->service->summary($this->periodA);

    // 2. THE POINT OF THIS CASE. A requested BU-B is discarded and the
    //    ACCOUNT's mill comes back — the mill-bound branch, not the Admin
    //    branch (which would have returned the requested 'BU-LAIN' as-is).
    expect($this->service->resolveBusinessUnit((string) $this->businessUnitB->id))
        ->toBe((string) $this->businessUnitA->id);
    expect($this->service->resolveBusinessUnit((string) $this->businessUnitB->id))
        ->not->toBe((string) $this->businessUnitB->id);
    // Even a mill id that does not exist at all is discarded rather than
    // validated — the parameter is never used for this role.
    expect($this->service->resolveBusinessUnit('BU-LAIN'))
        ->toBe((string) $this->businessUnitA->id);

    // 3. The old assertion's SPIRIT: the queries that do run are scoped.
    $stationQueries = collect($queries)
        ->filter(fn ($query) => str_contains($query['sql'], 'cages_track_records'))
        ->values();

    expect($stationQueries)->not->toBeEmpty();

    foreach ($stationQueries as $query) {
        $bindings = array_map(fn ($binding) => (string) $binding, $query['bindings']);

        expect($bindings)->toContain((string) $this->businessUnitA->id);
        expect($bindings)->not->toContain((string) $this->businessUnitB->id);
    }

    // 4. And the figures themselves are BU-A's, not BU-B's 900.
    expect($summary['kpi']['total_cages_tipped'])->toBe(7);
    expect($summary['kpi']['total_cages_out'])->toBe(5);
});

// Case 3
it('resolveBusinessUnit uses users.business_unit_id for Supervisor and ignores the client parameter', function () {
    $this->actingAs($this->supervisorA);

    // BU-B is passed in and DISCARDED — not validated, not compared.
    expect($this->service->resolveBusinessUnit((string) $this->businessUnitB->id))
        ->toBe((string) $this->businessUnitA->id);
});

// Case 4
it('resolveBusinessUnit uses users.business_unit_id for Mill Management and no BU-B data is aggregated', function () {
    cagesTrackReportRecord($this->stationB, '2026-03-10', [['hour' => 6, 'cages' => 70]], ['cages_out' => 70]);
    cagesTrackReportRecord($this->stationA, '2026-03-10', [['hour' => 6, 'cages' => 5]], ['cages_out' => 5]);

    $this->actingAs($this->millManagementA);

    expect($this->service->resolveBusinessUnit((string) $this->businessUnitB->id))
        ->toBe((string) $this->businessUnitA->id);

    $summary = $this->service->summary($this->periodA);

    expect($summary['kpi']['total_cages_tipped'])->toBe(5);
    expect($summary['kpi']['total_cages_out'])->toBe(5);
});

// Case 5
it('throws 422 VALIDATION_ERROR with errors.business_unit_id when Admin sends no mill', function () {
    $this->actingAs($this->admin);

    $exception = null;

    try {
        $this->service->resolveBusinessUnit(null);
    } catch (ValidationException $e) {
        $exception = $e;
    }

    // Incomplete input, not refused access — 422, never 403, and never a
    // silent empty result that would read as "this mill has no data".
    expect($exception)->not->toBeNull();
    expect($exception->status)->toBe(422);
    expect($exception->errors())->toHaveKey('business_unit_id');
});

// Case 6
it('resolveBusinessUnit uses the client parameter when Admin sends one, and aggregates that mill', function () {
    cagesTrackReportRecord($this->stationB, '2026-03-10', [['hour' => 9, 'cages' => 11]], ['cages_out' => 4]);

    $this->actingAs($this->admin);

    expect($this->service->resolveBusinessUnit((string) $this->businessUnitB->id))
        ->toBe((string) $this->businessUnitB->id);

    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('cages-track')
        ->range('2026-03-01', '2026-03-31')->create();

    $summary = $this->service->summary($periodB);

    expect($summary['kpi']['total_cages_tipped'])->toBe(11);
    expect($summary['kpi']['total_cages_out'])->toBe(4);
});

// Case 7 — THE SPY. See the class docblock above.
it('fails closed for a bound account with no mill and never calls allBusinessUnits()', function () {
    $noMillSupervisor = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $spy = new CagesTrackReportAllBusinessUnitsSpy;

    $this->actingAs($noMillSupervisor);

    $exception = null;

    try {
        $spy->resolveBusinessUnit((string) $this->businessUnitB->id);
    } catch (ValidationException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception->status)->toBe(422);
    expect($exception->errors())->toHaveKey('business_unit_id');
    expect($exception->errors()['business_unit_id'][0])->toContain('Hubungi Admin');

    // THE POINT OF THIS CASE: one broken master-data row must never turn
    // into a cross-mill leak by falling back to "every mill".
    expect($spy->allBusinessUnitsCalls)->toBe(0);
});

// Case 8
it('authorizePeriod throws 403 FORBIDDEN when the period belongs to another mill, and computes nothing', function () {
    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('cages-track')
        ->range('2026-03-01', '2026-03-31')->create();

    cagesTrackReportRecord($this->stationB, '2026-03-10', [['hour' => 6, 'cages' => 40]]);

    $this->actingAs($this->supervisorA);

    $caught = null;

    $queries = cagesTrackReportQueriesDuring(function () use (&$caught, $periodB) {
        try {
            $this->service->authorizePeriod((string) $periodB->id);
        } catch (AuthorizationException $e) {
            $caught = $e;
        }
    });

    // THIS is the real leak path — unlike the ignored business_unit_id
    // query param, a period id is a concrete handle to another mill's data.
    expect($caught)->toBeInstanceOf(AuthorizationException::class);
    expect(collect($queries)->filter(fn ($sql) => str_contains($sql, 'cages_tipped_times'))->values()->all())
        ->toBe([]);
});

// Case 9
it('authorizePeriod throws 404 NOT_FOUND when the period id does not exist', function () {
    $this->actingAs($this->supervisorA);

    expect(fn () => $this->service->authorizePeriod((string) Str::uuid()))
        ->toThrow(ModelNotFoundException::class);
});

// Case 10
it('authorizePeriod lets Admin through for any mill and aggregates that mill', function () {
    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('cages-track')
        ->range('2026-03-01', '2026-03-31')->create();

    cagesTrackReportRecord($this->stationB, '2026-03-10', [['hour' => 6, 'cages' => 7]], ['cages_out' => 3]);

    $this->actingAs($this->admin);

    $period = $this->service->authorizePeriod((string) $periodB->id);

    expect($period->id)->toBe($periodB->id);

    $summary = $this->service->summary($period);

    expect($summary['kpi']['total_cages_tipped'])->toBe(7);
    expect($summary['period']['business_unit_name'])->toBe('Mill Beta');
});

// =====================================================================
// listPeriods() — cases 11-13
// =====================================================================

// Case 11
it('listPeriods offers cages-track periods and every-station-type periods, always as the cages-track pair', function () {
    // stationType(null) tidak lagi berarti `station_type` NULL — kolom itu
    // hilang 2026-09-25. Cakupan semua-stasiun kini berarti satu baris
    // period_stations per jenis stasiun, dan baris 'cages-track'-nya itulah
    // yang membuat periode ini terpungut di sini.
    $allTypes = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType(null)
        ->range('2026-02-01', '2026-02-28')->named('Periode Februari Semua Stasiun')->create();
    $sterilizer = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('sterilizer')
        ->range('2026-01-01', '2026-01-31')->named('Periode Januari Sterilizer')->create();

    $this->actingAs($this->supervisorA);

    $periods = $this->service->listPeriods((string) $this->businessUnitA->id);
    $ids = collect($periods)->pluck('id')->all();

    expect($ids)->toContain((string) $this->periodA->id);
    expect($ids)->toContain((string) $allTypes->id);
    expect($ids)->not->toContain((string) $sterilizer->id);

    // Label 'Semua Stasiun' lenyap: setiap opsi adalah pasangan
    // (periode, cages-track), jadi jenis stasiunnya selalu terisi dan selalu
    // jenis stasiun layar ini.
    $option = collect($periods)->firstWhere('id', (string) $allTypes->id);

    expect($option['station_type'])->toBe('cages-track');
    expect($option['station_type_label'])->toBe('Cages Track');
});

// Case 11b — BARU 2026-09-26, bersama pemisahan periods/period_stations.
// Perilaku yang DULU dijamin cabang orWhereNull('station_type') dan kini
// sengaja dibuang: tanpa baris period_stations untuk cages-track, sebuah
// periode bukan periode Cages & Tracks, sekalipun milik mill yang sama.
it('listPeriods does NOT pick up a period with no period_stations row for cages-track', function () {
    $otherTypesOnly = Period::factory()->forBusinessUnit($this->businessUnitA)
        ->stationTypes(['sterilizer', 'boiler-room'])
        ->range('2026-02-01', '2026-02-28')->create();
    $noStations = Period::factory()->forBusinessUnit($this->businessUnitA)
        ->noStations()
        ->range('2026-01-01', '2026-01-31')->create();

    $this->actingAs($this->supervisorA);

    $ids = collect($this->service->listPeriods((string) $this->businessUnitA->id))->pluck('id')->all();

    expect($ids)->not->toContain((string) $otherTypesOnly->id);
    expect($ids)->not->toContain((string) $noStations->id);
});

// Case 11c — BARU 2026-09-26. Inti pemisahan model ini, dan bentuk yang
// sebelumnya mustahil diuji: dua jenis stasiun berstatus berbeda di periode
// yang sama. Laporan Cages & Tracks melaporkan status cages-track, bukan
// status stasiun lain di periode itu.
it('reports the cages-track status, not another station type status in the same period', function () {
    $period = Period::factory()->forBusinessUnit($this->businessUnitA)
        ->noStations()->range('2026-04-01', '2026-04-30')->named('Periode Campuran')->create();

    PeriodStation::factory()->forPeriod($period)->stationType('cages-track')->open()->create();
    PeriodStation::factory()->forPeriod($period)->stationType('sterilizer')->closed()->create();

    $this->actingAs($this->supervisorA);

    $option = collect($this->service->listPeriods((string) $this->businessUnitA->id))
        ->firstWhere('id', (string) $period->id);

    expect($option['status'])->toBe('open');
    expect($this->service->summary($period)['period']['status'])->toBe('open');
});

// Case 12
it('listPeriods only returns periods of the resolved business unit', function () {
    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('cages-track')
        ->range('2026-03-01', '2026-03-31')->create();

    $this->actingAs($this->supervisorA);

    $ids = collect($this->service->listPeriods((string) $this->businessUnitA->id))->pluck('id')->all();

    expect($ids)->toContain((string) $this->periodA->id);
    expect($ids)->not->toContain((string) $periodB->id);
});

// Case 13
it('listPeriods returns an empty array rather than a 404 when the mill has no covering period', function () {
    $this->periodA->delete();
    Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('sterilizer')
        ->range('2026-03-01', '2026-03-31')->create();

    $this->actingAs($this->supervisorA);

    // [] is a valid answer — an empty picker plus a UI hint, never a 404.
    expect($this->service->listPeriods((string) $this->businessUnitA->id))->toBe([]);
});

// =====================================================================
// summary() — scoping — cases 14-15
// =====================================================================

// Case 14
it('summary includes records exactly on start_date and exactly on end_date (inclusive on both bounds)', function () {
    cagesTrackReportRecord($this->stationA, '2026-03-01', [['hour' => 6, 'cages' => 4]], ['cages_out' => 4]);
    cagesTrackReportRecord($this->stationA, '2026-03-31', [['hour' => 7, 'cages' => 6]], ['cages_out' => 6]);
    // One day either side — neither may be pulled in by a timestamp
    // comparison that clips the boundary days.
    cagesTrackReportRecord($this->stationA, '2026-02-28', [['hour' => 6, 'cages' => 99]]);
    cagesTrackReportRecord($this->stationA, '2026-04-01', [['hour' => 6, 'cages' => 99]]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    expect($summary['kpi']['total_cages_tipped'])->toBe(10);
    expect(collect($summary['daily'])->pluck('date')->all())->toBe(['2026-03-01', '2026-03-31']);
});

// Case 15
it('summary only counts records of stations belonging to the period business unit and of type cages-track', function () {
    $otherMillStation = Station::factory()->forBusinessUnit($this->businessUnitB)->cagesTrack()->create();
    $sameMillSterilizer = Station::factory()->forBusinessUnit($this->businessUnitA)->sterilizer()->create();

    cagesTrackReportRecord($otherMillStation, '2026-03-10', [['hour' => 6, 'cages' => 50]], ['cages_out' => 50]);
    cagesTrackReportRecord($sameMillSterilizer, '2026-03-10', [['hour' => 6, 'cages' => 50]], ['cages_out' => 50]);
    cagesTrackReportRecord($this->stationA, '2026-03-10', [['hour' => 6, 'cages' => 12]], ['cages_out' => 9]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    expect($summary['kpi']['total_cages_tipped'])->toBe(12);
    expect($summary['kpi']['total_cages_out'])->toBe(9);
});

// =====================================================================
// summary() — GRAIN — cases 16-19
// =====================================================================

// Case 16
it('summary reads every tipping figure from cages_tipped_times, never from cages_track_records.cages_tipped', function () {
    cagesTrackReportRecord($this->stationA, '2026-03-10', [
        ['hour' => 6, 'cages' => 30],
        ['hour' => 7, 'cages' => 40],
    ], ['cages_tipped' => 100]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    // 70 from the hourly rows. The header's 100 is ignored ENTIRELY — the
    // two can differ when an Operator corrects one of them, and the hourly
    // rows are the ones recorded per event.
    expect($summary['kpi']['total_cages_tipped'])->toBe(70);
    expect($summary['total']['cages_tipped'])->toBe(70);
    expect($summary['daily'][0]['cages_tipped'])->toBe(70);
});

// Case 17 — THE GRAIN TRAP.
it('summary sums total_cages_out per record so 8 hourly rows do not multiply it', function () {
    cagesTrackReportRecord($this->stationA, '2026-03-10', [
        ['hour' => 0, 'cages' => 1], ['hour' => 1, 'cages' => 1],
        ['hour' => 2, 'cages' => 1], ['hour' => 3, 'cages' => 1],
        ['hour' => 4, 'cages' => 1], ['hour' => 5, 'cages' => 1],
        ['hour' => 6, 'cages' => 1], ['hour' => 7, 'cages' => 1],
    ], ['cages_out' => 50]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    // 50, NOT 400. This is what fails the moment cages_out is summed over
    // a JOIN to the hourly details instead of by iterating records.
    expect($summary['kpi']['total_cages_out'])->toBe(50);
    expect($summary['kpi']['total_cages_out'])->not->toBe(400);
    expect($summary['total']['cages_out'])->toBe(50);
    expect($summary['daily'][0]['cages_out'])->toBe(50);
});

// Case 18
it('summary sums total_cages_out across every record on the same date', function () {
    cagesTrackReportRecord($this->stationA, '2026-03-10', [['hour' => 6, 'cages' => 1]], ['cages_out' => 20]);
    cagesTrackReportRecord($this->stationA, '2026-03-10', [['hour' => 7, 'cages' => 1]], ['cages_out' => 30]);
    cagesTrackReportRecord($this->stationA, '2026-03-10', [['hour' => 8, 'cages' => 1]], ['cages_out' => 40]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    expect($summary['daily'])->toHaveCount(1);
    expect($summary['daily'][0]['cages_out'])->toBe(90);
    expect($summary['kpi']['total_cages_out'])->toBe(90);
});

// Case 19
it('summary merges several records on one date into a single daily row', function () {
    cagesTrackReportRecord($this->stationA, '2026-03-02', [['hour' => 6, 'cages' => 8]], [
        'cages_track_number' => 'CT-A',
    ]);
    cagesTrackReportRecord($this->stationA, '2026-03-02', [['hour' => 9, 'cages' => 5]], [
        'cages_track_number' => 'CT-B',
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    expect($summary['daily'])->toHaveCount(1);
    expect($summary['daily'][0]['date'])->toBe('2026-03-02');
    expect($summary['daily'][0]['cages_tipped'])->toBe(13);
});

// =====================================================================
// summary() — hourly distribution — cases 20-21
// =====================================================================

// Case 20
it('summary always returns 24 hourly entries in order even when most of them are zero', function () {
    cagesTrackReportRecord($this->stationA, '2026-03-10', [
        ['hour' => 7, 'cages' => 3],
        ['hour' => 8, 'cages' => 4],
        ['hour' => 9, 'cages' => 5],
    ]);

    $this->actingAs($this->supervisorA);

    $hourly = $this->service->summary($this->periodA)['hourly'];

    // The chart must keep the same shape from period to period, so a quiet
    // hour is visibly quiet rather than absent.
    expect($hourly)->toHaveCount(24);
    expect(collect($hourly)->pluck('hour')->all())->toBe(range(0, 23));

    foreach ($hourly as $row) {
        $expected = match ($row['hour']) {
            7 => 3,
            8 => 4,
            9 => 5,
            default => 0,
        };

        expect($row['cages'])->toBe($expected);
    }
});

// Case 21
it('summary marks within_operating_window from the tippler window of the day', function () {
    cagesTrackReportRecord($this->stationA, '2026-03-02', [['hour' => 7, 'cages' => 2]], [
        'tippler_start_time' => '2026-03-02 06:00:00',
        'tippler_stop_time' => '2026-03-02 18:00:00',
    ]);

    $this->actingAs($this->supervisorA);

    $hourly = $this->service->summary($this->periodA)['hourly'];

    foreach ($hourly as $row) {
        // 06:00 -> 18:00 is 12 hours, walked forward from hour 6: {6..17}.
        expect($row['within_operating_window'])->toBe($row['hour'] >= 6 && $row['hour'] <= 17);
    }
});

// =====================================================================
// summary() — the operating window — cases 22-27
// =====================================================================

// Case 22
it('summary computes the window duration from full timestamps, not from the hour components', function () {
    cagesTrackReportRecord($this->stationA, '2026-03-02', [['hour' => 7, 'cages' => 2]], [
        'tippler_start_time' => '2026-03-02 06:00:00',
        'tippler_stop_time' => '2026-03-02 18:00:00',
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    expect($summary['daily'][0]['operating_hours'])->toBe(12.0);
});

// Case 23
it('summary reports a positive duration across midnight because the stop carries the next date', function () {
    cagesTrackReportRecord($this->stationA, '2026-03-02', [
        ['hour' => 22, 'cages' => 2],
        ['hour' => 1, 'cages' => 3],
    ], [
        'tippler_start_time' => '2026-03-02 22:00:00',
        'tippler_stop_time' => '2026-03-03 04:00:00',
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    // 6.0, never -18: the duration is a timestamp difference. The circular
    // arithmetic belongs to the HOUR SET only, asserted right below.
    expect($summary['daily'][0]['operating_hours'])->toBe(6.0);
    expect($summary['daily'][0]['operating_hours'])->toBeGreaterThan(0);

    $withinWindow = collect($summary['hourly'])
        ->filter(fn ($row) => $row['within_operating_window'])
        ->pluck('hour')
        ->sort()
        ->values()
        ->all();

    expect($withinWindow)->toBe([0, 1, 2, 3, 22, 23]);
});

// Case 24
it('summary treats stop <= start as a window that cannot be computed, never as a negative duration', function () {
    cagesTrackReportRecord($this->stationA, '2026-03-02', [['hour' => 22, 'cages' => 2]], [
        // Same date, stop earlier than start — the validator carries no
        // `after:` rule, so this really can reach the database.
        'tippler_start_time' => '2026-03-02 22:00:00',
        'tippler_stop_time' => '2026-03-02 04:00:00',
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    expect($summary['daily'][0]['operating_hours'])->toBeNull();
    expect($summary['daily'][0]['idle_operating_hours'])->toBeNull();
    expect($summary['kpi']['days_without_valid_window'])->toBe(1);
    expect($summary['kpi']['avg_tippler_duration_hours'])->toBeNull();
});

// Case 25
it('summary caps the operating hour set at 24 distinct hours for a window longer than a day', function () {
    cagesTrackReportRecord($this->stationA, '2026-03-02', [['hour' => 6, 'cages' => 2]], [
        'tippler_start_time' => '2026-03-02 06:00:00',
        'tippler_stop_time' => '2026-03-04 06:00:00',
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    $withinWindow = collect($summary['hourly'])
        ->filter(fn ($row) => $row['within_operating_window'])
        ->pluck('hour')
        ->all();

    // Every hour, each of them exactly once — not 48 entries, not repeats.
    expect($withinWindow)->toHaveCount(24);
    expect(array_values(array_unique($withinWindow)))->toHaveCount(24);
    expect($summary['daily'][0]['operating_hours'])->toBe(48.0);
});

// Case 26
it('summary disqualifies a whole date when one of its records has no computable window', function () {
    cagesTrackReportRecord($this->stationA, '2026-03-02', [['hour' => 6, 'cages' => 3]], [
        'cages_track_number' => 'CT-OK',
        'tippler_start_time' => '2026-03-02 06:00:00',
        'tippler_stop_time' => '2026-03-02 18:00:00',
    ]);
    cagesTrackReportRecord($this->stationA, '2026-03-02', [['hour' => 9, 'cages' => 2]], [
        'cages_track_number' => 'CT-BROKEN',
        'tippler_start_time' => '2026-03-02 06:00:00',
        'tippler_stop_time' => null,
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    // FAIL CLOSED: a partial figure would silently lose part of the day.
    expect($summary['daily'])->toHaveCount(1);
    expect($summary['daily'][0]['operating_hours'])->toBeNull();
    expect($summary['daily'][0]['idle_operating_hours'])->toBeNull();
    expect($summary['kpi']['days_without_valid_window'])->toBe(1);
    // The tipping figures themselves are unaffected — only the window is.
    expect($summary['daily'][0]['cages_tipped'])->toBe(5);
});

// Case 27
it('summary excludes an uncomputable date from both the duration average and the idle hours', function () {
    // 10 hours.
    cagesTrackReportRecord($this->stationA, '2026-03-02', [['hour' => 6, 'cages' => 1]], [
        'tippler_start_time' => '2026-03-02 06:00:00',
        'tippler_stop_time' => '2026-03-02 16:00:00',
    ]);
    // 8 hours.
    cagesTrackReportRecord($this->stationA, '2026-03-03', [['hour' => 6, 'cages' => 1]], [
        'tippler_start_time' => '2026-03-03 06:00:00',
        'tippler_stop_time' => '2026-03-03 14:00:00',
    ]);
    // No stop at all.
    cagesTrackReportRecord($this->stationA, '2026-03-04', [['hour' => 6, 'cages' => 1]], [
        'tippler_start_time' => '2026-03-04 06:00:00',
        'tippler_stop_time' => null,
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    expect($summary['kpi']['avg_tippler_duration_hours'])->toBe(9.0);
    expect($summary['kpi']['days_without_valid_window'])->toBe(1);
    expect($summary['kpi']['days_with_records'])->toBe(3);
    // 9 idle hours on 2026-03-02 (10-hour window, 1 tipped) + 7 on
    // 2026-03-03 (8-hour window, 1 tipped). The NULL date contributes ZERO
    // — never an estimate.
    expect($summary['kpi']['idle_operating_hours'])->toBe(16);
    expect($summary['daily'][2]['idle_operating_hours'])->toBeNull();
});

// =====================================================================
// summary() — idle hours — cases 28-30
// =====================================================================

// Case 28
it('summary counts idle hours only inside the operating window', function () {
    cagesTrackReportRecord($this->stationA, '2026-03-02', [
        ['hour' => 6, 'cages' => 3],
        ['hour' => 7, 'cages' => 3],
        ['hour' => 8, 'cages' => 3],
    ], [
        'tippler_start_time' => '2026-03-02 06:00:00',
        'tippler_stop_time' => '2026-03-02 18:00:00',
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    // Hours 9..17 — nine of them. Hours 0..5 and 18..23 are outside the
    // window and are NEVER counted.
    expect($summary['kpi']['idle_operating_hours'])->toBe(9);
    expect($summary['daily'][0]['idle_operating_hours'])->toBe(9);
});

// Case 29
it('summary counts only the window hours as idle even when the whole day has no tipping at all', function () {
    cagesTrackReportRecord($this->stationA, '2026-03-02', [], [
        'tippler_start_time' => '2026-03-02 08:00:00',
        'tippler_stop_time' => '2026-03-02 12:00:00',
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    // 4 (hours 8..11), not 24. Counting all 24 would make a one-shift mill
    // look permanently idle for 16 hours — true, and deeply misleading.
    expect($summary['kpi']['idle_operating_hours'])->toBe(4);
    expect($summary['kpi']['idle_operating_hours'])->not->toBe(24);
});

// Case 30
it('summary counts idle hours against the circular hour set across midnight', function () {
    cagesTrackReportRecord($this->stationA, '2026-03-02', [
        ['hour' => 1, 'cages' => 2],
        ['hour' => 22, 'cages' => 2],
    ], [
        'tippler_start_time' => '2026-03-02 22:00:00',
        'tippler_stop_time' => '2026-03-03 04:00:00',
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    // Window {22,23,0,1,2,3}, tipped {1,22} -> idle {23,0,2,3} = 4.
    // Hours 5..21 are outside the window and are not counted.
    expect($summary['kpi']['idle_operating_hours'])->toBe(4);
    expect($summary['daily'][0]['idle_operating_hours'])->toBe(4);
});

// =====================================================================
// summary() — longest gap — cases 31-37
// =====================================================================

// Case 31
it('summary takes the largest difference between consecutive tipping hours within one date', function () {
    cagesTrackReportHours($this->stationA, '2026-03-02', [6, 8, 14]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    // 14 - 8 = 6, not the first gap of 2.
    expect($summary['kpi']['longest_gap_hours'])->toBe(6);
    expect($summary['daily'][0]['longest_gap_hours'])->toBe(6);
});

// Case 31b
//
// THE NIGHT-SHIFT GAP. This is the case the first version of the spec got
// wrong, and it was wrong on the screen's headline KPI. A tippler running
// 22:00 -> 04:00 without a single pause records hours 22, 23, 0, 1. Sorted
// as bare integers that reads [0, 1, 22, 23], whose largest step is 21 — a
// mill that never stopped, reported as having idled 21 hours. Hours are
// therefore ordered by ELAPSED TIME SINCE THE WINDOW OPENED, not by their
// numeric value.
it('summary measures an overnight gap from the window start, not from the numeric hour order', function () {
    cagesTrackReportHours($this->stationA, '2026-03-02', [22, 23, 0, 1], [
        'tippler_start_time' => '2026-03-02 22:00:00',
        'tippler_stop_time' => '2026-03-03 04:00:00',
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    // Anchored at 22 the offsets are [0,1,2,3]: the tippler paused an hour
    // at most. 21 would be the numeric-order answer, and it is wrong.
    expect($summary['kpi']['longest_gap_hours'])->toBe(1);
    expect($summary['kpi']['longest_gap_hours'])->not->toBe(21);
    expect($summary['daily'][0]['longest_gap_hours'])->toBe(1);
});

// Case 31c
it('summary still reports a real pause inside an overnight window', function () {
    cagesTrackReportHours($this->stationA, '2026-03-02', [22, 1], [
        'tippler_start_time' => '2026-03-02 22:00:00',
        'tippler_stop_time' => '2026-03-03 04:00:00',
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    // 22:00 -> 01:00 really is three hours of elapsed time.
    expect($summary['kpi']['longest_gap_hours'])->toBe(3);
});

// Case 31d
//
// A date whose window cannot be computed has no anchor, and inventing one
// would fabricate a shift boundary nobody recorded. Those dates keep the
// plain numeric order — stated here so the fallback is deliberate rather
// than accidental.
it('summary falls back to numeric hour order when the date has no computable window', function () {
    cagesTrackReportHours($this->stationA, '2026-03-02', [6, 14], [
        'tippler_stop_time' => null,
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    expect($summary['kpi']['longest_gap_hours'])->toBe(8);
    expect($summary['kpi']['days_without_valid_window'])->toBe(1);
});

// Case 32
it('summary merges the tipping hours of every record on a date before measuring the gap', function () {
    cagesTrackReportHours($this->stationA, '2026-03-02', [6, 12], ['cages_track_number' => 'CT-A']);
    cagesTrackReportHours($this->stationA, '2026-03-02', [9], ['cages_track_number' => 'CT-B']);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    // {6,9,12} -> 3, not the 6 that record A alone would suggest.
    expect($summary['kpi']['longest_gap_hours'])->toBe(3);
});

// Case 33
it('summary never measures a gap across dates', function () {
    cagesTrackReportHours($this->stationA, '2026-03-02', [6, 7]);
    cagesTrackReportHours($this->stationA, '2026-03-03', [20, 21]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    // An overnight pause is not an operational gap.
    expect($summary['kpi']['longest_gap_hours'])->toBe(1);
});

// Case 34
it('summary lets a date with a single tipping hour contribute no gap candidate at all', function () {
    cagesTrackReportHours($this->stationA, '2026-03-02', [10]);
    cagesTrackReportHours($this->stationA, '2026-03-03', [6, 9]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    expect($summary['kpi']['longest_gap_hours'])->toBe(3);
    expect($summary['kpi']['longest_gap_date'])->toBe('2026-03-03');
    // null, NOT 0: with one tipping hour there is no gap to measure.
    expect($summary['daily'][0]['longest_gap_hours'])->toBeNull();
});

// Case 35
it('summary returns a null longest gap when the whole period has a single tipping hour', function () {
    cagesTrackReportHours($this->stationA, '2026-03-02', [10]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    expect($summary['kpi']['longest_gap_hours'])->toBeNull();
    expect($summary['kpi']['longest_gap_date'])->toBeNull();
    // Explicitly NOT zero — 0 would claim there was no pause.
    expect($summary['kpi']['longest_gap_hours'])->not->toBe(0);
});

// Case 36
it('summary reports the date on which the longest gap happened', function () {
    cagesTrackReportHours($this->stationA, '2026-03-02', [6, 9]);
    cagesTrackReportHours($this->stationA, '2026-03-05', [6, 13]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    expect($summary['kpi']['longest_gap_hours'])->toBe(7);
    expect($summary['kpi']['longest_gap_date'])->toBe('2026-03-05');
});

// Case 37
it('summary picks the earliest date when two dates share the longest gap', function () {
    cagesTrackReportHours($this->stationA, '2026-03-02', [6, 11]);
    cagesTrackReportHours($this->stationA, '2026-03-05', [8, 13]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    expect($summary['kpi']['longest_gap_hours'])->toBe(5);
    // Deterministic: ties go to the earliest date.
    expect($summary['kpi']['longest_gap_date'])->toBe('2026-03-02');
});

// =====================================================================
// summary() — queue — cases 38-39
// =====================================================================

// Case 38
it('summary reports the remaining queue as a snapshot, min and average, never a sum', function () {
    cagesTrackReportRecord($this->stationA, '2026-03-02', [
        ['hour' => 6, 'cages' => 2, 'remain' => 12],
        ['hour' => 7, 'cages' => 2, 'remain' => 5],
        ['hour' => 8, 'cages' => 2, 'remain' => 9],
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    // (12 + 5 + 9) / 3 = 8.666... -> 8.7. The sum 26 must appear nowhere.
    expect($summary['queue']['min_remaining'])->toBe(5);
    expect($summary['queue']['avg_remaining'])->toBe(8.7);
    expect($summary['queue']['min_remaining'])->not->toBe(26);
    expect($summary['queue']['avg_remaining'])->not->toBe(26.0);
});

// Case 39
it('summary returns a null queue when the period has no hourly detail row at all', function () {
    cagesTrackReportRecord($this->stationA, '2026-03-02', []);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    expect($summary['queue']['min_remaining'])->toBeNull();
    expect($summary['queue']['avg_remaining'])->toBeNull();
});

// =====================================================================
// summary() — tippler duration — cases 40-41
// =====================================================================

// Case 40
it('summary averages the tippler duration over the windowed dates only', function () {
    cagesTrackReportRecord($this->stationA, '2026-03-02', [['hour' => 6, 'cages' => 1]], [
        'tippler_start_time' => '2026-03-02 06:00:00',
        'tippler_stop_time' => '2026-03-02 16:00:00', // 10 hours
    ]);
    cagesTrackReportRecord($this->stationA, '2026-03-03', [['hour' => 6, 'cages' => 1]], [
        'tippler_start_time' => '2026-03-03 06:00:00',
        'tippler_stop_time' => '2026-03-03 14:00:00', // 8 hours
    ]);
    cagesTrackReportRecord($this->stationA, '2026-03-04', [['hour' => 6, 'cages' => 1]], [
        'tippler_start_time' => '2026-03-04 06:00:00',
        'tippler_stop_time' => null,
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    // Denominator 2, not 3.
    expect($summary['kpi']['avg_tippler_duration_hours'])->toBe(9.0);
    expect($summary['kpi']['days_without_valid_window'])->toBe(1);
});

// Case 41
it('summary returns a null tippler duration when no date has a computable window', function () {
    foreach (['2026-03-02', '2026-03-03', '2026-03-04'] as $date) {
        cagesTrackReportRecord($this->stationA, $date, [['hour' => 6, 'cages' => 1]], [
            'tippler_start_time' => $date.' 06:00:00',
            'tippler_stop_time' => null,
        ]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    // null, NOT 0 — 0 would read as "the tippler never ran".
    expect($summary['kpi']['avg_tippler_duration_hours'])->toBeNull();
    expect($summary['kpi']['avg_tippler_duration_hours'])->not->toBe(0.0);
    expect($summary['kpi']['days_without_valid_window'])->toBe(3);
});

// =====================================================================
// summary() — daily rows and the daily average — cases 42-44
// =====================================================================

// Case 42
it('summary still emits a daily row with zero tipping for a date whose record has no hourly row', function () {
    cagesTrackReportRecord($this->stationA, '2026-03-02', [['hour' => 6, 'cages' => 30]]);
    cagesTrackReportRecord($this->stationA, '2026-03-03', []);
    cagesTrackReportRecord($this->stationA, '2026-03-04', [['hour' => 6, 'cages' => 60]]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    expect($summary['daily'])->toHaveCount(3);
    expect($summary['daily'][1]['date'])->toBe('2026-03-03');
    expect($summary['daily'][1]['cages_tipped'])->toBe(0);
    // Still in the denominator: dropping it would make the daily average
    // look better than reality.
    expect($summary['kpi']['days_with_records'])->toBe(3);
    expect($summary['kpi']['avg_cages_per_day'])->toBe(30.0);
});

// Case 43
it('summary divides by every date that has a record, including the ones without tipping', function () {
    cagesTrackReportRecord($this->stationA, '2026-03-02', [['hour' => 6, 'cages' => 40]]);
    cagesTrackReportRecord($this->stationA, '2026-03-03', [['hour' => 6, 'cages' => 50]]);
    cagesTrackReportRecord($this->stationA, '2026-03-04', []);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    // 90 / 3 = 30.0, not 90 / 2 = 45.0.
    expect($summary['kpi']['total_cages_tipped'])->toBe(90);
    expect($summary['kpi']['avg_cages_per_day'])->toBe(30.0);
    expect($summary['kpi']['avg_cages_per_day'])->not->toBe(45.0);
});

// Case 44
it('summary emits no daily row for a date that has no record at all', function () {
    $period = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('cages-track')
        ->range('2026-03-01', '2026-03-10')->create();

    cagesTrackReportHours($this->stationA, '2026-03-02', [6]);
    cagesTrackReportHours($this->stationA, '2026-03-05', [6]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($period);

    // No zero-padded rows: an empty bar would read as "we measured
    // nothing" rather than "the mill did not run".
    expect($summary['daily'])->toHaveCount(2);
    expect(collect($summary['daily'])->pluck('date')->all())->toBe(['2026-03-02', '2026-03-05']);
    expect($summary['kpi']['days_with_records'])->toBe(2);
});

// =====================================================================
// summary() — peak hour — cases 45-46
// =====================================================================

// Case 45
it('summary reports the peak tipping hour with its cage count', function () {
    cagesTrackReportRecord($this->stationA, '2026-03-02', [
        ['hour' => 7, 'cages' => 20],
        ['hour' => 8, 'cages' => 45],
        ['hour' => 9, 'cages' => 30],
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    expect($summary['kpi']['peak_hour'])->toBe(8);
    expect($summary['kpi']['peak_hour_cages'])->toBe(45);
});

// Case 46
it('summary picks the smallest hour when two hours tie for the peak', function () {
    cagesTrackReportRecord($this->stationA, '2026-03-02', [
        ['hour' => 7, 'cages' => 45],
        ['hour' => 14, 'cages' => 45],
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    // Deterministic — ties go to the earlier hour.
    expect($summary['kpi']['peak_hour'])->toBe(7);
    expect($summary['kpi']['peak_hour_cages'])->toBe(45);
});

// =====================================================================
// summary() — empty period + PHP aggregation — cases 47-48
// =====================================================================

// Case 47
it('summary returns zeroed figures and explicit nulls for a period with no tipping at all', function () {
    $this->actingAs($this->supervisorA);

    $summary = $this->service->summary($this->periodA);

    expect($summary['kpi']['total_cages_tipped'])->toBe(0);
    expect($summary['kpi']['total_cages_out'])->toBe(0);
    // Guarded division — an empty period is 0, never a DivisionByZeroError.
    expect($summary['kpi']['avg_cages_per_day'])->toBe(0.0);
    expect($summary['kpi']['peak_hour'])->toBeNull();
    expect($summary['kpi']['peak_hour_cages'])->toBe(0);
    expect($summary['kpi']['idle_operating_hours'])->toBe(0);
    expect($summary['kpi']['longest_gap_hours'])->toBeNull();
    expect($summary['kpi']['longest_gap_date'])->toBeNull();
    expect($summary['kpi']['avg_tippler_duration_hours'])->toBeNull();
    expect($summary['kpi']['days_with_records'])->toBe(0);
    expect($summary['kpi']['days_without_valid_window'])->toBe(0);
    expect($summary['hourly'])->toHaveCount(24);
    expect(collect($summary['hourly'])->pluck('cages')->unique()->all())->toBe([0]);
    expect($summary['daily'])->toBe([]);
    expect($summary['queue']['min_remaining'])->toBeNull();
    expect($summary['queue']['avg_remaining'])->toBeNull();
    expect($summary['total']['days'])->toBe(0);
});

// Case 48
it('summary aggregates in PHP over raw rows, with no SQL aggregate or timestamp arithmetic', function () {
    cagesTrackReportRecord($this->stationA, '2026-03-02', [['hour' => 6, 'cages' => 5, 'remain' => 4]], [
        'tippler_start_time' => '2026-03-02 06:00:00',
        'tippler_stop_time' => null,
    ]);
    // A date with a record but no hourly row — the other half of the shape
    // whose SQLite/PostgreSQL behaviour would differ if it were aggregated
    // in SQL.
    cagesTrackReportRecord($this->stationA, '2026-03-03', []);

    $this->actingAs($this->supervisorA);

    $summary = null;

    $queries = cagesTrackReportQueriesDuring(function () use (&$summary) {
        $summary = $this->service->summary($this->periodA);
    });

    $aggregates = collect($queries)
        ->filter(fn ($sql) => preg_match('/\b(sum|avg|min|max|count)\s*\(/i', $sql) === 1
            || stripos($sql, 'group by') !== false
            || stripos($sql, 'julianday') !== false
            || stripos($sql, 'timestampdiff') !== false)
        ->values()
        ->all();

    expect($aggregates)->toBe([]);

    // And the null-is-not-zero rules survive the round trip on SQLite:
    // the date whose stop is NULL yields null operating hours (not 0) and
    // is excluded from the average, while the date whose record carries no
    // hourly row yields 0 tipped cages (not a missing row).
    expect($summary['daily'][0]['operating_hours'])->toBeNull();
    expect($summary['daily'][0]['idle_operating_hours'])->toBeNull();
    expect($summary['kpi']['days_without_valid_window'])->toBe(1);
    // Averaged over the one windowed date only — never over both.
    expect($summary['kpi']['avg_tippler_duration_hours'])->toBe(12.0);
    expect($summary['daily'][1]['cages_tipped'])->toBe(0);
});

// =====================================================================
// export() — cases 49-51
// =====================================================================

// Case 49
//
// NOTE ON chunk(200): the tech spec phrases this case as "chunk(200) is
// called 3 times" for 450 hourly rows. The implementation chunks the
// RECORD query (the header rows) and streams every hourly row of each
// record inside the chunk callback, so the chunk count follows the number
// of records, not of hourly rows. The OBSERVABLE contract — a streamed
// text/csv response carrying exactly one line per tipping hour with the
// record context columns repeated — is what this case asserts, because it
// is what the file's reader actually gets.
it('export streams one line per tipping hour with the record context repeated', function () {
    $now = now()->toDateTimeString();
    $totalRows = 450;

    // 450 hourly rows spread over 19 headers: the (record, hour) unique
    // index caps a single record at 24 rows. Both header and detail rows
    // are inserted through the query builder rather than the factories —
    // CagesTippedTimeFactory's tipped_hour uses faker's unique() generator,
    // which overflows long before 450, and CagesTrackRecordFactory would
    // create one User per record.
    $written = 0;
    $recordIndex = 0;

    while ($written < $totalRows) {
        $recordId = (string) Str::uuid();
        $date = '2026-03-'.str_pad((string) ($recordIndex + 1), 2, '0', STR_PAD_LEFT);

        DB::table('cages_track_records')->insert([
            'id' => $recordId,
            'station_id' => $this->stationA->id,
            'cages_track_number' => 'CT-EXPORT-'.$recordIndex,
            'date' => $date,
            'tippler_start_time' => $date.' 06:00:00',
            'tippler_stop_time' => $date.' 18:00:00',
            'cages_out' => 7,
            'cages_tipped' => 999,
            'note' => 'Catatan ekspor',
            'status' => 'saved',
            'created_by' => $this->supervisorA->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $rows = [];

        for ($hour = 0; $hour < 24 && $written < $totalRows; $hour++) {
            $rows[] = [
                'id' => (string) Str::uuid(),
                'cages_track_record_id' => $recordId,
                'tipped_hour' => $hour,
                'checked_cage_numbers' => '1,2',
                'total_cages' => 2,
                'cages_remain' => 8,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $written++;
        }

        DB::table('cages_tipped_times')->insert($rows);
        $recordIndex++;
    }

    $this->actingAs($this->supervisorA);

    $response = $this->service->export($this->periodA, 'csv');

    expect($response)->toBeInstanceOf(StreamedResponse::class);
    expect($response->headers->get('Content-Type'))->toBe('text/csv');

    ob_start();
    $response->sendContent();
    $body = ob_get_clean();

    $lines = array_values(array_filter(explode("\n", trim($body))));

    // Header + one line per TIPPING HOUR.
    expect($lines)->toHaveCount($totalRows + 1);
    expect($lines[0])->toContain('Nomor Cages Track');
    expect($lines[0])->toContain('Lori Ditumpahkan');
    expect($lines[0])->toContain('Lori Keluar (record)');

    // The record's context columns are repeated verbatim on every line so
    // the file can be pivoted straight in a spreadsheet.
    expect($lines[1])->toContain('CT-EXPORT-0');
    expect($lines[1])->toContain('Catatan ekspor');
    expect($lines[1])->toContain('2026-03-01 06:00');
    expect($lines[24])->toContain('CT-EXPORT-0');
    expect($lines[25])->toContain('CT-EXPORT-1');
    // Header grain repeated, NEVER read as a per-hour figure.
    expect(substr_count($body, 'Catatan ekspor'))->toBe($totalRows);
});

// Case 50
it('export throws 422 EXPORT_FAILED when the number of HOURLY rows exceeds 50.000', function () {
    // The ceiling counts HOURLY LINES, not header records — one daily
    // record carries up to 24 of them, which is exactly the trap commit
    // 8611974 fixed for the 18 station exports. A couple of thousand
    // headers, 50.001 hourly rows.
    $now = now()->toDateTimeString();
    $total = CagesTrackReportService::EXPORT_ROW_LIMIT + 1;
    $written = 0;
    $recordIndex = 0;
    $headers = 0;
    $recordRows = [];
    $detailRows = [];

    while ($written < $total) {
        $recordId = (string) Str::uuid();
        $date = '2026-03-'.str_pad((string) (($recordIndex % 28) + 1), 2, '0', STR_PAD_LEFT);

        $recordRows[] = [
            'id' => $recordId,
            'station_id' => $this->stationA->id,
            'cages_track_number' => 'CT-LIMIT-'.$recordIndex,
            'date' => $date,
            'tippler_start_time' => $date.' 06:00:00',
            'tippler_stop_time' => $date.' 18:00:00',
            'cages_out' => 1,
            'cages_tipped' => 1,
            'note' => null,
            'status' => 'saved',
            'created_by' => $this->supervisorA->id,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $headers++;

        for ($hour = 0; $hour < 24 && $written < $total; $hour++) {
            $detailRows[] = [
                'id' => (string) Str::uuid(),
                'cages_track_record_id' => $recordId,
                'tipped_hour' => $hour,
                'checked_cage_numbers' => '1',
                'total_cages' => 1,
                'cages_remain' => 9,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $written++;
        }

        $recordIndex++;
    }

    foreach (array_chunk($recordRows, 500) as $chunk) {
        DB::table('cages_track_records')->insert($chunk);
    }

    foreach (array_chunk($detailRows, 2000) as $chunk) {
        DB::table('cages_tipped_times')->insert($chunk);
    }

    $this->actingAs($this->supervisorA);

    // Far fewer headers than the ceiling — counting headers would sail
    // straight past the real limit.
    expect($headers)->toBeLessThan(CagesTrackReportService::EXPORT_ROW_LIMIT);
    expect(CagesTippedTime::count())->toBe($total);

    expect(fn () => $this->service->export($this->periodA, 'csv'))
        ->toThrow(ExportFailedException::class);
});

// Case 51
it('export throws 422 VALIDATION_ERROR for a format outside csv and excel', function () {
    $this->actingAs($this->supervisorA);

    $exception = null;

    try {
        $this->service->export($this->periodA, 'xlsx');
    } catch (ValidationException $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception->status)->toBe(422);
    expect($exception->errors())->toHaveKey('format');

    // The two supported ones still work.
    expect($this->service->export($this->periodA, 'csv'))->toBeInstanceOf(StreamedResponse::class);
    expect($this->service->export($this->periodA, 'excel'))->toBeInstanceOf(StreamedResponse::class);
});

// =====================================================================
// Case 52 — full success path
// =====================================================================

it('returns the complete recap when every condition passes, and changes no station data', function () {
    $this->actingAs($this->supervisorA);

    // Day 1 — 06:00-18:00, tipping at 6, 7 and 8.
    cagesTrackReportRecord($this->stationA, '2026-03-02', [
        ['hour' => 6, 'cages' => 30, 'remain' => 12],
        ['hour' => 7, 'cages' => 20, 'remain' => 16],
        ['hour' => 8, 'cages' => 10, 'remain' => 20],
    ], [
        'cages_track_number' => 'CT-001',
        'cages_out' => 55,
        'cages_tipped' => 999,
        'tippler_start_time' => '2026-03-02 06:00:00',
        'tippler_stop_time' => '2026-03-02 18:00:00',
    ]);

    // Day 2 — same window, tipping at 6 and 14 (an 8-hour lull).
    cagesTrackReportRecord($this->stationA, '2026-03-05', [
        ['hour' => 6, 'cages' => 25, 'remain' => 14],
        ['hour' => 14, 'cages' => 15, 'remain' => 18],
    ], [
        'cages_track_number' => 'CT-002',
        'cages_out' => 35,
        'cages_tipped' => 999,
        'tippler_start_time' => '2026-03-05 06:00:00',
        'tippler_stop_time' => '2026-03-05 18:00:00',
    ]);

    $recordsBefore = CagesTrackRecord::count();
    $detailsBefore = CagesTippedTime::count();

    $period = $this->service->authorizePeriod((string) $this->periodA->id);
    $summary = $this->service->summary($period);

    expect(array_keys($summary))->toBe(['period', 'kpi', 'hourly', 'daily', 'queue', 'total']);
    expect($summary['period']['status'])->toBe('open');
    expect($summary['period']['business_unit_name'])->toBe('Mill Alpha');

    expect($summary['kpi']['total_cages_tipped'])->toBe(100);
    expect($summary['kpi']['total_cages_out'])->toBe(90);
    expect($summary['kpi']['avg_cages_per_day'])->toBe(50.0);
    expect($summary['kpi']['peak_hour'])->toBe(6);
    expect($summary['kpi']['peak_hour_cages'])->toBe(55);
    // Day 1: window {6..17}, tipped {6,7,8} -> 9 idle.
    // Day 2: window {6..17}, tipped {6,14}  -> 10 idle.
    expect($summary['kpi']['idle_operating_hours'])->toBe(19);
    expect($summary['kpi']['longest_gap_hours'])->toBe(8);
    expect($summary['kpi']['longest_gap_date'])->toBe('2026-03-05');
    expect($summary['kpi']['avg_tippler_duration_hours'])->toBe(12.0);
    expect($summary['kpi']['days_with_records'])->toBe(2);
    expect($summary['kpi']['days_without_valid_window'])->toBe(0);

    expect($summary['hourly'])->toHaveCount(24);
    expect($summary['daily'])->toHaveCount(2);
    expect($summary['queue']['min_remaining'])->toBe(12);
    expect($summary['queue']['avg_remaining'])->toBe(16.0);
    expect($summary['total'])->toBe(['cages_tipped' => 100, 'cages_out' => 90, 'days' => 2]);

    // Read-only by construction.
    expect(CagesTrackRecord::count())->toBe($recordsBefore);
    expect(CagesTippedTime::count())->toBe($detailsBefore);
});
