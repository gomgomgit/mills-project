<?php

/**
 * ShiftScheduleApiTest — screen-171--jadwal-shift / usecase-177--jadwal-shift
 * (Jadwal Shift). Integration tests for the screen's SEVEN endpoints
 * (App\Http\Controllers\Api\ShiftScheduleController), through the real
 * route -> EnsureRole -> controller -> ShiftScheduleService -> Eloquent chain
 * on the sqlite testing DB. Same style as tests/Feature/Api/KelolaShiftTest.php.
 *
 *   GET  /api/shift-schedules/business-units/options
 *   GET  /api/shift-schedules/production-lines/options
 *   GET  /api/shift-schedules/candidates
 *   PUT  /api/shift-schedules/cell            -> 200 (NOT 201)
 *   POST /api/shift-schedules/apply           -> 200
 *   POST /api/shift-schedules/copy-previous   -> 200
 *   GET  /api/shift-schedules
 *
 * One test per `test_scenarios` entry (27, named by scenario_ref), each
 * executing that scenario's `api_test` steps IN ORDER and asserting every
 * step's expected_status and expected_error_code. Placeholders in the spec
 * ("<uuid-line>") are replaced with real factory ids, and `{{stepN.field}}`
 * references are resolved from the earlier response.
 *
 * ── ERROR CODE ASSERTIONS ───────────────────────────────────────────────
 *
 * ApiExceptionHandler emits `code` for VALIDATION_ERROR / NOT_FOUND /
 * FORBIDDEN (raised by the SERVICE via abort(403)) and for the three
 * HasErrorCode exceptions (SHIFT_CLOSED, NOT_A_CANDIDATE,
 * CONFIRMATION_REQUIRED). A role rejection by the ROUTE middleware
 * (EnsureRole, e.g. operator) answers `message` only — no `code` — because
 * that middleware builds its own JSON; the operator scenario therefore
 * asserts status + message, and asserts the absence of `code` KEY (not prose).
 *
 * ── DATES ───────────────────────────────────────────────────────────────
 *
 * Clock frozen at 2026-10-12 10:00 Asia/Jakarta; every month/date is sent
 * explicitly; work_date compared as 'YYYY-MM-DD' strings. 2026-10-01 is a
 * Thursday.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\ProductionLine;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

dataset('jadwal api admin equivalents', fn () => array_map(fn (UserRole $r) => [$r], UserRole::adminEquivalents()));

dataset('jadwal api mill-scoped roles', [
    'supervisor' => [UserRole::Supervisor],
    'mill_management' => [UserRole::MillManagement],
]);

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-12 10:00:00', 'Asia/Jakarta'));

    $this->millA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->millB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);
    $this->millKosong = BusinessUnit::factory()->create(['name' => 'Mill Kosong']);

    $this->lineA1 = ProductionLine::factory()->forBusinessUnit($this->millA)->create(['name' => 'Line 1']);
    $this->lineA2 = ProductionLine::factory()->forBusinessUnit($this->millA)->create(['name' => 'Line 2']);   // no shifts
    $this->lineB1 = ProductionLine::factory()->forBusinessUnit($this->millB)->create(['name' => 'Line B1']);

    $this->pagi = Shift::factory()->forProductionLine($this->lineA1)->named('Pagi')->range('07:00:00', '15:00:00')->create();
    $this->siang = Shift::factory()->forProductionLine($this->lineA1)->named('Siang')->range('15:00:00', '23:00:00')->create();
    $this->shiftB = Shift::factory()->forProductionLine($this->lineB1)->named('Pagi B')->range('07:00:00', '15:00:00')->create();

    $this->admin = User::factory()->role(UserRole::Admin)->create(['name' => 'Admin X', 'business_unit_id' => null]);
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->millA)->create(['name' => 'Sup Alpha']);
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->millA)->create(['name' => 'MM Alpha']);
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->millA)->create(['name' => 'Operator Gate']);

    $this->budi = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->millA)->create(['name' => 'Budi Santoso']);
    $this->citra = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->millA)->create(['name' => 'Citra']);
    $this->dewi = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->millA)->create(['name' => 'Dewi Penyelia']);
    $this->bayu = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->millB)->create(['name' => 'Bayu Beta']);
});

afterEach(function () {
    Carbon::setTestNow();
});

// ── helpers (prefix jsa: Pest files share one global function namespace) ──

function jsaCell(string $shiftId, string $date): array
{
    $ids = DB::table('shift_assignments')->where('shift_id', $shiftId)->where('work_date', $date)->pluck('user_id')->all();
    sort($ids);

    return $ids;
}

function jsaSorted(array $ids): array
{
    sort($ids);

    return array_values($ids);
}

function jsaSnapshot(): array
{
    $rows = DB::table('shift_assignments')->get(['shift_id', 'user_id', 'work_date'])
        ->map(fn ($r) => $r->shift_id.'|'.$r->user_id.'|'.substr((string) $r->work_date, 0, 10))->all();
    sort($rows);

    return $rows;
}

function jsaCount(): int
{
    return DB::table('shift_assignments')->count();
}

function jsaPlace(Shift $shift, User $user, string $date): void
{
    ShiftAssignment::factory()->create([
        'shift_id' => $shift->id, 'user_id' => $user->id, 'work_date' => $date, 'created_by' => $user->id,
    ]);
}

function jsaClose(Shift $shift, string $at): void
{
    $previous = Carbon::getTestNow();
    Carbon::setTestNow(Carbon::parse($at, 'Asia/Jakarta'));
    $shift->delete();
    Carbon::setTestNow($previous);
}

/** The grid cell for shift x date out of a GET /api/shift-schedules `data` block. */
function jsaGridCell(array $data, string $shiftId, string $date): ?array
{
    foreach ($data['cells'] as $cell) {
        if ($cell['shift_id'] === $shiftId && $cell['date'] === $date) {
            return $cell;
        }
    }

    return null;
}

