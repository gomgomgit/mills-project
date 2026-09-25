<?php

/**
 * LaporanCagesTrackTest (Feature/Livewire) — screen-130--laporan-cages-track-web /
 * usecase-130--laporan-cages-track-web (Laporan Periode Cages & Tracks).
 *
 * Component tests for App\Livewire\Dashboard\LaporanCagesTrack, one per
 * test_scenarios entry's `component_test`. Mirrors
 * tests/Feature/Livewire/LaporanSterilizerTest.php (screen-129, this
 * screen's twin).
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
 * other does not.
 *
 * THE MILL IS NEVER NEGOTIABLE FROM THE UI for a bound role: the
 * "mill lain" scenario forces the public `businessUnitId` property to
 * another mill and asserts that not one figure moves.
 */

use App\Enums\UserRole;
use App\Livewire\Dashboard\LaporanCagesTrack;
use App\Models\BusinessUnit;
use App\Models\CagesTippedTime;
use App\Models\CagesTrackRecord;
use App\Models\Period;
use App\Models\Station;
use App\Models\User;
use Livewire\Livewire;

/**
 * One cages_track_records header plus one cages_tipped_times row per entry
 * of $details.
 *
 * `cages_tipped` defaults to 999 ON PURPOSE — it is the header summary
 * field the screen must never render, so a regression that prints it is
 * unmistakable rather than plausible.
 *
 * @param  list<array{hour: int, cages?: int, remain?: int}>  $details
 */
function laporanCagesTrackComponentRecord(Station $station, string $date, array $details = [], array $overrides = []): CagesTrackRecord
{
    $record = CagesTrackRecord::factory()->forStation($station)->onDate($date)->create(array_merge([
        'tippler_start_time' => $date.' 06:00:00',
        'tippler_stop_time' => $date.' 18:00:00',
        'cages_out' => 0,
        'cages_tipped' => 999,
    ], $overrides));

    foreach ($details as $detail) {
        CagesTippedTime::factory()->forRecord($record)->create([
            'tipped_hour' => $detail['hour'],
            'total_cages' => $detail['cages'] ?? 1,
            'cages_remain' => $detail['remain'] ?? 10,
            'checked_cage_numbers' => $detail['numbers'] ?? '1,2',
        ]);
    }

    return $record;
}

/** Shorthand: tipping hours with one cage each. */
function laporanCagesTrackComponentHours(Station $station, string $date, array $hours, array $overrides = []): CagesTrackRecord
{
    return laporanCagesTrackComponentRecord($station, $date, array_map(
        fn ($hour) => ['hour' => $hour],
        $hours,
    ), $overrides);
}

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->stationA = Station::factory()->forBusinessUnit($this->businessUnitA)->cagesTrack()->create();
    $this->stationB = Station::factory()->forBusinessUnit($this->businessUnitB)->cagesTrack()->create();

    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    $this->periodA = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('cages-track')
        ->range('2026-03-01', '2026-03-31')
        ->named('Periode Maret Alpha')
        ->open()
        ->create();
});

// =====================================================================
// Scenario 1: "berhasil sebagai Supervisor atau Mill Management"
// =====================================================================
it('berhasil: no mill picker, the mill caption, every KPI card, the charts, the queue card and the recap', function () {
    laporanCagesTrackComponentRecord($this->stationA, '2026-03-02', [
        ['hour' => 6, 'cages' => 30, 'remain' => 12],
        ['hour' => 7, 'cages' => 20, 'remain' => 16],
        ['hour' => 8, 'cages' => 10, 'remain' => 20],
    ], ['cages_out' => 55]);
    laporanCagesTrackComponentRecord($this->stationA, '2026-03-05', [
        ['hour' => 6, 'cages' => 25, 'remain' => 14],
        ['hour' => 14, 'cages' => 15, 'remain' => 18],
    ], ['cages_out' => 35]);

    $recordsBefore = CagesTrackRecord::count();
    $detailsBefore = CagesTippedTime::count();

    foreach ([$this->supervisor, $this->millManagement] as $user) {
        $component = Livewire::actingAs($user)
            ->test(LaporanCagesTrack::class)
            // The newest period is auto-selected, so the page is useful on
            // first paint rather than demanding a choice first.
            ->assertSet('periodId', (string) $this->periodA->id)
            // Offering a picker they cannot use would be a lie.
            ->assertDontSeeHtml('data-testid="mill-select"')
            ->assertSeeHtml('data-testid="mill-current"')
            ->assertSee('Mill Alpha')
            ->assertSeeHtml('data-testid="hero-range"')
            ->assertSeeHtml('data-testid="hero-status"')
            ->assertSeeHtml('data-testid="period-select"')
            ->assertSeeHtml('data-testid="report-kpis"')
            ->assertSeeHtml('data-testid="kpi-total-tipped"')
            ->assertSeeHtml('data-testid="kpi-total-out"')
            ->assertSeeHtml('data-testid="kpi-avg-per-day"')
            ->assertSeeHtml('data-testid="kpi-peak-hour"')
            ->assertSeeHtml('data-testid="kpi-idle-hours"')
            ->assertSeeHtml('data-testid="kpi-longest-gap"')
            ->assertSeeHtml('data-testid="kpi-tippler-duration"')
            ->assertSeeHtml('data-testid="days-without-valid-window"')
            ->assertSeeHtml('data-testid="hourly-distribution"')
            ->assertSeeHtml('data-testid="daily-trend"')
            ->assertSeeHtml('data-testid="queue-card"')
            ->assertSeeHtml('data-testid="queue-min"')
            ->assertSeeHtml('data-testid="queue-avg"')
            ->assertSeeHtml('data-testid="daily-recap"')
            ->assertDontSeeHtml('data-testid="empty-period"')
            ->assertViewHas('summary', fn ($summary) => $summary['kpi']['total_cages_tipped'] === 100
                && $summary['kpi']['total_cages_out'] === 90
                && $summary['kpi']['avg_cages_per_day'] === 50.0
                && $summary['kpi']['peak_hour'] === 6
                && $summary['kpi']['longest_gap_hours'] === 8
                && $summary['kpi']['avg_tippler_duration_hours'] === 12.0);

        // The daily recap opens and carries a period Total row.
        $component->call('toggleRekapHarian')
            ->assertSet('showRecap', true)
            ->assertSeeHtml('data-testid="recap-table"')
            ->assertSeeHtml('data-testid="recap-row-2026-03-02"')
            ->assertSeeHtml('data-testid="recap-row-2026-03-05"')
            ->assertSeeHtml('data-testid="recap-row-total"')
            ->assertSee('TOTAL PERIODE');
    }

    // Read-only: rendering changes no station data.
    expect(CagesTrackRecord::count())->toBe($recordsBefore);
    expect(CagesTippedTime::count())->toBe($detailsBefore);
});

