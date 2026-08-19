<?php

/**
 * BusinessUnitServiceTest — screen-029--kelola-business-unit /
 * usecase-029--kelola-business-unit.
 *
 * Unit tests for App\Services\BusinessUnitService::listBusinessUnits() /
 * ::companyOptions() / ::create() / ::update() / ::delete(), covering the
 * unit_test_cases derived from this screen's business_logic. Calls the
 * service directly (no HTTP layer), mirroring tests/Unit/Services/
 * CorporateServiceTest.php / CompanyServiceTest.php's pragmatic deviation
 * from test_strategy.unit_test.mock_policy ("mock all I/O"): this service
 * persists/queries via Eloquent (BusinessUnit::query(), no injectable
 * repository abstraction exists in this codebase), so this suite binds
 * Tests\TestCase + RefreshDatabase (sqlite in-memory, per phpunit.xml) and
 * seeds fixture data via model factories.
 *
 * CRITICAL divergence from BOTH CorporateServiceTest.php AND
 * CompanyServiceTest.php: `code` is unique GLOBALLY (mirrors Corporate's
 * corporate_code / Company's company_code), but `name` has NO uniqueness
 * rule AT ALL — neither global (Corporate) nor scoped to the parent
 * (Company's per-corporate_id rule). The "creates successfully with a
 * name already used" test below proves this, and unlike Company's
 * equivalent test it succeeds even WITHIN THE SAME company, not just
 * across a different parent.
 *
 * CRITICAL environment constraint: this environment has no PHP `gd`
 * extension installed, so `UploadedFile::fake()->image(...)` throws
 * `LogicException: GD extension is not installed`. Every logo-related test
 * below therefore uses the binary-fake pattern instead — e.g.
 * `UploadedFile::fake()->create('logo.jpg', $sizeInKb, 'image/jpeg')` —
 * exactly like CorporateServiceTest.php / CompanyServiceTest.php's logo
 * tests.
 */

use App\Enums\UserRole;
use App\Exceptions\BusinessUnitHasStationsException;
use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Station;
use App\Models\User;
use App\Services\BusinessUnitService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new BusinessUnitService();
});

// create with a non-existent company_id -> 422 validation error.
it('throws a ValidationException when creating with a non-existent company_id', function () {
    expect(fn () => $this->service->create([
        'company_id' => '00000000-0000-0000-0000-000000000000',
        'code' => 'BU-001',
        'name' => 'Mill Unit',
    ]))->toThrow(ValidationException::class);

    expect(BusinessUnit::count())->toBe(0);
});

// create without a company_id at all -> 422 validation error under
// company_id.
it('throws a ValidationException when creating without a company_id', function () {
    try {
        $this->service->create(['code' => 'BU-002', 'name' => 'Mill Unit']);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('company_id');
    }

    expect(BusinessUnit::count())->toBe(0);
});

// create with an empty name (but a valid company_id + code, isolating the
// assertion to the name field) -> 422 validation error.
it('throws a ValidationException when creating with an empty name', function () {
    $company = Company::factory()->create();

    try {
        $this->service->create(['company_id' => $company->id, 'code' => 'BU-003', 'name' => '']);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('name');
    }

    expect(BusinessUnit::count())->toBe(0);
});

// create without a code at all -> 422 validation error under code.
it('throws a ValidationException when creating without a code', function () {
    $company = Company::factory()->create();

    try {
        $this->service->create(['company_id' => $company->id, 'name' => 'Mill Tanpa Kode']);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('code');
    }

    expect(BusinessUnit::count())->toBe(0);
});

// CRITICAL: create with a duplicate code (already exists, GLOBALLY, any
// company, different name) -> 422 validation error isolated to `code`.
it('throws a ValidationException when creating with a code that already exists under any company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    BusinessUnit::factory()->create(['company_id' => $companyA->id, 'code' => 'BU-DUP', 'name' => 'Mill Alpha']);

    try {
        $this->service->create(['company_id' => $companyB->id, 'code' => 'BU-DUP', 'name' => 'Mill Beta']);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('code');
        expect($e->errors())->not->toHaveKey('name');
    }

    expect(BusinessUnit::where('code', 'BU-DUP')->count())->toBe(1);
});

