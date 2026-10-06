<?php

/**
 * MasterDataTreeViewTest (Feature/Livewire) — screen-127--master-data-tree-view
 * "Struktur Mills" (REVAMP 2026-10-06) / usecase-127, -157, -158, -159.
 *
 * REWRITTEN for the revamp. Carries all 42 `component_test` bindings of the
 * screen's 46 test_scenarios (scenarios 7, 25, 36 and 46 are
 * `component_test: N/A` — their guard lives at the ROUTE layer, which
 * Livewire::test() cannot observe since it mounts the component directly
 * and bypasses route middleware), plus the 22 component-level
 * unit_test_cases of usecases 157 / 158 / 159.
 *
 * ── WHAT SURVIVED FROM THE PRE-REVAMP FILE ──────────────────────────────
 * Two scenarios had to survive with equivalent assertions intact, and do,
 * at the bottom of this file:
 *   - "bebas N+1 (jumlah query konstan)" — now strictly STRONGER than the
 *     pre-revamp version: it no longer asserts `< 15` queries for one
 *     fixture, it asserts the count is EQUAL between a small and a 4x
 *     larger hierarchy. A per-card query would show up as a difference.
 *   - "403 untuk non-Admin" (x3 roles) — unchanged in substance, except
 *     the absence assertion now names the screen's own `data-testid`s
 *     instead of a label phrase (the sidebar label changed to "Struktur
 *     Mills" in this revamp; a phrase assertion would have kept passing
 *     for the wrong reason).
 *
 * Two scenarios LOST THEIR SUBJECT and are replaced here by their nearest
 * equivalents on the new shape, named so a reader can see the lineage:
 *   - old "toggleNode() buka-tutup" -> "PENERUS toggleNode(): daftar line
 *     setiap kartu mill selalu terlihat ...". Expand/collapse is gone on
 *     purpose (it hides data that is already loaded), so the successor
 *     asserts the thing that replaced it: every line visible on the first
 *     render, and no expand/collapse control anywhere in the screen.
 *   - old "tautan node ber-filter induk" -> "PENERUS tautan node: tautan
 *     Kelola ... TANPA query param filter induk". Filtered navigation is
 *     gone because Corporate/Company/mill/line are managed on THIS page
 *     now; the successor asserts the links are plain, and that the four
 *     Kelola screens' #[Url] filter properties still exist (they were
 *     deliberately NOT removed).
 *
 * ── ASSERTION DISCIPLINE (lessons this project has already paid for) ────
 *  - Absence-of-UI is asserted over `data-testid` / class names, NEVER
 *    over a phrase: this page contains the word "paginasi" in its own help
 *    text and in two nav aria-labels, so `assertDontSee('paginasi')` would
 *    fail for a reason that has nothing to do with pagination controls.
 *  - The query layer is NEVER mocked for anything that reads data. Real
 *    rows, real SELECTs, RefreshDatabase. Services are mocked only where
 *    the case under test is literally "what does the component do with
 *    what the service threw / was handed".
 *  - UserFactory's `business_unit_id` default cascades a whole
 *    BusinessUnit -> Company -> Corporate chain into existence, and nearly
 *    every assertion here states an EXACT number. Every admin in this file
 *    is therefore created with `business_unit_id => null`.
 *  - Fixture codes are invented by these tests themselves or read back
 *    from the factories — the Phase-2 mock HTML's BU-A / CORP-SN / CMP-SN-1
 *    / PL-A-01 are made up and are NOT used as ground truth. Where a
 *    scenario's own text names 'BU-A'/'BU-B' those are this test's own
 *    rows, created here.
 *  - GD is not installed in this environment, so UploadedFile::fake()
 *    ->image() is unavailable; valid images come from the suite-wide
 *    fakeRealImage() helper (tests/Pest.php) and rejected ones from
 *    UploadedFile::fake()->create(), which produces a file that is NOT a
 *    real image — exactly what App\Rules\RealImage must reject.
 */

use App\Enums\UserRole;
use App\Exceptions\BusinessUnitHasStationsException;
use App\Exceptions\CompanyHasBusinessUnitsException;
use App\Exceptions\CorporateHasCompaniesException;
use App\Exceptions\ProductionLineHasStationsException;
use App\Livewire\MasterData\KelolaBusinessUnit;
use App\Livewire\MasterData\KelolaCompany;
use App\Livewire\MasterData\KelolaProductionLine;
use App\Livewire\MasterData\MasterDataTreeView;
use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Corporate;
use App\Models\Machinery;
use App\Models\MachineryGroup;
use App\Models\ProductionLine;
use App\Models\Station;
use App\Models\User;
use App\Models\WeighbridgeRecord;
use App\Services\BusinessUnitService;
use App\Services\CompanyService;
use App\Services\CorporateService;
use App\Services\ProductionLineService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);
});

// ═════════════════════════════════════════════════════════════════════════
// Helpers
// ═════════════════════════════════════════════════════════════════════════

function md127Corporate(string $name, ?string $code = null): Corporate
{
    return Corporate::factory()->create([
        'name' => $name,
        'corporate_code' => $code ?? 'CORP-'.strtoupper(substr(md5($name), 0, 6)),
        'email' => null,
        'website' => null,
    ]);
}

function md127Company(Corporate $corporate, string $name, ?string $code = null): Company
{
    return Company::factory()->create([
        'corporate_id' => $corporate->id,
        'name' => $name,
        'company_code' => $code ?? 'CMP-'.strtoupper(substr(md5($name), 0, 6)),
    ]);
}

function md127Mill(Company $company, string $name, string $code, ?string $logo = null): BusinessUnit
{
    return BusinessUnit::factory()->create([
        'company_id' => $company->id,
        'name' => $name,
        'code' => $code,
        'logo' => $logo,
    ]);
}

function md127Line(BusinessUnit $mill, string $name, ?string $code = null): ProductionLine
{
    return ProductionLine::factory()->forBusinessUnit($mill)->create([
        'name' => $name,
        'code' => $code,
    ]);
}

/**
 * The smallest complete chain: one Corporate > one Company > one mill.
 *
 * @return array{corporate: Corporate, company: Company, mill: BusinessUnit}
 */
function md127Chain(string $suffix = 'Utama'): array
{
    $corporate = md127Corporate("Corp {$suffix}");
    $company = md127Company($corporate, "Company {$suffix}");
    $mill = md127Mill($company, "Mill {$suffix}", 'BU-'.strtoupper(substr(md5($suffix), 0, 6)));

    return ['corporate' => $corporate, 'company' => $company, 'mill' => $mill];
}

/** How many times a `data-testid` appears in rendered markup. */
function md127Occurrences(string $html, string $testid): int
{
    return substr_count($html, 'data-testid="'.$testid.'"');
}

/** The number printed inside one tile of the counts bar. */
function md127CountShown(string $html, string $level): int
{
    expect($html)->toMatch('/data-testid="count-'.preg_quote($level, '/').'">\s*<span[^>]*>(\d+)</');

    preg_match('/data-testid="count-'.preg_quote($level, '/').'">\s*<span[^>]*>(\d+)</', $html, $matches);

    return (int) $matches[1];
}

/** All four counts-bar numbers, in hierarchy order. */
function md127CountsShown(string $html): array
{
    return [
        'corporate' => md127CountShown($html, 'corporate'),
        'company' => md127CountShown($html, 'company'),
        'business_unit' => md127CountShown($html, 'business-unit'),
        'production_line' => md127CountShown($html, 'production-line'),
    ];
}

/** Real row counts straight from the four hierarchy tables. */
function md127CountsInDatabase(): array
{
    return [
        'corporate' => Corporate::count(),
        'company' => Company::count(),
        'business_unit' => BusinessUnit::count(),
        'production_line' => ProductionLine::count(),
    ];
}

/** The `<article>` markup of one mill card, located by the mill's name. */
function md127Card(string $html, string $millName): string
{
    foreach (explode('<article class="sm-mill"', $html) as $index => $chunk) {
        if ($index === 0) {
            continue;
        }

        if (str_contains($chunk, 'data-testid="mill-name" >') || str_contains($chunk, 'data-testid="mill-name">'.$millName.'<')) {
            if (str_contains($chunk, 'data-testid="mill-name">'.$millName.'<')) {
                return $chunk;
            }
        }
    }

    throw new RuntimeException("Tidak ada kartu mill bernama \"{$millName}\" di markup.");
}

/** Whether a mill card exists at all (no exception, just a boolean). */
function md127HasCard(string $html, string $millName): bool
{
    return str_contains($html, 'data-testid="mill-name">'.$millName.'<');
}

/** The `<tr>` markup of one summary-list row, located by the entity name. */
function md127Row(string $html, string $name): string
{
    foreach (explode('<tr class="sm-table__row"', $html) as $index => $chunk) {
        if ($index === 0) {
            continue;
        }

        $row = explode('</tr>', $chunk)[0];

        if (str_contains($row, '>'.$name.'</span>')) {
            return $row;
        }
    }

    throw new RuntimeException("Tidak ada baris ringkas bernama \"{$name}\" di markup.");
}

/** The "N Production Line" chip value printed on one mill card. */
function md127LineCountOnCard(string $html, string $millName): int
{
    $card = md127Card($html, $millName);

    expect($card)->toMatch('/data-testid="mill-line-count">(\d+) Production Line</');
    preg_match('/data-testid="mill-line-count">(\d+) Production Line</', $card, $matches);

    return (int) $matches[1];
}

/** Count of queries issued by one callable. */
function md127QueryCount(callable $callback): int
{
    $count = 0;
    DB::listen(function () use (&$count) {
        $count++;
    });

    $callback();

    return $count;
}

/** Every locator that would represent a pagination control on this screen. */
const MD127_PAGINATION_TESTIDS = [
    'pagination',
    'pagination-corporate',
    'pagination-company',
    'mill-prev-page',
    'mill-next-page',
    'corporate-prev-page',
    'corporate-next-page',
    'company-prev-page',
    'company-next-page',
];

/** Every locator that would represent an expand/collapse control. */
const MD127_TOGGLE_TESTIDS = ['node-toggle', 'mill-toggle', 'tree-toggle'];

function md127AssertNoPaginationControl(string $html): void
{
    foreach (MD127_PAGINATION_TESTIDS as $testid) {
        expect(md127Occurrences($html, $testid))->toBe(0, "kontrol paginasi [{$testid}] seharusnya tidak dirender");
    }

    expect($html)->not->toContain('class="sm-pager"');
}

function md127AssertNoToggleControl(string $html): void
{
    foreach (MD127_TOGGLE_TESTIDS as $testid) {
        expect(md127Occurrences($html, $testid))->toBe(0);
    }

    // Scoped to the screen container by construction: Livewire::test()
    // renders the component only, without the app shell's hamburger button
    // or the chatbot widget — both of which carry aria-expanded on every
    // page and are what makes a page-wide [aria-expanded] count of 0
    // impossible in a browser.
    expect($html)->not->toContain('aria-expanded')
        ->and($html)->not->toContain('sm-node-toggle');
}

// ═════════════════════════════════════════════════════════════════════════
// usecase-127--master-data-tree-view — 11 component_test bindings
// ═════════════════════════════════════════════════════════════════════════

it('Lihat Seluruh Hierarki Master Data dalam Satu Halaman — success', function () {
    // 2 Corporate / 2 Company / 3 mill (satu berlogo) / 4 Production Line.
    $corpOne = md127Corporate('Corp Satu');
    $companyOne = md127Company($corpOne, 'Company Satu');
    $millA = md127Mill($companyOne, 'Mill Alpha', 'BU-ALPHA');
    $millB = md127Mill($companyOne, 'Mill Bravo', 'BU-BRAVO');

    $corpTwo = md127Corporate('Corp Dua');
    $companyTwo = md127Company($corpTwo, 'Company Dua');
    $millC = md127Mill($companyTwo, 'Mill Charlie', 'BU-CHARLIE', 'business-unit-logos/charlie.png');

    md127Line($millA, 'Line Alpha Satu');
    md127Line($millA, 'Line Alpha Dua');
    md127Line($millB, 'Line Bravo Satu');
    md127Line($millC, 'Line Charlie Satu');

    $before = md127CountsInDatabase();
    expect($before)->toBe(['corporate' => 2, 'company' => 2, 'business_unit' => 3, 'production_line' => 4]);

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class);
    $html = $component->html();

    // Baris ringkasan memuat keempat angka total.
    expect(md127CountsShown($html))->toBe($before);

    // Satu kartu per mill, lengkap dengan nama, kode, dan breadcrumb.
    expect(md127Occurrences($html, 'mill-card'))->toBe(3);
    foreach ([
        ['Mill Alpha', 'BU-ALPHA', 'Corp Satu › Company Satu'],
        ['Mill Bravo', 'BU-BRAVO', 'Corp Satu › Company Satu'],
        ['Mill Charlie', 'BU-CHARLIE', 'Corp Dua › Company Dua'],
    ] as [$name, $code, $crumb]) {
        $card = md127Card($html, $name);

        expect($card)->toContain('data-testid="mill-code">'.$code.'<')
            ->and($card)->toContain('data-testid="mill-crumb">'.$crumb.'<');
    }

    // SELURUH nama Production Line tiap mill ada di markup pada render
    // PERTAMA, tanpa satu pun aksi.
    foreach (['Line Alpha Satu', 'Line Alpha Dua', 'Line Bravo Satu', 'Line Charlie Satu'] as $lineName) {
        expect($html)->toContain('data-testid="line-name">'.$lineName.'<');
    }
    expect(md127LineCountOnCard($html, 'Mill Alpha'))->toBe(2);
    expect(md127LineCountOnCard($html, 'Mill Charlie'))->toBe(1);

    // The mill WITH a logo renders an <img>; the two without render the
    // initials placeholder.
    expect(md127Card($html, 'Mill Charlie'))->toContain('data-testid="mill-logo"')
        ->and(md127Card($html, 'Mill Alpha'))->toContain('data-testid="mill-logo-fallback"');

    // Kedua daftar ringkas terender.
    expect(md127Occurrences($html, 'corporate-row'))->toBe(2)
        ->and(md127Occurrences($html, 'company-row'))->toBe(2);

    // Keterangan batas hierarki + kedua tautannya.
    expect($html)->toContain('data-testid="hierarchy-boundary"')
        ->and($html)->toContain('data-testid="link-kelola-station"')
        ->and($html)->toContain('data-testid="link-kelola-machinery"')
        ->and($html)->toContain('href="'.route('master-data.stations').'"')
        ->and($html)->toContain('href="'.route('master-data.machinery').'"');

    // Tidak ada baris basis data yang berubah sesudah render — this screen
    // reads on render(), it must never write there.
    expect(md127CountsInDatabase())->toBe($before);
});

