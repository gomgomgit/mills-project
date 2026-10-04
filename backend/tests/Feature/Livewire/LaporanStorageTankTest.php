<?php

/**
 * LaporanStorageTankTest (Feature/Livewire) — screen-133--laporan-storage-tank-web /
 * usecase-133--laporan-storage-tank-web (Laporan Periode Storage Tank).
 *
 * Component tests for App\Livewire\Dashboard\LaporanStorageTank, one per
 * test_scenarios entry's `component_test`. Mirrors
 * tests/Feature/Livewire/LaporanClarificationTest.php (screen-132).
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
 * other does not. There is NO Operator widening here: the mobile Storage
 * Tank report is screen-139, a separate screen with its own endpoints.
 *
 * ----------------------------------------------------------------------
 * THE THREE TEXTS THIS SCREEN MUST NEVER CONFUSE
 * ----------------------------------------------------------------------
 *   "tidak tersedia"        nothing was measured (null)
 *   "tidak dapat dihitung"  something WAS measured, once — so the
 *                           difference between two readings does not exist
 *   "0,0"                   it was measured twice and did not change
 * Three different claims, and the whole point of this screen is that it
 * never substitutes one for another. Every empty-cell assertion below names
 * WHICH of the three it expects and asserts the other two are absent.
 *
 * ----------------------------------------------------------------------
 * NUMBER FORMATTING IS PART OF THE CONTRACT
 * ----------------------------------------------------------------------
 * Trailing zeros are trimmed with at least one decimal kept, so one
 * formatter serves metrics on wildly different scales without lying: 4,0 /
 * 0,2 / 0,207 / 55,0. Movement carries an EXPLICIT ASCII sign (+10,0 /
 * -180,0 / 0,0), never a U+2212 minus. Reading instants read "01 Sep 06:00"
 * — date AND hour together, because together they say what span was
 * actually differenced.
 *
 * ----------------------------------------------------------------------
 * NO THRESHOLD FLAGGING, ASSERTED BY NAME — AND BY CLASS ATTRIBUTE
 * ----------------------------------------------------------------------
 * Storage Tank has no operational-target master table, so nothing here is
 * flagged out of range. The explanatory note box deliberately uses the
 * class `.md-explain` rather than the shared `.md-threshold`, precisely so
 * the absence can be asserted as a bare substring on the rendered HTML.
 * Scenario 25 goes further and compares the class ATTRIBUTE of a card
 * holding an extreme value against one holding an ordinary value: they must
 * be byte-identical, because there is no conditional branch on class
 * anywhere on this page.
 *
 * ----------------------------------------------------------------------
 * "60,0 IS NOWHERE ON THE TEMPERATURE CARD" — ASSERTED OVER THE WHOLE CARD
 * ----------------------------------------------------------------------
 * Scenarios 12 and 21 say the text "60,0" must not appear on the
 * temperature card. That is asserted over the ENTIRE
 * data-testid="metric-card-temperature" section — figures, meta, foot and
 * the explanatory note alike — not over some sub-region of it. The note
 * (data-testid="temperature-source-note") explains in prose that the three
 * POSITIONAL temperatures are separate metrics not used here, and prints no
 * digit at all while doing so, which is what makes the whole-card claim
 * checkable as the spec words it.
 *
 * The prohibition on "60" and "60,0" rests entirely on the FIXTURES: both
 * scenarios feed top/middle/bottom 50/60/70 while average_temperature_c is
 * null (12) or 55,0 (21), so 60 can only reach the card by being DERIVED
 * from the three positional temperatures — which is precisely the defect
 * the scenarios exist to catch. Never give those fixtures an
 * average_temperature_c of 60, or the assertion stops meaning anything.
 */

use App\Enums\UserRole;
use App\Livewire\Dashboard\LaporanStorageTank;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\StorageTankDetail;
use App\Models\StorageTankRecord;
use App\Models\User;
use App\Services\StorageTankRecordService;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * One storage_tank_records header for $tankId on $date, plus one
 * storage_tank_details row per entry of $rows, with `time_slot` filled in
 * from the canonical grid by position.
 *
 * @param  list<array<string, mixed>>  $rows
 */
function laporanStorageTankComponentRecord(Station $station, string $date, string $tankId, array $rows = [], array $overrides = []): StorageTankRecord
{
    $record = StorageTankRecord::factory()->forStation($station)->onDate($date)->create(array_merge([
        'storage_tank_id' => $tankId,
        'note' => null,
    ], $overrides));

    $slots = StorageTankRecordService::canonicalTimeSlots();

    foreach (array_values($rows) as $index => $row) {
        StorageTankDetail::factory()->forRecord($record)->create(array_merge([
            'time_slot' => $slots[$index % count($slots)],
        ], $row));
    }

    return $record;
}

/**
 * THE SCRAMBLED FIXTURE: 100,0 @01 Sep 06:00 -> 250,0 @05 Sep 12:00 ->
 * 80,0 @10 Sep 18:00, headers WRITTEN 05 Sep, 10 Sep, 01 Sep. Opening by
 * value would be 80,0; by storage order 250,0; by time (correct) 100,0.
 */
function laporanStorageTankComponentScrambled(Station $station, string $tankId = 'TK-01'): void
{
    laporanStorageTankComponentRecord($station, '2026-09-05', $tankId, [
        ['time_slot' => '12:00', 'calculated_weight_mt' => 250.0],
    ]);
    laporanStorageTankComponentRecord($station, '2026-09-10', $tankId, [
        ['time_slot' => '18:00', 'calculated_weight_mt' => 80.0],
    ]);
    laporanStorageTankComponentRecord($station, '2026-09-01', $tankId, [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 100.0],
    ]);
}

/**
 * The text rendered inside the element carrying $testid — used instead of
 * matching a glyph, so "unavailable, not zero" is asserted as semantics
 * rather than typography.
 */
function laporanStorageTankRendered(string $html, string $testid): ?string
{
    if (preg_match('/data-testid="'.preg_quote($testid, '/').'"[^>]*>(.*?)</s', $html, $matches)) {
        return trim(html_entity_decode($matches[1]));
    }

    return null;
}

/**
 * The `class` attribute of the element carrying $testid, verbatim.
 *
 * Scenario 25 compares two of these byte for byte: an extreme value must
 * not be able to change one character of a card's markup, and asserting the
 * ABSENCE of a flag class is weaker than asserting the presence of the same
 * class on both.
 */
function laporanStorageTankClassOf(string $html, string $testid): ?string
{
    if (preg_match('/<[a-zA-Z]+\s+class="([^"]*)"\s+data-testid="'.preg_quote($testid, '/').'"/s', $html, $matches)) {
        return $matches[1];
    }

    return null;
}

/**
 * The slice of HTML that begins at $fromTestid and ends just before
 * $toTestid — used to read a card's FIGURE REGION without its explanatory
 * note. See the "one known friction" note in the file header.
 */
function laporanStorageTankSlice(string $html, string $fromTestid, string $toTestid): string
{
    $start = strpos($html, 'data-testid="'.$fromTestid.'"');
    $end = strpos($html, 'data-testid="'.$toTestid.'"');

    if ($start === false || $end === false || $end <= $start) {
        return '';
    }

    return substr($html, $start, $end - $start);
}

/**
 * One whole `<section data-testid="...">...</section>` of the rendered page.
 *
 * Unlike laporanStorageTankSlice() this is bounded by the element's OWN
 * closing tag, so "nowhere on this card" can be asserted over the card in
 * full instead of over a region that stops at a sibling testid. The report's
 * cards never nest a `<section>`, which is what makes the lazy match exact.
 */
function laporanStorageTankSection(string $html, string $testid): string
{
    if (preg_match('/<section[^>]*data-testid="'.preg_quote($testid, '/').'".*?<\/section>/s', $html, $matches)) {
        return $matches[0];
    }

    return '';
}

/** One `<tr>` of a recap table, by its data-testid. */
function laporanStorageTankRow(string $html, string $testid): string
{
    if (preg_match('/<tr data-testid="'.preg_quote($testid, '/').'".*?<\/tr>/s', $html, $matches)) {
        return $matches[0];
    }

    return '';
}

/** The four oil-quality metric cards, by their data-testid slug. */
const LAPORAN_STORAGE_TANK_METRIC_CARDS = ['ffa', 'moisture', 'impurities', 'dobi'];

/** Every numeric block the report renders — used by the "no figures" cases. */
const LAPORAN_STORAGE_TANK_FIGURE_TESTIDS = [
    'coverage-card', 'report-stock', 'stock-opening-card', 'stock-closing-card',
    'stock-movement-card', 'report-quality', 'metric-card-ffa', 'metric-card-moisture',
    'metric-card-impurities', 'metric-card-dobi', 'metric-card-temperature',
    'stock-trend-chart', 'quality-trend-chart', 'by-tank-table', 'daily-table',
    'export-button', 'export-excel-button',
];

/** Controls a read-only report must never expose. */
const LAPORAN_STORAGE_TANK_WRITE_TESTIDS = [
    'save-button', 'create-button', 'edit-button', 'delete-button',
    'add-row-button', 'remove-row-button', 'submit-button',
];

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->storageTank()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->storageTank()->create();

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
        ->stationType('storage-tank')
        ->range('2026-09-01', '2026-09-30')
        ->named('Periode September Alpha')
        ->open()
        ->create();
});

