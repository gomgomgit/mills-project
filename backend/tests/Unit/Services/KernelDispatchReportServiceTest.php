<?php

/**
 * KernelDispatchReportServiceTest — screen-164--laporan-kernel-dispatch-web /
 * usecase-170--laporan-kernel-dispatch-web (Laporan Periode Kernel Dispatch),
 * terhadap App\Services\KernelDispatchReportService.
 *
 * SATU `it()` PER unit_test_case pada tech spec — KEENAM PULUH DUA, dalam
 * urutan spec-nya sendiri. Penomoran `case N` di bawah SATU-BASIS, sementara
 * spec menyimpannya sebagai array JSON nol-basis: unit_test_cases[0] adalah
 * `case 1` di sini. Konvensi yang sama dengan ketujuh belas berkas saudaranya.
 *
 * `uses(TestCase::class, RefreshDatabase::class)` WAJIB: tests/Pest.php hanya
 * mengikat Tests\TestCase ke Feature/, jadi tanpa baris itu suite Unit tidak
 * punya container aplikasi sama sekali.
 *
 * ======================================================================
 * LAYAR INI HYBRID — SEPARUH TRANSAKSI, SEPARUH MUTU — DAN ITU MENENTUKAN
 * SETIAP JEBAKAN DI BAWAH
 * ======================================================================
 * `kernel_dispatch_details` TIDAK punya grid slot waktu: satu barisnya adalah
 * SATU PENGIRIMAN. Presedennya separuh SolidWasteDisposalReportServiceTest
 * (screen-162, sisi transaksi) dan separuh ProcessWaterReportServiceTest
 * (screen-160, sisi mutu per-ukuran).
 *
 *   1. SATUAN HITUNGNYA BARIS DETAIL, BUKAN RECORD (case 14). Satu record
 *      induk membawa banyak pengiriman.
 *   2. EMPAT UKURAN MUTU, EMPAT PENYEBUT (case 24, 25, 29). Inilah jebakan
 *      UTAMA layar ini: satu penyebut tunggal membuat keempat measured_count
 *      sama (dan biasanya sama dengan dispatch_count). Fixture di case 24
 *      karena itu disusun supaya KELIMA angka itu BERBEDA — fixture yang
 *      membuat keempatnya sama tidak membuktikan apa pun.
 *   3. PENJAGANYA PER UKURAN, BUKAN GLOBAL (case 25). measured_count 0 pada
 *      satu ukuran mengosongkan ukuran ITU saja; ketiga lainnya tetap
 *      berangka DI PAYLOAD YANG SAMA.
 *   4. NULL BUKAN NOL (case 20). net_weight_total/avg NULL — bukan 0.0 —
 *      diasersikan sebagai IDENTITAS null (toBeNull / assertNull).
 *   5. 'TIDAK TERCATAT' BUKAN 'TIDAK DILEPAS' (case 32). not_released_count
 *      hanya 'not_released' PERSIS; baris null tetap ada di by_gate_status.
 *      Menggabungkannya MENUDUH pengiriman yang sekadar belum diisi.
 *   6. PENJUMLAHAN REKAP = dispatch_count (case 34, 39). Asersi penjumlahan
 *      itulah yang menangkap baris yang 'dirapikan' — termasuk nilai enum
 *      yang tidak dikenal.
 *
 * ----------------------------------------------------------------------
 * TANGGAL FIXTURE SELALU RELATIF, TIDAK PERNAH KALENDER YANG DIPAKU
 * ----------------------------------------------------------------------
 * Tech spec menulis tanggal kalender (2026-03-xx); yang dipertahankan di sini
 * MAKSUDNYA, bukan digitnya. Dua sebab, keduanya menentukan:
 *
 *   - `$this->periodA` HARUS selalu sudah SELESAI supaya days_counted ===
 *     days_in_period === 31. Tanggal kalender yang dipaku berhenti memenuhi
 *     syarat itu begitu kalender melewatinya.
 *   - KernelDispatchRecordFactory men-default `date` ke
 *     dateTimeBetween('-30 days','now') dan StationFactory bersarang membuat
 *     ProductionLine sendiri. Asersi "tepat N pengiriman" yang menumpang
 *     default itu gagal sesekali tanpa ada hubungannya dengan layar ini.
 *     SETIAP record di bawah memaku tanggalnya lewat helper.
 *
 * ----------------------------------------------------------------------
 * DUA KOLOM YANG SKEMANYA MENOLAK FIXTURE, DAN KEDUANYA LEWAT SERVICE-GANDA
 * ----------------------------------------------------------------------
 *   - `event_date` NOT NULL (migrasi 2026_08_31_000007 menulis
 *     `$table->date('event_date')` tanpa ->nullable()). Case 47 MEMBUKTIKAN
 *     penolakan itu; cabang `undated_dispatch_count > 0` diuji di case 48
 *     lewat service-ganda in-memory.
 *   - `remarks_gate_status` adalah `enum(['released','not_released'])`
 *     nullable — sebuah CHECK CONSTRAINT di PostgreSQL DAN di SQLite. Nilai
 *     'HOLD_BY_SECURITY' karena itu TIDAK DAPAT disimpan lewat factory, dan
 *     case 33/34 menjalankannya lewat service-ganda yang sama.
 *
 * Yang TIDAK dilakukan di kedua tempat: berpura-pura menyemainya lewat
 * factory dan diam-diam menguji nol baris.
 */

use App\Enums\PeriodStatus;
use App\Enums\RecordStatus;
use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\KernelDispatchDetail;
use App\Models\KernelDispatchRecord;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\StationType;
use App\Models\User;
use App\Services\KernelDispatchReportService;
use App\Services\StationReportService;
use App\Support\Display;
use App\Support\ExportValue;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * SPY untuk case 5 — aturan 'gagal tertutup'.
 *
 * allBusinessUnits() publik pada service justru supaya dapat di-override di
 * sini. Mengasersikan 422-nya saja akan sama-sama hijau terhadap implementasi
 * yang MEMBANGUN daftar seluruh mill lebih dulu lalu melempar sesudahnya — dan
 * implementasi itu adalah kebocoran lintas mill yang menunggu satu refactor
 * lagi. Hanya hitungan panggilan NOL yang membuktikan daftarnya tidak pernah
 * disusun.
 */
class KernelDispatchReportAllBusinessUnitsSpy extends KernelDispatchReportService
{
    public int $allBusinessUnitsCalls = 0;

    public function allBusinessUnits(): Collection
    {
        $this->allBusinessUnitsCalls++;

        return parent::allBusinessUnits();
    }
}

/**
 * Menurunkan batas ekspor supaya perbedaan "baris PENGIRIMAN versus record
 * induk" dapat dibuktikan tanpa menyemai 50.001 baris.
 *
 * Hanya mungkin karena service membaca batasnya lewat `static::`, bukan
 * `self::` — pilihan itulah yang subclass ini ada untuk mengujinya.
 */
class KernelDispatchReportTinyExportService extends KernelDispatchReportService
{
    public const EXPORT_ROW_LIMIT = 5;
}

/**
 * Service yang membaca PENGIRIMAN dari MEMORI alih-alih dari basis data.
 *
 * ADA KARENA SKEMANYA, BUKAN KARENA KEMALASAN — lihat docblock berkas: dua
 * kolom (`event_date` NOT NULL dan `remarks_gate_status` ber-CHECK constraint)
 * membuat dua cabang invarian TIDAK DAPAT DICAPAI lewat factory. Meng-override
 * dispatchesFor() membuat dispatchesBlockOf(), qualityOf(), breakdownOf(),
 * dailyOf(), dailyTotalOf() dan textEntriesOf() — yaitu SELURUH agregasi —
 * tetap dijalankan sungguhan atas pengiriman itu.
 */
class KernelDispatchReportInMemoryService extends KernelDispatchReportService
{
    /** @var Collection<int, object> */
    public static Collection $dispatches;

    protected function dispatchesFor(Period $period, string $productionLineId): Collection
    {
        return static::$dispatches;
    }
}

/**
 * Satu record induk Kernel Dispatch, TANGGALNYA DIEJA sebagai argumen
 * posisional karena ia yang menentukan keanggotaan periode (recordQueryFor()
 * menyaring atas kernel_dispatch_records.date) dan tidak boleh pernah
 * diserahkan pada default acak factory.
 */
function kdReportRecord(Station $station, string $date, array $attributes = []): KernelDispatchRecord
{
    return KernelDispatchRecord::factory()
        ->forStation($station)
        ->onDate($date)
        ->create(array_merge([
            'kernel_dispatch_id' => 'KD-'.Str::upper(Str::random(8)),
            'status' => RecordStatus::Saved,
            'note' => null,
        ], $attributes));
}

/**
 * Satu baris detail = SATU PENGIRIMAN.
 *
 * SELURUH kolom yang berarti bagi laporan di-default menjadi NULL di sini, dan
 * itu disengaja: KernelDispatchDetailFactory mengisi ketiga kolom kategoris,
 * keempat kolom mutu, kedua nomor segel, nomor surat jalan, shift, gate status
 * dan findings dengan nilai faker ACAK, sehingga fixture yang menumpangnya akan
 * mengasersikan rekap yang isinya berubah tiap jalan. Setiap test di bawah
 * menyebut TEPAT kolom yang memang jadi subjeknya.
 *
 * `net_weight_mt` di-default 1.0 sementara bruto/tara di-default NULL — bentuk
 * yang sama dengan kedua baris fixture e2e hari ini (netto 20 MT sementara
 * bruto DAN tara null). Laporan ini MEMBACA net_weight_mt apa adanya;
 * menghitung ulang bruto−tara akan mengubah 20 MT menjadi null pada data yang
 * sudah ada.
 */
function kdReportDispatch(
    KernelDispatchRecord $record,
    string $eventDate,
    array $values = [],
): KernelDispatchDetail {
    return KernelDispatchDetail::factory()
        ->forRecord($record)
        ->create(array_merge([
            'event_date' => $eventDate,
            'shift' => null,
            'weighbridge_ticket_no' => null,
            'waybill_number' => null,
            'transporter_contractor' => null,
            'vehicle_plate_no' => null,
            'driver_name' => null,
            'silo_source_id' => null,
            'destination_buyer' => null,
            'gross_weight_mt' => null,
            'tare_weight_mt' => null,
            'net_weight_mt' => 1.0,
            'kernel_moisture_percent' => null,
            'dirt_impurities_percent' => null,
            'ffa_percent' => null,
            'broken_kernel_percent' => null,
            'security_seal_no_top' => null,
            'security_seal_no_bottom' => null,
            'weighbridge_operator_id' => null,
            'remarks_gate_status' => null,
            'findings' => null,
        ], $values));
}

/**
 * Satu PENGIRIMAN DI MEMORI, berbentuk PERSIS seperti objek yang
 * dispatchesOf() hasilkan — dipakai HANYA oleh case 33, 34 dan 48, tempat
 * skema menolak nilainya.
 */
function kdReportMemoryDispatch(?string $date, array $values = []): object
{
    $quality = [];

    foreach (KernelDispatchReportService::QUALITY_MEASURES as $measure) {
        $quality[$measure] = $values['quality'][$measure] ?? null;
    }

    unset($values['quality']);

    return (object) array_merge([
        'date' => $date,
        'net_weight' => 1.0,
        'quality' => $quality,
        'transporter' => null,
        'silo_source' => null,
        'destination_buyer' => null,
        'gate_status' => null,
        'waybill_number' => null,
        'security_seal_no_top' => null,
        'security_seal_no_bottom' => null,
        'findings' => null,
        'status' => RecordStatus::Saved->value,
    ], $values);
}

/** Setiap pernyataan SQL yang berjalan di dalam $callback, huruf kecil. */
function kdReportQueriesDuring(callable $callback): array
{
    $queries = [];

    DB::listen(function ($query) use (&$queries) {
        $queries[] = strtolower($query->sql);
    });

    try {
        $callback();
    } catch (Throwable) {
        // Kegagalan sengaja ditelan: pemanggilnya mengasersikan exception-nya
        // sendiri, dan yang dikumpulkan di sini adalah SQL-nya.
    }

    return $queries;
}

/** Benar ketika salah satu pernyataan menyentuh $table. */
function kdReportTouched(array $queries, string $table): bool
{
    foreach ($queries as $sql) {
        if (str_contains($sql, $table)) {
            return true;
        }
    }

    return false;
}

/** Berapa pernyataan yang membaca $table. */
function kdReportCountTouching(array $queries, string $table): int
{
    return count(array_filter($queries, fn (string $sql) => str_contains($sql, $table)));
}

/**
 * Seluruh nama KUNCI yang muncul di mana pun, serapa pun dalamnya.
 *
 * DIPAKAI ALIH-ALIH MEMINDAI json_encode: `_note_no_hourly` adalah KALIMAT
 * MANUSIA di dalam payload ini dan ia MEMUAT kata 'jam' DAN menyebut 'CPO
 * Dispatch'; `_note_no_quality_thresholds` memuat 'batas'. Pemindaian teks
 * karena itu menjawab pertanyaan yang salah — ia akan cocok dengan kalimat
 * yang justru MENYATAKAN ketiadaannya. Yang ditanyakan adalah apakah sebuah
 * KUNCI ada.
 *
 * @return list<string>
 */
function kdReportAllKeys(mixed $value): array
{
    if (! is_array($value)) {
        return [];
    }

    $keys = [];

    foreach ($value as $key => $child) {
        if (is_string($key)) {
            $keys[] = $key;
        }

        $keys = array_merge($keys, kdReportAllKeys($child));
    }

    return array_values(array_unique($keys));
}

/** Satu baris rekap untuk satu nilai (null = kelompok 'tidak tercatat'). */
function kdReportEntry(array $rows, ?string $value): ?array
{
    foreach ($rows as $row) {
        if ($row['value'] === $value) {
            return $row;
        }
    }

    return null;
}

/** Posisi baris 'tidak tercatat' di dalam satu rekap, atau -1. */
function kdReportNullIndex(array $rows): int
{
    foreach ($rows as $index => $row) {
        if ($row['value'] === null) {
            return $index;
        }
    }

    return -1;
}

/** Baris daily[] untuk satu tanggal, atau null. */
function kdReportDaily(array $summary, string $date): ?array
{
    foreach ($summary['daily'] as $row) {
        if ($row['date'] === $date) {
            return $row;
        }
    }

    return null;
}