it('Lihat Seluruh Hierarki Master Data dalam Satu Halaman — Belum ada data master sama sekali', function () {
    // RefreshDatabase + an admin with no business unit = genuinely zero
    // rows at all four levels.
    expect(md127CountsInDatabase())->toBe([
        'corporate' => 0, 'company' => 0, 'business_unit' => 0, 'production_line' => 0,
    ]);

    $html = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)->html();

    // Baris ringkasan menampilkan 0/0/0/0 — bukan hilang.
    expect($html)->toContain('data-testid="counts-bar"');
    expect(md127CountsShown($html))->toBe([
        'corporate' => 0, 'company' => 0, 'business_unit' => 0, 'production_line' => 0,
    ]);

    // Ketiga blok kosong per bagian harus ada BERSAMAAN — tidak ada satu
    // keadaan kosong tunggal yang menggantikan seluruh halaman.
    expect($html)->toContain('data-testid="mill-empty"')
        ->and($html)->toContain('data-testid="corporate-empty"')
        ->and($html)->toContain('data-testid="company-empty"');

    // ...masing-masing beserta tombol tambahnya sendiri.
    expect($html)->toContain('data-testid="add-mill-button-empty"')
        ->and($html)->toContain('data-testid="add-corporate-button-empty"')
        ->and($html)->toContain('data-testid="add-company-button-empty"');

    // Dan ketiga bagiannya tetap ada (judul + tabel/papannya), bukan
    // digantikan satu pesan gabungan.
    expect(md127Occurrences($html, 'mill-card'))->toBe(0)
        ->and(md127Occurrences($html, 'corporate-row'))->toBe(0)
        ->and(md127Occurrences($html, 'company-row'))->toBe(0)
        ->and($html)->toContain('data-testid="hierarchy-boundary"');
});

it('Lihat Seluruh Hierarki Master Data dalam Satu Halaman — Mill tanpa Production Line', function () {
    $chain = md127Chain('Tanpa');
    $millWithout = $chain['mill'];
    $millWith = md127Mill($chain['company'], 'Mill Berline', 'BU-BERLINE');
    md127Line($millWith, 'Line Satu');
    md127Line($millWith, 'Line Dua');

    $html = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)->html();

    // Kartu mill tanpa line TETAP terender, lengkap.
    $emptyCard = md127Card($html, 'Mill Tanpa');
    expect($emptyCard)->toContain('data-testid="mill-code">'.$millWithout->code.'<')
        ->and($emptyCard)->toContain('data-testid="mill-crumb">Corp Tanpa › Company Tanpa<')
        ->and($emptyCard)->toContain('data-testid="mill-no-lines"')
        ->and(md127LineCountOnCard($html, 'Mill Tanpa'))->toBe(0);

    // Tombol tambah line-nya ada, dan aria-label-nya menyebut nama mill itu.
    expect($emptyCard)->toContain('data-testid="add-line-'.$millWithout->id.'"')
        ->and($emptyCard)->toContain('aria-label="Tambah Production Line pada Business Unit Mill Tanpa"');

    // Kartu mill yang punya line TIDAK memuat keterangan itu.
    expect(md127Card($html, 'Mill Berline'))->not->toContain('data-testid="mill-no-lines"');
    expect(md127Occurrences($html, 'mill-no-lines'))->toBe(1);
});

it('Lihat Seluruh Hierarki Master Data dalam Satu Halaman — Corporate atau Company tanpa anak', function () {
    $full = md127Chain('Lengkap');
    md127Line($full['mill'], 'Line Lengkap Satu');

    $corpNoCompany = md127Corporate('Corp Tanpa Company');
    $companyNoMill = md127Company($full['corporate'], 'Company Tanpa Mill');

    $html = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)->html();

    // Corporate tanpa Company: ada di daftar ringkas, angka 0 TERCETAK.
    $corpRow = md127Row($html, 'Corp Tanpa Company');
    expect($corpRow)->toContain('data-testid="corporate-company-count">0<')
        ->and($corpRow)->toContain('data-testid="edit-corporate-'.$corpNoCompany->id.'"')
        ->and($corpRow)->toContain('data-testid="delete-corporate-'.$corpNoCompany->id.'"');

    // Company tanpa mill: ada, dengan Corporate induknya dan angka 0.
    $companyRow = md127Row($html, 'Company Tanpa Mill');
    expect($companyRow)->toContain('data-testid="company-mill-count">0<')
        ->and($companyRow)->toContain('>Corp Lengkap</span>')
        ->and($companyRow)->toContain('data-testid="edit-company-'.$companyNoMill->id.'"')
        ->and($companyRow)->toContain('data-testid="delete-company-'.$companyNoMill->id.'"');

    // Namanya TIDAK muncul di breadcrumb kartu mana pun.
    foreach (explode('data-testid="mill-crumb">', $html) as $index => $chunk) {
        if ($index === 0) {
            continue;
        }

        $crumb = explode('<', $chunk)[0];
        expect($crumb)->not->toContain('Corp Tanpa Company')
            ->and($crumb)->not->toContain('Company Tanpa Mill');
    }

    // Satu-satunya kartu adalah mill dari hierarki yang lengkap.
    expect(md127Occurrences($html, 'mill-card'))->toBe(1)
        ->and(md127HasCard($html, 'Mill Lengkap'))->toBeTrue();
});

it('Lihat Seluruh Hierarki Master Data dalam Satu Halaman — Menyaring dengan kata kunci', function () {
    $corpA = md127Corporate('Corp Alfa');
    $companyA = md127Company($corpA, 'Company Alfa');
    $millA = md127Mill($companyA, 'Business Unit A', 'BU-A');
    md127Line($millA, 'Line Khusus Alfa');
    md127Line($millA, 'Line Biasa Alfa');

    $corpB = md127Corporate('Corp Beta');
    $companyB = md127Company($corpB, 'Company Beta');
    $millB = md127Mill($companyB, 'Business Unit B', 'BU-B');
    md127Line($millB, 'Line Beta Satu');

    $totals = md127CountsInDatabase();

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class);
    expect(md127Occurrences($component->html(), 'mill-card'))->toBe(2);

    // Disaring dengan KODE mill — dan diulang dengan huruf besar-kecil
    // berbeda: hasilnya harus identik (lower() + LIKE).
    foreach (['BU-A', 'bu-a', 'Bu-a'] as $keyword) {
        $component->set('search', $keyword);
        $html = $component->html();

        expect(md127Occurrences($html, 'mill-card'))->toBe(1)
            ->and(md127HasCard($html, 'Business Unit A'))->toBeTrue()
            ->and(md127HasCard($html, 'Business Unit B'))->toBeFalse();

        // Kedua daftar ringkas juga menyempit.
        expect(md127Occurrences($html, 'corporate-row'))->toBe(1)
            ->and(md127Occurrences($html, 'company-row'))->toBe(1)
            ->and($html)->toContain('>Corp Alfa</span>')
            ->and($html)->not->toContain('>Corp Beta</span>');

        // Baris ringkasan TETAP total seluruh data, dengan keterangan
        // jumlah yang cocok di sampingnya.
        expect(md127CountsShown($html))->toBe($totals)
            ->and($html)->toContain('data-testid="match-note"');
    }

    // Disaring dengan NAMA LINE: kartu mill pemiliknya tetap terender dan
    // line yang cocok ditandai.
    $component->set('search', 'Line Khusus Alfa');
    $html = $component->html();

    expect(md127Occurrences($html, 'mill-card'))->toBe(1)
        ->and(md127HasCard($html, 'Business Unit A'))->toBeTrue();

    $card = md127Card($html, 'Business Unit A');
    expect($card)->toContain('data-testid="line-name">Line Khusus Alfa<')
        // Line yang tidak cocok TETAP ada di kartu (kartu tidak boleh
        // berbohong tentang isi mill-nya), hanya saja tidak ditandai.
        ->and($card)->toContain('data-testid="line-name">Line Biasa Alfa<')
        ->and(md127Occurrences($card, 'line-match'))->toBe(1);
    expect(md127CountsShown($html))->toBe($totals);

    // Mengosongkan kotaknya memulihkan tampilan utuh.
    $component->set('search', '');
    $html = $component->html();

    expect(md127Occurrences($html, 'mill-card'))->toBe(2)
        ->and(md127Occurrences($html, 'corporate-row'))->toBe(2)
        ->and(md127Occurrences($html, 'company-row'))->toBe(2)
        ->and(md127Occurrences($html, 'match-note'))->toBe(0)
        ->and(md127Occurrences($html, 'line-match'))->toBe(0)
        ->and(md127CountsShown($html))->toBe($totals);
});

it('Lihat Seluruh Hierarki Master Data dalam Satu Halaman — Penyaring tidak mencocokkan apa pun', function () {
    $chain = md127Chain('Ada');
    md127Line($chain['mill'], 'Line Ada Satu');
    md127Mill($chain['company'], 'Mill Ada Dua', 'BU-ADA2');

    $totals = md127CountsInDatabase();

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class);
    $before = md127CountsShown($component->html());

    $component->set('search', 'zzz-tidak-ada-apa-pun');
    $html = $component->html();

    // Blok "tidak ada yang cocok" MENGUTIP kata kuncinya...
    expect($html)->toContain('data-testid="mill-no-match"')
        ->and($html)->toContain('tidak ada yang cocok dengan "zzz-tidak-ada-apa-pun"');

    // ...beserta kontrol mengosongkan penyaringnya.
    expect($html)->toContain('data-testid="clear-search-mill"');

    // Tidak satu pun kartu mill terender.
    expect(md127Occurrences($html, 'mill-card'))->toBe(0);

    // Baris ringkasan TETAP total seluruh data, tak berubah dibanding
    // render tanpa penyaring — yang kosong adalah hasil pencarian.
    expect(md127CountsShown($html))->toBe($before)
        ->and(md127CountsShown($html))->toBe($totals);

    // Mengklik kontrol pengosong memulihkan papannya.
    $component->call('clearSearch');
    expect($component->get('search'))->toBe('');
    expect(md127Occurrences($component->html(), 'mill-card'))->toBe(2);
});

it('Lihat Seluruh Hierarki Master Data dalam Satu Halaman — Nama atau kode sangat panjang', function () {
    $chain = md127Chain('Panjang');
    $longName = 'Mill '.str_repeat('Namapanjang ', 10);
    $longName = substr($longName.str_repeat('X', 120), 0, 120);
    expect(strlen($longName))->toBe(120);

    $longMill = md127Mill($chain['company'], $longName, 'BU-PANJANG');
    md127Mill($chain['company'], 'Mill Pendek', 'BU-PENDEK');

    $html = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)->html();

    // Nama 120 karakter itu ada UTUH di markup — pemotongan dilakukan CSS,
    // bukan PHP, jadi tidak ada bentuk terpotong untuk diuji.
    expect($html)->toContain('data-testid="mill-name">'.$longName.'<');

    // Elemen namanya membawa kelas pemotong dan atribut title berisi nilai
    // utuh yang SAMA.
    $card = md127Card($html, $longName);
    expect($card)->toContain('class="sm-mill__name sm-truncate" title="'.$longName.'" data-testid="mill-name">'.$longName.'<');

    // Tidak ada pemotongan hasil PHP (Str::limit) pada nilai itu.
    expect($html)->not->toContain(substr($longName, 0, 40).'...')
        ->and($html)->not->toContain(substr($longName, 0, 40).'…');
    expect($longMill->fresh()->name)->toBe($longName);

    // Dan kartu pendeknya memakai kelas pemotong yang sama — pemotongan
    // adalah properti papan, bukan tambalan untuk satu baris.
    expect(md127Card($html, 'Mill Pendek'))->toContain('class="sm-mill__name sm-truncate"');
});

