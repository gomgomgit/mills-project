<?php

/**
 * MasterDataTreeServiceTest — screen-127--master-data-tree-view ("Struktur
 * Mills") / usecase-127--master-data-tree-view.
 *
 * Unit tests for App\Services\MasterDataTreeService::board() / ::counts() /
 * ::corporateRows() / ::companyRows() — the four entry points that replaced
 * getTree() in the 2026-10-06 revamp. Carries usecase-127's SEVEN
 * service-level unit_test_cases, plus the pagination and LIKE-escaping
 * cases the same business_logic steps imply.
 *
 * Same pragmatic deviation from test_strategy.unit_test.mock_policy as
 * every other tests/Unit/Services/*ServiceTest.php in this suite: this
 * service queries exclusively through Eloquent (no injectable repository
 * abstraction exists in this codebase), so mocking the query layer would
 * only assert that the mock was called. It has burned this project before
 * — a mocked query passed while the real SELECT omitted a column — so
 * these tests bind Tests\TestCase + RefreshDatabase (sqlite in-memory, per
 * phpunit.xml) and seed real rows via model factories.
 *
 * NO User is created anywhere in this file ON PURPOSE. UserFactory's
 * `business_unit_id` default cascades a whole BusinessUnit -> Company ->
 * Corporate chain into existence, and every counts() assertion here states
 * an EXACT total — one stray cascaded row would make the expected numbers
 * wrong for a reason that has nothing to do with the subject.
 *
 * SQLITE vs POSTGRESQL. This suite runs on SQLite while production runs
 * PostgreSQL, and the service's filter is deliberately `lower()` + `LIKE`
 * with an explicit `ESCAPE '\'` rather than `ILIKE` (PostgreSQL-only).
 * Two tests below exist specifically to keep both halves of that decision
 * covered: one proves case-insensitivity, one proves a keyword containing
 * a literal `%` does not match everything — which is the only thing that
 * executes the ESCAPE clause.
 */

use App\Models\BusinessUnit;
use App\Models\Company;
use App\Models\Corporate;
use App\Models\ProductionLine;
use App\Services\MasterDataTreeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new MasterDataTreeService;
});

/** perPage the screen actually uses — same 20 as the four Kelola screens. */
const TREE_PER_PAGE = 20;

/**
 * One mill under a freshly built corporate > company chain, with the
 * parents' names/codes stated so breadcrumb assertions can name them.
 *
 * @return array{corporate: Corporate, company: Company, mill: BusinessUnit}
 */
function treeChain(string $corporateName, string $companyName, string $millName, string $millCode): array
{
    $corporate = Corporate::factory()->create(['name' => $corporateName]);
    $company = Company::factory()->create([
        'corporate_id' => $corporate->id,
        'name' => $companyName,
    ]);
    $mill = BusinessUnit::factory()->create([
        'company_id' => $company->id,
        'name' => $millName,
        'code' => $millCode,
    ]);

    return ['corporate' => $corporate, 'company' => $company, 'mill' => $mill];
}

/** Counts the queries one callable issues. */
function countQueries(callable $callback): int
{
    $count = 0;
    DB::listen(function () use (&$count) {
        $count++;
    });

    $callback();

    DB::flushQueryLog();

    return $count;
}

