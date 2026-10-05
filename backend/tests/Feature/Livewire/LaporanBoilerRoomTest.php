<?php

/**
 * LaporanBoilerRoomTest (Feature/Livewire) — screen-131--laporan-boiler-room-web /
 * usecase-131--laporan-boiler-room-web (Laporan Periode Boiler Room).
 *
 * Component tests for App\Livewire\Dashboard\LaporanBoilerRoom, one per
 * test_scenarios entry's `component_test`. Mirrors
 * tests/Feature/Livewire/LaporanCagesTrackTest.php (screen-130).
 *
 * COMPONENT SHAPE (deliberately minimal, and asserted as such): properties
 * `businessUnitId`, `periodId`, `showRecap`; methods mount(),
 * toggleRekapHarian(), export($format), render(). There is no
 * updatedBusinessUnitId() hook — switching mill is handled by
 * keepSelectionValid() during render, which is why set('businessUnitId',
 * ...) alone is enough to reload the period list.
 *
 * ACCESS CONTROL is closed twice over: the route carries
 * 'role:supervisor,mill_management,admin' (EnsureRole -> abort 403 before
 * the component ever mounts), and mount() itself refuses an Operator. The
 * Operator scenario asserts BOTH, because each guard covers a path the
 * other does not. There is no mobile Boiler Room report (screen-137), so
 * unlike Cages & Tracks nothing here was widened for Operator.
 *
 * THE MILL IS NEVER NEGOTIABLE FROM THE UI for a bound role: the
 * "mill lain" scenario forces the public `businessUnitId` property to
 * another mill and asserts that not one figure moves.
 *
 * ----------------------------------------------------------------------
 * ON THE DAILY RECAP'S DEFAULT STATE — RESOLVED (tech spec v2)
 * ----------------------------------------------------------------------
 * The tech spec used to contradict itself: edge_case_handling called the
 * daily recap a `<details>` CLOSED by default, while the SAME spec's
 * test_scenarios said the opposite in two places — scenario 1's
 * component_test requires data-testid="daily-recap" to be PRESENT on first
 * render, and scenario 14's requires it to be ABSENT after the FIRST toggle
 * and present again after the second (its browser_test likewise says the
 * first click "menutup"). Those only make sense from an OPEN start, and a
 * `<details>` would keep the table in the DOM while merely collapsing it —
 * which scenario 14 explicitly rejects.
 *
 * RESOLUTION: test_scenarios were authoritative. edge_case_handling has
 * been corrected in tech spec v2 to read "open by default, closed via a
 * button, markup @if-guarded so the rows leave the DOM" — which is exactly
 * what these tests assert and what the implementation already does
 * (showRecap = true, a plain button, @if-guarded markup). No assertion
 * here changed; only this note did.
 */

use App\Enums\UserRole;
use App\Livewire\Dashboard\LaporanBoilerRoom;
use App\Models\BoilerRoomDetail;
use App\Models\BoilerRoomRecord;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use App\Services\BoilerRoomRecordService;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * One boiler_room_records header plus one boiler_room_details row per entry
 * of $rows, with `time_slot` filled in from the canonical grid by position.
 *
 * @param  list<array<string, mixed>>  $rows
 */
function laporanBoilerRoomComponentRecord(Station $station, string $date, array $rows = [], array $overrides = []): BoilerRoomRecord
{
    $record = BoilerRoomRecord::factory()->forStation($station)->onDate($date)->create(array_merge([
        'boiler_room_id' => 'BLR-1',
        'note' => null,
    ], $overrides));

    $slots = BoilerRoomRecordService::canonicalTimeSlots();

    foreach (array_values($rows) as $index => $row) {
        BoilerRoomDetail::factory()->forRecord($record)->create(array_merge([
            'time_slot' => $slots[$index % count($slots)],
        ], $row));
    }

    return $record;
}

/**
 * $count rows each filling only steam_pressure_bar (plus $extra).
 *
 * @return list<array<string, mixed>>
 */
function laporanBoilerRoomComponentPressureRows(int $count, float $pressure = 20.0, array $extra = []): array
{
    $rows = [];

    for ($index = 0; $index < $count; $index++) {
        $rows[] = array_merge(['steam_pressure_bar' => $pressure], $extra);
    }

    return $rows;
}

/** The five headline metric cards, by their data-testid stem. */
const LAPORAN_BOILER_ROOM_METRIC_CARDS = [
    'steam-pressure', 'steam-temp', 'water-tds', 'water-ph', 'exhaust-gas-temp',
];

/**
 * Markup pemilih PERIODE saja. Sejak 2026-09-28 pemilih Production Line
 * berdiri di sebelahnya dengan <option>-nya sendiri, jadi menghitung
 * <option> di seluruh halaman tidak lagi menjawab pertanyaan yang skenario
 * ini ajukan ("berapa periode yang ditawarkan"). Menyempitkannya ke elemen
 * pemilih periode membuat asersinya lebih tepat, bukan lebih longgar.
 */
function laporanBoilerRoomPeriodSelectHtml(string $html): string
{
    preg_match('/<select[^>]*data-testid="period-select(?:or)?"[\s\S]*?<\/select>/', $html, $matches);

    return $matches[0] ?? '';
}

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->boilerRoom()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->boilerRoom()->create();

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
        ->stationType('boiler-room')
        ->range('2026-03-01', '2026-03-31')
        ->named('Periode Maret Alpha')
        ->open()
        ->create();
});

// =====================================================================
// Scenario 1: "berhasil sebagai Supervisor atau Mill Management"
// =====================================================================
it('berhasil: no mill picker, the mill caption, every metric card with its own reading count, and every section', function () {
    laporanBoilerRoomComponentRecord($this->stationA, '2026-03-02', laporanBoilerRoomComponentPressureRows(12, 20.0, [
        'steam_temp_c' => 260.0,
        'water_tds_ppm' => 2000.0,
        'water_ph' => 10.5,
        'exhaust_gas_temp_c' => 210.0,
        'blowdown_executed' => 'y',
    ]), ['boiler_room_id' => 'BLR-1']);

    laporanBoilerRoomComponentRecord($this->stationA, '2026-03-03', laporanBoilerRoomComponentPressureRows(8, 24.0, [
        'steam_temp_c' => 280.0,
        'water_tds_ppm' => 2200.0,
        'water_ph' => 11.5,
        'exhaust_gas_temp_c' => 230.0,
        'sootblowing_executed' => 'y',
    ]), ['boiler_room_id' => 'BLR-2']);

    foreach ([$this->supervisor, $this->millManagement] as $user) {
        $component = Livewire::actingAs($user)
            ->test(LaporanBoilerRoom::class)
            ->set('productionLineId', $this->lineA)
            // The newest period is auto-selected, so the page is useful on
            // first paint rather than demanding a choice first.
            ->assertSet('periodId', (string) $this->periodA->id)
            // Offering a picker they cannot use would be a lie.
            ->assertDontSeeHtml('data-testid="mill-selector"')
            ->assertSeeHtml('data-testid="mill-name"')
            ->assertSee('Mill Alpha')
            ->assertSeeHtml('data-testid="period-selector"')
            ->assertSeeHtml('data-testid="recording-coverage"')
            ->assertSeeHtml('data-testid="maintenance-blowdown-executed"')
            ->assertSeeHtml('data-testid="maintenance-sootblowing-executed"')
            ->assertSeeHtml('data-testid="daily-trend-steam-pressure"')
            ->assertSeeHtml('data-testid="per-unit-recap"')
            // PRESENT on first render — see the file docblock on the recap's
            // default state.
            ->assertSeeHtml('data-testid="daily-recap"')
            ->assertSeeHtml('data-testid="export-button"')
            // The label without which the raw extremes read as a broken
            // report next to the daily averages.
            ->assertSeeHtml('data-testid="raw-extremes-note"')
            ->assertDontSeeHtml('data-testid="empty-period-notice"');

        // Every metric card carries min / avg / max AND its own reading
        // count, side by side — an average over 3 readings and one over 300
        // must never look equally convincing.
        foreach (LAPORAN_BOILER_ROOM_METRIC_CARDS as $card) {
            $component->assertSeeHtml('data-testid="metric-'.$card.'-min"');
            $component->assertSeeHtml('data-testid="metric-'.$card.'-avg"');
            $component->assertSeeHtml('data-testid="metric-'.$card.'-max"');
            $component->assertSeeHtml('data-testid="metric-'.$card.'-reading-count"');
        }

        $component->assertViewHas('summary', fn ($summary) => $summary['metrics']['steam_pressure_bar']['reading_count'] === 20
            && $summary['metrics']['steam_pressure_bar']['avg'] === 21.6
            && $summary['maintenance']['blowdown']['executed'] === 12
            && $summary['maintenance']['sootblowing']['executed'] === 8
            && $summary['coverage']['filled_slots'] === 20
            && count($summary['by_unit']) === 2);
    }
});