it('Lihat Seluruh Hierarki Master Data dalam Satu Halaman — Mill tanpa logo', function () {
    $chain = md127Chain('Logo');
    $noLogo = md127Mill($chain['company'], 'Alpha Beta', 'BU-AB');
    $withLogo = md127Mill($chain['company'], 'Mill Berlogo', 'BU-BL', 'business-unit-logos/berlogo.png');

    $html = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)->html();

    // Penanda pengganti berisi inisial maksimal dua huruf.
    $noLogoCard = md127Card($html, 'Alpha Beta');
    expect($noLogoCard)->toMatch('/data-testid="mill-logo-fallback">\s*AB\s*</')
        // Markup kartu itu TIDAK memuat <img> sama sekali — bukan <img>
        // ber-src kosong, yang akan digambar sebagai ikon gambar rusak.
        ->and($noLogoCard)->not->toContain('<img')
        ->and($noLogoCard)->not->toContain('data-testid="mill-logo"');

    // Kartu mill berlogo tetap merender <img> dengan src terisi.
    $withLogoCard = md127Card($html, 'Mill Berlogo');
    expect($withLogoCard)->toContain('data-testid="mill-logo"')
        ->and($withLogoCard)->toContain('src="'.Storage::disk(BusinessUnitService::LOGO_DISK)->url($withLogo->logo).'"')
        ->and($withLogoCard)->not->toContain('src=""')
        ->and($withLogoCard)->not->toContain('data-testid="mill-logo-fallback"');

    expect($noLogo->fresh()->logo)->toBeNull();
});

it('Lihat Seluruh Hierarki Master Data dalam Satu Halaman — hierarki melewati batas Production Line', function () {
    $chain = md127Chain('Batas');
    $line = md127Line($chain['mill'], 'Line Batas Satu');
    $station = Station::factory()->forProductionLine($line)->create(['name' => 'Stasiun Rahasia Zulu']);
    $group = MachineryGroup::factory()->forStation($station)->create(['group_code' => 'MG-RAHASIA-YANKEE']);
    Machinery::factory()->create([
        'station_id' => $station->id,
        'production_line_id' => $line->id,
        'machinery_group_id' => $group->id,
        'name' => 'Mesin Rahasia Xray',
    ]);

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    $html = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)->html();

    // Tidak satu pun nama/kode Station, Machinery Group, maupun Machinery
    // ada di markup ter-render.
    expect($html)->not->toContain('Stasiun Rahasia Zulu')
        ->and($html)->not->toContain('MG-RAHASIA-YANKEE')
        ->and($html)->not->toContain('Mesin Rahasia Xray');

    // Keterangan batas hierarki menyebut ketiga tingkat itu dan membawa
    // tautan ke layar Kelola-nya.
    expect($html)->toContain('data-testid="hierarchy-boundary"');
    $note = explode('data-testid="hierarchy-boundary"', $html)[1];
    expect($note)->toContain('Station')
        ->and($note)->toContain('Machinery Group')
        ->and($note)->toContain('Machinery')
        ->and($note)->toContain('data-testid="link-kelola-station"')
        ->and($note)->toContain('data-testid="link-kelola-machinery"');

    // board() hanya menyentuh empat tabel hierarki — ketiga tabel di luar
    // batas tidak di-query sama sekali.
    expect($queries)->not->toBeEmpty();
    foreach ($queries as $sql) {
        expect($sql)->not->toContain('"stations"')
            ->and($sql)->not->toContain('"machinery_groups"')
            ->and($sql)->not->toContain('"machineries"');
    }
});

it('Lihat Seluruh Hierarki Master Data dalam Satu Halaman — paginasi muncul hanya ketika isinya melebihi satu halaman', function () {
    // ── (a) 5 mill, di bawah perPage 20 ─────────────────────────────────
    $chain = md127Chain('Paginasi');
    $mills = [$chain['mill']];
    foreach (range(2, 5) as $index) {
        $mills[] = md127Mill($chain['company'], "Mill Paginasi {$index}", "BU-PG-{$index}");
    }
    foreach ($mills as $mill) {
        md127Line($mill, 'Line '.$mill->code.' A');
        md127Line($mill, 'Line '.$mill->code.' B');
    }

    $small = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class);
    $html = $small->html();

    // Seluruh 5 kartu dan seluruh baris line ada di SATU render.
    expect(md127Occurrences($html, 'mill-card'))->toBe(5)
        ->and(md127Occurrences($html, 'line-row'))->toBe(10);

    // Dan TIDAK ada satu pun kontrol paginasi — diperiksa atas
    // kelas/testid, BUKAN atas frasa: halaman ini memuat kata "paginasi"
    // pada keterangannya dan pada aria-label nav paginasinya.
    md127AssertNoPaginationControl($html);
    md127AssertNoToggleControl($html);

    // ── (b) 34 mill dan 60 Production Line ──────────────────────────────
    $bulkChain = md127Chain('Massal');
    $bulk = [];
    foreach (range(1, 34) as $index) {
        $code = str_pad((string) $index, 3, '0', STR_PAD_LEFT);
        $bulk[] = md127Mill($bulkChain['company'], "Mill Massal {$code}", "BU-MS-{$code}");
    }
    // 60 lines over the 34 mills: the first 26 get two, the rest get one.
    foreach ($bulk as $position => $mill) {
        md127Line($mill, 'Line Massal '.$mill->code.' A');
        if ($position < 26) {
            md127Line($mill, 'Line Massal '.$mill->code.' B');
        }
    }

    // Only Massal mills must be on the board for the page arithmetic to be
    // readable — drop the five from part (a) and their lines.
    ProductionLine::whereIn('business_unit_id', collect($mills)->pluck('id'))->delete();
    BusinessUnit::whereIn('id', collect($mills)->pluck('id'))->delete();

    expect(BusinessUnit::count())->toBe(35) // 34 Massal + Mill Massal's own chain mill
        ->and(ProductionLine::count())->toBe(60);

    // The chain mill created by md127Chain('Massal') has no lines; remove
    // it so the board is exactly 34.
    BusinessUnit::whereKey($bulkChain['mill']->id)->delete();
    expect(BusinessUnit::count())->toBe(34);

    $totals = md127CountsInDatabase();

    $large = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class);
    $pageOne = $large->html();

    // Dengan 34 mill kontrol paginasi TERENDER; halaman 1 memuat 20 kartu.
    expect($pageOne)->toContain('data-testid="pagination"')
        ->and($pageOne)->toContain('data-testid="mill-next-page"')
        ->and($pageOne)->toContain('data-testid="mill-prev-page"')
        ->and(md127Occurrences($pageOne, 'mill-card'))->toBe(20);

    // Keterangan posisi halaman menyebut TOTAL 34, bukan 20.
    expect($pageOne)->toContain('menampilkan 1-20 dari 34 mill');

    // Baris ringkasan tetap menyatakan total seluruh data.
    expect(md127CountsShown($pageOne))->toBe($totals);

    // Halaman 2 memuat 14.
    $large->call('nextPage');
    $pageTwo = $large->html();

    expect($large->get('page'))->toBe(2)
        ->and(md127Occurrences($pageTwo, 'mill-card'))->toBe(14)
        ->and($pageTwo)->toContain('menampilkan 21-34 dari 34 mill')
        ->and(md127CountsShown($pageTwo))->toBe($totals);

    // SELURUH baris line tiap mill yang terlihat tetap utuh — line tidak
    // ikut dipaginasi — dan jumlah pada kartu berasal dari withCount, yaitu
    // jumlah SEBENARNYA.
    foreach ($bulk as $position => $mill) {
        if (! md127HasCard($pageTwo, $mill->name)) {
            continue;
        }

        $expectedLines = $position < 26 ? 2 : 1;
        $card = md127Card($pageTwo, $mill->name);

        expect(md127Occurrences($card, 'line-row'))->toBe($expectedLines)
            ->and(md127LineCountOnCard($pageTwo, $mill->name))->toBe($expectedLines)
            ->and((int) ProductionLine::where('business_unit_id', $mill->id)->count())->toBe($expectedLines);
    }

    // Sesudah set('search', ...) DARI HALAMAN 2, $page kembali ke 1 dan
    // hasilnya tidak kosong.
    $large->set('search', 'BU-MS-034');
    expect($large->get('page'))->toBe(1);
    $filtered = $large->html();
    expect(md127Occurrences($filtered, 'mill-card'))->toBe(1)
        ->and(md127HasCard($filtered, 'Mill Massal 034'))->toBeTrue()
        ->and(md127CountsShown($filtered))->toBe($totals);

    // Komponen TIDAK memakai trait WithPagination milik Livewire — ia
    // memakai $page/$perPage + nextPage()/previousPage() seperti keempat
    // layar Kelola. Satu pola, bukan pola kelima.
    expect(class_uses_recursive(MasterDataTreeView::class))
        ->not->toContain(Livewire\WithPagination::class);
    expect(method_exists(MasterDataTreeView::class, 'nextPage'))->toBeTrue()
        ->and(method_exists(MasterDataTreeView::class, 'previousPage'))->toBeTrue();
    expect($large->get('perPage'))->toBe(20);
});

it('Lihat Seluruh Hierarki Master Data dalam Satu Halaman — baris ringkasan jumlah hilang atau tidak jujur', function () {
    // 3 Corporate (satu tanpa Company), 4 Company (satu tanpa mill),
    // 5 mill, 8 line.
    $corpA = md127Corporate('Corp Jujur A');
    $corpB = md127Corporate('Corp Jujur B');
    md127Corporate('Corp Jujur C Tanpa Company');

    $companyA1 = md127Company($corpA, 'Company Jujur A1');
    $companyA2 = md127Company($corpA, 'Company Jujur A2');
    $companyB1 = md127Company($corpB, 'Company Jujur B1');
    md127Company($corpB, 'Company Jujur B2 Tanpa Mill');

    $mills = [
        md127Mill($companyA1, 'Mill Jujur 1', 'BU-JJ-1'),
        md127Mill($companyA1, 'Mill Jujur 2', 'BU-JJ-2'),
        md127Mill($companyA2, 'Mill Jujur 3', 'BU-JJ-3'),
        md127Mill($companyB1, 'Mill Jujur 4', 'BU-JJ-4'),
        md127Mill($companyB1, 'Mill Jujur 5', 'BU-JJ-5'),
    ];
    foreach ([3, 2, 2, 1, 0] as $position => $lineCount) {
        foreach (range(1, max($lineCount, 0)) as $index) {
            if ($lineCount === 0) {
                break;
            }
            md127Line($mills[$position], 'Line Jujur '.($position + 1).'-'.$index);
        }
    }

    $expected = ['corporate' => 3, 'company' => 4, 'business_unit' => 5, 'production_line' => 8];
    expect(md127CountsInDatabase())->toBe($expected);

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class);

    // Render pertama, tanpa penyaring.
    $unfiltered = $component->html();
    expect($unfiltered)->toContain('data-testid="counts-bar"')
        ->and(md127CountsShown($unfiltered))->toBe($expected);

    // Render kedua, dengan penyaring yang hanya mencocokkan SATU mill.
    $component->set('search', 'BU-JJ-3');
    $filtered = $component->html();

    expect($filtered)->toContain('data-testid="counts-bar"')
        ->and(md127Occurrences($filtered, 'mill-card'))->toBe(1)
        // Angka total TIDAK mengecil.
        ->and(md127CountsShown($filtered))->toBe($expected)
        // ...dan jumlah yang cocok muncul sebagai keterangan TERPISAH.
        ->and($filtered)->toContain('data-testid="match-note"');

    // Keempat angka itu sama dengan COUNT langsung atas keempat tabel,
    // termasuk Corporate dan Company yang belum punya anak.
    expect(md127CountsShown($filtered))->toBe(md127CountsInDatabase());
});

// ═════════════════════════════════════════════════════════════════════════
// usecase-157--tambah-entitas-hierarki-master-data — 13 bindings
// ═════════════════════════════════════════════════════════════════════════

it('Tambah Entitas Hierarki Master Data — berhasil', function () {
    $chain = md127Chain('Tambah');
    $companyId = $chain['company']->id;

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class);
    $millsBefore = md127CountShown($component->html(), 'business-unit');

    $component->call('openCreate', 'business-unit')
        ->set('form.company_id', $companyId)
        ->set('form.code', 'E2E-BU-NEW')
        ->set('form.name', 'Mill Baru')
        ->call('save');

    $component->assertHasNoErrors();

    // Satu baris business_units baru tersimpan dengan company_id, code,
    // dan name itu.
    $created = BusinessUnit::where('code', 'E2E-BU-NEW')->first();
    expect($created)->not->toBeNull()
        ->and($created->name)->toBe('Mill Baru')
        ->and($created->company_id)->toBe($companyId);

    // Modal tertutup, form kosong, logo null, successMessage terisi.
    $component->assertSet('modalLevel', null)
        ->assertSet('modalMode', null)
        ->assertSet('editingId', null)
        ->assertSet('form', [])
        ->assertSet('logo', null)
        ->assertSet('successMessage', 'Business Unit berhasil ditambahkan.');

    // Render sesudahnya memuat kartu 'Mill Baru' dan angka Mill bertambah
    // satu — dihitung ulang oleh render(), bukan ditambah manual.
    $after = $component->html();
    expect(md127HasCard($after, 'Mill Baru'))->toBeTrue()
        ->and(md127CountShown($after, 'business-unit'))->toBe($millsBefore + 1)
        ->and($after)->toContain('data-testid="success-message"');
});

it('Tambah Entitas Hierarki Master Data — Field wajib kosong', function () {
    // Spy: the service must never be reached when the component's own
    // mirror rules already refused.
    $this->mock(CorporateService::class, function ($mock) {
        $mock->shouldNotReceive('create');
        $mock->shouldNotReceive('update');
    });

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openCreate', 'corporate')
        ->set('form.corporate_code', 'E2E-CORP-01')
        ->call('save');

    $component->assertHasErrors('form.name');

    // Tidak ada baris corporates baru.
    expect(Corporate::count())->toBe(0);

    // Modal TETAP terbuka, isian lain utuh.
    $component->assertSet('modalLevel', 'corporate')
        ->assertSet('modalMode', 'create')
        ->assertSet('form.corporate_code', 'E2E-CORP-01')
        ->assertSet('successMessage', null);

    // Pesannya dirender di bawah field-nya, bukan sebagai toast halaman.
    $html = $component->html();
    expect($html)->toContain('data-testid="field-name"')
        ->and($html)->toContain('class="sm-field__error"');
});

