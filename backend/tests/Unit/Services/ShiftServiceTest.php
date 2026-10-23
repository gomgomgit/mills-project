<?php

/**
 * ShiftServiceTest — screen-170--kelola-shift / usecase-176--kelola-shift
 * (Kelola Shift per Production Line).
 *
 * Unit tests for App\Services\ShiftService, mirroring
 * tests/Unit/Services/PeriodServiceTest.php's structure
 * (`uses(TestCase::class, RefreshDatabase::class)` at the top is MANDATORY —
 * without it the factories' faker calls fail with "Unknown format").
 *
 * COVERAGE MAP — this file implements unit_test_cases 1, 2, 4–37 and 46–53
 * of the screen tech-spec. Each test carries its case number. The cases that
 * do NOT live here, and where they went instead:
 *   - case 3  (mount() guards admin itself) → Feature/Livewire/KelolaShiftTest
 *   - case 38 (component and controller share one coverage calculation)
 *     → asserted on BOTH sides: Feature/Api + Feature/Livewire
 *   - cases 39, 40, 44, 45 (component behaviour, blade ordering, badge text)
 *     → Feature/Livewire/KelolaShiftTest
 *   - cases 41, 42 (the Alpine preview inside the form) → e2e-web/tests/
 *     kelola-shift.spec.ts; the preview only exists in a real browser
 *   - case 43 (literal routes registered before /shifts/{id}) →
 *     Feature/Api/KelolaShiftTest, where the route table is the subject
 *
 * ── guardAdmin() RUNS AT THE TOP OF EVERY PUBLIC SERVICE METHOD ──────────
 *
 * Unlike PeriodService, this service enforces the admin rule ITSELF (third
 * layer, above the route middleware and above KelolaShift::mount()). Two
 * consequences for every test in this file:
 *
 *  1. The actor MUST be set. `actingAs($this->admin, 'web')` in beforeEach is
 *     not decoration — without it every single call aborts 403. (It would be
 *     needed anyway: `created_by` is taken from the session, never from the
 *     request.)
 *  2. A 403 raised HERE is an HttpException, so the API envelope carries
 *     `code: FORBIDDEN`. A 403 raised by the ROUTE middleware does not —
 *     App\Http\Middleware\EnsureRole builds its own JSON response and never
 *     reaches ApiExceptionHandler. That is why the API test file asserts
 *     status + message for role rejections and deliberately not `code`.
 *
 * ── THE ONE THING THESE TESTS EXIST TO PROTECT ──────────────────────────
 *
 * EVERY range comparison lives on a MINUTE AXIS 0..1440 whose zero is 07:00.
 * Comparing '23:00' with '07:00' as STRINGS concludes the night shift's range
 * is empty, so a night shift never registers as overlapping — and the wrong
 * answer looks entirely plausible. The test named
 * "perbandingan string jam TIDAK dipakai — kasus jebakan" (case 20) is
 * written so that it genuinely reddens a string-comparing implementation:
 * 01:00–03:00 against an existing 23:00–07:00 MUST be rejected, and the
 * overlap window it names must be 01:00–03:00. Same defect class as the slot
 * ordering bug in the Engine Room report.
 *
 * ── SPEC v2 (2026-10-16): TWO RULES CHANGED, AND BOTH CHANGED WHAT A
 *    PASSING TEST MEANS ────────────────────────────────────────────────
 *
 * 1. COINCIDING HOURS MEAN A 24-HOUR SHIFT. `start_time == end_time` is the
 *    only way to express 1440 minutes, so it is ACCEPTED on the write path —
 *    but only while the line has no OTHER LIVE shift. On a line that already
 *    holds one it is rejected as VALIDATION_ERROR on `end_time` with a
 *    zero-duration message, and DELIBERATELY NOT as SHIFT_OVERLAP: a 24-hour
 *    shift cannot sit beside any shift at all, so there it can only be a
 *    typo, and a typo is not an overlap. Case 23 asserts BOTH branches; case
 *    9 now goes through create() rather than the factory, because the write
 *    path itself produces the row that case used to have to smuggle in.
 *
 * 2. DELETION IS SOFT DELETE. All four live paths — the list, the
 *    coverage/gap computation, the form's live preview (shiftBlocksByLine)
 *    and THE OVERLAP COMPARISON SET — exclude closed rows, the last of those
 *    being the one most easily missed. Its failure mode is characteristic
 *    and is asserted head-on (cases 48, 49): if closed rows were still
 *    compared, the hours and the name just freed could never be reused by
 *    the replacement — and reusing them is precisely why soft delete was
 *    chosen over a hard delete.
 *
 * NOT BUILT IN THIS ROUND, AND NOT TESTED HERE: supersede-on-edit
 * (business_logic 29–30). Its trigger is "a record references this shift",
 * and no column in the system references a shift today — `shift_id` is born
 * with the record-tagging round. Editing hours therefore MUTATES the row, and
 * case 53 asserts exactly that: no extra row, no new closed row. There is no
 * test for supersede behaviour because there is no branch for one.
 */

use App\Enums\UserRole;
use App\Exceptions\ShiftOverlapException;
use App\Models\BusinessUnit;
use App\Models\ProductionLine;
use App\Models\Shift;
use App\Models\User;
use App\Services\ShiftService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new ShiftService;

    $this->millA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->millB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    // Named, not faker-numbered: listShifts() orders lines by name, and a
    // random name would make "the first coverage entry" mean a different line
    // from run to run.
    $this->lineA1 = ProductionLine::factory()->forBusinessUnit($this->millA)->create(['name' => 'Line 1']);
    $this->lineA2 = ProductionLine::factory()->forBusinessUnit($this->millA)->create(['name' => 'Line 2']);
    $this->lineB1 = ProductionLine::factory()->forBusinessUnit($this->millB)->create(['name' => 'Line B1']);

    $this->admin = User::factory()->role(UserRole::Admin)->create([
        'name' => 'Admin X',
        'business_unit_id' => null,
    ]);
    $this->supervisor = User::factory()->role(UserRole::Supervisor)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->create();

    // create()/update() stamp created_by/updated_by from auth()->id(), and
    // guardAdmin() rejects an absent session outright.
    $this->actingAs($this->admin, 'web');
});

/**
 * Valid create() payload for Line 1; override only what a test is about.
 * There is NO day, weekday or effective-date key — the schedule applies the
 * same way every day and that absence is deliberate (case 36/37).
 */
function shiftPayload(ProductionLine $line, array $overrides = []): array
{
    return array_merge([
        'production_line_id' => $line->id,
        'name' => 'Pagi',
        'start_time' => '07:00',
        'end_time' => '15:00',
    ], $overrides);
}

/**
 * Runs $callback with the query log on and returns every statement that
 * touched the `shifts` table. "Zero shift queries ran" is a claim several
 * cases make, and counting queries is the only way to prove it: a service
 * that validates AFTER querying gives the right answer for the wrong reason.
 *
 * @return list<string>
 */
function shiftQueriesDuring(callable $callback): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    try {
        $callback();
    } finally {
        $queries = collect(DB::getQueryLog())
            ->pluck('query')
            ->filter(fn (string $sql) => str_contains($sql, 'shifts'))
            ->values()
            ->all();

        DB::disableQueryLog();
        DB::flushQueryLog();
    }

    return $queries;
}

/** The HTTP status carried by an abort() — asserted, never assumed to be 403. */
function shiftAbortStatusOf(callable $callback): ?int
{
    try {
        $callback();
    } catch (HttpException $e) {
        return $e->getStatusCode();
    }

    return null;
}

/**
 * The coverage entry for one line out of a listShifts() result.
 *
 * @param  array<string, mixed>  $result
 * @return array<string, mixed>
 */
function shiftCoverageFor(array $result, ProductionLine $line): array
{
    $entry = collect($result['coverage'])->firstWhere('production_line_id', $line->id);

    expect($entry)->not->toBeNull();

    return $entry;
}

/**
 * Only the 'shift' blocks of a coverage entry, in payload order.
 *
 * @param  array<string, mixed>  $entry
 * @return list<array<string, mixed>>
 */
function shiftBlocksOf(array $entry): array
{
    return collect($entry['blocks'])->where('kind', 'shift')->values()->all();
}

/**
 * Only the 'gap' blocks of a coverage entry, in payload order.
 *
 * @param  array<string, mixed>  $entry
 * @return list<array<string, mixed>>
 */
function gapBlocksOf(array $entry): array
{
    return collect($entry['blocks'])->where('kind', 'gap')->values()->all();
}

/**
 * Blok tempat sebuah shift DIMULAI — `segment_index` 0, yang menurut
 * konstruksi selalu berawal tepat di offset jam mulainya.
 *
 * Dipakai untuk membandingkan urutan tabel dengan urutan timeline. Sejak
 * sumbu dimulai 00:00 (v3), shift yang melewati tengah malam punya DUA
 * potongan dan potongan keduanya jatuh di tepi KIRI batang — jadi urutan
 * seluruh blok tidak lagi sama dengan urutan baris tabel, dan memaksakannya
 * sama hanya bisa dilakukan dengan mengarang. Yang MASIH berlaku, dan yang
 * memang dimaksud aturannya, adalah urutan JAM MULAI shift-nya.
 *
 * @param  array<string, mixed>  $entry
 * @return list<array<string, mixed>>
 */
function startingShiftBlocksOf(array $entry): array
{
    return collect($entry['blocks'])
        ->where('kind', 'shift')
        ->where('segment_index', 0)
        ->values()
        ->all();
}

/**
 * INVARIAN PAYLOAD v3, diasersi atas SETIAP blok di SETIAP line: tidak ada
 * blok yang melewati tepi sumbu.
 *
 * Satu asersi menyeluruh ini menutup SELURUH kelas cacat yang dilaporkan
 * pemilik produk 2026-10-16 — bukan hanya kasus yang kebetulan ditulis. Blok
 * yang start_offset + duration-nya lebih dari 1440 digambar `left+width`
 * melewati tepi kanan `.ks-track` yang ber-`overflow: hidden`, sehingga ia
 * DIPOTONG DIAM-DIAM sementara sisanya di tepi kiri tidak pernah tergambar.
 * Implementasi satu-blok sebelum v3 memerah di sini.
 *
 * @param  array<string, mixed>  $entry
 */
function expectNoBlockPastAxisEnd(array $entry): void
{
    foreach ($entry['blocks'] as $block) {
        $end = $block['start_offset_minutes'] + $block['duration_minutes'];

        expect($block['start_offset_minutes'])->toBeGreaterThanOrEqual(0);
        expect($block['duration_minutes'])->toBeGreaterThan(0);
        expect($end)->toBeLessThanOrEqual(
            ShiftService::DAY_MINUTES,
            sprintf(
                'blok %s mulai %d durasi %d berakhir di %d — melewati tepi sumbu dan akan dipotong overflow hidden',
                $block['label'] ?? '(tanpa label)',
                $block['start_offset_minutes'],
                $block['duration_minutes'],
                $end
            )
        );
    }
}

// ── Peran (cases 1, 2) ──────────────────────────────────────────────────

// Case 1 — THREE roles, SIX surfaces, and zero shift queries on each.
it('menolak 403 FORBIDDEN pada seluruh metode service untuk peran selain admin', function (string $role) {
    $other = ProductionLine::factory()->forBusinessUnit($this->millA)->create(['name' => 'Line 9']);
    $existing = Shift::factory()->forProductionLine($other)->pagi()->create();

    $actor = match ($role) {
        'supervisor' => $this->supervisor,
        'mill_management' => $this->millManagement,
        'operator' => $this->operator,
    };

    $this->actingAs($actor, 'web');

    $calls = [
        'businessUnitOptions' => fn () => $this->service->businessUnitOptions(),
        'productionLineOptions' => fn () => $this->service->productionLineOptions($this->millA->id),
        'listShifts' => fn () => $this->service->listShifts($this->millA->id),
        'shiftBlocksByLine' => fn () => $this->service->shiftBlocksByLine($this->millA->id),
        'create' => fn () => $this->service->create(shiftPayload($this->lineA1)),
        'update' => fn () => $this->service->update($existing->id, ['name' => 'Diubah']),
        'delete' => fn () => $this->service->delete($existing->id),
    ];

    foreach ($calls as $label => $call) {
        $queries = [];

        $status = shiftAbortStatusOf(function () use ($call, &$queries) {
            $queries = shiftQueriesDuring($call);
        });

        expect($status)->toBe(403, "$label seharusnya 403 untuk peran $role");
        expect($queries)->toBe([], "$label menjalankan kueri shift untuk peran $role");
    }

    // Nothing was written and nothing was removed.
    expect(Shift::count())->toBe(1);
    expect(Shift::find($existing->id)->name)->toBe('Pagi');
})->with([
    'supervisor' => ['supervisor'],
    'mill management' => ['mill_management'],
    'operator' => ['operator'],
]);

