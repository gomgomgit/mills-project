<?php

/**
 * LaporanStasiunTest (Feature/Livewire) — screen-140--laporan-stasiun-web /
 * usecase-142--laporan-stasiun-web (Pilih Stasiun untuk Laporan).
 *
 * Component tests for App\Livewire\Dashboard\LaporanStasiun, one per
 * test_scenarios entry's `component_test`. Mirrors
 * tests/Feature/Livewire/LaporanSterilizerTest.php's conventions.
 *
 * COMPONENT SHAPE (deliberately minimal): one property, `businessUnitId`,
 * plus mount() and render(). There is no updatedBusinessUnitId() hook —
 * switching mill is handled during render, which is why
 * set('businessUnitId', ...) alone is enough to rebuild the grid.
 *
 * THE ROLE SHAPES THE FIRST STEP, not just the data, and that is what most
 * of these tests are about:
 *   - Supervisor / Mill Management get NO mill picker at all. Their mill is
 *     fixed, so a picker would be a lie — and set('businessUnitId', ...)
 *     from a hand-crafted request changes nothing, because the property is
 *     never read for those roles.
 *   - Admin gets the picker and must use it: no mill, no grid — the page
 *     asks for one instead of rendering an empty grid that reads as
 *     "no data".
 *   - A bound account with no mill is told to contact Admin, and the full
 *     mill list is NEVER offered as a consolation. That is asserted as the
 *     ABSENCE of mill-select and of every other mill's name, not merely as
 *     the presence of the message.
 *
 * ACCESS CONTROL is closed twice over: the route carries
 * 'role:supervisor,mill_management,admin' (EnsureRole -> abort 403 before
 * the component ever mounts), and mount() itself refuses an Operator. The
 * Operator test asserts BOTH, because each guard covers a path the other
 * does not.
 *
 * TILE MARKUP IS PART OF THE CONTRACT: an available station is an <a> with
 * an href; an unbuilt one is a <span class="station-tile disabled"> with
 * aria-disabled="true", the caption "Belum tersedia", NO href and NO
 * wire:click — so pressing it cannot navigate and cannot fire a request.
 */

use App\Enums\UserRole;
use App\Livewire\Dashboard\LaporanStasiun;
use App\Models\BusinessUnit;
use App\Models\StationType;
use App\Models\User;
use Livewire\Livewire;

/**
 * Replaces the whole station_types master with exactly $rows, each
 * [code, name, sort_order]. Safe here because no test in this file creates
 * a station or a period, so nothing holds an FK to the removed rows.
 */
function laporanStasiunComponentMaster(array $rows): void
{
    StationType::query()->delete();

    foreach ($rows as [$code, $name, $sortOrder]) {
        StationType::create([
            'code' => $code,
            'name' => $name,
            'sort_order' => $sortOrder,
            'is_active' => true,
        ]);
    }
}

/**
 * The station codes whose tiles appear in $html, IN MARKUP ORDER — which
 * is the order the user sees them in the grid.
 *
 * @return list<string>
 */
function laporanStasiunTileCodes(string $html): array
{
    preg_match_all('/data-testid="station-tile-([a-z0-9-]+)"/', $html, $matches);

    return $matches[1];
}

/** The markup of the single tile carrying $testid, or '' when absent. */
function laporanStasiunTileMarkup(string $html, string $code): string
{
    // Tiles are either <a ...>...</a> or <span ...>...</span>; matching
    // from the opening tag that carries the testid to its closing tag
    // keeps the assertion on THAT tile rather than on the whole grid.
    $pattern = '/<(a|span)\b[^>]*data-testid="station-tile-'.preg_quote($code, '/').'"[^>]*>.*?<\/\1>/s';

    return preg_match($pattern, $html, $matches) === 1 ? $matches[0] : '';
}

beforeEach(function () {
    $this->businessUnitA = BusinessUnit::factory()->create(['name' => 'Mill Alpha']);
    $this->businessUnitB = BusinessUnit::factory()->create(['name' => 'Mill Beta']);

    $this->supervisor = User::factory()->role(UserRole::Supervisor)->forBusinessUnit($this->businessUnitA)->create();
    $this->millManagement = User::factory()->role(UserRole::MillManagement)->forBusinessUnit($this->businessUnitA)->create();
    $this->operator = User::factory()->role(UserRole::Operator)->forBusinessUnit($this->businessUnitA)->create();
    $this->admin = User::factory()->role(UserRole::Admin)->create(['business_unit_id' => null]);

    // Broken master data: bound to a mill role, bound to no mill.
    $this->supervisorNoMill = User::factory()->role(UserRole::Supervisor)->create(['business_unit_id' => null]);
});

