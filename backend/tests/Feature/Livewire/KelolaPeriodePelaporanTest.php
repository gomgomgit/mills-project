<?php

/**
 * KelolaPeriodePelaporanTest (Feature/Livewire) —
 * screen-128--kelola-periode-pelaporan / usecase-128 (CRUD) +
 * usecase-140 (tutup & buka kembali) + usecase-144 (buka stasiun draft).
 *
 * Component tests for App\Livewire\MasterData\KelolaPeriodePelaporan, one
 * per test_scenarios entry whose `component_test` is non-empty (scenarios
 * 1–13, 16–18 and 21–28; scenarios 14, 15, 19 and 20 carry an empty
 * component_test — they are pure API/mobile-sync scenarios and live,
 * skipped, in tests/Feature/Api/KelolaPeriodePelaporanTest.php), plus the
 * cancelOpen() path and the shapes the one-row-per-station-type model made
 * impossible to test at all: a period with two stations in different statuses,
 * closing one without disturbing the other, the Edit/Hapus enable rule, the
 * per-station unverified figure, and update()'s station backfill. Mirrors
 * tests/Feature/Livewire/KelolaProductionLineTest.php's structure.
 *
 * BINDING SHAPE: `business_unit_id` is a bare top-level property; `name`,
 * `start_date` and `end_date` are bound as `form.<field>`. THERE IS NO
 * `station_type` BINDING — a period takes no station choice, so the form has
 * no such control and the property is gone (scenario 27 asserts the select is
 * absent, not merely unused). Filters are `filterBusinessUnitId` /
 * `filterStatus`.
 *
 * THE LIST IS COLLAPSED BY DEFAULT. A period's station rows are rendered only
 * while its id is in `$expandedPeriodIds` — `toggleExpanded($periodId)` flips
 * it, and askClose()/askOpen()/askReopen() expand the affected period by
 * themselves so the result is visible where the action was taken. Tests that
 * assert on a station row therefore expand first (or go through one of those
 * three actions).
 *
 * PER-STATION ACTIONS TAKE A `period_stations` ID. livewireStationId() is the
 * only place these tests derive one, so no test can pass a period id to
 * askClose()/askOpen()/askReopen() and pass for the wrong reason. The
 * properties they set are named `closingStationId` / `openingStationId` /
 * `confirmingReopenStationId` for the same reason.
 *
 * ACCESS CONTROL is route-level only ('auth' + 'role:admin' in
 * routes/web.php, App\Http\Middleware\EnsureRole aborts 403 before the
 * component ever mounts) — so the two "non-Admin" scenarios assert the
 * ROUTE, via a plain HTTP GET, rather than mounting the component with a
 * non-admin actor, which would assert a guard the component does not own.
 */

use App\Enums\UserRole;
use App\Livewire\MasterData\KelolaPeriodePelaporan;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\SterilizerRecord;
use App\Models\ThreshingRecord;
use App\Models\User;
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
function livewireStationId(Period|string $period, string $stationType = 'sterilizer'): string
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
function livewireButtonTag(string $html, string $testId): string
{
    $matched = preg_match(
        '/<button[^>]*data-testid="'.preg_quote($testId, '/').'"[^>]*>/',
        $html,
        $matches
    );

    expect($matched)->toBe(1, "tombol dengan data-testid=\"$testId\" tidak ditemukan");

    return $matches[0];
}

// Scenario 1: "Kelola Periode Pelaporan — success"
it('berhasil: mengisi form tambah periode lalu barisnya muncul di tabel dengan status draft', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('openCreateForm')
        ->assertSet('showForm', true)
        ->set('business_unit_id', $this->businessUnitA->id)
        ->set('form.name', 'Oktober 2026')
        ->set('form.start_date', '2026-10-01')
        ->set('form.end_date', '2026-10-31')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showForm', false)
        ->assertSet('formErrorMessage', null)
        ->assertSet('successMessage', 'Periode berhasil dibuat.')
        ->assertViewHas('periods', fn ($rows) => collect($rows)->contains(
            fn ($row) => $row['name'] === 'Oktober 2026'
                && $row['status_summary'] === 'draft'
                && $row['business_unit_name'] === 'Mill Alpha'
                && $row['station_count'] === 1
                && $row['closed_station_count'] === 0
                && $row['is_immutable'] === false
                // The station list is DERIVED from the mill's inventory — the
                // form never asked for it.
                && $row['stations'][0]['station_type_label'] === 'Sterilizer'
                && $row['stations'][0]['status'] === 'draft'
                && $row['stations'][0]['closed_by_name'] === null
        ));

    $stored = Period::where('name', 'Oktober 2026')->firstOrFail();
    expect($stored->business_unit_id)->toBe($this->businessUnitA->id);
    expect(PeriodStation::where('period_id', $stored->id)->pluck('station_type')->all())
        ->toBe(['sterilizer']);
});

/**
 * The form lost the "Jenis Stasiun" control, so the Admin lost a choice they
 * used to have. They get told what the period will cover instead — read from
 * PeriodService::activeStationTypesForMill(), the same call create() makes.
 */
