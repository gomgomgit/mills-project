<?php

/**
 * KelolaShiftTest (Feature/Api) — screen-170--kelola-shift /
 * usecase-176--kelola-shift (Kelola Shift per Production Line).
 *
 * Integration tests for the screen's SIX endpoints
 * (App\Http\Controllers\Api\ShiftController), exercising the real route →
 * EnsureRole → controller → ShiftService → Eloquent chain against the sqlite
 * in-memory testing DB. Mirrors tests/Feature/Api/KelolaPeriodePelaporanTest.php.
 *
 *   GET    /api/shifts/business-units/options
 *   GET    /api/shifts/production-lines/options
 *   GET    /api/shifts
 *   POST   /api/shifts            → 200, NOT 201 (the spec's scenarios)
 *   PATCH  /api/shifts/{id}
 *   DELETE /api/shifts/{id}
 *
 * One test per test_scenarios entry whose `api_test` is non-empty, each
 * executing that scenario's steps IN ORDER and asserting every step's
 * expected_status and expected_error_code. Scenarios 8, 9, 23, 26 and 27 carry
 * an EMPTY api_test — they are form-preview, DOM and layout claims and live in
 * the Livewire and Playwright files instead; they are named in the comments
 * below rather than silently dropped. Plus unit_test_cases 36 and 43, whose
 * subject is the ROUTE TABLE itself and therefore belongs here.
 *
 * ── PLACEHOLDER IDs IN THE SPEC ARE NOT LITERAL-SAFE ────────────────────
 *
 * The scenarios send `production_line_id: "pl-001"` / `"pl-003"` and
 * `business_unit_id: "bu-001"`. A non-UUID or foreign production_line_id is
 * 422 BY DESIGN (edge_case 5 + ShiftService::validate()), so posting the
 * literals and expecting 200 would assert the opposite of the contract. Every
 * test below substitutes a REAL factory-created UUID. The one literal that is
 * kept is scenario 3's `business_unit_id: "bu-tanpa-line"`: an unknown mill
 * still answers 200 with empty data, because this endpoint SUMMARISES rather
 * than fetching one resource — and that is asserted, next to the real
 * line-less mill.
 *
 * ── ERROR CODE ASSERTIONS ───────────────────────────────────────────────
 *
 * ApiExceptionHandler emits the machine-readable `code` for VALIDATION_ERROR /
 * NOT_FOUND / UNAUTHENTICATED and for every exception implementing
 * App\Exceptions\HasErrorCode (SHIFT_OVERLAP) — all asserted here. FORBIDDEN
 * is the exception: App\Http\Middleware\EnsureRole builds its own JSON
 * response and never reaches ApiExceptionHandler, so a role rejection carries
 * `message` only. Scenario 28's api_test asks for `expected_error_code:
 * FORBIDDEN`; with the route middleware in front, the middleware answers
 * first, so the 403 tests assert STATUS + MESSAGE and explicitly assert the
 * absence of `code`. The same 403 raised by ShiftService::guardAdmin() itself
 * DOES carry `code: FORBIDDEN` — that path is covered in
 * tests/Unit/Services/ShiftServiceTest.php, where the service is called
 * directly.
 *
 * NO DATE PARAMETER ANYWHERE, and that absence is deliberate: the schedule
 * applies the same way every day (product owner decision 2026-10-15).
 * Case 36 below asserts it on the route table so "completing" the API with a
 * day dimension reddens first.
 */

use App\Enums\UserRole;
use App\Models\BusinessUnit;
use App\Models\ProductionLine;
use App\Models\Shift;
use App\Models\User;
use App\Services\ShiftService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->millA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->millB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    // Named rather than faker-numbered: listShifts() orders lines by name, so
    // "the first coverage entry" must mean the same line on every run.
    $this->lineA1 = ProductionLine::factory()->forBusinessUnit($this->millA)->create(['name' => 'Line 1']);
    $this->lineA2 = ProductionLine::factory()->forBusinessUnit($this->millA)->create(['name' => 'Line 2']);
    $this->lineA3 = ProductionLine::factory()->forBusinessUnit($this->millA)->create(['name' => 'Line 3']);
    $this->lineB1 = ProductionLine::factory()->forBusinessUnit($this->millB)->create(['name' => 'Line B1']);

    $this->admin = User::factory()->role(UserRole::Admin)->create([
        'name' => 'Admin X',
        'business_unit_id' => null,
    ]);
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->create();
});

/**
 * Valid POST /api/shifts body for a line; override only what matters.
 *
 * NO `day`, `weekday` OR `effective_from` KEY, and never a placeholder id.
 */
function shiftApiPayload(ProductionLine $line, array $overrides = []): array
{
    return array_merge([
        'production_line_id' => $line->id,
        'name' => 'Pagi',
        'start_time' => '07:00',
        'end_time' => '15:00',
    ], $overrides);
}

/**
 * The coverage entry for one line out of a GET /api/shifts body.
 *
 * @return array<string, mixed>
 */
function apiCoverageFor(array $body, ProductionLine $line): array
{
    $entry = collect($body['coverage'])->firstWhere('production_line_id', $line->id);

    expect($entry)->not->toBeNull("coverage tidak memuat line {$line->name}");

    return $entry;
}

// ── Scenario 1 ──────────────────────────────────────────────────────────