// ── Scenario: success ────────────────────────────────────────────────────

it('Jadwal Shift — success: candidates -> PUT cell -> grid', function () {
    $this->actingAs($this->admin, 'web');

    // Step 1
    $candidates = $this->getJson("/api/shift-schedules/candidates?production_line_id={$this->lineA1->id}&date=2026-10-05");
    $candidates->assertOk();
    $first = $candidates->json('data.0.user_id');   // {{step1.data[0].user_id}}
    expect($first)->toBe($this->budi->id)           // ordered by name: Budi Santoso < Citra < Dewi Penyelia ...
        ->and($candidates->json('empty_reason'))->toBeNull();

    // Step 2
    $put = $this->putJson('/api/shift-schedules/cell', ['shift_id' => $this->pagi->id, 'date' => '2026-10-05', 'user_ids' => [$first]]);
    $put->assertOk()   // 200, NOT 201
        ->assertJsonPath('data.shift_id', $this->pagi->id)
        ->assertJsonPath('data.date', '2026-10-05')
        ->assertJsonPath('data.changed_by_others', false)
        ->assertJsonPath('data.assignees.0.user_id', $first)
        ->assertJsonPath('data.assignees.0.name', 'Budi Santoso');

    // Step 3
    $grid = $this->getJson("/api/shift-schedules?production_line_id={$this->lineA1->id}&month=2026-10");
    $grid->assertOk()
        ->assertJsonPath('data.state', 'ready')
        ->assertJsonPath('data.month', '2026-10')
        ->assertJsonPath('data.summary.text', '1 dari 62 sel sudah terisi')
        ->assertJsonPath('data.summary.filled_cells', 1)
        ->assertJsonPath('data.summary.empty_cells', 61);

    $filled = jsaGridCell($grid->json('data'), $this->pagi->id, '2026-10-05');
    $other = jsaGridCell($grid->json('data'), $this->pagi->id, '2026-10-06');
    expect(array_column($filled['assignees'], 'user_id'))->toBe([$first])
        ->and($other['assignees'])->toBe([])
        ->and(jsaCell($this->pagi->id, '2026-10-05'))->toBe([$first]);
});

// ── Scenario: Mill belum dipilih ─────────────────────────────────────────

it('Jadwal Shift — Mill belum dipilih: options 200, production-lines/options tanpa business_unit_id 422 VALIDATION_ERROR', function () {
    $this->actingAs($this->admin, 'web');

    $mills = $this->getJson('/api/shift-schedules/business-units/options');
    $mills->assertOk()->assertJsonPath('fixed', false);
    expect(array_column($mills->json('data'), 'id'))->toEqualCanonicalizing(BusinessUnit::query()->pluck('id')->all());

    $lines = $this->getJson('/api/shift-schedules/production-lines/options');
    $lines->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR')->assertJsonValidationErrors('business_unit_id');

    $this->getJson('/api/shift-schedules/production-lines/options?business_unit_id=')
        ->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
});

// ── Scenario: Production Line belum dipilih ──────────────────────────────

it('Jadwal Shift — Production Line belum dipilih: GET grid tanpa line 422 VALIDATION_ERROR', function () {
    $this->actingAs($this->admin, 'web');

    $this->getJson('/api/shift-schedules?month=2026-10')
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR')
        ->assertJsonValidationErrors('production_line_id');

    expect(jsaCount())->toBe(0);
});

// ── Scenario: Mill belum punya Production Line ───────────────────────────

it('Jadwal Shift — Mill belum punya Production Line: options 200 dengan data kosong', function () {
    $this->actingAs($this->admin, 'web');

    $this->getJson("/api/shift-schedules/production-lines/options?business_unit_id={$this->millKosong->id}")
        ->assertOk()->assertExactJson(['data' => []]);

    // And a mill WITH lines lists them, with the live-shift count.
    $withLines = $this->getJson("/api/shift-schedules/production-lines/options?business_unit_id={$this->millA->id}");
    $withLines->assertOk();
    $byName = collect($withLines->json('data'))->keyBy('name');
    expect($byName->keys()->all())->toBe(['Line 1', 'Line 2'])
        ->and($byName['Line 1']['live_shift_count'])->toBe(2)
        ->and($byName['Line 2']['live_shift_count'])->toBe(0);
});

// ── Scenario: Line belum punya shift hidup ───────────────────────────────

it('Jadwal Shift — Line belum punya shift hidup: 200 state no_live_shift, shifts dan cells kosong', function () {
    $this->actingAs($this->admin, 'web');

    $this->getJson("/api/shift-schedules?production_line_id={$this->lineA2->id}&month=2026-10")
        ->assertOk()
        ->assertJsonPath('data.state', 'no_live_shift')
        ->assertJsonPath('data.shifts', [])
        ->assertJsonPath('data.cells', [])
        ->assertJsonPath('data.summary.text', '0 dari 0 sel sudah terisi')
        ->assertJsonCount(31, 'data.days');
});

// ── Scenario: Supervisor atau Mill Management mencoba mill lain ──────────

