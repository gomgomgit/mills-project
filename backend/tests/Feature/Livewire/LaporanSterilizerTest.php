<?php

/**
 * LaporanSterilizerTest (Feature/Livewire) — screen-129--laporan-sterilizer-web /
 * usecase-129--laporan-sterilizer-web (Laporan Periode Sterilizer).
 *
 * Component tests for App\Livewire\Dashboard\LaporanSterilizer, one per
 * test_scenarios entry's `component_test`. Mirrors
 * tests/Feature/Livewire/ManagementReportTest.php's conventions.
 *
 * COMPONENT SHAPE (deliberately minimal, and asserted as such by the
 * "laporan bersifat baca saja" scenario): properties `businessUnitId`,
 * `periodId`, `showRecap`; methods mount(), toggleRekapHarian(),
 * export($format), render(). There is no updatedBusinessUnitId() hook —
 * switching mill is handled by keepSelectionValid() during render, which
 * is why set('businessUnitId', ...) is enough to reload the period list.
 *
 * ACCESS CONTROL is closed twice over: the route carries
 * 'role:supervisor,mill_management,admin' (EnsureRole -> abort 403 before
 * the component ever mounts), and mount() itself refuses an Operator. The
 * Operator scenario asserts BOTH, because each guard covers a path the
 * other does not.
 */

use App\Enums\UserRole;
use App\Livewire\Dashboard\LaporanSterilizer;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\SterilizerDetail;
use App\Models\SterilizerRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/** One Sterilizer cycle, all six triple-peak times filled, 90 minutes. */
function laporanSterilizerComponentCycle(array $overrides = []): array
{
    return array_merge([
        'sterilizer_no' => '1',
        'close_door_time' => '07:00',
        'peak_1_time' => '07:15',
        'exhaust_1_time' => '07:20',
        'peak_2_time' => '07:35',
        'exhaust_2_time' => '07:40',
        'peak_3_time' => '07:55',
        'exhaust_3_time' => '08:00',
        'open_door_time' => '08:30',
        'duration_minutes' => 90,
        'number_of_cages' => 10,
        'cages_status' => 'Baik',
        'checked_by_spv' => true,
        'remarks' => null,
    ], $overrides);
}

function laporanSterilizerComponentRecord(Station $station, string $date, array $cycles = [], array $recordOverrides = []): SterilizerRecord
{
    $record = SterilizerRecord::factory()->forStation($station)->onDate($date)->create($recordOverrides);

    foreach ($cycles as $cycle) {
        SterilizerDetail::factory()->forRecord($record)->create(laporanSterilizerComponentCycle($cycle));
    }

    return $record;
}

function laporanSterilizerComponentDurations(Station $station, string $date, array $durations): SterilizerRecord
{
    return laporanSterilizerComponentRecord($station, $date, array_map(
        fn ($duration) => ['duration_minutes' => $duration],
        $durations,
    ));
}

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->sterilizer()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->sterilizer()->create();

    // Production line tempat tiap stasiun berdiri. Sejak 2026-09-28
    // memilih line WAJIB di layar laporan, jadi hampir setiap skenario
    // di berkas ini memilihnya lebih dulu — tanpa itu layar dengan sengaja
    // tidak menampilkan satu angka pun.
    $this->lineA = (string) $this->stationA->production_line_id;
    $this->lineB = (string) $this->stationB->production_line_id;

    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->range('2026-09-01', '2026-09-30')
        ->named('Periode September Alpha')
        ->open()
        ->create();
});

// =====================================================================
// Scenario: "success"
// =====================================================================
it('berhasil: renders the four KPI cards, the charts, the outlier threshold, the recap table, and exports CSV', function () {
    laporanSterilizerComponentRecord($this->stationA, '2026-09-05', array_map(
        fn ($duration) => ['duration_minutes' => $duration, 'sterilizer_no' => '1'],
        [88, 90, 91, 92, 93],
    ));
    laporanSterilizerComponentRecord($this->stationA, '2026-09-12', array_map(
        fn ($duration) => ['duration_minutes' => $duration, 'sterilizer_no' => '2'],
        [94, 95, 96, 97, 200],
    ));

    $recordsBefore = SterilizerRecord::count();
    $detailsBefore = SterilizerDetail::count();

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanSterilizer::class)
        ->set('productionLineId', $this->lineA)
        // The newest period is auto-selected, so the page is useful on
        // first paint rather than demanding a choice first.
        ->assertSet('periodId', (string) $this->periodA->id)
        ->assertSeeHtml('data-testid="report-kpis"')
        ->assertSeeHtml('data-testid="kpi-total-cycles"')
        ->assertSeeHtml('data-testid="kpi-total-cages"')
        ->assertSeeHtml('data-testid="kpi-avg-duration"')
        ->assertSeeHtml('data-testid="kpi-triple-peak"')
        ->assertSeeHtml('data-testid="daily-trend"')
        ->assertSeeHtml('data-testid="duration-distribution"')
        ->assertSeeHtml('data-testid="by-unit"')
        ->assertSeeHtml('data-testid="outliers"')
        // The threshold is always written on screen, so the reader knows
        // what the flagging is based on.
        ->assertSeeHtml('data-testid="outlier-threshold"')
        ->assertSeeHtml('data-testid="outlier-item"')
        ->assertSee('Ambang batas yang dipakai:')
        ->assertSeeHtml('data-testid="recap-details"');

    // Daily recap opens, and carries a period Total row.
    $component->call('toggleRekapHarian')
        ->assertSet('showRecap', true)
        ->assertSeeHtml('data-testid="recap-table"')
        ->assertSeeHtml('data-testid="recap-row-2026-09-05"')
        ->assertSeeHtml('data-testid="recap-row-2026-09-12"')
        ->assertSeeHtml('data-testid="recap-row-total"')
        ->assertSee('TOTAL PERIODE');

    $component->call('export', 'csv')->assertFileDownloaded(null, null, 'text/csv');

    // Read-only: nothing about rendering or exporting touches the data.
    expect(SterilizerRecord::count())->toBe($recordsBefore);
    expect(SterilizerDetail::count())->toBe($detailsBefore);
});

