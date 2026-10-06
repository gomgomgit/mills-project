<?php

/**
 * LaporanDepricarpingTest (Livewire) — screen-152--laporan-depricarping-web, the web
 * page itself.
 *
 * One test per test_scenarios[].component_test on screen-152's tech spec. The
 * figures are proven against the service in
 * tests/Unit/Services/DepricarpingReportServiceTest.php and over HTTP in
 * tests/Feature/Api/LaporanDepricarpingTest.php; what this file proves is what
 * the PAGE does with them — which pickers exist for which role, which empty
 * state is rendered, and the things the screen must never let a reader
 * misread.
 *
 * ASSERTED ON THE RENDERED HTML, not on component state, wherever the claim
 * is about what the reader sees. A data-testid present in the markup but never
 * reached by the blade's conditionals would still satisfy a state assertion.
 */

use App\Enums\UserRole;
use App\Livewire\Dashboard\LaporanDepricarping;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\DepricarpingDetail;
use App\Models\DepricarpingOperationalTarget;
use App\Models\DepricarpingRecord;
use App\Models\User;
use App\Services\DepricarpingRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** Every data-testid that only exists once a figure is actually rendered. */
const LAPORAN_DEPRICARPING_FIGURE_TESTIDS = [
    'coverage',
    'coverage-slots',
    'coverage-denominator',
    'metrics',
    'metrics-table',
    'no-flagging-note',
    'by-presser',
    // DUA bagian, bukan satu: downtime (angka) dan findings (teks) sengaja
    // terpisah — satu menjawab "berapa lama", satu "apa yang terlihat".
    'downtime',
    'findings',
    'completeness',
    'daily-recap-card',
];

function laporanDepricarpingComponentRecord(
    Station $station,
    string $date,
    string $presserId = 'PR-1',
    array $attributes = [],
): DepricarpingRecord {
    return DepricarpingRecord::factory()
        ->forStation($station)
        ->onDate($date)
        ->create(array_merge(['presser_id' => $presserId, 'note' => 'Catatan harian'], $attributes));
}

function laporanDepricarpingComponentSlot(DepricarpingRecord $record, string $timeSlot, array $values = []): DepricarpingDetail
{
    return DepricarpingDetail::factory()
        ->forRecord($record)
        ->timeSlot($timeSlot)
        ->create($values);
}

function laporanDepricarpingComponentSeedTargets(): void
{
    // Ketujuh baris master, persis seperti DepricarpingOperationalTargetSeeder.
    // TUJUH parameter untuk LIMA kolom ukur: 'Nut Breakage Rate' dan 'Press
    // Cake Moisture' tidak punya kolom pengukuran di mana pun pada skema ini.
    $rows = [
        ['Fan Static Pressure', '40 - 50 mmH2O', '< 35 or > 55 mmH2O', 'Low pressure drops fibre early (heavy losses). High pressure sucks clean small nuts into the fibre cyclone.'],
        ['Polishing Drum Speed', '20 - 24 RPM', '< 18 or > 26 RPM', 'Slower speeds fail to detach residual mesocarp fibre from nuts. Higher speeds cause premature mechanical wear.'],
        ['Air Velocity (Aspirator)', '12 - 14 m/s', '< 10 or > 16 m/s', 'Controls the pneumatic separation gap. Must cleanly lift light fiber hulls while letting heavy polished nuts sink.'],
        ['Fibre Moisture Content', '33% - 37%', '> 40%', 'High moisture reduces downstream boiler combustion efficiency and indicates poor press station performance.'],
        ['Kernel Loss in Fibre', '< 0.50%', '> 1.00%', 'Direct operational revenue loss. Signifies an unstable pneumatic lifting balance or unstripped cake clumps.'],
        ['Nut Silo Temperature', '60C - 70C', '< 55C or > 75C', 'Crucial for nut conditioning. Correct heat shrinks the kernel inside the shell, enabling high-efficiency cracking.'],
    ];

    foreach ($rows as $index => [$parameter, $range, $limit, $justification]) {
        DepricarpingOperationalTarget::create([
            'parameter_metric' => $parameter,
            'target_range' => $range,
            'critical_limit' => $limit,
            'operational_consequence_justification' => $justification,
            'sort_order' => $index + 1,
        ]);
    }
}

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->depricarping()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->depricarping()->create();

    $this->lineA = (string) $this->stationA->production_line_id;
    $this->lineB = (string) $this->stationB->production_line_id;

    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    $this->periodA = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('depricarping')
        ->range('2026-09-01', '2026-09-10')->open()->named('Periode September Alpha')->create();

    $this->slots = DepricarpingRecordService::canonicalTimeSlots();
});

