<?php

/**
 * PeriodClosureServiceTest — screen-128--kelola-periode-pelaporan /
 * usecase-140--tutup-buka-periode-pelaporan (Tutup & Buka Kembali Periode
 * Pelaporan).
 *
 * Unit tests for App\Services\PeriodClosureService. `uses(TestCase::class,
 * RefreshDatabase::class)` at the top is MANDATORY (see
 * ThreshingRecordServiceTest.php) — without it the factories' faker calls
 * fail with "Unknown format".
 *
 * COVERAGE MAP — this file covers unit_test_cases 30–46 of the screen
 * tech-spec (usecase-140) AND 47–55 (usecase-144 open(), added
 * 2026-09-23); cases 1–29 (usecase-128 list/create/update/delete) live in
 * PeriodServiceTest.php. Each test carries its case number.
 *
 * EVERY ACTION TARGETS ONE `period_stations` ROW SINCE 2026-09-25. Status
 * moved off `periods` because stations do not finish at the same time, so
 * close(), reopen(), open() and unverifiedCount() all take ONE
 * `$periodStationId` — the id PeriodService::toRow() publishes as
 * `stations[].id`. The tests below therefore build a period with
 * ->noStations() and attach its station rows explicitly, which is also the
 * only way to express the shape this split exists for: Sterilizer closed
 * while Clarification is still open.
 *
 * WHY FOUR TESTS GO THROUGH HTTP (cases 31, 39, 45, 47): the admin-only rule
 * is route middleware ('auth:web' + 'role:admin' in routes/api.php, see
 * App\Http\Middleware\EnsureRole), never a check inside the service — so
 * asserting it on the service would assert nothing. EnsureRole writes its
 * own JSON response and never reaches ApiExceptionHandler, so its 403
 * carries `message` only and no `code` (screen 4-implement known_issue);
 * these tests assert the status and the unchanged row, not `code`. Case 47
 * is the same situation stated differently: its tech-spec text names a
 * "ForbiddenException", but NO SUCH CLASS EXISTS in this codebase and
 * open() has no role check of its own — the rule lives entirely in
 * EnsureRole — so the case is realised as an HTTP test against the route,
 * exactly like 31/39/45. The URLs below hit `/api/period-stations/{id}/...`,
 * the shape step 4 settled on: a per-station action now says so in its path
 * instead of borrowing `/api/periods/{id}/...` and quietly meaning something
 * else by {id}.
 *
 * WHAT CLOSING DOES *NOT* DO HERE: this service only sets
 * period_stations.status. Refusing a station record whose event date falls
 * inside a closed period (422 PERIOD_CLOSED) is NOT implemented on
 * screen-128 — it belongs to the 18 *RecordService classes, tracked by
 * usecase-141--kunci-input-periode-tertutup. The four scenarios that
 * depend on it are written and skipped in
 * tests/Feature/Api/KelolaPeriodePelaporanTest.php.
 */

use App\Enums\PeriodStatus;
use App\Enums\UserRole;
use App\Exceptions\PeriodAlreadyClosedException;
use App\Exceptions\PeriodNotClosedException;
use App\Exceptions\PeriodNotDraftException;
use App\Models\BoilerRoomRecord;
use App\Models\BusinessUnit;
use App\Models\Period;
use App\Models\PeriodStation;
use App\Models\Station;
use App\Models\SterilizerRecord;
use App\Models\ThreshingRecord;
use App\Models\User;
use App\Services\PeriodClosureService;
use App\Services\PeriodService;
use App\Services\RecordVerificationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(PeriodClosureService::class);

    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->admin = User::factory()->role(UserRole::Admin)->create(['name' => 'Admin X']);
    $this->otherAdmin = User::factory()->role(UserRole::Admin)->create(['name' => 'Admin A']);
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->create();

    $this->sterilizerStation = Station::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->sterilizer()
        ->create();

    // close()/reopen() stamp closed_by/updated_by from auth()->id().
    $this->actingAs($this->admin, 'web');
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * One `period_stations` row of an October 2026 period on the given mill —
 * the unit every action in this service now operates on. The parent is
 * created with ->noStations() so only the station rows a test asks for
 * exist (PeriodFactory would otherwise register all 19 master types and
 * collide with UNIQUE(period_id, station_type)).
 *
 * @param  list<string>  $extraStationTypes  further rows on the SAME period,
 *                                           left untouched by the action —
 *                                           this is how "closing one station
 *                                           does not close the others" is
 *                                           asserted.
 */
