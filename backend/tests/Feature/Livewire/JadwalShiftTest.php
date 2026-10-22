<?php

/**
 * JadwalShiftTest (Feature/Livewire) — screen-171--jadwal-shift /
 * usecase-177--jadwal-shift (Jadwal Shift).
 *
 * Component tests for App\Livewire\MasterData\JadwalShift: one per
 * `test_scenarios` entry whose `component_test` is non-empty (27 entries),
 * plus the sidebar / cross-link / role-scope checks of the brief. The
 * component owns NO business rule — it calls ShiftScheduleService — so these
 * tests prove the mapping (which element shows what), while the rules
 * themselves are proven in tests/Unit/Services/ShiftScheduleServiceTest.php
 * and tests/Feature/ShiftScheduleApiTest.php.
 *
 * ── HOW ASSERTIONS READ THE PAGE (and why) ──────────────────────────────
 *
 * Substring scans over the whole HTML are not used for anything that matters:
 * "belum ada petugas" appears in the summary line as well as in empty cells,
 * "nonaktif" in explanatory copy, and a candidate's name in the cell AND the
 * panel — a scan matches in the wrong place and stays green. Instead every
 * assertion targets an ELEMENT, via XPath over the rendered HTML:
 *
 *   - a grid cell is `td[@data-testid="cell-{shiftId}-{date}"]`;
 *   - "this cell is empty" = it HAS a `span.jd-empty-cell` descendant, and
 *     "this cell is filled" = it has `div.jd-chips` chips and NONE of those;
 *   - absence is asserted on a data-testid KEY or an id, never on prose;
 *   - the summary is `[data-testid="summary-text"]`.
 *
 * The empty-cell wording ("belum ada petugas") sits in a span that has a
 * class but no data-testid of its own; the test locates it by the cell's
 * testid + that class and checks its text (see known issues in the report).
 *
 * DOM-only claims (a sticky column actually stays visible, text is really
 * >= 14px, a cell is not clipped by its parent) are NOT provable here — this
 * file checks the declarations that make them true; the measured geometry is
 * in e2e-web/tests/jadwal-shift.spec.ts.
 *
 * ── DATES ───────────────────────────────────────────────────────────────
 *
 * Clock frozen at 2026-10-12 10:00 Asia/Jakarta (reset in afterEach); month
 * is always passed explicitly. 2026-10-01 is a Thursday.
 */

use App\Enums\UserRole;
use App\Livewire\MasterData\JadwalShift;
use App\Models\BusinessUnit;
use App\Models\ProductionLine;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

dataset('jadwal lw admin equivalents', fn () => array_map(fn (UserRole $r) => [$r], UserRole::adminEquivalents()));

dataset('jadwal lw mill-scoped roles', [
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

// ── helpers (prefix jdl: Pest files share one global function namespace) ──

function jdlPlace(Shift $shift, User $user, string $date): void
{
    ShiftAssignment::factory()->create([
        'shift_id' => $shift->id, 'user_id' => $user->id, 'work_date' => $date, 'created_by' => $user->id,
    ]);
}

function jdlClose(Shift $shift, string $at): void
{
    $previous = Carbon::getTestNow();
    Carbon::setTestNow(Carbon::parse($at, 'Asia/Jakarta'));
    $shift->delete();
    Carbon::setTestNow($previous);
}

function jdlCell(string $shiftId, string $date): array
{
    $ids = DB::table('shift_assignments')->where('shift_id', $shiftId)->where('work_date', $date)->pluck('user_id')->all();
    sort($ids);

    return $ids;
}

function jdlSorted(array $ids): array
{
    sort($ids);

    return array_values($ids);
}

function jdlSnapshot(): array
{
    $rows = DB::table('shift_assignments')->get(['shift_id', 'user_id', 'work_date'])
        ->map(fn ($r) => $r->shift_id.'|'.$r->user_id.'|'.substr((string) $r->work_date, 0, 10))->all();
    sort($rows);

    return $rows;
}

function jdlCount(): int
{
    return DB::table('shift_assignments')->count();
}

function jdlXpath(string $html): DOMXPath
{
    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>');
    libxml_clear_errors();

    return new DOMXPath($dom);
}

/** @return list<DOMElement> */
function jdlNodes(string $html, string $query): array
{
    $result = [];
    foreach (jdlXpath($html)->query($query) as $node) {
        $result[] = $node;
    }

    return $result;
}

function jdlText(?DOMNode $node): string
{
    return $node === null ? '' : trim(preg_replace('/\s+/u', ' ', $node->textContent));
}

/** Text of the single element with this data-testid; fails when absent or duplicated. */
function jdlTestIdText(string $html, string $testId): string
{
    $nodes = jdlNodes($html, '//*[@data-testid="'.$testId.'"]');
    expect($nodes)->toHaveCount(1, "data-testid=\"$testId\" harus ada tepat satu");

    return jdlText($nodes[0]);
}

function jdlHas(string $html, string $testId): bool
{
    return jdlNodes($html, '//*[@data-testid="'.$testId.'"]') !== [];
}

function jdlCellNode(string $html, string $shiftId, string $date): DOMElement
{
    $nodes = jdlNodes($html, '//td[@data-testid="cell-'.$shiftId.'-'.$date.'"]');
    expect($nodes)->toHaveCount(1, "sel $shiftId / $date tidak dirender tepat satu kali");

    return $nodes[0];
}

/** Names of the visible chips inside a cell, in order. */
function jdlChipNames(string $html, string $shiftId, string $date): array
{
    $cell = jdlCellNode($html, $shiftId, $date);
    $names = [];
    foreach ((new DOMXPath($cell->ownerDocument))->query('.//span[contains(concat(" ", normalize-space(@class), " "), " jd-chip ")]', $cell) as $chip) {
        // chip text = name (+ the "nonaktif" badge, which is its own span)
        $clone = $chip->cloneNode(true);
        foreach (iterator_to_array((new DOMXPath($clone->ownerDocument))->query('.//span', $clone)) as $inner) {
            $inner->parentNode->removeChild($inner);
        }
        $names[] = jdlText($clone);
    }

    return $names;
}

function jdlCellIsEmpty(string $html, string $shiftId, string $date): bool
{
    $cell = jdlCellNode($html, $shiftId, $date);
    $empty = (new DOMXPath($cell->ownerDocument))->query('.//span[contains(concat(" ", normalize-space(@class), " "), " jd-empty-cell ")]', $cell);

    if ($empty->length === 1) {
        expect(jdlText($empty->item(0)))->toBe('belum ada petugas');
    }

    return $empty->length === 1;
}

/** [value => label] of the <option>s of a <select data-testid=...>. */
function jdlOptions(string $html, string $selectTestId): array
{
    $out = [];
    foreach (jdlNodes($html, '//select[@data-testid="'.$selectTestId.'"]/option') as $option) {
        $out[] = [$option->getAttribute('value'), jdlText($option)];
    }

    return $out;
}

function jdlSidebar(string $html): string
{
    preg_match('#<aside class="shell-sidebar".*?</aside>#s', $html, $m);

    return $m[0] ?? '';
}

/** Mounts the component at a context. */
function jdlOpen(object $test, User $user, ?string $millId = null, ?string $lineId = null, string $month = '2026-10')
{
    $component = Livewire::actingAs($user)->test(JadwalShift::class)->set('month', $month);

    if ($millId !== null) {
        $component->set('businessUnitId', $millId);
    }
    if ($lineId !== null) {
        $component->set('productionLineId', $lineId);
    }

    return $component;
}

/**
 * Admin on Line 1, in the TABLE view. Spec v2 made the calendar the default
 * view; the pre-v2 assertions in this file read table cells, so they ask for
 * the table explicitly (a view switch writes nothing).
 */
function jdlAsAdminOnLine1(object $test, string $month = '2026-10')
{
    return jdlOpen($test, $test->admin, $test->millA->id, $test->lineA1->id, $month)->call('setViewMode', 'table');
}

/** Admin on Line 1, in the default CALENDAR view. */
function jdlAsAdminCalendar(object $test, string $month = '2026-10')
{
    return jdlOpen($test, $test->admin, $test->millA->id, $test->lineA1->id, $month);
}

/** Sets the checked users of ONE shift section on the open day card. */
function jdlSelect($component, string $shiftId, array $userIds)
{
    return $component->set('daySelected.'.$shiftId, $userIds);
}

// ── Scenario: success ────────────────────────────────────────────────────

it('Jadwal Shift — success: pilih mill dan line, buka sel, centang satu petugas, Simpan', function () {
    $component = jdlAsAdminOnLine1($this)
        ->call('openDay', '2026-10-05', $this->pagi->id)
        ->assertSet('openShiftId', $this->pagi->id)
        ->assertSet('openDate', '2026-10-05')
        ->set('daySelected.'.$this->pagi->id, [$this->budi->id])
        ->call('saveShift', $this->pagi->id)
        ->assertSet('panelError', null)
        ->assertSet('panelNotice', 'Petugas sel berhasil disimpan.');

    $html = $component->html();

    expect(jdlCell($this->pagi->id, '2026-10-05'))->toBe([$this->budi->id])
        ->and(jdlChipNames($html, $this->pagi->id, '2026-10-05'))->toBe(['Budi Santoso'])
        ->and(jdlCellIsEmpty($html, $this->pagi->id, '2026-10-05'))->toBeFalse()
        // every OTHER cell is still empty
        ->and(jdlCellIsEmpty($html, $this->pagi->id, '2026-10-06'))->toBeTrue()
        ->and(jdlCellIsEmpty($html, $this->siang->id, '2026-10-05'))->toBeTrue()
        ->and(jdlTestIdText($html, 'summary-text'))->toBe('1 dari 62 sel sudah terisi');
});

// ── Scenario: Mill belum dipilih ─────────────────────────────────────────

it('Jadwal Shift — Mill belum dipilih: tanpa grid, arahan pilih mill, pemilih Line belum punya pilihan', function () {
    $html = Livewire::actingAs($this->admin)->test(JadwalShift::class)->set('month', '2026-10')->html();

    expect(jdlHas($html, 'pick-mill-guide'))->toBeTrue()
        ->and(jdlTestIdText($html, 'pick-mill-guide'))->toContain('Pilih mill terlebih dahulu')
        ->and(jdlHas($html, 'schedule-grid'))->toBeFalse()
        ->and(jdlHas($html, 'pick-line-guide'))->toBeFalse()
        ->and(jdlHas($html, 'schedule-summary'))->toBeFalse()
        // Line select exists but offers ONLY the placeholder (no real line to pick yet).
        ->and(jdlOptions($html, 'line-select'))->toBe([['', '-- Pilih Production Line --']]);
});

// ── Scenario: Production Line belum dipilih ──────────────────────────────

it('Jadwal Shift — Production Line belum dipilih: tanpa grid, tanpa opsi "semua line", arahan pilih line', function () {
    $html = jdlOpen($this, $this->admin, $this->millA->id)->html();

    expect(jdlHas($html, 'pick-line-guide'))->toBeTrue()
        ->and(jdlHas($html, 'schedule-grid'))->toBeFalse()
        ->and(jdlHas($html, 'pick-mill-guide'))->toBeFalse()
        // EXACT option list: placeholder + the two real lines. No "semua"/"all" entry of any kind.
        ->and(jdlOptions($html, 'line-select'))->toBe([
            ['', '-- Pilih Production Line --'],
            [$this->lineA1->id, 'Line 1'],
            [$this->lineA2->id, 'Line 2 — belum punya shift'],
        ]);
});

// ── Scenario: Mill belum punya Production Line ───────────────────────────

it('Jadwal Shift — Mill belum punya Production Line: pesan dan tautan ke Kelola Production Line, tanpa grid', function () {
    $html = jdlOpen($this, $this->admin, $this->millKosong->id)->html();

    expect(jdlHas($html, 'no-production-line-guide'))->toBeTrue()
        ->and(jdlTestIdText($html, 'no-production-line-guide'))->toContain('Mill ini belum punya Production Line')
        ->and(jdlHas($html, 'schedule-grid'))->toBeFalse();

    $link = jdlNodes($html, '//a[@data-testid="production-line-link"]');
    expect($link)->toHaveCount(1)
        ->and($link[0]->getAttribute('href'))->toBe(route('master-data.production-lines'))
        ->and(jdlText($link[0]))->toBe('Kelola Production Line');
});

// ── Scenario: Line belum punya shift hidup ───────────────────────────────

it('Jadwal Shift — Line belum punya shift hidup: pesan dengan tautan ke Kelola Shift, tanpa tabel grid', function () {
    $html = jdlOpen($this, $this->admin, $this->millA->id, $this->lineA2->id)->html();

    expect(jdlHas($html, 'no-live-shift-guide'))->toBeTrue()
        ->and(jdlTestIdText($html, 'no-live-shift-guide'))->toContain('belum punya shift')
        ->and(jdlHas($html, 'schedule-grid'))->toBeFalse()
        ->and(jdlHas($html, 'schedule-summary'))->toBeFalse()
        ->and(jdlHas($html, 'open-apply-button'))->toBeFalse();   // nothing to apply to

    $link = jdlNodes($html, '//a[@data-testid="kelola-shift-link"]');
    expect($link)->toHaveCount(1)->and($link[0]->getAttribute('href'))->toBe(route('master-data.shifts'));
});

// ── Scenario: Supervisor atau Mill Management mencoba mill lain ──────────

it('Jadwal Shift — Supervisor/Mill Management: pemilih mill tidak tampil (fixed), mill lain lewat query string dipaksa kembali', function (UserRole $role) {
    $actor = User::factory()->role($role)->forBusinessUnit($this->millA)->create();

    // Mounting WITH another mill in the URL-bound property (what ?businessUnitId= does).
    $component = Livewire::actingAs($actor)->test(JadwalShift::class, ['businessUnitId' => $this->millB->id, 'month' => '2026-10']);
    $html = $component->html();

    $component->assertSet('businessUnitId', $this->millA->id);

    expect(jdlHas($html, 'mill-select'))->toBeFalse()                       // no picker at all
        ->and(jdlTestIdText($html, 'mill-fixed'))->toBe('Mill Alpha')
        ->and(jdlOptions($html, 'line-select'))->toBe([
            ['', '-- Pilih Production Line --'],
            [$this->lineA1->id, 'Line 1'],
            [$this->lineA2->id, 'Line 2 — belum punya shift'],
        ])
        ->and(str_contains($html, $this->lineB1->id))->toBeFalse()          // mill B's line id appears nowhere
        ->and(str_contains($html, $this->millB->id))->toBeFalse();

    // Setting mill B after mount, and a mill-B line, changes nothing.
    $component->set('businessUnitId', $this->millB->id)->set('productionLineId', $this->lineB1->id);
    $after = $component->html();

    $component->assertSet('businessUnitId', $this->millA->id)->assertSet('productionLineId', '');
    expect(jdlHas($after, 'schedule-grid'))->toBeFalse()
        ->and(jdlHas($after, 'pick-line-guide'))->toBeTrue()
        ->and(str_contains($after, $this->lineB1->id))->toBeFalse();
})->with('jadwal lw mill-scoped roles');

it('Jadwal Shift — Supervisor/Mill Management memakai mill sendiri tanpa memilih; grid tampil untuk line-nya', function (UserRole $role) {
    $actor = User::factory()->role($role)->forBusinessUnit($this->millA)->create();
    jdlPlace($this->pagi, $this->budi, '2026-10-05');

    $html = Livewire::actingAs($actor)->test(JadwalShift::class)
        ->set('month', '2026-10')
        ->set('productionLineId', $this->lineA1->id)
        ->call('setViewMode', 'table')
        ->html();

    expect(jdlHas($html, 'schedule-grid'))->toBeTrue()
        ->and(jdlChipNames($html, $this->pagi->id, '2026-10-05'))->toBe(['Budi Santoso'])
        // Kelola Shift is Admin-only: the shortcut to it is not offered, and the note says so.
        ->and(jdlHas($html, 'open-kelola-shift'))->toBeFalse()
        ->and(jdlTestIdText($html, 'schedule-note'))->toContain('Kelola Shift (khusus Admin)');
})->with('jadwal lw mill-scoped roles');

it('Jadwal Shift — Admin melihat pemilih mill berisi semua mill (tidak fixed)', function () {
    $html = Livewire::actingAs($this->admin)->test(JadwalShift::class)->set('month', '2026-10')->html();

    $options = jdlOptions($html, 'mill-select');
    expect(jdlHas($html, 'mill-fixed'))->toBeFalse()
        ->and(array_column($options, 0))->toEqualCanonicalizing(array_merge([''], BusinessUnit::query()->pluck('id')->all()));
});

// ── Scenario: Operator mencoba membuka layar ─────────────────────────────

it('Jadwal Shift — Operator: ditolak oleh rute DAN oleh mount() komponen, tanpa data shift_assignments', function () {
    jdlPlace($this->pagi, $this->budi, '2026-10-05');

    // Layer 1 — route middleware.
    $this->actingAs($this->operator, 'web')->get('/master-data/shift-schedules')->assertForbidden();

    // Layer 2 — mount() itself, mounted directly; Livewire renders the 403 page instead of rethrowing.
    DB::flushQueryLog();
    DB::enableQueryLog();
    $html = Livewire::actingAs($this->operator)
        ->test(JadwalShift::class, ['businessUnitId' => $this->millA->id, 'productionLineId' => $this->lineA1->id, 'month' => '2026-10'])
        ->html();
    $touched = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'shift_assignments'))->count();
    DB::disableQueryLog();

    expect($html)->toContain('Akses Ditolak')
        ->and(jdlHas($html, 'schedule-grid'))->toBeFalse()
        ->and(jdlHas($html, 'mill-select'))->toBeFalse()
        ->and(jdlHas($html, 'mill-fixed'))->toBeFalse()
        ->and(str_contains($html, $this->budi->id))->toBeFalse()
        ->and($touched)->toBe(0);
});

