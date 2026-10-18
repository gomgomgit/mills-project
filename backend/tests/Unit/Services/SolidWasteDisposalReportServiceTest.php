<?php

/**
 * SolidWasteDisposalReportServiceTest — screen-162--laporan-solid-waste-disposal-web
 * / usecase-168--laporan-solid-waste-disposal-web (Laporan Periode Solid Waste
 * Disposal), terhadap App\Services\SolidWasteDisposalReportService.
 *
 * SATU `it()` PER unit_test_case pada tech spec — KEEMPAT PULUH DELAPAN, dalam
 * urutan spec-nya sendiri. Penomoran `case N` di bawah SATU-BASIS, sementara
 * spec menyimpannya sebagai array JSON nol-basis: unit_test_cases[0] adalah
 * `case 1` di sini. Konvensi yang sama dengan keenam belas berkas saudaranya.
 *
 * `uses(TestCase::class, RefreshDatabase::class)` WAJIB: tests/Pest.php hanya
 * mengikat Tests\TestCase ke Feature/, jadi tanpa baris itu suite Unit tidak
 * punya container aplikasi sama sekali — auth()->user(), Eloquent, dan faker di
 * dalam factory semuanya meledak.
 *
 * ======================================================================
 * BENTUK LAPORAN INI PER-TRANSAKSI, DAN SETIAP JEBAKAN DI BAWAH MENGIKUTINYA
 * ======================================================================
 * `solid_waste_disposal_details` TIDAK punya READING_FIELDS dan tidak punya
 * grid 24 slot: satu barisnya adalah SATU MUATAN KELUAR. Presedennya
 * WeighbridgeReportServiceTest (screen-143) dan GradingReportServiceTest
 * (screen-146), BUKAN ProcessWaterReportServiceTest (screen-160) yang bergrid.
 *
 *   1. SATUAN HITUNGNYA BARIS DETAIL, BUKAN RECORD (case 14). Satu record induk
 *      membawa banyak muatan, jadi `loads.load_count` 5 untuk satu record
 *      ber-5-detail adalah perbedaan bentuk terpenting terhadap laporan
 *      bergrid — dan satu-satunya angka yang dapat membedakan implementasi yang
 *      benar dari yang menghitung record.
 *   2. NULL BUKAN NOL (case 19, 21, 41). net_weight_total/avg NULL — bukan 0.0 —
 *      ketika tak satu muatan pun tertimbang. Diasersikan sebagai IDENTITAS
 *      null (`toBeNull()`), tidak pernah sebagai falsy: 0.0 harus memerahkan
 *      test-nya.
 *   3. SETIAP TINGKAT MENERBITKAN PENYEBUTNYA (case 18, 20, 29). Rata-rata atas
 *      penyebut yang tak terlihat tidak dapat diperiksa siapa pun, dan aturan
 *      itu berlaku di blok `loads`, di tiap baris keempat rekap, DAN di tiap
 *      baris `daily[]`.
 *   4. BARIS NULL ADALAH SEBUAH KELOMPOK, BUKAN BARIS YANG DIBUANG (case 23-28).
 *      Penjumlahan load_count tiap rekap WAJIB sama dengan loads.load_count;
 *      asersi penjumlahan itulah yang menangkap baris null yang dibuang.
 *
 * ----------------------------------------------------------------------
 * TANGGAL FIXTURE SELALU RELATIF, TIDAK PERNAH KALENDER YANG DIPAKU
 * ----------------------------------------------------------------------
 * Tech spec menulis tanggal kalender (2019-03-xx); yang dipertahankan di sini
 * MAKSUDNYA, bukan digitnya — persis seperti ProcessWaterReportServiceTest.
 * Dua sebab, keduanya menentukan:
 *
 *   - `$this->periodA` HARUS selalu sudah SELESAI supaya days_counted ===
 *     days_in_period === 31. Tanggal kalender yang dipaku berhenti memenuhi
 *     syarat itu begitu kalender melewatinya.
 *   - SolidWasteDisposalRecordFactory men-default `date` ke
 *     dateTimeBetween('-30 days','now') dan StationFactory bersarang membuat
 *     ProductionLine sendiri. Asersi "tepat N muatan" yang menumpang default
 *     itu gagal sesekali tanpa ada hubungannya dengan layar ini (flake pabrik
 *     tersarang bertanggal acak). SETIAP record di bawah memaku tanggalnya
 *     lewat helper, tidak satu pun menumpang default.
 *
 * ----------------------------------------------------------------------
 * KOLOM `event_date` NOT NULL DI SKEMA — DAN ITU MENGUBAH CASE 32 DAN 33
 * ----------------------------------------------------------------------
 * Migrasi 2026_08_31_000003 mendeklarasikan `$table->date('event_date')` TANPA
 * ->nullable(), dan tidak ada satu migrasi pun sesudahnya yang mengubahnya.
 * Jadi sebuah muatan ber-event_date NULL TIDAK DAPAT DISIMPAN: SQLite (dan
 * PostgreSQL) menolak INSERT-nya. Case 56 di bawah MEMBUKTIKAN penolakan itu,
 * supaya fakta ini tidak lagi ditemukan ulang dari nol.
 *
 * Konsekuensinya `undated_load_count`, cabang `date === null` di loadsOf(),
 * `continue` di dailyOf() dan kotak `undated-note` di blade semuanya TIDAK
 * DAPAT DICAPAI lewat basis data hari ini. Cabangnya tetap diuji — case 32 dan
 * 33 memasang koleksi record DI MEMORI lewat subclass yang meng-override
 * recordsFor(), sehingga agregasinya (loadsOf → breakdownOf/dailyOf/
 * dailyTotalOf) benar-benar dijalankan atas muatan tak bertanggal. Yang TIDAK
 * dilakukan: berpura-pura menyemainya lewat factory dan diam-diam menguji nol
 * baris.
 */

use App\Enums\RecordStatus;
use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\ProductionLine;
use App\Models\SolidWasteDisposalDetail;
use App\Models\SolidWasteDisposalRecord;
use App\Models\Station;
use App\Models\StationType;
use App\Models\User;
use App\Services\SolidWasteDisposalReportService;
use App\Services\StationReportService;
use App\Support\ExportValue;
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
 * SPY untuk case 5 — aturan 'gagal tertutup'.
 *
 * allBusinessUnits() publik pada service justru supaya dapat di-override di
 * sini. Mengasersikan 422-nya saja akan sama-sama hijau terhadap implementasi
 * yang MEMBANGUN daftar seluruh mill lebih dulu lalu melempar sesudahnya — dan
 * implementasi itu adalah kebocoran lintas mill yang menunggu satu refactor
 * lagi. Hanya hitungan panggilan NOL yang membuktikan daftarnya tidak pernah
 * disusun.
 */
class SolidWasteDisposalReportAllBusinessUnitsSpy extends SolidWasteDisposalReportService
{
    public int $allBusinessUnitsCalls = 0;

    public function allBusinessUnits(): Collection
    {
        $this->allBusinessUnitsCalls++;

        return parent::allBusinessUnits();
    }
}

/**
 * Menurunkan batas ekspor supaya perbedaan "baris MUATAN versus record induk"
 * dapat dibuktikan tanpa menyemai 50.000 baris.
 *
 * Hanya mungkin karena service membaca batasnya lewat `static::`, bukan
 * `self::` — pilihan itulah yang subclass ini ada untuk mengujinya.
 */
class SolidWasteDisposalReportTinyExportService extends SolidWasteDisposalReportService
{
    public const EXPORT_ROW_LIMIT = 5;
}

/**
 * Service yang membaca record dari MEMORI alih-alih dari basis data.
 *
 * ADA KARENA SKEMANYA, BUKAN KARENA KEMALASAN: `event_date` NOT NULL (lihat
 * docblock berkas), sehingga muatan tak bertanggal tidak dapat disimpan sama
 * sekali. Meng-override recordsFor() membuat loadsOf(), breakdownOf(),
 * dailyOf() dan dailyTotalOf() — yaitu SELURUH agregasi yang cabang null-nya
 * hidup di dalamnya — tetap dijalankan sungguhan atas muatan itu.
 */
class SolidWasteDisposalReportInMemoryService extends SolidWasteDisposalReportService
{
    /** @var Collection<int, SolidWasteDisposalRecord> */
    public static Collection $records;

    protected function recordsFor(Period $period, string $productionLineId): Collection
    {
        return static::$records;
    }
}

/**
 * Satu record induk Solid Waste Disposal, TANGGALNYA DIEJA sebagai argumen
 * posisional karena ia yang menentukan keanggotaan periode dan tidak boleh
 * pernah diserahkan pada default acak factory.
 */
function swdReportRecord(Station $station, string $date, array $attributes = []): SolidWasteDisposalRecord
{
    return SolidWasteDisposalRecord::factory()
        ->forStation($station)
        ->onDate($date)
        ->create(array_merge([
            'solid_waste_disposal_id' => 'SWD-'.Str::upper(Str::random(6)),
            'status' => RecordStatus::Saved,
            'note' => null,
        ], $attributes));
}

/**
 * Satu baris detail = SATU MUATAN KELUAR.
 *
 * SELURUH kolom yang berarti bagi laporan di-default menjadi NULL di sini, dan
 * itu disengaja: SolidWasteDisposalDetailFactory mengisi keempat kolom
 * kategoris, kedua nomor dokumen, remarks dan findings dengan nilai faker acak,
 * sehingga fixture yang menumpangnya akan mengasersikan rekap yang isinya
 * berubah tiap jalan. Setiap test di bawah menyebut TEPAT kolom yang memang
 * jadi subjeknya.
 *
 * `net_weight_mt` di-default 1.0 dan bruto/tara di-default NULL — bentuk yang
 * sama dengan dua baris fixture e2e hari ini (netto 5 MT sementara bruto DAN
 * tara null). Laporan ini MEMBACA net_weight_mt apa adanya; menghitung ulang
 * bruto−tara akan mengubah 5 MT menjadi null pada data yang sudah ada.
 */
function swdReportLoad(
    SolidWasteDisposalRecord $record,
    string $eventDate,
    array $values = [],
): SolidWasteDisposalDetail {
    return SolidWasteDisposalDetail::factory()
        ->forRecord($record)
        ->create(array_merge([
            'event_date' => $eventDate,
            'shift' => null,
            'solid_waste_type' => null,
            'source_station' => null,
            'disposal_utilization_site' => null,
            'purpose_end_use' => null,
            'gate_pass_no' => null,
            'security_seal_no' => null,
            'remarks' => null,
            'findings' => null,
            'gross_weight_mt' => null,
            'tare_weight_mt' => null,
            'net_weight_mt' => 1.0,
        ], $values));
}

/**
 * Satu muatan DI MEMORI — dipakai HANYA oleh case 32/33, tempat event_date
 * null dibutuhkan dan skema menolaknya.
 */
function swdReportMemoryLoad(?string $eventDate, array $values = []): SolidWasteDisposalDetail
{
    $detail = new SolidWasteDisposalDetail(array_merge([
        'event_date' => $eventDate,
        'solid_waste_type' => null,
        'source_station' => null,
        'disposal_utilization_site' => null,
        'purpose_end_use' => null,
        'gate_pass_no' => null,
        'security_seal_no' => null,
        'remarks' => null,
        'findings' => null,
        'net_weight_mt' => 1.0,
    ], $values));

    return $detail;
}

/** Satu record induk DI MEMORI yang membawa muatan-muatan di atas. */
function swdReportMemoryRecord(array $details, RecordStatus $status = RecordStatus::Saved): SolidWasteDisposalRecord
{
    $record = new SolidWasteDisposalRecord;
    $record->status = $status;
    $record->setRelation('solidWasteDisposalDetails', collect($details));

    return $record;
}

/** Setiap pernyataan SQL yang berjalan di dalam $callback, huruf kecil. */
function swdReportQueriesDuring(callable $callback): array
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
function swdReportTouched(array $queries, string $table): bool
{
    foreach ($queries as $sql) {
        if (str_contains($sql, $table)) {
            return true;
        }
    }

    return false;
}

/** Berapa pernyataan yang membaca $table. */
function swdReportCountTouching(array $queries, string $table): int
{
    return count(array_filter($queries, fn (string $sql) => str_contains($sql, $table)));
}

/**
 * Seluruh nama KUNCI yang muncul di mana pun, serapa pun dalamnya.
 *
 * DIPAKAI ALIH-ALIH MEMINDAI json_encode: `_note_no_hourly` dan
 * `_note_shift_not_published` adalah KALIMAT MANUSIA di dalam payload ini dan
 * keduanya MEMUAT kata 'jam'/'shift'. Pemindaian teks karena itu menjawab
 * pertanyaan yang salah — ia akan cocok dengan kalimat yang justru MENYATAKAN
 * ketiadaannya. Yang ditanyakan adalah apakah sebuah KUNCI ada.
 *
 * @return list<string>
 */
function swdReportAllKeys(mixed $value): array
{
    if (! is_array($value)) {
        return [];
    }

    $keys = [];

    foreach ($value as $key => $child) {
        if (is_string($key)) {
            $keys[] = $key;
        }

        $keys = array_merge($keys, swdReportAllKeys($child));
    }

    return array_values(array_unique($keys));
}

/** Satu baris rekap untuk satu nilai (null = kelompok 'tidak tercatat'). */
function swdReportEntry(array $rows, ?string $value): ?array
{
    foreach ($rows as $row) {
        if ($row['value'] === $value) {
            return $row;
        }
    }

    return null;
}

