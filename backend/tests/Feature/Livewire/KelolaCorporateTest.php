<?php

/**
 * KelolaCorporateTest (Feature/Livewire) — screen-027--kelola-corporate /
 * usecase-027--kelola-corporate.
 *
 * Component tests for App\Livewire\MasterData\KelolaCorporate, one per
 * test_scenarios' component_test step (scenarios 1-5). Uses
 * Livewire::actingAs($user)->test() (mirrors tests/Feature/Livewire/
 * ChangePasswordFormTest.php) — the component itself requires an
 * authenticated session ($confirmingDeleteId etc. plus app(CorporateService
 * ::class) calls), independent of the route-level role guard.
 *
 * Scenario 6 ("akses ditolak untuk selain Admin") deviates from
 * Livewire::test()'s usual mount-a-component-directly harness: per this
 * component's own docblock, access control for this screen is enforced
 * entirely at the routing layer ('auth' + 'role:admin' in routes/web.php,
 * EnsureRole::forbidden() -> abort(403)) — Livewire::test(KelolaCorporate::
 * class) instantiates the component directly and does not run route
 * middleware, so it cannot observe that guard. This scenario instead
 * exercises the real HTTP route ($this->actingAs($user, 'web')->get(
 * '/master-data/corporates')) to assert the actual access-denied behavior:
 * 403, and (implicitly, since the component never mounts when the
 * middleware short-circuits first) no list/controls are ever rendered.
 *
 * Entity-catalog v4 rework (screen-027--kelola-corporate 3-tech-spec ver 2):
 * the form is now a single `$form` array (bound via `wire:model="form.
 * <field>"`) instead of a single `name` property, so every ->set()/
 * ->assertSet() below targets `form.<field>` (e.g. `form.corporate_code`,
 * `form.name`) rather than the old bare `name`. `corporate_code` is a
 * second required+unique field alongside `name`, so create/edit flows below
 * always set both unless the test is specifically about corporate_code
 * validation. Also covers the `logo` file upload (WithFileUploads — set via
 * ->set('logo', UploadedFile::fake()->image(...)), the standard Livewire
 * testing pattern for WithFileUploads components; this is the first
 * WithFileUploads component in this codebase, so no established in-repo
 * precedent existed to follow — see known_issues) and created_by/updated_by
 * surfacing through the rendered row.
 */

use App\Enums\UserRole;
use App\Livewire\MasterData\KelolaCorporate;
use App\Models\Company;
use App\Models\Corporate;
use App\Models\User;
use App\Services\CorporateService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->role(UserRole::Admin)->create();
});

// Scenario 1: "Kelola Corporate — success"
it('berhasil: creates a corporate that appears in the list with company_count 0', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaCorporate::class)
        ->call('openCreateForm')
        ->assertSet('showForm', true)
        ->set('form.corporate_code', 'COR-LW-001')
        ->set('form.name', 'PT Sawit Makmur Jaya')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showForm', false)
        ->assertViewHas('corporates', fn ($corporates) => collect($corporates)->contains(
            fn ($c) => $c['name'] === 'PT Sawit Makmur Jaya' && $c['company_count'] === 0
        ));

    expect(Corporate::where('name', 'PT Sawit Makmur Jaya')->exists())->toBeTrue();
});

// Scenario 2: "Kelola Corporate — Edit Corporate"
it('Edit Corporate: loads the existing fields then updates the name in the component state', function () {
    $corporate = Corporate::factory()->create(['corporate_code' => 'COR-LW-002', 'name' => 'PT Lama Jaya']);

    Livewire::actingAs($this->admin)
        ->test(KelolaCorporate::class)
        ->call('openEditForm', $corporate->id)
        ->assertSet('editingId', $corporate->id)
        ->assertSet('form.corporate_code', 'COR-LW-002')
        ->assertSet('form.name', 'PT Lama Jaya')
        ->set('form.name', 'PT Baru Sentosa')
        ->call('save')
        ->assertHasNoErrors()
        ->assertViewHas('corporates', fn ($corporates) => collect($corporates)->contains(
            fn ($c) => $c['id'] === $corporate->id && $c['name'] === 'PT Baru Sentosa'
        ));

    expect($corporate->fresh()->name)->toBe('PT Baru Sentosa');
});