it('Tambah Entitas Hierarki Master Data — Kode sudah dipakai', function () {
    $chain = md127Chain('Dipakai');
    md127Mill($chain['company'], 'Mill Pertama', 'BU-DIPAKAI');
    $millsBefore = BusinessUnit::count();

    // Kode beda huruf besar-kecil — menguji UniqueCaseInsensitive.
    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openCreate', 'business-unit')
        ->set('form.company_id', $chain['company']->id)
        ->set('form.code', 'bu-dipakai')
        ->set('form.name', 'Mill Kedua')
        ->call('save');

    $component->assertHasErrors('form.code');
    expect($component->errors()->get('form.code')[0])->toContain('sudah digunakan');

    expect(BusinessUnit::count())->toBe($millsBefore);
    $component->assertSet('modalLevel', 'business-unit')
        ->assertSet('form.name', 'Mill Kedua')
        ->assertSet('successMessage', null);
});

it('Tambah Entitas Hierarki Master Data — Kode Production Line dibiarkan kosong', function () {
    $chain = md127Chain('LineKosong');
    $millId = $chain['mill']->id;

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class);

    $component->call('openCreate', 'production-line', $millId)
        ->set('form.name', 'Line Tanpa Kode')
        ->call('save')
        ->assertHasNoErrors();

    // Dan sekali lagi dengan nama berbeda, kode tetap kosong.
    $component->call('openCreate', 'production-line', $millId)
        ->set('form.name', 'Line Tanpa Kode Dua')
        ->call('save')
        ->assertHasNoErrors();

    // '' dinormalkan menjadi null oleh ProductionLineService::emptyToNull(),
    // sehingga dua line tanpa kode TIDAK dianggap bentrok.
    expect(ProductionLine::count())->toBe(2)
        ->and(ProductionLine::whereNull('code')->count())->toBe(2)
        ->and(ProductionLine::where('code', '')->count())->toBe(0);

    // Kedua baris line terender di dalam kartu mill itu.
    $card = md127Card($component->html(), $chain['mill']->name);
    expect($card)->toContain('data-testid="line-name">Line Tanpa Kode<')
        ->and($card)->toContain('data-testid="line-name">Line Tanpa Kode Dua<')
        ->and(md127Occurrences($card, 'line-row'))->toBe(2);
});

it('Tambah Entitas Hierarki Master Data — Berkas logo ditolak', function () {
    // (a) berkas ber-ekstensi png tetapi BUKAN gambar sebenarnya —
    //     menguji App\Rules\RealImage.
    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openCreate', 'corporate')
        ->set('form.corporate_code', 'E2E-CORP-02')
        ->set('form.name', 'Corp Logo')
        ->set('logo', UploadedFile::fake()->create('bukan-gambar.png', 10, 'image/png'))
        ->call('save');

    // Kuncinya 'logo' TANPA awalan form.
    $component->assertHasErrors('logo');
    expect($component->errors()->has('form.logo'))->toBeFalse();

    expect(Corporate::count())->toBe(0);
    $component->assertSet('modalLevel', 'corporate')
        // Pekerjaan mengisi form tidak terbuang.
        ->assertSet('form.corporate_code', 'E2E-CORP-02')
        ->assertSet('form.name', 'Corp Logo')
        ->assertSet('successMessage', null);
    expect($component->html())->toContain('data-testid="logo-error"');

    // (b) berkas gambar SUNGGUHAN tetapi > 2MB.
    $oversize = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openCreate', 'corporate')
        ->set('form.corporate_code', 'E2E-CORP-03')
        ->set('form.name', 'Corp Logo Besar')
        ->set('logo', fakeRealImage('besar.jpg', 3000))
        ->call('save');

    $oversize->assertHasErrors('logo');
    expect(Corporate::count())->toBe(0);
    $oversize->assertSet('modalLevel', 'corporate')
        ->assertSet('form.name', 'Corp Logo Besar')
        ->assertSet('successMessage', null);
});

it('Tambah Entitas Hierarki Master Data — Induk diubah sebelum menyimpan', function () {
    $chain = md127Chain('Pindah');
    $millA = $chain['mill'];
    $millB = md127Mill($chain['company'], 'Mill Pindah B', 'BU-PDH-B');
    md127Line($millA, 'Line A Lama');

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openCreate', 'production-line', $millA->id)
        // Nilai induk dibaca dari $form saat save(), BUKAN dari parameter
        // openCreate() — itu sebabnya select-nya tidak boleh dikunci.
        ->set('form.business_unit_id', $millB->id)
        ->set('form.name', 'Line Pindah Induk')
        ->call('save');

    $component->assertHasNoErrors();

    $line = ProductionLine::where('name', 'Line Pindah Induk')->first();
    expect($line)->not->toBeNull()
        ->and($line->business_unit_id)->toBe($millB->id);

    $html = $component->html();
    expect(md127Card($html, 'Mill Pindah B'))->toContain('data-testid="line-name">Line Pindah Induk<')
        ->and(md127Card($html, $millA->name))->not->toContain('data-testid="line-name">Line Pindah Induk<');

    expect(md127LineCountOnCard($html, 'Mill Pindah B'))->toBe(1)
        ->and(md127LineCountOnCard($html, $millA->name))->toBe(1);
});

it('Tambah Entitas Hierarki Master Data — Membatalkan modal', function () {
    $chain = md127Chain('Batal');

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class);
    $before = md127CountsInDatabase();
    $countsBefore = md127CountsShown($component->html());

    $component->call('openCreate', 'company')
        ->set('form.company_code', 'E2E-CMP-X')
        ->set('form.name', 'Company Batal')
        ->call('closeModal');

    $component->assertSet('modalLevel', null)
        ->assertSet('modalMode', null)
        ->assertSet('editingId', null)
        ->assertSet('form', [])
        ->assertSet('logo', null)
        ->assertSet('formErrorMessage', null)
        ->assertSet('successMessage', null);

    // Tidak ada baris baru di keempat tabel.
    expect(md127CountsInDatabase())->toBe($before);
    expect(md127CountsShown($component->html()))->toBe($countsBefore);
    expect($chain['corporate']->fresh()->name)->toBe('Corp Batal');
});

it('Tambah Entitas Hierarki Master Data — Belum ada calon induk', function () {
    // (a) business-unit tanpa satu pun Company.
    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openCreate', 'business-unit');

    $component->assertSet('modalLevel', null)->assertSet('modalMode', null);
    expect($component->get('formErrorMessage'))->toContain('Company');
    // Tidak ada <select> induk kosong yang terender.
    expect(md127Occurrences($component->html(), 'parent-select'))->toBe(0);
    expect($component->html())->toContain('data-testid="form-error-page"');

    // (b) company tanpa Corporate.
    $company = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openCreate', 'company');
    $company->assertSet('modalLevel', null);
    expect($company->get('formErrorMessage'))->toContain('Corporate');
    expect(md127Occurrences($company->html(), 'parent-select'))->toBe(0);

    // (c) production-line tanpa Business Unit.
    $line = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openCreate', 'production-line');
    $line->assertSet('modalLevel', null);
    expect($line->get('formErrorMessage'))->toContain('Business Unit');
    expect(md127Occurrences($line->html(), 'parent-select'))->toBe(0);

    // Corporate is the root: it has no parent to be missing, so it always
    // opens even on an empty database.
    Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openCreate', 'corporate')
        ->assertSet('modalLevel', 'corporate');
});

/**
 * ⚠ CATATAN SQLITE vs POSTGRESQL. Test ini LULUS di sini dan HARUS tetap
 * dibaca sebagai lulus-yang-tidak-cukup: suite ini berjalan di SQLite,
 * dan jalur yang sama menjawab HTTP 500 di PostgreSQL. companyRules()
 * membangun aturan unik nama Company dengan
 * `->where(fn ($q) => $q->where('corporate_id', $corporateId))`, dan
 * ketika induknya belum dipilih $corporateId adalah '' — SQLite menerima
 * '' pada kolom uuid, PostgreSQL menolaknya (SQLSTATE 22P02). Buktinya
 * ada di e2e-web/tests/struktur-mills.spec.ts skenario 21, yang gagal
 * terhadap database e2e PostgreSQL; docblock di sana memuat rinciannya.
 * Jangan "perbaiki" test ini — yang perlu diperbaiki adalah aturannya.
 */
it('Tambah Entitas Hierarki Master Data — induk wajib tidak dipilih', function () {
    md127Corporate('Corp Induk Ada');

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openCreate', 'company')
        ->set('form.company_code', 'E2E-CMP-02')
        ->set('form.name', 'Company Tanpa Induk')
        ->call('save');

    $component->assertHasErrors('form.corporate_id');
    expect(Company::count())->toBe(0);
    $component->assertSet('modalLevel', 'company')
        ->assertSet('form.company_code', 'E2E-CMP-02')
        ->assertSet('form.name', 'Company Tanpa Induk')
        ->assertSet('successMessage', null);

    // Pesannya dirender pada select induknya.
    expect($component->html())->toContain('data-testid="parent-select"');
});

it('Tambah Entitas Hierarki Master Data — kode Production Line diisi tetapi sudah dipakai', function () {
    $chain = md127Chain('KodeBentrok');
    md127Line($chain['mill'], 'Line Sudah Ada', 'PL-DIPAKAI');
    $linesBefore = ProductionLine::count();

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openCreate', 'production-line', $chain['mill']->id)
        ->set('form.code', 'PL-DIPAKAI')
        ->set('form.name', 'Line Kode Bentrok')
        ->call('save');

    // Kode line memang opsional, tetapi bila diisi tetap harus unik.
    $component->assertHasErrors('form.code');
    expect(ProductionLine::count())->toBe($linesBefore);
    $component->assertSet('modalLevel', 'production-line')
        ->assertSet('form.name', 'Line Kode Bentrok')
        ->assertSet('successMessage', null);
});

it('Tambah Entitas Hierarki Master Data — induk terisi otomatis tidak boleh dikunci atau disembunyikan', function () {
    $chain = md127Chain('Terisi');
    $millA = $chain['mill'];
    $millB = md127Mill($chain['company'], 'Mill Terisi B', 'BU-TRS-B');

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openCreate', 'production-line', $millA->id);

    // Terisi otomatis...
    $component->assertSet('form.business_unit_id', $millA->id);

    $html = $component->html();
    expect($html)->toContain('data-testid="parent-select"');

    // ...tetapi select-nya TERENDER dan TIDAK membawa disabled / readonly /
    // type=hidden (uiux-spec component_patterns[web-form-input]).
    $select = explode('</select>', explode('<select', $html)[1])[0];
    expect($select)->toContain('data-testid="parent-select"')
        ->and($select)->not->toContain('disabled')
        ->and($select)->not->toContain('readonly')
        ->and($select)->not->toContain('type="hidden"');
    expect($html)->not->toContain('type="hidden" wire:model="form.business_unit_id"');

    // Select itu memuat opsi millB juga.
    expect($select)->toContain('value="'.$millA->id.'"')
        ->and($select)->toContain('value="'.$millB->id.'"')
        ->and($select)->toContain('>'.$millB->name.'<');

    // Mengubah nilainya berhasil tanpa galat.
    $component->set('form.business_unit_id', $millB->id)
        ->assertSet('form.business_unit_id', $millB->id)
        ->assertHasNoErrors();
});

it('Tambah Entitas Hierarki Master Data — logo tidak diisi', function () {
    $chain = md127Chain('NoLogo');

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openCreate', 'business-unit')
        ->set('form.company_id', $chain['company']->id)
        ->set('form.code', 'E2E-BU-NOLOGO')
        ->set('form.name', 'Mill Tanpa Logo')
        ->call('save');

    // Tidak ada galat 'logo' maupun 'form.logo'.
    $component->assertHasNoErrors(['logo', 'form.logo']);
    $component->assertHasNoErrors();

    $created = BusinessUnit::where('code', 'E2E-BU-NOLOGO')->first();
    expect($created)->not->toBeNull()
        ->and($created->logo)->toBeNull();

    $component->assertSet('modalLevel', null)
        ->assertSet('successMessage', 'Business Unit berhasil ditambahkan.');

    // Render berikutnya menampilkan kartunya dengan penanda inisial, bukan
    // <img> ber-src kosong.
    $card = md127Card($component->html(), 'Mill Tanpa Logo');
    expect($card)->toContain('data-testid="mill-logo-fallback"')
        ->and($card)->not->toContain('<img')
        ->and($card)->not->toContain('src=""');
});

it('Tambah Entitas Hierarki Master Data — angka jumlah dihitung ulang setelah berhasil', function () {
    $chain = md127Chain('HitungUlang');
    md127Line($chain['mill'], 'Line Pertama');
    md127Line($chain['mill'], 'Line Kedua');

    // Satu instance saja, dari awal sampai akhir — pembuktian bahwa data
    // dimuat di render(), bukan di mount().
    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class);
    $before = $component->html();

    $linesBefore = md127CountShown($before, 'production-line');
    expect($linesBefore)->toBe(2)
        ->and(md127LineCountOnCard($before, $chain['mill']->name))->toBe(2);

    $component->call('openCreate', 'production-line', $chain['mill']->id)
        ->set('form.name', 'Line Ketiga')
        ->call('save')
        ->assertHasNoErrors();

    $after = $component->html();

    expect(md127CountShown($after, 'production-line'))->toBe($linesBefore + 1)
        ->and(md127LineCountOnCard($after, $chain['mill']->name))->toBe(3)
        ->and($after)->toContain('data-testid="line-name">Line Ketiga<');

    // Keempat angka ringkasan tetap sama dengan COUNT langsung atas
    // keempat tabel.
    expect(md127CountsShown($after))->toBe(md127CountsInDatabase());
});

