<?php

/**
 * KelolaStationTest (Feature/Livewire) — screen-030--kelola-station /
 * usecase-030--kelola-station.
 *
 * Component tests for App\Livewire\MasterData\KelolaStation, one per
 * test_scenarios' component_test step. Uses Livewire::actingAs($user)->test()
 * (mirrors tests/Feature/Livewire/KelolaBusinessUnitTest.php) — the
 * component itself requires an authenticated session (app(StationService::class)
 * calls), independent of the route-level role guard.
 *
 * The "akses ditolak" scenario deviates from Livewire::test()'s usual
 * mount-a-component-directly harness: per this component's own docblock,
 * access control for this screen is enforced entirely at the routing layer
 * ('auth' + 'role:admin' in routes/web.php, EnsureRole::forbidden() ->
 * abort(403)) — Livewire::test(KelolaStation::class) instantiates the
 * component directly and does not run route middleware, so it cannot
 * observe that guard. This scenario instead exercises the real HTTP route
 * ($this->actingAs($user, 'web')->get('/master-data/stations')) to assert
 * the actual access-denied behavior.
 *
 * Divergence from KelolaBusinessUnitTest.php: Station has no logo field at
 * all — no WithFileUploads-related tests here. `business_unit_id` and
 * `type` are bare top-level bound properties (`wire:model="business_unit_id"`
 * / `wire:model="type"`), `is_active` is a bare top-level bound boolean
 * property — every other field (name, code, description) is bound via
 * `form.<field>`. The filter dropdown property is `filterBusinessUnitId`.
 *
 * CRITICAL divergence: the cross-field rule — `is_active` may only be
 * true when `type` is not "other" — surfaces its validation error under
 * the bare `is_active` property (not `form.is_active`). See the dedicated
 * tests below; this is the most important edge case for this screen,
 * exercised here at the Livewire layer (also covered at the Service unit
 * level and the Api layer).
 *
 * FIXED IMPLEMENTATION BUG (found by test-writer-agent, fixed by the
 * orchestrator after this file was first generated):
 * App\Livewire\MasterData\KelolaStation::buildValidator() originally built
 * its $payload/$rules arrays with PLAIN keys 'name'/'code'/'description'
 * (not 'form.name'/'form.code'/'form.description'), unlike its sibling
 * App\Livewire\MasterData\KelolaBusinessUnit::buildValidator(), which
 * deliberately uses 'form.code'/'form.name' as the array keys so the
 * thrown ValidationException's error-bag keys line up with the blade
 * template's `@error('form.name')`/`@error('form.code')` directives. The
 * FIRST validation pass ($this->buildValidator()->validate(), not wrapped
 * in try/catch) used to throw with errors keyed 'name'/'code'/
 * 'description', which did NOT match kelola-station.blade.php's
 * `@error('form.name')`/`@error('form.code')`/`@error('form.description')`
 * directives, silently hiding these three fields' client-side validation
 * errors from the rendered UI (though they were present in the component's
 * error bag, just under the wrong key) — `business_unit_id`/`type`/
 * `is_active` were unaffected (their keys are already bare, matching their
 * bare properties). buildValidator() now uses 'form.name'/'form.code'/
 * 'form.description' consistently with the SECOND validation pass
 * (StationService::validate()'s ValidationException, caught in save()'s
 * catch block), which already remapped correctly. Tests below assert the
 * corrected, blade-template-matching keys — 'form.code'/'form.name'.
 */

use App\Enums\UserRole;
use App\Livewire\MasterData\KelolaStation;
use App\Models\BusinessUnit;
use App\Models\Machinery;
use App\Models\MachineryGroup;
use App\Models\Station;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create(['name' => 'Mill Unit Awal']);
    $this->admin = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->businessUnit)->create();
});

// Scenario "Kelola Station — success"
it('berhasil: picks a Business Unit, fills the form and creates a station that appears in the list', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaStation::class)
        ->call('openCreateForm')
        ->assertSet('showForm', true)
        ->set('business_unit_id', $this->businessUnit->id)
        ->set('type', 'weighbridge')
        ->set('is_active', true)
        ->set('form.name', 'Weighbridge Baru')
        ->set('form.code', 'STA-LW-001')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showForm', false)
        ->assertViewHas('stations', fn ($stations) => collect($stations)->contains(
            fn ($s) => $s['name'] === 'Weighbridge Baru'
                && $s['business_unit_name'] === 'Mill Unit Awal'
                && $s['machinery_group_count'] === 0
        ));

    expect(Station::where('name', 'Weighbridge Baru')->exists())->toBeTrue();
});