// ─────────────────────────────────────────────────────────────────────────
// unit_test_cases[0]
// ─────────────────────────────────────────────────────────────────────────
it('board() mengembalikan satu kartu per Business Unit, lengkap dengan breadcrumb corporate dan company induknya', function () {
    // given: 2 Corporate, masing-masing 1 Company; Company pertama punya 2
    // mill, Company kedua punya 1 mill.
    $first = treeChain('Corp Satu', 'Company Satu', 'Mill Satu', 'BU-S1');
    $millTwo = BusinessUnit::factory()->create([
        'company_id' => $first['company']->id,
        'name' => 'Mill Dua',
        'code' => 'BU-S2',
    ]);
    $second = treeChain('Corp Dua', 'Company Dua', 'Mill Tiga', 'BU-S3');

    $board = $this->service->board(null, 1, TREE_PER_PAGE);

    // expect: 3 kartu mill; tiap kartu membawa nama corporate dan company
    // induk yang benar.
    expect($board['data'])->toHaveCount(3);
    expect($board['meta']['total'])->toBe(3);

    $cards = collect($board['data'])->keyBy('id');

    expect($cards[$first['mill']->id]['name'])->toBe('Mill Satu')
        ->and($cards[$first['mill']->id]['code'])->toBe('BU-S1')
        ->and($cards[$first['mill']->id]['company_name'])->toBe('Company Satu')
        ->and($cards[$first['mill']->id]['corporate_name'])->toBe('Corp Satu');

    expect($cards[$millTwo->id]['company_name'])->toBe('Company Satu')
        ->and($cards[$millTwo->id]['corporate_name'])->toBe('Corp Satu');

    expect($cards[$second['mill']->id]['company_name'])->toBe('Company Dua')
        ->and($cards[$second['mill']->id]['corporate_name'])->toBe('Corp Dua');
});

// ─────────────────────────────────────────────────────────────────────────
// unit_test_cases[1] — the N+1 proof, at service level.
// ─────────────────────────────────────────────────────────────────────────
it('board() memuat seluruh production line tiap mill tanpa query tambahan per mill', function () {
    // given: 3 mill dengan total 6 production line.
    $small = treeChain('Corp Kecil', 'Company Kecil', 'Mill Kecil 1', 'BU-K1');
    foreach ([2, 3] as $index) {
        BusinessUnit::factory()->create([
            'company_id' => $small['company']->id,
            'name' => "Mill Kecil {$index}",
            'code' => "BU-K{$index}",
        ]);
    }
    foreach (BusinessUnit::all() as $mill) {
        ProductionLine::factory()->forBusinessUnit($mill)->count(2)->create();
    }

    expect(BusinessUnit::count())->toBe(3)
        ->and(ProductionLine::count())->toBe(6);

    $smallCount = countQueries(fn () => $this->service->board(null, 1, TREE_PER_PAGE));

    // ...now triple the mills and triple the lines.
    foreach (range(4, 12) as $index) {
        $mill = BusinessUnit::factory()->create([
            'company_id' => $small['company']->id,
            'name' => "Mill Kecil {$index}",
            'code' => "BU-K{$index}",
        ]);
        ProductionLine::factory()->forBusinessUnit($mill)->count(2)->create();
    }

    expect(BusinessUnit::count())->toBe(12)
        ->and(ProductionLine::count())->toBe(24);

    $largeCount = countQueries(fn () => $board = $this->service->board(null, 1, TREE_PER_PAGE));

    // expect: jumlah query konstan dan tidak bertambah seiring jumlah mill
    // maupun line (bebas N+1). Equality, not "less than N": eager-loading
    // is the whole point, and a per-card query would show up here as a
    // difference of nine.
    expect($largeCount)->toBe($smallCount);

    // And the lines really are loaded — a "constant query count" that
    // loaded nothing would also pass the assertion above.
    $board = $this->service->board(null, 1, TREE_PER_PAGE);
    expect($board['data'])->toHaveCount(12);
    foreach ($board['data'] as $card) {
        expect($card['production_lines'])->toHaveCount(2)
            ->and($card['production_lines_count'])->toBe(2);
    }
});