// ═════════════════════════════════════════════════════════════════════════
// usecase-157 — 8 unit_test_cases
// ═════════════════════════════════════════════════════════════════════════

it("openCreate('production-line', \$millId) mengisi induk pada form", function () {
    $chain = md127Chain('UnitTambah');

    Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openCreate', 'production-line', $chain['mill']->id)
        ->assertSet('form.business_unit_id', $chain['mill']->id)
        ->assertSet('modalMode', 'create')
        ->assertSet('modalLevel', 'production-line')
        ->assertSet('editingId', null);
});

it('openCreate untuk tingkat yang belum punya calon induk tidak membuka modal', function () {
    // Tidak ada satu pun Company.
    expect(Company::count())->toBe(0);

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openCreate', 'business-unit');

    $component->assertSet('modalLevel', null)->assertSet('modalMode', null);
    expect($component->get('formErrorMessage'))->toContain('Company');
});

it('save() dengan field wajib kosong tidak memanggil service', function () {
    $this->mock(BusinessUnitService::class, function ($mock) {
        $mock->shouldReceive('companyOptions')->andReturn([['id' => 'x', 'name' => 'x']]);
        $mock->shouldNotReceive('create');
        $mock->shouldNotReceive('update');
    });

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openCreate', 'business-unit')
        ->set('form.company_id', 'x')
        ->set('form.code', 'BU-NO-NAME')
        ->call('save');

    $component->assertHasErrors('form.name');
});

it('save() memetakan ValidationException service ke kunci form.<field>', function () {
    $chain = md127Chain('MapError');

    $this->mock(BusinessUnitService::class, function ($mock) use ($chain) {
        $mock->shouldReceive('companyOptions')->andReturn([
            ['id' => $chain['company']->id, 'name' => $chain['company']->name],
        ]);
        $mock->shouldReceive('create')->once()->andThrow(
            ValidationException::withMessages(['code' => 'Kode business unit sudah digunakan.'])
        );
    });

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openCreate', 'business-unit')
        ->set('form.company_id', $chain['company']->id)
        ->set('form.code', 'BU-BARU-SEKALI')
        ->set('form.name', 'Mill Map Error')
        ->call('save');

    $component->assertHasErrors('form.code');
    expect($component->errors()->get('form.code')[0])->toBe('Kode business unit sudah digunakan.');
    $component->assertSet('modalLevel', 'business-unit')
        ->assertSet('successMessage', null);
});

it('save() memetakan galat logo tanpa awalan form.', function () {
    $this->mock(CorporateService::class, function ($mock) {
        $mock->shouldReceive('create')->once()->andThrow(
            ValidationException::withMessages(['logo' => 'Logo harus berupa file gambar.'])
        );
    });

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openCreate', 'corporate')
        ->set('form.corporate_code', 'CORP-LOGO-KEY')
        ->set('form.name', 'Corp Logo Key')
        ->call('save');

    $component->assertHasErrors('logo');
    expect($component->errors()->has('form.logo'))->toBeFalse();
    expect($component->errors()->get('logo')[0])->toBe('Logo harus berupa file gambar.');
});

it('save() berhasil menutup modal dan mengisi successMessage', function () {
    $chain = md127Chain('SaveOk');

    Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openCreate', 'business-unit')
        ->set('form.company_id', $chain['company']->id)
        ->set('form.code', 'BU-SAVE-OK')
        ->set('form.name', 'Mill Save Ok')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('modalLevel', null)
        ->assertSet('modalMode', null)
        ->assertSet('form', [])
        ->assertSet('logo', null)
        ->assertSet('successMessage', 'Business Unit berhasil ditambahkan.');
});

it('save() memakai induk dari $form, bukan dari parameter openCreate', function () {
    $chain = md127Chain('ArgInduk');
    $millA = $chain['mill'];
    $millB = md127Mill($chain['company'], 'Mill Arg B', 'BU-ARG-B');

    $received = null;
    $this->mock(ProductionLineService::class, function ($mock) use ($millA, $millB, &$received) {
        $mock->shouldReceive('businessUnitOptions')->andReturn([
            ['id' => $millA->id, 'name' => $millA->name],
            ['id' => $millB->id, 'name' => $millB->name],
        ]);
        $mock->shouldReceive('create')->once()->andReturnUsing(function (array $data) use (&$received) {
            $received = $data;

            return [];
        });
    });

    Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openCreate', 'production-line', $millA->id)
        ->set('form.business_unit_id', $millB->id)
        ->set('form.name', 'Line Arg Induk')
        ->call('save')
        ->assertHasNoErrors();

    expect($received)->not->toBeNull()
        ->and($received['business_unit_id'])->toBe($millB->id)
        ->and($received['business_unit_id'])->not->toBe($millA->id);
});

it('save() menangkap ModelNotFoundException ketika induk sudah dihapus', function () {
    $chain = md127Chain('IndukHilang');

    $this->mock(BusinessUnitService::class, function ($mock) use ($chain) {
        $mock->shouldReceive('companyOptions')->andReturn([
            ['id' => $chain['company']->id, 'name' => $chain['company']->name],
        ]);
        $mock->shouldReceive('create')->once()->andThrow(new ModelNotFoundException);
    });

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openCreate', 'business-unit')
        ->set('form.company_id', $chain['company']->id)
        ->set('form.code', 'BU-INDUK-HILANG')
        ->set('form.name', 'Mill Induk Hilang')
        ->call('save');

    expect($component->get('formErrorMessage'))->not->toBeNull()
        ->and($component->get('formErrorMessage'))->toContain('Company')
        ->and($component->get('formErrorMessage'))->not->toContain('ModelNotFoundException');
    $component->assertSet('modalLevel', 'business-unit')
        ->assertSet('successMessage', null);
    expect($component->html())->toContain('data-testid="form-error"');
});

// ═════════════════════════════════════════════════════════════════════════
// usecase-158--ubah-entitas-hierarki-master-data — 9 bindings
// ═════════════════════════════════════════════════════════════════════════

it('Ubah Entitas Hierarki Master Data — berhasil', function () {
    $chain = md127Chain('UbahOk');
    $mill = $chain['mill'];
    $mill->update([
        'logo' => 'business-unit-logos/ubah-ok.png',
        'telephone_no' => '021-555-0001',
        'email' => 'mill@contoh.co.id',
    ]);

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openEdit', 'business-unit', $mill->id);

    // $form memuat code, name, company_id, dan field kontaknya.
    $component->assertSet('form.code', $mill->code)
        ->assertSet('form.name', $mill->name)
        ->assertSet('form.company_id', $chain['company']->id)
        ->assertSet('form.telephone_no', '021-555-0001')
        ->assertSet('form.email', 'mill@contoh.co.id')
        ->assertSet('editingId', $mill->id)
        ->assertSet('modalMode', 'edit');

    // Keterangan nama berkas logo yang sedang dipakai terender, dan TIDAK
    // diisikan ke input file.
    $html = $component->html();
    expect($component->get('existingLogoName'))->toBe('ubah-ok.png')
        ->and($html)->toContain('data-testid="existing-logo-name"')
        ->and($html)->toContain('ubah-ok.png');
    $fileInput = explode('>', explode('data-testid="field-logo"', $html)[0]);
    expect(end($fileInput))->not->toContain('value=');

    $component->set('form.name', 'Mill Nama Baru')->call('save')->assertHasNoErrors();

    expect($mill->fresh()->name)->toBe('Mill Nama Baru');
    $component->assertSet('modalLevel', null)
        ->assertSet('successMessage', 'Business Unit berhasil diperbarui.');
    expect(md127HasCard($component->html(), 'Mill Nama Baru'))->toBeTrue();
});

it('Ubah Entitas Hierarki Master Data — Memindahkan entitas ke induk lain', function () {
    $chain = md127Chain('Geser');
    $millA = $chain['mill'];
    $millB = md127Mill($chain['company'], 'Mill Geser B', 'BU-GSR-B');

    $lineToMove = md127Line($millA, 'Line Geser Pindah');
    md127Line($millA, 'Line Geser Tetap');
    md127Line($millB, 'Line Geser B1');

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class);
    $before = $component->html();
    expect(md127LineCountOnCard($before, $millA->name))->toBe(2)
        ->and(md127LineCountOnCard($before, 'Mill Geser B'))->toBe(1);
    $totalLinesBefore = md127CountShown($before, 'production-line');

    $component->call('openEdit', 'production-line', $lineToMove->id)
        ->set('form.business_unit_id', $millB->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($lineToMove->fresh()->business_unit_id)->toBe($millB->id);

    $after = $component->html();
    expect(md127Card($after, 'Mill Geser B'))->toContain('data-testid="line-name">Line Geser Pindah<')
        ->and(md127Card($after, $millA->name))->not->toContain('data-testid="line-name">Line Geser Pindah<');

    // Kedua angka benar tanpa penyesuaian manual.
    expect(md127LineCountOnCard($after, $millA->name))->toBe(1)
        ->and(md127LineCountOnCard($after, 'Mill Geser B'))->toBe(2);

    // Total Production Line pada baris ringkasan TIDAK berubah.
    expect(md127CountShown($after, 'production-line'))->toBe($totalLinesBefore);
});

it('Ubah Entitas Hierarki Master Data — Field wajib dikosongkan', function () {
    $chain = md127Chain('WajibKosong');
    $mill = $chain['mill'];
    $originalName = $mill->name;
    $originalUpdatedAt = $mill->fresh()->updated_at;

    $this->mock(BusinessUnitService::class, function ($mock) use ($chain) {
        $mock->shouldReceive('companyOptions')->andReturn([
            ['id' => $chain['company']->id, 'name' => $chain['company']->name],
        ]);
        $mock->shouldNotReceive('update');
    });

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openEdit', 'business-unit', $mill->id)
        ->set('form.name', '')
        ->call('save');

    $component->assertHasErrors('form.name')
        ->assertSet('modalMode', 'edit')
        ->assertSet('editingId', $mill->id)
        ->assertSet('successMessage', null);

    // Baris basis data TIDAK tersentuh — nama dan updated_at sama persis.
    $fresh = $mill->fresh();
    expect($fresh->name)->toBe($originalName)
        ->and($fresh->updated_at->equalTo($originalUpdatedAt))->toBeTrue();
});

it('Ubah Entitas Hierarki Master Data — Kode diubah menjadi kode yang sudah dipakai', function () {
    $chain = md127Chain('KodeTabrak');
    $millA = $chain['mill'];
    $millA->update(['code' => 'BU-A']);
    md127Mill($chain['company'], 'Mill Tabrak B', 'BU-B');

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openEdit', 'business-unit', $millA->id)
        ->set('form.code', 'BU-B')
        ->call('save');

    $component->assertHasErrors('form.code');
    expect($component->errors()->get('form.code')[0])->toContain('sudah digunakan');

    // Kode millA di basis data masih 'BU-A'.
    expect($millA->fresh()->code)->toBe('BU-A');
    $component->assertSet('modalLevel', 'business-unit')
        ->assertSet('modalMode', 'edit')
        ->assertSet('successMessage', null);
});

it('Ubah Entitas Hierarki Master Data — Kode disimpan apa adanya tanpa diubah', function () {
    $chain = md127Chain('KodeTetap');
    $mill = $chain['mill'];
    $mill->update(['code' => 'BU-A']);

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openEdit', 'business-unit', $mill->id)
        ->set('form.name', 'Mill Nama Lain')
        ->call('save');

    // ignore($editingId) pada aturan unik mencegah entitas bentrok dengan
    // dirinya sendiri — tanpa ini, SETIAP penyimpanan tanpa mengubah kode
    // akan gagal.
    $component->assertHasNoErrors();

    $fresh = $mill->fresh();
    expect($fresh->name)->toBe('Mill Nama Lain')
        ->and($fresh->code)->toBe('BU-A');

    $component->assertSet('modalLevel', null)
        ->assertSet('successMessage', 'Business Unit berhasil diperbarui.');
});

it('Ubah Entitas Hierarki Master Data — Logo baru ditolak', function () {
    $chain = md127Chain('LogoTolak');
    $mill = $chain['mill'];
    $mill->update(['logo' => 'business-unit-logos/lama.png']);
    Storage::fake(BusinessUnitService::LOGO_DISK);
    Storage::disk(BusinessUnitService::LOGO_DISK)->put('business-unit-logos/lama.png', 'isi-logo-lama');

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openEdit', 'business-unit', $mill->id)
        ->set('form.name', 'Mill Nama Diubah')
        ->set('logo', UploadedFile::fake()->create('palsu.png', 10, 'image/png'))
        ->call('save');

    // Kunci tanpa awalan form.
    $component->assertHasErrors('logo');
    expect($component->errors()->has('form.logo'))->toBeFalse();

    // Modal tetap terbuka; seluruh perubahan field lain TETAP ada di form.
    $component->assertSet('modalLevel', 'business-unit')
        ->assertSet('modalMode', 'edit')
        ->assertSet('form.name', 'Mill Nama Diubah')
        ->assertSet('successMessage', null);

    // Kolom logo di basis data masih menunjuk berkas LAMA, dan berkas itu
    // masih ada di disk — tidak ada operasi tulis yang terjadi.
    expect($mill->fresh()->logo)->toBe('business-unit-logos/lama.png')
        ->and($mill->fresh()->name)->not->toBe('Mill Nama Diubah');
    Storage::disk(BusinessUnitService::LOGO_DISK)->assertExists('business-unit-logos/lama.png');
});

