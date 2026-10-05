<?php

/**
 * LaporanWeighbridgeTest (Feature/Livewire) — screen-143--laporan-weighbridge-web /
 * usecase-146--laporan-weighbridge-web (Laporan Periode Weighbridge).
 *
 * Component tests for App\Livewire\Dashboard\LaporanWeighbridge — ONE TEST PER
 * test_scenarios ENTRY (all 35), each driving that scenario's
 * `component_test` action and asserting its `assert`. Mirrors
 * tests/Feature/Livewire/LaporanStorageTankTest.php (screen-133).
 *
 * COMPONENT SHAPE (deliberately minimal, and asserted as such in scenario 31):
 * properties `businessUnitId`, `productionLineId`, `periodId`,
 * `dailyRecapOpen`; methods mount(), updatedBusinessUnitId(),
 * toggleDailyRecap(), exportCsv($format), render(). Nothing that reads like a
 * write action — a read-only report must not offer one.
 *
 * ACCESS CONTROL is closed twice over: the route carries
 * 'role:supervisor,mill_management,admin' (EnsureRole -> abort 403 before the
 * component ever mounts), and mount() itself refuses an Operator. Scenario 20
 * asserts BOTH, because each guard covers a path the other does not. There is
 * no Operator widening here at all — the mobile Weighbridge report
 * (screen-144) is not built yet.
 *
 * =====================================================================
 * THE FOUR THINGS THIS SCREEN MUST NEVER LET A READER MISREAD
 * =====================================================================
 *  1. THE TWO FLOWS ARE NEVER SUMMED. Arus masuk and arus keluar get their
 *     own cards, their own breakdown table and their own hourly chart, and
 *     nowhere on the page is there a number that adds them. Scenario 23
 *     asserts that STRUCTURALLY — over the rendered testid set and the daily
 *     table's column structure — because "there is no such number" cannot be
 *     proven by looking for a number.
 *
 *  2. THERE IS NO VEHICLE-IN-PLANT DURATION ANYWHERE, and the absence is
 *     deliberate rather than an omission.
 *
 *     SCENARIO 29 NEVER ASSERTS assertDontSee('durasi'). THE BLADE SAYS THE
 *     WORD ON PURPOSE: data-testid="no-duration-note" renders the prose
 *     "Lama kendaraan di pabrik TIDAK dilaporkan di layar ini, dan
 *     ketiadaannya disengaja", which is a FEATURE — the reader is told why the
 *     metric is missing instead of being left to wonder. A naive text grep
 *     fails on the explanation itself and would force someone to delete the
 *     explanation to make the test pass. So the absence is asserted
 *     STRUCTURALLY: zero duration-named keys in the payload, zero
 *     duration-named <th>, zero duration KPI card, and exactly ONE timestamp
 *     column in the exported CSV.
 *
 *  3. EVERY AVERAGE RENDERS ITS OWN DENOMINATOR BESIDE IT. The net-weight
 *     average divides by the trips whose weight is actually filled, and that
 *     count is printed next to it (receive-avg-denominator /
 *     dispatch-avg-denominator), because the trip count is deliberately
 *     larger.
 *
 *  4. A NULL FIGURE RENDERS AS "tidak tersedia", NEVER AS 0. Three different
 *     claims the screen must never substitute for one another:
 *       "tidak tersedia"  nothing was ever weighed
 *       "0"               measured, and the answer was zero
 *       an em dash "—"    this date has no trip on that flow at all
 *
 * ---------------------------------------------------------------------
 * NUMBER FORMATTING IS PART OF THE CONTRACT
 * ---------------------------------------------------------------------
 * Weights render with Indonesian separators and two decimals (1.000,00 kg);
 * counts render with no decimals (1.000); hours render as wall-clock
 * ("07.00") rather than as a bare integer, so an hour can never be read as a
 * quantity. WEIGHTS ARE RAW KILOGRAMS — the mock's "ton" is not the repo
 * convention, and converting on one screen only would make two screens quote
 * different numbers for the same row.
 *
 * ---------------------------------------------------------------------
 * HOW A NULL net_weight IS SEEDED — READ THIS BEFORE ADDING A FIXTURE
 * ---------------------------------------------------------------------
 * WeighbridgeRecord::booted() recomputes net_weight = gross - tare on EVERY
 * save when both are non-null, so `->create(['net_weight' => null])` is
 * SILENTLY OVERWRITTEN. gross_weight is NOT NULL in the schema, so nulling
 * gross and tare together is refused by the database. The one shape that
 * works is the shape the domain produces — GROSS TAKEN, TARE NOT YET.
 */

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Livewire\Dashboard\LaporanWeighbridge;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use App\Models\WeighbridgeRecord;
use App\Services\WeighbridgeReportService;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * One weighbridge_records row. `record_datetime` is POSITIONAL because it
 * decides period membership; pass null for an UNDATED trip. `net_weight` is
 * the one attribute interpreted rather than passed through — see the header.
 */
function laporanWeighbridgeComponentTrip(Station $station, ?string $recordDatetime, array $attributes = []): WeighbridgeRecord
{
    $net = array_key_exists('net_weight', $attributes) ? $attributes['net_weight'] : 1000.0;
    unset($attributes['net_weight']);

    $weights = $net === null
        ? ['gross_weight' => 12000.0, 'tare_weight' => null, 'net_weight' => null]
        : ['gross_weight' => (float) $net + 2000.0, 'tare_weight' => 2000.0, 'net_weight' => (float) $net];

    return WeighbridgeRecord::factory()->forStation($station)->create(array_merge([
        'record_datetime' => $recordDatetime,
        'weighbridge_type' => WeighbridgeReportService::FLOW_RECEIVE,
        'estate_supplier' => 'Estate A',
        'destination' => null,
        'status' => RecordStatus::Saved,
    ], $weights, $attributes));
}

/** An ARUS MASUK trip. */
function laporanWeighbridgeComponentReceive(
    Station $station,
    ?string $recordDatetime,
    float|int|null $netWeight = 1000.0,
    string $origin = 'Estate A',
    array $attributes = [],
): WeighbridgeRecord {
    return laporanWeighbridgeComponentTrip($station, $recordDatetime, array_merge([
        'weighbridge_type' => WeighbridgeReportService::FLOW_RECEIVE,
        'estate_supplier' => $origin,
        'destination' => null,
        'net_weight' => $netWeight,
    ], $attributes));
}

/** An ARUS KELUAR trip. */
function laporanWeighbridgeComponentDispatch(
    Station $station,
    ?string $recordDatetime,
    float|int|null $netWeight = 1000.0,
    ?string $destination = 'Refinery X',
    array $attributes = [],
): WeighbridgeRecord {
    return laporanWeighbridgeComponentTrip($station, $recordDatetime, array_merge([
        'weighbridge_type' => WeighbridgeReportService::FLOW_DISPATCH,
        'estate_supplier' => 'Estate A',
        'destination' => $destination,
        'net_weight' => $netWeight,
    ], $attributes));
}

/** The text content of the element carrying $testid, or null. */
function laporanWeighbridgeRendered(string $html, string $testid): ?string
{
    if (preg_match('/data-testid="'.preg_quote($testid, '/').'"[^>]*>(.*?)</s', $html, $matches)) {
        return trim(html_entity_decode($matches[1]));
    }

    return null;
}

/**
 * One whole `<section data-testid="...">...</section>` of the rendered page,
 * bounded by the element's OWN closing tag — so "nowhere on this card" can be
 * asserted over the card in full. The report's cards never nest a `<section>`,
 * which is what makes the lazy match exact.
 */
function laporanWeighbridgeSection(string $html, string $testid): string
{
    if (preg_match('/<section[^>]*data-testid="'.preg_quote($testid, '/').'".*?<\/section>/s', $html, $matches)) {
        return $matches[0];
    }

    return '';
}

/** One `<tr>` of a recap table, by its data-testid. */
function laporanWeighbridgeRow(string $html, string $testid): string
{
    if (preg_match('/<tr data-testid="'.preg_quote($testid, '/').'".*?<\/tr>/s', $html, $matches)) {
        return $matches[0];
    }

    return '';
}

/** Every data-testid actually rendered on the page. */
function laporanWeighbridgeTestIds(string $html): array
{
    preg_match_all('/data-testid="([^"]*)"/', $html, $matches);

    return array_values(array_unique($matches[1]));
}

/** The text of every `<th>` on the page, decoded and trimmed. */
function laporanWeighbridgeTableHeadings(string $html): array
{
    preg_match_all('/<th[^>]*>(.*?)<\/th>/s', $html, $matches);

    return array_map(
        fn ($cell) => trim(html_entity_decode(strip_tags($cell))),
        $matches[1],
    );
}

/** Every key name appearing ANYWHERE in a nested array, flattened. */
function laporanWeighbridgeComponentAllKeys(array $payload): array
{
    $keys = [];

    foreach ($payload as $key => $value) {
        if (is_string($key)) {
            $keys[] = $key;
        }

        if (is_array($value)) {
            $keys = array_merge($keys, laporanWeighbridgeComponentAllKeys($value));
        }
    }

    return array_values(array_unique($keys));
}

/** The contents of the CSV the screen actually downloaded. */
function laporanWeighbridgeDownloadedCsv(Testable $component): string
{
    return base64_decode((string) data_get($component->effects, 'download.content'));
}

/** A downloaded CSV body split into non-empty lines. */
function laporanWeighbridgeDownloadedLines(string $body): array
{
    return array_values(array_filter(explode("\n", trim($body))));
}

/** Every numeric block the report renders — used by the "no figures" cases. */
const LAPORAN_WEIGHBRIDGE_FIGURE_TESTIDS = [
    'no-sum-note', 'flow-receive', 'flow-dispatch',
    'kpi-receive-trip-count', 'kpi-receive-net-weight-total', 'kpi-receive-net-weight-avg',
    'kpi-dispatch-trip-count', 'kpi-dispatch-net-weight-total', 'kpi-dispatch-net-weight-avg',
    'completeness-card', 'incomplete-card', 'incomplete-table',
    'hourly-kpis', 'hourly-distribution', 'hourly-chart-receive', 'hourly-chart-dispatch',
    'by-origin-card', 'by-origin-table', 'by-destination-card', 'by-destination-table',
    'daily-trend', 'daily-recap-card', 'daily-table',
    'export-button', 'export-excel-button',
];

/** Controls a read-only report must never expose. */
const LAPORAN_WEIGHBRIDGE_WRITE_TESTIDS = [
    'save-button', 'create-button', 'edit-button', 'delete-button',
    'add-row-button', 'remove-row-button', 'submit-button', 'verify-button',
];

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->weighbridge()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->weighbridge()->create();

    // Exactly ONE production line per mill at setup. Choosing a line is
    // MANDATORY on this screen, so nearly every scenario sets it explicitly;
    // the ones that need a second line create it themselves.
    $this->lineA = (string) $this->stationA->production_line_id;
    $this->lineB = (string) $this->stationB->production_line_id;

    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('weighbridge')
        ->range('2026-09-01', '2026-09-30')
        ->named('Periode September Alpha')
        ->open()
        ->create();
});