// =====================================================================
// Scenario: "Admin belum memilih mill"
// =====================================================================
it('admin tanpa mill: renders an empty mill picker and asks for a mill instead of drawing a report', function () {
    Livewire::actingAs($this->admin)
        ->test(LaporanSterilizer::class)
        ->set('productionLineId', $this->lineA)
        ->assertSet('businessUnitId', '')
        ->assertSeeHtml('data-testid="mill-select"')
        ->assertSeeHtml('data-testid="empty-select-mill"')
        ->assertSee('Pilih mill terlebih dahulu')
        // No period picker, no KPI, no charts — and no error either.
        ->assertDontSeeHtml('data-testid="period-select"')
        ->assertDontSeeHtml('data-testid="report-kpis"')
        ->assertDontSeeHtml('data-testid="daily-trend"')
        ->assertDontSeeHtml('data-testid="empty-no-data"');
});

// =====================================================================
// Scenario: "Admin memilih mill lalu melihat laporannya"
// =====================================================================
it('admin memilih mill: the period list reloads for that mill and every figure comes from it alone', function () {
    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('sterilizer')
        ->range('2026-09-01', '2026-09-30')->named('Periode September Beta')->create();

    laporanSterilizerComponentDurations($this->stationA, '2026-09-10', [90, 100]);
    laporanSterilizerComponentDurations($this->stationB, '2026-09-10', [200, 210, 220]);

    Livewire::actingAs($this->admin)
        ->test(LaporanSterilizer::class)
        ->set('productionLineId', $this->lineA)
        ->set('businessUnitId', (string) $this->businessUnitA->id)
        // keepSelectionValid() auto-selects the newest period of the newly
        // chosen mill — no updatedBusinessUnitId() hook needed.
        ->assertSet('periodId', (string) $this->periodA->id)
        ->assertSee('Periode September Alpha')
        ->assertDontSee('Periode September Beta')
        ->assertDontSeeHtml('data-testid="empty-select-mill"')
        ->assertSeeHtml('data-testid="report-kpis"')
        ->assertSee('Mill Alpha')
        ->assertSeeHtml('data-testid="unit-1"')
        // Mill A only: 2 cycles / 20 cages / 95 minutes. Mill B's three
        // long cycles are nowhere in the figures. ("Mill Beta" itself is
        // still on the page — it is one of the picker's options, which is
        // the whole point of the picker.)
        ->assertViewHas('summary', fn ($summary) => $summary['period']['business_unit_name'] === 'Mill Alpha'
            && $summary['kpi']['total_cycles'] === 2
            && $summary['kpi']['total_cages'] === 20
            && $summary['kpi']['avg_duration_minutes'] === 95.0);

    // Mill B's own period is untouched — it simply is not offered here.
    expect($periodB->fresh())->not->toBeNull();
});

// =====================================================================
// Scenario: "Belum ada Periode Pelaporan"
// =====================================================================
it('belum ada periode: renders an empty period picker plus the hint to create one, without any figure or error', function () {
    $this->periodA->delete();
    Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('boiler-room')
        ->range('2026-09-01', '2026-09-30')->create();

    Livewire::actingAs($this->supervisor)
        ->test(LaporanSterilizer::class)
        ->set('productionLineId', $this->lineA)
        ->assertSet('periodId', '')
        ->assertSeeHtml('data-testid="period-select"')
        ->assertSee('Belum ada periode')
        ->assertSeeHtml('data-testid="empty-no-periods"')
        ->assertSee('Belum ada Periode Pelaporan')
        ->assertDontSeeHtml('data-testid="report-kpis"')
        ->assertDontSeeHtml('data-testid="daily-trend"');
});

// =====================================================================
// Scenario: "Periode tanpa data Sterilizer"
// =====================================================================
it('periode tanpa data: KPI cards render as zero with an explicit message and no empty bars', function () {
    laporanSterilizerComponentDurations($this->stationA, '2026-08-31', [90]);

    Livewire::actingAs($this->supervisor)
        ->test(LaporanSterilizer::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $this->periodA->id)
        ->assertSeeHtml('data-testid="report-kpis"')
        ->assertSeeHtml('data-testid="empty-no-data"')
        ->assertSee('Belum ada data pada periode ini')
        // The charts are not forced to draw empty bars.
        ->assertDontSeeHtml('data-testid="daily-trend"')
        ->assertDontSeeHtml('data-testid="by-unit"')
        ->assertDontSeeHtml('data-testid="recap-table"');
});

// =====================================================================
// Scenario: "Sebagian siklus belum punya durasi"
// =====================================================================
it('sebagian siklus tanpa durasi: the average is undiluted and the excluded-cycle count is shown next to it', function () {
    laporanSterilizerComponentDurations($this->stationA, '2026-09-10', [90, 100, 110, null, null]);

    Livewire::actingAs($this->supervisor)
        ->test(LaporanSterilizer::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $this->periodA->id)
        ->assertSeeHtml('data-testid="kpi-total-cycles"')
        ->assertSeeHtml('data-testid="kpi-avg-duration"')
        // MUST be on screen: the average covers 3 of the 5 cycles, and the
        // page says so rather than letting it read as covering all five.
        ->assertSeeHtml('data-testid="cycles-without-duration"')
        ->assertSee('Dihitung dari 3 siklus')
        ->assertSee('2 siklus tanpa durasi dikeluarkan');
});

