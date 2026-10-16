<?php

/**
 * LaporanProcessWaterTest (Feature/Livewire) —
 * screen-160--laporan-process-water-web, halaman webnya sendiri.
 *
 * Satu uji per entri `test_scenarios[].component_test` pada tech spec
 * screen-160 (33 skenario), ditambah DUA unit test case yang memang
 * menyangkut komponen ini dan blade-nya (case 50 dan 51) dan satu asersi
 * pintu masuk dari Laporan Stasiun (screen-140).
 *
 * LAPISAN INI ADA UNTUK KEADAAN YANG TIDAK BISA DIATUR DI TEMPAT LAIN.
 * Angkanya sudah dibuktikan dua kali terhadap data yang dikendalikan penuh —
 * atas service-nya di tests/Unit/Services/ProcessWaterReportServiceTest.php
 * dan atas kontrak HTTP-nya di tests/Feature/Api/LaporanProcessWaterTest.php
 * — jadi berkas ini TIDAK mengulanginya. Yang dibuktikan di sini adalah apa
 * yang HALAMAN lakukan terhadap angka itu: pemilih mana yang ada untuk peran
 * mana, keadaan kosong mana yang digambar, dan hal-hal yang layar ini tidak
 * boleh biarkan terbaca salah.
 *
 * DIASERSI ATAS HTML TER-RENDER, bukan atas state komponen, di mana pun
 * klaimnya tentang apa yang pembaca lihat: sebuah data-testid yang ada di
 * markup tetapi tidak pernah dicapai oleh kondisional blade akan tetap
 * meloloskan asersi atas state.
 *
 * DAN SELALU ATAS ELEMENNYA, BUKAN ATAS SUBSTRING HALAMAN. Label layar ini
 * banyak yang pendek — 'Shift', 'pH', 'TDS', 'ppm', '—' — dan ketiga
 * keterangan panjangnya (aturan sebelas kolom, ketiadaan penandaan,
 * ketiadaan kolom shift) MENGEJA kata-kata itu di dalam prosanya. Asersi
 * substring atas seluruh halaman karena itu dapat hijau tanpa membuktikan
 * apa pun; setiap klaim di bawah menyasar potongan elemen ber-data-testid.
 *
 * ────────────────────────────────────────────────────────────────────────
 * TIGA HAL STRUKTURAL YANG HANYA LAPISAN INI MENJAGA
 * ────────────────────────────────────────────────────────────────────────
 *   1. canAccess() MEMEGANG DAFTAR PERANNYA SENDIRI dan TIDAK mendelegasikan
 *      ke ProcessWaterReportService::guardAccess(). Service itu MENERIMA
 *      Operator SEJAK COMMIT PERTAMA (kembaran mobile screen-161 dibangun
 *      dalam seri yang sama), jadi delegasi akan membuka layar web ini untuk
 *      Operator dalam tarikan napas yang sama — tanpa satu galat pun dan
 *      tanpa apa pun di layar yang menandainya. Di sini bahayanya BUKAN di
 *      masa depan: ia sudah ada hari ini. Case 50 dan skenario 25 yang
 *      menjaganya.
 *   2. KOTAK KETERANGAN MEMAKAI `.md-explain`, BUKAN `.md-threshold`.
 *      Keduanya tampil identik dan yang berbeda hanya namanya; layar ini
 *      sengaja tidak menandai satu pun nilai di luar batas, dan ketiadaan itu
 *      diasersi MENURUT NAMA KELAS pada HTML ter-render. Memakai kelas
 *      bernama 'threshold' untuk menjelaskan bahwa tidak ada ambang akan
 *      memerahkan asersi itu justru karena namanya.
 *   3. NOL BLOK SEBARAN KATEGORIS DI DOM, diasersi atas TESTID dan JUDUL —
 *      bukan atas substring 'sebaran'. Layar ini MENYATAKAN mengapa
 *      `action_taken_status` diperlakukan sebagai teks, dan pernyataan itu
 *      sendiri memuat kata 'sebaran'.
 *
 * DAN SEMBILAN KEADAAN yang database e2e bersama tidak bisa menyusunnya,
 * karena setiap test di sini punya databasenya sendiri: mill tanpa satu pun
 * periode Process Water, periode berisi record tetapi NOL slot terisi,
 * periode yang belum mulai (slot harapan nol), periode yang masih berjalan,
 * sebuah metrik yang tidak pernah diisi sepanjang periode, slot yang hanya
 * mengisi kolom identitas, kedua kolom teks yang kosong sepanjang periode,
 * akun terikat mill dengan business_unit_id null, dan id mill/line/periode
 * milik mill lain yang disuapkan lewat properti komponen. Spec browser
 * screen-160 sengaja tidak mencoba satu pun dari itu.
 *
 * TANGGALNYA SELALU RELATIF (now()->subDays(...)), dan tanggal record SELALU
 * DIPAKU lewat ->onDate(): default ProcessWaterRecordFactory adalah
 * dateTimeBetween('-30 days','now'), dan asersi "tepat N baris" yang
 * menumpang default itu gagal sesekali karena alasan yang tidak ada
 * hubungannya dengan layar ini.
 */

use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Livewire\Dashboard\LaporanProcessWater;
use App\Livewire\Dashboard\LaporanStasiun;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\ProcessWaterDetail;
use App\Models\ProcessWaterRecord;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use App\Services\ProcessWaterRecordService;
use App\Services\ProcessWaterReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Tiap data-testid yang HANYA ada setelah sebuah angka benar-benar dirender —
 * yaitu setelah mill, production line DAN periode terpilih. Berlaku pada
 * periode berdata MAUPUN periode kosong: kartu metrik tetap digambar dengan
 * nilai tidak tersedia, karena kartu yang hilang terbaca sebagai "parameter
 * itu tidak ada".
 */
const LAPORAN_PROCESS_WATER_FIGURE_TESTIDS = [
    'coverage',
    'coverage-slots',
    'coverage-percent',
    'coverage-formula',
    'coverage-note',
    // KETERANGAN TERPENTING DI LAYAR INI: tiga belas kolom adalah yang tampak
    // wajar dari skema, dan sebelas adalah yang benar.
    'eleven-columns-note',
    // KEEMPAT blok metrik, mengikuti RANTAI PENGOLAHAN AIR.
    'metric-block-water-flow',
    'metric-block-softener',
    'metric-block-chemical-dosing',
    'metric-block-boiler-feed',
    'metric-card',
    'metric-avg',
    'metric-range',
    'metric-denominator',
    // Keterangan yang menyatakan apa yang layar ini sengaja TIDAK lakukan.
    'treatment-chain-note',
    'softener-note',
    'dosing-unit-note',
    'no-threshold-note',
    // DUA bagian teks bebas yang TERPISAH, masing-masing dengan
    // keterangannya.
    'action-taken-entries',
    'action-taken-is-text-note',
    'findings',
    'findings-note',
    // Ketiadaan yang DINYATAKAN.
    'not-published',
    'no-downtime-note',
    'no-shift-note',
];

/** Testid yang baru ada ketika periodenya BENAR-BENAR punya slot terisi. */
const LAPORAN_PROCESS_WATER_DATA_TESTIDS = [
    'daily-trend-raw-water-flow',
    'daily-trend-raw-water-flow-note',
    // REKAP PER INSPECTOR DI LAYAR — penyimpangan sadar dari preseden Storage
    // Tank, diminta pemilik produk 2026-10-16.
    'inspector-recap-details',
    'inspector-recap-toggle',
    'inspector-recap-table',
    'inspector-not-recorded',
    'inspector-recorded-total',
    'inspector-recap-purpose-note',
    'inspector-recap-raw-code-note',
    'by-unit-details',
    'by-unit-toggle',
    'by-unit-table',
    'by-unit-row',
    'by-unit-row-total',
    'by-unit-note',
    'by-unit-scroll-hint',
    'daily-recap-details',
    'daily-recap-toggle',
    'daily-table',
    'daily-row-total',
    'daily-recap-note',
];

function laporanProcessWaterComponentRecord(
    Station $station,
    string $date,
    string $processWaterId = 'PW-1',
    array $attributes = [],
): ProcessWaterRecord {
    return ProcessWaterRecord::factory()
        ->forStation($station)
        ->onDate($date)
        ->create(array_merge(['process_water_id' => $processWaterId, 'note' => 'Catatan harian'], $attributes));
}

function laporanProcessWaterComponentSlot(
    ProcessWaterRecord $record,
    string $timeSlot,
    array $values = [],
): ProcessWaterDetail {
    return ProcessWaterDetail::factory()
        ->forRecord($record)
        ->timeSlot($timeSlot)
        ->create($values);
}

/**
 * Isi satu elemen yang dikenali data-testid-nya, dipotong dari HTML.
 *
 * Dipakai setiap kali klaimnya tentang SATU ELEMEN tertentu: "tidak ada teks
 * nol persen di mana pun pada halaman" akan selalu gagal kalau diasersi atas
 * seluruh halaman, karena keterangan cakupan dan rekap per inspector memuat
 * banyak persentase lain.
 */
function laporanProcessWaterComponentSlice(string $html, string $testid, string $closing = '</p>'): string
{
    $start = strpos($html, 'data-testid="'.$testid.'"');

    if ($start === false) {
        return '';
    }

    $end = strpos($html, $closing, $start);

    return $end === false ? substr($html, $start) : substr($html, $start, $end - $start);
}