// =====================================================================
// Scenario 2: "berhasil sebagai Admin"
// =====================================================================
it('admin: the mill picker is rendered, and picking a mill then a period fills the whole report', function () {
    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('cages-track')
        ->range('2026-03-01', '2026-03-31')->named('Periode Maret Beta')->create();

    laporanCagesTrackComponentRecord($this->stationA, '2026-03-10', [['hour' => 6, 'cages' => 12]], ['cages_out' => 9]);
    laporanCagesTrackComponentRecord($this->stationB, '2026-03-10', [['hour' => 6, 'cages' => 500]], ['cages_out' => 500]);

    Livewire::actingAs($this->admin)
        ->test(LaporanCagesTrack::class)
        ->assertSet('businessUnitId', '')
        ->assertSeeHtml('data-testid="mill-select"')
        ->assertSee('Mill Alpha')
        ->assertSee('Mill Beta')
        ->set('businessUnitId', (string) $this->businessUnitA->id)
        // keepSelectionValid() auto-selects the newest period of the newly
        // chosen mill — no updatedBusinessUnitId() hook needed.
        ->assertSet('periodId', (string) $this->periodA->id)
        ->assertDontSeeHtml('data-testid="mill-required-hint"')
        ->assertSeeHtml('data-testid="report-kpis"')
        ->assertSeeHtml('data-testid="hourly-distribution"')
        ->assertSeeHtml('data-testid="daily-trend"')
        ->assertSeeHtml('data-testid="queue-card"')
        ->assertSeeHtml('data-testid="daily-recap"')
        ->assertSee('Periode Maret Alpha')
        ->assertDontSee('Periode Maret Beta')
        // Mill A only — Mill B's 500 cages are nowhere in the figures.
        ->assertViewHas('summary', fn ($summary) => $summary['period']['business_unit_name'] === 'Mill Alpha'
            && $summary['kpi']['total_cages_tipped'] === 12
            && $summary['kpi']['total_cages_out'] === 9);

    expect($periodB->fresh())->not->toBeNull();
});

// =====================================================================
// Scenario 3: "ekspor rincian per jam ke CSV"
// =====================================================================
it('ekspor: the export action streams a CSV whose filename carries the period name', function () {
    laporanCagesTrackComponentRecord($this->stationA, '2026-03-02', [
        ['hour' => 6, 'cages' => 3],
        ['hour' => 7, 'cages' => 2],
    ], ['cages_track_number' => 'CT-EXPORT-001', 'cages_out' => 12, 'note' => 'Catatan harian']);

    $recordsBefore = CagesTrackRecord::count();
    $detailsBefore = CagesTippedTime::count();

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanCagesTrack::class)
        ->assertSeeHtml('data-testid="export-csv"')
        ->assertSeeHtml('data-testid="export-excel"');

    $download = $component->call('export', 'csv');
    $download->assertFileDownloaded(null, null, 'text/csv');

    $effect = $download->effects['download'];

    // The filename carries the period name, so a downloaded file is
    // identifiable without opening it.
    expect($effect['name'])->toContain('laporan-cages-track');
    expect($effect['name'])->toContain('periode-maret-alpha');
    expect($effect['name'])->toEndWith('.csv');

    // One line per TIPPING HOUR with the record's context columns repeated
    // — byte-for-byte what /api/cages-track-reports/export returns, because
    // both go through the same service.
    $body = base64_decode($effect['content']);
    $lines = array_values(array_filter(explode("\n", trim($body))));

    expect($lines)->toHaveCount(3);
    expect($lines[0])->toContain('Nomor Cages Track');

    foreach (array_slice($lines, 1) as $line) {
        expect($line)->toContain('CT-EXPORT-001');
        expect($line)->toContain('2026-03-02');
        expect($line)->toContain('Catatan harian');
    }

    // Excel is served through the same path, under the xlsx metadata.
    $excel = $component->call('export', 'excel');
    expect($excel->effects['download']['name'])->toEndWith('.xlsx');

    // Read-only: exporting changes nothing.
    expect(CagesTrackRecord::count())->toBe($recordsBefore);
    expect(CagesTippedTime::count())->toBe($detailsBefore);
});