// =====================================================================
// Scenario 2: "berhasil sebagai Admin"
// =====================================================================
it('admin: the mill picker is rendered, the period list follows the chosen mill, and every section appears', function () {
    laporanBoilerRoomComponentRecord($this->stationA, '2026-03-10', laporanBoilerRoomComponentPressureRows(4, 20.0, [
        'steam_temp_c' => 260.0,
        'water_tds_ppm' => 2000.0,
        'water_ph' => 10.0,
        'exhaust_gas_temp_c' => 200.0,
    ]));

    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('boiler-room')
        ->range('2026-03-01', '2026-03-31')->named('Periode Maret Beta')->open()->create();

    $component = Livewire::actingAs($this->admin)
        ->test(LaporanBoilerRoom::class)
        ->set('productionLineId', $this->lineA)
        // Admin is the one role not bound to a mill, so it gets the picker.
        ->assertSeeHtml('data-testid="mill-selector"')
        ->assertSeeHtml('data-testid="select-mill-first-hint"')
        ->set('businessUnitId', (string) $this->businessUnitA->id);

    // The period list refreshed to the chosen mill, and the newest one of
    // THAT mill was selected — no updatedBusinessUnitId() hook needed.
    $component->assertSet('periodId', (string) $this->periodA->id);
    $component->assertViewHas('periods', fn ($periods) => array_column($periods, 'id') === [(string) $this->periodA->id]);
    expect($component->viewData('periods'))->not->toContain($periodB->id);

    $component
        ->assertSeeHtml('data-testid="recording-coverage"')
        ->assertSeeHtml('data-testid="maintenance-blowdown-executed"')
        ->assertSeeHtml('data-testid="maintenance-sootblowing-executed"')
        ->assertSeeHtml('data-testid="daily-trend-steam-pressure"')
        ->assertSeeHtml('data-testid="per-unit-recap"')
        ->assertSeeHtml('data-testid="daily-recap"')
        ->assertSeeHtml('data-testid="export-button"')
        ->assertDontSeeHtml('data-testid="select-mill-first-hint"');

    foreach (LAPORAN_BOILER_ROOM_METRIC_CARDS as $card) {
        $component->assertSeeHtml('data-testid="metric-'.$card.'-avg"');
        $component->assertSeeHtml('data-testid="metric-'.$card.'-reading-count"');
    }
});

// =====================================================================
// Scenario 3: "Admin memilih mill lebih dulu"
// =====================================================================
it('admin tanpa mill: the picker and the hint are shown, and not one report figure is rendered', function () {
    laporanBoilerRoomComponentRecord($this->stationA, '2026-03-10', laporanBoilerRoomComponentPressureRows(4));

    Livewire::actingAs($this->admin)
        ->test(LaporanBoilerRoom::class)
        ->set('productionLineId', $this->lineA)
        ->assertSeeHtml('data-testid="mill-selector"')
        ->assertSeeHtml('data-testid="select-mill-first-hint"')
        // The page asks for a mill instead of drawing an empty report that
        // would read as "this mill has no data".
        ->assertDontSeeHtml('data-testid="metric-steam-pressure-avg"')
        ->assertDontSeeHtml('data-testid="recording-coverage"')
        ->assertDontSeeHtml('data-testid="daily-recap"')
        ->assertDontSeeHtml('data-testid="per-unit-recap"')
        ->assertDontSeeHtml('data-testid="export-button"')
        ->assertViewHas('summary', null);
});

// =====================================================================
// Scenario 4: "mill belum punya periode"
// =====================================================================
it('mill tanpa periode: the period picker has no option at all and the contact-Admin hint is shown', function () {
    $supervisorB = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitB)->create();

    // Line milik Mill Beta: $this->lineA ada di Mill Alpha dan karena itu
    // DIABAIKAN di sini — persis jaminan "line mill lain tidak pernah
    // terpakai" yang diuji tersendiri di bawah.
    $lineB = (string) $this->stationB->production_line_id;

    $component = Livewire::actingAs($supervisorB)->test(LaporanBoilerRoom::class)
        ->set('productionLineId', $lineB);

    $component
        ->assertSeeHtml('data-testid="period-selector"')
        ->assertSeeHtml('data-testid="no-period-hint"')
        ->assertSee('Kelola Periode Pelaporan')
        ->assertSet('periodId', '')
        ->assertDontSeeHtml('data-testid="metric-steam-pressure-avg"')
        ->assertDontSeeHtml('data-testid="recording-coverage"')
        ->assertDontSeeHtml('data-testid="per-unit-recap"');

    // Rendered deliberately WITHOUT a placeholder option — an empty picker,
    // not a fake "belum ada periode" entry.
    expect(laporanBoilerRoomPeriodSelectHtml($component->html()))->not->toContain('<option value="');
});

// =====================================================================
// Scenario 5: "periode tanpa data"
// =====================================================================
it('periode tanpa data: the empty notice appears, every metric reads as unavailable rather than 0, and no chart is drawn', function () {
    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanBoilerRoom::class)
        ->set('productionLineId', $this->lineA)
        ->assertSet('periodId', (string) $this->periodA->id)
        ->assertSeeHtml('data-testid="empty-period-notice"')
        // An empty chart would read as a measured flat line.
        ->assertDontSeeHtml('data-testid="daily-trend-steam-pressure"')
        ->assertDontSeeHtml('data-testid="daily-trend-steam-temp"')
        ->assertDontSeeHtml('data-testid="per-unit-recap"')
        ->assertDontSeeHtml('data-testid="daily-recap"');

    foreach (LAPORAN_BOILER_ROOM_METRIC_CARDS as $card) {
        // '–', never '0': zero would read as "measured, and it was zero".
        $component->assertSeeHtml('<span data-testid="metric-'.$card.'-avg">–</span>');
        $component->assertDontSeeHtml('<span data-testid="metric-'.$card.'-avg">0</span>');
        $component->assertSeeHtml('<b data-testid="metric-'.$card.'-reading-count">0</b>');
    }

    $component->assertViewHas('summary', fn ($summary) => $summary['has_data'] === false
        && $summary['metrics']['steam_pressure_bar']['avg'] === null);
});

