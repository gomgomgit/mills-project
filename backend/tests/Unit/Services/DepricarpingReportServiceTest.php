<?php

/**
 * DepricarpingReportServiceTest — screen-152--laporan-depricarping-web /
 * screen-153--laporan-depricarping-mobile, DepricarpingReportService.
 *
 * One test per unit_test_cases entry on screen-152's tech spec, grouped the
 * same way as BoilerRoomReportServiceTest and GradingReportServiceTest so
 * the three can be read side by side.
 *
 * ────────────────────────────────────────────────────────────────────────
 * THE FIXTURES ARE DELIBERATELY UNEVEN, AND THAT IS THE POINT
 * ────────────────────────────────────────────────────────────────────────
 * Three of this service's rules can only be proven by data that CONTRADICTS
 * the shortcut:
 *
 *   - EVERY METRIC HAS ITS OWN DENOMINATOR. With every column filled on
 *     every slot, one shared denominator gives the same answer and the
 *     assertion is empty. So the columns below are filled on DIFFERENT
 *     subsets of slots — 10 slots of throughput against 3 of drum speed.
 *
 *   - daily_total IS RECOMPUTED, not an average of the daily averages. A
 *     fixture with equal slot counts per day cannot tell the two apart, so
 *     one day carries ten slots and the next carries one.
 *
 *   - A SLOT CARRYING ONLY findings IS STILL FILLED. If every slot
 *     with a reason also carried a measurement, this rule would never be
 *     exercised.
 *
 * Do not "tidy" these numbers. Tidying them turns half this file into tests
 * that are always green.
 *
 * SQLite runs this suite while production runs PostgreSQL, which is why the
 * period filter is asserted at the EDGES (first day, last day, one day
 * outside) rather than only in the middle: whereDate() versus a bare where()
 * differs only at those edges, and only on PostgreSQL.
 */

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\DepricarpingDetail;
use App\Models\DepricarpingOperationalTarget;
use App\Models\DepricarpingRecord;
use App\Models\User;
use App\Enums\StationType as StationTypeEnum;
use App\Services\DepricarpingRecordService;
use App\Services\DepricarpingReportService;
use App\Services\StationReportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Spy that proves allBusinessUnits() is never reached on the fail-closed
 * paths. Same device as GradingReportAllBusinessUnitsSpy.
 */
class DepricarpingReportAllBusinessUnitsSpy extends DepricarpingReportService
{
    public int $allBusinessUnitsCalls = 0;

    public function allBusinessUnits(): Collection
    {
        $this->allBusinessUnitsCalls++;

        return parent::allBusinessUnits();
    }
}

/**
 * Lowers the export ceiling so the row-vs-record distinction can be proven
 * without seeding 50.000 rows. Only possible because the service reads the
 * limit through `static::`, never `self::` — that choice is what this
 * subclass exists to exercise.
 */
class DepricarpingReportTinyExportService extends DepricarpingReportService
{
    public const EXPORT_ROW_LIMIT = 5;
}

/** One daily record for one presser. */
function depricarpingReportRecord(
    Station $station,
    string $date,
    string $presserId = 'PR-1',
    array $attributes = [],
): DepricarpingRecord {
    return DepricarpingRecord::factory()
        ->forStation($station)
        ->onDate($date)
        ->create(array_merge([
            'presser_id' => $presserId,
            'note' => 'Catatan harian',
        ], $attributes));
}

/** One time-slot row. Every measurement column defaults to null. */
function depricarpingReportSlot(
    DepricarpingRecord $record,
    string $timeSlot,
    array $values = [],
): DepricarpingDetail {
    return DepricarpingDetail::factory()
        ->forRecord($record)
        ->timeSlot($timeSlot)
        ->create($values);
}

/** The six seeded master rows, matching DepricarpingOperationalTargetSeeder. */
function depricarpingReportSeedTargets(): void
{
    // ENAM baris master, persis seperti DepricarpingOperationalTargetSeeder,
    // dan masternya membawa EMPAT kolom — satu lebih banyak daripada master
    // Threshing maupun Pressing (operational_consequence_justification).
    //
    // ENAM parameter untuk TUJUH kolom ukur, dan ketimpangannya berjalan ke
    // DUA arah sekaligus:
    //   - 'Nut Silo Temperature' mengatur DUA kolom (nut_silo_1_temp_c dan
    //     nut_silo_2_temp_c), sehingga enam entri peta hanya menyebut LIMA
    //     parameter berbeda;
    //   - 'Kernel Loss in Fibre' TIDAK dipetakan ke kolom mana pun, walau
    //     kernel_recovery_in_fibre_percent ada: master menyebutnya kehilangan
    //     ('< 0.50%'), kolom dan keempat layar input melabelinya perolehan.
    $rows = [
        ['Fan Static Pressure', '40 - 50 mmH2O', '< 35 or > 55 mmH2O', 'Low pressure drops fibre early (heavy losses). High pressure sucks clean small nuts into the fibre cyclone.'],
        ['Polishing Drum Speed', '20 - 24 RPM', '< 18 or > 26 RPM', 'Slower speeds fail to detach residual mesocarp fibre from nuts. Higher speeds cause premature mechanical wear.'],
        ['Air Velocity (Aspirator)', '12 - 14 m/s', '< 10 or > 16 m/s', 'Controls the pneumatic separation gap. Must cleanly lift light fiber hulls while letting heavy polished nuts sink.'],
        ['Fibre Moisture Content', '33% - 37%', '> 40%', 'High moisture reduces downstream boiler combustion efficiency and indicates poor press station performance.'],
        ['Kernel Loss in Fibre', '< 0.50%', '> 1.00%', 'Direct operational revenue loss. Signifies an unstable pneumatic lifting balance or unstripped cake clumps.'],
        ['Nut Silo Temperature', '60C - 70C', '< 55C or > 75C', 'Crucial for nut conditioning. Correct heat shrinks the kernel inside the shell, enabling high-efficiency cracking.'],
    ];

    foreach ($rows as $index => [$parameter, $range, $limit, $justification]) {
        DepricarpingOperationalTarget::create([
            'parameter_metric' => $parameter,
            'target_range' => $range,
            'critical_limit' => $limit,
            'operational_consequence_justification' => $justification,
            'sort_order' => $index + 1,
        ]);
    }
}

/** Every SQL statement run inside $callback. */
function depricarpingReportQueriesDuring(callable $callback): array
{
    $queries = [];

    DB::listen(function ($query) use (&$queries) {
        $queries[] = strtolower($query->sql);
    });

    $callback();

    return $queries;
}

function depricarpingReportStreamed(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

/** The metric entry for one column, from the metrics list. */
function depricarpingReportMetric(array $summary, string $column): array
{
    foreach ($summary['metrics'] as $metric) {
        if ($metric['column'] === $column) {
            return $metric;
        }
    }

    throw new RuntimeException("metric {$column} tidak ada pada payload");
}

beforeEach(function () {
    $this->service = new DepricarpingReportService;

    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->depricarping()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->depricarping()->create();

    $this->lineA = (string) $this->stationA->production_line_id;
    $this->lineB = (string) $this->stationB->production_line_id;

    $this->supervisorA = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagementA = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operatorA = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    // A period that is already OVER, so days_counted == days_in_period and
    // the coverage denominator is deterministic. The running-period case has
    // its own test.
    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('depricarping')
        ->range('2026-09-01', '2026-09-10')
        ->open()
        ->named('Periode September Alpha')
        ->create();
});

// =====================================================================
// GROUP A — ACCESS, MILL, LINE, PERIOD
// =====================================================================

it('case 1 — guardAccess menolak tamu 401 dan peran di luar keempatnya 403, sebelum satu kueri periode', function () {
    $queries = depricarpingReportQueriesDuring(function () {
        expect(fn () => $this->service->listPeriods(null))->toThrow(AuthenticationException::class);
    });

    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "periods"');
    }
});

it('case 2 — resolveBusinessUnit membuang business_unit_id klien untuk Operator, Supervisor, dan Mill Management', function () {
    foreach ([$this->operatorA, $this->supervisorA, $this->millManagementA] as $user) {
        $this->actingAs($user);

        // Not validated, not compared, DISCARDED. THIS is the assertion that
        // fails if Operator ever falls into the Admin branch — where the
        // client's business_unit_id IS honoured.
        expect($this->service->resolveBusinessUnit((string) $this->businessUnitB->id))
            ->toBe((string) $this->businessUnitA->id);
    }
});