// ─────────────────────────────────────────────────────────────────────────
// unit_test_cases[2]
// ─────────────────────────────────────────────────────────────────────────
it('counts() menghitung seluruh data, bukan hasil penyaringan', function () {
    // given: 3 Corporate, 4 Company, 5 mill, 8 line; penyaring aktif yang
    // hanya mencocokkan 1 mill.
    [$corporateA, $corporateB, $corporateC] = [
        Corporate::factory()->create(['name' => 'Corp A']),
        Corporate::factory()->create(['name' => 'Corp B']),
        Corporate::factory()->create(['name' => 'Corp C']),
    ];
    $companyA1 = Company::factory()->create(['corporate_id' => $corporateA->id, 'name' => 'Company A1']);
    $companyA2 = Company::factory()->create(['corporate_id' => $corporateA->id, 'name' => 'Company A2']);
    $companyB1 = Company::factory()->create(['corporate_id' => $corporateB->id, 'name' => 'Company B1']);
    Company::factory()->create(['corporate_id' => $corporateC->id, 'name' => 'Company C1']);

    $mills = [];
    foreach ([
        [$companyA1, 'Mill Alpha', 'BU-ALPHA'],
        [$companyA1, 'Mill Bravo', 'BU-BRAVO'],
        [$companyA2, 'Mill Charlie', 'BU-CHARLIE'],
        [$companyB1, 'Mill Delta', 'BU-DELTA'],
        [$companyB1, 'Mill Echo', 'BU-ECHO'],
    ] as [$company, $name, $code]) {
        $mills[] = BusinessUnit::factory()->create([
            'company_id' => $company->id,
            'name' => $name,
            'code' => $code,
        ]);
    }

    // 8 lines spread over the five mills.
    ProductionLine::factory()->forBusinessUnit($mills[0])->count(3)->create();
    ProductionLine::factory()->forBusinessUnit($mills[1])->count(2)->create();
    ProductionLine::factory()->forBusinessUnit($mills[2])->count(2)->create();
    ProductionLine::factory()->forBusinessUnit($mills[3])->count(1)->create();

    $expected = [
        'corporate' => 3,
        'company' => 4,
        'business_unit' => 5,
        'production_line' => 8,
    ];

    expect($this->service->counts())->toBe($expected);

    // A filter narrowing the board down to exactly one mill...
    $filtered = $this->service->board('BU-CHARLIE', 1, TREE_PER_PAGE);
    expect($filtered['data'])->toHaveCount(1);

    // ...changes nothing about the totals. counts() takes no keyword at
    // all, which is the structural guarantee behind business rule 13.
    expect($this->service->counts())->toBe($expected);
});

// ─────────────────────────────────────────────────────────────────────────
// unit_test_cases[3]
// ─────────────────────────────────────────────────────────────────────────
it('penyaring mencocokkan nama maupun kode, tidak peka huruf besar-kecil', function () {
    // given: mill bernama 'Business Unit A' berkode 'BU-A'.
    $chain = treeChain('Corp Saring', 'Company Saring', 'Business Unit A', 'BU-A');
    BusinessUnit::factory()->create([
        'company_id' => $chain['company']->id,
        'name' => 'Mill Lain Sekali',
        'code' => 'BU-ZZZ',
    ]);

    // expect: kata kunci 'bu-a' maupun 'business unit a' keduanya
    // mencocokkan mill itu — pada SEMUA variasi huruf besar-kecil, karena
    // lower() + LIKE (bukan ILIKE) harus berlaku di SQLite maupun
    // PostgreSQL.
    foreach (['bu-a', 'BU-A', 'Bu-A', 'business unit a', 'BUSINESS UNIT A', 'Business Unit A'] as $keyword) {
        $board = $this->service->board($keyword, 1, TREE_PER_PAGE);

        expect($board['data'])->toHaveCount(1)
            ->and($board['data'][0]['id'])->toBe($chain['mill']->id);
    }
});

// ─────────────────────────────────────────────────────────────────────────
// unit_test_cases[4]
// ─────────────────────────────────────────────────────────────────────────
it('mill tetap tampil bila yang cocok adalah salah satu line di dalamnya', function () {
    // given: mill 'Business Unit A' dengan line bernama 'Line Khusus'.
    $chain = treeChain('Corp Line', 'Company Line', 'Business Unit A', 'BU-LINE-A');
    ProductionLine::factory()->forBusinessUnit($chain['mill'])->create(['name' => 'Line Khusus']);
    ProductionLine::factory()->forBusinessUnit($chain['mill'])->create(['name' => 'Line Biasa']);

    $other = BusinessUnit::factory()->create([
        'company_id' => $chain['company']->id,
        'name' => 'Business Unit B',
        'code' => 'BU-LINE-B',
    ]);
    ProductionLine::factory()->forBusinessUnit($other)->create(['name' => 'Line Umum']);

    // expect: kata kunci 'khusus' tetap menampilkan kartu mill itu, dengan
    // line yang cocok ditandai.
    $board = $this->service->board('khusus', 1, TREE_PER_PAGE);

    expect($board['data'])->toHaveCount(1)
        ->and($board['data'][0]['id'])->toBe($chain['mill']->id);

    $lines = collect($board['data'][0]['production_lines'])->keyBy('name');

    // Both lines stay in the card — a card showing only its matching lines
    // would be lying about the mill's contents — but only the match is
    // flagged.
    expect($lines)->toHaveCount(2)
        ->and($lines['Line Khusus']['matches'])->toBeTrue()
        ->and($lines['Line Biasa']['matches'])->toBeFalse()
        ->and($board['data'][0]['production_lines_count'])->toBe(2);
});

