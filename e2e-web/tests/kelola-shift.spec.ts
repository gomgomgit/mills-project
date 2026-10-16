/**
 * Kelola Shift per Production Line (Browser/Playwright) —
 * screen-170--kelola-shift / usecase-176--kelola-shift.
 *
 * Satu test per entri `test_scenarios` yang punya `browser_test` (ke-33-nya
 * punya), dikelompokkan di mana satu keadaan halaman membuktikan beberapa di
 * antaranya. Nomor skenario disebut di komentar tiap test: 16 + 21, 19 + 33,
 * 20 + 30, 29 + 31, dan 14 + 15 masing-masing satu test.
 *
 * ── PRASYARAT YANG WAJIB DIPENUHI SEBELUM SPEC INI DAPAT JALAN ──────────
 *
 * Tabel `shifts` (migrasi 2026_10_15_000001_create_shifts_table) DAN migrasi
 * soft delete-nya (2026_10_16_000001_add_soft_deletes_to_shifts_table —
 * `deleted_at` plus PARTIAL unique index `WHERE deleted_at IS NULL`) wajib
 * sudah jalan di database e2e. Tanpa yang kedua, setiap test di bawah yang
 * menghapus lalu memakai ulang nama akan ditolak oleh unique index yang masih
 * PENUH, dan gejalanya terbaca seperti bug di lapisan aplikasi. Jalankan
 * salah satu:
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
 * ── REVISI SPEC v2 (2026-10-16) — DUA ATURAN YANG BERUBAH ───────────────
 *
 * 1. JAM BERIMPIT BERARTI SHIFT 24 JAM. Ia DITERIMA pada line yang belum
 *    punya shift HIDUP lain, dan ditolak sebagai durasi nol hanya pada line
 *    yang sudah terisi. Versi sebelumnya spec ini menyatakan skenario 21
 *    "tidak dapat dibuat lewat peramban" dan menutup hari produksinya dengan
 *    DUA shift; kini satu shift 07:00–07:00 disimpan lewat form apa adanya,
 *    dan satu blok penuh selebar track-nya yang diukur. Catatan lama itu
 *    dihapus alih-alih dibiarkan: sebuah keterangan yang menyatakan sesuatu
 *    tidak mungkin diuji adalah cara tercepat membuat orang berikutnya
 *    berhenti mencoba.
 *
 * ── REVISI SPEC v3 (2026-10-16) — SUMBU 00:00 DAN BLOK BERSEGMEN ───────
 *
 * Pemilik produk memakai layar ini dan melaporkan pratinjau yang berantakan
 * serta tampilan yang tidak sesuai begitu jam melewati 07:00. Akarnya BUKAN
 * titik awal sumbu: blok digambar sebagai SATU rentang utuh, sehingga shift
 * yang melintasi titik awal digambar `left+width` melewati tepi kanan track
 * ber-`overflow: hidden` lalu DIPOTONG DIAM-DIAM, sementara sisanya di tepi
 * kiri tidak pernah tergambar. Tiga hal berubah, dan hanya yang pertama
 * adalah perbaikan cacat:
 *
 * 1. BLOK DIGAMBAR SEBAGAI SEGMEN. Shift yang melintasi titik awal sumbu
 *    menjadi DUA elemen ber-`data-testid` sama dan `data-segment-index`
 *    berbeda. Asersi v2 "satu elemen yang menempel ujung kanan" justru
 *    MELOLOSKAN blok terpotong — elemen terpotong memang satu dan memang
 *    menempel ujung kanan. Penggantinya mengukur bahwa tak ada blok yang
 *    tepi kanannya melewati tepi kanan track.
 * 2. SUMBU DIMULAI 00:00, bukan 07:00 — murni pilihan menggambar.
 * 3. PERINGATAN "tidak dimulai 07:00" DICABUT seluruhnya.
 *
 * 2. PENGHAPUSAN ADALAH SOFT DELETE. Barisnya ditutup, bukan dibuang, jadi
 *    ketidakhadirannya di tabel dan sebagai blok timeline harus diasersi dan
 *    bukan disimpulkan (skenario 19 + 33), dan nama serta jam yang
 *    dikosongkan wajib dapat dipakai ulang oleh penggantinya (skenario
 *    29 + 31) — justru alasan soft delete dipilih.
 *
 *    Konsekuensi untuk spec ini: clearShifts() di ./support/shifts.ts kini
 *    MENUTUP baris alih-alih membuangnya, dan residu dari run sebelumnya
 *    tetap tidak menghalangi karena partial unique index hanya berlaku bagi
 *    baris hidup.
 *
 * PENGGANTIAN-SAAT-UBAH (business_logic 29–30) BELUM DIBANGUN dan sengaja
 * TIDAK diuji di sini. Pemicunya "ada record yang merujuk shift ini", dan
 * tidak ada satu kolom pun di sistem yang merujuk shift hari ini. Skenario 32
 * karena itu mengasersi perilaku yang benar-benar ada: mengubah jam memutasi
 * baris yang sama, dan tidak ada baris kedua yang muncul.
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
  top: number
  bottom: number
  height: number
}

async function geometryOf(locator: Locator, label: string): Promise<Geometry> {
  const box = await locator.boundingBox()

  expect(box, `${label}: elemen tidak punya kotak (tidak terlihat?)`).not.toBeNull()

  return {
    left: box!.x,
    right: box!.x + box!.width,
    width: box!.width,
    top: box!.y,
    bottom: box!.y + box!.height,
    height: box!.height,
  }
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
 * SATU potongan blok sebuah shift. Shift yang melewati tengah malam punya DUA
 * elemen ber-`data-testid` yang sama — sengaja sama, supaya potongan sebuah
 * shift dapat DIHITUNG — jadi locator yang hanya memakai data-testid akan
 * melanggar mode strict Playwright. `data-segment-index` 0 selalu potongan
 * tempat shift itu DIMULAI.
 */
