<?php

/**
 * SterilizerReportServiceTest — screen-129--laporan-sterilizer-web /
 * usecase-129--laporan-sterilizer-web (Laporan Periode Sterilizer).
 *
 * One test per unit_test_case in the screen's tech spec (37 cases, in the
 * spec's own order), against App\Services\SterilizerReportService.
 * Mirrors ManagementReportServiceTest / SterilizerRecordServiceTest's
 * pragmatic RefreshDatabase + factory approach.
 *
 * `uses(TestCase::class, RefreshDatabase::class)` is MANDATORY here:
 * tests/Pest.php binds Tests\TestCase only to Feature/, so without this
 * line the Unit suite has no application container — auth()->user(),
 * Eloquent and the faker formats the factories rely on would all blow up.
 *
 * TWO DIFFERENT CROSS-MILL GUARDS ARE ASSERTED SEPARATELY, on purpose:
 *   - resolveBusinessUnit() IGNORES the client's business_unit_id for
 *     Supervisor / Mill Management (own mill's data, NOT a 403 — a 403
 *     would confirm the other mill exists);
 *   - authorizePeriod() REFUSES another mill's period_id with 403.
 * Collapsing those two into one assertion would hide whichever one broke.
 */

use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\Station;
use App\Models\SterilizerDetail;
use App\Models\SterilizerRecord;
use App\Models\User;
use App\Services\SterilizerReportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * One Sterilizer cycle with all six triple-peak times filled and a
 * duration — the "healthy" baseline every case below deviates from by
 * overriding only the columns it is actually about.
 */
function sterilizerReportCycle(array $overrides = []): array
{
    return array_merge([
        'sterilizer_no' => '1',
        'close_door_time' => '07:00',
        'peak_1_time' => '07:15',
        'exhaust_1_time' => '07:20',
        'peak_2_time' => '07:35',
        'exhaust_2_time' => '07:40',
        'peak_3_time' => '07:55',
        'exhaust_3_time' => '08:00',
        'open_door_time' => '08:30',
        'duration_minutes' => 90,
        'number_of_cages' => 10,
        'cages_status' => 'Baik',
        'checked_by_spv' => true,
        'remarks' => null,
    ], $overrides);
}

/**
 * One sterilizer_records header on $date for $station, carrying one
 * sterilizer_details row per entry of $cycles.
 */
function sterilizerReportRecord(Station $station, string $date, array $cycles = [], array $recordOverrides = []): SterilizerRecord
{
    $record = SterilizerRecord::factory()->forStation($station)->onDate($date)->create($recordOverrides);

    foreach ($cycles as $cycle) {
        SterilizerDetail::factory()->forRecord($record)->create(sterilizerReportCycle($cycle));
    }

    return $record;
}

/** Durations only — the shape most aggregation cases need. */
function sterilizerReportDurations(Station $station, string $date, array $durations): SterilizerRecord
{
    return sterilizerReportRecord($station, $date, array_map(
        fn ($duration) => ['duration_minutes' => $duration],
        $durations,
    ));
}

beforeEach(function () {
    $this->service = new SterilizerReportService;

    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->sterilizer()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->sterilizer()->create();

    $this->supervisorA = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagementA = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operatorA = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    // Admin is the one role not bound to a mill — users.business_unit_id
    // is NULL, which is exactly why the mill picker exists.
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->range('2026-09-01', '2026-09-30')
        ->open()
        ->named('Periode September A')
        ->create();
});

// ---------------------------------------------------------------------
// resolveBusinessUnit() — cases 1-4
// ---------------------------------------------------------------------

// Case 1
it('resolveBusinessUnit ignores the query business_unit_id for Supervisor', function () {
    $this->actingAs($this->supervisorA);

    expect($this->service->resolveBusinessUnit($this->businessUnitB->id))
        ->toBe((string) $this->businessUnitA->id);
});

// Case 2
it('resolveBusinessUnit ignores the query business_unit_id for Mill Management', function () {
    $this->actingAs($this->millManagementA);

    expect($this->service->resolveBusinessUnit($this->businessUnitB->id))
        ->toBe((string) $this->businessUnitA->id);
});

// Case 3
it('resolveBusinessUnit uses the query business_unit_id for Admin', function () {
    $this->actingAs($this->admin);

    expect($this->service->resolveBusinessUnit((string) $this->businessUnitB->id))
        ->toBe((string) $this->businessUnitB->id);
});