// Scenario 1: "berhasil menambahkan shift" — four steps, in order.
it('berhasil: options mill, options line, POST shift, lalu menemukannya pada daftar', function () {
    // Step 1 — GET /api/shifts/business-units/options -> 200
    $options = $this->actingAs($this->admin, 'web')->getJson('/api/shifts/business-units/options');

    $options->assertOk();
    $options->assertJsonStructure(['data' => [['id', 'name']]]);
    expect(collect($options->json('data'))->pluck('id')->all())->toContain($this->millA->id);

    // Step 2 — GET /api/shifts/production-lines/options?business_unit_id=… -> 200
    $lines = $this->actingAs($this->admin, 'web')
        ->getJson("/api/shifts/production-lines/options?business_unit_id={$this->millA->id}");

    $lines->assertOk();
    expect(array_column($lines->json('data'), 'name'))->toBe(['Line 1', 'Line 2', 'Line 3']);
    // Line milik mill lain tidak pernah ikut.
    expect(collect($lines->json('data'))->pluck('id')->all())->not->toContain($this->lineB1->id);

    // Step 3 — POST /api/shifts -> 200 (bukan 201)
    $create = $this->actingAs($this->admin, 'web')->postJson('/api/shifts', shiftApiPayload($this->lineA1));

    $create->assertStatus(200);
    $create->assertJsonPath('data.production_line_id', $this->lineA1->id);
    $create->assertJsonPath('data.name', 'Pagi');
    $create->assertJsonPath('data.start_time', '07:00');
    $create->assertJsonPath('data.end_time', '15:00');
    $create->assertJsonPath('data.crosses_midnight', false);
    $create->assertJsonPath('data.duration_minutes', 480);

    // created_by datang dari SESI, tidak pernah dari request.
    expect(Shift::firstOrFail()->created_by)->toBe($this->admin->id);

    // Step 4 — GET /api/shifts?business_unit_id=… -> 200
    $list = $this->actingAs($this->admin, 'web')
        ->getJson("/api/shifts?business_unit_id={$this->millA->id}");

    $list->assertOk();
    $list->assertJsonStructure([
        'data' => [[
            'id', 'production_line_id', 'production_line_name', 'name', 'start_time', 'end_time',
            'crosses_midnight', 'duration_minutes', 'position',
        ]],
        'coverage' => [[
            'production_line_id', 'production_line_name', 'shift_count', 'state',
            'uncovered_minutes', 'starts_at_production_day', 'blocks',
        ]],
        'meta' => ['page', 'per_page', 'total'],
    ]);
    $list->assertJsonPath('meta.total', 1);
    $list->assertJsonPath('data.0.name', 'Pagi');
    $list->assertJsonPath('data.0.production_line_name', 'Line 1');
    $list->assertJsonPath('data.0.position', 0);
    $list->assertJsonPath('data.0.start_offset_minutes', 0);

    // Satu entri cakupan per line mill itu — ketiganya, bukan hanya yang
    // punya shift.
    expect($list->json('coverage'))->toHaveCount(3);

    $line1 = apiCoverageFor($list->json(), $this->lineA1);
    expect($line1['state'])->toBe('gap');
    expect($line1['uncovered_minutes'])->toBe(960);
    expect($line1['uncovered_hours_text'])->toBe('16');
});

// ── Scenario 2 ──────────────────────────────────────────────────────────

// Scenario 2: "mill belum dipilih"
it('menolak 422 VALIDATION_ERROR ketika business_unit_id absen pada daftar shift', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    $response = $this->actingAs($this->admin, 'web')->getJson('/api/shifts');

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'VALIDATION_ERROR');
    $response->assertJsonValidationErrors(['business_unit_id']);
    $response->assertJsonMissingPath('data');
    $response->assertJsonMissingPath('coverage');

    // '' dibaca sebagai "belum dipilih", bukan sebagai mill bernama string
    // kosong — kolom uuid PostgreSQL menolak string kosong.
    $this->actingAs($this->admin, 'web')->getJson('/api/shifts?business_unit_id=')
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR');

    // Dan options line menuntut hal yang sama (endpoint #2's 422).
    $this->actingAs($this->admin, 'web')->getJson('/api/shifts/production-lines/options')
        ->assertStatus(422)
        ->assertJsonPath('code', 'VALIDATION_ERROR');
});

// ── Scenario 3 ──────────────────────────────────────────────────────────

// Scenario 3: "mill belum punya Production Line"
it('memulangkan data dan coverage kosong dengan 200 untuk mill tanpa Production Line', function () {
    $millKosong = BusinessUnit::factory()->create(['name' => 'Mill Tanpa Line']);

    $response = $this->actingAs($this->admin, 'web')
        ->getJson("/api/shifts?business_unit_id={$millKosong->id}");

    $response->assertOk();
    $response->assertJsonPath('data', []);
    $response->assertJsonPath('coverage', []);
    $response->assertJsonPath('meta.total', 0);

    // Dan id literal skenario itu — 'bu-tanpa-line', yang bukan UUID — juga
    // 200 dengan data kosong, BUKAN 404 dan bukan 500: endpoint ini
    // merangkum, ia tidak mengambil satu resource. (Str::isUuid diperiksa
    // sebelum kueri; whereKey('bukan-uuid') pada kolom uuid PostgreSQL
    // melempar SQLSTATE 22P02.)
    expect(Str::isUuid('bu-tanpa-line'))->toBeFalse();

    $literal = $this->actingAs($this->admin, 'web')
        ->getJson('/api/shifts?business_unit_id=bu-tanpa-line');

    $literal->assertOk();
    $literal->assertJsonPath('data', []);
    $literal->assertJsonPath('coverage', []);
});

// ── Scenario 4 ──────────────────────────────────────────────────────────

// Scenario 4: "line belum punya shift"
it('menandai line tanpa shift sebagai unconfigured dan BUKAN gap 24 jam', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();
    Shift::factory()->forProductionLine($this->lineA1)->siang()->create();
    Shift::factory()->forProductionLine($this->lineA1)->malam()->create();

    $body = $this->actingAs($this->admin, 'web')
        ->getJson("/api/shifts?business_unit_id={$this->millA->id}")
        ->assertOk()
        ->json();

    $line3 = apiCoverageFor($body, $this->lineA3);

    expect($line3['state'])->toBe('unconfigured');
    expect($line3['shift_count'])->toBe(0);
    // NOL blok diterbitkan dan uncovered_minutes 0 — 'belum diatur' bukan
    // 'celah 24 jam': yang pertama belum dikerjakan, yang kedua keputusan
    // yang mungkin salah.
    expect($line3['uncovered_minutes'])->toBe(0);
    expect($line3['blocks'])->toBe([]);
    expect($line3['state'])->not->toBe('gap');

    // Pembedaannya ada DI PAYLOAD: line yang tercakup penuh dan line yang
    // belum diatur tidak punya nilai yang sama di satu field pun.
    $line1 = apiCoverageFor($body, $this->lineA1);
    expect($line1['state'])->toBe('covered');
    expect($line1['shift_count'])->toBe(3);
});