it('Jadwal Shift — Supervisor atau Mill Management mencoba mill lain: options 200 fixed, mill/line lain 403 FORBIDDEN', function (UserRole $role) {
    $actor = User::factory()->role($role)->forBusinessUnit($this->millA)->create();
    $this->actingAs($actor, 'web');
    jsaPlace($this->shiftB, $this->bayu, '2026-10-05');
    $before = jsaSnapshot();

    // Step 1
    $options = $this->getJson('/api/shift-schedules/business-units/options');
    $options->assertOk()->assertJsonPath('fixed', true)->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->millA->id);

    // Step 2 / 3
    $this->getJson("/api/shift-schedules/production-lines/options?business_unit_id={$this->millB->id}")
        ->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
    $this->getJson("/api/shift-schedules?production_line_id={$this->lineB1->id}")
        ->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');

    // Writes and the other read/aggregate endpoints are scoped the same way.
    $this->getJson("/api/shift-schedules/candidates?production_line_id={$this->lineB1->id}")->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
    $this->putJson('/api/shift-schedules/cell', ['shift_id' => $this->shiftB->id, 'date' => '2026-10-06', 'user_ids' => [$this->bayu->id]])
        ->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
    $this->postJson('/api/shift-schedules/apply', ['shift_id' => $this->shiftB->id, 'month' => '2026-10', 'dates' => ['2026-10-06'], 'user_ids' => []])
        ->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
    $this->postJson('/api/shift-schedules/copy-previous', ['production_line_id' => $this->lineB1->id, 'month' => '2026-10'])
        ->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');

    expect(jsaSnapshot())->toBe($before);

    // Own mill works for the same account.
    $this->getJson("/api/shift-schedules?production_line_id={$this->lineA1->id}&month=2026-10")->assertOk();
})->with('jadwal api mill-scoped roles');

// ── Scenario: Operator mencoba membuka layar ─────────────────────────────

it('Jadwal Shift — Operator mencoba membuka layar: 403 pada setiap endpoint, tanpa penulisan', function () {
    $this->actingAs($this->operator, 'web');

    $responses = [
        $this->getJson('/api/shift-schedules/business-units/options'),
        $this->getJson("/api/shift-schedules/production-lines/options?business_unit_id={$this->millA->id}"),
        $this->getJson("/api/shift-schedules/candidates?production_line_id={$this->lineA1->id}"),
        $this->putJson('/api/shift-schedules/cell', ['shift_id' => $this->pagi->id, 'date' => '2026-10-05', 'user_ids' => [$this->budi->id]]),
        $this->postJson('/api/shift-schedules/apply', ['shift_id' => $this->pagi->id, 'month' => '2026-10', 'dates' => ['2026-10-05'], 'user_ids' => []]),
        $this->postJson('/api/shift-schedules/copy-previous', ['production_line_id' => $this->lineA1->id, 'month' => '2026-10']),
        $this->getJson("/api/shift-schedules?production_line_id={$this->lineA1->id}&month=2026-10"),
    ];

    foreach ($responses as $response) {
        $response->assertForbidden()->assertJsonPath('message', 'Anda tidak memiliki akses untuk aksi ini.');
        // The route middleware answers first and carries `message` only: assert the KEY, not prose.
        expect(array_key_exists('code', $response->json()))->toBeFalse();
        expect(array_key_exists('data', $response->json()))->toBeFalse();
    }

    expect(jsaCount())->toBe(0);
});

it('Jadwal Shift — tanpa login: 401 pada endpoint', function () {
    $this->getJson('/api/shift-schedules/business-units/options')->assertUnauthorized();
    $this->putJson('/api/shift-schedules/cell', [])->assertUnauthorized();
    $this->getJson("/api/shift-schedules?production_line_id={$this->lineA1->id}")->assertUnauthorized();
});

it('Jadwal Shift — tujuh role setara Admin lolos gate rute sebagai admin dan membaca semua mill', function (UserRole $role) {
    $actor = User::factory()->role($role)->forBusinessUnit($this->millA)->create();
    expect($actor->getRawOriginal('role'))->toBe($role->value);

    $this->actingAs($actor, 'web');

    $this->getJson('/api/shift-schedules/business-units/options')->assertOk()->assertJsonPath('fixed', false);
    // A mill that is not the account's own.
    $this->getJson("/api/shift-schedules?production_line_id={$this->lineB1->id}&month=2026-10")->assertOk()->assertJsonPath('data.state', 'ready');
    $this->putJson('/api/shift-schedules/cell', ['shift_id' => $this->shiftB->id, 'date' => '2026-10-05', 'user_ids' => [$this->bayu->id]])->assertOk();
    expect(jsaCell($this->shiftB->id, '2026-10-05'))->toBe([$this->bayu->id]);
})->with('jadwal api admin equivalents');

// ── Scenario: Satu orang di beberapa shift pada hari yang sama ───────────

it('Jadwal Shift — Satu orang di beberapa shift pada hari yang sama: dua PUT 200, candidates menandai shift lain', function () {
    $this->actingAs($this->admin, 'web');

    $this->putJson('/api/shift-schedules/cell', ['shift_id' => $this->pagi->id, 'date' => '2026-10-05', 'user_ids' => [$this->budi->id]])->assertOk();
    $this->putJson('/api/shift-schedules/cell', ['shift_id' => $this->siang->id, 'date' => '2026-10-05', 'user_ids' => [$this->budi->id]])->assertOk();

    $candidates = $this->getJson("/api/shift-schedules/candidates?production_line_id={$this->lineA1->id}&date=2026-10-05");
    $candidates->assertOk();
    $budi = collect($candidates->json('data'))->firstWhere('user_id', $this->budi->id);

    expect(array_column($budi['already_on_shifts'], 'shift_id'))->toEqualCanonicalizing([$this->pagi->id, $this->siang->id])
        ->and(jsaCell($this->pagi->id, '2026-10-05'))->toBe([$this->budi->id])
        ->and(jsaCell($this->siang->id, '2026-10-05'))->toBe([$this->budi->id])
        ->and(jsaCount())->toBe(2);

    $grid = $this->getJson("/api/shift-schedules?production_line_id={$this->lineA1->id}&month=2026-10")->json('data');
    expect(jsaGridCell($grid, $this->pagi->id, '2026-10-05')['assignees'][0]['also_on_other_shift_today'])->toBeTrue();
});

// ── Scenario: Orang yang sama dipilih dua kali pada sel yang sama ────────