it('form tambah: memperlihatkan daftar stasiun yang akan didaftarkan begitu Business Unit dipilih', function () {
    Station::factory()->forBusinessUnit($this->businessUnitA)->clarification()->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('openCreateForm')
        // Nothing to preview until a mill is chosen.
        ->assertSet('business_unit_id', '')
        ->assertViewHas('stationPreview', [])
        ->assertSee('Pilih Business Unit untuk melihat jenis stasiun')
        ->set('business_unit_id', $this->businessUnitA->id)
        ->assertViewHas('stationPreview', fn ($preview) => collect($preview)->pluck('code')->all() === ['sterilizer', 'clarification'])
        ->assertSee('otomatis mencakup 2 jenis stasiun aktif')
        ->assertSee('Sterilizer')
        ->assertSee('Clarification')
        // A mill with no stations at all is called out rather than shown as an
        // empty list the Admin has to interpret.
        ->set('business_unit_id', $this->businessUnitB->id)
        ->assertViewHas('stationPreview', [])
        ->assertSeeHtml('data-testid="station-preview-empty"');
});

// Scenario 2: "Rentang tanggal tumpang tindih"
it('rentang tumpang tindih: form tetap terbuka dengan isian utuh dan pesan menyebut periode yang bentrok', function () {
    Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('openCreateForm')
        ->set('business_unit_id', $this->businessUnitA->id)
        ->set('form.name', 'Oktober Tambahan')
        ->set('form.start_date', '2026-10-15')
        ->set('form.end_date', '2026-11-15')
        ->call('save')
        // The modal stays open and the Admin's input survives.
        ->assertSet('showForm', true)
        ->assertSet('form.name', 'Oktober Tambahan')
        ->assertSet('form.start_date', '2026-10-15')
        ->assertSet('business_unit_id', $this->businessUnitA->id)
        ->assertSet('formErrorMessage', fn ($message) => is_string($message) && str_contains($message, 'Oktober 2026'))
        ->assertViewHas('periods', fn ($rows) => count($rows) === 1);

    expect(Period::count())->toBe(1);
});

// Scenario 3: "Tanggal selesai lebih awal dari tanggal mulai"
it('tanggal terbalik: menampilkan error di bawah Tanggal Selesai dan tidak menyimpan', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('openCreateForm')
        ->set('business_unit_id', $this->businessUnitA->id)
        ->set('form.name', 'Periode Terbalik')
        ->set('form.start_date', '2026-10-31')
        ->set('form.end_date', '2026-10-01')
        ->call('save')
        ->assertHasErrors(['form.end_date'])
        ->assertSet('showForm', true);

    expect(Period::count())->toBe(0);
    expect(PeriodStation::count())->toBe(0);
});

// Scenario 4: "Nama periode sudah dipakai"
it('nama duplikat: menampilkan error di bawah Nama Periode dan tabel tidak bertambah', function () {
    Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('openCreateForm')
        ->set('business_unit_id', $this->businessUnitA->id)
        ->set('form.name', 'Oktober 2026')
        ->set('form.start_date', '2026-12-01')
        ->set('form.end_date', '2026-12-31')
        ->call('save')
        ->assertHasErrors(['form.name'])
        ->assertSet('showForm', true)
        ->assertViewHas('periods', fn ($rows) => count($rows) === 1);

    expect(Period::count())->toBe(1);
});

/**
 * The form used to mirror PeriodService::validate() in a local
 * buildValidator(), and the copy had drifted: it scoped the unique-name rule
 * to (mill, station_type) and added a whereNull('business_unit_id') branch the
 * service never had. The mirror is gone, so the form's verdict IS the
 * service's. This test pins the case the two used to disagree on — a name
 * reused across two mills, which the drifted copy refused and the service
 * allows.
 */
it('aturan validasi form identik dengan service: nama yang sama di mill lain diterima', function () {
    Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('openCreateForm')
        ->set('business_unit_id', $this->businessUnitB->id)
        ->set('form.name', 'Oktober 2026')
        ->set('form.start_date', '2026-10-01')
        ->set('form.end_date', '2026-10-31')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('successMessage', 'Periode berhasil dibuat.');

    expect(Period::where('name', 'Oktober 2026')->count())->toBe(2);
});

// Scenario 5: "Mengubah atau menghapus periode yang sudah tertutup"
it('ada stasiun tertutup: Edit dan Hapus mati di tabel dan ditolak bila tetap dipicu', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->closed($this->adminA, '2026-11-01 09:14:00')
        ->create();

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->assertViewHas('periods', fn ($rows) => collect($rows)->contains(
            fn ($row) => $row['id'] === $period->id && $row['is_immutable'] === true
        ));

    // The buttons EXIST and are DISABLED — an Admin must see that editing is
    // blocked rather than hunt for a vanished control.
    $html = $component->html();
    expect(livewireButtonTag($html, "edit-button-{$period->id}"))->toContain('disabled');
    expect(livewireButtonTag($html, "delete-button-{$period->id}"))->toContain('disabled');

    $component
        // Edit is refused before the form even opens (forged/stale call).
        ->call('openEditForm', $period->id)
        ->assertSet('showForm', false)
        ->assertSet('deleteErrorMessage', fn ($m) => is_string($m) && str_contains($m, 'Buka kembali periode terlebih dahulu'))
        // Delete is refused too, and the row stays.
        ->call('askDelete', $period->id)
        ->call('confirmDelete')
        ->assertSet('confirmingDeleteId', null)
        ->assertSet('deleteErrorMessage', fn ($m) => is_string($m) && str_contains($m, 'Buka kembali periode terlebih dahulu'))
        ->assertViewHas('periods', fn ($rows) => collect($rows)->contains(
            fn ($row) => $row['id'] === $period->id && $row['name'] === 'Oktober 2026'
        ));

    $fresh = $period->fresh();
    expect($fresh)->not->toBeNull();
    expect($fresh->name)->toBe('Oktober 2026');
    expect(PeriodStation::findOrFail(livewireStationId($period))->status->value)->toBe('closed');
});

