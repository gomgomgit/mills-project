<?php

/**
 * PeriodServiceTest — screen-128--kelola-periode-pelaporan /
 * usecase-128--kelola-periode-pelaporan (Kelola Periode Pelaporan).
 *
 * Unit tests for App\Services\PeriodService, mirroring
 * BusinessUnitServiceTest.php / ThreshingRecordServiceTest.php's structure
 * (`uses(TestCase::class, RefreshDatabase::class)` at the top is MANDATORY
 * — without it the factories' faker calls fail with "Unknown format").
 *
 * COVERAGE MAP — this file covers unit_test_cases 1–29 of the screen
 * tech-spec (usecase-128); cases 30–46 (usecase-140: unverifiedCount /
 * close / reopen) live in PeriodClosureServiceTest.php. Each test below
 * carries the tech-spec case number it implements.
 *
 * WHY TWO TESTS GO THROUGH HTTP (cases 1, 2, 10): authentication and the
 * admin-only rule are NOT enforced inside PeriodService — they are route
 * middleware ('auth:web' + 'role:admin' in routes/api.php, see
 * App\Http\Middleware\EnsureRole). Asserting them on the service would
 * assert nothing at all, so those three cases exercise the real route.
 * NOTE: EnsureRole builds its own JSON response and never reaches
 * ApiExceptionHandler, so a 403 from it carries `message` only — no `code`
 * field (documented in the screen's 4-implement known_issues). These tests
 * assert the status and the message, deliberately not `code`.
 *
 * SCOPE OF A PERIOD is (business_unit_id, station_type), station_type NULL
 * meaning "every station type in this mill" — the wildcard behaviour in
 * both directions is what cases 20 and 21 pin down. `station_type` is a FK
 * to `station_types`.`code` (seeded by migration 2026_09_22_000029), so
 * every value used here ('sterilizer', 'boiler-room', 'clarification') is
 * a real row in that master table, never an invented string.
 */

use App\Enums\PeriodStatus;
use App\Enums\UserRole;
use App\Exceptions\PeriodClosedImmutableException;
use App\Exceptions\PeriodOverlapException;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\User;
use App\Services\PeriodService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new PeriodService;

    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->admin = User::factory()->role(UserRole::Admin)->create(['name' => 'Admin X']);
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();

    // PeriodService::create()/update() stamp created_by/updated_by from
    // auth()->id(), and periods.created_by is NOT NULL.
    $this->actingAs($this->admin, 'web');
});

/**
 * Valid create payload; override only what a test is actually about.
 */
function periodPayload(array $overrides = []): array
{
    return array_merge([
        'business_unit_id' => null,
        'station_type' => 'sterilizer',
        'name' => 'Oktober 2026',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-31',
    ], $overrides);
}

// ── list (cases 1–9) ────────────────────────────────────────────────────

// Case 1
it('menolak 401 UNAUTHENTICATED saat GET /api/periods tanpa sesi', function () {
    // Drop the guard instance actingAs() primed in beforeEach, so this
    // request really arrives without an authenticated session.
    $this->app['auth']->forgetGuards();

    $response = $this->getJson('/api/periods');

    $response->assertStatus(401);
    $response->assertJsonMissingPath('data');
});

// Case 2
it('menolak 403 FORBIDDEN pada GET /api/periods saat actor bukan Admin', function () {
    Period::factory()->forBusinessUnit($this->businessUnitA)->create();

    $response = $this->actingAs($this->supervisor, 'web')->getJson('/api/periods');

    $response->assertStatus(403);
    $response->assertJsonStructure(['message']);
    $response->assertJsonMissingPath('data');
});

// Case 3
it('memakai default page=1 dan per_page=20 ketika query kosong', function () {
    Period::factory()->forBusinessUnit($this->businessUnitA)->count(35)->create();

    $result = $this->service->listPeriods(1, 20);

    expect($result['data'])->toHaveCount(20);
    expect($result['meta']['page'])->toBe(1);
    expect($result['meta']['per_page'])->toBe(20);
    expect($result['meta']['total'])->toBe(35);
});

