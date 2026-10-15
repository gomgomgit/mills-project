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
 * COVERAGE MAP — this file implements unit_test_cases 1, 2 and 4–37 of the
 * screen tech-spec. Each test carries its case number. The cases that do NOT
 * live here, and where they went instead:
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
 * RESOLVED SPEC INCONSISTENCY, TESTED BOTH WAYS. `start_time == end_time` is
 * REJECTED on the write path as VALIDATION_ERROR (entity constraint #2, case
 * 23), while durationMinutes() READS an equal pair as 1440 so a row inserted
 * directly renders as one full `covered` block (case 9). Case 23 therefore
 * goes through create(); case 9 seeds the row through the factory, never
 * through create().
 */

use App\Enums\UserRole;
use App\Exceptions\ShiftOverlapException;
use App\Models\BusinessUnit;
use App\Models\ProductionLine;
use App\Models\Shift;
use App\Models\User;
use App\Services\ShiftService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

// ── Rumus menit — SUMBU 0..1440 RELATIF 07:00 (case 7) ──────────────────

// Case 7 (formula half)
it('mengukur offset dari 07:00 dan bukan dari tengah malam', function () {
    expect(ShiftService::PRODUCTION_DAY_START_MINUTE)->toBe(420);
    expect(ShiftService::DAY_MINUTES)->toBe(1440);

    expect(ShiftService::offsetFromProductionDayStart('07:00'))->toBe(0);
    expect(ShiftService::offsetFromProductionDayStart('15:00'))->toBe(480);
    expect(ShiftService::offsetFromProductionDayStart('23:00'))->toBe(960);
    expect(ShiftService::offsetFromProductionDayStart('06:00'))->toBe(1380);
    // 07:00 the next day is 1440, which as an OFFSET wraps back to 0 — the
    // axis is half-open at its right end, and that is what keeps a shift
    // starting at 07:00 from being drawn off the end of the track.
    expect(ShiftService::offsetFromProductionDayStart('07:00:00'))->toBe(0);

    // Both column shapes read the same: PostgreSQL returns TIME as 'HH:MM:SS'
    // while SQLite returns whatever was stored.
    expect(ShiftService::minuteOfDay('23:00'))->toBe(1380);
    expect(ShiftService::minuteOfDay('23:00:00'))->toBe(1380);
    expect(ShiftService::displayTime('23:00:00'))->toBe('23:00');

    expect(ShiftService::timeFromOffset(0))->toBe('07:00');
    expect(ShiftService::timeFromOffset(480))->toBe('15:00');
    expect(ShiftService::timeFromOffset(960))->toBe('23:00');
    expect(ShiftService::timeFromOffset(1380))->toBe('06:00');
    expect(ShiftService::timeFromOffset(1440))->toBe('07:00');
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

it('memecah shift lintas batas hari produksi HANYA di dalam perhitungan', function () {
    // 07:00–15:00 → satu segmen.
    expect(ShiftService::segmentsFor(0, 480))->toBe([[0, 480]]);
    // 23:00–07:00 → masih SATU segmen: ia berhenti tepat di 1440.
    expect(ShiftService::segmentsFor(960, 480))->toBe([[960, 1440]]);
    // 05:00–09:00 → melewati batas hari produksi, jadi DUA segmen.
    expect(ShiftService::segmentsFor(1320, 240))->toBe([[1320, 1440], [0, 120]]);
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
it('menerbitkan start_offset_minutes dan duration_minutes dari sumbu 07:00', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();
    Shift::factory()->forProductionLine($this->lineA2)->malam()->create();

    $result = $this->service->listShifts($this->millA->id);

    $pagi = collect($result['data'])->firstWhere('name', 'Pagi');
    $malam = collect($result['data'])->firstWhere('name', 'Malam');

    expect($pagi['start_offset_minutes'])->toBe(0);
    expect($pagi['duration_minutes'])->toBe(480);
    expect($pagi['crosses_midnight'])->toBeFalse();
    expect($pagi['start_time'])->toBe('07:00');
    expect($pagi['end_time'])->toBe('15:00');
    expect($pagi['time_label'])->toBe('07:00 — 15:00');

    expect($malam['start_offset_minutes'])->toBe(960);
    expect($malam['duration_minutes'])->toBe(480);
    expect($malam['crosses_midnight'])->toBeTrue();
    // Jam selesai yang lebih kecil ditulis '+1' supaya tidak terbaca sebagai
    // salah input.
    expect($malam['time_label'])->toBe('23:00 — 07:00 +1');
    // Teks jam durasi dihitung di service, supaya blade tidak melakukan satu
    // pun aritmetika jam.
    expect($malam['duration_hours_text'])->toBe('8');
});

// Case 8
it('menerbitkan shift lintas tengah malam sebagai SATU blok, bukan dua', function () {
    $malam = Shift::factory()->forProductionLine($this->lineA1)->malam()->create();

    $entry = shiftCoverageFor($this->service->listShifts($this->millA->id, $this->lineA1->id), $this->lineA1);

    $blocks = shiftBlocksOf($entry);

    expect($blocks)->toHaveCount(1);
    expect($blocks[0]['shift_id'])->toBe($malam->id);
    expect($blocks[0]['start_offset_minutes'])->toBe(960);
    expect($blocks[0]['duration_minutes'])->toBe(480);
    expect($blocks[0]['crosses_midnight'])->toBeTrue();

    // TIDAK ada blok kedua di ujung kiri sumbu — memecahnya di payload akan
    // membuat blade menggambar dua potongan di kedua ujung batang.
    expect(collect($entry['blocks'])->where('start_offset_minutes', 0)->where('kind', 'shift'))
        ->toHaveCount(0);

    // Blok itu berhenti TEPAT di ujung kanan sumbu.
    expect($blocks[0]['start_offset_minutes'] + $blocks[0]['duration_minutes'])
        ->toBe(ShiftService::DAY_MINUTES);
});

/**
 * Case 9 — DISENGAJA LEWAT FACTORY, BUKAN create(). Jalur tulis menolak
 * 07:00–07:00 sebagai VALIDATION_ERROR (case 23); yang diuji di sini adalah
 * PEMBACAAN baris semacam itu, yang wajib terbaca sebagai satu blok penuh
 * 1440 menit dan bukan sebagai blok tak berdurasi yang hilang dari timeline.
 */
it('membaca satu shift 07:00–07:00 sebagai satu blok 1440 menit yang tercakup penuh', function () {
    Shift::factory()->forProductionLine($this->lineA1)->named('Penuh')->range('07:00:00', '07:00:00')->create();

    $entry = shiftCoverageFor($this->service->listShifts($this->millA->id, $this->lineA1->id), $this->lineA1);

    expect($entry['state'])->toBe('covered');
    expect($entry['uncovered_minutes'])->toBe(0);
    expect($entry['shift_count'])->toBe(1);
    expect(shiftBlocksOf($entry))->toHaveCount(1);
    expect(shiftBlocksOf($entry)[0]['duration_minutes'])->toBe(1440);
    expect(gapBlocksOf($entry))->toBe([]);
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
    expect($entry['starts_at_production_day'])->toBeTrue();
    expect(gapBlocksOf($entry))->toBe([]);

    expect(array_column(shiftBlocksOf($entry), 'label'))->toBe(['Pagi', 'Siang', 'Malam']);
    expect(array_column(shiftBlocksOf($entry), 'start_offset_minutes'))->toBe([0, 480, 960]);
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
    expect($gaps[0]['start_offset_minutes'])->toBe(480);
    expect($gaps[0]['duration_minutes'])->toBe(240);
    expect($gaps[0]['shift_id'])->toBeNull();
    expect($gaps[0]['time_label'])->toBe('15:00–19:00');

    // Blok tersusun urut offset, jadi celahnya memang DI ANTARA kedua shift.
    expect(array_column($entry['blocks'], 'kind'))->toBe(['shift', 'gap', 'shift']);
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

// Case 14
it('menandai starts_at_production_day false ketika union 24 jam tidak dimulai 07:00', function () {
    Shift::factory()->forProductionLine($this->lineA1)->named('A')->range('06:00:00', '14:00:00')->create();
    Shift::factory()->forProductionLine($this->lineA1)->named('B')->range('14:00:00', '22:00:00')->create();
    Shift::factory()->forProductionLine($this->lineA1)->named('C')->range('22:00:00', '06:00:00')->create();

    $entry = shiftCoverageFor($this->service->listShifts($this->millA->id, $this->lineA1->id), $this->lineA1);

    expect($entry['state'])->toBe('covered');
    expect($entry['uncovered_minutes'])->toBe(0);
    // PERINGATAN, BUKAN PENOLAKAN — ketiga shift tersimpan apa adanya.
    expect($entry['starts_at_production_day'])->toBeFalse();
    expect(Shift::count())->toBe(3);
});

// Case 15
it('menurunkan position dari jam mulai relatif 07:00, bukan dari urutan penambahan', function () {
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
    $blockIds = array_column(shiftBlocksOf($entry), 'shift_id');

    expect($blockIds)->toBe($dataIds);
    // Dan keduanya memang urut position, bukan sekadar kebetulan sama.
    expect(array_column($result['data'], 'position'))->toBe([0, 1, 2]);
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
    expect($blocks[$this->lineA1->id])->toHaveCount(1);
    expect($blocks[$this->lineA1->id][0]['kind'])->toBe('shift');
    expect($blocks[$this->lineA1->id][0]['start_offset_minutes'])->toBe(960);
    expect($blocks[$this->lineA1->id][0]['duration_minutes'])->toBe(480);
    expect($blocks[$this->lineA1->id][0]['crosses_midnight'])->toBeTrue();

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

    // Dua blok bersebelahan, nol blok gap di antaranya.
    $entry = shiftCoverageFor($this->service->listShifts($this->millA->id, $this->lineA1->id), $this->lineA1);
    expect(array_column($entry['blocks'], 'kind'))->toBe(['shift', 'shift', 'gap']);
    expect(gapBlocksOf($entry)[0]['start_offset_minutes'])->toBe(960);

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
    expect($overlap['start_offset_minutes'])->toBe(479);
    expect($overlap['end_offset_minutes'])->toBe(480);

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
    // 02:00 → offset 1140, 05:00 → 1320. Diukur di sumbu menit relatif 07:00.
    expect($overlap['start_offset_minutes'])->toBe(1140);
    expect($overlap['end_offset_minutes'])->toBe(1320);

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
    // 01:00 → offset 1080, 03:00 → 1200: seluruh kandidat (120 menit)
    // tertanam di dalam segmen Malam [960, 1440].
    expect($overlap['start_offset_minutes'])->toBe(1080);
    expect($overlap['end_offset_minutes'])->toBe(1200);
    expect(ShiftService::intersectionMinutes([960, 1440], [1080, 1200]))->toBe(120);

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

    // 08:00–20:00 menindih Pagi (offset 60..480) DAN Siang (480..780).
    $overlap = $this->service->findOverlap($this->lineA1->id, '08:00', '20:00');

    expect($overlap['shift']->name)->toBe('Pagi');
    expect($overlap['start_offset_minutes'])->toBe(60);
    expect($overlap['end_offset_minutes'])->toBe(480);
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

// Case 23
it('menolak start_time sama dengan end_time sebagai VALIDATION_ERROR, bukan SHIFT_OVERLAP', function () {
    $thrown = null;

    try {
        $this->service->create(shiftPayload($this->lineA1, [
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
    expect($thrown)->not->toBeInstanceOf(ShiftOverlapException::class);
    expect(Shift::count())->toBe(0);

    // Pemeriksaannya SESUDAH normalisasi: '07:00' dan '07:00:00' adalah jam
    // yang sama, dan perbandingan string tidak mengetahuinya.
    expect(fn () => $this->service->create(shiftPayload($this->lineA1, [
        'name' => 'Nol 2',
        'start_time' => '07:00',
        'end_time' => '07:00:00',
    ])))->toThrow(ValidationException::class);
    expect(Shift::count())->toBe(0);
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
            'production_line_id' => (string) \Illuminate\Support\Str::uuid(),
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
    expect(fn () => $this->service->update((string) \Illuminate\Support\Str::uuid(), ['name' => 'X']))
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
    // Tepat di posisi yang ditinggalkan Siang (15:00 → offset 480).
    expect($gaps[0]['start_offset_minutes'])->toBe(480);
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
    expect(fn () => $this->service->delete((string) \Illuminate\Support\Str::uuid()))
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
 */
it('tidak punya kolom hari maupun masa berlaku, dan unik pada (production_line_id, name)', function () {
    foreach (['id', 'production_line_id', 'name', 'start_time', 'end_time', 'created_by', 'updated_by'] as $column) {
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

    // Tumpang tindih TIDAK dapat ditegakkan index — dua rentang beririsan
    // bukan duplikasi nilai. Penegakannya ada di lapisan aplikasi, dan unique
    // index atas jamnya justru akan SALAH: dua line boleh punya shift pada
    // jam yang sama persis.
    expect($uniqueIndexes->contains(fn (array $columns) => in_array('start_time', array_values($columns), true)))
        ->toBeFalse('jam tidak boleh dijadikan unique index');
});
