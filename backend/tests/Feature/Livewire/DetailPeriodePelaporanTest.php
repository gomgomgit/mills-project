<?php

/**
 * DetailPeriodePelaporanTest (Feature/Livewire) —
 * screen-142--detail-periode-pelaporan / usecase-145 (lihat detail) +
 * usecase-140 (tutup & buka kembali) + usecase-144 (buka stasiun draft).
 *
 * Component tests for App\Livewire\MasterData\DetailPeriodePelaporan.
 *
 * WHERE THESE TESTS CAME FROM (2026-09-27). Most of them used to live in
 * tests/Feature/Livewire/KelolaPeriodePelaporanTest.php, against the list
 * screen, back when a period's station rows were an expandable second <tr>
 * inside that list and every per-station action lived in it. The accordion and
 * the actions moved to this screen, so the tests moved with them — they were
 * not dropped and their assertions were not softened. What DID change shape:
 * a test that used to `call('toggleExpanded', $periodId)` first now simply
 * mounts this component with the period id, because the station rows are the
 * page rather than a disclosure inside another one.
 *
 * TWO KINDS OF ID, AND MIXING THEM IS THIS SCREEN'S MOST EXPENSIVE MISTAKE.
 * The component is mounted with a PERIOD id. Every per-station action takes a
 * `period_stations` id. detailStationId() is the only place this file turns a
 * period into one, so no test can hand a period id to askClose()/askOpen()/
 * askReopen() and pass for the wrong reason.
 *
 * ACCESS CONTROL is route-level only ('auth' + 'role:admin' in routes/web.php,
 * App\Http\Middleware\EnsureRole aborts 403 before the component ever mounts)
 * — so the "non-Admin" scenarios assert the ROUTE, via a plain HTTP GET, plus
 * the API actions behind the buttons, rather than mounting the component with
 * a non-admin actor, which would assert a guard the component does not own.
 *
 * PERIOD LOCK ENFORCEMENT IS NOT TESTED HERE AND DOES NOT EXIST YET. Closing a
 * station sets a status; refusing record input/edit/verification inside a
 * closed period is usecase-141--kunci-input-periode-tertutup, whose four
 * scenarios live skipped in tests/Feature/Api/KelolaPeriodePelaporanTest.php.
 */

use App\Enums\UserRole;
use App\Livewire\MasterData\DetailPeriodePelaporan;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\Station;
use App\Models\SterilizerRecord;
use App\Models\ThreshingRecord;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->admin = User::factory()->role(UserRole::Admin)->create(['name' => 'Admin X']);
    $this->adminA = User::factory()->role(UserRole::Admin)->create(['name' => 'Admin A']);
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->create();

    $this->sterilizerStation = Station::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->sterilizer()
        ->create();
});

/**
 * THE ONLY PLACE THIS FILE TURNS A PERIOD INTO A period_stations ID.
 */
function detailStationId(Period|string $period, string $stationType = 'sterilizer'): string
{
    return PeriodStation::query()
        ->where('period_id', $period instanceof Period ? $period->id : $period)
        ->where('station_type', $stationType)
        ->firstOrFail()
        ->id;
}

/**
 * The opening `<button ...>` tag carrying $testId, so a test can assert on
 * that one button's attributes (`disabled` in particular) instead of on the
 * whole page, where another button's attribute would satisfy the assertion.
 */
function detailButtonTag(string $html, string $testId): string
{
    $matched = preg_match(
        '/<button[^>]*data-testid="'.preg_quote($testId, '/').'"[^>]*>/',
        $html,
        $matches
    );

    expect($matched)->toBe(1, "tombol dengan data-testid=\"$testId\" tidak ditemukan");

    return $matches[0];
}

/** Mounts the detail component for one period, as Admin X. */
function detailFor(Period|string $period): Testable
{
    return Livewire::actingAs(test()->admin)
        ->test(DetailPeriodePelaporan::class, ['id' => $period instanceof Period ? $period->id : $period]);
}

// ── usecase-145 — Lihat Detail Periode Pelaporan ────────────────────────

// Scenario: "Lihat Detail Periode Pelaporan — sukses"
it('menampilkan ringkasan periode dan tabel stasiun terurut mengikuti sort_order master', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->noStations()
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->create();

    // Inserted in an order that is neither the master's nor alphabetical, so
    // a component that forgot to respect toRow()'s ordering would show it.
    PeriodStation::factory()->forPeriod($period)->stationType('clarification')->open()->create();
    PeriodStation::factory()->forPeriod($period)->stationType('boiler-room')->draft()->create();
    PeriodStation::factory()->forPeriod($period)->stationType('sterilizer')
        ->closed($this->adminA, '2026-11-01 09:14:00')->create();

    $component = detailFor($period)
        ->assertSet('notFound', false)
        ->assertViewHas('period', fn ($row) => $row['id'] === $period->id
            && $row['name'] === 'Oktober 2026'
            && $row['business_unit_name'] === 'Mill Alpha'
            && $row['station_count'] === 3
            && $row['closed_station_count'] === 1
            // sort_order: sterilizer 40 < clarification 70 < boiler-room 90.
            // Alphabetically that order is exactly reversed.
            && collect($row['stations'])->pluck('station_type')->all()
                === ['sterilizer', 'clarification', 'boiler-room'])
        ->assertSee('Oktober 2026')
        ->assertSee('Mill Alpha')
        ->assertSee('01/10/2026')
        ->assertSee('31/10/2026')
        ->assertSee('3 stasiun')
        ->assertSee('1 tertutup')
        // The five columns of the station table.
        ->assertSee('Jenis Stasiun')
        ->assertSee('Status')
        ->assertSee('Ditutup Oleh')
        ->assertSee('Waktu Ditutup')
        ->assertSee('Aksi')
        ->assertSee('Admin A')
        ->assertSeeHtml('data-testid="period-stations-'.$period->id.'"');

    foreach (['sterilizer', 'clarification', 'boiler-room'] as $code) {
        $component->assertSeeHtml('data-testid="period-station-row-'.detailStationId($period, $code).'"');
    }
});

