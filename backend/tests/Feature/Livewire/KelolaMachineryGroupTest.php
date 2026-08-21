<?php

/**
 * KelolaMachineryGroupTest (Feature/Livewire) — screen-033--kelola-machinery-group /
 * usecase-033--kelola-machinery-group.
 *
 * Component tests for App\Livewire\MasterData\KelolaMachineryGroup, one per
 * test_scenarios' component_test step. Uses Livewire::actingAs($user)->test()
 * (mirrors tests/Feature/Livewire/KelolaStationTest.php) — the component
 * itself requires an authenticated session (app(MachineryGroupService::class)
 * calls), independent of the route-level role guard.
 *
 * The "akses ditolak" scenario deviates from Livewire::test()'s usual
 * mount-a-component-directly harness: per this component's own docblock,
 * access control for this screen is enforced entirely at the routing layer
 * ('auth' + 'role:admin' in routes/web.php, EnsureRole::forbidden() ->
 * abort(403)) — Livewire::test(KelolaMachineryGroup::class) instantiates
 * the component directly and does not run route middleware, so it cannot
 * observe that guard. This scenario instead exercises the real HTTP route
 * ($this->actingAs($user, 'web')->get('/master-data/machinery-groups')) to
 * assert the actual access-denied behavior.
 *
 * CRITICAL divergence from KelolaStationTest.php — the structural rule
 * this screen exists to enforce: `production_line_id` is NEVER a bound
 * property/submitted payload key here. `selectedProductionLineName` is a
 * display-only derived property (see App\Livewire\MasterData\
 * KelolaMachineryGroup's own docblock) — the dedicated tests below assert
 * it updates correctly when the Station picker changes, and that it plays
 * no role in what gets persisted. `station_id` is a bare top-level bound
 * property (`wire:model.live="station_id"`) — every other field
 * (group_code, description, unit, workshop_factor, cost_per_equipment) is
 * bound via `form.<field>`. The filter dropdown property is
 * `filterStationId`.
 */

use App\Enums\UserRole;
use App\Livewire\MasterData\KelolaMachineryGroup;
use App\Models\BusinessUnit;
use App\Models\Machinery;
use App\Models\MachineryGroup;
use App\Models\Station;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create(['name' => 'Mill Unit Awal']);
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'Weighbridge Awal']);
    $this->productionLine = $this->station->productionLine()->first();
    $this->admin = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->businessUnit)->create();
});

// Scenario "Kelola Machinery Group — success"
it('berhasil: picks a Station, fills the form and creates a machinery group that appears in the list', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaMachineryGroup::class)
        ->call('openCreateForm')
        ->assertSet('showForm', true)
        ->set('station_id', $this->station->id)
        ->assertSet('selectedProductionLineName', $this->productionLine->name)
        ->set('form.group_code', 'MG-LW-001')
        ->set('form.unit', 'unit')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showForm', false)
        ->assertViewHas('machineryGroups', fn ($rows) => collect($rows)->contains(
            fn ($r) => $r['group_code'] === 'MG-LW-001'
                && $r['station_name'] === 'Weighbridge Awal'
                && $r['production_line_id'] === $this->station->production_line_id
                && $r['machinery_count'] === 0
        ));

    $fresh = MachineryGroup::where('group_code', 'MG-LW-001')->firstOrFail();
    expect($fresh->production_line_id)->toBe($this->station->production_line_id);
});

// CRITICAL — selectedProductionLineName is display-only: even if it were
// somehow desynced/stale, the persisted production_line_id is still
// always the real Station's production_line_id, never trusted from
// client state.
it('selectedProductionLineName is purely cosmetic and never affects the persisted production_line_id', function () {
    $otherProductionLine = \App\Models\ProductionLine::factory()->create(['name' => 'Line Lain']);

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaMachineryGroup::class)
        ->call('openCreateForm')
        ->set('station_id', $this->station->id)
        ->assertSet('selectedProductionLineName', $this->productionLine->name);

    // Force the display-only property out of sync with reality — this
    // must have zero effect on what gets persisted, since save() never
    // reads it.
    $component->set('selectedProductionLineName', $otherProductionLine->name);

    $component
        ->set('form.group_code', 'MG-LW-COSMETIC')
        ->call('save')
        ->assertHasNoErrors();

    $fresh = MachineryGroup::where('group_code', 'MG-LW-COSMETIC')->firstOrFail();
    expect($fresh->production_line_id)->toBe($this->station->production_line_id);
    expect($fresh->production_line_id)->not->toBe($otherProductionLine->id);
});

// updatedStationId(): clearing the Station selection clears the
// display-only production line name back to null.
it('clears selectedProductionLineName when the station selection is cleared', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaMachineryGroup::class)
        ->call('openCreateForm')
        ->set('station_id', $this->station->id)
        ->assertSet('selectedProductionLineName', $this->productionLine->name)
        ->set('station_id', '')
        ->assertSet('selectedProductionLineName', null);
});