// ── Scenario 5 ──────────────────────────────────────────────────────────

// Scenario 5: "filter diset seluruh line"
it('menerima production_line_id yang absen sebagai seluruh line mill itu', function () {
    // Dua belas line, satu entri cakupan per line — dan tidak satu pun 422:
    // berbeda dari layar laporan tempat line WAJIB dipilih.
    for ($i = 4; $i <= 12; $i++) {
        ProductionLine::factory()->forBusinessUnit($this->millA)->create([
            'name' => 'Line '.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
        ]);
    }

    $response = $this->actingAs($this->admin, 'web')
        ->getJson("/api/shifts?business_unit_id={$this->millA->id}");

    $response->assertOk();
    expect($response->json('coverage'))->toHaveCount(12);
    expect(collect($response->json('coverage'))->pluck('production_line_id')->unique())->toHaveCount(12);
});

// ── Scenario 6 ──────────────────────────────────────────────────────────

// Scenario 6: "cakupan penuh tetapi tidak selaras hari produksi".
// `production_line_id: "pl-001"` diganti UUID nyata — literalnya 422 by
// design (edge_case 5), jadi mengirimnya dan menuntut 200 justru akan
// mengasersi kebalikan kontraknya.
it('melaporkan covered dengan starts_at_production_day false ketika union dimulai 06:00', function () {
    Shift::factory()->forProductionLine($this->lineA1)->named('A')->range('06:00:00', '14:00:00')->create();
    Shift::factory()->forProductionLine($this->lineA1)->named('B')->range('14:00:00', '22:00:00')->create();
    Shift::factory()->forProductionLine($this->lineA1)->named('C')->range('22:00:00', '06:00:00')->create();

    $body = $this->actingAs($this->admin, 'web')
        ->getJson("/api/shifts?business_unit_id={$this->millA->id}&production_line_id={$this->lineA1->id}")
        ->assertOk()
        ->json();

    $entry = apiCoverageFor($body, $this->lineA1);

    expect($entry['state'])->toBe('covered');
    expect($entry['uncovered_minutes'])->toBe(0);
    // PERINGATAN, BUKAN PENOLAKAN — ketiga shift tersimpan apa adanya.
    expect($entry['starts_at_production_day'])->toBeFalse();
    expect(Shift::count())->toBe(3);

    // Filter satu line berarti satu entri cakupan.
    expect($body['coverage'])->toHaveCount(1);
});

// ── Scenario 7 ──────────────────────────────────────────────────────────

// Scenario 7: "shift melewati tengah malam"
it('menerima shift 23:00–07:00 dan menerbitkannya sebagai SATU blok di ujung kanan sumbu', function () {
    $create = $this->actingAs($this->admin, 'web')->postJson('/api/shifts', shiftApiPayload($this->lineA1, [
        'name' => 'Malam',
        'start_time' => '23:00',
        'end_time' => '07:00',
    ]));

    $create->assertStatus(200);
    $create->assertJsonPath('data.crosses_midnight', true);
    $create->assertJsonPath('data.duration_minutes', 480);
    $shiftId = $create->json('data.id');

    $body = $this->actingAs($this->admin, 'web')
        ->getJson("/api/shifts?business_unit_id={$this->millA->id}&production_line_id={$this->lineA1->id}")
        ->assertOk()
        ->json();

    $entry = apiCoverageFor($body, $this->lineA1);
    $shiftBlocks = collect($entry['blocks'])->where('kind', 'shift')->values();

    // TEPAT SATU blok, bukan dua potongan di kedua ujung sumbu.
    expect($shiftBlocks)->toHaveCount(1);
    expect($shiftBlocks[0]['shift_id'])->toBe($shiftId);
    expect($shiftBlocks[0]['start_offset_minutes'])->toBe(960);
    expect($shiftBlocks[0]['duration_minutes'])->toBe(480);
    expect($shiftBlocks[0]['crosses_midnight'])->toBeTrue();
    // Dan ia berhenti TEPAT di ujung kanan sumbu.
    expect($shiftBlocks[0]['start_offset_minutes'] + $shiftBlocks[0]['duration_minutes'])
        ->toBe(ShiftService::DAY_MINUTES);

    // Label '23:00 — 07:00 +1' pada baris tabel MAUPUN blok, supaya jam
    // selesai yang lebih kecil tidak terbaca sebagai salah input.
    expect($shiftBlocks[0]['time_label'])->toBe('23:00 — 07:00 +1');
    expect($body['data'][0]['time_label'])->toBe('23:00 — 07:00 +1');
    expect($body['data'][0]['crosses_midnight'])->toBeTrue();
});

/*
 * Scenario 8 ("pratinjau menampilkan tumpang tindih sebelum simpan") and
 * Scenario 9 ("jam diketik separuh") carry an EMPTY api_test on purpose: the
 * preview is Alpine inside the form and its whole claim is that NO network
 * request is sent. They live in e2e-web/tests/kelola-shift.spec.ts.
 */

// ── Scenario 10 ─────────────────────────────────────────────────────────

// Scenario 10: "jam diketik manual tanpa picker" — the API half is simply
// that a plainly typed 'HH:MM' is accepted and stored. The "no disabled, no
// readonly, picker AND typing" half is a DOM claim: Livewire + Playwright.
it('menerima jam yang diketik sebagai HH:MM dan menyimpannya sebagai H:i:s', function () {
    $create = $this->actingAs($this->admin, 'web')->postJson('/api/shifts', shiftApiPayload($this->lineA1));

    $create->assertStatus(200);
    $create->assertJsonPath('data.start_time', '07:00');

    // Kolomnya TIME: bentuk panjang diterima sama di PostgreSQL dan SQLite.
    expect(Shift::firstOrFail()->start_time)->toBe('07:00:00');
    expect(Shift::firstOrFail()->end_time)->toBe('15:00:00');
});

// ── Scenario 11 ─────────────────────────────────────────────────────────