// Scenario: "Lihat Detail Periode Pelaporan — kembali ke daftar periode"
it('tombol kembali membawa Admin ke daftar periode tanpa mengubah apa pun', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    $stationId = detailStationId($period);

    detailFor($period)
        ->assertSeeHtml('data-testid="back-to-periods"')
        ->call('backToList')
        ->assertRedirect(route('master-data.periods'));

    expect(PeriodStation::findOrFail($stationId)->status->value)->toBe('open');
});

// Scenario: "Lihat Detail Periode Pelaporan — Periode tidak ditemukan"
it('periode tidak ditemukan: pesan ramah beserta jalan kembali, bukan halaman kosong', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Periode Sementara')
        ->range('2026-10-01', '2026-10-31')
        ->create();

    $id = $period->id;
    Period::whereKey($id)->delete();

    Livewire::actingAs($this->admin)
        ->test(DetailPeriodePelaporan::class, ['id' => $id])
        ->assertStatus(200)
        ->assertSet('notFound', true)
        ->assertViewHas('period', null)
        ->assertSee('Periode tidak ditemukan')
        ->assertSeeHtml('data-testid="period-not-found"')
        ->assertDontSeeHtml('data-testid="period-stations-'.$id.'"')
        ->assertSeeHtml('data-testid="back-to-periods-empty"')
        ->call('backToList')
        ->assertRedirect(route('master-data.periods'));
});

// Scenario: "Lihat Detail Periode Pelaporan — Periode tanpa baris stasiun"
it('periode tanpa baris stasiun: penjelasan menggantikan tabel, dan Edit/Hapus tetap aktif', function () {
    // Mill Beta has no station at all, so the period is born with no rows.
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->noStations()
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->create();

    $component = detailFor($period)
        ->assertViewHas('period', fn ($row) => $row['stations'] === []
            && $row['station_count'] === 0
            && $row['closed_station_count'] === 0
            && $row['status_summary'] === 'empty'
            && $row['is_immutable'] === false)
        ->assertSee('Tanpa Stasiun')
        // An explanation with a way out, NOT an empty table.
        ->assertSeeHtml('data-testid="period-stations-empty-'.$period->id.'"')
        ->assertSee('belum memiliki stasiun aktif')
        ->assertSee('simpan ulang periode ini')
        ->assertDontSeeHtml('data-testid="period-stations-'.$period->id.'"');

    $html = $component->html();
    expect(detailButtonTag($html, "edit-button-{$period->id}"))->not->toContain('disabled');
    expect(detailButtonTag($html, "delete-button-{$period->id}"))->not->toContain('disabled');
});

// Scenario: "Lihat Detail Periode Pelaporan — Status stasiun campuran"
//
// The shape the one-row-per-station-type model made impossible to assert at
// all before the split: three stations of one period, three different
// statuses, three different actions.
it('status campuran: badge Campuran, satu aksi per baris sesuai statusnya, Edit dan Hapus mati', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->noStations()
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->create();

    PeriodStation::factory()->forPeriod($period)->stationType('sterilizer')
        ->closed($this->adminA, '2026-11-01 09:14:00')->create();
    PeriodStation::factory()->forPeriod($period)->stationType('clarification')->open()->create();
    PeriodStation::factory()->forPeriod($period)->stationType('boiler-room')->draft()->create();

    $sterilizerRow = detailStationId($period, 'sterilizer');
    $clarificationRow = detailStationId($period, 'clarification');
    $boilerRow = detailStationId($period, 'boiler-room');

    $component = detailFor($period)
        ->assertViewHas('period', fn ($row) => $row['status_summary'] === 'mixed'
            && $row['closed_station_count'] === 1
            && $row['is_immutable'] === true)
        ->assertSee('Campuran')
        ->assertSee('Tertutup')
        ->assertSee('Terbuka')
        ->assertSee('Draft')
        // Each row offers exactly the one action its OWN status allows —
        // draft -> open -> closed, no shortcut.
        ->assertSeeHtml("station-reopen-button-{$sterilizerRow}")
        ->assertDontSeeHtml("station-close-button-{$sterilizerRow}")
        ->assertDontSeeHtml("station-open-button-{$sterilizerRow}")
        ->assertSeeHtml("station-close-button-{$clarificationRow}")
        ->assertDontSeeHtml("station-open-button-{$clarificationRow}")
        ->assertDontSeeHtml("station-reopen-button-{$clarificationRow}")
        ->assertSeeHtml("station-open-button-{$boilerRow}")
        ->assertDontSeeHtml("station-close-button-{$boilerRow}")
        ->assertDontSeeHtml("station-reopen-button-{$boilerRow}");

    // ONE closed station out of three freezes the period — "any", not "all".
    // The buttons EXIST and are DISABLED, with the reason on `title`.
    $html = $component->html();
    expect(detailButtonTag($html, "edit-button-{$period->id}"))->toContain('disabled');
    expect(detailButtonTag($html, "edit-button-{$period->id}"))->toContain('title=');
    expect(detailButtonTag($html, "delete-button-{$period->id}"))->toContain('disabled');
    expect(detailButtonTag($html, "delete-button-{$period->id}"))->toContain('title=');
    expect($html)->toContain('Buka kembali stasiun tersebut terlebih dahulu');
});

