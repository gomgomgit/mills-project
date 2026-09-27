<?php

/**
 * KelolaPeriodePelaporanTest (Feature/Livewire) —
 * screen-128--kelola-periode-pelaporan / usecase-128 (CRUD).
 *
 * Component tests for App\Livewire\MasterData\KelolaPeriodePelaporan, one per
 * test_scenarios entry whose `component_test` is non-empty, plus the shapes the
 * one-row-per-station-type model made impossible to test at all: the Edit/Hapus
 * enable rule and update()'s station backfill.
 * Mirrors tests/Feature/Livewire/KelolaProductionLineTest.php's structure.
 *
 * THIS SCREEN IS THE LIST, AND ONLY THE LIST (2026-09-27). A period's station
 * rows used to hang under it in an expandable second <tr>, with every
 * per-station action (tutup / buka / buka kembali, and their dialogs) inside
 * it. All of that moved to screen-142--detail-periode-pelaporan, and the tests
 * for it moved with it, unweakened, to
 * tests/Feature/Livewire/DetailPeriodePelaporanTest.php. What this file still
 * asserts about stations is the SUMMARY the parent row shows
 * (`status_summary`, `station_count`, `closed_station_count`, `is_immutable`)
 * — plus, explicitly, that not one of the removed controls is left behind.
 *
 * WHERE A CLOSED OR OPEN STATION COMES FROM NOW: the factory, or a direct
 * `period_stations` UPDATE. It is SETUP here, never an action under test —
 * this component cannot close, open or reopen anything any more.
 *
 * BINDING SHAPE: `business_unit_id` is a bare top-level property; `name`,
 * `start_date` and `end_date` are bound as `form.<field>`. THERE IS NO
 * `station_type` BINDING — a period takes no station choice, so the form has
 * no such control and the property is gone (scenario 27 asserts the select is
 * absent, not merely unused). Filters are `filterBusinessUnitId` /
 * `filterStatus`.
 *
 * livewireStationId() is the only place this file turns a period into a
 * `period_stations` id, so a test can never confuse the two.
 *
 * ACCESS CONTROL is route-level only ('auth' + 'role:admin' in
 * routes/web.php, App\Http\Middleware\EnsureRole aborts 403 before the
 * component ever mounts) — so the "non-Admin" scenario asserts the ROUTE, via
 * a plain HTTP GET, rather than mounting the component with a non-admin actor,
 * which would assert a guard the component does not own.
 */

use App\Enums\UserRole;
use App\Livewire\MasterData\KelolaPeriodePelaporan;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\SterilizerRecord;
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

    // Close ONE of the two stations. Closing is screen-142's action (asserted
    // there); here it is setup for what the LIST does with the result.
    PeriodStation::whereKey(livewireStationId($period, 'sterilizer'))->update([
        'status' => 'closed',
        'closed_by' => $this->adminA->id,
        'closed_at' => now(),
    ]);

    // The change happened outside Livewire, so re-render before asserting on
    // the markup.
    $component->call('$refresh');

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

    // And the documented way out really does work end to end. The reopen
    // itself belongs to screen-142 now (it is asserted there, through the
    // detail component); here it is pure setup for what this screen does with
    // a period that is no longer frozen.
    PeriodStation::whereKey($sterilizerRowId)->update([
        'status' => 'open',
        'closed_by' => null,
        'closed_at' => null,
    ]);

    $component
        ->call('openEditForm', $period->id)
        ->assertSet('showForm', true)
        ->call('save')
        ->assertHasNoErrors();

    expect(PeriodStation::where('period_id', $period->id)->pluck('station_type')->sort()->values()->all())
        ->toBe(['clarification', 'sterilizer']);
});

/**
 * A period whose stations disagree renders as ONE row badged "Campuran". The
 * summary is all this screen shows of them: the per-station truth, and every
 * action that depends on it, lives on screen-142 behind the period-name link.
 */