// =====================================================================
// Jalur sukses
// =====================================================================

it('skenario 1 — Supervisor: tanpa pemilih mill, seluruh bagian laporan terender', function () {
    laporanDepricarpingComponentSeedTargets();

    $record = laporanDepricarpingComponentRecord($this->stationA, '2026-09-04');

    foreach (range(0, 3) as $index) {
        $values = ['fan_static_pressure_mmh2o' => 30.0];

        if ($index < 2) {
            $values['polishing_drum_speed_rpm'] = 22.0;
        }

        laporanDepricarpingComponentSlot($record, $this->slots[$index], $values);
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanDepricarping::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->not->toContain('data-testid="mill-select"');
    expect($html)->toContain('data-testid="mill-name"');

    foreach (LAPORAN_DEPRICARPING_FIGURE_TESTIDS as $testid) {
        expect($html)->toContain('data-testid="'.$testid.'"');
    }

    // KETUJUH baris parameter, masing-masing dengan standarnya.
    expect($html)->toContain('Tekanan Statis Fan');
    expect($html)->toContain('Putaran Polishing Drum');
    expect($html)->toContain('Suhu Nut Silo 1');
    expect($html)->toContain('Suhu Nut Silo 2');
    expect($html)->toContain('20 - 24 RPM');
    expect($html)->toContain('&lt; 18 or &gt; 26 RPM');
    // KOLOM KEEMPAT master — tidak ada padanannya pada master Threshing
    // maupun Pressing, dan paling mudah hilang saat tabel dipersempit.
    expect($html)->toContain('premature mechanical wear');
    expect($html)->toContain('data-testid="metric-consequence"');

    // Tidak ada satu pun kontrol tulis.
    expect($html)->not->toContain('>Simpan<');
    expect($html)->not->toContain('>Hapus<');
});

it('skenario 1b — Mill Management melihat layar yang sama persis dengan Supervisor', function () {
    $record = laporanDepricarpingComponentRecord($this->stationA, '2026-09-04');
    laporanDepricarpingComponentSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 30.0]);

    $html = Livewire::actingAs($this->millManagement)
        ->test(LaporanDepricarping::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->not->toContain('data-testid="mill-select"');
    expect($html)->toContain('data-testid="metrics-table"');
});

it('skenario 2 — Admin: pemilih mill dirender, dan tanpa mill tidak ada satu angka pun', function () {
    $record = laporanDepricarpingComponentRecord($this->stationA, '2026-09-04');
    laporanDepricarpingComponentSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 30.0]);

    $component = Livewire::actingAs($this->admin)->test(LaporanDepricarping::class);

    $html = $component->html();

    expect($html)->toContain('data-testid="mill-select"');
    expect($html)->toContain('data-testid="mill-select-hint"');

    foreach (LAPORAN_DEPRICARPING_FIGURE_TESTIDS as $testid) {
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
    $record = laporanDepricarpingComponentRecord($this->stationA, '2026-09-04');
    laporanDepricarpingComponentSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 30.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanDepricarping::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Periode yang terisi seperlima pun menghasilkan rata-rata yang terlihat
    // rapi, jadi pembaca harus melihat cakupannya LEBIH DULU. Diasersi atas
    // POSISI, bukan sekadar kehadiran.
    expect(strpos($html, 'data-testid="coverage"'))
        ->toBeLessThan(strpos($html, 'data-testid="metrics"'));
});