// Scenario 3: "Kelola Corporate — Hapus Corporate — berhasil"
it('Hapus Corporate berhasil: removes the row from the list after confirmation', function () {
    $corporate = Corporate::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaCorporate::class)
        ->call('askDelete', $corporate->id)
        ->assertSet('confirmingDeleteId', $corporate->id)
        ->call('confirmDelete')
        ->assertSet('confirmingDeleteId', null)
        ->assertSet('deleteErrorMessage', null)
        ->assertViewHas('corporates', fn ($corporates) => ! collect($corporates)->contains(
            fn ($c) => $c['id'] === $corporate->id
        ));

    expect(Corporate::find($corporate->id))->toBeNull();
});

// Scenario 4: "Kelola Corporate — Hapus Corporate — ditolak"
it('Hapus Corporate ditolak: shows an error and keeps the row when it has related companies', function () {
    $corporate = Corporate::factory()->create();
    Company::factory()->create(['corporate_id' => $corporate->id]);

    Livewire::actingAs($this->admin)
        ->test(KelolaCorporate::class)
        ->call('askDelete', $corporate->id)
        ->call('confirmDelete')
        ->assertSet('confirmingDeleteId', null)
        ->assertSet('deleteErrorMessage', fn ($message) => str_contains($message, 'Company'))
        ->assertSee($corporate->name)
        ->assertViewHas('corporates', fn ($corporates) => collect($corporates)->contains(
            fn ($c) => $c['id'] === $corporate->id
        ));

    expect(Corporate::find($corporate->id))->not->toBeNull();
});

// Scenario 5: "Kelola Corporate — Nama duplikat" (create branch)
it('Nama duplikat (create): shows a validation error under form.name and does not create a row', function () {
    Corporate::factory()->create(['name' => 'PT Sawit Makmur Jaya']);

    Livewire::actingAs($this->admin)
        ->test(KelolaCorporate::class)
        ->call('openCreateForm')
        ->set('form.corporate_code', 'COR-LW-003')
        ->set('form.name', 'PT Sawit Makmur Jaya')
        ->call('save')
        ->assertHasErrors(['form.name'])
        ->assertSet('showForm', true);

    expect(Corporate::where('name', 'PT Sawit Makmur Jaya')->count())->toBe(1);
});

// Scenario 5: "Kelola Corporate — Nama duplikat" (edit branch)
it('Nama duplikat (edit): shows a validation error under form.name and does not update the row', function () {
    Corporate::factory()->create(['name' => 'PT Alpha']);
    $target = Corporate::factory()->create(['name' => 'PT Beta', 'corporate_code' => 'COR-LW-004']);

    Livewire::actingAs($this->admin)
        ->test(KelolaCorporate::class)
        ->call('openEditForm', $target->id)
        ->set('form.name', 'PT Alpha')
        ->call('save')
        ->assertHasErrors(['form.name'])
        ->assertSet('showForm', true);

    expect($target->fresh()->name)->toBe('PT Beta');
});

// New: corporate_code is required — leaving it blank shows a validation
// error under form.corporate_code and does not create a row.
it('Kode kosong (create): shows a validation error under form.corporate_code and does not create a row', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaCorporate::class)
        ->call('openCreateForm')
        ->set('form.name', 'PT Tanpa Kode')
        ->call('save')
        ->assertHasErrors(['form.corporate_code'])
        ->assertSet('showForm', true);

    expect(Corporate::where('name', 'PT Tanpa Kode')->exists())->toBeFalse();
});

// New: duplicate corporate_code (create branch) -> error under
// form.corporate_code.
it('Kode duplikat (create): shows a validation error under form.corporate_code and does not create a row', function () {
    Corporate::factory()->create(['corporate_code' => 'COR-LW-DUP']);

    Livewire::actingAs($this->admin)
        ->test(KelolaCorporate::class)
        ->call('openCreateForm')
        ->set('form.corporate_code', 'COR-LW-DUP')
        ->set('form.name', 'PT Nama Berbeda')
        ->call('save')
        ->assertHasErrors(['form.corporate_code'])
        ->assertSet('showForm', true);

    expect(Corporate::where('name', 'PT Nama Berbeda')->exists())->toBeFalse();
});