// Scenario "Kelola Station — Edit Station"
it('Edit Station: loads the existing values then updates the name/type/business unit', function () {
    $businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Unit Tujuan']);
    $station = Station::factory()->forBusinessUnit($this->businessUnit)->withCode('STA-LW-002')->create(['name' => 'Weighbridge Lama']);

    Livewire::actingAs($this->admin)
        ->test(KelolaStation::class)
        ->call('openEditForm', $station->id)
        ->assertSet('editingId', $station->id)
        ->assertSet('business_unit_id', $this->businessUnit->id)
        ->assertSet('type', 'weighbridge')
        ->assertSet('form.name', 'Weighbridge Lama')
        ->assertSet('form.code', 'STA-LW-002')
        ->set('business_unit_id', $businessUnitB->id)
        ->set('form.name', 'Weighbridge Baru')
        ->call('save')
        ->assertHasNoErrors()
        ->assertViewHas('stations', fn ($stations) => collect($stations)->contains(
            fn ($s) => $s['id'] === $station->id
                && $s['name'] === 'Weighbridge Baru'
                && $s['business_unit_name'] === 'Mill Unit Tujuan'
        ));

    expect($station->fresh()->name)->toBe('Weighbridge Baru');
    expect($station->fresh()->business_unit_id)->toBe($businessUnitB->id);
});

// Scenario "Kelola Station — Hapus Station — berhasil"
it('Hapus Station berhasil: removes the row from the list after confirmation', function () {
    $station = Station::factory()->forBusinessUnit($this->businessUnit)->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaStation::class)
        ->call('askDelete', $station->id)
        ->assertSet('confirmingDeleteId', $station->id)
        ->call('confirmDelete')
        ->assertSet('confirmingDeleteId', null)
        ->assertSet('deleteErrorMessage', null)
        ->assertViewHas('stations', fn ($stations) => ! collect($stations)->contains(
            fn ($s) => $s['id'] === $station->id
        ));

    expect(Station::find($station->id))->toBeNull();
});

// Scenario "Kelola Station — Hapus Station — ditolak (MachineryGroup)"
it('Hapus Station ditolak: shows an inline error and keeps the row when it has a related MachineryGroup', function () {
    $station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    MachineryGroup::factory()->forStation($station)->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaStation::class)
        ->call('askDelete', $station->id)
        ->call('confirmDelete')
        ->assertSet('confirmingDeleteId', null)
        ->assertSet('deleteErrorMessage', fn ($message) => ! empty($message))
        ->assertSee($station->name)
        ->assertViewHas('stations', fn ($stations) => collect($stations)->contains(
            fn ($s) => $s['id'] === $station->id
        ));

    expect(Station::find($station->id))->not->toBeNull();
});

// Additional coverage: the same 409 delete-guard fires for a related
// Machinery row too (independent of MachineryGroup).
it('Hapus Station ditolak: shows an inline error and keeps the row when it has a related Machinery', function () {
    $station = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    Machinery::factory()->create(['station_id' => $station->id]);

    Livewire::actingAs($this->admin)
        ->test(KelolaStation::class)
        ->call('askDelete', $station->id)
        ->call('confirmDelete')
        ->assertSet('confirmingDeleteId', null)
        ->assertSet('deleteErrorMessage', fn ($message) => ! empty($message));

    expect(Station::find($station->id))->not->toBeNull();
});

// "Batal" on the inline delete confirmation — no delete happens, row
// stays.
it('cancelDelete: cancels the inline confirmation without deleting the row', function () {
    $station = Station::factory()->forBusinessUnit($this->businessUnit)->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaStation::class)
        ->call('askDelete', $station->id)
        ->call('cancelDelete')
        ->assertSet('confirmingDeleteId', null);

    expect(Station::find($station->id))->not->toBeNull();
});

// Kode duplikat (create branch)
it('Kode duplikat (create): shows a validation error under form.code and does not create a row', function () {
    Station::factory()->forBusinessUnit($this->businessUnit)->withCode('STA-LW-003')->create(['name' => 'Weighbridge Alpha']);

    Livewire::actingAs($this->admin)
        ->test(KelolaStation::class)
        ->call('openCreateForm')
        ->set('business_unit_id', $this->businessUnit->id)
        ->set('type', 'weighbridge')
        ->set('is_active', true)
        ->set('form.code', 'STA-LW-003')
        ->set('form.name', 'Weighbridge Lain')
        ->call('save')
        ->assertHasErrors(['form.code'])
        ->assertSet('showForm', true);

    expect(Station::where('code', 'STA-LW-003')->count())->toBe(1);
});