/**
 * Case 2 (service half) — no session at all. guardAdmin() reads
 * auth()->user() and aborts before any query; the HTTP 401 that the same
 * condition produces through the route lives in
 * tests/Feature/Api/KelolaShiftTest.php, because 401 is the middleware's
 * answer and not this service's.
 */
it('menolak tanpa sesi sebelum satu kueri shift pun berjalan', function () {
    $this->app['auth']->forgetGuards();

    $queries = [];

    $status = shiftAbortStatusOf(function () use (&$queries) {
        $queries = shiftQueriesDuring(fn () => $this->service->listShifts($this->millA->id));
    });

    expect($status)->toBe(403);
    expect($queries)->toBe([]);
});

// ── Rumus menit — SUMBU 0..1440 DARI 00:00 (case 7) ─────────────────────

// Case 7 (formula half) — REVISI v3 2026-10-16: titik nol 07:00 → 00:00.
it('mengukur offset dari 00:00, dan 07:00 jatuh di menit 420', function () {
    expect(ShiftService::DAY_MINUTES)->toBe(1440);

    expect(ShiftService::offsetOnAxis('00:00'))->toBe(0);
    expect(ShiftService::offsetOnAxis('07:00'))->toBe(420);
    expect(ShiftService::offsetOnAxis('15:00'))->toBe(900);
    expect(ShiftService::offsetOnAxis('23:00'))->toBe(1380);
    expect(ShiftService::offsetOnAxis('06:00'))->toBe(360);

    // Offset adalah PERAN (posisi pada sumbu gambar), minuteOfDay adalah
    // pembacaan format. Keduanya kini bernilai sama, dan nilai itulah yang
    // diasersi — bukan bahwa keduanya fungsi yang sama, karena yang pertama
    // sudah sekali berubah dan dapat berubah lagi.
    expect(ShiftService::offsetOnAxis('23:00:00'))->toBe(1380);

    // Both column shapes read the same: PostgreSQL returns TIME as 'HH:MM:SS'
    // while SQLite returns whatever was stored.
    expect(ShiftService::minuteOfDay('23:00'))->toBe(1380);
    expect(ShiftService::minuteOfDay('23:00:00'))->toBe(1380);
    expect(ShiftService::displayTime('23:00:00'))->toBe('23:00');

    expect(ShiftService::timeFromOffset(0))->toBe('00:00');
    expect(ShiftService::timeFromOffset(420))->toBe('07:00');
    expect(ShiftService::timeFromOffset(900))->toBe('15:00');
    expect(ShiftService::timeFromOffset(1380))->toBe('23:00');
    // Tepi kanan sumbu dibaca sebagai 00:00 — label celah '22:00–00:00'
    // adalah bentuk yang benar untuk celah yang berakhir di tepi batang.
    expect(ShiftService::timeFromOffset(1440))->toBe('00:00');

    // Konstanta 420 TIDAK ADA LAGI di kelas ini: awal hari produksi 07:00
    // adalah urusan kisi slot stasiun bergrid, bukan urusan shift.
    expect(method_exists(ShiftService::class, 'offsetFromProductionDayStart'))->toBeFalse();
    expect(defined(ShiftService::class.'::PRODUCTION_DAY_START_MINUTE'))->toBeFalse();
});

it('menghitung durasi melintasi tengah malam, dan membaca jam berimpit sebagai 1440', function () {
    expect(ShiftService::durationMinutes('07:00', '15:00'))->toBe(480);
    expect(ShiftService::durationMinutes('15:00', '23:00'))->toBe(480);
    // Bukan negatif dan bukan nol.
    expect(ShiftService::durationMinutes('23:00', '07:00'))->toBe(480);
    expect(ShiftService::durationMinutes('22:00', '02:00'))->toBe(240);
    // PEMBACAAN jam berimpit = satu hari penuh. Jalur TULIS menolaknya
    // (case 23) — ini bentuk bacanya, supaya baris semacam itu tidak
    // menghilang dari timeline sebagai blok tak berdurasi.
    expect(ShiftService::durationMinutes('07:00', '07:00'))->toBe(1440);
    expect(ShiftService::durationMinutes('23:00', '23:00:00'))->toBe(1440);
});

it('memecah shift yang melintasi titik nol sumbu, dan hanya yang melintasinya', function () {
    // 07:00–15:00 → satu segmen.
    expect(ShiftService::segmentsFor(420, 480))->toBe([[420, 900]]);
    // 00:00–08:00 → satu segmen yang berawal tepat di titik nol.
    expect(ShiftService::segmentsFor(0, 480))->toBe([[0, 480]]);
    // 16:00–24:00 → satu segmen yang berakhir tepat di tepi kanan.
    expect(ShiftService::segmentsFor(960, 480))->toBe([[960, 1440]]);
    // 23:00–07:00 → MELINTASI titik nol, jadi DUA segmen.
    expect(ShiftService::segmentsFor(1380, 480))->toBe([[1380, 1440], [0, 420]]);
    // Shift 24 jam yang tidak mulai 00:00 → dua segmen yang bersama-sama
    // menutup seluruh sumbu, tanpa satu pun kasus khusus di fungsinya.
    expect(ShiftService::segmentsFor(420, 1440))->toBe([[420, 1440], [0, 420]]);
    // Shift 24 jam yang mulai 00:00 → satu segmen selebar sumbu penuh.
    expect(ShiftService::segmentsFor(0, 1440))->toBe([[0, 1440]]);
});

it('menghitung irisan nol untuk rentang yang hanya bersentuhan di ujung', function () {
    // Pergantian shift normal: 07–15 lalu 15–23.
    expect(ShiftService::intersectionMinutes([0, 480], [480, 960]))->toBe(0);
    // Tindihan satu menit.
    expect(ShiftService::intersectionMinutes([0, 480], [479, 960]))->toBe(1);
    // Terpisah jauh.
    expect(ShiftService::intersectionMinutes([0, 480], [600, 700]))->toBe(0);
    // Tertanam seluruhnya.
    expect(ShiftService::intersectionMinutes([0, 480], [100, 200]))->toBe(100);
});

// ── Pemilih (cases 18, 33, 34) ──────────────────────────────────────────

// Case 34
it('memulangkan options mill kosong ketika master business_units kosong', function () {
    Shift::query()->delete();
    ProductionLine::query()->delete();
    BusinessUnit::query()->delete();

    expect($this->service->businessUnitOptions())->toBe([]);
});

it('memulangkan options mill urut nama', function () {
    // UserFactory membuat satu mill sendiri per pengguna, jadi master mill
    // memuat lebih banyak baris daripada kedua mill yang dibuat test ini.
    // Yang diuji adalah PENGURUTANNYA dan keikutsertaan keduanya, bukan
    // jumlah barisnya — daftar yang dipaku akan memerah setiap kali sebuah
    // test menambah pengguna.
    $names = array_column($this->service->businessUnitOptions(), 'name');

    $sorted = $names;
    sort($sorted, SORT_STRING);

    expect($names)->toBe($sorted);
    expect($names)->toContain('Mill Alpha');
    expect($names)->toContain('Mill Beta');
});

// Case 18 (options half)
it('menolak 422 dan nol kueri shift ketika business_unit_id absen pada options line', function () {
    $queries = shiftQueriesDuring(function () {
        expect(fn () => $this->service->productionLineOptions(null))
            ->toThrow(ValidationException::class);
        // '' dibaca sebagai "belum dipilih", bukan sebagai mill bernama
        // string kosong — kolom uuid PostgreSQL menolak string kosong.
        expect(fn () => $this->service->productionLineOptions(''))
            ->toThrow(ValidationException::class);
    });

    expect($queries)->toBe([]);
});

it('memulangkan hanya line milik mill yang diminta', function () {
    expect(array_column($this->service->productionLineOptions($this->millA->id), 'name'))
        ->toBe(['Line 1', 'Line 2']);
    expect(array_column($this->service->productionLineOptions($this->millB->id), 'name'))
        ->toBe(['Line B1']);
    // id yang bentuknya bukan UUID tidak pernah sampai ke kueri: whereKey()
    // atas kolom uuid PostgreSQL melempar SQLSTATE 22P02 dan SQLite
    // menelannya diam-diam.
    expect($this->service->productionLineOptions('bu-tanpa-line'))->toBe([]);
});

// ── Daftar + cakupan (cases 4, 5, 6, 7, 8, 9, 10, 11, 13, 14, 15, 16, 33, 35)

// Case 4
it('menolak 422 VALIDATION_ERROR dan nol kueri shift ketika business_unit_id absen', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    $queries = shiftQueriesDuring(function () {
        expect(fn () => $this->service->listShifts(null))->toThrow(ValidationException::class);
    });

    expect($queries)->toBe([]);
});

// Case 5
it('menolak 422 VALIDATION_ERROR ketika production_line_id milik mill lain, bukan 403', function () {
    $thrown = null;

    try {
        $this->service->listShifts($this->millA->id, $this->lineB1->id);
    } catch (ValidationException $e) {
        $thrown = $e;
    }

    expect($thrown)->not->toBeNull();
    expect($thrown->errors())->toHaveKey('production_line_id');
    // Bukan HttpException 403: bagi Admin itu parameter yang keliru, bukan
    // penolakan akses.
    expect($thrown)->not->toBeInstanceOf(HttpException::class);
});

it('menolak 422 ketika production_line_id bukan UUID', function () {
    expect(fn () => $this->service->listShifts($this->millA->id, 'pl-001'))
        ->toThrow(ValidationException::class);
});

// Case 6
it('memuat seluruh line mill itu ketika production_line_id absen', function () {
    $lineA3 = ProductionLine::factory()->forBusinessUnit($this->millA)->create(['name' => 'Line 3']);

    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();
    Shift::factory()->forProductionLine($this->lineA2)->siang()->create();
    Shift::factory()->forProductionLine($lineA3)->malam()->create();
    // Line mill LAIN tidak pernah ikut.
    Shift::factory()->forProductionLine($this->lineB1)->pagi()->create();

    $result = $this->service->listShifts($this->millA->id);

    expect(array_column($result['coverage'], 'production_line_name'))
        ->toBe(['Line 1', 'Line 2', 'Line 3']);
    expect($result['data'])->toHaveCount(3);
    expect(array_column($result['data'], 'name'))->toBe(['Pagi', 'Siang', 'Malam']);
    expect($result['meta']['total'])->toBe(3);
});

// Case 7 (payload half)
it('menerbitkan start_offset_minutes dan duration_minutes dari sumbu 00:00', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();
    Shift::factory()->forProductionLine($this->lineA2)->malam()->create();

    $result = $this->service->listShifts($this->millA->id);

    $pagi = collect($result['data'])->firstWhere('name', 'Pagi');
    $malam = collect($result['data'])->firstWhere('name', 'Malam');

    expect($pagi['start_offset_minutes'])->toBe(420);
    expect($pagi['duration_minutes'])->toBe(480);
    expect($pagi['crosses_midnight'])->toBeFalse();
    expect($pagi['start_time'])->toBe('07:00');
    expect($pagi['end_time'])->toBe('15:00');
    expect($pagi['time_label'])->toBe('07:00 — 15:00');

    expect($malam['start_offset_minutes'])->toBe(1380);
    expect($malam['duration_minutes'])->toBe(480);
    expect($malam['crosses_midnight'])->toBeTrue();
    // Jam selesai yang lebih kecil ditulis '+1' supaya tidak terbaca sebagai
    // salah input.
    expect($malam['time_label'])->toBe('23:00 — 07:00 +1');
    // Teks jam durasi dihitung di service, supaya blade tidak melakukan satu
    // pun aritmetika jam.
    expect($malam['duration_hours_text'])->toBe('8');
});

/**
 * Case 8 — REVISI v3 2026-10-16, dan asersinya DIBALIK dari v2.
 *
 * v2 menuntut SATU blok berdurasi utuh. Tuntutan itulah cacatnya: satu blok
 * 23:00–07:00 pada sumbu 00:00 berarti start 1380 + durasi 480 = 1860, dan
 * blade menggambarnya `left:95,8%;width:33,3%` — melewati tepi kanan
 * `.ks-track` yang ber-`overflow: hidden`, sehingga 420 menit terakhirnya
 * DIPOTONG DIAM-DIAM dan tidak pernah tergambar di tepi kiri. Cacat itu ada
 * pula pada sumbu 07:00, hanya korbannya berbeda (shift yang melintasi 07:00).
 */
