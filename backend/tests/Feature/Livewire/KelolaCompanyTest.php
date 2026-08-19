<?php

/**
 * KelolaCompanyTest (Feature/Livewire) — screen-028--kelola-company /
 * usecase-028--kelola-company.
 *
 * Component tests for App\Livewire\MasterData\KelolaCompany, one per
 * test_scenarios' component_test step (scenarios 1-6). Uses
 * Livewire::actingAs($user)->test() (mirrors tests/Feature/Livewire/
 * KelolaCorporateTest.php) — the component itself requires an
 * authenticated session (app(CompanyService::class) calls), independent
 * of the route-level role guard.
 *
 * Scenario 7 ("akses ditolak untuk non-Admin") deviates from
 * Livewire::test()'s usual mount-a-component-directly harness: per this
 * component's own docblock, access control for this screen is enforced
 * entirely at the routing layer ('auth' + 'role:admin' in routes/web.php,
 * EnsureRole::forbidden() -> abort(403)) — Livewire::test(KelolaCompany::
 * class) instantiates the component directly and does not run route
 * middleware, so it cannot observe that guard. This scenario instead
 * exercises the real HTTP route ($this->actingAs($user, 'web')->get(
 * '/master-data/companies')) to assert the actual access-denied behavior:
 * 403, and (implicitly, since the component never mounts when the
 * middleware short-circuits first) no list/controls are ever rendered.
 *
 * Scenario 6 ("Belum ada Corporate") note: the current implementation
 * (resources/views/livewire/master-data/kelola-company.blade.php) renders
 * only the placeholder "-- Pilih Corporate --" <option> when
 * corporateOptions is empty — there is no distinct `disabled` attribute or
 * dedicated "create a Corporate first" guidance copy in the markup. This
 * suite asserts the actual observable behavior (empty options list, only
 * the placeholder option visible) rather than a guidance message that does
 * not exist in the implementation; flagged as a known_issue in this
 * agent's final report rather than silently asserting something false.
 *
 * Entity-catalog v4 rework (screen-028--kelola-company 3-tech-spec ver 2):
 * confirmed directly from app/Livewire/MasterData/KelolaCompany.php —
 * the create/edit form is now a single `$form` keyed array (bound via
 * `wire:model="form.<field>"`), so every ->set()/->assertSet()/
 * ->assertHasErrors() call below for a FIELDS-list field (company_code,
 * name, short_name, ...) targets `form.<field>` rather than a bare
 * property name. `corporate_id` and `last_update`, however, are
 * deliberately kept OUTSIDE `$form` as their own top-level bound
 * properties (`wire:model="corporate_id"` / `wire:model="last_update"`),
 * mirroring the pre-existing corporate_id handling — so those two are
 * referenced bare, not as `form.corporate_id` / `form.last_update`. `logo`
 * is also a bare top-level property (WithFileUploads), same as
 * KelolaCorporate. `company_code` is now a second required field
 * alongside `name`, but UNLIKE `name` it is unique GLOBALLY (mirrors
 * KelolaCorporate's `corporate_code`) rather than scoped to corporate_id
 * — see the two company_code tests below for the differentiator from
 * `name`'s per-corporate scope.
 *
 * CRITICAL environment constraint: this environment has no PHP `gd`
 * extension installed, so `UploadedFile::fake()->image(...)` throws
 * `LogicException: GD extension is not installed`. Every logo-related test
 * below therefore uses the binary-fake pattern instead — e.g.
 * `UploadedFile::fake()->create('logo.jpg', $sizeInKb, 'image/jpeg')` —
 * exactly like KelolaCorporateTest.php's just-updated logo tests.
 */

use App\Enums\UserRole;
use App\Livewire\MasterData\KelolaCompany;
use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Corporate;
use App\Models\User;
use App\Services\CompanyService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