it('Jadwal Shift — Orang yang sama dipilih dua kali pada sel yang sama: tepat 1 baris, 200 keduanya', function () {
    $this->actingAs($this->admin, 'web');

    $this->putJson('/api/shift-schedules/cell', ['shift_id' => $this->pagi->id, 'date' => '2026-10-05', 'user_ids' => [$this->budi->id]])->assertOk();
    $second = $this->putJson('/api/shift-schedules/cell', [
        'shift_id' => $this->pagi->id, 'date' => '2026-10-05',
        'user_ids' => [$this->budi->id, $this->budi->id], 'loaded_user_ids' => [$this->budi->id],
    ]);

    $second->assertOk()->assertJsonPath('data.changed_by_others', false)->assertJsonCount(1, 'data.assignees');
    expect(jsaCount())->toBe(1)
        ->and(jsaCell($this->pagi->id, '2026-10-05'))->toBe([$this->budi->id]);
});

// ── Scenario: Shift dibiarkan tanpa petugas ──────────────────────────────

it('Jadwal Shift — Shift dibiarkan tanpa petugas: PUT daftar kosong 200 dan sel dihitung kosong', function () {
    $this->actingAs($this->admin, 'web');
    jsaPlace($this->pagi, $this->budi, '2026-10-05');
    jsaPlace($this->pagi, $this->citra, '2026-10-05');
    jsaPlace($this->pagi, $this->budi, '2026-10-06');   // other cell, must survive

    $this->putJson('/api/shift-schedules/cell', ['shift_id' => $this->pagi->id, 'date' => '2026-10-05', 'user_ids' => []])
        ->assertOk()->assertJsonPath('data.assignees', []);

    $grid = $this->getJson("/api/shift-schedules?production_line_id={$this->lineA1->id}&month=2026-10");
    $grid->assertOk()->assertJsonPath('data.summary.text', '1 dari 62 sel sudah terisi');

    expect(jsaGridCell($grid->json('data'), $this->pagi->id, '2026-10-05')['assignees'])->toBe([])
        ->and(jsaCount())->toBe(1)
        ->and(jsaCell($this->pagi->id, '2026-10-06'))->toBe([$this->budi->id]);
});

it('Jadwal Shift — PUT tanpa kunci user_ids ditolak 422 (kosong harus dikirim eksplisit)', function () {
    $this->actingAs($this->admin, 'web');
    jsaPlace($this->pagi, $this->budi, '2026-10-05');

    $this->putJson('/api/shift-schedules/cell', ['shift_id' => $this->pagi->id, 'date' => '2026-10-05'])
        ->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR')->assertJsonValidationErrors('user_ids');

    expect(jsaCell($this->pagi->id, '2026-10-05'))->toBe([$this->budi->id]);
});

// ── Scenario: Mill tanpa calon peserta ───────────────────────────────────

it('Jadwal Shift — Mill tanpa calon peserta: 200 data kosong dengan empty_reason no_candidates', function () {
    $this->actingAs($this->admin, 'web');
    $lineKosong = ProductionLine::factory()->forBusinessUnit($this->millKosong)->create(['name' => 'Line K']);
    User::factory()->role(UserRole::Operator)->forBusinessUnit($this->millKosong)->inactive()->create();

    $this->getJson("/api/shift-schedules/candidates?production_line_id={$lineKosong->id}")
        ->assertOk()->assertExactJson(['data' => [], 'empty_reason' => 'no_candidates']);

    // A search that finds nothing in a mill WITH candidates is NOT no_candidates.
    $this->getJson("/api/shift-schedules/candidates?production_line_id={$this->lineA1->id}&q=zzzz")
        ->assertOk()->assertExactJson(['data' => [], 'empty_reason' => null]);
});

// ── Scenario: Role selain Operator dan Supervisor tidak menjadi calon ────

it('Jadwal Shift — Role selain Operator dan Supervisor tidak menjadi calon peserta', function () {
    $this->actingAs($this->admin, 'web');
    $engineer = User::factory()->role(UserRole::Engineer)->forBusinessUnit($this->millA)->create(['name' => 'Eko Engineer']);
    $mm = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->millA)->create(['name' => 'Mira MM']);
    $adm = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->millA)->create(['name' => 'Adi Admin']);

    $ids = array_column($this->getJson("/api/shift-schedules/candidates?production_line_id={$this->lineA1->id}")->assertOk()->json('data'), 'user_id');

    // Expected set from the RAW role column (the engineer is effectively Admin).
    $expected = User::query()->where('business_unit_id', $this->millA->id)->where('is_active', true)
        ->whereIn('role', ['operator', 'supervisor'])->pluck('id')->all();

    expect($ids)->toEqualCanonicalizing($expected)
        ->and($ids)->toContain($this->budi->id)
        ->and($ids)->toContain($this->dewi->id)
        ->and(in_array($engineer->id, $ids, true))->toBeFalse()
        ->and(in_array($mm->id, $ids, true))->toBeFalse()
        ->and(in_array($adm->id, $ids, true))->toBeFalse();
});

// ── Scenario: Shift lintas tengah malam ──────────────────────────────────

it('Jadwal Shift — Shift lintas tengah malam: baris di tanggal mulai, tidak di tanggal berikutnya, header memuat +1', function () {
    $this->actingAs($this->admin, 'web');
    $malam = Shift::factory()->forProductionLine($this->lineA1)->named('Malam')->range('23:00:00', '07:00:00')->create();

    $this->putJson('/api/shift-schedules/cell', ['shift_id' => $malam->id, 'date' => '2026-10-05', 'user_ids' => [$this->budi->id]])->assertOk();

    $grid = $this->getJson("/api/shift-schedules?production_line_id={$this->lineA1->id}&month=2026-10");
    $grid->assertOk();
    $data = $grid->json('data');
    $column = collect($data['shifts'])->firstWhere('id', $malam->id);

    expect($column['crosses_midnight'])->toBeTrue()
        ->and($column['label'])->toContain('+1')
        ->and(array_column(jsaGridCell($data, $malam->id, '2026-10-05')['assignees'], 'user_id'))->toBe([$this->budi->id])
        ->and(jsaGridCell($data, $malam->id, '2026-10-06')['assignees'])->toBe([])
        ->and(jsaCell($malam->id, '2026-10-06'))->toBe([]);
});