// Scenario 11: "tumpang tindih ditolak saat simpan"
it('menolak 422 SHIFT_OVERLAP dengan menyebut nama shift pembanding dan rentang tindihannya', function () {
    Shift::factory()->forProductionLine($this->lineA1)->siang()->create();

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/shifts', shiftApiPayload($this->lineA1, [
        'name' => 'Sore',
        'start_time' => '22:00',
        'end_time' => '02:00',
    ]));

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'SHIFT_OVERLAP');

    // assertStringContainsString, BUKAN expect()->toContain($a, $b) — yang
    // kedua menerima BEBERAPA NEEDLE dan akan membaca pesan tes sebagai
    // needle, lalu menghijau tanpa membuktikan apa pun.
    $message = $response->json('message');
    $this->assertStringContainsString('Siang', $message, 'pesan tidak menyebut shift pembanding');
    $this->assertStringContainsString('22:00', $message, 'pesan tidak menyebut awal rentang tindihan');
    $this->assertStringContainsString('23:00', $message, 'pesan tidak menyebut akhir rentang tindihan');

    expect(Shift::count())->toBe(1);
});

// ── Scenario 12 ─────────────────────────────────────────────────────────

// Scenario 12: "pergantian shift berimpit diterima" — THE ZERO-MINUTE
// BOUNDARY, ACCEPTED. A shift that ends exactly when another starts
// intersects zero minutes; that is a normal shift change, not an overlap.
it('menerima shift yang mulai tepat saat shift sebelumnya berakhir', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/shifts', shiftApiPayload($this->lineA1, [
        'name' => 'Siang',
        'start_time' => '15:00',
        'end_time' => '23:00',
    ]));

    $response->assertStatus(200);
    expect(Shift::count())->toBe(2);

    $body = $this->actingAs($this->admin, 'web')
        ->getJson("/api/shifts?business_unit_id={$this->millA->id}&production_line_id={$this->lineA1->id}")
        ->json();

    // Dua blok BERSEBELAHAN tanpa blok gap di antaranya — celahnya hanya yang
    // di belakang keduanya (23:00 → 07:00).
    $entry = apiCoverageFor($body, $this->lineA1);
    expect(array_column($entry['blocks'], 'kind'))->toBe(['shift', 'shift', 'gap']);
    expect($entry['blocks'][0]['duration_minutes'])->toBe(480);
    expect($entry['blocks'][1]['start_offset_minutes'])->toBe(480);
    expect($entry['blocks'][2]['start_offset_minutes'])->toBe(960);
});

// ── Scenario 13 ─────────────────────────────────────────────────────────

// Scenario 13: "tumpang tindih satu menit ditolak" — THE ONE-MINUTE
// BOUNDARY, REJECTED. Together with scenario 12 this pins the boundary to the
// intersection being strictly > 0, not to a minute tolerance.
it('menolak 422 SHIFT_OVERLAP untuk tindihan satu menit dan menyebut rentang satu menit itu', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/shifts', shiftApiPayload($this->lineA1, [
        'name' => 'Sore',
        'start_time' => '14:59',
        'end_time' => '23:00',
    ]));

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'SHIFT_OVERLAP');

    $message = $response->json('message');
    $this->assertStringContainsString('Pagi', $message, 'pesan tidak menyebut shift pembanding');
    $this->assertStringContainsString('14:59', $message, 'pesan tidak menyebut rentang satu menit');
    $this->assertStringContainsString('15:00', $message, 'pesan tidak menyebut rentang satu menit');

    expect(Shift::count())->toBe(1);
});

/**
 * The trap case on the HTTP surface (unit_test_cases 19/20): a night shift
 * 23:00–07:00 already exists, and a candidate 01:00–03:00 falls entirely
 * inside it. An implementation comparing '23:00' > '07:00' as STRINGS
 * concludes the night range is empty and LETS THIS THROUGH with a 200 — so
 * this test reddens that implementation rather than passing either way.
 */
it('mendeteksi tindihan di dalam shift malam — kasus jebakan perbandingan string', function () {
    Shift::factory()->forProductionLine($this->lineA1)->malam()->create();

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/shifts', shiftApiPayload($this->lineA1, [
        'name' => 'Dini',
        'start_time' => '01:00',
        'end_time' => '03:00',
    ]));

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'SHIFT_OVERLAP');
    $this->assertStringContainsString('Malam', $response->json('message'), 'pesan tidak menyebut shift pembanding');

    expect(Shift::count())->toBe(1);
    expect(Shift::where('name', 'Dini')->exists())->toBeFalse();
});

// ── Scenario 14 ─────────────────────────────────────────────────────────

// Scenario 14: "nama shift sudah dipakai di line yang sama"
it('menolak 422 VALIDATION_ERROR ketika nama shift sudah dipakai pada line yang sama', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    // Jamnya sengaja TIDAK bertindih: yang memerah harus keunikan nama, bukan
    // tumpang tindih — keduanya kesalahan berbeda dengan pesan berbeda.
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/shifts', shiftApiPayload($this->lineA1, [
        'name' => 'Pagi',
        'start_time' => '15:00',
        'end_time' => '23:00',
    ]));

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'VALIDATION_ERROR');
    $response->assertJsonValidationErrors(['name']);
    expect($response->json('code'))->not->toBe('SHIFT_OVERLAP');
    $this->assertStringContainsString(
        'sudah dipakai',
        $response->json('errors.name.0'),
        'pesan tidak menyebut nama sudah dipakai'
    );

    expect(Shift::count())->toBe(1);
});

// ── Scenario 15 ─────────────────────────────────────────────────────────

// Scenario 15: "nama shift sama dengan line lain diterima"
it('menerima nama shift yang sama pada Production Line yang berbeda', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    $response = $this->actingAs($this->admin, 'web')->postJson('/api/shifts', shiftApiPayload($this->lineA2));

    $response->assertStatus(200);
    $response->assertJsonPath('data.production_line_id', $this->lineA2->id);
    expect(Shift::where('name', 'Pagi')->count())->toBe(2);

    // Dua baris 'Pagi' dengan kolom Production Line yang berbeda.
    $rows = collect($this->actingAs($this->admin, 'web')
        ->getJson("/api/shifts?business_unit_id={$this->millA->id}")
        ->json('data'))
        ->where('name', 'Pagi');

    expect($rows)->toHaveCount(2);
    expect($rows->pluck('production_line_name')->sort()->values()->all())->toBe(['Line 1', 'Line 2']);
});