it('Jadwal Shift — tujuh role setara Admin dapat membuka layar sebagai admin (rute + mount)', function (UserRole $role) {
    $actor = User::factory()->role($role)->forBusinessUnit($this->millA)->create();
    expect($actor->getRawOriginal('role'))->toBe($role->value);

    $this->actingAs($actor, 'web')->get('/master-data/shift-schedules')->assertOk();

    $html = Livewire::actingAs($actor)->test(JadwalShift::class)->set('month', '2026-10')->html();
    expect(jdlHas($html, 'mill-select'))->toBeTrue()           // unrestricted: sees the picker
        ->and(jdlHas($html, 'mill-fixed'))->toBeFalse();
})->with('jadwal lw admin equivalents');

// ── Scenario: Satu orang di beberapa shift pada hari yang sama ───────────

it('Jadwal Shift — Satu orang di beberapa shift pada hari yang sama: tersimpan tanpa galat, panel menandai shift lain', function () {
    jdlPlace($this->pagi, $this->budi, '2026-10-05');

    $component = jdlAsAdminOnLine1($this)->call('openDay', '2026-10-05', $this->siang->id);
    $panel = $component->html();

    // The panel flags Budi as already on Pagi — as an element keyed by his id.
    $flag = jdlTestIdText($panel, 'also-other-'.$this->budi->id);
    expect($flag)->toBe('juga di shift Pagi hari ini')
        ->and(jdlHas($panel, 'also-other-'.$this->citra->id))->toBeFalse();

    $component->set('daySelected.'.$this->siang->id, [$this->budi->id])->call('saveShift', $this->siang->id)->assertSet('panelError', null);

    $html = $component->html();
    expect(jdlCell($this->siang->id, '2026-10-05'))->toBe([$this->budi->id])
        ->and(jdlCell($this->pagi->id, '2026-10-05'))->toBe([$this->budi->id])
        ->and(jdlCount())->toBe(2)
        ->and(jdlChipNames($html, $this->pagi->id, '2026-10-05'))->toBe(['Budi Santoso'])
        ->and(jdlChipNames($html, $this->siang->id, '2026-10-05'))->toBe(['Budi Santoso'])
        ->and(jdlHas($html, 'panel-error'))->toBeFalse();
});

// ── Scenario: Orang yang sama dipilih dua kali pada sel yang sama ────────

it('Jadwal Shift — Orang yang sama pada sel yang sama: buka sel berisi U1, biarkan tercentang, Simpan: tak ada perubahan', function () {
    jdlPlace($this->pagi, $this->budi, '2026-10-05');
    $rowId = DB::table('shift_assignments')->value('id');

    $component = jdlAsAdminOnLine1($this)
        ->call('openDay', '2026-10-05', $this->pagi->id)
        ->assertSet('daySelected', [$this->pagi->id => [$this->budi->id], $this->siang->id => []])
        ->assertSet('dayLoaded', [$this->pagi->id => [$this->budi->id], $this->siang->id => []])
        ->call('saveShift', $this->pagi->id)
        ->assertSet('panelError', null)
        ->assertSet('panelNotice', 'Petugas sel berhasil disimpan.');   // NOT the "diubah orang lain" notice

    expect(jdlCount())->toBe(1)
        ->and(DB::table('shift_assignments')->value('id'))->toBe($rowId)
        ->and(jdlHas($component->html(), 'panel-error'))->toBeFalse();
});

// ── Scenario: Shift dibiarkan tanpa petugas ──────────────────────────────

it('Jadwal Shift — Shift dibiarkan tanpa petugas: hapus centang semua lalu Simpan, sel kosong dan dihitung kosong', function () {
    jdlPlace($this->pagi, $this->budi, '2026-10-05');
    jdlPlace($this->pagi, $this->citra, '2026-10-05');
    jdlPlace($this->pagi, $this->budi, '2026-10-06');

    $component = jdlAsAdminOnLine1($this);
    expect(jdlTestIdText($component->html(), 'summary-text'))->toBe('2 dari 62 sel sudah terisi');

    $component->call('openDay', '2026-10-05', $this->pagi->id)
        ->set('daySelected.'.$this->pagi->id, [])
        ->call('saveShift', $this->pagi->id)
        ->assertSet('panelError', null);

    $html = $component->html();
    expect(jdlCell($this->pagi->id, '2026-10-05'))->toBe([])
        ->and(jdlCellIsEmpty($html, $this->pagi->id, '2026-10-05'))->toBeTrue()
        ->and(jdlCellIsEmpty($html, $this->pagi->id, '2026-10-06'))->toBeFalse()    // neighbour untouched
        ->and(jdlCell($this->pagi->id, '2026-10-06'))->toBe([$this->budi->id])
        ->and(jdlTestIdText($html, 'summary-text'))->toBe('1 dari 62 sel sudah terisi');
});

it('Jadwal Shift — tombol "Kosongkan" mengosongkan sel tanpa menyentuh pilihan yang belum disimpan', function () {
    jdlPlace($this->pagi, $this->budi, '2026-10-05');

    $component = jdlAsAdminOnLine1($this)
        ->call('openDay', '2026-10-05', $this->pagi->id)
        ->call('clearShift', $this->pagi->id)     // one person only: no confirmation, written at once
        ->assertSet('panelError', null);

    expect(jdlCell($this->pagi->id, '2026-10-05'))->toBe([])
        ->and(jdlCellIsEmpty($component->html(), $this->pagi->id, '2026-10-05'))->toBeTrue();
});

// ── Scenario: Mill tanpa calon peserta ───────────────────────────────────

it('Jadwal Shift — Mill tanpa calon peserta: panel menyatakan tidak ada calon peserta, bukan daftar kosong tanpa keterangan', function () {
    $lineK = ProductionLine::factory()->forBusinessUnit($this->millKosong)->create(['name' => 'Line K']);
    $shiftK = Shift::factory()->forProductionLine($lineK)->named('Pagi K')->range('07:00:00', '15:00:00')->create();
    User::factory()->role(UserRole::Operator)->forBusinessUnit($this->millKosong)->inactive()->create();
    User::factory()->role(UserRole::Engineer)->forBusinessUnit($this->millKosong)->create();

    $html = jdlOpen($this, $this->admin, $this->millKosong->id, $lineK->id)
        ->call('openDay', '2026-10-05', $shiftK->id)
        ->html();

    expect(jdlTestIdText($html, 'no-candidates'))->toStartWith('tidak ada calon peserta')
        ->and(jdlHas($html, 'no-search-result'))->toBeFalse()
        ->and(jdlNodes($html, '//*[@data-testid="panel-list"]//input[@type="checkbox"]'))->toBe([]);
});

it('Jadwal Shift — pencarian tanpa hasil di mill yang PUNYA calon: "Tidak ada nama yang cocok", bukan "tidak ada calon peserta"', function () {
    $html = jdlAsAdminOnLine1($this)
        ->call('openDay', '2026-10-05', $this->pagi->id)
        ->set('panelSearch', 'zzzz-tidak-ada')
        ->html();

    expect(jdlHas($html, 'no-search-result'))->toBeTrue()
        ->and(jdlHas($html, 'no-candidates'))->toBeFalse();
});

// ── Scenario: Role selain Operator dan Supervisor tidak menjadi calon ────