it('Ubah Entitas Hierarki Master Data — Entitas sudah dihapus Admin lain', function () {
    $chain = md127Chain('HilangUbah');
    $mill = $chain['mill'];
    $millId = $mill->id;

    // (a) Dihapus SEBELUM openEdit.
    BusinessUnit::whereKey($millId)->delete();

    $opened = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openEdit', 'business-unit', $millId);

    $opened->assertSet('modalLevel', null)->assertSet('modalMode', null);
    expect($opened->get('formErrorMessage'))->toContain('sudah dihapus')
        ->and($opened->get('formErrorMessage'))->not->toContain('ModelNotFoundException')
        ->and($opened->get('formErrorMessage'))->not->toContain('Illuminate\\');
    // Render berikutnya tidak lagi memuat entitas itu.
    expect(md127HasCard($opened->html(), 'Mill HilangUbah'))->toBeFalse();

    // (b) Dihapus DI ANTARA openEdit dan save.
    $second = md127Mill($chain['company'], 'Mill Hilang Tengah', 'BU-HT');

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openEdit', 'business-unit', $second->id);
    BusinessUnit::whereKey($second->id)->delete();
    $component->set('form.name', 'X')->call('save');

    expect($component->get('formErrorMessage'))->toContain('sudah dihapus')
        ->and($component->get('formErrorMessage'))->not->toContain('ModelNotFoundException');
    $component->assertSet('successMessage', null)
        // Modal tetap terbuka dengan isiannya.
        ->assertSet('modalLevel', 'business-unit');
    expect(md127HasCard($component->html(), 'Mill Hilang Tengah'))->toBeFalse();
});

it('Ubah Entitas Hierarki Master Data — Dua Admin mengubah satu entitas hampir bersamaan', function () {
    $chain = md127Chain('Rebutan');
    $mill = $chain['mill'];

    $instanceOne = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openEdit', 'business-unit', $mill->id)
        ->set('form.name', 'Nama Dari Admin 1');

    $instanceTwo = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openEdit', 'business-unit', $mill->id)
        ->set('form.name', 'Nama Dari Admin 2')
        ->call('save');

    $instanceTwo->assertHasNoErrors()
        ->assertSet('successMessage', 'Business Unit berhasil diperbarui.');
    expect($mill->fresh()->name)->toBe('Nama Dari Admin 2');

    // Baru kemudian instance1 menyimpan — dan ia menang, tanpa galat
    // konflik dan tanpa pesan penguncian apa pun.
    $instanceOne->call('save')
        ->assertHasNoErrors()
        ->assertSet('successMessage', 'Business Unit berhasil diperbarui.')
        ->assertSet('formErrorMessage', null);

    expect($mill->fresh()->name)->toBe('Nama Dari Admin 1');

    // Tidak ada kolom/kondisi penguncian optimistis yang diperkenalkan
    // komponen ini — perilakunya sama dengan keempat layar Kelola.
    foreach (['version', 'lock_version', 'row_version', 'updated_at_token'] as $lockish) {
        expect(property_exists(MasterDataTreeView::class, $lockish))->toBeFalse();
    }
});

it('Ubah Entitas Hierarki Master Data — Membatalkan modal', function () {
    $corporate = md127Corporate('Corp Batal Ubah', 'CORP-BU-1');

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openEdit', 'corporate', $corporate->id)
        ->set('form.name', 'Nama Yang Dibatalkan')
        ->set('form.corporate_code', 'KODE-BATAL')
        ->call('closeModal');

    $component->assertSet('form', [])
        ->assertSet('logo', null)
        ->assertSet('editingId', null)
        ->assertSet('modalLevel', null)
        ->assertSet('modalMode', null)
        ->assertSet('formErrorMessage', null);

    // Baris corporates sama persis seperti sebelum openEdit.
    $fresh = $corporate->fresh();
    expect($fresh->name)->toBe('Corp Batal Ubah')
        ->and($fresh->corporate_code)->toBe('CORP-BU-1');

    // Render berikutnya menampilkan nilai lama.
    $html = $component->html();
    expect(md127Row($html, 'Corp Batal Ubah'))->toContain('>CORP-BU-1</span>')
        ->and($html)->not->toContain('Nama Yang Dibatalkan');

    // Membuka openEdit lagi memuat nilai dari basis data, bukan sisa isian
    // yang dibatalkan.
    $component->call('openEdit', 'corporate', $corporate->id)
        ->assertSet('form.name', 'Corp Batal Ubah')
        ->assertSet('form.corporate_code', 'CORP-BU-1');
});

// ═════════════════════════════════════════════════════════════════════════
// usecase-158 — 7 unit_test_cases
// ═════════════════════════════════════════════════════════════════════════

it('openEdit mengisi form dengan seluruh nilai entitas termasuk induknya', function () {
    $chain = md127Chain('UnitEdit');
    $mill = $chain['mill'];
    $mill->update([
        'telephone_no' => '021-777-0001',
        'contact_no' => '0812-0000-1111',
        'address' => 'Jalan Uji Nomor 1',
    ]);

    Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openEdit', 'business-unit', $mill->id)
        ->assertSet('form.code', $mill->code)
        ->assertSet('form.name', $mill->name)
        ->assertSet('form.company_id', $chain['company']->id)
        ->assertSet('form.telephone_no', '021-777-0001')
        ->assertSet('form.contact_no', '0812-0000-1111')
        ->assertSet('form.address', 'Jalan Uji Nomor 1')
        ->assertSet('editingId', $mill->id)
        ->assertSet('modalMode', 'edit');
});

it('openEdit atas entitas yang sudah dihapus tidak membuka modal', function () {
    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openEdit', 'business-unit', '00000000-0000-0000-0000-000000000000');

    $component->assertSet('modalLevel', null)->assertSet('modalMode', null);
    expect($component->get('formErrorMessage'))->toContain('sudah dihapus');
});

it('menyimpan tanpa mengubah kode tidak dianggap bentrok dengan diri sendiri', function () {
    $chain = md127Chain('IgnoreSelf');
    $mill = $chain['mill'];
    $mill->update(['code' => 'BU-A']);

    $received = null;
    $this->mock(BusinessUnitService::class, function ($mock) use ($chain, &$received) {
        $mock->shouldReceive('companyOptions')->andReturn([
            ['id' => $chain['company']->id, 'name' => $chain['company']->name],
        ]);
        $mock->shouldReceive('update')->once()->andReturnUsing(function (string $id, array $data) use (&$received) {
            $received = $data;

            return [];
        });
    });

    Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openEdit', 'business-unit', $mill->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($received)->not->toBeNull()
        ->and($received['code'])->toBe('BU-A');
});

it('mengubah kode menjadi kode mill lain ditolak', function () {
    $chain = md127Chain('UnitTabrak');
    $millA = $chain['mill'];
    $millA->update(['code' => 'BU-A']);
    md127Mill($chain['company'], 'Mill Unit B', 'BU-B');

    $this->mock(BusinessUnitService::class, function ($mock) use ($chain) {
        $mock->shouldReceive('companyOptions')->andReturn([
            ['id' => $chain['company']->id, 'name' => $chain['company']->name],
        ]);
        $mock->shouldNotReceive('update');
    });

    Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openEdit', 'business-unit', $millA->id)
        ->set('form.code', 'BU-B')
        ->call('save')
        ->assertHasErrors('form.code');

    expect($millA->fresh()->code)->toBe('BU-A');
});

it('mengubah induk memanggil service dengan id induk yang baru', function () {
    $chain = md127Chain('UnitPindah');
    $millA = $chain['mill'];
    $millB = md127Mill($chain['company'], 'Mill Unit Pindah B', 'BU-UP-B');
    $line = md127Line($millA, 'Line Unit Pindah');

    $received = null;
    $this->mock(ProductionLineService::class, function ($mock) use ($millA, $millB, &$received) {
        $mock->shouldReceive('businessUnitOptions')->andReturn([
            ['id' => $millA->id, 'name' => $millA->name],
            ['id' => $millB->id, 'name' => $millB->name],
        ]);
        $mock->shouldReceive('update')->once()->andReturnUsing(function (string $id, array $data) use (&$received) {
            $received = $data;

            return [];
        });
    });

    Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openEdit', 'production-line', $line->id)
        ->set('form.business_unit_id', $millB->id)
        ->call('save')
        ->assertHasNoErrors();

    expect($received)->not->toBeNull()
        ->and($received['business_unit_id'])->toBe($millB->id);
});

it('logo null berarti tidak mengubah logo', function () {
    $chain = md127Chain('LogoNull');
    $mill = $chain['mill'];
    $mill->update(['logo' => 'business-unit-logos/tetap.png']);

    $receivedLogo = 'belum-dipanggil';
    $this->mock(BusinessUnitService::class, function ($mock) use ($chain, &$receivedLogo) {
        $mock->shouldReceive('companyOptions')->andReturn([
            ['id' => $chain['company']->id, 'name' => $chain['company']->name],
        ]);
        $mock->shouldReceive('update')->once()
            ->andReturnUsing(function (string $id, array $data, $logo = null) use (&$receivedLogo) {
                $receivedLogo = $logo;

                return [];
            });
    });

    Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openEdit', 'business-unit', $mill->id)
        ->set('form.name', 'Mill Logo Null')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('logo', null);

    expect($receivedLogo)->toBeNull();
    // Logo lama tetap ada — "jangan ubah logo", bukan "hapus logo".
    expect($mill->fresh()->logo)->toBe('business-unit-logos/tetap.png');
});

it('save() menangkap ModelNotFoundException saat entitas hilang di tengah jalan', function () {
    $chain = md127Chain('SaveHilang');
    $mill = $chain['mill'];

    $this->mock(BusinessUnitService::class, function ($mock) use ($chain) {
        $mock->shouldReceive('companyOptions')->andReturn([
            ['id' => $chain['company']->id, 'name' => $chain['company']->name],
        ]);
        $mock->shouldReceive('update')->once()->andThrow(new ModelNotFoundException);
    });

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('openEdit', 'business-unit', $mill->id)
        ->set('form.name', 'Mill Save Hilang')
        ->call('save');

    expect($component->get('formErrorMessage'))->not->toBeNull()
        ->and($component->get('formErrorMessage'))->toContain('sudah dihapus');
    $component->assertSet('successMessage', null);
});

// ═════════════════════════════════════════════════════════════════════════
// usecase-159--hapus-entitas-hierarki-master-data — 9 bindings
// ═════════════════════════════════════════════════════════════════════════

it('Hapus Entitas Hierarki Master Data — sukses', function () {
    $clean = md127Corporate('Corp Bersih');
    $keep = md127Chain('Sisa');
    md127Line($keep['mill'], 'Line Sisa Satu');

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class);
    $before = md127CountsShown($component->html());

    $component->call('askDelete', 'corporate', $clean->id);

    expect($component->get('confirmingDelete'))->toBe([
        'level' => 'corporate',
        'id' => $clean->id,
        'name' => 'Corp Bersih',
    ]);
    expect($component->html())->toContain('data-testid="delete-confirm-text"')
        ->and($component->html())->toContain('<strong>Corp Bersih</strong>');

    $component->call('confirmDelete');

    expect(Corporate::find($clean->id))->toBeNull();
    $component->assertSet('confirmingDelete', [])
        ->assertSet('deleteErrorMessage', null)
        ->assertSet('successMessage', 'Corporate berhasil dihapus.');

    $after = md127CountsShown($component->html());
    expect($after['corporate'])->toBe($before['corporate'] - 1)
        // Jumlah baris ketiga tabel lain tidak berubah.
        ->and($after['company'])->toBe($before['company'])
        ->and($after['business_unit'])->toBe($before['business_unit'])
        ->and($after['production_line'])->toBe($before['production_line']);
});

