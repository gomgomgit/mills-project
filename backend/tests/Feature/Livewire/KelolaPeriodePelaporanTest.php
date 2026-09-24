<?php

/**
 * KelolaPeriodePelaporanTest (Feature/Livewire) —
 * screen-128--kelola-periode-pelaporan / usecase-128 (CRUD) +
 * usecase-140 (tutup & buka kembali) + usecase-144 (buka periode draft).
 *
 * Component tests for App\Livewire\MasterData\KelolaPeriodePelaporan, one
 * per test_scenarios entry whose `component_test` is non-empty (scenarios
 * 1–13, 16–18 and 21–28; scenarios 14, 15, 19 and 20 carry an empty
 * component_test — they are pure API/mobile-sync scenarios and live,
 * skipped, in tests/Feature/Api/KelolaPeriodePelaporanTest.php), plus one
 * extra test for the cancelOpen() path, which the business spec lists as
 * an alternative flow without a bdd_scenario of its own. Mirrors
 * tests/Feature/Livewire/KelolaProductionLineTest.php's structure.
 *
 * BINDING SHAPE: `business_unit_id` and `station_type` are bare top-level
 * properties (an unselected "Jenis Stasiun" binds to '' and means the NULL
 * "all station types" scope); `name`, `start_date` and `end_date` are
 * bound as `form.<field>`. Filters are `filterBusinessUnitId` /
 * `filterStatus`.
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

// Scenario 1: "Kelola Periode Pelaporan — success"
it('berhasil: mengisi form tambah periode lalu barisnya muncul di tabel dengan status draft', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('openCreateForm')
        ->assertSet('showForm', true)
        ->set('business_unit_id', $this->businessUnitA->id)
        ->set('station_type', 'sterilizer')
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
                && $row['status'] === 'draft'
                && $row['business_unit_name'] === 'Mill Alpha'
                && $row['station_type_label'] === 'Sterilizer'
                && $row['closed_by_name'] === null
        ));

    $stored = Period::where('name', 'Oktober 2026')->firstOrFail();
    expect($stored->status->value)->toBe('draft');
    expect($stored->business_unit_id)->toBe($this->businessUnitA->id);
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
        ->set('station_type', 'sterilizer')
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
        ->set('station_type', '')
        ->set('form.name', 'Periode Terbalik')
        ->set('form.start_date', '2026-10-31')
        ->set('form.end_date', '2026-10-01')
        ->call('save')
        ->assertHasErrors(['form.end_date'])
        ->assertSet('showForm', true);

    expect(Period::count())->toBe(0);
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
        ->set('station_type', 'sterilizer')
        ->set('form.name', 'Oktober 2026')
        ->set('form.start_date', '2026-12-01')
        ->set('form.end_date', '2026-12-31')
        ->call('save')
        ->assertHasErrors(['form.name'])
        ->assertSet('showForm', true)
        ->assertViewHas('periods', fn ($rows) => count($rows) === 1);

    expect(Period::count())->toBe(1);
});

// Scenario 5: "Mengubah atau menghapus periode yang sudah tertutup"
it('periode tertutup: edit dan hapus ditolak dengan pesan yang mengarahkan membuka kembali periode', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->closed($this->adminA, '2026-11-01 09:14:00')
        ->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        // Edit is refused before the form even opens.
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
    expect($fresh->status->value)->toBe('closed');
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
        ->set('station_type', 'sterilizer')
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

// Scenario 10: "Tutup & Buka Kembali Periode Pelaporan — success"
it('tutup periode: dialog memuat jumlah belum terverifikasi, konfirmasi mengubah badge menjadi Tertutup', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-10-10')->count(2)->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('askClose', $period->id)
        ->assertSet('closingId', $period->id)
        ->assertSet('closingUnverifiedCount', 2)
        ->assertSee('data stasiun belum terverifikasi')
        ->call('confirmClose')
        ->assertSet('closingId', null)
        ->assertSet('closeErrorMessage', null)
        ->assertSet('successMessage', 'Periode berhasil ditutup.')
        ->assertViewHas('periods', fn ($rows) => collect($rows)->contains(
            fn ($row) => $row['id'] === $period->id
                && $row['status'] === 'closed'
                && $row['closed_by_name'] === 'Admin X'
                && $row['closed_at'] !== null
        ))
        // A closed row offers reopen only.
        ->assertSeeHtml("reopen-button-{$period->id}")
        ->assertDontSeeHtml("close-button-{$period->id}");

    expect($period->fresh()->status->value)->toBe('closed');
});

// Scenario 11: "buka kembali periode yang sudah tertutup"
it('buka kembali periode: badge jadi Terbuka dan kolom penutupan kosong kembali', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->closed($this->adminA, '2026-11-01 09:14:00')
        ->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('askReopen', $period->id)
        ->assertSet('confirmingReopenId', $period->id)
        ->call('confirmReopen')
        ->assertSet('confirmingReopenId', null)
        ->assertSet('successMessage', 'Periode berhasil dibuka kembali.')
        ->assertViewHas('periods', fn ($rows) => collect($rows)->contains(
            fn ($row) => $row['id'] === $period->id
                && $row['status'] === 'open'
                && $row['closed_by'] === null
                && $row['closed_at'] === null
        ))
        // Edit, Hapus and Tutup Periode are available again.
        ->assertSeeHtml("edit-button-{$period->id}")
        ->assertSeeHtml("close-button-{$period->id}")
        ->assertSeeHtml("delete-button-{$period->id}");

    $fresh = $period->fresh();
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

    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('askClose', $period->id)
        ->assertSet('closingId', $period->id)
        ->call('cancelClose')
        ->assertSet('closingId', null)
        ->assertSet('closingPeriod', null)
        ->assertSet('closingUnverifiedCount', 0)
        ->assertSet('successMessage', null)
        ->assertViewHas('periods', fn ($rows) => collect($rows)->contains(
            fn ($row) => $row['id'] === $period->id
                && $row['status'] === 'open'
                && $row['closed_by'] === null
        ));

    expect($period->fresh()->status->value)->toBe('open');
});

// Scenario 13: "masih banyak data belum terverifikasi"
it('dialog tutup: menampilkan jumlah dan rincian per jenis stasiun, tombol konfirmasi tetap aktif', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(null)
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    $threshingStation = Station::factory()->forBusinessUnit($this->businessUnitA)->threshing()->create();

    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-10-10')->count(5)->create();
    \App\Models\ThreshingRecord::factory()->forStation($threshingStation)->onDate('2026-10-11')->count(2)->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('askClose', $period->id)
        ->assertSet('closingUnverifiedCount', 7)
        ->assertSet('closingBreakdown', fn ($breakdown) => collect($breakdown)->pluck('count', 'station_type')->all() === [
            'sterilizer' => 5,
            'threshing' => 2,
        ])
        ->assertSee('data stasiun belum terverifikasi')
        ->assertSee('verifikasi ikut terkunci')
        // Per-station-type breakdown labels.
        ->assertSee('Sterilizer')
        ->assertSee('Threshing')
        // The confirm button is rendered and never disabled by the figure.
        ->assertSeeHtml('data-testid="confirm-close-button"')
        // And it really does work.
        ->call('confirmClose')
        ->assertSet('successMessage', 'Periode berhasil ditutup.');
});

// Scenario 16: "menutup periode yang sudah tertutup"
it('baris tertutup: hanya aksi Buka Kembali Periode yang ter-render', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->closed($this->adminA, '2026-11-01 09:14:00')
        ->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->assertSee('Tertutup')
        ->assertSeeHtml("reopen-button-{$period->id}")
        ->assertDontSeeHtml("close-button-{$period->id}")
        ->assertDontSeeHtml("edit-button-{$period->id}")
        ->assertDontSeeHtml("delete-button-{$period->id}");
});

// Scenario 17: "dua Admin menutup periode bersamaan"
it('dua Admin menutup bersamaan: Admin kedua diberi tahu dan catatan penutup pertama tetap', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    // Admin B still sees the period as open and opens the dialog.
    $component = Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('askClose', $period->id)
        ->assertSet('closingId', $period->id);

    // Admin A closes it first, straight in the database.
    $closedAt = now()->subMinute();
    Period::whereKey($period->id)->update([
        'status' => 'closed',
        'closed_by' => $this->adminA->id,
        'closed_at' => $closedAt,
    ]);

    $component
        ->call('confirmClose')
        ->assertStatus(200)
        ->assertSet('successMessage', null)
        ->assertSet('closeErrorMessage', fn ($m) => is_string($m) && str_contains($m, 'Admin A'))
        ->assertViewHas('periods', fn ($rows) => collect($rows)->contains(
            fn ($row) => $row['id'] === $period->id
                && $row['status'] === 'closed'
                && $row['closed_by_name'] === 'Admin A'
        ));

    // Admin X never overwrote Admin A's record.
    expect($period->fresh()->closed_by)->toBe($this->adminA->id);
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

    // The screen itself is unreachable, so neither action button is ever
    // rendered for this actor.
    $this->actingAs($user, 'web')->get('/master-data/periods')->assertForbidden();

    // And the API actions behind those buttons are refused too.
    $this->actingAs($user, 'web')->postJson("/api/periods/{$openPeriod->id}/close")->assertStatus(403);
    $this->actingAs($user, 'web')->postJson("/api/periods/{$closedPeriod->id}/reopen")->assertStatus(403);

    expect($openPeriod->fresh()->status->value)->toBe('open');
    expect($closedPeriod->fresh()->closed_by)->toBe($this->adminA->id);
})->with([
    'supervisor' => ['supervisor'],
    'mill management' => ['mill_management'],
    'operator' => ['operator'],
]);

// ── usecase-144 (Buka Periode) ──────────────────────────────────────────
//
// Scenarios 21–28 plus the cancel path. "Buka Periode" is offered on DRAFT
// rows only, via a real dialog (askOpen -> openingId/openingPeriod ->
// confirmOpen), because draft -> open cannot be undone. Errors surface on
// the EXISTING $closeErrorMessage property (the [data-testid="close-error"]
// alert), not on a new one; success sets $successMessage.

/** A draft period on Mill Alpha — the only status that offers "Buka Periode". */
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
it('buka periode: dialog konfirmasi tampil, setelah konfirmasi badge jadi Terbuka dan tombol Buka Periode hilang', function () {
    $period = draftPeriodForOpenComponent($this->businessUnitA);

    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-10-10')->count(2)->create();
    $recordsBefore = SterilizerRecord::query()->orderBy('id')->get()
        ->map(fn ($r) => [$r->id, $r->checked_by, $r->acknowledged_by, (string) $r->updated_at])
        ->all();

    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        // A draft row offers the action.
        ->assertSeeHtml('data-testid="open-period-button"')
        ->call('askOpen', $period->id)
        ->assertSet('openingId', $period->id)
        ->assertSet('openingPeriod', fn ($snapshot) => is_array($snapshot) && $snapshot['id'] === $period->id)
        // The dialog spells out that the step cannot be undone.
        ->assertSeeHtml('data-testid="open-period-dialog"')
        ->assertSeeHtml('data-testid="confirm-open-period"')
        ->assertSee('tidak dapat dikembalikan ke Draft')
        ->call('confirmOpen')
        ->assertSet('openingId', null)
        ->assertSet('openingPeriod', null)
        ->assertSet('closeErrorMessage', null)
        ->assertSet('successMessage', 'Periode berhasil dibuka.')
        ->assertViewHas('periods', fn ($rows) => collect($rows)->contains(
            fn ($row) => $row['id'] === $period->id
                && $row['status'] === 'open'
                && $row['closed_by'] === null
        ))
        ->assertSee('Terbuka')
        // The action is no longer offered for that row, while Edit, Tutup
        // and Hapus stay available.
        ->assertDontSeeHtml('data-testid="open-period-button"')
        ->assertSeeHtml("edit-button-{$period->id}")
        ->assertSeeHtml("close-button-{$period->id}")
        ->assertSeeHtml("delete-button-{$period->id}");

    expect($period->fresh()->status->value)->toBe('open');

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

    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('askOpen', $period->id)
        ->assertSet('openingId', $period->id)
        ->assertSeeHtml('data-testid="open-period-dialog"')
        ->call('cancelOpen')
        ->assertSet('openingId', null)
        ->assertSet('openingPeriod', null)
        ->assertSet('closeErrorMessage', null)
        ->assertSet('successMessage', null)
        ->assertDontSeeHtml('data-testid="open-period-dialog"')
        ->assertViewHas('periods', fn ($rows) => collect($rows)->contains(
            fn ($row) => $row['id'] === $period->id && $row['status'] === 'draft'
        ))
        // The row still offers the action, untouched.
        ->assertSeeHtml('data-testid="open-period-button"');

    expect($period->fresh()->status->value)->toBe('draft');
});