// Case 4
it('membatasi per_page maksimal 100 ketika diminta lebih besar', function () {
    Period::factory()->forBusinessUnit($this->businessUnitA)->count(3)->create();

    $result = $this->service->listPeriods(1, 500);

    expect($result['meta']['per_page'])->toBe(100);
});

// Case 5
it('menyaring daftar berdasarkan business_unit_id', function () {
    Period::factory()->forBusinessUnit($this->businessUnitA)->count(2)->create();
    Period::factory()->forBusinessUnit($this->businessUnitB)->count(3)->create();

    $result = $this->service->listPeriods(1, 20, $this->businessUnitA->id);

    expect($result['data'])->toHaveCount(2);
    expect($result['meta']['total'])->toBe(2);
    expect(collect($result['data'])->every(
        fn (array $row) => $row['business_unit_id'] === $this->businessUnitA->id
    ))->toBeTrue();
});

// Case 6
it('menyaring daftar berdasarkan status', function () {
    Period::factory()->forBusinessUnit($this->businessUnitA)->draft()->create();
    Period::factory()->forBusinessUnit($this->businessUnitA)->open()->create();
    Period::factory()->forBusinessUnit($this->businessUnitA)->closed($this->admin)->create();

    $result = $this->service->listPeriods(1, 20, null, 'closed');

    expect($result['data'])->toHaveCount(1);
    expect($result['data'][0]['status'])->toBe('closed');
});

// Case 7
it("menampilkan station_type_label 'Semua Stasiun' ketika station_type NULL", function () {
    Period::factory()->forBusinessUnit($this->businessUnitA)->stationType(null)->create();

    $result = $this->service->listPeriods(1, 20);

    expect($result['data'][0]['station_type'])->toBeNull();
    expect($result['data'][0]['station_type_label'])->toBe('Semua Stasiun');
});