// =====================================================================
// Scenario: "success as Supervisor / Mill Management"
// =====================================================================
it('berhasil: Supervisor melihat mill aktif dan grid stasiun tanpa langkah memilih mill', function () {
    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanStasiun::class)
        ->assertSeeHtml('data-testid="mill-current"')
        ->assertSee('Mill Alpha')
        ->assertSeeHtml('data-testid="station-grid"')
        // Offering a picker they cannot use would be a lie, so there is
        // none.
        ->assertDontSeeHtml('data-testid="mill-select"')
        ->assertSeeHtml('data-testid="station-tile-sterilizer"')
        ->html();

    $codes = laporanStasiunTileCodes($html);
    $expected = StationType::where('code', '<>', 'other')->orderBy('sort_order')->pluck('code')->all();

    expect($codes)->toBe($expected);

    // The one built report: a real link, carrying the mill with it.
    $sterilizer = laporanStasiunTileMarkup($html, 'sterilizer');

    expect($sterilizer)->toContain('href=');
    expect($sterilizer)->toContain('/reports/sterilizer');
    expect($sterilizer)->toContain('business_unit_id='.$this->businessUnitA->id);
    expect($sterilizer)->not->toContain('disabled');

    // Mill Management is bound the same way and must render identically.
    Livewire::actingAs($this->millManagement)
        ->test(LaporanStasiun::class)
        ->assertDontSeeHtml('data-testid="mill-select"')
        ->assertSeeHtml('data-testid="mill-current"')
        ->assertSee('Mill Alpha')
        ->assertSeeHtml('data-testid="station-grid"');
});

// =====================================================================
// Scenario: "success as Admin"
// =====================================================================
it('berhasil: Admin melihat pemilih Mill lebih dulu, grid muncul setelah mill dipilih', function () {
    $component = Livewire::actingAs($this->admin)
        ->test(LaporanStasiun::class)
        ->assertSeeHtml('data-testid="mill-select"')
        // Mill first, station second — the grid is withheld entirely until
        // the mill is settled.
        ->assertDontSeeHtml('data-testid="station-grid"')
        ->assertSeeHtml('data-testid="mill-required-hint"');

    $html = $component->set('businessUnitId', (string) $this->businessUnitA->id)
        ->assertSeeHtml('data-testid="station-grid"')
        ->assertDontSeeHtml('data-testid="mill-required-hint"')
        ->assertSee('Mill Alpha')
        ->html();

    $sterilizer = laporanStasiunTileMarkup($html, 'sterilizer');

    expect($sterilizer)->toContain('href=');
    expect($sterilizer)->toContain('business_unit_id='.$this->businessUnitA->id);
    expect($sterilizer)->not->toContain('disabled');
});

// =====================================================================
// Scenario: "Admin belum memilih mill"
// =====================================================================
it('admin tanpa mill: arahan memilih mill tampil dan grid tidak dirender, tanpa error', function () {
    Livewire::actingAs($this->admin)
        ->test(LaporanStasiun::class)
        ->assertOk()
        ->assertSeeHtml('data-testid="mill-required-hint"')
        ->assertSee('Pilih mill terlebih dahulu untuk menampilkan stasiun.')
        ->assertDontSeeHtml('data-testid="station-grid"')
        ->assertDontSeeHtml('data-testid="station-tile-sterilizer"');
});

// =====================================================================
// Scenario: "belum ada mill di sistem"
// =====================================================================
it('belum ada mill: keterangan master Business Unit kosong, tanpa opsi mill dan tanpa grid', function () {
    // Bound accounts hold an FK to the mills, so they go first.
    User::query()->whereNotNull('business_unit_id')->delete();
    BusinessUnit::query()->delete();

    Livewire::actingAs($this->admin)
        ->test(LaporanStasiun::class)
        ->assertOk()
        ->assertSeeHtml('data-testid="no-business-units"')
        ->assertSee('Master Business Unit masih kosong')
        ->assertDontSeeHtml('data-testid="station-grid"')
        // The picker is still there, but it offers nothing — the hint
        // explains why instead of leaving an empty dropdown unexplained.
        ->assertDontSee('Mill Alpha')
        ->assertDontSee('Mill Beta');
});