/** Posisi baris 'tidak tercatat' di dalam satu rekap, atau -1. */
function swdReportNullIndex(array $rows): int
{
    foreach ($rows as $index => $row) {
        if ($row['value'] === null) {
            return $index;
        }
    }

    return -1;
}

/** Baris daily[] untuk satu tanggal, atau null. */
function swdReportDaily(array $summary, string $date): ?array
{
    foreach ($summary['daily'] as $row) {
        if ($row['date'] === $date) {
            return $row;
        }
    }

    return null;
}

/** Isi sebuah StreamedResponse, ditangkap. ALIRKAN SEKALI per response. */
function swdReportStreamed(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

/** Body CSV yang sudah ditangkap, dipecah menjadi baris non-kosong. */
function swdReportCsvLines(string $body): array
{
    return array_values(array_filter(explode("\n", trim($body)), fn ($line) => trim($line) !== ''));
}

/** Satu baris CSV, diurai. */
function swdReportCsvRow(string $line): array
{
    return str_getcsv(trim($line, "\r"), ',', '"', '\\');
}

beforeEach(function () {
    $this->service = new SolidWasteDisposalReportService;

    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->solidWasteDisposal()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->solidWasteDisposal()->create();

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
        ->stationType(StationTypeEnum::SolidWasteDisposal->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Maret Alpha')
        ->create();
});

// =====================================================================
// GROUP A — MILL MANA, LINE MANA, PERIODE MANA (case 1-13)
// =====================================================================

// ---------------------------------------------------------------------
// Case 1
// ---------------------------------------------------------------------
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

// ---------------------------------------------------------------------
// Case 2
// ---------------------------------------------------------------------
it('case 2 — businessUnitOptions mengembalikan seluruh mill bagi Admin; master kosong mengembalikan [] bukan galat', function () {
    BusinessUnit::factory()->create(['name' => 'Mill C']);
    // Mill Alpha / Mill Beta sudah ada dari beforeEach; ditambah dua lagi
    // supaya urutan NAIK benar-benar diuji dan bukan kebetulan dua entri.
    BusinessUnit::factory()->create(['name' => 'Mill B']);

    $this->actingAs($this->admin);

    $options = $this->service->businessUnitOptions();

    $names = array_column($options, 'name');

    // URUT NAIK, diasersikan sebagai sifat daftarnya — bukan sebagai daftar
    // harfiah: Period::factory() membuat `created_by`-nya sendiri lewat
    // User::factory(), yang ikut melahirkan satu BusinessUnit bernama faker.
    // Daftar harfiah di sini akan merah karena pabrik bersarang, bukan karena
    // urutannya salah.
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
    SolidWasteDisposalDetail::query()->delete();
    SolidWasteDisposalRecord::query()->delete();
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

// ---------------------------------------------------------------------
// Case 3
// ---------------------------------------------------------------------
it('case 3 — periods menolak 422 ketika Admin tidak menyertakan business_unit_id', function () {
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

    // Tidak ada mill default dan tidak ada 'mill pertama' yang dipilihkan:
    // dengan lebih dari satu mill di sistem, tak satu pun terpakai.
    $this->assertGreaterThan(1, BusinessUnit::query()->count());
});

// ---------------------------------------------------------------------
// Case 4
// ---------------------------------------------------------------------
it('case 4 — business_unit_id kiriman DIABAIKAN bagi peran terikat mill, bukan 403', function () {
    // Mill Beta benar-benar ada DAN punya periode SWD-nya sendiri, sehingga
    // sebuah implementasi yang menghormati parameternya akan terlihat.
    $periodeB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::SolidWasteDisposal->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Maret Beta')
        ->create();

    $this->actingAs($this->supervisorA);

    $periods = $this->service->listPeriods((string) $this->businessUnitB->id);

    expect($periods)->toHaveCount(1);
    expect($periods[0]['id'])->toBe((string) $this->periodA->id);

    // ASERSI NEGATIF EKSPLISIT: menolak dengan 403 akan membocorkan keberadaan
    // mill-B lewat perbedaan respons, jadi yang benar adalah 200 berisi data
    // mill sendiri.
    expect(array_column($periods, 'id'))->not->toContain((string) $periodeB->id);
});

// ---------------------------------------------------------------------
// Case 5
// ---------------------------------------------------------------------
it('case 5 — akun terikat mill yang business_unit_id-nya NULL menghasilkan 422, BUKAN 403, dan daftar seluruh mill tidak pernah disusun', function () {
    foreach ([UserRole::Supervisor, UserRole::MillManagement] as $role) {
        $orphan = User::factory()->role($role)->create(['business_unit_id' => null]);
        $this->actingAs($orphan);

        $spy = new SolidWasteDisposalReportAllBusinessUnitsSpy;

        $thrown = null;

        try {
            $spy->listPeriods(null);
        } catch (Throwable $exception) {
            $thrown = $exception;
        }

        // Asersi atas KELAS exception dan kode, bukan hanya atas pesan.
        expect($thrown)->toBeInstanceOf(ValidationException::class);
        expect($thrown)->not->toBeInstanceOf(AuthorizationException::class);
        expect($thrown->status)->toBe(422);
        expect($thrown->errors()['business_unit_id'])
            ->toBe(['Akun Anda belum terhubung ke mill. Hubungi Admin.']);

        // SEPARUH YANG MENENTUKAN: gagal TERTUTUP. Menawarkan daftar seluruh
        // mill sebagai jalan keluar akan mengubah satu baris master yang rusak
        // menjadi kebocoran lintas mill — dan implementasi seperti itu tetap
        // menjawab 422, jadi hanya hitungan panggilan nol yang memisahkannya.
        expect($spy->allBusinessUnitsCalls)->toBe(0);
    }
});

// ---------------------------------------------------------------------
// Case 6
// ---------------------------------------------------------------------
it("case 6 — periods hanya memuat periode yang punya baris period_stations berjenis 'solid-waste-disposal'", function () {
    // P1 = periodA dari beforeEach (SWD saja).
    $p2 = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::Weighbridge->value)
        ->range(now()->subDays(80)->toDateString(), now()->subDays(50)->toDateString())
        ->open()
        ->named('P2 Weighbridge Saja')
        ->create();

    $p3 = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationTypes([StationTypeEnum::SolidWasteDisposal->value, StationTypeEnum::Weighbridge->value])
        ->range(now()->subDays(120)->toDateString(), now()->subDays(90)->toDateString())
        ->open()
        ->named('P3 SWD + Weighbridge')
        ->create();

    $this->actingAs($this->supervisorA);

    $periods = $this->service->listPeriods(null);

    expect($periods)->toHaveCount(2);
    expect(array_column($periods, 'id'))->toBe([(string) $this->periodA->id, (string) $p3->id]);
    expect(array_column($periods, 'id'))->not->toContain((string) $p2->id);

    foreach ($periods as $entry) {
        expect($entry['station_type'])->toBe('solid-waste-disposal');
        expect($entry['station_type_label'])->not->toBe('');
        // Label datang dari tabel master `station_types`, bukan dari enum.
        expect($entry['station_type_label'])->toBe('Solid Waste Disposal');
    }
});

// ---------------------------------------------------------------------
// Case 7
// ---------------------------------------------------------------------
it('case 7 — periods TIDAK menyaring status periode: draft, open dan closed sama-sama terbaca', function () {
    Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::SolidWasteDisposal->value)
        ->range(now()->subDays(80)->toDateString(), now()->subDays(50)->toDateString())
        ->draft()
        ->named('Periode Draft')
        ->create();

    Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::SolidWasteDisposal->value)
        ->range(now()->subDays(120)->toDateString(), now()->subDays(90)->toDateString())
        ->closed()
        ->named('Periode Tertutup')
        ->create();

    $this->actingAs($this->supervisorA);

    $periods = $this->service->listPeriods(null);

    expect($periods)->toHaveCount(3);

    // ASERSI ATAS HIMPUNAN, bukan atas jumlah saja: tiga entri yang kebetulan
    // berstatus sama akan lolos asersi jumlah.
    $statuses = array_column($periods, 'status');
    sort($statuses);

    expect($statuses)->toBe(['closed', 'draft', 'open']);
});

// ---------------------------------------------------------------------
// Case 8
// ---------------------------------------------------------------------
it('case 8 — periods mengembalikan [] ketika mill tidak punya satu pun periode SWD', function () {
    PeriodStation::query()->delete();
    Period::query()->delete();

    foreach ([StationTypeEnum::Weighbridge->value, StationTypeEnum::Grading->value] as $index => $type) {
        Period::factory()
            ->forBusinessUnit($this->businessUnitA)
            ->stationType($type)
            ->range(now()->subDays(80 + 40 * $index)->toDateString(), now()->subDays(50 + 40 * $index)->toDateString())
            ->open()
            ->named('Periode Stasiun Lain '.$index)
            ->create();
    }

    $this->actingAs($this->supervisorA);

    // Pemilih periode kosong adalah keadaan yang DILAPORKAN, bukan galat.
    expect($this->service->listPeriods(null))->toBe([]);
});

// ---------------------------------------------------------------------
// Case 9
// ---------------------------------------------------------------------
it('case 9 — summary menolak 422 ketika period_id absen', function () {
    $this->actingAs($this->supervisorA);

    foreach ([null, ''] as $missing) {
        $thrown = null;

        try {
            $this->service->buildSummary($missing, null, $this->lineA);
        } catch (ValidationException $exception) {
            $thrown = $exception;
        }

        expect($thrown)->toBeInstanceOf(ValidationException::class);
        expect($thrown->status)->toBe(422);
        expect($thrown->errors()['period_id'])->toBe(['Periode Pelaporan wajib dipilih.']);
    }
});

// ---------------------------------------------------------------------
// Case 10
// ---------------------------------------------------------------------
it("case 10 — summary menolak 422 ketika production_line_id absen: tanpa fallback 'line pertama' dan tanpa mode semua-line", function () {
    // DUA line yang MASING-MASING bermuatan — sebuah implementasi yang
    // menjumlahkan keduanya akan menghasilkan 11 + 7 = 18, angka yang tidak
    // dimiliki siapa pun.
    $stationKedua = Station::factory()->forBusinessUnit($this->businessUnitA)->solidWasteDisposal()->create();

    $r1 = swdReportRecord($this->stationA, $this->day1);
    foreach (range(1, 11) as $i) {
        swdReportLoad($r1, $this->day1);
    }

    $r2 = swdReportRecord($stationKedua, $this->day1);
    foreach (range(1, 7) as $i) {
        swdReportLoad($r2, $this->day1);
    }

    $this->actingAs($this->supervisorA);

    $payload = null;
    $thrown = null;

    try {
        $payload = $this->service->buildSummary($this->periodA, null, null);
    } catch (ValidationException $exception) {
        $thrown = $exception;
    }

    expect($thrown)->toBeInstanceOf(ValidationException::class);
    expect($thrown->status)->toBe(422);
    expect($thrown->errors()['production_line_id'])
        ->toBe(['Production Line wajib dipilih untuk menampilkan laporan.']);

    // TIDAK ADA PAYLOAD YANG TERBIT. Kebocoran cakupan yang sudah tiga kali
    // terjadi di proyek ini selalu berbentuk 200 berisi angka gabungan, bukan
    // 500 — jadi 'nol payload' adalah asersinya sendiri.
    expect($payload)->toBeNull();
});

// ---------------------------------------------------------------------
// Case 11
// ---------------------------------------------------------------------
it('case 11 — summary menolak 403 ketika periode milik mill lain', function () {
    $periodeB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::SolidWasteDisposal->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Beta')
        ->create();

    swdReportLoad(swdReportRecord($this->stationB, $this->day1), $this->day1);

    $this->actingAs($this->supervisorA);

    $payload = null;
    $thrown = null;

    $queries = swdReportQueriesDuring(function () use ($periodeB, &$payload, &$thrown) {
        try {
            $payload = $this->service->buildSummary((string) $periodeB->id, null, $this->lineA);
        } catch (Throwable $exception) {
            $thrown = $exception;
        }
    });

    expect($thrown)->toBeInstanceOf(AuthorizationException::class);
    expect($payload)->toBeNull();
    // Tidak satu angka pun terhitung.
    expect(swdReportTouched($queries, 'solid_waste_disposal_details'))->toBeFalse();
});

// ---------------------------------------------------------------------
// Case 12
// ---------------------------------------------------------------------
it('case 12 — summary menolak 403 ketika production_line_id milik mill lain: DITOLAK, bukan diabaikan', function () {
    $this->actingAs($this->supervisorA);

    $thrown = null;

    $queries = swdReportQueriesDuring(function () use (&$thrown) {
        try {
            $this->service->buildSummary($this->periodA, null, $this->lineB);
        } catch (Throwable $exception) {
            $thrown = $exception;
        }
    });

    expect($thrown)->toBeInstanceOf(AuthorizationException::class);
    expect(swdReportTouched($queries, 'solid_waste_disposal_records'))->toBeFalse();

    // KONTRAS YANG DIASERSIKAN BERSAMA case 4, di test yang sama supaya
    // keduanya tidak dapat berubah terpisah: business_unit_id milik mill lain
    // DIABAIKAN (200 berisi mill sendiri) sementara production_line_id milik
    // mill lain DITOLAK (403). Line adalah parameter yang pengguna PILIH
    // secara sadar; mill adalah konteks yang diturunkan dari akunnya.
    $diabaikan = $this->service->buildSummary($this->periodA, (string) $this->businessUnitB->id, $this->lineA);

    expect($diabaikan['business_unit']['id'])->toBe((string) $this->businessUnitA->id);
    expect($diabaikan['business_unit']['name'])->toBe('Mill Alpha');
});

// ---------------------------------------------------------------------
// Case 13
// ---------------------------------------------------------------------
it('case 13 — summary menghitung normal untuk periode berstatus draft maupun closed', function () {
    $draft = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::SolidWasteDisposal->value)
        ->range(now()->subDays(80)->toDateString(), now()->subDays(50)->toDateString())
        ->draft()
        ->named('Periode Draft')
        ->create();

    $closed = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::SolidWasteDisposal->value)
        ->range(now()->subDays(120)->toDateString(), now()->subDays(90)->toDateString())
        ->closed()
        ->named('Periode Tertutup')
        ->create();

    $recordDraft = swdReportRecord($this->stationA, now()->subDays(70)->toDateString());
    $recordClosed = swdReportRecord($this->stationA, now()->subDays(100)->toDateString());

    foreach (range(1, 4) as $i) {
        swdReportLoad($recordDraft, now()->subDays(70)->toDateString());
        swdReportLoad($recordClosed, now()->subDays(100)->toDateString());
    }

    $this->actingAs($this->supervisorA);

    // Status periode TIDAK pernah diperiksa sebagai gate — kunci periode
    // mengatur PENULISAN data, bukan PEMBACAAN laporan.
    $draftSummary = $this->service->buildSummary($draft, null, $this->lineA);
    $closedSummary = $this->service->buildSummary($closed, null, $this->lineA);

    expect($draftSummary['loads']['load_count'])->toBe(4);
    expect($draftSummary['period']['status'])->toBe('draft');
    expect($closedSummary['loads']['load_count'])->toBe(4);
    expect($closedSummary['period']['status'])->toBe('closed');
});