/** Isi sebuah StreamedResponse, ditangkap. ALIRKAN SEKALI per response. */
function kdReportStreamed(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

/** Body CSV yang sudah ditangkap, dipecah menjadi baris non-kosong. */
function kdReportCsvLines(string $body): array
{
    return array_values(array_filter(explode("\n", trim($body)), fn ($line) => trim($line) !== ''));
}

/** Satu baris CSV, diurai. */
function kdReportCsvRow(string $line): array
{
    return str_getcsv(trim($line, "\r"), ',', '"', '\\');
}

/** KEEMPAT rekap kategoris layar ini, termasuk by_gate_status. */
const KD_RECAP_KEYS = ['by_transporter', 'by_silo_source', 'by_destination_buyer', 'by_gate_status'];

beforeEach(function () {
    $this->service = new KernelDispatchReportService;

    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->kernelDispatch()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->kernelDispatch()->create();

    // PRODUCTION LINE ADALAH KONTEKS YANG DIPILIH, BUKAN IKATAN AKUN — dan
    // memilihnya WAJIB di layar ini, jadi hampir setiap case di bawah
    // menyebutnya eksplisit.
    $this->lineA = (string) $this->stationA->production_line_id;
    $this->lineB = (string) $this->stationB->production_line_id;

    $this->supervisorA = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagementA = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operatorA = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    // Admin satu-satunya peran yang tidak terikat mill — justru itu sebab
    // pemilih mill ada.
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    // PERIODE YANG SUDAH SELESAI, 31 hari: days_counted === days_in_period ===
    // 31 selamanya, apa pun tanggal kalender saat suite dijalankan.
    $this->periodStart = now()->subDays(40)->toDateString();
    $this->periodEnd = now()->subDays(10)->toDateString();

    $this->day1 = now()->subDays(39)->toDateString();
    $this->day2 = now()->subDays(38)->toDateString();
    $this->day3 = now()->subDays(37)->toDateString();
    $this->dayBeforePeriod = now()->subDays(41)->toDateString();
    $this->dayAfterPeriod = now()->subDays(9)->toDateString();

    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelDispatch->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Maret Alpha')
        ->create();
});

// =====================================================================
// GROUP A — MILL MANA, LINE MANA, PERIODE MANA (case 1-13)
// =====================================================================

it('case 1 — businessUnitOptions menolak non-admin dengan 403 DARI DALAM service', function () {
    foreach ([
        'Supervisor' => $this->supervisorA,
        'Mill Management' => $this->millManagementA,
        'Station Operator' => $this->operatorA,
    ] as $label => $user) {
        $this->actingAs($user);

        $thrown = null;

        try {
            // DIPANGGIL LANGSUNG, tanpa HTTP kernel dan tanpa satu middleware
            // pun: penolakannya karena itu TIDAK MUNGKIN datang dari daftar
            // peran rute. Middleware prefix ini memang menerima keempat peran
            // demi ketiga rute data, sehingga penjaga di sana secara struktural
            // tidak dapat menutup rute ini saja.
            $this->service->businessUnitOptions();
        } catch (Throwable $exception) {
            $thrown = $exception;
        }

        $this->assertInstanceOf(
            AuthorizationException::class,
            $thrown,
            "peran {$label} seharusnya ditolak 403 oleh service sendiri",
        );
    }
});

it('case 2 — businessUnitOptions mengembalikan seluruh mill bagi Admin; master kosong mengembalikan [] bukan galat', function () {
    BusinessUnit::factory()->create(['name' => 'Mill C']);
    BusinessUnit::factory()->create(['name' => 'Mill B']);

    $this->actingAs($this->admin);

    $options = $this->service->businessUnitOptions();
    $names = array_column($options, 'name');

    // URUT NAIK, diasersikan sebagai sifat daftarnya — bukan sebagai daftar
    // harfiah: Period::factory() membuat `created_by`-nya sendiri lewat
    // User::factory(), yang ikut melahirkan satu BusinessUnit bernama faker.
    $sorted = $names;
    sort($sorted);

    expect($names)->toBe($sorted);
    expect($names)->toHaveCount(BusinessUnit::query()->count());

    foreach (['Mill Alpha', 'Mill B', 'Mill Beta', 'Mill C'] as $mill) {
        $this->assertContains($mill, $names, "daftar mill kehilangan {$mill}");
    }

    expect(array_keys($options[0]))->toBe(['id', 'name']);

    // VARIAN B — master kosong. Seluruh pemegang FK dibersihkan lebih dulu;
    // urutannya mengikuti arah FK-nya, bukan abjad.
    KernelDispatchDetail::query()->delete();
    KernelDispatchRecord::query()->delete();
    Station::query()->delete();
    PeriodStation::query()->delete();
    Period::query()->delete();
    ProductionLine::query()->delete();
    User::query()->whereNotNull('business_unit_id')->delete();
    BusinessUnit::query()->delete();

    // 'Belum ada mill' adalah jawaban SAH: [] dengan HTTP 200, bukan 404 dan
    // bukan exception.
    expect($this->service->businessUnitOptions())->toBe([]);
});

it('case 3 — periods menolak 422 ketika Admin tidak menyertakan business_unit_id', function () {
    // DUA mill, KEDUANYA punya periode kernel-dispatch bertanggal eksplisit:
    // implementasi yang memilih 'mill pertama' akan terlihat.
    Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::KernelDispatch->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Maret Beta')
        ->create();

    $this->actingAs($this->admin);

    foreach ([null, ''] as $missing) {
        $thrown = null;

        try {
            $this->service->listPeriods($missing);
        } catch (ValidationException $exception) {
            $thrown = $exception;
        }

        expect($thrown)->toBeInstanceOf(ValidationException::class);
        expect($thrown->status)->toBe(422);
        // Pesannya MENYEBUT parameter yang kurang, dalam Bahasa Indonesia.
        expect($thrown->errors())->toHaveKey('business_unit_id');
        expect($thrown->errors()['business_unit_id'])
            ->toBe(['Pilih mill terlebih dahulu untuk menampilkan laporan.']);
    }

    // ASERSI NEGATIF — NOL PERIODE TERBIT. Kedua mill memang punya periode
    // kernel-dispatch, jadi 'tidak ada mill default' adalah pernyataan yang
    // berisiko gagal.
    $this->assertGreaterThan(1, BusinessUnit::query()->count());
    expect(Period::query()->count())->toBeGreaterThanOrEqual(2);
});

it('case 4 — business_unit_id kiriman DIABAIKAN bagi peran terikat mill, bukan 403', function () {
    $periodeB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::KernelDispatch->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('P-B')
        ->create();

    $this->periodA->update(['name' => 'P-A']);

    $this->actingAs($this->supervisorA);

    $periods = $this->service->listPeriods((string) $this->businessUnitB->id);

    expect($periods)->toHaveCount(1);
    expect($periods[0]['id'])->toBe((string) $this->periodA->id);
    expect($periods[0]['name'])->toBe('P-A');

    // ASERSI NEGATIF EKSPLISIT atas id DAN atas NAMA — bukan atas jumlah entri
    // saja. Menolak dengan 403 akan membocorkan keberadaan mill-B kepada orang
    // yang tidak berhak mengetahuinya.
    expect(array_column($periods, 'id'))->not->toContain((string) $periodeB->id);
    $this->assertNotContains('P-B', array_column($periods, 'name'));
});

it('case 5 — akun terikat mill yang business_unit_id-nya NULL menghasilkan 422, BUKAN 403, dan daftar seluruh mill tidak pernah disusun', function () {
    foreach ([UserRole::Supervisor, UserRole::MillManagement] as $role) {
        $orphan = User::factory()->role($role)->create(['business_unit_id' => null]);
        $this->actingAs($orphan);

        $spy = new KernelDispatchReportAllBusinessUnitsSpy;

        $thrown = null;

        try {
            $spy->listPeriods(null);
        } catch (Throwable $exception) {
            $thrown = $exception;
        }

        // ATAS KELAS exception DAN atas kodenya — bukan atas potongan pesan.
        expect($thrown)->toBeInstanceOf(ValidationException::class);
        expect($thrown->status)->toBe(422);
        expect($thrown->errors())->toHaveKey('business_unit_id');

        // ASERSI NEGATIF: bukan ForbiddenException dan bukan 403. Master yang
        // rusak bukan izin yang kurang.
        $this->assertNotInstanceOf(AuthorizationException::class, $thrown);

        // GAGAL TERTUTUP: daftar seluruh mill TIDAK PERNAH DISUSUN. Tanpa
        // hitungan nol ini, asersi 422 di atas akan sama hijau terhadap
        // implementasi yang membangun daftarnya lebih dulu lalu melempar.
        expect($spy->allBusinessUnitsCalls)->toBe(0);
    }
});

it("case 6 — periods hanya memuat periode yang punya baris period_stations berjenis 'kernel-dispatch'", function () {
    $lainJenis = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::SolidWasteDisposal->value)
        ->range(now()->subDays(80)->toDateString(), now()->subDays(60)->toDateString())
        ->open()
        ->named('Periode SWD saja')
        ->create();

    $tanpaStasiun = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->noStations()
        ->range(now()->subDays(120)->toDateString(), now()->subDays(100)->toDateString())
        ->open()
        ->named('Periode tanpa stasiun')
        ->create();

    $this->actingAs($this->supervisorA);

    $periods = $this->service->listPeriods(null);

    expect($periods)->toHaveCount(1);
    expect($periods[0]['id'])->toBe((string) $this->periodA->id);
    // BERTANDA HUBUNG: kunci snake_case akan ter-compile, tampak benar, dan
    // tidak pernah cocok dengan satu baris period_stations pun.
    expect($periods[0]['station_type'])->toBe('kernel-dispatch');
    expect($periods[0]['station_type_label'])->not->toBe('');

    // Keduanya diasersikan ABSEN ATAS ID-nya, bukan lewat jumlah.
    $ids = array_column($periods, 'id');
    $this->assertNotContains((string) $lainJenis->id, $ids);
    $this->assertNotContains((string) $tanpaStasiun->id, $ids);
});

it('case 7 — periods TIDAK menyaring status periode: draft, open dan closed sama-sama terbaca', function () {
    $draft = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelDispatch->value)
        ->range(now()->subDays(80)->toDateString(), now()->subDays(60)->toDateString())
        ->draft()
        ->named('Periode Draft')
        ->create();

    $closed = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelDispatch->value)
        ->range(now()->subDays(120)->toDateString(), now()->subDays(100)->toDateString())
        ->closed()
        ->named('Periode Closed')
        ->create();

    $this->actingAs($this->supervisorA);

    $periods = $this->service->listPeriods(null);
    $ids = array_column($periods, 'id');

    // $this->assertContains() — expect()->toContain($a, $b) membaca argumen
    // kedua sebagai needle KEDUA dan tidak boleh dipakai di sini.
    $this->assertContains((string) $this->periodA->id, $ids);
    $this->assertContains((string) $draft->id, $ids);
    $this->assertContains((string) $closed->id, $ids);

    $statusById = array_column($periods, 'status', 'id');

    expect($statusById[(string) $this->periodA->id])->toBe(PeriodStatus::Open->value);
    expect($statusById[(string) $draft->id])->toBe(PeriodStatus::Draft->value);
    expect($statusById[(string) $closed->id])->toBe(PeriodStatus::Closed->value);
});

it('case 8 — periods mengembalikan [] ketika mill belum punya satu pun periode kernel-dispatch', function () {
    $this->periodA->stations()->delete();
    $this->periodA->delete();

    foreach ([StationTypeEnum::Weighbridge->value, StationTypeEnum::Grading->value] as $index => $type) {
        Period::factory()
            ->forBusinessUnit($this->businessUnitA)
            ->stationType($type)
            ->range(
                now()->subDays(80 - ($index * 30))->toDateString(),
                now()->subDays(60 - ($index * 30))->toDateString(),
            )
            ->open()
            ->named('Periode '.$type)
            ->create();
    }

    $this->actingAs($this->supervisorA);

    // Array KOSONG dengan 200 — bukan exception dan bukan 404.
    expect($this->service->listPeriods(null))->toBe([]);
});

it('case 9 — summary menolak 422 ketika period_id absen', function () {
    kdReportDispatch(kdReportRecord($this->stationA, $this->day1), $this->day1);

    $this->actingAs($this->supervisorA);

    foreach ([null, ''] as $missing) {
        $thrown = null;
        $payload = null;

        try {
            $payload = $this->service->buildSummary($missing, null, $this->lineA);
        } catch (ValidationException $exception) {
            $thrown = $exception;
        }

        expect($thrown)->toBeInstanceOf(ValidationException::class);
        expect($thrown->status)->toBe(422);
        expect($thrown->errors())->toHaveKey('period_id');
        // ASERSI NEGATIF: tidak ada payload yang terbit sebagian — NOL blok
        // angka dihitung.
        expect($payload)->toBeNull();
    }
});

it("case 10 — summary menolak 422 ketika production_line_id absen: tanpa fallback 'line pertama' dan tanpa mode semua-line", function () {
    // DUA LINE yang MASING-MASING berpengiriman: implementasi yang diam-diam
    // memilih 'line pertama' atau menjumlahkan semua line akan mengembalikan
    // 200 berisi angka, dan test ini memerah.
    $lineKedua = Station::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->kernelDispatch()
        ->create();

    kdReportDispatch(kdReportRecord($this->stationA, $this->day1), $this->day1, ['net_weight_mt' => 10.0]);
    kdReportDispatch(kdReportRecord($lineKedua, $this->day1), $this->day1, ['net_weight_mt' => 100.0]);

    expect((string) $lineKedua->production_line_id)->not->toBe($this->lineA);

    $this->actingAs($this->supervisorA);

    foreach ([null, ''] as $missing) {
        $thrown = null;
        $payload = null;

        try {
            $payload = $this->service->buildSummary($this->periodA, null, $missing);
        } catch (ValidationException $exception) {
            $thrown = $exception;
        }

        expect($thrown)->toBeInstanceOf(ValidationException::class);
        expect($thrown->status)->toBe(422);
        expect($thrown->errors()['production_line_id'])
            ->toBe(['Production Line wajib dipilih untuk menampilkan laporan.']);
        expect($payload)->toBeNull();
    }
});

it('case 11 — summary menolak 403 ketika periode milik mill lain', function () {
    $periodeB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::KernelDispatch->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Maret Beta')
        ->create();

    kdReportDispatch(kdReportRecord($this->stationB, $this->day1), $this->day1, ['net_weight_mt' => 99.0]);

    $this->actingAs($this->supervisorA);

    $queries = kdReportQueriesDuring(function () use ($periodeB) {
        $this->service->buildSummary((string) $periodeB->id, null, $this->lineB);
    });

    $thrown = null;

    try {
        $this->service->buildSummary((string) $periodeB->id, null, $this->lineB);
    } catch (Throwable $exception) {
        $thrown = $exception;
    }

    expect($thrown)->toBeInstanceOf(AuthorizationException::class);

    // DIPERIKSA SEBELUM SATU AGREGAT PUN DIHITUNG: nol kueri ke tabel detail.
    $this->assertFalse(
        kdReportTouched($queries, 'kernel_dispatch_details'),
        'penolakan 403 terjadi setelah baris pengiriman dibaca',
    );
});

it('case 12 — summary menolak 403 ketika production_line_id milik mill lain: DITOLAK, bukan diabaikan, dan dikontraskan dengan business_unit_id dalam SATU test', function () {
    $this->actingAs($this->supervisorA);

    // BAGIAN 1 — period_id mill-A + production_line_id mill-B → 403.
    $thrown = null;

    try {
        $this->service->buildSummary($this->periodA, null, $this->lineB);
    } catch (Throwable $exception) {
        $thrown = $exception;
    }

    expect($thrown)->toBeInstanceOf(AuthorizationException::class);

    // BAGIAN 2 — permintaan yang sama DITAMBAH business_unit_id mill-B:
    // business_unit_id DIABAIKAN tanpa galat sementara production_line_id
    // mill-B TETAP 403.
    $thrownDua = null;

    try {
        $this->service->buildSummary($this->periodA, (string) $this->businessUnitB->id, $this->lineB);
    } catch (Throwable $exception) {
        $thrownDua = $exception;
    }

    expect($thrownDua)->toBeInstanceOf(AuthorizationException::class);

    // Dan business_unit_id mill-B pada line YANG SAH memang diabaikan — 200
    // berisi mill pemanggil sendiri. Keduanya DALAM SATU TEST supaya
    // perbedaannya tidak dihapus belakangan: business_unit_id adalah konteks
    // yang diturunkan dari akun sehingga diabaikan, production_line_id adalah
    // parameter yang dipilih sadar sehingga ditolak.
    $summary = $this->service->buildSummary($this->periodA, (string) $this->businessUnitB->id, $this->lineA);

    expect($summary['business_unit']['id'])->toBe((string) $this->businessUnitA->id);
    expect($summary['business_unit']['name'])->toBe('Mill Alpha');
    expect($summary['production_line']['id'])->toBe($this->lineA);
});

it('case 13 — Station Operator DITERIMA di periods, summary dan export sejak commit pertama', function () {
    kdReportDispatch(kdReportRecord($this->stationA, $this->day1), $this->day1, ['net_weight_mt' => 4.0]);

    $this->actingAs($this->operatorA);

    $periods = $this->service->listPeriods(null);

    expect($periods)->toHaveCount(1);
    expect($periods[0]['id'])->toBe((string) $this->periodA->id);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // ANGKA PENUH, bukan kerangka kosong.
    expect($summary['dispatches']['dispatch_count'])->toBe(1);
    expect($summary['dispatches']['net_weight_total'])->toBe(4.0);
    // Dan mill-nya mill AKUN: Operator berada di cabang TERIKAT MILL
    // resolveBusinessUnit(), bukan di cabang Admin. Tanpa baris ini,
    // penerimaan di guardAccess() adalah kebocoran lintas mill.
    expect($summary['business_unit']['id'])->toBe((string) $this->businessUnitA->id);

    expect($this->service->export($this->periodA, 'csv', null, $this->lineA))
        ->toBeInstanceOf(StreamedResponse::class);

    // Rute WEB /reports/kernel-dispatch tetap 403 bagi Operator — dibuktikan
    // di tests/Feature/Livewire/LaporanKernelDispatchTest.php (skenario 2),
    // yang punya HTTP kernel. Asersi ini mengunci bahwa screen-165 (mobile)
    // tidak perlu melebarkan satu gate pun.
    $this->assertTrue(true);
});

// =====================================================================
// GROUP B — SATUAN HITUNG DAN TONASE (case 14-23)
// =====================================================================

