<?php

/**
 * KelolaBusinessUnitTest (Feature/Livewire) — screen-029--kelola-business-unit
 * / usecase-029--kelola-business-unit.
 *
 * Component tests for App\Livewire\MasterData\KelolaBusinessUnit, one per
 * test_scenarios' component_test step. Uses Livewire::actingAs($user)->test()
 * (mirrors tests/Feature/Livewire/KelolaCompanyTest.php) — the component
 * itself requires an authenticated session (app(BusinessUnitService::class)
 * calls), independent of the route-level role guard.
 *
 * The "akses ditolak" scenario deviates from Livewire::test()'s usual
 * mount-a-component-directly harness: per this component's own docblock,
 * access control for this screen is enforced entirely at the routing layer
 * ('auth' + 'role:admin' in routes/web.php, EnsureRole::forbidden() ->
 * abort(403)) — Livewire::test(KelolaBusinessUnit::class) instantiates the
 * component directly and does not run route middleware, so it cannot
 * observe that guard. This scenario instead exercises the real HTTP route
 * ($this->actingAs($user, 'web')->get('/master-data/business-units')) to
 * assert the actual access-denied behavior.
 *
 * "Belum ada Company" note: the current implementation
 * (resources/views/livewire/master-data/kelola-business-unit.blade.php)
 * renders only the placeholder "-- Pilih Company --" <option> when
 * companyOptions is empty — there is no distinct `disabled` attribute or
 * dedicated "create a Company first" guidance copy in the markup (mirrors
 * tests/Feature/Livewire/KelolaCompanyTest.php's equivalent known_issue for
 * screen-028). This suite asserts the actual observable behavior rather
 * than a guidance message that does not exist in the implementation.
 *
 * CRITICAL divergence from BOTH KelolaCorporateTest.php AND
 * KelolaCompanyTest.php: `code` is unique GLOBALLY (mirrors Corporate's
 * corporate_code / Company's company_code) and surfaces its validation
 * error under `form.code`, but `name` has NO uniqueness rule at all —
 * neither global nor scoped to `company_id` — so submitting a duplicate
 * name never produces a validation error here, even within the SAME
 * company (unlike Company's per-corporate_id scoping).
 *
 * CRITICAL divergence — delete-guard: BusinessUnitHasStationsException
 * fires when the business unit has related Station rows (not BusinessUnit
 * rows).
 *
 * `company_id` is a bare top-level bound property (`wire:model="company_id"`,
 * kept OUTSIDE `$form`, mirroring KelolaCompany's `corporate_id` handling)
 * — every other field (code, name, ...) is bound via `form.<field>`.
 * `logo` is also a bare top-level property (WithFileUploads), same as
 * KelolaCorporate/KelolaCompany. The filter dropdown property is
 * `filterCompanyId` (not `filterCorporateId`).
 *
 * CRITICAL environment constraint: this environment has no PHP `gd`
 * extension installed, so `UploadedFile::fake()->image(...)` throws
 * `LogicException: GD extension is not installed`. Every logo-related test
 * below therefore uses the binary-fake pattern instead — e.g.
 * `UploadedFile::fake()->create('logo.jpg', $sizeInKb, 'image/jpeg')`.
 */

use App\Enums\UserRole;
use App\Livewire\MasterData\KelolaBusinessUnit;
use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Station;
use App\Models\User;
use App\Services\BusinessUnitService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

// Scenario "Kelola Business Unit — success"
it('berhasil: picks a Company, fills a unique code+name and creates a business unit that appears in the list', function () {
    $company = Company::factory()->create(['name' => 'PT Induk Company']);

    Livewire::actingAs($this->admin)
        ->test(KelolaBusinessUnit::class)
        ->call('openCreateForm')
        ->assertSet('showForm', true)
        ->set('company_id', $company->id)
        ->set('form.code', 'BU-LW-001')
        ->set('form.name', 'Mill Unit 1')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showForm', false)
        ->assertViewHas('businessUnits', fn ($businessUnits) => collect($businessUnits)->contains(
            fn ($bu) => $bu['name'] === 'Mill Unit 1'
                && $bu['company_name'] === 'PT Induk Company'
                && $bu['station_count'] === 15
        ));

    expect(BusinessUnit::where('name', 'Mill Unit 1')->exists())->toBeTrue();
});