it('skenario 4 — cakupan mencetak ketiga angka pembentuk penyebutnya', function () {
    foreach (['PR-1', 'PR-2'] as $presser) {
        $record = laporanDepricarpingComponentRecord($this->stationA, '2026-09-04', $presser);
        laporanDepricarpingComponentSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 30.0]);
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanDepricarping::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->toContain('data-testid="coverage-denominator"');
    // 2 presser x 10 hari x 24 slot = 480.
    expect($html)->toContain('480');
    expect($html)->toContain('presser &times; 10 hari &times; 24 slot');
});

// =====================================================================
// Penyebut per parameter
// =====================================================================

it('skenario 5 — tiap baris parameter mencetak penyebutnya sendiri', function () {
    $record = laporanDepricarpingComponentRecord($this->stationA, '2026-09-04');

    foreach (range(0, 3) as $index) {
        $values = ['fan_static_pressure_mmh2o' => 30.0];

        if ($index < 2) {
            $values['polishing_drum_speed_rpm'] = 22.0;
        }

        laporanDepricarpingComponentSlot($record, $this->slots[$index], $values);
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanDepricarping::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Dua penyebut BERBEDA pada halaman yang sama — itulah yang membuat
    // asersi ini bermakna alih-alih selalu hijau.
    expect($html)->toContain('4 dari 4 slot');
    expect($html)->toContain('2 dari 4 slot');
    expect(substr_count($html, 'data-testid="metric-denominator"'))->toBe(7);
});

it('skenario 6 — baris parameter tanpa pembacaan TETAP terender dengan tidak tersedia', function () {
    $record = laporanDepricarpingComponentRecord($this->stationA, '2026-09-04');
    laporanDepricarpingComponentSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 30.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanDepricarping::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Kelima baris ada, meski hanya satu kolom yang pernah diukur: baris yang
    // hilang terbaca sebagai "tidak ada parameter ini", padahal yang benar
    // adalah "tidak ada yang mengukurnya".
    expect(substr_count($html, 'data-testid="metric-row"'))->toBe(7);
    expect($html)->toContain('Kecepatan Udara Aspirator');
    expect($html)->toContain('tidak tersedia');
    expect($html)->toContain('0 dari 1 slot');
});

// =====================================================================
// Standar operasional, dan ketiadaan penandaan
// =====================================================================

it('skenario 7 — standar dan rencana tindakan berada pada baris yang sama dengan angkanya', function () {
    laporanDepricarpingComponentSeedTargets();

    $record = laporanDepricarpingComponentRecord($this->stationA, '2026-09-04');
    laporanDepricarpingComponentSlot($record, '07:00', ['polishing_drum_speed_rpm' => 27.4]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanDepricarping::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Potong satu baris <tr> dan buktikan ketiganya ada di dalamnya — menaruh
    // standarnya di halaman lain akan membuang satu-satunya keunggulan yang
    // diberikan master target.
    $rows = explode('data-testid="metric-row"', $html);
    $levelRow = collect($rows)->first(fn ($row) => str_contains($row, 'Putaran Polishing Drum')) ?? '';

    // KEEMPATNYA pada baris yang sama: angka, rentang target, batas kritis,
    // dan akibat bila dilewati. Menaruh salah satu di bagian lain akan
    // menghapus sebagian informasi yang dipakai pembaca untuk memutuskan —
    // dan yang keempat itulah yang membedakan parameter mana yang mendesak.
    expect($levelRow)->toContain('27,40');
    expect($levelRow)->toContain('20 - 24 RPM');
    expect($levelRow)->toContain('&lt; 18 or &gt; 26 RPM');
    expect($levelRow)->toContain('premature mechanical wear');
});

it('skenario 8 — tidak ada satu pun kelas penanda di luar batas pada HTML ter-render', function () {
    laporanDepricarpingComponentSeedTargets();

    $record = laporanDepricarpingComponentRecord($this->stationA, '2026-09-04');
    // Jelas di luar rentang standarnya, dan tetap tidak ditandai.
    laporanDepricarpingComponentSlot($record, '07:00', ['polishing_drum_speed_rpm' => 27.4]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanDepricarping::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Diasersi atas KETIADAAN NAMA KELAS, bukan ketiadaan frasa: kalimat
    // penjelasnya sendiri menyebut "di luar batas" untuk menyatakan bahwa
    // penandaan itu TIDAK ada, jadi penyisiran teks justru akan gagal pada
    // kalimat yang membuktikan klaimnya.
    foreach (['md-threshold', 'is-danger', 'is-warning', 'md-chip--danger'] as $className) {
        expect($html)->not->toContain($className);
    }

    // Dan ketiadaannya DINYATAKAN, bukan dibiarkan terbaca sebagai fitur yang
    // belum selesai.
    expect($html)->toContain('data-testid="no-flagging-note"');
    expect($html)->toContain('teks bebas');
    expect($html)->toContain('berlaku umum untuk seluruh mill');
});

it('skenario 9 — target tanpa kolom pengukuran terender pada bagiannya sendiri', function () {
    laporanDepricarpingComponentSeedTargets();

    $record = laporanDepricarpingComponentRecord($this->stationA, '2026-09-04');
    laporanDepricarpingComponentSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 30.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanDepricarping::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->toContain('data-testid="targets-without-metric"');
    // SATU parameter, dan alasannya BUKAN "tidak ada kolomnya".
    expect($html)->toContain('Kernel Loss in Fibre');
    expect(substr_count($html, 'data-testid="targets-without-metric-row"'))->toBe(1);
    expect($html)->toContain('data-testid="targets-without-metric-note"');
    expect($html)->toContain('data-testid="targets-without-metric-reason"');
    // ALASANNYA tercetak, bukan hanya nama parameternya: "ada kolomnya tapi
    // arahnya belum pasti" menuntut keputusan penamaan, sementara "tidak ada
    // kolomnya" menuntut kolom baru. Dua tindakan yang berbeda.
    expect($html)->toContain('arahnya belum pasti');
    // Dan baris metriknya sendiri menyatakan standarnya tidak dipasangkan,
    // alih-alih menampilkan sel kosong yang terbaca seperti master kosong.
    expect($html)->toContain('standar tidak dipasangkan');
});

it('skenario 9b — bagian standar-tanpa-pengukuran TETAP digambar walau kosong', function () {
    // Master yang hanya memuat parameter-parameter terpetakan: daftarnya
    // kosong, tetapi bagiannya tetap harus ada. Bagian yang hilang ketika
    // kosong tidak dapat dibedakan dari bagian yang belum pernah dibuat — dan
    // di layar ini bagian itulah yang memuat temuan paling penting.
    DepricarpingOperationalTarget::create([
        'parameter_metric' => 'Fan Static Pressure',
        'target_range' => '40 - 50 mmH2O',
        'critical_limit' => '< 35 or > 55 mmH2O',
        'operational_consequence_justification' => 'Low pressure drops fibre early.',
        'sort_order' => 1,
    ]);

    $record = laporanDepricarpingComponentRecord($this->stationA, '2026-09-04');
    laporanDepricarpingComponentSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 45.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanDepricarping::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->toContain('data-testid="targets-without-metric"');
    expect($html)->toContain('data-testid="targets-all-measured"');
    expect($html)->not->toContain('data-testid="targets-without-metric-row"');
});

it('skenario 9c — kedua baris nut silo membawa keterangan bahwa standarnya satu', function () {
    laporanDepricarpingComponentSeedTargets();

    $record = laporanDepricarpingComponentRecord($this->stationA, '2026-09-04');
    laporanDepricarpingComponentSlot($record, '07:00', [
        'nut_silo_1_temp_c' => 65.0,
        'nut_silo_2_temp_c' => 85.0,
    ]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanDepricarping::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // DUA keterangan, satu per baris silo. Tanpa itu standar '60C - 70C' yang
    // tercetak dua kali berturut-turut terbaca seperti data terduplikasi, dan
    // seseorang akan "membersihkannya".
    expect(substr_count($html, 'data-testid="metric-shared-standard"'))->toBe(2);
    expect($html)->toContain('Angkanya tetap dipisah karena keduanya silo yang berbeda');

    // Dan angkanya TIDAK dirata-ratakan: 75,00 tidak boleh muncul sama sekali.
    expect($html)->toContain('65,00');
    expect($html)->toContain('85,00');
    expect($html)->not->toContain('75,00');
});

it('skenario 10 — master target kosong: keterangan terender, angka ukur tetap tampil', function () {
    $record = laporanDepricarpingComponentRecord($this->stationA, '2026-09-04');
    laporanDepricarpingComponentSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 30.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanDepricarping::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->toContain('data-testid="targets-master-empty"');
    expect($html)->not->toContain('data-testid="targets-without-metric"');
    // Seeder yang belum dijalankan tidak menghapus pengukuran yang sudah
    // terjadi.
    expect($html)->toContain('data-testid="metrics-table"');
    expect($html)->toContain('30,00');
});

// =====================================================================
// Downtime
// =====================================================================

it('skenario 11 — dua ejaan alasan downtime terender sebagai dua baris', function () {
    $record = laporanDepricarpingComponentRecord($this->stationA, '2026-09-04');

    foreach (range(0, 2) as $index) {
        laporanDepricarpingComponentSlot($record, $this->slots[$index], ['findings' => 'Belt kendur']);
    }

    laporanDepricarpingComponentSlot($record, $this->slots[3], ['findings' => 'belt kendur']);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanDepricarping::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect(substr_count($html, 'data-testid="findings-row"'))->toBe(2);
    expect($html)->toContain('data-testid="findings-note"');
    // Tanpa keterangan itu, dua baris mirip terbaca sebagai cacat laporan.
    expect($html)->toContain('harfiah');
});

it('skenario 12 — tanpa alasan downtime: keterangan terender, bukan tabel kosong', function () {
    $record = laporanDepricarpingComponentRecord($this->stationA, '2026-09-04');
    laporanDepricarpingComponentSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 30.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanDepricarping::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->toContain('data-testid="findings-empty"');
    expect($html)->not->toContain('data-testid="findings-table"');

    // Dan blok downtime pun menyatakan ketiadaannya alih-alih mencetak 0
    // menit, yang akan terbaca seperti "stasiun tidak pernah berhenti".
    expect($html)->toContain('data-testid="downtime-empty"');
    expect($html)->not->toContain('data-testid="downtime-total"');
});

it('skenario 12b — downtime numerik terender dengan penyebut slot pencatatnya', function () {
    $record = laporanDepricarpingComponentRecord($this->stationA, '2026-09-04');
    laporanDepricarpingComponentSlot($record, $this->slots[0], ['downtime_minutes' => 10]);
    laporanDepricarpingComponentSlot($record, $this->slots[1], ['downtime_minutes' => 20]);
    laporanDepricarpingComponentSlot($record, $this->slots[2], ['fan_static_pressure_mmh2o' => 45.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanDepricarping::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->toContain('data-testid="downtime-total"');
    expect($html)->toContain('data-testid="downtime-recorded-slots"');
    expect($html)->toContain('data-testid="downtime-average"');
    expect($html)->toContain('30 <span>menit</span>');
    // PENYEBUTNYA 2, bukan 3 slot terisi: slot tanpa catatan downtime BUKAN
    // nol menit, dan halaman ini menyatakannya.
    expect($html)->toContain('2 <span>slot</span>');
    expect($html)->toContain('15,0 <span>menit</span>');
    expect($html)->toContain('tidak dihitung sebagai nol');
    // Parameter ini tidak punya standar pada master, dan itu dinyatakan.
    expect($html)->toContain('tidak punya baris pada master target');
});

// =====================================================================
// Keadaan tanpa angka
// =====================================================================

it('skenario 13 — line belum dipilih: arahan memilih line, dan NOL angka', function () {
    $record = laporanDepricarpingComponentRecord($this->stationA, '2026-09-04');
    laporanDepricarpingComponentSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 30.0]);

    $html = Livewire::actingAs($this->supervisor)->test(LaporanDepricarping::class)->html();

    expect($html)->toContain('data-testid="select-production-line-hint"');

    foreach (LAPORAN_DEPRICARPING_FIGURE_TESTIDS as $testid) {
        expect($html)->not->toContain('data-testid="'.$testid.'"');
    }

    // Dan TIDAK ADA angka seluruh mill sebagai penggantinya.
    expect($html)->toContain('tidak menampilkan angka seluruh mill');
});

it('skenario 14 — mill hanya punya satu line: tetap TIDAK dipilih otomatis', function () {
    $record = laporanDepricarpingComponentRecord($this->stationA, '2026-09-04');
    laporanDepricarpingComponentSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 30.0]);

    $html = Livewire::actingAs($this->supervisor)->test(LaporanDepricarping::class)->html();

    // Memilih line adalah keputusan pembaca; menebaknya menghasilkan angka
    // yang tidak ia minta.
    expect($html)->toContain('data-testid="select-production-line-hint"');
});

it('skenario 15 — periode tanpa slot terisi: keterangan terender, tabel tidak digambar', function () {
    $record = laporanDepricarpingComponentRecord($this->stationA, '2026-09-04');
    laporanDepricarpingComponentSlot($record, '07:00');

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanDepricarping::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->toContain('data-testid="empty-state"');
    // Cakupan TETAP terender — justru itu yang menjelaskan kekosongannya.
    expect($html)->toContain('data-testid="coverage"');
    // Tabel kosong akan terbaca sebagai hasil pengukuran bernilai nol.
    expect($html)->not->toContain('data-testid="metrics-table"');
    expect($html)->not->toContain('data-testid="daily-table"');
    expect($html)->not->toContain('data-testid="findings-table"');
});

it('skenario 16 — periode belum mulai: persen cakupan tanda pisah, bukan 0%', function () {
    $notStarted = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('depricarping')
        ->range(now()->addDays(5)->toDateString(), now()->addDays(20)->toDateString())
        ->open()->named('Periode Belum Mulai')->create();

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanDepricarping::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $notStarted->id)
        ->html();

    expect($html)->toContain('data-testid="coverage-not-started-note"');
    expect($html)->toContain('bukan 0%');
});

it('skenario 17 — periode berjalan: keterangan penyebut berhenti di hari ini', function () {
    $running = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('depricarping')
        ->range(now()->subDays(4)->toDateString(), now()->addDays(10)->toDateString())
        ->open()->named('Periode Berjalan')->create();

    $record = laporanDepricarpingComponentRecord($this->stationA, now()->subDays(1)->toDateString());
    laporanDepricarpingComponentSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 30.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanDepricarping::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $running->id)
        ->html();

    expect($html)->toContain('data-testid="coverage-running-note"');
    expect($html)->toContain('dihitung sampai hari ini');
});

