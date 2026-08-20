<?php

/**
 * KelolaBusinessUnitTest (Feature/Api) — screen-029--kelola-business-unit /
 * usecase-029--kelola-business-unit.
 *
 * Integration tests for GET /api/business-units, GET /api/companies/options,
 * POST/PATCH/DELETE /api/business-units (App\Http\Controllers\Api\
 * BusinessUnitController). Exercises the real route -> middleware ->
 * controller -> BusinessUnitService -> Eloquent chain against the sqlite
 * in-memory testing DB (RefreshDatabase, bound in tests/Pest.php for the
 * Feature suite). Mirrors tests/Feature/Api/KelolaCompanyTest.php exactly,
 * with the divergences documented below.
 *
 * Session auth: authenticated via $this->actingAs($user, 'web') (mirrors
 * KelolaCompanyTest.php) — matches config/auth.php's 'web' session guard.
 *
 * CRITICAL divergence #1 — index() access control: unlike every other
 * admin-only master-data screen in this codebase, GET /api/business-units
 * is a DELIBERATE, documented exception (see
 * BusinessUnitController::index()'s docblock) — it is NOT gated by
 * 'role:admin' (or even 'auth') at all, in EITHER its legacy no-params
 * branch (screen-001/002's pre-login "Business Area" picker) OR its
 * richer paginated branch (page/per_page/company_id params). Both are
 * intentionally public. Only companyOptions()/store()/update()/destroy()
 * are admin-gated ('auth:web' + 'role:admin', see routes/api.php).
 *
 * CRITICAL divergence #2 — code vs name uniqueness: `code` is unique
 * GLOBALLY (like Corporate's corporate_code / Company's company_code),
 * but `name` has NO uniqueness rule at all — neither global nor scoped to
 * the parent company. See the dedicated "no uniqueness rule on name"
 * tests below, the key differentiator from both KelolaCorporateTest.php
 * and KelolaCompanyTest.php.
 *
 * CRITICAL divergence #3 — delete-guard: BusinessUnitHasStationsException
 * (409 BUSINESS_UNIT_HAS_STATIONS) fires when the business unit has
 * related Station rows (not BusinessUnit rows, unlike Company's guard).
 *
 * Response shape note: shared_decisions.error_format is
 * `{ "message": ..., "errors": {...} }` — ApiExceptionHandler only adds
 * `errors` for 422 ValidationException responses; 404
 * (ModelNotFoundException) and 409 (BusinessUnitHasStationsException, a
 * plain HttpException) render as `{ "message": ... }` only.
 *
 * CRITICAL environment constraint: this environment has no PHP `gd`
 * extension installed, so `UploadedFile::fake()->image(...)` throws
 * `LogicException: GD extension is not installed`. Every logo-related test
 * below therefore uses the binary-fake pattern instead — e.g.
 * `UploadedFile::fake()->create('logo.jpg', $sizeInKb, 'image/jpeg')`.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Station;
use App\Models\User;
use App\Services\BusinessUnitService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Shared business unit for all beforeEach users (mirrors
    // tests/Feature/Api/KelolaCompanyTest.php) — without this, each
    // User::factory() call's default business_unit_id => BusinessUnit::factory()
    // cascades through BusinessUnitFactory's default company_id =>
    // Company::factory() down to CompanyFactory's default corporate_id =>
    // Corporate::factory(), creating one incidental BusinessUnit/Company/
    // Corporate row per user and inflating any meta.total assertion below.
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->admin = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->businessUnit)->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnit)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnit)->create();
});

// Scenario "Kelola Business Unit — success"
it('berhasil: loads company options then creates a business unit, returns 201 with the expected row shape', function () {
    $company = Company::factory()->create(['name' => 'PT Induk Company']);

    $optionsResponse = $this->actingAs($this->admin, 'web')->getJson('/api/companies/options');
    $optionsResponse->assertOk();
    $optionsResponse->assertJsonFragment(['id' => $company->id, 'name' => 'PT Induk Company']);

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/business-units', [
        'company_id' => $company->id,
        'code' => 'BU-API-001',
        'name' => 'Mill Unit 1',
    ]);

    $response->assertStatus(201);
    $response->assertJsonStructure([
        'id', 'code', 'name', 'logo', 'logo_url',
        'company_id', 'company_name', 'station_count',
        'created_by', 'updated_by', 'created_at',
    ]);
    $response->assertJson([
        'code' => 'BU-API-001',
        'name' => 'Mill Unit 1',
        'company_id' => $company->id,
        'company_name' => 'PT Induk Company',
        'station_count' => 0,
    ]);

    expect(BusinessUnit::where('name', 'Mill Unit 1')->exists())->toBeTrue();
});

// Scenario "Kelola Business Unit — Edit Business Unit"
it('Edit Business Unit: updates the code/name/company then returns 200 with the updated row', function () {
    $companyA = Company::factory()->create(['name' => 'PT Awal']);
    $companyB = Company::factory()->create(['name' => 'PT Tujuan']);
    $businessUnit = BusinessUnit::factory()->create(['company_id' => $companyA->id, 'code' => 'BU-API-002', 'name' => 'Mill Lama']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/business-units/{$businessUnit->id}", [
        'company_id' => $companyB->id,
        'code' => 'BU-API-002B',
        'name' => 'Mill Baru',
    ]);

    $response->assertOk();
    $response->assertJson([
        'id' => $businessUnit->id,
        'code' => 'BU-API-002B',
        'name' => 'Mill Baru',
        'company_id' => $companyB->id,
        'company_name' => 'PT Tujuan',
    ]);

    expect($businessUnit->fresh()->name)->toBe('Mill Baru');
    expect($businessUnit->fresh()->code)->toBe('BU-API-002B');
    expect($businessUnit->fresh()->company_id)->toBe($companyB->id);
});

// Scenario "Kelola Business Unit — Hapus Business Unit — berhasil"
it('Hapus Business Unit berhasil: deletes a business unit with no related stations, returns 200', function () {
    $businessUnit = BusinessUnit::factory()->create();

    $response = $this->actingAs($this->admin, 'web')->deleteJson("/api/business-units/{$businessUnit->id}");

    $response->assertOk();
    $response->assertExactJson(['deleted' => true]);

    expect(BusinessUnit::find($businessUnit->id))->toBeNull();
});

// Scenario "Kelola Business Unit — Hapus Business Unit — ditolak"
it('Hapus Business Unit ditolak: returns 409 BUSINESS_UNIT_HAS_STATIONS and keeps the row when it has related stations', function () {
    $businessUnit = BusinessUnit::factory()->create();
    Station::factory()->create(['business_unit_id' => $businessUnit->id]);

    $response = $this->actingAs($this->admin, 'web')->deleteJson("/api/business-units/{$businessUnit->id}");

    $response->assertStatus(409);
    $response->assertJsonStructure(['message']);
    $response->assertJsonMissing(['errors']);
    expect($response->json('message'))->toContain('Station');

    expect(BusinessUnit::find($businessUnit->id))->not->toBeNull();
});

// Scenario "Kelola Business Unit — Kode duplikat" (create branch)
it('Kode duplikat (create): returns 422 with errors.code when the code already exists under any company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    BusinessUnit::factory()->create(['company_id' => $companyA->id, 'code' => 'BU-API-DUP', 'name' => 'Mill Alpha']);

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/business-units', [
        'company_id' => $companyB->id,
        'code' => 'BU-API-DUP',
        'name' => 'Mill Beta',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['code']);
    $response->assertJsonMissingValidationErrors(['name']);
    expect(BusinessUnit::where('code', 'BU-API-DUP')->count())->toBe(1);
});

// Scenario "Kelola Business Unit — Kode duplikat" (edit branch)
it('Kode duplikat (edit): returns 422 with errors.code when updating to a code taken by another business unit', function () {
    BusinessUnit::factory()->create(['code' => 'BU-API-OTHER']);
    $target = BusinessUnit::factory()->create(['code' => 'BU-API-TARGET']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/business-units/{$target->id}", [
        'company_id' => $target->company_id,
        'code' => 'BU-API-OTHER',
        'name' => $target->name,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['code']);
    expect($target->fresh()->code)->toBe('BU-API-TARGET');
});

// Keep-own-code-unchanged on update must succeed (no false-positive
// uniqueness violation against self).
it('succeeds when updating a business unit and keeping its own code unchanged', function () {
    $businessUnit = BusinessUnit::factory()->create(['code' => 'BU-API-SELF', 'name' => 'Mill Lama']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/business-units/{$businessUnit->id}", [
        'company_id' => $businessUnit->company_id,
        'code' => 'BU-API-SELF',
        'name' => 'Mill Baru',
    ]);

    $response->assertOk();
    $response->assertJson(['code' => 'BU-API-SELF', 'name' => 'Mill Baru']);
    expect($businessUnit->fresh()->name)->toBe('Mill Baru');
});

// CRITICAL — the key differentiator from KelolaCorporateTest.php AND
// KelolaCompanyTest.php: `name` has NO uniqueness rule at all. Unlike
// Company (scoped to corporate_id), the identical name is allowed even
// WITHIN THE SAME company here.
it('creates a business unit successfully with a name already used within the SAME company (no uniqueness rule on name)', function () {
    $company = Company::factory()->create();
    BusinessUnit::factory()->create(['company_id' => $company->id, 'code' => 'BU-API-003', 'name' => 'Mill Kembar']);

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/business-units', [
        'company_id' => $company->id,
        'code' => 'BU-API-004',
        'name' => 'Mill Kembar',
    ]);

    $response->assertStatus(201);
    $response->assertJson([
        'name' => 'Mill Kembar',
        'company_id' => $company->id,
    ]);

    expect(BusinessUnit::where('company_id', $company->id)->where('name', 'Mill Kembar')->count())->toBe(2);
});

// Same, but under a DIFFERENT company — also no conflict.
it('creates a business unit successfully with a name already used under a DIFFERENT company (no conflict)', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    BusinessUnit::factory()->create(['company_id' => $companyA->id, 'code' => 'BU-API-005', 'name' => 'Mill Kembar']);

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/business-units', [
        'company_id' => $companyB->id,
        'code' => 'BU-API-006',
        'name' => 'Mill Kembar',
    ]);

    $response->assertStatus(201);
    $response->assertJson([
        'name' => 'Mill Kembar',
        'company_id' => $companyB->id,
    ]);

    expect(BusinessUnit::where('name', 'Mill Kembar')->count())->toBe(2);
});

// Scenario "Kelola Business Unit — Company induk wajib dipilih"
it('Company wajib dipilih: returns 422 with errors.company_id when creating without a company_id', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/business-units', [
        'code' => 'BU-API-NOCO',
        'name' => 'Mill Unit Tanpa Company',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['company_id']);
});

// Extra branch coverage: create with a non-existent company_id -> 422.
it('returns 422 with errors.company_id when creating with a non-existent company_id', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/business-units', [
        'company_id' => '00000000-0000-0000-0000-000000000000',
        'code' => 'BU-API-007',
        'name' => 'Mill Unit',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['company_id']);
});

// Extra branch coverage: create with an empty name -> 422.
it('returns 422 with errors.name when creating with an empty name', function () {
    $company = Company::factory()->create();

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/business-units', [
        'company_id' => $company->id,
        'code' => 'BU-API-008',
        'name' => '',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name']);
});

// Extra branch coverage: create without a code -> 422.
it('returns 422 with errors.code when creating without a code', function () {
    $company = Company::factory()->create();

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/business-units', [
        'company_id' => $company->id,
        'name' => 'Mill Tanpa Kode',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['code']);
});

// Extra branch coverage: update a non-existent id -> 404.
it('returns 404 when updating a non-existent business unit', function () {
    $company = Company::factory()->create();

    $response = $this->actingAs($this->admin, 'web')->patchJson('/api/business-units/00000000-0000-0000-0000-000000000000', [
        'company_id' => $company->id,
        'code' => 'BU-API-009',
        'name' => 'Mill Apapun',
    ]);

    $response->assertStatus(404);
    $response->assertJson(['message' => 'Data tidak ditemukan.']);
});

// Extra branch coverage: delete a non-existent id -> 404.
it('returns 404 when deleting a non-existent business unit', function () {
    $response = $this->actingAs($this->admin, 'web')->deleteJson('/api/business-units/00000000-0000-0000-0000-000000000000');

    $response->assertStatus(404);
    $response->assertJson(['message' => 'Data tidak ditemukan.']);
});

// New: uploading a valid jpg logo succeeds, the row/response includes a
// logo_url, and the file is stored under business-unit-logos/.
it('uploads a valid logo image and returns a logo_url, storing the file under business-unit-logos/', function () {
    Storage::fake(BusinessUnitService::LOGO_DISK);
    $company = Company::factory()->create();

    $response = $this->actingAs($this->admin, 'web')->post('/api/business-units', [
        'company_id' => $company->id,
        'code' => 'BU-API-LOGO-001',
        'name' => 'Mill Berlogo',
        'logo' => UploadedFile::fake()->create('logo.jpg', 500, 'image/jpeg'),
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('logo_url', fn ($url) => ! empty($url));

    $storedPath = $response->json('logo');
    expect($storedPath)->not->toBeNull();
    expect($storedPath)->toStartWith('business-unit-logos/');
    Storage::disk(BusinessUnitService::LOGO_DISK)->assertExists($storedPath);
});

// New: `logo` is optional — omitting it entirely still succeeds.
it('creates a business unit successfully when logo is omitted', function () {
    $company = Company::factory()->create();

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/business-units', [
        'company_id' => $company->id,
        'code' => 'BU-API-LOGO-002',
        'name' => 'Mill Tanpa Logo',
    ]);

    $response->assertStatus(201);
    $response->assertJson(['logo' => null, 'logo_url' => null]);
});

// New: an oversized logo (> 2MB) -> 422 errors.logo.
it('returns 422 with errors.logo when the logo file exceeds the max size', function () {
    Storage::fake(BusinessUnitService::LOGO_DISK);
    $company = Company::factory()->create();

    $response = $this->actingAs($this->admin, 'web')->post('/api/business-units', [
        'company_id' => $company->id,
        'code' => 'BU-API-LOGO-003',
        'name' => 'Mill Logo Besar',
        'logo' => UploadedFile::fake()->create('logo.jpg', 3000, 'image/jpeg'),
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['logo']);
    expect(BusinessUnit::where('code', 'BU-API-LOGO-003')->exists())->toBeFalse();
});

// New: a disallowed mime type (e.g. PDF) -> 422 errors.logo.
it('returns 422 with errors.logo when the logo file has a disallowed mime type', function () {
    Storage::fake(BusinessUnitService::LOGO_DISK);
    $company = Company::factory()->create();

    $response = $this->actingAs($this->admin, 'web')->post('/api/business-units', [
        'company_id' => $company->id,
        'code' => 'BU-API-LOGO-004',
        'name' => 'Mill Logo Pdf',
        'logo' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['logo']);
    expect(BusinessUnit::where('code', 'BU-API-LOGO-004')->exists())->toBeFalse();
});

// New: a new logo upload on update() replaces the stored logo path.
it('replaces the stored logo on update when a new file is provided', function () {
    Storage::fake(BusinessUnitService::LOGO_DISK);
    $businessUnit = BusinessUnit::factory()->create([
        'code' => 'BU-API-LOGO-005',
        'logo' => 'business-unit-logos/old.jpg',
    ]);
    $newLogo = UploadedFile::fake()->create('new-logo.png', 400, 'image/png');

    $response = $this->actingAs($this->admin, 'web')->post("/api/business-units/{$businessUnit->id}", [
        '_method' => 'PATCH',
        'company_id' => $businessUnit->company_id,
        'code' => 'BU-API-LOGO-005',
        'name' => $businessUnit->name,
        'logo' => $newLogo,
    ]);

    $response->assertOk();
    $storedPath = $response->json('logo');
    expect($storedPath)->not->toBe('business-unit-logos/old.jpg');
    Storage::disk(BusinessUnitService::LOGO_DISK)->assertExists($storedPath);
});

// New: update() without a new logo argument leaves the existing stored
// `logo` column untouched.
it('leaves the existing logo untouched when updating without a new logo file', function () {
    $businessUnit = BusinessUnit::factory()->create([
        'code' => 'BU-API-LOGO-006',
        'logo' => 'business-unit-logos/keep.jpg',
    ]);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/business-units/{$businessUnit->id}", [
        'company_id' => $businessUnit->company_id,
        'code' => 'BU-API-LOGO-006',
        'name' => 'Mill Nama Baru',
    ]);

    $response->assertOk();
    $response->assertJson(['logo' => 'business-unit-logos/keep.jpg']);
});

// New: created_by is always derived server-side from the authenticated
// admin — a spoofed created_by in the request payload must not override
// it.
it('sets created_by from the authenticated admin and ignores a spoofed created_by in the payload', function () {
    $spoofed = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->businessUnit)->create();
    $company = Company::factory()->create();

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/business-units', [
        'company_id' => $company->id,
        'code' => 'BU-API-AUDIT-001',
        'name' => 'Mill Audit Trail',
        'created_by' => $spoofed->id,
    ]);

    $response->assertStatus(201);
    $response->assertJson(['created_by' => $this->admin->id]);
    expect(BusinessUnit::where('name', 'Mill Audit Trail')->firstOrFail()->created_by)->toBe($this->admin->id);
});

// New: updated_by is always derived server-side from the authenticated
// admin — a spoofed updated_by in the request payload must not override
// it.
it('sets updated_by from the authenticated admin and ignores a spoofed updated_by in the payload', function () {
    $spoofed = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->businessUnit)->create();
    $businessUnit = BusinessUnit::factory()->create(['code' => 'BU-API-AUDIT-002', 'name' => 'Mill Lama Sekali']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/business-units/{$businessUnit->id}", [
        'company_id' => $businessUnit->company_id,
        'code' => 'BU-API-AUDIT-002',
        'name' => 'Mill Baru Sekali',
        'updated_by' => $spoofed->id,
    ]);

    $response->assertOk();
    $response->assertJson(['updated_by' => $this->admin->id]);
    expect($businessUnit->fresh()->updated_by)->toBe($this->admin->id);
});

// New: optional contact/legal fields round-trip through the API.
it('accepts, persists, and returns optional contact/legal fields when provided', function () {
    $company = Company::factory()->create();

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/business-units', [
        'company_id' => $company->id,
        'code' => 'BU-API-FIELDS-001',
        'name' => 'Mill Lengkap',
        'short_name' => 'MLK',
        'leader_name' => 'Budi Santoso',
        'email' => 'info@milllengkap.co.id',
        'telephone_no' => '021-5551234',
        'labor_union' => 'Serikat Pekerja Mill Lengkap',
    ]);

    $response->assertStatus(201);
    $response->assertJson([
        'short_name' => 'MLK',
        'leader_name' => 'Budi Santoso',
        'email' => 'info@milllengkap.co.id',
        'telephone_no' => '021-5551234',
        'labor_union' => 'Serikat Pekerja Mill Lengkap',
    ]);
});

// New: omitting every optional field does not error.
it('creates a business unit successfully when every optional field is omitted', function () {
    $company = Company::factory()->create();

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/business-units', [
        'company_id' => $company->id,
        'code' => 'BU-API-FIELDS-002',
        'name' => 'Mill Minimal',
    ]);

    $response->assertStatus(201);
    $response->assertJson(['short_name' => null, 'address' => null, 'labor_union' => null]);
});

// Scenario "Kelola Business Unit — Belum ada Company"
it('Belum ada Company: returns an empty company options list when no companies exist', function () {
    // beforeEach's shared $this->businessUnit cascades through
    // BusinessUnitFactory's default company_id => Company::factory() down
    // to CompanyFactory's default corporate_id => Corporate::factory(),
    // producing one incidental Company row — deleted here so this scenario
    // genuinely starts from zero Companies. cascadeOnDelete on
    // business_units.company_id (create_business_units_table migration)
    // cascades the delete down to $this->businessUnit itself;
    // users.business_unit_id is nullOnDelete (create_users_table
    // migration), so existing sessions/users survive.
    Company::query()->delete();

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/companies/options');

    $response->assertOk();
    $response->assertExactJson(['data' => []]);
});

// GET /api/business-units — legacy no-params branch: public/unauthenticated,
// bare {id, name} shape, matching screen-001/002's pre-login "Business
// Area" picker. Deliberately called with NO actingAs() at all.
it('list (no params): returns the legacy public {data:[{id,name}]} shape with no authenticated session at all', function () {
    $extra = BusinessUnit::factory()->create(['name' => 'Mill Publik']);

    $response = $this->getJson('/api/business-units');

    $response->assertOk();
    $response->assertJsonFragment(['id' => $extra->id, 'name' => 'Mill Publik']);
    $response->assertJsonFragment(['id' => $this->businessUnit->id, 'name' => $this->businessUnit->name]);

    $data = $response->json('data');
    expect($data)->not->toBeEmpty();
    foreach ($data as $row) {
        expect(array_keys($row))->toBe(['id', 'name']);
    }
});

// GET /api/business-units — richer paginated branch (page/per_page/
// company_id params): also public/unauthenticated per the controller's
// deliberate merge design — this test confirms it WITHOUT actingAs().
it('list (with params, unauthenticated): returns the richer paginated shape with company_name/station_count without any session', function () {
    $company = Company::factory()->create(['name' => 'PT Induk Jaya']);
    $businessUnit = BusinessUnit::factory()->create(['company_id' => $company->id, 'name' => 'Mill Alpha']);
    Station::factory()->count(2)->forBusinessUnit($businessUnit)->create();

    $response = $this->getJson('/api/business-units?page=1&per_page=20');

    $response->assertOk();
    $response->assertJsonStructure(['data', 'meta' => ['page', 'per_page', 'total', 'total_pages']]);
    $response->assertJsonFragment([
        'name' => 'Mill Alpha',
        'company_name' => 'PT Induk Jaya',
        'station_count' => 2,
    ]);
});

// Same richer paginated branch, but WITH an authenticated admin session —
// confirms the shape/content is identical regardless of auth state (not
// gated either way).
it('list (with params, authenticated as admin): returns the same richer paginated shape', function () {
    $company = Company::factory()->create(['name' => 'PT Induk Jaya']);
    BusinessUnit::factory()->create(['company_id' => $company->id, 'name' => 'Mill Alpha']);

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/business-units?page=1&per_page=20');

    $response->assertOk();
    $response->assertJsonStructure(['data', 'meta' => ['page', 'per_page', 'total', 'total_pages']]);
    $response->assertJsonFragment(['name' => 'Mill Alpha', 'company_name' => 'PT Induk Jaya']);
});

// company_id filter on the richer paginated branch returns only that
// company's business units.
it('list (with company_id filter): returns only that company\'s business units', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    BusinessUnit::factory()->create(['company_id' => $companyA->id, 'name' => 'Mill A1']);
    BusinessUnit::factory()->create(['company_id' => $companyB->id, 'name' => 'Mill B1']);

    $response = $this->getJson("/api/business-units?company_id={$companyA->id}");

    $response->assertOk();
    $response->assertJsonFragment(['name' => 'Mill A1']);
    $response->assertJsonMissing(['name' => 'Mill B1']);
});

// Actor-permission coverage: companyOptions/create/update/delete are
// admin-only ('role:admin' middleware group, routes/api.php) — NOT the
// index() list endpoint, which is deliberately left public (see this
// file's docblock, divergence #1). The tech-spec's
// "Kelola Business Unit — Akses ditolak untuk non-Admin" scenario_ref
// names GET /api/business-units as the 403 target, but the actual
// implementation (BusinessUnitController::index() docblock) deliberately
// keeps that ONE endpoint public for screen-001/002 backward
// compatibility — see known_issues in this agent's final report. This
// suite instead exercises the four endpoints that ARE genuinely
// admin-gated.
it('akses ditolak: returns 403 for companyOptions/create/update/delete when the user is not an admin', function (string $role) {
    $user = match ($role) {
        'supervisor' => $this->supervisor,
        'mill_management' => $this->millManagement,
        'operator' => $this->operator,
    };
    $company = Company::factory()->create();
    $businessUnit = BusinessUnit::factory()->create(['company_id' => $company->id]);

    $optionsResponse = $this->actingAs($user, 'web')->getJson('/api/companies/options');
    $optionsResponse->assertStatus(403);
    $optionsResponse->assertJsonStructure(['message']);

    $createResponse = $this->actingAs($user, 'web')->postJson('/api/business-units', [
        'company_id' => $company->id,
        'code' => 'BU-API-FORBID-'.strtoupper($role),
        'name' => 'Mill Tidak Boleh',
    ]);
    $createResponse->assertStatus(403);

    $updateResponse = $this->actingAs($user, 'web')->patchJson("/api/business-units/{$businessUnit->id}", [
        'company_id' => $company->id,
        'code' => 'BU-API-FORBID-2-'.strtoupper($role),
        'name' => 'Mill Tidak Boleh',
    ]);
    $updateResponse->assertStatus(403);

    $deleteResponse = $this->actingAs($user, 'web')->deleteJson("/api/business-units/{$businessUnit->id}");
    $deleteResponse->assertStatus(403);

    expect(BusinessUnit::find($businessUnit->id))->not->toBeNull();
})->with([
    'supervisor' => ['supervisor'],
    'mill management' => ['mill_management'],
    'operator' => ['operator'],
]);

// Auth-guard coverage: unauthenticated requests to the four admin-gated
// endpoints must not reach the service at all -> 401.
it('returns 401 for companyOptions/create/update/delete when there is no authenticated session', function () {
    $company = Company::factory()->create();
    $businessUnit = BusinessUnit::factory()->create(['company_id' => $company->id]);

    $this->getJson('/api/companies/options')->assertStatus(401);

    $this->postJson('/api/business-units', [
        'company_id' => $company->id,
        'code' => 'BU-API-401-1',
        'name' => 'Mill Tanpa Sesi',
    ])->assertStatus(401);

    $this->patchJson("/api/business-units/{$businessUnit->id}", [
        'company_id' => $company->id,
        'code' => 'BU-API-401-2',
        'name' => 'Mill Tanpa Sesi',
    ])->assertStatus(401);

    $this->deleteJson("/api/business-units/{$businessUnit->id}")->assertStatus(401);

    expect(BusinessUnit::find($businessUnit->id))->not->toBeNull();
});
