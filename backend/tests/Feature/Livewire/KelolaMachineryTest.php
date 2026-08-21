<?php

/**
 * KelolaMachineryTest (Feature/Livewire) — screen-031--kelola-machinery /
 * usecase-031--kelola-machinery.
 *
 * Component tests for App\Livewire\MasterData\KelolaMachinery. Uses
 * Livewire::actingAs($user)->test() (mirrors tests/Feature/Livewire/
 * KelolaMachineryGroupTest.php) — the component itself requires an
 * authenticated session (app(MachineryService::class) calls),
 * independent of the route-level role guard.
 *
 * The "akses ditolak" scenario deviates from Livewire::test()'s usual
 * mount-a-component-directly harness, same reasoning as
 * KelolaMachineryGroupTest.php's own docblock: access control for this
 * screen is enforced entirely at the routing layer, so this scenario
 * exercises the real HTTP route instead.
 *
 * CRITICAL divergences this screen's own field set requires:
 *  - `machinery_group_id` is the bare top-level bound property (like
 *    `station_id` on KelolaMachineryGroupTest.php); `selectedStationName`/
 *    `selectedProductionLineName` are display-only derived properties
 *    (production_line_id, not business_unit_id — see entity-catalog v10).
 *  - `$insurances`/`$taxPurchases` are single-row sections (index 0 only,
 *    one Asuransi row and one Pajak/Pembelian row per machinery — not a
 *    repeatable grid); a blank row persists no child record at all.
 *  - `picture` is a WithFileUploads upload (mirrors
 *    tests/Feature/Livewire/KelolaCorporateTest.php's `logo` coverage,
 *    using UploadedFile::fake()->create() rather than ->image() per the
 *    established GD-extension workaround — this environment does not
 *    reliably have the GD extension available for ->image()'s actual
 *    PNG/JPEG byte generation).
 *  - delete() has NO guard/exception branch — confirmDelete() always
 *    succeeds once confirmed, unlike KelolaMachineryGroupTest.php's
 *    "Hapus ditolak" scenario, which has no equivalent here.
 */

use App\Enums\UserRole;
use App\Livewire\MasterData\KelolaMachinery;
use App\Models\BusinessUnit;
use App\Models\Machinery;
use App\Models\MachineryGroup;
use App\Models\MachineryInsurance;
use App\Models\MachineryTaxPurchase;
use App\Models\Station;
use App\Models\User;
use App\Services\MachineryService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create(['name' => 'Mill Unit Awal']);
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create(['name' => 'Weighbridge Awal']);
    $this->productionLine = $this->station->productionLine()->first();
    $this->group = MachineryGroup::factory()->forStation($this->station)->withGroupCode('MG-LW-BASE')->create();
    $this->admin = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->businessUnit)->create();
});

// Scenario "Kelola Machinery — success"
it('berhasil: picks a Machinery Group, fills the form and creates a machinery that appears in the list', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaMachinery::class)
        ->call('openCreateForm')
        ->assertSet('showForm', true)
        ->set('machinery_group_id', $this->group->id)
        ->assertSet('selectedStationName', 'Weighbridge Awal')
        ->assertSet('selectedProductionLineName', $this->productionLine->name)
        ->set('form.equipment_code', 'EQ-LW-001')
        ->set('form.name', 'Boiler Utama')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showForm', false)
        ->assertViewHas('machineryRows', fn ($rows) => collect($rows)->contains(
            fn ($r) => $r['equipment_code'] === 'EQ-LW-001'
                && $r['machinery_group_code'] === 'MG-LW-BASE'
        ));

    $fresh = Machinery::where('equipment_code', 'EQ-LW-001')->firstOrFail();
    expect($fresh->station_id)->toBe($this->station->id);
    expect($fresh->production_line_id)->toBe($this->station->production_line_id);
});

// CRITICAL — the derived display-only names never affect what's
// persisted, even if forced out of sync.
it('selectedStationName/selectedProductionLineName are purely cosmetic and never affect the persisted station_id/production_line_id', function () {
    $otherStation = Station::factory()->create(['name' => 'Station Lain']);

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaMachinery::class)
        ->call('openCreateForm')
        ->set('machinery_group_id', $this->group->id)
        ->assertSet('selectedStationName', 'Weighbridge Awal');

    $component->set('selectedStationName', $otherStation->name);

    $component
        ->set('form.equipment_code', 'EQ-LW-COSMETIC')
        ->set('form.name', 'Mesin Kosmetik')
        ->call('save')
        ->assertHasNoErrors();

    $fresh = Machinery::where('equipment_code', 'EQ-LW-COSMETIC')->firstOrFail();
    expect($fresh->station_id)->toBe($this->station->id);
    expect($fresh->station_id)->not->toBe($otherStation->id);
});

