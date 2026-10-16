<?php

/**
 * KelolaShiftTest (Feature/Livewire) — screen-170--kelola-shift /
 * usecase-176--kelola-shift (Kelola Shift per Production Line).
 *
 * Component tests for App\Livewire\MasterData\KelolaShift, one per
 * test_scenarios entry whose `component_test` is non-empty, plus
 * unit_test_cases 3, 38, 39, 40, 44 and 45 — the cases whose subject is the
 * component or the blade rather than the service. Mirrors
 * tests/Feature/Livewire/KelolaPeriodePelaporanTest.php's structure.
 *
 * SPEC v2 (2026-10-16) — scenarios 29–33 landed with two decisions: jam
 * berimpit now MEANS a 24-hour shift (accepted while the line holds no other
 * live shift), and deletion is a SOFT DELETE. Their component half is the
 * final section of this file. Scenarios 16 and 21 were REBUILT rather than
 * relaxed: both used to set themselves up on an EMPTY line, which is now
 * exactly the accepted case, so a test left as it was would have been
 * asserting the rule the product owner replaced.
 *
 * SUPERSEDE-ON-EDIT IS NOT TESTED HERE AND HAS NO TEST ANYWHERE
 * (business_logic 29–30). Its trigger is "a record references this shift" and
 * no column in the system references a shift today, so editing hours MUTATES
 * the row — scenario 32 below asserts precisely that, which is the behaviour
 * that actually exists.
 *
 * ── TWO SCENARIOS CANNOT BE FULLY EXPRESSED HERE, AND SAY SO ────────────
 *
 * Scenario 8 ("pratinjau menampilkan tumpang tindih sebelum simpan") and
 * scenario 9 ("jam diketik separuh") are about the LIVE PREVIEW inside the
 * form, which is Alpine running in a browser — Livewire renders its markup
 * but never evaluates its expressions. What this file CAN prove is the half
 * that makes the preview's central claim possible at all: the form's fields
 * are bound with plain `wire:model` (deferred), so NOT ONE network request is
 * sent while the Admin types, and the preview's own data (`previewBlocks`)
 * comes from ShiftService::shiftBlocksByLine() rather than being recomputed
 * in JavaScript. The moving blocks and the conflict marker are asserted in
 * e2e-web/tests/kelola-shift.spec.ts. Scenario 27 (narrow widths, nothing
 * under 14px) is likewise a measured-geometry claim and lives there too; its
 * testable half here is that this page declares no literal font size at all.
 *
 * ── ACCESS CONTROL IS GUARDED THREE TIMES, AND ALL THREE ARE ASSERTED ───
 *
 * routes/web.php ('auth' + 'role:admin', EnsureRole aborts before mount),
 * KelolaShift::mount() (abort_unless, case 3 — the second layer), and
 * ShiftService::guardAdmin() (the third, tested in
 * tests/Unit/Services/ShiftServiceTest.php). Unlike screen-128, where the
 * component owns no guard of its own, mounting this component as a non-admin
 * is a real test here: case 3 exists precisely because relying on the
 * middleware alone was rejected.
 *
 * BINDING SHAPE: `businessUnitId` and `productionLineId` are bare top-level
 * URL-bound properties; the form fields are `form.production_line_id`,
 * `form.name`, `form.start_time`, `form.end_time`. Delete confirmation is
 * inline (`askDelete` → `confirmDelete` / `cancelDelete`), and its label names
 * the shift AND its line.
 *
 * BADGE ASSERTIONS TARGET THE BADGE ELEMENT, never a container scan: the
 * words "belum diatur" also appear in the explanatory sentence under the bar
 * and in the table's note row, so `assertSee('belum diatur')` would match in
 * the wrong place and pass while the badge said something else entirely.
 * ksBadgeText() below is the only way this file reads a badge.
 */

use App\Enums\UserRole;
use App\Livewire\MasterData\KelolaShift;
use App\Models\BusinessUnit;
use App\Models\ProductionLine;
use App\Models\Shift;
use App\Models\User;
use App\Services\ShiftService;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function () {
    $this->millA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->millB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    // Named rather than faker-numbered: coverage is ordered by line name, so
    // "Line 1 is the first bar" must mean the same thing on every run.
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
 * THE ONLY WAY THIS FILE READS A COVERAGE BADGE — the badge ELEMENT's own
 * text, not a scan of its container.
 *
 * "belum diatur" appears three times on a screen with one unconfigured line:
 * on the badge, in the explanatory paragraph under the bar, and in the table's
 * note row. A container scan therefore passes while the badge reads something
 * else, which is exactly the defect class implementation_notes #15 names.
 */
function ksBadgeText(string $html, string $lineId): string
{
    $matched = preg_match(
        '/<span[^>]*data-testid="coverage-badge-'.preg_quote($lineId, '/').'"[^>]*>(.*?)<\/span>/s',
        $html,
        $matches
    );

    expect($matched)->toBe(1, "badge cakupan untuk line $lineId tidak ditemukan");

    return trim(html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5));
}

/**
 * Every value of one data-testid prefix, IN DOCUMENT ORDER. Used to compare
 * the table's row order with the timeline's block order: two independent
 * sorts are the fastest way to make the two views disagree, so the invariant
 * is measured rather than assumed.
 *
 * @return list<string>
 */
function ksOrderedTestIds(string $html, string $prefix): array
{
    preg_match_all(
        '/data-testid="'.preg_quote($prefix, '/').'([^"]+)"/',
        $html,
        $matches
    );

    return $matches[1];
}

/**
 * The opening tag of one element carrying $testId, so a test can assert on
 * THAT element's attributes (`disabled` / `readonly` in particular) instead of
 * on the whole page, where another element's attribute would satisfy it.
 */
function ksElementTag(string $html, string $testId): string
{
    $matched = preg_match(
        '/<(?:input|select|textarea|button)[^>]*data-testid="'.preg_quote($testId, '/').'"[^>]*>/',
        $html,
        $matches
    );

    expect($matched)->toBe(1, "elemen dengan data-testid=\"$testId\" tidak ditemukan");

    return $matches[0];
}

/**
 * Statements that touched the `shifts` table while $callback ran. "Zero shift
 * queries" is a claim three scenarios make; counting queries is the only way
 * to prove it.
 *
 * @return list<string>
 */
function ksShiftQueriesDuring(callable $callback): array
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

/** Fills the create form for one line and saves it. */
function ksFillAndSave(
    Testable $component,
    ProductionLine $line,
    string $name,
    string $startTime,
    string $endTime
): Testable {
    return $component
        ->call('openCreateForm')
        ->set('form.production_line_id', $line->id)
        ->set('form.name', $name)
        ->set('form.start_time', $startTime)
        ->set('form.end_time', $endTime)
        ->call('save');
}

// ── Scenario 1 ──────────────────────────────────────────────────────────

// Scenario 1: "berhasil menambahkan shift"
it('berhasil: menambahkan Pagi 07:00–15:00 pada Line 1 lalu blok, badge dan barisnya muncul selaras', function () {
    $component = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id);

    ksFillAndSave($component, $this->lineA1, 'Pagi', '07:00', '15:00')
        ->assertHasNoErrors()
        ->assertSet('showForm', false)
        ->assertSet('formErrorMessage', null)
        ->assertSet('successMessage', 'Shift berhasil dibuat.');

    $stored = Shift::firstOrFail();
    expect($stored->production_line_id)->toBe($this->lineA1->id);
    expect($stored->created_by)->toBe($this->admin->id);

    // Blok timeline: offset 0, durasi 480 — angkanya datang dari server.
    $coverage = collect($component->viewData('coverage'));
    $line1 = $coverage->firstWhere('production_line_id', $this->lineA1->id);

    expect($line1['state'])->toBe('gap');
    expect($line1['shift_count'])->toBe(1);
    $shiftBlocks = collect($line1['blocks'])->where('kind', 'shift')->values();
    expect($shiftBlocks)->toHaveCount(1);
    expect($shiftBlocks[0]['start_offset_minutes'])->toBe(0);
    expect($shiftBlocks[0]['duration_minutes'])->toBe(480);

    $html = $component->html();

    // Badge Line 1 — ELEMENNYA, dan angka jam celahnya disebut.
    expect(ksBadgeText($html, $this->lineA1->id))->toBe('ada celah 16 jam');

    // Baris tabel muncul, dan urutannya sama dengan urutan blok timeline.
    expect(ksOrderedTestIds($html, 'shift-row-'))->toBe([$stored->id]);
    expect(ksOrderedTestIds($html, 'timeline-block-'))->toBe([$stored->id]);
});

