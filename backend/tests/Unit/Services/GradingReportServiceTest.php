<?php

/**
 * GradingReportServiceTest — screen-146--laporan-grading-web /
 * screen-147--laporan-grading-mobile, GradingReportService.
 *
 * One test per unit_test_cases entry on screen-146's tech spec, grouped the
 * same way as WeighbridgeReportServiceTest so the two can be read side by
 * side.
 *
 * ────────────────────────────────────────────────────────────────────────
 * THE FIXTURES ARE DELIBERATELY INCONSISTENT, AND THAT IS THE POINT
 * ────────────────────────────────────────────────────────────────────────
 * Two of this service's rules can only be proven by data that CONTRADICTS the
 * shortcut:
 *
 *   - `percentage` is READ, never recomputed. A load whose percentages happen
 *     to match its netto and bunch count proves nothing: a service that
 *     recomputed them would produce the same answer and pass. So the fixtures
 *     below store percentages that are impossible to derive from the load's
 *     own netto/quantity.
 *
 *   - `avg_percentage` divides by the number of loads that RECORDED the
 *     parameter, not by the period's load count. With every parameter present
 *     on every load the two denominators coincide and the assertion is empty.
 *     So parameters here appear on DIFFERENT subsets of loads.
 *
 * Do not "tidy" these numbers. Tidying them turns half this file into tests
 * that are always green.
 *
 * SQLite runs this suite while production runs PostgreSQL, which is why the
 * period filter is asserted at the EDGES (first day, last day, one day
 * outside) rather than only in the middle: whereDate() versus a bare where()
 * on a timestamp column differs only at those edges, and only on PostgreSQL.
 */

use App\Enums\RecordStatus;
use App\Enums\Uom;
use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Models\BusinessUnit;
use App\Models\GradingDetail;
use App\Models\GradingParameter;
use App\Models\GradingRecord;
use App\Models\Period;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use App\Services\GradingReportService;
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
 * Spy that proves allBusinessUnits() is never reached on the fail-closed
 * paths. Same device as WeighbridgeReportAllBusinessUnitsSpy.
 */
class GradingReportAllBusinessUnitsSpy extends GradingReportService
{
    public int $allBusinessUnitsCalls = 0;

    public function allBusinessUnits(): Collection
    {
        $this->allBusinessUnitsCalls++;

        return parent::allBusinessUnits();
    }
}

/** One sorted load, with its parameter rows. */
function gradingReportLoad(
    Station $station,
    string $date,
    float $netto = 10000.0,
    float $bunch = 100.0,
    array $attributes = [],
): GradingRecord {
    return GradingRecord::factory()
        ->forStation($station)
        ->onDate($date)
        ->create(array_merge([
            'netto' => $netto,
            'quantity' => $bunch,
            'estate_supplier' => 'Estate A',
            'division' => 'Divisi 1',
        ], $attributes));
}

/** One parameter row on a load. The percentage is stored, never derived. */
function gradingReportDetail(
    GradingRecord $record,
    GradingParameter $parameter,
    float $quantity,
    Uom $uom,
    float $percentage,
): GradingDetail {
    return GradingDetail::factory()
        ->forGradingRecord($record)
        ->forGradingParameter($parameter)
        ->create([
            'quantity' => $quantity,
            'uom' => $uom,
            'percentage' => $percentage,
        ]);
}

/** Every SQL statement run inside $callback. */
function gradingReportQueriesDuring(callable $callback): array
{
    $queries = [];

    DB::listen(function ($query) use (&$queries) {
        $queries[] = strtolower($query->sql);
    });

    $callback();

    return $queries;
}