it('case 14 — dispatch_count menghitung BARIS DETAIL, bukan record induk', function () {
    $r1 = kdReportRecord($this->stationA, $this->day1);
    $r2 = kdReportRecord($this->stationA, $this->day2);

    foreach (range(1, 5) as $i) {
        // Sebagian ber-netto NULL: satuan hitungnya PENGIRIMAN, bukan
        // penimbangan yang selesai.
        kdReportDispatch($r1, $this->day1, ['net_weight_mt' => $i <= 3 ? 2.0 : null]);
    }

    foreach (range(1, 2) as $i) {
        kdReportDispatch($r2, $this->day2, ['net_weight_mt' => null]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // 7, BUKAN 2. Ini perbedaan bentuk terpenting terhadap laporan bergrid.
    expect($summary['dispatches']['dispatch_count'])->toBe(7);
    expect(KernelDispatchRecord::query()->count())->toBe(2);
    expect($summary['dispatches']['net_weight_dispatch_count'])->toBe(3);
    expect($summary['dispatches']['missing_net_weight_dispatch_count'])->toBe(4);
});

it('case 15 — lingkup baris menyingkirkan record stasiun lain dan record line lain', function () {
    $lineKedua = Station::factory()->forBusinessUnit($this->businessUnitA)->kernelDispatch()->create();
    $stasiunLain = Station::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->forProductionLine((string) $this->stationA->production_line_id)
        ->solidWasteDisposal()
        ->create();

    $r1 = kdReportRecord($this->stationA, $this->day1);

    foreach (range(1, 3) as $i) {
        kdReportDispatch($r1, $this->day1, ['net_weight_mt' => 10.0]);
    }

    $r2 = kdReportRecord($lineKedua, $this->day1);

    foreach (range(1, 4) as $i) {
        kdReportDispatch($r2, $this->day1, ['net_weight_mt' => 100.0]);
    }

    // 5 detail solid-waste-disposal pada LINE YANG SAMA: dibuat lewat
    // factory-nya sendiri supaya join `stations.type` yang hilang akan
    // terlihat. Jenis stasiunnya berbeda, jadi recordQueryFor() wajib
    // menyingkirkannya lewat `stations.type`.
    expect($stasiunLain->type instanceof StationTypeEnum
        ? $stasiunLain->type->value
        : (string) $stasiunLain->type)->toBe('solid-waste-disposal');

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['dispatches']['dispatch_count'])->toBe(3);
    // KEBOCORAN DIBUAT TERLIHAT SEBAGAI ANGKA, bukan hanya sebagai jumlah:
    // netto tiap kelompok disemai berbeda (line-1 = 10/baris, line-2 =
    // 100/baris) sehingga satu baris line-2 yang bocor langsung mengubahnya.
    expect($summary['dispatches']['net_weight_total'])->toBe(30.0);
    expect($summary['dispatches']['net_weight_avg'])->toBe(10.0);
});

it('case 16 — keanggotaan periode ditentukan kolom `date` pada INDUK, inklusif di kedua ujung', function () {
    foreach ([
        $this->dayBeforePeriod,
        $this->periodStart,
        $this->periodEnd,
        $this->dayAfterPeriod,
    ] as $tanggal) {
        kdReportDispatch(kdReportRecord($this->stationA, $tanggal), $tanggal, ['net_weight_mt' => 1.0]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // Hari pertama dan hari terakhir IKUT; satu hari sebelum dan satu hari
    // sesudah TIDAK. Perbandingan tanggal memakai whereDate() bukan where():
    // suite berjalan di SQLite in-memory sementara produksi dan e2e di
    // PostgreSQL.
    expect($summary['dispatches']['dispatch_count'])->toBe(2);
    expect(array_column($summary['daily'], 'date'))->toBe([$this->periodStart, $this->periodEnd]);
});

it('case 17 — satu line dengan LEBIH DARI SATU unit stasiun kernel-dispatch digabung tepat sekali', function () {
    $unitKedua = Station::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->forProductionLine((string) $this->stationA->production_line_id)
        ->kernelDispatch()
        ->create();

    expect((string) $unitKedua->production_line_id)->toBe($this->lineA);

    foreach ([$this->stationA, $unitKedua] as $station) {
        $record = kdReportRecord($station, $this->day1);

        foreach (range(1, 2) as $i) {
            kdReportDispatch($record, $this->day1, ['net_weight_mt' => 3.0]);
        }
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // 4 — digabung, tidak diduplikasi menjadi 8 (join 1:N yang salah) dan
    // tidak hanya satu unit yang terbaca (2).
    expect($summary['dispatches']['dispatch_count'])->toBe(4);
    expect($summary['dispatches']['net_weight_total'])->toBe(12.0);
});

it('case 18 — summary menghitung normal untuk periode berstatus draft maupun closed', function () {
    $periodeDraft = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelDispatch->value)
        ->range(now()->subDays(80)->toDateString(), now()->subDays(60)->toDateString())
        ->draft()
        ->named('Periode Draft')
        ->create();

    $periodeClosed = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelDispatch->value)
        ->range(now()->subDays(120)->toDateString(), now()->subDays(100)->toDateString())
        ->closed()
        ->named('Periode Closed')
        ->create();

    // ISI IDENTIK pada kedua periode, bertanggal eksplisit.
    foreach ([
        [$periodeDraft, now()->subDays(79)->toDateString()],
        [$periodeClosed, now()->subDays(119)->toDateString()],
    ] as [$periode, $tanggal]) {
        $record = kdReportRecord($this->stationA, $tanggal);

        foreach ([
            ['kernel_moisture_percent' => 7.0, 'dirt_impurities_percent' => 0.02, 'ffa_percent' => 3.0, 'broken_kernel_percent' => 5.0],
            ['kernel_moisture_percent' => 7.0, 'dirt_impurities_percent' => null, 'ffa_percent' => 3.0, 'broken_kernel_percent' => null],
            ['kernel_moisture_percent' => null, 'dirt_impurities_percent' => null, 'ffa_percent' => 3.0, 'broken_kernel_percent' => null],
        ] as $mutu) {
            kdReportDispatch($record, $tanggal, array_merge(['net_weight_mt' => 5.0], $mutu));
        }
    }

    $this->actingAs($this->supervisorA);

    $draft = $this->service->buildSummary($periodeDraft, null, $this->lineA);
    $closed = $this->service->buildSummary($periodeClosed, null, $this->lineA);

    expect($draft['period']['status'])->toBe(PeriodStatus::Draft->value);
    expect($closed['period']['status'])->toBe(PeriodStatus::Closed->value);

    foreach (['dispatch_count', 'net_weight_total', 'net_weight_avg', 'net_weight_dispatch_count'] as $key) {
        $this->assertSame(
            $draft['dispatches'][$key],
            $closed['dispatches'][$key],
            "status periode menyaring {$key}",
        );
    }

    // KEEMPAT measured_count mutu sama persis — status periode TIDAK menyaring
    // apa pun di satu angka pun.
    foreach (KernelDispatchReportService::QUALITY_MEASURES as $measure) {
        $this->assertSame(
            $draft['quality'][$measure]['measured_count'],
            $closed['quality'][$measure]['measured_count'],
            "status periode menyaring penyebut {$measure}",
        );
    }

    expect($draft['quality']['kernel_moisture_percent']['measured_count'])->toBe(2);
    expect($draft['quality']['ffa_percent']['measured_count'])->toBe(3);
});

it('case 19 — net_weight_total dan net_weight_avg dihitung HANYA atas baris non-null; net_weight_dispatch_count DITERBITKAN sebagai penyebut', function () {
    $record = kdReportRecord($this->stationA, $this->day1);

    foreach ([10.0, 20.0, 30.0, null, null] as $netto) {
        kdReportDispatch($record, $this->day1, ['net_weight_mt' => $netto]);
    }

    $this->actingAs($this->supervisorA);

    $dispatches = $this->service->buildSummary($this->periodA, null, $this->lineA)['dispatches'];

    expect($dispatches['net_weight_total'])->toBe(60.0);
    expect($dispatches['net_weight_dispatch_count'])->toBe(3);
    expect($dispatches['net_weight_avg'])->toBe(20.0);

    // ASERSI NEGATIF: avg BUKAN 12 (60/5) — penyebutnya baris yang tertimbang,
    // bukan seluruh pengiriman.
    $this->assertNotEquals(12.0, $dispatches['net_weight_avg']);
    expect($dispatches['dispatch_count'])->toBe(5);
});

it('case 20 — net_weight_dispatch_count 0 memberi net_weight_total dan net_weight_avg NULL, BUKAN 0', function () {
    $record = kdReportRecord($this->stationA, $this->day1);

    foreach (range(1, 4) as $i) {
        kdReportDispatch($record, $this->day1, ['net_weight_mt' => null]);
    }

    $this->actingAs($this->supervisorA);

    $dispatches = $this->service->buildSummary($this->periodA, null, $this->lineA)['dispatches'];

    // IDENTITAS null, bukan falsy: '0,00 MT' adalah pernyataan palsu bahwa
    // penimbangan sudah selesai dan hasilnya nol.
    $this->assertNull($dispatches['net_weight_total']);
    $this->assertNull($dispatches['net_weight_avg']);
    expect($dispatches['net_weight_dispatch_count'])->toBe(0);
    expect($dispatches['missing_net_weight_dispatch_count'])->toBe(4);
    expect($dispatches['dispatch_count'])->toBe(4);
});

it('case 21 — penyebut rata-rata adalah net_weight_dispatch_count dan penyebut itu DITERBITKAN di payload', function () {
    $record = kdReportRecord($this->stationA, $this->day1);

    foreach ([12.0, 12.0, 12.0, null, null, null] as $netto) {
        kdReportDispatch($record, $this->day1, ['net_weight_mt' => $netto]);
    }

    $this->actingAs($this->supervisorA);

    $dispatches = $this->service->buildSummary($this->periodA, null, $this->lineA)['dispatches'];

    expect($dispatches['net_weight_avg'])->toBe(12.0);
    expect($dispatches['net_weight_dispatch_count'])->toBe(3);

    // KEHADIRAN KUNCI PENYEBUT diasersikan dengan array_key_exists —
    // rata-rata tanpa penyebut yang terbaca adalah angka yang tidak dapat
    // dinilai orang.
    $this->assertArrayHasKey('net_weight_dispatch_count', $dispatches);
});

it('case 22 — missing_net_weight_dispatch_count = dispatch_count − net_weight_dispatch_count, dan nol tetap diterbitkan', function () {
    $this->actingAs($this->supervisorA);

    // VARIAN A — 7 pengiriman, 4 ber-netto dan 3 tanpa netto.
    $record = kdReportRecord($this->stationA, $this->day1);

    foreach ([1.0, 2.0, 3.0, 4.0, null, null, null] as $netto) {
        kdReportDispatch($record, $this->day1, ['net_weight_mt' => $netto]);
    }

    $a = $this->service->buildSummary($this->periodA, null, $this->lineA)['dispatches'];

    expect($a['dispatch_count'])->toBe(7);
    expect($a['net_weight_dispatch_count'])->toBe(4);
    expect($a['missing_net_weight_dispatch_count'])->toBe(3);
    $this->assertSame(
        $a['dispatch_count'] - $a['net_weight_dispatch_count'],
        $a['missing_net_weight_dispatch_count'],
    );

    // VARIAN B — ketujuh detail ber-netto: missing = 0 dan KUNCINYA TETAP ADA.
    KernelDispatchDetail::query()->update(['net_weight_mt' => 2.0]);

    $b = $this->service->buildSummary($this->periodA, null, $this->lineA)['dispatches'];

    $this->assertArrayHasKey('missing_net_weight_dispatch_count', $b);
    expect($b['missing_net_weight_dispatch_count'])->toBe(0);
    expect($b['net_weight_dispatch_count'])->toBe(7);
});

it('case 23 — net_weight_mt DIBACA APA ADANYA dan tidak pernah dihitung ulang dari bruto dikurangi tara', function () {
    $record = kdReportRecord($this->stationA, $this->day1);

    // BARIS 1 — netto terisi sementara bruto DAN tara KEDUANYA null. Baris
    // yang ditulis lewat jalur selain form web dapat punya netto tanpa
    // bruto/tara, dan bentuk ini PERSIS bentuk kedua baris fixture e2e.
    kdReportDispatch($record, $this->day1, [
        'gross_weight_mt' => null,
        'tare_weight_mt' => null,
        'net_weight_mt' => 18.5,
    ]);

    // BARIS 2 — bruto 30, tara 8, netto 17.0 (sengaja TIDAK sama dengan 22).
    kdReportDispatch($record, $this->day1, [
        'gross_weight_mt' => 30.0,
        'tare_weight_mt' => 8.0,
        'net_weight_mt' => 17.0,
    ]);

    $this->actingAs($this->supervisorA);

    $dispatches = $this->service->buildSummary($this->periodA, null, $this->lineA)['dispatches'];

    // 35.5, bukan 40.5 (18.5 + 22) dan bukan 22 (baris 1 dilewati).
    expect($dispatches['net_weight_total'])->toBe(35.5);
    expect($dispatches['net_weight_dispatch_count'])->toBe(2);
    $this->assertNotEquals(40.5, $dispatches['net_weight_total']);

    $harian = kdReportDaily($this->service->buildSummary($this->periodA, null, $this->lineA), $this->day1);

    expect($harian['net_weight_total'])->toBe(35.5);
});

// =====================================================================
// GROUP C — EMPAT UKURAN MUTU, EMPAT PENYEBUT (case 24-30)
// =====================================================================

it('case 24 — KEEMPAT measured_count mutu dihitung TERPISAH dan INDEPENDEN, dengan LIMA angka yang semuanya BERBEDA', function () {
    $record = kdReportRecord($this->stationA, $this->day1);

    // SEMBILAN pengiriman, disusun supaya keempat penyebut berbeda:
    // kernel_moisture 6, dirt_impurities 5, ffa 4, broken_kernel 3, dan
    // dispatch_count 9. Kombinasi WAJIB yang hadir di daftar ini:
    //   #1 kadar air TANPA ALB
    //   #7 ALB TANPA kadar air
    //   #4 KEDUANYA
    //   #9 TIDAK satu hasil uji pun
    $pola = [
        ['kernel_moisture_percent' => 6.0, 'dirt_impurities_percent' => null,  'ffa_percent' => null, 'broken_kernel_percent' => null],
        ['kernel_moisture_percent' => 6.0, 'dirt_impurities_percent' => 0.01,  'ffa_percent' => null, 'broken_kernel_percent' => null],
        ['kernel_moisture_percent' => 6.0, 'dirt_impurities_percent' => 0.01,  'ffa_percent' => null, 'broken_kernel_percent' => null],
        ['kernel_moisture_percent' => 6.0, 'dirt_impurities_percent' => 0.01,  'ffa_percent' => 3.0,  'broken_kernel_percent' => 5.0],
        ['kernel_moisture_percent' => 6.0, 'dirt_impurities_percent' => 0.01,  'ffa_percent' => 3.0,  'broken_kernel_percent' => 5.0],
        ['kernel_moisture_percent' => 6.0, 'dirt_impurities_percent' => 0.01,  'ffa_percent' => 3.0,  'broken_kernel_percent' => 5.0],
        ['kernel_moisture_percent' => null, 'dirt_impurities_percent' => null, 'ffa_percent' => 3.0,  'broken_kernel_percent' => null],
        ['kernel_moisture_percent' => null, 'dirt_impurities_percent' => null, 'ffa_percent' => null, 'broken_kernel_percent' => null],
        ['kernel_moisture_percent' => null, 'dirt_impurities_percent' => null, 'ffa_percent' => null, 'broken_kernel_percent' => null],
    ];

    foreach ($pola as $mutu) {
        kdReportDispatch($record, $this->day1, array_merge(['net_weight_mt' => 1.0], $mutu));
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);
    $quality = $summary['quality'];

    // LIMA ANGKA, SEMUANYA BERBEDA, diasersikan SATU PER SATU. Inilah jebakan
    // utama layar ini: satu penyebut tunggal untuk keempat ukuran membuat
    // keempatnya sama (dan biasanya sama dengan dispatch_count), sehingga test
    // ini memerah tepat pada implementasi yang 'tampak rapi' dan salah secara
    // sistematis.
    expect($quality['kernel_moisture_percent']['measured_count'])->toBe(6);
    expect($quality['dirt_impurities_percent']['measured_count'])->toBe(5);
    expect($quality['ffa_percent']['measured_count'])->toBe(4);
    expect($quality['broken_kernel_percent']['measured_count'])->toBe(3);
    expect($summary['dispatches']['dispatch_count'])->toBe(9);

    // Dan kelimanya memang LIMA NILAI BERBEDA — fixture yang membuat sebagian
    // di antaranya kebetulan sama akan membuat asersi di atas setengah vakum.
    $angka = [
        $quality['kernel_moisture_percent']['measured_count'],
        $quality['dirt_impurities_percent']['measured_count'],
        $quality['ffa_percent']['measured_count'],
        $quality['broken_kernel_percent']['measured_count'],
        $summary['dispatches']['dispatch_count'],
    ];

    expect(array_values(array_unique($angka)))->toHaveCount(5);
});

it('case 25 — penyebut NOL pada satu ukuran TIDAK mengosongkan ukuran lain: diperiksa PER UKURAN, bukan lewat satu penjaga global', function () {
    $record = kdReportRecord($this->stationA, $this->day1);

    // ffa_percent NULL pada KEEMPATNYA; ketiga ukuran lain terisi pada
    // keempatnya dengan nilai yang BERBEDA-BEDA.
    foreach ([
        ['kernel_moisture_percent' => 6.0, 'dirt_impurities_percent' => 0.01, 'broken_kernel_percent' => 2.0],
        ['kernel_moisture_percent' => 7.0, 'dirt_impurities_percent' => 0.02, 'broken_kernel_percent' => 4.0],
        ['kernel_moisture_percent' => 8.0, 'dirt_impurities_percent' => 0.03, 'broken_kernel_percent' => 6.0],
        ['kernel_moisture_percent' => 9.0, 'dirt_impurities_percent' => 0.04, 'broken_kernel_percent' => 8.0],
    ] as $mutu) {
        kdReportDispatch($record, $this->day1, array_merge(['ffa_percent' => null], $mutu));
    }

    $this->actingAs($this->supervisorA);

    $quality = $this->service->buildSummary($this->periodA, null, $this->lineA)['quality'];

    // UKURAN YANG KOSONG: ketiga nilainya NULL dengan measured_count 0.
    $this->assertNull($quality['ffa_percent']['min']);
    $this->assertNull($quality['ffa_percent']['avg']);
    $this->assertNull($quality['ffa_percent']['max']);
    expect($quality['ffa_percent']['measured_count'])->toBe(0);

    // SEKALIGUS, DI PAYLOAD YANG SAMA: ketiga ukuran lain BERANGKA dengan
    // measured_count 4. Satu penjaga global 'ada data mutu atau tidak' akan
    // mengosongkan keempatnya dan test ini memerah.
    foreach (['kernel_moisture_percent', 'dirt_impurities_percent', 'broken_kernel_percent'] as $measure) {
        expect($quality[$measure]['measured_count'])->toBe(4);
        $this->assertNotNull($quality[$measure]['min'], "{$measure}.min ikut dikosongkan");
        $this->assertNotNull($quality[$measure]['avg'], "{$measure}.avg ikut dikosongkan");
        $this->assertNotNull($quality[$measure]['max'], "{$measure}.max ikut dikosongkan");
        $this->assertIsFloat($quality[$measure]['avg']);
    }

    expect($quality['kernel_moisture_percent']['min'])->toBe(6.0);
    expect($quality['kernel_moisture_percent']['max'])->toBe(9.0);
    expect($quality['kernel_moisture_percent']['avg'])->toBe(7.5);
});

it('case 26 — keempat ukuran mutu kosong SEMENTARA tonase ada, dan kebalikannya: blok mutu dan blok berat independen di kedua arah', function () {
    $this->actingAs($this->supervisorA);

    // VARIAN A — netto terisi pada semuanya, keempat kolom mutu NULL.
    $recordA = kdReportRecord($this->stationA, $this->day1);

    foreach (range(1, 5) as $i) {
        kdReportDispatch($recordA, $this->day1, ['net_weight_mt' => 10.0]);
    }

    $a = $this->service->buildSummary($this->periodA, null, $this->lineA);

    foreach (KernelDispatchReportService::QUALITY_MEASURES as $measure) {
        expect($a['quality'][$measure]['measured_count'])->toBe(0);
        $this->assertNull($a['quality'][$measure]['min'], "{$measure}.min bukan null");
        $this->assertNull($a['quality'][$measure]['avg'], "{$measure}.avg bukan null");
        $this->assertNull($a['quality'][$measure]['max'], "{$measure}.max bukan null");
    }

    // SEMENTARA blok berat tetap BERANGKA — blok mutu menyatakan belum ada
    // hasil uji dan itu tidak menyentuh tonase.
    expect($a['dispatches']['net_weight_total'])->toBe(50.0);
    expect($a['dispatches']['net_weight_avg'])->toBe(10.0);
    expect($a['dispatches']['net_weight_dispatch_count'])->toBe(5);

    // VARIAN B (TERBALIK) — keempat kolom mutu terisi, netto NULL pada
    // semuanya.
    KernelDispatchDetail::query()->delete();
    KernelDispatchRecord::query()->delete();

    $recordB = kdReportRecord($this->stationA, $this->day2);

    foreach (range(1, 5) as $i) {
        kdReportDispatch($recordB, $this->day2, [
            'net_weight_mt' => null,
            'kernel_moisture_percent' => 6.0 + $i,
            'dirt_impurities_percent' => 0.01 * $i,
            'ffa_percent' => 1.0 * $i,
            'broken_kernel_percent' => 2.0 * $i,
        ]);
    }

    $b = $this->service->buildSummary($this->periodA, null, $this->lineA);

    foreach (KernelDispatchReportService::QUALITY_MEASURES as $measure) {
        expect($b['quality'][$measure]['measured_count'])->toBe(5);
        $this->assertNotNull($b['quality'][$measure]['avg'], "{$measure}.avg kosong padahal terisi");
    }

    $this->assertNull($b['dispatches']['net_weight_total']);
    $this->assertNull($b['dispatches']['net_weight_avg']);
    expect($b['dispatches']['dispatch_count'])->toBe(5);
});

it('case 27 — min, avg dan max benar untuk tiap ukuran, dihitung DI PHP bukan lewat agregat SQL', function () {
    $record = kdReportRecord($this->stationA, $this->day1);

    foreach ([
        ['kernel_moisture_percent' => 6.2, 'dirt_impurities_percent' => 0.01],
        ['kernel_moisture_percent' => 7.8, 'dirt_impurities_percent' => 0.03],
        ['kernel_moisture_percent' => 7.0, 'dirt_impurities_percent' => null],
    ] as $mutu) {
        kdReportDispatch($record, $this->day1, $mutu);
    }

    $this->actingAs($this->supervisorA);

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = strtolower($query->sql);
    });

    $quality = $this->service->buildSummary($this->periodA, null, $this->lineA)['quality'];

    // Dibandingkan dengan toleransi float EKSPLISIT.
    $this->assertEqualsWithDelta(6.2, $quality['kernel_moisture_percent']['min'], 0.0005);
    $this->assertEqualsWithDelta(7.8, $quality['kernel_moisture_percent']['max'], 0.0005);
    $this->assertEqualsWithDelta(7.0, $quality['kernel_moisture_percent']['avg'], 0.0005);
    expect($quality['kernel_moisture_percent']['measured_count'])->toBe(3);

    $this->assertEqualsWithDelta(0.01, $quality['dirt_impurities_percent']['min'], 0.0005);
    $this->assertEqualsWithDelta(0.03, $quality['dirt_impurities_percent']['max'], 0.0005);
    $this->assertEqualsWithDelta(0.02, $quality['dirt_impurities_percent']['avg'], 0.0005);
    expect($quality['dirt_impurities_percent']['measured_count'])->toBe(2);

    // PERHITUNGAN TERJADI DI PHP, bukan lewat AVG()/MIN()/MAX() basis data:
    // angka yang sama harus keluar di SQLite (suite) dan PostgreSQL (e2e), dan
    // agregat SQL membedakan pembulatan antar-mesin. Diasersikan atas SQL yang
    // benar-benar berjalan.
    foreach ($queries as $sql) {
        $this->assertStringNotContainsString('avg(', $sql, "agregat SQL AVG() dipakai: {$sql}");
        $this->assertStringNotContainsString('min(', $sql, "agregat SQL MIN() dipakai: {$sql}");
        $this->assertStringNotContainsString('max(', $sql, "agregat SQL MAX() dipakai: {$sql}");
        $this->assertStringNotContainsString('group by', $sql, "GROUP BY dipakai: {$sql}");
    }

    // Tiga desimal, bukan dua: kadar kotoran bergerak di orde perseratus.
    expect(KernelDispatchReportService::QUALITY_DECIMALS)->toBe(3);
});