// ── Scenario 2 ──────────────────────────────────────────────────────────

/**
 * Scenario 2: "mill belum dipilih" — NOL KUERI SHIFT, bukan daftar kosong
 * yang dihitung dulu lalu disembunyikan. render() tidak memanggil
 * listShifts() sama sekali selama $businessUnitId kosong.
 */
it('mill belum dipilih: nol timeline, nol baris, dan NOL kueri shift', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    $component = null;

    $queries = ksShiftQueriesDuring(function () use (&$component) {
        $component = Livewire::actingAs($this->admin)->test(KelolaShift::class);
    });

    expect($queries)->toBe([]);

    $component
        ->assertSet('businessUnitId', '')
        ->assertViewHas('coverage', [])
        ->assertViewHas('shifts', [])
        // Arahan memilih mill terlihat…
        ->assertSeeHtml('data-testid="pick-mill-guide"')
        ->assertSee('Pilih mill terlebih dahulu');

    $html = $component->html();

    // …dan tidak ada satu pun batang timeline maupun baris shift.
    expect(ksOrderedTestIds($html, 'timeline-track-'))->toBe([]);
    expect(ksOrderedTestIds($html, 'shift-row-'))->toBe([]);
});

// ── Scenario 3 ──────────────────────────────────────────────────────────

// Scenario 3: "mill belum punya Production Line"
it('mill tanpa Production Line: menyatakannya dan mengarahkan ke Kelola Production Line', function () {
    $millKosong = BusinessUnit::factory()->create(['name' => 'Mill Tanpa Line']);

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $millKosong->id);

    $component
        ->assertViewHas('coverage', [])
        ->assertViewHas('shifts', [])
        // Bukan timeline kosong tanpa penjelasan.
        ->assertSeeHtml('data-testid="no-production-line-guide"')
        ->assertSee('Mill ini belum punya Production Line')
        ->assertSeeHtml('data-testid="production-line-link"')
        ->assertSeeHtml('href="'.route('master-data.production-lines').'"');

    $html = $component->html();
    expect(ksOrderedTestIds($html, 'timeline-track-'))->toBe([]);
    // Arahan "pilih mill" TIDAK muncul di sini — mill-nya sudah dipilih.
    expect($html)->not->toContain('data-testid="pick-mill-guide"');
});

// ── Scenario 4 ──────────────────────────────────────────────────────────

/**
 * Scenario 4: "line belum punya shift" — 'belum diatur' DAN BUKAN 'ada celah
 * 24 jam'. Dua hal berbeda: yang pertama belum dikerjakan, yang kedua
 * keputusan yang mungkin salah.
 */
it('line tanpa shift: badge berbunyi belum diatur dan bloknya polos tanpa arsiran', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id);

    $line3 = collect($component->viewData('coverage'))
        ->firstWhere('production_line_id', $this->lineA3->id);

    expect($line3['state'])->toBe('unconfigured');
    expect($line3['shift_count'])->toBe(0);
    expect($line3['uncovered_minutes'])->toBe(0);
    expect($line3['blocks'])->toBe([]);

    $html = $component->html();

    // Badge-NYA, bukan pemindaian kontainer.
    expect(ksBadgeText($html, $this->lineA3->id))->toBe('belum diatur');
    expect(ksBadgeText($html, $this->lineA3->id))->not->toBe('ada celah 24 jam');

    // Bloknya .ks-block-empty — POLOS. Kelas celah .ks-block-gap, yang
    // membawa arsiran, TIDAK dipakai untuk line ini.
    $emptyBlock = preg_match(
        '/<div[^>]*class="([^"]*)"[^>]*data-testid="timeline-empty-'.preg_quote($this->lineA3->id, '/').'"/',
        $html,
        $matches
    );
    expect($emptyBlock)->toBe(1, 'blok "belum diatur" tidak ditemukan');
    expect($matches[1])->toContain('ks-block-empty');
    $this->assertStringNotContainsString(
        'ks-block-gap',
        $matches[1],
        'blok "belum diatur" tidak boleh memakai kelas celah yang berarsiran'
    );

    // Kalimat yang membedakan keduanya dirender.
    $component->assertSeeHtml('data-testid="unconfigured-note-'.$this->lineA3->id.'"');
    $component->assertSee('belum diatur');
    $component->assertSee('celah 24 jam');

    // Line itu juga dinyatakan di tabel sebagai CATATAN, bukan sebagai baris
    // kosong yang terbaca seperti shift tanpa jam.
    $component->assertSeeHtml('data-testid="unconfigured-row-'.$this->lineA3->id.'"');
    // Urutannya mengikuti urutan coverage (nama line), jadi Line 2 lebih
    // dahulu daripada Line 3 — dan Line 1, yang punya shift, tidak punya
    // baris catatan sama sekali.
    expect(ksOrderedTestIds($html, 'unconfigured-row-'))
        ->toBe([$this->lineA2->id, $this->lineA3->id]);
});

// ── Scenario 5 ──────────────────────────────────────────────────────────

// Scenario 5: "filter diset seluruh line"
it('filter line kosong: satu batang per line, dua belas line tanpa satu pun 422', function () {
    for ($i = 4; $i <= 12; $i++) {
        ProductionLine::factory()->forBusinessUnit($this->millA)->create([
            'name' => 'Line '.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
        ]);
    }

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id)
        ->assertSet('productionLineId', '')
        ->assertHasNoErrors();

    expect($component->viewData('coverage'))->toHaveCount(12);
    expect(ksOrderedTestIds($component->html(), 'timeline-track-'))->toHaveCount(12);

    // production_line_id yang absen diterima — berbeda dari layar laporan
    // tempat line WAJIB dipilih.
    $component->assertSee('Semua line ditampilkan');
});

// ── Scenario 6 ──────────────────────────────────────────────────────────

// Scenario 6: "cakupan penuh tetapi tidak selaras hari produksi"
it('union 24 jam yang dimulai 06:00: badge tercakup penuh DAN peringatan ketidakselarasan', function () {
    Shift::factory()->forProductionLine($this->lineA1)->named('A')->range('06:00:00', '14:00:00')->create();
    Shift::factory()->forProductionLine($this->lineA1)->named('B')->range('14:00:00', '22:00:00')->create();
    Shift::factory()->forProductionLine($this->lineA1)->named('C')->range('22:00:00', '06:00:00')->create();

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id)
        ->set('productionLineId', $this->lineA1->id);

    $entry = collect($component->viewData('coverage'))->firstWhere('production_line_id', $this->lineA1->id);

    expect($entry['state'])->toBe('covered');
    expect($entry['uncovered_minutes'])->toBe(0);
    expect($entry['starts_at_production_day'])->toBeFalse();

    expect(ksBadgeText($component->html(), $this->lineA1->id))->toBe('tercakup 24 jam');

    // PERINGATAN, BUKAN PENOLAKAN: ketiga shift tetap tersimpan.
    $component->assertSeeHtml('data-testid="misaligned-note-'.$this->lineA1->id.'"');
    $component->assertSee('Jadwal line ini tidak dimulai 07:00.');
    $component->assertSee('peringatan, bukan');
    $component->assertHasNoErrors();
    expect(Shift::count())->toBe(3);
});

// ── Scenario 7 ──────────────────────────────────────────────────────────

// Scenario 7: "shift melewati tengah malam"
it('shift Malam 23:00–07:00: satu blok offset 960 durasi 480, label +1 pada tabel dan blok', function () {
    $component = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id);

    ksFillAndSave($component, $this->lineA1, 'Malam', '23:00', '07:00')
        ->assertHasNoErrors()
        ->assertSet('successMessage', 'Shift berhasil dibuat.');

    $stored = Shift::firstOrFail();

    $entry = collect($component->viewData('coverage'))->firstWhere('production_line_id', $this->lineA1->id);
    $shiftBlocks = collect($entry['blocks'])->where('kind', 'shift')->values();

    // TEPAT SATU blok, bukan dua potongan di kedua ujung sumbu.
    expect($shiftBlocks)->toHaveCount(1);
    expect($shiftBlocks[0]['start_offset_minutes'])->toBe(960);
    expect($shiftBlocks[0]['duration_minutes'])->toBe(480);
    expect($shiftBlocks[0]['crosses_midnight'])->toBeTrue();

    $html = $component->html();

    // Satu elemen blok untuk shift itu.
    expect(ksOrderedTestIds($html, 'timeline-block-'))->toBe([$stored->id]);

    // Label '23:00 — 07:00 +1' pada blok DAN pada barisnya.
    $component->assertSee('23:00 — 07:00 +1');
    expect(collect($component->viewData('shifts'))->first()['time_label'])->toBe('23:00 — 07:00 +1');
    expect(collect($component->viewData('shifts'))->first()['crosses_midnight'])->toBeTrue();
    // Jam selesai di kolom tabel ikut ber-'+1' supaya tidak terbaca sebagai
    // salah input.
    $component->assertSee('07:00 +1');

    // Blade memetakan menit ke persen dan tidak menghitung jam: 960/14,4 =
    // 66,6667% dan 480/14,4 = 33,3333%.
    $this->assertStringContainsString(
        'left:66.6667%;width:33.3333%',
        $html,
        'blok Malam tidak diposisikan dari start_offset_minutes/durasi'
    );
});