// CRITICAL — the key differentiator from both CorporateServiceTest.php AND
// CompanyServiceTest.php: `name` has NO uniqueness rule at all. Unlike
// Company (scoped to corporate_id), the identical name is allowed even
// WITHIN THE SAME company here.
it('creates successfully with a name already used by another business unit in the SAME company (no uniqueness rule on name)', function () {
    $company = Company::factory()->create();
    BusinessUnit::factory()->create(['company_id' => $company->id, 'code' => 'BU-004', 'name' => 'Mill Unit']);

    $result = $this->service->create(['company_id' => $company->id, 'code' => 'BU-005', 'name' => 'Mill Unit']);

    expect($result['name'])->toBe('Mill Unit');
    expect($result['company_id'])->toBe($company->id);
    expect(BusinessUnit::where('company_id', $company->id)->where('name', 'Mill Unit')->count())->toBe(2);
});

// Same, but under a DIFFERENT company — also no conflict.
it('creates successfully with a name already used by another business unit under a DIFFERENT company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    BusinessUnit::factory()->create(['company_id' => $companyA->id, 'code' => 'BU-006', 'name' => 'Mill Unit']);

    $result = $this->service->create(['company_id' => $companyB->id, 'code' => 'BU-007', 'name' => 'Mill Unit']);

    expect($result['name'])->toBe('Mill Unit');
    expect($result['company_id'])->toBe($companyB->id);
    expect(BusinessUnit::where('name', 'Mill Unit')->count())->toBe(2);
});

// update a non-existent id -> 404 not found.
it('throws a ModelNotFoundException when updating a non-existent business unit', function () {
    $company = Company::factory()->create();

    expect(fn () => $this->service->update('00000000-0000-0000-0000-000000000000', [
        'company_id' => $company->id,
        'code' => 'BU-ANY',
        'name' => 'Any Name',
    ]))->toThrow(ModelNotFoundException::class);
});

// delete a non-existent id -> 404 not found.
it('throws a ModelNotFoundException when deleting a non-existent business unit', function () {
    expect(fn () => $this->service->delete('00000000-0000-0000-0000-000000000000'))
        ->toThrow(ModelNotFoundException::class);
});

