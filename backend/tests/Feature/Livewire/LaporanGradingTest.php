<?php

/**
 * LaporanGradingTest (Livewire) — screen-146--laporan-grading-web, the web
 * page itself.
 *
 * One test per test_scenarios[].component_test on screen-146's tech spec. The
 * figures are proven against the service in
 * tests/Unit/Services/GradingReportServiceTest.php and over HTTP in
 * tests/Feature/Api/LaporanGradingTest.php; what this file proves is what the
 * PAGE does with them — which pickers exist for which role, which empty state
 * is rendered, and the three things the screen must never let a reader
 * misread.
 *
 * ASSERTED ON THE RENDERED HTML, not on component state, wherever the claim is
 * about what the reader sees. A data-testid present in the markup but never
 * reached by the blade's conditionals would still satisfy a state assertion.
 */

use App\Enums\RecordStatus;
use App\Enums\Uom;
use App\Enums\UserRole;
use App\Livewire\Dashboard\LaporanGrading;
use App\Models\BusinessUnit;
use App\Models\GradingDetail;
use App\Models\GradingParameter;
use App\Models\GradingRecord;
use App\Models\Period;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/** Every data-testid that only exists once a figure is actually rendered. */
const LAPORAN_GRADING_FIGURE_TESTIDS = [
    'headline-kpis',
    'kpi-load-count',
    'kpi-netto-total',
    'kpi-bunch-total',
    'parameter-bunch',
    'parameter-kg',
    'parameter-share-note',
    'by-estate-supplier',
    'completeness',
    'daily-recap-card',
];

function laporanGradingComponentLoad(
    Station $station,
    string $date,
    float $netto = 10000.0,
    float $bunch = 100.0,
    array $attributes = [],
): GradingRecord {
    return GradingRecord::factory()
        ->forStation($station)
        ->onDate($date)
        ->create(array_merge([
            'netto' => $netto,
            'quantity' => $bunch,
            'estate_supplier' => 'Estate A',
            'division' => 'Divisi 1',
        ], $attributes));
}

function laporanGradingComponentDetail(
    GradingRecord $record,
    GradingParameter $parameter,
    float $quantity,
    Uom $uom,
    float $percentage,
): GradingDetail {
    return GradingDetail::factory()
        ->forGradingRecord($record)
        ->forGradingParameter($parameter)
        ->create([
            'quantity' => $quantity,
            'uom' => $uom,
            'percentage' => $percentage,
        ]);
}

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->grading()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->grading()->create();

    $this->lineA = (string) $this->stationA->production_line_id;
    $this->lineB = (string) $this->stationB->production_line_id;

    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    $this->periodA = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('grading')
        ->range('2026-09-01', '2026-09-30')->open()->named('Periode September Alpha')->create();

    $this->mentah = GradingParameter::factory()->create(['name' => 'Mentah', 'uom' => Uom::Bunch, 'sort_order' => 10]);
    $this->masak = GradingParameter::factory()->create(['name' => 'Masak', 'uom' => Uom::Bunch, 'sort_order' => 20]);
    $this->brondolan = GradingParameter::factory()->create(['name' => 'Brondolan Segar', 'uom' => Uom::Kg, 'sort_order' => 30]);
});

// =====================================================================
// Jalur sukses
// =====================================================================

it('skenario 1 — Supervisor: tanpa pemilih mill, seluruh bagian laporan terender', function () {
    $load = laporanGradingComponentLoad($this->stationA, '2026-09-04');
    laporanGradingComponentDetail($load, $this->mentah, 30.0, Uom::Bunch, 11.0);
    laporanGradingComponentDetail($load, $this->brondolan, 40.0, Uom::Kg, 33.0);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanGrading::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->not->toContain('data-testid="mill-select"');
    expect($html)->toContain('data-testid="mill-name"');

    foreach (LAPORAN_GRADING_FIGURE_TESTIDS as $testid) {
        expect($html)->toContain('data-testid="'.$testid.'"');
    }

    // Kedua tabel parameter terender, masing-masing dengan totalnya sendiri.
    expect($html)->toContain('data-testid="parameter-bunch-table"');
    expect($html)->toContain('data-testid="parameter-kg-table"');
    expect($html)->toContain('Mentah');
    expect($html)->toContain('Brondolan Segar');

    // Tidak ada satu pun kontrol tulis.
    expect($html)->not->toContain('>Simpan<');
    expect($html)->not->toContain('>Hapus<');
});