// Scenario 1: "Kelola Company — success"
it('berhasil: picks a Corporate, fills a unique code+name and creates a company that appears in the list', function () {
    $corporate = Corporate::factory()->create(['name' => 'PT Induk Jaya']);

    Livewire::actingAs($this->admin)
        ->test(KelolaCompany::class)
        ->call('openCreateForm')
        ->assertSet('showForm', true)
        ->set('corporate_id', $corporate->id)
        ->set('form.company_code', 'COMP-LW-001')
        ->set('form.name', 'PT Anak Usaha')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showForm', false)
        ->assertViewHas('companies', fn ($companies) => collect($companies)->contains(
            fn ($c) => $c['name'] === 'PT Anak Usaha'
                && $c['corporate_name'] === 'PT Induk Jaya'
                && $c['business_unit_count'] === 0
        ));

    expect(Company::where('name', 'PT Anak Usaha')->exists())->toBeTrue();
});

// Scenario 2: "Kelola Company — Edit Company"
it('Edit Company: loads the existing values then updates the name and corporate', function () {
    $corporateA = Corporate::factory()->create(['name' => 'PT Awal']);
    $corporateB = Corporate::factory()->create(['name' => 'PT Tujuan']);
    $company = Company::factory()->create(['corporate_id' => $corporateA->id, 'company_code' => 'COMP-LW-002', 'name' => 'PT Lama Jaya']);

    Livewire::actingAs($this->admin)
        ->test(KelolaCompany::class)
        ->call('openEditForm', $company->id)
        ->assertSet('editingId', $company->id)
        ->assertSet('corporate_id', $corporateA->id)
        ->assertSet('form.company_code', 'COMP-LW-002')
        ->assertSet('form.name', 'PT Lama Jaya')
        ->set('corporate_id', $corporateB->id)
        ->set('form.name', 'PT Baru Sentosa')
        ->call('save')
        ->assertHasNoErrors()
        ->assertViewHas('companies', fn ($companies) => collect($companies)->contains(
            fn ($c) => $c['id'] === $company->id
                && $c['name'] === 'PT Baru Sentosa'
                && $c['corporate_name'] === 'PT Tujuan'
        ));

    expect($company->fresh()->name)->toBe('PT Baru Sentosa');
    expect($company->fresh()->corporate_id)->toBe($corporateB->id);
});

// Scenario 3: "Kelola Company — Hapus Company — berhasil"
it('Hapus Company berhasil: removes the row from the list after confirmation', function () {
    $company = Company::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaCompany::class)
        ->call('askDelete', $company->id)
        ->assertSet('confirmingDeleteId', $company->id)
        ->call('confirmDelete')
        ->assertSet('confirmingDeleteId', null)
        ->assertSet('deleteErrorMessage', null)
        ->assertViewHas('companies', fn ($companies) => ! collect($companies)->contains(
            fn ($c) => $c['id'] === $company->id
        ));

    expect(Company::find($company->id))->toBeNull();
});

// Scenario 4: "Kelola Company — Hapus Company — ditolak"
it('Hapus Company ditolak: shows an error and keeps the row when it has related business units', function () {
    $company = Company::factory()->create();
    BusinessUnit::factory()->create(['company_id' => $company->id]);

    Livewire::actingAs($this->admin)
        ->test(KelolaCompany::class)
        ->call('askDelete', $company->id)
        ->call('confirmDelete')
        ->assertSet('confirmingDeleteId', null)
        ->assertSet('deleteErrorMessage', fn ($message) => str_contains($message, 'Business Unit'))
        ->assertSee($company->name)
        ->assertViewHas('companies', fn ($companies) => collect($companies)->contains(
            fn ($c) => $c['id'] === $company->id
        ));

    expect(Company::find($company->id))->not->toBeNull();
});