// =====================================================================
// Scenario 1: "sukses sebagai Supervisor atau Mill Management"
// =====================================================================
it('skenario 1 — Supervisor / Mill Management: tanpa pemilih Mill, kedua kelompok arus dan seluruh blok tampil, ekspor aktif', function () {
    laporanWeighbridgeComponentReceive($this->stationA, '2026-09-02 07:10', 10000.0, 'Estate A');
    laporanWeighbridgeComponentReceive($this->stationA, '2026-09-02 07:40', 5000.0, 'Supplier B');
    laporanWeighbridgeComponentReceive($this->stationA, '2026-09-03 09:00', null, 'Estate A');
    laporanWeighbridgeComponentDispatch($this->stationA, '2026-09-04 20:00', 20000.0, 'Refinery X');
    laporanWeighbridgeComponentDispatch($this->stationA, '2026-09-05 21:00', 4000.0, null);
    laporanWeighbridgeComponentReceive($this->stationA, null, 9999.0);

    foreach ([$this->supervisor, $this->millManagement] as $user) {
        $component = Livewire::actingAs($user)
            ->test(LaporanWeighbridge::class)
            ->set('productionLineId', $this->lineA)
            // The newest period auto-selects, so the page is useful on first
            // paint; the LINE deliberately does not.
            ->assertSet('periodId', (string) $this->periodA->id)
            // Offering a picker a bound role cannot use would be a lie.
            ->assertDontSeeHtml('data-testid="mill-select"')
            ->assertSeeHtml('data-testid="mill-name"')
            ->assertSee('Mill Alpha')
            ->assertSeeHtml('data-testid="production-line-select"')
            ->assertSeeHtml('data-testid="production-line-current"')
            ->assertSeeHtml('data-testid="period-select"')
            ->assertSeeHtml('data-testid="no-sum-note"')
            // Both flow groups, each with its own three cards.
            ->assertSeeHtml('data-testid="flow-receive"')
            ->assertSeeHtml('data-testid="flow-dispatch"')
            ->assertSeeHtml('data-testid="kpi-receive-trip-count"')
            ->assertSeeHtml('data-testid="kpi-receive-net-weight-total"')
            ->assertSeeHtml('data-testid="kpi-receive-net-weight-avg"')
            ->assertSeeHtml('data-testid="kpi-dispatch-trip-count"')
            ->assertSeeHtml('data-testid="kpi-dispatch-net-weight-total"')
            ->assertSeeHtml('data-testid="kpi-dispatch-net-weight-avg"')
            // The two breakdown tables.
            ->assertSeeHtml('data-testid="by-origin-table"')
            ->assertSeeHtml('data-testid="by-destination-table"')
            // 24-hour distribution per flow, plus busiest hour and empty hours.
            ->assertSeeHtml('data-testid="hourly-chart-receive"')
            ->assertSeeHtml('data-testid="hourly-chart-dispatch"')
            ->assertSeeHtml('data-testid="kpi-busiest-hour-receive"')
            ->assertSeeHtml('data-testid="kpi-busiest-hour-dispatch"')
            ->assertSeeHtml('data-testid="kpi-empty-hours-receive"')
            ->assertSeeHtml('data-testid="kpi-empty-hours-dispatch"')
            // Completeness: missing weight per flow, draft and undated counts.
            ->assertSeeHtml('data-testid="missing-net-weight-receive"')
            ->assertSeeHtml('data-testid="missing-net-weight-dispatch"')
            ->assertSeeHtml('data-testid="draft-trip-count"')
            ->assertSeeHtml('data-testid="undated-trip-count"')
            ->assertSeeHtml('data-testid="completeness-card"')
            // Daily recap, trend and the export action.
            ->assertSeeHtml('data-testid="daily-table"')
            ->assertSeeHtml('data-testid="daily-trend"')
            ->assertSeeHtml('data-testid="export-button"')
            ->assertDontSee('Mill Beta')
            ->assertDontSeeHtml('data-testid="empty-state"');

        $html = $component->html();

        // The average's DENOMINATOR is printed beside it, and it is the
        // filled-weight count rather than the trip count.
        expect(laporanWeighbridgeRendered($html, 'receive-avg-denominator'))->toBe('2');
        expect(laporanWeighbridgeRendered($html, 'receive-net-weight-trip-count'))->toBe('2');
        expect(laporanWeighbridgeRendered($html, 'undated-trip-count'))->toBe('1 trip');

        // No write-flavoured control anywhere.
        foreach (LAPORAN_WEIGHBRIDGE_WRITE_TESTIDS as $writeish) {
            expect($html)->not->toContain('data-testid="'.$writeish.'"');
        }

        $component->assertViewHas('summary', fn ($summary) => $summary['receive']['trip_count'] === 3
            && $summary['receive']['net_weight_total'] === 15000.0
            && $summary['receive']['net_weight_trip_count'] === 2
            && $summary['dispatch']['trip_count'] === 2
            && $summary['undated_trip_count'] === 1);
    }
});

// =====================================================================
// Scenario 2: "sukses sebagai Admin"
// =====================================================================
it('skenario 2 — Admin: pemilih Mill dirender lebih dulu, line dan periode baru terisi setelah mill dipilih, isi layar identik', function () {
    laporanWeighbridgeComponentReceive($this->stationA, '2026-09-10 08:00', 1000.0, 'Estate Alpha');
    laporanWeighbridgeComponentReceive($this->stationB, '2026-09-10 08:00', 99999.0, 'Estate Beta');

    // Before a mill is chosen: the picker is there, the other two lists are not.
    $component = Livewire::actingAs($this->admin)
        ->test(LaporanWeighbridge::class)
        ->assertSeeHtml('data-testid="mill-select"')
        ->assertViewHas('needsMillSelection', true)
        ->assertViewHas('productionLineOptions', [])
        ->assertViewHas('periods', [])
        ->assertViewHas('summary', null);

    // After the mill: the line options populate, and the period picker with them.
    $component->set('businessUnitId', (string) $this->businessUnitA->id)
        ->assertViewHas('needsMillSelection', false)
        ->assertSeeHtml('data-testid="production-line-select"')
        ->assertViewHas('productionLineOptions', fn ($options) => array_column($options, 'id') === [$this->lineA])
        ->assertViewHas('periods', fn ($periods) => array_column($periods, 'id') === [(string) $this->periodA->id]);

    $component->set('productionLineId', $this->lineA)
        ->assertSet('periodId', (string) $this->periodA->id)
        // The same sections as a mill-bound user.
        ->assertSeeHtml('data-testid="flow-receive"')
        ->assertSeeHtml('data-testid="flow-dispatch"')
        ->assertSeeHtml('data-testid="by-origin-table"')
        ->assertSeeHtml('data-testid="by-destination-table"')
        ->assertSeeHtml('data-testid="daily-table"')
        ->assertSeeHtml('data-testid="export-button"')
        ->assertSeeHtml('data-testid="export-excel-button"')
        // Every figure is scoped to the chosen mill and line.
        ->assertSee('Estate Alpha')
        ->assertDontSee('Estate Beta')
        ->assertViewHas('summary', fn ($summary) => $summary['business_unit']['name'] === 'Mill Alpha'
            && $summary['receive']['net_weight_total'] === 1000.0
            && $summary['production_line']['id'] === $this->lineA);
});

// =====================================================================
// Scenario 3: "Production Line belum dipilih"
// =====================================================================
it('skenario 3 — tanpa Production Line terpilih: nol blok angka, hanya ajakan memilih, dan tidak ada angka seluruh mill sebagai pengganti', function () {
    laporanWeighbridgeComponentReceive($this->stationA, '2026-09-04 08:00', 12345.0);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanWeighbridge::class)
        ->assertSet('productionLineId', '')
        ->assertViewHas('needsProductionLineSelection', true)
        ->assertViewHas('summary', null)
        ->assertSeeHtml('data-testid="production-line-select"')
        ->assertSeeHtml('data-testid="select-production-line-hint"')
        ->assertSee('Pilih production line terlebih dahulu')
        // NO "all lines" option — a deliberate difference from the Data
        // Browser, because a report produces AGGREGATES and a total mixing a
        // dozen lines is not a number anyone can act on.
        ->assertDontSee('Semua Line')
        ->assertDontSee('Semua Production Line');

    $html = $component->html();

    foreach (LAPORAN_WEIGHBRIDGE_FIGURE_TESTIDS as $testid) {
        expect($html)->not->toContain('data-testid="'.$testid.'"');
    }

    // Not one number from the line is on the page.
    expect($html)->not->toContain('12.345');
});

// =====================================================================
// Scenario 4: "Admin mengganti mill setelah memilih line"
// =====================================================================
it('skenario 4 — Admin berganti mill: production_line_id direset, daftar line dimuat ulang, dan tak satu angka mill lama tersisa', function () {
    laporanWeighbridgeComponentReceive($this->stationA, '2026-09-04 08:00', 12345.0, 'Estate Alpha');

    Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('weighbridge')
        ->range('2026-09-01', '2026-09-30')->named('Periode Beta')->open()->create();

    $component = Livewire::actingAs($this->admin)
        ->test(LaporanWeighbridge::class)
        ->set('businessUnitId', (string) $this->businessUnitA->id)
        ->set('productionLineId', $this->lineA)
        ->assertSeeHtml('data-testid="flow-receive"')
        ->assertSee('Estate Alpha');

    // Switch the mill. keepProductionLineValid() drops a line that is not in
    // the new mill's option list — one mechanism, not two, and no updated*
    // hook of its own.
    $component->set('businessUnitId', (string) $this->businessUnitB->id)
        ->assertSet('productionLineId', '')
        // updatedBusinessUnitId() drops the period too: a period belongs to
        // exactly one mill.
        ->assertSet('periodId', (string) Period::query()->where('business_unit_id', $this->businessUnitB->id)->value('id'))
        ->assertViewHas('needsProductionLineSelection', true)
        ->assertViewHas('summary', null)
        ->assertViewHas('productionLineOptions', fn ($options) => array_column($options, 'id') === [$this->lineB])
        ->assertSeeHtml('data-testid="select-production-line-hint"')
        ->assertDontSee('Estate Alpha');

    $html = $component->html();

    foreach (LAPORAN_WEIGHBRIDGE_FIGURE_TESTIDS as $testid) {
        expect($html)->not->toContain('data-testid="'.$testid.'"');
    }

    expect($html)->not->toContain('12.345');
});

// =====================================================================
// Scenario 5: "mill hanya punya satu Production Line"
// =====================================================================
it('skenario 5 — mill berline-tunggal: satu-satunya opsi TIDAK dipilih otomatis, dan laporan baru tampil setelah pembaca memilihnya', function () {
    laporanWeighbridgeComponentReceive($this->stationA, '2026-09-04 08:00', 1000.0);

    $lineName = (string) ProductionLine::findOrFail($this->lineA)->name;

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanWeighbridge::class)
        ->assertViewHas('productionLineOptions', fn ($options) => count($options) === 1)
        // Choosing is the reader's decision, and guessing it produces figures
        // they did not ask for.
        ->assertSet('productionLineId', '')
        ->assertViewHas('summary', null)
        ->assertSeeHtml('data-testid="select-production-line-hint"')
        ->assertDontSeeHtml('data-testid="production-line-current"');

    // After the single option IS picked, the report names that line, so the
    // reader always knows which line the numbers belong to.
    $component->set('productionLineId', $this->lineA)
        ->assertSeeHtml('data-testid="production-line-current"')
        ->assertSeeHtml('data-testid="hero-production-line"')
        ->assertSee($lineName)
        ->assertViewHas('selectedProductionLine', fn ($line) => $line['id'] === $this->lineA)
        ->assertSeeHtml('data-testid="flow-receive"');
});