// Case 8
it('mengembalikan closed_by_name dan closed_at untuk periode tertutup', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->closed($this->admin, '2026-11-01 09:14:00')
        ->create();

    $result = $this->service->listPeriods(1, 20);

    $row = collect($result['data'])->firstWhere('id', $period->id);

    expect($row['closed_by'])->toBe($this->admin->id);
    expect($row['closed_by_name'])->toBe('Admin X');
    expect($row['closed_at'])->not->toBeNull();
    // ISO 8601 (Carbon::toIso8601String()).
    expect($row['closed_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
});

// Case 9
it('mengembalikan data kosong ketika tidak ada periode sesuai filter', function () {
    Period::factory()->forBusinessUnit($this->businessUnitA)->draft()->create();

    $result = $this->service->listPeriods(1, 20, $this->businessUnitB->id, 'closed');

    expect($result['data'])->toBe([]);
    expect($result['meta']['total'])->toBe(0);
});

// ── businessUnitOptions (cases 10–12) ───────────────────────────────────

// Case 10
it('businessUnitOptions: menolak 403 ketika actor bukan Admin', function () {
    $response = $this->actingAs($this->supervisor, 'web')
        ->getJson('/api/periods/business-units/options');

    $response->assertStatus(403);
    $response->assertJsonStructure(['message']);
});

// Case 11
it('businessUnitOptions: mengembalikan daftar kosong ketika belum ada Business Unit', function () {
    Period::query()->delete();
    User::query()->update(['business_unit_id' => null]);
    BusinessUnit::query()->delete();

    expect($this->service->businessUnitOptions())->toBe([]);
});

// Case 12
it('businessUnitOptions: mengembalikan id dan name seluruh Business Unit terurut nama', function () {
    Period::query()->delete();
    User::query()->update(['business_unit_id' => null]);
    BusinessUnit::query()->delete();

    BusinessUnit::factory()->create(['name' => 'Zeta Mill']);
    BusinessUnit::factory()->create(['name' => 'Alpha Mill']);
    BusinessUnit::factory()->create(['name' => 'Beta Mill']);

    $options = $this->service->businessUnitOptions();

    expect($options)->toHaveCount(3);
    expect(array_column($options, 'name'))->toBe(['Alpha Mill', 'Beta Mill', 'Zeta Mill']);
    expect($options[0])->toHaveKeys(['id', 'name']);
});

// ── create (cases 13–22) ────────────────────────────────────────────────

// Case 13
it('create: menolak 422 ketika business_unit_id kosong', function () {
    try {
        $this->service->create(periodPayload(['business_unit_id' => null]));
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('business_unit_id');
    }

    expect(Period::count())->toBe(0);
});

// Case 14
it('create: menolak 422 ketika business_unit_id tidak ditemukan', function () {
    try {
        $this->service->create(periodPayload([
            'business_unit_id' => '00000000-0000-0000-0000-000000000000',
        ]));
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('business_unit_id');
    }

    expect(Period::count())->toBe(0);
});

// Case 15
it('create: menolak 422 ketika name kosong', function () {
    try {
        $this->service->create(periodPayload([
            'business_unit_id' => $this->businessUnitA->id,
            'name' => '',
        ]));
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('name');
    }

    expect(Period::count())->toBe(0);
});

// Case 16
it('create: menolak 422 ketika name sudah dipakai pada (business_unit_id, station_type) yang sama', function () {
    Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->create();

    try {
        $this->service->create(periodPayload([
            'business_unit_id' => $this->businessUnitA->id,
            'station_type' => 'sterilizer',
            'name' => 'Oktober 2026',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-31',
        ]));
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('name');
    }

    // No INSERT — the pre-existing row is still the only one.
    expect(Period::count())->toBe(1);
});

// Case 17
it('create: mengizinkan name sama pada station_type berbeda di mill yang sama', function () {
    Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->create();

    $row = $this->service->create(periodPayload([
        'business_unit_id' => $this->businessUnitA->id,
        'station_type' => 'boiler-room',
        'name' => 'Oktober 2026',
        'start_date' => '2026-12-01',
        'end_date' => '2026-12-31',
    ]));

    expect($row['name'])->toBe('Oktober 2026');
    expect($row['station_type'])->toBe('boiler-room');
    expect(Period::count())->toBe(2);
});

// Case 18
it('create: menolak 422 ketika end_date lebih awal dari start_date', function () {
    try {
        $this->service->create(periodPayload([
            'business_unit_id' => $this->businessUnitA->id,
            'start_date' => '2026-10-31',
            'end_date' => '2026-10-01',
        ]));
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('end_date');
    }

    expect(Period::count())->toBe(0);
});

// Case 19
it('create: melempar PeriodOverlapException ketika rentang beririsan pada cakupan sama', function () {
    Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('September-Oktober 2026')
        ->range('2026-09-15', '2026-10-15')
        ->create();

    try {
        $this->service->create(periodPayload([
            'business_unit_id' => $this->businessUnitA->id,
            'station_type' => 'sterilizer',
            'name' => 'Oktober Tambahan',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]));
        $this->fail('Expected PeriodOverlapException was not thrown.');
    } catch (PeriodOverlapException $e) {
        expect($e->getStatusCode())->toBe(422);
        expect($e->errorCode())->toBe('PERIOD_OVERLAP');
        // The message must name the conflicting period.
        expect($e->getMessage())->toContain('September-Oktober 2026');
    }

    expect(Period::count())->toBe(1);
});

// Case 20
it('create: melempar PeriodOverlapException ketika periode station_type NULL beririsan dengan periode jenis tertentu', function () {
    Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober Sterilizer')
        ->range('2026-10-01', '2026-10-31')
        ->create();

    expect(fn () => $this->service->create(periodPayload([
        'business_unit_id' => $this->businessUnitA->id,
        'station_type' => null,
        'name' => 'Semua Stasiun Okt',
        'start_date' => '2026-10-10',
        'end_date' => '2026-10-20',
    ])))->toThrow(PeriodOverlapException::class);

    expect(Period::count())->toBe(1);
});

// Case 21
it('create: melempar PeriodOverlapException ketika periode jenis tertentu beririsan dengan periode NULL yang sudah ada', function () {
    Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType(null)
        ->named('Semua Stasiun Okt')
        ->range('2026-10-01', '2026-10-31')
        ->create();

    expect(fn () => $this->service->create(periodPayload([
        'business_unit_id' => $this->businessUnitA->id,
        'station_type' => 'clarification',
        'name' => 'Oktober Clarification',
        'start_date' => '2026-10-10',
        'end_date' => '2026-10-20',
    ])))->toThrow(PeriodOverlapException::class);

    expect(Period::count())->toBe(1);
});

// Case 22
it('create: menyimpan periode baru dengan status draft ketika seluruh validasi lolos', function () {
    $row = $this->service->create(periodPayload([
        'business_unit_id' => $this->businessUnitA->id,
        'station_type' => 'sterilizer',
        'name' => 'Oktober 2026',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-31',
    ]));

    expect($row['status'])->toBe('draft');
    expect($row['closed_by'])->toBeNull();
    expect($row['closed_at'])->toBeNull();
    expect($row['business_unit_name'])->toBe('Mill Alpha');
    expect($row['station_type_label'])->toBe('Sterilizer');

    $stored = Period::findOrFail($row['id']);
    expect($stored->status)->toBe(PeriodStatus::Draft);
    expect($stored->created_by)->toBe($this->admin->id);
    expect($stored->start_date->toDateString())->toBe('2026-10-01');
    expect($stored->end_date->toDateString())->toBe('2026-10-31');
});

// ── update (cases 23–26) ────────────────────────────────────────────────

// Case 23
it('update: melempar 404 ketika periode tidak ditemukan (sudah dihapus Admin lain)', function () {
    expect(fn () => $this->service->update(
        '00000000-0000-0000-0000-000000000000',
        periodPayload(['business_unit_id' => $this->businessUnitA->id])
    ))->toThrow(ModelNotFoundException::class);
});

// Case 24
it('update: menolak 409 PERIOD_CLOSED_IMMUTABLE ketika periode berstatus closed', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->closed($this->admin)
        ->create();

    try {
        $this->service->update($period->id, periodPayload([
            'business_unit_id' => $this->businessUnitA->id,
            'station_type' => 'sterilizer',
            'name' => 'Oktober 2026 Revisi',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]));
        $this->fail('Expected PeriodClosedImmutableException was not thrown.');
    } catch (PeriodClosedImmutableException $e) {
        expect($e->getStatusCode())->toBe(409);
        expect($e->errorCode())->toBe('PERIOD_CLOSED_IMMUTABLE');
        expect($e->getMessage())->toContain('Buka kembali periode terlebih dahulu');
    }

    // No UPDATE — the row is bit-for-bit unchanged.
    expect($period->fresh()->name)->toBe('Oktober 2026');
    expect($period->fresh()->status)->toBe(PeriodStatus::Closed);
});