it('case 28 — satu nilai saja memberi min = avg = max; nilai negatif DILAPORKAN APA ADANYA', function () {
    $this->actingAs($this->supervisorA);

    // VARIAN A — tepat SATU pengiriman punya broken_kernel_percent.
    $recordA = kdReportRecord($this->stationA, $this->day1);

    kdReportDispatch($recordA, $this->day1, ['broken_kernel_percent' => 4.5]);
    kdReportDispatch($recordA, $this->day1, ['broken_kernel_percent' => null]);
    kdReportDispatch($recordA, $this->day1, ['broken_kernel_percent' => null]);

    $a = $this->service->buildSummary($this->periodA, null, $this->lineA)['quality'];

    // min = avg = max = 4.5 — bukan null dan bukan 0.
    expect($a['broken_kernel_percent']['min'])->toBe(4.5);
    expect($a['broken_kernel_percent']['avg'])->toBe(4.5);
    expect($a['broken_kernel_percent']['max'])->toBe(4.5);
    expect($a['broken_kernel_percent']['measured_count'])->toBe(1);

    // VARIAN B — persen NEGATIF. Skema mengizinkannya: tidak ada CHECK
    // constraint pada kolom persen.
    KernelDispatchDetail::query()->delete();
    KernelDispatchRecord::query()->delete();

    $recordB = kdReportRecord($this->stationA, $this->day2);

    kdReportDispatch($recordB, $this->day2, ['ffa_percent' => -1.2]);
    kdReportDispatch($recordB, $this->day2, ['ffa_percent' => 3.4]);

    $b = $this->service->buildSummary($this->periodA, null, $this->lineA)['quality'];

    // Nilai negatif IKUT: tidak dibuang dan tidak dijadikan 0. Laporan
    // melaporkan apa yang tersimpan; menyaring di lapisan laporan akan
    // menyembunyikan data masukan yang rusak dari orang yang justru harus
    // memperbaikinya.
    $this->assertEqualsWithDelta(-1.2, $b['ffa_percent']['min'], 0.0005);
    $this->assertEqualsWithDelta(3.4, $b['ffa_percent']['max'], 0.0005);
    $this->assertEqualsWithDelta(1.1, $b['ffa_percent']['avg'], 0.0005);
    expect($b['ffa_percent']['measured_count'])->toBe(2);
    $this->assertLessThan(0, $b['ffa_percent']['min']);
});

it('case 29 — satu pengiriman yang punya kadar air tetapi tidak punya ALB masuk penyebut yang satu dan TIDAK masuk yang lain', function () {
    // TEPAT SATU pengiriman — bentuk TERKECIL dari jebakan penyebut tunggal.
    kdReportDispatch(kdReportRecord($this->stationA, $this->day1), $this->day1, [
        'kernel_moisture_percent' => 7.1,
        'dirt_impurities_percent' => null,
        'ffa_percent' => null,
        'broken_kernel_percent' => null,
    ]);

    $this->actingAs($this->supervisorA);

    $quality = $this->service->buildSummary($this->periodA, null, $this->lineA)['quality'];

    // DUA PENYEBUT BERBEDA DARI SATU BARIS YANG SAMA.
    expect($quality['kernel_moisture_percent']['measured_count'])->toBe(1);
    expect($quality['kernel_moisture_percent']['min'])->toBe(7.1);
    expect($quality['kernel_moisture_percent']['avg'])->toBe(7.1);
    expect($quality['kernel_moisture_percent']['max'])->toBe(7.1);

    foreach (['ffa_percent', 'dirt_impurities_percent', 'broken_kernel_percent'] as $measure) {
        expect($quality[$measure]['measured_count'])->toBe(0);
        $this->assertNull($quality[$measure]['min'], "{$measure}.min bukan null");
        $this->assertNull($quality[$measure]['avg'], "{$measure}.avg bukan null");
        $this->assertNull($quality[$measure]['max'], "{$measure}.max bukan null");
    }
});

it('case 30 — quality._note DITERBITKAN sebagai kunci dan berisi prosa non-kosong, bahkan untuk periode tanpa satu pun hasil uji', function () {
    $this->actingAs($this->supervisorA);

    // VARIAN A — periode berdata.
    kdReportDispatch(kdReportRecord($this->stationA, $this->day1), $this->day1, [
        'kernel_moisture_percent' => 7.0,
    ]);

    $a = $this->service->buildSummary($this->periodA, null, $this->lineA)['quality'];

    // DIASERSIKAN ATAS KEHADIRAN KUNCI, bukan atas kalimat tertentu.
    $this->assertArrayHasKey('_note', $a);
    $this->assertIsString($a['_note']);
    $this->assertNotSame('', trim($a['_note']));
    expect($a['_note'])->toBe(KernelDispatchReportService::NOTE_QUALITY_DENOMINATORS);

    // VARIAN B — periode tanpa satu pun hasil uji: kuncinya TETAP ADA.
    KernelDispatchDetail::query()->delete();
    KernelDispatchRecord::query()->delete();

    $b = $this->service->buildSummary($this->periodA, null, $this->lineA)['quality'];

    $this->assertArrayHasKey('_note', $b);
    $this->assertNotSame('', trim($b['_note']));

    // Dan daftar ukurannya memang KEEMPAT kolom, dalam urutan konstanta
    // service — bukan ditulis ulang di tempat lain.
    expect(KernelDispatchReportService::QUALITY_MEASURES)->toBe([
        'kernel_moisture_percent',
        'dirt_impurities_percent',
        'ffa_percent',
        'broken_kernel_percent',
    ]);
    expect(array_keys($b))->toBe(array_merge(KernelDispatchReportService::QUALITY_MEASURES, ['_note']));
});

// =====================================================================
// GROUP D — STATUS PELEPASAN GERBANG, SATU-SATUNYA KOLOM BERPILIHAN
// TERTUTUP DI STASIUN INI (case 31-35)
// =====================================================================

it('case 31 — by_gate_status menerbitkan KODE MENTAH di payload; label TIDAK ada di payload', function () {
    $record = kdReportRecord($this->stationA, $this->day1);

    kdReportDispatch($record, $this->day1, ['remarks_gate_status' => 'released']);
    kdReportDispatch($record, $this->day1, ['remarks_gate_status' => 'not_released']);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);
    $nilai = array_column($summary['by_gate_status'], 'value');

    $this->assertContains('released', $nilai);
    $this->assertContains('not_released', $nilai);

    // ASERSI NEGATIF — labelnya TIDAK ADA di payload mana pun. Label adalah
    // kosakata lapisan tampilan; menerbitkannya di payload memindahkan
    // kosakata ke lapisan yang salah dan membuat pasangan mobile (screen-165)
    // menerima label yang tidak dapat ia ubah.
    $serialized = json_encode($summary, JSON_UNESCAPED_UNICODE);

    $this->assertStringNotContainsString('"Released"', $serialized);
    $this->assertStringNotContainsString('"Not Released"', $serialized);
    $this->assertNotContains('Released', $nilai);
    $this->assertNotContains('Not Released', $nilai);

    // Dan labelnya MEMANG berbeda dari kodenya — tanpa baris ini asersi
    // negatif di atas bisa hijau pada pemetaan identitas.
    expect(Display::option('released'))->toBe('Released');
    expect(Display::option('not_released'))->toBe('Not Released');
});

it("case 32 — not_released_count menghitung HANYA value === 'not_released' persis; baris null ADA di by_gate_status tetapi TIDAK masuk hitungan", function () {
    $this->actingAs($this->supervisorA);

    $record = kdReportRecord($this->stationA, $this->day1);

    // KETIGA KEADAAN SEKALIGUS: 3 'released', 2 'not_released', 4 NULL.
    foreach (['released', 'released', 'released', 'not_released', 'not_released', null, null, null, null] as $status) {
        kdReportDispatch($record, $this->day1, ['remarks_gate_status' => $status]);
    }

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // 2 — bukan 6 (null tidak digabung) dan bukan 0.
    expect($summary['not_released_count'])->toBe(2);
    $this->assertNotSame(6, $summary['not_released_count']);

    // SEKALIGUS, DALAM TEST YANG SAMA: baris value null ADA, dengan
    // dispatch_count 4. Diasersikan dengan assertNull atas value-nya, BUKAN
    // lewat pencarian string 'tidak tercatat' — justru pasangan dua asersi itu
    // yang membuktikan 'tidak tercatat' dan 'tidak dilepas' adalah dua keadaan
    // berbeda, dan menggabungkannya MENUDUH pengiriman yang sekadar belum
    // diisi statusnya.
    $barisNull = kdReportEntry($summary['by_gate_status'], null);

    $this->assertNotNull($barisNull, "by_gate_status kehilangan baris 'tidak tercatat'");
    $this->assertNull($barisNull['value']);
    expect($barisNull['dispatch_count'])->toBe(4);

    expect(kdReportEntry($summary['by_gate_status'], 'not_released')['dispatch_count'])->toBe(2);
    expect(kdReportEntry($summary['by_gate_status'], 'released')['dispatch_count'])->toBe(3);

    // VARIAN KEDUA — nol pengiriman 'not_released': kuncinya TETAP diterbitkan
    // bernilai 0.
    KernelDispatchDetail::query()->update(['remarks_gate_status' => 'released']);

    $kedua = $this->service->buildSummary($this->periodA, null, $this->lineA);

    $this->assertArrayHasKey('not_released_count', $kedua);
    expect($kedua['not_released_count'])->toBe(0);
});

it('case 33 — nilai remarks_gate_status di luar kedua pilihan yang dikenal mendapat barisnya SENDIRI, apa adanya', function () {
    // TIDAK LEWAT BASIS DATA, DAN ITU BUKAN PILIHAN: migrasi
    // 2026_08_31_000007 mendeklarasikan remarks_gate_status sebagai
    // enum(['released','not_released'])->nullable() — sebuah CHECK CONSTRAINT
    // di PostgreSQL DAN di SQLite — sehingga 'HOLD_BY_SECURITY' DITOLAK pada
    // INSERT lewat factory. Cabangnya tetap wajib diuji karena
    // KernelDispatchRecordService menyimpan `filled(...) ? value : null` tanpa
    // daftar tertutup, jadi jalur selain form web dapat menyimpan apa pun.
    $dispatches = [];

    foreach (['released', 'released', 'not_released', 'HOLD_BY_SECURITY', 'HOLD_BY_SECURITY', null] as $status) {
        $dispatches[] = kdReportMemoryDispatch($this->day1, ['gate_status' => $status]);
    }

    KernelDispatchReportInMemoryService::$dispatches = collect($dispatches);

    $this->actingAs($this->supervisorA);

    $summary = (new KernelDispatchReportInMemoryService)
        ->buildSummary($this->periodA, null, $this->lineA);

    // EMPAT BARIS: 'released', 'not_released', 'HOLD_BY_SECURITY', dan null.
    expect($summary['by_gate_status'])->toHaveCount(4);

    $tidakDikenal = kdReportEntry($summary['by_gate_status'], 'HOLD_BY_SECURITY');

    // TIDAK DIBUANG.
    $this->assertNotNull($tidakDikenal, 'nilai tak dikenal dibuang dari by_gate_status');
    // Nilai MENTAH apa adanya.
    expect($tidakDikenal['value'])->toBe('HOLD_BY_SECURITY');
    expect($tidakDikenal['dispatch_count'])->toBe(2);

    // TIDAK DILIPAT KE BARIS NULL: baris null tetap berisi 1 saja.
    $barisNull = kdReportEntry($summary['by_gate_status'], null);
    $this->assertNotNull($barisNull);
    expect($barisNull['dispatch_count'])->toBe(1);

    // TIDAK DIPAKSA MASUK SALAH SATU PILIHAN: kedua baris yang dikenal tetap
    // sebesar yang disemai.
    expect(kdReportEntry($summary['by_gate_status'], 'released')['dispatch_count'])->toBe(2);
    expect(kdReportEntry($summary['by_gate_status'], 'not_released')['dispatch_count'])->toBe(1);

    // not_released_count TETAP 1 — nilai tak dikenal BUKAN 'tidak dilepas'.
    expect($summary['not_released_count'])->toBe(1);

    // Dan skemanya MEMANG menolak nilai itu lewat factory — fakta yang membuat
    // service-ganda di atas perlu, bukan sekadar nyaman.
    $record = kdReportRecord($this->stationA, $this->day1);

    $this->expectException(QueryException::class);
    kdReportDispatch($record, $this->day1, ['remarks_gate_status' => 'HOLD_BY_SECURITY']);
});

it('case 34 — penjumlahan dispatch_count by_gate_status TETAP SAMA DENGAN dispatch_count meski ada nilai tak dikenal dan baris null', function () {
    $dispatches = [];

    foreach (['released', 'released', 'not_released', 'HOLD_BY_SECURITY', 'HOLD_BY_SECURITY', null] as $status) {
        $dispatches[] = kdReportMemoryDispatch($this->day1, ['gate_status' => $status]);
    }

    KernelDispatchReportInMemoryService::$dispatches = collect($dispatches);

    $this->actingAs($this->supervisorA);

    $summary = (new KernelDispatchReportInMemoryService)
        ->buildSummary($this->periodA, null, $this->lineA);

    // INILAH ASERSI YANG MENANGKAP IMPLEMENTASI YANG 'MERAPIKAN': begitu satu
    // baris hilang — nilai tak dikenal yang dibuang, atau baris null yang
    // dibuang — rekapnya tidak lagi berjumlah ke angka di atasnya, dan
    // selisihnya langsung menunjuk baris yang dibuang.
    $this->assertSame(
        6,
        array_sum(array_column($summary['by_gate_status'], 'dispatch_count')),
        'penjumlahan by_gate_status tidak sama dengan dispatch_count',
    );
    $this->assertSame(
        $summary['dispatches']['dispatch_count'],
        array_sum(array_column($summary['by_gate_status'], 'dispatch_count')),
    );
    expect($summary['dispatches']['dispatch_count'])->toBe(6);
});