// ─────────────────────────────────────────────────────────────────────────
// unit_test_cases[5]
// ─────────────────────────────────────────────────────────────────────────
it('Corporate tanpa Company tetap muncul di daftar ringkas dengan angka anak nol', function () {
    // given: 1 Corporate tanpa Company sama sekali (di samping satu
    // hierarki lengkap, supaya "tidak muncul di papan" berarti sesuatu).
    $orphan = Corporate::factory()->create(['name' => 'Corp Tanpa Anak']);
    $chain = treeChain('Corp Berisi', 'Company Berisi', 'Mill Berisi', 'BU-BERISI');

    $rows = $this->service->corporateRows(null, 1, TREE_PER_PAGE);
    $byId = collect($rows['data'])->keyBy('id');

    // expect: Corporate itu ada di daftar ringkas, companies_count = 0...
    expect($byId)->toHaveCount(2)
        ->and($byId[$orphan->id]['companies_count'])->toBe(0)
        ->and($byId[$chain['corporate']->id]['companies_count'])->toBe(1);

    // ...dan tidak muncul di papan kartu mana pun.
    $board = $this->service->board(null, 1, TREE_PER_PAGE);
    expect(collect($board['data'])->pluck('corporate_name')->all())
        ->not->toContain('Corp Tanpa Anak');

    // Mirror case for Company: a Company with no mill is only visible in
    // companyRows(), with its zero printed.
    $companyNoMill = Company::factory()->create([
        'corporate_id' => $chain['corporate']->id,
        'name' => 'Company Tanpa Mill',
    ]);
    $companies = collect($this->service->companyRows(null, 1, TREE_PER_PAGE)['data'])->keyBy('id');

    expect($companies[$companyNoMill->id]['business_units_count'])->toBe(0)
        ->and($companies[$companyNoMill->id]['corporate_name'])->toBe('Corp Berisi')
        ->and($companies[$chain['company']->id]['business_units_count'])->toBe(1);

    expect(collect($this->service->board(null, 1, TREE_PER_PAGE)['data'])->pluck('company_name')->all())
        ->not->toContain('Company Tanpa Mill');
});

// ─────────────────────────────────────────────────────────────────────────
// unit_test_cases[6]
// ─────────────────────────────────────────────────────────────────────────
it('mill tanpa production line ditandai dari production_lines_count, bukan dari koleksi kosong hasil penyaringan', function () {
    // given: mill punya 2 line, tetapi penyaring aktif tidak mencocokkan
    // satu pun line itu (ia cocok lewat KODE MILL-nya).
    $chain = treeChain('Corp Hitung', 'Company Hitung', 'Mill Berline', 'BU-HITUNG');
    ProductionLine::factory()->forBusinessUnit($chain['mill'])->create(['name' => 'Line Pertama']);
    ProductionLine::factory()->forBusinessUnit($chain['mill'])->create(['name' => 'Line Kedua']);

    $millWithoutLines = BusinessUnit::factory()->create([
        'company_id' => $chain['company']->id,
        'name' => 'Mill Kosong',
        'code' => 'BU-KOSONG',
    ]);

    $filtered = $this->service->board('BU-HITUNG', 1, TREE_PER_PAGE);

    expect($filtered['data'])->toHaveCount(1);

    // expect: mill TIDAK ditandai 'belum ada Production Line' — the count
    // is the real one (2), and the lines are all still there even though
    // none of them matched the keyword.
    expect($filtered['data'][0]['production_lines_count'])->toBe(2)
        ->and($filtered['data'][0]['production_lines'])->toHaveCount(2)
        ->and(collect($filtered['data'][0]['production_lines'])->pluck('matches')->all())
        ->toBe([false, false]);

    // ...and the condition genuinely exists for a mill whose count IS 0.
    $empty = $this->service->board('BU-KOSONG', 1, TREE_PER_PAGE);

    expect($empty['data'])->toHaveCount(1)
        ->and($empty['data'][0]['id'])->toBe($millWithoutLines->id)
        ->and($empty['data'][0]['production_lines_count'])->toBe(0)
        ->and($empty['data'][0]['production_lines'])->toBe([]);
});