it('skenario 18 — mill tanpa periode Depricarping: arahan, tanpa satu angka pun', function () {
    $otherMillPeriodOnly = BusinessUnit::factory()->create(['name' => 'Mill Tanpa Periode']);
    $station = Station::factory()->forBusinessUnit($otherMillPeriodOnly)->depricarping()->create();
    $supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($otherMillPeriodOnly)->create();

    $html = Livewire::actingAs($supervisor)
        ->test(LaporanDepricarping::class)
        ->set('productionLineId', (string) $station->production_line_id)
        ->html();

    expect($html)->toContain('data-testid="no-period-hint">');

    foreach (LAPORAN_DEPRICARPING_FIGURE_TESTIDS as $testid) {
        expect($html)->not->toContain('data-testid="'.$testid.'"');
    }
});

it('skenario 19 — akun terikat mill tanpa business_unit_id: gagal tertutup tanpa pemilih mill', function () {
    $boundWithoutMill = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $html = Livewire::actingAs($boundWithoutMill)->test(LaporanDepricarping::class)->html();

    expect($html)->toContain('data-testid="no-mill-hint"');
    // Pemilih Mill TIDAK ditawarkan sebagai gantinya — menawarkannya berarti
    // menyerahkan daftar seluruh mill kepada peran yang semestinya terikat
    // pada tepat satu.
    expect($html)->not->toContain('data-testid="mill-select"');
});