// ── Scenario 16 + 21 ────────────────────────────────────────────────────

/**
 * Scenarios 16 and 21 are the TWO HALVES OF ONE RESOLVED INCONSISTENCY, and
 * they are tested as such:
 *
 *  - WRITE PATH: 07:00–07:00 is rejected as VALIDATION_ERROR (entity
 *    constraint #2, scenario 16 and scenario 21's api_test), never as
 *    SHIFT_OVERLAP — a zero-duration shift cannot hold a record.
 *  - READ PATH: durationMinutes() reads an equal pair as 1440, so a row
 *    inserted directly (factory/model) renders as ONE full `covered` block
 *    (scenario 21's component_test). That half is seeded through the factory,
 *    deliberately NOT through the API.
 */
it('menolak 422 VALIDATION_ERROR ketika jam mulai sama dengan jam selesai, bukan SHIFT_OVERLAP', function () {
    $response = $this->actingAs($this->admin, 'web')->postJson('/api/shifts', shiftApiPayload($this->lineA1, [
        'name' => 'Nol',
        'start_time' => '07:00',
        'end_time' => '07:00',
    ]));

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'VALIDATION_ERROR');
    $response->assertJsonValidationErrors(['end_time']);
    expect($response->json('code'))->not->toBe('SHIFT_OVERLAP');
    $this->assertStringContainsString(
        'berdurasi nol',
        $response->json('errors.end_time.0'),
        'pesan tidak menyebut durasi nol'
    );

    // Scenario 21's api_test sends the same thing on 'pl-003' (Line 3): same
    // answer, and it must not depend on which line it is.
    $this->actingAs($this->admin, 'web')->postJson('/api/shifts', shiftApiPayload($this->lineA3, [
        'name' => 'Penuh',
        'start_time' => '07:00',
        'end_time' => '07:00',
    ]))->assertStatus(422)->assertJsonPath('code', 'VALIDATION_ERROR');

    expect(Shift::count())->toBe(0);
});

it('membaca baris 07:00–07:00 yang sudah ada sebagai satu blok 1440 menit tercakup penuh', function () {
    // DIBUAT LEWAT FACTORY, bukan lewat API: jalur tulis menolaknya (test di
    // atas), dan yang diuji di sini adalah PEMBACAANNYA.
    Shift::factory()->forProductionLine($this->lineA3)->named('Penuh')->range('07:00:00', '07:00:00')->create();

    $body = $this->actingAs($this->admin, 'web')
        ->getJson("/api/shifts?business_unit_id={$this->millA->id}&production_line_id={$this->lineA3->id}")
        ->assertOk()
        ->json();

    $entry = apiCoverageFor($body, $this->lineA3);

    expect($entry['state'])->toBe('covered');
    expect($entry['uncovered_minutes'])->toBe(0);
    expect($entry['blocks'])->toHaveCount(1);
    expect($entry['blocks'][0]['kind'])->toBe('shift');
    expect($entry['blocks'][0]['duration_minutes'])->toBe(1440);
    expect(collect($entry['blocks'])->where('kind', 'gap'))->toHaveCount(0);
});

// ── Scenario 17 ─────────────────────────────────────────────────────────

// Scenario 17: "mengubah shift sehingga bertindih"
it('menolak 422 SHIFT_OVERLAP pada PATCH yang menabrak shift berikutnya, sementara ubah nama tetap berhasil', function () {
    $pagi = Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();
    Shift::factory()->forProductionLine($this->lineA1)->siang()->create();

    // Step 1 — PATCH /api/shifts/{id} { end_time: 16:00 } -> 422 SHIFT_OVERLAP
    $response = $this->actingAs($this->admin, 'web')
        ->patchJson("/api/shifts/{$pagi->id}", ['end_time' => '16:00']);

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'SHIFT_OVERLAP');

    $message = $response->json('message');
    $this->assertStringContainsString('Siang', $message, 'pesan tidak menyebut shift pembanding');
    $this->assertStringContainsString('15:00', $message, 'pesan tidak menyebut awal rentang tindihan');
    $this->assertStringContainsString('16:00', $message, 'pesan tidak menyebut akhir rentang tindihan');

    expect($pagi->refresh()->end_time)->toBe('15:00:00');

    // Dan mengubah HANYA namanya tetap berhasil — shift yang sedang diubah
    // dikecualikan dari pembanding. Tanpa pengecualian itu, tidak ada
    // perubahan yang pernah bisa disimpan.
    $rename = $this->actingAs($this->admin, 'web')
        ->patchJson("/api/shifts/{$pagi->id}", ['name' => 'Pagi Awal']);

    $rename->assertStatus(200);
    $rename->assertJsonPath('data.name', 'Pagi Awal');
    // PATCH PARSIAL: jam yang tidak dikirim mempertahankan nilai tersimpan.
    $rename->assertJsonPath('data.start_time', '07:00');
    $rename->assertJsonPath('data.end_time', '15:00');
    expect($pagi->refresh()->updated_by)->toBe($this->admin->id);
});

// ── Scenario 18 ─────────────────────────────────────────────────────────