it('clears selectedStationName/selectedProductionLineName when the machinery group selection is cleared', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaMachinery::class)
        ->call('openCreateForm')
        ->set('machinery_group_id', $this->group->id)
        ->assertSet('selectedStationName', 'Weighbridge Awal')
        ->set('machinery_group_id', '')
        ->assertSet('selectedStationName', null)
        ->assertSet('selectedProductionLineName', null);
});

// Scenario "Kelola Machinery — Edit Machinery"
it('Edit Machinery: loads the existing values then updates the equipment_code/name', function () {
    $machinery = Machinery::factory()->forFullMachineryGroup($this->group)->withEquipmentCode('EQ-LW-002')->create(['name' => 'Lama']);

    Livewire::actingAs($this->admin)
        ->test(KelolaMachinery::class)
        ->call('openEditForm', $machinery->id)
        ->assertSet('editingId', $machinery->id)
        ->assertSet('machinery_group_id', $this->group->id)
        ->assertSet('form.equipment_code', 'EQ-LW-002')
        ->assertSet('form.name', 'Lama')
        ->set('form.name', 'Baru')
        ->call('save')
        ->assertHasNoErrors()
        ->assertViewHas('machineryRows', fn ($rows) => collect($rows)->contains(
            fn ($r) => $r['id'] === $machinery->id && $r['name'] === 'Baru'
        ));

    expect($machinery->fresh()->name)->toBe('Baru');
});

// Scenario "Kelola Machinery — Hapus — berhasil"
it('Hapus berhasil: removes the row from the list after confirmation, no guard of any kind', function () {
    $machinery = Machinery::factory()->forFullMachineryGroup($this->group)->create();
    MachineryInsurance::factory()->forMachinery($machinery)->count(2)->create();
    MachineryTaxPurchase::factory()->forMachinery($machinery)->count(2)->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaMachinery::class)
        ->call('askDelete', $machinery->id)
        ->assertSet('confirmingDeleteId', $machinery->id)
        ->call('confirmDelete')
        ->assertSet('confirmingDeleteId', null)
        ->assertSet('deleteErrorMessage', null)
        ->assertViewHas('machineryRows', fn ($rows) => ! collect($rows)->contains(
            fn ($r) => $r['id'] === $machinery->id
        ));

    expect(Machinery::find($machinery->id))->toBeNull();
});

it('cancelDelete: cancels the inline confirmation without deleting the row', function () {
    $machinery = Machinery::factory()->forFullMachineryGroup($this->group)->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaMachinery::class)
        ->call('askDelete', $machinery->id)
        ->call('cancelDelete')
        ->assertSet('confirmingDeleteId', null);

    expect(Machinery::find($machinery->id))->not->toBeNull();
});

// Kode duplikat (create branch)
it('Kode duplikat (create): shows a validation error under form.equipment_code and does not create a row', function () {
    Machinery::factory()->forFullMachineryGroup($this->group)->withEquipmentCode('EQ-LW-004')->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaMachinery::class)
        ->call('openCreateForm')
        ->set('machinery_group_id', $this->group->id)
        ->set('form.equipment_code', 'EQ-LW-004')
        ->set('form.name', 'Mesin Duplikat')
        ->call('save')
        ->assertHasErrors(['form.equipment_code'])
        ->assertSet('showForm', true);

    expect(Machinery::where('equipment_code', 'EQ-LW-004')->count())->toBe(1);
});

// Kode duplikat (edit branch)
it('Kode duplikat (edit): shows a validation error under form.equipment_code and does not update the row', function () {
    Machinery::factory()->forFullMachineryGroup($this->group)->withEquipmentCode('EQ-LW-OTHER')->create();
    $target = Machinery::factory()->forFullMachineryGroup($this->group)->withEquipmentCode('EQ-LW-TARGET')->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaMachinery::class)
        ->call('openEditForm', $target->id)
        ->set('form.equipment_code', 'EQ-LW-OTHER')
        ->call('save')
        ->assertHasErrors(['form.equipment_code'])
        ->assertSet('showForm', true);

    expect($target->fresh()->equipment_code)->toBe('EQ-LW-TARGET');
});

it('updates a machinery keeping its own equipment_code unchanged without errors', function () {
    $machinery = Machinery::factory()->forFullMachineryGroup($this->group)->withEquipmentCode('EQ-LW-SELF')->create(['description' => 'Lama']);

    Livewire::actingAs($this->admin)
        ->test(KelolaMachinery::class)
        ->call('openEditForm', $machinery->id)
        ->set('form.description', 'Baru')
        ->call('save')
        ->assertHasNoErrors();

    expect($machinery->fresh()->description)->toBe('Baru');
    expect($machinery->fresh()->equipment_code)->toBe('EQ-LW-SELF');
});

