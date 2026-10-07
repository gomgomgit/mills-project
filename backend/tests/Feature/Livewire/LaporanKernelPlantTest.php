<?php

/**
 * LaporanKernelPlantTest (Feature/Livewire) — screen-154--laporan-kernel-plant-web,
 * halaman webnya sendiri.
 *
 * LAPISAN INI ADA UNTUK KEADAAN YANG TIDAK BISA DIATUR DI TEMPAT LAIN.
 * Angkanya sudah dibuktikan dua kali terhadap data yang dikendalikan penuh —
 * atas service-nya di tests/Unit/Services/KernelPlantReportServiceTest.php (67
 * kasus) dan atas kontrak HTTP-nya di tests/Feature/Api/LaporanKernelPlantTest.php
 * (33 skenario) — jadi berkas ini TIDAK mengulanginya. Yang dibuktikan di sini
 * adalah apa yang HALAMAN lakukan terhadap angka itu: pemilih mana yang ada
 * untuk peran mana, keadaan kosong mana yang digambar, dan hal-hal yang layar
 * ini tidak boleh biarkan terbaca salah.
 *
 * Dan — ini sebab utamanya — TUJUH KEADAAN yang database e2e bersama tidak
 * bisa menyusunnya, karena setiap test di sini punya databasenya sendiri:
 * mill dengan satu Production Line (tetap tidak dipilih otomatis), mill tanpa
 * satu pun periode Kernel Plant, periode berisi record tetapi NOL slot terisi,
 * periode yang belum mulai, baris master yang disunting tangan sehingga
 * namanya tidak lagi cocok dengan peta kolom, akun terikat mill dengan
 * business_unit_id null, dan id mill/line milik mill lain yang disuapkan lewat
 * properti komponen. Browser test screen-154 sengaja tidak mencoba satu pun
 * dari itu.
 *
 * DIASERSI ATAS HTML TER-RENDER, bukan atas state komponen, di mana pun
 * klaimnya tentang apa yang pembaca lihat. Sebuah data-testid yang ada di
 * markup tetapi tidak pernah dicapai oleh kondisional blade akan tetap
 * meloloskan asersi atas state.
 *
 * SATU HAL STRUKTURAL IKUT DIJAGA DI SINI: canAccess() memegang daftar
 * perannya SENDIRI dan tidak mendelegasikan ke
 * KernelPlantReportService::guardAccess(). Service itu MENERIMA Operator —
 * demi kembaran mobile screen-155 yang memakai ulang keempat endpoint-nya —
 * jadi delegasi akan MEMBUKA layar web ini untuk Operator tanpa satu galat pun
 * dan tanpa apa pun di layar yang menandainya.
 */

use App\Enums\RecordStatus;
use App\Enums\StationType as StationTypeEnum;
use App\Enums\UserRole;
use App\Livewire\Dashboard\LaporanKernelPlant;
use App\Livewire\Dashboard\LaporanStasiun;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** Tiap data-testid yang hanya ada setelah sebuah angka benar-benar dirender. */
const LAPORAN_KERNEL_PLANT_FIGURE_TESTIDS = [
    'coverage',
    'coverage-slots',
    'coverage-denominator',
    'coverage-days',
    'metrics',
    'metrics-table',
    'no-flagging-note',
    'by-kernel-plant',
    // DUA bagian, bukan satu: downtime (angka) dan temuan (teks) sengaja
    // terpisah — satu menjawab "berapa lama", satu "apa yang terlihat".
    'downtime',
    'findings',
    'completeness',
    'daily-recap-card',
];

function laporanKernelPlantComponentRecord(
    Station $station,
    string $date,
    string $kernelPlantId = 'KP-1',
    array $attributes = [],
): KernelPlantRecord {
    return KernelPlantRecord::factory()
        ->forStation($station)
        ->onDate($date)
        ->create(array_merge(['kernel_plant_id' => $kernelPlantId, 'note' => 'Catatan harian'], $attributes));
}

function laporanKernelPlantComponentSlot(KernelPlantRecord $record, string $timeSlot, array $values = []): KernelPlantDetail
{
    return KernelPlantDetail::factory()
        ->forRecord($record)
        ->timeSlot($timeSlot)
        ->create($values);
}

/**
 * ENAM baris master, persis seperti KernelPlantOperationalTargetSeeder.
 *
 * Masternya hanya TIGA kolom — equipment_parameter / target_benchmark /
 * corrective_action_plan — jadi tidak ada batas kritis maupun akibat
 * operasional untuk dirender, berbeda dari master Depricarping yang empat
 * kolom.
 *
 * ENAM parameter untuk TUJUH kolom ukur, dan ketimpangannya dua arah:
 *   - 'Ripple Mill (Cracker)' mengatur DUA kolom dan 'Kernel Silo 1 & 2' juga
 *     DUA, jadi tujuh entri peta hanya menyebut LIMA parameter;
 *   - 'Final Kernel Dirt' tidak punya kolom ukur di MANA PUN pada skema ini.
 *
 * @return list<array{0: string, 1: string, 2: string}>
 */
function laporanKernelPlantComponentTargetRows(): array
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

/** @param  callable(string): bool|null  $filter */
function laporanKernelPlantComponentSeedTargets(?callable $filter = null, array $rename = []): void
{
    foreach (laporanKernelPlantComponentTargetRows() as $index => [$parameter, $benchmark, $plan]) {
        if ($filter !== null && ! $filter($parameter)) {
            continue;
        }

        KernelPlantOperationalTarget::create([
            'equipment_parameter' => $rename[$parameter] ?? $parameter,
            'target_benchmark' => $benchmark,
            'corrective_action_plan' => $plan,
            'sort_order' => $index + 1,
        ]);
    }
}

/**
 * Isi satu elemen yang dikenali data-testid-nya, dipotong dari HTML.
 *
 * Dipakai setiap kali klaimnya tentang satu sel tertentu: "tidak ada teks
 * nol persen di mana pun pada halaman" akan selalu gagal di sini, karena
 * kedua kolom target master memuat '≤ 7.0%' dan '≤ 6.0%'. Yang benar adalah
 * memeriksa SEL cakupannya.
 */
function laporanKernelPlantComponentSlice(string $html, string $testid, string $closing = '</p>'): string
{
    $start = strpos($html, 'data-testid="'.$testid.'"');

    if ($start === false) {
        return '';
    }

    $end = strpos($html, $closing, $start);

    return $end === false ? substr($html, $start) : substr($html, $start, $end - $start);
}

/** Potongan satu baris <tr data-testid="metric-row"> yang memuat $needle. */
function laporanKernelPlantComponentMetricRow(string $html, string $needle): string
{
    $rows = explode('data-testid="metric-row"', $html);

    return collect($rows)->first(fn (string $row) => str_contains($row, $needle)) ?? '';
}

