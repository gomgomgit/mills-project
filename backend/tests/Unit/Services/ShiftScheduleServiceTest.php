<?php

/**
 * ShiftScheduleServiceTest — screen-171--jadwal-shift /
 * usecase-177--jadwal-shift (Jadwal Shift: mengisi SEL shift x tanggal dengan
 * orang, per Production Line, per bulan).
 *
 * Unit tests for App\Services\ShiftScheduleService against the real (sqlite,
 * RefreshDatabase) schema: NO mocks of the DB layer, real factories. Mirrors
 * tests/Unit/Services/ShiftServiceTest.php (`uses(TestCase::class,
 * RefreshDatabase::class)` is mandatory — without it the factories' faker
 * calls fail).
 *
 * COVERAGE MAP — api_contracts[0].unit_test_cases 1..29 of the screen
 * tech-spec. Every test name starts with `#N` (the case number); cases that
 * need several tests carry the same number.
 *
 * ── TWO ROLE READINGS THESE TESTS EXIST TO PROTECT ──────────────────────
 *
 * 1. THE GATE reads the EFFECTIVE role (`$user->role`, through
 *    App\Casts\EffectiveUserRole): the seven admin-equivalent roles
 *    (engineer, shift_supervisor, maintenance, mekanik, logistics, qa_qc,
 *    mill_admin) pass it AS ADMIN, on any mill (#5).
 * 2. CANDIDATE ELIGIBILITY reads the CHOSEN role (`getRawOriginal('role')`):
 *    an `engineer` is effectively Admin yet is NOT a candidate, while
 *    operator and supervisor users are, and mill_management / admin are not
 *    (#6, #12). A service reading `$user->role` for eligibility would
 *    silently flip exactly that distinction, so every eligibility assertion
 *    here sets the expectation from the raw column, never from `->role`.
 *
 * ── DATES ───────────────────────────────────────────────────────────────
 *
 * Nothing depends on "today". beforeEach freezes the clock at
 * 2026-10-12 10:00 Asia/Jakarta (Carbon::setTestNow; reset in afterEach) and
 * every month/date is passed explicitly. work_date is a plain 'YYYY-MM-DD'
 * string everywhere (sqlite compares dates as strings; PostgreSQL does not),
 * and no assertion relies on whereDate semantics. 2026-10-01 is a Thursday.
 *
 * ── CLOSED SHIFTS (#10, #27) ────────────────────────────────────────────
 *
 * A shift is closed by really calling `$shift->delete()` under
 * Carbon::setTestNow, so `deleted_at` is whatever Eloquent writes in the app
 * timezone — the same value production reads back. The service reads that
 * value as an app-timezone value (a known deviation from reading raw UTC);
 * the boundary tests below pin the REAL behaviour at 23:59 / 00:30 / 01:30
 * Asia/Jakarta, not a hypothetical UTC reading.
 */

use App\Enums\UserRole;
use App\Exceptions\ConfirmationRequiredException;
use App\Exceptions\NotACandidateException;
use App\Exceptions\ShiftClosedException;
use App\Models\BusinessUnit;
use App\Models\ProductionLine;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\ShiftScheduleService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

dataset('jadwal shift admin equivalents', fn () => array_map(fn (UserRole $r) => [$r], UserRole::adminEquivalents()));

dataset('jadwal shift mill-scoped roles', [
    'supervisor' => [UserRole::Supervisor],
    'mill_management' => [UserRole::MillManagement],
]);

beforeEach(function () {
    // Frozen clock: nothing below depends on the real date.
    Carbon::setTestNow(Carbon::parse('2026-10-12 10:00:00', 'Asia/Jakarta'));

    $this->service = new ShiftScheduleService;

    $this->millA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->millB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);
    $this->millC = BusinessUnit::factory()->create(['name' => 'Mill Tanpa Line']);

    $this->lineA1 = ProductionLine::factory()->forBusinessUnit($this->millA)->create(['name' => 'Line 1']);
    $this->lineA2 = ProductionLine::factory()->forBusinessUnit($this->millA)->create(['name' => 'Line 2']);
    $this->lineB1 = ProductionLine::factory()->forBusinessUnit($this->millB)->create(['name' => 'Line B1']);

    // Two live shifts on Line 1; Line 2 deliberately has none.
    $this->pagi = Shift::factory()->forProductionLine($this->lineA1)->named('Pagi')->range('07:00:00', '15:00:00')->create();
    $this->siang = Shift::factory()->forProductionLine($this->lineA1)->named('Siang')->range('15:00:00', '23:00:00')->create();
    $this->shiftB = Shift::factory()->forProductionLine($this->lineB1)->named('Pagi B')->range('07:00:00', '15:00:00')->create();

    $this->admin = User::factory()->role(UserRole::Admin)->create(['name' => 'Admin X', 'business_unit_id' => null]);
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->millA)->create(['name' => 'Sup Alpha']);
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->millA)->create(['name' => 'MM Alpha']);
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->millA)->create(['name' => 'Operator Gate']);

    // Candidates of mill A (active operator / supervisor).
    $this->budi = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->millA)->create(['name' => 'Budi Santoso']);
    $this->citra = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->millA)->create(['name' => 'Citra']);
    $this->dewi = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->millA)->create(['name' => 'Dewi Penyelia']);

    // A candidate of mill B, for the cross-mill cases.
    $this->bayu = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->millB)->create(['name' => 'Bayu Beta']);

    $this->actingAs($this->admin, 'web');
});

afterEach(function () {
    Carbon::setTestNow();
});

// ── helpers (prefixed jsh: Pest files share one global function namespace) ──

/** Sorted user ids currently in a cell, read straight from the table. */
function jshCell(string $shiftId, string $date): array
{
    $ids = DB::table('shift_assignments')
        ->where('shift_id', $shiftId)
        ->where('work_date', $date)
        ->pluck('user_id')
        ->all();
    sort($ids);

    return $ids;
}

function jshSorted(array $ids): array
{
    sort($ids);

    return array_values($ids);
}

/** Every assignment row as "shift|user|date", sorted — a full-table snapshot. */
function jshSnapshot(): array
{
    $rows = DB::table('shift_assignments')->get(['shift_id', 'user_id', 'work_date'])
        ->map(fn ($r) => $r->shift_id.'|'.$r->user_id.'|'.substr((string) $r->work_date, 0, 10))
        ->all();
    sort($rows);

    return $rows;
}

function jshCount(): int
{
    return DB::table('shift_assignments')->count();
}

function jshPlace(Shift $shift, User $user, string $date): ShiftAssignment
{
    return ShiftAssignment::factory()->create([
        'shift_id' => $shift->id,
        'user_id' => $user->id,
        'work_date' => $date,
        'created_by' => $user->id,
    ]);
}

function jshCatch(callable $callback): ?Throwable
{
    try {
        $callback();
    } catch (Throwable $e) {
        return $e;
    }

    return null;
}

/** HTTP status of an abort(), or null when nothing was thrown. */
function jshAbortStatus(callable $callback): ?int
{
    $e = jshCatch($callback);

    return $e instanceof HttpException ? $e->getStatusCode() : null;
}

/** Number of statements that touched shift_assignments while $callback ran. */
function jshAssignmentQueries(callable $callback): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        jshCatch($callback);
    } finally {
        $count = collect(DB::getQueryLog())
            ->filter(fn (array $q) => str_contains($q['query'], 'shift_assignments'))
            ->count();
        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    return $count;
}

/** Closes a shift for real: delete() with the clock set to $at (Asia/Jakarta). */
function jshCloseShiftAt(Shift $shift, string $at): void
{
    $previous = Carbon::getTestNow();
    Carbon::setTestNow(Carbon::parse($at, 'Asia/Jakarta'));
    $shift->delete();
    Carbon::setTestNow($previous);
}

function jshGridCells(array $grid, string $shiftId): array
{
    return array_values(array_filter($grid['cells'], fn ($c) => $c['shift_id'] === $shiftId));
}

function jshGridCell(array $grid, string $shiftId, string $date): ?array
{
    foreach ($grid['cells'] as $cell) {
        if ($cell['shift_id'] === $shiftId && $cell['date'] === $date) {
            return $cell;
        }
    }

    return null;
}

// ── #1 operator: 403, zero shift_assignments queries ─────────────────────

it('#1 menolak operator dengan 403 pada keenam aksi dan opsi, dengan NOL kueri shift_assignments', function () {
    $this->actingAs($this->operator, 'web');
    $svc = $this->service;

    $calls = [
        'businessUnitOptions' => fn () => $svc->businessUnitOptions(),
        'productionLineOptions' => fn () => $svc->productionLineOptions($this->millA->id),
        'grid' => fn () => $svc->grid($this->lineA1->id, '2026-10'),
        'candidates' => fn () => $svc->candidates($this->lineA1->id),
        'saveCell' => fn () => $svc->saveCell(['shift_id' => $this->pagi->id, 'date' => '2026-10-05', 'user_ids' => [$this->budi->id]]),
        'apply' => fn () => $svc->apply(['shift_id' => $this->pagi->id, 'month' => '2026-10', 'dates' => ['2026-10-05'], 'user_ids' => [$this->budi->id]]),
        'copyPrevious' => fn () => $svc->copyPrevious(['production_line_id' => $this->lineA1->id, 'month' => '2026-10']),
    ];

    foreach ($calls as $name => $call) {
        expect(jshAbortStatus($call))->toBe(403, "$name harus 403 untuk operator");
        expect(jshAssignmentQueries($call))->toBe(0, "$name menyentuh shift_assignments sebelum gate");
    }

    expect(jshCount())->toBe(0);
});