// =====================================================================
// Penjagaan lintas mill
// =====================================================================

it('skenario 20 — periode mill lain lewat properti: penolakan terlihat, tanpa angka mill lain', function () {
    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('depricarping')
        ->range('2026-09-01', '2026-09-30')->open()->create();

    $recordB = laporanDepricarpingComponentRecord($this->stationB, '2026-09-04', 'PR-B');
    laporanDepricarpingComponentSlot($recordB, '07:00', ['fan_static_pressure_mmh2o' => 99.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanDepricarping::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $periodB->id)
        ->html();

    expect($html)->toContain('data-testid="forbidden-notice"');
    // Penukaran diam-diam akan menjawab penyelidikan lintas mill dengan angka
    // mill lain di bawah id yang ditanyakan.
    expect($html)->not->toContain('99,00');
});

it('skenario 21 — line mill lain lewat properti: jatuh ke belum memilih, bukan data mill lain', function () {
    $recordB = laporanDepricarpingComponentRecord($this->stationB, '2026-09-04', 'PR-B');
    laporanDepricarpingComponentSlot($recordB, '07:00', ['fan_static_pressure_mmh2o' => 99.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanDepricarping::class)
        ->set('productionLineId', $this->lineB)
        ->html();

    expect($html)->toContain('data-testid="select-production-line-hint"');
    expect($html)->not->toContain('99,00');
});

it('skenario 22 — mill lain lewat properti untuk peran terikat: diabaikan, tetap mill sendiri', function () {
    $record = laporanDepricarpingComponentRecord($this->stationA, '2026-09-04', 'PR-A');
    laporanDepricarpingComponentSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 11.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanDepricarping::class)
        ->set('businessUnitId', (string) $this->businessUnitB->id)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Memaksa propertinya tidak mengubah apa pun: resolvedBusinessUnitId()
    // sama sekali tidak membacanya untuk peran terikat mill.
    expect($html)->toContain('Mill Alpha');
    expect($html)->toContain('11,00');
});

it('skenario 23 — Admin berganti mill: pilihan line direset dan periode dikosongkan', function () {
    $lineAnotherInB = ProductionLine::factory()->create(['business_unit_id' => $this->businessUnitB->id]);

    $component = Livewire::actingAs($this->admin)
        ->test(LaporanDepricarping::class)
        ->set('businessUnitId', (string) $this->businessUnitA->id)
        ->set('productionLineId', $this->lineA);

    expect($component->html())->toContain('data-testid="coverage"');

    $html = $component->set('businessUnitId', (string) $this->businessUnitB->id)->html();

    // Line mill sebelumnya dibuang oleh keepProductionLineValid(), jadi tidak
    // ada satu angka mill lama yang dapat bertahan di layar.
    expect($component->get('productionLineId'))->toBe('');
    expect($html)->toContain('data-testid="select-production-line-hint"');
});

// =====================================================================
// Peran yang TIDAK dibuka
// =====================================================================

it('skenario 24 — Operator ditolak 403 di layar web meski service menerimanya', function () {
    // Daftar peran komponen TERPISAH dari guardAccess() service, dan
    // terpisahnya itu disengaja: service menerima Operator karena layar
    // mobile (screen-153) memakai ulang endpoint yang sama. Bila canAccess()
    // suatu saat mendelegasikan ke service, layar web ini terbuka diam-diam —
    // dan test inilah yang akan menangkapnya.
    Livewire::actingAs($this->operator)
        ->test(LaporanDepricarping::class)
        ->assertForbidden();
});

// =====================================================================
// Rekap harian
// =====================================================================

it('skenario 25 — rekap harian terbuka secara bawaan dan dapat ditutup tanpa mengubah angka', function () {
    $record = laporanDepricarpingComponentRecord($this->stationA, '2026-09-04');
    laporanDepricarpingComponentSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 30.0]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanDepricarping::class)
        ->set('productionLineId', $this->lineA);

    expect($component->html())->toContain('data-testid="daily-table"');

    $closed = $component->call('toggleDailyRecap')->html();

    // Ditutup berarti BENAR-BENAR hilang dari DOM, bukan sekadar
    // disembunyikan — keadaan terlihat dan DOM tidak boleh berselisih.
    expect($closed)->not->toContain('data-testid="daily-table"');
    // Dan angka utamanya tidak bergerak sedikit pun.
    expect($closed)->toContain('data-testid="metrics-table"');
    expect($closed)->toContain('30,00');

    expect($component->call('toggleDailyRecap')->html())->toContain('data-testid="daily-table"');
});

