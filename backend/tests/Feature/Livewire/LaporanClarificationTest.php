<?php

/**
 * LaporanClarificationTest (Feature/Livewire) — screen-132--laporan-clarification-web /
 * usecase-132--laporan-clarification-web (Laporan Periode Clarification).
 *
 * Component tests for App\Livewire\Dashboard\LaporanClarification, one per
 * test_scenarios entry's `component_test`. Mirrors
 * tests/Feature/Livewire/LaporanBoilerRoomTest.php (screen-131).
 *
 * COMPONENT SHAPE (deliberately minimal, and asserted as such): properties
 * `businessUnitId`, `periodId`, `dailyRecapOpen`; methods mount(),
 * updatedBusinessUnitId(), toggleDailyRecap(), exportCsv($format),
 * render(). Nothing that reads like a write action — a read-only report
 * must not offer one.
 *
 * ACCESS CONTROL is closed twice over: the route carries
 * 'role:supervisor,mill_management,admin' (EnsureRole -> abort 403 before
 * the component ever mounts), and mount() itself refuses an Operator. The
 * Operator scenario asserts BOTH, because each guard covers a path the
 * other does not. There is no mobile Clarification report (screen-138), so
 * nothing here was widened for Operator.
 *
 * THE MILL IS NEVER NEGOTIABLE FROM THE UI for a bound role: the "mill
 * lain" scenario forces the public `businessUnitId` property to another
 * mill and asserts that not one figure moves — 200 with the caller's own
 * data, deliberately not a refusal. A PERIOD id from another mill IS
 * refused, visibly, with none of that mill's figures rendered.
 *
 * ----------------------------------------------------------------------
 * ON "–" vs "0", AND WHY THE MARKER GLYPH IS NEVER ASSERTED DIRECTLY
 * ----------------------------------------------------------------------
 * A null figure must render as an unavailable marker and NEVER as 0: "we
 * measured it and it was zero" and "nobody ever measured it" are different
 * answers, and on this screen that difference is the whole downtime
 * contract. What matters is the SEMANTICS, not which dash the view uses —
 * the spec writes an em dash and the implementation renders an en dash —
 * so these tests extract the rendered value and assert it is not zero and
 * not a number, rather than matching a glyph that would break on a purely
 * typographic change.
 *
 * ----------------------------------------------------------------------
 * ON THE ABSENCE OF THRESHOLD FLAGGING, AND THE ONE WORD THAT IS ALLOWED
 * ----------------------------------------------------------------------
 * Clarification has NO operational-target master, so nothing on this page
 * is flagged out of range and the absence is asserted BY NAME. One
 * deliberate exception: the CSS class `md-threshold` is the shared
 * explanatory-note box, and this screen uses it precisely to STATE that
 * nothing is flagged (data-testid="tank-gap-note") and to explain unfilled
 * slots (data-testid="low-coverage-emphasis"). So the markup assertions
 * name the flagging testids and colour classes — threshold-card,
 * outlier-badge, iqr-summary, danger-indicator, is-danger, is-warning,
 * text-red, bg-red, md-trendchart__col--low/--high — and additionally
 * assert that the only `threshold`-flavoured strings present are those two
 * note boxes. The bare-substring form of that rule is asserted where it
 * genuinely holds, on the JSON payload, in
 * tests/Unit/Services/ClarificationReportServiceTest.php case 26 and
 * tests/Feature/Api/LaporanClarificationTest.php scenario 21.
 */

use App\Enums\UserRole;
use App\Livewire\Dashboard\LaporanClarification;
use App\Models\BusinessUnit;
use App\Models\ClarificationDetail;
use App\Models\ClarificationRecord;
use App\Models\Period;
use App\Models\Station;
use App\Models\User;
use App\Services\ClarificationRecordService;
use Livewire\Livewire;

/**
 * One clarification_records header plus one clarification_details row per
 * entry of $rows, with `time_slot` filled in from the canonical grid by
 * position.
 *
 * @param  list<array<string, mixed>>  $rows
 */
function laporanClarificationComponentRecord(Station $station, string $date, array $rows = [], array $overrides = []): ClarificationRecord
{
    $record = ClarificationRecord::factory()->forStation($station)->onDate($date)->create(array_merge([
        'clarification_id' => 'CLF-01',
        'note' => null,
    ], $overrides));

    $slots = ClarificationRecordService::canonicalTimeSlots();

    foreach (array_values($rows) as $index => $row) {
        ClarificationDetail::factory()->forRecord($record)->create(array_merge([
            'time_slot' => $slots[$index % count($slots)],
        ], $row));
    }

    return $record;
}

/**
 * $count rows each filling ONLY sludge_tank_temp_c — filled rows that carry
 * NO production rate.
 *
 * @return list<array<string, mixed>>
 */
function laporanClarificationComponentSludgeRows(int $count, float $temp = 87.0, array $extra = []): array
{
    $rows = [];

    for ($index = 0; $index < $count; $index++) {
        $rows[] = array_merge(['sludge_tank_temp_c' => $temp], $extra);
    }

    return $rows;
}

/**
 * TEN filled rows of which only FOUR carry a rate (10,0 / 12,5 / 8,0 / 9,5
 * = 40,0), every metric filled a different number of times: clarification
 * 7, oil 5, sludge 9, buffer 3, rate 4, downtime 6.
 *
 * @return list<array<string, mixed>>
 */
function laporanClarificationComponentMixedRows(): array
{
    $rates = [10.0, 12.5, 8.0, 9.5];
    $rows = [];

    for ($index = 0; $index < 10; $index++) {
        $rows[] = [
            'clarification_tank_temp_c' => $index <= 6 ? 93.0 : null,
            'oil_tank_temperature_c' => $index <= 4 ? 97.0 : null,
            'sludge_tank_temp_c' => $index >= 1 ? 87.0 : null,
            'buffer_tank_level_percent' => $index <= 2 ? 72.0 : null,
            'pure_oil_production_rate_ton_hour' => $index <= 3 ? $rates[$index] : null,
            'downtime_mins' => $index <= 5 ? 10.0 : null,
        ];
    }

    return $rows;
}

/**
 * The text rendered inside the element carrying $testid — used instead of
 * matching a dash glyph, so "unavailable, not zero" is asserted as
 * semantics rather than typography.
 */
function laporanClarificationRendered(string $html, string $testid): ?string
{
    if (preg_match('/data-testid="'.preg_quote($testid, '/').'"[^>]*>(.*?)</s', $html, $matches)) {
        return trim(html_entity_decode($matches[1]));
    }

    return null;
}