// ── #2 / #3 supervisor + mill_management on another mill ─────────────────

it('#2/#3 menolak supervisor dan mill_management atas mill lain dengan 403, tanpa penulisan', function (UserRole $role) {
    $actor = User::factory()->role($role)->forBusinessUnit($this->millA)->create();
    $this->actingAs($actor, 'web');
    $svc = $this->service;

    jshPlace($this->shiftB, $this->bayu, '2026-10-05');
    $before = jshSnapshot();

    $calls = [
        'productionLineOptions' => fn () => $svc->productionLineOptions($this->millB->id),
        'grid' => fn () => $svc->grid($this->lineB1->id, '2026-10'),
        'candidates' => fn () => $svc->candidates($this->lineB1->id),
        'saveCell' => fn () => $svc->saveCell(['shift_id' => $this->shiftB->id, 'date' => '2026-10-06', 'user_ids' => [$this->bayu->id]]),
        'apply' => fn () => $svc->apply(['shift_id' => $this->shiftB->id, 'month' => '2026-10', 'dates' => ['2026-10-06'], 'user_ids' => [$this->bayu->id]]),
        'copyPrevious' => fn () => $svc->copyPrevious(['production_line_id' => $this->lineB1->id, 'month' => '2026-10']),
    ];

    foreach ($calls as $name => $call) {
        expect(jshAbortStatus($call))->toBe(403, "$name harus 403 atas mill lain");
    }

    expect(jshSnapshot())->toBe($before);

    // Own mill is fine, so the 403 above is about the mill and not the role.
    expect($svc->grid($this->lineA1->id, '2026-10')['state'])->toBe('ready');
})->with('jadwal shift mill-scoped roles');

it('#2 supervisor tanpa business_unit_id tidak boleh menyentuh mill mana pun (403), bukan semua mill', function () {
    $orphan = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);
    $this->actingAs($orphan, 'web');

    expect(jshAbortStatus(fn () => $this->service->grid($this->lineA1->id, '2026-10')))->toBe(403);
    expect(jshAbortStatus(fn () => $this->service->productionLineOptions($this->millA->id)))->toBe(403);
});

// ── #4 fixed mill options ────────────────────────────────────────────────

it('#4 supervisor/mill_management hanya melihat satu mill pada options dan fixed=true', function (UserRole $role) {
    $actor = User::factory()->role($role)->forBusinessUnit($this->millA)->create();
    $this->actingAs($actor, 'web');

    $result = $this->service->businessUnitOptions();

    expect($result['fixed'])->toBeTrue()
        ->and($result['data'])->toHaveCount(1)
        ->and($result['data'][0]['id'])->toBe($this->millA->id)
        ->and($result['data'][0]['name'])->toBe('Mill Alpha');
})->with('jadwal shift mill-scoped roles');

it('#4 admin melihat semua mill dan fixed=false', function () {
    $result = $this->service->businessUnitOptions();

    expect($result['fixed'])->toBeFalse()
        ->and(array_column($result['data'], 'id'))->toEqualCanonicalizing(BusinessUnit::query()->pluck('id')->all());
});

// ── #5 seven admin-equivalent roles ──────────────────────────────────────

it('#5 tujuh role setara Admin lolos gate sebagai admin pada mill mana pun', function (UserRole $role) {
    // A mill-A account working on mill B proves it is read as an unrestricted Admin.
    $actor = User::factory()->role($role)->forBusinessUnit($this->millA)->create();

    // Chosen role is stored; the effective one is Admin — the premise of the test.
    expect($actor->getRawOriginal('role'))->toBe($role->value)
        ->and($actor->role)->toBe(UserRole::Admin);

    $this->actingAs($actor, 'web');

    $options = $this->service->businessUnitOptions();
    expect($options['fixed'])->toBeFalse()
        ->and(array_column($options['data'], 'id'))->toEqualCanonicalizing(BusinessUnit::query()->pluck('id')->all());

    expect($this->service->productionLineOptions($this->millB->id)['data'])->toHaveCount(1);
    expect($this->service->grid($this->lineB1->id, '2026-10')['state'])->toBe('ready');

    $saved = $this->service->saveCell(['shift_id' => $this->shiftB->id, 'date' => '2026-10-05', 'user_ids' => [$this->bayu->id]]);
    expect(array_column($saved['assignees'], 'user_id'))->toBe([$this->bayu->id]);
})->with('jadwal shift admin equivalents');

// ── #6 candidates use the CHOSEN role ────────────────────────────────────

it('#6 kandidat memakai role TERPILIH: engineer (efektif admin), mill_management, dan admin dikecualikan', function () {
    $engineer = User::factory()->role(UserRole::Engineer)->forBusinessUnit($this->millA)->create(['name' => 'Eko Engineer']);
    $mm = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->millA)->create(['name' => 'Mira MM']);
    $adm = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->millA)->create(['name' => 'Adi Admin']);

    // The engineer really is an Admin as far as ->role goes — that is the trap.
    expect(User::find($engineer->id)->role)->toBe(UserRole::Admin)
        ->and(User::find($engineer->id)->getRawOriginal('role'))->toBe('engineer');

    $result = $this->service->candidates($this->lineA1->id);
    $ids = array_column($result['data'], 'user_id');

    // Expected set built from the RAW column, never from ->role.
    $expected = User::query()
        ->where('business_unit_id', $this->millA->id)
        ->where('is_active', true)
        ->whereIn('role', ['operator', 'supervisor'])
        ->pluck('id')->all();

    expect($ids)->toEqualCanonicalizing($expected)
        ->and($ids)->toEqualCanonicalizing([$this->budi->id, $this->citra->id, $this->dewi->id, $this->operator->id, $this->supervisor->id])
        ->and(in_array($engineer->id, $ids, true))->toBeFalse()
        ->and(in_array($mm->id, $ids, true))->toBeFalse()
        ->and(in_array($adm->id, $ids, true))->toBeFalse()
        ->and(in_array($this->millManagement->id, $ids, true))->toBeFalse();

    // role_label shows the CHOSEN role, per row.
    $byId = collect($result['data'])->keyBy('user_id');
    expect($byId[$this->budi->id]['role_label'])->toBe(UserRole::Operator->label())
        ->and($byId[$this->dewi->id]['role_label'])->toBe(UserRole::Supervisor->label());
});

// ── #7 candidate filtering, ordering, search ─────────────────────────────

it('#7 kandidat: nonaktif dan mill lain tidak muncul, urut nama, pencarian lower()+LIKE tanpa membedakan huruf', function () {
    User::factory()->role(UserRole::Operator)->forBusinessUnit($this->millA)->inactive()->create(['name' => 'Budi Nonaktif']);
    $abdul = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->millA)->create(['name' => 'Abdul Budiman']);

    $all = $this->service->candidates($this->lineA1->id)['data'];
    $names = array_column($all, 'name');

    expect($names)->not->toContain('Budi Nonaktif')
        ->and($names)->not->toContain('Bayu Beta')
        ->and($names)->toBe(collect($names)->sort()->values()->all());   // ordered by name

    $found = $this->service->candidates($this->lineA1->id, 'bUd')['data'];

    expect(array_column($found, 'user_id'))->toBe([$abdul->id, $this->budi->id])   // 'Abdul Budiman' < 'Budi Santoso'
        ->and(array_column($found, 'name'))->toBe(['Abdul Budiman', 'Budi Santoso']);
});

it('#7 pencarian memperlakukan % dan _ sebagai teks, bukan wildcard', function () {
    expect($this->service->candidates($this->lineA1->id, '%')['data'])->toBe([])
        ->and($this->service->candidates($this->lineA1->id, '_')['data'])->toBe([]);

    $odd = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->millA)->create(['name' => 'Pak_Eko 100%']);

    expect(array_column($this->service->candidates($this->lineA1->id, '_eko')['data'], 'user_id'))->toBe([$odd->id])
        ->and(array_column($this->service->candidates($this->lineA1->id, '100%')['data'], 'user_id'))->toBe([$odd->id]);
});

// ── #8 empty_reason ──────────────────────────────────────────────────────

it('#8 candidates mengembalikan empty_reason no_candidates bila mill tanpa calon aktif', function () {
    $lineC = ProductionLine::factory()->forBusinessUnit($this->millC)->create(['name' => 'Line C']);
    // Only an inactive operator and a non-candidate role live in mill C.
    User::factory()->role(UserRole::Operator)->forBusinessUnit($this->millC)->inactive()->create();
    User::factory()->role(UserRole::Engineer)->forBusinessUnit($this->millC)->create();

    $result = $this->service->candidates($lineC->id);

    expect($result['data'])->toBe([])
        ->and($result['empty_reason'])->toBe('no_candidates');
});