// Case 4
it('resolveBusinessUnit throws 422 VALIDATION_ERROR with errors.business_unit_id when Admin sends no mill', function () {
    $this->actingAs($this->admin);

    $exception = null;

    try {
        $this->service->resolveBusinessUnit(null);
    } catch (ValidationException $e) {
        $exception = $e;
    }

    // Deliberately not a silent empty result: an Admin who forgot the mill
    // must be told, not shown an empty report.
    expect($exception)->not->toBeNull();
    expect($exception->status)->toBe(422);
    expect($exception->errors())->toHaveKey('business_unit_id');
});

// ---------------------------------------------------------------------
// businessUnitOptions() — cases 5-6
// ---------------------------------------------------------------------

// Case 5
it('businessUnitOptions refuses a non-Admin actor with 403 FORBIDDEN', function () {
    $this->actingAs($this->supervisorA);

    expect(fn () => $this->service->businessUnitOptions())->toThrow(AuthorizationException::class);
});

// Case 6
it('businessUnitOptions returns the mill list for Admin', function () {
    BusinessUnit::factory()->create(['name' => 'Mill Gamma']);
    $this->actingAs($this->admin);

    $options = $this->service->businessUnitOptions();

    // Every mill in the instance is offered (the factories behind the
    // fixtures create a couple of incidental ones), shaped {id, name} and
    // ordered by name.
    expect($options)->toHaveCount(BusinessUnit::count());
    expect(array_keys($options[0]))->toBe(['id', 'name']);
    expect(collect($options)->pluck('name')->intersect(['Mill Alpha', 'Mill Beta', 'Mill Gamma'])->values()->all())
        ->toBe(['Mill Alpha', 'Mill Beta', 'Mill Gamma']);
});

// ---------------------------------------------------------------------
// listPeriods() — cases 7-12
// ---------------------------------------------------------------------

// Case 7
it('listPeriods includes a period whose station_type is sterilizer', function () {
    $this->actingAs($this->supervisorA);

    $ids = collect($this->service->listPeriods($this->businessUnitA->id))->pluck('id')->all();

    expect($ids)->toContain((string) $this->periodA->id);
});

// Case 8
it('listPeriods includes a period that covers every station type, reported AS the sterilizer pair', function () {
    // stationType(null) tidak lagi berarti `station_type` NULL — kolom itu
    // hilang 2026-09-25. Cakupan semua-stasiun kini berarti SATU BARIS
    // period_stations per jenis stasiun, termasuk satu untuk sterilizer, dan
    // baris itulah alasan periode ini terpungut.
    $allTypes = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(null)
        ->range('2026-08-01', '2026-08-31')
        ->create();

    $this->actingAs($this->supervisorA);

    $option = collect($this->service->listPeriods($this->businessUnitA->id))
        ->firstWhere('id', (string) $allTypes->id);

    expect($option)->not->toBeNull();

    // Label 'Semua Stasiun' lenyap bersama station_type NULL: opsi ini adalah
    // pasangan (periode, sterilizer), jadi jenis stasiun yang dilaporkan
    // selalu jenis stasiun layar ini — dan tidak pernah null lagi.
    expect($option['station_type'])->toBe('sterilizer');
    expect($option['station_type_label'])->toBe('Sterilizer');
});

// Case 9
it('listPeriods excludes a period scoped to another station type', function () {
    $boilerRoom = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('boiler-room')
        ->range('2026-07-01', '2026-07-31')
        ->create();

    $this->actingAs($this->supervisorA);

    $ids = collect($this->service->listPeriods($this->businessUnitA->id))->pluck('id')->all();

    expect($ids)->not->toContain((string) $boilerRoom->id);
});

// Case 9b — BARU 2026-09-26, bersama pemisahan periods/period_stations.
// Inilah perilaku yang DULU dijamin cabang orWhereNull('station_type') dan
// kini sengaja dibuang: tanpa baris period_stations untuk sterilizer, sebuah
// periode bukan periode Sterilizer — sekalipun ia milik mill yang sama, dan
// sekalipun ia mengelola jenis stasiun lain.
it('listPeriods does NOT pick up a period with no period_stations row for sterilizer', function () {
    $otherTypesOnly = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationTypes(['boiler-room', 'clarification'])
        ->range('2026-07-01', '2026-07-31')
        ->create();

    // Induk tanpa satu pun baris anak — dulu mustahil (station_type NULL
    // justru berarti "semua"), kini berarti "tidak ada stasiun yang dikelola".
    $noStations = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->noStations()
        ->range('2026-06-01', '2026-06-30')
        ->create();

    $this->actingAs($this->supervisorA);

    $ids = collect($this->service->listPeriods($this->businessUnitA->id))->pluck('id')->all();

    expect($ids)->not->toContain((string) $otherTypesOnly->id);
    expect($ids)->not->toContain((string) $noStations->id);
});

