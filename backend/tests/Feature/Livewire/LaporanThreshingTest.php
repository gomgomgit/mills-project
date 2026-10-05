<?php

/**
 * LaporanThreshingTest (Livewire) — screen-148--laporan-threshing-web, the web
 * page itself.
 *
 * One test per test_scenarios[].component_test on screen-148's tech spec. The
 * figures are proven against the service in
 * tests/Unit/Services/ThreshingReportServiceTest.php and over HTTP in
 * tests/Feature/Api/LaporanThreshingTest.php; what this file proves is what
 * the PAGE does with them — which pickers exist for which role, which empty
 * state is rendered, and the things the screen must never let a reader
 * misread.
 *
 * ASSERTED ON THE RENDERED HTML, not on component state, wherever the claim
 * is about what the reader sees. A data-testid present in the markup but never
 * reached by the blade's conditionals would still satisfy a state assertion.
 */

use App\Enums\UserRole;
use App\Livewire\Dashboard\LaporanThreshing;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\ThreshingDetail;
use App\Models\ThreshingOperationalTarget;
use App\Models\ThreshingRecord;
use App\Models\User;
use App\Services\ThreshingRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** Every data-testid that only exists once a figure is actually rendered. */
const LAPORAN_THRESHING_FIGURE_TESTIDS = [
    'coverage',
    'coverage-slots',
    'coverage-denominator',
    'metrics',
    'metrics-table',
    'no-flagging-note',
    'by-thresher',
    'downtime',
    'completeness',
    'daily-recap-card',
];

function laporanThreshingComponentRecord(
    Station $station,
    string $date,
    string $thresherId = 'TH-1',
    array $attributes = [],
): ThreshingRecord {
    return ThreshingRecord::factory()
        ->forStation($station)
        ->onDate($date)
        ->create(array_merge(['thresher_id' => $thresherId, 'note' => 'Catatan harian'], $attributes));
}

function laporanThreshingComponentSlot(ThreshingRecord $record, string $timeSlot, array $values = []): ThreshingDetail
{
    return ThreshingDetail::factory()
        ->forRecord($record)
        ->timeSlot($timeSlot)
        ->create($values);
}

function laporanThreshingComponentSeedTargets(): void
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

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->threshing()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->threshing()->create();

    $this->lineA = (string) $this->stationA->production_line_id;
    $this->lineB = (string) $this->stationB->production_line_id;

    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    $this->periodA = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('threshing')
        ->range('2026-09-01', '2026-09-10')->open()->named('Periode September Alpha')->create();

    $this->slots = ThreshingRecordService::canonicalTimeSlots();
});

// =====================================================================
// Jalur sukses
// =====================================================================

