<?php

/**
 * KernelPlantReportServiceTest — screen-154--laporan-kernel-plant-web /
 * screen-155--laporan-kernel-plant-mobile, KernelPlantReportService.
 *
 * Satu uji per entri unit_test_cases pada tech spec screen-154, dikelompokkan
 * sama seperti DepricarpingReportServiceTest supaya keduanya dapat dibaca
 * berdampingan — laporan ini adalah PORT dari laporan Depricarping, dan yang
 * perlu terlihat adalah bagian mana yang diwarisi dan bagian mana yang baru.
 *
 * ────────────────────────────────────────────────────────────────────────
 * FIXTURE-NYA SENGAJA TIDAK RATA, DAN ITU JUSTRU INTINYA
 * ────────────────────────────────────────────────────────────────────────
 * Lima aturan service ini hanya dapat dibuktikan oleh data yang MELAWAN
 * jalan pintasnya:
 *
 *   1. TIAP METRIK PUNYA PENYEBUTNYA SENDIRI. Dengan ketujuh kolom terisi
 *      pada setiap slot, satu penyebut bersama memberi jawaban yang sama dan
 *      asersinya kosong. Karena itu ketujuh kolom di bawah diisi pada
 *      HIMPUNAN SLOT YANG BERBEDA-BEDA — 6 / 2 / 5 / 4 / 3 / 7 / 1 — dan
 *      diasersi per kolom, bukan sekali untuk semuanya.
 *
 *   2. coverage.filled_slots DAPAT MELEBIHI penyebut setiap metrik. Dua dari
 *      sembilan READING_FIELDS (downtime_minutes, findings) bukan kolom ukur,
 *      jadi slot yang hanya berisi temuan terhitung terisi tanpa menyumbang
 *      ke penyebut mana pun. Yang diasersi adalah KETIDAKSAMAAN-nya;
 *      kesamaan justru bug yang hendak dicegah.
 *
 *   3. ADA DUA PASANGAN BERBAGI STANDAR, bukan satu. Depricarping punya tepat
 *      satu ('Nut Silo Temperature'), jadi kode yang mengistimewakan satu
 *      pasangan lolos di sana dan salah di sini. Dan targets_without_metric
 *      dibandingkan terhadap HIMPUNAN 5 nama parameter, bukan count() atas 7
 *      entri peta — termasuk kasus master sengaja diisi 7 baris supaya
 *      count(master) === count(peta) dan implementasi berbasis count akan
 *      menerbitkan daftar kosong.
 *
 *   4. null BUKAN 0. coverage_percent null ketika PENYEBUTNYA tak terbentuk
 *      (expected_slots === 0), dan 0.0 ketika penyebutnya ada tetapi tak satu
 *      slot pun terisi — dua kasus berpasangan, keduanya ditulis.
 *      downtime.total_minutes null bila tak ada yang mencatat, tetapi 0 bila
 *      ada yang mencatat nol, dan nol itu IKUT recorded_slot_count.
 *
 *   5. TIDAK ADA PENANDAAN DI LUAR BATAS. Diasersi atas KETIADAAN KUNCI pada
 *      struktur payload dengan nilai yang disemai jauh di luar target — bukan
 *      atas ketiadaan frasa, karena kalimat di layar justru memuat kata "di
 *      luar batas", dan bukan atas kelas CSS, yang milik uji UI.
 *
 * Jangan "merapikan" angka-angka ini. Merapikannya mengubah separuh berkas
 * ini menjadi uji yang selalu hijau.
 *
 * TANGGAL FIXTURE SELALU RELATIF (now()->subDays(...)), tidak pernah tanggal
 * kalender yang dipaku: periodA di bawah harus SELALU sudah selesai supaya
 * days_counted === days_in_period, dan tanggal yang dipaku berhenti memenuhi
 * syarat itu begitu kalender melewatinya.
 *
 * Suite ini berjalan di SQLite in-memory sementara produksi PostgreSQL, yang
 * juga alasan penyaring periode diasersi di UJUNG-ujungnya (hari pertama,
 * hari terakhir, satu hari di luar): whereDate() versus where() biasa hanya
 * berbeda di sana, dan hanya di PostgreSQL. Tidak ada satu pun asersi di
 * berkas ini yang bersandar pada perilaku agregat SQL atas kolom bernull —
 * service-nya mengagregasi di PHP justru supaya perbedaan itu tidak ada.
 */

use App\Enums\RecordStatus;
use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Models\BusinessUnit;
use App\Models\KernelPlantDetail;
use App\Models\KernelPlantOperationalTarget;
use App\Models\KernelPlantRecord;
use App\Models\Period;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use App\Services\KernelPlantRecordService;
use App\Services\KernelPlantReportService;
use App\Services\StationReportService;
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
 * Spy yang membuktikan allBusinessUnits() tidak pernah tercapai pada jalur
 * gagal-tertutup. Perangkat yang sama dengan
 * DepricarpingReportAllBusinessUnitsSpy.
 */
class KernelPlantReportAllBusinessUnitsSpy extends KernelPlantReportService
{
    public int $allBusinessUnitsCalls = 0;

    public function allBusinessUnits(): Collection
    {
        $this->allBusinessUnitsCalls++;

        return parent::allBusinessUnits();
    }
}

/**
 * Menurunkan batas ekspor supaya perbedaan "baris slot versus record" dapat
 * dibuktikan tanpa menyemai 50.000 baris. Hanya mungkin karena service
 * membaca batasnya lewat `static::`, bukan `self::` — pilihan itulah yang
 * subclass ini ada untuk mengujinya.
 */
class KernelPlantReportTinyExportService extends KernelPlantReportService
{
    public const EXPORT_ROW_LIMIT = 5;
}

/** Satu record harian untuk satu unit kernel plant. */
function kernelPlantReportRecord(
    Station $station,
    string $date,
    string $kernelPlantId = 'KP-1',
    array $attributes = [],
): KernelPlantRecord {
    return KernelPlantRecord::factory()
        ->forStation($station)
        ->onDate($date)
        ->create(array_merge([
            'kernel_plant_id' => $kernelPlantId,
            'note' => 'Catatan harian',
        ], $attributes));
}

/** Satu baris slot waktu. Kesembilan kolom bacaan default-nya null. */
function kernelPlantReportSlot(
    KernelPlantRecord $record,
    string $timeSlot,
    array $values = [],
): KernelPlantDetail {
    return KernelPlantDetail::factory()
        ->forRecord($record)
        ->timeSlot($timeSlot)
        ->create($values);
}

/** 24 slot kanonis, dalam urutan layar input. */
function kernelPlantReportSlots(): array
{
    return KernelPlantRecordService::canonicalTimeSlots();
}

/**
 * ENAM baris master, persis seperti KernelPlantOperationalTargetSeeder.
 *
 * Masternya membawa TIGA kolom — equipment_parameter / target_benchmark /
 * corrective_action_plan — dan TIDAK SATU PUN namanya sama dengan master
 * Threshing, Pressing, maupun Depricarping. Menyalin nama kolom tetangga
 * menghasilkan blok target yang seluruhnya null tanpa satu pun galat.
 *
 * ENAM parameter untuk TUJUH kolom ukur, dan ketimpangannya berjalan ke dua
 * arah sekaligus:
 *   - 'Ripple Mill (Cracker)' mengatur DUA kolom (ripple_mill_1_amps dan
 *     ripple_mill_2_amps) dan 'Kernel Silo 1 & 2' juga DUA
 *     (kernel_silo_1_temp_c, kernel_silo_2_temp_c), sehingga tujuh entri peta
 *     hanya menyebut LIMA parameter berbeda;
 *   - 'Final Kernel Dirt' tidak punya kolom ukur di MANA PUN pada skema ini,
 *     jadi ia penghuni tetap targets_without_metric.
 */
function kernelPlantReportSeedTargets(): void
{
    foreach (kernelPlantReportTargetRows() as $index => [$parameter, $benchmark, $plan]) {
        KernelPlantOperationalTarget::create([
            'equipment_parameter' => $parameter,
            'target_benchmark' => $benchmark,
            'corrective_action_plan' => $plan,
            'sort_order' => $index + 1,
        ]);
    }
}

/** @return list<array{0: string, 1: string, 2: string}> */
function kernelPlantReportTargetRows(): array
{
    return [
        ['Ripple Mill (Cracker)', '20 - 25 Amps (Nut Breakage >95%)', 'Adjust rotor-vane clearance if uncracked nut rate >5%.'],
        ['Claybath / Hydrocyclone', 'Specific Gravity 1.18 - 1.24', 'Verify calcium carbonate mixture if kernels float with shell.'],
        ['Kernel Silo 1 & 2', '70°C - 80°C (Top/Middle zones)', 'Check heater elements/steam valves if temperature drops below 65°C.'],
        ['Final Kernel Moisture', '≤ 7.0% (Prevents mold growth)', 'Increase retention time or adjust silo air flow rates.'],
        ['Final Kernel Dirt', '≤ 6.0% (Standard quality premium)', 'Clean winnowing ducts or re-calibrate hydrocyclone settings.'],
        ['Shell Bin Kernel Loss', '≤ 1.5% (Maximized separation recovery)', 'Reduce air velocity or inspect separator screen meshes.'],
    ];
}

/** Setiap pernyataan SQL yang berjalan di dalam $callback. */
function kernelPlantReportQueriesDuring(callable $callback): array
{
    $queries = [];

    DB::listen(function ($query) use (&$queries) {
        $queries[] = strtolower($query->sql);
    });

    $callback();

    return $queries;
}

function kernelPlantReportStreamed(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

/** Entri metrics[] untuk satu kolom. */
function kernelPlantReportMetric(array $summary, string $column): array
{
    foreach ($summary['metrics'] as $metric) {
        if ($metric['column'] === $column) {
            return $metric;
        }
    }

    throw new RuntimeException("metric {$column} tidak ada pada payload");
}

/**
 * Seluruh nama KUNCI yang muncul di mana pun di dalam struktur — dipakai
 * case 56, yang harus menyisir KUNCI alih-alih mencari frasa.
 *
 * @return list<string>
 */
function kernelPlantReportAllKeys(mixed $value): array
{
    if (! is_array($value)) {
        return [];
    }

    $keys = [];

    foreach ($value as $key => $child) {
        if (is_string($key)) {
            $keys[] = $key;
        }

        $keys = array_merge($keys, kernelPlantReportAllKeys($child));
    }

    return array_values(array_unique($keys));
}

beforeEach(function () {
    $this->service = new KernelPlantReportService;

    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->kernelPlant()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->kernelPlant()->create();

    $this->lineA = (string) $this->stationA->production_line_id;
    $this->lineB = (string) $this->stationB->production_line_id;

    $this->supervisorA = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->supervisorB = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitB)->create();
    $this->millManagementA = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operatorA = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    // Periode yang SUDAH SELESAI, sehingga days_counted === days_in_period
    // dan penyebut cakupannya deterministik. Periode berjalan dan periode
    // belum mulai punya kasusnya masing-masing.
    //
    // RELATIF, bukan tanggal kalender: tanggal yang dipaku berhenti "sudah
    // selesai" begitu kalender melewatinya, dan asersi penyebut di bawah akan
    // mulai gagal karena alasan yang tidak ada hubungannya dengan kode.
    $this->periodStart = now()->subDays(20)->toDateString();
    $this->periodEnd = now()->subDays(11)->toDateString();

    // Empat tanggal di dalam periode, plus satu hari tepat di luarnya.
    $this->day1 = now()->subDays(19)->toDateString();
    $this->day2 = now()->subDays(18)->toDateString();
    $this->day3 = now()->subDays(17)->toDateString();
    $this->day4 = now()->subDays(16)->toDateString();
    $this->dayAfterPeriod = now()->subDays(10)->toDateString();

    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelPlant->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Kernel Plant Alpha')
        ->create();

    $this->slots = kernelPlantReportSlots();
});

// =====================================================================
// GROUP A — AKSES, MILL, LINE, PERIODE (case 1-15)
// =====================================================================

it('case 1 — guardAccess menolak tamu sebelum satu kueri pun, dan keempat peran enum memang keempat yang diizinkan', function () {
    $queries = kernelPlantReportQueriesDuring(function () {
        expect(fn () => $this->service->listPeriods(null))->toThrow(AuthenticationException::class);
        expect(fn () => $this->service->buildSummary((string) $this->periodA->id, null, $this->lineA))
            ->toThrow(AuthenticationException::class);
    });

    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "kernel_plant_records"');
        expect($sql)->not->toContain('from "periods"');
    }

    // CABANG 403 guardAccess() TIDAK DAPAT DICAPAI LEWAT PERILAKU, dan itu
    // fakta yang perlu dinyatakan alih-alih disimulasikan: App\Enums\UserRole
    // hanya punya EMPAT nilai, dan keempatnya diizinkan di sini. Menyemai
    // peran kelima ('weighbridge_operator', seperti disebut tech spec) berarti
    // menulis nilai yang akan ditolak cast enum pada User::$role saat dibaca —
    // yang diuji bukan lagi guard-nya melainkan cast-nya.
    //
    // Karena itu yang dikunci adalah PREMIS-nya: begitu peran kelima
    // ditambahkan ke enum, uji ini gagal dan cabang 403 harus diuji atas
    // perilaku.
    expect(UserRole::cases())->toHaveCount(4);
    expect(collect(UserRole::cases())->map(fn (UserRole $role) => $role->value)->sort()->values()->all())
        ->toBe(['admin', 'mill_management', 'operator', 'supervisor']);
});