// =====================================================================
// Scenario 4: "Periode tanpa data"
// =====================================================================
it('periode tanpa data: the KPI cards render as zero with an explicit notice and no empty charts', function () {
    laporanCagesTrackComponentHours($this->stationA, '2026-02-28', [6]);

    Livewire::actingAs($this->supervisor)
        ->test(LaporanCagesTrack::class)
        ->set('periodId', (string) $this->periodA->id)
        ->assertSeeHtml('data-testid="report-kpis"')
        ->assertSeeHtml('data-testid="kpi-total-tipped"')
        ->assertSeeHtml('data-testid="kpi-total-out"')
        ->assertSeeHtml('data-testid="empty-period"')
        ->assertSee('Belum ada data pada periode ini')
        // The charts are not forced to draw empty bars.
        ->assertDontSeeHtml('data-testid="hourly-distribution"')
        ->assertDontSeeHtml('data-testid="daily-trend"')
        ->assertDontSeeHtml('data-testid="queue-card"')
        ->assertDontSeeHtml('data-testid="recap-table"')
        ->assertViewHas('summary', fn ($summary) => $summary['kpi']['total_cages_tipped'] === 0
            && $summary['kpi']['total_cages_out'] === 0
            && $summary['kpi']['peak_hour'] === null
            && $summary['daily'] === []);
});

// =====================================================================
// Scenario 5: "Hari dengan record tetapi tanpa rincian per jam"
// =====================================================================
it('hari tanpa rincian: the recap keeps a row of 0 for that date and it still divides the daily average', function () {
    laporanCagesTrackComponentRecord($this->stationA, '2026-03-02', [['hour' => 6, 'cages' => 40]]);
    laporanCagesTrackComponentRecord($this->stationA, '2026-03-03', []);
    laporanCagesTrackComponentRecord($this->stationA, '2026-03-04', [['hour' => 6, 'cages' => 50]]);

    Livewire::actingAs($this->supervisor)
        ->test(LaporanCagesTrack::class)
        ->set('periodId', (string) $this->periodA->id)
        ->call('toggleRekapHarian')
        ->assertSeeHtml('data-testid="recap-row-2026-03-02"')
        // Present, not dropped — dropping it would make the daily average
        // look better than reality.
        ->assertSeeHtml('data-testid="recap-row-2026-03-03"')
        ->assertSeeHtml('data-testid="recap-row-2026-03-04"')
        ->assertSeeHtml('data-testid="kpi-avg-per-day"')
        // 90 / 3 = 30, not 90 / 2 = 45.
        ->assertSee('90 lori dibagi 3 hari ber-record')
        ->assertViewHas('summary', fn ($summary) => $summary['kpi']['days_with_records'] === 3
            && $summary['kpi']['avg_cages_per_day'] === 30.0
            && $summary['daily'][1]['cages_tipped'] === 0);
});

// =====================================================================
// Scenario 6: "Seluruh hari tanpa waktu berhenti tippler"
// =====================================================================
it('tanpa waktu berhenti: the duration card says unavailable rather than zero, next to the excluded-day count', function () {
    foreach (['2026-03-02', '2026-03-03', '2026-03-04'] as $date) {
        laporanCagesTrackComponentRecord($this->stationA, $date, [['hour' => 6, 'cages' => 5]], [
            'tippler_start_time' => $date.' 06:00:00',
            'tippler_stop_time' => null,
        ]);
    }

    Livewire::actingAs($this->supervisor)
        ->test(LaporanCagesTrack::class)
        ->set('periodId', (string) $this->periodA->id)
        ->assertSeeHtml('data-testid="kpi-tippler-duration"')
        ->assertSee('Durasi operasi tidak tersedia')
        // ALWAYS rendered beside it: the average must never be read without
        // knowing how many days were left out.
        ->assertSeeHtml('data-testid="days-without-valid-window"')
        ->assertSee('3 hari tanpa waktu berhenti tippler yang sah')
        // The recap writes "tidak tercatat", never "0 jam".
        ->call('toggleRekapHarian')
        ->assertSee('tidak tercatat')
        ->assertViewHas('summary', fn ($summary) => $summary['kpi']['avg_tippler_duration_hours'] === null
            && $summary['kpi']['days_without_valid_window'] === 3);
});

// =====================================================================
// Scenario 7: "Penumpahan hanya pada satu jam"
// =====================================================================
it('satu jam saja: the gap card states there is nothing to measure and never prints 0 jam', function () {
    laporanCagesTrackComponentRecord($this->stationA, '2026-03-02', [['hour' => 10, 'cages' => 8]]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanCagesTrack::class)
        ->set('periodId', (string) $this->periodA->id)
        ->assertSeeHtml('data-testid="kpi-longest-gap"')
        ->assertSeeHtml('data-testid="insufficient-gap"')
        ->assertSee('Tidak ada jeda yang dapat dihitung')
        ->assertViewHas('summary', fn ($summary) => $summary['kpi']['longest_gap_hours'] === null
            && $summary['kpi']['longest_gap_date'] === null);

    // 0 would claim there was no pause; the card must not say that.
    $html = $component->html();
    $card = substr($html, strpos($html, 'data-testid="kpi-longest-gap"'));
    $card = substr($card, 0, strpos($card, '</article>'));

    expect($card)->not->toContain('0 <span>jam</span>');
});

