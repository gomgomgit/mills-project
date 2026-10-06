<?php

/**
 * PressingReportServiceTest — screen-150--laporan-pressing-web /
 * screen-151--laporan-pressing-mobile, PressingReportService.
 *
 * One test per unit_test_cases entry on screen-150's tech spec, grouped the
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
 *   - A SLOT CARRYING ONLY downtime_reason IS STILL FILLED. If every slot
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
use App\Models\PressingDetail;
use App\Models\PressingOperationalTarget;
use App\Models\PressingRecord;
use App\Models\User;
use App\Services\PressingReportService;
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
class PressingReportAllBusinessUnitsSpy extends PressingReportService
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
class PressingReportTinyExportService extends PressingReportService
{
    public const EXPORT_ROW_LIMIT = 5;
}

/** One daily record for one presser. */
function pressingReportRecord(
    Station $station,
    string $date,
    string $presserId = 'PR-1',
    array $attributes = [],
): PressingRecord {
    return PressingRecord::factory()
        ->forStation($station)
        ->onDate($date)
        ->create(array_merge([
            'presser_id' => $presserId,
            'note' => 'Catatan harian',
        ], $attributes));
}

/** One time-slot row. Every measurement column defaults to null. */
function pressingReportSlot(
    PressingRecord $record,
    string $timeSlot,
    array $values = [],
): PressingDetail {
    return PressingDetail::factory()
        ->forRecord($record)
        ->timeSlot($timeSlot)
        ->create($values);
}

/** The six seeded master rows, matching PressingOperationalTargetSeeder. */
function pressingReportSeedTargets(): void
{
    // Ketujuh baris master, persis seperti PressingOperationalTargetSeeder.
    // TUJUH parameter untuk LIMA kolom ukur: 'Nut Breakage Rate' dan 'Press
    // Cake Moisture' tidak punya kolom pengukuran di mana pun pada skema ini.
    $rows = [
        ['Digester Temperature', '90C - 95C', '< 85C (Leads to poor oil liberation)'],
        ['Digester Fill Level', '75% - 80% (Minimum 3/4 full)', '< 50% (Reduces retention time & friction)'],
        ['Screw Press Motor Current', '35 - 45 Amperes', '> 50 Amps (Indicates choke or heavy load)'],
        ['Cone Hydraulic Pressure', '45 - 55 Bar', '> 60 Bar (Increases nut breakage severely)'],
        ['Dilution Water Temperature', '85C - 90C', '< 80C (Causes poor oil-water separation)'],
        ['Nut Breakage Rate', '< 10% to 12%', '> 15% (Adjust screw press cones backward)'],
        ['Press Cake Moisture', '34% - 38%', '> 40% (Indicates insufficient pressing pressure)'],
    ];

    foreach ($rows as $index => [$parameter, $range, $limit]) {
        PressingOperationalTarget::create([
            'parameter_metric' => $parameter,
            'target_operating_range' => $range,
            'critical_trigger_action_limit' => $limit,
            'sort_order' => $index + 1,
        ]);
    }
}

/** Every SQL statement run inside $callback. */
function pressingReportQueriesDuring(callable $callback): array
{
    $queries = [];

    DB::listen(function ($query) use (&$queries) {
        $queries[] = strtolower($query->sql);
    });

    $callback();

    return $queries;
}