// =====================================================================
// Scenario 1: "berhasil sebagai Supervisor atau Mill Management"
// =====================================================================
it('berhasil: tanpa pemilih mill, seluruh blok angka tampil, dan tiap kartu metrik membawa jumlah pembacaannya sendiri', function () {
    laporanStorageTankComponentScrambled($this->stationA);
    laporanStorageTankComponentRecord($this->stationA, '2026-09-02', 'TK-02', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 400.0, 'ffa_percent' => 4.0,
            'average_temperature_c' => 55.0, 'moisture_content_percent' => 0.2,
            'impurities_dirt_percent' => 0.02, 'dobi_index' => 3.0],
        ['time_slot' => '18:00', 'calculated_weight_mt' => 450.0, 'ffa_percent' => 4.4,
            'moisture_content_percent' => 0.24, 'dobi_index' => 2.8],
    ]);

    foreach ([$this->supervisor, $this->millManagement] as $user) {
        $component = Livewire::actingAs($user)
            ->test(LaporanStorageTank::class)
            ->set('productionLineId', $this->lineA)
            // The newest period is auto-selected, so the page is useful on
            // first paint rather than demanding a choice first.
            ->assertSet('periodId', (string) $this->periodA->id)
            // Offering a picker they cannot use would be a lie.
            ->assertDontSeeHtml('data-testid="mill-select"')
            ->assertSeeHtml('data-testid="mill-name"')
            ->assertSee('Mill Alpha')
            ->assertSeeHtml('data-testid="period-select"')
            ->assertSeeHtml('data-testid="coverage-card"')
            ->assertSeeHtml('data-testid="stock-opening-card"')
            ->assertSeeHtml('data-testid="stock-closing-card"')
            ->assertSeeHtml('data-testid="stock-movement-card"')
            ->assertSeeHtml('data-testid="opening-at"')
            ->assertSeeHtml('data-testid="closing-at"')
            ->assertSeeHtml('data-testid="by-tank-table"')
            ->assertSeeHtml('data-testid="metric-card-ffa"')
            ->assertSeeHtml('data-testid="metric-card-moisture"')
            ->assertSeeHtml('data-testid="metric-card-impurities"')
            ->assertSeeHtml('data-testid="metric-card-dobi"')
            ->assertSeeHtml('data-testid="metric-card-temperature"')
            ->assertSeeHtml('data-testid="quality-trend-chart"')
            ->assertSeeHtml('data-testid="stock-trend-chart"')
            ->assertSeeHtml('data-testid="daily-table"')
            ->assertSeeHtml('data-testid="export-button"')
            ->assertDontSee('Mill Beta')
            ->assertDontSeeHtml('data-testid="empty-state"');

        $html = $component->html();

        // EXACTLY ONE quality chart: the three series share one drawing
        // area because what says the oil is deteriorating is the three of
        // them moving TOGETHER.
        expect(substr_count($html, 'data-testid="quality-trend-chart"'))->toBe(1);

        // opening-at / closing-at exist EXACTLY ONCE each, inside their own
        // cards — the per-tank table's time cells deliberately carry no
        // testid, so a strict-mode browser locator can never be ambiguous.
        expect(substr_count($html, 'data-testid="opening-at"'))->toBe(1);
        expect(substr_count($html, 'data-testid="closing-at"'))->toBe(1);

        // Every metric card carries its OWN reading count — there is no one
        // shared denominator anywhere on the page.
        foreach (LAPORAN_STORAGE_TANK_METRIC_CARDS as $slug) {
            $component->assertSeeHtml('data-testid="metric-'.$slug.'-avg"');
            $component->assertSeeHtml('data-testid="metric-'.$slug.'-reading-count"');
        }

        $component->assertSeeHtml('data-testid="metric-temperature-reading-count"');

        // No write-flavoured control anywhere.
        foreach (LAPORAN_STORAGE_TANK_WRITE_TESTIDS as $writeish) {
            expect($html)->not->toContain('data-testid="'.$writeish.'"');
        }

        // The figures behind the markup: stock chosen BY TIME, movement
        // summed PER TANK, average temperature READ from its own column.
        $component->assertViewHas('summary', fn ($summary) => $summary['stock']['opening_mt'] === 100.0 + 400.0
            && $summary['stock']['movement_mt'] === 30.0
            && $summary['metrics']['average_temperature_c']['avg'] === 55.0
            && $summary['coverage']['filled_slots'] === 5
            && count($summary['by_tank']) === 2);
    }
});

// =====================================================================
// Scenario 2: "berhasil sebagai Admin"
// =====================================================================
it('admin: pemilih Mill dirender, daftar periode mengikuti mill terpilih, dan isi layar identik dengan peran lain', function () {
    laporanStorageTankComponentRecord($this->stationA, '2026-09-10', 'TK-01', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 100.0, 'ffa_percent' => 4.0,
            'average_temperature_c' => 55.0, 'moisture_content_percent' => 0.2, 'dobi_index' => 3.0],
        ['time_slot' => '18:00', 'calculated_weight_mt' => 120.0, 'ffa_percent' => 4.2,
            'moisture_content_percent' => 0.22, 'dobi_index' => 2.9],
    ]);

    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('storage-tank')
        ->range('2026-09-01', '2026-09-30')->named('Periode September Beta')->open()->create();

    $component = Livewire::actingAs($this->admin)
        ->test(LaporanStorageTank::class)
        ->set('productionLineId', $this->lineA)
        // Admin is the one role not bound to a mill, so it gets the picker.
        ->assertSeeHtml('data-testid="mill-select"')
        ->assertSeeHtml('data-testid="mill-select-hint"')
        ->set('businessUnitId', (string) $this->businessUnitA->id);

    // The period list refreshed to the chosen mill, and the newest one of
    // THAT mill was selected.
    $component->assertSet('periodId', (string) $this->periodA->id);
    expect(array_column($component->viewData('periods'), 'id'))->toBe([(string) $this->periodA->id]);
    expect(array_column($component->viewData('periods'), 'id'))->not->toContain((string) $periodB->id);

    $component
        ->assertSeeHtml('data-testid="mill-select"')
        ->assertDontSeeHtml('data-testid="mill-select-hint"')
        ->assertSeeHtml('data-testid="coverage-card"')
        ->assertSeeHtml('data-testid="stock-opening-card"')
        ->assertSeeHtml('data-testid="stock-closing-card"')
        ->assertSeeHtml('data-testid="stock-movement-card"')
        ->assertSeeHtml('data-testid="by-tank-table"')
        ->assertSeeHtml('data-testid="metric-card-temperature"')
        ->assertSeeHtml('data-testid="quality-trend-chart"')
        ->assertSeeHtml('data-testid="stock-trend-chart"')
        ->assertSeeHtml('data-testid="daily-table"')
        ->assertSeeHtml('data-testid="export-button"')
        ->assertSeeHtml('data-testid="export-excel-button"');

    foreach (LAPORAN_STORAGE_TANK_METRIC_CARDS as $slug) {
        $component->assertSeeHtml('data-testid="metric-'.$slug.'-avg"');
        $component->assertSeeHtml('data-testid="metric-'.$slug.'-reading-count"');
    }
});

// =====================================================================
// Scenario 3: "Admin memilih mill lebih dulu"
// =====================================================================
it('admin tanpa mill: pemilih dan arahan terlihat, dan tidak satu pun angka laporan dirender', function () {
    laporanStorageTankComponentRecord($this->stationA, '2026-09-10', 'TK-01', [
        ['calculated_weight_mt' => 100.0],
    ]);

    $component = Livewire::actingAs($this->admin)
        ->test(LaporanStorageTank::class)
        ->set('productionLineId', $this->lineA)
        ->assertSeeHtml('data-testid="mill-select"')
        ->assertSeeHtml('data-testid="mill-select-hint"')
        // The page ASKS for a mill instead of drawing an empty report that
        // would read as "this mill has no data".
        ->assertDontSeeHtml('data-testid="period-select"')
        ->assertViewHas('summary', null);

    $html = $component->html();

    foreach (LAPORAN_STORAGE_TANK_FIGURE_TESTIDS as $testid) {
        expect($html)->not->toContain('data-testid="'.$testid.'"');
    }
});

// =====================================================================
// Scenario 4: "mill belum punya periode"
// =====================================================================
it('mill tanpa periode: pemilih periode tanpa satu pun opsi, arahan menghubungi Admin, tanpa angka', function () {
    $supervisorB = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitB)->create();

    // Mill Beta has a period, but for another station type only.
    Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('sterilizer')
        ->range('2026-09-01', '2026-09-30')->named('Periode Sterilizer Beta')->open()->create();

    $component = Livewire::actingAs($supervisorB)
        ->test(LaporanStorageTank::class)
        ->set('productionLineId', (string) $this->stationB->production_line_id)
        ->assertSeeHtml('data-testid="period-select"')
        ->assertSeeHtml('data-testid="no-period-hint"')
        ->assertSee('Hubungi Admin')
        // The FULL sentence, not just the two words: "Hubungi Admin" also
        // opens the unrelated admin-without-mill hint, and only this wording
        // points at the screen that actually fixes the problem. It sits on
        // ONE source line inside md-empty__text with no tag between the
        // words, so the match does not depend on HTML line wrapping.
        ->assertSee('Hubungi Admin agar membuatnya terlebih dahulu di layar Kelola Periode Pelaporan')
        ->assertSet('periodId', '')
        ->assertViewHas('periods', [])
        ->assertViewHas('summary', null);

    $html = $component->html();

    // The picker is rendered with NO option at all — not with a pretend
    // "belum ada periode" option, which would be an option that selects
    // nothing.
    preg_match('/data-testid="period-select">(.*?)<\/select>/s', $html, $matches);
    expect($matches[1] ?? '')->not->toContain('<option');

    foreach (LAPORAN_STORAGE_TANK_FIGURE_TESTIDS as $testid) {
        expect($html)->not->toContain('data-testid="'.$testid.'"');
    }
});