// =====================================================================
// Scenario 6: "Admin memilih mill lebih dulu"
// =====================================================================
it('skenario 6 — Admin belum memilih mill: pemilih terisi, ajakan memilih tampil, dan daftar line serta periode masih kosong', function () {
    $component = Livewire::actingAs($this->admin)
        ->test(LaporanWeighbridge::class)
        ->assertSeeHtml('data-testid="mill-select"')
        ->assertSeeHtml('data-testid="mill-select-hint"')
        ->assertSee('Pilih mill terlebih dahulu')
        ->assertViewHas('needsMillSelection', true)
        ->assertViewHas('summary', null)
        // NEITHER request has been fired: the two lists are still empty.
        ->assertViewHas('productionLineOptions', [])
        ->assertViewHas('periods', [])
        ->assertDontSeeHtml('data-testid="production-line-select"')
        ->assertDontSeeHtml('data-testid="period-select"');

    // And the mill picker IS populated — it is the one control that works.
    $component->assertViewHas('businessUnitOptions', function ($options) {
        $names = array_column($options, 'name');

        return in_array('Mill Alpha', $names, true) && in_array('Mill Beta', $names, true);
    });

    $html = $component->html();

    foreach (LAPORAN_WEIGHBRIDGE_FIGURE_TESTIDS as $testid) {
        expect($html)->not->toContain('data-testid="'.$testid.'"');
    }
});

// =====================================================================
// Scenario 7: "mill belum punya periode pelaporan"
// =====================================================================
it('skenario 7 — mill tanpa periode yang mencakup Weighbridge: pemilih periode tanpa satu opsi pun, arahan menghubungi Admin, tanpa angka', function () {
    PeriodStation::query()->where('period_id', $this->periodA->id)->delete();
    $this->periodA->delete();

    // The mill is fine and has a line; it is the PERIODS that are missing.
    Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('boiler-room')
        ->range('2026-10-01', '2026-10-31')->named('Periode Boiler Saja')->open()->create();

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanWeighbridge::class)
        ->set('productionLineId', $this->lineA)
        ->assertViewHas('periods', [])
        ->assertSet('periodId', '')
        ->assertSeeHtml('data-testid="period-select"')
        ->assertSeeHtml('data-testid="no-period-hint"')
        ->assertSee('Belum ada Periode Pelaporan')
        // The contact-Admin guidance names the screen that creates periods.
        // Asserted on "Kelola Periode Pelaporan" rather than on "Hubungi
        // Admin": the blade wraps that phrase across a source line break, so
        // the rendered HTML carries a newline between the two words and a
        // literal two-word match would fail on the formatting rather than on
        // the behaviour.
        ->assertSee('Kelola Periode Pelaporan')
        ->assertViewHas('summary', null);

    $html = $component->html();

    // The picker renders with NO <option> at all — not with a pseudo-option.
    expect(preg_match('/data-testid="period-select"[^>]*>(.*?)<\/select>/s', $html, $m))->toBe(1);
    expect(trim(strip_tags($m[1])))->toBe('');

    foreach (LAPORAN_WEIGHBRIDGE_FIGURE_TESTIDS as $testid) {
        expect($html)->not->toContain('data-testid="'.$testid.'"');
    }
});

// =====================================================================
// Scenario 8: "periode tanpa data"
// =====================================================================
it('skenario 8 — periode tanpa data: kartu berbunyi tidak tersedia (bukan 0), pernyataan belum ada data, dan tidak ada grafik kosong', function () {
    $empty = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('weighbridge')
        ->range('2026-11-01', '2026-11-30')->named('Periode Tanpa Data')->open()->create();

    laporanWeighbridgeComponentReceive($this->stationA, '2026-09-04 08:00', 9999.0);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanWeighbridge::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $empty->id)
        ->assertSeeHtml('data-testid="empty-state"')
        ->assertSee('Belum ada data pada periode dan line ini');

    $html = $component->html();

    // Every weight figure reads "tidak tersedia" rather than 0 — zero would
    // claim a measured total of nothing.
    foreach (['kpi-receive-net-weight-total', 'kpi-receive-net-weight-avg',
        'kpi-dispatch-net-weight-total', 'kpi-dispatch-net-weight-avg'] as $card) {
        expect(laporanWeighbridgeSection($html, 'flow-receive').laporanWeighbridgeSection($html, 'flow-dispatch'))
            ->toContain('data-testid="'.$card.'"');
    }

    expect(laporanWeighbridgeSection($html, 'flow-receive'))->toContain('tidak tersedia');
    expect(laporanWeighbridgeSection($html, 'flow-dispatch'))->toContain('tidak tersedia');

    // No empty chart is drawn — a chart of zeroes would read as measured zero
    // activity.
    expect($html)->not->toContain('data-testid="hourly-distribution"');
    expect($html)->not->toContain('data-testid="hourly-chart-receive"');
    expect($html)->not->toContain('data-testid="daily-trend-chart"');
    expect($html)->not->toContain('data-testid="by-origin-table"');
    expect($html)->not->toContain('data-testid="daily-table"');

    $component->assertViewHas('summary', fn ($summary) => $summary['receive']['trip_count'] === 0
        && $summary['receive']['net_weight_total'] === null
        && $summary['dispatch']['net_weight_avg'] === null
        && $summary['completeness']['days_with_trip'] === 0);
});

// =====================================================================
// Scenario 9: "trip tersinkron terlambat dari mobile"
// =====================================================================
it('skenario 9 — trip tersinkron terlambat ikut pada periode tempat penimbangan terjadi, dan periode berjalan tidak terpengaruh', function () {
    $current = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('weighbridge')
        ->range('2026-10-01', '2026-10-31')->named('Periode Oktober Berjalan')->open()->create();

    $late = laporanWeighbridgeComponentReceive($this->stationA, '2026-09-20 08:00', 7000.0, 'Tersinkron Terlambat');
    $late->forceFill(['created_at' => '2026-10-15 02:00:00', 'updated_at' => '2026-10-15 02:00:00'])->saveQuietly();

    laporanWeighbridgeComponentReceive($this->stationA, '2026-10-05 08:00', 1000.0, 'Estate Oktober');

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanWeighbridge::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $this->periodA->id);

    // The ended period counts it, and its date appears in that period's recap.
    $component->assertViewHas('summary', fn ($summary) => $summary['receive']['trip_count'] === 1
        && $summary['receive']['net_weight_total'] === 7000.0
        && array_column($summary['daily'], 'date') === ['2026-09-20']);
    $component->assertSeeHtml('data-testid="daily-row-2026-09-20"');
    $component->assertSee('Tersinkron Terlambat');

    // The current period is unaffected by it.
    $component->set('periodId', (string) $current->id)
        ->assertViewHas('summary', fn ($summary) => $summary['receive']['trip_count'] === 1
            && $summary['receive']['net_weight_total'] === 1000.0
            && array_column($summary['daily'], 'date') === ['2026-10-05'])
        ->assertSeeHtml('data-testid="daily-row-2026-10-05"')
        ->assertDontSeeHtml('data-testid="daily-row-2026-09-20"')
        ->assertDontSee('Tersinkron Terlambat');
});

// =====================================================================
// Scenario 10: "trip tanpa penanda waktu penimbangan"
// =====================================================================
it('skenario 10 — trip tanpa penanda waktu dirender sebagai angkanya sendiri di blok kelengkapan dan tidak muncul di angka mana pun', function () {
    foreach (['2026-09-02 08:00', '2026-09-03 08:00', '2026-09-04 08:00'] as $dt) {
        laporanWeighbridgeComponentReceive($this->stationA, $dt, 1000.0, 'Estate Bertanggal');
    }

    laporanWeighbridgeComponentReceive($this->stationA, null, 50000.0, 'Estate Tanpa Waktu');
    laporanWeighbridgeComponentDispatch($this->stationA, null, 60000.0, 'Tujuan Tanpa Waktu');

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanWeighbridge::class)
        ->set('productionLineId', $this->lineA)
        ->assertSeeHtml('data-testid="incomplete-row-undated"')
        ->assertSeeHtml('data-testid="undated-trip-count"');

    $html = $component->html();

    expect(laporanWeighbridgeRendered($html, 'undated-trip-count'))->toBe('2 trip');

    // Its own figure in the completeness block, and NOWHERE else: the
    // breakdowns, the counts and the daily recap all reflect only the dated
    // trips, so the reader can reconcile the difference against the Data
    // Browser.
    expect($html)->not->toContain('Estate Tanpa Waktu');
    expect($html)->not->toContain('Tujuan Tanpa Waktu');
    expect($html)->toContain('Estate Bertanggal');

    $component->assertViewHas('summary', fn ($summary) => $summary['undated_trip_count'] === 2
        && $summary['receive']['trip_count'] === 3
        && $summary['receive']['net_weight_total'] === 3000.0
        && $summary['dispatch']['trip_count'] === 0
        && count($summary['daily']) === 3);

    // And the row explains its effect: outside every figure.
    expect(laporanWeighbridgeRow($html, 'incomplete-row-undated'))->toContain('TIDAK ikut terhitung');
});

// =====================================================================
// Scenario 11: "periode hanya memuat satu jenis arus"
// =====================================================================
it('skenario 11 — arus keluar tanpa trip: kelompoknya dirender UTUH dengan nilai tidak tersedia, tidak disembunyikan maupun diciutkan', function () {
    foreach (range(1, 6) as $i) {
        laporanWeighbridgeComponentReceive($this->stationA, sprintf('2026-09-%02d 08:00', $i), 1000.0);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanWeighbridge::class)
        ->set('productionLineId', $this->lineA)
        // Rendered in full: an absent section reads as "this does not exist",
        // while a present section of unavailables reads as "there were none".
        ->assertSeeHtml('data-testid="flow-dispatch"')
        ->assertSeeHtml('data-testid="kpi-dispatch-trip-count"')
        ->assertSeeHtml('data-testid="kpi-dispatch-net-weight-total"')
        ->assertSeeHtml('data-testid="kpi-dispatch-net-weight-avg"')
        ->assertSeeHtml('data-testid="hourly-chart-dispatch"')
        ->assertSeeHtml('data-testid="by-destination-table"')
        ->assertSeeHtml('data-testid="by-destination-empty"');

    $html = $component->html();
    $dispatch = laporanWeighbridgeSection($html, 'flow-dispatch');

    expect($dispatch)->toContain('tidak tersedia');
    expect(laporanWeighbridgeRendered($html, 'kpi-empty-hours-dispatch'))->not->toBeNull();

    // The receive block shows its own figures, separately.
    expect(laporanWeighbridgeSection($html, 'flow-receive'))->toContain('6.000,00');

    $component->assertViewHas('summary', fn ($summary) => $summary['dispatch']['trip_count'] === 0
        && $summary['dispatch']['net_weight_total'] === null
        && $summary['dispatch']['empty_hour_count'] === 24
        && $summary['receive']['trip_count'] === 6);
});