function periodStationFor(
    BusinessUnit $businessUnit,
    string $stationType = 'sterilizer',
    string $status = 'open',
    ?User $closedBy = null,
    ?string $closedAt = null,
    array $extraStationTypes = []
): PeriodStation {
    $period = Period::factory()
        ->forBusinessUnit($businessUnit)
        ->noStations()
        ->named('Oktober 2026 '.$stationType.'-'.uniqid())
        ->range('2026-10-01', '2026-10-31')
        ->create();

    foreach ($extraStationTypes as $extra) {
        PeriodStation::factory()->forPeriod($period)->stationType($extra)->open()->create();
    }

    $factory = PeriodStation::factory()->forPeriod($period)->stationType($stationType);

    $factory = match ($status) {
        'draft' => $factory->draft(),
        'open' => $factory->open(),
        'closed' => $factory->closed($closedBy, $closedAt),
    };

    return $factory->create();
}

// ── unverifiedCount (cases 30–37) ───────────────────────────────────────

// Case 30
it('unverifiedCount: melempar 404 ketika baris stasiun periode tidak ditemukan', function () {
    expect(fn () => $this->service->unverifiedCount('00000000-0000-0000-0000-000000000000'))
        ->toThrow(ModelNotFoundException::class);
});

// Case 31
it('unverifiedCount: menolak 403 ketika actor bukan Admin', function () {
    $station = periodStationFor($this->businessUnitA);

    $response = $this->actingAs($this->supervisor, 'web')
        ->getJson("/api/period-stations/{$station->id}/unverified-count");

    $response->assertStatus(403);
    $response->assertJsonMissingPath('unverified_count');
});

// Case 32
it('unverifiedCount: menghitung hanya tabel stasiun sesuai jenis stasiun barisnya', function () {
    $station = periodStationFor($this->businessUnitA, 'sterilizer', 'open', extraStationTypes: ['boiler-room']);

    $boilerStation = Station::factory()->forBusinessUnit($this->businessUnitA)->boilerRoom()->create();

    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-10-05')->count(2)->create();
    // Same mill, same dates, DIFFERENT station type — registered on the
    // same period but not the row being counted, so it must not appear.
    BoilerRoomRecord::factory()->forStation($boilerStation)->onDate('2026-10-05')->count(3)->create();

    $result = $this->service->unverifiedCount($station->id);

    expect($result['unverified_count'])->toBe(2);
    expect($result['breakdown'])->toHaveCount(1);
    expect($result['breakdown'][0]['station_type'])->toBe('sterilizer');
    expect($result['breakdown'][0]['count'])->toBe(2);
});

// Case 33 — replaces the old "counts every station type when station_type
// is NULL". A period-wide total would fold in stations the closure does not
// touch, so the warning shown before closing Sterilizer would be a number
// about other people's work — and permanently non-zero on a busy mill,
// which trains Admins to click past it.
it('unverifiedCount: dihitung untuk jenis stasiun yang akan ditutup, bukan se-periode', function () {
    $sterilizerRow = periodStationFor(
        $this->businessUnitA,
        'sterilizer',
        'open',
        extraStationTypes: ['boiler-room', 'threshing']
    );

    $boilerStation = Station::factory()->forBusinessUnit($this->businessUnitA)->boilerRoom()->create();
    $threshingStation = Station::factory()->forBusinessUnit($this->businessUnitA)->threshing()->create();

    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-10-05')->count(2)->create();
    BoilerRoomRecord::factory()->forStation($boilerStation)->onDate('2026-10-06')->count(3)->create();
    ThreshingRecord::factory()->forStation($threshingStation)->onDate('2026-10-07')->create();

    // 6 unverified records live inside this period's range; only the 2
    // belonging to the station being closed are reported.
    expect($this->service->unverifiedCount($sterilizerRow->id))->toBe([
        'unverified_count' => 2,
        'breakdown' => [['station_type' => 'sterilizer', 'count' => 2]],
    ]);

    // ...and each sibling row reports only its own.
    $boilerRow = PeriodStation::query()
        ->where('period_id', $sterilizerRow->period_id)
        ->where('station_type', 'boiler-room')
        ->firstOrFail();

    expect($this->service->unverifiedCount($boilerRow->id)['unverified_count'])->toBe(3);
});

