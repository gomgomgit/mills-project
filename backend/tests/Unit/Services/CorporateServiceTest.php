<?php

/**
 * CorporateServiceTest — screen-027--kelola-corporate /
 * usecase-027--kelola-corporate.
 *
 * Unit tests for App\Services\CorporateService::listCorporates() /
 * ::create() / ::update() / ::delete(), covering the unit_test_cases
 * derived from this screen's business_logic (steps 1-4). Calls the service
 * directly (no HTTP layer), mirroring tests/Unit/Services/
 * WeighbridgeRecordServiceTest.php's pragmatic deviation from
 * test_strategy.unit_test.mock_policy ("mock all I/O"): this service
 * persists/queries via Eloquent (Corporate::query(), no injectable
 * repository abstraction exists in this codebase), so this suite binds
 * Tests\TestCase + RefreshDatabase (sqlite in-memory, per phpunit.xml) and
 * seeds fixture data via model factories — fast/isolated in practice,
 * while exercising the real validation/query-building logic, which is the
 * behavior actually worth covering here.
 *
 * Entity-catalog v4 rework (screen-027--kelola-corporate 3-tech-spec ver 2):
 * `corporate_code` is now a second required+unique field alongside `name`
 * (see CorporateService::validate()), so every create()/update() payload
 * below includes a corporate_code unless the test is specifically about
 * corporate_code validation itself. Also covers: the `logo` upload
 * (CorporateService::LOGO_DISK = 'local', confirmed by reading
 * app/Services/CorporateService.php directly — matches config/filesystems.php's
 * FILESYSTEM_DISK default), created_by/updated_by auto-derivation from
 * auth()->id() (never accepted from the payload), and round-trip coverage
 * for the new optional contact/legal fields.
 */

use App\Enums\UserRole;
use App\Exceptions\CorporateHasCompaniesException;
use App\Models\Company;
use App\Models\Corporate;
use App\Models\User;
use App\Services\CorporateService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new CorporateService();
});

// unit_test_case 1: create with an empty name (but a valid corporate_code,
// isolating the assertion to the name field) -> 422 validation error.
it('throws a ValidationException when creating with an empty name', function () {
    try {
        $this->service->create(['corporate_code' => 'COR-001', 'name' => '']);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('name');
    }

    expect(Corporate::count())->toBe(0);
});

// New: create without a corporate_code at all -> 422 validation error.
it('throws a ValidationException when creating without a corporate_code', function () {
    try {
        $this->service->create(['name' => 'PT Tanpa Kode']);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('corporate_code');
    }

    expect(Corporate::count())->toBe(0);
});

// unit_test_case 2: create with a duplicate name (already exists) -> 422
// validation error. corporate_code is unique here so the failure is
// isolated to `name`.
it('throws a ValidationException when creating with a name that already exists', function () {
    Corporate::factory()->create(['name' => 'PT Sawit Makmur Jaya']);

    try {
        $this->service->create(['corporate_code' => 'COR-NEW-001', 'name' => 'PT Sawit Makmur Jaya']);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('name');
        expect($e->errors())->not->toHaveKey('corporate_code');
    }

    expect(Corporate::count())->toBe(1);
});

// New: create with a duplicate corporate_code (already exists, different
// name) -> 422 validation error isolated to `corporate_code`.
it('throws a ValidationException when creating with a corporate_code that already exists', function () {
    Corporate::factory()->create(['corporate_code' => 'COR-DUP']);

    try {
        $this->service->create(['corporate_code' => 'COR-DUP', 'name' => 'PT Nama Berbeda']);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('corporate_code');
        expect($e->errors())->not->toHaveKey('name');
    }

    expect(Corporate::count())->toBe(1);
});

// unit_test_case 3: update a non-existent id -> 404 not found.
it('throws a ModelNotFoundException when updating a non-existent corporate', function () {
    expect(fn () => $this->service->update('00000000-0000-0000-0000-000000000000', ['corporate_code' => 'COR-ANY', 'name' => 'Any Name']))
        ->toThrow(ModelNotFoundException::class);
});

