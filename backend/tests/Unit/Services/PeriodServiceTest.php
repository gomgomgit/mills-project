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
 * WHAT CHANGED 2026-09-25 AND WHAT THESE TESTS NOW PIN DOWN
 * A period's scope is its MILL, full stop — `periods.station_type` is gone
 * and so is the NULL = "every station type" wildcard. The consequences are
 * each guarded by a test here:
 *
 *  - ONE DATE, ONE PERIOD PER MILL: overlap is rejected even when the two
 *    periods carry different station types (cases 20/21) — that exact pair
 *    of cases previously asserted the OPPOSITE via the NULL wildcard.
 *  - NAME UNIQUENESS is per mill, not per (mill, station type) — case 16
 *    rejects, case 17 allows the same name in a DIFFERENT mill only.
 *  - `station_type` IS NOT AN INPUT: a caller that still sends one is
 *    ignored, never 422'd (see "mengabaikan station_type" below).
 *  - create() DERIVES the station list from the mill's own active stations
 *    (case 22 + the three activeStationTypesForMill tests).
 *  - update()/delete() refuse when AT LEAST ONE station row is closed
 *    (cases 24/28 plus the two "hanya satu stasiun tertutup" tests).
 *  - toRow() is nested: per-station status/closure live in `stations[]`,
 *    the parent has `status_summary`/`is_immutable` instead of `status`.
 *
 * Every station type used here ('sterilizer', 'boiler-room',
 * 'clarification') is a real row in the `station_types` master table
 * (seeded by migration 2026_09_22_000029) — `period_stations.station_type`
 * and `stations.type` are both FKs to its `code`, never invented strings.
 */

use App\Enums\PeriodStatus;
use App\Enums\UserRole;
use App\Exceptions\PeriodClosedImmutableException;
use App\Exceptions\PeriodOverlapException;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\Station;
use App\Models\StationType;
use App\Models\User;
use App\Services\PeriodService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new PeriodService;

    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->admin = User::factory()->role(UserRole::Admin)->create(['name' => 'Admin X']);
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();

    // BusinessUnit::factory() creates no production line and no station at
    // all, so a mill has NO station types until a test gives it some — and
    // PeriodService::create() derives a period's station rows from exactly
    // that inventory. Mill Alpha gets three of the 19 master types so the
    // difference between "the mill's types" and "every master type" is
    // visible in every create() assertion below.
    millStations($this->businessUnitA, ['sterilizer', 'boiler-room', 'clarification']);

    // PeriodService::create()/update() stamp created_by/updated_by from
    // auth()->id(), and periods.created_by is NOT NULL.
    $this->actingAs($this->admin, 'web');
});

afterEach(function () {
    // Test panel "Periode Terbuka Hari Ini" membekukan tanggal; tanpa ini,
    // sebuah kegagalan di tengah test akan membocorkan waktu palsu ke test
    // berikutnya dan membuat kegagalan berikutnya tidak bisa dipercaya.
    Carbon::setTestNow();
});

/**
 * Gives a mill one ACTIVE station per type. Station::factory()->
 * forBusinessUnit() auto-creates a matching ProductionLine, so the two FKs
 * never disagree.
 *
 * @param  list<string>  $types
 */
function millStations(BusinessUnit $businessUnit, array $types): void
{
    foreach ($types as $type) {
        Station::factory()
            ->forBusinessUnit($businessUnit)
            ->create(['type' => $type]);
    }
}

/**
 * Valid create payload; override only what a test is actually about.
 * `station_type` is deliberately absent — a period takes no station choice
 * from its caller since 2026-09-25.
 */
function periodPayload(array $overrides = []): array
{
    return array_merge([
        'business_unit_id' => null,
        'name' => 'Oktober 2026',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-31',
    ], $overrides);
}

/**
 * A period whose station rows are NOT uniform: Sterilizer closed while
 * Clarification is still open. This shape could not exist before
 * 2026-09-25 (one status per period) and is the entire reason
 * `period_stations` was split out.
 */
function halfClosedPeriod(BusinessUnit $businessUnit, User $closer): Period
{
    $period = Period::factory()
        ->forBusinessUnit($businessUnit)
        ->noStations()
        ->named('Oktober 2026 separuh')
        ->range('2026-10-01', '2026-10-31')
        ->create();

    PeriodStation::factory()->forPeriod($period)->stationType('sterilizer')->closed($closer, '2026-11-01 09:14:00')->create();
    PeriodStation::factory()->forPeriod($period)->stationType('clarification')->open()->create();

    return $period->refresh();
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
    Period::factory()->forBusinessUnit($this->businessUnitA)->noStations()->count(35)->create();

    $result = $this->service->listPeriods(1, 20);

    expect($result['data'])->toHaveCount(20);
    expect($result['meta']['page'])->toBe(1);
    expect($result['meta']['per_page'])->toBe(20);
    expect($result['meta']['total'])->toBe(35);
});