// Case 34
it('unverifiedCount: hanya menghitung record dengan date di dalam rentang (batas inklusif)', function () {
    $station = periodStationFor($this->businessUnitA, 'sterilizer');

    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-09-30')->create();
    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-10-01')->create();
    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-10-31')->create();
    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-11-01')->create();

    $result = $this->service->unverifiedCount($station->id);

    // Both boundary dates are inside; the day before and the day after are
    // not. created_at / sync time is irrelevant — only the event date.
    expect($result['unverified_count'])->toBe(2);
});

// Case 35
it('unverifiedCount: hanya menghitung record milik business_unit periode', function () {
    $station = periodStationFor($this->businessUnitA, 'sterilizer');

    $otherMillStation = Station::factory()->forBusinessUnit($this->businessUnitB)->sterilizer()->create();

    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-10-05')->create();
    SterilizerRecord::factory()->forStation($otherMillStation)->onDate('2026-10-05')->count(4)->create();

    $result = $this->service->unverifiedCount($station->id);

    expect($result['unverified_count'])->toBe(1);
});

// Case 36
it('unverifiedCount: menghitung record yang checked_by NULL atau acknowledged_by NULL', function () {
    $station = periodStationFor($this->businessUnitA, 'sterilizer');

    // (null, null) — unverified.
    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-10-05')->create();
    // (terisi, null) — still unverified: acknowledgement is missing.
    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-10-06')->create([
        'checked_by' => $this->supervisor->id,
        'acknowledged_by' => null,
    ]);
    // (terisi, terisi) — fully verified, not counted.
    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-10-07')->create([
        'checked_by' => $this->supervisor->id,
        'acknowledged_by' => $this->millManagement->id,
    ]);

    $result = $this->service->unverifiedCount($station->id);

    expect($result['unverified_count'])->toBe(2);
});

// Case 37
it('unverifiedCount: mengembalikan 0 dan breakdown kosong ketika seluruh record sudah terverifikasi', function () {
    $station = periodStationFor($this->businessUnitA, 'sterilizer');

    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-10-05')->count(3)->create([
        'checked_by' => $this->supervisor->id,
        'acknowledged_by' => $this->millManagement->id,
    ]);

    $result = $this->service->unverifiedCount($station->id);

    expect($result['unverified_count'])->toBe(0);
    expect($result['breakdown'])->toBe([]);
});

// ── close (cases 38–42) ─────────────────────────────────────────────────

// Case 38
it('close: melempar 404 ketika baris stasiun periode tidak ditemukan', function () {
    expect(fn () => $this->service->close('00000000-0000-0000-0000-000000000000'))
        ->toThrow(ModelNotFoundException::class);
});

// Case 39
it('close: menolak 403 ketika actor bukan Admin', function () {
    $station = periodStationFor($this->businessUnitA);

    $response = $this->actingAs($this->millManagement, 'web')
        ->postJson("/api/period-stations/{$station->id}/close");

    $response->assertStatus(403);

    // Status unchanged — Mill Management may read the reports but may not
    // close the books.
    expect($station->fresh()->status)->toBe(PeriodStatus::Open);
    expect($station->fresh()->closed_by)->toBeNull();
});