// =====================================================================
// Scenario: "Seluruh durasi seragam"
// =====================================================================
it('durasi seragam: the outlier card states there is nothing unusual and still prints the threshold', function () {
    laporanSterilizerComponentDurations($this->stationA, '2026-09-10', array_fill(0, 10, 95));

    Livewire::actingAs($this->supervisor)
        ->test(LaporanSterilizer::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $this->periodA->id)
        ->assertSeeHtml('data-testid="outliers"')
        ->assertSeeHtml('data-testid="outliers-none"')
        ->assertSee('Tidak ada siklus di luar kebiasaan')
        // The bounds stay on screen (IQR = 0 -> 95..95), so the card is
        // never empty-with-no-explanation.
        ->assertSeeHtml('data-testid="outlier-threshold"')
        ->assertDontSeeHtml('data-testid="outliers-insufficient"')
        ->assertDontSeeHtml('data-testid="outlier-item"');
});

// =====================================================================
// Scenario: "Supervisor dan Mill Management hanya melihat millnya sendiri"
// =====================================================================
it('mill sendiri: no mill picker is rendered and only the user\'s own mill appears anywhere', function () {
    Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('sterilizer')
        ->range('2026-09-01', '2026-09-30')->named('Periode September Beta')->create();

    laporanSterilizerComponentDurations($this->stationA, '2026-09-10', [90, 100]);
    laporanSterilizerComponentDurations($this->stationB, '2026-09-10', [200, 210, 220]);

    foreach ([$this->supervisor, $this->millManagement] as $user) {
        Livewire::actingAs($user)
            ->test(LaporanSterilizer::class)
            ->set('productionLineId', $this->lineA)
            // Offering a picker they cannot use would be a lie, so there
            // is none at all.
            ->assertDontSeeHtml('data-testid="mill-select"')
            ->assertSet('periodId', (string) $this->periodA->id)
            ->assertSee('Periode September Alpha')
            ->assertDontSee('Periode September Beta')
            ->assertSee('Mill Alpha')
            ->assertDontSee('Mill Beta');
    }
});

// =====================================================================
// Scenario: "Operator mencoba mengakses"
// =====================================================================
it('operator: the route refuses before mount, and mount() itself refuses too', function () {
    // Route layer — EnsureRole::forbidden() -> abort(403).
    $response = $this->actingAs($this->operator, 'web')->get('/reports/sterilizer');
    $response->assertForbidden();
    $response->assertDontSee('Laporan Periode');

    // Component layer — mount()'s abort_unless(403) covers the component
    // being mounted directly. Livewire's test harness renders the 403
    // error page instead of the component, so assert on that.
    $html = Livewire::actingAs($this->operator)->test(LaporanSterilizer::class)->html();

    expect($html)->toContain('Forbidden');
    expect($html)->not->toContain('data-testid="laporan-sterilizer"');
    expect($html)->not->toContain('data-testid="report-kpis"');
    expect($html)->not->toContain('data-testid="recap-table"');
});

// =====================================================================
// Scenario: "Periode berstatus Tertutup"
// =====================================================================
it('periode tertutup: the report renders as usual, the status shows as a caption, and export still runs', function () {
    $closed = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('sterilizer')
        ->range('2026-11-01', '2026-11-30')->named('Periode November Tertutup')->closed()->create();

    laporanSterilizerComponentDurations($this->stationA, '2026-11-10', [90, 95]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanSterilizer::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $closed->id)
        ->assertSeeHtml('data-testid="hero-status"')
        ->assertSee('Tertutup')
        ->assertSeeHtml('data-testid="report-kpis"')
        ->assertSeeHtml('data-testid="daily-trend"')
        // Status limits neither the view nor the export: the button is
        // never disabled.
        ->assertSeeHtml('data-testid="export-csv"');

    // Tidak ada atribut `disabled` STATIS pada tombol mana pun. Sejak
    // 2026-10-05 tombol ekspor membawa wire:loading.attr="disabled" —
    // penonaktifan SESAAT selama request ekspor berjalan (cegah unduhan
    // ganda), bukan penguncian oleh periode tertutup.
    expect($component->html())->not->toMatch('/\sdisabled(?=[\s>=\/])/');

    $component->call('export', 'csv')->assertFileDownloaded(null, null, 'text/csv');
});

// =====================================================================
// Scenario: "periode yang tidak mencakup jenis stasiun Sterilizer tidak
// dapat dipilih"
// =====================================================================
it('pemilih periode: offers only periods with a sterilizer period_stations row, newest first', function () {
    Period::factory()->forBusinessUnit($this->businessUnitA)->stationType(null)
        ->range('2026-10-01', '2026-10-31')->named('Periode Oktober Semua Stasiun')->create();
    Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('boiler-room')
        ->range('2026-08-01', '2026-08-31')->named('Periode Agustus Boiler')->create();

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanSterilizer::class)
        ->set('productionLineId', $this->lineA)
        ->assertSee('Periode Oktober Semua Stasiun')
        ->assertSee('Periode September Alpha')
        ->assertDontSee('Periode Agustus Boiler');

    $html = $component->html();

    // Newest first, so the auto-selected option is the October one.
    expect(strpos($html, 'Periode Oktober Semua Stasiun'))
        ->toBeLessThan(strpos($html, 'Periode September Alpha'));

    // Periode Oktober dibuat dengan stationType(null), yang sejak 2026-09-25
    // berarti "satu baris period_stations per jenis stasiun" — bukan lagi
    // station_type NULL. Karena itu setiap opsi membawa jenis stasiun layar
    // ini, dan label bersama 'Semua Stasiun' sudah tidak ada.
    expect(array_column($component->viewData('periods'), 'station_type_label'))
        ->toBe(['Sterilizer', 'Sterilizer']);
});