// Scenario 5: "Kelola Company — Nama duplikat dalam Corporate yang sama"
// (create branch, in the SAME corporate)
it('Nama duplikat (create, same corporate): shows a validation error under form.name and does not create a row', function () {
    $corporate = Corporate::factory()->create();
    Company::factory()->create(['corporate_id' => $corporate->id, 'company_code' => 'COMP-LW-003', 'name' => 'PT Anak Usaha']);

    Livewire::actingAs($this->admin)
        ->test(KelolaCompany::class)
        ->call('openCreateForm')
        ->set('corporate_id', $corporate->id)
        ->set('form.company_code', 'COMP-LW-004')
        ->set('form.name', 'PT Anak Usaha')
        ->call('save')
        ->assertHasErrors(['form.name'])
        ->assertSet('showForm', true);

    expect(Company::where('corporate_id', $corporate->id)->where('name', 'PT Anak Usaha')->count())->toBe(1);
});

// Scenario 5: "Kelola Company — Nama duplikat dalam Corporate yang sama"
// (edit branch, in the SAME corporate)
it('Nama duplikat (edit, same corporate): shows a validation error under form.name and does not update the row', function () {
    $corporate = Corporate::factory()->create();
    Company::factory()->create(['corporate_id' => $corporate->id, 'company_code' => 'COMP-LW-005', 'name' => 'PT Alpha']);
    $target = Company::factory()->create(['corporate_id' => $corporate->id, 'company_code' => 'COMP-LW-006', 'name' => 'PT Beta']);

    Livewire::actingAs($this->admin)
        ->test(KelolaCompany::class)
        ->call('openEditForm', $target->id)
        ->set('form.name', 'PT Alpha')
        ->call('save')
        ->assertHasErrors(['form.name'])
        ->assertSet('showForm', true);

    expect($target->fresh()->name)->toBe('PT Beta');
});

// CRITICAL — the key differentiator from KelolaCorporateTest.php: the same
// name is allowed under a DIFFERENT corporate, submitted successfully via
// the component with no validation error. (company_code must still be
// globally unique, so a fresh one is used.)
it('creates a company successfully with a name already used under a different corporate (no conflict)', function () {
    $corporateA = Corporate::factory()->create();
    $corporateB = Corporate::factory()->create();
    Company::factory()->create(['corporate_id' => $corporateA->id, 'company_code' => 'COMP-LW-007', 'name' => 'PT Anak Usaha']);

    Livewire::actingAs($this->admin)
        ->test(KelolaCompany::class)
        ->call('openCreateForm')
        ->set('corporate_id', $corporateB->id)
        ->set('form.company_code', 'COMP-LW-008')
        ->set('form.name', 'PT Anak Usaha')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showForm', false);

    expect(Company::where('name', 'PT Anak Usaha')->count())->toBe(2);
});

// New (CRITICAL — the key differentiator from company_code's global
// scope): company_code required — leaving it blank shows a validation
// error under form.company_code and does not create a row.
it('Kode kosong (create): shows a validation error under form.company_code and does not create a row', function () {
    $corporate = Corporate::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaCompany::class)
        ->call('openCreateForm')
        ->set('corporate_id', $corporate->id)
        ->set('form.name', 'PT Tanpa Kode')
        ->call('save')
        ->assertHasErrors(['form.company_code'])
        ->assertSet('showForm', true);

    expect(Company::where('name', 'PT Tanpa Kode')->exists())->toBeFalse();
});

// New: duplicate company_code under a DIFFERENT corporate (create branch)
// -> error under form.company_code, unlike `name` this is NOT scoped to
// corporate_id.
it('Kode duplikat lintas Corporate (create): shows a validation error under form.company_code and does not create a row', function () {
    $corporateA = Corporate::factory()->create();
    $corporateB = Corporate::factory()->create();
    Company::factory()->create(['corporate_id' => $corporateA->id, 'company_code' => 'COMP-LW-DUP']);

    Livewire::actingAs($this->admin)
        ->test(KelolaCompany::class)
        ->call('openCreateForm')
        ->set('corporate_id', $corporateB->id)
        ->set('form.company_code', 'COMP-LW-DUP')
        ->set('form.name', 'PT Nama Berbeda')
        ->call('save')
        ->assertHasErrors(['form.company_code'])
        ->assertSet('showForm', true);

    expect(Company::where('name', 'PT Nama Berbeda')->exists())->toBeFalse();
});