// =====================================================================
// Scenario 8: "Operasi melewati tengah malam"
// =====================================================================
it('lintas tengah malam: the recap shows 6 hours (never negative) and idle follows the circular hour set', function () {
    laporanCagesTrackComponentRecord($this->stationA, '2026-03-02', [
        ['hour' => 1, 'cages' => 3],
        ['hour' => 22, 'cages' => 2],
    ], [
        'tippler_start_time' => '2026-03-02 22:00:00',
        'tippler_stop_time' => '2026-03-03 04:00:00',
    ]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanCagesTrack::class)
        ->set('periodId', (string) $this->periodA->id)
        ->call('toggleRekapHarian')
        ->assertSeeHtml('data-testid="recap-row-2026-03-02"')
        ->assertSee('6 jam')
        ->assertSeeHtml('data-testid="kpi-tippler-duration"')
        ->assertSeeHtml('data-testid="kpi-idle-hours"')
        // Window {22,23,0,1,2,3}, tipped {1,22} -> idle {23,0,2,3} = 4.
        ->assertViewHas('summary', fn ($summary) => $summary['daily'][0]['operating_hours'] === 6.0
            && $summary['kpi']['avg_tippler_duration_hours'] === 6.0
            && $summary['kpi']['idle_operating_hours'] === 4);

    $html = $component->html();

    // No negative duration anywhere the reader actually looks: not in the
    // recap row, not on the duration card. (-18 is what the hour-component
    // subtraction would have produced for 22:00 -> 04:00.)
    $recapRow = substr($html, strpos($html, 'data-testid="recap-row-2026-03-02"'));
    $recapRow = substr($recapRow, 0, strpos($recapRow, '</tr>'));

    expect($recapRow)->toContain('6 jam');
    expect($recapRow)->not->toContain('-18');
    expect(preg_match('/>\s*-\d/', $recapRow))->toBe(0);

    $durationCard = substr($html, strpos($html, 'data-testid="kpi-tippler-duration"'));
    $durationCard = substr($durationCard, 0, strpos($durationCard, '</article>'));

    expect($durationCard)->not->toContain('-18');
    expect(preg_match('/>\s*-\d/', $durationCard))->toBe(0);

    // All 24 hour columns are still drawn, and the circular window hours are
    // the ones flagged as operating hours.
    foreach (range(0, 23) as $hour) {
        expect($html)->toContain('data-testid="hourly-col-'.$hour.'"');
    }

    foreach ([22, 23, 0, 1, 2, 3] as $hour) {
        $column = substr($html, strpos($html, 'data-testid="hourly-col-'.$hour.'"'));

        expect(substr($column, 0, 200))->toContain('data-within-window="1"');
    }

    foreach ([4, 5, 12, 21] as $hour) {
        $column = substr($html, strpos($html, 'data-testid="hourly-col-'.$hour.'"'));

        expect(substr($column, 0, 200))->toContain('data-within-window="0"');
    }
});

// =====================================================================
// Scenario 9: "Admin belum memilih mill"
// =====================================================================
it('admin tanpa mill: the page asks for a mill instead of drawing an empty report', function () {
    Livewire::actingAs($this->admin)
        ->test(LaporanCagesTrack::class)
        ->assertSet('businessUnitId', '')
        ->assertSeeHtml('data-testid="mill-select"')
        ->assertSeeHtml('data-testid="mill-required-hint"')
        ->assertSee('Pilih mill terlebih dahulu')
        // No period picker, no KPI, no charts — and no error either.
        ->assertDontSeeHtml('data-testid="period-select"')
        ->assertDontSeeHtml('data-testid="report-kpis"')
        ->assertDontSeeHtml('data-testid="hourly-distribution"')
        ->assertDontSeeHtml('data-testid="empty-period"')
        ->assertDontSeeHtml('data-testid="no-mill-for-account"');
});

// =====================================================================
// Scenario 10: "Akun terikat mill tetapi mill-nya kosong"
// =====================================================================
it('akun tanpa mill: a contact-Admin notice and NO mill picker offered in its place', function () {
    $noMill = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);

    Livewire::actingAs($noMill)
        ->test(LaporanCagesTrack::class)
        ->assertSeeHtml('data-testid="no-mill-for-account"')
        ->assertSee('Akun Anda belum terhubung ke mill')
        ->assertSee('Hubungi Admin')
        // FAIL CLOSED: handing the full list of every mill to a role that is
        // supposed to be tied to exactly one would turn a broken master-data
        // row into a cross-mill leak.
        ->assertDontSeeHtml('data-testid="mill-select"')
        ->assertDontSeeHtml('data-testid="mill-current"')
        ->assertDontSeeHtml('data-testid="period-select"')
        ->assertDontSeeHtml('data-testid="report-kpis"')
        ->assertDontSee('Mill Alpha')
        ->assertDontSee('Mill Beta');
});