it('case 3 — resolveBusinessUnit menuntut business_unit_id dari Admin', function () {
    $this->actingAs($this->admin);

    expect(fn () => $this->service->resolveBusinessUnit(null))->toThrow(ValidationException::class);
    expect(fn () => $this->service->resolveBusinessUnit(''))->toThrow(ValidationException::class);
});

it('case 4 — akun terikat mill tanpa business_unit_id gagal tertutup tanpa membaca daftar mill', function () {
    $boundWithoutMill = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $this->actingAs($boundWithoutMill);

    $spy = new DepricarpingReportAllBusinessUnitsSpy;

    expect(fn () => $spy->resolveBusinessUnit(null))->toThrow(ValidationException::class);

    // Falling back to "every mill" would turn one broken master-data row into
    // a cross-mill leak. The spy proves that path is not taken.
    expect($spy->allBusinessUnitsCalls)->toBe(0);
});

it('case 5 — businessUnitOptions menolak setiap peran terikat mill, termasuk Operator', function () {
    $spy = new DepricarpingReportAllBusinessUnitsSpy;

    // The sharpest assertion in this file about what is NOT open: Operator
    // reaches every other entry point, and still cannot have the mill list.
    foreach ([$this->operatorA, $this->supervisorA, $this->millManagementA] as $user) {
        $this->actingAs($user);

        expect(fn () => $spy->businessUnitOptions())->toThrow(AuthorizationException::class);
    }

    expect($spy->allBusinessUnitsCalls)->toBe(0);

    $this->actingAs($this->admin);

    // Mill yang terbentuk dari factory stasiun ikut terdaftar, jadi yang
    // diasersi adalah KEHADIRAN kedua mill uji — bukan panjang daftarnya.
    $names = collect($spy->businessUnitOptions())->pluck('name')->all();

    expect($names)->toContain('Mill Alpha');
    expect($names)->toContain('Mill Beta');
});

it('case 6 — listPeriods menyaring menurut baris period_stations station_type depricarping', function () {
    Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->range('2026-08-01', '2026-08-31')
        ->open()
        ->named('Periode Sterilizer Saja')
        ->create();

    $this->actingAs($this->supervisorA);

    $periods = $this->service->listPeriods(null);

    expect($periods)->toHaveCount(1);
    expect($periods[0]['name'])->toBe('Periode September Alpha');
    expect($periods[0]['station_type'])->toBe('depricarping');
});

it('case 7 — listPeriods memakai status dari baris period_stations, bukan kolom status periode', function () {
    $this->actingAs($this->supervisorA);

    expect($this->service->listPeriods(null)[0]['status'])->toBe('open');
});

it('case 8 — listPeriods tetap mencantumkan periode tertutup', function () {
    Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('depricarping')
        ->range('2026-08-01', '2026-08-31')
        ->closed()
        ->named('Periode Agustus Tertutup')
        ->create();

    $this->actingAs($this->supervisorA);

    $statuses = collect($this->service->listPeriods(null))->pluck('status')->all();

    // Status does not filter: reading a closed period is always allowed —
    // the period lock governs WRITING data.
    expect($statuses)->toContain('closed');
    expect($this->service->listPeriods(null))->toHaveCount(2);
});

it('case 9 — requirePeriod menolak ketiadaan period_id dengan 422, bukan 404', function () {
    $this->actingAs($this->supervisorA);

    expect(fn () => $this->service->buildSummary(null, null, $this->lineA))
        ->toThrow(ValidationException::class);
    expect(fn () => $this->service->buildSummary('', null, $this->lineA))
        ->toThrow(ValidationException::class);
});

it('case 10 — resolveProductionLine memulangkan null untuk line mill lain dan untuk ketiadaan pilihan', function () {
    $this->actingAs($this->supervisorA);

    $businessUnitId = (string) $this->businessUnitA->id;

    expect($this->service->resolveProductionLine($businessUnitId, null))->toBeNull();
    expect($this->service->resolveProductionLine($businessUnitId, ''))->toBeNull();
    // Line mill lain: null, bukan line itu — dan bukan exception, karena di
    // layar "belum memilih" adalah keadaan normal. Penolakan 422/403 untuk
    // jalur API ada di controller.
    expect($this->service->resolveProductionLine($businessUnitId, $this->lineB))->toBeNull();
    expect($this->service->resolveProductionLine($businessUnitId, $this->lineA))->toBe($this->lineA);
});

it('case 11 — authorizePeriod menolak periode mill lain dengan 403 dan periode tak ada dengan 404', function () {
    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType('depricarping')
        ->range('2026-09-01', '2026-09-30')
        ->open()
        ->create();

    $this->actingAs($this->supervisorA);

    expect(fn () => $this->service->authorizePeriod((string) $periodB->id))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->authorizePeriod((string) Str::uuid()))
        ->toThrow(ModelNotFoundException::class);
});

it('case 12 — Admin lolos authorizePeriod untuk mill mana pun', function () {
    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType('depricarping')
        ->range('2026-09-01', '2026-09-30')
        ->open()
        ->create();

    $this->actingAs($this->admin);

    expect($this->service->authorizePeriod((string) $periodB->id)->id)->toBe($periodB->id);
});

// =====================================================================
// GROUP B — RECORD SCOPE
// =====================================================================

