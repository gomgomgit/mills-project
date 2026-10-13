<?php

/**
 * EffluentPlantReportServiceTest — screen-156--laporan-effluent-plant-web,
 * EffluentPlantReportService.
 *
 * Satu uji per entri `unit_test_cases` pada tech spec screen-156 (65 kasus),
 * dikelompokkan sama seperti KernelPlantReportServiceTest supaya keduanya
 * dapat dibaca berdampingan. Tiga kasus terakhir (63-65) menyangkut komponen
 * Livewire, bukan service, dan dibuktikan di
 * tests/Feature/Livewire/LaporanEffluentPlantTest.php dengan label kasus yang
 * sama — berkas ini menutup kasus 1-62 plus satu kasus tambahan (66) untuk
 * SATU BARIS StationReportService::REPORT_ROUTES yang menentukan layarnya
 * dapat dicapai.
 *
 * ────────────────────────────────────────────────────────────────────────
 * FIXTURE-NYA SENGAJA TIDAK RATA, DAN ITU JUSTRU INTINYA
 * ────────────────────────────────────────────────────────────────────────
 * Enam aturan service ini hanya dapat dibuktikan oleh data yang MELAWAN
 * jalan pintasnya:
 *
 *   1. EMPAT BELAS METRIK, EMPAT BELAS PENYEBUT. Dengan keempat belas kolom
 *      terisi pada setiap slot, satu penyebut bersama memberi jawaban yang
 *      sama dan asersinya kosong. Karena itu kolom-kolomnya diisi pada
 *      HIMPUNAN SLOT YANG BERBEDA-BEDA dan diasersi per kolom.
 *
 *   2. KETIGA METRIK LABORATORIUM (BOD/COD/TSS buangan akhir) diisi jauh
 *      lebih jarang daripada sisanya — 2 slot lawan 24 — karena penyebut
 *      kecil pada ketiganya adalah keadaan NORMAL, dan satu penyebut bersama
 *      akan mengempiskan rata-ratanya menjadi angka yang tetap terlihat sah
 *      (2 nilai 300 dan 400 atas 100 baris menerbitkan 7, bukan 350).
 *
 *   3. BARIS TERISI = minimal satu dari SEMBILAN BELAS READING_FIELDS, bukan
 *      empat belas. Slot yang HANYA mengisi `findings` terhitung terisi dan
 *      tidak menambah penyebut satu metrik pun, jadi coverage.filled_slots
 *      DAPAT MELEBIHI penyebut setiap metrik. Yang diasersi adalah
 *      KETIDAKSAMAAN-nya; kesamaan justru bug yang hendak dicegah.
 *
 *   4. TIGA KOLOM STATUS KATEGORIS adalah SEBARAN, bukan rata-rata. Slot
 *      NULL masuk `not_recorded` pada kunci TERPISAH dan tidak pernah
 *      dilipat ke 'off'/'stop'; nilai dikenal yang tidak pernah muncul tetap
 *      hadir dengan 0. Keduanya ditulis sebagai kasus tersendiri.
 *
 *   5. DUA KOLOM TEKS BEBAS adalah DUA DAFTAR TERPISAH. Satu slot yang
 *      mengisi keduanya muncul di KEDUA daftar — itu benar, dan ada kasusnya
 *      sendiri.
 *
 *   6. TIDAK ADA PENANDAAN DI LUAR BATAS DAN TIDAK ADA BLOK DOWNTIME.
 *      Diasersi atas KETIADAAN KUNCI pada struktur payload dengan nilai yang
 *      disemai sangat ekstrem — bukan atas ketiadaan frasa, karena `_note`
 *      pada payload justru memuat kata "downtime" dan "di luar batas".
 *
 * Jangan "merapikan" angka-angka di bawah. Merapikannya mengubah separuh
 * berkas ini menjadi uji yang selalu hijau.
 *
 * TANGGAL FIXTURE SELALU RELATIF (now()->subDays(...)), tidak pernah tanggal
 * kalender yang dipaku: periodA di bawah harus SELALU sudah selesai supaya
 * days_counted === days_in_period, dan tanggal yang dipaku berhenti memenuhi
 * syarat itu begitu kalender melewatinya.
 *
 * Suite ini berjalan di SQLite in-memory sementara produksi PostgreSQL, yang
 * juga alasan penyaring periode diasersi di UJUNG-ujungnya: whereDate()
 * versus where() biasa hanya berbeda di sana. Tidak ada satu pun asersi di
 * berkas ini yang bersandar pada perilaku agregat SQL atas kolom bernull —
 * service-nya mengagregasi di PHP justru supaya perbedaan itu tidak ada, dan
 * kasus 57 yang membuktikannya.
 */

use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Exceptions\ExportFailedException;
use App\Models\BusinessUnit;
use App\Models\EffluentPlantDetail;
use App\Models\EffluentPlantRecord;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use App\Services\EffluentPlantRecordService;
use App\Services\EffluentPlantReportService;
use App\Services\StationReportService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Spy yang membuktikan allBusinessUnits() tidak pernah tercapai pada jalur
 * gagal-tertutup. Perangkat yang sama dengan
 * KernelPlantReportAllBusinessUnitsSpy.
 */
class EffluentPlantReportAllBusinessUnitsSpy extends EffluentPlantReportService
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
class EffluentPlantReportTinyExportService extends EffluentPlantReportService
{
    public const EXPORT_ROW_LIMIT = 5;
}

/** Satu record harian untuk satu unit Effluent Plant. */
function effluentPlantReportRecord(
    Station $station,
    string $date,
    string $effluentPlantId = 'EP-1',
    array $attributes = [],
): EffluentPlantRecord {
    return EffluentPlantRecord::factory()
        ->forStation($station)
        ->onDate($date)
        ->create(array_merge([
            'effluent_plant_id' => $effluentPlantId,
            'note' => 'Catatan harian',
        ], $attributes));
}

/** Satu baris slot waktu. KESEMBILAN BELAS kolom bacaan default-nya null. */
function effluentPlantReportSlot(
    EffluentPlantRecord $record,
    string $timeSlot,
    array $values = [],
): EffluentPlantDetail {
    return EffluentPlantDetail::factory()
        ->forRecord($record)
        ->timeSlot($timeSlot)
        ->create($values);
}

/** 24 slot kanonis, dalam urutan layar input (07:00..06:00). */
function effluentPlantReportSlots(): array
{
    return EffluentPlantRecordService::canonicalTimeSlots();
}

/** Setiap pernyataan SQL yang berjalan di dalam $callback. */
function effluentPlantReportQueriesDuring(callable $callback): array
{
    $queries = [];

    DB::listen(function ($query) use (&$queries) {
        $queries[] = strtolower($query->sql);
    });

    $callback();

    return $queries;
}

function effluentPlantReportStreamed(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

/**
 * Entri metrics[] untuk satu kolom.
 *
 * BERBEDA DARI KernelPlantReportService: di laporan ini `metrics` DIKUNCI
 * NAMA KOLOM, bukan daftar objek ber-`column`. Helper ini ada supaya
 * perbedaan itu dinyatakan sekali di satu tempat alih-alih tersirat di
 * enam puluh asersi.
 */
function effluentPlantReportMetric(array $summary, string $column): array
{
    expect($summary['metrics'])->toHaveKey($column);

    return $summary['metrics'][$column];
}

/**
 * Seluruh nama KUNCI yang muncul di mana pun di dalam struktur — dipakai
 * kasus 52, yang harus menyisir KUNCI alih-alih mencari frasa.
 *
 * @return list<string>
 */
function effluentPlantReportAllKeys(mixed $value): array
{
    if (! is_array($value)) {
        return [];
    }

    $keys = [];

    foreach ($value as $key => $child) {
        if (is_string($key)) {
            $keys[] = $key;
        }

        $keys = array_merge($keys, effluentPlantReportAllKeys($child));
    }

    return array_values(array_unique($keys));
}

beforeEach(function () {
    $this->service = new EffluentPlantReportService;

    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->effluentPlant()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->effluentPlant()->create();

    $this->lineA = (string) $this->stationA->production_line_id;
    $this->lineB = (string) $this->stationB->production_line_id;

    $this->supervisorA = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->supervisorB = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitB)->create();
    $this->millManagementA = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operatorA = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    // Periode yang SUDAH SELESAI, sehingga days_counted === days_in_period
    // dan penyebut cakupannya deterministik. Periode berjalan, periode belum
    // mulai, dan periode berdurasi 9 hari punya kasusnya masing-masing.
    //
    // RELATIF, bukan tanggal kalender: tanggal yang dipaku berhenti "sudah
    // selesai" begitu kalender melewatinya, dan asersi penyebut di bawah akan
    // mulai gagal karena alasan yang tidak ada hubungannya dengan kode.
    $this->periodStart = now()->subDays(20)->toDateString();
    $this->periodEnd = now()->subDays(11)->toDateString();

    // Empat tanggal di dalam periode, plus satu hari tepat di tiap sisinya.
    $this->day1 = now()->subDays(19)->toDateString();
    $this->day2 = now()->subDays(18)->toDateString();
    $this->day3 = now()->subDays(17)->toDateString();
    $this->day4 = now()->subDays(16)->toDateString();
    $this->dayBeforePeriod = now()->subDays(21)->toDateString();
    $this->dayAfterPeriod = now()->subDays(10)->toDateString();

    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::EffluentPlant->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Effluent Plant Alpha')
        ->create();

    $this->slots = effluentPlantReportSlots();
});

// =====================================================================
// GROUP A — AKSES: OPERATOR DITOLAK PADA KEEMPAT JALUR (case 1-5, 10)
//
// INILAH yang membedakan laporan ini dari Grading / Kernel Plant, yang
// menerima Operator pada ketiga rute datanya sejak commit pertama. Kembaran
// mobile screen-157 belum dibangun, jadi tidak ada pemanggil Operator yang
// sah hari ini — dan menerima sebuah peran sebelum pemanggilnya ada adalah
// akses yang tidak dapat ditinjau siapa pun.
// =====================================================================