// =====================================================================
// Scenario: "akun terikat mill tetapi mill-nya kosong"
// =====================================================================
it('akun tanpa mill: pesan hubungi Admin, tanpa pemilih mill dan tanpa grid (gagal tertutup)', function () {
    Livewire::actingAs($this->supervisorNoMill)
        ->test(LaporanStasiun::class)
        ->assertOk()
        ->assertSeeHtml('data-testid="no-mill-for-account"')
        ->assertSee('Akun Anda belum terhubung ke mill. Hubungi Admin.')
        // FAIL CLOSED: no fallback to the full mill list, so neither the
        // picker nor any other mill's name may appear.
        ->assertDontSeeHtml('data-testid="mill-select"')
        ->assertDontSeeHtml('data-testid="station-grid"')
        ->assertDontSee('Mill Alpha')
        ->assertDontSee('Mill Beta');
});

// =====================================================================
// Scenario: "menekan stasiun yang belum tersedia"
// =====================================================================
it('tile belum tersedia: berkelas disabled, aria-disabled, tanpa href dan tanpa wire:click', function () {
    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanStasiun::class)
        ->assertSeeHtml('data-testid="station-tile-threshing"')
        ->html();

    $threshing = laporanStasiunTileMarkup($html, 'threshing');

    expect($threshing)->not->toBe('');
    expect($threshing)->toContain('station-tile disabled');
    expect($threshing)->toContain('aria-disabled="true"');
    expect($threshing)->toContain('Belum tersedia');
    // Nothing to follow and nothing to fire: pressing it cannot navigate
    // and cannot send a request, which is why there is no error state.
    expect($threshing)->not->toContain('href=');
    expect($threshing)->not->toContain('wire:click');
});

// =====================================================================
// Scenario: "master Jenis Stasiun kosong"
// =====================================================================
it('master kosong: grid dirender tanpa tile apa pun dan keterangannya tampil, tanpa error', function () {
    // Only the historical catch-all remains, and that never gets a tile.
    laporanStasiunComponentMaster([['other', 'Other', 190]]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanStasiun::class)
        ->assertOk()
        ->assertSeeHtml('data-testid="station-grid"')
        ->assertSeeHtml('data-testid="no-station-types"')
        ->assertSee('Master Jenis Stasiun masih kosong')
        ->html();

    expect(laporanStasiunTileCodes($html))->toBe([]);
});

// =====================================================================
// Scenario: "Operator mencoba membuka layar ini"
// =====================================================================
it('operator: rute menolak sebelum mount, dan mount() sendiri juga menolak', function () {
    // Route layer — EnsureRole::forbidden() -> abort(403).
    $response = $this->actingAs($this->operator, 'web')->get('/reports');
    $response->assertForbidden();
    $response->assertDontSee('Laporan Stasiun');

    // Component layer — mount()'s abort_unless(403) covers the component
    // being mounted directly. Livewire's test harness renders the 403
    // error page instead of the component, so assert on that.
    $html = Livewire::actingAs($this->operator)->test(LaporanStasiun::class)->html();

    expect($html)->toContain('Forbidden');
    expect($html)->not->toContain('data-testid="station-grid"');
    expect($html)->not->toContain('data-testid="mill-select"');
    expect($html)->not->toContain('data-testid="mill-current"');
    expect($html)->not->toContain('Mill Alpha');
});

// =====================================================================
// Scenario: "pengguna terikat mill tidak dapat mengganti mill"
// =====================================================================
it('supervisor tidak dapat mengganti mill: set businessUnitId tidak mengubah apa pun', function () {
    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanStasiun::class)
        ->assertDontSeeHtml('data-testid="mill-select"')
        // The property exists on the component, so a hand-crafted request
        // can set it — it is simply never read for this role.
        ->set('businessUnitId', (string) $this->businessUnitB->id)
        ->assertDontSeeHtml('data-testid="mill-select"')
        ->assertSeeHtml('data-testid="mill-current"')
        ->assertSee('Mill Alpha')
        ->assertDontSee('Mill Beta')
        ->html();

    $sterilizer = laporanStasiunTileMarkup($html, 'sterilizer');

    expect($sterilizer)->toContain('business_unit_id='.$this->businessUnitA->id);
    expect($sterilizer)->not->toContain('business_unit_id='.$this->businessUnitB->id);
});