// =====================================================================
// Scenario 6: "sebuah metrik tidak pernah diisi"
// =====================================================================
it('satu metrik kosong: the pH card reads unavailable with 0 readings while the pressure card keeps its own 20', function () {
    laporanBoilerRoomComponentRecord($this->stationA, '2026-03-07', laporanBoilerRoomComponentPressureRows(20, 18.0));

    Livewire::actingAs($this->supervisor)
        ->test(LaporanBoilerRoom::class)
        ->set('productionLineId', $this->lineA)
        ->assertSeeHtml('<span data-testid="metric-water-ph-avg">–</span>')
        ->assertSeeHtml('<b data-testid="metric-water-ph-reading-count">0</b>')
        // Independent per metric — one empty metric does not touch another.
        ->assertSeeHtml('<span data-testid="metric-steam-pressure-avg">18,0</span>')
        ->assertSeeHtml('<b data-testid="metric-steam-pressure-reading-count">20</b>');
});

// =====================================================================
// Scenario 7: "perawatan tidak tercatat"
// =====================================================================
it('perawatan: the three states are rendered as three separate figures, and not recorded is never merged into not done', function () {
    $states = ['y', 'y', 'y', 'n', 'n', null, null, null, null, null];
    $rows = [];

    foreach ($states as $state) {
        $rows[] = ['steam_pressure_bar' => 20.0, 'blowdown_executed' => $state];
    }

    laporanBoilerRoomComponentRecord($this->stationA, '2026-03-08', $rows);

    Livewire::actingAs($this->supervisor)
        ->test(LaporanBoilerRoom::class)
        ->set('productionLineId', $this->lineA)
        ->assertSeeHtml('<span data-testid="maintenance-blowdown-executed">3</span>')
        ->assertSeeHtml('<span data-testid="maintenance-blowdown-not-executed">2</span>')
        ->assertSeeHtml('<b data-testid="maintenance-blowdown-not-recorded">5</b>')
        // Written down as three distinct labelled figures, never one blended
        // "not done" of 7.
        ->assertSee('tidak tercatat')
        ->assertDontSeeHtml('<span data-testid="maintenance-blowdown-not-executed">7</span>')
        ->assertViewHas('summary', fn ($summary) => $summary['maintenance']['blowdown']['executed'] === 3
            && $summary['maintenance']['blowdown']['not_executed'] === 2
            && $summary['maintenance']['blowdown']['not_recorded'] === 5);
});

// =====================================================================
// Scenario 8: "pencatatan sangat tidak lengkap"
// =====================================================================
it('kelengkapan rendah: the coverage card sits above every figure, and the small reading counts stay beside them', function () {
    $period = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('boiler-room')
        ->range('2026-04-01', '2026-04-30')->named('Periode April Tipis')->open()->create();

    laporanBoilerRoomComponentRecord($this->stationA, '2026-04-10', laporanBoilerRoomComponentPressureRows(6, 21.0));

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanBoilerRoom::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $period->id)
        ->assertSeeHtml('data-testid="recording-coverage"')
        ->assertSeeHtml('<strong data-testid="coverage-filled-slots">6</strong>')
        ->assertSeeHtml('data-testid="coverage-expected-slots"')
        // The figures are still shown — the reader is told how thin they are
        // instead of being shown nothing.
        ->assertSeeHtml('<span data-testid="metric-steam-pressure-avg">21,0</span>')
        ->assertSeeHtml('<b data-testid="metric-steam-pressure-reading-count">6</b>');

    $html = $component->html();

    // ABOVE every other figure, not a footnote: a period filled to a few
    // per cent still produces tidy-looking averages.
    expect(strpos($html, 'data-testid="recording-coverage"'))
        ->toBeLessThan(strpos($html, 'data-testid="report-metrics"'));
    expect(strpos($html, 'data-testid="recording-coverage"'))
        ->toBeLessThan(strpos($html, 'data-testid="per-unit-recap"'));
});

// =====================================================================
// Scenario 9: "mill punya beberapa unit boiler"
// =====================================================================
it('beberapa unit: three rows in the per-unit recap, the unrecorded unit among them with 0 readings', function () {
    laporanBoilerRoomComponentRecord($this->stationA, '2026-03-12', [
        ['time_slot' => '08:00', 'steam_pressure_bar' => 20.0],
    ], ['boiler_room_id' => 'BLR-1']);

    laporanBoilerRoomComponentRecord($this->stationA, '2026-03-12', [
        ['time_slot' => '08:00', 'steam_pressure_bar' => 24.0],
    ], ['boiler_room_id' => 'BLR-2']);

    laporanBoilerRoomComponentRecord($this->stationA, '2026-03-12', array_fill(0, 3, []), [
        'boiler_room_id' => 'BLR-3',
    ]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanBoilerRoom::class)
        ->set('productionLineId', $this->lineA)
        ->assertSeeHtml('data-testid="per-unit-recap"')
        ->assertSeeHtml('data-testid="per-unit-row-BLR-1"')
        ->assertSeeHtml('data-testid="per-unit-row-BLR-2"')
        // Dropping it would hide exactly the unit that was never written
        // down — the one worth seeing.
        ->assertSeeHtml('data-testid="per-unit-row-BLR-3"');

    $component->assertViewHas('summary', function ($summary) {
        $blank = collect($summary['by_unit'])->firstWhere('boiler_room_id', 'BLR-3');

        return $blank['reading_count'] === 0
            && $blank['steam_pressure_avg'] === null
            // The period cards combine BLR-1 and BLR-2.
            && $summary['metrics']['steam_pressure_bar']['avg'] === 22.0
            && $summary['metrics']['steam_pressure_bar']['reading_count'] === 2;
    });

    // The BLR-3 row renders its emptiness as a dash and a 0, not a fake zero
    // average.
    expect($component->html())->toContain('data-testid="per-unit-row-BLR-3"');
});

// =====================================================================
// Scenario 10: "akun belum terhubung ke mill"
// =====================================================================
it('akun tanpa mill: the contact-Admin notice, NO mill picker at all, and no figures', function () {
    $noMillSupervisor = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    Livewire::actingAs($noMillSupervisor)
        ->test(LaporanBoilerRoom::class)
        ->set('productionLineId', $this->lineA)
        ->assertSeeHtml('data-testid="no-mill-hint"')
        ->assertSee('Hubungi Admin')
        // FAIL CLOSED: offering the all-mills list to a role that is meant
        // to be tied to one mill turns a broken master-data row into a
        // cross-mill leak.
        ->assertDontSeeHtml('data-testid="mill-selector"')
        ->assertDontSeeHtml('data-testid="period-selector"')
        ->assertDontSeeHtml('data-testid="metric-steam-pressure-avg"')
        ->assertDontSeeHtml('data-testid="recording-coverage"')
        ->assertDontSeeHtml('data-testid="per-unit-recap"')
        ->assertViewHas('businessUnitOptions', [])
        ->assertViewHas('summary', null);
});