// =====================================================================
// Scenario 5: "periode tanpa data"
// =====================================================================
it('periode tanpa data: empty-state terlihat, kartu berbunyi tidak tersedia (bukan 0), dan grafik tidak digambar', function () {
    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanStorageTank::class)
        ->set('productionLineId', $this->lineA)
        ->assertSeeHtml('data-testid="empty-state"')
        ->assertSee('Belum ada data pada periode ini')
        // An empty chart would read as a measured flat line, so it is not
        // drawn at all.
        ->assertDontSeeHtml('data-testid="quality-trend-chart"')
        ->assertDontSeeHtml('data-testid="stock-trend-chart"')
        ->assertDontSeeHtml('data-testid="by-tank-table"')
        ->assertDontSeeHtml('data-testid="daily-table"');

    $html = $component->html();

    // The stock and metric cards ARE rendered — reading "tidak tersedia",
    // never 0 nor 0,00.
    foreach (['stock-opening-mt', 'stock-closing-mt', 'stock-movement-mt'] as $testid) {
        expect(laporanStorageTankRendered($html, $testid))->toBe('tidak tersedia');
    }

    foreach (['opening-at', 'closing-at'] as $testid) {
        expect(laporanStorageTankRendered($html, $testid))->toBe('tidak tersedia');
    }

    foreach (LAPORAN_STORAGE_TANK_METRIC_CARDS as $slug) {
        foreach (['avg', 'min', 'max'] as $stat) {
            $value = laporanStorageTankRendered($html, 'metric-'.$slug.'-'.$stat);

            expect($value)->toBe('tidak tersedia');
            expect($value)->not->toBe('0');
            expect($value)->not->toBe('0,00');
        }

        expect(laporanStorageTankRendered($html, 'metric-'.$slug.'-reading-count'))->toBe('0');
    }

    // The temperature card, both charts, the per-tank recap and the daily
    // recap sit BELOW the empty-state branch and are not drawn at all — an
    // empty card is not more informative than the notice that replaces it,
    // and an empty chart would read as a measured flat line.
    expect($html)->not->toContain('data-testid="metric-card-temperature"');
});

// =====================================================================
// Scenario 6: "tangki hanya punya satu pembacaan stok"
// =====================================================================
it('tangki berpembacaan tunggal: sel pergerakan berbunyi tidak dapat dihitung, bukan 0, dan tidak ikut ke kartu', function () {
    laporanStorageTankComponentRecord($this->stationA, '2026-09-01', 'TK-01', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 100.0],
    ]);
    laporanStorageTankComponentRecord($this->stationA, '2026-09-10', 'TK-01', [
        ['time_slot' => '18:00', 'calculated_weight_mt' => 130.0],
    ]);
    laporanStorageTankComponentRecord($this->stationA, '2026-09-10', 'TK-03', [
        ['time_slot' => '12:00', 'calculated_weight_mt' => 500.0],
    ]);

    $html = Livewire::actingAs($this->supervisor)->test(LaporanStorageTank::class)->set('productionLineId', $this->lineA)->html();

    $row = laporanStorageTankRow($html, 'by-tank-row-TK-03');

    expect($row)->not->toBe('');
    // Opening and closing are the SAME reading at the SAME instant...
    expect(substr_count($row, '500,0'))->toBe(2);
    expect(substr_count($row, '10 Sep 12:00'))->toBe(2);
    // ...so the movement cell says so in words. "0,0" would claim the stock
    // did not change — a stronger claim that was never measured.
    expect($row)->toContain('tidak dapat dihitung');
    expect($row)->not->toContain('>0,0<');
    expect($row)->not->toContain('>0<');

    // -- and the single reading contributes nothing to the period figure.
    expect(laporanStorageTankRendered($html, 'stock-movement-mt'))->toBe('+30,0');
    expect(laporanStorageTankRendered($html, 'stock-movement-mt'))->not->toContain('500');
    expect(laporanStorageTankRendered($html, 'tanks-without-movement'))->toBe('1');
    expect(laporanStorageTankRendered($html, 'tanks-with-movement'))->toBe('1');
});

// =====================================================================
// Scenario 7: "pembacaan paling awal tidak mencatat stok"
// =====================================================================
it('pembacaan paling awal tanpa stok: baris TK-04 menampilkan 150,0 dari 03 Sep 12:00, bukan tanggal awal periode', function () {
    laporanStorageTankComponentRecord($this->stationA, '2026-09-01', 'TK-04', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => null, 'ffa_percent' => 3.2],
    ]);
    laporanStorageTankComponentRecord($this->stationA, '2026-09-03', 'TK-04', [
        ['time_slot' => '12:00', 'calculated_weight_mt' => 150.0],
    ]);
    laporanStorageTankComponentRecord($this->stationA, '2026-09-09', 'TK-04', [
        ['time_slot' => '18:00', 'calculated_weight_mt' => 140.0],
    ]);

    $html = Livewire::actingAs($this->supervisor)->test(LaporanStorageTank::class)->set('productionLineId', $this->lineA)->html();

    $row = laporanStorageTankRow($html, 'by-tank-row-TK-04');

    expect($row)->toContain('150,0');
    expect($row)->toContain('03 Sep 12:00');
    expect($row)->not->toContain('01 Sep 06:00');

    // The card's opening timestamp points at the reading that was actually
    // used — the two unrecorded days become visible instead of implied.
    expect(laporanStorageTankRendered($html, 'stock-opening-mt'))->toBe('150,0');
    expect(laporanStorageTankRendered($html, 'opening-at'))->toBe('03 Sep 12:00');

    // The row states, in words, that this tank was only recorded from the
    // third day of the period onwards.
    expect($row)->toContain('baru tercatat sejak 03 Sep');
});

// =====================================================================
// Scenario 8: "tangki tanpa satu pun pembacaan stok"
// =====================================================================
it('tangki tanpa pembacaan stok: baris TK-06 tetap dirender dengan tidak tersedia, dan jumlah pembacaannya tetap > 0', function () {
    laporanStorageTankComponentRecord($this->stationA, '2026-09-04', 'TK-06', [
        ['ffa_percent' => 4.0, 'calculated_weight_mt' => null],
        ['ffa_percent' => 4.4, 'calculated_weight_mt' => null],
    ]);

    $html = Livewire::actingAs($this->supervisor)->test(LaporanStorageTank::class)->set('productionLineId', $this->lineA)->html();

    $row = laporanStorageTankRow($html, 'by-tank-row-TK-06');

    // NOT dropped — dropping it would hide exactly the tank nobody measured.
    expect($row)->not->toBe('');
    // SIX cells read "tidak tersedia": opening, opening time, closing,
    // closing time, movement, and the average temperature that was never
    // recorded either. Never "tidak dapat dihitung" (that is the
    // single-reading case, a different claim) and never 0.
    expect(substr_count($row, 'tidak tersedia'))->toBe(6);
    expect($row)->not->toContain('tidak dapat dihitung');
    expect($row)->not->toContain('>0,0<');

    // The reading count is real and non-zero: the tank WAS visited.
    expect($row)->toContain('<td>2</td>');
    // And its FFA average is still published, from its own denominator.
    expect($row)->toContain('4,2');
});

// =====================================================================
// Scenario 9: "jumlah tangki berbeda antara awal dan akhir periode"
// =====================================================================
it('jumlah tangki berbeda di kedua ujung: kartu pergerakan +10,0 dan bukan 410,0, dan baris TK-02 menyatakan keadaannya', function () {
    $period = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('storage-tank')
        ->range('2026-09-01', '2026-09-10')->named('Periode Sepuluh Hari')->open()->create();

    laporanStorageTankComponentRecord($this->stationA, '2026-09-01', 'TK-01', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 100.0],
    ]);
    laporanStorageTankComponentRecord($this->stationA, '2026-09-10', 'TK-01', [
        ['time_slot' => '18:00', 'calculated_weight_mt' => 90.0],
    ]);
    laporanStorageTankComponentRecord($this->stationA, '2026-09-09', 'TK-02', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 400.0],
    ]);
    laporanStorageTankComponentRecord($this->stationA, '2026-09-10', 'TK-02', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 420.0],
    ]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanStorageTank::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $period->id);

    $html = $component->html();

    expect(laporanStorageTankRendered($html, 'stock-movement-mt'))->toBe('+10,0');
    // The combined-stock answer appears nowhere on the page.
    expect($html)->not->toContain('410,0');

    // The card IS the column sum of the table below it — the reader can
    // check one against the other, and so can this test.
    $component->assertViewHas('summary', function ($summary) {
        $computable = array_filter($summary['by_tank'], fn ($tank) => $tank['movement_computable']);

        return round(array_sum(array_column($computable, 'movement_mt')), 2) === $summary['stock']['movement_mt'];
    });

    // TK-02 says, on its own row, that it was only recorded near the end.
    $row = laporanStorageTankRow($html, 'by-tank-row-TK-02');
    expect($row)->toContain('baru tercatat sejak 09 Sep');
});

// =====================================================================
// Scenario 10: "pergerakan bernilai negatif"
// =====================================================================
it('pergerakan negatif: -180,0 apa adanya, tanpa kelas peringatan dan tanpa dibulatkan ke nol', function () {
    laporanStorageTankComponentRecord($this->stationA, '2026-09-02', 'TK-05', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 500.0],
    ]);
    laporanStorageTankComponentRecord($this->stationA, '2026-09-08', 'TK-05', [
        ['time_slot' => '18:00', 'calculated_weight_mt' => 320.0],
    ]);

    $html = Livewire::actingAs($this->supervisor)->test(LaporanStorageTank::class)->set('productionLineId', $this->lineA)->html();

    $movement = laporanStorageTankRendered($html, 'stock-movement-mt');

    // ASCII sign, written explicitly, because the direction is what is read.
    expect($movement)->toBe('-180,0');
    expect($movement)->not->toBe('180,0');
    expect($movement)->not->toBe('0,0');
    expect($movement)->not->toContain("\u{2212}");

    // Oil leaving the tank is the ordinary case, not an alarm: the movement
    // card carries the SAME class attribute as the opening card.
    expect(laporanStorageTankClassOf($html, 'stock-movement-card'))
        ->toBe(laporanStorageTankClassOf($html, 'stock-opening-card'));

    foreach (['is-danger', 'is-warning', 'text-red', 'bg-red', 'severity', 'alert', 'threshold'] as $flag) {
        expect(strtolower($html))->not->toContain($flag);
    }
});