it('Machinery Group wajib dipilih: shows a validation error under machinery_group_id and does not create a row', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaMachinery::class)
        ->call('openCreateForm')
        ->set('form.equipment_code', 'EQ-LW-NOGROUP')
        ->set('form.name', 'Mesin Tanpa Group')
        ->call('save')
        ->assertHasErrors(['machinery_group_id'])
        ->assertSet('showForm', true);

    expect(Machinery::where('equipment_code', 'EQ-LW-NOGROUP')->exists())->toBeFalse();
});

it('Kode kosong (create): shows a validation error under form.equipment_code and does not create a row', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaMachinery::class)
        ->call('openCreateForm')
        ->set('machinery_group_id', $this->group->id)
        ->set('form.name', 'Mesin Tanpa Kode')
        ->call('save')
        ->assertHasErrors(['form.equipment_code'])
        ->assertSet('showForm', true);

    expect(Machinery::count())->toBe(0);
});

it('Nama kosong (create): shows a validation error under form.name and does not create a row', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaMachinery::class)
        ->call('openCreateForm')
        ->set('machinery_group_id', $this->group->id)
        ->set('form.equipment_code', 'EQ-LW-NONAME')
        ->call('save')
        ->assertHasErrors(['form.name'])
        ->assertSet('showForm', true);

    expect(Machinery::count())->toBe(0);
});

// --- single-row section: insurance ------------------------------------------------

it('insurances.0.* fields persist as a single Asuransi row', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaMachinery::class)
        ->call('openCreateForm')
        ->assertCount('insurances', 1)
        ->set('machinery_group_id', $this->group->id)
        ->set('form.equipment_code', 'EQ-LW-INS')
        ->set('form.name', 'Mesin Asuransi')
        ->set('insurances.0.ownership', 'Perusahaan')
        ->set('insurances.0.insurance_policy_no', 'POL-LW-1')
        ->call('save')
        ->assertHasNoErrors();

    $machinery = Machinery::where('equipment_code', 'EQ-LW-INS')->firstOrFail();
    expect(MachineryInsurance::where('machinery_id', $machinery->id)->count())->toBe(1);
    expect(MachineryInsurance::where('machinery_id', $machinery->id)->first()->insurance_policy_no)->toBe('POL-LW-1');
});

it('leaving the insurance row entirely blank creates no MachineryInsurance record', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaMachinery::class)
        ->call('openCreateForm')
        ->set('machinery_group_id', $this->group->id)
        ->set('form.equipment_code', 'EQ-LW-INS-BLANK')
        ->set('form.name', 'Mesin Tanpa Asuransi')
        ->call('save')
        ->assertHasNoErrors();

    $machinery = Machinery::where('equipment_code', 'EQ-LW-INS-BLANK')->firstOrFail();
    expect(MachineryInsurance::where('machinery_id', $machinery->id)->count())->toBe(0);
});

// --- single-row section: tax / purchase ----------------------------------------------

it('taxPurchases.0.* fields persist as a single Pajak/Pembelian row', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaMachinery::class)
        ->call('openCreateForm')
        ->assertCount('taxPurchases', 1)
        ->set('machinery_group_id', $this->group->id)
        ->set('form.equipment_code', 'EQ-LW-TAX')
        ->set('form.name', 'Mesin Pajak')
        ->set('taxPurchases.0.policy_type', 'Cash')
        ->set('taxPurchases.0.contact_name', 'Budi')
        ->call('save')
        ->assertHasNoErrors();

    $machinery = Machinery::where('equipment_code', 'EQ-LW-TAX')->firstOrFail();
    expect(MachineryTaxPurchase::where('machinery_id', $machinery->id)->count())->toBe(1);
    expect(MachineryTaxPurchase::where('machinery_id', $machinery->id)->first()->policy_type)->toBe('Cash');
});

// Editing a machinery pre-loads its existing child rows into the grids.
it('openEditForm pre-loads existing insurance/tax_purchase rows into the grids', function () {
    $machinery = Machinery::factory()->forFullMachineryGroup($this->group)->create();
    MachineryInsurance::factory()->forMachinery($machinery)->create(['insurance_policy_no' => 'POL-PRELOAD']);
    MachineryTaxPurchase::factory()->forMachinery($machinery)->create(['policy_type' => 'Leasing']);

    Livewire::actingAs($this->admin)
        ->test(KelolaMachinery::class)
        ->call('openEditForm', $machinery->id)
        ->assertCount('insurances', 1)
        ->assertSet('insurances.0.insurance_policy_no', 'POL-PRELOAD')
        ->assertCount('taxPurchases', 1)
        ->assertSet('taxPurchases.0.policy_type', 'Leasing');
});

