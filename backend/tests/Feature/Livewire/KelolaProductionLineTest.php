<?php

/**
 * KelolaProductionLineTest (Feature/Livewire) — screen-036--kelola-production-line /
 * usecase-036--kelola-production-line.
 *
 * Component tests for App\Livewire\MasterData\KelolaProductionLine, one per
 * scenario. Uses Livewire::actingAs($user)->test() — the component itself
 * requires an authenticated session (app(ProductionLineService::class)
 * calls), independent of the route-level role guard. Mirrors
 * tests/Feature/Livewire/KelolaMachineryGroupTest.php / KelolaBusinessUnitTest.php's
 * structure.
 *
 * `business_unit_id` is a bare top-level bound property
 * (`wire:model="business_unit_id"`) — every other field (name, code,
 * description) is bound via `form.<field>`. The filter dropdown property
 * is `filterBusinessUnitId`. create() auto-provisions 15 stations — the
 * "berhasil" test asserts station_count === 15 in the resulting list row.
 */

use App\Enums\UserRole;
use App\Livewire\MasterData\KelolaProductionLine;
use App\Models\BusinessUnit;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create(['name' => 'Mill Unit Awal']);
    $this->admin = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->businessUnit)->create();
});

// Scenario "Kelola Production Line — success"
it('berhasil: picks a Business Unit, fills the form and creates a production line that appears in the list with 15 stations', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaProductionLine::class)
        ->call('openCreateForm')
        ->assertSet('showForm', true)
        ->set('business_unit_id', $this->businessUnit->id)
        ->set('form.name', 'Line LW 01')
        ->set('form.code', 'PL-LW-001')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showForm', false)
        ->assertViewHas('productionLines', fn ($rows) => collect($rows)->contains(
            fn ($r) => $r['code'] === 'PL-LW-001'
                && $r['business_unit_name'] === $this->businessUnit->name
                && $r['station_count'] === 15
        ));

    $fresh = ProductionLine::where('code', 'PL-LW-001')->firstOrFail();
    expect($fresh->business_unit_id)->toBe($this->businessUnit->id);
    expect(Station::where('production_line_id', $fresh->id)->count())->toBe(15);
});

// Scenario "Kelola Production Line — Edit Production Line"
it('Edit Production Line: loads the existing values then updates name/code without touching existing stations', function () {
    $productionLine = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->withCode('PL-LW-002')->create();
    Station::factory()->forProductionLine($productionLine)->count(15)->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaProductionLine::class)
        ->call('openEditForm', $productionLine->id)
        ->assertSet('editingId', $productionLine->id)
        ->assertSet('business_unit_id', $this->businessUnit->id)
        ->assertSet('form.code', 'PL-LW-002')
        ->set('form.name', 'Line LW Baru')
        ->set('form.code', 'PL-LW-003')
        ->call('save')
        ->assertHasNoErrors()
        ->assertViewHas('productionLines', fn ($rows) => collect($rows)->contains(
            fn ($r) => $r['id'] === $productionLine->id
                && $r['code'] === 'PL-LW-003'
                && $r['name'] === 'Line LW Baru'
                && $r['station_count'] === 15
        ));

    expect($productionLine->fresh()->code)->toBe('PL-LW-003');
    expect(Station::where('production_line_id', $productionLine->id)->count())->toBe(15);
});

// Scenario "Kelola Production Line — Hapus — berhasil"
it('Hapus berhasil: removes the row from the list after confirmation', function () {
    $productionLine = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaProductionLine::class)
        ->call('askDelete', $productionLine->id)
        ->assertSet('confirmingDeleteId', $productionLine->id)
        ->call('confirmDelete')
        ->assertSet('confirmingDeleteId', null)
        ->assertSet('deleteErrorMessage', null)
        ->assertViewHas('productionLines', fn ($rows) => ! collect($rows)->contains(
            fn ($r) => $r['id'] === $productionLine->id
        ));

    expect(ProductionLine::find($productionLine->id))->toBeNull();
});

// Scenario "Kelola Production Line — Hapus — ditolak (Station)"
it('Hapus ditolak: shows an inline error and keeps the row when it has related Station', function () {
    $productionLine = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create();
    Station::factory()->forProductionLine($productionLine)->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaProductionLine::class)
        ->call('askDelete', $productionLine->id)
        ->call('confirmDelete')
        ->assertSet('confirmingDeleteId', null)
        ->assertSet('deleteErrorMessage', fn ($message) => ! empty($message))
        ->assertViewHas('productionLines', fn ($rows) => collect($rows)->contains(
            fn ($r) => $r['id'] === $productionLine->id
        ));

    expect(ProductionLine::find($productionLine->id))->not->toBeNull();
});

// "Batal" on the inline delete confirmation — no delete happens, row stays.
it('cancelDelete: cancels the inline confirmation without deleting the row', function () {
    $productionLine = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaProductionLine::class)
        ->call('askDelete', $productionLine->id)
        ->call('cancelDelete')
        ->assertSet('confirmingDeleteId', null);

    expect(ProductionLine::find($productionLine->id))->not->toBeNull();
});