it('#8 pencarian tanpa hasil di mill yang PUNYA calon bukan no_candidates', function () {
    $result = $this->service->candidates($this->lineA1->id, 'zzzz-tidak-ada');

    expect($result['data'])->toBe([])
        ->and($result['empty_reason'])->toBeNull();

    expect($this->service->candidates($this->lineA1->id)['empty_reason'])->toBeNull();
});

// ── #9 already_on_shifts ─────────────────────────────────────────────────

it('#9 candidates dengan date menyertakan already_on_shifts untuk shift lain pada line itu', function () {
    jshPlace($this->pagi, $this->budi, '2026-10-05');
    jshPlace($this->siang, $this->budi, '2026-10-05');
    jshPlace($this->pagi, $this->budi, '2026-10-06');          // other date: must not count
    jshPlace($this->shiftB, $this->bayu, '2026-10-05');        // other line/mill: must not count
    jshPlace($this->siang, $this->citra, '2026-10-06');        // other date

    $withDate = collect($this->service->candidates($this->lineA1->id, null, '2026-10-05')['data'])->keyBy('user_id');

    expect(array_column($withDate[$this->budi->id]['already_on_shifts'], 'shift_id'))
        ->toEqualCanonicalizing([$this->pagi->id, $this->siang->id])
        ->and(array_column($withDate[$this->budi->id]['already_on_shifts'], 'shift_name'))
        ->toEqualCanonicalizing(['Pagi', 'Siang'])
        ->and($withDate[$this->citra->id]['already_on_shifts'])->toBe([]);

    $withoutDate = collect($this->service->candidates($this->lineA1->id)['data'])->keyBy('user_id');
    expect($withoutDate[$this->budi->id]['already_on_shifts'])->toBe([]);
});

it('#9 date yang tidak ada di kalender pada candidates ditolak 422', function () {
    expect(jshCatch(fn () => $this->service->candidates($this->lineA1->id, null, '2026-02-30')))
        ->toBeInstanceOf(ValidationException::class);
    expect(jshCatch(fn () => $this->service->candidates($this->lineA1->id, null, '2026-10-5')))
        ->toBeInstanceOf(ValidationException::class);
});

/**
 * KNOWN FAILING — SUSPECTED IMPLEMENTATION DEFECT (not fixed here, tests only).
 * ShiftScheduleService::isValidDate() calls Carbon::createFromFormat() which
 * THROWS Carbon\Exceptions\InvalidFormatException on a string that does not
 * match 'Y-m-d' at all ('05/10/2026', 'abc'), instead of returning false. In
 * candidates() (the only path where isValidDate() is not behind the Validator)
 * a malformed ?date= therefore surfaces as a 500, not 422 VALIDATION_ERROR.
 */
it('#9 date berformat salah total pada candidates ditolak 422 (bukan 500)', function (string $bad) {
    $e = jshCatch(fn () => $this->service->candidates($this->lineA1->id, null, $bad));

    expect($e)->toBeInstanceOf(ValidationException::class);
})->with(['05/10/2026', 'abc']);

// ── #10 SHIFT_CLOSED ─────────────────────────────────────────────────────

it('#10 menolak penulisan ke shift tertutup dengan SHIFT_CLOSED (cell dan apply), histori utuh', function () {
    $closing = Shift::factory()->forProductionLine($this->lineA1)->named('Malam')->range('23:00:00', '07:00:00')->create();
    jshPlace($closing, $this->budi, '2026-10-02');
    jshCloseShiftAt($closing, '2026-10-15 08:00:00');
    $before = jshSnapshot();

    $cell = jshCatch(fn () => $this->service->saveCell(['shift_id' => $closing->id, 'date' => '2026-10-20', 'user_ids' => [$this->citra->id]]));
    $apply = jshCatch(fn () => $this->service->apply([
        'shift_id' => $closing->id, 'month' => '2026-10', 'dates' => ['2026-10-20'], 'user_ids' => [$this->citra->id],
    ]));
    // Also a date BEFORE the closure: the whole shift is read-only for writes.
    $earlier = jshCatch(fn () => $this->service->saveCell(['shift_id' => $closing->id, 'date' => '2026-10-02', 'user_ids' => []]));

    foreach ([$cell, $apply, $earlier] as $e) {
        expect($e)->toBeInstanceOf(ShiftClosedException::class)
            ->and($e->getStatusCode())->toBe(422)
            ->and($e->errorCode())->toBe('SHIFT_CLOSED');
    }

    expect(jshSnapshot())->toBe($before)
        ->and(jshCell($closing->id, '2026-10-02'))->toBe([$this->budi->id]);
});

// ── #11 NOT_FOUND ────────────────────────────────────────────────────────

it('#11 menolak shift_id yang tidak ada dengan ModelNotFound (NOT_FOUND 404)', function () {
    $ghost = (string) Str::uuid();

    expect(jshCatch(fn () => $this->service->saveCell(['shift_id' => $ghost, 'date' => '2026-10-05', 'user_ids' => []])))
        ->toBeInstanceOf(ModelNotFoundException::class);
    expect(jshCatch(fn () => $this->service->apply(['shift_id' => $ghost, 'month' => '2026-10', 'dates' => ['2026-10-05'], 'user_ids' => []])))
        ->toBeInstanceOf(ModelNotFoundException::class);
    expect(jshCatch(fn () => $this->service->grid($ghost, '2026-10')))
        ->toBeInstanceOf(ModelNotFoundException::class);
    expect(jshCount())->toBe(0);
});

// ── #12 NOT_A_CANDIDATE, all-or-nothing ──────────────────────────────────

it('#12 satu user bukan calon menolak SELURUH penyimpanan; pesan menyebut user dan alasannya; sel tetap', function () {
    $inactive = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->millA)->inactive()->create(['name' => 'Rina Nonaktif']);
    $engineer = User::factory()->role(UserRole::Engineer)->forBusinessUnit($this->millA)->create(['name' => 'Eko Engineer']);

    jshPlace($this->pagi, $this->budi, '2026-10-05');   // the cell holds [X]
    $before = jshSnapshot();

    $e = jshCatch(fn () => $this->service->saveCell([
        'shift_id' => $this->pagi->id,
        'date' => '2026-10-05',
        'user_ids' => [$this->citra->id, $inactive->id, $engineer->id, $this->bayu->id],
    ]));

    expect($e)->toBeInstanceOf(NotACandidateException::class)
        ->and($e->getStatusCode())->toBe(422)
        ->and($e->errorCode())->toBe('NOT_A_CANDIDATE')
        ->and($e->getMessage())->toContain('Rina Nonaktif (nonaktif)')
        ->and($e->getMessage())->toContain('Eko Engineer (role Engineer bukan Operator atau Supervisor)')
        ->and($e->getMessage())->toContain('Bayu Beta (bertugas di mill lain)')
        ->and($e->getMessage())->not->toContain('Citra');   // the valid one is not blamed

    // All-or-nothing: the valid user (Citra) was NOT written either.
    expect(jshSnapshot())->toBe($before)
        ->and(jshCell($this->pagi->id, '2026-10-05'))->toBe([$this->budi->id]);
});

it('#12 mill_management dan admin juga bukan calon (role terpilih), dan uuid tak dikenal ditolak', function () {
    $mm = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->millA)->create(['name' => 'Mira MM']);
    $adm = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->millA)->create(['name' => 'Adi Admin']);
    $ghost = (string) Str::uuid();

    foreach ([[$mm, 'Mira MM (role '.UserRole::MillManagement->label().' bukan Operator atau Supervisor)'],
        [$adm, 'Adi Admin (role '.UserRole::Admin->label().' bukan Operator atau Supervisor)']] as [$person, $needle]) {
        $e = jshCatch(fn () => $this->service->saveCell(['shift_id' => $this->pagi->id, 'date' => '2026-10-05', 'user_ids' => [$person->id]]));
        expect($e)->toBeInstanceOf(NotACandidateException::class)
            ->and($e->getMessage())->toContain($needle);
    }

    $e = jshCatch(fn () => $this->service->saveCell(['shift_id' => $this->pagi->id, 'date' => '2026-10-05', 'user_ids' => [$ghost]]));
    expect($e)->toBeInstanceOf(NotACandidateException::class)
        ->and($e->getMessage())->toContain($ghost.' (user tidak ditemukan)');

    expect(jshCount())->toBe(0);
});

// ── #13 duplicates ───────────────────────────────────────────────────────

it('#13 user_ids duplikat dilebur: tepat U1 dan U2, satu baris per user', function () {
    $result = $this->service->saveCell([
        'shift_id' => $this->pagi->id,
        'date' => '2026-10-05',
        'user_ids' => [$this->budi->id, $this->budi->id, $this->citra->id],
    ]);

    expect(jshCount())->toBe(2)
        ->and(jshCell($this->pagi->id, '2026-10-05'))->toBe(jshSorted([$this->budi->id, $this->citra->id]))
        ->and($result['assignees'])->toHaveCount(2);
});

// ── #14 idempotent ───────────────────────────────────────────────────────