it('skenario 1b — Mill Management melihat layar yang sama persis dengan Supervisor', function () {
    laporanGradingComponentLoad($this->stationA, '2026-09-04');

    $html = Livewire::actingAs($this->millManagement)
        ->test(LaporanGrading::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->not->toContain('data-testid="mill-select"');
    expect($html)->toContain('data-testid="kpi-load-count"');
});

it('skenario 2 — Admin: pemilih mill dirender, dan tanpa mill tidak ada satu angka pun', function () {
    laporanGradingComponentLoad($this->stationA, '2026-09-04');

    $component = Livewire::actingAs($this->admin)->test(LaporanGrading::class);

    $html = $component->html();

    expect($html)->toContain('data-testid="mill-select"');
    expect($html)->toContain('data-testid="mill-select-hint"');

    foreach (LAPORAN_GRADING_FIGURE_TESTIDS as $testid) {
        expect($html)->not->toContain('data-testid="'.$testid.'"');
    }

    $withMill = $component
        ->set('businessUnitId', (string) $this->businessUnitA->id)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($withMill)->toContain('data-testid="kpi-load-count"');
});

// =====================================================================
// Keadaan tanpa angka
// =====================================================================

it('skenario 3 — line belum dipilih: arahan memilih line, dan NOL angka', function () {
    laporanGradingComponentLoad($this->stationA, '2026-09-04');

    $html = Livewire::actingAs($this->supervisor)->test(LaporanGrading::class)->html();

    expect($html)->toContain('data-testid="select-production-line-hint"');

    foreach (LAPORAN_GRADING_FIGURE_TESTIDS as $testid) {
        expect($html)->not->toContain('data-testid="'.$testid.'"');
    }

    // Dan TIDAK ADA angka seluruh mill sebagai penggantinya.
    expect($html)->not->toContain('data-testid="empty-state"');
});

it('skenario 4 — Admin mengganti mill: periode dan line terpilih dikosongkan', function () {
    laporanGradingComponentLoad($this->stationA, '2026-09-04');

    $component = Livewire::actingAs($this->admin)
        ->test(LaporanGrading::class)
        ->set('businessUnitId', (string) $this->businessUnitA->id)
        ->set('productionLineId', $this->lineA);

    expect($component->html())->toContain('data-testid="kpi-load-count"');

    $component->set('businessUnitId', (string) $this->businessUnitB->id);

    // Line milik mill lama dibuang oleh keepProductionLineValid().
    $component->assertSet('productionLineId', '');
    expect($component->html())->toContain('data-testid="select-production-line-hint"');
    expect($component->html())->not->toContain('data-testid="kpi-load-count"');
});

it('skenario 5 — akun terikat mill tanpa business_unit_id: pesan, dan TIDAK ada pemilih mill', function () {
    $boundWithoutMill = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $html = Livewire::actingAs($boundWithoutMill)->test(LaporanGrading::class)->html();

    expect($html)->toContain('data-testid="no-mill-hint"');
    // Menawarkan daftar seluruh mill kepada peran terikat mill justru
    // mengubah data master yang rusak menjadi kebocoran lintas mill.
    expect($html)->not->toContain('data-testid="mill-select"');
});

it('skenario 6 — mill tanpa periode Grading: arahan menghubungi Admin', function () {
    $this->periodA->stations()->delete();
    $this->periodA->delete();

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanGrading::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->toContain('data-testid="no-period-hint"');
    expect($html)->not->toContain('data-testid="kpi-load-count"');
});

it('skenario 7 — periode tanpa data: angka utama "tidak tersedia", tabel parameter tidak digambar', function () {
    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanGrading::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->toContain('data-testid="empty-state"');
    // Angka utama TETAP terender, bernilai tidak tersedia — bukan nol.
    expect($html)->toContain('data-testid="kpi-netto-total"');
    expect($html)->toContain('tidak tersedia');
    // Tabel kosong akan terbaca sebagai komposisi nol yang terukur.
    expect($html)->not->toContain('data-testid="parameter-bunch-table"');
    expect($html)->not->toContain('data-testid="daily-table"');
});

it('skenario 8 — periode milik mill lain: penolakan terlihat, tanpa satu angka pun', function () {
    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('grading')
        ->range('2026-09-01', '2026-09-30')->open()->named('Periode September Beta')->create();

    laporanGradingComponentLoad($this->stationB, '2026-09-04', attributes: ['estate_supplier' => 'Estate Rahasia']);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanGrading::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $periodB->id)
        ->html();

    expect($html)->toContain('data-testid="forbidden-notice"');
    expect($html)->not->toContain('Estate Rahasia');

    foreach (LAPORAN_GRADING_FIGURE_TESTIDS as $testid) {
        expect($html)->not->toContain('data-testid="'.$testid.'"');
    }
});

