<?php

/**
 * LaporanEffluentPlantTest (Feature/Livewire) — screen-156--laporan-effluent-plant-web,
 * halaman webnya sendiri.
 *
 * Satu uji per entri `test_scenarios[].component_test` pada tech spec
 * screen-156, ditambah tiga kasus unit yang memang menyangkut komponen ini
 * (case 63, 64, 65) dan satu asersi pintu masuk dari screen-140.
 *
 * LAPISAN INI ADA UNTUK KEADAAN YANG TIDAK BISA DIATUR DI TEMPAT LAIN.
 * Angkanya sudah dibuktikan dua kali terhadap data yang dikendalikan penuh —
 * atas service-nya di tests/Unit/Services/EffluentPlantReportServiceTest.php
 * dan atas kontrak HTTP-nya di tests/Feature/Api/LaporanEffluentPlantTest.php
 * — jadi berkas ini TIDAK mengulanginya. Yang dibuktikan di sini adalah apa
 * yang HALAMAN lakukan terhadap angka itu: pemilih mana yang ada untuk peran
 * mana, keadaan kosong mana yang digambar, dan hal-hal yang layar ini tidak
 * boleh biarkan terbaca salah.
 *
 * Dan — ini sebab utamanya — TUJUH KEADAAN yang database e2e bersama tidak
 * bisa menyusunnya, karena setiap test di sini punya databasenya sendiri:
 * mill tanpa satu pun periode Effluent Plant, periode berisi record tetapi
 * NOL slot terisi, periode yang belum mulai (slot harapan nol), periode yang
 * masih berjalan, nilai laboratorium yang tidak pernah diisi sepanjang
 * periode, akun terikat mill dengan business_unit_id null, dan id mill/line
 * milik mill lain yang disuapkan lewat properti komponen. Spec browser
 * screen-156 sengaja tidak mencoba satu pun dari itu.
 *
 * DIASERSI ATAS HTML TER-RENDER, bukan atas state komponen, di mana pun
 * klaimnya tentang apa yang pembaca lihat. Sebuah data-testid yang ada di
 * markup tetapi tidak pernah dicapai oleh kondisional blade akan tetap
 * meloloskan asersi atas state.
 *
 * SATU HAL STRUKTURAL IKUT DIJAGA DI SINI: canAccess() memegang daftar
 * perannya SENDIRI dan tidak mendelegasikan ke
 * EffluentPlantReportService::guardAccess(). Service itu MENOLAK Operator
 * hari ini, tetapi screen-157 (kembaran mobile) akan melebarkannya — dan
 * kalau canAccess() mendelegasikan, layar web ini terbuka untuk Operator
 * dalam tarikan napas yang sama, tanpa satu galat pun dan tanpa apa pun di
 * layar yang menandainya.
 *
 * DUA TEMPAT KOMPONENNYA BERBEDA DARI TULISAN TECH SPEC, masing-masing
 * diberi komentar di tempatnya: rekap harian memakai elemen <details> native
 * alih-alih state komponen (skenario 23), dan label ketiga kolom status pada
 * TABEL SEBARAN dan BERKAS EKSPOR memakai label yang SAMA sejak 2026-10-14
 * (On/Off/Fault, Run/Stop lewat Display::OPTION_LABELS) — layar Detail
 * (On/Off/Fault, Run/Stop) — dua pembaca yang berbeda, dua konvensi yang
 * memang berbeda.
 */

use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Livewire\Dashboard\LaporanEffluentPlant;
use App\Livewire\Dashboard\LaporanStasiun;
use App\Models\BusinessUnit;
use App\Models\EffluentPlantDetail;
use App\Models\EffluentPlantRecord;
use App\Models\Period;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use App\Services\EffluentPlantRecordService;
use App\Services\EffluentPlantReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Tiap data-testid yang HANYA ada setelah sebuah angka benar-benar dirender —
 * yaitu setelah mill, production line DAN periode terpilih.
 */
const LAPORAN_EFFLUENT_PLANT_FIGURE_TESTIDS = [
    'coverage',
    'coverage-slots',
    'coverage-percent',
    'coverage-formula',
    'coverage-note',
    // Keenam blok metrik — pengelompokannya murni tata letak, tetapi
    // keenamnya harus benar-benar digambar.
    'metric-block-anaerobic-pond-1',
    'metric-block-anaerobic-pond-2',
    'metric-block-cooling-pond',
    'metric-block-flow',
    'metric-block-chemical',
    'metric-block-final-discharge',
    'metric-card',
    'metric-denominator',
    'environmental-compliance-note',
    // KETIGA sebaran status, masing-masing kartunya sendiri.
    'status-distribution-biogas-flare',
    'status-distribution-dosing-pump-1',
    'status-distribution-sludge-dewatering',
    'status-distribution-note',
    'biogas-flare-fault-count',
    // Pernyataan ketiadaan blok downtime — bagian yang hilang tanpa
    // keterangan tidak dapat dibedakan dari bagian yang gagal dimuat.
    'no-downtime-note',
    // DUA bagian teks bebas, bukan satu.
    'maintenance-remarks',
    'findings',
];

/** Testid yang baru ada ketika periodenya BENAR-BENAR punya slot terisi. */
const LAPORAN_EFFLUENT_PLANT_DATA_TESTIDS = [
    'daily-trend-raw-pome-feed',
    'daily-trend-final-discharge-ph',
    'by-unit',
    'by-unit-table',
    'daily-recap-details',
    'daily-table',
    'completeness',
    'days-with-records',
    'total-reading-rows',
];

function laporanEffluentPlantComponentRecord(
    Station $station,
    string $date,
    string $effluentPlantId = 'EP-1',
    array $attributes = [],
): EffluentPlantRecord {
    return EffluentPlantRecord::factory()
        ->forStation($station)
        ->onDate($date)
        ->create(array_merge(['effluent_plant_id' => $effluentPlantId, 'note' => 'Catatan harian'], $attributes));
}

function laporanEffluentPlantComponentSlot(
    EffluentPlantRecord $record,
    string $timeSlot,
    array $values = [],
): EffluentPlantDetail {
    return EffluentPlantDetail::factory()
        ->forRecord($record)
        ->timeSlot($timeSlot)
        ->create($values);
}

/**
 * Isi satu elemen yang dikenali data-testid-nya, dipotong dari HTML.
 *
 * Dipakai setiap kali klaimnya tentang satu sel tertentu: "tidak ada teks nol
 * persen di mana pun pada halaman" akan selalu gagal kalau diasersi atas
 * seluruh halaman, karena keterangan cakupan dan sebaran status memuat banyak
 * persentase. Yang benar adalah memeriksa SEL-nya.
 */
function laporanEffluentPlantComponentSlice(string $html, string $testid, string $closing = '</p>'): string
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
 * KARTU, bukan baris tabel: laporan ini merender keempat belas metrik sebagai
 * kartu di dalam enam blok tata letak, dan tiap kartu HARUS mencetak
 * penyebutnya sendiri.
 */
function laporanEffluentPlantComponentCard(string $html, string $column): string
{
    $start = strpos($html, 'data-metric="'.$column.'"');

    if ($start === false) {
        return '';
    }

    $end = strpos($html, '</article>', $start);

    return $end === false ? substr($html, $start) : substr($html, $start, $end - $start);
}

/** Potongan satu baris <tr data-testid="status-row"> untuk satu nilai. */
function laporanEffluentPlantComponentStatusRow(string $html, string $testid, string $value): string
{
    $card = laporanEffluentPlantComponentSlice($html, $testid, '</table>');
    $start = strpos($card, 'data-status-value="'.$value.'"');

    if ($start === false) {
        return '';
    }

    $end = strpos($card, '</tr>', $start);

    return $end === false ? substr($card, $start) : substr($card, $start, $end - $start);
}

/** Tanggal seperti yang dicetak blade: '07 Okt 2026'. */
function laporanEffluentPlantComponentTanggal(string $date): string
{
    $bulan = ['01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr', '05' => 'Mei', '06' => 'Jun',
        '07' => 'Jul', '08' => 'Agu', '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'];

    [$y, $m, $d] = array_pad(explode('-', substr($date, 0, 10)), 3, '');

    return $d.' '.($bulan[$m] ?? $m).' '.$y;
}

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->effluentPlant()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->effluentPlant()->create();

    $this->lineA = (string) $this->stationA->production_line_id;
    $this->lineB = (string) $this->stationB->production_line_id;

    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    // TANGGAL RELATIF SAJA — ada pemindai statis untuk tanggal fixture yang
    // basi. Periode yang SUDAH SELESAI: 10 hari, days_counted === days_in_period.
    $this->periodStart = now()->subDays(20)->toDateString();
    $this->periodEnd = now()->subDays(11)->toDateString();

    $this->day1 = now()->subDays(19)->toDateString();
    $this->day2 = now()->subDays(18)->toDateString();
    $this->day3 = now()->subDays(17)->toDateString();

    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::EffluentPlant->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Effluent Plant Alpha')
        ->create();

    $this->slots = EffluentPlantRecordService::canonicalTimeSlots();
});

// =====================================================================
// Jalur sukses
// =====================================================================

