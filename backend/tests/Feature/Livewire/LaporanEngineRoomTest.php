<?php

/**
 * LaporanEngineRoomTest (Feature/Livewire) — screen-158--laporan-engine-room-web,
 * halaman webnya sendiri.
 *
 * Satu uji per entri `test_scenarios[].component_test` pada tech spec
 * screen-158 (35 skenario), ditambah tiga unit test case yang memang
 * menyangkut komponen ini dan blade-nya (case 71, 72, 73) dan satu asersi
 * pintu masuk dari Laporan Stasiun (screen-140).
 *
 * LAPISAN INI ADA UNTUK KEADAAN YANG TIDAK BISA DIATUR DI TEMPAT LAIN.
 * Angkanya sudah dibuktikan dua kali terhadap data yang dikendalikan penuh —
 * atas service-nya di tests/Unit/Services/EngineRoomReportServiceTest.php dan
 * atas kontrak HTTP-nya di tests/Feature/Api/LaporanEngineRoomTest.php — jadi
 * berkas ini TIDAK mengulanginya. Yang dibuktikan di sini adalah apa yang
 * HALAMAN lakukan terhadap angka itu: pemilih mana yang ada untuk peran mana,
 * keadaan kosong mana yang digambar, dan hal-hal yang layar ini tidak boleh
 * biarkan terbaca salah.
 *
 * DIASERSI ATAS HTML TER-RENDER, bukan atas state komponen, di mana pun
 * klaimnya tentang apa yang pembaca lihat: sebuah data-testid yang ada di
 * markup tetapi tidak pernah dicapai oleh kondisional blade akan tetap
 * meloloskan asersi atas state.
 *
 * ────────────────────────────────────────────────────────────────────────
 * DUA HAL STRUKTURAL YANG HANYA LAPISAN INI MENJAGA
 * ────────────────────────────────────────────────────────────────────────
 *   1. canAccess() MEMEGANG DAFTAR PERANNYA SENDIRI dan TIDAK mendelegasikan
 *      ke EngineRoomReportService::guardAccess(). Service itu MENERIMA
 *      Operator SEJAK COMMIT PERTAMA (kembaran mobile screen-159 dibangun
 *      dalam seri yang sama), jadi delegasi akan membuka layar web ini untuk
 *      Operator dalam tarikan napas yang sama — tanpa satu galat pun dan
 *      tanpa apa pun di layar yang menandainya. Berbeda dari saudara-
 *      saudaranya, di sini bahayanya BUKAN di masa depan: ia sudah ada hari
 *      ini, karena service-nya memang menerima Operator sekarang. Case 71 dan
 *      skenario 25 yang menjaganya.
 *   2. KOTAK KETERANGAN MEMAKAI `.md-explain`, BUKAN `.md-threshold`.
 *      Keduanya tampil identik dan yang berbeda hanya namanya; layar ini
 *      sengaja tidak menandai satu pun nilai di luar batas, dan ketiadaan itu
 *      diasersi MENURUT NAMA KELAS pada HTML ter-render. Memakai kelas
 *      bernama 'threshold' untuk menjelaskan bahwa tidak ada ambang akan
 *      memerahkan asersi itu justru karena namanya.
 *
 * DAN SEMBILAN KEADAAN yang database e2e bersama tidak bisa menyusunnya,
 * karena setiap test di sini punya databasenya sendiri: mill tanpa satu pun
 * periode Engine Room, periode berisi record tetapi NOL slot terisi, periode
 * yang belum mulai (slot harapan nol), periode yang masih berjalan, sebuah
 * metrik yang tidak pernah diisi sepanjang periode, satu genset yang tidak
 * pernah menyala, kolom ekspor energi yang tidak pernah terisi, akun terikat
 * mill dengan business_unit_id null, dan id mill/line/periode milik mill lain
 * yang disuapkan lewat properti komponen. Spec browser screen-158 sengaja
 * tidak mencoba satu pun dari itu.
 *
 * TANGGALNYA SELALU RELATIF (now()->subDays(...)).
 */

use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Livewire\Dashboard\LaporanEngineRoom;
use App\Livewire\Dashboard\LaporanStasiun;
use App\Models\BusinessUnit;
use App\Models\EngineRoomDetail;
use App\Models\EngineRoomRecord;
use App\Models\Period;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use App\Services\EngineRoomRecordService;
use App\Services\EngineRoomReportService;
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
const LAPORAN_ENGINE_ROOM_FIGURE_TESTIDS = [
    'coverage',
    'coverage-slots',
    'coverage-percent',
    'coverage-formula',
    'coverage-note',
    // KEENAM blok metrik — pengelompokannya murni tata letak, tetapi
    // keenamnya harus benar-benar digambar.
    'metric-block-steam-turbine',
    'metric-block-diesel-gen-1',
    'metric-block-diesel-gen-2',
    'metric-block-electrical-sync',
    'metric-block-compressor',
    'metric-block-auxiliary',
    'metric-card',
    'metric-avg',
    'metric-range',
    'metric-denominator',
    // Kartu ke-23, yang TIDAK menerbitkan rata-rata periode.
    'energy-export-card',
    'energy-export-badge',
    'energy-export-last',
    'energy-export-denominator',
    // Keterangan yang menyatakan apa yang layar ini sengaja TIDAK lakukan.
    'genset-parallel-note',
    'no-threshold-note',
    'compressor-label-note',
    'auxiliary-label-note',
    // KEDUA sebaran status, masing-masing kartunya sendiri.
    'status-distribution-diesel-gen-1',
    'status-distribution-diesel-gen-2',
    'status-distribution-diesel-gen-1-table',
    'status-distribution-diesel-gen-2-table',
    'status-row',
    'status-not-recorded',
    'status-recorded-total',
    'standby-not-off-note',
    'status-distribution-note',
    'no-downtime-note',
    // DUA bagian teks bebas, bukan satu.
    'maintenance-remarks',
    'maintenance-remarks-note',
    'findings',
    'findings-note',
];

/** Testid yang baru ada ketika periodenya BENAR-BENAR punya slot terisi. */
const LAPORAN_ENGINE_ROOM_DATA_TESTIDS = [
    'daily-energy-export',
    'daily-energy-export-note',
    'daily-trend-total-factory-load',
    'aggregation-difference-note',
    'by-unit',
    'by-unit-table',
    'by-unit-scroll-hint',
    'by-unit-row',
    'by-unit-row-total',
    'daily-recap-details',
    'daily-recap-toggle',
    'daily-table',
    'daily-row-total',
    'daily-total-energy-export',
    'daily-total-energy-note',
    'completeness',
    'days-with-records',
    'total-reading-rows',
];

function laporanEngineRoomComponentRecord(
    Station $station,
    string $date,
    string $engineRoomId = 'ER-1',
    array $attributes = [],
): EngineRoomRecord {
    return EngineRoomRecord::factory()
        ->forStation($station)
        ->onDate($date)
        ->create(array_merge(['engine_room_id' => $engineRoomId, 'note' => 'Catatan harian'], $attributes));
}

function laporanEngineRoomComponentSlot(
    EngineRoomRecord $record,
    string $timeSlot,
    array $values = [],
): EngineRoomDetail {
    return EngineRoomDetail::factory()
        ->forRecord($record)
        ->timeSlot($timeSlot)
        ->create($values);
}

/**
 * Isi satu elemen yang dikenali data-testid-nya, dipotong dari HTML.
 *
 * Dipakai setiap kali klaimnya tentang SATU SEL tertentu: "tidak ada teks nol
 * persen di mana pun pada halaman" akan selalu gagal kalau diasersi atas
 * seluruh halaman, karena keterangan cakupan dan kedua sebaran status memuat
 * banyak persentase.
 */
function laporanEngineRoomSlice(string $html, string $testid, string $closing = '</p>'): string
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
 * KARTU, bukan baris tabel: laporan ini merender kedua puluh dua metrik
 * sebagai kartu di dalam enam blok tata letak, dan tiap kartu HARUS mencetak
 * penyebutnya sendiri.
 */
function laporanEngineRoomCard(string $html, string $column): string
{
    $start = strpos($html, 'data-metric="'.$column.'"');

    if ($start === false) {
        return '';
    }

    $end = strpos($html, '</article>', $start);

    return $end === false ? substr($html, $start) : substr($html, $start, $end - $start);
}

/**
 * Potongan satu baris <tr data-status-value="..."> di dalam satu kartu
 * sebaran.
 *
 * DIPAKAI ALIH-ALIH MEMINDAI TEKS KARTUNYA: label 'Run' / 'Off' hanya dua
 * atau tiga huruf dan akan cocok di dalam kata lain mana pun yang kebetulan
 * ada di kartu itu — asersi substring lalu hijau tanpa membuktikan apa pun.
 * Harga kesalahan itu sudah dibayar tiga kali (screen-156, screen-157).
 */
function laporanEngineRoomStatusRow(string $html, string $testid, string $value): string
{
    $card = laporanEngineRoomSlice($html, $testid.'-table', '</table>');
    $start = strpos($card, 'data-status-value="'.$value.'"');

    if ($start === false) {
        return '';
    }

    $end = strpos($card, '</tr>', $start);

    return $end === false ? substr($card, $start) : substr($card, $start, $end - $start);
}

/**
 * Jumlah slot pada SEL tertentu di dalam satu baris sebaran — sel kedua.
 *
 * Diambil dari <td>-nya sendiri, bukan dengan memindai barisnya: angka
 * telanjang cocok di mana saja, termasuk di dalam porsi persennya pada sel
 * ketiga baris yang sama.
 */
function laporanEngineRoomStatusCount(string $html, string $testid, string $value): ?string
{
    $row = $value === 'not_recorded'
        ? laporanEngineRoomSlice($html, 'status-not-recorded', '</tr>')
        : laporanEngineRoomStatusRow($html, $testid, $value);

    if ($row === '') {
        return null;
    }

    if (preg_match_all('#<td[^>]*>(.*?)</td>#s', $row, $matches) < 2) {
        return null;
    }

    return trim(strip_tags($matches[1][1]));
}

/** Tanggal seperti yang dicetak blade: '07 Okt 2026'. */
function laporanEngineRoomTanggal(string $date): string
{
    $bulan = ['01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr', '05' => 'Mei', '06' => 'Jun',
        '07' => 'Jul', '08' => 'Agu', '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'];

    [$y, $m, $d] = array_pad(explode('-', substr($date, 0, 10)), 3, '');

    return $d.' '.($bulan[$m] ?? $m).' '.$y;
}

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->engineRoom()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->engineRoom()->create();

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

    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::EngineRoom->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Engine Room Alpha')
        ->create();

    $this->slots = EngineRoomRecordService::canonicalTimeSlots();
});

