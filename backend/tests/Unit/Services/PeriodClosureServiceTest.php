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
 * exactly like 31/39/45.
 *
 * WHAT CLOSING DOES *NOT* DO HERE: this service only sets periods.status.
 * Refusing a station record whose event date falls inside a closed period
 * (422 PERIOD_CLOSED) is NOT implemented on screen-128 — it belongs to the
 * 18 *RecordService classes, tracked by
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
 * An open period on Mill Alpha covering October 2026, scoped to ONE
 * station type unless overridden.
 */
function periodForClosure(BusinessUnit $businessUnit, ?string $stationType = 'sterilizer'): Period
{
    return Period::factory()
        ->forBusinessUnit($businessUnit)
        ->stationType($stationType)
        ->named('Oktober 2026 '.($stationType ?? 'semua').'-'.uniqid())
        ->range('2026-10-01', '2026-10-31')
        ->open()
        ->create();
}

// ── unverifiedCount (cases 30–37) ───────────────────────────────────────

// Case 30
it('unverifiedCount: melempar 404 ketika periode tidak ditemukan', function () {
    expect(fn () => $this->service->unverifiedCount('00000000-0000-0000-0000-000000000000'))
        ->toThrow(ModelNotFoundException::class);
});

// Case 31
it('unverifiedCount: menolak 403 ketika actor bukan Admin', function () {
    $period = periodForClosure($this->businessUnitA);

    $response = $this->actingAs($this->supervisor, 'web')
        ->getJson("/api/periods/{$period->id}/unverified-count");

    $response->assertStatus(403);
    $response->assertJsonMissingPath('unverified_count');
});

// Case 32
it('unverifiedCount: menghitung hanya tabel stasiun sesuai station_type periode', function () {
    $period = periodForClosure($this->businessUnitA, 'sterilizer');

    $boilerStation = Station::factory()->forBusinessUnit($this->businessUnitA)->boilerRoom()->create();

    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-10-05')->count(2)->create();
    // Same mill, same dates, DIFFERENT station type — outside this
    // period's scope, so it must not be counted.
    BoilerRoomRecord::factory()->forStation($boilerStation)->onDate('2026-10-05')->count(3)->create();

    $result = $this->service->unverifiedCount($period->id);

    expect($result['unverified_count'])->toBe(2);
    expect($result['breakdown'])->toHaveCount(1);
    expect($result['breakdown'][0]['station_type'])->toBe('sterilizer');
    expect($result['breakdown'][0]['count'])->toBe(2);
});

// Case 33
it('unverifiedCount: menghitung seluruh tipe stasiun ketika station_type periode NULL', function () {
    $period = periodForClosure($this->businessUnitA, null);

    $boilerStation = Station::factory()->forBusinessUnit($this->businessUnitA)->boilerRoom()->create();
    $threshingStation = Station::factory()->forBusinessUnit($this->businessUnitA)->threshing()->create();

    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-10-05')->count(2)->create();
    BoilerRoomRecord::factory()->forStation($boilerStation)->onDate('2026-10-06')->count(3)->create();
    ThreshingRecord::factory()->forStation($threshingStation)->onDate('2026-10-07')->create();

    $result = $this->service->unverifiedCount($period->id);

    expect($result['unverified_count'])->toBe(6);
    // One entry per station type with count > 0 — the 15 other station
    // types have no records and are omitted entirely.
    expect($result['breakdown'])->toHaveCount(3);
    expect(collect($result['breakdown'])->pluck('count', 'station_type')->all())->toBe([
        'sterilizer' => 2,
        'threshing' => 1,
        'boiler-room' => 3,
    ]);
});

// Case 34
it('unverifiedCount: hanya menghitung record dengan date di dalam rentang (batas inklusif)', function () {
    $period = periodForClosure($this->businessUnitA, 'sterilizer');

    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-09-30')->create();
    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-10-01')->create();
    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-10-31')->create();
    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-11-01')->create();

    $result = $this->service->unverifiedCount($period->id);

    // Both boundary dates are inside; the day before and the day after are
    // not. created_at / sync time is irrelevant — only the event date.
    expect($result['unverified_count'])->toBe(2);
});

// Case 35
it('unverifiedCount: hanya menghitung record milik business_unit periode', function () {
    $period = periodForClosure($this->businessUnitA, 'sterilizer');

    $otherMillStation = Station::factory()->forBusinessUnit($this->businessUnitB)->sterilizer()->create();

    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-10-05')->create();
    SterilizerRecord::factory()->forStation($otherMillStation)->onDate('2026-10-05')->count(4)->create();

    $result = $this->service->unverifiedCount($period->id);

    expect($result['unverified_count'])->toBe(1);
});