// Scenario: "Lihat Detail Periode Pelaporan — Bukan Admin membuka detail"
it('akses ditolak: non-Admin tidak dapat membuka /master-data/periods/{id} sama sekali', function (string $role) {
    $user = match ($role) {
        'supervisor' => $this->supervisor,
        'mill_management' => $this->millManagement,
        'operator' => $this->operator,
    };

    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Periode Rahasia')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    // EnsureRole::forbidden() -> abort(403) BEFORE the component mounts, so
    // neither the period nor any station status is ever rendered.
    $response = $this->actingAs($user, 'web')->get("/master-data/periods/{$period->id}");

    $response->assertForbidden();
    $response->assertDontSee('Periode Rahasia');
    $response->assertDontSee('station-close-button', false);
})->with([
    'supervisor' => ['supervisor'],
    'mill management' => ['mill_management'],
    'operator' => ['operator'],
]);

// ── usecase-140 — Tutup & Buka Kembali ──────────────────────────────────

// Scenario: "Tutup & Buka Kembali Periode Pelaporan — success"
it('tutup stasiun: dialog memuat jumlah belum terverifikasi, konfirmasi mengubah badge menjadi Tertutup', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    $stationId = detailStationId($period);

    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-10-10')->count(2)->create();

    $component = detailFor($period)
        ->assertSeeHtml("station-close-button-{$stationId}")
        ->call('askClose', $stationId)
        ->assertSet('closingStationId', $stationId)
        ->assertSet('closingUnverifiedCount', 2)
        ->assertSet('closingStation', fn ($snapshot) => is_array($snapshot)
            && $snapshot['period_station_id'] === $stationId
            && $snapshot['station_type_label'] === 'Sterilizer')
        ->assertSee('belum terverifikasi')
        ->call('confirmClose')
        ->assertSet('closingStationId', null)
        ->assertSet('closeErrorMessage', null)
        ->assertSet('successMessage', 'Stasiun periode berhasil ditutup.')
        ->assertViewHas('period', fn ($row) => $row['status_summary'] === 'closed'
            && $row['closed_station_count'] === 1
            && $row['is_immutable'] === true
            && $row['stations'][0]['closed_by_name'] === 'Admin X'
            && $row['stations'][0]['closed_at'] !== null)
        // A closed station offers reopen only.
        ->assertSeeHtml("station-reopen-button-{$stationId}")
        ->assertDontSeeHtml("station-close-button-{$stationId}")
        ->assertSee('1 tertutup')
        ->assertSee('Admin X');

    // And the period is frozen now.
    $html = $component->html();
    expect(detailButtonTag($html, "edit-button-{$period->id}"))->toContain('disabled');
    expect(detailButtonTag($html, "delete-button-{$period->id}"))->toContain('disabled');

    expect(PeriodStation::findOrFail($stationId)->status->value)->toBe('closed');
});

// Scenario: "Tutup & Buka Kembali Periode Pelaporan — buka kembali stasiun yang sudah tertutup"
it('buka kembali stasiun: badge jadi Terbuka dan kolom penutupan kosong kembali', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->closed($this->adminA, '2026-11-01 09:14:00')
        ->create();

    $stationId = detailStationId($period);

    $component = detailFor($period)
        ->call('askReopen', $stationId)
        ->assertSet('confirmingReopenStationId', $stationId)
        // The inline confirmation names the station it is about.
        ->assertSee('Buka kembali Sterilizer?')
        ->call('confirmReopen')
        ->assertSet('confirmingReopenStationId', null)
        ->assertSet('successMessage', 'Stasiun periode berhasil dibuka kembali.')
        ->assertViewHas('period', fn ($row) => $row['status_summary'] === 'open'
            && $row['is_immutable'] === false
            && $row['stations'][0]['closed_by'] === null
            && $row['stations'][0]['closed_at'] === null)
        // Tutup Stasiun is available again on the station row...
        ->assertSeeHtml("station-close-button-{$stationId}")
        ->assertDontSeeHtml("station-reopen-button-{$stationId}");

    // ...and Edit/Hapus are live again on the period.
    $html = $component->html();
    expect(detailButtonTag($html, "edit-button-{$period->id}"))->not->toContain('disabled');
    expect(detailButtonTag($html, "delete-button-{$period->id}"))->not->toContain('disabled');

    $fresh = PeriodStation::findOrFail($stationId);
    expect($fresh->status->value)->toBe('open');
    expect($fresh->closed_by)->toBeNull();
    expect($fresh->closed_at)->toBeNull();
});