// ── Scenario: Shift dihapus pertengahan bulan ────────────────────────────

it('Jadwal Shift — Shift dihapus pertengahan bulan: closed=true, closed_from, sel sebelum read-only, sesudahnya tidak dikirim', function () {
    $this->actingAs($this->admin, 'web');
    jsaPlace($this->siang, $this->budi, '2026-10-14');
    jsaClose($this->siang, '2026-10-15 01:30:00');   // 01:30 WIB on the 15th

    $grid = $this->getJson("/api/shift-schedules?production_line_id={$this->lineA1->id}&month=2026-10");
    $grid->assertOk()->assertJsonPath('data.state', 'ready');
    $data = $grid->json('data');
    $column = collect($data['shifts'])->firstWhere('id', $this->siang->id);

    expect($column['closed'])->toBeTrue()
        ->and($column['closed_from'])->toBe('2026-10-15')
        ->and(collect($data['shifts'])->firstWhere('id', $this->pagi->id)['closed'])->toBeFalse();

    $closedCells = array_values(array_filter($data['cells'], fn ($c) => $c['shift_id'] === $this->siang->id));
    expect($closedCells)->toHaveCount(14);
    foreach ($closedCells as $cell) {
        expect($cell['editable'])->toBeFalse()->and($cell['date'] < '2026-10-15')->toBeTrue();
    }
    expect(jsaGridCell($data, $this->siang->id, '2026-10-15'))->toBeNull()
        ->and(array_column(jsaGridCell($data, $this->siang->id, '2026-10-14')['assignees'], 'user_id'))->toBe([$this->budi->id])
        ->and(jsaGridCell($data, $this->pagi->id, '2026-10-20')['editable'])->toBeTrue();
});

// ── Scenario: Jam shift diubah setelah bulan diisi ───────────────────────

it('Jadwal Shift — Jam shift diubah setelah bulan diisi: petugas tetap pada shift yang sama dengan jam terbaru', function () {
    $this->actingAs($this->admin, 'web');
    jsaPlace($this->pagi, $this->budi, '2026-10-05');
    $before = jsaSnapshot();

    $this->pagi->update(['start_time' => '08:00:00', 'end_time' => '16:00:00']);

    $grid = $this->getJson("/api/shift-schedules?production_line_id={$this->lineA1->id}&month=2026-10");
    $grid->assertOk();
    $column = collect($grid->json('data.shifts'))->firstWhere('id', $this->pagi->id);

    expect($column['start_time'])->toBe('08:00')
        ->and($column['end_time'])->toBe('16:00')
        ->and(array_column(jsaGridCell($grid->json('data'), $this->pagi->id, '2026-10-05')['assignees'], 'user_id'))->toBe([$this->budi->id])
        ->and(jsaSnapshot())->toBe($before);
});

// ── Scenario: User dinonaktifkan setelah ditugaskan ──────────────────────

it('Jadwal Shift — User dinonaktifkan setelah ditugaskan: tampil nonaktif, tak bisa ditambah ke sel lain, boleh dikeluarkan', function () {
    $this->actingAs($this->admin, 'web');
    $gone = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->millA)->create(['name' => 'Rina Pindah']);
    jsaPlace($this->pagi, $gone, '2026-10-05');
    $gone->update(['is_active' => false]);

    // Step 1
    $grid = $this->getJson("/api/shift-schedules?production_line_id={$this->lineA1->id}&month=2026-10");
    $grid->assertOk();
    $assignee = jsaGridCell($grid->json('data'), $this->pagi->id, '2026-10-05')['assignees'][0];
    expect($assignee['user_id'])->toBe($gone->id)->and($assignee['is_active'])->toBeFalse();

    // Step 2
    $this->putJson('/api/shift-schedules/cell', ['shift_id' => $this->pagi->id, 'date' => '2026-10-06', 'user_ids' => [$gone->id]])
        ->assertStatus(422)->assertJsonPath('code', 'NOT_A_CANDIDATE');
    expect(jsaCell($this->pagi->id, '2026-10-06'))->toBe([]);

    // And not offered as a candidate.
    $ids = array_column($this->getJson("/api/shift-schedules/candidates?production_line_id={$this->lineA1->id}")->json('data'), 'user_id');
    expect(in_array($gone->id, $ids, true))->toBeFalse();

    // Step 3
    $this->putJson('/api/shift-schedules/cell', ['shift_id' => $this->pagi->id, 'date' => '2026-10-05', 'user_ids' => [], 'loaded_user_ids' => [$gone->id]])
        ->assertOk()->assertJsonPath('data.assignees', []);
    expect(jsaCell($this->pagi->id, '2026-10-05'))->toBe([]);
});

// ── Scenario: Terapkan ke banyak tanggal ─────────────────────────────────