it('Hapus Entitas Hierarki Master Data — Penghapusan ditolak karena masih ada yang bergantung', function () {
    // ── (a) TANPA mock, atas mill NYATA yang memang punya penghalang ────
    // Deliberately first: part (b) binds a mocked BusinessUnitService into
    // the container for the rest of the test, and a mock installed before
    // this would answer here too — which is exactly how a test like this
    // ends up asserting the mock's own message and proving nothing.
    $real = md127Chain('DitolakNyata');
    $realMill = $real['mill'];
    $realLine = md127Line($realMill, 'Line Penghalang');
    Station::factory()->forProductionLine($realLine)->create();
    User::factory()->role(UserRole::Operator)->forBusinessUnit($realMill)->create();

    $live = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('askDelete', 'business-unit', $realMill->id)
        ->call('confirmDelete');

    // Nothing was deleted, at any of the four levels.
    expect(BusinessUnit::find($realMill->id))->not->toBeNull()
        ->and(ProductionLine::find($realLine->id))->not->toBeNull()
        ->and($live->get('confirmingDelete'))->not->toBe([]);

    // The real service's real message, with the real blocker counts in it.
    expect($live->get('deleteErrorMessage'))->toContain('1 User')
        ->toContain('1 Production Line')
        ->toContain('Station');
    expect($live->html())->toContain('data-testid="delete-refusal"')
        ->and($live->html())->toContain('1 User');
    $live->assertSet('successMessage', null);

    // ...and it really is the exception's own getMessage(), verbatim.
    $thrown = null;
    try {
        app(BusinessUnitService::class)->delete($realMill->id);
    } catch (BusinessUnitHasStationsException $exception) {
        $thrown = $exception->getMessage();
    }
    expect($live->get('deleteErrorMessage'))->toBe($thrown);

    // ── (b) pesan service dipalsukan agar angkanya diketahui pasti ──────
    $chain = md127Chain('Ditolak');
    $mill = $chain['mill'];
    $verbatim = 'Business Unit tidak dapat dihapus karena masih memiliki 3 User, 2 Production Line, 36 Station. Hapus atau pindahkan data tersebut terlebih dahulu.';
    $rowsBefore = md127CountsInDatabase();

    $this->mock(BusinessUnitService::class, function ($mock) use ($verbatim) {
        $mock->shouldReceive('delete')->once()->andThrow(new BusinessUnitHasStationsException($verbatim));
    });

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('askDelete', 'business-unit', $mill->id)
        ->call('confirmDelete');

    // Tidak ada baris yang terhapus di keempat tabel.
    expect(BusinessUnit::find($mill->id))->not->toBeNull()
        ->and(md127CountsInDatabase())->toBe($rowsBefore);

    // Modal konfirmasi TETAP TERBUKA...
    expect($component->get('confirmingDelete'))->not->toBe([]);

    // ...dan $deleteErrorMessage SAMA PERSIS dengan $e->getMessage().
    $component->assertSet('deleteErrorMessage', $verbatim);

    // Markup modal memuat angka-angkanya — assert pada substring angka,
    // bukan pada frasa generik.
    $html = $component->html();
    expect($html)->toContain('data-testid="delete-refusal"')
        ->and($html)->toContain('3 User')
        ->and($html)->toContain('2 Production Line')
        ->and($html)->toContain('36 Station');

    // Dan pesannya tidak diganti frasa yang disusun komponen.
    expect($component->get('deleteErrorMessage'))->not->toContain('gagal menghapus')
        ->and($component->get('deleteErrorMessage'))->not->toContain('Gagal menghapus');
    $component->assertSet('successMessage', null);
});

it('Hapus Entitas Hierarki Master Data — Membatalkan konfirmasi', function () {
    $chain = md127Chain('BatalHapus');
    $line = md127Line($chain['mill'], 'Line Batal Hapus');

    $this->mock(ProductionLineService::class, function ($mock) {
        $mock->shouldNotReceive('delete');
    });

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class);
    $before = md127CountsInDatabase();
    $shownBefore = md127CountsShown($component->html());

    $component->call('askDelete', 'production-line', $line->id);
    expect($component->get('confirmingDelete'))->not->toBe([]);

    $component->call('cancelDelete');

    $component->assertSet('confirmingDelete', [])
        ->assertSet('deleteErrorMessage', null)
        ->assertSet('successMessage', null);

    // Tidak ada tulisan apa pun yang terjadi.
    expect(md127CountsInDatabase())->toBe($before);
    expect(md127CountsShown($component->html()))->toBe($shownBefore);
    expect(ProductionLine::find($line->id))->not->toBeNull();
});

it('Hapus Entitas Hierarki Master Data — Menghapus Production Line yang stasiunnya masih bersih', function () {
    $chain = md127Chain('LineBersih');
    $mill = $chain['mill'];
    $line = md127Line($mill, 'Line Bersih');
    md127Line($mill, 'Line Lain');
    $stationOne = Station::factory()->forProductionLine($line)->weighbridge()->create();
    $stationTwo = Station::factory()->forProductionLine($line)->grading()->create();

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class);
    $linesBefore = md127CountShown($component->html(), 'production-line');

    $component->call('askDelete', 'production-line', $line->id);

    // Modal konfirmasi merender keterangan bahwa stasiun ikut terhapus
    // SEBELUM disetujui.
    expect($component->html())->toContain('Seluruh Station milik Production Line ini ikut terhapus');

    $component->call('confirmDelete');

    // Baris line itu dan KEDUA baris stations miliknya hilang bersamaan.
    expect(ProductionLine::find($line->id))->toBeNull()
        ->and(Station::find($stationOne->id))->toBeNull()
        ->and(Station::find($stationTwo->id))->toBeNull()
        ->and(Station::where('production_line_id', $line->id)->count())->toBe(0);

    $component->assertSet('confirmingDelete', [])
        ->assertSet('deleteErrorMessage', null)
        ->assertSet('successMessage', 'Production Line berhasil dihapus.');

    $after = $component->html();
    expect(md127LineCountOnCard($after, $mill->name))->toBe(1)
        ->and(md127CountShown($after, 'production-line'))->toBe($linesBefore - 1);

    // Mill induknya TIDAK terhapus.
    expect(BusinessUnit::find($mill->id))->not->toBeNull()
        ->and(md127HasCard($after, $mill->name))->toBeTrue();
});

it('Hapus Entitas Hierarki Master Data — Menghapus Production Line yang stasiunnya sudah dipakai', function () {
    $chain = md127Chain('LineKotor');
    $mill = $chain['mill'];
    $line = md127Line($mill, 'Line Kotor');
    $weighbridge = Station::factory()->forProductionLine($line)->weighbridge()->create();
    $grading = Station::factory()->forProductionLine($line)->grading()->create();
    WeighbridgeRecord::factory()->forStation($weighbridge)->create();
    MachineryGroup::factory()->forStation($grading)->create();

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class);
    $linesBefore = md127CountShown($component->html(), 'production-line');

    $component->call('askDelete', 'production-line', $line->id)->call('confirmDelete');

    // Baris line MASIH ada dan SELURUH baris stations miliknya masih ada —
    // seluruh pemeriksaan terjadi di dalam transaksi SEBELUM DELETE pertama.
    expect(ProductionLine::find($line->id))->not->toBeNull()
        ->and(Station::where('production_line_id', $line->id)->count())->toBe(2)
        ->and(Station::find($weighbridge->id))->not->toBeNull()
        ->and(Station::find($grading->id))->not->toBeNull();

    // Modal tetap terbuka dengan pesan service apa adanya, memuat jumlah
    // record produksi dan machinery group — assert pada angkanya.
    expect($component->get('confirmingDelete'))->not->toBe([]);
    $message = (string) $component->get('deleteErrorMessage');
    expect($message)->toContain('1 record stasiun')
        ->toContain('1 Machinery Group');
    expect($component->html())->toContain('data-testid="delete-refusal"')
        ->and($component->html())->toContain('1 record stasiun');

    $component->assertSet('successMessage', null);
    expect(md127CountShown($component->html(), 'production-line'))->toBe($linesBefore);

    // Dan pesan itu memang pesan exception-nya, bukan karangan komponen.
    $thrown = null;
    try {
        app(ProductionLineService::class)->delete($line->id);
    } catch (ProductionLineHasStationsException $exception) {
        $thrown = $exception->getMessage();
    }
    expect($message)->toBe($thrown);
});

it('Hapus Entitas Hierarki Master Data — Entitas sudah dihapus Admin lain', function () {
    $corporate = md127Corporate('Corp Ganda');

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('askDelete', 'corporate', $corporate->id);

    // Sesi lain menghapusnya.
    Corporate::whereKey($corporate->id)->delete();

    $component->call('confirmDelete');

    // Modal konfirmasi TERTUTUP.
    $component->assertSet('confirmingDelete', []);

    // Pesannya kalimat biasa — bukan jejak ModelNotFoundException dan bukan
    // pesan penghalang.
    $message = (string) $component->get('deleteErrorMessage');
    expect($message)->toContain('tidak ditemukan')
        ->toContain('sudah dihapus')
        ->not->toContain('ModelNotFoundException')
        ->not->toContain('Illuminate\\')
        ->not->toContain('masih memiliki');

    $html = $component->html();
    expect($html)->toContain('data-testid="delete-error"')
        ->and($html)->not->toContain('>Corp Ganda</span>');
    expect(md127CountShown($html, 'corporate'))->toBe(0);
});

it('Hapus Entitas Hierarki Master Data — Menghapus mill terakhir milik sebuah Company', function () {
    $corporate = md127Corporate('Corp Tunggal');
    $company = md127Company($corporate, 'Company Tunggal');
    $mill = md127Mill($company, 'Mill Tunggal', 'BU-TUNGGAL');

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class);
    $before = md127CountsShown($component->html());

    $component->call('askDelete', 'business-unit', $mill->id)->call('confirmDelete');

    $component->assertSet('successMessage', 'Business Unit berhasil dihapus.');

    // Baris business_units terhapus; companies induknya MASIH ADA.
    expect(BusinessUnit::find($mill->id))->toBeNull()
        ->and(Company::find($company->id))->not->toBeNull();

    $after = $component->html();

    // Company itu tidak punya kartu mill dan tidak muncul di breadcrumb
    // mana pun...
    expect(md127Occurrences($after, 'mill-card'))->toBe(0)
        ->and($after)->not->toContain('data-testid="mill-crumb"');

    // ...tetapi TETAP terender di daftar ringkas Company dengan
    // business_units_count = 0 yang TERCETAK.
    expect(md127Row($after, 'Company Tunggal'))->toContain('data-testid="company-mill-count">0<');

    expect(md127CountShown($after, 'business-unit'))->toBe($before['business_unit'] - 1)
        ->and(md127CountShown($after, 'company'))->toBe($before['company']);
});

it('Hapus Entitas Hierarki Master Data — Menghapus Production Line terakhir milik sebuah mill', function () {
    $chain = md127Chain('SatuLine');
    $mill = $chain['mill'];
    $line = md127Line($mill, 'Line Satu-satunya');

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class);
    $before = md127CountsShown($component->html());
    expect(md127LineCountOnCard($component->html(), $mill->name))->toBe(1);

    $component->call('askDelete', 'production-line', $line->id)->call('confirmDelete');

    $component->assertSet('successMessage', 'Production Line berhasil dihapus.');
    expect(ProductionLine::find($line->id))->toBeNull()
        ->and(BusinessUnit::find($mill->id))->not->toBeNull();

    $after = $component->html();

    // board() menandai mill itu production_lines_count = 0 dan kartunya
    // TETAP terender dengan keterangan 'belum ada Production Line' beserta
    // tombol tambahnya — bukan ruang kosong.
    expect(md127HasCard($after, $mill->name))->toBeTrue()
        ->and(md127LineCountOnCard($after, $mill->name))->toBe(0);

    $card = md127Card($after, $mill->name);
    expect($card)->toContain('data-testid="mill-no-lines"')
        ->and($card)->toContain('data-testid="add-line-'.$mill->id.'"')
        ->and(md127Occurrences($card, 'line-row'))->toBe(0);

    expect(md127CountShown($after, 'production-line'))->toBe($before['production_line'] - 1)
        ->and(md127CountShown($after, 'business-unit'))->toBe($before['business_unit']);
});

it('Hapus Entitas Hierarki Master Data — konfirmasi tidak menyebut nama entitas', function () {
    $corporate = md127Corporate('Corp Nama');
    $company = md127Company($corporate, 'Company Nama');
    $mill = md127Mill($company, 'Mill Nama', 'BU-NAMA');
    $line = md127Line($mill, 'Line Nama');

    foreach ([
        ['corporate', $corporate->id, 'Corp Nama'],
        ['company', $company->id, 'Company Nama'],
        ['business-unit', $mill->id, 'Mill Nama'],
        ['production-line', $line->id, 'Line Nama'],
    ] as [$level, $id, $name]) {
        $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
            ->call('askDelete', $level, $id);

        // $confirmingDelete['name'] berisi NAMA entitas, bukan hanya id.
        expect($component->get('confirmingDelete')['name'])->toBe($name)
            ->and($component->get('confirmingDelete')['name'])->not->toBe($id);

        // ...dan markup modal konfirmasi MEMUAT nama itu di dalam kalimatnya.
        $html = $component->html();
        expect($html)->toContain('data-testid="delete-confirm-text"')
            ->and($html)->toContain('<strong>'.$name.'</strong>');

        // Untuk production-line, kalimatnya juga menyebut stasiun yang ikut
        // terhapus.
        if ($level === 'production-line') {
            $confirm = explode('</p>', explode('data-testid="delete-confirm-text"', $html)[1])[0];
            expect($confirm)->toContain('Station');
        }
    }

    // Nama yang terender tetap nama yang diambil SAAT askDelete, meski
    // baris basis datanya berubah di antara dua render — namanya disimpan
    // di state, tidak dicari ulang.
    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('askDelete', 'business-unit', $mill->id);
    BusinessUnit::whereKey($mill->id)->update(['name' => 'Nama Yang Berubah']);

    expect($component->html())->toContain('<strong>Mill Nama</strong>')
        ->and($component->get('confirmingDelete')['name'])->toBe('Mill Nama');
});

// ═════════════════════════════════════════════════════════════════════════
// usecase-159 — 7 unit_test_cases
// ═════════════════════════════════════════════════════════════════════════

it('askDelete menyimpan nama entitas, bukan hanya id-nya', function () {
    $chain = md127Chain('AskName');
    $mill = $chain['mill'];
    $mill->update(['name' => 'Business Unit A']);

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('askDelete', 'business-unit', $mill->id);

    expect($component->get('confirmingDelete')['name'])->toBe('Business Unit A')
        ->and($component->get('confirmingDelete')['id'])->toBe($mill->id)
        ->and($component->get('confirmingDelete')['level'])->toBe('business-unit');
});