it('menerbitkan shift lintas tengah malam sebagai DUA segmen bertaut, bukan satu blok', function () {
    $malam = Shift::factory()->forProductionLine($this->lineA1)->malam()->create();

    $entry = shiftCoverageFor($this->service->listShifts($this->millA->id, $this->lineA1->id), $this->lineA1);

    $blocks = shiftBlocksOf($entry);

    expect($blocks)->toHaveCount(2);
    expect(array_column($blocks, 'shift_id'))->toBe([$malam->id, $malam->id]);

    // Urut offset: potongan tepi KIRI lebih dulu di payload, meski secara jam
    // ia potongan KEDUA — karena itulah segment_index ada.
    expect($blocks[0]['start_offset_minutes'])->toBe(0);
    expect($blocks[0]['duration_minutes'])->toBe(420);
    expect($blocks[0]['segment_index'])->toBe(1);

    expect($blocks[1]['start_offset_minutes'])->toBe(1380);
    expect($blocks[1]['duration_minutes'])->toBe(60);
    expect($blocks[1]['segment_index'])->toBe(0);

    // Jumlah durasi kedua segmen = durasi shift. Tak ada menit yang hilang
    // dan tak ada yang dihitung dua kali.
    expect(array_sum(array_column($blocks, 'duration_minutes')))->toBe(480);

    // crosses_midnight TRUE pada KEDUANYA — tanpa itu blade kehilangan dasar
    // untuk menandai keduanya satu shift.
    expect(array_column($blocks, 'crosses_midnight'))->toBe([true, true]);
    expect(array_column($blocks, 'segment_count'))->toBe([2, 2]);

    // Label pada potongan yang LEBIH PANJANG (420 menit), supaya namanya
    // terbaca alih-alih terpotong di potongan 60 menit.
    expect(array_column($blocks, 'is_label_segment'))->toBe([true, false]);

    // Dan inilah asersi yang memerah terhadap implementasi v2.
    expectNoBlockPastAxisEnd($entry);
});

/**
 * Case 9 — LEWAT create(), BUKAN LEWAT FACTORY (revisi spec v2 2026-10-16).
 *
 * Case ini DULU ditanam lewat factory justru untuk MELEWATI jalur tulis, yang
 * saat itu menolak 07:00–07:00 tanpa syarat. Jalur tulis kini MENGHASILKAN
 * baris ini sendiri pada line yang belum punya shift hidup lain, jadi menanam
 * lewat factory akan melewati satu-satunya hal yang berubah — dan sebuah test
 * yang melewati perubahannya hijau tanpa membuktikan apa pun. Cabang
 * penolakannya (line yang sudah terisi) ada di case 23.
 */
it('menyimpan satu shift 07:00–07:00 lewat create() dan membacanya sebagai satu blok 1440 menit', function () {
    $created = $this->service->create(shiftPayload($this->lineA1, [
        'name' => 'Penuh',
        'start_time' => '07:00',
        'end_time' => '07:00',
    ]));

    // Jalur tulis TIDAK menolak, dan durasinya terbaca 1440 — bukan 0.
    expect($created['duration_minutes'])->toBe(1440);
    expect(Shift::whereKey($created['id'])->exists())->toBeTrue();

    $entry = shiftCoverageFor($this->service->listShifts($this->millA->id, $this->lineA1->id), $this->lineA1);

    expect($entry['state'])->toBe('covered');
    expect($entry['uncovered_minutes'])->toBe(0);
    expect($entry['shift_count'])->toBe(1);
    expect(gapBlocksOf($entry))->toBe([]);

    // Pada sumbu 00:00 shift 07:00–07:00 MELINTASI titik nol, jadi ia keluar
    // sebagai dua segmen bertaut — dan keduanya bersama-sama menutup setiap
    // menit sumbu. Tidak ada kasus khusus untuk "satu blok penuh": kasus
    // khusus hanya menambah cabang yang dapat salah, sementara batang yang
    // tergambar sama penuhnya.
    $blocks = shiftBlocksOf($entry);

    expect($blocks)->toHaveCount(2);
    expect(array_sum(array_column($blocks, 'duration_minutes')))->toBe(1440);
    expect(array_column($blocks, 'start_offset_minutes'))->toBe([0, 420]);
    expect(array_column($blocks, 'duration_minutes'))->toBe([420, 1020]);
    expectNoBlockPastAxisEnd($entry);
});

// Case 10
it('melaporkan cakupan penuh oleh tiga shift tanpa satu pun blok gap', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();
    Shift::factory()->forProductionLine($this->lineA1)->siang()->create();
    Shift::factory()->forProductionLine($this->lineA1)->malam()->create();

    $entry = shiftCoverageFor($this->service->listShifts($this->millA->id, $this->lineA1->id), $this->lineA1);

    expect($entry['state'])->toBe('covered');
    expect($entry['uncovered_minutes'])->toBe(0);
    expect($entry['uncovered_hours_text'])->toBe('0');
    expect($entry['shift_count'])->toBe(3);
    expect(gapBlocksOf($entry))->toBe([]);

    // EMPAT blok untuk TIGA shift: Malam menyumbang dua segmen, dan yang
    // kedua jatuh di tepi kiri batang. Payload urut offset, jadi urutannya
    // mulai dari potongan Malam itu.
    expect(array_column(shiftBlocksOf($entry), 'label'))->toBe(['Malam', 'Pagi', 'Siang', 'Malam']);
    expect(array_column(shiftBlocksOf($entry), 'start_offset_minutes'))->toBe([0, 420, 900, 1380]);
    expect(array_column(shiftBlocksOf($entry), 'duration_minutes'))->toBe([420, 480, 480, 60]);

    // Urutan JAM MULAI — yang memang dimaksud aturan "urutan tabel dan
    // timeline selalu sama" — tetap Pagi, Siang, Malam.
    expect(array_column(startingShiftBlocksOf($entry), 'label'))->toBe(['Pagi', 'Siang', 'Malam']);

    expectNoBlockPastAxisEnd($entry);
});

// Case 11
it('menerbitkan celah sebagai blok ber-kind gap dengan offset dan durasinya', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();
    Shift::factory()->forProductionLine($this->lineA1)->named('Malam')->range('19:00:00', '07:00:00')->create();

    $entry = shiftCoverageFor($this->service->listShifts($this->millA->id, $this->lineA1->id), $this->lineA1);

    expect($entry['state'])->toBe('gap');
    expect($entry['uncovered_minutes'])->toBe(240);
    // Angka jamnya DISEBUT, bukan hanya 'ada celah'.
    expect($entry['uncovered_hours_text'])->toBe('4');

    $gaps = gapBlocksOf($entry);

    expect($gaps)->toHaveCount(1);
    expect($gaps[0]['start_offset_minutes'])->toBe(900);
    expect($gaps[0]['duration_minutes'])->toBe(240);
    expect($gaps[0]['shift_id'])->toBeNull();
    expect($gaps[0]['time_label'])->toBe('15:00–19:00');

    // Blok tersusun urut offset: potongan Malam di tepi kiri (00:00–07:00),
    // Pagi, celah, lalu potongan Malam yang mulai 19:00.
    expect(array_column($entry['blocks'], 'kind'))->toBe(['shift', 'shift', 'gap', 'shift']);
    expectNoBlockPastAxisEnd($entry);
});

// Case 12
it('tidak pernah menolak celah di lapisan data', function () {
    $this->service->create(shiftPayload($this->lineA1));

    // Satu shift 8 jam meninggalkan celah 16 jam, dan itu tersimpan.
    $entry = shiftCoverageFor($this->service->listShifts($this->millA->id, $this->lineA1->id), $this->lineA1);

    expect(Shift::count())->toBe(1);
    expect($entry['state'])->toBe('gap');
    expect($entry['uncovered_minutes'])->toBe(960);
    expect($entry['uncovered_hours_text'])->toBe('16');
});

/**
 * Case 13 — 'unconfigured' BUKAN 'gap' 1440. Perbedaannya bermakna: yang
 * pertama belum dikerjakan, yang kedua keputusan yang mungkin salah. Nol blok
 * diterbitkan dan uncovered_minutes 0, supaya tidak ada badge yang menyebut
 * angka jam untuk line yang belum dikerjakan.
 */
it('menandai line tanpa shift sebagai unconfigured, bukan gap 1440 menit', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    $result = $this->service->listShifts($this->millA->id);
    $entry = shiftCoverageFor($result, $this->lineA2);

    expect($entry['state'])->toBe('unconfigured');
    expect($entry['shift_count'])->toBe(0);
    expect($entry['uncovered_minutes'])->toBe(0);
    expect($entry['uncovered_hours_text'])->toBe('0');
    expect($entry['blocks'])->toBe([]);
    expect($entry['state'])->not->toBe('gap');

    // Dan line yang PUNYA celah tetap 'gap' dengan angkanya — keduanya
    // dibedakan di payload, bukan hanya di layar.
    expect(shiftCoverageFor($result, $this->lineA1)['state'])->toBe('gap');
    expect(shiftCoverageFor($result, $this->lineA1)['uncovered_minutes'])->toBe(960);
});

/**
 * Case 14 — REVISI v3 2026-10-16: `starts_at_production_day` DICABUT
 * ("dibuang saja", pemilik produk). Keselarasan jadwal shift dengan awal hari
 * produksi 07:00 tidak memengaruhi apa pun secara fungsional, sehingga
 * menandainya hanya menanam kecemasan tanpa tindakan yang dapat diambil.
 *
 * KETIADAANNYA DIASERSI ATAS NAMA KUNCI, lewat penelusur rekursif — bukan
 * dengan memindai teks payload. Payload ini memuat prosa (label, time_label),
 * dan sebuah pemindaian teks akan memerah terhadap kode yang justru benar
 * begitu ada label yang menyebut "hari produksi". Pelajaran yang sama sudah
 * dibayar di laporan Engine Room.
 */
it('tidak menerbitkan kunci starts_at_production_day di mana pun pada payload', function () {
    Shift::factory()->forProductionLine($this->lineA1)->named('A')->range('06:00:00', '14:00:00')->create();
    Shift::factory()->forProductionLine($this->lineA1)->named('B')->range('14:00:00', '22:00:00')->create();
    Shift::factory()->forProductionLine($this->lineA1)->named('C')->range('22:00:00', '06:00:00')->create();

    $result = $this->service->listShifts($this->millA->id, $this->lineA1->id);
    $entry = shiftCoverageFor($result, $this->lineA1);

    // Cakupan penuh dinyatakan tanpa syarat tambahan apa pun tentang jam
    // berapa jadwalnya dimulai.
    expect($entry['state'])->toBe('covered');
    expect($entry['uncovered_minutes'])->toBe(0);
    expect(Shift::count())->toBe(3);

    $walk = function (mixed $node) use (&$walk): array {
        $found = [];

        if (is_array($node)) {
            foreach ($node as $key => $value) {
                if (is_string($key)) {
                    $found[] = $key;
                }

                $found = array_merge($found, $walk($value));
            }
        }

        return $found;
    };

    $keys = $walk($result);

    // Penelusurnya benar-benar menelusuri — kalau tidak, asersi ketiadaan di
    // bawahnya lolos tanpa membuktikan apa pun.
    expect($keys)->toContain('blocks');
    expect($keys)->toContain('start_offset_minutes');
    expect($keys)->not->toContain('starts_at_production_day');

    // Dan juga tidak muncul sebagai kunci pada line yang belum diatur.
    $unconfigured = shiftCoverageFor($this->service->listShifts($this->millA->id), $this->lineA2);
    expect(array_keys($unconfigured))->not->toContain('starts_at_production_day');
});

// Case 15
it('menurunkan position dari jam mulai pada sumbu 00:00, bukan dari urutan penambahan', function () {
    // Urutan penambahan SENGAJA acak, dan nama abjadnya pun tidak selaras
    // dengan urutan jamnya.
    Shift::factory()->forProductionLine($this->lineA1)->malam()->create();
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();
    Shift::factory()->forProductionLine($this->lineA1)->siang()->create();

    $rows = collect($this->service->listShifts($this->millA->id, $this->lineA1->id)['data']);

    expect($rows->pluck('position', 'name')->all())->toBe([
        'Pagi' => 0,
        'Siang' => 1,
        'Malam' => 2,
    ]);
    // Bukan urutan penambahan (Malam dulu) dan bukan abjad (Malam, Pagi,
    // Siang).
    expect($rows->pluck('name')->all())->toBe(['Pagi', 'Siang', 'Malam']);
});

// Case 16
it('memakai satu nilai position yang sama untuk urutan data dan urutan blocks', function () {
    Shift::factory()->forProductionLine($this->lineA1)->malam()->create();
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();
    Shift::factory()->forProductionLine($this->lineA1)->siang()->create();

    $result = $this->service->listShifts($this->millA->id, $this->lineA1->id);
    $entry = shiftCoverageFor($result, $this->lineA1);

    $dataIds = array_column($result['data'], 'id');

    // DIBANDINGKAN PADA BLOK TEMPAT SHIFT DIMULAI, bukan pada seluruh blok.
    // Sejak sumbu dimulai 00:00, Malam punya potongan kedua di tepi kiri
    // batang, jadi urutan seluruh blok memang tidak lagi sama dengan urutan
    // baris tabel — dan tidak bisa sama pada sumbu linier mana pun. Yang
    // dimaksud aturannya, dan yang masih berlaku, adalah urutan jam mulainya.
    expect(array_column(startingShiftBlocksOf($entry), 'shift_id'))->toBe($dataIds);

    // Dan keduanya memang urut position, bukan sekadar kebetulan sama.
    expect(array_column($result['data'], 'position'))->toBe([0, 1, 2]);

    // Potongan tambahan itu memang ada, dan ia milik shift yang sama —
    // asersi di atas tidak lolos karena potongannya hilang.
    expect(shiftBlocksOf($entry))->toHaveCount(4);
    expect(collect(shiftBlocksOf($entry))->where('segment_index', 1)->pluck('shift_id')->all())
        ->toBe([$dataIds[2]]);
});