// Scenario: "Tutup & Buka Kembali Periode Pelaporan — Admin membatalkan penutupan"
it('membatalkan penutupan: aksi close tidak pernah dipanggil dan status tidak berubah', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    $stationId = detailStationId($period);

    detailFor($period)
        ->call('askClose', $stationId)
        ->assertSet('closingStationId', $stationId)
        ->call('cancelClose')
        ->assertSet('closingStationId', null)
        ->assertSet('closingStation', null)
        ->assertSet('closingUnverifiedCount', 0)
        ->assertSet('successMessage', null)
        ->assertViewHas('period', fn ($row) => $row['status_summary'] === 'open'
            && $row['stations'][0]['closed_by'] === null);

    expect(PeriodStation::findOrFail($stationId)->status->value)->toBe('open');
});

// Scenario: "jumlah belum terverifikasi di-scope ke jenis stasiun yang ditutup"
it('dialog tutup: jumlahnya milik stasiun itu saja, bukan se-periode, dan tombol konfirmasi tetap aktif', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationTypes(['sterilizer', 'threshing'])
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    $threshingStation = Station::factory()->forBusinessUnit($this->businessUnitA)->threshing()->create();

    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-10-10')->count(5)->create();
    ThreshingRecord::factory()->forStation($threshingStation)->onDate('2026-10-11')->count(2)->create();

    $sterilizerRow = detailStationId($period, 'sterilizer');
    $threshingRow = detailStationId($period, 'threshing');

    detailFor($period)
        ->call('askClose', $sterilizerRow)
        // 5, not 7: the figure is Sterilizer's own. A period-wide number would
        // have nothing to do with the action being confirmed.
        ->assertSet('closingUnverifiedCount', 5)
        ->assertSet('closingBreakdown', fn ($breakdown) => collect($breakdown)->pluck('count', 'station_type')->all() === [
            'sterilizer' => 5,
        ])
        ->assertSee('5 data Sterilizer belum terverifikasi')
        ->assertSee('verifikasi ikut terkunci')
        ->assertSee('Jenis stasiun lain pada periode ini tidak terpengaruh')
        // Threshing is not named anywhere in Sterilizer's dialog.
        ->assertDontSee('data Threshing belum terverifikasi')
        // The confirm button is rendered and never disabled by the figure.
        ->assertSeeHtml('data-testid="confirm-close-button"')
        ->call('confirmClose')
        ->assertSet('successMessage', 'Stasiun periode berhasil ditutup.')
        // The OTHER station's dialog reports its own, different figure.
        ->call('askClose', $threshingRow)
        ->assertSet('closingUnverifiedCount', 2)
        ->assertSee('2 data Threshing belum terverifikasi');
});

// Scenario: "record jenis stasiun lain tidak ikut terkunci" (component half)
it('menutup satu stasiun tidak mengubah tampilan maupun aksi stasiun lain pada periode yang sama', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->noStations()
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->create();

    PeriodStation::factory()->forPeriod($period)->stationType('sterilizer')->open()->create();
    PeriodStation::factory()->forPeriod($period)->stationType('clarification')->open()->create();

    $sterilizerRow = detailStationId($period, 'sterilizer');
    $clarificationRow = detailStationId($period, 'clarification');

    $component = detailFor($period);

    $clarificationBefore = collect($component->viewData('period')['stations'])
        ->firstWhere('station_type', 'clarification');

    $component
        ->call('askClose', $sterilizerRow)
        ->call('confirmClose')
        ->assertSet('successMessage', 'Stasiun periode berhasil ditutup.')
        ->assertViewHas('period', fn ($row) => $row['status_summary'] === 'mixed');

    $clarificationAfter = collect($component->viewData('period')['stations'])
        ->firstWhere('station_type', 'clarification');

    // Bit-for-bit the same row in the rendered data.
    expect($clarificationAfter)->toBe($clarificationBefore);
    expect($clarificationAfter['status'])->toBe('open');

    // And the rendered HTML still offers Clarification its own close action,
    // while Sterilizer now offers reopen.
    $html = $component->html();
    expect($html)->toContain("station-close-button-{$clarificationRow}");
    expect($html)->toContain("station-reopen-button-{$sterilizerRow}");
    expect($html)->not->toContain("station-close-button-{$sterilizerRow}");

    expect(PeriodStation::findOrFail($clarificationRow)->status->value)->toBe('open');
    expect(PeriodStation::findOrFail($clarificationRow)->closed_by)->toBeNull();
});