// New: duplicate corporate_code (edit branch) -> error under
// form.corporate_code, target row left untouched.
it('Kode duplikat (edit): shows a validation error under form.corporate_code and does not update the row', function () {
    Corporate::factory()->create(['corporate_code' => 'COR-LW-OTHER']);
    $target = Corporate::factory()->create(['corporate_code' => 'COR-LW-TARGET']);

    Livewire::actingAs($this->admin)
        ->test(KelolaCorporate::class)
        ->call('openEditForm', $target->id)
        ->set('form.corporate_code', 'COR-LW-OTHER')
        ->call('save')
        ->assertHasErrors(['form.corporate_code'])
        ->assertSet('showForm', true);

    expect($target->fresh()->corporate_code)->toBe('COR-LW-TARGET');
});

// New: updating a corporate while keeping its own corporate_code unchanged
// must succeed (no false-positive uniqueness violation against self).
it('updates a corporate keeping its own corporate_code unchanged without errors', function () {
    $corporate = Corporate::factory()->create(['corporate_code' => 'COR-LW-SELF', 'name' => 'PT Lama']);

    Livewire::actingAs($this->admin)
        ->test(KelolaCorporate::class)
        ->call('openEditForm', $corporate->id)
        ->set('form.name', 'PT Baru')
        ->call('save')
        ->assertHasNoErrors();

    expect($corporate->fresh()->name)->toBe('PT Baru');
    expect($corporate->fresh()->corporate_code)->toBe('COR-LW-SELF');
});

// New: a valid logo upload succeeds and is stored on LOGO_DISK.
it('uploads a valid logo image successfully and stores the file', function () {
    Storage::fake(CorporateService::LOGO_DISK);

    Livewire::actingAs($this->admin)
        ->test(KelolaCorporate::class)
        ->call('openCreateForm')
        ->set('form.corporate_code', 'COR-LW-LOGO-001')
        ->set('form.name', 'PT Berlogo')
        ->set('logo', UploadedFile::fake()->create('logo.jpg', 500, 'image/jpeg'))
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showForm', false);

    $corporate = Corporate::where('name', 'PT Berlogo')->firstOrFail();
    expect($corporate->logo)->not->toBeNull();
    Storage::disk(CorporateService::LOGO_DISK)->assertExists($corporate->logo);
});

// New: `logo` is optional — omitting it entirely still succeeds.
it('creates a corporate successfully when no logo is chosen', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaCorporate::class)
        ->call('openCreateForm')
        ->set('form.corporate_code', 'COR-LW-LOGO-002')
        ->set('form.name', 'PT Tanpa Logo')
        ->call('save')
        ->assertHasNoErrors();

    expect(Corporate::where('name', 'PT Tanpa Logo')->firstOrFail()->logo)->toBeNull();
});

// New: an oversized logo (> 2MB) -> validation error under `logo` (the
// component's top-level upload property, not `form.logo`), form stays open.
it('shows a validation error under logo when the file exceeds the max size', function () {
    Storage::fake(CorporateService::LOGO_DISK);

    Livewire::actingAs($this->admin)
        ->test(KelolaCorporate::class)
        ->call('openCreateForm')
        ->set('form.corporate_code', 'COR-LW-LOGO-003')
        ->set('form.name', 'PT Logo Besar')
        ->set('logo', UploadedFile::fake()->create('logo.jpg', 3000, 'image/jpeg'))
        ->call('save')
        ->assertHasErrors(['logo'])
        ->assertSet('showForm', true);

    expect(Corporate::where('name', 'PT Logo Besar')->exists())->toBeFalse();
});