// Case 9c — BARU 2026-09-26. Inti pemisahan model ini, dan bentuk yang
// sebelumnya MUSTAHIL diuji: satu periode dengan dua jenis stasiun berstatus
// berbeda. Laporan Sterilizer harus melaporkan status Sterilizer, bukan status
// stasiun lain yang kebetulan ada di periode yang sama.
it('reports the sterilizer status, not another station type status in the same period', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->noStations()
        ->range('2026-09-01', '2026-09-30')
        ->named('Periode Campuran')
        ->create();

    PeriodStation::factory()->forPeriod($period)->stationType('sterilizer')->open()->create();
    PeriodStation::factory()->forPeriod($period)->stationType('boiler-room')->closed()->create();

    $this->actingAs($this->supervisorA);

    $option = collect($this->service->listPeriods($this->businessUnitA->id))
        ->firstWhere('id', (string) $period->id);

    expect($option['status'])->toBe('open');

    // Header summary() memakai jalur yang sama, tanpa relasi ter-eager-load.
    expect($this->service->summary($period)['period']['status'])->toBe('open');

    // Dan sebaliknya: Sterilizer tertutup sementara Boiler Room terbuka.
    $period->stations()->where('station_type', 'sterilizer')->delete();
    $period->stations()->where('station_type', 'boiler-room')->delete();
    PeriodStation::factory()->forPeriod($period)->stationType('sterilizer')->closed()->create();
    PeriodStation::factory()->forPeriod($period)->stationType('boiler-room')->open()->create();

    expect($this->service->summary($period->fresh())['period']['status'])->toBe('closed');
});

// Case 10
it('listPeriods orders the result by start_date descending', function () {
    $this->periodA->forceFill(['start_date' => '2026-07-01', 'end_date' => '2026-07-31'])->save();

    Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('sterilizer')
        ->range('2026-09-01', '2026-09-30')->create();
    Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('sterilizer')
        ->range('2026-08-01', '2026-08-31')->create();

    $this->actingAs($this->supervisorA);

    $starts = collect($this->service->listPeriods($this->businessUnitA->id))->pluck('start_date')->all();

    expect($starts)->toBe(['2026-09-01', '2026-08-01', '2026-07-01']);
});

// Case 11
it('listPeriods returns an empty array when the mill has no period covering Sterilizer', function () {
    $this->periodA->delete();
    Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('boiler-room')
        ->range('2026-09-01', '2026-09-30')->create();

    $this->actingAs($this->supervisorA);

    // [] is a valid answer — an empty picker plus a UI hint, never a 404.
    expect($this->service->listPeriods($this->businessUnitA->id))->toBe([]);
});

// Case 12
it('listPeriods only returns periods of the resolved business unit', function () {
    $otherMillPeriod = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('sterilizer')
        ->range('2026-09-01', '2026-09-30')->create();

    $this->actingAs($this->supervisorA);

    $ids = collect($this->service->listPeriods($this->businessUnitA->id))->pluck('id')->all();

    expect($ids)->toContain((string) $this->periodA->id);
    expect($ids)->not->toContain((string) $otherMillPeriod->id);
});

// ---------------------------------------------------------------------
// authorizePeriod() — cases 13-17
// ---------------------------------------------------------------------

// Case 13
it('authorizePeriod throws 404 NOT_FOUND when the period id does not exist', function () {
    $this->actingAs($this->supervisorA);

    expect(fn () => $this->service->authorizePeriod((string) Str::uuid()))
        ->toThrow(ModelNotFoundException::class);
});