function blockSegment(page: Page, shiftId: string, segmentIndex = 0): Locator {
  return page.locator(
    `[data-testid="timeline-block-${shiftId}"][data-segment-index="${segmentIndex}"]`,
  )
}

/**
 * Id shift dari potongan tempat tiap shift DIMULAI, IN DOCUMENT ORDER.
 *
 * Inilah urutan yang dibandingkan dengan urutan baris tabel. Urutan SELURUH
 * elemen blok tidak lagi sama dengannya sejak sumbu dimulai 00:00: shift yang
 * melewati tengah malam menyumbang potongan kedua di tepi KIRI batang. Itu
 * tidak dapat dihindari oleh sumbu linier mana pun — yang dapat dipilih
 * hanyalah shift mana yang terpecah.
 */
async function orderedStartBlockIds(page: Page): Promise<string[]> {
  return page.evaluate(() => {
    return Array.from(
      document.querySelectorAll('[data-testid^="timeline-block-"][data-segment-index="0"]'),
    ).map((element) => (element.getAttribute('data-testid') ?? '').slice('timeline-block-'.length))
  })
}

/**
 * NOL blok yang tepi kanannya melewati tepi kanan track — diukur dari DOM,
 * untuk SETIAP blok pada SETIAP batang.
 *
 * Asersi inilah yang memerah terhadap implementasi sebelum v3. `overflow:
 * hidden` membuat blok yang melewati tepi tetap TERLIHAT dan tetap menempel
 * ujung kanan, jadi asersi "ada, terlihat, menempel ujung kanan" menghijau
 * justru pada halaman yang cacat; yang membedakan keduanya hanya geometri
 * yang diukur.
 */