it('case 2 — keempat peran lolos guardAccess, termasuk operator demi kembaran mobile screen-155', function () {
    kernelPlantReportSeedTargets();

    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');
    kernelPlantReportSlot($record, '07:00', ['ripple_mill_1_amps' => 22.0]);

    foreach ([$this->supervisorA, $this->millManagementA, $this->operatorA] as $user) {
        $this->actingAs($user);

        $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

        expect($summary['metrics'])->toHaveCount(7);
        expect($summary)->toHaveKeys([
            'period', 'business_unit', 'production_line', 'has_data', 'coverage',
            'metrics', 'targets_without_metric', 'targets_master_empty',
            'all_targets_measured', 'by_kernel_plant', 'daily', 'daily_total',
            'downtime', 'findings', 'total',
        ]);
    }

    // Admin harus menyertakan business_unit_id — satu-satunya peran yang
    // nilainya dihormati.
    $this->actingAs($this->admin);

    $adminSummary = $this->service->buildSummary(
        $this->periodA,
        (string) $this->businessUnitA->id,
        $this->lineA,
    );

    expect($adminSummary['metrics'])->toHaveCount(7);
});

it('case 3 — businessUnitOptions menolak 403 DARI DALAM service untuk supervisor, mill_management, dan operator', function () {
    $spy = new KernelPlantReportAllBusinessUnitsSpy;

    // Asersi paling tajam di berkas ini tentang apa yang TIDAK terbuka:
    // Operator mencapai ketiga rute lainnya dan tetap tidak boleh memegang
    // daftar mill. Middleware prefix sengaja meloloskan keempat peran demi
    // ketiga endpoint itu, jadi penolakan ini WAJIB berasal dari service —
    // uji ini gagal bila guard-nya dipindah ke rute.
    foreach ([$this->operatorA, $this->supervisorA, $this->millManagementA] as $user) {
        $this->actingAs($user);

        expect(fn () => $spy->businessUnitOptions())->toThrow(AuthorizationException::class);
    }

    expect($spy->allBusinessUnitsCalls)->toBe(0);
});

it('case 4 — businessUnitOptions mengembalikan daftar mill untuk Admin, tanpa cache', function () {
    $this->actingAs($this->admin);

    // Mill yang terbentuk dari factory stasiun ikut terdaftar, jadi yang
    // diasersi adalah KEHADIRAN mill uji dan PERTUMBUHAN daftarnya — bukan
    // panjang mutlaknya.
    $first = $this->service->businessUnitOptions();

    expect(collect($first)->pluck('name')->all())->toContain('Mill Alpha', 'Mill Beta');
    expect($first[0])->toHaveKeys(['id', 'name']);

    BusinessUnit::factory()->create(['name' => 'Mill Gamma']);

    $second = $this->service->businessUnitOptions();

    expect($second)->toHaveCount(count($first) + 1);
    expect(collect($second)->pluck('name')->all())->toContain('Mill Gamma');
});

it('case 5 — resolveBusinessUnit MENGABAIKAN business_unit_id argumen untuk supervisor', function () {
    $this->actingAs($this->supervisorA);

    // Tidak divalidasi, tidak dibandingkan, DIBUANG.
    expect($this->service->resolveBusinessUnit((string) $this->businessUnitB->id))
        ->toBe((string) $this->businessUnitA->id);

    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');
    kernelPlantReportSlot($record, '07:00', ['ripple_mill_1_amps' => 22.0]);

    $summary = $this->service->buildSummary($this->periodA, (string) $this->businessUnitB->id, $this->lineA);

    expect($summary['business_unit']['id'])->toBe((string) $this->businessUnitA->id);
    expect($summary['business_unit']['name'])->toBe('Mill Alpha');
});

it('case 6 — resolveBusinessUnit MENGABAIKAN business_unit_id argumen untuk mill_management juga', function () {
    $this->actingAs($this->millManagementA);

    expect($this->service->resolveBusinessUnit((string) $this->businessUnitB->id))
        ->toBe((string) $this->businessUnitA->id);

    $summary = $this->service->buildSummary($this->periodA, (string) $this->businessUnitB->id, $this->lineA);

    expect($summary['business_unit']['name'])->toBe('Mill Alpha');
});

it('case 7 — operator masuk cabang TERIKAT MILL, bukan cabang Admin', function () {
    $this->actingAs($this->operatorA);

    // UJI REGRESI LANGSUNG: bila operator hanya ditambahkan di guardAccess()
    // tanpa ditambahkan ke cabang terikat mill pada resolveBusinessUnit(), ia
    // jatuh ke cabang Admin — tempat business_unit_id kiriman klien
    // DIHORMATI — dan asersi ini mengembalikan mill B.
    expect($this->service->resolveBusinessUnit((string) $this->businessUnitB->id))
        ->toBe((string) $this->businessUnitA->id);

    $summary = $this->service->buildSummary($this->periodA, (string) $this->businessUnitB->id, $this->lineA);

    expect($summary['business_unit']['name'])->toBe('Mill Alpha');
});

it('case 8 — akun terikat mill tanpa business_unit_id gagal tertutup, tanpa kueri dan tanpa membaca daftar mill', function () {
    $boundWithoutMill = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $this->actingAs($boundWithoutMill);

    $spy = new KernelPlantReportAllBusinessUnitsSpy;

    // PENOLAKANNYA 422 VALIDATION_ERROR, bukan 403 FORBIDDEN seperti ditulis
    // tech spec: resolveBusinessUnit() melempar ValidationException dengan
    // pesan 'Akun Anda belum terhubung ke mill. Hubungi Admin.' pada cabang
    // ini, sama seperti kelima laporan kondisi sebelumnya. Yang load-bearing
    // bukan angka statusnya melainkan GAGAL TERTUTUP tanpa menyentuh daftar
    // seluruh mill — itulah yang diasersi di bawah.
    $queries = kernelPlantReportQueriesDuring(function () use ($spy) {
        expect(fn () => $spy->resolveBusinessUnit(null))->toThrow(ValidationException::class);
    });

    // Jatuh ke "seluruh mill" akan mengubah satu baris master-data yang rusak
    // menjadi kebocoran lintas mill. Spy ini membuktikan jalur itu tidak
    // ditempuh.
    expect($spy->allBusinessUnitsCalls)->toBe(0);

    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "business_units"');
        expect($sql)->not->toContain('from "kernel_plant_records"');
    }

    try {
        $spy->resolveBusinessUnit(null);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('business_unit_id');
        expect($e->errors()['business_unit_id'][0])->toContain('belum terhubung ke mill');
    }
});

it('case 9 — resolveBusinessUnit melempar 422 VALIDATION_ERROR untuk Admin tanpa business_unit_id', function () {
    $this->actingAs($this->admin);

    // BUKAN null diam-diam dan BUKAN hasil kosong, yang akan terbaca sebagai
    // "mill ini tidak punya data".
    expect(fn () => $this->service->resolveBusinessUnit(null))->toThrow(ValidationException::class);
    expect(fn () => $this->service->resolveBusinessUnit(''))->toThrow(ValidationException::class);
    expect(fn () => $this->service->buildSummary($this->periodA, null, $this->lineA))
        ->toThrow(ValidationException::class);
});

it('case 10 — resolveBusinessUnit memakai business_unit_id dari pemanggil untuk Admin', function () {
    $this->actingAs($this->admin);

    // Satu-satunya peran yang nilainya dihormati.
    expect($this->service->resolveBusinessUnit((string) $this->businessUnitB->id))
        ->toBe((string) $this->businessUnitB->id);
    expect($this->service->resolveBusinessUnit((string) $this->businessUnitA->id))
        ->toBe((string) $this->businessUnitA->id);
});

it('case 11 — requirePeriod melempar 422 ketika period_id tidak dikirim, bukan 404', function () {
    $this->actingAs($this->supervisorA);

    expect(fn () => $this->service->buildSummary(null, null, $this->lineA))
        ->toThrow(ValidationException::class);
    expect(fn () => $this->service->buildSummary('', null, $this->lineA))
        ->toThrow(ValidationException::class);

    try {
        $this->service->buildSummary(null, null, $this->lineA);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('period_id');
    }
});

it('case 12 — requirePeriod melempar 404 ketika period_id tidak ada', function () {
    $this->actingAs($this->supervisorA);

    expect(fn () => $this->service->buildSummary((string) Str::uuid(), null, $this->lineA))
        ->toThrow(ModelNotFoundException::class);
    expect(fn () => $this->service->authorizePeriod((string) Str::uuid()))
        ->toThrow(ModelNotFoundException::class);
});

it('case 13 — authorizePeriodModel melempar 403 untuk periode mill lain yang diminta peran terikat mill', function () {
    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::KernelPlant->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->create();

    $this->actingAs($this->supervisorA);

    // Pengecualian yang DISENGAJA dari aturan "diabaikan": period_id selalu
    // eksplisit dari pemanggil, jadi ia DITOLAK, bukan diabaikan.
    expect(fn () => $this->service->authorizePeriod((string) $periodB->id))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->buildSummary((string) $periodB->id, null, $this->lineA))
        ->toThrow(AuthorizationException::class);

    // Admin lolos untuk mill mana pun — satu-satunya peran tak terikat.
    $this->actingAs($this->admin);

    expect($this->service->authorizePeriod((string) $periodB->id)->id)->toBe($periodB->id);
});

it('case 14 — urutan guard dipertahankan: sesi diperiksa sebelum id periode dicari', function () {
    // Pemanggil yang tidak berhak sama sekali tidak boleh bisa menyimpulkan id
    // periode mana yang ada dengan membandingkan 403/401 terhadap 404.
    //
    // Peran DI LUAR keempat peran tidak dapat dibentuk (lihat case 1 — enum
    // hanya punya empat nilai dan keempatnya diizinkan), jadi yang diuji di
    // sini adalah cabang guard yang MEMANG dapat dicapai: tamu. Sifat yang
    // sama dan sifat yang sama yang dilanggar bila lookup-nya dipindah ke
    // depan guard.
    $missingPeriodId = (string) Str::uuid();

    expect(fn () => $this->service->buildSummary($missingPeriodId, null, $this->lineA))
        ->toThrow(AuthenticationException::class);

    $queries = kernelPlantReportQueriesDuring(function () use ($missingPeriodId) {
        expect(fn () => $this->service->authorizePeriod($missingPeriodId))
            ->toThrow(AuthenticationException::class);
    });

    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "periods"');
    }
});

it('case 15 — Admin tanpa business_unit_id mendapat 422 sebelum 404 untuk period_id yang tidak ada', function () {
    $this->actingAs($this->admin);

    // Mill diperiksa SEBELUM id periode: 422, bukan 404.
    expect(fn () => $this->service->buildSummary((string) Str::uuid(), null, $this->lineA))
        ->toThrow(ValidationException::class);
});

// =====================================================================
// GROUP B — CAKUPAN RECORD DAN PRODUCTION LINE (case 16-19)
// =====================================================================

it('case 16 — resolveProductionLine memulangkan null untuk line mill lain dan untuk ketiadaan pilihan', function () {
    $this->actingAs($this->supervisorA);

    $businessUnitId = (string) $this->businessUnitA->id;

    expect($this->service->resolveProductionLine($businessUnitId, null))->toBeNull();
    expect($this->service->resolveProductionLine($businessUnitId, ''))->toBeNull();
    // Line mill lain: null, bukan line itu — dan bukan exception, karena di
    // layar "belum memilih" adalah keadaan normal. 403/404 justru akan
    // memastikan line milik mill lain itu ada.
    expect($this->service->resolveProductionLine($businessUnitId, $this->lineB))->toBeNull();
    expect($this->service->resolveProductionLine($businessUnitId, $this->lineA))->toBe($this->lineA);

    // DAN DATA MILL LAIN TETAP TIDAK PERNAH TERBACA. Perhatikan: tech spec
    // menulis "angka mencakup seluruh mill A" untuk keadaan ini, tetapi
    // buildSummary() TIDAK memanggil resolveProductionLine() — ia menerapkan
    // production_line_id yang diberikan apa adanya. Line milik mill lain
    // karena itu menyaring sampai NOL baris, sementara kuerinya tetap
    // dibatasi mill PERIODE, jadi angka mill lain tetap mustahil terbaca.
    $recordA = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-A');
    kernelPlantReportSlot($recordA, '07:00', ['ripple_mill_1_amps' => 11.0]);

    $recordB = kernelPlantReportRecord($this->stationB, $this->day2, 'KP-B');
    kernelPlantReportSlot($recordB, '07:00', ['ripple_mill_1_amps' => 99.0]);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineB);

    expect($summary['business_unit']['name'])->toBe('Mill Alpha');
    expect($summary['coverage']['filled_slots'])->toBe(0);
    expect($summary['has_data'])->toBeFalse();
    expect($summary['by_kernel_plant'])->toBe([]);
    // Angka 99.0 milik mill B tidak boleh terbaca lewat jalan mana pun, dan
    // angka 11.0 milik mill A pun tidak, karena penyaring line menolak
    // keduanya.
    expect(kernelPlantReportMetric($summary, 'ripple_mill_1_amps')['max'])->toBeNull();

    // CATATAN SENGAJA TANPA ASERSI — DILAPORKAN SEBAGAI DUGAAN CACAT, BUKAN
    // DIKUNCI DI SINI. Blok `production_line` pada payload saat ini BUKAN
    // null untuk keadaan ini: productionLineInfo() mencari line hanya
    // berdasarkan id, tanpa membandingkan mill-nya, sehingga ia menerbitkan
    // {id, name} milik line MILL LAIN — justru nama yang
    // resolveProductionLine() ada untuk tidak dibocorkan ("403 justru akan
    // memastikan line itu ada").
    //
    // Angkanya aman (lihat asersi di atas); yang bocor adalah KEBERADAAN dan
    // NAMA line mill lain. Perbaikannya satu baris di
    // productionLineInfo() — menyaring juga pada business_unit_id mill yang
    // berlaku. Asersi atas nilai blok itu dibiarkan KOSONG di sini supaya
    // perbaikan tidak perlu menyentuh berkas uji ini, dan supaya uji ini
    // tidak menjadi bukti bahwa kebocorannya disengaja.
});