// ─────────────────────────────────────────────────────────────────────────
// Pagination — business rule 10 / implementation note 16.
// ─────────────────────────────────────────────────────────────────────────
it('board() memaginasi di basis data pada perPage 20 dan meta-nya menyebut total sebenarnya', function () {
    $chain = treeChain('Corp Besar', 'Company Besar', 'Mill 001', 'BU-001');
    foreach (range(2, 34) as $index) {
        $code = str_pad((string) $index, 3, '0', STR_PAD_LEFT);
        BusinessUnit::factory()->create([
            'company_id' => $chain['company']->id,
            'name' => "Mill {$code}",
            'code' => "BU-{$code}",
        ]);
    }

    $pageOne = $this->service->board(null, 1, TREE_PER_PAGE);
    $pageTwo = $this->service->board(null, 2, TREE_PER_PAGE);

    expect($pageOne['data'])->toHaveCount(20)
        ->and($pageTwo['data'])->toHaveCount(14)
        ->and($pageOne['meta']['total'])->toBe(34)
        ->and($pageTwo['meta']['total'])->toBe(34)
        ->and($pageOne['meta']['per_page'])->toBe(20)
        ->and($pageOne['meta']['total_pages'])->toBe(2);

    // The two pages are disjoint — proof the LIMIT/OFFSET happened in the
    // database rather than the whole board being loaded and sliced.
    $idsOne = collect($pageOne['data'])->pluck('id');
    $idsTwo = collect($pageTwo['data'])->pluck('id');

    expect($idsOne->intersect($idsTwo))->toHaveCount(0)
        ->and($idsOne->merge($idsTwo)->unique())->toHaveCount(34);

    // Production Lines are NEVER paginated: they live inside their mill's
    // card and ride along on the eager-load.
    $withLines = BusinessUnit::factory()->create([
        'company_id' => $chain['company']->id,
        'name' => 'Mill ZZZ Banyak Line',
        'code' => 'BU-ZZZ',
    ]);
    ProductionLine::factory()->forBusinessUnit($withLines)->count(25)->create();

    $lastPage = $this->service->board(null, 2, TREE_PER_PAGE);
    $card = collect($lastPage['data'])->firstWhere('id', $withLines->id);

    expect($card)->not->toBeNull()
        ->and($card['production_lines'])->toHaveCount(25)
        ->and($card['production_lines_count'])->toBe(25);
});

it('penyaringan terjadi di basis data sebelum paginasi, jadi kecocokan di halaman mana pun tetap ditemukan', function () {
    $chain = treeChain('Corp Cari', 'Company Cari', 'Mill 001', 'BU-C001');
    foreach (range(2, 30) as $index) {
        $code = str_pad((string) $index, 3, '0', STR_PAD_LEFT);
        BusinessUnit::factory()->create([
            'company_id' => $chain['company']->id,
            'name' => "Mill {$code}",
            'code' => "BU-C{$code}",
        ]);
    }

    // 'Mill 030' sorts last and therefore sits on page 2 unfiltered...
    $unfilteredPageOne = collect($this->service->board(null, 1, TREE_PER_PAGE)['data'])->pluck('name');
    expect($unfilteredPageOne)->not->toContain('Mill 030');

    // ...yet filtering for it finds it on page 1 of the filtered result,
    // because the WHERE runs before the LIMIT.
    $filtered = $this->service->board('BU-C030', 1, TREE_PER_PAGE);

    expect($filtered['data'])->toHaveCount(1)
        ->and($filtered['data'][0]['name'])->toBe('Mill 030')
        ->and($filtered['meta']['total'])->toBe(1)
        ->and($filtered['meta']['total_pages'])->toBe(1);
});