// Scenario: "menutup stasiun yang sudah tertutup"
it('stasiun tertutup: hanya aksi Buka Kembali yang ter-render pada baris stasiunnya', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->closed($this->adminA, '2026-11-01 09:14:00')
        ->create();

    $stationId = detailStationId($period);

    detailFor($period)
        ->assertSee('Tertutup')
        ->assertSeeHtml("station-reopen-button-{$stationId}")
        ->assertDontSeeHtml("station-close-button-{$stationId}")
        ->assertDontSeeHtml("station-open-button-{$stationId}");
});

// Scenario: "dua Admin menutup stasiun yang sama bersamaan"
it('dua Admin menutup stasiun bersamaan: Admin kedua diberi tahu dan catatan penutup pertama tetap', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    $stationId = detailStationId($period);

    // Admin X still sees the station as open and opens the dialog.
    $component = detailFor($period)
        ->call('askClose', $stationId)
        ->assertSet('closingStationId', $stationId);

    // Admin A closes it first, straight in the database — on the CHILD table,
    // which is where the status lives now.
    PeriodStation::whereKey($stationId)->update([
        'status' => 'closed',
        'closed_by' => $this->adminA->id,
        'closed_at' => now()->subMinute(),
    ]);

    $component
        ->call('confirmClose')
        ->assertStatus(200)
        ->assertSet('successMessage', null)
        ->assertSet('closeErrorMessage', fn ($m) => is_string($m) && str_contains($m, 'Admin A'))
        ->assertSeeHtml('data-testid="close-error"')
        ->assertViewHas('period', fn ($row) => $row['status_summary'] === 'closed'
            && $row['stations'][0]['closed_by_name'] === 'Admin A');

    // Admin X never overwrote Admin A's record.
    expect(PeriodStation::findOrFail($stationId)->closed_by)->toBe($this->adminA->id);
});

// Scenario: "baris stasiun sudah tidak ada"
it('stasiun hilang di tengah jalan: pesan tidak ditemukan dan halaman tetap menawarkan jalan kembali', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Periode Sementara')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    $stationId = detailStationId($period);

    $component = detailFor($period)
        ->call('askClose', $stationId)
        ->assertSet('closingStationId', $stationId);

    // Another Admin deletes the period — the station row cascades away.
    Period::whereKey($period->id)->delete();

    $component
        ->call('confirmClose')
        ->assertStatus(200)
        ->assertSet('successMessage', null)
        ->assertSet('closeErrorMessage', fn ($m) => is_string($m) && str_contains($m, 'tidak ditemukan'))
        ->assertSet('notFound', true)
        ->assertSee('Periode tidak ditemukan')
        ->assertSeeHtml('data-testid="back-to-periods-empty"');

    expect(PeriodStation::find($stationId))->toBeNull();
});

// Scenario: "pengguna selain Admin menutup stasiun"
it('akses ditolak: non-Admin tidak dapat memicu aksi tutup maupun buka kembali', function (string $role) {
    $user = match ($role) {
        'supervisor' => $this->supervisor,
        'mill_management' => $this->millManagement,
        'operator' => $this->operator,
    };

    $openPeriod = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Periode Terbuka')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    $closedPeriod = Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType('sterilizer')
        ->named('Periode Tertutup')
        ->range('2026-10-01', '2026-10-31')
        ->closed($this->adminA, '2026-11-01 09:14:00')
        ->create();

    $openStationId = detailStationId($openPeriod);
    $closedStationId = detailStationId($closedPeriod);

    // The screen itself is unreachable, so neither action button is ever
    // rendered for this actor.
    $this->actingAs($user, 'web')->get("/master-data/periods/{$openPeriod->id}")->assertForbidden();

    // And the API actions behind those buttons are refused too.
    $this->actingAs($user, 'web')->postJson("/api/period-stations/{$openStationId}/close")->assertStatus(403);
    $this->actingAs($user, 'web')->postJson("/api/period-stations/{$closedStationId}/reopen")->assertStatus(403);

    expect(PeriodStation::findOrFail($openStationId)->status->value)->toBe('open');
    expect(PeriodStation::findOrFail($closedStationId)->closed_by)->toBe($this->adminA->id);
})->with([
    'supervisor' => ['supervisor'],
    'mill management' => ['mill_management'],
    'operator' => ['operator'],
]);

/**
 * Scenario: "membuka kembali stasiun agar periodenya dapat disimpan ulang" —
 * the manual way out of the backfill gap, end to end on this screen. Edit is
 * dead while one station is closed and alive again the moment it is reopened,
 * and the save really does register the station type the mill gained since.
 */