// =====================================================================
// Scenario 11: "sebuah metrik mutu tidak pernah diisi"
// =====================================================================
it('metrik yang tidak pernah diisi: kartu kotoran berbunyi tidak tersedia dengan 0 pembacaan, kartu FFA tetap normal', function () {
    laporanStorageTankComponentRecord($this->stationA, '2026-09-04', 'TK-01', [
        ['ffa_percent' => 3.0, 'impurities_dirt_percent' => null, 'calculated_weight_mt' => 100.0],
        ['ffa_percent' => 4.0, 'impurities_dirt_percent' => null],
        ['ffa_percent' => 5.0, 'impurities_dirt_percent' => null],
    ]);

    $html = Livewire::actingAs($this->supervisor)->test(LaporanStorageTank::class)->set('productionLineId', $this->lineA)->html();

    expect(laporanStorageTankRendered($html, 'metric-impurities-avg'))->toBe('tidak tersedia');
    expect(laporanStorageTankRendered($html, 'metric-impurities-avg'))->not->toBe('0,00');
    expect(laporanStorageTankRendered($html, 'metric-impurities-min'))->toBe('tidak tersedia');
    expect(laporanStorageTankRendered($html, 'metric-impurities-max'))->toBe('tidak tersedia');
    expect(laporanStorageTankRendered($html, 'metric-impurities-reading-count'))->toBe('0');

    // The neighbour is untouched, with its OWN denominator.
    expect(laporanStorageTankRendered($html, 'metric-ffa-avg'))->toBe('4,0');
    expect(laporanStorageTankRendered($html, 'metric-ffa-min'))->toBe('3,0');
    expect(laporanStorageTankRendered($html, 'metric-ffa-max'))->toBe('5,0');
    expect(laporanStorageTankRendered($html, 'metric-ffa-reading-count'))->toBe('3');
});

// =====================================================================
// Scenario 12: "kolom suhu rata-rata kosong"
// =====================================================================
it('kolom suhu rata-rata kosong: kartu suhu berbunyi tidak tersedia dengan 0 pembacaan, tanpa menghitung sendiri 60,0', function () {
    laporanStorageTankComponentRecord($this->stationA, '2026-09-04', 'TK-01', [
        [
            'oil_temperature_top_c' => 50.0,
            'oil_temperature_middle_c' => 60.0,
            'oil_temperature_bottom_c' => 70.0,
            'average_temperature_c' => null,
            'calculated_weight_mt' => 100.0,
        ],
    ]);

    $html = Livewire::actingAs($this->supervisor)->test(LaporanStorageTank::class)->set('productionLineId', $this->lineA)->html();

    expect(laporanStorageTankRendered($html, 'metric-temperature-avg'))->toBe('tidak tersedia');
    expect(laporanStorageTankRendered($html, 'metric-temperature-min'))->toBe('tidak tersedia');
    expect(laporanStorageTankRendered($html, 'metric-temperature-max'))->toBe('tidak tersedia');
    expect(laporanStorageTankRendered($html, 'metric-temperature-reading-count'))->toBe('0');

    // THE CLAIM THIS SCENARIO IS ABOUT, over the WHOLE card: the screen
    // derives nothing from the three positional temperatures, so neither
    // "60,0" nor a bare "60" appears anywhere inside
    // data-testid="metric-card-temperature" — explanatory note included.
    // The fixture above supplies 50/60/70 with a null average precisely so
    // that a 60 could only come from a recomputation.
    $card = laporanStorageTankSection($html, 'metric-card-temperature');

    expect($card)->not->toBe('');
    expect($card)->toContain('data-testid="temperature-source-note"');
    expect($card)->not->toContain('60,0');
    expect($card)->not->toContain('60');

    // And the per-tank column comes from the same empty source.
    expect(laporanStorageTankRow($html, 'by-tank-row-TK-01'))->toContain('tidak tersedia');
});

// =====================================================================
// Scenario 13: "pencatatan sangat tidak lengkap"
// =====================================================================
it('pencatatan sangat tidak lengkap: kartu kelengkapan menampilkan 3 dari 240 pada blok utama, dan angka utama tetap tampil', function () {
    $period = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('storage-tank')
        ->range('2026-09-01', '2026-09-05')->named('Periode Tipis')->open()->create();

    laporanStorageTankComponentRecord($this->stationA, '2026-09-01', 'TK-01', [
        ['time_slot' => '07:00', 'calculated_weight_mt' => 100.0, 'ffa_percent' => 4.0],
        ['time_slot' => '08:00', 'calculated_weight_mt' => 90.0],
    ]);
    laporanStorageTankComponentRecord($this->stationA, '2026-09-03', 'TK-02', [
        ['time_slot' => '07:00', 'calculated_weight_mt' => 300.0],
    ]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanStorageTank::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $period->id)
        ->assertSeeHtml('data-testid="coverage-card"')
        ->assertSeeHtml('data-testid="low-coverage-emphasis"');

    $html = $component->html();

    expect(laporanStorageTankRendered($html, 'coverage-filled-slots'))->toBe('3');
    expect(laporanStorageTankRendered($html, 'coverage-expected-slots'))->toBe('240');
    expect(laporanStorageTankRendered($html, 'coverage-percent'))->toBe('1,3%'); // 1 desimal seperti laporan lain (temuan audit 2026-10-04 #10)

    // PART OF THE REPORT BODY, NOT A FOOTNOTE: the coverage card is
    // rendered BEFORE the stock cards, because on this screen coverage is
    // what says how far apart the two compared readings sit.
    expect(strpos($html, 'data-testid="coverage-card"'))
        ->toBeLessThan(strpos($html, 'data-testid="stock-opening-card"'));

    // And the main figures are still there.
    expect(laporanStorageTankRendered($html, 'stock-opening-mt'))->toBe('400,0');
    expect(laporanStorageTankRendered($html, 'stock-movement-mt'))->toBe('-10,0');
    $component->assertSeeHtml('data-testid="by-tank-table"');
});

// =====================================================================
// Scenario 14: "akun belum terhubung ke mill"
// =====================================================================
it('akun tanpa mill: pesan menghubungi Admin, pemilih Mill TIDAK dirender, dan tidak satu pun angka', function () {
    $noMillSupervisor = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    laporanStorageTankComponentRecord($this->stationA, '2026-09-10', 'TK-01', [
        ['calculated_weight_mt' => 100.0],
    ]);

    $component = Livewire::actingAs($noMillSupervisor)
        ->test(LaporanStorageTank::class)
        ->set('productionLineId', $this->lineA)
        ->assertSeeHtml('data-testid="no-mill-hint"')
        ->assertSee('Hubungi Admin')
        // FAIL CLOSED: offering the all-mills list to a role that is
        // supposed to be tied to exactly one mill would turn one broken
        // master-data row into a cross-mill leak.
        ->assertDontSeeHtml('data-testid="mill-select"')
        ->assertDontSeeHtml('data-testid="period-select"')
        ->assertViewHas('summary', null);

    $html = $component->html();

    expect($html)->not->toContain('Mill Beta');

    foreach (LAPORAN_STORAGE_TANK_FIGURE_TESTIDS as $testid) {
        expect($html)->not->toContain('data-testid="'.$testid.'"');
    }
});

// =====================================================================
// Scenario 15: "mencoba melihat mill lain"
// =====================================================================
it('mill lain: memaksa businessUnitId tidak mengubah satu angka pun, tetapi periode mill lain ditolak secara terlihat', function () {
    laporanStorageTankComponentRecord($this->stationA, '2026-09-10', 'TK-01', [
        ['calculated_weight_mt' => 100.0, 'ffa_percent' => 4.0],
    ]);
    laporanStorageTankComponentRecord($this->stationB, '2026-09-10', 'TK-BETA', [
        ['calculated_weight_mt' => 900.0, 'ffa_percent' => 99.0],
    ]);

    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('storage-tank')
        ->range('2026-09-01', '2026-09-30')->named('Periode September Beta')->open()->create();

    // Step 1 — forcing the public property to another mill is a NO-OP for a
    // bound role: still 200, still the caller's own mill.
    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanStorageTank::class)
        ->set('productionLineId', $this->lineA)
        ->set('businessUnitId', (string) $this->businessUnitB->id);

    $component->assertSee('Mill Alpha');
    $component->assertDontSee('Mill Beta');
    $component->assertDontSeeHtml('data-testid="mill-select"');
    $component->assertSet('periodId', (string) $this->periodA->id);

    $html = $component->html();
    expect(laporanStorageTankRendered($html, 'stock-opening-mt'))->toBe('100,0');
    expect($html)->not->toContain('900,0');
    expect($html)->not->toContain('TK-BETA');

    // Step 2 — a HAND-FORCED period id from another mill IS refused, and
    // visibly: a silent swap would answer a cross-mill probe with different
    // numbers under the id that was asked for.
    $component->set('periodId', (string) $periodB->id);

    $component->assertSeeHtml('data-testid="forbidden-notice"');
    $component->assertSee('Anda tidak memiliki akses ke periode ini');
    $component->assertViewHas('summary', null);

    $forbiddenHtml = $component->html();
    expect($forbiddenHtml)->not->toContain('900,0');
    expect($forbiddenHtml)->not->toContain('TK-BETA');
    expect($forbiddenHtml)->not->toContain('data-testid="by-tank-table"');
});

// =====================================================================
// Scenario 16: "Operator mencoba membuka layar web ini"
// =====================================================================
it('operator: rute menolak sebelum mount, dan mount() sendiri juga menolak', function () {
    // Route layer — EnsureRole::forbidden() -> abort(403).
    $response = $this->actingAs($this->operator, 'web')->get('/reports/storage-tank');
    $response->assertForbidden();
    $response->assertDontSee('Laporan Periode');

    // Component layer — mount()'s abort_unless(403) covers the component
    // being mounted directly, which is exactly how this scenario exercises
    // it. There is NO Operator widening here: the mobile Storage Tank
    // report is screen-139, a separate screen with its own endpoints.
    $html = Livewire::actingAs($this->operator)->test(LaporanStorageTank::class)->html();

    expect($html)->toContain('Forbidden');
    expect($html)->not->toContain('data-testid="laporan-storage-tank"');

    foreach (LAPORAN_STORAGE_TANK_FIGURE_TESTIDS as $testid) {
        expect($html)->not->toContain('data-testid="'.$testid.'"');
    }
});