it('Jadwal Shift — Role selain Operator dan Supervisor tidak menjadi calon peserta di panel', function () {
    $engineer = User::factory()->role(UserRole::Engineer)->forBusinessUnit($this->millA)->create(['name' => 'Eko Engineer']);
    $mm = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->millA)->create(['name' => 'Mira MM']);
    $adm = User::factory()->role(UserRole::Admin)->forBusinessUnit($this->millA)->create(['name' => 'Adi Admin']);
    $inactive = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->millA)->inactive()->create(['name' => 'Rina Nonaktif']);

    $html = jdlAsAdminOnLine1($this)->call('openDay', '2026-10-05', $this->pagi->id)->html();

    // Panel candidates = checkboxes keyed by user id, read from the raw role column.
    $shown = array_map(
        fn (DOMElement $n) => $n->getAttribute('value'),
        jdlNodes($html, '//*[@data-testid="panel-list"]//input[@type="checkbox"]')
    );
    $expected = User::query()->where('business_unit_id', $this->millA->id)->where('is_active', true)
        ->whereIn('role', ['operator', 'supervisor'])->pluck('id')->all();

    expect($shown)->toEqualCanonicalizing($expected)
        ->and(jdlHas($html, 'candidate-'.$this->budi->id))->toBeTrue()
        ->and(jdlHas($html, 'candidate-'.$this->dewi->id))->toBeTrue()
        ->and(jdlHas($html, 'candidate-'.$engineer->id))->toBeFalse()
        ->and(jdlHas($html, 'candidate-'.$mm->id))->toBeFalse()
        ->and(jdlHas($html, 'candidate-'.$adm->id))->toBeFalse()
        ->and(jdlHas($html, 'candidate-'.$inactive->id))->toBeFalse()
        ->and(jdlHas($html, 'candidate-'.$this->bayu->id))->toBeFalse();   // other mill
});

// ── Scenario: Shift lintas tengah malam ──────────────────────────────────

it('Jadwal Shift — Shift lintas tengah malam: petugas di baris 5 Okt, tidak di 6 Okt, header memuat +1', function () {
    $malam = Shift::factory()->forProductionLine($this->lineA1)->named('Malam')->range('23:00:00', '07:00:00')->create();

    $component = jdlAsAdminOnLine1($this)
        ->call('openDay', '2026-10-05', $malam->id)
        ->set('daySelected.'.$malam->id, [$this->budi->id])
        ->call('saveShift', $malam->id);
    $html = $component->html();

    expect(jdlChipNames($html, $malam->id, '2026-10-05'))->toBe(['Budi Santoso'])
        ->and(jdlCellIsEmpty($html, $malam->id, '2026-10-06'))->toBeTrue()
        ->and(jdlCell($malam->id, '2026-10-06'))->toBe([])
        ->and(jdlTestIdText($html, 'shift-head-'.$malam->id))->toBe('Malam 23:00 — 07:00 +1')
        // a normal shift's header carries no "+1"
        ->and(jdlTestIdText($html, 'shift-head-'.$this->pagi->id))->toBe('Pagi 07:00 — 15:00');
});

// ── Scenario: Shift dihapus pertengahan bulan ────────────────────────────

it('Jadwal Shift — Shift dihapus pertengahan bulan: kolom read-only "shift sudah dihapus", tanpa sel sesudah penutupan, histori terlihat', function () {
    jdlPlace($this->siang, $this->budi, '2026-10-14');
    jdlClose($this->siang, '2026-10-15 01:30:00');

    $component = jdlAsAdminOnLine1($this);
    $html = $component->html();

    expect(jdlTestIdText($html, 'closed-badge-'.$this->siang->id))->toBe('shift sudah dihapus')
        ->and(jdlHas($html, 'closed-badge-'.$this->pagi->id))->toBeFalse()
        // History before the closure: visible and read-only (class). Its button only OPENS the
        // day card (history mode) - proven below by what the card does NOT contain.
        ->and(jdlChipNames($html, $this->siang->id, '2026-10-14'))->toBe(['Budi Santoso'])
        ->and(jdlCellNode($html, $this->siang->id, '2026-10-14')->getAttribute('class'))->toContain('jd-readonly')
        ->and(jdlHas($html, 'jd-cell-btn-'.$this->siang->id.'-2026-10-14'))->toBeTrue()
        ->and(jdlHas($html, 'jd-cell-btn-'.$this->siang->id.'-2026-10-01'))->toBeTrue()
        // On/after closed_from: the cell is a placeholder with no content and no button at all.
        ->and(jdlCellNode($html, $this->siang->id, '2026-10-15')->getAttribute('class'))->toContain('jd-closed')
        ->and(jdlText(jdlCellNode($html, $this->siang->id, '2026-10-15')))->toBe('tidak ada — shift sudah dihapus')
        ->and(jdlHas($html, 'jd-cell-btn-'.$this->siang->id.'-2026-10-15'))->toBeFalse()
        ->and(jdlHas($html, 'jd-cell-btn-'.$this->siang->id.'-2026-10-31'))->toBeFalse()
        // The live column next to it is clickable on the same dates.
        ->and(jdlHas($html, 'jd-cell-btn-'.$this->pagi->id.'-2026-10-14'))->toBeTrue()
        ->and(jdlHas($html, 'jd-cell-btn-'.$this->pagi->id.'-2026-10-20'))->toBeTrue();

    // Clicking a read-only cell opens the card in HISTORY mode: no error, no save control, no checkbox.
    $before = jdlSnapshot();
    $component->call('openDay', '2026-10-14', $this->siang->id)
        ->assertSet('openDate', '2026-10-14')
        ->assertSet('panelError', null);
    $card = $component->html();
    expect(jdlTestIdText($card, 'jd-card-closed-'.$this->siang->id))->toBe('shift sudah dihapus')
        ->and(jdlTestIdText($card, 'jd-card-history-'.$this->siang->id))->toBe('Budi Santoso. Shift sudah dihapus; hanya dapat dibaca.')
        ->and(jdlHas($card, 'jd-card-save-'.$this->siang->id))->toBeFalse()
        ->and(jdlHas($card, 'jd-card-clear-'.$this->siang->id))->toBeFalse()
        ->and(jdlNodes($card, '//*[@data-testid="jd-card-shift-'.$this->siang->id.'"]//input'))->toBe([])
        ->and(jdlSnapshot())->toBe($before);

    // A crafted save for the closed shift writes nothing (the shift is not in the loaded day).
    $component->call('saveShift', $this->siang->id);
    expect(jdlSnapshot())->toBe($before);
});

// ── Scenario: Jam shift diubah setelah bulan diisi ───────────────────────

it('Jadwal Shift — Jam shift diubah setelah bulan diisi: petugas tetap di kolom yang sama dengan label jam terbaru', function () {
    jdlPlace($this->pagi, $this->budi, '2026-10-05');
    $this->pagi->update(['start_time' => '08:00:00', 'end_time' => '16:00:00']);

    $html = jdlAsAdminOnLine1($this)->html();

    expect(jdlTestIdText($html, 'shift-head-'.$this->pagi->id))->toBe('Pagi 08:00 — 16:00')
        ->and(jdlChipNames($html, $this->pagi->id, '2026-10-05'))->toBe(['Budi Santoso'])
        ->and(jdlCount())->toBe(1)
        ->and(jdlCell($this->pagi->id, '2026-10-05'))->toBe([$this->budi->id]);
});

// ── Scenario: User dinonaktifkan setelah ditugaskan ──────────────────────

it('Jadwal Shift — User dinonaktifkan setelah ditugaskan: tampil "nonaktif", bukan calon di sel lain, boleh dikeluarkan', function () {
    $gone = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->millA)->create(['name' => 'Rina Pindah']);
    jdlPlace($this->pagi, $gone, '2026-10-05');
    $gone->update(['is_active' => false]);

    $component = jdlAsAdminOnLine1($this);
    $html = $component->html();

    // The cell shows the name WITH a "nonaktif" badge element inside the chip.
    $badges = jdlNodes($html, '//td[@data-testid="cell-'.$this->pagi->id.'-2026-10-05"]//span[contains(concat(" ", normalize-space(@class), " "), " jd-chip ")]//span[contains(concat(" ", normalize-space(@class), " "), " badge ")]');
    expect(jdlChipNames($html, $this->pagi->id, '2026-10-05'))->toBe(['Rina Pindah'])
        ->and($badges)->toHaveCount(1)
        ->and(jdlText($badges[0]))->toBe('nonaktif');

    // Panel of ANOTHER cell: not offered as a candidate.
    $other = $component->call('openDay', '2026-10-06', $this->pagi->id)->html();
    expect(jdlHas($other, 'candidate-'.$gone->id))->toBeFalse();

    // Adding the inactive user to another cell is refused, with name + reason; nothing is written.
    $component->set('daySelected.'.$this->pagi->id, [$gone->id])->call('saveShift', $this->pagi->id);
    expect($component->get('panelError'))->toContain('Rina Pindah (nonaktif)')
        ->and(jdlCell($this->pagi->id, '2026-10-06'))->toBe([])
        ->and(jdlCount())->toBe(1);

    // Own cell: the inactive member is listed (with a badge) so it can be removed.
    // The refused selection is still unsaved, so moving to another date first asks to discard it.
    $component->call('openDay', '2026-10-05', $this->pagi->id)->assertSet('openDate', '2026-10-06')->assertSet('pendingDate', '2026-10-05');
    $own = $component->call('confirmDiscard')->assertSet('openDate', '2026-10-05')->html();
    $row = jdlNodes($own, '//*[@data-testid="panel-list"]//label[.//input[@data-testid="candidate-'.$gone->id.'"]]');
    expect($row)->toHaveCount(1)
        ->and(jdlText($row[0]))->toContain('Rina Pindah');

    $component->set('daySelected.'.$this->pagi->id, [])->call('saveShift', $this->pagi->id)->assertSet('panelError', null);

    expect(jdlCell($this->pagi->id, '2026-10-05'))->toBe([])
        ->and(jdlCellIsEmpty($component->html(), $this->pagi->id, '2026-10-05'))->toBeTrue();
});

// ── Scenario: Terapkan ke banyak tanggal ─────────────────────────────────

it('Jadwal Shift — Terapkan ke beberapa tanggal: pratinjau, konfirmasi inline saat menimpa, tanggal luar bulan diabaikan', function () {
    jdlPlace($this->pagi, $this->citra, '2026-10-01');
    $before = jdlSnapshot();

    $component = jdlAsAdminOnLine1($this)
        ->call('openApply')
        ->assertSet('applyOpen', true)
        ->assertSet('applyFrom', '2026-10-01')      // defaults span the open month
        ->assertSet('applyTo', '2026-10-31')
        ->set('applyShiftId', $this->pagi->id)
        ->set('applyMode', 'dates')
        ->set('applyUserIds', [$this->budi->id]);

    foreach (['2026-10-01', '2026-10-02', '2026-11-01'] as $date) {
        $component->set('applyDateToAdd', $date)->call('addApplyDate');
    }
    $component->assertSet('applyDates', ['2026-10-01', '2026-10-02', '2026-11-01']);

    // Preview: counts shown BEFORE saving, nothing written.
    $component->call('previewApply');
    $preview = $component->html();
    expect($component->get('applyPreview'))->toMatchArray([
        'target_cells' => 2, 'cells_already_filled' => 1, 'people_affected' => 1, 'outside_month_ignored' => 1, 'applied' => false,
    ])
        ->and(jdlTestIdText($preview, 'apply-preview-title'))->toBe('Akan memengaruhi 2 sel')
        ->and(jdlTestIdText($preview, 'apply-preview-detail'))->toStartWith(
            '1 di antaranya sudah berisi dan akan ditimpa (1 orang tergeser). Hanya tanggal pada bulan Oktober 2026 yang terpengaruh'
        )
        ->and(jdlHas($preview, 'apply-confirm'))->toBeFalse()
        ->and(jdlSnapshot())->toBe($before);

    // First "Terapkan": the inline confirmation appears and NOTHING is written yet.
    $component->call('submitApply')->assertSet('applyConfirming', true);
    $confirm = $component->html();
    expect(jdlTestIdText($confirm, 'apply-confirm'))->toStartWith('Timpa 1 sel yang sudah berisi?')
        ->and(jdlHas($confirm, 'apply-confirm-button'))->toBeTrue()
        ->and(jdlHas($confirm, 'apply-submit-button'))->toBeFalse()   // the plain button is replaced
        ->and(jdlSnapshot())->toBe($before);

    // Changing any input withdraws the confirmation (it only ever applies to the counted input).
    $component->set('applyUserIds', [$this->budi->id, $this->dewi->id])
        ->assertSet('applyConfirming', false)
        ->assertSet('applyPreview', null);
    $component->set('applyUserIds', [$this->budi->id])->call('submitApply')->assertSet('applyConfirming', true);

    // Second click ("Ya, timpa dan simpan"): written.
    $component->call('submitApply')->assertSet('applyConfirming', false)->assertSet('applyError', null);
    $after = $component->html();

    expect(jdlTestIdText($after, 'apply-result'))->toBe('2 sel diperbarui dari 2 sel sasaran. 1 tanggal di luar bulan ini diabaikan.')
        ->and(jdlHas($after, 'apply-confirm'))->toBeFalse()
        ->and(jdlCell($this->pagi->id, '2026-10-01'))->toBe([$this->budi->id])
        ->and(jdlCell($this->pagi->id, '2026-10-02'))->toBe([$this->budi->id])
        ->and(jdlCell($this->pagi->id, '2026-11-01'))->toBe([])
        ->and(jdlCount())->toBe(2)
        ->and(jdlChipNames($after, $this->pagi->id, '2026-10-02'))->toBe(['Budi Santoso']);
});