// ── Scenario 8 + 9 ──────────────────────────────────────────────────────

/**
 * Scenarios 8 and 9 — THE HALF THAT IS TESTABLE WITHOUT A BROWSER.
 *
 * The preview's central claim is "NOL permintaan jaringan terkirim" while the
 * Admin types. That is decided by the binding: plain `wire:model` is deferred,
 * `wire:model.live` would round-trip on every keystroke. The moving blocks and
 * the conflict marker themselves are Alpine, and are asserted in
 * e2e-web/tests/kelola-shift.spec.ts.
 */
it('form: field terikat wire:model deferred sehingga nol permintaan terkirim saat mengetik', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    $html = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id)
        ->call('openCreateForm')
        ->assertSet('showForm', true)
        ->html();

    foreach (['form-name-input', 'form-start-time-input', 'form-end-time-input', 'form-line-select'] as $testId) {
        $tag = ksElementTag($html, $testId);

        $this->assertStringContainsString('wire:model=', $tag, "$testId tidak terikat wire:model");
        // .live / .blur / .debounce semuanya memicu putaran jaringan saat
        // mengetik — pratinjau justru ada supaya tidak ada yang terkirim.
        $this->assertStringNotContainsString('wire:model.live', $tag, "$testId terikat .live");
        $this->assertStringNotContainsString('wire:model.blur', $tag, "$testId terikat .blur");
    }

    // Pratinjau ada, dan blok shift yang sudah ada datang dari SERVER
    // (shiftBlocksByLine), bukan dihitung ulang di JavaScript.
    expect($html)->toContain('data-testid="shift-preview"');
    expect($html)->toContain('data-testid="preview-track"');

    $previewBlocks = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id)
        ->viewData('previewBlocks');

    expect($previewBlocks)->toHaveKey($this->lineA1->id);
    expect($previewBlocks[$this->lineA1->id][0]['start_offset_minutes'])->toBe(0);
    expect($previewBlocks[$this->lineA1->id][0]['duration_minutes'])->toBe(480);
});

/**
 * unit_test_cases 41 (the part that can be pinned without a browser) — the
 * client preview uses THE SAME offset formula as the server. The Alpine
 * expression is read from the rendered markup and compared against
 * ShiftService's own constants, so changing one without the other reddens.
 * Its visible behaviour is asserted in the Playwright spec.
 */
it('pratinjau klien memakai rumus offset dan durasi yang sama dengan server', function () {
    $html = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id)
        ->call('openCreateForm')
        ->html();

    expect(ShiftService::PRODUCTION_DAY_START_MINUTE)->toBe(420);
    expect(ShiftService::DAY_MINUTES)->toBe(1440);

    // offset = (menit - 420 + 1440) % 1440
    $this->assertStringContainsString(
        '(this.minuteOfDay(value) - 420 + 1440) % 1440',
        $html,
        'pratinjau tidak memakai rumus offset-dari-07:00 milik server'
    );
    // durasi = (selesai - mulai + 1440) % 1440, dengan 0 dibaca 1440
    $this->assertStringContainsString(
        'diff === 0 ? 1440 : diff',
        $html,
        'pratinjau tidak membaca jam berimpit sebagai 1440 seperti server'
    );
    // menit → persen: 1440 menit = 100%, jadi satu persen = 14,4 menit —
    // angka yang sama dengan $pct() di blade.
    $this->assertStringContainsString('minutes / 14.4', $html, 'pratinjau memakai skala persen yang lain');
});

// ── Scenario 10 ─────────────────────────────────────────────────────────

/**
 * Scenario 10: "jam diketik manual tanpa picker". Field waktu menerima
 * pengetikan manual MAUPUN picker — <input type="time"> memberi keduanya —
 * dan TIDAK ADA input yang dinonaktifkan maupun readonly di form ini (aturan
 * form web proyek ini).
 */
it('jam diketik manual: tersimpan, dan nol field yang disabled atau readonly di form', function () {
    $component = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id);

    ksFillAndSave($component, $this->lineA1, 'Pagi', '07:00', '15:00')
        ->assertHasNoErrors()
        ->assertSet('successMessage', 'Shift berhasil dibuat.');

    expect(Shift::firstOrFail()->start_time)->toBe('07:00:00');

    // Form dibuka kembali, lalu SELURUH input/select/textarea di dalamnya
    // diperiksa — bukan hanya yang kebetulan diingat.
    $html = $component->call('openCreateForm')->html();

    $formHtml = substr($html, (int) strpos($html, 'class="ks-form"'));

    preg_match_all('/<(?:input|select|textarea)[^>]*>/', $formHtml, $fields);

    expect($fields[0])->not->toBe([]);

    foreach ($fields[0] as $field) {
        $this->assertStringNotContainsString('disabled', $field, "field dinonaktifkan: $field");
        $this->assertStringNotContainsString('readonly', $field, "field readonly: $field");
    }

    // Kedua field waktu memang <input type="time">: picker DAN pengetikan
    // manual, keduanya.
    foreach (['form-start-time-input', 'form-end-time-input'] as $testId) {
        $this->assertStringContainsString('type="time"', ksElementTag($html, $testId), "$testId bukan input waktu");
    }
});

// ── Scenario 11 ─────────────────────────────────────────────────────────

// Scenario 11: "tumpang tindih ditolak saat simpan"
it('tumpang tindih: form tetap terbuka dengan isian utuh dan pesan menyebut nama serta rentangnya', function () {
    Shift::factory()->forProductionLine($this->lineA1)->siang()->create();

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id);

    ksFillAndSave($component, $this->lineA1, 'Sore', '22:00', '02:00')
        // Simpan yang ditolak tidak boleh membuat Admin kehilangan isiannya.
        ->assertSet('showForm', true)
        ->assertSet('form.name', 'Sore')
        ->assertSet('form.start_time', '22:00')
        ->assertSet('form.end_time', '02:00')
        ->assertSet('successMessage', null);

    $message = $component->get('formErrorMessage');

    expect($message)->toBeString();
    // Bukan hanya 'jam bertindih': nama shift pembanding DAN rentang jam
    // tindihannya disebut.
    $this->assertStringContainsString('Siang', $message, 'pesan tidak menyebut shift pembanding');
    $this->assertStringContainsString('22:00', $message, 'pesan tidak menyebut awal rentang tindihan');
    $this->assertStringContainsString('23:00', $message, 'pesan tidak menyebut akhir rentang tindihan');

    // Pesan tingkat-form, bukan error per-field: ia konflik antara dua field
    // jam dan sebuah baris yang sudah ada.
    $component->assertHasNoErrors();
    $component->assertSeeHtml('data-testid="form-error"');

    expect(Shift::count())->toBe(1);
});

// ── Scenario 12 ─────────────────────────────────────────────────────────

// Scenario 12: "pergantian shift berimpit diterima"
it('pergantian berimpit: Siang mulai tepat 15:00 tersimpan dan kedua blok bersebelahan', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id);

    ksFillAndSave($component, $this->lineA1, 'Siang', '15:00', '23:00')
        ->assertHasNoErrors()
        ->assertSet('formErrorMessage', null)
        ->assertSet('successMessage', 'Shift berhasil dibuat.');

    expect(Shift::count())->toBe(2);

    $entry = collect($component->viewData('coverage'))->firstWhere('production_line_id', $this->lineA1->id);

    // Dua blok bersebelahan, NOL blok gap di antaranya — celahnya hanya yang
    // di belakang keduanya.
    expect(array_column($entry['blocks'], 'kind'))->toBe(['shift', 'shift', 'gap']);
    expect($entry['blocks'][0]['start_offset_minutes'] + $entry['blocks'][0]['duration_minutes'])
        ->toBe($entry['blocks'][1]['start_offset_minutes']);
    expect($entry['blocks'][2]['start_offset_minutes'])->toBe(960);
});