// Case 40
it('close: melempar 409 PERIOD_ALREADY_CLOSED ketika UPDATE bersyarat menghasilkan affected=0', function () {
    // Admin A got there first.
    $station = periodStationFor($this->businessUnitA, 'sterilizer', 'closed', $this->otherAdmin, '2026-11-01 09:14:00');

    try {
        $this->service->close($station->id);
        $this->fail('Expected PeriodAlreadyClosedException was not thrown.');
    } catch (PeriodAlreadyClosedException $e) {
        expect($e->getStatusCode())->toBe(409);
        expect($e->errorCode())->toBe('PERIOD_ALREADY_CLOSED');
        // The loser is told who actually closed it.
        expect($e->getMessage())->toContain('Admin A');
    }

    // The first closer's record is NOT overwritten.
    $fresh = $station->fresh();
    expect($fresh->closed_by)->toBe($this->otherAdmin->id);
    expect($fresh->closed_at->format('Y-m-d H:i'))->toBe('2026-11-01 09:14');
    // And the loser left no trace on the parent either.
    expect($station->period->fresh()->updated_by)->toBeNull();
});

// Case 41
it('close: menutup satu stasiun dan mencatat closed_by serta closed_at ketika syarat terpenuhi', function (string $status) {
    $frozen = Carbon::create(2026, 11, 2, 7, 30, 0);
    Carbon::setTestNow($frozen);

    $station = periodStationFor($this->businessUnitA, 'sterilizer', $status);

    $result = $this->service->close($station->id);

    expect($result['period_station_id'])->toBe($station->id);
    expect($result['period_id'])->toBe($station->period_id);
    expect($result['station_type'])->toBe('sterilizer');
    expect($result['station_type_label'])->toBe('Sterilizer');
    expect($result['status'])->toBe('closed');
    expect($result['closed_by'])->toBe($this->admin->id);
    expect($result['closed_by_name'])->toBe('Admin X');
    expect($result['closed_at'])->toBe($frozen->copy()->toIso8601String());

    $fresh = $station->fresh();
    expect($fresh->status)->toBe(PeriodStatus::Closed);
    expect($fresh->closed_by)->toBe($this->admin->id);
    expect($fresh->closed_at->toIso8601String())->toBe($frozen->copy()->toIso8601String());
    // The closure audit lives on the child row; the parent only records who
    // last touched the period.
    expect($station->period->fresh()->updated_by)->toBe($this->admin->id);
})->with([
    'draft' => ['draft'],
    'open' => ['open'],
]);

// Case 41b — THE POINT OF THE WHOLE SPLIT: closing one station type leaves
// every other station type of the same period exactly as it was.
it('close: menutup satu stasiun TIDAK menutup stasiun lain di periode yang sama', function () {
    $sterilizerRow = periodStationFor(
        $this->businessUnitA,
        'sterilizer',
        'open',
        extraStationTypes: ['clarification', 'boiler-room']
    );

    $this->service->close($sterilizerRow->id);

    $statuses = PeriodStation::query()
        ->where('period_id', $sterilizerRow->period_id)
        ->orderBy('station_type')
        ->pluck('status', 'station_type')
        ->map(fn ($status) => $status instanceof PeriodStatus ? $status->value : $status)
        ->all();

    expect($statuses)->toBe([
        'boiler-room' => 'open',
        'clarification' => 'open',
        'sterilizer' => 'closed',
    ]);

    // No closure record leaked onto the untouched rows.
    expect(PeriodStation::query()
        ->where('period_id', $sterilizerRow->period_id)
        ->whereNotNull('closed_by')
        ->count())->toBe(1);
});

// Case 42
it('close: berhasil meski masih ada record belum terverifikasi', function () {
    $station = periodStationFor($this->businessUnitA, 'sterilizer');

    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-10-10')->count(5)->create();

    expect($this->service->unverifiedCount($station->id)['unverified_count'])->toBe(5);

    $result = $this->service->close($station->id);

    // The figure is a warning, never a blocker.
    expect($result['status'])->toBe('closed');
    expect($station->fresh()->status)->toBe(PeriodStatus::Closed);
    expect($this->service->unverifiedCount($station->id)['unverified_count'])->toBe(5);
});