// Case 14
it('authorizePeriod throws 403 FORBIDDEN when a Supervisor opens another mill\'s period', function () {
    $otherMillPeriod = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('sterilizer')
        ->range('2026-09-01', '2026-09-30')->create();

    $this->actingAs($this->supervisorA);

    // THIS is the real leak path — unlike the ignored business_unit_id
    // query param, a period id is a concrete handle to another mill's data.
    expect(fn () => $this->service->authorizePeriod((string) $otherMillPeriod->id))
        ->toThrow(AuthorizationException::class);
});

// Case 15
it('authorizePeriod throws 403 FORBIDDEN when Mill Management opens another mill\'s period', function () {
    $otherMillPeriod = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('sterilizer')
        ->range('2026-09-01', '2026-09-30')->create();

    $this->actingAs($this->millManagementA);

    expect(fn () => $this->service->authorizePeriod((string) $otherMillPeriod->id))
        ->toThrow(AuthorizationException::class);
});

// Case 16 — rewritten 2026-09-23 for screen-135 (Laporan Sterilizer Mobile).
//
// This case used to assert that an Operator was refused even on their own
// mill's period, back when Operator had no report access at all. That is no
// longer the product decision: the people who key the data in are entitled
// to read it back, so the four /api/sterilizer-reports/* endpoints now admit
// Operator. The WEB route /reports/sterilizer still does not — Operator has
// no web UI — and e2e-web/tests/laporan-sterilizer.spec.ts pins that down.
//
// The mill-scoping guarantee is unchanged and is what the second half below
// protects: Operator passes for its OWN mill and is still refused outright
// for another mill's period, exactly like Supervisor and Mill Management.
// A period id is a concrete handle to another mill's data, so this is the
// real cross-mill leak path — widening the role must never widen that.
it('authorizePeriod lets an Operator through for their own mill\'s period', function () {
    $this->actingAs($this->operatorA);

    $period = $this->service->authorizePeriod((string) $this->periodA->id);

    expect($period->id)->toBe($this->periodA->id);
});

it('authorizePeriod still throws 403 FORBIDDEN when an Operator opens another mill\'s period', function () {
    $otherMillPeriod = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('sterilizer')
        ->range('2026-09-01', '2026-09-30')->create();

    $this->actingAs($this->operatorA);

    expect(fn () => $this->service->authorizePeriod((string) $otherMillPeriod->id))
        ->toThrow(AuthorizationException::class);
});

// Case 17
it('authorizePeriod lets Admin through for any mill\'s period', function () {
    $otherMillPeriod = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('sterilizer')
        ->range('2026-09-01', '2026-09-30')->create();

    $this->actingAs($this->admin);

    $period = $this->service->authorizePeriod((string) $otherMillPeriod->id);

    expect($period->id)->toBe($otherMillPeriod->id);
});

// ---------------------------------------------------------------------
// summary() — date range — cases 18-21
// ---------------------------------------------------------------------

// Case 18
it('summary includes cycles exactly on start_date and exactly on end_date (inclusive on both bounds)', function () {
    sterilizerReportDurations($this->stationA, '2026-09-01', [90]);
    sterilizerReportDurations($this->stationA, '2026-09-30', [95]);

    $summary = $this->service->summary($this->periodA);

    expect($summary['kpi']['total_cycles'])->toBe(2);
    expect(collect($summary['daily'])->pluck('date')->all())->toBe(['2026-09-01', '2026-09-30']);
});

// Case 19
it('summary excludes cycles one day before start_date and one day after end_date', function () {
    sterilizerReportDurations($this->stationA, '2026-08-31', [90]);
    sterilizerReportDurations($this->stationA, '2026-10-01', [90]);

    $summary = $this->service->summary($this->periodA);

    expect($summary['kpi']['total_cycles'])->toBe(0);
    expect($summary['daily'])->toBe([]);
});

// Case 20
it('summary filters on sterilizer_records.date, not created_at', function () {
    // Record A: inside the period, synced late (created_at after the
    // period ended) — still belongs to the period it happened in.
    $insideButLateSync = sterilizerReportDurations($this->stationA, '2026-09-15', [90]);
    DB::table('sterilizer_records')->where('id', $insideButLateSync->id)
        ->update(['created_at' => '2026-10-05 08:00:00']);

    // Record B: outside the period, entered during it — must not count.
    $outsideButCreatedInside = sterilizerReportDurations($this->stationA, '2026-08-20', [100]);
    DB::table('sterilizer_records')->where('id', $outsideButCreatedInside->id)
        ->update(['created_at' => '2026-09-10 08:00:00']);

    $summary = $this->service->summary($this->periodA);

    expect($summary['kpi']['total_cycles'])->toBe(1);
    expect($summary['kpi']['avg_duration_minutes'])->toBe(90.0);
    expect(collect($summary['daily'])->pluck('date')->all())->toBe(['2026-09-15']);
});