it('satu periode dengan dua stasiun berstatus berbeda ter-render sebagai satu baris Campuran', function () {
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

    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->assertViewHas('periods', fn ($rows) => count($rows) === 1 && $rows[0]['status_summary'] === 'mixed')
        // ONE row, summarised — not two rows and not 19.
        ->assertSee('Campuran')
        ->assertSee('2 stasiun')
        ->assertSee('1 tertutup')
        // NOTHING per-station is rendered on this screen any more, and there
        // is no longer a control that would reveal it.
        ->assertDontSeeHtml("expand-button-{$period->id}")
        ->assertDontSeeHtml("period-stations-{$period->id}")
        ->assertDontSeeHtml("period-station-row-{$sterilizerRow}")
        ->assertDontSeeHtml("period-station-row-{$clarificationRow}")
        ->assertDontSeeHtml("station-reopen-button-{$sterilizerRow}")
        ->assertDontSeeHtml("station-open-button-{$clarificationRow}");
});

// Scenario 13: "Kelola Periode Pelaporan — Membuka detail periode dari daftar"
//
// The period name became the way IN to screen-142 on 2026-09-27, replacing the
// expand control. This test pins both halves: the link is there, and every
// data-testid the tech-spec lists as REMOVED from this screen really is gone —
// not merely unrendered for this particular row's status.
it('nama periode adalah tautan ke layar detail, dan tak satu pun aksi stasiun tersisa di daftar', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->noStations()
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->create();

    // One station per status, so a leftover action of ANY kind would show.
    PeriodStation::factory()->forPeriod($period)->stationType('sterilizer')
        ->closed($this->adminA, '2026-11-01 09:14:00')->create();
    PeriodStation::factory()->forPeriod($period)->stationType('clarification')->open()->create();
    PeriodStation::factory()->forPeriod($period)->stationType('boiler-room')->draft()->create();

    $component = Livewire::actingAs($this->admin)->test(KelolaPeriodePelaporan::class);

    $component
        ->assertSeeHtml('data-testid="period-link-'.$period->id.'"')
        ->assertSeeHtml('href="'.route('master-data.periods.detail', $period->id).'"')
        // The columns that stay.
        ->assertSeeHtml('data-testid="period-table"')
        ->assertSeeHtml('data-testid="station-summary-'.$period->id.'"')
        ->assertSeeHtml('data-testid="status-summary-'.$period->id.'"')
        ->assertSeeHtml('data-testid="edit-button-'.$period->id.'"')
        ->assertSeeHtml('data-testid="delete-button-'.$period->id.'"');

    // Every testid the tech-spec lists as removed from this screen.
    $html = $component->html();
    foreach ([
        'expand-button-',
        'period-stations-',
        'period-station-row-',
        'station-status-badge-',
        'station-close-button-',
        'station-open-button-',
        'station-reopen-button-',
        'unverified-warning',
        'unverified-count',
        'unverified-breakdown',
        'confirm-close-button',
        'cancel-close-button',
        'confirm-reopen-button',
        'open-period-dialog',
        'confirm-open-period',
        'cancel-open-period',
        'close-error',
    ] as $goneTestId) {
        expect($html)->not->toContain($goneTestId);
    }

    // And the actions behind them are not callable on this component either.
    foreach (['toggleExpanded', 'askClose', 'confirmClose', 'askOpen', 'confirmOpen', 'askReopen', 'confirmReopen'] as $goneMethod) {
        expect(method_exists(KelolaPeriodePelaporan::class, $goneMethod))
            ->toBeFalse("aksi stasiun `$goneMethod` seharusnya sudah pindah ke screen-142");
    }
});

// Scenario 27: "status Terbuka tidak dapat dikembalikan ke Draft"
it('stasiun Terbuka: form edit tidak menyediakan pilihan status maupun jenis stasiun, dan status tetap Terbuka', function () {
    // The station is seeded open — opening it is screen-142's action, and it
    // is asserted there.
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
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Periode Agustus 2026')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    $stationId = livewireStationId($period);

    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
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