// ── reopen (cases 43–46) ────────────────────────────────────────────────

// Case 43
it('reopen: melempar 404 ketika baris stasiun periode tidak ditemukan', function () {
    expect(fn () => $this->service->reopen('00000000-0000-0000-0000-000000000000'))
        ->toThrow(ModelNotFoundException::class);
});

// Case 44
it('reopen: melempar 409 PERIOD_NOT_CLOSED ketika stasiun belum berstatus closed', function (string $status) {
    $station = periodStationFor($this->businessUnitA, 'sterilizer', $status);

    try {
        $this->service->reopen($station->id);
        $this->fail('Expected PeriodNotClosedException was not thrown.');
    } catch (PeriodNotClosedException $e) {
        expect($e->getStatusCode())->toBe(409);
        expect($e->errorCode())->toBe('PERIOD_NOT_CLOSED');
    }

    // No UPDATE.
    expect($station->fresh()->status->value)->toBe($status);
    expect($station->period->fresh()->updated_by)->toBeNull();
})->with([
    'draft' => ['draft'],
    'open' => ['open'],
]);

// Case 45
it('reopen: menolak 403 ketika actor bukan Admin', function () {
    $station = periodStationFor($this->businessUnitA, 'sterilizer', 'closed', $this->otherAdmin, '2026-11-01 09:14:00');

    $response = $this->actingAs($this->operator, 'web')
        ->postJson("/api/period-stations/{$station->id}/reopen");

    $response->assertStatus(403);

    $fresh = $station->fresh();
    expect($fresh->status)->toBe(PeriodStatus::Closed);
    expect($fresh->closed_by)->toBe($this->otherAdmin->id);
});

// Case 46
it('reopen: membuka kembali satu stasiun dan menghapus catatan penutupan', function () {
    $station = periodStationFor(
        $this->businessUnitA,
        'sterilizer',
        'closed',
        $this->otherAdmin,
        '2026-11-01 09:14:00',
        extraStationTypes: ['clarification']
    );

    $result = $this->service->reopen($station->id);

    expect($result['period_station_id'])->toBe($station->id);
    expect($result['station_type'])->toBe('sterilizer');
    expect($result['status'])->toBe('open');
    expect($result['closed_by'])->toBeNull();
    expect($result['closed_by_name'])->toBeNull();
    expect($result['closed_at'])->toBeNull();

    $fresh = $station->fresh();
    expect($fresh->status)->toBe(PeriodStatus::Open);
    expect($fresh->closed_by)->toBeNull();
    expect($fresh->closed_at)->toBeNull();
    expect($station->period->fresh()->updated_by)->toBe($this->admin->id);

    // The sibling row was never in scope of this action.
    $sibling = PeriodStation::query()
        ->where('period_id', $station->period_id)
        ->where('station_type', 'clarification')
        ->firstOrFail();
    expect($sibling->status)->toBe(PeriodStatus::Open);
});

// ── supporting behaviour of the same use case ───────────────────────────

// Replaces the old coveredStationTypes() test. That method resolved "which
// station types does this period cover" from a nullable column; the list is
// explicit rows in `period_stations` now, so a service-side derivation
// would be a second, disagreeing source of truth.
it('coveredStationTypes: tidak ada lagi — daftar stasiun kini eksplisit di period_stations', function () {
    expect(method_exists(PeriodClosureService::class, 'coveredStationTypes'))->toBeFalse();
});

it('unverifiedCount: mengabaikan tipe stasiun yang tidak punya tabel record (other)', function () {
    $station = periodStationFor($this->businessUnitA, 'other');

    // 'other' is a real row in station_types but has no *_records table —
    // it contributes 0 instead of blowing up.
    $result = $this->service->unverifiedCount($station->id);

    expect($result['unverified_count'])->toBe(0);
    expect($result['breakdown'])->toBe([]);
});