/**
 * THE ENABLE RULE IS `is_immutable`, WHICH IS "ANY STATION CLOSED" — not "all
 * closed" and not "the period is closed", a thing that no longer exists. Draft
 * and open stations leave Edit/Hapus fully usable; ONE closed station out of
 * two switches them both off.
 */
it('Edit dan Hapus aktif selama hanya ada stasiun draft/open, dan mati begitu satu stasiun ditutup', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->noStations()
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->create();

    PeriodStation::factory()->forPeriod($period)->stationType('sterilizer')->open()->create();
    PeriodStation::factory()->forPeriod($period)->stationType('clarification')->draft()->create();

    $component = Livewire::actingAs($this->admin)->test(KelolaPeriodePelaporan::class);

    $html = $component->html();
    expect(livewireButtonTag($html, "edit-button-{$period->id}"))->not->toContain('disabled');
    expect(livewireButtonTag($html, "delete-button-{$period->id}"))->not->toContain('disabled');
    $component->assertViewHas('periods', fn ($rows) => collect($rows)->contains(
        fn ($row) => $row['id'] === $period->id
            && $row['status_summary'] === 'mixed'
            && $row['is_immutable'] === false
    ));

    // Editing really is allowed in this state.
    $component
        ->call('openEditForm', $period->id)
        ->assertSet('showForm', true)
        ->assertSet('deleteErrorMessage', null)
        ->call('closeForm');

    // Close ONE of the two stations.
    $component
        ->call('askClose', livewireStationId($period, 'sterilizer'))
        ->call('confirmClose')
        ->assertSet('successMessage', 'Stasiun periode berhasil ditutup.');

    $html = $component->html();
    expect(livewireButtonTag($html, "edit-button-{$period->id}"))->toContain('disabled');
    expect(livewireButtonTag($html, "delete-button-{$period->id}"))->toContain('disabled');
    $component->assertViewHas('periods', fn ($rows) => collect($rows)->contains(
        fn ($row) => $row['id'] === $period->id
            && $row['is_immutable'] === true
            && $row['closed_station_count'] === 1
    ));
});

// Scenario 6: "Belum ada Business Unit"
it('belum ada Business Unit: dropdown kosong dan form tidak dapat disimpan', function () {
    Period::query()->delete();
    SterilizerRecord::query()->delete();
    Station::query()->delete();
    ProductionLine::query()->delete();
    User::query()->update(['business_unit_id' => null]);
    BusinessUnit::query()->delete();

    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->assertViewHas('businessUnitOptions', [])
        ->call('openCreateForm')
        ->set('form.name', 'Oktober 2026')
        ->set('form.start_date', '2026-10-01')
        ->set('form.end_date', '2026-10-31')
        ->call('save')
        ->assertHasErrors(['business_unit_id'])
        ->assertSet('showForm', true)
        // The blade renders the "create a Business Unit first" hint on the
        // empty select.
        ->assertSee('Belum ada Business Unit');

    expect(Period::count())->toBe(0);
});

// Scenario 7: "Periode sudah dihapus pengguna lain"
it('periode dihapus pengguna lain: pesan error ramah, daftar dimuat ulang, komponen tetap hidup', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(null)
        ->named('Periode Sementara')
        ->range('2026-11-01', '2026-11-30')
        ->create();

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('openEditForm', $period->id)
        ->assertSet('showForm', true)
        ->assertSet('editingId', $period->id);

    // Another Admin deletes the row while this form is open.
    Period::whereKey($period->id)->delete();

    $component
        ->set('form.name', 'Periode Sementara Revisi')
        ->call('save')
        ->assertStatus(200)
        ->assertSet('formErrorMessage', fn ($m) => is_string($m) && str_contains($m, 'tidak ditemukan'))
        ->assertViewHas('periods', fn ($rows) => ! collect($rows)->contains(
            fn ($row) => $row['id'] === $period->id
        ));
});

// Scenario 8: "pengguna non-Admin mencoba mengakses"
it('akses ditolak: non-Admin tidak dapat membuka /master-data/periods sama sekali', function (string $role) {
    $user = match ($role) {
        'supervisor' => $this->supervisor,
        'mill_management' => $this->millManagement,
        'operator' => $this->operator,
    };

    Period::factory()->forBusinessUnit($this->businessUnitA)->named('Periode Rahasia')->create();

    // EnsureRole::forbidden() -> abort(403) BEFORE the component mounts, so
    // no period data is ever rendered.
    $response = $this->actingAs($user, 'web')->get('/master-data/periods');

    $response->assertForbidden();
    $response->assertDontSee('Periode Rahasia');
})->with([
    'supervisor' => ['supervisor'],
    'mill management' => ['mill_management'],
    'operator' => ['operator'],
]);