/** The four metric cards of the second KPI row, by their data-testid stem. */
const LAPORAN_CLARIFICATION_METRIC_CARDS = [
    'clarification-temp', 'oil-temp', 'sludge-temp', 'buffer-level',
];

/** Every numeric block the report renders — used by the "no figures" cases. */
const LAPORAN_CLARIFICATION_FIGURE_TESTIDS = [
    'recording-coverage', 'summary-production-total', 'summary-production-reading-count',
    'production-rate-avg', 'summary-downtime-total', 'summary-downtime-reading-count',
    'sludge-temp-avg', 'buffer-level-summary', 'tank-temperature-chart',
    'daily-trend', 'by-unit-table', 'daily-recap-table',
];

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->clarification()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->clarification()->create();

    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('clarification')
        ->range('2026-03-01', '2026-03-31')
        ->named('Periode Maret Alpha')
        ->open()
        ->create();
});

// =====================================================================
// Scenario 1: "berhasil sebagai Supervisor atau Mill Management"
// =====================================================================
it('berhasil: no mill picker, the mill caption, production beside its reading count, and every section', function () {
    laporanClarificationComponentRecord($this->stationA, '2026-03-02', [
        ['clarification_tank_temp_c' => 93.0, 'oil_tank_temperature_c' => 97.0, 'sludge_tank_temp_c' => 87.0,
            'buffer_tank_level_percent' => 72.0, 'pure_oil_production_rate_ton_hour' => 10.0, 'downtime_mins' => 6.0],
        ['clarification_tank_temp_c' => 95.0, 'oil_tank_temperature_c' => 99.0, 'sludge_tank_temp_c' => 89.0,
            'buffer_tank_level_percent' => 74.0, 'pure_oil_production_rate_ton_hour' => 12.0, 'downtime_mins' => 4.0],
    ], ['clarification_id' => 'CLF-01']);

    laporanClarificationComponentRecord($this->stationA, '2026-03-03', [
        ['clarification_tank_temp_c' => 91.0, 'oil_tank_temperature_c' => 95.0, 'sludge_tank_temp_c' => 85.0,
            'buffer_tank_level_percent' => 70.0, 'pure_oil_production_rate_ton_hour' => 8.0, 'downtime_mins' => 10.0],
    ], ['clarification_id' => 'CLF-02']);

    foreach ([$this->supervisor, $this->millManagement] as $user) {
        $component = Livewire::actingAs($user)
            ->test(LaporanClarification::class)
            // The newest period is auto-selected, so the page is useful on
            // first paint rather than demanding a choice first.
            ->assertSet('periodId', (string) $this->periodA->id)
            // Offering a picker they cannot use would be a lie.
            ->assertDontSeeHtml('data-testid="mill-selector"')
            ->assertSeeHtml('data-testid="mill-name"')
            ->assertSee('Mill Alpha')
            ->assertSeeHtml('data-testid="period-selector"')
            ->assertSeeHtml('data-testid="recording-coverage"')
            ->assertSeeHtml('data-testid="summary-production-total"')
            ->assertSeeHtml('data-testid="summary-production-reading-count"')
            ->assertSeeHtml('data-testid="summary-downtime-total"')
            ->assertSeeHtml('data-testid="tank-temperature-chart"')
            ->assertSeeHtml('data-testid="buffer-level-summary"')
            ->assertSeeHtml('data-testid="daily-trend"')
            ->assertSeeHtml('data-testid="by-unit-table"')
            ->assertSeeHtml('data-testid="daily-recap-table"')
            ->assertSeeHtml('data-testid="export-csv-button"')
            // Another mill's identity never appears.
            ->assertDontSee('Mill Beta')
            ->assertDontSeeHtml('data-testid="no-data-notice"');

        $html = $component->html();

        // EXACTLY ONE temperature chart: the three tanks share one axis
        // because what is read is the GAP between them.
        expect(substr_count($html, 'data-testid="tank-temperature-chart"'))->toBe(1);

        // No write-flavoured control anywhere.
        foreach (['save-button', 'edit-button', 'delete-button', 'add-row-button', 'remove-row-button'] as $writeish) {
            expect($html)->not->toContain('data-testid="'.$writeish.'"');
        }

        foreach (LAPORAN_CLARIFICATION_METRIC_CARDS as $card) {
            $component->assertSeeHtml('data-testid="'.$card.'-avg"');
            $component->assertSeeHtml('data-testid="'.$card.'-reading-count"');
        }

        // 10,0 + 12,0 + 8,0 = 30,0 ton over 3 rate readings.
        $component->assertViewHas('summary', fn ($summary) => $summary['production']['total_ton'] === 30.0
            && $summary['production']['reading_count'] === 3
            && $summary['downtime']['total_mins'] === 20
            && $summary['coverage']['filled_slots'] === 3
            && count($summary['by_unit']) === 2);
    }
});

// =====================================================================
// Scenario 2: "berhasil sebagai Admin"
// =====================================================================
it('admin: the mill picker is rendered, the period list follows the chosen mill, and every block is identical', function () {
    laporanClarificationComponentRecord($this->stationA, '2026-03-10', [
        ['clarification_tank_temp_c' => 93.0, 'oil_tank_temperature_c' => 97.0, 'sludge_tank_temp_c' => 87.0,
            'buffer_tank_level_percent' => 72.0, 'pure_oil_production_rate_ton_hour' => 10.0, 'downtime_mins' => 6.0],
    ]);

    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('clarification')
        ->range('2026-03-01', '2026-03-31')->named('Periode Maret Beta')->open()->create();

    $component = Livewire::actingAs($this->admin)
        ->test(LaporanClarification::class)
        // Admin is the one role not bound to a mill, so it gets the picker.
        ->assertSeeHtml('data-testid="mill-selector"')
        ->assertSeeHtml('data-testid="mill-required-hint"')
        ->set('businessUnitId', (string) $this->businessUnitA->id);

    // The period list refreshed to the chosen mill, and the newest one of
    // THAT mill was selected.
    $component->assertSet('periodId', (string) $this->periodA->id);
    $component->assertViewHas('periods', fn ($periods) => array_column($periods, 'id') === [(string) $this->periodA->id]);
    expect(array_column($component->viewData('periods'), 'id'))->not->toContain((string) $periodB->id);

    $component
        ->assertSeeHtml('data-testid="mill-selector"')
        ->assertSeeHtml('data-testid="recording-coverage"')
        ->assertSeeHtml('data-testid="summary-production-total"')
        ->assertSeeHtml('data-testid="summary-production-reading-count"')
        ->assertSeeHtml('data-testid="summary-downtime-total"')
        ->assertSeeHtml('data-testid="tank-temperature-chart"')
        ->assertSeeHtml('data-testid="daily-trend"')
        ->assertSeeHtml('data-testid="by-unit-table"')
        ->assertSeeHtml('data-testid="daily-recap-table"')
        ->assertSeeHtml('data-testid="export-csv-button"')
        ->assertDontSeeHtml('data-testid="mill-required-hint"');

    foreach (LAPORAN_CLARIFICATION_METRIC_CARDS as $card) {
        $component->assertSeeHtml('data-testid="'.$card.'-avg"');
        $component->assertSeeHtml('data-testid="'.$card.'-reading-count"');
    }
});