it('#14 penaruhan ulang idempoten: baris yang sama, tanpa galat, changed_by_others=false', function () {
    $first = jshPlace($this->pagi, $this->budi, '2026-10-05');

    $result = $this->service->saveCell([
        'shift_id' => $this->pagi->id,
        'date' => '2026-10-05',
        'user_ids' => [$this->budi->id],
        'loaded_user_ids' => [$this->budi->id],
    ]);

    expect(jshCount())->toBe(1)
        ->and(DB::table('shift_assignments')->value('id'))->toBe($first->id)   // not deleted + re-inserted
        ->and($result['changed_by_others'])->toBeFalse()
        ->and($result['previous_user_ids'])->toBe([])
        ->and(array_column($result['assignees'], 'user_id'))->toBe([$this->budi->id]);
});

// ── #15 same user on two shifts, same day ────────────────────────────────

it('#15 user yang sama di dua shift pada tanggal yang sama diterima tanpa pemeriksaan bentrok', function () {
    $this->service->saveCell(['shift_id' => $this->pagi->id, 'date' => '2026-10-05', 'user_ids' => [$this->budi->id]]);
    $this->service->saveCell(['shift_id' => $this->siang->id, 'date' => '2026-10-05', 'user_ids' => [$this->budi->id]]);

    expect(jshCount())->toBe(2)
        ->and(jshCell($this->pagi->id, '2026-10-05'))->toBe([$this->budi->id])
        ->and(jshCell($this->siang->id, '2026-10-05'))->toBe([$this->budi->id]);
});

// ── #16 empty list clears the cell ───────────────────────────────────────

it('#16 daftar kosong mengosongkan sel dan TIDAK menyentuh sel lain', function () {
    jshPlace($this->pagi, $this->budi, '2026-10-05');
    jshPlace($this->pagi, $this->citra, '2026-10-05');
    jshPlace($this->pagi, $this->budi, '2026-10-06');     // same shift, other date
    jshPlace($this->siang, $this->citra, '2026-10-05');   // same date, other shift
    $untouched = [
        $this->pagi->id.'|'.$this->budi->id.'|2026-10-06',
        $this->siang->id.'|'.$this->citra->id.'|2026-10-05',
    ];
    sort($untouched);

    $result = $this->service->saveCell(['shift_id' => $this->pagi->id, 'date' => '2026-10-05', 'user_ids' => []]);

    expect($result['assignees'])->toBe([])
        ->and(jshCell($this->pagi->id, '2026-10-05'))->toBe([])
        ->and(jshCount())->toBe(2)
        ->and(jshSnapshot())->toBe($untouched);

    // Clearing an already-empty cell is a quiet no-op, not an error.
    $again = $this->service->saveCell(['shift_id' => $this->pagi->id, 'date' => '2026-10-05', 'user_ids' => []]);
    expect($again['assignees'])->toBe([])->and(jshCount())->toBe(2);
});

// ── #17 removing an inactive user ────────────────────────────────────────

it('#17 mengeluarkan user nonaktif selalu diizinkan, dan yang dipertahankan tidak divalidasi ulang', function () {
    $gone = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->millA)->create(['name' => 'Rina Pindah']);
    jshPlace($this->pagi, $gone, '2026-10-05');
    jshPlace($this->pagi, $this->citra, '2026-10-05');
    $gone->update(['is_active' => false]);   // deactivated AFTER being assigned

    // Keeping the inactive one while editing the rest: not re-validated.
    $this->service->saveCell(['shift_id' => $this->pagi->id, 'date' => '2026-10-05', 'user_ids' => [$gone->id, $this->citra->id, $this->budi->id]]);
    expect(jshCell($this->pagi->id, '2026-10-05'))->toBe(jshSorted([$gone->id, $this->citra->id, $this->budi->id]));

    // Removing it: allowed, no NOT_A_CANDIDATE.
    $result = $this->service->saveCell(['shift_id' => $this->pagi->id, 'date' => '2026-10-05', 'user_ids' => [$this->citra->id, $this->budi->id]]);
    expect(jshCell($this->pagi->id, '2026-10-05'))->toBe(jshSorted([$this->citra->id, $this->budi->id]))
        ->and(array_column($result['assignees'], 'user_id'))->not->toContain($gone->id);

    // But ADDING an inactive user to ANOTHER cell is refused.
    $e = jshCatch(fn () => $this->service->saveCell(['shift_id' => $this->pagi->id, 'date' => '2026-10-06', 'user_ids' => [$gone->id]]));
    expect($e)->toBeInstanceOf(NotACandidateException::class)
        ->and(jshCell($this->pagi->id, '2026-10-06'))->toBe([]);
});

// ── #18 / #19 last-write-wins + changed_by_others ────────────────────────

it('#18 last-write-wins: changed_by_others=true, previous_user_ids=[U2], isi akhir = user_ids terbaru', function () {
    jshPlace($this->pagi, $this->citra, '2026-10-05');   // someone else saved U2 meanwhile

    $result = $this->service->saveCell([
        'shift_id' => $this->pagi->id,
        'date' => '2026-10-05',
        'user_ids' => [$this->dewi->id],
        'loaded_user_ids' => [$this->budi->id],   // what THIS screen loaded
    ]);

    expect($result['changed_by_others'])->toBeTrue()
        ->and($result['previous_user_ids'])->toBe([$this->citra->id])
        ->and(array_column($result['assignees'], 'user_id'))->toBe([$this->dewi->id])
        ->and(jshCell($this->pagi->id, '2026-10-05'))->toBe([$this->dewi->id]);
});

it('#19 changed_by_others=false bila isi sel sama dengan loaded_user_ids (urutan diabaikan) atau loaded tidak dikirim', function () {
    jshPlace($this->pagi, $this->budi, '2026-10-05');
    jshPlace($this->pagi, $this->citra, '2026-10-05');

    $same = $this->service->saveCell([
        'shift_id' => $this->pagi->id, 'date' => '2026-10-05',
        'user_ids' => [$this->budi->id], 'loaded_user_ids' => [$this->citra->id, $this->budi->id],   // reversed order
    ]);
    expect($same['changed_by_others'])->toBeFalse()->and($same['previous_user_ids'])->toBe([]);

    $absent = $this->service->saveCell(['shift_id' => $this->pagi->id, 'date' => '2026-10-05', 'user_ids' => [$this->dewi->id]]);
    expect($absent['changed_by_others'])->toBeFalse()->and($absent['previous_user_ids'])->toBe([]);

    // Both sides empty (scenario "Dua pengatur": loaded_user_ids=[] on a cell nobody has filled).
    $empty = $this->service->saveCell(['shift_id' => $this->siang->id, 'date' => '2026-10-05', 'user_ids' => [$this->budi->id], 'loaded_user_ids' => []]);
    expect($empty['changed_by_others'])->toBeFalse();
});

// ── #20 apply dry_run ────────────────────────────────────────────────────

it('#20 apply dry_run tidak menulis apa pun dan melaporkan target_cells, cells_already_filled, people_affected', function () {
    jshPlace($this->pagi, $this->citra, '2026-10-01');                       // filled, 1 person displaced
    jshPlace($this->pagi, $this->citra, '2026-10-02');
    jshPlace($this->pagi, $this->dewi, '2026-10-02');                        // filled, 2 displaced
    $before = jshSnapshot();                                                  // 2026-10-03 stays empty

    $result = $this->service->apply([
        'shift_id' => $this->pagi->id,
        'month' => '2026-10',
        'dates' => ['2026-10-01', '2026-10-02', '2026-10-03'],
        'user_ids' => [$this->budi->id],
        'dry_run' => true,
    ]);

    expect($result['target_cells'])->toBe(3)
        ->and($result['cells_already_filled'])->toBe(2)
        ->and($result['people_affected'])->toBe(3)
        ->and($result['outside_month_ignored'])->toBe(0)
        ->and($result['applied'])->toBeFalse()
        ->and($result['written_cells'])->toBe(0)
        ->and(jshSnapshot())->toBe($before);
});

it('#20 people_affected conta hanya orang yang TERGESER, bukan yang tetap', function () {
    jshPlace($this->pagi, $this->budi, '2026-10-01');   // stays (budi is in the new list)
    jshPlace($this->pagi, $this->citra, '2026-10-01');  // displaced

    $result = $this->service->apply([
        'shift_id' => $this->pagi->id, 'month' => '2026-10', 'dates' => ['2026-10-01'],
        'user_ids' => [$this->budi->id], 'dry_run' => true,
    ]);

    expect($result['cells_already_filled'])->toBe(1)->and($result['people_affected'])->toBe(1);
});

// ── #21 CONFIRMATION_REQUIRED ────────────────────────────────────────────