// Kode duplikat (edit branch)
it('Kode duplikat (edit): shows a validation error under form.code and does not update the row', function () {
    Station::factory()->forBusinessUnit($this->businessUnit)->withCode('STA-LW-OTHER')->create();
    $target = Station::factory()->forBusinessUnit($this->businessUnit)->withCode('STA-LW-TARGET')->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaStation::class)
        ->call('openEditForm', $target->id)
        ->set('form.code', 'STA-LW-OTHER')
        ->call('save')
        ->assertHasErrors(['form.code'])
        ->assertSet('showForm', true);

    expect($target->fresh()->code)->toBe('STA-LW-TARGET');
});

// Keep-own-code-unchanged on update must succeed (no false-positive
// uniqueness violation against self).
it('updates a station keeping its own code unchanged without errors', function () {
    $station = Station::factory()->forBusinessUnit($this->businessUnit)->withCode('STA-LW-SELF')->create(['name' => 'Weighbridge Lama']);

    Livewire::actingAs($this->admin)
        ->test(KelolaStation::class)
        ->call('openEditForm', $station->id)
        ->set('form.name', 'Weighbridge Baru')
        ->call('save')
        ->assertHasNoErrors();

    expect($station->fresh()->name)->toBe('Weighbridge Baru');
    expect($station->fresh()->code)->toBe('STA-LW-SELF');
});

// CRITICAL — the cross-field rule (create branch): is_active=true with
// type=other -> validation error under is_active (bare property, not
// form.is_active), form stays open, nothing created.
it('Status/Type invalid (create): shows a validation error under is_active when is_active=true and type=other', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaStation::class)
        ->call('openCreateForm')
        ->set('business_unit_id', $this->businessUnit->id)
        ->set('type', 'other')
        ->set('is_active', true)
        ->set('form.name', 'Station Other Aktif')
        ->call('save')
        ->assertHasErrors(['is_active'])
        ->assertSet('showForm', true);

    expect(Station::where('name', 'Station Other Aktif')->exists())->toBeFalse();
});

// CRITICAL — the cross-field rule (edit branch).
it('Status/Type invalid (edit): shows a validation error under is_active when updating to is_active=true and type=other', function () {
    $station = Station::factory()->forBusinessUnit($this->businessUnit)->create(['type' => \App\Enums\StationType::Weighbridge, 'is_active' => true]);

    Livewire::actingAs($this->admin)
        ->test(KelolaStation::class)
        ->call('openEditForm', $station->id)
        ->set('type', 'other')
        ->set('is_active', true)
        ->call('save')
        ->assertHasErrors(['is_active'])
        ->assertSet('showForm', true);

    expect($station->fresh()->type->value)->toBe('weighbridge');
});

// Allows is_active=true for weighbridge/grading/cages-track, and
// is_active=false for type=other — submitted successfully via the
// component with no validation error.
it('creates a station successfully with is_active=true for non-other types', function (string $type) {
    Livewire::actingAs($this->admin)
        ->test(KelolaStation::class)
        ->call('openCreateForm')
        ->set('business_unit_id', $this->businessUnit->id)
        ->set('type', $type)
        ->set('is_active', true)
        ->set('form.name', "Station Aktif LW $type")
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showForm', false);

    expect(Station::where('name', "Station Aktif LW $type")->firstOrFail()->is_active)->toBeTrue();
})->with([
    'weighbridge' => ['weighbridge'],
    'grading' => ['grading'],
    'cages-track' => ['cages-track'],
]);

it('creates a station successfully with is_active=false for type=other', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaStation::class)
        ->call('openCreateForm')
        ->set('business_unit_id', $this->businessUnit->id)
        ->set('type', 'other')
        ->set('is_active', false)
        ->set('form.name', 'Station Other LW Nonaktif')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showForm', false);

    $fresh = Station::where('name', 'Station Other LW Nonaktif')->firstOrFail();
    expect($fresh->type->value)->toBe('other');
    expect($fresh->is_active)->toBeFalse();
});