// update with a code taken by another business unit (any company) -> 422
// validation error isolated to `code`.
it('throws a ValidationException when updating to a code taken by another business unit', function () {
    BusinessUnit::factory()->create(['code' => 'BU-OTHER']);
    $target = BusinessUnit::factory()->create(['code' => 'BU-TARGET']);

    try {
        $this->service->update($target->id, [
            'company_id' => $target->company_id,
            'code' => 'BU-OTHER',
            'name' => $target->name,
        ]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('code');
    }

    expect($target->fresh()->code)->toBe('BU-TARGET');
});

// Keep-own-code-unchanged on update must NOT false-positive the unique
// rule (Rule::unique(...)->ignore() excluding self).
it('updates a business unit keeping its own code without a false-positive uniqueness error', function () {
    $businessUnit = BusinessUnit::factory()->create(['code' => 'BU-SELF', 'name' => 'Mill Lama']);

    $result = $this->service->update($businessUnit->id, [
        'company_id' => $businessUnit->company_id,
        'code' => 'BU-SELF',
        'name' => 'Mill Baru',
    ]);

    expect($result['code'])->toBe('BU-SELF');
    expect($result['name'])->toBe('Mill Baru');
    expect($businessUnit->fresh()->name)->toBe('Mill Baru');
});

// update with a duplicate name (no uniqueness rule) succeeds -- the
// critical divergence proven again on the update() path.
it('updates successfully with a name already used by another business unit (no uniqueness rule on name)', function () {
    $company = Company::factory()->create();
    BusinessUnit::factory()->create(['company_id' => $company->id, 'code' => 'BU-008', 'name' => 'Mill Kembar']);
    $target = BusinessUnit::factory()->create(['company_id' => $company->id, 'code' => 'BU-009', 'name' => 'Mill Beda']);

    $result = $this->service->update($target->id, [
        'company_id' => $company->id,
        'code' => 'BU-009',
        'name' => 'Mill Kembar',
    ]);

    expect($result['name'])->toBe('Mill Kembar');
    expect($target->fresh()->name)->toBe('Mill Kembar');
});

// Delete-guard: deleting a business unit that has related Station rows ->
// 409 (not deleted, row still exists after).
it('throws a BusinessUnitHasStationsException when deleting a business unit that has related stations', function () {
    $businessUnit = BusinessUnit::factory()->create();
    Station::factory()->create(['business_unit_id' => $businessUnit->id]);

    expect(fn () => $this->service->delete($businessUnit->id))
        ->toThrow(BusinessUnitHasStationsException::class);

    expect(BusinessUnit::find($businessUnit->id))->not->toBeNull();
});

// Happy path — delete: success, the business unit is actually removed
// when it has no related stations.
it('deletes a business unit that has no related stations', function () {
    $businessUnit = BusinessUnit::factory()->create();

    $this->service->delete($businessUnit->id);

    expect(BusinessUnit::find($businessUnit->id))->toBeNull();
});

// Happy path — create: success, returns the expected row shape.
it('creates a business unit and returns the expected row shape', function () {
    $company = Company::factory()->create(['name' => 'PT Induk Company']);

    $result = $this->service->create([
        'company_id' => $company->id,
        'code' => 'BU-100',
        'name' => 'Mill Unit 1',
    ]);

    expect($result)->toHaveKeys([
        'id', 'code', 'name', 'logo', 'logo_url',
        'company_id', 'company_name', 'station_count',
        'created_by', 'updated_by', 'created_at',
    ]);
    expect($result['code'])->toBe('BU-100');
    expect($result['name'])->toBe('Mill Unit 1');
    expect($result['company_id'])->toBe($company->id);
    expect($result['company_name'])->toBe('PT Induk Company');
    expect($result['station_count'])->toBe(0);
    expect($result['logo'])->toBeNull();
    expect($result['logo_url'])->toBeNull();
    expect(BusinessUnit::where('name', 'Mill Unit 1')->exists())->toBeTrue();
});

// Happy path — update: success, returns the expected row shape with the
// updated name.
it('updates a business unit and returns the expected row shape', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create(['name' => 'PT Company Tujuan']);
    $businessUnit = BusinessUnit::factory()->create(['company_id' => $companyA->id, 'code' => 'BU-200', 'name' => 'Mill Lama']);

    $result = $this->service->update($businessUnit->id, [
        'company_id' => $companyB->id,
        'code' => 'BU-200',
        'name' => 'Mill Baru',
    ]);

    expect($result)->toHaveKeys(['id', 'code', 'name', 'company_id', 'company_name', 'station_count', 'created_at']);
    expect($result['id'])->toBe($businessUnit->id);
    expect($result['name'])->toBe('Mill Baru');
    expect($result['company_id'])->toBe($companyB->id);
    expect($result['company_name'])->toBe('PT Company Tujuan');
    expect($businessUnit->fresh()->name)->toBe('Mill Baru');
    expect($businessUnit->fresh()->company_id)->toBe($companyB->id);
});

// listBusinessUnits(): paginated list returns rows with company_name +
// station_count per row.
it('lists business units paginated with company_name and station_count per row', function () {
    $company = Company::factory()->create(['name' => 'PT Induk Jaya']);
    $businessUnit = BusinessUnit::factory()->create(['company_id' => $company->id, 'name' => 'Mill Alpha']);
    Station::factory()->count(2)->create(['business_unit_id' => $businessUnit->id]);
    BusinessUnit::factory()->create(['company_id' => $company->id, 'name' => 'Mill Beta']);

    $result = $this->service->listBusinessUnits(1, 20);

    expect($result['meta'])->toBe([
        'page' => 1,
        'per_page' => 20,
        'total' => 2,
        'total_pages' => 1,
    ]);
    expect($result['data'])->toHaveCount(2);

    $row = collect($result['data'])->firstWhere('name', 'Mill Alpha');
    expect($row['company_name'])->toBe('PT Induk Jaya');
    expect($row['station_count'])->toBe(2);
});

// listBusinessUnits(): list filtered by company_id query param returns
// only that company's business units.
it('filters the list by company_id when provided', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    BusinessUnit::factory()->create(['company_id' => $companyA->id, 'name' => 'Mill A1']);
    BusinessUnit::factory()->create(['company_id' => $companyA->id, 'name' => 'Mill A2']);
    BusinessUnit::factory()->create(['company_id' => $companyB->id, 'name' => 'Mill B1']);

    $result = $this->service->listBusinessUnits(1, 20, $companyA->id);

    expect($result['meta']['total'])->toBe(2);
    expect(collect($result['data'])->pluck('name')->sort()->values()->all())->toBe(['Mill A1', 'Mill A2']);
});