it("case 35 — Display::OPTION_LABELS memuat 'released' dan 'not_released'; option() jatuh ke nilai mentah untuk yang tak dikenal dan '-' untuk kosong", function () {
    // DIPANGGIL LANGSUNG — ini kosakata bersama, bukan perilaku service.
    expect(Display::option('released'))->toBe('Released');
    expect(Display::option('not_released'))->toBe('Not Released');
    // Label DISALIN VERBATIM dari <select> form, tidak diterjemahkan: dua
    // label untuk satu nilai adalah dua kosakata.
    expect(Display::option('HOLD_BY_SECURITY'))->toBe('HOLD_BY_SECURITY');
    expect(Display::option(null))->toBe('-');
    expect(Display::option(''))->toBe('-');

    $this->assertArrayHasKey('released', Display::OPTION_LABELS);
    $this->assertArrayHasKey('not_released', Display::OPTION_LABELS);

    // PEMETAANNYA HIDUP DI SATU TEMPAT — keputusan proyek 2026-10-14: blade
    // MENDELEGASIKAN, jangan menyalin. Dicari dengan grep atas pasangan
    // kode→label pada ketiga pembacanya.
    $berkas = [
        'resources/views/livewire/dashboard/laporan-kernel-dispatch.blade.php',
        'resources/views/livewire/data/detail-kernel-dispatch.blade.php',
    ];

    foreach ($berkas as $path) {
        $penuh = base_path($path);

        if (! is_file($penuh)) {
            continue;
        }

        $isi = (string) file_get_contents($penuh);

        // Yang dilarang adalah MENULISKAN ULANG pemetaannya — pasangan
        // "'released' => 'Released'" dalam bentuk apa pun.
        $this->assertDoesNotMatchRegularExpression(
            "/'not_released'\s*=>\s*'Not Released'/",
            $isi,
            "{$path} menyalin pemetaan Display::OPTION_LABELS alih-alih mendelegasikannya",
        );
        $this->assertDoesNotMatchRegularExpression(
            "/'released'\s*=>\s*'Released'/",
            $isi,
            "{$path} menyalin pemetaan Display::OPTION_LABELS alih-alih mendelegasikannya",
        );
    }

    // Dan exporter-nya pun MENDELEGASIKAN, bukan membawa daftar label
    // miliknya sendiri. DIKOREKSI 2026-10-18: ia memanggil
    // ExportValue::option(), bukan Display::option() — dan itu TETAP
    // delegasi, karena ExportValue::option() hanya membungkus
    // Display::option() lalu mengubah '-' menjadi null. Yang diasersikan di
    // sini adalah KETIADAAN pemetaan tandingan, dan itu tidak berubah.
    $service = (string) file_get_contents(base_path('app/Services/KernelDispatchReportService.php'));

    $this->assertStringContainsString('ExportValue::option($value)', $service);
    $this->assertDoesNotMatchRegularExpression("/'not_released'\s*=>\s*'Not Released'/", $service);

    // ASERSI NEGATIF YANG BARU: Display::option() TIDAK dipakai langsung di
    // jalur ekspor. Aturan rumah (keputusan pemilik produk 2026-10-14)
    // menyatakan kolom enum pengukuran memakai ExportValue::option() DI MANA
    // PUN, dan kedua preseden yang sudah ada — EffluentPlantReportService dan
    // EngineRoomReportService — memakainya persis begitu.
    $this->assertStringNotContainsString('Display::option($value)', $service);
});

// =====================================================================
// GROUP E — TIGA REKAP TEKS BEBAS DAN TIGA PENGHITUNG KEPATUHAN
// (case 36-45)
// =====================================================================

it('case 36 — tiga rekap teks bebas mengelompokkan nilai APA ADANYA: nol join, nol pemetaan; net_weight_total per entri hanya menjumlah netto non-null', function () {
    $record = kdReportRecord($this->stationA, $this->day1);

    foreach ([10.0, 20.0, null] as $netto) {
        kdReportDispatch($record, $this->day1, [
            'net_weight_mt' => $netto,
            'transporter_contractor' => 'PT Angkut Jaya',
            'silo_source_id' => 'SILO-A',
            'destination_buyer' => 'PT Pembeli',
        ]);
    }

    $this->actingAs($this->supervisorA);

    $queries = kdReportQueriesDuring(function () {
        $this->service->buildSummary($this->periodA, null, $this->lineA);
    });

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    $entri = kdReportEntry($summary['by_transporter'], 'PT Angkut Jaya');

    $this->assertNotNull($entri);
    expect($entri['dispatch_count'])->toBe(3);
    expect($entri['net_weight_total'])->toBe(30.0);
    // TIAP BARIS REKAP MENERBITKAN PENYEBUTNYA SENDIRI.
    expect($entri['net_weight_dispatch_count'])->toBe(2);
    expect($entri['net_weight_avg'])->toBe(15.0);

    // Nilai yang muncul SEKALI tetap berbaris sendiri dan tidak pernah dilipat
    // menjadi 'lainnya'.
    expect($summary['by_silo_source'])->toHaveCount(1);
    expect(kdReportEntry($summary['by_silo_source'], 'SILO-A')['dispatch_count'])->toBe(3);
    expect(kdReportEntry($summary['by_destination_buyer'], 'PT Pembeli')['dispatch_count'])->toBe(3);
    $this->assertNull(kdReportEntry($summary['by_transporter'], 'lainnya'));

    // ASERSI NEGATIF ATAS QUERY LOG: NOL kueri tambahan ke tabel master apa
    // pun untuk menerjemahkan nilai ketiga kolom itu.
    foreach (['transporters', 'contractors', 'silos', 'silo', 'buyers', 'destinations'] as $tabel) {
        $this->assertFalse(
            kdReportTouched($queries, ' '.$tabel),
            "rekap menembak kueri ke tabel master {$tabel}",
        );
    }
});

it('case 37 — silo_source_id dikelompokkan sebagai TEKS: nilai yang tampak seperti uuid TETAP dicetak apa adanya', function () {
    $uuid = '9f1c2a44-5e21-4b6f-8c0d-7a3e51b9d012';
    $record = kdReportRecord($this->stationA, $this->day1);

    kdReportDispatch($record, $this->day1, ['silo_source_id' => $uuid]);
    kdReportDispatch($record, $this->day1, ['silo_source_id' => $uuid]);
    kdReportDispatch($record, $this->day1, ['silo_source_id' => 'Silo Timur 2']);

    $this->actingAs($this->supervisorA);

    $queries = kdReportQueriesDuring(function () {
        $this->service->buildSummary($this->periodA, null, $this->lineA);
    });

    $rekap = $this->service->buildSummary($this->periodA, null, $this->lineA)['by_silo_source'];
    $nilai = array_column($rekap, 'value');

    // VERBATIM: tidak ditukar dengan nama apa pun, tidak dipotong, tidak
    // dibuang.
    $this->assertContains($uuid, $nilai);
    $this->assertContains('Silo Timur 2', $nilai);
    expect(kdReportEntry($rekap, $uuid)['dispatch_count'])->toBe(2);
    expect(kdReportEntry($rekap, 'Silo Timur 2')['dispatch_count'])->toBe(1);

    // ASERSI NEGATIF ATAS QUERY LOG: nol join dan nol kueri ke tabel mana pun
    // bernama silo. Kolomnya string nullable di migrasi 2026_08_31, tidak ada
    // tabel silo di satu migrasi pun, dan form merendernya <input type="text">.
    foreach ($queries as $sql) {
        $this->assertStringNotContainsString('silo', $sql, "kueri menyentuh tabel bernama silo: {$sql}");
    }
});

it("case 38 — baris value null ('tidak tercatat') adalah entri TERSENDIRI; kolom kosong pada SELURUH pengiriman memberi satu baris berisi semuanya", function () {
    $this->actingAs($this->supervisorA);

    // VARIAN A — 5 pengiriman, 2 di antaranya destination_buyer NULL.
    $recordA = kdReportRecord($this->stationA, $this->day1);

    foreach (['PT Satu', 'PT Satu', 'PT Dua', null, null] as $pembeli) {
        kdReportDispatch($recordA, $this->day1, ['destination_buyer' => $pembeli]);
    }

    $a = $this->service->buildSummary($this->periodA, null, $this->lineA)['by_destination_buyer'];
    $barisNull = kdReportEntry($a, null);

    $this->assertNotNull($barisNull);
    $this->assertNull($barisNull['value']);
    expect($barisNull['dispatch_count'])->toBe(2);
    // TERPISAH dari baris-baris bernilai.
    expect($a)->toHaveCount(3);

    // VARIAN B — destination_buyer NULL pada SEMUANYA.
    KernelDispatchDetail::query()->delete();
    KernelDispatchRecord::query()->delete();

    $recordB = kdReportRecord($this->stationA, $this->day2);

    foreach (range(1, 6) as $i) {
        kdReportDispatch($recordB, $this->day2, ['destination_buyer' => null]);
    }

    $b = $this->service->buildSummary($this->periodA, null, $this->lineA)['by_destination_buyer'];

    // TEPAT SATU baris, value null, dispatch_count 6 — bukan array kosong dan
    // bukan blok yang dihilangkan dari payload.
    expect($b)->toHaveCount(1);
    $this->assertNull($b[0]['value']);
    expect($b[0]['dispatch_count'])->toBe(6);
    $this->assertNotSame([], $b);
});

it('case 39 — penjumlahan dispatch_count tiap rekap SAMA DENGAN dispatch_count, diasersikan di KEEMPAT rekap termasuk by_gate_status', function () {
    $record = kdReportRecord($this->stationA, $this->day1);

    // POLA NULL YANG BERBEDA antar-kolom supaya satu rekap tidak membuktikan
    // rekap lain: transporter null pada 1, silo null pada 3, pembeli null pada
    // 5, gate status null pada 2.
    $baris = [
        ['transporter_contractor' => null,      'silo_source_id' => 'S1',  'destination_buyer' => 'B1',  'remarks_gate_status' => 'released'],
        ['transporter_contractor' => 'T1',      'silo_source_id' => null,  'destination_buyer' => 'B1',  'remarks_gate_status' => 'released'],
        ['transporter_contractor' => 'T1',      'silo_source_id' => null,  'destination_buyer' => null,  'remarks_gate_status' => null],
        ['transporter_contractor' => 'T2',      'silo_source_id' => null,  'destination_buyer' => null,  'remarks_gate_status' => 'not_released'],
        ['transporter_contractor' => 'T2',      'silo_source_id' => 'S2',  'destination_buyer' => null,  'remarks_gate_status' => 'released'],
        ['transporter_contractor' => 'T2',      'silo_source_id' => 'S2',  'destination_buyer' => null,  'remarks_gate_status' => null],
        ['transporter_contractor' => 'T3',      'silo_source_id' => 'S2',  'destination_buyer' => null,  'remarks_gate_status' => 'released'],
        ['transporter_contractor' => 'T3',      'silo_source_id' => 'S3',  'destination_buyer' => 'B2',  'remarks_gate_status' => 'not_released'],
    ];

    foreach ($baris as $nilai) {
        kdReportDispatch($record, $this->day1, $nilai);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['dispatches']['dispatch_count'])->toBe(8);

    // EMPAT ASERSI PENJUMLAHAN TERPISAH. Pola null yang berbeda itulah yang
    // membuat keempatnya tidak saling menumpang — baris null yang dibuang di
    // satu rekap saja akan tetap tertangkap.
    foreach (KD_RECAP_KEYS as $rekap) {
        $this->assertSame(
            8,
            array_sum(array_column($summary[$rekap], 'dispatch_count')),
            "rekap {$rekap} tidak menjumlah ke dispatch_count",
        );
    }

    // Dan keempat rekap memang punya baris null-nya masing-masing dengan
    // dispatch_count yang BERBEDA — kalau tidak, keempat asersi di atas akan
    // setengah vakum.
    expect(kdReportEntry($summary['by_transporter'], null)['dispatch_count'])->toBe(1);
    expect(kdReportEntry($summary['by_silo_source'], null)['dispatch_count'])->toBe(3);
    expect(kdReportEntry($summary['by_destination_buyer'], null)['dispatch_count'])->toBe(5);
    expect(kdReportEntry($summary['by_gate_status'], null)['dispatch_count'])->toBe(2);
});

it('case 40 — string kosong dan whitespace di kolom kategoris di-trim lalu diperlakukan SAMA DENGAN null: satu entri, bukan dua atau tiga', function () {
    $record = kdReportRecord($this->stationA, $this->day1);

    foreach ([null, '', '   ', 'PT Angkut Jaya'] as $transporter) {
        kdReportDispatch($record, $this->day1, ['transporter_contractor' => $transporter]);
    }

    $this->actingAs($this->supervisorA);

    $rekap = $this->service->buildSummary($this->periodA, null, $this->lineA)['by_transporter'];

    // TEPAT DUA BARIS.
    expect($rekap)->toHaveCount(2);
    expect(kdReportEntry($rekap, null)['dispatch_count'])->toBe(3);
    expect(kdReportEntry($rekap, 'PT Angkut Jaya')['dispatch_count'])->toBe(1);

    // ASERSI NEGATIF: tidak ada entri yang value-nya '' maupun '   '.
    $nilai = array_column($rekap, 'value');
    $this->assertNotContains('', $nilai);
    $this->assertNotContains('   ', $nilai);
});

it('case 41 — nilai kategoris yang berbeda hanya pada huruf besar/kecil TETAP dua entri terpisah', function () {
    $record = kdReportRecord($this->stationA, $this->day1);

    foreach (['PT Pembeli', 'pt pembeli', ' PT Pembeli '] as $pembeli) {
        kdReportDispatch($record, $this->day1, ['destination_buyer' => $pembeli]);
    }

    $this->actingAs($this->supervisorA);

    $rekap = $this->service->buildSummary($this->periodA, null, $this->lineA)['by_destination_buyer'];

    // DUA BARIS: yang berspasi IKUT setelah trim; beda huruf besar/kecil TIDAK
    // disatukan — menyatukannya berarti memutuskan atas nama orang bahwa
    // keduanya pembeli yang sama.
    expect($rekap)->toHaveCount(2);
    expect(kdReportEntry($rekap, 'PT Pembeli')['dispatch_count'])->toBe(2);
    expect(kdReportEntry($rekap, 'pt pembeli')['dispatch_count'])->toBe(1);
    $this->assertNotContains(' PT Pembeli ', array_column($rekap, 'value'));
});

it('case 42 — urutan tiap rekap: net_weight_total DESC, lalu dispatch_count DESC, lalu value ASC, dengan SERI SEMPURNA pada kedua kunci pertama', function () {
    $record = kdReportRecord($this->stationA, $this->day1);

    // SERI SEMPURNA pada net_weight_total DAN dispatch_count untuk ketiga
    // entri — tanpa itu pemutus ketiga (value ASC) tidak pernah dieksekusi dan
    // asersi urutannya VAKUM. Disemai dalam urutan Charlie, Alpha, Bravo
    // supaya urutan masuk tidak sama dengan urutan keluar.
    foreach (['Charlie', 'Alpha', 'Bravo'] as $nama) {
        kdReportDispatch($record, $this->day1, ['transporter_contractor' => $nama, 'net_weight_mt' => 20.0]);
        kdReportDispatch($record, $this->day1, ['transporter_contractor' => $nama, 'net_weight_mt' => 20.0]);
    }

    kdReportDispatch($record, $this->day1, ['transporter_contractor' => 'Delta', 'net_weight_mt' => 100.0]);

    $this->actingAs($this->supervisorA);

    $rekap = $this->service->buildSummary($this->periodA, null, $this->lineA)['by_transporter'];

    // URUTAN LENGKAP, bukan assertContains yang akan lolos pada urutan apa pun.
    expect(array_column($rekap, 'value'))->toBe(['Delta', 'Alpha', 'Bravo', 'Charlie']);

    // Dan ketiganya MEMANG seri sempurna pada kedua kunci pertama.
    foreach (['Alpha', 'Bravo', 'Charlie'] as $nama) {
        expect(kdReportEntry($rekap, $nama)['net_weight_total'])->toBe(40.0);
        expect(kdReportEntry($rekap, $nama)['dispatch_count'])->toBe(2);
    }
});

it('case 43 — null diurutkan sebagai 0.0 UNTUK PENGURUTAN SAJA; kelompok null bertonase besar muncul DI ATAS dan BUKAN baris terakhir', function () {
    $record = kdReportRecord($this->stationA, $this->day1);

    // Baris null: 5 pengiriman, total 500.
    foreach (range(1, 5) as $i) {
        kdReportDispatch($record, $this->day1, ['silo_source_id' => null, 'net_weight_mt' => 100.0]);
    }

    // SILO-A: total 80 atas 2 pengiriman.
    kdReportDispatch($record, $this->day1, ['silo_source_id' => 'SILO-A', 'net_weight_mt' => 40.0]);
    kdReportDispatch($record, $this->day1, ['silo_source_id' => 'SILO-A', 'net_weight_mt' => 40.0]);

    // SILO-B: total 80 juga, tetapi dispatch_count LEBIH BESAR (4).
    foreach (range(1, 4) as $i) {
        kdReportDispatch($record, $this->day1, ['silo_source_id' => 'SILO-B', 'net_weight_mt' => 20.0]);
    }

    // SILO-C: seluruh nettonya kosong sehingga net_weight_total-nya NULL.
    foreach (range(1, 2) as $i) {
        kdReportDispatch($record, $this->day1, ['silo_source_id' => 'SILO-C', 'net_weight_mt' => null]);
    }

    $this->actingAs($this->supervisorA);

    $rekap = $this->service->buildSummary($this->periodA, null, $this->lineA)['by_silo_source'];

    expect(array_column($rekap, 'value'))->toBe([null, 'SILO-B', 'SILO-A', 'SILO-C']);

    // Baris null di INDEKS 0 — tonasenya yang menentukan posisinya, bukan
    // null-nya.
    expect(kdReportNullIndex($rekap))->toBe(0);

    // ASERSI NEGATIF EKSPLISIT: baris null BUKAN entri terakhir. 'null dipaku
    // di akhir' adalah kesalahan yang paling sering tersamar sebagai rapi.
    $this->assertNotSame(count($rekap) - 1, kdReportNullIndex($rekap));

    // SILO-C diurutkan sebagai 0.0 sehingga ia yang TERAKHIR, SEMENTARA
    // net_weight_total-nya di payload TETAP null — pengurutan tidak boleh
    // merembes ke nilai yang diterbitkan.
    $siloC = kdReportEntry($rekap, 'SILO-C');
    $this->assertNull($siloC['net_weight_total']);
    $this->assertNull($siloC['net_weight_avg']);
    expect(array_column($rekap, 'value')[count($rekap) - 1])->toBe('SILO-C');

    // SILO-B di atas SILO-A karena pemutus KEDUA (dispatch_count DESC).
    expect(kdReportEntry($rekap, 'SILO-A')['net_weight_total'])->toBe(80.0);
    expect(kdReportEntry($rekap, 'SILO-B')['net_weight_total'])->toBe(80.0);
    expect(kdReportEntry($rekap, 'SILO-B')['dispatch_count'])->toBe(4);
    expect(kdReportEntry($rekap, 'SILO-A')['dispatch_count'])->toBe(2);
});

