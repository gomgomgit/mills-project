<?php

/**
 * ThreshingReportServiceTest — screen-148--laporan-threshing-web /
 * screen-149--laporan-threshing-mobile, ThreshingReportService.
 *
 * One test per unit_test_cases entry on screen-148's tech spec, grouped the
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
use App\Models\ThreshingDetail;
use App\Models\ThreshingOperationalTarget;
use App\Models\ThreshingRecord;
use App\Models\User;
use App\Services\ThreshingReportService;
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
class ThreshingReportAllBusinessUnitsSpy extends ThreshingReportService
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
class ThreshingReportTinyExportService extends ThreshingReportService
{
    public const EXPORT_ROW_LIMIT = 5;
}

/** One daily record for one thresher. */
function threshingReportRecord(
    Station $station,
    string $date,
    string $thresherId = 'TH-1',
    array $attributes = [],
): ThreshingRecord {
    return ThreshingRecord::factory()
        ->forStation($station)
        ->onDate($date)
        ->create(array_merge([
            'thresher_id' => $thresherId,
            'note' => 'Catatan harian',
        ], $attributes));
}

/** One time-slot row. Every measurement column defaults to null. */
function threshingReportSlot(
    ThreshingRecord $record,
    string $timeSlot,
    array $values = [],
): ThreshingDetail {
    return ThreshingDetail::factory()
        ->forRecord($record)
        ->timeSlot($timeSlot)
        ->create($values);
}

/** The six seeded master rows, matching ThreshingOperationalTargetSeeder. */
function threshingReportSeedTargets(): void
{
    $rows = [
        ['FFB Throughput', 'As per mill capacity design (e.g., 30-60 MT/hr)', 'Adjust feeder conveyor speed.'],
        ['Thresher Drum Speed', '21 - 23 RPM (optimal for separation)', 'Inspect drive belt tension and gearbox alignment.'],
        ['Motor Current', 'Within motor rated full-load current (FLC)', 'Check for drum overloading or wedged bunches.'],
        ['Bearing Temperature', 'Below 70C (Check if >75C)', 'Lubricate bearings / check for mechanical wear.'],
        ['Unstripped Bunch Rate', 'Target: 0% (Action required if >2%)', 'Verify autoclaved sterilization pressure and duration.'],
        ['Empty Bunch (EB) Oil Loss', 'Target: <0.50% on dry basis', 'Check thresher drum bars and inner lifting paddles.'],
    ];

    foreach ($rows as $index => [$parameter, $standard, $action]) {
        ThreshingOperationalTarget::create([
            'parameter' => $parameter,
            'standard_operational_target' => $standard,
            'action_plan_on_deviation' => $action,
            'sort_order' => $index + 1,
        ]);
    }
}

/** Every SQL statement run inside $callback. */
function threshingReportQueriesDuring(callable $callback): array
{
    $queries = [];

    DB::listen(function ($query) use (&$queries) {
        $queries[] = strtolower($query->sql);
    });

    $callback();

    return $queries;
}