// Case 33
it('memulangkan data dan coverage kosong dengan 200 untuk mill tanpa production line', function () {
    $millKosong = BusinessUnit::factory()->create(['name' => 'Mill Tanpa Line']);

    $result = $this->service->listShifts($millKosong->id);

    expect($result['data'])->toBe([]);
    expect($result['coverage'])->toBe([]);
    expect($result['meta']['total'])->toBe(0);

    // Mill yang tidak dikenal — dan id yang bentuknya bukan UUID — juga
    // merangkum kosong, bukan 404: endpoint ini MERANGKUM, ia tidak mengambil
    // satu resource.
    expect($this->service->listShifts('bu-tanpa-line')['coverage'])->toBe([]);
    expect($this->service->listShifts('bu-tanpa-line')['data'])->toBe([]);
});

/**
 * Case 35 — data BERPAGINASI, coverage TIDAK. Sebuah panel yang ikut
 * berpindah halaman akan mengatakan hal berbeda tentang line yang sama
 * tergantung halaman mana yang terbuka.
 */
it('memaginasi data sementara coverage memuat seluruh line tanpa dipotong halaman', function () {
    $lineA3 = ProductionLine::factory()->forBusinessUnit($this->millA)->create(['name' => 'Line 3']);

    foreach ([$this->lineA1, $this->lineA2, $lineA3] as $line) {
        Shift::factory()->forProductionLine($line)->pagi()->create();
        Shift::factory()->forProductionLine($line)->siang()->create();
        Shift::factory()->forProductionLine($line)->malam()->create();
    }

    $page1 = $this->service->listShifts($this->millA->id, null, 1, 4);
    $page2 = $this->service->listShifts($this->millA->id, null, 2, 4);
    $page3 = $this->service->listShifts($this->millA->id, null, 3, 4);

    expect($page1['data'])->toHaveCount(4);
    expect($page2['data'])->toHaveCount(4);
    expect($page3['data'])->toHaveCount(1);

    expect($page1['meta']['page'])->toBe(1);
    expect($page1['meta']['per_page'])->toBe(4);
    expect($page1['meta']['total'])->toBe(9);
    expect($page1['meta']['total_pages'])->toBe(3);
    expect($page2['meta']['page'])->toBe(2);

    // coverage sama persis di setiap halaman: tiga line, sembilan shift.
    foreach ([$page1, $page2, $page3] as $page) {
        expect($page['coverage'])->toHaveCount(3);
        expect(array_sum(array_column($page['coverage'], 'shift_count')))->toBe(9);
    }

    // Halaman di luar rentang: data kosong, coverage tetap utuh.
    $page9 = $this->service->listShifts($this->millA->id, null, 9, 4);
    expect($page9['data'])->toBe([]);
    expect($page9['coverage'])->toHaveCount(3);
});

it('membatasi per_page maksimal 100 dan page minimal 1', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    expect($this->service->listShifts($this->millA->id, null, 1, 500)['meta']['per_page'])->toBe(100);
    expect($this->service->listShifts($this->millA->id, null, 0, 20)['meta']['page'])->toBe(1);
    expect($this->service->listShifts($this->millA->id, null, -3, 20)['meta']['page'])->toBe(1);
});

// ── shiftBlocksByLine() — sumber data pratinjau di dalam form ───────────

it('memulangkan blok shift seluruh line mill untuk pratinjau, termasuk line tanpa shift', function () {
    Shift::factory()->forProductionLine($this->lineA1)->malam()->create();

    $blocks = $this->service->shiftBlocksByLine($this->millA->id);

    // Satu kunci per line mill itu — line tanpa shift TETAP hadir dengan
    // daftar kosong, supaya pratinjau tidak kehilangan line yang dipilih.
    expect(array_keys($blocks))->toHaveCount(2);
    expect($blocks)->toHaveKey($this->lineA1->id);
    expect($blocks)->toHaveKey($this->lineA2->id);
    expect($blocks[$this->lineA2->id])->toBe([]);

    // Bentuknya sama persis dengan coverage[].blocks ber-kind 'shift', supaya
    // pratinjau memakai angka yang sama dengan yang nanti diterbitkan server.
    // Termasuk SEGMENTASINYA: Malam 23:00–07:00 keluar sebagai dua potongan
    // di sini juga, karena pratinjau menggambar blok yang sudah ada dari
    // daftar ini — bila di sini ia utuh sementara di coverage tersegmentasi,
    // satu layar akan memuat dua dasar yang berbeda, dan itulah cacat v2.
    expect($blocks[$this->lineA1->id])->toHaveCount(2);
    expect(array_column($blocks[$this->lineA1->id], 'kind'))->toBe(['shift', 'shift']);
    expect(array_column($blocks[$this->lineA1->id], 'start_offset_minutes'))->toBe([1380, 0]);
    expect(array_column($blocks[$this->lineA1->id], 'duration_minutes'))->toBe([60, 420]);
    expect(array_column($blocks[$this->lineA1->id], 'segment_index'))->toBe([0, 1]);
    expect(array_column($blocks[$this->lineA1->id], 'crosses_midnight'))->toBe([true, true]);

    foreach ($blocks[$this->lineA1->id] as $block) {
        expect($block['start_offset_minutes'] + $block['duration_minutes'])
            ->toBeLessThanOrEqual(ShiftService::DAY_MINUTES);
    }

    // Mill wajib dipilih di sini juga.
    expect(fn () => $this->service->shiftBlocksByLine(null))->toThrow(ValidationException::class);
    expect($this->service->shiftBlocksByLine('bu-tanpa-line'))->toBe([]);
});

// ── Tumpang tindih (cases 17, 18, 19, 20, 21) ───────────────────────────

/**
 * Case 17 — IRISAN NOL MENIT DITERIMA. Shift yang berakhir tepat pada jam
 * mulai shift lain adalah pergantian shift normal. Batas inklusifnya hidup di
 * `intersectionMinutes() > 0` dan hanya di sana.
 */
it('menerima pergantian shift berimpit karena irisannya nol menit', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    expect($this->service->findOverlap($this->lineA1->id, '15:00', '23:00'))->toBeNull();

    $created = $this->service->create(shiftPayload($this->lineA1, [
        'name' => 'Siang',
        'start_time' => '15:00',
        'end_time' => '23:00',
    ]));

    expect($created['name'])->toBe('Siang');
    expect(Shift::count())->toBe(2);

    // Dua blok bersebelahan, NOL blok gap di antaranya — yang tersisa hanya
    // celah 23:00–07:00, dan pada sumbu 00:00 celah itu melintasi titik nol
    // sehingga ia pun keluar sebagai DUA potongan di kedua tepi batang.
    $entry = shiftCoverageFor($this->service->listShifts($this->millA->id, $this->lineA1->id), $this->lineA1);
    expect(array_column($entry['blocks'], 'kind'))->toBe(['gap', 'shift', 'shift', 'gap']);
    expect(array_column($entry['blocks'], 'start_offset_minutes'))->toBe([0, 420, 900, 1380]);
    expect($entry['uncovered_minutes'])->toBe(480);
    expect(array_column(gapBlocksOf($entry), 'duration_minutes'))->toBe([420, 60]);
    // Tidak ada celah di ANTARA Pagi dan Siang — itu yang dibuktikan di sini.
    expect(collect(gapBlocksOf($entry))->firstWhere('start_offset_minutes', 900))->toBeNull();
    expectNoBlockPastAxisEnd($entry);

    // Dan juga ke arah sebaliknya: shift yang BERAKHIR tepat di jam mulai
    // shift yang sudah ada.
    expect($this->service->findOverlap($this->lineA1->id, '03:00', '07:00'))->toBeNull();
});

// Case 18
it('menolak tumpang tindih satu menit dan menyebut nama shift serta rentang satu menitnya', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    $overlap = $this->service->findOverlap($this->lineA1->id, '14:59', '23:00');

    expect($overlap)->not->toBeNull();
    expect($overlap['shift']->name)->toBe('Pagi');
    // 14:59 → 899, 15:00 → 900 pada sumbu menit-ke-dalam-hari.
    expect($overlap['start_offset_minutes'])->toBe(899);
    expect($overlap['end_offset_minutes'])->toBe(900);

    $thrown = null;

    try {
        $this->service->create(shiftPayload($this->lineA1, [
            'name' => 'Sore',
            'start_time' => '14:59',
            'end_time' => '23:00',
        ]));
    } catch (ShiftOverlapException $e) {
        $thrown = $e;
    }

    expect($thrown)->not->toBeNull();
    expect($thrown->getStatusCode())->toBe(422);
    expect($thrown->errorCode())->toBe('SHIFT_OVERLAP');

    // Pesan yang hanya berbunyi 'jam bertindih' memaksa Admin mencari sendiri
    // penyebabnya di antara belasan baris: nama DAN rentangnya disebut.
    // assertStringContainsString, BUKAN toContain($a, $b) — yang kedua
    // menerima BEBERAPA NEEDLE dan akan membaca pesannya sebagai needle
    // kedua, lalu menghijau tanpa membuktikan apa pun.
    $this->assertStringContainsString('Pagi', $thrown->getMessage(), 'pesan tidak menyebut shift pembanding');
    $this->assertStringContainsString('14:59', $thrown->getMessage(), 'pesan tidak menyebut awal rentang tindihan');
    $this->assertStringContainsString('15:00', $thrown->getMessage(), 'pesan tidak menyebut akhir rentang tindihan');

    expect(Shift::count())->toBe(1);
});

// Case 19
it('mendeteksi tumpang tindih pada shift yang melewati tengah malam', function () {
    Shift::factory()->forProductionLine($this->lineA1)->malam()->create();

    $overlap = $this->service->findOverlap($this->lineA1->id, '02:00', '05:00');

    expect($overlap)->not->toBeNull();
    expect($overlap['shift']->name)->toBe('Malam');
    // 02:00 → 120, 05:00 → 300, dan keduanya jatuh di dalam segmen Malam
    // yang kedua: [0, 420]. Sumbunya menit-ke-dalam-hari.
    expect($overlap['start_offset_minutes'])->toBe(120);
    expect($overlap['end_offset_minutes'])->toBe(300);

    expect(fn () => $this->service->create(shiftPayload($this->lineA1, [
        'name' => 'Dini',
        'start_time' => '02:00',
        'end_time' => '05:00',
    ])))->toThrow(ShiftOverlapException::class);

    expect(Shift::count())->toBe(1);
});

/**
 * Case 20 — KASUS JEBAKAN, dan ia harus benar-benar memerahkan implementasi
 * yang membandingkan string jam.
 *
 * Line punya Malam 23:00–07:00. Kandidat 01:00–03:00 jatuh SELURUHNYA di
 * dalamnya. Sebuah implementasi yang membandingkan '23:00' > '07:00' sebagai
 * string menyimpulkan rentang Malam KOSONG — '01:00' tidak berada antara
 * '23:00' dan '07:00' dalam urutan leksikografis — lalu MELOLOSKAN kandidat
 * ini dan menyimpannya. Test ini gagal pada implementasi seperti itu di tiga
 * tempat sekaligus: findOverlap() memulangkan null, create() tidak melempar,
 * dan Shift::count() menjadi 2.
 *
 * Kelas kesalahan yang sama dengan cacat pengurutan slot di laporan Engine
 * Room, yang 5467 test gagal menangkap karena tak satu pun di antaranya
 * memakai nilai yang membedakan jawaban benar dari jawaban yang masuk akal.
 */