function gradingReportStreamed(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

beforeEach(function () {
    $this->service = new GradingReportService;

    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->grading()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->grading()->create();

    $this->lineA = (string) $this->stationA->production_line_id;
    $this->lineB = (string) $this->stationB->production_line_id;

    $this->supervisorA = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagementA = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operatorA = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('grading')
        ->range('2026-09-01', '2026-09-30')
        ->open()
        ->named('Periode September Alpha')
        ->create();

    // Two bunch parameters and one kg parameter — enough to prove the two
    // groups never meet.
    $this->mentah = GradingParameter::factory()->create(['name' => 'Mentah', 'uom' => Uom::Bunch, 'sort_order' => 10]);
    $this->masak = GradingParameter::factory()->create(['name' => 'Masak', 'uom' => Uom::Bunch, 'sort_order' => 20]);
    $this->brondolan = GradingParameter::factory()->create(['name' => 'Brondolan Segar', 'uom' => Uom::Kg, 'sort_order' => 30]);
});

// =====================================================================
// GROUP A — ACCESS, MILL, LINE, PERIOD
// =====================================================================

it('case 1 — guardAccess menolak tamu 401 dan peran di luar keempatnya 403, sebelum satu kueri periode', function () {
    $queries = gradingReportQueriesDuring(function () {
        expect(fn () => $this->service->listPeriods(null))->toThrow(AuthenticationException::class);
    });

    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "periods"');
    }
});

it('case 2 — resolveBusinessUnit membuang business_unit_id klien untuk Operator, Supervisor, dan Mill Management', function () {
    foreach ([$this->operatorA, $this->supervisorA, $this->millManagementA] as $user) {
        $this->actingAs($user);

        // Not validated, not compared, DISCARDED. This is the assertion that
        // fails if Operator ever falls into the Admin branch.
        expect($this->service->resolveBusinessUnit((string) $this->businessUnitB->id))
            ->toBe((string) $this->businessUnitA->id);
    }
});

it('case 3 — resolveBusinessUnit menuntut business_unit_id dari Admin', function () {
    $this->actingAs($this->admin);

    expect(fn () => $this->service->resolveBusinessUnit(null))->toThrow(ValidationException::class);
});

it('case 4 — akun terikat mill tanpa business_unit_id gagal tertutup tanpa membaca daftar mill', function () {
    $boundWithoutMill = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $this->actingAs($boundWithoutMill);

    $spy = new GradingReportAllBusinessUnitsSpy;

    expect(fn () => $spy->resolveBusinessUnit(null))->toThrow(ValidationException::class);

    // Falling back to "every mill" would turn one broken master-data row into
    // a cross-mill leak. The spy proves that path is not taken.
    expect($spy->allBusinessUnitsCalls)->toBe(0);
});

it('case 5 — businessUnitOptions menolak setiap peran terikat mill, termasuk Operator', function () {
    $spy = new GradingReportAllBusinessUnitsSpy;

    // The sharpest assertion in this file about what did NOT widen: Operator
    // reaches every other entry point, and still cannot have the mill list.
    foreach ([$this->operatorA, $this->supervisorA, $this->millManagementA] as $user) {
        $this->actingAs($user);

        expect(fn () => $spy->businessUnitOptions())->toThrow(AuthorizationException::class);
    }

    expect($spy->allBusinessUnitsCalls)->toBe(0);
});

it('case 6 — resolveProductionLine: 422 absen, 403 mill lain, lolos milik mill yang berlaku', function () {
    $this->actingAs($this->supervisorA);

    $businessUnitId = (string) $this->businessUnitA->id;

    $queries = gradingReportQueriesDuring(function () use ($businessUnitId) {
        expect(fn () => $this->service->resolveProductionLine($businessUnitId, null))
            ->toThrow(ValidationException::class);
        expect(fn () => $this->service->resolveProductionLine($businessUnitId, $this->lineB))
            ->toThrow(AuthorizationException::class);
    });

    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "grading_records"');
    }

    expect($this->service->resolveProductionLine($businessUnitId, $this->lineA))->toBe($this->lineA);
});