// Scenario "Kelola Business Unit — Edit Business Unit"
it('Edit Business Unit: loads the existing values then updates the code/name/company', function () {
    $companyA = Company::factory()->create(['name' => 'PT Awal']);
    $companyB = Company::factory()->create(['name' => 'PT Tujuan']);
    $businessUnit = BusinessUnit::factory()->create(['company_id' => $companyA->id, 'code' => 'BU-LW-002', 'name' => 'Mill Lama']);

    Livewire::actingAs($this->admin)
        ->test(KelolaBusinessUnit::class)
        ->call('openEditForm', $businessUnit->id)
        ->assertSet('editingId', $businessUnit->id)
        ->assertSet('company_id', $companyA->id)
        ->assertSet('form.code', 'BU-LW-002')
        ->assertSet('form.name', 'Mill Lama')
        ->set('company_id', $companyB->id)
        ->set('form.name', 'Mill Baru')
        ->call('save')
        ->assertHasNoErrors()
        ->assertViewHas('businessUnits', fn ($businessUnits) => collect($businessUnits)->contains(
            fn ($bu) => $bu['id'] === $businessUnit->id
                && $bu['name'] === 'Mill Baru'
                && $bu['company_name'] === 'PT Tujuan'
        ));

    expect($businessUnit->fresh()->name)->toBe('Mill Baru');
    expect($businessUnit->fresh()->company_id)->toBe($companyB->id);
});

// Scenario "Kelola Business Unit — Hapus Business Unit — berhasil"
it('Hapus Business Unit berhasil: removes the row from the list after confirmation', function () {
    $businessUnit = BusinessUnit::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaBusinessUnit::class)
        ->call('askDelete', $businessUnit->id)
        ->assertSet('confirmingDeleteId', $businessUnit->id)
        ->call('confirmDelete')
        ->assertSet('confirmingDeleteId', null)
        ->assertSet('deleteErrorMessage', null)
        ->assertViewHas('businessUnits', fn ($businessUnits) => ! collect($businessUnits)->contains(
            fn ($bu) => $bu['id'] === $businessUnit->id
        ));

    expect(BusinessUnit::find($businessUnit->id))->toBeNull();
});

// Scenario "Kelola Business Unit — Hapus Business Unit — ditolak"
it('Hapus Business Unit ditolak: shows an inline error and keeps the row when it has related stations', function () {
    $businessUnit = BusinessUnit::factory()->create();
    Station::factory()->create(['business_unit_id' => $businessUnit->id]);

    Livewire::actingAs($this->admin)
        ->test(KelolaBusinessUnit::class)
        ->call('askDelete', $businessUnit->id)
        ->call('confirmDelete')
        ->assertSet('confirmingDeleteId', null)
        ->assertSet('deleteErrorMessage', fn ($message) => str_contains($message, 'Station'))
        ->assertSee($businessUnit->name)
        ->assertViewHas('businessUnits', fn ($businessUnits) => collect($businessUnits)->contains(
            fn ($bu) => $bu['id'] === $businessUnit->id
        ));

    expect(BusinessUnit::find($businessUnit->id))->not->toBeNull();
});

// Scenario "Kelola Business Unit — Kode duplikat" (create branch)
it('Kode duplikat (create): shows a validation error under form.code and does not create a row', function () {
    $company = Company::factory()->create();
    BusinessUnit::factory()->create(['company_id' => $company->id, 'code' => 'BU-LW-003', 'name' => 'Mill Alpha']);

    Livewire::actingAs($this->admin)
        ->test(KelolaBusinessUnit::class)
        ->call('openCreateForm')
        ->set('company_id', $company->id)
        ->set('form.code', 'BU-LW-003')
        ->set('form.name', 'Mill Lain')
        ->call('save')
        ->assertHasErrors(['form.code'])
        ->assertSet('showForm', true);

    expect(BusinessUnit::where('code', 'BU-LW-003')->count())->toBe(1);
});