// companyOptions(): returns a populated list, ordered by name, when
// companies exist.
it('returns a populated company options list ordered by name', function () {
    Company::factory()->create(['name' => 'PT Zulu']);
    Company::factory()->create(['name' => 'PT Alpha']);

    $result = $this->service->companyOptions();

    expect(collect($result)->pluck('name')->all())->toBe(['PT Alpha', 'PT Zulu']);
    expect($result[0])->toHaveKeys(['id', 'name']);
});

// companyOptions(): "No Company exists yet" edge case -- returns an empty
// list when no Company rows exist.
it('returns an empty company options list when no companies exist', function () {
    $result = $this->service->companyOptions();

    expect($result)->toBe([]);
});

// created_by is always derived from the authenticated user, never accepted
// from the payload even when spoofed.
it('sets created_by from the authenticated user and ignores a spoofed created_by in the payload', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $spoofed = User::factory()->role(UserRole::Admin)->create();
    $company = Company::factory()->create();

    $this->actingAs($admin, 'web');

    $result = $this->service->create([
        'company_id' => $company->id,
        'code' => 'BU-300',
        'name' => 'Mill Audit Trail',
        'created_by' => $spoofed->id,
    ]);

    expect($result['created_by'])->toBe($admin->id);
    expect($result['created_by'])->not->toBe($spoofed->id);
    expect(BusinessUnit::where('name', 'Mill Audit Trail')->firstOrFail()->created_by)->toBe($admin->id);
});

// updated_by is always derived from the authenticated user, never accepted
// from the payload even when spoofed.
it('sets updated_by from the authenticated user and ignores a spoofed updated_by in the payload', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $spoofed = User::factory()->role(UserRole::Admin)->create();
    $company = Company::factory()->create();
    $businessUnit = BusinessUnit::factory()->create(['company_id' => $company->id, 'code' => 'BU-400', 'name' => 'Mill Lama Sekali']);

    $this->actingAs($admin, 'web');

    $result = $this->service->update($businessUnit->id, [
        'company_id' => $company->id,
        'code' => 'BU-400',
        'name' => 'Mill Baru Sekali',
        'updated_by' => $spoofed->id,
    ]);

    expect($result['updated_by'])->toBe($admin->id);
    expect($result['updated_by'])->not->toBe($spoofed->id);
    expect($businessUnit->fresh()->updated_by)->toBe($admin->id);
});

// A valid logo upload is stored on LOGO_DISK and surfaced as logo_url.
it('stores an uploaded logo file under business-unit-logos/ and returns a logo_url in the row', function () {
    Storage::fake(BusinessUnitService::LOGO_DISK);
    $company = Company::factory()->create();
    $logo = UploadedFile::fake()->create('logo.jpg', 500, 'image/jpeg');

    $result = $this->service->create([
        'company_id' => $company->id,
        'code' => 'BU-500',
        'name' => 'Mill Berlogo',
    ], $logo);

    expect($result['logo'])->not->toBeNull();
    expect($result['logo'])->toStartWith('business-unit-logos/');
    expect($result['logo_url'])->not->toBeNull();
    Storage::disk(BusinessUnitService::LOGO_DISK)->assertExists($result['logo']);
});

// `logo` is optional -- omitting it entirely still succeeds with a null
// logo.
it('creates a business unit without a logo successfully', function () {
    $company = Company::factory()->create();

    $result = $this->service->create([
        'company_id' => $company->id,
        'code' => 'BU-600',
        'name' => 'Mill Tanpa Logo',
    ]);

    expect($result['logo'])->toBeNull();
    expect($result['logo_url'])->toBeNull();
});

// An oversized logo (> 2MB / max:2048) -> 422 errors.logo, nothing
// persisted.
it('throws a ValidationException when the logo file exceeds the max size', function () {
    Storage::fake(BusinessUnitService::LOGO_DISK);
    $company = Company::factory()->create();
    $logo = UploadedFile::fake()->create('logo.jpg', 3000, 'image/jpeg');

    try {
        $this->service->create([
            'company_id' => $company->id,
            'code' => 'BU-700',
            'name' => 'Mill Logo Besar',
        ], $logo);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('logo');
    }

    expect(BusinessUnit::count())->toBe(0);
});

