<?php

/**
 * CompanyServiceTest — screen-028--kelola-company /
 * usecase-028--kelola-company.
 *
 * Unit tests for App\Services\CompanyService::listCompanies() /
 * ::corporateOptions() / ::create() / ::update() / ::delete(), covering
 * the unit_test_cases derived from this screen's business_logic
 * (steps 1-5). Calls the service directly (no HTTP layer), mirroring
 * tests/Unit/Services/CorporateServiceTest.php's pragmatic deviation from
 * test_strategy.unit_test.mock_policy ("mock all I/O"): this service
 * persists/queries via Eloquent (Company::query(), no injectable
 * repository abstraction exists in this codebase), so this suite binds
 * Tests\TestCase + RefreshDatabase (sqlite in-memory, per phpunit.xml) and
 * seeds fixture data via model factories — fast/isolated in practice,
 * while exercising the real validation/query-building logic, which is the
 * behavior actually worth covering here.
 *
 * CRITICAL divergence from CorporateServiceTest.php: Company name
 * uniqueness is scoped to `corporate_id`, not global — see the "creates a
 * company successfully with a name already used under a different
 * corporate" test below, the key differentiator from screen-027's suite.
 *
 * Entity-catalog v4 rework (screen-028--kelola-company 3-tech-spec ver 2):
 * `company_code` is now a second required field alongside `name`, but
 * UNLIKE `name` it is unique GLOBALLY across the whole `companies` table
 * (mirrors CorporateService's `corporate_code`) — so every create()/
 * update() payload below includes a company_code unless the test is
 * specifically about company_code validation itself (or the call is
 * expected to fail before validate() runs, e.g. a non-existent id). Also
 * covers: the `logo` upload (CompanyService::LOGO_DISK = 'local', same
 * convention as CorporateService), the `last_update` optional date field
 * (no Corporate equivalent), created_by/updated_by auto-derivation from
 * auth()->id() (never accepted from the payload), and round-trip coverage
 * for the optional contact/legal fields.
 *
 * CRITICAL environment constraint: this environment has no PHP `gd`
 * extension installed, so `UploadedFile::fake()->image(...)` throws
 * `LogicException: GD extension is not installed`. Every logo-related test
 * below therefore uses the binary-fake pattern instead — e.g.
 * `UploadedFile::fake()->create('logo.jpg', $sizeInKb, 'image/jpeg')` —
 * exactly like CorporateServiceTest.php's just-updated logo tests.
 */

use App\Enums\UserRole;
use App\Exceptions\CompanyHasBusinessUnitsException;
use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Corporate;
use App\Models\User;
use App\Services\CompanyService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new CompanyService();
});

// unit_test_case 6: create with a non-existent corporate_id -> 422
// validation error.
it('throws a ValidationException when creating with a non-existent corporate_id', function () {
    expect(fn () => $this->service->create([
        'corporate_id' => '00000000-0000-0000-0000-000000000000',
        'company_code' => 'COMP-001',
        'name' => 'PT Anak Usaha',
    ]))->toThrow(ValidationException::class);

    expect(Company::count())->toBe(0);
});

// unit_test_case 7: create with an empty name (but a valid company_code,
// isolating the assertion to the name field) -> 422 validation error.
it('throws a ValidationException when creating with an empty name', function () {
    $corporate = Corporate::factory()->create();

    try {
        $this->service->create(['corporate_id' => $corporate->id, 'company_code' => 'COMP-002', 'name' => '']);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('name');
    }

    expect(Company::count())->toBe(0);
});

// New: create without a company_code at all -> 422 validation error.
it('throws a ValidationException when creating without a company_code', function () {
    $corporate = Corporate::factory()->create();

    try {
        $this->service->create(['corporate_id' => $corporate->id, 'name' => 'PT Tanpa Kode']);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('company_code');
    }

    expect(Company::count())->toBe(0);
});