it('skenario 26 — baris total periode dihitung ulang, bukan merata-ratakan rata-rata harian', function () {
    // Hari A: 10 slot bernilai 10. Hari B: 1 slot bernilai 100.
    $dayA = laporanDepricarpingComponentRecord($this->stationA, '2026-09-02', 'PR-1');

    foreach (range(0, 9) as $index) {
        laporanDepricarpingComponentSlot($dayA, $this->slots[$index], ['fan_static_pressure_mmh2o' => 10.0]);
    }

    $dayB = laporanDepricarpingComponentRecord($this->stationA, '2026-09-03', 'PR-1');
    laporanDepricarpingComponentSlot($dayB, '07:00', ['fan_static_pressure_mmh2o' => 100.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanDepricarping::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    $totalRow = explode('data-testid="daily-row-total"', $html)[1] ?? '';

    // 18,18 — BUKAN 55,00. Rata-rata dari rata-rata memberi bobot sama pada
    // hari yang jumlah slotnya berbeda.
    expect($totalRow)->toContain('18,18');
    expect($totalRow)->not->toContain('55,00');
});

// =====================================================================
// Ekspor
// =====================================================================

it('skenario 27 — ekspor mengalirkan berkas untuk periode dan line terpilih', function () {
    $record = laporanDepricarpingComponentRecord($this->stationA, '2026-09-04', 'PR-9');
    laporanDepricarpingComponentSlot($record, '07:00', ['fan_static_pressure_mmh2o' => 30.0]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanDepricarping::class)
        ->set('productionLineId', $this->lineA);

    $component->call('exportCsv', 'csv')->assertFileDownloaded();
});

it('skenario 28 — ekspor tanpa line terpilih tidak menghasilkan apa pun', function () {
    laporanDepricarpingComponentRecord($this->stationA, '2026-09-04', 'PR-9');

    $component = Livewire::actingAs($this->supervisor)->test(LaporanDepricarping::class);

    // Dijaga DI DALAM metodenya, bukan hanya di blade — sehingga pemanggilan
    // langsung tidak dapat mengunduh berkas yang mencampur seluruh line mill.
    expect($component->call('exportCsv', 'csv')->effects)->not->toHaveKey('download');
});