// ── open — usecase-144 (cases 47–55) ────────────────────────────────────
//
// open() is draft -> open for ONE station row and NOTHING else. Its whole
// concurrency story is one conditional UPDATE ... WHERE id=? AND
// status='draft' on `period_stations`; there is no lock and no transaction,
// so the tests below assert the WHERE clause and the affected-rows branch
// directly rather than trusting the happy path.

/** A draft station row on Mill Alpha, ready to be opened. */
function draftStationForOpening(BusinessUnit $businessUnit, string $stationType = 'sterilizer'): PeriodStation
{
    return periodStationFor($businessUnit, $stationType, 'draft');
}

// Case 47 — realised as an HTTP test on purpose: there is no
// ForbiddenException class and open() carries no role check; the rule is
// EnsureRole on the route ('auth:web' + 'role:admin'), so asserting it on
// the service would assert nothing at all.
it('open: menolak 403 ketika actor bukan Admin, tanpa menyentuh baris stasiun', function (string $role) {
    $station = draftStationForOpening($this->businessUnitA);

    $actor = match ($role) {
        'supervisor' => $this->supervisor,
        'mill_management' => $this->millManagement,
        'operator' => $this->operator,
    };

    $response = $this->actingAs($actor, 'web')->postJson("/api/period-stations/{$station->id}/open");

    $response->assertStatus(403);
    $response->assertJsonMissingPath('status');

    // findOrFail() never ran and no UPDATE was executed — the row is
    // bit-for-bit what it was.
    expect($station->fresh()->status)->toBe(PeriodStatus::Draft);
    expect($station->period->fresh()->updated_by)->toBeNull();
})->with([
    'supervisor' => ['supervisor'],
    'mill management' => ['mill_management'],
    'operator' => ['operator'],
]);

// Case 48
it('open: melempar 404 ketika baris stasiun periode tidak ditemukan', function () {
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    expect(fn () => $this->service->open('00000000-0000-0000-0000-000000000000'))
        ->toThrow(ModelNotFoundException::class);

    // No UPDATE was executed.
    expect(collect($queries)->filter(fn (string $sql) => str_starts_with(strtolower(trim($sql)), 'update')))
        ->toHaveCount(0);
});

// Case 49
it('open: melempar 409 PERIOD_NOT_DRAFT ketika stasiun sudah berstatus open', function () {
    $station = periodStationFor($this->businessUnitA, 'sterilizer', 'open');

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    try {
        $this->service->open($station->id);
        $this->fail('Expected PeriodNotDraftException was not thrown.');
    } catch (PeriodNotDraftException $e) {
        expect($e->getStatusCode())->toBe(409);
        expect($e->errorCode())->toBe('PERIOD_NOT_DRAFT');
        expect($e->getMessage())->toContain('sudah terbuka');
        // An already-open station must NOT be pointed at reopen — that
        // pointer belongs to the closed case only.
        expect($e->getMessage())->not->toContain('Buka Kembali Periode');
    }

    expect(collect($queries)->filter(fn (string $sql) => str_starts_with(strtolower(trim($sql)), 'update')))
        ->toHaveCount(0);
    expect($station->fresh()->status)->toBe(PeriodStatus::Open);
});

// Case 50
it('open: melempar 409 PERIOD_NOT_DRAFT untuk stasiun closed dan mengarahkan ke Buka Kembali Periode', function () {
    $station = periodStationFor($this->businessUnitA, 'sterilizer', 'closed', $this->otherAdmin, '2026-11-01 09:14:00');

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    try {
        $this->service->open($station->id);
        $this->fail('Expected PeriodNotDraftException was not thrown.');
    } catch (PeriodNotDraftException $e) {
        expect($e->getStatusCode())->toBe(409);
        expect($e->errorCode())->toBe('PERIOD_NOT_DRAFT');
        // A closed station gets a DIFFERENT message from an open one, and it
        // names the other action explicitly — "Buka Periode" and "Buka
        // Kembali Periode" are trivially confused.
        expect($e->getMessage())->toContain('sudah tertutup');
        expect($e->getMessage())->toContain('Buka Kembali Periode');
        expect($e->getMessage())->not->toContain('sudah terbuka');
    }

    expect(collect($queries)->filter(fn (string $sql) => str_starts_with(strtolower(trim($sql)), 'update')))
        ->toHaveCount(0);

    $fresh = $station->fresh();
    expect($fresh->status)->toBe(PeriodStatus::Closed);
    expect($fresh->closed_by)->toBe($this->otherAdmin->id);
});