// =====================================================================
// Scenario (BARU 2026-09-26): periode tanpa baris period_stations untuk
// sterilizer tidak muncul di pemilih — perilaku yang DULU dijamin cabang
// orWhereNull('station_type') dan kini sengaja dibuang.
// =====================================================================
it('pemilih periode: periode tanpa baris sterilizer tidak ditawarkan', function () {
    Period::factory()->forBusinessUnit($this->businessUnitA)
        ->stationTypes(['boiler-room', 'clarification'])
        ->range('2026-10-01', '2026-10-31')->named('Periode Oktober Tanpa Sterilizer')->create();
    Period::factory()->forBusinessUnit($this->businessUnitA)
        ->noStations()->range('2026-11-01', '2026-11-30')->named('Periode November Tanpa Stasiun')->create();

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanSterilizer::class)
        ->set('productionLineId', $this->lineA)
        ->assertSee('Periode September Alpha')
        ->assertDontSee('Periode Oktober Tanpa Sterilizer')
        ->assertDontSee('Periode November Tanpa Stasiun');

    expect(array_column($component->viewData('periods'), 'id'))
        ->toBe([(string) $this->periodA->id]);
});

// =====================================================================
// Scenario (BARU 2026-09-26): chip status di hero memakai status STASIUN INI,
// bukan status periode — periode tidak punya status lagi. Sterilizer terbuka
// sementara Boiler Room tertutup di periode yang sama adalah bentuk yang
// menjadi alasan tabel period_stations dipisah.
// =====================================================================
it('chip status: menampilkan status sterilizer, bukan status stasiun lain di periode yang sama', function () {
    $mixed = Period::factory()->forBusinessUnit($this->businessUnitA)
        ->noStations()->range('2026-12-01', '2026-12-31')->named('Periode Desember Campuran')->create();

    PeriodStation::factory()->forPeriod($mixed)->stationType('sterilizer')->open()->create();
    PeriodStation::factory()->forPeriod($mixed)->stationType('boiler-room')->closed()->create();

    // Newest first, jadi periode campuran inilah yang terpilih otomatis.
    $component = Livewire::actingAs($this->supervisor)->test(LaporanSterilizer::class)
        ->set('productionLineId', $this->lineA);

    expect($component->viewData('selectedPeriod')['id'])->toBe((string) $mixed->id);
    expect($component->viewData('selectedPeriod')['status'])->toBe('open');

    preg_match('/data-testid="hero-status">(.*?)<\/span>/s', $component->html(), $matches);

    expect(trim($matches[1] ?? ''))->toBe('Terbuka');
});

// =====================================================================
// Scenario: "rentang inklusif memakai tanggal kejadian"
// =====================================================================
it('rentang inklusif: both bounds are included and a late-synced cycle still belongs to its own date', function () {
    laporanSterilizerComponentDurations($this->stationA, '2026-09-01', [90]);
    laporanSterilizerComponentDurations($this->stationA, '2026-09-30', [95]);
    laporanSterilizerComponentDurations($this->stationA, '2026-08-31', [100]);

    $lateSync = laporanSterilizerComponentDurations($this->stationA, '2026-09-15', [105]);
    DB::table('sterilizer_records')->where('id', $lateSync->id)->update(['created_at' => '2026-10-05 08:00:00']);

    $enteredDuringPeriod = laporanSterilizerComponentDurations($this->stationA, '2026-08-20', [110]);
    DB::table('sterilizer_records')->where('id', $enteredDuringPeriod->id)->update(['created_at' => '2026-09-10 08:00:00']);

    Livewire::actingAs($this->supervisor)
        ->test(LaporanSterilizer::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $this->periodA->id)
        ->call('toggleRekapHarian')
        ->assertSeeHtml('data-testid="recap-row-2026-09-01"')
        ->assertSeeHtml('data-testid="recap-row-2026-09-30"')
        ->assertSeeHtml('data-testid="recap-row-2026-09-15"')
        ->assertDontSeeHtml('data-testid="recap-row-2026-08-31"')
        ->assertDontSeeHtml('data-testid="recap-row-2026-08-20"');
});

// =====================================================================
// Scenario: "kepatuhan triple-peak turun saat waktu puncak atau
// pembuangan tidak lengkap"
// =====================================================================
it('triple-peak: the card shows 75%, not 100%, when one cycle misses one of the six times', function () {
    laporanSterilizerComponentRecord($this->stationA, '2026-09-10', [
        [],
        [],
        [],
        ['exhaust_3_time' => null],
    ]);

    Livewire::actingAs($this->supervisor)
        ->test(LaporanSterilizer::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $this->periodA->id)
        ->assertSeeHtml('data-testid="kpi-triple-peak"')
        ->assertSee('3 dari 4 siklus lengkap')
        ->assertSee('1 siklus belum lengkap keenam waktunya');
});

