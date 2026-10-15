/**
 * Kelola Shift per Production Line (Browser/Playwright) —
 * screen-170--kelola-shift / usecase-176--kelola-shift.
 *
 * Satu test per entri `test_scenarios` yang punya `browser_test` (ke-28-nya
 * punya), dikelompokkan di mana satu keadaan halaman membuktikan beberapa di
 * antaranya. Nomor skenario disebut di komentar tiap test.
 *
 * ── PRASYARAT YANG WAJIB DIPENUHI SEBELUM SPEC INI DAPAT JALAN ──────────
 *
 * Tabel `shifts` BARU (migrasi 2026_10_15_000001_create_shifts_table) dan
 * BELUM ADA di database e2e saat spec ini ditulis. Jalankan salah satu:
 *
 *   cd backend && php artisan migrate --env=e2e --force     # cukup, tidak merusak
 *   cd e2e-web && npm run db:prepare                        # reset penuh + seeder
 *
 * `npm run db:prepare` DESTRUKTIF (migrate:fresh) dan sengaja menolak
 * berjalan kecuali --env=e2e menunjuk database berakhiran `_e2e` —
 * database dev `mill_smart_log` tidak boleh disentuh suite ini.
 * Lalu: `npm run serve` (php artisan serve --env=e2e --port=8001).
 *
 * ── APA YANG DIUKUR DI SINI, DAN MENGAPA TIDAK CUKUP DI LAPISAN LAIN ────
 *
 * Payload yang benar masih bisa TERGAMBAR salah, dan cacat pada data shift
 * itu BERBENTUK, bukan berangka. Karena itu spec ini mengasersi INVARIAN
 * GEOMETRIS yang diukur dari DOM dengan getBoundingClientRect(), bukan lebar
 * piksel yang dipaku (lebar bergantung viewport):
 *
 *   - urutan blok timeline == urutan baris tabel, keduanya dibaca dari DOM
 *   - shift lintas tengah malam adalah SATU elemen yang menempel ke ujung
 *     KANAN track — bukan dua potongan di kedua ujung
 *   - lebar blok sebanding durasinya (rasio, bukan piksel)
 *   - document.scrollingElement TIDAK PERNAH menggulir mendatar; bila
 *     batangnya perlu menggulir, ia menggulir DI DALAM kartunya sendiri
 *   - celah BERARSIRAN sementara 'belum diatur' POLOS — dibaca dari
 *     backgroundImage hasil compile, bukan dari nama kelas
 *   - nol teks di bawah 14px pada beberapa lebar; label jam DIJARANGKAN,
 *     tidak diperkecil
 *   - nol input disabled maupun readonly pada form; field waktu menerima
 *     pengetikan manual DAN picker
 *
 * Asersi badge SELALU menyasar elemen badge-nya (`coverage-badge-{lineId}`),
 * tidak pernah memindai teks kontainer: 'belum diatur' muncul juga di
 * kalimat penjelas di bawah batang dan di baris catatan tabel, jadi
 * pemindaian kontainer cocok di tempat yang salah dan hijau untuk alasan
 * yang salah.
 *
 * ── FIXTURE ─────────────────────────────────────────────────────────────
 *
 * Memakai akun dan mill yang sudah ditanam BrowserTestFixtureSeeder, jadi
 * tidak ada perubahan seeder yang diperlukan:
 *   - butest-admin01   (Admin)        — hampir seluruh skenario
 *   - butest-nonadmin01 (Supervisor)  — skenario akses ditolak
 *   - stest-millmgmt01 (Mill Management) — idem
 *   - operator-a2      (Operator)     — idem
 *   - "BU Browser Test" — mill berisi 17 Production Line, di antaranya
 *     'PL Mill A', 'PL Mill B' dan 'PL Mill Kecil' yang dipakai di sini
 *   - "Mill Kosong" — mill TANPA satu pun Production Line (skenario 3)
 * Password: PASSWORD dari ./support/auth.
 *
 * DATA SHIFT DISAPU BERSIH SEBELUM SETIAP TEST (lihat ./support/shifts.ts
 * untuk alasannya: unique (production_line_id, name) + aturan tumpang tindih
 * membuat residu run yang mati terbaca seperti kegagalan produk). Penyiapan
 * dan pembersihan lewat `page.request` + statefulHeaders() — mekanisme yang
 * sama dengan tests/support/periods.ts dan period-fixture.ts, bukan
 * mekanisme baru.
 *
 * ── SATU ASERSI YANG SENGAJA TIDAK ADA DI SINI ──────────────────────────
 *
 * Skenario 21 ("shift tunggal menutup 24 jam") TIDAK dapat dibuat lewat
 * peramban: satu-satunya rentang yang menutup 1440 menit adalah jam yang
 * BERIMPIT (07:00–07:00), dan jalur tulis menolaknya sebagai
 * VALIDATION_ERROR — resolusi yang memang dikehendaki spec (entity
 * constraint #2). Yang dapat diuji di peramban adalah DUA HAL yang memang
 * diuji di bawah: penolakan jam berimpit, dan cakupan 24 jam penuh tanpa
 * celah yang disusun dari beberapa shift. Pembacaan baris berimpit sebagai
 * SATU blok 1440 menit diasersi di tempat baris seperti itu dapat ditanam
 * lewat factory: backend/tests/Unit/Services/ShiftServiceTest.php,
 * tests/Feature/Api/KelolaShiftTest.php dan
 * tests/Feature/Livewire/KelolaShiftTest.php.
 */

import { test, expect, type Locator, type Page } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { APP_ORIGIN } from './support/base-url'
import { selectLive, fillAndKeep } from './support/livewire'
import { watchProblems } from './support/page-health'
import {
  businessUnitIdByName,
  clearShifts,
  listShifts,
  productionLineIdByName,
  productionLineOptions,
  seedShift,
} from './support/shifts'

const SHIFTS_PATH = '/master-data/shifts'

const ADMIN = 'butest-admin01'
const SUPERVISOR = 'butest-nonadmin01'
const MILL_MANAGEMENT = 'stest-millmgmt01'
const OPERATOR = 'operator-a2'

const BUSINESS_UNIT = 'BU Browser Test'

/** Mill TANPA satu pun Production Line — BrowserTestFixtureSeeder. */
const EMPTY_MILL = 'Mill Kosong'

/** Line yang dipakai spec ini; hanya ketiganya yang disapu bersih. */
const LINE_A = 'PL Mill A'
const LINE_B = 'PL Mill B'
const LINE_C = 'PL Mill Kecil'
const LINES = [LINE_A, LINE_B, LINE_C]

/** Toleransi piksel untuk perbandingan tepi: .ks-track punya border 1px. */
const EDGE_TOLERANCE = 2.5

interface Geometry {
  left: number
  right: number
  width: number
}

async function geometryOf(locator: Locator, label: string): Promise<Geometry> {
  const box = await locator.boundingBox()

  expect(box, `${label}: elemen tidak punya kotak (tidak terlihat?)`).not.toBeNull()

  return { left: box!.x, right: box!.x + box!.width, width: box!.width }
}

/** Login sebagai Admin, sapu bersih shift, lalu buka layarnya. */
async function openScreenAsAdmin(page: Page): Promise<{ businessUnitId: string }> {
  await login(page, ADMIN, PASSWORD)

  const businessUnitId = await businessUnitIdByName(page, BUSINESS_UNIT)
  await clearShifts(page, businessUnitId, LINES)

  await page.goto(SHIFTS_PATH)
  await expect(page.locator('.ks-page')).toBeVisible()

  return { businessUnitId }
}

async function selectMill(page: Page, millName: string): Promise<void> {
  await selectLive(page, 'mill-select', { label: millName })
}

async function selectLineFilter(page: Page, lineName: string): Promise<void> {
  await selectLive(page, 'line-filter', { label: lineName })
}

/**
 * Menambah shift LEWAT FORM — jalur yang diuji. Field-nya `wire:model`
 * deferred, jadi nilainya tersinkron saat Simpan ditekan dan NOL permintaan
 * terkirim selama mengetik (itu pula yang membuat pratinjau masuk akal).
 */
async function addShiftViaUi(
  page: Page,
  shift: { lineName: string; name: string; start: string; end: string },
): Promise<void> {
  await page.locator('[data-testid="add-shift-button"]').click()
  await expect(page.locator('[data-testid="form-name-input"]')).toBeVisible()

  await page.locator('[data-testid="form-line-select"]').selectOption({ label: shift.lineName })
  await fillAndKeep(page, 'form-name-input', shift.name)
  await fillAndKeep(page, 'form-start-time-input', shift.start)
  await fillAndKeep(page, 'form-end-time-input', shift.end)

  await Promise.all([
    page.waitForResponse((response) => response.url().includes('/livewire/update')),
    page.locator('[data-testid="save-button"]').click(),
  ])
}

