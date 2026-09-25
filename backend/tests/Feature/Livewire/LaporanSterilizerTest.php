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
use App\Models\Station;
use App\Models\SterilizerDetail;
use App\Models\SterilizerRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
        ->set('periodId', (string) $closed->id)
        ->assertSeeHtml('data-testid="hero-status"')
        ->assertSee('Tertutup')
        ->assertSeeHtml('data-testid="report-kpis"')
        ->assertSeeHtml('data-testid="daily-trend"')
        // Status limits neither the view nor the export: the button is
        // never disabled.
        ->assertSeeHtml('data-testid="export-csv"')
        ->assertDontSeeHtml('disabled');

    $component->call('export', 'csv')->assertFileDownloaded(null, null, 'text/csv');
});

// =====================================================================
// Scenario: "periode yang tidak mencakup jenis stasiun Sterilizer tidak
// dapat dipilih"
// =====================================================================
it('pemilih periode: offers sterilizer and all-station-type periods only, newest first', function () {
    Period::factory()->forBusinessUnit($this->businessUnitA)->stationType(null)
        ->range('2026-10-01', '2026-10-31')->named('Periode Oktober Semua Stasiun')->create();
    Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('boiler-room')
        ->range('2026-08-01', '2026-08-31')->named('Periode Agustus Boiler')->create();

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanSterilizer::class)
        ->assertSee('Periode Oktober Semua Stasiun')
        ->assertSee('Periode September Alpha')
        ->assertDontSee('Periode Agustus Boiler');

    $html = $component->html();

    // Newest first, so the auto-selected option is the October one.
    expect(strpos($html, 'Periode Oktober Semua Stasiun'))
        ->toBeLessThan(strpos($html, 'Periode September Alpha'));
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