// =====================================================================
// Scenario 11: "Mill belum punya periode"
// =====================================================================
it('belum ada periode: an empty period picker plus the contact-Admin hint, without any figure or error', function () {
    $this->periodA->delete();
    Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('sterilizer')
        ->range('2026-03-01', '2026-03-31')->named('Periode Maret Sterilizer')->create();

    Livewire::actingAs($this->supervisor)
        ->test(LaporanCagesTrack::class)
        ->assertSet('periodId', '')
        ->assertSeeHtml('data-testid="period-select"')
        ->assertSee('Belum ada periode')
        ->assertSeeHtml('data-testid="no-periods"')
        ->assertSee('Belum ada Periode Pelaporan')
        ->assertDontSee('Periode Maret Sterilizer')
        ->assertDontSeeHtml('data-testid="report-kpis"')
        ->assertDontSeeHtml('data-testid="hourly-distribution"')
        ->assertDontSeeHtml('data-testid="export-csv"');
});

// =====================================================================
// Scenario 12: "Mencoba melihat mill lain"
// =====================================================================
it('mill lain: forcing the businessUnitId property to another mill moves nothing at all', function () {
    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('cages-track')
        ->range('2026-03-01', '2026-03-31')->named('Periode Maret Beta')->create();

    laporanCagesTrackComponentRecord($this->stationA, '2026-03-10', [['hour' => 6, 'cages' => 10]], ['cages_out' => 7]);
    laporanCagesTrackComponentRecord($this->stationB, '2026-03-10', [['hour' => 6, 'cages' => 900]], ['cages_out' => 900]);

    Livewire::actingAs($this->supervisor)
        ->test(LaporanCagesTrack::class)
        // resolvedBusinessUnitId() does not consult this property at all for
        // a bound role.
        ->set('businessUnitId', (string) $this->businessUnitB->id)
        ->assertSeeHtml('data-testid="mill-current"')
        ->assertSee('Mill Alpha')
        ->assertDontSee('Mill Beta')
        ->assertDontSee('Periode Maret Beta')
        ->assertSet('periodId', (string) $this->periodA->id)
        ->assertViewHas('summary', fn ($summary) => $summary['period']['business_unit_name'] === 'Mill Alpha'
            && $summary['kpi']['total_cages_tipped'] === 10
            && $summary['kpi']['total_cages_out'] === 7);

    // Mill B's period is simply not in this mill's list, so a hand-forced
    // period id is replaced before authorizePeriod() ever sees it.
    Livewire::actingAs($this->supervisor)
        ->test(LaporanCagesTrack::class)
        ->set('periodId', (string) $periodB->id)
        ->assertSet('periodId', (string) $this->periodA->id);
});

// =====================================================================
// Scenario 13: "Operator mencoba membuka layar web ini"
// =====================================================================
it('operator: the route refuses before mount, and mount() itself refuses too', function () {
    // Route layer — EnsureRole::forbidden() -> abort(403).
    $response = $this->actingAs($this->operator, 'web')->get('/reports/cages-track');
    $response->assertForbidden();
    $response->assertDontSee('Laporan Periode');

    // Component layer — mount()'s abort_unless(403) covers the component
    // being mounted directly. Livewire's test harness renders the 403 error
    // page instead of the component, so assert on that.
    $html = Livewire::actingAs($this->operator)->test(LaporanCagesTrack::class)->html();

    expect($html)->toContain('Forbidden');
    expect($html)->not->toContain('data-testid="laporan-cages-track"');
    expect($html)->not->toContain('data-testid="report-kpis"');
    expect($html)->not->toContain('data-testid="recap-table"');
});

// =====================================================================
// Scenario 14: "Periode tertutup"
// =====================================================================
it('periode tertutup: the status is a caption, the report is complete and the export button is never disabled', function () {
    $closed = Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('cages-track')
        ->range('2026-04-01', '2026-04-30')->named('Periode April Tertutup')->closed()->create();

    laporanCagesTrackComponentRecord($this->stationA, '2026-04-10', [
        ['hour' => 6, 'cages' => 4],
        ['hour' => 9, 'cages' => 6],
    ], ['cages_out' => 11]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanCagesTrack::class)
        ->set('periodId', (string) $closed->id)
        ->assertSeeHtml('data-testid="hero-status"')
        ->assertSee('Tertutup')
        ->assertSeeHtml('data-testid="report-kpis"')
        ->assertSeeHtml('data-testid="hourly-distribution"')
        ->assertSeeHtml('data-testid="daily-trend"')
        // The period lock governs writing data, not reading a report.
        ->assertSeeHtml('data-testid="export-csv"')
        ->assertDontSeeHtml('disabled');

    $component->call('export', 'csv')->assertFileDownloaded(null, null, 'text/csv');
});