it('#21 apply menimpa tanpa confirm_overwrite -> 409 CONFIRMATION_REQUIRED, nol tulis; dengan konfirmasi diterapkan', function () {
    jshPlace($this->pagi, $this->citra, '2026-10-01');
    jshPlace($this->pagi, $this->citra, '2026-10-02');
    jshPlace($this->pagi, $this->dewi, '2026-10-02');
    jshPlace($this->pagi, $this->budi, '2026-10-04');   // already equals the new list: not counted as written
    $before = jshSnapshot();

    $payload = [
        'shift_id' => $this->pagi->id, 'month' => '2026-10',
        'dates' => ['2026-10-01', '2026-10-02', '2026-10-03', '2026-10-04'],
        'user_ids' => [$this->budi->id], 'dry_run' => false,
    ];

    foreach ([$payload, $payload + ['confirm_overwrite' => false]] as $withoutConfirm) {
        $e = jshCatch(fn () => $this->service->apply($withoutConfirm));
        expect($e)->toBeInstanceOf(ConfirmationRequiredException::class)
            ->and($e->getStatusCode())->toBe(409)
            ->and($e->errorCode())->toBe('CONFIRMATION_REQUIRED')
            ->and(jshSnapshot())->toBe($before);
    }

    $result = $this->service->apply($payload + ['confirm_overwrite' => true]);

    expect($result['applied'])->toBeTrue()
        ->and($result['target_cells'])->toBe(4)
        ->and($result['cells_already_filled'])->toBe(3)
        ->and($result['written_cells'])->toBe(3);   // 10-04 already equalled the list

    foreach (['2026-10-01', '2026-10-02', '2026-10-03', '2026-10-04'] as $date) {
        expect(jshCell($this->pagi->id, $date))->toBe([$this->budi->id]);
    }
    expect(jshCount())->toBe(4);
});

it('#21 apply ke sel yang semuanya kosong tidak butuh konfirmasi', function () {
    $result = $this->service->apply([
        'shift_id' => $this->pagi->id, 'month' => '2026-10', 'dates' => ['2026-10-01', '2026-10-02'],
        'user_ids' => [$this->budi->id, $this->citra->id],
    ]);

    expect($result['applied'])->toBeTrue()
        ->and($result['written_cells'])->toBe(2)
        ->and(jshCount())->toBe(4);
});

it('#21 apply dengan user_ids kosong pada sel berisi butuh konfirmasi, lalu mengosongkan tanpa menyentuh sel lain', function () {
    jshPlace($this->pagi, $this->budi, '2026-10-05');
    jshPlace($this->pagi, $this->citra, '2026-10-06');
    jshPlace($this->pagi, $this->budi, '2026-10-07');   // not a target
    jshPlace($this->siang, $this->budi, '2026-10-05');  // other shift
    $before = jshSnapshot();

    $payload = ['shift_id' => $this->pagi->id, 'month' => '2026-10', 'dates' => ['2026-10-05', '2026-10-06'], 'user_ids' => []];

    expect(jshCatch(fn () => $this->service->apply($payload + ['dry_run' => false])))
        ->toBeInstanceOf(ConfirmationRequiredException::class);
    expect(jshSnapshot())->toBe($before);

    $dry = $this->service->apply($payload + ['dry_run' => true]);
    expect($dry['cells_already_filled'])->toBe(2)->and($dry['people_affected'])->toBe(2);

    $done = $this->service->apply($payload + ['dry_run' => false, 'confirm_overwrite' => true]);

    expect($done['applied'])->toBeTrue()
        ->and($done['written_cells'])->toBe(2)
        ->and(jshCell($this->pagi->id, '2026-10-05'))->toBe([])
        ->and(jshCell($this->pagi->id, '2026-10-06'))->toBe([])
        ->and(jshCount())->toBe(2)
        ->and(jshCell($this->pagi->id, '2026-10-07'))->toBe([$this->budi->id])
        ->and(jshCell($this->siang->id, '2026-10-05'))->toBe([$this->budi->id]);
});

it('#21 apply dengan user bukan calon ditolak NOT_A_CANDIDATE dan tidak menulis apa pun', function () {
    $engineer = User::factory()->role(UserRole::Engineer)->forBusinessUnit($this->millA)->create();

    foreach ([true, false] as $dry) {
        $e = jshCatch(fn () => $this->service->apply([
            'shift_id' => $this->pagi->id, 'month' => '2026-10', 'dates' => ['2026-10-01'],
            'user_ids' => [$this->budi->id, $engineer->id], 'dry_run' => $dry,
        ]));
        expect($e)->toBeInstanceOf(NotACandidateException::class);
    }
    expect(jshCount())->toBe(0);
});

// ── #22 apply validation ─────────────────────────────────────────────────

it('#22 apply: dates dan range keduanya diisi, atau keduanya kosong, ditolak 422 tanpa menulis', function () {
    $both = jshCatch(fn () => $this->service->apply([
        'shift_id' => $this->pagi->id, 'month' => '2026-10',
        'dates' => ['2026-10-01'], 'range' => ['from' => '2026-10-01', 'to' => '2026-10-03'],
        'user_ids' => [$this->budi->id],
    ]));
    $neither = jshCatch(fn () => $this->service->apply([
        'shift_id' => $this->pagi->id, 'month' => '2026-10', 'dates' => [], 'user_ids' => [$this->budi->id],
    ]));
    $absent = jshCatch(fn () => $this->service->apply([
        'shift_id' => $this->pagi->id, 'month' => '2026-10', 'user_ids' => [$this->budi->id],
    ]));

    foreach ([$both, $neither, $absent] as $e) {
        expect($e)->toBeInstanceOf(ValidationException::class)
            ->and($e->errors())->toHaveKey('dates');
    }
    expect(jshCount())->toBe(0);
});

it('#22 apply: range terbalik, bulan salah, tanggal mustahil, weekday di luar 1..7 -> 422', function () {
    $base = ['shift_id' => $this->pagi->id, 'month' => '2026-10', 'user_ids' => [$this->budi->id]];

    $reversed = jshCatch(fn () => $this->service->apply($base + ['range' => ['from' => '2026-10-10', 'to' => '2026-10-01']]));
    expect($reversed)->toBeInstanceOf(ValidationException::class)->and($reversed->errors())->toHaveKey('range.to');

    foreach ([
        'bulan 13' => ['month' => '2026-13', 'dates' => ['2026-10-01']],
        'bulan bukan YYYY-MM' => ['month' => 'Oktober', 'dates' => ['2026-10-01']],
        'tanggal mustahil' => ['dates' => ['2026-02-30']],
        'weekday 8' => ['range' => ['from' => '2026-10-01', 'to' => '2026-10-05', 'weekdays' => [8]]],
        'range tanpa to' => ['range' => ['from' => '2026-10-01']],
    ] as $label => $override) {
        expect(jshCatch(fn () => $this->service->apply(array_merge($base, $override))))
            ->toBeInstanceOf(ValidationException::class, $label);
    }

    expect(jshCount())->toBe(0);
});

it('#22 apply: tanggal di luar month diabaikan, dihitung ke outside_month_ignored, dan tidak ditulis', function () {
    $result = $this->service->apply([
        'shift_id' => $this->pagi->id, 'month' => '2026-10',
        'dates' => ['2026-09-30', '2026-10-01', '2026-11-01', '2026-10-01'],   // duplicate collapsed
        'user_ids' => [$this->budi->id],
    ]);

    expect($result['target_cells'])->toBe(1)
        ->and($result['outside_month_ignored'])->toBe(2)
        ->and($result['written_cells'])->toBe(1)
        ->and(jshCount())->toBe(1)
        ->and(jshCell($this->pagi->id, '2026-10-01'))->toBe([$this->budi->id]);

    // A range straddling the month boundary: only the in-month days are written.
    $range = $this->service->apply([
        'shift_id' => $this->siang->id, 'month' => '2026-10',
        'range' => ['from' => '2026-09-28', 'to' => '2026-10-03'], 'user_ids' => [$this->budi->id],
    ]);
    expect($range['target_cells'])->toBe(3)->and($range['outside_month_ignored'])->toBe(3);
    expect(DB::table('shift_assignments')->where('shift_id', $this->siang->id)->where('work_date', '<', '2026-10-01')->count())->toBe(0);
});

it('#22 apply: filter weekdays pada range (1=Senin..7=Minggu)', function () {
    // 2026-10-01 is Thursday: Mondays in 01..14 are the 5th and the 12th.
    $mondays = $this->service->apply([
        'shift_id' => $this->pagi->id, 'month' => '2026-10',
        'range' => ['from' => '2026-10-01', 'to' => '2026-10-14', 'weekdays' => [1]],
        'user_ids' => [$this->budi->id],
    ]);
    $dates = DB::table('shift_assignments')->where('shift_id', $this->pagi->id)->orderBy('work_date')->pluck('work_date')->map(fn ($d) => substr((string) $d, 0, 10))->all();

    expect($mondays['target_cells'])->toBe(2)->and($dates)->toBe(['2026-10-05', '2026-10-12']);

    // Saturday + Sunday in 01..14: 3, 4, 10, 11.
    $weekend = $this->service->apply([
        'shift_id' => $this->siang->id, 'month' => '2026-10',
        'range' => ['from' => '2026-10-01', 'to' => '2026-10-14', 'weekdays' => [6, 7]],
        'user_ids' => [$this->budi->id], 'dry_run' => true,
    ]);
    expect($weekend['target_cells'])->toBe(4);

    // No weekdays filter = every day in the range.
    $all = $this->service->apply([
        'shift_id' => $this->siang->id, 'month' => '2026-10',
        'range' => ['from' => '2026-10-01', 'to' => '2026-10-14'], 'user_ids' => [], 'dry_run' => true,
    ]);
    expect($all['target_cells'])->toBe(14);
});

// ── #23 copy-previous ────────────────────────────────────────────────────