// =====================================================================
// Scenario 17: "periode tertutup"
// =====================================================================
it('periode tertutup: penanda status Tertutup, seluruh blok tetap dirender, dan tombol ekspor tidak dinonaktifkan', function () {
    $closed = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('storage-tank')
        ->range('2026-11-01', '2026-11-30')->named('Periode November Tertutup')->closed()->create();

    laporanStorageTankComponentRecord($this->stationA, '2026-11-04', 'TK-01', [
        ['time_slot' => '07:00', 'calculated_weight_mt' => 100.0, 'ffa_percent' => 4.0,
            'moisture_content_percent' => 0.2, 'dobi_index' => 3.0, 'average_temperature_c' => 55.0],
        ['time_slot' => '08:00', 'calculated_weight_mt' => 90.0, 'ffa_percent' => 4.4,
            'moisture_content_percent' => 0.22, 'dobi_index' => 2.8],
    ]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanStorageTank::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $closed->id);

    $html = $component->html();

    // The period lock governs writing data, not reading a report.
    expect(laporanStorageTankRendered($html, 'period-status-badge'))->toBe('Tertutup');

    $component
        ->assertSeeHtml('data-testid="coverage-card"')
        ->assertSeeHtml('data-testid="stock-opening-card"')
        ->assertSeeHtml('data-testid="stock-movement-card"')
        ->assertSeeHtml('data-testid="by-tank-table"')
        ->assertSeeHtml('data-testid="daily-table"')
        ->assertSeeHtml('data-testid="stock-trend-chart"')
        ->assertSeeHtml('data-testid="export-button"');

    // The export button is never disabled by a closed period — and the
    // action behind it really does hand back a file.
    expect($html)->toMatch('/<button[^>]*data-testid="export-button"(?![^>]*disabled)/');
    $component->call('exportCsv', 'csv')->assertFileDownloaded(null, null, 'text/csv');

    expect(laporanStorageTankRendered($html, 'stock-movement-mt'))->toBe('-10,0');
});

// =====================================================================
// Scenario 18: "rekap harian panjang"
// =====================================================================
it('rekap harian panjang: terbuka secara bawaan, dapat ditutup, dan angka utama beserta grafik tetap terlihat', function () {
    for ($day = 1; $day <= 20; $day++) {
        laporanStorageTankComponentRecord($this->stationA, sprintf('2026-09-%02d', $day), 'TK-01', [
            ['time_slot' => '07:00', 'calculated_weight_mt' => 1000.0 + $day,
                'ffa_percent' => 4.0, 'moisture_content_percent' => 0.2, 'dobi_index' => 3.0],
        ]);
    }

    $component = Livewire::actingAs($this->supervisor)->test(LaporanStorageTank::class)
        ->set('productionLineId', $this->lineA);

    // OPEN BY DEFAULT — a decision, not a default left alone.
    $component->assertSet('dailyRecapOpen', true)
        ->assertSeeHtml('data-testid="daily-toggle"')
        ->assertSeeHtml('data-testid="daily-table"');

    // One row per DATE — counted on the date-shaped ids, so the tfoot's
    // daily-row-total is not miscounted as a 21st date.
    expect(substr_count($component->html(), 'data-testid="daily-row-2026-'))->toBe(20);

    // Closed means genuinely ABSENT from the DOM, not merely hidden — a
    // button rather than <details>, so the visible state and the rendered
    // DOM can never disagree.
    $component->call('toggleDailyRecap')
        ->assertSet('dailyRecapOpen', false)
        ->assertDontSeeHtml('data-testid="daily-table"')
        // ...while the main figures and both charts stay on screen.
        ->assertSeeHtml('data-testid="stock-opening-card"')
        ->assertSeeHtml('data-testid="stock-movement-card"')
        ->assertSeeHtml('data-testid="quality-trend-chart"')
        ->assertSeeHtml('data-testid="stock-trend-chart"')
        ->assertSeeHtml('data-testid="daily-toggle"');

    // Clicking again brings it back, one row per date that has a record.
    $component->call('toggleDailyRecap')
        ->assertSet('dailyRecapOpen', true)
        ->assertSeeHtml('data-testid="daily-table"')
        ->assertSeeHtml('data-testid="daily-row-2026-09-01"')
        ->assertSeeHtml('data-testid="daily-row-2026-09-20"');

    expect($component->html())->not->toContain('data-testid="daily-row-2026-09-21"');
});

// =====================================================================
// Scenario 19: "stok awal adalah pembacaan pertama dan stok akhir terakhir"
// =====================================================================
it('stok awal/akhir menurut waktu: 100,0 @01 Sep 06:00 dan 80,0 @10 Sep 18:00, walau nilai dan urutan penyimpanan berkata lain', function () {
    laporanStorageTankComponentScrambled($this->stationA);

    // TK-02 locks the cross-date ordering: the earlier DATE carries the
    // later SLOT.
    laporanStorageTankComponentRecord($this->stationA, '2026-09-01', 'TK-02', [
        ['time_slot' => '18:00', 'calculated_weight_mt' => 120.0],
    ]);
    laporanStorageTankComponentRecord($this->stationA, '2026-09-02', 'TK-02', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 90.0],
    ]);

    $html = Livewire::actingAs($this->supervisor)->test(LaporanStorageTank::class)->set('productionLineId', $this->lineA)->html();

    // The period card sums the tanks' own openings/closings, and the
    // timestamps span the earliest opening to the latest closing.
    expect(laporanStorageTankRendered($html, 'stock-opening-mt'))->toBe('220,0'); // 100 + 120
    expect(laporanStorageTankRendered($html, 'opening-at'))->toBe('01 Sep 06:00');
    expect(laporanStorageTankRendered($html, 'stock-closing-mt'))->toBe('170,0'); // 80 + 90
    expect(laporanStorageTankRendered($html, 'closing-at'))->toBe('10 Sep 18:00');

    $tk01 = laporanStorageTankRow($html, 'by-tank-row-TK-01');
    expect($tk01)->toContain('100,0');
    expect($tk01)->toContain('01 Sep 06:00');
    expect($tk01)->toContain('80,0');
    expect($tk01)->toContain('10 Sep 18:00');
    // Neither the highest value nor the first row stored.
    expect($tk01)->not->toContain('250,0');

    $tk02 = laporanStorageTankRow($html, 'by-tank-row-TK-02');
    expect($tk02)->toContain('120,0');
    expect($tk02)->toContain('01 Sep 18:00');
});

// =====================================================================
// Scenario 20: "pergerakan dihitung per tangki lalu dijumlahkan"
// =====================================================================
it('pergerakan bersih sama dengan jumlah kolom pergerakan by-tank-table, dan 410,0 tidak muncul di halaman', function () {
    $period = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('storage-tank')
        ->range('2026-09-01', '2026-09-10')->named('Periode Per Tangki')->open()->create();

    laporanStorageTankComponentRecord($this->stationA, '2026-09-01', 'TK-01', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 100.0],
    ]);
    laporanStorageTankComponentRecord($this->stationA, '2026-09-10', 'TK-01', [
        ['time_slot' => '18:00', 'calculated_weight_mt' => 90.0],
    ]);
    laporanStorageTankComponentRecord($this->stationA, '2026-09-09', 'TK-02', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 400.0],
    ]);
    laporanStorageTankComponentRecord($this->stationA, '2026-09-10', 'TK-02', [
        ['time_slot' => '06:00', 'calculated_weight_mt' => 420.0],
    ]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanStorageTank::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $period->id);

    $html = $component->html();

    expect(laporanStorageTankRendered($html, 'stock-movement-mt'))->toBe('+10,0');

    // Per-tank movements, on their own rows, adding up to exactly that.
    expect(laporanStorageTankRow($html, 'by-tank-row-TK-01'))->toContain('-10,0');
    expect(laporanStorageTankRow($html, 'by-tank-row-TK-02'))->toContain('+20,0');

    // The table foot repeats the card's figure — the two are meant to be
    // checked against each other by the reader.
    expect(laporanStorageTankRow($html, 'by-tank-row-total'))->toContain('+10,0');

    // The combined-stock answer is nowhere on the page.
    expect($html)->not->toContain('410,0');
});

// =====================================================================
// Scenario 21: "suhu rata-rata diambil dari kolom yang dicatat Operator"
// =====================================================================
it('suhu rata-rata: kartu menampilkan 55,0 dari kolom Operator, dan tidak 60,0 hasil hitung ulang', function () {
    laporanStorageTankComponentRecord($this->stationA, '2026-09-04', 'TK-01', [
        [
            'oil_temperature_top_c' => 50.0,
            'oil_temperature_middle_c' => 60.0,
            'oil_temperature_bottom_c' => 70.0,
            'average_temperature_c' => 55.0,
            'calculated_weight_mt' => 100.0,
        ],
    ]);

    $html = Livewire::actingAs($this->supervisor)->test(LaporanStorageTank::class)->set('productionLineId', $this->lineA)->html();

    expect(laporanStorageTankRendered($html, 'metric-temperature-avg'))->toBe('55,0');
    expect(laporanStorageTankRendered($html, 'metric-temperature-reading-count'))->toBe('1');

    // The WHOLE card, explanatory note included: the Operator's 55,0 is on
    // it and the recomputed 60,0 — and a bare 60 — is nowhere on it. The
    // fixture supplies 50/60/70 alongside the recorded 55,0 so that a 60
    // could only arrive by recomputation.
    $card = laporanStorageTankSection($html, 'metric-card-temperature');

    expect($card)->not->toBe('');
    expect($card)->toContain('55,0');
    expect($card)->not->toContain('60,0');
    expect($card)->not->toContain('60');

    // The note that explains WHERE the number comes from is part of the
    // card's contract: it is an explanation box, never a threshold box.
    expect($card)->toContain('data-testid="temperature-source-note"');
    expect($html)->toContain('data-testid="temperature-source-note"');
    expect($html)->toContain('md-explain');
    expect(strtolower($html))->not->toContain('md-threshold');

    // The per-tank column reads the same column.
    expect(laporanStorageTankRow($html, 'by-tank-row-TK-01'))->toContain('55,0');
});