// ── Scenario 13 ─────────────────────────────────────────────────────────

// Scenario 13: "tumpang tindih satu menit ditolak"
it('tindihan satu menit: ditolak dan rentang 14:59–15:00 disebut pada pesannya', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id);

    ksFillAndSave($component, $this->lineA1, 'Sore', '14:59', '23:00')
        ->assertSet('showForm', true);

    $message = $component->get('formErrorMessage');

    $this->assertStringContainsString('Pagi', $message, 'pesan tidak menyebut shift pembanding');
    $this->assertStringContainsString('14:59', $message, 'pesan tidak menyebut rentang satu menit');
    $this->assertStringContainsString('15:00', $message, 'pesan tidak menyebut rentang satu menit');

    // Batasnya pada IRISAN NOL, bukan pada toleransi menit: satu menit sudah
    // cukup untuk ditolak (bandingkan dengan skenario 12, yang diterima).
    expect(Shift::count())->toBe(1);
});

// ── Scenario 14 ─────────────────────────────────────────────────────────

// Scenario 14: "nama shift sudah dipakai di line yang sama"
it('nama sudah dipakai pada line yang sama: error di bawah field Nama, bukan SHIFT_OVERLAP', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id);

    // Jamnya sengaja TIDAK bertindih: yang memerah harus keunikan nama.
    ksFillAndSave($component, $this->lineA1, 'Pagi', '15:00', '23:00')
        ->assertHasErrors(['form.name'])
        ->assertSet('showForm', true)
        // Pesan tingkat-form TIDAK dipakai untuk ini: ia error per-field.
        ->assertSet('formErrorMessage', null)
        ->assertSeeHtml('data-testid="error-name"')
        ->assertSee('Nama Shift sudah dipakai pada Production Line ini.');

    expect(Shift::count())->toBe(1);
});

// ── Scenario 15 ─────────────────────────────────────────────────────────

// Scenario 15: "nama shift sama dengan line lain diterima"
it('nama yang sama pada line berbeda: tersimpan dan muncul sebagai dua baris', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id);

    ksFillAndSave($component, $this->lineA2, 'Pagi', '07:00', '15:00')
        ->assertHasNoErrors()
        ->assertSet('successMessage', 'Shift berhasil dibuat.');

    expect(Shift::where('name', 'Pagi')->count())->toBe(2);

    $rows = collect($component->viewData('shifts'))->where('name', 'Pagi');

    expect($rows)->toHaveCount(2);
    // Dua baris dengan kolom Production Line yang BERBEDA.
    expect($rows->pluck('production_line_name')->sort()->values()->all())->toBe(['Line 1', 'Line 2']);
    expect(ksOrderedTestIds($component->html(), 'shift-row-'))->toHaveCount(2);
});

// ── Scenario 16 + 21 ────────────────────────────────────────────────────

/**
 * Scenario 16 — DITOLAK HANYA PADA LINE YANG SUDAH PUNYA SHIFT HIDUP (revisi
 * spec v2 2026-10-16). Versi sebelumnya test ini menyimpan jam berimpit pada
 * line yang KOSONG dan mengharapkan penolakan; cabang itu kini DITERIMA, jadi
 * prasyaratnya diubah alih-alih asersinya dilemahkan: Line 1 diberi Pagi
 * 07:00–15:00 lebih dulu, sehingga jam berimpit di sana hanya bisa berarti
 * salah ketik. Cabang diterimanya ada di test berikutnya.
 */
it('jam berimpit pada line yang sudah terisi: error di bawah Jam Selesai menyebut durasi nol', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id);

    ksFillAndSave($component, $this->lineA1, 'Nol', '07:00', '07:00')
        ->assertHasErrors(['form.end_time'])
        ->assertSet('showForm', true)
        // BUKAN SHIFT_OVERLAP: keduanya kesalahan berbeda dengan pesan
        // berbeda, dan yang ini per-field. formErrorMessage adalah tempat
        // pesan per-form (tumpang tindih) mendarat, jadi ia WAJIB kosong.
        ->assertSet('formErrorMessage', null)
        ->assertSeeHtml('data-testid="error-end-time"')
        ->assertSee('shift berdurasi nol');

    // Keterangan di bawah Jam Selesai menyatakan ATURAN BARU-nya, bukan
    // larangan tanpa syarat versi v1 — Admin yang membaca larangan lama akan
    // menyimpulkan shift 24 jam tidak mungkin dibuat sama sekali.
    $this->assertStringContainsString(
        'hanya diterima bila Production Line ini belum punya shift lain',
        $component->html(),
        'keterangan di bawah Jam Selesai masih menyatakan aturan v1'
    );

    expect(Shift::where('production_line_id', $this->lineA1->id)->count())->toBe(1);
});

/**
 * Scenario 21 — SHIFT TUNGGAL MENUTUP 24 JAM, LEWAT FORM. Baris ini DULU
 * ditanam lewat factory justru karena save() menolaknya; sejak v2 save()
 * MENGHASILKANNYA pada line yang belum punya shift hidup, jadi ia disimpan
 * lewat form di sini. Menanamnya lewat factory akan melewati satu-satunya hal
 * yang berubah.
 */
it('menyimpan 07:00–07:00 lewat form pada line kosong: satu blok 1440 menit dan badge tercakup 24 jam', function () {
    $component = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id)
        ->set('productionLineId', $this->lineA3->id);

    ksFillAndSave($component, $this->lineA3, 'Penuh', '07:00', '07:00')
        ->assertHasNoErrors()
        ->assertSet('showForm', false)
        ->assertSet('formErrorMessage', null);

    expect(Shift::where('production_line_id', $this->lineA3->id)->count())->toBe(1);

    $entry = collect($component->viewData('coverage'))->firstWhere('production_line_id', $this->lineA3->id);

    expect($entry['state'])->toBe('covered');
    expect($entry['blocks'])->toHaveCount(1);
    expect($entry['blocks'][0]['duration_minutes'])->toBe(1440);
    expect(collect($entry['blocks'])->where('kind', 'gap'))->toHaveCount(0);

    expect(ksBadgeText($component->html(), $this->lineA3->id))->toBe('tercakup 24 jam');
    // Satu blok selebar penuh: 1440/14,4 = 100%.
    $this->assertStringContainsString('left:0%;width:100%', $component->html());
});

// ── Scenario 17 ─────────────────────────────────────────────────────────

// Scenario 17: "mengubah shift sehingga bertindih"
it('ubah jam selesai menjadi bertindih: ditolak; ubah hanya namanya tetap berhasil', function () {
    $pagi = Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();
    Shift::factory()->forProductionLine($this->lineA1)->siang()->create();

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id)
        ->call('openEditForm', $pagi->id)
        ->assertSet('showForm', true)
        ->assertSet('editingId', $pagi->id)
        // Form terisi dari baris yang ada, jamnya 'HH:MM' — bentuk yang
        // diterima <input type="time">.
        ->assertSet('form.name', 'Pagi')
        ->assertSet('form.start_time', '07:00')
        ->assertSet('form.end_time', '15:00')
        ->set('form.end_time', '16:00')
        ->call('save')
        ->assertSet('showForm', true);

    $message = $component->get('formErrorMessage');

    $this->assertStringContainsString('Siang', $message, 'pesan tidak menyebut shift pembanding');
    $this->assertStringContainsString('15:00', $message, 'pesan tidak menyebut awal rentang tindihan');
    $this->assertStringContainsString('16:00', $message, 'pesan tidak menyebut akhir rentang tindihan');
    expect($pagi->refresh()->end_time)->toBe('15:00:00');

    // Dan mengubah HANYA namanya berhasil — shift yang sedang diubah
    // dikecualikan dari pembanding. Tanpa pengecualian itu tidak ada yang
    // pernah bisa disimpan.
    $component
        ->set('form.end_time', '15:00')
        ->set('form.name', 'Pagi Awal')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('formErrorMessage', null)
        ->assertSet('successMessage', 'Shift berhasil diperbarui.')
        ->assertSet('showForm', false);

    expect($pagi->refresh()->name)->toBe('Pagi Awal');
    expect($pagi->refresh()->updated_by)->toBe($this->admin->id);
});

// ── Scenario 18 ─────────────────────────────────────────────────────────