// Scenario 9: "Business Unit tidak diisi"
it('Business Unit kosong: error di bawah field Business Unit sementara isian lain tetap ada', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('openCreateForm')
        ->set('form.name', 'Oktober 2026')
        ->set('form.start_date', '2026-10-01')
        ->set('form.end_date', '2026-10-31')
        ->call('save')
        ->assertHasErrors(['business_unit_id'])
        ->assertSet('showForm', true)
        ->assertSet('form.name', 'Oktober 2026')
        ->assertSet('form.start_date', '2026-10-01')
        ->assertSet('form.end_date', '2026-10-31');

    expect(Period::count())->toBe(0);
});

/**
 * update()'s BACKFILL, through the screen. A station type the mill gains after
 * the period was created has no row — it cannot be closed and its records are
 * never locked. Saving the period from the Edit form adds it, as `draft`, and
 * the form says so beforehand.
 */
it('simpan ulang periode: mendaftarkan jenis stasiun yang ditambahkan ke mill setelah periode dibuat', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    $sterilizerRowId = livewireStationId($period, 'sterilizer');

    // The mill gains a Clarification station AFTER the period exists.
    Station::factory()->forBusinessUnit($this->businessUnitA)->clarification()->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        // The drifted snapshot: one row, nothing that can close Clarification.
        ->assertViewHas('periods', fn ($rows) => collect($rows)->contains(
            fn ($row) => $row['id'] === $period->id && $row['station_count'] === 1
        ))
        ->call('openEditForm', $period->id)
        ->assertSet('showForm', true)
        // The form warns which rows the save will add, before it is saved.
        ->assertViewHas('stationPreview', fn ($preview) => collect($preview)->pluck('is_new', 'code')->all() === [
            'sterilizer' => false,
            'clarification' => true,
        ])
        ->assertSeeHtml('data-testid="station-preview-new-clarification"')
        ->assertSee('menambahkan')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('successMessage', 'Periode berhasil diperbarui.')
        ->assertViewHas('periods', fn ($rows) => collect($rows)->contains(
            fn ($row) => $row['id'] === $period->id
                && $row['station_count'] === 2
                && collect($row['stations'])->pluck('status', 'station_type')->all() === [
                    'sterilizer' => 'open',
                    'clarification' => 'draft',
                ]
        ));

    // The pre-existing row is the SAME row, with its status intact — the
    // backfill adds, it never replaces and never resets.
    expect(PeriodStation::findOrFail($sterilizerRowId)->status->value)->toBe('open');
    expect(PeriodStation::where('period_id', $period->id)->count())->toBe(2);
});

/**
 * THE GAP THIS LEAVES, STATED AS A TEST RATHER THAN LEFT TO BE DISCOVERED. A
 * period with one closed station is immutable, so update() refuses it — and
 * with it the backfill. Nothing is added, and the closed row is not touched
 * either. The way out today is manual: reopen the station, save, close it
 * again. See PeriodService::update()'s docblock.
 */
it('periode dengan stasiun tertutup tidak bisa di-backfill: Edit mati dan baris baru tidak pernah ditambahkan', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->closed($this->adminA, '2026-11-01 09:14:00')
        ->create();

    $sterilizerRowId = livewireStationId($period, 'sterilizer');
    Station::factory()->forBusinessUnit($this->businessUnitA)->clarification()->create();

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('openEditForm', $period->id)
        // Refused: the form never opens, so the backfill never runs.
        ->assertSet('showForm', false)
        ->assertSet('deleteErrorMessage', fn ($m) => is_string($m) && str_contains($m, 'Buka kembali periode terlebih dahulu'));

    expect(PeriodStation::where('period_id', $period->id)->count())->toBe(1);
    expect(PeriodStation::findOrFail($sterilizerRowId)->closed_by)->toBe($this->adminA->id);

    // And the documented way out really does work end to end.
    $component
        ->call('askReopen', $sterilizerRowId)
        ->call('confirmReopen')
        ->assertSet('successMessage', 'Stasiun periode berhasil dibuka kembali.')
        ->call('openEditForm', $period->id)
        ->assertSet('showForm', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(PeriodStation::where('period_id', $period->id)->pluck('station_type')->sort()->values()->all())
        ->toBe(['clarification', 'sterilizer']);
});

// Scenario 10: "Tutup & Buka Kembali Periode Pelaporan — success"
it('tutup stasiun: dialog memuat jumlah belum terverifikasi, konfirmasi mengubah badge menjadi Tertutup', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    $stationId = livewireStationId($period);

    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-10-10')->count(2)->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        // Collapsed by default: the station row (and its action) is not there
        // until the period is expanded.
        ->assertDontSeeHtml("station-close-button-{$stationId}")
        ->call('toggleExpanded', $period->id)
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
        ->assertViewHas('periods', fn ($rows) => collect($rows)->contains(
            fn ($row) => $row['id'] === $period->id
                && $row['status_summary'] === 'closed'
                && $row['closed_station_count'] === 1
                && $row['stations'][0]['closed_by_name'] === 'Admin X'
                && $row['stations'][0]['closed_at'] !== null
        ))
        // A closed station offers reopen only.
        ->assertSeeHtml("station-reopen-button-{$stationId}")
        ->assertDontSeeHtml("station-close-button-{$stationId}");

    expect(PeriodStation::findOrFail($stationId)->status->value)->toBe('closed');
});