// =====================================================================
// Scenario 11: "mencoba melihat mill lain"
// =====================================================================
it('mill lain: forcing businessUnitId changes nothing, and another mill period is replaced before it is ever read', function () {
    laporanBoilerRoomComponentRecord($this->stationA, '2026-03-10', [['steam_pressure_bar' => 20.0]]);
    laporanBoilerRoomComponentRecord($this->stationB, '2026-03-10', [['steam_pressure_bar' => 900.0]]);

    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('boiler-room')
        ->range('2026-03-01', '2026-03-31')->named('Periode Maret Beta')->open()->create();

    // The mill is not negotiable from the UI for a bound role:
    // resolvedBusinessUnitId() never consults $businessUnitId for them.
    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanBoilerRoom::class)
        ->set('productionLineId', $this->lineA)
        ->set('businessUnitId', (string) $this->businessUnitB->id)
        ->assertSeeHtml('data-testid="mill-name"')
        ->assertSee('Mill Alpha')
        ->assertDontSee('Mill Beta')
        ->assertSeeHtml('<span data-testid="metric-steam-pressure-avg">20,0</span>')
        ->assertDontSee('900');

    $component->assertViewHas('summary', fn ($summary) => $summary['business_unit']['name'] === 'Mill Alpha'
        && $summary['metrics']['steam_pressure_bar']['avg'] === 20.0);

    // Hand-forcing another mill's period: it is not in this mill's list, so
    // keepSelectionValid() replaces it before authorizePeriod() ever sees
    // it — no other mill's figure is ever rendered.
    $component->set('periodId', (string) $periodB->id)
        ->assertSet('periodId', (string) $this->periodA->id)
        ->assertDontSee('Periode Maret Beta')
        ->assertDontSee('900');
});

// =====================================================================
// Scenario 12: "Operator mencoba membuka layar web ini"
// =====================================================================
it('operator: the route refuses before mount, and mount() itself refuses too', function () {
    // Route layer — EnsureRole::forbidden() -> abort(403).
    $response = $this->actingAs($this->operator, 'web')->get('/reports/boiler-room');
    $response->assertForbidden();
    $response->assertDontSee('Laporan Periode');

    // Component layer — mount()'s abort_unless(403) covers the component
    // being mounted directly, which is exactly how this scenario exercises
    // it. Livewire's test harness renders the 403 error page instead of the
    // component, so assert on that.
    $html = Livewire::actingAs($this->operator)->test(LaporanBoilerRoom::class)->html();

    expect($html)->toContain('Forbidden');
    expect($html)->not->toContain('data-testid="laporan-boiler-room"');
    expect($html)->not->toContain('data-testid="report-metrics"');
    expect($html)->not->toContain('data-testid="per-unit-recap"');
    expect($html)->not->toContain('data-testid="daily-recap"');
});

// =====================================================================
// Scenario 13: "periode tertutup"
// =====================================================================
it('periode tertutup: the status is a caption, the report is complete and the export button is never disabled', function () {
    $closed = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('boiler-room')
        ->range('2026-05-01', '2026-05-31')->named('Periode Mei Tertutup')->closed()->create();

    laporanBoilerRoomComponentRecord($this->stationA, '2026-05-10', laporanBoilerRoomComponentPressureRows(4, 20.0, [
        'steam_temp_c' => 260.0,
        'water_tds_ppm' => 2000.0,
        'water_ph' => 10.0,
        'exhaust_gas_temp_c' => 200.0,
    ]));

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanBoilerRoom::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $closed->id)
        ->assertSeeHtml('data-testid="period-status"')
        ->assertSee('Tertutup')
        ->assertSeeHtml('data-testid="recording-coverage"')
        ->assertSeeHtml('data-testid="per-unit-recap"')
        ->assertSeeHtml('data-testid="daily-recap"')
        // The period lock governs writing data, not reading a report.
        ->assertSeeHtml('data-testid="export-button"');

    // Tidak ada atribut `disabled` STATIS pada tombol mana pun. Sejak
    // 2026-10-05 tombol ekspor membawa wire:loading.attr="disabled" —
    // penonaktifan SESAAT selama request ekspor berjalan (cegah unduhan
    // ganda), bukan penguncian oleh periode tertutup.
    expect($component->html())->not->toMatch('/\sdisabled(?=[\s>=\/])/');

    $component->call('export', 'csv')->assertFileDownloaded(null, null, 'text/csv');
});

// =====================================================================
// Scenario 14: "rekap harian panjang"
//
// The FIRST toggle CLOSES the recap and the SECOND re-opens it — which only
// makes sense from an open start, and is the opposite of what
// edge_case_handling's "<details> tertutup bawaan" line says. See the file
// docblock; this test follows test_scenarios, verbatim.
// =====================================================================
it('rekap panjang: the toggle hides the recap on the first call and brings it back on the second, cards untouched', function () {
    foreach (range(1, 20) as $day) {
        laporanBoilerRoomComponentRecord($this->stationA, sprintf('2026-03-%02d', $day), [
            ['steam_pressure_bar' => 20.0 + $day, 'steam_temp_c' => 250.0 + $day],
        ]);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanBoilerRoom::class)
        ->set('productionLineId', $this->lineA)
        ->assertSeeHtml('data-testid="daily-recap-toggle"')
        // OPEN on first render.
        ->assertSeeHtml('data-testid="daily-recap"')
        ->assertSeeHtml('data-testid="report-metrics"')
        ->assertSeeHtml('data-testid="daily-trend-steam-pressure"');

    // First toggle — the table leaves the DOM entirely, rather than being
    // merely collapsed (which is why this is a button and a Livewire
    // property, not a <details>).
    $component->call('toggleRekapHarian')
        ->assertDontSeeHtml('data-testid="daily-recap"')
        ->assertSeeHtml('data-testid="daily-recap-toggle"')
        // The headline figures and the trend survive both states.
        ->assertSeeHtml('data-testid="report-metrics"')
        ->assertSeeHtml('data-testid="daily-trend-steam-pressure"');

    // Second toggle — back again.
    $component->call('toggleRekapHarian')
        ->assertSeeHtml('data-testid="daily-recap"')
        ->assertSeeHtml('data-testid="report-metrics"')
        ->assertSeeHtml('data-testid="daily-trend-steam-pressure"');

    $component->assertViewHas('summary', fn ($summary) => count($summary['daily']) === 20);
});

// =====================================================================
// Scenario 15: "layar hanya membaca"
// =====================================================================
it('baca saja: no write-flavoured control anywhere, and rendering changes not one row', function () {
    laporanBoilerRoomComponentRecord($this->stationA, '2026-03-10', laporanBoilerRoomComponentPressureRows(5, 20.0, [
        'water_ph' => 7.0,
        'blowdown_executed' => 'y',
    ]));

    $recordsBefore = BoilerRoomRecord::count();
    $detailsBefore = BoilerRoomDetail::count();

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanBoilerRoom::class)
        ->set('productionLineId', $this->lineA)
        ->call('toggleRekapHarian')
        ->call('toggleRekapHarian')
        ->html();

    foreach (['save-button', 'edit-button', 'delete-button', 'add-row-button', 'remove-row-button'] as $writeish) {
        expect($html)->not->toContain('data-testid="'.$writeish.'"');
    }

    // The component's public surface stays a toggle and an export.
    preg_match_all('/wire:click="([a-zA-Z]+)/', $html, $matches);
    expect(array_unique($matches[1]))->toEqualCanonicalizing(['toggleRekapHarian', 'export']);

    expect(BoilerRoomRecord::count())->toBe($recordsBefore);
    expect(BoilerRoomDetail::count())->toBe($detailsBefore);
});