it('perbandingan string jam TIDAK dipakai — kasus jebakan', function () {
    Shift::factory()->forProductionLine($this->lineA1)->malam()->create();

    // Bukti bahwa perbandingan string memang akan menyimpulkan hal lain:
    // dalam urutan leksikografis '01:00' TIDAK berada di antara kedua ujung.
    expect('01:00' > '23:00')->toBeFalse();
    expect('01:00' < '07:00')->toBeTrue();
    expect('23:00' > '07:00')->toBeTrue();

    $overlap = $this->service->findOverlap($this->lineA1->id, '01:00', '03:00');

    expect($overlap)->not->toBeNull();
    expect($overlap['shift']->name)->toBe('Malam');
    // 01:00 → 60, 03:00 → 180: seluruh kandidat (120 menit) tertanam di dalam
    // segmen Malam yang kedua, [0, 420].
    expect($overlap['start_offset_minutes'])->toBe(60);
    expect($overlap['end_offset_minutes'])->toBe(180);
    expect(ShiftService::intersectionMinutes([0, 420], [60, 180]))->toBe(120);
    // Dan segmen PERTAMA Malam, [1380, 1440], tidak beririsan dengannya —
    // jadi jawabannya memang datang dari segmentasi, bukan dari kebetulan.
    expect(ShiftService::intersectionMinutes([1380, 1440], [60, 180]))->toBe(0);

    $thrown = null;

    try {
        $this->service->create(shiftPayload($this->lineA1, [
            'name' => 'Dini',
            'start_time' => '01:00',
            'end_time' => '03:00',
        ]));
    } catch (ShiftOverlapException $e) {
        $thrown = $e;
    }

    expect($thrown)->not->toBeNull();
    $this->assertStringContainsString('Malam', $thrown->getMessage(), 'pesan tidak menyebut shift pembanding');
    $this->assertStringContainsString('01:00', $thrown->getMessage(), 'pesan tidak menyebut rentang tindihan');
    $this->assertStringContainsString('03:00', $thrown->getMessage(), 'pesan tidak menyebut rentang tindihan');

    expect(Shift::count())->toBe(1);
});

// Case 21
it('memeriksa tumpang tindih hanya di dalam satu production line', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    // Line LAIN, jam sama persis, nama sama persis: keduanya memang bekerja
    // bersamaan di line yang berbeda.
    expect($this->service->findOverlap($this->lineA2->id, '07:00', '15:00'))->toBeNull();

    $created = $this->service->create(shiftPayload($this->lineA2));

    expect($created['production_line_id'])->toBe($this->lineA2->id);
    expect(Shift::count())->toBe(2);

    // Dan line di MILL lain pun tidak ikut dibandingkan.
    $this->service->create(shiftPayload($this->lineB1));
    expect(Shift::count())->toBe(3);
});

it('menyebut shift pembanding dengan offset terkecil ketika ada beberapa tindihan', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();
    Shift::factory()->forProductionLine($this->lineA1)->siang()->create();

    // 08:00–20:00 (offset 480..1200) menindih Pagi (420..900) DAN Siang (900..1380).
    $overlap = $this->service->findOverlap($this->lineA1->id, '08:00', '20:00');

    expect($overlap['shift']->name)->toBe('Pagi');
    // Tindihan dengan Pagi dimulai 480 (08:00) dan yang dengan Siang 900
    // (15:00); yang disebut adalah yang offset-nya TERKECIL.
    expect($overlap['start_offset_minutes'])->toBe(480);
    expect($overlap['end_offset_minutes'])->toBe(900);
});

// ── Validasi tulis (cases 22, 23, 24, 25) ───────────────────────────────

// Case 22
it('menegakkan keunikan nama PER LINE, bukan global', function () {
    $this->service->create(shiftPayload($this->lineA1));

    $thrown = null;

    try {
        // Jam sengaja TIDAK bertindih, supaya yang memerah benar-benar
        // keunikan nama dan bukan tumpang tindih.
        $this->service->create(shiftPayload($this->lineA1, [
            'start_time' => '15:00',
            'end_time' => '23:00',
        ]));
    } catch (ValidationException $e) {
        $thrown = $e;
    }

    expect($thrown)->not->toBeNull();
    expect($thrown->errors())->toHaveKey('name');
    $this->assertStringContainsString('sudah dipakai', $thrown->errors()['name'][0], 'pesan tidak menyebut nama sudah dipakai');
    expect($thrown)->not->toBeInstanceOf(ShiftOverlapException::class);

    // Nama yang sama pada line LAIN diterima.
    $this->service->create(shiftPayload($this->lineA2));

    expect(Shift::where('name', 'Pagi')->count())->toBe(2);
});

/**
 * Case 23 — DUA CABANG, dan keduanya diuji di satu test karena yang
 * dibuktikan justru PEMBEDAANNYA (revisi spec v2 2026-10-16, business_logic
 * 17). Jam berimpit berarti shift 24 jam; ia sah pada line yang belum punya
 * shift HIDUP lain dan hanya bisa berarti salah ketik pada line yang sudah
 * terisi. Yang ditolak ditolak sebagai VALIDATION_ERROR pada key `end_time`,
 * SENGAJA BUKAN SHIFT_OVERLAP.
 */
it('membaca start_time sama dengan end_time sebagai 24 jam di line kosong dan menolaknya di line terisi', function () {
    // CABANG DITERIMA — Line 1 belum punya shift hidup sama sekali.
    $penuh = $this->service->create(shiftPayload($this->lineA1, [
        'name' => 'Penuh',
        'start_time' => '07:00',
        'end_time' => '07:00',
    ]));

    expect($penuh['duration_minutes'])->toBe(1440);

    $entry = shiftCoverageFor($this->service->listShifts($this->millA->id, $this->lineA1->id), $this->lineA1);
    expect($entry['state'])->toBe('covered');
    expect($entry['uncovered_minutes'])->toBe(0);
    expect(gapBlocksOf($entry))->toBe([]);
    // 07:00–07:00 melintasi titik nol sumbu, jadi dua segmen bertaut yang
    // bersama-sama menutup setiap menit sumbu — batang penuh, tanpa celah.
    expect(array_sum(array_column(shiftBlocksOf($entry), 'duration_minutes')))->toBe(1440);
    expectNoBlockPastAxisEnd($entry);

    // CABANG DITOLAK — Line 2 sudah punya Pagi 07:00–15:00.
    $this->service->create(shiftPayload($this->lineA2));

    $thrown = null;

    try {
        $this->service->create(shiftPayload($this->lineA2, [
            'name' => 'Nol',
            'start_time' => '07:00',
            'end_time' => '07:00',
        ]));
    } catch (ValidationException $e) {
        $thrown = $e;
    }

    expect($thrown)->not->toBeNull();
    expect($thrown->errors())->toHaveKey('end_time');
    $this->assertStringContainsString('berdurasi nol', $thrown->errors()['end_time'][0], 'pesan tidak menyebut durasi nol');
    // BUKAN SHIFT_OVERLAP — shift 24 jam menurut definisinya tidak dapat
    // berdampingan dengan shift mana pun, jadi di sini jam berimpit hanya
    // bisa berarti salah ketik, dan salah ketik bukan tumpang tindih.
    expect($thrown)->not->toBeInstanceOf(ShiftOverlapException::class);
    expect(Shift::where('production_line_id', $this->lineA2->id)->count())->toBe(1);

    // Pemeriksaannya SESUDAH normalisasi: '07:00' dan '07:00:00' adalah jam
    // yang sama, dan perbandingan string tidak mengetahuinya. Cabang yang
    // dipakai di sini tetap cabang penolakan, pada line yang sama.
    $thrownNormalized = null;

    try {
        $this->service->create(shiftPayload($this->lineA2, [
            'name' => 'Nol 2',
            'start_time' => '07:00',
            'end_time' => '07:00:00',
        ]));
    } catch (ValidationException $e) {
        $thrownNormalized = $e;
    }

    expect($thrownNormalized)->not->toBeNull();
    expect($thrownNormalized->errors())->toHaveKey('end_time');
    expect(Shift::where('production_line_id', $this->lineA2->id)->count())->toBe(1);
});

/**
 * Case 23, sisi UPDATE — satu-satunya shift HIDUP sebuah line boleh diubah
 * menjadi 24 jam. Tanpa `$excludeId` pada lineHasOtherLiveShift(), shift itu
 * akan menghitung DIRINYA SENDIRI sebagai "shift lain" dan perubahan ini
 * tidak akan pernah bisa disimpan.
 */
it('mengizinkan satu-satunya shift hidup sebuah line diubah menjadi 24 jam', function () {
    $only = $this->service->create(shiftPayload($this->lineA1));

    $updated = $this->service->update($only['id'], [
        'end_time' => '07:00',
    ]);

    expect($updated['duration_minutes'])->toBe(1440);

    $entry = shiftCoverageFor($this->service->listShifts($this->millA->id, $this->lineA1->id), $this->lineA1);
    expect($entry['state'])->toBe('covered');
    expect(gapBlocksOf($entry))->toBe([]);
});

/**
 * Case 23, sisi lanjutan — sesudah sebuah shift 24 jam ada, TIDAK ADA shift
 * kedua yang dapat ditambahkan pada line itu, dan penolakannya kali ini
 * MEMANG SHIFT_OVERLAP: jamnya sungguh bertindih, bukan salah ketik.
 */
it('menolak shift kedua pada line yang sudah ditutup penuh shift 24 jam sebagai SHIFT_OVERLAP', function () {
    $this->service->create(shiftPayload($this->lineA1, [
        'name' => 'Penuh',
        'start_time' => '07:00',
        'end_time' => '07:00',
    ]));

    expect(fn () => $this->service->create(shiftPayload($this->lineA1, [
        'name' => 'Siang',
        'start_time' => '15:00',
        'end_time' => '23:00',
    ])))->toThrow(ShiftOverlapException::class);

    expect(Shift::where('production_line_id', $this->lineA1->id)->count())->toBe(1);
});

// Case 24
it('menolak field wajib yang absen dengan menyebut field-nya', function (string $field) {
    $payload = shiftPayload($this->lineA1);
    unset($payload[$field]);

    $thrown = null;

    try {
        $this->service->create($payload);
    } catch (ValidationException $e) {
        $thrown = $e;
    }

    expect($thrown)->not->toBeNull();
    expect($thrown->errors())->toHaveKey($field);
    expect(Shift::count())->toBe(0);
})->with([
    'production_line_id' => ['production_line_id'],
    'name' => ['name'],
    'start_time' => ['start_time'],
    'end_time' => ['end_time'],
]);

it('menolak jam yang bentuknya bukan JJ:MM', function () {
    foreach (['7', '7:0', '25:00', '07:60', 'pagi'] as $bad) {
        $thrown = null;

        try {
            $this->service->create(shiftPayload($this->lineA1, ['start_time' => $bad]));
        } catch (ValidationException $e) {
            $thrown = $e;
        }

        expect($thrown)->not->toBeNull("jam '$bad' seharusnya ditolak");
        expect($thrown->errors())->toHaveKey('start_time');
    }

    expect(Shift::count())->toBe(0);
});

// Case 25
it('menolak production_line_id yang tidak ada di master', function () {
    $thrown = null;

    try {
        $this->service->create(shiftPayload($this->lineA1, [
            'production_line_id' => (string) Str::uuid(),
        ]));
    } catch (ValidationException $e) {
        $thrown = $e;
    }

    expect($thrown)->not->toBeNull();
    expect($thrown->errors())->toHaveKey('production_line_id');

    // Dan id yang bentuknya bukan UUID — jebakan SQLSTATE 22P02 — ditolak
    // sebagai VALIDATION_ERROR dan bukan sebagai galat basis data.
    expect(fn () => $this->service->create(shiftPayload($this->lineA1, [
        'production_line_id' => 'pl-001',
    ])))->toThrow(ValidationException::class);

    expect(Shift::count())->toBe(0);
});

it('menyetempel created_by dari sesi dan tidak pernah dari request', function () {
    $this->service->create(shiftPayload($this->lineA1, [
        // Nilai yang dikirim pemanggil DIABAIKAN: aktornya selalu diturunkan
        // dari sesi.
        'created_by' => $this->supervisor->id,
    ]));

    $stored = Shift::firstOrFail();

    expect($stored->created_by)->toBe($this->admin->id);
    expect($stored->updated_by)->toBeNull();
    expect(ShiftService::FIELDS)->toBe(['production_line_id', 'name', 'start_time', 'end_time']);
});

it('menormalkan jam ke H:i:s sebelum menyimpan', function () {
    // Form mengirim 'HH:MM' (bentuk <input type="time">); kolomnya TIME,
    // dan bentuk panjang diterima sama di PostgreSQL maupun SQLite.
    $this->service->create(shiftPayload($this->lineA1, ['start_time' => '07:05', 'end_time' => '15:00']));

    expect(Shift::firstOrFail()->start_time)->toBe('07:05:00');
    expect(Shift::firstOrFail()->end_time)->toBe('15:00:00');

    // Bentuk panjang yang datang dari basis data juga diterima apa adanya.
    $this->service->create(shiftPayload($this->lineA2, ['start_time' => '07:05:00', 'end_time' => '15:00:00']));

    expect(Shift::where('production_line_id', $this->lineA2->id)->firstOrFail()->start_time)->toBe('07:05:00');
});

// ── update() (cases 26, 27, 28, 29) ─────────────────────────────────────