// =====================================================================
// Scenario 15: "periode yang tidak mencakup Cages & Tracks tidak boleh muncul"
// =====================================================================
it('pemilih periode: offers cages-track and all-station-type periods only, newest first', function () {
    Period::factory()->forBusinessUnit($this->businessUnitA)->stationType(null)
        ->range('2026-05-01', '2026-05-31')->named('Periode Mei Semua Stasiun')->create();
    Period::factory()->forBusinessUnit($this->businessUnitA)->stationType('sterilizer')
        ->range('2026-02-01', '2026-02-28')->named('Periode Februari Sterilizer')->create();

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanCagesTrack::class)
        ->assertSee('Periode Mei Semua Stasiun')
        ->assertSee('Periode Maret Alpha')
        ->assertDontSee('Periode Februari Sterilizer');

    $html = $component->html();

    // Newest first, so the auto-selected option is the May one.
    expect(strpos($html, 'Periode Mei Semua Stasiun'))
        ->toBeLessThan(strpos($html, 'Periode Maret Alpha'));
    // A NULL station type is labelled, never shown blank.
    expect($html)->toContain('Semua Stasiun');
});

// =====================================================================
// Scenario 16: "rentang periode harus inklusif"
// =====================================================================
it('rentang inklusif: both boundary dates appear in the trend and the recap, and outside dates do not', function () {
    laporanCagesTrackComponentRecord($this->stationA, '2026-03-01', [['hour' => 6, 'cages' => 4]]);
    laporanCagesTrackComponentRecord($this->stationA, '2026-03-31', [['hour' => 7, 'cages' => 6]]);
    laporanCagesTrackComponentRecord($this->stationA, '2026-02-28', [['hour' => 6, 'cages' => 99]]);
    laporanCagesTrackComponentRecord($this->stationA, '2026-04-01', [['hour' => 6, 'cages' => 99]]);

    Livewire::actingAs($this->supervisor)
        ->test(LaporanCagesTrack::class)
        ->set('periodId', (string) $this->periodA->id)
        ->call('toggleRekapHarian')
        ->assertSeeHtml('data-testid="recap-row-2026-03-01"')
        ->assertSeeHtml('data-testid="recap-row-2026-03-31"')
        ->assertDontSeeHtml('data-testid="recap-row-2026-02-28"')
        ->assertDontSeeHtml('data-testid="recap-row-2026-04-01"')
        ->assertSeeHtml('data-testid="daily-trend-col-2026-03-01"')
        ->assertSeeHtml('data-testid="daily-trend-col-2026-03-31"')
        ->assertViewHas('summary', fn ($summary) => $summary['kpi']['total_cages_tipped'] === 10
            && count($summary['daily']) === 2);
});

// =====================================================================
// Scenario 17: "angka ringkasan record harian tidak boleh dipakai"
// =====================================================================
it('angka ringkasan: the screen shows the hourly-row total and never the header summary figure', function () {
    laporanCagesTrackComponentRecord($this->stationA, '2026-03-02', [
        ['hour' => 6, 'cages' => 30],
        ['hour' => 7, 'cages' => 40],
    ], ['cages_tipped' => 123456]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanCagesTrack::class)
        ->set('periodId', (string) $this->periodA->id)
        ->call('toggleRekapHarian')
        ->assertSeeHtml('data-testid="kpi-total-tipped"')
        ->assertSee('Sumbernya baris rincian per jam, bukan angka ringkasan record harian')
        ->assertViewHas('summary', fn ($summary) => $summary['kpi']['total_cages_tipped'] === 70
            && $summary['daily'][0]['cages_tipped'] === 70);

    // The misleading header figure never reaches the page, formatted or raw.
    expect($component->html())->not->toContain('123456');
    expect($component->html())->not->toContain('123.456');
});

// =====================================================================
// Scenario 18: "lori keluar tidak boleh terkalikan jumlah baris rincian"
// =====================================================================
it('lori keluar: a record with cages_out 50 and eight hourly rows shows 50, not 400', function () {
    laporanCagesTrackComponentRecord($this->stationA, '2026-03-02', [
        ['hour' => 0, 'cages' => 1], ['hour' => 1, 'cages' => 1],
        ['hour' => 2, 'cages' => 1], ['hour' => 3, 'cages' => 1],
        ['hour' => 4, 'cages' => 1], ['hour' => 5, 'cages' => 1],
        ['hour' => 6, 'cages' => 1], ['hour' => 7, 'cages' => 1],
    ], ['cages_out' => 50]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanCagesTrack::class)
        ->set('periodId', (string) $this->periodA->id)
        ->assertSeeHtml('data-testid="kpi-total-out"')
        ->assertSee('Dijumlahkan per record harian, bukan per baris rincian jam')
        ->assertViewHas('summary', fn ($summary) => $summary['kpi']['total_cages_out'] === 50
            && $summary['daily'][0]['cages_out'] === 50);

    expect($component->html())->not->toContain('400');
});

// =====================================================================
// Scenario 19: "jam tanpa penumpahan tidak boleh dihitung di luar jam operasi"
// =====================================================================
it('jam menganggur: only in-window empty hours are counted, and the chart marks the out-of-window ones', function () {
    laporanCagesTrackComponentRecord($this->stationA, '2026-03-02', [
        ['hour' => 6, 'cages' => 3],
        ['hour' => 7, 'cages' => 3],
        ['hour' => 8, 'cages' => 3],
    ], [
        'tippler_start_time' => '2026-03-02 06:00:00',
        'tippler_stop_time' => '2026-03-02 18:00:00',
    ]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanCagesTrack::class)
        ->set('periodId', (string) $this->periodA->id)
        ->assertSeeHtml('data-testid="kpi-idle-hours"')
        ->assertSee('Hanya dihitung di dalam jam operasi, bukan sepanjang 24 jam')
        ->assertSeeHtml('data-testid="hourly-distribution"')
        // Hours 9..17 only — never 21 (all 24 minus the three tipped).
        ->assertViewHas('summary', fn ($summary) => $summary['kpi']['idle_operating_hours'] === 9);

    $html = $component->html();

    // Every hour column carries its in/out-of-window flag, so the chart can
    // say which hours were never candidates for being idle.
    foreach (range(0, 23) as $hour) {
        $column = substr($html, strpos($html, 'data-testid="hourly-col-'.$hour.'"'));
        $column = substr($column, 0, 200);
        $expected = ($hour >= 6 && $hour <= 17) ? 'data-within-window="1"' : 'data-within-window="0"';

        expect($column)->toContain($expected);
    }
});