// Scenario 18: "memindahkan shift ke line lain"
it('memeriksa tumpang tindih terhadap line TUJUAN ketika PATCH memindahkan production_line_id', function () {
    $pagi = Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();
    Shift::factory()->forProductionLine($this->lineA2)->named('Pagi Line 2')->range('07:00:00', '15:00:00')->create();

    $response = $this->actingAs($this->admin, 'web')
        ->patchJson("/api/shifts/{$pagi->id}", ['production_line_id' => $this->lineA2->id]);

    $response->assertStatus(422);
    $response->assertJsonPath('code', 'SHIFT_OVERLAP');
    // Pembandingnya shift di line TUJUAN; shift di line asal justru tidak
    // lagi relevan setelah shift-nya pindah.
    $this->assertStringContainsString(
        'Pagi Line 2',
        $response->json('message'),
        'pesan tidak menyebut shift di line tujuan'
    );
    expect($pagi->refresh()->production_line_id)->toBe($this->lineA1->id);

    // Memindahkannya ke line yang jamnya kosong BERHASIL.
    $moved = $this->actingAs($this->admin, 'web')
        ->patchJson("/api/shifts/{$pagi->id}", ['production_line_id' => $this->lineA3->id]);

    $moved->assertStatus(200);
    $moved->assertJsonPath('data.production_line_id', $this->lineA3->id);
});

// unit_test_cases 29 + 32 — id yang tidak ada pada kedua rute ber-{id}.
it('menjawab 404 NOT_FOUND pada PATCH dan DELETE untuk id shift yang tidak ada', function () {
    $missing = (string) Str::uuid();

    $patch = $this->actingAs($this->admin, 'web')->patchJson("/api/shifts/{$missing}", ['name' => 'X']);
    $patch->assertStatus(404);
    $patch->assertJsonPath('code', 'NOT_FOUND');

    $delete = $this->actingAs($this->admin, 'web')->deleteJson("/api/shifts/{$missing}");
    $delete->assertStatus(404);
    $delete->assertJsonPath('code', 'NOT_FOUND');
});

// ── Scenario 19 ─────────────────────────────────────────────────────────

// Scenario 19: "menghapus shift membesarkan celah" — two steps, in order.
it('menghapus satu shift lalu cakupan line itu berubah dari covered menjadi gap', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();
    $siang = Shift::factory()->forProductionLine($this->lineA1)->siang()->create();
    Shift::factory()->forProductionLine($this->lineA1)->malam()->create();

    $before = $this->actingAs($this->admin, 'web')
        ->getJson("/api/shifts?business_unit_id={$this->millA->id}&production_line_id={$this->lineA1->id}")
        ->json();
    expect(apiCoverageFor($before, $this->lineA1)['state'])->toBe('covered');

    // Step 1 — DELETE /api/shifts/{id} -> 200
    $delete = $this->actingAs($this->admin, 'web')->deleteJson("/api/shifts/{$siang->id}");

    $delete->assertStatus(200);
    $delete->assertJsonPath('data.deleted', true);
    expect(Shift::find($siang->id))->toBeNull();

    // Step 2 — GET /api/shifts -> 200, cakupan sudah berubah
    $after = $this->actingAs($this->admin, 'web')
        ->getJson("/api/shifts?business_unit_id={$this->millA->id}&production_line_id={$this->lineA1->id}")
        ->assertOk()
        ->json();

    $entry = apiCoverageFor($after, $this->lineA1);

    expect($entry['state'])->toBe('gap');
    expect($entry['uncovered_minutes'])->toBe(480);
    expect($entry['uncovered_hours_text'])->toBe('8');
    expect($entry['shift_count'])->toBe(2);

    $gaps = collect($entry['blocks'])->where('kind', 'gap')->values();
    expect($gaps)->toHaveCount(1);
    // Tepat di posisi yang ditinggalkan Siang (15:00 → offset 480).
    expect($gaps[0]['start_offset_minutes'])->toBe(480);
    expect($gaps[0]['duration_minutes'])->toBe(480);
});

// ── Scenario 20 ─────────────────────────────────────────────────────────

// Scenario 20: "menghapus seluruh shift sebuah line"
it('mengembalikan line ke unconfigured sesudah shift terakhirnya dihapus', function () {
    $only = Shift::factory()->forProductionLine($this->lineA2)->pagi()->create();

    $this->actingAs($this->admin, 'web')->deleteJson("/api/shifts/{$only->id}")->assertStatus(200);

    $entry = apiCoverageFor(
        $this->actingAs($this->admin, 'web')
            ->getJson("/api/shifts?business_unit_id={$this->millA->id}")
            ->json(),
        $this->lineA2
    );

    expect($entry['state'])->toBe('unconfigured');
    expect($entry['shift_count'])->toBe(0);
    // BUKAN 'gap' dengan uncovered_minutes 1440.
    expect($entry['uncovered_minutes'])->toBe(0);
    expect($entry['blocks'])->toBe([]);
});

// ── Scenario 22 ─────────────────────────────────────────────────────────

// Scenario 22: "mengganti mill mengosongkan filter line" — the API half is
// that the options endpoint answers for the NEW mill only. The filter-reset
// itself is component state: Feature/Livewire.
it('memulangkan options line milik mill yang diminta saja', function () {
    $response = $this->actingAs($this->admin, 'web')
        ->getJson("/api/shifts/production-lines/options?business_unit_id={$this->millB->id}");

    $response->assertOk();
    expect(array_column($response->json('data'), 'name'))->toBe(['Line B1']);
    expect(collect($response->json('data'))->pluck('id')->all())->not->toContain($this->lineA1->id);

    // Mill yang tidak dikenal: array kosong dengan 200, bukan 404.
    $this->actingAs($this->admin, 'web')
        ->getJson('/api/shifts/production-lines/options?business_unit_id='.Str::uuid())
        ->assertOk()
        ->assertJsonPath('data', []);
});

/*
 * Scenario 23 ("Admin mencari pengaturan jendela sinkronisasi"),
 * Scenario 26 ("status cakupan terbaca tanpa warna") and Scenario 27 ("layar
 * dibuka pada lebar sempit") carry an EMPTY api_test: all three are claims
 * about the rendered screen. 23 and 26 live in Feature/Livewire; 26 and 27
 * live in e2e-web/tests/kelola-shift.spec.ts.
 */

// ── Scenario 24 ─────────────────────────────────────────────────────────