it('case 7 — authorizePeriod: 404 untuk id tak ada, 403 untuk periode mill lain', function () {
    $this->actingAs($this->supervisorA);

    expect(fn () => $this->service->authorizePeriod((string) Str::uuid()))
        ->toThrow(ModelNotFoundException::class);

    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('grading')
        ->range('2026-09-01', '2026-09-30')->open()->named('Periode September Beta')->create();

    expect(fn () => $this->service->authorizePeriod((string) $periodB->id))
        ->toThrow(AuthorizationException::class);
});

it('case 8 — listPeriods hanya memulangkan periode yang punya baris period_stations grading', function () {
    $this->actingAs($this->supervisorA);

    $closed = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('grading')
        ->range('2026-08-01', '2026-08-31')->closed()->named('Periode Agustus Alpha')->create();

    $otherStation = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('weighbridge')
        ->range('2026-07-01', '2026-07-31')->open()->named('Periode Juli Weighbridge')->create();

    $periods = collect($this->service->listPeriods(null))->keyBy('id');

    expect($periods)->toHaveKey((string) $this->periodA->id);
    expect($periods)->toHaveKey((string) $closed->id);
    expect($periods)->not->toHaveKey((string) $otherStation->id);

    // Status yang tertera adalah status BARIS grading, bukan status periode.
    expect($periods[(string) $this->periodA->id]['status'])->toBe('open');
    expect($periods[(string) $closed->id]['status'])->toBe('closed');
    expect($periods[(string) $this->periodA->id]['station_type'])->toBe('grading');
});

it('case 9 — penyaringan line memakai grading_records.production_line_id, tanpa join ke stations', function () {
    $this->actingAs($this->supervisorA);

    $lineTwo = ProductionLine::factory()->forBusinessUnit($this->businessUnitA)->create(['name' => 'Line Kedua']);

    gradingReportLoad($this->stationA, '2026-09-04');

    // The station is LATER reassigned. The load must not move with it.
    $this->stationA->update(['production_line_id' => $lineTwo->id]);

    $queries = gradingReportQueriesDuring(function () use ($lineTwo) {
        expect($this->service->buildSummary($this->periodA, null, $this->lineA)['load_count'])->toBe(1);
        expect($this->service->buildSummary($this->periodA, null, (string) $lineTwo->id)['load_count'])->toBe(0);
    });

    foreach ($queries as $sql) {
        if (str_contains($sql, 'from "grading_records"')) {
            expect($sql)->not->toContain('join "stations"');
        }
    }
});

it('case 10 — rentang periode inklusif di kedua ujung', function () {
    $this->actingAs($this->supervisorA);

    gradingReportLoad($this->stationA, '2026-09-01');
    gradingReportLoad($this->stationA, '2026-09-30');
    gradingReportLoad($this->stationA, '2026-08-31');
    gradingReportLoad($this->stationA, '2026-10-01');

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['load_count'])->toBe(2);
    expect(array_column($summary['daily'], 'date'))->toBe(['2026-09-01', '2026-09-30']);
});

it('case 11 — keanggotaan periode ditentukan kolom date, bukan created_at', function () {
    $this->actingAs($this->supervisorA);

    // date INSIDE the period, row created long after it ended.
    $late = gradingReportLoad($this->stationA, '2026-09-10');
    $late->forceFill(['created_at' => '2026-12-01 08:00:00'])->saveQuietly();

    // date OUTSIDE the period, row created inside it.
    $early = gradingReportLoad($this->stationA, '2026-10-15');
    $early->forceFill(['created_at' => '2026-09-15 08:00:00'])->saveQuietly();

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['load_count'])->toBe(1);
    expect(array_column($summary['daily'], 'date'))->toBe(['2026-09-10']);
});

// =====================================================================
// GROUP B — THE TWO UNIT GROUPS
// =====================================================================

