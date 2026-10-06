/**
 * struktur-mills.spec.ts — screen-127--master-data-tree-view "Struktur Mills"
 * (REVAMP 2026-10-06) / usecase-127, -157, -158, -159.
 *
 * Carries the 44 `browser_test` bindings of this screen's 46 test_scenarios.
 * Scenarios 2 ("Belum ada data master sama sekali") and 20 ("Belum ada calon
 * induk") have NO browser test on purpose: both demand an EMPTY database
 * (zero Corporate), and this database is shared with 86 other specs —
 * emptying it would break all of them. Those two live in the component-test
 * layer (backend/tests/Feature/Livewire/MasterDataTreeViewTest.php) where
 * RefreshDatabase makes a genuinely empty database cheap.
 *
 * ── RUNS STANDALONE ─────────────────────────────────────────────────────
 * After `e2e-web/scripts/prepare-db.sh` this spec needs nothing another
 * spec planted (lesson of commit 9db7a25). Everything it depends on that
 * prepare-db.sh does not guarantee, it plants itself in beforeAll under the
 * `E2E127` prefix and removes again in afterAll, CHILD BEFORE PARENT —
 * deletion is refused while children exist, so cleaning a Corporate before
 * its Company would leave both behind.
 *
 * Server: :8001 (`php artisan serve --env=e2e --port=8001`). NEVER :8000 —
 * that is the developer's own dev server on the dev database.
 * Admin fixture: brtest-admin01 / Passw0rd!  Non-Admin: brtest-supervisor01.
 *
 * ── TWO ADJUSTMENTS TO THE SCENARIOS AS WRITTEN ─────────────────────────
 *
 * 1. SCENARIO 11's assertion "page-wide [aria-expanded] count is 0" can
 *    never hold, in this or any other spec: the app shell's sidebar
 *    hamburger button and the chatbot widget both carry aria-expanded on
 *    EVERY page. It is scoped here to the screen container —
 *    `[data-testid="struktur-mills"] [aria-expanded]` — which is what the
 *    root testid was added for. Every other absence-of-element assertion
 *    in this file is scoped the same way, for the same reason.
 *
 * 2. SCENARIO 1, 11 and 12's "jumlah kartu mill sama dengan angka Mill
 *    pada baris ringkasan" and scenario 11's "locator paginasi berjumlah
 *    0" assume the fixture holds ~5 mills, below perPage 20. It does not:
 *    prepare-db.sh (DatabaseSeeder demo + BrowserTestFixtureSeeder) leaves
 *    19 mills (measured, not estimated), and this spec plants ~15 more,
 *    so the board holds 34-35 during a run. So those assertions are made
 *    DATA-DRIVEN against the real COUNT read from the database: the rule
 *    under test is "pagination renders if and only if the content exceeds
 *    one page, and the counts bar always states the total" — that rule is
 *    asserted in both directions here, which is strictly more than the
 *    one direction the fixture happened to allow. Where a scenario needs
 *    to SEE a specific planted card, the spec narrows the board with the
 *    screen's own filter box first (focusBoard) rather than hoping the
 *    card landed on page 1.
 *
 * Every test installs watchProblems(): a pageerror, a console.error or any
 * application response >= 400 fails it. The 403 scenarios allow 403
 * explicitly, since that status IS the thing they assert.
 */

import { test, expect, type Browser, type Locator, type Page } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { artisan } from './support/backend'
import { watchProblems } from './support/page-health'

const PATH = '/master-data/tree-view'
const ADMIN = 'brtest-admin01'
const NON_ADMIN = 'brtest-supervisor01'
const PREFIX = 'E2E127'

/** A .png by extension whose bytes are plain text — App\Rules\RealImage must refuse it. */
const FAKE_PNG = {
  name: 'bukan-gambar.png',
  mimeType: 'image/png',
  buffer: Buffer.from('ini teks biasa, bukan gambar PNG sungguhan'),
}

interface Fixtures {
  corpInduk: { id: string; name: string; code: string }
  companyInduk: { id: string; name: string; code: string }
  millTanpaLine: { id: string; name: string; code: string }
  corpTanpaCompany: { id: string; name: string }
  companyTanpaMill: { id: string; name: string }
  millPanjang: { id: string; name: string }
  millSatu: { id: string; name: string; code: string }
  millDua: { id: string; name: string; code: string }
  millUbah: { id: string; name: string }
  millWajib: { id: string; name: string }
  millKodeTetap: { id: string; name: string; code: string }
  millLogo: { id: string; name: string }
  millHilang: { id: string; name: string }
  millRebutan: { id: string; name: string }
  millTunggal: { id: string; name: string }
  companyTunggal: { id: string; name: string }
  millSatuLine: { id: string; name: string }
  lineSatuSatunya: { id: string; name: string }
  millPindah: { id: string; name: string }
  linePindah: { id: string; name: string }
  millBersih: { id: string; name: string }
  lineBersih: { id: string; name: string }
  lineBatal: { id: string; name: string }
  millKotor: { id: string; name: string }
  lineKotor: { id: string; name: string }
  lineRahasia: { id: string; name: string }
  /** Names BEYOND the hierarchy boundary — none may appear on this screen. */
  stationRahasia: string
  machineryGroupRahasia: string
  machineryRahasia: string
  corpBatal: { id: string; name: string; code: string }
  corpHapus: { id: string; name: string }
  corpGanda: { id: string; name: string }
  /** Real fixture references, read from the database — never invented. */
  realMillCode: string
  realLineCode: string
  realStationName: string
  realMachineryName: string
  blockedMill: { id: string; name: string; users: number; lines: number; stations: number; periods: number }
}

let fx: Fixtures

async function tinker(code: string): Promise<string> {
  return artisan(['tinker', '--no-interaction', '--execute', code])
}

/** Runs PHP that echoes `OUT=<json>` and returns the parsed value. */
async function tinkerJson<T>(code: string): Promise<T> {
  const out = await tinker(code)
  const line = out.split('\n').find((candidate) => candidate.trim().startsWith('OUT='))

  if (!line) throw new Error(`tinker tidak mengembalikan OUT=: ${out}`)

  return JSON.parse(line.trim().slice('OUT='.length)) as T
}

/** The four counts-bar numbers, as rendered. */
async function readCounts(page: Page): Promise<Record<string, number>> {
  const levels = ['corporate', 'company', 'business-unit', 'production-line']
  const entries: Array<[string, number]> = []

  for (const level of levels) {
    const text = await page.locator(`[data-testid="count-${level}"] .sm-counts__num`).innerText()
    entries.push([level, Number(text.trim())])
  }

  return Object.fromEntries(entries)
}

/** Real COUNT(*) over the four hierarchy tables. */
async function databaseCounts(): Promise<Record<string, number>> {
  return tinkerJson(`
    echo 'OUT=' . json_encode([
      'corporate' => App\\Models\\Corporate::count(),
      'company' => App\\Models\\Company::count(),
      'business-unit' => App\\Models\\BusinessUnit::count(),
      'production-line' => App\\Models\\ProductionLine::count(),
    ]);
  `)
}

/** Types into the filter box and waits for the Livewire round-trip it triggers. */
async function filterBoard(page: Page, keyword: string): Promise<void> {
  await Promise.all([
    page.waitForResponse((response) => response.url().includes('/livewire/update')),
    page.locator('[data-testid="search-input"]').fill(keyword),
  ])
  await expect(page.locator('[data-testid="search-input"]')).toHaveValue(keyword)
}

/**
 * Narrows the board to this spec's own planted rows before asserting on
 * one of their cards. The board paginates at 20 and the fixture already
 * holds 19 mills (measured), so "the card is on screen" is only deterministic behind
 * a filter — and narrowing is what the filter box is for.
 */
async function focusBoard(page: Page, keyword: string = PREFIX): Promise<void> {
  await filterBoard(page, keyword)
  await expect(page.locator('[data-testid="match-note"]')).toBeVisible()
}

/** The mill card whose name element reads exactly `name`. */
function millCard(page: Page, name: string): Locator {
  return page.locator('[data-testid="mill-card"]').filter({
    has: page.locator('[data-testid="mill-name"]', { hasText: new RegExp(`^${escapeRegex(name)}$`) }),
  })
}

/** A summary-list row (Corporate or Company) containing this exact name cell. */
function summaryRow(page: Page, testid: 'corporate-row' | 'company-row', name: string): Locator {
  return page.locator(`[data-testid="${testid}"]`).filter({
    has: page.locator('span', { hasText: new RegExp(`^${escapeRegex(name)}$`) }),
  })
}

/** A production-line row inside a given mill card. */
function lineRow(page: Page, millName: string, lineName: string): Locator {
  return millCard(page, millName).locator('[data-testid="line-row"]').filter({
    has: page.locator('[data-testid="line-name"]', { hasText: new RegExp(`^${escapeRegex(lineName)}$`) }),
  })
}