// Case 36
it('unverifiedCount: menghitung record yang checked_by NULL atau acknowledged_by NULL', function () {
    $period = periodForClosure($this->businessUnitA, 'sterilizer');

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

    $result = $this->service->unverifiedCount($period->id);

    expect($result['unverified_count'])->toBe(2);
});

// Case 37
it('unverifiedCount: mengembalikan 0 dan breakdown kosong ketika seluruh record sudah terverifikasi', function () {
    $period = periodForClosure($this->businessUnitA, 'sterilizer');

    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-10-05')->count(3)->create([
        'checked_by' => $this->supervisor->id,
        'acknowledged_by' => $this->millManagement->id,
    ]);

    $result = $this->service->unverifiedCount($period->id);

    expect($result['unverified_count'])->toBe(0);
    expect($result['breakdown'])->toBe([]);
});

// ── close (cases 38–42) ─────────────────────────────────────────────────

// Case 38
it('close: melempar 404 ketika periode tidak ditemukan', function () {
    expect(fn () => $this->service->close('00000000-0000-0000-0000-000000000000'))
        ->toThrow(ModelNotFoundException::class);
});

// Case 39
it('close: menolak 403 ketika actor bukan Admin', function () {
    $period = periodForClosure($this->businessUnitA);

    $response = $this->actingAs($this->millManagement, 'web')
        ->postJson("/api/periods/{$period->id}/close");

    $response->assertStatus(403);

    // Status unchanged — Mill Management may read the reports but may not
    // close the books.
    expect($period->fresh()->status)->toBe(PeriodStatus::Open);
    expect($period->fresh()->closed_by)->toBeNull();
});

// Case 40
it('close: melempar 409 PERIOD_ALREADY_CLOSED ketika UPDATE bersyarat menghasilkan affected=0', function () {
    // Admin A got there first.
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->range('2026-10-01', '2026-10-31')
        ->closed($this->otherAdmin, '2026-11-01 09:14:00')
        ->create();

    try {
        $this->service->close($period->id);
        $this->fail('Expected PeriodAlreadyClosedException was not thrown.');
    } catch (PeriodAlreadyClosedException $e) {
        expect($e->getStatusCode())->toBe(409);
        expect($e->errorCode())->toBe('PERIOD_ALREADY_CLOSED');
        // The loser is told who actually closed it.
        expect($e->getMessage())->toContain('Admin A');
    }

    // The first closer's record is NOT overwritten.
    $fresh = $period->fresh();
    expect($fresh->closed_by)->toBe($this->otherAdmin->id);
    expect($fresh->closed_at->format('Y-m-d H:i'))->toBe('2026-11-01 09:14');
});

// Case 41
it('close: menutup periode dan mencatat closed_by serta closed_at ketika syarat terpenuhi', function (string $status) {
    $frozen = Carbon::create(2026, 11, 2, 7, 30, 0);
    Carbon::setTestNow($frozen);

    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->range('2026-10-01', '2026-10-31')
        ->state(['status' => $status])
        ->create();

    $result = $this->service->close($period->id);

    expect($result['status'])->toBe('closed');
    expect($result['closed_by'])->toBe($this->admin->id);
    expect($result['closed_by_name'])->toBe('Admin X');
    expect($result['closed_at'])->toBe($frozen->copy()->toIso8601String());

    $fresh = $period->fresh();
    expect($fresh->status)->toBe(PeriodStatus::Closed);
    expect($fresh->closed_by)->toBe($this->admin->id);
    expect($fresh->closed_at->toIso8601String())->toBe($frozen->copy()->toIso8601String());
})->with([
    'draft' => ['draft'],
    'open' => ['open'],
]);

// Case 42
it('close: berhasil meski masih ada record belum terverifikasi', function () {
    $period = periodForClosure($this->businessUnitA, 'sterilizer');

    SterilizerRecord::factory()->forStation($this->sterilizerStation)->onDate('2026-10-10')->count(5)->create();

    expect($this->service->unverifiedCount($period->id)['unverified_count'])->toBe(5);

    $result = $this->service->close($period->id);

    // The figure is a warning, never a blocker.
    expect($result['status'])->toBe('closed');
    expect($period->fresh()->status)->toBe(PeriodStatus::Closed);
    expect($this->service->unverifiedCount($period->id)['unverified_count'])->toBe(5);
});