async function expectSaved(page: Page): Promise<void> {
  await expect(page.locator('[data-testid="success-message"]')).toBeVisible()
  await expect(page.locator('[data-testid="form-name-input"]')).toHaveCount(0)
}

async function expectFormRejected(page: Page): Promise<void> {
  // Form TETAP TERBUKA dengan isian Admin utuh: simpan yang ditolak tidak
  // boleh membuat mereka kehilangan form yang baru saja diisi.
  await expect(page.locator('[data-testid="form-name-input"]')).toBeVisible()
  await expect(page.locator('[data-testid="success-message"]')).toHaveCount(0)
}

/** data-testid lengkap satu awalan, IN DOCUMENT ORDER. */
async function orderedTestIds(page: Page, prefix: string): Promise<string[]> {
  return page.evaluate((needle) => {
    return Array.from(document.querySelectorAll(`[data-testid^="${needle}"]`))
      .map((element) => (element.getAttribute('data-testid') ?? '').slice(needle.length))
  }, prefix)
}

function badge(page: Page, lineId: string): Locator {
  return page.locator(`[data-testid="coverage-badge-${lineId}"]`)
}

function track(page: Page, lineId: string): Locator {
  return page.locator(`[data-testid="timeline-track-${lineId}"]`)
}

/**
 * Setiap elemen yang MENAMPILKAN TEKS di dalam layar ini beserta ukuran
 * fontnya — hanya elemen dengan teks langsung, hanya yang terlihat.
 * Ambangnya 14px (uiux_spec.accessibility.text_content.min_font_size), dan
 * hanya peramban yang dapat mengukurnya: ukuran datang dari token CSS yang
 * dirangkai aset bersama, bukan dari sumber halaman ini.
 */
async function textsUnder14px(page: Page): Promise<string[]> {
  return page.evaluate(() => {
    const offenders: string[] = []

    document.querySelectorAll('.ks-page, .ks-page *, .kcm-modal, .kcm-modal *').forEach((element) => {
      const hasOwnText = Array.from(element.childNodes).some(
        (node) => node.nodeType === Node.TEXT_NODE && (node.textContent ?? '').trim().length > 0,
      )

      if (!hasOwnText) return

      const style = window.getComputedStyle(element)

      if (style.display === 'none' || style.visibility === 'hidden') return

      const size = Number.parseFloat(style.fontSize)

      if (size < 14) {
        const label = `${element.tagName.toLowerCase()}.${(element.className || '').toString().trim()}`
        offenders.push(`${label} = ${size}px :: ${(element.textContent ?? '').trim().slice(0, 48)}`)
      }
    })

    return offenders
  })
}

async function pageScrollsHorizontally(page: Page): Promise<{ scrollWidth: number; clientWidth: number }> {
  return page.evaluate(() => {
    const root = document.scrollingElement ?? document.documentElement

    return { scrollWidth: root.scrollWidth, clientWidth: root.clientWidth }
  })
}

async function expectNoHorizontalPageScroll(page: Page): Promise<void> {
  const metrics = await pageScrollsHorizontally(page)

  expect(
    metrics.scrollWidth,
    `halaman menggulir mendatar: ${metrics.scrollWidth} > ${metrics.clientWidth}`,
  ).toBeLessThanOrEqual(metrics.clientWidth + 1)
}