// =====================================================================
// Scenario 16: "daftar periode hanya yang mencakup Boiler Room"
// =====================================================================
it('pemilih periode: hanya periode yang punya baris period_stations boiler-room yang ditawarkan', function () {
    $allTypes = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType(null)
        ->range('2026-06-01', '2026-06-30')->named('Periode Semua Stasiun')->open()->create();
    $otherType = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('sterilizer')
        ->range('2026-07-01', '2026-07-31')->named('Periode Sterilizer Saja')->open()->create();

    $component = Livewire::actingAs($this->supervisor)->test(LaporanBoilerRoom::class)
        ->set('productionLineId', $this->lineA);

    $component
        ->assertSee('Periode Maret Alpha')
        ->assertSee('Periode Semua Stasiun')
        ->assertDontSee('Periode Sterilizer Saja');

    $component->assertViewHas('periods', function ($periods) use ($allTypes, $otherType) {
        $ids = array_column($periods, 'id');

        return in_array((string) $this->periodA->id, $ids, true)
            && in_array((string) $allTypes->id, $ids, true)
            && ! in_array((string) $otherType->id, $ids, true);
    });

    // Newest first. Periode 'Semua Stasiun' dibuat dengan stationType(null),
    // yang sejak 2026-09-25 berarti "satu baris period_stations per jenis
    // stasiun" — bukan station_type NULL. Setiap opsi karena itu berlabel jenis
    // stasiun layar ini; label bersama 'Semua Stasiun' sudah tidak ada.
    expect($component->viewData('periods')[0]['id'])->toBe((string) $allTypes->id);
    expect($component->viewData('periods')[0]['station_type'])->toBe('boiler-room');
    expect($component->viewData('periods')[0]['station_type_label'])->toBe('Boiler Room');
});

// =====================================================================
// Scenario (BARU 2026-09-26): periode tanpa baris period_stations untuk
// boiler-room tidak ditawarkan — perilaku yang DULU dijamin cabang
// orWhereNull('station_type') dan kini sengaja dibuang.
// =====================================================================
it('pemilih periode: periode tanpa baris boiler-room tidak ditawarkan', function () {
    Period::factory()->forBusinessUnit($this->businessUnitA)
        ->stationTypes(['sterilizer', 'clarification'])
        ->range('2026-06-01', '2026-06-30')->named('Periode Tanpa Boiler')->open()->create();
    Period::factory()->forBusinessUnit($this->businessUnitA)
        ->noStations()->range('2026-07-01', '2026-07-31')->named('Periode Tanpa Stasiun')->create();

    $component = Livewire::actingAs($this->supervisor)->test(LaporanBoilerRoom::class)
        ->set('productionLineId', $this->lineA)
        ->assertSee('Periode Maret Alpha')
        ->assertDontSee('Periode Tanpa Boiler')
        ->assertDontSee('Periode Tanpa Stasiun');

    expect(array_column($component->viewData('periods'), 'id'))
        ->toBe([(string) $this->periodA->id]);
});

// =====================================================================
// Scenario (BARU 2026-09-26): status opsi memakai status boiler-room, bukan
// status stasiun lain di periode yang sama.
// =====================================================================
it('pemilih periode: status opsi memakai status boiler-room, bukan status stasiun lain', function () {
    $mixed = Period::factory()->forBusinessUnit($this->businessUnitA)
        ->noStations()->range('2026-06-01', '2026-06-30')->named('Periode Campuran')->create();

    PeriodStation::factory()->forPeriod($mixed)->stationType('boiler-room')->open()->create();
    PeriodStation::factory()->forPeriod($mixed)->stationType('sterilizer')->closed()->create();

    $component = Livewire::actingAs($this->supervisor)->test(LaporanBoilerRoom::class)
        ->set('productionLineId', $this->lineA);

    $option = collect($component->viewData('periods'))->firstWhere('id', (string) $mixed->id);

    expect($option['status'])->toBe('open');
});

// =====================================================================
// Scenario 17: "rentang periode inklusif di kedua ujung"
// =====================================================================
it('rentang inklusif: the recap and the trend carry both bounds, and nothing from outside them', function () {
    laporanBoilerRoomComponentRecord($this->stationA, '2026-03-01', [['steam_pressure_bar' => 10.0]]);
    laporanBoilerRoomComponentRecord($this->stationA, '2026-03-31', [['steam_pressure_bar' => 30.0]]);
    laporanBoilerRoomComponentRecord($this->stationA, '2026-02-28', [['steam_pressure_bar' => 99.0]]);
    laporanBoilerRoomComponentRecord($this->stationA, '2026-04-01', [['steam_pressure_bar' => 88.0]]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanBoilerRoom::class)
        ->set('productionLineId', $this->lineA)
        ->assertSeeHtml('data-testid="daily-recap"')
        ->assertSeeHtml('data-testid="daily-recap-row-2026-03-01"')
        ->assertSeeHtml('data-testid="daily-recap-row-2026-03-31"')
        ->assertDontSeeHtml('data-testid="daily-recap-row-2026-02-28"')
        ->assertDontSeeHtml('data-testid="daily-recap-row-2026-04-01"')
        // The trend has a column for each of the two bound dates.
        ->assertSeeHtml('data-testid="daily-trend-steam-pressure-col-2026-03-01"')
        ->assertSeeHtml('data-testid="daily-trend-steam-pressure-col-2026-03-31"');

    $component->assertViewHas('summary', fn ($summary) => $summary['metrics']['steam_pressure_bar']['reading_count'] === 2
        && $summary['metrics']['steam_pressure_bar']['min'] === 10.0
        && $summary['metrics']['steam_pressure_bar']['max'] === 30.0
        && array_column($summary['daily'], 'date') === ['2026-03-01', '2026-03-31']);

    // The out-of-range readings appear nowhere on the page.
    expect($component->html())->not->toContain('99,0');
    expect($component->html())->not->toContain('88,0');
});