// ── reopen (cases 43–46) ────────────────────────────────────────────────

// Case 43
it('reopen: melempar 404 ketika periode tidak ditemukan', function () {
    expect(fn () => $this->service->reopen('00000000-0000-0000-0000-000000000000'))
        ->toThrow(ModelNotFoundException::class);
});

// Case 44
it('reopen: melempar 409 PERIOD_NOT_CLOSED ketika periode belum berstatus closed', function (string $status) {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->state(['status' => $status])
        ->create();

    try {
        $this->service->reopen($period->id);
        $this->fail('Expected PeriodNotClosedException was not thrown.');
    } catch (PeriodNotClosedException $e) {
        expect($e->getStatusCode())->toBe(409);
        expect($e->errorCode())->toBe('PERIOD_NOT_CLOSED');
    }

    // No UPDATE.
    expect($period->fresh()->status->value)->toBe($status);
})->with([
    'draft' => ['draft'],
    'open' => ['open'],
]);

// Case 45
it('reopen: menolak 403 ketika actor bukan Admin', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->closed($this->otherAdmin, '2026-11-01 09:14:00')
        ->create();

    $response = $this->actingAs($this->operator, 'web')
        ->postJson("/api/periods/{$period->id}/reopen");

    $response->assertStatus(403);

    $fresh = $period->fresh();
    expect($fresh->status)->toBe(PeriodStatus::Closed);
    expect($fresh->closed_by)->toBe($this->otherAdmin->id);
});

// Case 46
it('reopen: membuka kembali periode dan menghapus catatan penutupan', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->closed($this->otherAdmin, '2026-11-01 09:14:00')
        ->create();

    $result = $this->service->reopen($period->id);

    expect($result['status'])->toBe('open');
    expect($result['closed_by'])->toBeNull();
    expect($result['closed_at'])->toBeNull();

    $fresh = $period->fresh();
    expect($fresh->status)->toBe(PeriodStatus::Open);
    expect($fresh->closed_by)->toBeNull();
    expect($fresh->closed_at)->toBeNull();
    expect($fresh->updated_by)->toBe($this->admin->id);
});

// ── supporting behaviour of the same use case ───────────────────────────

it('coveredStationTypes: satu tipe untuk periode bercakupan tipe, seluruh tipe aktif untuk cakupan NULL', function () {
    $typed = periodForClosure($this->businessUnitA, 'sterilizer');
    $allTypes = periodForClosure($this->businessUnitA, null);

    expect($this->service->coveredStationTypes($typed))->toBe(['sterilizer']);

    $all = $this->service->coveredStationTypes($allTypes);
    expect(count($all))->toBeGreaterThanOrEqual(18);
    expect($all)->toContain('sterilizer', 'weighbridge', 'boiler-room');
});

it('unverifiedCount: mengabaikan tipe stasiun yang tidak punya tabel record (other)', function () {
    $period = periodForClosure($this->businessUnitA, 'other');

    // 'other' is a real row in station_types but has no *_records table —
    // it contributes 0 instead of blowing up.
    $result = $this->service->unverifiedCount($period->id);

    expect($result['unverified_count'])->toBe(0);
    expect($result['breakdown'])->toBe([]);
});

// ── open — usecase-144 (cases 47–55) ────────────────────────────────────
//
// open() is draft -> open and NOTHING else. Its whole concurrency story is
// one conditional UPDATE ... WHERE id=? AND status='draft'; there is no
// lock and no transaction, so the tests below assert the WHERE clause and
// the affected-rows branch directly rather than trusting the happy path.

/** A draft period on Mill Alpha, ready to be opened. */
function draftPeriodForOpening(BusinessUnit $businessUnit, ?string $stationType = 'sterilizer'): Period
{
    return Period::factory()
        ->forBusinessUnit($businessUnit)
        ->stationType($stationType)
        ->named('Draft Oktober 2026 '.($stationType ?? 'semua').'-'.uniqid())
        ->range('2026-10-01', '2026-10-31')
        ->draft()
        ->create();
}