// =====================================================================
// Scenario 3: "Admin memilih mill lebih dulu"
// =====================================================================
it('admin tanpa mill: the picker and the hint are shown, and not one report figure is rendered', function () {
    laporanClarificationComponentRecord($this->stationA, '2026-03-10', [
        ['pure_oil_production_rate_ton_hour' => 10.0],
    ]);

    $component = Livewire::actingAs($this->admin)
        ->test(LaporanClarification::class)
        ->assertSeeHtml('data-testid="mill-selector"')
        ->assertSeeHtml('data-testid="mill-required-hint"')
        // The page asks for a mill instead of drawing an empty report that
        // would read as "this mill has no data".
        ->assertDontSeeHtml('data-testid="summary-production-total"')
        ->assertDontSeeHtml('data-testid="tank-temperature-chart"')
        ->assertDontSeeHtml('data-testid="recording-coverage"')
        ->assertDontSeeHtml('data-testid="period-selector"')
        ->assertDontSeeHtml('data-testid="export-csv-button"')
        ->assertViewHas('summary', null);

    $html = $component->html();

    foreach (LAPORAN_CLARIFICATION_FIGURE_TESTIDS as $testid) {
        expect($html)->not->toContain('data-testid="'.$testid.'"');
    }
});

// =====================================================================
// Scenario 4: "mill belum punya periode"
// =====================================================================
it('mill tanpa periode: the period picker has no option at all and the contact-Admin hint is shown', function () {
    $supervisorB = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitB)->create();

    $component = Livewire::actingAs($supervisorB)->test(LaporanClarification::class);

    $component
        ->assertSeeHtml('data-testid="period-selector"')
        ->assertSeeHtml('data-testid="no-period-hint"')
        ->assertSee('Kelola Periode Pelaporan')
        ->assertSet('periodId', '')
        ->assertDontSeeHtml('data-testid="summary-production-total"')
        ->assertDontSeeHtml('data-testid="recording-coverage"')
        ->assertDontSeeHtml('data-testid="by-unit-table"')
        ->assertDontSeeHtml('data-testid="tank-temperature-chart"');

    // Rendered deliberately WITHOUT a placeholder option — an empty picker,
    // not a fake "belum ada periode" entry.
    expect($component->html())->not->toContain('<option value="');
});

// =====================================================================
// Scenario 5: "periode tanpa data"
// =====================================================================
it('periode tanpa data: the empty notice appears, every figure reads as unavailable rather than 0, and no chart is drawn', function () {
    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanClarification::class)
        ->assertSet('periodId', (string) $this->periodA->id)
        ->assertSeeHtml('data-testid="no-data-notice"')
        // An empty chart would read as a measured flat line.
        ->assertDontSeeHtml('data-testid="tank-temperature-chart"')
        ->assertDontSeeHtml('data-testid="daily-trend"')
        ->assertDontSeeHtml('data-testid="by-unit-table"')
        ->assertDontSeeHtml('data-testid="daily-recap-table"');

    $html = $component->html();

    // UNAVAILABLE, NEVER 0 — asserted as semantics, not as a dash glyph.
    foreach (['summary-production-total', 'production-rate-avg'] as $testid) {
        $value = laporanClarificationRendered($html, $testid);

        expect($value)->not->toBeNull();
        expect($value)->not->toBe('0');
        expect($value)->not->toBe('0,0');
        expect($value)->not->toBe('0,00');
        expect($value)->not->toMatch('/^\d/');
    }

    foreach (LAPORAN_CLARIFICATION_METRIC_CARDS as $card) {
        $value = laporanClarificationRendered($html, $card.'-avg');

        expect($value)->not->toBe('0');
        expect($value)->not->toBe('0,0');
        expect($value)->not->toMatch('/^\d/');
        // The denominator IS zero, and says so — that is the other half of
        // the distinction.
        expect(laporanClarificationRendered($html, $card.'-reading-count'))->toBe('0');
    }

    // Downtime spells it out in words rather than printing a 0.
    expect(laporanClarificationRendered($html, 'summary-downtime-total'))->toBe('tidak tercatat');
    expect(laporanClarificationRendered($html, 'summary-downtime-reading-count'))->toBe('0');

    $component->assertViewHas('summary', fn ($summary) => $summary['has_data'] === false
        && $summary['production']['total_ton'] === null
        && $summary['downtime']['total_mins'] === null);
});

// =====================================================================
// Scenario 6: "laju produksi tidak pernah tercatat"
// =====================================================================
it('laju tidak tercatat: the production card reads unavailable with 0 readings while sludge keeps its own 9', function () {
    laporanClarificationComponentRecord($this->stationA, '2026-03-07', laporanClarificationComponentSludgeRows(9, 87.0));

    $html = Livewire::actingAs($this->supervisor)->test(LaporanClarification::class)->html();

    $production = laporanClarificationRendered($html, 'summary-production-total');

    expect($production)->not->toBe('0');
    expect($production)->not->toBe('0,0');
    expect($production)->not->toMatch('/^\d/');
    expect(laporanClarificationRendered($html, 'summary-production-reading-count'))->toBe('0');

    // Independent per metric — the sludge card is entirely unaffected.
    expect(laporanClarificationRendered($html, 'sludge-temp-avg'))->toBe('87,0');
    expect(laporanClarificationRendered($html, 'sludge-temp-reading-count'))->toBe('9');
});