// Kode duplikat (create branch)
it('Kode duplikat (create): shows a validation error under form.code and does not create a row', function () {
    ProductionLine::factory()->forBusinessUnit($this->businessUnit)->withCode('PL-LW-004')->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaProductionLine::class)
        ->call('openCreateForm')
        ->set('business_unit_id', $this->businessUnit->id)
        ->set('form.name', 'Line Dup')
        ->set('form.code', 'PL-LW-004')
        ->call('save')
        ->assertHasErrors(['form.code'])
        ->assertSet('showForm', true);

    expect(ProductionLine::where('code', 'PL-LW-004')->count())->toBe(1);
});

// Kode duplikat (edit branch)
it('Kode duplikat (edit): shows a validation error under form.code and does not update the row', function () {
    ProductionLine::factory()->forBusinessUnit($this->businessUnit)->withCode('PL-LW-OTHER')->create();
    $target = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->withCode('PL-LW-TARGET')->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaProductionLine::class)
        ->call('openEditForm', $target->id)
        ->set('form.code', 'PL-LW-OTHER')
        ->call('save')
        ->assertHasErrors(['form.code'])
        ->assertSet('showForm', true);

    expect($target->fresh()->code)->toBe('PL-LW-TARGET');
});

// Keep-own-code-unchanged on update must succeed (no false-positive
// uniqueness violation against self).
it('updates a production line keeping its own code unchanged without errors', function () {
    $productionLine = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->withCode('PL-LW-SELF')->create(['description' => 'Lama']);

    Livewire::actingAs($this->admin)
        ->test(KelolaProductionLine::class)
        ->call('openEditForm', $productionLine->id)
        ->set('form.description', 'Baru')
        ->call('save')
        ->assertHasNoErrors();

    expect($productionLine->fresh()->description)->toBe('Baru');
    expect($productionLine->fresh()->code)->toBe('PL-LW-SELF');
});

// Business Unit wajib dipilih.
it('Business Unit wajib dipilih: shows a validation error under business_unit_id and does not create a row', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaProductionLine::class)
        ->call('openCreateForm')
        ->set('form.name', 'Line Tanpa BU')
        ->call('save')
        ->assertHasErrors(['business_unit_id'])
        ->assertSet('showForm', true);

    expect(ProductionLine::where('name', 'Line Tanpa BU')->exists())->toBeFalse();
});

// Nama kosong.
it('Nama kosong (create): shows a validation error under form.name and does not create a row', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaProductionLine::class)
        ->call('openCreateForm')
        ->set('business_unit_id', $this->businessUnit->id)
        ->call('save')
        ->assertHasErrors(['form.name'])
        ->assertSet('showForm', true);

    expect(ProductionLine::count())->toBe(0);
});

// "closeForm" resets the form entirely — a subsequent openCreateForm
// starts clean.
it('closeForm: resets the form and hides it', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaProductionLine::class)
        ->call('openCreateForm')
        ->set('business_unit_id', $this->businessUnit->id)
        ->set('form.name', 'Line Draft')
        ->call('closeForm')
        ->assertSet('showForm', false)
        ->assertSet('form.name', '')
        ->assertSet('business_unit_id', '');

    expect(ProductionLine::where('name', 'Line Draft')->exists())->toBeFalse();
});

// Editing a production line that was deleted by someone else between
// opening the form and submitting -> friendly formErrorMessage, not a 500.
it('save (edit): shows a friendly error message when the production line was deleted before saving', function () {
    $productionLine = ProductionLine::factory()->forBusinessUnit($this->businessUnit)->create();

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaProductionLine::class)
        ->call('openEditForm', $productionLine->id);

    $productionLine->delete();

    $component
        ->set('form.name', 'Line Dihapus')
        ->call('save')
        ->assertSet('formErrorMessage', fn ($message) => ! empty($message));
});

// Scenario "Kelola Production Line — Akses ditolak untuk non-Admin"
// (exercised via the real HTTP route, since access control is enforced at
// the routing layer.)
it('akses ditolak: returns 403 and never renders the component for a non-admin session', function (string $role) {
    $user = User::factory()->role(UserRole::from($role))->forBusinessUnit($this->businessUnit)->create();

    $response = $this->actingAs($user, 'web')->get('/master-data/production-lines');

    $response->assertForbidden();
    $response->assertDontSee('Kelola Production Line');
})->with([
    'supervisor' => ['supervisor'],
    'mill management' => ['mill_management'],
    'operator' => ['operator'],
]);

// Pagination — nextPage()/previousPage() move the page and clamp at 1.
it('nextPage/previousPage: paginates the list and clamps at page 1', function () {
    ProductionLine::factory()->forBusinessUnit($this->businessUnit)->withCode('PL-LW-PA')->create();
    ProductionLine::factory()->forBusinessUnit($this->businessUnit)->withCode('PL-LW-PB')->create();

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaProductionLine::class)
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
// to page 1 and only shows that business unit's production lines.
it('filters the list when filterBusinessUnitId is set, resetting to page 1', function () {
    $otherBusinessUnit = BusinessUnit::factory()->create();
    ProductionLine::factory()->forBusinessUnit($this->businessUnit)->withCode('PL-LW-A1')->create();
    ProductionLine::factory()->forBusinessUnit($otherBusinessUnit)->withCode('PL-LW-B1')->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaProductionLine::class)
        ->set('page', 2)
        ->set('filterBusinessUnitId', $this->businessUnit->id)
        ->assertSet('page', 1)
        ->assertViewHas('productionLines', fn ($rows) => collect($rows)->pluck('code')->all() === ['PL-LW-A1']);
});