// Case 26
it('mengecualikan shift yang sedang diubah dari himpunan pembanding', function () {
    $pagi = Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();
    Shift::factory()->forProductionLine($this->lineA1)->siang()->create();

    // Tanpa pengecualian ini setiap perubahan bertindih dengan dirinya
    // sendiri dan tidak ada yang pernah bisa disimpan.
    $updated = $this->service->update($pagi->id, ['name' => 'Pagi Awal']);

    expect($updated['name'])->toBe('Pagi Awal');
    // PATCH PARSIAL: jam yang tidak dikirim mempertahankan nilai tersimpan,
    // bukan menjadi null.
    expect($updated['start_time'])->toBe('07:00');
    expect($updated['end_time'])->toBe('15:00');
    expect($pagi->refresh()->updated_by)->toBe($this->admin->id);

    expect($this->service->findOverlap($this->lineA1->id, '07:00', '15:00', $pagi->id))->toBeNull();
    // Dan TANPA pengecualian, ia memang bertindih dengan dirinya sendiri.
    expect($this->service->findOverlap($this->lineA1->id, '07:00', '15:00'))->not->toBeNull();
});

// Case 27
it('menolak PATCH yang membuat shift bertindih dengan tetangganya', function () {
    $pagi = Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();
    Shift::factory()->forProductionLine($this->lineA1)->siang()->create();

    $thrown = null;

    try {
        $this->service->update($pagi->id, ['end_time' => '16:00']);
    } catch (ShiftOverlapException $e) {
        $thrown = $e;
    }

    expect($thrown)->not->toBeNull();
    expect($thrown->getStatusCode())->toBe(422);
    $this->assertStringContainsString('Siang', $thrown->getMessage(), 'pesan tidak menyebut shift pembanding');
    $this->assertStringContainsString('15:00', $thrown->getMessage(), 'pesan tidak menyebut awal rentang tindihan');
    $this->assertStringContainsString('16:00', $thrown->getMessage(), 'pesan tidak menyebut akhir rentang tindihan');

    // Tidak ada yang tersimpan separuh.
    expect($pagi->refresh()->end_time)->toBe('15:00:00');
});

// Case 28
it('memeriksa tumpang tindih terhadap line TUJUAN ketika production_line_id ikut berubah', function () {
    $pagi = Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();
    Shift::factory()->forProductionLine($this->lineA2)->named('Pagi Line 2')->range('07:00:00', '15:00:00')->create();

    $thrown = null;

    try {
        $this->service->update($pagi->id, ['production_line_id' => $this->lineA2->id]);
    } catch (ShiftOverlapException $e) {
        $thrown = $e;
    }

    expect($thrown)->not->toBeNull();
    // Pembandingnya shift di line TUJUAN — shift di line asal justru tidak
    // lagi relevan setelah shift-nya pindah.
    $this->assertStringContainsString('Pagi Line 2', $thrown->getMessage(), 'pesan tidak menyebut shift di line tujuan');
    expect($pagi->refresh()->production_line_id)->toBe($this->lineA1->id);

    // Dan pindah ke line yang jamnya kosong BERHASIL.
    $lineA3 = ProductionLine::factory()->forBusinessUnit($this->millA)->create(['name' => 'Line 3']);
    $moved = $this->service->update($pagi->id, ['production_line_id' => $lineA3->id]);

    expect($moved['production_line_id'])->toBe($lineA3->id);
});

// Case 29
it('melempar NOT_FOUND pada PATCH id yang tidak ada', function () {
    expect(fn () => $this->service->update((string) Str::uuid(), ['name' => 'X']))
        ->toThrow(ModelNotFoundException::class);
});

// ── delete() (cases 30, 31, 32) ─────────────────────────────────────────

// Case 30
it('mengubah cakupan dari covered menjadi gap sesudah satu shift dihapus', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();
    $siang = Shift::factory()->forProductionLine($this->lineA1)->siang()->create();
    Shift::factory()->forProductionLine($this->lineA1)->malam()->create();

    expect(shiftCoverageFor($this->service->listShifts($this->millA->id, $this->lineA1->id), $this->lineA1)['state'])
        ->toBe('covered');

    $this->service->delete($siang->id);

    $entry = shiftCoverageFor($this->service->listShifts($this->millA->id, $this->lineA1->id), $this->lineA1);

    expect($entry['state'])->toBe('gap');
    expect($entry['uncovered_minutes'])->toBe(480);
    expect($entry['shift_count'])->toBe(2);

    $gaps = gapBlocksOf($entry);
    expect($gaps)->toHaveCount(1);
    // Tepat di posisi yang ditinggalkan Siang (15:00 → offset 900).
    expect($gaps[0]['start_offset_minutes'])->toBe(900);
    expect($gaps[0]['duration_minutes'])->toBe(480);
});

// Case 31
it('mengembalikan line ke unconfigured sesudah shift terakhirnya dihapus', function () {
    $only = Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    $this->service->delete($only->id);

    $entry = shiftCoverageFor($this->service->listShifts($this->millA->id, $this->lineA1->id), $this->lineA1);

    expect($entry['state'])->toBe('unconfigured');
    expect($entry['shift_count'])->toBe(0);
    expect($entry['uncovered_minutes'])->toBe(0);
    expect($entry['blocks'])->toBe([]);
    // BUKAN 'gap' 1440.
    expect($entry['state'])->not->toBe('gap');
    expect($entry['uncovered_minutes'])->not->toBe(1440);
});

// Case 32
it('melempar NOT_FOUND pada DELETE id yang tidak ada', function () {
    expect(fn () => $this->service->delete((string) Str::uuid()))
        ->toThrow(ModelNotFoundException::class);
});

// ── Ketiadaan dimensi hari (cases 36, 37) ───────────────────────────────

/**
 * Case 36 (service half) — NOL field hari/tanggal pada permukaan tulis
 * service. Permukaan RUTE diuji di tests/Feature/Api/KelolaShiftTest.php,
 * tempat tabel rutenya menjadi subjeknya.
 */
it('tidak menerima satu pun field hari maupun masa berlaku pada jalur tulis', function () {
    foreach (['date', 'day', 'weekday', 'day_of_week', 'effective_from', 'effective_to', 'valid_from'] as $forbidden) {
        expect(ShiftService::FIELDS)->not->toContain($forbidden);
    }

    // Field semacam itu DIABAIKAN, bukan 422: controller hanya meneruskan
    // ShiftService::FIELDS, jadi ia tidak pernah sampai ke sini.
    $created = $this->service->create(shiftPayload($this->lineA1, [
        'day_of_week' => 'monday',
        'effective_from' => '2026-10-15',
    ]));

    expect($created)->not->toHaveKey('day_of_week');
    expect($created)->not->toHaveKey('effective_from');
    expect(Shift::firstOrFail()->getAttributes())->not->toHaveKey('day_of_week');
});

/**
 * Case 37 — skema tabel. Jadwal berlaku sama setiap hari (keputusan pemilik
 * produk 2026-10-15), dan ketiadaan dimensi hari DISENGAJA. Test ini ada
 * supaya "melengkapi" skema ini dengan kolom hari memerah lebih dulu.
 *
 * SEJAK v2 IA JUGA MENGIKAT BENTUK INDEX-NYA. `deleted_at` hadir, dan unique
 * index (production_line_id, name) WAJIB berupa PARTIAL index `WHERE
 * deleted_at IS NULL` — kalau ia masih penuh, sebuah 'Pagi' yang ditutup
 * memblokir 'Pagi' penggantinya selamanya, dan gejalanya terbaca seperti bug
 * di lapisan aplikasi padahal berasal dari index yang lupa dibuang
 * (business_logic 28, migrasi 2026_10_16_000001).
 */
it('punya deleted_at dan unique index PARTIAL pada (production_line_id, name), tanpa kolom hari', function () {
    foreach ([
        'id', 'production_line_id', 'name', 'start_time', 'end_time',
        'created_by', 'updated_by', 'deleted_at',
    ] as $column) {
        expect(Schema::hasColumn('shifts', $column))->toBeTrue("kolom $column tidak ada");
    }

    foreach ([
        'day', 'day_of_week', 'weekday', 'days', 'date', 'effective_from', 'effective_to',
        'valid_from', 'valid_to', 'rotation', 'rotation_pattern',
    ] as $forbidden) {
        expect(Schema::hasColumn('shifts', $forbidden))
            ->toBeFalse("kolom $forbidden seharusnya TIDAK ada — jadwal berlaku sama setiap hari");
    }

    $uniqueIndexes = collect(Schema::getIndexes('shifts'))
        ->filter(fn (array $index) => $index['unique'] === true)
        ->map(fn (array $index) => $index['columns'])
        ->values();

    expect($uniqueIndexes->contains(fn (array $columns) => array_values($columns) === ['production_line_id', 'name']))
        ->toBeTrue('unique index (production_line_id, name) tidak ditemukan');

    // ── PARTIAL, BUKAN PENUH ────────────────────────────────────────────
    //
    // Schema::getIndexes() memulangkan KOLOMNYA saja dan tidak pernah
    // menyebut predikat `WHERE`-nya, jadi sebuah unique index penuh dan
    // sebuah partial unique index terlihat IDENTIK di atas. Satu-satunya cara
    // membedakan keduanya adalah membaca DEFINISI index-nya dari katalog
    // dialeknya sendiri — karena itu test ini sadar-driver alih-alih
    // mengandalkan satu API portabel yang tidak punya jawabannya.
    //
    // Suite ini berjalan di SQLite in-memory sementara produksi dan basis
    // data e2e memakai PostgreSQL, dan DDL-nya sengaja ditulis sekali untuk
    // keduanya; keduanya dibaca di sini supaya test ini tetap mengikat di
    // dialek mana pun ia dijalankan, bukan hanya di yang murah.
    $driver = DB::connection()->getDriverName();

    $definition = match ($driver) {
        // sqlite_master.sql memuat pernyataan CREATE INDEX apa adanya,
        // lengkap dengan predikatnya. (Autoindex implisit ber-sql NULL —
        // yang ini eksplisit, jadi sql-nya terisi.)
        'sqlite' => (string) DB::table('sqlite_master')
            ->where('type', 'index')
            ->where('name', 'shifts_production_line_id_name_unique')
            ->value('sql'),
        // pg_indexes.indexdef memuat bentuk yang sama sesudah di-parse ulang
        // PostgreSQL: '... WHERE (deleted_at IS NULL)'.
        'pgsql' => (string) DB::table('pg_indexes')
            ->where('indexname', 'shifts_production_line_id_name_unique')
            ->value('indexdef'),
        default => '',
    };

    $this->assertNotSame(
        '',
        $definition,
        "definisi index tidak terbaca pada driver '$driver' — tambahkan cabang katalognya, jangan lemahkan asersinya"
    );

    $normalized = str_replace(['(', ')'], '', strtolower($definition));

    $this->assertStringContainsString(
        'where deleted_at is null',
        $normalized,
        'unique index (production_line_id, name) masih PENUH: nama shift yang ditutup akan memblokir penggantinya selamanya'
    );
    $this->assertStringContainsString('unique', $normalized, 'index-nya tidak unique');

    // Tumpang tindih TIDAK dapat ditegakkan index — dua rentang beririsan
    // bukan duplikasi nilai. Penegakannya ada di lapisan aplikasi, dan unique
    // index atas jamnya justru akan SALAH: dua line boleh punya shift pada
    // jam yang sama persis.
    expect($uniqueIndexes->contains(fn (array $columns) => in_array('start_time', array_values($columns), true)))
        ->toBeFalse('jam tidak boleh dijadikan unique index');
});

// ── Soft delete (cases 46–53) ───────────────────────────────────────────
//
// MENGAPA SELURUH BAGIAN INI ADA. `shift_id` akan mendarat di kedelapan
// belas tabel record di putaran penandaan berikutnya, bermakna "shift yang
// berlaku saat operator menyimpan record". Membuang baris shift akan membuat
// record lama kehilangan jawaban atas "record ini sebenarnya shift jam
// berapa" — tanpa satu pun aksi yang terlihat destruktif. Karena itu DELETE
// menutup baris, dan keempat jalur yang hidup wajib mengecualikannya.
//
// Baris tertutup di bawah ditanam LANGSUNG lewat ->closed(), bukan dengan
// memanggil delete() atas baris hidup: yang diuji di sini PENGECUALIANNYA,
// dan menanamnya lewat delete() akan menguji delete() sekali lagi.

// Case 46
it('menutup baris alih-alih membuangnya: deleted_at terisi dan withTrashed() tetap memulangkannya', function () {
    $pagi = Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    $this->service->delete($pagi->id);

    // Jalur yang HIDUP tidak lagi melihatnya.
    expect(Shift::find($pagi->id))->toBeNull();

    // Barisnya MASIH ADA — itulah yang membuat laporan historis tetap
    // terbaca ketika record kelak merujuk shift ini.
    $closed = Shift::withTrashed()->find($pagi->id);

    expect($closed)->not->toBeNull();
    expect($closed->deleted_at)->not->toBeNull();
    // Jamnya ikut tersimpan utuh: rujukan historis butuh jam ASLINYA, bukan
    // hanya id-nya.
    expect($closed->start_time)->toBe('07:00:00');
    expect($closed->end_time)->toBe('15:00:00');
});