// =====================================================================
// Scenario 18: "setiap metrik punya penyebutnya sendiri"
// =====================================================================
it('penyebut terpisah: the pH card reads 7,0 over 4 readings while the pressure card reads its raw 12,0 low', function () {
    $pressures = [12.0, 28.0, 20.0, 20.0, 20.0, 20.0, 20.0, 20.0, 20.0, 20.0];
    $phValues = [6.0, 7.0, 7.0, 8.0, null, null, null, null, null, null];
    $rows = [];

    foreach ($pressures as $index => $pressure) {
        $rows[] = ['steam_pressure_bar' => $pressure, 'water_ph' => $phValues[$index]];
    }

    laporanBoilerRoomComponentRecord($this->stationA, '2026-03-02', $rows);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanBoilerRoom::class)
        ->set('productionLineId', $this->lineA)
        // 28.0 / 4 = 7.0, rendered with its own denominator beside it.
        ->assertSeeHtml('<span data-testid="metric-water-ph-avg">7,0</span>')
        ->assertSeeHtml('<b data-testid="metric-water-ph-reading-count">4</b>')
        ->assertDontSeeHtml('<span data-testid="metric-water-ph-avg">2,8</span>')
        ->assertSeeHtml('<b data-testid="metric-steam-pressure-reading-count">10</b>')
        // The card's low comes from the RAW slot reading...
        ->assertSeeHtml('<span data-testid="metric-steam-pressure-min">12,0</span>')
        // ...while the recap row for the same date reads the DAILY average.
        ->assertSeeHtml('data-testid="daily-recap-row-2026-03-02"')
        // Without this label the reader concludes the report is broken.
        ->assertSeeHtml('data-testid="raw-extremes-note"')
        ->assertSee('tidak dapat dicocokkan');

    $component->assertViewHas('summary', function ($summary) {
        $recap = collect($summary['daily'])->firstWhere('date', '2026-03-02');

        return $summary['metrics']['water_ph']['avg'] === 7.0
            && $summary['metrics']['water_ph']['reading_count'] === 4
            && $summary['metrics']['steam_pressure_bar']['reading_count'] === 10
            && $summary['metrics']['steam_pressure_bar']['min'] === 12.0
            // 20.0 in the recap, 12.0 on the card. Deliberately different.
            && $recap['steam_pressure_avg'] === 20.0;
    });

    // Both numbers are on the page at once, which is exactly why the label
    // above it exists.
    expect($component->html())->toContain('20,0');
    expect($component->html())->toContain('12,0');
});

// =====================================================================
// Scenario 19: "jumlah pembacaan ditampilkan berdampingan dengan angkanya"
// =====================================================================
it('reading count: every metric card renders its average and its own reading count in the same block', function () {
    $rows = [];

    for ($index = 0; $index < 10; $index++) {
        $rows[] = [
            'steam_pressure_bar' => 20.0,
            'steam_temp_c' => $index < 7 ? 260.0 : null,
            'water_tds_ppm' => $index < 5 ? 2000.0 : null,
            'water_ph' => $index < 4 ? 7.0 : null,
            'exhaust_gas_temp_c' => $index < 2 ? 210.0 : null,
        ];
    }

    laporanBoilerRoomComponentRecord($this->stationA, '2026-03-05', $rows);

    $component = Livewire::actingAs($this->supervisor)->test(LaporanBoilerRoom::class)
        ->set('productionLineId', $this->lineA);
    $html = $component->html();

    $expected = [
        'steam-pressure' => 10,
        'steam-temp' => 7,
        'water-tds' => 5,
        'water-ph' => 4,
        'exhaust-gas-temp' => 2,
    ];

    foreach ($expected as $card => $count) {
        $component->assertSeeHtml('data-testid="metric-'.$card.'-avg"');
        $component->assertSeeHtml('<b data-testid="metric-'.$card.'-reading-count">'.$count.'</b>');

        // Same card, avg first then its count — no metric is shown without
        // one, and no count is borrowed from another metric.
        $cardStart = strpos($html, 'data-testid="metric-'.$card.'"');
        $avgAt = strpos($html, 'data-testid="metric-'.$card.'-avg"');
        $countAt = strpos($html, 'data-testid="metric-'.$card.'-reading-count"');

        expect($cardStart)->not->toBeFalse();
        expect($avgAt)->toBeGreaterThan($cardStart);
        expect($countAt)->toBeGreaterThan($avgAt);
    }
});

// =====================================================================
// Scenario 20: "laju bahan bakar dan beban fan tidak pernah dirata-rata"
// =====================================================================
it('teks bebas: no metric card, no trend and no per-unit column for fuel feed rate or fan load', function () {
    laporanBoilerRoomComponentRecord($this->stationA, '2026-03-11', [
        [
            'steam_pressure_bar' => 20.0,
            'fuel_feed_rate' => '12 ton/jam',
            'id_fan_load' => '80%',
            'sa_fan_load' => 'sedang',
        ],
    ]);

    $html = Livewire::actingAs($this->supervisor)->test(LaporanBoilerRoom::class)->set('productionLineId', $this->lineA)->html();

    // Their units are mixed on the paper form (Hz / % / tons), so averaging
    // them is not merely wrong, it is meaningless.
    foreach (['fuel-feed-rate', 'id-fan-load', 'sa-fan-load'] as $card) {
        foreach (['min', 'avg', 'max', 'reading-count'] as $part) {
            expect($html)->not->toContain('data-testid="metric-'.$card.'-'.$part.'"');
        }

        expect($html)->not->toContain('data-testid="daily-trend-'.$card.'"');
    }

    // And their values never surface as an aggregate on the page at all —
    // the export is the only place they appear.
    expect($html)->not->toContain('12 ton/jam');
    expect($html)->not->toContain('Laju Bahan Bakar');
    expect($html)->not->toContain('Beban ID Fan');
});

// =====================================================================
// Scenario 21: "tidak ada penandaan nilai di luar batas"
// =====================================================================
it('tanpa ambang: extreme values render in the same neutral style, with no badge, icon, label or danger colour', function () {
    laporanBoilerRoomComponentRecord($this->stationA, '2026-03-18', [
        ['steam_pressure_bar' => 5.0, 'steam_temp_c' => 100.0],
        ['steam_pressure_bar' => 95.0, 'steam_temp_c' => 400.0],
    ]);

    $html = Livewire::actingAs($this->supervisor)->test(LaporanBoilerRoom::class)->set('productionLineId', $this->lineA)->html();

    // The extremes ARE rendered — they are simply not judged.
    expect($html)->toContain('data-testid="metric-steam-pressure-min"');
    expect($html)->toContain('5,0');
    expect($html)->toContain('95,0');

    // Boiler Room has no operational-target master, so a threshold here
    // would be a statistic dressed up as a safety limit. Judgement is the
    // reader's — the absence is what gets asserted.
    foreach ([
        'threshold-badge', 'out-of-range-icon', 'alert-label', 'threshold-card',
        'outlier', 'severity',
    ] as $forbidden) {
        expect(strtolower($html))->not->toContain($forbidden);
    }

    foreach ([
        'text-red', 'bg-red', 'md-chip--danger', 'md-chip--warning',
        'md-trendchart__col--low', 'md-trendchart__col--high', 'is-danger', 'is-warning',
    ] as $colour) {
        expect($html)->not->toContain($colour);
    }
});