// =====================================================================
// Scenario 12: "seluruh trip beratnya kosong"
// =====================================================================
it('skenario 12 — seluruh trip beratnya kosong: total dan rata-rata tidak tersedia (bukan 0), penyebut 0, jumlah trip apa adanya', function () {
    foreach (range(1, 6) as $i) {
        laporanWeighbridgeComponentReceive($this->stationA, sprintf('2026-09-%02d 08:00', $i), null);
    }

    foreach (range(1, 2) as $i) {
        laporanWeighbridgeComponentDispatch($this->stationA, sprintf('2026-09-%02d 14:00', $i), null);
    }

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanWeighbridge::class)
        ->set('productionLineId', $this->lineA)
        ->html();

    $receive = laporanWeighbridgeSection($html, 'flow-receive');

    // "tidak tersedia", NEVER 0 — the two are different claims.
    expect($receive)->toContain('tidak tersedia');
    expect(laporanWeighbridgeRendered($html, 'receive-net-weight-trip-count'))->toBe('0');
    expect(laporanWeighbridgeRendered($html, 'receive-avg-denominator'))->toBe('0');
    expect(laporanWeighbridgeRendered($html, 'receive-missing-net-weight-inline'))->toBe('6');
    expect(laporanWeighbridgeRendered($html, 'dispatch-missing-net-weight-inline'))->toBe('2');

    // The trip counts are the real numbers, and the per-flow missing count
    // equals that flow's trip count.
    expect(laporanWeighbridgeRendered($html, 'missing-net-weight-receive'))->toBe('6 trip');
    expect(laporanWeighbridgeRendered($html, 'missing-net-weight-dispatch'))->toBe('2 trip');
});

// =====================================================================
// Scenario 13: "penimbangan belum selesai pada sebagian trip"
// =====================================================================
it('skenario 13 — penimbangan belum selesai sebagian: rata-rata dari penyebutnya sendiri, penyebut dirender di sampingnya, dan jumlah per arus terpisah', function () {
    // receive: 5 trips, 3 weighed (1000 + 2000 + 3000) -> avg 2.000,00.
    foreach ([1000.0, 2000.0, 3000.0, null, null] as $i => $net) {
        laporanWeighbridgeComponentReceive($this->stationA, sprintf('2026-09-%02d 08:00', $i + 1), $net);
    }

    // dispatch: 3 trips, 2 weighed (4000 + 6000) -> avg 5.000,00.
    foreach ([4000.0, 6000.0, null] as $i => $net) {
        laporanWeighbridgeComponentDispatch($this->stationA, sprintf('2026-09-%02d 14:00', $i + 1), $net);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanWeighbridge::class)
        ->set('productionLineId', $this->lineA);

    $html = $component->html();

    // The denominator is rendered NEXT TO the average, and it is the
    // filled-weight count — not the trip count.
    expect(laporanWeighbridgeRendered($html, 'receive-avg-denominator'))->toBe('3');
    expect(laporanWeighbridgeRendered($html, 'dispatch-avg-denominator'))->toBe('2');
    expect(laporanWeighbridgeSection($html, 'flow-receive'))->toContain('2.000,00');
    expect(laporanWeighbridgeSection($html, 'flow-dispatch'))->toContain('5.000,00');

    // The unfinished trips do not pull the average down — 1.200,00 is what
    // dividing by 5 would print, and it is nowhere on the page.
    expect($html)->not->toContain('1.200,00');

    // The missing count is rendered separately for each flow.
    expect(laporanWeighbridgeRendered($html, 'missing-net-weight-receive'))->toBe('2 trip');
    expect(laporanWeighbridgeRendered($html, 'missing-net-weight-dispatch'))->toBe('1 trip');

    $component->assertViewHas('summary', fn ($summary) => $summary['receive']['net_weight_avg'] === 2000.0
        && $summary['receive']['net_weight_trip_count'] === 3
        && $summary['dispatch']['net_weight_avg'] === 5000.0);
});

// =====================================================================
// Scenario 14: "tujuan belum diisi pada sebagian trip arus keluar"
// =====================================================================
it('skenario 14 — tujuan belum diisi: baris kelompoknya sendiri berlabel Belum diisi, tidak dibuang, dan kolom Trip menjumlah tepat ke trip arus keluar', function () {
    foreach (range(1, 6) as $i) {
        laporanWeighbridgeComponentDispatch($this->stationA, sprintf('2026-09-%02d 14:00', $i), 3000.0, 'Refinery X');
    }

    foreach (range(1, 4) as $i) {
        laporanWeighbridgeComponentDispatch($this->stationA, sprintf('2026-09-%02d 15:00', $i + 10), 1000.0, null);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanWeighbridge::class)
        ->set('productionLineId', $this->lineA)
        // Its own row, labelled as not-yet-filled rather than invented.
        ->assertSeeHtml('data-testid="by-destination-row-null"')
        ->assertSee('Belum diisi');

    $html = $component->html();

    expect(laporanWeighbridgeRow($html, 'by-destination-row-null'))->toContain('Belum diisi');
    expect(laporanWeighbridgeRendered($html, 'no-destination-dispatch-count'))->toBe('4 trip');

    // The footer of the table carries the headline figure the column must sum
    // to — with the null row present it does, and without it it would not.
    $component->assertViewHas('summary', function ($summary) {
        $rows = $summary['dispatch']['by_destination'];

        return array_sum(array_column($rows, 'trip_count')) === $summary['dispatch']['trip_count']
            && $summary['dispatch']['trip_count'] === 10;
    });

    expect(laporanWeighbridgeRow($html, 'by-destination-row-total'))->toContain('10');
});

// =====================================================================
// Scenario 15: "seluruh trip terjadi pada jam yang sama"
// =====================================================================
it('skenario 15 — seluruh trip pada satu jam: 24 kolom tetap digambar, satu berisi semuanya, jam tersibuk menunjuk jam itu, jam tanpa trip 23', function () {
    foreach (range(1, 9) as $i) {
        laporanWeighbridgeComponentReceive($this->stationA, sprintf('2026-09-%02d 06:%02d', $i, $i * 5), 1000.0);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanWeighbridge::class)
        ->set('productionLineId', $this->lineA)
        // Still rendered, however uninformative it looks: a chart that
        // disappears when the data is lopsided teaches the reader nothing.
        ->assertSeeHtml('data-testid="hourly-chart-receive"');

    $html = $component->html();

    // All 24 columns are present, and the zero ones are DIMMED rather than
    // dropped — the chart must keep its shape between periods.
    foreach (range(0, 23) as $hour) {
        expect($html)->toContain('data-testid="hourly-col-receive-'.$hour.'"');
    }

    expect(laporanWeighbridgeRendered($html, 'kpi-busiest-hour-receive'))->not->toBeNull();
    expect(laporanWeighbridgeSection($html, 'hourly-distribution'))->toContain('data-testid="hourly-col-receive-6"');
    expect($html)->toContain('06.00');
    expect(laporanWeighbridgeSection($html, 'hourly-kpis'))->toContain('23');

    $component->assertViewHas('summary', fn ($summary) => $summary['receive']['busiest_hour'] === 6
        && $summary['receive']['busiest_hour_trip_count'] === 9
        && $summary['receive']['empty_hour_count'] === 23
        && count($summary['receive']['hourly']) === 24);
});

// =====================================================================
// Scenario 16: "seluruh trip berstatus draft"
// =====================================================================
it('skenario 16 — seluruh trip draft: tak satu angka pun disembunyikan dan jumlah draft sama dengan total trip kedua arus', function () {
    foreach (range(1, 5) as $i) {
        laporanWeighbridgeComponentReceive($this->stationA, sprintf('2026-09-%02d 08:00', $i), 1000.0, 'Estate A', [
            'status' => $i % 2 === 0 ? RecordStatus::DraftPaused : RecordStatus::DraftOngoing,
        ]);
    }

    foreach (range(1, 3) as $i) {
        laporanWeighbridgeComponentDispatch($this->stationA, sprintf('2026-09-%02d 14:00', $i), 2000.0, 'Refinery X', [
            'status' => RecordStatus::DraftOngoing,
        ]);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanWeighbridge::class)
        ->set('productionLineId', $this->lineA)
        ->assertSeeHtml('data-testid="flow-receive"')
        ->assertSeeHtml('data-testid="flow-dispatch"')
        ->assertSeeHtml('data-testid="by-origin-table"')
        ->assertSeeHtml('data-testid="by-destination-table"')
        ->assertSeeHtml('data-testid="daily-table"')
        ->assertSeeHtml('data-testid="incomplete-row-draft"');

    $html = $component->html();

    // The draft count equals the total trip count across BOTH flows — and the
    // row says, in words, that drafts are COUNTED rather than filtered.
    expect(laporanWeighbridgeRendered($html, 'draft-trip-count'))->toBe('8 trip');
    expect(laporanWeighbridgeRow($html, 'incomplete-row-draft'))->toContain('ikut terhitung');

    $component->assertViewHas('summary', fn ($summary) => $summary['draft_trip_count'] === 8
        && $summary['receive']['trip_count'] + $summary['dispatch']['trip_count'] === 8
        && $summary['receive']['net_weight_total'] === 5000.0);
});

// =====================================================================
// Scenario 17: "satu asal menyumbang hampir seluruh arus masuk"
// =====================================================================
it('skenario 17 — satu asal dominan: tiap asal dapat barisnya sendiri, tanpa pemangkasan dan tanpa baris sisa', function () {
    laporanWeighbridgeComponentReceive($this->stationA, '2026-09-01 08:00', 50000.0, 'Estate Dominan');

    foreach (range(1, 11) as $i) {
        laporanWeighbridgeComponentReceive($this->stationA, sprintf('2026-09-%02d 09:00', $i + 1), 100.0, 'Supplier Kecil '.$i);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanWeighbridge::class)
        ->set('productionLineId', $this->lineA)
        ->assertSeeHtml('data-testid="by-origin-table"')
        ->assertSee('Estate Dominan');

    $html = $component->html();

    // Twelve rows, one per origin. Trimming the list would make the smallest
    // supplier vanish from the report entirely.
    expect(substr_count($html, 'data-testid="by-origin-row"'))->toBe(12);

    foreach (range(1, 11) as $i) {
        expect($html)->toContain('Supplier Kecil '.$i);
    }

    // No remainder row of any kind.
    expect(strtolower($html))->not->toContain('lain-lain</td>');
    expect($html)->not->toContain('data-testid="by-origin-row-others"');

    // And the Trip column sums to the headline figure, shown on the footer row.
    expect(laporanWeighbridgeRow($html, 'by-origin-row-total'))->toContain('12');

    $component->assertViewHas('summary', fn ($summary) => count($summary['receive']['by_origin']) === 12
        && array_sum(array_column($summary['receive']['by_origin'], 'trip_count')) === $summary['receive']['trip_count']);
});

