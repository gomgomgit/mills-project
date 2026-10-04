/**
 * Detail Periode Pelaporan (Browser/Playwright) —
 * screen-142--detail-periode-pelaporan / usecase-145 (lihat detail) +
 * usecase-140 (tutup & buka kembali) + usecase-144 (buka stasiun draft).
 *
 * ── MENGAPA SPEC INI ADA (2026-09-27) ───────────────────────────────────
 *
 * Sampai 2026-09-26 daftar stasiun sebuah periode adalah ACCORDION di
 * screen-128: sebuah <tr> kedua yang hanya dirender selama id periodenya ada
 * di $expandedPeriodIds. Seluruh aksi per stasiun hidup di dalamnya, dan
 * seluruh pengujiannya hidup di tests/kelola-periode-pelaporan.spec.ts.
 *
 * Accordion itu dicabut. Daftar stasiun dan SETIAP aksi per stasiun kini
 * punya layarnya sendiri, `/master-data/periods/{id}`, yang dicapai lewat
 * nama periode pada daftar (`period-link-{periodId}`, sebuah <a href>
 * sungguhan dengan wire:navigate). Test yang menggerakkan aksi stasiun
 * PINDAH ke berkas ini apa adanya — bukan ditulis ulang lebih longgar:
 *
 *   - Scenario 13  dialog tutup: angka belum terverifikasi per stasiun
 *   - Scenario 10  tutup stasiun (sukses)
 *   - Scenario 11  buka kembali stasiun
 *   - Scenario 12  membatalkan dialog Tutup Stasiun
 *   - Scenario 16  baris Tertutup: tidak ada Tutup Stasiun
 *   - Scenario 17  dua Admin menutup stasiun yang sama
 *   - Scenario 18  non-Admin: tidak ada Tutup/Buka Kembali
 *   - Scenario 21  buka stasiun draft (sukses) + pembatalannya
 *   - Scenario 22  baris Terbuka: tidak ada Buka Stasiun
 *   - Scenario 23  baris Tertutup: hanya Buka Kembali
 *   - Scenario 24  periode dihapus pengguna lain saat dialog terbuka
 *   - Scenario 25  dua Admin membuka stasiun yang sama
 *   - Scenario 26  non-Admin: tidak ada Buka Stasiun
 *
 * ditambah skenario yang SEBELUMNYA MUSTAHIL ditulis, karena accordion tidak
 * pernah punya halaman sendiri: navigasi daftar → detail → kembali, periode
 * tidak ditemukan, mill tanpa stasiun aktif, dan hapus-dari-detail yang
 * me-redirect ke daftar.
 *
 * ── DUA JENIS ID, DAN HANYA SATU MILIK HALAMAN INI ───────────────────────
 *
 * Parameter rute adalah id PERIODE — yang diterima Edit dan Hapus. SETIAP
 * aksi per stasiun menerima id `period_stations` (`stations[].id`), dan
 * setiap testid per-stasiun membawanya. Mengirim id periode ke endpoint
 * `/api/period-stations/{id}/...` adalah kekeliruan termahal layar ini, jadi
 * id stasiun TIDAK PERNAH ditebak di sini: ia dibaca dari atribut
 * data-testid barisnya lewat stationIdFor() (tests/support/period-screen.ts).
 *
 * FIXTURES — memakai ulang akun yang sudah dibuat BrowserTestFixtureSeeder:
 *   - butest-admin01     (Admin,      nama "Butest admin01")
 *   - pltest-admin01     (Admin,      nama "Pltest admin01") — Admin kedua
 *   - butest-nonadmin01  (Supervisor) — skenario "akses ditolak"
 *   - Business Unit "BU Browser Test" — mill dengan belasan jenis stasiun
 *     aktif. Jumlahnya TIDAK PERNAH di-hardcode: ia dibaca dari pratinjau
 *     stasiun pada form, atau dari ringkasan periodenya sendiri.
 * Password: PASSWORD dari ./support/auth.
 *
 * ── RUANG TANGGAL ────────────────────────────────────────────────────────
 *
 * Satu tanggal hanya boleh dimiliki satu periode pada satu mill
 * (PeriodService::findOverlapping()), jadi spec ini harus menjauh dari
 * tests/kelola-periode-pelaporan.spec.ts yang menanam periode di mill yang
 * SAMA. Keduanya memakai epoch dan RUN_OFFSET yang identik (menit sejak
 * 2026-01-01, monoton) — spec ini menggesernya DETAIL_BASE hari ke depan,
 * jauh melewati slot terjauh spec itu (rejectedRange(26) = +1260 hari).
 * Karena kedua RUN_OFFSET dihitung pada run yang sama dan hanya berbeda
 * beberapa menit, jarak 5.000 hari tidak mungkin terjembatani.
 *
 * SLOT TUMBUH MENURUT URUTAN DEKLARASI, disengaja: daftar dipaginasi 20 baris
 * ORDER BY start_date DESC dan spec ini menumpuk satu periode per test sampai
 * afterAll, jadi baris milik sebuah test harus selalu menyortir DI ATAS
 * baris yang ditinggalkan test-test sebelumnya. Skenario berjendela tetap
 * (Agustus 2026, angka belum terverifikasi) karena itu berjalan PALING AWAL,
 * selagi daftar masih pendek.
 */

import { test, expect, type Locator, type Page } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { deletePeriodsByPrefix, statefulHeaders } from './support/periods'
import {
  PERIODS_PATH,
  createPeriodViaUi,
  gotoPeriodDetail,
  openStation,
  openStationRow,
  periodDetailPath,
  periodIdFor,
  periodRow,
  selectSearchable,
  stationIdFor,
} from './support/period-screen'

const ADMIN = 'butest-admin01'
const ADMIN_NAME = 'Butest admin01'
const SECOND_ADMIN = 'pltest-admin01'
const SECOND_ADMIN_NAME = 'Pltest admin01'
const NON_ADMIN = 'butest-nonadmin01'

const BUSINESS_UNIT = 'BU Browser Test'

/** Baris stasiun yang digerakkan hampir setiap skenario. */
const STATION_TYPE = 'Sterilizer'

/** Baris stasiun kedua, untuk membuktikan keduanya tidak pernah bergerak bersama. */
const OTHER_STATION_TYPE = 'Clarification'

/**
 * Satu-satunya jenis stasiun yang punya record belum terverifikasi di dalam
 * jendela Agustus 2026 yang ditanam BrowserTestFixtureSeeder (satu record
 * Effluent Plant bertanggal 2026-08-05, tanpa checked_by maupun
 * acknowledged_by). Jenis lain tidak punya satu pun di jendela itu — justru
 * itulah yang membuat angka per-stasiun dapat dibuktikan lewat browser.
 */
const UNVERIFIED_STATION_TYPE = 'Effluent Plant'

/**
 * Periode fixture skenario dialog tutup — ditanam
 * BrowserTestFixtureSeeder::periodFixtures() di "Mill Periode Uji",
 * 2026-08-01..2026-08-15, seluruh stasiunnya Draft setiap seed. Mill itu
 * TERSENDIRI karena "BU Browser Test" memegang periode "Prasyarat Form"
 * 2020-01-01..hari ini+7 (tests/support/period-fixture.ts) yang menutupi
 * Agustus 2026.
 */