/**
 * KEPUTUSAN TURUNAN, bukan permintaan spec: skema shift tidak punya kolom
 * `deleted_by` sementara baris tertutup kini hidup selamanya, jadi tanpa
 * stempel ini tidak ada satu pun jejak siapa yang menutupnya. `updated_by`
 * adalah satu-satunya kolom yang tersedia untuk menyimpan jawabannya, dan ia
 * distempel SEBELUM penutupan — sesudahnya baris itu tidak lagi terjangkau
 * jalur yang hidup.
 */
it('menyetempel updated_by sebelum menutup baris, karena skema tidak punya deleted_by', function () {
    $pagi = Shift::factory()->forProductionLine($this->lineA1)->pagi()->create(['updated_by' => null]);

    $this->service->delete($pagi->id);

    expect(Shift::withTrashed()->findOrFail($pagi->id)->updated_by)->toBe($this->admin->id);
});

// Case 47 — JALUR HIDUP 1 (daftar) dan 2 (cakupan/celah).
it('mengecualikan shift tertutup dari daftar, dari cakupan, dan dari perhitungan celah', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();
    Shift::factory()->forProductionLine($this->lineA1)->malam()->create();
    // Shift tertutup yang jamnya berada TEPAT di antara keduanya: bila ia
    // ikut dihitung, celah 15:00–23:00 akan hilang dari payload.
    $closed = Shift::factory()->forProductionLine($this->lineA1)->closed()->siang()->create();

    $result = $this->service->listShifts($this->millA->id, $this->lineA1->id);

    expect($result['data'])->toHaveCount(2);
    expect(collect($result['data'])->pluck('id'))->not->toContain($closed->id);
    expect(collect($result['data'])->pluck('name')->sort()->values()->all())->toBe(['Malam', 'Pagi']);

    $entry = shiftCoverageFor($result, $this->lineA1);

    expect($entry['shift_count'])->toBe(2);
    expect($entry['state'])->toBe('gap');
    expect($entry['uncovered_minutes'])->toBe(480);
    // TIGA blok untuk DUA shift: Malam menyumbang dua segmen.
    expect(shiftBlocksOf($entry))->toHaveCount(3);
    expect(startingShiftBlocksOf($entry))->toHaveCount(2);
    expect(collect(shiftBlocksOf($entry))->pluck('shift_id'))->not->toContain($closed->id);

    // Celahnya dihitung PADA RENTANG shift yang tertutup itu, seolah tidak
    // ada shift di sana.
    $gaps = gapBlocksOf($entry);
    expect($gaps)->toHaveCount(1);
    expect($gaps[0]['start_offset_minutes'])->toBe(900);
    expect($gaps[0]['duration_minutes'])->toBe(480);
});

// Case 47, lanjutan — JALUR HIDUP 3 (sumber pratinjau pada form).
it('mengecualikan shift tertutup dari sumber pratinjau form (shiftBlocksByLine)', function () {
    $live = Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();
    $closed = Shift::factory()->forProductionLine($this->lineA1)->closed()->siang()->create();

    $blocks = $this->service->shiftBlocksByLine($this->millA->id);

    expect($blocks)->toHaveKey($this->lineA1->id);

    $ids = collect($blocks[$this->lineA1->id])->pluck('shift_id');

    expect($ids->all())->toBe([$live->id]);
    expect($ids)->not->toContain($closed->id);
});

// Case 51
it('menandai line yang seluruh shift-nya tertutup sebagai unconfigured, bukan gap 1440 menit', function () {
    Shift::factory()->forProductionLine($this->lineA1)->closed()->pagi()->create();
    Shift::factory()->forProductionLine($this->lineA1)->closed()->siang()->create();

    $result = $this->service->listShifts($this->millA->id, $this->lineA1->id);
    $entry = shiftCoverageFor($result, $this->lineA1);

    expect($result['data'])->toBe([]);
    expect($entry['state'])->toBe('unconfigured');
    expect($entry['shift_count'])->toBe(0);
    // BUKAN 'gap' 1440: belum dikerjakan versus keputusan yang mungkin salah
    // adalah dua arti yang berbeda, dan payload-nya yang membedakannya.
    expect($entry['state'])->not->toBe('gap');
    expect($entry['uncovered_minutes'])->toBe(0);
    expect($entry['blocks'])->toBe([]);
});

/**
 * Case 48 — JALUR HIDUP 4, YANG PALING MUDAH TERLEWAT, dan test yang paling
 * penting di bagian ini.
 *
 * Bila findOverlap() berhenti mengecualikan baris tertutup, JAM yang baru
 * saja dikosongkan tidak akan pernah dapat dipakai ulang oleh penggantinya —
 * dan memakai ulang jam adalah justru alasan soft delete dipilih alih-alih
 * penghapusan permanen. Kegagalannya tidak terlihat di daftar maupun di
 * timeline; ia hanya muncul sebagai penolakan SHIFT_OVERLAP terhadap shift
 * yang sudah tidak ada.
 */
it('membebaskan JAM shift yang ditutup untuk dipakai ulang oleh penggantinya', function () {
    $pagi = Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    // Ditutup lewat jalur produksinya sendiri — yang diuji di sini adalah
    // RANGKAIANNYA: hapus, lalu pakai ulang jamnya.
    $this->service->delete($pagi->id);

    // findOverlap() tidak lagi melihat baris itu.
    expect($this->service->findOverlap($this->lineA1->id, '07:00', '15:00'))->toBeNull();

    // Dan jamnya yang SAMA PERSIS dapat dipakai pengganti.
    $replacement = $this->service->create(shiftPayload($this->lineA1, [
        'name' => 'Pagi Baru',
        'start_time' => '07:00',
        'end_time' => '15:00',
    ]));

    expect($replacement['start_time'])->toBe('07:00');
    expect($replacement['end_time'])->toBe('15:00');

    $entry = shiftCoverageFor($this->service->listShifts($this->millA->id, $this->lineA1->id), $this->lineA1);
    expect($entry['shift_count'])->toBe(1);
    expect(shiftBlocksOf($entry)[0]['shift_id'])->toBe($replacement['id']);
});

/**
 * Case 49 — NAMA yang dikosongkan juga bebas dipakai ulang, dan DUA PENEGAK
 * harus sepakat: partial unique index `WHERE deleted_at IS NULL` di basis
 * data, dan Rule::unique()->whereNull('deleted_at') di aplikasi. Yang kedua
 * ditulis tangan karena Rule::unique() membangun kueri Query Builder dan
 * karena itu TIDAK dijangkau global scope SoftDeletes — satu-satunya jalur
 * shift di service yang luput darinya. Bila salah satu lupa, yang lebih
 * ketatlah yang menang dan penggantian terhalang namanya sendiri.
 */
it('membebaskan NAMA shift yang ditutup untuk dipakai ulang oleh penggantinya', function () {
    $pagi = Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    $this->service->delete($pagi->id);

    // Nama yang sama PADA LINE YANG SAMA, dan sengaja pada jam yang berbeda
    // supaya yang terbukti benar-benar keunikan nama dan bukan tumpang
    // tindih.
    $replacement = $this->service->create(shiftPayload($this->lineA1, [
        'name' => 'Pagi',
        'start_time' => '15:00',
        'end_time' => '23:00',
    ]));

    expect($replacement['name'])->toBe('Pagi');
    expect(Shift::where('name', 'Pagi')->count())->toBe(1);
    expect(Shift::withTrashed()->where('name', 'Pagi')->count())->toBe(2);
});

/**
 * Case 50 — PENGECUALIANNYA HANYA BERLAKU BAGI BARIS TERTUTUP. Dua baris
 * HIDUP dengan (line, name) yang sama tetap ditolak, dan ditolak oleh
 * KEDUANYA: 422 dari lapisan aplikasi, dan galat driver dari partial unique
 * index ketika sebuah insert langsung melewati validasinya.
 */
it('tetap menolak dua baris HIDUP dengan (production_line_id, name) yang sama', function () {
    Shift::factory()->forProductionLine($this->lineA1)->closed()->pagi()->create();
    $this->service->create(shiftPayload($this->lineA1));

    // Lapisan aplikasi: 422 yang menyebut namanya.
    $thrown = null;

    try {
        $this->service->create(shiftPayload($this->lineA1, [
            'start_time' => '15:00',
            'end_time' => '23:00',
        ]));
    } catch (ValidationException $e) {
        $thrown = $e;
    }

    expect($thrown)->not->toBeNull();
    expect($thrown->errors())->toHaveKey('name');
    $this->assertStringContainsString('sudah dipakai', $thrown->errors()['name'][0], 'pesan tidak menyebut nama sudah dipakai');

    // Lapisan basis data: insert LANGSUNG yang melewati validate() tetap
    // ditolak index-nya. Tanpa asersi ini, sebuah index yang ikut
    // terlonggarkan menjadi non-unique akan lolos diam-diam di belakang
    // pemeriksaan aplikasi.
    // Dibungkus savepoint: di PostgreSQL satu pernyataan yang gagal membuat SELURUH transaksi
    // pembungkus tes "aborted" (SQLSTATE 25P02) dan kueri berikutnya ditolak; SQLite memaafkannya.
    expect(fn () => DB::transaction(fn () => Shift::factory()
        ->forProductionLine($this->lineA1)
        ->named('Pagi')
        ->range('15:00:00', '23:00:00')
        ->create()))
        ->toThrow(QueryException::class);

    expect(Shift::where('production_line_id', $this->lineA1->id)->count())->toBe(1);
});

// Case 52 — baris tertutup tidak terjangkau SATU PUN jalur tulis yang hidup.
it('melempar NOT_FOUND pada DELETE maupun PATCH shift yang sudah ditutup', function () {
    $closed = Shift::factory()->forProductionLine($this->lineA1)->closed()->pagi()->create();

    expect(fn () => $this->service->delete($closed->id))->toThrow(ModelNotFoundException::class);
    expect(fn () => $this->service->update($closed->id, ['name' => 'Pagi Diubah']))
        ->toThrow(ModelNotFoundException::class);

    // Dan ia tidak berubah karenanya.
    $still = Shift::withTrashed()->findOrFail($closed->id);
    expect($still->name)->toBe('Pagi');
    expect($still->deleted_at)->not->toBeNull();
});

/**
 * Case 53 — PENGGANTIAN-SAAT-UBAH BELUM ADA, DAN ITU DISENGAJA
 * (business_logic 30). Pemicunya adalah "ada record yang merujuk shift ini",
 * dan hari ini tidak ada satu kolom pun di sistem yang merujuk shift —
 * `shift_id` baru lahir bersama penandaan kedelapan belas tabel record. Jadi
 * mengubah jam MEMUTASI baris, dan test ini mengikat keadaan itu supaya
 * penggantian tidak dibangun lebih awal terhadap cabang yang tidak pernah
 * dilewati jalur produksi mana pun.
 */
it('memutasi baris ketika jamnya diubah: nol baris baru dan nol baris tertutup baru', function () {
    $pagi = $this->service->create(shiftPayload($this->lineA1));

    $this->service->update($pagi['id'], ['end_time' => '16:00']);

    // Jumlah baris pada line itu TERMASUK yang tertutup tidak bertambah.
    expect(Shift::withTrashed()->where('production_line_id', $this->lineA1->id)->count())->toBe(1);
    expect(Shift::onlyTrashed()->where('production_line_id', $this->lineA1->id)->count())->toBe(0);

    // Baris yang SAMA menyimpan jam yang baru.
    $row = Shift::findOrFail($pagi['id']);
    expect($row->end_time)->toBe('16:00:00');
    expect($row->start_time)->toBe('07:00:00');
});

/**
 * Pertemuan kedua keputusan v2, dan satu-satunya tempat keduanya bersinggung:
 * line yang SELURUH shift-nya sudah ditutup terbaca sama seperti line yang
 * belum pernah diberi shift, sehingga ia menerima shift 24 jam kembali.
 * Konsekuensi itu memang yang diinginkan — lineHasOtherLiveShift() tidak
 * menghitung baris tertutup.
 */
it('menerima kembali shift 24 jam pada line yang seluruh shift-nya sudah ditutup', function () {
    Shift::factory()->forProductionLine($this->lineA1)->closed()->pagi()->create();
    Shift::factory()->forProductionLine($this->lineA1)->closed()->siang()->create();

    $penuh = $this->service->create(shiftPayload($this->lineA1, [
        'name' => 'Penuh',
        'start_time' => '07:00',
        'end_time' => '07:00',
    ]));

    expect($penuh['duration_minutes'])->toBe(1440);

    $entry = shiftCoverageFor($this->service->listShifts($this->millA->id, $this->lineA1->id), $this->lineA1);
    expect($entry['state'])->toBe('covered');
    expect($entry['shift_count'])->toBe(1);
    expect(gapBlocksOf($entry))->toBe([]);
});