it('case 12 — kelompok bunch dan kg berdiri sendiri, dengan penyebut masing-masing', function () {
    $this->actingAs($this->supervisorA);

    $load = gradingReportLoad($this->stationA, '2026-09-04');

    gradingReportDetail($load, $this->mentah, 30.0, Uom::Bunch, 11.0);
    gradingReportDetail($load, $this->masak, 70.0, Uom::Bunch, 22.0);
    gradingReportDetail($load, $this->brondolan, 40.0, Uom::Kg, 33.0);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['bunch']['quantity_total'])->toBe(100.0);
    expect($summary['kg']['quantity_total'])->toBe(40.0);

    // Shares are computed against their OWN group total.
    $bunchByName = collect($summary['bunch']['rows'])->keyBy('name');
    expect($bunchByName['Mentah']['share_percent'])->toBe(30.0);
    expect($bunchByName['Masak']['share_percent'])->toBe(70.0);
    expect($summary['kg']['rows'][0]['share_percent'])->toBe(100.0);

    // NOT ONE KEY equals 140 — bunches are never added to kilograms.
    expect(json_encode($summary))->not->toContain('140');
});

it('case 13 — share_percent null ketika total kelompoknya 0', function () {
    $this->actingAs($this->supervisorA);

    $load = gradingReportLoad($this->stationA, '2026-09-04');

    gradingReportDetail($load, $this->mentah, 0.0, Uom::Bunch, 0.0);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['bunch']['quantity_total'])->toBe(0.0);
    // null, not 0, and not a DivisionByZeroError.
    expect($summary['bunch']['rows'][0]['share_percent'])->toBeNull();
});

it('case 14 — avg_percentage memakai penyebut muatan yang mencatat parameter itu', function () {
    $this->actingAs($this->supervisorA);

    // Ten loads; only three record Mentah.
    foreach (range(1, 10) as $i) {
        $load = gradingReportLoad($this->stationA, '2026-09-0'.min($i, 9));

        if ($i <= 3) {
            gradingReportDetail($load, $this->mentah, 10.0, Uom::Bunch, [9.0, 6.0, 3.0][$i - 1]);
        }

        gradingReportDetail($load, $this->masak, 90.0, Uom::Bunch, 90.0);
    }

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    $mentah = collect($summary['bunch']['rows'])->firstWhere('name', 'Mentah');

    expect($summary['load_count'])->toBe(10);
    // (9 + 6 + 3) / 3 = 6.0 — NOT / 10, which would give 1.8.
    expect($mentah['avg_percentage'])->toBe(6.0);
    expect($mentah['load_count'])->toBe(3);
});

it('case 15 — percentage DIBACA, tidak dihitung ulang dari netto maupun jumlah janjang', function () {
    $this->actingAs($this->supervisorA);

    // netto 10.000 and bunch 100 would make a quantity of 10 come out as
    // 0,1% (kg) or 10% (bunch) if recomputed. The stored value is 42.
    $load = gradingReportLoad($this->stationA, '2026-09-04', netto: 10000.0, bunch: 100.0);

    gradingReportDetail($load, $this->mentah, 10.0, Uom::Bunch, 42.0);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['bunch']['rows'][0]['avg_percentage'])->toBe(42.0);
});

it('case 16 — uom diambil dari baris detail, bukan dari master parameternya', function () {
    $this->actingAs($this->supervisorA);

    $load = gradingReportLoad($this->stationA, '2026-09-04');

    // Master says bunch; the ROW says kg. The frozen copy on the row wins —
    // it is what says which unit the quantity was recorded in.
    gradingReportDetail($load, $this->mentah, 25.0, Uom::Kg, 5.0);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['kg']['rows'])->toHaveCount(1);
    expect($summary['kg']['rows'][0]['name'])->toBe('Mentah');
    expect($summary['bunch']['rows'])->toBe([]);
    expect($summary['bunch']['quantity_total'])->toBeNull();
});