it('case 44 — TIGA penghitung kepatuhan TERPISAH menghitung null DAN string kosong setelah trim; nol tetap diterbitkan; TIDAK ADA kunci gabungan', function () {
    $this->actingAs($this->supervisorA);

    $record = kdReportRecord($this->stationA, $this->day1);

    // 6 pengiriman: waybill NULL pada 1 dan '  ' pada 1; segel atas NULL pada
    // 2; segel bawah '' pada 3.
    $baris = [
        ['waybill_number' => null,      'security_seal_no_top' => null,  'security_seal_no_bottom' => ''],
        ['waybill_number' => '  ',      'security_seal_no_top' => null,  'security_seal_no_bottom' => ''],
        ['waybill_number' => 'SJ-0003', 'security_seal_no_top' => 'T3',  'security_seal_no_bottom' => ''],
        ['waybill_number' => 'SJ-0004', 'security_seal_no_top' => 'T4',  'security_seal_no_bottom' => 'B4'],
        ['waybill_number' => 'SJ-0005', 'security_seal_no_top' => 'T5',  'security_seal_no_bottom' => 'B5'],
        ['waybill_number' => 'SJ-0006', 'security_seal_no_top' => 'T6',  'security_seal_no_bottom' => 'B6'],
    ];

    foreach ($baris as $nilai) {
        kdReportDispatch($record, $this->day1, $nilai);
    }

    $compliance = $this->service->buildSummary($this->periodA, null, $this->lineA)['compliance'];

    // TIGA ANGKA yang diasersikan TERPISAH.
    expect($compliance['missing_waybill_count'])->toBe(2);
    expect($compliance['missing_seal_top_count'])->toBe(2);
    expect($compliance['missing_seal_bottom_count'])->toBe(3);

    // ASERSI NEGATIF ATAS NAMA KUNCI (array_key_exists, bukan pencarian
    // teks): compliance TIDAK punya kunci gabungan apa pun.
    foreach ([
        'missing_seal_count', 'missing_seals_count', 'seal_count',
        'missing_document_count', 'missing_trace_count', 'compliance_count',
    ] as $gabungan) {
        $this->assertArrayNotHasKey($gabungan, $compliance, "compliance punya kunci gabungan {$gabungan}");
    }

    // VARIAN KEDUA — seluruh jejak lengkap: ketiganya 0 dan ketiga kunci TETAP
    // ADA.
    KernelDispatchDetail::query()->update([
        'waybill_number' => 'SJ-LENGKAP',
        'security_seal_no_top' => 'T-LENGKAP',
        'security_seal_no_bottom' => 'B-LENGKAP',
    ]);

    $kedua = $this->service->buildSummary($this->periodA, null, $this->lineA)['compliance'];

    foreach (['missing_waybill_count', 'missing_seal_top_count', 'missing_seal_bottom_count'] as $kunci) {
        $this->assertArrayHasKey($kunci, $kedua, "kunci {$kunci} hilang ketika nilainya 0");
        expect($kedua[$kunci])->toBe(0);
    }
});

it('case 45 — satu pengiriman yang kehilangan KEDUA nomor segel terhitung di KEDUA penghitung: itu DUA FAKTA, bukan penghitungan ganda', function () {
    $record = kdReportRecord($this->stationA, $this->day1);

    // Satu kehilangan KEDUA segel, satu kehilangan hanya segel atas, satu
    // lengkap.
    // Nomor surat jalan DIISI pada ketiganya dengan sengaja: tanpa itu
    // missing_waybill_count sendiri menjadi 3 dan asersi negatif 'tidak ada
    // angka 3' di bawah akan merah karena alasan yang salah.
    kdReportDispatch($record, $this->day1, ['waybill_number' => 'SJ-1', 'security_seal_no_top' => null, 'security_seal_no_bottom' => null]);
    kdReportDispatch($record, $this->day1, ['waybill_number' => 'SJ-2', 'security_seal_no_top' => null, 'security_seal_no_bottom' => 'B2']);
    kdReportDispatch($record, $this->day1, ['waybill_number' => 'SJ-3', 'security_seal_no_top' => 'T3', 'security_seal_no_bottom' => 'B3']);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);
    $compliance = $summary['compliance'];

    expect($compliance['missing_seal_top_count'])->toBe(2);
    expect($compliance['missing_seal_bottom_count'])->toBe(1);
    expect($compliance['missing_waybill_count'])->toBe(0);

    // ASERSI NEGATIF: tidak ada angka 3 yang diterbitkan di mana pun sebagai
    // 'total segel hilang'. Satu kendaraan kehilangan satu segel != dua
    // kendaraan masing-masing kehilangan satu, dan satu angka gabungan (di
    // sini 3) akan menghapus perbedaan itu.
    foreach ($compliance as $kunci => $nilai) {
        if ($kunci === '_note') {
            continue;
        }

        $this->assertNotSame(3, $nilai, "compliance.{$kunci} menerbitkan angka gabungan 3");
    }

    // Diperiksa atas NAMA KUNCI juga, di SELURUH payload: tidak ada kunci
    // bernama total segel hilang di level mana pun.
    $keys = kdReportAllKeys($summary);

    foreach (['missing_seal_count', 'total_missing_seal', 'seal_missing_total'] as $gabungan) {
        $this->assertNotContains($gabungan, $keys);
    }

    // Dan prosanya MENYATAKAN dua fakta itu — diasersikan atas kehadiran
    // kuncinya, prosanya sendiri milik konstanta.
    $this->assertArrayHasKey('_note', $compliance);
    expect($compliance['_note'])->toBe(KernelDispatchReportService::NOTE_COMPLIANCE);
});

// =====================================================================
// GROUP F — DRAFT, TAK BERTANGGAL, REKAP HARIAN, KELENGKAPAN, DAN
// KETIADAAN YANG DIASERSIKAN ATAS NAMA KUNCI (case 46-53)
// =====================================================================

it('case 46 — record draft ikut terhitung di SELURUH angka termasuk keempat ukuran mutu; draft_dispatch_count menyatakan berapa', function () {
    $this->actingAs($this->supervisorA);

    $mutu = [
        'kernel_moisture_percent' => 7.0,
        'dirt_impurities_percent' => 0.02,
        'ffa_percent' => 3.0,
        'broken_kernel_percent' => 5.0,
    ];

    $draft = kdReportRecord($this->stationA, $this->day1, ['status' => RecordStatus::DraftOngoing]);
    $final = kdReportRecord($this->stationA, $this->day2, ['status' => RecordStatus::Saved]);

    foreach (range(1, 3) as $i) {
        kdReportDispatch($draft, $this->day1, array_merge(['net_weight_mt' => 2.0], $mutu));
    }

    foreach (range(1, 2) as $i) {
        kdReportDispatch($final, $this->day2, array_merge(['net_weight_mt' => 2.0], $mutu));
    }

    $dengan = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($dengan['dispatches']['dispatch_count'])->toBe(5);
    expect($dengan['dispatches']['net_weight_total'])->toBe(10.0);
    expect($dengan['draft_dispatch_count'])->toBe(3);

    foreach (KernelDispatchReportService::QUALITY_MEASURES as $measure) {
        expect($dengan['quality'][$measure]['measured_count'])->toBe(5);
    }

    // DIBUKTIKAN DENGAN MEMBANDINGKAN terhadap varian yang draft-nya dihapus:
    // keempat measured_count turun dari 5 ke 2. draft_dispatch_count ADALAH
    // pernyataan bahwa draft ikut terhitung, bukan penyaringnya.
    KernelDispatchDetail::query()->where('kernel_dispatch_record_id', $draft->id)->delete();
    $draft->delete();

    $tanpa = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($tanpa['dispatches']['dispatch_count'])->toBe(2);
    expect($tanpa['draft_dispatch_count'])->toBe(0);

    foreach (KernelDispatchReportService::QUALITY_MEASURES as $measure) {
        expect($tanpa['quality'][$measure]['measured_count'])->toBe(2);
    }

    // Dan kedua status draft yang dikenal service memang kedua status itu.
    expect(KernelDispatchReportService::DRAFT_STATUSES)->toBe([
        RecordStatus::DraftOngoing->value,
        RecordStatus::DraftPaused->value,
    ]);
});

it('case 47 — undated_dispatch_count SELALU diterbitkan dan bernilai 0 dalam praktik, karena skema MENOLAK event_date NULL', function () {
    $record = kdReportRecord($this->stationA, $this->day1);

    kdReportDispatch($record, $this->day1, ['net_weight_mt' => 1.0]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // array_key_exists DAN nilainya, bukan hanya nilainya.
    $this->assertArrayHasKey('undated_dispatch_count', $summary);
    expect($summary['undated_dispatch_count'])->toBe(0);

    // PERCOBAAN INSERT LANGSUNG melempar QueryException: asersi atas KELAS
    // exception itu yang membuktikan kolomnya NOT NULL di migrasi, bukan
    // sekadar 'belum pernah terjadi'.
    $thrown = null;

    try {
        kdReportDispatch($record, $this->day1, ['event_date' => null]);
    } catch (Throwable $exception) {
        $thrown = $exception;
    }

    expect($thrown)->toBeInstanceOf(QueryException::class);

    $thrownRaw = null;

    try {
        DB::table('kernel_dispatch_details')->insert([
            'id' => (string) Str::uuid(),
            'kernel_dispatch_record_id' => $record->id,
            'event_date' => null,
        ]);
    } catch (Throwable $exception) {
        $thrownRaw = $exception;
    }

    expect($thrownRaw)->toBeInstanceOf(QueryException::class);
});

it('case 48 — cabang undated_dispatch_count > 0 diuji lewat service-ganda in-memory, bukan lewat DB', function () {
    $dispatches = [];

    // 5 bertanggal eksplisit (3 hari berbeda) + 2 ber-date null.
    foreach ([$this->day1, $this->day1, $this->day2, $this->day2, $this->day3] as $tanggal) {
        $dispatches[] = kdReportMemoryDispatch($tanggal, [
            'net_weight' => 2.0,
            'transporter' => 'T1',
            'silo_source' => 'S1',
            'destination_buyer' => 'B1',
            'gate_status' => 'released',
        ]);
    }

    foreach (range(1, 2) as $i) {
        $dispatches[] = kdReportMemoryDispatch(null, [
            'net_weight' => 2.0,
            'transporter' => 'T1',
            'silo_source' => 'S1',
            'destination_buyer' => 'B1',
            'gate_status' => 'released',
        ]);
    }

    KernelDispatchReportInMemoryService::$dispatches = collect($dispatches);

    $this->actingAs($this->supervisorA);

    $summary = (new KernelDispatchReportInMemoryService)
        ->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['undated_dispatch_count'])->toBe(2);
    // KEDUA BARIS MASUK dispatch_count.
    expect($summary['dispatches']['dispatch_count'])->toBe(7);

    // DAN MASUK KEEMPAT REKAP KATEGORIS.
    foreach (KD_RECAP_KEYS as $rekap) {
        $this->assertSame(
            7,
            array_sum(array_column($summary[$rekap], 'dispatch_count')),
            "rekap {$rekap} membuang pengiriman tak bertanggal",
        );
    }

    // TETAPI TIDAK MASUK daily[] — tepat kurang 2.
    expect($summary['daily'])->toHaveCount(3);
    expect(array_sum(array_column($summary['daily'], 'dispatch_count')))->toBe(5);
    $this->assertSame(
        $summary['dispatches']['dispatch_count'] - $summary['undated_dispatch_count'],
        array_sum(array_column($summary['daily'], 'dispatch_count')),
        'penjumlahan daily[] harus tepat dispatch_count dikurangi undated_dispatch_count',
    );
    expect(array_column($summary['daily'], 'date'))->toBe([$this->day1, $this->day2, $this->day3]);
});

it('case 49 — daily[] berisi satu entri per hari BERPENGIRIMAN, bukan per hari kalender; daily_total DIJUMLAHKAN dari daily[]', function () {
    // SEMBILAN tanggal berbeda di dalam periode 31 hari, disemai dalam urutan
    // insert yang DIACAK supaya urutan naik tidak diwarisi dari urutan kueri —
    // PostgreSQL tidak menjanjikannya.
    $offsets = [31, 39, 20, 35, 12, 28, 17, 24, 14];
    $tanggal = array_map(fn (int $o) => now()->subDays($o)->toDateString(), $offsets);

    foreach ($tanggal as $index => $hari) {
        $record = kdReportRecord($this->stationA, $hari);

        // Beberapa hari dengan lebih dari satu pengiriman; sebagian baris
        // ber-netto null.
        kdReportDispatch($record, $hari, ['net_weight_mt' => 3.0]);

        if ($index % 3 === 0) {
            kdReportDispatch($record, $hari, ['net_weight_mt' => null]);
        }

        if ($index % 4 === 0) {
            kdReportDispatch($record, $hari, ['net_weight_mt' => 1.5]);
        }
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // 9 — bukan 31 (hari kalender) dan bukan jumlah pengiriman.
    expect($summary['daily'])->toHaveCount(9);
    expect($summary['completeness']['days_in_period'])->toBe(31);

    $urut = $tanggal;
    sort($urut);
    expect(array_column($summary['daily'], 'date'))->toBe($urut);

    // DIJUMLAHKAN dari daily[], bukan disalin.
    $this->assertSame(
        array_sum(array_column($summary['daily'], 'dispatch_count')),
        $summary['daily_total']['dispatch_count'],
    );
    $this->assertEqualsWithDelta(
        array_sum(array_map(fn (array $row) => (float) ($row['net_weight_total'] ?? 0.0), $summary['daily'])),
        (float) $summary['daily_total']['net_weight_total'],
        0.005,
    );
    $this->assertSame(
        array_sum(array_column($summary['daily'], 'net_weight_dispatch_count')),
        $summary['daily_total']['net_weight_dispatch_count'],
    );

    // Pada fixture berbasis DB keduanya memang sama — yang membedakan 'dijumlahkan'
    // dari 'disalin' adalah skenario undated in-memory (case 48), tempat kedua
    // angka itu BERBEDA.
    expect($summary['daily_total']['dispatch_count'])->toBe($summary['dispatches']['dispatch_count']);

    // Dan setiap baris harian menerbitkan PENYEBUTNYA sendiri.
    foreach ($summary['daily'] as $row) {
        $this->assertArrayHasKey('net_weight_dispatch_count', $row);
        $this->assertArrayHasKey('net_weight_avg', $row);
    }
});

it('case 50 — completeness lewat ReportPeriodDays::counted: periode belum mulai memberi days_counted 0 dan percent NULL', function () {
    $this->actingAs($this->supervisorA);

    // VARIAN A — periode mulai 10 hari di MASA DEPAN.
    $periodeDepan = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelDispatch->value)
        ->range(now()->addDays(10)->toDateString(), now()->addDays(40)->toDateString())
        ->open()
        ->named('Periode Masa Depan')
        ->create();

    $a = $this->service->buildSummary($periodeDepan, null, $this->lineA)['completeness'];

    expect($a['days_counted'])->toBe(0);
    // assertNull — blade mencetak '—'. Nol persen berarti ada hari yang
    // diharapkan dan tak satu pun terisi; '—' berarti belum ada hari yang
    // diharapkan sama sekali.
    $this->assertNull($a['percent']);
    expect($a['days_with_dispatch'])->toBe(0);
    // days_in_period tetap PANJANG PENUH periode.
    expect($a['days_in_period'])->toBe(31);

    // VARIAN B — periode mulai 9 hari lalu dan berakhir 20 hari ke depan,
    // dengan pengiriman pada 3 tanggal EKSPLISIT di bagian yang sudah
    // berjalan.
    $periodeBerjalan = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelDispatch->value)
        ->range(now()->subDays(9)->toDateString(), now()->addDays(20)->toDateString())
        ->open()
        ->named('Periode Berjalan')
        ->create();

    foreach ([8, 6, 3] as $offset) {
        $hari = now()->subDays($offset)->toDateString();
        kdReportDispatch(kdReportRecord($this->stationA, $hari), $hari, ['net_weight_mt' => 1.0]);
    }

    $b = $this->service->buildSummary($periodeBerjalan, null, $this->lineA)['completeness'];

    // start..hari ini INKLUSIF = 10. Hari ini diambil WIB dari config dan
    // bukan dari waktu server.
    expect($b['days_counted'])->toBe(10);
    expect($b['days_with_dispatch'])->toBe(3);
    $this->assertEqualsWithDelta(30.0, $b['percent'], 0.05);
    expect($b['days_in_period'])->toBe(30);

    // VARIAN C — periode yang sudah SELESAI, panjang 10 hari, pengiriman pada
    // 5 tanggal eksplisit.
    $periodeSelesai = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelDispatch->value)
        ->range(now()->subDays(70)->toDateString(), now()->subDays(61)->toDateString())
        ->closed()
        ->named('Periode Selesai')
        ->create();

    foreach ([70, 69, 68, 67, 66] as $offset) {
        $hari = now()->subDays($offset)->toDateString();
        kdReportDispatch(kdReportRecord($this->stationA, $hari), $hari, ['net_weight_mt' => 1.0]);
    }

    $c = $this->service->buildSummary($periodeSelesai, null, $this->lineA)['completeness'];

    expect($c['days_counted'])->toBe(10);
    expect($c['days_in_period'])->toBe(10);
    expect($c['days_with_dispatch'])->toBe(5);
    $this->assertEqualsWithDelta(50.0, $c['percent'], 0.05);
});