// unit_test_case 8: create with a duplicate name within the SAME corporate
// -> 422 validation error. company_code is unique here so the failure is
// isolated to `name`.
it('throws a ValidationException when creating with a name that already exists in the same corporate', function () {
    $corporate = Corporate::factory()->create();
    Company::factory()->create(['corporate_id' => $corporate->id, 'company_code' => 'COMP-003', 'name' => 'PT Anak Usaha']);

    try {
        $this->service->create(['corporate_id' => $corporate->id, 'company_code' => 'COMP-004', 'name' => 'PT Anak Usaha']);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('name');
        expect($e->errors())->not->toHaveKey('company_code');
    }

    expect(Company::where('corporate_id', $corporate->id)->count())->toBe(1);
});

// New (CRITICAL — the key differentiator from company_code's global scope):
// create with a duplicate company_code (already exists under a DIFFERENT
// corporate, different name) -> 422 validation error isolated to
// `company_code`. company_code uniqueness is NOT scoped to corporate_id.
it('throws a ValidationException when creating with a company_code that already exists under a different corporate', function () {
    $corporateA = Corporate::factory()->create();
    $corporateB = Corporate::factory()->create();
    Company::factory()->create(['corporate_id' => $corporateA->id, 'company_code' => 'COMP-DUP', 'name' => 'PT Alpha']);

    try {
        $this->service->create(['corporate_id' => $corporateB->id, 'company_code' => 'COMP-DUP', 'name' => 'PT Beta']);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('company_code');
        expect($e->errors())->not->toHaveKey('name');
    }

    expect(Company::where('company_code', 'COMP-DUP')->count())->toBe(1);
});

// unit_test_case 9 (CRITICAL — the key differentiator from
// CorporateServiceTest.php's global uniqueness): the same name is allowed
// to exist under a DIFFERENT corporate_id — uniqueness is scoped per
// corporate, not global. (company_code, however, must still be unique
// globally, so a fresh one is supplied here.)
it('creates a company successfully with a name already used under a different corporate', function () {
    $corporateA = Corporate::factory()->create();
    $corporateB = Corporate::factory()->create();
    Company::factory()->create(['corporate_id' => $corporateA->id, 'company_code' => 'COMP-005', 'name' => 'PT Anak Usaha']);

    $result = $this->service->create(['corporate_id' => $corporateB->id, 'company_code' => 'COMP-006', 'name' => 'PT Anak Usaha']);

    expect($result['name'])->toBe('PT Anak Usaha');
    expect($result['corporate_id'])->toBe($corporateB->id);
    expect(Company::where('name', 'PT Anak Usaha')->count())->toBe(2);
});

// unit_test_case 10: update a non-existent id -> 404 not found.
it('throws a ModelNotFoundException when updating a non-existent company', function () {
    $corporate = Corporate::factory()->create();

    expect(fn () => $this->service->update('00000000-0000-0000-0000-000000000000', [
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-007',
        'name' => 'Any Name',
    ]))->toThrow(ModelNotFoundException::class);
});

// unit_test_case 11: delete a non-existent id -> 404 not found.
it('throws a ModelNotFoundException when deleting a non-existent company', function () {
    expect(fn () => $this->service->delete('00000000-0000-0000-0000-000000000000'))
        ->toThrow(ModelNotFoundException::class);
});

// unit_test_case 12: update with a name duplicate in the SAME corporate ->
// 422 validation error.
it('throws a ValidationException when updating to a name taken by another company in the same corporate', function () {
    $corporate = Corporate::factory()->create();
    Company::factory()->create(['corporate_id' => $corporate->id, 'company_code' => 'COMP-008', 'name' => 'PT Alpha']);
    $target = Company::factory()->create(['corporate_id' => $corporate->id, 'company_code' => 'COMP-009', 'name' => 'PT Beta']);

    try {
        $this->service->update($target->id, ['corporate_id' => $corporate->id, 'company_code' => 'COMP-009', 'name' => 'PT Alpha']);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('name');
    }

    expect($target->fresh()->name)->toBe('PT Beta');
});