// =====================================================================
// Scenario 18: "akun belum terhubung ke mill"
//
// FAIL CLOSED. render() resolves the mill itself rather than calling the
// service's resolveBusinessUnit() — which would throw — precisely so this
// state renders a contact-Admin notice instead of an error page. And NO mill
// picker is offered as a workaround: handing the list of every mill to a role
// that is supposed to be bound to one is exactly the leak this screen must
// not open.
// =====================================================================
it('skenario 18 — akun belum terhubung ke mill: pesan menghubungi Admin, pemilih Mill TIDAK dirender, dan tidak satu angka pun', function () {
    foreach ([UserRole::Supervisor, UserRole::MillManagement] as $role) {
        $noMill = User::factory()->role($role)->create(['business_unit_id' => null]);

        $component = Livewire::actingAs($noMill)
            ->test(LaporanWeighbridge::class)
            ->assertViewHas('hasNoMillForAccount', true)
            ->assertSeeHtml('data-testid="no-mill-hint"')
            ->assertSee('Akun Anda belum terhubung ke mill')
            ->assertSee('Hubungi Admin')
            // No all-mills list is offered as a way out.
            ->assertDontSeeHtml('data-testid="mill-select"')
            ->assertViewHas('businessUnitOptions', [])
            ->assertViewHas('summary', null)
            ->assertDontSee('Mill Alpha')
            ->assertDontSee('Mill Beta');

        $html = $component->html();

        foreach (LAPORAN_WEIGHBRIDGE_FIGURE_TESTIDS as $testid) {
            expect($html)->not->toContain('data-testid="'.$testid.'"');
        }
    }
});

// =====================================================================
// Scenario 19: "mencoba melihat mill atau Production Line milik mill lain"
// =====================================================================
it('skenario 19 — lintas mill: mill kiriman diabaikan diam-diam, line mill lain dibuang ke belum-memilih, dan periode mill lain ditolak secara terlihat', function () {
    laporanWeighbridgeComponentReceive($this->stationA, '2026-09-10 08:00', 1000.0, 'Estate Alpha');
    laporanWeighbridgeComponentReceive($this->stationB, '2026-09-10 08:00', 99999.0, 'Estate Beta');

    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('weighbridge')
        ->range('2026-09-01', '2026-09-30')->named('Periode September Beta')->open()->create();

    // (a) Forcing another mill's id changes NOTHING at all for a bound role —
    // the property is never consulted, so this is not a refusal, it is a no-op.
    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanWeighbridge::class)
        ->set('productionLineId', $this->lineA)
        ->set('businessUnitId', (string) $this->businessUnitB->id)
        ->assertSee('Mill Alpha')
        ->assertDontSee('Mill Beta')
        ->assertSee('Estate Alpha')
        ->assertDontSee('Estate Beta')
        ->assertViewHas('summary', fn ($summary) => $summary['business_unit']['name'] === 'Mill Alpha'
            && $summary['receive']['net_weight_total'] === 1000.0);

    // (b) A FOREIGN PRODUCTION LINE is a concrete handle on another mill's
    // data, so it is dropped back to "not chosen" — which renders the
    // choose-a-line prompt rather than parading the other mill's line name.
    $component->set('productionLineId', $this->lineB)
        ->assertSet('productionLineId', '')
        ->assertViewHas('summary', null)
        ->assertSeeHtml('data-testid="select-production-line-hint"')
        ->assertDontSee('Estate Beta');

    // (c) A FOREIGN PERIOD is refused VISIBLY, with none of the other mill's
    // figures rendered — a silent swap would answer a cross-mill probe with
    // different numbers under the id that was asked for.
    $forbidden = Livewire::actingAs($this->supervisor)
        ->test(LaporanWeighbridge::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $periodB->id)
        ->assertViewHas('forbidden', true)
        ->assertSeeHtml('data-testid="forbidden-notice"')
        ->assertSee('Anda tidak memiliki akses ke periode ini')
        ->assertViewHas('summary', null)
        ->assertDontSee('Estate Beta');

    $html = $forbidden->html();

    foreach (LAPORAN_WEIGHBRIDGE_FIGURE_TESTIDS as $testid) {
        expect($html)->not->toContain('data-testid="'.$testid.'"');
    }
});

// =====================================================================
// Scenario 20: "Operator mencoba membuka layar web ini"
// =====================================================================
it('skenario 20 — Operator: rute menolak sebelum mount, dan mount() sendiri juga menolak, tanpa satu pun data termuat', function () {
    laporanWeighbridgeComponentReceive($this->stationA, '2026-09-04 08:00', 1000.0, 'Estate Rahasia');

    // Route layer — EnsureRole -> abort(403) before the component ever mounts.
    $response = $this->actingAs($this->operator, 'web')->get('/reports/weighbridge');
    $response->assertForbidden();
    $response->assertDontSee('Laporan Periode');
    $response->assertDontSee('Estate Rahasia');

    // Component layer — mount()'s abort_unless(403) covers the component being
    // mounted directly, which is exactly how this scenario exercises it.
    //
    // THIS REFUSAL SURVIVED THE screen-144 WIDENING (2026-10-05), and that is
    // the point of keeping this assertion: the four /api/weighbridge-reports/*
    // routes now admit Operator for the mobile report, while this WEB screen
    // does not. The component has its own role list (canAccess()) rather than
    // borrowing WeighbridgeReportService::guardAccess(), so widening the
    // service could not widen this screen by accident — and if someone ever
    // makes canAccess() delegate to the service, this test fails.
    $html = Livewire::actingAs($this->operator)->test(LaporanWeighbridge::class)->html();

    expect($html)->toContain('Forbidden');
    expect($html)->not->toContain('data-testid="laporan-weighbridge"');
    expect($html)->not->toContain('Estate Rahasia');

    // No summary, period list, production line list or trip detail is ever
    // loaded into the component state.
    foreach (LAPORAN_WEIGHBRIDGE_FIGURE_TESTIDS as $testid) {
        expect($html)->not->toContain('data-testid="'.$testid.'"');
    }

    foreach (['period-select', 'production-line-select', 'mill-select'] as $picker) {
        expect($html)->not->toContain('data-testid="'.$picker.'"');
    }
});

// =====================================================================
// Scenario 21: "periode berstatus tertutup"
// =====================================================================
it('skenario 21 — periode tertutup: tetap dapat dipilih, laporan dirender penuh, status Tertutup dilabelkan, dan tombol ekspor tidak dinonaktifkan', function () {
    $closed = Period::factory()->forBusinessUnit($this->businessUnitA)->noStations()
        ->range('2026-11-01', '2026-11-30')->named('Periode November Tertutup')->create();
    PeriodStation::factory()->forPeriod($closed)->stationType('weighbridge')->closed()->create();

    laporanWeighbridgeComponentReceive($this->stationA, '2026-11-04 08:00', 1000.0);
    laporanWeighbridgeComponentDispatch($this->stationA, '2026-11-05 14:00', 2000.0);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanWeighbridge::class)
        ->set('productionLineId', $this->lineA)
        // Listed and selectable: the period lock governs WRITING data, not
        // READING a report.
        ->assertViewHas('periods', fn ($periods) => in_array((string) $closed->id, array_column($periods, 'id'), true))
        ->set('periodId', (string) $closed->id)
        ->assertSeeHtml('data-testid="period-status-badge"')
        ->assertSee('Tertutup')
        // Exactly the same sections as an open period.
        ->assertSeeHtml('data-testid="flow-receive"')
        ->assertSeeHtml('data-testid="flow-dispatch"')
        ->assertSeeHtml('data-testid="by-origin-table"')
        ->assertSeeHtml('data-testid="by-destination-table"')
        ->assertSeeHtml('data-testid="hourly-distribution"')
        ->assertSeeHtml('data-testid="daily-table"')
        ->assertSeeHtml('data-testid="daily-trend"')
        ->assertSeeHtml('data-testid="completeness-card"')
        ->assertSeeHtml('data-testid="export-button"');

    $html = $component->html();

    // The export button is never disabled by status.
    expect($html)->toMatch('/<button[^>]*data-testid="export-button"/');
    expect(preg_match('/<button[^>]*disabled[^>]*data-testid="export-button"/', $html))->toBe(0);

    // And it really downloads.
    $component->call('exportCsv', 'csv')->assertFileDownloaded(null, null, 'text/csv');
});

// =====================================================================
// Scenario 22: "rekap harian sangat panjang"
// =====================================================================
it('skenario 22 — rekap harian panjang: terbuka secara bawaan, dapat ditutup dan dibuka lagi, dan tak satu angka pun berubah', function () {
    $long = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('weighbridge')
        ->range('2026-11-01', '2026-12-30')->named('Periode 60 Hari')->open()->create();

    for ($day = 1; $day <= 30; $day++) {
        laporanWeighbridgeComponentReceive($this->stationA, sprintf('2026-11-%02d 08:00', $day), 1000.0);
    }

    for ($day = 1; $day <= 10; $day++) {
        laporanWeighbridgeComponentDispatch($this->stationA, sprintf('2026-12-%02d 14:00', $day), 2000.0);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanWeighbridge::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $long->id)
        // OPEN BY DEFAULT, and that is a decision rather than a default left
        // alone — the inclusive-range scenario reads dates straight off this
        // table without toggling anything.
        ->assertSet('dailyRecapOpen', true)
        ->assertSeeHtml('data-testid="daily-table"');

    $openHtml = $component->html();

    $headlineOpen = [
        laporanWeighbridgeRendered($openHtml, 'kpi-receive-trip-count'),
        laporanWeighbridgeRendered($openHtml, 'receive-net-weight-trip-count'),
        laporanWeighbridgeRendered($openHtml, 'draft-trip-count'),
        laporanWeighbridgeRendered($openHtml, 'days-with-trip'),
    ];

    // CLOSED means genuinely absent from the DOM, not merely hidden — a plain
    // button rather than <details>, so the visible state and the rendered DOM
    // can never disagree.
    $component->call('toggleDailyRecap')
        ->assertSet('dailyRecapOpen', false)
        ->assertDontSeeHtml('data-testid="daily-table"')
        // The headline figures and the trend stay rendered in BOTH states.
        ->assertSeeHtml('data-testid="flow-receive"')
        ->assertSeeHtml('data-testid="flow-dispatch"')
        ->assertSeeHtml('data-testid="daily-trend"')
        ->assertSeeHtml('data-testid="daily-toggle"');

    $closedHtml = $component->html();

    expect([
        laporanWeighbridgeRendered($closedHtml, 'kpi-receive-trip-count'),
        laporanWeighbridgeRendered($closedHtml, 'receive-net-weight-trip-count'),
        laporanWeighbridgeRendered($closedHtml, 'draft-trip-count'),
        laporanWeighbridgeRendered($closedHtml, 'days-with-trip'),
    ])->toBe($headlineOpen);

    $component->call('toggleDailyRecap')
        ->assertSet('dailyRecapOpen', true)
        ->assertSeeHtml('data-testid="daily-table"');

    $reopenedHtml = $component->html();

    expect([
        laporanWeighbridgeRendered($reopenedHtml, 'kpi-receive-trip-count'),
        laporanWeighbridgeRendered($reopenedHtml, 'receive-net-weight-trip-count'),
        laporanWeighbridgeRendered($reopenedHtml, 'draft-trip-count'),
        laporanWeighbridgeRendered($reopenedHtml, 'days-with-trip'),
    ])->toBe($headlineOpen);

    $component->assertViewHas('summary', fn ($summary) => count($summary['daily']) === 40);
});