/** Tanggal seperti yang dicetak blade: '07 Okt 2026'. */
function laporanKernelPlantComponentTanggal(string $date): string
{
    $bulan = ['01' => 'Jan', '02' => 'Feb', '03' => 'Mar', '04' => 'Apr', '05' => 'Mei', '06' => 'Jun',
        '07' => 'Jul', '08' => 'Agu', '09' => 'Sep', '10' => 'Okt', '11' => 'Nov', '12' => 'Des'];

    [$y, $m, $d] = array_pad(explode('-', substr($date, 0, 10)), 3, '');

    return $d.' '.($bulan[$m] ?? $m).' '.$y;
}

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->kernelPlant()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->kernelPlant()->create();

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

    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelPlant->value)
        ->range($this->periodStart, $this->periodEnd)
        ->open()
        ->named('Periode Kernel Plant Alpha')
        ->create();

    $this->slots = KernelPlantRecordService::canonicalTimeSlots();
});

// =====================================================================
// Jalur sukses
// =====================================================================

it('skenario 1 — Supervisor: tanpa pemilih mill, seluruh bagian laporan terender', function () {
    laporanKernelPlantComponentSeedTargets();

    $record = laporanKernelPlantComponentRecord($this->stationA, $this->day1);

    laporanKernelPlantComponentSlot($record, $this->slots[0], [
        'ripple_mill_1_amps' => 22.0,
        'claybath_hydro_sg' => 1.2,
        'kernel_silo_1_temp_c' => 70.0,
        'downtime_minutes' => 10,
        'findings' => 'Belt kendur',
    ]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Peran terikat mill tidak melihat pemilih mill — menawarkan pemilih yang
    // tidak bisa ia pakai adalah kebohongan kecil yang mahal.
    expect($html)->not->toContain('data-testid="mill-select"');
    expect($html)->toContain('data-testid="mill-name"');
    expect($html)->toContain('Mill Alpha');

    foreach (LAPORAN_KERNEL_PLANT_FIGURE_TESTIDS as $testid) {
        expect($html)->toContain('data-testid="'.$testid.'"');
    }

    // KETUJUH baris parameter, masing-masing dengan standarnya — dan kedua
    // kolom target dirender VERBATIM, keterangan dalam tanda kurung ikut.
    expect(substr_count($html, 'data-testid="metric-row"'))->toBe(7);
    expect($html)->toContain('Arus Ripple Mill 1');
    expect($html)->toContain('Arus Ripple Mill 2');
    expect($html)->toContain('Suhu Kernel Silo 1');
    expect($html)->toContain('Suhu Kernel Silo 2');
    expect($html)->toContain('Shell Bin Kernel Loss');
    expect($html)->toContain('Specific Gravity 1.18 - 1.24');
    expect($html)->toContain('Verify calcium carbonate mixture if kernels float with shell.');

    // Bacaan saja: tidak ada satu pun kontrol tulis.
    expect($html)->not->toContain('>Simpan<');
    expect($html)->not->toContain('>Hapus<');
    expect($html)->not->toContain('>Verifikasi<');
});

it('skenario 1b — Mill Management melihat layar yang sama persis dengan Supervisor', function () {
    $record = laporanKernelPlantComponentRecord($this->stationA, $this->day1);
    laporanKernelPlantComponentSlot($record, $this->slots[0], ['ripple_mill_1_amps' => 22.0]);

    $html = Livewire::actingAs($this->millManagement)
        ->test(LaporanKernelPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->not->toContain('data-testid="mill-select"');
    expect($html)->toContain('data-testid="mill-name"');
    expect($html)->toContain('data-testid="metrics-table"');
});

it('skenario 2 — Admin: pemilih mill dirender, dan tanpa mill tidak ada satu angka pun', function () {
    $record = laporanKernelPlantComponentRecord($this->stationA, $this->day1);
    laporanKernelPlantComponentSlot($record, $this->slots[0], ['ripple_mill_1_amps' => 22.0]);

    $component = Livewire::actingAs($this->admin)->test(LaporanKernelPlant::class);

    $html = $component->html();

    expect($html)->toContain('data-testid="mill-select"');
    expect($html)->toContain('data-testid="mill-select-hint"');

    foreach (LAPORAN_KERNEL_PLANT_FIGURE_TESTIDS as $testid) {
        expect($html)->not->toContain('data-testid="'.$testid.'"');
    }

    $withMill = $component
        ->set('businessUnitId', (string) $this->businessUnitA->id)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($withMill)->toContain('data-testid="metrics-table"');
});

// =====================================================================
// Urutan: cakupan SEBELUM angka ukur
// =====================================================================

it('skenario 3 — blok cakupan berada DI ATAS tabel parameter pada urutan DOM', function () {
    $record = laporanKernelPlantComponentRecord($this->stationA, $this->day1);
    laporanKernelPlantComponentSlot($record, $this->slots[0], ['ripple_mill_1_amps' => 22.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Periode yang terisi seperlima pun menghasilkan rata-rata yang terlihat
    // rapi, jadi pembaca harus melihat cakupannya LEBIH DULU. Diasersi atas
    // POSISI, bukan sekadar kehadiran.
    expect(strpos($html, 'data-testid="coverage"'))
        ->toBeLessThan(strpos($html, 'data-testid="metrics"'));
});

it('skenario 4 — cakupan mencetak ketiga angka pembentuk penyebutnya', function () {
    foreach (['KP-1', 'KP-2'] as $unit) {
        $record = laporanKernelPlantComponentRecord($this->stationA, $this->day1, $unit);
        laporanKernelPlantComponentSlot($record, $this->slots[0], ['ripple_mill_1_amps' => 22.0]);
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // 2 unit x 10 hari x 24 slot = 480.
    expect($html)->toContain('data-testid="coverage-denominator"');
    expect($html)->toContain('unit &times; 10 hari &times; 24 slot');
    expect($html)->toContain('480');
});

// =====================================================================
// Penyebut per parameter — N berbeda tiap baris, M SAMA pada ketujuhnya
// =====================================================================

it('skenario 5 — tiap baris mencetak N miliknya sendiri, dan M sama pada KETUJUH baris', function () {
    $record = laporanKernelPlantComponentRecord($this->stationA, $this->day1);

    laporanKernelPlantComponentSlot($record, $this->slots[0], [
        'ripple_mill_1_amps' => 22.0,
        'ripple_mill_2_amps' => 24.0,
        'kernel_silo_1_temp_c' => 70.0,
        'kernel_silo_2_temp_c' => 80.0,
    ]);
    laporanKernelPlantComponentSlot($record, $this->slots[1], ['ripple_mill_1_amps' => 23.0]);
    // Slot yang HANYA mengisi temuan tetap terhitung terisi — sembilan kolom
    // bacaan, bukan tujuh.
    laporanKernelPlantComponentSlot($record, $this->slots[2], ['findings' => 'Belt kendur']);
    laporanKernelPlantComponentSlot($record, $this->slots[3], ['downtime_minutes' => 10]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // TIGA N yang BERBEDA pada halaman yang sama — itulah yang membuat asersi
    // ini bermakna alih-alih selalu hijau.
    expect($html)->toContain('2 dari 4 slot');
    expect($html)->toContain('1 dari 4 slot');
    expect($html)->toContain('0 dari 4 slot');

    // Dan M-nya SAMA pada ketujuh baris: ketujuh kolom ukur ada pada SETIAP
    // baris detail, jadi "unit mana mengukur parameter mana" bukan fakta yang
    // tersimpan di mana pun dan M per kolom hanya bisa dikarang. Mock HTML
    // layar ini sempat mencetak dua M yang berbeda; tech spec menetapkan satu.
    expect(substr_count($html, 'data-testid="metric-denominator"'))->toBe(7);
    expect(substr_count($html, 'dari 4 slot'))->toBe(7);
});

it('skenario 6 — tabel parameter TUJUH kolom, bukan delapan: master ini tidak punya batas kritis', function () {
    laporanKernelPlantComponentSeedTargets();

    $record = laporanKernelPlantComponentRecord($this->stationA, $this->day1);
    laporanKernelPlantComponentSlot($record, $this->slots[0], ['claybath_hydro_sg' => 1.2]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    $table = laporanKernelPlantComponentSlice($html, 'metrics-table', '</table>');

    // Parameter, min, rata-rata, maks, penyebut, target/benchmark, rencana
    // tindakan. TUJUH — satu lebih sedikit daripada tabel Depricarping, yang
    // masternya membawa critical_limit dan
    // operational_consequence_justification. Sel untuk kolom yang masternya
    // tidak punya sengaja tidak dibuat sama sekali.
    expect(substr_count($table, '<th scope="col">'))->toBe(7);
    expect($html)->not->toContain('data-testid="metric-critical-limit"');
    expect($html)->not->toContain('data-testid="metric-consequence"');

    // KEDUA kolom target berada pada BARIS YANG SAMA dengan angkanya — di
    // situlah penilaian manusia benar-benar terjadi.
    $row = laporanKernelPlantComponentMetricRow($html, 'Claybath / Hydrocyclone');

    expect($row)->toContain('1,20');
    expect($row)->toContain('Specific Gravity 1.18 - 1.24');
    expect($row)->toContain('Verify calcium carbonate mixture if kernels float with shell.');
    expect(substr_count($row, 'data-testid="metric-target-benchmark"'))->toBe(1);
    expect(substr_count($row, 'data-testid="metric-corrective-action"'))->toBe(1);
});

// =====================================================================
// DUA pasangan berbagi standar, bukan satu
// =====================================================================

it('skenario 7 — KEDUA pasangan ditandai (empat baris bertanda, tiga tanpa), dan angkanya tidak dirata-ratakan', function () {
    laporanKernelPlantComponentSeedTargets();

    $record = laporanKernelPlantComponentRecord($this->stationA, $this->day1);
    laporanKernelPlantComponentSlot($record, $this->slots[0], [
        'ripple_mill_1_amps' => 20.0,
        'ripple_mill_2_amps' => 30.0,
        'kernel_silo_1_temp_c' => 70.0,
        'kernel_silo_2_temp_c' => 80.0,
    ]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // EMPAT keterangan: dua untuk pasangan ripple mill, dua untuk pasangan
    // kernel silo. Depricarping hanya punya SATU pasangan (Nut Silo), jadi
    // markup atau logika yang mengistimewakan satu pasangan LOLOS di sana dan
    // SALAH di sini — dan inilah asersi yang menangkapnya.
    expect(substr_count($html, 'data-testid="metric-shared-standard"'))->toBe(4);
    expect(substr_count($html, 'data-testid="metric-shared-standard-row"'))->toBe(4);
    // Tiga baris sisanya TIDAK bertanda: tujuh baris, empat bertanda.
    expect(substr_count($html, 'data-testid="metric-row"'))->toBe(7);

    // Penandanya DITURUNKAN dari target.shares_standard_with, dan menyebut
    // saudara kolomnya dengan label manusia, bukan nama kolom mentah.
    expect($html)->toContain('<b>Arus Ripple Mill 2</b>');
    expect($html)->toContain('<b>Arus Ripple Mill 1</b>');
    expect($html)->toContain('<b>Suhu Kernel Silo 2</b>');
    expect($html)->toContain('<b>Suhu Kernel Silo 1</b>');
    expect($html)->toContain('Ripple Mill (Cracker)');
    expect($html)->toContain('Kernel Silo 1 &amp; 2');

    // Dan pernyataan bahwa angkanya tidak digabung — tanpa itu standar yang
    // identik tercetak dua kali berturut-turut terbaca seperti data
    // terduplikasi, dan seseorang akan "membersihkannya".
    expect($html)->toContain('Angkanya TIDAK dirata-ratakan menjadi satu');

    // Keempat angkanya berdiri sendiri: rata-rata gabungan 25,00 (ripple) dan
    // 75,00 (silo) tidak boleh muncul sama sekali — rata-rata pasangan akan
    // menyembunyikan ketidakseimbangan beban yang justru menjadi alasan
    // parameter ini diukur.
    expect($html)->toContain('20,00');
    expect($html)->toContain('30,00');
    expect($html)->toContain('70,00');
    expect($html)->toContain('80,00');
    expect($html)->not->toContain('25,00');
    expect($html)->not->toContain('75,00');
});

// =====================================================================
// Standar tanpa pengukuran, dan ketiadaan penandaan
// =====================================================================

it('skenario 8 — Final Kernel Dirt terbit pada bagiannya sendiri, lengkap dengan alasannya', function () {
    laporanKernelPlantComponentSeedTargets();

    $record = laporanKernelPlantComponentRecord($this->stationA, $this->day1);
    laporanKernelPlantComponentSlot($record, $this->slots[0], ['kernel_moisture_percent' => 6.5]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->toContain('data-testid="targets-without-metric"');
    expect($html)->toContain('data-testid="targets-without-metric-table"');
    // TEPAT SATU baris: daftar ini dibandingkan terhadap HIMPUNAN lima nama
    // parameter terpeta, bukan terhadap jumlah tujuh entri peta — kesamaan
    // jumlah (enam baris master lawan tujuh entri) akan membuat 'Final Kernel
    // Dirt' luput justru ketika ia satu-satunya yang tertinggal.
    expect(substr_count($html, 'data-testid="targets-without-metric-row"'))->toBe(1);
    expect($html)->toContain('Final Kernel Dirt');
    expect($html)->toContain('data-testid="targets-without-metric-reason"');
    expect($html)->toContain('Tidak ada kolom pengukurannya');
    expect($html)->not->toContain('data-testid="targets-all-measured"');
    expect($html)->not->toContain('data-testid="targets-master-empty"');
});

it('skenario 9 — bagian standar-tanpa-pengukuran TETAP digambar walau isinya kosong', function () {
    // Master yang hanya memuat KELIMA parameter terpeta: daftarnya kosong,
    // dan all_targets_measured menjadi penandanya.
    laporanKernelPlantComponentSeedTargets(fn (string $parameter) => $parameter !== 'Final Kernel Dirt');

    $record = laporanKernelPlantComponentRecord($this->stationA, $this->day1);
    laporanKernelPlantComponentSlot($record, $this->slots[0], ['shell_loss_percent' => 1.2]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Bagian yang hilang ketika kosong TIDAK DAPAT DIBEDAKAN dari bagian yang
    // belum pernah dibuat — dan di layar ini bagian itulah yang juga
    // memperlihatkan nama parameter master yang disunting tangan.
    expect($html)->toContain('data-testid="targets-without-metric"');
    expect($html)->toContain('data-testid="targets-all-measured"');
    expect($html)->not->toContain('data-testid="targets-without-metric-row"');
    expect($html)->not->toContain('data-testid="targets-master-empty"');
});

it('skenario 10 — baris master disunting tangan: standarnya terlepas dan PINDAH ke standar-tanpa-pengukuran', function () {
    // Satu suntingan ejaan pada master. Pemetaan kolom-ke-parameter bersifat
    // TETAP dan tidak mencocokkan teks, jadi kedua kolom ripple mill
    // kehilangan standarnya — dan keterlepasan itu harus TERLIHAT, bukan
    // diam-diam menghapus standar dari tabel atas.
    laporanKernelPlantComponentSeedTargets(null, ['Ripple Mill (Cracker)' => 'Ripple Mill Cracker']);

    $record = laporanKernelPlantComponentRecord($this->stationA, $this->day1);
    laporanKernelPlantComponentSlot($record, $this->slots[0], ['ripple_mill_1_amps' => 22.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Angka hasil ukurnya TETAP UTUH.
    expect($html)->toContain('22,00');
    expect(substr_count($html, 'data-testid="metric-row"'))->toBe(7);

    // Baris ripple mill kehilangan kedua kolom targetnya, dan mengatakannya.
    $row = laporanKernelPlantComponentMetricRow($html, 'Arus Ripple Mill 1');

    expect($row)->toContain('tidak terpeta ke satu pun baris master');
    expect($row)->toContain('target belum terisi pada master');

    // Dan standar yang terlepas muncul di bagian standar-tanpa-pengukuran,
    // bersama Final Kernel Dirt: DUA baris, bukan satu.
    expect(substr_count($html, 'data-testid="targets-without-metric-row"'))->toBe(2);
    expect($html)->toContain('Ripple Mill Cracker');
    expect($html)->toContain('Final Kernel Dirt');
    expect($html)->not->toContain('data-testid="targets-all-measured"');

    // DAN KETERANGAN BERBAGI STANDAR TIDAK MENGARANG BARIS MASTER YANG SUDAH
    // TIDAK ADA. Versi pertama blade mencetak equipment_parameter apa adanya,
    // sehingga pada keadaan INI — satu-satunya keadaan yang aturan "suntingan
    // master harus terlihat" dibangun untuk itu — ia merender
    //   master hanya memuat satu baris &ldquo;&rdquo; untuk keduanya
    // yaitu tanda kutip kosong yang MENGKLAIM sebuah baris master yang justru
    // baru saja lepas. Fallback nilai tidak cukup: kalimatnya sendiri menjadi
    // tidak benar, jadi yang bercabang adalah pernyataannya.
    $sharedNote = laporanKernelPlantComponentSlice($html, 'metric-shared-standard', '</small>');
    expect($sharedNote)->not->toContain('&ldquo;&rdquo;');
    expect($sharedNote)->not->toContain('master hanya memuat satu baris');
    expect($sharedNote)->toContain('tidak terpeta ke satu pun baris');
    // Pernyataan yang menentukan tetap ada — perbaikan di atas tidak boleh
    // menghapusnya bersama kalimat yang salah.
    expect($sharedNote)->toContain('TIDAK dirata-ratakan');
});

it('skenario 11 — master target kosong: keterangan terender, angka ukur tetap tampil', function () {
    $record = laporanKernelPlantComponentRecord($this->stationA, $this->day1);
    laporanKernelPlantComponentSlot($record, $this->slots[0], ['ripple_mill_1_amps' => 22.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->toContain('data-testid="targets-master-empty"');
    expect($html)->not->toContain('data-testid="targets-without-metric"');
    // Seeder yang belum dijalankan tidak menghapus pengukuran yang sudah
    // terjadi.
    expect($html)->toContain('data-testid="metrics-table"');
    expect($html)->toContain('22,00');
});

it('skenario 12 — tidak ada satu pun KELAS penanda di luar batas pada HTML ter-render', function () {
    laporanKernelPlantComponentSeedTargets();

    $record = laporanKernelPlantComponentRecord($this->stationA, $this->day1);
    // Jelas di luar rentang standarnya — '20 - 25 Amps' — dan tetap tidak
    // ditandai.
    laporanKernelPlantComponentSlot($record, $this->slots[0], [
        'ripple_mill_1_amps' => 99.0,
        'kernel_silo_1_temp_c' => 120.0,
    ]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Diasersi atas KETIADAAN NAMA KELAS, bukan ketiadaan frasa: kalimat
    // penjelasnya sendiri menyebut "di luar batas" untuk menyatakan bahwa
    // penandaan itu TIDAK ada, jadi penyisiran teks justru akan gagal pada
    // kalimat yang membuktikan klaimnya.
    foreach (['md-threshold', 'is-danger', 'is-warning', 'is-critical', 'md-chip--danger'] as $className) {
        expect($html)->not->toContain($className);
    }

    // Dan ketiadaannya DINYATAKAN, bukan dibiarkan terbaca sebagai fitur yang
    // belum selesai yang nanti akan "dilengkapi" seseorang.
    expect($html)->toContain('data-testid="no-flagging-note"');
    expect($html)->toContain('teks bebas');
    expect($html)->toContain('berlaku umum untuk seluruh mill');
});

// =====================================================================
// Downtime dan temuan — DUA blok, bukan satu
// =====================================================================

it('skenario 13 — downtime numerik terender dengan penyebut slot PENCATATnya', function () {
    $record = laporanKernelPlantComponentRecord($this->stationA, $this->day1);
    laporanKernelPlantComponentSlot($record, $this->slots[0], ['downtime_minutes' => 10]);
    laporanKernelPlantComponentSlot($record, $this->slots[1], ['downtime_minutes' => 20]);
    laporanKernelPlantComponentSlot($record, $this->slots[2], ['ripple_mill_1_amps' => 22.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->toContain('data-testid="downtime-total"');
    expect($html)->toContain('data-testid="downtime-recorded-slots"');
    expect($html)->toContain('data-testid="downtime-average"');
    expect($html)->not->toContain('data-testid="downtime-empty"');

    expect($html)->toContain('30 <span>menit</span>');
    // PENYEBUTNYA 2, bukan 3 slot terisi: slot tanpa catatan downtime BUKAN
    // nol menit, dan halaman ini menyatakannya.
    expect($html)->toContain('2 <span>slot</span>');
    expect($html)->toContain('15,0 <span>menit</span>');
    expect($html)->toContain('tidak dihitung sebagai nol');
    // Parameter ini tidak punya baris pada master, dan itu dinyatakan —
    // tanpa itu sel target yang kosong terbaca sebagai master yang belum diisi.
    expect($html)->toContain('tidak punya baris pada master target');
});

it('skenario 14 — nol yang TERCATAT dihitung; tanpa satu pun catatan, totalnya tanda pisah bukan nol', function () {
    // Separuh pertama: nol yang BENAR-BENAR tercatat adalah pernyataan
    // seseorang bahwa stasiun tidak berhenti.
    $record = laporanKernelPlantComponentRecord($this->stationA, $this->day1);
    laporanKernelPlantComponentSlot($record, $this->slots[0], ['downtime_minutes' => 0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->toContain('data-testid="downtime-total"');
    expect($html)->toContain('0 <span>menit</span>');
    expect($html)->toContain('1 <span>slot</span>');
    expect($html)->not->toContain('data-testid="downtime-empty"');

    // Separuh kedua: periode & line lain tanpa satu pun catatan downtime —
    // totalnya tanda pisah. Total nol terbaca seperti "stasiun tidak pernah
    // berhenti", padahal yang benar adalah "tidak ada yang mencatatnya".
    $lain = BusinessUnit::factory()->create(['name' => 'Mill Tanpa Downtime']);
    $station = Station::factory()->forBusinessUnit($lain)->kernelPlant()->create();
    $supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($lain)->create();

    Period::factory()->forBusinessUnit($lain)->stationType(StationTypeEnum::KernelPlant->value)
        ->range($this->periodStart, $this->periodEnd)->open()->named('Periode Tanpa Downtime')->create();

    $recordLain = laporanKernelPlantComponentRecord($station, $this->day1, 'KP-X');
    laporanKernelPlantComponentSlot($recordLain, $this->slots[0], ['ripple_mill_1_amps' => 22.0]);

    $htmlLain = Livewire::actingAs($supervisor)
        ->test(LaporanKernelPlant::class)
        ->set('productionLineId', (string) $station->production_line_id)
        ->html();

    expect($htmlLain)->toContain('data-testid="downtime-empty"');
    expect(laporanKernelPlantComponentSlice($htmlLain, 'downtime-total', '</article>'))
        ->toContain('&mdash;');
    expect($htmlLain)->not->toContain('0 <span>menit</span>');
});

it('skenario 15 — temuan dikelompokkan HARFIAH pada blok yang TERPISAH dari downtime', function () {
    $record = laporanKernelPlantComponentRecord($this->stationA, $this->day1);

    foreach (range(0, 2) as $index) {
        laporanKernelPlantComponentSlot($record, $this->slots[$index], ['findings' => 'Belt kendur']);
    }

    // Ejaan kedua untuk hal yang sama: DUA baris, bukan satu.
    laporanKernelPlantComponentSlot($record, $this->slots[3], ['findings' => 'belt kendur']);
    // Satu slot yang membawa KEDUANYA — dan tidak terhitung dua kali di bawah
    // satu pengertian.
    laporanKernelPlantComponentSlot($record, $this->slots[4], ['findings' => 'Belt kendur', 'downtime_minutes' => 5]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect(substr_count($html, 'data-testid="findings-row"'))->toBe(2);
    expect($html)->toContain('data-testid="findings-note"');
    // Tanpa keterangan itu, dua baris mirip terbaca sebagai cacat laporan
    // alih-alih sebagai bentuk datanya.
    expect($html)->toContain('harfiah');

    // DUA bagian yang berbeda, keduanya ada, dan temuan berada SETELAH
    // downtime: satu menjawab "berapa lama", satu "apa yang terlihat".
    expect($html)->toContain('data-testid="downtime"');
    expect(strpos($html, 'data-testid="downtime"'))
        ->toBeLessThan(strpos($html, 'data-testid="findings"'));
    expect($html)->toContain('data-testid="downtime-recorded-slots"');
});

it('skenario 16 — tanpa temuan: keterangan terender, bukan tabel kosong', function () {
    $record = laporanKernelPlantComponentRecord($this->stationA, $this->day1);
    laporanKernelPlantComponentSlot($record, $this->slots[0], ['ripple_mill_1_amps' => 22.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->toContain('data-testid="findings-empty"');
    expect($html)->not->toContain('data-testid="findings-table"');
});

// =====================================================================
// Kelengkapan record — kelengkapan, BUKAN penyaring
// =====================================================================

it('skenario 17 — record draft IKUT terhitung dan jumlahnya dinyatakan; status verifikasi bukan penyaring', function () {
    $draft = laporanKernelPlantComponentRecord($this->stationA, $this->day1, 'KP-1', [
        'status' => RecordStatus::DraftOngoing,
    ]);
    laporanKernelPlantComponentSlot($draft, $this->slots[0], ['ripple_mill_1_amps' => 22.0]);

    $synced = laporanKernelPlantComponentRecord($this->stationA, $this->day2, 'KP-2');
    laporanKernelPlantComponentSlot($synced, $this->slots[0], ['ripple_mill_1_amps' => 24.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->toContain('data-testid="completeness"');
    expect(laporanKernelPlantComponentSlice($html, 'record-count', '</article>'))->toContain('2 <span>record</span>');
    expect(laporanKernelPlantComponentSlice($html, 'draft-record-count', '</article>'))->toContain('1 <span>record</span>');
    // Angka draft-nya IKUT terhitung: kedua pembacaan ada pada tabel.
    expect($html)->toContain('22,00');
    expect($html)->toContain('24,00');
    expect($html)->toContain('data-testid="records-not-checked"');
    expect($html)->toContain('data-testid="records-not-acknowledged"');
});

// =====================================================================
// Keadaan yang HANYA lapisan ini bisa menyusunnya
// =====================================================================

it('skenario 18 — mill hanya punya SATU Production Line: tetap TIDAK dipilih otomatis', function () {
    $record = laporanKernelPlantComponentRecord($this->stationA, $this->day1);
    laporanKernelPlantComponentSlot($record, $this->slots[0], ['ripple_mill_1_amps' => 22.0]);

    $component = Livewire::actingAs($this->supervisor)->test(LaporanKernelPlant::class);

    // Mill A memang hanya punya satu line — dan itu TIDAK membuatnya terpilih.
    expect($component->viewData('productionLineOptions'))->toHaveCount(1);
    expect($component->get('productionLineId'))->toBe('');

    $html = $component->html();

    // Memilih line adalah keputusan pembaca; menebaknya akan membuat ia
    // menyangka sedang melihat seluruh mill.
    expect($html)->toContain('data-testid="select-production-line-hint"');
    expect($html)->toContain('tidak menampilkan angka seluruh mill');

    foreach (LAPORAN_KERNEL_PLANT_FIGURE_TESTIDS as $testid) {
        expect($html)->not->toContain('data-testid="'.$testid.'"');
    }
});

it('skenario 19 — mill tanpa SATU PUN periode Kernel Plant: arahan, tanpa satu angka pun', function () {
    $tanpaPeriode = BusinessUnit::factory()->create(['name' => 'Mill Tanpa Periode']);
    $station = Station::factory()->forBusinessUnit($tanpaPeriode)->kernelPlant()->create();
    $supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($tanpaPeriode)->create();

    // Record ADA, periodenya tidak — jadi kekosongannya bukan kekosongan data.
    $record = laporanKernelPlantComponentRecord($station, $this->day1, 'KP-X');
    laporanKernelPlantComponentSlot($record, $this->slots[0], ['ripple_mill_1_amps' => 99.0]);

    $html = Livewire::actingAs($supervisor)
        ->test(LaporanKernelPlant::class)
        ->set('productionLineId', (string) $station->production_line_id)
        ->html();

    expect($html)->toContain('data-testid="no-period-hint"');
    expect($html)->not->toContain('99,00');

    foreach (LAPORAN_KERNEL_PLANT_FIGURE_TESTIDS as $testid) {
        expect($html)->not->toContain('data-testid="'.$testid.'"');
    }
});

it('skenario 20 — periode berisi record tetapi NOL slot terisi: persennya 0,0% BUKAN tanda pisah, dan ketujuh baris tetap ada', function () {
    laporanKernelPlantComponentSeedTargets();

    $record = laporanKernelPlantComponentRecord($this->stationA, $this->day1);
    // Baris slot yang KESEMBILAN kolom bacaannya kosong: ia disaring keluar
    // dari slot terisi, tetapi unitnya tetap punya record.
    laporanKernelPlantComponentSlot($record, $this->slots[0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // PENYEBUTNYA ADA — 1 unit x 10 hari x 24 slot — jadi persennya adalah
    // nol yang terukur, bukan ketiadaan penyebut. Diasersi pada SEL cakupan,
    // bukan atas halaman: kedua kolom target master memuat '≤ 7.0%' dan
    // '≤ 6.0%', sehingga asersi persen yang tidak dibatasi akan menyentuh
    // teks master.
    $persen = laporanKernelPlantComponentSlice($html, 'coverage-percent');

    expect($persen)->toContain('0,0%');
    expect($persen)->not->toContain('&mdash;');

    expect($html)->toContain('data-testid="coverage"');
    expect($html)->toContain('data-testid="empty-state"');

    // TABEL PARAMETER TETAP DIGAMBAR, lengkap ketujuh barisnya dengan
    // penyebut nol: baris yang hilang terbaca sebagai "parameter itu tidak
    // ada", padahal yang benar adalah "tidak ada yang mengukurnya".
    expect($html)->toContain('data-testid="metrics-table"');
    expect(substr_count($html, 'data-testid="metric-row"'))->toBe(7);
    expect(substr_count($html, 'data-testid="metric-denominator"'))->toBe(7);
    expect(substr_count($html, '0 dari 0 slot'))->toBe(7);
    expect($html)->toContain('tidak tersedia');

    // Rekap per unit, rekap harian, blok downtime dan daftar temuan TIDAK
    // digambar — tabel kosong akan terbaca sebagai hasil pengukuran nol.
    expect($html)->not->toContain('data-testid="by-kernel-plant-table"');
    expect($html)->not->toContain('data-testid="daily-table"');
    expect($html)->not->toContain('data-testid="downtime"');
    expect($html)->not->toContain('data-testid="findings"');
});

it('skenario 20b — pasangan kontrol: tanpa satu pun record, penyebutnya tidak terbentuk dan persennya tanda pisah', function () {
    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Tidak ada unit yang punya record -> kernel_plant_count 0 -> expected_slots
    // 0. Persentase nol di sini akan MENGKLAIM ada yang diukur dan hasilnya
    // nol; yang benar adalah penyebutnya belum ada.
    $persen = laporanKernelPlantComponentSlice($html, 'coverage-percent');

    expect($persen)->toContain('&mdash;');
    expect($persen)->not->toContain('0,0%');
    expect($html)->toContain('data-testid="empty-state"');
});

it('skenario 21 — periode BELUM MULAI: days_counted diperiksa SEBELUM period_running', function () {
    $belumMulai = Period::factory()->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelPlant->value)
        ->range(now()->addDays(5)->toDateString(), now()->addDays(20)->toDateString())
        ->open()->named('Periode Belum Mulai')->create();

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelPlant::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $belumMulai->id)
        ->html();

    // ReportPeriodDays::isRunning() JUGA true untuk periode yang seluruh
    // rentangnya masih di masa depan, jadi memeriksa period_running lebih dulu
    // akan menjelaskan periode yang belum mulai sebagai "sedang berjalan" —
    // padahal tidak ada satu hari pun yang dihitung.
    expect($html)->toContain('data-testid="coverage-not-started-note"');
    expect($html)->toContain('periode BELUM MULAI');
    expect($html)->not->toContain('data-testid="coverage-running-note"');
    expect($html)->not->toContain('periode masih berjalan');

    // Dan persennya tanda pisah: tidak ada hari yang dapat menjadi pembagi.
    expect(laporanKernelPlantComponentSlice($html, 'coverage-percent'))->toContain('&mdash;');
});

it('skenario 22 — periode sedang berjalan: keterangannya menyebut HARI TERAKHIR YANG TERHITUNG, bukan tanggal akhir', function () {
    $berjalan = Period::factory()->forBusinessUnit($this->businessUnitA)
        ->stationType(StationTypeEnum::KernelPlant->value)
        ->range(now()->subDays(4)->toDateString(), now()->addDays(10)->toDateString())
        ->open()->named('Periode Berjalan')->create();

    $record = laporanKernelPlantComponentRecord($this->stationA, now()->subDays(1)->toDateString());
    laporanKernelPlantComponentSlot($record, $this->slots[0], ['ripple_mill_1_amps' => 22.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelPlant::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $berjalan->id)
        ->html();

    expect($html)->toContain('data-testid="coverage-running-note"');
    expect($html)->not->toContain('data-testid="coverage-not-started-note"');

    // Tanggal akhir yang BELUM TIBA akan terbaca seperti hari yang sudah ikut
    // dihitung, jadi keterangannya harus menyebut hari ini.
    $catatan = laporanKernelPlantComponentSlice($html, 'coverage-running-note');

    expect($catatan)->toContain('dihitung sampai '.laporanKernelPlantComponentTanggal(now()->toDateString()));
    expect($catatan)->not->toContain(laporanKernelPlantComponentTanggal(now()->addDays(10)->toDateString()));
});

it('skenario 23 — akun terikat mill dengan business_unit_id null: gagal tertutup, TANPA pemilih mill', function () {
    $tanpaMill = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $component = Livewire::actingAs($tanpaMill)->test(LaporanKernelPlant::class);
    $html = $component->html();

    expect($html)->toContain('data-testid="no-mill-hint"');
    expect($html)->toContain('Hubungi Admin');

    // Pemilih mill TIDAK ditawarkan sebagai gantinya — menawarkannya berarti
    // menyerahkan daftar seluruh mill kepada peran yang semestinya terikat
    // pada tepat satu. Daftar itu bahkan tidak dibaca.
    expect($html)->not->toContain('data-testid="mill-select"');
    expect($html)->not->toContain('data-testid="mill-name"');
    expect($component->viewData('businessUnitOptions'))->toBe([]);

    foreach (LAPORAN_KERNEL_PLANT_FIGURE_TESTIDS as $testid) {
        expect($html)->not->toContain('data-testid="'.$testid.'"');
    }
});

// =====================================================================
// Penjagaan lintas mill lewat properti komponen
// =====================================================================

it('skenario 24 — id mill milik mill lain lewat properti: DIABAIKAN, tetap mill sendiri', function () {
    $record = laporanKernelPlantComponentRecord($this->stationA, $this->day1, 'KP-A');
    laporanKernelPlantComponentSlot($record, $this->slots[0], ['ripple_mill_1_amps' => 11.0]);

    $recordB = laporanKernelPlantComponentRecord($this->stationB, $this->day1, 'KP-B');
    laporanKernelPlantComponentSlot($recordB, $this->slots[0], ['ripple_mill_1_amps' => 99.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelPlant::class)
        ->set('businessUnitId', (string) $this->businessUnitB->id)
        ->set('productionLineId', $this->lineA)
        ->html();

    // resolvedBusinessUnitId() sama sekali tidak membaca properti itu untuk
    // peran terikat mill, jadi memaksanya tidak mengubah apa pun — HTTP 200,
    // sengaja bukan 403.
    expect($html)->toContain('Mill Alpha');
    expect($html)->not->toContain('Mill Beta');
    expect($html)->toContain('11,00');
    expect($html)->not->toContain('99,00');
});

it('skenario 25 — id line milik mill lain lewat properti: jatuh ke BELUM MEMILIH, bukan data mill lain', function () {
    $recordB = laporanKernelPlantComponentRecord($this->stationB, $this->day1, 'KP-B');
    laporanKernelPlantComponentSlot($recordB, $this->slots[0], ['ripple_mill_1_amps' => 99.0]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelPlant::class)
        ->set('productionLineId', $this->lineB);

    $html = $component->html();

    // Line mill lain adalah pegangan KONKRET atas data mill lain, jadi ia
    // dibuang oleh keepProductionLineValid() — jatuh ke "belum memilih", bukan
    // ke line pertama.
    expect($component->get('productionLineId'))->toBe('');
    expect($html)->toContain('data-testid="select-production-line-hint"');
    expect($html)->not->toContain('99,00');
});

it('skenario 26 — periode milik mill lain lewat properti: penolakan TERLIHAT, tanpa satu angka mill lain', function () {
    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)
        ->stationType(StationTypeEnum::KernelPlant->value)
        ->range($this->periodStart, $this->periodEnd)->open()->named('Periode Beta')->create();

    $recordB = laporanKernelPlantComponentRecord($this->stationB, $this->day1, 'KP-B');
    laporanKernelPlantComponentSlot($recordB, $this->slots[0], ['ripple_mill_1_amps' => 99.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelPlant::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $periodB->id)
        ->html();

    // Penukaran diam-diam akan menjawab penyelidikan lintas mill dengan angka
    // mill lain DI BAWAH ID YANG DITANYAKAN.
    expect($html)->toContain('data-testid="forbidden-notice"');
    expect($html)->not->toContain('99,00');
    expect($html)->not->toContain('data-testid="metrics-table"');
});

it('skenario 27 — Admin berganti mill: pilihan line direset dan periode dikosongkan', function () {
    ProductionLine::factory()->create(['business_unit_id' => $this->businessUnitB->id]);

    $record = laporanKernelPlantComponentRecord($this->stationA, $this->day1);
    laporanKernelPlantComponentSlot($record, $this->slots[0], ['ripple_mill_1_amps' => 22.0]);

    $component = Livewire::actingAs($this->admin)
        ->test(LaporanKernelPlant::class)
        ->set('businessUnitId', (string) $this->businessUnitA->id)
        ->set('productionLineId', $this->lineA);

    expect($component->html())->toContain('data-testid="coverage"');
    expect($component->get('periodId'))->toBe((string) $this->periodA->id);

    $html = $component->set('businessUnitId', (string) $this->businessUnitB->id)->html();

    // Line mill sebelumnya dibuang oleh keepProductionLineValid(), jadi tidak
    // ada satu angka mill lama yang dapat bertahan di layar.
    expect($component->get('productionLineId'))->toBe('');
    expect($component->get('periodId'))->toBe('');
    expect($html)->toContain('data-testid="select-production-line-hint"');
    expect($html)->not->toContain('22,00');
});

// =====================================================================
// Rekap harian
// =====================================================================

it('skenario 28 — rekap harian terbuka secara bawaan dan dapat ditutup tanpa mengubah satu angka pun', function () {
    $record = laporanKernelPlantComponentRecord($this->stationA, $this->day1);
    laporanKernelPlantComponentSlot($record, $this->slots[0], ['ripple_mill_1_amps' => 22.0]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelPlant::class)
        ->set('productionLineId', $this->lineA);

    expect($component->html())->toContain('data-testid="daily-table"');

    $closed = $component->call('toggleDailyRecap')->html();

    // Ditutup berarti BENAR-BENAR hilang dari DOM, bukan sekadar
    // disembunyikan — keadaan terlihat dan DOM tidak boleh berselisih.
    expect($closed)->not->toContain('data-testid="daily-table"');
    expect($closed)->toContain('data-testid="daily-recap-card"');
    // Dan angka utamanya tidak bergerak sedikit pun.
    expect($closed)->toContain('data-testid="metrics-table"');
    expect($closed)->toContain('22,00');

    expect($component->call('toggleDailyRecap')->html())->toContain('data-testid="daily-table"');
});

it('skenario 29 — baris total periode DIHITUNG ULANG, bukan merata-ratakan rata-rata harian', function () {
    // Hari A: 10 slot bernilai 10. Hari B: 1 slot bernilai 100.
    $dayA = laporanKernelPlantComponentRecord($this->stationA, $this->day1, 'KP-1');

    foreach (range(0, 9) as $index) {
        laporanKernelPlantComponentSlot($dayA, $this->slots[$index], ['ripple_mill_1_amps' => 10.0]);
    }

    $dayB = laporanKernelPlantComponentRecord($this->stationA, $this->day2, 'KP-1');
    laporanKernelPlantComponentSlot($dayB, $this->slots[0], ['ripple_mill_1_amps' => 100.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelPlant::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    $totalRow = explode('data-testid="daily-row-total"', $html)[1] ?? '';

    // 18,18 — BUKAN 55,00. Rata-rata dari rata-rata memberi bobot yang sama
    // pada hari berisi satu slot dan hari berisi sepuluh.
    expect($totalRow)->toContain('18,18');
    expect($totalRow)->not->toContain('55,00');
    expect($html)->toContain('data-testid="daily-total-note"');
});

// =====================================================================
// Ekspor
// =====================================================================

it('skenario 30 — ekspor mengalirkan berkas untuk periode dan line terpilih', function () {
    $record = laporanKernelPlantComponentRecord($this->stationA, $this->day1, 'KP-9');
    laporanKernelPlantComponentSlot($record, $this->slots[0], ['ripple_mill_1_amps' => 22.0]);

    Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelPlant::class)
        ->set('productionLineId', $this->lineA)
        ->call('exportCsv', 'csv')
        ->assertFileDownloaded();
});

it('skenario 31 — ekspor tanpa line terpilih tidak menghasilkan apa pun', function () {
    laporanKernelPlantComponentRecord($this->stationA, $this->day1, 'KP-9');

    $component = Livewire::actingAs($this->supervisor)->test(LaporanKernelPlant::class);

    // Dijaga DI DALAM metodenya, bukan hanya di blade — sehingga pemanggilan
    // langsung tidak dapat mengunduh berkas yang mencampur seluruh line mill.
    expect($component->call('exportCsv', 'csv')->effects)->not->toHaveKey('download');
});

// =====================================================================
// Peran yang TIDAK dibuka — dan mengapa daftarnya tidak boleh didelegasikan
// =====================================================================

it('skenario 32 — Operator ditolak pada RUTE-nya, bukan hanya pada komponennya', function () {
    // Component test TIDAK melewati middleware, jadi hanya baris ini yang
    // membuktikan penjagaan rutenya ('role:supervisor,mill_management,admin').
    $response = $this->actingAs($this->operator, 'web')->get('/reports/kernel-plant');

    $response->assertStatus(403);
    $response->assertDontSee('Laporan Periode');

    // Dan peran yang memang dibuka benar-benar sampai ke halamannya — tanpa
    // ini, 403 di atas akan tetap hijau pada rute yang menolak semua orang.
    $this->actingAs($this->supervisor, 'web')
        ->get('/reports/kernel-plant')
        ->assertOk()
        ->assertSee('data-testid="laporan-kernel-plant"', false);

    // Lapisan komponen: mount() menolak Operator yang dipasang langsung.
    $html = Livewire::actingAs($this->operator)->test(LaporanKernelPlant::class)->html();

    expect($html)->toContain('Forbidden');
    expect($html)->not->toContain('data-testid="laporan-kernel-plant"');
    expect($html)->not->toContain('data-testid="metrics-table"');
});

it('skenario 33 — canAccess() memegang daftar perannya SENDIRI dan tidak mendelegasikan ke guardAccess()', function () {
    // SERVICE-nya MEMANG MENERIMA OPERATOR — kembaran mobile screen-155
    // memakai ulang keempat endpoint-nya — dan inilah yang membuat delegasi
    // berbahaya: bila canAccess() memanggil guardAccess(), layar web ini
    // terbuka untuk Operator tanpa satu galat pun.
    $this->actingAs($this->operator, 'web');

    expect(fn () => app(KernelPlantReportService::class)->guardAccess())->not->toThrow(Exception::class);

    // Diasersi juga SECARA STRUKTURAL atas badan metodenya, karena asersi
    // perilaku di atas akan tetap hijau pada hari seseorang mempersempit
    // guardAccess() dan menjadikan delegasi "tampak benar".
    $method = new ReflectionMethod(LaporanKernelPlant::class, 'canAccess');
    $source = implode('', array_slice(
        file($method->getFileName()),
        $method->getStartLine() - 1,
        $method->getEndLine() - $method->getStartLine() + 1,
    ));

    expect($source)->not->toContain('guardAccess');
    expect($source)->not->toContain('KernelPlantReportService');
    // Ketiga peran disebut di tempat, dan Operator tidak disebut sama sekali.
    expect($source)->toContain('UserRole::Supervisor->value');
    expect($source)->toContain('UserRole::MillManagement->value');
    expect($source)->toContain('UserRole::Admin->value');
    expect($source)->not->toContain('UserRole::Operator');
});

it('skenario 34 — layar hanya membaca: tidak ada satu metode publik yang berbau tulis, dan data tidak bergerak', function () {
    $record = laporanKernelPlantComponentRecord($this->stationA, $this->day1);
    laporanKernelPlantComponentSlot($record, $this->slots[0], ['ripple_mill_1_amps' => 22.0]);

    $recordsBefore = KernelPlantRecord::query()->orderBy('id')->get()->toJson();
    $detailsBefore = KernelPlantDetail::query()->orderBy('id')->get()->toJson();

    Livewire::actingAs($this->supervisor)
        ->test(LaporanKernelPlant::class)
        ->set('productionLineId', $this->lineA)
        ->call('toggleDailyRecap')
        ->call('toggleDailyRecap')
        ->html();

    expect(KernelPlantRecord::query()->orderBy('id')->get()->toJson())->toBe($recordsBefore);
    expect(KernelPlantDetail::query()->orderBy('id')->get()->toJson())->toBe($detailsBefore);

    $methods = array_map(
        fn (ReflectionMethod $method) => $method->getName(),
        (new ReflectionClass(LaporanKernelPlant::class))->getMethods(ReflectionMethod::IS_PUBLIC),
    );

    foreach (['save', 'store', 'create', 'update', 'delete', 'destroy', 'submit', 'verify', 'close'] as $writeish) {
        expect($methods)->not->toContain($writeish);
    }
});

// =====================================================================
// Pintu masuk dari Laporan Stasiun (screen-140)
// =====================================================================

it('skenario 35 — tile Kernel Plant pada Laporan Stasiun AKTIF dan menunjuk ke rute laporan ini', function () {
    // SATU BARIS yang menentukan layar ini dapat dicapai: entri 'kernel-plant'
    // pada StationReportService::REPORT_ROUTES. Tanpa asersi ini, laporannya
    // bisa lengkap, seluruh test backend hijau, dan tile-nya tetap kelabu —
    // pola kegagalan yang sudah pernah terjadi pada laporan Cages & Tracks.
    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanStasiun::class)
        ->set('productionLineId', $this->lineA)
        ->assertSeeHtml('data-testid="station-grid"')
        ->assertSeeHtml('data-testid="station-tile-kernel-plant"')
        ->html();

    // Kuncinya 'kernel-plant' BERTANDA HUBUNG — itulah yang dipegang
    // StationTypeEnum::KernelPlant->value; kunci snake_case akan ter-compile,
    // tampak benar, dan tidak pernah cocok dengan satu baris master pun.
    $pattern = '/<(a|span)\b[^>]*data-testid="station-tile-kernel-plant"[^>]*>.*?<\/\1>/s';

    expect(preg_match($pattern, $html, $matches))->toBe(1);

    $tile = $matches[0];

    expect($tile)->toContain('href=');
    expect($tile)->toContain('/reports/kernel-plant');
    expect($tile)->not->toContain('disabled');
    // Mill DAN line ikut terbawa, sehingga layar tujuan tidak meminta memilih
    // lagi — itu sebabnya kedua properti layar ini membawa #[Url(as: ...)].
    expect($tile)->toContain('business_unit_id='.$this->businessUnitA->id);
    expect($tile)->toContain('production_line_id='.$this->lineA);
});