/**
 * KNOWN FAILING — SUSPECTED BLADE DEFECT (not fixed here, tests only).
 * jadwal-shift.blade.php writes `yang terpengaruh@if ($applyPreview[...] > 0);
 * ... @endif` with the `@if` glued to the preceding word. Blade only compiles
 * a directive when `@` is NOT preceded by a word character, so the `@if (...)`
 * is printed to the user as literal text and the "N tanggal di luar bulan ini
 * diabaikan" sentence is never conditional. The intended text is asserted.
 */
it('Jadwal Shift — pratinjau Terapkan: kalimat tanggal luar bulan tampil bersih, tanpa sisa sintaks Blade', function () {
    $component = jdlAsAdminOnLine1($this)->call('openApply')
        ->set('applyShiftId', $this->pagi->id)->set('applyMode', 'dates')
        ->set('applyDateToAdd', '2026-10-02')->call('addApplyDate')
        ->set('applyDateToAdd', '2026-11-01')->call('addApplyDate')
        ->set('applyUserIds', [$this->budi->id])
        ->call('previewApply');
    $detail = jdlTestIdText($component->html(), 'apply-preview-detail');

    expect(str_contains($detail, '@if'))->toBeFalse()
        ->and(str_contains($detail, '@endif'))->toBeFalse()
        ->and($detail)->toBe('0 di antaranya sudah berisi (0 orang tergeser). Hanya tanggal pada bulan Oktober 2026 yang terpengaruh; 1 tanggal di luar bulan ini diabaikan.');
});

it('Jadwal Shift — pratinjau Terapkan tanpa tanggal luar bulan tidak menyebut "diabaikan"', function () {
    $component = jdlAsAdminOnLine1($this)->call('openApply')
        ->set('applyShiftId', $this->pagi->id)->set('applyMode', 'dates')
        ->set('applyDateToAdd', '2026-10-02')->call('addApplyDate')
        ->set('applyUserIds', [$this->budi->id])
        ->call('previewApply');
    $detail = jdlTestIdText($component->html(), 'apply-preview-detail');

    expect(str_contains($detail, '@if'))->toBeFalse()
        ->and(str_contains($detail, 'diabaikan'))->toBeFalse()
        ->and($detail)->toBe('0 di antaranya sudah berisi (0 orang tergeser). Hanya tanggal pada bulan Oktober 2026 yang terpengaruh.');
});

it('Jadwal Shift — Terapkan: "Batal" pada konfirmasi tidak menulis apa pun', function () {
    jdlPlace($this->pagi, $this->citra, '2026-10-05');
    $before = jdlSnapshot();

    $component = jdlAsAdminOnLine1($this)->call('openApply')
        ->set('applyShiftId', $this->pagi->id)->set('applyMode', 'dates')
        ->set('applyDateToAdd', '2026-10-05')->call('addApplyDate')
        ->set('applyUserIds', [$this->budi->id])
        ->call('submitApply')->assertSet('applyConfirming', true)
        ->call('cancelApplyConfirm')->assertSet('applyConfirming', false);

    expect(jdlHas($component->html(), 'apply-confirm'))->toBeFalse()
        ->and(jdlHas($component->html(), 'apply-submit-button'))->toBeTrue()
        ->and(jdlSnapshot())->toBe($before);
});

it('Jadwal Shift — Terapkan: mode rentang dengan filter hari menulis hanya hari yang dipilih', function () {
    $component = jdlAsAdminOnLine1($this)->call('openApply')
        ->set('applyShiftId', $this->pagi->id)
        ->set('applyMode', 'range')
        ->set('applyFrom', '2026-10-01')->set('applyTo', '2026-10-14')
        ->set('applyWeekdays', [1])          // Mondays: the 5th and the 12th
        ->set('applyUserIds', [$this->budi->id])
        ->call('submitApply')
        ->assertSet('applyError', null);

    expect(jdlCell($this->pagi->id, '2026-10-05'))->toBe([$this->budi->id])
        ->and(jdlCell($this->pagi->id, '2026-10-12'))->toBe([$this->budi->id])
        ->and(jdlCount())->toBe(2)
        ->and(jdlTestIdText($component->html(), 'apply-result'))->toBe('2 sel diperbarui dari 2 sel sasaran.');
});

it('Jadwal Shift — Terapkan: tanpa shift / tanggal tidak valid / bukan calon menampilkan galat dan tidak menulis', function () {
    $engineer = User::factory()->role(UserRole::Engineer)->forBusinessUnit($this->millA)->create(['name' => 'Eko Engineer']);

    $component = jdlAsAdminOnLine1($this)->call('openApply')->set('applyMode', 'dates');

    // No shift chosen.
    $component->call('previewApply');
    expect($component->get('applyError'))->not->toBeNull()->and($component->get('applyPreview'))->toBeNull();

    // Malformed typed date is refused at the component, nothing added.
    $component->set('applyDateToAdd', '05-10-2026')->call('addApplyDate');
    expect($component->get('applyError'))->toBe('Tanggal harus berformat YYYY-MM-DD.')
        ->and($component->get('applyDates'))->toBe([]);

    // Non-candidate person: refused with the person's name and reason.
    $component->set('applyShiftId', $this->pagi->id)
        ->set('applyDateToAdd', '2026-10-05')->call('addApplyDate')
        ->set('applyUserIds', [$engineer->id])->call('submitApply');
    expect($component->get('applyError'))->toContain('Eko Engineer (role Engineer bukan Operator atau Supervisor)');

    expect(jdlCount())->toBe(0);
});

// ── Scenario: Terapkan dengan daftar orang kosong ────────────────────────

it('Jadwal Shift — Terapkan dengan daftar orang kosong: konfirmasi inline menyebut jumlah sel dan orang; setelah konfirmasi sel kosong', function () {
    jdlPlace($this->pagi, $this->budi, '2026-10-05');
    jdlPlace($this->pagi, $this->citra, '2026-10-06');
    jdlPlace($this->pagi, $this->citra, '2026-10-07');   // not a target

    $component = jdlAsAdminOnLine1($this)->call('openApply')
        ->set('applyShiftId', $this->pagi->id)->set('applyMode', 'dates')
        ->set('applyDateToAdd', '2026-10-05')->call('addApplyDate')
        ->set('applyDateToAdd', '2026-10-06')->call('addApplyDate')
        ->set('applyUserIds', [])
        ->call('submitApply')
        ->assertSet('applyConfirming', true);

    $html = $component->html();
    expect(jdlTestIdText($html, 'apply-confirm'))->toStartWith('Timpa 2 sel yang sudah berisi?')
        ->and(jdlTestIdText($html, 'apply-preview-detail'))->toContain('(2 orang tergeser)')
        ->and(jdlCount())->toBe(3);

    $component->call('submitApply');
    $after = $component->html();

    expect(jdlTestIdText($after, 'apply-result'))->toBe('2 sel diperbarui dari 2 sel sasaran.')
        ->and(jdlCellIsEmpty($after, $this->pagi->id, '2026-10-05'))->toBeTrue()
        ->and(jdlCellIsEmpty($after, $this->pagi->id, '2026-10-06'))->toBeTrue()
        ->and(jdlCellIsEmpty($after, $this->pagi->id, '2026-10-07'))->toBeFalse()
        ->and(jdlCell($this->pagi->id, '2026-10-07'))->toBe([$this->citra->id])
        ->and(jdlCount())->toBe(1);
});

// ── Scenario: Salin dari bulan sebelumnya ────────────────────────────────

it('Jadwal Shift — Salin dari bulan sebelumnya: pratinjau "N terisi, M dipertahankan, K dilewati", lalu hasil; sel berisi tidak berubah', function () {
    $inactive = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->millA)->create(['name' => 'Rina Nonaktif']);
    jdlPlace($this->pagi, $this->budi, '2026-10-05');       // copied
    jdlPlace($this->pagi, $this->budi, '2026-10-06');       // target already filled -> kept
    jdlPlace($this->pagi, $inactive, '2026-10-07');         // skipped
    jdlPlace($this->pagi, $this->citra, '2026-10-31');      // November has 30 days -> skipped
    jdlPlace($this->pagi, $this->dewi, '2026-11-06');
    $inactive->update(['is_active' => false]);
    $before = jdlSnapshot();

    $component = jdlAsAdminOnLine1($this, '2026-11')->call('previewCopy')->assertSet('copyError', null);
    $preview = $component->html();

    expect(jdlTestIdText($preview, 'copy-preview-text'))->toBe('1 terisi, 1 dipertahankan, 2 dilewati. Sel yang sudah berisi tidak ditimpa.')
        ->and(jdlHas($preview, 'copy-result'))->toBeFalse()
        ->and(jdlSnapshot())->toBe($before);                // preview wrote nothing

    $component->call('confirmCopy')->assertSet('copyPreview', null);
    $after = $component->html();

    expect(jdlTestIdText($after, 'copy-result'))->toBe('1 terisi, 1 dipertahankan, 2 dilewati')
        ->and(jdlHas($after, 'copy-preview'))->toBeFalse()
        ->and(jdlCell($this->pagi->id, '2026-11-05'))->toBe([$this->budi->id])
        ->and(jdlCell($this->pagi->id, '2026-11-06'))->toBe([$this->dewi->id])      // the filled cell is unchanged
        ->and(jdlChipNames($after, $this->pagi->id, '2026-11-06'))->toBe(['Dewi Penyelia'])
        ->and(jdlCount())->toBe(count($before) + 1);
});

it('Jadwal Shift — Salin: "Batal" pada pratinjau tidak menyalin', function () {
    jdlPlace($this->pagi, $this->budi, '2026-10-05');
    $before = jdlSnapshot();

    $component = jdlAsAdminOnLine1($this, '2026-11')->call('previewCopy')->call('cancelCopy');

    expect($component->get('copyPreview'))->toBeNull()
        ->and(jdlHas($component->html(), 'copy-preview'))->toBeFalse()
        ->and(jdlSnapshot())->toBe($before);
});

// ── Scenario: Salin saat bulan sebelumnya kosong ─────────────────────────

it('Jadwal Shift — Salin saat bulan sebelumnya kosong: "tidak ada yang disalin", grid tidak berubah', function () {
    jdlPlace($this->pagi, $this->budi, '2026-09-05');   // two months back
    $before = jdlSnapshot();

    $component = jdlAsAdminOnLine1($this, '2026-11');
    $summaryBefore = jdlTestIdText($component->html(), 'summary-text');
    $component->call('previewCopy');
    $html = $component->html();

    expect(jdlTestIdText($html, 'copy-result'))->toBe('Bulan Oktober 2026 belum punya penugasan, tidak ada yang disalin.')
        ->and(jdlHas($html, 'copy-preview'))->toBeFalse()
        ->and(jdlTestIdText($html, 'summary-text'))->toBe($summaryBefore)
        ->and(jdlSnapshot())->toBe($before);
});

// ── Scenario: Mengubah tanggal lampau ────────────────────────────────────