const FIXTURE_PERIOD_NAME = 'Fixture Periode Agustus 2026'

/**
 * Mill tanpa satu pun stasiun aktif, sehingga periodenya tidak mendapat baris
 * `period_stations` sama sekali — ringkasan `empty` / "Tanpa Stasiun", yang
 * tidak dapat diproduksi di mill yang sudah dilengkapi.
 */
const EMPTY_MILL = 'Mill Kode Duplikat'

/** Periode yang tidak pernah ada — bentuk uuid valid, agar yang diuji benar-benar 404 dan bukan galat tipe. */
const MISSING_PERIOD_ID = '00000000-0000-4000-8000-000000000000'

/**
 * Offset hari per run, monoton (satu hari ruang-tanggal per menit jam dinding
 * sejak 2026-01-01) — rumus yang sama persis dengan
 * tests/kelola-periode-pelaporan.spec.ts, agar DETAIL_BASE di bawah benar-benar
 * sebanding dan pemisahannya aritmetis, bukan kebetulan.
 */
const RUN_OFFSET = Math.floor((Date.now() - Date.UTC(2026, 0, 1)) / 60000)

/**
 * Jarak dari slot-slot tests/kelola-periode-pelaporan.spec.ts, dalam hari.
 * Slot terjauh spec itu adalah rejectedRange(26) = RUN_OFFSET + 1.260; 5.000
 * hari memberi margin yang tidak mungkin terjembatani oleh selisih beberapa
 * menit antara dua RUN_OFFSET pada run yang sama.
 */
const DETAIL_BASE = 5000

function isoDate(dayOffset: number): string {
  return new Date(Date.UTC(2100, 0, 1) + dayOffset * 86400000).toISOString().slice(0, 10)
}

/** Jendela lima hari, unik untuk run ini DAN untuk slot skenario ini. */
function uniqueRange(slot: number): { start: string; end: string } {
  const startDay = RUN_OFFSET + DETAIL_BASE + slot * 10

  return { start: isoDate(startDay), end: isoDate(startDay + 4) }
}

/**
 * Jendela periode "Tanpa Stasiun", yang hidup di MILL LAIN.
 *
 * Tahun 9600, sama seperti skenario sejenis di
 * tests/kelola-periode-pelaporan.spec.ts — tapi 50.000 hari lebih jauh,
 * melewati batas atas offsetnya (RUN_OFFSET % 40000), sehingga keduanya tidak
 * mungkin bertemu di mill yang sama. Periodenya dihapus di dalam test-nya
 * sendiri; lajur ini sabuk pengaman kedua.
 */
function emptyMillRange(): { start: string; end: string } {
  const startDay = 50000 + (RUN_OFFSET % 20000)

  const iso = (offset: number) =>
    new Date(Date.UTC(9600, 0, 1) + offset * 86400000).toISOString().slice(0, 10)

  return { start: iso(startDay), end: iso(startDay + 4) }
}

function uniqueName(label: string): string {
  return `Periode Detail ${label} ${RUN_OFFSET}`
}

async function gotoPeriods(page: Page): Promise<void> {
  await page.goto(PERIODS_PATH)
}

/** Membuat periode lewat UI daftar dan menunggu barisnya muncul. */
async function createPeriod(
  page: Page,
  options: { name: string; start: string; end: string; businessUnit?: string },
): Promise<void> {
  await createPeriodViaUi(page, {
    businessUnit: options.businessUnit ?? BUSINESS_UNIT,
    name: options.name,
    start: options.start,
    end: options.end,
  })
}

/**
 * Membuat periode lalu langsung membuka layar detailnya. Mengembalikan kedua
 * id yang dibutuhkan skenario: id PERIODE (rute, Edit, Hapus) dan id
 * `period_stations` untuk `stationLabel` (aksi tutup/buka).
 */
async function createPeriodAndOpenDetail(
  page: Page,
  options: { name: string; start: string; end: string; stationLabel?: string },
): Promise<{ periodId: string; stationId: string }> {
  await createPeriod(page, options)

  return openStationRow(page, options.name, options.stationLabel ?? STATION_TYPE)
}

/** Id periode fixture FIXTURE_PERIOD_NAME, dibaca dari GET /api/periods. */
async function fixturePeriodId(page: Page): Promise<string> {
  const headers = await statefulHeaders(page)

  for (let pageNo = 1; pageNo <= 50; pageNo += 1) {
    const response = await page.request.get(`/api/periods?page=${pageNo}&per_page=100`, { headers })
    expect(response.ok(), `tidak bisa membaca daftar periode: ${response.status()}`).toBe(true)

    const rows = ((await response.json()).data ?? []) as Array<{ id: string; name: string }>
    const match = rows.find((row) => row.name === FIXTURE_PERIOD_NAME)

    if (match !== undefined) {
      return match.id
    }

    if (rows.length < 100) {
      break
    }
  }

  throw new Error(`periode fixture "${FIXTURE_PERIOD_NAME}" tidak ada — jalankan BrowserTestFixtureSeeder`)
}

/** Satu baris stasiun pada layar detail. */
function stationRow(page: Page, stationId: string): Locator {
  return page.locator(`[data-testid="period-station-row-${stationId}"]`)
}

/**
 * Membuka dialog "Tutup Stasiun" untuk satu baris, membawanya dari Draft ke
 * Terbuka lebih dulu bila perlu — tombolnya hanya ada pada baris Terbuka.
 */
async function askCloseStation(page: Page, periodId: string, stationId: string): Promise<void> {
  await openStation(page, periodId, stationId)
  await page.locator(`[data-testid="station-close-button-${stationId}"]`).click()
  await expect(page.locator('[data-testid="unverified-warning"]')).toBeVisible()
}

/** Menutup satu baris stasiun sampai badge-nya benar-benar "Tertutup". */
async function closeStationHere(page: Page, periodId: string, stationId: string): Promise<void> {
  await askCloseStation(page, periodId, stationId)
  await page.locator('[data-testid="confirm-close-button"]').click()
  await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Tertutup')
}

/** Membuka kembali satu baris stasiun tertutup, dari layar detail. */
async function reopenStationHere(page: Page, stationId: string): Promise<void> {
  await page.locator(`[data-testid="station-reopen-button-${stationId}"]`).click()
  await page.locator('[data-testid="confirm-reopen-button"]').click()
  await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Terbuka')
}

/**
 * Menghapus periode dari LAYAR DETAIL dan menunggu redirect ke daftar.
 * Hapus yang berhasil tidak boleh meninggalkan Admin di halaman "tidak
 * ditemukan" untuk sesuatu yang baru saja sengaja dihapusnya.
 */
async function deletePeriodFromDetail(page: Page, periodId: string, name: string): Promise<void> {
  await page.locator(`[data-testid="delete-button-${periodId}"]`).click()
  await page.locator('[data-testid="confirm-delete-button"]').click()

  await expect(page).toHaveURL(new RegExp(`${PERIODS_PATH}$`))
  await expect(page.locator('[data-testid="period-table"]')).toBeVisible()
  await expect(periodRow(page, name)).toHaveCount(0)
}