// =====================================================================
// Scenario 22: "Admin mengunduh CSV setelah memilih mill"
//
// REGRESSION — the export path resolves the mill a SECOND time
// (BoilerRoomReportService::buildExportRows() -> resolveBusinessUnit()),
// and every earlier export scenario logs in as Supervisor, whose mill
// comes from auth()->user()->business_unit_id and can therefore never be
// missing. Admin is the only role whose mill lives in the component's own
// state, so only an Admin download proves the component threads its
// RESOLVED mill into the service instead of leaving it null — null is
// refused with 422 (ValidationException) before a single byte is streamed,
// which looks to the user like an inert Ekspor button.
// =====================================================================
it('admin ekspor: an Admin who picked a mill actually downloads the CSV, and it carries that mill alone', function () {
    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('boiler-room')
        ->range('2026-03-01', '2026-03-31')->named('Periode Maret Beta')->open()->create();

    laporanBoilerRoomComponentRecord($this->stationA, '2026-03-10', laporanBoilerRoomComponentPressureRows(4, 20.0, [
        'steam_temp_c' => 260.0,
        'water_tds_ppm' => 2000.0,
        'water_ph' => 10.0,
        'exhaust_gas_temp_c' => 200.0,
    ]), ['boiler_room_id' => 'BLR-ADMIN-ALPHA']);

    laporanBoilerRoomComponentRecord($this->stationB, '2026-03-10', laporanBoilerRoomComponentPressureRows(2, 99.0), [
        'boiler_room_id' => 'BLR-ADMIN-BETA',
    ]);

    $recordsBefore = BoilerRoomRecord::count();
    $detailsBefore = BoilerRoomDetail::count();

    $component = Livewire::actingAs($this->admin)
        ->test(LaporanBoilerRoom::class)
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
    expect($body)->toContain('BLR-ADMIN-ALPHA');
    expect($body)->not->toContain('BLR-ADMIN-BETA');

    // Read-only: exporting changes nothing, and Mill B's period is untouched.
    expect(BoilerRoomRecord::count())->toBe($recordsBefore);
    expect(BoilerRoomDetail::count())->toBe($detailsBefore);
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
// memilih mill lalu menekan tile Boiler Room MENDARAT DI LAYAR YANG
// MEMINTANYA MEMILIH MILL LAGI, tanpa satu angka pun termuat — persis
// seperti sebelum perbaikan, dan tanpa satu test pun memerah. Itulah yang
// ditutup di sini.
//
// Asersinya sengaja PERILAKU dan bukan refleksi atas atributnya: membaca
// atribut PHP hanya menguji ejaan, dan tetap hijau kalau Livewire mengubah
// semantik `as:`.
// =====================================================================
it('hidrasi query string: Admin yang tiba dari tautan tile langsung melihat laporan mill itu, bukan permintaan memilih mill lagi', function () {
    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('boiler-room')
        ->range('2026-03-01', '2026-03-31')->named('Periode Maret Beta')->create();

    laporanBoilerRoomComponentRecord($this->stationA, '2026-03-10', laporanBoilerRoomComponentPressureRows(4));
    laporanBoilerRoomComponentRecord($this->stationB, '2026-03-10', laporanBoilerRoomComponentPressureRows(6, 30.0));

    // (a) Permukaan HTTP — URL yang bentuknya persis seperti yang dibangun
    // StationReportService untuk tile Boiler Room.
    $response = $this->actingAs($this->admin, 'web')
        ->get(route('reports.boiler-room', ['business_unit_id' => $this->businessUnitA->id]));

    $response->assertOk();
    $response->assertDontSee('Pilih mill terlebih dahulu');
    $response->assertSee('Periode Maret Alpha');
    $response->assertDontSee('Periode Maret Beta');

    // (b) Permukaan komponen — propertinya benar-benar terhidrasi, periode
    // mill itu ikut termuat, dan angkanya berasal dari mill itu saja.
    Livewire::actingAs($this->admin)
        ->withQueryParams(['business_unit_id' => (string) $this->businessUnitA->id])
        ->test(LaporanBoilerRoom::class)
        ->set('productionLineId', $this->lineA)
        ->assertSet('businessUnitId', (string) $this->businessUnitA->id)
        ->assertSet('periodId', (string) $this->periodA->id)
        ->assertViewHas('needsMillSelection', false)
        ->assertDontSeeHtml('data-testid="select-mill-first-hint"')
        ->assertViewHas('summary', fn ($summary) => $summary !== null
            && $summary['period']['business_unit_name'] === 'Mill Alpha'
            && $summary['period']['name'] === 'Periode Maret Alpha');

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
    Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('boiler-room')
        ->range('2026-03-01', '2026-03-31')->named('Periode Maret Beta')->create();

    laporanBoilerRoomComponentRecord($this->stationA, '2026-03-10', laporanBoilerRoomComponentPressureRows(4));
    laporanBoilerRoomComponentRecord($this->stationB, '2026-03-10', laporanBoilerRoomComponentPressureRows(6, 30.0));

    foreach ([$this->supervisor, $this->millManagement] as $user) {
        $response = $this->actingAs($user, 'web')
            ->get(route('reports.boiler-room', ['business_unit_id' => $this->businessUnitB->id]));

        $response->assertOk();
        $response->assertSee('Periode Maret Alpha');
        $response->assertDontSee('Periode Maret Beta');
        $response->assertDontSee('Mill Beta');

        Livewire::actingAs($user)
            ->withQueryParams(['business_unit_id' => (string) $this->businessUnitB->id])
            ->test(LaporanBoilerRoom::class)
            ->set('productionLineId', $this->lineA)
            // Terhidrasi — dan tetap diabaikan.
            ->assertSet('businessUnitId', (string) $this->businessUnitB->id)
            ->assertSet('periodId', (string) $this->periodA->id)
            ->assertViewHas('summary', fn ($summary) => $summary !== null
                && $summary['period']['business_unit_name'] === 'Mill Alpha'
                && $summary['period']['name'] === 'Periode Maret Alpha');
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
 * Line KEDUA di MILL YANG SAMA, lengkap dengan stasiun Boiler Room-nya
 * sendiri. Sengaja satu mill: jaminan yang diuji di sini bukan cakupan mill
 * (itu sudah ditutup ec32cd9) melainkan cakupan LINE DI DALAM satu mill.
 */
function laporanBoilerRoomSecondLine(BusinessUnit $businessUnit, string $name = 'Line Kedua'): Station
{
    $line = ProductionLine::factory()->create([
        'business_unit_id' => $businessUnit->id,
        'name' => $name,
    ]);

    return Station::factory()->forProductionLine($line)->boilerRoom()->create();
}

/** Isi berkas CSV yang benar-benar diunduh dari layar. */
function laporanBoilerRoomDownloadedCsv(Testable $component): string
{
    return base64_decode((string) data_get($component->effects, 'download.content'));
}

it('production line: tanpa line terpilih tidak ada satu angka pun, hanya arahan memilih', function () {
    // Line A: 2 slot terisi, tekanan 20,0 bar.
    laporanBoilerRoomComponentRecord($this->stationA, '2026-03-05',
        laporanBoilerRoomComponentPressureRows(2, 20.0),
        ['boiler_room_id' => 'BLR-LINE-A']);

    Livewire::actingAs($this->supervisor)
        ->test(LaporanBoilerRoom::class)
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
        ->assertDontSeeHtml('data-testid="recording-coverage"')
        ->assertDontSeeHtml('data-testid="per-unit-recap"');
});

it('production line: angka yang tampil milik line terpilih, bukan jumlah dua line', function () {
    // Line A: 2 slot terisi, tekanan 20,0 bar.
    laporanBoilerRoomComponentRecord($this->stationA, '2026-03-05',
        laporanBoilerRoomComponentPressureRows(2, 20.0),
        ['boiler_room_id' => 'BLR-LINE-A']);

    $stationC = laporanBoilerRoomSecondLine($this->businessUnitA);
    $lineC = (string) $stationC->production_line_id;
    // Line C: 5 slot terisi, tekanan 60,0 bar. Rata-rata gabungan akan
    // menjadi 48,57 — bukan 20,0 dan bukan 60,0, sehingga pencampuran
    // ketahuan.
    laporanBoilerRoomComponentRecord($stationC, '2026-03-06',
        laporanBoilerRoomComponentPressureRows(5, 60.0),
        ['boiler_room_id' => 'BLR-LINE-C']);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanBoilerRoom::class)
        ->set('productionLineId', $this->lineA);

    // ARAH PERTAMA — line A: angkanya milik A, dan BUKAN A+C.
    $component->assertViewHas('summary', fn ($summary) => $summary['coverage']['filled_slots'] === 2
        && $summary['metrics']['steam_pressure_bar']['avg'] === 20.0
        && $summary['metrics']['steam_pressure_bar']['reading_count'] === 2);

    // ARAH KEDUA — line C: angkanya berpindah seluruhnya ke C. Tanpa arah ini
    // sebuah filter yang menyaring habis juga akan hijau.
    $component->set('productionLineId', $lineC)
        ->assertViewHas('summary', fn ($summary) => $summary['coverage']['filled_slots'] === 5
            && $summary['metrics']['steam_pressure_bar']['avg'] === 60.0
            && $summary['metrics']['steam_pressure_bar']['reading_count'] === 5);
});

it('production line: line mill lain diabaikan, lewat properti maupun lewat query string', function () {
    // Line A: 2 slot terisi, tekanan 20,0 bar.
    laporanBoilerRoomComponentRecord($this->stationA, '2026-03-05',
        laporanBoilerRoomComponentPressureRows(2, 20.0),
        ['boiler_room_id' => 'BLR-LINE-A']);

    // (a) Lewat properti — dibuang saat render, jatuh ke "belum memilih".
    Livewire::actingAs($this->supervisor)
        ->test(LaporanBoilerRoom::class)
        ->set('productionLineId', $this->lineB)
        ->assertSet('productionLineId', '')
        ->assertViewHas('needsProductionLineSelection', true)
        ->assertViewHas('summary', null);

    // (b) Lewat query string — sama saja.
    Livewire::actingAs($this->supervisor)
        ->withQueryParams(['production_line_id' => $this->lineB])
        ->test(LaporanBoilerRoom::class)
        ->assertSet('productionLineId', '')
        ->assertViewHas('summary', null);

    // (c) SISI POSITIFNYA, dan inilah yang menjaga `as: 'production_line_id'`:
    // line yang sah dari query string BENAR-BENAR terhidrasi dan langsung
    // memuat laporannya. Tanpa `as:`, Livewire memakai nama properti
    // ('productionLineId') sebagai kunci query, keduanya tidak bertemu, dan
    // asersi (a)/(b) di atas tetap hijau tanpa menandai apa pun.
    Livewire::actingAs($this->supervisor)
        ->withQueryParams(['production_line_id' => $this->lineA])
        ->test(LaporanBoilerRoom::class)
        ->assertSet('productionLineId', $this->lineA)
        ->assertViewHas('needsProductionLineSelection', false)
        ->assertViewHas('summary', fn ($summary) => $summary !== null && $summary['coverage']['filled_slots'] === 2
        && $summary['metrics']['steam_pressure_bar']['avg'] === 20.0
        && $summary['metrics']['steam_pressure_bar']['reading_count'] === 2);
});

it('production line: ekspor CSV hanya memuat baris line terpilih', function () {
    // Line A: 2 slot terisi, tekanan 20,0 bar.
    laporanBoilerRoomComponentRecord($this->stationA, '2026-03-05',
        laporanBoilerRoomComponentPressureRows(2, 20.0),
        ['boiler_room_id' => 'BLR-LINE-A']);

    $stationC = laporanBoilerRoomSecondLine($this->businessUnitA);
    $lineC = (string) $stationC->production_line_id;
    // Line C: 5 slot terisi, tekanan 60,0 bar. Rata-rata gabungan akan
    // menjadi 48,57 — bukan 20,0 dan bukan 60,0, sehingga pencampuran
    // ketahuan.
    laporanBoilerRoomComponentRecord($stationC, '2026-03-06',
        laporanBoilerRoomComponentPressureRows(5, 60.0),
        ['boiler_room_id' => 'BLR-LINE-C']);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanBoilerRoom::class)
        ->set('productionLineId', $this->lineA);

    $component->call('export', 'csv')->assertFileDownloaded(null, null, 'text/csv');

    $csv = laporanBoilerRoomDownloadedCsv($component);

    expect($csv)->toContain('BLR-LINE-A');
    expect($csv)->not->toContain('BLR-LINE-C');

    // Arah sebaliknya, berkas yang sama sekali berbeda isinya.
    $component->set('productionLineId', $lineC)->call('export', 'csv');

    $csvC = laporanBoilerRoomDownloadedCsv($component);

    expect($csvC)->toContain('BLR-LINE-C');
    expect($csvC)->not->toContain('BLR-LINE-A');
});

it('production line: tanpa line terpilih tidak ada berkas yang diunduh sama sekali', function () {
    // Line A: 2 slot terisi, tekanan 20,0 bar.
    laporanBoilerRoomComponentRecord($this->stationA, '2026-03-05',
        laporanBoilerRoomComponentPressureRows(2, 20.0),
        ['boiler_room_id' => 'BLR-LINE-A']);

    Livewire::actingAs($this->supervisor)
        ->test(LaporanBoilerRoom::class)
        ->call('export', 'csv')
        ->assertNoFileDownloaded();
});

it('production line: record yang stasiunnya sudah dipindah tetap terhitung di line asalnya', function () {
    // Line A: 2 slot terisi, tekanan 20,0 bar.
    laporanBoilerRoomComponentRecord($this->stationA, '2026-03-05',
        laporanBoilerRoomComponentPressureRows(2, 20.0),
        ['boiler_room_id' => 'BLR-LINE-A']);

    // Stasiunnya dipindah ke line lain DI MILL YANG SAMA — perubahan
    // konfigurasi yang sah, bukan perbaikan data.
    $lineBaru = ProductionLine::factory()->create([
        'business_unit_id' => $this->businessUnitA->id,
        'name' => 'Line Baru',
    ]);

    $this->stationA->update(['production_line_id' => $lineBaru->id]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanBoilerRoom::class);

    // DI LINE ASALNYA: masih terhitung utuh. Hanya mungkin karena filternya
    // membaca kolom `production_line_id` DI TABEL RECORD — sebuah join ke
    // `stations` akan memindahkan angka ini ke Line Baru dan menulis ulang
    // sejarah periode yang sudah lewat.
    $component->set('productionLineId', $this->lineA)
        ->assertViewHas('summary', fn ($summary) => $summary['coverage']['filled_slots'] === 2
        && $summary['metrics']['steam_pressure_bar']['avg'] === 20.0
        && $summary['metrics']['steam_pressure_bar']['reading_count'] === 2);

    // DI LINE BARUNYA: tidak ada apa pun. Stasiunnya memang ada di sana
    // sekarang, tetapi tidak satu pun record dihasilkan di sana.
    $component->set('productionLineId', (string) $lineBaru->id)
        ->assertViewHas('summary', fn ($summary) => $summary['coverage']['filled_slots'] === 0 && $summary['has_data'] === false);
});