// --- picture upload -----------------------------------------------------------

it('uploads a valid picture successfully and stores the file', function () {
    Storage::fake(MachineryService::PICTURE_DISK);

    Livewire::actingAs($this->admin)
        ->test(KelolaMachinery::class)
        ->call('openCreateForm')
        ->set('machinery_group_id', $this->group->id)
        ->set('form.equipment_code', 'EQ-LW-PIC')
        ->set('form.name', 'Mesin Berfoto')
        ->set('picture', UploadedFile::fake()->create('picture.jpg', 500, 'image/jpeg'))
        ->call('save')
        ->assertHasNoErrors();

    $machinery = Machinery::where('equipment_code', 'EQ-LW-PIC')->firstOrFail();
    expect($machinery->picture)->not->toBeNull();
    Storage::disk(MachineryService::PICTURE_DISK)->assertExists($machinery->picture);
});

it('shows a validation error under picture when the file exceeds the max size', function () {
    Storage::fake(MachineryService::PICTURE_DISK);

    Livewire::actingAs($this->admin)
        ->test(KelolaMachinery::class)
        ->call('openCreateForm')
        ->set('machinery_group_id', $this->group->id)
        ->set('form.equipment_code', 'EQ-LW-PICBIG')
        ->set('form.name', 'Mesin Foto Besar')
        ->set('picture', UploadedFile::fake()->create('picture.jpg', 3000, 'image/jpeg'))
        ->call('save')
        ->assertHasErrors(['picture']);

    expect(Machinery::where('equipment_code', 'EQ-LW-PICBIG')->exists())->toBeFalse();
});

// "closeForm" resets the form entirely, including the insurance/tax rows.
it('closeForm: resets the form, picture, and insurance/tax rows and hides it', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaMachinery::class)
        ->call('openCreateForm')
        ->set('machinery_group_id', $this->group->id)
        ->set('form.equipment_code', 'EQ-LW-DRAFT')
        ->set('insurances.0.ownership', 'Perusahaan')
        ->call('closeForm')
        ->assertSet('showForm', false)
        ->assertSet('form.equipment_code', '')
        ->assertSet('machinery_group_id', '')
        ->assertSet('insurances', [])
        ->assertSet('taxPurchases', []);

    expect(Machinery::where('equipment_code', 'EQ-LW-DRAFT')->exists())->toBeFalse();
});

// Editing a machinery that was deleted by someone else between opening
// the form and submitting -> friendly formErrorMessage, not a 500.
it('save (edit): shows a friendly error message when the machinery was deleted before saving', function () {
    $machinery = Machinery::factory()->forFullMachineryGroup($this->group)->create();

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaMachinery::class)
        ->call('openEditForm', $machinery->id);

    $machinery->delete();

    $component
        ->set('form.name', 'Nama Setelah Dihapus')
        ->call('save')
        ->assertSet('formErrorMessage', fn ($message) => ! empty($message));
});

// Scenario "Kelola Machinery — Akses ditolak untuk non-Admin"
it('akses ditolak: returns 403 and never renders the component for a non-admin session', function (string $role) {
    $user = User::factory()->role(UserRole::from($role))->forBusinessUnit($this->businessUnit)->create();

    $response = $this->actingAs($user, 'web')->get('/master-data/machinery');

    $response->assertForbidden();
    $response->assertDontSee('Kelola Machinery');
})->with([
    'supervisor' => ['supervisor'],
    'mill management' => ['mill_management'],
    'operator' => ['operator'],
]);

// Pagination — nextPage()/previousPage() move the page and clamp at 1.
it('nextPage/previousPage: paginates the list and clamps at page 1', function () {
    Machinery::factory()->forFullMachineryGroup($this->group)->withEquipmentCode('EQ-LW-PA')->create();
    Machinery::factory()->forFullMachineryGroup($this->group)->withEquipmentCode('EQ-LW-PB')->create();

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaMachinery::class)
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

// Filtering by filterMachineryGroupId resets to page 1 and only shows
// that group's machinery.
it('filters the list when filterMachineryGroupId is set, resetting to page 1', function () {
    $groupB = MachineryGroup::factory()->forStation($this->station)->withGroupCode('MG-LW-B')->create();
    Machinery::factory()->forFullMachineryGroup($this->group)->withEquipmentCode('EQ-LW-A1')->create();
    Machinery::factory()->forFullMachineryGroup($groupB)->withEquipmentCode('EQ-LW-B1')->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaMachinery::class)
        ->set('page', 2)
        ->set('filterMachineryGroupId', $this->group->id)
        ->assertSet('page', 1)
        ->assertViewHas('machineryRows', fn ($rows) => collect($rows)->pluck('equipment_code')->all() === ['EQ-LW-A1']);
});