// Case 21
it('summary only counts stations of the period\'s business unit whose type is sterilizer', function () {
    $otherMillStation = Station::factory()->forBusinessUnit($this->businessUnitB)->sterilizer()->create();
    $sameMillBoilerRoom = Station::factory()->forBusinessUnit($this->businessUnitA)->boilerRoom()->create();

    sterilizerReportDurations($otherMillStation, '2026-09-10', [90]);
    sterilizerReportDurations($sameMillBoilerRoom, '2026-09-10', [90]);
    sterilizerReportDurations($this->stationA, '2026-09-10', [120]);

    $summary = $this->service->summary($this->periodA);

    expect($summary['kpi']['total_cycles'])->toBe(1);
    expect($summary['kpi']['avg_duration_minutes'])->toBe(120.0);
});

// ---------------------------------------------------------------------
// summary() — KPI — cases 22-28
// ---------------------------------------------------------------------

// Case 22
it('summary counts total_cycles from detail rows and total_cages from SUM(number_of_cages)', function () {
    sterilizerReportRecord($this->stationA, '2026-09-10', [
        ['number_of_cages' => 10],
        ['number_of_cages' => 12],
        ['number_of_cages' => 8],
    ]);

    $summary = $this->service->summary($this->periodA);

    expect($summary['kpi']['total_cycles'])->toBe(3);
    expect($summary['kpi']['total_cages'])->toBe(30);
    expect($summary['total']['cycles'])->toBe(3);
    expect($summary['total']['cages'])->toBe(30);
});

// Case 23
it('summary excludes cycles without a duration from avg/min/max and reports how many were excluded', function () {
    sterilizerReportDurations($this->stationA, '2026-09-10', [90, 100, 110, null, null]);

    $summary = $this->service->summary($this->periodA);

    expect($summary['kpi']['total_cycles'])->toBe(5);
    expect($summary['kpi']['cycles_without_duration'])->toBe(2);
    expect($summary['kpi']['avg_duration_minutes'])->toBe(100.0);
    expect($summary['kpi']['min_duration_minutes'])->toBe(90);
    expect($summary['kpi']['max_duration_minutes'])->toBe(110);
});

// Case 24
it('summary returns null avg/min/max when no cycle has a duration at all', function () {
    sterilizerReportDurations($this->stationA, '2026-09-10', [null, null, null, null]);

    $summary = $this->service->summary($this->periodA);

    // null, NOT 0 — a 0 would be indistinguishable from a real
    // zero-minute duration.
    expect($summary['kpi']['avg_duration_minutes'])->toBeNull();
    expect($summary['kpi']['min_duration_minutes'])->toBeNull();
    expect($summary['kpi']['max_duration_minutes'])->toBeNull();
    expect($summary['kpi']['cycles_without_duration'])->toBe(4);
    expect($summary['kpi']['total_cycles'])->toBe(4);
});

// Case 25
it('summary reports 100% triple-peak compliance when all six times are filled on every cycle', function () {
    sterilizerReportRecord($this->stationA, '2026-09-10', [[], [], [], []]);

    $summary = $this->service->summary($this->periodA);

    expect($summary['kpi']['triple_peak_compliance_percent'])->toBe(100.0);
});

// Case 26
it('summary drops triple-peak compliance to 75% when one cycle misses exhaust_3_time', function () {
    sterilizerReportRecord($this->stationA, '2026-09-10', [
        [],
        [],
        [],
        ['exhaust_3_time' => null],
    ]);

    $summary = $this->service->summary($this->periodA);

    // No partial credit: a cycle that skipped an exhaust did not follow
    // the boiling pattern at all.
    expect($summary['kpi']['triple_peak_compliance_percent'])->toBe(75.0);
});

// Case 27
it('summary reports 0% triple-peak compliance for an empty period without dividing by zero', function () {
    $summary = $this->service->summary($this->periodA);

    expect($summary['kpi']['total_cycles'])->toBe(0);
    expect($summary['kpi']['triple_peak_compliance_percent'])->toBe(0.0);
});