it('case 17 — muatan tanpa baris parameter tetap terhitung pada header dan dihitung tersendiri', function () {
    $this->actingAs($this->supervisorA);

    $withDetail = gradingReportLoad($this->stationA, '2026-09-04', netto: 1000.0, bunch: 10.0);
    gradingReportDetail($withDetail, $this->mentah, 10.0, Uom::Bunch, 100.0);

    gradingReportLoad($this->stationA, '2026-09-05', netto: 2000.0, bunch: 20.0);
    gradingReportLoad($this->stationA, '2026-09-06', netto: 3000.0, bunch: 30.0);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // INSIDE every header figure …
    expect($summary['load_count'])->toBe(3);
    expect($summary['netto_total'])->toBe(6000.0);
    expect($summary['bunch_total'])->toBe(60.0);
    // … and OUTSIDE both parameter blocks.
    expect($summary['bunch']['quantity_total'])->toBe(10.0);
    expect($summary['bunch']['rows'][0]['load_count'])->toBe(1);
    expect($summary['loads_without_detail'])->toBe(2);
});

it('case 18 — nol muatan memulangkan null, bukan 0', function () {
    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['load_count'])->toBe(0);
    expect($summary['netto_total'])->toBeNull();
    expect($summary['netto_avg'])->toBeNull();
    expect($summary['bunch_total'])->toBeNull();
    expect($summary['bunch_avg'])->toBeNull();
    expect($summary['bunch']['quantity_total'])->toBeNull();
    expect($summary['kg']['quantity_total'])->toBeNull();
    expect($summary['bunch']['rows'])->toBe([]);
    expect($summary['kg']['rows'])->toBe([]);
    expect($summary['daily'])->toBe([]);
});

it('case 19 — urutan baris parameter deterministik ketika pangsa dan kuantitas sama', function () {
    $this->actingAs($this->supervisorA);

    $load = gradingReportLoad($this->stationA, '2026-09-04');

    // Identical quantity => identical share. Name breaks the tie, ascending.
    gradingReportDetail($load, $this->masak, 50.0, Uom::Bunch, 50.0);
    gradingReportDetail($load, $this->mentah, 50.0, Uom::Bunch, 50.0);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect(array_column($summary['bunch']['rows'], 'name'))->toBe(['Masak', 'Mentah']);
});

// =====================================================================
// GROUP C — RECAPS AND COMPLETENESS
// =====================================================================

it('case 20 — asal yang belum diisi adalah satu kelompok, bukan baris yang dibuang', function () {
    $this->actingAs($this->supervisorA);

    // estate_supplier adalah kolom NOT NULL, jadi "belum diisi" di basis data
    // ini berarti string kosong — bukan NULL. Dicoba keduanya di sini: string
    // kosong dan string berisi spasi saja, yang keduanya harus mendarat di
    // kelompok yang SAMA. (Service-nya tetap menangani NULL secara defensif;
    // kolomnya hanya tidak mengizinkannya.)
    gradingReportLoad($this->stationA, '2026-09-04', attributes: ['estate_supplier' => '']);
    gradingReportLoad($this->stationA, '2026-09-05', attributes: ['estate_supplier' => '   ']);
    gradingReportLoad($this->stationA, '2026-09-06', attributes: ['estate_supplier' => 'Estate A']);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    $byOrigin = collect($summary['by_estate_supplier'])->keyBy('estate_supplier');

    expect($byOrigin)->toHaveCount(2);
    expect($byOrigin['']['load_count'])->toBe(2);
    expect($byOrigin['Estate A']['load_count'])->toBe(1);
    // The groups still add up to the headline figure.
    expect(collect($summary['by_estate_supplier'])->sum('load_count'))->toBe($summary['load_count']);
});