it('corporateRows() dan companyRows() dipaginasi sendiri-sendiri, terlepas dari papan kartu', function () {
    foreach (range(1, 25) as $index) {
        $code = str_pad((string) $index, 3, '0', STR_PAD_LEFT);
        $corporate = Corporate::factory()->create(['name' => "Corp {$code}"]);
        Company::factory()->create(['corporate_id' => $corporate->id, 'name' => "Company {$code}"]);
    }

    $corporatesOne = $this->service->corporateRows(null, 1, TREE_PER_PAGE);
    $corporatesTwo = $this->service->corporateRows(null, 2, TREE_PER_PAGE);
    $companiesOne = $this->service->companyRows(null, 1, TREE_PER_PAGE);
    $companiesTwo = $this->service->companyRows(null, 2, TREE_PER_PAGE);

    expect($corporatesOne['data'])->toHaveCount(20)
        ->and($corporatesTwo['data'])->toHaveCount(5)
        ->and($corporatesOne['meta']['total'])->toBe(25)
        ->and($companiesOne['data'])->toHaveCount(20)
        ->and($companiesTwo['data'])->toHaveCount(5)
        ->and($companiesOne['meta']['total'])->toBe(25);

    // Asking for page 2 of the Corporate list does not move the Company
    // list — the three positions are independent ($page, $corporatePage,
    // $companyPage).
    expect(collect($corporatesTwo['data'])->pluck('name')->all())
        ->not->toBe(collect($corporatesOne['data'])->pluck('name')->all());
    expect($companiesOne['meta']['page'])->toBe(1)
        ->and($corporatesTwo['meta']['page'])->toBe(2);
});

// ─────────────────────────────────────────────────────────────────────────
// SQLite/PostgreSQL portability — the ESCAPE '\' clause.
// ─────────────────────────────────────────────────────────────────────────
it("kata kunci yang memuat wildcard LIKE ('%' dan '_') dicari apa adanya, bukan mencocokkan semuanya", function () {
    $chain = treeChain('Corp Wildcard', 'Company Wildcard', 'Mill 100% Kapasitas', 'BU-PCT');
    $underscore = BusinessUnit::factory()->create([
        'company_id' => $chain['company']->id,
        'name' => 'Mill Garis_Bawah',
        'code' => 'BU-UND',
    ]);
    $plain = BusinessUnit::factory()->create([
        'company_id' => $chain['company']->id,
        'name' => 'Mill Biasa Saja',
        'code' => 'BU-PLAIN',
    ]);

    expect(BusinessUnit::count())->toBe(3);

    // A bare '%' would match every row if it were passed through to LIKE
    // unescaped — the ESCAPE clause plus escapeLike() is what makes it a
    // literal percent sign instead.
    $percent = $this->service->board('%', 1, TREE_PER_PAGE);
    expect($percent['data'])->toHaveCount(1)
        ->and($percent['data'][0]['id'])->toBe($chain['mill']->id);

    // '_' is LIKE's single-character wildcard; unescaped it would match
    // every name of length >= 1.
    $single = $this->service->board('_', 1, TREE_PER_PAGE);
    expect($single['data'])->toHaveCount(1)
        ->and($single['data'][0]['id'])->toBe($underscore->id);

    // And a keyword mixing a literal wildcard with ordinary text still
    // works as text.
    $mixed = $this->service->board('100% kap', 1, TREE_PER_PAGE);
    expect($mixed['data'])->toHaveCount(1)
        ->and($mixed['data'][0]['id'])->toBe($chain['mill']->id);

    expect(collect($percent['data'])->pluck('id'))->not->toContain($plain->id);
});