function escapeRegex(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

/** The "N Production Line" chip value on one mill card. */
async function lineCountOnCard(page: Page, millName: string): Promise<number> {
  const chip = millCard(page, millName).locator('[data-testid="mill-line-count"]')
  await expect(chip).toBeVisible()
  const text = await chip.innerText()

  return Number(text.trim().split(' ')[0])
}

/** Clicks a control and waits for the Livewire round-trip it triggers. */
async function clickLive(locator: Locator): Promise<void> {
  const page = locator.page()
  await Promise.all([
    page.waitForResponse((response) => response.url().includes('/livewire/update')),
    locator.click(),
  ])
}

/** Everything the screen would render as a pagination control. */
function paginationControls(page: Page): Locator {
  // Scoped to the screen container deliberately: the app shell carries its
  // own nav elements, and this assertion is about THIS screen's pagination.
  return page.locator(
    '[data-testid="struktur-mills"] [data-testid="pagination"], '
    + '[data-testid="struktur-mills"] [data-testid="pagination-corporate"], '
    + '[data-testid="struktur-mills"] [data-testid="pagination-company"], '
    + '[data-testid="struktur-mills"] .sm-pager, '
    + '[data-testid="struktur-mills"] nav[aria-label*="Pagination"]',
  )
}

/** Everything the screen would render as an expand/collapse control. */
function toggleControls(page: Page): Locator {
  // SCOPED — see the file header, adjustment (1): the shell's hamburger
  // button and the chatbot widget both carry aria-expanded on every page,
  // so a page-wide count of 0 is impossible by construction.
  return page.locator(
    '[data-testid="struktur-mills"] [aria-expanded], '
    + '[data-testid="struktur-mills"] [data-testid="node-toggle"], '
    + '[data-testid="struktur-mills"] .sm-node-toggle',
  )
}

async function expectNoHorizontalScroll(page: Page): Promise<void> {
  const overflow = await page.evaluate(
    () => document.body.scrollWidth - document.documentElement.clientWidth,
  )
  expect(overflow).toBeLessThanOrEqual(1)
}

async function gotoBoard(page: Page, username: string = ADMIN): Promise<void> {
  await login(page, username, PASSWORD)
  await page.goto(PATH)
  await expect(page.locator('[data-testid="struktur-mills"]')).toBeVisible()
  await expect(page.locator('[data-testid="counts-bar"]')).toBeVisible()
}

/** A second, independent browser context logged in as the same Admin. */
async function secondAdminBoard(browser: Browser): Promise<{ page: Page; close: () => Promise<void> }> {
  const context = await browser.newContext()
  const page = await context.newPage()
  await gotoBoard(page)

  return { page, close: () => context.close() }
}

test.describe('Struktur Mills (screen-127)', () => {
  let problems: string[] = []

  test.beforeAll(async () => {
    test.setTimeout(180_000)

    fx = await tinkerJson<Fixtures>(`
      $corpInduk = App\\Models\\Corporate::factory()->create(['name' => '${PREFIX} Corp Induk', 'corporate_code' => '${PREFIX}-CORP-01', 'email' => null, 'website' => null]);
      $companyInduk = App\\Models\\Company::factory()->create(['corporate_id' => $corpInduk->id, 'name' => '${PREFIX} Company Induk', 'company_code' => '${PREFIX}-CMP-01']);

      $mill = function (string $name, string $code) use ($companyInduk) {
          return App\\Models\\BusinessUnit::factory()->create(['company_id' => $companyInduk->id, 'name' => $name, 'code' => $code]);
      };

      $millTanpaLine = $mill('${PREFIX} Mill Tanpa Line', '${PREFIX}-BU-TL');
      $corpTanpaCompany = App\\Models\\Corporate::factory()->create(['name' => '${PREFIX} Corp Tanpa Company', 'corporate_code' => '${PREFIX}-CORP-02', 'email' => null, 'website' => null]);
      $companyTanpaMill = App\\Models\\Company::factory()->create(['corporate_id' => $corpInduk->id, 'name' => '${PREFIX} Company Tanpa Mill', 'company_code' => '${PREFIX}-CMP-02']);

      $panjang = '${PREFIX} ' . str_repeat('Namapanjang ', 10);
      $panjang = substr($panjang . str_repeat('Z', 130), 0, 127);
      $millPanjang = $mill($panjang, '${PREFIX}-BU-PJ');

      $millSatu = $mill('${PREFIX} Mill Satu', '${PREFIX}-BU-1');
      $millDua = $mill('${PREFIX} Mill Dua', '${PREFIX}-BU-2');
      $millUbah = $mill('${PREFIX} Mill Ubah', '${PREFIX}-BU-UB');
      $millWajib = $mill('${PREFIX} Mill Wajib', '${PREFIX}-BU-WJ');
      $millKodeTetap = $mill('${PREFIX} Mill Kode Tetap', '${PREFIX}-BU-KT');
      $millHilang = $mill('${PREFIX} Mill Hilang', '${PREFIX}-BU-HL');
      $millRebutan = $mill('${PREFIX} Mill Rebutan', '${PREFIX}-BU-RB');

      // Mill WITH a real logo on disk — every fixture mill is logo-less, so
      // scenario 32 ("logo lama tetap terpakai") has to plant one.
      $logoPath = 'business-unit-logos/${PREFIX.toLowerCase()}-logo.png';
      Illuminate\\Support\\Facades\\Storage::disk(App\\Services\\BusinessUnitService::LOGO_DISK)->put(
          $logoPath,
          base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==')
      );
      $millLogo = $mill('${PREFIX} Mill Logo', '${PREFIX}-BU-LG');
      $millLogo->update(['logo' => $logoPath]);

      // Company with EXACTLY one mill (scenario 43).
      $companyTunggal = App\\Models\\Company::factory()->create(['corporate_id' => $corpInduk->id, 'name' => '${PREFIX} Company Tunggal', 'company_code' => '${PREFIX}-CMP-03']);
      $millTunggal = App\\Models\\BusinessUnit::factory()->create(['company_id' => $companyTunggal->id, 'name' => '${PREFIX} Mill Tunggal', 'code' => '${PREFIX}-BU-TG']);

      // Mill with EXACTLY one clean line (scenario 44).
      $millSatuLine = $mill('${PREFIX} Mill Satu Line', '${PREFIX}-BU-SL');
      $lineSatuSatunya = App\\Models\\ProductionLine::factory()->forBusinessUnit($millSatuLine)->create(['name' => '${PREFIX} Line Satu-satunya']);

      // Line to move between two mills (scenario 28).
      $millPindah = $mill('${PREFIX} Mill Pindah Tujuan', '${PREFIX}-BU-PT');
      $linePindah = App\\Models\\ProductionLine::factory()->forBusinessUnit($millUbah)->create(['name' => '${PREFIX} Line Pindah']);

      // Line whose stations are CLEAN (scenario 40) + a line only ever
      // cancelled on (scenario 39), on their own mill.
      $millBersih = $mill('${PREFIX} Mill Bersih', '${PREFIX}-BU-BS');
      $lineBersih = App\\Models\\ProductionLine::factory()->forBusinessUnit($millBersih)->create(['name' => '${PREFIX} Line Bersih']);
      App\\Models\\Station::factory()->forProductionLine($lineBersih)->weighbridge()->create(['name' => '${PREFIX} Station Bersih 1']);
      App\\Models\\Station::factory()->forProductionLine($lineBersih)->grading()->create(['name' => '${PREFIX} Station Bersih 2']);
      $lineBatal = App\\Models\\ProductionLine::factory()->forBusinessUnit($millBersih)->create(['name' => '${PREFIX} Line Batal']);

      // Line whose station ALREADY has a production record (scenario 41).
      $millKotor = $mill('${PREFIX} Mill Kotor', '${PREFIX}-BU-KR');
      $lineKotor = App\\Models\\ProductionLine::factory()->forBusinessUnit($millKotor)->create(['name' => '${PREFIX} Line Kotor']);
      $stationKotor = App\\Models\\Station::factory()->forProductionLine($lineKotor)->weighbridge()->create(['name' => '${PREFIX} Station Kotor']);
      // created_by pinned to an EXISTING account on purpose:
      // WeighbridgeRecordFactory defaults it to User::factory(), whose own
      // business_unit_id default cascades a whole BusinessUnit -> Company ->
      // Corporate chain into existence under faker names this spec's
      // prefix-based cleanup can never find again.
      $author = App\\Models\\User::where('username', '${ADMIN}')->firstOrFail();
      App\\Models\\WeighbridgeRecord::factory()->forStation($stationKotor)->create(['created_by' => $author->id]);

      // A Station + Machinery Group + Machinery under their own line, named
      // distinctively: scenario 10 asserts the screen stops at Production
      // Line, and a fixture name that happens to be a SUBSTRING of a
      // rendered Production Line name ("Boiler Room" inside "PL Tanpa
      // Boiler Room") would fail that assertion for no real reason.
      $lineRahasia = App\\Models\\ProductionLine::factory()->forBusinessUnit($millKotor)->create(['name' => '${PREFIX} Line Rahasia']);
      $stationRahasia = App\\Models\\Station::factory()->forProductionLine($lineRahasia)->create(['name' => '${PREFIX} Station Rahasia Zulu']);
      $groupRahasia = App\\Models\\MachineryGroup::factory()->forStation($stationRahasia)->create(['group_code' => '${PREFIX}-MG-YANKEE']);
      $machineryRahasia = App\\Models\\Machinery::factory()->create([
          'station_id' => $stationRahasia->id,
          'production_line_id' => $lineRahasia->id,
          'machinery_group_id' => $groupRahasia->id,
          'name' => '${PREFIX} Mesin Rahasia Xray',
      ]);

      $corpBatal = App\\Models\\Corporate::factory()->create(['name' => '${PREFIX} Corp Batal', 'corporate_code' => '${PREFIX}-CORP-03', 'email' => null, 'website' => null]);
      $corpHapus = App\\Models\\Corporate::factory()->create(['name' => '${PREFIX} Corp Hapus', 'corporate_code' => '${PREFIX}-CORP-04', 'email' => null, 'website' => null]);
      $corpGanda = App\\Models\\Corporate::factory()->create(['name' => '${PREFIX} Corp Ganda', 'corporate_code' => '${PREFIX}-CORP-05', 'email' => null, 'website' => null]);

      // REAL fixture references — read from the database, never invented
      // (the Phase-2 mock's BU-A / CORP-SN / CMP-SN-1 / PL-A-01 are made up).
      $realMill = App\\Models\\BusinessUnit::where('name', 'NOT LIKE', '${PREFIX}%')->orderBy('name')->firstOrFail();
      $realLine = App\\Models\\ProductionLine::whereNotNull('code')->where('name', 'NOT LIKE', '${PREFIX}%')->orderBy('name')->firstOrFail();
      $realStation = App\\Models\\Station::where('name', 'NOT LIKE', '${PREFIX}%')->orderBy('name')->firstOrFail();
      $realMachinery = App\\Models\\Machinery::orderBy('name')->firstOrFail();

      // The mill with the MOST blockers — scenario 38 must be refused.
      $blocked = App\\Models\\BusinessUnit::where('name', 'NOT LIKE', '${PREFIX}%')
          ->withCount(['users', 'productionLines', 'stations'])
          ->orderByDesc('production_lines_count')
          ->firstOrFail();

      $row = fn ($model, array $extra = []) => array_merge([
          'id' => $model->id,
          'name' => $model->name,
          'code' => $model->code ?? $model->corporate_code ?? $model->company_code,
      ], $extra);

      echo 'OUT=' . json_encode([
          'corpInduk' => $row($corpInduk),
          'companyInduk' => $row($companyInduk),
          'millTanpaLine' => $row($millTanpaLine),
          'corpTanpaCompany' => $row($corpTanpaCompany),
          'companyTanpaMill' => $row($companyTanpaMill),
          'millPanjang' => $row($millPanjang),
          'millSatu' => $row($millSatu),
          'millDua' => $row($millDua),
          'millUbah' => $row($millUbah),
          'millWajib' => $row($millWajib),
          'millKodeTetap' => $row($millKodeTetap),
          'millLogo' => $row($millLogo),
          'millHilang' => $row($millHilang),
          'millRebutan' => $row($millRebutan),
          'millTunggal' => $row($millTunggal),
          'companyTunggal' => $row($companyTunggal),
          'millSatuLine' => $row($millSatuLine),
          'lineSatuSatunya' => $row($lineSatuSatunya),
          'millPindah' => $row($millPindah),
          'linePindah' => $row($linePindah),
          'millBersih' => $row($millBersih),
          'lineBersih' => $row($lineBersih),
          'lineBatal' => $row($lineBatal),
          'millKotor' => $row($millKotor),
          'lineKotor' => $row($lineKotor),
          'lineRahasia' => $row($lineRahasia),
          'stationRahasia' => $stationRahasia->name,
          'machineryGroupRahasia' => $groupRahasia->group_code,
          'machineryRahasia' => $machineryRahasia->name,
          'corpBatal' => $row($corpBatal),
          'corpHapus' => $row($corpHapus),
          'corpGanda' => $row($corpGanda),
          'realMillCode' => $realMill->code,
          'realLineCode' => $realLine->code,
          'realStationName' => $realStation->name,
          'realMachineryName' => $realMachinery->name,
          'blockedMill' => [
              'id' => $blocked->id,
              'name' => $blocked->name,
              'users' => (int) $blocked->users_count,
              'lines' => (int) $blocked->production_lines_count,
              'stations' => (int) $blocked->stations_count,
              'periods' => App\\Models\\Period::where('business_unit_id', $blocked->id)->count(),
          ],
      ]);
    `)
  })

  test.afterAll(async () => {
    // CHILD BEFORE PARENT, strictly: every level refuses deletion while it
    // still has children, so the order below is the only one that works.
    // Lines created through the UI also carry 18 auto-provisioned stations.
    await tinker(`
      $lineIds = App\\Models\\ProductionLine::where('name', 'LIKE', '${PREFIX}%')
          ->orWhere('code', 'LIKE', '${PREFIX}%')
          ->pluck('id');
      $millIds = App\\Models\\BusinessUnit::where('name', 'LIKE', '${PREFIX}%')
          ->orWhere('code', 'LIKE', '${PREFIX}%')
          ->pluck('id');
      $stationIds = App\\Models\\Station::whereIn('production_line_id', $lineIds)
          ->orWhereIn('business_unit_id', $millIds)
          ->pluck('id');

      App\\Models\\WeighbridgeRecord::whereIn('station_id', $stationIds)->delete();
      App\\Models\\Machinery::whereIn('station_id', $stationIds)->delete();
      App\\Models\\MachineryGroup::whereIn('station_id', $stationIds)->delete();
      App\\Models\\Station::whereIn('id', $stationIds)->delete();
      App\\Models\\Period::whereIn('business_unit_id', $millIds)->delete();
      App\\Models\\ProductionLine::whereIn('id', $lineIds)->delete();
      App\\Models\\BusinessUnit::whereIn('id', $millIds)->delete();
      App\\Models\\Company::where('name', 'LIKE', '${PREFIX}%')->orWhere('company_code', 'LIKE', '${PREFIX}%')->delete();
      App\\Models\\Corporate::where('name', 'LIKE', '${PREFIX}%')->orWhere('corporate_code', 'LIKE', '${PREFIX}%')->delete();

      Illuminate\\Support\\Facades\\Storage::disk(App\\Services\\BusinessUnitService::LOGO_DISK)
          ->delete('business-unit-logos/${PREFIX.toLowerCase()}-logo.png');

      echo 'OUT=' . json_encode(['sisaMill' => App\\Models\\BusinessUnit::where('name', 'LIKE', '${PREFIX}%')->count()]);
    `)
  })

  test.afterEach(() => {
    expect(problems).toEqual([])
  })

  // ═══════════════════════════════════════════════════════════════════════
  // usecase-127--master-data-tree-view
  // ═══════════════════════════════════════════════════════════════════════

  test('1. Lihat Seluruh Hierarki Master Data dalam Satu Halaman — success', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)

    const shown = await readCounts(page)
    const real = await databaseCounts()

    // Baris ringkasan menampilkan empat angka, dan mereka adalah TOTAL
    // seluruh data.
    expect(shown).toEqual(real)

    // Jumlah kartu mill = angka Mill, kecuali bila papan memang melebihi
    // satu halaman — lihat adjustment (2) di kepala berkas.
    const perPage = 20
    const expectedCards = Math.min(real['business-unit'], perPage)
    await expect(page.locator('[data-testid="mill-card"]')).toHaveCount(expectedCards)

    // Setiap kartu menampilkan nama, kode, dan breadcrumb induknya.
    const cards = page.locator('[data-testid="mill-card"]')
    for (let index = 0; index < expectedCards; index++) {
      const card = cards.nth(index)
      await expect(card.locator('[data-testid="mill-name"]')).not.toHaveText('')
      await expect(card.locator('[data-testid="mill-code"]')).not.toHaveText('')
      await expect(card.locator('[data-testid="mill-crumb"]')).toContainText('›')
    }

    // Baris Production Line sudah terlihat TANPA klik apa pun.
    await focusBoard(page, fx.millBersih.name)
    await expect(lineRow(page, fx.millBersih.name, fx.lineBersih.name)).toBeVisible()
    await filterBoard(page, '')

    // Kedua tabel ringkas terlihat.
    await expect(page.locator('[data-testid="corporate-table"]')).toBeVisible()
    await expect(page.locator('[data-testid="company-table"]')).toBeVisible()
    await expect(page.locator('[data-testid="corporate-row"]').first()).toBeVisible()
    await expect(page.locator('[data-testid="company-row"]').first()).toBeVisible()

    // Tautan Kelola Station / Machinery punya href yang benar.
    await expect(page.locator('[data-testid="link-kelola-station"]'))
      .toHaveAttribute('href', /\/master-data\/stations$/)
    await expect(page.locator('[data-testid="link-kelola-machinery"]'))
      .toHaveAttribute('href', /\/master-data\/machinery$/)

    // Tidak ada gulir horizontal pada <body>.
    await expectNoHorizontalScroll(page)
  })

  // Scenario 2 — TIDAK ADA browser test, disengaja (lihat kepala berkas).

  test('3. Lihat Seluruh Hierarki Master Data dalam Satu Halaman — Mill tanpa Production Line', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)
    await focusBoard(page, fx.millTanpaLine.name)

    const card = millCard(page, fx.millTanpaLine.name)
    await expect(card).toHaveCount(1)
    await expect(card).toBeVisible()

    // Keterangan 'belum ada Production Line' dan tombol tambah line.
    const note = card.locator('[data-testid="mill-no-lines"]')
    await expect(note).toBeVisible()
    await expect(card.locator(`[data-testid="add-line-${fx.millTanpaLine.id}"]`)).toBeVisible()
    expect(await lineCountOnCard(page, fx.millTanpaLine.name)).toBe(0)

    // Kartu itu tidak berisi ruang kosong tanpa teks — blok keterangannya
    // punya tinggi > 0 dan teks yang terbaca.
    const box = await note.boundingBox()
    expect(box).not.toBeNull()
    expect(box!.height).toBeGreaterThan(0)
    expect((await note.innerText()).trim().length).toBeGreaterThan(0)
    await expect(card.locator('[data-testid="line-row"]')).toHaveCount(0)
  })

  test('4. Lihat Seluruh Hierarki Master Data dalam Satu Halaman — Corporate atau Company tanpa anak', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)
    await focusBoard(page, PREFIX)

    // Corporate tanpa Company: terlihat di tabel ringkasnya, angka '0'.
    const corpRow = summaryRow(page, 'corporate-row', fx.corpTanpaCompany.name)
    await expect(corpRow).toHaveCount(1)
    await expect(corpRow.locator('[data-testid="corporate-company-count"]')).toHaveText('0')

    // Company tanpa mill: terlihat, angka '0', dengan Corporate induknya.
    const companyRow = summaryRow(page, 'company-row', fx.companyTanpaMill.name)
    await expect(companyRow).toHaveCount(1)
    await expect(companyRow.locator('[data-testid="company-mill-count"]')).toHaveText('0')
    await expect(companyRow).toContainText(fx.corpInduk.name)

    // Keduanya tidak muncul sebagai kartu mill...
    await expect(millCard(page, fx.corpTanpaCompany.name)).toHaveCount(0)
    await expect(millCard(page, fx.companyTanpaMill.name)).toHaveCount(0)

    // ...dan tidak muncul di breadcrumb kartu mana pun.
    const crumbs = await page.locator('[data-testid="mill-crumb"]').allInnerTexts()
    for (const crumb of crumbs) {
      expect(crumb).not.toContain(fx.corpTanpaCompany.name)
      expect(crumb).not.toContain(fx.companyTanpaMill.name)
    }

    // Tombol ubah dan hapus pada barisnya dapat diklik.
    await expect(corpRow.locator(`[data-testid="edit-corporate-${fx.corpTanpaCompany.id}"]`)).toBeEnabled()
    await expect(corpRow.locator(`[data-testid="delete-corporate-${fx.corpTanpaCompany.id}"]`)).toBeEnabled()
    await expect(companyRow.locator(`[data-testid="edit-company-${fx.companyTanpaMill.id}"]`)).toBeEnabled()
    await expect(companyRow.locator(`[data-testid="delete-company-${fx.companyTanpaMill.id}"]`)).toBeEnabled()
  })

  test('5. Lihat Seluruh Hierarki Master Data dalam Satu Halaman — Menyaring dengan kata kunci', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)

    const before = await readCounts(page)
    const cardsBefore = await page.locator('[data-testid="mill-card"]').count()
    const corporatesBefore = await page.locator('[data-testid="corporate-row"]').count()

    // Kode SEBENARNYA salah satu mill fixture (dibaca dari basis data).
    await filterBoard(page, fx.realMillCode)
    await expect(page.locator('[data-testid="mill-card"]')).toHaveCount(1)
    await expect(page.locator('[data-testid="mill-code"]')).toHaveText(fx.realMillCode)

    // Kedua tabel ringkas ikut menyempit.
    const corporatesAfter = await page.locator('[data-testid="corporate-row"]').count()
    expect(corporatesAfter).toBeLessThan(corporatesBefore)
    expect(corporatesAfter).toBeGreaterThan(0)
    expect(await page.locator('[data-testid="company-row"]').count()).toBeGreaterThan(0)

    // Keempat angka baris ringkasan SAMA PERSIS dengan sebelum menyaring.
    expect(await readCounts(page)).toEqual(before)

    // Keterangan jumlah yang cocok muncul di samping angka total.
    await expect(page.locator('[data-testid="match-note"]')).toBeVisible()

    // Parameter ?search pada URL terisi saat menyaring (#[Url]).
    await expect.poll(() => new URL(page.url()).searchParams.get('search')).toBe(fx.realMillCode)

    // Tidak peka huruf besar-kecil.
    await filterBoard(page, fx.realMillCode.toLowerCase())
    await expect(page.locator('[data-testid="mill-card"]')).toHaveCount(1)
    await expect(page.locator('[data-testid="mill-code"]')).toHaveText(fx.realMillCode)

    // Disaring dengan NAMA LINE: kartu mill pemiliknya tetap terender dan
    // line yang cocok DITANDAI.
    await filterBoard(page, fx.lineBersih.name)
    const ownerCard = millCard(page, fx.millBersih.name)
    await expect(ownerCard).toHaveCount(1)
    await expect(lineRow(page, fx.millBersih.name, fx.lineBersih.name)
      .locator('[data-testid="line-match"]')).toBeVisible()
    expect(await readCounts(page)).toEqual(before)

    // Sesudah dikosongkan, seluruh kartu dan kedua daftar kembali utuh dan
    // ?search hilang/kosong lagi.
    await filterBoard(page, '')
    await expect(page.locator('[data-testid="mill-card"]')).toHaveCount(cardsBefore)
    await expect(page.locator('[data-testid="corporate-row"]')).toHaveCount(corporatesBefore)
    await expect(page.locator('[data-testid="match-note"]')).toHaveCount(0)
    await expect.poll(() => new URL(page.url()).searchParams.get('search') ?? '').toBe('')
  })

  test('6. Lihat Seluruh Hierarki Master Data dalam Satu Halaman — Penyaring tidak mencocokkan apa pun', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)

    const before = await readCounts(page)
    const cardsBefore = await page.locator('[data-testid="mill-card"]').count()

    await filterBoard(page, 'zzz-tidak-ada-apa-pun')

    // Blok 'tidak ada yang cocok' terlihat dan MEMUAT kata kunci yang
    // diketik.
    const noMatch = page.locator('[data-testid="mill-no-match"]')
    await expect(noMatch).toBeVisible()
    await expect(noMatch).toContainText('zzz-tidak-ada-apa-pun')

    // Tidak ada kartu mill terlihat.
    await expect(page.locator('[data-testid="mill-card"]')).toHaveCount(0)

    // Keempat angka identik dengan nilai sebelum menyaring.
    expect(await readCounts(page)).toEqual(before)

    // Mengklik kontrol pengosong mengembalikan seluruh kartu dan
    // mengosongkan kotak penyaring.
    await clickLive(page.locator('[data-testid="clear-search-mill"]'))
    await expect(page.locator('[data-testid="mill-card"]')).toHaveCount(cardsBefore)
    await expect(page.locator('[data-testid="search-input"]')).toHaveValue('')
    await expect(page.locator('[data-testid="mill-no-match"]')).toHaveCount(0)
  })

  test('7. Lihat Seluruh Hierarki Master Data dalam Satu Halaman — Peran selain Admin mencoba membuka halaman', async ({ page }) => {
    problems = watchProblems(page, { allowStatus: [403] })
    await login(page, NON_ADMIN, PASSWORD)

    // (a) Butir menu Struktur Mills tidak ada di sidebar peran itu.
    await page.goto('/dashboard')
    await expect(page.locator(`nav a[href$="${PATH}"]`)).toHaveCount(0)

    // (b) Kunjungan langsung lewat alamat.
    const response = await page.goto(PATH)
    expect(response?.status()).toBe(403)

    // Halaman yang tampil adalah halaman terlarang dan TIDAK memuat satu
    // pun penanda layar ini — komponen tidak pernah mount.
    for (const testid of ['struktur-mills', 'counts-bar', 'mill-card', 'search-input', 'hierarchy-boundary']) {
      await expect(page.locator(`[data-testid="${testid}"]`)).toHaveCount(0)
    }
  })

  test('8. Lihat Seluruh Hierarki Master Data dalam Satu Halaman — Nama atau kode sangat panjang', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)
    await focusBoard(page, `${PREFIX}-BU-PJ`)

    const card = page.locator('[data-testid="mill-card"]').first()
    await expect(card).toBeVisible()
    const nameElement = card.locator('[data-testid="mill-name"]')

    // textContent SAMA dengan nama utuh, dan title-nya juga nama utuh —
    // dapat dibaca serta disalin.
    await expect(nameElement).toHaveText(fx.millPanjang.name)
    await expect(nameElement).toHaveAttribute('title', fx.millPanjang.name)

    // scrollWidth > clientWidth: bukti pemotongan NYATA terjadi di CSS.
    const metrics = await nameElement.evaluate((element) => ({
      scrollWidth: element.scrollWidth,
      clientWidth: element.clientWidth,
      textOverflow: getComputedStyle(element).textOverflow,
    }))
    expect(metrics.scrollWidth).toBeGreaterThan(metrics.clientWidth)
    expect(metrics.textOverflow).toBe('ellipsis')

    // Lebar kartu itu sama dengan lebar kartu tetangganya — papan tidak
    // melar. (Keduanya terlihat bersama di bawah penyaring E2E127.)
    await filterBoard(page, PREFIX)
    const longBox = await millCard(page, fx.millPanjang.name).boundingBox()
    const shortBox = await millCard(page, fx.millSatu.name).boundingBox()
    expect(longBox).not.toBeNull()
    expect(shortBox).not.toBeNull()
    expect(Math.abs(longBox!.width - shortBox!.width)).toBeLessThanOrEqual(1)

    await expectNoHorizontalScroll(page)
  })

  test('9. Lihat Seluruh Hierarki Master Data dalam Satu Halaman — Mill tanpa logo', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)
    await focusBoard(page, PREFIX)

    const card = millCard(page, fx.millSatu.name)
    const fallback = card.locator('[data-testid="mill-logo-fallback"]')

    // Penanda inisial terlihat (bounding box > 0), bukan ikon gambar rusak.
    await expect(fallback).toHaveCount(1)
    const box = await fallback.boundingBox()
    expect(box).not.toBeNull()
    expect(box!.width).toBeGreaterThan(0)
    expect(box!.height).toBeGreaterThan(0)
    expect((await fallback.innerText()).trim()).not.toBe('')

    // Tidak ada <img> bernaturalWidth 0 di dalam kartu itu — tidak ada
    // <img> sama sekali.
    await expect(card.locator('img')).toHaveCount(0)

    // Penanda pengganti memakai kelas yang SAMA untuk semua mill tanpa
    // logo (konsisten).
    const classNames = await page.locator('[data-testid="mill-logo-fallback"]').evaluateAll(
      (elements) => [...new Set(elements.map((element) => element.className))],
    )
    expect(classNames).toHaveLength(1)

    // Pembanding: mill yang MEMANG berlogo merender <img> yang benar-benar
    // termuat (naturalWidth > 0).
    const logoCard = millCard(page, fx.millLogo.name)
    const img = logoCard.locator('[data-testid="mill-logo"]')
    await expect(img).toHaveCount(1)
    await expect.poll(() => img.evaluate((element: HTMLImageElement) => element.naturalWidth))
      .toBeGreaterThan(0)
  })

  test('10. Lihat Seluruh Hierarki Master Data dalam Satu Halaman — hierarki melewati batas Production Line', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)

    // Nama Station / Machinery Group / Machinery yang BENAR-BENAR ada di
    // basis data di bawah sebuah Production Line, dibaca dari sana —
    // tidak satu pun boleh ditemukan di halaman ini.
    //
    // Namanya ditanam spec ini dengan awalan khas, BUKAN diambil dari
    // stasiun fixture mana pun: nama stasiun fixture adalah nama kanonik
    // ("Boiler Room", "Pressing", ...) dan masing-masing muncul sebagai
    // SUBSTRING nama Production Line fixture yang memang dirender di sini
    // ("PL Tanpa Boiler Room"). Asersi atas nama seperti itu gagal karena
    // kebetulan penamaan, bukan karena batas hierarkinya bocor.
    await focusBoard(page, fx.millKotor.name)
    const bodyText = await page.locator('body').innerText()

    // Line-nya MEMANG terender — jadi "tidak ditemukan" di bawah ini
    // benar-benar soal tingkat di bawahnya, bukan soal cabang yang tidak
    // tampil.
    expect(bodyText).toContain(fx.lineRahasia.name)
    expect(bodyText).not.toContain(fx.stationRahasia)
    expect(bodyText).not.toContain(fx.machineryGroupRahasia)
    expect(bodyText).not.toContain(fx.machineryRahasia)

    await filterBoard(page, '')

    // Keterangan batas hierarki terlihat dan menyebut ketiga tingkat itu.
    const boundary = page.locator('[data-testid="hierarchy-boundary"]')
    await expect(boundary).toBeVisible()
    await expect(boundary).toContainText('Station')
    await expect(boundary).toContainText('Machinery Group')
    await expect(boundary).toContainText('Machinery')

    // Kedua tautan benar-benar membawa ke layar Kelola-nya — batas itu
    // bukan jalan buntu.
    await boundary.locator('[data-testid="link-kelola-station"]').click()
    await page.waitForURL((url) => url.pathname === '/master-data/stations')
    await page.goBack()
    await expect(page.locator('[data-testid="struktur-mills"]')).toBeVisible()

    await page.locator('[data-testid="hierarchy-boundary"] [data-testid="link-kelola-machinery"]').click()
    await page.waitForURL((url) => url.pathname === '/master-data/machinery')
  })

  test('11. Lihat Seluruh Hierarki Master Data dalam Satu Halaman — paginasi muncul hanya ketika isinya melebihi satu halaman', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)

    await expect(page.locator('[data-testid="search-input"]')).toHaveValue('')

    const perPage = 20
    const real = await databaseCounts()
    const shown = await readCounts(page)
    expect(shown).toEqual(real)

    // Papan: kontrol paginasi dirender JIKA DAN HANYA JIKA totalnya
    // melebihi perPage. Both directions are asserted — see adjustment (2).
    const boardPager = page.locator('[data-testid="struktur-mills"] [data-testid="pagination"]')
    if (real['business-unit'] > perPage) {
      await expect(boardPager).toHaveCount(1)
      await expect(page.locator('[data-testid="mill-card"]')).toHaveCount(perPage)
      await expect(boardPager).toContainText(`dari ${real['business-unit']} mill`)
      await expect(page.locator('[data-testid="mill-next-page"]')).toBeEnabled()
    } else {
      await expect(boardPager).toHaveCount(0)
      await expect(page.locator('[data-testid="mill-card"]')).toHaveCount(real['business-unit'])
    }

    // Kedua daftar ringkas: aturan yang sama, masing-masing sendiri.
    for (const [testid, level] of [
      ['pagination-corporate', 'corporate'],
      ['pagination-company', 'company'],
    ] as const) {
      const pager = page.locator(`[data-testid="struktur-mills"] [data-testid="${testid}"]`)
      await expect(pager).toHaveCount(real[level] > perPage ? 1 : 0)
    }

    // Production Line TIDAK ikut dipaginasi: jumlah pada kartu adalah
    // jumlah SEBENARNYA (withCount) dan baris line-nya utuh.
    await focusBoard(page, fx.millBersih.name)
    const lines = await tinkerJson<number>(
      `echo 'OUT=' . json_encode(App\\Models\\ProductionLine::where('business_unit_id', '${fx.millBersih.id}')->count());`,
    )
    expect(await lineCountOnCard(page, fx.millBersih.name)).toBe(lines)
    await expect(millCard(page, fx.millBersih.name).locator('[data-testid="line-row"]')).toHaveCount(lines)
    await filterBoard(page, '')

    // Locator lipat/buka berjumlah 0 — SCOPED ke kontainer layar (lihat
    // adjustment (1)); page-wide tidak mungkin 0 karena shell dan widget
    // chatbot sama-sama membawa aria-expanded di setiap halaman.
    await expect(toggleControls(page)).toHaveCount(0)
    // ...dan page-wide memang BUKAN 0, yang membuktikan scoping itu perlu.
    expect(await page.locator('[aria-expanded]').count()).toBeGreaterThan(0)
  })

  test('12. Lihat Seluruh Hierarki Master Data dalam Satu Halaman — baris ringkasan jumlah hilang atau tidak jujur', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)

    // Baris ringkasan ada di bagian paling atas halaman — di atas kotak
    // penyaring dan di atas papan kartu.
    const countsBox = await page.locator('[data-testid="counts-bar"]').boundingBox()
    const searchBox = await page.locator('[data-testid="search-input"]').boundingBox()
    expect(countsBox).not.toBeNull()
    expect(searchBox).not.toBeNull()
    expect(countsBox!.y).toBeLessThan(searchBox!.y)

    // Keempat angkanya sama dengan COUNT keempat tabel.
    const before = await readCounts(page)
    expect(before).toEqual(await databaseCounts())

    // Kata kunci yang hanya mencocokkan satu mill.
    await filterBoard(page, fx.realMillCode)
    await expect(page.locator('[data-testid="mill-card"]')).toHaveCount(1)

    // Keempat angka TIDAK berubah, dan jumlah yang cocok muncul sebagai
    // keterangan TERPISAH di sampingnya.
    expect(await readCounts(page)).toEqual(before)
    await expect(page.locator('[data-testid="match-note"]')).toBeVisible()
    await expect(page.locator('[data-testid="match-note"]')).toContainText('1 Mill')
  })

  // ═══════════════════════════════════════════════════════════════════════
  // usecase-157--tambah-entitas-hierarki-master-data
  // ═══════════════════════════════════════════════════════════════════════

  test('13. Tambah Entitas Hierarki Master Data — berhasil', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)
    await focusBoard(page, PREFIX)

    const millsBefore = (await readCounts(page))['business-unit']

    await clickLive(page.locator('[data-testid="add-mill-button"]'))
    await expect(page.locator('[data-testid="parent-select"]')).toBeVisible()
    await page.locator('[data-testid="parent-select"]').selectOption({ value: fx.companyInduk.id })
    await page.locator('[data-testid="field-code"]').fill(`${PREFIX}-BU-01`)
    await page.locator('[data-testid="field-name"]').fill(`${PREFIX} Mill Baru`)
    await clickLive(page.locator('[data-testid="save-button"]'))

    // Modal tertutup; pesan konfirmasi singkat terlihat.
    await expect(page.locator('[data-testid="save-button"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="success-message"]')).toBeVisible()

    // Kartu baru muncul di papan TANPA memuat ulang halaman, dan angka
    // Mill naik satu dari nilai yang dicatat.
    await expect(millCard(page, `${PREFIX} Mill Baru`)).toHaveCount(1)
    await expect(page.locator('[data-testid="count-business-unit"] .sm-counts__num'))
      .toHaveText(String(millsBefore + 1))
  })

  test('14. Tambah Entitas Hierarki Master Data — Field wajib kosong', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)

    const corporatesBefore = await tinkerJson<number>(
      "echo 'OUT=' . json_encode(App\\Models\\Corporate::count());",
    )

    await clickLive(page.locator('[data-testid="add-corporate-button"]'))
    await page.locator('[data-testid="field-corporate_code"]').fill(`${PREFIX}-CORP-WAJIB`)
    await clickLive(page.locator('[data-testid="save-button"]'))

    // Pesan validasi muncul tepat di bawah field nama.
    const nameField = page.locator('[data-testid="field-name"]').locator('xpath=..')
    await expect(nameField.locator('.sm-field__error')).toBeVisible()

    // Modal tetap terlihat; field kode masih berisi nilai yang diketik.
    await expect(page.locator('[data-testid="save-button"]')).toBeVisible()
    await expect(page.locator('[data-testid="field-corporate_code"]')).toHaveValue(`${PREFIX}-CORP-WAJIB`)

    // Tidak ada baris Corporate baru.
    await clickLive(page.locator('[data-testid="cancel-button"]'))
    expect(await tinkerJson<number>("echo 'OUT=' . json_encode(App\\Models\\Corporate::count());"))
      .toBe(corporatesBefore)
  })

  test('15. Tambah Entitas Hierarki Master Data — Kode sudah dipakai', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)
    await focusBoard(page, PREFIX)

    const millsBefore = (await readCounts(page))['business-unit']
    const cardsBefore = await page.locator('[data-testid="mill-card"]').count()

    await clickLive(page.locator('[data-testid="add-mill-button"]'))
    await page.locator('[data-testid="parent-select"]').selectOption({ value: fx.companyInduk.id })
    // Kode SEBENARNYA mill fixture, dibaca dari basis data.
    await page.locator('[data-testid="field-code"]').fill(fx.realMillCode)
    await page.locator('[data-testid="field-name"]').fill(`${PREFIX} Mill Kode Bentrok`)
    await clickLive(page.locator('[data-testid="save-button"]'))

    // Pesan 'sudah digunakan' di bawah field kode.
    const codeField = page.locator('[data-testid="field-code"]').locator('xpath=..')
    await expect(codeField.locator('.sm-field__error')).toContainText(/sudah digunakan/i)

    // Modal tetap terbuka dengan nama yang diketik masih terisi.
    await expect(page.locator('[data-testid="field-name"]')).toHaveValue(`${PREFIX} Mill Kode Bentrok`)

    await clickLive(page.locator('[data-testid="cancel-button"]'))

    // Jumlah kartu dan angka Mill tidak berubah.
    await expect(page.locator('[data-testid="mill-card"]')).toHaveCount(cardsBefore)
    await expect(page.locator('[data-testid="count-business-unit"] .sm-counts__num'))
      .toHaveText(String(millsBefore))
  })

  test('16. Tambah Entitas Hierarki Master Data — Kode Production Line dibiarkan kosong', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)
    await focusBoard(page, fx.millTanpaLine.name)

    const linesBefore = (await readCounts(page))['production-line']

    await clickLive(millCard(page, fx.millTanpaLine.name)
      .locator(`[data-testid="add-line-${fx.millTanpaLine.id}"]`))
    await expect(page.locator('[data-testid="parent-select"]')).toHaveValue(fx.millTanpaLine.id)
    await page.locator('[data-testid="field-name"]').fill(`${PREFIX} Line Tanpa Kode`)
    // Kode dibiarkan kosong.
    await expect(page.locator('[data-testid="field-code"]')).toHaveValue('')
    await clickLive(page.locator('[data-testid="save-button"]'))

    // Modal tertutup dengan konfirmasi; tidak ada pesan galat pada kode.
    await expect(page.locator('[data-testid="save-button"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="success-message"]')).toBeVisible()

    // Baris line muncul di dalam kartu mill itu MESKI tanpa kode.
    const row = lineRow(page, fx.millTanpaLine.name, `${PREFIX} Line Tanpa Kode`)
    await expect(row).toHaveCount(1)
    await expect(row.locator('[data-testid="line-code"]')).toHaveCount(0)

    // Angka Production Line naik satu.
    await expect(page.locator('[data-testid="count-production-line"] .sm-counts__num'))
      .toHaveText(String(linesBefore + 1))
  })

  test('17. Tambah Entitas Hierarki Master Data — Berkas logo ditolak', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)

    const corporatesBefore = await tinkerJson<number>(
      "echo 'OUT=' . json_encode(App\\Models\\Corporate::count());",
    )

    await clickLive(page.locator('[data-testid="add-corporate-button"]'))
    await page.locator('[data-testid="field-corporate_code"]').fill(`${PREFIX}-CORP-LOGO`)
    await page.locator('[data-testid="field-name"]').fill(`${PREFIX} Corp Logo Ditolak`)

    // Berkas .png yang isinya teks — dibuat di memori, bukan disimpan di
    // repo: App\Rules\RealImage memeriksa ISI berkas, bukan ekstensinya.
    await Promise.all([
      page.waitForResponse((response) => response.url().includes('/livewire/update')),
      page.locator('[data-testid="field-logo"]').setInputFiles(FAKE_PNG),
    ])
    await clickLive(page.locator('[data-testid="save-button"]'))

    // Pesan penolakan logo terlihat DI DEKAT field logo.
    // TIMEOUT 15s, bukan 5s bawaan, DISENGAJA: asersi ini menunggu satu
    // putaran unggah berkas Livewire, dan putaran itu sesekali melewati 5
    // detik saat mesinnya sibuk — terukur 6,9s dan 7,0s pada 2 dari ~50
    // eksekusi, sementara saat sehat ~2,1s; diisolasi lolos 3/3 berkali-kali.
    // Yang lambat unggahannya, BUKAN produknya. Memperpanjang batas di satu
    // asersi ini lebih jujur daripada menyetel `retries`, yang akan ikut
    // menutupi kegagalan sebenarnya di 43 test lain pada berkas ini.
    // JANGAN mengubah kode produksi untuk ini.
    await expect(page.locator('[data-testid="logo-error"]')).toBeVisible({ timeout: 15_000 })

    // Modal tetap terbuka; kode dan nama masih berisi nilai yang diketik.
    await expect(page.locator('[data-testid="field-corporate_code"]')).toHaveValue(`${PREFIX}-CORP-LOGO`)
    await expect(page.locator('[data-testid="field-name"]')).toHaveValue(`${PREFIX} Corp Logo Ditolak`)

    await clickLive(page.locator('[data-testid="cancel-button"]'))
    expect(await tinkerJson<number>("echo 'OUT=' . json_encode(App\\Models\\Corporate::count());"))
      .toBe(corporatesBefore)
  })

  test('18. Tambah Entitas Hierarki Master Data — Induk diubah sebelum menyimpan', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)
    await focusBoard(page, PREFIX)

    const linesOnDuaBefore = await lineCountOnCard(page, fx.millDua.name)

    // Ditekan dari dalam kartu mill A...
    await clickLive(millCard(page, fx.millSatu.name).locator(`[data-testid="add-line-${fx.millSatu.id}"]`))
    await expect(page.locator('[data-testid="parent-select"]')).toHaveValue(fx.millSatu.id)

    // ...lalu select mill induk DIUBAH ke mill B.
    await page.locator('[data-testid="parent-select"]').selectOption({ value: fx.millDua.id })
    await page.locator('[data-testid="field-name"]').fill(`${PREFIX} Line Pindah Induk`)
    await clickLive(page.locator('[data-testid="save-button"]'))

    await expect(page.locator('[data-testid="save-button"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="success-message"]')).toBeVisible()

    // Barisnya muncul di kartu mill B, dan kartu mill A TIDAK memuatnya.
    await expect(lineRow(page, fx.millDua.name, `${PREFIX} Line Pindah Induk`)).toHaveCount(1)
    await expect(lineRow(page, fx.millSatu.name, `${PREFIX} Line Pindah Induk`)).toHaveCount(0)

    // Jumlah line pada kartu mill B naik satu.
    expect(await lineCountOnCard(page, fx.millDua.name)).toBe(linesOnDuaBefore + 1)
  })

  test('19. Tambah Entitas Hierarki Master Data — Membatalkan modal', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)

    const before = await readCounts(page)

    await clickLive(page.locator('[data-testid="add-company-button"]'))
    await page.locator('[data-testid="field-company_code"]').fill(`${PREFIX}-CMP-BATAL`)
    await page.locator('[data-testid="field-name"]').fill(`${PREFIX} Company Batal`)

    // Tutup modal tanpa Simpan.
    await clickLive(page.locator('[data-testid="cancel-button"]'))

    // Modal tidak terlihat lagi.
    await expect(page.locator('[data-testid="save-button"]')).toHaveCount(0)

    // Tidak ada baris Company baru; keempat angka identik.
    await expect(summaryRow(page, 'company-row', `${PREFIX} Company Batal`)).toHaveCount(0)
    expect(await readCounts(page)).toEqual(before)

    // Membuka modal tambah lagi menampilkan form KOSONG.
    await clickLive(page.locator('[data-testid="add-company-button"]'))
    await expect(page.locator('[data-testid="field-company_code"]')).toHaveValue('')
    await expect(page.locator('[data-testid="field-name"]')).toHaveValue('')
    await clickLive(page.locator('[data-testid="cancel-button"]'))
  })

  // Scenario 20 — TIDAK ADA browser test, disengaja (lihat kepala berkas).

  /**
   * ⚠ DUGAAN CACAT IMPLEMENTASI — test ini GAGAL hari ini, dan gagalnya
   * benar. Dibiarkan apa adanya supaya babak perbaikan melihatnya.
   *
   * Menyimpan Company tanpa memilih Corporate induk menjawab HTTP 500 di
   * PostgreSQL. MasterDataTreeView::companyRules() membangun aturan unik
   * nama Company sebagai:
   *
   *   $corporateId = (string) ($this->form['corporate_id'] ?? '');
   *   UniqueCaseInsensitive::on('companies', 'name')
   *       ->where(fn ($query) => $query->where('corporate_id', $corporateId));
   *
   * Ketika induknya belum dipilih, $corporateId adalah '' dan aturan nama
   * tetap dijalankan (Laravel hanya menghentikan aturan BERIKUTNYA pada
   * atribut yang sama, dan `required` gagal pada form.corporate_id, bukan
   * pada form.name). Kuerinya menjadi:
   *
   *   select exists(select * from "companies"
   *     where lower(name) = ? and "corporate_id" = '')
   *
   * SQLite menerima '' pada kolom uuid; PostgreSQL menolaknya —
   * SQLSTATE[22P02] invalid input syntax for type uuid: "". Karena itu
   * component test setara di backend/tests/Feature/Livewire/
   * MasterDataTreeViewTest.php LULUS (suite itu SQLite) sementara layar
   * sebenarnya 500 di produksi. Persis jebakan SQLite-vs-PostgreSQL yang
   * sudah pernah berbiaya di proyek ini.
   *
   * Pola yang sama ada di KelolaCompany::rules() (baris 196-197), jadi
   * dugaan ini kemungkinan BUKAN regresi layar ini melainkan cacat lama
   * yang baru kali ini tersentuh uji. Perbaikan yang masuk akal: lewati
   * (atau beri nilai yang pasti tidak cocok pada) scope `where` itu ketika
   * corporate_id kosong.
   */
  test('21. Tambah Entitas Hierarki Master Data — induk wajib tidak dipilih', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)

    const companiesBefore = (await readCounts(page))['company']

    await clickLive(page.locator('[data-testid="add-company-button"]'))
    await page.locator('[data-testid="field-company_code"]').fill(`${PREFIX}-CMP-NOINDUK`)
    await page.locator('[data-testid="field-name"]').fill(`${PREFIX} Company Tanpa Induk`)
    // Select Corporate induk dibiarkan pada pilihan kosong.
    await expect(page.locator('[data-testid="parent-select"]')).toHaveValue('')
    await clickLive(page.locator('[data-testid="save-button"]'))

    // Pesan validasi muncul pada select Corporate induk.
    const parentField = page.locator('[data-testid="parent-select"]').locator('xpath=..')
    await expect(parentField.locator('.sm-field__error')).toBeVisible()

    // Modal tetap terbuka dengan kode dan nama masih terisi.
    await expect(page.locator('[data-testid="field-company_code"]')).toHaveValue(`${PREFIX}-CMP-NOINDUK`)
    await expect(page.locator('[data-testid="field-name"]')).toHaveValue(`${PREFIX} Company Tanpa Induk`)

    await clickLive(page.locator('[data-testid="cancel-button"]'))
    await expect(page.locator('[data-testid="count-company"] .sm-counts__num'))
      .toHaveText(String(companiesBefore))
  })

  test('22. Tambah Entitas Hierarki Master Data — kode Production Line diisi tetapi sudah dipakai', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)
    await focusBoard(page, fx.millSatu.name)

    const linesBefore = (await readCounts(page))['production-line']

    await clickLive(millCard(page, fx.millSatu.name).locator(`[data-testid="add-line-${fx.millSatu.id}"]`))
    // Kode SEBENARNYA salah satu Production Line fixture.
    await page.locator('[data-testid="field-code"]').fill(fx.realLineCode)
    await page.locator('[data-testid="field-name"]').fill(`${PREFIX} Line Bentrok`)
    await clickLive(page.locator('[data-testid="save-button"]'))

    const codeField = page.locator('[data-testid="field-code"]').locator('xpath=..')
    await expect(codeField.locator('.sm-field__error')).toContainText(/sudah digunakan/i)
    await expect(page.locator('[data-testid="field-name"]')).toHaveValue(`${PREFIX} Line Bentrok`)

    await clickLive(page.locator('[data-testid="cancel-button"]'))
    await expect(page.locator('[data-testid="count-production-line"] .sm-counts__num'))
      .toHaveText(String(linesBefore))
  })

  test('23. Tambah Entitas Hierarki Master Data — induk terisi otomatis tidak boleh dikunci atau disembunyikan', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)
    await focusBoard(page, PREFIX)

    await clickLive(millCard(page, fx.millSatu.name).locator(`[data-testid="add-line-${fx.millSatu.id}"]`))

    const select = page.locator('[data-testid="parent-select"]')

    // Terlihat (bukan hidden) dan nilainya sudah mill pemilik kartu itu.
    await expect(select).toBeVisible()
    await expect(select).toHaveValue(fx.millSatu.id)

    // Tidak disabled dan tidak readonly.
    await expect(select).toBeEnabled()
    expect(await select.getAttribute('disabled')).toBeNull()
    expect(await select.getAttribute('readonly')).toBeNull()
    expect(await select.evaluate((element: HTMLSelectElement) => element.disabled)).toBe(false)

    // Mengganti pilihan ke mill lain berhasil dan nilai select berubah.
    await select.selectOption({ value: fx.millDua.id })
    await expect(select).toHaveValue(fx.millDua.id)

    await clickLive(page.locator('[data-testid="cancel-button"]'))
  })

  test('24. Tambah Entitas Hierarki Master Data — logo tidak diisi', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)
    await focusBoard(page, PREFIX)

    const millsBefore = (await readCounts(page))['business-unit']

    await clickLive(page.locator('[data-testid="add-mill-button"]'))
    await page.locator('[data-testid="parent-select"]').selectOption({ value: fx.companyInduk.id })
    await page.locator('[data-testid="field-code"]').fill(`${PREFIX}-BU-NOLOGO`)
    await page.locator('[data-testid="field-name"]').fill(`${PREFIX} Mill Tanpa Logo`)
    // Tanpa memilih berkas logo.
    await expect(page.locator('[data-testid="field-logo"]')).toHaveValue('')
    await clickLive(page.locator('[data-testid="save-button"]'))

    await expect(page.locator('[data-testid="save-button"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="success-message"]')).toBeVisible()

    // Kartu baru terlihat dengan penanda inisial sebagai ganti logo, dan
    // tidak ada <img> bernaturalWidth 0 di kartu itu.
    const card = millCard(page, `${PREFIX} Mill Tanpa Logo`)
    await expect(card).toHaveCount(1)
    await expect(card.locator('[data-testid="mill-logo-fallback"]')).toBeVisible()
    await expect(card.locator('img')).toHaveCount(0)

    await expect(page.locator('[data-testid="count-business-unit"] .sm-counts__num'))
      .toHaveText(String(millsBefore + 1))
  })

  test('25. Tambah Entitas Hierarki Master Data — peran selain Admin tidak boleh menambah', async ({ page }) => {
    problems = watchProblems(page, { allowStatus: [403] })

    const before = await databaseCounts()

    await login(page, NON_ADMIN, PASSWORD)
    const response = await page.goto(PATH)
    expect(response?.status()).toBe(403)

    // Tidak satu pun tombol tambah terender — halaman layar ini tidak
    // pernah dirender sama sekali.
    for (const testid of [
      'add-mill-button', 'add-mill-button-empty', 'add-corporate-button',
      'add-company-button', 'parent-select', 'save-button', 'struktur-mills',
    ]) {
      await expect(page.locator(`[data-testid="${testid}"]`)).toHaveCount(0)
    }
    await expect(page.locator('[data-testid^="add-line-"]')).toHaveCount(0)

    // Jumlah baris keempat tabel hierarki tidak berubah.
    expect(await databaseCounts()).toEqual(before)
  })

  test('26. Tambah Entitas Hierarki Master Data — angka jumlah dihitung ulang setelah berhasil', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)
    await focusBoard(page, fx.millSatuLine.name)

    const linesBefore = (await readCounts(page))['production-line']
    const onCardBefore = await lineCountOnCard(page, fx.millSatuLine.name)

    await clickLive(millCard(page, fx.millSatuLine.name)
      .locator(`[data-testid="add-line-${fx.millSatuLine.id}"]`))
    await page.locator('[data-testid="field-name"]').fill(`${PREFIX} Line Hitung Ulang`)
    await clickLive(page.locator('[data-testid="save-button"]'))

    // TANPA reload: angka ringkasan naik satu dan jumlah line pada kartu
    // naik satu; baris line baru terlihat di dalam kartu.
    await expect(page.locator('[data-testid="count-production-line"] .sm-counts__num'))
      .toHaveText(String(linesBefore + 1))
    expect(await lineCountOnCard(page, fx.millSatuLine.name)).toBe(onCardBefore + 1)
    await expect(lineRow(page, fx.millSatuLine.name, `${PREFIX} Line Hitung Ulang`)).toHaveCount(1)

    // Sesudah reload manual angkanya TETAP SAMA — bukan nilai sementara di
    // memori.
    await page.reload()
    await expect(page.locator('[data-testid="counts-bar"]')).toBeVisible()
    await expect(page.locator('[data-testid="count-production-line"] .sm-counts__num'))
      .toHaveText(String(linesBefore + 1))
    await focusBoard(page, fx.millSatuLine.name)
    expect(await lineCountOnCard(page, fx.millSatuLine.name)).toBe(onCardBefore + 1)
  })

  // ═══════════════════════════════════════════════════════════════════════
  // usecase-158--ubah-entitas-hierarki-master-data
  // ═══════════════════════════════════════════════════════════════════════

  test('27. Ubah Entitas Hierarki Master Data — berhasil', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)
    // Narrowed on the PREFIX, not on this mill's own name: the scenario
    // renames it, and a filter keyed to the old name would drop the card
    // from the board the moment the rename succeeded.
    await focusBoard(page, PREFIX)

    const before = await readCounts(page)

    await clickLive(millCard(page, fx.millUbah.name).locator(`[data-testid="edit-mill-${fx.millUbah.id}"]`))

    // Modal terbuka SUDAH terisi kode, nama, dan select Company induk yang
    // benar.
    await expect(page.locator('[data-testid="field-code"]')).toHaveValue(`${PREFIX}-BU-UB`)
    await expect(page.locator('[data-testid="field-name"]')).toHaveValue(fx.millUbah.name)
    await expect(page.locator('[data-testid="parent-select"]')).toHaveValue(fx.companyInduk.id)

    await page.locator('[data-testid="field-name"]').fill(`${PREFIX} Mill Sudah Diubah`)
    await clickLive(page.locator('[data-testid="save-button"]'))

    await expect(page.locator('[data-testid="save-button"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="success-message"]')).toBeVisible()

    // Kartu di papan menampilkan nama baru TANPA reload.
    await expect(millCard(page, `${PREFIX} Mill Sudah Diubah`)).toHaveCount(1)
    await expect(millCard(page, fx.millUbah.name)).toHaveCount(0)

    // Keempat angka tidak berubah — mengubah nama tidak mengubah jumlah.
    expect(await readCounts(page)).toEqual(before)

    // Restore so later tests can still find it by its planted name.
    await clickLive(millCard(page, `${PREFIX} Mill Sudah Diubah`)
      .locator(`[data-testid="edit-mill-${fx.millUbah.id}"]`))
    await page.locator('[data-testid="field-name"]').fill(fx.millUbah.name)
    await clickLive(page.locator('[data-testid="save-button"]'))
    await expect(millCard(page, fx.millUbah.name)).toHaveCount(1)
  })

  test('28. Ubah Entitas Hierarki Master Data — Memindahkan entitas ke induk lain', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)
    await focusBoard(page, PREFIX)

    const linesTotalBefore = (await readCounts(page))['production-line']
    const onSourceBefore = await lineCountOnCard(page, fx.millUbah.name)
    const onTargetBefore = await lineCountOnCard(page, fx.millPindah.name)

    await clickLive(lineRow(page, fx.millUbah.name, fx.linePindah.name)
      .locator(`[data-testid="edit-line-${fx.linePindah.id}"]`))
    await page.locator('[data-testid="parent-select"]').selectOption({ value: fx.millPindah.id })
    await clickLive(page.locator('[data-testid="save-button"]'))

    // TANPA reload: barisnya hilang dari kartu mill A dan muncul di mill B.
    await expect(lineRow(page, fx.millUbah.name, fx.linePindah.name)).toHaveCount(0)
    await expect(lineRow(page, fx.millPindah.name, fx.linePindah.name)).toHaveCount(1)

    // Jumlah line turun satu di A dan naik satu di B.
    expect(await lineCountOnCard(page, fx.millUbah.name)).toBe(onSourceBefore - 1)
    expect(await lineCountOnCard(page, fx.millPindah.name)).toBe(onTargetBefore + 1)

    // Angka Production Line pada baris ringkasan TETAP SAMA.
    await expect(page.locator('[data-testid="count-production-line"] .sm-counts__num'))
      .toHaveText(String(linesTotalBefore))
  })

  test('29. Ubah Entitas Hierarki Master Data — Field wajib dikosongkan', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)
    await focusBoard(page, fx.millWajib.name)

    await clickLive(millCard(page, fx.millWajib.name).locator(`[data-testid="edit-mill-${fx.millWajib.id}"]`))
    await page.locator('[data-testid="field-name"]').fill('')
    await clickLive(page.locator('[data-testid="save-button"]'))

    // Pesan validasi tepat di bawah field nama; modal tetap terlihat.
    const nameField = page.locator('[data-testid="field-name"]').locator('xpath=..')
    await expect(nameField.locator('.sm-field__error')).toBeVisible()
    await expect(page.locator('[data-testid="save-button"]')).toBeVisible()

    await clickLive(page.locator('[data-testid="cancel-button"]'))

    // Kartu di papan masih menampilkan nama lama — nilai di basis data
    // tidak berubah.
    await expect(millCard(page, fx.millWajib.name)).toHaveCount(1)
    expect(await tinkerJson<string>(
      `echo 'OUT=' . json_encode(App\\Models\\BusinessUnit::findOrFail('${fx.millWajib.id}')->name);`,
    )).toBe(fx.millWajib.name)
  })

  test('30. Ubah Entitas Hierarki Master Data — Kode diubah menjadi kode yang sudah dipakai', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)
    await focusBoard(page, PREFIX)

    await clickLive(millCard(page, fx.millSatu.name).locator(`[data-testid="edit-mill-${fx.millSatu.id}"]`))
    await page.locator('[data-testid="field-code"]').fill(fx.millDua.code)
    await clickLive(page.locator('[data-testid="save-button"]'))

    const codeField = page.locator('[data-testid="field-code"]').locator('xpath=..')
    await expect(codeField.locator('.sm-field__error')).toContainText(/sudah digunakan/i)
    await expect(page.locator('[data-testid="save-button"]')).toBeVisible()

    await clickLive(page.locator('[data-testid="cancel-button"]'))

    // Kartu mill Satu di papan masih menampilkan kode lamanya.
    await expect(millCard(page, fx.millSatu.name).locator('[data-testid="mill-code"]'))
      .toHaveText(fx.millSatu.code)
  })

  test('31. Ubah Entitas Hierarki Master Data — Kode disimpan apa adanya tanpa diubah', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)
    await focusBoard(page, fx.millKodeTetap.name)

    await clickLive(millCard(page, fx.millKodeTetap.name)
      .locator(`[data-testid="edit-mill-${fx.millKodeTetap.id}"]`))

    // Hanya nama yang diubah; field kode dibiarkan apa adanya.
    await expect(page.locator('[data-testid="field-code"]')).toHaveValue(fx.millKodeTetap.code)
    await page.locator('[data-testid="field-name"]').fill(`${fx.millKodeTetap.name} v2`)
    await clickLive(page.locator('[data-testid="save-button"]'))

    // Tidak ada pesan galat pada field kode; modal tertutup dengan
    // konfirmasi.
    await expect(page.locator('[data-testid="save-button"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="success-message"]')).toBeVisible()

    // Kartu menampilkan nama baru dan kode yang SAMA seperti sebelumnya.
    const card = millCard(page, `${fx.millKodeTetap.name} v2`)
    await expect(card).toHaveCount(1)
    await expect(card.locator('[data-testid="mill-code"]')).toHaveText(fx.millKodeTetap.code)
  })

  test('32. Ubah Entitas Hierarki Master Data — Logo baru ditolak', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)
    await focusBoard(page, fx.millLogo.name)

    const card = millCard(page, fx.millLogo.name)
    await expect(card.locator('[data-testid="mill-logo"]')).toHaveCount(1)

    await clickLive(card.locator(`[data-testid="edit-mill-${fx.millLogo.id}"]`))

    // Keterangan nama berkas logo yang sedang dipakai terender.
    await expect(page.locator('[data-testid="existing-logo-name"]')).toBeVisible()

    await page.locator('[data-testid="field-name"]').fill(`${fx.millLogo.name} Diubah`)
    await Promise.all([
      page.waitForResponse((response) => response.url().includes('/livewire/update')),
      page.locator('[data-testid="field-logo"]').setInputFiles(FAKE_PNG),
    ])
    await clickLive(page.locator('[data-testid="save-button"]'))

    // Pesan penolakan logo terlihat di dekat field logo; modal tetap
    // terbuka dan field nama masih berisi nilai yang baru diketik.
    // TIMEOUT 15s, bukan 5s bawaan, DISENGAJA: asersi ini menunggu satu
    // putaran unggah berkas Livewire, dan putaran itu sesekali melewati 5
    // detik saat mesinnya sibuk — terukur 6,9s dan 7,0s pada 2 dari ~50
    // eksekusi, sementara saat sehat ~2,1s; diisolasi lolos 3/3 berkali-kali.
    // Yang lambat unggahannya, BUKAN produknya. Memperpanjang batas di satu
    // asersi ini lebih jujur daripada menyetel `retries`, yang akan ikut
    // menutupi kegagalan sebenarnya di 43 test lain pada berkas ini.
    // JANGAN mengubah kode produksi untuk ini.
    await expect(page.locator('[data-testid="logo-error"]')).toBeVisible({ timeout: 15_000 })
    await expect(page.locator('[data-testid="field-name"]')).toHaveValue(`${fx.millLogo.name} Diubah`)

    await clickLive(page.locator('[data-testid="cancel-button"]'))

    // Sesudah modal ditutup, kartu masih menampilkan logo LAMANYA — bukan
    // penanda inisial, bukan gambar rusak.
    const after = millCard(page, fx.millLogo.name)
    const img = after.locator('[data-testid="mill-logo"]')
    await expect(img).toHaveCount(1)
    await expect(after.locator('[data-testid="mill-logo-fallback"]')).toHaveCount(0)
    await expect.poll(() => img.evaluate((element: HTMLImageElement) => element.naturalWidth))
      .toBeGreaterThan(0)
  })

  test('33. Ubah Entitas Hierarki Master Data — Entitas sudah dihapus Admin lain', async ({ page, browser }) => {
    problems = watchProblems(page)
    await gotoBoard(page)
    await focusBoard(page, fx.millHilang.name)

    const millsBefore = (await readCounts(page))['business-unit']

    // Konteks A: modal ubah terbuka.
    await clickLive(millCard(page, fx.millHilang.name)
      .locator(`[data-testid="edit-mill-${fx.millHilang.id}"]`))
    await expect(page.locator('[data-testid="field-name"]')).toHaveValue(fx.millHilang.name)

    // Konteks B: Admin yang sama menghapus mill itu sampai selesai.
    // Urutannya deterministik, bukan balapan.
    const other = await secondAdminBoard(browser)
    try {
      await focusBoard(other.page, fx.millHilang.name)
      await clickLive(millCard(other.page, fx.millHilang.name)
        .locator(`[data-testid="delete-mill-${fx.millHilang.id}"]`))
      await expect(other.page.locator('[data-testid="delete-confirm-text"]')).toContainText(fx.millHilang.name)
      await clickLive(other.page.locator('[data-testid="confirm-delete-button"]'))
      await expect(other.page.locator('[data-testid="success-message"]')).toBeVisible()
    } finally {
      await other.close()
    }

    // Kembali ke konteks A: ubah nama lalu Simpan.
    await page.locator('[data-testid="field-name"]').fill(`${fx.millHilang.name} Diubah`)
    await clickLive(page.locator('[data-testid="save-button"]'))

    // Kalimat biasa yang menyatakan datanya sudah tidak ada — tanpa stack
    // trace dan tanpa halaman galat 500.
    const alert = page.locator('[data-testid="form-error"]')
    await expect(alert).toBeVisible()
    await expect(alert).toContainText(/sudah dihapus/i)
    expect(await alert.innerText()).not.toContain('ModelNotFoundException')

    await clickLive(page.locator('[data-testid="cancel-button"]'))

    // Sesudah halaman menyegarkan dirinya, kartunya tidak lagi ada dan
    // angka Mill sudah berkurang satu.
    await expect(millCard(page, fx.millHilang.name)).toHaveCount(0)
    await expect(page.locator('[data-testid="count-business-unit"] .sm-counts__num'))
      .toHaveText(String(millsBefore - 1))
  })

  test('34. Ubah Entitas Hierarki Master Data — Dua Admin mengubah satu entitas hampir bersamaan', async ({ page, browser }) => {
    problems = watchProblems(page)
    await gotoBoard(page)
    await focusBoard(page, fx.millRebutan.name)

    // Di A: modal ubah terbuka, nama diisi 'E2E127 Dari A'.
    await clickLive(millCard(page, fx.millRebutan.name)
      .locator(`[data-testid="edit-mill-${fx.millRebutan.id}"]`))
    await page.locator('[data-testid="field-name"]').fill(`${PREFIX} Dari A`)

    // Di B: modal ubah mill yang sama, 'E2E127 Dari B', Simpan dulu.
    const other = await secondAdminBoard(browser)
    try {
      await focusBoard(other.page, fx.millRebutan.name)
      await clickLive(millCard(other.page, fx.millRebutan.name)
        .locator(`[data-testid="edit-mill-${fx.millRebutan.id}"]`))
      await other.page.locator('[data-testid="field-name"]').fill(`${PREFIX} Dari B`)
      await clickLive(other.page.locator('[data-testid="save-button"]'))
      await expect(other.page.locator('[data-testid="success-message"]')).toBeVisible()
      await expect(other.page.locator('[data-testid="save-button"]')).toHaveCount(0)
    } finally {
      await other.close()
    }

    // Baru kemudian di A klik Simpan. Urutannya ditentukan spec.
    await clickLive(page.locator('[data-testid="save-button"]'))

    // Kedua Simpan berhasil dengan konfirmasi dan TANPA pesan
    // konflik/penguncian.
    await expect(page.locator('[data-testid="success-message"]')).toBeVisible()
    await expect(page.locator('[data-testid="save-button"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="form-error"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="form-error-page"]')).toHaveCount(0)

    // Sesudah memuat ulang halaman, kartu menampilkan 'E2E127 Dari A' —
    // penyimpanan terakhir yang menang.
    await page.reload()
    await focusBoard(page, `${PREFIX} Dari A`)
    await expect(millCard(page, `${PREFIX} Dari A`)).toHaveCount(1)
    await expect(millCard(page, `${PREFIX} Dari B`)).toHaveCount(0)
  })

  test('35. Ubah Entitas Hierarki Master Data — Membatalkan modal', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)
    await focusBoard(page, fx.corpBatal.name)

    const before = await readCounts(page)
    const row = summaryRow(page, 'corporate-row', fx.corpBatal.name)
    await expect(row).toHaveCount(1)

    await clickLive(row.locator(`[data-testid="edit-corporate-${fx.corpBatal.id}"]`))
    await page.locator('[data-testid="field-name"]').fill(`${PREFIX} Corp Batal Diubah`)
    await page.locator('[data-testid="field-short_name"]').fill('Jangan Tersimpan')

    // Tutup modal tanpa Simpan.
    await clickLive(page.locator('[data-testid="cancel-button"]'))

    // Baris di tabel ringkas masih menampilkan nilai lama; keempat angka
    // tidak berubah.
    await expect(summaryRow(page, 'corporate-row', fx.corpBatal.name)).toHaveCount(1)
    await expect(summaryRow(page, 'corporate-row', `${PREFIX} Corp Batal Diubah`)).toHaveCount(0)
    expect(await readCounts(page)).toEqual(before)

    // Modal yang dibuka ulang menampilkan nilai dari basis data, bukan
    // perubahan yang dibatalkan.
    await clickLive(summaryRow(page, 'corporate-row', fx.corpBatal.name)
      .locator(`[data-testid="edit-corporate-${fx.corpBatal.id}"]`))
    await expect(page.locator('[data-testid="field-name"]')).toHaveValue(fx.corpBatal.name)
    await expect(page.locator('[data-testid="field-short_name"]')).not.toHaveValue('Jangan Tersimpan')
    await clickLive(page.locator('[data-testid="cancel-button"]'))
  })

  test('36. Ubah Entitas Hierarki Master Data — hanya Admin yang boleh mengubah', async ({ page }) => {
    problems = watchProblems(page, { allowStatus: [403] })

    const snapshot = await tinkerJson<{ name: string; code: string }>(`
      $mill = App\\Models\\BusinessUnit::where('code', '${fx.realMillCode}')->firstOrFail();
      echo 'OUT=' . json_encode(['name' => $mill->name, 'code' => $mill->code]);
    `)

    await login(page, NON_ADMIN, PASSWORD)
    const response = await page.goto(PATH)
    expect(response?.status()).toBe(403)

    // Tidak satu pun aksi ubah terender — layar ini tidak pernah dirender
    // bagi peran itu.
    await expect(page.locator('[data-testid^="edit-mill-"]')).toHaveCount(0)
    await expect(page.locator('[data-testid^="edit-line-"]')).toHaveCount(0)
    await expect(page.locator('[data-testid^="edit-corporate-"]')).toHaveCount(0)
    await expect(page.locator('[data-testid^="edit-company-"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="save-button"]')).toHaveCount(0)

    // Nama dan kode mill itu di basis data tidak berubah.
    expect(await tinkerJson<{ name: string; code: string }>(`
      $mill = App\\Models\\BusinessUnit::where('code', '${fx.realMillCode}')->firstOrFail();
      echo 'OUT=' . json_encode(['name' => $mill->name, 'code' => $mill->code]);
    `)).toEqual(snapshot)
  })

  // ═══════════════════════════════════════════════════════════════════════
  // usecase-159--hapus-entitas-hierarki-master-data
  // ═══════════════════════════════════════════════════════════════════════

  test('37. Hapus Entitas Hierarki Master Data — sukses', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)
    await focusBoard(page, fx.corpHapus.name)

    const before = await readCounts(page)
    const row = summaryRow(page, 'corporate-row', fx.corpHapus.name)
    await expect(row).toHaveCount(1)

    await clickLive(row.locator(`[data-testid="delete-corporate-${fx.corpHapus.id}"]`))

    // Konfirmasi memuat nama entitasnya.
    await expect(page.locator('[data-testid="delete-confirm-text"]')).toContainText(fx.corpHapus.name)

    await clickLive(page.locator('[data-testid="confirm-delete-button"]'))

    // Modal tertutup, pesan singkat muncul, barisnya hilang TANPA reload.
    await expect(page.locator('[data-testid="confirm-delete-button"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="success-message"]')).toBeVisible()
    await expect(summaryRow(page, 'corporate-row', fx.corpHapus.name)).toHaveCount(0)

    // Angka Corporate turun satu; ketiga angka lain tidak berubah.
    const after = await readCounts(page)
    expect(after['corporate']).toBe(before['corporate'] - 1)
    expect(after['company']).toBe(before['company'])
    expect(after['business-unit']).toBe(before['business-unit'])
    expect(after['production-line']).toBe(before['production-line'])
  })

  test('38. Hapus Entitas Hierarki Master Data — Penghapusan ditolak karena masih ada yang bergantung', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)
    await focusBoard(page, fx.blockedMill.name)

    const before = await readCounts(page)
    const card = millCard(page, fx.blockedMill.name)
    await expect(card).toHaveCount(1)

    await clickLive(card.locator(`[data-testid="delete-mill-${fx.blockedMill.id}"]`))
    await clickLive(page.locator('[data-testid="confirm-delete-button"]'))

    // Modal konfirmasi TETAP TERLIHAT — tidak tertutup, tidak berubah
    // menjadi toast.
    await expect(page.locator('[data-testid="confirm-delete-button"]')).toBeVisible()
    await expect(page.locator('[data-testid="delete-error"]')).toHaveCount(0)

    // Di dalam modal terbaca pesan penolakan yang memuat angka penghalang
    // yang PERSIS SAMA dengan hasil hitungan basis data.
    const refusal = page.locator('[data-testid="delete-refusal"]')
    await expect(refusal).toBeVisible()
    for (const [count, label] of [
      [fx.blockedMill.users, 'User'],
      [fx.blockedMill.lines, 'Production Line'],
      [fx.blockedMill.stations, 'Station'],
      [fx.blockedMill.periods, 'Periode'],
    ] as Array<[number, string]>) {
      if (count > 0) {
        await expect(refusal).toContainText(`${count} ${label}`)
      }
    }

    // Menutup modal tidak menghapus apa pun; kartunya masih ada dan
    // keempat angka tidak berubah.
    await clickLive(page.locator('[data-testid="cancel-delete-button"]'))
    await expect(millCard(page, fx.blockedMill.name)).toHaveCount(1)
    expect(await readCounts(page)).toEqual(before)
  })

  test('39. Hapus Entitas Hierarki Master Data — Membatalkan konfirmasi', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)
    await focusBoard(page, fx.millBersih.name)

    const before = await readCounts(page)
    const row = lineRow(page, fx.millBersih.name, fx.lineBatal.name)
    await expect(row).toHaveCount(1)

    await clickLive(row.locator(`[data-testid="delete-line-${fx.lineBatal.id}"]`))
    await expect(page.locator('[data-testid="delete-confirm-text"]')).toBeVisible()

    // Tutup konfirmasi TANPA menyetujuinya.
    await clickLive(page.locator('[data-testid="cancel-delete-button"]'))

    // Modal menghilang; barisnya masih ada; keempat angka identik; tidak
    // ada pesan berhasil maupun galat.
    await expect(page.locator('[data-testid="delete-confirm-text"]')).toHaveCount(0)
    await expect(lineRow(page, fx.millBersih.name, fx.lineBatal.name)).toHaveCount(1)
    expect(await readCounts(page)).toEqual(before)
    await expect(page.locator('[data-testid="success-message"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="delete-error"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="delete-refusal"]')).toHaveCount(0)
  })

  test('40. Hapus Entitas Hierarki Master Data — Menghapus Production Line yang stasiunnya masih bersih', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)
    await focusBoard(page, fx.millBersih.name)

    const linesBefore = (await readCounts(page))['production-line']
    const onCardBefore = await lineCountOnCard(page, fx.millBersih.name)

    await clickLive(lineRow(page, fx.millBersih.name, fx.lineBersih.name)
      .locator(`[data-testid="delete-line-${fx.lineBersih.id}"]`))

    // Konfirmasi menyebut nama line DAN memberitahukan bahwa stasiun
    // miliknya ikut terhapus.
    const confirm = page.locator('[data-testid="delete-confirm-text"]')
    await expect(confirm).toContainText(fx.lineBersih.name)
    await expect(confirm).toContainText(/Station/)

    await clickLive(page.locator('[data-testid="confirm-delete-button"]'))

    // Modal tertutup dengan pesan singkat; barisnya hilang dari kartu.
    await expect(page.locator('[data-testid="confirm-delete-button"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="success-message"]')).toBeVisible()
    await expect(lineRow(page, fx.millBersih.name, fx.lineBersih.name)).toHaveCount(0)

    // Jumlah line pada kartu turun satu, angka ringkasan turun satu, dan
    // kartu mill-nya TETAP ADA.
    expect(await lineCountOnCard(page, fx.millBersih.name)).toBe(onCardBefore - 1)
    await expect(page.locator('[data-testid="count-production-line"] .sm-counts__num'))
      .toHaveText(String(linesBefore - 1))
    await expect(millCard(page, fx.millBersih.name)).toHaveCount(1)

    // Dan stasiunnya ikut terhapus dalam satu transaksi.
    expect(await tinkerJson<number>(
      `echo 'OUT=' . json_encode(App\\Models\\Station::where('production_line_id', '${fx.lineBersih.id}')->count());`,
    )).toBe(0)
  })

  test('41. Hapus Entitas Hierarki Master Data — Menghapus Production Line yang stasiunnya sudah dipakai', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)

    // Jumlah penghalangnya diambil dari basis data lebih dulu.
    const blockers = await tinkerJson<{ records: number; stations: number }>(`
      $stationIds = App\\Models\\Station::where('production_line_id', '${fx.lineKotor.id}')->pluck('id');
      echo 'OUT=' . json_encode([
        'records' => App\\Models\\WeighbridgeRecord::whereIn('station_id', $stationIds)->count(),
        'stations' => $stationIds->count(),
      ]);
    `)
    expect(blockers.records).toBeGreaterThan(0)

    await focusBoard(page, fx.millKotor.name)
    const linesBefore = (await readCounts(page))['production-line']

    await clickLive(lineRow(page, fx.millKotor.name, fx.lineKotor.name)
      .locator(`[data-testid="delete-line-${fx.lineKotor.id}"]`))
    await clickLive(page.locator('[data-testid="confirm-delete-button"]'))

    // Modal konfirmasi tetap terlihat, dengan pesan penolakan yang memuat
    // jumlah record stasiun sesuai hitungan basis data.
    await expect(page.locator('[data-testid="confirm-delete-button"]')).toBeVisible()
    const refusal = page.locator('[data-testid="delete-refusal"]')
    await expect(refusal).toBeVisible()
    await expect(refusal).toContainText(`${blockers.records} record stasiun`)

    await clickLive(page.locator('[data-testid="cancel-delete-button"]'))

    // Baris line masih ada di dalam kartu mill; jumlah baris stations
    // miliknya tidak berubah; angka ringkasan tidak berubah.
    await expect(lineRow(page, fx.millKotor.name, fx.lineKotor.name)).toHaveCount(1)
    expect(await tinkerJson<number>(
      `echo 'OUT=' . json_encode(App\\Models\\Station::where('production_line_id', '${fx.lineKotor.id}')->count());`,
    )).toBe(blockers.stations)
    await expect(page.locator('[data-testid="count-production-line"] .sm-counts__num'))
      .toHaveText(String(linesBefore))
  })

  test('42. Hapus Entitas Hierarki Master Data — Entitas sudah dihapus Admin lain', async ({ page, browser }) => {
    problems = watchProblems(page)
    await gotoBoard(page)
    await focusBoard(page, fx.corpGanda.name)

    const corporatesBefore = (await readCounts(page))['corporate']

    // Di A: konfirmasi hapus terbuka.
    await clickLive(summaryRow(page, 'corporate-row', fx.corpGanda.name)
      .locator(`[data-testid="delete-corporate-${fx.corpGanda.id}"]`))
    await expect(page.locator('[data-testid="delete-confirm-text"]')).toContainText(fx.corpGanda.name)

    // Di B: Corporate itu dihapus sampai selesai. Urutan ditentukan spec.
    const other = await secondAdminBoard(browser)
    try {
      await focusBoard(other.page, fx.corpGanda.name)
      await clickLive(summaryRow(other.page, 'corporate-row', fx.corpGanda.name)
        .locator(`[data-testid="delete-corporate-${fx.corpGanda.id}"]`))
      await clickLive(other.page.locator('[data-testid="confirm-delete-button"]'))
      await expect(other.page.locator('[data-testid="success-message"]')).toBeVisible()
    } finally {
      await other.close()
    }

    // Kembali ke A dan setujui konfirmasi.
    await clickLive(page.locator('[data-testid="confirm-delete-button"]'))

    // Kalimat biasa yang menyatakan datanya sudah tidak ada — tanpa stack
    // trace, tanpa halaman galat 500 — dan konfirmasi TERTUTUP.
    await expect(page.locator('[data-testid="confirm-delete-button"]')).toHaveCount(0)
    const notice = page.locator('[data-testid="delete-error"]')
    await expect(notice).toBeVisible()
    await expect(notice).toContainText(/sudah dihapus/i)
    expect(await notice.innerText()).not.toContain('ModelNotFoundException')

    // Barisnya tidak ada lagi, dan angka Corporate berkurang SATU, bukan
    // dua.
    await expect(summaryRow(page, 'corporate-row', fx.corpGanda.name)).toHaveCount(0)
    await expect(page.locator('[data-testid="count-corporate"] .sm-counts__num'))
      .toHaveText(String(corporatesBefore - 1))
  })

  test('43. Hapus Entitas Hierarki Master Data — Menghapus mill terakhir milik sebuah Company', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)
    await focusBoard(page, PREFIX)

    const before = await readCounts(page)

    await clickLive(millCard(page, fx.millTunggal.name)
      .locator(`[data-testid="delete-mill-${fx.millTunggal.id}"]`))
    await clickLive(page.locator('[data-testid="confirm-delete-button"]'))
    await expect(page.locator('[data-testid="success-message"]')).toBeVisible()

    // Kartunya hilang dari papan.
    await expect(millCard(page, fx.millTunggal.name)).toHaveCount(0)

    // Baris Company-nya MASIH terlihat di tabel ringkas, dengan jumlah
    // mill terbaca '0'.
    const row = summaryRow(page, 'company-row', fx.companyTunggal.name)
    await expect(row).toHaveCount(1)
    await expect(row.locator('[data-testid="company-mill-count"]')).toHaveText('0')

    // Angka Mill turun satu, angka Company tetap.
    const after = await readCounts(page)
    expect(after['business-unit']).toBe(before['business-unit'] - 1)
    expect(after['company']).toBe(before['company'])

    // Company itu tidak muncul di breadcrumb kartu mana pun.
    const crumbs = await page.locator('[data-testid="mill-crumb"]').allInnerTexts()
    for (const crumb of crumbs) {
      expect(crumb).not.toContain(fx.companyTunggal.name)
    }
  })

  test('44. Hapus Entitas Hierarki Master Data — Menghapus Production Line terakhir milik sebuah mill', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)
    await focusBoard(page, fx.millSatuLine.name)

    // Scenario 26 added a line to this mill; remove it so the mill really
    // has EXACTLY one line left, whichever order the two tests ran in.
    await tinker(`
      $ids = App\\Models\\ProductionLine::where('business_unit_id', '${fx.millSatuLine.id}')
          ->where('id', '!=', '${fx.lineSatuSatunya.id}')->pluck('id');
      App\\Models\\Station::whereIn('production_line_id', $ids)->delete();
      App\\Models\\ProductionLine::whereIn('id', $ids)->delete();
    `)
    await page.reload()
    await expect(page.locator('[data-testid="counts-bar"]')).toBeVisible()
    await focusBoard(page, fx.millSatuLine.name)

    const linesBefore = (await readCounts(page))['production-line']
    const millsBefore = (await readCounts(page))['business-unit']
    expect(await lineCountOnCard(page, fx.millSatuLine.name)).toBe(1)

    await clickLive(lineRow(page, fx.millSatuLine.name, fx.lineSatuSatunya.name)
      .locator(`[data-testid="delete-line-${fx.lineSatuSatunya.id}"]`))
    await clickLive(page.locator('[data-testid="confirm-delete-button"]'))
    await expect(page.locator('[data-testid="success-message"]')).toBeVisible()

    // TANPA reload: barisnya hilang dan kartu mill TETAP terlihat.
    const card = millCard(page, fx.millSatuLine.name)
    await expect(card).toHaveCount(1)
    await expect(card.locator('[data-testid="line-row"]')).toHaveCount(0)

    // Di dalam kartu kini terbaca keterangan 'belum ada Production Line'
    // beserta tombol tambahnya; jumlah line pada kartu menjadi 0.
    await expect(card.locator('[data-testid="mill-no-lines"]')).toBeVisible()
    await expect(card.locator(`[data-testid="add-line-${fx.millSatuLine.id}"]`)).toBeVisible()
    expect(await lineCountOnCard(page, fx.millSatuLine.name)).toBe(0)

    // Angka Production Line turun satu dan angka Mill tetap.
    const after = await readCounts(page)
    expect(after['production-line']).toBe(linesBefore - 1)
    expect(after['business-unit']).toBe(millsBefore)
  })

  test('45. Hapus Entitas Hierarki Master Data — konfirmasi tidak menyebut nama entitas', async ({ page }) => {
    problems = watchProblems(page)
    await gotoBoard(page)
    await focusBoard(page, PREFIX)

    // (a) Kartu sebuah mill.
    const millNameOnCard = await millCard(page, fx.millSatu.name)
      .locator('[data-testid="mill-name"]').innerText()
    await clickLive(millCard(page, fx.millSatu.name)
      .locator(`[data-testid="delete-mill-${fx.millSatu.id}"]`))
    await expect(page.locator('[data-testid="delete-confirm-text"]')).toContainText(millNameOnCard.trim())
    await expect(millCard(page, fx.millSatu.name).locator(`[data-testid="delete-mill-${fx.millSatu.id}"]`))
      .toHaveAttribute('aria-label', `Hapus Business Unit ${fx.millSatu.name}`)
    await clickLive(page.locator('[data-testid="cancel-delete-button"]'))

    // (b) Sebuah baris Production Line — konfirmasinya juga menyebut
    //     stasiun yang ikut terhapus, dan aria-label tombolnya membedakan
    //     barisnya dengan nama mill induknya.
    const lineLocator = lineRow(page, fx.millPindah.name, fx.linePindah.name)
    const lineNameOnRow = await lineLocator.locator('[data-testid="line-name"]').innerText()
    const deleteLine = lineLocator.locator(`[data-testid="delete-line-${fx.linePindah.id}"]`)
    await expect(deleteLine).toHaveAttribute(
      'aria-label',
      `Hapus Production Line ${fx.linePindah.name} pada Business Unit ${fx.millPindah.name}`,
    )
    await clickLive(deleteLine)
    const confirm = page.locator('[data-testid="delete-confirm-text"]')
    await expect(confirm).toContainText(lineNameOnRow.trim())
    await expect(confirm).toContainText(/Station/)
    await clickLive(page.locator('[data-testid="cancel-delete-button"]'))

    // (c) Sebuah baris di tabel ringkas Corporate.
    const corpRow = summaryRow(page, 'corporate-row', fx.corpInduk.name)
    await expect(corpRow.locator(`[data-testid="delete-corporate-${fx.corpInduk.id}"]`))
      .toHaveAttribute('aria-label', `Hapus Corporate ${fx.corpInduk.name}`)
    await clickLive(corpRow.locator(`[data-testid="delete-corporate-${fx.corpInduk.id}"]`))
    await expect(page.locator('[data-testid="delete-confirm-text"]')).toContainText(fx.corpInduk.name)
    await clickLive(page.locator('[data-testid="cancel-delete-button"]'))

    // (d) Sebuah baris di tabel ringkas Company.
    const companyRow = summaryRow(page, 'company-row', fx.companyInduk.name)
    await expect(companyRow.locator(`[data-testid="delete-company-${fx.companyInduk.id}"]`))
      .toHaveAttribute('aria-label', `Hapus Company ${fx.companyInduk.name}`)
    await clickLive(companyRow.locator(`[data-testid="delete-company-${fx.companyInduk.id}"]`))
    await expect(page.locator('[data-testid="delete-confirm-text"]')).toContainText(fx.companyInduk.name)
    await clickLive(page.locator('[data-testid="cancel-delete-button"]'))

    // Setiap konfirmasi DIBATALKAN — spec ini tidak menulis apa pun.
    await expect(page.locator('[data-testid="delete-confirm-text"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="success-message"]')).toHaveCount(0)
  })

  test('46. Hapus Entitas Hierarki Master Data — peran selain Admin mencoba menghapus', async ({ page }) => {
    problems = watchProblems(page, { allowStatus: [403] })

    const before = await databaseCounts()

    await login(page, NON_ADMIN, PASSWORD)
    const response = await page.goto(PATH)
    expect(response?.status()).toBe(403)

    // Tidak satu pun tombol hapus maupun modal konfirmasi terender.
    await expect(page.locator('[data-testid^="delete-mill-"]')).toHaveCount(0)
    await expect(page.locator('[data-testid^="delete-line-"]')).toHaveCount(0)
    await expect(page.locator('[data-testid^="delete-corporate-"]')).toHaveCount(0)
    await expect(page.locator('[data-testid^="delete-company-"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="delete-confirm-text"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="confirm-delete-button"]')).toHaveCount(0)

    // Jumlah baris keempat tabel hierarki SAMA PERSIS.
    expect(await databaseCounts()).toEqual(before)
  })
})