// Scenario "Kelola Machinery Group — Edit Machinery Group"
it('Edit Machinery Group: loads the existing values (including the derived Production Line name) then updates the group_code/station', function () {
    $stationB = Station::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'Weighbridge Tujuan']);
    $machineryGroup = MachineryGroup::factory()->forStation($this->station)->withGroupCode('MG-LW-002')->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaMachineryGroup::class)
        ->call('openEditForm', $machineryGroup->id)
        ->assertSet('editingId', $machineryGroup->id)
        ->assertSet('station_id', $this->station->id)
        ->assertSet('selectedProductionLineName', $this->productionLine->name)
        ->assertSet('form.group_code', 'MG-LW-002')
        ->set('station_id', $stationB->id)
        ->set('form.group_code', 'MG-LW-003')
        ->call('save')
        ->assertHasNoErrors()
        ->assertViewHas('machineryGroups', fn ($rows) => collect($rows)->contains(
            fn ($r) => $r['id'] === $machineryGroup->id
                && $r['group_code'] === 'MG-LW-003'
                && $r['station_name'] === 'Weighbridge Tujuan'
        ));

    expect($machineryGroup->fresh()->group_code)->toBe('MG-LW-003');
    expect($machineryGroup->fresh()->station_id)->toBe($stationB->id);
});

// Scenario "Kelola Machinery Group — Hapus — berhasil"
it('Hapus berhasil: removes the row from the list after confirmation', function () {
    $machineryGroup = MachineryGroup::factory()->forStation($this->station)->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaMachineryGroup::class)
        ->call('askDelete', $machineryGroup->id)
        ->assertSet('confirmingDeleteId', $machineryGroup->id)
        ->call('confirmDelete')
        ->assertSet('confirmingDeleteId', null)
        ->assertSet('deleteErrorMessage', null)
        ->assertViewHas('machineryGroups', fn ($rows) => ! collect($rows)->contains(
            fn ($r) => $r['id'] === $machineryGroup->id
        ));

    expect(MachineryGroup::find($machineryGroup->id))->toBeNull();
});

// Scenario "Kelola Machinery Group — Hapus — ditolak (Machinery)"
it('Hapus ditolak: shows an inline error and keeps the row when it has related Machinery', function () {
    $machineryGroup = MachineryGroup::factory()->forStation($this->station)->create();
    Machinery::factory()->forMachineryGroup($machineryGroup)->create(['station_id' => $this->station->id]);

    Livewire::actingAs($this->admin)
        ->test(KelolaMachineryGroup::class)
        ->call('askDelete', $machineryGroup->id)
        ->call('confirmDelete')
        ->assertSet('confirmingDeleteId', null)
        ->assertSet('deleteErrorMessage', fn ($message) => ! empty($message))
        ->assertViewHas('machineryGroups', fn ($rows) => collect($rows)->contains(
            fn ($r) => $r['id'] === $machineryGroup->id
        ));

    expect(MachineryGroup::find($machineryGroup->id))->not->toBeNull();
});

// "Batal" on the inline delete confirmation — no delete happens, row
// stays.
it('cancelDelete: cancels the inline confirmation without deleting the row', function () {
    $machineryGroup = MachineryGroup::factory()->forStation($this->station)->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaMachineryGroup::class)
        ->call('askDelete', $machineryGroup->id)
        ->call('cancelDelete')
        ->assertSet('confirmingDeleteId', null);

    expect(MachineryGroup::find($machineryGroup->id))->not->toBeNull();
});

// Kode duplikat (create branch)
it('Kode duplikat (create): shows a validation error under form.group_code and does not create a row', function () {
    MachineryGroup::factory()->forStation($this->station)->withGroupCode('MG-LW-004')->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaMachineryGroup::class)
        ->call('openCreateForm')
        ->set('station_id', $this->station->id)
        ->set('form.group_code', 'MG-LW-004')
        ->call('save')
        ->assertHasErrors(['form.group_code'])
        ->assertSet('showForm', true);

    expect(MachineryGroup::where('group_code', 'MG-LW-004')->count())->toBe(1);
});

// Kode duplikat (edit branch)
it('Kode duplikat (edit): shows a validation error under form.group_code and does not update the row', function () {
    MachineryGroup::factory()->forStation($this->station)->withGroupCode('MG-LW-OTHER')->create();
    $target = MachineryGroup::factory()->forStation($this->station)->withGroupCode('MG-LW-TARGET')->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaMachineryGroup::class)
        ->call('openEditForm', $target->id)
        ->set('form.group_code', 'MG-LW-OTHER')
        ->call('save')
        ->assertHasErrors(['form.group_code'])
        ->assertSet('showForm', true);

    expect($target->fresh()->group_code)->toBe('MG-LW-TARGET');
});