it('Jadwal Shift — Terapkan ke banyak tanggal: dry_run 200, tanpa konfirmasi 409, dengan konfirmasi 200', function () {
    $this->actingAs($this->admin, 'web');
    jsaPlace($this->pagi, $this->citra, '2026-10-01');   // one of the two in-month targets is already filled
    $before = jsaSnapshot();

    $body = [
        'shift_id' => $this->pagi->id, 'month' => '2026-10',
        'dates' => ['2026-10-01', '2026-10-02', '2026-11-01'],   // 11-01 is outside the month
        'user_ids' => [$this->budi->id],
    ];

    // Step 1: dry_run
    $this->postJson('/api/shift-schedules/apply', $body + ['dry_run' => true])
        ->assertOk()
        ->assertJsonPath('data.target_cells', 2)
        ->assertJsonPath('data.cells_already_filled', 1)
        ->assertJsonPath('data.people_affected', 1)
        ->assertJsonPath('data.outside_month_ignored', 1)
        ->assertJsonPath('data.applied', false)
        ->assertJsonPath('data.written_cells', 0);
    expect(jsaSnapshot())->toBe($before);

    // Step 2: overwrite without confirmation
    $this->postJson('/api/shift-schedules/apply', $body + ['dry_run' => false])
        ->assertStatus(409)->assertJsonPath('code', 'CONFIRMATION_REQUIRED');
    expect(jsaSnapshot())->toBe($before);

    // Step 3: confirmed
    $this->postJson('/api/shift-schedules/apply', $body + ['dry_run' => false, 'confirm_overwrite' => true])
        ->assertOk()
        ->assertJsonPath('data.applied', true)
        ->assertJsonPath('data.written_cells', 2)
        ->assertJsonPath('data.outside_month_ignored', 1);

    expect(jsaCell($this->pagi->id, '2026-10-01'))->toBe([$this->budi->id])
        ->and(jsaCell($this->pagi->id, '2026-10-02'))->toBe([$this->budi->id])
        ->and(jsaCell($this->pagi->id, '2026-11-01'))->toBe([])
        ->and(jsaCount())->toBe(2);
});

it('Jadwal Shift — Terapkan: dates dan range sekaligus / tidak ada sama sekali / range terbalik -> 422 VALIDATION_ERROR', function () {
    $this->actingAs($this->admin, 'web');
    $base = ['shift_id' => $this->pagi->id, 'month' => '2026-10', 'user_ids' => [$this->budi->id]];

    foreach ([
        'dates+range' => ['dates' => ['2026-10-01'], 'range' => ['from' => '2026-10-01', 'to' => '2026-10-02']],
        'tidak ada' => [],
        'range terbalik' => ['range' => ['from' => '2026-10-09', 'to' => '2026-10-01']],
    ] as $label => $extra) {
        $this->postJson('/api/shift-schedules/apply', $base + $extra)
            ->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    // Range with weekday filter: Mondays of 01..14 Oct are the 5th and 12th.
    $this->postJson('/api/shift-schedules/apply', $base + ['range' => ['from' => '2026-10-01', 'to' => '2026-10-14', 'weekdays' => [1]]])
        ->assertOk()->assertJsonPath('data.target_cells', 2);
    expect(jsaCell($this->pagi->id, '2026-10-05'))->toBe([$this->budi->id])
        ->and(jsaCell($this->pagi->id, '2026-10-12'))->toBe([$this->budi->id])
        ->and(jsaCount())->toBe(2);
});

// ── Scenario: Terapkan dengan daftar orang kosong ────────────────────────

it('Jadwal Shift — Terapkan dengan daftar orang kosong: dry_run 200, tanpa konfirmasi 409, dikonfirmasi mengosongkan', function () {
    $this->actingAs($this->admin, 'web');
    jsaPlace($this->pagi, $this->budi, '2026-10-05');
    jsaPlace($this->pagi, $this->citra, '2026-10-06');
    jsaPlace($this->pagi, $this->citra, '2026-10-07');   // not a target

    $body = ['shift_id' => $this->pagi->id, 'month' => '2026-10', 'dates' => ['2026-10-05', '2026-10-06'], 'user_ids' => []];

    $this->postJson('/api/shift-schedules/apply', $body + ['dry_run' => true])
        ->assertOk()->assertJsonPath('data.cells_already_filled', 2)->assertJsonPath('data.people_affected', 2);
    expect(jsaCount())->toBe(3);

    $this->postJson('/api/shift-schedules/apply', $body + ['dry_run' => false])
        ->assertStatus(409)->assertJsonPath('code', 'CONFIRMATION_REQUIRED');
    expect(jsaCount())->toBe(3);

    $this->postJson('/api/shift-schedules/apply', $body + ['dry_run' => false, 'confirm_overwrite' => true])
        ->assertOk()->assertJsonPath('data.applied', true)->assertJsonPath('data.written_cells', 2);

    expect(jsaCell($this->pagi->id, '2026-10-05'))->toBe([])
        ->and(jsaCell($this->pagi->id, '2026-10-06'))->toBe([])
        ->and(jsaCell($this->pagi->id, '2026-10-07'))->toBe([$this->citra->id])
        ->and(jsaCount())->toBe(1);
});

// ── Scenario: Salin dari bulan sebelumnya ────────────────────────────────

it('Jadwal Shift — Salin dari bulan sebelumnya: dry_run lalu salin, sel berisi dipertahankan, skipped dihitung', function () {
    $this->actingAs($this->admin, 'web');
    $inactive = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->millA)->create(['name' => 'Rina Nonaktif']);

    jsaPlace($this->pagi, $this->budi, '2026-10-05');
    jsaPlace($this->pagi, $this->budi, '2026-10-06');      // target filled -> kept
    jsaPlace($this->pagi, $inactive, '2026-10-07');        // inactive -> skipped
    jsaPlace($this->pagi, $this->citra, '2026-10-31');     // November has 30 days -> missing_day
    jsaPlace($this->pagi, $this->dewi, '2026-11-06');
    $inactive->update(['is_active' => false]);
    $before = jsaSnapshot();

    // Step 1
    $this->postJson('/api/shift-schedules/copy-previous', ['production_line_id' => $this->lineA1->id, 'month' => '2026-11', 'dry_run' => true])
        ->assertOk()
        ->assertJsonPath('data.source_month', '2026-10')
        ->assertJsonPath('data.filled', 1)
        ->assertJsonPath('data.kept', 1)
        ->assertJsonPath('data.skipped.inactive_or_not_candidate', 1)
        ->assertJsonPath('data.skipped.missing_day', 1)
        ->assertJsonPath('data.skipped.closed_shift', 0)
        ->assertJsonPath('data.applied', false);
    expect(jsaSnapshot())->toBe($before);

    // Step 2
    $this->postJson('/api/shift-schedules/copy-previous', ['production_line_id' => $this->lineA1->id, 'month' => '2026-11'])
        ->assertOk()->assertJsonPath('data.applied', true)->assertJsonPath('data.filled', 1);

    expect(jsaCell($this->pagi->id, '2026-11-05'))->toBe([$this->budi->id])
        ->and(jsaCell($this->pagi->id, '2026-11-06'))->toBe([$this->dewi->id])   // kept, not overwritten
        ->and(jsaCell($this->pagi->id, '2026-11-07'))->toBe([])
        ->and(jsaCount())->toBe(count($before) + 1);
});