// Scenario 11: "buka kembali periode yang sudah tertutup"
it('buka kembali stasiun: badge jadi Terbuka dan kolom penutupan kosong kembali', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->closed($this->adminA, '2026-11-01 09:14:00')
        ->create();

    $stationId = livewireStationId($period);

    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('askReopen', $stationId)
        ->assertSet('confirmingReopenStationId', $stationId)
        // The inline confirmation names the station it is about.
        ->assertSee('Buka kembali Sterilizer?')
        ->call('confirmReopen')
        ->assertSet('confirmingReopenStationId', null)
        ->assertSet('successMessage', 'Stasiun periode berhasil dibuka kembali.')
        ->assertViewHas('periods', fn ($rows) => collect($rows)->contains(
            fn ($row) => $row['id'] === $period->id
                && $row['status_summary'] === 'open'
                && $row['is_immutable'] === false
                && $row['stations'][0]['closed_by'] === null
                && $row['stations'][0]['closed_at'] === null
        ))
        // Tutup Stasiun is available again on the station row, and Edit/Hapus
        // are live again on the period row.
        ->assertSeeHtml("station-close-button-{$stationId}")
        ->assertSeeHtml("edit-button-{$period->id}")
        ->assertSeeHtml("delete-button-{$period->id}");

    $fresh = PeriodStation::findOrFail($stationId);
    expect($fresh->status->value)->toBe('open');
    expect($fresh->closed_by)->toBeNull();
    expect($fresh->closed_at)->toBeNull();
});

// Scenario 12: "Admin membatalkan penutupan"
it('membatalkan penutupan: aksi close tidak pernah dipanggil dan status tidak berubah', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    $stationId = livewireStationId($period);

    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('askClose', $stationId)
        ->assertSet('closingStationId', $stationId)
        ->call('cancelClose')
        ->assertSet('closingStationId', null)
        ->assertSet('closingStation', null)
        ->assertSet('closingUnverifiedCount', 0)
        ->assertSet('successMessage', null)
        ->assertViewHas('periods', fn ($rows) => collect($rows)->contains(
            fn ($row) => $row['id'] === $period->id
                && $row['status_summary'] === 'open'
                && $row['stations'][0]['closed_by'] === null
        ));

    expect(PeriodStation::findOrFail($stationId)->status->value)->toBe('open');
});

// Scenario 13: "masih banyak data belum terverifikasi"
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

    $sterilizerRow = livewireStationId($period, 'sterilizer');
    $threshingRow = livewireStationId($period, 'threshing');

    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('askClose', $sterilizerRow)
        // 5, not 7: the figure is Sterilizer's own. Before the split there was
        // one count per period, so this dialog claimed 7 — a number that had
        // nothing to do with the action being confirmed.
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
        // And it really does work.
        ->call('confirmClose')
        ->assertSet('successMessage', 'Stasiun periode berhasil ditutup.')
        // The OTHER station's dialog reports its own, different figure.
        ->call('askClose', $threshingRow)
        ->assertSet('closingUnverifiedCount', 2)
        ->assertSee('2 data Threshing belum terverifikasi');
});

/**
 * Closing one station must not disturb how the others are DISPLAYED — the same
 * row ids, the same statuses, the same empty closure columns. This is the shape
 * the table split exists for and could not be asserted before.
 */