// =====================================================================
// Scenario 23: "arus masuk dan arus keluar tidak pernah dijumlahkan"
// =====================================================================
it('skenario 23 — kedua arus tidak pernah dijumlahkan: tiap metrik dirender terpisah dan tidak ada satu pun angka gabungan di halaman', function () {
    laporanWeighbridgeComponentReceive($this->stationA, '2026-09-04 08:00', 1000.0);
    laporanWeighbridgeComponentReceive($this->stationA, '2026-09-05 08:00', 2000.0);
    laporanWeighbridgeComponentDispatch($this->stationA, '2026-09-04 14:00', 4000.0);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanWeighbridge::class)
        ->set('productionLineId', $this->lineA)
        ->assertSeeHtml('data-testid="no-sum-note"')
        ->assertSee('dua kelompok yang tidak pernah dijumlahkan');

    $html = $component->html();

    // Each metric rendered separately for each flow — and a SEPARATE hourly
    // chart per flow, never one stacked chart, because a stacked bar would
    // show a combined height, which is exactly the number this report must
    // not have.
    foreach ([
        'kpi-receive-trip-count', 'kpi-dispatch-trip-count',
        'kpi-receive-net-weight-total', 'kpi-dispatch-net-weight-total',
        'kpi-receive-net-weight-avg', 'kpi-dispatch-net-weight-avg',
        'hourly-chart-receive', 'hourly-chart-dispatch',
    ] as $testid) {
        expect($html)->toContain('data-testid="'.$testid.'"');
    }

    // NO rendered element claims to be a combined figure.
    foreach (laporanWeighbridgeTestIds($html) as $testid) {
        foreach (['combined', 'gabungan', 'grand', 'overall', 'total-trip', 'all-flows'] as $forbidden) {
            expect(strtolower($testid))->not->toContain($forbidden);
        }
    }

    // The daily recap keeps the flows in SEPARATE COLUMNS, including on the
    // footer row: 5 cells (date + 2 per flow), never a 6th combined one.
    expect(preg_match_all('/<td/', laporanWeighbridgeRow($html, 'daily-row-total')))->toBe(5);
    expect(preg_match_all('/<td/', laporanWeighbridgeRow($html, 'daily-row-2026-09-04')))->toBe(5);

    $headings = laporanWeighbridgeTableHeadings($html);

    foreach ($headings as $heading) {
        foreach (['gabungan', 'combined', 'total keseluruhan'] as $forbidden) {
            expect(strtolower($heading))->not->toContain($forbidden);
        }
    }

    // And the payload itself carries no combined key.
    $component->assertViewHas('summary', function ($summary) {
        foreach (array_map('strtolower', laporanWeighbridgeComponentAllKeys($summary)) as $key) {
            foreach (['combined', 'gabungan', 'grand', 'overall', 'all_flows'] as $forbidden) {
                if (str_contains($key, $forbidden)) {
                    return false;
                }
            }
        }

        return array_keys($summary) === [
            'business_unit', 'production_line', 'period', 'receive', 'dispatch',
            'draft_trip_count', 'undated_trip_count', 'daily', 'daily_total', 'completeness',
        ];
    });

    // The cross-flow sum (3 trips / 7.000,00 kg) is nowhere on the page.
    expect($html)->not->toContain('7.000,00');
});

// =====================================================================
// Scenario 24: "rata-rata berat hanya memakai trip yang beratnya terisi"
// =====================================================================
it('skenario 24 — rata-rata memakai penyebutnya sendiri: 10 trip, 6 tertimbang, penyebut 6 dirender di samping angkanya', function () {
    foreach (range(1, 6) as $i) {
        laporanWeighbridgeComponentReceive($this->stationA, sprintf('2026-09-%02d 08:00', $i), 10000.0);
    }

    foreach (range(7, 10) as $i) {
        laporanWeighbridgeComponentReceive($this->stationA, sprintf('2026-09-%02d 08:00', $i), null);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanWeighbridge::class)
        ->set('productionLineId', $this->lineA);

    $html = $component->html();
    $receive = laporanWeighbridgeSection($html, 'flow-receive');

    // 60000 / 6 = 10.000,00 — and 60000 / 10 = 6.000,00 is nowhere.
    expect($receive)->toContain('10.000,00');
    expect($html)->not->toContain('6.000,00');

    // The 6 is rendered BESIDE the average, as the denominator used.
    expect(laporanWeighbridgeRendered($html, 'receive-avg-denominator'))->toBe('6');
    expect(laporanWeighbridgeRendered($html, 'receive-net-weight-trip-count'))->toBe('6');
    // The trip count still reads 10.
    expect(laporanWeighbridgeSection($html, 'flow-receive'))->toContain('10 <span>trip</span>');
    // And the four weightless trips are their own figure, so the two numbers
    // reconcile.
    expect(laporanWeighbridgeRendered($html, 'missing-net-weight-receive'))->toBe('4 trip');

    $component->assertViewHas('summary', fn ($summary) => $summary['receive']['trip_count'] === 10
        && $summary['receive']['net_weight_trip_count'] === 6
        && $summary['receive']['net_weight_avg'] === 10000.0);
});

// =====================================================================
// Scenario 25: "jumlah penimbangan yang belum selesai ditampilkan per arus"
// =====================================================================
it('skenario 25 — penimbangan belum selesai dirender sebagai DUA angka terpisah per arus, tidak pernah dijumlahkan jadi satu', function () {
    foreach ([1000.0, null, null] as $i => $net) {
        laporanWeighbridgeComponentReceive($this->stationA, sprintf('2026-09-%02d 08:00', $i + 1), $net);
    }

    foreach ([2000.0, 3000.0, null, null, null] as $i => $net) {
        laporanWeighbridgeComponentDispatch($this->stationA, sprintf('2026-09-%02d 14:00', $i + 1), $net);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanWeighbridge::class)
        ->set('productionLineId', $this->lineA)
        ->assertSeeHtml('data-testid="incomplete-row-missing-weight-receive"')
        ->assertSeeHtml('data-testid="incomplete-row-missing-weight-dispatch"');

    $html = $component->html();

    // Two separate figures, in their own rows.
    expect(laporanWeighbridgeRendered($html, 'missing-net-weight-receive'))->toBe('2 trip');
    expect(laporanWeighbridgeRendered($html, 'missing-net-weight-dispatch'))->toBe('3 trip');

    // Each is ALSO rendered alongside its own flow's trip count, so the share
    // of incomplete data per flow is readable.
    expect(laporanWeighbridgeRendered($html, 'receive-missing-net-weight-inline'))->toBe('2');
    expect(laporanWeighbridgeRendered($html, 'dispatch-missing-net-weight-inline'))->toBe('3');
    expect(laporanWeighbridgeSection($html, 'flow-receive'))->toContain('3 <span>trip</span>');
    expect(laporanWeighbridgeSection($html, 'flow-dispatch'))->toContain('5 <span>trip</span>');

    // NEVER summed: no element renders the merged figure 5 trip as a single
    // missing-weight number.
    expect(laporanWeighbridgeTestIds($html))->not->toContain('missing-net-weight-total');
    expect(laporanWeighbridgeTestIds($html))->not->toContain('missing-net-weight');
});

// =====================================================================
// Scenario 26: "penyaringan line memakai line yang melekat pada trip itu"
// =====================================================================
it('skenario 26 — line yang MELEKAT pada trip: angka line lama tidak berubah setelah stasiunnya dipindah, dan line baru tidak mewarisi trip itu', function () {
    $lineRecorded = $this->lineA;
    $lineCurrent = ProductionLine::factory()->forBusinessUnit($this->businessUnitA)->create(['name' => 'Line Kedua']);

    laporanWeighbridgeComponentReceive($this->stationA, '2026-09-04 08:00', 1000.0, 'Estate Asal');
    laporanWeighbridgeComponentDispatch($this->stationA, '2026-09-04 14:00', 2000.0, 'Refinery X');

    // The station is LATER reassigned. Moving a station must not rewrite the
    // trips it already produced.
    $this->stationA->update(['production_line_id' => $lineCurrent->id]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanWeighbridge::class)
        ->set('productionLineId', $lineRecorded)
        ->assertSee('Estate Asal')
        ->assertSeeHtml('data-testid="daily-row-2026-09-04"')
        ->assertViewHas('summary', fn ($summary) => $summary['receive']['trip_count'] === 1
            && $summary['dispatch']['trip_count'] === 1
            && $summary['receive']['net_weight_total'] === 1000.0
            && count($summary['receive']['by_origin']) === 1
            && count($summary['daily']) === 1);

    // Under the station's CURRENT line the same trip appears in NO figure.
    $component->set('productionLineId', (string) $lineCurrent->id)
        ->assertDontSee('Estate Asal')
        ->assertSeeHtml('data-testid="empty-state"')
        ->assertViewHas('summary', fn ($summary) => $summary['receive']['trip_count'] === 0
            && $summary['dispatch']['trip_count'] === 0
            && $summary['receive']['by_origin'] === []
            && $summary['daily'] === []);
});

// =====================================================================
// Scenario 27: "keanggotaan periode ditentukan waktu kejadian penimbangan"
// =====================================================================
it('skenario 27 — keanggotaan periode dari waktu penimbangan: waktu baris dibuat maupun waktu sinkronisasi tidak memengaruhi satu angka pun', function () {
    $inside = laporanWeighbridgeComponentReceive($this->stationA, '2026-09-20 08:00', 1000.0, 'Ditimbang Di Dalam');
    $inside->forceFill(['created_at' => '2026-12-01 01:00:00', 'updated_at' => '2026-12-01 01:00:00'])->saveQuietly();

    $outside = laporanWeighbridgeComponentReceive($this->stationA, '2026-07-20 08:00', 9000.0, 'Ditimbang Di Luar');
    $outside->forceFill(['created_at' => '2026-09-15 01:00:00', 'updated_at' => '2026-09-15 01:00:00'])->saveQuietly();

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanWeighbridge::class)
        ->set('productionLineId', $this->lineA)
        ->assertSee('Ditimbang Di Dalam')
        ->assertDontSee('Ditimbang Di Luar')
        ->assertSeeHtml('data-testid="daily-row-2026-09-20"');

    $before = $component->html();

    // Altering created_at changes NOTHING on the rendered page.
    $inside->forceFill(['created_at' => '2026-09-20 08:00:00'])->saveQuietly();
    $outside->forceFill(['created_at' => '2026-12-31 23:00:00'])->saveQuietly();

    $after = Livewire::actingAs($this->supervisor)
        ->test(LaporanWeighbridge::class)
        ->set('productionLineId', $this->lineA);

    $after->assertViewHas('summary', fn ($summary) => $summary['receive']['trip_count'] === 1
        && $summary['receive']['net_weight_total'] === 1000.0
        && array_column($summary['daily'], 'date') === ['2026-09-20']);

    expect(laporanWeighbridgeRendered($after->html(), 'kpi-receive-trip-count'))
        ->toBe(laporanWeighbridgeRendered($before, 'kpi-receive-trip-count'));
});