it('case 13 — penyaringan line memakai kolom record, bukan join ke stations', function () {
    // Satu stasiun, yang KINI terdaftar di lineA. Satu record lama dicatat
    // ketika stasiun itu masih di line lain, dan kolom record-nya menunjuk
    // line lama itu — justru itu yang benar: data dihasilkan di sana.
    $otherLine = ProductionLine::factory()->create(['business_unit_id' => $this->businessUnitA->id]);

    $kept = depricarpingReportRecord($this->stationA, '2026-09-03', 'PR-1');
    $moved = depricarpingReportRecord($this->stationA, '2026-09-03', 'PR-2', [
        'production_line_id' => $otherLine->id,
    ]);

    depricarpingReportSlot($kept, '07:00', ['fan_static_pressure_mmh2o' => 30.0]);
    depricarpingReportSlot($moved, '07:00', ['fan_static_pressure_mmh2o' => 99.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['coverage']['filled_slots'])->toBe(1);
    expect(depricarpingReportMetric($summary, 'fan_static_pressure_mmh2o')['avg'])->toBe(30.0);
    // Menyaring lewat join ke stations akan menarik record yang dipindah dan
    // menjawab 64,5 — rata-rata dua line sekaligus.
    expect(depricarpingReportMetric($summary, 'fan_static_pressure_mmh2o')['max'])->toBe(30.0);
});

it('case 14 — keanggotaan periode inklusif di kedua ujung dan menolak satu hari di luarnya', function () {
    $first = depricarpingReportRecord($this->stationA, '2026-09-01', 'PR-1');
    $last = depricarpingReportRecord($this->stationA, '2026-09-10', 'PR-1');
    $outside = depricarpingReportRecord($this->stationA, '2026-09-11', 'PR-1');

    depricarpingReportSlot($first, '07:00', ['fan_static_pressure_mmh2o' => 10.0]);
    depricarpingReportSlot($last, '07:00', ['fan_static_pressure_mmh2o' => 20.0]);
    depricarpingReportSlot($outside, '07:00', ['fan_static_pressure_mmh2o' => 999.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['total']['record_count'])->toBe(2);
    expect(depricarpingReportMetric($summary, 'fan_static_pressure_mmh2o')['max'])->toBe(20.0);
});

it('case 15 — baris slot yang keenam kolomnya null tidak dihitung terisi', function () {
    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');

    foreach (['07:00', '08:00', '09:00'] as $slot) {
        depricarpingReportSlot($record, $slot);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['coverage']['filled_slots'])->toBe(0);
    expect($summary['has_data'])->toBeFalse();
    // Record-nya tetap ada, dan tetap ikut penyebut cakupan.
    expect($summary['total']['record_count'])->toBe(1);
    expect($summary['coverage']['presser_count'])->toBe(1);
});

it('case 16 — baris slot yang HANYA berisi findings dihitung terisi', function () {
    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');

    depricarpingReportSlot($record, '07:00', ['findings' => 'Belt kendur']);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // Slot yang jelas disentuh operator tidak boleh dilaporkan sebagai slot
    // kosong — dan definisi "terisi" dipinjam dari layar input itu sendiri.
    expect($summary['coverage']['filled_slots'])->toBe(1);
    expect($summary['has_data'])->toBeTrue();
    // Tidak satu pun angka ukur terbentuk darinya.
    expect(depricarpingReportMetric($summary, 'fan_static_pressure_mmh2o')['avg'])->toBeNull();
    expect(depricarpingReportMetric($summary, 'fan_static_pressure_mmh2o')['filled_slot_count'])->toBe(0);
    expect($summary['findings'])->toBe([['finding' => 'Belt kendur', 'slot_count' => 1]]);
});

// =====================================================================
// GROUP C — PER-METRIC DENOMINATORS
// =====================================================================

it('case 17 — tiap kolom memakai penyebutnya sendiri, bukan satu penyebut bersama', function () {
    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');

    $slots = DepricarpingReportServiceSlots();

    // 10 slot ber-throughput; hanya 3 di antaranya ber-drum-speed.
    foreach (range(0, 9) as $index) {
        $values = ['fan_static_pressure_mmh2o' => 30.0];

        if ($index < 3) {
            // 20 + 22 + 24 = 66, rata-rata 22,0 bila dibagi 3.
            $values['polishing_drum_speed_rpm'] = 20.0 + ($index * 2);
        }

        depricarpingReportSlot($record, $slots[$index], $values);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect(depricarpingReportMetric($summary, 'fan_static_pressure_mmh2o')['filled_slot_count'])->toBe(10);
    expect(depricarpingReportMetric($summary, 'polishing_drum_speed_rpm')['filled_slot_count'])->toBe(3);
    // 66/3 = 22,0. Penyebut bersama (10) akan menjawab 6,6 — angka yang tidak
    // akan dipertanyakan siapa pun.
    expect(depricarpingReportMetric($summary, 'polishing_drum_speed_rpm')['avg'])->toBe(22.0);
});

it('case 18 — kolom tanpa satu pun nilai menghasilkan null, bukan 0, dan barisnya tetap ada', function () {
    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');

    depricarpingReportSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 30.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    $motor = depricarpingReportMetric($summary, 'air_velocity_ms');

    // Baris yang hilang terbaca sebagai "tidak ada parameter ini", padahal
    // yang benar adalah "tidak ada yang mengukurnya".
    expect($summary['metrics'])->toHaveCount(7);
    expect($motor['min'])->toBeNull();
    expect($motor['avg'])->toBeNull();
    expect($motor['max'])->toBeNull();
    expect($motor['filled_slot_count'])->toBe(0);
});

it('case 19 — kolom dengan satu nilai menghasilkan min, rata-rata, dan maks yang sama', function () {
    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');

    depricarpingReportSlot($record, '07:00', ['nut_silo_1_temp_c' => 0.42]);

    $this->actingAs($this->supervisorA);

    $metric = depricarpingReportMetric(
        $this->service->buildSummary($this->periodA, null, $this->lineA),
        'nut_silo_1_temp_c',
    );

    expect($metric['min'])->toBe(0.42);
    expect($metric['avg'])->toBe(0.42);
    expect($metric['max'])->toBe(0.42);
    // Penyebut inilah yang menjelaskan mengapa ketiganya sama.
    expect($metric['filled_slot_count'])->toBe(1);
});

it('case 20 — nilai nol dan negatif dilaporkan apa adanya', function () {
    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');

    depricarpingReportSlot($record, '07:00', ['fibre_moisture_percent' => 0.0]);
    depricarpingReportSlot($record, '08:00', ['fibre_moisture_percent' => -1.0]);

    $this->actingAs($this->supervisorA);

    $metric = depricarpingReportMetric(
        $this->service->buildSummary($this->periodA, null, $this->lineA),
        'fibre_moisture_percent',
    );

    // Laporan ini tidak menilai kewajaran angka — ia tidak punya dasar untuk
    // itu, dan membuang nilai "tidak wajar" berarti menyembunyikan justru
    // yang paling perlu dilihat.
    expect($metric['min'])->toBe(-1.0);
    expect($metric['max'])->toBe(0.0);
    expect($metric['avg'])->toBe(-0.5);
    expect($metric['filled_slot_count'])->toBe(2);
});

it('case 21 — seluruh agregasi di PHP: tidak ada agregat SQL atas kolom nullable', function () {
    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');

    depricarpingReportSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 30.0]);
    depricarpingReportSlot($record, '08:00', ['polishing_drum_speed_rpm' => 22.0]);

    $this->actingAs($this->supervisorA);

    $queries = depricarpingReportQueriesDuring(function () {
        $this->service->buildSummary($this->periodA, null, $this->lineA);
    });

    // SQLite (suite) dan PostgreSQL (produksi) berbeda perilaku pada AVG atas
    // kolom bernull, dan perbedaan itu akan lolos dari suite ini.
    foreach ($queries as $sql) {
        foreach (['avg(', 'min(', 'max(', 'sum(', 'group by'] as $forbidden) {
            expect($sql)->not->toContain($forbidden);
        }
    }
});

// =====================================================================
// GROUP D — COVERAGE
// =====================================================================

it('case 22 — expected_slots adalah presser x hari dihitung x 24 slot kanonis', function () {
    foreach (['PR-1', 'PR-2'] as $presser) {
        $record = depricarpingReportRecord($this->stationA, '2026-09-02', $presser);
        depricarpingReportSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 30.0]);
    }

    $this->actingAs($this->supervisorA);

    $coverage = $this->service->buildSummary($this->periodA, null, $this->lineA)['coverage'];

    expect($coverage['presser_count'])->toBe(2);
    expect($coverage['slots_per_presser_per_day'])->toBe(24);
    expect($coverage['days_counted'])->toBe(10);
    expect($coverage['expected_slots'])->toBe(480);
    expect($coverage['filled_slots'])->toBe(2);
});

it('case 23 — presser_count memakai presser yang muncul, bukan jumlah stasiun terdaftar', function () {
    // Empat stasiun depricarping terdaftar pada line ini; hanya dua presser
    // yang benar-benar beroperasi di periode itu.
    Station::factory()->count(3)->forBusinessUnit($this->businessUnitA)->depricarping()->create([
        'production_line_id' => $this->lineA,
    ]);

    foreach (['PR-1', 'PR-2'] as $presser) {
        $record = depricarpingReportRecord($this->stationA, '2026-09-02', $presser);
        depricarpingReportSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 30.0]);
    }

    $this->actingAs($this->supervisorA);

    // Penyebut yang dibangun dari stasiun terdaftar akan menghukum mill yang
    // memang sengaja tidak mengoperasikan sebuah presser.
    expect($this->service->buildSummary($this->periodA, null, $this->lineA)['coverage']['presser_count'])
        ->toBe(2);
});

it('case 24 — days_counted berhenti di hari ini untuk periode berjalan', function () {
    $running = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('depricarping')
        ->range(now()->subDays(4)->toDateString(), now()->addDays(10)->toDateString())
        ->open()
        ->named('Periode Berjalan')
        ->create();

    $record = depricarpingReportRecord($this->stationA, now()->subDays(1)->toDateString(), 'PR-1');
    depricarpingReportSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 30.0]);

    $this->actingAs($this->supervisorA);

    $coverage = $this->service->buildSummary($running, null, $this->lineA)['coverage'];

    expect($coverage['period_running'])->toBeTrue();
    expect($coverage['days_in_period'])->toBe(15);
    // Hari yang belum terjadi tidak mungkin tercatat, jadi tidak boleh
    // menjadi pembagi.
    expect($coverage['days_counted'])->toBeLessThan(15);
    expect($coverage['days_counted'])->toBe(5);
});