// Scenario "Kelola Station — Business Unit induk wajib dipilih"
it('Business Unit wajib dipilih: shows a validation error under business_unit_id and does not create a row', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaStation::class)
        ->call('openCreateForm')
        ->set('type', 'weighbridge')
        ->set('is_active', true)
        ->set('form.name', 'Station Tanpa BU')
        ->call('save')
        ->assertHasErrors(['business_unit_id'])
        ->assertSet('showForm', true);

    expect(Station::where('name', 'Station Tanpa BU')->exists())->toBeFalse();
});

// Type wajib dipilih.
it('Type wajib dipilih: shows a validation error under type and does not create a row', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaStation::class)
        ->call('openCreateForm')
        ->set('business_unit_id', $this->businessUnit->id)
        ->set('is_active', true)
        ->set('form.name', 'Station Tanpa Type')
        ->call('save')
        ->assertHasErrors(['type'])
        ->assertSet('showForm', true);

    expect(Station::where('name', 'Station Tanpa Type')->exists())->toBeFalse();
});

// Nama kosong.
it('Nama kosong (create): shows a validation error under form.name and does not create a row', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaStation::class)
        ->call('openCreateForm')
        ->set('business_unit_id', $this->businessUnit->id)
        ->set('type', 'weighbridge')
        ->set('is_active', true)
        ->call('save')
        ->assertHasErrors(['form.name'])
        ->assertSet('showForm', true);

    expect(Station::count())->toBe(0);
});

// "closeForm" resets the form entirely — a subsequent openCreateForm
// starts clean.
it('closeForm: resets the form and hides it', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaStation::class)
        ->call('openCreateForm')
        ->set('form.name', 'Draft Belum Disimpan')
        ->call('closeForm')
        ->assertSet('showForm', false)
        ->assertSet('form.name', '');

    expect(Station::where('name', 'Draft Belum Disimpan')->exists())->toBeFalse();
});

// Editing a station that was deleted by someone else between opening the
// form and submitting -> friendly formErrorMessage, not a 500.
it('save (edit): shows a friendly error message when the station was deleted before saving', function () {
    $station = Station::factory()->forBusinessUnit($this->businessUnit)->create();

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaStation::class)
        ->call('openEditForm', $station->id);

    $station->delete();

    $component
        ->set('form.name', 'Weighbridge Baru')
        ->call('save')
        ->assertSet('formErrorMessage', fn ($message) => ! empty($message));
});

// Scenario "Kelola Station — Akses ditolak untuk non-Admin" (see file-level
// docblock: exercised via the real HTTP route rather than Livewire::test(),
// since access control is enforced at the routing layer.)
it('akses ditolak: returns 403 and never renders the component for a non-admin session', function (string $role) {
    $user = User::factory()->role(UserRole::from($role))->forBusinessUnit($this->businessUnit)->create();

    $response = $this->actingAs($user, 'web')->get('/master-data/stations');

    $response->assertForbidden();
    $response->assertDontSee('Kelola Station');
})->with([
    'supervisor' => ['supervisor'],
    'mill management' => ['mill_management'],
    'operator' => ['operator'],
]);

// Pagination — nextPage()/previousPage() move the page and clamp at 1.
it('nextPage/previousPage: paginates the list and clamps at page 1', function () {
    Station::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'Weighbridge A']);
    Station::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'Weighbridge B']);

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaStation::class)
        ->set('perPage', 1)
        ->assertSet('page', 1)
        ->call('nextPage')
        ->assertSet('page', 2)
        ->call('previousPage')
        ->assertSet('page', 1)
        ->call('previousPage')
        ->assertSet('page', 1);

    expect($component->get('page'))->toBe(1);
});

// Additional coverage: filtering the list by filterBusinessUnitId resets
// to page 1 and only shows that business unit's stations.
it('filters the list when filterBusinessUnitId is set, resetting to page 1', function () {
    $businessUnitB = BusinessUnit::factory()->create();
    Station::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'Weighbridge A1']);
    Station::factory()->forBusinessUnit($businessUnitB)->create(['name' => 'Weighbridge B1']);

    Livewire::actingAs($this->admin)
        ->test(KelolaStation::class)
        ->set('page', 2)
        ->set('filterBusinessUnitId', $this->businessUnit->id)
        ->assertSet('page', 1)
        ->assertViewHas('stations', fn ($stations) => collect($stations)->pluck('name')->all() === ['Weighbridge A1']);
});