// Scenario 24: "tabel berpindah halaman sementara timeline tidak"
it('memaginasi data sementara coverage memuat seluruh line pada setiap halaman', function () {
    foreach ([$this->lineA1, $this->lineA2, $this->lineA3] as $line) {
        Shift::factory()->forProductionLine($line)->pagi()->create();
        Shift::factory()->forProductionLine($line)->siang()->create();
        Shift::factory()->forProductionLine($line)->malam()->create();
    }

    $page1 = $this->actingAs($this->admin, 'web')
        ->getJson("/api/shifts?business_unit_id={$this->millA->id}&page=1&per_page=4")
        ->assertOk();

    // Step 1 of the scenario — page=2.
    $page2 = $this->actingAs($this->admin, 'web')
        ->getJson("/api/shifts?business_unit_id={$this->millA->id}&page=2&per_page=4")
        ->assertOk();

    expect($page1->json('data'))->toHaveCount(4);
    expect($page2->json('data'))->toHaveCount(4);
    $page1->assertJsonPath('meta.page', 1);
    $page2->assertJsonPath('meta.page', 2);
    $page2->assertJsonPath('meta.per_page', 4);
    $page2->assertJsonPath('meta.total', 9);

    // Baris tabelnya memang BERGANTI.
    expect(array_column($page2->json('data'), 'id'))
        ->not->toBe(array_column($page1->json('data'), 'id'));

    // Sementara coverage sama persis — ia merangkum cakupan line, bukan
    // mendaftar baris, dan panel yang ikut berpindah halaman akan mengatakan
    // hal berbeda tentang line yang sama tergantung halaman mana yang
    // terbuka.
    expect($page2->json('coverage'))->toHaveCount(3);
    expect($page2->json('coverage'))->toBe($page1->json('coverage'));
});

// ── Scenario 25 ─────────────────────────────────────────────────────────

// Scenario 25: "urutan tabel dan timeline selalu sama"
it('menurunkan urutan data dan urutan blok dari satu nilai position yang sama', function () {
    // Urutan penambahan SENGAJA acak, dan abjad namanya pun tidak selaras
    // dengan urutan jamnya.
    Shift::factory()->forProductionLine($this->lineA1)->malam()->create();
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();
    Shift::factory()->forProductionLine($this->lineA1)->siang()->create();

    $body = $this->actingAs($this->admin, 'web')
        ->getJson("/api/shifts?business_unit_id={$this->millA->id}&production_line_id={$this->lineA1->id}")
        ->assertOk()
        ->json();

    expect(array_column($body['data'], 'name'))->toBe(['Pagi', 'Siang', 'Malam']);
    expect(array_column($body['data'], 'position'))->toBe([0, 1, 2]);

    $entry = apiCoverageFor($body, $this->lineA1);
    $blockIds = collect($entry['blocks'])->where('kind', 'shift')->pluck('shift_id')->all();

    expect($blockIds)->toBe(array_column($body['data'], 'id'));
});

// ── Scenario 28 ─────────────────────────────────────────────────────────

/**
 * Scenario 28: "hanya Admin punya layar ini" — both steps, for all three
 * non-admin roles.
 *
 * The scenario asks for `expected_error_code: FORBIDDEN`. EnsureRole builds
 * its own JSON response and never reaches ApiExceptionHandler, so the body
 * carries `message` only: with the route middleware in front, the middleware
 * answers first. Asserted here as status + message + the deliberate ABSENCE
 * of `code`, exactly as the screen-128 API tests do. The FORBIDDEN code does
 * exist on the 403 that ShiftService::guardAdmin() raises — covered in
 * tests/Unit/Services/ShiftServiceTest.php.
 */
it('menolak 403 pada seluruh enam endpoint untuk peran selain admin', function (string $role) {
    $existing = Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    $actor = match ($role) {
        'supervisor' => $this->supervisor,
        'mill_management' => $this->millManagement,
        'operator' => $this->operator,
    };

    $calls = [
        'options mill' => fn () => $this->actingAs($actor, 'web')->getJson('/api/shifts/business-units/options'),
        'options line' => fn () => $this->actingAs($actor, 'web')
            ->getJson("/api/shifts/production-lines/options?business_unit_id={$this->millA->id}"),
        // Step 1 of the scenario.
        'daftar' => fn () => $this->actingAs($actor, 'web')
            ->getJson("/api/shifts?business_unit_id={$this->millA->id}"),
        // Step 2 of the scenario.
        'POST' => fn () => $this->actingAs($actor, 'web')
            ->postJson('/api/shifts', shiftApiPayload($this->lineA1, ['name' => 'X'])),
        'PATCH' => fn () => $this->actingAs($actor, 'web')
            ->patchJson("/api/shifts/{$existing->id}", ['name' => 'Diubah']),
        'DELETE' => fn () => $this->actingAs($actor, 'web')->deleteJson("/api/shifts/{$existing->id}"),
    ];

    foreach ($calls as $label => $call) {
        $response = $call();

        expect($response->status())->toBe(403, "$label seharusnya 403 untuk peran $role");
        $response->assertJsonStructure(['message']);
        // NOL data shift terlihat, dan tidak ada `code` — lihat docblock.
        $response->assertJsonMissingPath('data');
        $response->assertJsonMissingPath('coverage');
        $response->assertJsonMissingPath('code');
    }

    // Tidak ada yang ditulis dan tidak ada yang hilang.
    expect(Shift::count())->toBe(1);
    expect(Shift::find($existing->id)->name)->toBe('Pagi');
})->with([
    'supervisor' => ['supervisor'],
    'mill management' => ['mill_management'],
    'operator' => ['operator'],
]);

// unit_test_cases 2 — 401 tanpa sesi, pada keenam endpoint.
it('menolak 401 UNAUTHENTICATED pada seluruh enam endpoint tanpa sesi', function () {
    $existing = Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    $responses = [
        'options mill' => $this->getJson('/api/shifts/business-units/options'),
        'options line' => $this->getJson("/api/shifts/production-lines/options?business_unit_id={$this->millA->id}"),
        'daftar' => $this->getJson("/api/shifts?business_unit_id={$this->millA->id}"),
        'POST' => $this->postJson('/api/shifts', shiftApiPayload($this->lineA1, ['name' => 'X'])),
        'PATCH' => $this->patchJson("/api/shifts/{$existing->id}", ['name' => 'Diubah']),
        'DELETE' => $this->deleteJson("/api/shifts/{$existing->id}"),
    ];

    foreach ($responses as $label => $response) {
        expect($response->status())->toBe(401, "$label seharusnya 401 tanpa sesi");
        $response->assertJsonMissingPath('data');
    }

    expect(Shift::count())->toBe(1);
});