// Scenario 22: "Periode sudah terbuka"
it('buka periode yang sudah terbuka: pesan menyebut sudah terbuka dan status tidak berubah', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        // An open row never renders the button — this is the forged/stale
        // call the component still has to survive.
        ->assertDontSeeHtml('data-testid="open-period-button"')
        ->call('askOpen', $period->id)
        ->call('confirmOpen')
        ->assertStatus(200)
        ->assertSet('successMessage', null)
        ->assertSet('openingId', null)
        ->assertSet('closeErrorMessage', fn ($m) => is_string($m)
            && str_contains($m, 'sudah terbuka')
            && ! str_contains($m, 'Buka Kembali Periode'))
        ->assertViewHas('periods', fn ($rows) => collect($rows)->contains(
            fn ($row) => $row['id'] === $period->id && $row['status'] === 'open'
        ));

    expect($period->fresh()->status->value)->toBe('open');
});

// Scenario 23: "Periode sudah tertutup"
it('buka periode yang sudah tertutup: pesan mengarahkan ke Buka Kembali Periode dan status tetap Tertutup', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->closed($this->adminA, '2026-11-01 09:14:00')
        ->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('askOpen', $period->id)
        ->call('confirmOpen')
        ->assertStatus(200)
        ->assertSet('successMessage', null)
        ->assertSet('closeErrorMessage', fn ($m) => is_string($m)
            && str_contains($m, 'sudah tertutup')
            && str_contains($m, 'Buka Kembali Periode'))
        ->assertViewHas('periods', fn ($rows) => collect($rows)->contains(
            fn ($row) => $row['id'] === $period->id && $row['status'] === 'closed'
        ))
        // A closed row offers "Buka Kembali Periode", never "Buka Periode".
        ->assertSeeHtml("reopen-button-{$period->id}")
        ->assertDontSeeHtml('data-testid="open-period-button"');

    expect($period->fresh()->status->value)->toBe('closed');
});