// =====================================================================
// Scenario 7: "jam tanpa catatan laju"
// =====================================================================
it('jam tanpa laju: 40,0 ton over 4 readings with an average of 10,00 — never 4,00 — beside 10 filled slots', function () {
    laporanClarificationComponentRecord($this->stationA, '2026-03-08', laporanClarificationComponentMixedRows());

    $component = Livewire::actingAs($this->supervisor)->test(LaporanClarification::class);
    $html = $component->html();

    expect(laporanClarificationRendered($html, 'summary-production-total'))->toBe('40,0');
    expect(laporanClarificationRendered($html, 'summary-production-reading-count'))->toBe('4');

    // 40,0 / 4 = 10,00. A zero-filling implementation would render 4,00 —
    // a perfectly plausible rate, which is precisely the danger.
    expect(laporanClarificationRendered($html, 'production-rate-avg'))->toBe('10,00');
    expect(laporanClarificationRendered($html, 'production-rate-avg'))->not->toBe('4,00');

    // The coverage card shows the ten filled slots the four readings came
    // out of — the two numbers disagree ON PURPOSE and both are rendered.
    $component->assertSeeHtml('data-testid="recording-coverage"');
    expect(laporanClarificationRendered($html, 'coverage-filled-slots'))->toBe('10');
});

// =====================================================================
// Scenario 8: "downtime tercatat bersamaan dengan laju"
// =====================================================================
it('downtime bersama laju: 10,0 ton and 20 menit side by side, with the note saying they do not offset', function () {
    laporanClarificationComponentRecord($this->stationA, '2026-03-09', [
        ['pure_oil_production_rate_ton_hour' => 10.0, 'downtime_mins' => 20.0],
    ]);

    $component = Livewire::actingAs($this->supervisor)->test(LaporanClarification::class);
    $html = $component->html();

    // >> The non-subtracting formula is STILL PENDING the process owner —
    // >> this asserts current behaviour and does not decide the question.
    expect(laporanClarificationRendered($html, 'summary-production-total'))->toBe('10,0');
    expect(laporanClarificationRendered($html, 'summary-downtime-total'))->toBe('20');

    // Without this note the two numbers look like a contradiction.
    $component->assertSeeHtml('data-testid="downtime-production-note"');
    $component->assertSee('tidak saling mengurangi');
});

// =====================================================================
// Scenario 9: "downtime tidak pernah tercatat"
// =====================================================================
it('downtime null vs nol: the two periods render differently, and the reading count tells them apart', function () {
    $recordedZero = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('clarification')
        ->range('2026-06-01', '2026-06-30')->named('Periode Juni Downtime Nol')->open()->create();

    laporanClarificationComponentRecord($this->stationA, '2026-03-10', laporanClarificationComponentSludgeRows(5, 87.0));

    $zeroRows = [];

    for ($index = 0; $index < 5; $index++) {
        $zeroRows[] = ['downtime_mins' => 0.0];
    }

    laporanClarificationComponentRecord($this->stationA, '2026-06-10', $zeroRows, ['clarification_id' => 'CLF-JUN']);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanClarification::class)
        ->set('periodId', (string) $this->periodA->id);

    $neverHtml = $component->html();

    // "tidak tercatat" — deliberately words, not a 0.
    expect(laporanClarificationRendered($neverHtml, 'summary-downtime-total'))->toBe('tidak tercatat');
    expect(laporanClarificationRendered($neverHtml, 'summary-downtime-reading-count'))->toBe('0');

    $component->set('periodId', (string) $recordedZero->id);

    $zeroHtml = $component->html();

    expect(laporanClarificationRendered($zeroHtml, 'summary-downtime-total'))->toBe('0');
    expect(laporanClarificationRendered($zeroHtml, 'summary-downtime-reading-count'))->toBe('5');

    // THE TWO TEXTS DIFFER — which is the contract, not an accident.
    expect(laporanClarificationRendered($neverHtml, 'summary-downtime-total'))
        ->not->toBe(laporanClarificationRendered($zeroHtml, 'summary-downtime-total'));
    expect(laporanClarificationRendered($neverHtml, 'summary-downtime-reading-count'))
        ->not->toBe(laporanClarificationRendered($zeroHtml, 'summary-downtime-reading-count'));
});

// =====================================================================
// Scenario 10: "pencatatan sangat tidak lengkap"
// =====================================================================
it('kelengkapan rendah: 9 of 144 is rendered in the main block above the figures, which still appear', function () {
    $period = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('clarification')
        ->range('2026-04-01', '2026-04-03')->named('Periode Tiga Hari')->open()->create();

    laporanClarificationComponentRecord($this->stationA, '2026-04-01', [
        ['pure_oil_production_rate_ton_hour' => 10.0],
        ['pure_oil_production_rate_ton_hour' => 12.0],
        ['pure_oil_production_rate_ton_hour' => 8.0],
        ['sludge_tank_temp_c' => 87.0],
        ['sludge_tank_temp_c' => 87.0],
        ['sludge_tank_temp_c' => 87.0],
        ['sludge_tank_temp_c' => 87.0],
        ['sludge_tank_temp_c' => 87.0],
        ['sludge_tank_temp_c' => 87.0],
    ], ['clarification_id' => 'CLF-01']);
    laporanClarificationComponentRecord($this->stationA, '2026-04-02', [], ['clarification_id' => 'CLF-02']);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanClarification::class)
        ->set('periodId', (string) $period->id)
        ->assertSeeHtml('data-testid="recording-coverage"')
        ->assertSeeHtml('data-testid="low-coverage-emphasis"');

    $html = $component->html();

    expect(laporanClarificationRendered($html, 'coverage-filled-slots'))->toBe('9');
    expect(laporanClarificationRendered($html, 'coverage-expected-slots'))->toBe('144');
    expect(laporanClarificationRendered($html, 'coverage-percent'))->toBe('6,3%');

    // THE COVERAGE CARD IS PART OF THE REPORT BODY, NOT A FOOTNOTE: on this
    // screen a gap in the recording lowers the derived production figure
    // itself, so the card is rendered ABOVE every number.
    expect(strpos($html, 'data-testid="recording-coverage"'))
        ->toBeLessThan(strpos($html, 'data-testid="summary-production-total"'));
    expect(strpos($html, 'data-testid="recording-coverage"'))
        ->toBeLessThan(strpos($html, 'data-testid="report-metrics"'));

    // And the production figure is still shown, beside its reading count.
    expect(laporanClarificationRendered($html, 'summary-production-total'))->toBe('30,0');
    expect(laporanClarificationRendered($html, 'summary-production-reading-count'))->toBe('3');
});