// unit_test_case 4: update with a name taken by another existing corporate
// -> 422 validation error.
it('throws a ValidationException when updating to a name taken by another corporate', function () {
    Corporate::factory()->create(['name' => 'PT Alpha']);
    $target = Corporate::factory()->create(['name' => 'PT Beta', 'corporate_code' => 'COR-TARGET']);

    try {
        $this->service->update($target->id, ['corporate_code' => 'COR-TARGET', 'name' => 'PT Alpha']);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('name');
    }

    expect($target->fresh()->name)->toBe('PT Beta');
});

// New: update with a corporate_code taken by another existing corporate ->
// 422 validation error.
it('throws a ValidationException when updating to a corporate_code taken by another corporate', function () {
    Corporate::factory()->create(['corporate_code' => 'COR-OTHER']);
    $target = Corporate::factory()->create(['corporate_code' => 'COR-TARGET']);

    try {
        $this->service->update($target->id, ['corporate_code' => 'COR-OTHER', 'name' => $target->name]);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('corporate_code');
    }

    expect($target->fresh()->corporate_code)->toBe('COR-TARGET');
});

// New: updating a corporate while keeping its own corporate_code unchanged
// must NOT false-positive on the unique rule (Rule::unique(...)->ignore()
// excluding self — business_logic step 3).
it('updates a corporate keeping its own corporate_code without a false-positive uniqueness error', function () {
    $corporate = Corporate::factory()->create(['corporate_code' => 'COR-SELF', 'name' => 'PT Lama']);

    $result = $this->service->update($corporate->id, ['corporate_code' => 'COR-SELF', 'name' => 'PT Baru']);

    expect($result['corporate_code'])->toBe('COR-SELF');
    expect($result['name'])->toBe('PT Baru');
    expect($corporate->fresh()->name)->toBe('PT Baru');
});

// unit_test_case 5: delete a non-existent id -> 404 not found.
it('throws a ModelNotFoundException when deleting a non-existent corporate', function () {
    expect(fn () => $this->service->delete('00000000-0000-0000-0000-000000000000'))
        ->toThrow(ModelNotFoundException::class);
});

// unit_test_case 6: delete a corporate that has related Company rows ->
// 409 (not deleted, row still exists after). UNCHANGED by the
// entity-catalog v4 rework.
it('throws a CorporateHasCompaniesException when deleting a corporate that has related companies', function () {
    $corporate = Corporate::factory()->create();
    Company::factory()->create(['corporate_id' => $corporate->id]);

    expect(fn () => $this->service->delete($corporate->id))
        ->toThrow(CorporateHasCompaniesException::class);

    expect(Corporate::find($corporate->id))->not->toBeNull();
});

// unit_test_case 7 (happy path — create): success — returns the expected
// success object, including the new fields (corporate_code, logo/logo_url,
// created_by/updated_by).
it('creates a corporate and returns the expected row shape', function () {
    $result = $this->service->create([
        'corporate_code' => 'COR-100',
        'name' => 'PT Sawit Makmur Jaya',
    ]);

    expect($result)->toHaveKeys([
        'id', 'corporate_code', 'name', 'logo', 'logo_url',
        'company_count', 'created_by', 'updated_by', 'created_at',
    ]);
    expect($result['corporate_code'])->toBe('COR-100');
    expect($result['name'])->toBe('PT Sawit Makmur Jaya');
    expect($result['company_count'])->toBe(0);
    expect($result['logo'])->toBeNull();
    expect($result['logo_url'])->toBeNull();
    expect(Corporate::where('name', 'PT Sawit Makmur Jaya')->exists())->toBeTrue();
});

// unit_test_case 7 (happy path — update): success — returns the expected
// success object with the updated name.
it('updates a corporate and returns the expected row shape', function () {
    $corporate = Corporate::factory()->create(['name' => 'PT Lama', 'corporate_code' => 'COR-200']);

    $result = $this->service->update($corporate->id, ['corporate_code' => 'COR-200', 'name' => 'PT Baru']);

    expect($result)->toHaveKeys(['id', 'corporate_code', 'name', 'company_count', 'created_at']);
    expect($result['id'])->toBe($corporate->id);
    expect($result['name'])->toBe('PT Baru');
    expect($corporate->fresh()->name)->toBe('PT Baru');
});