// Scenario 24: "Periode tidak ditemukan"
it('buka periode yang sudah dihapus: pesan tidak ditemukan, komponen tetap hidup, baris hilang dari daftar', function () {
    $period = draftPeriodForOpenComponent($this->businessUnitA, 'Periode Sementara');

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('askOpen', $period->id)
        ->assertSet('openingId', $period->id);

    // Another Admin deletes the row while the dialog is open.
    Period::whereKey($period->id)->delete();

    $component
        ->call('confirmOpen')
        ->assertStatus(200)
        ->assertSet('openingId', null)
        ->assertSet('openingPeriod', null)
        ->assertSet('successMessage', null)
        ->assertSet('closeErrorMessage', fn ($m) => is_string($m) && str_contains($m, 'tidak ditemukan'))
        ->assertViewHas('periods', fn ($rows) => ! collect($rows)->contains(
            fn ($row) => $row['id'] === $period->id
        ));
});

// Scenario 25: "Dua Admin membuka bersamaan"
it('dua Admin membuka bersamaan: hanya yang pertama berhasil, yang kedua diberi tahu sudah terbuka', function () {
    $period = draftPeriodForOpenComponent($this->businessUnitA);

    // Both Admins render the list while the period is still draft.
    $adminB = Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('askOpen', $period->id)
        ->assertSet('openingId', $period->id);

    // Admin A gets there first.
    Livewire::actingAs($this->adminA)
        ->test(KelolaPeriodePelaporan::class)
        ->call('askOpen', $period->id)
        ->call('confirmOpen')
        ->assertSet('successMessage', 'Periode berhasil dibuka.')
        ->assertSet('closeErrorMessage', null);

    expect($period->fresh()->updated_by)->toBe($this->adminA->id);

    // Admin B confirms the same opening a moment later.
    $adminB
        ->call('confirmOpen')
        ->assertStatus(200)
        ->assertSet('successMessage', null)
        ->assertSet('closeErrorMessage', fn ($m) => is_string($m) && str_contains($m, 'sudah terbuka'))
        ->assertViewHas('periods', fn ($rows) => collect($rows)->contains(
            fn ($row) => $row['id'] === $period->id && $row['status'] === 'open'
        ));

    // Admin B never overwrote Admin A's stamp.
    $fresh = $period->fresh();
    expect($fresh->status->value)->toBe('open');
    expect($fresh->updated_by)->toBe($this->adminA->id);
});