// New: duplicate company_code (edit branch) -> error under
// form.company_code, target row left untouched.
it('Kode duplikat (edit): shows a validation error under form.company_code and does not update the row', function () {
    Company::factory()->create(['company_code' => 'COMP-LW-OTHER']);
    $target = Company::factory()->create(['company_code' => 'COMP-LW-TARGET']);

    Livewire::actingAs($this->admin)
        ->test(KelolaCompany::class)
        ->call('openEditForm', $target->id)
        ->set('form.company_code', 'COMP-LW-OTHER')
        ->call('save')
        ->assertHasErrors(['form.company_code'])
        ->assertSet('showForm', true);

    expect($target->fresh()->company_code)->toBe('COMP-LW-TARGET');
});

// New: updating a company while keeping its own company_code unchanged
// must succeed (no false-positive uniqueness violation against self).
it('updates a company keeping its own company_code unchanged without errors', function () {
    $company = Company::factory()->create(['company_code' => 'COMP-LW-SELF', 'name' => 'PT Lama']);

    Livewire::actingAs($this->admin)
        ->test(KelolaCompany::class)
        ->call('openEditForm', $company->id)
        ->set('form.name', 'PT Baru')
        ->call('save')
        ->assertHasNoErrors();

    expect($company->fresh()->name)->toBe('PT Baru');
    expect($company->fresh()->company_code)->toBe('COMP-LW-SELF');
});

// New: a valid logo upload succeeds and is stored on LOGO_DISK under
// company-logos/.
it('uploads a valid logo image successfully and stores the file', function () {
    Storage::fake(CompanyService::LOGO_DISK);
    $corporate = Corporate::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaCompany::class)
        ->call('openCreateForm')
        ->set('corporate_id', $corporate->id)
        ->set('form.company_code', 'COMP-LW-LOGO-001')
        ->set('form.name', 'PT Berlogo')
        ->set('logo', UploadedFile::fake()->create('logo.jpg', 500, 'image/jpeg'))
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showForm', false);

    $company = Company::where('name', 'PT Berlogo')->firstOrFail();
    expect($company->logo)->not->toBeNull();
    expect($company->logo)->toStartWith('company-logos/');
    Storage::disk(CompanyService::LOGO_DISK)->assertExists($company->logo);
});

// New: `logo` is optional — omitting it entirely still succeeds.
it('creates a company successfully when no logo is chosen', function () {
    $corporate = Corporate::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaCompany::class)
        ->call('openCreateForm')
        ->set('corporate_id', $corporate->id)
        ->set('form.company_code', 'COMP-LW-LOGO-002')
        ->set('form.name', 'PT Tanpa Logo')
        ->call('save')
        ->assertHasNoErrors();

    expect(Company::where('name', 'PT Tanpa Logo')->firstOrFail()->logo)->toBeNull();
});

// New: an oversized logo (> 2MB) -> validation error under `logo` (the
// component's top-level upload property, not `form.logo`), form stays open.
it('shows a validation error under logo when the file exceeds the max size', function () {
    Storage::fake(CompanyService::LOGO_DISK);
    $corporate = Corporate::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaCompany::class)
        ->call('openCreateForm')
        ->set('corporate_id', $corporate->id)
        ->set('form.company_code', 'COMP-LW-LOGO-003')
        ->set('form.name', 'PT Logo Besar')
        ->set('logo', UploadedFile::fake()->create('logo.jpg', 3000, 'image/jpeg'))
        ->call('save')
        ->assertHasErrors(['logo'])
        ->assertSet('showForm', true);

    expect(Company::where('name', 'PT Logo Besar')->exists())->toBeFalse();
});