// ── Permukaan rute (unit_test_cases 36, 43) ─────────────────────────────

/**
 * Case 43 — URUTAN PENDAFTARAN RUTE ADALAH BAGIAN DARI KONTRAK, BUKAN GAYA.
 * Kedua rute LITERAL wajib terdaftar SEBELUM /shifts/{id}; bila dibalik,
 * Laravel mencocokkan "business-units" / "production-lines" sebagai {id} dan
 * menjawab 404 untuk rute yang jelas-jelas ada. Jebakan ini sudah pernah
 * terjadi di routes/api.php untuk /periods/business-units/options.
 */
it('mendaftarkan rute literal SEBELUM rute ber-{id} dan keduanya tidak menjawab 404', function () {
    $uris = collect(Route::getRoutes()->getRoutes())
        ->map(fn ($route) => $route->uri())
        ->values()
        ->all();

    $positionOf = function (string $uri) use ($uris): int {
        $position = array_search($uri, $uris, true);

        expect($position)->not->toBeFalse("rute $uri tidak terdaftar");

        return (int) $position;
    };

    $idRoutePosition = $positionOf('api/shifts/{id}');

    expect($positionOf('api/shifts/business-units/options'))->toBeLessThan($idRoutePosition);
    expect($positionOf('api/shifts/production-lines/options'))->toBeLessThan($idRoutePosition);

    // Dan diukur dari HTTP, bukan hanya dari tabel rute: keduanya menjawab
    // 200 dan mendarat di aksinya sendiri, bukan 404 sebagai {id}.
    $mill = $this->actingAs($this->admin, 'web')->getJson('/api/shifts/business-units/options');
    $mill->assertOk();
    $mill->assertJsonStructure(['data']);
    // Jawaban aksi options, bukan jawaban aksi {id}: nol kunci coverage/meta.
    $mill->assertJsonMissingPath('coverage');

    $line = $this->actingAs($this->admin, 'web')
        ->getJson("/api/shifts/production-lines/options?business_unit_id={$this->millA->id}");
    $line->assertOk();
    expect(array_column($line->json('data'), 'name'))->toBe(['Line 1', 'Line 2', 'Line 3']);

    // PATCH/DELETE pada SEGMEN LITERAL itu bukan rute yang ada — kalau
    // keduanya cocok sebagai {id}, test di atas akan hijau sementara
    // urutannya terbalik.
    expect(collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/shifts'))
        ->map(fn ($route) => implode('|', $route->methods()).' '.$route->uri())
        ->values()
        ->all())
        ->toHaveCount(6);
});

/**
 * Case 36 — NOL parameter tanggal/hari pada keenam endpoint, dan ketiadaan
 * itu DISENGAJA: jadwal shift berlaku sama setiap hari (keputusan pemilik
 * produk 2026-10-15). Test ini ada supaya "melengkapi"-nya memerah lebih
 * dulu.
 */
it('tidak punya satu pun parameter tanggal atau hari pada keenam endpoint', function () {
    $forbidden = ['date', 'day', 'weekday', 'day_of_week', 'effective_from', 'effective_to', 'valid_from'];

    // Tidak ada path param semacam itu.
    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'api/shifts')) {
            continue;
        }

        foreach ($route->parameterNames() as $parameter) {
            expect($forbidden)->not->toContain($parameter);
        }
    }

    // Dan field semacam itu tidak pernah tersimpan: controller meneruskan
    // HANYA ShiftService::FIELDS.
    $create = $this->actingAs($this->admin, 'web')->postJson('/api/shifts', shiftApiPayload($this->lineA1, [
        'day_of_week' => 'monday',
        'effective_from' => '2026-10-15',
    ]));

    $create->assertStatus(200);
    $create->assertJsonMissingPath('data.day_of_week');
    $create->assertJsonMissingPath('data.effective_from');
    expect(Shift::firstOrFail()->getAttributes())->not->toHaveKey('day_of_week');

    // Query param semacam itu diabaikan, bukan 422 — tetapi ia juga tidak
    // mengubah jawaban: jadwalnya memang sama setiap hari.
    $withDate = $this->actingAs($this->admin, 'web')
        ->getJson("/api/shifts?business_unit_id={$this->millA->id}&date=2026-10-15");
    $plain = $this->actingAs($this->admin, 'web')
        ->getJson("/api/shifts?business_unit_id={$this->millA->id}");

    $withDate->assertOk();
    expect($withDate->json('coverage'))->toBe($plain->json('coverage'));
    expect($withDate->json('data'))->toBe($plain->json('data'));
});

/**
 * unit_test_cases 38 (API half) — the controller publishes EXACTLY the
 * coverage ShiftService computes, with no second calculation of its own. The
 * Livewire half of the same claim (the component publishes the same thing)
 * lives in tests/Feature/Livewire/KelolaShiftTest.php, and the two together
 * are what make "coverage is computed once" testable at all.
 */
it('menerbitkan cakupan yang identik dengan hasil ShiftService, tanpa hitungan kedua', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();
    Shift::factory()->forProductionLine($this->lineA1)->malam()->create();
    Shift::factory()->forProductionLine($this->lineA2)->siang()->create();

    $fromApi = $this->actingAs($this->admin, 'web')
        ->getJson("/api/shifts?business_unit_id={$this->millA->id}")
        ->assertOk()
        ->json();

    $this->actingAs($this->admin, 'web');
    $fromService = app(ShiftService::class)->listShifts($this->millA->id, null, 1, 20);

    // json() memulangkan array asosiatif yang sama bentuknya dengan hasil
    // service — perbandingan utuh, bukan beberapa field pilihan.
    expect($fromApi['coverage'])->toBe($fromService['coverage']);
    expect($fromApi['data'])->toBe($fromService['data']);
    expect($fromApi['meta']['total'])->toBe($fromService['meta']['total']);
});