// =====================================================================
// Scenario 22: "setiap metrik punya penyebutnya sendiri"
// =====================================================================
it('penyebut terpisah: FFA 2 pembacaan rata-rata 4,0, kadar air 1 rata-rata 0,2 (bukan 0,07), DOBI 1', function () {
    laporanStorageTankComponentRecord($this->stationA, '2026-09-04', 'TK-01', [
        ['ffa_percent' => 3.0, 'moisture_content_percent' => 0.2, 'dobi_index' => null],
        ['ffa_percent' => 5.0, 'moisture_content_percent' => null, 'dobi_index' => null],
        ['ffa_percent' => null, 'moisture_content_percent' => null, 'dobi_index' => 2.4],
    ]);

    $html = Livewire::actingAs($this->supervisor)->test(LaporanStorageTank::class)->set('productionLineId', $this->lineA)->html();

    expect(laporanStorageTankRendered($html, 'metric-ffa-reading-count'))->toBe('2');
    expect(laporanStorageTankRendered($html, 'metric-ffa-avg'))->toBe('4,0');

    expect(laporanStorageTankRendered($html, 'metric-moisture-reading-count'))->toBe('1');
    expect(laporanStorageTankRendered($html, 'metric-moisture-avg'))->toBe('0,2');
    // 0,2 / 3 = 0,07 — the answer a single shared denominator would give.
    expect(laporanStorageTankRendered($html, 'metric-moisture-avg'))->not->toBe('0,07');

    expect(laporanStorageTankRendered($html, 'metric-dobi-reading-count'))->toBe('1');
    expect(laporanStorageTankRendered($html, 'metric-dobi-avg'))->toBe('2,4');

    // NOT ONE card carries the shared filled-row count of 3.
    expect(laporanStorageTankRendered($html, 'coverage-filled-slots'))->toBe('3');

    foreach (LAPORAN_STORAGE_TANK_METRIC_CARDS as $slug) {
        expect(laporanStorageTankRendered($html, 'metric-'.$slug.'-reading-count'))->not->toBe('3');
    }
});

// =====================================================================
// Scenario 23: "rentang periode inklusif di kedua ujung"
// =====================================================================
it('rentang inklusif: rekap harian memuat kedua tanggal ujung, dan tanggal di luar periode tidak muncul', function () {
    $period = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('storage-tank')
        ->range('2026-09-01', '2026-09-10')->named('Periode Inklusif')->open()->create();

    laporanStorageTankComponentRecord($this->stationA, '2026-08-31', 'TK-01', [
        ['time_slot' => '23:00', 'calculated_weight_mt' => 999.0],
    ]);
    laporanStorageTankComponentRecord($this->stationA, '2026-09-01', 'TK-01', [
        ['time_slot' => '00:00', 'calculated_weight_mt' => 111.0],
    ]);
    laporanStorageTankComponentRecord($this->stationA, '2026-09-10', 'TK-01', [
        ['time_slot' => '23:00', 'calculated_weight_mt' => 222.0],
    ]);
    laporanStorageTankComponentRecord($this->stationA, '2026-09-11', 'TK-01', [
        ['time_slot' => '00:00', 'calculated_weight_mt' => 888.0],
    ]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanStorageTank::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $period->id);

    $html = $component->html();

    // The daily recap is OPEN by default, so both end dates read straight
    // off the table without toggling anything.
    $component->assertSeeHtml('data-testid="daily-row-2026-09-01"');
    $component->assertSeeHtml('data-testid="daily-row-2026-09-10"');
    expect($html)->not->toContain('data-testid="daily-row-2026-08-31"');
    expect($html)->not->toContain('data-testid="daily-row-2026-09-11"');
    expect(substr_count($html, 'data-testid="daily-row-2026-'))->toBe(2);

    expect(laporanStorageTankRendered($html, 'opening-at'))->toBe('01 Sep 00:00');
    expect(laporanStorageTankRendered($html, 'closing-at'))->toBe('10 Sep 23:00');
    expect(laporanStorageTankRendered($html, 'stock-opening-mt'))->toBe('111,0');
    expect(laporanStorageTankRendered($html, 'stock-closing-mt'))->toBe('222,0');
    expect($html)->not->toContain('999,0');
    expect($html)->not->toContain('888,0');
});

// =====================================================================
// Scenario 24: "FFA, kadar air, dan DOBI pada satu grafik"
// =====================================================================
it('satu grafik untuk tiga metrik: SATU quality-trend-chart, dan legendanya menyatakan penanganan skalanya', function () {
    // Three ranges that cannot share one linear value axis: FFA ~3-5 %,
    // moisture ~0,1-0,3 %, DOBI ~2-4 without a unit.
    foreach ([
        ['2026-09-01', 3.2, 0.12, 3.8],
        ['2026-09-02', 4.1, 0.21, 3.1],
        ['2026-09-03', 4.9, 0.29, 2.4],
    ] as [$date, $ffa, $moisture, $dobi]) {
        laporanStorageTankComponentRecord($this->stationA, $date, 'TK-01', [
            ['time_slot' => '07:00', 'ffa_percent' => $ffa, 'moisture_content_percent' => $moisture,
                'dobi_index' => $dobi, 'calculated_weight_mt' => 1000.0],
        ]);
    }

    $component = Livewire::actingAs($this->supervisor)->test(LaporanStorageTank::class)
        ->set('productionLineId', $this->lineA);
    $html = $component->html();

    // ONE drawing area for the three series — not three separate charts.
    expect(substr_count($html, 'data-testid="quality-trend-chart"'))->toBe(1);
    expect($html)->not->toContain('data-testid="ffa-trend-chart"');
    expect($html)->not->toContain('data-testid="moisture-trend-chart"');
    expect($html)->not->toContain('data-testid="dobi-trend-chart"');

    // THE BINDING ASSERTION: the scale treatment is STATED in the legend.
    // Pinning three incomparable scales to one axis without a word would
    // draw two of the three as flat lines at the bottom and read as "nothing
    // changed".
    $legend = laporanStorageTankSlice($html, 'quality-trend-legend', 'quality-trend-note');

    expect($legend)->not->toBe('');
    expect($legend)->toContain('dinormalkan');
    expect($legend)->toContain('indeks 100');
    // Each of the three series says it, on its own legend row.
    expect(substr_count($legend, 'dinormalkan'))->toBeGreaterThanOrEqual(3);
    expect($legend)->toContain('FFA');
    expect($legend)->toContain('Kadar Air');
    expect($legend)->toContain('DOBI');

    // The stock chart states its own scale choice too: an axis that does
    // not start at zero.
    $stockLegend = laporanStorageTankSlice($html, 'stock-trend-legend', 'quality-trend-card');
    expect($stockLegend)->toContain('tidak dimulai dari nol');

    // And the RAW values remain, in full, on the recap below the chart —
    // which is what makes the normalisation hide nothing.
    $component->assertSeeHtml('data-testid="daily-table"');
    expect(laporanStorageTankRow($html, 'daily-row-2026-09-01'))->toContain('3,2');
    expect(laporanStorageTankRow($html, 'daily-row-2026-09-01'))->toContain('0,12');
    expect(laporanStorageTankRow($html, 'daily-row-2026-09-01'))->toContain('3,8');
});

// =====================================================================
// Scenario 25: "tidak ada penandaan nilai di luar batas"
// =====================================================================
it('nilai ekstrem: kartu ekstrem membawa atribut class yang IDENTIK dengan kartu biasa, dan tidak ada kosakata penandaan', function () {
    laporanStorageTankComponentRecord($this->stationA, '2026-09-04', 'TK-01', [
        ['ffa_percent' => 4.0, 'average_temperature_c' => 50.0, 'dobi_index' => 3.0,
            'moisture_content_percent' => 0.2, 'calculated_weight_mt' => 100.0],
        ['ffa_percent' => 42.0, 'average_temperature_c' => 150.0, 'dobi_index' => 3.1,
            'moisture_content_percent' => 0.21, 'calculated_weight_mt' => 90.0],
        ['ffa_percent' => 4.2, 'average_temperature_c' => 51.0, 'dobi_index' => 2.9,
            'moisture_content_percent' => 0.19],
    ]);

    $html = Livewire::actingAs($this->supervisor)->test(LaporanStorageTank::class)->set('productionLineId', $this->lineA)->html();

    foreach ([
        'threshold', 'outlier', 'iqr', 'fence', 'is_out_of_range', 'is-out-of-range',
        'severity', 'alert', 'text-red', 'bg-red', 'is-danger', 'is-warning',
    ] as $forbidden) {
        expect(strtolower($html))->not->toContain($forbidden);
    }

    // THE STRONGER FORM OF THE SAME RULE: the card holding the extreme FFA
    // and the card holding an ordinary DOBI carry the SAME class attribute,
    // byte for byte. There is no conditional branch on class on this page,
    // so an extreme value cannot change one character of the markup.
    expect(laporanStorageTankClassOf($html, 'metric-card-ffa'))
        ->toBe(laporanStorageTankClassOf($html, 'metric-card-dobi'));
    expect(laporanStorageTankClassOf($html, 'metric-card-ffa'))->not->toBeNull();

    // Same for the movement card, whose value is negative here.
    expect(laporanStorageTankClassOf($html, 'stock-movement-card'))
        ->toBe(laporanStorageTankClassOf($html, 'stock-opening-card'));

    // The extremes are rendered as-is: judging them is the reader's job.
    expect(laporanStorageTankRendered($html, 'metric-ffa-max'))->toBe('42,0');
    expect(laporanStorageTankRendered($html, 'metric-temperature-max'))->toBe('150,0');
});