it('case 1 — 403 ketika Operator meminta daftar opsi mill, dan master mill tidak pernah dibaca', function () {
    $this->actingAs($this->operatorA);

    $spy = new EffluentPlantReportAllBusinessUnitsSpy;

    $queries = effluentPlantReportQueriesDuring(function () use ($spy) {
        expect(fn () => $spy->businessUnitOptions())->toThrow(AuthorizationException::class);
    });

    expect($spy->allBusinessUnitsCalls)->toBe(0);

    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "business_units"');
    }
});

it('case 2 — 403 ketika Operator meminta daftar periode', function () {
    $this->actingAs($this->operatorA);

    // Penolakan ini DISENGAJA dan sementara: ketika screen-157 dibangun,
    // pelebarannya WAJIB menyentuh guardAccess() DAN cabang terikat-mill di
    // resolveBusinessUnit() sekaligus. Melebarkan yang pertama saja
    // menjatuhkan Operator ke cabang Admin tempat business_unit_id kiriman
    // klien DIHORMATI — kebocoran lintas mill, bukan cacat tampilan.
    expect(fn () => $this->service->listPeriods(null))->toThrow(AuthorizationException::class);
});

it('case 3 — 403 ketika Operator meminta ringkasan, sebelum resolusi mill/line dijalankan', function () {
    $this->actingAs($this->operatorA);

    $queries = effluentPlantReportQueriesDuring(function () {
        expect(fn () => $this->service->buildSummary((string) $this->periodA->id, null, $this->lineA))
            ->toThrow(AuthorizationException::class);
    });

    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "effluent_plant_records"');
        expect($sql)->not->toContain('from "production_lines"');
        expect($sql)->not->toContain('from "periods"');
    }
});

it('case 4 — 403 ketika Operator meminta ekspor, dan pengaliran tidak pernah dimulai', function () {
    $record = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-1');
    effluentPlantReportSlot($record, '07:00', ['anaerobic_pond_1_ph' => 7.2]);

    $this->actingAs($this->operatorA);

    expect(fn () => $this->service->export($this->periodA, 'csv', null, $this->lineA))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->buildExportRows($this->periodA, null, $this->lineA))
        ->toThrow(AuthorizationException::class);
});

it('case 5 — 401 ketika permintaan ringkasan datang tanpa autentikasi, sebelum satu kueri pun', function () {
    $queries = effluentPlantReportQueriesDuring(function () {
        expect(fn () => $this->service->buildSummary((string) $this->periodA->id, null, $this->lineA))
            ->toThrow(AuthenticationException::class);
        expect(fn () => $this->service->listPeriods(null))->toThrow(AuthenticationException::class);
        expect(fn () => $this->service->businessUnitOptions())->toThrow(AuthenticationException::class);
    });

    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "effluent_plant_records"');
        expect($sql)->not->toContain('from "periods"');
    }
});

it('case 10 — guard peran dievaluasi SEBELUM resolusi mill: 403, bukan 422', function () {
    $operatorWithoutMill = User::factory()->role(UserRole::Operator)->create(['business_unit_id' => null]);

    $this->actingAs($operatorWithoutMill);

    // Peran yang tidak diterima sama sekali tidak boleh belajar apa pun
    // tentang keadaan akunnya — termasuk bahwa akunnya belum terhubung ke
    // mill. 403 lebih dulu, 422 tidak pernah tercapai.
    expect(fn () => $this->service->resolveBusinessUnit(null))->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->listPeriods(null))->toThrow(AuthorizationException::class);
});

// =====================================================================
// GROUP B — PEMILIH MILL: ADMIN SAJA (case 6-9)
// =====================================================================

it('case 6 — 403 ketika Supervisor meminta daftar opsi mill, DARI DALAM service', function () {
    $this->actingAs($this->supervisorA);

    $spy = new EffluentPlantReportAllBusinessUnitsSpy;

    // Penjagaannya ada di SERVICE, bukan di middleware rute: ketika
    // screen-157 melebarkan daftar peran rute, endpoint ini harus tetap
    // tertutup. Uji ini gagal bila guard-nya dipindah ke rute.
    expect(fn () => $spy->businessUnitOptions())->toThrow(AuthorizationException::class);
    expect($spy->allBusinessUnitsCalls)->toBe(0);
});

it('case 7 — 403 ketika Mill Management meminta daftar opsi mill', function () {
    $this->actingAs($this->millManagementA);

    $spy = new EffluentPlantReportAllBusinessUnitsSpy;

    expect(fn () => $spy->businessUnitOptions())->toThrow(AuthorizationException::class);
    expect($spy->allBusinessUnitsCalls)->toBe(0);
});

it('case 8 — 200 dengan daftar mill terurut nama ketika Admin meminta opsi mill', function () {
    BusinessUnit::factory()->create(['name' => 'Mill Gamma']);

    $this->actingAs($this->admin);

    $options = $this->service->businessUnitOptions();

    expect($options[0])->toHaveKeys(['id', 'name']);

    // Mill yang terbentuk dari factory lain ikut terdaftar, jadi yang
    // diasersi adalah KEHADIRAN ketiga mill uji dan URUTANNYA relatif satu
    // sama lain — bukan panjang mutlak daftarnya.
    $names = collect($options)->pluck('name')->all();

    expect($names)->toContain('Mill Alpha', 'Mill Beta', 'Mill Gamma');

    $uji = collect($names)->filter(fn (string $name) => str_starts_with($name, 'Mill '))->values()->all();

    expect($uji)->toBe(['Mill Alpha', 'Mill Beta', 'Mill Gamma']);
});

it('case 9 — 200 dengan data kosong ketika master mill belum berisi apa pun, bukan 404', function () {
    $this->actingAs($this->admin);

    // Mengosongkan master secara utuh: FK dimatikan sesaat karena yang diuji
    // adalah jawaban service atas master kosong, bukan tata urut penghapusan.
    Schema::withoutForeignKeyConstraints(function () {
        EffluentPlantDetail::query()->delete();
        EffluentPlantRecord::query()->delete();
        PeriodStation::query()->delete();
        Period::query()->delete();
        Station::query()->delete();
        ProductionLine::query()->delete();
        User::query()->whereNotNull('business_unit_id')->delete();
        BusinessUnit::query()->delete();
    });

    expect($this->service->businessUnitOptions())->toBe([]);
});

// =====================================================================
// GROUP C — RESOLUSI MILL (case 11-14)
// =====================================================================

it('case 11 — 422 ketika Admin tidak mengirim business_unit_id pada daftar periode, tanpa kueri periode', function () {
    $this->actingAs($this->admin);

    $queries = effluentPlantReportQueriesDuring(function () {
        expect(fn () => $this->service->listPeriods(null))->toThrow(ValidationException::class);
    });

    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "periods"');
    }

    try {
        $this->service->listPeriods(null);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('business_unit_id');
        expect($e->errors()['business_unit_id'][0])->toContain('Pilih mill');
    }
});

it('case 12 — business_unit_id kiriman Supervisor DIBUANG dan mill akun yang dipakai', function () {
    $this->actingAs($this->supervisorA);

    // Tidak divalidasi, tidak dibandingkan, DIBUANG.
    expect($this->service->resolveBusinessUnit((string) $this->businessUnitB->id))
        ->toBe((string) $this->businessUnitA->id);

    $recordA = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-A');
    effluentPlantReportSlot($recordA, '07:00', ['anaerobic_pond_1_ph' => 7.0]);

    $recordB = effluentPlantReportRecord($this->stationB, $this->day2, 'EP-B');
    effluentPlantReportSlot($recordB, '07:00', ['anaerobic_pond_1_ph' => 99.0]);

    $summary = $this->service->buildSummary($this->periodA, (string) $this->businessUnitB->id, $this->lineA);

    expect($summary['business_unit']['id'])->toBe((string) $this->businessUnitA->id);
    expect($summary['business_unit']['name'])->toBe('Mill Alpha');
    // 99.0 milik mill lain tidak boleh terbaca sama sekali.
    expect(effluentPlantReportMetric($summary, 'anaerobic_pond_1_ph')['max'])->toBe(7.0);
});

it('case 13 — business_unit_id kiriman peran terikat mill tidak divalidasi sama sekali', function () {
    $this->actingAs($this->millManagementA);

    $tidakAda = (string) Str::uuid();

    // BUKAN 422 dan BUKAN 403: nilainya dibuang tanpa dibandingkan maupun
    // divalidasi. 403 justru akan menegaskan bahwa mill lain itu ada.
    expect($this->service->resolveBusinessUnit($tidakAda))->toBe((string) $this->businessUnitA->id);

    $summary = $this->service->buildSummary($this->periodA, $tidakAda, $this->lineA);

    expect($summary['business_unit']['name'])->toBe('Mill Alpha');
    expect($summary['period']['id'])->toBe((string) $this->periodA->id);
});

it('case 14 — akun terikat mill dengan business_unit_id NULL gagal tertutup, tanpa membaca daftar mill', function () {
    $boundWithoutMill = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $this->actingAs($boundWithoutMill);

    $spy = new EffluentPlantReportAllBusinessUnitsSpy;

    // PENOLAKANNYA 422 VALIDATION_ERROR, bukan 403 FORBIDDEN seperti ditulis
    // tech spec: resolveBusinessUnit() melempar ValidationException dengan
    // pesan 'Akun Anda belum terhubung ke mill. Hubungi Admin.' Yang
    // load-bearing bukan angka statusnya melainkan GAGAL TERTUTUP tanpa
    // menyentuh daftar seluruh mill.
    $queries = effluentPlantReportQueriesDuring(function () use ($spy) {
        expect(fn () => $spy->buildSummary((string) $this->periodA->id, null, $this->lineA))
            ->toThrow(ValidationException::class);
    });

    expect($spy->allBusinessUnitsCalls)->toBe(0);

    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "business_units"');
        expect($sql)->not->toContain('from "effluent_plant_records"');
    }

    try {
        $spy->resolveBusinessUnit(null);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('business_unit_id');
        expect($e->errors()['business_unit_id'][0])->toContain('belum terhubung ke mill');
    }
});

// =====================================================================
// GROUP D — PRODUCTION LINE DAN PERIODE (case 15-24)
// =====================================================================