// =====================================================================
// Jalur sukses
// =====================================================================

it('skenario 1 — Supervisor: tanpa pemilih mill, seluruh bagian laporan terender', function () {
    $record = laporanEngineRoomComponentRecord($this->stationA, $this->day1);

    laporanEngineRoomComponentSlot($record, $this->slots[0], [
        'steam_turbine_inlet_pressure_bar' => 20.0,
        'steam_turbine_rpm' => 3000.0,
        'electrical_sync_total_factory_load_kw' => 900.0,
        'air_compressor_1_pressure_bar' => 7.0,
        'fuel_tank_level' => 50.0,
        'daily_energy_export_kwh' => 1200.0,
        'diesel_gen_1_status' => 'run',
        'diesel_gen_2_status' => 'standby',
        'action_taken_maintenance_remark' => 'Ganti filter oli',
        'findings' => 'Getaran turbin naik',
    ]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEngineRoom::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Peran terikat mill tidak melihat pemilih mill — menawarkan pemilih yang
    // tidak bisa ia pakai adalah kebohongan kecil yang mahal.
    expect($html)->not->toContain('data-testid="mill-select"');
    expect($html)->toContain('data-testid="mill-name"');
    expect($html)->toContain('Mill Alpha');

    foreach (LAPORAN_ENGINE_ROOM_FIGURE_TESTIDS as $testid) {
        $this->assertStringContainsString('data-testid="'.$testid.'"', $html, "testid {$testid} tidak dirender");
    }

    foreach (LAPORAN_ENGINE_ROOM_DATA_TESTIDS as $testid) {
        $this->assertStringContainsString('data-testid="'.$testid.'"', $html, "testid {$testid} tidak dirender");
    }

    // KEDUA PULUH DUA kartu metrik, masing-masing dengan penyebutnya sendiri,
    // PLUS kartu ke-23 (ekspor energi) yang markupnya sendiri karena ia bukan
    // rata-rata periode.
    expect(substr_count($html, 'data-testid="metric-card"'))->toBe(22);
    expect(substr_count($html, 'data-testid="metric-denominator"'))->toBe(22);
    expect(substr_count($html, 'data-testid="metric-avg"'))->toBe(22);
    expect(substr_count($html, 'data-testid="metric-range"'))->toBe(22);
    expect(substr_count($html, 'data-testid="energy-export-card"'))->toBe(1);
    expect(count(EngineRoomReportService::NUMERIC_METRICS))->toBe(22);
    // 22 kartu metrik + 1 kartu energi = 23 kolom ukur, seluruhnya dirender.
    expect(substr_count($html, 'data-metric="'))->toBe(23);

    foreach (EngineRoomReportService::NUMERIC_METRICS as $column) {
        $this->assertStringContainsString('data-metric="'.$column.'"', $html, "kartu metrik {$column} tidak dirender");
    }

    $this->assertStringContainsString('data-metric="daily_energy_export_kwh"', $html);

    // Keenam blok membawa data-metric-group-nya, diturunkan dari RESPONS.
    foreach (['steam_turbine', 'diesel_gen_1', 'diesel_gen_2', 'electrical_sync', 'compressor', 'auxiliary'] as $group) {
        $this->assertStringContainsString('data-metric-group="'.$group.'"', $html, "blok {$group} tidak dirender");
    }

    // Blok kelengkapan muncul SEBELUM kartu metrik pertama di urutan DOM —
    // periode yang terisi seperlima pun menghasilkan rata-rata yang terlihat
    // rapi, jadi pembaca harus melihat kelengkapannya lebih dulu.
    expect(strpos($html, 'data-testid="coverage"'))->toBeLessThan(strpos($html, 'data-testid="metric-card"'));
    expect(strpos($html, 'data-testid="coverage"'))
        ->toBeLessThan(strpos($html, 'data-testid="metric-block-steam-turbine"'));

    // KEDUA sebaran status: 3 baris nilai masing-masing + baris tidak
    // tercatat masing-masing.
    expect(substr_count($html, 'data-testid="status-row"'))->toBe(6);
    expect(substr_count($html, 'data-testid="status-not-recorded"'))->toBe(2);

    foreach (['status-distribution-diesel-gen-1', 'status-distribution-diesel-gen-2'] as $testid) {
        foreach (['run', 'standby', 'off'] as $value) {
            expect(laporanEngineRoomStatusRow($html, $testid, $value))
                ->not->toBe('', "{$testid} tidak merender baris {$value}");
        }
    }

    // Keterangan kesejajaran genset dirender di KEDUA blok genset.
    expect(substr_count($html, 'data-testid="genset-parallel-note"'))->toBe(2);

    // DUA rekap teks TERPISAH dengan judulnya masing-masing.
    expect($html)->toContain('Rekap Catatan Tindakan Perawatan');
    expect($html)->toContain('Rekap Temuan');
    expect($html)->toContain('Ganti filter oli');
    expect($html)->toContain('Getaran turbin naik');

    // Dua seri harian dengan agregasi BERBEDA.
    expect($html)->toContain('Seri Harian Ekspor Energi');
    expect($html)->toContain('Tren Harian Beban Total Pabrik');

    // Kedua tombol ekspor tampil.
    expect($html)->toContain('data-testid="export-button"');
    expect($html)->toContain('data-testid="export-excel-button"');

    // Bacaan saja: tidak ada satu pun kontrol tulis.
    expect($html)->not->toContain('>Simpan<');
    expect($html)->not->toContain('>Hapus<');
    expect($html)->not->toContain('>Verifikasi<');
});

it('skenario 1b — Mill Management melihat layar yang SAMA PERSIS dengan Supervisor', function () {
    $record = laporanEngineRoomComponentRecord($this->stationA, $this->day1);
    laporanEngineRoomComponentSlot($record, $this->slots[0], ['steam_turbine_rpm' => 3000.0]);

    $html = Livewire::actingAs($this->millManagement)
        ->test(LaporanEngineRoom::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->not->toContain('data-testid="mill-select"');
    expect($html)->toContain('data-testid="mill-name"');
    expect($html)->toContain('data-testid="coverage"');
    expect(substr_count($html, 'data-testid="metric-card"'))->toBe(22);
});

it('skenario 2 — Admin: pemilih mill dirender, dan isinya identik dengan peran terikat mill', function () {
    $record = laporanEngineRoomComponentRecord($this->stationA, $this->day1);
    laporanEngineRoomComponentSlot($record, $this->slots[0], [
        'steam_turbine_rpm' => 3000.0,
        'daily_energy_export_kwh' => 1200.0,
        'action_taken_maintenance_remark' => 'Ganti filter',
    ]);

    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::EngineRoom->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Engine Room Beta')
        ->create();

    $component = Livewire::actingAs($this->admin)->test(LaporanEngineRoom::class);

    expect($component->html())->toContain('data-testid="mill-select"');

    $withMill = $component
        ->set('businessUnitId', (string) $this->businessUnitA->id)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Pemilih Mill TETAP dirender bagi Admin.
    expect($withMill)->toContain('data-testid="mill-select"');
    // Daftar periode hanya memuat periode mill yang DIPILIH.
    expect($withMill)->toContain('Periode Engine Room Alpha');
    expect($withMill)->not->toContain('Periode Engine Room Beta');

    // Isi laporannya identik dengan peran terikat mill.
    expect(substr_count($withMill, 'data-testid="metric-card"'))->toBe(22);
    expect(substr_count($withMill, 'data-testid="status-row"'))->toBe(6);
    expect($withMill)->toContain('data-testid="daily-energy-export"');
    expect($withMill)->toContain('data-testid="maintenance-remarks"');
    expect($withMill)->toContain('data-testid="findings"');
    expect($withMill)->toContain('data-testid="hero-production-line"');

    // Dan tidak ada satu pun aksi tulis yang tersedia.
    expect($withMill)->not->toContain('>Simpan<');
    expect($withMill)->not->toContain('>Hapus<');
});

it('skenario 3 — Admin tanpa memilih mill: arahan memilih mill, NOL angka, tanpa tombol ekspor', function () {
    $record = laporanEngineRoomComponentRecord($this->stationA, $this->day1);
    laporanEngineRoomComponentSlot($record, $this->slots[0], ['steam_turbine_rpm' => 3000.0]);

    $html = Livewire::actingAs($this->admin)->test(LaporanEngineRoom::class)->html();

    expect($html)->toContain('data-testid="mill-select"');
    expect($html)->toContain('data-testid="mill-select-hint"');
    expect($html)->toContain('Pilih mill terlebih dahulu');

    // Tidak satu pun angka laporan, dan pemilih periode belum dirender.
    foreach (LAPORAN_ENGINE_ROOM_FIGURE_TESTIDS as $testid) {
        $this->assertStringNotContainsString('data-testid="'.$testid.'"', $html, "testid {$testid} seharusnya TIDAK dirender");
    }

    expect($html)->not->toContain('data-testid="period-select"');
    expect($html)->not->toContain('data-testid="export-button"');
    expect($html)->not->toContain('data-testid="export-excel-button"');
});

it('skenario 4 — Production Line belum dipilih: arahan memilih line, NOL angka, tanpa tombol ekspor', function () {
    $record = laporanEngineRoomComponentRecord($this->stationA, $this->day1);
    laporanEngineRoomComponentSlot($record, $this->slots[0], ['steam_turbine_rpm' => 3000.0]);

    // Mill punya DUA line, dan tidak satu pun dipilih otomatis.
    ProductionLine::factory()->create(['business_unit_id' => $this->businessUnitA->id, 'name' => 'Line Kedua']);

    $component = Livewire::actingAs($this->supervisor)->test(LaporanEngineRoom::class);

    $html = $component->html();

    expect($html)->toContain('data-testid="select-production-line-hint"');

    // KETIGA KEADAAN: muat pertama, sesudah ganti periode, dan sesudah
    // pemuatan ulang — pada ketiganya tidak ada satu angka pun.
    $afterPeriod = $component->set('periodId', (string) $this->periodA->id)->html();
    $afterReload = $component->set('productionLineId', '')->html();

    foreach ([$html, $afterPeriod, $afterReload] as $index => $markup) {
        foreach (LAPORAN_ENGINE_ROOM_FIGURE_TESTIDS as $testid) {
            $this->assertStringNotContainsString(
                'data-testid="'.$testid.'"',
                $markup,
                "testid {$testid} seharusnya TIDAK dirender (keadaan {$index})",
            );
        }

        // TIDAK ADA angka seluruh mill sebagai penggantinya, dan tombol
        // ekspor BELUM DIRENDER — tombol yang ada tetapi tidak berguna adalah
        // tombol yang akan diklik.
        $this->assertStringNotContainsString('data-testid="export-button"', $markup);
        $this->assertStringNotContainsString('data-testid="export-excel-button"', $markup);
        $this->assertStringContainsString('data-testid="select-production-line-hint"', $markup);
    }

    $hint = laporanEngineRoomSlice($html, 'select-production-line-hint', '</div>');

    expect($hint)->toContain('tidak menampilkan angka seluruh mill');
});

it('skenario 5 / case 72 — Admin mengganti mill MENGOSONGKAN periode DAN production line', function () {
    $record = laporanEngineRoomComponentRecord($this->stationA, $this->day1);
    laporanEngineRoomComponentSlot($record, $this->slots[0], ['steam_turbine_rpm' => 3000.0]);

    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::EngineRoom->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Engine Room Beta')
        ->create();

    $component = Livewire::actingAs($this->admin)
        ->test(LaporanEngineRoom::class)
        ->set('businessUnitId', (string) $this->businessUnitA->id)
        ->set('productionLineId', $this->lineA);

    // Periode terbaru terpilih otomatis pada muat pertama, jadi laporannya
    // sudah tampil sebelum mill diganti.
    expect($component->html())->toContain('data-testid="coverage"');
    expect($component->get('periodId'))->not->toBe('');

    // Sebuah periode MAUPUN sebuah line milik tepat satu mill, jadi membawa
    // salah satunya melintas akan merender penolakan untuk sesuatu yang
    // tidak dilakukan pengguna (QA LP-W-09).
    $afterSwitch = $component->set('businessUnitId', (string) $this->businessUnitB->id);

    expect($afterSwitch->get('periodId'))->toBe('');
    expect($afterSwitch->get('productionLineId'))->toBe('');

    $html = $afterSwitch->html();

    expect($html)->not->toContain('data-testid="metric-card"');
    expect($html)->not->toContain('data-testid="coverage-percent"');
    // Daftar periode dimuat ulang untuk mill B dan tidak memuat periode mill A.
    expect($html)->toContain('Periode Engine Room Beta');
    expect($html)->not->toContain('Periode Engine Room Alpha');
    // Layar menampilkan arahan memilih line.
    expect($html)->toContain('data-testid="select-production-line-hint"');
});

it('skenario 6 — mill tanpa SATU PUN periode Engine Room: arahan menghubungi Admin, tanpa angka', function () {
    $supervisorB = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitB)->create();

    // Mill B punya periode untuk jenis stasiun LAIN — tanpa baris ini asersi
    // "pemilih periode kosong" selalu hijau karena tidak ada yang dapat
    // bocor.
    Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::BoilerRoom->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Boiler Room Beta')
        ->create();

    $html = Livewire::actingAs($supervisorB)
        ->test(LaporanEngineRoom::class)
        ->set('productionLineId', $this->lineB)
        ->html();

    expect($html)->toContain('data-testid="no-period-hint"');
    expect($html)->toContain('Belum ada Periode Pelaporan');
    expect($html)->toContain('Hubungi Admin');
    expect($html)->toContain('Kelola Periode Pelaporan');

    // Pemilih periode sengaja tetap kosong dan TIDAK diisi periode milik
    // jenis stasiun lain.
    expect($html)->not->toContain('Periode Boiler Room Beta');

    foreach (LAPORAN_ENGINE_ROOM_FIGURE_TESTIDS as $testid) {
        $this->assertStringNotContainsString('data-testid="'.$testid.'"', $html, "testid {$testid} seharusnya TIDAK dirender");
    }
});

// =====================================================================
// Keadaan data: kartu kosong, genset, teks bebas, seri energi
// =====================================================================

it('skenario 7 — periode tanpa data: 22 kartu "tidak tersedia" dengan 0 pembacaan, tanpa grafik kosong', function () {
    $record = laporanEngineRoomComponentRecord($this->stationA, $this->day1);
    laporanEngineRoomComponentSlot($record, $this->slots[0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEngineRoom::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Blok kelengkapan TETAP dirender, dan keterangan belum ada data terlihat.
    expect($html)->toContain('data-testid="coverage"');
    expect($html)->toContain('data-testid="empty-state"');
    expect($html)->toContain('Belum ada data pada periode dan line ini');

    // Kartu metrik TETAP digambar — kartu yang hilang terbaca sebagai
    // "parameter itu tidak ada", padahal yang benar "tidak ada yang
    // mengukurnya".
    expect(substr_count($html, 'data-testid="metric-card"'))->toBe(22);
    expect(substr_count($html, 'data-testid="metric-denominator"'))->toBe(22);

    foreach (EngineRoomReportService::NUMERIC_METRICS as $column) {
        $card = laporanEngineRoomCard($html, $column);

        // Nilainya 'tidak tersedia', BUKAN angka 0.
        $this->assertStringContainsString('tidak tersedia', $card, $column);
        // Dan penyebutnya 0 — bentuk inilah yang menangkap penyebut bersama
        // tanpa memaku satu angka pun: penyebut bersama akan mencetak jumlah
        // slot terisi periode di sana. CATATAN BENTUK ASERSI: pesan tambahan
        // TIDAK boleh dikirim ke expect()->toContain(), yang memperlakukan
        // argumen kedua sebagai NEEDLE KEDUA — dan needle itu kebetulan ada
        // di dalam potongan kartunya, sehingga asersinya hijau tanpa
        // membuktikan apa pun.
        $this->assertStringContainsString('>0<', laporanEngineRoomSlice($card, 'metric-denominator'), $column);
    }

    // Kedua seri harian, rekap per unit dan rekap harian TIDAK digambar:
    // grafik kosong terbaca sebagai garis datar hasil pengukuran.
    foreach (LAPORAN_ENGINE_ROOM_DATA_TESTIDS as $testid) {
        $this->assertStringNotContainsString('data-testid="'.$testid.'"', $html, "testid {$testid} seharusnya TIDAK dirender");
    }
});

it('skenario 8 — satu metrik kosong sepanjang periode, 21 metrik lain tetap berangka', function () {
    $record = laporanEngineRoomComponentRecord($this->stationA, $this->day1);

    $terisi = array_values(array_diff(
        EngineRoomReportService::NUMERIC_METRICS,
        ['electrical_sync_power_factor'],
    ));

    // Jumlah pembacaan yang BERBEDA-BEDA antar metrik — fixture yang rata
    // membuat satu penyebut bersama menjawab sama dan asersinya kosong.
    foreach (range(0, 4) as $index) {
        $values = array_fill_keys($terisi, 10.0 + $index);

        if ($index >= 2) {
            unset($values['steam_turbine_rpm']);
        }

        laporanEngineRoomComponentSlot($record, $this->slots[$index], $values);
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEngineRoom::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Kartu power factor TETAP dirender dengan penanda tidak tersedia dan
    // penyebut 0 — ia tidak hilang dari blok Kelistrikan Tersinkron.
    $kosong = laporanEngineRoomCard($html, 'electrical_sync_power_factor');

    expect($kosong)->not->toBe('');
    expect($kosong)->toContain('tidak tersedia');
    expect(laporanEngineRoomSlice($kosong, 'metric-denominator'))->toContain('>0<');
    expect(strpos($html, 'data-metric-group="electrical_sync"'))
        ->toBeLessThan(strpos($html, 'data-metric="electrical_sync_power_factor"'));

    // Dan penyebut kedua puluh satu kartu lain BERBEDA-BEDA, bukan satu
    // angka yang sama di semuanya.
    expect(laporanEngineRoomSlice(laporanEngineRoomCard($html, 'steam_turbine_rpm'), 'metric-denominator'))
        ->toContain('>2<');
    expect(laporanEngineRoomSlice(laporanEngineRoomCard($html, 'steam_turbine_inlet_pressure_bar'), 'metric-denominator'))
        ->toContain('>5<');
});

it('skenario 9 — satu genset tidak pernah menyala: empat kartu nol, sebaran tetap lengkap', function () {
    $record = laporanEngineRoomComponentRecord($this->stationA, $this->day1);

    foreach (range(0, 4) as $index) {
        laporanEngineRoomComponentSlot($record, $this->slots[$index], [
            'diesel_gen_1_load_kw' => 700.0,
            'diesel_gen_1_amperage_a' => 1100.0,
            'diesel_gen_1_jacket_water_temp_c' => 80.0,
            'diesel_gen_1_lube_oil_pressure_bar' => 4.2,
            'diesel_gen_1_status' => 'run',
            'diesel_gen_2_status' => 'off',
        ]);
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEngineRoom::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    foreach ([
        'diesel_gen_2_load_kw', 'diesel_gen_2_amperage_a',
        'diesel_gen_2_jacket_water_temp_c', 'diesel_gen_2_lube_oil_pressure_bar',
    ] as $column) {
        $card = laporanEngineRoomCard($html, $column);

        $this->assertStringContainsString('tidak tersedia', $card, $column);
        $this->assertStringContainsString('>0<', laporanEngineRoomSlice($card, 'metric-denominator'), $column);
    }

    // Sebaran Genset 2: seluruh slot pada baris Off, baris Run bernilai 0 —
    // DIBACA DARI SEL-nya sendiri, bukan dengan memindai kartunya.
    expect(laporanEngineRoomStatusCount($html, 'status-distribution-diesel-gen-2', 'off'))->toBe('5');
    expect(laporanEngineRoomStatusCount($html, 'status-distribution-diesel-gen-2', 'run'))->toBe('0');
    expect(laporanEngineRoomStatusCount($html, 'status-distribution-diesel-gen-2', 'standby'))->toBe('0');

    // Tidak ada elemen bertanda galat atau peringatan: genset mati sepanjang
    // periode adalah keadaan operasional yang SAH.
    foreach ([
        'md-threshold', 'is-danger', 'is-warning', 'is-critical', 'is-breach',
        'md-chip--danger', 'md-badge--danger', 'md-kpi--danger',
    ] as $className) {
        $this->assertStringNotContainsString($className, $html, "kelas penanda {$className} dipakai");
    }
});

it('skenario 10 — kedua genset dengan angka sangat berbeda: dua kartu terpisah, tanpa kartu gabungan', function () {
    $record = laporanEngineRoomComponentRecord($this->stationA, $this->day1);

    foreach (range(0, 3) as $index) {
        laporanEngineRoomComponentSlot($record, $this->slots[$index], [
            'diesel_gen_1_load_kw' => 800.0,
            'diesel_gen_2_load_kw' => 40.0,
        ]);
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEngineRoom::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // DUA kartu TERPISAH dengan angkanya masing-masing — dibaca dari sel
    // nilai di dalam kartunya, bukan dari halaman.
    expect(laporanEngineRoomSlice(laporanEngineRoomCard($html, 'diesel_gen_1_load_kw'), 'metric-avg', '</span>'))
        ->toContain('800');
    expect(laporanEngineRoomSlice(laporanEngineRoomCard($html, 'diesel_gen_2_load_kw'), 'metric-avg', '</span>'))
        ->toContain('40');

    // Tidak ada kartu gabungan, badge selisih, maupun penanda penyimpangan.
    expect($html)->not->toContain('data-metric="diesel_gen_combined_load_kw"');
    expect($html)->not->toContain('data-testid="genset-delta"');
    expect($html)->not->toContain('data-testid="genset-imbalance"');
    expect($html)->not->toContain('data-testid="genset-total"');

    // Keterangan kesejajaran FISIK dirender di KEDUA blok, dan TIDAK menyebut
    // standar bersama — stasiun ini tidak punya master target sama sekali.
    expect(substr_count($html, 'data-testid="genset-parallel-note"'))->toBe(2);

    $note = laporanEngineRoomSlice($html, 'genset-parallel-note', '</div>');

    expect($note)->toContain('dua mesin fisik yang berbeda');
    expect($note)->toContain('bukan standar');
    expect($note)->toContain('sengaja tidak dirata-ratakan');
});

it('skenario 11 — satu slot hanya mengisi kolom teks bebas: slot terisi melebihi tiap penyebut', function () {
    $record = laporanEngineRoomComponentRecord($this->stationA, $this->day1);

    foreach (range(0, 4) as $index) {
        laporanEngineRoomComponentSlot($record, $this->slots[$index], [
            'action_taken_maintenance_remark' => 'Cek level oli',
        ]);
    }

    foreach (range(5, 6) as $index) {
        laporanEngineRoomComponentSlot($record, $this->slots[$index], ['steam_turbine_rpm' => 3000.0]);
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEngineRoom::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Slot terisi 7 sementara penyebut terbesar 2 — DIBACA DARI SEL-nya,
    // bukan dengan memindai halaman: angka telanjang cocok di mana saja.
    expect(laporanEngineRoomSlice($html, 'coverage-slots', '</span>'))->toContain('>7<');
    expect(laporanEngineRoomSlice(laporanEngineRoomCard($html, 'steam_turbine_rpm'), 'metric-denominator'))
        ->toContain('>2<');

    // Keterangan sebabnya dirender DI BLOK KELENGKAPAN dan TIDAK diringkas —
    // ia menyebut kedua puluh tujuh kolom termasuk kedua kolom teks.
    $note = laporanEngineRoomSlice($html, 'coverage-note', '</div>');

    expect($note)->toContain('dua puluh');
    expect($note)->toContain('tujuh');
    expect($note)->toContain('kedua kolom teks bebas');
    expect($note)->toContain('lebih besar');
});

it('skenario 12 — seluruh slot tidak mencatat status genset: tiga baris nol, tidak tercatat 18', function () {
    $record = laporanEngineRoomComponentRecord($this->stationA, $this->day1);

    foreach (range(0, 17) as $index) {
        laporanEngineRoomComponentSlot($record, $this->slots[$index], ['steam_turbine_rpm' => 3000.0]);
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEngineRoom::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // KETIGA baris nilai TETAP dirender dengan nol masing-masing, dan baris
    // tidak tercatat memuat 18 — tak satu pun slot dilipat ke
    // run/standby/off.
    foreach (['run', 'standby', 'off'] as $value) {
        expect(laporanEngineRoomStatusCount($html, 'status-distribution-diesel-gen-1', $value))
            ->toBe('0', $value);
    }

    expect(laporanEngineRoomStatusCount($html, 'status-distribution-diesel-gen-1', 'not_recorded'))->toBe('18');

    // Keterangan bahwa Standby bukan Off tetap terlihat.
    expect($html)->toContain('data-testid="standby-not-off-note"');
    expect(laporanEngineRoomSlice($html, 'standby-not-off-note', '</div>'))
        ->toContain('BUKAN');
});

it('skenario 13 / case 73 — nilai status dikenal yang tidak pernah muncul tetap dirender dengan 0', function () {
    $record = laporanEngineRoomComponentRecord($this->stationA, $this->day1);

    foreach (range(0, 9) as $index) {
        laporanEngineRoomComponentSlot($record, $this->slots[$index], [
            'diesel_gen_1_status' => $index < 7 ? 'run' : 'off',
        ]);
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEngineRoom::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Baris 'standby' TETAP ADA di DOM dengan jumlah 0: "tidak pernah siaga"
    // terbaca BERBEDA dari "tidak dilaporkan", yang muncul pada baris tidak
    // tercatat dengan jumlah yang berbeda.
    expect(laporanEngineRoomStatusRow($html, 'status-distribution-diesel-gen-1', 'standby'))->not->toBe('');
    expect(laporanEngineRoomStatusCount($html, 'status-distribution-diesel-gen-1', 'standby'))->toBe('0');
    expect(laporanEngineRoomStatusCount($html, 'status-distribution-diesel-gen-1', 'run'))->toBe('7');
    expect(laporanEngineRoomStatusCount($html, 'status-distribution-diesel-gen-1', 'off'))->toBe('3');
    expect(laporanEngineRoomStatusCount($html, 'status-distribution-diesel-gen-1', 'not_recorded'))->toBe('0');

    // CASE 73 — LABELNYA DIDELEGASIKAN ke App\Support\Display::option() dan
    // berbunyi Run / Standby / Off; blade TIDAK memuat peta label sendiri dan
    // TIDAK menerjemahkannya ke Bahasa Indonesia.
    //
    // Diasersi PADA BARISNYA MASING-MASING lewat data-status-value, bukan
    // dengan memindai teks kartunya: 'On'/'Off'/'Run' hanya dua atau tiga
    // huruf dan akan cocok di dalam kata lain mana pun.
    foreach (['run' => 'Run', 'standby' => 'Standby', 'off' => 'Off'] as $value => $label) {
        $row = laporanEngineRoomStatusRow($html, 'status-distribution-diesel-gen-1', $value);

        $this->assertStringContainsString('>'.$label.'<', $row, $value);
    }

    // Tidak ada label Bahasa Indonesia untuk ketiga nilai itu.
    expect($html)->not->toContain('>Siaga<');
    expect($html)->not->toContain('>Nyala<');
    expect($html)->not->toContain('>Mati<');

    // Dan blade mendelegasikan — diasersi SECARA STRUKTURAL, karena asersi
    // perilaku akan tetap hijau pada peta label yang disalin dan kebetulan
    // masih sama.
    $blade = file_get_contents(resource_path('views/livewire/dashboard/laporan-engine-room.blade.php'));

    expect($blade)->toContain('\App\Support\Display::option(');
    expect($blade)->not->toContain("'run' => 'Run'");
    expect($blade)->not->toContain("'standby' => 'Standby'");
});

it('skenario 14 — ekspor energi harian tidak pernah diisi: keterangan tidak ada data, tanpa titik nol', function () {
    $record = laporanEngineRoomComponentRecord($this->stationA, $this->day1);

    foreach (range(0, 2) as $index) {
        laporanEngineRoomComponentSlot($record, $this->slots[$index], [
            'steam_turbine_rpm' => 3000.0,
            'daily_energy_export_kwh' => null,
        ]);
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEngineRoom::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->toContain('data-testid="daily-energy-export-empty"');
    expect(laporanEngineRoomSlice($html, 'daily-energy-export-empty'))
        ->toContain('tidak terisi pada satu slot pun');

    // Tidak ada satu pun kolom tanggal pada serinya, dan tidak ada garis yang
    // digambar — garis nol mengklaim pengukuran yang tidak pernah dilakukan.
    expect($html)->not->toContain('data-testid="daily-energy-export-col-');
    expect(substr_count($html, 'data-testid="daily-energy-export-col-'))->toBe(0);

    // Kartu ke-23 tetap dirender, dengan nilai tidak tersedia dan penyebut 0.
    $card = laporanEngineRoomSlice($html, 'energy-export-card', '</article>');

    expect($card)->toContain('tidak tersedia');
    expect(laporanEngineRoomSlice($html, 'energy-export-denominator'))->toContain('>0<');

    // Dan sel total ekspor energi pada rekap harian sengaja TANDA PISAH.
    expect(laporanEngineRoomSlice($html, 'daily-total-energy-export', '</td>'))->toContain('&mdash;');
});

it('skenario 15 — ekspor energi harian turun di dalam satu hari: nilainya apa adanya, tanpa penanda', function () {
    // MERAH PADA IMPLEMENTASI HARI INI karena cacat `sortBy([closure,
    // closure])` di EngineRoomReportService::lastEnergyValueOf() —
    // diagnosis lengkap dan perbaikan satu barisnya ada di kepala case 44
    // pada tests/Unit/Services/EngineRoomReportServiceTest.php.
    $record = laporanEngineRoomComponentRecord($this->stationA, $this->day2);

    // 07:00 = 900 lalu 12:00 = 300 sebagai slot TERAKHIR pada kisi kanonis.
    laporanEngineRoomComponentSlot($record, '07:00', ['daily_energy_export_kwh' => 900.0]);
    laporanEngineRoomComponentSlot($record, '12:00', ['daily_energy_export_kwh' => 300.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEngineRoom::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    $col = laporanEngineRoomSlice($html, 'daily-energy-export-col-'.$this->day2, '</div>');

    expect($col)->not->toBe('');
    // Nilainya 300 APA ADANYA, dan kolomnya membawa slot asalnya.
    expect($col)->toContain('>300<');
    expect($col)->toContain('data-time-slot="12:00"');

    // Tidak ada elemen bertanda anomali, galat, atau koreksi.
    foreach ([
        'md-threshold', 'is-danger', 'is-warning', 'is-critical',
        'md-trendchart__bar--high', 'md-trendchart__bar--low',
    ] as $className) {
        $this->assertStringNotContainsString($className, $html, "kelas penanda {$className} dipakai");
    }

    // Dan keterangan ketidakpastian semantik kolom ini TETAP dirender di
    // dekat serinya, utuh.
    $note = laporanEngineRoomSlice($html, 'daily-energy-export-note', '</div>');

    expect($note)->toContain('nilai TERAKHIR hari itu');
    expect($note)->toContain('per slot waktu');
    expect($note)->toContain('kisi kanonis');
    expect($note)->toContain('turun');
});

it('skenario 16 — satu slot mengisi kedua kolom teks: dirender SEKALI di tiap rekap', function () {
    $record = laporanEngineRoomComponentRecord($this->stationA, $this->day2);

    laporanEngineRoomComponentSlot($record, '07:00', [
        'action_taken_maintenance_remark' => 'Kencangkan baut pondasi',
        'findings' => 'Baut pondasi longgar',
    ]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEngineRoom::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // SATU entri di masing-masing rekap, masing-masing dengan tanggal dan
    // slot waktunya.
    expect(substr_count($html, 'data-testid="maintenance-remark-row"'))->toBe(1);
    expect(substr_count($html, 'data-testid="finding-row"'))->toBe(1);

    $remark = laporanEngineRoomSlice($html, 'maintenance-remark-row', '</div></div>');
    $finding = laporanEngineRoomSlice($html, 'finding-row', '</div></div>');

    expect($remark)->toContain('Kencangkan baut pondasi');
    expect($remark)->toContain(laporanEngineRoomTanggal($this->day2));
    expect($remark)->toContain('07:00');
    expect($finding)->toContain('Baut pondasi longgar');
    expect($finding)->toContain('07:00');

    // DUA bagian BERJUDUL TERPISAH, dan rekap perawatan di atas rekap temuan.
    expect(strpos($html, 'data-testid="maintenance-remarks"'))
        ->toBeLessThan(strpos($html, 'data-testid="findings"'));
    expect($html)->toContain('Rekap Catatan Tindakan Perawatan');
    expect($html)->toContain('Rekap Temuan');

    // Tidak ada tabel gabungan, dan jumlah slot terisi TIDAK bertambah dua.
    expect(laporanEngineRoomSlice($html, 'coverage-slots', '</span>'))->toContain('>1<');
});

it('skenario 17 — kedua rekap teks kosong: keterangan masing-masing, bukan tabel kosong', function () {
    $record = laporanEngineRoomComponentRecord($this->stationA, $this->day1);
    laporanEngineRoomComponentSlot($record, $this->slots[0], ['steam_turbine_rpm' => 3000.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEngineRoom::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->toContain('data-testid="maintenance-remarks-empty"');
    expect($html)->toContain('data-testid="findings-empty"');
    expect(laporanEngineRoomSlice($html, 'maintenance-remarks-empty'))
        ->toContain('Tidak ada satu pun catatan tindakan perawatan');
    expect(laporanEngineRoomSlice($html, 'findings-empty'))
        ->toContain('Tidak ada satu pun temuan');

    // Keduanya tetap punya JUDUL TERPISAH, dan tidak ada header tabel yang
    // berdiri sendiri tanpa baris.
    expect($html)->toContain('Rekap Catatan Tindakan Perawatan');
    expect($html)->toContain('Rekap Temuan');
    expect($html)->not->toContain('data-testid="maintenance-remark-row"');
    expect($html)->not->toContain('data-testid="finding-row"');
});

// =====================================================================
// Rekap per unit, kelengkapan, dan keadaan periode
// =====================================================================

it('skenario 18 — beberapa unit Engine Room: satu baris per unit, unit tanpa pembacaan tetap dirender', function () {
    $terisi = laporanEngineRoomComponentRecord($this->stationA, $this->day1, 'ER-TERISI');
    laporanEngineRoomComponentSlot($terisi, $this->slots[0], ['steam_turbine_rpm' => 3000.0]);
    laporanEngineRoomComponentSlot($terisi, $this->slots[1], ['steam_turbine_rpm' => 3100.0]);

    $kosong = laporanEngineRoomComponentRecord($this->stationA, $this->day1, 'ER-KOSONG');
    laporanEngineRoomComponentSlot($kosong, $this->slots[0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEngineRoom::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Angka periode pada kartu metrik MENGGABUNGKAN kedua unit.
    expect(laporanEngineRoomSlice(laporanEngineRoomCard($html, 'steam_turbine_rpm'), 'metric-denominator'))
        ->toContain('>2<');
    expect(laporanEngineRoomSlice($html, 'hero-unit-count', '</span>'))->toContain('2');

    // DUA baris unit, dan baris unit tanpa pembacaan TIDAK dihilangkan dari
    // tabel: membuangnya akan menyembunyikan justru unit yang tidak pernah
    // ditulis.
    expect(substr_count($html, 'data-testid="by-unit-row"'))->toBe(2);
    expect($html)->toContain('>ER-TERISI<');
    expect($html)->toContain('>ER-KOSONG<');

    // Baris total menyatakan gabungannya, dihitung ulang atas seluruh slot.
    expect(laporanEngineRoomSlice($html, 'by-unit-row-total', '</td>'))->toContain('Gabungan');

    // Jumlah slot per unit (kolom data-testid="by-unit-reading-rows")
    // menjumlah ke slot terisi periode.
    preg_match_all('#data-testid="by-unit-reading-rows"[^>]*>(.*?)</td>#s', $html, $matches);

    expect($matches[1])->toHaveCount(2);
    expect(array_sum(array_map(fn ($cell) => (int) trim(strip_tags($cell)), $matches[1])))->toBe(2);
});

it('skenario 19 — pencatatan sangat tidak lengkap: cakupan DI ATAS kartu metrik, persennya kecil', function () {
    // 12 slot dari 720 yang diharapkan (3 unit × 10 hari × 24).
    foreach (['ER-1', 'ER-2', 'ER-3'] as $index => $unit) {
        $record = laporanEngineRoomComponentRecord($this->stationA, $this->day2, $unit);

        foreach (range(0, 3) as $slotIndex) {
            laporanEngineRoomComponentSlot($record, $this->slots[$slotIndex], [
                'steam_turbine_rpm' => 3000.0 + $index,
            ]);
        }
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEngineRoom::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Persentase rendah, DIBACA DARI SELNYA — keterangan cakupan dan kedua
    // sebaran status memuat banyak persentase lain, jadi asersi atas halaman
    // tidak membuktikan apa pun.
    expect(laporanEngineRoomSlice($html, 'coverage-percent', '</span>'))->toContain('1,7%');
    expect(laporanEngineRoomSlice($html, 'coverage-slots', '</span>'))->toContain('>12<');
    expect(laporanEngineRoomSlice($html, 'coverage-slots', '</span>'))->toContain('720');

    // DAN elemennya muncul SEBELUM kartu metrik pertama di urutan DOM.
    expect(strpos($html, 'data-testid="coverage-percent"'))
        ->toBeLessThan(strpos($html, 'data-testid="metric-card"'));

    // Angka metriknya TETAP dirender beserta penyebut kecilnya masing-masing.
    expect(substr_count($html, 'data-testid="metric-card"'))->toBe(22);
    expect(laporanEngineRoomSlice(laporanEngineRoomCard($html, 'steam_turbine_rpm'), 'metric-denominator'))
        ->toContain('>12<');
});

it('skenario 20 — periode masih berjalan: keterangannya menyebut HARI TERAKHIR YANG TERHITUNG', function () {
    $running = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::EngineRoom->value)
        ->range(now()->subDays(9)->toDateString(), now()->addDays(20)->toDateString())
        ->open()
        ->named('Periode Berjalan')
        ->create();

    // Hari-hari yang lewat terisi penuh: 10 hari × 24 slot = 240.
    foreach (range(0, 9) as $offset) {
        $record = laporanEngineRoomComponentRecord(
            $this->stationA,
            now()->subDays(9 - $offset)->toDateString(),
            'ER-1',
        );

        foreach ($this->slots as $slot) {
            laporanEngineRoomComponentSlot($record, $slot, ['steam_turbine_rpm' => 3000.0]);
        }
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEngineRoom::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $running->id)
        ->html();

    // Penyebutnya dihitung dari 10 hari, bukan 30 — hari yang belum terjadi
    // tidak mungkin tercatat.
    $slots = laporanEngineRoomSlice($html, 'coverage-slots', '</span>');

    expect($slots)->toContain('240');
    expect($slots)->not->toContain('720');

    // Persennya mendekati 100 dan tidak turun akibat hari yang belum terjadi.
    expect(laporanEngineRoomSlice($html, 'coverage-percent', '</span>'))->toContain('100,0%');

    // Rumusnya memuat '× N hari (sampai hari ini)'.
    $formula = laporanEngineRoomSlice($html, 'coverage-formula');

    expect($formula)->toContain('hari');
    expect($formula)->toContain('(sampai hari ini)');
    expect($formula)->toContain('24 slot');

    // Keterangan periode berjalan menyebut HARI TERAKHIR YANG TERHITUNG,
    // bukan tanggal akhir periode: tanggal akhir yang belum tiba akan
    // terbaca seperti hari yang sudah ikut dihitung.
    $note = laporanEngineRoomSlice($html, 'coverage-running-note');

    expect($note)->toContain('Dihitung sampai '.laporanEngineRoomTanggal(now()->toDateString()));
    expect($note)->not->toContain(laporanEngineRoomTanggal(now()->addDays(20)->toDateString()));
});

it('skenario 21 — tidak ada slot yang diharapkan: persennya TANDA PISAH, bukan 0,0%', function () {
    $future = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::EngineRoom->value)
        ->range(now()->addDays(5)->toDateString(), now()->addDays(14)->toDateString())
        ->open()
        ->named('Periode Belum Mulai')
        ->create();

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEngineRoom::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $future->id)
        ->html();

    // DIBACA DARI SELNYA: '0,0%' akan muncul di banyak tempat lain pada
    // halaman ini (porsi sebaran status), jadi asersi atas halaman akan
    // gagal karena alasan yang salah.
    $percent = laporanEngineRoomSlice($html, 'coverage-percent', '</span>');

    expect($percent)->toContain('&mdash;');
    expect($percent)->not->toContain('0,0%');

    // Dan keterangannya menyatakan belum ada slot yang diharapkan.
    expect($html)->toContain('data-testid="coverage-no-expected-note"');
    expect(laporanEngineRoomSlice($html, 'coverage-no-expected-note'))
        ->toContain('Belum ada slot yang diharapkan');
});

// =====================================================================
// Akses, cakupan mill, dan penjagaan peran
// =====================================================================

it('skenario 22 — akun terikat mill dengan business_unit_id null: gagal tertutup, TANPA pemilih mill', function () {
    $supervisorWithoutMill = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $html = Livewire::actingAs($supervisorWithoutMill)->test(LaporanEngineRoom::class)->html();

    expect($html)->toContain('data-testid="no-mill-hint"');
    expect($html)->toContain('Akun Anda belum terhubung ke mill');
    expect($html)->toContain('Hubungi Admin');

    // GAGAL TERTUTUP: pemilih Mill TIDAK dirender sebagai gantinya, sehingga
    // daftar seluruh mill tidak pernah ditawarkan kepada peran yang memang
    // terikat satu mill.
    expect($html)->not->toContain('data-testid="mill-select"');
    expect($html)->not->toContain('data-testid="production-line-select"');
    expect($html)->not->toContain('data-testid="period-select"');

    foreach (LAPORAN_ENGINE_ROOM_FIGURE_TESTIDS as $testid) {
        $this->assertStringNotContainsString('data-testid="'.$testid.'"', $html, "testid {$testid} seharusnya TIDAK dirender");
    }
});

it('skenario 23 — id mill milik mill lain lewat properti: DIABAIKAN, tetap mill sendiri', function () {
    $recordA = laporanEngineRoomComponentRecord($this->stationA, $this->day1, 'ER-A');
    laporanEngineRoomComponentSlot($recordA, $this->slots[0], ['steam_turbine_rpm' => 3000.0]);

    $recordB = laporanEngineRoomComponentRecord($this->stationB, $this->day1, 'ER-B');
    laporanEngineRoomComponentSlot($recordB, $this->slots[0], ['steam_turbine_rpm' => 9999.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEngineRoom::class)
        ->set('businessUnitId', (string) $this->businessUnitB->id)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Angka yang dirender tetap milik mill A, dan nama mill pada hero pun
    // tetap mill A: resolvedBusinessUnitId() TIDAK membaca properti itu bagi
    // peran terikat mill.
    expect($html)->toContain('Mill Alpha');
    expect($html)->not->toContain('Mill Beta');
    expect($html)->toContain('>ER-A<');
    expect($html)->not->toContain('>ER-B<');

    // Dan tidak ada kartu metrik yang berisi angka mill B — dibaca dari sel
    // nilai kartunya, bukan dari halaman.
    expect(laporanEngineRoomSlice(laporanEngineRoomCard($html, 'steam_turbine_rpm'), 'metric-avg', '</span>'))
        ->toContain('3.000');
    expect(laporanEngineRoomSlice(laporanEngineRoomCard($html, 'steam_turbine_rpm'), 'metric-avg', '</span>'))
        ->not->toContain('9.999');
});

it('skenario 24 — id line milik mill lain lewat properti: jatuh ke BELUM MEMILIH, bukan data mill lain', function () {
    $recordB = laporanEngineRoomComponentRecord($this->stationB, $this->day1, 'ER-B');
    laporanEngineRoomComponentSlot($recordB, $this->slots[0], ['steam_turbine_rpm' => 9999.0]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanEngineRoom::class)
        ->set('productionLineId', $this->lineB);

    $html = $component->html();

    // keepProductionLineValid() membuang line yang tidak ada di dalam daftar
    // opsi mill yang berlaku; di layar, "belum memilih line" adalah keadaan
    // awal yang normal dan harus merender ajakan memilih, bukan halaman
    // galat 422.
    expect($component->get('productionLineId'))->toBe('');
    expect($html)->toContain('data-testid="select-production-line-hint"');
    expect($html)->not->toContain('data-testid="metric-card"');
    expect($html)->not->toContain('>ER-B<');
    // Nama line mill lain pun tidak pernah terbit di sini.
    expect($html)->not->toContain('data-testid="hero-production-line"');
});

it('skenario 24b — periode milik mill lain lewat properti: penolakan TERLIHAT, tanpa angka mill lain', function () {
    $recordB = laporanEngineRoomComponentRecord($this->stationB, $this->day1, 'ER-B');
    laporanEngineRoomComponentSlot($recordB, $this->slots[0], ['steam_turbine_rpm' => 9999.0]);

    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::EngineRoom->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Engine Room Beta')
        ->create();

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEngineRoom::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $periodB->id)
        ->html();

    // Ditolak SECARA TERLIHAT, bukan ditukar diam-diam: penukaran diam-diam
    // akan menjawab sebuah percobaan lintas mill dengan angka mill lain di
    // bawah id yang diminta.
    expect($html)->toContain('data-testid="forbidden-notice"');
    expect($html)->toContain('Anda tidak memiliki akses ke periode ini');
    expect($html)->not->toContain('data-testid="metric-card"');
    expect($html)->not->toContain('data-testid="coverage-percent"');
    expect($html)->not->toContain('>ER-B<');
});

it('skenario 25 / case 71 — Operator ditolak pada RUTE WEB dan pada mount() komponennya', function () {
    // Component test TIDAK melewati middleware, jadi hanya baris ini yang
    // membuktikan penjagaan rutenya ('role:supervisor,mill_management,admin')
    // — dan rute WEB ini TIDAK dilebarkan walaupun ketiga rute API datanya
    // memang menerima Operator.
    $response = $this->actingAs($this->operator, 'web')->get('/reports/engine-room');

    $response->assertStatus(403);
    $response->assertDontSee('Laporan Periode Engine Room');

    // Dan peran yang memang dibuka benar-benar sampai ke halamannya — tanpa
    // ini, 403 di atas akan tetap hijau pada rute yang menolak semua orang.
    $this->actingAs($this->supervisor, 'web')
        ->get('/reports/engine-room')
        ->assertOk()
        ->assertSee('data-testid="laporan-engine-room"', false);

    // Lapisan komponen: mount() melakukan abort_unless atas daftar perannya
    // SENDIRI (supervisor|mill_management|admin), dan penjagaan ini TIDAK
    // memanggil EngineRoomReportService::guardAccess() yang memang menerima
    // operator.
    $html = Livewire::actingAs($this->operator)->test(LaporanEngineRoom::class)->html();

    expect($html)->toContain('Forbidden');
    expect($html)->not->toContain('data-testid="laporan-engine-room"');
    expect($html)->not->toContain('data-testid="coverage"');
    expect($html)->not->toContain('data-testid="metric-card"');
});

it('case 71b — canAccess() memegang daftar perannya SENDIRI dan tidak mendelegasikan ke guardAccess()', function () {
    // BERBEDA DARI SAUDARA-SAUDARANYA, di sini bahayanya BUKAN di masa
    // depan: EngineRoomReportService::guardAccess() MENERIMA Operator HARI
    // INI, karena kembaran mobile screen-159 dibangun dalam seri yang sama.
    // Kalau canAccess() mendelegasikan ke sana, layar web ini terbuka untuk
    // Operator SEKARANG — tanpa satu galat pun dan tanpa apa pun di layar
    // yang menandainya.
    //
    // Diasersi SECARA STRUKTURAL atas badan metodenya, karena asersi
    // perilaku di atas (skenario 25) baru gagal SETELAH delegasi terjadi —
    // yaitu sesudah kebocorannya mendarat.
    $method = new ReflectionMethod(LaporanEngineRoom::class, 'canAccess');
    $source = implode('', array_slice(
        file($method->getFileName()),
        $method->getStartLine() - 1,
        $method->getEndLine() - $method->getStartLine() + 1,
    ));

    expect($source)->not->toContain('guardAccess');
    expect($source)->not->toContain('EngineRoomReportService');
    // Ketiga peran disebut di tempat, dan Operator tidak disebut sama sekali.
    expect($source)->toContain('UserRole::Supervisor->value');
    expect($source)->toContain('UserRole::MillManagement->value');
    expect($source)->toContain('UserRole::Admin->value');
    expect($source)->not->toContain('UserRole::Operator');
});

// =====================================================================
// Periode tertutup, rekap harian, baca-saja, ekspor
// =====================================================================

it('skenario 26 — periode tertutup: laporan penuh, badge Tertutup, kedua tombol ekspor tetap ada', function () {
    $closed = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::EngineRoom->value)
        ->range(now()->subDays(40)->toDateString(), now()->subDays(31)->toDateString())
        ->closed()
        ->named('Periode Tertutup')
        ->create();

    $record = laporanEngineRoomComponentRecord($this->stationA, now()->subDays(35)->toDateString(), 'ER-9');
    laporanEngineRoomComponentSlot($record, $this->slots[0], ['steam_turbine_rpm' => 3000.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEngineRoom::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $closed->id)
        ->html();

    // Badge status periode pada elemennya SENDIRI.
    expect($html)->toContain('data-testid="period-status-badge"');
    expect(laporanEngineRoomSlice($html, 'period-status-badge', '</span>'))->toContain('Tertutup');

    // Seluruh bagian laporan dirender penuh.
    expect($html)->toContain('data-testid="coverage"');
    expect(substr_count($html, 'data-testid="metric-card"'))->toBe(22);
    expect($html)->toContain('data-testid="by-unit"');
    expect($html)->toContain('data-testid="daily-table"');

    // Kunci periode mengatur PENULISAN data, bukan pembacaan laporan — jadi
    // tombolnya tidak pernah dinonaktifkan.
    expect($html)->toContain('data-testid="export-button"');
    expect($html)->toContain('data-testid="export-excel-button"');

    $tombol = laporanEngineRoomSlice($html, 'export-button', '</button>');

    expect($tombol)->not->toContain('disabled="disabled"');
});

it('skenario 27 — rekap harian 31 hari: baris per tanggal + baris total, gulir mendatar di dalam kartunya', function () {
    $long = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::EngineRoom->value)
        ->range(now()->subDays(45)->toDateString(), now()->subDays(15)->toDateString())
        ->open()
        ->named('Periode Panjang')
        ->create();

    expect((int) $long->start_date->diffInDays($long->end_date) + 1)->toBe(31);

    // Ketiga puluh satu tanggal berdata, sehingga rekapnya memuat 31 baris
    // tanggal plus satu baris total periode.
    foreach (range(0, 30) as $offset) {
        $record = laporanEngineRoomComponentRecord(
            $this->stationA,
            now()->subDays(45 - $offset)->toDateString(),
            'ER-1',
        );
        laporanEngineRoomComponentSlot($record, $this->slots[0], [
            'electrical_sync_total_factory_load_kw' => 900.0 + $offset,
            'daily_energy_export_kwh' => 1000.0 + $offset,
        ]);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanEngineRoom::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $long->id);

    $html = $component->html();

    // 31 baris tanggal + 1 baris total.
    expect(substr_count($html, 'data-testid="daily-row-'))->toBe(32);
    expect($html)->toContain('data-testid="daily-row-total"');
    expect($html)->toContain('data-testid="daily-recap-toggle"');

    // BEDA DARI TULISAN TECH SPEC, yang menyebut "aksi buka/tutup": rekap
    // harian memakai elemen <details> NATIVE, jadi membuka/menutupnya
    // terjadi sepenuhnya di peramban dan TIDAK memicu satu putaran Livewire
    // pun — itulah yang membuat klaim "tidak memicu permintaan data baru"
    // benar secara struktural.
    expect($html)->toContain('<details class="md-details" data-testid="daily-recap-details"');

    $detailsStart = strpos($html, 'data-testid="daily-recap-details"');
    $detailsEnd = strpos($html, '</details>', $detailsStart);
    $details = substr($html, $detailsStart, $detailsEnd - $detailsStart);

    expect($details)->toContain('data-testid="daily-table"');
    expect($details)->toContain('data-testid="daily-row-total"');
    // Kartu metrik, blok kelengkapan, kedua seri harian dan rekap per unit
    // berada DI LUAR elemen yang ditutup, jadi semuanya tetap terbaca pada
    // kedua keadaan.
    expect($details)->not->toContain('data-testid="metric-card"');
    expect($details)->not->toContain('data-testid="coverage-slots"');
    expect($details)->not->toContain('data-testid="daily-energy-export"');
    expect($details)->not->toContain('data-testid="by-unit-table"');

    // Petunjuk geser mendatar pada kedua seri harian, karena periodenya
    // lebih dari 10 tanggal.
    expect($html)->toContain('data-testid="daily-energy-export-scroll-hint"');
    expect($html)->toContain('data-testid="daily-trend-total-factory-load-scroll-hint"');
    expect(laporanEngineRoomSlice($html, 'by-unit-scroll-hint'))->toContain('menggulir mendatar');

    // KEDUA tabel rekap terlebar dibungkus .md-recap, dan jumlah kolomnya
    // dibandingkan dengan angka yang mock-nya setujui: rekap per unit 26
    // kolom, rekap harian 25 kolom.
    $byUnit = laporanEngineRoomSlice($html, 'by-unit-table', '</thead>');
    $daily = laporanEngineRoomSlice($html, 'daily-table', '</thead>');

    // DIHITUNG ATAS '<th scope="col">', BUKAN atas '<th': '<thead' memuat
    // '<th' dan akan ikut terhitung satu kali di setiap tabel.
    expect(substr_count($byUnit, '<th scope="col">'))->toBe(26);
    expect(substr_count($daily, '<th scope="col">'))->toBe(25);
    // 3 kolom konteks + 22 metrik + 1 kolom ekspor energi = 26 pada rekap
    // per unit; rekap harian tanpa kolom "Hari tercatat" = 25.
    expect(3 + count(EngineRoomReportService::NUMERIC_METRICS) + 1)->toBe(26);

    expect($html)->toContain('<div class="md-recap">');

    // DAN KELASNYA BENAR-BENAR TERDEFINISI PADA HASIL RENDER HALAMANNYA,
    // bukan hanya dipakai di markup: kelas tanpa definisi tampak benar di
    // sumber dan hilang di layar. Diperiksa atas halaman utuh (layout +
    // partial gaya), bukan atas berkas sumbernya.
    $page = $this->actingAs($this->supervisor, 'web')->get('/reports/engine-room?production_line_id='.$this->lineA);

    $page->assertOk();

    $rendered = $page->getContent();

    expect($rendered)->toContain('.md-recap');
    expect($rendered)->toMatch('/\.md-recap\s*\{[^}]*overflow-x:\s*auto/');
    expect($rendered)->toContain('.md-details');
    expect($rendered)->toContain('.md-scrollhint');
    expect($rendered)->toContain('.md-explain');
});

it('skenario 28 — layar hanya membaca: tidak ada form, input, maupun tombol tulis', function () {
    $record = laporanEngineRoomComponentRecord($this->stationA, $this->day1, 'ER-1');

    laporanEngineRoomComponentSlot($record, $this->slots[0], [
        'steam_turbine_rpm' => 3000.0,
        'diesel_gen_1_status' => 'run',
        'diesel_gen_2_status' => 'standby',
        'daily_energy_export_kwh' => 1200.0,
        'action_taken_maintenance_remark' => 'Ganti filter',
        'findings' => 'Getaran naik',
    ]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanEngineRoom::class)
        ->set('productionLineId', $this->lineA);

    $html = $component->html();

    // Seluruh bagian ditelusuri — kedua sebaran, kedua rekap teks, rekap per
    // unit, dan rekap harian semuanya ada.
    foreach ([
        'status-distribution-diesel-gen-1', 'status-distribution-diesel-gen-2',
        'maintenance-remarks', 'findings', 'by-unit-table', 'daily-table',
    ] as $testid) {
        $this->assertStringContainsString('data-testid="'.$testid.'"', $html);
    }

    // Tidak ada elemen form, tombol simpan/ubah/hapus, maupun input yang
    // dapat disunting: ketiga pemilihnya <select> ber-wire:model.
    expect($html)->not->toContain('<form');
    expect($html)->not->toContain('<input');
    expect($html)->not->toContain('<textarea');
    expect($html)->not->toContain('>Simpan<');
    expect($html)->not->toContain('>Ubah<');
    expect($html)->not->toContain('>Hapus<');
    expect($html)->not->toContain('>Verifikasi<');
    expect($html)->not->toContain('type="submit"');

    // Dan komponennya TIDAK mengekspos satu pun metode publik yang menulis
    // ke engine_room_records — diasersi atas DAFTAR metode publiknya, bukan
    // atas satu respons: sebuah metode baru tidak akan membuat uji lain
    // gagal.
    $publicMethods = collect((new ReflectionClass(LaporanEngineRoom::class))->getMethods(ReflectionMethod::IS_PUBLIC))
        ->filter(fn (ReflectionMethod $method) => $method->getDeclaringClass()->getName() === LaporanEngineRoom::class)
        ->map(fn (ReflectionMethod $method) => $method->getName())
        ->values()
        ->all();

    expect($publicMethods)->toBe([
        'mount', 'updatingBusinessUnitId', 'updatedBusinessUnitId', 'updatedPeriodId',
        'exportCsv', 'render',
    ]);

    // Membaca laporannya tidak menggerakkan satu baris pun.
    $recordsBefore = EngineRoomRecord::query()->count();
    $detailsBefore = EngineRoomDetail::query()->count();

    $component->set('periodId', (string) $this->periodA->id)->html();

    expect(EngineRoomRecord::query()->count())->toBe($recordsBefore);
    expect(EngineRoomDetail::query()->count())->toBe($detailsBefore);
});

it('skenario 29 — opsi periode hanya yang mencakup Engine Room', function () {
    $keduaEngineRoom = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::EngineRoom->value)
        ->range(now()->subDays(40)->toDateString(), now()->subDays(31)->toDateString())
        ->open()
        ->named('Periode Engine Room Kedua')
        ->create();

    foreach ([
        StationTypeEnum::BoilerRoom->value,
        StationTypeEnum::EffluentPlant->value,
        StationTypeEnum::StorageTank->value,
    ] as $index => $type) {
        Period::factory()
            ->forBusinessUnit($this->businessUnitA)
            ->stationType($type)
            ->range(
                now()->subDays(70 - $index * 11)->toDateString(),
                now()->subDays(63 - $index * 11)->toDateString(),
            )
            ->open()
            ->named('Periode Stasiun Lain '.$index)
            ->create();
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEngineRoom::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    $picker = laporanEngineRoomSlice($html, 'period-select', '</select>');

    // TEPAT dua option data (plus satu option '' berbunyi "Pilih Periode").
    expect(substr_count($picker, '<option value="'.$this->periodA->id.'"'))->toBe(1);
    expect(substr_count($picker, '<option value="'.$keduaEngineRoom->id.'"'))->toBe(1);
    expect($picker)->toContain('>Pilih Periode<');
    expect(substr_count($picker, '<option'))->toBe(3);

    // Ketiga periode stasiun lain TIDAK ADA di DOM sehingga tidak dapat
    // dipilih.
    expect($html)->not->toContain('Periode Stasiun Lain 0');
    expect($html)->not->toContain('Periode Stasiun Lain 1');
    expect($html)->not->toContain('Periode Stasiun Lain 2');
});

it('skenario 30 — pembacaan di tanggal mulai dan tanggal akhir muncul di rekap harian dan seri', function () {
    foreach ([$this->periodStart, $this->periodEnd] as $index => $date) {
        laporanEngineRoomComponentSlot(
            laporanEngineRoomComponentRecord($this->stationA, $date, 'ER-UJUNG-'.$index),
            $this->slots[0],
            [
                'steam_turbine_rpm' => 3000.0,
                'diesel_gen_1_status' => 'run',
                'daily_energy_export_kwh' => 1000.0 + $index,
            ],
        );
    }

    foreach ([now()->subDays(21)->toDateString(), now()->subDays(10)->toDateString()] as $index => $date) {
        laporanEngineRoomComponentSlot(
            laporanEngineRoomComponentRecord($this->stationA, $date, 'ER-LUAR-'.$index),
            $this->slots[0],
            ['steam_turbine_rpm' => 9999.0, 'daily_energy_export_kwh' => 9999.0],
        );
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEngineRoom::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Kedua record ujung ikut terhitung.
    expect(laporanEngineRoomSlice(laporanEngineRoomCard($html, 'steam_turbine_rpm'), 'metric-denominator'))
        ->toContain('>2<');
    expect(laporanEngineRoomStatusCount($html, 'status-distribution-diesel-gen-1', 'run'))->toBe('2');

    // Kolom seri dan baris rekap harian untuk KEDUA tanggal ujung ada.
    expect($html)->toContain('data-testid="daily-energy-export-col-'.$this->periodStart.'"');
    expect($html)->toContain('data-testid="daily-energy-export-col-'.$this->periodEnd.'"');
    expect($html)->toContain('data-testid="daily-row-'.$this->periodStart.'"');
    expect($html)->toContain('data-testid="daily-row-'.$this->periodEnd.'"');

    // Dan record sehari di luar masing-masing ujung TIDAK muncul di mana pun.
    expect($html)->not->toContain('>ER-LUAR-0<');
    expect($html)->not->toContain('>ER-LUAR-1<');
    expect($html)->not->toContain('data-testid="daily-row-'.now()->subDays(21)->toDateString().'"');
    expect($html)->not->toContain('data-testid="daily-row-'.now()->subDays(10)->toDateString().'"');
    expect(substr_count($html, 'data-testid="by-unit-row"'))->toBe(2);
});

it('skenario 31 / 32 — tiap kartu mencetak penyebutnya SENDIRI, berdampingan dengan angkanya', function () {
    $record = laporanEngineRoomComponentRecord($this->stationA, $this->day1, 'ER-1');

    // Kolom turbin uap pada 12 slot; kolom genset dan kelistrikan hanya pada
    // 3 — penyebut yang jauh berbeda itulah yang membuat asersi di bawah
    // bermakna.
    foreach (range(0, 11) as $index) {
        $values = ['steam_turbine_rpm' => 3000.0, 'steam_turbine_inlet_temp_c' => 200.0];

        if ($index < 3) {
            $values['diesel_gen_1_load_kw'] = 900.0;
            $values['electrical_sync_busbar_voltage_v'] = 400.0;
        }

        laporanEngineRoomComponentSlot($record, $this->slots[$index], $values);
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEngineRoom::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Setiap dari kedua puluh dua kartu memuat penyebutnya DI DALAM kartunya
    // sendiri, berdampingan dengan nilainya — tidak ada kartu yang
    // menampilkan angka tanpa jumlah pembacaannya.
    foreach (EngineRoomReportService::NUMERIC_METRICS as $column) {
        $card = laporanEngineRoomCard($html, $column);

        $this->assertStringContainsString('data-testid="metric-avg"', $card, $column);
        $this->assertStringContainsString('data-testid="metric-range"', $card, $column);
        $this->assertStringContainsString('data-testid="metric-denominator"', $card, $column);
    }

    // Penyebutnya BERBEDA-BEDA, dan metrik yang jarang diisi TIDAK mengempis
    // ke arah nol: 900 tetap 900, bukan 900 × 3 / 12 = 225.
    expect(laporanEngineRoomSlice(laporanEngineRoomCard($html, 'steam_turbine_rpm'), 'metric-denominator'))
        ->toContain('>12<');
    expect(laporanEngineRoomSlice(laporanEngineRoomCard($html, 'diesel_gen_1_load_kw'), 'metric-denominator'))
        ->toContain('>3<');

    $gensetAvg = laporanEngineRoomSlice(laporanEngineRoomCard($html, 'diesel_gen_1_load_kw'), 'metric-avg', '</span>');

    expect($gensetAvg)->toContain('900');
    expect($gensetAvg)->not->toContain('225');
});

it('skenario 33 — tidak ada satu pun KELAS penanda di luar batas pada HTML ter-render', function () {
    $record = laporanEngineRoomComponentRecord($this->stationA, $this->day1, 'ER-1');

    // Nilai SENGAJA jauh dari nominal yang dikenal luas DI LUAR aplikasi.
    laporanEngineRoomComponentSlot($record, $this->slots[0], [
        'electrical_sync_system_frequency_hz' => 43.0,
        'electrical_sync_power_factor' => 0.35,
        'electrical_sync_busbar_voltage_v' => 310.0,
    ]);
    laporanEngineRoomComponentSlot($record, $this->slots[1], [
        'electrical_sync_system_frequency_hz' => 57.0,
        'electrical_sync_power_factor' => 0.99,
        'electrical_sync_busbar_voltage_v' => 520.0,
    ]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEngineRoom::class)
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

    // Ketiga nilai dirender dengan gaya netral yang sama, dan nilainya apa
    // adanya.
    expect(laporanEngineRoomSlice(laporanEngineRoomCard($html, 'electrical_sync_power_factor'), 'metric-range'))
        ->toContain('0,350');

    // Dan ketiadaannya DINYATAKAN UTUH — ketiadaan penandaan yang tidak
    // dijelaskan terbaca sebagai fitur yang belum selesai, dan seseorang akan
    // "melengkapinya".
    $note = laporanEngineRoomSlice($html, 'no-threshold-note', '</div>');

    expect($note)->toContain('tidak punya tabel master target');
    expect($note)->toContain('tidak menurunkan satu pun ambang');
    // Frasanya terpotong baris pada blade, jadi dicocokkan dengan regex
    // yang menoleransi spasi — bukan dengan substring yang akan gagal karena
    // pemformatan sumbernya, bukan karena isinya.
    expect($note)->toMatch('/batas\s+keselamatan\s+kelistrikan/');

    // Dan keterangan itu dirender DI DALAM blok Kelistrikan Tersinkron,
    // bukan sebagai catatan kaki halaman.
    $block = laporanEngineRoomSlice($html, 'metric-block-electrical-sync', '</section>');

    expect($block)->toContain('data-testid="no-threshold-note"');
});

it('skenario 34 — tidak ada bagian downtime: diasersi atas TESTID dan JUDUL, bukan atas substring', function () {
    $record = laporanEngineRoomComponentRecord($this->stationA, $this->day1, 'ER-1');

    laporanEngineRoomComponentSlot($record, $this->slots[0], array_fill_keys(
        array_merge(EngineRoomReportService::NUMERIC_METRICS, ['daily_energy_export_kwh']),
        7.5,
    ));

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEngineRoom::class)
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
    expect($html)->toContain('data-testid="no-downtime-note"');
    expect(laporanEngineRoomSlice($html, 'no-downtime-note', '</div>'))
        ->toContain('tidak mencatat lama berhenti sama sekali');
});

it('skenario 35 — ekspor CSV dan Excel mengalirkan berkas; keduanya terkunci selama penyiapan', function () {
    $record = laporanEngineRoomComponentRecord($this->stationA, $this->day1, 'ER-9');
    laporanEngineRoomComponentSlot($record, $this->slots[0], [
        'steam_turbine_rpm' => 3000.0,
        'diesel_gen_1_status' => 'standby',
    ]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanEngineRoom::class)
        ->set('productionLineId', $this->lineA);

    $csv = $component->call('exportCsv', 'csv');
    $csv->assertFileDownloaded();

    $excel = $component->call('exportCsv', 'excel');
    $excel->assertFileDownloaded();

    // Selama penyiapan KEDUA tombol dinonaktifkan (tidak ada unduhan ganda)
    // dan tombol yang diklik menampilkan 'Mengekspor…'.
    $html = $component->html();

    foreach (['export-button', 'export-excel-button'] as $testid) {
        $tombol = laporanEngineRoomSlice($html, $testid, '</button>');

        $this->assertStringContainsString('wire:loading.attr="disabled"', $tombol, $testid);
        $this->assertStringContainsString('wire:target="exportCsv"', $tombol, $testid);
        $this->assertStringContainsString('Mengekspor', $tombol, $testid);
    }
});

it('skenario 35b — ekspor tanpa line atau tanpa periode terpilih tidak menghasilkan apa pun', function () {
    $record = laporanEngineRoomComponentRecord($this->stationA, $this->day1, 'ER-9');
    laporanEngineRoomComponentSlot($record, $this->slots[0], ['steam_turbine_rpm' => 3000.0]);

    // LINE DIJAGA DI METODE, bukan hanya di blade: panggilan langsung ke
    // metode ini tidak boleh pernah mengambil berkas yang mencampur seluruh
    // line mill.
    $tanpaLine = Livewire::actingAs($this->supervisor)
        ->test(LaporanEngineRoom::class)
        ->call('exportCsv', 'csv');

    expect($tanpaLine->effects)->not->toHaveKey('download');

    // DAN tanpa periode. Perhatikan bentuknya: men-set periodId menjadi ''
    // TIDAK cukup, karena render berikutnya MEMILIH ULANG periode terbaru
    // (keepSelectionValid). Keadaan "tanpa periode" yang sah adalah mill yang
    // tidak punya satu pun periode Engine Room.
    $supervisorB = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitB)->create();

    $tanpaPeriode = Livewire::actingAs($supervisorB)
        ->test(LaporanEngineRoom::class)
        ->set('productionLineId', $this->lineB)
        ->call('exportCsv', 'csv');

    expect($tanpaPeriode->get('periodId'))->toBe('');
    expect($tanpaPeriode->effects)->not->toHaveKey('download');
});

// =====================================================================
// Pintu masuk dari Laporan Stasiun (screen-140)
// =====================================================================

it('skenario 36 — tile Engine Room pada Laporan Stasiun AKTIF dan menunjuk ke rute laporan ini', function () {
    // SATU BARIS yang menentukan layar ini dapat dicapai: entri 'engine-room'
    // pada StationReportService::REPORT_ROUTES. Tanpa asersi ini, laporannya
    // bisa lengkap, seluruh test backend hijau, dan tile-nya tetap kelabu —
    // pola kegagalan yang sudah pernah terjadi pada laporan Cages & Tracks
    // versi web.
    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanStasiun::class)
        ->set('productionLineId', $this->lineA)
        ->assertSeeHtml('data-testid="station-grid"')
        ->assertSeeHtml('data-testid="station-tile-engine-room"')
        ->html();

    $pattern = '/<(a|span)\b[^>]*data-testid="station-tile-engine-room"[^>]*>.*?<\/\1>/s';

    expect(preg_match($pattern, $html, $matches))->toBe(1);

    $tile = $matches[0];

    expect($tile)->toContain('href=');
    expect($tile)->toContain('/reports/engine-room');
    expect($tile)->not->toContain('disabled');
    // Mill DAN line ikut terbawa, sehingga layar tujuan tidak meminta
    // memilih lagi — itu sebabnya kedua properti layar ini membawa
    // #[Url(as: ...)] dengan kunci yang eksplisit.
    expect($tile)->toContain('business_unit_id='.$this->businessUnitA->id);
    expect($tile)->toContain('production_line_id='.$this->lineA);
});