it('Jadwal Shift — Mengubah tanggal lampau: perubahan tersimpan tanpa pesan penguncian', function () {
    jdlPlace($this->pagi, $this->budi, '2026-10-01');   // frozen "today" is 10-12: 10-01 is past

    $component = jdlAsAdminOnLine1($this)
        ->call('openDay', '2026-10-01', $this->pagi->id)
        ->assertSet('panelError', null)
        ->set('daySelected.'.$this->pagi->id, [$this->citra->id])
        ->call('saveShift', $this->pagi->id)
        ->assertSet('panelError', null);
    $html = $component->html();

    expect(jdlCell($this->pagi->id, '2026-10-01'))->toBe([$this->citra->id])
        ->and(jdlChipNames($html, $this->pagi->id, '2026-10-01'))->toBe(['Citra'])
        ->and(jdlHas($html, 'panel-error'))->toBeFalse()
        ->and(jdlHas($html, 'jd-cell-btn-'.$this->pagi->id.'-2026-10-01'))->toBeTrue()   // still clickable
        ->and(jdlHas($html, 'jd-card-save-'.$this->pagi->id))->toBeTrue();          // and still saveable from the card
});

// ── Scenario: Dua pengatur mengubah sel yang sama ────────────────────────

it('Jadwal Shift — Dua pengatur mengubah sel yang sama: pesan "sel telah diubah orang lain", simpan terakhir berlaku', function () {
    $component = jdlAsAdminOnLine1($this)->call('openDay', '2026-10-05', $this->pagi->id);   // organiser B opens an EMPTY cell

    jdlPlace($this->pagi, $this->citra, '2026-10-05');    // organiser A saves in the meantime

    $component->set('daySelected.'.$this->pagi->id, [$this->budi->id])->call('saveShift', $this->pagi->id)->assertSet('panelError', null);
    $html = $component->html();

    expect(jdlTestIdText($html, 'jd-card-notice-'.$this->pagi->id))->toBe(
        'Sel telah diubah orang lain sejak Anda membukanya (isi sebelumnya: Citra). Isian Anda tetap disimpan dan menggantikannya.'
    )
        ->and(jdlCell($this->pagi->id, '2026-10-05'))->toBe([$this->budi->id])      // last write won
        ->and(jdlChipNames($html, $this->pagi->id, '2026-10-05'))->toBe(['Budi Santoso'])
        ->and($component->get('dayLoaded')[$this->pagi->id])->toBe([$this->budi->id]);   // refreshed, not stale
});

// ── Scenario: Bulan 28, 29, atau 30 hari ─────────────────────────────────

it('Jadwal Shift — Bulan 28, 29, atau 30 hari: navigasi bulan memuat ulang tepat sebanyak hari yang ada', function () {
    $component = jdlAsAdminOnLine1($this, '2026-03');

    $dayRows = fn (string $html) => array_map(
        fn (DOMElement $n) => substr($n->getAttribute('data-testid'), strlen('day-row-')),
        jdlNodes($html, '//tr[starts-with(@data-testid,"day-row-")]')
    );

    expect($dayRows($component->html()))->toHaveCount(31);

    $component->call('nextMonth')->assertSet('month', '2026-04');
    $april = $dayRows($component->html());
    expect($april)->toHaveCount(30)->and(end($april))->toBe('2026-04-30')
        ->and(jdlTestIdText($component->html(), 'month-name'))->toBe('April 2026');

    $component->set('month', '2026-03')->call('previousMonth')->assertSet('month', '2026-02');
    $feb = $dayRows($component->html());
    expect($feb)->toHaveCount(28)->and(end($feb))->toBe('2026-02-28')
        ->and(in_array('2026-02-29', $feb, true))->toBeFalse()
        ->and(in_array('2026-02-30', $feb, true))->toBeFalse()
        ->and(jdlHas($component->html(), 'day-row-2026-03-01'))->toBeFalse();

    $component->set('month', '2028-03')->call('previousMonth')->assertSet('month', '2028-02');
    expect($dayRows($component->html()))->toHaveCount(29);

    // Year rollover both ways, and "Bulan ini" returns to the (frozen) current month.
    $component->set('month', '2026-12')->call('nextMonth')->assertSet('month', '2027-01');
    $component->call('previousMonth')->assertSet('month', '2026-12');
    $component->call('thisMonth')->assertSet('month', '2026-10');
});

it('Jadwal Shift — berpindah bulan menutup panel dan hasil sebelumnya', function () {
    $component = jdlAsAdminOnLine1($this)
        ->call('openDay', '2026-10-05', $this->pagi->id)
        ->call('openApply')
        ->assertSet('applyOpen', true)
        ->call('nextMonth');

    $component->assertSet('openDate', null)->assertSet('openShiftId', null)->assertSet('applyOpen', false)
        ->assertSet('daySelected', [])->assertSet('dayLoaded', []);
    expect(jdlHas($component->html(), 'apply-card'))->toBeFalse();
});

// ── Scenario: Layar pada lebar sempit ────────────────────────────────────

it('Jadwal Shift — Layar pada lebar sempit: grid di wrapper yang dapat digulir, kolom tanggal sticky, tak ada font < 14px', function () {
    $html = jdlAsAdminOnLine1($this)->html();

    // The table sits INSIDE the scroll wrapper element.
    expect(jdlNodes($html, '//*[@data-testid="schedule-grid-scroll"]//table[@data-testid="schedule-grid"]'))->toHaveCount(1);

    // Every date cell (header + one per day) carries the sticky column class.
    $dateCols = jdlNodes($html, '//table[@data-testid="schedule-grid"]//*[contains(concat(" ", normalize-space(@class), " "), " jd-date-col ")]');
    expect($dateCols)->toHaveCount(1 + 31);

    // The declarations that make the above true, read from the page's own <style> block.
    preg_match('#<style>(.*?)</style>#s', $html, $m);
    $css = $m[1] ?? '';
    expect($css)->not->toBe('');

    preg_match('/\.jd-grid-scroll\s*\{([^}]*)\}/', $css, $scroll);
    preg_match('/\.jd-grid \.jd-date-col\s*\{([^}]*)\}/', $css, $sticky);
    expect($scroll[1] ?? '')->toContain('overflow: auto')
        ->and($sticky[1] ?? '')->toContain('position: sticky')
        ->and($sticky[1] ?? '')->toContain('left: 0');

    // Text floor: the page forces max(.., 14px) on everything, and no literal px font-size below 14 exists.
    expect($css)->toContain('.jd-page, .jd-page * { font-size: max(var(--font-size-sm), 14px); }');
    preg_match_all('/font-size:\s*(\d+(?:\.\d+)?)px/', $css, $sizes);
    foreach ($sizes[1] as $px) {
        expect((float) $px)->toBeGreaterThanOrEqual(14.0);
    }
});

// ── Scenario: Menugaskan user bukan calon peserta ditolak ────────────────

it('Jadwal Shift — user bukan calon lewat pemanggilan langsung ke komponen: pesan menyebut user dan alasan, sel tak berubah', function () {
    $engineer = User::factory()->role(UserRole::Engineer)->forBusinessUnit($this->millA)->create(['name' => 'Eko Engineer']);
    $mm = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->millA)->create(['name' => 'Mira MM']);
    jdlPlace($this->pagi, $this->citra, '2026-10-05');
    $before = jdlSnapshot();

    $component = jdlAsAdminOnLine1($this)->call('openDay', '2026-10-05', $this->pagi->id);

    // Engineer is NOT in the panel; the call goes straight to the component state, as a crafted request would.
    expect(jdlHas($component->html(), 'candidate-'.$engineer->id))->toBeFalse();

    $component->set('daySelected.'.$this->pagi->id, [$this->budi->id, $engineer->id, $this->bayu->id, $mm->id])->call('saveShift', $this->pagi->id);

    $error = $component->get('panelError');
    expect($error)->toContain('Eko Engineer (role Engineer bukan Operator atau Supervisor)')
        ->and($error)->toContain('Bayu Beta (bertugas di mill lain)')
        ->and($error)->toContain('Mira MM (role '.UserRole::MillManagement->label().' bukan Operator atau Supervisor)')
        ->and(jdlTestIdText($component->html(), 'panel-error'))->toBe($error)
        ->and($component->get('panelNotice'))->toBeNull()
        // the panel stays open with the user's selection intact
        ->and($component->get('openShiftId'))->toBe($this->pagi->id)
        ->and($component->get('openDate'))->toBe('2026-10-05')
        ->and($component->get('daySelected')[$this->pagi->id])->toBe([$this->budi->id, $engineer->id, $this->bayu->id, $mm->id])
        ->and(jdlSnapshot())->toBe($before);
});

// ── Scenario: Mengisi shift terhapus pada tanggal sesudah penutupan ──────

it('Jadwal Shift — simpan sel shift tertutup pada tanggal sesudah penutupan: ditolak "sudah dihapus", histori utuh', function () {
    jdlPlace($this->siang, $this->budi, '2026-10-05');
    jdlClose($this->siang, '2026-10-15 08:00:00');
    $before = jdlSnapshot();

    $component = jdlAsAdminOnLine1($this)
        // A crafted call: the grid never offers this cell, so the day-card state is set directly.
        ->set('openDate', '2026-10-20')
        ->set('dayLoaded', [$this->siang->id => []])
        ->set('daySelected', [$this->siang->id => [$this->citra->id]])
        ->call('saveShift', $this->siang->id);

    $error = $component->get('panelError');
    expect($error)->toContain('sudah dihapus')
        ->and($error)->toContain('Siang')
        ->and(jdlSnapshot())->toBe($before);

    $html = $component->html();
    expect(jdlChipNames($html, $this->siang->id, '2026-10-05'))->toBe(['Budi Santoso']);
});

// ── Scenario: Membuka tanpa memilih Production Line lewat permintaan langsung

it('Jadwal Shift — production_line_id kosong atau "all" pada komponen: tanpa grid, diminta memilih satu Production Line', function (string $value) {
    $component = Livewire::actingAs($this->admin)->test(JadwalShift::class)
        ->set('month', '2026-10')
        ->set('businessUnitId', $this->millA->id)
        ->set('productionLineId', $value);
    $html = $component->html();

    $component->assertSet('productionLineId', '');
    expect(jdlHas($html, 'schedule-grid'))->toBeFalse()
        ->and(jdlHas($html, 'pick-line-guide'))->toBeTrue()
        ->and(jdlTestIdText($html, 'pick-line-guide'))->toContain('Pilih Production Line');
})->with(['kosong' => [''], 'all' => ['all'], 'bukan uuid' => ['pl-001']]);

it('Jadwal Shift — mengganti mill mengosongkan line dan menutup panel', function () {
    $component = jdlAsAdminOnLine1($this)->call('openDay', '2026-10-05', $this->pagi->id);

    $component->set('businessUnitId', $this->millB->id)
        ->assertSet('productionLineId', '')
        ->assertSet('openDate', null)
        ->assertSet('openShiftId', null);
    expect(jdlHas($component->html(), 'schedule-grid'))->toBeFalse();
});

// ── "+N lagi" expander ───────────────────────────────────────────────────

it('Jadwal Shift — sel dengan banyak petugas: tabel menampilkan semuanya (tanpa "+N lagi"), kalender memotong jadi 2 + "+N lagi" yang membuka semuanya', function () {
    $extra1 = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->millA)->create(['name' => 'Eka Kelima']);
    $extra2 = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->millA)->create(['name' => 'Fajar Keenam']);
    foreach ([$this->budi, $this->citra, $this->dewi, $extra1, $extra2] as $person) {
        jdlPlace($this->pagi, $person, '2026-10-05');
    }
    jdlPlace($this->pagi, $this->budi, '2026-10-06');   // a 1-person cell has no expander

    // TABLE: every name is a chip, ordered by name, and there is no expander at all.
    $table = jdlAsAdminOnLine1($this);
    expect(jdlChipNames($table->html(), $this->pagi->id, '2026-10-05'))
        ->toBe(['Budi Santoso', 'Citra', 'Dewi Penyelia', 'Eka Kelima', 'Fajar Keenam'])
        ->and(jdlNodes($table->html(), '//*[starts-with(@data-testid,"jd-more-")]'))->toBe([]);

    // CALENDAR: 2 names + "+3 lagi".
    $component = jdlAsAdminCalendar($this);
    $lineId = 'jd-line-2026-10-05-'.$this->pagi->id;
    $moreId = 'jd-more-2026-10-05-'.$this->pagi->id;
    $names = fn (string $html) => jdlText(jdlNodes($html, '//*[@data-testid="'.$lineId.'"]/span[1]')[0]);

    expect($names($component->html()))->toBe('Budi Santoso, Citra')
        ->and(jdlTestIdText($component->html(), $moreId))->toBe('+3 lagi')
        ->and(jdlHas($component->html(), 'jd-more-2026-10-06-'.$this->pagi->id))->toBeFalse();

    $key = 'cal|'.$this->pagi->id.'|2026-10-05';
    $component->call('toggleCell', $key);
    expect($names($component->html()))->toBe('Budi Santoso, Citra, Dewi Penyelia, Eka Kelima, Fajar Keenam')
        ->and(jdlTestIdText($component->html(), $moreId))->toBe('sembunyikan');

    $component->call('toggleCell', $key);
    expect($names($component->html()))->toBe('Budi Santoso, Citra')
        ->and(jdlTestIdText($component->html(), $moreId))->toBe('+3 lagi');
});