it('case 15 — production_line_id tidak pernah dipakai pada kueri daftar periode', function () {
    $this->actingAs($this->supervisorA);

    // PERIODE MILIK MILL, BUKAN MILIK LINE. Bentuk tanda tangannya yang
    // membuktikannya: listPeriods() tidak punya parameter line sama sekali,
    // jadi ia tidak mungkin menyaringnya.
    $reflection = new ReflectionMethod(EffluentPlantReportService::class, 'listPeriods');

    expect($reflection->getNumberOfParameters())->toBe(1);
    expect($reflection->getParameters()[0]->getName())->toBe('businessUnitId');

    $queries = effluentPlantReportQueriesDuring(function () {
        $this->service->listPeriods(null);
    });

    $periodQueries = array_values(array_filter(
        $queries,
        fn (string $sql) => str_contains($sql, 'from "periods"'),
    ));

    expect($periodQueries)->not->toBeEmpty();

    foreach ($periodQueries as $sql) {
        expect($sql)->not->toContain('production_line');
        expect($sql)->toContain('business_unit_id');
        expect($sql)->toContain('station_type');
    }
});

it('case 16 — 422 ketika production_line_id absen pada ringkasan', function () {
    $this->actingAs($this->supervisorA);

    expect(fn () => $this->service->buildSummary($this->periodA, null, null))
        ->toThrow(ValidationException::class);

    try {
        $this->service->buildSummary($this->periodA, null, null);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('production_line_id');
    }
});

it('case 17 — nol kueri effluent_plant_records dan tanpa default "semua line" ketika production_line_id absen', function () {
    $record = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-1');
    effluentPlantReportSlot($record, '07:00', ['anaerobic_pond_1_ph' => 7.0]);

    $this->actingAs($this->supervisorA);

    $queries = effluentPlantReportQueriesDuring(function () {
        expect(fn () => $this->service->buildSummary($this->periodA, null, null))
            ->toThrow(ValidationException::class);
        expect(fn () => $this->service->buildSummary($this->periodA, null, ''))
            ->toThrow(ValidationException::class);
    });

    // Diam-diam melebarkan cakupan laporan karena satu parameter terlupa
    // adalah yang terburuk dari dua dunia: ia menjawab dengan angka yang
    // tidak diminta siapa pun.
    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "effluent_plant_records"');
    }
});

it('case 18 — 403 ketika production_line_id milik mill lain, tanpa menghitung satu angka line itu', function () {
    $recordB = effluentPlantReportRecord($this->stationB, $this->day2, 'EP-B');
    effluentPlantReportSlot($recordB, '07:00', ['anaerobic_pond_1_ph' => 99.0]);

    $this->actingAs($this->supervisorA);

    $queries = effluentPlantReportQueriesDuring(function () {
        expect(fn () => $this->service->buildSummary($this->periodA, null, $this->lineB))
            ->toThrow(AuthorizationException::class);
    });

    foreach ($queries as $sql) {
        expect($sql)->not->toContain('from "effluent_plant_records"');
    }

    expect(fn () => $this->service->resolveProductionLine((string) $this->businessUnitA->id, $this->lineB))
        ->toThrow(AuthorizationException::class);
});

it('case 19 — 422 ketika period_id absen pada ringkasan', function () {
    $this->actingAs($this->supervisorA);

    expect(fn () => $this->service->buildSummary(null, null, $this->lineA))
        ->toThrow(ValidationException::class);

    try {
        $this->service->buildSummary('', null, $this->lineA);
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('period_id');
    }
});

it('case 20 — 404 ketika period_id tidak ada', function () {
    $this->actingAs($this->supervisorA);

    expect(fn () => $this->service->buildSummary((string) Str::uuid(), null, $this->lineA))
        ->toThrow(ModelNotFoundException::class);
    expect(fn () => $this->service->authorizePeriod((string) Str::uuid()))
        ->toThrow(ModelNotFoundException::class);
});

it('case 21 — 403 ketika periode milik mill lain, sehingga id periode mana yang ada tidak pernah dipelajari', function () {
    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::EffluentPlant->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Effluent Plant Beta')
        ->create();

    $this->actingAs($this->supervisorA);

    expect(fn () => $this->service->buildSummary((string) $periodB->id, null, $this->lineA))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->authorizePeriod((string) $periodB->id))
        ->toThrow(AuthorizationException::class);

    // Admin lolos untuk mill mana pun — satu-satunya peran yang tidak
    // terikat.
    $this->actingAs($this->admin);

    expect($this->service->authorizePeriod((string) $periodB->id)->id)->toBe($periodB->id);
});

it('case 22 — guard LINE dievaluasi SEBELUM guard periode', function () {
    $this->actingAs($this->supervisorA);

    // Keduanya absen: yang disebut galatnya HARUS production_line_id, karena
    // permintaan yang tidak lengkap tidak boleh berubah menjadi 404/403
    // tentang periode yang pemanggilnya tidak pernah berhak menanyakannya.
    try {
        $this->service->buildSummary(null, null, null);
        $this->fail('seharusnya melempar ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('production_line_id');
        expect($e->errors())->not->toHaveKey('period_id');
    }

    // Dan line mill lain bersama period_id yang tidak ada: 403 line, bukan
    // 404 periode.
    expect(fn () => $this->service->buildSummary((string) Str::uuid(), null, $this->lineB))
        ->toThrow(AuthorizationException::class);
});

it('case 23 — periode berstatus tertutup tetap menghasilkan ringkasan penuh dan tetap dapat diekspor', function () {
    $closed = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::EffluentPlant->value)
        ->range(now()->subDays(40)->toDateString(), now()->subDays(31)->toDateString())
        ->closed()
        ->named('Periode Tertutup')
        ->create();

    $record = effluentPlantReportRecord($this->stationA, now()->subDays(35)->toDateString(), 'EP-7');
    effluentPlantReportSlot($record, '07:00', ['anaerobic_pond_1_ph' => 7.1]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($closed, null, $this->lineA);

    // Kunci periode mengatur PENULISAN data, bukan pembacaan laporan.
    expect($summary['period']['status'])->toBe('closed');
    expect($summary['has_data'])->toBeTrue();
    expect($summary['metrics'])->toHaveCount(14);

    $body = effluentPlantReportStreamed($this->service->export($closed, 'csv', null, $this->lineA));

    expect($body)->toContain('EP-7');
});

it('case 24 — daftar periode hanya memuat periode yang mencakup Effluent Plant', function () {
    // Periode yang berlaku untuk SELURUH jenis stasiun: sejak 2026-09-25
    // cakupan semua-stasiun dinyatakan lewat ADANYA satu baris
    // period_stations per jenis, jadi periode ini PUNYA baris effluent-plant
    // dan memang harus ikut terdaftar.
    $semuaJenis = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(null)
        ->range(now()->subDays(60)->toDateString(), now()->subDays(51)->toDateString())
        ->open()
        ->named('Periode Semua Jenis')
        ->create();

    $kernelSaja = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelPlant->value)
        ->range(now()->subDays(80)->toDateString(), now()->subDays(71)->toDateString())
        ->open()
        ->named('Periode Kernel Plant Saja')
        ->create();

    $this->actingAs($this->supervisorA);

    $ids = collect($this->service->listPeriods(null))->pluck('id')->all();

    expect($ids)->toContain((string) $this->periodA->id);
    expect($ids)->toContain((string) $semuaJenis->id);
    expect($ids)->not->toContain((string) $kernelSaja->id);
    expect($ids)->toHaveCount(2);

    // Dan station_type-nya BERTANDA HUBUNG: kunci snake_case akan
    // ter-compile, tampak benar, dan tidak pernah cocok dengan satu baris
    // master pun — daftar periodenya lalu kosong untuk setiap mill tanpa
    // satu galat.
    foreach ($this->service->listPeriods(null) as $option) {
        expect($option['station_type'])->toBe('effluent-plant');
        expect($option['station_type_label'])->not->toBe('');
        expect($option)->toHaveKeys(['id', 'name', 'start_date', 'end_date', 'status', 'station_type', 'station_type_label']);
    }
});

// =====================================================================
// GROUP E — KEANGGOTAAN PERIODE DAN ISOLASI LINE (case 25-28)
// =====================================================================