// Scenario 18: "memindahkan shift ke line lain"
it('memindahkan shift ke line yang jamnya terisi: ditolak dengan menyebut shift di line TUJUAN', function () {
    $pagi = Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();
    Shift::factory()->forProductionLine($this->lineA2)->named('Pagi Line 2')->range('07:00:00', '15:00:00')->create();

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id)
        ->call('openEditForm', $pagi->id)
        ->set('form.production_line_id', $this->lineA2->id)
        ->call('save')
        ->assertSet('showForm', true);

    // Pembandingnya shift di line TUJUAN; shift di line asal tidak lagi
    // relevan setelah shift-nya pindah.
    $this->assertStringContainsString(
        'Pagi Line 2',
        $component->get('formErrorMessage'),
        'pesan tidak menyebut shift di line tujuan'
    );
    expect($pagi->refresh()->production_line_id)->toBe($this->lineA1->id);

    // Memindahkannya ke line yang jamnya kosong berhasil.
    $component
        ->set('form.production_line_id', $this->lineA3->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('successMessage', 'Shift berhasil diperbarui.');

    expect($pagi->refresh()->production_line_id)->toBe($this->lineA3->id);
});

// ── Scenario 19 ─────────────────────────────────────────────────────────

// Scenario 19: "menghapus shift membesarkan celah"
it('hapus satu shift: konfirmasi menyebut nama DAN line-nya, lalu cakupan berubah menjadi celah', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();
    $siang = Shift::factory()->forProductionLine($this->lineA1)->siang()->create();
    Shift::factory()->forProductionLine($this->lineA1)->malam()->create();

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id)
        ->set('productionLineId', $this->lineA1->id);

    expect(ksBadgeText($component->html(), $this->lineA1->id))->toBe('tercakup 24 jam');

    // Konfirmasi inline menyebut nama shift DAN line-nya — "Hapus 'Siang'?"
    // tanpa line-nya ambigu pada mill berbelasan line.
    $component
        ->call('askDelete', $siang->id)
        ->assertSet('confirmingDeleteId', $siang->id)
        ->assertSet('confirmingDeleteLabel', 'Siang pada Line 1')
        ->assertSeeHtml('data-testid="delete-confirm-label"')
        ->assertSee('Hapus shift Siang pada Line 1?')
        ->assertSeeHtml('data-testid="confirm-delete-button"');

    $component
        ->call('confirmDelete')
        ->assertSet('confirmingDeleteId', null)
        ->assertSet('successMessage', 'Shift Siang pada Line 1 berhasil dihapus.');

    expect(Shift::find($siang->id))->toBeNull();

    $entry = collect($component->viewData('coverage'))->firstWhere('production_line_id', $this->lineA1->id);

    expect($entry['state'])->toBe('gap');
    expect($entry['uncovered_minutes'])->toBe(480);

    $gaps = collect($entry['blocks'])->where('kind', 'gap')->values();
    expect($gaps)->toHaveCount(1);
    // Tepat di posisi yang ditinggalkan Siang.
    expect($gaps[0]['start_offset_minutes'])->toBe(480);

    $html = $component->html();
    expect(ksBadgeText($html, $this->lineA1->id))->toBe('ada celah 8 jam');
    // Blok celah dirender dengan kelas yang BERARSIRAN.
    expect($html)->toContain('data-testid="timeline-gap-'.$this->lineA1->id.'-480"');
    expect($html)->toContain('ks-block-gap');
});

it('membatalkan konfirmasi hapus tidak menghapus apa pun', function () {
    $pagi = Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id)
        ->call('askDelete', $pagi->id)
        ->assertSet('confirmingDeleteId', $pagi->id)
        ->call('cancelDelete')
        ->assertSet('confirmingDeleteId', null)
        ->assertSet('confirmingDeleteLabel', null);

    expect(Shift::count())->toBe(1);
});

// ── Scenario 20 ─────────────────────────────────────────────────────────

// Scenario 20: "menghapus seluruh shift sebuah line"
it('hapus shift terakhir: badge berbunyi belum diatur, BUKAN ada celah 24 jam', function () {
    $only = Shift::factory()->forProductionLine($this->lineA2)->pagi()->create();

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id)
        ->set('productionLineId', $this->lineA2->id)
        ->call('askDelete', $only->id)
        ->call('confirmDelete');

    $entry = collect($component->viewData('coverage'))->firstWhere('production_line_id', $this->lineA2->id);

    expect($entry['state'])->toBe('unconfigured');
    expect($entry['shift_count'])->toBe(0);
    expect($entry['uncovered_minutes'])->toBe(0);

    $html = $component->html();

    expect(ksBadgeText($html, $this->lineA2->id))->toBe('belum diatur');
    expect(ksBadgeText($html, $this->lineA2->id))->not->toBe('ada celah 24 jam');
    // Bloknya polos: .ks-block-empty hadir, nol .ks-block-gap pada batang ini.
    expect($html)->toContain('data-testid="timeline-empty-'.$this->lineA2->id.'"');
    expect(ksOrderedTestIds($html, 'timeline-gap-'))->toBe([]);
});

// ── Scenario 22 (dan unit_test_cases 39) ────────────────────────────────

// Scenario 22 / case 39: "mengganti mill mengosongkan filter line"
it('mengganti mill: filter line dikosongkan dan daftar line dimuat ulang untuk mill baru', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();
    Shift::factory()->forProductionLine($this->lineB1)->siang()->create();

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id)
        ->set('productionLineId', $this->lineA1->id);

    // Timeline mill A tampil.
    expect($component->viewData('coverage'))->toHaveCount(1);
    expect(array_column($component->viewData('lineOptions'), 'name'))->toBe(['Line 1', 'Line 2', 'Line 3']);

    $component
        ->set('businessUnitId', $this->millB->id)
        // Sebuah line milik TEPAT SATU mill, jadi membawanya ke mill lain akan
        // menampilkan galat untuk sesuatu yang tidak dilakukan Admin.
        ->assertSet('productionLineId', '')
        ->assertSet('page', 1)
        ->assertSet('showForm', false)
        ->assertHasNoErrors();

    // Daftar line dimuat ulang untuk mill B dan tidak memuat satu pun line
    // mill A.
    expect(array_column($component->viewData('lineOptions'), 'name'))->toBe(['Line B1']);
    expect(collect($component->viewData('lineOptions'))->pluck('id')->all())
        ->not->toContain($this->lineA1->id);

    expect(collect($component->viewData('coverage'))->pluck('production_line_id')->all())
        ->toBe([$this->lineB1->id]);
});

/**
 * The stale filter that arrives via a bookmarked URL — a line id belonging to
 * another mill. It is returned to "semua line" WITHOUT an error message:
 * the Admin did nothing wrong, they changed mill.
 */
it('filter line basi dari URL: dikembalikan ke semua line tanpa pesan galat', function () {
    Shift::factory()->forProductionLine($this->lineB1)->pagi()->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaShift::class, ['businessUnitId' => $this->millB->id])
        ->set('businessUnitId', $this->millB->id)
        // Line milik mill A pada mill B.
        ->set('productionLineId', $this->lineA1->id)
        ->assertSet('productionLineId', '')
        ->assertHasNoErrors()
        ->assertSet('formErrorMessage', null)
        ->assertSet('deleteErrorMessage', null);
});

// ── Scenario 23 ─────────────────────────────────────────────────────────

/**
 * Scenario 23: "Admin mencari pengaturan jendela sinkronisasi". NOL field
 * jendela/sync/sinkronisasi di form shift — dan keterangan yang menyatakan di
 * MANA ia diatur, supaya Admin tidak menyimpulkan fiturnya belum ada.
 */
it('nol field sinkronisasi pada form, dan keterangan tempat ia diatur dirender', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    $html = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id)
        ->call('openCreateForm')
        ->html();

    // Seluruh DOM layar dan form ditelusuri: tidak ada field bernama,
    // ber-id, atau terikat sync/sinkron/jendela.
    preg_match_all('/<(?:input|select|textarea)[^>]*>/', $html, $fields);

    expect($fields[0])->not->toBe([]);

    foreach ($fields[0] as $field) {
        foreach (['sync', 'sinkron', 'jendela', 'window', 'interval'] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                strtolower($field),
                "form memuat field sinkronisasi: $field"
            );
        }
    }

    // Keterangannya ada, DAN ia menyebut di mana setelan itu diatur.
    expect($html)->toContain('data-testid="sync-window-note"');
    $this->assertStringContainsString('Jarak waktu sinkronisasi tidak diatur di layar ini', $html);
    $this->assertStringContainsString('Mills Setting', $html);
});