// New: update with a company_code taken by another company under a
// DIFFERENT corporate -> 422 validation error isolated to `company_code`.
it('throws a ValidationException when updating to a company_code taken by another company under a different corporate', function () {
    $corporateA = Corporate::factory()->create();
    $corporateB = Corporate::factory()->create();
    Company::factory()->create(['corporate_id' => $corporateA->id, 'company_code' => 'COMP-OTHER']);
    $target = Company::factory()->create(['corporate_id' => $corporateB->id, 'company_code' => 'COMP-TARGET']);

    try {
        $this->service->update($target->id, [
            'corporate_id' => $corporateB->id,
            'company_code' => 'COMP-OTHER',
            'name' => $target->name,
        ]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('company_code');
    }

    expect($target->fresh()->company_code)->toBe('COMP-TARGET');
});

// New: company_code uniqueness ignores self on update — keeping a
// company's own company_code unchanged must NOT false-positive.
it('updates a company keeping its own company_code without a false-positive uniqueness error', function () {
    $corporate = Corporate::factory()->create();
    $target = Company::factory()->create(['corporate_id' => $corporate->id, 'company_code' => 'COMP-SELF', 'name' => 'PT Lama']);

    $result = $this->service->update($target->id, [
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-SELF',
        'name' => 'PT Baru',
    ]);

    expect($result['company_code'])->toBe('COMP-SELF');
    expect($result['name'])->toBe('PT Baru');
    expect($target->fresh()->name)->toBe('PT Baru');
});

// unit_test_case 13: update succeeds keeping the row's own unchanged name
// (self-exclusion from the uniqueness check works) — also keeps its own
// company_code unchanged (both rules are self-excluded simultaneously).
it('updates a company keeping its own unchanged name without a validation error', function () {
    $corporate = Corporate::factory()->create();
    $target = Company::factory()->create(['corporate_id' => $corporate->id, 'company_code' => 'COMP-010', 'name' => 'PT Tetap Sama']);

    $result = $this->service->update($target->id, [
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-010',
        'name' => 'PT Tetap Sama',
    ]);

    expect($result['name'])->toBe('PT Tetap Sama');
    expect($target->fresh()->name)->toBe('PT Tetap Sama');
});

// unit_test_case 14 (409 branch): delete a company that has related
// business units -> 409 (not deleted, row still exists after). UNCHANGED
// by the entity-catalog v4 rework.
it('throws a CompanyHasBusinessUnitsException when deleting a company that has related business units', function () {
    $company = Company::factory()->create();
    BusinessUnit::factory()->create(['company_id' => $company->id]);

    expect(fn () => $this->service->delete($company->id))
        ->toThrow(CompanyHasBusinessUnitsException::class);

    expect(Company::find($company->id))->not->toBeNull();
});

// unit_test_case 14 (happy path — delete): success — the company is
// actually removed when it has no related business units. UNCHANGED.
it('deletes a company that has no related business units', function () {
    $company = Company::factory()->create();

    $this->service->delete($company->id);

    expect(Company::find($company->id))->toBeNull();
});

// unit_test_case 14 (happy path — create): success also returns the full
// row shape, including the new fields (company_code, logo/logo_url,
// created_by/updated_by).
it('creates a company and returns the expected row shape', function () {
    $corporate = Corporate::factory()->create(['name' => 'PT Induk Jaya']);

    $result = $this->service->create([
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-011',
        'name' => 'PT Anak Usaha',
    ]);

    expect($result)->toHaveKeys([
        'id', 'company_code', 'name', 'logo', 'logo_url', 'last_update',
        'corporate_id', 'corporate_name', 'business_unit_count',
        'created_by', 'updated_by', 'created_at',
    ]);
    expect($result['company_code'])->toBe('COMP-011');
    expect($result['name'])->toBe('PT Anak Usaha');
    expect($result['corporate_id'])->toBe($corporate->id);
    expect($result['corporate_name'])->toBe('PT Induk Jaya');
    expect($result['business_unit_count'])->toBe(0);
    expect($result['logo'])->toBeNull();
    expect($result['logo_url'])->toBeNull();
    expect(Company::where('name', 'PT Anak Usaha')->exists())->toBeTrue();
});

// unit_test_case 14 (happy path — update): success — returns the expected
// row shape with the updated name.
it('updates a company and returns the expected row shape', function () {
    $corporate = Corporate::factory()->create();
    $company = Company::factory()->create(['corporate_id' => $corporate->id, 'company_code' => 'COMP-012', 'name' => 'PT Lama']);

    $result = $this->service->update($company->id, [
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-012',
        'name' => 'PT Baru',
    ]);

    expect($result)->toHaveKeys([
        'id', 'company_code', 'name', 'corporate_id', 'corporate_name', 'business_unit_count', 'created_at',
    ]);
    expect($result['id'])->toBe($company->id);
    expect($result['name'])->toBe('PT Baru');
    expect($company->fresh()->name)->toBe('PT Baru');
});

// unit_test_case 1: paginated list returns rows with corporate_name +
// business_unit_count populated correctly (business_logic step 1).
// UNCHANGED.
it('lists companies paginated with corporate_name and business_unit_count per row', function () {
    $corporate = Corporate::factory()->create(['name' => 'PT Induk Jaya']);
    $company = Company::factory()->create(['corporate_id' => $corporate->id, 'name' => 'PT Anak Usaha']);
    BusinessUnit::factory()->count(2)->create(['company_id' => $company->id]);
    Company::factory()->create(['corporate_id' => $corporate->id, 'name' => 'PT Anak Lain']);

    $result = $this->service->listCompanies(1, 20);

    expect($result['meta'])->toBe([
        'page' => 1,
        'per_page' => 20,
        'total' => 2,
        'total_pages' => 1,
    ]);
    expect($result['data'])->toHaveCount(2);

    $row = collect($result['data'])->firstWhere('name', 'PT Anak Usaha');
    expect($row['corporate_name'])->toBe('PT Induk Jaya');
    expect($row['business_unit_count'])->toBe(2);
});

// unit_test_case 2: list filtered by corporate_id query param returns only
// that corporate's companies (business_logic step 1, optional filter).
// UNCHANGED.
it('filters the list by corporate_id when provided', function () {
    $corporateA = Corporate::factory()->create();
    $corporateB = Corporate::factory()->create();
    Company::factory()->create(['corporate_id' => $corporateA->id, 'name' => 'PT A1']);
    Company::factory()->create(['corporate_id' => $corporateA->id, 'name' => 'PT A2']);
    Company::factory()->create(['corporate_id' => $corporateB->id, 'name' => 'PT B1']);

    $result = $this->service->listCompanies(1, 20, $corporateA->id);

    expect($result['meta']['total'])->toBe(2);
    expect(collect($result['data'])->pluck('name')->sort()->values()->all())->toBe(['PT A1', 'PT A2']);
});

// unit_test_case 4: corporateOptions() returns a populated list, ordered
// by name, when corporates exist (business_logic step 2). UNCHANGED.
it('returns a populated corporate options list ordered by name', function () {
    Corporate::factory()->create(['name' => 'PT Zulu']);
    Corporate::factory()->create(['name' => 'PT Alpha']);

    $result = $this->service->corporateOptions();

    expect(collect($result)->pluck('name')->all())->toBe(['PT Alpha', 'PT Zulu']);
    expect($result[0])->toHaveKeys(['id', 'name']);
});

// unit_test_case 5: corporateOptions() returns an empty list when no
// corporates exist. UNCHANGED.
it('returns an empty corporate options list when no corporates exist', function () {
    $result = $this->service->corporateOptions();

    expect($result)->toBe([]);
});

// New: a valid logo upload is stored on LOGO_DISK under company-logos/ and
// surfaced as logo_url.
it('stores an uploaded logo file under company-logos/ and returns a logo_url in the row', function () {
    Storage::fake(CompanyService::LOGO_DISK);
    $corporate = Corporate::factory()->create();
    $logo = UploadedFile::fake()->create('logo.jpg', 500, 'image/jpeg');

    $result = $this->service->create([
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-LOGO-001',
        'name' => 'PT Berlogo',
    ], $logo);

    expect($result['logo'])->not->toBeNull();
    expect($result['logo'])->toStartWith('company-logos/');
    expect($result['logo_url'])->not->toBeNull();
    Storage::disk(CompanyService::LOGO_DISK)->assertExists($result['logo']);
});

// New: `logo` is optional — omitting it entirely still succeeds.
it('creates a company without a logo successfully', function () {
    $corporate = Corporate::factory()->create();

    $result = $this->service->create([
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-LOGO-002',
        'name' => 'PT Tanpa Logo',
    ]);

    expect($result['logo'])->toBeNull();
    expect($result['logo_url'])->toBeNull();
});

// New: an oversized logo (> 2MB / max:2048) -> 422 errors.logo, nothing
// persisted.
it('throws a ValidationException when the logo file exceeds the max size', function () {
    Storage::fake(CompanyService::LOGO_DISK);
    $corporate = Corporate::factory()->create();
    $logo = UploadedFile::fake()->create('logo.jpg', 3000, 'image/jpeg');

    try {
        $this->service->create([
            'corporate_id' => $corporate->id,
            'company_code' => 'COMP-LOGO-003',
            'name' => 'PT Logo Besar',
        ], $logo);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('logo');
    }

    expect(Company::count())->toBe(0);
});

// New: a disallowed mime type (e.g. PDF, only jpg/jpeg/png are accepted)
// -> 422 errors.logo, nothing persisted.
it('throws a ValidationException when the logo file has a disallowed mime type', function () {
    Storage::fake(CompanyService::LOGO_DISK);
    $corporate = Corporate::factory()->create();
    $logo = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    try {
        $this->service->create([
            'corporate_id' => $corporate->id,
            'company_code' => 'COMP-LOGO-004',
            'name' => 'PT Logo Pdf',
        ], $logo);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('logo');
    }

    expect(Company::count())->toBe(0);
});

// New: a new logo upload on update() replaces the stored logo path.
it('replaces the stored logo on update when a new file is provided', function () {
    Storage::fake(CompanyService::LOGO_DISK);
    $corporate = Corporate::factory()->create();
    $company = Company::factory()->create([
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-LOGO-005',
        'logo' => 'company-logos/old.jpg',
    ]);
    $newLogo = UploadedFile::fake()->create('new-logo.png', 400, 'image/png');

    $result = $this->service->update($company->id, [
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-LOGO-005',
        'name' => $company->name,
    ], $newLogo);

    expect($result['logo'])->not->toBe('company-logos/old.jpg');
    Storage::disk(CompanyService::LOGO_DISK)->assertExists($result['logo']);
});

// New: update() without a new logo argument (null) leaves the existing
// stored `logo` column untouched.
it('leaves the existing logo untouched when updating without a new logo file', function () {
    $corporate = Corporate::factory()->create();
    $company = Company::factory()->create([
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-LOGO-006',
        'logo' => 'company-logos/keep.jpg',
    ]);

    $result = $this->service->update($company->id, [
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-LOGO-006',
        'name' => 'PT Nama Baru',
    ]);

    expect($result['logo'])->toBe('company-logos/keep.jpg');
});

// New: last_update accepts a valid date and round-trips through the row.
it('accepts a valid last_update date and returns it in the row', function () {
    $corporate = Corporate::factory()->create();

    $result = $this->service->create([
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-DATE-001',
        'name' => 'PT Tanggal Valid',
        'last_update' => '2026-08-01',
    ]);

    expect($result['last_update'])->toBe('2026-08-01');
    expect(Company::where('name', 'PT Tanggal Valid')->firstOrFail()->last_update->toDateString())->toBe('2026-08-01');
});

// New: last_update rejects an invalid (non-date) value -> 422 errors.last_update.
it('throws a ValidationException when last_update is not a valid date', function () {
    $corporate = Corporate::factory()->create();

    try {
        $this->service->create([
            'corporate_id' => $corporate->id,
            'company_code' => 'COMP-DATE-002',
            'name' => 'PT Tanggal Salah',
            'last_update' => 'bukan-tanggal',
        ]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('last_update');
    }

    expect(Company::where('name', 'PT Tanggal Salah')->exists())->toBeFalse();
});

// New: created_by is always derived from the authenticated user, never
// accepted from the payload even when spoofed.
it('sets created_by from the authenticated user and ignores a spoofed created_by in the payload', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $spoofed = User::factory()->role(UserRole::Admin)->create();
    $corporate = Corporate::factory()->create();

    $this->actingAs($admin, 'web');

    $result = $this->service->create([
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-AUDIT-001',
        'name' => 'PT Audit Trail',
        'created_by' => $spoofed->id,
    ]);

    expect($result['created_by'])->toBe($admin->id);
    expect($result['created_by'])->not->toBe($spoofed->id);
    expect(Company::where('name', 'PT Audit Trail')->firstOrFail()->created_by)->toBe($admin->id);
});

// New: updated_by is always derived from the authenticated user, never
// accepted from the payload even when spoofed.
it('sets updated_by from the authenticated user and ignores a spoofed updated_by in the payload', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $spoofed = User::factory()->role(UserRole::Admin)->create();
    $corporate = Corporate::factory()->create();
    $company = Company::factory()->create([
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-AUDIT-002',
        'name' => 'PT Lama Sekali',
    ]);

    $this->actingAs($admin, 'web');

    $result = $this->service->update($company->id, [
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-AUDIT-002',
        'name' => 'PT Baru Sekali',
        'updated_by' => $spoofed->id,
    ]);

    expect($result['updated_by'])->toBe($admin->id);
    expect($result['updated_by'])->not->toBe($spoofed->id);
    expect($company->fresh()->updated_by)->toBe($admin->id);
});