// Scenario "Kelola Business Unit — Kode duplikat" (edit branch)
it('Kode duplikat (edit): shows a validation error under form.code and does not update the row', function () {
    BusinessUnit::factory()->create(['code' => 'BU-LW-OTHER']);
    $target = BusinessUnit::factory()->create(['code' => 'BU-LW-TARGET']);

    Livewire::actingAs($this->admin)
        ->test(KelolaBusinessUnit::class)
        ->call('openEditForm', $target->id)
        ->set('form.code', 'BU-LW-OTHER')
        ->call('save')
        ->assertHasErrors(['form.code'])
        ->assertSet('showForm', true);

    expect($target->fresh()->code)->toBe('BU-LW-TARGET');
});

// Keep-own-code-unchanged on update must succeed (no false-positive
// uniqueness violation against self).
it('updates a business unit keeping its own code unchanged without errors', function () {
    $businessUnit = BusinessUnit::factory()->create(['code' => 'BU-LW-SELF', 'name' => 'Mill Lama']);

    Livewire::actingAs($this->admin)
        ->test(KelolaBusinessUnit::class)
        ->call('openEditForm', $businessUnit->id)
        ->set('form.name', 'Mill Baru')
        ->call('save')
        ->assertHasNoErrors();

    expect($businessUnit->fresh()->name)->toBe('Mill Baru');
    expect($businessUnit->fresh()->code)->toBe('BU-LW-SELF');
});

// CRITICAL — the key differentiator from KelolaCorporateTest.php AND
// KelolaCompanyTest.php: `name` has NO uniqueness rule at all — a
// duplicate name succeeds even within the SAME company, submitted
// successfully via the component with no validation error.
it('creates a business unit successfully with a name already used within the SAME company (no uniqueness rule on name)', function () {
    $company = Company::factory()->create();
    BusinessUnit::factory()->create(['company_id' => $company->id, 'code' => 'BU-LW-004', 'name' => 'Mill Kembar']);

    Livewire::actingAs($this->admin)
        ->test(KelolaBusinessUnit::class)
        ->call('openCreateForm')
        ->set('company_id', $company->id)
        ->set('form.code', 'BU-LW-005')
        ->set('form.name', 'Mill Kembar')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showForm', false);

    expect(BusinessUnit::where('company_id', $company->id)->where('name', 'Mill Kembar')->count())->toBe(2);
});

// Scenario "Kelola Business Unit — Company induk wajib dipilih"
it('Company wajib dipilih: shows a validation error under company_id and does not create a row', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaBusinessUnit::class)
        ->call('openCreateForm')
        ->set('form.code', 'BU-LW-NOCO')
        ->set('form.name', 'Mill Tanpa Company')
        ->call('save')
        ->assertHasErrors(['company_id'])
        ->assertSet('showForm', true);

    expect(BusinessUnit::where('name', 'Mill Tanpa Company')->exists())->toBeFalse();
});

// New: code required — leaving it blank shows a validation error under
// form.code and does not create a row.
it('Kode kosong (create): shows a validation error under form.code and does not create a row', function () {
    $company = Company::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaBusinessUnit::class)
        ->call('openCreateForm')
        ->set('company_id', $company->id)
        ->set('form.name', 'Mill Tanpa Kode')
        ->call('save')
        ->assertHasErrors(['form.code'])
        ->assertSet('showForm', true);

    expect(BusinessUnit::where('name', 'Mill Tanpa Kode')->exists())->toBeFalse();
});