// ── Scenario 24 ─────────────────────────────────────────────────────────

// Scenario 24: "tabel berpindah halaman sementara timeline tidak"
it('berpindah halaman: baris tabel berganti sementara timeline tetap seluruh line', function () {
    foreach ([$this->lineA1, $this->lineA2, $this->lineA3] as $line) {
        Shift::factory()->forProductionLine($line)->pagi()->create();
        Shift::factory()->forProductionLine($line)->siang()->create();
        Shift::factory()->forProductionLine($line)->malam()->create();
    }

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('perPage', 4)
        ->set('businessUnitId', $this->millA->id);

    $page1Rows = ksOrderedTestIds($component->html(), 'shift-row-');
    $page1Coverage = $component->viewData('coverage');

    expect($page1Rows)->toHaveCount(4);
    expect($page1Coverage)->toHaveCount(3);

    $component->call('nextPage')->assertSet('page', 2);

    $page2Rows = ksOrderedTestIds($component->html(), 'shift-row-');
    $page2Coverage = $component->viewData('coverage');

    // Baris tabelnya BERGANTI…
    expect($page2Rows)->toHaveCount(4);
    expect($page2Rows)->not->toBe($page1Rows);
    expect(array_intersect($page1Rows, $page2Rows))->toBe([]);
    expect($component->viewData('meta')['total'])->toBe(9);

    // …sementara timeline-nya tidak: ia merangkum cakupan line, bukan
    // mendaftar baris.
    expect($page2Coverage)->toBe($page1Coverage);
    expect(ksOrderedTestIds($component->html(), 'timeline-track-'))->toHaveCount(3);

    $component->call('previousPage')->assertSet('page', 1);
    expect(ksOrderedTestIds($component->html(), 'shift-row-'))->toBe($page1Rows);

    // previousPage() tidak pernah turun di bawah halaman 1.
    $component->call('previousPage')->assertSet('page', 1);
});

// ── Scenario 25 (dan unit_test_cases 40) ────────────────────────────────

/**
 * Scenario 25 / case 40: urutan baris tabel dan urutan blok timeline
 * IDENTIK, dan keduanya diturunkan dari satu nilai `position` yang sama.
 * Blade tidak mengurutkan apa pun sendiri — dua pengurutan yang berdiri
 * sendiri adalah cara tercepat membuat kedua tampilan berselisih.
 */
it('urutan baris tabel dan urutan blok timeline identik, diturunkan dari satu position', function () {
    // Urutan penambahan SENGAJA acak, dan abjad namanya pun tidak selaras
    // dengan urutan jamnya.
    $malam = Shift::factory()->forProductionLine($this->lineA1)->malam()->create();
    $pagi = Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();
    $siang = Shift::factory()->forProductionLine($this->lineA1)->siang()->create();

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id)
        ->set('productionLineId', $this->lineA1->id);

    $rows = collect($component->viewData('shifts'));

    expect($rows->pluck('position', 'name')->all())->toBe(['Pagi' => 0, 'Siang' => 1, 'Malam' => 2]);

    $html = $component->html();

    $expected = [$pagi->id, $siang->id, $malam->id];

    // DIBACA DARI DOM, keduanya, lalu dibandingkan.
    expect(ksOrderedTestIds($html, 'shift-row-'))->toBe($expected);
    expect(ksOrderedTestIds($html, 'timeline-block-'))->toBe($expected);

    // Bukan urutan penambahan (Malam dulu) dan bukan abjad nama.
    expect(ksOrderedTestIds($html, 'shift-row-'))->not->toBe([$malam->id, $pagi->id, $siang->id]);
});

/**
 * Case 40 (the other half) — BLADE TIDAK MENGURUTKAN DAN TIDAK BERHITUNG JAM.
 * The behavioural half is the test above; this one names the functions that
 * must not appear, because a second sort or a second duration calculation
 * would agree with the server today and drift tomorrow, and the drift would
 * look plausible.
 */
it('blade tidak memanggil pengurutan maupun aritmetika jam apa pun', function () {
    $blade = file_get_contents(resource_path('views/livewire/master-data/kelola-shift.blade.php'));

    expect($blade)->toBeString();

    // Pengurutan di blade.
    foreach (['sortBy(', 'sortByDesc(', 'usort(', '->sort(', 'ksort(', 'asort('] as $forbidden) {
        $this->assertStringNotContainsString(
            $forbidden,
            $blade,
            "blade mengurutkan sendiri lewat $forbidden — urutan wajib dibaca dari respons"
        );
    }

    // Aritmetika jam di blade. $pct() — menit menjadi persen — adalah
    // SATU-SATUNYA aritmetika yang diizinkan, dan pembaginya 14,4.
    foreach (['strtotime(', 'Carbon::', 'diffInMinutes(', 'date_diff(', 'mktime('] as $forbidden) {
        $this->assertStringNotContainsString(
            $forbidden,
            $blade,
            "blade menghitung jam sendiri lewat $forbidden"
        );
    }

    $this->assertStringContainsString('/ 14.4', $blade, '$pct() tidak lagi memetakan menit ke persen');
});

// ── Scenario 26 (dan unit_test_cases 44, 45) ────────────────────────────

/**
 * Scenario 26 / cases 44 + 45: KETIGA STATUS TERBACA DARI KATANYA, dan jumlah
 * jam celah DISEBUT ANGKANYA. Asersinya menyasar TEKS BADGE-nya, bukan kelas
 * warnanya dan bukan pemindaian kontainer — 'belum diatur' muncul juga di
 * kalimat penjelas dan di baris catatan tabel, jadi pemindaian kontainer akan
 * cocok di tempat yang salah.
 */
it('ketiga status cakupan terbaca dari kata pada badge-nya, lengkap dengan angka jam celah', function () {
    // Line 1 tercakup penuh.
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();
    Shift::factory()->forProductionLine($this->lineA1)->siang()->create();
    Shift::factory()->forProductionLine($this->lineA1)->malam()->create();

    // Line 2 bercelah 4 jam (15:00–19:00).
    Shift::factory()->forProductionLine($this->lineA2)->pagi()->create();
    Shift::factory()->forProductionLine($this->lineA2)->named('Malam')->range('19:00:00', '07:00:00')->create();

    // Line 3 belum diatur.

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id);

    $html = $component->html();

    expect(ksBadgeText($html, $this->lineA1->id))->toBe('tercakup 24 jam');
    expect(ksBadgeText($html, $this->lineA2->id))->toBe('ada celah 4 jam');
    expect(ksBadgeText($html, $this->lineA3->id))->toBe('belum diatur');

    // Case 45: angka jamnya disebut, bukan hanya 'ada celah'.
    expect(ksBadgeText($html, $this->lineA2->id))->not->toBe('ada celah');
    $this->assertStringContainsString('4', ksBadgeText($html, $this->lineA2->id));

    // Dan 'belum diatur' MEMANG muncul di tempat lain juga — itulah alasan
    // asersi di atas menyasar elemennya. Kalau tidak, pemindaian kontainer
    // akan cocok di sini.
    expect(substr_count($html, 'belum diatur'))->toBeGreaterThan(1);

    // Status tidak bergantung warna: kata-katanya cukup. Kelas warnanya ada
    // sebagai PENGUAT, bukan sebagai satu-satunya pembawa makna.
    expect($html)->toContain('badge-success');
    expect($html)->toContain('badge-warning');
    expect($html)->toContain('badge-neutral');

    // Setengah jam ditulis dengan koma desimal Indonesia, bukan titik.
    Shift::where('production_line_id', $this->lineA2->id)->where('name', 'Malam')->firstOrFail()
        ->update(['start_time' => '19:30:00']);

    $refreshed = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id)
        ->html();

    expect(ksBadgeText($refreshed, $this->lineA2->id))->toBe('ada celah 4,5 jam');
});

// ── Scenario 27 (bagian yang terukur tanpa peramban) ────────────────────

/**
 * Scenario 27: "layar dibuka pada lebar sempit" — label jam DIJARANGKAN,
 * bukan diperkecil, dan nol teks di bawah 14px. Lebar sesungguhnya hanya
 * dapat diukur di peramban (e2e-web/tests/kelola-shift.spec.ts); yang dapat
 * dibuktikan di sini adalah bahwa halaman ini TIDAK PERNAH menuliskan ukuran
 * font literal — seluruhnya token, dan --font-size-sm adalah 14px — dan bahwa
 * penjarangannya memang display:none, bukan pengecilan.
 */