// =====================================================================
// Yang tidak boleh salah dibaca
// =====================================================================

it('skenario 9 — dua tabel parameter terpisah, dan tidak ada angka gabungan kedua satuan', function () {
    $load = laporanGradingComponentLoad($this->stationA, '2026-09-04');
    laporanGradingComponentDetail($load, $this->mentah, 100.0, Uom::Bunch, 10.0);
    laporanGradingComponentDetail($load, $this->brondolan, 40.0, Uom::Kg, 4.0);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanGrading::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->toContain('data-testid="parameter-bunch-table"');
    expect($html)->toContain('data-testid="parameter-kg-table"');

    // 100 + 40 = 140 — angka yang BISA terbentuk dari fixture ini, dan itulah
    // yang membuat asersi ketiadaannya bermakna.
    expect($html)->not->toContain('140,00');
});

it('skenario 10 — pangsa dan rata-rata persentase terender berdampingan beserta keterangannya', function () {
    $besar = laporanGradingComponentLoad($this->stationA, '2026-09-04', netto: 20000.0, bunch: 200.0);
    laporanGradingComponentDetail($besar, $this->mentah, 180.0, Uom::Bunch, 90.0);

    $kecil = laporanGradingComponentLoad($this->stationA, '2026-09-05', netto: 1000.0, bunch: 10.0);
    laporanGradingComponentDetail($kecil, $this->mentah, 1.0, Uom::Bunch, 10.0);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanGrading::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Keterangan yang membuat kedua angka dapat dibaca — tanpanya keduanya
    // hanya terlihat saling membantah.
    expect($html)->toContain('data-testid="parameter-share-note"');
    expect($html)->toContain('berbobot menurut besar muatan');
    expect($html)->toContain('tiap muatan');

    // Pangsa 100% (satu-satunya parameter) sementara rata-ratanya 50%.
    expect($html)->toContain('100,00%');
    expect($html)->toContain('50,00%');
});