it('Edit mati saat ada stasiun tertutup dan hidup lagi setelah stasiun itu dibuka kembali', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->closed($this->adminA, '2026-11-01 09:14:00')
        ->create();

    $sterilizerRow = detailStationId($period, 'sterilizer');

    // The mill gains a Clarification station AFTER the period was closed.
    Station::factory()->forBusinessUnit($this->businessUnitA)->clarification()->create();

    $component = detailFor($period);

    expect(detailButtonTag($component->html(), "edit-button-{$period->id}"))->toContain('disabled');

    // Forcing the action anyway is refused by the same rule, not by a
    // re-derivation of it.
    $component
        ->call('openEditForm')
        ->assertSet('showForm', false)
        ->assertSet('deleteErrorMessage', fn ($m) => is_string($m) && str_contains($m, 'Buka kembali periode terlebih dahulu'));

    expect(PeriodStation::where('period_id', $period->id)->count())->toBe(1);

    // Reopen -> save -> the backfill runs and the new station type is
    // registered.
    $component
        ->call('askReopen', $sterilizerRow)
        ->call('confirmReopen')
        ->assertSet('successMessage', 'Stasiun periode berhasil dibuka kembali.');

    expect(detailButtonTag($component->html(), "edit-button-{$period->id}"))->not->toContain('disabled');

    $component
        ->call('openEditForm')
        ->assertSet('showForm', true)
        ->assertSet('business_unit_id', $this->businessUnitA->id)
        ->assertSet('form.name', 'Oktober 2026')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('successMessage', 'Periode berhasil diperbarui.');

    expect(PeriodStation::where('period_id', $period->id)->pluck('station_type')->sort()->values()->all())
        ->toBe(['clarification', 'sterilizer']);

    // The pre-existing row is the SAME row — the backfill adds, it never
    // replaces.
    expect(PeriodStation::findOrFail($sterilizerRow)->status->value)->toBe('open');
});

// ── usecase-144 — Buka Stasiun ──────────────────────────────────────────

/** A draft period on Mill Alpha — the only status that offers "Buka Stasiun". */
function draftPeriodForDetail(BusinessUnit $businessUnit, string $name = 'Oktober 2026'): Period
{
    return Period::factory()
        ->forBusinessUnit($businessUnit)
        ->stationType('sterilizer')
        ->named($name)
        ->range('2026-10-01', '2026-10-31')
        ->draft()
        ->create();
}

// Scenario: "Buka Periode Pelaporan — sukses"
it('buka stasiun: dialog konfirmasi tampil, setelah konfirmasi badge jadi Terbuka dan tombol Buka Stasiun hilang', function () {
    $period = draftPeriodForDetail($this->businessUnitA);
    $stationId = detailStationId($period);

    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-10-10')->count(2)->create();
    $recordsBefore = SterilizerRecord::query()->orderBy('id')->get()
        ->map(fn ($r) => [$r->id, $r->checked_by, $r->acknowledged_by, (string) $r->updated_at])
        ->all();

    $component = detailFor($period)
        ->assertSeeHtml("station-open-button-{$stationId}")
        ->call('askOpen', $stationId)
        ->assertSet('openingStationId', $stationId)
        ->assertSet('openingStation', fn ($snapshot) => is_array($snapshot)
            && $snapshot['period_station_id'] === $stationId
            && $snapshot['station_type_label'] === 'Sterilizer')
        // The dialog spells out that the step cannot be undone.
        ->assertSeeHtml('data-testid="open-period-dialog"')
        ->assertSeeHtml('data-testid="confirm-open-period"')
        ->assertSee('tidak dapat dikembalikan ke Draft')
        ->call('confirmOpen')
        ->assertSet('openingStationId', null)
        ->assertSet('openingStation', null)
        ->assertSet('closeErrorMessage', null)
        ->assertSet('successMessage', 'Stasiun periode berhasil dibuka.')
        ->assertViewHas('period', fn ($row) => $row['status_summary'] === 'open'
            && $row['is_immutable'] === false
            && $row['stations'][0]['closed_by'] === null)
        ->assertSee('Terbuka')
        // The action is no longer offered for that station, while its close
        // action stays available.
        ->assertDontSeeHtml("station-open-button-{$stationId}")
        ->assertSeeHtml("station-close-button-{$stationId}");

    // An open station does NOT freeze its period.
    $html = $component->html();
    expect(detailButtonTag($html, "edit-button-{$period->id}"))->not->toContain('disabled');
    expect(detailButtonTag($html, "delete-button-{$period->id}"))->not->toContain('disabled');

    expect(PeriodStation::findOrFail($stationId)->status->value)->toBe('open');

    // Not one station record changed.
    $recordsAfter = SterilizerRecord::query()->orderBy('id')->get()
        ->map(fn ($r) => [$r->id, $r->checked_by, $r->acknowledged_by, (string) $r->updated_at])
        ->all();
    expect($recordsAfter)->toBe($recordsBefore);
});

// Alternative flow "Admin membatalkan pembukaan".
it('membatalkan pembukaan: dialog tertutup, status tetap Draft, tanpa notifikasi sukses', function () {
    $period = draftPeriodForDetail($this->businessUnitA);
    $stationId = detailStationId($period);

    detailFor($period)
        ->call('askOpen', $stationId)
        ->assertSet('openingStationId', $stationId)
        ->assertSeeHtml('data-testid="open-period-dialog"')
        ->call('cancelOpen')
        ->assertSet('openingStationId', null)
        ->assertSet('openingStation', null)
        ->assertSet('closeErrorMessage', null)
        ->assertSet('successMessage', null)
        ->assertDontSeeHtml('data-testid="open-period-dialog"')
        ->assertViewHas('period', fn ($row) => $row['status_summary'] === 'draft')
        // The station row still offers the action, untouched.
        ->assertSeeHtml("station-open-button-{$stationId}");

    expect(PeriodStation::findOrFail($stationId)->status->value)->toBe('draft');
});