// =====================================================================
// Scenario 20: "jeda terpanjang tidak boleh dihitung lintas hari"
// =====================================================================
it('jeda terpanjang: the card shows the intra-day gap with one single date, not a two-day span', function () {
    laporanCagesTrackComponentHours($this->stationA, '2026-03-02', [6, 7]);
    laporanCagesTrackComponentHours($this->stationA, '2026-03-03', [20, 21]);

    Livewire::actingAs($this->supervisor)
        ->test(LaporanCagesTrack::class)
        ->set('periodId', (string) $this->periodA->id)
        ->assertSeeHtml('data-testid="kpi-longest-gap"')
        ->assertSee('Diukur antar jam penumpahan dalam satu tanggal, tidak melintasi malam')
        // One date, never a range — the caption names a single day.
        ->assertSee('Terjadi pada 02 Mar 2026')
        ->assertDontSeeHtml('data-testid="insufficient-gap"')
        ->assertViewHas('summary', fn ($summary) => $summary['kpi']['longest_gap_hours'] === 1
            && $summary['kpi']['longest_gap_date'] === '2026-03-02');
});

// =====================================================================
// Scenario 21: "antrean tersisa tidak boleh diakumulasi"
// =====================================================================
it('antrean tersisa: the queue card shows the lowest and the average snapshot, never their sum', function () {
    laporanCagesTrackComponentRecord($this->stationA, '2026-03-02', [
        ['hour' => 6, 'cages' => 2, 'remain' => 12],
        ['hour' => 7, 'cages' => 2, 'remain' => 5],
        ['hour' => 8, 'cages' => 2, 'remain' => 9],
    ]);

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanCagesTrack::class)
        ->set('periodId', (string) $this->periodA->id)
        ->assertSeeHtml('data-testid="queue-card"')
        ->assertSeeHtml('data-testid="queue-min"')
        ->assertSeeHtml('data-testid="queue-avg"')
        ->assertSee('Potret per jam')
        ->assertViewHas('summary', fn ($summary) => $summary['queue']['min_remaining'] === 5
            && $summary['queue']['avg_remaining'] === 8.7);

    // 26 — the sum of the three snapshots — must appear nowhere on the card.
    $html = $component->html();
    $card = substr($html, strpos($html, 'data-testid="queue-card"'));
    $card = substr($card, 0, strpos($card, '</section>'));

    expect($card)->toContain('5');
    expect($card)->toContain('8,7');
    expect($card)->not->toContain('>26<');
});

// =====================================================================
// Read-only surface — asserted once, for the whole screen
// =====================================================================
it('baca saja: the component exposes no create/update/delete action and changes no row', function () {
    laporanCagesTrackComponentRecord($this->stationA, '2026-03-10', [
        ['hour' => 6, 'cages' => 5],
        ['hour' => 9, 'cages' => 5],
    ], ['cages_out' => 9]);

    $recordsBefore = CagesTrackRecord::count();
    $detailsBefore = CagesTippedTime::count();

    $component = Livewire::actingAs($this->supervisor)
        ->test(LaporanCagesTrack::class)
        ->set('periodId', (string) $this->periodA->id)
        ->call('toggleRekapHarian')
        ->assertSeeHtml('data-testid="recap-table"');

    // The public surface is two pickers, a toggle and an export — nothing
    // that reads like a write action.
    $publicMethods = collect((new ReflectionClass(LaporanCagesTrack::class))->getMethods(ReflectionMethod::IS_PUBLIC))
        ->filter(fn (ReflectionMethod $method) => $method->getDeclaringClass()->getName() === LaporanCagesTrack::class)
        ->map(fn (ReflectionMethod $method) => $method->getName())
        ->sort()
        ->values()
        ->all();

    expect($publicMethods)->toBe(['export', 'mount', 'render', 'toggleRekapHarian']);

    foreach (['create', 'store', 'update', 'save', 'delete', 'destroy', 'verify', 'acknowledge'] as $writeAction) {
        expect($publicMethods)->not->toContain($writeAction);
    }

    $html = $component->html();
    expect($html)->not->toContain('wire:submit');
    expect($html)->not->toContain('>Tambah<');
    expect($html)->not->toContain('>Hapus<');
    expect($html)->not->toContain('>Ubah<');

    expect(CagesTrackRecord::count())->toBe($recordsBefore);
    expect(CagesTippedTime::count())->toBe($detailsBefore);
});