// =====================================================================
// Scenario 28: "rentang periode inklusif di kedua ujung"
// =====================================================================
it('skenario 28 — rentang inklusif: trip hari pertama dan hari terakhir terhitung penuh, H-1 dan H+1 tidak, dan rekap harian memuat kedua ujungnya', function () {
    laporanWeighbridgeComponentReceive($this->stationA, '2026-09-01 00:05', 1000.0, 'Hari Pertama');
    laporanWeighbridgeComponentReceive($this->stationA, '2026-09-30 23:50', 2000.0, 'Hari Terakhir');
    laporanWeighbridgeComponentReceive($this->stationA, '2026-08-31 23:50', 9000.0, 'Sehari Sebelum');
    laporanWeighbridgeComponentReceive($this->stationA, '2026-10-01 00:10', 8000.0, 'Sehari Sesudah');

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanWeighbridge::class)
        ->set('productionLineId', $this->lineA)
        ->assertSee('Hari Pertama')
        ->assertSee('Hari Terakhir')
        ->assertDontSee('Sehari Sebelum')
        ->assertDontSee('Sehari Sesudah')
        // The daily recap carries a row for the first day AND the last day.
        ->assertSeeHtml('data-testid="daily-row-2026-09-01"')
        ->assertSeeHtml('data-testid="daily-row-2026-09-30"')
        ->assertDontSeeHtml('data-testid="daily-row-2026-08-31"')
        ->assertDontSeeHtml('data-testid="daily-row-2026-10-01"');

    $html = $component->html();

    expect(laporanWeighbridgeRendered($html, 'days-with-trip'))->toBe('2');
    expect(laporanWeighbridgeRendered($html, 'days-in-period'))->toBe('30');
    // The excluded rows' weights appear nowhere.
    expect($html)->not->toContain('9.000,00');
    expect($html)->not->toContain('8.000,00');

    $component->assertViewHas('summary', fn ($summary) => $summary['receive']['trip_count'] === 2
        && $summary['receive']['net_weight_total'] === 3000.0
        && array_column($summary['daily'], 'date') === ['2026-09-01', '2026-09-30']);
});

// =====================================================================
// Scenario 29: "tidak ada angka lama kendaraan berada di pabrik di mana pun"
//
// READ THE FILE HEADER BEFORE CHANGING THIS TEST. It must NEVER assert
// assertDontSee('durasi') or assertDontSee('duration'): the blade deliberately
// renders the prose "Lama kendaraan di pabrik TIDAK dilaporkan di layar ini,
// dan ketiadaannya disengaja" under data-testid="no-duration-note", which is
// the feature, not a leak. A naive text grep fails on the explanation itself
// and would force someone to delete the explanation to go green.
//
// So the absence is asserted STRUCTURALLY: zero duration-named keys in the
// payload, zero duration-named <th>, zero duration KPI card, and exactly ONE
// timestamp column in the CSV the screen downloads.
// =====================================================================
it('skenario 29 — tidak ada angka lama kendaraan di pabrik: diasersi secara STRUKTURAL, bukan lewat pencarian teks (penjelasannya memang ada di layar)', function () {
    foreach (range(1, 8) as $i) {
        laporanWeighbridgeComponentReceive($this->stationA, sprintf('2026-09-%02d 0%d:30', $i, $i % 9), 1000.0);
        laporanWeighbridgeComponentDispatch($this->stationA, sprintf('2026-09-%02d 1%d:30', $i, $i % 9), 2000.0);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanWeighbridge::class)
        ->set('productionLineId', $this->lineA);

    $html = $component->html();

    // (a) ZERO duration-named keys in the payload the view is handed.
    $component->assertViewHas('summary', function ($summary) {
        foreach (array_map('strtolower', laporanWeighbridgeComponentAllKeys($summary)) as $key) {
            foreach (['duration', 'durasi', 'lama', 'turnaround', 'dwell', 'elapsed', 'arrival', 'in_plant'] as $forbidden) {
                if (str_contains($key, $forbidden)) {
                    return false;
                }
            }
        }

        return true;
    });

    // (b) ZERO duration-named <th> on the page.
    foreach (laporanWeighbridgeTableHeadings($html) as $heading) {
        foreach (['durasi', 'duration', 'lama', 'turnaround'] as $forbidden) {
            expect(strtolower($heading))->not->toContain($forbidden);
        }
    }

    // (c) ZERO duration KPI card, as a value, an average or an extreme.
    foreach (laporanWeighbridgeTestIds($html) as $testid) {
        foreach (['duration', 'durasi', 'lama', 'turnaround', 'dwell'] as $forbidden) {
            // The explanatory note is the ONE testid allowed to name the
            // absent metric — it exists precisely to say it is absent.
            if ($testid === 'no-duration-note') {
                continue;
            }

            expect(strtolower($testid))->not->toContain($forbidden);
        }
    }

    // (d) The ONLY time-based rendering is the hourly distribution, which is
    // derivable from a SINGLE timestamp.
    expect($html)->toContain('data-testid="hourly-distribution"');
    expect($html)->toContain('data-testid="kpi-busiest-hour-receive"');
    expect($html)->toContain('data-testid="kpi-empty-hours-receive"');

    // (e) And the explanation IS rendered — the reader is told why the metric
    // is missing rather than left to wonder.
    expect($html)->toContain('data-testid="no-duration-note"');

    // (f) The downloaded CSV carries EXACTLY ONE timestamp column, and no
    // duration column.
    $downloaded = $component->call('exportCsv', 'csv');
    $csv = laporanWeighbridgeDownloadedCsv($downloaded);
    $header = str_getcsv(laporanWeighbridgeDownloadedLines($csv)[0], ',', '"', '\\');

    expect($header)->toBe(WeighbridgeReportService::EXPORT_HEADER);

    $timeColumns = array_values(array_filter(
        $header,
        fn ($column) => preg_match('/waktu|time|jam|tanggal|date/i', $column) === 1,
    ));

    expect($timeColumns)->toBe(['Waktu Penimbangan']);

    foreach ($header as $column) {
        foreach (['durasi', 'duration', 'lama', 'turnaround'] as $forbidden) {
            expect(strtolower($column))->not->toContain($forbidden);
        }
    }
});

// =====================================================================
// Scenario 30: "sebaran trip per jam dihitung dari penanda waktu tunggal"
// =====================================================================
it('skenario 30 — sebaran per jam: 24 slot per arus yang menjumlah tepat ke trip bertanggal arus itu, dengan jam tersibuk dan jam tanpa trip sendiri-sendiri', function () {
    foreach (['06:10', '06:50', '09:15', '14:40', '22:05'] as $i => $time) {
        laporanWeighbridgeComponentReceive($this->stationA, sprintf('2026-09-%02d %s', $i + 1, $time), 1000.0);
    }

    foreach (['09:30', '20:10'] as $i => $time) {
        laporanWeighbridgeComponentDispatch($this->stationA, sprintf('2026-09-%02d %s', $i + 10, $time), 2000.0);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanWeighbridge::class)
        ->set('productionLineId', $this->lineA)
        ->assertSeeHtml('data-testid="hourly-chart-receive"')
        ->assertSeeHtml('data-testid="hourly-chart-dispatch"')
        ->assertSeeHtml('data-testid="kpi-busiest-hour-receive"')
        ->assertSeeHtml('data-testid="kpi-busiest-hour-dispatch"')
        ->assertSeeHtml('data-testid="kpi-empty-hours-receive"')
        ->assertSeeHtml('data-testid="kpi-empty-hours-dispatch"');

    $html = $component->html();

    // 24 slots PER FLOW, each taken from the trips' own single timestamps.
    foreach (['receive', 'dispatch'] as $flow) {
        foreach (range(0, 23) as $hour) {
            expect($html)->toContain('data-testid="hourly-col-'.$flow.'-'.$hour.'"');
        }
    }

    // Busiest hour rendered as a WALL CLOCK, so it can never be read as a
    // quantity.
    expect(laporanWeighbridgeRendered($html, 'kpi-busiest-hour-receive'))->not->toBeNull();
    expect(laporanWeighbridgeSection($html, 'hourly-kpis'))->toContain('06.00');
    expect(laporanWeighbridgeSection($html, 'hourly-kpis'))->toContain('09.00');

    $component->assertViewHas('summary', function ($summary) {
        foreach (['receive', 'dispatch'] as $flow) {
            if (count($summary[$flow]['hourly']) !== 24) {
                return false;
            }

            // The sum of the 24 slot counts equals exactly that flow's dated
            // trip count.
            if (array_sum(array_column($summary[$flow]['hourly'], 'trip_count')) !== $summary[$flow]['trip_count']) {
                return false;
            }
        }

        return $summary['receive']['busiest_hour'] === 6
            && $summary['receive']['empty_hour_count'] === 20
            && $summary['dispatch']['busiest_hour'] === 9
            && $summary['dispatch']['empty_hour_count'] === 22;
    });
});

// =====================================================================
// Scenario 31: "layar hanya membaca dan tidak mengubah data stasiun"
// =====================================================================
it('skenario 31 — baca saja: tidak ada method publik yang menulis, tidak ada kontrol tulis, dan data stasiun tidak berubah setelah seluruh interaksi', function () {
    $otherPeriod = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('weighbridge')
        ->range('2026-11-01', '2026-11-30')->named('Periode November')->open()->create();

    laporanWeighbridgeComponentReceive($this->stationA, '2026-09-04 08:00', 1000.0);
    laporanWeighbridgeComponentDispatch($this->stationA, '2026-09-05 14:00', 2000.0);
    laporanWeighbridgeComponentReceive($this->stationA, '2026-11-04 08:00', 3000.0);

    $countBefore = WeighbridgeRecord::count();
    $checksumBefore = WeighbridgeRecord::query()->orderBy('id')->get()->toJson();

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanWeighbridge::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $otherPeriod->id)
        ->set('periodId', (string) $this->periodA->id)
        ->call('toggleDailyRecap')
        ->call('toggleDailyRecap');

    $firstRead = $component->viewData('summary');

    $component->call('exportCsv', 'csv')->assertFileDownloaded(null, null, 'text/csv');

    $html = $component->html();

    foreach (LAPORAN_WEIGHBRIDGE_WRITE_TESTIDS as $writeish) {
        expect($html)->not->toContain('data-testid="'.$writeish.'"');
    }

    // The component's PUBLIC SURFACE is three pickers, a toggle and an export
    // — nothing that reads like a write action.
    $methods = array_map(
        fn (ReflectionMethod $method) => $method->getName(),
        (new ReflectionClass(LaporanWeighbridge::class))->getMethods(ReflectionMethod::IS_PUBLIC)
    );

    foreach (['save', 'store', 'create', 'update', 'delete', 'destroy', 'submit', 'verify', 'close'] as $writeish) {
        expect($methods)->not->toContain($writeish);
    }

    // Byte-identical rows, and re-reading the same period and line returns the
    // same payload as the first read.
    expect(WeighbridgeRecord::count())->toBe($countBefore);
    expect(WeighbridgeRecord::query()->orderBy('id')->get()->toJson())->toBe($checksumBefore);

    $reread = Livewire::actingAs($this->supervisor)
        ->test(LaporanWeighbridge::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $this->periodA->id);

    expect($reread->viewData('summary'))->toEqual($firstRead);
});