// Case 25
it('update: melempar PeriodOverlapException ketika rentang baru beririsan, mengecualikan dirinya sendiri', function () {
    $p1 = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-15')
        ->open()
        ->create();

    Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('November 2026')
        ->range('2026-11-01', '2026-11-30')
        ->open()
        ->create();

    expect(fn () => $this->service->update($p1->id, periodPayload([
        'business_unit_id' => $this->businessUnitA->id,
        'station_type' => 'sterilizer',
        'name' => 'Oktober 2026',
        'start_date' => '2026-10-20',
        'end_date' => '2026-11-05',
    ])))->toThrow(PeriodOverlapException::class);

    expect($p1->fresh()->start_date->toDateString())->toBe('2026-10-01');
    expect($p1->fresh()->end_date->toDateString())->toBe('2026-10-15');
});

// Case 26
it('update: mengizinkan perubahan rentang yang tidak menimbulkan tumpang tindih pada periode terbuka', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-15')
        ->open()
        ->create();

    $row = $this->service->update($period->id, periodPayload([
        'business_unit_id' => $this->businessUnitA->id,
        'station_type' => 'sterilizer',
        'name' => 'Oktober 2026',
        'start_date' => '2026-10-05',
        'end_date' => '2026-10-20',
    ]));

    expect($row['start_date'])->toBe('2026-10-05');
    expect($row['end_date'])->toBe('2026-10-20');
    expect($row['status'])->toBe('open');

    $fresh = $period->fresh();
    expect($fresh->start_date->toDateString())->toBe('2026-10-05');
    expect($fresh->updated_by)->toBe($this->admin->id);
});