it('case 17 — production_line_id null menghasilkan cakupan seluruh line pada mill itu', function () {
    $otherLine = ProductionLine::factory()->create(['business_unit_id' => $this->businessUnitA->id]);
    $stationOnOtherLine = Station::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->kernelPlant()
        ->create(['production_line_id' => $otherLine->id]);

    $onLineA = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-A1');
    kernelPlantReportSlot($onLineA, '07:00', ['ripple_mill_1_amps' => 20.0]);

    $onLineOther = kernelPlantReportRecord($stationOnOtherLine, $this->day2, 'KP-A2');
    kernelPlantReportSlot($onLineOther, '07:00', ['ripple_mill_1_amps' => 30.0]);

    $this->actingAs($this->supervisorA);

    // Sah di API meski layar web tidak pernah memanggil dalam keadaan ini —
    // controller-nya menolak 422 lebih dulu (case 62 pada uji API).
    $summary = $this->service->buildSummary($this->periodA, null, null);

    expect($summary['production_line'])->toBeNull();
    expect($summary['coverage']['filled_slots'])->toBe(2);
    expect(kernelPlantReportMetric($summary, 'ripple_mill_1_amps')['avg'])->toBe(25.0);
    expect($summary['coverage']['kernel_plant_count'])->toBe(2);
});

it('case 18 — penyaringan line memakai kolom production_line_id pada kernel_plant_records, bukan join ke stations', function () {
    // SATU stasiun. Satu record dihasilkan saat stasiun itu masih di line A,
    // lalu stasiunnya DIPINDAH ke line lain. Kolom record tetap menunjuk line
    // tempat data itu benar-benar dihasilkan — justru itu yang benar.
    $otherLine = ProductionLine::factory()->create(['business_unit_id' => $this->businessUnitA->id]);

    $producedOnLineA = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1', [
        'production_line_id' => $this->lineA,
    ]);
    kernelPlantReportSlot($producedOnLineA, '07:00', ['ripple_mill_1_amps' => 20.0]);

    $producedOnOtherLine = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-2', [
        'production_line_id' => $otherLine->id,
    ]);
    kernelPlantReportSlot($producedOnOtherLine, '07:00', ['ripple_mill_1_amps' => 99.0]);

    // Stasiunnya KINI terdaftar di line lain. Join ke stations akan membaca
    // keadaan SEKARANG dan menulis ulang sejarah.
    $this->stationA->forceFill(['production_line_id' => $otherLine->id])->save();

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['coverage']['filled_slots'])->toBe(1);
    expect(kernelPlantReportMetric($summary, 'ripple_mill_1_amps')['avg'])->toBe(20.0);
    // Join ke stations akan menarik KEDUA record dan menjawab 59,5 —
    // rata-rata dua line sekaligus.
    expect(kernelPlantReportMetric($summary, 'ripple_mill_1_amps')['max'])->toBe(20.0);
});

it('case 19 — recordsFor tidak menimbulkan N+1: satu kueri record plus satu eager-load detail', function () {
    foreach (range(1, 5) as $index) {
        $record = kernelPlantReportRecord($this->stationA, $this->day2, "KP-{$index}");

        foreach ($this->slots as $slot) {
            kernelPlantReportSlot($record, $slot, ['ripple_mill_1_amps' => 20.0 + $index]);
        }
    }

    $this->actingAs($this->supervisorA);

    $queries = kernelPlantReportQueriesDuring(function () {
        $this->service->buildSummary($this->periodA, null, $this->lineA);
    });

    $recordQueries = array_values(array_filter(
        $queries,
        fn (string $sql) => str_contains($sql, 'from "kernel_plant_records"'),
    ));
    $detailQueries = array_values(array_filter(
        $queries,
        fn (string $sql) => str_contains($sql, 'from "kernel_plant_details"'),
    ));

    // TEPAT 2, bukan 6: detailnya ter-eager-load sekali untuk kelima record.
    expect($recordQueries)->toHaveCount(1);
    expect($detailQueries)->toHaveCount(1);
    // Dan terurut time_slot kanonis, dibandingkan sebagai nilai TIME apa
    // adanya — tidak pernah di-cast menjadi jam integer.
    expect($detailQueries[0])->toContain('order by "time_slot" asc');
});

// =====================================================================
// GROUP C — DEFINISI "TERISI" DIPINJAM DARI LAYAR INPUT (case 20-22)
// =====================================================================

it('case 20 — definisi terisi DIPINJAM dari KernelPlantRecordService::isRowFilled, bukan diturunkan ulang', function () {
    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');
    kernelPlantReportSlot($record, '07:00', ['kernel_moisture_percent' => 6.4]);

    $this->actingAs($this->supervisorA);

    expect($this->service->buildSummary($this->periodA, null, $this->lineA)['coverage']['filled_slots'])
        ->toBe(1);

    // PRASYARAT REFACTOR: keduanya publik, justru supaya dapat dipinjam.
    expect(KernelPlantRecordService::READING_FIELDS)->toHaveCount(9);
    expect(KernelPlantRecordService::READING_FIELDS)->toBe([
        'ripple_mill_1_amps',
        'ripple_mill_2_amps',
        'claybath_hydro_sg',
        'kernel_silo_1_temp_c',
        'kernel_silo_2_temp_c',
        'kernel_moisture_percent',
        'shell_loss_percent',
        'downtime_minutes',
        'findings',
    ]);
    expect((new ReflectionMethod(KernelPlantRecordService::class, 'isRowFilled'))->isPublic())->toBeTrue();
    expect((new ReflectionClassConstant(KernelPlantRecordService::class, 'READING_FIELDS'))->isPublic())->toBeTrue();

    // UJI STRUKTURAL PENDAMPING: service laporan MEMANGGIL definisi itu dan
    // TIDAK memuat daftar sembilan kolomnya sendiri. Dua definisi yang
    // berselisih berarti cakupan di laporan tidak sama dengan apa yang
    // diterima layar input, dan tidak ada yang bisa tahu mana yang salah.
    $source = file_get_contents(
        (new ReflectionClass(KernelPlantReportService::class))->getFileName(),
    );

    expect($source)->toContain('KernelPlantRecordService::READING_FIELDS');
    expect($source)->toContain('isRowFilled(');

    foreach ((new ReflectionClass(KernelPlantReportService::class))->getConstants() as $name => $value) {
        expect($value)->not->toBe(KernelPlantRecordService::READING_FIELDS, "konstanta {$name} menduplikasi READING_FIELDS");
    }

    // NUMERIC_METRICS adalah TUJUH kolom ukur, bukan kesembilan kolom bacaan —
    // downtime_minutes dijumlahkan dan findings dikelompokkan, keduanya di
    // bloknya sendiri.
    expect(KernelPlantReportService::NUMERIC_METRICS)->toHaveCount(7);
    expect(KernelPlantReportService::NUMERIC_METRICS)
        ->not->toContain('downtime_minutes')
        ->not->toContain('findings');
});

it('case 21 — slot yang kesembilan kolom bacaannya kosong disaring keluar dan tidak dihitung', function () {
    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');

    foreach ($this->slots as $slot) {
        kernelPlantReportSlot($record, $slot);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // Slot tak tersentuh sudah dilaporkan hilang oleh penyebut cakupan;
    // menghitungnya lagi melaporkan kekosongan yang sama dua kali.
    expect($summary['coverage']['filled_slots'])->toBe(0);
    expect($summary['has_data'])->toBeFalse();
    // Record-nya tetap ada, dan tetap ikut penyebut cakupan.
    expect($summary['total']['record_count'])->toBe(1);
    expect($summary['coverage']['kernel_plant_count'])->toBe(1);
});

it('case 22 — asimetri dipertahankan: downtime_minutes 0 adalah PERNYATAAN, findings kosong adalah kosong', function () {
    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');

    kernelPlantReportSlot($record, '07:00', ['downtime_minutes' => 0]);
    kernelPlantReportSlot($record, '08:00', ['findings' => '']);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // Slot X terhitung, slot Y tidak.
    expect($summary['coverage']['filled_slots'])->toBe(1);
    expect($summary['downtime']['recorded_slot_count'])->toBe(1);
    expect($summary['findings'])->toBe([]);

    // Dan langsung pada definisinya, kolom per kolom.
    $service = new KernelPlantRecordService;
    $empty = array_fill_keys(KernelPlantRecordService::READING_FIELDS, null);

    expect($service->isRowFilled($empty))->toBeFalse();
    expect($service->isRowFilled(array_merge($empty, ['findings' => ''])))->toBeFalse();
    expect($service->isRowFilled(array_merge($empty, ['findings' => 'Ada'])))->toBeTrue();
    expect($service->isRowFilled(array_merge($empty, ['downtime_minutes' => 0])))->toBeTrue();

    foreach (KernelPlantReportService::NUMERIC_METRICS as $column) {
        expect($service->isRowFilled(array_merge($empty, [$column => 1.0])))
            ->toBeTrue("kolom {$column} harus membuat baris terhitung terisi");
    }
});

// =====================================================================
// GROUP D — CAKUPAN PENCATATAN (case 23-28 dan case 65)
// =====================================================================

it('case 23 — expected_slots adalah kernel_plant_count x days_counted x 24 slot kanonis', function () {
    $fiveDayPeriod = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelPlant->value)
        ->range(now()->subDays(9)->toDateString(), now()->subDays(5)->toDateString())
        ->open()
        ->named('Periode Lima Hari')
        ->create();

    foreach (['KP-1', 'KP-2'] as $kernelPlantId) {
        $record = kernelPlantReportRecord($this->stationA, now()->subDays(8)->toDateString(), $kernelPlantId);
        kernelPlantReportSlot($record, '07:00', ['ripple_mill_1_amps' => 22.0]);
    }

    $this->actingAs($this->supervisorA);

    $coverage = $this->service->buildSummary($fiveDayPeriod, null, $this->lineA)['coverage'];

    // KETIGA angka pembentuk penyebut terbit TERPISAH, supaya pembaca dapat
    // memeriksa hitungannya sendiri alih-alih mempercayai satu persen.
    expect($coverage['kernel_plant_count'])->toBe(2);
    expect($coverage['days_counted'])->toBe(5);
    expect($coverage['days_in_period'])->toBe(5);
    expect($coverage['slots_per_kernel_plant_per_day'])->toBe(24);
    expect($coverage['expected_slots'])->toBe(240);
    expect($coverage['filled_slots'])->toBe(2);

    // Satu definisi, satu jawaban: bukan konstanta terpisah yang bisa
    // menyimpang dari grid layar input.
    expect($coverage['slots_per_kernel_plant_per_day'])
        ->toBe(count(KernelPlantRecordService::canonicalTimeSlots()));
});

it('case 24 — kernel_plant_count dihitung dari unit yang BENAR-BENAR punya record, bukan unit terdaftar', function () {
    // Empat stasiun kernel plant terdaftar pada line ini; hanya dua unit
    // kernel plant yang benar-benar beroperasi di periode itu.
    Station::factory()->count(3)->forBusinessUnit($this->businessUnitA)->kernelPlant()->create([
        'production_line_id' => $this->lineA,
    ]);

    foreach (['KP-1', 'KP-2'] as $kernelPlantId) {
        $record = kernelPlantReportRecord($this->stationA, $this->day2, $kernelPlantId);
        kernelPlantReportSlot($record, '07:00', ['ripple_mill_1_amps' => 22.0]);
    }

    $this->actingAs($this->supervisorA);

    // Penyebut yang dibangun dari stasiun terdaftar akan MENGHUKUM mill yang
    // memang sengaja tidak mengoperasikan salah satu unitnya.
    expect($this->service->buildSummary($this->periodA, null, $this->lineA)['coverage']['kernel_plant_count'])
        ->toBe(2);
});

it('case 25 — coverage_percent NULL, bukan 0.0, ketika expected_slots nol karena tak ada record sama sekali', function () {
    $this->actingAs($this->supervisorA);

    $coverage = $this->service->buildSummary($this->periodA, null, $this->lineA)['coverage'];

    expect($coverage['kernel_plant_count'])->toBe(0);
    expect($coverage['expected_slots'])->toBe(0);
    // assertNull, BUKAN assertSame(0.0): penyebutnya tidak terbentuk, jadi
    // tidak ada persentase yang dapat dinyatakan. Lihat case 65 untuk
    // PASANGAN KONTROL-nya, yang justru harus 0.0.
    expect($coverage['coverage_percent'])->toBeNull();
    expect($coverage['filled_slots'])->toBe(0);
});

it('case 65 — PASANGAN KONTROL case 25: coverage_percent 0.0, BUKAN null, ketika periode punya record tetapi tak satu slotnya terisi', function () {
    $oneDay = now()->subDays(6)->toDateString();

    $singleDayPeriod = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelPlant->value)
        ->range($oneDay, $oneDay)
        ->open()
        ->named('Periode Satu Hari')
        ->create();

    $record = kernelPlantReportRecord($this->stationA, $oneDay, 'KP-1');

    foreach ($this->slots as $slot) {
        kernelPlantReportSlot($record, $slot);
    }

    $this->actingAs($this->supervisorA);

    $coverage = $this->service->buildSummary($singleDayPeriod, null, $this->lineA)['coverage'];

    expect($coverage['kernel_plant_count'])->toBe(1);
    expect($coverage['days_counted'])->toBe(1);
    expect($coverage['expected_slots'])->toBe(24);
    expect($coverage['filled_slots'])->toBe(0);
    // assertSame(0.0), BUKAN assertNull. SYARAT null adalah tentang PENYEBUT
    // (expected_slots === 0: tidak ada unit yang punya record, atau tidak ada
    // hari terhitung), bukan tentang pembilang. Di sini penyebutnya terbentuk,
    // dan "nol dari 24 slot terisi" adalah pernyataan yang SAH tentang
    // kelengkapan.
    //
    // Tanpa pasangan ini, case 25 sendiri membuat orang menyimpulkan bahwa
    // "tidak ada slot terisi" selalu berarti null — kesimpulan yang salah.
    expect($coverage['coverage_percent'])->toBe(0.0);
    expect($this->service->buildSummary($singleDayPeriod, null, $this->lineA)['has_data'])->toBeFalse();
});