// New: a disallowed mime type -> validation error under `logo`. Also
// verifies the getLogoIsPreviewableProperty() guard does not throw
// FileNotPreviewableException on the re-render that follows the failed
// validate() call, since a .pdf sits in $logo but is not one of
// PREVIEWABLE_LOGO_EXTENSIONS.
it('shows a validation error under logo when the file has a disallowed mime type, without the preview guard throwing', function () {
    Storage::fake(CompanyService::LOGO_DISK);
    $corporate = Corporate::factory()->create();

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaCompany::class)
        ->call('openCreateForm')
        ->set('corporate_id', $corporate->id)
        ->set('form.company_code', 'COMP-LW-LOGO-004')
        ->set('form.name', 'PT Logo Salah Format')
        ->set('logo', UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'))
        ->call('save')
        ->assertHasErrors(['logo'])
        ->assertSet('showForm', true);

    // The component's own computed property confirms the guard resolves
    // to "not previewable" (rather than throwing) for a disallowed
    // extension still sitting in $logo after the failed validation.
    expect($component->get('logoIsPreviewable'))->toBeFalse();

    expect(Company::where('name', 'PT Logo Salah Format')->exists())->toBeFalse();
});

// New: openEditForm() populates existingLogoUrl as the preview for a
// company that already has a stored logo.
it('populates existingLogoUrl when editing a company that already has a logo', function () {
    $company = Company::factory()->create([
        'company_code' => 'COMP-LW-LOGO-005',
        'logo' => 'company-logos/existing.jpg',
    ]);

    Livewire::actingAs($this->admin)
        ->test(KelolaCompany::class)
        ->call('openEditForm', $company->id)
        ->assertSet('existingLogoUrl', fn ($url) => ! empty($url))
        ->assertSet('logo', null);
});

// New: last_update accepts a valid date and is persisted.
it('accepts a valid last_update date and persists it', function () {
    $corporate = Corporate::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaCompany::class)
        ->call('openCreateForm')
        ->set('corporate_id', $corporate->id)
        ->set('form.company_code', 'COMP-LW-DATE-001')
        ->set('form.name', 'PT Tanggal Valid')
        ->set('last_update', '2026-08-01')
        ->call('save')
        ->assertHasNoErrors();

    expect(Company::where('name', 'PT Tanggal Valid')->firstOrFail()->last_update->toDateString())->toBe('2026-08-01');
});

// New: last_update rejects an invalid (non-date) value -> validation error
// under `last_update`.
it('shows a validation error under last_update when the value is not a valid date', function () {
    $corporate = Corporate::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaCompany::class)
        ->call('openCreateForm')
        ->set('corporate_id', $corporate->id)
        ->set('form.company_code', 'COMP-LW-DATE-002')
        ->set('form.name', 'PT Tanggal Salah')
        ->set('last_update', 'bukan-tanggal')
        ->call('save')
        ->assertHasErrors(['last_update'])
        ->assertSet('showForm', true);

    expect(Company::where('name', 'PT Tanggal Salah')->exists())->toBeFalse();
});

// New: created_by is set from the authenticated admin and surfaced in the
// rendered row.
it('sets created_by from the authenticated admin and surfaces it in the rendered row', function () {
    $corporate = Corporate::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaCompany::class)
        ->call('openCreateForm')
        ->set('corporate_id', $corporate->id)
        ->set('form.company_code', 'COMP-LW-AUDIT-001')
        ->set('form.name', 'PT Audit Trail')
        ->call('save')
        ->assertHasNoErrors()
        ->assertViewHas('companies', fn ($companies) => collect($companies)->contains(
            fn ($c) => $c['name'] === 'PT Audit Trail' && $c['created_by'] === $this->admin->id
        ));

    expect(Company::where('name', 'PT Audit Trail')->firstOrFail()->created_by)->toBe($this->admin->id);
});