// =====================================================================
// Scenario 11: "mill punya beberapa unit Clarification"
// =====================================================================
it('beberapa unit: three rows in the per-unit table, the readingless one among them with 0 readings', function () {
    laporanClarificationComponentRecord($this->stationA, '2026-03-02', [
        ['pure_oil_production_rate_ton_hour' => 20.0],
    ], ['clarification_id' => 'CLF-01']);
    laporanClarificationComponentRecord($this->stationA, '2026-03-02', [
        ['pure_oil_production_rate_ton_hour' => 15.0],
    ], ['clarification_id' => 'CLF-02']);
    laporanClarificationComponentRecord($this->stationA, '2026-03-03', [[], []], ['clarification_id' => 'CLF-03']);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanClarification::class)
        ->assertSeeHtml('data-testid="by-unit-table"')
        ->assertSeeHtml('data-testid="by-unit-row-CLF-01"')
        ->assertSeeHtml('data-testid="by-unit-row-CLF-02"')
        // Dropping it would hide exactly the unit that was never written
        // down — the one worth seeing.
        ->assertSeeHtml('data-testid="by-unit-row-CLF-03"');

    $html = $component->html();

    expect(laporanClarificationRendered($html, 'summary-production-total'))->toBe('35,0');

    $component->assertViewHas('summary', fn ($summary) => count($summary['by_unit']) === 3
        && collect($summary['by_unit'])->firstWhere('clarification_id', 'CLF-03')['reading_count'] === 0
        && collect($summary['by_unit'])->firstWhere('clarification_id', 'CLF-03')['production_ton'] === null);
});

// =====================================================================
// Scenario 12: "akun belum terhubung ke mill"
// =====================================================================
it('akun tanpa mill: the contact-Admin notice, NO mill picker at all, and no figures', function () {
    $noMillSupervisor = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    $component = Livewire::actingAs($noMillSupervisor)
        ->test(LaporanClarification::class)
        ->assertSeeHtml('data-testid="no-mill-hint"')
        ->assertSee('Hubungi Admin')
        // FAIL CLOSED: offering the whole-mill list to a role that is
        // supposed to be tied to one mill turns broken master data into a
        // cross-mill leak.
        ->assertDontSeeHtml('data-testid="mill-selector"')
        ->assertDontSeeHtml('data-testid="period-selector"');

    $html = $component->html();

    foreach (LAPORAN_CLARIFICATION_FIGURE_TESTIDS as $testid) {
        expect($html)->not->toContain('data-testid="'.$testid.'"');
    }
});

// =====================================================================
// Scenario 13: "mencoba melihat mill lain"
// =====================================================================
it('mill lain: forcing businessUnitId changes nothing, and another mill period is refused visibly', function () {
    laporanClarificationComponentRecord($this->stationA, '2026-03-10', [
        ['pure_oil_production_rate_ton_hour' => 20.0],
    ]);
    laporanClarificationComponentRecord($this->stationB, '2026-03-10', [
        ['pure_oil_production_rate_ton_hour' => 900.0],
    ], ['clarification_id' => 'CLF-BETA']);

    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('clarification')
        ->range('2026-03-01', '2026-03-31')->named('Periode Maret Beta')->open()->create();

    // Step 1 — the mill is not negotiable from the UI for a bound role:
    // resolvedBusinessUnitId() never consults $businessUnitId for them.
    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanClarification::class)
        ->set('businessUnitId', (string) $this->businessUnitB->id)
        ->assertSeeHtml('data-testid="mill-name"')
        ->assertSee('Mill Alpha')
        ->assertDontSee('Mill Beta')
        // Mill B's 900,0 must not reach the by-unit table even while the
        // header still says Mill Alpha and the total below is still 20,0 —
        // a by-unit-only leak is exactly the case that assertDontSee('Mill
        // Beta') and the '20,0' comparison both miss, so this assertion is
        // load-bearing and is kept, not dropped. It is asserted on the row
        // testid rather than as assertDontSee('900'): a bare 3-digit
        // substring collides with the random UUIDs rendered into <option
        // value="...">, see the rationale at Scenario 19 below. The testid
        // form is also stronger — it fires on a leaked CLF-BETA row
        // whatever its figures happen to be.
        ->assertDontSeeHtml('data-testid="by-unit-row-CLF-BETA"');

    expect(laporanClarificationRendered($component->html(), 'summary-production-total'))->toBe('20,0');

    $component->assertViewHas('summary', fn ($summary) => $summary['business_unit']['name'] === 'Mill Alpha'
        && $summary['production']['total_ton'] === 20.0);

    // Step 2 — hand-forcing another mill's PERIOD is refused visibly, with
    // no figure of that mill rendered. Different from the silently ignored
    // business_unit_id: here there IS a concrete handle to another mill.
    $component->set('periodId', (string) $periodB->id)
        ->assertSeeHtml('data-testid="forbidden-notice"')
        ->assertDontSee('Periode Maret Beta')
        ->assertDontSeeHtml('data-testid="summary-production-total"')
        ->assertDontSeeHtml('data-testid="by-unit-table"');

    // "no figure of that mill rendered" asserted as the absence of EVERY
    // numeric block — the same form Scenarios 12 and 14 use — instead of
    // assertDontSee('900'), whose bare 3-digit substring collides with the
    // random UUIDs rendered into <option value="...">; see the rationale at
    // Scenario 19 below. Strictly stronger than the numeric form: it also
    // covers the figures that do not contain "900" (daily recap, trend,
    // coverage, the metric cards), which the two testids above leave open.
    $html = $component->html();

    foreach (LAPORAN_CLARIFICATION_FIGURE_TESTIDS as $testid) {
        expect($html)->not->toContain('data-testid="'.$testid.'"');
    }
});

// =====================================================================
// Scenario 14: "Operator mencoba membuka layar web ini"
// =====================================================================
it('operator: the route refuses before mount, and mount() itself refuses too', function () {
    // Route layer — EnsureRole::forbidden() -> abort(403).
    $response = $this->actingAs($this->operator, 'web')->get('/reports/clarification');
    $response->assertForbidden();
    $response->assertDontSee('Laporan Periode');

    // Component layer — mount()'s abort_unless(403) covers the component
    // being mounted directly, which is exactly how this scenario exercises
    // it. There is NO Operator widening here: the mobile Clarification
    // report is screen-138 and has not been built.
    $html = Livewire::actingAs($this->operator)->test(LaporanClarification::class)->html();

    expect($html)->toContain('Forbidden');
    expect($html)->not->toContain('data-testid="laporan-clarification"');

    foreach (LAPORAN_CLARIFICATION_FIGURE_TESTIDS as $testid) {
        expect($html)->not->toContain('data-testid="'.$testid.'"');
    }
});