// Case 4
it('membatasi per_page maksimal 100 ketika diminta lebih besar', function () {
    Period::factory()->forBusinessUnit($this->businessUnitA)->noStations()->count(3)->create();

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

// Case 6 — the filter now runs through the child table, and it means "HAS
// at least one station in this status", never "all of them are".
it('menyaring daftar berdasarkan status lewat tabel period_stations', function () {
    Period::factory()->forBusinessUnit($this->businessUnitA)->draft()->range('2026-08-01', '2026-08-31')->create();
    Period::factory()->forBusinessUnit($this->businessUnitA)->open()->range('2026-09-01', '2026-09-30')->create();
    $closed = Period::factory()->forBusinessUnit($this->businessUnitA)->closed($this->admin)->range('2026-10-01', '2026-10-31')->create();

    $result = $this->service->listPeriods(1, 20, null, 'closed');

    expect($result['data'])->toHaveCount(1);
    expect($result['data'][0]['id'])->toBe($closed->id);
    expect($result['data'][0]['status_summary'])->toBe('closed');
    expect($result['data'][0]['closed_station_count'])->toBe($result['data'][0]['station_count']);
});

// Case 6b — the reading the filter deliberately does NOT use. Under an
// "all stations are X" rule this period would match neither 'closed' nor
// 'open' and would vanish from every filtered view while plainly existing
// unfiltered; under "any" it shows up in both, which is what it is.
it('menyaring status: periode separuh tertutup muncul pada filter closed DAN open', function () {
    $period = halfClosedPeriod($this->businessUnitA, $this->admin);

    $closedResult = $this->service->listPeriods(1, 20, null, 'closed');
    $openResult = $this->service->listPeriods(1, 20, null, 'open');
    $draftResult = $this->service->listPeriods(1, 20, null, 'draft');

    expect(collect($closedResult['data'])->pluck('id')->all())->toBe([$period->id]);
    expect(collect($openResult['data'])->pluck('id')->all())->toBe([$period->id]);
    // It has no draft station at all, so 'draft' must not match it.
    expect($draftResult['data'])->toBe([]);
});

// Case 7 — replaces the old "station_type_label 'Semua Stasiun' ketika
// station_type NULL": both the NULL scope and that label are gone. The
// station list is explicit now, one entry per registered type.
it('toRow: menyertakan satu entri stations per jenis stasiun beserta labelnya, urut proses', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationTypes(['clarification', 'sterilizer'])
        ->create();

    $result = $this->service->listPeriods(1, 20);
    $row = collect($result['data'])->firstWhere('id', $period->id);

    // Ordered by the master table's sort_order (process order), NOT by the
    // order the rows were inserted in: sterilizer (40) before
    // clarification (70).
    expect(array_column($row['stations'], 'station_type'))->toBe(['sterilizer', 'clarification']);
    expect(array_column($row['stations'], 'station_type_label'))->toBe(['Sterilizer', 'Clarification']);
    expect($row['stations'][0]['id'])->toBe(
        PeriodStation::query()->where('period_id', $period->id)->where('station_type', 'sterilizer')->value('id')
    );
    expect($row['station_count'])->toBe(2);
    expect($row['closed_station_count'])->toBe(0);
    expect($row['is_immutable'])->toBeFalse();
    expect($row['status_summary'])->toBe('draft');

    // The keys that became plural are NOT flattened back onto the parent —
    // a parent `status` in particular would keep the old silently-false
    // `$row['status'] === 'closed'` comparisons alive.
    expect($row)->not->toHaveKey('status');
    expect($row)->not->toHaveKey('station_type');
    expect($row)->not->toHaveKey('closed_by');
    expect($row)->not->toHaveKey('closed_at');
});

// Case 8 — the closure record is per station row now.
it('mengembalikan closed_by_name dan closed_at pada entri stations yang tertutup', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationTypes(['sterilizer'])
        ->closed($this->admin, '2026-11-01 09:14:00')
        ->create();

    $result = $this->service->listPeriods(1, 20);

    $row = collect($result['data'])->firstWhere('id', $period->id);
    $station = $row['stations'][0];

    expect($station['status'])->toBe('closed');
    expect($station['closed_by'])->toBe($this->admin->id);
    expect($station['closed_by_name'])->toBe('Admin X');
    expect($station['closed_at'])->not->toBeNull();
    // ISO 8601 (Carbon::toIso8601String()).
    expect($station['closed_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/');
});

// Case 8b — TWO STATIONS OF ONE PERIOD IN DIFFERENT STATUSES. The shape
// the table split exists for; impossible to express before 2026-09-25.
it('toRow: dua jenis stasiun pada periode yang sama dapat berstatus berbeda', function () {
    $period = halfClosedPeriod($this->businessUnitA, $this->admin);

    $result = $this->service->listPeriods(1, 20);
    $row = collect($result['data'])->firstWhere('id', $period->id);

    expect(collect($row['stations'])->pluck('status', 'station_type')->all())->toBe([
        'sterilizer' => 'closed',
        'clarification' => 'open',
    ]);
    expect($row['station_count'])->toBe(2);
    expect($row['closed_station_count'])->toBe(1);
    // One closed station is enough to freeze the period — same condition
    // update()/delete() refuse on.
    expect($row['is_immutable'])->toBeTrue();
    expect($row['status_summary'])->toBe('mixed');

    // Only the closed one carries a closure record.
    expect(collect($row['stations'])->firstWhere('station_type', 'clarification')['closed_by'])->toBeNull();
    expect(collect($row['stations'])->firstWhere('station_type', 'clarification')['closed_at'])->toBeNull();
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

// Case 16 — uniqueness is scoped to the MILL alone now. The old rule also
// keyed on station_type and matched the NULL scope with whereNull(), which
// PostgreSQL's UNIQUE index can never enforce (every NULL is distinct
// there), so two all-station periods of one mill could share a name.
it('create: menolak 422 ketika name sudah dipakai pada mill yang sama', function () {
    Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationTypes(['sterilizer'])
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->create();

    try {
        $this->service->create(periodPayload([
            'business_unit_id' => $this->businessUnitA->id,
            'name' => 'Oktober 2026',
            // A different station type no longer buys a second period the
            // same name — and the range is far away so this is the name
            // rule failing, not the overlap rule.
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

// Case 17 — the ONLY axis a duplicate name is still allowed on.
it('create: mengizinkan name sama pada mill yang berbeda', function () {
    Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->create();

    $row = $this->service->create(periodPayload([
        'business_unit_id' => $this->businessUnitB->id,
        'name' => 'Oktober 2026',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-31',
    ]));

    expect($row['name'])->toBe('Oktober 2026');
    expect($row['business_unit_id'])->toBe($this->businessUnitB->id);
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
it('create: melempar PeriodOverlapException ketika rentang beririsan pada mill yang sama', function () {
    Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationTypes(['sterilizer'])
        ->named('September-Oktober 2026')
        ->range('2026-09-15', '2026-10-15')
        ->create();

    try {
        $this->service->create(periodPayload([
            'business_unit_id' => $this->businessUnitA->id,
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
        // ...and must no longer claim the collision is station-type-bound.
        expect($e->getMessage())->not->toContain('jenis stasiun');
    }

    expect(Period::count())->toBe(1);
});

// Case 20 — THE RULE THAT INVERTED. Two periods of one mill overlapping on
// different station types used to be perfectly legal (the old clause keyed
// on station_type); it is now refused, because a record dated inside both
// ranges had no answerable period.
it('create: melempar PeriodOverlapException meski jenis stasiunnya berbeda', function () {
    Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationTypes(['boiler-room'])
        ->named('Oktober Boiler')
        ->range('2026-10-01', '2026-10-31')
        ->create();

    try {
        $this->service->create(periodPayload([
            'business_unit_id' => $this->businessUnitA->id,
            'name' => 'Oktober Sterilizer',
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-20',
        ]));
        $this->fail('Expected PeriodOverlapException was not thrown.');
    } catch (PeriodOverlapException $e) {
        expect($e->errorCode())->toBe('PERIOD_OVERLAP');
        expect($e->getMessage())->toContain('Oktober Boiler');
    }

    expect(Period::count())->toBe(1);
});

// Case 21 — the same rule from the other direction: a new range fully
// CONTAINING an existing one is just as much a collision, again regardless
// of which station types either period registers.
it('create: melempar PeriodOverlapException ketika rentang baru memuat periode lain seluruhnya', function () {
    Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationTypes(['clarification'])
        ->named('Pertengahan Oktober')
        ->range('2026-10-10', '2026-10-20')
        ->create();

    expect(fn () => $this->service->create(periodPayload([
        'business_unit_id' => $this->businessUnitA->id,
        'name' => 'Oktober Penuh',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-31',
    ])))->toThrow(PeriodOverlapException::class);

    expect(Period::count())->toBe(1);
});

// Case 22
it('create: menyimpan periode baru dengan seluruh stasiun mill berstatus draft', function () {
    $row = $this->service->create(periodPayload([
        'business_unit_id' => $this->businessUnitA->id,
        'name' => 'Oktober 2026',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-31',
    ]));

    expect($row['business_unit_name'])->toBe('Mill Alpha');
    expect($row['status_summary'])->toBe('draft');
    expect($row['is_immutable'])->toBeFalse();
    expect($row['closed_station_count'])->toBe(0);

    // One row per station type active in THIS mill (3 of the 19 master
    // types), in process order — never the whole master table.
    expect(array_column($row['stations'], 'station_type'))->toBe([
        'sterilizer', 'clarification', 'boiler-room',
    ]);
    expect(collect($row['stations'])->every(fn (array $s) => $s['status'] === 'draft'))->toBeTrue();
    expect(collect($row['stations'])->every(fn (array $s) => $s['closed_by'] === null && $s['closed_at'] === null))->toBeTrue();

    $stored = Period::findOrFail($row['id']);
    expect($stored->created_by)->toBe($this->admin->id);
    expect($stored->start_date->toDateString())->toBe('2026-10-01');
    expect($stored->end_date->toDateString())->toBe('2026-10-31');
    expect($stored->stations()->count())->toBe(3);
    expect($stored->stations()->where('status', PeriodStatus::Draft->value)->count())->toBe(3);
});

// Case 22b
it('create: mengabaikan station_type yang masih dikirim pemanggil lama', function () {
    // Not a 422: the field carries no meaning any more, so an outdated
    // client must not be broken by it — and it must not be able to narrow
    // the station list either.
    $row = $this->service->create(periodPayload([
        'business_unit_id' => $this->businessUnitA->id,
        'station_type' => 'stasiun-karangan',
        'name' => 'Oktober 2026',
    ]));

    expect(array_column($row['stations'], 'station_type'))->toBe([
        'sterilizer', 'clarification', 'boiler-room',
    ]);
    expect($row)->not->toHaveKey('station_type');
});

// Case 22c
it('create: hanya mendaftarkan jenis stasiun yang aktif di mill itu', function () {
    // A station the mill retired, and a master type the mill simply does
    // not have, must BOTH stay out of the period's station list: a closure
    // action that seals nothing is worse than no action.
    Station::factory()->forBusinessUnit($this->businessUnitA)->create([
        'type' => 'threshing',
        'is_active' => false,
    ]);
    StationType::query()->where('code', 'clarification')->update(['is_active' => false]);

    $row = $this->service->create(periodPayload([
        'business_unit_id' => $this->businessUnitA->id,
        'name' => 'Oktober 2026',
    ]));

    expect(array_column($row['stations'], 'station_type'))->toBe(['sterilizer', 'boiler-room']);
});

// Case 22d
it('create: mill tanpa stasiun sama sekali menghasilkan periode tanpa baris stasiun', function () {
    // Mill Beta has no station at all (BusinessUnit::factory() creates
    // none). The period is still created — an Admin may plan periods for a
    // mill still being provisioned, and a mill without stations has no
    // station records that could escape a lock. It simply has nothing to
    // close, and stays editable and deletable.
    $row = $this->service->create(periodPayload([
        'business_unit_id' => $this->businessUnitB->id,
        'name' => 'Oktober Beta',
    ]));

    expect($row['stations'])->toBe([]);
    expect($row['station_count'])->toBe(0);
    expect($row['status_summary'])->toBe('empty');
    expect($row['is_immutable'])->toBeFalse();

    $this->service->delete($row['id']);
    expect(Period::find($row['id']))->toBeNull();
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
it('update: menolak 409 PERIOD_CLOSED_IMMUTABLE ketika stasiun periode tertutup', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationTypes(['sterilizer'])
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->closed($this->admin)
        ->create();

    try {
        $this->service->update($period->id, periodPayload([
            'business_unit_id' => $this->businessUnitA->id,
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
    expect($period->stations()->where('status', PeriodStatus::Closed->value)->count())->toBe(1);
});

// Case 24b — "ANY", NOT "ALL". One closed station out of two freezes the
// whole period: moving its date range moves what that closed station
// locks, which loses a seal just as surely as deleting it would.
it('update: menolak 409 PERIOD_CLOSED_IMMUTABLE meski hanya SATU stasiun yang tertutup', function () {
    $period = halfClosedPeriod($this->businessUnitA, $this->admin);

    try {
        $this->service->update($period->id, periodPayload([
            'business_unit_id' => $this->businessUnitA->id,
            'name' => 'Oktober 2026 separuh (revisi)',
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-25',
        ]));
        $this->fail('Expected PeriodClosedImmutableException was not thrown.');
    } catch (PeriodClosedImmutableException $e) {
        expect($e->errorCode())->toBe('PERIOD_CLOSED_IMMUTABLE');
    }

    $fresh = $period->fresh();
    expect($fresh->name)->toBe('Oktober 2026 separuh');
    expect($fresh->start_date->toDateString())->toBe('2026-10-01');
    expect($fresh->end_date->toDateString())->toBe('2026-10-31');
});

// Case 25
it('update: melempar PeriodOverlapException ketika rentang baru beririsan, mengecualikan dirinya sendiri', function () {
    $p1 = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationTypes(['sterilizer'])
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-15')
        ->open()
        ->create();

    Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationTypes(['sterilizer'])
        ->named('November 2026')
        ->range('2026-11-01', '2026-11-30')
        ->open()
        ->create();

    expect(fn () => $this->service->update($p1->id, periodPayload([
        'business_unit_id' => $this->businessUnitA->id,
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
        ->stationTypes(['sterilizer'])
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-15')
        ->open()
        ->create();

    $row = $this->service->update($period->id, periodPayload([
        'business_unit_id' => $this->businessUnitA->id,
        'name' => 'Oktober 2026',
        'start_date' => '2026-10-05',
        'end_date' => '2026-10-20',
    ]));

    expect($row['start_date'])->toBe('2026-10-05');
    expect($row['end_date'])->toBe('2026-10-20');

    // EDITING NEVER TOUCHES AN EXISTING STATION'S STATUS — Sterilizer is
    // still 'open', with no closure record invented for it.
    expect(collect($row['stations'])->firstWhere('station_type', 'sterilizer')['status'])->toBe('open');

    // BUT A SUCCESSFUL update() BACKFILLS THE MISSING STATION ROWS (keputusan
    // user 2026-09-26, see PeriodService::update()). This period was built
    // with Sterilizer alone while Mill Alpha also has Boiler Room and
    // Clarification, so those two are added as 'draft' — that is the whole
    // point: a station type with no row cannot be closed and its records are
    // never locked. The summary therefore becomes 'mixed', which is an
    // accurate reading of one open station and two draft ones.
    expect($row['station_count'])->toBe(3);
    // Ordered by the master table's sort_order (process order), not by when
    // the rows were inserted.
    expect(collect($row['stations'])->pluck('status', 'station_type')->all())->toBe([
        'sterilizer' => 'open',
        'clarification' => 'draft',
        'boiler-room' => 'draft',
    ]);
    expect($row['status_summary'])->toBe('mixed');
    expect($row['is_immutable'])->toBeFalse();

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
it('delete: menolak 409 PERIOD_CLOSED_IMMUTABLE ketika stasiun periode tertutup', function () {
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

// Case 28b — the reason the rule is "any": period_stations.period_id is
// cascadeOnDelete, so an "all stations closed" rule would let this delete
// through and silently unlock the sealed Sterilizer records with it.
it('delete: menolak 409 PERIOD_CLOSED_IMMUTABLE meski hanya SATU stasiun yang tertutup', function () {
    $period = halfClosedPeriod($this->businessUnitA, $this->admin);

    expect(fn () => $this->service->delete($period->id))
        ->toThrow(PeriodClosedImmutableException::class);

    expect(Period::find($period->id))->not->toBeNull();
    expect(PeriodStation::query()->where('period_id', $period->id)->count())->toBe(2);
});

// Case 29
it('delete: menghapus periode ketika seluruh syarat terpenuhi', function (string $status) {
    $factory = Period::factory()->forBusinessUnit($this->businessUnitA);
    $period = ($status === 'draft' ? $factory->draft() : $factory->open())->create();

    $this->service->delete($period->id);

    expect(Period::find($period->id))->toBeNull();
    // Cascade took the station rows with it.
    expect(PeriodStation::query()->where('period_id', $period->id)->count())->toBe(0);
})->with([
    'draft' => ['draft'],
    'open' => ['open'],
]);

// ── supporting behaviour of the same use case ───────────────────────────

it('findOverlapping: memperlakukan batas rentang sebagai inklusif', function () {
    Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->named('Oktober 2026')
        ->range('2026-10-01', '2026-10-31')
        ->create();

    // A range starting exactly on the existing end_date DOES overlap.
    expect($this->service->findOverlapping(
        $this->businessUnitA->id,
        '2026-10-31',
        '2026-11-30'
    ))->not->toBeNull();

    // One day later it does not.
    expect($this->service->findOverlapping(
        $this->businessUnitA->id,
        '2026-11-01',
        '2026-11-30'
    ))->toBeNull();
});

it('findOverlapping: tidak melihat periode milik Business Unit lain', function () {
    Period::factory()
        ->forBusinessUnit($this->businessUnitB)
        ->range('2026-10-01', '2026-10-31')
        ->create();

    expect($this->service->findOverlapping(
        $this->businessUnitA->id,
        '2026-10-05',
        '2026-10-10'
    ))->toBeNull();
});

it('activeStationTypesForMill: hanya jenis stasiun mill itu, urut proses', function () {
    expect($this->service->activeStationTypesForMill($this->businessUnitA->id))
        ->toBe(['sterilizer', 'clarification', 'boiler-room']);

    // Mill Beta has no station, and a mill's inventory is never inferred
    // from the master table.
    expect($this->service->activeStationTypesForMill($this->businessUnitB->id))->toBe([]);
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
    expect($this->service->stationTypeLabel('kode-tidak-dikenal'))->toBe('kode-tidak-dikenal');

    // The NULL scope is gone, so the "Semua Stasiun" constant that named it
    // is gone too — asserted here because five *ReportService classes used
    // to borrow it and a re-introduction would quietly revive the concept.
    expect(defined(PeriodService::class.'::ALL_STATION_TYPES_LABEL'))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| openPeriodsByBusinessUnit() — panel "Periode Terbuka Hari Ini per Mill"
|--------------------------------------------------------------------------
|
| Covers unit_test_cases 50–58 and 60–64 of the screen tech-spec v6. Case 49
| (401/403) is route middleware, never a service check, so it lives in
| tests/Feature/Api/KelolaPeriodePelaporanTest.php — asserting it here would
| assert nothing.
|
| "Terbuka hari ini" is TWO conditions, both required: a `period_stations` row
| with status 'open' AND today inside start_date..end_date. Every test below
| freezes the date, because the date is part of the WHERE — not a flag on the
| result — so without freezing none of this is testable and the suite would
| behave differently depending on the day it runs.
*/

/**
 * A period on the given mill, with explicit station rows so "1 of 3 open" can
 * be expressed — the shape the status/date split exists for. The parent is
 * created with ->noStations() so only the rows asked for exist.
 *
 * @param  list<string>  $statusByType  e.g. ['sterilizer' => 'open', ...]
 */
function panelPeriod(BusinessUnit $businessUnit, string $name, string $start, string $end, array $statusByType): Period
{
    $period = Period::factory()
        ->forBusinessUnit($businessUnit)
        ->named($name)
        ->range($start, $end)
        ->noStations()
        ->create();

    foreach ($statusByType as $type => $status) {
        PeriodStation::factory()
            ->forPeriod($period)
            ->stationType($type)
            ->create(['status' => $status]);
    }

    return $period;
}

// Case 50
it('open-summary: mengembalikan satu entri untuk SETIAP Business Unit, termasuk yang tidak punya periode terbuka hari ini', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 15));
    $businessUnitC = BusinessUnit::factory()->create(['name' => 'Mill Gamma']);

    panelPeriod($this->businessUnitA, 'Okt A', '2026-10-01', '2026-10-31', ['sterilizer' => 'open']);

    $result = $this->service->openPeriodsByBusinessUnit();

    // THE INVARIANT, not a magic number: one entry per Business Unit that
    // exists, whatever the fixtures happen to have created. (millStations()
    // quietly creates extra mills of its own through
    // ProductionLine::factory(), so an absolute count here would assert the
    // fixture rather than the rule.) A mill with nothing open must still be
    // present — "this mill can take no input today" is the panel's most useful
    // statement, not an empty slot to drop.
    expect($result['data'])->toHaveCount(BusinessUnit::count());

    $byName = collect($result['data'])->keyBy('business_unit_name');
    expect($byName)->toHaveKeys(['Mill Alpha', 'Mill Beta', 'Mill Gamma']);
    expect($byName['Mill Alpha']['open_periods'])->toHaveCount(1);
    expect($byName['Mill Beta']['open_periods'])->toBe([]);
    expect($byName[$businessUnitC->name]['open_periods'])->toBe([]);

    // Entries are ordered by mill name, so the panel's card order is stable
    // between requests.
    $names = collect($result['data'])->pluck('business_unit_name')->all();
    $sorted = $names;
    sort($sorted, SORT_NATURAL);
    expect($names)->toBe($sorted);
});

// Case 51
it('open-summary: mengabaikan periode yang seluruh barisnya draft WALAU rentangnya memuat hari ini', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 15));

    panelPeriod($this->businessUnitA, 'Okt Draft', '2026-10-01', '2026-10-31', [
        'sterilizer' => 'draft',
        'boiler-room' => 'draft',
        'clarification' => 'draft',
    ]);

    $result = $this->service->openPeriodsByBusinessUnit($this->businessUnitA->id);

    // A running range on its own is not enough: nothing in this period can
    // receive input, so saying it is "open today" would be false.
    expect($result['data'][0]['open_periods'])->toBe([]);
});

// Case 52
it('open-summary: mengabaikan periode yang seluruh barisnya closed walau rentangnya memuat hari ini', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 15));

    panelPeriod($this->businessUnitA, 'Okt Closed', '2026-10-01', '2026-10-31', [
        'sterilizer' => 'closed',
        'boiler-room' => 'closed',
    ]);

    $result = $this->service->openPeriodsByBusinessUnit($this->businessUnitA->id);

    expect($result['data'][0]['open_periods'])->toBe([]);
});

// Case 53 + 54
it('open-summary: memasukkan periode yang punya MINIMAL SATU baris open dan menghitung dari period_stations', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 15));

    $period = panelPeriod($this->businessUnitA, 'Okt Campuran', '2026-10-01', '2026-10-31', [
        'sterilizer' => 'open',
        'boiler-room' => 'draft',
        'clarification' => 'closed',
    ]);

    $result = $this->service->openPeriodsByBusinessUnit($this->businessUnitA->id);
    $open = $result['data'][0]['open_periods'];

    expect($open)->toHaveCount(1);
    expect($open[0]['id'])->toBe($period->id);
    expect($open[0]['name'])->toBe('Okt Campuran');
    expect($open[0]['start_date'])->toBe('2026-10-01');
    expect($open[0]['end_date'])->toBe('2026-10-31');

    // The numbers come from period_stations, never from status_summary — which
    // for this period reads 'mixed' and names no number at all.
    expect($open[0]['open_station_count'])->toBe(1);
    expect($open[0]['station_count'])->toBe(3);
});

// Case 55
it('open-summary: batas rentang INKLUSIF di kedua ujung', function (string $today) {
    Carbon::setTestNow(Carbon::parse($today));

    panelPeriod($this->businessUnitA, 'Okt Batas', '2026-10-01', '2026-10-31', ['sterilizer' => 'open']);

    $result = $this->service->openPeriodsByBusinessUnit($this->businessUnitA->id);

    expect($result['data'][0]['open_periods'])->toHaveCount(1);
})->with(['2026-10-01', '2026-10-15', '2026-10-31']);

// Case 56
it('open-summary: MENGECUALIKAN periode berbaris open yang end_date-nya sudah terlampaui', function () {
    // One day past the end: the period still holds an open station — someone
    // forgot to close it. Excluding it is the user's decision of 2026-10-01,
    // taken with this consequence shown first; the blind spot is recorded as
    // open_question #1 on the screen's business spec.
    Carbon::setTestNow(Carbon::create(2026, 11, 1));

    panelPeriod($this->businessUnitA, 'Okt Lupa Ditutup', '2026-10-01', '2026-10-31', ['sterilizer' => 'open']);

    $result = $this->service->openPeriodsByBusinessUnit($this->businessUnitA->id);

    expect($result['data'][0]['open_periods'])->toBe([]);
});

// Case 57
it('open-summary: MENGECUALIKAN periode berbaris open yang start_date-nya masih di masa depan, lalu memunculkannya tanpa perubahan data', function () {
    panelPeriod($this->businessUnitA, 'Nov Belum Mulai', '2026-11-01', '2026-11-30', ['sterilizer' => 'open']);

    Carbon::setTestNow(Carbon::create(2026, 10, 31));
    expect($this->service->openPeriodsByBusinessUnit($this->businessUnitA->id)['data'][0]['open_periods'])->toBe([]);

    // Nothing is written anywhere; only the clock moves. The filter is the
    // server's date at request time, not something stored.
    Carbon::setTestNow(Carbon::create(2026, 11, 1));
    expect($this->service->openPeriodsByBusinessUnit($this->businessUnitA->id)['data'][0]['open_periods'])->toHaveCount(1);
});

// Case 58
it('open-summary: dua periode yang sama-sama memenuhi syarat pada satu mill keduanya dikembalikan, terurut start_date desc', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 15));

    // This violates the overlap rule on purpose, which is only reachable by
    // writing rows directly — findOverlapping() is a service guard and there is
    // NO database constraint behind it. The panel is where such a violation has
    // to become visible, so the list must never be collapsed with first().
    panelPeriod($this->businessUnitA, 'Okt Satu', '2026-10-01', '2026-10-31', ['sterilizer' => 'open']);
    panelPeriod($this->businessUnitA, 'Okt Dua', '2026-10-10', '2026-10-20', ['boiler-room' => 'open']);

    $open = $this->service->openPeriodsByBusinessUnit($this->businessUnitA->id)['data'][0]['open_periods'];

    expect($open)->toHaveCount(2);
    expect(collect($open)->pluck('name')->all())->toBe(['Okt Dua', 'Okt Satu']);
});

// Case 59
it('open-summary: menyempit ke satu mill ketika business_unit_id dikirim, dengan isi yang sama', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 15));
    millStations($this->businessUnitB, ['sterilizer']);

    panelPeriod($this->businessUnitA, 'Okt A', '2026-10-01', '2026-10-31', ['sterilizer' => 'open']);
    panelPeriod($this->businessUnitB, 'Okt B', '2026-10-01', '2026-10-31', ['sterilizer' => 'open']);

    $all = $this->service->openPeriodsByBusinessUnit();
    $one = $this->service->openPeriodsByBusinessUnit($this->businessUnitA->id);

    expect($all['data'])->toHaveCount(BusinessUnit::count());
    expect($one['data'])->toHaveCount(1);
    expect($one['data'][0]['business_unit_id'])->toBe($this->businessUnitA->id);

    // Narrowing changes WHICH mills are listed, never what is said about one —
    // asserted by comparing the same mill's entry across both calls.
    $allAlpha = collect($all['data'])->firstWhere('business_unit_id', $this->businessUnitA->id);
    expect($one['data'][0])->toBe($allAlpha);

    // And Mill Beta's open period is genuinely there in the unfiltered call, so
    // the narrowing above dropped something real rather than nothing.
    expect(collect($all['data'])->firstWhere('business_unit_id', $this->businessUnitB->id)['open_periods'])
        ->toHaveCount(1);
});

// Case 60
it('open-summary: business_unit_id yang tidak ada mengembalikan data kosong, bukan exception', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 15));

    $result = $this->service->openPeriodsByBusinessUnit('11111111-2222-3333-4444-555555555555');

    // It summarises, it does not fetch one resource — an unmatched filter is a
    // legitimate empty result, which is why the controller answers 200 and not 404.
    expect($result['data'])->toBe([]);
    expect($result['meta']['today'])->toBe('2026-10-15');
});

// Case 61
it('open-summary: mengembalikan data kosong ketika belum ada satu pun Business Unit', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 15));

    PeriodStation::query()->delete();
    Period::query()->delete();
    Station::query()->delete();
    BusinessUnit::query()->delete();

    // The ONLY case that yields empty data. "No mill has an open period today"
    // yields a list of entries with empty open_periods — the FE tells the two
    // apart by how many entries it receives.
    expect($this->service->openPeriodsByBusinessUnit()['data'])->toBe([]);
});

// Case 62
it('open-summary: meta.today adalah tanggal acuan yang dipakai menyaring', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 15, 23, 59, 59));

    expect($this->service->openPeriodsByBusinessUnit()['meta']['today'])->toBe('2026-10-15');
});

// Case 64
it('open-summary: periode tanpa satu pun baris stasiun tidak mungkin muncul', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 15));

    // Mill Beta has no active station, so its period is born with zero rows —
    // there is nothing to open, so it can never satisfy condition (a).
    panelPeriod($this->businessUnitB, 'Okt Kosong', '2026-10-01', '2026-10-31', []);

    $result = $this->service->openPeriodsByBusinessUnit($this->businessUnitB->id);

    expect($result['data'])->toHaveCount(1);
    expect($result['data'][0]['open_periods'])->toBe([]);
});

// Case 63
it('open-summary: entri periode TIDAK memuat is_running maupun is_past_range', function () {
    Carbon::setTestNow(Carbon::create(2026, 10, 15));

    panelPeriod($this->businessUnitA, 'Okt A', '2026-10-01', '2026-10-31', ['sterilizer' => 'open']);

    $entry = $this->service->openPeriodsByBusinessUnit($this->businessUnitA->id)['data'][0]['open_periods'][0];

    // Every period that reaches the panel is already running today, so such a
    // flag would always carry the same value and invite the reader to think
    // other states are being sent too. The keys are asserted exactly.
    expect(array_keys($entry))->toBe([
        'id', 'name', 'start_date', 'end_date', 'open_station_count', 'station_count',
    ]);
});
