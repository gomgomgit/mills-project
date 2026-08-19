<?php

/**
 * KelolaCorporateTest (Feature/Api) — screen-027--kelola-corporate /
 * usecase-027--kelola-corporate.
 *
 * Integration tests for GET/POST/PATCH/DELETE /api/corporates
 * (App\Http\Controllers\Api\CorporateController), one per test_scenarios'
 * api_test step(s). Exercises the real route -> 'auth:web' + 'role:admin'
 * middleware -> controller -> CorporateService -> Eloquent chain against
 * the sqlite in-memory testing DB (RefreshDatabase, bound in
 * tests/Pest.php for the Feature suite).
 *
 * Session auth: authenticated via $this->actingAs($user, 'web') (mirrors
 * tests/Feature/Api/DataBrowserWeighbridgeTest.php) — matches
 * config/auth.php's 'web' session guard, the same guard this screen's
 * routes are gated by ('auth:web' in routes/api.php).
 *
 * Response shape note: shared_decisions.error_format is
 * `{ "message": ..., "errors": {...} }` — ApiExceptionHandler only adds
 * `errors` for 422 ValidationException responses; 404 (ModelNotFoundException)
 * and 409 (CorporateHasCompaniesException, a plain HttpException) render as
 * `{ "message": ... }` only.
 *
 * Entity-catalog v4 rework (screen-027--kelola-corporate 3-tech-spec ver 2):
 * `corporate_code` is now a second required+unique field alongside `name`,
 * so every create()/update() payload below includes one unless the test is
 * specifically about corporate_code validation. Also covers the `logo`
 * upload (Storage::fake(CorporateService::LOGO_DISK) — confirmed to be
 * 'local' by reading app/Services/CorporateService.php directly), the
 * other optional contact/legal fields, and created_by/updated_by
 * auto-derivation. Requests here use ->post()/->patch() (not postJson()/
 * patchJson()) whenever a `logo` UploadedFile is part of the payload, since
 * a JSON body cannot carry a file upload; ApiExceptionHandler still returns
 * JSON error responses for these because it routes by `$request->is('api/*')`
 * in addition to `$request->expectsJson()` (see its own docblock), not by
 * request Content-Type.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Corporate;
use App\Models\User;
use App\Services\CorporateService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    // Shared business unit for all beforeEach users (mirrors
    // tests/Feature/Api/DataBrowserWeighbridgeTest.php and
    // tests/Feature/Api/LoginWebTest.php) — without this, each User::factory()
    // call's default business_unit_id => BusinessUnit::factory() cascades
    // through BusinessUnitFactory's default company_id => Company::factory()
    // down to CompanyFactory's default corporate_id => Corporate::factory(),
    // creating one incidental Corporate row per user and inflating any
    // meta.total assertion below.
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->admin = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->businessUnit)->create();
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnit)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnit)->create();
});

// Scenario 1: "Kelola Corporate — success"
it('berhasil: creates a corporate and returns 201 with the expected row shape', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/corporates', [
        'corporate_code' => 'COR-API-001',
        'name' => 'PT Sawit Makmur Jaya',
    ]);

    $response->assertStatus(201);
    $response->assertJsonStructure(['id', 'corporate_code', 'name', 'logo', 'logo_url', 'company_count', 'created_by', 'updated_by', 'created_at']);
    $response->assertJson([
        'corporate_code' => 'COR-API-001',
        'name' => 'PT Sawit Makmur Jaya',
        'company_count' => 0,
    ]);

    expect(Corporate::where('name', 'PT Sawit Makmur Jaya')->exists())->toBeTrue();
});

// Scenario 2: "Kelola Corporate — Edit Corporate"
it('Edit Corporate: updates the name and returns 200 with the updated row', function () {
    $corporate = Corporate::factory()->create(['corporate_code' => 'COR-API-002', 'name' => 'PT Lama Jaya']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/corporates/{$corporate->id}", [
        'corporate_code' => 'COR-API-002',
        'name' => 'PT Baru Sentosa',
    ]);

    $response->assertOk();
    $response->assertJson([
        'id' => $corporate->id,
        'name' => 'PT Baru Sentosa',
    ]);

    expect($corporate->fresh()->name)->toBe('PT Baru Sentosa');
});

// Scenario 3: "Kelola Corporate — Hapus Corporate — berhasil"
it('Hapus Corporate berhasil: deletes a corporate with no related companies, returns 200', function () {
    $corporate = Corporate::factory()->create();

    $response = $this->actingAs($this->admin, 'web')->deleteJson("/api/corporates/{$corporate->id}");

    $response->assertOk();
    $response->assertExactJson(['deleted' => true]);

    expect(Corporate::find($corporate->id))->toBeNull();
});

// Scenario 4: "Kelola Corporate — Hapus Corporate — ditolak"
it('Hapus Corporate ditolak: returns 409 CORPORATE_HAS_COMPANIES and keeps the row when it has related companies', function () {
    $corporate = Corporate::factory()->create();
    Company::factory()->create(['corporate_id' => $corporate->id]);

    $response = $this->actingAs($this->admin, 'web')->deleteJson("/api/corporates/{$corporate->id}");

    $response->assertStatus(409);
    $response->assertJsonStructure(['message']);
    $response->assertJsonMissing(['errors']);
    expect($response->json('message'))->toContain('Company');

    expect(Corporate::find($corporate->id))->not->toBeNull();
});

// Scenario 5: "Kelola Corporate — Nama duplikat" (create branch)
it('Nama duplikat (create): returns 422 with errors.name when the name already exists', function () {
    Corporate::factory()->create(['name' => 'PT Sawit Makmur Jaya']);

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/corporates', [
        'corporate_code' => 'COR-API-003',
        'name' => 'PT Sawit Makmur Jaya',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name']);
});

// Scenario 5: "Kelola Corporate — Nama duplikat" (edit branch)
it('Nama duplikat (edit): returns 422 with errors.name when the name is taken by another corporate', function () {
    Corporate::factory()->create(['name' => 'PT Alpha']);
    $target = Corporate::factory()->create(['name' => 'PT Beta', 'corporate_code' => 'COR-API-004']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/corporates/{$target->id}", [
        'corporate_code' => 'COR-API-004',
        'name' => 'PT Alpha',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name']);
    expect($target->fresh()->name)->toBe('PT Beta');
});

// Extra branch coverage: create with empty name -> 422.
it('returns 422 with errors.name when creating with an empty name', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/corporates', [
        'corporate_code' => 'COR-API-005',
        'name' => '',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['name']);
});

// Extra branch coverage: update a non-existent id -> 404.
it('returns 404 when updating a non-existent corporate', function () {
    $response = $this->actingAs($this->admin, 'web')->patchJson('/api/corporates/00000000-0000-0000-0000-000000000000', [
        'corporate_code' => 'COR-API-006',
        'name' => 'PT Apapun',
    ]);

    $response->assertStatus(404);
    $response->assertJson(['message' => 'Data tidak ditemukan.']);
});

// Extra branch coverage: delete a non-existent id -> 404.
it('returns 404 when deleting a non-existent corporate', function () {
    $response = $this->actingAs($this->admin, 'web')->deleteJson('/api/corporates/00000000-0000-0000-0000-000000000000');

    $response->assertStatus(404);
    $response->assertJson(['message' => 'Data tidak ditemukan.']);
});

// New: create without a corporate_code -> 422 errors.corporate_code.
it('returns 422 with errors.corporate_code when creating without a corporate_code', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/corporates', [
        'name' => 'PT Tanpa Kode',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['corporate_code']);
});

// New: create with a duplicate corporate_code (different name) -> 422
// errors.corporate_code, isolated from `name`.
it('returns 422 with errors.corporate_code when the corporate_code already exists', function () {
    Corporate::factory()->create(['corporate_code' => 'COR-API-DUP']);

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/corporates', [
        'corporate_code' => 'COR-API-DUP',
        'name' => 'PT Nama Berbeda',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['corporate_code']);
    $response->assertJsonMissingValidationErrors(['name']);
});

// New: update with a corporate_code taken by another corporate -> 422
// errors.corporate_code.
it('returns 422 with errors.corporate_code when updating to a corporate_code taken by another corporate', function () {
    Corporate::factory()->create(['corporate_code' => 'COR-API-OTHER']);
    $target = Corporate::factory()->create(['corporate_code' => 'COR-API-TARGET']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/corporates/{$target->id}", [
        'corporate_code' => 'COR-API-OTHER',
        'name' => $target->name,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['corporate_code']);
    expect($target->fresh()->corporate_code)->toBe('COR-API-TARGET');
});

// New: updating a corporate while keeping its own corporate_code unchanged
// must succeed (no false-positive uniqueness violation against self).
it('succeeds when updating a corporate and keeping its own corporate_code unchanged', function () {
    $corporate = Corporate::factory()->create(['corporate_code' => 'COR-API-SELF', 'name' => 'PT Lama']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/corporates/{$corporate->id}", [
        'corporate_code' => 'COR-API-SELF',
        'name' => 'PT Baru',
    ]);

    $response->assertOk();
    $response->assertJson(['corporate_code' => 'COR-API-SELF', 'name' => 'PT Baru']);
    expect($corporate->fresh()->name)->toBe('PT Baru');
});

// New: uploading a valid jpg logo succeeds, the row/response includes a
// logo_url, and the file is actually stored.
it('uploads a valid logo image and returns a logo_url, storing the file', function () {
    Storage::fake(CorporateService::LOGO_DISK);

    $response = $this->actingAs($this->admin, 'web')->post('/api/corporates', [
        'corporate_code' => 'COR-API-LOGO-001',
        'name' => 'PT Berlogo',
        'logo' => UploadedFile::fake()->create('logo.jpg', 500, 'image/jpeg'),
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('logo_url', fn ($url) => ! empty($url));

    $storedPath = $response->json('logo');
    expect($storedPath)->not->toBeNull();
    Storage::disk(CorporateService::LOGO_DISK)->assertExists($storedPath);
});

// New: `logo` is optional — omitting it entirely still succeeds.
it('creates a corporate successfully when logo is omitted', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/corporates', [
        'corporate_code' => 'COR-API-LOGO-002',
        'name' => 'PT Tanpa Logo',
    ]);

    $response->assertStatus(201);
    $response->assertJson(['logo' => null, 'logo_url' => null]);
});

// New: an oversized logo (> 2MB) -> 422 errors.logo.
it('returns 422 with errors.logo when the logo file exceeds the max size', function () {
    Storage::fake(CorporateService::LOGO_DISK);

    $response = $this->actingAs($this->admin, 'web')->post('/api/corporates', [
        'corporate_code' => 'COR-API-LOGO-003',
        'name' => 'PT Logo Besar',
        'logo' => UploadedFile::fake()->create('logo.jpg', 3000, 'image/jpeg'),
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['logo']);
    expect(Corporate::where('corporate_code', 'COR-API-LOGO-003')->exists())->toBeFalse();
});

// New: a disallowed mime type (e.g. PDF) -> 422 errors.logo.
it('returns 422 with errors.logo when the logo file has a disallowed mime type', function () {
    Storage::fake(CorporateService::LOGO_DISK);

    $response = $this->actingAs($this->admin, 'web')->post('/api/corporates', [
        'corporate_code' => 'COR-API-LOGO-004',
        'name' => 'PT Logo Pdf',
        'logo' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['logo']);
    expect(Corporate::where('corporate_code', 'COR-API-LOGO-004')->exists())->toBeFalse();
});

// New: created_by is always derived server-side from the authenticated
// admin — a spoofed created_by in the request payload must not override it.
it('sets created_by from the authenticated admin and ignores a spoofed created_by in the payload', function () {
    $spoofed = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->businessUnit)->create();

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/corporates', [
        'corporate_code' => 'COR-API-AUDIT-001',
        'name' => 'PT Audit Trail',
        'created_by' => $spoofed->id,
    ]);

    $response->assertStatus(201);
    $response->assertJson(['created_by' => $this->admin->id]);
    expect(Corporate::where('name', 'PT Audit Trail')->firstOrFail()->created_by)->toBe($this->admin->id);
});

// New: updated_by is always derived server-side from the authenticated
// admin — a spoofed updated_by in the request payload must not override it.
it('sets updated_by from the authenticated admin and ignores a spoofed updated_by in the payload', function () {
    $spoofed = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->businessUnit)->create();
    $corporate = Corporate::factory()->create(['corporate_code' => 'COR-API-AUDIT-002', 'name' => 'PT Lama Sekali']);

    $response = $this->actingAs($this->admin, 'web')->patchJson("/api/corporates/{$corporate->id}", [
        'corporate_code' => 'COR-API-AUDIT-002',
        'name' => 'PT Baru Sekali',
        'updated_by' => $spoofed->id,
    ]);

    $response->assertOk();
    $response->assertJson(['updated_by' => $this->admin->id]);
    expect($corporate->fresh()->updated_by)->toBe($this->admin->id);
});

// New: optional contact/legal fields round-trip through the API — accepted,
// persisted, returned.
it('accepts, persists, and returns optional contact/legal fields when provided', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/corporates', [
        'corporate_code' => 'COR-API-FIELDS-001',
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

    $fresh = Corporate::where('name', 'PT Lengkap')->firstOrFail();
    expect($fresh->short_name)->toBe('PSM');
    expect($fresh->labor_union)->toBe('Serikat Pekerja PT Lengkap');
});

// New: omitting every optional field does not error.
it('creates a corporate successfully when every optional field is omitted', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/corporates', [
        'corporate_code' => 'COR-API-FIELDS-002',
        'name' => 'PT Minimal',
    ]);

    $response->assertStatus(201);
    $response->assertJson(['short_name' => null, 'address' => null, 'labor_union' => null]);
});

// Scenario 6: "Kelola Corporate — akses ditolak untuk selain Admin"
it('akses ditolak: returns 403 for the list endpoint when the user is not an admin', function (string $role) {
    $user = match ($role) {
        'supervisor' => $this->supervisor,
        'mill_management' => $this->millManagement,
        'operator' => $this->operator,
    };

    $response = $this->actingAs($user, 'web')->getJson('/api/corporates');

    $response->assertStatus(403);
    $response->assertJsonStructure(['message']);
})->with([
    'supervisor' => ['supervisor'],
    'mill management' => ['mill_management'],
    'operator' => ['operator'],
]);

// Auth-guard coverage: unauthenticated requests must not reach the service
// at all.
it('returns 401 for the list endpoint when there is no authenticated session', function () {
    $response = $this->getJson('/api/corporates');

    $response->assertStatus(401);
});

// Baseline happy-path list coverage (admin, business_logic step 1:
// company_count per row, pagination meta shape).
it('lists corporates paginated with company_count per row for an admin', function () {
    $corporate = Corporate::factory()->create();
    Company::factory()->count(2)->create(['corporate_id' => $corporate->id]);

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/corporates');

    // meta.total reflects every Corporate row that actually exists, not just
    // the one created explicitly above: beforeEach's shared $this->businessUnit
    // (used by all 4 actor users, see beforeEach) still cascades through
    // BusinessUnitFactory's default company_id => Company::factory() down to
    // CompanyFactory's default corporate_id => Corporate::factory(), producing
    // one incidental Corporate row alongside the one created explicitly here.
    $response->assertOk();
    $response->assertJson([
        'meta' => [
            'page' => 1,
            'per_page' => 20,
            'total' => Corporate::count(),
            'total_pages' => 1,
        ],
    ]);
    expect(Corporate::count())->toBe(2);
    $response->assertJsonFragment(['company_count' => 2]);
});