// ── Scenario: Salin saat bulan sebelumnya kosong ─────────────────────────

it('Jadwal Shift — Salin saat bulan sebelumnya kosong: 200 nothing_to_copy=true tanpa penulisan', function () {
    $this->actingAs($this->admin, 'web');
    jsaPlace($this->pagi, $this->budi, '2026-09-05');   // two months back: not the source
    $before = jsaSnapshot();

    $this->postJson('/api/shift-schedules/copy-previous', ['production_line_id' => $this->lineA1->id, 'month' => '2026-11'])
        ->assertOk()
        ->assertJsonPath('data.nothing_to_copy', true)
        ->assertJsonPath('data.filled', 0)
        ->assertJsonPath('data.kept', 0);

    expect(jsaSnapshot())->toBe($before);
});

// ── Scenario: Mengubah tanggal lampau ────────────────────────────────────

it('Jadwal Shift — Mengubah tanggal lampau: PUT 200 tanpa penguncian', function () {
    $this->actingAs($this->admin, 'web');   // clock: 2026-10-12, so 10-01 is in the past

    $this->putJson('/api/shift-schedules/cell', ['shift_id' => $this->pagi->id, 'date' => '2026-10-01', 'user_ids' => [$this->budi->id]])->assertOk();
    $this->putJson('/api/shift-schedules/cell', ['shift_id' => $this->pagi->id, 'date' => '2026-10-01', 'user_ids' => [$this->citra->id]])->assertOk();

    expect(jsaCell($this->pagi->id, '2026-10-01'))->toBe([$this->citra->id]);
});

// ── Scenario: Dua pengatur mengubah sel yang sama ────────────────────────

it('Jadwal Shift — Dua pengatur mengubah sel yang sama: last-write-wins dengan changed_by_others', function () {
    $this->actingAs($this->admin, 'web');

    // Step 1: organiser A saves U2 (loaded empty).
    $this->putJson('/api/shift-schedules/cell', ['shift_id' => $this->pagi->id, 'date' => '2026-10-05', 'user_ids' => [$this->citra->id], 'loaded_user_ids' => []])
        ->assertOk()->assertJsonPath('data.changed_by_others', false);

    // Step 2: organiser B, still looking at the empty cell, saves U1.
    $this->putJson('/api/shift-schedules/cell', ['shift_id' => $this->pagi->id, 'date' => '2026-10-05', 'user_ids' => [$this->budi->id], 'loaded_user_ids' => []])
        ->assertOk()
        ->assertJsonPath('data.changed_by_others', true)
        ->assertJsonPath('data.previous_user_ids', [$this->citra->id])
        ->assertJsonPath('data.assignees.0.user_id', $this->budi->id);

    // The LAST write won.
    expect(jsaCell($this->pagi->id, '2026-10-05'))->toBe([$this->budi->id]);
});

// ── Scenario: Bulan 28, 29, atau 30 hari ─────────────────────────────────

it('Jadwal Shift — Bulan 28, 29, atau 30 hari: jumlah hari = hari sebenarnya, tanpa tanggal palsu', function () {
    $this->actingAs($this->admin, 'web');

    foreach (['2026-02' => 28, '2028-02' => 29, '2026-04' => 30, '2026-10' => 31] as $month => $days) {
        $response = $this->getJson("/api/shift-schedules?production_line_id={$this->lineA1->id}&month={$month}");
        $response->assertOk()->assertJsonCount($days, 'data.days')->assertJsonPath('data.summary.total_cells', $days * 2);

        $dates = array_column($response->json('data.days'), 'date');
        expect(end($dates))->toBe(sprintf('%s-%02d', $month, $days))
            ->and(array_filter($dates, fn ($d) => ! str_starts_with($d, $month.'-')))->toBe([]);
    }
});

// ── Scenario: Layar pada lebar sempit ────────────────────────────────────

it('Jadwal Shift — Layar pada lebar sempit: grid 200 membawa kolom dan baris yang dibutuhkan grid yang dapat digulir', function () {
    $this->actingAs($this->admin, 'web');

    $grid = $this->getJson("/api/shift-schedules?production_line_id={$this->lineA1->id}&month=2026-10");
    $grid->assertOk();
    $data = $grid->json('data');

    // The scroll wrapper / sticky date column / 14px floor are geometry (Livewire + Playwright);
    // the API half is that EVERY row and column the wrapper must scroll over is present.
    expect($data['shifts'])->toHaveCount(2)
        ->and($data['days'])->toHaveCount(31)
        ->and($data['cells'])->toHaveCount(62);
    foreach ($data['shifts'] as $shift) {
        expect(array_keys($shift))->toContain('start_time', 'end_time', 'label', 'crosses_midnight', 'closed', 'closed_from');
    }
    foreach ($data['days'] as $day) {
        expect(array_keys($day))->toContain('date', 'weekday', 'weekday_label', 'is_weekend', 'is_today');
    }
});

// ── Scenario: Menugaskan user bukan calon peserta ditolak ────────────────