/**
 * Potongan satu kartu metrik, dikenali dari atribut data-metric-nya.
 *
 * KARTU, bukan baris tabel: laporan ini merender kesembilan metrik sebagai
 * kartu di dalam empat blok tata letak, dan tiap kartu HARUS mencetak
 * penyebutnya sendiri.
 */
function laporanProcessWaterComponentCard(string $html, string $column): string
{
    $start = strpos($html, 'data-metric="'.$column.'"');

    if ($start === false) {
        return '';
    }

    $end = strpos($html, '</article>', $start);

    return $end === false ? substr($html, $start) : substr($html, $start, $end - $start);
}

/**
 * Potongan satu baris rekap per inspector, dikenali dari
 * data-inspector-id-nya.
 *
 * DIPAKAI ALIH-ALIH MEMINDAI TEKS TABELNYA: kode inspector di lapangan
 * sependek 'OP-07' dan bahkan '1180734', dan angka telanjang cocok di mana
 * saja — termasuk di dalam porsi persennya pada sel ketiga baris yang sama.
 */
function laporanProcessWaterComponentInspectorRow(string $html, string $inspectorId): string
{
    $table = laporanProcessWaterComponentSlice($html, 'inspector-recap-table', '</table>');
    $start = strpos($table, 'data-inspector-id="'.$inspectorId.'"');

    if ($start === false) {
        return '';
    }

    $end = strpos($table, '</tr>', $start);

    return $end === false ? substr($table, $start) : substr($table, $start, $end - $start);
}

/** Nilai sel ke-$index (1-based) di dalam satu potongan <tr>. */
function laporanProcessWaterComponentCell(string $row, int $index): ?string
{
    if (preg_match_all('#<td[^>]*>(.*?)</td>#s', $row, $matches) < $index) {
        return null;
    }

    return trim(strip_tags($matches[1][$index - 1]));
}

/** Tanggal seperti yang dicetak blade: '07 Okt 2026'. */
function laporanProcessWaterComponentTanggal(string $date): string
{
    $bulan = ['01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr', '05' => 'Mei', '06' => 'Jun',
        '07' => 'Jul', '08' => 'Agu', '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'];

    [$y, $m, $d] = array_pad(explode('-', substr($date, 0, 10)), 3, '');

    return $d.' '.($bulan[$m] ?? $m).' '.$y;
}

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->processWater()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->processWater()->create();

    $this->lineA = (string) $this->stationA->production_line_id;
    $this->lineB = (string) $this->stationB->production_line_id;

    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    $this->periodStart = now()->subDays(20)->toDateString();
    $this->periodEnd = now()->subDays(11)->toDateString();

    $this->day1 = now()->subDays(19)->toDateString();
    $this->day2 = now()->subDays(18)->toDateString();
    $this->day3 = now()->subDays(17)->toDateString();
    $this->dayBeforePeriod = now()->subDays(21)->toDateString();
    $this->dayAfterPeriod = now()->subDays(10)->toDateString();

    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::ProcessWater->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Process Water Alpha')
        ->create();

    $this->slots = ProcessWaterRecordService::canonicalTimeSlots();
});

// =====================================================================
// Jalur sukses
// =====================================================================