// New: a valid logo upload succeeds and is stored on LOGO_DISK under
// business-unit-logos/.
it('uploads a valid logo image successfully and stores the file', function () {
    Storage::fake(BusinessUnitService::LOGO_DISK);
    $company = Company::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaBusinessUnit::class)
        ->call('openCreateForm')
        ->set('company_id', $company->id)
        ->set('form.code', 'BU-LW-LOGO-001')
        ->set('form.name', 'Mill Berlogo')
        ->set('logo', UploadedFile::fake()->create('logo.jpg', 500, 'image/jpeg'))
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showForm', false);

    $businessUnit = BusinessUnit::where('name', 'Mill Berlogo')->firstOrFail();
    expect($businessUnit->logo)->not->toBeNull();
    expect($businessUnit->logo)->toStartWith('business-unit-logos/');
    Storage::disk(BusinessUnitService::LOGO_DISK)->assertExists($businessUnit->logo);
});

// New: `logo` is optional — omitting it entirely still succeeds.
it('creates a business unit successfully when no logo is chosen', function () {
    $company = Company::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaBusinessUnit::class)
        ->call('openCreateForm')
        ->set('company_id', $company->id)
        ->set('form.code', 'BU-LW-LOGO-002')
        ->set('form.name', 'Mill Tanpa Logo')
        ->call('save')
        ->assertHasNoErrors();

    expect(BusinessUnit::where('name', 'Mill Tanpa Logo')->firstOrFail()->logo)->toBeNull();
});

// New: an oversized logo (> 2MB) -> validation error under `logo` (the
// component's top-level upload property, not `form.logo`), form stays
// open.
it('shows a validation error under logo when the file exceeds the max size', function () {
    Storage::fake(BusinessUnitService::LOGO_DISK);
    $company = Company::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaBusinessUnit::class)
        ->call('openCreateForm')
        ->set('company_id', $company->id)
        ->set('form.code', 'BU-LW-LOGO-003')
        ->set('form.name', 'Mill Logo Besar')
        ->set('logo', UploadedFile::fake()->create('logo.jpg', 3000, 'image/jpeg'))
        ->call('save')
        ->assertHasErrors(['logo'])
        ->assertSet('showForm', true);

    expect(BusinessUnit::where('name', 'Mill Logo Besar')->exists())->toBeFalse();
});

// New: a disallowed mime type -> validation error under `logo`. Also
// verifies the getLogoIsPreviewableProperty() guard does not throw
// FileNotPreviewableException on the re-render that follows the failed
// validate() call.
it('shows a validation error under logo when the file has a disallowed mime type, without the preview guard throwing', function () {
    Storage::fake(BusinessUnitService::LOGO_DISK);
    $company = Company::factory()->create();

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaBusinessUnit::class)
        ->call('openCreateForm')
        ->set('company_id', $company->id)
        ->set('form.code', 'BU-LW-LOGO-004')
        ->set('form.name', 'Mill Logo Salah Format')
        ->set('logo', UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'))
        ->call('save')
        ->assertHasErrors(['logo'])
        ->assertSet('showForm', true);

    expect($component->get('logoIsPreviewable'))->toBeFalse();

    expect(BusinessUnit::where('name', 'Mill Logo Salah Format')->exists())->toBeFalse();
});

// New: openEditForm() populates existingLogoUrl as the preview for a
// business unit that already has a stored logo.
it('populates existingLogoUrl when editing a business unit that already has a logo', function () {
    $businessUnit = BusinessUnit::factory()->create([
        'code' => 'BU-LW-LOGO-005',
        'logo' => 'business-unit-logos/existing.jpg',
    ]);

    Livewire::actingAs($this->admin)
        ->test(KelolaBusinessUnit::class)
        ->call('openEditForm', $businessUnit->id)
        ->assertSet('existingLogoUrl', fn ($url) => ! empty($url))
        ->assertSet('logo', null);
});

// New: created_by is set from the authenticated admin and surfaced in the
// rendered row.
it('sets created_by from the authenticated admin and surfaces it in the rendered row', function () {
    $company = Company::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaBusinessUnit::class)
        ->call('openCreateForm')
        ->set('company_id', $company->id)
        ->set('form.code', 'BU-LW-AUDIT-001')
        ->set('form.name', 'Mill Audit Trail')
        ->call('save')
        ->assertHasNoErrors()
        ->assertViewHas('businessUnits', fn ($businessUnits) => collect($businessUnits)->contains(
            fn ($bu) => $bu['name'] === 'Mill Audit Trail' && $bu['created_by'] === $this->admin->id
        ));

    expect(BusinessUnit::where('name', 'Mill Audit Trail')->firstOrFail()->created_by)->toBe($this->admin->id);
});