// A disallowed mime type (e.g. PDF, only jpg/jpeg/png are accepted) -> 422
// errors.logo, nothing persisted.
it('throws a ValidationException when the logo file has a disallowed mime type', function () {
    Storage::fake(BusinessUnitService::LOGO_DISK);
    $company = Company::factory()->create();
    $logo = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    try {
        $this->service->create([
            'company_id' => $company->id,
            'code' => 'BU-800',
            'name' => 'Mill Logo Pdf',
        ], $logo);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('logo');
    }

    expect(BusinessUnit::count())->toBe(0);
});

// A new logo upload on update() replaces the stored logo path.
it('replaces the stored logo on update when a new file is provided', function () {
    Storage::fake(BusinessUnitService::LOGO_DISK);
    $company = Company::factory()->create();
    $businessUnit = BusinessUnit::factory()->create([
        'company_id' => $company->id,
        'code' => 'BU-900',
        'logo' => 'business-unit-logos/old.jpg',
    ]);
    $newLogo = UploadedFile::fake()->create('new-logo.png', 400, 'image/png');

    $result = $this->service->update($businessUnit->id, [
        'company_id' => $company->id,
        'code' => 'BU-900',
        'name' => $businessUnit->name,
    ], $newLogo);

    expect($result['logo'])->not->toBe('business-unit-logos/old.jpg');
    Storage::disk(BusinessUnitService::LOGO_DISK)->assertExists($result['logo']);
});

// update() without a new logo argument (null) leaves the existing stored
// `logo` column untouched.
it('leaves the existing logo untouched when updating without a new logo file', function () {
    $company = Company::factory()->create();
    $businessUnit = BusinessUnit::factory()->create([
        'company_id' => $company->id,
        'code' => 'BU-901',
        'logo' => 'business-unit-logos/keep.jpg',
    ]);

    $result = $this->service->update($businessUnit->id, [
        'company_id' => $company->id,
        'code' => 'BU-901',
        'name' => 'Mill Nama Baru',
    ]);

    expect($result['logo'])->toBe('business-unit-logos/keep.jpg');
});

// Optional contact/legal fields are accepted, persisted, and returned when
// provided on create.
it('accepts, persists, and returns the optional contact/legal fields when provided on create', function () {
    $company = Company::factory()->create();

    $result = $this->service->create([
        'company_id' => $company->id,
        'code' => 'BU-1000',
        'name' => 'Mill Lengkap',
        'short_name' => 'MLK',
        'leader_name' => 'Budi Santoso',
        'email' => 'info@milllengkap.co.id',
        'telephone_no' => '021-5551234',
        'labor_union' => 'Serikat Pekerja Mill Lengkap',
    ]);

    expect($result['short_name'])->toBe('MLK');
    expect($result['leader_name'])->toBe('Budi Santoso');
    expect($result['email'])->toBe('info@milllengkap.co.id');
    expect($result['telephone_no'])->toBe('021-5551234');
    expect($result['labor_union'])->toBe('Serikat Pekerja Mill Lengkap');

    $fresh = BusinessUnit::where('name', 'Mill Lengkap')->firstOrFail();
    expect($fresh->short_name)->toBe('MLK');
    expect($fresh->labor_union)->toBe('Serikat Pekerja Mill Lengkap');
});

// Omitting every optional field does not error, and they save as null
// (normalizeTextFields()'s "" -> null behaviour for OPTIONAL_TEXT_FIELDS).
it('creates a business unit successfully when every optional field is omitted', function () {
    $company = Company::factory()->create();

    $result = $this->service->create([
        'company_id' => $company->id,
        'code' => 'BU-1100',
        'name' => 'Mill Minimal',
    ]);

    expect($result['short_name'])->toBeNull();
    expect($result['address'])->toBeNull();
    expect($result['labor_union'])->toBeNull();
});

// Optional fields round-trip correctly through update() too.
it('accepts, persists, and returns the optional fields when updated', function () {
    $company = Company::factory()->create();
    $businessUnit = BusinessUnit::factory()->create([
        'company_id' => $company->id,
        'code' => 'BU-1200',
        'address' => null,
        'website' => null,
    ]);

    $result = $this->service->update($businessUnit->id, [
        'company_id' => $company->id,
        'code' => 'BU-1200',
        'name' => $businessUnit->name,
        'address' => 'Jl. Industri No. 1',
        'website' => 'https://milllengkap.co.id',
    ]);

    expect($result['address'])->toBe('Jl. Industri No. 1');
    expect($result['website'])->toBe('https://milllengkap.co.id');
    expect($businessUnit->fresh()->address)->toBe('Jl. Industri No. 1');
});