// Case 51
it('open: menjalankan UPDATE berkondisi pada period_stations dengan klausa WHERE status draft, bukan save() model', function () {
    $station = draftStationForOpening($this->businessUnitA);

    $statements = [];
    DB::listen(function ($query) use (&$statements) {
        $statements[] = ['sql' => $query->sql, 'bindings' => $query->bindings];
    });

    $this->service->open($station->id);

    $updates = collect($statements)
        ->filter(fn (array $s) => str_starts_with(strtolower(trim($s['sql'])), 'update'))
        ->values();

    $statusUpdates = $updates
        ->filter(fn (array $s) => str_contains(strtolower($s['sql']), 'period_stations'))
        ->values();

    // Exactly one UPDATE carries the status change — not a read-then-save()
    // pair.
    expect($statusUpdates)->toHaveCount(1);

    $sql = strtolower($statusUpdates[0]['sql']);

    // The guard lives in the WHERE clause: BOTH the id and the status must
    // be there. A save() on the loaded model would key on the id alone and
    // would silently overwrite a row another Admin already opened.
    $where = substr($sql, (int) strpos($sql, ' where '));
    expect($where)->toContain('id');
    expect($where)->toContain('status');

    expect($statusUpdates[0]['bindings'])->toContain('draft');
    expect($statusUpdates[0]['bindings'])->toContain('open');

    // The only other UPDATE is the parent's audit stamp, and it touches
    // nothing but updated_by/updated_at — the status never goes near
    // `periods` again.
    $periodUpdates = $updates
        ->filter(fn (array $s) => ! str_contains(strtolower($s['sql']), 'period_stations'))
        ->values();
    expect($periodUpdates)->toHaveCount(1);
    expect(strtolower($periodUpdates[0]['sql']))->toContain('updated_by');
    expect(strtolower($periodUpdates[0]['sql']))->not->toContain('status');
    expect($periodUpdates[0]['bindings'])->toContain($this->admin->id);
});

// Case 52
it('open: melempar 409 PERIOD_NOT_DRAFT ketika UPDATE berkondisi mengenai 0 baris', function () {
    $station = draftStationForOpening($this->businessUnitA);

    // Simulate the race exactly where it happens: between the findOrFail()
    // read (which still sees 'draft') and the conditional UPDATE, another
    // Admin opens the same row straight in the database. The in-memory
    // model the service holds is therefore stale and the UPDATE matches 0
    // rows.
    $raced = false;
    DB::listen(function ($query) use (&$raced, $station) {
        if ($raced || ! str_starts_with(strtolower(trim($query->sql)), 'select')) {
            return;
        }

        if (! str_contains(strtolower($query->sql), 'period_stations')) {
            return;
        }

        $raced = true;
        DB::table('period_stations')->where('id', $station->id)->update(['status' => PeriodStatus::Open->value]);
    });

    try {
        $this->service->open($station->id);
        $this->fail('Expected PeriodNotDraftException was not thrown.');
    } catch (PeriodNotDraftException $e) {
        expect($e->getStatusCode())->toBe(409);
        expect($e->errorCode())->toBe('PERIOD_NOT_DRAFT');
        // The message names the status the row actually has NOW.
        expect($e->getMessage())->toContain('sudah terbuka');
    }

    expect($raced)->toBeTrue();

    // No silent overwrite: the loser never stamped the parent's updated_by.
    expect($station->fresh()->status)->toBe(PeriodStatus::Open);
    expect($station->period->fresh()->updated_by)->toBeNull();
});