it('nol ukuran font literal pada halaman, dan label jam dijarangkan bukan diperkecil', function () {
    $html = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id)
        ->html();

    // Setiap deklarasi font-size pada <style> halaman ini WAJIB memakai
    // token. Satu literal px/rem/em di sini adalah satu tempat yang dapat
    // jatuh di bawah 14px tanpa terlihat.
    preg_match_all('/font-size:\s*([^;}]+)/', $html, $declarations);

    expect($declarations[1])->not->toBe([]);

    foreach ($declarations[1] as $value) {
        $this->assertStringContainsString(
            'var(--font-size-',
            $value,
            "ukuran font literal ditemukan: font-size: $value"
        );
    }

    // Penjarangan: label sekunder DISEMBUNYIKAN pada lebar sempit.
    $this->assertStringContainsString('.ks-axis-tick--secondary { display: none; }', $html);
    $this->assertStringContainsString('@media (max-width: 767px)', $html);

    // Kartu-nya yang menggulir mendatar, bukan halamannya.
    $this->assertStringContainsString('.ks-scroll { overflow-x: auto; }', $html);
});

/**
 * TIAP KELAS DI MARKUP WAJIB PUNYA DEFINISI, dan diperiksa pada HASIL
 * RENDER — bukan pada sumbernya. Di kelola-machinery.blade.php lima kelas
 * tanpa definisi lolos 25 test Livewire dan 19 spec browser, karena test
 * menguji perilaku sementara yang rusak adalah tampilan.
 */
it('setiap kelas ks- yang dipakai markup punya definisi pada CSS hasil render', function () {
    $html = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id)
        ->call('openCreateForm')
        ->html();

    // Kelas yang dipakai markup (atribut class="…"), termasuk yang hanya
    // muncul di dalam form dan pratinjau.
    preg_match_all('/class="([^"]*)"/', $html, $classAttributes);

    $used = collect($classAttributes[1])
        ->flatMap(fn (string $value) => preg_split('/\s+/', trim($value)) ?: [])
        ->filter(fn (string $class) => str_starts_with($class, 'ks-'))
        ->unique()
        ->values();

    expect($used)->not->toHaveCount(0);

    // Definisi yang dideklarasikan <style> halaman ini.
    preg_match_all('/\.(ks-[A-Za-z0-9_-]+)/', $html, $definitions);
    $defined = collect($definitions[1])->unique()->values()->all();

    foreach ($used as $class) {
        expect($defined)->toContain($class);
    }
});

// ── Scenario 28 (dan unit_test_cases 3) ─────────────────────────────────

/**
 * Scenario 28 / case 3: HANYA ADMIN PUNYA LAYAR INI, dan dua lapis menjaganya
 * di jalur web.
 *
 * Lapis pertama: routes/web.php ('auth' + 'role:admin') — EnsureRole aborts
 * 403 sebelum komponen mount. Lapis kedua: abort_unless() di
 * KelolaShift::mount() sendiri, yang diuji dengan MEMASANG komponennya
 * langsung — berbeda dari screen-128, tempat komponennya tidak punya
 * penjagaan sendiri sehingga menguji mount() akan mengasersi penjagaan yang
 * bukan miliknya.
 */
it('non-Admin: ditolak oleh rute DAN oleh mount() komponennya sendiri, dengan nol kueri shift', function (string $role) {
    $user = match ($role) {
        'supervisor' => $this->supervisor,
        'mill_management' => $this->millManagement,
        'operator' => $this->operator,
    };

    Shift::factory()->forProductionLine($this->lineA1)->named('Shift Rahasia')->create();

    // Lapis pertama — rute.
    $response = $this->actingAs($user, 'web')->get('/master-data/shifts');

    $response->assertForbidden();
    $response->assertDontSee('Shift Rahasia');

    // Lapis kedua — mount() komponennya, DIPASANG LANGSUNG, dan nol kueri
    // shift. Livewire menangkap HttpException yang dilempar abort_unless()
    // dan merender respons galatnya alih-alih melemparkannya ke pemanggil,
    // jadi yang diasersi adalah HALAMAN 403 itu — bukan sebuah exception.
    $html = null;

    $queries = ksShiftQueriesDuring(function () use ($user, &$html) {
        $html = Livewire::actingAs($user)
            ->test(KelolaShift::class, ['businessUnitId' => $this->millA->id])
            ->html();
    });

    $this->assertStringContainsString(
        'Akses Ditolak',
        $html,
        "mount() seharusnya menolak peran $role dengan halaman 403"
    );
    $this->assertStringContainsString('403', $html, "halaman 403 tidak dirender untuk peran $role");
    // Layarnya sendiri tidak pernah dirender: nol akar halaman, nol pemilih
    // Mill, nol nama shift.
    $this->assertStringNotContainsString('class="ks-page"', $html, "layar dirender untuk peran $role");
    $this->assertStringNotContainsString('data-testid="mill-select"', $html, "pemilih Mill dirender untuk peran $role");
    $this->assertStringNotContainsString('Shift Rahasia', $html, "data shift terlihat oleh peran $role");

    expect($queries)->toBe([], "peran $role menjalankan kueri shift");
})->with([
    'supervisor' => ['supervisor'],
    'mill management' => ['mill_management'],
    'operator' => ['operator'],
]);

it('Admin dapat membuka /master-data/shifts', function () {
    $this->actingAs($this->admin, 'web')
        ->get('/master-data/shifts')
        ->assertOk()
        ->assertSee('Kelola Shift')
        ->assertSeeHtml('data-testid="mill-select"');
});

// ── unit_test_cases 38 ──────────────────────────────────────────────────

/**
 * Case 38 — KOMPONEN DAN CONTROLLER MEMAKAI SERVICE YANG SAMA, dan cakupannya
 * IDENTIK. Cakupan tidak dihitung ulang di blade: perhitungan yang sama akan
 * dibutuhkan gerbang sinkronisasi dan penandaan shift pada record, dan tiga
 * penghitung yang berdiri sendiri akan berselisih. Sisi API dari klaim yang
 * sama ada di tests/Feature/Api/KelolaShiftTest.php.
 */
it('komponen menerbitkan cakupan yang identik dengan controller API dan dengan service', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();
    Shift::factory()->forProductionLine($this->lineA1)->malam()->create();
    Shift::factory()->forProductionLine($this->lineA2)->siang()->create();

    $fromComponent = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id)
        ->viewData('coverage');

    $fromApi = $this->actingAs($this->admin, 'web')
        ->getJson("/api/shifts?business_unit_id={$this->millA->id}")
        ->assertOk()
        ->json('coverage');

    $this->actingAs($this->admin, 'web');
    $fromService = app(ShiftService::class)->listShifts($this->millA->id, null, 1, 20)['coverage'];

    expect($fromComponent)->toBe($fromService);
    expect($fromApi)->toBe($fromService);
});

// ── Ketahanan jalur gagal ───────────────────────────────────────────────

it('menampilkan pesan ketika shift yang akan diubah atau dihapus sudah hilang', function () {
    $pagi = Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id)
        ->call('askDelete', $pagi->id);

    // Pengguna lain menghapusnya lebih dulu.
    Shift::whereKey($pagi->id)->delete();

    $component
        ->call('confirmDelete')
        ->assertSet('confirmingDeleteId', null)
        ->assertSet('deleteErrorMessage', 'Shift tidak ditemukan, mungkin sudah dihapus.')
        ->assertSeeHtml('data-testid="delete-error"');

    Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id)
        ->call('openEditForm', $pagi->id)
        ->assertSet('showForm', false)
        ->assertSet('deleteErrorMessage', 'Shift tidak ditemukan, mungkin sudah dihapus.');
});

it('form tambah memakai line yang sedang difilter sebagai nilai awal', function () {
    Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id)
        ->set('productionLineId', $this->lineA2->id)
        ->call('openCreateForm')
        // Admin yang baru melihat satu line hampir selalu menambah shift di
        // line itu.
        ->assertSet('form.production_line_id', $this->lineA2->id)
        ->assertSet('form.name', '')
        ->assertSet('form.start_time', '')
        ->assertSet('form.end_time', '');
});