// =====================================================================
// Scenario 26: "layar hanya membaca"
// =====================================================================
it('baca saja: tidak ada method publik yang menulis, tidak ada testid aksi tulis, dan jumlah baris tidak berubah', function () {
    $otherPeriod = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('storage-tank')
        ->range('2026-11-01', '2026-11-30')->named('Periode November')->open()->create();

    laporanStorageTankComponentRecord($this->stationA, '2026-09-04', 'TK-01', [
        ['time_slot' => '07:00', 'calculated_weight_mt' => 100.0, 'ffa_percent' => 4.0],
        ['time_slot' => '08:00', 'calculated_weight_mt' => 90.0],
    ]);
    laporanStorageTankComponentRecord($this->stationA, '2026-11-04', 'TK-02', [
        ['time_slot' => '07:00', 'calculated_weight_mt' => 300.0],
    ]);

    $recordsBefore = StorageTankRecord::count();
    $detailsBefore = StorageTankDetail::count();
    $checksumBefore = StorageTankDetail::query()->orderBy('id')->get()->toJson();

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanStorageTank::class)
        ->set('productionLineId', $this->lineA)
        ->set('periodId', (string) $otherPeriod->id)
        ->set('periodId', (string) $this->periodA->id)
        ->call('toggleDailyRecap')
        ->call('toggleDailyRecap');

    $component->call('exportCsv', 'csv')->assertFileDownloaded(null, null, 'text/csv');

    $html = $component->html();

    foreach (LAPORAN_STORAGE_TANK_WRITE_TESTIDS as $writeish) {
        expect($html)->not->toContain('data-testid="'.$writeish.'"');
    }

    // The component's PUBLIC SURFACE is a toggle, an export and three bound
    // properties — nothing that reads like a write action.
    $methods = array_map(
        fn (ReflectionMethod $method) => $method->getName(),
        (new ReflectionClass(LaporanStorageTank::class))->getMethods(ReflectionMethod::IS_PUBLIC)
    );

    foreach (['save', 'store', 'create', 'update', 'delete', 'destroy', 'submit'] as $writeish) {
        expect($methods)->not->toContain($writeish);
    }

    expect(StorageTankRecord::count())->toBe($recordsBefore);
    expect(StorageTankDetail::count())->toBe($detailsBefore);
    expect(StorageTankDetail::query()->orderBy('id')->get()->toJson())->toBe($checksumBefore);
});

// =====================================================================
// Scenario 27: "daftar periode hanya yang mencakup Storage Tank"
// =====================================================================
it('daftar periode: opsi memuat hanya periode yang punya baris period_stations storage-tank', function () {
    $periodOther = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('sterilizer')
        ->range('2026-10-01', '2026-10-31')->named('Periode A Stasiun Lain')->open()->create();
    $periodStorage = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('storage-tank')
        ->range('2026-11-01', '2026-11-30')->named('Periode B Storage Tank')->closed()->create();
    $periodAllTypes = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType(null)
        ->range('2026-12-01', '2026-12-31')->named('Periode C Semua Stasiun')->open()->create();

    $component = Livewire::actingAs($this->supervisor)->test(LaporanStorageTank::class)
        ->set('productionLineId', $this->lineA);

    $ids = array_column($component->viewData('periods'), 'id');

    expect($ids)->toContain((string) $periodStorage->id);
    expect($ids)->toContain((string) $periodAllTypes->id);
    expect($ids)->not->toContain((string) $periodOther->id);

    $html = $component->html();

    preg_match('/data-testid="period-select">(.*?)<\/select>/s', $html, $matches);
    $options = $matches[1] ?? '';

    expect($options)->toContain('Periode B Storage Tank');
    expect($options)->toContain('Periode C Semua Stasiun');
    expect($options)->not->toContain('Periode A Stasiun Lain');

    // A CLOSED period is offered exactly like an open one. Statusnya kini datang
    // dari baris period_stations 'storage-tank', bukan dari periodenya.
    expect($options)->toContain('Tertutup');

    // 'Periode C Semua Stasiun' dibuat dengan stationType(null), yang sejak
    // 2026-09-25 berarti "satu baris period_stations per jenis stasiun" — bukan
    // station_type NULL. Setiap opsi karena itu berlabel jenis stasiun layar
    // ini; label bersama ALL_STATION_TYPES_LABEL ('Semua Stasiun') ikut dihapus.
    expect(array_values(array_unique(array_column($component->viewData('periods'), 'station_type_label'))))
        ->toBe(['Storage Tank']);
});

// =====================================================================
// Scenario (BARU 2026-09-26): periode tanpa baris period_stations untuk
// storage-tank tidak ditawarkan — perilaku yang DULU dijamin cabang
// orWhereNull('station_type') dan kini sengaja dibuang.
// =====================================================================
it('daftar periode: periode tanpa baris storage-tank tidak ditawarkan', function () {
    Period::factory()->forBusinessUnit($this->businessUnitA)
        ->stationTypes(['sterilizer', 'boiler-room'])
        ->range('2026-10-01', '2026-10-31')->named('Periode Tanpa Storage Tank')->open()->create();
    Period::factory()->forBusinessUnit($this->businessUnitA)
        ->noStations()->range('2026-09-01', '2026-09-30')->named('Periode Tanpa Stasiun')->create();

    $component = Livewire::actingAs($this->supervisor)->test(LaporanStorageTank::class)
        ->set('productionLineId', $this->lineA)
        ->assertDontSee('Periode Tanpa Storage Tank')
        ->assertDontSee('Periode Tanpa Stasiun');

    expect(array_column($component->viewData('periods'), 'id'))
        ->toBe([(string) $this->periodA->id]);
});