it('case 26 — days_counted === 0 untuk periode BELUM MULAI, meski period_running juga true', function () {
    $notStarted = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelPlant->value)
        ->range(now()->addDays(10)->toDateString(), now()->addDays(25)->toDateString())
        ->open()
        ->named('Periode Belum Mulai')
        ->create();

    $this->actingAs($this->supervisorA);

    $coverage = $this->service->buildSummary($notStarted, null, $this->lineA)['coverage'];

    // KEDUANYA diasersi bersama, dan ITULAH inti kasus ini: period_running
    // bernilai true JUGA untuk periode yang belum mulai, jadi layar yang
    // membaca period_running lebih dulu akan menjelaskan periode ini sebagai
    // "sedang berjalan dan belum ada yang tercatat" padahal hari pertamanya
    // belum datang. days_counted === 0 harus dibaca LEBIH DULU.
    expect($coverage['days_counted'])->toBe(0);
    expect($coverage['period_running'])->toBeTrue();
    expect($coverage['days_in_period'])->toBe(16);
    expect($coverage['expected_slots'])->toBe(0);
    expect($coverage['coverage_percent'])->toBeNull();
});

it('case 27 — days_counted berhenti di hari ini untuk periode yang sedang berjalan', function () {
    $running = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelPlant->value)
        ->range(now()->subDays(3)->toDateString(), now()->addDays(7)->toDateString())
        ->open()
        ->named('Periode Berjalan')
        ->create();

    $record = kernelPlantReportRecord($this->stationA, now()->subDays(1)->toDateString(), 'KP-1');
    kernelPlantReportSlot($record, '07:00', ['ripple_mill_1_amps' => 22.0]);

    $this->actingAs($this->supervisorA);

    $coverage = $this->service->buildSummary($running, null, $this->lineA)['coverage'];

    expect($coverage['period_running'])->toBeTrue();
    expect($coverage['days_in_period'])->toBe(11);
    // Hari yang belum terjadi tidak mungkin tercatat, jadi tidak boleh
    // menjadi pembagi.
    expect($coverage['days_counted'])->toBe(4);
    expect($coverage['days_counted'])->toBeLessThan($coverage['days_in_period']);
    expect($coverage['expected_slots'])->toBe(96);
});

it('case 28 — has_data membedakan "tidak ada yang bisa dilaporkan" dari "angkanya kebetulan nol"', function () {
    $this->actingAs($this->supervisorA);

    // Kasus A: nol slot terisi.
    expect($this->service->buildSummary($this->periodA, null, $this->lineA)['has_data'])->toBeFalse();

    // Kasus B: slot terisi dengan downtime_minutes = 0 dan nilai ukur 0.0.
    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');
    kernelPlantReportSlot($record, '07:00', [
        'shell_loss_percent' => 0.0,
        'downtime_minutes' => 0,
    ]);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['has_data'])->toBeTrue();
    expect(kernelPlantReportMetric($summary, 'shell_loss_percent')['avg'])->toBe(0.0);
    expect($summary['downtime']['total_minutes'])->toBe(0);
});

// =====================================================================
// GROUP E — PENYEBUT PER METRIK (case 29-33)
// =====================================================================

it('case 29 — PENYEBUT PER METRIK: ketujuh filled_slot_count genuin berbeda-beda', function () {
    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');

    // FIXTURE SENGAJA MEMBERI TUJUH PENYEBUT BERBEDA. Dengan penyebut yang
    // seragam, satu penyebut bersama lolos tanpa terlihat.
    $pola = [
        'ripple_mill_1_amps' => 6,
        'ripple_mill_2_amps' => 2,
        'claybath_hydro_sg' => 5,
        'kernel_silo_1_temp_c' => 4,
        'kernel_silo_2_temp_c' => 3,
        'kernel_moisture_percent' => 7,
        'shell_loss_percent' => 1,
    ];

    foreach (array_slice($this->slots, 0, 7) as $index => $slot) {
        $values = [];

        foreach ($pola as $column => $count) {
            if ($index < $count) {
                $values[$column] = 10.0 + $index;
            }
        }

        kernelPlantReportSlot($record, $slot, $values);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['metrics'])->toHaveCount(7);

    // DIASERSI PER KOLOM. Satu asersi atas "satu penyebut bersama" akan lolos
    // terhadap implementasi yang salah.
    foreach ($pola as $column => $count) {
        expect(kernelPlantReportMetric($summary, $column)['filled_slot_count'])
            ->toBe($count, "penyebut {$column} harus {$count}");
    }

    // Dan ketujuhnya memang berbeda-beda — bukan kebetulan sama.
    expect(collect($summary['metrics'])->pluck('filled_slot_count')->all())
        ->toBe([6, 2, 5, 4, 3, 7, 1]);

    // Ketujuh kolom terbit dalam urutan NUMERIC_METRICS.
    expect(collect($summary['metrics'])->pluck('column')->all())
        ->toBe(KernelPlantReportService::NUMERIC_METRICS);
});

it('case 30 — coverage.filled_slots DAPAT MELEBIHI penyebut setiap metrik, dan itu benar', function () {
    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');

    // 10 slot berkolom ukur dengan sebaran tidak rata — kernel_moisture_percent
    // mengisi kesepuluhnya, jadi penyebut terbesar adalah 10.
    $pola = [
        'kernel_moisture_percent' => 10,
        'ripple_mill_1_amps' => 6,
        'claybath_hydro_sg' => 5,
        'kernel_silo_1_temp_c' => 4,
        'kernel_silo_2_temp_c' => 3,
        'ripple_mill_2_amps' => 2,
        'shell_loss_percent' => 1,
    ];

    foreach (array_slice($this->slots, 0, 10) as $index => $slot) {
        $values = [];

        foreach ($pola as $column => $count) {
            if ($index < $count) {
                $values[$column] = 10.0 + $index;
            }
        }

        kernelPlantReportSlot($record, $slot, $values);
    }

    // DITAMBAH 4 slot yang HANYA mengisi findings, tanpa satu pun kolom ukur.
    foreach (array_slice($this->slots, 10, 4) as $slot) {
        kernelPlantReportSlot($record, $slot, ['findings' => 'Periksa ripple mill']);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['coverage']['filled_slots'])->toBe(14);

    // KETIDAKSAMAAN, bukan kesamaan: kesamaan adalah justru bug yang hendak
    // dicegah. Slot dihitung terisi bila salah satu dari SEMBILAN kolom
    // bacaan terisi, dan dua di antaranya (downtime_minutes, findings) bukan
    // kolom ukur — jadi selisih ini BENAR, dan dikunci di sini supaya tidak
    // "diperbaiki".
    foreach ($summary['metrics'] as $metric) {
        expect($summary['coverage']['filled_slots'])
            ->toBeGreaterThan($metric['filled_slot_count'], "filled_slots harus melebihi penyebut {$metric['column']}");
    }

    expect(collect($summary['metrics'])->max('filled_slot_count'))->toBe(10);
    expect($summary['findings'])->toBe([['finding' => 'Periksa ripple mill', 'slot_count' => 4]]);
});