it('skenario 11 — penyebut rata-rata terender sebagai jumlah muatan yang mencatat parameter itu', function () {
    foreach (range(1, 5) as $i) {
        $load = laporanGradingComponentLoad($this->stationA, '2026-09-0'.$i);

        if ($i <= 2) {
            laporanGradingComponentDetail($load, $this->mentah, 10.0, Uom::Bunch, 10.0);
        }
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanGrading::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // "2 dari 5 muatan" — penyebutnya dicetak, bukan disembunyikan.
    expect($html)->toContain('2 dari 5 muatan');
});

it('skenario 12 — kelompok satuan tanpa baris TETAP dirender, bukan disembunyikan', function () {
    $load = laporanGradingComponentLoad($this->stationA, '2026-09-04');
    laporanGradingComponentDetail($load, $this->mentah, 50.0, Uom::Bunch, 50.0);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanGrading::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->toContain('data-testid="parameter-kg"');
    // Bagian yang hilang akan terbaca sebagai "tidak ada bagian ini",
    // padahal yang benar adalah "tidak ada isinya".
    expect($html)->toContain('data-testid="parameter-kg-empty"');
});

it('skenario 13 — muatan tanpa baris parameter: ikut angka utama, dihitung tersendiri', function () {
    $withDetail = laporanGradingComponentLoad($this->stationA, '2026-09-04', netto: 1000.0, bunch: 10.0);
    laporanGradingComponentDetail($withDetail, $this->mentah, 10.0, Uom::Bunch, 100.0);

    laporanGradingComponentLoad($this->stationA, '2026-09-05', netto: 2000.0, bunch: 20.0);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanGrading::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->toContain('data-testid="loads-without-detail"');
    expect($html)->toContain('tidak menyumbang apa pun');
});

it('skenario 14 — kelengkapan: keenam angka terender pada satu bagian', function () {
    $checker = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();

    laporanGradingComponentLoad($this->stationA, '2026-09-04', attributes: [
        'status' => RecordStatus::DraftOngoing,
        'division' => null,
    ]);
    laporanGradingComponentLoad($this->stationA, '2026-09-05', attributes: ['checked_by' => $checker->id]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanGrading::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    foreach ([
        'completeness-days',
        'loads-without-detail',
        'draft-load-count',
        'loads-without-division',
        'loads-not-checked',
        'loads-not-acknowledged',
    ] as $testid) {
        expect($html)->toContain('data-testid="'.$testid.'"');
    }

    // Ketiadaan penghitung "tanpa tanggal" dinyatakan, bukan dibiarkan
    // terbaca sebagai kelalaian.
    expect($html)->toContain('data-testid="completeness-note"');
    expect($html)->toContain('kolom wajib');
});

it('skenario 15 — tidak ada penandaan nilai di luar batas, DIASERSI MENURUT NAMA', function () {
    $load = laporanGradingComponentLoad($this->stationA, '2026-09-04');
    laporanGradingComponentDetail($load, $this->mentah, 190.0, Uom::Bunch, 95.0);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanGrading::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    // Kelas/penanda ambang diperiksa MENURUT NAMA: memeriksa "tidak ada
    // kartu ambang" secara visual akan selalu hijau.
    //
    // DIPERIKSA KELASNYA, BUKAN KATA-KATANYA. Frasa "di luar batas" memang
    // MUNCUL di halaman ini — di dalam keterangan yang menyatakan bahwa
    // penandaan itu tidak ada. Mengasersi ketiadaan frasanya akan menolak
    // justru kalimat yang membuat ketiadaan itu dapat dibaca orang.
    expect($html)->not->toContain('md-threshold');
    expect($html)->not->toContain('is-danger');
    expect($html)->not->toContain('is-warning');
    expect($html)->not->toContain('md-chip--danger');

    // Dan kalimatnya ADA, karena ketiadaan penandaan harus dinyatakan, bukan
    // dibiarkan terbaca sebagai kelalaian.
    expect($html)->toContain('Tidak ada nilai yang ditandai di luar batas di layar ini');

    // Nilai ekstremnya tetap tampil apa adanya.
    expect($html)->toContain('95,00%');
});

// =====================================================================
// Rekap harian, periode tertutup, dan akses
// =====================================================================

it('skenario 16 — rekap harian dapat ditutup dan dibuka tanpa mengubah angka', function () {
    laporanGradingComponentLoad($this->stationA, '2026-09-04');
    laporanGradingComponentLoad($this->stationA, '2026-09-05');

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanGrading::class)
        ->set('productionLineId', $this->lineA);

    expect($component->html())->toContain('data-testid="daily-table"');

    $closed = $component->call('toggleDailyRecap')->html();

    // Tombol, bukan <details>: tertutup berarti benar-benar hilang dari DOM.
    expect($closed)->not->toContain('data-testid="daily-table"');
    // Angka utama tetap terender.
    expect($closed)->toContain('data-testid="kpi-load-count"');
    expect($closed)->toContain('data-testid="parameter-bunch"');

    expect($component->call('toggleDailyRecap')->html())->toContain('data-testid="daily-table"');
});

it('skenario 17 — periode tertutup: badge Tertutup, laporan penuh, ekspor tetap ada', function () {
    $closed = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('grading')
        ->range('2026-11-01', '2026-11-30')->closed()->named('Periode November Tertutup')->create();

    laporanGradingComponentLoad($this->stationA, '2026-11-04');

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanGrading::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $closed->id)
        ->html();

    expect($html)->toContain('data-testid="period-status-badge"');
    expect($html)->toContain('Tertutup');
    expect($html)->toContain('data-testid="kpi-load-count"');
    // Kunci periode mengatur penulisan data, bukan pembacaan laporan.
    expect($html)->toContain('data-testid="export-button"');
});