// =====================================================================
// Scenario (BARU 2026-09-26): status opsi memakai status storage-tank, bukan
// status stasiun lain di periode yang sama.
// =====================================================================
it('daftar periode: status opsi memakai status storage-tank, bukan status stasiun lain', function () {
    $mixed = Period::factory()->forBusinessUnit($this->businessUnitA)
        ->noStations()->range('2026-12-01', '2026-12-31')->named('Periode Campuran')->create();

    PeriodStation::factory()->forPeriod($mixed)->stationType('storage-tank')->open()->create();
    PeriodStation::factory()->forPeriod($mixed)->stationType('sterilizer')->closed()->create();

    $component = Livewire::actingAs($this->supervisor)->test(LaporanStorageTank::class)
        ->set('productionLineId', $this->lineA);

    $option = collect($component->viewData('periods'))->firstWhere('id', (string) $mixed->id);

    expect($option['status'])->toBe('open');
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
// memilih mill lalu menekan tile Storage Tank MENDARAT DI LAYAR YANG
// MEMINTANYA MEMILIH MILL LAGI, tanpa satu angka pun termuat — persis
// seperti sebelum perbaikan, dan tanpa satu test pun memerah. Itulah yang
// ditutup di sini.
//
// Asersinya sengaja PERILAKU dan bukan refleksi atas atributnya: membaca
// atribut PHP hanya menguji ejaan, dan tetap hijau kalau Livewire mengubah
// semantik `as:`.
// =====================================================================
it('hidrasi query string: Admin yang tiba dari tautan tile langsung melihat laporan mill itu, bukan permintaan memilih mill lagi', function () {
    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('storage-tank')
        ->range('2026-09-01', '2026-09-30')->named('Periode September Beta')->create();

    laporanStorageTankComponentRecord($this->stationA, '2026-09-10', 'TK-01', [['calculated_weight_mt' => 100.0]]);
    laporanStorageTankComponentRecord($this->stationB, '2026-09-10', 'TK-09', [['calculated_weight_mt' => 999.0]]);

    // (a) Permukaan HTTP — URL yang bentuknya persis seperti yang dibangun
    // StationReportService untuk tile Storage Tank.
    $response = $this->actingAs($this->admin, 'web')
        ->get(route('reports.storage-tank', ['business_unit_id' => $this->businessUnitA->id]));

    $response->assertOk();
    $response->assertDontSee('Pilih mill terlebih dahulu');
    $response->assertSee('Periode September Alpha');
    $response->assertDontSee('Periode September Beta');

    // (b) Permukaan komponen — propertinya benar-benar terhidrasi, periode
    // mill itu ikut termuat, dan angkanya berasal dari mill itu saja.
    Livewire::actingAs($this->admin)
        ->withQueryParams(['business_unit_id' => (string) $this->businessUnitA->id])
        ->test(LaporanStorageTank::class)
        ->set('productionLineId', $this->lineA)
        ->assertSet('businessUnitId', (string) $this->businessUnitA->id)
        ->assertSet('periodId', (string) $this->periodA->id)
        ->assertViewHas('needsMillSelection', false)
        ->assertDontSeeHtml('data-testid="mill-select-hint"')
        ->assertViewHas('summary', fn ($summary) => $summary !== null
            && $summary['period']['business_unit_name'] === 'Mill Alpha'
            && $summary['period']['name'] === 'Periode September Alpha');

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
    Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('storage-tank')
        ->range('2026-09-01', '2026-09-30')->named('Periode September Beta')->create();

    laporanStorageTankComponentRecord($this->stationA, '2026-09-10', 'TK-01', [['calculated_weight_mt' => 100.0]]);
    laporanStorageTankComponentRecord($this->stationB, '2026-09-10', 'TK-09', [['calculated_weight_mt' => 999.0]]);

    foreach ([$this->supervisor, $this->millManagement] as $user) {
        $response = $this->actingAs($user, 'web')
            ->get(route('reports.storage-tank', ['business_unit_id' => $this->businessUnitB->id]));

        $response->assertOk();
        $response->assertSee('Periode September Alpha');
        $response->assertDontSee('Periode September Beta');
        $response->assertDontSee('Mill Beta');

        Livewire::actingAs($user)
            ->withQueryParams(['business_unit_id' => (string) $this->businessUnitB->id])
            ->test(LaporanStorageTank::class)
            ->set('productionLineId', $this->lineA)
            // Terhidrasi — dan tetap diabaikan.
            ->assertSet('businessUnitId', (string) $this->businessUnitB->id)
            ->assertSet('periodId', (string) $this->periodA->id)
            ->assertViewHas('summary', fn ($summary) => $summary !== null
                && $summary['period']['business_unit_name'] === 'Mill Alpha'
                && $summary['period']['name'] === 'Periode September Alpha');
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
 * Line KEDUA di MILL YANG SAMA, lengkap dengan stasiun Storage Tank-nya
 * sendiri. Sengaja satu mill: jaminan yang diuji di sini bukan cakupan mill
 * (itu sudah ditutup ec32cd9) melainkan cakupan LINE DI DALAM satu mill.
 */
function laporanStorageTankSecondLine(BusinessUnit $businessUnit, string $name = 'Line Kedua'): Station
{
    $line = ProductionLine::factory()->create([
        'business_unit_id' => $businessUnit->id,
        'name' => $name,
    ]);

    return Station::factory()->forProductionLine($line)->storageTank()->create();
}

/** Isi berkas CSV yang benar-benar diunduh dari layar. */
function laporanStorageTankDownloadedCsv(Testable $component): string
{
    return base64_decode((string) data_get($component->effects, 'download.content'));
}

it('production line: tanpa line terpilih tidak ada satu angka pun, hanya arahan memilih', function () {
    // Line A: 2 slot terisi, berat 100,0 MT.
    laporanStorageTankComponentRecord($this->stationA, '2026-09-05', 'TK-LINE-A', [
        ['calculated_weight_mt' => 100.0],
        ['calculated_weight_mt' => 100.0],
    ]);

    Livewire::actingAs($this->supervisor)
        ->test(LaporanStorageTank::class)
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
        ->assertDontSeeHtml('data-testid="by-tank-table"');
});

it('production line: angka yang tampil milik line terpilih, bukan jumlah dua line', function () {
    // Line A: 2 slot terisi, berat 100,0 MT.
    laporanStorageTankComponentRecord($this->stationA, '2026-09-05', 'TK-LINE-A', [
        ['calculated_weight_mt' => 100.0],
        ['calculated_weight_mt' => 100.0],
    ]);

    $stationC = laporanStorageTankSecondLine($this->businessUnitA);
    $lineC = (string) $stationC->production_line_id;
    // Line C: 5 slot terisi, 500,0 MT. Rata-rata gabungan 385,7 — bukan salah
    // satu dari keduanya, sehingga pencampuran ketahuan.
    laporanStorageTankComponentRecord($stationC, '2026-09-06', 'TK-LINE-C', [
        ['calculated_weight_mt' => 500.0],
        ['calculated_weight_mt' => 500.0],
        ['calculated_weight_mt' => 500.0],
        ['calculated_weight_mt' => 500.0],
        ['calculated_weight_mt' => 500.0],
    ]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanStorageTank::class)
        ->set('productionLineId', $this->lineA);

    // ARAH PERTAMA — line A: angkanya milik A, dan BUKAN A+C.
    $component->assertViewHas('summary', fn ($summary) => $summary['coverage']['filled_slots'] === 2
        && $summary['metrics']['calculated_weight_mt']['avg'] === 100.0
        && $summary['metrics']['calculated_weight_mt']['reading_count'] === 2);

    // ARAH KEDUA — line C: angkanya berpindah seluruhnya ke C. Tanpa arah ini
    // sebuah filter yang menyaring habis juga akan hijau.
    $component->set('productionLineId', $lineC)
        ->assertViewHas('summary', fn ($summary) => $summary['coverage']['filled_slots'] === 5
            && $summary['metrics']['calculated_weight_mt']['avg'] === 500.0
            && $summary['metrics']['calculated_weight_mt']['reading_count'] === 5);
});

it('production line: line mill lain diabaikan, lewat properti maupun lewat query string', function () {
    // Line A: 2 slot terisi, berat 100,0 MT.
    laporanStorageTankComponentRecord($this->stationA, '2026-09-05', 'TK-LINE-A', [
        ['calculated_weight_mt' => 100.0],
        ['calculated_weight_mt' => 100.0],
    ]);

    // (a) Lewat properti — dibuang saat render, jatuh ke "belum memilih".
    Livewire::actingAs($this->supervisor)
        ->test(LaporanStorageTank::class)
        ->set('productionLineId', $this->lineB)
        ->assertSet('productionLineId', '')
        ->assertViewHas('needsProductionLineSelection', true)
        ->assertViewHas('summary', null);

    // (b) Lewat query string — sama saja.
    Livewire::actingAs($this->supervisor)
        ->withQueryParams(['production_line_id' => $this->lineB])
        ->test(LaporanStorageTank::class)
        ->assertSet('productionLineId', '')
        ->assertViewHas('summary', null);

    // (c) SISI POSITIFNYA, dan inilah yang menjaga `as: 'production_line_id'`:
    // line yang sah dari query string BENAR-BENAR terhidrasi dan langsung
    // memuat laporannya. Tanpa `as:`, Livewire memakai nama properti
    // ('productionLineId') sebagai kunci query, keduanya tidak bertemu, dan
    // asersi (a)/(b) di atas tetap hijau tanpa menandai apa pun.
    Livewire::actingAs($this->supervisor)
        ->withQueryParams(['production_line_id' => $this->lineA])
        ->test(LaporanStorageTank::class)
        ->assertSet('productionLineId', $this->lineA)
        ->assertViewHas('needsProductionLineSelection', false)
        ->assertViewHas('summary', fn ($summary) => $summary !== null && $summary['coverage']['filled_slots'] === 2
        && $summary['metrics']['calculated_weight_mt']['avg'] === 100.0
        && $summary['metrics']['calculated_weight_mt']['reading_count'] === 2);
});

it('production line: ekspor CSV hanya memuat baris line terpilih', function () {
    // Line A: 2 slot terisi, berat 100,0 MT.
    laporanStorageTankComponentRecord($this->stationA, '2026-09-05', 'TK-LINE-A', [
        ['calculated_weight_mt' => 100.0],
        ['calculated_weight_mt' => 100.0],
    ]);

    $stationC = laporanStorageTankSecondLine($this->businessUnitA);
    $lineC = (string) $stationC->production_line_id;
    // Line C: 5 slot terisi, 500,0 MT. Rata-rata gabungan 385,7 — bukan salah
    // satu dari keduanya, sehingga pencampuran ketahuan.
    laporanStorageTankComponentRecord($stationC, '2026-09-06', 'TK-LINE-C', [
        ['calculated_weight_mt' => 500.0],
        ['calculated_weight_mt' => 500.0],
        ['calculated_weight_mt' => 500.0],
        ['calculated_weight_mt' => 500.0],
        ['calculated_weight_mt' => 500.0],
    ]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanStorageTank::class)
        ->set('productionLineId', $this->lineA);

    $component->call('exportCsv', 'csv')->assertFileDownloaded(null, null, 'text/csv');

    $csv = laporanStorageTankDownloadedCsv($component);

    expect($csv)->toContain('TK-LINE-A');
    expect($csv)->not->toContain('TK-LINE-C');

    // Arah sebaliknya, berkas yang sama sekali berbeda isinya.
    $component->set('productionLineId', $lineC)->call('exportCsv', 'csv');

    $csvC = laporanStorageTankDownloadedCsv($component);

    expect($csvC)->toContain('TK-LINE-C');
    expect($csvC)->not->toContain('TK-LINE-A');
});

it('production line: tanpa line terpilih tidak ada berkas yang diunduh sama sekali', function () {
    // Line A: 2 slot terisi, berat 100,0 MT.
    laporanStorageTankComponentRecord($this->stationA, '2026-09-05', 'TK-LINE-A', [
        ['calculated_weight_mt' => 100.0],
        ['calculated_weight_mt' => 100.0],
    ]);

    Livewire::actingAs($this->supervisor)
        ->test(LaporanStorageTank::class)
        ->call('exportCsv', 'csv')
        ->assertNoFileDownloaded();
});

it('production line: record yang stasiunnya sudah dipindah tetap terhitung di line asalnya', function () {
    // Line A: 2 slot terisi, berat 100,0 MT.
    laporanStorageTankComponentRecord($this->stationA, '2026-09-05', 'TK-LINE-A', [
        ['calculated_weight_mt' => 100.0],
        ['calculated_weight_mt' => 100.0],
    ]);

    // Stasiunnya dipindah ke line lain DI MILL YANG SAMA — perubahan
    // konfigurasi yang sah, bukan perbaikan data.
    $lineBaru = ProductionLine::factory()->create([
        'business_unit_id' => $this->businessUnitA->id,
        'name' => 'Line Baru',
    ]);

    $this->stationA->update(['production_line_id' => $lineBaru->id]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanStorageTank::class);

    // DI LINE ASALNYA: masih terhitung utuh. Hanya mungkin karena filternya
    // membaca kolom `production_line_id` DI TABEL RECORD — sebuah join ke
    // `stations` akan memindahkan angka ini ke Line Baru dan menulis ulang
    // sejarah periode yang sudah lewat.
    $component->set('productionLineId', $this->lineA)
        ->assertViewHas('summary', fn ($summary) => $summary['coverage']['filled_slots'] === 2
        && $summary['metrics']['calculated_weight_mt']['avg'] === 100.0
        && $summary['metrics']['calculated_weight_mt']['reading_count'] === 2);

    // DI LINE BARUNYA: tidak ada apa pun. Stasiunnya memang ada di sana
    // sekarang, tetapi tidak satu pun record dihasilkan di sana.
    $component->set('productionLineId', (string) $lineBaru->id)
        ->assertViewHas('summary', fn ($summary) => $summary['coverage']['filled_slots'] === 0 && $summary['has_data'] === false);
});