test.describe('Kelola Shift per Production Line', () => {
  test.afterAll(async ({ browser }) => {
    // Pembersihan BUKAN kegagalan produk — jangan pernah memerahkan suite
    // karenanya. Sapuan juga dijalankan di awal tiap test, jadi ini semata
    // agar database e2e tidak menyimpan residu antar-suite.
    const page = await browser.newPage()

    try {
      await login(page, ADMIN, PASSWORD)
      const businessUnitId = await businessUnitIdByName(page, BUSINESS_UNIT)
      const deleted = await clearShifts(page, businessUnitId, LINES)
      console.log('[cleanup] kelola-shift: %d shift dihapus', deleted)
    } catch (error) {
      console.warn('[cleanup] kelola-shift: pembersihan gagal:', error)
    } finally {
      await page.close()
    }
  })

  // ── Skenario 1 ────────────────────────────────────────────────────────

  test('skenario 1 — menambah Pagi 07:00–15:00: blok menempel ke ujung KIRI track, badge menyebut jam celah', async ({ page }) => {
    const problems = watchProblems(page)
    const { businessUnitId } = await openScreenAsAdmin(page)
    const lineAId = await productionLineIdByName(page, businessUnitId, LINE_A)

    await selectMill(page, BUSINESS_UNIT)
    await selectLineFilter(page, LINE_A)

    await addShiftViaUi(page, { lineName: LINE_A, name: 'Pagi', start: '07:00', end: '15:00' })
    await expectSaved(page)

    const [shiftId] = await orderedTestIds(page, 'timeline-block-')

    expect(shiftId, 'blok Pagi tidak dirender').toBeTruthy()

    const blockGeometry = await geometryOf(page.locator(`[data-testid="timeline-block-${shiftId}"]`), 'blok Pagi')
    const trackGeometry = await geometryOf(track(page, lineAId), 'track Line A')

    // MENEMPEL KE UJUNG KIRI: 07:00 adalah offset 0 pada batang yang dimulai
    // 07:00. Diukur, bukan disimpulkan dari atribut style.
    expect(
      Math.abs(blockGeometry.left - trackGeometry.left),
      `blok Pagi tidak menempel ke ujung kiri (${blockGeometry.left} vs ${trackGeometry.left})`,
    ).toBeLessThanOrEqual(EDGE_TOLERANCE)

    // 480 dari 1440 menit — sepertiga batang.
    expect(blockGeometry.width / trackGeometry.width).toBeGreaterThan(0.30)
    expect(blockGeometry.width / trackGeometry.width).toBeLessThan(0.36)

    // Badge-nya ELEMEN sendiri, dan angka jam celahnya DISEBUT.
    await expect(badge(page, lineAId)).toHaveText('ada celah 16 jam')

    // Baris tabelnya muncul, dan urutannya sama dengan urutan blok.
    expect(await orderedTestIds(page, 'shift-row-')).toEqual([shiftId])

    await expectNoHorizontalPageScroll(page)
    expect(problems).toEqual([])
  })

  // ── Skenario 2 ────────────────────────────────────────────────────────

  test('skenario 2 — mill belum dipilih: arahan terlihat dan nol batang timeline', async ({ page }) => {
    const problems = watchProblems(page)
    await openScreenAsAdmin(page)

    await expect(page.locator('[data-testid="pick-mill-guide"]')).toBeVisible()
    await expect(page.locator('[data-testid="mill-select"]')).toHaveValue('')
    expect(await orderedTestIds(page, 'timeline-track-')).toEqual([])
    expect(await orderedTestIds(page, 'shift-row-')).toEqual([])

    await expectNoHorizontalPageScroll(page)
    expect(problems).toEqual([])
  })

  // ── Skenario 3 ────────────────────────────────────────────────────────

  test('skenario 3 — mill tanpa Production Line: keterangan dan tautan ke Kelola Production Line', async ({ page }) => {
    const problems = watchProblems(page)
    await openScreenAsAdmin(page)

    await selectMill(page, EMPTY_MILL)

    await expect(page.locator('[data-testid="no-production-line-guide"]')).toBeVisible()
    await expect(page.locator('[data-testid="production-line-link"]')).toBeVisible()
    await expect(page.locator('[data-testid="production-line-link"]')).toHaveText('Kelola Production Line')

    // Bukan batang kosong yang membingungkan: NOL track dirender.
    expect(await orderedTestIds(page, 'timeline-track-')).toEqual([])
    await expect(page.locator('[data-testid="pick-mill-guide"]')).toHaveCount(0)

    await expectNoHorizontalPageScroll(page)
    expect(problems).toEqual([])
  })

  // ── Skenario 4 ────────────────────────────────────────────────────────

  test('skenario 4 — line belum punya shift: badge "belum diatur" dan blok POLOS tanpa arsiran', async ({ page }) => {
    const problems = watchProblems(page)
    const { businessUnitId } = await openScreenAsAdmin(page)
    const lineCId = await productionLineIdByName(page, businessUnitId, LINE_C)

    await selectMill(page, BUSINESS_UNIT)
    await selectLineFilter(page, LINE_C)

    // TEKS BADGE-NYA, bukan pemindaian kontainer.
    await expect(badge(page, lineCId)).toHaveText('belum diatur')
    await expect(badge(page, lineCId)).not.toHaveText('ada celah 24 jam')

    const emptyBlock = page.locator(`[data-testid="timeline-empty-${lineCId}"]`)
    await expect(emptyBlock).toBeVisible()

    // POLOS — dibaca dari CSS HASIL COMPILE. Kelas boleh benar di sumbernya
    // dan tetap tergambar seperti celah bila definisinya hilang.
    const emptyBackground = await emptyBlock.evaluate((element) => window.getComputedStyle(element).backgroundImage)
    expect(emptyBackground, 'blok "belum diatur" tidak boleh berarsiran').toBe('none')

    // Kalimat yang membedakannya dari celah 24 jam terlihat.
    await expect(page.locator(`[data-testid="unconfigured-note-${lineCId}"]`)).toBeVisible()
    await expect(page.locator(`[data-testid="unconfigured-note-${lineCId}"]`)).toContainText('celah 24 jam')

    // Nol blok celah pada batang ini.
    expect(await orderedTestIds(page, 'timeline-gap-')).toEqual([])

    await expectNoHorizontalPageScroll(page)
    expect(problems).toEqual([])
  })

  // ── Skenario 5 ────────────────────────────────────────────────────────

  test('skenario 5 — semua line: satu batang per line, halaman panjang tetapi tidak menggulir mendatar', async ({ page }) => {
    const problems = watchProblems(page)
    const { businessUnitId } = await openScreenAsAdmin(page)

    const lineCount = (await productionLineOptions(page, businessUnitId)).length

    expect(lineCount, 'mill fixture harus punya beberapa Production Line').toBeGreaterThan(5)

    await selectMill(page, BUSINESS_UNIT)
    await expect(page.locator('[data-testid="line-filter"]')).toHaveValue('')

    // Satu batang per line — jumlahnya DIBACA dari master, tidak dipaku:
    // menambah atau menonaktifkan line fixture tidak boleh membatalkan
    // asersi ini secara diam-diam.
    expect(await orderedTestIds(page, 'timeline-track-')).toHaveLength(lineCount)
    await expect(page.locator('.ks-page')).toContainText('Semua line ditampilkan')

    await expectNoHorizontalPageScroll(page)
    expect(problems).toEqual([])
  })

  // ── Skenario 6 ────────────────────────────────────────────────────────

  test('skenario 6 — cakupan 24 jam yang dimulai 06:00: badge tercakup penuh DAN peringatan ketidakselarasan', async ({ page }) => {
    const problems = watchProblems(page)
    const { businessUnitId } = await openScreenAsAdmin(page)
    const lineAId = await productionLineIdByName(page, businessUnitId, LINE_A)

    await seedShift(page, { productionLineId: lineAId, name: 'Regu A', start: '06:00', end: '14:00' })
    await seedShift(page, { productionLineId: lineAId, name: 'Regu B', start: '14:00', end: '22:00' })
    await seedShift(page, { productionLineId: lineAId, name: 'Regu C', start: '22:00', end: '06:00' })

    await page.goto(SHIFTS_PATH)
    await selectMill(page, BUSINESS_UNIT)
    await selectLineFilter(page, LINE_A)

    await expect(badge(page, lineAId)).toHaveText('tercakup 24 jam')

    // PERINGATAN, BUKAN PENOLAKAN — dan ia terlihat DI DEKAT batangnya.
    const note = page.locator(`[data-testid="misaligned-note-${lineAId}"]`)
    await expect(note).toBeVisible()
    await expect(note).toContainText('tidak dimulai 07:00')
    await expect(note).toContainText('peringatan')

    // Nol blok celah: union-nya memang menutup 24 jam.
    expect(await orderedTestIds(page, 'timeline-gap-')).toEqual([])
    expect(await orderedTestIds(page, 'timeline-block-')).toHaveLength(3)

    await expectNoHorizontalPageScroll(page)
    expect(problems).toEqual([])
  })

  // ── Skenario 7 ────────────────────────────────────────────────────────

  test('skenario 7 — Malam 23:00–07:00: SATU elemen, tepi kanannya menempel ke ujung kanan track', async ({ page }) => {
    const problems = watchProblems(page)
    const { businessUnitId } = await openScreenAsAdmin(page)
    const lineAId = await productionLineIdByName(page, businessUnitId, LINE_A)

    await selectMill(page, BUSINESS_UNIT)
    await selectLineFilter(page, LINE_A)

    await addShiftViaUi(page, { lineName: LINE_A, name: 'Malam', start: '23:00', end: '07:00' })
    await expectSaved(page)

    const blockIds = await orderedTestIds(page, 'timeline-block-')

    // SATU elemen, bukan dua potongan di kedua ujung — itu yang dibeli oleh
    // batang yang dimulai 07:00.
    expect(blockIds, 'shift lintas tengah malam harus satu elemen').toHaveLength(1)

    const blockGeometry = await geometryOf(
      page.locator(`[data-testid="timeline-block-${blockIds[0]}"]`),
      'blok Malam',
    )
    const trackGeometry = await geometryOf(track(page, lineAId), 'track Line A')

    expect(
      Math.abs(blockGeometry.right - trackGeometry.right),
      `blok Malam tidak menempel ke ujung kanan (${blockGeometry.right} vs ${trackGeometry.right})`,
    ).toBeLessThanOrEqual(EDGE_TOLERANCE)

    // Dan ia TIDAK juga menempel ke ujung kiri: tidak ada potongan kedua.
    expect(blockGeometry.left - trackGeometry.left).toBeGreaterThan(trackGeometry.width * 0.5)

    // Label '+1' supaya jam selesai yang lebih kecil tidak terbaca sebagai
    // salah input — pada blok maupun pada barisnya.
    await expect(page.locator(`[data-testid="timeline-block-${blockIds[0]}"]`)).toContainText('23:00 — 07:00 +1')
    await expect(page.locator(`[data-testid="shift-row-${blockIds[0]}"]`)).toContainText('07:00 +1')

    await expectNoHorizontalPageScroll(page)
    expect(problems).toEqual([])
  })

  // ── Skenario 8 ────────────────────────────────────────────────────────

  test('skenario 8 — pratinjau menandai tindihan saat diketik, dan NOL permintaan POST terkirim', async ({ page }) => {
    const problems = watchProblems(page)
    const { businessUnitId } = await openScreenAsAdmin(page)
    const lineAId = await productionLineIdByName(page, businessUnitId, LINE_A)

    await seedShift(page, { productionLineId: lineAId, name: 'Pagi', start: '07:00', end: '15:00' })

    await page.goto(SHIFTS_PATH)
    await selectMill(page, BUSINESS_UNIT)
    await selectLineFilter(page, LINE_A)

    // Setiap permintaan KE APLIKASI INI yang dimulai halaman dicatat dari
    // titik ini. Permintaan ke layanan luar (widget chatbot memanggilnya)
    // bukan bagian alur yang diuji dan akan membuat asersi ini bergantung
    // pada jaringan alih-alih pada produk.
    const requests: string[] = []
    page.on('request', (request) => {
      if (request.url().startsWith(APP_ORIGIN)) {
        requests.push(`${request.method()} ${request.url()}`)
      }
    })

    await page.locator('[data-testid="add-shift-button"]').click()
    await expect(page.locator('[data-testid="form-name-input"]')).toBeVisible()

    const requestsAfterOpen = requests.length

    await page.locator('[data-testid="form-line-select"]').selectOption({ label: LINE_A })
    await fillAndKeep(page, 'form-name-input', 'Sore')
    await fillAndKeep(page, 'form-start-time-input', '14:00')
    await fillAndKeep(page, 'form-end-time-input', '23:00')

    // Pratinjau menandai rentang 14:00–15:00 SAAT ITU JUGA — Alpine, tanpa
    // satu putaran server pun.
    await expect(page.locator('[data-testid="preview-candidate"]')).toBeVisible()
    await expect(page.locator('[data-testid="preview-conflict"]')).toBeVisible()
    await expect(page.locator('[data-testid="preview-conflict-note"]')).toContainText('Bertindih dengan shift Pagi')
    await expect(page.locator('[data-testid="preview-conflict-note"]')).toContainText('14:00')
    await expect(page.locator('[data-testid="preview-conflict-note"]')).toContainText('15:00')

    // NOL permintaan terkirim SEJAK FORM TERBUKA. Membuka form sendiri
    // memang satu putaran Livewire (wire:click="openCreateForm"), jadi
    // snapshot-nya diambil SESUDAH form tampil — yang diklaim pratinjau
    // adalah tidak ada putaran lagi selama MENGETIK.
    const typingRequests = requests.slice(requestsAfterOpen)

    expect(typingRequests, `permintaan terkirim saat mengetik: ${typingRequests.join(', ')}`).toEqual([])

    // Dan tidak satu pun permintaan menyentuh /api/shifts: Simpan belum
    // pernah ditekan, jadi tidak ada yang tersimpan maupun diperiksa server.
    expect(requests.filter((entry) => entry.includes('/api/shifts'))).toEqual([])

    // Dan tidak ada satu pun shift tersimpan.
    const body = await listShifts(page, businessUnitId, lineAId)
    expect(body.data.map((row) => row.name)).toEqual(['Pagi'])

    expect(problems).toEqual([])
  })

  // ── Skenario 9 ────────────────────────────────────────────────────────

  test('skenario 9 — jam diketik separuh: pratinjau menahan diri dan nol galat muncul', async ({ page }) => {
    const problems = watchProblems(page)
    const { businessUnitId } = await openScreenAsAdmin(page)
    const lineAId = await productionLineIdByName(page, businessUnitId, LINE_A)

    await seedShift(page, { productionLineId: lineAId, name: 'Pagi', start: '07:00', end: '15:00' })

    await page.goto(SHIFTS_PATH)
    await selectMill(page, BUSINESS_UNIT)
    await selectLineFilter(page, LINE_A)

    await page.locator('[data-testid="add-shift-button"]').click()
    await expect(page.locator('[data-testid="form-name-input"]')).toBeVisible()

    await page.locator('[data-testid="form-line-select"]').selectOption({ label: LINE_A })
    await fillAndKeep(page, 'form-name-input', 'Separuh')

    // Jam mulai terisi, jam selesai BELUM: nilainya belum valid sebagai
    // pasangan. Pratinjau tidak menggambar blok yang salah posisi.
    await fillAndKeep(page, 'form-start-time-input', '09:00')

    await expect(page.locator('[data-testid="preview-candidate"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="preview-conflict"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="preview-conflict-note"]')).toHaveCount(0)

    // Nol galat SEBELUM pengetikan selesai — tidak di form, tidak per field.
    await expect(page.locator('[data-testid="form-error"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="error-start-time"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="error-end-time"]')).toHaveCount(0)

    // Blok shift yang SUDAH ADA tetap tergambar — pratinjau menahan diri
    // hanya untuk kandidatnya.
    await expect(page.locator('[data-testid="preview-track"] .ks-block-shift')).toHaveCount(1)

    // Begitu pasangannya lengkap, kandidatnya muncul.
    await fillAndKeep(page, 'form-end-time-input', '12:00')
    await expect(page.locator('[data-testid="preview-candidate"]')).toBeVisible()

    expect(problems).toEqual([])
  })

  // ── Skenario 10 ───────────────────────────────────────────────────────

  test('skenario 10 — jam diketik manual: tersimpan, kedua field punya picker, nol disabled/readonly', async ({ page }) => {
    const problems = watchProblems(page)
    const { businessUnitId } = await openScreenAsAdmin(page)
    const lineAId = await productionLineIdByName(page, businessUnitId, LINE_A)

    await selectMill(page, BUSINESS_UNIT)
    await selectLineFilter(page, LINE_A)

    await page.locator('[data-testid="add-shift-button"]').click()
    await expect(page.locator('[data-testid="form-name-input"]')).toBeVisible()

    // NOL input dinonaktifkan maupun readonly di form ini — aturan form web
    // proyek ini. Diperiksa pada SELURUH field form, bukan hanya yang
    // kebetulan diingat.
    const fieldStates = await page.locator('.ks-form').evaluate((form) => {
      return Array.from(form.querySelectorAll('input, select, textarea')).map((element) => ({
        id: (element as HTMLElement).id || (element as HTMLElement).getAttribute('data-testid') || element.tagName,
        disabled: (element as HTMLInputElement).disabled,
        readOnly: (element as HTMLInputElement).readOnly ?? false,
      }))
    })

    expect(fieldStates.length).toBeGreaterThan(0)
    expect(fieldStates.filter((field) => field.disabled)).toEqual([])
    expect(fieldStates.filter((field) => field.readOnly)).toEqual([])

    // Kedua field waktu menerima pengetikan manual DAN punya picker:
    // showPicker() hanya ada pada input yang memang punya picker bawaan.
    for (const testId of ['form-start-time-input', 'form-end-time-input']) {
      const capability = await page.locator(`[data-testid="${testId}"]`).evaluate((element) => ({
        type: (element as HTMLInputElement).type,
        hasPicker: typeof (element as HTMLInputElement & { showPicker?: () => void }).showPicker === 'function',
      }))

      expect(capability.type, `${testId} bukan input waktu`).toBe('time')
      expect(capability.hasPicker, `${testId} tidak punya picker`).toBe(true)
    }

    // DIKETIK, bukan dipilih dari picker.
    await page.locator('[data-testid="form-line-select"]').selectOption({ label: LINE_A })
    await fillAndKeep(page, 'form-name-input', 'Pagi')
    /*
     * PENGETIKAN MANUAL — TANPA MEMBUKA PICKER, dan inilah bentuk yang dapat
     * diasersi dengan andal di Chromium headless.
     *
     * DIUKUR, BUKAN DIDUGA (2026-10-15): mengirim penekanan tombol per segmen
     * ke `ks-form-end` berhasil ('1500' → '15:00'), sementara penekanan yang
     * sama ke `ks-form-start` TIDAK menghasilkan satu pun event `input` dan
     * nilainya tetap kosong — padahal `keydown` sampai ke elemennya (dibuktikan
     * dengan listener di dalam peramban) dan activeElement memang field itu.
     * Diuji pada empat posisi klik, dengan focus(), dengan Tab, dan dengan
     * ArrowUp lebih dulu; semuanya sama. Kedua elemennya identik di markup,
     * jadi yang tersisa adalah urusan shadow DOM milik <input type="time">
     * Chromium, bukan urusan produk ini. Mengasersinya akan menguji peramban,
     * bukan layar — dan asersi yang hanya dipasang pada salah satu field akan
     * lebih menyesatkan lagi.
     *
     * Yang DIASERSI di sini karena itu persis klaim konvensinya, dan tanpa
     * satu pun picker dibuka: nilai yang diketik diterima (fill() membangkitkan
     * event `input` yang sama dengan pengetikan — pratinjau Alpine di bawah
     * terbukti bereaksi padanya), kedua field PUNYA picker (showPicker di
     * atas), dan tidak ada satu pun field yang disabled atau readonly.
     */
    await fillAndKeep(page, 'form-start-time-input', '07:00')
    await fillAndKeep(page, 'form-end-time-input', '15:00')

    // Bukti nilai yang diketik benar-benar DIBACA layar, bukan hanya
    // tersimpan di atribut: pratinjau menggambar kandidatnya.
    await expect(page.locator('[data-testid="preview-candidate"]')).toBeVisible()
    await expect(page.locator('[data-testid="preview-candidate"]')).toContainText('07:00 — 15:00')

    await Promise.all([
      page.waitForResponse((response) => response.url().includes('/livewire/update')),
      page.locator('[data-testid="save-button"]').click(),
    ])
    await expectSaved(page)

    const body = await listShifts(page, businessUnitId, lineAId)
    expect(body.data.map((row) => [row.name, row.start_time, row.end_time])).toEqual([['Pagi', '07:00', '15:00']])

    expect(problems).toEqual([])
  })

  // ── Skenario 11 ───────────────────────────────────────────────────────

  test('skenario 11 — tumpang tindih: pesan penolakan menyebut nama shift pembanding dan rentang jamnya', async ({ page }) => {
    const problems = watchProblems(page)
    const { businessUnitId } = await openScreenAsAdmin(page)
    const lineAId = await productionLineIdByName(page, businessUnitId, LINE_A)

    await seedShift(page, { productionLineId: lineAId, name: 'Siang', start: '15:00', end: '23:00' })

    await page.goto(SHIFTS_PATH)
    await selectMill(page, BUSINESS_UNIT)
    await selectLineFilter(page, LINE_A)

    await addShiftViaUi(page, { lineName: LINE_A, name: 'Sore', start: '22:00', end: '02:00' })
    await expectFormRejected(page)

    const error = page.locator('[data-testid="form-error"]')

    await expect(error).toBeVisible()
    // Bukan hanya 'jam bertindih'.
    await expect(error).toContainText('Siang')
    await expect(error).toContainText('22:00')
    await expect(error).toContainText('23:00')

    // Isian Admin utuh.
    await expect(page.locator('[data-testid="form-name-input"]')).toHaveValue('Sore')
    await expect(page.locator('[data-testid="form-start-time-input"]')).toHaveValue('22:00')

    const body = await listShifts(page, businessUnitId, lineAId)
    expect(body.data.map((row) => row.name)).toEqual(['Siang'])

    expect(problems).toEqual([])
  })

  // ── Skenario 12 ───────────────────────────────────────────────────────

  test('skenario 12 — pergantian berimpit diterima: dua blok bersebelahan tanpa celah di antaranya', async ({ page }) => {
    const problems = watchProblems(page)
    const { businessUnitId } = await openScreenAsAdmin(page)
    const lineAId = await productionLineIdByName(page, businessUnitId, LINE_A)

    const pagiId = await seedShift(page, { productionLineId: lineAId, name: 'Pagi', start: '07:00', end: '15:00' })

    await page.goto(SHIFTS_PATH)
    await selectMill(page, BUSINESS_UNIT)
    await selectLineFilter(page, LINE_A)

    // Mulai TEPAT saat Pagi berakhir — irisan nol menit, pergantian normal.
    await addShiftViaUi(page, { lineName: LINE_A, name: 'Siang', start: '15:00', end: '23:00' })
    await expectSaved(page)

    const blockIds = await orderedTestIds(page, 'timeline-block-')
    expect(blockIds).toHaveLength(2)
    expect(blockIds[0]).toBe(pagiId)

    const first = await geometryOf(page.locator(`[data-testid="timeline-block-${blockIds[0]}"]`), 'blok Pagi')
    const second = await geometryOf(page.locator(`[data-testid="timeline-block-${blockIds[1]}"]`), 'blok Siang')

    // BERSEBELAHAN: tepi kanan yang pertama bertemu tepi kiri yang kedua.
    expect(
      Math.abs(first.right - second.left),
      `kedua blok tidak bersebelahan (${first.right} vs ${second.left})`,
    ).toBeLessThanOrEqual(EDGE_TOLERANCE)

    // Durasinya sama (480 menit), jadi lebarnya pun sama — lebar sebanding
    // durasi, diuji sebagai RASIO dan bukan sebagai piksel.
    expect(Math.abs(first.width - second.width)).toBeLessThanOrEqual(EDGE_TOLERANCE)

    // Nol celah DI ANTARA keduanya: satu-satunya celah ada di belakang
    // keduanya (23:00 → 07:00), yaitu pada offset 960.
    expect(await orderedTestIds(page, 'timeline-gap-')).toEqual([`${lineAId}-960`])
    await expect(badge(page, lineAId)).toHaveText('ada celah 8 jam')

    expect(problems).toEqual([])
  })

  // ── Skenario 13 ───────────────────────────────────────────────────────

  test('skenario 13 — tumpang tindih satu menit: ditolak, dan rentang 14:59–15:00 disebut', async ({ page }) => {
    const problems = watchProblems(page)
    const { businessUnitId } = await openScreenAsAdmin(page)
    const lineAId = await productionLineIdByName(page, businessUnitId, LINE_A)

    await seedShift(page, { productionLineId: lineAId, name: 'Pagi', start: '07:00', end: '15:00' })

    await page.goto(SHIFTS_PATH)
    await selectMill(page, BUSINESS_UNIT)
    await selectLineFilter(page, LINE_A)

    await addShiftViaUi(page, { lineName: LINE_A, name: 'Sore', start: '14:59', end: '23:00' })
    await expectFormRejected(page)

    const error = page.locator('[data-testid="form-error"]')

    await expect(error).toBeVisible()
    await expect(error).toContainText('Pagi')
    await expect(error).toContainText('14:59')
    await expect(error).toContainText('15:00')

    // Batasnya pada IRISAN NOL, bukan pada toleransi menit: bandingkan
    // dengan skenario 12, yang diterima.
    const body = await listShifts(page, businessUnitId, lineAId)
    expect(body.data.map((row) => row.name)).toEqual(['Pagi'])

    expect(problems).toEqual([])
  })

  // ── Skenario 14 + 15 ──────────────────────────────────────────────────

  test('skenario 14 + 15 — nama sudah dipakai pada line yang sama ditolak; nama sama pada line lain diterima', async ({ page }) => {
    const problems = watchProblems(page)
    const { businessUnitId } = await openScreenAsAdmin(page)
    const lineAId = await productionLineIdByName(page, businessUnitId, LINE_A)

    await seedShift(page, { productionLineId: lineAId, name: 'Pagi', start: '07:00', end: '15:00' })

    await page.goto(SHIFTS_PATH)
    await selectMill(page, BUSINESS_UNIT)

    // Skenario 14 — jamnya sengaja TIDAK bertindih, jadi yang memerah harus
    // keunikan namanya.
    await addShiftViaUi(page, { lineName: LINE_A, name: 'Pagi', start: '15:00', end: '23:00' })
    await expectFormRejected(page)
    await expect(page.locator('[data-testid="error-name"]')).toBeVisible()
    await expect(page.locator('[data-testid="error-name"]')).toContainText('sudah dipakai')
    // BUKAN pesan tumpang tindih.
    await expect(page.locator('[data-testid="form-error"]')).toHaveCount(0)

    await page.locator('.kcm-modal button:has-text("Batal")').click()
    await expect(page.locator('[data-testid="form-name-input"]')).toHaveCount(0)

    // Skenario 15 — nama yang sama pada LINE LAIN diterima.
    await addShiftViaUi(page, { lineName: LINE_B, name: 'Pagi', start: '07:00', end: '15:00' })
    await expectSaved(page)

    // Dua baris 'Pagi' dengan kolom Production Line yang BERBEDA.
    const rows = page.locator('.ks-shift-row', { hasText: 'Pagi' })
    await expect(rows).toHaveCount(2)
    await expect(page.locator('.ks-shift-row', { hasText: LINE_A })).toHaveCount(1)
    await expect(page.locator('.ks-shift-row', { hasText: LINE_B })).toHaveCount(1)

    expect((await listShifts(page, businessUnitId)).data.filter((row) => row.name === 'Pagi')).toHaveLength(2)

    expect(problems).toEqual([])
  })

  // ── Skenario 16 + 21 ──────────────────────────────────────────────────

  test('skenario 16 + 21 — jam berimpit ditolak menyebut durasi nol; 24 jam penuh terbaca tanpa celah', async ({ page }) => {
    const problems = watchProblems(page)
    const { businessUnitId } = await openScreenAsAdmin(page)
    const lineAId = await productionLineIdByName(page, businessUnitId, LINE_A)

    await selectMill(page, BUSINESS_UNIT)
    await selectLineFilter(page, LINE_A)

    // Skenario 16 — jam mulai dan selesai sama persis.
    await addShiftViaUi(page, { lineName: LINE_A, name: 'Nol', start: '07:00', end: '07:00' })
    await expectFormRejected(page)
    await expect(page.locator('[data-testid="error-end-time"]')).toBeVisible()
    await expect(page.locator('[data-testid="error-end-time"]')).toContainText('berdurasi nol')
    // Penolakannya menyebut DURASI NOL, bukan tumpang tindih.
    await expect(page.locator('[data-testid="error-end-time"]')).not.toContainText('bertindih')
    await expect(page.locator('[data-testid="form-error"]')).toHaveCount(0)

    await page.locator('.kcm-modal button:has-text("Batal")').click()
    await expect(page.locator('[data-testid="form-name-input"]')).toHaveCount(0)

    /*
     * Skenario 21 — MENUTUP SELURUH HARI PRODUKSI. Satu shift tidak dapat
     * melakukannya lewat peramban: satu-satunya rentang berdurasi 1440 menit
     * adalah jam yang berimpit, dan itu baru saja ditolak — resolusi yang
     * memang dikehendaki (entity constraint #2). Jadi hari produksinya
     * ditutup dengan rentang yang tidak berimpit, dan yang diasersi adalah
     * yang dijanjikan skenarionya: satu batang penuh tanpa celah, badge
     * berbunyi tercakup 24 jam.
     */
    await seedShift(page, { productionLineId: lineAId, name: 'Pagi', start: '07:00', end: '19:00' })
    await seedShift(page, { productionLineId: lineAId, name: 'Malam', start: '19:00', end: '07:00' })

    await page.goto(SHIFTS_PATH)
    await selectMill(page, BUSINESS_UNIT)
    await selectLineFilter(page, LINE_A)

    await expect(badge(page, lineAId)).toHaveText('tercakup 24 jam')
    expect(await orderedTestIds(page, 'timeline-gap-')).toEqual([])

    // Kedua blok MENUTUP seluruh track: tepi kiri yang pertama menempel ke
    // ujung kiri, tepi kanan yang terakhir ke ujung kanan, dan keduanya
    // bertemu di tengah — nol piksel yang tidak dimiliki shift mana pun.
    const blockIds = await orderedTestIds(page, 'timeline-block-')
    expect(blockIds).toHaveLength(2)

    const trackGeometry = await geometryOf(track(page, lineAId), 'track Line A')
    const first = await geometryOf(page.locator(`[data-testid="timeline-block-${blockIds[0]}"]`), 'blok pertama')
    const last = await geometryOf(page.locator(`[data-testid="timeline-block-${blockIds[1]}"]`), 'blok kedua')

    expect(Math.abs(first.left - trackGeometry.left)).toBeLessThanOrEqual(EDGE_TOLERANCE)
    expect(Math.abs(last.right - trackGeometry.right)).toBeLessThanOrEqual(EDGE_TOLERANCE)
    expect(Math.abs(first.right - last.left)).toBeLessThanOrEqual(EDGE_TOLERANCE)

    // Lebar SEBANDING durasi: 720 menit berbanding 720 menit.
    expect(Math.abs(first.width - last.width)).toBeLessThanOrEqual(EDGE_TOLERANCE)

    await expectNoHorizontalPageScroll(page)
    expect(problems).toEqual([])
  })

  // ── Skenario 17 ───────────────────────────────────────────────────────

  test('skenario 17 — ubah jam selesai sehingga menabrak: ditolak; ubah namanya saja tetap berhasil', async ({ page }) => {
    const problems = watchProblems(page)
    const { businessUnitId } = await openScreenAsAdmin(page)
    const lineAId = await productionLineIdByName(page, businessUnitId, LINE_A)

    const pagiId = await seedShift(page, { productionLineId: lineAId, name: 'Pagi', start: '07:00', end: '15:00' })
    await seedShift(page, { productionLineId: lineAId, name: 'Siang', start: '15:00', end: '23:00' })

    await page.goto(SHIFTS_PATH)
    await selectMill(page, BUSINESS_UNIT)
    await selectLineFilter(page, LINE_A)

    await page.locator(`[data-testid="edit-button-${pagiId}"]`).click()
    await expect(page.locator('[data-testid="form-end-time-input"]')).toHaveValue('15:00')

    await fillAndKeep(page, 'form-end-time-input', '16:00')
    await Promise.all([
      page.waitForResponse((response) => response.url().includes('/livewire/update')),
      page.locator('[data-testid="save-button"]').click(),
    ])

    const error = page.locator('[data-testid="form-error"]')
    await expect(error).toBeVisible()
    await expect(error).toContainText('Siang')
    await expect(error).toContainText('15:00')
    await expect(error).toContainText('16:00')

    // Mengubah NAMANYA saja tetap berhasil — shift yang sedang diubah
    // dikecualikan dari pembanding.
    await fillAndKeep(page, 'form-end-time-input', '15:00')
    await fillAndKeep(page, 'form-name-input', 'Pagi Awal')
    await Promise.all([
      page.waitForResponse((response) => response.url().includes('/livewire/update')),
      page.locator('[data-testid="save-button"]').click(),
    ])

    await expect(page.locator('[data-testid="success-message"]')).toBeVisible()
    await expect(page.locator(`[data-testid="shift-row-${pagiId}"]`)).toContainText('Pagi Awal')

    const body = await listShifts(page, businessUnitId, lineAId)
    expect(body.data.find((row) => row.id === pagiId)?.end_time).toBe('15:00')

    expect(problems).toEqual([])
  })

  // ── Skenario 18 ───────────────────────────────────────────────────────

  test('skenario 18 — memindahkan shift ke line yang jamnya terisi: ditolak menyebut shift di line tujuan', async ({ page }) => {
    const problems = watchProblems(page)
    const { businessUnitId } = await openScreenAsAdmin(page)
    const lineAId = await productionLineIdByName(page, businessUnitId, LINE_A)
    const lineBId = await productionLineIdByName(page, businessUnitId, LINE_B)

    const pagiId = await seedShift(page, { productionLineId: lineAId, name: 'Pagi', start: '07:00', end: '15:00' })
    await seedShift(page, { productionLineId: lineBId, name: 'Pagi Line B', start: '07:00', end: '15:00' })

    await page.goto(SHIFTS_PATH)
    await selectMill(page, BUSINESS_UNIT)

    await page.locator(`[data-testid="edit-button-${pagiId}"]`).click()
    await expect(page.locator('[data-testid="form-line-select"]')).toBeVisible()

    await page.locator('[data-testid="form-line-select"]').selectOption({ label: LINE_B })
    await Promise.all([
      page.waitForResponse((response) => response.url().includes('/livewire/update')),
      page.locator('[data-testid="save-button"]').click(),
    ])

    const error = page.locator('[data-testid="form-error"]')
    await expect(error).toBeVisible()
    // Pembandingnya shift di line TUJUAN.
    await expect(error).toContainText('Pagi Line B')

    const body = await listShifts(page, businessUnitId)
    expect(body.data.find((row) => row.id === pagiId)?.production_line_name).toBe(LINE_A)

    expect(problems).toEqual([])
  })

  // ── Skenario 19 ───────────────────────────────────────────────────────

  test('skenario 19 — hapus satu shift: badge menyebut jam celah dan ruang BERTEKSTUR muncul di posisinya', async ({ page }) => {
    const problems = watchProblems(page)
    const { businessUnitId } = await openScreenAsAdmin(page)
    const lineAId = await productionLineIdByName(page, businessUnitId, LINE_A)

    const pagiId = await seedShift(page, { productionLineId: lineAId, name: 'Pagi', start: '07:00', end: '15:00' })
    const siangId = await seedShift(page, { productionLineId: lineAId, name: 'Siang', start: '15:00', end: '23:00' })
    await seedShift(page, { productionLineId: lineAId, name: 'Malam', start: '23:00', end: '07:00' })

    await page.goto(SHIFTS_PATH)
    await selectMill(page, BUSINESS_UNIT)
    await selectLineFilter(page, LINE_A)

    await expect(badge(page, lineAId)).toHaveText('tercakup 24 jam')

    const siangGeometry = await geometryOf(page.locator(`[data-testid="timeline-block-${siangId}"]`), 'blok Siang')

    // Konfirmasi inline menyebut nama shift DAN line-nya.
    await page.locator(`[data-testid="delete-button-${siangId}"]`).click()
    await expect(page.locator('[data-testid="delete-confirm-label"]')).toContainText('Siang')
    await expect(page.locator('[data-testid="delete-confirm-label"]')).toContainText(LINE_A)

    await Promise.all([
      page.waitForResponse((response) => response.url().includes('/livewire/update')),
      page.locator('[data-testid="confirm-delete-button"]').click(),
    ])

    await expect(page.locator(`[data-testid="shift-row-${siangId}"]`)).toHaveCount(0)
    await expect(badge(page, lineAId)).toHaveText('ada celah 8 jam')

    // Blok celah muncul TEPAT di posisi yang ditinggalkan Siang (offset 480).
    const gapBlock = page.locator(`[data-testid="timeline-gap-${lineAId}-480"]`)
    await expect(gapBlock).toBeVisible()

    const gapGeometry = await geometryOf(gapBlock, 'blok celah')
    expect(Math.abs(gapGeometry.left - siangGeometry.left)).toBeLessThanOrEqual(EDGE_TOLERANCE)
    expect(Math.abs(gapGeometry.width - siangGeometry.width)).toBeLessThanOrEqual(EDGE_TOLERANCE)

    // BERTEKSTUR, bukan ruang putih — ruang putih terbaca sebagai latar
    // kartu dan bukan sebagai temuan. Dibaca dari CSS hasil compile.
    const gapBackground = await gapBlock.evaluate((element) => window.getComputedStyle(element).backgroundImage)
    expect(gapBackground, 'blok celah tidak berarsiran').toContain('gradient')

    // Shift yang masih ada tetap di tempatnya.
    await expect(page.locator(`[data-testid="timeline-block-${pagiId}"]`)).toBeVisible()

    expect(problems).toEqual([])
  })

  // ── Skenario 20 ───────────────────────────────────────────────────────

  test('skenario 20 — hapus shift terakhir: badge "belum diatur" dan bloknya polos tanpa arsiran celah', async ({ page }) => {
    const problems = watchProblems(page)
    const { businessUnitId } = await openScreenAsAdmin(page)
    const lineBId = await productionLineIdByName(page, businessUnitId, LINE_B)

    const onlyId = await seedShift(page, { productionLineId: lineBId, name: 'Pagi', start: '07:00', end: '15:00' })

    await page.goto(SHIFTS_PATH)
    await selectMill(page, BUSINESS_UNIT)
    await selectLineFilter(page, LINE_B)

    await expect(badge(page, lineBId)).toHaveText('ada celah 16 jam')

    await page.locator(`[data-testid="delete-button-${onlyId}"]`).click()
    await Promise.all([
      page.waitForResponse((response) => response.url().includes('/livewire/update')),
      page.locator('[data-testid="confirm-delete-button"]').click(),
    ])

    // BELUM DIATUR, bukan celah 24 jam.
    await expect(badge(page, lineBId)).toHaveText('belum diatur')
    await expect(badge(page, lineBId)).not.toHaveText('ada celah 24 jam')

    const emptyBlock = page.locator(`[data-testid="timeline-empty-${lineBId}"]`)
    await expect(emptyBlock).toBeVisible()

    const emptyBackground = await emptyBlock.evaluate((element) => window.getComputedStyle(element).backgroundImage)
    expect(emptyBackground, 'blok "belum diatur" tidak boleh berarsiran').toBe('none')
    expect(await orderedTestIds(page, 'timeline-gap-')).toEqual([])

    expect(problems).toEqual([])
  })

  // ── Skenario 22 ───────────────────────────────────────────────────────

  test('skenario 22 — ganti mill: filter Production Line kembali belum terpilih dan daftarnya milik mill baru', async ({ page }) => {
    const problems = watchProblems(page)
    const { businessUnitId } = await openScreenAsAdmin(page)
    const lineAId = await productionLineIdByName(page, businessUnitId, LINE_A)

    await seedShift(page, { productionLineId: lineAId, name: 'Pagi', start: '07:00', end: '15:00' })

    await page.goto(SHIFTS_PATH)
    await selectMill(page, BUSINESS_UNIT)
    await selectLineFilter(page, LINE_A)

    await expect(page.locator('[data-testid="line-filter"]')).toHaveValue(lineAId)
    await expect(page.locator(`[data-testid="timeline-track-${lineAId}"]`)).toBeVisible()

    // Mill lain — satu yang punya line, supaya perbedaan daftarnya terbaca.
    await selectMill(page, EMPTY_MILL)

    // Filter kembali ke keadaan belum terpilih, tanpa pesan galat tentang
    // line yang tidak cocok.
    await expect(page.locator('[data-testid="line-filter"]')).toHaveValue('')
    await expect(page.locator(`[data-testid="timeline-track-${lineAId}"]`)).toHaveCount(0)
    await expect(page.locator('[data-testid="form-error"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="delete-error"]')).toHaveCount(0)

    // Daftar line-nya milik mill baru — "Mill Kosong" tidak punya satu pun.
    const lineOptions = await page.locator('[data-testid="line-filter"] option').allTextContents()
    expect(lineOptions).toEqual(['Semua line'])

    await selectMill(page, BUSINESS_UNIT)
    const backOptions = await page.locator('[data-testid="line-filter"] option').allTextContents()
    expect(backOptions).toContain(LINE_A)
    await expect(page.locator('[data-testid="line-filter"]')).toHaveValue('')

    expect(problems).toEqual([])
  })

  // ── Skenario 23 ───────────────────────────────────────────────────────

  test('skenario 23 — nol field sinkronisasi di layar dan di form, dan keterangan tempatnya diatur terlihat', async ({ page }) => {
    const problems = watchProblems(page)
    const { businessUnitId } = await openScreenAsAdmin(page)
    const lineAId = await productionLineIdByName(page, businessUnitId, LINE_A)

    await seedShift(page, { productionLineId: lineAId, name: 'Pagi', start: '07:00', end: '15:00' })

    await page.goto(SHIFTS_PATH)
    await selectMill(page, BUSINESS_UNIT)
    await page.locator('[data-testid="add-shift-button"]').click()
    await expect(page.locator('[data-testid="form-name-input"]')).toBeVisible()

    // SELURUH field pada halaman DAN pada form ditelusuri.
    const suspicious = await page.evaluate(() => {
      const needles = ['sync', 'sinkron', 'jendela', 'window', 'interval']

      return Array.from(document.querySelectorAll('input, select, textarea'))
        .map((element) => {
          const el = element as HTMLInputElement
          return `${el.name} ${el.id} ${el.getAttribute('wire:model') ?? ''} ${el.getAttribute('data-testid') ?? ''}`.toLowerCase()
        })
        .filter((signature) => needles.some((needle) => signature.includes(needle)))
    })

    expect(suspicious, `field sinkronisasi ditemukan: ${suspicious.join(' | ')}`).toEqual([])

    // Dan keterangannya terlihat, menyebut DI MANA setelan itu diatur —
    // supaya Admin tidak menyimpulkan fiturnya belum ada.
    const note = page.locator('[data-testid="sync-window-note"]')
    await expect(note).toBeVisible()
    await expect(note).toContainText('Jarak waktu sinkronisasi tidak diatur di layar ini')
    await expect(note).toContainText('Mills Setting')

    expect(problems).toEqual([])
  })

  // ── Skenario 24 ───────────────────────────────────────────────────────

  test('skenario 24 — halaman kedua tabel: baris berganti sementara batang timeline tetap seluruh line', async ({ page }) => {
    const problems = watchProblems(page)
    const { businessUnitId } = await openScreenAsAdmin(page)

    const lineAId = await productionLineIdByName(page, businessUnitId, LINE_A)
    const lineBId = await productionLineIdByName(page, businessUnitId, LINE_B)
    const lineCId = await productionLineIdByName(page, businessUnitId, LINE_C)

    /*
     * DUA PULUH SATU SHIFT, DITANAM LEWAT API. Tabelnya dipaginasi 20 baris,
     * jadi halaman kedua hanya ada kalau ada baris ke-21 — dan mengisi form
     * modal dua puluh satu kali akan memakan menit tanpa membuktikan apa pun
     * yang belum dibuktikan skenario 1. Jamnya disusun agar tidak bertindih:
     * tujuh blok tiga jam per line, 07:00 → 04:00.
     */
    const lineIds = [lineAId, lineBId, lineCId]

    for (let index = 0; index < 21; index += 1) {
      const lineId = lineIds[index % 3]
      const slot = Math.floor(index / 3)
      const startHour = (7 + slot * 3) % 24
      const endHour = (7 + slot * 3 + 3) % 24
      const pad = (hour: number) => String(hour).padStart(2, '0')

      await seedShift(page, {
        productionLineId: lineId,
        name: `Blok ${slot + 1}`,
        start: `${pad(startHour)}:00`,
        end: `${pad(endHour)}:00`,
      })
    }

    await page.goto(SHIFTS_PATH)
    await selectMill(page, BUSINESS_UNIT)
    await selectLineFilter(page, LINE_A)

    // Satu line → tujuh baris, satu halaman. Dipakai sebagai kontrol bahwa
    // paginasinya memang soal jumlah baris.
    await expect(page.locator('.ks-shift-row')).toHaveCount(7)

    // Semua line → 21 baris → dua halaman.
    await selectLineFilter(page, 'Semua line')
    await expect(page.locator('.ks-shift-row')).toHaveCount(20)

    const page1Rows = await orderedTestIds(page, 'shift-row-')
    const page1Tracks = await orderedTestIds(page, 'timeline-track-')

    await expect(page.locator('[data-testid="pagination-summary"]')).toContainText('Halaman 1 dari 2')

    await Promise.all([
      page.waitForResponse((response) => response.url().includes('/livewire/update')),
      page.locator('[data-testid="next-page"]').click(),
    ])

    await expect(page.locator('[data-testid="pagination-summary"]')).toContainText('Halaman 2 dari 2')

    const page2Rows = await orderedTestIds(page, 'shift-row-')
    const page2Tracks = await orderedTestIds(page, 'timeline-track-')

    // Baris tabelnya BERGANTI…
    expect(page2Rows).toHaveLength(1)
    expect(page2Rows.filter((id) => page1Rows.includes(id))).toEqual([])

    // …sementara batang timeline-nya TIDAK: ia merangkum cakupan line, bukan
    // mendaftar baris.
    expect(page2Tracks).toEqual(page1Tracks)
    await expect(badge(page, lineAId)).toBeVisible()
    await expect(badge(page, lineBId)).toBeVisible()
    await expect(badge(page, lineCId)).toBeVisible()

    await expectNoHorizontalPageScroll(page)
    expect(problems).toEqual([])
  })

  // ── Skenario 25 ───────────────────────────────────────────────────────

  test('skenario 25 — urutan blok timeline sama dengan urutan baris tabel, dan lebar sebanding durasi', async ({ page }) => {
    const problems = watchProblems(page)
    const { businessUnitId } = await openScreenAsAdmin(page)
    const lineAId = await productionLineIdByName(page, businessUnitId, LINE_A)

    // Ditanam dengan urutan ACAK, dan abjad namanya pun tidak selaras dengan
    // urutan jamnya: Malam dulu, lalu Pagi, lalu Siang.
    const malamId = await seedShift(page, { productionLineId: lineAId, name: 'Malam', start: '23:00', end: '07:00' })
    const pagiId = await seedShift(page, { productionLineId: lineAId, name: 'Pagi', start: '07:00', end: '15:00' })
    const siangId = await seedShift(page, { productionLineId: lineAId, name: 'Siang', start: '15:00', end: '19:00' })

    await page.goto(SHIFTS_PATH)
    await selectMill(page, BUSINESS_UNIT)
    await selectLineFilter(page, LINE_A)

    const expected = [pagiId, siangId, malamId]

    // KEDUANYA DIBACA DARI DOM, lalu dibandingkan.
    expect(await orderedTestIds(page, 'timeline-block-')).toEqual(expected)
    expect(await orderedTestIds(page, 'shift-row-')).toEqual(expected)

    // Bukan urutan penambahan, dan bukan abjad nama.
    expect(await orderedTestIds(page, 'shift-row-')).not.toEqual([malamId, pagiId, siangId])

    // LEBAR SEBANDING DURASI, diuji sebagai RASIO: Pagi 480 menit, Siang 240
    // menit, Malam 480 menit.
    const pagi = await geometryOf(page.locator(`[data-testid="timeline-block-${pagiId}"]`), 'blok Pagi')
    const siang = await geometryOf(page.locator(`[data-testid="timeline-block-${siangId}"]`), 'blok Siang')
    const malam = await geometryOf(page.locator(`[data-testid="timeline-block-${malamId}"]`), 'blok Malam')

    expect(pagi.width / siang.width).toBeGreaterThan(1.9)
    expect(pagi.width / siang.width).toBeLessThan(2.1)
    expect(Math.abs(pagi.width - malam.width)).toBeLessThanOrEqual(EDGE_TOLERANCE)

    // Dan posisinya naik searah dengan urutannya.
    expect(pagi.left).toBeLessThan(siang.left)
    expect(siang.left).toBeLessThan(malam.left)

    expect(problems).toEqual([])
  })

  // ── Skenario 26 ───────────────────────────────────────────────────────

  test('skenario 26 — ketiga status terbaca dari katanya saja, dan jumlah jam celah disebut angkanya', async ({ page }) => {
    const problems = watchProblems(page)
    const { businessUnitId } = await openScreenAsAdmin(page)

    const lineAId = await productionLineIdByName(page, businessUnitId, LINE_A)
    const lineBId = await productionLineIdByName(page, businessUnitId, LINE_B)
    const lineCId = await productionLineIdByName(page, businessUnitId, LINE_C)

    // Line A tercakup penuh.
    await seedShift(page, { productionLineId: lineAId, name: 'Pagi', start: '07:00', end: '15:00' })
    await seedShift(page, { productionLineId: lineAId, name: 'Siang', start: '15:00', end: '23:00' })
    await seedShift(page, { productionLineId: lineAId, name: 'Malam', start: '23:00', end: '07:00' })

    // Line B bercelah 4 jam (15:00–19:00).
    await seedShift(page, { productionLineId: lineBId, name: 'Pagi', start: '07:00', end: '15:00' })
    await seedShift(page, { productionLineId: lineBId, name: 'Malam', start: '19:00', end: '07:00' })

    // Line C belum diatur — sengaja tidak diberi shift.

    await page.goto(SHIFTS_PATH)
    await selectMill(page, BUSINESS_UNIT)

    // KETIGANYA DARI BADGE-NYA SENDIRI.
    await expect(badge(page, lineAId)).toHaveText('tercakup 24 jam')
    await expect(badge(page, lineBId)).toHaveText('ada celah 4 jam')
    await expect(badge(page, lineCId)).toHaveText('belum diatur')

    // 'belum diatur' MEMANG muncul lebih dari sekali di halaman — itulah
    // sebabnya asersi di atas menyasar elemennya, bukan kontainernya.
    const occurrences = await page.evaluate(
      () => (document.querySelector('.ks-page')?.textContent ?? '').split('belum diatur').length - 1,
    )
    expect(occurrences).toBeGreaterThan(1)

    // Legenda menamai ketiga keadaan, jadi batangnya terbaca tanpa warna.
    await expect(page.locator('.ks-legend')).toContainText('Shift')
    await expect(page.locator('.ks-legend')).toContainText('Celah')
    await expect(page.locator('.ks-legend')).toContainText('Belum diatur')

    expect(problems).toEqual([])
  })

  // ── Skenario 27 ───────────────────────────────────────────────────────

  test('skenario 27 — beberapa lebar: nol teks di bawah 14px, label jam dijarangkan, halaman tidak menggulir mendatar', async ({ page }) => {
    const problems = watchProblems(page)
    const { businessUnitId } = await openScreenAsAdmin(page)
    const lineAId = await productionLineIdByName(page, businessUnitId, LINE_A)

    await seedShift(page, { productionLineId: lineAId, name: 'Pagi', start: '07:00', end: '15:00' })
    await seedShift(page, { productionLineId: lineAId, name: 'Malam', start: '23:00', end: '07:00' })

    await page.goto(SHIFTS_PATH)
    await selectMill(page, BUSINESS_UNIT)
    await selectLineFilter(page, LINE_A)

    for (const width of [1440, 1280, 1024, 820, 768]) {
      await page.setViewportSize({ width, height: 900 })
      await expect(track(page, lineAId)).toBeVisible()

      // NOL teks di bawah 14px pada lebar mana pun — termasuk label jam.
      const offenders = await textsUnder14px(page)
      expect(offenders, `lebar ${width}px: teks di bawah 14px → ${offenders.join(' ; ')}`).toEqual([])

      // Halaman TIDAK PERNAH menggulir mendatar.
      await expectNoHorizontalPageScroll(page)

      // Label jam utama tetap terlihat pada setiap lebar.
      await expect(page.locator('.ks-axis-tick--start').first()).toBeVisible()
      await expect(page.locator('.ks-axis-tick--end').first()).toBeVisible()
    }

    // DIJARANGKAN, BUKAN DIPERKECIL: pada lebar sempit label sekunder
    // disembunyikan sementara ukuran label yang tersisa tidak berubah.
    await page.setViewportSize({ width: 1440, height: 900 })
    const wideTickSize = await page.locator('.ks-axis-tick--secondary').first()
      .evaluate((element) => window.getComputedStyle(element).fontSize)
    await expect(page.locator('.ks-axis-tick--secondary').first()).toBeVisible()

    await page.setViewportSize({ width: 700, height: 900 })
    await expect(page.locator('.ks-axis-tick--secondary').first()).toBeHidden()

    const narrowTickSize = await page.locator('.ks-axis-tick').first()
      .evaluate((element) => window.getComputedStyle(element).fontSize)
    expect(Number.parseFloat(narrowTickSize)).toBeGreaterThanOrEqual(14)
    expect(narrowTickSize).toBe(wideTickSize)

    // Dan ketika batangnya memang perlu menggulir, ia menggulir DI DALAM
    // kartunya sendiri — halamannya tetap tidak.
    const scrollMetrics = await page.locator('.ks-scroll').first().evaluate((element) => ({
      scrollWidth: element.scrollWidth,
      clientWidth: element.clientWidth,
      overflowX: window.getComputedStyle(element).overflowX,
    }))

    expect(scrollMetrics.overflowX).toBe('auto')
    expect(
      scrollMetrics.scrollWidth,
      'batang tidak menggulir di dalam kartunya pada lebar sempit',
    ).toBeGreaterThan(scrollMetrics.clientWidth)

    await expectNoHorizontalPageScroll(page)
    expect(problems).toEqual([])
  })

  // ── Skenario 28 ───────────────────────────────────────────────────────

  for (const [label, username] of [
    ['Supervisor', SUPERVISOR],
    ['Mill Management', MILL_MANAGEMENT],
    ['Operator', OPERATOR],
  ] as const) {
    test(`skenario 28 — ${label} membuka rutenya langsung: ditolak dan nol data shift terlihat`, async ({ page }) => {
      // Halaman 403 memang dijawab 403; itu keadaan yang diharapkan.
      const problems = watchProblems(page, { allowStatus: [403] })

      // Prasyarat ditanam sebagai Admin lebih dulu, di konteks tersendiri,
      // supaya ada data yang BISA terlihat kalau penjagaannya bocor.
      const adminPage = await page.context().browser()!.newPage()

      try {
        await login(adminPage, ADMIN, PASSWORD)
        const businessUnitId = await businessUnitIdByName(adminPage, BUSINESS_UNIT)
        await clearShifts(adminPage, businessUnitId, LINES)
        const lineAId = await productionLineIdByName(adminPage, businessUnitId, LINE_A)
        await seedShift(adminPage, {
          productionLineId: lineAId,
          name: 'Shift Rahasia',
          start: '07:00',
          end: '15:00',
        })
      } finally {
        await adminPage.close()
      }

      await login(page, username, PASSWORD)
      await page.goto(SHIFTS_PATH)

      // Ditolak middleware role — tidak mendarat di layarnya.
      await expect(page.locator('.ks-page')).toHaveCount(0)
      await expect(page.locator('[data-testid="mill-select"]')).toHaveCount(0)
      await expect(page.locator('body')).toContainText('Akses Ditolak')

      // NOL data shift terlihat.
      await expect(page.locator('body')).not.toContainText('Shift Rahasia')

      expect(problems).toEqual([])
    })
  }
})