// Scenario 26: "Bukan Admin mencoba membuka periode"
it('akses ditolak: non-Admin tidak dapat memicu aksi Buka Periode maupun melihat tombolnya', function (string $role) {
    $user = match ($role) {
        'supervisor' => $this->supervisor,
        'mill_management' => $this->millManagement,
        'operator' => $this->operator,
    };

    $period = draftPeriodForOpenComponent($this->businessUnitA, 'Periode Draft');

    // The screen itself is unreachable (EnsureRole aborts 403 before mount),
    // so the button is never rendered for this actor.
    $response = $this->actingAs($user, 'web')->get('/master-data/periods');
    $response->assertForbidden();
    $response->assertDontSee('open-period-button', false);

    // And the action behind it is refused too.
    $this->actingAs($user, 'web')
        ->postJson("/api/periods/{$period->id}/open")
        ->assertStatus(403);

    $fresh = $period->fresh();
    expect($fresh->status->value)->toBe('draft');
    expect($fresh->updated_by)->toBeNull();
})->with([
    'supervisor' => ['supervisor'],
    'mill management' => ['mill_management'],
    'operator' => ['operator'],
]);

// Scenario 27: "status Terbuka tidak dapat dikembalikan ke Draft"
it('periode Terbuka: form edit tidak menyediakan pilihan status dan status tetap Terbuka setelah simpan', function () {
    $period = draftPeriodForOpenComponent($this->businessUnitA);

    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('askOpen', $period->id)
        ->call('confirmOpen')
        ->assertSet('successMessage', 'Periode berhasil dibuka.')
        ->call('openEditForm', $period->id)
        ->assertSet('showForm', true)
        // The form binds business unit, station type, name and the two
        // dates — and nothing else. There is no status control at all, so
        // no component action can produce 'draft' from an open period.
        ->assertDontSeeHtml('wire:model.live="form.status"')
        ->assertDontSeeHtml('wire:model="form.status"')
        ->assertDontSeeHtml('data-testid="status-select"')
        ->set('form.name', 'Oktober 2026 (revisi)')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('successMessage', 'Periode berhasil diperbarui.')
        ->assertViewHas('periods', fn ($rows) => collect($rows)->contains(
            fn ($row) => $row['id'] === $period->id
                && $row['status'] === 'open'
                && $row['name'] === 'Oktober 2026 (revisi)'
        ));

    expect($period->fresh()->status->value)->toBe('open');
});

// Scenario 28: "periode Terbuka tetap dapat diubah dan dihapus"
it('periode Terbuka tetap dapat diubah dan dihapus seperti Draft', function () {
    $period = draftPeriodForOpenComponent($this->businessUnitA, 'Periode Agustus 2026');

    Livewire::actingAs($this->admin)
        ->test(KelolaPeriodePelaporan::class)
        ->call('askOpen', $period->id)
        ->call('confirmOpen')
        ->assertSet('successMessage', 'Periode berhasil dibuka.')
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
});