// New: optional contact/legal fields round-trip through the component —
// set via form.<field>, persisted, and reflected in the rendered row.
it('accepts, persists, and returns optional contact/legal fields when provided', function () {
    $corporate = Corporate::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaCompany::class)
        ->call('openCreateForm')
        ->set('corporate_id', $corporate->id)
        ->set('form.company_code', 'COMP-LW-FIELDS-001')
        ->set('form.name', 'PT Lengkap')
        ->set('form.short_name', 'PSM')
        ->set('form.leader_name', 'Budi Santoso')
        ->set('form.email', 'info@ptlengkap.co.id')
        ->call('save')
        ->assertHasNoErrors()
        ->assertViewHas('companies', fn ($companies) => collect($companies)->contains(
            fn ($c) => $c['name'] === 'PT Lengkap' && $c['short_name'] === 'PSM' && $c['leader_name'] === 'Budi Santoso'
        ));

    $fresh = Company::where('name', 'PT Lengkap')->firstOrFail();
    expect($fresh->short_name)->toBe('PSM');
    expect($fresh->email)->toBe('info@ptlengkap.co.id');
});

// New: omitting every optional field does not error.
it('creates a company successfully when every optional field is left blank', function () {
    $corporate = Corporate::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaCompany::class)
        ->call('openCreateForm')
        ->set('corporate_id', $corporate->id)
        ->set('form.company_code', 'COMP-LW-FIELDS-002')
        ->set('form.name', 'PT Minimal')
        ->call('save')
        ->assertHasNoErrors();

    $fresh = Company::where('name', 'PT Minimal')->firstOrFail();
    expect($fresh->short_name)->toBeNull();
    expect($fresh->address)->toBeNull();
});

// Scenario 6: "Kelola Company — Belum ada Corporate"
it('Belum ada Corporate: renders an empty corporate dropdown when opening the create form', function () {
    Corporate::query()->delete();

    Livewire::actingAs($this->admin)
        ->test(KelolaCompany::class)
        ->assertViewHas('corporateOptions', fn ($options) => count($options) === 0)
        ->call('openCreateForm')
        ->assertSet('showForm', true)
        ->assertSee('-- Pilih Corporate --');

    // Submitting with no Corporate selected is rejected by the same
    // corporate_id validation rule as any other invalid corporate_id.
    Livewire::actingAs($this->admin)
        ->test(KelolaCompany::class)
        ->call('openCreateForm')
        ->set('form.company_code', 'COMP-LW-009')
        ->set('form.name', 'PT Tanpa Corporate')
        ->call('save')
        ->assertHasErrors(['corporate_id']);
});

// Scenario 7: "Kelola Company — akses ditolak untuk non-Admin"
// (see file-level docblock: exercised via the real HTTP route rather than
// Livewire::test(), since access control is enforced at the routing layer.)
it('akses ditolak: returns 403 and never renders the component for a non-admin session', function (string $role) {
    $user = User::factory()->role(UserRole::from($role))->create();

    $response = $this->actingAs($user, 'web')->get('/master-data/companies');

    $response->assertForbidden();
    $response->assertDontSee('Kelola Company');
})->with([
    'supervisor' => ['supervisor'],
    'mill management' => ['mill_management'],
    'operator' => ['operator'],
]);

// Additional coverage: filtering the list by corporate_id resets to page 1
// and only shows that corporate's companies. UNCHANGED.
it('filters the list when filterCorporateId is set', function () {
    $corporateA = Corporate::factory()->create();
    $corporateB = Corporate::factory()->create();
    Company::factory()->create(['corporate_id' => $corporateA->id, 'name' => 'PT A1']);
    Company::factory()->create(['corporate_id' => $corporateB->id, 'name' => 'PT B1']);

    Livewire::actingAs($this->admin)
        ->test(KelolaCompany::class)
        ->set('filterCorporateId', $corporateA->id)
        ->assertViewHas('companies', fn ($companies) => collect($companies)->pluck('name')->all() === ['PT A1']);
});