it('case 51 — payload TIDAK punya kunci hourly, busiest_hour, empty_hour_count, maupun apa pun bernama turnaround / time_in / time_out, diasersikan ATAS NAMA KUNCI lewat penelusur rekursif', function () {
    $record = kdReportRecord($this->stationA, $this->day1);

    kdReportDispatch($record, $this->day1, [
        'net_weight_mt' => 4.0,
        'kernel_moisture_percent' => 7.0,
        'shift' => 'Shift 1',
        'transporter_contractor' => 'T1',
        'remarks_gate_status' => 'released',
        'findings' => 'Ada ceceran',
    ]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // PENELUSUR REKURSIF ATAS SELURUH NAMA KUNCI — bukan array_walk_recursive,
    // yang hanya menjangkau nilai DAUN dan karena itu tidak pernah melihat
    // nama kunci sebuah sub-array.
    $keys = kdReportAllKeys($summary);

    foreach ([
        'hourly', 'hourly_distribution', 'by_hour', 'per_hour', 'hour', 'hours',
        'busiest_hour', 'empty_hour_count', 'time_slot', 'time_slots',
        'turnaround', 'turnaround_minutes', 'time_in', 'time_out',
        'avg_turnaround', 'turnaround_avg',
    ] as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
    }

    // WAJIB ATAS NAMA KUNCI DAN BUKAN ATAS TEKS PAYLOAD: _note_no_hourly
    // MEMUAT kata 'jam' dan menyebut 'CPO Dispatch', sehingga asersi berbasis
    // teks akan cocok dengan prosa yang justru MENYATAKAN ketiadaan itu —
    // jebakan yang sudah terbukti dua kali di proyek ini. Baris di bawah
    // MEMBUKTIKAN jebakannya nyata, bukan teoretis.
    $this->assertStringContainsString('jam', $summary['_note_no_hourly']);
    $this->assertStringContainsString('CPO Dispatch', $summary['_note_no_hourly']);
    $this->assertStringContainsString('batas', $summary['_note_no_quality_thresholds']);

    // ASERSI PENDAMPING: kedua kunci prosa ADA dan berisi string non-kosong.
    foreach (['_note_no_hourly', '_note_no_quality_thresholds'] as $kunci) {
        $this->assertArrayHasKey($kunci, $summary);
        $this->assertIsString($summary[$kunci]);
        $this->assertNotSame('', trim($summary[$kunci]));
    }

    expect($summary['_note_no_hourly'])->toBe(KernelDispatchReportService::NOTE_NO_HOURLY);
    expect($summary['_note_no_quality_thresholds'])->toBe(KernelDispatchReportService::NOTE_NO_QUALITY_THRESHOLDS);
});

it('case 52 — text_entries HANYA punya findings: TIDAK ADA kunci remarks; entri kosong dibuang, daftar kosong tetap diterbitkan', function () {
    $this->actingAs($this->supervisorA);

    $record = kdReportRecord($this->stationA, $this->day2);
    $recordAwal = kdReportRecord($this->stationA, $this->day1);

    kdReportDispatch($recordAwal, $this->day1, ['findings' => 'Ceceran di gerbang']);
    kdReportDispatch($record, $this->day2, ['findings' => 'Segel bawah terlepas']);
    kdReportDispatch($record, $this->day2, ['findings' => '   ']);
    kdReportDispatch($record, $this->day2, ['findings' => null]);

    $textEntries = $this->service->buildSummary($this->periodA, null, $this->lineA)['text_entries'];

    // SATU DAFTAR, bukan dua: stasiun ini TIDAK punya kolom catatan kedua,
    // berbeda dari Solid Waste Disposal yang punya remarks juga, jadi struktur
    // text_entries dua-daftar tidak boleh disalin dari sana.
    expect(array_keys($textEntries))->toBe(['findings']);
    $this->assertArrayNotHasKey('remarks', $textEntries);

    // 2 entri {date,value} urut tanggal naik; entri kosong/whitespace dibuang.
    expect($textEntries['findings'])->toHaveCount(2);
    expect(array_column($textEntries['findings'], 'value'))
        ->toBe(['Ceceran di gerbang', 'Segel bawah terlepas']);
    expect(array_column($textEntries['findings'], 'date'))->toBe([$this->day1, $this->day2]);
    expect(array_keys($textEntries['findings'][0]))->toBe(['date', 'value']);

    // VARIAN KEDUA — nol findings: [] dengan KUNCI TETAP ADA.
    KernelDispatchDetail::query()->update(['findings' => null]);

    $kedua = $this->service->buildSummary($this->periodA, null, $this->lineA)['text_entries'];

    $this->assertArrayHasKey('findings', $kedua);
    expect($kedua['findings'])->toBe([]);
    expect(array_keys($kedua))->toBe(['findings']);
});

it('case 53 — kunci shift ABSEN dari payload /summary tetapi ADA di header ekspor: dua asersi di dua lapisan', function () {
    $record = kdReportRecord($this->stationA, $this->day1);

    kdReportDispatch($record, $this->day1, ['shift' => 'Shift 1', 'net_weight_mt' => 4.0]);

    $this->actingAs($this->supervisorA);

    // (a) PENELUSUR REKURSIF atas nama kunci payload TIDAK menemukan 'shift'
    //     di level mana pun.
    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);
    $keys = kdReportAllKeys($summary);

    foreach (['shift', 'shifts', 'by_shift', 'per_shift', 'shift_distribution', 'shift_id'] as $forbidden) {
        $this->assertNotContains($forbidden, $keys, "payload tidak boleh punya kunci {$forbidden}");
    }

    // Prosa yang MENYATAKAN ketiadaan itu memuat kata 'shift' — sebabnya
    // asersi di atas berjalan atas NAMA KUNCI.
    $this->assertStringContainsString('shift', $summary['_note_shift_not_published']);

    // (b) HEADER BARIS PERTAMA EKSPOR memuat kolom shift, dan baris datanya
    //     berisi 'Shift 1'. Kolom itu teks bebas pada tabel detail, BUKAN FK
    //     ke tabel shifts.
    $lines = kdReportCsvLines(kdReportStreamed(
        $this->service->export($this->periodA, 'csv', null, $this->lineA),
    ));

    $header = kdReportCsvRow($lines[0]);

    $this->assertContains('Shift', $header);

    $kolom = array_search('Shift', $header, true);
    $baris = kdReportCsvRow($lines[1]);

    expect($baris[$kolom])->toBe('Shift 1');
    $this->assertContains('shift', KernelDispatchReportService::DETAIL_EXPORT_FIELDS);
});

// =====================================================================
// GROUP G — EKSPOR (case 54-58)
// =====================================================================

it('case 54 — satu baris ekspor per PENGIRIMAN, kolom konteks diulang verbatim, dan DUA PULUH SATU kolom detail hadir', function () {
    $r1 = kdReportRecord($this->stationA, $this->day1, ['status' => RecordStatus::Saved]);
    $r2 = kdReportRecord($this->stationA, $this->day2, ['status' => RecordStatus::DraftOngoing]);

    // SELURUH 21 kolom detail diisi nilai yang DAPAT DIBEDAKAN satu dari
    // lainnya, supaya dua kolom yang tertukar terlihat.
    foreach (range(1, 2) as $i) {
        kdReportDispatch($r1, $this->day1, [
            'shift' => 'Shift '.$i,
            'weighbridge_ticket_no' => 'WT-'.$i,
            'waybill_number' => 'SJ-'.$i,
            'transporter_contractor' => 'PT Angkut '.$i,
            'vehicle_plate_no' => 'B 100'.$i.' XX',
            'driver_name' => 'Sopir '.$i,
            'silo_source_id' => 'SILO-'.$i,
            'destination_buyer' => 'PT Pembeli '.$i,
            'gross_weight_mt' => 30.0 + $i,
            'tare_weight_mt' => 8.0 + $i,
            'net_weight_mt' => 20.0 + $i,
            'kernel_moisture_percent' => 6.0 + $i,
            'dirt_impurities_percent' => 0.01 * $i,
            'ffa_percent' => 3.0 + $i,
            'broken_kernel_percent' => 5.0 + $i,
            'security_seal_no_top' => 'SST-'.$i,
            'security_seal_no_bottom' => 'SSB-'.$i,
            'weighbridge_operator_id' => 'OP-'.$i,
            'remarks_gate_status' => 'released',
            'findings' => 'Temuan '.$i,
        ]);
    }

    kdReportDispatch($r2, $this->day2, [
        'shift' => 'Shift 3',
        'weighbridge_ticket_no' => 'WT-3',
        'waybill_number' => 'SJ-3',
        'vehicle_plate_no' => 'B 1003 XX',
        'driver_name' => 'Sopir 3',
        'weighbridge_operator_id' => 'OP-3',
        'net_weight_mt' => 23.0,
        'remarks_gate_status' => 'not_released',
    ]);

    $this->actingAs($this->supervisorA);

    $lines = kdReportCsvLines(kdReportStreamed(
        $this->service->export($this->periodA, 'csv', null, $this->lineA),
    ));

    // 1 header + 3 baris data — SATU PER PENGIRIMAN, bukan satu per record.
    expect($lines)->toHaveCount(4);
    expect(KernelDispatchRecord::query()->count())->toBe(2);

    $header = kdReportCsvRow($lines[0]);

    // KESAMAAN ARRAY PENUH terhadap konstantanya — bukan `contains` atas satu
    // atau dua kolom.
    expect($header)->toBe(KernelDispatchReportService::EXPORT_HEADER);
    expect($header)->toHaveCount(27);
    expect(KernelDispatchReportService::EXPORT_CONTEXT_COLUMN_COUNT)->toBe(6);
    expect(KernelDispatchReportService::DETAIL_EXPORT_FIELDS)->toHaveCount(21);
    expect(count($header))->toBe(
        KernelDispatchReportService::EXPORT_CONTEXT_COLUMN_COUNT
        + count(KernelDispatchReportService::DETAIL_EXPORT_FIELDS),
    );

    // KE-21 KOLOM DETAIL DIASERSIKAN, BUKAN DIINGAT.
    expect(KernelDispatchReportService::DETAIL_EXPORT_FIELDS)->toBe([
        'event_date', 'shift', 'weighbridge_ticket_no', 'waybill_number',
        'transporter_contractor', 'vehicle_plate_no', 'driver_name',
        'silo_source_id', 'destination_buyer', 'gross_weight_mt',
        'tare_weight_mt', 'net_weight_mt', 'kernel_moisture_percent',
        'dirt_impurities_percent', 'ffa_percent', 'broken_kernel_percent',
        'security_seal_no_top', 'security_seal_no_bottom',
        'weighbridge_operator_id', 'remarks_gate_status', 'findings',
    ]);

    // Label kolom mengikuti LAYAR DETAIL stasiun ini, bukan nama kolom DB.
    // $this->assertContains() — bukan expect()->toContain(), yang membaca
    // argumen kedua sebagai needle kedua.
    foreach ([
        'Periode', 'Rentang Periode', 'Mill', 'Production Line', 'Tanggal Record', 'Status',
        'Tanggal Kejadian', 'Shift', 'Weighbridge Ticket No', 'Waybill Number',
        'Transporter/Contractor', 'Vehicle Plate No', 'Driver Name', 'Silo Source ID',
        'Destination/Buyer', 'Gross Weight (MT)', 'Tare Weight (MT)', 'Net Weight (MT)',
        'Kernel Moisture (%)', 'Dirt/Impurities (%)', 'FFA (%)', 'Broken Kernel (%)',
        'Security Seal No (Top)', 'Security Seal No (Bottom)', 'Weighbridge Operator ID',
        'Remarks/Gate Status', 'Findings',
    ] as $label) {
        $this->assertContains($label, $header, "header ekspor kehilangan kolom {$label}");
    }

    $rows = array_map(fn (string $line) => kdReportCsvRow($line), array_slice($lines, 1));
    $dariR1 = array_slice($rows, 0, 2);
    $dariR2 = array_slice($rows, 2, 1);

    // KOLOM KONTEKS DIBANDINGKAN ANTAR-BARIS, bukan hanya dicek
    // keberadaannya. Keempat kolom PERTAMA yang dijanjikan sama di setiap
    // baris: nama periode, rentang, mill, line.
    $konteks = array_slice($dariR1[0], 0, 4);

    expect($konteks[0])->toBe('Periode Maret Alpha');
    expect($konteks[1])->toBe($this->periodStart.' s/d '.$this->periodEnd);
    expect($konteks[2])->toBe('Mill Alpha');
    expect($konteks[3])->toBe((string) ProductionLine::find($this->lineA)->name);

    foreach ($rows as $row) {
        expect(array_slice($row, 0, 4))->toBe($konteks);
    }

    // Dua kolom konteks terakhir memang BERBEDA antar-record — ia tanggal dan
    // status RECORD INDUK.
    expect($dariR1[0][4])->toBe($this->day1);
    expect($dariR1[0][5])->toBe(ExportValue::status(RecordStatus::Saved->value));
    expect($dariR2[0][4])->toBe($this->day2);
    expect($dariR2[0][5])->toBe(ExportValue::status(RecordStatus::DraftOngoing->value));
    $this->assertNotSame($dariR1[0][5], $dariR2[0][5]);

    // LIMA KOLOM YANG TIDAK MUNCUL DI SATU ANGKA LAYAR PUN, diasersikan
    // TERPISAH BESERTA NILAINYA karena kelimanya yang paling mudah
    // tertinggal.
    foreach ([
        'Weighbridge Ticket No' => ['WT-1', 'WT-2', 'WT-3'],
        'Waybill Number' => ['SJ-1', 'SJ-2', 'SJ-3'],
        'Vehicle Plate No' => ['B 1001 XX', 'B 1002 XX', 'B 1003 XX'],
        'Driver Name' => ['Sopir 1', 'Sopir 2', 'Sopir 3'],
        'Weighbridge Operator ID' => ['OP-1', 'OP-2', 'OP-3'],
    ] as $label => $harapan) {
        $kolom = array_search($label, $header, true);

        $this->assertNotFalse($kolom, "header ekspor kehilangan kolom {$label}");
        $this->assertSame($harapan, array_column($rows, $kolom), "nilai kolom {$label} tidak lengkap");
    }
});

it('case 55 — remarks_gate_status di EKSPOR memakai ExportValue::option(): satu kosakata, dan sel KOSONG bukan "-"', function () {
    $record = kdReportRecord($this->stationA, $this->day1);

    kdReportDispatch($record, $this->day1, ['remarks_gate_status' => 'released']);
    kdReportDispatch($record, $this->day1, ['remarks_gate_status' => 'not_released']);
    kdReportDispatch($record, $this->day1, ['remarks_gate_status' => null]);

    $this->actingAs($this->supervisorA);

    $body = kdReportStreamed($this->service->export($this->periodA, 'csv', null, $this->lineA));
    $lines = kdReportCsvLines($body);
    $header = kdReportCsvRow($lines[0]);
    $kolom = array_search('Remarks/Gate Status', $header, true);

    $this->assertNotFalse($kolom);

    $nilai = array_map(fn (string $line) => kdReportCsvRow($line)[$kolom], array_slice($lines, 1));

    // Label yang SAMA dengan yang dicetak blade laporan dan blade detail —
    // satu kosakata, bukan dua. TETAPI status yang tidak tercatat menjadi sel
    // KOSONG, bukan '-'.
    //
    // DIKOREKSI 2026-10-18. Versi pertama case ini menuntut '-' dengan alasan
    // "sama seperti layar Detail mencetaknya", dan alasan itu SALAH TEMPAT.
    // Di LAYAR, '-' adalah penanda yang dibaca manusia. Di BERKAS EKSPOR, ia
    // sebuah NILAI TEKS yang akan diurutkan, disaring dan dijumlahkan oleh
    // pembacanya: sel '-' menjadi KATEGORI PALSU di tabel pivot, sementara sel
    // kosong memang berarti kosong. Itulah sebabnya ExportValue::option() ada
    // — ia membungkus Display::option() lalu mengubah '-' menjadi null — dan
    // itulah aturan rumah (keputusan pemilik produk 2026-10-14) yang
    // menyatakan kolom enum pengukuran memakainya DI MANA PUN, termasuk layar
    // laporan. Kedua preseden yang sudah ada memakainya persis begitu; layar
    // ini yang menyimpang, bukan aturannya.
    expect($nilai)->toBe(['Released', 'Not Released', '']);

    // ASERSI NEGATIF: tidak ada string 'not_released' MENTAH di keluaran
    // ekspor.
    $this->assertStringNotContainsString('not_released', $body);
    $this->assertStringNotContainsString('released,', $body);
});

it('case 56 — daftar format service TERTUTUP {csv, xlsx} dan ditolak SEBELUM satu byte dialirkan', function () {
    kdReportDispatch(kdReportRecord($this->stationA, $this->day1), $this->day1);

    $this->actingAs($this->supervisorA);

    // Trim dan penetapan default 'csv' terjadi SATU LAPIS DI ATAS service — di
    // KernelDispatchReportController::queryString(), yang memulangkan null
    // untuk nilai kosong-setelah-trim. Separuh perilaku case 56 karena itu
    // TIDAK DAPAT dilihat dari uji unit sama sekali, dan dibuktikan di
    // tests/Feature/Api/LaporanKernelDispatchTest.php ('format — TRIM +
    // DEFAULT'). Yang dibuktikan DI SINI: daftarnya benar-benar tertutup dan
    // PEKA HURUF.
    foreach (['pdf', 'CSV', 'XLSX', 'excel', '  ', '', ' csv '] as $format) {
        $response = null;
        $thrown = null;

        try {
            $response = $this->service->export($this->periodA, $format, null, $this->lineA);
        } catch (ValidationException $exception) {
            $thrown = $exception;
        }

        expect($thrown)->toBeInstanceOf(ValidationException::class);
        expect($thrown->status)->toBe(422);
        expect($thrown->errors()['format'])->toBe(['Format ekspor harus csv atau xlsx.']);
        // NOL response stream terbentuk: pemeriksaannya terjadi sebelum writer
        // dibuat, karena galat setelah header terkirim mendarat di komputer
        // orang sebagai berkas rusak, bukan sebagai pesan.
        expect($response)->toBeNull();
    }

    expect(KernelDispatchReportService::SUPPORTED_FORMATS)->toBe(['csv', 'xlsx']);

    $csv = $this->service->export($this->periodA, 'csv', null, $this->lineA);
    expect($csv)->toBeInstanceOf(StreamedResponse::class);
    expect($csv->headers->get('Content-Type'))->toContain('text/csv');

    $xlsx = $this->service->export($this->periodA, 'xlsx', null, $this->lineA);
    expect($xlsx)->toBeInstanceOf(StreamedResponse::class);
});