// unit_test_case 7 (happy path — delete): success — the corporate is
// actually removed when it has no related companies. UNCHANGED.
it('deletes a corporate that has no related companies', function () {
    $corporate = Corporate::factory()->create();

    $this->service->delete($corporate->id);

    expect(Corporate::find($corporate->id))->toBeNull();
});

// Additional coverage: listCorporates() paginates and reports company_count
// per row (business_logic step 1), used by both entry points.
it('lists corporates paginated with company_count per row', function () {
    $withCompanies = Corporate::factory()->create(['name' => 'PT Alpha']);
    Company::factory()->count(2)->create(['corporate_id' => $withCompanies->id]);
    Corporate::factory()->create(['name' => 'PT Beta']);

    $result = $this->service->listCorporates(1, 20);

    expect($result['meta'])->toBe([
        'page' => 1,
        'per_page' => 20,
        'total' => 2,
        'total_pages' => 1,
    ]);
    expect($result['data'])->toHaveCount(2);

    $alphaRow = collect($result['data'])->firstWhere('name', 'PT Alpha');
    expect($alphaRow['company_count'])->toBe(2);
});

// New: created_by is always derived from the authenticated user, never
// accepted from the payload even when spoofed.
it('sets created_by from the authenticated user and ignores a spoofed created_by in the payload', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $spoofed = User::factory()->role(UserRole::Admin)->create();

    $this->actingAs($admin, 'web');

    $result = $this->service->create([
        'corporate_code' => 'COR-300',
        'name' => 'PT Audit Trail',
        'created_by' => $spoofed->id,
    ]);

    expect($result['created_by'])->toBe($admin->id);
    expect($result['created_by'])->not->toBe($spoofed->id);
    expect(Corporate::where('name', 'PT Audit Trail')->firstOrFail()->created_by)->toBe($admin->id);
});

// New: updated_by is always derived from the authenticated user, never
// accepted from the payload even when spoofed.
it('sets updated_by from the authenticated user and ignores a spoofed updated_by in the payload', function () {
    $admin = User::factory()->role(UserRole::Admin)->create();
    $spoofed = User::factory()->role(UserRole::Admin)->create();
    $corporate = Corporate::factory()->create(['corporate_code' => 'COR-400', 'name' => 'PT Lama Sekali']);

    $this->actingAs($admin, 'web');

    $result = $this->service->update($corporate->id, [
        'corporate_code' => 'COR-400',
        'name' => 'PT Baru Sekali',
        'updated_by' => $spoofed->id,
    ]);

    expect($result['updated_by'])->toBe($admin->id);
    expect($result['updated_by'])->not->toBe($spoofed->id);
    expect($corporate->fresh()->updated_by)->toBe($admin->id);
});

// New: a valid logo upload is stored on LOGO_DISK and surfaced as logo_url.
it('stores an uploaded logo file and returns a logo_url in the row', function () {
    Storage::fake(CorporateService::LOGO_DISK);
    $logo = UploadedFile::fake()->create('logo.jpg', 500, 'image/jpeg');

    $result = $this->service->create([
        'corporate_code' => 'COR-500',
        'name' => 'PT Berlogo',
    ], $logo);

    expect($result['logo'])->not->toBeNull();
    expect($result['logo_url'])->not->toBeNull();
    Storage::disk(CorporateService::LOGO_DISK)->assertExists($result['logo']);
});

// New: `logo` is optional — omitting it entirely still succeeds.
it('creates a corporate without a logo successfully', function () {
    $result = $this->service->create([
        'corporate_code' => 'COR-600',
        'name' => 'PT Tanpa Logo',
    ]);

    expect($result['logo'])->toBeNull();
    expect($result['logo_url'])->toBeNull();
});