async function expectNoBlockPastTrackEdge(page: Page): Promise<void> {
  const offenders = await page.evaluate(() => {
    const found: string[] = []

    document.querySelectorAll('[data-testid^="timeline-track-"]').forEach((trackElement) => {
      const trackBox = trackElement.getBoundingClientRect()

      trackElement.querySelectorAll('.ks-block').forEach((block) => {
        const box = block.getBoundingClientRect()

        if (box.right > trackBox.right + 1 || box.left < trackBox.left - 1) {
          found.push(
            `${block.getAttribute('data-testid') ?? block.className}: ` +
              `[${Math.round(box.left)}, ${Math.round(box.right)}] vs track ` +
              `[${Math.round(trackBox.left)}, ${Math.round(trackBox.right)}]`,
          )
        }
      })
    })

    return found
  })

  expect(offenders, 'ada blok yang digambar di luar track dan dipotong overflow hidden').toEqual([])
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

  test('skenario 1 — menambah Pagi 07:00–15:00: blok jatuh di 07:00 pada sumbu 00:00, badge menyebut jam celah', async ({ page }) => {
    const problems = watchProblems(page)
    const { businessUnitId } = await openScreenAsAdmin(page)
    const lineAId = await productionLineIdByName(page, businessUnitId, LINE_A)

    await selectMill(page, BUSINESS_UNIT)
    await selectLineFilter(page, LINE_A)

    await addShiftViaUi(page, { lineName: LINE_A, name: 'Pagi', start: '07:00', end: '15:00' })
    await expectSaved(page)

    const [shiftId] = await orderedStartBlockIds(page)

    expect(shiftId, 'blok Pagi tidak dirender').toBeTruthy()

    // SATU potongan: 07:00–15:00 tidak melintasi titik awal sumbu.
    await expect(page.locator(`[data-testid="timeline-block-${shiftId}"]`)).toHaveCount(1)

    const blockGeometry = await geometryOf(blockSegment(page, shiftId), 'blok Pagi')
    const trackGeometry = await geometryOf(track(page, lineAId), 'track Line A')

    // JATUH DI 07:00 PADA SUMBU 00:00 — yaitu 420/1440 = 29,17% dari tepi
    // kiri, BUKAN menempel ke tepi itu. Diukur sebagai rasio, bukan
    // disimpulkan dari atribut style dan tanpa satu pun lebar piksel.
    const startRatio = (blockGeometry.left - trackGeometry.left) / trackGeometry.width

    expect(startRatio, `blok Pagi tidak jatuh di 07:00 (rasio ${startRatio})`).toBeGreaterThan(0.27)
    expect(startRatio).toBeLessThan(0.31)

    // 480 dari 1440 menit — sepertiga batang.
    expect(blockGeometry.width / trackGeometry.width).toBeGreaterThan(0.30)
    expect(blockGeometry.width / trackGeometry.width).toBeLessThan(0.36)

    // Sumbunya memang dimulai 00:00 dan berakhir 24:00 — disasar pada ELEMEN
    // labelnya, karena '00:00' dapat muncul pula di kolom jam pada tabel.
    await expect(page.locator('[data-testid="axis-tick-start"]').first()).toHaveText('00:00')
    await expect(page.locator('[data-testid="axis-tick-end"]').first()).toHaveText('24:00')

    // Badge-nya ELEMEN sendiri, dan angka jam celahnya DISEBUT.
    await expect(badge(page, lineAId)).toHaveText('ada celah 16 jam')

    // Baris tabelnya muncul, dan urutannya sama dengan urutan blok.
    expect(await orderedTestIds(page, 'shift-row-')).toEqual([shiftId])

    await expectNoBlockPastTrackEdge(page)

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

  test('skenario 6 — cakupan 24 jam yang dimulai 06:00: badge tercakup penuh dan NOL peringatan ketidakselarasan', async ({ page }) => {
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

    // PERINGATANNYA DICABUT (v3, "dibuang saja"). Diasersi atas TESTID blok
    // peringatannya — bukan atas prosanya, karena '07:00' masih muncul di
    // keterangan field Jam Selesai dan sebuah pemindaian teks akan mencocoki
    // tempat yang salah.
    await expect(page.locator(`[data-testid="misaligned-note-${lineAId}"]`)).toHaveCount(0)
    await expect(page.locator('[data-testid^="misaligned-note-"]')).toHaveCount(0)

    // Nol blok celah: union-nya memang menutup 24 jam.
    expect(await orderedTestIds(page, 'timeline-gap-')).toEqual([])

    // TIGA shift, dan Regu C (22:00–06:00) melewati tengah malam sehingga ia
    // menyumbang DUA potongan: empat elemen, tiga jam-mulai.
    expect(await orderedTestIds(page, 'timeline-block-')).toHaveLength(4)
    expect(await orderedStartBlockIds(page)).toHaveLength(3)

    await expectNoBlockPastTrackEdge(page)

    await expectNoHorizontalPageScroll(page)
    expect(problems).toEqual([])
  })

  // ── Skenario 7 ────────────────────────────────────────────────────────

  test('skenario 7 — Malam 23:00–07:00: DUA potongan bertaut di kedua tepi track, nol yang terpotong', async ({ page }) => {
    const problems = watchProblems(page)
    const { businessUnitId } = await openScreenAsAdmin(page)
    const lineAId = await productionLineIdByName(page, businessUnitId, LINE_A)

    await selectMill(page, BUSINESS_UNIT)
    await selectLineFilter(page, LINE_A)

    await addShiftViaUi(page, { lineName: LINE_A, name: 'Malam', start: '23:00', end: '07:00' })
    await expectSaved(page)

    const [shiftId] = await orderedStartBlockIds(page)

    expect(shiftId, 'blok Malam tidak dirender').toBeTruthy()

    // DUA elemen ber-data-testid sama: potongan 23:00–24:00 (segment 0, tempat
    // shift ini dimulai) dan potongan 00:00–07:00 (segment 1).
    await expect(page.locator(`[data-testid="timeline-block-${shiftId}"]`)).toHaveCount(2)

    const startPiece = await geometryOf(blockSegment(page, shiftId, 0), 'potongan 23:00–24:00')
    const wrapPiece = await geometryOf(blockSegment(page, shiftId, 1), 'potongan 00:00–07:00')
    const trackGeometry = await geometryOf(track(page, lineAId), 'track Line A')

    // Potongan pertama menempel tepi KANAN, yang kedua menempel tepi KIRI.
    expect(
      Math.abs(startPiece.right - trackGeometry.right),
      `potongan 23:00–24:00 tidak menempel tepi kanan (${startPiece.right} vs ${trackGeometry.right})`,
    ).toBeLessThanOrEqual(EDGE_TOLERANCE)
    expect(
      Math.abs(wrapPiece.left - trackGeometry.left),
      `potongan 00:00–07:00 tidak menempel tepi kiri (${wrapPiece.left} vs ${trackGeometry.left})`,
    ).toBeLessThanOrEqual(EDGE_TOLERANCE)

    // JUMLAH LEBAR KEDUANYA sebanding 480 menit dari 1440 — sepertiga batang.
    // Inilah yang membuktikan tidak ada menit yang hilang: sebelum v3, 420
    // dari 480 menit dipotong diam-diam oleh overflow hidden.
    const combinedRatio = (startPiece.width + wrapPiece.width) / trackGeometry.width

    expect(combinedRatio, `jumlah lebar kedua potongan tidak sebanding durasi (${combinedRatio})`).toBeGreaterThan(0.30)
    expect(combinedRatio).toBeLessThan(0.36)

    // Dan potongan 00:00–07:00 memang yang lebih lebar (420 menit vs 60).
    expect(wrapPiece.width).toBeGreaterThan(startPiece.width)

    // ASERSI YANG MEMERAH TERHADAP IMPLEMENTASI LAMA: nol blok di luar track.
    await expectNoBlockPastTrackEdge(page)

    // Label '+1' supaya jam selesai yang lebih kecil tidak terbaca sebagai
    // salah input — pada KEDUA potongan dan pada barisnya, dan label yang
    // sama pada keduanya itulah yang memberi tahu pembaca mereka satu shift.
    await expect(blockSegment(page, shiftId, 0)).toContainText('23:00 — 07:00 +1')
    await expect(blockSegment(page, shiftId, 1)).toContainText('23:00 — 07:00 +1')
    await expect(blockSegment(page, shiftId, 1)).toContainText('Malam')
    await expect(page.locator(`[data-testid="shift-row-${shiftId}"]`)).toContainText('07:00 +1')

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

    // DUA JALUR, bukan satu (v3). Shift yang sudah ada di jalur atas, kandidat
    // di jalur bawah, dan keduanya TIDAK berpotongan secara vertikal — kalau
    // berpotongan, nama dan jam kandidat tertumpuk persis di atas nama dan jam
    // shift yang ditimpanya, dan dua teks pada satu titik itulah yang
    // dilaporkan sebagai berantakan. Diukur dari DOM.
    const previewExisting = await geometryOf(
      page.locator('[data-testid="preview-track"] .ks-block-shift').first(),
      'blok shift yang sudah ada di pratinjau',
    )
    const previewCandidate = await geometryOf(
      page.locator('[data-testid="preview-candidate"]').first(),
      'blok kandidat',
    )

    expect(
      previewCandidate.top,
      `jalur kandidat menimpa jalur shift yang sudah ada (${previewCandidate.top} < ${previewExisting.bottom})`,
    ).toBeGreaterThanOrEqual(previewExisting.bottom - 1)

    // Dan keduanya memang menempati rentang MENDATAR yang beririsan — kalau
    // tidak, asersi di atas lolos karena mereka berjauhan, bukan karena
    // jalurnya terpisah.
    expect(previewCandidate.left).toBeLessThan(previewExisting.right)
    expect(previewCandidate.right).toBeGreaterThan(previewExisting.left)
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

    const blockIds = await orderedStartBlockIds(page)
    expect(blockIds).toHaveLength(2)
    expect(blockIds[0]).toBe(pagiId)

    const first = await geometryOf(blockSegment(page, blockIds[0]), 'blok Pagi')
    const second = await geometryOf(blockSegment(page, blockIds[1]), 'blok Siang')

    // BERSEBELAHAN: tepi kanan yang pertama bertemu tepi kiri yang kedua.
    expect(
      Math.abs(first.right - second.left),
      `kedua blok tidak bersebelahan (${first.right} vs ${second.left})`,
    ).toBeLessThanOrEqual(EDGE_TOLERANCE)

    // Durasinya sama (480 menit), jadi lebarnya pun sama — lebar sebanding
    // durasi, diuji sebagai RASIO dan bukan sebagai piksel.
    expect(Math.abs(first.width - second.width)).toBeLessThanOrEqual(EDGE_TOLERANCE)

    // Nol celah DI ANTARA keduanya. Satu-satunya celah ada di belakang
    // keduanya (23:00 → 07:00), dan pada sumbu 00:00 celah itu melintasi
    // titik awal sehingga ia pun tergambar sebagai DUA potongan: 00:00–07:00
    // (offset 0) dan 23:00–24:00 (offset 1380). Keduanya 480 menit bersama.
    expect(await orderedTestIds(page, 'timeline-gap-')).toEqual([
      `${lineAId}-0`,
      `${lineAId}-1380`,
    ])
    await expect(badge(page, lineAId)).toHaveText('ada celah 8 jam')

    await expectNoBlockPastTrackEdge(page)

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

  /*
   * DUA CABANG DARI SATU ATURAN (revisi spec v2 2026-10-16, business_logic
   * 17). Jam berimpit BERARTI shift 24 jam: ia diterima pada line yang belum
   * punya shift HIDUP lain, dan ditolak sebagai durasi nol pada line yang
   * sudah terisi — di sana ia hanya bisa berarti salah ketik, karena shift 24
   * jam menurut definisinya tidak dapat berdampingan dengan shift mana pun.
   *
   * VERSI SEBELUMNYA SPEC INI MENYIAPKAN DIRINYA DI LINE YANG KOSONG lalu
   * mengharapkan penolakan, dan menutup hari produksi dengan DUA shift karena
   * satu shift 24 jam dianggap tidak mungkin dibuat lewat peramban. Keduanya
   * kini terbalik: prasyaratnya yang berubah, dan skenario 21 akhirnya dapat
   * dijalankan apa adanya — SATU shift, satu blok penuh.
   */
  test('skenario 16 + 21 — jam berimpit: ditolak di line terisi, diterima sebagai 24 jam di line kosong', async ({ page }) => {
    const problems = watchProblems(page)
    const { businessUnitId } = await openScreenAsAdmin(page)
    const lineAId = await productionLineIdByName(page, businessUnitId, LINE_A)
    const lineBId = await productionLineIdByName(page, businessUnitId, LINE_B)

    // PRASYARAT cabang penolakan: Line A sudah punya satu shift hidup.
    await seedShift(page, { productionLineId: lineAId, name: 'Pagi', start: '07:00', end: '15:00' })

    await page.goto(SHIFTS_PATH)
    await selectMill(page, BUSINESS_UNIT)
    await selectLineFilter(page, LINE_A)

    // ── Skenario 16 — DITOLAK pada line yang sudah terisi ──────────────
    await addShiftViaUi(page, { lineName: LINE_A, name: 'Nol', start: '07:00', end: '07:00' })
    await expectFormRejected(page)

    const endTimeError = page.locator('[data-testid="error-end-time"]')
    await expect(endTimeError).toBeVisible()
    await expect(endTimeError).toContainText('berdurasi nol')
    // Penolakannya menyebut DURASI NOL, bukan tumpang tindih — dan pesan
    // per-form (tempat tumpang tindih mendarat) tidak muncul sama sekali.
    await expect(endTimeError).not.toContainText('bertindih')
    await expect(page.locator('[data-testid="form-error"]')).toHaveCount(0)

    await page.locator('.kcm-modal button:has-text("Batal")').click()
    await expect(page.locator('[data-testid="form-name-input"]')).toHaveCount(0)

    // ── Skenario 21 — DITERIMA pada line yang belum punya shift hidup ───
    //
    // Line B masih kosong (openScreenAsAdmin menyapu bersih ketiga line), jadi
    // 07:00–07:00 di sana adalah satu-satunya cara mengungkapkan 1440 menit —
    // dan ia tersimpan lewat FORM, jalur yang diuji.
    await addShiftViaUi(page, { lineName: LINE_B, name: 'Penuh', start: '07:00', end: '07:00' })
    await expectSaved(page)

    await selectLineFilter(page, LINE_B)

    await expect(badge(page, lineBId)).toHaveText('tercakup 24 jam')
    expect(await orderedTestIds(page, 'timeline-gap-')).toEqual([])

    // SATU SHIFT yang menutup SELURUH track. Pada sumbu 00:00 ia melintasi
    // titik awal (07:00 → 07:00), jadi ia tergambar sebagai DUA potongan
    // bertaut — dan keduanya BERSAMA-SAMA menutup batang penuh tanpa celah
    // dan tanpa melewati tepinya. Diukur dari DOM, tanpa satu pun lebar
    // piksel yang ditulis di spec ini.
    const [fullShiftId] = await orderedStartBlockIds(page)
    expect(fullShiftId).toBeTruthy()
    await expect(page.locator(`[data-testid="timeline-block-${fullShiftId}"]`)).toHaveCount(2)

    const trackGeometry = await geometryOf(track(page, lineBId), 'track Line B')
    const pieceA = await geometryOf(blockSegment(page, fullShiftId, 0), 'potongan 07:00–24:00')
    const pieceB = await geometryOf(blockSegment(page, fullShiftId, 1), 'potongan 00:00–07:00')

    // Tepi terluar keduanya menempel ke kedua ujung track…
    expect(Math.abs(pieceB.left - trackGeometry.left)).toBeLessThanOrEqual(EDGE_TOLERANCE)
    expect(Math.abs(pieceA.right - trackGeometry.right)).toBeLessThanOrEqual(EDGE_TOLERANCE)
    // …mereka BERTAUT di tengah (tepi kanan yang satu = tepi kiri yang lain)…
    expect(Math.abs(pieceB.right - pieceA.left)).toBeLessThanOrEqual(EDGE_TOLERANCE)
    // …dan jumlah lebarnya = lebar track.
    expect(Math.abs(pieceA.width + pieceB.width - trackGeometry.width)).toBeLessThanOrEqual(EDGE_TOLERANCE)

    await expectNoBlockPastTrackEdge(page)

    // Dan payload-nya memang SATU shift berdurasi 1440 menit, bukan dua
    // shift — yang dua adalah potongan gambarnya, bukan barisnya.
    const body = await listShifts(page, businessUnitId, lineBId)
    expect(body.data).toHaveLength(1)
    expect(body.data[0].duration_minutes).toBe(1440)

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

  // ── Skenario 19 + 33 ──────────────────────────────────────────────────

  /*
   * SKENARIO 33 ADALAH KLAIM PERAMBAN YANG SAMA, dibaca dari sisi yang lain:
   * "shift yang ditutup tidak muncul di mana pun yang hidup". Sejak
   * penghapusan menjadi SOFT DELETE (v2 2026-10-16) barisnya tetap ada di
   * basis data, jadi ketidakhadirannya di tabel DAN sebagai blok timeline
   * justru yang harus diasersi — bukan disimpulkan dari badge-nya.
   */
  test('skenario 19 + 33 — hapus satu shift: barisnya dan bloknya hilang, ruang BERTEKSTUR muncul di posisinya', async ({ page }) => {
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

    const siangGeometry = await geometryOf(blockSegment(page, siangId), 'blok Siang')

    // Konfirmasi inline menyebut nama shift DAN line-nya.
    await page.locator(`[data-testid="delete-button-${siangId}"]`).click()
    await expect(page.locator('[data-testid="delete-confirm-label"]')).toContainText('Siang')
    await expect(page.locator('[data-testid="delete-confirm-label"]')).toContainText(LINE_A)

    await Promise.all([
      page.waitForResponse((response) => response.url().includes('/livewire/update')),
      page.locator('[data-testid="confirm-delete-button"]').click(),
    ])

    // Skenario 33 — HILANG DARI KEDUANYA. Barisnya masih ada di basis data
    // (soft delete), jadi sebuah jalur yang lupa mengecualikan baris tertutup
    // akan tetap menggambarnya di salah satu dari dua tempat ini.
    await expect(page.locator(`[data-testid="shift-row-${siangId}"]`)).toHaveCount(0)
    await expect(page.locator(`[data-testid="timeline-block-${siangId}"]`)).toHaveCount(0)
    await expect(badge(page, lineAId)).toHaveText('ada celah 8 jam')

    // Blok celah muncul TEPAT di posisi yang ditinggalkan Siang — 15:00 pada
    // sumbu 00:00 adalah offset 900.
    const gapBlock = page.locator(`[data-testid="timeline-gap-${lineAId}-900"]`)
    await expect(gapBlock).toBeVisible()

    const gapGeometry = await geometryOf(gapBlock, 'blok celah')
    expect(Math.abs(gapGeometry.left - siangGeometry.left)).toBeLessThanOrEqual(EDGE_TOLERANCE)
    expect(Math.abs(gapGeometry.width - siangGeometry.width)).toBeLessThanOrEqual(EDGE_TOLERANCE)

    // BERTEKSTUR, bukan ruang putih — ruang putih terbaca sebagai latar
    // kartu dan bukan sebagai temuan. Dibaca dari CSS hasil compile.
    const gapBackground = await gapBlock.evaluate((element) => window.getComputedStyle(element).backgroundImage)
    expect(gapBackground, 'blok celah tidak berarsiran').toContain('gradient')

    // Shift yang masih ada tetap di tempatnya.
    await expect(blockSegment(page, pagiId)).toBeVisible()

    await expectNoBlockPastTrackEdge(page)

    expect(problems).toEqual([])
  })

  // ── Skenario 20 + 30 ──────────────────────────────────────────────────

  /*
   * SKENARIO 30 ADALAH SKENARIO 20 SESUDAH SOFT DELETE: sebuah line yang
   * SELURUH shift-nya sudah ditutup wajib terbaca sama seperti line yang
   * belum pernah diberi shift — 'belum diatur' dengan blok polos, BUKAN celah
   * 24 jam. Keduanya satu tindakan peramban yang sama, jadi satu test.
   */
  test('skenario 20 + 30 — hapus shift terakhir: badge "belum diatur" dan bloknya polos tanpa arsiran celah', async ({ page }) => {
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

  // ── Skenario 29 + 31 ──────────────────────────────────────────────────

  /*
   * PENGGANTIAN — justru alasan soft delete dipilih alih-alih penghapusan
   * permanen, dan karena itu diuji sebagai SATU tindakan: shift yang baru
   * dihapus diganti dengan shift yang memakai NAMA dan JAM-nya sekaligus.
   *
   * Dua penegak harus sepakat agar ini berhasil. NAMA: partial unique index
   * `WHERE deleted_at IS NULL` di basis data dan Rule::unique() di aplikasi,
   * yang keduanya wajib mengecualikan baris tertutup. JAM: himpunan pembanding
   * tumpang tindih, jalur yang paling mudah terlewat — bila baris tertutup
   * ikut dibandingkan, jam yang baru dikosongkan tidak akan pernah bisa
   * dipakai ulang, dan penolakannya akan menyebut shift yang sudah tidak
   * terlihat di mana pun.
   */
  test('skenario 29 + 31 — nama DAN jam shift yang dihapus bebas dipakai ulang penggantinya', async ({ page }) => {
    const problems = watchProblems(page)
    const { businessUnitId } = await openScreenAsAdmin(page)
    const lineAId = await productionLineIdByName(page, businessUnitId, LINE_A)

    const pagiId = await seedShift(page, { productionLineId: lineAId, name: 'Pagi', start: '07:00', end: '15:00' })

    await page.goto(SHIFTS_PATH)
    await selectMill(page, BUSINESS_UNIT)
    await selectLineFilter(page, LINE_A)

    await page.locator(`[data-testid="delete-button-${pagiId}"]`).click()
    await Promise.all([
      page.waitForResponse((response) => response.url().includes('/livewire/update')),
      page.locator('[data-testid="confirm-delete-button"]').click(),
    ])

    await expect(page.locator(`[data-testid="shift-row-${pagiId}"]`)).toHaveCount(0)
    await expect(badge(page, lineAId)).toHaveText('belum diatur')

    // NAMA DAN JAM yang sama persis dengan shift yang baru ditutup.
    await addShiftViaUi(page, { lineName: LINE_A, name: 'Pagi', start: '07:00', end: '15:00' })
    await expectSaved(page)

    // Tidak ada pesan penolakan dari kedua penegaknya.
    await expect(page.locator('[data-testid="form-error"]')).toHaveCount(0)
    await expect(badge(page, lineAId)).toHaveText('ada celah 16 jam')

    // Barisnya muncul di tabel, dan hanya SATU 'Pagi' yang hidup — baris
    // penggantinya, bukan baris yang ditutup.
    const body = await listShifts(page, businessUnitId, lineAId)
    expect(body.data.filter((row) => row.name === 'Pagi')).toHaveLength(1)
    expect(body.data[0].id).not.toBe(pagiId)

    const rowId = body.data[0].id
    await expect(page.locator(`[data-testid="shift-row-${rowId}"]`)).toBeVisible()
    await expect(page.locator(`[data-testid="timeline-block-${rowId}"]`)).toBeVisible()

    expect(problems).toEqual([])
  })

  // ── Skenario 32 ───────────────────────────────────────────────────────

  /*
   * PENGUBAHAN MEMUTASI BARIS, dan itu disengaja (business_logic 30).
   * Penggantian-saat-ubah dipicu oleh "ada record yang merujuk shift ini", dan
   * tidak ada satu kolom pun di sistem yang merujuk shift hari ini —
   * `shift_id` baru lahir bersama penandaan kedelapan belas tabel record. Yang
   * diasersi di sini karena itu adalah perilaku yang BENAR-BENAR ADA: baris
   * yang sama menampilkan jam baru, dan tidak muncul baris kedua.
   */
  test('skenario 32 — mengubah jam: baris yang SAMA menampilkan jam baru, nol baris kedua', async ({ page }) => {
    const problems = watchProblems(page)
    const { businessUnitId } = await openScreenAsAdmin(page)
    const lineAId = await productionLineIdByName(page, businessUnitId, LINE_A)

    const pagiId = await seedShift(page, { productionLineId: lineAId, name: 'Pagi', start: '07:00', end: '15:00' })

    await page.goto(SHIFTS_PATH)
    await selectMill(page, BUSINESS_UNIT)
    await selectLineFilter(page, LINE_A)

    await expect(page.locator(`[data-testid="shift-row-${pagiId}"]`)).toContainText('15:00')

    await page.locator(`[data-testid="edit-button-${pagiId}"]`).click()
    await expect(page.locator('[data-testid="form-end-time-input"]')).toHaveValue('15:00')

    await fillAndKeep(page, 'form-end-time-input', '16:00')
    await Promise.all([
      page.waitForResponse((response) => response.url().includes('/livewire/update')),
      page.locator('[data-testid="save-button"]').click(),
    ])

    await expectSaved(page)

    // BARIS YANG SAMA (id tidak berubah) dengan jam yang baru...
    await expect(page.locator(`[data-testid="shift-row-${pagiId}"]`)).toContainText('16:00')

    // ...dan NOL baris kedua, juga nol baris tertutup baru: tabelnya memuat
    // tepat satu baris untuk line ini.
    expect(await orderedTestIds(page, 'shift-row-')).toEqual([pagiId])
    expect(await orderedStartBlockIds(page)).toEqual([pagiId])

    const body = await listShifts(page, businessUnitId, lineAId)
    expect(body.data).toHaveLength(1)
    expect(body.data[0].id).toBe(pagiId)
    expect(body.data[0].end_time).toBe('16:00')

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

    // KEDUANYA DIBACA DARI DOM, lalu dibandingkan — pada potongan tempat tiap
    // shift DIMULAI. Malam melewati tengah malam, jadi potongan keduanya
    // jatuh di tepi kiri batang dan urutan SELURUH elemen blok adalah
    // [Malam, Pagi, Siang, Malam]. Menuntutnya sama dengan urutan tabel hanya
    // bisa dilakukan dengan berhenti menggambar potongan itu — yang justru
    // cacat yang baru diperbaiki.
    expect(await orderedStartBlockIds(page)).toEqual(expected)
    expect(await orderedTestIds(page, 'shift-row-')).toEqual(expected)
    expect(await orderedTestIds(page, 'timeline-block-')).toEqual([malamId, pagiId, siangId, malamId])

    // Bukan urutan penambahan, dan bukan abjad nama.
    expect(await orderedTestIds(page, 'shift-row-')).not.toEqual([malamId, pagiId, siangId])

    // LEBAR SEBANDING DURASI, diuji sebagai RASIO: Pagi 480 menit, Siang 240
    // menit, Malam 480 menit TERBAGI DUA POTONGAN — jadi yang dibandingkan
    // dengan Pagi adalah JUMLAH lebar kedua potongan Malam.
    const pagi = await geometryOf(blockSegment(page, pagiId), 'blok Pagi')
    const siang = await geometryOf(blockSegment(page, siangId), 'blok Siang')
    const malamStart = await geometryOf(blockSegment(page, malamId, 0), 'potongan 23:00–24:00')
    const malamWrap = await geometryOf(blockSegment(page, malamId, 1), 'potongan 00:00–07:00')

    expect(pagi.width / siang.width).toBeGreaterThan(1.9)
    expect(pagi.width / siang.width).toBeLessThan(2.1)
    expect(Math.abs(pagi.width - (malamStart.width + malamWrap.width))).toBeLessThanOrEqual(EDGE_TOLERANCE)

    // Dan posisi jam-mulai naik searah dengan urutannya.
    expect(pagi.left).toBeLessThan(siang.left)
    expect(siang.left).toBeLessThan(malamStart.left)

    // Potongan lanjutan Malam berada di SEBELAH KIRI Pagi — bukan kekeliruan
    // urutan, melainkan konsekuensi sumbu yang dimulai 00:00.
    expect(malamWrap.left).toBeLessThan(pagi.left)

    await expectNoBlockPastTrackEdge(page)

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