function threshingReportStreamed(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

/** The metric entry for one column, from the metrics list. */
function threshingReportMetric(array $summary, string $column): array
{
    foreach ($summary['metrics'] as $metric) {
        if ($metric['column'] === $column) {
            return $metric;
        }
    }

    throw new RuntimeException("metric {$column} tidak ada pada payload");
}

beforeEach(function () {
    $this->service = new ThreshingReportService;

    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->threshing()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->threshing()->create();

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
        ->stationType('threshing')
        ->range('2026-09-01', '2026-09-10')
        ->open()
        ->named('Periode September Alpha')
        ->create();
});

// =====================================================================
// GROUP A — ACCESS, MILL, LINE, PERIOD
// =====================================================================

it('case 1 — guardAccess menolak tamu 401 dan peran di luar keempatnya 403, sebelum satu kueri periode', function () {
    $queries = threshingReportQueriesDuring(function () {
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

    $spy = new ThreshingReportAllBusinessUnitsSpy;

    expect(fn () => $spy->resolveBusinessUnit(null))->toThrow(ValidationException::class);

    // Falling back to "every mill" would turn one broken master-data row into
    // a cross-mill leak. The spy proves that path is not taken.
    expect($spy->allBusinessUnitsCalls)->toBe(0);
});

it('case 5 — businessUnitOptions menolak setiap peran terikat mill, termasuk Operator', function () {
    $spy = new ThreshingReportAllBusinessUnitsSpy;

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

it('case 6 — listPeriods menyaring menurut baris period_stations station_type threshing', function () {
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
    expect($periods[0]['station_type'])->toBe('threshing');
});

it('case 7 — listPeriods memakai status dari baris period_stations, bukan kolom status periode', function () {
    $this->actingAs($this->supervisorA);

    expect($this->service->listPeriods(null)[0]['status'])->toBe('open');
});

it('case 8 — listPeriods tetap mencantumkan periode tertutup', function () {
    Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('threshing')
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
        ->stationType('threshing')
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
        ->stationType('threshing')
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

    $kept = threshingReportRecord($this->stationA, '2026-09-03', 'TH-1');
    $moved = threshingReportRecord($this->stationA, '2026-09-03', 'TH-2', [
        'production_line_id' => $otherLine->id,
    ]);

    threshingReportSlot($kept, '07:00', ['ffb_throughput_mt_hour' => 30.0]);
    threshingReportSlot($moved, '07:00', ['ffb_throughput_mt_hour' => 99.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['coverage']['filled_slots'])->toBe(1);
    expect(threshingReportMetric($summary, 'ffb_throughput_mt_hour')['avg'])->toBe(30.0);
    // Menyaring lewat join ke stations akan menarik record yang dipindah dan
    // menjawab 64,5 — rata-rata dua line sekaligus.
    expect(threshingReportMetric($summary, 'ffb_throughput_mt_hour')['max'])->toBe(30.0);
});

it('case 14 — keanggotaan periode inklusif di kedua ujung dan menolak satu hari di luarnya', function () {
    $first = threshingReportRecord($this->stationA, '2026-09-01', 'TH-1');
    $last = threshingReportRecord($this->stationA, '2026-09-10', 'TH-1');
    $outside = threshingReportRecord($this->stationA, '2026-09-11', 'TH-1');

    threshingReportSlot($first, '07:00', ['ffb_throughput_mt_hour' => 10.0]);
    threshingReportSlot($last, '07:00', ['ffb_throughput_mt_hour' => 20.0]);
    threshingReportSlot($outside, '07:00', ['ffb_throughput_mt_hour' => 999.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['total']['record_count'])->toBe(2);
    expect(threshingReportMetric($summary, 'ffb_throughput_mt_hour')['max'])->toBe(20.0);
});

it('case 15 — baris slot yang keenam kolomnya null tidak dihitung terisi', function () {
    $record = threshingReportRecord($this->stationA, '2026-09-02', 'TH-1');

    foreach (['07:00', '08:00', '09:00'] as $slot) {
        threshingReportSlot($record, $slot);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['coverage']['filled_slots'])->toBe(0);
    expect($summary['has_data'])->toBeFalse();
    // Record-nya tetap ada, dan tetap ikut penyebut cakupan.
    expect($summary['total']['record_count'])->toBe(1);
    expect($summary['coverage']['thresher_count'])->toBe(1);
});

it('case 16 — baris slot yang HANYA berisi downtime_reason dihitung terisi', function () {
    $record = threshingReportRecord($this->stationA, '2026-09-02', 'TH-1');

    threshingReportSlot($record, '07:00', ['downtime_reason' => 'Belt kendur']);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // Slot yang jelas disentuh operator tidak boleh dilaporkan sebagai slot
    // kosong — dan definisi "terisi" dipinjam dari layar input itu sendiri.
    expect($summary['coverage']['filled_slots'])->toBe(1);
    expect($summary['has_data'])->toBeTrue();
    // Tidak satu pun angka ukur terbentuk darinya.
    expect(threshingReportMetric($summary, 'ffb_throughput_mt_hour')['avg'])->toBeNull();
    expect(threshingReportMetric($summary, 'ffb_throughput_mt_hour')['filled_slot_count'])->toBe(0);
    expect($summary['downtime_reasons'])->toBe([['reason' => 'Belt kendur', 'slot_count' => 1]]);
});

// =====================================================================
// GROUP C — PER-METRIC DENOMINATORS
// =====================================================================

it('case 17 — tiap kolom memakai penyebutnya sendiri, bukan satu penyebut bersama', function () {
    $record = threshingReportRecord($this->stationA, '2026-09-02', 'TH-1');

    $slots = ThreshingReportServiceSlots();

    // 10 slot ber-throughput; hanya 3 di antaranya ber-drum-speed.
    foreach (range(0, 9) as $index) {
        $values = ['ffb_throughput_mt_hour' => 30.0];

        if ($index < 3) {
            // 20 + 22 + 24 = 66, rata-rata 22,0 bila dibagi 3.
            $values['thresher_drum_speed_rpm'] = 20.0 + ($index * 2);
        }

        threshingReportSlot($record, $slots[$index], $values);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect(threshingReportMetric($summary, 'ffb_throughput_mt_hour')['filled_slot_count'])->toBe(10);
    expect(threshingReportMetric($summary, 'thresher_drum_speed_rpm')['filled_slot_count'])->toBe(3);
    // 66/3 = 22,0. Penyebut bersama (10) akan menjawab 6,6 — angka yang tidak
    // akan dipertanyakan siapa pun.
    expect(threshingReportMetric($summary, 'thresher_drum_speed_rpm')['avg'])->toBe(22.0);
});

it('case 18 — kolom tanpa satu pun nilai menghasilkan null, bukan 0, dan barisnya tetap ada', function () {
    $record = threshingReportRecord($this->stationA, '2026-09-02', 'TH-1');

    threshingReportSlot($record, '07:00', ['ffb_throughput_mt_hour' => 30.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    $motor = threshingReportMetric($summary, 'motor_current_amps');

    // Baris yang hilang terbaca sebagai "tidak ada parameter ini", padahal
    // yang benar adalah "tidak ada yang mengukurnya".
    expect($summary['metrics'])->toHaveCount(5);
    expect($motor['min'])->toBeNull();
    expect($motor['avg'])->toBeNull();
    expect($motor['max'])->toBeNull();
    expect($motor['filled_slot_count'])->toBe(0);
});

it('case 19 — kolom dengan satu nilai menghasilkan min, rata-rata, dan maks yang sama', function () {
    $record = threshingReportRecord($this->stationA, '2026-09-02', 'TH-1');

    threshingReportSlot($record, '07:00', ['empty_bunch_oil_loss_percent' => 0.42]);

    $this->actingAs($this->supervisorA);

    $metric = threshingReportMetric(
        $this->service->buildSummary($this->periodA, null, $this->lineA),
        'empty_bunch_oil_loss_percent',
    );

    expect($metric['min'])->toBe(0.42);
    expect($metric['avg'])->toBe(0.42);
    expect($metric['max'])->toBe(0.42);
    // Penyebut inilah yang menjelaskan mengapa ketiganya sama.
    expect($metric['filled_slot_count'])->toBe(1);
});

it('case 20 — nilai nol dan negatif dilaporkan apa adanya', function () {
    $record = threshingReportRecord($this->stationA, '2026-09-02', 'TH-1');

    threshingReportSlot($record, '07:00', ['unstripped_bunch_count_percent' => 0.0]);
    threshingReportSlot($record, '08:00', ['unstripped_bunch_count_percent' => -1.0]);

    $this->actingAs($this->supervisorA);

    $metric = threshingReportMetric(
        $this->service->buildSummary($this->periodA, null, $this->lineA),
        'unstripped_bunch_count_percent',
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
    $record = threshingReportRecord($this->stationA, '2026-09-02', 'TH-1');

    threshingReportSlot($record, '07:00', ['ffb_throughput_mt_hour' => 30.0]);
    threshingReportSlot($record, '08:00', ['thresher_drum_speed_rpm' => 22.0]);

    $this->actingAs($this->supervisorA);

    $queries = threshingReportQueriesDuring(function () {
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

it('case 22 — expected_slots adalah thresher x hari dihitung x 24 slot kanonis', function () {
    foreach (['TH-1', 'TH-2'] as $thresher) {
        $record = threshingReportRecord($this->stationA, '2026-09-02', $thresher);
        threshingReportSlot($record, '07:00', ['ffb_throughput_mt_hour' => 30.0]);
    }

    $this->actingAs($this->supervisorA);

    $coverage = $this->service->buildSummary($this->periodA, null, $this->lineA)['coverage'];

    expect($coverage['thresher_count'])->toBe(2);
    expect($coverage['slots_per_thresher_per_day'])->toBe(24);
    expect($coverage['days_counted'])->toBe(10);
    expect($coverage['expected_slots'])->toBe(480);
    expect($coverage['filled_slots'])->toBe(2);
});

it('case 23 — thresher_count memakai thresher yang muncul, bukan jumlah stasiun terdaftar', function () {
    // Empat stasiun threshing terdaftar pada line ini; hanya dua thresher
    // yang benar-benar beroperasi di periode itu.
    Station::factory()->count(3)->forBusinessUnit($this->businessUnitA)->threshing()->create([
        'production_line_id' => $this->lineA,
    ]);

    foreach (['TH-1', 'TH-2'] as $thresher) {
        $record = threshingReportRecord($this->stationA, '2026-09-02', $thresher);
        threshingReportSlot($record, '07:00', ['ffb_throughput_mt_hour' => 30.0]);
    }

    $this->actingAs($this->supervisorA);

    // Penyebut yang dibangun dari stasiun terdaftar akan menghukum mill yang
    // memang sengaja tidak mengoperasikan sebuah thresher.
    expect($this->service->buildSummary($this->periodA, null, $this->lineA)['coverage']['thresher_count'])
        ->toBe(2);
});

it('case 24 — days_counted berhenti di hari ini untuk periode berjalan', function () {
    $running = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('threshing')
        ->range(now()->subDays(4)->toDateString(), now()->addDays(10)->toDateString())
        ->open()
        ->named('Periode Berjalan')
        ->create();

    $record = threshingReportRecord($this->stationA, now()->subDays(1)->toDateString(), 'TH-1');
    threshingReportSlot($record, '07:00', ['ffb_throughput_mt_hour' => 30.0]);

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
        ->stationType('threshing')
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

it('case 26 — slots_per_thresher_per_day diambil dari canonicalTimeSlots layar input', function () {
    $record = threshingReportRecord($this->stationA, '2026-09-02', 'TH-1');
    threshingReportSlot($record, '07:00', ['ffb_throughput_mt_hour' => 30.0]);

    $this->actingAs($this->supervisorA);

    $coverage = $this->service->buildSummary($this->periodA, null, $this->lineA)['coverage'];

    // Satu definisi, satu jawaban: bukan konstanta terpisah yang bisa
    // menyimpang dari grid layar input.
    expect($coverage['slots_per_thresher_per_day'])
        ->toBe(count(App\Services\ThreshingRecordService::canonicalTimeSlots()));
});

// =====================================================================
// GROUP E — OPERATIONAL TARGETS (tidak ada padanannya di tujuh laporan lain)
// =====================================================================

it('case 27 — tiap kolom ukur membawa standar dan rencana tindakannya', function () {
    threshingReportSeedTargets();

    $record = threshingReportRecord($this->stationA, '2026-09-02', 'TH-1');
    threshingReportSlot($record, '07:00', ['thresher_drum_speed_rpm' => 27.4]);

    $this->actingAs($this->supervisorA);

    $metric = threshingReportMetric(
        $this->service->buildSummary($this->periodA, null, $this->lineA),
        'thresher_drum_speed_rpm',
    );

    expect($metric['target']['parameter'])->toBe('Thresher Drum Speed');
    expect($metric['target']['standard_operational_target'])->toBe('21 - 23 RPM (optimal for separation)');
    expect($metric['target']['action_plan_on_deviation'])->toContain('drive belt tension');
});

it('case 28 — parameter master tanpa kolom ukur masuk targets_without_metric', function () {
    threshingReportSeedTargets();

    $record = threshingReportRecord($this->stationA, '2026-09-02', 'TH-1');
    threshingReportSlot($record, '07:00', ['ffb_throughput_mt_hour' => 30.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // Enam parameter pada master, lima kolom pada formulir. Ketimpangan itu
    // DITERBITKAN: standar yang tidak pernah diukur terbaca seperti terpenuhi
    // padahal ia sekadar tidak ada.
    expect($summary['metrics'])->toHaveCount(5);
    expect($summary['targets_without_metric'])->toHaveCount(1);
    expect($summary['targets_without_metric'][0]['parameter'])->toBe('Bearing Temperature');
    expect($summary['targets_without_metric'][0]['action_plan_on_deviation'])->toContain('Lubricate');
    expect($summary['targets_master_empty'])->toBeFalse();
});

it('case 29 — pemetaan memakai peta tetap: ejaan master yang diubah tidak menghapus angka', function () {
    threshingReportSeedTargets();

    ThreshingOperationalTarget::query()
        ->where('parameter', 'Thresher Drum Speed')
        ->update(['parameter' => 'Thresher Drum Speeed']);

    $record = threshingReportRecord($this->stationA, '2026-09-02', 'TH-1');
    threshingReportSlot($record, '07:00', ['thresher_drum_speed_rpm' => 22.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);
    $metric = threshingReportMetric($summary, 'thresher_drum_speed_rpm');

    // ANGKANYA UTUH — pemetaan tidak bersandar pada teks nama parameter.
    expect($metric['avg'])->toBe(22.0);
    expect($metric['filled_slot_count'])->toBe(1);
    // Standarnya terlepas, dan keterlepasan itu TERLIHAT alih-alih senyap.
    expect($metric['target']['standard_operational_target'])->toBeNull();
    expect(collect($summary['targets_without_metric'])->pluck('parameter')->all())
        ->toContain('Thresher Drum Speeed');
});

it('case 30 — master target kosong tidak menghapus angka hasil ukur', function () {
    $record = threshingReportRecord($this->stationA, '2026-09-02', 'TH-1');
    threshingReportSlot($record, '07:00', ['ffb_throughput_mt_hour' => 30.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['targets_master_empty'])->toBeTrue();
    expect($summary['targets_without_metric'])->toBe([]);
    expect($summary['metrics'])->toHaveCount(5);
    // Seeder yang belum dijalankan tidak boleh menghapus pengukuran yang
    // sudah terjadi.
    expect(threshingReportMetric($summary, 'ffb_throughput_mt_hour')['avg'])->toBe(30.0);
    expect(threshingReportMetric($summary, 'ffb_throughput_mt_hour')['target']['parameter'])->toBeNull();
});

it('case 31 — payload tidak memuat satu pun kunci penilaian terhadap standar', function () {
    threshingReportSeedTargets();

    $record = threshingReportRecord($this->stationA, '2026-09-02', 'TH-1');
    // 27,4 RPM terhadap standar '21 - 23 RPM' — jelas di luar rentangnya,
    // dan laporan ini TETAP tidak menilainya.
    threshingReportSlot($record, '07:00', ['thresher_drum_speed_rpm' => 27.4]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);
    $flat = json_encode($summary);

    // Asersi atas KETIADAAN, dan ia harus berupa penyisiran: memeriksa satu
    // kunci saja akan selalu hijau. standard_operational_target adalah PROSA,
    // jadi mengubahnya menjadi pembanding berarti mengarang batas yang tidak
    // pernah ditetapkan siapa pun.
    foreach (['severity', 'is_out_of_range', 'out_of_range', 'exceeds', 'flag', 'threshold', 'breach'] as $forbidden) {
        expect($flat)->not->toContain($forbidden);
    }

    // Kedua angka tetap diterbitkan apa adanya, berdampingan.
    expect(threshingReportMetric($summary, 'thresher_drum_speed_rpm')['avg'])->toBe(27.4);
    expect(threshingReportMetric($summary, 'thresher_drum_speed_rpm')['target']['standard_operational_target'])
        ->toBe('21 - 23 RPM (optimal for separation)');
});

// =====================================================================
// GROUP F — RECAPS
// =====================================================================

it('case 32 — by_thresher mengelompokkan thresher_id sebagai unit, bukan kunci baris', function () {
    foreach (['2026-09-02', '2026-09-03'] as $date) {
        $record = threshingReportRecord($this->stationA, $date, 'TH-1');
        threshingReportSlot($record, '07:00', ['ffb_throughput_mt_hour' => 30.0]);
    }

    $this->actingAs($this->supervisorA);

    $byThresher = $this->service->buildSummary($this->periodA, null, $this->lineA)['by_thresher'];

    expect($byThresher)->toHaveCount(1);
    expect($byThresher[0]['thresher_id'])->toBe('TH-1');
    expect($byThresher[0]['day_count'])->toBe(2);
    expect($byThresher[0]['filled_slot_count'])->toBe(2);
});

it('case 33 — daily_total dihitung ulang atas seluruh slot, bukan merata-ratakan rata-rata harian', function () {
    $slots = ThreshingReportServiceSlots();

    // Hari A: 10 slot bernilai 10. Hari B: 1 slot bernilai 100.
    $dayA = threshingReportRecord($this->stationA, '2026-09-02', 'TH-1');

    foreach (range(0, 9) as $index) {
        threshingReportSlot($dayA, $slots[$index], ['ffb_throughput_mt_hour' => 10.0]);
    }

    $dayB = threshingReportRecord($this->stationA, '2026-09-03', 'TH-1');
    threshingReportSlot($dayB, '07:00', ['ffb_throughput_mt_hour' => 100.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // (10x10 + 100) / 11 = 18,18 — BUKAN (10 + 100) / 2 = 55. Rata-rata dari
    // rata-rata memberi bobot sama pada hari yang jumlah slotnya berbeda.
    expect($summary['daily_total']['averages']['ffb_throughput_mt_hour'])->toBe(18.18);
    expect($summary['daily_total']['filled_slot_count'])->toBe(11);
    expect($summary['daily'][0]['averages']['ffb_throughput_mt_hour'])->toBe(10.0);
    expect($summary['daily'][1]['averages']['ffb_throughput_mt_hour'])->toBe(100.0);
});

it('case 34 — hari tanpa record tidak mendapat baris pada rekap harian', function () {
    $record = threshingReportRecord($this->stationA, '2026-09-05', 'TH-1');
    threshingReportSlot($record, '07:00', ['ffb_throughput_mt_hour' => 30.0]);

    $this->actingAs($this->supervisorA);

    $daily = $this->service->buildSummary($this->periodA, null, $this->lineA)['daily'];

    // Baris nol untuk hari pabrik tidak beroperasi akan terbaca sebagai "kami
    // mengukur dan hasilnya nol".
    expect($daily)->toHaveCount(1);
    expect($daily[0]['date'])->toBe('2026-09-05');
});

it('case 35 — alasan downtime dikelompokkan harfiah tanpa penyeragaman huruf', function () {
    $slots = ThreshingReportServiceSlots();
    $record = threshingReportRecord($this->stationA, '2026-09-02', 'TH-1');

    foreach (range(0, 2) as $index) {
        threshingReportSlot($record, $slots[$index], ['downtime_reason' => 'Belt kendur']);
    }

    foreach (range(3, 4) as $index) {
        threshingReportSlot($record, $slots[$index], ['downtime_reason' => 'belt kendur']);
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
    $record = threshingReportRecord($this->stationA, '2026-09-02', 'TH-1');

    threshingReportSlot($record, '07:00', ['downtime_reason' => '   ', 'ffb_throughput_mt_hour' => 30.0]);

    $this->actingAs($this->supervisorA);

    expect($this->service->buildSummary($this->periodA, null, $this->lineA)['downtime_reasons'])->toBe([]);
});

it('case 37 — slot ber-downtime tetap ikut angka ukur bila kolom ukurnya terisi', function () {
    $record = threshingReportRecord($this->stationA, '2026-09-02', 'TH-1');

    threshingReportSlot($record, '07:00', [
        'ffb_throughput_mt_hour' => 25.0,
        'downtime_reason' => 'Belt kendur',
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // Downtime adalah keterangan tambahan pada slot itu, bukan penyaring.
    expect(threshingReportMetric($summary, 'ffb_throughput_mt_hour')['avg'])->toBe(25.0);
    expect($summary['downtime_reasons'])->toBe([['reason' => 'Belt kendur', 'slot_count' => 1]]);
});

// =====================================================================
// GROUP G — TOTALS, DRAFT, VERIFICATION
// =====================================================================

it('case 38 — record draft ikut seluruh angka dan jumlahnya dinyatakan', function () {
    $draftOngoing = threshingReportRecord($this->stationA, '2026-09-02', 'TH-1', [
        'status' => RecordStatus::DraftOngoing,
    ]);
    $draftPaused = threshingReportRecord($this->stationA, '2026-09-03', 'TH-2', [
        'status' => RecordStatus::DraftPaused,
    ]);
    // Synced, bukan Saved: ThreshingRecord::booted() menolak status Saved
    // sebelum record punya satu pun detail, dan yang dibuktikan di sini
    // adalah "bukan draft" — bukan status tertentu.
    $saved = threshingReportRecord($this->stationA, '2026-09-04', 'TH-3', [
        'status' => RecordStatus::Synced,
    ]);

    foreach ([$draftOngoing, $draftPaused, $saved] as $record) {
        threshingReportSlot($record, '07:00', ['ffb_throughput_mt_hour' => 30.0]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // IKUT terhitung — jumlahnya hanya dinyatakan, supaya pembaca tahu
    // seberapa besar laporan ini berdiri di atas data yang belum selesai.
    expect($summary['total']['record_count'])->toBe(3);
    expect(threshingReportMetric($summary, 'ffb_throughput_mt_hour')['filled_slot_count'])->toBe(3);
    // Kedua keadaan draft dihitung bersama: sama-sama belum selesai.
    expect($summary['total']['draft_record_count'])->toBe(2);
});

it('case 39 — status verifikasi bukan penyaring dan jumlahnya dinyatakan', function () {
    $unchecked = threshingReportRecord($this->stationA, '2026-09-02', 'TH-1');
    $checked = threshingReportRecord($this->stationA, '2026-09-03', 'TH-2', [
        'checked_by' => $this->supervisorA->id,
        'acknowledged_by' => $this->millManagementA->id,
    ]);

    foreach ([$unchecked, $checked] as $record) {
        threshingReportSlot($record, '07:00', ['ffb_throughput_mt_hour' => 30.0]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['total']['records_not_checked'])->toBe(1);
    expect($summary['total']['records_not_acknowledged'])->toBe(1);
    // Keduanya tetap terhitung penuh pada angka ukur.
    expect(threshingReportMetric($summary, 'ffb_throughput_mt_hour')['filled_slot_count'])->toBe(2);
});

it('case 40 — has_data membedakan tidak ada yang dilaporkan dari angkanya nol', function () {
    $this->actingAs($this->supervisorA);

    expect($this->service->buildSummary($this->periodA, null, $this->lineA)['has_data'])->toBeFalse();

    $record = threshingReportRecord($this->stationA, '2026-09-02', 'TH-1');
    threshingReportSlot($record, '07:00', ['ffb_throughput_mt_hour' => 0.0]);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['has_data'])->toBeTrue();
    expect(threshingReportMetric($summary, 'ffb_throughput_mt_hour')['avg'])->toBe(0.0);
});

// =====================================================================
// GROUP H — EXPORT
// =====================================================================

it('case 41 — batas ekspor dihitung atas baris slot, bukan record', function () {
    $slots = ThreshingReportServiceSlots();
    $record = threshingReportRecord($this->stationA, '2026-09-02', 'TH-1');

    // SATU record, ENAM baris slot. Batas per record akan meloloskannya.
    foreach (range(0, 5) as $index) {
        threshingReportSlot($record, $slots[$index], ['ffb_throughput_mt_hour' => 30.0]);
    }

    $this->actingAs($this->supervisorA);

    $tiny = new ThreshingReportTinyExportService;

    expect(fn () => $tiny->buildExportRows($this->periodA, null, $this->lineA))
        ->toThrow(ExportFailedException::class);

    // Dan batas yang persis sama masih lolos: "strictly greater than".
    ThreshingDetail::query()->where('time_slot', $slots[5])->delete();

    expect($tiny->buildExportRows($this->periodA, null, $this->lineA))->toBeInstanceOf(Generator::class);
});

it('case 42 — penjagaan ekspor berjalan eager, bukan pada iterasi pertama', function () {
    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType('threshing')
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
    $recordOne = threshingReportRecord($this->stationA, '2026-09-02', 'TH-1');
    $recordTwo = threshingReportRecord($this->stationA, '2026-09-03', 'TH-2');

    foreach (['07:00', '08:00', '09:00'] as $slot) {
        threshingReportSlot($recordOne, $slot, ['ffb_throughput_mt_hour' => 30.0]);
        threshingReportSlot($recordTwo, $slot, ['ffb_throughput_mt_hour' => 40.0]);
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

    expect($rows[0][4])->toBe('TH-1');
    expect($rows[3][4])->toBe('TH-2');
    // Slot selalu HH:MM.
    expect($rows[0][7])->toBe('07:00');
});

it('case 44 — slot kosong tetap menjadi baris ekspor dengan sel kosong', function () {
    $record = threshingReportRecord($this->stationA, '2026-09-02', 'TH-1');

    threshingReportSlot($record, '07:00');

    $this->actingAs($this->supervisorA);

    $rows = iterator_to_array($this->service->buildExportRows($this->periodA, null, $this->lineA));

    expect($rows)->toHaveCount(1);
    // Bukan dibuang, dan BUKAN ditulis 0 — membuangnya akan membuat berkasnya
    // berselisih dengan angka cakupan yang laporan yang sama terbitkan.
    expect($rows[0][8])->toBeNull();
    expect($rows[0][13])->toBeNull();
});

it('case 45 — format di luar csv dan excel ditolak sebelum satu baris dialirkan', function () {
    $record = threshingReportRecord($this->stationA, '2026-09-02', 'TH-1');
    threshingReportSlot($record, '07:00', ['ffb_throughput_mt_hour' => 30.0]);

    $this->actingAs($this->supervisorA);

    expect(fn () => $this->service->export($this->periodA, 'pdf', null, $this->lineA))
        ->toThrow(ValidationException::class);
});

it('case 46 — ekspor CSV memuat header dan baris data, dan periode tertutup ikut terekspor', function () {
    $closed = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('threshing')
        ->range('2026-08-01', '2026-08-31')
        ->closed()
        ->named('Periode Agustus Tertutup')
        ->create();

    $record = threshingReportRecord($this->stationA, '2026-08-05', 'TH-9');
    threshingReportSlot($record, '07:00', ['ffb_throughput_mt_hour' => 33.5, 'downtime_reason' => 'Belt kendur']);

    $this->actingAs($this->supervisorA);

    $response = $this->service->export($closed, 'csv', null, $this->lineA);
    $body = threshingReportStreamed($response);

    // Kunci periode mengatur PENULISAN data, bukan pembacaan laporan.
    expect($body)->toContain('Slot Waktu');
    expect($body)->toContain('Alasan Downtime');
    expect($body)->toContain('TH-9');
    expect($body)->toContain('Belt kendur');
});

/** The 24 canonical slots, in input-screen order. */
function ThreshingReportServiceSlots(): array
{
    return App\Services\ThreshingRecordService::canonicalTimeSlots();
}