// Case 28
it('summary returns zeroed KPI and empty collections for a period with no Sterilizer data', function () {
    $summary = $this->service->summary($this->periodA);

    expect($summary['kpi']['total_cycles'])->toBe(0);
    expect($summary['kpi']['total_cages'])->toBe(0);
    expect($summary['kpi']['cycles_without_duration'])->toBe(0);
    expect($summary['kpi']['avg_duration_minutes'])->toBeNull();
    expect($summary['kpi']['min_duration_minutes'])->toBeNull();
    expect($summary['kpi']['max_duration_minutes'])->toBeNull();
    expect($summary['kpi']['triple_peak_compliance_percent'])->toBe(0.0);
    expect($summary['daily'])->toBe([]);
    expect($summary['by_unit'])->toBe([]);
    expect($summary['outliers']['items'])->toBe([]);
    expect($summary['period']['id'])->toBe((string) $this->periodA->id);
});

// ---------------------------------------------------------------------
// summary() — outliers — cases 29-31
// ---------------------------------------------------------------------

// Case 29
it('outliers flags insufficient_data when fewer than 8 cycles have a duration', function () {
    sterilizerReportDurations($this->stationA, '2026-09-10', [88, 90, 91, 92, 93, 94, 95, null, null]);

    $outliers = $this->service->summary($this->periodA)['outliers'];

    expect($outliers['insufficient_data'])->toBeTrue();
    expect($outliers['sample_size'])->toBe(7);
    expect($outliers['min_sample_size'])->toBe(8);
    expect($outliers['lower_bound'])->toBeNull();
    expect($outliers['upper_bound'])->toBeNull();
    expect($outliers['items'])->toBe([]);
});

// Case 30
it('outliers computes the Tukey fence and flags the cycles outside it once the sample reaches 8', function () {
    sterilizerReportRecord($this->stationA, '2026-09-10', array_map(
        fn ($duration) => ['duration_minutes' => $duration, 'sterilizer_no' => '2', 'number_of_cages' => 11, 'cages_status' => 'Baik'],
        [88, 90, 91, 92, 93, 94, 95, 96, 97, 200],
    ));

    $outliers = $this->service->summary($this->periodA)['outliers'];

    // Q1 = 91.25, Q3 = 95.75, IQR = 4.5 -> 84.5 .. 102.5 (percentile by
    // linear interpolation at index p*(n-1), same as PERCENTILE.INC).
    expect($outliers['method'])->toBe('iqr');
    expect($outliers['insufficient_data'])->toBeFalse();
    expect($outliers['sample_size'])->toBe(10);
    expect($outliers['lower_bound'])->toBe(84.5);
    expect($outliers['upper_bound'])->toBe(102.5);
    expect($outliers['items'])->toHaveCount(1);
    expect($outliers['items'][0]['duration_minutes'])->toBe(200);
    expect($outliers['items'][0]['date'])->toBe('2026-09-10');
    expect($outliers['items'][0]['sterilizer_no'])->toBe('2');
    expect($outliers['items'][0]['number_of_cages'])->toBe(11);
    expect($outliers['items'][0]['cages_status'])->toBe('Baik');
});

// Case 31
it('outliers returns empty items but real bounds when every duration is identical', function () {
    sterilizerReportDurations($this->stationA, '2026-09-10', array_fill(0, 10, 95));

    $outliers = $this->service->summary($this->periodA)['outliers'];

    // IQR = 0, so the fence collapses onto the value itself. The bounds
    // are still returned so the screen can state the threshold it applied
    // instead of rendering an unexplained empty card.
    expect($outliers['insufficient_data'])->toBeFalse();
    expect($outliers['lower_bound'])->toBe(95.0);
    expect($outliers['upper_bound'])->toBe(95.0);
    expect($outliers['items'])->toBe([]);
});

// ---------------------------------------------------------------------
// summary() — daily / by_unit — cases 32-33
// ---------------------------------------------------------------------

// Case 32
it('daily groups by sterilizer_records.date and only lists dates that actually have cycles', function () {
    $period = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('sterilizer')
        ->range('2026-10-01', '2026-10-05')->create();

    sterilizerReportDurations($this->stationA, '2026-10-02', [90, 95]);
    sterilizerReportDurations($this->stationA, '2026-10-04', [100]);

    $daily = $this->service->summary($period)['daily'];

    // No zero-padded rows: an empty bar would read as "we measured
    // nothing" rather than "the mill did not run".
    expect($daily)->toHaveCount(2);
    expect($daily[0]['date'])->toBe('2026-10-02');
    expect($daily[0]['cycles'])->toBe(2);
    expect($daily[1]['date'])->toBe('2026-10-04');
    expect($daily[1]['cycles'])->toBe(1);
});