it('skenario 1 — Supervisor: tanpa pemilih mill, seluruh bagian laporan terender', function () {
    $record = laporanProcessWaterComponentRecord($this->stationA, $this->day1);

    laporanProcessWaterComponentSlot($record, $this->slots[0], array_fill_keys(
        ProcessWaterReportService::NUMERIC_METRICS,
        7.5,
    ) + [
        'inspector_id' => 'INS-01',
        'action_taken_status' => 'Dosis alum dinaikkan',
        'findings' => 'Air baku keruh',
    ]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanProcessWater::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Peran terikat mill tidak melihat pemilih mill — menawarkan pemilih yang
    // tidak bisa ia pakai adalah kebohongan kecil yang mahal.
    expect($html)->not->toContain('data-testid="mill-select"');
    expect($html)->toContain('data-testid="mill-name"');
    expect($html)->toContain('Mill Alpha');

    foreach (LAPORAN_PROCESS_WATER_FIGURE_TESTIDS as $testid) {
        $this->assertStringContainsString('data-testid="'.$testid.'"', $html, "testid {$testid} tidak dirender");
    }

    foreach (LAPORAN_PROCESS_WATER_DATA_TESTIDS as $testid) {
        $this->assertStringContainsString('data-testid="'.$testid.'"', $html, "testid {$testid} tidak dirender");
    }

    // KESEMBILAN kartu metrik, masing-masing dengan penyebutnya sendiri.
    expect(substr_count($html, 'data-testid="metric-card"'))->toBe(9);
    expect(substr_count($html, 'data-testid="metric-denominator"'))->toBe(9);
    expect(substr_count($html, 'data-testid="metric-avg"'))->toBe(9);
    expect(substr_count($html, 'data-testid="metric-range"'))->toBe(9);
    expect(substr_count($html, 'data-metric="'))->toBe(9);
    expect(count(ProcessWaterReportService::NUMERIC_METRICS))->toBe(9);

    foreach (ProcessWaterReportService::NUMERIC_METRICS as $column) {
        $this->assertStringContainsString('data-metric="'.$column.'"', $html, "kartu metrik {$column} tidak dirender");
    }

    // KEEMPAT blok membawa data-metric-group-nya, diturunkan dari RESPONS —
    // bukan dari daftar nama kolom yang dipaku di template.
    foreach (['water_flow', 'softener', 'chemical_dosing', 'boiler_feed'] as $group) {
        $this->assertStringContainsString('data-metric-group="'.$group.'"', $html, "blok {$group} tidak dirender");
    }

    // Blok kelengkapan muncul SEBELUM kartu metrik pertama di urutan DOM —
    // periode yang terisi seperlima pun menghasilkan rata-rata yang terlihat
    // rapi, jadi pembaca harus melihat kelengkapannya lebih dulu.
    expect(strpos($html, 'data-testid="coverage"'))->toBeLessThan(strpos($html, 'data-testid="metric-card"'));
    expect(strpos($html, 'data-testid="coverage"'))
        ->toBeLessThan(strpos($html, 'data-testid="metric-block-water-flow"'));

    // NOL ELEMEN BLOK SEBARAN KATEGORIS DI DOM — diasersi atas TESTID dan
    // JUDUL, bukan atas substring 'sebaran': layar ini MENYATAKAN mengapa
    // action_taken_status diperlakukan sebagai teks, dan pernyataannya sendiri
    // memuat kata itu.
    foreach ([
        'status-distribution', 'status-distribution-action-taken', 'status-row',
        'status-not-recorded', 'status-recorded-total', 'status-distribution-note',
        'status-distribution-table',
    ] as $testid) {
        $this->assertStringNotContainsString('data-testid="'.$testid.'"', $html, "elemen sebaran {$testid} dirender");
    }

    expect($html)->not->toContain('<h3>Sebaran');
    expect($html)->not->toContain('data-status-value=');

    // DUA rekap teks TERPISAH dengan judulnya masing-masing.
    expect($html)->toContain('Rekap Catatan Tindakan');
    expect($html)->toContain('Rekap Temuan');
    expect($html)->toContain('Dosis alum dinaikkan');
    expect($html)->toContain('Air baku keruh');

    // Satu tren harian, dan hanya satu.
    expect(substr_count($html, 'data-testid="daily-trend-'))->toBeGreaterThan(0);
    expect($html)->toContain('Tren Harian Aliran Air Baku');

    // Kedua tombol ekspor tampil.
    expect($html)->toContain('data-testid="export-button"');
    expect($html)->toContain('data-testid="export-excel-button"');

    // Bacaan saja: tidak ada satu pun kontrol tulis.
    expect($html)->not->toContain('>Simpan<');
    expect($html)->not->toContain('>Hapus<');
    expect($html)->not->toContain('>Verifikasi<');
});

it('skenario 1b — Mill Management melihat layar yang SAMA PERSIS dengan Supervisor', function () {
    $record = laporanProcessWaterComponentRecord($this->stationA, $this->day1);
    laporanProcessWaterComponentSlot($record, $this->slots[0], ['raw_water_flow_m3h' => 120.0]);

    $html = Livewire::actingAs($this->millManagement)
        ->test(LaporanProcessWater::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->not->toContain('data-testid="mill-select"');
    expect($html)->toContain('data-testid="mill-name"');
    expect($html)->toContain('Mill Alpha');
    expect(substr_count($html, 'data-testid="metric-card"'))->toBe(9);
    expect($html)->toContain('data-testid="coverage"');
});

it('skenario 2 — Admin: pemilih mill dirender, dan isinya identik dengan peran terikat mill', function () {
    $record = laporanProcessWaterComponentRecord($this->stationA, $this->day1);
    laporanProcessWaterComponentSlot($record, $this->slots[0], ['raw_water_flow_m3h' => 120.0]);

    $html = Livewire::actingAs($this->admin)
        ->test(LaporanProcessWater::class)
        ->set('businessUnitId', (string) $this->businessUnitA->id)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->toContain('data-testid="mill-select"');
    expect($html)->toContain('data-testid="coverage"');
    expect(substr_count($html, 'data-testid="metric-card"'))->toBe(9);
    // Nama mill terpilih tampil di hero.
    expect(laporanProcessWaterComponentSlice($html, 'report-hero', '</section>'))->toContain('Mill Alpha');
    // Daftar periode hanya milik mill terpilih.
    expect($html)->toContain('Periode Process Water Alpha');
});

it('skenario 4 — Admin tanpa memilih mill: arahan memilih mill, NOL angka, tanpa tombol ekspor', function () {
    $html = Livewire::actingAs($this->admin)
        ->test(LaporanProcessWater::class)
        ->html();

    expect($html)->toContain('data-testid="mill-select"');
    expect($html)->toContain('data-testid="mill-select-hint"');

    foreach (['coverage', 'coverage-slots', 'metric-card', 'export-button', 'export-excel-button'] as $testid) {
        $this->assertStringNotContainsString('data-testid="'.$testid.'"', $html, "testid {$testid} seharusnya tidak dirender");
    }
});

it('skenario 3 — Production Line belum dipilih: arahan memilih line, NOL angka, tanpa tombol ekspor', function () {
    laporanProcessWaterComponentSlot(
        laporanProcessWaterComponentRecord($this->stationA, $this->day1),
        $this->slots[0],
        ['raw_water_flow_m3h' => 120.0],
    );

    $component = Livewire::actingAs($this->supervisor)->test(LaporanProcessWater::class);

    $html = $component->html();

    expect($html)->toContain('data-testid="select-production-line-hint"');

    foreach (['coverage', 'coverage-slots', 'coverage-percent', 'metric-card', 'export-button', 'export-excel-button'] as $testid) {
        $this->assertStringNotContainsString('data-testid="'.$testid.'"', $html, "testid {$testid} seharusnya tidak dirender");
    }

    // TIDAK ADA jalur mundur ke angka seluruh mill, dan itu DINYATAKAN.
    expect(laporanProcessWaterComponentSlice($html, 'select-production-line-hint', '</div>'))
        ->toContain('tidak menampilkan angka seluruh mill');

    // Mengganti periode TIDAK memunculkan angka selama line belum dipilih:
    // penjaganya ada di FUNGSI pemuatan ringkasan, bukan hanya di template.
    $afterPeriod = $component->set('periodId', (string) $this->periodA->id)->html();

    expect($afterPeriod)->not->toContain('data-testid="coverage"');
    expect($afterPeriod)->not->toContain('data-testid="metric-card"');

    // Dan aksi ekspor yang dipanggil langsung tidak menghasilkan berkas apa
    // pun — penjagaannya ada di metodenya, bukan hanya pada tombolnya.
    expect($component->call('exportCsv', 'csv')->effects)->not->toHaveKey('download');
});

it('case 51 / skenario 5 — Admin mengganti mill MENGOSONGKAN periode DAN production line', function () {
    $record = laporanProcessWaterComponentRecord($this->stationA, $this->day1);
    laporanProcessWaterComponentSlot($record, $this->slots[0], ['raw_water_flow_m3h' => 120.0]);

    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::ProcessWater->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Process Water Beta')
        ->create();

    $component = Livewire::actingAs($this->admin)
        ->test(LaporanProcessWater::class)
        ->set('businessUnitId', (string) $this->businessUnitA->id)
        ->set('productionLineId', $this->lineA);

    // Laporannya tampil lebih dulu — tanpa ini, "menjadi kosong" tidak
    // membuktikan apa pun.
    $component->assertSet('periodId', (string) $this->periodA->id);
    expect($component->html())->toContain('data-testid="coverage"');

    $afterSwitch = $component->set('businessUnitId', (string) $this->businessUnitB->id);

    // KEDUANYA dikosongkan: sebuah periode MAUPUN sebuah line milik tepat
    // satu mill, jadi membawa salah satunya melintas akan merender penolakan
    // untuk sesuatu yang tidak dilakukan pengguna (QA LP-W-09).
    $afterSwitch->assertSet('periodId', '');
    $afterSwitch->assertSet('productionLineId', '');

    $html = $afterSwitch->html();

    expect($html)->not->toContain('data-testid="coverage"');
    expect($html)->not->toContain('data-testid="metric-card"');
    // Daftar periode dimuat ulang untuk mill B tanpa memuat periode mill A.
    expect($html)->toContain('Periode Process Water Beta');
    expect($html)->not->toContain('Periode Process Water Alpha');
    // Dan BUKAN pesan galat tentang periode yang tidak cocok.
    expect($html)->not->toContain('data-testid="forbidden-notice"');
});

it('skenario 6 — mill tanpa SATU PUN periode Process Water: arahan menghubungi Admin, tanpa angka', function () {
    Period::query()->delete();

    Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::EngineRoom->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Engine Room')
        ->create();

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanProcessWater::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->toContain('data-testid="no-period-hint"');
    expect(laporanProcessWaterComponentSlice($html, 'no-period-hint', '</div>'))->toContain('Hubungi Admin');
    // Pemilih periode sengaja TETAP kosong dan TIDAK diisi periode milik
    // jenis stasiun lain.
    expect($html)->not->toContain('Periode Engine Room');
    expect($html)->not->toContain('data-testid="coverage"');
    expect($html)->not->toContain('data-testid="metric-card"');
});

// =====================================================================
// Keadaan kosong dan aturan sebelas kolom
// =====================================================================

it('skenario 7 — periode tanpa data: 9 kartu "tidak tersedia" dengan 0 pembacaan, tanpa grafik kosong', function () {
    $empty = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::ProcessWater->value)
        ->range(now()->subDays(40)->toDateString(), now()->subDays(31)->toDateString())
        ->open()
        ->named('Periode Tanpa Data')
        ->create();

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanProcessWater::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $empty->id)
        ->html();

    expect($html)->toContain('data-testid="empty-state"');
    // Blok kelengkapan TETAP dirender — kelengkapan pencatatan adalah ISI
    // laporan, bukan catatan kaki.
    expect($html)->toContain('data-testid="coverage"');
    // KESEMBILAN kartu tetap digambar: kartu yang hilang terbaca sebagai
    // "parameter itu tidak ada", padahal yang benar "tidak ada yang
    // mengukurnya".
    expect(substr_count($html, 'data-testid="metric-card"'))->toBe(9);

    foreach (ProcessWaterReportService::NUMERIC_METRICS as $column) {
        $card = laporanProcessWaterComponentCard($html, $column);

        $this->assertStringContainsString('tidak tersedia', $card, "kartu {$column} tidak menampilkan tidak tersedia");
        $this->assertStringContainsString(
            'dari <b>0</b> pembacaan',
            $card,
            "kartu {$column} tidak mencetak penyebut nol",
        );
    }

    // Tren harian, rekap per inspector, rekap per unit dan rekap harian TIDAK
    // digambar: grafik kosong terbaca sebagai garis datar hasil pengukuran.
    foreach (LAPORAN_PROCESS_WATER_DATA_TESTIDS as $testid) {
        $this->assertStringNotContainsString('data-testid="'.$testid.'"', $html, "testid {$testid} seharusnya tidak dirender");
    }
});

it('skenario 8 — satu metrik kosong sepanjang periode, 8 metrik lain tetap berangka', function () {
    $record = laporanProcessWaterComponentRecord($this->stationA, $this->day1);

    $values = array_fill_keys(ProcessWaterReportService::NUMERIC_METRICS, 7.5);
    unset($values['boiler_feed_tds_ppm']);

    laporanProcessWaterComponentSlot($record, $this->slots[0], $values);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanProcessWater::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    $tds = laporanProcessWaterComponentCard($html, 'boiler_feed_tds_ppm');

    // Kartunya TETAP dirender dengan tidak tersedia dan penyebut nol …
    expect($tds)->not->toBe('');
    expect($tds)->toContain('tidak tersedia');
    expect($tds)->toContain('dari <b>0</b> pembacaan');

    // … dan tetap DI DALAM blok Air Umpan Boiler, bukan dipindah ke bagian
    // lain. Diasersi sebagai KETURUNAN elemennya.
    $block = laporanProcessWaterComponentSlice($html, 'metric-block-boiler-feed', '</section>');

    expect($block)->toContain('data-metric="boiler_feed_tds_ppm"');

    // 8 kartu lain berangka, dengan penyebut 1.
    foreach (ProcessWaterReportService::NUMERIC_METRICS as $column) {
        if ($column === 'boiler_feed_tds_ppm') {
            continue;
        }

        expect(laporanProcessWaterComponentCard($html, $column))->toContain('dari <b>1</b> pembacaan');
    }
});