it('case 25 — coverage_percent null ketika expected_slots nol, bukan 0.0', function () {
    $notStarted = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('depricarping')
        ->range(now()->addDays(5)->toDateString(), now()->addDays(20)->toDateString())
        ->open()
        ->named('Periode Belum Mulai')
        ->create();

    $this->actingAs($this->supervisorA);

    $coverage = $this->service->buildSummary($notStarted, null, $this->lineA)['coverage'];

    expect($coverage['days_counted'])->toBe(0);
    expect($coverage['expected_slots'])->toBe(0);
    // 0% mengklaim ada yang diukur dan hasilnya nol. Perbedaan yang disengaja
    // dari BoilerRoomReportService, yang memulangkan 0.0 di titik ini.
    expect($coverage['coverage_percent'])->toBeNull();
});

it('case 26 — slots_per_presser_per_day diambil dari canonicalTimeSlots layar input', function () {
    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    depricarpingReportSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 30.0]);

    $this->actingAs($this->supervisorA);

    $coverage = $this->service->buildSummary($this->periodA, null, $this->lineA)['coverage'];

    // Satu definisi, satu jawaban: bukan konstanta terpisah yang bisa
    // menyimpang dari grid layar input.
    expect($coverage['slots_per_presser_per_day'])
        ->toBe(count(App\Services\DepricarpingRecordService::canonicalTimeSlots()));
});

// =====================================================================
// GROUP E — OPERATIONAL TARGETS (tidak ada padanannya di tujuh laporan lain)
// =====================================================================

it('case 27 — tiap kolom ukur membawa standar dan rencana tindakannya', function () {
    depricarpingReportSeedTargets();

    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    depricarpingReportSlot($record, '07:00', ['polishing_drum_speed_rpm' => 27.4]);

    $this->actingAs($this->supervisorA);

    $metric = depricarpingReportMetric(
        $this->service->buildSummary($this->periodA, null, $this->lineA),
        'polishing_drum_speed_rpm',
    );

    expect($metric['target']['parameter_metric'])->toBe('Polishing Drum Speed');
    expect($metric['target']['target_range'])->toBe('20 - 24 RPM');
    expect($metric['target']['critical_limit'])->toBe('< 18 or > 26 RPM');
    // KOLOM KEEMPAT, yang tidak ada pada master Threshing maupun Pressing.
    // Paling mudah terlupakan dan paling merugikan bila hilang: tanpa itu
    // tiga parameter yang sama-sama melewati batas tampak sama pentingnya.
    expect($metric['target']['operational_consequence_justification'])
        ->toContain('premature mechanical wear');
    // Kolom ini hanya diatur satu standar untuk dirinya sendiri.
    expect($metric['target']['shares_standard_with'])->toBe([]);
    expect($metric['target']['unmapped_reason'])->toBeNull();
});

it('case 28 — parameter master tanpa kolom ukur masuk targets_without_metric', function () {
    depricarpingReportSeedTargets();

    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    depricarpingReportSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 30.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // Enam parameter pada master, lima kolom pada formulir. Ketimpangan itu
    // DITERBITKAN: standar yang tidak pernah diukur terbaca seperti terpenuhi
    // padahal ia sekadar tidak ada.
    // TUJUH kolom ukur, ENAM parameter master. Ketimpangannya berjalan ke DUA
    // arah: satu parameter mengatur dua kolom, dan satu parameter tidak
    // dipetakan ke kolom mana pun.
    expect($summary['metrics'])->toHaveCount(7);
    // SATU, bukan dua seperti pada Pressing — dan alasannya pun berbeda
    // jenisnya.
    expect($summary['targets_without_metric'])->toHaveCount(1);

    $unmeasured = collect($summary['targets_without_metric'])->pluck('parameter_metric')->all();

    expect($unmeasured)->toBe(['Kernel Loss in Fibre']);

    // KEEMPAT kolom master ikut diterbitkan, bukan hanya namanya — DAN
    // alasannya, karena 'ada kolomnya tapi arahnya belum pasti' menuntut
    // tindakan yang berbeda dari 'tidak ada kolomnya'.
    $kernel = $summary['targets_without_metric'][0];

    expect($kernel['target_range'])->toBe('< 0.50%');
    expect($kernel['critical_limit'])->toBe('> 1.00%');
    expect($kernel['operational_consequence_justification'])->toContain('revenue loss');
    expect($kernel['reason'])->toBe(DepricarpingReportService::UNMAPPED_DIRECTION_UNRESOLVED);

    expect($summary['targets_master_empty'])->toBeFalse();
    expect($summary['all_targets_measured'])->toBeFalse();
});

it('case 29 — pemetaan memakai peta tetap: ejaan master yang diubah tidak menghapus angka', function () {
    depricarpingReportSeedTargets();

    DepricarpingOperationalTarget::query()
        ->where('parameter_metric', 'Polishing Drum Speed')
        ->update(['parameter_metric' => 'Polishing Drum Speeed']);

    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    depricarpingReportSlot($record, '07:00', ['polishing_drum_speed_rpm' => 22.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);
    $metric = depricarpingReportMetric($summary, 'polishing_drum_speed_rpm');

    // ANGKANYA UTUH — pemetaan tidak bersandar pada teks nama parameter.
    expect($metric['avg'])->toBe(22.0);
    expect($metric['filled_slot_count'])->toBe(1);
    // Standarnya terlepas, dan keterlepasan itu TERLIHAT alih-alih senyap.
    expect($metric['target']['target_range'])->toBeNull();
    // Baris yang namanya tidak dikenal lagi BERGABUNG dengan kedua parameter
    // yang memang tak terukur, jadi daftarnya menjadi tiga — dan keterlepasan
    // itu TERLIHAT alih-alih senyap.
    expect(collect($summary['targets_without_metric'])->pluck('parameter_metric')->all())
        ->toContain('Polishing Drum Speeed');
    expect($summary['targets_without_metric'])->toHaveCount(2);

    // ALASANNYA 'no_column', BUKAN 'direction_unresolved': ejaan yang diubah
    // membuat parameter itu tidak dikenal peta mana pun. Perbedaannya penting
    // — yang ini menuntut ejaan dibetulkan, yang satu lagi menuntut keputusan
    // penamaan.
    $renamed = collect($summary['targets_without_metric'])
        ->firstWhere('parameter_metric', 'Polishing Drum Speeed');

    expect($renamed['reason'])->toBe(DepricarpingReportService::UNMAPPED_NO_COLUMN);
});

it('case 30 — master target kosong tidak menghapus angka hasil ukur', function () {
    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    depricarpingReportSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 30.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['targets_master_empty'])->toBeTrue();
    expect($summary['targets_without_metric'])->toBe([]);
    expect($summary['metrics'])->toHaveCount(7);
    // Master kosong BUKAN sama dengan "semuanya terukur". Layar membaca
    // targets_master_empty lebih dulu justru karena kedua kunci ini bisa
    // sama-sama menunjuk daftar kosong untuk sebab yang berlawanan.
    expect($summary['all_targets_measured'])->toBeTrue();
    expect($summary['targets_master_empty'])->toBeTrue();
    // Seeder yang belum dijalankan tidak boleh menghapus pengukuran yang
    // sudah terjadi.
    expect(depricarpingReportMetric($summary, 'fan_static_pressure_mmh2o')['avg'])->toBe(30.0);
    expect(depricarpingReportMetric($summary, 'fan_static_pressure_mmh2o')['target']['parameter_metric'])->toBeNull();
});

it('case 31 — payload tidak memuat satu pun kunci penilaian terhadap standar', function () {
    depricarpingReportSeedTargets();

    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    // 27,4 RPM terhadap standar '20 - 24 RPM' dan batas kritis
    // '< 18 or > 26 RPM' — jelas di luar KEDUANYA, dan laporan ini TETAP
    // tidak menilainya. Batas di master Depricarping justru yang paling rapi
    // bentuknya dari ketiga master yang ada, jadi godaan menguraikannya nyata.
    depricarpingReportSlot($record, '07:00', ['polishing_drum_speed_rpm' => 27.4]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);
    $flat = json_encode($summary);

    // Asersi atas KETIADAAN, dan ia harus berupa penyisiran: memeriksa satu
    // kunci saja akan selalu hijau. kedua kolom target adalah teks bebas yang dapat disunting kapan pun,
    // jadi mengubahnya menjadi pembanding berarti mengarang batas yang tidak
    // pernah ditetapkan siapa pun.
    foreach (['severity', 'is_out_of_range', 'out_of_range', 'exceeds', 'flag', 'threshold', 'breach'] as $forbidden) {
        expect($flat)->not->toContain($forbidden);
    }

    // Kedua angka tetap diterbitkan apa adanya, berdampingan.
    expect(depricarpingReportMetric($summary, 'polishing_drum_speed_rpm')['avg'])->toBe(27.4);
    expect(depricarpingReportMetric($summary, 'polishing_drum_speed_rpm')['target']['target_range'])
        ->toBe('20 - 24 RPM');
    expect(depricarpingReportMetric($summary, 'polishing_drum_speed_rpm')['target']['critical_limit'])
        ->toBe('< 18 or > 26 RPM');
});