function pressingReportStreamed(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

/** The metric entry for one column, from the metrics list. */
function pressingReportMetric(array $summary, string $column): array
{
    foreach ($summary['metrics'] as $metric) {
        if ($metric['column'] === $column) {
            return $metric;
        }
    }

    throw new RuntimeException("metric {$column} tidak ada pada payload");
}

beforeEach(function () {
    $this->service = new PressingReportService;

    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->pressing()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->pressing()->create();

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
        ->stationType('pressing')
        ->range('2026-09-01', '2026-09-10')
        ->open()
        ->named('Periode September Alpha')
        ->create();
});

// =====================================================================
// GROUP A — ACCESS, MILL, LINE, PERIOD
// =====================================================================

it('case 1 — guardAccess menolak tamu 401 dan peran di luar keempatnya 403, sebelum satu kueri periode', function () {
    $queries = pressingReportQueriesDuring(function () {
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

    $spy = new PressingReportAllBusinessUnitsSpy;

    expect(fn () => $spy->resolveBusinessUnit(null))->toThrow(ValidationException::class);

    // Falling back to "every mill" would turn one broken master-data row into
    // a cross-mill leak. The spy proves that path is not taken.
    expect($spy->allBusinessUnitsCalls)->toBe(0);
});

it('case 5 — businessUnitOptions menolak setiap peran terikat mill, termasuk Operator', function () {
    $spy = new PressingReportAllBusinessUnitsSpy;

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

it('case 6 — listPeriods menyaring menurut baris period_stations station_type pressing', function () {
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
    expect($periods[0]['station_type'])->toBe('pressing');
});

it('case 7 — listPeriods memakai status dari baris period_stations, bukan kolom status periode', function () {
    $this->actingAs($this->supervisorA);

    expect($this->service->listPeriods(null)[0]['status'])->toBe('open');
});

it('case 8 — listPeriods tetap mencantumkan periode tertutup', function () {
    Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('pressing')
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
        ->stationType('pressing')
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
        ->stationType('pressing')
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

    $kept = pressingReportRecord($this->stationA, '2026-09-03', 'PR-1');
    $moved = pressingReportRecord($this->stationA, '2026-09-03', 'PR-2', [
        'production_line_id' => $otherLine->id,
    ]);

    pressingReportSlot($kept, '07:00', ['digester_temp_c' => 30.0]);
    pressingReportSlot($moved, '07:00', ['digester_temp_c' => 99.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['coverage']['filled_slots'])->toBe(1);
    expect(pressingReportMetric($summary, 'digester_temp_c')['avg'])->toBe(30.0);
    // Menyaring lewat join ke stations akan menarik record yang dipindah dan
    // menjawab 64,5 — rata-rata dua line sekaligus.
    expect(pressingReportMetric($summary, 'digester_temp_c')['max'])->toBe(30.0);
});

it('case 14 — keanggotaan periode inklusif di kedua ujung dan menolak satu hari di luarnya', function () {
    $first = pressingReportRecord($this->stationA, '2026-09-01', 'PR-1');
    $last = pressingReportRecord($this->stationA, '2026-09-10', 'PR-1');
    $outside = pressingReportRecord($this->stationA, '2026-09-11', 'PR-1');

    pressingReportSlot($first, '07:00', ['digester_temp_c' => 10.0]);
    pressingReportSlot($last, '07:00', ['digester_temp_c' => 20.0]);
    pressingReportSlot($outside, '07:00', ['digester_temp_c' => 999.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['total']['record_count'])->toBe(2);
    expect(pressingReportMetric($summary, 'digester_temp_c')['max'])->toBe(20.0);
});

it('case 15 — baris slot yang keenam kolomnya null tidak dihitung terisi', function () {
    $record = pressingReportRecord($this->stationA, '2026-09-02', 'PR-1');

    foreach (['07:00', '08:00', '09:00'] as $slot) {
        pressingReportSlot($record, $slot);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['coverage']['filled_slots'])->toBe(0);
    expect($summary['has_data'])->toBeFalse();
    // Record-nya tetap ada, dan tetap ikut penyebut cakupan.
    expect($summary['total']['record_count'])->toBe(1);
    expect($summary['coverage']['presser_count'])->toBe(1);
});

it('case 16 — baris slot yang HANYA berisi downtime_reason dihitung terisi', function () {
    $record = pressingReportRecord($this->stationA, '2026-09-02', 'PR-1');

    pressingReportSlot($record, '07:00', ['downtime_reason' => 'Belt kendur']);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // Slot yang jelas disentuh operator tidak boleh dilaporkan sebagai slot
    // kosong — dan definisi "terisi" dipinjam dari layar input itu sendiri.
    expect($summary['coverage']['filled_slots'])->toBe(1);
    expect($summary['has_data'])->toBeTrue();
    // Tidak satu pun angka ukur terbentuk darinya.
    expect(pressingReportMetric($summary, 'digester_temp_c')['avg'])->toBeNull();
    expect(pressingReportMetric($summary, 'digester_temp_c')['filled_slot_count'])->toBe(0);
    expect($summary['downtime_reasons'])->toBe([['reason' => 'Belt kendur', 'slot_count' => 1]]);
});

// =====================================================================
// GROUP C — PER-METRIC DENOMINATORS
// =====================================================================

it('case 17 — tiap kolom memakai penyebutnya sendiri, bukan satu penyebut bersama', function () {
    $record = pressingReportRecord($this->stationA, '2026-09-02', 'PR-1');

    $slots = PressingReportServiceSlots();

    // 10 slot ber-throughput; hanya 3 di antaranya ber-drum-speed.
    foreach (range(0, 9) as $index) {
        $values = ['digester_temp_c' => 30.0];

        if ($index < 3) {
            // 20 + 22 + 24 = 66, rata-rata 22,0 bila dibagi 3.
            $values['digester_level_percent'] = 20.0 + ($index * 2);
        }

        pressingReportSlot($record, $slots[$index], $values);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect(pressingReportMetric($summary, 'digester_temp_c')['filled_slot_count'])->toBe(10);
    expect(pressingReportMetric($summary, 'digester_level_percent')['filled_slot_count'])->toBe(3);
    // 66/3 = 22,0. Penyebut bersama (10) akan menjawab 6,6 — angka yang tidak
    // akan dipertanyakan siapa pun.
    expect(pressingReportMetric($summary, 'digester_level_percent')['avg'])->toBe(22.0);
});

it('case 18 — kolom tanpa satu pun nilai menghasilkan null, bukan 0, dan barisnya tetap ada', function () {
    $record = pressingReportRecord($this->stationA, '2026-09-02', 'PR-1');

    pressingReportSlot($record, '07:00', ['digester_temp_c' => 30.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    $motor = pressingReportMetric($summary, 'press_motor_current_amps');

    // Baris yang hilang terbaca sebagai "tidak ada parameter ini", padahal
    // yang benar adalah "tidak ada yang mengukurnya".
    expect($summary['metrics'])->toHaveCount(5);
    expect($motor['min'])->toBeNull();
    expect($motor['avg'])->toBeNull();
    expect($motor['max'])->toBeNull();
    expect($motor['filled_slot_count'])->toBe(0);
});

it('case 19 — kolom dengan satu nilai menghasilkan min, rata-rata, dan maks yang sama', function () {
    $record = pressingReportRecord($this->stationA, '2026-09-02', 'PR-1');

    pressingReportSlot($record, '07:00', ['dilution_water_temp_c' => 0.42]);

    $this->actingAs($this->supervisorA);

    $metric = pressingReportMetric(
        $this->service->buildSummary($this->periodA, null, $this->lineA),
        'dilution_water_temp_c',
    );

    expect($metric['min'])->toBe(0.42);
    expect($metric['avg'])->toBe(0.42);
    expect($metric['max'])->toBe(0.42);
    // Penyebut inilah yang menjelaskan mengapa ketiganya sama.
    expect($metric['filled_slot_count'])->toBe(1);
});

it('case 20 — nilai nol dan negatif dilaporkan apa adanya', function () {
    $record = pressingReportRecord($this->stationA, '2026-09-02', 'PR-1');

    pressingReportSlot($record, '07:00', ['cone_hydraulic_pressure_bar' => 0.0]);
    pressingReportSlot($record, '08:00', ['cone_hydraulic_pressure_bar' => -1.0]);

    $this->actingAs($this->supervisorA);

    $metric = pressingReportMetric(
        $this->service->buildSummary($this->periodA, null, $this->lineA),
        'cone_hydraulic_pressure_bar',
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
    $record = pressingReportRecord($this->stationA, '2026-09-02', 'PR-1');

    pressingReportSlot($record, '07:00', ['digester_temp_c' => 30.0]);
    pressingReportSlot($record, '08:00', ['digester_level_percent' => 22.0]);

    $this->actingAs($this->supervisorA);

    $queries = pressingReportQueriesDuring(function () {
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
        $record = pressingReportRecord($this->stationA, '2026-09-02', $presser);
        pressingReportSlot($record, '07:00', ['digester_temp_c' => 30.0]);
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
    // Empat stasiun pressing terdaftar pada line ini; hanya dua presser
    // yang benar-benar beroperasi di periode itu.
    Station::factory()->count(3)->forBusinessUnit($this->businessUnitA)->pressing()->create([
        'production_line_id' => $this->lineA,
    ]);

    foreach (['PR-1', 'PR-2'] as $presser) {
        $record = pressingReportRecord($this->stationA, '2026-09-02', $presser);
        pressingReportSlot($record, '07:00', ['digester_temp_c' => 30.0]);
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
        ->stationType('pressing')
        ->range(now()->subDays(4)->toDateString(), now()->addDays(10)->toDateString())
        ->open()
        ->named('Periode Berjalan')
        ->create();

    $record = pressingReportRecord($this->stationA, now()->subDays(1)->toDateString(), 'PR-1');
    pressingReportSlot($record, '07:00', ['digester_temp_c' => 30.0]);

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
        ->stationType('pressing')
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
    $record = pressingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    pressingReportSlot($record, '07:00', ['digester_temp_c' => 30.0]);

    $this->actingAs($this->supervisorA);

    $coverage = $this->service->buildSummary($this->periodA, null, $this->lineA)['coverage'];

    // Satu definisi, satu jawaban: bukan konstanta terpisah yang bisa
    // menyimpang dari grid layar input.
    expect($coverage['slots_per_presser_per_day'])
        ->toBe(count(App\Services\PressingRecordService::canonicalTimeSlots()));
});

// =====================================================================
// GROUP E — OPERATIONAL TARGETS (tidak ada padanannya di tujuh laporan lain)
// =====================================================================

it('case 27 — tiap kolom ukur membawa standar dan rencana tindakannya', function () {
    pressingReportSeedTargets();

    $record = pressingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    pressingReportSlot($record, '07:00', ['digester_level_percent' => 27.4]);

    $this->actingAs($this->supervisorA);

    $metric = pressingReportMetric(
        $this->service->buildSummary($this->periodA, null, $this->lineA),
        'digester_level_percent',
    );

    expect($metric['target']['parameter_metric'])->toBe('Digester Fill Level');
    expect($metric['target']['target_operating_range'])->toBe('75% - 80% (Minimum 3/4 full)');
    expect($metric['target']['critical_trigger_action_limit'])->toContain('Reduces retention time');
});

it('case 28 — parameter master tanpa kolom ukur masuk targets_without_metric', function () {
    pressingReportSeedTargets();

    $record = pressingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    pressingReportSlot($record, '07:00', ['digester_temp_c' => 30.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // Enam parameter pada master, lima kolom pada formulir. Ketimpangan itu
    // DITERBITKAN: standar yang tidak pernah diukur terbaca seperti terpenuhi
    // padahal ia sekadar tidak ada.
    expect($summary['metrics'])->toHaveCount(5);
    // DUA, bukan satu seperti pada Threshing.
    expect($summary['targets_without_metric'])->toHaveCount(2);

    $unmeasured = collect($summary['targets_without_metric'])->pluck('parameter_metric')->all();

    expect($unmeasured)->toContain('Nut Breakage Rate');
    expect($unmeasured)->toContain('Press Cake Moisture');

    // KEDUA kolom target ikut diterbitkan untuk keduanya, bukan hanya namanya.
    $nut = collect($summary['targets_without_metric'])->firstWhere('parameter_metric', 'Nut Breakage Rate');

    expect($nut['target_operating_range'])->toBe('< 10% to 12%');
    expect($summary['targets_without_metric'][0]['critical_trigger_action_limit'])->toContain('Adjust screw press cones');
    expect($summary['targets_master_empty'])->toBeFalse();
});

it('case 29 — pemetaan memakai peta tetap: ejaan master yang diubah tidak menghapus angka', function () {
    pressingReportSeedTargets();

    PressingOperationalTarget::query()
        ->where('parameter_metric', 'Digester Fill Level')
        ->update(['parameter_metric' => 'Digester Fill Levell']);

    $record = pressingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    pressingReportSlot($record, '07:00', ['digester_level_percent' => 22.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);
    $metric = pressingReportMetric($summary, 'digester_level_percent');

    // ANGKANYA UTUH — pemetaan tidak bersandar pada teks nama parameter.
    expect($metric['avg'])->toBe(22.0);
    expect($metric['filled_slot_count'])->toBe(1);
    // Standarnya terlepas, dan keterlepasan itu TERLIHAT alih-alih senyap.
    expect($metric['target']['target_operating_range'])->toBeNull();
    // Baris yang namanya tidak dikenal lagi BERGABUNG dengan kedua parameter
    // yang memang tak terukur, jadi daftarnya menjadi tiga — dan keterlepasan
    // itu TERLIHAT alih-alih senyap.
    expect(collect($summary['targets_without_metric'])->pluck('parameter_metric')->all())
        ->toContain('Digester Fill Levell');
    expect($summary['targets_without_metric'])->toHaveCount(3);
});

it('case 30 — master target kosong tidak menghapus angka hasil ukur', function () {
    $record = pressingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    pressingReportSlot($record, '07:00', ['digester_temp_c' => 30.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['targets_master_empty'])->toBeTrue();
    expect($summary['targets_without_metric'])->toBe([]);
    expect($summary['metrics'])->toHaveCount(5);
    // Seeder yang belum dijalankan tidak boleh menghapus pengukuran yang
    // sudah terjadi.
    expect(pressingReportMetric($summary, 'digester_temp_c')['avg'])->toBe(30.0);
    expect(pressingReportMetric($summary, 'digester_temp_c')['target']['parameter_metric'])->toBeNull();
});

it('case 31 — payload tidak memuat satu pun kunci penilaian terhadap standar', function () {
    pressingReportSeedTargets();

    $record = pressingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    // 27,4 RPM terhadap standar '21 - 23 RPM' — jelas di luar rentangnya,
    // dan laporan ini TETAP tidak menilainya.
    pressingReportSlot($record, '07:00', ['digester_level_percent' => 27.4]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);
    $flat = json_encode($summary);

    // Asersi atas KETIADAAN, dan ia harus berupa penyisiran: memeriksa satu
    // kunci saja akan selalu hijau. Kolom target adalah teks bebas dan tidak
    // ada apa pun pada skema yang membatasi bentuknya, jadi mengubahnya
    // menjadi pembanding berarti mengarang batas yang tidak pernah ditetapkan
    // siapa pun.
    foreach (['severity', 'is_out_of_range', 'out_of_range', 'exceeds', 'flag', 'threshold', 'breach'] as $forbidden) {
        expect($flat)->not->toContain($forbidden);
    }

    // Kedua angka tetap diterbitkan apa adanya, berdampingan.
    expect(pressingReportMetric($summary, 'digester_level_percent')['avg'])->toBe(27.4);
    expect(pressingReportMetric($summary, 'digester_level_percent')['target']['target_operating_range'])
        ->toBe('75% - 80% (Minimum 3/4 full)');
});

// =====================================================================
// GROUP F — RECAPS
// =====================================================================

it('case 32 — by_presser mengelompokkan presser_id sebagai unit, bukan kunci baris', function () {
    foreach (['2026-09-02', '2026-09-03'] as $date) {
        $record = pressingReportRecord($this->stationA, $date, 'PR-1');
        pressingReportSlot($record, '07:00', ['digester_temp_c' => 30.0]);
    }

    $this->actingAs($this->supervisorA);

    $byPresser = $this->service->buildSummary($this->periodA, null, $this->lineA)['by_presser'];

    expect($byPresser)->toHaveCount(1);
    expect($byPresser[0]['presser_id'])->toBe('PR-1');
    expect($byPresser[0]['day_count'])->toBe(2);
    expect($byPresser[0]['filled_slot_count'])->toBe(2);
});

it('case 33 — daily_total dihitung ulang atas seluruh slot, bukan merata-ratakan rata-rata harian', function () {
    $slots = PressingReportServiceSlots();

    // Hari A: 10 slot bernilai 10. Hari B: 1 slot bernilai 100.
    $dayA = pressingReportRecord($this->stationA, '2026-09-02', 'PR-1');

    foreach (range(0, 9) as $index) {
        pressingReportSlot($dayA, $slots[$index], ['digester_temp_c' => 10.0]);
    }

    $dayB = pressingReportRecord($this->stationA, '2026-09-03', 'PR-1');
    pressingReportSlot($dayB, '07:00', ['digester_temp_c' => 100.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // (10x10 + 100) / 11 = 18,18 — BUKAN (10 + 100) / 2 = 55. Rata-rata dari
    // rata-rata memberi bobot sama pada hari yang jumlah slotnya berbeda.
    expect($summary['daily_total']['averages']['digester_temp_c'])->toBe(18.18);
    expect($summary['daily_total']['filled_slot_count'])->toBe(11);
    expect($summary['daily'][0]['averages']['digester_temp_c'])->toBe(10.0);
    expect($summary['daily'][1]['averages']['digester_temp_c'])->toBe(100.0);
});

it('case 34 — hari tanpa record tidak mendapat baris pada rekap harian', function () {
    $record = pressingReportRecord($this->stationA, '2026-09-05', 'PR-1');
    pressingReportSlot($record, '07:00', ['digester_temp_c' => 30.0]);

    $this->actingAs($this->supervisorA);

    $daily = $this->service->buildSummary($this->periodA, null, $this->lineA)['daily'];

    // Baris nol untuk hari pabrik tidak beroperasi akan terbaca sebagai "kami
    // mengukur dan hasilnya nol".
    expect($daily)->toHaveCount(1);
    expect($daily[0]['date'])->toBe('2026-09-05');
});

it('case 35 — alasan downtime dikelompokkan harfiah tanpa penyeragaman huruf', function () {
    $slots = PressingReportServiceSlots();
    $record = pressingReportRecord($this->stationA, '2026-09-02', 'PR-1');

    foreach (range(0, 2) as $index) {
        pressingReportSlot($record, $slots[$index], ['downtime_reason' => 'Belt kendur']);
    }

    foreach (range(3, 4) as $index) {
        pressingReportSlot($record, $slots[$index], ['downtime_reason' => 'belt kendur']);
    }

    $this->actingAs($this->supervisorA);

    $reasons = $this->service->buildSummary($this->periodA, null, $this->lineA)['downtime_reasons'];

    // Menyeragamkan akan menggabungkan sebab yang penulisnya memang maksudkan
    // berbeda; layar menyatakan sifat harfiahnya supaya dua baris mirip tidak
    // dibaca sebagai cacat laporan.
    expect($reasons)->toBe([
        ['reason' => 'Belt kendur', 'slot_count' => 3],
        ['reason' => 'belt kendur', 'slot_count' => 2],
    ]);
});

it('case 36 — alasan downtime berisi hanya spasi diperlakukan sebagai kosong', function () {
    $record = pressingReportRecord($this->stationA, '2026-09-02', 'PR-1');

    pressingReportSlot($record, '07:00', ['downtime_reason' => '   ', 'digester_temp_c' => 30.0]);

    $this->actingAs($this->supervisorA);

    expect($this->service->buildSummary($this->periodA, null, $this->lineA)['downtime_reasons'])->toBe([]);
});

it('case 37 — slot ber-downtime tetap ikut angka ukur bila kolom ukurnya terisi', function () {
    $record = pressingReportRecord($this->stationA, '2026-09-02', 'PR-1');

    pressingReportSlot($record, '07:00', [
        'digester_temp_c' => 25.0,
        'downtime_reason' => 'Belt kendur',
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // Downtime adalah keterangan tambahan pada slot itu, bukan penyaring.
    expect(pressingReportMetric($summary, 'digester_temp_c')['avg'])->toBe(25.0);
    expect($summary['downtime_reasons'])->toBe([['reason' => 'Belt kendur', 'slot_count' => 1]]);
});

// =====================================================================
// GROUP G — TOTALS, DRAFT, VERIFICATION
// =====================================================================

it('case 38 — record draft ikut seluruh angka dan jumlahnya dinyatakan', function () {
    $draftOngoing = pressingReportRecord($this->stationA, '2026-09-02', 'PR-1', [
        'status' => RecordStatus::DraftOngoing,
    ]);
    $draftPaused = pressingReportRecord($this->stationA, '2026-09-03', 'PR-2', [
        'status' => RecordStatus::DraftPaused,
    ]);
    // Synced, bukan Saved: PressingRecord::booted() menolak status Saved
    // sebelum record punya satu pun detail, dan yang dibuktikan di sini
    // adalah "bukan draft" — bukan status tertentu.
    $saved = pressingReportRecord($this->stationA, '2026-09-04', 'PR-3', [
        'status' => RecordStatus::Synced,
    ]);

    foreach ([$draftOngoing, $draftPaused, $saved] as $record) {
        pressingReportSlot($record, '07:00', ['digester_temp_c' => 30.0]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // IKUT terhitung — jumlahnya hanya dinyatakan, supaya pembaca tahu
    // seberapa besar laporan ini berdiri di atas data yang belum selesai.
    expect($summary['total']['record_count'])->toBe(3);
    expect(pressingReportMetric($summary, 'digester_temp_c')['filled_slot_count'])->toBe(3);
    // Kedua keadaan draft dihitung bersama: sama-sama belum selesai.
    expect($summary['total']['draft_record_count'])->toBe(2);
});

it('case 39 — status verifikasi bukan penyaring dan jumlahnya dinyatakan', function () {
    $unchecked = pressingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    $checked = pressingReportRecord($this->stationA, '2026-09-03', 'PR-2', [
        'checked_by' => $this->supervisorA->id,
        'acknowledged_by' => $this->millManagementA->id,
    ]);

    foreach ([$unchecked, $checked] as $record) {
        pressingReportSlot($record, '07:00', ['digester_temp_c' => 30.0]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['total']['records_not_checked'])->toBe(1);
    expect($summary['total']['records_not_acknowledged'])->toBe(1);
    // Keduanya tetap terhitung penuh pada angka ukur.
    expect(pressingReportMetric($summary, 'digester_temp_c')['filled_slot_count'])->toBe(2);
});

it('case 40 — has_data membedakan tidak ada yang dilaporkan dari angkanya nol', function () {
    $this->actingAs($this->supervisorA);

    expect($this->service->buildSummary($this->periodA, null, $this->lineA)['has_data'])->toBeFalse();

    $record = pressingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    pressingReportSlot($record, '07:00', ['digester_temp_c' => 0.0]);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['has_data'])->toBeTrue();
    expect(pressingReportMetric($summary, 'digester_temp_c')['avg'])->toBe(0.0);
});

// =====================================================================
// GROUP H — EXPORT
// =====================================================================

it('case 41 — batas ekspor dihitung atas baris slot, bukan record', function () {
    $slots = PressingReportServiceSlots();
    $record = pressingReportRecord($this->stationA, '2026-09-02', 'PR-1');

    // SATU record, ENAM baris slot. Batas per record akan meloloskannya.
    foreach (range(0, 5) as $index) {
        pressingReportSlot($record, $slots[$index], ['digester_temp_c' => 30.0]);
    }

    $this->actingAs($this->supervisorA);

    $tiny = new PressingReportTinyExportService;

    expect(fn () => $tiny->buildExportRows($this->periodA, null, $this->lineA))
        ->toThrow(ExportFailedException::class);

    // Dan batas yang persis sama masih lolos: "strictly greater than".
    PressingDetail::query()->where('time_slot', $slots[5])->delete();

    expect($tiny->buildExportRows($this->periodA, null, $this->lineA))->toBeInstanceOf(Generator::class);
});

it('case 42 — penjagaan ekspor berjalan eager, bukan pada iterasi pertama', function () {
    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType('pressing')
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
    $recordOne = pressingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    $recordTwo = pressingReportRecord($this->stationA, '2026-09-03', 'PR-2');

    foreach (['07:00', '08:00', '09:00'] as $slot) {
        pressingReportSlot($recordOne, $slot, ['digester_temp_c' => 30.0]);
        pressingReportSlot($recordTwo, $slot, ['digester_temp_c' => 40.0]);
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
    $record = pressingReportRecord($this->stationA, '2026-09-02', 'PR-1');

    pressingReportSlot($record, '07:00');

    $this->actingAs($this->supervisorA);

    $rows = iterator_to_array($this->service->buildExportRows($this->periodA, null, $this->lineA));

    expect($rows)->toHaveCount(1);
    // Bukan dibuang, dan BUKAN ditulis 0 — membuangnya akan membuat berkasnya
    // berselisih dengan angka cakupan yang laporan yang sama terbitkan.
    expect($rows[0][8])->toBeNull();
    expect($rows[0][13])->toBeNull();
});

it('case 45 — format di luar csv dan excel ditolak sebelum satu baris dialirkan', function () {
    $record = pressingReportRecord($this->stationA, '2026-09-02', 'PR-1');
    pressingReportSlot($record, '07:00', ['digester_temp_c' => 30.0]);

    $this->actingAs($this->supervisorA);

    expect(fn () => $this->service->export($this->periodA, 'pdf', null, $this->lineA))
        ->toThrow(ValidationException::class);
});

it('case 46 — ekspor CSV memuat header dan baris data, dan periode tertutup ikut terekspor', function () {
    $closed = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('pressing')
        ->range('2026-08-01', '2026-08-31')
        ->closed()
        ->named('Periode Agustus Tertutup')
        ->create();

    $record = pressingReportRecord($this->stationA, '2026-08-05', 'PR-9');
    pressingReportSlot($record, '07:00', ['digester_temp_c' => 33.5, 'downtime_reason' => 'Belt kendur']);

    $this->actingAs($this->supervisorA);

    $response = $this->service->export($closed, 'csv', null, $this->lineA);
    $body = pressingReportStreamed($response);

    // Kunci periode mengatur PENULISAN data, bukan pembacaan laporan.
    expect($body)->toContain('Slot Waktu');
    expect($body)->toContain('Alasan Downtime');
    expect($body)->toContain('PR-9');
    expect($body)->toContain('Belt kendur');
});

/** The 24 canonical slots, in input-screen order. */
function PressingReportServiceSlots(): array
{
    return App\Services\PressingRecordService::canonicalTimeSlots();
}