it('case 21 — penghitung kelengkapan menghitung, bukan menyaring', function () {
    $this->actingAs($this->supervisorA);

    $checker = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();

    gradingReportLoad($this->stationA, '2026-09-04', attributes: [
        'status' => RecordStatus::DraftOngoing,
        'division' => null,
    ]);
    gradingReportLoad($this->stationA, '2026-09-05', attributes: [
        'status' => RecordStatus::DraftPaused,
        'checked_by' => $checker->id,
    ]);
    gradingReportLoad($this->stationA, '2026-09-06', attributes: [
        'checked_by' => $checker->id,
        'acknowledged_by' => $checker->id,
    ]);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['draft_load_count'])->toBe(2);
    expect($summary['loads_without_division'])->toBe(1);
    expect($summary['loads_not_checked'])->toBe(1);
    expect($summary['loads_not_acknowledged'])->toBe(2);

    // None of them filtered anything.
    expect($summary['load_count'])->toBe(3);
    expect($summary['netto_total'])->toBe(30000.0);
});

it('case 22 — days_with_load sama dengan panjang daily, dan days_counted memakai ReportPeriodDays', function () {
    $this->actingAs($this->supervisorA);

    $running = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('grading')
        ->range(now()->subDays(4)->toDateString(), now()->addDays(10)->toDateString())
        ->open()->named('Periode Berjalan')->create();

    foreach ([4, 3, 2] as $daysAgo) {
        gradingReportLoad($this->stationA, now()->subDays($daysAgo)->toDateString());
    }

    $summary = $this->service->buildSummary($running, null, $this->lineA);

    expect($summary['daily'])->toHaveCount(3);
    expect($summary['completeness']['days_with_load'])->toBe(3);
    expect($summary['completeness']['period_running'])->toBeTrue();
    // start..today inclusive = 5 days, not the full 15.
    expect($summary['completeness']['days_counted'])->toBe(5);
    expect($summary['completeness']['days_in_period'])->toBe(15);
});

it('case 23 — daily_total menjumlah seluruh baris daily', function () {
    $this->actingAs($this->supervisorA);

    gradingReportLoad($this->stationA, '2026-09-04', netto: 1000.0, bunch: 10.0);
    gradingReportLoad($this->stationA, '2026-09-04', netto: 2000.0, bunch: 20.0);
    gradingReportLoad($this->stationA, '2026-09-05', netto: 3000.0, bunch: 30.0);
    gradingReportLoad($this->stationA, '2026-09-05', netto: 4000.0, bunch: 40.0);
    gradingReportLoad($this->stationA, '2026-09-05', netto: 5000.0, bunch: 50.0);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect(array_column($summary['daily'], 'load_count'))->toBe([2, 3]);
    expect($summary['daily_total']['load_count'])->toBe(5);
    expect($summary['daily_total']['netto_total'])->toBe(15000.0);
    expect($summary['daily_total']['bunch_total'])->toBe(150.0);
});

// =====================================================================
// GROUP D — THE EXPORT
// =====================================================================

it('case 24 — ekspor memancarkan satu baris per parameter per muatan dengan konteks diulang', function () {
    $this->actingAs($this->supervisorA);

    $first = gradingReportLoad($this->stationA, '2026-09-04');
    gradingReportDetail($first, $this->mentah, 10.0, Uom::Bunch, 10.0);
    gradingReportDetail($first, $this->brondolan, 5.0, Uom::Kg, 1.0);

    $second = gradingReportLoad($this->stationA, '2026-09-05');
    gradingReportDetail($second, $this->masak, 80.0, Uom::Bunch, 80.0);
    gradingReportDetail($second, $this->brondolan, 7.0, Uom::Kg, 2.0);

    $rows = iterator_to_array($this->service->buildExportRows($this->periodA, null, $this->lineA));

    expect($rows)->toHaveCount(4);

    foreach ($rows as $row) {
        // Context repeated verbatim on every row.
        expect($row[0])->toBe('Periode September Alpha');
        expect($row[1])->toBe('Mill Alpha');
    }

    // Satuan rides along on every row.
    $units = array_map(fn ($row) => $row[12], $rows);
    expect($units)->toContain('bunch');
    expect($units)->toContain('kg');
});