// ── No disabled / readonly inputs ────────────────────────────────────────

it('Jadwal Shift — tidak ada input atau select yang disabled/readonly (tanggal dapat diketik sekaligus dipilih)', function () {
    $component = jdlAsAdminOnLine1($this)->call('openDay', '2026-10-05', $this->pagi->id)->call('openApply');
    $html = $component->html();

    expect(jdlNodes($html, '//input[@disabled or @readonly] | //select[@disabled or @readonly] | //textarea[@disabled or @readonly]'))->toBe([])
        // The date fields are real <input type="date">: typable AND pickable.
        ->and(jdlNodes($html, '//input[@data-testid="apply-from"]')[0]->getAttribute('type'))->toBe('date')
        ->and(jdlNodes($html, '//input[@data-testid="apply-to"]')[0]->getAttribute('type'))->toBe('date');
});

// ═════════════════════════════════════════════════════════════════════════
// Spec v2 — tampilan Kalender / Tabel + kartu tanggal di samping
// (10 test_scenarios baru + pengaman tambahan). Kalender adalah tampilan
// bawaan; mengganti tampilan tidak pernah menulis.
// ═════════════════════════════════════════════════════════════════════════

/** @return list<DOMElement> calendar day boxes (jd-day-YYYY-MM-DD), excluding jd-day-card. */
function jdlDayBoxes(string $html): array
{
    return array_values(array_filter(
        jdlNodes($html, '//*[starts-with(@data-testid,"jd-day-")]'),
        fn (DOMElement $n) => preg_match('/^jd-day-\d{4}-\d{2}-\d{2}$/', $n->getAttribute('data-testid')) === 1
    ));
}

/** The text of the first <style> block of the page. */
function jdlCss(string $html): string
{
    preg_match('#<style>(.*?)</style>#s', $html, $m);

    return $m[1] ?? '';
}

it('Jadwal Shift — Kalender adalah tampilan bawaan: tujuh header, satu kotak per tanggal, satu baris per shift, pemilih menandai Kalender', function () {
    jdlPlace($this->pagi, $this->budi, '2026-10-05');
    jdlPlace($this->siang, $this->citra, '2026-10-05');

    // viewMode is NOT bound to the URL: a ?viewMode=table query string does not switch the view.
    $component = Livewire::withQueryParams(['viewMode' => 'table'])->actingAs($this->admin)->test(JadwalShift::class)
        ->set('month', '2026-10')->set('businessUnitId', $this->millA->id)->set('productionLineId', $this->lineA1->id)
        ->assertSet('viewMode', 'calendar');
    $html = $component->html();

    expect(jdlHas($html, 'jd-calendar'))->toBeTrue()
        ->and(jdlHas($html, 'schedule-grid'))->toBeFalse();

    // Seven weekday headers, exact labels, Monday first.
    $heads = array_map(fn (DOMElement $n) => [$n->getAttribute('data-testid'), jdlText($n)], jdlNodes($html, '//*[starts-with(@data-testid,"jd-cal-head-")]'));
    expect($heads)->toBe([
        ['jd-cal-head-Sen', 'Sen'], ['jd-cal-head-Sel', 'Sel'], ['jd-cal-head-Rab', 'Rab'], ['jd-cal-head-Kam', 'Kam'],
        ['jd-cal-head-Jum', 'Jum'], ['jd-cal-head-Sab', 'Sab'], ['jd-cal-head-Min', 'Min'],
    ]);

    // Exactly one box per day of October (31), each holding one line per live shift (2).
    $boxes = jdlDayBoxes($html);
    expect($boxes)->toHaveCount(31)
        ->and($boxes[0]->getAttribute('data-testid'))->toBe('jd-day-2026-10-01')
        ->and($boxes[30]->getAttribute('data-testid'))->toBe('jd-day-2026-10-31');
    foreach ($boxes as $box) {
        $date = substr($box->getAttribute('data-testid'), strlen('jd-day-'));
        expect(jdlHas($html, 'jd-line-'.$date.'-'.$this->pagi->id))->toBeTrue("$date tanpa baris Pagi")
            ->and(jdlHas($html, 'jd-line-'.$date.'-'.$this->siang->id))->toBeTrue("$date tanpa baris Siang");
        expect(jdlNodes($html, '//*[@data-testid="jd-day-'.$date.'"]//*[starts-with(@data-testid,"jd-line-")]'))->toHaveCount(2);
    }

    // A line = shift name + its people; an empty one says so.
    expect(jdlTestIdText($html, 'jd-line-2026-10-05-'.$this->pagi->id))->toBe('Pagi Budi Santoso')
        ->and(jdlTestIdText($html, 'jd-line-2026-10-05-'.$this->siang->id))->toBe('Siang Citra')
        ->and(jdlTestIdText($html, 'jd-line-2026-10-06-'.$this->pagi->id))->toBe('Pagi belum ada petugas')
        ->and(jdlTestIdText($html, 'jd-empty-2026-10-06-'.$this->pagi->id))->toBe('belum ada petugas');

    // The switch marks Calendar active with TEXT (not colour only) and aria-pressed.
    $cal = jdlNodes($html, '//button[@data-testid="jd-view-calendar"]')[0];
    $tab = jdlNodes($html, '//button[@data-testid="jd-view-table"]')[0];
    expect(jdlText($cal))->toBe('Kalender (aktif)')
        ->and($cal->getAttribute('aria-pressed'))->toBe('true')
        ->and(jdlText($tab))->toBe('Tabel')
        ->and($tab->getAttribute('aria-pressed'))->toBe('false');
});

it('Jadwal Shift — Berganti ke tampilan Tabel: isi, ringkasan, mill/line/bulan sama; urutan kolom menurut position; tidak menulis apa pun', function () {
    jdlPlace($this->pagi, $this->budi, '2026-10-05');
    jdlPlace($this->siang, $this->citra, '2026-10-05');
    jdlPlace($this->pagi, $this->dewi, '2026-10-06');
    $rows = jdlSnapshot();

    $component = jdlAsAdminCalendar($this);
    $calendarSummary = jdlTestIdText($component->html(), 'summary-text');
    expect($calendarSummary)->toBe('3 dari 62 sel sudah terisi');

    $component->call('setViewMode', 'table')->assertSet('viewMode', 'table');
    $html = $component->html();

    $component->assertSet('businessUnitId', $this->millA->id)->assertSet('productionLineId', $this->lineA1->id)->assertSet('month', '2026-10');
    $heads = array_map(fn (DOMElement $n) => $n->getAttribute('data-testid'), jdlNodes($html, '//thead//th[starts-with(@data-testid,"shift-head-")]'));
    expect(jdlHas($html, 'jd-calendar'))->toBeFalse()
        ->and(jdlHas($html, 'schedule-grid'))->toBeTrue()
        ->and($heads)->toBe(['shift-head-'.$this->pagi->id, 'shift-head-'.$this->siang->id])
        ->and(jdlNodes($html, '//tr[starts-with(@data-testid,"day-row-")]'))->toHaveCount(31)
        ->and(jdlTestIdText($html, 'summary-text'))->toBe($calendarSummary)
        ->and(jdlChipNames($html, $this->pagi->id, '2026-10-05'))->toBe(['Budi Santoso'])
        ->and(jdlChipNames($html, $this->siang->id, '2026-10-05'))->toBe(['Citra'])
        ->and(jdlChipNames($html, $this->pagi->id, '2026-10-06'))->toBe(['Dewi Penyelia'])
        ->and(jdlCellIsEmpty($html, $this->siang->id, '2026-10-06'))->toBeTrue()
        ->and(jdlSnapshot())->toBe($rows);

    // The switch flips its text marker.
    expect(jdlText(jdlNodes($html, '//button[@data-testid="jd-view-table"]')[0]))->toBe('Tabel (aktif)')
        ->and(jdlText(jdlNodes($html, '//button[@data-testid="jd-view-calendar"]')[0]))->toBe('Kalender');

    // Back to the calendar: same summary, same rows. An unknown mode is ignored, not stored.
    $component->call('setViewMode', 'kanban')->assertSet('viewMode', 'table');
    $component->call('setViewMode', 'calendar')->assertSet('viewMode', 'calendar');
    expect(jdlTestIdText($component->html(), 'summary-text'))->toBe($calendarSummary)
        ->and(jdlTestIdText($component->html(), 'jd-line-2026-10-05-'.$this->pagi->id))->toBe('Pagi Budi Santoso')
        ->and(jdlSnapshot())->toBe($rows);
});

it('Jadwal Shift — Mengeklik sel tabel membuka kartu tanggal: tanpa kontrol "Atur"; menyimpan menulis tepat satu sel', function () {
    $component = jdlAsAdminOnLine1($this);
    $html = $component->html();

    // No "Atur" control anywhere in the table: no button whose own text is exactly "Atur",
    // and none of the old per-cell edit hooks.
    expect(jdlNodes($html, '//button[normalize-space(.)="Atur"]'))->toBe([])
        ->and(jdlNodes($html, '//*[starts-with(@data-testid,"edit-")]'))->toBe([])
        ->and(jdlNodes($html, '//td[starts-with(@data-testid,"cell-")]//button'))->toHaveCount(62)   // the cell itself is the click target
        ->and(jdlHas($html, 'jd-day-card'))->toBeFalse()
        ->and(jdlHas($html, 'jd-card-placeholder'))->toBeTrue();

    $before = jdlCount();
    $component->call('openDay', '2026-10-05', $this->pagi->id)
        ->assertSet('openDate', '2026-10-05')
        ->assertSet('openShiftId', $this->pagi->id);
    $card = $component->html();

    // The card sits beside the grid (grid still rendered), titled with the date, listing ALL shifts of that day.
    expect(jdlHas($card, 'schedule-grid'))->toBeTrue()
        ->and(jdlNodes($card, '//*[@data-testid="jd-day-card"]')[0]->getAttribute('data-date'))->toBe('2026-10-05')
        ->and(jdlTestIdText($card, 'jd-card-title'))->toBe('Sen 5 Oktober 2026')
        ->and(jdlHas($card, 'jd-card-shift-'.$this->pagi->id))->toBeTrue()
        ->and(jdlHas($card, 'jd-card-shift-'.$this->siang->id))->toBeTrue()
        // Pagi is the opened one: its toggle is expanded, Siang's is not.
        ->and(jdlNodes($card, '//button[@data-testid="jd-card-shift-toggle-'.$this->pagi->id.'"]')[0]->getAttribute('aria-expanded'))->toBe('true')
        ->and(jdlNodes($card, '//button[@data-testid="jd-card-shift-toggle-'.$this->siang->id.'"]')[0]->getAttribute('aria-expanded'))->toBe('false')
        ->and(jdlHas($card, 'jd-card-save-'.$this->pagi->id))->toBeTrue()
        ->and(jdlHas($card, 'jd-card-save-'.$this->siang->id))->toBeFalse()
        ->and(jdlCount())->toBe($before);   // opening writes nothing

    // Check people on BOTH shifts, but save only Pagi: exactly one cell is written, Siang is untouched.
    $component->set('daySelected.'.$this->pagi->id, [$this->budi->id])
        ->set('daySelected.'.$this->siang->id, [$this->citra->id])
        ->call('saveShift', $this->pagi->id)
        ->assertSet('panelError', null);

    expect(jdlSnapshot())->toBe([$this->pagi->id.'|'.$this->budi->id.'|2026-10-05'])
        ->and(jdlCell($this->siang->id, '2026-10-05'))->toBe([])
        ->and($component->get('daySelected')[$this->siang->id])->toBe([$this->citra->id])   // still pending, still unsaved
        ->and(jdlChipNames($component->html(), $this->pagi->id, '2026-10-05'))->toBe(['Budi Santoso'])
        ->and(jdlTestIdText($component->html(), 'jd-card-notice-'.$this->pagi->id))->toBe('Petugas sel berhasil disimpan.')
        ->and(jdlHas($component->html(), 'jd-card-dirty-'.$this->siang->id))->toBeTrue()
        ->and(jdlHas($component->html(), 'jd-card-dirty-'.$this->pagi->id))->toBeFalse();
});