// =====================================================================
// Scenario 15: "periode tertutup"
// =====================================================================
it('periode tertutup: the status is a caption, the report is complete and Ekspor is never disabled', function () {
    $closed = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('clarification')
        ->range('2026-05-01', '2026-05-31')->named('Periode Mei Tertutup')->closed()->create();

    laporanClarificationComponentRecord($this->stationA, '2026-05-10', [
        ['clarification_tank_temp_c' => 93.0, 'oil_tank_temperature_c' => 97.0, 'sludge_tank_temp_c' => 87.0,
            'buffer_tank_level_percent' => 72.0, 'pure_oil_production_rate_ton_hour' => 10.0, 'downtime_mins' => 6.0],
        ['clarification_tank_temp_c' => 95.0, 'oil_tank_temperature_c' => 99.0, 'sludge_tank_temp_c' => 89.0,
            'buffer_tank_level_percent' => 74.0, 'pure_oil_production_rate_ton_hour' => 12.0, 'downtime_mins' => 4.0],
    ], ['clarification_id' => 'CLF-MEI']);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanClarification::class)
        ->set('periodId', (string) $closed->id)
        ->assertSeeHtml('data-testid="period-status"')
        ->assertSee('Tertutup')
        ->assertSeeHtml('data-testid="recording-coverage"')
        ->assertSeeHtml('data-testid="summary-production-total"')
        ->assertSeeHtml('data-testid="by-unit-table"')
        ->assertSeeHtml('data-testid="daily-recap-table"')
        // The period lock governs writing data, not reading a report.
        ->assertSeeHtml('data-testid="export-csv-button"')
        ->assertDontSeeHtml('disabled');

    $component->call('exportCsv', 'csv')->assertFileDownloaded(null, null, 'text/csv');
});

// =====================================================================
// Scenario 16: "rekap harian panjang"
// =====================================================================
it('rekap panjang: the toggle hides the recap on the first call and brings it back on the second, cards untouched', function () {
    foreach (range(1, 20) as $day) {
        laporanClarificationComponentRecord($this->stationA, sprintf('2026-03-%02d', $day), [
            ['pure_oil_production_rate_ton_hour' => 10.0 + $day, 'sludge_tank_temp_c' => 80.0 + $day,
                'clarification_tank_temp_c' => 90.0 + $day, 'oil_tank_temperature_c' => 95.0 + $day],
        ], ['clarification_id' => 'CLF-01']);
    }

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanClarification::class)
        ->assertSet('dailyRecapOpen', true)
        ->assertSeeHtml('data-testid="daily-recap-toggle"')
        // OPEN on first render.
        ->assertSeeHtml('data-testid="daily-recap-table"')
        ->assertSeeHtml('data-testid="summary-production-total"')
        ->assertSeeHtml('data-testid="daily-trend"');

    // First toggle — the table leaves the DOM entirely, rather than being
    // merely collapsed (which is why this is a button and a Livewire
    // property, not a <details>).
    $component->call('toggleDailyRecap')
        ->assertSet('dailyRecapOpen', false)
        ->assertDontSeeHtml('data-testid="daily-recap-table"')
        ->assertSeeHtml('data-testid="daily-recap-toggle"')
        // The headline figures and the trend survive both states.
        ->assertSeeHtml('data-testid="summary-production-total"')
        ->assertSeeHtml('data-testid="daily-trend"');

    // Second toggle — back again, with dozens of rows.
    $component->call('toggleDailyRecap')
        ->assertSet('dailyRecapOpen', true)
        ->assertSeeHtml('data-testid="daily-recap-table"')
        ->assertSeeHtml('data-testid="summary-production-total"')
        ->assertSeeHtml('data-testid="daily-trend"');

    // One row per dated record, plus the separate total row in the tfoot.
    expect(substr_count($component->html(), 'data-testid="daily-recap-row-2026-'))->toBe(20);
    expect(substr_count($component->html(), 'data-testid="daily-recap-row-total"'))->toBe(1);
    $component->assertViewHas('summary', fn ($summary) => count($summary['daily']) === 20);
});

// =====================================================================
// Scenario 17: "produksi diturunkan dari laju dan jumlah pembacaannya ditampilkan"
// =====================================================================
it('produksi turunan: 40,0 ton and its reading count 4 render INSIDE the same card, beside each other', function () {
    laporanClarificationComponentRecord($this->stationA, '2026-03-11', [
        ['pure_oil_production_rate_ton_hour' => 10.0],
        ['pure_oil_production_rate_ton_hour' => 12.5],
        ['pure_oil_production_rate_ton_hour' => 8.0],
        ['pure_oil_production_rate_ton_hour' => 9.5],
    ]);

    $component = Livewire::actingAs($this->supervisor)->test(LaporanClarification::class);
    $html = $component->html();

    expect(laporanClarificationRendered($html, 'summary-production-total'))->toBe('40,0');
    expect(laporanClarificationRendered($html, 'summary-production-reading-count'))->toBe('4');

    // SAME CARD, DIRECTLY BESIDE THE NUMBER — not a page footnote. A total
    // from 4 readings and a total from 400 must not look equally convincing,
    // and a denominator the reader has to hunt for does not do that job.
    $cardStart = strpos($html, 'data-testid="production-card"');
    $cardEnd = strpos($html, 'data-testid="downtime-card"');

    expect($cardStart)->not->toBeFalse();
    expect($cardEnd)->toBeGreaterThan($cardStart);

    $card = substr($html, $cardStart, $cardEnd - $cardStart);

    expect($card)->toContain('data-testid="summary-production-total"');
    expect($card)->toContain('data-testid="summary-production-reading-count"');
    expect($card)->toContain('data-testid="production-derived-note"');

    $component->assertSee('Diturunkan, bukan dicatat');
});