/** Menghapus periode dari DAFTAR, lewat konfirmasi inline di barisnya. */
async function deletePeriodFromList(page: Page, name: string): Promise<void> {
  const periodId = await periodIdFor(page, name)

  await page.locator(`[data-testid="delete-button-${periodId}"]`).click()
  await page.locator('[data-testid="confirm-delete-button"]').click()

  await expect(periodRow(page, name)).toHaveCount(0)
}

test.describe('Detail Periode Pelaporan', () => {
  // Pembersihan — lihat tests/support/periods.ts untuk alasan lengkapnya.
  // Tanpa ini periode yang dibuat spec ini menumpuk di database dev sampai
  // memenuhi halaman 1 daftar yang dipaginasi 20 baris, dan suite mulai gagal
  // 1-2 run KEMUDIAN di createPeriod() sebelum satu pun asersi jalan.
  test.afterAll(async ({ browser }) => {
    const page = await browser.newPage()

    try {
      await login(page, ADMIN, PASSWORD)
      const deleted = await deletePeriodsByPrefix(page, ['Periode Detail '])
      console.log('[cleanup] detail-periode-pelaporan: %d periode dihapus', deleted)
    } catch (error) {
      // Kegagalan membersihkan bukan kegagalan produk — jangan pernah
      // memerahkan suite karenanya.
      console.warn('[cleanup] detail-periode-pelaporan: pembersihan gagal:', error)
    } finally {
      await page.close()
    }
  })

  // ── Scenario 13 — PINDAH dari screen-128 ──────────────────────────────
  //
  // Memakai periode fixture "Fixture Periode Agustus 2026" di "Mill Periode
  // Uji": jendelanya harus mencakup 2026-08-05, tanggal record Effluent Plant
  // belum terverifikasi milik BrowserTestFixtureSeeder::periodFixtures().
  // Id-nya dibaca lewat API, bukan dari daftar, jadi posisinya di daftar yang
  // dipaginasi tidak berpengaruh.
  test('dialog tutup: angka belum terverifikasi milik stasiun yang ditutup, bukan se-periode', async ({ page }) => {
    await login(page, ADMIN, PASSWORD)

    // Periode FIXTURE, bukan periode buatan test (sejak 2026-10-04): jendela
    // Agustus 2026 ini membingkai record Effluent Plant belum terverifikasi,
    // dan periode yang membingkai record tidak bisa dihapus (409
    // PERIOD_HAS_RECORDS) — periode buatan test akan tertinggal dan menolak
    // run berikutnya dengan PERIOD_OVERLAP. Lihat FIXTURE_PERIOD_NAME.
    const periodId = await fixturePeriodId(page)
    await gotoPeriodDetail(page, periodId)

    const unverifiedId = await stationIdFor(page, periodId, UNVERIFIED_STATION_TYPE)
    const quietId = await stationIdFor(page, periodId, STATION_TYPE)

    // Stasiun yang PUNYA record belum terverifikasi: angka nyata bukan nol,
    // dinamai menurut jenis stasiunnya, rinciannya, dan penjelasan bahwa
    // verifikasi ikut terkunci.
    await askCloseStation(page, periodId, unverifiedId)
    await expect(page.locator('[data-testid="unverified-count"]')).toContainText(
      new RegExp(`[1-9]\\d* data ${UNVERIFIED_STATION_TYPE} belum terverifikasi`),
    )
    await expect(page.locator('[data-testid="unverified-breakdown"]')).toContainText(UNVERIFIED_STATION_TYPE)
    await expect(page.locator('[data-testid="unverified-warning"]')).toContainText('verifikasi ikut terkunci')
    await page.locator('[data-testid="cancel-close-button"]').click()
    await expect(page.locator('[data-testid="unverified-warning"]')).toHaveCount(0)

    // INTI SELURUH REVISI: periode yang sama, rentang yang sama, stasiun yang
    // berbeda — dan angka yang berbeda. Angka se-periode akan melaporkan
    // record Effluent Plant itu di sini juga.
    await askCloseStation(page, periodId, quietId)
    // toContainText, dan (^|\s) alih-alih ^ telanjang: matcher RegExp
    // diterapkan pada node teks mentah, yang di-indent blade menjadi dua
    // baris — normalisasi whitespace toHaveText hanya berlaku untuk bentuk
    // string. Penjaga yang penting adalah batas kata, agar "10" tidak pernah
    // memenuhi asersi yang ditulis untuk "0".
    await expect(page.locator('[data-testid="unverified-count"]')).toContainText(
      new RegExp(`(^|\\s)0 data ${STATION_TYPE} belum terverifikasi`),
    )
    await page.locator('[data-testid="cancel-close-button"]').click()

    // Angkanya tidak pernah menghalangi penutupan — penutupan tetap berjalan.
    await askCloseStation(page, periodId, unverifiedId)
    await page.locator('[data-testid="confirm-close-button"]').click()
    await expect(page.locator(`[data-testid="station-status-badge-${unverifiedId}"]`)).toHaveText('Tertutup')

    // Stasiunnya dibuka kembali supaya periode fixture tidak tertinggal
    // terkunci bila spec ini diulang tanpa seed; BrowserTestFixtureSeeder
    // (globalSetup) tetap mengembalikan seluruh barisnya ke Draft setiap run.
    // Periodenya TIDAK dihapus — lihat FIXTURE_PERIOD_NAME.
    await reopenStationHere(page, unverifiedId)
  })

  // ── usecase-145: halaman detail itu sendiri ───────────────────────────

  // PINDAH dari screen-128 (bagian per-stasiun dari skenario "menambah
  // periode baru"): baris stasiun tidak lagi ada di daftar, jadi asersinya
  // pindah ke sini bersama barisnya.
  test('periode baru: satu baris stasiun per inventaris mill, semuanya Draft, urut sesuai master', async ({ page }) => {
    const { start, end } = uniqueRange(1)
    const name = uniqueName('Baru')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)

    // Dibuat manual (bukan lewat createPeriod) semata untuk MEREKAM urutan
    // pratinjau stasiunnya. Urutan itulah pembanding yang sah untuk urutan
    // tabel di layar detail: keduanya harus mengikuti station_types.sort_order,
    // dan tidak ada satu pun label yang di-hardcode di sini.
    await page.locator('[data-testid="add-period-button"]').click()
    await selectSearchable(page, 'business_unit_id', BUSINESS_UNIT)
    const previewItems = page.locator('[data-testid="station-preview"] .kc-preview__list li')
    await expect(previewItems.first()).toBeVisible()
    const previewLabels = (await previewItems.allTextContents()).map((label) => label.trim())
    expect(previewLabels.length).toBeGreaterThan(1)

    await page.locator('#name').fill(name)
    await page.locator('#start_date').fill(start)
    await page.locator('#end_date').fill(end)
    await page.locator('[data-testid="save-button"]').click()
    await expect(periodRow(page, name)).toBeVisible()

    const periodId = await periodIdFor(page, name)
    await gotoPeriodDetail(page, periodId)

    // Ringkasan periodenya, bukan ringkasan salah satu stasiunnya.
    await expect(page.locator('[data-testid="period-name"]')).toHaveText(name)
    await expect(page.locator('[data-testid="period-business-unit"]')).toHaveText(BUSINESS_UNIT)
    await expect(page.locator(`[data-testid="status-summary-${periodId}"]`)).toHaveText('Draft')
    await expect(page.locator(`[data-testid="station-summary-${periodId}"]`))
      .toHaveText(`${previewLabels.length} stasiun`)

    // Satu baris per jenis stasiun yang dipratinjau, DALAM URUTAN YANG SAMA.
    const rows = page.locator(`[data-testid="period-stations-${periodId}"] tbody tr`)
    await expect(rows).toHaveCount(previewLabels.length)
    const renderedLabels = await rows.locator('td:first-child').allTextContents()
    expect(renderedLabels.map((label) => label.trim())).toEqual(previewLabels)

    // Semuanya Draft, dan kolom penutupan kosong pada setiap baris.
    const badges = page.locator('[data-testid^="station-status-badge-"]')
    await expect(badges).toHaveCount(previewLabels.length)
    for (const text of await badges.allTextContents()) {
      expect(text.trim()).toBe('Draft')
    }

    const stationId = await stationIdFor(page, periodId, STATION_TYPE)
    await expect(stationRow(page, stationId).locator('td').nth(2)).toHaveText('—')
    await expect(stationRow(page, stationId).locator('td').nth(3)).toHaveText('—')
    // Setiap baris Draft menawarkan "Buka Stasiun" dan tidak satu pun yang
    // lain — siklusnya tidak punya jalan pintas.
    await expect(page.locator(`[data-testid="station-open-button-${stationId}"]`)).toBeVisible()
    await expect(page.locator(`[data-testid="station-close-button-${stationId}"]`)).toHaveCount(0)
    await expect(page.locator(`[data-testid="station-reopen-button-${stationId}"]`)).toHaveCount(0)
  })

  // Skenario yang MUSTAHIL sebelum pemisahan: accordion tidak punya URL,
  // tidak punya tautan masuk, dan tidak punya jalan kembali.
  test('navigasi: nama periode di daftar membuka layar detail, dan ada jalan kembali ke daftar', async ({ page }) => {
    const { start, end } = uniqueRange(2)
    const name = uniqueName('Navigasi')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end })

    const periodId = await periodIdFor(page, name)

    // Tautan sungguhan: href-nya menunjuk rute detail, sehingga baris ini
    // dapat dibuka di tab baru dan di-bookmark.
    const link = page.locator(`[data-testid="period-link-${periodId}"]`)
    await expect(link).toHaveText(name)
    await expect(link).toHaveAttribute('href', new RegExp(`${periodDetailPath(periodId)}$`))

    await link.click()

    await expect(page).toHaveURL(new RegExp(`${periodDetailPath(periodId)}$`))
    await expect(page.locator('[data-testid="period-summary"]')).toBeVisible()
    await expect(page.locator('[data-testid="period-name"]')).toHaveText(name)

    // Dan jalan kembalinya mendarat di daftar, dengan barisnya masih ada.
    await page.locator('[data-testid="back-to-periods"]').click()
    await expect(page).toHaveURL(new RegExp(`${PERIODS_PATH}$`))
    await expect(periodRow(page, name)).toBeVisible()
    // Accordion-nya benar-benar tidak ada lagi: tidak ada toggle, dan tidak
    // ada satu pun tabel stasiun di daftar.
    await expect(page.locator(`[data-testid="expand-button-${periodId}"]`)).toHaveCount(0)
    await expect(page.locator(`[data-testid="period-stations-${periodId}"]`)).toHaveCount(0)
  })

  // PINDAH dari screen-128 ("stasiun tidak serentak"), bagian per-stasiunnya.
  // Inti seluruh revisi per-stasiun: stasiun tidak selesai serentak, dan
  // layar detail harus merendernya apa adanya alih-alih memilih satu sisi.
  test('status campuran: satu tertutup satu terbuka sisanya Draft, dan menutup satu tidak mengubah baris lain', async ({ page }) => {
    const { start, end } = uniqueRange(3)
    const name = uniqueName('Campuran')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    const { periodId } = await createPeriodAndOpenDetail(page, { name, start, end })

    const closedId = await stationIdFor(page, periodId, STATION_TYPE)
    const openId = await stationIdFor(page, periodId, OTHER_STATION_TYPE)

    // Semuanya berangkat identik: periode sama, rentang sama, status sama.
    await expect(page.locator(`[data-testid="station-status-badge-${closedId}"]`)).toHaveText('Draft')
    await expect(page.locator(`[data-testid="station-status-badge-${openId}"]`)).toHaveText('Draft')

    // Bawa stasiun kedua ke Terbuka dan tinggalkan di situ.
    await openStation(page, periodId, openId)

    // Tutup yang pertama saja.
    await closeStationHere(page, periodId, closedId)

    // MENUTUP SATU STASIUN TIDAK MENGUBAH APA PUN PADA YANG LAIN: baris yang
    // sama, periode yang sama, masih Terbuka, kolom penutupan masih kosong,
    // dan masih menawarkan Tutup Stasiun-nya sendiri.
    await expect(page.locator(`[data-testid="station-status-badge-${openId}"]`)).toHaveText('Terbuka')
    await expect(stationRow(page, openId).locator('td').nth(2)).toHaveText('—')
    await expect(stationRow(page, openId).locator('td').nth(3)).toHaveText('—')
    await expect(page.locator(`[data-testid="station-close-button-${openId}"]`)).toBeVisible()
    // ...dan yang tertutup menyebut penutupnya.
    await expect(stationRow(page, closedId).locator('td').nth(2)).toHaveText(ADMIN_NAME)
    await expect(stationRow(page, closedId).locator('td').nth(3)).not.toHaveText('—')

    // Sisanya tidak ikut bergerak: masih ada baris Draft di tabel yang sama.
    await expect(page.locator('[data-testid^="station-status-badge-"]').filter({ hasText: 'Draft' }).first())
      .toBeVisible()

    // Badge ringkasan di kepala halaman meringkas perselisihan itu alih-alih
    // memilih satu sisi.
    await expect(page.locator(`[data-testid="status-summary-${periodId}"]`)).toHaveText('Campuran')
    await expect(page.locator(`[data-testid="station-summary-${periodId}"]`)).toContainText('1 tertutup')

    // Setelah reload, kedua status tetap sama — perbedaannya hidup di data,
    // bukan di kecelakaan rendering.
    await page.reload()
    await expect(page.locator(`[data-testid="station-status-badge-${closedId}"]`)).toHaveText('Tertutup')
    await expect(page.locator(`[data-testid="station-status-badge-${openId}"]`)).toHaveText('Terbuka')
    await expect(page.locator(`[data-testid="status-summary-${periodId}"]`)).toHaveText('Campuran')
  })

  // ── usecase-140: Tutup & Buka Kembali — PINDAH dari screen-128 ────────

  // Scenario 10: "Tutup & Buka Kembali Periode Pelaporan — success"
  test('tutup stasiun: dialog menyebut stasiunnya, setelah konfirmasi badge stasiun jadi Tertutup', async ({ page }) => {
    const { start, end } = uniqueRange(4)
    const name = uniqueName('Tutup Sukses')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    const { periodId, stationId } = await createPeriodAndOpenDetail(page, { name, start, end })

    await askCloseStation(page, periodId, stationId)

    await expect(page.locator('.kcm-modal')).toBeVisible()
    await expect(page.locator('[data-testid="unverified-count"]')).toContainText(
      `data ${STATION_TYPE} belum terverifikasi`,
    )
    // Dialognya tentang SATU stasiun pada SATU periode, dan menyebut keduanya.
    await expect(page.locator('.kcm-modal')).toContainText(name)
    await expect(page.locator('.kcm-modal')).toContainText('Jenis stasiun lain pada periode ini tidak terpengaruh')

    await page.locator('[data-testid="confirm-close-button"]').click()

    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Tertutup')
    // "Ditutup Oleh" menyebut Admin yang mengonfirmasinya.
    await expect(stationRow(page, stationId).locator('td').nth(2)).toHaveText(ADMIN_NAME)
    await expect(page.locator('.kcm-modal')).toHaveCount(0)
    await expect(page.locator('[data-testid="success-message"]')).toBeVisible()
  })

  // Scenario 11: "buka kembali periode yang sudah tertutup"
  test('buka kembali stasiun: badge jadi Terbuka, kolom penutupan kosong, tombol Tutup Stasiun kembali', async ({ page }) => {
    const { start, end } = uniqueRange(5)
    const name = uniqueName('Buka Kembali')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    const { periodId, stationId } = await createPeriodAndOpenDetail(page, { name, start, end })

    await closeStationHere(page, periodId, stationId)

    await page.locator(`[data-testid="station-reopen-button-${stationId}"]`).click()
    await page.locator('[data-testid="confirm-reopen-button"]').click()

    // reopen() mendarat di Terbuka, tidak pernah kembali ke Draft, dan
    // menghapus catatan siapa yang menutupnya.
    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Terbuka')
    await expect(stationRow(page, stationId).locator('td').nth(2)).toHaveText('—')
    await expect(stationRow(page, stationId).locator('td').nth(3)).toHaveText('—')
    await expect(page.locator(`[data-testid="station-close-button-${stationId}"]`)).toBeVisible()
    await expect(page.locator(`[data-testid="edit-button-${periodId}"]`)).toBeEnabled()
    await expect(page.locator(`[data-testid="delete-button-${periodId}"]`)).toBeEnabled()
    await expect(page.locator('[data-testid="success-message"]')).toBeVisible()
  })

  // Scenario 12: "Admin membatalkan penutupan"
  test('membatalkan dialog Tutup Stasiun: badge stasiun tetap dan kolom Ditutup Oleh tetap kosong', async ({ page }) => {
    const { start, end } = uniqueRange(6)
    const name = uniqueName('Batal Tutup')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    const { periodId, stationId } = await createPeriodAndOpenDetail(page, { name, start, end })

    await askCloseStation(page, periodId, stationId)

    await page.locator('[data-testid="cancel-close-button"]').click()

    await expect(page.locator('[data-testid="unverified-warning"]')).toHaveCount(0)
    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Terbuka')
    await expect(stationRow(page, stationId).locator('td').nth(2)).toHaveText('—')
    // Satu stasiun Terbuka di antara belasan yang masih Draft — ringkasannya
    // Campuran, bukan Terbuka.
    await expect(page.locator(`[data-testid="status-summary-${periodId}"]`)).toHaveText('Campuran')
  })

  // Scenario 16: "menutup periode yang sudah tertutup"
  test('baris stasiun Tertutup: tombol Tutup Stasiun tidak terlihat, Buka Kembali dapat diklik', async ({ page }) => {
    const { start, end } = uniqueRange(7)
    const name = uniqueName('Sudah Tertutup')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    const { periodId, stationId } = await createPeriodAndOpenDetail(page, { name, start, end })

    await closeStationHere(page, periodId, stationId)

    await expect(page.locator(`[data-testid="station-close-button-${stationId}"]`)).toHaveCount(0)
    await expect(page.locator(`[data-testid="station-reopen-button-${stationId}"]`)).toBeEnabled()
  })

  // Scenario 17: "dua Admin menutup periode bersamaan" — dua Admin menutup
  // STASIUN yang sama, masing-masing di layar detail periode itu.
  test('dua Admin menutup stasiun yang sama: Admin kedua diberi tahu, penutup pertama tetap tercatat', async ({ page, browser }) => {
    const { start, end } = uniqueRange(8)
    const name = uniqueName('Balapan Tutup')

    // Admin B menyiapkan barisnya, membawa stasiunnya ke Terbuka, dan
    // MEMBIARKAN halaman detailnya terbuka tanpa reload.
    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    const { periodId, stationId } = await createPeriodAndOpenDetail(page, { name, start, end })
    await openStation(page, periodId, stationId)

    // Admin A menutup stasiun yang sama di konteks browser lain.
    const otherContext = await browser.newContext()
    const otherPage = await otherContext.newPage()
    await login(otherPage, SECOND_ADMIN, PASSWORD)
    await gotoPeriodDetail(otherPage, periodId)
    await otherPage.locator(`[data-testid="station-close-button-${stationId}"]`).click()
    await otherPage.locator('[data-testid="confirm-close-button"]').click()
    await expect(otherPage.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Tertutup')
    await expect(stationRow(otherPage, stationId)).toContainText(SECOND_ADMIN_NAME)

    // Admin B, tanpa reload, mengonfirmasi penutupan stasiun yang sama.
    await page.locator(`[data-testid="station-close-button-${stationId}"]`).click()
    await page.locator('[data-testid="confirm-close-button"]').click()

    // Ia diberi tahu bahwa stasiunnya sudah tertutup, oleh siapa dan kapan —
    // dan kini kedua browser menampilkan Admin A sebagai penutupnya.
    await expect(page.locator('[data-testid="close-error"]')).toContainText(SECOND_ADMIN_NAME)
    await expect(page.locator('[data-testid="success-message"]')).toHaveCount(0)
    await expect(stationRow(page, stationId)).toContainText(SECOND_ADMIN_NAME)

    await otherPage.reload()
    await expect(stationRow(otherPage, stationId)).toContainText(SECOND_ADMIN_NAME)
    await otherContext.close()
  })

  // Scenario 18: "pengguna selain Admin menutup periode" — rute detail
  // dijaga 'auth' + 'role:admin' persis seperti daftarnya, jadi layar ini
  // tidak pernah mount untuk non-Admin.
  test('akses ditolak: non-Admin tidak mendapat layar detail maupun tombol Tutup/Buka Kembali', async ({ page, browser }) => {
    const { start, end } = uniqueRange(9)
    const name = uniqueName('Non Admin Tutup')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    const { periodId, stationId } = await createPeriodAndOpenDetail(page, { name, start, end })

    const otherContext = await browser.newContext()
    const otherPage = await otherContext.newPage()
    await login(otherPage, NON_ADMIN, PASSWORD)
    await otherPage.goto(periodDetailPath(periodId))

    // EnsureRole::forbidden() -> abort(403), halaman galat HTML bawaan Laravel.
    await expect(otherPage.locator('body')).toContainText(/403/)
    await expect(otherPage.locator('[data-testid="period-summary"]')).toHaveCount(0)
    await expect(otherPage.locator('[data-testid^="station-close-button-"]')).toHaveCount(0)
    await expect(otherPage.locator('[data-testid^="station-reopen-button-"]')).toHaveCount(0)
    await otherContext.close()

    // Diperiksa ulang sebagai Admin: stasiunnya persis seperti semula.
    await page.reload()
    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Draft')
    await expect(stationRow(page, stationId).locator('td').nth(2)).toHaveText('—')
  })

  // ── Periode induk di layar detail: Edit / Hapus ───────────────────────

  // Versi layar detail dari scenario 5. Layar ini membawa tombol Edit dan
  // Hapus periode yang sama, digerakkan `is_immutable` yang sama — dan di
  // sinilah stasiun yang menguncinya berada, jadi kedua arahnya dapat diuji
  // tanpa berpindah halaman satu kali pun.
  test('satu stasiun tertutup: Edit dan Hapus periode mati dengan penjelasan, hidup lagi setelah dibuka kembali', async ({ page }) => {
    const { start, end } = uniqueRange(10)
    const name = uniqueName('Terkunci')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    const { periodId, stationId } = await createPeriodAndOpenDetail(page, { name, start, end })

    const editButton = page.locator(`[data-testid="edit-button-${periodId}"]`)
    const deleteButton = page.locator(`[data-testid="delete-button-${periodId}"]`)

    // Berangkat dari keadaan hidup, supaya "mati" sesudahnya benar-benar
    // perubahan dan bukan keadaan awal.
    await expect(editButton).toBeEnabled()
    await expect(deleteButton).toBeEnabled()

    await closeStationHere(page, periodId, stationId)

    // MATI, BUKAN HILANG: `is_immutable` adalah kondisi persis yang ditolak
    // PeriodService::update()/delete() (409 PERIOD_CLOSED_IMMUTABLE), dan
    // periode dengan 1 stasiun tertutup dari belasan harus MEMPERLIHATKAN
    // bahwa pengubahan terhalang, bukan membuat Admin mencari tombol yang
    // lenyap. 409-nya sendiri diasersi di tingkat API.
    await expect(editButton).toBeDisabled()
    await expect(deleteButton).toBeDisabled()
    await expect(editButton).toHaveAttribute('title', /[Bb]uka kembali stasiun/)
    await expect(deleteButton).toHaveAttribute('title', /[Bb]uka kembali stasiun/)
    await expect(page.locator(`[data-testid="station-summary-${periodId}"]`)).toContainText('1 tertutup')

    // Buka kembali stasiunnya dan kedua aksi periode tersedia lagi — tidak
    // pernah ada yang mengunci periodenya sendiri.
    await reopenStationHere(page, stationId)
    await expect(editButton).toBeEnabled()
    await expect(deleteButton).toBeEnabled()
    await expect(editButton).not.toHaveAttribute('title', /[Bb]uka kembali stasiun/)
    await expect(page.locator('[data-testid="period-name"]')).toHaveText(name)
  })

  // Edit periode dari layar detail — form yang sama dengan daftar, tanpa
  // pemilih Jenis Stasiun, dan ringkasan halaman ikut ter-refresh.
  test('edit periode dari detail: nama berubah di ringkasan, tanpa kontrol status maupun jenis stasiun', async ({ page }) => {
    const { start, end } = uniqueRange(11)
    const name = uniqueName('Ubah')
    const renamed = `${name} Revisi`

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    const { periodId, stationId } = await createPeriodAndOpenDetail(page, { name, start, end })
    await openStation(page, periodId, stationId)

    await page.locator(`[data-testid="edit-button-${periodId}"]`).click()

    const modal = page.locator('.kcm-modal')
    await expect(modal).toBeVisible()
    // Tidak ada kontrol status apa pun — tidak ada jalan pulang ke Draft dari
    // UI — dan tidak ada kontrol jenis stasiun, karena cakupan periode
    // diturunkan dari mill-nya, bukan dipilih.
    await expect(modal.locator('#status')).toHaveCount(0)
    await expect(modal.locator('#station_type')).toHaveCount(0)
    await expect(modal).not.toContainText('Draft')
    await expect(modal).toContainText('tidak diubah maupun dihapus')

    await page.locator('#name').fill(renamed)
    await page.locator('[data-testid="save-button"]').click()

    await expect(page.locator('.kcm-modal')).toHaveCount(0)
    await expect(page.locator('[data-testid="success-message"]')).toBeVisible()
    await expect(page.locator('[data-testid="period-name"]')).toHaveText(renamed)
    // Menyimpan tidak menyentuh satu pun baris stasiun yang sudah ada.
    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Terbuka')

    await page.reload()
    await expect(page.locator('[data-testid="period-name"]')).toHaveText(renamed)
  })

  // Skenario yang mustahil sebelum layar ini ada: menghapus dari halaman yang
  // MEMBAHAS periode itu. Diamnya di tempat akan merender "tidak ditemukan"
  // untuk sesuatu yang baru saja sengaja dihapus Admin.
  test('hapus periode dari detail: redirect ke daftar dan barisnya benar-benar hilang', async ({ page }) => {
    const { start, end } = uniqueRange(12)
    const name = uniqueName('Hapus Redirect')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    const { periodId } = await createPeriodAndOpenDetail(page, { name, start, end })

    await page.locator(`[data-testid="delete-button-${periodId}"]`).click()
    await expect(page.locator('[data-testid="confirm-delete-button"]')).toBeVisible()
    await page.locator('[data-testid="confirm-delete-button"]').click()

    // Mendarat di DAFTAR, bukan di halaman "tidak ditemukan".
    await expect(page).toHaveURL(new RegExp(`${PERIODS_PATH}$`))
    await expect(page.locator('[data-testid="period-table"]')).toBeVisible()
    await expect(page.locator('[data-testid="period-not-found"]')).toHaveCount(0)
    await expect(periodRow(page, name)).toHaveCount(0)

    // Dan URL detailnya kini benar-benar kosong.
    await page.goto(periodDetailPath(periodId))
    await expect(page.locator('[data-testid="period-not-found"]')).toBeVisible()
  })

  // Hapus yang DITOLAK tetap di halaman ini. 404 (periode sudah dihapus
  // pengguna lain) adalah satu-satunya penolakan yang dapat diproduksi lewat
  // browser: penolakan 409 tidak dapat, karena tombol Hapus sudah mati lebih
  // dulu saat ada stasiun tertutup (diasersi di skenario "Terkunci" di atas).
  test('hapus periode yang sudah dihapus pengguna lain: tetap di detail dengan penjelasan, tanpa redirect dan tanpa 500', async ({ page, browser }) => {
    const { start, end } = uniqueRange(13)
    const name = uniqueName('Hapus Balapan')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    const { periodId } = await createPeriodAndOpenDetail(page, { name, start, end })

    // Admin 1 membuka konfirmasi hapus lebih dulu...
    await page.locator(`[data-testid="delete-button-${periodId}"]`).click()
    await expect(page.locator('[data-testid="confirm-delete-button"]')).toBeVisible()

    // ...lalu Admin 2, di konteks lain, menghapus periodenya dari daftar.
    const otherContext = await browser.newContext()
    const otherPage = await otherContext.newPage()
    await login(otherPage, SECOND_ADMIN, PASSWORD)
    await gotoPeriods(otherPage)
    await deletePeriodFromList(otherPage, name)
    await otherContext.close()

    await page.locator('[data-testid="confirm-delete-button"]').click()

    // Tetap di URL detail — tidak ada redirect diam-diam yang menyamar
    // sebagai keberhasilan — dengan penjelasan dan jalan kembali.
    await expect(page.locator('[data-testid="period-not-found"]')).toBeVisible()
    expect(new URL(page.url()).pathname).toBe(periodDetailPath(periodId))
    await expect(page.locator('body')).not.toContainText('500')

    await page.locator('[data-testid="back-to-periods-empty"]').click()
    await expect(page).toHaveURL(new RegExp(`${PERIODS_PATH}$`))
    await expect(periodRow(page, name)).toHaveCount(0)
  })

  // ── usecase-144: Buka Stasiun — PINDAH dari screen-128 ────────────────

  // Scenario 21: "Buka Periode Pelaporan — sukses"
  test('buka stasiun draft: dialog konfirmasi lalu badge stasiun jadi Terbuka dan tombol Buka Stasiun hilang', async ({ page }) => {
    const { start, end } = uniqueRange(14)
    const name = uniqueName('Buka Sukses')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    const { periodId, stationId } = await createPeriodAndOpenDetail(page, { name, start, end })

    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Draft')

    await page.locator(`[data-testid="station-open-button-${stationId}"]`).click()

    const dialog = page.locator('[data-testid="open-period-dialog"]')
    await expect(dialog).toBeVisible()
    await expect(dialog).toContainText('tidak dapat dikembalikan ke Draft')
    // Dialognya tentang SATU stasiun, dan menyebutnya.
    await expect(dialog).toContainText(STATION_TYPE)
    await expect(dialog).toContainText(name)

    await page.locator('[data-testid="confirm-open-period"]').click()

    // Dialog tertutup dan stasiunnya kini Terbuka.
    await expect(page.locator('[data-testid="open-period-dialog"]')).toHaveCount(0)
    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Terbuka')
    await expect(page.locator(`[data-testid="station-open-button-${stationId}"]`)).toHaveCount(0)
    // Membuka bukan menutup: kolom penutupan tetap kosong dan aksi periode
    // tetap tersedia.
    await expect(stationRow(page, stationId).locator('td').nth(2)).toHaveText('—')
    await expect(stationRow(page, stationId).locator('td').nth(3)).toHaveText('—')
    await expect(page.locator(`[data-testid="edit-button-${periodId}"]`)).toBeEnabled()
    await expect(page.locator(`[data-testid="delete-button-${periodId}"]`)).toBeEnabled()
    await expect(page.locator('[data-testid="success-message"]')).toBeVisible()
  })

  // Alternative flow "Admin membatalkan pembukaan".
  test('membatalkan dialog Buka Stasiun: badge tetap Draft dan tombol Buka Stasiun masih ada', async ({ page }) => {
    const { start, end } = uniqueRange(15)
    const name = uniqueName('Batal Buka')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    const { stationId } = await createPeriodAndOpenDetail(page, { name, start, end })

    await page.locator(`[data-testid="station-open-button-${stationId}"]`).click()
    await expect(page.locator('[data-testid="open-period-dialog"]')).toBeVisible()

    await page.locator('[data-testid="cancel-open-period"]').click()

    await expect(page.locator('[data-testid="open-period-dialog"]')).toHaveCount(0)
    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Draft')
    await expect(page.locator(`[data-testid="station-open-button-${stationId}"]`)).toBeVisible()
  })

  // Scenario 22: "Periode sudah terbuka"
  test('baris stasiun Terbuka: tombol Buka Stasiun tidak dirender sama sekali', async ({ page }) => {
    const { start, end } = uniqueRange(16)
    const name = uniqueName('Sudah Terbuka')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    const { periodId, stationId } = await createPeriodAndOpenDetail(page, { name, start, end })
    await openStation(page, periodId, stationId)

    // Masih tidak ada setelah reload penuh — tombolnya digerakkan status baris
    // stasiunnya, bukan keadaan di sisi klien.
    await page.reload()
    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Terbuka')
    await expect(page.locator(`[data-testid="station-open-button-${stationId}"]`)).toHaveCount(0)
    await expect(page.locator(`[data-testid="station-close-button-${stationId}"]`)).toBeVisible()
  })

  // Scenario 23: "Periode sudah tertutup"
  test('baris stasiun Tertutup: hanya Buka Kembali yang ditawarkan, bukan Buka Stasiun', async ({ page }) => {
    const { start, end } = uniqueRange(17)
    const name = uniqueName('Buka Tertutup')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    const { periodId, stationId } = await createPeriodAndOpenDetail(page, { name, start, end })

    await closeStationHere(page, periodId, stationId)

    // "Buka Stasiun" dan "Buka Kembali" adalah dua aksi berbeda; stasiun
    // tertutup hanya boleh menawarkan yang kedua.
    await expect(page.locator(`[data-testid="station-open-button-${stationId}"]`)).toHaveCount(0)
    await expect(page.locator(`[data-testid="station-reopen-button-${stationId}"]`)).toBeEnabled()
  })

  // Scenario 24: "Periode tidak ditemukan"
  //
  // BENTUK PENOLAKANNYA BERUBAH BERSAMA LAYARNYA, BUKAN HILANG. Dulu daftar
  // tetap ter-render dan pesan "tidak ditemukan" muncul sebagai alert inline.
  // Di layar yang MEMBAHAS satu periode, periode yang lenyap membuat seluruh
  // isinya tidak punya subjek: render berikutnya adalah halaman "Periode
  // tidak ditemukan" berikut jalan kembalinya — dan alert inline itu memang
  // tidak ikut dirender (blade menempatkannya di cabang @else). Yang diasersi
  // karena itu halaman penjelasannya, bukan alert yang secara struktural
  // tidak mungkin ada.
  test('periode dihapus pengguna lain: konfirmasi Buka Stasiun berujung halaman tidak ditemukan, tanpa error 500', async ({ page, browser }) => {
    const { start, end } = uniqueRange(18)
    const name = uniqueName('Buka Sudah Dihapus')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    const { periodId, stationId } = await createPeriodAndOpenDetail(page, { name, start, end })

    // Admin 1 membuka dialog konfirmasi LEBIH DULU, selagi baris stasiunnya
    // masih ada...
    await page.locator(`[data-testid="station-open-button-${stationId}"]`).click()
    await expect(page.locator('[data-testid="open-period-dialog"]')).toBeVisible()

    // ...lalu Admin 2, di konteks lain, menghapus seluruh periodenya — yang
    // membawa serta baris-baris stasiunnya.
    const otherContext = await browser.newContext()
    const otherPage = await otherContext.newPage()
    await login(otherPage, SECOND_ADMIN, PASSWORD)
    await gotoPeriods(otherPage)
    await deletePeriodFromList(otherPage, name)
    await otherContext.close()

    await page.locator('[data-testid="confirm-open-period"]').click()

    await expect(page.locator('[data-testid="period-not-found"]')).toBeVisible()
    await expect(page.locator('[data-testid="period-not-found"]')).toContainText(/tidak ditemukan/i)
    await expect(page.locator('body')).not.toContainText('500')
    // Tidak ada tabel stasiun yang tertinggal, dan tidak ada yang berubah
    // diam-diam.
    await expect(page.locator(`[data-testid="period-stations-${periodId}"]`)).toHaveCount(0)
    await expect(page.locator('[data-testid="success-message"]')).toHaveCount(0)

    await page.reload()
    await expect(page.locator('[data-testid="period-not-found"]')).toBeVisible()
  })

  // Scenario 25: "Dua Admin membuka bersamaan"
  test('dua Admin membuka stasiun yang sama: hanya yang pertama mengubah status, yang kedua diberi tahu sudah terbuka', async ({ page, browser }) => {
    const { start, end } = uniqueRange(19)
    const name = uniqueName('Balapan Buka')

    // Admin B menyiapkan barisnya dan membiarkan halaman detailnya (yang kini
    // basi) terbuka.
    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    const { periodId, stationId } = await createPeriodAndOpenDetail(page, { name, start, end })

    // Admin A membuka stasiun yang sama di konteks browser lain, lebih dulu.
    const otherContext = await browser.newContext()
    const otherPage = await otherContext.newPage()
    await login(otherPage, SECOND_ADMIN, PASSWORD)
    await gotoPeriodDetail(otherPage, periodId)
    await otherPage.locator(`[data-testid="station-open-button-${stationId}"]`).click()
    await otherPage.locator('[data-testid="confirm-open-period"]').click()
    await expect(otherPage.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Terbuka')
    await otherContext.close()

    // Admin B, tanpa reload, mengonfirmasi pembukaan yang sama.
    await page.locator(`[data-testid="station-open-button-${stationId}"]`).click()
    await page.locator('[data-testid="confirm-open-period"]').click()

    // Diberi tahu sudah terbuka — tidak ada perubahan kedua, tidak ada
    // penimpaan diam-diam.
    await expect(page.locator('[data-testid="close-error"]')).toContainText(/sudah terbuka/i)
    await expect(page.locator('[data-testid="success-message"]')).toHaveCount(0)
    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Terbuka')

    await page.reload()
    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Terbuka')
    await expect(page.locator(`[data-testid="station-open-button-${stationId}"]`)).toHaveCount(0)
  })

  // Scenario 26: "Bukan Admin mencoba membuka periode"
  test('akses ditolak: non-Admin tidak pernah melihat tombol Buka Stasiun dan status tetap Draft', async ({ page, browser }) => {
    const { start, end } = uniqueRange(20)
    const name = uniqueName('Buka Non Admin')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    const { periodId, stationId } = await createPeriodAndOpenDetail(page, { name, start, end })

    const otherContext = await browser.newContext()
    const otherPage = await otherContext.newPage()
    await login(otherPage, NON_ADMIN, PASSWORD)
    await otherPage.goto(periodDetailPath(periodId))
    await expect(otherPage.locator('body')).toContainText(/403/)
    await expect(otherPage.locator('[data-testid^="station-open-button-"]')).toHaveCount(0)
    await otherContext.close()

    // Diperiksa ulang sebagai Admin: stasiunnya persis seperti semula.
    await page.reload()
    await expect(page.locator(`[data-testid="station-status-badge-${stationId}"]`)).toHaveText('Draft')
    await expect(page.locator(`[data-testid="station-open-button-${stationId}"]`)).toBeVisible()
  })

  // ── Keadaan tepi yang hanya ada sejak layar ini punya URL sendiri ─────

  test('periode tidak ditemukan: penjelasan dan jalan kembali, bukan halaman kosong dan bukan 500', async ({ page }) => {
    await login(page, ADMIN, PASSWORD)
    await page.goto(periodDetailPath(MISSING_PERIOD_ID))

    const notFound = page.locator('[data-testid="period-not-found"]')
    await expect(notFound).toBeVisible()
    await expect(notFound).toContainText(/tidak ditemukan/i)
    await expect(page.locator('body')).not.toContainText('500')
    // Tidak ada ringkasan, tidak ada tabel stasiun, tidak ada aksi yang
    // menunggu subjek yang tidak ada.
    await expect(page.locator('[data-testid="period-summary"]')).toHaveCount(0)
    await expect(page.locator('[data-testid^="period-stations-"]')).toHaveCount(0)
    await expect(page.locator('[data-testid^="station-close-button-"]')).toHaveCount(0)

    await page.locator('[data-testid="back-to-periods-empty"]').click()
    await expect(page).toHaveURL(new RegExp(`${PERIODS_PATH}$`))
    await expect(page.locator('[data-testid="period-table"]')).toBeVisible()
  })

  // status_summary 'empty' — mill tanpa satu pun stasiun aktif. Di layar
  // detail keadaan ini harus MENJELASKAN dirinya, bukan menampilkan tabel
  // kosong yang harus ditafsirkan Admin sendiri.
  test('mill tanpa stasiun aktif: penjelasan beserta cara memperbaikinya, bukan tabel stasiun kosong', async ({ page }) => {
    const { start, end } = emptyMillRange()
    const name = uniqueName('Tanpa Stasiun')

    await login(page, ADMIN, PASSWORD)
    await gotoPeriods(page)
    await createPeriod(page, { name, start, end, businessUnit: EMPTY_MILL })

    const periodId = await periodIdFor(page, name)
    await gotoPeriodDetail(page, periodId)

    await expect(page.locator('[data-testid="period-business-unit"]')).toHaveText(EMPTY_MILL)
    await expect(page.locator(`[data-testid="status-summary-${periodId}"]`)).toHaveText('Tanpa Stasiun')
    await expect(page.locator(`[data-testid="station-summary-${periodId}"]`)).toHaveText('Belum ada stasiun')

    // BUKAN TABEL KOSONG: sebuah penjelasan, plus langkah konkret untuk
    // memperbaikinya.
    const notice = page.locator(`[data-testid="period-stations-empty-${periodId}"]`)
    await expect(notice).toBeVisible()
    await expect(notice).toContainText('belum memiliki stasiun aktif')
    await expect(notice).toContainText('tetap dapat diubah maupun dihapus')
    await expect(page.locator(`[data-testid="period-stations-${periodId}"]`)).toHaveCount(0)
    await expect(page.locator('[data-testid^="station-status-badge-"]')).toHaveCount(0)

    // Tidak ada yang bisa ditutup, jadi tidak ada yang immutable: kedua aksi
    // periode tetap hidup.
    await expect(page.locator(`[data-testid="edit-button-${periodId}"]`)).toBeEnabled()
    await expect(page.locator(`[data-testid="delete-button-${periodId}"]`)).toBeEnabled()

    // Dibersihkan di dalam test: periode ini hidup di MILL LAIN, dan
    // meninggalkannya berarti menduduki ruang tanggal yang dipakai spec lain
    // di mill yang sama.
    await deletePeriodFromDetail(page, periodId, name)
  })
})