/** Builds the October source / November target scenario of case #23. */
function jshSeedCopyScenario(object $t): array
{
    $closing = Shift::factory()->forProductionLine($t->lineA1)->named('Malam')->range('23:00:00', '07:00:00')->create();
    $inactive = User::factory()->role(UserRole::Operator)->forBusinessUnit($t->millA)->create(['name' => 'Rina Nonaktif']);
    $engineer = User::factory()->role(UserRole::Engineer)->forBusinessUnit($t->millA)->create(['name' => 'Eko Engineer']);

    // Source: October 2026.
    jshPlace($t->pagi, $t->budi, '2026-10-05');            // -> copied
    jshPlace($t->pagi, $t->citra, '2026-10-05');           // -> copied (same cell, 2 people)
    jshPlace($t->pagi, $t->budi, '2026-10-06');            // target cell already filled -> kept
    jshPlace($t->pagi, $inactive, '2026-10-07');           // inactive -> skipped
    jshPlace($t->pagi, $engineer, '2026-10-08');           // not a candidate -> skipped
    jshPlace($t->pagi, $t->budi, '2026-10-31');            // November has 30 days -> missing_day
    jshPlace($closing, $t->citra, '2026-10-10');           // shift closed now -> closed_shift
    $inactive->update(['is_active' => false]);
    jshCloseShiftAt($closing, '2026-10-20 08:00:00');

    // Another line's October data must never leak into line 1.
    $otherLineShift = Shift::factory()->forProductionLine($t->lineA2)->named('Pagi A2')->range('07:00:00', '15:00:00')->create();
    jshPlace($otherLineShift, $t->budi, '2026-10-09');

    // Target: November already has one filled cell (Pagi 11-06 = Dewi).
    jshPlace($t->pagi, $t->dewi, '2026-11-06');

    return compact('closing', 'inactive', 'engineer', 'otherLineShift');
}

it('#23 copy-previous mempertahankan sel berisi dan menghitung skipped per alasan', function () {
    jshSeedCopyScenario($this);

    $result = $this->service->copyPrevious(['production_line_id' => $this->lineA1->id, 'month' => '2026-11']);

    expect($result['source_month'])->toBe('2026-10')
        ->and($result['filled'])->toBe(1)
        ->and($result['kept'])->toBe(1)
        ->and($result['skipped'])->toBe(['closed_shift' => 1, 'inactive_or_not_candidate' => 2, 'missing_day' => 1])
        ->and($result['nothing_to_copy'])->toBeFalse()
        ->and($result['applied'])->toBeTrue();

    // 11-05 copied with BOTH people; 11-06 (kept) was NOT overwritten with Budi.
    expect(jshCell($this->pagi->id, '2026-11-05'))->toBe(jshSorted([$this->budi->id, $this->citra->id]))
        ->and(jshCell($this->pagi->id, '2026-11-06'))->toBe([$this->dewi->id])
        ->and(jshCell($this->pagi->id, '2026-11-07'))->toBe([])
        ->and(jshCell($this->pagi->id, '2026-11-08'))->toBe([])
        ->and(jshCell($this->pagi->id, '2026-11-09'))->toBe([])    // the other line's row did not leak
        ->and(jshCell($this->pagi->id, '2026-11-10'))->toBe([])    // closed shift not copied
        ->and(DB::table('shift_assignments')->where('work_date', '>=', '2026-11-01')->count())->toBe(3);   // 2 + the kept Dewi

    // The source month itself is untouched (7 Pagi/Malam rows + 1 other line).
    expect(DB::table('shift_assignments')->where('work_date', '<', '2026-11-01')->count())->toBe(8);
});

it('#23 copy-previous dry_run=true menghitung yang sama tetapi tidak menulis (applied=false)', function () {
    jshSeedCopyScenario($this);
    $before = jshSnapshot();

    $result = $this->service->copyPrevious(['production_line_id' => $this->lineA1->id, 'month' => '2026-11', 'dry_run' => true]);

    expect($result['filled'])->toBe(1)
        ->and($result['kept'])->toBe(1)
        ->and($result['skipped'])->toBe(['closed_shift' => 1, 'inactive_or_not_candidate' => 2, 'missing_day' => 1])
        ->and($result['applied'])->toBeFalse()
        ->and(jshSnapshot())->toBe($before);
});

it('#23 copy-previous dijalankan ulang idempoten: filled=0, kepalsuan tidak menambah baris', function () {
    jshSeedCopyScenario($this);
    $payload = ['production_line_id' => $this->lineA1->id, 'month' => '2026-11'];

    $this->service->copyPrevious($payload);
    $after = jshSnapshot();

    $second = $this->service->copyPrevious($payload);

    expect($second['filled'])->toBe(0)
        ->and($second['kept'])->toBe(2)     // 11-05 (copied before) and 11-06
        ->and(jshSnapshot())->toBe($after);
});

it('#23 copy-previous dari Januari mengambil Desember tahun sebelumnya', function () {
    $result = $this->service->copyPrevious(['production_line_id' => $this->lineA1->id, 'month' => '2027-01', 'dry_run' => true]);

    expect($result['source_month'])->toBe('2026-12');
});

// ── #24 copy-previous with empty source ──────────────────────────────────

it('#24 copy-previous saat bulan sumber kosong: nothing_to_copy=true, filled=0, nol tulis', function () {
    // Data exists, but in another month and on another line: not "the previous month of this line".
    jshPlace($this->pagi, $this->budi, '2026-09-05');
    $otherLineShift = Shift::factory()->forProductionLine($this->lineA2)->named('Pagi A2')->range('07:00:00', '15:00:00')->create();
    jshPlace($otherLineShift, $this->budi, '2026-10-05');
    $before = jshSnapshot();

    $result = $this->service->copyPrevious(['production_line_id' => $this->lineA1->id, 'month' => '2026-11']);

    expect($result['nothing_to_copy'])->toBeTrue()
        ->and($result['filled'])->toBe(0)
        ->and($result['kept'])->toBe(0)
        ->and($result['skipped'])->toBe(['closed_shift' => 0, 'inactive_or_not_candidate' => 0, 'missing_day' => 0])
        ->and(jshSnapshot())->toBe($before);
});

it('#24 copy-previous memvalidasi masukan dan lingkup mill', function () {
    expect(jshCatch(fn () => $this->service->copyPrevious(['month' => '2026-11'])))->toBeInstanceOf(ValidationException::class);
    expect(jshCatch(fn () => $this->service->copyPrevious(['production_line_id' => 'all', 'month' => '2026-11'])))->toBeInstanceOf(ValidationException::class);
    expect(jshCatch(fn () => $this->service->copyPrevious(['production_line_id' => $this->lineA1->id, 'month' => '2026-1'])))->toBeInstanceOf(ValidationException::class);
    expect(jshCatch(fn () => $this->service->copyPrevious(['production_line_id' => (string) Str::uuid(), 'month' => '2026-11'])))->toBeInstanceOf(ModelNotFoundException::class);
});

// ── #25 month length ─────────────────────────────────────────────────────

it('#25 panjang bulan: days = 28/29/30/31 dan total_cells = hari x shift berlaku', function (string $month, int $days, string $lastDate) {
    $grid = $this->service->grid($this->lineA1->id, $month);

    expect($grid['days'])->toHaveCount($days)
        ->and($grid['days'][$days - 1]['date'])->toBe($lastDate)
        ->and($grid['days'][0]['date'])->toBe($month.'-01')
        ->and($grid['summary']['total_cells'])->toBe($days * 2)   // Pagi + Siang
        ->and($grid['cells'])->toHaveCount($days * 2)
        ->and($grid['month'])->toBe($month);

    // No phantom day beyond the real last one.
    $dates = array_column($grid['days'], 'date');
    expect(in_array(date('Y-m-d', strtotime($lastDate.' +1 day')), $dates, true))->toBeFalse();
})->with([
    '2026-02 (28)' => ['2026-02', 28, '2026-02-28'],
    '2028-02 kabisat (29)' => ['2028-02', 29, '2028-02-29'],
    '2026-04 (30)' => ['2026-04', 30, '2026-04-30'],
    '2026-10 (31)' => ['2026-10', 31, '2026-10-31'],
]);

// ── #26 midnight-crossing shift ──────────────────────────────────────────

it('#26 shift lintas tengah malam dikunci pada TANGGAL MULAI: tak ada baris di tanggal berikutnya', function () {
    $malam = Shift::factory()->forProductionLine($this->lineA1)->named('Malam')->range('23:00:00', '07:00:00')->create();

    $this->service->saveCell(['shift_id' => $malam->id, 'date' => '2026-10-05', 'user_ids' => [$this->budi->id]]);

    expect(jshCell($malam->id, '2026-10-05'))->toBe([$this->budi->id])
        ->and(jshCell($malam->id, '2026-10-06'))->toBe([])
        ->and(DB::table('shift_assignments')->where('shift_id', $malam->id)->count())->toBe(1);

    $grid = $this->service->grid($this->lineA1->id, '2026-10');
    $column = collect($grid['shifts'])->firstWhere('id', $malam->id);

    expect($column['crosses_midnight'])->toBeTrue()
        ->and($column['label'])->toContain('+1')
        ->and($column['start_time'])->toBe('23:00')
        ->and($column['end_time'])->toBe('07:00')
        ->and(array_column(jshGridCell($grid, $malam->id, '2026-10-05')['assignees'], 'user_id'))->toBe([$this->budi->id])
        ->and(jshGridCell($grid, $malam->id, '2026-10-06')['assignees'])->toBe([]);

    // A non-crossing shift is not marked as crossing.
    expect(collect($grid['shifts'])->firstWhere('id', $this->pagi->id)['crosses_midnight'])->toBeFalse();
});

