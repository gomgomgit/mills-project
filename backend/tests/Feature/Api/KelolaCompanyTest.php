<?php

/**
 * KelolaCompanyTest (Feature/Api) — screen-028--kelola-company /
 * usecase-028--kelola-company.
 *
 * Integration tests for GET/POST/PATCH/DELETE /api/companies and
 * GET /api/corporates/options (App\Http\Controllers\Api\
 * CompanyController), one per test_scenarios' api_test step(s). Exercises
 * the real route -> 'auth:web' + 'role:admin' middleware -> controller ->
 * CompanyService -> Eloquent chain against the sqlite in-memory testing DB
 * (RefreshDatabase, bound in tests/Pest.php for the Feature suite).
 * Mirrors tests/Feature/Api/KelolaCorporateTest.php exactly.
 *
 * Session auth: authenticated via $this->actingAs($user, 'web') (mirrors
 * KelolaCorporateTest.php) — matches config/auth.php's 'web' session
 * guard, the same guard this screen's routes are gated by ('auth:web' in
 * routes/api.php).
 *
 * Response shape note: shared_decisions.error_format is
 * `{ "message": ..., "errors": {...} }` — ApiExceptionHandler only adds
 * `errors` for 422 ValidationException responses; 404 (ModelNotFoundException)
 * and 409 (CompanyHasBusinessUnitsException, a plain HttpException) render
 * as `{ "message": ... }` only.
 *
 * CRITICAL divergence from KelolaCorporateTest.php: Company name
 * uniqueness is scoped to `corporate_id`, not global — the "cross
 * corporate" test below is the key differentiator from screen-027's suite.
 *
 * Entity-catalog v4 rework (screen-028--kelola-company 3-tech-spec ver 2):
 * `company_code` is now a second required field alongside `name`, but
 * UNLIKE `name` it is unique GLOBALLY across the whole `companies` table
 * (mirrors CorporateService's `corporate_code`) — so every create()/
 * update() payload below includes a company_code unless the test is
 * specifically about company_code validation (or expected to fail/short-
 * circuit before validate() runs). Also covers the `logo` upload
 * (Storage::fake(CompanyService::LOGO_DISK) — confirmed to be 'local' by
 * reading app/Services/CompanyService.php directly), the `last_update`
 * optional date field, and created_by/updated_by auto-derivation. Requests
 * here use ->post()/->patch() (not postJson()/patchJson()) whenever a
 * `logo` UploadedFile is part of the payload, since a JSON body cannot
 * carry a file upload; ApiExceptionHandler still returns JSON error
 * responses for these because it routes by `$request->is('api/*')` in
 * addition to `$request->expectsJson()` (see its own docblock), not by
 * request Content-Type.
 *
 * CRITICAL environment constraint: this environment has no PHP `gd`
 * extension installed, so `UploadedFile::fake()->image(...)` throws
 * `LogicException: GD extension is not installed`. Every logo-related test
 * below therefore uses the binary-fake pattern instead — e.g.
 * `UploadedFile::fake()->create('logo.jpg', $sizeInKb, 'image/jpeg')` —
 * exactly like KelolaCorporateTest.php's just-updated logo tests.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Corporate;
use App\Models\User;
use App\Services\CompanyService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Shared business unit for all beforeEach users (mirrors
    // tests/Feature/Api/KelolaCorporateTest.php) — without this, each
    // User::factory() call's default business_unit_id => BusinessUnit::factory()
    // cascades through BusinessUnitFactory's default company_id =>
    // Company::factory() down to CompanyFactory's default corporate_id =>
    // Corporate::factory(), creating one incidental Corporate + Company row
    // per user and inflating any meta.total assertion below.
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->admin = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->businessUnit)->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnit)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnit)->create();
});

// Scenario 1: "Kelola Company — success"
it('berhasil: loads corporate options then creates a company, returns 201 with the expected row shape', function () {
    $corporate = Corporate::factory()->create(['name' => 'PT Induk Jaya']);

    $optionsResponse = $this->actingAs($this->admin, 'web')->getJson('/api/corporates/options');
    $optionsResponse->assertOk();
    $optionsResponse->assertJsonFragment(['id' => $corporate->id, 'name' => 'PT Induk Jaya']);

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/companies', [
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-API-001',
        'name' => 'PT Anak Usaha',
    ]);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'id', 'company_code', 'name', 'logo', 'logo_url', 'last_update',
        'corporate_id', 'corporate_name', 'business_unit_count',
        'created_by', 'updated_by', 'created_at',
    ]);
    $response->assertJson([
        'company_code' => 'COMP-API-001',
        'name' => 'PT Anak Usaha',
        'corporate_id' => $corporate->id,
        'corporate_name' => 'PT Induk Jaya',
        'business_unit_count' => 0,
    ]);

    expect(Company::where('name', 'PT Anak Usaha')->exists())->toBeTrue();
});

// Scenario 2: "Kelola Company — Edit Company"
it('Edit Company: updates the name and corporate then returns 200 with the updated row', function () {
    $corporateA = Corporate::factory()->create(['name' => 'PT Awal']);
    $corporateB = Corporate::factory()->create(['name' => 'PT Tujuan']);
    $company = Company::factory()->create(['corporate_id' => $corporateA->id, 'company_code' => 'COMP-API-002', 'name' => 'PT Lama Jaya']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/companies/{$company->id}", [
        'corporate_id' => $corporateB->id,
        'company_code' => 'COMP-API-002',
        'name' => 'PT Baru Sentosa',
    ]);

    $response->assertOk();
    $response->assertJson([
        'id' => $company->id,
        'name' => 'PT Baru Sentosa',
        'corporate_id' => $corporateB->id,
        'corporate_name' => 'PT Tujuan',
    ]);

    expect($company->fresh()->name)->toBe('PT Baru Sentosa');
    expect($company->fresh()->corporate_id)->toBe($corporateB->id);
});

// Scenario 3: "Kelola Company — Hapus Company — berhasil"
it('Hapus Company berhasil: deletes a company with no related business units, returns 200', function () {
    $company = Company::factory()->create();

    $response = $this->actingAs($this->admin, 'web')->deleteJson("/api/companies/{$company->id}");

    $response->assertOk();
    $response->assertExactJson(['deleted' => true]);

    expect(Company::find($company->id))->toBeNull();
});

// Scenario 4: "Kelola Company — Hapus Company — ditolak"
it('Hapus Company ditolak: returns 409 COMPANY_HAS_BUSINESS_UNITS and keeps the row when it has related business units', function () {
    $company = Company::factory()->create();
    BusinessUnit::factory()->create(['company_id' => $company->id]);

    $response = $this->actingAs($this->admin, 'web')->deleteJson("/api/companies/{$company->id}");

    $response->assertStatus(409);
    $response->assertJsonStructure(['message']);
    $response->assertJsonMissing(['errors']);
    expect($response->json('message'))->toContain('Business Unit');

    expect(Company::find($company->id))->not->toBeNull();
});

// Scenario 5: "Kelola Company — Nama duplikat dalam Corporate yang sama"
// (create branch)
it('Nama duplikat (create): returns 422 with errors.name when the name already exists in the same corporate', function () {
    $corporate = Corporate::factory()->create();
    Company::factory()->create(['corporate_id' => $corporate->id, 'company_code' => 'COMP-API-003', 'name' => 'PT Anak Usaha']);

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/companies', [
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-API-004',
        'name' => 'PT Anak Usaha',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name']);
    $response->assertJsonMissingValidationErrors(['company_code']);
});

// Scenario 5: "Kelola Company — Nama duplikat dalam Corporate yang sama"
// (edit branch)
it('Nama duplikat (edit): returns 422 with errors.name when the name is taken by another company in the same corporate', function () {
    $corporate = Corporate::factory()->create();
    Company::factory()->create(['corporate_id' => $corporate->id, 'company_code' => 'COMP-API-005', 'name' => 'PT Alpha']);
    $target = Company::factory()->create(['corporate_id' => $corporate->id, 'company_code' => 'COMP-API-006', 'name' => 'PT Beta']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/companies/{$target->id}", [
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-API-006',
        'name' => 'PT Alpha',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name']);
    expect($target->fresh()->name)->toBe('PT Beta');
});

// CRITICAL — the key differentiator from KelolaCorporateTest.php: the same
// name is allowed to exist under a DIFFERENT corporate_id, no conflict.
// (company_code must still be globally unique, so a fresh one is used.)
it('creates a company successfully with a name already used under a different corporate (no conflict)', function () {
    $corporateA = Corporate::factory()->create();
    $corporateB = Corporate::factory()->create();
    Company::factory()->create(['corporate_id' => $corporateA->id, 'company_code' => 'COMP-API-007', 'name' => 'PT Anak Usaha']);

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/companies', [
        'corporate_id' => $corporateB->id,
        'company_code' => 'COMP-API-008',
        'name' => 'PT Anak Usaha',
    ]);

    $response->assertStatus(201);
    $response->assertJson([
        'name' => 'PT Anak Usaha',
        'corporate_id' => $corporateB->id,
    ]);

    expect(Company::where('name', 'PT Anak Usaha')->count())->toBe(2);
});

// New (CRITICAL — the key differentiator from company_code's global
// scope): creating with a company_code that already exists under a
// DIFFERENT corporate -> 422 errors.company_code, isolated from `name`.
it('returns 422 with errors.company_code when the company_code already exists under a different corporate', function () {
    $corporateA = Corporate::factory()->create();
    $corporateB = Corporate::factory()->create();
    Company::factory()->create(['corporate_id' => $corporateA->id, 'company_code' => 'COMP-API-DUP', 'name' => 'PT Alpha']);

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/companies', [
        'corporate_id' => $corporateB->id,
        'company_code' => 'COMP-API-DUP',
        'name' => 'PT Beta',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['company_code']);
    $response->assertJsonMissingValidationErrors(['name']);
    expect(Company::where('company_code', 'COMP-API-DUP')->count())->toBe(1);
});

// New: update with a company_code taken by another company (any
// corporate) -> 422 errors.company_code.
it('returns 422 with errors.company_code when updating to a company_code taken by another company', function () {
    Company::factory()->create(['company_code' => 'COMP-API-OTHER']);
    $target = Company::factory()->create(['company_code' => 'COMP-API-TARGET']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/companies/{$target->id}", [
        'corporate_id' => $target->corporate_id,
        'company_code' => 'COMP-API-OTHER',
        'name' => $target->name,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['company_code']);
    expect($target->fresh()->company_code)->toBe('COMP-API-TARGET');
});

// New: updating a company while keeping its own company_code unchanged
// must succeed (no false-positive uniqueness violation against self).
it('succeeds when updating a company and keeping its own company_code unchanged', function () {
    $company = Company::factory()->create(['company_code' => 'COMP-API-SELF', 'name' => 'PT Lama']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/companies/{$company->id}", [
        'corporate_id' => $company->corporate_id,
        'company_code' => 'COMP-API-SELF',
        'name' => 'PT Baru',
    ]);

    $response->assertOk();
    $response->assertJson(['company_code' => 'COMP-API-SELF', 'name' => 'PT Baru']);
    expect($company->fresh()->name)->toBe('PT Baru');
});

// New: create without a company_code -> 422 errors.company_code.
it('returns 422 with errors.company_code when creating without a company_code', function () {
    $corporate = Corporate::factory()->create();

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/companies', [
        'corporate_id' => $corporate->id,
        'name' => 'PT Tanpa Kode',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['company_code']);
});

// New: uploading a valid jpg logo succeeds, the row/response includes a
// logo_url, and the file is stored under company-logos/.
it('uploads a valid logo image and returns a logo_url, storing the file under company-logos/', function () {
    Storage::fake(CompanyService::LOGO_DISK);
    $corporate = Corporate::factory()->create();

    $response = $this->actingAs($this->admin, 'web')->post('/api/companies', [
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-API-LOGO-001',
        'name' => 'PT Berlogo',
        'logo' => UploadedFile::fake()->create('logo.jpg', 500, 'image/jpeg'),
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('logo_url', fn ($url) => ! empty($url));

    $storedPath = $response->json('logo');
    expect($storedPath)->not->toBeNull();
    expect($storedPath)->toStartWith('company-logos/');
    Storage::disk(CompanyService::LOGO_DISK)->assertExists($storedPath);
});

// New: `logo` is optional — omitting it entirely still succeeds.
it('creates a company successfully when logo is omitted', function () {
    $corporate = Corporate::factory()->create();

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/companies', [
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-API-LOGO-002',
        'name' => 'PT Tanpa Logo',
    ]);

    $response->assertStatus(201);
    $response->assertJson(['logo' => null, 'logo_url' => null]);
});

// New: an oversized logo (> 2MB) -> 422 errors.logo.
it('returns 422 with errors.logo when the logo file exceeds the max size', function () {
    Storage::fake(CompanyService::LOGO_DISK);
    $corporate = Corporate::factory()->create();

    $response = $this->actingAs($this->admin, 'web')->post('/api/companies', [
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-API-LOGO-003',
        'name' => 'PT Logo Besar',
        'logo' => UploadedFile::fake()->create('logo.jpg', 3000, 'image/jpeg'),
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['logo']);
    expect(Company::where('company_code', 'COMP-API-LOGO-003')->exists())->toBeFalse();
});

// New: a disallowed mime type (e.g. PDF) -> 422 errors.logo.
it('returns 422 with errors.logo when the logo file has a disallowed mime type', function () {
    Storage::fake(CompanyService::LOGO_DISK);
    $corporate = Corporate::factory()->create();

    $response = $this->actingAs($this->admin, 'web')->post('/api/companies', [
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-API-LOGO-004',
        'name' => 'PT Logo Pdf',
        'logo' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['logo']);
    expect(Company::where('company_code', 'COMP-API-LOGO-004')->exists())->toBeFalse();
});

// New: last_update accepts a valid date and round-trips through the
// response.
it('accepts a valid last_update date and returns it in the response', function () {
    $corporate = Corporate::factory()->create();

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/companies', [
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-API-DATE-001',
        'name' => 'PT Tanggal Valid',
        'last_update' => '2026-08-01',
    ]);

    $response->assertStatus(201);
    $response->assertJson(['last_update' => '2026-08-01']);
});

// New: last_update rejects an invalid (non-date) value -> 422 errors.last_update.
it('returns 422 with errors.last_update when last_update is not a valid date', function () {
    $corporate = Corporate::factory()->create();

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/companies', [
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-API-DATE-002',
        'name' => 'PT Tanggal Salah',
        'last_update' => 'bukan-tanggal',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['last_update']);
    expect(Company::where('company_code', 'COMP-API-DATE-002')->exists())->toBeFalse();
});

// New: created_by is always derived server-side from the authenticated
// admin — a spoofed created_by in the request payload must not override it.
it('sets created_by from the authenticated admin and ignores a spoofed created_by in the payload', function () {
    $spoofed = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->businessUnit)->create();
    $corporate = Corporate::factory()->create();

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/companies', [
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-API-AUDIT-001',
        'name' => 'PT Audit Trail',
        'created_by' => $spoofed->id,
    ]);

    $response->assertStatus(201);
    $response->assertJson(['created_by' => $this->admin->id]);
    expect(Company::where('name', 'PT Audit Trail')->firstOrFail()->created_by)->toBe($this->admin->id);
});

// New: updated_by is always derived server-side from the authenticated
// admin — a spoofed updated_by in the request payload must not override it.
it('sets updated_by from the authenticated admin and ignores a spoofed updated_by in the payload', function () {
    $spoofed = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->businessUnit)->create();
    $corporate = Corporate::factory()->create();
    $company = Company::factory()->create(['corporate_id' => $corporate->id, 'company_code' => 'COMP-API-AUDIT-002', 'name' => 'PT Lama Sekali']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/companies/{$company->id}", [
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-API-AUDIT-002',
        'name' => 'PT Baru Sekali',
        'updated_by' => $spoofed->id,
    ]);

    $response->assertOk();
    $response->assertJson(['updated_by' => $this->admin->id]);
    expect($company->fresh()->updated_by)->toBe($this->admin->id);
});

// New: optional contact/legal fields round-trip through the API — accepted,
// persisted, returned.
it('accepts, persists, and returns optional contact/legal fields when provided', function () {
    $corporate = Corporate::factory()->create();

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/companies', [
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-API-FIELDS-001',
        'name' => 'PT Lengkap',
        'short_name' => 'PSM',
        'leader_name' => 'Budi Santoso',
        'email' => 'info@ptlengkap.co.id',
        'telephone_no' => '021-5551234',
        'labor_union' => 'Serikat Pekerja PT Lengkap',
    ]);

    $response->assertStatus(201);
    $response->assertJson([
        'short_name' => 'PSM',
        'leader_name' => 'Budi Santoso',
        'email' => 'info@ptlengkap.co.id',
        'telephone_no' => '021-5551234',
        'labor_union' => 'Serikat Pekerja PT Lengkap',
    ]);

    $fresh = Company::where('name', 'PT Lengkap')->firstOrFail();
    expect($fresh->short_name)->toBe('PSM');
    expect($fresh->labor_union)->toBe('Serikat Pekerja PT Lengkap');
});

// New: omitting every optional field does not error.
it('creates a company successfully when every optional field is omitted', function () {
    $corporate = Corporate::factory()->create();

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/companies', [
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-API-FIELDS-002',
        'name' => 'PT Minimal',
    ]);

    $response->assertStatus(201);
    $response->assertJson(['short_name' => null, 'address' => null, 'labor_union' => null]);
});

// Extra branch coverage: create with non-existent corporate_id -> 422.
it('returns 422 with errors.corporate_id when creating with a non-existent corporate_id', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/companies', [
        'corporate_id' => '00000000-0000-0000-0000-000000000000',
        'company_code' => 'COMP-API-009',
        'name' => 'PT Anak Usaha',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['corporate_id']);
});

// Extra branch coverage: create with empty name -> 422.
it('returns 422 with errors.name when creating with an empty name', function () {
    $corporate = Corporate::factory()->create();

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/companies', [
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-API-010',
        'name' => '',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name']);
});

// Extra branch coverage: update a non-existent id -> 404.
it('returns 404 when updating a non-existent company', function () {
    $corporate = Corporate::factory()->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson('/api/companies/00000000-0000-0000-0000-000000000000', [
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-API-011',
        'name' => 'PT Apapun',
    ]);

    $response->assertStatus(404);
    $response->assertJson(['message' => 'Data tidak ditemukan.']);
});

// Extra branch coverage: delete a non-existent id -> 404.
it('returns 404 when deleting a non-existent company', function () {
    $response = $this->actingAs($this->admin, 'web')->deleteJson('/api/companies/00000000-0000-0000-0000-000000000000');

    $response->assertStatus(404);
    $response->assertJson(['message' => 'Data tidak ditemukan.']);
});

// Scenario 6: "Kelola Company — Belum ada Corporate"
it('Belum ada Corporate: returns an empty corporate options list when no corporates exist', function () {
    // beforeEach's shared $this->businessUnit cascades through
    // BusinessUnitFactory's default company_id => Company::factory() down
    // to CompanyFactory's default corporate_id => Corporate::factory(),
    // producing one incidental Corporate row — deleted here so this
    // scenario genuinely starts from zero Corporates. cascadeOnDelete on
    // companies.corporate_id / business_units.company_id (see the
    // create_companies_table / create_business_units_table migrations)
    // cascades the delete down; users.business_unit_id is nullOnDelete
    // (create_users_table migration), so existing sessions/users survive.
    Corporate::query()->delete();

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/corporates/options');

    $response->assertOk();
    $response->assertExactJson(['data' => []]);
});

// Scenario 7: "Kelola Company — akses ditolak untuk non-Admin"
it('akses ditolak: returns 403 for the list endpoint when the user is not an admin', function (string $role) {
    $user = match ($role) {
        'supervisor' => $this->supervisor,
        'mill_management' => $this->millManagement,
        'operator' => $this->operator,
    };

    $response = $this->actingAs($user, 'web')->getJson('/api/companies');

    $response->assertStatus(403);
    $response->assertJsonStructure(['message']);
})->with([
    'supervisor' => ['supervisor'],
    'mill management' => ['mill_management'],
    'operator' => ['operator'],
]);

// Additional actor-permission coverage: create/update/delete are also
// admin-only (mirrors the list endpoint's guard — same 'role:admin'
// middleware group in routes/api.php).
it('akses ditolak: returns 403 for create/update/delete when the user is not an admin', function () {
    $corporate = Corporate::factory()->create();
    $company = Company::factory()->create(['corporate_id' => $corporate->id]);

    $createResponse = $this->actingAs($this->supervisor, 'web')->postJson('/api/companies', [
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-API-012',
        'name' => 'PT Tidak Boleh',
    ]);
    $createResponse->assertStatus(403);

    $updateResponse = $this->actingAs($this->supervisor, 'web')->patchJson("/api/companies/{$company->id}", [
        'corporate_id' => $corporate->id,
        'company_code' => 'COMP-API-013',
        'name' => 'PT Tidak Boleh',
    ]);
    $updateResponse->assertStatus(403);

    $deleteResponse = $this->actingAs($this->supervisor, 'web')->deleteJson("/api/companies/{$company->id}");
    $deleteResponse->assertStatus(403);

    expect(Company::find($company->id))->not->toBeNull();
});

// Auth-guard coverage: unauthenticated requests must not reach the service
// at all.
it('returns 401 for the list endpoint when there is no authenticated session', function () {
    $response = $this->getJson('/api/companies');

    $response->assertStatus(401);
});

// Baseline happy-path list coverage (admin, business_logic step 1:
// corporate_name + business_unit_count per row, pagination meta shape,
// and the optional corporate_id filter). UNCHANGED.
it('lists companies paginated with corporate_name and business_unit_count per row for an admin', function () {
    $corporate = Corporate::factory()->create(['name' => 'PT Induk Jaya']);
    $company = Company::factory()->create(['corporate_id' => $corporate->id, 'name' => 'PT Anak Usaha']);
    BusinessUnit::factory()->count(2)->create(['company_id' => $company->id]);

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/companies');

    $response->assertOk();
    $response->assertJson([
        'meta' => [
            'page' => 1,
            'per_page' => 20,
            'total' => Company::count(),
            'total_pages' => 1,
        ],
    ]);
    $response->assertJsonFragment([
        'name' => 'PT Anak Usaha',
        'corporate_name' => 'PT Induk Jaya',
        'business_unit_count' => 2,
    ]);
});

// List filtered by corporate_id query param returns only that corporate's
// companies. UNCHANGED.
it('filters the list by corporate_id when the query param is provided', function () {
    $corporateA = Corporate::factory()->create();
    $corporateB = Corporate::factory()->create();
    Company::factory()->create(['corporate_id' => $corporateA->id, 'name' => 'PT A1']);
    Company::factory()->create(['corporate_id' => $corporateB->id, 'name' => 'PT B1']);

    $response = $this->actingAs($this->admin, 'web')->getJson("/api/companies?corporate_id={$corporateA->id}");

    $response->assertOk();
    $response->assertJsonFragment(['name' => 'PT A1']);
    $response->assertJsonMissing(['name' => 'PT B1']);
});