it('Jadwal Shift — Menugaskan user bukan calon peserta ditolak: 422 NOT_A_CANDIDATE, sel tidak berubah', function () {
    $this->actingAs($this->admin, 'web');
    $engineer = User::factory()->role(UserRole::Engineer)->forBusinessUnit($this->millA)->create(['name' => 'Eko Engineer']);
    $inactive = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->millA)->inactive()->create(['name' => 'Rina Nonaktif']);
    jsaPlace($this->pagi, $this->citra, '2026-10-05');
    $before = jsaSnapshot();

    $cell = fn (array $ids) => $this->putJson('/api/shift-schedules/cell', ['shift_id' => $this->pagi->id, 'date' => '2026-10-05', 'user_ids' => $ids]);

    // Step 1: one valid + one engineer
    $one = $cell([$this->budi->id, $engineer->id]);
    $one->assertStatus(422)->assertJsonPath('code', 'NOT_A_CANDIDATE');
    expect($one->json('message'))->toContain('Eko Engineer (role Engineer bukan Operator atau Supervisor)');

    // Step 2: inactive
    $cell([$inactive->id])->assertStatus(422)->assertJsonPath('code', 'NOT_A_CANDIDATE');

    // Step 3: another mill
    $three = $cell([$this->bayu->id]);
    $three->assertStatus(422)->assertJsonPath('code', 'NOT_A_CANDIDATE');
    expect($three->json('message'))->toContain('Bayu Beta (bertugas di mill lain)');

    // All-or-nothing: the valid person of step 1 was not saved either.
    expect(jsaSnapshot())->toBe($before);
});

// ── Scenario: Mengisi shift terhapus pada tanggal sesudah penutupan ditolak

it('Jadwal Shift — Mengisi shift terhapus pada tanggal sesudah penutupan ditolak: 422 SHIFT_CLOSED, histori utuh', function () {
    $this->actingAs($this->admin, 'web');
    jsaPlace($this->siang, $this->budi, '2026-10-05');
    jsaClose($this->siang, '2026-10-15 08:00:00');
    $before = jsaSnapshot();

    $this->putJson('/api/shift-schedules/cell', ['shift_id' => $this->siang->id, 'date' => '2026-10-20', 'user_ids' => [$this->citra->id]])
        ->assertStatus(422)->assertJsonPath('code', 'SHIFT_CLOSED');
    $this->postJson('/api/shift-schedules/apply', [
        'shift_id' => $this->siang->id, 'month' => '2026-10', 'dates' => ['2026-10-20'], 'user_ids' => [$this->citra->id], 'dry_run' => false,
    ])->assertStatus(422)->assertJsonPath('code', 'SHIFT_CLOSED');

    $grid = $this->getJson("/api/shift-schedules?production_line_id={$this->lineA1->id}&month=2026-10");
    $grid->assertOk();

    expect(jsaSnapshot())->toBe($before)
        ->and(array_column(jsaGridCell($grid->json('data'), $this->siang->id, '2026-10-05')['assignees'], 'user_id'))->toBe([$this->budi->id])
        ->and(jsaGridCell($grid->json('data'), $this->siang->id, '2026-10-20'))->toBeNull();
});

it('Jadwal Shift — shift_id yang tidak ada: 404 NOT_FOUND', function () {
    $this->actingAs($this->admin, 'web');
    $ghost = (string) Str::uuid();

    $this->putJson('/api/shift-schedules/cell', ['shift_id' => $ghost, 'date' => '2026-10-05', 'user_ids' => []])
        ->assertNotFound()->assertJsonPath('code', 'NOT_FOUND');
    $this->getJson("/api/shift-schedules?production_line_id={$ghost}&month=2026-10")
        ->assertNotFound()->assertJsonPath('code', 'NOT_FOUND');
});

// ── Scenario: Membuka tanpa memilih Production Line lewat permintaan langsung

it('Jadwal Shift — Membuka tanpa memilih Production Line lewat permintaan langsung: absen dan "all" -> 422 VALIDATION_ERROR', function () {
    $this->actingAs($this->admin, 'web');

    $this->getJson('/api/shift-schedules?month=2026-10')
        ->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR')->assertJsonValidationErrors('production_line_id');
    $this->getJson('/api/shift-schedules?production_line_id=all&month=2026-10')
        ->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR')->assertJsonValidationErrors('production_line_id');

    // The same rule guards the other line-scoped reads / writes.
    $this->getJson('/api/shift-schedules/candidates?production_line_id=all')->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
    $this->getJson('/api/shift-schedules/candidates')->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
    $this->postJson('/api/shift-schedules/copy-previous', ['production_line_id' => 'all', 'month' => '2026-11'])->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');
});

// ── Route table ──────────────────────────────────────────────────────────

it('rute: keenam rute literal didaftarkan SEBELUM GET /shift-schedules dan tidak ada rute ber-{id}', function () {
    $uris = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with($r->uri(), 'api/shift-schedules'))
        ->map(fn ($r) => implode('|', $r->methods()).' '.$r->uri())
        ->values();

    $grid = $uris->search(fn ($u) => $u === 'GET|HEAD api/shift-schedules');
    expect($grid)->not->toBeFalse();

    foreach ([
        'GET|HEAD api/shift-schedules/business-units/options',
        'GET|HEAD api/shift-schedules/production-lines/options',
        'GET|HEAD api/shift-schedules/candidates',
        'PUT api/shift-schedules/cell',
        'POST api/shift-schedules/apply',
        'POST api/shift-schedules/copy-previous',
    ] as $literal) {
        $index = $uris->search(fn ($u) => $u === $literal);
        expect($index)->not->toBeFalse("rute $literal tidak terdaftar")
            ->and($index)->toBeLessThan($grid, "$literal harus terdaftar sebelum GET /shift-schedules");
    }

    // No parameterised segment anywhere in this group.
    expect($uris->filter(fn ($u) => str_contains($u, '{'))->all())->toBe([]);
});