// =====================================================================
// Scenario 32: "daftar periode dibatasi pada periode yang mencakup Weighbridge"
// =====================================================================
it('skenario 32 — opsi periode hanya yang MENDAFTARKAN weighbridge, dengan status baris weighbridge-nya sendiri dan kedua status tetap dapat dipilih', function () {
    $open = Period::factory()->forBusinessUnit($this->businessUnitA)->noStations()
        ->range('2026-10-01', '2026-10-31')->named('Periode Weighbridge Terbuka')->create();
    PeriodStation::factory()->forPeriod($open)->stationType('weighbridge')->open()->create();
    // Another station type in the SAME period is CLOSED, so a lookup that
    // grabs "the period's status" picks the wrong row.
    PeriodStation::factory()->forPeriod($open)->stationType('boiler-room')->closed()->create();

    $closed = Period::factory()->forBusinessUnit($this->businessUnitA)->noStations()
        ->range('2026-11-01', '2026-11-30')->named('Periode Weighbridge Tertutup')->create();
    PeriodStation::factory()->forPeriod($closed)->stationType('weighbridge')->closed()->create();

    $otherStationOnly = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('boiler-room')
        ->range('2026-12-01', '2026-12-31')->named('Periode Boiler Saja')->open()->create();

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanWeighbridge::class)
        ->set('productionLineId', $this->lineA)
        ->assertSee('Periode Weighbridge Terbuka')
        ->assertSee('Periode Weighbridge Tertutup')
        // Periods registering only other stations are absent from the list.
        ->assertDontSee('Periode Boiler Saja');

    $component->assertViewHas('periods', function ($periods) use ($open, $closed, $otherStationOnly) {
        $byId = collect($periods)->keyBy('id');

        return $byId->has((string) $open->id)
            && $byId->has((string) $closed->id)
            && ! $byId->has((string) $otherStationOnly->id)
            // Each option's status is ITS OWN weighbridge period_stations row.
            && $byId[(string) $open->id]['status'] === 'open'
            && $byId[(string) $closed->id]['status'] === 'closed'
            && $byId[(string) $open->id]['station_type'] === 'weighbridge';
    });

    // Never Period::$status — which no longer exists and THROWS.
    expect(fn () => $open->status)->toThrow(LogicException::class);

    // Both open and closed are present among the options and both are
    // selectable: status never filters this list.
    $html = $component->html();
    expect($html)->toContain('Terbuka');
    expect($html)->toContain('Tertutup');

    $component->set('periodId', (string) $closed->id)
        ->assertSet('periodId', (string) $closed->id)
        ->assertViewHas('forbidden', false);
});

// =====================================================================
// Scenario 33: "tidak ada penandaan nilai di luar batas"
// =====================================================================
it('skenario 33 — nilai ekstrem dirender apa adanya: tanpa penanda di luar batas, tanpa warna peringatan, tanpa ikon kelayakan', function () {
    laporanWeighbridgeComponentReceive($this->stationA, '2026-09-01 08:00', 1000.0, 'Estate Biasa');
    laporanWeighbridgeComponentReceive($this->stationA, '2026-09-02 08:00', 999999.0, 'Estate Sangat Besar');
    laporanWeighbridgeComponentReceive($this->stationA, '2026-09-03 08:00', 1.0, 'Estate Sangat Kecil');

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanWeighbridge::class)
        ->set('productionLineId', $this->lineA);

    $html = $component->html();

    // The explanatory box uses `.md-explain` rather than the shared
    // `.md-threshold`, precisely so the absence can be asserted as a bare
    // substring on the rendered HTML.
    foreach ([
        'md-threshold', 'is-danger', 'is-warning', 'md-chip--danger',
        'out_of_range', 'is_out_of_range', 'outlier', 'iqr',
    ] as $forbidden) {
        expect($html)->not->toContain($forbidden);
    }

    // The payload carries no flag field either.
    $component->assertViewHas('summary', function ($summary) {
        foreach (array_map('strtolower', laporanWeighbridgeComponentAllKeys($summary)) as $key) {
            foreach (['threshold', 'flag', 'outlier', 'severity', 'alert', 'danger'] as $forbidden) {
                if (str_contains($key, $forbidden)) {
                    return false;
                }
            }
        }

        return true;
    });

    // The extremes are rendered as-is and STILL contribute to the totals and
    // the average like any other trip.
    expect($html)->toContain('999.999,00');
    expect($html)->toContain('Estate Sangat Besar');
    expect($html)->toContain('Estate Sangat Kecil');

    $component->assertViewHas('summary', fn ($summary) => $summary['receive']['net_weight_total'] === 1001000.0
        && $summary['receive']['net_weight_trip_count'] === 3);

    // Every by-origin row carries the SAME markup: there is no conditional
    // branch on class anywhere on this page.
    preg_match_all('/<tr data-testid="by-origin-row"[^>]*>/', $html, $rows);
    expect($rows[0])->toHaveCount(3);
    expect(array_unique($rows[0]))->toHaveCount(1);
});

// =====================================================================
// Scenario 34: "kelengkapan pencatatan tampil sebagai bagian laporan"
// =====================================================================
it('skenario 34 — kelengkapan pencatatan adalah BAGIAN laporan: keempat keadaan terkumpul di satu tempat bersama days_in_period dan days_with_trip', function () {
    laporanWeighbridgeComponentReceive($this->stationA, '2026-09-01 08:00', 1000.0);
    laporanWeighbridgeComponentReceive($this->stationA, '2026-09-02 08:00', null);
    laporanWeighbridgeComponentReceive($this->stationA, '2026-09-03 08:00', 2000.0, 'Estate A', ['status' => RecordStatus::DraftOngoing]);
    laporanWeighbridgeComponentDispatch($this->stationA, '2026-09-03 14:00', null, 'Refinery X');
    laporanWeighbridgeComponentDispatch($this->stationA, '2026-09-04 14:00', 3000.0, null);
    laporanWeighbridgeComponentReceive($this->stationA, null, 7000.0);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanWeighbridge::class)
        ->set('productionLineId', $this->lineA)
        // A card of its own standing, rendered beside the headline figures —
        // not a footnote.
        ->assertSeeHtml('data-testid="completeness-card"')
        ->assertSeeHtml('data-testid="incomplete-card"')
        ->assertSeeHtml('data-testid="incomplete-table"')
        // All five rows gathered in ONE place.
        ->assertSeeHtml('data-testid="incomplete-row-missing-weight-receive"')
        ->assertSeeHtml('data-testid="incomplete-row-missing-weight-dispatch"')
        ->assertSeeHtml('data-testid="incomplete-row-no-destination"')
        ->assertSeeHtml('data-testid="incomplete-row-draft"')
        ->assertSeeHtml('data-testid="incomplete-row-undated"');

    $html = $component->html();

    expect(laporanWeighbridgeRendered($html, 'missing-net-weight-receive'))->toBe('1 trip');
    expect(laporanWeighbridgeRendered($html, 'missing-net-weight-dispatch'))->toBe('1 trip');
    expect(laporanWeighbridgeRendered($html, 'no-destination-dispatch-count'))->toBe('1 trip');
    expect(laporanWeighbridgeRendered($html, 'draft-trip-count'))->toBe('1 trip');
    expect(laporanWeighbridgeRendered($html, 'undated-trip-count'))->toBe('1 trip');
    expect(laporanWeighbridgeRendered($html, 'days-in-period'))->toBe('30');
    expect(laporanWeighbridgeRendered($html, 'days-with-trip'))->toBe('4');

    // Each row states HOW it bears on the figures above — the three states
    // apply differently and the reader is told so.
    expect(laporanWeighbridgeRow($html, 'incomplete-row-draft'))->toContain('ikut terhitung');
    expect(laporanWeighbridgeRow($html, 'incomplete-row-undated'))->toContain('TIDAK ikut terhitung');
    expect(laporanWeighbridgeRow($html, 'incomplete-row-no-destination'))->toContain('tidak dibuang');
});

// =====================================================================
// Scenario 35: "unduh rincian seluruh trip sebagai CSV"
// =====================================================================
it('skenario 35 — ekspor CSV dari layar: satu baris per trip, konteks diulang, label kolom sama dengan layar, kedua arus tetap terbedakan', function () {
    laporanWeighbridgeComponentReceive($this->stationA, '2026-09-01 08:00', 1000.0, 'Estate A');
    laporanWeighbridgeComponentReceive($this->stationA, '2026-09-02 08:00', null, 'Estate B');
    laporanWeighbridgeComponentDispatch($this->stationA, '2026-09-03 14:00', 5000.0, 'Refinery X');
    laporanWeighbridgeComponentDispatch($this->stationA, '2026-09-04 14:00', 6000.0, null);

    $lineName = (string) ProductionLine::findOrFail($this->lineA)->name;

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanWeighbridge::class)
        ->set('productionLineId', $this->lineA)
        ->assertSeeHtml('data-testid="export-button"');

    $downloaded = $component->call('exportCsv', 'csv');
    $downloaded->assertFileDownloaded(null, null, 'text/csv');

    $lines = laporanWeighbridgeDownloadedLines(laporanWeighbridgeDownloadedCsv($downloaded));
    $header = str_getcsv($lines[0], ',', '"', '\\');

    expect($header)->toBe(WeighbridgeReportService::EXPORT_HEADER);
    // One row per trip for this period and line.
    expect($lines)->toHaveCount(5);

    // The column labels match the terms rendered on screen.
    $html = $component->html();
    expect($html)->toContain('Berat bersih (kg)');
    expect($header)->toContain('Berat Bersih (kg)');
    expect($header)->toContain('Jenis Arus');

    $flowIndex = array_search('Jenis Arus', $header, true);
    $netIndex = array_search('Berat Bersih (kg)', $header, true);
    $flows = [];
    $netCells = [];

    foreach (array_slice($lines, 1) as $line) {
        $row = str_getcsv($line, ',', '"', '\\');

        // The mill, production line and period context repeat on every row.
        expect($row[0])->toBe('Periode September Alpha');
        expect($row[1])->toBe('Mill Alpha');
        expect($row[2])->toBe($lineName);

        $flows[] = $row[$flowIndex];
        $netCells[] = $row[$netIndex];
    }

    // Receive and dispatch stay distinguishable and are NEVER summed.
    expect(count(array_filter($flows, fn ($f) => $f === 'Arus Masuk')))->toBe(2);
    expect(count(array_filter($flows, fn ($f) => $f === 'Arus Keluar')))->toBe(2);

    // The unweighed trip is a row with an EMPTY weight cell, never dropped and
    // never written as 0.
    expect(count(array_filter($netCells, fn ($cell) => $cell === '')))->toBe(1);
    expect($netCells)->not->toContain('0');

    // And exportCsv() does nothing at all when no line is chosen: a file that
    // mixed every line of the mill must not be reachable from here either.
    $noLine = Livewire::actingAs($this->supervisor)->test(LaporanWeighbridge::class);
    expect($noLine->call('exportCsv', 'csv')->effects)->not->toHaveKey('download');
});