// New: optional contact/legal fields are accepted, persisted, and returned
// when provided on create.
it('accepts, persists, and returns the optional contact/legal fields when provided on create', function () {
    $corporate = Corporate::factory()->create();

    $result = $this->service->create([
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-FIELDS-001',
        'name' => 'PT Lengkap',
        'short_name' => 'PSM',
        'leader_name' => 'Budi Santoso',
        'email' => 'info@ptlengkap.co.id',
        'telephone_no' => '021-5551234',
        'labor_union' => 'Serikat Pekerja PT Lengkap',
    ]);

    expect($result['short_name'])->toBe('PSM');
    expect($result['leader_name'])->toBe('Budi Santoso');
    expect($result['email'])->toBe('info@ptlengkap.co.id');
    expect($result['telephone_no'])->toBe('021-5551234');
    expect($result['labor_union'])->toBe('Serikat Pekerja PT Lengkap');

    $fresh = Company::where('name', 'PT Lengkap')->firstOrFail();
    expect($fresh->short_name)->toBe('PSM');
    expect($fresh->labor_union)->toBe('Serikat Pekerja PT Lengkap');
});

// New: omitting every optional field does not error, and they save as
// null (normalizeTextFields()'s "" -> null behaviour for OPTIONAL_TEXT_FIELDS).
it('creates a company successfully when every optional field is omitted', function () {
    $corporate = Corporate::factory()->create();

    $result = $this->service->create([
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-FIELDS-002',
        'name' => 'PT Minimal',
    ]);

    expect($result['short_name'])->toBeNull();
    expect($result['address'])->toBeNull();
    expect($result['labor_union'])->toBeNull();
});

// New: optional fields round-trip correctly through update() too.
it('accepts, persists, and returns the optional fields when updated', function () {
    $corporate = Corporate::factory()->create();
    $company = Company::factory()->create([
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-FIELDS-003',
        'address' => null,
        'website' => null,
    ]);

    $result = $this->service->update($company->id, [
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-FIELDS-003',
        'name' => $company->name,
        'address' => 'Jl. Industri No. 1',
        'website' => 'https://ptlengkap.co.id',
    ]);

    expect($result['address'])->toBe('Jl. Industri No. 1');
    expect($result['website'])->toBe('https://ptlengkap.co.id');
    expect($company->fresh()->address)->toBe('Jl. Industri No. 1');
});