// ── #27 closed_from in the application timezone ──────────────────────────

it('#27 closed_from memakai zona waktu aplikasi: 15 Okt 01:30 WIB -> 2026-10-15 (= 14 Okt 18:30 UTC)', function () {
    expect(config('app.timezone'))->toBe('Asia/Jakarta');

    jshPlace($this->siang, $this->budi, '2026-10-14');
    jshPlace($this->siang, $this->citra, '2026-10-15');   // on closed_from: not emitted
    // 18:30 UTC on the 14th IS 01:30 WIB on the 15th — the same instant.
    $instant = Carbon::parse('2026-10-14 18:30:00', 'UTC')->setTimezone('Asia/Jakarta');
    expect($instant->toDateString())->toBe('2026-10-15');
    jshCloseShiftAt($this->siang, $instant->toDateTimeString());

    $grid = $this->service->grid($this->lineA1->id, '2026-10');
    $column = collect($grid['shifts'])->firstWhere('id', $this->siang->id);
    $cells = jshGridCells($grid, $this->siang->id);

    expect($column['closed'])->toBeTrue()
        ->and($column['closed_from'])->toBe('2026-10-15');

    $dates = array_column($cells, 'date');
    expect($cells)->toHaveCount(14)                          // 10-01 .. 10-14
        ->and(in_array('2026-10-14', $dates, true))->toBeTrue()
        ->and(in_array('2026-10-15', $dates, true))->toBeFalse()
        ->and(in_array('2026-10-31', $dates, true))->toBeFalse();
    foreach ($cells as $cell) {
        expect($cell['editable'])->toBeFalse();
    }

    // History before the closure is still visible; the live column stays editable.
    expect(array_column(jshGridCell($grid, $this->siang->id, '2026-10-14')['assignees'], 'user_id'))->toBe([$this->budi->id]);
    expect(jshGridCell($grid, $this->pagi->id, '2026-10-20')['editable'])->toBeTrue();

    // Summary counts only the cells that exist: 31 live Pagi + 14 closed Siang.
    expect($grid['summary']['total_cells'])->toBe(31 + 14)
        ->and($grid['summary']['filled_cells'])->toBe(1);
});

it('#27 closed_from: 14 Okt 23:59 WIB -> 2026-10-14 (bukan tanggal UTC, bukan hari berikutnya)', function () {
    jshCloseShiftAt($this->siang, '2026-10-14 23:59:00');

    $grid = $this->service->grid($this->lineA1->id, '2026-10');
    $column = collect($grid['shifts'])->firstWhere('id', $this->siang->id);
    $dates = array_column(jshGridCells($grid, $this->siang->id), 'date');

    expect($column['closed_from'])->toBe('2026-10-14')
        ->and($dates)->toHaveCount(13)
        ->and(in_array('2026-10-13', $dates, true))->toBeTrue()
        ->and(in_array('2026-10-14', $dates, true))->toBeFalse();
});

it('#27 shift ditutup 00:30 WIB tanggal 1 tidak lagi muncul di bulan itu, tetapi muncul penuh di bulan sebelumnya', function () {
    jshPlace($this->siang, $this->budi, '2026-09-20');
    jshCloseShiftAt($this->siang, '2026-10-01 00:30:00');

    $october = $this->service->grid($this->lineA1->id, '2026-10');
    expect(array_column($october['shifts'], 'id'))->toBe([$this->pagi->id]);

    $september = $this->service->grid($this->lineA1->id, '2026-09');
    $column = collect($september['shifts'])->firstWhere('id', $this->siang->id);

    expect($column['closed_from'])->toBe('2026-10-01')
        ->and(jshGridCells($september, $this->siang->id))->toHaveCount(30)
        ->and(array_column(jshGridCell($september, $this->siang->id, '2026-09-20')['assignees'], 'user_id'))->toBe([$this->budi->id]);
});

// ── #28 grid validation + no_live_shift ──────────────────────────────────

it('#28 grid: production_line_id wajib (absen, kosong, "all", bukan uuid) -> 422 pada production_line_id', function (?string $value) {
    $e = jshCatch(fn () => $this->service->grid($value, '2026-10'));

    expect($e)->toBeInstanceOf(ValidationException::class)
        ->and($e->errors())->toHaveKey('production_line_id');
})->with([
    'absen' => [null],
    'kosong' => [''],
    'all' => ['all'],
    'ALL' => ['ALL'],
    'bukan uuid' => ['pl-001'],
]);

it('#28 grid: bulan tidak valid -> 422 pada month', function () {
    $e = jshCatch(fn () => $this->service->grid($this->lineA1->id, '2026-13'));

    expect($e)->toBeInstanceOf(ValidationException::class)->and($e->errors())->toHaveKey('month');
});

it('#28 grid: line tanpa shift hidup -> state no_live_shift dengan shifts=[] dan cells=[] (bukan galat)', function () {
    $grid = $this->service->grid($this->lineA2->id, '2026-10');

    expect($grid['state'])->toBe('no_live_shift')
        ->and($grid['shifts'])->toBe([])
        ->and($grid['cells'])->toBe([])
        ->and($grid['days'])->toHaveCount(31)
        ->and($grid['summary']['text'])->toBe('0 dari 0 sel sudah terisi')
        ->and($grid['production_line']['id'])->toBe($this->lineA2->id);
});

it('#28 grid: line yang SATU-SATUNYA shift-nya ditutup pertengahan bulan juga no_live_shift', function () {
    $only = Shift::factory()->forProductionLine($this->lineA2)->named('Satu')->range('07:00:00', '15:00:00')->create();
    jshPlace($only, $this->budi, '2026-10-03');
    jshCloseShiftAt($only, '2026-10-15 08:00:00');

    $grid = $this->service->grid($this->lineA2->id, '2026-10');

    expect($grid['state'])->toBe('no_live_shift')->and($grid['shifts'])->toBe([])->and($grid['cells'])->toBe([]);
});

it('#28 grid: bulan default = bulan berjalan; is_today hanya pada satu hari; akhir pekan ditandai', function () {
    $grid = $this->service->grid($this->lineA1->id);   // clock frozen at 2026-10-12

    expect($grid['month'])->toBe('2026-10');

    $today = array_values(array_filter($grid['days'], fn ($d) => $d['is_today']));
    expect($today)->toHaveCount(1)->and($today[0]['date'])->toBe('2026-10-12');

    $byDate = collect($grid['days'])->keyBy('date');
    expect($byDate['2026-10-03']['is_weekend'])->toBeTrue()
        ->and($byDate['2026-10-03']['weekday_label'])->toBe('Sab')
        ->and($byDate['2026-10-04']['weekday_label'])->toBe('Min')
        ->and($byDate['2026-10-05']['is_weekend'])->toBeFalse()
        ->and($byDate['2026-10-05']['weekday_label'])->toBe('Sen');

    // Another month has no "today" at all.
    expect(array_filter($this->service->grid($this->lineA1->id, '2026-11')['days'], fn ($d) => $d['is_today']))->toBe([]);
});

it('#28 productionLineOptions: business_unit_id wajib, mill tanpa line -> data kosong, live_shift_count hanya shift hidup', function () {
    $e = jshCatch(fn () => $this->service->productionLineOptions(null));
    expect($e)->toBeInstanceOf(ValidationException::class)->and($e->errors())->toHaveKey('business_unit_id');
    expect(jshCatch(fn () => $this->service->productionLineOptions('')))->toBeInstanceOf(ValidationException::class);

    expect($this->service->productionLineOptions($this->millC->id))->toBe(['data' => []]);
    expect($this->service->productionLineOptions((string) Str::uuid()))->toBe(['data' => []]);
    expect($this->service->productionLineOptions('bu-001'))->toBe(['data' => []]);

    $closed = Shift::factory()->forProductionLine($this->lineA2)->named('Lama')->range('07:00:00', '15:00:00')->create();
    jshCloseShiftAt($closed, '2026-10-01 08:00:00');

    $options = collect($this->service->productionLineOptions($this->millA->id)['data'])->keyBy('name');
    expect($options->keys()->all())->toBe(['Line 1', 'Line 2'])
        ->and($options['Line 1']['live_shift_count'])->toBe(2)
        ->and($options['Line 2']['live_shift_count'])->toBe(0);   // the closed shift does not count
});

// ── #29 happy path ───────────────────────────────────────────────────────