it('case 31 — metrik tanpa satu pun nilai non-null TETAP TAMPIL dengan min/avg/max null dan penyebut 0', function () {
    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');
    kernelPlantReportSlot($record, '07:00', ['ripple_mill_1_amps' => 22.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // Tujuh baris yang menyusut menjadi enam akan terbaca sebagai "tidak ada
    // parameter ini", padahal yang benar adalah "tidak ada yang mengukurnya".
    expect($summary['metrics'])->toHaveCount(7);

    $shell = kernelPlantReportMetric($summary, 'shell_loss_percent');

    expect($shell['min'])->toBeNull();
    expect($shell['avg'])->toBeNull();
    expect($shell['max'])->toBeNull();
    expect($shell['filled_slot_count'])->toBe(0);
    // Labelnya tetap ada supaya barisnya dapat digambar.
    expect($shell['label'])->toBe('Shell Bin Kernel Loss');
    expect($shell['unit'])->toBe('%');
});

it('case 32 — nilai null DILEWATI, tidak diperlakukan sebagai nol, pada min/avg/max', function () {
    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');

    // 3 slot mengisi ripple_mill_1_amps; 21 slot lain meninggalkan kolom itu
    // null tetapi TETAP terisi lewat kolom lain, supaya ke-21 slot itu benar
    // ikut filled_rows dan perbedaannya betul-betul teruji.
    foreach ($this->slots as $index => $slot) {
        $values = ['kernel_moisture_percent' => 6.0];

        if ($index < 3) {
            $values['ripple_mill_1_amps'] = 20.0 + ($index * 2);
        }

        kernelPlantReportSlot($record, $slot, $values);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);
    $metric = kernelPlantReportMetric($summary, 'ripple_mill_1_amps');

    // Nol akan menarik minimum ke 0.0 dan merata-ratakan ke 2.75.
    expect($metric['min'])->toBe(20.0);
    expect($metric['avg'])->toBe(22.0);
    expect($metric['max'])->toBe(24.0);
    expect($metric['filled_slot_count'])->toBe(3);

    // Dan penyebut kolom pendampingnya memang 24 — dua penyebut berbeda pada
    // satu himpunan slot yang sama.
    expect(kernelPlantReportMetric($summary, 'kernel_moisture_percent')['filled_slot_count'])->toBe(24);
    expect($summary['coverage']['filled_slots'])->toBe(24);
});

it('case 33 — seluruh agregasi di PHP: nol agregat SQL atas kolom nullable, dan whereDate dipakai apa adanya', function () {
    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');
    kernelPlantReportSlot($record, '07:00', ['ripple_mill_1_amps' => 22.0]);
    kernelPlantReportSlot($record, '08:00', ['kernel_silo_1_temp_c' => 75.0]);

    $this->actingAs($this->supervisorA);

    $queries = kernelPlantReportQueriesDuring(function () {
        $this->service->buildSummary($this->periodA, null, $this->lineA);
    });

    // SQLite (suite) dan PostgreSQL (produksi) berbeda perilaku pada AVG/SUM
    // atas kolom bernull, dan perbedaan itu akan lolos dari suite ini.
    foreach ($queries as $sql) {
        foreach (['avg(', 'min(', 'max(', 'sum(', 'group by'] as $forbidden) {
            expect($sql)->not->toContain($forbidden);
        }
    }

    $recordQueries = array_values(array_filter(
        $queries,
        fn (string $sql) => str_contains($sql, 'from "kernel_plant_records"'),
    ));

    expect($recordQueries)->not->toBeEmpty();

    // whereDate(), bukan where() mentah: pada SQLite ia mengompilasi menjadi
    // strftime('%Y-%m-%d', ...), dan justru di ujung-ujung periode itulah
    // bentuk mentah berbeda — dan hanya di PostgreSQL.
    if (DB::connection()->getDriverName() === 'sqlite') {
        expect($recordQueries[0])->toContain('strftime');
    }
});

it('case 33b — keanggotaan periode inklusif di kedua ujung dan menolak satu hari di luarnya', function () {
    $first = kernelPlantReportRecord($this->stationA, $this->periodStart, 'KP-1');
    $last = kernelPlantReportRecord($this->stationA, $this->periodEnd, 'KP-1');
    $outside = kernelPlantReportRecord($this->stationA, $this->dayAfterPeriod, 'KP-1');

    kernelPlantReportSlot($first, '07:00', ['ripple_mill_1_amps' => 10.0]);
    kernelPlantReportSlot($last, '07:00', ['ripple_mill_1_amps' => 20.0]);
    kernelPlantReportSlot($outside, '07:00', ['ripple_mill_1_amps' => 999.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['total']['record_count'])->toBe(2);
    expect(kernelPlantReportMetric($summary, 'ripple_mill_1_amps')['max'])->toBe(20.0);
});

// =====================================================================
// GROUP F — STANDAR OPERASIONAL (case 34-43)
// =====================================================================

it('case 34 — DUA pasangan berbagi standar terbit lewat shares_standard_with, bukan satu', function () {
    kernelPlantReportSeedTargets();

    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');
    kernelPlantReportSlot($record, '07:00', [
        'ripple_mill_1_amps' => 22.0,
        'ripple_mill_2_amps' => 23.0,
        'kernel_silo_1_temp_c' => 75.0,
        'kernel_silo_2_temp_c' => 76.0,
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // EMPAT BARIS, bukan dua. Depricarping hanya punya SATU pasangan, jadi
    // kode yang mengistimewakan satu pasangan lolos di sana dan gagal di sini.
    expect(kernelPlantReportMetric($summary, 'ripple_mill_1_amps')['target']['shares_standard_with'])
        ->toBe(['ripple_mill_2_amps']);
    expect(kernelPlantReportMetric($summary, 'ripple_mill_2_amps')['target']['shares_standard_with'])
        ->toBe(['ripple_mill_1_amps']);
    expect(kernelPlantReportMetric($summary, 'kernel_silo_1_temp_c')['target']['shares_standard_with'])
        ->toBe(['kernel_silo_2_temp_c']);
    expect(kernelPlantReportMetric($summary, 'kernel_silo_2_temp_c')['target']['shares_standard_with'])
        ->toBe(['kernel_silo_1_temp_c']);

    // TIGA kolom sisanya ber-shares_standard_with = [].
    foreach (['claybath_hydro_sg', 'kernel_moisture_percent', 'shell_loss_percent'] as $column) {
        expect(kernelPlantReportMetric($summary, $column)['target']['shares_standard_with'])
            ->toBe([], "kolom {$column} tidak berbagi standar dengan siapa pun");
    }

    // TEPAT EMPAT baris yang membawa penanda itu.
    expect(collect($summary['metrics'])->filter(fn (array $metric) => $metric['target']['shares_standard_with'] !== [])->count())
        ->toBe(4);
});

it('case 35 — shares_standard_with DITURUNKAN dari COLUMN_TARGET_PARAMETER, bukan dipaku', function () {
    kernelPlantReportSeedTargets();

    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');
    kernelPlantReportSlot($record, '07:00', ['ripple_mill_1_amps' => 22.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    $map = KernelPlantReportService::COLUMN_TARGET_PARAMETER;

    // TUJUH entri untuk TUJUH kolom ukur — tidak ada kolom tanpa standar.
    expect($map)->toHaveCount(7);
    expect(array_keys($map))->toBe(KernelPlantReportService::NUMERIC_METRICS);
    // Tetapi hanya LIMA parameter berbeda, karena DUA pasangan menunjuk
    // parameter yang sama. Inilah sebabnya targetsWithoutMetric() harus
    // membandingkan atas HIMPUNAN, bukan atas count().
    expect(array_unique(array_values($map)))->toHaveCount(5);

    // Keluaran service dibandingkan dengan hasil yang DITURUNKAN dari peta,
    // untuk ketujuh kolom.
    foreach ($map as $column => $parameter) {
        $expected = array_values(array_keys(array_filter(
            $map,
            fn (string $candidate, string $candidateColumn) => $candidate === $parameter && $candidateColumn !== $column,
            ARRAY_FILTER_USE_BOTH,
        )));

        expect(kernelPlantReportMetric($summary, $column)['target']['shares_standard_with'])
            ->toBe($expected, "shares_standard_with {$column} harus turunan peta");
    }

    // GREP PENDAMPING: badan sharesStandardWith() tidak memuat satu pun nama
    // kolom maupun nama parameter. Penambahan ripple mill atau silo KETIGA
    // harus tertandai benar tanpa menyentuh metode ini.
    $method = new ReflectionMethod(KernelPlantReportService::class, 'sharesStandardWith');
    $lines = file($method->getFileName());
    $body = implode('', array_slice(
        $lines,
        $method->getStartLine() - 1,
        $method->getEndLine() - $method->getStartLine() + 1,
    ));

    foreach (array_merge(array_keys($map), array_values($map)) as $literal) {
        expect($body)->not->toContain($literal);
    }
});

it('case 36 — targetFor memulangkan baris master yang SAMA untuk kedua anggota pasangan, keterangan dalam tanda kurung UTUH', function () {
    kernelPlantReportSeedTargets();

    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');
    kernelPlantReportSlot($record, '07:00', [
        'ripple_mill_1_amps' => 22.0,
        'ripple_mill_2_amps' => 23.0,
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    $first = kernelPlantReportMetric($summary, 'ripple_mill_1_amps')['target'];
    $second = kernelPlantReportMetric($summary, 'ripple_mill_2_amps')['target'];

    expect($first['equipment_parameter'])->toBe('Ripple Mill (Cracker)');
    expect($second['equipment_parameter'])->toBe('Ripple Mill (Cracker)');
    // Keterangan dalam tanda kurung dibawa UTUH, tanpa dipotong: kualifikasinya
    // bagian dari standar, bukan hiasan.
    expect($first['target_benchmark'])->toBe('20 - 25 Amps (Nut Breakage >95%)');
    expect($second['target_benchmark'])->toBe('20 - 25 Amps (Nut Breakage >95%)');
    expect($first['corrective_action_plan'])->toBe('Adjust rotor-vane clearance if uncracked nut rate >5%.');
    expect($second['corrective_action_plan'])->toBe($first['corrective_action_plan']);

    // KETIGA KOLOM MASTER, dan NAMANYA: tidak satu pun dari ketiganya sama
    // dengan nama kolom master Threshing, Pressing, maupun Depricarping.
    // Menyalin nama tetangga menghasilkan blok target yang seluruhnya null
    // tanpa satu pun galat.
    //
    // DAN HANYA EMPAT KUNCI. Tech spec menyebut `has_standard` pada
    // metrics[].target; kunci itu TIDAK ADA di payload (has_standard hanya ada
    // pada blok downtime, case 51). Masternya juga tidak punya kolom
    // critical_limit maupun operational_consequence_justification, jadi
    // keduanya tidak diterbitkan sebagai kunci yang selamanya null.
    expect(array_keys($first))->toBe([
        'equipment_parameter',
        'target_benchmark',
        'corrective_action_plan',
        'shares_standard_with',
    ]);
    expect($first)->not->toHaveKey('critical_limit');
    expect($first)->not->toHaveKey('operational_consequence_justification');
    expect($first)->not->toHaveKey('has_standard');

    // Dan master 'Kernel Silo 1 & 2' membawa derajat serta kualifikasi zonanya
    // apa adanya.
    expect(kernelPlantReportMetric($summary, 'kernel_silo_1_temp_c')['target']['target_benchmark'])
        ->toBe('70°C - 80°C (Top/Middle zones)');
});

it('case 37 — angka kedua ripple mill dan kedua silo TIDAK PERNAH dirata-ratakan menjadi satu', function () {
    kernelPlantReportSeedTargets();

    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');

    foreach (array_slice($this->slots, 0, 3) as $slot) {
        kernelPlantReportSlot($record, $slot, [
            'ripple_mill_1_amps' => 24.0,
            'ripple_mill_2_amps' => 12.0,
            'kernel_silo_1_temp_c' => 78.0,
            'kernel_silo_2_temp_c' => 66.0,
        ]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect(kernelPlantReportMetric($summary, 'ripple_mill_1_amps')['avg'])->toBe(24.0);
    expect(kernelPlantReportMetric($summary, 'ripple_mill_2_amps')['avg'])->toBe(12.0);
    expect(kernelPlantReportMetric($summary, 'kernel_silo_1_temp_c')['avg'])->toBe(78.0);
    expect(kernelPlantReportMetric($summary, 'kernel_silo_2_temp_c')['avg'])->toBe(66.0);

    // Masing-masing dengan penyebutnya sendiri.
    foreach (['ripple_mill_1_amps', 'ripple_mill_2_amps', 'kernel_silo_1_temp_c', 'kernel_silo_2_temp_c'] as $column) {
        expect(kernelPlantReportMetric($summary, $column)['filled_slot_count'])->toBe(3);
    }

    // TIDAK ADA entri bernilai 18.0 maupun 72.0 — merata-ratakan pasangan akan
    // menyembunyikan unit yang menyimpang di belakang unit yang normal, sama
    // dengan menjumlahkan dua blok unit pada laporan Grading.
    $averages = collect($summary['metrics'])->pluck('avg')->all();

    expect($averages)->not->toContain(18.0);
    expect($averages)->not->toContain(72.0);

    // Dan tidak ada entri GABUNGAN: tujuh kolom, tujuh entri, nama kolomnya
    // persis nama kolom tabel.
    expect(collect($summary['metrics'])->pluck('column')->all())
        ->toBe(KernelPlantReportService::NUMERIC_METRICS);
    expect(collect($summary['metrics'])->pluck('column')->all())
        ->not->toContain('ripple_mill_amps');
    expect(collect($summary['metrics'])->pluck('column')->all())
        ->not->toContain('kernel_silo_temp_c');
});

it('case 38 — targetsWithoutMetric membandingkan terhadap HIMPUNAN 5 nama parameter, bukan count() 7 entri peta', function () {
    kernelPlantReportSeedTargets();

    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');
    kernelPlantReportSlot($record, '07:00', ['ripple_mill_1_amps' => 22.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // KEADAAN MANTAP LAYAR INI: tepat SATU baris. Kernel dirt tidak punya
    // kolom ukur di MANA PUN pada skema ini, jadi tidak ada pemetaan yang
    // dapat menutup celahnya. DITERBITKAN, bukan disembunyikan: standar yang
    // tidak pernah diukur terbaca seperti terpenuhi padahal ia sekadar tidak
    // ada — dan yang ini menyebut premi mutu yang dibayarkan ke mill.
    expect($summary['targets_without_metric'])->toHaveCount(1);

    $row = $summary['targets_without_metric'][0];

    // KUNCINYA equipment_parameter — barisnya adalah baris master APA ADANYA
    // plus `reason`, jadi namanya nama kolom yang sebenarnya, bukan
    // 'parameter'.
    expect(array_keys($row))->toBe([
        'equipment_parameter',
        'target_benchmark',
        'corrective_action_plan',
        'reason',
    ]);
    expect($row['equipment_parameter'])->toBe('Final Kernel Dirt');
    expect($row['target_benchmark'])->toBe('≤ 6.0% (Standard quality premium)');
    expect($row['corrective_action_plan'])->toBe('Clean winnowing ducts or re-calibrate hydrocyclone settings.');
    expect($row['reason'])->toBe(KernelPlantReportService::UNMAPPED_NO_COLUMN);
    expect($row['reason'])->toBe('no_column');

    // Kedua parameter berbagi standar TIDAK muncul di situ, meski masing-masing
    // dipakai DUA KALI oleh peta. Membandingkan count() akan membuat keduanya
    // tampak terpakai dua kali dan meninggalkan standar sungguhan tampak tak
    // terpakai.
    $names = collect($summary['targets_without_metric'])->pluck('equipment_parameter')->all();

    expect($names)->not->toContain('Ripple Mill (Cracker)');
    expect($names)->not->toContain('Kernel Silo 1 & 2');

    expect($summary['all_targets_measured'])->toBeFalse();
    expect($summary['targets_master_empty'])->toBeFalse();
});

it('case 39 — kesamaan JUMLAH tidak membuat Final Kernel Dirt luput: 7 baris master lawan 7 entri peta', function () {
    kernelPlantReportSeedTargets();

    // Master SENGAJA diisi 7 baris, sehingga
    // count(master) === count(COLUMN_TARGET_PARAMETER) === 7. Implementasi
    // yang membandingkan count() akan melihat 7 === 7 dan menerbitkan daftar
    // KOSONG — tepat kegagalan yang diuji di sini.
    KernelPlantOperationalTarget::create([
        'equipment_parameter' => 'Winnowing Column Draft',
        'target_benchmark' => '8 - 12 mmH2O',
        'corrective_action_plan' => 'Re-balance damper if shell carry-over rises.',
        'sort_order' => 7,
    ]);

    expect(KernelPlantOperationalTarget::query()->count())
        ->toBe(count(KernelPlantReportService::COLUMN_TARGET_PARAMETER));

    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');
    kernelPlantReportSlot($record, '07:00', ['ripple_mill_1_amps' => 22.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // DUA baris, dan 'Final Kernel Dirt' TETAP di antaranya.
    expect($summary['targets_without_metric'])->toHaveCount(2);

    $names = collect($summary['targets_without_metric'])->pluck('equipment_parameter')->all();

    expect($names)->toContain('Final Kernel Dirt');
    expect($names)->toContain('Winnowing Column Draft');

    foreach ($summary['targets_without_metric'] as $row) {
        expect($row['reason'])->toBe('no_column');
    }

    expect($summary['all_targets_measured'])->toBeFalse();
});

it('case 40 — all_targets_measured true dan kuncinya TETAP ADA ketika targets_without_metric kosong', function () {
    // Master diisi HANYA 5 baris yang kelimanya namanya cocok dengan nilai
    // COLUMN_TARGET_PARAMETER — baris 'Final Kernel Dirt' dihapus.
    foreach (kernelPlantReportTargetRows() as $index => [$parameter, $benchmark, $plan]) {
        if ($parameter === 'Final Kernel Dirt') {
            continue;
        }

        KernelPlantOperationalTarget::create([
            'equipment_parameter' => $parameter,
            'target_benchmark' => $benchmark,
            'corrective_action_plan' => $plan,
            'sort_order' => $index + 1,
        ]);
    }

    expect(KernelPlantOperationalTarget::query()->count())->toBe(5);

    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');
    kernelPlantReportSlot($record, '07:00', ['ripple_mill_1_amps' => 22.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['targets_without_metric'])->toBe([]);
    // KUNCINYA HARUS ADA, bukan sekadar bernilai true: layar memakainya untuk
    // MENGGAMBAR bagiannya dengan keterangan alih-alih menyembunyikannya.
    // Bagian yang hilang tidak dapat dibedakan dari bagian yang tak pernah
    // dibuat siapa pun.
    expect($summary)->toHaveKey('all_targets_measured');
    expect($summary['all_targets_measured'])->toBeTrue();

    // KEDUA KUNCI diasersi terpisah, karena keduanya dapat sama-sama menunjuk
    // daftar kosong untuk sebab yang BERLAWANAN: master yang belum terisi
    // versus seluruh standar yang sudah terukur.
    expect($summary['targets_master_empty'])->toBeFalse();

    // Dan kelima standar itu memang terpasang pada ketujuh kolom.
    foreach (KernelPlantReportService::NUMERIC_METRICS as $column) {
        expect(kernelPlantReportMetric($summary, $column)['target']['target_benchmark'])
            ->not->toBeNull("kolom {$column} harus membawa standarnya");
    }
});

it('case 41 — master kosong: targets_master_empty true, ketujuh metrik tetap terbit, angkanya utuh', function () {
    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');
    kernelPlantReportSlot($record, '07:00', ['ripple_mill_1_amps' => 22.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['targets_master_empty'])->toBeTrue();
    expect($summary['targets_without_metric'])->toBe([]);
    // Master kosong BUKAN sama dengan "semuanya terukur" — layar memeriksa
    // targets_master_empty LEBIH DULU justru karena itu.
    expect($summary['all_targets_measured'])->toBeTrue();

    expect($summary['metrics'])->toHaveCount(7);

    // Tech spec menyebut has_standard = false di sini; kunci itu tidak ada
    // pada metrics[].target, jadi "tanpa standar" dinyatakan sebagai ketiga
    // kolom master yang null — dan layar mengatakannya dengan "belum terisi
    // pada master".
    foreach ($summary['metrics'] as $metric) {
        expect($metric['target'])->not->toHaveKey('has_standard');
        expect($metric['target']['equipment_parameter'])->toBeNull();
        expect($metric['target']['target_benchmark'])->toBeNull();
        expect($metric['target']['corrective_action_plan'])->toBeNull();
        // shares_standard_with TETAP turunan peta, bukan turunan master.
        expect($metric['target'])->toHaveKey('shares_standard_with');
    }

    expect(kernelPlantReportMetric($summary, 'ripple_mill_1_amps')['target']['shares_standard_with'])
        ->toBe(['ripple_mill_2_amps']);

    // Seeder yang belum dijalankan tidak boleh menghapus pengukuran yang
    // sudah terjadi — laporan tetap berguna sebagai angka.
    expect(kernelPlantReportMetric($summary, 'ripple_mill_1_amps')['avg'])->toBe(22.0);
    expect($summary['has_data'])->toBeTrue();
});

it('case 42 — nama parameter master yang disunting melepas standarnya DAN memindahkannya ke targets_without_metric', function () {
    kernelPlantReportSeedTargets();

    KernelPlantOperationalTarget::query()
        ->where('equipment_parameter', 'Kernel Silo 1 & 2')
        ->update(['equipment_parameter' => 'Kernel Silo 1 and 2']);

    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');
    kernelPlantReportSlot($record, '07:00', [
        'kernel_silo_1_temp_c' => 75.0,
        'kernel_silo_2_temp_c' => 76.0,
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // ANGKANYA UTUH — pemetaan tidak bersandar pada teks nama parameter, jadi
    // perbaikan ejaan di master tidak dapat diam-diam melepas sebuah figur
    // dari standarnya.
    foreach (['kernel_silo_1_temp_c' => 75.0, 'kernel_silo_2_temp_c' => 76.0] as $column => $avg) {
        $metric = kernelPlantReportMetric($summary, $column);

        expect($metric['avg'])->toBe($avg);
        expect($metric['filled_slot_count'])->toBe(1);
        // Standarnya terlepas, dan keterlepasan itu TERLIHAT alih-alih senyap.
        expect($metric['target']['equipment_parameter'])->toBeNull();
        expect($metric['target']['target_benchmark'])->toBeNull();
        expect($metric['target']['corrective_action_plan'])->toBeNull();
        // Pasangannya tetap dikenali: shares_standard_with turunan PETA.
        expect($metric['target']['shares_standard_with'])->toHaveCount(1);
    }

    // DUA entri: baris yang namanya disunting, DI SAMPING 'Final Kernel Dirt'.
    expect($summary['targets_without_metric'])->toHaveCount(2);

    $names = collect($summary['targets_without_metric'])->pluck('equipment_parameter')->all();

    expect($names)->toContain('Kernel Silo 1 and 2');
    expect($names)->toContain('Final Kernel Dirt');

    $renamed = collect($summary['targets_without_metric'])
        ->firstWhere('equipment_parameter', 'Kernel Silo 1 and 2');

    expect($renamed['reason'])->toBe('no_column');
    expect($renamed['target_benchmark'])->toBe('70°C - 80°C (Top/Middle zones)');

    expect($summary['all_targets_measured'])->toBeFalse();
});

it('case 43 — targetsByParameter mempertahankan urutan sort_order master, bukan urutan penyisipan', function () {
    // Enam baris master DISISIPKAN TERBALIK, dengan sort_order 1..6 tetap
    // benar. Lalu dua baris tak terpeta ditambahkan dengan sort_order yang
    // membuat urutan penyisipan dan urutan sort_order BERBEDA.
    foreach (array_reverse(kernelPlantReportTargetRows(), true) as $index => [$parameter, $benchmark, $plan]) {
        KernelPlantOperationalTarget::create([
            'equipment_parameter' => $parameter,
            'target_benchmark' => $benchmark,
            'corrective_action_plan' => $plan,
            'sort_order' => $index + 1,
        ]);
    }

    // 'Final Kernel Dirt' ada di sort_order 5. Dua baris tak terpeta di
    // bawah disisipkan dengan sort_order 4 dan 9 — satu DI DEPAN dan satu DI
    // BELAKANGNYA — sementara urutan penyisipannya justru terbalik.
    KernelPlantOperationalTarget::create([
        'equipment_parameter' => 'Zeta Parameter Tak Terukur',
        'target_benchmark' => 'n/a',
        'corrective_action_plan' => 'n/a',
        'sort_order' => 9,
    ]);
    KernelPlantOperationalTarget::create([
        'equipment_parameter' => 'Alfa Parameter Tak Terukur',
        'target_benchmark' => 'n/a',
        'corrective_action_plan' => 'n/a',
        'sort_order' => 4,
    ]);

    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');
    kernelPlantReportSlot($record, '07:00', ['ripple_mill_1_amps' => 22.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // Urutannya mengikuti sort_order (4, 5, 9), BUKAN urutan penyisipan
    // (Zeta lebih dulu daripada Alfa) dan bukan alfabet.
    expect(collect($summary['targets_without_metric'])->pluck('equipment_parameter')->all())
        ->toBe([
            'Alfa Parameter Tak Terukur',
            'Final Kernel Dirt',
            'Zeta Parameter Tak Terukur',
        ]);
});

// =====================================================================
// GROUP G — REKAP PER UNIT, HARIAN, DAN TOTAL (case 44-47, 57)
// =====================================================================

it('case 44 — by_kernel_plant menerbitkan rekap per unit, dan kernel_plant_id adalah LABEL bukan kunci baris', function () {
    // Unit A: 8 slot terisi pada DUA tanggal. Unit B: 3 slot terisi pada satu
    // tanggal.
    $unitADay1 = kernelPlantReportRecord($this->stationA, $this->day1, 'KP-01');
    $unitADay2 = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-01');
    $unitB = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-02');

    foreach (array_slice($this->slots, 0, 4) as $slot) {
        kernelPlantReportSlot($unitADay1, $slot, ['ripple_mill_1_amps' => 20.0, 'downtime_minutes' => 5]);
        kernelPlantReportSlot($unitADay2, $slot, ['ripple_mill_1_amps' => 24.0]);
    }

    foreach (array_slice($this->slots, 0, 3) as $slot) {
        kernelPlantReportSlot($unitB, $slot, ['ripple_mill_1_amps' => 12.0]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['by_kernel_plant'])->toHaveCount(2);
    expect(array_keys($summary['by_kernel_plant'][0]))->toBe([
        'kernel_plant_id',
        'kernel_plant_name',
        'day_count',
        'filled_slot_count',
        'downtime_minutes',
        'averages',
    ]);

    $byUnit = collect($summary['by_kernel_plant'])->keyBy('kernel_plant_id');

    expect($byUnit['KP-01']['filled_slot_count'])->toBe(8);
    expect($byUnit['KP-02']['filled_slot_count'])->toBe(3);
    // kernel_plant_id yang SAMA pada dua tanggal adalah SATU unit dengan dua
    // hari record — bukan dua baris. day_count-nya mengatakan dua.
    expect($byUnit['KP-01']['day_count'])->toBe(2);
    expect($byUnit['KP-02']['day_count'])->toBe(1);

    // LABEL, BUKAN KUNCI ASING: kernel_plant_records.kernel_plant_id adalah
    // kolom `string` biasa yang diketik di layar input, bukan uuid dan bukan
    // FK — tidak ada tabel master `kernel_plants` pada skema ini — jadi
    // kernel_plant_name adalah string itu sendiri.
    expect($byUnit['KP-01']['kernel_plant_name'])->toBe('KP-01');
    expect($byUnit['KP-02']['kernel_plant_name'])->toBe('KP-02');

    // averages adalah MAP kolom -> float|null per unit, bukan satu angka.
    expect($byUnit['KP-01']['averages'])->toBeArray();
    expect(array_keys($byUnit['KP-01']['averages']))->toBe(KernelPlantReportService::NUMERIC_METRICS);
    expect($byUnit['KP-01']['averages']['ripple_mill_1_amps'])->toBe(22.0);
    expect($byUnit['KP-02']['averages']['ripple_mill_1_amps'])->toBe(12.0);
    expect($byUnit['KP-01']['averages']['shell_loss_percent'])->toBeNull();

    // downtime per unit: 4 slot x 5 menit pada unit A; null — BUKAN 0 — pada
    // unit yang tak satu slotnya mencatat.
    expect($byUnit['KP-01']['downtime_minutes'])->toBe(20);
    expect($byUnit['KP-02']['downtime_minutes'])->toBeNull();
});

it('case 45 — dailyOf menerbitkan satu entri per TANGGAL YANG PUNYA RECORD, dengan penyebut hari itu', function () {
    $dayOne = kernelPlantReportRecord($this->stationA, $this->day1, 'KP-1');
    kernelPlantReportSlot($dayOne, $this->slots[0], ['ripple_mill_1_amps' => 20.0]);
    kernelPlantReportSlot($dayOne, $this->slots[1], ['ripple_mill_1_amps' => 22.0]);

    $dayTwo = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');

    foreach ($this->slots as $slot) {
        kernelPlantReportSlot($dayTwo, $slot, ['ripple_mill_1_amps' => 30.0]);
    }

    // Hari ketiga PUNYA record tetapi tak satu slotnya terisi.
    $dayThree = kernelPlantReportRecord($this->stationA, $this->day3, 'KP-1');
    kernelPlantReportSlot($dayThree, $this->slots[0]);

    $this->actingAs($this->supervisorA);

    $daily = $this->service->buildSummary($this->periodA, null, $this->lineA)['daily'];

    // TIGA entri, terurut tanggal naik: tanggal yang punya record TETAP
    // mendapat baris walau nol slotnya terisi — membuangnya akan membuat
    // periode terlihat lebih tercatat daripada kenyataannya.
    expect($daily)->toHaveCount(3);
    expect(collect($daily)->pluck('date')->all())->toBe([$this->day1, $this->day2, $this->day3]);
    expect($daily[0]['filled_slot_count'])->toBe(2);
    expect($daily[1]['filled_slot_count'])->toBe(24);
    expect($daily[2]['filled_slot_count'])->toBe(0);

    // averages per kolom per hari, dan null untuk hari yang tak mengukurnya.
    expect($daily[0]['averages']['ripple_mill_1_amps'])->toBe(21.0);
    expect($daily[1]['averages']['ripple_mill_1_amps'])->toBe(30.0);
    expect($daily[2]['averages']['ripple_mill_1_amps'])->toBeNull();

    // Hari TANPA record sama sekali tidak mendapat baris: baris nol untuk hari
    // pabrik tidak beroperasi akan terbaca sebagai "kami mengukur dan hasilnya
    // nol".
    expect(collect($daily)->pluck('date')->all())->not->toContain($this->day4);
});

it('case 46 — daily_total DIHITUNG ULANG atas seluruh slot terisi, bukan merata-ratakan rata-rata harian', function () {
    // Hari-1: 2 slot bernilai 10 (rata-rata harian 10).
    $dayOne = kernelPlantReportRecord($this->stationA, $this->day1, 'KP-1');

    foreach (array_slice($this->slots, 0, 2) as $slot) {
        kernelPlantReportSlot($dayOne, $slot, ['ripple_mill_1_amps' => 10.0]);
    }

    // Hari-2: 24 slot bernilai 30 (rata-rata harian 30).
    $dayTwo = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');

    foreach ($this->slots as $slot) {
        kernelPlantReportSlot($dayTwo, $slot, ['ripple_mill_1_amps' => 30.0]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // (2*10 + 24*30) / 26 = 740/26 = 28,46 — BUKAN (10+30)/2 = 20,0.
    // Merata-ratakan rata-rata memberi bobot sama pada hari dengan satu slot
    // dan hari dengan dua puluh empat.
    expect($summary['daily_total']['averages']['ripple_mill_1_amps'])->toBe(28.46);
    expect($summary['daily_total']['averages']['ripple_mill_1_amps'])->not->toBe(20.0);

    // Kedua rata-rata harian tetap terbit apa adanya di sisi lain payload.
    expect($summary['daily'][0]['averages']['ripple_mill_1_amps'])->toBe(10.0);
    expect($summary['daily'][1]['averages']['ripple_mill_1_amps'])->toBe(30.0);
});

it('case 47 — daily_total.filled_slot_count sama dengan jumlah seluruh daily[].filled_slot_count', function () {
    $dayOne = kernelPlantReportRecord($this->stationA, $this->day1, 'KP-1');

    foreach (array_slice($this->slots, 0, 2) as $slot) {
        kernelPlantReportSlot($dayOne, $slot, ['ripple_mill_1_amps' => 10.0]);
    }

    $dayTwo = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');

    foreach ($this->slots as $slot) {
        kernelPlantReportSlot($dayTwo, $slot, ['ripple_mill_1_amps' => 30.0]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['daily_total']['filled_slot_count'])->toBe(26);
    expect(collect($summary['daily'])->sum('filled_slot_count'))->toBe(26);
    // Dan sama dengan pembilang cakupan — satu kenyataan, satu angka.
    expect($summary['coverage']['filled_slots'])->toBe(26);
});

it('case 57 — total[] menerbitkan kelima penghitung periodenya', function () {
    $plan = [
        // [tanggal, status, sudah diperiksa, sudah diakui]
        [$this->day1, RecordStatus::DraftOngoing, true, true],
        [$this->day1, RecordStatus::DraftPaused, true, true],
        [$this->day2, RecordStatus::Synced, true, true],
        [$this->day2, RecordStatus::Synced, true, true],
        [$this->day3, RecordStatus::Synced, false, true],
        [$this->day4, RecordStatus::Synced, false, true],
        [$this->day4, RecordStatus::Synced, false, false],
    ];

    foreach ($plan as $index => [$date, $status, $checked, $acknowledged]) {
        $record = kernelPlantReportRecord($this->stationA, $date, 'KP-'.($index + 1), [
            'status' => $status,
            'checked_by' => $checked ? $this->supervisorA->id : null,
            'acknowledged_by' => $acknowledged ? $this->millManagementA->id : null,
        ]);

        kernelPlantReportSlot($record, '07:00', ['ripple_mill_1_amps' => 22.0]);
    }

    $this->actingAs($this->supervisorA);

    $total = $this->service->buildSummary($this->periodA, null, $this->lineA)['total'];

    expect($total['record_count'])->toBe(7);
    expect($total['days_with_records'])->toBe(4);
    // Kedua keadaan draft dihitung BERSAMA: yang perlu diketahui pembaca
    // adalah "seberapa besar laporan ini berdiri di atas data yang belum
    // selesai", dan draft berjalan maupun draft terhenti sama-sama belum
    // selesai.
    expect($total['draft_record_count'])->toBe(2);
    expect($total['records_not_checked'])->toBe(3);
    expect($total['records_not_acknowledged'])->toBe(1);

    // Dan status verifikasi BUKAN penyaring: ketujuhnya tetap terhitung penuh.
    expect(kernelPlantReportMetric(
        $this->service->buildSummary($this->periodA, null, $this->lineA),
        'ripple_mill_1_amps',
    )['filled_slot_count'])->toBe(7);
});

// =====================================================================
// GROUP H — DOWNTIME DAN TEMUAN (case 48-56)
// =====================================================================

it('case 48 — downtime.total_minutes NULL, bukan 0, ketika tak satu slot pun mencatatnya', function () {
    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');

    foreach (array_slice($this->slots, 0, 12) as $slot) {
        kernelPlantReportSlot($record, $slot, ['ripple_mill_1_amps' => 22.0]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // Total 0 menit terbaca seperti "stasiun tidak pernah berhenti", padahal
    // yang benar adalah "tidak ada yang mencatatnya". assertNull, bukan
    // assertSame(0).
    expect($summary['downtime']['total_minutes'])->toBeNull();
    expect($summary['downtime']['recorded_slot_count'])->toBe(0);
    expect($summary['downtime']['avg_minutes_per_recorded_slot'])->toBeNull();
    // Dan ke-12 slotnya tetap terhitung terisi.
    expect($summary['coverage']['filled_slots'])->toBe(12);
});

it('case 49 — downtime.total_minutes 0 dan recorded_slot_count MENGHITUNG nol yang tercatat', function () {
    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');

    foreach (array_slice($this->slots, 0, 3) as $slot) {
        kernelPlantReportSlot($record, $slot, ['downtime_minutes' => 0]);
    }

    foreach (array_slice($this->slots, 3, 2) as $slot) {
        kernelPlantReportSlot($record, $slot, ['ripple_mill_1_amps' => 22.0]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // assertSame(0), BUKAN null. "Tercatat nol menit" dan "tidak dicatat"
    // adalah dua kenyataan berbeda, dan uji ini satu-satunya yang
    // membedakannya: nol berarti SESEORANG MENYATAKAN stasiun tidak berhenti
    // pada slot itu, dan memperlakukannya sebagai "tidak tercatat" akan
    // membuang pernyataan itu.
    expect($summary['downtime']['total_minutes'])->toBe(0);
    expect($summary['downtime']['recorded_slot_count'])->toBe(3);
    expect($summary['downtime']['avg_minutes_per_recorded_slot'])->toBe(0.0);
});

it('case 50 — downtime menjumlahkan campuran nol dan non-nol dengan penyebut yang benar', function () {
    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');

    foreach ([0, 0, 15, 45] as $index => $minutes) {
        kernelPlantReportSlot($record, $this->slots[$index], ['downtime_minutes' => $minutes]);
    }

    foreach ([4, 5] as $index) {
        kernelPlantReportSlot($record, $this->slots[$index], ['ripple_mill_1_amps' => 22.0]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['downtime']['total_minutes'])->toBe(60);
    // PENYEBUTNYA 4, BUKAN 2: nol tetap pencatatan. Memakai 2 akan membuat
    // rata-rata MEMBESAR karena nol dibuang; memakai 6 akan membuatnya
    // mengecil justru seiring bertambahnya slot yang TIDAK dicatat — laporan
    // akan tampak lebih baik karena lebih sedikit yang ditulis.
    expect($summary['downtime']['recorded_slot_count'])->toBe(4);
    expect($summary['downtime']['avg_minutes_per_recorded_slot'])->toBe(15.0);

    // Pencilan ikut total apa adanya — laporan tidak punya dasar menyebut
    // sebuah angka salah.
    kernelPlantReportSlot($record, $this->slots[6], ['downtime_minutes' => 1440]);

    $withOutlier = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($withOutlier['downtime']['total_minutes'])->toBe(1500);
    expect($withOutlier['downtime']['recorded_slot_count'])->toBe(5);
});

it('case 51 — downtime.has_standard selalu false karena downtime tidak punya baris di master', function () {
    kernelPlantReportSeedTargets();

    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');
    kernelPlantReportSlot($record, '07:00', ['downtime_minutes' => 15]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // DINYATAKAN SEBAGAI KUNCI, bukan dibiarkan terbaca sebagai master yang
    // belum terisi: keenam parameter master seluruhnya pengukuran, tidak satu
    // pun tentang lama berhenti.
    expect($summary['downtime'])->toHaveKey('has_standard');
    expect($summary['downtime']['has_standard'])->toBeFalse();
    expect(array_keys($summary['downtime']))->toBe([
        'total_minutes',
        'recorded_slot_count',
        'avg_minutes_per_recorded_slot',
        'has_standard',
    ]);

    // Dan tidak ada upaya mencocokkan downtime ke equipment_parameter mana
    // pun: ia bukan salah satu dari ketujuh kolom ukur.
    expect(KernelPlantReportService::COLUMN_TARGET_PARAMETER)->not->toHaveKey('downtime_minutes');
});

it('case 52 — findingsOf mengelompokkan HARFIAH tanpa normalisasi huruf, ejaan, maupun spasi', function () {
    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');

    kernelPlantReportSlot($record, $this->slots[0], ['findings' => 'Ripple mill bising']);
    kernelPlantReportSlot($record, $this->slots[1], ['findings' => 'ripple mill bising']);
    kernelPlantReportSlot($record, $this->slots[2], ['findings' => 'Ripple  mill bising']);

    $this->actingAs($this->supervisorA);

    $findings = $this->service->buildSummary($this->periodA, null, $this->lineA)['findings'];

    // TIGA entri terpisah, masing-masing slot_count 1 — bukan satu entri
    // slot_count 3. Menyeragamkan akan menggabungkan sebab yang penulisnya
    // memang maksudkan berbeda; layar menyatakan sifat harfiahnya supaya tiga
    // baris mirip tidak dibaca sebagai cacat laporan.
    //
    // Urutannya: ketiganya ber-slot_count 1, jadi pemutus serinya teks naik —
    // spasi (0x20) mendahului 'm', dan 'R' (0x52) mendahului 'r'.
    expect($findings)->toBe([
        ['finding' => 'Ripple  mill bising', 'slot_count' => 1],
        ['finding' => 'Ripple mill bising', 'slot_count' => 1],
        ['finding' => 'ripple mill bising', 'slot_count' => 1],
    ]);
});

it('case 53 — findings diurutkan slot_count turun lalu teks naik sebagai pemutus seri', function () {
    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');

    $semai = [
        'Bearing panas' => 3,
        'Zink habis' => 2,
        'Ayakan kotor' => 2,
    ];

    $slotIndex = 0;

    foreach ($semai as $finding => $count) {
        foreach (range(1, $count) as $ignored) {
            kernelPlantReportSlot($record, $this->slots[$slotIndex++], ['findings' => $finding]);
        }
    }

    $this->actingAs($this->supervisorA);

    $findings = $this->service->buildSummary($this->periodA, null, $this->lineA)['findings'];

    // URUTAN PERSIS, bukan hanya keanggotaan: pemutus seri alfabetis itulah
    // yang membuat urutannya stabil antar-render alih-alih bergantung pada
    // urutan baris yang dipulangkan mesin basis data.
    expect($findings)->toBe([
        ['finding' => 'Bearing panas', 'slot_count' => 3],
        ['finding' => 'Ayakan kotor', 'slot_count' => 2],
        ['finding' => 'Zink habis', 'slot_count' => 2],
    ]);
});

it('case 54 — findings terpisah dari downtime: slot yang membawa keduanya tidak dihitung dua kali', function () {
    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');

    kernelPlantReportSlot($record, '07:00', [
        'downtime_minutes' => 30,
        'findings' => 'Ripple mill berhenti',
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // SLOT YANG SAMA, DUA BLOK yang menjawab dua pertanyaan berbeda, tanpa
    // penggandaan. Menggabungkannya akan menghitung slot ini dua kali di
    // bawah satu judul dan menghancurkan kemampuan menjawab salah satunya
    // sendiri.
    expect($summary['coverage']['filled_slots'])->toBe(1);
    expect($summary['downtime']['total_minutes'])->toBe(30);
    expect($summary['downtime']['recorded_slot_count'])->toBe(1);
    expect($summary['findings'])->toBe([['finding' => 'Ripple mill berhenti', 'slot_count' => 1]]);

    // Dan tidak ada satu pun kunci yang menggabungkan keduanya.
    expect($summary['downtime'])->not->toHaveKey('findings');
    expect(kernelPlantReportAllKeys($summary))->not->toContain('downtime_findings');
});

it('case 55 — findings kosong ("" maupun null) tidak pernah menjadi kelompok', function () {
    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');

    kernelPlantReportSlot($record, $this->slots[0], ['findings' => '', 'ripple_mill_1_amps' => 22.0]);
    kernelPlantReportSlot($record, $this->slots[1], ['findings' => null, 'ripple_mill_1_amps' => 23.0]);
    // Hanya spasi: dipangkas di ujungnya oleh rowOf(), lalu menjadi null —
    // bukan kelompok berlabel kosong.
    kernelPlantReportSlot($record, $this->slots[2], ['findings' => '   ', 'ripple_mill_1_amps' => 24.0]);
    kernelPlantReportSlot($record, $this->slots[3], ['findings' => 'Kernel basah']);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['findings'])->toBe([['finding' => 'Kernel basah', 'slot_count' => 1]]);
    expect(collect($summary['findings'])->pluck('finding')->all())->not->toContain('');
    expect(collect($summary['findings'])->pluck('finding')->all())->not->toContain('   ');
});

it('case 56 — TIDAK ADA satu pun penandaan di luar batas: diasersi atas KETIADAAN KUNCI pada struktur', function () {
    kernelPlantReportSeedTargets();

    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');

    // Nilai SENGAJA jauh di luar target: 90.0 Amps terhadap
    // '20 - 25 Amps (Nut Breakage >95%)' dan kadar air 45.0% terhadap
    // '≤ 7.0% (Prevents mold growth)'. Keduanya jelas melewati standarnya,
    // dan laporan ini TETAP tidak menilainya.
    kernelPlantReportSlot($record, '07:00', [
        'ripple_mill_1_amps' => 90.0,
        'kernel_moisture_percent' => 45.0,
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // DIASERSI ATAS KETIADAAN KUNCI, bukan atas ketiadaan frasa: kalimat
    // penjelasan di layar justru memuat kata "di luar batas", jadi pencarian
    // frasa akan salah tangkap. Dan asersi kelas CSS milik uji UI, bukan
    // lapisan ini.
    $forbiddenOnMetrics = ['severity', 'flag', 'is_out_of_range', 'out_of_range', 'status', 'warning', 'color', 'threshold', 'breach'];

    foreach ($summary['metrics'] as $metric) {
        foreach ($forbiddenOnMetrics as $key) {
            expect($metric)->not->toHaveKey($key, "metrics[{$metric['column']}] tidak boleh punya kunci {$key}");
            expect($metric['target'])->not->toHaveKey($key, "metrics[{$metric['column']}].target tidak boleh punya kunci {$key}");
        }

        // Yang ADA hanyalah angka dan kedua kolom target sebagai TEKS.
        expect(array_keys($metric))->toBe([
            'column', 'label', 'unit', 'min', 'avg', 'max', 'filled_slot_count', 'target',
        ]);
    }

    // PENYISIRAN SELURUH PAYLOAD. 'status' sengaja TIDAK dilarang di sini:
    // period.status adalah kunci yang sah dan memang status periode, bukan
    // penilaian terhadap sebuah angka.
    $allKeys = kernelPlantReportAllKeys($summary);

    foreach (['severity', 'flag', 'is_out_of_range', 'out_of_range', 'warning', 'color', 'threshold', 'breach', 'exceeds'] as $key) {
        expect($allKeys)->not->toContain($key);
    }

    // Kedua angka tetap diterbitkan apa adanya, berdampingan dengan
    // standarnya.
    expect(kernelPlantReportMetric($summary, 'ripple_mill_1_amps')['avg'])->toBe(90.0);
    expect(kernelPlantReportMetric($summary, 'ripple_mill_1_amps')['target']['target_benchmark'])
        ->toBe('20 - 25 Amps (Nut Breakage >95%)');
    expect(kernelPlantReportMetric($summary, 'kernel_moisture_percent')['avg'])->toBe(45.0);
    expect(kernelPlantReportMetric($summary, 'kernel_moisture_percent')['target']['target_benchmark'])
        ->toBe('≤ 7.0% (Prevents mold growth)');
});

// =====================================================================
// GROUP I — DAFTAR PERIODE (case 58-59)
// =====================================================================

it('case 58 — listPeriods memulangkan [] ketika mill belum punya satu pun periode Kernel Plant', function () {
    Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType('sterilizer')
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Sterilizer Saja')
        ->create();

    $this->actingAs($this->supervisorB);

    // [] dengan HTTP 200 — BUKAN galat, dan BUKAN periode milik jenis stasiun
    // lain yang bocor masuk. Layar menampilkan petunjuk ke Kelola Periode
    // Pelaporan, bukan 404.
    expect($this->service->listPeriods(null))->toBe([]);

    // Dan penyaringnya memang baris period_stations untuk station_type
    // 'kernel-plant' — DENGAN TANDA HUBUNG. Garis bawah di sini tidak akan
    // cocok dengan satu baris pun, sehingga jawabannya [] untuk SETIAP mill,
    // tanpa satu pun galat.
    expect(StationTypeEnum::KernelPlant->value)->toBe('kernel-plant');

    $this->actingAs($this->supervisorA);

    $periods = $this->service->listPeriods(null);

    expect($periods)->toHaveCount(1);
    expect($periods[0]['name'])->toBe('Periode Kernel Plant Alpha');
    expect($periods[0]['station_type'])->toBe('kernel-plant');
});

it('case 59 — listPeriods TIDAK menyaring berdasarkan status, dan terurut terbaru lebih dulu', function () {
    Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelPlant->value)
        ->range(now()->subDays(40)->toDateString(), now()->subDays(31)->toDateString())
        ->closed()
        ->named('Periode Tertutup')
        ->create();

    Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelPlant->value)
        ->range(now()->subDays(5)->toDateString(), now()->addDays(5)->toDateString())
        ->draft()
        ->named('Periode Draft')
        ->create();

    $this->actingAs($this->supervisorA);

    $periods = $this->service->listPeriods(null);

    // KETIGANYA terbit: status mengatur PENULISAN data, bukan pembacaan
    // laporan. Terurut start_date menurun.
    expect(collect($periods)->pluck('name')->all())->toBe([
        'Periode Draft',
        'Periode Kernel Plant Alpha',
        'Periode Tertutup',
    ]);
    // Dan `status` yang dilaporkan adalah status BARIS period_stations untuk
    // jenis stasiun ini, bukan status periode: periode tidak punya status
    // sendiri karena stasiun tidak ditutup serentak.
    expect(collect($periods)->pluck('status')->all())->toBe(['draft', 'open', 'closed']);

    expect(array_keys($periods[0]))->toBe([
        'id', 'name', 'start_date', 'end_date', 'status', 'station_type', 'station_type_label',
    ]);
});

// =====================================================================
// GROUP J — EKSPOR (case 60-64)
// =====================================================================

it('case 60 — export menerbitkan KESEMBILAN kolom READING_FIELDS berurutan di samping kolom konteks', function () {
    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-9');
    kernelPlantReportSlot($record, '07:00', ['ripple_mill_1_amps' => 22.0, 'findings' => 'Bearing panas']);
    kernelPlantReportSlot($record, '08:00', ['kernel_silo_1_temp_c' => 75.0, 'downtime_minutes' => 12]);

    $this->actingAs($this->supervisorA);

    expect(KernelPlantReportService::EXPORT_HEADER)->toBe([
        'Periode',
        'Mill',
        'Production Line',
        'Tanggal',
        'Unit Kernel Plant',
        'Status',
        'Catatan',
        'Slot Waktu',
        'Ripple Mill 1 (Amps)',
        'Ripple Mill 2 (Amps)',
        'Claybath/Hydrocyclone (SG)',
        'Suhu Kernel Silo 1 (C)',
        'Suhu Kernel Silo 2 (C)',
        'Kadar Air Kernel (%)',
        'Shell Bin Kernel Loss (%)',
        'Downtime (Menit)',
        'Temuan',
    ]);
    // 8 kolom konteks+slot + 9 kolom bacaan.
    expect(KernelPlantReportService::EXPORT_HEADER)->toHaveCount(17);

    $body = kernelPlantReportStreamed($this->service->export($this->periodA, 'csv', null, $this->lineA));

    expect($body)->toContain('Slot Waktu');
    // DOWNTIME DAN TEMUAN WAJIB ADA: menghilangkan salah satunya membuat
    // berkasnya tidak dapat menggantikan laporan, yang justru tujuan
    // mengekspornya.
    expect($body)->toContain('Downtime (Menit)');
    expect($body)->toContain('Temuan');
    expect($body)->toContain('Suhu Kernel Silo 1 (C)');
    expect($body)->toContain('Suhu Kernel Silo 2 (C)');
    expect($body)->toContain('KP-9');
    expect($body)->toContain('Bearing panas');
    // 'Unit Kernel Plant', bukan 'Presser': kolom header membawa
    // kernel_plant_id, label unit kernel plant yang menghasilkan bacaannya.
    expect($body)->toContain('Unit Kernel Plant');
    expect($body)->not->toContain('Presser');
});

it('case 61 — export memancarkan SATU BARIS PER SLOT WAKTU dengan kolom konteks DIULANG', function () {
    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-9');

    foreach (array_slice($this->slots, 0, 3) as $slot) {
        kernelPlantReportSlot($record, $slot, ['ripple_mill_1_amps' => 22.0]);
    }

    $this->actingAs($this->supervisorA);

    $rows = iterator_to_array($this->service->buildExportRows($this->periodA, null, $this->lineA));

    expect($rows)->toHaveCount(3);

    // Kolom konteks terulang VERBATIM pada ketiga baris — diulang, bukan
    // dikosongkan, supaya berkasnya langsung dapat dipivot di spreadsheet.
    foreach ($rows as $row) {
        expect($row)->toHaveCount(17);
        expect($row[0])->toBe('Periode Kernel Plant Alpha');
        expect($row[1])->toBe('Mill Alpha');
        expect($row[2])->not->toBe('');
        expect($row[3])->toBe($this->day2);
        expect($row[4])->toBe('KP-9');
    }

    // Slot selalu HH:MM.
    expect($rows[0][7])->toBe('07:00');
    expect($rows[1][7])->toBe('08:00');
    expect($rows[2][7])->toBe('09:00');

    // Slot yang kolom ukurnya kosong TETAP menjadi baris dengan sel KOSONG —
    // bukan dibuang, dan BUKAN ditulis 0. Membuangnya akan membuat berkasnya
    // berselisih dengan angka cakupan yang laporan yang sama terbitkan.
    $empty = kernelPlantReportRecord($this->stationA, $this->day3, 'KP-8');
    kernelPlantReportSlot($empty, '07:00');

    $withEmpty = iterator_to_array($this->service->buildExportRows($this->periodA, null, $this->lineA));

    expect($withEmpty)->toHaveCount(4);
    expect($withEmpty[3][4])->toBe('KP-8');
    // Ripple Mill 1 dan Temuan — ujung-ujung blok sembilan kolom bacaan.
    expect($withEmpty[3][8])->toBeNull();
    expect($withEmpty[3][16])->toBeNull();
});

it('case 62 — export melempar 422 untuk format di luar csv|excel', function () {
    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');
    kernelPlantReportSlot($record, '07:00', ['ripple_mill_1_amps' => 22.0]);

    $this->actingAs($this->supervisorA);

    expect(KernelPlantReportService::SUPPORTED_FORMATS)->toBe(['csv', 'excel']);

    expect(fn () => $this->service->export($this->periodA, 'pdf', null, $this->lineA))
        ->toThrow(ValidationException::class);
    // Service-nya KETAT: string kosong bukan format yang dikenal. Default
    // 'csv' hidup di controller (KernelPlantReportController::export()), bukan
    // di sini — jadi panggilan service langsung tidak dapat diam-diam
    // mengganti format.
    expect(fn () => $this->service->export($this->periodA, '', null, $this->lineA))
        ->toThrow(ValidationException::class);

    try {
        $this->service->export($this->periodA, 'pdf', null, $this->lineA);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('format');
    }

    expect($this->service->export($this->periodA, 'excel', null, $this->lineA))
        ->toBeInstanceOf(StreamedResponse::class);
});

it('case 63 — batas ekspor dihitung atas BARIS SLOT, bukan record, dan ditolak EAGER', function () {
    $record = kernelPlantReportRecord($this->stationA, $this->day2, 'KP-1');

    // SATU record, ENAM baris slot. Batas yang dihitung per RECORD akan
    // meloloskannya — satu record harian membawa sampai 24 baris, jadi batas
    // per record akan mewaveloloskan berkas 24x lebih besar dari yang
    // dimaksud.
    foreach (array_slice($this->slots, 0, 6) as $slot) {
        kernelPlantReportSlot($record, $slot, ['ripple_mill_1_amps' => 22.0]);
    }

    $this->actingAs($this->supervisorA);

    $tiny = new KernelPlantReportTinyExportService;

    // Penolakannya datang dari PEMANGGILAN ITU SENDIRI, sebelum satu byte pun
    // dialirkan: buildExportRows() bukan generator, ia MEMULANGKAN generator.
    expect(fn () => $tiny->buildExportRows($this->periodA, null, $this->lineA))
        ->toThrow(ExportFailedException::class);
    expect(fn () => $tiny->export($this->periodA, 'csv', null, $this->lineA))
        ->toThrow(ExportFailedException::class);

    // Dan batas yang PERSIS SAMA masih lolos: "strictly greater than".
    KernelPlantDetail::query()->where('time_slot', $this->slots[5])->delete();

    expect($tiny->buildExportRows($this->periodA, null, $this->lineA))->toBeInstanceOf(Generator::class);

    // ExportFailedException ADALAH 422 dengan kode EXPORT_FAILED — tech spec
    // menyebut ValidationException; yang diterbitkan adalah HttpException
    // berstatus 422 tanpa kunci `errors`, karena ini kondisi tunggal yang
    // tidak terikat satu field.
    $thrown = null;

    try {
        $tiny->buildExportRows($this->periodA, null, $this->lineA);
        kernelPlantReportSlot($record, $this->slots[5], ['ripple_mill_1_amps' => 22.0]);
        $tiny->buildExportRows($this->periodA, null, $this->lineA);
    } catch (ExportFailedException $e) {
        $thrown = $e;
    }

    expect($thrown)->not->toBeNull();
    expect($thrown->getStatusCode())->toBe(422);
    expect($thrown->errorCode())->toBe('EXPORT_FAILED');

    // Dan batas sungguhannya 50.000, dibaca lewat static:: sehingga subclass
    // di atas dapat menurunkannya alih-alih menyemai 50.000 baris.
    expect(KernelPlantReportService::EXPORT_ROW_LIMIT)->toBe(50000);
});

it('case 64 — guard export berjalan EAGER saat pemanggilan, bukan tertunda sampai pengaliran dimulai', function () {
    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::KernelPlant->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->create();

    $this->actingAs($this->supervisorA);

    // Metode yang DIJADIKAN generator akan menunda 403/422 sampai iterasi
    // pertama — yaitu setelah header terkirim — sehingga penolakan tiba
    // sebagai unduhan sukses yang kosong.
    expect(fn () => $this->service->buildExportRows($periodB, null, $this->lineA))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->export($periodB, 'csv', null, $this->lineA))
        ->toThrow(AuthorizationException::class);

    // Periode TERTUTUP tetap dapat diekspor: kunci periode mengatur PENULISAN
    // data, bukan pembacaan laporan.
    $closed = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelPlant->value)
        ->range(now()->subDays(40)->toDateString(), now()->subDays(31)->toDateString())
        ->closed()
        ->named('Periode Tertutup')
        ->create();

    $record = kernelPlantReportRecord($this->stationA, now()->subDays(35)->toDateString(), 'KP-7');
    kernelPlantReportSlot($record, '07:00', ['ripple_mill_1_amps' => 22.0]);

    $body = kernelPlantReportStreamed($this->service->export($closed, 'csv', null, $this->lineA));

    expect($body)->toContain('KP-7');
    expect($this->service->buildSummary($closed, null, $this->lineA)['period']['status'])->toBe('closed');
});

// =====================================================================
// SATU BARIS YANG MENENTUKAN LAYARNYA DAPAT DICAPAI
// =====================================================================

it('case 66 — REPORT_ROUTES memetakan kernel-plant, dan posisinya mengikuti sort_order', function () {
    // Tanpa entri 'kernel-plant' pada StationReportService::REPORT_ROUTES,
    // layar laporan ada, seluruh uji lainnya lolos, dan tile-nya tetap
    // kelabu — layarnya sekadar tidak dapat dicapai dari UI.
    expect(StationReportService::REPORT_ROUTES)
        ->toHaveKey(StationTypeEnum::KernelPlant->value, 'reports.kernel-plant');
    expect(route('reports.kernel-plant', [], false))->toBe('/reports/kernel-plant');

    // URUTANNYA LOAD-BEARING: peta ini harus tetap urut menurut
    // station_types.sort_order, karena layar pemilih stasiun membandingkan
    // urutannya dengan urutan master. Asersi ber-urutan, bukan sekadar
    // "memuat" — dan ia MEMANG dimaksudkan gagal bila peta berubah, supaya
    // daftarnya diperbarui alih-alih diam-diam menjadi selalu hijau.
    $codes = array_keys(StationReportService::REPORT_ROUTES);
    $at = array_search(StationTypeEnum::KernelPlant->value, $codes, true);

    expect($codes[$at - 1])->toBe(StationTypeEnum::Clarification->value);
    expect($codes[$at + 1])->toBe(StationTypeEnum::BoilerRoom->value);
});