// =====================================================================
// Scenario 26: "Admin mengunduh CSV setelah memilih mill"
//
// REGRESSION — the export path is the ONE place where the mill is resolved
// a second time, and every earlier export scenario logs in as Supervisor,
// whose mill comes from auth()->user()->business_unit_id and can therefore
// never be missing. Admin is the only role whose mill lives in the
// component's own state, so only an Admin download proves the component
// threads its RESOLVED mill into the service instead of leaving it null —
// which is refused with 422 (ValidationException) before a single byte is
// streamed, and looks to the user like an inert Ekspor button.
// =====================================================================
it('admin ekspor: an Admin who picked a mill actually downloads the CSV, and it carries that mill alone', function () {
    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('cages-track')
        ->range('2026-03-01', '2026-03-31')->named('Periode Maret Beta')->create();

    laporanCagesTrackComponentRecord($this->stationA, '2026-03-02', [
        ['hour' => 6, 'cages' => 3],
        ['hour' => 7, 'cages' => 2],
    ], ['cages_track_number' => 'CT-ADMIN-ALPHA', 'cages_out' => 12]);

    laporanCagesTrackComponentRecord($this->stationB, '2026-03-02', [
        ['hour' => 6, 'cages' => 500],
    ], ['cages_track_number' => 'CT-ADMIN-BETA', 'cages_out' => 500]);

    $recordsBefore = CagesTrackRecord::count();
    $detailsBefore = CagesTippedTime::count();

    $component = Livewire::actingAs($this->admin)
        ->test(LaporanCagesTrack::class)
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
    expect($body)->toContain('CT-ADMIN-ALPHA');
    expect($body)->not->toContain('CT-ADMIN-BETA');

    // Read-only: exporting changes nothing, and Mill B's period is untouched.
    expect(CagesTrackRecord::count())->toBe($recordsBefore);
    expect(CagesTippedTime::count())->toBe($detailsBefore);
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
// memilih mill lalu menekan tile Cages & Tracks MENDARAT DI LAYAR YANG
// MEMINTANYA MEMILIH MILL LAGI, tanpa satu angka pun termuat — persis
// seperti sebelum perbaikan, dan tanpa satu test pun memerah. Itulah yang
// ditutup di sini.
//
// Asersinya sengaja PERILAKU dan bukan refleksi atas atributnya: membaca
// atribut PHP hanya menguji ejaan, dan tetap hijau kalau Livewire mengubah
// semantik `as:`.
// =====================================================================
it('hidrasi query string: Admin yang tiba dari tautan tile langsung melihat laporan mill itu, bukan permintaan memilih mill lagi', function () {
    $periodB = Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('cages-track')
        ->range('2026-03-01', '2026-03-31')->named('Periode Maret Beta')->create();

    laporanCagesTrackComponentHours($this->stationA, '2026-03-10', [6, 7]);
    laporanCagesTrackComponentHours($this->stationB, '2026-03-10', [6, 7, 8]);

    // (a) Permukaan HTTP — URL yang bentuknya persis seperti yang dibangun
    // StationReportService untuk tile Cages & Tracks.
    $response = $this->actingAs($this->admin, 'web')
        ->get(route('reports.cages-track', ['business_unit_id' => $this->businessUnitA->id]));

    $response->assertOk();
    $response->assertDontSee('Pilih mill terlebih dahulu');
    $response->assertSee('Periode Maret Alpha');
    $response->assertDontSee('Periode Maret Beta');

    // (b) Permukaan komponen — propertinya benar-benar terhidrasi, periode
    // mill itu ikut termuat, dan angkanya berasal dari mill itu saja.
    Livewire::actingAs($this->admin)
        ->withQueryParams(['business_unit_id' => (string) $this->businessUnitA->id])
        ->test(LaporanCagesTrack::class)
        ->assertSet('businessUnitId', (string) $this->businessUnitA->id)
        ->assertSet('periodId', (string) $this->periodA->id)
        ->assertViewHas('needsMillSelection', false)
        ->assertDontSeeHtml('data-testid="mill-required-hint"')
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
    Period::factory()->forBusinessUnit($this->businessUnitB)->stationType('cages-track')
        ->range('2026-03-01', '2026-03-31')->named('Periode Maret Beta')->create();

    laporanCagesTrackComponentHours($this->stationA, '2026-03-10', [6, 7]);
    laporanCagesTrackComponentHours($this->stationB, '2026-03-10', [6, 7, 8]);

    foreach ([$this->supervisor, $this->millManagement] as $user) {
        $response = $this->actingAs($user, 'web')
            ->get(route('reports.cages-track', ['business_unit_id' => $this->businessUnitB->id]));

        $response->assertOk();
        $response->assertSee('Periode Maret Alpha');
        $response->assertDontSee('Periode Maret Beta');
        $response->assertDontSee('Mill Beta');

        Livewire::actingAs($user)
            ->withQueryParams(['business_unit_id' => (string) $this->businessUnitB->id])
            ->test(LaporanCagesTrack::class)
            // Terhidrasi — dan tetap diabaikan.
            ->assertSet('businessUnitId', (string) $this->businessUnitB->id)
            ->assertSet('periodId', (string) $this->periodA->id)
            ->assertViewHas('summary', fn ($summary) => $summary !== null
                && $summary['period']['business_unit_name'] === 'Mill Alpha'
                && $summary['period']['name'] === 'Periode Maret Alpha');
    }
});