it('#29 happy path: saveCell mengembalikan assignees, changed_by_others=false; ringkasan "N dari M" dihitung server', function () {
    $result = $this->service->saveCell([
        'shift_id' => $this->pagi->id, 'date' => '2026-10-05',
        'user_ids' => [$this->citra->id, $this->budi->id], 'loaded_user_ids' => [],
    ]);

    expect($result['shift_id'])->toBe($this->pagi->id)
        ->and($result['date'])->toBe('2026-10-05')
        ->and($result['changed_by_others'])->toBeFalse()
        ->and($result['previous_user_ids'])->toBe([])
        ->and(array_column($result['assignees'], 'name'))->toBe(['Budi Santoso', 'Citra']);   // by name

    $grid = $this->service->grid($this->lineA1->id, '2026-10');

    expect($grid['state'])->toBe('ready')
        ->and($grid['summary']['total_cells'])->toBe(62)
        ->and($grid['summary']['filled_cells'])->toBe(1)               // two people, ONE cell
        ->and($grid['summary']['empty_cells'])->toBe(61)
        ->and($grid['summary']['text'])->toBe('1 dari 62 sel sudah terisi')
        ->and($grid['summary']['filled_cells'] + $grid['summary']['empty_cells'])->toBe($grid['summary']['total_cells']);

    $cell = jshGridCell($grid, $this->pagi->id, '2026-10-05');
    expect($cell['editable'])->toBeTrue()
        ->and(array_column($cell['assignees'], 'name'))->toBe(['Budi Santoso', 'Citra'])
        ->and($cell['assignees'][0]['is_active'])->toBeTrue()
        ->and($cell['assignees'][0]['role_label'])->toBe(UserRole::Operator->label());
    expect(jshGridCell($grid, $this->pagi->id, '2026-10-06')['assignees'])->toBe([])
        ->and(jshGridCell($grid, $this->siang->id, '2026-10-05')['assignees'])->toBe([]);

    // Filling a second cell moves the text to 2, and clearing brings it back.
    $this->service->saveCell(['shift_id' => $this->siang->id, 'date' => '2026-10-05', 'user_ids' => [$this->budi->id]]);
    expect($this->service->grid($this->lineA1->id, '2026-10')['summary']['text'])->toBe('2 dari 62 sel sudah terisi');

    $this->service->saveCell(['shift_id' => $this->siang->id, 'date' => '2026-10-05', 'user_ids' => []]);
    $this->service->saveCell(['shift_id' => $this->pagi->id, 'date' => '2026-10-05', 'user_ids' => []]);
    expect($this->service->grid($this->lineA1->id, '2026-10')['summary']['text'])->toBe('0 dari 62 sel sudah terisi');
});

it('#29 grid menandai also_on_other_shift_today hanya untuk orang di >= 2 shift pada hari itu', function () {
    jshPlace($this->pagi, $this->budi, '2026-10-05');
    jshPlace($this->siang, $this->budi, '2026-10-05');
    jshPlace($this->pagi, $this->citra, '2026-10-05');

    $grid = $this->service->grid($this->lineA1->id, '2026-10');
    $pagiCell = collect(jshGridCell($grid, $this->pagi->id, '2026-10-05')['assignees'])->keyBy('user_id');
    $siangCell = collect(jshGridCell($grid, $this->siang->id, '2026-10-05')['assignees'])->keyBy('user_id');

    expect($pagiCell[$this->budi->id]['also_on_other_shift_today'])->toBeTrue()
        ->and($siangCell[$this->budi->id]['also_on_other_shift_today'])->toBeTrue()
        ->and($pagiCell[$this->citra->id]['also_on_other_shift_today'])->toBeFalse();
});

it('#29 grid memuat user nonaktif di sel dengan is_active=false (tetap tampil agar bisa dikeluarkan)', function () {
    $gone = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->millA)->create(['name' => 'Rina Pindah']);
    jshPlace($this->pagi, $gone, '2026-10-05');
    $gone->update(['is_active' => false]);

    $assignees = jshGridCell($this->service->grid($this->lineA1->id, '2026-10'), $this->pagi->id, '2026-10-05')['assignees'];

    expect($assignees)->toHaveCount(1)
        ->and($assignees[0]['user_id'])->toBe($gone->id)
        ->and($assignees[0]['is_active'])->toBeFalse();
});

it('#29 tanggal lampau dapat diubah (tanpa penguncian)', function () {
    // The frozen clock is 2026-10-12; both dates are well in the past.
    $this->service->saveCell(['shift_id' => $this->pagi->id, 'date' => '2026-10-01', 'user_ids' => [$this->budi->id]]);
    $this->service->saveCell(['shift_id' => $this->pagi->id, 'date' => '2020-01-15', 'user_ids' => [$this->citra->id]]);
    $this->service->saveCell(['shift_id' => $this->pagi->id, 'date' => '2026-10-01', 'user_ids' => [$this->citra->id]]);

    expect(jshCell($this->pagi->id, '2026-10-01'))->toBe([$this->citra->id])
        ->and(jshCell($this->pagi->id, '2020-01-15'))->toBe([$this->citra->id]);
});

it('#29 jam shift diubah: penugasan tetap pada shift yang sama dan label memakai jam terbaru', function () {
    jshPlace($this->pagi, $this->budi, '2026-10-05');
    $before = jshSnapshot();

    $this->pagi->update(['start_time' => '08:00:00', 'end_time' => '16:00:00']);

    $grid = $this->service->grid($this->lineA1->id, '2026-10');
    $column = collect($grid['shifts'])->firstWhere('id', $this->pagi->id);

    expect(jshSnapshot())->toBe($before)
        ->and($column['start_time'])->toBe('08:00')
        ->and($column['end_time'])->toBe('16:00')
        ->and($column['label'])->toContain('08:00')
        ->and(array_column(jshGridCell($grid, $this->pagi->id, '2026-10-05')['assignees'], 'user_id'))->toBe([$this->budi->id]);
});

it('#29 grid mengurutkan kolom shift pada sumbu jam (Pagi sebelum Siang sebelum Malam)', function () {
    Shift::factory()->forProductionLine($this->lineA1)->named('Malam')->range('23:00:00', '07:00:00')->create();

    $names = array_column($this->service->grid($this->lineA1->id, '2026-10')['shifts'], 'name');

    expect($names)->toBe(['Pagi', 'Siang', 'Malam']);
});

it('#29 saveCell memvalidasi bentuk masukan: tanggal mustahil, user_ids hilang, bukan uuid -> 422', function () {
    $cases = [
        'tanggal mustahil' => ['shift_id' => $this->pagi->id, 'date' => '2026-02-30', 'user_ids' => []],
        'tanggal bukan Y-m-d' => ['shift_id' => $this->pagi->id, 'date' => '05-10-2026', 'user_ids' => []],
        'user_ids hilang' => ['shift_id' => $this->pagi->id, 'date' => '2026-10-05'],
        'user_ids bukan uuid' => ['shift_id' => $this->pagi->id, 'date' => '2026-10-05', 'user_ids' => ['abc']],
        'shift_id bukan uuid' => ['shift_id' => 'shift-1', 'date' => '2026-10-05', 'user_ids' => []],
        'shift_id hilang' => ['date' => '2026-10-05', 'user_ids' => []],
    ];

    foreach ($cases as $label => $payload) {
        expect(jshCatch(fn () => $this->service->saveCell($payload)))->toBeInstanceOf(ValidationException::class, $label);
    }
    expect(jshCount())->toBe(0);
});

// ── migration / schema guarantees (#9 of the brief) ──────────────────────

it('migrasi: unique (shift_id, user_id, work_date) menolak duplikat; user sama di tanggal/shift lain boleh', function () {
    jshPlace($this->pagi, $this->budi, '2026-10-05');

    expect(fn () => ShiftAssignment::query()->create([
        'shift_id' => $this->pagi->id, 'user_id' => $this->budi->id, 'work_date' => '2026-10-05', 'created_by' => $this->admin->id,
    ]))->toThrow(Illuminate\Database\QueryException::class);

    expect(jshCount())->toBe(1);

    // Different date, shift, or user is not a duplicate.
    jshPlace($this->pagi, $this->budi, '2026-10-06');
    jshPlace($this->siang, $this->budi, '2026-10-05');
    jshPlace($this->pagi, $this->citra, '2026-10-05');
    expect(jshCount())->toBe(4);
});

it('migrasi: baris penugasan tetap dapat menunjuk shift yang sudah di-soft-delete (FK tanpa cascade)', function () {
    $row = jshPlace($this->siang, $this->budi, '2026-10-05');

    jshCloseShiftAt($this->siang, '2026-10-10 08:00:00');

    expect(Shift::query()->whereKey($this->siang->id)->exists())->toBeFalse()   // gone from the live scope
        ->and(Shift::withTrashed()->whereKey($this->siang->id)->exists())->toBeTrue()
        ->and(ShiftAssignment::query()->whereKey($row->id)->exists())->toBeTrue()
        ->and($row->fresh()->shift->id)->toBe($this->siang->id);               // relation resolves through withTrashed

    // And a NEW row can still be inserted against the closed shift at the table level (history import).
    DB::table('shift_assignments')->insert([
        'id' => (string) Str::uuid(), 'shift_id' => $this->siang->id, 'user_id' => $this->citra->id,
        'work_date' => '2026-10-06', 'created_by' => $this->admin->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    expect(jshCell($this->siang->id, '2026-10-06'))->toBe([$this->citra->id]);
});