// =====================================================================
// Scenario: "ambang siklus menyimpang wajib ditampilkan di layar"
// =====================================================================
it('ambang pencilan: the bounds are printed and only cycles outside them are listed, with full context', function () {
    laporanSterilizerComponentRecord($this->stationA, '2026-09-10', array_map(
        fn ($duration) => ['duration_minutes' => $duration, 'sterilizer_no' => '3', 'number_of_cages' => 11],
        [88, 90, 91, 92, 93, 94, 95, 96, 97, 200],
    ));

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanSterilizer::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $this->periodA->id)
        ->assertSeeHtml('data-testid="outlier-threshold"')
        // 84,5 - 102,5 menit, rendered with the Indonesian decimal comma.
        ->assertSee('84,5')
        ->assertSee('102,5')
        ->assertSeeHtml('data-testid="outlier-item"')
        ->assertSee('10 Sep 2026')
        ->assertSee('11 lori')
        ->assertDontSeeHtml('data-testid="outliers-insufficient"');

    // Exactly one cycle is outside the fence — the 200-minute one.
    expect(substr_count($component->html(), 'data-testid="outlier-item"'))->toBe(1);
});

// =====================================================================
// Scenario: "laporan bersifat baca saja"
// =====================================================================
it('baca saja: the component exposes no create/update/delete action and changes no row', function () {
    laporanSterilizerComponentDurations($this->stationA, '2026-09-10', [90, 95, 100]);

    $recordsBefore = SterilizerRecord::count();
    $detailsBefore = SterilizerDetail::count();

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanSterilizer::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $this->periodA->id)
        ->call('toggleRekapHarian')
        ->assertSeeHtml('data-testid="recap-table"');

    // The public surface is a picker, a toggle and an export — nothing
    // that reads like a write action.
    $publicMethods = collect((new ReflectionClass(LaporanSterilizer::class))->getMethods(ReflectionMethod::IS_PUBLIC))
        ->filter(fn (ReflectionMethod $method) => $method->getDeclaringClass()->getName() === LaporanSterilizer::class)
        ->map(fn (ReflectionMethod $method) => $method->getName())
        ->sort()
        ->values()
        ->all();

    expect($publicMethods)->toBe(['export', 'mount', 'render', 'toggleRekapHarian']);

    foreach (['create', 'store', 'update', 'save', 'delete', 'destroy', 'verify', 'acknowledge'] as $writeAction) {
        expect($publicMethods)->not->toContain($writeAction);
    }

    // No edit/add/delete affordance on screen either.
    $html = $component->html();
    expect($html)->not->toContain('wire:submit');
    expect($html)->not->toContain('>Tambah<');
    expect($html)->not->toContain('>Hapus<');
    expect($html)->not->toContain('>Ubah<');

    expect(SterilizerRecord::count())->toBe($recordsBefore);
    expect(SterilizerDetail::count())->toBe($detailsBefore);
});

// =====================================================================
// Scenario: "Admin mengunduh CSV setelah memilih mill"
//
// REGRESSION — the export path is the ONE place where the mill is resolved
// a second time, and every earlier export scenario logs in as Supervisor,
// whose mill comes from auth()->user()->business_unit_id and can therefore
// never be missing. Admin is the only role whose mill lives in the
// component's own state, so only an Admin download proves the component
// threads its resolved mill into the service instead of leaving it null —
// which is refused with 422 (ValidationException) before a single byte is
// streamed, and looks to the user like an inert Ekspor button.
// =====================================================================
it('admin ekspor: an Admin who picked a mill actually downloads the CSV, and it carries that mill alone', function () {
    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('sterilizer')
        ->range('2026-09-01', '2026-09-30')->named('Periode September Beta')->create();

    laporanSterilizerComponentRecord($this->stationA, '2026-09-10', [
        ['sterilizer_no' => '1', 'duration_minutes' => 90],
        ['sterilizer_no' => '2', 'duration_minutes' => 95],
    ], ['sterilizer_id' => 'STR-ADMIN-ALPHA']);

    laporanSterilizerComponentRecord($this->stationB, '2026-09-10', [
        ['sterilizer_no' => '9', 'duration_minutes' => 200],
    ], ['sterilizer_id' => 'STR-ADMIN-BETA']);

    $recordsBefore = SterilizerRecord::count();
    $detailsBefore = SterilizerDetail::count();

    $component = Livewire::actingAs($this->admin)
        ->test(LaporanSterilizer::class)
        ->set('productionLineId', $this->lineA)
        ->set('businessUnitId', (string) $this->businessUnitA->id)
        ->assertSet('periodId', (string) $this->periodA->id);

    // THE DOWNLOAD MUST ACTUALLY HAPPEN. Asserting "no exception" would be
    // satisfied by a page that silently returns null; assertFileDownloaded
    // is only satisfied by a streamed response with the CSV content type.
    $download = $component->call('export', 'csv');
    $download->assertFileDownloaded(null, null, 'text/csv');

    $effect = $download->effects['download'];
    expect($effect['name'])->toEndWith('.csv');

    // The bytes belong to the picked mill and to no other — an Admin export
    // that fell back to "every mill" would show up right here.
    $body = base64_decode($effect['content']);
    expect($body)->toContain('STR-ADMIN-ALPHA');
    expect($body)->not->toContain('STR-ADMIN-BETA');

    // Read-only: exporting changes nothing, and Mill B's period is untouched.
    expect(SterilizerRecord::count())->toBe($recordsBefore);
    expect(SterilizerDetail::count())->toBe($detailsBefore);
    expect($periodB->fresh())->not->toBeNull();
});