it('penolakan membiarkan modal terbuka dan menampilkan pesan service apa adanya', function () {
    $chain = md127Chain('Verbatim');
    $mill = $chain['mill'];
    $verbatim = 'Business Unit tidak dapat dihapus karena masih memiliki 3 User, 2 Production Line.';

    $this->mock(BusinessUnitService::class, function ($mock) use ($verbatim) {
        $mock->shouldReceive('delete')->once()->andThrow(new BusinessUnitHasStationsException($verbatim));
    });

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('askDelete', 'business-unit', $mill->id)
        ->call('confirmDelete');

    expect($component->get('confirmingDelete'))->not->toBe([]);
    $component->assertSet('deleteErrorMessage', $verbatim);
    expect($component->get('deleteErrorMessage'))->toContain('3 User')
        ->toContain('2 Production Line');
});

it('pesan penolakan tidak diganti pesan generik', function () {
    $chain = md127Chain('NonGenerik');
    $long = 'Corporate tidak dapat dihapus karena masih memiliki 17 Company yang bergantung padanya, '
        .'termasuk 4 Company yang sudah punya Business Unit. Hapus atau pindahkan data tersebut terlebih dahulu.';

    $this->mock(CorporateService::class, function ($mock) use ($long) {
        $mock->shouldReceive('delete')->once()->andThrow(new CorporateHasCompaniesException($long));
    });

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('askDelete', 'corporate', $chain['corporate']->id)
        ->call('confirmDelete');

    $component->assertSet('deleteErrorMessage', $long);
    expect($component->get('deleteErrorMessage'))->toContain('17 Company')
        ->toContain('4 Company')
        ->not->toContain('gagal menghapus')
        ->not->toContain('Gagal menghapus')
        ->not->toContain('Terjadi kesalahan');
    expect(strlen((string) $component->get('deleteErrorMessage')))->toBe(strlen($long));
});

it('penghapusan berhasil mengosongkan konfirmasi dan mengisi successMessage', function () {
    $clean = md127Corporate('Corp Tanpa Penghalang');

    Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('askDelete', 'corporate', $clean->id)
        ->call('confirmDelete')
        ->assertSet('confirmingDelete', [])
        ->assertSet('successMessage', 'Corporate berhasil dihapus.')
        ->assertSet('deleteErrorMessage', null);

    expect(Corporate::find($clean->id))->toBeNull();
});

it('ModelNotFoundException ditangani sebagai kalimat biasa', function () {
    $chain = md127Chain('MnfeHapus');

    $this->mock(CompanyService::class, function ($mock) {
        $mock->shouldReceive('delete')->once()->andThrow(new ModelNotFoundException);
    });

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('askDelete', 'company', $chain['company']->id)
        ->call('confirmDelete');

    // Modal tertutup, pesannya kalimat biasa.
    $component->assertSet('confirmingDelete', []);
    expect($component->get('deleteErrorMessage'))->toContain('tidak ditemukan')
        ->toContain('sudah dihapus')
        ->not->toContain('ModelNotFoundException')
        ->not->toContain('No query results');
});

it('komponen tidak mengulang pemeriksaan ketergantungan sendiri', function () {
    // Mill yang PUNYA anak (satu Production Line + satu Station), tetapi
    // service-nya dipalsukan agar berhasil.
    $chain = md127Chain('OtoritasService');
    $mill = $chain['mill'];
    $line = md127Line($mill, 'Line Penghalang Nyata');
    Station::factory()->forProductionLine($line)->create();

    $this->mock(BusinessUnitService::class, function ($mock) {
        $mock->shouldReceive('delete')->once()->andReturnNull();
    });

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)
        ->call('askDelete', 'business-unit', $mill->id)
        ->call('confirmDelete');

    // Komponen tetap memanggilnya dan memperlakukannya sebagai BERHASIL —
    // membuktikan otoritasnya hanya di service, bukan ganda.
    $component->assertSet('confirmingDelete', [])
        ->assertSet('deleteErrorMessage', null)
        ->assertSet('successMessage', 'Business Unit berhasil dihapus.');
});

it('menghapus line terakhir membuat kartu mill menampilkan keadaan kosong pada render berikutnya', function () {
    $chain = md127Chain('AkhirLine');
    $mill = $chain['mill'];
    $line = md127Line($mill, 'Line Terakhir');

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class);
    expect(md127LineCountOnCard($component->html(), $mill->name))->toBe(1);

    $component->call('askDelete', 'production-line', $line->id)->call('confirmDelete');

    $after = $component->html();
    expect(md127LineCountOnCard($after, $mill->name))->toBe(0)
        ->and(md127Card($after, $mill->name))->toContain('data-testid="mill-no-lines"');
});

// ═════════════════════════════════════════════════════════════════════════
// MUST SURVIVE from the pre-revamp file
// ═════════════════════════════════════════════════════════════════════════

it('bebas N+1: jumlah query konstan, tidak bertambah seiring jumlah mill maupun line', function () {
    $chain = md127Chain('NPlusOne');
    $mills = [$chain['mill']];
    foreach (range(2, 3) as $index) {
        $mills[] = md127Mill($chain['company'], "Mill N1 {$index}", "BU-N1-{$index}");
    }
    foreach ($mills as $mill) {
        md127Line($mill, 'Line '.$mill->code.' A');
        md127Line($mill, 'Line '.$mill->code.' B');
    }

    expect(BusinessUnit::count())->toBe(3)
        ->and(ProductionLine::count())->toBe(6);

    $small = md127QueryCount(function () {
        Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)->html();
    });

    // Quadruple the mills AND the lines.
    foreach (range(4, 12) as $index) {
        $mill = md127Mill($chain['company'], "Mill N1 {$index}", "BU-N1-{$index}");
        md127Line($mill, 'Line '.$mill->code.' A');
        md127Line($mill, 'Line '.$mill->code.' B');
    }

    expect(BusinessUnit::count())->toBe(12)
        ->and(ProductionLine::count())->toBe(24);

    $large = md127QueryCount(function () {
        Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)->html();
    });

    // Equality, not "fewer than N": eager-loading company.corporate +
    // productionLines with withCount('productionLines') is what keeps this
    // constant, and one query per card would surface here as a difference
    // of nine. The pre-revamp version of this scenario asserted `< 15`
    // for a single fixture; this is strictly stronger.
    expect($large)->toBe($small)
        ->and($large)->toBeLessThan(20);
});

it('akses ditolak: returns 403 and never renders the component for a non-admin session', function (string $role) {
    $chain = md127Chain('Guard');
    md127Line($chain['mill'], 'Line Guard Satu');

    $user = User::factory()->role(UserRole::from($role))->forBusinessUnit($chain['mill'])->create();

    $response = $this->actingAs($user, 'web')->get('/master-data/tree-view');

    $response->assertForbidden();

    // Absence of the screen is asserted over its own data-testids, not over
    // a label phrase: the sidebar label changed to "Struktur Mills" in this
    // revamp, and a phrase assertion on the old label would have kept
    // passing for the wrong reason.
    foreach (['struktur-mills', 'counts-bar', 'search-input', 'mill-card', 'hierarchy-boundary'] as $testid) {
        $response->assertDontSee('data-testid="'.$testid.'"', false);
    }

    // ...and not one row of this screen's data leaked into the 403 page.
    $response->assertDontSee($chain['mill']->code, false);
    $response->assertDontSee('Line Guard Satu', false);
})->with([
    'supervisor' => ['supervisor'],
    'mill management' => ['mill_management'],
    'operator' => ['operator'],
]);

// ═════════════════════════════════════════════════════════════════════════
// SUCCESSORS of the two pre-revamp scenarios whose subject is gone
// ═════════════════════════════════════════════════════════════════════════

it('PENERUS toggleNode(): daftar line setiap kartu mill selalu terlihat tanpa aksi apa pun, dan tidak ada kontrol lipat/buka', function () {
    // The pre-revamp scenario was "toggles a node open then closed again
    // via toggleNode()". Expand/collapse is gone on purpose — it hid data
    // that was already loaded — so this is its equivalent on the new shape:
    // what used to need three expands is visible on the first render, and
    // there is no toggle left to press.
    $chain = md127Chain('Penerus');
    $millA = $chain['mill'];
    $millB = md127Mill($chain['company'], 'Mill Penerus B', 'BU-PN-B');
    md127Line($millA, 'Line Penerus A1');
    md127Line($millA, 'Line Penerus A2');
    md127Line($millB, 'Line Penerus B1');

    $component = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class);
    $html = $component->html();

    // Corporate, Company, mill AND line all visible in ONE render, with no
    // call() in between — the three expands the old tree demanded.
    expect($html)->toContain('data-testid="mill-crumb">Corp Penerus › Company Penerus<')
        ->and(md127Occurrences($html, 'mill-card'))->toBe(2)
        ->and(md127Occurrences($html, 'line-row'))->toBe(3);
    foreach (['Line Penerus A1', 'Line Penerus A2', 'Line Penerus B1'] as $lineName) {
        expect($html)->toContain('data-testid="line-name">'.$lineName.'<');
    }

    // No expand/collapse control of any kind survives, by testid and by
    // class — never by phrase.
    md127AssertNoToggleControl($html);

    // And the methods that drove it are gone from the component, so a
    // leftover caller would fail loudly rather than silently do nothing.
    expect(method_exists(MasterDataTreeView::class, 'toggleNode'))->toBeFalse()
        ->and(method_exists(MasterDataTreeView::class, 'isExpanded'))->toBeFalse()
        ->and(property_exists(MasterDataTreeView::class, 'expanded'))->toBeFalse()
        ->and(property_exists(MasterDataTreeView::class, 'tree'))->toBeFalse();
});

it('PENERUS tautan node ber-filter induk: tautan Kelola dirender tanpa query param filter induk, dan properti filter layar Kelola tetap ada', function () {
    // The pre-revamp scenario asserted each node linked to its Kelola
    // screen WITH a parent filter query param
    // (?filterCorporateId=/?filterCompanyId=/?filterBusinessUnitId=).
    // Managing those levels now happens on THIS page, so the links are
    // plain — but the four Kelola screens' #[Url] filter properties were
    // deliberately NOT removed, and this successor guards both halves.
    $chain = md127Chain('Tautan');
    md127Line($chain['mill'], 'Line Tautan Satu');

    $html = Livewire::actingAs($this->admin)->test(MasterDataTreeView::class)->html();

    foreach ([
        'link-kelola-corporate' => route('master-data.corporates'),
        'link-kelola-company' => route('master-data.companies'),
        'link-kelola-business-unit' => route('master-data.business-units'),
        'link-kelola-production-line' => route('master-data.production-lines'),
        'link-kelola-station' => route('master-data.stations'),
        'link-kelola-machinery' => route('master-data.machinery'),
    ] as $testid => $url) {
        expect($html)->toContain('href="'.$url.'" class="sm-btn sm-btn--ghost sm-btn--sm" data-testid="'.$testid.'"');
    }

    // Tidak satu pun tautan membawa query param filter induk.
    expect($html)->not->toContain('filterCorporateId')
        ->and($html)->not->toContain('filterCompanyId')
        ->and($html)->not->toContain('filterBusinessUnitId');

    // Tetapi properti #[Url] di keempat layar Kelola TIDAK dihapus — ia
    // tetap berguna saat layar itu diakses langsung.
    expect(property_exists(KelolaCompany::class, 'filterCorporateId'))->toBeTrue()
        ->and(property_exists(KelolaBusinessUnit::class, 'filterCompanyId'))->toBeTrue()
        ->and(property_exists(KelolaProductionLine::class, 'filterBusinessUnitId'))->toBeTrue();
});

// ═════════════════════════════════════════════════════════════════════════
// Route-layer guard note — scenarios 7, 25, 36, 46 are component_test: N/A
// ═════════════════════════════════════════════════════════════════════════

it('scenario 7/25/36/46 (guard peran) hanya dapat diuji di lapisan rute dan browser, bukan di komponen', function () {
    // Documented, executable proof of WHY those four scenarios carry
    // `component_test: N/A`: the guard is route middleware, and
    // Livewire::test() mounts the component directly, bypassing it. The
    // component itself holds no role check at all — on purpose, because a
    // second check that could disagree with the first is worse than one
    // clear check.
    $source = file_get_contents(app_path('Livewire/MasterData/MasterDataTreeView.php'));
    // Comments stripped first: the class docblock MENTIONS EnsureRole when
    // explaining where the guard lives, and a naive substring search over
    // the raw file would read that sentence as a second role check.
    $code = implode('', array_map(
        fn (array $token) => in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true) ? '' : ($token[1] ?? ''),
        array_map(fn ($token) => is_array($token) ? $token : [0, $token], token_get_all($source))
    ));

    expect($code)->not->toContain('UserRole')
        ->and($code)->not->toContain('->role')
        ->and($code)->not->toContain('abort(403')
        ->and($code)->not->toContain('EnsureRole')
        ->and($code)->not->toContain('Gate::')
        ->and($code)->not->toContain('authorize(');
    // ...and the explanatory sentence in the docblock is still there, so a
    // future reader is told where the guard actually is.
    expect($source)->toContain('EnsureRole');

    // The route is where it lives, and it really is guarded.
    $routes = file_get_contents(base_path('routes/web.php'));
    $declaration = explode("->name('master-data.tree-view')", $routes)[0];
    expect(substr($declaration, -600))->toContain('role:admin');

    // Belt and braces: an Admin DOES reach it through the real middleware
    // stack, so the 403s asserted above are about the role, not about a
    // broken route.
    $chain = md127Chain('RuteAdmin');
    $this->actingAs($this->admin, 'web')
        ->get('/master-data/tree-view')
        ->assertOk()
        ->assertSee('data-testid="struktur-mills"', false)
        ->assertSee($chain['mill']->code, false);
});