it('penyaring mempersempit kedua daftar ringkas lewat induk maupun turunannya', function () {
    $chain = treeChain('Corp Pohon', 'Company Pohon', 'Mill Pohon', 'BU-POHON');
    ProductionLine::factory()->forBusinessUnit($chain['mill'])->create(['name' => 'Line Pohon Satu']);

    $other = treeChain('Corp Terpisah', 'Company Terpisah', 'Mill Terpisah', 'BU-TERPISAH');
    ProductionLine::factory()->forBusinessUnit($other['mill'])->create(['name' => 'Line Terpisah Satu']);

    // Keyword at the LEAF level narrows both summary lists to the branch
    // that owns the leaf.
    $corporates = $this->service->corporateRows('Line Pohon Satu', 1, TREE_PER_PAGE);
    $companies = $this->service->companyRows('Line Pohon Satu', 1, TREE_PER_PAGE);

    expect($corporates['data'])->toHaveCount(1)
        ->and($corporates['data'][0]['id'])->toBe($chain['corporate']->id)
        ->and($companies['data'])->toHaveCount(1)
        ->and($companies['data'][0]['id'])->toBe($chain['company']->id);

    // Keyword at the ROOT level keeps the whole branch visible, including
    // the mill card.
    $byRoot = $this->service->board('Corp Pohon', 1, TREE_PER_PAGE);
    expect($byRoot['data'])->toHaveCount(1)
        ->and($byRoot['data'][0]['id'])->toBe($chain['mill']->id);

    // An empty keyword (and whitespace only) is "no filter at all", not a
    // filter matching nothing.
    foreach ([null, '', '   '] as $blank) {
        expect($this->service->board($blank, 1, TREE_PER_PAGE)['data'])->toHaveCount(2);
        expect($this->service->corporateRows($blank, 1, TREE_PER_PAGE)['data'])->toHaveCount(2);
        expect($this->service->companyRows($blank, 1, TREE_PER_PAGE)['data'])->toHaveCount(2);
    }
});

it('kartu mill tanpa logo membawa inisial maksimal dua huruf, bukan URL logo', function () {
    $chain = treeChain('Corp Inisial', 'Company Inisial', 'Alpha Beta', 'BU-AB');
    BusinessUnit::factory()->create([
        'company_id' => $chain['company']->id,
        'name' => 'Gamma',
        'code' => 'BU-G',
    ]);

    $cards = collect($this->service->board(null, 1, TREE_PER_PAGE)['data'])->keyBy('name');

    expect($cards['Alpha Beta']['initials'])->toBe('AB')
        ->and($cards['Alpha Beta']['logo_url'])->toBeNull()
        ->and($cards['Gamma']['initials'])->toBe('GA')
        ->and($cards['Gamma']['logo_url'])->toBeNull();
});

it('nama panjang dikembalikan utuh — pemotongan bukan tugas service ini', function () {
    $longName = 'Mill '.str_repeat('Panjang ', 20);
    $chain = treeChain('Corp Panjang', 'Company Panjang', trim($longName), 'BU-PANJANG-'.str_repeat('X', 30));

    $card = $this->service->board(null, 1, TREE_PER_PAGE)['data'][0];

    expect($card['name'])->toBe($chain['mill']->name)
        ->and($card['code'])->toBe($chain['mill']->code)
        ->and($card['name'])->not->toContain('...')
        ->and($card['name'])->not->toContain('…');
});

it('counts() nol pada basis data yang benar-benar kosong, bukan hilang', function () {
    expect($this->service->counts())->toBe([
        'corporate' => 0,
        'company' => 0,
        'business_unit' => 0,
        'production_line' => 0,
    ]);

    expect($this->service->board(null, 1, TREE_PER_PAGE)['data'])->toBe([])
        ->and($this->service->corporateRows(null, 1, TREE_PER_PAGE)['data'])->toBe([])
        ->and($this->service->companyRows(null, 1, TREE_PER_PAGE)['data'])->toBe([]);
});
