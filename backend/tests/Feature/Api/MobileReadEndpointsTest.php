<?php

/**
 * MobileReadEndpointsTest (Feature/Api) — dua endpoint baca untuk mobile
 * (audit 2026-10-04):
 *   - GET /api/grading-parameters
 *   - GET /api/records/{stationType}/verification?ids[]=…
 *
 * Akses operator diuji lewat TOKEN SANCTUM sungguhan (Authorization: Bearer),
 * persis jalur yang dipakai aplikasi mobile — bukan actingAs() guard web.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\CagesTrackRecord;
use App\Models\GradingParameter;
use App\Models\GradingRecord;
use App\Models\Station;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->businessUnit = BusinessUnit::factory()->create();
    $this->station = Station::factory()->forBusinessUnit($this->businessUnit)->create();

    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnit)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnit)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnit)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create();

    $this->record = CagesTrackRecord::factory()->forStation($this->station)->create([
        'checked_by' => $this->supervisor->id,
        'acknowledged_by' => $this->millManagement->id,
    ]);
    $this->unverified = CagesTrackRecord::factory()->forStation($this->station)->create([
        'checked_by' => null,
        'acknowledged_by' => null,
    ]);

    $this->otherBusinessUnit = BusinessUnit::factory()->create();
    $this->otherStation = Station::factory()->forBusinessUnit($this->otherBusinessUnit)->create();
    $this->otherRecord = CagesTrackRecord::factory()->forStation($this->otherStation)->create([
        'checked_by' => null,
        'acknowledged_by' => null,
    ]);
});

function operatorBearer(User $user): array
{
    return ['Authorization' => 'Bearer '.$user->createToken('hp-test')->plainTextToken];
}

function verificationStatusUrl(string $stationType, array $ids): string
{
    return "/api/records/{$stationType}/verification?".http_build_query(['ids' => $ids]);
}

// ---------------------------------------------------------------------------
// GET /api/grading-parameters
// ---------------------------------------------------------------------------

it('grading-parameters: operator dengan token Sanctum menerima daftar urut sort_order dengan bentuk yang diharapkan mobile', function () {
    GradingParameter::factory()->create(['name' => 'Masak', 'uom' => 'bunch', 'sort_order' => 3]);
    GradingParameter::factory()->create(['name' => 'Mentah', 'uom' => 'bunch', 'sort_order' => 1]);
    GradingParameter::factory()->create(['name' => 'Brondolan Segar', 'uom' => 'kg', 'sort_order' => 2]);

    $response = $this->withHeaders(operatorBearer($this->operator))
        ->getJson('/api/grading-parameters')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'uom', 'sort_order']]]);

    expect(collect($response->json('data'))->pluck('name')->all())->toBe(['Mentah', 'Brondolan Segar', 'Masak']);
    expect($response->json('data.1.uom'))->toBe('kg');
    expect($response->json('data.1.sort_order'))->toBe(2);
    expect(Str::isUuid($response->json('data.0.id')))->toBeTrue();
});

it('grading-parameters: ke-4 peran boleh membaca', function (string $roleProperty) {
    GradingParameter::factory()->create();

    $this->actingAs($this->{$roleProperty}, 'web')
        ->getJson('/api/grading-parameters')
        ->assertOk()
        ->assertJsonCount(1, 'data');
})->with(['admin', 'supervisor', 'millManagement', 'operator']);

it('grading-parameters: tanpa autentikasi ditolak 401', function () {
    $this->getJson('/api/grading-parameters')->assertUnauthorized();
});

// ---------------------------------------------------------------------------
// GET /api/records/{stationType}/verification
// ---------------------------------------------------------------------------

it('verification status: operator dengan token Sanctum menerima status + nama verifikator', function () {
    $response = $this->withHeaders(operatorBearer($this->operator))
        ->getJson(verificationStatusUrl('cages-track', [$this->record->id, $this->unverified->id]))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $byId = collect($response->json('data'))->keyBy('id');

    expect($byId[$this->record->id])->toBe([
        'id' => $this->record->id,
        'checked_by' => $this->supervisor->id,
        'checked_by_name' => $this->supervisor->name,
        'acknowledged_by' => $this->millManagement->id,
        'acknowledged_by_name' => $this->millManagement->name,
    ]);
    expect($byId[$this->unverified->id])->toBe([
        'id' => $this->unverified->id,
        'checked_by' => null,
        'checked_by_name' => null,
        'acknowledged_by' => null,
        'acknowledged_by_name' => null,
    ]);
});

it('verification status: ke-4 peran boleh membaca', function (string $roleProperty) {
    $this->actingAs($this->{$roleProperty}, 'web')
        ->getJson(verificationStatusUrl('cages-track', [$this->record->id]))
        ->assertOk()
        ->assertJsonPath('data.0.id', $this->record->id);
})->with(['admin', 'supervisor', 'millManagement', 'operator']);

it('verification status: tanpa autentikasi ditolak 401', function () {
    $this->getJson(verificationStatusUrl('cages-track', [$this->record->id]))->assertUnauthorized();
});

it('verification status: id record mill lain TIDAK muncul untuk aktor terikat mill', function (string $roleProperty) {
    $this->actingAs($this->{$roleProperty}, 'web')
        ->getJson(verificationStatusUrl('cages-track', [$this->record->id, $this->otherRecord->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->record->id);
})->with(['operator', 'supervisor', 'millManagement']);

it('verification status: id record mill lain juga TIDAK muncul lewat token Sanctum operator', function () {
    $this->withHeaders(operatorBearer($this->operator))
        ->getJson(verificationStatusUrl('cages-track', [$this->otherRecord->id]))
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

it('verification status: Admin melihat record di DUA mill', function () {
    $this->actingAs($this->admin, 'web')
        ->getJson(verificationStatusUrl('cages-track', [$this->record->id, $this->otherRecord->id]))
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('verification status: aktor terikat mill tanpa mill gagal-tertutup 422', function () {
    $millless = User::factory()->role(UserRole::Operator)->create(['business_unit_id' => null]);

    $this->actingAs($millless, 'web')
        ->getJson(verificationStatusUrl('cages-track', [$this->record->id]))
        ->assertUnprocessable();
});

it('verification status: id yang tidak dikenal atau milik stasiun lain cukup tidak muncul', function () {
    $grading = GradingRecord::factory()->forStation($this->station)->create();

    $this->actingAs($this->operator, 'web')
        ->getJson(verificationStatusUrl('cages-track', [(string) Str::uuid(), $grading->id]))
        ->assertOk()
        ->assertExactJson(['data' => []]);
});

it('verification status: id bukan UUID ditolak 422 dengan pesan Bahasa Indonesia', function () {
    $this->actingAs($this->operator, 'web')
        ->getJson(verificationStatusUrl('cages-track', [$this->record->id, 'bukan-uuid']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ids.1' => 'Id record harus berupa UUID.']);
});

it('verification status: tanpa ids ditolak 422', function () {
    $this->actingAs($this->operator, 'web')
        ->getJson('/api/records/cages-track/verification')
        ->assertUnprocessable()
        ->assertJsonPath('errors.ids.0', 'Daftar id record wajib diisi.');
});

it('verification status: lebih dari 200 id ditolak 422', function () {
    $ids = array_map(fn () => (string) Str::uuid(), range(1, 201));

    $this->actingAs($this->operator, 'web')
        ->getJson(verificationStatusUrl('cages-track', $ids))
        ->assertUnprocessable()
        ->assertJsonPath('errors.ids.0', 'Paling banyak 200 id record per permintaan.');
});

it('verification status: jenis stasiun tidak dikenal → 404, sama dengan PATCH-nya', function () {
    $this->actingAs($this->operator, 'web')
        ->getJson(verificationStatusUrl('bukan-stasiun', [$this->record->id]))
        ->assertNotFound()
        ->assertJsonPath('message', 'Jenis stasiun tidak dikenal.');

    $this->actingAs($this->supervisor, 'web')
        ->patchJson("/api/records/bukan-stasiun/{$this->record->id}/verification", ['level' => 'checked', 'value' => true])
        ->assertNotFound()
        ->assertJsonPath('message', 'Jenis stasiun tidak dikenal.');
});

// Temuan audit 2026-10-05 #11: 404 verifikasi hanya membawa `message`;
// sekarang amplop error standar dengan `code` NOT_FOUND seperti endpoint lain.
it('verification: 404 (jenis stasiun tak dikenal / record tak ada) membawa code NOT_FOUND', function () {
    $this->actingAs($this->operator, 'web')
        ->getJson(verificationStatusUrl('bukan-stasiun', [$this->record->id]))
        ->assertNotFound()
        ->assertExactJson(['message' => 'Jenis stasiun tidak dikenal.', 'code' => 'NOT_FOUND']);

    $this->actingAs($this->supervisor, 'web')
        ->patchJson("/api/records/bukan-stasiun/{$this->record->id}/verification", ['level' => 'checked', 'value' => true])
        ->assertNotFound()
        ->assertExactJson(['message' => 'Jenis stasiun tidak dikenal.', 'code' => 'NOT_FOUND']);

    $this->actingAs($this->supervisor, 'web')
        ->patchJson('/api/records/cages-track/'.Str::uuid().'/verification', ['level' => 'checked', 'value' => true])
        ->assertNotFound()
        ->assertExactJson(['message' => 'Record tidak ditemukan.', 'code' => 'NOT_FOUND']);
});