// Case 53
it('open: tidak membaca, memvalidasi, maupun mengubah data stasiun mana pun', function () {
    $station = draftStationForOpening($this->businessUnitA);

    $records = SterilizerRecord::factory()
        ->forStation($this->sterilizerStation)
        ->onDate('2026-10-10')
        ->count(3)
        ->create();

    $before = SterilizerRecord::query()->orderBy('id')->get()->map(fn ($r) => [
        $r->id, $r->checked_by, $r->acknowledged_by, (string) $r->updated_at,
    ])->all();

    // The ONLY station-record collaborator this service has is
    // RecordVerificationService (unverifiedCount() uses it to resolve the 18
    // record models). open() must never reach for it.
    $verification = Mockery::mock(RecordVerificationService::class);
    $verification->shouldNotReceive('modelForStationType');
    $verification->shouldNotReceive('canVerify');

    $service = new PeriodClosureService($verification);

    $touchedTables = [];
    DB::listen(function ($query) use (&$touchedTables) {
        $touchedTables[] = strtolower($query->sql);
    });

    $result = $service->open($station->id);

    expect($result['status'])->toBe('open');

    // data_operations touch `period_stations` and `periods` only — no
    // *_records table is read or written, unlike unverifiedCount() which
    // counts unverified records.
    $stationQueries = collect($touchedTables)
        ->filter(fn (string $sql) => str_contains($sql, '_records'))
        ->values();
    expect($stationQueries)->toHaveCount(0);

    $after = SterilizerRecord::query()->orderBy('id')->get()->map(fn ($r) => [
        $r->id, $r->checked_by, $r->acknowledged_by, (string) $r->updated_at,
    ])->all();

    expect($after)->toBe($before);
    expect(SterilizerRecord::count())->toBe($records->count());
});

// Case 54
it('open: mengembalikan bentuk status baru dan mencatat updated_by ketika syarat terpenuhi', function () {
    $station = draftStationForOpening($this->businessUnitA);

    $result = $this->service->open($station->id);

    // The SAME shape close() and reopen() return, so the three step-4
    // handlers differ only in the method they call.
    expect($result)->toBe([
        'period_station_id' => $station->id,
        'period_id' => $station->period_id,
        'station_type' => 'sterilizer',
        'station_type_label' => 'Sterilizer',
        'status' => 'open',
        'closed_by' => null,
        'closed_by_name' => null,
        'closed_at' => null,
    ]);

    $fresh = $station->fresh();
    expect($fresh->status)->toBe(PeriodStatus::Open);
    expect($station->period->fresh()->updated_by)->toBe($this->admin->id);
    // Opening is not closing: the closure columns stay empty.
    expect($fresh->closed_by)->toBeNull();
    expect($fresh->closed_at)->toBeNull();
});

// Case 55 — guards a rule that is very easy to "tidy up" into a bug: only
// 'closed' locks a period. A period whose stations are open must stay as
// editable and as deletable as a draft one.
it('open: periode dengan stasiun berstatus open tetap dapat diubah dan dihapus', function () {
    $periodService = app(PeriodService::class);

    $station = draftStationForOpening($this->businessUnitA);
    $this->service->open($station->id);
    expect($station->fresh()->status)->toBe(PeriodStatus::Open);

    $updated = $periodService->update($station->period_id, [
        'business_unit_id' => $this->businessUnitA->id,
        'name' => 'Oktober 2026 (revisi)',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-31',
    ]);

    // No PeriodClosedImmutableException — and the station statuses are
    // untouched by the edit (PeriodService never validates or writes them).
    expect($updated['name'])->toBe('Oktober 2026 (revisi)');
    expect($updated['status_summary'])->toBe('open');

    $periodService->delete($station->period_id);

    expect(Period::find($station->period_id))->toBeNull();
    expect(PeriodStation::find($station->id))->toBeNull();
});