// =====================================================================
// Scenario: "daftar stasiun mengikuti master Jenis Stasiun dan urutan proses produksi"
// =====================================================================
it('master berubah: tile jenis baru ikut tampil dan urutannya mengikuti sort_order terbaru', function () {
    $before = laporanStasiunTileCodes(
        Livewire::actingAs($this->supervisor)->test(LaporanStasiun::class)->html(),
    );

    // The master changes: one type added, and one existing type moved to
    // the front of the process order. No code changes.
    StationType::create([
        'code' => 'stasiun-baru',
        'name' => 'Stasiun Baru',
        'sort_order' => 45,
        'is_active' => true,
    ]);
    StationType::where('code', 'threshing')->update(['sort_order' => 1]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanStasiun::class)
        ->assertSeeHtml('data-testid="station-tile-stasiun-baru"')
        ->assertSee('Stasiun Baru')
        ->html();

    $after = laporanStasiunTileCodes($html);
    $expected = StationType::where('code', '<>', 'other')->orderBy('sort_order')->pluck('code')->all();

    expect($after)->toHaveCount(count($before) + 1);
    expect($after)->toBe($expected);
    expect($after[0])->toBe('threshing');

    // A type the icon map has never heard of still gets a tile — adding a
    // station type must never break this page.
    expect(laporanStasiunTileMarkup($html, 'stasiun-baru'))->toContain('station-tile disabled');
});

// =====================================================================
// Scenario: "jenis stasiun historis 'other' dikecualikan"
// =====================================================================
it("other dikecualikan: tile other tidak dirender, tile jenis lain tetap ada", function () {
    laporanStasiunComponentMaster([
        ['sterilizer', 'Sterilizer', 40],
        ['threshing', 'Threshing', 50],
        ['other', 'Other', 190],
    ]);

    $html = Livewire::actingAs($this->supervisor)
        ->test(LaporanStasiun::class)
        ->assertDontSeeHtml('data-testid="station-tile-other"')
        ->assertSeeHtml('data-testid="station-tile-sterilizer"')
        ->assertSeeHtml('data-testid="station-tile-threshing"')
        ->html();

    expect(laporanStasiunTileCodes($html))->toBe(['sterilizer', 'threshing']);
});

// =====================================================================
// Scenario: "stasiun yang belum dibangun tampil nonaktif, bukan disembunyikan"
// =====================================================================
it('belum dibangun tetap tampil: jumlah tile mengikuti master, hanya sterilizer yang aktif', function () {
    $html = Livewire::actingAs($this->supervisor)->test(LaporanStasiun::class)->html();

    $codes = laporanStasiunTileCodes($html);

    expect($codes)->toHaveCount(StationType::where('code', '<>', 'other')->count());

    $sterilizer = laporanStasiunTileMarkup($html, 'sterilizer');
    $threshing = laporanStasiunTileMarkup($html, 'threshing');

    expect($sterilizer)->toContain('href=');
    expect($sterilizer)->not->toContain('disabled');

    expect($threshing)->toContain('station-tile disabled');
    expect($threshing)->toContain('Belum tersedia');
    expect($threshing)->not->toContain('href=');
});

// =====================================================================
// Scenario: "mill yang ditetapkan terbawa ke layar laporan"
// =====================================================================
it('mill terbawa: setelah Admin berganti mill, href tile mengikuti mill terakhir', function () {
    $component = Livewire::actingAs($this->admin)
        ->test(LaporanStasiun::class)
        ->set('businessUnitId', (string) $this->businessUnitA->id);

    $firstHtml = $component->html();

    expect(laporanStasiunTileMarkup($firstHtml, 'sterilizer'))
        ->toContain('business_unit_id='.$this->businessUnitA->id);

    $secondHtml = $component->set('businessUnitId', (string) $this->businessUnitB->id)
        ->assertSee('Mill Beta')
        ->assertSeeHtml('data-testid="station-grid"')
        ->html();

    $sterilizer = laporanStasiunTileMarkup($secondHtml, 'sterilizer');

    expect($sterilizer)->toContain('business_unit_id='.$this->businessUnitB->id);
    expect($sterilizer)->not->toContain('business_unit_id='.$this->businessUnitA->id);
});