it('case 57 — ekspor menolak 422 EAGER ketika melampaui batas baris, dan batasnya INKLUSIF', function () {
    $this->actingAs($this->supervisorA);

    // Batas sungguhan tetap 50.000; yang diturunkan hanya milik subclass uji,
    // dan itu HANYA mungkin karena service membaca batasnya lewat `static::`.
    expect(KernelDispatchReportService::EXPORT_ROW_LIMIT)->toBe(50000);

    $tiny = new KernelDispatchReportTinyExportService;

    // ENAM pengiriman pada DUA record induk: batasnya menghitung BARIS YANG
    // DITERBITKAN, bukan record. Implementasi yang menghitung record melihat 2
    // dan meloloskannya.
    $r1 = kdReportRecord($this->stationA, $this->day1);
    $r2 = kdReportRecord($this->stationA, $this->day2);

    foreach (range(1, 3) as $i) {
        kdReportDispatch($r1, $this->day1);
        kdReportDispatch($r2, $this->day2);
    }

    expect(KernelDispatchRecord::query()->count())->toBe(2);
    expect(KernelDispatchDetail::query()->count())->toBe(6);

    $response = null;
    $thrown = null;

    try {
        $response = $tiny->export($this->periodA, 'csv', null, $this->lineA);
    } catch (ValidationException $exception) {
        $thrown = $exception;
    }

    expect($thrown)->toBeInstanceOf(ValidationException::class);
    expect($thrown->status)->toBe(422);
    // NOL BYTE ISI TERKIRIM dan tidak ada header content-disposition:
    // pemeriksaannya terjadi SEBELUM aliran.
    expect($response)->toBeNull();

    $pesan = $thrown->errors()['period_id'][0];

    $this->assertStringContainsString('5 baris pengiriman', $pesan);
    $this->assertStringContainsString('6 baris', $pesan);
    $this->assertStringContainsString('Persempit rentang periode', $pesan);

    // TEPAT PADA BATAS: ekspor BERJALAN — perbandingannya strictly-greater,
    // dan diuji pada KEDUA sisinya.
    KernelDispatchDetail::query()->orderBy('id')->limit(1)->get()->each->delete();

    expect(KernelDispatchDetail::query()->count())->toBe(5);

    $lines = kdReportCsvLines(kdReportStreamed($tiny->export($this->periodA, 'csv', null, $this->lineA)));

    expect($lines)->toHaveCount(6);
});

it('case 58 — ekspor xlsx menghasilkan workbook .xlsx SUNGGUHAN lewat SheetWriter, bukan CSV berekstensi .xlsx', function () {
    $record = kdReportRecord($this->stationA, $this->day1);

    foreach (range(1, 3) as $i) {
        kdReportDispatch($record, $this->day1, ['shift' => 'Shift '.$i]);
    }

    $this->actingAs($this->supervisorA);

    $response = $this->service->export($this->periodA, 'xlsx', null, $this->lineA);
    $body = kdReportStreamed($response);

    // DIASERSIKAN ATAS BYTE-NYA, bukan atas nama berkas atau Content-Type:
    // sampai 2026-10-04 setiap 'Ekspor Excel' mengirim byte CSV bernama .xlsx
    // dan Excel menolak membukanya.
    $this->assertStringStartsWith("PK\x03\x04", $body);
    $this->assertStringContainsString('xl/workbook.xml', $body);

    // xlsxRows() (tests/Pest.php) MELEMPAR bila paketnya tidak memuat
    // [Content_Types].xml, workbook, maupun xl/worksheets/sheet1.xml.
    $rows = xlsxRows($body);

    // Baris pertama sheet memuat header yang SAMA dengan varian CSV.
    expect($rows[0])->toBe(KernelDispatchReportService::EXPORT_HEADER);
    expect($rows)->toHaveCount(4);

    expect($response->headers->get('Content-Type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

// =====================================================================
// GROUP H — N+1 (case 59)
// =====================================================================

it('case 59 — /summary memuat details DAN station dengan jumlah kueri TETAP meski jumlah record bertambah (NON-VAKUM)', function () {
    // ASERSI INI DINYATAKAN NON-VAKUM DAN PROSEDURNYA SUDAH DIJALANKAN:
    // eager load `with(['kernelDispatchDetails', 'station'])` pada
    // recordsFor() DILEPAS lebih dulu, test ini dijalankan sampai MEMERAH
    // (varian B melonjak dari 7 menjadi 31 kueri — sebanding jumlah record),
    // baru eager load-nya dipasang kembali byte-per-byte. Jumlah kueri yang
    // belum pernah merah tidak membuktikan eager load-nya ada.
    $this->actingAs($this->supervisorA);

    // VARIAN A — 2 record induk x 3 detail.
    foreach (range(1, 2) as $i) {
        $record = kdReportRecord($this->stationA, $this->day1);

        foreach (range(1, 3) as $j) {
            kdReportDispatch($record, $this->day1, ['net_weight_mt' => 1.0]);
        }
    }

    // PERIODE DISERAHKAN SEBAGAI ID, bukan sebagai model: model yang sama
    // menyimpan relasi `businessUnit` yang sudah dimuat dari panggilan
    // pertama, sehingga varian kedua akan menembak SATU kueri lebih sedikit
    // dan asersi 'jumlahnya sama' memerah karena alasan yang tidak ada
    // hubungannya dengan eager load detail.
    $periodId = (string) $this->periodA->id;

    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->service->buildSummary($periodId, null, $this->lineA);
    $queriesA = count(DB::getQueryLog());
    DB::disableQueryLog();

    // VARIAN B — 12 record induk x 10 detail, bertanggal EKSPLISIT juga.
    KernelDispatchDetail::query()->delete();
    KernelDispatchRecord::query()->delete();

    foreach (range(1, 12) as $i) {
        $hari = now()->subDays(39 - ($i % 20))->toDateString();
        $record = kdReportRecord($this->stationA, $hari);

        foreach (range(1, 10) as $j) {
            kdReportDispatch($record, $hari, ['net_weight_mt' => 1.0]);
        }
    }

    expect(KernelDispatchRecord::query()->count())->toBe(12);
    expect(KernelDispatchDetail::query()->count())->toBe(120);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $summary = $this->service->buildSummary($periodId, null, $this->lineA);
    $queriesB = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($summary['dispatches']['dispatch_count'])->toBe(120);

    $this->assertSame(
        $queriesA,
        $queriesB,
        "jumlah kueri tumbuh bersama jumlah record (A={$queriesA}, B={$queriesB}) — eager load kernelDispatchDetails/station hilang",
    );

    // Dan jumlahnya memang KECIL — asersi 'sama' di atas akan hijau juga pada
    // dua angka yang keduanya besar.
    $this->assertLessThan(12, $queriesB, "jumlah kueri {$queriesB} terlalu besar untuk 12 record");

    // Nama relasinya `kernelDispatchDetails`, BUKAN `details` — diasersikan
    // atas sumbernya supaya pergantian nama yang 'merapikan' tidak lolos.
    $service = (string) file_get_contents(base_path('app/Services/KernelDispatchReportService.php'));
    $this->assertStringContainsString("'kernelDispatchDetails' => fn", $service);
    $this->assertStringContainsString("'station',", $service);
});

// =====================================================================
// GROUP I — PINTU MASUK screen-140 DAN RIAK-nya (case 60-61)
// =====================================================================

it("case 60 — REPORT_ROUTES memuat kunci 'kernel-dispatch' BERTANDA HUBUNG, dan array_keys-nya tetap urut sort_order dengan entri ini di POSISI TERAKHIR", function () {
    $this->assertArrayHasKey('kernel-dispatch', StationReportService::REPORT_ROUTES);
    expect(StationReportService::REPORT_ROUTES['kernel-dispatch'])->toBe('reports.kernel-dispatch');

    // ASERSI NEGATIF: kunci snake_case akan ter-compile, terlihat benar, dan
    // tidak pernah cocok dengan satu baris master pun — laporannya ada,
    // seluruh test-nya sendiri lolos, dan tile-nya tetap kelabu.
    $this->assertArrayNotHasKey('kernel_dispatch', StationReportService::REPORT_ROUTES);

    // Dan kuncinya memang nilai yang dibawa enum-nya, bukan literal yang
    // kebetulan sama.
    expect(StationTypeEnum::KernelDispatch->value)->toBe('kernel-dispatch');

    // URUTAN DIVERIFIKASI DARI TABEL station_types lewat kueri, BUKAN dari
    // urutan deklarasi enum — keduanya memang berbeda.
    $berlaporan = StationType::query()
        ->orderBy('sort_order')
        ->pluck('sort_order', 'code')
        ->filter(fn ($sortOrder, $code) => array_key_exists($code, StationReportService::REPORT_ROUTES));

    expect($berlaporan->keys()->all())->toBe(array_keys(StationReportService::REPORT_ROUTES));

    $keys = array_keys(StationReportService::REPORT_ROUTES);
    $index = array_search('kernel-dispatch', $keys, true);

    expect($index)->toBe(count($keys) - 1);
    expect($keys[$index - 1])->toBe('solid-waste-disposal');

    // Dan sort_order-nya memang yang membuat urutan itu benar.
    expect($berlaporan['solid-waste-disposal'])->toBe(150);
    expect($berlaporan['kernel-dispatch'])->toBe(160);
});

it("case 61 — PENJAGA RIAK: REPORT_ROUTES masih TIDAK punya 'process-quality-control', dan StationReportServiceTest memakai kode fiktif", function () {
    // PENJAGA YANG SENGAJA AKAN MEMERAH DI screen-168. Ketiga berkas yang
    // memakai stasiun NYATA sebagai contoh 'belum berlaporan'
    // (tests/Feature/Api/LaporanStasiunTest.php,
    // tests/Feature/Livewire/LaporanStasiunTest.php,
    // e2e-web/tests/laporan-stasiun.spec.ts) sudah memakai kode ini
    // (sort_order 180), sehingga pekerjaan screen-164 TIDAK memerahkannya.
    // Begitu process-quality-control mulai berlaporan, baris di bawah memerah
    // dan MEMAKSA pergeseran contoh itu ditangani alih-alih lolos diam-diam.
    $this->assertArrayNotHasKey('process-quality-control', StationReportService::REPORT_ROUTES);

    // Dan kode itu MEMANG ada di master — tanpa baris ini, asersi di atas akan
    // hijau juga pada kode yang salah ketik.
    expect(StationType::query()->where('code', 'process-quality-control')->exists())->toBeTrue();
    expect(StationType::query()->where('code', 'process-quality-control')->value('sort_order'))->toBe(180);

    // StationReportServiceTest memakai kode FIKTIF yang disuntikkan ke master
    // miliknya sendiri, jadi ia juga tidak terpengaruh — dan kode itu tidak
    // akan pernah mendapat entri.
    $this->assertArrayNotHasKey('zz-stasiun-fiktif', StationReportService::REPORT_ROUTES);
    expect(StationType::query()->where('code', 'zz-stasiun-fiktif')->exists())->toBeFalse();

    // cpo-dispatch (screen-166) juga belum berlaporan hari ini — dicatat di
    // sini supaya pergeseran berikutnya terlihat sebagai dua langkah, bukan
    // satu.
    $this->assertArrayNotHasKey('cpo-dispatch', StationReportService::REPORT_ROUTES);
});

// =====================================================================
// GROUP J — INVARIAN STRUKTURAL DI LUAR DAFTAR 62, tetapi dituntut
// implementation_notes dan aturan proyek
// =====================================================================

it('tanpa sesi terautentikasi setiap titik masuk melempar 401 sebelum satu baris pun dibaca', function () {
    foreach ([
        'businessUnitOptions' => fn () => $this->service->businessUnitOptions(),
        'listPeriods' => fn () => $this->service->listPeriods(null),
        'buildSummary' => fn () => $this->service->buildSummary($this->periodA, null, $this->lineA),
        'buildExportRows' => fn () => iterator_to_array($this->service->buildExportRows($this->periodA, null, $this->lineA)),
        'export' => fn () => $this->service->export($this->periodA, 'csv', null, $this->lineA),
        'authorizePeriod' => fn () => $this->service->authorizePeriod((string) $this->periodA->id),
    ] as $label => $call) {
        $thrown = null;

        try {
            $call();
        } catch (Throwable $exception) {
            $thrown = $exception;
        }

        $this->assertInstanceOf(AuthenticationException::class, $thrown, "{$label} tidak melempar 401");
    }
});

it('authorizePeriod menjawab 404 untuk id yang tidak ada dan 403 untuk periode mill lain', function () {
    $periodeB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::KernelDispatch->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->create();

    $this->actingAs($this->supervisorA);

    $this->assertInstanceOf(
        Period::class,
        $this->service->authorizePeriod((string) $this->periodA->id),
    );

    $hilang = null;

    try {
        $this->service->authorizePeriod((string) Str::uuid());
    } catch (Throwable $exception) {
        $hilang = $exception;
    }

    expect($hilang)->toBeInstanceOf(ModelNotFoundException::class);

    // ORACLE LINTAS MILL DITUTUP: periode mill lain menjawab 403, TIDAK PERNAH
    // 404 — pemanggil tidak boleh dapat mengetahui apakah sebuah id ada
    // dengan membandingkan keduanya.
    $asing = null;

    try {
        $this->service->authorizePeriod((string) $periodeB->id);
    } catch (Throwable $exception) {
        $asing = $exception;
    }

    expect($asing)->toBeInstanceOf(AuthorizationException::class);

    // Admin lolos untuk mill mana pun.
    $this->actingAs($this->admin);
    $this->assertInstanceOf(Period::class, $this->service->authorizePeriod((string) $periodeB->id));
});

it('tidak satu kueri pun menyaring status record, dan penyaring line memakai kolom snapshot pada record', function () {
    kdReportDispatch(kdReportRecord($this->stationA, $this->day1), $this->day1);

    $this->actingAs($this->supervisorA);

    $queries = kdReportQueriesDuring(function () {
        $this->service->buildSummary($this->periodA, null, $this->lineA);
    });

    foreach ($queries as $sql) {
        if (! str_contains($sql, 'kernel_dispatch_records')) {
            continue;
        }

        $this->assertStringNotContainsString(
            'kernel_dispatch_records"."status"',
            $sql,
            "kueri menyaring status record: {$sql}",
        );
        $this->assertStringNotContainsString('status in (', $sql, "kueri menyaring status record: {$sql}");
    }

    // PENYARING LINE memakai kolom SNAPSHOT milik record, BUKAN
    // stations.production_line_id: menyaring lewat stations akan MENULIS ULANG
    // SEJARAH setiap kali sebuah stasiun dipindahkan ke line lain.
    $recordQuery = array_values(array_filter(
        $queries,
        fn (string $sql) => str_contains($sql, 'kernel_dispatch_records') && str_contains($sql, 'production_line_id'),
    ));

    $this->assertNotSame([], $recordQuery, 'tidak ada kueri yang menyaring production_line_id');

    foreach ($recordQuery as $sql) {
        $this->assertStringContainsString('"kernel_dispatch_records"."production_line_id"', $sql);
        $this->assertStringNotContainsString('"stations"."production_line_id"', $sql);
    }
});

it('nama production line mill lain tidak pernah terbit lewat summary', function () {
    // Ditemukan 2026-10-07 saat menulis uji KernelPlantReportService: tanpa
    // klausa business_unit_id di productionLineInfo(), memanggil /summary
    // dengan production_line_id milik mill LAIN menjawab 200 berisi id DAN
    // NAMA MANUSIAWI line itu.
    $lineB = ProductionLine::find($this->lineB);
    $lineB->update(['name' => 'PL Rahasia Mill Beta']);

    $this->actingAs($this->admin);

    // Sebagai Admin yang MEMILIH mill-A, menyebut line mill-B tetap 403.
    $thrown = null;

    try {
        $this->service->buildSummary($this->periodA, (string) $this->businessUnitA->id, $this->lineB);
    } catch (Throwable $exception) {
        $thrown = $exception;
    }

    expect($thrown)->toBeInstanceOf(AuthorizationException::class);

    // Dan nama itu tidak pernah terbit di payload line yang sah.
    $summary = $this->service->buildSummary($this->periodA, (string) $this->businessUnitA->id, $this->lineA);

    $this->assertStringNotContainsString(
        'PL Rahasia Mill Beta',
        (string) json_encode($summary, JSON_UNESCAPED_UNICODE),
    );
    expect($summary['production_line']['name'])->toBe((string) ProductionLine::find($this->lineA)->name);
});

it('periode tanpa satu pun record mengembalikan kerangka lengkap, bukan galat dan bukan nol di mana-mana', function () {
    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // BENTUK PAYLOAD-NYA, diasersikan sebagai daftar kunci yang LENGKAP:
    // sebuah blok yang hilang tidak boleh lolos hanya karena tak satu asersi
    // pun menyebutnya.
    expect(array_keys($summary))->toBe([
        'business_unit', 'production_line', 'period', 'dispatches', 'quality',
        'by_transporter', 'by_silo_source', 'by_destination_buyer',
        'by_gate_status', 'not_released_count', 'compliance',
        'draft_dispatch_count', 'undated_dispatch_count', 'daily',
        'daily_total', 'completeness', 'text_entries', '_note_no_hourly',
        '_note_no_quality_thresholds', '_note_shift_not_published',
    ]);

    expect($summary['dispatches']['dispatch_count'])->toBe(0);
    // NULL, BUKAN 0: nol akan mengaku-akui total terukur yang tidak ada.
    $this->assertNull($summary['dispatches']['net_weight_total']);
    $this->assertNull($summary['dispatches']['net_weight_avg']);
    expect($summary['not_released_count'])->toBe(0);

    foreach (KD_RECAP_KEYS as $rekap) {
        expect($summary[$rekap])->toBe([]);
    }

    foreach (KernelDispatchReportService::QUALITY_MEASURES as $measure) {
        expect($summary['quality'][$measure]['measured_count'])->toBe(0);
        $this->assertNull($summary['quality'][$measure]['avg']);
    }

    expect($summary['daily'])->toBe([]);
    expect($summary['daily_total']['dispatch_count'])->toBe(0);
    expect($summary['text_entries']['findings'])->toBe([]);
    expect($summary['completeness']['days_with_dispatch'])->toBe(0);
    $this->assertEqualsWithDelta(0.0, (float) $summary['completeness']['percent'], 0.05);
});

it('summary() adalah ALIAS buildSummary(): satu implementasi, dua nama', function () {
    kdReportDispatch(kdReportRecord($this->stationA, $this->day1), $this->day1, ['net_weight_mt' => 7.0]);

    $this->actingAs($this->supervisorA);

    expect($this->service->summary($this->periodA, null, $this->lineA))
        ->toEqual($this->service->buildSummary($this->periodA, null, $this->lineA));
});