// Case 33
it('by_unit groups per sterilizer_no with cycles, cages and its own avg_duration', function () {
    sterilizerReportRecord($this->stationA, '2026-09-10', [
        ['sterilizer_no' => '1', 'number_of_cages' => 12, 'duration_minutes' => 90],
        ['sterilizer_no' => '1', 'number_of_cages' => 8, 'duration_minutes' => null],
        ['sterilizer_no' => '2', 'number_of_cages' => 9, 'duration_minutes' => 100],
    ]);

    $byUnit = collect($this->service->summary($this->periodA)['by_unit'])->keyBy('sterilizer_no');

    expect($byUnit)->toHaveCount(2);
    expect($byUnit['1']['cycles'])->toBe(2);
    expect($byUnit['1']['cages'])->toBe(20);
    // 90 only — the cycle without a duration does not drag this down.
    expect($byUnit['1']['avg_duration'])->toBe(90.0);
    expect($byUnit['2']['cycles'])->toBe(1);
    expect($byUnit['2']['cages'])->toBe(9);
    expect($byUnit['2']['avg_duration'])->toBe(100.0);
});

// ---------------------------------------------------------------------
// export() — cases 34-36
// ---------------------------------------------------------------------

// Case 34
it('export writes one line per CYCLE with the record context columns repeated on each line', function () {
    // DISERAGAMKAN 2026-09-25: export() kini memanggil resolveBusinessUnit()
    // seperti ketiga service laporan lain. Sebelumnya jalur ekspor Sterilizer
    // tidak punya penjaga otorisasi sama sekali — bukan hanya tanpa validasi
    // mill, tetapi tanpa guardAccess() juga — sehingga test ini tidak perlu
    // login. Celah itu kini tertutup, jadi fixture-nya ikut login.
    $this->actingAs($this->supervisorA);

    sterilizerReportRecord($this->stationA, '2026-09-10', [
        ['sterilizer_no' => '1', 'duration_minutes' => 90],
        ['sterilizer_no' => '2', 'duration_minutes' => 95],
        ['sterilizer_no' => '3', 'duration_minutes' => 100],
    ], [
        'sterilizer_id' => 'STR-EXPORT-001',
        'note' => 'Catatan harian',
    ]);

    $response = $this->service->export($this->periodA, 'csv');

    expect($response)->toBeInstanceOf(StreamedResponse::class);
    expect($response->headers->get('Content-Type'))->toBe('text/csv');

    ob_start();
    $response->sendContent();
    $body = ob_get_clean();

    $lines = array_values(array_filter(explode("\n", trim($body))));

    expect($lines)->toHaveCount(4); // header + 3 cycles
    // Judul kolom Bahasa Indonesia + konteks Periode/Mill/Line (temuan
    // audit 2026-10-04 #8a/#8b).
    expect(str_getcsv($lines[0], ',', '"', '\\'))->toBe(SterilizerReportService::EXPORT_HEADER);
    expect($lines[0])->not->toContain('Sterilizer ID');

    foreach (array_slice($lines, 1) as $line) {
        // Context columns repeated verbatim on every cycle line, so the
        // file can be pivoted straight in a spreadsheet.
        expect($line)->toContain('STR-EXPORT-001');
        expect($line)->toContain('2026-09-10');
        expect($line)->toContain('Catatan harian');
    }

    expect($lines[1])->toContain('90');
    expect($lines[2])->toContain('95');
    expect($lines[3])->toContain('100');
});