// =====================================================================
// REGRESSION — `#[Url(as: 'business_unit_id')]`: hidrasi mill dari query
// string.
//
// StationReportService membangun tautan setiap tile Laporan Stasiun
// (screen-140) sebagai route($routeName, ['business_unit_id' => $id]),
// jadi kunci yang benar-benar dipakai di URL adalah `business_unit_id`.
// Tanpa `as:`, #[Url] memakai NAMA PROPERTI ('businessUnitId') sebagai
// kunci query — dan kedua kunci itu tidak akan pernah bertemu.
//
// Akibatnya kalau `as:` hilang atau salah ketik: Admin yang baru saja
// memilih mill lalu menekan sebuah tile MENDARAT DI LAYAR YANG MEMINTANYA
// MEMILIH MILL LAGI, tanpa satu angka pun termuat — persis seperti sebelum
// perbaikan, dan tanpa satu test pun memerah. Itulah yang ditutup di sini.
//
// Asersinya sengaja PERILAKU dan bukan refleksi atas atributnya: membaca
// atribut PHP hanya menguji ejaan, dan tetap hijau kalau Livewire mengubah
// semantik `as:`.
// =====================================================================
it('hidrasi query string: Admin yang tiba dari tautan tile langsung melihat laporan mill itu, bukan permintaan memilih mill lagi', function () {
    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('sterilizer')
        ->range('2026-09-01', '2026-09-30')->named('Periode September Beta')->create();

    laporanSterilizerComponentDurations($this->stationA, '2026-09-10', [90, 100]);
    laporanSterilizerComponentDurations($this->stationB, '2026-09-10', [200, 210, 220]);

    // (a) Permukaan HTTP — URL yang bentuknya persis seperti yang dibangun
    // StationReportService untuk tile Sterilizer.
    $response = $this->actingAs($this->admin, 'web')
        ->get(route('reports.sterilizer', ['business_unit_id' => $this->businessUnitA->id]));

    $response->assertOk();
    $response->assertDontSee('Pilih mill terlebih dahulu');
    $response->assertSee('Periode September Alpha');
    $response->assertDontSee('Periode September Beta');

    // (b) Permukaan komponen — propertinya benar-benar terhidrasi, periode
    // mill itu ikut termuat, dan angkanya berasal dari mill itu saja.
    Livewire::actingAs($this->admin)
        ->withQueryParams(['business_unit_id' => (string) $this->businessUnitA->id])
        ->test(LaporanSterilizer::class)
        ->set('productionLineId', $this->lineA)
        ->assertSet('businessUnitId', (string) $this->businessUnitA->id)
        ->assertSet('periodId', (string) $this->periodA->id)
        ->assertViewHas('needsMillSelection', false)
        ->assertDontSeeHtml('data-testid="empty-select-mill"')
        ->assertSeeHtml('data-testid="report-kpis"')
        ->assertViewHas('summary', fn ($summary) => $summary !== null
            && $summary['period']['business_unit_name'] === 'Mill Alpha'
            && $summary['kpi']['total_cycles'] === 2);

    expect($periodB->fresh())->not->toBeNull();
});

// =====================================================================
// SISI SEBALIKNYA — peran yang terikat satu mill.
//
// resolvedBusinessUnitId() MENGABAIKAN properti ini sepenuhnya untuk
// Supervisor / Mill Management, jadi memaksa mill lain lewat query string
// tidak boleh mengubah apa pun. Test ini menjaga agar seseorang kelak
// tidak "memperbaiki" hidrasi dengan cara yang membuka kebocoran lintas
// mill.
//
// Asersi `businessUnitId` yang terhidrasi disengaja: tanpanya test ini
// bisa hijau hanya karena query string tidak pernah sampai ke komponen —
// hijau yang tidak membuktikan apa-apa.
// =====================================================================
it('peran terikat mill: memaksa mill lain lewat query string tidak mengubah apa pun bagi Supervisor / Mill Management', function () {
    Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('sterilizer')
        ->range('2026-09-01', '2026-09-30')->named('Periode September Beta')->create();

    laporanSterilizerComponentDurations($this->stationA, '2026-09-10', [90, 100]);
    laporanSterilizerComponentDurations($this->stationB, '2026-09-10', [200, 210, 220]);

    foreach ([$this->supervisor, $this->millManagement] as $user) {
        $response = $this->actingAs($user, 'web')
            ->get(route('reports.sterilizer', ['business_unit_id' => $this->businessUnitB->id]));

        $response->assertOk();
        $response->assertSee('Periode September Alpha');
        $response->assertDontSee('Periode September Beta');
        $response->assertDontSee('Mill Beta');

        Livewire::actingAs($user)
            ->withQueryParams(['business_unit_id' => (string) $this->businessUnitB->id])
            ->test(LaporanSterilizer::class)
            ->set('productionLineId', $this->lineA)
            // Terhidrasi — dan tetap diabaikan.
            ->assertSet('businessUnitId', (string) $this->businessUnitB->id)
            ->assertSet('periodId', (string) $this->periodA->id)
            ->assertViewHas('summary', fn ($summary) => $summary !== null
                && $summary['period']['business_unit_name'] === 'Mill Alpha'
                && $summary['kpi']['total_cycles'] === 2);
    }
});

// =====================================================================
// PRODUCTION LINE — konsumen pertama kolom `production_line_id` (ccc884d)
//
// Lima jaminan, satu per skenario di bawah:
//   1. belum memilih line  -> tidak ada satu angka pun, hanya arahan memilih
//   2. memilih line        -> angkanya MILIK LINE ITU, bukan jumlah dua line
//   3. line mill lain      -> diabaikan, lewat properti maupun query string
//   4. ekspor CSV          -> ikut tersaring ke line terpilih
//   5. stasiun dipindah    -> recordnya TETAP terhitung di line asalnya
//
// SETIAP skenario penyaringan dibuat DUA ARAH — data line terpilih ADA, data
// line lain TIDAK ADA. Alasannya bukan gaya: test berjalan di SQLite,
// produksi di PostgreSQL, dan SQLite memperlakukan `where "kolom_tak_ada" = ?`
// sebagai perbandingan string literal — 0 baris, tanpa error. Tanpa sisi
// "ADA", sebuah filter yang menyaring HABIS akan hijau di sini dan meledak di
// PostgreSQL.
// =====================================================================