// Scenario: "Buka Periode Pelaporan — Stasiun sudah terbuka"
it('buka stasiun yang sudah terbuka: pesan menyebut sudah terbuka dan status tidak berubah', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    $stationId = detailStationId($period);

    detailFor($period)
        // An open station never renders the button — this is the forged/stale
        // call the component still has to survive.
        ->assertDontSeeHtml("station-open-button-{$stationId}")
        ->call('askOpen', $stationId)
        ->call('confirmOpen')
        ->assertStatus(200)
        ->assertSet('successMessage', null)
        ->assertSet('openingStationId', null)
        ->assertSet('closeErrorMessage', fn ($m) => is_string($m)
            && str_contains($m, 'sudah terbuka')
            && ! str_contains($m, 'Buka Kembali Periode'))
        ->assertViewHas('period', fn ($row) => $row['status_summary'] === 'open');

    expect(PeriodStation::findOrFail($stationId)->status->value)->toBe('open');
});

// Scenario: "Buka Periode Pelaporan — Stasiun sudah tertutup"
it('buka stasiun yang sudah tertutup: pesan mengarahkan ke Buka Kembali dan status tetap Tertutup', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->closed($this->adminA, '2026-11-01 09:14:00')
        ->create();

    $stationId = detailStationId($period);

    detailFor($period)
        ->call('askOpen', $stationId)
        ->call('confirmOpen')
        ->assertStatus(200)
        ->assertSet('successMessage', null)
        ->assertSet('closeErrorMessage', fn ($m) => is_string($m)
            && str_contains($m, 'sudah tertutup')
            && str_contains($m, 'Buka Kembali Periode'))
        ->assertViewHas('period', fn ($row) => $row['status_summary'] === 'closed')
        // A closed station offers "Buka Kembali", never "Buka Stasiun".
        ->assertSeeHtml("station-reopen-button-{$stationId}")
        ->assertDontSeeHtml("station-open-button-{$stationId}");

    expect(PeriodStation::findOrFail($stationId)->status->value)->toBe('closed');
});

// Scenario: "Buka Periode Pelaporan — Baris stasiun tidak ditemukan"
it('buka stasiun yang sudah dihapus: pesan tidak ditemukan dan komponen tetap hidup', function () {
    $period = draftPeriodForDetail($this->businessUnitA, 'Periode Sementara');
    $stationId = detailStationId($period);

    $component = detailFor($period)
        ->call('askOpen', $stationId)
        ->assertSet('openingStationId', $stationId);

    Period::whereKey($period->id)->delete();

    $component
        ->call('confirmOpen')
        ->assertStatus(200)
        ->assertSet('openingStationId', null)
        ->assertSet('openingStation', null)
        ->assertSet('successMessage', null)
        ->assertSet('closeErrorMessage', fn ($m) => is_string($m) && str_contains($m, 'tidak ditemukan'))
        ->assertSet('notFound', true);
});

// Scenario: "Buka Periode Pelaporan — Dua Admin membuka bersamaan"
it('dua Admin membuka stasiun bersamaan: hanya yang pertama berhasil, yang kedua diberi tahu sudah terbuka', function () {
    $period = draftPeriodForDetail($this->businessUnitA);
    $stationId = detailStationId($period);

    // Both Admins render the page while the station is still draft.
    $adminX = detailFor($period)
        ->call('askOpen', $stationId)
        ->assertSet('openingStationId', $stationId);

    // Admin A gets there first.
    Livewire::actingAs($this->adminA)
        ->test(DetailPeriodePelaporan::class, ['id' => $period->id])
        ->call('askOpen', $stationId)
        ->call('confirmOpen')
        ->assertSet('successMessage', 'Stasiun periode berhasil dibuka.')
        ->assertSet('closeErrorMessage', null);

    expect($period->fresh()->updated_by)->toBe($this->adminA->id);

    // Admin X confirms the same opening a moment later.
    $adminX
        ->call('confirmOpen')
        ->assertStatus(200)
        ->assertSet('successMessage', null)
        ->assertSet('closeErrorMessage', fn ($m) => is_string($m) && str_contains($m, 'sudah terbuka'))
        ->assertViewHas('period', fn ($row) => $row['status_summary'] === 'open');

    // Admin X never overwrote Admin A's stamp.
    expect(PeriodStation::findOrFail($stationId)->status->value)->toBe('open');
    expect($period->fresh()->updated_by)->toBe($this->adminA->id);
});

// Scenario: "Buka Periode Pelaporan — Bukan Admin mencoba membuka stasiun"
it('akses ditolak: non-Admin tidak dapat memicu aksi Buka Stasiun maupun melihat tombolnya', function (string $role) {
    $user = match ($role) {
        'supervisor' => $this->supervisor,
        'mill_management' => $this->millManagement,
        'operator' => $this->operator,
    };

    $period = draftPeriodForDetail($this->businessUnitA, 'Periode Draft');
    $stationId = detailStationId($period);

    $response = $this->actingAs($user, 'web')->get("/master-data/periods/{$period->id}");
    $response->assertForbidden();
    $response->assertDontSee('station-open-button', false);

    $this->actingAs($user, 'web')
        ->postJson("/api/period-stations/{$stationId}/open")
        ->assertStatus(403);

    expect(PeriodStation::findOrFail($stationId)->status->value)->toBe('draft');
    expect($period->fresh()->updated_by)->toBeNull();
})->with([
    'supervisor' => ['supervisor'],
    'mill management' => ['mill_management'],
    'operator' => ['operator'],
]);