it('case 25 — rentang periode inklusif di kedua ujung', function () {
    $first = effluentPlantReportRecord($this->stationA, $this->periodStart, 'EP-1');
    $last = effluentPlantReportRecord($this->stationA, $this->periodEnd, 'EP-1');

    effluentPlantReportSlot($first, '07:00', ['anaerobic_pond_1_ph' => 6.0]);
    effluentPlantReportSlot($last, '07:00', ['anaerobic_pond_1_ph' => 8.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['coverage']['filled_slots'])->toBe(2);
    expect(effluentPlantReportMetric($summary, 'anaerobic_pond_1_ph')['min'])->toBe(6.0);
    expect(effluentPlantReportMetric($summary, 'anaerobic_pond_1_ph')['max'])->toBe(8.0);
    expect($summary['total']['days_with_records'])->toBe(2);
});

it('case 26 — record di luar rentang periode dikecualikan di KEDUA sisi', function () {
    $before = effluentPlantReportRecord($this->stationA, $this->dayBeforePeriod, 'EP-1');
    $inside = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-1');
    $after = effluentPlantReportRecord($this->stationA, $this->dayAfterPeriod, 'EP-1');

    effluentPlantReportSlot($before, '07:00', [
        'anaerobic_pond_1_ph' => 1.0,
        'biogas_flare_status' => 'fault',
        'findings' => 'sebelum periode',
    ]);
    effluentPlantReportSlot($inside, '07:00', ['anaerobic_pond_1_ph' => 7.0]);
    effluentPlantReportSlot($after, '07:00', [
        'anaerobic_pond_1_ph' => 999.0,
        'biogas_flare_status' => 'fault',
        'findings' => 'sesudah periode',
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['coverage']['filled_slots'])->toBe(1);
    expect(effluentPlantReportMetric($summary, 'anaerobic_pond_1_ph')['reading_count'])->toBe(1);
    expect(effluentPlantReportMetric($summary, 'anaerobic_pond_1_ph')['avg'])->toBe(7.0);
    expect($summary['status_distribution']['biogas_flare_status']['counts']['fault'])->toBe(0);
    expect($summary['findings'])->toBe([]);
    expect($summary['daily'])->toHaveCount(1);
});

it('case 27 — penyaringan line memakai kolom production_line_id pada record itu sendiri, bukan join ke stations', function () {
    // SATU stasiun. Satu record dihasilkan saat stasiun itu masih di line A,
    // lalu stasiunnya DIPINDAH ke line lain. Kolom record tetap menunjuk line
    // tempat data itu benar-benar dihasilkan — justru itu yang benar.
    $otherLine = ProductionLine::factory()->create(['business_unit_id' => $this->businessUnitA->id]);

    $producedOnLineA = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-1', [
        'production_line_id' => $this->lineA,
    ]);
    effluentPlantReportSlot($producedOnLineA, '07:00', ['anaerobic_pond_1_ph' => 7.0]);

    $producedOnOtherLine = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-2', [
        'production_line_id' => $otherLine->id,
    ]);
    effluentPlantReportSlot($producedOnOtherLine, '07:00', ['anaerobic_pond_1_ph' => 99.0]);

    // Stasiunnya KINI terdaftar di line lain. Join ke stations akan membaca
    // keadaan SEKARANG dan menulis ulang sejarah pembacaan yang sudah
    // dicatat.
    $this->stationA->forceFill(['production_line_id' => $otherLine->id])->save();

    $this->actingAs($this->supervisorA);

    $queries = effluentPlantReportQueriesDuring(function () {
        $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

        expect($summary['coverage']['filled_slots'])->toBe(1);
        // Join ke stations akan menarik KEDUA record dan menjawab 53,0 —
        // rata-rata dua line sekaligus.
        expect(effluentPlantReportMetric($summary, 'anaerobic_pond_1_ph')['avg'])->toBe(7.0);
        expect(effluentPlantReportMetric($summary, 'anaerobic_pond_1_ph')['max'])->toBe(7.0);
    });

    $recordQueries = array_values(array_filter(
        $queries,
        fn (string $sql) => str_contains($sql, 'from "effluent_plant_records"'),
    ));

    expect($recordQueries)->not->toBeEmpty();

    foreach ($recordQueries as $sql) {
        expect($sql)->toContain('"effluent_plant_records"."production_line_id"');
    }
});

it('case 28 — record line lain pada mill yang SAMA dikecualikan dari setiap angka', function () {
    $line4 = ProductionLine::factory()->create(['business_unit_id' => $this->businessUnitA->id]);
    $station4 = Station::factory()->forProductionLine($line4)->effluentPlant()->create();

    $onLineA = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-1');
    effluentPlantReportSlot($onLineA, '07:00', ['anaerobic_pond_1_ph' => 7.0]);

    $onLine4 = effluentPlantReportRecord($station4, $this->day2, 'EP-4');
    effluentPlantReportSlot($onLine4, '07:00', [
        'anaerobic_pond_1_ph' => 99.0,
        'biogas_flare_status' => 'fault',
        'remarks_maintenance_actions' => 'milik line lain',
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['coverage']['filled_slots'])->toBe(1);
    expect($summary['coverage']['effluent_plant_unit_count'])->toBe(1);
    expect(effluentPlantReportMetric($summary, 'anaerobic_pond_1_ph')['max'])->toBe(7.0);
    expect($summary['status_distribution']['biogas_flare_status']['counts']['fault'])->toBe(0);
    expect($summary['maintenance_remarks'])->toBe([]);
    expect(collect($summary['by_unit'])->pluck('effluent_plant_id')->all())->toBe(['EP-1']);
});

// =====================================================================
// GROUP F — DEFINISI "TERISI" DIPINJAM DARI LAYAR INPUT (case 29-30)
// =====================================================================

it('case 29 — baris terisi bila minimal satu dari SEMBILAN BELAS READING_FIELDS non-null', function () {
    $record = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-1');

    effluentPlantReportSlot($record, '07:00', ['anaerobic_pond_1_ph' => 7.0]);
    // Slot yang HANYA mengisi temuan: terhitung TERISI, dan tidak menambah
    // penyebut satu metrik pun. 19 kolom, bukan 14.
    effluentPlantReportSlot($record, '08:00', ['findings' => 'Busa di kolam']);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['coverage']['filled_slots'])->toBe(2);
    expect($summary['has_data'])->toBeTrue();

    // DAN INILAH KETIDAKSAMAAN YANG DICARI: jumlah slot terisi MELEBIHI
    // penyebut setiap metrik. Kesamaan justru bug yang hendak dicegah.
    foreach (EffluentPlantReportService::NUMERIC_METRICS as $metric) {
        expect(effluentPlantReportMetric($summary, $metric)['reading_count'])
            ->toBeLessThan($summary['coverage']['filled_slots']);
    }

    // Definisinya DIPINJAM dari layar input, tidak diturunkan ulang: 19
    // kolom, dan keduanya sengaja dijadikan publik untuk itu.
    expect(EffluentPlantRecordService::READING_FIELDS)->toHaveCount(19);
    expect(app(EffluentPlantRecordService::class)->isRowFilled(['findings' => 'ada']))->toBeTrue();
    expect(app(EffluentPlantRecordService::class)->isRowFilled(['biogas_flare_status' => 'on']))->toBeTrue();
    expect(app(EffluentPlantRecordService::class)->isRowFilled([]))->toBeFalse();
});