// Case 47 — realised as an HTTP test on purpose: there is no
// ForbiddenException class and open() carries no role check; the rule is
// EnsureRole on the route ('auth:web' + 'role:admin'), so asserting it on
// the service would assert nothing at all.
it('open: menolak 403 ketika actor bukan Admin, tanpa menyentuh periode', function (string $role) {
    $period = draftPeriodForOpening($this->businessUnitA);

    $actor = match ($role) {
        'supervisor' => $this->supervisor,
        'mill_management' => $this->millManagement,
        'operator' => $this->operator,
    };

    $response = $this->actingAs($actor, 'web')->postJson("/api/periods/{$period->id}/open");

    $response->assertStatus(403);
    $response->assertJsonMissingPath('status');

    // findOrFail() never ran and no UPDATE was executed — the row is
    // bit-for-bit what it was.
    $fresh = $period->fresh();
    expect($fresh->status)->toBe(PeriodStatus::Draft);
    expect($fresh->updated_by)->toBeNull();
})->with([
    'supervisor' => ['supervisor'],
    'mill management' => ['mill_management'],
    'operator' => ['operator'],
]);

// Case 48
it('open: melempar 404 ketika periode tidak ditemukan', function () {
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
it('open: melempar 409 PERIOD_NOT_DRAFT ketika periode sudah berstatus open', function () {
    $period = periodForClosure($this->businessUnitA);

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    try {
        $this->service->open($period->id);
        $this->fail('Expected PeriodNotDraftException was not thrown.');
    } catch (PeriodNotDraftException $e) {
        expect($e->getStatusCode())->toBe(409);
        expect($e->errorCode())->toBe('PERIOD_NOT_DRAFT');
        expect($e->getMessage())->toContain('sudah terbuka');
        // An already-open period must NOT be pointed at reopen — that
        // pointer belongs to the closed case only.
        expect($e->getMessage())->not->toContain('Buka Kembali Periode');
    }

    expect(collect($queries)->filter(fn (string $sql) => str_starts_with(strtolower(trim($sql)), 'update')))
        ->toHaveCount(0);
    expect($period->fresh()->status)->toBe(PeriodStatus::Open);
});

// Case 50
it('open: melempar 409 PERIOD_NOT_DRAFT untuk periode closed dan mengarahkan ke Buka Kembali Periode', function () {
    $period = Period::factory()
        ->forBusinessUnit($this->businessUnitA)
        ->stationType('sterilizer')
        ->range('2026-10-01', '2026-10-31')
        ->closed($this->otherAdmin, '2026-11-01 09:14:00')
        ->create();

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    try {
        $this->service->open($period->id);
        $this->fail('Expected PeriodNotDraftException was not thrown.');
    } catch (PeriodNotDraftException $e) {
        expect($e->getStatusCode())->toBe(409);
        expect($e->errorCode())->toBe('PERIOD_NOT_DRAFT');
        // A closed period gets a DIFFERENT message from an open one, and it
        // names the other action explicitly — "Buka Periode" and "Buka
        // Kembali Periode" are trivially confused.
        expect($e->getMessage())->toContain('sudah tertutup');
        expect($e->getMessage())->toContain('Buka Kembali Periode');
        expect($e->getMessage())->not->toContain('sudah terbuka');
    }

    expect(collect($queries)->filter(fn (string $sql) => str_starts_with(strtolower(trim($sql)), 'update')))
        ->toHaveCount(0);

    $fresh = $period->fresh();
    expect($fresh->status)->toBe(PeriodStatus::Closed);
    expect($fresh->closed_by)->toBe($this->otherAdmin->id);
});

// Case 51
it('open: menjalankan UPDATE berkondisi dengan klausa WHERE status draft, bukan save() model', function () {
    $period = draftPeriodForOpening($this->businessUnitA);

    $statements = [];
    DB::listen(function ($query) use (&$statements) {
        $statements[] = ['sql' => $query->sql, 'bindings' => $query->bindings];
    });

    $this->service->open($period->id);

    $updates = collect($statements)
        ->filter(fn (array $s) => str_starts_with(strtolower(trim($s['sql'])), 'update'))
        ->values();

    // Exactly one UPDATE — not a read-then-save() pair.
    expect($updates)->toHaveCount(1);

    $sql = strtolower($updates[0]['sql']);
    expect($sql)->toContain('periods');

    // The guard lives in the WHERE clause: BOTH the id and the status must
    // be there. A save() on the loaded model would key on the id alone and
    // would silently overwrite a row another Admin already opened.
    $where = substr($sql, (int) strpos($sql, ' where '));
    expect($where)->toContain('id');
    expect($where)->toContain('status');

    expect($updates[0]['bindings'])->toContain('draft');
    expect($updates[0]['bindings'])->toContain('open');
    expect($updates[0]['bindings'])->toContain($this->admin->id);
});

// Case 52
it('open: melempar 409 PERIOD_NOT_DRAFT ketika UPDATE berkondisi mengenai 0 baris', function () {
    $period = draftPeriodForOpening($this->businessUnitA);

    // Simulate the race exactly where it happens: between the findOrFail()
    // read (which still sees 'draft') and the conditional UPDATE, another
    // Admin opens the same row straight in the database. The in-memory
    // model the service holds is therefore stale and the UPDATE matches 0
    // rows.
    $raced = false;
    DB::listen(function ($query) use (&$raced, $period) {
        if ($raced || ! str_starts_with(strtolower(trim($query->sql)), 'select')) {
            return;
        }

        if (! str_contains(strtolower($query->sql), 'periods')) {
            return;
        }

        $raced = true;
        DB::table('periods')->where('id', $period->id)->update(['status' => PeriodStatus::Open->value]);
    });

    try {
        $this->service->open($period->id);
        $this->fail('Expected PeriodNotDraftException was not thrown.');
    } catch (PeriodNotDraftException $e) {
        expect($e->getStatusCode())->toBe(409);
        expect($e->errorCode())->toBe('PERIOD_NOT_DRAFT');
        // The message names the status the row actually has NOW.
        expect($e->getMessage())->toContain('sudah terbuka');
    }

    expect($raced)->toBeTrue();

    // No silent overwrite: the loser never stamped updated_by.
    $fresh = $period->fresh();
    expect($fresh->status)->toBe(PeriodStatus::Open);
    expect($fresh->updated_by)->toBeNull();
});

// Case 53
it('open: tidak membaca, memvalidasi, maupun mengubah data stasiun mana pun', function () {
    $period = draftPeriodForOpening($this->businessUnitA, null);

    $records = SterilizerRecord::factory()
        ->forStation($this->sterilizerStation)
        ->onDate('2026-10-10')
        ->count(3)
        ->create();

    $before = SterilizerRecord::query()->orderBy('id')->get()->map(fn ($r) => [
        $r->id, $r->checked_by, $r->acknowledged_by, (string) $r->updated_at,
    ])->all();

    // The ONLY station-record collaborator this service has is
    // RecordVerificationService (close() uses it to resolve the 18 record
    // models). open() must never reach for it.
    $verification = Mockery::mock(RecordVerificationService::class);
    $verification->shouldNotReceive('modelForStationType');
    $verification->shouldNotReceive('canVerify');

    $service = new PeriodClosureService($verification);

    $touchedTables = [];
    DB::listen(function ($query) use (&$touchedTables) {
        $touchedTables[] = strtolower($query->sql);
    });

    $result = $service->open($period->id);

    expect($result['status'])->toBe('open');

    // data_operations are exactly one period SELECT and one period UPDATE —
    // no *_records table is read or written, unlike close() which counts
    // unverified records across up to 18 tables.
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
it('open: mengembalikan id dan status open serta mencatat updated_by ketika syarat terpenuhi', function () {
    $period = draftPeriodForOpening($this->businessUnitA);

    $result = $this->service->open($period->id);

    expect($result)->toBe([
        'id' => $period->id,
        'status' => 'open',
    ]);

    $fresh = $period->fresh();
    expect($fresh->status)->toBe(PeriodStatus::Open);
    expect($fresh->updated_by)->toBe($this->admin->id);
    // Opening is not closing: the closure columns stay empty.
    expect($fresh->closed_by)->toBeNull();
    expect($fresh->closed_at)->toBeNull();
});

// Case 55 — guards a rule that is very easy to "tidy up" into a bug: only
// 'closed' locks a period. An open period must stay as editable and as
// deletable as a draft one.
it('open: periode berstatus open tetap dapat diubah dan dihapus', function () {
    $periodService = app(PeriodService::class);

    $period = draftPeriodForOpening($this->businessUnitA);
    $this->service->open($period->id);
    expect($period->fresh()->status)->toBe(PeriodStatus::Open);

    $updated = $periodService->update($period->id, [
        'business_unit_id' => $this->businessUnitA->id,
        'station_type' => 'sterilizer',
        'name' => 'Oktober 2026 (revisi)',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-31',
    ]);

    // No PeriodClosedImmutableException — and the status is untouched by the
    // edit (it is neither validated nor written by PeriodService::validate()).
    expect($updated['name'])->toBe('Oktober 2026 (revisi)');
    expect($updated['status'])->toBe('open');

    $periodService->delete($period->id);

    expect(Period::find($period->id))->toBeNull();
});