// Scenario: "status Terbuka tidak dapat dikembalikan ke Draft"
it('baris Terbuka hanya menawarkan Tutup Stasiun — tidak ada aksi apa pun yang mengembalikannya ke Draft', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    $stationId = detailStationId($period);

    detailFor($period)
        ->assertSeeHtml("station-close-button-{$stationId}")
        ->assertDontSeeHtml("station-open-button-{$stationId}")
        ->assertDontSeeHtml("station-reopen-button-{$stationId}")
        // There is no status control on the edit form either — a period takes
        // neither a status nor a station choice.
        ->call('openEditForm')
        ->assertSet('showForm', true)
        ->assertDontSeeHtml('wire:model="form.status"')
        ->assertDontSeeHtml('data-testid="status-select"')
        ->assertDontSeeHtml('data-testid="station-type-select"')
        ->assertDontSeeHtml('wire:model="station_type"');

    expect(PeriodStation::findOrFail($stationId)->status->value)->toBe('open');
});

// Scenario: "periode dengan stasiun Terbuka tetap dapat diubah dan dihapus"
it('periode dengan stasiun Terbuka tetap dapat diubah dan dihapus dari layar detail', function () {
    $period = draftPeriodForDetail($this->businessUnitA, 'Periode Agustus 2026');
    $stationId = detailStationId($period);

    $component = detailFor($period)
        ->call('askOpen', $stationId)
        ->call('confirmOpen')
        ->assertSet('successMessage', 'Stasiun periode berhasil dibuka.')
        // Edit — accepted, no PERIOD_CLOSED_IMMUTABLE: only 'closed' locks.
        ->call('openEditForm')
        ->assertSet('showForm', true)
        ->assertSet('deleteErrorMessage', null)
        ->set('form.name', 'Periode Agustus 2026 (revisi)')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('successMessage', 'Periode berhasil diperbarui.')
        ->assertViewHas('period', fn ($row) => $row['name'] === 'Periode Agustus 2026 (revisi)');

    // Delete — accepted too. The page is ABOUT the period that just went
    // away, so the only honest destination is the list.
    $component
        ->call('askDelete')
        ->assertSet('confirmingDelete', true)
        ->assertSeeHtml('data-testid="confirm-delete-button"')
        ->call('confirmDelete')
        ->assertRedirect(route('master-data.periods'));

    expect(Period::find($period->id))->toBeNull();
    expect(PeriodStation::find($stationId))->toBeNull();
});

// Scenario: "membuka satu stasiun tidak menyentuh stasiun lain"
it('membuka satu stasiun tidak menyentuh baris lain dan membuat ringkasan periode jadi Campuran', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->noStations()
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->create();

    PeriodStation::factory()->forPeriod($period)->stationType('sterilizer')->draft()->create();
    PeriodStation::factory()->forPeriod($period)->stationType('clarification')->draft()->create();

    $sterilizerRow = detailStationId($period, 'sterilizer');
    $clarificationRow = detailStationId($period, 'clarification');

    detailFor($period)
        ->assertViewHas('period', fn ($row) => $row['status_summary'] === 'draft')
        ->call('askOpen', $sterilizerRow)
        ->call('confirmOpen')
        ->assertSet('successMessage', 'Stasiun periode berhasil dibuka.')
        ->assertViewHas('period', fn ($row) => $row['status_summary'] === 'mixed'
            && collect($row['stations'])->pluck('status', 'station_type')->all() === [
                'sterilizer' => 'open',
                'clarification' => 'draft',
            ])
        ->assertSee('Campuran')
        ->assertSeeHtml("station-close-button-{$sterilizerRow}")
        ->assertSeeHtml("station-open-button-{$clarificationRow}");

    expect(PeriodStation::findOrFail($clarificationRow)->status->value)->toBe('draft');
});

// Hapus yang ditolak karena ada stasiun tertutup: tombolnya mati, dan
// memicunya paksa tetap ditolak dengan alasan yang sama.
it('hapus periode ditolak selama ada stasiun tertutup, dan halaman tetap di tempat', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->closed($this->adminA, '2026-11-01 09:14:00')
        ->create();

    detailFor($period)
        ->call('askDelete')
        ->call('confirmDelete')
        ->assertNoRedirect()
        ->assertSet('confirmingDelete', false)
        ->assertSet('deleteErrorMessage', fn ($m) => is_string($m) && str_contains($m, 'Buka kembali periode terlebih dahulu'))
        ->assertSeeHtml('data-testid="delete-error"')
        ->assertViewHas('period', fn ($row) => $row['id'] === $period->id);

    expect(Period::find($period->id))->not->toBeNull();
});