it('skenario 18 — Operator: rute menolak sebelum mount, dan mount() sendiri juga menolak', function () {
    laporanGradingComponentLoad($this->stationA, '2026-09-04', attributes: ['estate_supplier' => 'Estate Rahasia']);

    // Lapis rute — EnsureRole -> abort(403) sebelum komponen mount.
    $response = $this->actingAs($this->operator, 'web')->get('/reports/grading');
    $response->assertForbidden();
    $response->assertDontSee('Estate Rahasia');

    // Lapis komponen. INI YANG PALING PENTING DI LAYAR INI: ketiga rute API
    // data JUSTRU menerima Operator, karena laporan mobile (screen-147)
    // memakai endpoint yang sama. canAccess() punya daftar perannya SENDIRI,
    // jadi pelebaran service tidak dapat melebarkan layar web ini. Kalau
    // canAccess() suatu hari mendelegasikan ke service, test ini gagal.
    $html = Livewire::actingAs($this->operator)->test(LaporanGrading::class)->html();

    expect($html)->toContain('Forbidden');

    foreach (LAPORAN_GRADING_FIGURE_TESTIDS as $testid) {
        expect($html)->not->toContain('data-testid="'.$testid.'"');
    }
});

it('skenario 19 — mill milik mill lain lewat query string: diabaikan, angka tetap milik mill sendiri', function () {
    laporanGradingComponentLoad($this->stationA, '2026-09-04', netto: 1111.0);
    laporanGradingComponentLoad($this->stationB, '2026-09-04', netto: 9999.0, attributes: ['estate_supplier' => 'Estate Rahasia']);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanGrading::class, ['business_unit_id' => (string) $this->businessUnitB->id])
        ->set('productionLineId', $this->lineA)
        ->html();

    expect($html)->toContain('Mill Alpha');
    expect($html)->not->toContain('Estate Rahasia');
    expect($html)->toContain('1.111,00');
});

it('skenario 20 — line milik mill lain lewat query string: dibuang, arahan memilih line', function () {
    laporanGradingComponentLoad($this->stationA, '2026-09-04');

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanGrading::class, ['production_line_id' => $this->lineB]);

    // Jatuh ke "belum memilih" — yang merender arahan memilih, bukan nama
    // line mill lain.
    $component->assertSet('productionLineId', '');
    expect($component->html())->toContain('data-testid="select-production-line-hint"');
});

it('skenario 21 — mill satu line: line tetap TIDAK dipilih otomatis', function () {
    laporanGradingComponentLoad($this->stationA, '2026-09-04');

    // Mill A hanya punya satu line pada setup ini.
    expect(ProductionLine::query()->where('business_unit_id', $this->businessUnitA->id)->count())->toBe(1);

    $component = Livewire::actingAs($this->supervisor)->test(LaporanGrading::class);

    // Memilih line adalah keputusan pembaca laporan; menebaknya akan
    // menghasilkan angka yang tidak ia minta.
    $component->assertSet('productionLineId', '');
    expect($component->html())->toContain('data-testid="select-production-line-hint"');
});

it('skenario 22 — layar hanya membaca: permukaan publiknya sebatas pemilih, toggle, dan ekspor', function () {
    laporanGradingComponentLoad($this->stationA, '2026-09-04');

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanGrading::class)
        ->set('productionLineId', $this->lineA);

    $public = collect((new ReflectionClass(LaporanGrading::class))->getMethods(ReflectionMethod::IS_PUBLIC))
        ->map(fn (ReflectionMethod $method) => $method->getName())
        ->filter(fn (string $name) => $method = (new ReflectionMethod(LaporanGrading::class, $name))->getDeclaringClass()->getName() === LaporanGrading::class)
        ->values()
        ->all();

    // mount / render / updatedBusinessUnitId / toggleDailyRecap / exportCsv —
    // tidak ada satu pun aksi yang menulis data stasiun.
    expect($public)->toContain('toggleDailyRecap');
    expect($public)->toContain('exportCsv');
    expect($public)->not->toContain('save');
    expect($public)->not->toContain('delete');
    expect($public)->not->toContain('update');

    // Dan datanya memang tidak berubah setelah seluruh interaksi.
    $component->call('toggleDailyRecap')->call('toggleDailyRecap');

    expect(GradingRecord::query()->count())->toBe(1);
    expect(GradingDetail::query()->count())->toBe(0);
});