it('menutup satu stasiun tidak mengubah tampilan stasiun lain di baris periode yang sama', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->noStations()
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->create();

    PeriodStation::factory()->forPeriod($period)->stationType('sterilizer')->open()->create();
    PeriodStation::factory()->forPeriod($period)->stationType('clarification')->open()->create();

    $sterilizerRow = livewireStationId($period, 'sterilizer');
    $clarificationRow = livewireStationId($period, 'clarification');

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('toggleExpanded', $period->id);

    $before = collect($component->viewData('periods'))
        ->firstWhere('id', $period->id)['stations'];
    $clarificationBefore = collect($before)->firstWhere('station_type', 'clarification');

    $component
        ->call('askClose', $sterilizerRow)
        ->call('confirmClose')
        ->assertSet('successMessage', 'Stasiun periode berhasil ditutup.')
        ->assertViewHas('periods', fn ($rows) => collect($rows)->contains(
            fn ($row) => $row['id'] === $period->id && $row['status_summary'] === 'mixed'
        ));

    $after = collect($component->viewData('periods'))
        ->firstWhere('id', $period->id)['stations'];
    $clarificationAfter = collect($after)->firstWhere('station_type', 'clarification');

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

/**
 * A period whose stations disagree renders as ONE parent row badged "Campuran",
 * with the per-station truth one click away. The summary is never a substitute
 * for the station rows when an action is decided — but it is the only honest
 * single value for the list.
 */
it('satu periode dengan dua stasiun berstatus berbeda ter-render sebagai satu baris Campuran yang dapat dibuka', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->noStations()
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->create();

    PeriodStation::factory()->forPeriod($period)->stationType('sterilizer')
        ->closed($this->adminA, '2026-11-01 09:14:00')->create();
    PeriodStation::factory()->forPeriod($period)->stationType('clarification')->draft()->create();

    $sterilizerRow = livewireStationId($period, 'sterilizer');
    $clarificationRow = livewireStationId($period, 'clarification');

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->assertViewHas('periods', fn ($rows) => count($rows) === 1 && $rows[0]['status_summary'] === 'mixed')
        // ONE parent row, summarised — not two rows and not 19.
        ->assertSee('Campuran')
        ->assertSee('2 stasiun')
        ->assertSee('1 tertutup')
        // Nothing per-station is rendered while collapsed.
        ->assertDontSeeHtml("period-station-row-{$sterilizerRow}")
        ->assertDontSeeHtml("period-station-row-{$clarificationRow}")
        ->call('toggleExpanded', $period->id)
        ->assertSeeHtml("period-station-row-{$sterilizerRow}")
        ->assertSeeHtml("period-station-row-{$clarificationRow}")
        // Each station shows ITS OWN status, closer and action.
        ->assertSee('Sterilizer')
        ->assertSee('Clarification')
        ->assertSee('Tertutup')
        ->assertSee('Draft')
        ->assertSee('Admin A')
        ->assertSeeHtml("station-reopen-button-{$sterilizerRow}")
        ->assertSeeHtml("station-open-button-{$clarificationRow}")
        // Collapsing hides them again — the toggle is not one-way.
        ->call('toggleExpanded', $period->id)
        ->assertDontSeeHtml("period-station-row-{$sterilizerRow}");

    expect($component->get('expandedPeriodIds'))->toBe([]);
});

// Scenario 16: "menutup periode yang sudah tertutup"
it('stasiun tertutup: hanya aksi Buka Kembali yang ter-render pada baris stasiunnya', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->closed($this->adminA, '2026-11-01 09:14:00')
        ->create();

    $stationId = livewireStationId($period);

    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('toggleExpanded', $period->id)
        ->assertSee('Tertutup')
        ->assertSeeHtml("station-reopen-button-{$stationId}")
        ->assertDontSeeHtml("station-close-button-{$stationId}")
        ->assertDontSeeHtml("station-open-button-{$stationId}");
});

// Scenario 17: "dua Admin menutup periode bersamaan"
it('dua Admin menutup stasiun bersamaan: Admin kedua diberi tahu dan catatan penutup pertama tetap', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    $stationId = livewireStationId($period);

    // Admin B still sees the station as open and opens the dialog.
    $component = Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
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
        ->assertViewHas('periods', fn ($rows) => collect($rows)->contains(
            fn ($row) => $row['id'] === $period->id
                && $row['status_summary'] === 'closed'
                && $row['stations'][0]['closed_by_name'] === 'Admin A'
        ));

    // Admin X never overwrote Admin A's record.
    expect(PeriodStation::findOrFail($stationId)->closed_by)->toBe($this->adminA->id);
});

// Scenario 18: "pengguna selain Admin menutup periode"
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

    $openStationId = livewireStationId($openPeriod);
    $closedStationId = livewireStationId($closedPeriod);

    // The screen itself is unreachable, so neither action button is ever
    // rendered for this actor.
    $this->actingAs($user, 'web')->get('/master-data/periods')->assertForbidden();

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

// ── usecase-144 (Buka Stasiun) ──────────────────────────────────────────
//
// Scenarios 21–28 plus the cancel path. "Buka Stasiun" is offered on DRAFT
// station rows only, via a real dialog (askOpen -> openingStationId/
// openingStation -> confirmOpen), because draft -> open cannot be undone.
// Errors surface on the EXISTING $closeErrorMessage property (the
// [data-testid="close-error"] alert), not on a new one; success sets
// $successMessage.

/** A draft period on Mill Alpha — the only status that offers "Buka Stasiun". */
function draftPeriodForOpenComponent(BusinessUnit $businessUnit, string $name = 'Oktober 2026'): Period
{
    return Period::factory()
        ->forBusinessUnit($businessUnit)
        ->stationType('sterilizer')
        ->named($name)
        ->range('2026-10-01', '2026-10-31')
        ->draft()
        ->create();
}