// ═══════════════════════════════════════════════════════════════════════
// PENANDAAN shift_id PADA RECORD (2026-10-16)
//
// Sampai putaran ini jadwal Shift tidak punya satu pun pembaca: jadwalnya
// ada, dan tidak ada data yang terhubung kepadanya. shiftIdAt() dan
// resolveShiftIdForSave() adalah penghubungnya, dan keduanyalah satu-satunya
// tempat aturan "record ini masuk shift mana" diputuskan — mobile maupun web
// memanggil yang sama, yang berbeda hanya JAM MANA yang dimasukkan.
// ═══════════════════════════════════════════════════════════════════════

it('shiftIdAt: memulangkan shift yang sedang berjalan pada jam yang diberikan', function () {
    $pagi = Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();
    $siang = Shift::factory()->forProductionLine($this->lineA1)->siang()->create();
    $malam = Shift::factory()->forProductionLine($this->lineA1)->malam()->create();

    // Pagi 07:00–15:00, Siang 15:00–23:00, Malam 23:00–07:00.
    expect($this->service->shiftIdAt($this->lineA1->id, '07:00'))->toBe($pagi->id);
    expect($this->service->shiftIdAt($this->lineA1->id, '11:30'))->toBe($pagi->id);
    expect($this->service->shiftIdAt($this->lineA1->id, '14:59'))->toBe($pagi->id);
    expect($this->service->shiftIdAt($this->lineA1->id, '15:00'))->toBe($siang->id);
    expect($this->service->shiftIdAt($this->lineA1->id, '22:59'))->toBe($siang->id);

    // MELINTASI TENGAH MALAM — kedua sisi titik nol sumbu milik shift yang
    // SAMA, dan inilah kasus yang akan diloloskan implementasi yang
    // membandingkan string jam: '01:00' tidak berada di antara '23:00' dan
    // '07:00' secara leksikografis.
    expect($this->service->shiftIdAt($this->lineA1->id, '23:00'))->toBe($malam->id);
    expect($this->service->shiftIdAt($this->lineA1->id, '23:59'))->toBe($malam->id);
    expect($this->service->shiftIdAt($this->lineA1->id, '00:00'))->toBe($malam->id);
    expect($this->service->shiftIdAt($this->lineA1->id, '01:00'))->toBe($malam->id);
    expect($this->service->shiftIdAt($this->lineA1->id, '06:59'))->toBe($malam->id);
});

/**
 * BATAS SETENGAH TERBUKA, dan ini pembeda yang DISENGAJA dari findOverlap().
 *
 * Di sana batasnya inklusif, sehingga Pagi 07:00–15:00 dan Siang 15:00–23:00
 * boleh berdampingan. Di sini pertanyaannya lain — "menit ini milik siapa" —
 * dan ia wajib punya TEPAT SATU jawaban. Kedua aturan itu benar untuk
 * pertanyaannya masing-masing, dan menyamakannya akan merusak salah satunya.
 */
it('shiftIdAt: menit pergantian milik shift yang BARU, bukan yang berakhir', function () {
    $pagi = Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();
    $siang = Shift::factory()->forProductionLine($this->lineA1)->siang()->create();

    expect($this->service->shiftIdAt($this->lineA1->id, '14:59'))->toBe($pagi->id);
    expect($this->service->shiftIdAt($this->lineA1->id, '15:00'))->toBe($siang->id);
    expect($this->service->shiftIdAt($this->lineA1->id, '15:00'))->not->toBe($pagi->id);

    // Dan jalur tulis TETAP menerima keduanya berdampingan — kedua aturan
    // hidup berdampingan, tidak saling menggantikan.
    expect($this->service->findOverlap($this->lineA1->id, '15:00', '23:00', $siang->id))->toBeNull();
});

it('shiftIdAt: null ketika jamnya jatuh pada celah jadwal', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();
    Shift::factory()->forProductionLine($this->lineA1)->named('Malam')->range('19:00:00', '07:00:00')->create();

    // Celahnya 15:00–19:00, dan ia memang diizinkan oleh rancangan.
    expect($this->service->shiftIdAt($this->lineA1->id, '15:00'))->toBeNull();
    expect($this->service->shiftIdAt($this->lineA1->id, '17:30'))->toBeNull();
    expect($this->service->shiftIdAt($this->lineA1->id, '18:59'))->toBeNull();
    expect($this->service->shiftIdAt($this->lineA1->id, '19:00'))->not->toBeNull();
});

it('shiftIdAt: null untuk line yang belum punya jadwal sama sekali', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    // lineA2 tidak diberi shift — dan line yang shift-nya ada di line LAIN
    // tidak boleh ikut terbaca.
    expect($this->service->shiftIdAt($this->lineA2->id, '08:00'))->toBeNull();
    expect($this->service->shiftIdAt($this->lineA1->id, '08:00'))->not->toBeNull();
});

it('shiftIdAt: shift tertutup tidak pernah dipulangkan', function () {
    $closed = Shift::factory()->forProductionLine($this->lineA1)->closed()->pagi()->create();

    expect($this->service->shiftIdAt($this->lineA1->id, '08:00'))->toBeNull();

    // Barisnya memang masih ada — yang dibuktikan di sini adalah ia
    // dikecualikan, bukan bahwa ia tidak pernah ditulis.
    expect(Shift::withTrashed()->whereKey($closed->id)->exists())->toBeTrue();
});

it('shiftIdAt: shift 24 jam memiliki setiap menit pada line itu', function () {
    $penuh = $this->service->create(shiftPayload($this->lineA1, [
        'name' => 'Penuh',
        'start_time' => '07:00',
        'end_time' => '07:00',
    ]));

    foreach (['00:00', '06:59', '07:00', '12:00', '23:59'] as $jam) {
        expect($this->service->shiftIdAt($this->lineA1->id, $jam))->toBe($penuh['id'], "menit $jam tidak dimiliki shift 24 jam");
    }
});

/**
 * Data yang BERTINDIH mustahil lahir dari aplikasi — jalur tulis menolaknya —
 * tetapi baris yang ditanam factory bisa. Yang diasersi di sini bukan bahwa
 * tindihannya benar, melainkan bahwa jawabannya DETERMINISTIK: segmen yang
 * paling baru dimulai. Jawaban yang bergantung pada urutan baris basis data
 * adalah yang paling mahal ditelusuri ketika ia salah.
 */
it('shiftIdAt: pada data bertindih memilih segmen yang paling baru dimulai', function () {
    Shift::factory()->forProductionLine($this->lineA1)->named('Lama')->range('06:00:00', '18:00:00')->create();
    $baru = Shift::factory()->forProductionLine($this->lineA1)->named('Baru')->range('10:00:00', '20:00:00')->create();

    // 12:00 ada di dalam keduanya; yang menang adalah yang mulai 10:00.
    expect($this->service->shiftIdAt($this->lineA1->id, '12:00'))->toBe($baru->id);

    // Dan jawabannya tidak berubah ketika kueri yang sama diulang — bukan
    // sekadar kebetulan urutan baris pada pemanggilan pertama.
    expect($this->service->shiftIdAt($this->lineA1->id, '12:00'))->toBe($baru->id);
});

it('shiftIdAt: menerima jam maupun datetime penuh, dan hanya jamnya yang dipakai', function () {
    $siang = Shift::factory()->forProductionLine($this->lineA1)->siang()->create();

    expect($this->service->shiftIdAt($this->lineA1->id, '16:00'))->toBe($siang->id);
    expect($this->service->shiftIdAt($this->lineA1->id, '16:00:00'))->toBe($siang->id);
    expect($this->service->shiftIdAt($this->lineA1->id, '2026-10-16 16:00:00'))->toBe($siang->id);
    expect($this->service->shiftIdAt($this->lineA1->id, '2020-01-01 16:45:30'))->toBe($siang->id);

    // TANGGALNYA TIDAK BERPENGARUH — jadwal berlaku sama setiap hari, dan
    // ketiadaan dimensi hari itu keputusan pemilik produk 2026-10-15.
    expect(ShiftService::minuteOfDayOf('2026-10-16 16:00:00'))->toBe(960);
    expect(ShiftService::minuteOfDayOf('16:00'))->toBe(960);
});

/**
 * JALUR MOBILE — stempel perangkat DIPERCAYA, tetapi diperiksa.
 *
 * Inversi yang disengaja terhadap `production_line_id`, yang justru tidak
 * pernah dibaca dari request. Alasannya dinyatakan di
 * ShiftService::resolveShiftIdForSave(): server hanya tahu waktu
 * SINKRONISASI, dan jarak ke waktu Simpan memang berjam-jam.
 */
it('resolveShiftIdForSave: memakai shift kiriman klien ketika ia milik line itu', function () {
    $malam = Shift::factory()->forProductionLine($this->lineA1)->malam()->create();

    // Jam server sengaja TIDAK diatur — yang membuktikan nilainya datang dari
    // klien adalah bahwa ia dipulangkan apa adanya tanpa line itu perlu
    // sedang berada di shift mana pun pada jam server.
    expect($this->service->resolveShiftIdForSave($malam->id, $this->lineA1->id))->toBe($malam->id);
});

it('resolveShiftIdForSave: menolak shift milik line lain', function () {
    $milikLineLain = Shift::factory()->forProductionLine($this->lineA2)->pagi()->create();

    $thrown = null;

    try {
        $this->service->resolveShiftIdForSave($milikLineLain->id, $this->lineA1->id);
    } catch (ValidationException $e) {
        $thrown = $e;
    }

    expect($thrown)->not->toBeNull();
    expect($thrown->errors())->toHaveKey('shift_id');

    // INILAH gunanya pemeriksaan ini: tanpa ia, laporan per-line akan memuat
    // shift yang bukan miliknya — bentuk kebocoran yang sudah tiga kali
    // terbukti pada cakupan Production Line di proyek ini.
});

it('resolveShiftIdForSave: menolak shift yang sudah ditutup', function () {
    $closed = Shift::factory()->forProductionLine($this->lineA1)->closed()->pagi()->create();

    expect(fn () => $this->service->resolveShiftIdForSave($closed->id, $this->lineA1->id))
        ->toThrow(ValidationException::class);
});

it('resolveShiftIdForSave: menolak id yang tidak dikenal sama sekali', function () {
    expect(fn () => $this->service->resolveShiftIdForSave('a2fe23a6-0000-4000-8000-000000000000', $this->lineA1->id))
        ->toThrow(ValidationException::class);
});

it('resolveShiftIdForSave: menurunkan dari jam server ketika klien tidak mengirim apa pun', function () {
    $pagi = Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();
    $malam = Shift::factory()->forProductionLine($this->lineA1)->malam()->create();

    // JAM SERVER DIBEKUKAN, bukan dibiarkan apa adanya: tanpa ini test lulus
    // atau gagal menurut jam dinding mesin yang menjalankannya, dan test yang
    // gagal pada pukul tertentu saja adalah test yang akan dihapus orang.
    $this->travelTo(Carbon::parse('2026-10-16 09:00:00'));
    expect($this->service->resolveShiftIdForSave(null, $this->lineA1->id))->toBe($pagi->id);
    expect($this->service->resolveShiftIdForSave('', $this->lineA1->id))->toBe($pagi->id);
    expect($this->service->resolveShiftIdForSave('   ', $this->lineA1->id))->toBe($pagi->id);

    $this->travelTo(Carbon::parse('2026-10-16 02:00:00'));
    expect($this->service->resolveShiftIdForSave(null, $this->lineA1->id))->toBe($malam->id);

    $this->travelBack();
});

it('resolveShiftIdForSave: null ketika jam server jatuh di celah, bukan galat', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    $this->travelTo(Carbon::parse('2026-10-16 20:00:00'));

    // Menolak menyimpan di sini berarti menukar data lapangan dengan
    // kelengkapan master — menghukum orang yang tidak dapat memperbaikinya.
    expect($this->service->resolveShiftIdForSave(null, $this->lineA1->id))->toBeNull();

    $this->travelBack();
});

/**
 * shiftIdAt() SENGAJA TANPA guardAdmin(), dan ia satu-satunya metode publik
 * kelas ini yang begitu. Pemanggilnya adalah ke-18 record service saat
 * OPERATOR menyimpan; gate admin di sini akan menjatuhkan setiap penyimpanan
 * operator dengan 403. Diasersi supaya "kelalaian" itu tidak diperbaiki oleh
 * orang berikutnya yang membaca docblock gate tiga lapis di kepala kelas.
 */
it('shiftIdAt dan resolveShiftIdForSave berjalan untuk peran non-admin', function (string $role) {
    $pagi = Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    $actor = User::factory()->role(UserRole::from($role))->forBusinessUnit($this->millA)->create();
    $this->actingAs($actor);

    expect($this->service->shiftIdAt($this->lineA1->id, '08:00'))->toBe($pagi->id);
    expect($this->service->resolveShiftIdForSave($pagi->id, $this->lineA1->id))->toBe($pagi->id);
})->with(['operator', 'supervisor', 'mill_management']);