// =====================================================================
// GROUP F — RECAPS
// =====================================================================

it('case 32 — by_presser mengelompokkan presser_id sebagai unit, bukan kunci baris', function () {
    foreach (['2026-09-02', '2026-09-03'] as $date) {
        $record = depricarpingReportRecord($this->stationA, $date, 'PR-1');
        depricarpingReportSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 30.0]);
    }

    $this->actingAs($this->supervisorA);

    $byPresser = $this->service->buildSummary($this->periodA, null, $this->lineA)['by_presser'];

    expect($byPresser)->toHaveCount(1);
    expect($byPresser[0]['presser_id'])->toBe('PR-1');
    expect($byPresser[0]['day_count'])->toBe(2);
    expect($byPresser[0]['filled_slot_count'])->toBe(2);
});

it('case 33 — daily_total dihitung ulang atas seluruh slot, bukan merata-ratakan rata-rata harian', function () {
    $slots = DepricarpingReportServiceSlots();

    // Hari A: 10 slot bernilai 10. Hari B: 1 slot bernilai 100.
    $dayA = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');

    foreach (range(0, 9) as $index) {
        depricarpingReportSlot($dayA, $slots[$index], ['fan_static_pressure_mmh2o' => 10.0]);
    }

    $dayB = depricarpingReportRecord($this->stationA, '2026-09-03', 'PR-1');
    depricarpingReportSlot($dayB, '07:00', ['fan_static_pressure_mmh2o' => 100.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // (10x10 + 100) / 11 = 18,18 — BUKAN (10 + 100) / 2 = 55. Rata-rata dari
    // rata-rata memberi bobot sama pada hari yang jumlah slotnya berbeda.
    expect($summary['daily_total']['averages']['fan_static_pressure_mmh2o'])->toBe(18.18);
    expect($summary['daily_total']['filled_slot_count'])->toBe(11);
    expect($summary['daily'][0]['averages']['fan_static_pressure_mmh2o'])->toBe(10.0);
    expect($summary['daily'][1]['averages']['fan_static_pressure_mmh2o'])->toBe(100.0);
});

it('case 34 — hari tanpa record tidak mendapat baris pada rekap harian', function () {
    $record = depricarpingReportRecord($this->stationA, '2026-09-05', 'PR-1');
    depricarpingReportSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 30.0]);

    $this->actingAs($this->supervisorA);

    $daily = $this->service->buildSummary($this->periodA, null, $this->lineA)['daily'];

    // Baris nol untuk hari pabrik tidak beroperasi akan terbaca sebagai "kami
    // mengukur dan hasilnya nol".
    expect($daily)->toHaveCount(1);
    expect($daily[0]['date'])->toBe('2026-09-05');
});

it('case 35 — alasan downtime dikelompokkan harfiah tanpa penyeragaman huruf', function () {
    $slots = DepricarpingReportServiceSlots();
    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');

    foreach (range(0, 2) as $index) {
        depricarpingReportSlot($record, $slots[$index], ['findings' => 'Belt kendur']);
    }

    foreach (range(3, 4) as $index) {
        depricarpingReportSlot($record, $slots[$index], ['findings' => 'belt kendur']);
    }

    $this->actingAs($this->supervisorA);

    $reasons = $this->service->buildSummary($this->periodA, null, $this->lineA)['findings'];

    // Menyeragamkan akan menggabungkan sebab yang penulisnya memang maksudkan
    // berbeda; layar menyatakan sifat harfiahnya supaya dua baris mirip tidak
    // dibaca sebagai cacat laporan.
    expect($reasons)->toBe([
        ['finding' => 'Belt kendur', 'slot_count' => 3],
        ['finding' => 'belt kendur', 'slot_count' => 2],
    ]);
});

it('case 36 — alasan downtime berisi hanya spasi diperlakukan sebagai kosong', function () {
    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');

    depricarpingReportSlot($record, '07:00', ['findings' => '   ', 'fan_static_pressure_mmh2o' => 30.0]);

    $this->actingAs($this->supervisorA);

    expect($this->service->buildSummary($this->periodA, null, $this->lineA)['findings'])->toBe([]);
});

it('case 37 — slot ber-downtime tetap ikut angka ukur bila kolom ukurnya terisi', function () {
    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');

    depricarpingReportSlot($record, '07:00', [
        'fan_static_pressure_mmh2o' => 25.0,
        'findings' => 'Belt kendur',
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // Downtime adalah keterangan tambahan pada slot itu, bukan penyaring.
    expect(depricarpingReportMetric($summary, 'fan_static_pressure_mmh2o')['avg'])->toBe(25.0);
    expect($summary['findings'])->toBe([['finding' => 'Belt kendur', 'slot_count' => 1]]);
});

// =====================================================================
// GROUP G — TOTALS, DRAFT, VERIFICATION
// =====================================================================

it('case 38 — record draft ikut seluruh angka dan jumlahnya dinyatakan', function () {
    $draftOngoing = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1', [
        'status' => RecordStatus::DraftOngoing,
    ]);
    $draftPaused = depricarpingReportRecord($this->stationA, '2026-09-03', 'PR-2', [
        'status' => RecordStatus::DraftPaused,
    ]);
    // Synced, bukan Saved: DepricarpingRecord::booted() menolak status Saved
    // sebelum record punya satu pun detail, dan yang dibuktikan di sini
    // adalah "bukan draft" — bukan status tertentu.
    $saved = depricarpingReportRecord($this->stationA, '2026-09-04', 'PR-3', [
        'status' => RecordStatus::Synced,
    ]);

    foreach ([$draftOngoing, $draftPaused, $saved] as $record) {
        depricarpingReportSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 30.0]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // IKUT terhitung — jumlahnya hanya dinyatakan, supaya pembaca tahu
    // seberapa besar laporan ini berdiri di atas data yang belum selesai.
    expect($summary['total']['record_count'])->toBe(3);
    expect(depricarpingReportMetric($summary, 'fan_static_pressure_mmh2o')['filled_slot_count'])->toBe(3);
    // Kedua keadaan draft dihitung bersama: sama-sama belum selesai.
    expect($summary['total']['draft_record_count'])->toBe(2);
});

it('case 39 — status verifikasi bukan penyaring dan jumlahnya dinyatakan', function () {
    $unchecked = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    $checked = depricarpingReportRecord($this->stationA, '2026-09-03', 'PR-2', [
        'checked_by' => $this->supervisorA->id,
        'acknowledged_by' => $this->millManagementA->id,
    ]);

    foreach ([$unchecked, $checked] as $record) {
        depricarpingReportSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 30.0]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['total']['records_not_checked'])->toBe(1);
    expect($summary['total']['records_not_acknowledged'])->toBe(1);
    // Keduanya tetap terhitung penuh pada angka ukur.
    expect(depricarpingReportMetric($summary, 'fan_static_pressure_mmh2o')['filled_slot_count'])->toBe(2);
});

it('case 40 — has_data membedakan tidak ada yang dilaporkan dari angkanya nol', function () {
    $this->actingAs($this->supervisorA);

    expect($this->service->buildSummary($this->periodA, null, $this->lineA)['has_data'])->toBeFalse();

    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    depricarpingReportSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 0.0]);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['has_data'])->toBeTrue();
    expect(depricarpingReportMetric($summary, 'fan_static_pressure_mmh2o')['avg'])->toBe(0.0);
});

// =====================================================================
// GROUP H — EXPORT
// =====================================================================