// Scenario 21: "Buka Periode Pelaporan — sukses"
it('buka stasiun: dialog konfirmasi tampil, setelah konfirmasi badge jadi Terbuka dan tombol Buka Stasiun hilang', function () {
    $period = draftPeriodForOpenComponent($this->businessUnitA);
    $stationId = livewireStationId($period);

    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-10-10')->count(2)->create();
    $recordsBefore = SterilizerRecord::query()->orderBy('id')->get()
        ->map(fn ($r) => [$r->id, $r->checked_by, $r->acknowledged_by, (string) $r->updated_at])
        ->all();

    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('toggleExpanded', $period->id)
        // A draft station offers the action.
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
        ->assertViewHas('periods', fn ($rows) => collect($rows)->contains(
            fn ($row) => $row['id'] === $period->id
                && $row['status_summary'] === 'open'
                && $row['stations'][0]['closed_by'] === null
        ))
        ->assertSee('Terbuka')
        // The action is no longer offered for that station, while its close
        // action and the period's Edit/Hapus stay available.
        ->assertDontSeeHtml("station-open-button-{$stationId}")
        ->assertSeeHtml("station-close-button-{$stationId}")
        ->assertSeeHtml("edit-button-{$period->id}")
        ->assertSeeHtml("delete-button-{$period->id}");

    expect(PeriodStation::findOrFail($stationId)->status->value)->toBe('open');

    // Not one station record changed.
    $recordsAfter = SterilizerRecord::query()->orderBy('id')->get()
        ->map(fn ($r) => [$r->id, $r->checked_by, $r->acknowledged_by, (string) $r->updated_at])
        ->all();
    expect($recordsAfter)->toBe($recordsBefore);
});

// Alternative flow "Admin membatalkan pembukaan" — no bdd_scenario of its
// own, but the cancel path must not go untested: it resets the dialog and
// changes nothing.
it('membatalkan pembukaan: dialog tertutup, status tetap Draft, tanpa notifikasi sukses', function () {
    $period = draftPeriodForOpenComponent($this->businessUnitA);
    $stationId = livewireStationId($period);

    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('askOpen', $stationId)
        ->assertSet('openingStationId', $stationId)
        ->assertSeeHtml('data-testid="open-period-dialog"')
        ->call('cancelOpen')
        ->assertSet('openingStationId', null)
        ->assertSet('openingStation', null)
        ->assertSet('closeErrorMessage', null)
        ->assertSet('successMessage', null)
        ->assertDontSeeHtml('data-testid="open-period-dialog"')
        ->assertViewHas('periods', fn ($rows) => collect($rows)->contains(
            fn ($row) => $row['id'] === $period->id && $row['status_summary'] === 'draft'
        ))
        // The station row still offers the action, untouched.
        ->assertSeeHtml("station-open-button-{$stationId}");

    expect(PeriodStation::findOrFail($stationId)->status->value)->toBe('draft');
});

// Scenario 22: "Periode sudah terbuka"
it('buka stasiun yang sudah terbuka: pesan menyebut sudah terbuka dan status tidak berubah', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    $stationId = livewireStationId($period);

    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('toggleExpanded', $period->id)
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
        ->assertViewHas('periods', fn ($rows) => collect($rows)->contains(
            fn ($row) => $row['id'] === $period->id && $row['status_summary'] === 'open'
        ));

    expect(PeriodStation::findOrFail($stationId)->status->value)->toBe('open');
});

// Scenario 23: "Periode sudah tertutup"
it('buka stasiun yang sudah tertutup: pesan mengarahkan ke Buka Kembali dan status tetap Tertutup', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->closed($this->adminA, '2026-11-01 09:14:00')
        ->create();

    $stationId = livewireStationId($period);

    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('askOpen', $stationId)
        ->call('confirmOpen')
        ->assertStatus(200)
        ->assertSet('successMessage', null)
        ->assertSet('closeErrorMessage', fn ($m) => is_string($m)
            && str_contains($m, 'sudah tertutup')
            && str_contains($m, 'Buka Kembali Periode'))
        ->assertViewHas('periods', fn ($rows) => collect($rows)->contains(
            fn ($row) => $row['id'] === $period->id && $row['status_summary'] === 'closed'
        ))
        // A closed station offers "Buka Kembali", never "Buka Stasiun".
        ->assertSeeHtml("station-reopen-button-{$stationId}")
        ->assertDontSeeHtml("station-open-button-{$stationId}");

    expect(PeriodStation::findOrFail($stationId)->status->value)->toBe('closed');
});

// Scenario 24: "Periode tidak ditemukan"
it('buka stasiun yang sudah dihapus: pesan tidak ditemukan, komponen tetap hidup, baris hilang dari daftar', function () {
    $period = draftPeriodForOpenComponent($this->businessUnitA, 'Periode Sementara');
    $stationId = livewireStationId($period);

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('askOpen', $stationId)
        ->assertSet('openingStationId', $stationId);

    // Another Admin deletes the period while the dialog is open — the station
    // row cascades away with it.
    Period::whereKey($period->id)->delete();

    $component
        ->call('confirmOpen')
        ->assertStatus(200)
        ->assertSet('openingStationId', null)
        ->assertSet('openingStation', null)
        ->assertSet('successMessage', null)
        ->assertSet('closeErrorMessage', fn ($m) => is_string($m) && str_contains($m, 'tidak ditemukan'))
        ->assertViewHas('periods', fn ($rows) => ! collect($rows)->contains(
            fn ($row) => $row['id'] === $period->id
        ));
});