it('Jadwal Shift — Mengeklik kotak kalender membuka kartu tanggal: kotak bertanda "Dibuka", semua shift hari itu ada di kartu', function () {
    jdlPlace($this->pagi, $this->budi, '2026-10-05');

    $component = jdlAsAdminCalendar($this);
    expect(jdlNodes($component->html(), '//*[starts-with(@data-testid,"jd-opened-")]'))->toBe([]);

    $component->call('openDay', '2026-10-05')->assertSet('openDate', '2026-10-05')
        ->assertSet('openShiftId', $this->pagi->id);          // no shift given: the first live one (by position) opens
    $html = $component->html();

    $opened = jdlNodes($html, '//*[starts-with(@data-testid,"jd-opened-")]');
    expect($opened)->toHaveCount(1)
        ->and($opened[0]->getAttribute('data-testid'))->toBe('jd-opened-2026-10-05')
        ->and(jdlText($opened[0]))->toBe('Dibuka')
        ->and(jdlNodes($html, '//*[@data-testid="jd-day-card"]')[0]->getAttribute('data-date'))->toBe('2026-10-05')
        ->and(jdlHas($html, 'jd-calendar'))->toBeTrue()                       // the calendar stays visible beside the card
        ->and(jdlHas($html, 'jd-card-shift-toggle-'.$this->pagi->id))->toBeTrue()
        ->and(jdlHas($html, 'jd-card-shift-toggle-'.$this->siang->id))->toBeTrue()
        ->and(jdlNodes($html, '//*[@data-testid="jd-card-shift-'.$this->pagi->id.'"]//input[@type="checkbox"][@data-testid="candidate-'.$this->budi->id.'"]'))->toHaveCount(1);

    // The card lets Siang be arranged too (switch the open shift, then save it).
    $component->call('selectShift', $this->siang->id)
        ->set('daySelected.'.$this->siang->id, [$this->citra->id])
        ->call('saveShift', $this->siang->id);
    expect(jdlCell($this->siang->id, '2026-10-05'))->toBe([$this->citra->id])
        ->and(jdlCell($this->pagi->id, '2026-10-05'))->toBe([$this->budi->id]);

    // Opening another date moves the marker; closing removes it.
    $component->call('openDay', '2026-10-06');
    expect(jdlHas($component->html(), 'jd-opened-2026-10-05'))->toBeFalse()
        ->and(jdlHas($component->html(), 'jd-opened-2026-10-06'))->toBeTrue();
    $component->call('closeDay');
    expect(jdlNodes($component->html(), '//*[starts-with(@data-testid,"jd-opened-")]'))->toBe([])
        ->and(jdlHas($component->html(), 'jd-day-card'))->toBeFalse();
});

it('Jadwal Shift — Sel shift tertutup hanya menampilkan histori: tanpa daftar calon, tanpa checkbox, tanpa tombol simpan', function () {
    jdlPlace($this->siang, $this->budi, '2026-10-05');
    jdlClose($this->siang, '2026-10-15 08:00:00');
    $rows = jdlSnapshot();

    // Calendar: the closed shift's line shows ONLY where it has names, with the badge; after closure no line at all.
    $cal = jdlAsAdminCalendar($this);
    $html = $cal->html();
    expect(jdlHas($html, 'jd-line-2026-10-05-'.$this->siang->id))->toBeTrue()
        ->and(jdlText(jdlNodes($html, '//*[@data-testid="jd-line-2026-10-05-'.$this->siang->id.'"]//span[contains(concat(" ", normalize-space(@class), " "), " badge ")]')[0]))->toBe('shift sudah dihapus')
        ->and(jdlHas($html, 'jd-line-2026-10-06-'.$this->siang->id))->toBeFalse()      // before closure but nobody there: omitted
        ->and(jdlHas($html, 'jd-line-2026-10-20-'.$this->siang->id))->toBeFalse()      // after closure: omitted
        ->and(jdlHas($html, 'jd-line-2026-10-06-'.$this->pagi->id))->toBeTrue();       // live shifts always show

    // Clicking the calendar box, then the closed shift: history mode.
    $cal->call('openDay', '2026-10-05')->call('selectShift', $this->siang->id);
    $card = $cal->html();
    expect(jdlTestIdText($card, 'jd-card-closed-'.$this->siang->id))->toBe('shift sudah dihapus')
        ->and(jdlTestIdText($card, 'jd-card-history-'.$this->siang->id))->toBe('Budi Santoso. Shift sudah dihapus; hanya dapat dibaca.')
        ->and(jdlHas($card, 'jd-card-save-'.$this->siang->id))->toBeFalse()
        ->and(jdlHas($card, 'jd-card-clear-'.$this->siang->id))->toBeFalse()
        ->and(jdlHas($card, 'jd-card-dirty-'.$this->siang->id))->toBeFalse()
        ->and(jdlNodes($card, '//*[@data-testid="jd-card-shift-'.$this->siang->id.'"]//input'))->toBe([])
        // The candidate search/list never appears inside the closed shift's section. (The card keeps the
        // first LIVE shift as its editor, so they exist - but under Pagi, not under Siang.)
        ->and(jdlNodes($card, '//*[@data-testid="jd-card-shift-'.$this->siang->id.'"]//*[@data-testid="panel-search" or @data-testid="panel-list"]'))->toBe([])
        ->and(jdlNodes($card, '//*[@data-testid="jd-card-shift-'.$this->pagi->id.'"]//*[@data-testid="panel-list"]'))->toHaveCount(1)
        ->and(jdlSnapshot())->toBe($rows);

    // Clicking a table cell of the closed shift: same.
    $table = jdlAsAdminOnLine1($this)->call('openDay', '2026-10-05', $this->siang->id);
    $card = $table->html();
    expect(jdlHas($card, 'jd-card-history-'.$this->siang->id))->toBeTrue()
        ->and(jdlHas($card, 'jd-card-save-'.$this->siang->id))->toBeFalse()
        ->and(jdlNodes($card, '//*[@data-testid="jd-card-shift-'.$this->siang->id.'"]//input'))->toBe([])
        ->and(jdlSnapshot())->toBe($rows);

    // The write path agrees with the UI: the API-level rule SHIFT_CLOSED is covered in ShiftScheduleApiTest;
    // a crafted component call on a closed-shift cell writes nothing either.
    $table->call('saveShift', $this->siang->id)->call('clearShift', $this->siang->id);
    expect(jdlSnapshot())->toBe($rows);
});

it('Jadwal Shift — Kotak kalender di luar bulan tidak dapat diklik: tepat 3 kotak kosong di depan untuk Oktober 2026, tanpa aksi', function () {
    $component = jdlAsAdminCalendar($this);
    $html = $component->html();

    // 1 Oct 2026 is a Thursday -> exactly Mon, Tue, Wed are blank ahead of it.
    $lead = jdlNodes($html, '//*[starts-with(@data-testid,"jd-blank-lead-")]');
    expect(array_map(fn (DOMElement $n) => $n->getAttribute('data-testid'), $lead))->toBe(['jd-blank-lead-0', 'jd-blank-lead-1', 'jd-blank-lead-2']);

    // 31 days + 3 leading = 34 cells -> padded to 35 by exactly 1 trailing blank.
    expect(jdlNodes($html, '//*[starts-with(@data-testid,"jd-blank-trail-")]'))->toHaveCount(1);

    // The first real box follows the third blank in document order.
    expect(jdlNodes($html, '//*[@data-testid="jd-blank-lead-2"]/following-sibling::*[1]')[0]->getAttribute('data-testid'))->toBe('jd-day-2026-10-01');

    // A blank is inert: no click action, no key action, not a button, not focusable, hidden from AT.
    foreach (array_merge($lead, jdlNodes($html, '//*[starts-with(@data-testid,"jd-blank-trail-")]')) as $blank) {
        foreach (['wire:click', 'wire:keydown.enter.self', 'role', 'tabindex'] as $attr) {
            expect($blank->hasAttribute($attr))->toBeFalse($blank->getAttribute('data-testid')." punya atribut $attr");
        }
        expect($blank->getAttribute('aria-hidden'))->toBe('true')
            ->and(jdlText($blank))->toBe('');
    }
    // ...while a real box IS an actionable, focusable button.
    $real = jdlNodes($html, '//*[@data-testid="jd-day-2026-10-01"]')[0];
    expect($real->getAttribute('role'))->toBe('button')->and($real->getAttribute('tabindex'))->toBe('0')
        ->and($real->getAttribute('wire:click'))->toBe("openDay('2026-10-01')");

    // Server side: a date outside the displayed month cannot open a card even by a crafted call.
    $component->call('openDay', '2026-09-30')->assertSet('openDate', null);
    $component->call('openDay', '2026-11-01')->assertSet('openDate', null);
    expect(jdlHas($component->html(), 'jd-day-card'))->toBeFalse();

    // Another month: November 2026 starts on a Sunday -> 6 leading blanks.
    $component->call('nextMonth');
    expect(jdlNodes($component->html(), '//*[starts-with(@data-testid,"jd-blank-lead-")]'))->toHaveCount(6);
});

it('Jadwal Shift — Kotak kalender padat memakai "+N lagi": N tepat, tidak ada nama hilang dari DOM', function () {
    $people = [$this->budi, $this->citra, $this->dewi];
    foreach (['Eka Kelima', 'Fajar Keenam', 'Gita Ketujuh'] as $name) {
        $people[] = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->millA)->create(['name' => $name]);
    }
    foreach ($people as $person) {
        jdlPlace($this->pagi, $person, '2026-10-05');          // 6 people on Pagi
    }
    jdlPlace($this->siang, $this->budi, '2026-10-05');
    jdlPlace($this->siang, $this->citra, '2026-10-05');
    jdlPlace($this->siang, $this->dewi, '2026-10-05');         // 3 people on Siang
    $allPagi = ['Budi Santoso', 'Citra', 'Dewi Penyelia', 'Eka Kelima', 'Fajar Keenam', 'Gita Ketujuh'];

    $component = jdlAsAdminCalendar($this);
    $names = fn (string $html, string $shiftId) => jdlText(jdlNodes($html, '//*[@data-testid="jd-line-2026-10-05-'.$shiftId.'"]/span[1]')[0]);

    // 2 shown + "+4 lagi" = 6; Siang: 2 shown + "+1 lagi" = 3.
    expect($names($component->html(), $this->pagi->id))->toBe('Budi Santoso, Citra')
        ->and(jdlTestIdText($component->html(), 'jd-more-2026-10-05-'.$this->pagi->id))->toBe('+4 lagi')
        ->and($names($component->html(), $this->siang->id))->toBe('Budi Santoso, Citra')
        ->and(jdlTestIdText($component->html(), 'jd-more-2026-10-05-'.$this->siang->id))->toBe('+1 lagi')
        // days with <= 2 people have no expander
        ->and(jdlHas($component->html(), 'jd-more-2026-10-06-'.$this->pagi->id))->toBeFalse();

    // Expanding Pagi reveals every name, in order, and leaves Siang collapsed.
    $component->call('toggleCell', 'cal|'.$this->pagi->id.'|2026-10-05');
    expect($names($component->html(), $this->pagi->id))->toBe(implode(', ', $allPagi))
        ->and(jdlTestIdText($component->html(), 'jd-more-2026-10-05-'.$this->pagi->id))->toBe('sembunyikan')
        ->and(jdlTestIdText($component->html(), 'jd-more-2026-10-05-'.$this->siang->id))->toBe('+1 lagi');

    // No name is lost: the table view lists all 6 chips of the same cell.
    $component->call('setViewMode', 'table');
    expect(jdlChipNames($component->html(), $this->pagi->id, '2026-10-05'))->toBe($allPagi);
});

it('Jadwal Shift — Pindah bulan menutup kartu tanggal tanpa menulis (juga pilihan yang belum disimpan dibuang)', function () {
    $component = jdlAsAdminCalendar($this);
    $component->call('openDay', '2026-10-05', $this->pagi->id)
        ->set('daySelected.'.$this->pagi->id, [$this->budi->id]);
    expect(jdlHas($component->html(), 'jd-day-card'))->toBeTrue();
    $before = jdlCount();

    $component->call('nextMonth')->assertSet('month', '2026-11')
        ->assertSet('openDate', null)->assertSet('openShiftId', null)
        ->assertSet('daySelected', [])->assertSet('dayLoaded', [])
        ->assertSet('pendingDate', null);
    $html = $component->html();

    expect(jdlHas($html, 'jd-day-card'))->toBeFalse()
        ->and(jdlHas($html, 'jd-card-placeholder'))->toBeTrue()
        ->and(jdlNodes($html, '//*[starts-with(@data-testid,"jd-opened-")]'))->toBe([])
        ->and(jdlCount())->toBe($before)->and($before)->toBe(0);

    // Previous month and "Bulan ini" close it too.
    $component->call('openDay', '2026-11-03')->assertSet('openDate', '2026-11-03')->call('previousMonth')->assertSet('openDate', null);
    $component->call('openDay', '2026-10-03')->assertSet('openDate', '2026-10-03')->call('nextMonth')->call('thisMonth')->assertSet('openDate', null);
});