it('skenario 9 — slot yang hanya mengisi kolom teks bebas: slot terisi MELEBIHI tiap penyebut', function () {
    $record = laporanProcessWaterComponentRecord($this->stationA, $this->day1);

    foreach (array_slice($this->slots, 0, 5) as $index => $slot) {
        laporanProcessWaterComponentSlot($record, $slot, ['findings' => 'Temuan '.$index]);
    }

    foreach (array_slice($this->slots, 5, 2) as $index => $slot) {
        laporanProcessWaterComponentSlot($record, $slot, ['raw_water_flow_m3h' => 100.0 + $index]);
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanProcessWater::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Jumlah slot terisi dibaca dari SEL-nya sendiri, tidak dari halaman.
    $slots = laporanProcessWaterComponentSlice($html, 'coverage-slots', '</span>');

    expect($slots)->toContain('7');

    // Penyebut terbesar di antara kesembilan kartu bernilai 2 — dan kedua
    // angka itu DIBANDINGKAN, bukan dipaku masing-masing.
    $denominators = [];

    foreach (ProcessWaterReportService::NUMERIC_METRICS as $column) {
        $foot = laporanProcessWaterComponentSlice(
            laporanProcessWaterComponentCard($html, $column),
            'metric-denominator',
            '</p>',
        );

        expect(preg_match('#dari <b>([\d.]+)</b> pembacaan#', $foot, $m))->toBe(1);

        $denominators[] = (int) str_replace('.', '', $m[1]);
    }

    expect(max($denominators))->toBe(2);
    expect(7)->toBeGreaterThan(max($denominators));

    // KETERANGAN ATURAN SEBELAS KOLOM DIRENDER UTUH, dan ia menyebut KEDUA
    // kolom identitas yang dikecualikan. Diasersi pada elemennya sendiri,
    // bukan atas halaman: 'shift' dan 'inspector_id' muncul di beberapa
    // keterangan lain pada layar ini.
    $note = laporanProcessWaterComponentSlice($html, 'eleven-columns-note', '</div>');

    expect($note)->not->toBe('');
    expect($note)->toContain('SEBELAS kolom pembacaan');
    expect($note)->toContain('bukan tiga belas');
    expect($note)->toContain('<code>shift</code>');
    expect($note)->toContain('<code>inspector_id</code>');
    // Frasanya terpotong baris pada blade, jadi dicocokkan dengan regex yang
    // menoleransi spasi — bukan dengan substring yang akan gagal karena
    // pemformatan sumbernya, bukan karena isinya.
    expect($note)->toMatch('/kolom\s+identitas, bukan kolom pembacaan/');
    // Dan ia MENYATAKAN bahwa jumlah slot terisi dapat MELEBIHI penyebut
    // setiap kartu — itulah keterangan yang membedakan selisih yang benar
    // dari ketidaksesuaian.
    expect($note)->toContain('MELEBIHI penyebut setiap kartu metrik');
});

it('skenario 10 / case 17 di layar — satu slot hanya inspector_id, satu lagi hanya findings: slot terisi 1', function () {
    $record = laporanProcessWaterComponentRecord($this->stationA, $this->day1);

    laporanProcessWaterComponentSlot($record, '07:00', ['inspector_id' => 'INS-01']);
    laporanProcessWaterComponentSlot($record, '08:00', ['findings' => 'Keruh di bak pengendap']);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanProcessWater::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // SATU, bukan dua: implementasi yang menghitung ke-13 kolom akan mencetak
    // 2 di sel ini.
    $slots = laporanProcessWaterComponentSlice($html, 'coverage-slots', '</span>');

    expect($slots)->toContain('<strong>1</strong>');
    expect($slots)->not->toContain('<strong>2</strong>');

    // Dan keterangan pengecualian kedua kolom identitas terlihat.
    $note = laporanProcessWaterComponentSlice($html, 'eleven-columns-note', '</div>');

    expect($note)->toContain('<b>dikecualikan</b>');
    expect($note)->toContain('<code>inspector_id</code>');

    // Slot ber-inspector_id SAJA bukan slot terisi, jadi rekap per inspector
    // tidak punya satu baris inspector pun — hanya baris 'tidak tercatat'.
    expect($html)->not->toContain('data-inspector-id="INS-01"');
    expect(laporanProcessWaterComponentCell(
        laporanProcessWaterComponentSlice($html, 'inspector-not-recorded', '</tr>'),
        2,
    ))->toBe('1');
});

// =====================================================================
// Dua rekap teks, dan NOL blok sebaran
// =====================================================================

it('skenario 11 — catatan tindakan dirender sebagai DAFTAR TEKS, dan NOL elemen sebaran di DOM', function () {
    $record = laporanProcessWaterComponentRecord($this->stationA, $this->day1);

    foreach ([
        'Dosis alum dinaikkan menjadi 12 kg/jam',
        'Backwash softener dijalankan',
        'Lapor ke supervisor, menunggu arahan',
    ] as $index => $text) {
        laporanProcessWaterComponentSlot($record, $this->slots[$index], ['action_taken_status' => $text]);
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanProcessWater::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // SATU ENTRI PER SLOT, masing-masing dengan tanggal dan slot waktunya.
    expect(substr_count($html, 'data-testid="action-taken-row"'))->toBe(3);

    $section = laporanProcessWaterComponentSlice($html, 'action-taken-entries', '</section>');

    expect($section)->toContain('Dosis alum dinaikkan menjadi 12 kg/jam');
    expect($section)->toContain(laporanProcessWaterComponentTanggal($this->day1));
    expect($section)->toContain('07:00');

    // NOL ELEMEN SEBARAN KATEGORIS — diasersi atas TESTID, ATRIBUT, dan
    // JUDUL. Bukan atas substring 'sebaran': keterangan di bawah MENYATAKAN
    // mengapa kolom ini bukan sebaran, dan pernyataannya memuat kata itu.
    foreach ([
        'status-distribution', 'status-distribution-action-taken', 'status-row',
        'status-not-recorded', 'status-recorded-total', 'status-distribution-note',
        'status-distribution-table', 'action-taken-distribution',
    ] as $testid) {
        $this->assertStringNotContainsString('data-testid="'.$testid.'"', $html, "elemen sebaran {$testid} dirender");
    }

    expect($html)->not->toContain('data-status-value=');
    expect($html)->not->toContain('<h3>Sebaran');
    // NOL tabel bertajuk Nilai / Jumlah / Porsi — kepala tabel sebaran di
    // laporan stasiun lain.
    expect($html)->not->toContain('<th scope="col">Nilai</th>');
    expect($html)->not->toContain('<th scope="col">Jumlah slot</th>');

    // Dan keterangan MENGAPA kolom ini diperlakukan sebagai teks dirender.
    $note = laporanProcessWaterComponentSlice($html, 'action-taken-is-text-note', '</div>');

    expect($note)->not->toBe('');
    expect($note)->toContain('teks bebas tanpa himpunan');
    expect($note)->toContain('Action Taken/Status');
});

it('skenario 12 — isi catatan tindakan berulang sama persis: DUA PULUH baris, bukan satu berangka', function () {
    $record = laporanProcessWaterComponentRecord($this->stationA, $this->day1);

    foreach (array_slice($this->slots, 0, 20) as $slot) {
        laporanProcessWaterComponentSlot($record, $slot, ['action_taken_status' => 'Sudah ditangani']);
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanProcessWater::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect(substr_count($html, 'data-testid="action-taken-row"'))->toBe(20);
    expect(substr_count($html, 'Sudah ditangani'))->toBeGreaterThanOrEqual(20);

    // Masing-masing membawa slot waktunya sendiri — itu yang membedakannya
    // dari satu baris berangka dua puluh.
    $section = laporanProcessWaterComponentSlice($html, 'action-taken-entries', '</section>');

    foreach (array_slice($this->slots, 0, 20) as $slot) {
        $this->assertStringContainsString($slot, $section, "slot {$slot} tidak dirender sebagai barisnya sendiri");
    }
});

it('skenario 13 — kedua rekap teks kosong: keterangan masing-masing, bukan satu keterangan gabungan', function () {
    $record = laporanProcessWaterComponentRecord($this->stationA, $this->day1);
    laporanProcessWaterComponentSlot($record, $this->slots[0], ['raw_water_flow_m3h' => 120.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanProcessWater::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // KEDUA bagian tetap berjudul TERPISAH …
    expect($html)->toContain('data-testid="action-taken-entries"');
    expect($html)->toContain('data-testid="findings"');
    // … dan masing-masing menampilkan keterangannya sendiri.
    expect($html)->toContain('data-testid="action-taken-empty"');
    expect($html)->toContain('data-testid="findings-empty"');
    expect($html)->not->toContain('data-testid="action-taken-row"');
    expect($html)->not->toContain('data-testid="finding-row"');

    expect(laporanProcessWaterComponentSlice($html, 'action-taken-empty', '</p>'))
        ->toContain('Tidak ada satu pun catatan tindakan');
    expect(laporanProcessWaterComponentSlice($html, 'findings-empty', '</p>'))
        ->toContain('Tidak ada satu pun temuan');
});

it('skenario 14 — satu slot mengisi kedua kolom teks: dirender SEKALI di tiap rekap', function () {
    $record = laporanProcessWaterComponentRecord($this->stationA, $this->day1);

    laporanProcessWaterComponentSlot($record, '07:00', [
        'action_taken_status' => 'Backwash softener',
        'findings' => 'Kesadahan keluaran naik',
    ]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanProcessWater::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect(substr_count($html, 'data-testid="action-taken-row"'))->toBe(1);
    expect(substr_count($html, 'data-testid="finding-row"'))->toBe(1);

    expect(laporanProcessWaterComponentSlice($html, 'action-taken-entries', '</section>'))
        ->toContain('Backwash softener');
    expect(laporanProcessWaterComponentSlice($html, 'findings', '</section>'))
        ->toContain('Kesadahan keluaran naik');

    // NOL daftar gabungan di DOM.
    foreach (['text-entries-combined', 'notes', 'all-texts', 'remarks'] as $testid) {
        $this->assertStringNotContainsString('data-testid="'.$testid.'"', $html);
    }

    // Jumlah slot terisi tidak bertambah dua, dan itu dinyatakan sebagai
    // bukan penghitungan ganda.
    expect(laporanProcessWaterComponentSlice($html, 'coverage-slots', '</span>'))->toContain('<strong>1</strong>');
    expect(laporanProcessWaterComponentSlice($html, 'findings-note', '</div>'))
        ->toContain('bukan penghitungan ganda');
});

// =====================================================================
// Rekap per inspector — DI LAYAR, dengan kode MENTAH
// =====================================================================

it('skenario 15 — seluruh slot tidak mencatat inspector: baris tidak tercatat memuat semuanya', function () {
    $record = laporanProcessWaterComponentRecord($this->stationA, $this->day1);

    foreach (array_slice($this->slots, 0, 18) as $index => $slot) {
        laporanProcessWaterComponentSlot($record, $slot, ['raw_water_flow_m3h' => 100.0 + $index]);
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanProcessWater::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Rekapnya TETAP dirender sehingga layar dapat MENYATAKAN keadaannya.
    expect($html)->toContain('data-testid="inspector-recap-table"');
    expect($html)->not->toContain('data-testid="inspector-row"');

    $notRecorded = laporanProcessWaterComponentSlice($html, 'inspector-not-recorded', '</tr>');

    expect(laporanProcessWaterComponentCell($notRecorded, 1))->toBe('tidak tercatat');
    expect(laporanProcessWaterComponentCell($notRecorded, 2))->toBe('18');
    expect(laporanProcessWaterComponentCell($notRecorded, 3))->toBe('100,0%');

    // Dan keterangan MAKSUD rekapnya tetap terlihat: ia ada untuk menelusuri
    // slot yang BELUM terisi, BUKAN untuk menilai orang.
    $purpose = laporanProcessWaterComponentSlice($html, 'inspector-recap-purpose-note', '</div>');

    expect($purpose)->not->toBe('');
    expect($purpose)->toContain('menelusuri slot yang BELUM terisi');
    expect($purpose)->toMatch('/bukan untuk\s+menilai orang/');
    expect($purpose)->toContain('<b>jumlah slot terisi</b> sebagai');
});

it('skenario 16 — satu inspector mengisi seluruh periode: satu baris, kode MENTAH, tanpa penanda', function () {
    $record = laporanProcessWaterComponentRecord($this->stationA, $this->day1);

    foreach (array_slice($this->slots, 0, 6) as $index => $slot) {
        laporanProcessWaterComponentSlot($record, $slot, [
            'inspector_id' => '1180734',
            'raw_water_flow_m3h' => 100.0 + $index,
        ]);
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanProcessWater::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect(substr_count($html, 'data-testid="inspector-row"'))->toBe(1);

    // Diasersi PADA BARISNYA, lewat data-inspector-id: kode inspector di
    // lapangan sependek '1180734', dan angka telanjang cocok di mana saja
    // pada halaman ini — termasuk di dalam porsi persennya sendiri.
    $row = laporanProcessWaterComponentInspectorRow($html, '1180734');

    expect($row)->not->toBe('');
    // Kode MENTAH apa adanya, di dalam <code> — tanpa nama, dan tanpa
    // percobaan memulangkannya.
    expect(laporanProcessWaterComponentCell($row, 1))->toBe('1180734');
    expect(laporanProcessWaterComponentCell($row, 2))->toBe('6');
    expect(laporanProcessWaterComponentCell($row, 3))->toBe('100,0%');

    // 'tidak tercatat' TETAP barisnya sendiri, bernilai 0 — bukan baris yang
    // dihilangkan.
    expect(laporanProcessWaterComponentCell(
        laporanProcessWaterComponentSlice($html, 'inspector-not-recorded', '</tr>'),
        2,
    ))->toBe('0');

    // Penyebut porsinya SLOT TERCATAT, dan baris totalnya menyatakannya.
    expect(laporanProcessWaterComponentCell(
        laporanProcessWaterComponentSlice($html, 'inspector-recorded-total', '</tr>'),
        2,
    ))->toBe('6');

    // NOL penanda: satu inspector mengisi seluruh periode adalah keadaan SAH.
    foreach (['is-danger', 'is-warning', 'md-chip--danger', 'md-badge--danger'] as $className) {
        $this->assertStringNotContainsString($className, $html, "kelas penanda {$className} dipakai");
    }

    // Dan keterangan bahwa kodenya mentah tanpa relasi ke users dirender.
    expect(laporanProcessWaterComponentSlice($html, 'inspector-recap-raw-code-note', '</div>'))
        ->toContain('bukan relasi ke tabel');
});

it('skenario 17 — beberapa unit Process Water: satu baris per unit, unit tanpa pembacaan tetap dirender', function () {
    $satu = laporanProcessWaterComponentRecord($this->stationA, $this->day1, 'PW-1');
    laporanProcessWaterComponentSlot($satu, '07:00', ['raw_water_flow_m3h' => 100.0]);
    laporanProcessWaterComponentSlot($satu, '08:00', ['raw_water_flow_m3h' => 140.0]);

    $dua = laporanProcessWaterComponentRecord($this->stationA, $this->day1, 'PW-2');
    laporanProcessWaterComponentSlot($dua, '07:00', ['shift' => 'Shift 1']);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanProcessWater::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect(substr_count($html, 'data-testid="by-unit-row"'))->toBe(2);

    $table = laporanProcessWaterComponentSlice($html, 'by-unit-table', '</table>');

    expect($table)->toContain('PW-1');
    // Unit TANPA pembacaan tetap muncul — membuangnya akan menyembunyikan
    // tepat unit yang tidak pernah ditulis.
    expect($table)->toContain('PW-2');

    // 12 kolom: unit + hari + slot + 9 rata-rata.
    expect(preg_match_all('#<th scope="col">#', $table))->toBe(12);

    // Unit tanpa pembacaan ikut menambah unit_count pada hero dan pada rumus
    // kelengkapan.
    expect(laporanProcessWaterComponentSlice($html, 'hero-unit-count', '</span>'))
        ->toContain('2 unit Process Water');
    expect(laporanProcessWaterComponentSlice($html, 'coverage-formula', '</p>'))
        ->toContain('2 unit');
});

// =====================================================================
// Kelengkapan: urutan DOM, periode berjalan, slot harapan nol
// =====================================================================

it('skenario 18 — pencatatan sangat tidak lengkap: cakupan DI ATAS kartu metrik, persennya kecil', function () {
    $record = laporanProcessWaterComponentRecord($this->stationA, $this->day1);
    laporanProcessWaterComponentSlot($record, '07:00', ['raw_water_flow_m3h' => 120.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanProcessWater::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Elemennya muncul SEBELUM kartu metrik pertama di urutan DOM. Posisi di
    // viewport diukur spec browser; di sini yang diasersi urutan sumbernya.
    expect(strpos($html, 'data-testid="coverage"'))->toBeLessThan(strpos($html, 'data-testid="metric-card"'));

    // Persennya dibaca dari SEL-nya sendiri — keterangan cakupan dan rekap
    // per inspector memuat banyak persentase lain.
    expect(laporanProcessWaterComponentSlice($html, 'coverage-percent', '</span>'))->toContain('0,4%');
    expect(laporanProcessWaterComponentSlice($html, 'coverage-slots', '</span>'))->toContain('dari 240 slot');

    // Angka metrik TETAP dirender dengan penyebut kecilnya.
    expect(laporanProcessWaterComponentCard($html, 'raw_water_flow_m3h'))->toContain('dari <b>1</b> pembacaan');
});

it('skenario 19 — periode masih berjalan: keterangannya menyebut HARI TERAKHIR YANG TERHITUNG', function () {
    $running = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::ProcessWater->value)
        ->range(now()->subDays(9)->toDateString(), now()->addDays(20)->toDateString())
        ->open()
        ->named('Periode Berjalan')
        ->create();

    laporanProcessWaterComponentSlot(
        laporanProcessWaterComponentRecord($this->stationA, now()->subDays(8)->toDateString(), 'PW-1'),
        '07:00',
        ['raw_water_flow_m3h' => 120.0],
    );

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanProcessWater::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $running->id)
        ->html();

    $note = laporanProcessWaterComponentSlice($html, 'coverage-running-note', '</p>');

    expect($note)->not->toBe('');
    // MENYEBUT HARI INI, bukan tanggal akhir periode: tanggal akhir yang
    // belum tiba akan terbaca seperti hari yang sudah ikut dihitung.
    expect($note)->toContain(laporanProcessWaterComponentTanggal(now()->toDateString()));
    expect($note)->not->toContain(laporanProcessWaterComponentTanggal(now()->addDays(20)->toDateString()));
    expect($note)->toContain('10 dari');
    expect($note)->toContain('30 hari periode');

    // Dan rumusnya memakai 10 hari, bukan 30.
    expect(laporanProcessWaterComponentSlice($html, 'coverage-formula', '</p>'))
        ->toContain('10 hari (sampai hari ini)');
});

it('skenario 20 — tidak ada slot yang diharapkan: persennya TANDA PISAH, bukan 0,0%', function () {
    $future = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::ProcessWater->value)
        ->range(now()->addDays(40)->toDateString(), now()->addDays(50)->toDateString())
        ->open()
        ->named('Periode Belum Mulai')
        ->create();

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanProcessWater::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $future->id)
        ->html();

    // DIASERSI PADA SEL CAKUPANNYA, tidak pernah atas halaman: keterangan
    // cakupan memuat banyak persentase lain, dan '0,0%' sebagai substring
    // halaman tidak membuktikan apa pun tentang sel ini.
    $percent = laporanProcessWaterComponentSlice($html, 'coverage-percent', '</span>');

    expect($percent)->not->toBe('');
    expect($percent)->toContain('&mdash;');
    expect($percent)->not->toContain('0,0%');
    expect($percent)->not->toContain('%');

    expect(laporanProcessWaterComponentSlice($html, 'coverage-no-expected-note', '</p>'))
        ->toContain('Belum ada slot yang diharapkan');
});

it('skenario 21 — nilai pH dan TDS jauh di luar batas: NOL kelas penanda, keterangannya UTUH', function () {
    $record = laporanProcessWaterComponentRecord($this->stationA, $this->day1);

    laporanProcessWaterComponentSlot($record, $this->slots[0], [
        'boiler_feed_water_ph' => 4.2,
        'boiler_feed_tds_ppm' => 6000.0,
    ]);
    laporanProcessWaterComponentSlot($record, $this->slots[1], [
        'boiler_feed_water_ph' => 11.8,
        'boiler_feed_tds_ppm' => 12.0,
    ]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanProcessWater::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // DISISIR ATAS NAMA KELAS, bukan atas frasa: keterangan yang menyatakan
    // ketiadaan penandaan itu sendiri memuat frasa "di luar batas", jadi
    // penyisiran teks justru gagal pada kalimat yang membuktikan klaimnya.
    //
    // DAN `.md-threshold` ADALAH BAGIAN PENTING DARI DAFTAR INI: kotak
    // keterangan layar ini memakai `.md-explain`, yang tampil identik dan
    // hanya berbeda namanya, justru supaya asersi ini dapat ditulis menurut
    // nama kelas.
    foreach ([
        'md-threshold', 'is-danger', 'is-warning', 'is-critical', 'is-breach',
        'md-chip--danger', 'md-badge--danger', 'md-kpi--danger',
        'md-trendchart__bar--high', 'md-trendchart__bar--low',
    ] as $className) {
        $this->assertStringNotContainsString($className, $html, "kelas penanda {$className} dipakai");
    }

    expect($html)->toContain('md-explain');

    // Nilainya dirender apa adanya, dengan gaya netral yang sama.
    expect(laporanProcessWaterComponentSlice(
        laporanProcessWaterComponentCard($html, 'boiler_feed_water_ph'),
        'metric-range',
        '</p>',
    ))->toContain('4,20');

    expect(laporanProcessWaterComponentSlice(
        laporanProcessWaterComponentCard($html, 'boiler_feed_tds_ppm'),
        'metric-range',
        '</p>',
    ))->toContain('6.000');

    // Dan ketiadaannya DINYATAKAN UTUH — ketiadaan penandaan yang tidak
    // dijelaskan terbaca sebagai fitur yang belum selesai, dan seseorang akan
    // "melengkapinya". Di layar inilah godaan itu paling besar.
    $note = laporanProcessWaterComponentSlice($html, 'no-threshold-note', '</div>');

    expect($note)->toContain('tidak punya tabel master target');
    // Frasanya terpotong baris pada blade, jadi dicocokkan dengan regex yang
    // menoleransi spasi — bukan dengan substring yang akan gagal karena
    // pemformatan sumbernya, bukan karena isinya.
    expect($note)->toMatch('/batas\s+keselamatan\s+boiler/');
    expect($note)->toMatch('/turunan\s+statistik/');

    // Dan keterangan itu dirender DI DALAM blok Air Umpan Boiler, bukan
    // sebagai catatan kaki halaman — diasersi sebagai KETURUNAN elemennya.
    expect(laporanProcessWaterComponentSlice($html, 'metric-block-boiler-feed', '</section>'))
        ->toContain('data-testid="no-threshold-note"');
});

// =====================================================================
// Akses
// =====================================================================

it('skenario 22 — akun terikat mill dengan business_unit_id null: gagal tertutup, TANPA pemilih mill', function () {
    $supervisorNoMill = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $html = Livewire::actingAs($supervisorNoMill)
        ->test(LaporanProcessWater::class)
        ->html();

    expect($html)->toContain('data-testid="no-mill-hint"');
    expect(laporanProcessWaterComponentSlice($html, 'no-mill-hint', '</div>'))->toContain('Hubungi Admin');
    // GAGAL TERTUTUP: pemilih Mill TIDAK dirender sebagai gantinya, sehingga
    // daftar seluruh mill tidak pernah ditawarkan.
    expect($html)->not->toContain('data-testid="mill-select"');
    expect($html)->not->toContain('Mill Alpha');
    expect($html)->not->toContain('Mill Beta');
    expect($html)->not->toContain('data-testid="coverage"');
    expect($html)->not->toContain('data-testid="metric-card"');
});

it('skenario 23 — id mill milik mill lain lewat properti: DIABAIKAN, tetap mill sendiri', function () {
    $recordA = laporanProcessWaterComponentRecord($this->stationA, $this->day1, 'PW-A');
    laporanProcessWaterComponentSlot($recordA, '07:00', ['raw_water_flow_m3h' => 120.0]);

    $recordB = laporanProcessWaterComponentRecord($this->stationB, $this->day1, 'PW-B');
    laporanProcessWaterComponentSlot($recordB, '07:00', ['raw_water_flow_m3h' => 9999.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanProcessWater::class)
        ->set('businessUnitId', (string) $this->businessUnitB->id)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Angka yang dirender tetap milik mill A, dan nama mill pada hero tetap
    // mill A.
    expect(laporanProcessWaterComponentSlice($html, 'report-hero', '</section>'))->toContain('Mill Alpha');
    expect(laporanProcessWaterComponentSlice($html, 'report-hero', '</section>'))->not->toContain('Mill Beta');
    expect(laporanProcessWaterComponentSlice($html, 'by-unit-table', '</table>'))->toContain('PW-A');
    expect(laporanProcessWaterComponentSlice($html, 'by-unit-table', '</table>'))->not->toContain('PW-B');
});

it('skenario 24 — id line milik mill lain lewat properti: jatuh ke BELUM MEMILIH, bukan data mill lain', function () {
    laporanProcessWaterComponentSlot(
        laporanProcessWaterComponentRecord($this->stationB, $this->day1, 'PW-B'),
        '07:00',
        ['raw_water_flow_m3h' => 9999.0],
    );

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanProcessWater::class)
        ->set('productionLineId', $this->lineB)
        ->html();

    // Di layar, "belum memilih line" adalah keadaan awal yang normal dan
    // harus merender ajakan memilih — bukan halaman galat; dan nol angka
    // milik line mill lain yang terbit.
    expect($html)->toContain('data-testid="select-production-line-hint"');
    expect($html)->not->toContain('data-testid="coverage"');
    expect($html)->not->toContain('data-testid="metric-card"');
    expect($html)->not->toContain('PW-B');
});

it('skenario 24b — periode milik mill lain lewat properti: penolakan TERLIHAT, tanpa angka mill lain', function () {
    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::ProcessWater->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Process Water Beta')
        ->create();

    laporanProcessWaterComponentSlot(
        laporanProcessWaterComponentRecord($this->stationB, $this->day1, 'PW-B'),
        '07:00',
        ['raw_water_flow_m3h' => 9999.0],
    );

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanProcessWater::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $periodB->id)
        ->html();

    // Ditolak SECARA TERLIHAT: penukaran diam-diam akan menjawab probe
    // lintas mill dengan angka mill LAIN di bawah id yang diminta.
    expect($html)->toContain('data-testid="forbidden-notice"');
    expect($html)->not->toContain('data-testid="coverage"');
    expect($html)->not->toContain('data-testid="metric-card"');
    expect($html)->not->toContain('PW-B');
});

it('skenario 25 / case 50 — Operator ditolak pada RUTE WEB dan pada mount() komponennya', function () {
    // Component test TIDAK melewati middleware, jadi hanya baris ini yang
    // membuktikan penjagaan rutenya ('role:supervisor,mill_management,admin')
    // — dan rute WEB ini TIDAK dilebarkan walaupun ketiga rute API datanya
    // memang menerima Operator.
    $response = $this->actingAs($this->operator, 'web')->get('/reports/process-water');

    $response->assertStatus(403);
    $response->assertDontSee('Laporan Periode Process Water');

    // Dan peran yang memang dibuka benar-benar sampai ke halamannya — tanpa
    // ini, 403 di atas akan tetap hijau pada rute yang menolak semua orang.
    $this->actingAs($this->supervisor, 'web')
        ->get('/reports/process-water')
        ->assertOk()
        ->assertSee('data-testid="laporan-process-water"', false);

    // Lapisan komponen: mount() melakukan abort_unless atas daftar perannya
    // SENDIRI (supervisor|mill_management|admin), dan penjagaan ini TIDAK
    // memanggil ProcessWaterReportService::guardAccess() yang memang menerima
    // operator.
    $html = Livewire::actingAs($this->operator)->test(LaporanProcessWater::class)->html();

    expect($html)->toContain('Forbidden');
    expect($html)->not->toContain('data-testid="laporan-process-water"');
    expect($html)->not->toContain('data-testid="coverage"');
    expect($html)->not->toContain('data-testid="metric-card"');
});

it('case 50b — canAccess() memegang daftar perannya SENDIRI dan tidak mendelegasikan ke guardAccess()', function () {
    // BUKAN BAHAYA MASA DEPAN: ProcessWaterReportService::guardAccess()
    // MENERIMA Operator HARI INI, karena kembaran mobile screen-161 dibangun
    // dalam seri yang sama. Kalau canAccess() mendelegasikan ke sana, layar
    // web ini terbuka untuk Operator SEKARANG — tanpa satu galat pun dan
    // tanpa apa pun di layar yang menandainya.
    //
    // Diasersi SECARA STRUKTURAL atas badan metodenya, karena asersi perilaku
    // di atas (skenario 25) baru gagal SETELAH delegasi terjadi — yaitu
    // sesudah kebocorannya mendarat.
    $method = new ReflectionMethod(LaporanProcessWater::class, 'canAccess');
    $source = implode('', array_slice(
        file($method->getFileName()),
        $method->getStartLine() - 1,
        $method->getEndLine() - $method->getStartLine() + 1,
    ));

    expect($source)->not->toContain('guardAccess');
    expect($source)->not->toContain('ProcessWaterReportService');
    // Ketiga peran disebut di tempat, dan Operator tidak disebut sama sekali.
    expect($source)->toContain('UserRole::Supervisor->value');
    expect($source)->toContain('UserRole::MillManagement->value');
    expect($source)->toContain('UserRole::Admin->value');
    expect($source)->not->toContain('UserRole::Operator');

    // Dan service-nya MEMANG menerima Operator — tanpa baris ini, asersi di
    // atas bisa hijau pada service yang juga menutup Operator, yang berarti
    // ia tidak lagi menjaga apa pun.
    expect(ProcessWaterReportService::NUMERIC_METRICS)->toHaveCount(9);
    $this->actingAs($this->operator, 'web')
        ->getJson('/api/process-water-reports/periods')
        ->assertOk();
});

// =====================================================================
// Periode tertutup, shift, rekap harian, baca-saja, ekspor
// =====================================================================

it('skenario 26 — periode tertutup: laporan penuh, badge Tertutup, kedua tombol ekspor tetap ada', function () {
    $closed = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::ProcessWater->value)
        ->range(now()->subDays(40)->toDateString(), now()->subDays(31)->toDateString())
        ->closed($this->admin)
        ->named('Periode Tertutup')
        ->create();

    laporanProcessWaterComponentSlot(
        laporanProcessWaterComponentRecord($this->stationA, now()->subDays(35)->toDateString(), 'PW-TUTUP'),
        '07:00',
        ['raw_water_flow_m3h' => 120.0],
    );

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanProcessWater::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $closed->id);

    $html = $component->html();

    // Seluruh bagian laporan dirender penuh.
    foreach (LAPORAN_PROCESS_WATER_FIGURE_TESTIDS as $testid) {
        $this->assertStringContainsString('data-testid="'.$testid.'"', $html, "testid {$testid} tidak dirender");
    }

    // Badge status pada ELEMENNYA SENDIRI: 'Tertutup' sebagai substring
    // halaman tidak membuktikan apa pun — layar ini menyebut kata itu di
    // keterangan pemilih periode juga.
    expect(laporanProcessWaterComponentSlice($html, 'period-status-badge', '</span>'))->toContain('Tertutup');

    // Kedua tombol ekspor tetap dirender dan TIDAK dinonaktifkan.
    expect($html)->toContain('data-testid="export-button"');
    expect($html)->toContain('data-testid="export-excel-button"');

    $component->call('exportCsv', 'csv')->assertFileDownloaded();
    $component->call('exportCsv', 'excel')->assertFileDownloaded();
});

it('skenario 27 — kolom shift TIDAK diterbitkan di layar, dan SEBABNYA dinyatakan', function () {
    $record = laporanProcessWaterComponentRecord($this->stationA, $this->day1);

    // `shift` TERISI di basis data, justru supaya ketiadaannya di layar tidak
    // lolos hanya karena kolomnya kosong.
    laporanProcessWaterComponentSlot($record, '07:00', [
        'shift' => 'Shift Malam',
        'inspector_id' => 'INS-01',
        'raw_water_flow_m3h' => 120.0,
    ]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanProcessWater::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // NOL kolom shift di DOM — diasersi atas TESTID, ATRIBUT, dan KEPALA
    // TABEL, bukan atas substring 'Shift': label pendek itu dieja di dalam
    // ketiga keterangan panjang layar ini, jadi asersi substring akan hijau
    // tanpa membuktikan apa pun.
    foreach ([
        'shift-distribution', 'shift-row', 'by-shift', 'shift-recap',
        'shift-table', 'shift-not-recorded',
    ] as $testid) {
        $this->assertStringNotContainsString('data-testid="'.$testid.'"', $html, "elemen shift {$testid} dirender");
    }

    expect($html)->not->toContain('<th scope="col">Shift</th>');
    expect($html)->not->toContain('data-shift=');
    expect($html)->not->toContain('data-metric="shift"');
    // Dan NILAI kolomnya tidak pernah terbit di layar.
    expect($html)->not->toContain('Shift Malam');

    // KETERANGAN SEBABNYA dirender: kosong di seluruh baris, dan dijadwalkan
    // diganti relasi ke master Shift. Tanpa ini, ketiadaannya terbaca sebagai
    // kelalaian.
    $note = laporanProcessWaterComponentSlice($html, 'no-shift-note', '</div>');

    expect($note)->not->toBe('');
    expect($note)->toContain('<code>shift</code>');
    expect($note)->toContain('kosong di seluruh');
    expect($note)->toMatch('/master\s+Shift/');
    // Dan ia menyatakan bahwa kolomnya TETAP ADA di ekspor.
    expect($note)->toMatch('/ikut\s+di\s+berkas\s+ekspor/');

    // Yang DI EKSPOR memang membawanya — dibuktikan atas header ekspor
    // service yang SAMA dengan yang dipakai tombol di layar ini.
    expect(ProcessWaterReportService::EXPORT_HEADER)->toContain('Shift');
    expect(ProcessWaterRecordService::DETAIL_FIELDS)->toContain('shift');
});

it('skenario 28 — pembacaan di tanggal mulai dan tanggal akhir muncul di rekap harian', function () {
    $awal = laporanProcessWaterComponentRecord($this->stationA, $this->periodStart, 'PW-AWAL');
    laporanProcessWaterComponentSlot($awal, '07:00', ['raw_water_flow_m3h' => 100.0]);

    $akhir = laporanProcessWaterComponentRecord($this->stationA, $this->periodEnd, 'PW-AKHIR');
    laporanProcessWaterComponentSlot($akhir, '07:00', ['raw_water_flow_m3h' => 200.0]);

    $luar = laporanProcessWaterComponentRecord($this->stationA, $this->dayAfterPeriod, 'PW-LUAR');
    laporanProcessWaterComponentSlot($luar, '07:00', ['raw_water_flow_m3h' => 8888.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanProcessWater::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Rekap harian memuat baris untuk KEDUA tanggal ujung — diasersi atas
    // data-testid baris tanggalnya, bukan atas teks tanggal di halaman.
    expect($html)->toContain('data-testid="daily-row-'.$this->periodStart.'"');
    expect($html)->toContain('data-testid="daily-row-'.$this->periodEnd.'"');
    expect($html)->not->toContain('data-testid="daily-row-'.$this->dayAfterPeriod.'"');

    // DICACAH ATAS BARIS BERTANGGAL SAJA: awalan 'daily-row-' juga cocok
    // dengan 'daily-row-total', jadi substr_count atas awalannya akan
    // menghitung baris total sebagai tanggal ketiga.
    expect(preg_match_all('#data-testid="daily-row-\d{4}-\d{2}-\d{2}"#', $html))->toBe(2);

    // Record di luar rentang tidak muncul di mana pun.
    expect($html)->not->toContain('PW-LUAR');

    // Dan penyebut kartunya mencakup kedua record ujung.
    expect(laporanProcessWaterComponentCard($html, 'raw_water_flow_m3h'))->toContain('dari <b>2</b> pembacaan');
});

it('skenario 29 — tiap kartu mencetak penyebutnya SENDIRI, berdampingan dengan angkanya', function () {
    $record = laporanProcessWaterComponentRecord($this->stationA, $this->day1);

    // Kolom ALIRAN AIR diisi pada 4 slot; softener dan air umpan boiler
    // DIKOSONGKAN sama sekali.
    foreach (array_slice($this->slots, 0, 4) as $index => $slot) {
        laporanProcessWaterComponentSlot($record, $slot, [
            'raw_water_flow_m3h' => 100.0 + $index,
            'clarified_water_flow_m3h' => $index < 2 ? 90.0 + $index : null,
            'alum_dosing_kgh' => $index < 1 ? 12.0 : null,
        ]);
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanProcessWater::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    $denominators = [];

    foreach (ProcessWaterReportService::NUMERIC_METRICS as $column) {
        $card = laporanProcessWaterComponentCard($html, $column);

        expect($card)->not->toBe('');
        // PENYEBUTNYA DICETAK DI KARTU ITU SENDIRI, berdampingan dengan
        // angkanya.
        expect($card)->toContain('data-testid="metric-denominator"');
        expect($card)->toContain('data-testid="metric-avg"');

        expect(preg_match('#dari <b>([\d.]+)</b> pembacaan#', $card, $m))->toBe(1);

        $denominators[$column] = (int) str_replace('.', '', $m[1]);
    }

    // HIMPUNAN PENYEBUT MEMUAT LEBIH DARI SATU NILAI — penyebut identik pada
    // sembilan kartu adalah cacat yang dicari.
    expect(array_values(array_unique(array_values($denominators))))->toHaveCount(4);
    expect($denominators['raw_water_flow_m3h'])->toBe(4);
    expect($denominators['clarified_water_flow_m3h'])->toBe(2);
    expect($denominators['alum_dosing_kgh'])->toBe(1);
    expect($denominators['softener_inlet_ph'])->toBe(0);

    // Dan kartu yang jarang diisi TIDAK mengempis ke arah nol: rata-ratanya
    // rata-rata baris non-null MILIKNYA SENDIRI. Dihitung di PHP dari
    // fixture, bukan lewat SQL.
    expect(laporanProcessWaterComponentSlice(
        laporanProcessWaterComponentCard($html, 'clarified_water_flow_m3h'),
        'metric-avg',
        '</span>',
    ))->toContain(number_format((90.0 + 91.0) / 2, 1, ',', '.'));
});

it('skenario 30 — ekspor CSV dan Excel mengalirkan berkas; keduanya terkunci selama penyiapan', function () {
    $record = laporanProcessWaterComponentRecord($this->stationA, $this->day1, 'PW-9');
    laporanProcessWaterComponentSlot($record, '07:00', [
        'shift' => 'Shift 1',
        'inspector_id' => 'INS-01',
        'raw_water_flow_m3h' => 120.0,
    ]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanProcessWater::class)
        ->set('productionLineId', $this->lineA);

    $component->call('exportCsv', 'csv')->assertFileDownloaded();
    $component->call('exportCsv', 'excel')->assertFileDownloaded();

    $html = $component->html();

    // Kedua tombol dikunci sesaat selama penyiapan — klik ganda tidak
    // menghasilkan dua unduhan.
    $filters = laporanProcessWaterComponentSlice($html, 'report-filters', '</section>');

    expect($filters)->toContain('wire:loading.attr="disabled"');
    expect($filters)->toContain('wire:target="exportCsv"');
});

it('skenario 31 — tidak ada bagian downtime: diasersi atas TESTID dan JUDUL, bukan atas substring', function () {
    $record = laporanProcessWaterComponentRecord($this->stationA, $this->day1);
    laporanProcessWaterComponentSlot($record, '07:00', array_fill_keys(
        ProcessWaterReportService::NUMERIC_METRICS,
        7.5,
    ));

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanProcessWater::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // DIASERSI ATAS TESTID DAN JUDUL, BUKAN atas substring 'downtime':
    // laporan ini MENYATAKAN ketiadaan bagian itu, dan pernyataannya sendiri
    // memakai data-testid="no-downtime-note".
    foreach (['downtime', 'downtime-total', 'downtime-empty', 'downtime-table', 'downtime-row'] as $testid) {
        $this->assertStringNotContainsString('data-testid="'.$testid.'"', $html);
    }

    expect($html)->not->toContain('<h3>Downtime');
    expect($html)->not->toContain('<h3>Lama Berhenti');
    expect($html)->not->toContain('Rekap Downtime');

    // Ketiadaannya DINYATAKAN — bagian yang hilang tanpa keterangan tidak
    // dapat dibedakan dari bagian yang gagal dimuat, dan laporan stasiun lain
    // memilikinya sehingga pembaca yang datang dari sana akan mencarinya.
    expect(laporanProcessWaterComponentSlice($html, 'no-downtime-note', '</div>'))
        ->toContain('tidak mencatat lama berhenti sama sekali');

    // Dan skemanya memang tidak punya kolomnya — tanpa baris ini, asersi di
    // atas hanya mengukur blade, bukan kenyataan yang mendasarinya.
    foreach (ProcessWaterRecordService::DETAIL_FIELDS as $field) {
        expect($field)->not->toContain('downtime');
    }
});

it('skenario 32 — rekap harian 31 hari: baris per tanggal + baris total, gulir mendatar di dalam kartunya', function () {
    $long = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::ProcessWater->value)
        ->range(now()->subDays(60)->toDateString(), now()->subDays(30)->toDateString())
        ->open()
        ->named('Periode Tiga Puluh Satu Hari')
        ->create();

    foreach (range(0, 30) as $offset) {
        $date = now()->subDays(60 - $offset)->toDateString();

        laporanProcessWaterComponentSlot(
            laporanProcessWaterComponentRecord($this->stationA, $date, 'PW-1'),
            '07:00',
            ['raw_water_flow_m3h' => 100.0 + $offset],
        );
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanProcessWater::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $long->id);

    $html = $component->html();

    // 31 baris tanggal PLUS satu baris total periode.
    expect(preg_match_all('#data-testid="daily-row-\d{4}-\d{2}-\d{2}"#', $html))->toBe(31);
    expect(substr_count($html, 'data-testid="daily-row-total"'))->toBe(1);

    $table = laporanProcessWaterComponentSlice($html, 'daily-table', '</table>');

    // 11 kolom: tanggal + slot + 9 rata-rata. Lebih sempit daripada Engine
    // Room yang 25 — tetapi tetap harus menggulir DI DALAM kartunya.
    expect(preg_match_all('#<th scope="col">#', $table))->toBe(11);

    // Wrapper gulirnya memakai kelas `.md-recap` — dan APAKAH kelas itu
    // BENAR-BENAR menggulir diperiksa pada hasil RENDER oleh spec browser,
    // bukan di sini: nama kelas pada sumber tidak membuktikan ada
    // definisinya di CSS hasil compile.
    expect($html)->toContain('<div class="md-recap">');
    expect($html)->toContain('data-testid="by-unit-scroll-hint"');

    // Dibuka/ditutup lewat <details> NATIVE: tidak satu putaran Livewire pun,
    // jadi tidak ada permintaan data baru yang mungkin terjadi.
    expect($html)->toContain('<details class="md-details" data-testid="daily-recap-details">');
    expect($html)->not->toContain('wire:click="toggleDaily"');
});

it('skenario 33 — layar hanya membaca: tidak ada form, input, maupun tombol tulis', function () {
    $record = laporanProcessWaterComponentRecord($this->stationA, $this->day1);
    laporanProcessWaterComponentSlot($record, '07:00', [
        'raw_water_flow_m3h' => 120.0,
        'inspector_id' => 'INS-01',
        'action_taken_status' => 'Sudah ditangani',
        'findings' => 'Normal',
    ]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanProcessWater::class)
        ->set('productionLineId', $this->lineA);

    $html = $component->html();

    // Nol elemen form dan nol input yang dapat disunting: ketiga pemilihnya
    // <select> ber-wire:model.
    expect($html)->not->toContain('<form');
    expect($html)->not->toContain('<input');
    expect($html)->not->toContain('<textarea');
    expect($html)->not->toContain('type="submit"');
    expect($html)->not->toContain('>Simpan<');
    expect($html)->not->toContain('>Hapus<');
    expect($html)->not->toContain('>Ubah<');
    expect($html)->not->toContain('>Verifikasi<');

    // Dan komponennya tidak mengekspos satu pun metode publik yang menulis.
    $public = collect((new ReflectionClass(LaporanProcessWater::class))->getMethods(ReflectionMethod::IS_PUBLIC))
        ->filter(fn (ReflectionMethod $method) => $method->class === LaporanProcessWater::class)
        ->map(fn (ReflectionMethod $method) => $method->name)
        ->values()
        ->all();

    expect($public)->toBe([
        'mount', 'updatingBusinessUnitId', 'updatedBusinessUnitId',
        'updatedPeriodId', 'exportCsv', 'render',
    ]);

    // Dan membacanya tidak mengubah satu baris pun.
    $before = [ProcessWaterRecord::query()->count(), ProcessWaterDetail::query()->count()];

    $component->call('exportCsv', 'csv')->assertFileDownloaded();

    expect([ProcessWaterRecord::query()->count(), ProcessWaterDetail::query()->count()])->toBe($before);
});

// =====================================================================
// PINTU MASUK DARI LAPORAN STASIUN (screen-140)
// =====================================================================

it('pintu masuk — tile Process Water pada Laporan Stasiun AKTIF dan menunjuk ke rute laporan ini', function () {
    // Tanpa entri 'process-water' pada StationReportService::REPORT_ROUTES,
    // layar ini ada, seluruh test lainnya lulus, dan tile-nya tetap kelabu —
    // pola kegagalan yang sudah pernah terjadi pada laporan Cages & Tracks
    // versi web.
    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanStasiun::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // DIPOTONG DARI TAG PEMBUKA YANG MEMBAWA TESTID-NYA SAMPAI TAG
    // PENUTUPNYA, bukan dari jendela sekitarnya: grid screen-140 merender
    // delapan belas tile berdampingan, dan sebuah jendela akan ikut memuat
    // tile tetangga yang memang masih kelabu — asersi 'tidak disabled' lalu
    // gagal karena tetangganya, bukan karena tile ini.
    $pattern = '/<(a|span)\b[^>]*data-testid="station-tile-process-water"[^>]*>.*?<\/\1>/s';

    expect(preg_match($pattern, $html, $matches))->toBe(1);

    $tile = $matches[0];

    // AKTIF, bukan kelabu, dan href-nya menunjuk ke rute laporan ini.
    expect($tile)->toContain('station-tile active');
    expect($tile)->not->toContain('station-tile disabled');
    expect($tile)->not->toContain('aria-disabled');
    expect($tile)->toContain('/reports/process-water');
    expect($tile)->not->toContain('Belum tersedia');
});