// Scenario 25: "Dua Admin membuka bersamaan"
it('dua Admin membuka stasiun bersamaan: hanya yang pertama berhasil, yang kedua diberi tahu sudah terbuka', function () {
    $period = draftPeriodForOpenComponent($this->businessUnitA);
    $stationId = livewireStationId($period);

    // Both Admins render the list while the station is still draft.
    $adminB = Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('askOpen', $stationId)
        ->assertSet('openingStationId', $stationId);

    // Admin A gets there first.
    Livewire::actingAs($this->adminA)
        ->test(KelolaPeriodePelaporan::class)
        ->call('askOpen', $stationId)
        ->call('confirmOpen')
        ->assertSet('successMessage', 'Stasiun periode berhasil dibuka.')
        ->assertSet('closeErrorMessage', null);

    expect($period->fresh()->updated_by)->toBe($this->adminA->id);

    // Admin B confirms the same opening a moment later.
    $adminB
        ->call('confirmOpen')
        ->assertStatus(200)
        ->assertSet('successMessage', null)
        ->assertSet('closeErrorMessage', fn ($m) => is_string($m) && str_contains($m, 'sudah terbuka'))
        ->assertViewHas('periods', fn ($rows) => collect($rows)->contains(
            fn ($row) => $row['id'] === $period->id && $row['status_summary'] === 'open'
        ));

    // Admin B never overwrote Admin A's stamp.
    expect(PeriodStation::findOrFail($stationId)->status->value)->toBe('open');
    expect($period->fresh()->updated_by)->toBe($this->adminA->id);
});

// Scenario 26: "Bukan Admin mencoba membuka periode"
it('akses ditolak: non-Admin tidak dapat memicu aksi Buka Stasiun maupun melihat tombolnya', function (string $role) {
    $user = match ($role) {
        'supervisor' => $this->supervisor,
        'mill_management' => $this->millManagement,
        'operator' => $this->operator,
    };

    $period = draftPeriodForOpenComponent($this->businessUnitA, 'Periode Draft');
    $stationId = livewireStationId($period);

    // The screen itself is unreachable (EnsureRole aborts 403 before mount),
    // so the button is never rendered for this actor.
    $response = $this->actingAs($user, 'web')->get('/master-data/periods');
    $response->assertForbidden();
    $response->assertDontSee('station-open-button', false);

    // And the action behind it is refused too.
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

// Scenario 27: "status Terbuka tidak dapat dikembalikan ke Draft"
it('stasiun Terbuka: form edit tidak menyediakan pilihan status maupun jenis stasiun, dan status tetap Terbuka', function () {
    $period = draftPeriodForOpenComponent($this->businessUnitA);
    $stationId = livewireStationId($period);

    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('askOpen', $stationId)
        ->call('confirmOpen')
        ->assertSet('successMessage', 'Stasiun periode berhasil dibuka.')
        ->call('openEditForm', $period->id)
        ->assertSet('showForm', true)
        // The form binds business unit, name and the two dates — and nothing
        // else. There is no status control, so no component action can produce
        // 'draft' from an open station...
        ->assertDontSeeHtml('wire:model.live="form.status"')
        ->assertDontSeeHtml('wire:model="form.status"')
        ->assertDontSeeHtml('data-testid="status-select"')
        // ...and no station-type control either: a period takes no station
        // choice any more, so the select and its `station_type` binding are
        // gone rather than merely unused.
        ->assertDontSeeHtml('data-testid="station-type-select"')
        ->assertDontSeeHtml('wire:model="station_type"')
        ->set('form.name', 'Oktober 2026 (revisi)')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('successMessage', 'Periode berhasil diperbarui.')
        ->assertViewHas('periods', fn ($rows) => collect($rows)->contains(
            fn ($row) => $row['id'] === $period->id
                && $row['status_summary'] === 'open'
                && $row['name'] === 'Oktober 2026 (revisi)'
        ));

    expect(PeriodStation::findOrFail($stationId)->status->value)->toBe('open');
});

// Scenario 28: "periode Terbuka tetap dapat diubah dan dihapus"
it('periode dengan stasiun Terbuka tetap dapat diubah dan dihapus seperti Draft', function () {
    $period = draftPeriodForOpenComponent($this->businessUnitA, 'Periode Agustus 2026');
    $stationId = livewireStationId($period);

    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('askOpen', $stationId)
        ->call('confirmOpen')
        ->assertSet('successMessage', 'Stasiun periode berhasil dibuka.')
        // Edit — accepted, no PERIOD_CLOSED_IMMUTABLE: only 'closed' locks.
        ->call('openEditForm', $period->id)
        ->assertSet('showForm', true)
        ->assertSet('deleteErrorMessage', null)
        ->set('form.name', 'Periode Agustus 2026 (revisi)')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('successMessage', 'Periode berhasil diperbarui.')
        ->assertViewHas('periods', fn ($rows) => collect($rows)->contains(
            fn ($row) => $row['id'] === $period->id && $row['name'] === 'Periode Agustus 2026 (revisi)'
        ))
        // Delete — accepted too, and the row disappears.
        ->call('askDelete', $period->id)
        ->call('confirmDelete')
        ->assertSet('deleteErrorMessage', null)
        ->assertSet('successMessage', 'Periode berhasil dihapus.')
        ->assertViewHas('periods', fn ($rows) => ! collect($rows)->contains(
            fn ($row) => $row['id'] === $period->id
        ));

    expect(Period::find($period->id))->toBeNull();
    expect(PeriodStation::find($stationId))->toBeNull();
});