// Case 35
it('export throws 422 EXPORT_FAILED when the number of CYCLE lines exceeds 50.000', function () {
    // DISERAGAMKAN 2026-09-25: export() kini memanggil resolveBusinessUnit()
    // seperti ketiga service laporan lain. Sebelumnya jalur ekspor Sterilizer
    // tidak punya penjaga otorisasi sama sekali — bukan hanya tanpa validasi
    // mill, tetapi tanpa guardAccess() juga — sehingga test ini tidak perlu
    // login. Celah itu kini tertutup, jadi fixture-nya ikut login.
    $this->actingAs($this->supervisorA);

    // The ceiling counts CYCLES, not header records — one daily record can
    // carry a dozen cycles, which is exactly the trap commit 8611974 fixed
    // for the 17-station export. Two headers, 50.001 cycles.
    $first = sterilizerReportRecord($this->stationA, '2026-09-10');
    $second = sterilizerReportRecord($this->stationA, '2026-09-11');

    $now = now()->toDateTimeString();
    $total = SterilizerReportService::EXPORT_ROW_LIMIT + 1;

    for ($offset = 0; $offset < $total; $offset += 5000) {
        $rows = [];

        for ($i = $offset; $i < min($offset + 5000, $total); $i++) {
            $rows[] = [
                'id' => (string) Str::uuid(),
                'sterilizer_record_id' => $i % 2 === 0 ? $first->id : $second->id,
                'sterilizer_no' => '1',
                'duration_minutes' => 90,
                'number_of_cages' => 10,
                'checked_by_spv' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('sterilizer_details')->insert($rows);
    }

    expect(SterilizerRecord::count())->toBe(2);

    expect(fn () => $this->service->export($this->periodA, 'csv'))
        ->toThrow(ExportFailedException::class);
});

// Case 36
it('export still succeeds for a period whose status is closed', function () {
    // DISERAGAMKAN 2026-09-25: export() kini memanggil resolveBusinessUnit()
    // seperti ketiga service laporan lain. Sebelumnya jalur ekspor Sterilizer
    // tidak punya penjaga otorisasi sama sekali — bukan hanya tanpa validasi
    // mill, tetapi tanpa guardAccess() juga — sehingga test ini tidak perlu
    // login. Celah itu kini tertutup, jadi fixture-nya ikut login.
    $this->actingAs($this->supervisorA);

    $closed = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('sterilizer')
        ->range('2026-11-01', '2026-11-30')->closed()->create();

    sterilizerReportDurations($this->stationA, '2026-11-10', [90, 95]);

    $response = $this->service->export($closed, 'csv');

    ob_start();
    $response->sendContent();
    $body = ob_get_clean();

    // Status is a caption, not a gate: it limits neither the report nor
    // the export.
    expect($response)->toBeInstanceOf(StreamedResponse::class);
    expect(array_values(array_filter(explode("\n", trim($body)))))->toHaveCount(3);
});

// ---------------------------------------------------------------------
// Case 37 — full success path
// ---------------------------------------------------------------------

it('returns success result when all conditions pass', function () {
    $this->actingAs($this->supervisorA);

    // 12 cycles inside the period, 10 of them with a duration.
    sterilizerReportRecord($this->stationA, '2026-09-05', array_map(
        fn ($duration) => ['sterilizer_no' => '1', 'duration_minutes' => $duration, 'number_of_cages' => 10],
        [88, 90, 91, 92, 93],
    ));
    sterilizerReportRecord($this->stationA, '2026-09-12', array_map(
        fn ($duration) => ['sterilizer_no' => '2', 'duration_minutes' => $duration, 'number_of_cages' => 12],
        [94, 95, 96, 97, 200, null, null],
    ));

    $period = $this->service->authorizePeriod((string) $this->periodA->id);
    $summary = $this->service->summary($period);

    expect(array_keys($summary))->toBe(['period', 'production_line', 'kpi', 'daily', 'by_unit', 'outliers', 'total']);
    expect($summary['period']['status'])->toBe('open');
    expect($summary['period']['business_unit_name'])->toBe('Mill Alpha');
    expect($summary['kpi']['total_cycles'])->toBe(12);
    expect($summary['kpi']['cycles_without_duration'])->toBe(2);
    expect($summary['kpi']['total_cages'])->toBe(134);
    expect($summary['daily'])->toHaveCount(2);
    expect($summary['by_unit'])->toHaveCount(2);
    expect($summary['outliers']['method'])->toBe('iqr');
    expect($summary['outliers']['insufficient_data'])->toBeFalse();
    expect($summary['outliers']['sample_size'])->toBe(10);
    expect($summary['outliers']['lower_bound'])->toBe(84.5);
    expect($summary['outliers']['upper_bound'])->toBe(102.5);
    expect($summary['outliers']['items'])->toHaveCount(1);
    expect($summary['total']['cycles'])->toBe(12);
});