// New: optional contact/legal fields round-trip through the component.
it('accepts, persists, and returns optional contact/legal fields when provided', function () {
    $company = Company::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaBusinessUnit::class)
        ->call('openCreateForm')
        ->set('company_id', $company->id)
        ->set('form.code', 'BU-LW-FIELDS-001')
        ->set('form.name', 'Mill Lengkap')
        ->set('form.short_name', 'MLK')
        ->set('form.leader_name', 'Budi Santoso')
        ->set('form.email', 'info@milllengkap.co.id')
        ->call('save')
        ->assertHasNoErrors()
        ->assertViewHas('businessUnits', fn ($businessUnits) => collect($businessUnits)->contains(
            fn ($bu) => $bu['name'] === 'Mill Lengkap' && $bu['short_name'] === 'MLK'
        ));

    $fresh = BusinessUnit::where('name', 'Mill Lengkap')->firstOrFail();
    expect($fresh->short_name)->toBe('MLK');
    expect($fresh->email)->toBe('info@milllengkap.co.id');
});

// New: omitting every optional field does not error.
it('creates a business unit successfully when every optional field is left blank', function () {
    $company = Company::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaBusinessUnit::class)
        ->call('openCreateForm')
        ->set('company_id', $company->id)
        ->set('form.code', 'BU-LW-FIELDS-002')
        ->set('form.name', 'Mill Minimal')
        ->call('save')
        ->assertHasNoErrors();

    $fresh = BusinessUnit::where('name', 'Mill Minimal')->firstOrFail();
    expect($fresh->short_name)->toBeNull();
    expect($fresh->address)->toBeNull();
});

// Scenario "Kelola Business Unit — Belum ada Company"
it('Belum ada Company: renders an empty company dropdown when opening the create form', function () {
    Company::query()->delete();

    Livewire::actingAs($this->admin)
        ->test(KelolaBusinessUnit::class)
        ->assertViewHas('companyOptions', fn ($options) => count($options) === 0)
        ->call('openCreateForm')
        ->assertSet('showForm', true)
        ->assertSee('-- Pilih Company --');

    // Submitting with no Company selected is rejected by the same
    // company_id validation rule as any other invalid company_id.
    Livewire::actingAs($this->admin)
        ->test(KelolaBusinessUnit::class)
        ->call('openCreateForm')
        ->set('form.code', 'BU-LW-006')
        ->set('form.name', 'Mill Tanpa Company')
        ->call('save')
        ->assertHasErrors(['company_id']);
});

// Scenario "Kelola Business Unit — Akses ditolak untuk non-Admin"
// (see file-level docblock: exercised via the real HTTP route rather than
// Livewire::test(), since access control is enforced at the routing
// layer.)
it('akses ditolak: returns 403 and never renders the component for a non-admin session', function (string $role) {
    $user = User::factory()->role(UserRole::from($role))->create();

    $response = $this->actingAs($user, 'web')->get('/master-data/business-units');

    $response->assertForbidden();
    $response->assertDontSee('Kelola Business Unit');
})->with([
    'supervisor' => ['supervisor'],
    'mill management' => ['mill_management'],
    'operator' => ['operator'],
]);

// Additional coverage: filtering the list by filterCompanyId resets to
// page 1 and only shows that company's business units.
it('filters the list when filterCompanyId is set', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    BusinessUnit::factory()->create(['company_id' => $companyA->id, 'name' => 'Mill A1']);
    BusinessUnit::factory()->create(['company_id' => $companyB->id, 'name' => 'Mill B1']);

    Livewire::actingAs($this->admin)
        ->test(KelolaBusinessUnit::class)
        ->set('filterCompanyId', $companyA->id)
        ->assertViewHas('businessUnits', fn ($businessUnits) => collect($businessUnits)->pluck('name')->all() === ['Mill A1']);
});