// =====================================================================
// GROUP B — LINGKUP DAN SATUAN HITUNG (case 14-17)
// =====================================================================

// ---------------------------------------------------------------------
// Case 14
// ---------------------------------------------------------------------
it('case 14 — load_count menghitung BARIS DETAIL, bukan record induk', function () {
    $record = swdReportRecord($this->stationA, $this->day1);

    foreach (range(1, 5) as $i) {
        swdReportLoad($record, $this->day1);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // PERBEDAAN BENTUK TERPENTING terhadap laporan bergrid, dan satu-satunya
    // angka yang memisahkan implementasi yang benar dari yang menghitung
    // record: SATU record induk, LIMA muatan.
    expect(SolidWasteDisposalRecord::query()->count())->toBe(1);
    expect($summary['loads']['load_count'])->toBe(5);
    expect($summary['loads']['load_count'])->not->toBe(1);
});

// ---------------------------------------------------------------------
// Case 15
// ---------------------------------------------------------------------
it('case 15 — lingkup baris menyingkirkan record stasiun lain dan record line lain', function () {
    $stationLine2 = Station::factory()->forBusinessUnit($this->businessUnitA)->solidWasteDisposal()->create();
    $line2 = (string) $stationLine2->production_line_id;

    $weighbridgeLine1 = Station::factory()
        ->forProductionLine($this->stationA->production_line_id)
        ->weighbridge()
        ->create();

    $swdLine1 = swdReportRecord($this->stationA, $this->day1);
    foreach (range(1, 3) as $i) {
        swdReportLoad($swdLine1, $this->day1);
    }

    $swdLine2 = swdReportRecord($stationLine2, $this->day1);
    foreach (range(1, 4) as $i) {
        swdReportLoad($swdLine2, $this->day1);
    }

    // Record ber-station_type LAIN di line yang SAMA. Ia memakai tabel record
    // yang sama HANYA bila penyaring stations.type dilepas — dan di sinilah
    // penyaring itu terbukti aktif: record di bawah tetap baris
    // solid_waste_disposal_records, tetapi stasiunnya weighbridge.
    $salahJenis = swdReportRecord($weighbridgeLine1, $this->day1);
    foreach (range(1, 5) as $i) {
        swdReportLoad($salahJenis, $this->day1);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['loads']['load_count'])->toBe(3);

    // ASERSI NEGATIF: 7 (line lain ikut), 8 (jenis lain ikut), 12 (keduanya
    // ikut) tidak pernah muncul. Kedua penyaring aktif, bukan salah satunya.
    expect($summary['loads']['load_count'])->not->toBe(7);
    expect($summary['loads']['load_count'])->not->toBe(8);
    expect($summary['loads']['load_count'])->not->toBe(12);

    // Dan line kedua memang punya angkanya sendiri — bukan nol karena
    // datanya tidak tersimpan.
    expect($this->service->buildSummary($this->periodA, null, $line2)['loads']['load_count'])->toBe(4);
});

// ---------------------------------------------------------------------
// Case 16
// ---------------------------------------------------------------------
it('case 16 — keanggotaan periode ditentukan kolom `date` pada INDUK, inklusif di kedua ujung', function () {
    $a = swdReportRecord($this->stationA, $this->periodStart);
    swdReportLoad($a, $this->periodStart);
    swdReportLoad($a, $this->periodStart);

    $b = swdReportRecord($this->stationA, $this->periodEnd);
    swdReportLoad($b, $this->periodEnd);
    swdReportLoad($b, $this->periodEnd);

    // Record C: induknya SEHARI SEBELUM periode, tetapi SELURUH detail-nya
    // ber-event_date di DALAM rentang. Penyaringnya memakai induk, jadi ia
    // dikecualikan seluruhnya.
    $c = swdReportRecord($this->stationA, $this->dayBeforePeriod);
    swdReportLoad($c, $this->day2);
    swdReportLoad($c, $this->day3);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // 4, bukan 6: kedua ujung rentang ikut terhitung dan record C tidak.
    expect($summary['loads']['load_count'])->toBe(4);
    expect(swdReportDaily($summary, $this->periodStart))->not->toBeNull();
    expect(swdReportDaily($summary, $this->periodEnd))->not->toBeNull();
    expect(swdReportDaily($summary, $this->day2))->toBeNull();
    expect(swdReportDaily($summary, $this->day3))->toBeNull();
});

// ---------------------------------------------------------------------
// Case 17
// ---------------------------------------------------------------------
it('case 17 — satu line dengan LEBIH DARI SATU unit stasiun SWD digabung tepat sekali', function () {
    $unit2 = Station::factory()
        ->forProductionLine($this->stationA->production_line_id)
        ->solidWasteDisposal()
        ->create();

    expect((string) $unit2->production_line_id)->toBe($this->lineA);

    $r1 = swdReportRecord($this->stationA, $this->day1);
    foreach (range(1, 3) as $i) {
        swdReportLoad($r1, $this->day1);
    }

    $r2 = swdReportRecord($unit2, $this->day2);
    foreach (range(1, 2) as $i) {
        swdReportLoad($r2, $this->day2);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // 5 — tidak 3 (satu unit terlewat) dan tidak 10 (join ganda ke `stations`).
    expect($summary['loads']['load_count'])->toBe(5);
    expect($summary['loads']['load_count'])->not->toBe(3);
    expect($summary['loads']['load_count'])->not->toBe(10);

    // Pembaca membaca SATU angka per line: nol pengelompokan per unit.
    expect(swdReportAllKeys($summary))->not->toContain('by_unit');
    expect(swdReportAllKeys($summary))->not->toContain('units');
});

// =====================================================================
// GROUP C — BERAT, PENYEBUT, DAN NULL YANG BUKAN NOL (case 18-21)
// =====================================================================

// ---------------------------------------------------------------------
// Case 18
// ---------------------------------------------------------------------
it('case 18 — total dan rata-rata netto dihitung HANYA atas baris non-null; net_weight_load_count diterbitkan sebagai penyebut', function () {
    $record = swdReportRecord($this->stationA, $this->day1);

    foreach (range(1, 7) as $i) {
        swdReportLoad($record, $this->day1, ['net_weight_mt' => 2.0]);
    }

    foreach (range(1, 3) as $i) {
        swdReportLoad($record, $this->day1, ['net_weight_mt' => null]);
    }

    $this->actingAs($this->supervisorA);

    $loads = $this->service->buildSummary($this->periodA, null, $this->lineA)['loads'];

    expect($loads['net_weight_total'])->toBe(14.0);
    expect($loads['net_weight_avg'])->toBe(2.0);
    expect($loads['net_weight_load_count'])->toBe(7);
    // KUNCINYA ADA, bukan hanya nilainya benar: rata-rata atas penyebut yang
    // tak terlihat adalah angka yang tidak dapat diperiksa siapa pun.
    $this->assertArrayHasKey('net_weight_load_count', $loads);
    expect($loads['load_count'])->toBe(10);
});

// ---------------------------------------------------------------------
// Case 18b — netto DIBACA APA ADANYA, tidak pernah dihitung ulang
// ---------------------------------------------------------------------
it('case 18b — net_weight_mt dibaca apa adanya dan TIDAK PERNAH dihitung ulang dari bruto dikurangi tara', function () {
    $record = swdReportRecord($this->stationA, $this->day1);

    // BENTUK YANG BENAR-BENAR ADA DI DB e2e HARI INI: netto 5 MT sementara
    // bruto DAN tara keduanya null. Menghitung ulang akan mengubah 5 menjadi
    // null pada data yang sudah tersimpan.
    swdReportLoad($record, $this->day1, [
        'gross_weight_mt' => null,
        'tare_weight_mt' => null,
        'net_weight_mt' => 5.0,
    ]);

    // Dan satu baris yang nettonya SENGAJA tidak sama dengan bruto − tara:
    // 10 − 3 = 7, tetapi kolomnya berisi 4. Laporan memakai KOLOMNYA.
    swdReportLoad($record, $this->day1, [
        'gross_weight_mt' => 10.0,
        'tare_weight_mt' => 3.0,
        'net_weight_mt' => 4.0,
    ]);

    $this->actingAs($this->supervisorA);

    $loads = $this->service->buildSummary($this->periodA, null, $this->lineA)['loads'];

    expect($loads['net_weight_total'])->toBe(9.0);
    // 12.0 adalah jawaban implementasi yang menghitung ulang (5 bruto-tara
    // null → null dibuang, 7 dari baris kedua) atau 7.0 bila baris pertama
    // hilang; keduanya harus memerah.
    expect($loads['net_weight_total'])->not->toBe(7.0);
    expect($loads['net_weight_load_count'])->toBe(2);
    expect($loads['net_weight_avg'])->toBe(4.5);
});

// ---------------------------------------------------------------------
// Case 19
// ---------------------------------------------------------------------
it('case 19 — net_weight_load_count 0 memberi total dan rata-rata NULL, BUKAN 0', function () {
    $record = swdReportRecord($this->stationA, $this->day1);

    foreach (range(1, 6) as $i) {
        swdReportLoad($record, $this->day1, ['net_weight_mt' => null]);
    }

    $this->actingAs($this->supervisorA);

    $loads = $this->service->buildSummary($this->periodA, null, $this->lineA)['loads'];

    // IDENTITAS null, bukan falsy: 0.0 harus MEMBUAT TEST MERAH. Nol muatan
    // bertimbang berarti 'tidak diketahui'; 0,00 MT berarti 'tidak ada beban
    // terangkut'.
    $this->assertNull($loads['net_weight_total']);
    $this->assertNull($loads['net_weight_avg']);
    $this->assertNotSame(0.0, $loads['net_weight_total']);
    $this->assertNotSame(0.0, $loads['net_weight_avg']);

    expect($loads['net_weight_load_count'])->toBe(0);
    expect($loads['load_count'])->toBe(6);
});

// ---------------------------------------------------------------------
// Case 20
// ---------------------------------------------------------------------
it('case 20 — penyebut rata-rata adalah net_weight_load_count, bukan load_count', function () {
    $record = swdReportRecord($this->stationA, $this->day1);

    foreach (range(1, 6) as $i) {
        swdReportLoad($record, $this->day1, ['net_weight_mt' => 5.0]);
    }

    foreach (range(1, 4) as $i) {
        swdReportLoad($record, $this->day1, ['net_weight_mt' => null]);
    }

    $this->actingAs($this->supervisorA);

    $loads = $this->service->buildSummary($this->periodA, null, $this->lineA)['loads'];

    expect($loads['net_weight_total'])->toBe(30.0);
    // 5.0 = 30/6, BUKAN 3.0 = 30/10.
    expect($loads['net_weight_avg'])->toBe(5.0);
    expect($loads['net_weight_avg'])->not->toBe(3.0);
    expect($loads['net_weight_load_count'])->toBe(6);
    expect($loads['load_count'])->toBe(10);

    // KETIGA ANGKA DAPAT DIREKONSILIASI PEMBACA, dan itu ditulis sebagai
    // identitas: 10 = 6 + 4.
    $this->assertSame(
        $loads['load_count'],
        $loads['net_weight_load_count'] + $loads['missing_net_weight_load_count'],
        'load_count harus sama dengan muatan bertimbang ditambah yang belum',
    );
});

// ---------------------------------------------------------------------
// Case 21
// ---------------------------------------------------------------------
it('case 21 — missing_net_weight_load_count adalah ANGKA TERSENDIRI di payload', function () {
    // VARIAN A — 10 muatan, 6 bertimbang.
    $record = swdReportRecord($this->stationA, $this->day1);

    foreach (range(1, 6) as $i) {
        swdReportLoad($record, $this->day1, ['net_weight_mt' => 5.0]);
    }

    foreach (range(1, 4) as $i) {
        swdReportLoad($record, $this->day1, ['net_weight_mt' => null]);
    }

    $this->actingAs($this->supervisorA);

    $loads = $this->service->buildSummary($this->periodA, null, $this->lineA)['loads'];

    $this->assertArrayHasKey('missing_net_weight_load_count', $loads);
    expect($loads['missing_net_weight_load_count'])->toBe(4);

    // VARIAN B — 6 muatan, 0 bertimbang: angkanya SAMA DENGAN load_count.
    SolidWasteDisposalDetail::query()->delete();

    foreach (range(1, 6) as $i) {
        swdReportLoad($record, $this->day1, ['net_weight_mt' => null]);
    }

    $varianB = $this->service->buildSummary($this->periodA, null, $this->lineA)['loads'];

    expect($varianB['missing_net_weight_load_count'])->toBe(6);
    expect($varianB['missing_net_weight_load_count'])->toBe($varianB['load_count']);
});

// =====================================================================
// GROUP D — KEEMPAT REKAP KATEGORIS (case 22-29)
// =====================================================================

// ---------------------------------------------------------------------
// Case 22
// ---------------------------------------------------------------------
it("case 22 — rekap mengelompokkan nilai APA ADANYA; nilai berfrekuensi satu tetap berbaris sendiri dan tidak pernah dilipat menjadi 'lainnya'", function () {
    $record = swdReportRecord($this->stationA, $this->day1);

    $semai = function (string $nilai, int $kali) use ($record) {
        foreach (range(1, $kali) as $i) {
            swdReportLoad($record, $this->day1, [
                'solid_waste_type' => $nilai,
                'source_station' => $nilai,
                'disposal_utilization_site' => $nilai,
                'purpose_end_use' => $nilai,
            ]);
        }
    };

    $semai('Fiber', 4);
    $semai('Shell', 2);
    $semai('Boiler Ash', 1);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['by_waste_type'])->toHaveCount(3);
    expect(swdReportEntry($summary['by_waste_type'], 'Fiber')['load_count'])->toBe(4);
    expect(swdReportEntry($summary['by_waste_type'], 'Shell')['load_count'])->toBe(2);
    // Nilai berfrekuensi SATU tetap mendapat barisnya sendiri.
    expect(swdReportEntry($summary['by_waste_type'], 'Boiler Ash')['load_count'])->toBe(1);

    // NOL PEMETAAN, NOL PELABELAN: value-nya persis string mentahnya.
    foreach (['by_waste_type', 'by_source_station', 'by_disposal_site', 'by_purpose_end_use'] as $rekap) {
        $values = array_column($summary[$rekap], 'value');

        $this->assertContains('Boiler Ash', $values, "rekap {$rekap} kehilangan nilai mentahnya");
        $this->assertNotContains('lainnya', $values, "rekap {$rekap} membuat kelompok 'lainnya'");
        $this->assertNotContains('Others', $values, "rekap {$rekap} membuat kelompok 'Others'");
        $this->assertNotContains('Lainnya', $values, "rekap {$rekap} membuat kelompok 'Lainnya'");
    }
});

// ---------------------------------------------------------------------
// Case 23
// ---------------------------------------------------------------------
it("case 23 — baris 'tidak tercatat' ADA sebagai entri tersendiri di keempat rekap, dan posisinya ditentukan tonasenya", function () {
    $record = swdReportRecord($this->stationA, $this->day1);

    // Kelompok NULL disemai BERTONASE PALING BESAR pada keempat kolom.
    foreach (range(1, 3) as $i) {
        swdReportLoad($record, $this->day1, ['net_weight_mt' => 20.0]);
    }

    // 'Zinc Scrap' sengaja dipilih supaya ABJADNYA SESUDAH apa pun yang bisa
    // dipakai sebagai label null: bila null dipaku di baris terakhir, test ini
    // merah; bila null diurutkan sebagai teks, ia juga merah.
    foreach (range(1, 2) as $i) {
        swdReportLoad($record, $this->day1, [
            'net_weight_mt' => 5.0,
            'solid_waste_type' => 'Zinc Scrap',
            'source_station' => 'Zinc Scrap',
            'disposal_utilization_site' => 'Zinc Scrap',
            'purpose_end_use' => 'Zinc Scrap',
        ]);
    }

    foreach (range(1, 2) as $i) {
        swdReportLoad($record, $this->day1, [
            'net_weight_mt' => 1.0,
            'solid_waste_type' => 'Alpha Ash',
            'source_station' => 'Alpha Ash',
            'disposal_utilization_site' => 'Alpha Ash',
            'purpose_end_use' => 'Alpha Ash',
        ]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    foreach (['by_waste_type', 'by_source_station', 'by_disposal_site', 'by_purpose_end_use'] as $rekap) {
        $rows = $summary[$rekap];

        // TEPAT SATU entri bernilai null.
        $this->assertSame(
            1,
            count(array_filter($rows, fn (array $row) => $row['value'] === null)),
            "rekap {$rekap} harus punya tepat satu baris 'tidak tercatat'",
        );

        // DI INDEKS 0 karena net_weight_total-nya terbesar (60.0) — bukan
        // sekadar 'ada di dalam daftar'.
        $this->assertSame(0, swdReportNullIndex($rows), "baris null rekap {$rekap} tidak berada di indeks 0");
        // ASERSI NEGATIF EKSPLISIT: ia BUKAN baris terakhir.
        $this->assertNotSame(
            count($rows) - 1,
            swdReportNullIndex($rows),
            "baris null rekap {$rekap} terkubur di baris terakhir",
        );

        expect(swdReportEntry($rows, null)['net_weight_total'])->toBe(60.0);
        expect(swdReportEntry($rows, null)['load_count'])->toBe(3);
    }
});

// ---------------------------------------------------------------------
// Case 24
// ---------------------------------------------------------------------
it('case 24 — penjumlahan load_count tiap rekap SAMA DENGAN loads.load_count', function () {
    $record = swdReportRecord($this->stationA, $this->day1);

    // 12 muatan; pada SETIAP kolom kategoris sebagian bernilai dan sebagian
    // null, dan polanya berbeda per kolom supaya satu kolom yang benar tidak
    // menutupi tiga yang salah.
    foreach (range(1, 12) as $i) {
        swdReportLoad($record, $this->day1, [
            'solid_waste_type' => $i % 2 === 0 ? 'Fiber' : null,
            'source_station' => $i % 3 === 0 ? 'Pressing' : null,
            'disposal_utilization_site' => $i % 4 === 0 ? 'TPA Utara' : null,
            'purpose_end_use' => $i <= 5 ? 'Composting' : null,
        ]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['loads']['load_count'])->toBe(12);

    // KEEMPATNYA SECARA TERPISAH, dengan pesan yang menamai rekapnya: bila
    // baris null dibuang, selisihnya tidak akan dijelaskan apa pun kepada
    // pembaca — dan penjumlahan inilah yang menangkapnya.
    foreach (['by_waste_type', 'by_source_station', 'by_disposal_site', 'by_purpose_end_use'] as $rekap) {
        $this->assertSame(
            12,
            array_sum(array_column($summary[$rekap], 'load_count')),
            "penjumlahan load_count rekap {$rekap} tidak sama dengan loads.load_count",
        );
    }
});

// ---------------------------------------------------------------------
// Case 25
// ---------------------------------------------------------------------
it('case 25 — string kosong dan whitespace di-trim lalu diperlakukan SAMA DENGAN null: satu entri, bukan dua', function () {
    $record = swdReportRecord($this->stationA, $this->day1);

    foreach ([null, null, '', '', '   ', '   '] as $nilai) {
        swdReportLoad($record, $this->day1, ['solid_waste_type' => $nilai]);
    }

    $this->actingAs($this->supervisorA);

    $rows = $this->service->buildSummary($this->periodA, null, $this->lineA)['by_waste_type'];

    expect($rows)->toHaveCount(1);
    $this->assertNull($rows[0]['value']);
    expect($rows[0]['load_count'])->toBe(6);

    // ASERSI NEGATIF: dua baris terpisah untuk '' dan '   ' akan membagi satu
    // keadaan menjadi tiga.
    $values = array_column($rows, 'value');
    $this->assertNotContains('', $values, "string kosong menjadi barisnya sendiri");
    $this->assertNotContains('   ', $values, 'whitespace menjadi barisnya sendiri');
});

// ---------------------------------------------------------------------
// Case 26
// ---------------------------------------------------------------------
it('case 26 — nilai yang berbeda hanya pada huruf besar/kecil TETAP dua entri terpisah', function () {
    $record = swdReportRecord($this->stationA, $this->day1);

    foreach (['TPA Utara', 'TPA Utara'] as $nilai) {
        swdReportLoad($record, $this->day1, ['disposal_utilization_site' => $nilai]);
    }

    foreach (['tpa utara', 'tpa utara', 'tpa utara'] as $nilai) {
        swdReportLoad($record, $this->day1, ['disposal_utilization_site' => $nilai]);
    }

    // Spasi tepi DI-TRIM, jadi baris ini bergabung dengan 'TPA Utara'.
    swdReportLoad($record, $this->day1, ['disposal_utilization_site' => ' TPA Utara ']);

    $this->actingAs($this->supervisorA);

    $rows = $this->service->buildSummary($this->periodA, null, $this->lineA)['by_disposal_site'];

    expect($rows)->toHaveCount(2);
    expect(swdReportEntry($rows, 'TPA Utara')['load_count'])->toBe(3);
    expect(swdReportEntry($rows, 'tpa utara')['load_count'])->toBe(3);
    // Hanya spasi ujung yang di-trim; case TIDAK dinormalkan — gejala data
    // master yang perlu dirapikan harus TERLIHAT.
    $this->assertNotContains(' TPA Utara ', array_column($rows, 'value'));
});

// ---------------------------------------------------------------------
// Case 27
// ---------------------------------------------------------------------
it('case 27 — urutan rekap: net_weight_total DESC, lalu load_count DESC, lalu value ASC; null terakhir HANYA di antara yang seri', function () {
    $record = swdReportRecord($this->stationA, $this->day1);

    // URUTAN INSERT SENGAJA BERBEDA dari urutan alfabetis MAUPUN dari urutan
    // jumlah, supaya hasilnya tidak dapat muncul karena kebetulan.
    foreach (range(1, 2) as $i) {
        swdReportLoad($record, $this->day1, ['solid_waste_type' => 'Shell', 'net_weight_mt' => 5.0]);
    }

    foreach (range(1, 2) as $i) {
        swdReportLoad($record, $this->day1, ['solid_waste_type' => null, 'net_weight_mt' => 15.0]);
    }

    foreach (range(1, 4) as $i) {
        swdReportLoad($record, $this->day1, ['solid_waste_type' => 'Fiber', 'net_weight_mt' => 2.5]);
    }

    swdReportLoad($record, $this->day1, ['solid_waste_type' => 'Boiler Ash', 'net_weight_mt' => 40.0]);

    foreach (range(1, 2) as $i) {
        swdReportLoad($record, $this->day1, ['solid_waste_type' => 'Zinc Scrap', 'net_weight_mt' => 5.0]);
    }

    $this->actingAs($this->supervisorA);

    $rows = $this->service->buildSummary($this->periodA, null, $this->lineA)['by_waste_type'];

    expect(array_column($rows, 'value'))->toBe(['Boiler Ash', null, 'Fiber', 'Shell', 'Zinc Scrap']);

    // (a) net_weight_total MENURUN MONOTON di sepanjang daftar.
    $totals = array_column($rows, 'net_weight_total');

    expect($totals)->toBe([40.0, 30.0, 10.0, 10.0, 10.0]);

    for ($i = 1; $i < count($totals); $i++) {
        $this->assertLessThanOrEqual(
            (float) $totals[$i - 1],
            (float) $totals[$i],
            'net_weight_total tidak menurun monoton pada indeks '.$i,
        );
    }

    $posisi = array_flip(array_map(fn ($v) => $v ?? '__null__', array_column($rows, 'value')));

    // (b) 'Fiber' mendahului 'Shell' dan 'Zinc Scrap' karena load_count-nya
    //     lebih besar walau nettonya seri.
    $this->assertLessThan($posisi['Shell'], $posisi['Fiber']);
    $this->assertLessThan($posisi['Zinc Scrap'], $posisi['Fiber']);
    expect(swdReportEntry($rows, 'Fiber')['load_count'])->toBe(4);

    // (c) 'Shell' mendahului 'Zinc Scrap' — pemutus seri value ASC bekerja
    //     setelah KEDUA kunci pertama seri.
    $this->assertLessThan($posisi['Zinc Scrap'], $posisi['Shell']);

    // (d) EKSPLISIT — kelompok null bertonase besar ada di indeks 1, DI ATAS
    //     tiga entri bernilai, dan tidak terkubur di baris terakhir.
    $this->assertSame(1, swdReportNullIndex($rows));
    $this->assertNotSame(count($rows) - 1, swdReportNullIndex($rows));
});

// ---------------------------------------------------------------------
// Case 28
// ---------------------------------------------------------------------
it("case 28 — kolom kategoris kosong pada SELURUH muatan menghasilkan satu baris 'tidak tercatat', bukan rekap kosong", function () {
    $record = swdReportRecord($this->stationA, $this->day1);

    foreach (range(1, 5) as $i) {
        swdReportLoad($record, $this->day1, ['purpose_end_use' => null, 'net_weight_mt' => 2.0]);
    }

    foreach (range(1, 4) as $i) {
        swdReportLoad($record, $this->day1, ['purpose_end_use' => '', 'net_weight_mt' => null]);
    }

    $this->actingAs($this->supervisorA);

    $rows = $this->service->buildSummary($this->periodA, null, $this->lineA)['by_purpose_end_use'];

    // BUKAN [] — rekap kosong membuat pembaca mengira bagian laporan itu
    // tidak ada.
    expect($rows)->toHaveCount(1);
    $this->assertNull($rows[0]['value']);
    expect($rows[0]['load_count'])->toBe(9);
    expect($rows[0]['net_weight_total'])->toBe(10.0);
    expect($rows[0]['net_weight_load_count'])->toBe(5);
});

// ---------------------------------------------------------------------
// Case 29
// ---------------------------------------------------------------------
it('case 29 — net_weight_total per entri rekap menjumlahkan HANYA netto non-null', function () {
    $record = swdReportRecord($this->stationA, $this->day1);

    foreach ([1.5, 2.5, null, null] as $netto) {
        swdReportLoad($record, $this->day1, ['solid_waste_type' => 'Fiber', 'net_weight_mt' => $netto]);
    }

    $this->actingAs($this->supervisorA);

    $entri = swdReportEntry(
        $this->service->buildSummary($this->periodA, null, $this->lineA)['by_waste_type'],
        'Fiber',
    );

    expect($entri['load_count'])->toBe(4);
    expect($entri['net_weight_total'])->toBe(4.0);
    // Baris ber-netto null ikut sebagai MUATAN tetapi tidak diperlakukan
    // sebagai 0 di dalam rata-rata mana pun: 4.0/2, bukan 4.0/4.
    expect($entri['net_weight_load_count'])->toBe(2);
    expect($entri['net_weight_avg'])->toBe(2.0);
    expect($entri['net_weight_avg'])->not->toBe(1.0);
});

// =====================================================================
// GROUP E — KEPATUHAN, DRAFT, UNDATED, HARIAN (case 30-36)
// =====================================================================

// ---------------------------------------------------------------------
// Case 30
// ---------------------------------------------------------------------
it('case 30 — compliance menghitung null DAN string kosong setelah trim; nol tetap diterbitkan', function () {
    $record = swdReportRecord($this->stationA, $this->day1);

    // VARIAN A — 8 muatan, kelengkapan campuran.
    foreach ([
        [null, null],
        [null, null],
        [null, null],
        ['', null],
        ['', null],
        ['  ', 'SS-1'],
        ['GP-1', 'SS-2'],
        ['GP-2', 'SS-3'],
    ] as [$gate, $seal]) {
        swdReportLoad($record, $this->day1, ['gate_pass_no' => $gate, 'security_seal_no' => $seal]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['compliance']['missing_gate_pass_count'])->toBe(6);
    expect($summary['compliance']['missing_seal_count'])->toBe(5);
    expect($summary['compliance']['_note'])->not->toBe('');

    // BLOK TERSENDIRI, tidak digabung ke dalam blok loads — asersi
    // STRUKTURAL atas lokasi kuncinya.
    $this->assertArrayNotHasKey('missing_gate_pass_count', $summary['loads']);
    $this->assertArrayNotHasKey('missing_seal_count', $summary['loads']);

    // VARIAN B — kedua nomor terisi seluruhnya.
    SolidWasteDisposalDetail::query()->delete();

    foreach (range(1, 8) as $i) {
        swdReportLoad($record, $this->day1, [
            'gate_pass_no' => 'GP-'.$i,
            'security_seal_no' => 'SS-'.$i,
        ]);
    }

    $lengkap = $this->service->buildSummary($this->periodA, null, $this->lineA)['compliance'];

    // KEDUA KUNCI TETAP ADA — nol itulah yang memberi tahu pembaca
    // pemeriksaannya memang berjalan.
    $this->assertArrayHasKey('missing_gate_pass_count', $lengkap);
    $this->assertArrayHasKey('missing_seal_count', $lengkap);
    expect($lengkap['missing_gate_pass_count'])->toBe(0);
    expect($lengkap['missing_seal_count'])->toBe(0);
});

// ---------------------------------------------------------------------
// Case 30b — compliance TIDAK PERNAH menerbitkan nomornya sendiri
// ---------------------------------------------------------------------
it('case 30b — nomor gate pass dan segel tidak pernah dirata-rata, diurutkan, digrafikkan, atau diterbitkan nilainya', function () {
    $record = swdReportRecord($this->stationA, $this->day1);

    swdReportLoad($record, $this->day1, ['gate_pass_no' => 'GP-90021', 'security_seal_no' => 'SS-90018']);
    swdReportLoad($record, $this->day1, ['gate_pass_no' => null, 'security_seal_no' => null]);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);
    $keys = swdReportAllKeys($summary);

    foreach ([
        'avg_gate_pass', 'gate_pass_chart', 'gate_pass_numbers', 'gate_pass_no',
        'min_gate_pass', 'max_gate_pass', 'sorted_gate_pass',
        'avg_seal', 'seal_chart', 'seal_numbers', 'security_seal_no',
        'min_seal', 'max_seal', 'sorted_seal',
    ] as $terlarang) {
        $this->assertNotContains($terlarang, $keys, "payload tidak boleh punya kunci {$terlarang}");
    }

    // Nomornya sendiri TIDAK diterbitkan di payload layar — ia hanya ada di
    // ekspor. Di sini aman memindai teks: keduanya nilai buatan test ini.
    $encoded = json_encode($summary);

    expect($encoded)->not->toContain('GP-90021');
    expect($encoded)->not->toContain('SS-90018');
});

// ---------------------------------------------------------------------
// Case 31
// ---------------------------------------------------------------------
it('case 31 — draft ikut terhitung di seluruh angka, dan draft_load_count menyatakan berapa', function () {
    $draftRecord = swdReportRecord($this->stationA, $this->day1, ['status' => RecordStatus::DraftOngoing]);
    $finalRecord = swdReportRecord($this->stationA, $this->day2, ['status' => RecordStatus::Saved]);

    foreach (range(1, 3) as $i) {
        swdReportLoad($draftRecord, $this->day1, [
            'net_weight_mt' => 2.0,
            'solid_waste_type' => 'Fiber',
            'source_station' => 'Pressing',
            'disposal_utilization_site' => 'TPA Utara',
            'purpose_end_use' => 'Composting',
        ]);
    }

    foreach (range(1, 5) as $i) {
        swdReportLoad($finalRecord, $this->day2, [
            'net_weight_mt' => 3.0,
            'solid_waste_type' => 'Shell',
            'source_station' => 'Threshing',
            'disposal_utilization_site' => 'TPA Selatan',
            'purpose_end_use' => 'Fuel',
        ]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['loads']['load_count'])->toBe(8);
    expect($summary['loads']['net_weight_load_count'])->toBe(8);
    expect($summary['draft_load_count'])->toBe(3);

    foreach (['by_waste_type', 'by_source_station', 'by_disposal_site', 'by_purpose_end_use'] as $rekap) {
        $this->assertSame(
            8,
            array_sum(array_column($summary[$rekap], 'load_count')),
            "rekap {$rekap} menyaring draft keluar",
        );
    }

    // ASERSI NEGATIF: 5 adalah jawaban implementasi yang menyaring draft.
    expect($summary['loads']['load_count'])->not->toBe(5);
    // Pembaca diberi tahu, bukan dilindungi.
    expect(array_sum(array_column($summary['daily'], 'load_count')))->toBe(8);
});

// ---------------------------------------------------------------------
// Case 32
//
// DIJALANKAN ATAS KOLEKSI DI MEMORI, bukan atas basis data: `event_date` NOT
// NULL di skema (lihat case 56 dan docblock berkas), jadi muatan tak
// bertanggal tidak dapat disimpan. Agregasinya sendiri — loadsOf, breakdownOf,
// dailyOf, dailyTotalOf — tetap dijalankan sungguhan.
// ---------------------------------------------------------------------
it('case 32 — undated_load_count menghitung baris ber-event_date NULL; baris itu masuk load_count dan keempat rekap tetapi TIDAK masuk daily[]', function () {
    $details = [];

    foreach ([$this->day1, $this->day1, $this->day1, $this->day2, $this->day2, $this->day3, $this->day3] as $tanggal) {
        $details[] = swdReportMemoryLoad($tanggal, [
            'solid_waste_type' => 'Fiber',
            'source_station' => 'Pressing',
            'disposal_utilization_site' => 'TPA Utara',
            'purpose_end_use' => 'Composting',
            'net_weight_mt' => 2.0,
        ]);
    }

    foreach (range(1, 3) as $i) {
        $details[] = swdReportMemoryLoad(null, [
            'solid_waste_type' => 'Fiber',
            'source_station' => 'Pressing',
            'disposal_utilization_site' => 'TPA Utara',
            'purpose_end_use' => 'Composting',
            'net_weight_mt' => 2.0,
        ]);
    }

    SolidWasteDisposalReportInMemoryService::$records = collect([swdReportMemoryRecord($details)]);

    $this->actingAs($this->supervisorA);

    $summary = (new SolidWasteDisposalReportInMemoryService)
        ->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['loads']['load_count'])->toBe(10);
    expect($summary['undated_load_count'])->toBe(3);

    foreach (['by_waste_type', 'by_source_station', 'by_disposal_site', 'by_purpose_end_use'] as $rekap) {
        $this->assertSame(
            10,
            array_sum(array_column($summary[$rekap], 'load_count')),
            "rekap {$rekap} membuang muatan tak bertanggal",
        );
    }

    // daily[] hanya punya 3 entri, dan ketiganya menjumlah 7.
    expect($summary['daily'])->toHaveCount(3);
    expect(array_sum(array_column($summary['daily'], 'load_count')))->toBe(7);
    expect(array_column($summary['daily'], 'date'))->toBe([$this->day1, $this->day2, $this->day3]);
});

// ---------------------------------------------------------------------
// Case 33
// ---------------------------------------------------------------------
it('case 33 — penjumlahan daily[].load_count KURANG dari load_count tepat sebesar undated_load_count', function () {
    $details = [];

    foreach ([$this->day1, $this->day1, $this->day1, $this->day2, $this->day2, $this->day3, $this->day3] as $tanggal) {
        $details[] = swdReportMemoryLoad($tanggal, ['net_weight_mt' => 2.0]);
    }

    foreach (range(1, 3) as $i) {
        $details[] = swdReportMemoryLoad(null, ['net_weight_mt' => 2.0]);
    }

    SolidWasteDisposalReportInMemoryService::$records = collect([swdReportMemoryRecord($details)]);

    $this->actingAs($this->supervisorA);

    $summary = (new SolidWasteDisposalReportInMemoryService)
        ->buildSummary($this->periodA, null, $this->lineA);

    // DIASERSIKAN SEBAGAI IDENTITAS, bukan sebagai 'kurang dari': selisih yang
    // tak dijelaskan tidak dapat dibedakan dari cacat.
    $this->assertSame(
        $summary['loads']['load_count'] - $summary['undated_load_count'],
        array_sum(array_column($summary['daily'], 'load_count')),
        'penjumlahan daily[] harus tepat load_count dikurangi undated_load_count',
    );

    expect(array_sum(array_column($summary['daily'], 'load_count')))->toBe(7);
    expect($summary['loads']['load_count'])->toBe(10);
    expect($summary['undated_load_count'])->toBe(3);
});

// ---------------------------------------------------------------------
// Case 34
// ---------------------------------------------------------------------
it('case 34 — daily[] berisi satu entri per hari BERMUATAN, bukan per hari kalender; urut tanggal naik', function () {
    $record = swdReportRecord($this->stationA, $this->day1);

    // Sembilan hari berbeda di dalam periode 31 hari, disemai dalam urutan
    // insert yang DIACAK supaya urutan naik tidak diwarisi dari urutan kueri —
    // PostgreSQL tidak menjanjikannya.
    $offsets = [31, 39, 20, 35, 12, 28, 17, 24, 14];
    $tanggal = array_map(fn (int $o) => now()->subDays($o)->toDateString(), $offsets);

    foreach ($tanggal as $index => $hari) {
        $muatan = $index % 3 === 0 ? 3 : ($index % 3 === 1 ? 1 : 2);

        foreach (range(1, $muatan) as $i) {
            swdReportLoad($record, $hari);
        }
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // TEPAT 9, bukan 31. Asersi jumlah entri ini WAJIB: 22 baris nol akan
    // mengubur 9 baris yang berarti.
    expect($summary['daily'])->toHaveCount(9);
    expect($summary['completeness']['days_in_period'])->toBe(31);

    $dates = array_column($summary['daily'], 'date');
    $sorted = $dates;
    sort($sorted);

    expect($dates)->toBe($sorted);

    foreach ($summary['daily'] as $row) {
        $this->assertGreaterThan(0, $row['load_count'], 'daily[] memuat entri ber-load_count 0');
    }
});

// ---------------------------------------------------------------------
// Case 35
// ---------------------------------------------------------------------
it('case 35 — event_date DI LUAR rentang periode tetap terhitung dan tetap muncul di daily[] apa adanya', function () {
    $record = swdReportRecord($this->stationA, now()->subDays(20)->toDateString());

    swdReportLoad($record, now()->subDays(20)->toDateString(), ['solid_waste_type' => 'Fiber']);
    // Induknya di DALAM rentang, kejadiannya di LUAR.
    swdReportLoad($record, $this->dayAfterPeriod, ['solid_waste_type' => 'Shell']);

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['loads']['load_count'])->toBe(2);
    expect(array_sum(array_column($summary['by_waste_type'], 'load_count')))->toBe(2);

    // TIDAK DISEMBUNYIKAN dan tidak dipindahkan: menyembunyikannya membuat
    // total tidak cocok dengan rincian hariannya.
    $diLuar = swdReportDaily($summary, $this->dayAfterPeriod);

    expect($diLuar)->not->toBeNull();
    expect($diLuar['load_count'])->toBe(1);
    $this->assertGreaterThan($summary['period']['end_date'], $diLuar['date']);
});

// ---------------------------------------------------------------------
// Case 36
// ---------------------------------------------------------------------
it('case 36 — daily_total DIJUMLAHKAN dari daily[], bukan disalin dari blok loads', function () {
    $details = [];

    // 7 muatan bertanggal, netto 16.0 total.
    foreach ([
        [$this->day1, 3.0],
        [$this->day1, 3.0],
        [$this->day1, 2.0],
        [$this->day2, 2.0],
        [$this->day2, 2.0],
        [$this->day3, 2.0],
        [$this->day3, 2.0],
    ] as [$tanggal, $netto]) {
        $details[] = swdReportMemoryLoad($tanggal, ['net_weight_mt' => $netto]);
    }

    // 3 muatan TAK BERTANGGAL, netto 4.0 total.
    foreach ([2.0, 1.0, 1.0] as $netto) {
        $details[] = swdReportMemoryLoad(null, ['net_weight_mt' => $netto]);
    }

    SolidWasteDisposalReportInMemoryService::$records = collect([swdReportMemoryRecord($details)]);

    $this->actingAs($this->supervisorA);

    $summary = (new SolidWasteDisposalReportInMemoryService)
        ->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['loads']['load_count'])->toBe(10);
    expect($summary['loads']['net_weight_total'])->toBe(20.0);
    expect($summary['undated_load_count'])->toBe(3);

    expect($summary['daily_total']['load_count'])->toBe(7);
    expect($summary['daily_total']['net_weight_total'])->toBe(16.0);

    // ASERSI EKSPLISIT: keduanya BERBEDA pada fixture ini, dan perbedaan itu
    // adalah informasi. Menyalin dari blok loads akan menyembunyikannya.
    $this->assertNotSame($summary['loads']['load_count'], $summary['daily_total']['load_count']);
    $this->assertNotSame($summary['loads']['net_weight_total'], $summary['daily_total']['net_weight_total']);
    expect($summary['daily_total']['_note'])->not->toBe('');
});

// =====================================================================
// GROUP F — KELENGKAPAN PENCATATAN (case 37-38)
// =====================================================================

// ---------------------------------------------------------------------
// Case 37
// ---------------------------------------------------------------------
it('case 37 — completeness untuk periode yang BELUM MULAI: days_counted 0 dan percent NULL', function () {
    // Periode 30 hari yang seluruhnya di masa depan. Tanggal RELATIF, bukan
    // Carbon::setTestNow: zona WIB dan 'hari ini' keduanya sudah ditangani
    // ReportPeriodDays, dan membekukan waktu di sini hanya menambah satu
    // sumber kegagalan yang bukan subjek test-nya.
    $future = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::SolidWasteDisposal->value)
        ->range(now()->addDays(10)->toDateString(), now()->addDays(39)->toDateString())
        ->open()
        ->named('Periode Belum Mulai')
        ->create();

    $this->actingAs($this->supervisorA);

    $completeness = $this->service->buildSummary($future, null, $this->lineA)['completeness'];

    expect($completeness['days_counted'])->toBe(0);
    expect($completeness['days_in_period'])->toBe(30);
    // IDENTITAS null — 0.0 harus merah, sebab 0,0% terbaca sebagai kelalaian
    // pencatatan sementara '—' berarti belum ada hari yang diharapkan.
    $this->assertNull($completeness['percent']);
    $this->assertNotSame(0.0, $completeness['percent']);
    expect($completeness['days_with_load'])->toBe(0);
});

// ---------------------------------------------------------------------
// Case 38
// ---------------------------------------------------------------------
it('case 38 — periode BERJALAN memakai penyebut start..hari ini; periode selesai memakai panjang periode', function () {
    // VARIAN A — periode 31 hari yang MASIH BERJALAN: mulai 8 hari lalu,
    // berakhir 22 hari lagi. Hari yang sudah lewat = 9 (termasuk hari ini).
    $running = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::SolidWasteDisposal->value)
        ->range(now()->subDays(8)->toDateString(), now()->addDays(22)->toDateString())
        ->open()
        ->named('Periode Berjalan')
        ->create();

    foreach (range(0, 8) as $offset) {
        $hari = now()->subDays($offset)->toDateString();
        swdReportLoad(swdReportRecord($this->stationA, $hari), $hari);
    }

    $this->actingAs($this->supervisorA);

    $berjalan = $this->service->buildSummary($running, null, $this->lineA)['completeness'];

    expect($berjalan['days_in_period'])->toBe(31);
    expect($berjalan['days_counted'])->toBe(9);
    expect($berjalan['days_with_load'])->toBe(9);
    // 100.0, BUKAN 29.0: periode berjalan tidak dihukum atas hari yang belum
    // datang.
    expect($berjalan['percent'])->toBe(100.0);
    expect($berjalan['percent'])->not->toBe(29.0);

    // VARIAN B — periode SELESAI (periodA, 31 hari) dengan muatan pada 9 hari.
    foreach ([39, 38, 37, 36, 35, 34, 33, 32, 31] as $offset) {
        $hari = now()->subDays($offset)->toDateString();
        swdReportLoad(swdReportRecord($this->stationA, $hari), $hari);
    }

    $selesai = $this->service->buildSummary($this->periodA, null, $this->lineA)['completeness'];

    expect($selesai['days_counted'])->toBe(31);
    expect($selesai['days_counted'])->toBe($selesai['days_in_period']);
    expect($selesai['days_with_load'])->toBe(9);
    expect($selesai['percent'])->toBe(round(100 * 9 / 31, 1));
    expect($selesai['percent'])->toBe(29.0);
});

// =====================================================================
// GROUP G — TEKS BEBAS, KETIADAAN, DAN PERIODE KOSONG (case 39-41)
// =====================================================================

// ---------------------------------------------------------------------
// Case 39
// ---------------------------------------------------------------------
it('case 39 — text_entries memisahkan findings dan remarks; entri kosong dibuang; satu muatan boleh muncul di keduanya', function () {
    $record = swdReportRecord($this->stationA, $this->day1);

    // Tanggal disemai TIDAK URUT supaya pengurutannya benar-benar diuji.
    swdReportLoad($record, $this->day3, [
        'findings' => 'Ceceran di gerbang',
        'remarks' => 'Dibersihkan petugas',
    ]);
    swdReportLoad($record, $this->day1, ['findings' => 'Terpal robek', 'remarks' => null]);
    swdReportLoad($record, $this->day2, ['findings' => '   ', 'remarks' => null]);

    $this->actingAs($this->supervisorA);

    $textEntries = $this->service->buildSummary($this->periodA, null, $this->lineA)['text_entries'];

    expect($textEntries['findings'])->toHaveCount(2);
    expect($textEntries['remarks'])->toHaveCount(1);

    // Urut tanggal NAIK: D1 (day1) lebih dulu daripada D3 (day3).
    expect(array_column($textEntries['findings'], 'date'))->toBe([$this->day1, $this->day3]);
    expect(array_column($textEntries['findings'], 'value'))->toBe(['Terpal robek', 'Ceceran di gerbang']);

    // D1 muncul di KEDUA daftar dan itu benar, bukan penghitungan ganda.
    expect($textEntries['remarks'][0])->toBe(['date' => $this->day3, 'value' => 'Dibersihkan petugas']);

    // D3 (findings whitespace, remarks null) tidak muncul di mana pun.
    $this->assertNotContains('   ', array_column($textEntries['findings'], 'value'));
    expect(array_column($textEntries['findings'], 'date'))->not->toContain($this->day2);
});

// ---------------------------------------------------------------------
// Case 40
// ---------------------------------------------------------------------
it("case 40 — payload TIDAK punya kunci hourly, busiest_hour, empty_hour_count maupun shift, diasersikan ATAS NAMA KUNCI", function () {
    $record = swdReportRecord($this->stationA, $this->day1);

    foreach (range(1, 4) as $i) {
        swdReportLoad($record, $this->day1, [
            'shift' => 'Shift '.$i,
            'solid_waste_type' => 'Fiber',
            'findings' => 'Catatan '.$i,
        ]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    // Di akar payload.
    foreach (['hourly', 'busiest_hour', 'empty_hour_count', 'shift'] as $terlarang) {
        $this->assertArrayNotHasKey($terlarang, $summary);
    }

    // DAN REKURSIF atas seluruh sub-array — loads, daily, compliance,
    // completeness, keempat rekap, text_entries.
    $keys = swdReportAllKeys($summary);

    foreach ([
        'hourly', 'busiest_hour', 'empty_hour_count', 'hour', 'hours', 'by_hour',
        'shift', 'shifts', 'by_shift', 'shift_distribution',
        'downtime', 'threshold', 'thresholds', 'out_of_range', 'target',
    ] as $terlarang) {
        $this->assertNotContains($terlarang, $keys, "payload tidak boleh punya kunci {$terlarang}");
    }

    // JEBAKAN YANG WAJIB DIHINDARI DAN SUDAH TERBUKTI DI PROYEK INI: ketiadaan
    // ini TIDAK PERNAH diasersikan dengan mencari kata 'jam'/'Sebaran'/'shift'
    // pada payload — ketiga prosa di bawah JUSTRU memuat kata-kata itu, dan
    // asersi berbasis prosa akan cocok dengan kalimat yang menyatakan
    // ketiadaannya lalu lolos tanpa membuktikan apa pun. Baris-baris ini
    // membuktikan jebakannya nyata di payload ini, bukan hipotetis.
    expect($summary['_note_no_hourly'])->toContain('jam');
    expect($summary['_note_shift_not_published'])->toContain('shift');

    // Ketiganya ADA dan berisi string non-kosong.
    foreach (['_note_no_hourly', '_note_shift_not_published', '_note_absences'] as $note) {
        $this->assertArrayHasKey($note, $summary);
        $this->assertIsString($summary[$note]);
        $this->assertNotSame('', $summary[$note]);
    }
});

// ---------------------------------------------------------------------
// Case 40b — purpose_end_use TIDAK dilipat menjadi dua ember
// ---------------------------------------------------------------------
it("case 40b — purpose_end_use diterbitkan per nilai apa adanya, bukan dua ember dibuang/dimanfaatkan", function () {
    $record = swdReportRecord($this->stationA, $this->day1);

    foreach (['Composting', 'Landfill', 'Fuel', 'Road Base'] as $nilai) {
        swdReportLoad($record, $this->day1, ['purpose_end_use' => $nilai]);
    }

    $this->actingAs($this->supervisorA);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['by_purpose_end_use'])->toHaveCount(4);

    $keys = swdReportAllKeys($summary);

    // ASERSI NEGATIF YANG MENJADI INTI SKENARIO INI — atas NAMA KUNCI, bukan
    // atas prosa layar.
    foreach ([
        'disposed', 'utilized', 'dibuang', 'dimanfaatkan',
        'disposal_group', 'utilization_group', 'by_disposal_mode',
    ] as $terlarang) {
        $this->assertNotContains($terlarang, $keys, "payload tidak boleh punya kunci {$terlarang}");
    }

    // Dan tidak ada nilai ember karangan di antara baris rekapnya.
    $values = array_column($summary['by_purpose_end_use'], 'value');

    foreach (['Dibuang', 'Dimanfaatkan', 'Disposed', 'Utilized'] as $ember) {
        $this->assertNotContains($ember, $values, "rekap purpose_end_use melipat nilainya ke ember '{$ember}'");
    }
});

// ---------------------------------------------------------------------
// Case 41
// ---------------------------------------------------------------------
it('case 41 — periode tanpa satu pun record mengembalikan kerangka lengkap, bukan galat dan bukan nol di mana-mana', function () {
    $this->actingAs($this->supervisorA);

    // KEADAAN NYATA DI DEV: solid_waste_disposal_details nol baris.
    expect(SolidWasteDisposalDetail::query()->count())->toBe(0);

    $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['loads']['load_count'])->toBe(0);
    $this->assertNull($summary['loads']['net_weight_total']);
    $this->assertNull($summary['loads']['net_weight_avg']);
    expect($summary['loads']['net_weight_load_count'])->toBe(0);
    expect($summary['loads']['missing_net_weight_load_count'])->toBe(0);

    foreach (['by_waste_type', 'by_source_station', 'by_disposal_site', 'by_purpose_end_use'] as $rekap) {
        $this->assertSame([], $summary[$rekap], "rekap {$rekap} seharusnya kosong");
    }

    expect($summary['daily'])->toBe([]);
    expect($summary['daily_total']['load_count'])->toBe(0);
    expect($summary['draft_load_count'])->toBe(0);
    expect($summary['undated_load_count'])->toBe(0);

    expect($summary['completeness']['days_counted'])->toBe(31);
    expect($summary['completeness']['days_with_load'])->toBe(0);
    // 0.0 DI SINI BENAR, dan itu berbeda dari case 37: ada 31 hari yang
    // diharapkan dan tak satu pun terisi.
    expect($summary['completeness']['percent'])->toBe(0.0);

    expect($summary['text_entries']['findings'])->toBe([]);
    expect($summary['text_entries']['remarks'])->toBe([]);

    foreach (['_note_no_hourly', '_note_shift_not_published', '_note_absences'] as $note) {
        $this->assertArrayHasKey($note, $summary);
    }

    // Dan kerangkanya UTUH: bentuk payload tidak berubah hanya karena kosong.
    expect(array_keys($summary))->toBe([
        'business_unit', 'production_line', 'period', 'loads',
        'by_waste_type', 'by_source_station', 'by_disposal_site', 'by_purpose_end_use',
        'compliance', 'draft_load_count', 'undated_load_count',
        'daily', 'daily_total', 'completeness', 'text_entries',
        '_note_no_hourly', '_note_shift_not_published', '_note_absences',
    ]);
});

// =====================================================================
// GROUP H — EKSPOR (case 42-45)
// =====================================================================

// ---------------------------------------------------------------------
// Case 42
// ---------------------------------------------------------------------
it('case 42 — satu baris ekspor per detail, kolom konteks diulang verbatim, seluruh kolom detail ikut', function () {
    $r1 = swdReportRecord($this->stationA, $this->day1, ['status' => RecordStatus::Saved]);
    $r2 = swdReportRecord($this->stationA, $this->day2, ['status' => RecordStatus::DraftOngoing]);

    foreach (range(1, 3) as $i) {
        swdReportLoad($r1, $this->day1, [
            'shift' => 'Shift '.$i,
            'gate_pass_no' => 'GP-'.$i,
            'security_seal_no' => 'SS-'.$i,
            'operator_id' => 'OP-'.$i,
            'weighbridge_ticket_no' => 'WT-'.$i,
            'vehicle_no' => 'B 100'.$i.' XX',
            'driver_name' => 'Sopir '.$i,
        ]);
    }

    foreach (range(4, 5) as $i) {
        swdReportLoad($r2, $this->day2, ['shift' => 'Shift '.$i]);
    }

    $this->actingAs($this->supervisorA);

    $lines = swdReportCsvLines(swdReportStreamed(
        $this->service->export($this->periodA, 'csv', null, $this->lineA),
    ));

    // 1 header + 5 baris data.
    expect($lines)->toHaveCount(6);

    $header = swdReportCsvRow($lines[0]);

    expect($header)->toBe(SolidWasteDisposalReportService::EXPORT_HEADER);
    expect($header)->toHaveCount(22);
    expect(SolidWasteDisposalReportService::DETAIL_EXPORT_FIELDS)->toHaveCount(17);
    expect(SolidWasteDisposalReportService::EXPORT_CONTEXT_COLUMN_COUNT)->toBe(5);
    expect(count($header))->toBe(
        SolidWasteDisposalReportService::EXPORT_CONTEXT_COLUMN_COUNT
        + count(SolidWasteDisposalReportService::DETAIL_EXPORT_FIELDS),
    );

    // DAFTAR KOLOM DETAIL DIASERSIKAN, BUKAN DIINGAT. Ketiga kolom yang tidak
    // muncul di satu angka layar pun ada di sini, dan justru karena itu
    // ketiganya yang paling mudah tertinggal.
    expect(SolidWasteDisposalReportService::DETAIL_EXPORT_FIELDS)->toBe([
        'event_date', 'shift', 'weighbridge_ticket_no', 'vehicle_no', 'driver_name',
        'solid_waste_type', 'source_station', 'gross_weight_mt', 'tare_weight_mt',
        'net_weight_mt', 'disposal_utilization_site', 'purpose_end_use',
        'gate_pass_no', 'security_seal_no', 'operator_id', 'remarks', 'findings',
    ]);

    // Label kolom mengikuti LAYAR DETAIL stasiun ini, bukan nama kolom DB —
    // dan kolom yang TIDAK ada di payload /summary tetap ada di berkas.
    foreach (['Shift', 'Gate Pass No', 'Security Seal No', 'Operator ID', 'Weighbridge Ticket No', 'Vehicle No', 'Driver Name'] as $label) {
        $this->assertContains($label, $header, "header ekspor kehilangan kolom {$label}");
    }

    $rows = array_map(fn (string $line) => swdReportCsvRow($line), array_slice($lines, 1));

    $dariR1 = array_slice($rows, 0, 3);
    $dariR2 = array_slice($rows, 3, 2);

    // KOLOM KONTEKS DIBANDINGKAN STRING DEMI STRING, bukan sekadar 'ada'.
    $konteksR1 = array_slice($dariR1[0], 0, 5);

    expect($konteksR1[0])->toBe('Periode Maret Alpha');
    expect($konteksR1[1])->toBe('Mill Alpha');
    expect($konteksR1[2])->toBe((string) ProductionLine::find($this->lineA)->name);
    expect($konteksR1[3])->toBe($this->day1);
    // STATUS RECORD dicetak lewat ExportValue::status() — bukan option(),
    // yang dipakai untuk kolom enum PENGUKURAN. Tabel detail stasiun ini
    // tidak punya satu pun kolom berenum, jadi option() memang tidak
    // dipanggil di sini.
    expect($konteksR1[4])->toBe(ExportValue::status(RecordStatus::Saved->value));
    expect($konteksR1[4])->toBe('Tersimpan');

    foreach ($dariR1 as $row) {
        expect(array_slice($row, 0, 5))->toBe($konteksR1);
    }

    $konteksR2 = array_slice($dariR2[0], 0, 5);

    expect($konteksR2[3])->toBe($this->day2);
    expect($konteksR2[4])->toBe(ExportValue::status(RecordStatus::DraftOngoing->value));
    expect($konteksR2[4])->toBe('Draft');

    foreach ($dariR2 as $row) {
        expect(array_slice($row, 0, 5))->toBe($konteksR2);
    }

    // Dan konteks kedua induk memang BERBEDA — kalau tidak, asersi 'sama
    // persis' di atas akan lolos secara hampa.
    $this->assertNotSame($konteksR1, $konteksR2);

    // Nilai shift tiap muatan ada di barisnya, dibandingkan PER BARIS.
    $kolomShift = array_search('Shift', $header, true);

    expect(array_column($rows, $kolomShift))
        ->toBe(['Shift 1', 'Shift 2', 'Shift 3', 'Shift 4', 'Shift 5']);
});

// ---------------------------------------------------------------------
// Case 43
// ---------------------------------------------------------------------
it('case 43 — ekspor menolak 422 untuk format tak dikenal SEBELUM satu byte pun dialirkan', function () {
    swdReportLoad(swdReportRecord($this->stationA, $this->day1), $this->day1);

    $this->actingAs($this->supervisorA);

    // DAFTAR TERTUTUP {csv, xlsx}, dibandingkan SECARA KETAT. Trim dan
    // penetapan default 'csv' terjadi SATU LAPIS DI ATAS service — di
    // SolidWasteDisposalReportController::queryString(), yang memulangkan null
    // untuk nilai kosong-setelah-trim sehingga format=' ' dan format absen
    // sama-sama menjadi 'csv' sementara ' csv ' menjadi 'csv'. Service-nya
    // sendiri sengaja TIDAK memaafkan apa pun: setiap nilai di bawah ditolak,
    // dan itulah yang membuat daftarnya benar-benar tertutup.
    foreach (['pdf', 'CSV', 'XLSX', '  ', '', ' csv '] as $format) {
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
        // TIDAK ADA response stream yang terbentuk: pemeriksaannya terjadi
        // sebelum writer dibuat, karena galat setelah header terkirim mendarat
        // sebagai berkas rusak, bukan sebagai pesan.
        expect($response)->toBeNull();
    }

    // Default parameter service-nya sendiri 'csv', jadi memanggilnya tanpa
    // format menghasilkan CSV — bukan exception.
    $default = $this->service->export($this->periodA, 'csv', null, $this->lineA);

    expect($default)->toBeInstanceOf(StreamedResponse::class);
    expect($default->headers->get('Content-Type'))->toContain('text/csv');

    expect(SolidWasteDisposalReportService::SUPPORTED_FORMATS)->toBe(['csv', 'xlsx']);
});

// ---------------------------------------------------------------------
// Case 44
// ---------------------------------------------------------------------
it('case 44 — ekspor menolak 422 EAGER ketika melampaui batas baris, dan batasnya INKLUSIF', function () {
    $this->actingAs($this->supervisorA);

    // Batas sungguhan tetap 50.000; yang diturunkan hanya milik subclass uji,
    // dan itu HANYA mungkin karena service membaca batasnya lewat `static::`.
    expect(SolidWasteDisposalReportService::EXPORT_ROW_LIMIT)->toBe(50000);

    $tiny = new SolidWasteDisposalReportTinyExportService;

    // ENAM muatan pada DUA record induk: batasnya menghitung BARIS YANG
    // DITERBITKAN, bukan record. Sebuah implementasi yang menghitung record
    // melihat 2 dan meloloskannya.
    $r1 = swdReportRecord($this->stationA, $this->day1);
    $r2 = swdReportRecord($this->stationA, $this->day2);

    foreach (range(1, 3) as $i) {
        swdReportLoad($r1, $this->day1);
        swdReportLoad($r2, $this->day2);
    }

    expect(SolidWasteDisposalRecord::query()->count())->toBe(2);
    expect(SolidWasteDisposalDetail::query()->count())->toBe(6);

    $thrown = null;

    try {
        $tiny->export($this->periodA, 'csv', null, $this->lineA);
    } catch (ValidationException $exception) {
        $thrown = $exception;
    }

    expect($thrown)->toBeInstanceOf(ValidationException::class);
    expect($thrown->status)->toBe(422);

    $pesan = $thrown->errors()['period_id'][0];

    // Pesannya MENYEBUT angka batasnya dan menyarankan mempersempit periode.
    $this->assertStringContainsString('5 baris muatan', $pesan);
    $this->assertStringContainsString('6 baris', $pesan);
    $this->assertStringContainsString('Persempit rentang periode', $pesan);

    // TEPAT PADA BATAS: ekspor BERJALAN — batasnya inklusif dan diuji pada
    // KEDUA sisinya.
    SolidWasteDisposalDetail::query()->orderBy('id')->limit(1)->get()->each->delete();

    expect(SolidWasteDisposalDetail::query()->count())->toBe(5);

    $lines = swdReportCsvLines(swdReportStreamed($tiny->export($this->periodA, 'csv', null, $this->lineA)));

    expect($lines)->toHaveCount(6);
});

// ---------------------------------------------------------------------
// Case 45
// ---------------------------------------------------------------------
it('case 45 — ekspor xlsx menghasilkan workbook .xlsx SUNGGUHAN lewat SheetWriter, bukan CSV berekstensi .xlsx', function () {
    $record = swdReportRecord($this->stationA, $this->day1);

    foreach (range(1, 3) as $i) {
        swdReportLoad($record, $this->day1, ['shift' => 'Shift '.$i]);
    }

    $this->actingAs($this->supervisorA);

    $response = $this->service->export($this->periodA, 'xlsx', null, $this->lineA);
    $body = swdReportStreamed($response);

    // DIASERSIKAN ATAS BYTE-NYA, bukan atas nama berkas atau Content-Type:
    // sampai 2026-10-04 setiap 'Ekspor Excel' mengirim byte CSV bernama .xlsx
    // dan Excel menolak membukanya, jadi nama berkas yang benar justru yang
    // paling mudah hijau secara hampa.
    $this->assertStringStartsWith("PK\x03\x04", $body);

    // xlsxRows() (tests/Pest.php) MELEMPAR bila paketnya tidak memuat
    // [Content_Types].xml, workbook, maupun xl/worksheets/sheet1.xml.
    $rows = xlsxRows($body);

    expect($rows[0])->toBe(SolidWasteDisposalReportService::EXPORT_HEADER);
    expect($rows)->toHaveCount(4);

    expect($response->headers->get('Content-Type'))
        ->toContain('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

// =====================================================================
// GROUP I — PINTU MASUK screen-140 DAN RIAK-nya (case 46-48)
// =====================================================================

// ---------------------------------------------------------------------
// Case 46
// ---------------------------------------------------------------------
it("case 46 — REPORT_ROUTES memuat kunci 'solid-waste-disposal' BERTANDA HUBUNG", function () {
    $this->assertArrayHasKey('solid-waste-disposal', StationReportService::REPORT_ROUTES);
    expect(StationReportService::REPORT_ROUTES['solid-waste-disposal'])->toBe('reports.solid-waste-disposal');

    // ASERSI NEGATIF: kunci snake_case akan ter-compile, terlihat benar, dan
    // tidak pernah cocok dengan satu baris master pun — laporannya ada,
    // seluruh test-nya sendiri lolos, dan tile-nya tetap kelabu.
    $this->assertArrayNotHasKey('solid_waste_disposal', StationReportService::REPORT_ROUTES);

    // Dan kuncinya memang nilai yang dibawa enum-nya, bukan literal yang
    // kebetulan sama.
    expect(StationTypeEnum::SolidWasteDisposal->value)->toBe('solid-waste-disposal');
});

// ---------------------------------------------------------------------
// Case 47
// ---------------------------------------------------------------------
it("case 47 — array_keys(REPORT_ROUTES) urut sort_order station_types, dengan 'solid-waste-disposal' DI ANTARA 'storage-tank' dan 'kernel-dispatch'", function () {
    // DIVERIFIKASI DARI TABEL station_types lewat kueri, BUKAN dari urutan
    // deklarasi enum — keduanya memang berbeda (enum menaruh
    // solid-waste-disposal di posisi ke-8).
    $berlaporan = StationType::query()
        ->orderBy('sort_order')
        ->pluck('sort_order', 'code')
        ->filter(fn ($sortOrder, $code) => array_key_exists($code, StationReportService::REPORT_ROUTES));

    expect($berlaporan->keys()->all())->toBe(array_keys(StationReportService::REPORT_ROUTES));

    $keys = array_keys(StationReportService::REPORT_ROUTES);
    $index = array_search('solid-waste-disposal', $keys, true);

    // solid-waste-disposal (150) TIDAK lagi di posisi terakhir sejak
    // screen-164 menambahkan kernel-dispatch (160) SETELAHNYA — kini ia kedua
    // dari belakang. Yang diasersikan tetap sebuah POSISI, bukan sekadar
    // keanggotaan: menyisipkan stasiun berikutnya di tempat yang salah harus
    // tetap memerahkan baris-baris ini.
    expect($index)->toBe(count($keys) - 2);
    expect($keys[$index - 1])->toBe('storage-tank');
    expect($keys[$index + 1])->toBe('kernel-dispatch');

    // Dan sort_order-nya memang yang membuat urutan itu benar.
    expect($berlaporan['storage-tank'])->toBe(140);
    expect($berlaporan['solid-waste-disposal'])->toBe(150);
    expect($berlaporan['kernel-dispatch'])->toBe(160);
});

// ---------------------------------------------------------------------
// Case 48
// ---------------------------------------------------------------------
it("case 48 — contoh 'stasiun belum berlaporan' kini kode FIKTIF; solid-waste-disposal DAN kernel-dispatch berlaporan", function () {
    // Separuh pertama riaknya hidup di
    // backend/tests/Unit/Services/StationReportServiceTest.php case 16 dan 18,
    // yang sudah dipindahkan ke 'zz-stasiun-fiktif'. Yang diasersikan DI SINI
    // adalah dua fakta yang membuat pemindahan itu benar dan TERAKHIR.

    // (1) solid-waste-disposal kini BERLAPORAN.
    $this->assertArrayHasKey('solid-waste-disposal', StationReportService::REPORT_ROUTES);

    // (2) Kode fiktif pembawanya tidak akan pernah mendapat entri — ia tidak
    //     ada di REPORT_ROUTES dan tidak ada di master station_types, jadi
    //     tidak ada layar laporan berikutnya yang dapat membasikannya.
    $this->assertArrayNotHasKey('zz-stasiun-fiktif', StationReportService::REPORT_ROUTES);
    expect(StationType::query()->where('code', 'zz-stasiun-fiktif')->exists())->toBeFalse();

    // (3) screen-164 SUDAH terjadi: kernel-dispatch kini BERLAPORAN, jadi ia
    //     keluar dari daftar kandidat di bawah. Transisinya dicatat di sini,
    //     bukan sekadar dilupakan.
    $this->assertArrayHasKey('kernel-dispatch', StationReportService::REPORT_ROUTES);
    expect(StationReportService::REPORT_ROUTES['kernel-dispatch'])->toBe('reports.kernel-dispatch');

    // (4) Sisa dua stasiun nyata yang belum berlaporan. KOPLINGNYA DISENGAJA:
    //     baris ini HARUS memerah di screen-166 (cpo-dispatch) dan screen-168
    //     (process-quality-control), supaya dua pergeseran terakhir dipaksa
    //     ditangani alih-alih lolos diam-diam.
    foreach (['cpo-dispatch', 'process-quality-control'] as $kandidat) {
        expect(StationType::query()->where('code', $kandidat)->exists())->toBeTrue();
        $this->assertArrayNotHasKey($kandidat, StationReportService::REPORT_ROUTES);
    }
});

// =====================================================================
// GROUP J — INVARIAN STRUKTURAL DI LUAR DAFTAR 48, tetapi dituntut
// implementation_notes dan aturan proyek
// =====================================================================

// ---------------------------------------------------------------------
// Case 49 — Operator diterima di service, DAN mill-nya tetap mill akunnya
// ---------------------------------------------------------------------
it('case 49 — Operator diterima pada jalur data dan tetap jatuh di cabang TERIKAT MILL', function () {
    $this->actingAs($this->operatorA);

    // Diterima guardAccess() sejak commit pertama — screen-163 (laporan SWD
    // mobile) pemanggil sahnya.
    expect($this->service->resolveBusinessUnit(null))->toBe((string) $this->businessUnitA->id);

    // DAN cabang yang menerimanya adalah cabang TERIKAT MILL, bukan cabang
    // Admin: business_unit_id kiriman klien DIBUANG. Tanpa baris Operator di
    // resolveBusinessUnit(), ia jatuh ke cabang Admin tempat parameter itu
    // JUSTRU dihormati — kebocoran lintas mill, bukan cacat tampilan.
    expect($this->service->resolveBusinessUnit((string) $this->businessUnitB->id))
        ->toBe((string) $this->businessUnitA->id);

    swdReportLoad(swdReportRecord($this->stationA, $this->day1), $this->day1);

    $summary = $this->service->buildSummary($this->periodA, (string) $this->businessUnitB->id, $this->lineA);

    expect($summary['business_unit']['id'])->toBe((string) $this->businessUnitA->id);
    expect($summary['loads']['load_count'])->toBe(1);

    // YANG TETAP TERTUTUP bagi Operator: pemilih mill.
    expect(fn () => $this->service->businessUnitOptions())->toThrow(AuthorizationException::class);
});

// ---------------------------------------------------------------------
// Case 50 — 401 sebelum apa pun dibaca
// ---------------------------------------------------------------------
it('case 50 — tanpa sesi terautentikasi setiap titik masuk melempar 401 sebelum satu baris pun dibaca', function () {
    foreach ([
        'businessUnitOptions' => fn () => $this->service->businessUnitOptions(),
        'listPeriods' => fn () => $this->service->listPeriods(null),
        'buildSummary' => fn () => $this->service->buildSummary($this->periodA, null, $this->lineA),
        'buildExportRows' => fn () => $this->service->buildExportRows($this->periodA, null, $this->lineA),
        'export' => fn () => $this->service->export($this->periodA, 'csv', null, $this->lineA),
        'authorizePeriod' => fn () => $this->service->authorizePeriod((string) $this->periodA->id),
    ] as $label => $call) {
        $thrown = null;

        $queries = swdReportQueriesDuring(function () use ($call, &$thrown) {
            try {
                $call();
            } catch (Throwable $exception) {
                $thrown = $exception;
            }
        });

        $this->assertInstanceOf(AuthenticationException::class, $thrown, "{$label} seharusnya 401");
        $this->assertFalse(
            swdReportTouched($queries, 'solid_waste_disposal_details'),
            "{$label} membaca baris muatan sebelum pemanggilnya dikenali",
        );
    }
});

// ---------------------------------------------------------------------
// Case 51 — 404 vs 403 bukan oracle keberadaan
// ---------------------------------------------------------------------
it('case 51 — authorizePeriod menjawab 404 untuk id yang tidak ada dan 403 untuk periode mill lain', function () {
    $periodeB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::SolidWasteDisposal->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Beta')
        ->create();

    $this->actingAs($this->supervisorA);

    expect(fn () => $this->service->authorizePeriod((string) Str::uuid()))
        ->toThrow(ModelNotFoundException::class);

    // ORACLE LINTAS MILL DITUTUP: periode mill lain menjawab 403, TIDAK PERNAH
    // 404 — perbedaan keduanya akan memastikan periode itu ada.
    expect(fn () => $this->service->authorizePeriod((string) $periodeB->id))
        ->toThrow(AuthorizationException::class);

    // Admin lolos untuk mill mana pun.
    $this->actingAs($this->admin);

    expect($this->service->authorizePeriod((string) $periodeB->id)->id)->toBe($periodeB->id);
});

// ---------------------------------------------------------------------
// Case 52 — eager load: SATU kueri detail, bukan satu per record
// ---------------------------------------------------------------------
it('case 52 — detail di-eager-load sekali, bukan satu kueri per record (N+1)', function () {
    foreach (range(1, 5) as $i) {
        $record = swdReportRecord($this->stationA, now()->subDays(30 + $i)->toDateString());

        foreach (range(1, 2) as $j) {
            swdReportLoad($record, now()->subDays(30 + $i)->toDateString());
        }
    }

    $this->actingAs($this->supervisorA);

    $summary = null;

    $queries = swdReportQueriesDuring(function () use (&$summary) {
        $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);
    });

    expect($summary['loads']['load_count'])->toBe(10);
    expect(SolidWasteDisposalRecord::query()->count())->toBe(5);

    // TEPAT SATU pernyataan membaca solid_waste_disposal_details. Tanpa
    // with('solidWasteDisposalDetails') angkanya 5 — dan itu dibuktikan
    // NON-VAKUM dengan melepas eager load-nya lebih dulu sampai baris ini
    // merah (5 !== 1), lalu memasangnya kembali.
    $this->assertSame(
        1,
        swdReportCountTouching($queries, 'solid_waste_disposal_details'),
        'detail harus dibaca dalam SATU kueri; lebih dari satu berarti N+1',
    );
});

// ---------------------------------------------------------------------
// Case 53 — nol penyaring status di seluruh kueri
// ---------------------------------------------------------------------
it('case 53 — tidak satu kueri pun menyaring status record', function () {
    swdReportLoad(
        swdReportRecord($this->stationA, $this->day1, ['status' => RecordStatus::DraftPaused]),
        $this->day1,
    );

    $this->actingAs($this->supervisorA);

    $summary = null;

    $queries = swdReportQueriesDuring(function () use (&$summary) {
        $summary = $this->service->buildSummary($this->periodA, null, $this->lineA);
    });

    // draft_paused ikut DRAFT_STATUSES.
    expect($summary['loads']['load_count'])->toBe(1);
    expect($summary['draft_load_count'])->toBe(1);

    foreach ($queries as $sql) {
        if (str_contains($sql, 'solid_waste_disposal_records')) {
            $this->assertStringNotContainsString(
                '"status"',
                $sql,
                'kueri record tidak boleh menyaring status: '.$sql,
            );
        }
    }

    expect(SolidWasteDisposalReportService::DRAFT_STATUSES)
        ->toBe([RecordStatus::DraftOngoing->value, RecordStatus::DraftPaused->value]);
});

// ---------------------------------------------------------------------
// Case 54 — production_line_id dibaca dari SNAPSHOT record, bukan stations
// ---------------------------------------------------------------------
it('case 54 — penyaring line memakai kolom snapshot pada record, bukan stations.production_line_id', function () {
    $lineLain = ProductionLine::factory()->forBusinessUnit($this->businessUnitA)->create();

    // Record ber-station di lineA tetapi ber-snapshot line LAIN: inilah
    // bentuk 'stasiun sudah dipindah ke line lain'. Menyaring lewat `stations`
    // akan MENULIS ULANG SEJARAH — angka bulan lalu berubah diam-diam.
    $dipindah = swdReportRecord($this->stationA, $this->day1, [
        'production_line_id' => $lineLain->id,
    ]);
    swdReportLoad($dipindah, $this->day1);

    $tetap = swdReportRecord($this->stationA, $this->day1);
    swdReportLoad($tetap, $this->day1);
    swdReportLoad($tetap, $this->day1);

    $this->actingAs($this->supervisorA);

    expect($this->service->buildSummary($this->periodA, null, $this->lineA)['loads']['load_count'])->toBe(2);
    expect($this->service->buildSummary($this->periodA, null, (string) $lineLain->id)['loads']['load_count'])->toBe(1);
});

// ---------------------------------------------------------------------
// Case 55 — productionLineInfo dibatasi ke mill-nya
// ---------------------------------------------------------------------
it('case 55 — nama production line mill lain tidak pernah terbit lewat summary', function () {
    $this->actingAs($this->admin);

    // Admin memilih mill-A tetapi menyebut line mill-B: resolveProductionLine()
    // menolaknya 403 — separuh kedua dari jaminan 'line mill lain ditolak'.
    expect(fn () => $this->service->buildSummary(
        $this->periodA,
        (string) $this->businessUnitA->id,
        $this->lineB,
    ))->toThrow(AuthorizationException::class);

    // Dan daftar opsinya pun tidak pernah memuat line mill lain.
    $options = $this->service->productionLineOptions((string) $this->businessUnitA->id);

    expect(array_column($options, 'id'))->not->toContain($this->lineB);
});

// ---------------------------------------------------------------------
// Case 56 — SKEMANYA: event_date NOT NULL
// ---------------------------------------------------------------------
it('case 56 — skema MENOLAK muatan tanpa event_date, sehingga cabang undated tidak dapat dicapai lewat basis data', function () {
    $record = swdReportRecord($this->stationA, $this->day1);

    // Migrasi 2026_08_31_000003 mendeklarasikan $table->date('event_date')
    // TANPA ->nullable(), dan tidak ada migrasi sesudahnya yang mengubahnya.
    // Fakta ini DICATAT SEBAGAI ASERSI supaya tidak ditemukan ulang dari nol:
    // ia yang memaksa case 32, 33 dan 36 memakai koleksi di memori.
    $this->expectException(Illuminate\Database\QueryException::class);

    SolidWasteDisposalDetail::factory()->forRecord($record)->create(['event_date' => null]);
});