it('skenario 1 — Supervisor: tanpa pemilih mill, seluruh bagian laporan terender', function () {
    $record = laporanEffluentPlantComponentRecord($this->stationA, $this->day1);

    laporanEffluentPlantComponentSlot($record, $this->slots[0], [
        'anaerobic_pond_1_ph' => 7.2,
        'cooling_pond_temp_c' => 41.0,
        'raw_pome_feed_rate_m3h' => 30.0,
        'final_discharge_ph' => 7.4,
        'biogas_flare_status' => 'on',
        'remarks_maintenance_actions' => 'Ganti seal pompa',
        'findings' => 'Busa tebal di kolam 2',
    ]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEffluentPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Peran terikat mill tidak melihat pemilih mill — menawarkan pemilih yang
    // tidak bisa ia pakai adalah kebohongan kecil yang mahal.
    expect($html)->not->toContain('data-testid="mill-select"');
    expect($html)->toContain('data-testid="mill-name"');
    expect($html)->toContain('Mill Alpha');

    foreach (LAPORAN_EFFLUENT_PLANT_FIGURE_TESTIDS as $testid) {
        $this->assertStringContainsString('data-testid="'.$testid.'"', $html, "testid {$testid} tidak dirender");
    }

    foreach (LAPORAN_EFFLUENT_PLANT_DATA_TESTIDS as $testid) {
        $this->assertStringContainsString('data-testid="'.$testid.'"', $html, "testid {$testid} tidak dirender");
    }

    // KEEMPAT BELAS kartu metrik, masing-masing dengan penyebutnya sendiri.
    expect(substr_count($html, 'data-testid="metric-card"'))->toBe(14);
    expect(substr_count($html, 'data-testid="metric-denominator"'))->toBe(14);
    expect(count(EffluentPlantReportService::NUMERIC_METRICS))->toBe(14);

    foreach (EffluentPlantReportService::NUMERIC_METRICS as $column) {
        $this->assertStringContainsString('data-metric="'.$column.'"', $html, "kartu metrik {$column} tidak dirender");
    }

    // Tiap kartu mencetak terendah/rata-rata/tertinggi beserta jumlah
    // pembacaannya.
    expect(substr_count($html, 'data-testid="metric-avg"'))->toBe(14);
    expect(substr_count($html, 'data-testid="metric-range"'))->toBe(14);

    // KETIGA sebaran status, DUA rekap teks bebas yang terpisah, DUA tren
    // harian.
    expect(substr_count($html, 'data-testid="status-row"'))->toBe(3 + 2 + 2);
    expect(substr_count($html, 'data-testid="status-not-recorded"'))->toBe(3);
    expect($html)->toContain('Rekap Catatan Perawatan');
    expect($html)->toContain('Rekap Temuan');
    expect($html)->toContain('Ganti seal pompa');
    expect($html)->toContain('Busa tebal di kolam 2');
    expect($html)->toContain('Tren Harian Laju POME Masuk');
    expect($html)->toContain('Tren Harian pH Buangan Akhir');

    // Kedua tombol ekspor tampil.
    expect($html)->toContain('data-testid="export-button"');
    expect($html)->toContain('data-testid="export-excel-button"');

    // Bacaan saja: tidak ada satu pun kontrol tulis.
    expect($html)->not->toContain('>Simpan<');
    expect($html)->not->toContain('>Hapus<');
    expect($html)->not->toContain('>Verifikasi<');
});

it('skenario 1b — Mill Management melihat layar yang sama persis dengan Supervisor', function () {
    $record = laporanEffluentPlantComponentRecord($this->stationA, $this->day1);
    laporanEffluentPlantComponentSlot($record, $this->slots[0], ['anaerobic_pond_1_ph' => 7.2]);

    $html = Livewire::actingAs($this->millManagement)
        ->test(LaporanEffluentPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->not->toContain('data-testid="mill-select"');
    expect($html)->toContain('data-testid="mill-name"');
    expect($html)->toContain('data-testid="coverage"');
    expect(substr_count($html, 'data-testid="metric-card"'))->toBe(14);
});

it('skenario 2 — Admin: pemilih mill dirender, dan tanpa mill tidak ada satu angka pun', function () {
    $record = laporanEffluentPlantComponentRecord($this->stationA, $this->day1);
    laporanEffluentPlantComponentSlot($record, $this->slots[0], ['anaerobic_pond_1_ph' => 7.2]);

    $component = Livewire::actingAs($this->admin)->test(LaporanEffluentPlant::class);

    $html = $component->html();

    expect($html)->toContain('data-testid="mill-select"');
    expect($html)->toContain('>Pilih Mill<');
    expect($html)->toContain('data-testid="mill-select-hint"');

    $withMill = $component
        ->set('businessUnitId', (string) $this->businessUnitA->id)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($withMill)->toContain('data-testid="coverage"');
    expect(substr_count($withMill, 'data-testid="metric-card"'))->toBe(14);
    // Daftar periode yang dimuat milik mill yang dipilih.
    expect($withMill)->toContain('Periode Effluent Plant Alpha');
    expect($withMill)->toContain('data-testid="export-button"');
    expect($withMill)->toContain('data-testid="export-excel-button"');
});

it('skenario 3 — Admin tanpa memilih mill: arahan memilih mill, pemilih periode tidak terisi opsi', function () {
    $html = Livewire::actingAs($this->admin)->test(LaporanEffluentPlant::class)->html();

    expect($html)->toContain('data-testid="mill-select-hint"');
    expect($html)->toContain('Pilih mill terlebih dahulu');
    expect($html)->toContain('>Pilih Mill<');

    // Tidak ada satu angka laporan pun, dan pemilih periode belum dirender
    // sama sekali (showPeriod = false selama mill belum dipilih).
    foreach (LAPORAN_EFFLUENT_PLANT_FIGURE_TESTIDS as $testid) {
        $this->assertStringNotContainsString('data-testid="'.$testid.'"', $html, "testid {$testid} seharusnya TIDAK dirender");
    }

    expect($html)->not->toContain('data-testid="period-select"');
    expect($html)->not->toContain('data-testid="export-button"');
});

it('skenario 4 — Production Line belum dipilih: arahan memilih line, NOL angka, tanpa tombol ekspor', function () {
    $record = laporanEffluentPlantComponentRecord($this->stationA, $this->day1);
    laporanEffluentPlantComponentSlot($record, $this->slots[0], ['anaerobic_pond_1_ph' => 7.2]);

    // Mill punya DUA line, dan tidak satu pun dipilih otomatis.
    ProductionLine::factory()->create(['business_unit_id' => $this->businessUnitA->id, 'name' => 'Line Kedua']);

    $html = Livewire::actingAs($this->supervisor)->test(LaporanEffluentPlant::class)->html();

    expect($html)->toContain('data-testid="select-production-line-hint"');
    expect($html)->toContain('>Pilih Line<');

    // TIDAK ADA satu angka pun — termasuk TIDAK ADA angka agregat seluruh
    // mill sebagai penggantinya.
    foreach (LAPORAN_EFFLUENT_PLANT_FIGURE_TESTIDS as $testid) {
        $this->assertStringNotContainsString('data-testid="'.$testid.'"', $html, "testid {$testid} seharusnya TIDAK dirender");
    }

    $hint = laporanEffluentPlantComponentSlice($html, 'select-production-line-hint', '</div>');

    expect($hint)->toContain('tidak menampilkan angka seluruh mill');

    // Tombol ekspor BELUM DIRENDER — tombol yang ada tetapi tidak berguna
    // adalah tombol yang akan diklik.
    expect($html)->not->toContain('data-testid="export-button"');
    expect($html)->not->toContain('data-testid="export-excel-button"');
});

it('skenario 5 / case 65 — mengganti mill MENGOSONGKAN periode DAN production line', function () {
    $record = laporanEffluentPlantComponentRecord($this->stationA, $this->day1);
    laporanEffluentPlantComponentSlot($record, $this->slots[0], ['anaerobic_pond_1_ph' => 7.2]);

    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::EffluentPlant->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Effluent Plant Beta')
        ->create();

    $component = Livewire::actingAs($this->admin)
        ->test(LaporanEffluentPlant::class)
        ->set('businessUnitId', (string) $this->businessUnitA->id)
        ->set('productionLineId', $this->lineA);

    // Periode terbaru terpilih otomatis pada muat pertama, jadi laporannya
    // sudah tampil sebelum mill diganti.
    expect($component->html())->toContain('data-testid="coverage"');
    expect($component->get('periodId'))->not->toBe('');

    // Sebuah periode MAUPUN sebuah line milik tepat satu mill, jadi
    // membawanya ke mill lain akan menampilkan galat untuk sesuatu yang tidak
    // dilakukan pengguna (temuan QA LP-W-09).
    $afterSwitch = $component->set('businessUnitId', (string) $this->businessUnitB->id);

    expect($afterSwitch->get('periodId'))->toBe('');
    expect($afterSwitch->get('productionLineId'))->toBe('');

    $html = $afterSwitch->html();

    expect($html)->toContain('>Pilih Periode<');
    expect($html)->toContain('>Pilih Line<');
    expect($html)->toContain('data-testid="select-production-line-hint"');
    // Periode mill LAMA tidak muncul lagi pada daftar.
    expect($html)->not->toContain('Periode Effluent Plant Alpha');
    expect($html)->toContain('Periode Effluent Plant Beta');

    foreach (LAPORAN_EFFLUENT_PLANT_FIGURE_TESTIDS as $testid) {
        $this->assertStringNotContainsString('data-testid="'.$testid.'"', $html, "testid {$testid} seharusnya TIDAK dirender");
    }
});

it('skenario 6 — mill tanpa SATU PUN periode Effluent Plant: arahan menghubungi Admin, tanpa angka', function () {
    $supervisorB = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitB)->create();

    // Mill B hanya punya periode berjenis stasiun LAIN — pemilih periodenya
    // sengaja tetap kosong dan TIDAK diisi periode jenis lain.
    Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::KernelPlant->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Kernel Plant Beta')
        ->create();

    $html = Livewire::actingAs($supervisorB)
        ->test(LaporanEffluentPlant::class)
        ->set('productionLineId', $this->lineB)
        ->html();

    expect($html)->toContain('data-testid="no-period-hint"');
    expect($html)->toContain('Belum ada Periode Pelaporan');
    expect($html)->toContain('Hubungi Admin');
    expect($html)->not->toContain('Periode Kernel Plant Beta');
    // Pemilih periode tidak punya opsi selain yang kosong.
    expect($html)->not->toContain('>Pilih Periode<');

    foreach (LAPORAN_EFFLUENT_PLANT_FIGURE_TESTIDS as $testid) {
        $this->assertStringNotContainsString('data-testid="'.$testid.'"', $html, "testid {$testid} seharusnya TIDAK dirender");
    }
});

// =====================================================================
// Keadaan kosong per metrik
// =====================================================================

it('skenario 7 — periode tanpa data: keempat belas kartu "tidak tersedia" dengan 0 pembacaan, tanpa grafik kosong', function () {
    $record = laporanEffluentPlantComponentRecord($this->stationA, $this->day1);
    laporanEffluentPlantComponentSlot($record, $this->slots[0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEffluentPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->toContain('data-testid="empty-state"');
    expect($html)->toContain('Belum ada data pada periode dan line ini');

    // KARTUNYA TETAP DIGAMBAR keempat belas — kartu yang hilang terbaca
    // sebagai "parameter itu tidak ada", padahal yang benar adalah "tidak
    // ada yang mengukurnya".
    expect(substr_count($html, 'data-testid="metric-card"'))->toBe(14);

    foreach (EffluentPlantReportService::NUMERIC_METRICS as $column) {
        $card = laporanEffluentPlantComponentCard($html, $column);

        // 'tidak tersedia', BUKAN '0': nol berarti terukur dan hasilnya nol.
        $this->assertStringContainsString('tidak tersedia', $card, "kartu {$column}");
        $this->assertStringContainsString('dari <b>0</b> pembacaan', $card, "kartu {$column}");
    }

    // Grafik kosong terbaca sebagai garis datar hasil pengukuran, jadi kedua
    // tren, rekap per unit dan rekap harian TIDAK digambar.
    foreach (LAPORAN_EFFLUENT_PLANT_DATA_TESTIDS as $testid) {
        $this->assertStringNotContainsString('data-testid="'.$testid.'"', $html, "testid {$testid} seharusnya TIDAK dirender");
    }
});

it('skenario 8 — satu metrik kosong sepanjang periode, metrik lain tetap berangka dengan penyebutnya', function () {
    $record = laporanEffluentPlantComponentRecord($this->stationA, $this->day1);

    foreach (range(0, 2) as $index) {
        laporanEffluentPlantComponentSlot($record, $this->slots[$index], [
            'anaerobic_pond_1_ph' => 7.2,
            'cooling_pond_temp_c' => 40.0 + $index,
        ]);
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEffluentPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    $kosong = laporanEffluentPlantComponentCard($html, 'chemical_consumed_kgl');

    expect($kosong)->toContain('tidak tersedia');
    expect($kosong)->toContain('dari <b>0</b> pembacaan');

    $terisi = laporanEffluentPlantComponentCard($html, 'cooling_pond_temp_c');

    expect($terisi)->not->toContain('tidak tersedia');
    expect($terisi)->toContain('dari <b>3</b> pembacaan');
    expect($terisi)->toContain('41,0');

    $ph = laporanEffluentPlantComponentCard($html, 'anaerobic_pond_1_ph');

    expect($ph)->toContain('dari <b>3</b> pembacaan');
});

it('skenario 9 — nilai laboratorium tidak pernah diisi: ketiga kartu bertanda lab, 0 pembacaan, keterangan di blok yang sama', function () {
    $nonLab = array_values(array_diff(
        EffluentPlantReportService::NUMERIC_METRICS,
        EffluentPlantReportService::LAB_METRICS,
    ));

    $record = laporanEffluentPlantComponentRecord($this->stationA, $this->day1);

    foreach ($this->slots as $slot) {
        laporanEffluentPlantComponentSlot($record, $slot, array_fill_keys($nonLab, 7.0));
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEffluentPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // TEPAT TIGA penanda lab, diturunkan dari is_lab pada respons dan tidak
    // dipaku di markup.
    expect(substr_count($html, 'data-testid="metric-lab-badge"'))->toBe(3);

    foreach (EffluentPlantReportService::LAB_METRICS as $column) {
        $card = laporanEffluentPlantComponentCard($html, $column);

        $this->assertStringContainsString('data-testid="metric-lab-badge"', $card, "kartu {$column}");
        $this->assertStringContainsString('nilai laboratorium', $card, "kartu {$column}");
        $this->assertStringContainsString('tidak tersedia', $card, "kartu {$column}");
        $this->assertStringContainsString('dari <b>0</b> pembacaan', $card, "kartu {$column}");
    }

    // pH buangan akhir TETAP berangka, dengan penyebut 24 — itulah yang
    // membuat perbedaan penyebut terlihat di blok yang sama.
    $phBuangan = laporanEffluentPlantComponentCard($html, 'final_discharge_ph');

    expect($phBuangan)->not->toContain('data-testid="metric-lab-badge"');
    expect($phBuangan)->toContain('dari <b>24</b> pembacaan');

    // KETERANGANNYA DI BLOK YANG SAMA dengan angkanya, bukan di catatan kaki
    // halaman.
    $blok = substr(
        $html,
        strpos($html, 'data-testid="metric-block-final-discharge"'),
        strpos($html, 'data-testid="status-distribution-biogas-flare"')
            - strpos($html, 'data-testid="metric-block-final-discharge"'),
    );

    expect($blok)->toContain('data-testid="environmental-compliance-note"');
    expect($blok)->toContain('Penyebut ketiga nilai laboratorium memang jauh lebih');
    // Tanpa nada kegagalan pencatatan: keadaan ini NORMAL.
    expect($blok)->toContain('keadaan');
    expect($blok)->toContain('normal');
});

// =====================================================================
// Sebaran status
// =====================================================================

it('skenario 10 — setiap sebaran merender baris "Tidak tercatat" TERPISAH dengan jumlahnya sendiri', function () {
    $record = laporanEffluentPlantComponentRecord($this->stationA, $this->day1);

    laporanEffluentPlantComponentSlot($record, $this->slots[0], ['biogas_flare_status' => 'on']);
    laporanEffluentPlantComponentSlot($record, $this->slots[1], ['biogas_flare_status' => 'on']);
    laporanEffluentPlantComponentSlot($record, $this->slots[2], ['biogas_flare_status' => 'fault']);

    foreach (range(3, 7) as $index) {
        laporanEffluentPlantComponentSlot($record, $this->slots[$index], ['anaerobic_pond_1_ph' => 7.0]);
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEffluentPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    $kartu = laporanEffluentPlantComponentSlice($html, 'status-distribution-biogas-flare', '</table>');

    // LABEL SAMA DENGAN LAYAR DETAIL: On / Off / Fault — satu nilai, satu
    // label di seluruh produk. Layar ini, blade-nya, formulir, layar Detail,
    // dan kedua berkas ekspor semuanya bersandar pada Display::OPTION_LABELS,
    // dan blade laporan MENDELEGASIKAN ke Display::option() alih-alih
    // menyalin petanya — jadi penyimpangan yang sempat ada (Nyala/Mati/
    // Gangguan, hanya di layar laporan) tidak dapat terulang tanpa menyentuh
    // Display sendiri.
    expect($kartu)->toContain('>On<');
    expect($kartu)->toContain('>Off<');
    expect($kartu)->toContain('>Fault<');
    expect($kartu)->toContain('>Tidak tercatat<');

    $mati = laporanEffluentPlantComponentStatusRow($html, 'status-distribution-biogas-flare', 'off');
    $tidakTercatat = laporanEffluentPlantComponentSlice($html, 'status-not-recorded', '</tr>');

    // Jumlah 'Off' TIDAK sama dengan jumlah slot kosong: melipatnya akan
    // melaporkan flare mati yang tidak pernah terjadi.
    expect($mati)->toContain('<td>0</td>');
    expect($tidakTercatat)->toContain('<td>5</td>');

    // Seluruh nilai yang dikenal tetap dirender beserta jumlahnya, pada
    // ketiga sebaran.
    expect(substr_count($html, 'data-testid="status-row"'))->toBe(7);
    expect(substr_count($html, 'data-testid="status-not-recorded"'))->toBe(3);
    expect(substr_count($html, 'data-testid="status-recorded-total"'))->toBe(3);
});

it('skenario 11 — nilai status dikenal yang tidak pernah muncul tetap dirender dengan jumlah 0', function () {
    $record = laporanEffluentPlantComponentRecord($this->stationA, $this->day1);

    foreach (range(0, 3) as $index) {
        laporanEffluentPlantComponentSlot($record, $this->slots[$index], ['biogas_flare_status' => 'on']);
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEffluentPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Baris 'Fault' TETAP ada dengan 0 — dan berbeda jelas dari baris
    // 'Tidak tercatat', sehingga "tidak pernah terjadi" terbaca lain dari
    // "tidak dilaporkan".
    $gangguan = laporanEffluentPlantComponentStatusRow($html, 'status-distribution-biogas-flare', 'fault');

    expect($gangguan)->not->toBe('');
    expect($gangguan)->toContain('>Fault<');
    expect($gangguan)->toContain('<td>0</td>');

    $tidakTercatat = laporanEffluentPlantComponentSlice($html, 'status-not-recorded', '</tr>');

    expect($tidakTercatat)->toContain('<td>0</td>');

    // Dan jumlah slot bergangguan DITONJOLKAN tersendiri — penonjolan itu
    // BUKAN penandaan di luar batas: ia menghitung sebuah nilai yang memang
    // tercatat, tidak menilai angka ukur terhadap sebuah ambang.
    $fault = laporanEffluentPlantComponentSlice($html, 'biogas-flare-fault-count', '</p>');

    expect($fault)->toContain('0 slot bergangguan');
    expect($fault)->not->toContain('is-danger');
    expect($fault)->not->toContain('md-threshold');
});

// =====================================================================
// Dua rekap teks bebas
// =====================================================================

it('skenario 12 — satu slot mengisi kedua kolom teks: dirender SEKALI di tiap rekap', function () {
    $record = laporanEffluentPlantComponentRecord($this->stationA, $this->day3);

    laporanEffluentPlantComponentSlot($record, '08:00', [
        'remarks_maintenance_actions' => 'Kalibrasi pompa dosing',
        'findings' => 'pH turun mendadak',
    ]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEffluentPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // SATU baris di tiap bagian, dengan tanggal dan slot waktunya.
    expect(substr_count($html, 'data-testid="maintenance-remark-row"'))->toBe(1);
    expect(substr_count($html, 'data-testid="finding-row"'))->toBe(1);

    $tanggal = laporanEffluentPlantComponentTanggal($this->day3);

    $perawatan = laporanEffluentPlantComponentSlice($html, 'maintenance-remark-row', '</div>');
    $temuan = laporanEffluentPlantComponentSlice($html, 'finding-row', '</div>');

    expect($perawatan)->toContain($tanggal);
    expect($perawatan)->toContain('08:00');
    expect($temuan)->toContain($tanggal);
    expect($temuan)->toContain('08:00');

    // DUA bagian dengan judul terpisah, dan tidak ada satu tabel gabungan.
    expect($html)->toContain('Rekap Catatan Perawatan');
    expect($html)->toContain('Rekap Temuan');
    expect(substr_count($html, 'data-testid="maintenance-remarks"'))->toBe(1);
    expect(substr_count($html, 'data-testid="findings"'))->toBe(1);
    // Menyatukan keduanyalah yang akan menghitung slot itu dua kali, di
    // dalam satu pengertian yang sama.
    expect(laporanEffluentPlantComponentSlice($html, 'findings-note', '</div>'))
        ->toContain('kedua');
});

it('skenario 13 — kedua rekap teks kosong: keterangan masing-masing, bukan tabel kosong', function () {
    $record = laporanEffluentPlantComponentRecord($this->stationA, $this->day1);
    laporanEffluentPlantComponentSlot($record, $this->slots[0], ['anaerobic_pond_1_ph' => 7.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEffluentPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->toContain('data-testid="maintenance-remarks-empty"');
    expect($html)->toContain('data-testid="findings-empty"');
    expect($html)->not->toContain('data-testid="maintenance-remark-row"');
    expect($html)->not->toContain('data-testid="finding-row"');

    // Tabel kosong tidak membedakan "tidak ada perawatan" dari "tidak ada
    // yang mencatatnya".
    expect(laporanEffluentPlantComponentSlice($html, 'maintenance-remarks-empty'))
        ->toContain('Tidak ada satu pun catatan perawatan');
    expect(laporanEffluentPlantComponentSlice($html, 'findings-empty'))
        ->toContain('Tidak ada satu pun temuan');
});

// =====================================================================
// Kelengkapan pencatatan — PALING ATAS
// =====================================================================

it('skenario 14 — pencatatan sangat tidak lengkap: cakupan DI ATAS kartu metrik, X dari Y slot dan persennya', function () {
    $record = laporanEffluentPlantComponentRecord($this->stationA, $this->day1);

    foreach (range(0, 2) as $index) {
        laporanEffluentPlantComponentSlot($record, $this->slots[$index], ['anaerobic_pond_1_ph' => 7.0]);
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEffluentPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Periode yang terisi seperlima pun menghasilkan rata-rata yang terlihat
    // rapi, jadi pembaca harus melihat kelengkapannya LEBIH DULU. Diasersi
    // atas POSISI, bukan sekadar kehadiran.
    expect(strpos($html, 'data-testid="coverage"'))
        ->toBeLessThan(strpos($html, 'data-testid="metric-block-anaerobic-pond-1"'));
    expect(strpos($html, 'data-testid="coverage"'))
        ->toBeLessThan(strpos($html, 'data-testid="metric-card"'));

    $slots = laporanEffluentPlantComponentSlice($html, 'coverage-slots', '</span>');

    expect($slots)->toContain('3');

    // 1 unit x 10 hari x 24 slot = 240; 3/240 = 1,3%.
    expect(laporanEffluentPlantComponentSlice($html, 'coverage-percent', '</span>'))->toContain('1,3%');
    expect(laporanEffluentPlantComponentSlice($html, 'coverage-formula'))->toContain('240');
    expect(laporanEffluentPlantComponentSlice($html, 'coverage-formula'))->toContain('24 slot');
    expect(laporanEffluentPlantComponentSlice($html, 'coverage-formula'))->toContain('hari');

    // Penyebut kecil terlihat berdampingan dengan angkanya.
    expect(laporanEffluentPlantComponentCard($html, 'anaerobic_pond_1_ph'))
        ->toContain('dari <b>3</b> pembacaan');
});

it('skenario 15 — periode masih berjalan: keterangannya menyebut HARI TERAKHIR YANG TERHITUNG', function () {
    $running = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::EffluentPlant->value)
        ->range(now()->subDays(3)->toDateString(), now()->addDays(5)->toDateString())
        ->open()
        ->named('Periode Berjalan')
        ->create();

    foreach (range(0, 3) as $offset) {
        $date = now()->subDays(3 - $offset)->toDateString();
        $record = laporanEffluentPlantComponentRecord($this->stationA, $date, 'EP-1');

        foreach ($this->slots as $slot) {
            laporanEffluentPlantComponentSlot($record, $slot, ['anaerobic_pond_1_ph' => 7.0]);
        }
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEffluentPlant::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $running->id)
        ->html();

    $note = laporanEffluentPlantComponentSlice($html, 'coverage-running-note');

    // HARI TERAKHIR YANG TERHITUNG, bukan tanggal akhir periode: tanggal
    // akhir yang belum tiba akan terbaca seperti hari yang sudah ikut
    // dihitung.
    expect($note)->toContain('Dihitung sampai '.laporanEffluentPlantComponentTanggal(now()->toDateString()));
    expect($note)->toContain('periode masih berjalan');
    // Markup-nya membungkus baris, jadi kedua angkanya diasersi tersendiri
    // alih-alih sebagai satu frasa yang kebetulan tidak pernah bersebelahan.
    expect($note)->toContain('(4 dari');
    expect($note)->toContain('9 hari periode sudah lewat)');
    expect($note)->not->toContain(laporanEffluentPlantComponentTanggal(now()->addDays(5)->toDateString()));

    // Hari yang belum terjadi tidak menurunkan persentase.
    expect(laporanEffluentPlantComponentSlice($html, 'coverage-percent', '</span>'))->toContain('100,0%');
    expect(laporanEffluentPlantComponentSlice($html, 'coverage-formula'))->toContain('(sampai hari ini)');
});

it('skenario 16 — tidak ada slot yang diharapkan: persennya tanda pisah, bukan 0,0%', function () {
    $future = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::EffluentPlant->value)
        ->range(now()->addDays(5)->toDateString(), now()->addDays(14)->toDateString())
        ->draft()
        ->named('Periode Belum Mulai')
        ->create();

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEffluentPlant::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $future->id)
        ->html();

    $percent = laporanEffluentPlantComponentSlice($html, 'coverage-percent', '</span>');

    // '—', bukan '0,0%' yang akan terbaca seolah pencatatan gagal padahal
    // belum ada yang diharapkan. DIASERSI PADA SEL-nya, bukan atas halaman:
    // keterangan lain di halaman ini memuat banyak persentase.
    expect($percent)->toContain('&mdash;');
    expect($percent)->not->toContain('0,0%');
    expect($html)->toContain('data-testid="coverage-no-expected-note"');
    expect(laporanEffluentPlantComponentSlice($html, 'coverage-no-expected-note'))
        ->toContain('Belum ada slot yang diharapkan');
});

// =====================================================================
// Rekap per unit
// =====================================================================

it('skenario 17 — beberapa unit Effluent Plant: satu baris per unit, unit tanpa pembacaan tetap dirender', function () {
    $unit1 = laporanEffluentPlantComponentRecord($this->stationA, $this->day1, 'EP-1');
    laporanEffluentPlantComponentSlot($unit1, $this->slots[0], [
        'anaerobic_pond_1_ph' => 6.0,
        'anaerobic_pond_1_temp_c' => 38.0,
        'raw_pome_feed_rate_m3h' => 30.0,
        'final_discharge_ph' => 7.0,
    ]);
    laporanEffluentPlantComponentSlot($unit1, $this->slots[1], ['anaerobic_pond_1_ph' => 8.0]);

    $unit2 = laporanEffluentPlantComponentRecord($this->stationA, $this->day1, 'EP-2');
    laporanEffluentPlantComponentSlot($unit2, $this->slots[0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEffluentPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect(substr_count($html, 'data-testid="by-unit-row"'))->toBe(2);
    expect($html)->toContain('>EP-1<');
    expect($html)->toContain('>EP-2<');
    // Membuangnya akan menyembunyikan justru unit yang tidak pernah
    // ditulis — dan unit itu tetap ikut menambah penyebut kelengkapan.
    expect($html)->toContain('data-testid="by-unit-row-total"');
    expect(laporanEffluentPlantComponentSlice($html, 'by-unit-row-total', '</tr>'))
        ->toContain('Gabungan 2 unit');

    // Angka periode MENGGABUNGKAN seluruh unit: rata-rata pH (6+8)/2 = 7,00.
    expect(laporanEffluentPlantComponentCard($html, 'anaerobic_pond_1_ph'))->toContain('7,00');
});

// =====================================================================
// Akses
// =====================================================================

it('skenario 18 — akun terikat mill dengan business_unit_id null: gagal tertutup, TANPA pemilih mill', function () {
    $tanpaMill = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $html = Livewire::actingAs($tanpaMill)->test(LaporanEffluentPlant::class)->html();

    expect($html)->toContain('data-testid="no-mill-hint"');
    expect($html)->toContain('Akun Anda belum terhubung ke mill');
    expect($html)->toContain('Hubungi Admin');

    // DAFTAR SELURUH MILL TIDAK DITAWARKAN sebagai gantinya — dan tidak
    // pernah dibaca untuk sampai ke sini.
    expect($html)->not->toContain('data-testid="mill-select"');
    expect($html)->not->toContain('>Pilih Mill<');
    expect($html)->not->toContain('Mill Alpha');
    expect($html)->not->toContain('Mill Beta');

    foreach (LAPORAN_EFFLUENT_PLANT_FIGURE_TESTIDS as $testid) {
        $this->assertStringNotContainsString('data-testid="'.$testid.'"', $html, "testid {$testid} seharusnya TIDAK dirender");
    }
});

it('skenario 19 — id mill milik mill lain lewat properti: DIABAIKAN, tetap mill sendiri', function () {
    $recordA = laporanEffluentPlantComponentRecord($this->stationA, $this->day1, 'EP-A');
    laporanEffluentPlantComponentSlot($recordA, $this->slots[0], ['anaerobic_pond_1_ph' => 7.0]);

    $recordB = laporanEffluentPlantComponentRecord($this->stationB, $this->day1, 'EP-B');
    laporanEffluentPlantComponentSlot($recordB, $this->slots[0], ['anaerobic_pond_1_ph' => 99.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEffluentPlant::class)
        ->set('businessUnitId', (string) $this->businessUnitB->id)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Untuk peran terikat mill, properti itu DIABAIKAN SEPENUHNYA oleh
    // resolvedBusinessUnitId().
    expect(laporanEffluentPlantComponentSlice($html, 'mill-name', '</p>'))->toContain('Mill Alpha');
    expect($html)->toContain('>EP-A<');
    expect($html)->not->toContain('>EP-B<');
    expect(laporanEffluentPlantComponentCard($html, 'anaerobic_pond_1_ph'))->toContain('7,00');
    expect(laporanEffluentPlantComponentCard($html, 'anaerobic_pond_1_ph'))->not->toContain('99,00');
});

it('skenario 20 — id line milik mill lain lewat properti: jatuh ke BELUM MEMILIH, bukan data mill lain', function () {
    $recordB = laporanEffluentPlantComponentRecord($this->stationB, $this->day1, 'EP-B');
    laporanEffluentPlantComponentSlot($recordB, $this->slots[0], ['anaerobic_pond_1_ph' => 99.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEffluentPlant::class)
        ->set('productionLineId', $this->lineB)
        ->html();

    // Di layar, "belum memilih line" adalah keadaan awal yang normal dan
    // harus merender ajakan memilih — bukan halaman galat. Dan nama line
    // mill lain tidak pernah terbit di sini.
    expect($html)->toContain('data-testid="select-production-line-hint"');
    expect($html)->not->toContain('data-testid="production-line-current"');
    expect($html)->not->toContain('>EP-B<');

    foreach (LAPORAN_EFFLUENT_PLANT_FIGURE_TESTIDS as $testid) {
        $this->assertStringNotContainsString('data-testid="'.$testid.'"', $html, "testid {$testid} seharusnya TIDAK dirender");
    }

    // Dan daftar opsi line hanya memuat line milik mill yang berlaku.
    $options = laporanEffluentPlantComponentSlice($html, 'production-line-select', '</select>');

    expect($options)->not->toContain($this->lineB);
});

it('skenario 20b — periode milik mill lain lewat properti: penolakan TERLIHAT, tanpa satu angka mill lain', function () {
    $periodB = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::EffluentPlant->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Effluent Plant Beta')
        ->create();

    $recordB = laporanEffluentPlantComponentRecord($this->stationB, $this->day1, 'EP-B');
    laporanEffluentPlantComponentSlot($recordB, $this->slots[0], ['anaerobic_pond_1_ph' => 99.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEffluentPlant::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $periodB->id)
        ->html();

    // Ditolak SECARA TERLIHAT, bukan diam-diam ditukar: penukaran diam-diam
    // akan menjawab sebuah penyelidikan lintas mill dengan angka mill lain
    // di bawah id yang ditanyakan.
    expect($html)->toContain('data-testid="forbidden-notice"');
    expect($html)->toContain('Anda tidak memiliki akses ke periode ini');
    expect($html)->not->toContain('>EP-B<');

    foreach (LAPORAN_EFFLUENT_PLANT_FIGURE_TESTIDS as $testid) {
        $this->assertStringNotContainsString('data-testid="'.$testid.'"', $html, "testid {$testid} seharusnya TIDAK dirender");
    }
});

it('skenario 21 / case 63 — Operator ditolak pada RUTE dan pada mount() komponennya', function () {
    // Component test TIDAK melewati middleware, jadi hanya baris ini yang
    // membuktikan penjagaan rutenya ('role:supervisor,mill_management,admin').
    $response = $this->actingAs($this->operator, 'web')->get('/reports/effluent-plant');

    $response->assertStatus(403);
    $response->assertDontSee('Laporan Periode');

    // Dan peran yang memang dibuka benar-benar sampai ke halamannya — tanpa
    // ini, 403 di atas akan tetap hijau pada rute yang menolak semua orang.
    $this->actingAs($this->supervisor, 'web')
        ->get('/reports/effluent-plant')
        ->assertOk()
        ->assertSee('data-testid="laporan-effluent-plant"', false);

    // Lapisan komponen: mount() melakukan abort_unless atas daftar perannya
    // SENDIRI (supervisor/mill_management/admin).
    $html = Livewire::actingAs($this->operator)->test(LaporanEffluentPlant::class)->html();

    expect($html)->toContain('Forbidden');
    expect($html)->not->toContain('data-testid="laporan-effluent-plant"');
    expect($html)->not->toContain('data-testid="coverage"');
    expect($html)->not->toContain('data-testid="metric-card"');
});

it('skenario 21b — canAccess() memegang daftar perannya SENDIRI dan tidak mendelegasikan ke guardAccess()', function () {
    // SERVICE-NYA MENOLAK OPERATOR HARI INI, dan justru itu yang membuat
    // delegasi berbahaya di masa depan: screen-157 (laporan mobile Effluent
    // Plant) akan MELEBARKAN guardAccess(), dan bila canAccess()
    // mendelegasikan ke sana, layar web ini terbuka untuk Operator dalam
    // tarikan napas yang sama — tanpa satu galat pun dan tanpa apa pun di
    // layar yang menandainya.
    //
    // Diasersi SECARA STRUKTURAL atas badan metodenya, karena asersi
    // perilaku akan tetap hijau hari ini (service dan komponen sama-sama
    // menolak Operator) dan baru gagal setelah pelebaran itu terjadi —
    // yaitu terlambat.
    $method = new ReflectionMethod(LaporanEffluentPlant::class, 'canAccess');
    $source = implode('', array_slice(
        file($method->getFileName()),
        $method->getStartLine() - 1,
        $method->getEndLine() - $method->getStartLine() + 1,
    ));

    expect($source)->not->toContain('guardAccess');
    expect($source)->not->toContain('EffluentPlantReportService');
    // Ketiga peran disebut di tempat, dan Operator tidak disebut sama sekali.
    expect($source)->toContain('UserRole::Supervisor->value');
    expect($source)->toContain('UserRole::MillManagement->value');
    expect($source)->toContain('UserRole::Admin->value');
    expect($source)->not->toContain('UserRole::Operator');
});

// =====================================================================
// Periode tertutup, rekap harian, baca-saja
// =====================================================================

it('skenario 22 — periode tertutup: laporan penuh, badge Tertutup, kedua tombol ekspor tetap ada', function () {
    $closed = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::EffluentPlant->value)
        ->range(now()->subDays(40)->toDateString(), now()->subDays(31)->toDateString())
        ->closed()
        ->named('Periode Tertutup')
        ->create();

    $record = laporanEffluentPlantComponentRecord($this->stationA, now()->subDays(35)->toDateString(), 'EP-9');
    laporanEffluentPlantComponentSlot($record, $this->slots[0], ['anaerobic_pond_1_ph' => 7.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEffluentPlant::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $closed->id)
        ->html();

    expect($html)->toContain('data-testid="period-status-badge"');
    expect(laporanEffluentPlantComponentSlice($html, 'period-status-badge', '</span>'))->toContain('Tertutup');
    expect($html)->toContain('data-testid="coverage"');
    expect(substr_count($html, 'data-testid="metric-card"'))->toBe(14);

    // Kunci periode mengatur PENULISAN data, bukan pembacaan laporan — jadi
    // tombol tidak pernah dinonaktifkan.
    expect($html)->toContain('data-testid="export-button"');
    expect($html)->toContain('data-testid="export-excel-button"');

    $tombol = laporanEffluentPlantComponentSlice($html, 'export-button', '</button>');

    expect($tombol)->not->toContain('disabled="disabled"');
});

it('skenario 23 — rekap harian dapat ditutup tanpa mengubah satu angka pun, dan petunjuk geser muncul di periode panjang', function () {
    // Periode SEBULAN supaya rekap hariannya panjang dan petunjuk geser pada
    // grafik tren (muncul di atas 10 tanggal) ikut terender.
    $long = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::EffluentPlant->value)
        ->range(now()->subDays(45)->toDateString(), now()->subDays(15)->toDateString())
        ->open()
        ->named('Periode Panjang')
        ->create();

    foreach (range(0, 11) as $offset) {
        $record = laporanEffluentPlantComponentRecord(
            $this->stationA,
            now()->subDays(44 - $offset)->toDateString(),
            'EP-1',
        );
        laporanEffluentPlantComponentSlot($record, $this->slots[0], [
            'raw_pome_feed_rate_m3h' => 30.0 + $offset,
            'final_discharge_ph' => 7.0,
        ]);
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEffluentPlant::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $long->id)
        ->html();

    expect(substr_count($html, 'data-testid="daily-row-'))->toBe(13); // 12 tanggal + baris total
    expect($html)->toContain('data-testid="daily-recap-toggle"');
    expect($html)->toContain('data-testid="daily-trend-raw-pome-feed-scroll-hint"');
    expect(laporanEffluentPlantComponentSlice($html, 'daily-trend-raw-pome-feed-scroll-hint'))
        ->toContain('Geser mendatar');

    // BEDA DARI TULISAN TECH SPEC, yang menyebut "state komponen": rekap
    // harian memakai elemen <details> NATIVE, jadi menutupnya terjadi
    // sepenuhnya di peramban dan TIDAK melalui satu putaran Livewire pun.
    // Itulah yang membuat klaim "menutupnya tidak mengubah satu angka pun"
    // benar secara struktural: seluruh angka halaman ini dihitung di
    // service, dan tabelnya berada DI DALAM elemen yang ditutup.
    expect($html)->toContain('<details class="md-details" data-testid="daily-recap-details"');

    $detailsStart = strpos($html, 'data-testid="daily-recap-details"');
    $detailsEnd = strpos($html, '</details>', $detailsStart);
    $details = substr($html, $detailsStart, $detailsEnd - $detailsStart);

    expect($details)->toContain('data-testid="daily-table"');
    expect($details)->toContain('data-testid="daily-row-total"');

    // Blok metrik utama dan KEDUA grafik tren berada DI LUAR elemen itu,
    // jadi keduanya tetap dirender pada kedua keadaan.
    expect($details)->not->toContain('data-testid="metric-card"');
    expect($details)->not->toContain('data-testid="daily-trend-raw-pome-feed"');
    expect($details)->not->toContain('data-testid="daily-trend-final-discharge-ph"');
    expect($details)->not->toContain('data-testid="coverage-slots"');
});

it('skenario 24 / case 64 — layar hanya membaca, dan angkanya identik dengan payload service yang sama', function () {
    $record = laporanEffluentPlantComponentRecord($this->stationA, $this->day1, 'EP-1');

    laporanEffluentPlantComponentSlot($record, $this->slots[0], [
        'anaerobic_pond_1_ph' => 7.25,
        'biogas_flare_status' => 'on',
        'findings' => 'Busa tebal',
    ]);
    laporanEffluentPlantComponentSlot($record, $this->slots[1], ['anaerobic_pond_1_ph' => 6.75]);

    $recordsBefore = EffluentPlantRecord::query()->orderBy('id')->get()->toJson();
    $detailsBefore = EffluentPlantDetail::query()->orderBy('id')->get()->toJson();

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEffluentPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Tidak satu baris stasiun pun bergerak.
    expect(EffluentPlantRecord::query()->orderBy('id')->get()->toJson())->toBe($recordsBefore);
    expect(EffluentPlantDetail::query()->orderBy('id')->get()->toJson())->toBe($detailsBefore);

    // Tidak ada satu metode publik yang berbau tulis.
    $methods = array_map(
        fn (ReflectionMethod $method) => $method->getName(),
        (new ReflectionClass(LaporanEffluentPlant::class))->getMethods(ReflectionMethod::IS_PUBLIC),
    );

    foreach (['save', 'store', 'create', 'update', 'delete', 'destroy', 'submit', 'verify', 'close'] as $writeish) {
        expect($methods)->not->toContain($writeish);
    }

    // Tidak ada elemen form tulis di dalam akar laporannya.
    expect($html)->not->toContain('<textarea');
    expect($html)->not->toContain('type="submit"');

    // case 64 — KOMPONEN MEMAKAI SERVICE YANG SAMA DENGAN CONTROLLER API,
    // sehingga halaman web dan API tidak mungkin berbeda angkanya.
    $this->actingAs($this->supervisor);

    $summary = app(EffluentPlantReportService::class)
        ->buildSummary($this->periodA, null, $this->lineA);

    expect($summary['metrics']['anaerobic_pond_1_ph']['avg'])->toBe(7.0);

    $card = laporanEffluentPlantComponentCard($html, 'anaerobic_pond_1_ph');

    expect($card)->toContain(number_format($summary['metrics']['anaerobic_pond_1_ph']['avg'], 2, ',', '.'));
    expect($card)->toContain('dari <b>'.$summary['metrics']['anaerobic_pond_1_ph']['reading_count'].'</b> pembacaan');
    expect(laporanEffluentPlantComponentSlice($html, 'coverage-slots', '</span>'))
        ->toContain((string) $summary['coverage']['filled_slots']);
});

// =====================================================================
// Daftar periode, keanggotaan, penyebut
// =====================================================================

it('skenario 25 — opsi periode hanya yang mencakup Effluent Plant, ditulis "Nama · rentang · Status"', function () {
    $semuaJenis = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(null)
        ->range(now()->subDays(60)->toDateString(), now()->subDays(51)->toDateString())
        ->open()
        ->named('Periode Semua Jenis')
        ->create();

    Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelPlant->value)
        ->range(now()->subDays(80)->toDateString(), now()->subDays(71)->toDateString())
        ->open()
        ->named('Periode Kernel Plant Saja')
        ->create();

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEffluentPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    $picker = laporanEffluentPlantComponentSlice($html, 'period-select', '</select>');

    expect($picker)->toContain('Periode Effluent Plant Alpha');
    // Cakupan semua-stasiun dinyatakan lewat ADANYA satu baris
    // period_stations per jenis, jadi periode ini PUNYA baris effluent-plant.
    expect($picker)->toContain('Periode Semua Jenis');
    expect($picker)->not->toContain('Periode Kernel Plant Saja');
    expect($picker)->toContain('>Pilih Periode<');

    // 'Nama · rentang · Status' — TANPA nama jenis stasiun, karena daftar ini
    // sudah hanya memuat periode yang mencakup stasiun laporan ini.
    expect($picker)->toContain('Periode Effluent Plant Alpha &middot;');
    expect($picker)->toContain('&middot; Terbuka');
    expect($picker)->not->toContain('Effluent Plant &middot; Terbuka');

    // Catatan singkat tentang cakupan daftar periode ada di bawah bar filter.
    expect($html)->toContain('Hanya periode yang mencakup Effluent Plant yang ditampilkan');
});

it('skenario 26 — pembacaan di tanggal mulai dan tanggal akhir muncul di rekap harian dan kedua tren', function () {
    $first = laporanEffluentPlantComponentRecord($this->stationA, $this->periodStart, 'EP-1');
    $last = laporanEffluentPlantComponentRecord($this->stationA, $this->periodEnd, 'EP-1');

    laporanEffluentPlantComponentSlot($first, $this->slots[0], [
        'raw_pome_feed_rate_m3h' => 30.0,
        'final_discharge_ph' => 7.0,
        'biogas_flare_status' => 'on',
    ]);
    laporanEffluentPlantComponentSlot($last, $this->slots[0], [
        'raw_pome_feed_rate_m3h' => 50.0,
        'final_discharge_ph' => 7.4,
        'biogas_flare_status' => 'off',
    ]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEffluentPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->toContain('data-testid="daily-row-'.$this->periodStart.'"');
    expect($html)->toContain('data-testid="daily-row-'.$this->periodEnd.'"');
    expect($html)->toContain('data-testid="daily-trend-raw-pome-feed-col-'.$this->periodStart.'"');
    expect($html)->toContain('data-testid="daily-trend-raw-pome-feed-col-'.$this->periodEnd.'"');
    expect($html)->toContain('data-testid="daily-trend-final-discharge-ph-col-'.$this->periodStart.'"');
    expect($html)->toContain('data-testid="daily-trend-final-discharge-ph-col-'.$this->periodEnd.'"');

    // Kelengkapan, penyebut dan sebaran status memperhitungkan kedua ujung.
    expect(laporanEffluentPlantComponentSlice($html, 'coverage-slots', '</span>'))->toContain('2');
    expect(laporanEffluentPlantComponentCard($html, 'raw_pome_feed_rate_m3h'))
        ->toContain('dari <b>2</b> pembacaan');
    expect(laporanEffluentPlantComponentSlice($html, 'status-recorded-total', '</tr>'))
        ->toContain('<td>2</td>');
});

it('skenario 27 / 28 — tiap kartu mencetak penyebutnya SENDIRI, di dalam kartu yang sama dengan angkanya', function () {
    $record = laporanEffluentPlantComponentRecord($this->stationA, $this->day1, 'EP-1');

    // Himpunan slot yang BERBEDA-BEDA per kolom — tiga penyebut berbeda pada
    // halaman yang sama, itulah yang membuat asersi ini bermakna alih-alih
    // selalu hijau.
    foreach (range(0, 2) as $index) {
        laporanEffluentPlantComponentSlot($record, $this->slots[$index], ['anaerobic_pond_1_ph' => 7.0]);
    }

    laporanEffluentPlantComponentSlot($record, $this->slots[3], [
        'cooling_pond_ph' => 7.5,
        'final_discharge_cod_mgl_lab' => 300.0,
    ]);
    laporanEffluentPlantComponentSlot($record, $this->slots[4], ['final_discharge_cod_mgl_lab' => 400.0]);
    // Slot yang HANYA mengisi temuan: terhitung terisi, tidak menambah
    // penyebut satu metrik pun.
    laporanEffluentPlantComponentSlot($record, $this->slots[5], ['findings' => 'Busa tebal']);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEffluentPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // EMPAT penyebut yang BERBEDA, masing-masing DI DALAM kartunya sendiri —
    // tidak dikumpulkan di satu catatan terpisah.
    expect(laporanEffluentPlantComponentCard($html, 'anaerobic_pond_1_ph'))
        ->toContain('dari <b>3</b> pembacaan');
    expect(laporanEffluentPlantComponentCard($html, 'cooling_pond_ph'))
        ->toContain('dari <b>1</b> pembacaan');
    expect(laporanEffluentPlantComponentCard($html, 'final_discharge_cod_mgl_lab'))
        ->toContain('dari <b>2</b> pembacaan');
    expect(laporanEffluentPlantComponentCard($html, 'chemical_consumed_kgl'))
        ->toContain('dari <b>0</b> pembacaan');

    // Dan rata-rata metrik laboratorium dihitung dari pembacaan METRIK ITU
    // SAJA: 350,00 — bukan 100,00 yang akan muncul bila dibagi keenam slot
    // terisi.
    expect(laporanEffluentPlantComponentCard($html, 'final_discharge_cod_mgl_lab'))->toContain('350');
    expect(laporanEffluentPlantComponentCard($html, 'final_discharge_cod_mgl_lab'))->not->toContain('100,00');

    // Slot terisi (6) MELEBIHI penyebut setiap metrik — itu benar, bukan
    // ketidaksesuaian yang perlu "diperbaiki".
    expect(laporanEffluentPlantComponentSlice($html, 'coverage-slots', '</span>'))->toContain('6');
    expect(laporanEffluentPlantComponentSlice($html, 'coverage-note'))->toContain('sembilan belas');

    // SETIAP kartu mencetak penyebutnya: empat belas kartu, empat belas
    // penyebut, tidak satu pun yang hilang atau mewarisi penyebut kartu lain.
    expect(substr_count($html, 'data-testid="metric-denominator"'))->toBe(14);
});

// =====================================================================
// Yang sengaja TIDAK ada
// =====================================================================

it('skenario 29 — pernyataan kepatuhan lingkungan dirender DI DALAM blok Buangan Akhir, bukan catatan kaki', function () {
    $record = laporanEffluentPlantComponentRecord($this->stationA, $this->day1, 'EP-1');

    laporanEffluentPlantComponentSlot($record, $this->slots[0], [
        'final_discharge_ph' => 7.0,
        'final_discharge_bod_mgl_lab' => 120.0,
        'final_discharge_cod_mgl_lab' => 300.0,
        'final_discharge_tss_mgl_lab' => 80.0,
    ]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEffluentPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    $blokAwal = strpos($html, 'data-testid="metric-block-final-discharge"');
    $blokAkhir = strpos($html, 'data-testid="status-distribution-biogas-flare"');
    $pernyataan = strpos($html, 'data-testid="environmental-compliance-note"');

    // DI DALAM / DI DEKAT blok Buangan Akhir — angka lingkungan tanpa batas
    // hukumnya mengundang pembaca memasok batas itu dari ingatan, dan
    // catatan kaki di ujung halaman dibaca terakhir atau tidak dibaca sama
    // sekali.
    expect($pernyataan)->toBeGreaterThan($blokAwal);
    expect($pernyataan)->toBeLessThan($blokAkhir);
    // Dan BUKAN di footer: ia berada di atas blok kelengkapan record.
    expect($pernyataan)->toBeLessThan(strpos($html, 'data-testid="completeness"'));

    $note = laporanEffluentPlantComponentSlice($html, 'environmental-compliance-note', '</div>');

    expect($note)->toContain('tidak menyatakan kepatuhan lingkungan');
    expect($note)->toContain('batas');
    expect($note)->toContain('regulasinya ditetapkan di luar aplikasi ini');

    // TIDAK ADA label/badge/kolom kesimpulan 'patuh'/'tidak patuh' di mana
    // pun — diasersi atas penanda markup, bukan atas kata di dalam kalimat
    // yang justru menyatakan ketiadaannya.
    expect($html)->not->toContain('data-testid="compliance-badge"');
    expect($html)->not->toContain('data-testid="compliance-verdict"');
    expect($html)->not->toContain('>Patuh<');
    expect($html)->not->toContain('>Tidak Patuh<');
    expect($html)->not->toContain('md-badge--compliant');
});

it('skenario 30 — tidak ada satu pun KELAS penanda di luar batas pada HTML ter-render', function () {
    $record = laporanEffluentPlantComponentRecord($this->stationA, $this->day1, 'EP-1');

    // Nilai SENGAJA ekstrem jauh di atas dan di bawah nilai lainnya.
    laporanEffluentPlantComponentSlot($record, $this->slots[0], [
        'anaerobic_pond_1_ph' => 0.1,
        'cooling_pond_temp_c' => 200.0,
        'raw_pome_feed_rate_m3h' => 1.0,
        'final_discharge_ph' => 14.0,
    ]);
    laporanEffluentPlantComponentSlot($record, $this->slots[1], [
        'anaerobic_pond_1_ph' => 13.9,
        'cooling_pond_temp_c' => -40.0,
        'raw_pome_feed_rate_m3h' => 9000.0,
        'final_discharge_ph' => 0.5,
    ]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEffluentPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // DISISIR ATAS NAMA KELAS, bukan atas frasa: keterangan yang menyatakan
    // ketiadaan penandaan itu sendiri memuat frasa "di luar batas", jadi
    // penyisiran teks justru gagal pada kalimat yang membuktikan klaimnya.
    foreach ([
        'md-threshold', 'is-danger', 'is-warning', 'is-critical', 'is-breach',
        'md-chip--danger', 'md-badge--danger', 'md-kpi--danger', 'md-trendchart__bar--high',
    ] as $className) {
        $this->assertStringNotContainsString($className, $html, "kelas penanda {$className} dipakai");
    }

    // Seluruh nilai dirender dengan gaya netral yang sama — keempat bagian
    // itu (kartu metrik, tren, rekap per unit, rekap harian) memakai satu
    // kelas yang sama untuk sel kosong dan tidak satu pun untuk nilai
    // ekstrem.
    expect(laporanEffluentPlantComponentCard($html, 'anaerobic_pond_1_ph'))->toContain('13,90');
    expect(laporanEffluentPlantComponentCard($html, 'cooling_pond_temp_c'))->toContain('-40,0');

    // Dan ketiadaannya DINYATAKAN — ketiadaan penandaan yang tidak
    // dijelaskan terbaca sebagai fitur yang belum selesai, dan seseorang
    // akan "melengkapinya".
    expect(laporanEffluentPlantComponentSlice($html, 'no-downtime-note', '</div>'))
        ->toContain('Tidak ada pula satu nilai yang ditandai di luar batas');
});

it('skenario 31 — status kategoris sebagai SEBARAN: tanpa terendah/rata-rata/tertinggi, tanpa seri tren', function () {
    $record = laporanEffluentPlantComponentRecord($this->stationA, $this->day1, 'EP-1');

    laporanEffluentPlantComponentSlot($record, $this->slots[0], [
        'biogas_flare_status' => 'on',
        'dosing_pump_1_status' => 'run',
        'sludge_dewatering_status' => 'stop',
        'raw_pome_feed_rate_m3h' => 40.0,
        'final_discharge_ph' => 7.0,
    ]);
    laporanEffluentPlantComponentSlot($record, $this->slots[1], [
        'biogas_flare_status' => 'fault',
        'dosing_pump_1_status' => 'stop',
        'sludge_dewatering_status' => 'run',
    ]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEffluentPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Ketiga sebaran dirender sebagai jumlah slot per nilai plus porsinya.
    foreach ([
        'status-distribution-biogas-flare',
        'status-distribution-dosing-pump-1',
        'status-distribution-sludge-dewatering',
    ] as $testid) {
        $kartu = laporanEffluentPlantComponentSlice($html, $testid, '</table>');

        $this->assertStringContainsString('Jumlah slot', $kartu, "sebaran {$testid}");
        $this->assertStringContainsString('Porsi', $kartu, "sebaran {$testid}");
        // Tidak ada kolom maupun label terendah/rata-rata/tertinggi.
        $this->assertStringNotContainsString('terendah', $kartu, "sebaran {$testid}");
        $this->assertStringNotContainsString('rata-rata', $kartu, "sebaran {$testid}");
        $this->assertStringNotContainsString('tertinggi', $kartu, "sebaran {$testid}");
    }

    // TIDAK SATU PUN dari ketiganya menjadi kartu metrik.
    foreach (array_keys(EffluentPlantReportService::STATUS_COLUMNS) as $column) {
        $this->assertStringNotContainsString(
            'data-metric="'.$column.'"',
            $html,
            "kolom status {$column} tidak boleh menjadi kartu metrik",
        );
    }

    // Dan grafik tren memuat TEPAT dua seri: laju POME masuk dan pH buangan
    // akhir.
    expect(substr_count($html, 'data-testid="daily-trend-'))->toBeGreaterThan(0);
    expect($html)->toContain('data-testid="daily-trend-raw-pome-feed"');
    expect($html)->toContain('data-testid="daily-trend-final-discharge-ph"');
    expect($html)->not->toContain('data-testid="daily-trend-biogas-flare"');
    expect($html)->not->toContain('data-testid="daily-trend-dosing-pump-1"');

    // Porsinya memakai SLOT TERCATAT sebagai penyebut, bukan slot yang
    // diharapkan — yang kedua akan mencampur slot tak tersentuh ke dalam
    // porsi sebuah nilai yang justru tercatat.
    expect(laporanEffluentPlantComponentSlice($html, 'status-distribution-note', '</div>'))
        ->toContain('jumlah slot tercatat sebagai penyebut');
});

it('skenario 32 — kedua kolom teks dirender utuh beserta tanggal dan slot waktu, tanpa angka ringkasan', function () {
    $day1 = laporanEffluentPlantComponentRecord($this->stationA, $this->day1, 'EP-1');
    $day2 = laporanEffluentPlantComponentRecord($this->stationA, $this->day2, 'EP-1');

    laporanEffluentPlantComponentSlot($day1, '07:00', ['remarks_maintenance_actions' => 'Ganti seal pompa']);
    laporanEffluentPlantComponentSlot($day1, '09:00', ['findings' => 'Busa tebal di kolam 2']);
    laporanEffluentPlantComponentSlot($day2, '13:00', ['findings' => 'Bau menyengat']);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEffluentPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect(substr_count($html, 'data-testid="maintenance-remark-row"'))->toBe(1);
    expect(substr_count($html, 'data-testid="finding-row"'))->toBe(2);

    expect($html)->toContain('Ganti seal pompa');
    expect($html)->toContain('Busa tebal di kolam 2');
    expect($html)->toContain('Bau menyengat');
    // Slot waktu HH:MM, beserta tanggalnya.
    expect($html)->toContain(laporanEffluentPlantComponentTanggal($this->day1).' &middot; 07:00');
    expect($html)->toContain(laporanEffluentPlantComponentTanggal($this->day2).' &middot; 13:00');

    // Teks bebas tidak punya rata-rata: tidak pernah dirata-rata maupun
    // digrafikkan.
    expect(laporanEffluentPlantComponentSlice($html, 'maintenance-remarks-note', '</div>'))
        ->toContain('tidak pernah dirata-rata maupun');

    foreach (EffluentPlantReportService::FREE_TEXT_COLUMNS as $column) {
        $this->assertStringNotContainsString(
            'data-metric="'.$column.'"',
            $html,
            "kolom teks bebas {$column} tidak boleh menjadi kartu metrik",
        );
        $this->assertStringNotContainsString(
            'data-testid="daily-trend-'.$column.'"',
            $html,
            "kolom teks bebas {$column} tidak boleh menjadi seri tren",
        );
    }
});

it('skenario 33 — tidak ada bagian downtime: diasersi atas TESTID dan JUDUL, bukan atas substring', function () {
    $record = laporanEffluentPlantComponentRecord($this->stationA, $this->day1, 'EP-1');

    foreach (array_slice($this->slots, 0, 4) as $slot) {
        laporanEffluentPlantComponentSlot($record, $slot, ['anaerobic_pond_1_ph' => 7.0]);
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanEffluentPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Ketiadaannya DINYATAKAN di layar — bagian yang hilang tanpa
    // keterangan tidak dapat dibedakan dari bagian yang gagal dimuat, dan
    // laporan stasiun lain memilikinya sehingga pembaca yang datang dari
    // sana akan mencarinya.
    expect($html)->toContain('data-testid="no-downtime-note"');
    expect(laporanEffluentPlantComponentSlice($html, 'no-downtime-note', '</div>'))
        ->toContain('tidak mencatat lama berhenti sama sekali');

    // TIDAK ADA BAGIAN, KARTU, KOLOM, MAUPUN PLACEHOLDER downtime. Diasersi
    // atas TESTID dan JUDUL — bukan atas substring 'downtime', karena
    // pernyataan ketiadaan di atas MEMBAWA kata itu di dalam testid-nya
    // sendiri. Kesamaan kedua hitungan di bawah itulah buktinya: satu-satunya
    // kemunculan 'downtime' pada halaman ter-render adalah testid pernyataan
    // ketiadaan itu.
    expect(substr_count($html, 'downtime'))->toBe(substr_count($html, 'no-downtime-note'));
    expect($html)->not->toContain('data-testid="downtime"');
    expect($html)->not->toContain('data-testid="downtime-total"');
    expect($html)->not->toContain('<h3>Downtime');
    expect($html)->not->toContain('>Downtime<');
    expect($html)->not->toContain('Lama Berhenti</th>');

    // Dan tidak ada teks galat maupun rangka kosong yang terbaca seperti
    // bagian yang gagal dimuat. FRASA 'gagal dimuat' SENGAJA TIDAK dicari di
    // sini: keterangan ketiadaan downtime itu sendiri memakainya untuk
    // menjelaskan mengapa ketiadaannya perlu dinyatakan. Yang diasersi adalah
    // ketiadaan PENANDA galat dan rangka kosong, bukan ketiadaan kata.
    expect($html)->not->toContain('Terjadi kesalahan');
    expect($html)->not->toContain('data-testid="load-error"');
    expect($html)->not->toContain('md-skeleton');
});

// =====================================================================
// Ekspor
// =====================================================================

it('skenario 34 — ekspor CSV dan Excel mengalirkan berkas; keduanya terkunci selama penyiapan', function () {
    $record = laporanEffluentPlantComponentRecord($this->stationA, $this->day1, 'EP-9');
    laporanEffluentPlantComponentSlot($record, $this->slots[0], ['anaerobic_pond_1_ph' => 7.0]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanEffluentPlant::class)
        ->set('productionLineId', $this->lineA);

    $csv = $component->call('exportCsv', 'csv');
    $csv->assertFileDownloaded();

    $excel = $component->call('exportCsv', 'excel');
    $excel->assertFileDownloaded();

    // Selama penyiapan KEDUA tombol dinonaktifkan (tidak ada unduhan ganda)
    // dan tombol yang diklik menampilkan 'Mengekspor…'.
    $html = $component->html();

    $tombolCsv = laporanEffluentPlantComponentSlice($html, 'export-button', '</button>');
    $tombolExcel = laporanEffluentPlantComponentSlice($html, 'export-excel-button', '</button>');

    expect($tombolCsv)->toContain('wire:loading.attr="disabled"');
    expect($tombolCsv)->toContain('wire:target="exportCsv"');
    expect($tombolCsv)->toContain('Mengekspor');
    expect($tombolExcel)->toContain('wire:loading.attr="disabled"');
    expect($tombolExcel)->toContain('wire:target="exportCsv"');
    expect($tombolExcel)->toContain('Mengekspor');
});

it('skenario 34b — ekspor tanpa line atau tanpa periode terpilih tidak menghasilkan apa pun', function () {
    $record = laporanEffluentPlantComponentRecord($this->stationA, $this->day1, 'EP-9');
    laporanEffluentPlantComponentSlot($record, $this->slots[0], ['anaerobic_pond_1_ph' => 7.0]);

    // LINE DIJAGA DI METODE, bukan hanya di blade: panggilan langsung ke
    // metode ini tidak boleh pernah mengambil berkas yang mencampur seluruh
    // line mill.
    $tanpaLine = Livewire::actingAs($this->supervisor)
        ->test(LaporanEffluentPlant::class)
        ->call('exportCsv', 'csv');

    expect($tanpaLine->effects)->not->toHaveKey('download');

    // DAN tanpa periode. Perhatikan bentuknya: men-set periodId menjadi ''
    // TIDAK cukup, karena render berikutnya MEMILIH ULANG periode terbaru
    // (keepSelectionValid) sehingga ekspornya memang menghasilkan berkas —
    // dan itu benar. Keadaan "tanpa periode" yang sah adalah mill yang tidak
    // punya satu pun periode Effluent Plant.
    $supervisorB = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitB)->create();

    $tanpaPeriode = Livewire::actingAs($supervisorB)
        ->test(LaporanEffluentPlant::class)
        ->set('productionLineId', $this->lineB)
        ->call('exportCsv', 'csv');

    expect($tanpaPeriode->get('periodId'))->toBe('');
    expect($tanpaPeriode->effects)->not->toHaveKey('download');
});

// =====================================================================
// Pintu masuk dari Laporan Stasiun (screen-140)
// =====================================================================

it('skenario 35 — tile Effluent Plant pada Laporan Stasiun AKTIF dan menunjuk ke rute laporan ini', function () {
    // SATU BARIS yang menentukan layar ini dapat dicapai: entri
    // 'effluent-plant' pada StationReportService::REPORT_ROUTES. Tanpa asersi
    // ini, laporannya bisa lengkap, seluruh test backend hijau, dan tile-nya
    // tetap kelabu — pola kegagalan yang sudah pernah terjadi pada laporan
    // Cages & Tracks versi web.
    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanStasiun::class)
        ->set('productionLineId', $this->lineA)
        ->assertSeeHtml('data-testid="station-grid"')
        ->assertSeeHtml('data-testid="station-tile-effluent-plant"')
        ->html();

    // Kuncinya 'effluent-plant' BERTANDA HUBUNG — itulah yang dipegang
    // StationTypeEnum::EffluentPlant->value; kunci snake_case akan
    // ter-compile, tampak benar, dan tidak pernah cocok dengan satu baris
    // master pun.
    $pattern = '/<(a|span)\b[^>]*data-testid="station-tile-effluent-plant"[^>]*>.*?<\/\1>/s';

    expect(preg_match($pattern, $html, $matches))->toBe(1);

    $tile = $matches[0];

    expect($tile)->toContain('href=');
    expect($tile)->toContain('/reports/effluent-plant');
    expect($tile)->not->toContain('disabled');
    // Mill DAN line ikut terbawa, sehingga layar tujuan tidak meminta
    // memilih lagi — itu sebabnya kedua properti layar ini membawa
    // #[Url(as: ...)] dengan kunci yang eksplisit.
    expect($tile)->toContain('business_unit_id='.$this->businessUnitA->id);
    expect($tile)->toContain('production_line_id='.$this->lineA);
});