/**
 * Line KEDUA di MILL YANG SAMA, lengkap dengan stasiun Sterilizer-nya
 * sendiri. Sengaja satu mill: jaminan yang diuji di sini bukan cakupan mill
 * (itu sudah ditutup ec32cd9) melainkan cakupan LINE DI DALAM satu mill.
 */
function laporanSterilizerSecondLine(BusinessUnit $businessUnit, string $name = 'Line Kedua'): Station
{
    $line = ProductionLine::factory()->create([
        'business_unit_id' => $businessUnit->id,
        'name' => $name,
    ]);

    return Station::factory()->forProductionLine($line)->sterilizer()->create();
}

/** Isi berkas CSV yang benar-benar diunduh dari layar. */
function laporanSterilizerDownloadedCsv(Testable $component): string
{
    return base64_decode((string) data_get($component->effects, 'download.content'));
}

it('production line: tanpa line terpilih tidak ada satu angka pun, hanya arahan memilih', function () {
    // Line A: 2 siklus / 20 lori.
    laporanSterilizerComponentRecord($this->stationA, '2026-09-05', [
        ['duration_minutes' => 90, 'number_of_cages' => 10],
        ['duration_minutes' => 90, 'number_of_cages' => 10, 'sterilizer_no' => '2'],
    ], ['sterilizer_id' => 'STR-LINE-A']);

    Livewire::actingAs($this->supervisor)
        ->test(LaporanSterilizer::class)
        ->assertSet('productionLineId', '')
        ->assertViewHas('needsProductionLineSelection', true)
        // Tidak ada angka sama sekali — bukan laporan kosong, bukan nol.
        ->assertViewHas('summary', null)
        ->assertSeeHtml('data-testid="production-line-select"')
        ->assertSeeHtml('data-testid="select-production-line-hint"')
        ->assertSee('Pilih production line terlebih dahulu')
        // TANPA opsi "semua" — itu perbedaan disengaja dari Data Browser.
        ->assertDontSee('Semua Line')
        ->assertDontSee('Semua Production Line')
        ->assertDontSeeHtml('data-testid="report-kpis"')
        ->assertDontSeeHtml('data-testid="daily-trend"');
});

it('production line: angka yang tampil milik line terpilih, bukan jumlah dua line', function () {
    // Line A: 2 siklus / 20 lori.
    laporanSterilizerComponentRecord($this->stationA, '2026-09-05', [
        ['duration_minutes' => 90, 'number_of_cages' => 10],
        ['duration_minutes' => 90, 'number_of_cages' => 10, 'sterilizer_no' => '2'],
    ], ['sterilizer_id' => 'STR-LINE-A']);

    $stationC = laporanSterilizerSecondLine($this->businessUnitA);
    $lineC = (string) $stationC->production_line_id;
    // Line C: 3 siklus / 300 lori — angka yang sama sekali berbeda, sehingga
    // "jumlah kedua line" (5 siklus / 320 lori) tidak bisa lolos sebagai
    // salah satu dari keduanya.
    laporanSterilizerComponentRecord($stationC, '2026-09-06', [
        ['duration_minutes' => 50, 'number_of_cages' => 100],
        ['duration_minutes' => 50, 'number_of_cages' => 100, 'sterilizer_no' => '2'],
        ['duration_minutes' => 50, 'number_of_cages' => 100, 'sterilizer_no' => '3'],
    ], ['sterilizer_id' => 'STR-LINE-C']);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanSterilizer::class)
        ->set('productionLineId', $this->lineA);

    // ARAH PERTAMA — line A: angkanya milik A, dan BUKAN A+C.
    $component->assertViewHas('summary', fn ($summary) => $summary['kpi']['total_cycles'] === 2
        && $summary['kpi']['total_cages'] === 20
        && $summary['kpi']['avg_duration_minutes'] === 90.0);

    // ARAH KEDUA — line C: angkanya berpindah seluruhnya ke C. Tanpa arah ini
    // sebuah filter yang menyaring habis juga akan hijau.
    $component->set('productionLineId', $lineC)
        ->assertViewHas('summary', fn ($summary) => $summary['kpi']['total_cycles'] === 3
            && $summary['kpi']['total_cages'] === 300
            && $summary['kpi']['avg_duration_minutes'] === 50.0);
});