it('skenario 1 — Supervisor: tanpa pemilih mill, seluruh bagian laporan terender', function () {
    laporanThreshingComponentSeedTargets();

    $record = laporanThreshingComponentRecord($this->stationA, '2026-09-04');

    foreach (range(0, 3) as $index) {
        $values = ['ffb_throughput_mt_hour' => 30.0];

        if ($index < 2) {
            $values['thresher_drum_speed_rpm'] = 22.0;
        }

        laporanThreshingComponentSlot($record, $this->slots[$index], $values);
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanThreshing::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->not->toContain('data-testid="mill-select"');
    expect($html)->toContain('data-testid="mill-name"');

    foreach (LAPORAN_THRESHING_FIGURE_TESTIDS as $testid) {
        expect($html)->toContain('data-testid="'.$testid.'"');
    }

    // Kelima baris parameter, masing-masing dengan standarnya.
    expect($html)->toContain('FFB Throughput');
    expect($html)->toContain('Putaran Drum Thresher');
    expect($html)->toContain('21 - 23 RPM (optimal for separation)');
    expect($html)->toContain('Inspect drive belt tension');

    // Tidak ada satu pun kontrol tulis.
    expect($html)->not->toContain('>Simpan<');
    expect($html)->not->toContain('>Hapus<');
});

it('skenario 1b — Mill Management melihat layar yang sama persis dengan Supervisor', function () {
    $record = laporanThreshingComponentRecord($this->stationA, '2026-09-04');
    laporanThreshingComponentSlot($record, '07:00', ['ffb_throughput_mt_hour' => 30.0]);

    $html = Livewire::actingAs($this->millManagement)
        ->test(LaporanThreshing::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->not->toContain('data-testid="mill-select"');
    expect($html)->toContain('data-testid="metrics-table"');
});

it('skenario 2 — Admin: pemilih mill dirender, dan tanpa mill tidak ada satu angka pun', function () {
    $record = laporanThreshingComponentRecord($this->stationA, '2026-09-04');
    laporanThreshingComponentSlot($record, '07:00', ['ffb_throughput_mt_hour' => 30.0]);

    $component = Livewire::actingAs($this->admin)->test(LaporanThreshing::class);

    $html = $component->html();

    expect($html)->toContain('data-testid="mill-select"');
    expect($html)->toContain('data-testid="mill-select-hint"');

    foreach (LAPORAN_THRESHING_FIGURE_TESTIDS as $testid) {
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
    $record = laporanThreshingComponentRecord($this->stationA, '2026-09-04');
    laporanThreshingComponentSlot($record, '07:00', ['ffb_throughput_mt_hour' => 30.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanThreshing::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Periode yang terisi seperlima pun menghasilkan rata-rata yang terlihat
    // rapi, jadi pembaca harus melihat cakupannya LEBIH DULU. Diasersi atas
    // POSISI, bukan sekadar kehadiran.
    expect(strpos($html, 'data-testid="coverage"'))
        ->toBeLessThan(strpos($html, 'data-testid="metrics"'));
});

it('skenario 4 — cakupan mencetak ketiga angka pembentuk penyebutnya', function () {
    foreach (['TH-1', 'TH-2'] as $thresher) {
        $record = laporanThreshingComponentRecord($this->stationA, '2026-09-04', $thresher);
        laporanThreshingComponentSlot($record, '07:00', ['ffb_throughput_mt_hour' => 30.0]);
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanThreshing::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->toContain('data-testid="coverage-denominator"');
    // 2 thresher x 10 hari x 24 slot = 480.
    expect($html)->toContain('480');
    expect($html)->toContain('thresher &times; 10 hari &times; 24 slot');
});

// =====================================================================
// Penyebut per parameter
// =====================================================================

it('skenario 5 — tiap baris parameter mencetak penyebutnya sendiri', function () {
    $record = laporanThreshingComponentRecord($this->stationA, '2026-09-04');

    foreach (range(0, 3) as $index) {
        $values = ['ffb_throughput_mt_hour' => 30.0];

        if ($index < 2) {
            $values['thresher_drum_speed_rpm'] = 22.0;
        }

        laporanThreshingComponentSlot($record, $this->slots[$index], $values);
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanThreshing::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Dua penyebut BERBEDA pada halaman yang sama — itulah yang membuat
    // asersi ini bermakna alih-alih selalu hijau.
    expect($html)->toContain('4 dari 4 slot');
    expect($html)->toContain('2 dari 4 slot');
    expect(substr_count($html, 'data-testid="metric-denominator"'))->toBe(5);
});

it('skenario 6 — baris parameter tanpa pembacaan TETAP terender dengan tidak tersedia', function () {
    $record = laporanThreshingComponentRecord($this->stationA, '2026-09-04');
    laporanThreshingComponentSlot($record, '07:00', ['ffb_throughput_mt_hour' => 30.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanThreshing::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Kelima baris ada, meski hanya satu kolom yang pernah diukur: baris yang
    // hilang terbaca sebagai "tidak ada parameter ini", padahal yang benar
    // adalah "tidak ada yang mengukurnya".
    expect(substr_count($html, 'data-testid="metric-row"'))->toBe(5);
    expect($html)->toContain('Arus Motor');
    expect($html)->toContain('tidak tersedia');
    expect($html)->toContain('0 dari 1 slot');
});

// =====================================================================
// Standar operasional, dan ketiadaan penandaan
// =====================================================================

it('skenario 7 — standar dan rencana tindakan berada pada baris yang sama dengan angkanya', function () {
    laporanThreshingComponentSeedTargets();

    $record = laporanThreshingComponentRecord($this->stationA, '2026-09-04');
    laporanThreshingComponentSlot($record, '07:00', ['thresher_drum_speed_rpm' => 27.4]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanThreshing::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Potong satu baris <tr> dan buktikan ketiganya ada di dalamnya — menaruh
    // standarnya di halaman lain akan membuang satu-satunya keunggulan yang
    // diberikan master target.
    $rows = explode('data-testid="metric-row"', $html);
    $drumRow = collect($rows)->first(fn ($row) => str_contains($row, 'Putaran Drum Thresher'));

    expect($drumRow)->toContain('27,40');
    expect($drumRow)->toContain('21 - 23 RPM (optimal for separation)');
    expect($drumRow)->toContain('Inspect drive belt tension');
});

it('skenario 8 — tidak ada satu pun kelas penanda di luar batas pada HTML ter-render', function () {
    laporanThreshingComponentSeedTargets();

    $record = laporanThreshingComponentRecord($this->stationA, '2026-09-04');
    // Jelas di luar rentang standarnya, dan tetap tidak ditandai.
    laporanThreshingComponentSlot($record, '07:00', ['thresher_drum_speed_rpm' => 27.4]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanThreshing::class)
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
    laporanThreshingComponentSeedTargets();

    $record = laporanThreshingComponentRecord($this->stationA, '2026-09-04');
    laporanThreshingComponentSlot($record, '07:00', ['ffb_throughput_mt_hour' => 30.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanThreshing::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->toContain('data-testid="targets-without-metric"');
    expect($html)->toContain('Bearing Temperature');
    expect($html)->toContain('data-testid="targets-without-metric-note"');
    // Standar yang tidak pernah diukur terbaca seperti terpenuhi padahal ia
    // sekadar tidak ada — dan halaman ini menyatakannya.
    expect($html)->toContain('tidak punya kolom untuk mengukurnya');
});

it('skenario 10 — master target kosong: keterangan terender, angka ukur tetap tampil', function () {
    $record = laporanThreshingComponentRecord($this->stationA, '2026-09-04');
    laporanThreshingComponentSlot($record, '07:00', ['ffb_throughput_mt_hour' => 30.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanThreshing::class)
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
    $record = laporanThreshingComponentRecord($this->stationA, '2026-09-04');

    foreach (range(0, 2) as $index) {
        laporanThreshingComponentSlot($record, $this->slots[$index], ['downtime_reason' => 'Belt kendur']);
    }

    laporanThreshingComponentSlot($record, $this->slots[3], ['downtime_reason' => 'belt kendur']);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanThreshing::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect(substr_count($html, 'data-testid="downtime-row"'))->toBe(2);
    expect($html)->toContain('data-testid="downtime-note"');
    // Tanpa keterangan itu, dua baris mirip terbaca sebagai cacat laporan.
    expect($html)->toContain('harfiah');
});

it('skenario 12 — tanpa alasan downtime: keterangan terender, bukan tabel kosong', function () {
    $record = laporanThreshingComponentRecord($this->stationA, '2026-09-04');
    laporanThreshingComponentSlot($record, '07:00', ['ffb_throughput_mt_hour' => 30.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanThreshing::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->toContain('data-testid="downtime-empty"');
    expect($html)->not->toContain('data-testid="downtime-table"');
});

// =====================================================================
// Keadaan tanpa angka
// =====================================================================

it('skenario 13 — line belum dipilih: arahan memilih line, dan NOL angka', function () {
    $record = laporanThreshingComponentRecord($this->stationA, '2026-09-04');
    laporanThreshingComponentSlot($record, '07:00', ['ffb_throughput_mt_hour' => 30.0]);

    $html = Livewire::actingAs($this->supervisor)->test(LaporanThreshing::class)->html();

    expect($html)->toContain('data-testid="select-production-line-hint"');

    foreach (LAPORAN_THRESHING_FIGURE_TESTIDS as $testid) {
        expect($html)->not->toContain('data-testid="'.$testid.'"');
    }

    // Dan TIDAK ADA angka seluruh mill sebagai penggantinya.
    expect($html)->toContain('tidak menampilkan angka seluruh mill');
});

it('skenario 14 — mill hanya punya satu line: tetap TIDAK dipilih otomatis', function () {
    $record = laporanThreshingComponentRecord($this->stationA, '2026-09-04');
    laporanThreshingComponentSlot($record, '07:00', ['ffb_throughput_mt_hour' => 30.0]);

    $html = Livewire::actingAs($this->supervisor)->test(LaporanThreshing::class)->html();

    // Memilih line adalah keputusan pembaca; menebaknya menghasilkan angka
    // yang tidak ia minta.
    expect($html)->toContain('data-testid="select-production-line-hint"');
});

it('skenario 15 — periode tanpa slot terisi: keterangan terender, tabel tidak digambar', function () {
    $record = laporanThreshingComponentRecord($this->stationA, '2026-09-04');
    laporanThreshingComponentSlot($record, '07:00');

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanThreshing::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->toContain('data-testid="empty-state"');
    // Cakupan TETAP terender — justru itu yang menjelaskan kekosongannya.
    expect($html)->toContain('data-testid="coverage"');
    // Tabel kosong akan terbaca sebagai hasil pengukuran bernilai nol.
    expect($html)->not->toContain('data-testid="metrics-table"');
    expect($html)->not->toContain('data-testid="daily-table"');
    expect($html)->not->toContain('data-testid="downtime-table"');
});

it('skenario 16 — periode belum mulai: persen cakupan tanda pisah, bukan 0%', function () {
    $notStarted = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('threshing')
        ->range(now()->addDays(5)->toDateString(), now()->addDays(20)->toDateString())
        ->open()->named('Periode Belum Mulai')->create();

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanThreshing::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $notStarted->id)
        ->html();

    expect($html)->toContain('data-testid="coverage-not-started-note"');
    expect($html)->toContain('bukan 0%');
});

it('skenario 17 — periode berjalan: keterangan penyebut berhenti di hari ini', function () {
    $running = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('threshing')
        ->range(now()->subDays(4)->toDateString(), now()->addDays(10)->toDateString())
        ->open()->named('Periode Berjalan')->create();

    $record = laporanThreshingComponentRecord($this->stationA, now()->subDays(1)->toDateString());
    laporanThreshingComponentSlot($record, '07:00', ['ffb_throughput_mt_hour' => 30.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanThreshing::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $running->id)
        ->html();

    expect($html)->toContain('data-testid="coverage-running-note"');
    expect($html)->toContain('dihitung sampai hari ini');
});

it('skenario 18 — mill tanpa periode Threshing: arahan, tanpa satu angka pun', function () {
    $otherMillPeriodOnly = BusinessUnit::factory()->create(['name' => 'Mill Tanpa Periode']);
    $station = Station::factory()->forBusinessUnit($otherMillPeriodOnly)->threshing()->create();
    $supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($otherMillPeriodOnly)->create();

    $html = Livewire::actingAs($supervisor)
        ->test(LaporanThreshing::class)
        ->set('productionLineId', (string) $station->production_line_id)
        ->html();

    expect($html)->toContain('data-testid="no-period-hint">');

    foreach (LAPORAN_THRESHING_FIGURE_TESTIDS as $testid) {
        expect($html)->not->toContain('data-testid="'.$testid.'"');
    }
});

it('skenario 19 — akun terikat mill tanpa business_unit_id: gagal tertutup tanpa pemilih mill', function () {
    $boundWithoutMill = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $html = Livewire::actingAs($boundWithoutMill)->test(LaporanThreshing::class)->html();

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
    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('threshing')
        ->range('2026-09-01', '2026-09-30')->open()->create();

    $recordB = laporanThreshingComponentRecord($this->stationB, '2026-09-04', 'TH-B');
    laporanThreshingComponentSlot($recordB, '07:00', ['ffb_throughput_mt_hour' => 99.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanThreshing::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $periodB->id)
        ->html();

    expect($html)->toContain('data-testid="forbidden-notice"');
    // Penukaran diam-diam akan menjawab penyelidikan lintas mill dengan angka
    // mill lain di bawah id yang ditanyakan.
    expect($html)->not->toContain('99,00');
});

it('skenario 21 — line mill lain lewat properti: jatuh ke belum memilih, bukan data mill lain', function () {
    $recordB = laporanThreshingComponentRecord($this->stationB, '2026-09-04', 'TH-B');
    laporanThreshingComponentSlot($recordB, '07:00', ['ffb_throughput_mt_hour' => 99.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanThreshing::class)
        ->set('productionLineId', $this->lineB)
        ->html();

    expect($html)->toContain('data-testid="select-production-line-hint"');
    expect($html)->not->toContain('99,00');
});

it('skenario 22 — mill lain lewat properti untuk peran terikat: diabaikan, tetap mill sendiri', function () {
    $record = laporanThreshingComponentRecord($this->stationA, '2026-09-04', 'TH-A');
    laporanThreshingComponentSlot($record, '07:00', ['ffb_throughput_mt_hour' => 11.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanThreshing::class)
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
        ->test(LaporanThreshing::class)
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
    // mobile (screen-149) memakai ulang endpoint yang sama. Bila canAccess()
    // suatu saat mendelegasikan ke service, layar web ini terbuka diam-diam —
    // dan test inilah yang akan menangkapnya.
    Livewire::actingAs($this->operator)
        ->test(LaporanThreshing::class)
        ->assertForbidden();
});

// =====================================================================
// Rekap harian
// =====================================================================

it('skenario 25 — rekap harian terbuka secara bawaan dan dapat ditutup tanpa mengubah angka', function () {
    $record = laporanThreshingComponentRecord($this->stationA, '2026-09-04');
    laporanThreshingComponentSlot($record, '07:00', ['ffb_throughput_mt_hour' => 30.0]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanThreshing::class)
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
    $dayA = laporanThreshingComponentRecord($this->stationA, '2026-09-02', 'TH-1');

    foreach (range(0, 9) as $index) {
        laporanThreshingComponentSlot($dayA, $this->slots[$index], ['ffb_throughput_mt_hour' => 10.0]);
    }

    $dayB = laporanThreshingComponentRecord($this->stationA, '2026-09-03', 'TH-1');
    laporanThreshingComponentSlot($dayB, '07:00', ['ffb_throughput_mt_hour' => 100.0]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanThreshing::class)
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
    $record = laporanThreshingComponentRecord($this->stationA, '2026-09-04', 'TH-9');
    laporanThreshingComponentSlot($record, '07:00', ['ffb_throughput_mt_hour' => 30.0]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanThreshing::class)
        ->set('productionLineId', $this->lineA);

    $component->call('exportCsv', 'csv')->assertFileDownloaded();
});

it('skenario 28 — ekspor tanpa line terpilih tidak menghasilkan apa pun', function () {
    laporanThreshingComponentRecord($this->stationA, '2026-09-04', 'TH-9');

    $component = Livewire::actingAs($this->supervisor)->test(LaporanThreshing::class);

    // Dijaga DI DALAM metodenya, bukan hanya di blade — sehingga pemanggilan
    // langsung tidak dapat mengunduh berkas yang mencampur seluruh line mill.
    expect($component->call('exportCsv', 'csv')->effects)->not->toHaveKey('download');
});