it('case 30 — baris dengan seluruh 19 kolom null tidak menambah filled_slots', function () {
    $record = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-1');

    foreach (array_slice($this->slots, 0, 3) as $slot) {
        effluentPlantReportSlot($record, $slot);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // Slot tak tersentuh sudah dilaporkan oleh cakupan; menghitungnya lagi
    // akan melaporkan kekosongan yang sama dua kali.
    expect($summary['coverage']['filled_slots'])->toBe(0);
    expect($summary['has_data'])->toBeFalse();
    // Harinya TETAP hari ber-record — ia tetap hari yang dicatat orang.
    expect($summary['total']['days_with_records'])->toBe(1);
    expect($summary['daily'])->toBe([]);
});

// =====================================================================
// GROUP G — EMPAT BELAS METRIK, EMPAT BELAS PENYEBUT (case 31-36)
// =====================================================================

it('case 31 — masing-masing dari 14 metrik menerbitkan reading_count SENDIRI', function () {
    $record = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-1');

    foreach (range(0, 9) as $index) {
        $values = ['anaerobic_pond_1_ph' => 7.0 + $index / 10];

        if ($index < 2) {
            $values['final_discharge_bod_mgl_lab'] = 120.0 + $index;
        }

        effluentPlantReportSlot($record, $this->slots[$index], $values);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['metrics'])->toHaveCount(14);
    expect(array_keys($summary['metrics']))->toBe(EffluentPlantReportService::NUMERIC_METRICS);

    expect(effluentPlantReportMetric($summary, 'anaerobic_pond_1_ph')['reading_count'])->toBe(10);
    expect(effluentPlantReportMetric($summary, 'final_discharge_bod_mgl_lab')['reading_count'])->toBe(2);

    // KEEMPAT BELAS punya kunci reading_count-nya masing-masing, dan tidak
    // ada satu pun penyebut bersama di mana pun pada payload.
    foreach (EffluentPlantReportService::NUMERIC_METRICS as $metric) {
        expect($summary['metrics'][$metric])
            ->toHaveKeys(['min', 'avg', 'max', 'reading_count', 'is_lab']);
    }

    expect($summary)->not->toHaveKey('reading_count');
    expect($summary['coverage'])->not->toHaveKey('reading_count');
});

it('case 32 — rata-rata metrik TIDAK memakai penyebut bersama seluruh baris', function () {
    // 100 baris slot pada lima unit; hanya DUA yang mengisi COD.
    foreach (range(1, 5) as $unit) {
        $record = effluentPlantReportRecord($this->stationA, $this->day2, "EP-{$unit}");

        foreach (array_slice($this->slots, 0, 20) as $index => $slot) {
            $values = ['anaerobic_pond_1_ph' => 7.0];

            if ($unit === 1 && $index === 0) {
                $values['final_discharge_cod_mgl_lab'] = 300.0;
            }

            if ($unit === 1 && $index === 1) {
                $values['final_discharge_cod_mgl_lab'] = 400.0;
            }

            effluentPlantReportSlot($record, $slot, $values);
        }
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['coverage']['filled_slots'])->toBe(100);

    $cod = effluentPlantReportMetric($summary, 'final_discharge_cod_mgl_lab');

    // 350 (dibagi 2), BUKAN 7 (dibagi 100). Angka 7 akan tetap terlihat
    // seperti angka yang sah — itulah sebabnya kasus ini ada.
    expect($cod['reading_count'])->toBe(2);
    expect($cod['avg'])->toBe(350.0);
    expect($cod['min'])->toBe(300.0);
    expect($cod['max'])->toBe(400.0);
    expect(effluentPlantReportMetric($summary, 'anaerobic_pond_1_ph')['reading_count'])->toBe(100);
});

it('case 33 — metrik tanpa satu pun nilai menghasilkan min/avg/max null, BUKAN 0', function () {
    $record = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-1');
    effluentPlantReportSlot($record, '07:00', ['anaerobic_pond_1_ph' => 7.0]);

    $this->actingAs($this->supervisorA);

    $chemical = effluentPlantReportMetric(
        $this->service->buildSummary($this->periodA, null, $this->lineA),
        'chemical_consumed_kgl',
    );

    // 0 akan terbaca sebagai "kami mengukurnya dan hasilnya nol".
    expect($chemical['min'])->toBeNull();
    expect($chemical['avg'])->toBeNull();
    expect($chemical['max'])->toBeNull();
    expect($chemical['reading_count'])->toBe(0);
});

it('case 34 — metrik dengan satu nilai menghasilkan min = avg = max', function () {
    $record = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-1');
    effluentPlantReportSlot($record, '07:00', ['biogas_flow_rate_m3h' => 125.5]);

    $this->actingAs($this->supervisorA);

    $biogas = effluentPlantReportMetric(
        $this->service->buildSummary($this->periodA, null, $this->lineA),
        'biogas_flow_rate_m3h',
    );

    expect($biogas['min'])->toBe(125.5);
    expect($biogas['avg'])->toBe(125.5);
    expect($biogas['max'])->toBe(125.5);
    expect($biogas['reading_count'])->toBe(1);
});

it('case 35 — tiga metrik laboratorium ditandai is_lab true, sebelas lainnya false', function () {
    $record = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-1');
    effluentPlantReportSlot($record, '07:00', ['anaerobic_pond_1_ph' => 7.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect(EffluentPlantReportService::LAB_METRICS)->toBe([
        'final_discharge_bod_mgl_lab',
        'final_discharge_cod_mgl_lab',
        'final_discharge_tss_mgl_lab',
    ]);

    foreach (EffluentPlantReportService::NUMERIC_METRICS as $metric) {
        $isLab = in_array($metric, EffluentPlantReportService::LAB_METRICS, true);

        expect($summary['metrics'][$metric]['is_lab'])->toBe($isLab, "is_lab {$metric}");
    }

    $penandaLab = collect($summary['metrics'])->filter(fn (array $m) => $m['is_lab'])->count();

    // Penandanya ada supaya layar dapat mengatakan di tempat angkanya bahwa
    // penyebut kecil itu NORMAL — dan supaya tidak ada pembaca kode yang
    // "memperbaiki" penyebutnya menjadi penyebut bersama.
    expect($penandaLab)->toBe(3);
});

it('case 36 — metrik laboratorium kosong tidak mengganggu sebelas metrik lain', function () {
    // Bacaan per jam LENGKAP: 10 hari x 24 slot = 240 baris, sebelas kolom
    // non-lab terisi seluruhnya, BOD/COD/TSS null seluruhnya.
    $nonLab = array_values(array_diff(
        EffluentPlantReportService::NUMERIC_METRICS,
        EffluentPlantReportService::LAB_METRICS,
    ));

    foreach (range(0, 9) as $offset) {
        $date = now()->subDays(20 - $offset)->toDateString();
        $record = effluentPlantReportRecord($this->stationA, $date, 'EP-1');

        foreach ($this->slots as $slot) {
            effluentPlantReportSlot($record, $slot, array_fill_keys($nonLab, 7.0));
        }
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['coverage']['filled_slots'])->toBe(240);

    foreach (EffluentPlantReportService::LAB_METRICS as $metric) {
        expect($summary['metrics'][$metric]['reading_count'])->toBe(0, $metric);
        expect($summary['metrics'][$metric]['avg'])->toBeNull($metric);
    }

    foreach ($nonLab as $metric) {
        expect($summary['metrics'][$metric]['reading_count'])->toBe(240, $metric);
        expect($summary['metrics'][$metric]['avg'])->toBe(7.0, $metric);
    }
});

// =====================================================================
// GROUP H — TIGA SEBARAN STATUS KATEGORIS (case 37-41)
// =====================================================================

it('case 37 — sebaran biogas_flare_status menghitung slot per nilai yang benar-benar ada', function () {
    $record = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-1');

    $urutan = ['on', 'on', 'on', 'on', 'on', 'off', 'off', 'fault'];

    foreach ($urutan as $index => $value) {
        effluentPlantReportSlot($record, $this->slots[$index], ['biogas_flare_status' => $value]);
    }

    $this->actingAs($this->supervisorA);

    $dist = $this->service->buildSummary($this->periodA, null, $this->lineA)['status_distribution'];

    expect($dist['biogas_flare_status']['counts'])->toBe(['on' => 5, 'off' => 2, 'fault' => 1]);
    expect($dist['biogas_flare_status']['not_recorded'])->toBe(0);
    expect($dist['biogas_flare_status']['recorded_slots'])->toBe(8);

    // KATEGORIS: tidak ada min/avg/max untuk kolom ini di mana pun.
    expect($dist['biogas_flare_status'])->not->toHaveKey('min');
    expect($dist['biogas_flare_status'])->not->toHaveKey('avg');
    expect($dist['biogas_flare_status'])->not->toHaveKey('max');

    // Dan invariannya: sum(counts) + not_recorded === recorded_slots.
    expect(array_sum($dist['biogas_flare_status']['counts']) + $dist['biogas_flare_status']['not_recorded'])
        ->toBe($dist['biogas_flare_status']['recorded_slots']);
});

it('case 38 — nilai status dikenal yang tidak muncul dilengkapi dengan jumlah 0', function () {
    $record = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-1');

    foreach (array_slice($this->slots, 0, 4) as $slot) {
        effluentPlantReportSlot($record, $slot, ['biogas_flare_status' => 'on']);
    }

    $this->actingAs($this->supervisorA);

    $dist = $this->service->buildSummary($this->periodA, null, $this->lineA)['status_distribution'];

    // Baris nilai yang dikenal tidak boleh HILANG dari daftar: baris yang
    // hilang tidak dapat dibedakan dari nilai yang tidak pernah ada di basis
    // data.
    expect($dist['biogas_flare_status']['counts'])->toBe(['on' => 4, 'off' => 0, 'fault' => 0]);
    expect($dist['biogas_flare_status']['known_values'])->toBe(['on', 'off', 'fault']);
    expect(EffluentPlantReportService::STATUS_COLUMNS['biogas_flare_status'])->toBe(['on', 'off', 'fault']);
});

it('case 39 — slot dengan kolom status NULL masuk not_recorded, BUKAN off/stop', function () {
    $record = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-1');

    // Sepuluh slot terisi lewat kolom lain, tanpa satu pun nilai status.
    foreach (array_slice($this->slots, 0, 10) as $slot) {
        effluentPlantReportSlot($record, $slot, ['anaerobic_pond_1_ph' => 7.0]);
    }

    $this->actingAs($this->supervisorA);

    $dist = $this->service->buildSummary($this->periodA, null, $this->lineA)['status_distribution'];

    // Melipat null ke 'off' akan melaporkan flare mati yang tidak pernah
    // terjadi — dan di stasiun ini "tidak kami tulis" dan "memang tidak
    // jalan" punya akibat yang sama sekali berbeda.
    expect($dist['biogas_flare_status']['not_recorded'])->toBe(10);
    expect($dist['biogas_flare_status']['counts'])->toBe(['on' => 0, 'off' => 0, 'fault' => 0]);
    expect($dist['dosing_pump_1_status']['not_recorded'])->toBe(10);
    expect($dist['dosing_pump_1_status']['counts'])->toBe(['run' => 0, 'stop' => 0]);
    expect($dist['sludge_dewatering_status']['not_recorded'])->toBe(10);
    expect($dist['sludge_dewatering_status']['counts'])->toBe(['run' => 0, 'stop' => 0]);
});

it('case 40 — sebaran pompa dosing dan sludge dewatering memuat run, stop, dan not_recorded', function () {
    $record = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-1');

    // dosing: 4 'run', 0 'stop', 3 null. sludge: 0 'run', 2 'stop', 5 null.
    $baris = [
        ['dosing_pump_1_status' => 'run', 'sludge_dewatering_status' => 'stop'],
        ['dosing_pump_1_status' => 'run', 'sludge_dewatering_status' => 'stop'],
        ['dosing_pump_1_status' => 'run', 'anaerobic_pond_1_ph' => 7.0],
        ['dosing_pump_1_status' => 'run', 'anaerobic_pond_1_ph' => 7.0],
        ['anaerobic_pond_1_ph' => 7.0],
        ['anaerobic_pond_1_ph' => 7.0],
        ['anaerobic_pond_1_ph' => 7.0],
    ];

    foreach ($baris as $index => $values) {
        effluentPlantReportSlot($record, $this->slots[$index], $values);
    }

    $this->actingAs($this->supervisorA);

    $dist = $this->service->buildSummary($this->periodA, null, $this->lineA)['status_distribution'];

    expect($dist['dosing_pump_1_status']['counts'])->toBe(['run' => 4, 'stop' => 0]);
    expect($dist['dosing_pump_1_status']['not_recorded'])->toBe(3);
    expect($dist['sludge_dewatering_status']['counts'])->toBe(['run' => 0, 'stop' => 2]);
    expect($dist['sludge_dewatering_status']['not_recorded'])->toBe(5);

    foreach (['dosing_pump_1_status', 'sludge_dewatering_status'] as $column) {
        expect(array_sum($dist[$column]['counts']) + $dist[$column]['not_recorded'])
            ->toBe($dist[$column]['recorded_slots'], $column);
    }
});

it('case 41 — ketiga kolom status tidak menghasilkan min/avg/max maupun masuk tren harian', function () {
    $record = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-1');

    effluentPlantReportSlot($record, '07:00', [
        'biogas_flare_status' => 'on',
        'dosing_pump_1_status' => 'run',
        'sludge_dewatering_status' => 'stop',
        'raw_pome_feed_rate_m3h' => 40.0,
        'final_discharge_ph' => 7.0,
    ]);
    effluentPlantReportSlot($record, '08:00', [
        'biogas_flare_status' => 'fault',
        'dosing_pump_1_status' => 'stop',
        'sludge_dewatering_status' => 'run',
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    foreach (array_keys(EffluentPlantReportService::STATUS_COLUMNS) as $column) {
        $this->assertArrayNotHasKey($column, $summary['metrics'], "metrics tidak boleh memuat {$column}");
        $this->assertArrayNotHasKey($column, $summary['daily_trend'], "daily_trend tidak boleh memuat {$column}");
        expect($summary['by_unit'][0]['metrics'])->not->toHaveKey($column);
        expect($summary['daily'][0]['metrics_avg'])->not->toHaveKey($column);
    }

    expect(EffluentPlantReportService::STATUS_COLUMNS)->toHaveCount(3);
    expect(array_keys($summary['status_distribution']))
        ->toBe(array_keys(EffluentPlantReportService::STATUS_COLUMNS));
});

// =====================================================================
// GROUP I — DUA DAFTAR TEKS BEBAS YANG TERPISAH (case 42-45)
// =====================================================================

it('case 42 — rekap catatan perawatan diterbitkan sebagai daftar {date, time_slot, text}', function () {
    $day2 = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-1');
    $day3 = effluentPlantReportRecord($this->stationA, $this->day3, 'EP-1');

    effluentPlantReportSlot($day2, '07:00', ['remarks_maintenance_actions' => 'Ganti seal pompa']);
    effluentPlantReportSlot($day3, '09:00', ['remarks_maintenance_actions' => 'Bersihkan saringan']);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['maintenance_remarks'])->toHaveCount(2);
    expect($summary['maintenance_remarks'][0])->toBe([
        'date' => $this->day2,
        'time_slot' => '07:00',
        'text' => 'Ganti seal pompa',
    ]);
    expect($summary['maintenance_remarks'][1]['time_slot'])->toBe('09:00');

    // Teks bebas tidak punya rata-rata: tidak pernah dirata-rata maupun
    // digrafikkan.
    foreach ($summary['maintenance_remarks'] as $entry) {
        expect(array_keys($entry))->toBe(['date', 'time_slot', 'text']);
    }

    expect($summary['daily_trend'])->not->toHaveKey('remarks_maintenance_actions');
    expect($summary['metrics'])->not->toHaveKey('remarks_maintenance_actions');
});

it('case 43 — rekap temuan diterbitkan sebagai daftar yang TERPISAH dari rekap perawatan', function () {
    $record = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-1');

    effluentPlantReportSlot($record, '07:00', ['findings' => 'Busa tebal di kolam 2']);
    effluentPlantReportSlot($record, '08:00', ['remarks_maintenance_actions' => 'Tambah dosis']);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // DUA daftar, masing-masing satu entri — tidak digabung menjadi satu.
    expect($summary['findings'])->toHaveCount(1);
    expect($summary['maintenance_remarks'])->toHaveCount(1);
    expect($summary['findings'][0]['text'])->toBe('Busa tebal di kolam 2');
    expect($summary['maintenance_remarks'][0]['text'])->toBe('Tambah dosis');
    expect(EffluentPlantReportService::FREE_TEXT_COLUMNS)
        ->toBe(['remarks_maintenance_actions', 'findings']);
});

it('case 44 — slot yang mengisi KEDUA kolom teks muncul di KEDUA daftar', function () {
    $record = effluentPlantReportRecord($this->stationA, $this->day3, 'EP-1');

    effluentPlantReportSlot($record, '08:00', [
        'remarks_maintenance_actions' => 'Kalibrasi pompa dosing',
        'findings' => 'pH turun mendadak',
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // SATU KALI di tiap daftar — bukan dua kali di satu daftar gabungan.
    // Keduanya menjawab pertanyaan yang berbeda: apa yang dikerjakan, versus
    // apa yang ditemukan.
    expect($summary['maintenance_remarks'])->toHaveCount(1);
    expect($summary['findings'])->toHaveCount(1);
    expect($summary['maintenance_remarks'][0]['date'])->toBe($this->day3);
    expect($summary['maintenance_remarks'][0]['time_slot'])->toBe('08:00');
    expect($summary['findings'][0]['date'])->toBe($this->day3);
    expect($summary['findings'][0]['time_slot'])->toBe('08:00');
    expect($summary['coverage']['filled_slots'])->toBe(1);
});

it('case 45 — kedua kolom teks kosong menghasilkan DUA daftar kosong yang terpisah', function () {
    $record = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-1');

    effluentPlantReportSlot($record, '07:00', ['anaerobic_pond_1_ph' => 7.0]);
    // Spasi tepi saja: catatan '   ' menjadi null, bukan entri kosong.
    effluentPlantReportSlot($record, '08:00', [
        'anaerobic_pond_1_ph' => 7.1,
        'remarks_maintenance_actions' => '   ',
        'findings' => '',
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['maintenance_remarks'])->toBe([]);
    expect($summary['findings'])->toBe([]);
    expect($summary)->toHaveKey('maintenance_remarks');
    expect($summary)->toHaveKey('findings');
});

// =====================================================================
// GROUP J — KELENGKAPAN PENCATATAN (case 46-51)
// =====================================================================

it('case 46 — expected_slots = unit_count x days_counted x 24', function () {
    // Periode 9 hari yang SUDAH SELESAI, sehingga days_counted 9.
    $period9 = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::EffluentPlant->value)
        ->range(now()->subDays(19)->toDateString(), now()->subDays(11)->toDateString())
        ->open()
        ->named('Periode Sembilan Hari')
        ->create();

    foreach (['EP-1', 'EP-2'] as $unit) {
        $record = effluentPlantReportRecord($this->stationA, $this->day2, $unit);
        effluentPlantReportSlot($record, '07:00', ['anaerobic_pond_1_ph' => 7.0]);
    }

    $this->actingAs($this->supervisorA);

    $coverage = $this->service->buildSummary($period9, null, $this->lineA)['coverage'];

    expect($coverage['days_in_period'])->toBe(9);
    expect($coverage['days_counted'])->toBe(9);
    expect($coverage['effluent_plant_unit_count'])->toBe(2);
    // 24 datang dari kisi slot kanonis layar input itu sendiri — satu
    // definisi, satu jawaban.
    expect($coverage['slots_per_unit_per_day'])->toBe(24);
    expect(count(EffluentPlantRecordService::canonicalTimeSlots()))->toBe(24);
    expect($coverage['expected_slots'])->toBe(432);
});

it('case 47 — days_counted BERHENTI DI HARI INI untuk periode yang masih berjalan', function () {
    // Periode 01-09 dibaca pada hari ke-4: start = hari ini - 3, end = hari
    // ini + 5 (sembilan hari, empat di antaranya sudah lewat).
    $running = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::EffluentPlant->value)
        ->range(now()->subDays(3)->toDateString(), now()->addDays(5)->toDateString())
        ->open()
        ->named('Periode Berjalan')
        ->create();

    // Keempat hari lewatnya terisi PENUH: 1 unit x 4 hari x 24 slot = 96.
    foreach (range(0, 3) as $offset) {
        $date = now()->subDays(3 - $offset)->toDateString();
        $record = effluentPlantReportRecord($this->stationA, $date, 'EP-1');

        foreach ($this->slots as $slot) {
            effluentPlantReportSlot($record, $slot, ['anaerobic_pond_1_ph' => 7.0]);
        }
    }

    $this->actingAs($this->supervisorA);

    $coverage = $this->service->buildSummary($running, null, $this->lineA)['coverage'];

    expect($coverage['days_in_period'])->toBe(9);
    expect($coverage['days_counted'])->toBe(4);
    expect($coverage['period_running'])->toBeTrue();
    expect($coverage['filled_slots'])->toBe(96);
    expect($coverage['expected_slots'])->toBe(96);
    // Hari yang belum terjadi tidak mungkin tercatat, jadi tidak ikut
    // menurunkan persentase.
    expect($coverage['coverage_percent'])->toBe(100.0);
});

it('case 48 — days_counted 0 untuk periode yang BELUM MULAI', function () {
    $future = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::EffluentPlant->value)
        ->range(now()->addDays(5)->toDateString(), now()->addDays(14)->toDateString())
        ->draft()
        ->named('Periode Belum Mulai')
        ->create();

    $this->actingAs($this->supervisorA);

    $coverage = $this->service->buildSummary($future, null, $this->lineA)['coverage'];

    expect($coverage['days_counted'])->toBe(0);
    expect($coverage['expected_slots'])->toBe(0);
    // period_running juga true untuk periode yang BELUM MULAI, jadi layar
    // harus memeriksa days_counted lebih dulu.
    expect($coverage['period_running'])->toBeTrue();
});

it('case 49 — expected_slots 0 menghasilkan coverage_percent 0.0 tanpa pembagian dengan nol', function () {
    $this->actingAs($this->supervisorA);

    // Tanpa satu record pun: unit_count 0, jadi expected_slots 0.
    $coverage = $this->service->buildSummary($this->periodA, null, $this->lineA)['coverage'];

    expect($coverage['effluent_plant_unit_count'])->toBe(0);
    expect($coverage['expected_slots'])->toBe(0);
    // 0.0, SENGAJA — bukan null: penanda bagi layar untuk menampilkan '—'
    // dengan 'belum ada slot yang diharapkan'. Yang tidak boleh adalah
    // DivisionByZeroError.
    expect($coverage['coverage_percent'])->toBe(0.0);
    expect($coverage['coverage_percent'])->not->toBeNull();
});

it('case 50 — has_data false ketika tidak ada slot terisi', function () {
    $record = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-1');
    effluentPlantReportSlot($record, '07:00');

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // Membedakan "tidak ada yang dilaporkan" dari "angkanya nol", dan
    // dipakai layar untuk MENOLAK menggambar grafik kosong yang akan terbaca
    // sebagai garis datar hasil pengukuran.
    expect($summary['has_data'])->toBeFalse();
    expect($summary['coverage']['filled_slots'])->toBe(0);
    expect($summary['metrics'])->toHaveCount(14);
});

it('case 51 — has_data true ketika ada slot terisi walau nilainya NOL', function () {
    $record = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-1');
    effluentPlantReportSlot($record, '07:00', ['biogas_flow_rate_m3h' => 0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['has_data'])->toBeTrue();
    expect(effluentPlantReportMetric($summary, 'biogas_flow_rate_m3h')['avg'])->toBe(0.0);
    expect(effluentPlantReportMetric($summary, 'biogas_flow_rate_m3h')['reading_count'])->toBe(1);
});

// =====================================================================
// GROUP K — YANG SENGAJA TIDAK ADA (case 52)
// =====================================================================

it('case 52 — TIDAK ADA penandaan di luar batas dan TIDAK ADA blok downtime: diasersi atas KUNCI', function () {
    $record = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-1');

    // Nilai SENGAJA ekstrem: pH 1,0 dan 13,9 pada air buangan, BOD 25.000
    // mg/L. Keduanya jelas jauh di luar baku mutu apa pun, dan laporan ini
    // TETAP tidak menilainya.
    effluentPlantReportSlot($record, '07:00', [
        'final_discharge_ph' => 1.0,
        'final_discharge_bod_mgl_lab' => 25000.0,
    ]);
    effluentPlantReportSlot($record, '08:00', ['final_discharge_ph' => 13.9]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // DIASERSI ATAS KETIADAAN KUNCI, bukan atas ketiadaan frasa: `_note`
    // pada payload ini justru MEMUAT kata "downtime" dan kata "patuh",
    // karena ia menyatakan ketiadaannya. Pencarian frasa akan salah tangkap.
    // Dan asersi kelas CSS milik uji UI, bukan lapisan ini.
    foreach ($summary['metrics'] as $column => $metric) {
        expect(array_keys($metric))->toBe(['min', 'avg', 'max', 'reading_count', 'is_lab'], $column);
    }

    $allKeys = effluentPlantReportAllKeys($summary);

    foreach ([
        'severity', 'flag', 'is_out_of_range', 'out_of_range', 'warning', 'color',
        'threshold', 'breach', 'exceeds', 'target', 'targets', 'critical_limit',
        'downtime', 'downtime_minutes', 'total_minutes',
        'compliance', 'is_compliant', 'compliant', 'patuh', 'quality_standard',
    ] as $key) {
        $this->assertNotContains($key, $allKeys, "payload tidak boleh punya kunci {$key}");
    }

    // Kunci yang MEMANG ada, dan hanya itu.
    expect(array_keys($summary))->toBe([
        'period', 'business_unit', 'production_line', 'has_data', 'coverage',
        'metrics', 'status_distribution', 'maintenance_remarks', 'findings',
        'daily_trend', 'by_unit', 'daily', 'daily_total', 'total', '_note',
    ]);

    // Dan angkanya tetap diterbitkan apa adanya.
    expect(effluentPlantReportMetric($summary, 'final_discharge_ph')['min'])->toBe(1.0);
    expect(effluentPlantReportMetric($summary, 'final_discharge_ph')['max'])->toBe(13.9);
    expect(effluentPlantReportMetric($summary, 'final_discharge_bod_mgl_lab')['avg'])->toBe(25000.0);
});

// =====================================================================
// GROUP L — TREN HARIAN, REKAP PER UNIT, REKAP HARIAN (case 53-57)
// =====================================================================

it('case 53 — tren harian hanya memuat DUA metrik', function () {
    $record = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-1');

    effluentPlantReportSlot(
        $record,
        '07:00',
        array_fill_keys(EffluentPlantReportService::NUMERIC_METRICS, 7.0),
    );

    $this->actingAs($this->supervisorA);

    $trend = $this->service->buildSummary($this->periodA, null, $this->lineA)['daily_trend'];

    // Ketiga nilai laboratorium SENGAJA tidak digrafikkan harian: dengan
    // penyebut sekecil itu garisnya akan lebih banyak bolongnya daripada
    // datanya, dan garis yang menyambung dua titik berjauhan menyiratkan
    // pengukuran yang tidak pernah dilakukan.
    expect(array_keys($trend))->toBe(['raw_pome_feed_rate_m3h', 'final_discharge_ph']);
    expect(EffluentPlantReportService::TREND_METRICS)
        ->toBe(['raw_pome_feed_rate_m3h', 'final_discharge_ph']);

    foreach ($trend as $series) {
        expect($series)->toHaveCount(1);
        expect(array_keys($series[0]))->toBe(['date', 'avg', 'reading_count']);
    }
});

it('case 54 — tanggal tanpa nilai metrik tren bernilai null, bukan 0, dan tetap hari ber-record', function () {
    $withTrend = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-1');
    effluentPlantReportSlot($withTrend, '07:00', ['raw_pome_feed_rate_m3h' => 40.0]);

    $withoutTrend = effluentPlantReportRecord($this->stationA, $this->day3, 'EP-1');
    effluentPlantReportSlot($withoutTrend, '07:00', ['anaerobic_pond_1_ph' => 7.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);
    $series = collect($summary['daily_trend']['raw_pome_feed_rate_m3h'])->keyBy('date');

    expect($series[$this->day2]['avg'])->toBe(40.0);
    expect($series[$this->day2]['reading_count'])->toBe(1);
    // Titik nol akan mengklaim sebuah pengukuran yang tidak pernah
    // dilakukan.
    expect($series[$this->day3]['avg'])->toBeNull();
    expect($series[$this->day3]['reading_count'])->toBe(0);
    expect($summary['total']['days_with_records'])->toBe(2);
    expect($summary['daily'])->toHaveCount(2);
});

it('case 55 — rekap per unit dikelompokkan pada effluent_plant_id dan angka periode menggabungkannya', function () {
    $unit1 = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-1');
    $unit2 = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-2');

    effluentPlantReportSlot($unit1, '07:00', ['anaerobic_pond_1_ph' => 6.0]);
    effluentPlantReportSlot($unit2, '07:00', ['anaerobic_pond_1_ph' => 8.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['by_unit'])->toHaveCount(2);
    expect(collect($summary['by_unit'])->pluck('effluent_plant_id')->all())->toBe(['EP-1', 'EP-2']);
    expect($summary['by_unit'][0]['metrics']['anaerobic_pond_1_ph']['avg'])->toBe(6.0);
    expect($summary['by_unit'][1]['metrics']['anaerobic_pond_1_ph']['avg'])->toBe(8.0);
    // Angka periode MENGGABUNGKAN keduanya, dihitung ulang atas seluruh slot.
    expect(effluentPlantReportMetric($summary, 'anaerobic_pond_1_ph')['avg'])->toBe(7.0);
    expect($summary['coverage']['effluent_plant_unit_count'])->toBe(2);

    // Satu definisi, satu jawaban: slot per unit menjumlah ke slot terisi.
    expect(collect($summary['by_unit'])->sum('reading_rows'))
        ->toBe($summary['coverage']['filled_slots']);
    expect($summary['daily_total']['reading_rows'])->toBe($summary['coverage']['filled_slots']);
    expect($summary['total']['reading_rows'])->toBe($summary['coverage']['filled_slots']);
});

it('case 56 — unit tanpa pembacaan tetap muncul dengan reading_count 0 dan rata-rata null', function () {
    $withReadings = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-1');
    effluentPlantReportSlot($withReadings, '07:00', ['anaerobic_pond_1_ph' => 7.0]);

    // Unit kedua punya record dan slot, tetapi tak satu kolomnya terisi.
    $withoutReadings = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-2');
    effluentPlantReportSlot($withoutReadings, '07:00');

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // Membuangnya akan menyembunyikan justru unit yang tidak pernah
    // ditulis — dan unit itu tetap ikut menambah penyebut kelengkapan.
    expect($summary['by_unit'])->toHaveCount(2);
    expect($summary['by_unit'][1]['effluent_plant_id'])->toBe('EP-2');
    expect($summary['by_unit'][1]['reading_rows'])->toBe(0);
    expect($summary['by_unit'][1]['reading_count'])->toBe(0);
    expect($summary['by_unit'][1]['metrics']['anaerobic_pond_1_ph']['avg'])->toBeNull();
    expect($summary['coverage']['effluent_plant_unit_count'])->toBe(2);
});

it('case 57 — agregasi dikerjakan DI PHP: nol agregat SQL, dan whereDate dipakai apa adanya', function () {
    $record = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-1');
    effluentPlantReportSlot($record, '07:00', ['anaerobic_pond_1_ph' => 7.0]);
    effluentPlantReportSlot($record, '08:00', ['final_discharge_ph' => 7.4]);

    $this->actingAs($this->supervisorA);

    $queries = effluentPlantReportQueriesDuring(function () {
        $this->service->buildSummary($this->periodA, null, $this->lineA);
    });

    // SQLite (suite) dan PostgreSQL (produksi) berbeda perilaku pada
    // AVG/SUM atas kolom bernull, dan di laporan ini risikonya lebih besar
    // daripada di mana pun: empat belas kolom nullable, tiga di antaranya
    // hampir selalu NULL.
    foreach ($queries as $sql) {
        foreach (['avg(', 'min(', 'max(', 'sum(', 'group by'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $sql, "kueri memakai {$forbidden}: {$sql}");
        }
    }

    $recordQueries = array_values(array_filter(
        $queries,
        fn (string $sql) => str_contains($sql, 'from "effluent_plant_records"'),
    ));

    // SATU kueri record + satu eager-load detail: tidak ada N+1.
    expect($recordQueries)->toHaveCount(1);

    $detailQueries = array_values(array_filter(
        $queries,
        fn (string $sql) => str_contains($sql, 'from "effluent_plant_details"'),
    ));

    expect($detailQueries)->toHaveCount(1);
    expect($detailQueries[0])->toContain('order by "time_slot" asc');

    // whereDate(), bukan where() mentah: pada SQLite ia mengompilasi menjadi
    // strftime('%Y-%m-%d', ...), dan justru di ujung-ujung periode itulah
    // bentuk mentah berbeda — dan hanya di PostgreSQL.
    if (DB::connection()->getDriverName() === 'sqlite') {
        expect($recordQueries[0])->toContain('strftime');
    }
});

// =====================================================================
// GROUP M — EKSPOR (case 58-62)
// =====================================================================

it('case 58 — format ekspor default csv ketika parameter format tidak dikirim', function () {
    $record = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-1');
    effluentPlantReportSlot($record, '07:00', ['anaerobic_pond_1_ph' => 7.0]);

    $this->actingAs($this->supervisorA);

    $response = $this->service->export(
        $this->periodA,
        requestedBusinessUnitId: null,
        productionLineId: $this->lineA,
    );

    expect($response)->toBeInstanceOf(StreamedResponse::class);
    expect($response->headers->get('content-type'))->toContain('text/csv');
    expect($response->headers->get('content-disposition'))->toContain('.csv');
    expect($response->headers->get('content-disposition'))->toContain('laporan-effluent-plant');
});

it('case 59 — 422 ketika format ekspor bukan csv maupun excel, dan pengaliran tidak dimulai', function () {
    $record = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-1');
    effluentPlantReportSlot($record, '07:00', ['anaerobic_pond_1_ph' => 7.0]);

    $this->actingAs($this->supervisorA);

    expect(EffluentPlantReportService::SUPPORTED_FORMATS)->toBe(['csv', 'excel']);

    expect(fn () => $this->service->export($this->periodA, 'pdf', null, $this->lineA))
        ->toThrow(ValidationException::class);
    // Service-nya KETAT: string kosong bukan format yang dikenal. Default
    // 'csv' hidup di controller, bukan di sini.
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

it('case 60 — batas ekspor dihitung atas BARIS SLOT, bukan record, dan 50.000 adalah batasnya', function () {
    $record = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-1');

    // SATU record, ENAM baris slot. Batas yang dihitung per RECORD akan
    // meloloskannya — satu record harian membawa sampai 24 baris, jadi batas
    // per record akan meloloskan berkas 24x lebih besar daripada yang
    // dimaksud. Jebakan yang sama yang diperbaiki untuk 18 ekspor stasiun
    // pada commit 8611974.
    foreach (array_slice($this->slots, 0, 6) as $slot) {
        effluentPlantReportSlot($record, $slot, ['anaerobic_pond_1_ph' => 7.0]);
    }

    $this->actingAs($this->supervisorA);

    $tiny = new EffluentPlantReportTinyExportService;

    // Penolakannya datang dari PEMANGGILAN ITU SENDIRI, sebelum satu byte
    // pun dialirkan: buildExportRows() bukan generator, ia MEMULANGKAN
    // generator.
    expect(fn () => $tiny->buildExportRows($this->periodA, null, $this->lineA))
        ->toThrow(ExportFailedException::class);
    expect(fn () => $tiny->export($this->periodA, 'csv', null, $this->lineA))
        ->toThrow(ExportFailedException::class);

    $thrown = null;

    try {
        $tiny->buildExportRows($this->periodA, null, $this->lineA);
    } catch (ExportFailedException $e) {
        $thrown = $e;
    }

    expect($thrown)->not->toBeNull();
    expect($thrown->getStatusCode())->toBe(422);
    expect($thrown->errorCode())->toBe('EXPORT_FAILED');

    // Dan batas yang PERSIS SAMA masih lolos: "strictly greater than".
    EffluentPlantDetail::query()->where('time_slot', $this->slots[5])->delete();

    expect($tiny->buildExportRows($this->periodA, null, $this->lineA))->toBeInstanceOf(Generator::class);

    // Batas sungguhannya 50.000, dibaca lewat static:: sehingga subclass di
    // atas dapat menurunkannya alih-alih menyemai 50.000 baris.
    expect(EffluentPlantReportService::EXPORT_ROW_LIMIT)->toBe(50000);
});

it('case 61 — guard, resolusi mill/line dan batas baris diperiksa EAGER sebelum pengaliran', function () {
    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::EffluentPlant->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->create();

    $this->actingAs($this->supervisorA);

    // Metode yang DIJADIKAN generator akan menunda 403/422 sampai iterasi
    // pertama — yaitu setelah header unduhan terkirim — sehingga penolakan
    // tiba sebagai unduhan sukses yang kosong.
    expect(fn () => $this->service->buildExportRows($periodB, null, $this->lineA))
        ->toThrow(AuthorizationException::class);
    expect(fn () => $this->service->export($periodB, 'csv', null, $this->lineA))
        ->toThrow(AuthorizationException::class);

    // Line mill lain: 403 sebagai respons biasa, bukan galat di tengah
    // stream.
    expect(fn () => $this->service->export($this->periodA, 'csv', null, $this->lineB))
        ->toThrow(AuthorizationException::class);
    // Line absen: 422, juga sebelum pengaliran.
    expect(fn () => $this->service->export($this->periodA, 'csv', null, null))
        ->toThrow(ValidationException::class);
});

it('case 62 — ekspor menerbitkan SATU BARIS PER SLOT WAKTU dengan kolom konteks DIULANG', function () {
    $record = effluentPlantReportRecord($this->stationA, $this->day2, 'EP-9');

    foreach (array_slice($this->slots, 0, 3) as $slot) {
        effluentPlantReportSlot($record, $slot, [
            'anaerobic_pond_1_ph' => 7.0,
            'biogas_flare_status' => 'fault',
            'dosing_pump_1_status' => 'run',
            'sludge_dewatering_status' => 'stop',
            'remarks_maintenance_actions' => 'Ganti seal',
            'findings' => 'Busa tebal',
        ]);
    }

    $this->actingAs($this->supervisorA);

    // DUA PULUH TUJUH kolom: 7 konteks + Slot Waktu + 19 READING_FIELDS.
    expect(EffluentPlantReportService::EXPORT_HEADER)->toHaveCount(27);
    expect(array_slice(EffluentPlantReportService::EXPORT_HEADER, 0, 8))->toBe([
        'Periode', 'Mill', 'Production Line', 'Tanggal',
        'Unit Effluent Plant', 'Status', 'Catatan', 'Slot Waktu',
    ]);
    // Tidak ada kolom downtime dan tidak ada kolom kesimpulan kepatuhan.
    foreach (EffluentPlantReportService::EXPORT_HEADER as $header) {
        expect(strtolower($header))->not->toContain('downtime');
        expect(strtolower($header))->not->toContain('patuh');
    }

    $rows = iterator_to_array($this->service->buildExportRows($this->periodA, null, $this->lineA));

    expect($rows)->toHaveCount(3);

    // Kolom konteks terulang VERBATIM pada ketiga baris — diulang, bukan
    // dikosongkan, supaya berkasnya langsung dapat dipivot di spreadsheet.
    foreach ($rows as $row) {
        expect($row)->toHaveCount(27);
        expect($row[0])->toBe('Periode Effluent Plant Alpha');
        expect($row[1])->toBe('Mill Alpha');
        expect($row[2])->not->toBe('');
        expect($row[3])->toBe($this->day2);
        expect($row[4])->toBe('EP-9');
    }

    // Slot selalu HH:MM.
    expect($rows[0][7])->toBe('07:00');
    expect($rows[1][7])->toBe('08:00');
    expect($rows[2][7])->toBe('09:00');

    // LABEL KOLOM STATUS MENGIKUTI LAYAR DETAIL, bukan pemeta status RECORD.
    // Tech spec menulis "ExportValue::status()", tetapi pemeta itu memetakan
    // status record (Tersimpan/Tersinkron) dan BUKAN ketiga kolom ini.
    // Implementasinya memakai ExportValue::option() -> Display::OPTION_LABELS,
    // yang menghasilkan On/Off/Fault dan Run/Stop — identik dengan ekspor
    // EffluentPlantRecordService::export() atas 19 kolom yang sama dan dengan
    // layar Detail, sesuai konvensi proyek "label ekspor ikut layar Detail".
    $readingFields = EffluentPlantRecordService::READING_FIELDS;
    $flareIndex = 8 + array_search('biogas_flare_status', $readingFields, true);
    $dosingIndex = 8 + array_search('dosing_pump_1_status', $readingFields, true);
    $sludgeIndex = 8 + array_search('sludge_dewatering_status', $readingFields, true);

    expect($rows[0][$flareIndex])->toBe('Fault');
    expect($rows[0][$dosingIndex])->toBe('Run');
    expect($rows[0][$sludgeIndex])->toBe('Stop');

    // KEDUA kolom teks bebas ikut, apa adanya — di ujung blok 19 kolom.
    expect($rows[0][25])->toBe('Ganti seal');
    expect($rows[0][26])->toBe('Busa tebal');

    // Slot yang kolom ukurnya kosong TETAP menjadi baris dengan sel KOSONG —
    // bukan dibuang, dan BUKAN ditulis 0. Membuangnya akan membuat berkasnya
    // berselisih dengan angka cakupan yang laporan yang sama terbitkan.
    $empty = effluentPlantReportRecord($this->stationA, $this->day3, 'EP-8');
    effluentPlantReportSlot($empty, '07:00');

    $withEmpty = iterator_to_array($this->service->buildExportRows($this->periodA, null, $this->lineA));

    expect($withEmpty)->toHaveCount(4);
    expect($withEmpty[3][4])->toBe('EP-8');
    // pH Kolam Anaerobik 1 dan Temuan — ujung-ujung blok 19 kolom bacaan.
    expect($withEmpty[3][8])->toBeNull();
    expect($withEmpty[3][26])->toBeNull();

    // Dan berkas yang di-stream memuat seluruh judul kolomnya.
    $body = effluentPlantReportStreamed($this->service->export($this->periodA, 'csv', null, $this->lineA));

    foreach (EffluentPlantReportService::EXPORT_HEADER as $header) {
        expect($body)->toContain($header);
    }

    expect($body)->toContain('EP-9');
    expect($body)->toContain('Busa tebal');
});

// =====================================================================
// SATU BARIS YANG MENENTUKAN LAYARNYA DAPAT DICAPAI (case 66)
//
// case 63-65 menyangkut komponen Livewire dan dibuktikan di
// tests/Feature/Livewire/LaporanEffluentPlantTest.php dengan label yang sama.
// =====================================================================

it('case 66 — REPORT_ROUTES memetakan effluent-plant, dan posisinya mengikuti sort_order', function () {
    // Tanpa entri 'effluent-plant' pada StationReportService::REPORT_ROUTES,
    // layar laporan ada, seluruh uji lainnya lolos, dan tile-nya tetap
    // kelabu — pola kegagalan yang sudah pernah terjadi pada laporan Cages &
    // Tracks versi web.
    expect(StationReportService::REPORT_ROUTES)
        ->toHaveKey(StationTypeEnum::EffluentPlant->value, 'reports.effluent-plant');
    // Kuncinya BERTANDA HUBUNG; kunci snake_case akan ter-compile, tampak
    // benar, dan tidak pernah cocok dengan satu baris master pun.
    expect(StationTypeEnum::EffluentPlant->value)->toBe('effluent-plant');
    expect(route('reports.effluent-plant', [], false))->toBe('/reports/effluent-plant');

    // URUTANNYA LOAD-BEARING: peta ini harus tetap urut menurut
    // station_types.sort_order, karena layar pemilih stasiun membandingkan
    // urutannya dengan urutan master. effluent-plant di 100 — di belakang
    // boiler-room (90), di depan depricarping (110).
    $codes = array_keys(StationReportService::REPORT_ROUTES);
    $at = array_search(StationTypeEnum::EffluentPlant->value, $codes, true);

    expect($codes[$at - 1])->toBe(StationTypeEnum::BoilerRoom->value);
    expect($codes[$at + 1])->toBe(StationTypeEnum::Depricarping->value);
});