// New: an oversized logo (> 2MB / max:2048) -> 422 errors.logo, nothing
// persisted.
it('throws a ValidationException when the logo file exceeds the max size', function () {
    Storage::fake(CorporateService::LOGO_DISK);
    $logo = UploadedFile::fake()->create('logo.jpg', 3000, 'image/jpeg');

    try {
        $this->service->create([
            'corporate_code' => 'COR-700',
            'name' => 'PT Logo Besar',
        ], $logo);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('logo');
    }

    expect(Corporate::count())->toBe(0);
});

// New: a disallowed mime type (e.g. PDF, only jpg/jpeg/png are accepted)
// -> 422 errors.logo, nothing persisted.
it('throws a ValidationException when the logo file has a disallowed mime type', function () {
    Storage::fake(CorporateService::LOGO_DISK);
    $logo = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    try {
        $this->service->create([
            'corporate_code' => 'COR-800',
            'name' => 'PT Logo Pdf',
        ], $logo);
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('logo');
    }

    expect(Corporate::count())->toBe(0);
});

// New: a new logo upload on update() replaces the stored logo path.
it('replaces the stored logo on update when a new file is provided', function () {
    Storage::fake(CorporateService::LOGO_DISK);
    $corporate = Corporate::factory()->create([
        'corporate_code' => 'COR-900',
        'logo' => 'corporate-logos/old.jpg',
    ]);
    $newLogo = UploadedFile::fake()->create('new-logo.png', 400, 'image/png');

    $result = $this->service->update($corporate->id, [
        'corporate_code' => 'COR-900',
        'name' => $corporate->name,
    ], $newLogo);

    expect($result['logo'])->not->toBe('corporate-logos/old.jpg');
    Storage::disk(CorporateService::LOGO_DISK)->assertExists($result['logo']);
});

// New: update() without a new logo argument (null) leaves the existing
// stored `logo` column untouched.
it('leaves the existing logo untouched when updating without a new logo file', function () {
    $corporate = Corporate::factory()->create([
        'corporate_code' => 'COR-901',
        'logo' => 'corporate-logos/keep.jpg',
    ]);

    $result = $this->service->update($corporate->id, [
        'corporate_code' => 'COR-901',
        'name' => 'PT Nama Baru',
    ]);

    expect($result['logo'])->toBe('corporate-logos/keep.jpg');
});

// New: optional contact/legal fields are accepted, persisted, and returned
// when provided.
it('accepts, persists, and returns the optional contact/legal fields when provided on create', function () {
    $result = $this->service->create([
        'corporate_code' => 'COR-1000',
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

    $fresh = Corporate::where('name', 'PT Lengkap')->firstOrFail();
    expect($fresh->short_name)->toBe('PSM');
    expect($fresh->labor_union)->toBe('Serikat Pekerja PT Lengkap');
});

// New: omitting every optional field does not error, and they save as
// null (normalizeTextFields()'s "" -> null behaviour for OPTIONAL_TEXT_FIELDS).
it('creates a corporate successfully when every optional field is omitted', function () {
    $result = $this->service->create([
        'corporate_code' => 'COR-1100',
        'name' => 'PT Minimal',
    ]);

    expect($result['short_name'])->toBeNull();
    expect($result['address'])->toBeNull();
    expect($result['labor_union'])->toBeNull();
});

// New: optional fields round-trip correctly through update() too.
it('accepts, persists, and returns the optional fields when updated', function () {
    $corporate = Corporate::factory()->create([
        'corporate_code' => 'COR-1200',
        'address' => null,
        'website' => null,
    ]);

    $result = $this->service->update($corporate->id, [
        'corporate_code' => 'COR-1200',
        'name' => $corporate->name,
        'address' => 'Jl. Industri No. 1',
        'website' => 'https://ptlengkap.co.id',
    ]);

    expect($result['address'])->toBe('Jl. Industri No. 1');
    expect($result['website'])->toBe('https://ptlengkap.co.id');
    expect($corporate->fresh()->address)->toBe('Jl. Industri No. 1');
});