// Keep-own-code-unchanged on update must succeed (no false-positive
// uniqueness violation against self).
it('updates a machinery group keeping its own group_code unchanged without errors', function () {
    $machineryGroup = MachineryGroup::factory()->forStation($this->station)->withGroupCode('MG-LW-SELF')->create(['description' => 'Lama']);

    Livewire::actingAs($this->admin)
        ->test(KelolaMachineryGroup::class)
        ->call('openEditForm', $machineryGroup->id)
        ->set('form.description', 'Baru')
        ->call('save')
        ->assertHasNoErrors();

    expect($machineryGroup->fresh()->description)->toBe('Baru');
    expect($machineryGroup->fresh()->group_code)->toBe('MG-LW-SELF');
});

// Station wajib dipilih.
it('Station wajib dipilih: shows a validation error under station_id and does not create a row', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaMachineryGroup::class)
        ->call('openCreateForm')
        ->set('form.group_code', 'MG-LW-NOSTATION')
        ->call('save')
        ->assertHasErrors(['station_id'])
        ->assertSet('showForm', true);

    expect(MachineryGroup::where('group_code', 'MG-LW-NOSTATION')->exists())->toBeFalse();
});

// Kode kosong.
it('Kode kosong (create): shows a validation error under form.group_code and does not create a row', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaMachineryGroup::class)
        ->call('openCreateForm')
        ->set('station_id', $this->station->id)
        ->call('save')
        ->assertHasErrors(['form.group_code'])
        ->assertSet('showForm', true);

    expect(MachineryGroup::count())->toBe(0);
});

// workshop_factor non-numeric.
it('Workshop Factor bukan angka: shows a validation error under form.workshop_factor', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaMachineryGroup::class)
        ->call('openCreateForm')
        ->set('station_id', $this->station->id)
        ->set('form.group_code', 'MG-LW-BADWF')
        ->set('form.workshop_factor', 'bukan-angka')
        ->call('save')
        ->assertHasErrors(['form.workshop_factor'])
        ->assertSet('showForm', true);

    expect(MachineryGroup::where('group_code', 'MG-LW-BADWF')->exists())->toBeFalse();
});

// "closeForm" resets the form entirely — a subsequent openCreateForm
// starts clean.
it('closeForm: resets the form and hides it', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaMachineryGroup::class)
        ->call('openCreateForm')
        ->set('station_id', $this->station->id)
        ->set('form.group_code', 'MG-LW-DRAFT')
        ->call('closeForm')
        ->assertSet('showForm', false)
        ->assertSet('form.group_code', '')
        ->assertSet('station_id', '')
        ->assertSet('selectedProductionLineName', null);

    expect(MachineryGroup::where('group_code', 'MG-LW-DRAFT')->exists())->toBeFalse();
});

// Editing a machinery group that was deleted by someone else between
// opening the form and submitting -> friendly formErrorMessage, not a
// 500.
it('save (edit): shows a friendly error message when the machinery group was deleted before saving', function () {
    $machineryGroup = MachineryGroup::factory()->forStation($this->station)->create();

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaMachineryGroup::class)
        ->call('openEditForm', $machineryGroup->id);

    $machineryGroup->delete();

    $component
        ->set('form.group_code', 'MG-LW-DIHAPUS')
        ->call('save')
        ->assertSet('formErrorMessage', fn ($message) => ! empty($message));
});

// Scenario "Kelola Machinery Group — Akses ditolak untuk non-Admin" (see
// file-level docblock: exercised via the real HTTP route rather than
// Livewire::test(), since access control is enforced at the routing
// layer.)
it('akses ditolak: returns 403 and never renders the component for a non-admin session', function (string $role) {
    $user = User::factory()->role(UserRole::from($role))->forBusinessUnit($this->businessUnit)->create();

    $response = $this->actingAs($user, 'web')->get('/master-data/machinery-groups');

    $response->assertForbidden();
    $response->assertDontSee('Kelola Machinery Group');
})->with([
    'supervisor' => ['supervisor'],
    'mill management' => ['mill_management'],
    'operator' => ['operator'],
]);

// Pagination — nextPage()/previousPage() move the page and clamp at 1.
it('nextPage/previousPage: paginates the list and clamps at page 1', function () {
    MachineryGroup::factory()->forStation($this->station)->withGroupCode('MG-LW-PA')->create();
    MachineryGroup::factory()->forStation($this->station)->withGroupCode('MG-LW-PB')->create();

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaMachineryGroup::class)
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

// Additional coverage: filtering the list by filterStationId resets to
// page 1 and only shows that station's machinery groups.
it('filters the list when filterStationId is set, resetting to page 1', function () {
    $stationB = Station::factory()->forBusinessUnit($this->businessUnit)->create();
    MachineryGroup::factory()->forStation($this->station)->withGroupCode('MG-LW-A1')->create();
    MachineryGroup::factory()->forStation($stationB)->withGroupCode('MG-LW-B1')->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaMachineryGroup::class)
        ->set('page', 2)
        ->set('filterStationId', $this->station->id)
        ->assertSet('page', 1)
        ->assertViewHas('machineryGroups', fn ($rows) => collect($rows)->pluck('group_code')->all() === ['MG-LW-A1']);
});