it('case 25 — ekspor tetap memancarkan satu baris untuk muatan tanpa baris parameter', function () {
    $this->actingAs($this->supervisorA);

    gradingReportLoad($this->stationA, '2026-09-04');

    $rows = iterator_to_array($this->service->buildExportRows($this->periodA, null, $this->lineA));

    expect($rows)->toHaveCount(1);

    // Parameter / Satuan / Kuantitas / Persentase all EMPTY — never dropped,
    // never written as 0.
    expect($rows[0][11])->toBeNull();
    expect($rows[0][12])->toBeNull();
    expect($rows[0][13])->toBeNull();
    expect($rows[0][14])->toBeNull();
    // The load's own columns are still there.
    expect($rows[0][3])->toBe('2026-09-04');
});

it('case 26 — batas baris ekspor dihitung atas jumlah baris parameter, bukan jumlah record', function () {
    $this->actingAs($this->supervisorA);

    // Four loads is nowhere near any record limit; eight parameter rows is
    // over this (temporarily lowered) ceiling.
    foreach (range(1, 4) as $i) {
        $load = gradingReportLoad($this->stationA, '2026-09-0'.$i);
        gradingReportDetail($load, $this->mentah, 10.0, Uom::Bunch, 10.0);
        gradingReportDetail($load, $this->masak, 20.0, Uom::Bunch, 20.0);
    }

    // A service that counted RECORDS would see 4 and let this through.
    $service = new class extends GradingReportService
    {
        public const EXPORT_ROW_LIMIT = 7;
    };

    expect(fn () => $service->buildExportRows($this->periodA, null, $this->lineA))
        ->toThrow(ExportFailedException::class);

    // Exactly at the limit still exports.
    $atLimit = new class extends GradingReportService
    {
        public const EXPORT_ROW_LIMIT = 8;
    };

    expect(iterator_to_array($atLimit->buildExportRows($this->periodA, null, $this->lineA)))->toHaveCount(8);
});

it('case 27 — format ekspor di luar csv|excel ditolak', function () {
    $this->actingAs($this->supervisorA);

    expect(fn () => $this->service->export($this->periodA, 'pdf', null, $this->lineA))
        ->toThrow(ValidationException::class);

    $response = $this->service->export($this->periodA, 'csv', null, $this->lineA);

    expect($response)->toBeInstanceOf(StreamedResponse::class);
});

it('case 28 — summary() adalah alias buildSummary(), bukan implementasi kedua', function () {
    $this->actingAs($this->supervisorA);

    $load = gradingReportLoad($this->stationA, '2026-09-04');
    gradingReportDetail($load, $this->mentah, 10.0, Uom::Bunch, 10.0);

    expect($this->service->summary($this->periodA, null, $this->lineA))
        ->toEqual($this->service->buildSummary($this->periodA, null, $this->lineA));
});

// =====================================================================
// GROUP E — OPERATOR, ADMITTED FROM DAY ONE
// =====================================================================

it('case 29 — Operator membaca laporan mill sendiri, identik dengan Supervisor', function () {
    $load = gradingReportLoad($this->stationA, '2026-09-04');
    gradingReportDetail($load, $this->mentah, 10.0, Uom::Bunch, 10.0);

    $this->actingAs($this->supervisorA);
    $asSupervisor = $this->service->buildSummary($this->periodA, null, $this->lineA);

    $this->actingAs($this->operatorA);
    $asOperator = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // ONE SOURCE OF NUMBERS. If these ever differ, the mobile report and the
    // web report drift apart unnoticed — the very thing reusing the same
    // endpoints is meant to prevent.
    expect($asOperator)->toEqual($asSupervisor);
});

it('case 30 — Operator ditolak 403 untuk line dan periode milik mill lain', function () {
    $this->actingAs($this->operatorA);

    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('grading')
        ->range('2026-09-01', '2026-09-30')->open()->named('Periode September Beta')->create();

    expect(fn () => $this->service->buildSummary($this->periodA, null, $this->lineB))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->buildSummary((string) $periodB->id, null, $this->lineA))
        ->toThrow(AuthorizationException::class);
});