// =====================================================================
// Scenario 18: "setiap metrik punya penyebutnya sendiri"
// =====================================================================
it('penyebut terpisah: each metric card renders its own reading count — 4, 9, 6 and 3 — with no global label', function () {
    laporanClarificationComponentRecord($this->stationA, '2026-03-12', laporanClarificationComponentMixedRows());

    $html = Livewire::actingAs($this->supervisor)->test(LaporanClarification::class)->html();

    expect(laporanClarificationRendered($html, 'summary-production-reading-count'))->toBe('4');
    expect(laporanClarificationRendered($html, 'sludge-temp-reading-count'))->toBe('9');
    expect(laporanClarificationRendered($html, 'summary-downtime-reading-count'))->toBe('6');
    expect(laporanClarificationRendered($html, 'buffer-level-reading-count'))->toBe('3');
    expect(laporanClarificationRendered($html, 'clarification-temp-reading-count'))->toBe('7');
    expect(laporanClarificationRendered($html, 'oil-temp-reading-count'))->toBe('5');

    // Each average consistent with ITS OWN denominator.
    expect(laporanClarificationRendered($html, 'production-rate-avg'))->toBe('10,00');
    expect(laporanClarificationRendered($html, 'sludge-temp-avg'))->toBe('87,0');

    // Every card carries its own count, and there is no single global
    // reading-count label anywhere.
    foreach (LAPORAN_CLARIFICATION_METRIC_CARDS as $card) {
        expect($html)->toContain('data-testid="'.$card.'-reading-count"');
    }

    expect($html)->not->toContain('data-testid="reading-count"');
    expect($html)->not->toContain('data-testid="total-reading-count"');
});

// =====================================================================
// Scenario 19: "rentang periode inklusif di kedua ujung"
// =====================================================================
it('rentang inklusif: the daily recap carries both bound dates and neither of the two outside them', function () {
    $period = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('clarification')
        ->range('2026-03-01', '2026-03-05')->named('Periode Awal Maret')->open()->create();

    laporanClarificationComponentRecord($this->stationA, '2026-03-01', [
        ['pure_oil_production_rate_ton_hour' => 10.0],
    ], ['clarification_id' => 'CLF-01']);
    laporanClarificationComponentRecord($this->stationA, '2026-03-05', [
        ['pure_oil_production_rate_ton_hour' => 20.0],
    ], ['clarification_id' => 'CLF-01']);
    laporanClarificationComponentRecord($this->stationA, '2026-02-28', [
        ['pure_oil_production_rate_ton_hour' => 999.0],
    ], ['clarification_id' => 'CLF-OUT-A']);
    laporanClarificationComponentRecord($this->stationA, '2026-03-06', [
        ['pure_oil_production_rate_ton_hour' => 888.0],
    ], ['clarification_id' => 'CLF-OUT-B']);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanClarification::class)
        ->set('periodId', (string) $period->id)
        ->assertSeeHtml('data-testid="daily-recap-row-2026-03-01"')
        ->assertSeeHtml('data-testid="daily-recap-row-2026-03-05"')
        // The two out-of-range days must be absent as ROWS. Asserting on the
        // row testids is the only collision-free form here: Period,
        // ClarificationRecord and ClarificationDetail all use HasUuids, and
        // the period UUIDs are rendered into <option value="..."> in the
        // period selector, so a bare assertDontSee('999') / ('888') on the
        // seeded out-of-range rates hit a random UUID substring at roughly
        // 1-in-40 — nondeterministic, with the failing value alternating
        // between runs. Do not restore the bare-substring form.
        ->assertDontSeeHtml('data-testid="daily-recap-row-2026-02-28"')
        ->assertDontSeeHtml('data-testid="daily-recap-row-2026-03-06"');

    // Totals include both ends: 10,0 + 20,0.
    expect(laporanClarificationRendered($component->html(), 'summary-production-total'))->toBe('30,0');
});

// =====================================================================
// Scenario 20: "ketiga suhu tangki pada satu grafik"
// =====================================================================
it('satu grafik: exactly one tank-temperature-chart carries all three series, with no per-tank chart anywhere', function () {
    foreach (['2026-03-15', '2026-03-16', '2026-03-17'] as $index => $date) {
        laporanClarificationComponentRecord($this->stationA, $date, [
            ['clarification_tank_temp_c' => 93.0 + $index, 'oil_tank_temperature_c' => 97.0 + $index,
                'sludge_tank_temp_c' => 87.0 + $index],
        ], ['clarification_id' => 'CLF-01']);
    }

    $component = Livewire::actingAs($this->supervisor)->test(LaporanClarification::class);
    $html = $component->html();

    // EXACTLY ONE chart element. Three separate charts would satisfy "tren
    // suhu antar tangki" literally while destroying its point: what is read
    // is the GAP between the tanks, and a gap needs one shared axis.
    expect(substr_count($html, 'data-testid="tank-temperature-chart"'))->toBe(1);

    foreach (['clarification-temp-chart', 'oil-temp-chart', 'sludge-temp-chart'] as $forbidden) {
        expect($html)->not->toContain('data-testid="'.$forbidden.'"');
    }

    // All three series inside that one chart, distinguished on TWO LAYERS:
    // colour AND dash pattern, so the chart stays readable without relying
    // on colour at all.
    expect(substr_count($html, 'md-lc__line--s1'))->toBe(1);
    expect(substr_count($html, 'md-lc__line--s2'))->toBe(1);
    expect(substr_count($html, 'md-lc__line--s3'))->toBe(1);

    // The legend names all three tanks.
    $component->assertSee('Tangki Clarification');
    $component->assertSee('Tangki Minyak');
    $component->assertSee('Tangki Sludge');
    // ...and names the pattern, not only the colour.
    $component->assertSee('garis utuh');
    $component->assertSee('garis putus');
    $component->assertSee('garis titik');
});

// =====================================================================
// Scenario 20b — THE STYLESHEET HALF OF THE TWO-LAYER DISTINCTION
//
// The md-lc* vocabulary lives in dashboard/partials/report-styles.blade.php
// and is loaded through <x-slot:styles> on the PAGE, not inside the Livewire
// component (Livewire 3 attaches wire:id to the first rendered element, and
// a <style> there kills every wire:model). So the colour+dash rules can only
// be asserted on the full page response.
// =====================================================================
it('gaya grafik: the three series differ by colour AND by dash pattern on the rendered page', function () {
    laporanClarificationComponentRecord($this->stationA, '2026-03-15', [
        ['clarification_tank_temp_c' => 93.0, 'oil_tank_temperature_c' => 97.0, 'sludge_tank_temp_c' => 87.0],
    ]);

    $page = $this->actingAs($this->supervisor, 'web')->get('/reports/clarification');
    $page->assertOk();

    $html = $page->getContent();

    // Layer one — a distinct colour token per series.
    expect($html)->toContain('.md-lc__line--s1 { stroke: var(--md-s1); }');
    expect($html)->toContain('--md-s2');
    expect($html)->toContain('--md-s3');

    // Layer two — a distinct dash pattern, so the three lines stay
    // distinguishable in greyscale and to a colour-blind reader.
    expect($html)->toContain('stroke-dasharray: 7 4');
    expect($html)->toContain('stroke-dasharray: 2 4');

    // And the chart itself is on the page, exactly once.
    expect(substr_count($html, 'data-testid="tank-temperature-chart"'))->toBe(1);
});