it('menutup form mengosongkan isian dan pesan galatnya', function () {
    Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id)
        ->call('openCreateForm')
        ->set('form.production_line_id', $this->lineA1->id)
        ->set('form.name', 'Pagi')
        ->set('form.start_time', '08:00')
        ->set('form.end_time', '12:00')
        ->call('save')
        ->assertSet('showForm', true)
        ->call('closeForm')
        ->assertSet('showForm', false)
        ->assertSet('editingId', null)
        ->assertSet('form.name', '')
        ->assertSet('formErrorMessage', null);
});

// ── Soft delete — scenarios 29–33 ───────────────────────────────────────
//
// SELURUH BAGIAN INI MENUTUP BARIS LEWAT ALUR LAYARNYA (askDelete →
// confirmDelete), bukan lewat factory ->closed(): yang diuji di sini adalah
// apa yang DILIHAT Admin sesudah menghapus. Varian yang menanam baris
// tertutup langsung ada di tests/Unit/Services/ShiftServiceTest.php.

// Scenario 29
it('nama shift yang baru dihapus bebas dipakai ulang lewat form pada line yang sama', function () {
    $pagi = Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id)
        ->set('productionLineId', $this->lineA1->id)
        ->call('askDelete', $pagi->id)
        ->call('confirmDelete');

    // Jamnya sengaja BERBEDA, supaya yang terbukti benar-benar keunikan nama
    // dan bukan tumpang tindih. Tanpa pengecualian baris tertutup pada
    // Rule::unique() DAN pada partial unique index, 'Pagi' penggantinya akan
    // terhalang oleh 'Pagi' yang sudah tidak terlihat di mana pun.
    ksFillAndSave($component, $this->lineA1, 'Pagi', '15:00', '23:00')
        ->assertHasNoErrors()
        ->assertSet('showForm', false);

    expect(Shift::where('name', 'Pagi')->count())->toBe(1);
    expect(Shift::withTrashed()->where('name', 'Pagi')->count())->toBe(2);
});

// Scenario 31
it('jam shift yang baru dihapus bebas dipakai ulang lewat form pada line yang sama', function () {
    $pagi = Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id)
        ->set('productionLineId', $this->lineA1->id)
        ->call('askDelete', $pagi->id)
        ->call('confirmDelete');

    // Bila baris tertutup ikut himpunan pembanding tumpang tindih, simpan ini
    // ditolak dengan menyebut shift yang sudah tidak ada — dan jam yang baru
    // dikosongkan tidak akan pernah bisa dipakai ulang penggantinya.
    ksFillAndSave($component, $this->lineA1, 'Pagi Baru', '07:00', '15:00')
        ->assertHasNoErrors()
        ->assertSet('formErrorMessage', null)
        ->assertSet('showForm', false);

    $entry = collect($component->viewData('coverage'))->firstWhere('production_line_id', $this->lineA1->id);

    expect($entry['shift_count'])->toBe(1);
    expect($entry['blocks'][0]['label'])->toBe('Pagi Baru');
    expect($entry['blocks'][0]['start_offset_minutes'])->toBe(0);
});

// Scenario 33
it('shift yang dihapus hilang dari tabel, dari blok timeline, dan dari pratinjau form', function () {
    $pagi = Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();
    $siang = Shift::factory()->forProductionLine($this->lineA1)->siang()->create();
    $malam = Shift::factory()->forProductionLine($this->lineA1)->malam()->create();

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id)
        ->set('productionLineId', $this->lineA1->id)
        ->call('askDelete', $siang->id)
        ->call('confirmDelete');

    $html = $component->html();

    // Tabel: barisnya hilang, kedua tetangganya tetap.
    expect(ksOrderedTestIds($html, 'shift-row-'))->toBe([$pagi->id, $malam->id]);

    // Timeline: tidak ada blok untuk shift yang ditutup, dan celahnya
    // dihitung pada rentangnya seolah tidak ada shift di sana.
    expect(ksOrderedTestIds($html, 'timeline-block-'))->toBe([$pagi->id, $malam->id]);
    expect($html)->toContain('data-testid="timeline-gap-'.$this->lineA1->id.'-480"');
    expect(ksBadgeText($html, $this->lineA1->id))->toBe('ada celah 8 jam');

    // Pratinjau pada form (shiftBlocksByLine) — JALUR HIDUP yang paling mudah
    // terlupakan sesudah daftar dan cakupan, karena ia tidak terlihat sampai
    // form dibuka.
    $preview = $component->call('openCreateForm')->viewData('previewBlocks');

    expect(collect($preview[$this->lineA1->id])->pluck('shift_id')->all())
        ->toBe([$pagi->id, $malam->id]);
});

// Scenario 30
it('line yang seluruh shift-nya sudah dihapus: badge belum diatur, BUKAN ada celah 24 jam', function () {
    $pagi = Shift::factory()->forProductionLine($this->lineA2)->pagi()->create();
    $siang = Shift::factory()->forProductionLine($this->lineA2)->siang()->create();

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id)
        ->set('productionLineId', $this->lineA2->id)
        ->call('askDelete', $pagi->id)
        ->call('confirmDelete')
        ->call('askDelete', $siang->id)
        ->call('confirmDelete');

    $entry = collect($component->viewData('coverage'))->firstWhere('production_line_id', $this->lineA2->id);

    expect($entry['state'])->toBe('unconfigured');
    expect($entry['shift_count'])->toBe(0);
    expect($entry['uncovered_minutes'])->toBe(0);

    $html = $component->html();

    // Dibaca dari ELEMEN badge-nya: "belum diatur" juga muncul pada kalimat
    // penjelas di bawah batang dan pada baris catatan tabel, jadi pemindaian
    // kontainer akan cocok di tempat yang salah.
    expect(ksBadgeText($html, $this->lineA2->id))->toBe('belum diatur');
    expect($html)->toContain('data-testid="timeline-empty-'.$this->lineA2->id.'"');
    expect(ksOrderedTestIds($html, 'timeline-gap-'))->toBe([]);
    expect(ksOrderedTestIds($html, 'shift-row-'))->toBe([]);
});

/**
 * Scenario 32 — PENGUBAHAN MEMUTASI BARIS, dan itu disengaja
 * (business_logic 30): penggantian-saat-ubah dipicu oleh "ada record yang
 * merujuk shift ini", dan tidak ada satu kolom pun di sistem yang merujuk
 * shift hari ini. Test ini mengikat perilaku yang BENAR-BENAR ADA — nol baris
 * kedua, nol baris tertutup baru — supaya penggantian tidak dibangun lebih
 * awal terhadap cabang yang tidak pernah dilewati.
 */
it('mengubah jam lewat form memutasi baris yang sama: nol baris kedua pada tabel', function () {
    $pagi = Shift::factory()->forProductionLine($this->lineA1)->pagi()->create();

    $component = Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id)
        ->set('productionLineId', $this->lineA1->id)
        ->call('openEditForm', $pagi->id)
        ->set('form.end_time', '16:00')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('showForm', false);

    expect(Shift::withTrashed()->where('production_line_id', $this->lineA1->id)->count())->toBe(1);
    expect(Shift::onlyTrashed()->count())->toBe(0);

    // Baris YANG SAMA menampilkan jam yang baru.
    $html = $component->html();

    expect(ksOrderedTestIds($html, 'shift-row-'))->toBe([$pagi->id]);
    expect($pagi->refresh()->end_time)->toBe('16:00:00');

    $row = collect($component->viewData('shifts'))->firstWhere('id', $pagi->id);
    expect($row['end_time'])->toBe('16:00');
});

// unit_test_cases 52 — baris tertutup tidak terjangkau jalur tulis yang hidup.
it('menampilkan pesan ketika shift yang akan diubah atau dihapus sudah ditutup orang lain', function () {
    $pagi = Shift::factory()->forProductionLine($this->lineA1)->closed()->pagi()->create();

    Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id)
        ->call('askDelete', $pagi->id)
        ->assertSet('confirmingDeleteId', null)
        ->assertSet('deleteErrorMessage', 'Shift tidak ditemukan, mungkin sudah dihapus.')
        ->assertSeeHtml('data-testid="delete-error"');

    Livewire::actingAs($this->admin)
        ->test(KelolaShift::class)
        ->set('businessUnitId', $this->millA->id)
        ->call('openEditForm', $pagi->id)
        ->assertSet('showForm', false)
        ->assertSet('deleteErrorMessage', 'Shift tidak ditemukan, mungkin sudah dihapus.');

    // Barisnya tetap ada dan tetap tertutup — jalur yang hidup tidak
    // menyentuhnya sama sekali.
    expect(Shift::withTrashed()->findOrFail($pagi->id)->deleted_at)->not->toBeNull();
});