it('case 41 — batas ekspor dihitung atas baris slot, bukan record', function () {
    $slots = DepricarpingReportServiceSlots();
    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');

    // SATU record, ENAM baris slot. Batas per record akan meloloskannya.
    foreach (range(0, 5) as $index) {
        depricarpingReportSlot($record, $slots[$index], ['fan_static_pressure_mmh2o' => 30.0]);
    }

    $this->actingAs($this->supervisorA);

    $tiny = new DepricarpingReportTinyExportService;

    expect(fn () => $tiny->buildExportRows($this->periodA, null, $this->lineA))
        ->toThrow(ExportFailedException::class);

    // Dan batas yang persis sama masih lolos: "strictly greater than".
    DepricarpingDetail::query()->where('time_slot', $slots[5])->delete();

    expect($tiny->buildExportRows($this->periodA, null, $this->lineA))->toBeInstanceOf(Generator::class);
});

it('case 42 — penjagaan ekspor berjalan eager, bukan pada iterasi pertama', function () {
    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType('depricarping')
        ->range('2026-09-01', '2026-09-30')
        ->open()
        ->create();

    $this->actingAs($this->supervisorA);

    // Penolakan datang dari PEMANGGILAN ITU SENDIRI. Bila ia tertunda sampai
    // iterasi pertama, 403 akan tiba sebagai unduhan sukses yang kosong.
    expect(fn () => $this->service->buildExportRows($periodB, null, $this->lineA))
        ->toThrow(AuthorizationException::class);
});

it('case 43 — ekspor memancarkan satu baris per slot dengan konteks diulang', function () {
    $recordOne = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    $recordTwo = depricarpingReportRecord($this->stationA, '2026-09-03', 'PR-2');

    foreach (['07:00', '08:00', '09:00'] as $slot) {
        depricarpingReportSlot($recordOne, $slot, ['fan_static_pressure_mmh2o' => 30.0]);
        depricarpingReportSlot($recordTwo, $slot, ['fan_static_pressure_mmh2o' => 40.0]);
    }

    $this->actingAs($this->supervisorA);

    $rows = iterator_to_array($this->service->buildExportRows($this->periodA, null, $this->lineA));

    expect($rows)->toHaveCount(6);

    // Kolom konteks terulang VERBATIM pada setiap baris, supaya berkasnya
    // dapat langsung dipivot di spreadsheet.
    foreach ($rows as $row) {
        expect($row[0])->toBe('Periode September Alpha');
        expect($row[1])->toBe('Mill Alpha');
        expect($row[2])->not->toBe('');
    }

    expect($rows[0][4])->toBe('PR-1');
    expect($rows[3][4])->toBe('PR-2');
    // Slot selalu HH:MM.
    expect($rows[0][7])->toBe('07:00');
});

it('case 44 — slot kosong tetap menjadi baris ekspor dengan sel kosong', function () {
    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');

    depricarpingReportSlot($record, '07:00');

    $this->actingAs($this->supervisorA);

    $rows = iterator_to_array($this->service->buildExportRows($this->periodA, null, $this->lineA));

    expect($rows)->toHaveCount(1);
    // Bukan dibuang, dan BUKAN ditulis 0 — membuangnya akan membuat berkasnya
    // berselisih dengan angka cakupan yang laporan yang sama terbitkan.
    expect($rows[0][8])->toBeNull();
    expect($rows[0][13])->toBeNull();
});

it('case 45 — format di luar csv dan excel ditolak sebelum satu baris dialirkan', function () {
    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    depricarpingReportSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 30.0]);

    $this->actingAs($this->supervisorA);

    expect(fn () => $this->service->export($this->periodA, 'pdf', null, $this->lineA))
        ->toThrow(ValidationException::class);
});

it('case 46 — ekspor CSV memuat header dan baris data, dan periode tertutup ikut terekspor', function () {
    $closed = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('depricarping')
        ->range('2026-08-01', '2026-08-31')
        ->closed()
        ->named('Periode Agustus Tertutup')
        ->create();

    $record = depricarpingReportRecord($this->stationA, '2026-08-05', 'PR-9');
    depricarpingReportSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 33.5, 'findings' => 'Belt kendur']);

    $this->actingAs($this->supervisorA);

    $response = $this->service->export($closed, 'csv', null, $this->lineA);
    $body = depricarpingReportStreamed($response);

    // Kunci periode mengatur PENULISAN data, bukan pembacaan laporan.
    expect($body)->toContain('Slot Waktu');
    // KESEMBILAN kolom bacaan ada di header, termasuk DUA yang tidak punya
    // padanan pada ekspor Threshing/Pressing: menit downtime (angka) dan
    // temuan (teks). Ekspor yang kehilangan salah satunya membuat berkasnya
    // tidak dapat menggantikan laporan, yang justru tujuan mengekspornya.
    expect($body)->toContain('Downtime (Menit)');
    expect($body)->toContain('Temuan');
    expect($body)->toContain('Suhu Nut Silo 1 (C)');
    expect($body)->toContain('Suhu Nut Silo 2 (C)');
    expect($body)->toContain('PR-9');
    expect($body)->toContain('Belt kendur');
});

/** The 24 canonical slots, in input-screen order. */
function DepricarpingReportServiceSlots(): array
{
    return App\Services\DepricarpingRecordService::canonicalTimeSlots();
}

// ====================================================================
// KASUS KHAS DEPRICARPING — perilaku yang tidak ada padanannya pada
// laporan Threshing maupun Pressing. Ditulis terpisah di bawah, bukan
// disisipkan di antara kasus 1-46, supaya terlihat mana yang diwarisi
// dari seri dan mana yang baru di layar ini.
// ====================================================================

it('case 47 — ketujuh kolom ukur diterbitkan dalam urutan NUMERIC_METRICS', function () {
    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    depricarpingReportSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 45.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect(collect($summary['metrics'])->pluck('column')->all())->toBe([
        'fan_static_pressure_mmh2o',
        'polishing_drum_speed_rpm',
        'air_velocity_ms',
        'fibre_moisture_percent',
        'kernel_recovery_in_fibre_percent',
        'nut_silo_1_temp_c',
        'nut_silo_2_temp_c',
    ]);
});