// =====================================================================
// Scenario 21: "tidak ada penandaan nilai di luar batas"
// =====================================================================
it('tanpa ambang: extreme values render in the same neutral style, with no badge, icon, label or danger colour', function () {
    laporanClarificationComponentRecord($this->stationA, '2026-03-18', [
        ['sludge_tank_temp_c' => 250.0, 'buffer_tank_level_percent' => 0.5,
            'clarification_tank_temp_c' => 93.0, 'oil_tank_temperature_c' => 97.0,
            'pure_oil_production_rate_ton_hour' => 10.0],
        ['sludge_tank_temp_c' => 87.0, 'buffer_tank_level_percent' => 72.0,
            'clarification_tank_temp_c' => 93.0, 'oil_tank_temperature_c' => 97.0,
            'pure_oil_production_rate_ton_hour' => 10.0],
    ]);

    $html = Livewire::actingAs($this->supervisor)->test(LaporanClarification::class)->html();

    // The extremes ARE rendered — they are simply not judged.
    expect(laporanClarificationRendered($html, 'sludge-temp-max'))->toBe('250,0');
    expect(laporanClarificationRendered($html, 'buffer-level-min'))->toBe('0,5');

    // No flagging element, by name.
    foreach ([
        'threshold-card', 'threshold-badge', 'outlier-badge', 'outlier',
        'iqr-summary', 'iqr', 'danger-indicator', 'alert-label',
        'out-of-range-icon', 'severity', 'status-flag',
    ] as $forbidden) {
        expect($html)->not->toContain('data-testid="'.$forbidden.'"');
    }

    // No warning colour or state class, by name.
    foreach ([
        'is-danger', 'is-warning', 'text-red', 'bg-red', 'text-amber', 'bg-amber',
        'md-chip--danger', 'md-chip--warning',
        'md-trendchart__col--low', 'md-trendchart__col--high',
        'is_out_of_range', 'out_of_range',
    ] as $flavour) {
        expect($html)->not->toContain($flavour);
    }

    // The only `threshold`-flavoured strings on the page are the two shared
    // EXPLANATORY note boxes — one of which exists precisely to say that
    // nothing here is flagged. See the file docblock.
    expect(substr_count($html, 'md-threshold'))
        ->toBe(substr_count($html, 'data-testid="tank-gap-note"') + substr_count($html, 'data-testid="low-coverage-emphasis"'));
    expect(strtolower($html))->not->toContain('ambang batas');

    // THE CARD HOLDING AN EXTREME VALUE CARRIES THE SAME class ATTRIBUTE AS
    // AN ORDINARY ONE — the absence of styling is what makes the "do not
    // flag" rule visible, and only a comparison can assert it.
    preg_match('/<article class="([^"]*)" data-testid="sludge-temp-summary"/', $html, $extreme);
    preg_match('/<article class="([^"]*)" data-testid="clarification-temp-summary"/', $html, $ordinary);

    expect($extreme[1] ?? null)->not->toBeNull();
    expect($ordinary[1] ?? null)->not->toBeNull();
    expect($extreme[1])->toBe($ordinary[1]);
});

// =====================================================================
// Scenario 22: "layar hanya membaca"
// =====================================================================
it('baca saja: no write-flavoured control anywhere, and rendering changes not one row', function () {
    $other = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('clarification')
        ->range('2026-05-01', '2026-05-31')->named('Periode Mei Alpha')->open()->create();

    laporanClarificationComponentRecord($this->stationA, '2026-03-10', laporanClarificationComponentMixedRows());
    laporanClarificationComponentRecord($this->stationA, '2026-05-10', laporanClarificationComponentSludgeRows(3, 87.0), [
        'clarification_id' => 'CLF-MEI',
    ]);

    $recordsBefore = ClarificationRecord::count();
    $detailsBefore = ClarificationDetail::count();
    $checksumBefore = ClarificationDetail::query()->orderBy('id')->get()->toJson();

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanClarification::class)
        ->set('periodId', (string) $this->periodA->id)
        ->set('periodId', (string) $other->id);

    $component->call('exportCsv', 'csv')->assertFileDownloaded(null, null, 'text/csv');

    $html = $component->html();

    foreach (['save-button', 'edit-button', 'delete-button', 'add-row-button', 'remove-row-button-0'] as $writeish) {
        expect($html)->not->toContain('data-testid="'.$writeish.'"');
    }

    // The component's public surface stays a toggle and an export — nothing
    // that reads like a write action.
    preg_match_all('/wire:click="([a-zA-Z]+)/', $html, $matches);
    expect(array_values(array_unique($matches[1])))->toEqualCanonicalizing(['toggleDailyRecap', 'exportCsv']);

    foreach (['save', 'update', 'delete', 'store', 'destroy'] as $writeMethod) {
        expect(method_exists(LaporanClarification::class, $writeMethod))->toBeFalse();
    }

    expect(ClarificationRecord::count())->toBe($recordsBefore);
    expect(ClarificationDetail::count())->toBe($detailsBefore);
    expect(ClarificationDetail::query()->orderBy('id')->get()->toJson())->toBe($checksumBefore);
});

// =====================================================================
// Scenario 23: "daftar periode hanya yang mencakup Clarification"
// =====================================================================
it('pemilih periode: exactly two options — Clarification and all-station-types, never another station type', function () {
    $supervisorB = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitB)->create();

    Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('clarification')
        ->range('2026-05-01', '2026-05-31')->named('Periode Clarification B')->open()->create();
    Period::factory()->forBusinessUnit($this->businessUnitB)->stationType(null)
        ->range('2026-06-01', '2026-06-30')->named('Periode Semua Stasiun B')->open()->create();
    Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('sterilizer')
        ->range('2026-07-01', '2026-07-31')->named('Periode Sterilizer B')->open()->create();
    Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('boiler-room')
        ->range('2026-08-01', '2026-08-31')->named('Periode Boiler Room B')->open()->create();

    $component = Livewire::actingAs($supervisorB)
        ->test(LaporanClarification::class)
        ->assertSee('Periode Clarification B')
        ->assertSee('Periode Semua Stasiun B')
        ->assertDontSee('Periode Sterilizer B')
        ->assertDontSee('Periode Boiler Room B');

    // A bound role has no mill picker, so every <option> on the page belongs
    // to the period selector.
    expect(substr_count($component->html(), '<option value="'))->toBe(2);

    $component->assertViewHas('periods', fn ($periods) => count($periods) === 2);
});