// New: a disallowed mime type -> validation error under `logo`.
it('shows a validation error under logo when the file has a disallowed mime type', function () {
    Storage::fake(CorporateService::LOGO_DISK);

    Livewire::actingAs($this->admin)
        ->test(KelolaCorporate::class)
        ->call('openCreateForm')
        ->set('form.corporate_code', 'COR-LW-LOGO-004')
        ->set('form.name', 'PT Logo Salah Format')
        ->set('logo', UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'))
        ->call('save')
        ->assertHasErrors(['logo'])
        ->assertSet('showForm', true);

    expect(Corporate::where('name', 'PT Logo Salah Format')->exists())->toBeFalse();
});

// New: openEditForm() populates existingLogoUrl as the preview for a
// corporate that already has a stored logo.
it('populates existingLogoUrl when editing a corporate that already has a logo', function () {
    $corporate = Corporate::factory()->create([
        'corporate_code' => 'COR-LW-LOGO-005',
        'logo' => 'corporate-logos/existing.jpg',
    ]);

    Livewire::actingAs($this->admin)
        ->test(KelolaCorporate::class)
        ->call('openEditForm', $corporate->id)
        ->assertSet('existingLogoUrl', fn ($url) => ! empty($url))
        ->assertSet('logo', null);
});

// New: created_by/updated_by are set from the authenticated admin and
// surfaced in the rendered row.
it('sets created_by from the authenticated admin and surfaces it in the rendered row', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaCorporate::class)
        ->call('openCreateForm')
        ->set('form.corporate_code', 'COR-LW-AUDIT-001')
        ->set('form.name', 'PT Audit Trail')
        ->call('save')
        ->assertHasNoErrors()
        ->assertViewHas('corporates', fn ($corporates) => collect($corporates)->contains(
            fn ($c) => $c['name'] === 'PT Audit Trail' && $c['created_by'] === $this->admin->id
        ));

    expect(Corporate::where('name', 'PT Audit Trail')->firstOrFail()->created_by)->toBe($this->admin->id);
});

// New: optional contact/legal fields round-trip through the component —
// set via form.<field>, persisted, and reflected in the rendered row.
it('accepts, persists, and returns optional contact/legal fields when provided', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaCorporate::class)
        ->call('openCreateForm')
        ->set('form.corporate_code', 'COR-LW-FIELDS-001')
        ->set('form.name', 'PT Lengkap')
        ->set('form.short_name', 'PSM')
        ->set('form.leader_name', 'Budi Santoso')
        ->set('form.email', 'info@ptlengkap.co.id')
        ->call('save')
        ->assertHasNoErrors()
        ->assertViewHas('corporates', fn ($corporates) => collect($corporates)->contains(
            fn ($c) => $c['name'] === 'PT Lengkap' && $c['short_name'] === 'PSM' && $c['leader_name'] === 'Budi Santoso'
        ));

    $fresh = Corporate::where('name', 'PT Lengkap')->firstOrFail();
    expect($fresh->short_name)->toBe('PSM');
    expect($fresh->email)->toBe('info@ptlengkap.co.id');
});

// New: omitting every optional field does not error.
it('creates a corporate successfully when every optional field is left blank', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaCorporate::class)
        ->call('openCreateForm')
        ->set('form.corporate_code', 'COR-LW-FIELDS-002')
        ->set('form.name', 'PT Minimal')
        ->call('save')
        ->assertHasNoErrors();

    $fresh = Corporate::where('name', 'PT Minimal')->firstOrFail();
    expect($fresh->short_name)->toBeNull();
    expect($fresh->address)->toBeNull();
});

// Scenario 6: "Kelola Corporate — akses ditolak untuk selain Admin"
// (see file-level docblock: exercised via the real HTTP route rather than
// Livewire::test(), since access control is enforced at the routing layer.)
it('akses ditolak: returns 403 and never renders the component for a non-admin session', function (string $role) {
    $user = User::factory()->role(UserRole::from($role))->create();

    $response = $this->actingAs($user, 'web')->get('/master-data/corporates');

    $response->assertForbidden();
    $response->assertDontSee('Kelola Corporate');
})->with([
    'supervisor' => ['supervisor'],
    'mill management' => ['mill_management'],
    'operator' => ['operator'],
]);