it('Jadwal Shift — Mengeklik tanggal lain dengan perubahan belum disimpan: konfirmasi inline, tidak berpindah diam-diam, tidak menulis', function () {
    $component = jdlAsAdminCalendar($this)->call('openDay', '2026-10-05', $this->pagi->id);

    // Clean card: switching date needs no confirmation.
    $component->call('openDay', '2026-10-07')->assertSet('openDate', '2026-10-07')->assertSet('pendingDate', null);
    expect(jdlHas($component->html(), 'jd-discard-confirm'))->toBeFalse();

    $component->call('openDay', '2026-10-05', $this->pagi->id)
        ->set('daySelected.'.$this->pagi->id, [$this->budi->id]);
    expect(jdlTestIdText($component->html(), 'jd-card-dirty-'.$this->pagi->id))->toBe('belum disimpan')
        ->and(jdlHas($component->html(), 'jd-discard-confirm'))->toBeFalse();

    // Click another date: the card does NOT move; the inline confirmation appears.
    $component->call('openDay', '2026-10-06')
        ->assertSet('openDate', '2026-10-05')
        ->assertSet('pendingDate', '2026-10-06');
    $html = $component->html();
    expect(jdlHas($html, 'jd-discard-confirm'))->toBeTrue()
        ->and(jdlText(jdlNodes($html, '//button[@data-testid="jd-discard-yes"]')[0]))->toBe('Ya, buang perubahan')
        ->and(jdlText(jdlNodes($html, '//button[@data-testid="jd-discard-no"]')[0]))->toBe('Tetap di sini')
        ->and(jdlNodes($html, '//*[@data-testid="jd-day-card"]')[0]->getAttribute('data-date'))->toBe('2026-10-05')
        ->and(jdlCount())->toBe(0);

    // "Tetap di sini": still on 5 Oct, selection intact, confirmation gone.
    $component->call('cancelDiscard')->assertSet('openDate', '2026-10-05')->assertSet('pendingDate', null);
    expect($component->get('daySelected')[$this->pagi->id])->toBe([$this->budi->id])
        ->and(jdlHas($component->html(), 'jd-discard-confirm'))->toBeFalse();

    // Click again and confirm: moves to 6 Oct, the change is discarded, nothing was written.
    $component->call('openDay', '2026-10-06')->call('confirmDiscard')
        ->assertSet('openDate', '2026-10-06')->assertSet('pendingDate', null);
    expect($component->get('daySelected')[$this->pagi->id])->toBe([])
        ->and(jdlHas($component->html(), 'jd-discard-confirm'))->toBeFalse()
        ->and(jdlNodes($component->html(), '//*[@data-testid="jd-day-card"]')[0]->getAttribute('data-date'))->toBe('2026-10-06')
        ->and(jdlCount())->toBe(0);

    // Re-selecting the saved value is NOT dirty (no false alarm); and switching shift on the same date keeps the selection.
    $component->set('daySelected.'.$this->pagi->id, [$this->budi->id])->call('selectShift', $this->siang->id);
    expect($component->get('daySelected')[$this->pagi->id])->toBe([$this->budi->id])
        ->and($component->instance()->isDirty($this->pagi->id))->toBeTrue()
        ->and($component->instance()->isDirty($this->siang->id))->toBeFalse();
});

it('Jadwal Shift — Kalender pada layar sempit menjadi daftar per minggu: aturan breakpoint ada, font >= 14px', function () {
    $css = jdlCss(jdlAsAdminCalendar($this)->html());
    expect($css)->not->toBe('');

    // Wide layout: seven columns.
    preg_match('/\.jd-cal\s*\{([^}]*)\}/', $css, $wide);
    expect($wide[1] ?? '')->toContain('grid-template-columns: repeat(7,');

    // Narrow layout: a max-width media block turns the grid into a single-column list and drops the weekday heads/blanks.
    expect(preg_match('/@media \(max-width: 768px\) \{(.*?)\n        \}/s', $css, $m))->toBe(1);
    $narrow = $m[1];
    expect($narrow)->toContain('.jd-cal { grid-template-columns: 1fr;')
        ->and($narrow)->toContain('min-width: 0')
        ->and($narrow)->toContain('.jd-cal-head, .jd-cal-blank { display: none; }')
        ->and($narrow)->toContain('.jd-cal-scroll { overflow-x: visible; }');

    // Text floor: forced by the page-wide rule, and no literal px font-size under 14 anywhere (incl. the narrow block).
    expect($css)->toContain('.jd-page, .jd-page * { font-size: max(var(--font-size-sm), 14px); }');
    preg_match_all('/font-size:\s*(\d+(?:\.\d+)?)px/', $css, $sizes);
    foreach ($sizes[1] as $px) {
        expect((float) $px)->toBeGreaterThanOrEqual(14.0);
    }
    expect(preg_match('/font-size:\s*(?:[0-9.]+)(?:px|pt|em|rem)/', $narrow))->toBe(0);   // narrow block sets no size of its own
    // Geometry (no horizontal page scroll at 375px, boxes not clipped) is measured in Playwright.
});

it('Jadwal Shift — localStorage gagal tidak merusak tampilan: server merender kalender tanpanya; akses penyimpanan dibungkus try/catch', function () {
    jdlPlace($this->pagi, $this->budi, '2026-10-05');

    // Server render is independent of the browser store: a fresh mount is a fully usable calendar.
    $component = jdlAsAdminCalendar($this)->assertSet('viewMode', 'calendar');
    $html = $component->html();
    expect(jdlHas($html, 'jd-calendar'))->toBeTrue()
        ->and(jdlTestIdText($html, 'jd-line-2026-10-05-'.$this->pagi->id))->toBe('Pagi Budi Santoso');

    // Both storage touches in the markup are guarded: read (x-init) and write (x-on:click on each button).
    $bar = jdlNodes($html, '//div[contains(concat(" ", normalize-space(@class), " "), " jd-view-bar ")]');
    expect($bar)->toHaveCount(1);
    $read = $bar[0]->getAttribute('x-init');
    expect($read)->toContain('localStorage.getItem')
        ->and($read)->toContain('try {')
        ->and($read)->toContain('catch (e) {}')
        // the read is validated: only the two known modes are ever applied
        ->and($read)->toContain("v === 'calendar' || v === 'table'");
    foreach (['jd-view-calendar', 'jd-view-table'] as $id) {
        $write = jdlNodes($html, '//button[@data-testid="'.$id.'"]')[0]->getAttribute('x-on:click');
        expect($write)->toContain('localStorage.setItem')
            ->and($write)->toContain('try {')
            ->and($write)->toContain('catch (e) {}');
    }

    // When the stored value is garbage (or storage threw), the client never calls setViewMode with it; and the
    // server ignores a bad mode anyway - the view stays as rendered.
    $component->call('setViewMode', '<script>')->assertSet('viewMode', 'calendar');
    // A valid stored value is applied through the same method (what x-init does).
    $component->call('setViewMode', 'table')->assertSet('viewMode', 'table');
    expect(jdlHas($component->html(), 'schedule-grid'))->toBeTrue();
});

it('Jadwal Shift — Kosongkan sel berisi banyak orang: klik pertama hanya meminta konfirmasi, "Ya" menulis, "Batal" tidak', function () {
    jdlPlace($this->pagi, $this->budi, '2026-10-05');
    jdlPlace($this->pagi, $this->citra, '2026-10-05');
    jdlPlace($this->pagi, $this->dewi, '2026-10-06');   // neighbour
    $rows = jdlSnapshot();

    $component = jdlAsAdminOnLine1($this)->call('openDay', '2026-10-05', $this->pagi->id);

    $component->call('clearShift', $this->pagi->id)->assertSet('confirmClearShiftId', $this->pagi->id);
    $html = $component->html();
    expect(jdlText(jdlNodes($html, '//*[@data-testid="jd-card-clear-confirm-'.$this->pagi->id.'"]')[0]))->toContain('mengeluarkan 2 orang')
        ->and(jdlSnapshot())->toBe($rows);

    $component->call('cancelClear')->assertSet('confirmClearShiftId', null);
    expect(jdlHas($component->html(), 'jd-card-clear-confirm-'.$this->pagi->id))->toBeFalse()
        ->and(jdlSnapshot())->toBe($rows);

    $component->call('clearShift', $this->pagi->id)->call('confirmClear', $this->pagi->id)->assertSet('panelError', null);
    expect(jdlCell($this->pagi->id, '2026-10-05'))->toBe([])
        ->and(jdlCell($this->pagi->id, '2026-10-06'))->toBe([$this->dewi->id])     // neighbour untouched
        ->and(jdlCount())->toBe(1)
        ->and(jdlHas($component->html(), 'jd-card-clear-confirm-'.$this->pagi->id))->toBeFalse();
});

// ── Cross-links and sidebar ──────────────────────────────────────────────

it('Jadwal Shift — Admin melihat tautan ke Kelola Shift di layar ini', function () {
    $html = jdlAsAdminOnLine1($this)->html();

    $buttons = jdlNodes($html, '//a[@data-testid="open-kelola-shift"]');
    expect($buttons)->toHaveCount(1)->and($buttons[0]->getAttribute('href'))->toBe(route('master-data.shifts'));
});

it('Kelola Shift — memuat tautan ke Jadwal Shift (bawa mill dan line yang dipilih)', function () {
    $bare = Livewire::actingAs($this->admin)->test(App\Livewire\MasterData\KelolaShift::class)->html();
    $link = jdlNodes($bare, '//a[@data-testid="open-jadwal-shift"]');

    expect($link)->toHaveCount(1)
        ->and($link[0]->getAttribute('href'))->toBe(route('master-data.shift-schedules'));

    $withContext = Livewire::actingAs($this->admin)->test(App\Livewire\MasterData\KelolaShift::class)
        ->set('businessUnitId', $this->millA->id)
        ->html();
    $contextLink = jdlNodes($withContext, '//a[@data-testid="open-jadwal-shift"]');

    expect($contextLink)->toHaveCount(1)
        ->and(html_entity_decode($contextLink[0]->getAttribute('href')))
        ->toBe(route('master-data.shift-schedules', ['businessUnitId' => $this->millA->id]));
});

it('sidebar — "Jadwal Shift" ada untuk admin, supervisor, dan mill_management; TIDAK ada untuk operator', function () {
    $href = 'href="'.route('master-data.shift-schedules').'"';

    foreach ([
        'admin' => [$this->admin, '/dashboard'],
        'supervisor' => [$this->supervisor, '/dashboard'],
        'mill_management' => [$this->millManagement, '/dashboard'],
    ] as $label => [$user, $page]) {
        $sidebar = jdlSidebar($this->actingAs($user, 'web')->get($page)->assertOk()->getContent());

        expect($sidebar)->not->toBe('', "sidebar $label tidak ditemukan")
            ->and(substr_count($sidebar, $href))->toBe(1, "sidebar $label harus memuat tautan Jadwal Shift tepat sekali");
    }

    $operatorSidebar = jdlSidebar($this->actingAs($this->operator, 'web')->get('/beranda')->assertOk()->getContent());
    preg_match_all('#<a href="([^"]+)"#', $operatorSidebar, $links);

    expect($operatorSidebar)->not->toBe('')
        ->and($links[1])->not->toContain(route('master-data.shift-schedules'));
});

it('sidebar — tujuh role setara Admin juga melihat "Jadwal Shift"', function (UserRole $role) {
    $actor = User::factory()->role($role)->forBusinessUnit($this->millA)->create();
    $sidebar = jdlSidebar($this->actingAs($actor, 'web')->get('/dashboard')->assertOk()->getContent());

    expect(substr_count($sidebar, 'href="'.route('master-data.shift-schedules').'"'))->toBe(1);
})->with('jadwal lw admin equivalents');

it('sidebar — di halaman Jadwal Shift tautan-nya aktif (class="active")', function () {
    $html = $this->actingAs($this->admin, 'web')->get('/master-data/shift-schedules')->assertOk()->getContent();
    $sidebar = jdlSidebar($html);

    expect($sidebar)->toContain('<a href="'.route('master-data.shift-schedules').'" class="active">');
});