it('case 48 — ketujuh penyebut berbeda-beda, masing-masing milik kolomnya sendiri', function () {
    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');

    // Fixture SENGAJA memberi tujuh penyebut yang BERBEDA. Dengan penyebut
    // yang seragam, satu penyebut bersama akan lolos tanpa terlihat.
    $slots = DepricarpingReportServiceSlots();
    $pola = [
        'fan_static_pressure_mmh2o' => 2,
        'polishing_drum_speed_rpm' => 9,
        'air_velocity_ms' => 0,
        'fibre_moisture_percent' => 7,
        'kernel_recovery_in_fibre_percent' => 1,
        'nut_silo_1_temp_c' => 5,
        'nut_silo_2_temp_c' => 3,
    ];

    foreach (array_slice($slots, 0, 10) as $i => $slot) {
        $values = [];

        foreach ($pola as $column => $count) {
            if ($i < $count) {
                $values[$column] = 10.0 + $i;
            }
        }

        depricarpingReportSlot($record, $slot, $values);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    foreach ($pola as $column => $count) {
        expect(depricarpingReportMetric($summary, $column)['filled_slot_count'])
            ->toBe($count, "penyebut {$column} harus {$count}");
    }
});

it('case 49 — kedua nut silo membawa standar master yang SAMA dan saling menyebut', function () {
    depricarpingReportSeedTargets();

    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    depricarpingReportSlot($record, '07:00', ['nut_silo_1_temp_c' => 65.0, 'nut_silo_2_temp_c' => 85.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    $silo1 = depricarpingReportMetric($summary, 'nut_silo_1_temp_c');
    $silo2 = depricarpingReportMetric($summary, 'nut_silo_2_temp_c');

    // SATU baris master mengatur DUA kolom — pertama kali terjadi di seri ini.
    expect($silo1['target']['parameter_metric'])->toBe('Nut Silo Temperature');
    expect($silo2['target']['parameter_metric'])->toBe('Nut Silo Temperature');
    expect($silo1['target']['target_range'])->toBe('60C - 70C');
    expect($silo2['target']['target_range'])->toBe('60C - 70C');

    // Hubungannya DITERBITKAN, bukan dibiarkan jadi prosa di layar: standar
    // identik yang tercetak dua kali tanpa penjelasan terbaca seperti data
    // terduplikasi, dan seseorang akan "membersihkannya".
    expect($silo1['target']['shares_standard_with'])->toBe(['nut_silo_2_temp_c']);
    expect($silo2['target']['shares_standard_with'])->toBe(['nut_silo_1_temp_c']);
});

it('case 50 — kedua nut silo TIDAK dirata-ratakan menjadi satu angka', function () {
    depricarpingReportSeedTargets();

    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    depricarpingReportSlot($record, '07:00', ['nut_silo_1_temp_c' => 65.0, 'nut_silo_2_temp_c' => 85.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect(depricarpingReportMetric($summary, 'nut_silo_1_temp_c')['avg'])->toBe(65.0);
    expect(depricarpingReportMetric($summary, 'nut_silo_2_temp_c')['avg'])->toBe(85.0);

    // TIDAK ADA satu pun metrik bernilai 75 — merata-ratakan kedua silo akan
    // menyembunyikan silo yang menyimpang di belakang silo yang normal, sama
    // dengan menjumlahkan dua blok unit pada laporan Grading.
    expect(collect($summary['metrics'])->pluck('avg')->all())->not->toContain(75.0);
});

it('case 51 — satu silo terisi dan satu tidak: dua baris tetap ada, standar sama tetap tercetak', function () {
    depricarpingReportSeedTargets();

    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    depricarpingReportSlot($record, '07:00', ['nut_silo_1_temp_c' => 65.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    $silo2 = depricarpingReportMetric($summary, 'nut_silo_2_temp_c');

    expect($silo2['avg'])->toBeNull();
    expect($silo2['filled_slot_count'])->toBe(0);
    // Standarnya TETAP tampil walau tidak ada pembacaan: baris yang hilang
    // terbaca sebagai "tidak ada parameter ini".
    expect($silo2['target']['target_range'])->toBe('60C - 70C');
    expect($silo2['target']['shares_standard_with'])->toBe(['nut_silo_1_temp_c']);
});

it('case 52 — kernel_recovery_in_fibre_percent TIDAK ADA pada peta target', function () {
    // Mengunci keputusan agar tidak "dilengkapi" oleh pembaca berikutnya.
    // Master menyebut standarnya KEHILANGAN ('< 0.50%'); kolom ini dan keempat
    // layar input Depricarping melabelinya PEROLEHAN. Memetakannya akan
    // membuat laporan menilai dengan arah TERBALIK tanpa ada yang menyadarinya.
    expect(DepricarpingReportService::COLUMN_TARGET_PARAMETER)->toHaveCount(6);
    expect(array_keys(DepricarpingReportService::COLUMN_TARGET_PARAMETER))
        ->not->toContain('kernel_recovery_in_fibre_percent');

    // Dan kolomnya MEMANG ada pada daftar metrik — tidak dipetakan bukan
    // berarti tidak dilaporkan.
    expect(DepricarpingReportService::NUMERIC_METRICS)
        ->toContain('kernel_recovery_in_fibre_percent');

    // Enam entri peta hanya menyebut LIMA parameter berbeda, karena kedua nut
    // silo menunjuk parameter yang sama. Inilah sebabnya targetsWithoutMetric()
    // harus membandingkan atas HIMPUNAN nilai, bukan atas count().
    expect(array_unique(array_values(DepricarpingReportService::COLUMN_TARGET_PARAMETER)))
        ->toHaveCount(5);
});

it('case 53 — angka kernel recovery tetap diterbitkan, dengan target null dan alasan terisi', function () {
    depricarpingReportSeedTargets();

    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    depricarpingReportSlot($record, '07:00', ['kernel_recovery_in_fibre_percent' => 0.4]);
    depricarpingReportSlot($record, '08:00', ['kernel_recovery_in_fibre_percent' => 0.6]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);
    $metric = depricarpingReportMetric($summary, 'kernel_recovery_in_fibre_percent');

    // ANGKANYA ADA — kolom yang tidak dipetakan tetap dilaporkan di bawah
    // label kolomnya sendiri.
    expect($metric['avg'])->toBe(0.5);
    expect($metric['filled_slot_count'])->toBe(2);

    // STANDARNYA TIDAK DIPASANGKAN, dan alasannya diterbitkan sebagai kunci
    // supaya layar dapat menjelaskannya alih-alih menampilkan sel kosong yang
    // terbaca seperti master yang belum terisi.
    expect($metric['target']['parameter_metric'])->toBeNull();
    expect($metric['target']['target_range'])->toBeNull();
    expect($metric['target']['critical_limit'])->toBeNull();
    expect($metric['target']['operational_consequence_justification'])->toBeNull();
    expect($metric['target']['shares_standard_with'])->toBe([]);
    expect($metric['target']['unmapped_reason'])
        ->toBe(DepricarpingReportService::UNMAPPED_DIRECTION_UNRESOLVED);
});

it('case 54 — parameter master baru tanpa kolom ukur memakai alasan no_column, bukan direction_unresolved', function () {
    depricarpingReportSeedTargets();

    DepricarpingOperationalTarget::create([
        'parameter_metric' => 'Shell Content in Kernel',
        'target_range' => '< 6%',
        'critical_limit' => '> 8%',
        'operational_consequence_justification' => 'Rejected by refinery on contract spec.',
        'sort_order' => 99,
    ]);

    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    depricarpingReportSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 45.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['targets_without_metric'])->toHaveCount(2);

    $byParameter = collect($summary['targets_without_metric'])->keyBy('parameter_metric');

    // DUA ALASAN YANG BERBEDA, dan perbedaannya menentukan tindakan: yang
    // pertama menuntut kolom ukur baru, yang kedua menuntut keputusan
    // penamaan. Menggabungkannya menjadi satu "belum terukur" akan
    // menyembunyikan tindakan mana yang diperlukan.
    expect($byParameter['Shell Content in Kernel']['reason'])
        ->toBe(DepricarpingReportService::UNMAPPED_NO_COLUMN);
    expect($byParameter['Kernel Loss in Fibre']['reason'])
        ->toBe(DepricarpingReportService::UNMAPPED_DIRECTION_UNRESOLVED);
});

it('case 55 — downtime dijumlahkan atas slot yang MENCATATNYA saja', function () {
    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    depricarpingReportSlot($record, '07:00', ['downtime_minutes' => 10]);
    depricarpingReportSlot($record, '08:00', ['downtime_minutes' => 20]);
    depricarpingReportSlot($record, '09:00', ['fan_static_pressure_mmh2o' => 45.0]);
    depricarpingReportSlot($record, '10:00', ['fan_static_pressure_mmh2o' => 46.0]);
    depricarpingReportSlot($record, '11:00', ['downtime_minutes' => 30]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['downtime']['total_minutes'])->toBe(60);
    // PENYEBUTNYA 3, BUKAN 5. Memakai 5 akan membuat rata-rata mengecil
    // justru seiring bertambahnya slot yang TIDAK dicatat — laporan akan
    // tampak lebih baik karena lebih sedikit yang ditulis.
    expect($summary['downtime']['recorded_slot_count'])->toBe(3);
    expect($summary['downtime']['avg_minutes_per_recorded_slot'])->toBe(20.0);
});

it('case 56 — downtime bernilai 0 yang tercatat IKUT dihitung', function () {
    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    depricarpingReportSlot($record, '07:00', ['downtime_minutes' => 0]);
    depricarpingReportSlot($record, '08:00', ['downtime_minutes' => 0]);
    depricarpingReportSlot($record, '09:00', ['downtime_minutes' => 30]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['downtime']['total_minutes'])->toBe(30);
    // NOL BERARTI SESEORANG MENYATAKAN stasiun tidak berhenti pada slot itu.
    // Memperlakukannya sebagai "tidak tercatat" akan membuang pernyataan itu.
    expect($summary['downtime']['recorded_slot_count'])->toBe(3);
    expect($summary['downtime']['avg_minutes_per_recorded_slot'])->toBe(10.0);
});

it('case 57 — tanpa satu pun slot pencatat, total downtime NULL bukan 0', function () {
    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    depricarpingReportSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 45.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // Total 0 menit terbaca seperti "stasiun tidak pernah berhenti", padahal
    // yang benar adalah "tidak ada yang mencatatnya".
    expect($summary['downtime']['total_minutes'])->toBeNull();
    expect($summary['downtime']['recorded_slot_count'])->toBe(0);
    expect($summary['downtime']['avg_minutes_per_recorded_slot'])->toBeNull();
});

it('case 58 — downtime tidak punya standar pada master, dan itu dinyatakan sebagai kunci', function () {
    depricarpingReportSeedTargets();

    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    depricarpingReportSlot($record, '07:00', ['downtime_minutes' => 15]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // Dinyatakan sebagai kunci, bukan dibiarkan terbaca sebagai master yang
    // belum terisi.
    expect($summary['downtime']['has_standard'])->toBeFalse();
});

it('case 59 — pencilan downtime ikut total apa adanya', function () {
    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    depricarpingReportSlot($record, '07:00', ['downtime_minutes' => 5]);
    depricarpingReportSlot($record, '08:00', ['downtime_minutes' => 1440]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // Laporan tidak membuang pencilan: ia tidak punya dasar menyebutnya salah.
    expect($summary['downtime']['total_minutes'])->toBe(1445);
    expect($summary['downtime']['recorded_slot_count'])->toBe(2);
});

it('case 60 — temuan dan downtime TIDAK dicampur', function () {
    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    depricarpingReportSlot($record, '07:00', ['downtime_minutes' => 15, 'findings' => 'Belt kendur']);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // Slot itu terhitung SEKALI pada masing-masing pengertian, dan tidak ada
    // satu pun kunci yang menggabungkan keduanya: satu menjawab "berapa lama",
    // satu menjawab "apa yang terlihat".
    expect($summary['downtime']['recorded_slot_count'])->toBe(1);
    expect($summary['findings'])->toBe([['finding' => 'Belt kendur', 'slot_count' => 1]]);
});

it('case 61 — slot yang HANYA berisi downtime_minutes dihitung terisi tetapi tidak menyumbang angka ukur', function () {
    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    depricarpingReportSlot($record, '07:00', ['downtime_minutes' => 12]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // downtime_minutes adalah kolom bacaan KEDELAPAN dari sembilan.
    expect($summary['coverage']['filled_slots'])->toBe(1);
    expect($summary['has_data'])->toBeTrue();

    foreach ($summary['metrics'] as $metric) {
        expect($metric['filled_slot_count'])->toBe(0, "kolom {$metric['column']} tidak boleh menyumbang angka");
        expect($metric['avg'])->toBeNull();
    }
});

it('case 62 — filled_slots DAPAT melebihi penyebut metrik mana pun, dan itu benar', function () {
    $record = depricarpingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    $slots = DepricarpingReportServiceSlots();

    foreach (array_slice($slots, 0, 3) as $slot) {
        depricarpingReportSlot($record, $slot, ['fan_static_pressure_mmh2o' => 45.0]);
    }

    foreach (array_slice($slots, 3, 7) as $slot) {
        depricarpingReportSlot($record, $slot, ['findings' => 'Periksa aspirator']);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // Slot dihitung terisi bila salah satu dari SEMBILAN kolom bacaan terisi,
    // dan dua di antaranya (downtime_minutes, findings) bukan kolom ukur.
    // Selisih ini BENAR; dikunci di sini supaya tidak "diperbaiki".
    expect($summary['coverage']['filled_slots'])->toBe(10);
    expect(collect($summary['metrics'])->max('filled_slot_count'))->toBe(3);
});

it('case 63 — by_presser dan daily membawa total downtime, null bila tidak ada yang mencatat', function () {
    $recordOne = depricarpingReportRecord($this->stationA, '2026-09-02', 'DP-01');
    depricarpingReportSlot($recordOne, '07:00', ['downtime_minutes' => 10]);
    depricarpingReportSlot($recordOne, '08:00', ['downtime_minutes' => 20]);

    $recordTwo = depricarpingReportRecord($this->stationA, '2026-09-03', 'DP-02');
    depricarpingReportSlot($recordTwo, '07:00', ['fan_static_pressure_mmh2o' => 45.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    $byPresser = collect($summary['by_presser'])->keyBy('presser_id');

    expect($byPresser['DP-01']['downtime_minutes'])->toBe(30);
    // null, BUKAN 0: presser yang tidak satu pun slotnya mencatat downtime
    // bukan presser yang tidak pernah berhenti.
    expect($byPresser['DP-02']['downtime_minutes'])->toBeNull();

    $daily = collect($summary['daily'])->keyBy('date');

    expect($daily['2026-09-02']['downtime_minutes'])->toBe(30);
    expect($daily['2026-09-03']['downtime_minutes'])->toBeNull();
    expect($summary['daily_total']['downtime_minutes'])->toBe(30);
});

it('case 64 — READING_FIELDS memuat sembilan kolom dan isRowFilled kini public', function () {
    expect(DepricarpingRecordService::READING_FIELDS)->toHaveCount(9);
    expect(DepricarpingRecordService::READING_FIELDS)->toBe([
        'fan_static_pressure_mmh2o',
        'polishing_drum_speed_rpm',
        'air_velocity_ms',
        'fibre_moisture_percent',
        'kernel_recovery_in_fibre_percent',
        'nut_silo_1_temp_c',
        'nut_silo_2_temp_c',
        'downtime_minutes',
        'findings',
    ]);

    // Laporan WAJIB meminjam definisi "terisi" dari layar input, bukan
    // menurunkannya ulang: dua definisi yang berselisih berarti cakupan di
    // laporan tidak sama dengan apa yang diterima layar input, dan tidak ada
    // yang bisa tahu mana yang salah.
    expect((new ReflectionMethod(DepricarpingRecordService::class, 'isRowFilled'))->isPublic())
        ->toBeTrue();
});

it('case 65 — isRowFilled: findings kosong TIDAK terisi, downtime_minutes 0 TERISI', function () {
    $service = new DepricarpingRecordService;

    $kosong = array_fill_keys(DepricarpingRecordService::READING_FIELDS, null);

    expect($service->isRowFilled($kosong))->toBeFalse();

    // Asimetri yang DISENGAJA dan dipertahankan apa adanya dari layar input:
    // findings tiba sebagai '' bila tidak disentuh, sementara
    // downtime_minutes 0 adalah PERNYATAAN bahwa stasiun tidak berhenti.
    expect($service->isRowFilled(array_merge($kosong, ['findings' => ''])))->toBeFalse();
    expect($service->isRowFilled(array_merge($kosong, ['findings' => 'Ada'])))->toBeTrue();
    expect($service->isRowFilled(array_merge($kosong, ['downtime_minutes' => 0])))->toBeTrue();

    // Dan ketujuh kolom ukur, satu per satu.
    foreach (DepricarpingReportService::NUMERIC_METRICS as $column) {
        expect($service->isRowFilled(array_merge($kosong, [$column => 1.0])))
            ->toBeTrue("kolom {$column} harus membuat baris terhitung terisi");
    }
});

it('case 66 — REPORT_ROUTES memetakan depricarping, dan posisinya mengikuti sort_order bukan alur proses', function () {
    expect(StationReportService::REPORT_ROUTES)
        ->toHaveKey(StationTypeEnum::Depricarping->value, 'reports.depricarping');

    $codes = array_keys(StationReportService::REPORT_ROUTES);
    $at = array_search(StationTypeEnum::Depricarping->value, $codes, true);

    // TETANGGANYA boiler-room lalu storage-tank — BUKAN pressing lalu
    // clarification seperti yang disarankan alur proses. sort_order
    // depricarping adalah 110 (di belakang clarification 70 dan boiler-room
    // 90, di depan storage-tank 140), dan urutan deklarasi enum juga
    // menyesatkan karena di sana Depricarping tepat setelah Pressing.
    //
    // Asersi berurutan ini MEMANG dimaksudkan gagal bila peta berubah, supaya
    // daftarnya diperbarui alih-alih diam-diam menjadi selalu hijau.
    expect($codes[$at - 1])->toBe(StationTypeEnum::BoilerRoom->value);
    expect($codes[$at + 1])->toBe(StationTypeEnum::StorageTank->value);
});
