<?php

/**
 * LaporanGradingTest (API) — screen-146--laporan-grading-web /
 * screen-147--laporan-grading-mobile, the four /api/grading-reports/*
 * endpoints.
 *
 * One test per test_scenarios[].api_test on screen-146's tech spec. The
 * aggregation rules themselves are proven in
 * tests/Unit/Services/GradingReportServiceTest.php against the service; what
 * this file proves is the HTTP contract — status codes, who is admitted, what
 * the payload actually carries, and what the exported file looks like.
 *
 * THE FIXTURES ARE DELIBERATELY INCONSISTENT with any shortcut: percentages
 * are stored values that cannot be derived from the load's own netto/bunch
 * count, and each parameter appears on a DIFFERENT subset of loads. See the
 * docblock of the unit test for why tidying them would turn these assertions
 * into always-green ones.
 */

use App\Enums\RecordStatus;
use App\Enums\Uom;
use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\GradingDetail;
use App\Models\GradingParameter;
use App\Models\GradingRecord;
use App\Models\Period;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use App\Services\StationReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function laporanGradingUrl(string $path, array $query = []): string
{
    return '/api/grading-reports/'.$path.($query === [] ? '' : '?'.http_build_query($query));
}

function laporanGradingLoad(
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

function laporanGradingDetail(
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

/** The body of a StreamedResponse, captured. STREAM ONCE. */
function laporanGradingStreamed($response): string
{
    ob_start();
    $response->sendContent();

    return (string) ob_get_clean();
}

function laporanGradingCsvLinesOf(string $body): array
{
    return array_values(array_filter(explode("\n", trim($body))));
}

function laporanGradingCsvRow(string $line): array
{
    return str_getcsv($line, ',', '"', '\\');
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
// Skenario 1-2: jalur sukses
// =====================================================================

it('skenario 1 — Supervisor dan Mill Management: periods + summary + export mill sendiri', function () {
    $load = laporanGradingLoad($this->stationA, '2026-09-04');
    laporanGradingDetail($load, $this->mentah, 30.0, Uom::Bunch, 11.0);
    laporanGradingDetail($load, $this->brondolan, 40.0, Uom::Kg, 33.0);

    foreach ([$this->supervisor, $this->millManagement] as $user) {
        $periods = $this->actingAs($user, 'web')->getJson(laporanGradingUrl('periods'));
        $periods->assertOk();
        expect(collect($periods->json('data'))->pluck('id'))->toContain((string) $this->periodA->id);

        $summary = $this->actingAs($user, 'web')->getJson(laporanGradingUrl('summary', [
            'period_id' => (string) $this->periodA->id,
            'production_line_id' => $this->lineA,
        ]));
        $summary->assertOk();

        expect($summary->json('business_unit.name'))->toBe('Mill Alpha');
        expect($summary->json('load_count'))->toBe(1);
        expect($summary->json('bunch.quantity_total'))->toEqual(30.0);
        expect($summary->json('kg.quantity_total'))->toEqual(40.0);
        expect($summary->json('bunch.rows.0.name'))->toBe('Mentah');
        expect($summary->json('kg.rows.0.name'))->toBe('Brondolan Segar');
    }

    $export = $this->actingAs($this->supervisor, 'web')->get(laporanGradingUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));
    $export->assertOk();
});

it('skenario 2 — Admin: pemilih mill lalu angka mill yang dipilih saja', function () {
    laporanGradingLoad($this->stationA, '2026-09-04', netto: 1111.0);
    laporanGradingLoad($this->stationB, '2026-09-04', netto: 9999.0);

    $options = $this->actingAs($this->admin, 'web')->getJson(laporanGradingUrl('business-units/options'));
    $options->assertOk();
    expect(array_column($options->json('data'), 'name'))->toContain('Mill Alpha');

    $summary = $this->actingAs($this->admin, 'web')->getJson(laporanGradingUrl('summary', [
        'business_unit_id' => (string) $this->businessUnitA->id,
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();

    expect($summary->json('netto_total'))->toEqual(1111.0);
    expect($summary->content())->not->toContain('9999');
});

// =====================================================================
// Skenario 3-6: parameter yang hilang dan pemilihan yang belum lengkap
// =====================================================================

it('skenario 3 — production_line_id absen: 422 dan nol kueri grading_records', function () {
    laporanGradingLoad($this->stationA, '2026-09-04', attributes: ['grading_number' => 'GR-RAHASIA']);

    $response = $this->actingAs($this->supervisor, 'web')->getJson(laporanGradingUrl('summary', [
        'period_id' => (string) $this->periodA->id,
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['production_line_id']);
    // Tidak ada angka apa pun yang lolos bersama penolakan ini.
    expect($response->json('load_count'))->toBeNull();
    expect($response->content())->not->toContain('GR-RAHASIA');
});

it('skenario 4 — period_id absen: 422 dengan errors.period_id', function () {
    $response = $this->actingAs($this->supervisor, 'web')->getJson(laporanGradingUrl('summary', [
        'production_line_id' => $this->lineA,
    ]));

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['period_id']);
});

it('skenario 5 — Admin tanpa business_unit_id: 422 pada periods maupun summary', function () {
    $this->actingAs($this->admin, 'web')->getJson(laporanGradingUrl('periods'))->assertStatus(422);

    $this->actingAs($this->admin, 'web')->getJson(laporanGradingUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]))->assertStatus(422);
});

it('skenario 6 — mill tanpa periode Grading: 200 dengan data kosong, bukan 404', function () {
    $this->periodA->stations()->delete();
    $this->periodA->delete();

    $response = $this->actingAs($this->supervisor, 'web')->getJson(laporanGradingUrl('periods'));

    $response->assertOk();
    expect($response->json('data'))->toBe([]);
});

// =====================================================================
// Skenario 7-13: isi laporan
// =====================================================================

it('skenario 7 — periode tanpa data: seluruh total null dan kedua kelompok tetap ada', function () {
    $response = $this->actingAs($this->supervisor, 'web')->getJson(laporanGradingUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $response->assertOk();
    $response->assertJsonPath('load_count', 0);
    $response->assertJsonPath('netto_total', null);
    $response->assertJsonPath('bunch_total', null);
    // Kedua kelompok TETAP ADA pada payload, supaya layar dapat
    // menampilkannya sebagai "tidak tersedia" alih-alih menyembunyikannya.
    $response->assertJsonPath('bunch.quantity_total', null);
    $response->assertJsonPath('kg.quantity_total', null);
    $response->assertJsonPath('bunch.rows', []);
    $response->assertJsonPath('kg.rows', []);
    $response->assertJsonPath('daily', []);
});

it('skenario 8 — satuan janjang dan kilogram tidak pernah dijumlahkan pada payload', function () {
    $load = laporanGradingLoad($this->stationA, '2026-09-04');
    laporanGradingDetail($load, $this->mentah, 100.0, Uom::Bunch, 10.0);
    laporanGradingDetail($load, $this->brondolan, 40.0, Uom::Kg, 4.0);

    $response = $this->actingAs($this->supervisor, 'web')->getJson(laporanGradingUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $response->assertOk();
    expect($response->json('bunch.quantity_total'))->toEqual(100.0);
    expect($response->json('kg.quantity_total'))->toEqual(40.0);

    // 100 + 40 = 140 — a number that COULD be formed from this fixture, which
    // is exactly what makes its absence meaningful.
    expect($response->content())->not->toContain('140');

    // And no key anywhere claims to be a combined total.
    $keys = array_keys($response->json());
    expect($keys)->not->toContain('quantity_total');
    expect($keys)->not->toContain('parameter_total');
});

it('skenario 9 — pangsa dan rata-rata persentase keduanya diterbitkan dan dapat berbeda', function () {
    // Satu muatan RAKSASA berkomposisi buruk, dua muatan kecil berkomposisi
    // baik: pangsa Mentah tinggi, rata-ratanya rendah.
    $besar = laporanGradingLoad($this->stationA, '2026-09-04', netto: 20000.0, bunch: 200.0);
    laporanGradingDetail($besar, $this->mentah, 180.0, Uom::Bunch, 90.0);

    foreach (['2026-09-05', '2026-09-06'] as $date) {
        $kecil = laporanGradingLoad($this->stationA, $date, netto: 1000.0, bunch: 10.0);
        laporanGradingDetail($kecil, $this->mentah, 1.0, Uom::Bunch, 10.0);
    }

    $response = $this->actingAs($this->supervisor, 'web')->getJson(laporanGradingUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $response->assertOk();

    $mentah = collect($response->json('bunch.rows'))->firstWhere('name', 'Mentah');

    // Pangsa 100% (satu-satunya parameter), tetapi rata-ratanya (90+10+10)/3.
    expect($mentah['share_percent'])->toEqual(100.0);
    expect($mentah['avg_percentage'])->toEqual(36.67);
    expect($mentah['load_count'])->toBe(3);
});

it('skenario 10 — rata-rata persentase memakai penyebut muatan yang mencatat parameter itu', function () {
    foreach (range(1, 10) as $i) {
        $load = laporanGradingLoad($this->stationA, '2026-09-0'.min($i, 9));

        if ($i <= 3) {
            laporanGradingDetail($load, $this->mentah, 10.0, Uom::Bunch, [9.0, 6.0, 3.0][$i - 1]);
        }
    }

    $response = $this->actingAs($this->supervisor, 'web')->getJson(laporanGradingUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $response->assertOk();
    expect($response->json('load_count'))->toBe(10);

    $mentah = collect($response->json('bunch.rows'))->firstWhere('name', 'Mentah');

    // 6.0 (dibagi 3), bukan 1.8 (dibagi 10).
    expect($mentah['avg_percentage'])->toEqual(6.0);
    expect($mentah['load_count'])->toBe(3);
});

it('skenario 11 — muatan tanpa baris parameter: ikut header, di luar kedua kelompok, dihitung tersendiri', function () {
    $withDetail = laporanGradingLoad($this->stationA, '2026-09-04', netto: 1000.0, bunch: 10.0);
    laporanGradingDetail($withDetail, $this->mentah, 10.0, Uom::Bunch, 100.0);

    laporanGradingLoad($this->stationA, '2026-09-05', netto: 2000.0, bunch: 20.0);

    $response = $this->actingAs($this->supervisor, 'web')->getJson(laporanGradingUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $response->assertOk();
    $response->assertJsonPath('load_count', 2);
    expect($response->json('netto_total'))->toEqual(3000.0);
    expect($response->json('bunch.quantity_total'))->toEqual(10.0);
    $response->assertJsonPath('loads_without_detail', 1);
});

it('skenario 12 — periode hanya memuat satu satuan: kelompok yang lain tetap diterbitkan', function () {
    $load = laporanGradingLoad($this->stationA, '2026-09-04');
    laporanGradingDetail($load, $this->mentah, 50.0, Uom::Bunch, 50.0);

    $response = $this->actingAs($this->supervisor, 'web')->getJson(laporanGradingUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $response->assertOk();
    expect($response->json('bunch.quantity_total'))->toEqual(50.0);
    // Dirender sebagai "tidak tersedia" oleh layar, BUKAN disembunyikan.
    $response->assertJsonPath('kg.quantity_total', null);
    $response->assertJsonPath('kg.rows', []);
});

it('skenario 13 — persentase diteruskan apa adanya, tidak dihitung ulang', function () {
    // netto 10.000 / 100 janjang: kuantitas 10 akan menjadi 10% bila dihitung
    // ulang dari jumlah janjang. Nilai tersimpannya 42.
    $load = laporanGradingLoad($this->stationA, '2026-09-04', netto: 10000.0, bunch: 100.0);
    laporanGradingDetail($load, $this->mentah, 10.0, Uom::Bunch, 42.0);

    $response = $this->actingAs($this->supervisor, 'web')->getJson(laporanGradingUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $response->assertOk();
    expect($response->json('bunch.rows.0.avg_percentage'))->toEqual(42.0);
});

// =====================================================================
// Skenario 14-16: rekap dan kelengkapan
// =====================================================================

it('skenario 14 — rekap per asal menampilkan seluruh asal, termasuk kelompok belum diisi', function () {
    laporanGradingLoad($this->stationA, '2026-09-04', netto: 9000.0, attributes: ['estate_supplier' => 'Estate Besar']);
    laporanGradingLoad($this->stationA, '2026-09-05', netto: 100.0, attributes: ['estate_supplier' => 'Estate Kecil']);
    laporanGradingLoad($this->stationA, '2026-09-06', netto: 500.0, attributes: ['estate_supplier' => '']);

    $response = $this->actingAs($this->supervisor, 'web')->getJson(laporanGradingUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $response->assertOk();

    $rows = $response->json('by_estate_supplier');

    expect($rows)->toHaveCount(3);
    expect(array_column($rows, 'estate_supplier'))->toContain('Estate Kecil');
    expect(array_column($rows, 'estate_supplier'))->toContain('');
    // Kolom muatan tetap menjumlah ke angka utama.
    expect(collect($rows)->sum('load_count'))->toBe($response->json('load_count'));
});

it('skenario 15 — muatan draft ikut terhitung dan jumlahnya diterbitkan', function () {
    laporanGradingLoad($this->stationA, '2026-09-04', attributes: ['status' => RecordStatus::DraftOngoing]);
    laporanGradingLoad($this->stationA, '2026-09-05', attributes: ['status' => RecordStatus::DraftPaused]);
    laporanGradingLoad($this->stationA, '2026-09-06');

    $response = $this->actingAs($this->supervisor, 'web')->getJson(laporanGradingUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $response->assertOk();
    $response->assertJsonPath('load_count', 3);
    $response->assertJsonPath('draft_load_count', 2);
});

it('skenario 16 — status verifikasi adalah kelengkapan, bukan penyaring', function () {
    $checker = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();

    laporanGradingLoad($this->stationA, '2026-09-04');
    laporanGradingLoad($this->stationA, '2026-09-05', attributes: ['checked_by' => $checker->id]);
    laporanGradingLoad($this->stationA, '2026-09-06', attributes: [
        'checked_by' => $checker->id,
        'acknowledged_by' => $checker->id,
    ]);

    $response = $this->actingAs($this->supervisor, 'web')->getJson(laporanGradingUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $response->assertOk();
    $response->assertJsonPath('loads_not_checked', 1);
    $response->assertJsonPath('loads_not_acknowledged', 2);
    // Tak satu pun angka utama tersaring olehnya.
    $response->assertJsonPath('load_count', 3);
    expect($response->json('netto_total'))->toEqual(30000.0);
});

// =====================================================================
// Skenario 17-21: periode, line, dan lintas mill
// =====================================================================

it('skenario 17 — muatan tersinkron terlambat tetap milik periode tempat tanggalnya berada', function () {
    $late = laporanGradingLoad($this->stationA, '2026-09-10');
    $late->forceFill(['created_at' => '2026-12-01 08:00:00'])->saveQuietly();

    $response = $this->actingAs($this->supervisor, 'web')->getJson(laporanGradingUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $response->assertOk();
    $response->assertJsonPath('load_count', 1);
});

it('skenario 18 — penyaringan line memakai line yang melekat pada muatan itu sendiri', function () {
    $lineTwo = ProductionLine::factory()->forBusinessUnit($this->businessUnitA)->create(['name' => 'Line Kedua']);

    laporanGradingLoad($this->stationA, '2026-09-04');
    $this->stationA->update(['production_line_id' => $lineTwo->id]);

    $onOldLine = $this->actingAs($this->supervisor, 'web')->getJson(laporanGradingUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $onOldLine->assertOk();
    $onOldLine->assertJsonPath('load_count', 1);

    $onNewLine = $this->actingAs($this->supervisor, 'web')->getJson(laporanGradingUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => (string) $lineTwo->id,
    ]));
    $onNewLine->assertOk();
    $onNewLine->assertJsonPath('load_count', 0);
});

it('skenario 19 — rentang periode inklusif di kedua ujung', function () {
    laporanGradingLoad($this->stationA, '2026-09-01');
    laporanGradingLoad($this->stationA, '2026-09-30');
    laporanGradingLoad($this->stationA, '2026-08-31');
    laporanGradingLoad($this->stationA, '2026-10-01');

    $response = $this->actingAs($this->supervisor, 'web')->getJson(laporanGradingUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));

    $response->assertOk();
    $response->assertJsonPath('load_count', 2);
    expect(array_column($response->json('daily'), 'date'))->toBe(['2026-09-01', '2026-09-30']);
});

it('skenario 20 — akun terikat mill tanpa business_unit_id: 422 menghubungi Admin', function () {
    $boundWithoutMill = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $this->actingAs($boundWithoutMill, 'web')->getJson(laporanGradingUrl('periods'))->assertStatus(422);
});

it('skenario 21 — mill dan line milik mill lain: tiga jawaban berbeda, sengaja', function () {
    laporanGradingLoad($this->stationA, '2026-09-04', netto: 1111.0);
    laporanGradingLoad($this->stationB, '2026-09-04', netto: 9999.0, attributes: ['estate_supplier' => 'Estate Rahasia']);

    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('grading')
        ->range('2026-09-01', '2026-09-30')->open()->named('Periode September Beta')->create();

    // (1) business_unit_id mill lain: DIABAIKAN, 200 berisi data mill sendiri.
    //     Sengaja bukan 403 — 403 akan membenarkan bahwa mill itu ada.
    $probe = $this->actingAs($this->supervisor, 'web')->getJson(laporanGradingUrl('summary', [
        'business_unit_id' => (string) $this->businessUnitB->id,
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $probe->assertOk();
    expect($probe->json('business_unit.id'))->toBe((string) $this->businessUnitA->id);
    expect($probe->content())->not->toContain('Estate Rahasia');

    // (2) production_line_id mill lain: 403 — pegangan nyata atas datanya.
    $this->actingAs($this->supervisor, 'web')->getJson(laporanGradingUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineB,
    ]))->assertStatus(403);

    // (3) period_id mill lain: 403.
    $this->actingAs($this->supervisor, 'web')->getJson(laporanGradingUrl('summary', [
        'period_id' => (string) $periodB->id,
        'production_line_id' => $this->lineA,
    ]))->assertStatus(403);
});

// =====================================================================
// Skenario 22: Operator — diterima pada rute data, ditolak pada pemilih mill
// =====================================================================

it('skenario 22 — Operator: diterima pada ketiga rute data untuk mill sendiri, 403 pada pemilih mill', function () {
    laporanGradingLoad($this->stationA, '2026-09-04', netto: 1111.0);
    laporanGradingLoad($this->stationB, '2026-09-04', netto: 9999.0, attributes: ['estate_supplier' => 'Estate Rahasia']);

    // TAMU LEBIH DULU: actingAs() bertahan sepanjang kasus ini.
    $this->getJson(laporanGradingUrl('periods'))->assertStatus(401);

    $periods = $this->actingAs($this->operator, 'web')->getJson(laporanGradingUrl('periods'));
    $periods->assertOk();
    expect(collect($periods->json('data'))->pluck('id'))->toContain((string) $this->periodA->id);

    $summary = $this->actingAs($this->operator, 'web')->getJson(laporanGradingUrl('summary', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();
    expect($summary->json('netto_total'))->toEqual(1111.0);
    expect($summary->content())->not->toContain('Estate Rahasia');

    // business_unit_id mill lain tetap 200 berisi data mill sendiri — asersi
    // yang gagal bila Operator jatuh ke cabang Admin.
    $probe = $this->actingAs($this->operator, 'web')->getJson(laporanGradingUrl('summary', [
        'business_unit_id' => (string) $this->businessUnitB->id,
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
    ]));
    $probe->assertOk();
    expect($probe->json('business_unit.id'))->toBe((string) $this->businessUnitA->id);

    $export = $this->actingAs($this->operator, 'web')->get(laporanGradingUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));
    $export->assertOk();

    // Rute keempat TETAP 403 — satu-satunya yang tidak dibuka.
    $options = $this->actingAs($this->operator, 'web')->getJson(laporanGradingUrl('business-units/options'));
    $options->assertStatus(403);
    expect($options->content())->not->toContain('Mill Beta');
});

// =====================================================================
// Skenario 23-25: periode tertutup dan ekspor
// =====================================================================

it('skenario 23 — periode tertutup: tetap terdaftar, terbaca penuh, dan tetap dapat diekspor', function () {
    $closed = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('grading')
        ->range('2026-11-01', '2026-11-30')->closed()->named('Periode November Tertutup')->create();

    $load = laporanGradingLoad($this->stationA, '2026-11-04');
    laporanGradingDetail($load, $this->mentah, 10.0, Uom::Bunch, 10.0);

    $periods = $this->actingAs($this->supervisor, 'web')->getJson(laporanGradingUrl('periods'));
    $periods->assertOk();
    expect(collect($periods->json('data'))->keyBy('id'))->toHaveKey((string) $closed->id);

    $summary = $this->actingAs($this->supervisor, 'web')->getJson(laporanGradingUrl('summary', [
        'period_id' => (string) $closed->id,
        'production_line_id' => $this->lineA,
    ]));
    $summary->assertOk();
    $summary->assertJsonPath('period.status', 'closed');
    $summary->assertJsonPath('load_count', 1);

    $this->actingAs($this->supervisor, 'web')->get(laporanGradingUrl('export', [
        'period_id' => (string) $closed->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]))->assertOk();
});

it('skenario 24 — ekspor: satu baris per parameter per muatan, konteks diulang, satuan ikut', function () {
    $first = laporanGradingLoad($this->stationA, '2026-09-04');
    laporanGradingDetail($first, $this->mentah, 10.0, Uom::Bunch, 10.0);
    laporanGradingDetail($first, $this->brondolan, 5.0, Uom::Kg, 1.0);

    // Muatan tanpa baris parameter TETAP satu baris.
    laporanGradingLoad($this->stationA, '2026-09-05', attributes: ['grading_number' => 'GR-TANPA-DETAIL']);

    $response = $this->actingAs($this->supervisor, 'web')->get(laporanGradingUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'csv',
    ]));
    $response->assertOk();

    $lines = laporanGradingCsvLinesOf(laporanGradingStreamed($response->baseResponse));

    // 1 header + 2 baris parameter + 1 baris muatan tanpa parameter.
    expect($lines)->toHaveCount(4);

    $header = laporanGradingCsvRow($lines[0]);
    expect($header[0])->toBe('Periode');
    expect($header)->toContain('Satuan');
    expect($header)->toContain('Parameter Mutu');

    $dataRows = array_map(fn ($line) => laporanGradingCsvRow($line), array_slice($lines, 1));

    foreach ($dataRows as $row) {
        expect($row[0])->toBe('Periode September Alpha');
        expect($row[1])->toBe('Mill Alpha');
    }

    $units = array_column($dataRows, 12);
    expect($units)->toContain('bunch');
    expect($units)->toContain('kg');

    // Baris muatan tanpa parameter: keempat kolom parameter KOSONG.
    $tanpaDetail = collect($dataRows)->firstWhere(4, 'GR-TANPA-DETAIL');
    expect($tanpaDetail)->not->toBeNull();
    expect($tanpaDetail[11])->toBe('');
    expect($tanpaDetail[12])->toBe('');
    expect($tanpaDetail[13])->toBe('');
    expect($tanpaDetail[14])->toBe('');
});

it('skenario 25 — ekspor: format di luar csv|excel ditolak 422', function () {
    $this->actingAs($this->supervisor, 'web')->getJson(laporanGradingUrl('export', [
        'period_id' => (string) $this->periodA->id,
        'production_line_id' => $this->lineA,
        'format' => 'pdf',
    ]))->assertStatus(422);
});

// =====================================================================
// Keterjangkauan: satu baris yang menentukan layar dapat dicapai
// =====================================================================

it('reachability — REPORT_ROUTES memetakan grading, menaruhnya DI ANTARA weighbridge dan cages-track', function () {
    expect(StationReportService::REPORT_ROUTES)->toHaveKey('grading');
    expect(StationReportService::REPORT_ROUTES['grading'])->toBe('reports.grading');
    expect(route(StationReportService::REPORT_ROUTES['grading'], [], false))->toBe('/reports/grading');

    // URUTANNYA LOAD-BEARING: peta ini harus tetap urut menurut
    // station_types.sort_order (weighbridge 10, grading 20, cages-track 30),
    // karena layar pemilih stasiun membandingkan urutannya dengan urutan
    // master. Asersi ber-urutan, bukan sekadar "memuat".
    $codes = array_keys(StationReportService::REPORT_ROUTES);
    expect(array_slice($codes, 0, 3))->toBe(['weighbridge', 'grading', 'cages-track']);
});