it('production line: line mill lain diabaikan, lewat properti maupun lewat query string', function () {
    // Line A: 2 siklus / 20 lori.
    laporanSterilizerComponentRecord($this->stationA, '2026-09-05', [
        ['duration_minutes' => 90, 'number_of_cages' => 10],
        ['duration_minutes' => 90, 'number_of_cages' => 10, 'sterilizer_no' => '2'],
    ], ['sterilizer_id' => 'STR-LINE-A']);

    // (a) Lewat properti — dibuang saat render, jatuh ke "belum memilih".
    Livewire::actingAs($this->supervisor)
        ->test(LaporanSterilizer::class)
        ->set('productionLineId', $this->lineB)
        ->assertSet('productionLineId', '')
        ->assertViewHas('needsProductionLineSelection', true)
        ->assertViewHas('summary', null);

    // (b) Lewat query string — sama saja.
    Livewire::actingAs($this->supervisor)
        ->withQueryParams(['production_line_id' => $this->lineB])
        ->test(LaporanSterilizer::class)
        ->assertSet('productionLineId', '')
        ->assertViewHas('summary', null);

    // (c) SISI POSITIFNYA, dan inilah yang menjaga `as: 'production_line_id'`:
    // line yang sah dari query string BENAR-BENAR terhidrasi dan langsung
    // memuat laporannya. Tanpa `as:`, Livewire memakai nama properti
    // ('productionLineId') sebagai kunci query, keduanya tidak bertemu, dan
    // asersi (a)/(b) di atas tetap hijau tanpa menandai apa pun.
    Livewire::actingAs($this->supervisor)
        ->withQueryParams(['production_line_id' => $this->lineA])
        ->test(LaporanSterilizer::class)
        ->assertSet('productionLineId', $this->lineA)
        ->assertViewHas('needsProductionLineSelection', false)
        ->assertViewHas('summary', fn ($summary) => $summary !== null && $summary['kpi']['total_cycles'] === 2
        && $summary['kpi']['total_cages'] === 20
        && $summary['kpi']['avg_duration_minutes'] === 90.0);
});

it('production line: ekspor CSV hanya memuat baris line terpilih', function () {
    // Line A: 2 siklus / 20 lori.
    laporanSterilizerComponentRecord($this->stationA, '2026-09-05', [
        ['duration_minutes' => 90, 'number_of_cages' => 10],
        ['duration_minutes' => 90, 'number_of_cages' => 10, 'sterilizer_no' => '2'],
    ], ['sterilizer_id' => 'STR-LINE-A']);

    $stationC = laporanSterilizerSecondLine($this->businessUnitA);
    $lineC = (string) $stationC->production_line_id;
    // Line C: 3 siklus / 300 lori — angka yang sama sekali berbeda, sehingga
    // "jumlah kedua line" (5 siklus / 320 lori) tidak bisa lolos sebagai
    // salah satu dari keduanya.
    laporanSterilizerComponentRecord($stationC, '2026-09-06', [
        ['duration_minutes' => 50, 'number_of_cages' => 100],
        ['duration_minutes' => 50, 'number_of_cages' => 100, 'sterilizer_no' => '2'],
        ['duration_minutes' => 50, 'number_of_cages' => 100, 'sterilizer_no' => '3'],
    ], ['sterilizer_id' => 'STR-LINE-C']);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanSterilizer::class)
        ->set('productionLineId', $this->lineA);

    $component->call('export', 'csv')->assertFileDownloaded(null, null, 'text/csv');

    $csv = laporanSterilizerDownloadedCsv($component);

    expect($csv)->toContain('STR-LINE-A');
    expect($csv)->not->toContain('STR-LINE-C');

    // Arah sebaliknya, berkas yang sama sekali berbeda isinya.
    $component->set('productionLineId', $lineC)->call('export', 'csv');

    $csvC = laporanSterilizerDownloadedCsv($component);

    expect($csvC)->toContain('STR-LINE-C');
    expect($csvC)->not->toContain('STR-LINE-A');
});

it('production line: tanpa line terpilih tidak ada berkas yang diunduh sama sekali', function () {
    // Line A: 2 siklus / 20 lori.
    laporanSterilizerComponentRecord($this->stationA, '2026-09-05', [
        ['duration_minutes' => 90, 'number_of_cages' => 10],
        ['duration_minutes' => 90, 'number_of_cages' => 10, 'sterilizer_no' => '2'],
    ], ['sterilizer_id' => 'STR-LINE-A']);

    Livewire::actingAs($this->supervisor)
        ->test(LaporanSterilizer::class)
        ->call('export', 'csv')
        ->assertNoFileDownloaded();
});

it('production line: record yang stasiunnya sudah dipindah tetap terhitung di line asalnya', function () {
    // Line A: 2 siklus / 20 lori.
    laporanSterilizerComponentRecord($this->stationA, '2026-09-05', [
        ['duration_minutes' => 90, 'number_of_cages' => 10],
        ['duration_minutes' => 90, 'number_of_cages' => 10, 'sterilizer_no' => '2'],
    ], ['sterilizer_id' => 'STR-LINE-A']);

    // Stasiunnya dipindah ke line lain DI MILL YANG SAMA — perubahan
    // konfigurasi yang sah, bukan perbaikan data.
    $lineBaru = ProductionLine::factory()->create([
        'business_unit_id' => $this->businessUnitA->id,
        'name' => 'Line Baru',
    ]);

    $this->stationA->update(['production_line_id' => $lineBaru->id]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanSterilizer::class);

    // DI LINE ASALNYA: masih terhitung utuh. Hanya mungkin karena filternya
    // membaca kolom `production_line_id` DI TABEL RECORD — sebuah join ke
    // `stations` akan memindahkan angka ini ke Line Baru dan menulis ulang
    // sejarah periode yang sudah lewat.
    $component->set('productionLineId', $this->lineA)
        ->assertViewHas('summary', fn ($summary) => $summary['kpi']['total_cycles'] === 2
        && $summary['kpi']['total_cages'] === 20
        && $summary['kpi']['avg_duration_minutes'] === 90.0);

    // DI LINE BARUNYA: tidak ada apa pun. Stasiunnya memang ada di sana
    // sekarang, tetapi tidak satu pun record dihasilkan di sana.
    $component->set('productionLineId', (string) $lineBaru->id)
        ->assertViewHas('summary', fn ($summary) => $summary['kpi']['total_cycles'] === 0 && $summary['kpi']['total_cages'] === 0);
});