// ── delete (cases 27–29) ────────────────────────────────────────────────

// Case 27
it('delete: melempar 404 ketika periode sudah dihapus pengguna lain', function () {
    expect(fn () => $this->service->delete('00000000-0000-0000-0000-000000000000'))
        ->toThrow(ModelNotFoundException::class);
});

// Case 28
it('delete: menolak 409 PERIOD_CLOSED_IMMUTABLE ketika periode berstatus closed', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->closed($this->admin)
        ->create();

    try {
        $this->service->delete($period->id);
        $this->fail('Expected PeriodClosedImmutableException was not thrown.');
    } catch (PeriodClosedImmutableException $e) {
        expect($e->getStatusCode())->toBe(409);
        expect($e->errorCode())->toBe('PERIOD_CLOSED_IMMUTABLE');
    }

    expect(Period::find($period->id))->not->toBeNull();
});

// Case 29
it('delete: menghapus periode ketika seluruh syarat terpenuhi', function (string $status) {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->state(['status' => $status])
        ->create();

    $this->service->delete($period->id);

    expect(Period::find($period->id))->toBeNull();
})->with([
    'draft' => ['draft'],
    'open' => ['open'],
]);

// ── supporting behaviour of the same use case ───────────────────────────

it('findOverlapping: memperlakukan batas rentang sebagai inklusif', function () {
    Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->create();

    // A range starting exactly on the existing end_date DOES overlap.
    expect($this->service->findOverlapping(
        $this->businessUnitA->id,
        'sterilizer',
        '2026-10-31',
        '2026-11-30'
    ))->not->toBeNull();

    // One day later it does not.
    expect($this->service->findOverlapping(
        $this->businessUnitA->id,
        'sterilizer',
        '2026-11-01',
        '2026-11-30'
    ))->toBeNull();
});

it('findOverlapping: tidak melihat periode milik Business Unit lain', function () {
    Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->stationType('sterilizer')
        ->range('2026-10-01', '2026-10-31')
        ->create();

    expect($this->service->findOverlapping(
        $this->businessUnitA->id,
        'sterilizer',
        '2026-10-05',
        '2026-10-10'
    ))->toBeNull();
});

it('stationTypeOptions: membaca tabel master station_types dalam urutan proses', function () {
    $options = $this->service->stationTypeOptions();

    expect(count($options))->toBeGreaterThanOrEqual(18);
    expect($options[0])->toHaveKeys(['code', 'name']);
    expect($options[0]['code'])->toBe('weighbridge');
    expect(collect($options)->pluck('code'))->toContain('sterilizer');
});

it('stationTypeLabel: memakai nama dari tabel master dan fallback ke kode tak dikenal', function () {
    expect($this->service->stationTypeLabel('sterilizer'))->toBe('Sterilizer');
    expect($this->service->stationTypeLabel(null))->toBe('Semua Stasiun');
    expect($this->service->stationTypeLabel('kode-tidak-dikenal'))->toBe('kode-tidak-dikenal');
});

it('create: menolak 422 ketika station_type tidak ada di tabel master station_types', function () {
    try {
        $this->service->create(periodPayload([
            'business_unit_id' => $this->businessUnitA->id,
            'station_type' => 'stasiun-karangan',
        ]));
        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('station_type');
    }

    expect(Period::count())->toBe(0);
});
