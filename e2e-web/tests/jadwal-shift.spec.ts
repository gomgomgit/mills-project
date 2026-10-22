/**
 * Jadwal Shift (Browser/Playwright) — screen-171--jadwal-shift /
 * usecase-177--jadwal-shift.
 *
 * Satu test per entri `test_scenarios` yang punya `browser_test` (27 + 10 entri
 * spec v2 = skenario 28-37, plus skenario 38 untuk kegagalan localStorage),
 * bernomor menurut urutannya di spec ("skenario N"), ditambah pemeriksaan
 * sidebar dan tautan silang dari Kelola Shift.
 *
 * ── PRASYARAT ───────────────────────────────────────────────────────────
 *
 * Sama dengan kelola-shift.spec.ts: database e2e sudah dimigrasi (termasuk
 * 2026_10_22_000002_create_shift_assignments_table) dan di-seed
 * (`npm run db:prepare`), server e2e berjalan (`npm run serve`). Spec ini
 * TIDAK dijalankan oleh penulisnya; hanya `--list` yang diverifikasi.
 *
 * ── FIXTURE ─────────────────────────────────────────────────────────────
 *
 *   - butest-admin01 (Admin), butest-nonadmin01 (Supervisor),
 *     stest-millmgmt01 (Mill Management), operator-a2 (Operator)
 *   - "BU Browser Test" dengan line 'PL Mill A' (dipakai), 'PL Mill Kecil'
 *     (dibiarkan tanpa shift); "Mill Kosong" tanpa line;
 *     "Mill Station Icon" dengan line 'PL Station Icon' tanpa user sama sekali
 *     (skenario 11).
 *   - Calon peserta: webtest-operator01 (Operator) dan butest-supervisor01
 *     (Supervisor) pada BU Browser Test.
 *   - User bantu `jdtest-engineer01` dan `jdtest-nonaktif01` dibuat lewat API
 *     (lihat ./support/shift-schedules.ts).
 *
 * ── DATA DAN KEBERSIHANNYA ──────────────────────────────────────────────
 *
 * Shift disapu (soft delete) sebelum tiap test lewat clearShifts(), dan tiap
 * test menanam shift BARU, sehingga penugasan sisa run lama menunjuk ke shift
 * yang sudah tertutup dan tidak pernah muncul di grid test berikutnya.
 * SATU PENGECUALIAN YANG DISENGAJA: salin-dari-bulan-sebelumnya membaca juga
 * shift tertutup (dihitung `closed_shift`), jadi skenario 19 dan 20 memakai
 * TAHUN ACAK (2200-2299) agar residu run lama tidak mengubah hitungannya.
 * Bulan lain memakai 2027-03 (masa depan, tak bergantung hari ini).
 *
 * Skenario 14 dan 26 menutup shift sungguhan lewat DELETE /api/shifts/{id};
 * `closed_from` adalah TANGGAL HARI INI menurut zona waktu aplikasi, jadi
 * bulan yang dibuka adalah bulan berjalan dan dibaca dari API, bukan
 * ditebak. Bila hari ini tanggal 1, shift tertutup tidak tampil di bulan itu
 * sama sekali (aturan yang diuji di unit test) dan kedua test di-skip.
 *
 * ── ASERSI ──────────────────────────────────────────────────────────────
 *
 * Selalu atas ELEMEN (data-testid / sel td), tidak pernah pemindaian teks
 * halaman: 'belum ada petugas' muncul juga di baris ringkasan. Penghitungan
 * memakai `toHaveCount` (menunggu), bukan `count()`. URL diperiksa dengan
 * waitForURL dengan fungsi pencocok, bukan toHaveURL dengan glob.
 */

import { test, expect, type Locator, type Page } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { selectLive, fillAndKeep } from './support/livewire'
import { watchProblems } from './support/page-health'
import {
  businessUnitIdByName,
  clearShifts,
  productionLineIdByName,
  seedShift,
} from './support/shifts'
import {
  candidatesOf,
  cellOf,
  changeShiftHours,
  closeShift,
  ensureHelperUser,
  gridOf,
  putCell,
  rawGet,
  seedCell,
  seededUser,
  setUserActive,
} from './support/shift-schedules'

const PATH = '/master-data/shift-schedules'

const ADMIN = 'butest-admin01'
const SUPERVISOR = 'butest-nonadmin01'
const MILL_MANAGEMENT = 'stest-millmgmt01'
const OPERATOR = 'operator-a2'

const BUSINESS_UNIT = 'BU Browser Test'
const EMPTY_MILL = 'Mill Kosong'
const NO_USER_MILL = 'Mill Station Icon'
const NO_USER_LINE = 'PL Station Icon'

const LINE_A = 'PL Mill A'
const LINE_C = 'PL Mill Kecil'
const LINES = [LINE_A, LINE_C]

/** Calon peserta tetap pada BU Browser Test. */
const PERSON_1 = { username: 'webtest-operator01', role: 'operator' }
const PERSON_2 = { username: 'butest-supervisor01', role: 'supervisor' }

const MONTH = '2027-03'

interface Arranged {
  businessUnitId: string
  lineId: string
  shifts: Record<string, string>
  u1: { id: string; name: string }
  u2: { id: string; name: string }
}

type ShiftSpec = { name: string; start: string; end: string }
const PAGI: ShiftSpec = { name: 'Pagi', start: '07:00', end: '15:00' }
const SIANG: ShiftSpec = { name: 'Siang', start: '15:00', end: '23:00' }
const MALAM: ShiftSpec = { name: 'Malam', start: '23:00', end: '07:00' }

/** Login admin, sapu shift, tanam shift yang diminta; kembalikan id-id yang dibutuhkan. */
async function arrange(page: Page, shifts: ShiftSpec[]): Promise<Arranged> {
  await login(page, ADMIN, PASSWORD)

  const businessUnitId = await businessUnitIdByName(page, BUSINESS_UNIT)
  await clearShifts(page, businessUnitId, LINES)

  const lineId = await productionLineIdByName(page, businessUnitId, LINE_A)
  const ids: Record<string, string> = {}

  for (const shift of shifts) {
    ids[shift.name] = await seedShift(page, { productionLineId: lineId, ...shift })
  }

  const p1 = await seededUser(page, PERSON_1.username, PERSON_1.role, businessUnitId)
  const p2 = await seededUser(page, PERSON_2.username, PERSON_2.role, businessUnitId)

  return {
    businessUnitId,
    lineId,
    shifts: ids,
    u1: { id: p1.id, name: p1.name },
    u2: { id: p2.id, name: p2.name },
  }
}

/**
 * Spec v2: tampilan bawaan adalah KALENDER. Skenario 1-27 menginspeksi sel
 * tabel, jadi gotoGrid membuka halaman lalu berpindah ke tampilan Tabel
 * (berpindah tampilan tidak menulis apa pun). Skenario 28 dst memakai
 * gotoCalendar.
 */
async function showTable(page: Page): Promise<void> {
  await expect(page.locator('[data-testid="jd-view-switch"]')).toBeVisible()
  await page.locator('[data-testid="jd-view-table"]').click()
  await expect(page.locator('[data-testid="schedule-grid"]')).toBeVisible()
}

async function gotoGrid(page: Page, a: Pick<Arranged, 'businessUnitId' | 'lineId'>, month: string = MONTH): Promise<void> {
  await page.goto(`${PATH}?businessUnitId=${a.businessUnitId}&productionLineId=${a.lineId}&month=${month}`)
  await showTable(page)
}

async function gotoCalendar(page: Page, a: Pick<Arranged, 'businessUnitId' | 'lineId'>, month: string = MONTH): Promise<void> {
  await page.goto(`${PATH}?businessUnitId=${a.businessUnitId}&productionLineId=${a.lineId}&month=${month}`)
  await expect(page.locator('[data-testid="jd-calendar"]')).toBeVisible()
}

function cell(page: Page, shiftId: string, date: string): Locator {
  return page.locator(`td[data-testid="cell-${shiftId}-${date}"]`)
}

function chips(page: Page, shiftId: string, date: string): Locator {
  return cell(page, shiftId, date).locator('.jd-chip')
}

function emptyText(page: Page, shiftId: string, date: string): Locator {
  return cell(page, shiftId, date).locator('.jd-empty-cell')
}

/** Spec v2: mengeklik sel tabel membuka kartu tanggal (tidak ada lagi tombol "Atur"). */
async function openCell(page: Page, shiftId: string, date: string): Promise<void> {
  await page.locator(`[data-testid="jd-cell-btn-${shiftId}-${date}"]`).click()
  await expect(page.locator('[data-testid="jd-day-card"]')).toHaveAttribute('data-date', date)
  await expect(page.locator(`[data-testid="jd-card-save-${shiftId}"]`)).toBeVisible()
}

/** Menyimpan shift yang sedang dibuka pada kartu (hanya SATU bagian shift yang punya tombol simpan). */
async function savePanel(page: Page): Promise<void> {
  await Promise.all([
    page.waitForResponse((response) => response.url().includes('/livewire/update')),
    page.locator('[data-testid^="jd-card-save-"]').click(),
  ])
}

/** Pesan sukses simpan pada bagian shift di kartu. */
function cardNotice(page: Page): Locator {
  return page.locator('[data-testid^="jd-card-notice-"]')
}

async function setPerson(page: Page, userId: string, checked: boolean): Promise<void> {
  const box = page.locator(`[data-testid="candidate-${userId}"]`)
  await expect(box).toBeVisible()
  await box.setChecked(checked)
}

async function pageProblems(page: Page): Promise<void> {
  // Tidak ada scroll horizontal pada dokumen: wrapper grid yang menggulir, bukan halaman.
  const metrics = await page.evaluate(() => ({
    scrollWidth: document.scrollingElement?.scrollWidth ?? 0,
    clientWidth: document.scrollingElement?.clientWidth ?? 0,
  }))
  expect(metrics.scrollWidth, `halaman menggulir mendatar: ${metrics.scrollWidth} > ${metrics.clientWidth}`).toBeLessThanOrEqual(metrics.clientWidth + 1)
}

/** Tanggal hari ini menurut zona waktu aplikasi diambil dari closed_from yang dilaporkan API. */
async function closeShiftToday(page: Page, a: Arranged, shiftName: string): Promise<{ month: string; closedFrom: string } | null> {
  await closeShift(page, a.shifts[shiftName])
  // Bulan berjalan menurut zona waktu aplikasi; dicoba dua kandidat agar tak bergantung zona mesin penguji.
  const candidates = [
    new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta' }).format(new Date()).slice(0, 7),
  ]

  for (const month of candidates) {
    const grid = await gridOf(page, a.lineId, month)
    const column = grid.shifts.find((shift) => shift.id === a.shifts[shiftName])

    if (column && column.closed_from && column.closed_from.startsWith(month)) {
      return { month, closedFrom: column.closed_from }
    }
  }

  return null
}

test.describe('Jadwal Shift', () => {
  test.afterAll(async ({ browser }) => {
    const page = await browser.newPage()

    try {
      await login(page, ADMIN, PASSWORD)
      const businessUnitId = await businessUnitIdByName(page, BUSINESS_UNIT)
      await clearShifts(page, businessUnitId, LINES)
      const quietMill = await businessUnitIdByName(page, NO_USER_MILL)
      await clearShifts(page, quietMill, [NO_USER_LINE])
    } catch (error) {
      console.warn('[cleanup] jadwal-shift: pembersihan gagal:', error)
    } finally {
      await page.close()
    }
  })

  // ── Skenario 1 ────────────────────────────────────────────────────────

  test('skenario 1 — success: pilih mill dan line, buka sel, centang petugas, simpan', async ({ page }) => {
    const problems = watchProblems(page)
    const a = await arrange(page, [PAGI])

    await page.goto(`${PATH}?month=${MONTH}`)
    await selectLive(page, 'mill-select', { label: BUSINESS_UNIT })
    await selectLive(page, 'line-select', { label: LINE_A })
    await expect(page.locator('[data-testid="jd-calendar"]')).toBeVisible()      // bawaan: kalender
    await showTable(page)
    await expect(page.locator('[data-testid="summary-text"]')).toHaveText('0 dari 31 sel sudah terisi')

    await openCell(page, a.shifts.Pagi, `${MONTH}-05`)
    await setPerson(page, a.u1.id, true)
    await savePanel(page)

    await expect(cardNotice(page)).toHaveText('Petugas sel berhasil disimpan.')
    await expect(chips(page, a.shifts.Pagi, `${MONTH}-05`)).toHaveText([a.u1.name])
    await expect(page.locator('[data-testid="summary-text"]')).toHaveText('1 dari 31 sel sudah terisi')
    await expect(emptyText(page, a.shifts.Pagi, `${MONTH}-05`)).toHaveCount(0)
    await expect(emptyText(page, a.shifts.Pagi, `${MONTH}-06`)).toHaveText('belum ada petugas')

    await pageProblems(page)
    expect(problems).toEqual([])
  })

  // ── Skenario 2 ────────────────────────────────────────────────────────

  test('skenario 2 — Mill belum dipilih: tanpa grid, arahan pilih mill', async ({ page }) => {
    const problems = watchProblems(page)
    await arrange(page, [])

    await page.goto(`${PATH}?month=${MONTH}`)

    await expect(page.locator('[data-testid="pick-mill-guide"]')).toBeVisible()
    await expect(page.locator('[data-testid="mill-select"]')).toHaveValue('')
    await expect(page.locator('[data-testid="schedule-grid"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="line-select"] option')).toHaveCount(1)

    expect(problems).toEqual([])
  })

  // ── Skenario 3 ────────────────────────────────────────────────────────

  test('skenario 3 — Production Line belum dipilih: tanpa grid dan tanpa opsi "semua line"', async ({ page }) => {
    const problems = watchProblems(page)
    await arrange(page, [PAGI])

    await page.goto(`${PATH}?month=${MONTH}`)
    await selectLive(page, 'mill-select', { label: BUSINESS_UNIT })

    await expect(page.locator('[data-testid="pick-line-guide"]')).toBeVisible()
    await expect(page.locator('[data-testid="schedule-grid"]')).toHaveCount(0)

    const labels = await page.locator('[data-testid="line-select"] option').allTextContents()
    expect(labels.length).toBeGreaterThan(1)
    expect(labels[0].trim()).toBe('-- Pilih Production Line --')
    expect(labels.filter((label) => /semua|all/i.test(label))).toEqual([])
    expect(labels.map((label) => label.trim())).toContain(LINE_A)

    expect(problems).toEqual([])
  })

  // ── Skenario 4 ────────────────────────────────────────────────────────

  test('skenario 4 — Mill belum punya Production Line: pesan dan tautan Kelola Production Line', async ({ page }) => {
    const problems = watchProblems(page)
    await arrange(page, [])

    await page.goto(`${PATH}?month=${MONTH}`)
    await selectLive(page, 'mill-select', { label: EMPTY_MILL })

    await expect(page.locator('[data-testid="no-production-line-guide"]')).toBeVisible()
    await expect(page.locator('[data-testid="production-line-link"]')).toHaveText('Kelola Production Line')
    await expect(page.locator('[data-testid="production-line-link"]')).toHaveAttribute('href', /\/master-data\/production-lines$/)
    await expect(page.locator('[data-testid="schedule-grid"]')).toHaveCount(0)

    expect(problems).toEqual([])
  })

  // ── Skenario 5 ────────────────────────────────────────────────────────

  test('skenario 5 — Line belum punya shift hidup: pesan dan tautan Kelola Shift, tanpa tabel', async ({ page }) => {
    const problems = watchProblems(page)
    const a = await arrange(page, [])

    await page.goto(`${PATH}?businessUnitId=${a.businessUnitId}&month=${MONTH}`)
    await selectLive(page, 'line-select', { label: LINE_A })

    await expect(page.locator('[data-testid="no-live-shift-guide"]')).toBeVisible()
    await expect(page.locator('[data-testid="kelola-shift-link"]')).toHaveAttribute('href', /\/master-data\/shifts$/)
    await expect(page.locator('[data-testid="schedule-grid"]')).toHaveCount(0)

    expect(problems).toEqual([])
  })

  // ── Skenario 6 ────────────────────────────────────────────────────────

  for (const [label, username] of [['Supervisor', SUPERVISOR], ['Mill Management', MILL_MANAGEMENT]] as const) {
    test(`skenario 6 — ${label} mencoba mill lain: pemilih mill tidak ada, mill lain ditolak 403`, async ({ page }) => {
      const problems = watchProblems(page, { allowStatus: [403] })

      await login(page, ADMIN, PASSWORD)
      const otherMillId = await businessUnitIdByName(page, EMPTY_MILL)
      await page.context().clearCookies()

      await login(page, username, PASSWORD)
      await page.goto(`${PATH}?businessUnitId=${otherMillId}&month=${MONTH}`)

      await expect(page.locator('[data-testid="mill-fixed"]')).toHaveText(BUSINESS_UNIT)
      await expect(page.locator('[data-testid="mill-select"]')).toHaveCount(0)
      await expect(page.locator('[data-testid="no-production-line-guide"]')).toHaveCount(0)   // mill lain tidak termuat
      await expect(page.locator('[data-testid="pick-line-guide"]')).toBeVisible()

      const direct = await rawGet(page, `/api/shift-schedules/production-lines/options?business_unit_id=${otherMillId}`)
      expect(direct.status).toBe(403)
      expect(direct.body.code).toBe('FORBIDDEN')

      expect(problems.filter((p) => !p.includes('403'))).toEqual([])
    })
  }

  // ── Skenario 7 ────────────────────────────────────────────────────────

  test('skenario 7 — Operator: halaman 403 dan menu "Jadwal Shift" tidak ada di sidebar', async ({ page }) => {
    const problems = watchProblems(page, { allowStatus: [403] })

    await login(page, OPERATOR, PASSWORD)
    const sidebar = page.locator('aside.shell-sidebar')
    await expect(sidebar).toBeVisible()
    await expect(sidebar.locator('a[href$="/master-data/shift-schedules"]')).toHaveCount(0)

    const response = await page.goto(PATH)
    expect(response?.status()).toBe(403)
    await expect(page.getByText('Akses Ditolak')).toBeVisible()
    await expect(page.locator('[data-testid="schedule-grid"]')).toHaveCount(0)
    await expect(page.locator('aside.shell-sidebar a[href$="/master-data/shift-schedules"]')).toHaveCount(0)

    expect(problems.filter((p) => !p.includes('403'))).toEqual([])
  })

  for (const [label, username] of [['Admin', ADMIN], ['Supervisor', SUPERVISOR], ['Mill Management', MILL_MANAGEMENT]] as const) {
    test(`sidebar — ${label} melihat menu "Jadwal Shift" yang membuka layar ini`, async ({ page }) => {
      await login(page, username, PASSWORD)
      await page.goto('/dashboard')

      const link = page.locator('aside.shell-sidebar a[href$="/master-data/shift-schedules"]')
      await expect(link).toHaveCount(1)
      await link.click()
      await page.waitForURL((url) => url.pathname === PATH)
      await expect(page.locator('.jd-page')).toBeVisible()
    })
  }

  test('tautan silang — Kelola Shift memuat tautan ke Jadwal Shift yang membawa mill dan line', async ({ page }) => {
    const a = await arrange(page, [PAGI])

    await page.goto('/master-data/shifts')
    await selectLive(page, 'mill-select', { label: BUSINESS_UNIT })
    const link = page.locator('[data-testid="open-jadwal-shift"]')
    await expect(link).toHaveCount(1)
    await expect(link).toHaveAttribute('href', new RegExp(`/master-data/shift-schedules\\?.*businessUnitId=${a.businessUnitId}`))
    await link.click()
    await page.waitForURL((url) => url.pathname === PATH)
    await expect(page.locator('[data-testid="mill-select"]')).toHaveValue(a.businessUnitId)
  })

  // ── Skenario 8 ────────────────────────────────────────────────────────

  test('skenario 8 — satu orang di beberapa shift pada hari yang sama: tersimpan, penanda shift lain terlihat', async ({ page }) => {
    const problems = watchProblems(page)
    const a = await arrange(page, [PAGI, SIANG])
    const date = `${MONTH}-05`
    await gotoGrid(page, a)

    await openCell(page, a.shifts.Pagi, date)
    await setPerson(page, a.u1.id, true)
    await savePanel(page)
    await expect(chips(page, a.shifts.Pagi, date)).toHaveText([a.u1.name])

    await openCell(page, a.shifts.Siang, date)
    await expect(page.locator(`[data-testid="also-other-${a.u1.id}"]`)).toHaveText('juga di shift Pagi hari ini')
    await setPerson(page, a.u1.id, true)
    await savePanel(page)

    await expect(page.locator('[data-testid="panel-error"]')).toHaveCount(0)
    await expect(chips(page, a.shifts.Siang, date)).toHaveText([a.u1.name])
    await expect(chips(page, a.shifts.Pagi, date)).toHaveText([a.u1.name])

    expect(problems).toEqual([])
  })

  // ── Skenario 9 ────────────────────────────────────────────────────────

  test('skenario 9 — sel berisi satu petugas disimpan tanpa perubahan: tetap satu petugas, tanpa galat', async ({ page }) => {
    const problems = watchProblems(page)
    const a = await arrange(page, [PAGI])
    const date = `${MONTH}-05`
    await seedCell(page, a.shifts.Pagi, date, [a.u1.id])
    await gotoGrid(page, a)

    await openCell(page, a.shifts.Pagi, date)
    await savePanel(page)

    await expect(cardNotice(page)).toHaveText('Petugas sel berhasil disimpan.')
    await expect(page.locator('[data-testid="panel-error"]')).toHaveCount(0)
    await expect(chips(page, a.shifts.Pagi, date)).toHaveText([a.u1.name])

    expect(problems).toEqual([])
  })

  // ── Skenario 10 ───────────────────────────────────────────────────────

  test('skenario 10 — petugas dikeluarkan semua: sel "belum ada petugas" dan ringkasan diperbarui', async ({ page }) => {
    const problems = watchProblems(page)
    const a = await arrange(page, [PAGI])
    const date = `${MONTH}-05`
    await seedCell(page, a.shifts.Pagi, date, [a.u1.id])
    await gotoGrid(page, a)
    await expect(page.locator('[data-testid="summary-text"]')).toHaveText('1 dari 31 sel sudah terisi')

    await openCell(page, a.shifts.Pagi, date)
    await setPerson(page, a.u1.id, false)
    await savePanel(page)

    await expect(emptyText(page, a.shifts.Pagi, date)).toHaveText('belum ada petugas')
    await expect(chips(page, a.shifts.Pagi, date)).toHaveCount(0)
    await expect(page.locator('[data-testid="summary-text"]')).toHaveText('0 dari 31 sel sudah terisi')

    expect(problems).toEqual([])
  })

  // ── Skenario 11 ───────────────────────────────────────────────────────

  test('skenario 11 — Mill tanpa calon peserta: panel menyatakan tidak ada calon peserta', async ({ page }) => {
    const problems = watchProblems(page)
    await login(page, ADMIN, PASSWORD)
    const millId = await businessUnitIdByName(page, NO_USER_MILL)
    await clearShifts(page, millId, [NO_USER_LINE])
    const lineId = await productionLineIdByName(page, millId, NO_USER_LINE)
    const shiftId = await seedShift(page, { productionLineId: lineId, ...PAGI })

    // Prasyarat fixture, diperiksa lewat API: mill ini memang tanpa calon.
    const candidates = await candidatesOf(page, lineId)
    expect(candidates.data, `fixture ${NO_USER_MILL} seharusnya tanpa user Operator/Supervisor aktif`).toEqual([])
    expect(candidates.empty_reason).toBe('no_candidates')

    await gotoGrid(page, { businessUnitId: millId, lineId })
    await openCell(page, shiftId, `${MONTH}-05`)

    await expect(page.locator('[data-testid="no-candidates"]')).toContainText('tidak ada calon peserta')
    await expect(page.locator('[data-testid="panel-list"] input[type="checkbox"]')).toHaveCount(0)

    expect(problems).toEqual([])
  })

  // ── Skenario 12 ───────────────────────────────────────────────────────

  test('skenario 12 — Engineer, Mill Management, dan Admin tidak muncul sebagai calon', async ({ page }) => {
    const problems = watchProblems(page)
    const a = await arrange(page, [PAGI])
    const engineer = await ensureHelperUser(page, { username: 'jdtest-engineer01', name: 'Jdtest Engineer', role: 'engineer', businessUnitId: a.businessUnitId })
    const mm = await seededUser(page, MILL_MANAGEMENT, 'mill_management', a.businessUnitId)
    const admin = await seededUser(page, ADMIN, 'admin', a.businessUnitId)

    await gotoGrid(page, a)
    await openCell(page, a.shifts.Pagi, `${MONTH}-05`)

    await expect(page.locator(`[data-testid="candidate-${a.u1.id}"]`)).toBeVisible()
    await expect(page.locator(`[data-testid="candidate-${a.u2.id}"]`)).toBeVisible()
    for (const person of [engineer, mm, admin]) {
      await expect(page.locator(`[data-testid="candidate-${person.id}"]`), `${person.username} tidak boleh jadi calon`).toHaveCount(0)
    }

    expect(problems).toEqual([])
  })

  // ── Skenario 13 ───────────────────────────────────────────────────────

  test('skenario 13 — shift lintas tengah malam: petugas hanya di tanggal mulai, header memuat +1', async ({ page }) => {
    const problems = watchProblems(page)
    const a = await arrange(page, [MALAM])
    await gotoGrid(page, a)

    await expect(page.locator(`[data-testid="shift-head-${a.shifts.Malam}"]`)).toContainText('+1')

    await openCell(page, a.shifts.Malam, `${MONTH}-05`)
    await setPerson(page, a.u1.id, true)
    await savePanel(page)

    await expect(chips(page, a.shifts.Malam, `${MONTH}-05`)).toHaveText([a.u1.name])
    await expect(chips(page, a.shifts.Malam, `${MONTH}-06`)).toHaveCount(0)
    await expect(emptyText(page, a.shifts.Malam, `${MONTH}-06`)).toHaveText('belum ada petugas')

    expect(problems).toEqual([])
  })

  // ── Skenario 14 ───────────────────────────────────────────────────────

  test('skenario 14 — shift dihapus pertengahan bulan: kolom read-only, tanpa sel sesudah penutupan', async ({ page }) => {
    const problems = watchProblems(page)
    const a = await arrange(page, [PAGI, SIANG])
    const month = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta' }).format(new Date()).slice(0, 7)
    const history = `${month}-01`

    await seedCell(page, a.shifts.Siang, history, [a.u1.id])
    const closed = await closeShiftToday(page, a, 'Siang')

    test.skip(closed === null || closed.closedFrom === history, 'hari ini tanggal 1: shift tertutup tidak tampil di bulan ini (dibuktikan di unit test)')
    const { closedFrom } = closed!

    await gotoGrid(page, a, month)

    await expect(page.locator(`[data-testid="closed-badge-${a.shifts.Siang}"]`)).toHaveText('shift sudah dihapus')
    await expect(chips(page, a.shifts.Siang, history)).toHaveText([a.u1.name])
    await expect(cell(page, a.shifts.Siang, history)).toHaveClass(/jd-readonly/)

    // Mengeklik sel riwayat membuka kartu dalam mode histori: tanpa checkbox, tanpa tombol simpan untuk shift itu.
    await page.locator(`[data-testid="jd-cell-btn-${a.shifts.Siang}-${history}"]`).click()
    await expect(page.locator(`[data-testid="jd-card-history-${a.shifts.Siang}"]`)).toBeVisible()
    await expect(page.locator(`[data-testid="jd-card-save-${a.shifts.Siang}"]`)).toHaveCount(0)
    await expect(page.locator(`[data-testid="jd-card-shift-${a.shifts.Siang}"] input`)).toHaveCount(0)

    // Pada/sesudah penutupan: sel pengganti tanpa tombol sama sekali; shift hidup tetap bisa diklik.
    await expect(cell(page, a.shifts.Siang, closedFrom)).toHaveClass(/jd-closed/)
    await expect(cell(page, a.shifts.Siang, closedFrom)).toContainText('shift sudah dihapus')
    await expect(page.locator(`[data-testid="jd-cell-btn-${a.shifts.Siang}-${closedFrom}"]`)).toHaveCount(0)
    await expect(page.locator(`[data-testid="jd-cell-btn-${a.shifts.Pagi}-${closedFrom}"]`)).toBeVisible()

    expect(problems).toEqual([])
  })

  // ── Skenario 15 ───────────────────────────────────────────────────────

  test('skenario 15 — jam shift diubah setelah bulan diisi: penugasan tetap, header memakai jam baru', async ({ page }) => {
    const problems = watchProblems(page)
    const a = await arrange(page, [PAGI])
    const date = `${MONTH}-05`
    await seedCell(page, a.shifts.Pagi, date, [a.u1.id])

    await changeShiftHours(page, { id: a.shifts.Pagi, productionLineId: a.lineId, name: 'Pagi' }, '08:00', '16:00')
    await gotoGrid(page, a)

    const head = page.locator(`[data-testid="shift-head-${a.shifts.Pagi}"]`)
    await expect(head).toContainText('08:00')
    await expect(head).toContainText('16:00')
    await expect(head).not.toContainText('07:00')
    await expect(chips(page, a.shifts.Pagi, date)).toHaveText([a.u1.name])

    expect(problems).toEqual([])
  })

  // ── Skenario 16 ───────────────────────────────────────────────────────

  test('skenario 16 — user dinonaktifkan setelah ditugaskan: tampil "nonaktif", tak ditawarkan di sel lain, bisa dikeluarkan', async ({ page }) => {
    const problems = watchProblems(page)
    const a = await arrange(page, [PAGI])
    const gone = await ensureHelperUser(page, { username: 'jdtest-nonaktif01', name: 'Jdtest Nonaktif', role: 'operator', businessUnitId: a.businessUnitId })
    const date = `${MONTH}-05`
    await seedCell(page, a.shifts.Pagi, date, [gone.id])
    await setUserActive(page, gone.id, false)
    await gotoGrid(page, a)

    await expect(chips(page, a.shifts.Pagi, date).locator('.badge')).toHaveText(['nonaktif'])

    // Sel lain: tidak ditawarkan.
    await openCell(page, a.shifts.Pagi, `${MONTH}-06`)
    await expect(page.locator(`[data-testid="candidate-${a.u1.id}"]`)).toBeVisible()
    await expect(page.locator(`[data-testid="candidate-${gone.id}"]`)).toHaveCount(0)

    // Sel semula: tampil agar bisa dikeluarkan.
    await openCell(page, a.shifts.Pagi, date)
    await setPerson(page, gone.id, false)
    await savePanel(page)

    await expect(page.locator('[data-testid="panel-error"]')).toHaveCount(0)
    await expect(emptyText(page, a.shifts.Pagi, date)).toHaveText('belum ada petugas')

    expect(problems).toEqual([])
  })

  // ── Skenario 17 ───────────────────────────────────────────────────────

  test('skenario 17 — Terapkan ke beberapa tanggal: pratinjau, konfirmasi inline, hanya bulan terbuka terisi', async ({ page }) => {
    const problems = watchProblems(page)
    const a = await arrange(page, [PAGI])
    await seedCell(page, a.shifts.Pagi, `${MONTH}-01`, [a.u2.id])
    await gotoGrid(page, a)

    await page.locator('[data-testid="open-apply-button"]').click()
    await expect(page.locator('[data-testid="apply-card"]')).toBeVisible()
    await selectLive(page, 'apply-shift', { value: a.shifts.Pagi })
    await page.locator('[data-testid="apply-mode-dates"]').check()

    for (const date of [`${MONTH}-01`, `${MONTH}-02`, '2027-04-01']) {
      await fillAndKeep(page, 'apply-date-add', date)
      await page.locator('[data-testid="apply-date-add-button"]').click()
      await expect(page.locator('[data-testid="apply-dates"]')).toContainText(date)
    }
    await page.locator(`[data-testid="apply-candidate-${a.u1.id}"]`).check()

    await page.locator('[data-testid="apply-preview-button"]').click()
    await expect(page.locator('[data-testid="apply-preview-title"]')).toHaveText('Akan memengaruhi 2 sel')
    await expect(page.locator('[data-testid="apply-preview-detail"]')).toContainText('1 di antaranya sudah berisi dan akan ditimpa (1 orang tergeser)')
    await expect(page.locator('[data-testid="apply-confirm"]')).toHaveCount(0)

    await page.locator('[data-testid="apply-submit-button"]').click()
    await expect(page.locator('[data-testid="apply-confirm"]')).toContainText('Timpa 1 sel yang sudah berisi?')
    await page.locator('[data-testid="apply-confirm-button"]').click()

    await expect(page.locator('[data-testid="apply-result"]')).toContainText('2 sel diperbarui dari 2 sel sasaran')
    await expect(chips(page, a.shifts.Pagi, `${MONTH}-01`)).toHaveText([a.u1.name])
    await expect(chips(page, a.shifts.Pagi, `${MONTH}-02`)).toHaveText([a.u1.name])
    await expect(page.locator('[data-testid="day-row-2027-04-01"]')).toHaveCount(0)

    const grid = await gridOf(page, a.lineId, '2027-04')
    expect(cellOf(grid, a.shifts.Pagi, '2027-04-01')?.assignees).toEqual([])

    expect(problems).toEqual([])
  })

  // ── Skenario 18 ───────────────────────────────────────────────────────

  test('skenario 18 — Terapkan dengan daftar orang kosong: konfirmasi menyebut sel dan orang, sel jadi kosong', async ({ page }) => {
    const problems = watchProblems(page)
    const a = await arrange(page, [PAGI])
    await seedCell(page, a.shifts.Pagi, `${MONTH}-05`, [a.u1.id])
    await seedCell(page, a.shifts.Pagi, `${MONTH}-06`, [a.u2.id])
    await gotoGrid(page, a)

    await page.locator('[data-testid="open-apply-button"]').click()
    await selectLive(page, 'apply-shift', { value: a.shifts.Pagi })
    await page.locator('[data-testid="apply-mode-dates"]').check()
    for (const date of [`${MONTH}-05`, `${MONTH}-06`]) {
      await fillAndKeep(page, 'apply-date-add', date)
      await page.locator('[data-testid="apply-date-add-button"]').click()
      await expect(page.locator('[data-testid="apply-dates"]')).toContainText(date)
    }

    await page.locator('[data-testid="apply-submit-button"]').click()
    await expect(page.locator('[data-testid="apply-confirm"]')).toContainText('Timpa 2 sel yang sudah berisi?')
    await expect(page.locator('[data-testid="apply-preview-detail"]')).toContainText('(2 orang tergeser)')
    await page.locator('[data-testid="apply-confirm-button"]').click()

    await expect(page.locator('[data-testid="apply-result"]')).toContainText('2 sel diperbarui dari 2 sel sasaran')
    await expect(emptyText(page, a.shifts.Pagi, `${MONTH}-05`)).toHaveText('belum ada petugas')
    await expect(emptyText(page, a.shifts.Pagi, `${MONTH}-06`)).toHaveText('belum ada petugas')

    expect(problems).toEqual([])
  })

  // ── Skenario 19 ───────────────────────────────────────────────────────

  test('skenario 19 — Salin dari bulan sebelumnya: N terisi, M dipertahankan, K dilewati; sel berisi tak berubah', async ({ page }) => {
    const problems = watchProblems(page)
    const year = 2200 + Math.floor(Math.random() * 99)   // lihat docblock: residu shift tertutup ikut terhitung
    const target = `${year}-03`
    const a = await arrange(page, [PAGI])
    await seedCell(page, a.shifts.Pagi, `${year}-02-05`, [a.u1.id])
    await seedCell(page, a.shifts.Pagi, `${year}-02-06`, [a.u1.id])
    await seedCell(page, a.shifts.Pagi, `${target}-06`, [a.u2.id])
    await gotoGrid(page, a, target)

    await page.locator('[data-testid="copy-previous-button"]').click()
    await expect(page.locator('[data-testid="copy-preview-text"]')).toContainText('1 terisi, 1 dipertahankan, 0 dilewati')
    await page.locator('[data-testid="confirm-copy-button"]').click()

    await expect(page.locator('[data-testid="copy-result"]')).toHaveText('1 terisi, 1 dipertahankan, 0 dilewati')
    await expect(chips(page, a.shifts.Pagi, `${target}-05`)).toHaveText([a.u1.name])
    await expect(chips(page, a.shifts.Pagi, `${target}-06`)).toHaveText([a.u2.name])   // tidak ditimpa

    expect(problems).toEqual([])
  })

  // ── Skenario 20 ───────────────────────────────────────────────────────

  test('skenario 20 — Salin saat bulan sebelumnya kosong: "tidak ada yang disalin", ringkasan tak berubah', async ({ page }) => {
    const problems = watchProblems(page)
    const year = 2200 + Math.floor(Math.random() * 99)
    const target = `${year}-03`
    const a = await arrange(page, [PAGI])
    await gotoGrid(page, a, target)
    const summaryBefore = await page.locator('[data-testid="summary-text"]').textContent()

    await page.locator('[data-testid="copy-previous-button"]').click()

    await expect(page.locator('[data-testid="copy-result"]')).toHaveText('Bulan Februari ' + year + ' belum punya penugasan, tidak ada yang disalin.')
    await expect(page.locator('[data-testid="copy-preview"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="summary-text"]')).toHaveText(summaryBefore ?? '')

    expect(problems).toEqual([])
  })

  // ── Skenario 21 ───────────────────────────────────────────────────────

  test('skenario 21 — mengubah tanggal lampau: diterima, nama baru tampil, tanpa pesan terkunci', async ({ page }) => {
    const problems = watchProblems(page)
    const a = await arrange(page, [PAGI])
    const month = '2026-03'                      // jauh di masa lalu relatif terhadap hari ini
    const date = `${month}-02`
    await seedCell(page, a.shifts.Pagi, date, [a.u1.id])
    await gotoGrid(page, a, month)

    await openCell(page, a.shifts.Pagi, date)
    await setPerson(page, a.u1.id, false)
    await setPerson(page, a.u2.id, true)
    await savePanel(page)

    await expect(page.locator('[data-testid="panel-error"]')).toHaveCount(0)
    await expect(chips(page, a.shifts.Pagi, date)).toHaveText([a.u2.name])

    expect(problems).toEqual([])
  })

  // ── Skenario 22 ───────────────────────────────────────────────────────

  test('skenario 22 — dua pengatur mengubah sel yang sama: layar lama diberi tahu, simpan terakhir berlaku', async ({ page, browser }) => {
    const problems = watchProblems(page)
    const a = await arrange(page, [PAGI])
    const date = `${MONTH}-05`
    await gotoGrid(page, a)
    await openCell(page, a.shifts.Pagi, date)         // sesi B membuka sel KOSONG

    const other = await browser.newPage()             // sesi A
    try {
      await login(other, ADMIN, PASSWORD)
      await gotoGrid(other, a)
      await openCell(other, a.shifts.Pagi, date)
      await setPerson(other, a.u2.id, true)
      await savePanel(other)
      await expect(chips(other, a.shifts.Pagi, date)).toHaveText([a.u2.name])
    } finally {
      await other.close()
    }

    await setPerson(page, a.u1.id, true)
    await savePanel(page)

    await expect(cardNotice(page)).toContainText('Sel telah diubah orang lain')
    await expect(chips(page, a.shifts.Pagi, date)).toHaveText([a.u1.name])

    expect(problems).toEqual([])
  })

  // ── Skenario 23 ───────────────────────────────────────────────────────

  test('skenario 23 — navigasi ke bulan 28/29/30 hari: grid hanya memuat tanggal yang ada', async ({ page }) => {
    const problems = watchProblems(page)
    const a = await arrange(page, [PAGI])
    const rows = page.locator('tr[data-testid^="day-row-"]')
    await gotoGrid(page, a, '2026-03')
    await expect(rows).toHaveCount(31)

    await page.locator('[data-testid="next-month"]').click()
    await expect(page.locator('[data-testid="month-name"]')).toHaveText('April 2026')
    await expect(rows).toHaveCount(30)

    await page.locator('[data-testid="prev-month"]').click()
    await page.locator('[data-testid="prev-month"]').click()
    await expect(page.locator('[data-testid="month-name"]')).toHaveText('Februari 2026')
    await expect(rows).toHaveCount(28)
    await expect(page.locator('[data-testid="day-row-2026-02-29"]')).toHaveCount(0)
    await expect(page.locator('[data-testid="day-row-2026-03-01"]')).toHaveCount(0)

    await gotoGrid(page, a, '2028-02')
    await expect(rows).toHaveCount(29)
    await expect(page.locator('[data-testid="day-row-2028-02-29"]')).toHaveCount(1)

    expect(problems).toEqual([])
  })

  // ── Skenario 24 ───────────────────────────────────────────────────────

  test('skenario 24 — lebar sempit (375px): grid menggulir di wrapper-nya, kolom tanggal tetap terlihat, font >= 14px, sel tak terpotong', async ({ page }) => {
    const problems = watchProblems(page)
    await page.setViewportSize({ width: 375, height: 800 })
    const a = await arrange(page, [PAGI, SIANG, MALAM])
    await seedCell(page, a.shifts.Pagi, `${MONTH}-02`, [a.u1.id, a.u2.id])
    await seedCell(page, a.shifts.Malam, `${MONTH}-02`, [a.u1.id])
    await gotoGrid(page, a)

    await pageProblems(page)

    const wrapper = page.locator('[data-testid="schedule-grid-scroll"]')
    const dims = await wrapper.evaluate((el) => ({ scrollWidth: el.scrollWidth, clientWidth: el.clientWidth }))
    expect(dims.scrollWidth, 'grid harus lebih lebar dari wrapper-nya pada 375px').toBeGreaterThan(dims.clientWidth)

    await wrapper.evaluate((el) => { el.scrollLeft = el.scrollWidth })
    const sticky = await page.evaluate(() => {
      const wrap = document.querySelector('[data-testid="schedule-grid-scroll"]') as HTMLElement
      const dateCell = document.querySelector('tbody th.jd-date-col') as HTMLElement
      const w = wrap.getBoundingClientRect()
      const d = dateCell.getBoundingClientRect()
      return { wrapLeft: w.left, dateLeft: d.left, dateRight: d.right, wrapRight: w.right }
    })
    expect(sticky.dateLeft, 'kolom tanggal hilang saat digulir mendatar').toBeGreaterThanOrEqual(sticky.wrapLeft - 1)
    expect(sticky.dateRight).toBeLessThanOrEqual(sticky.wrapRight + 1)

    const small = await page.evaluate(() => {
      const offenders: string[] = []
      document.querySelectorAll('.jd-page *').forEach((el) => {
        const hasText = Array.from(el.childNodes).some((n) => n.nodeType === Node.TEXT_NODE && (n.textContent ?? '').trim() !== '')
        if (!hasText || (el as HTMLElement).offsetParent === null) return
        const size = parseFloat(getComputedStyle(el).fontSize)
        if (size < 14) offenders.push(`${el.tagName.toLowerCase()}.${(el as HTMLElement).className}: ${size}px`)
      })
      return offenders
    })
    expect(small, 'teks di bawah 14px').toEqual([])

    await wrapper.evaluate((el) => { el.scrollLeft = 0 })
    const clipped = await page.evaluate(() => {
      const bad: string[] = []
      document.querySelectorAll('td[data-testid^="cell-"] .jd-chip').forEach((chip) => {
        const td = chip.closest('td') as HTMLElement
        const c = chip.getBoundingClientRect()
        const t = td.getBoundingClientRect()
        if (c.right > t.right + 1 || c.bottom > t.bottom + 1) bad.push(td.getAttribute('data-testid') ?? '')
      })
      return bad
    })
    expect(clipped, 'chip terpotong oleh selnya').toEqual([])

    expect(problems).toEqual([])
  })

  // ── Skenario 25 ───────────────────────────────────────────────────────

  test('skenario 25 — PUT langsung dengan user bukan calon: 422 NOT_A_CANDIDATE dan isi sel di grid tak berubah', async ({ page }) => {
    const problems = watchProblems(page, { allowStatus: [422] })
    const a = await arrange(page, [PAGI])
    const engineer = await ensureHelperUser(page, { username: 'jdtest-engineer01', name: 'Jdtest Engineer', role: 'engineer', businessUnitId: a.businessUnitId })
    const date = `${MONTH}-05`
    await seedCell(page, a.shifts.Pagi, date, [a.u2.id])

    const result = await putCell(page, { shiftId: a.shifts.Pagi, date, userIds: [a.u1.id, engineer.id] })
    expect(result.status).toBe(422)
    expect(result.body.code).toBe('NOT_A_CANDIDATE')

    await gotoGrid(page, a)
    await expect(chips(page, a.shifts.Pagi, date)).toHaveText([a.u2.name])   // u1 pun tidak tersimpan

    expect(problems.filter((p) => !p.includes('422'))).toEqual([])
  })

  // ── Skenario 26 ───────────────────────────────────────────────────────

  test('skenario 26 — PUT langsung ke shift tertutup: 422 SHIFT_CLOSED dan histori tetap terisi', async ({ page }) => {
    const problems = watchProblems(page, { allowStatus: [422] })
    const a = await arrange(page, [PAGI, SIANG])
    const month = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta' }).format(new Date()).slice(0, 7)
    const history = `${month}-01`

    await seedCell(page, a.shifts.Siang, history, [a.u1.id])
    const closed = await closeShiftToday(page, a, 'Siang')

    test.skip(closed === null || closed.closedFrom === history, 'hari ini tanggal 1: shift tertutup tidak tampil di bulan ini')
    const { closedFrom } = closed!

    const result = await putCell(page, { shiftId: a.shifts.Siang, date: closedFrom, userIds: [a.u2.id] })
    expect(result.status).toBe(422)
    expect(result.body.code).toBe('SHIFT_CLOSED')

    await gotoGrid(page, a, month)
    await expect(chips(page, a.shifts.Siang, history)).toHaveText([a.u1.name])
    await expect(page.locator(`td[data-testid="cell-${a.shifts.Siang}-${closedFrom}"] .jd-chip`)).toHaveCount(0)

    expect(problems.filter((p) => !p.includes('422'))).toEqual([])
  })

  // ── Skenario 27 ───────────────────────────────────────────────────────

  test('skenario 27 — tanpa line atau production_line_id=all: tanpa grid, diminta memilih satu line', async ({ page }) => {
    const problems = watchProblems(page, { allowStatus: [422] })
    const a = await arrange(page, [PAGI])

    for (const query of [`businessUnitId=${a.businessUnitId}&productionLineId=all`, `businessUnitId=${a.businessUnitId}`]) {
      await page.goto(`${PATH}?${query}&month=${MONTH}`)
      await expect(page.locator('[data-testid="pick-line-guide"]')).toBeVisible()
      await expect(page.locator('[data-testid="schedule-grid"]')).toHaveCount(0)
      await expect(page.locator('[data-testid="line-select"]')).toHaveValue('')
    }

    const direct = await rawGet(page, `/api/shift-schedules?production_line_id=all&month=${MONTH}`)
    expect(direct.status).toBe(422)
    expect(direct.body.code).toBe('VALIDATION_ERROR')

    expect(problems.filter((p) => !p.includes('422'))).toEqual([])
  })

  // ══════════════════════════════════════════════════════════════════════
  // Spec v2 — tampilan Kalender / Tabel + kartu tanggal di samping
  // (10 test_scenarios baru, skenario 28-37). Kalender adalah tampilan bawaan.
  // ══════════════════════════════════════════════════════════════════════

  /** Bulan dengan tanggal 1 = Kamis (Oktober 2026): tiga kotak kosong di depan. Eksplisit, tak bergantung hari ini. */
  const OCT_2026 = '2026-10'

  function calBox(page: Page, date: string): Locator {
    return page.locator(`[data-testid="jd-day-${date}"]`)
  }

  function dayCard(page: Page): Locator {
    return page.locator('[data-testid="jd-day-card"]')
  }

  /** Kartu berada di SAMPING kartu grid (kolom berbeda), bukan di bawahnya, dan grid tetap terlihat. */
  async function expectBeside(page: Page): Promise<void> {
    const grid = await page.locator('.jd-grid-card').boundingBox()
    const card = await dayCard(page).boundingBox()
    expect(grid, 'kartu grid tidak terukur').not.toBeNull()
    expect(card, 'kartu tanggal tidak terukur').not.toBeNull()
    expect(card!.x, 'kartu tanggal harus di sebelah kanan grid').toBeGreaterThanOrEqual(grid!.x + grid!.width - 1)
    await expect(page.locator('.jd-grid-card')).toBeVisible()
  }

  // ── Skenario 28 ───────────────────────────────────────────────────────

  test('skenario 28 — kalender adalah tampilan bawaan: tujuh kolom, 31 kotak, pemilih menandai Kalender', async ({ page }) => {
    const problems = watchProblems(page)
    const a = await arrange(page, [PAGI, SIANG])
    await seedCell(page, a.shifts.Pagi, `${MONTH}-05`, [a.u1.id])
    await gotoCalendar(page, a)

    await expect(page.locator('[data-testid="schedule-grid"]')).toHaveCount(0)
    await expect(page.locator('[data-testid^="jd-cal-head-"]')).toHaveText(['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'])
    await expect(page.locator(`[data-testid^="jd-day-${MONTH}-"]`)).toHaveCount(31)

    const columns = await page.locator('[data-testid="jd-calendar"]').evaluate((el) => getComputedStyle(el).gridTemplateColumns.split(' ').length)
    expect(columns, 'kalender lebar harus tujuh kolom').toBe(7)

    // Tiap kotak: satu baris per shift hidup (2), berisi nama shift dan petugasnya.
    await expect(page.locator(`[data-testid^="jd-line-${MONTH}-05-"]`)).toHaveCount(2)
    await expect(page.locator(`[data-testid="jd-line-${MONTH}-05-${a.shifts.Pagi}"] strong`)).toHaveText('Pagi')
    await expect(page.locator(`[data-testid="jd-line-${MONTH}-05-${a.shifts.Pagi}"]`)).toContainText(a.u1.name)
    await expect(page.locator(`[data-testid="jd-line-${MONTH}-05-${a.shifts.Siang}"]`)).toContainText('belum ada petugas')

    await expect(page.locator('[data-testid="jd-view-calendar"]')).toHaveText('Kalender (aktif)')
    await expect(page.locator('[data-testid="jd-view-calendar"]')).toHaveAttribute('aria-pressed', 'true')
    await expect(page.locator('[data-testid="jd-view-table"]')).toHaveText('Tabel')
    await expect(page.locator('[data-testid="jd-view-table"]')).toHaveAttribute('aria-pressed', 'false')

    expect(problems).toEqual([])
  })

  // ── Skenario 29 ───────────────────────────────────────────────────────

  test('skenario 29 — berganti Tabel lalu Kalender: isi dan ringkasan sama, data di server tak berubah', async ({ page }) => {
    const problems = watchProblems(page)
    const a = await arrange(page, [PAGI, SIANG])
    await seedCell(page, a.shifts.Pagi, `${MONTH}-05`, [a.u1.id])
    await seedCell(page, a.shifts.Siang, `${MONTH}-06`, [a.u2.id])
    const before = await gridOf(page, a.lineId, MONTH)
    await gotoCalendar(page, a)

    const summary = page.locator('[data-testid="summary-text"]')
    await expect(summary).toHaveText('2 dari 62 sel sudah terisi')

    await page.locator('[data-testid="jd-view-table"]').click()
    await expect(page.locator('[data-testid="schedule-grid"]')).toBeVisible()
    await expect(page.locator('[data-testid="jd-calendar"]')).toHaveCount(0)
    await expect(summary).toHaveText('2 dari 62 sel sudah terisi')
    await expect(chips(page, a.shifts.Pagi, `${MONTH}-05`)).toHaveText([a.u1.name])
    await expect(chips(page, a.shifts.Siang, `${MONTH}-06`)).toHaveText([a.u2.name])
    await expect(page.locator('[data-testid="month-name"]')).toHaveText('Maret 2027')

    // Urutan kolom shift mengikuti jam (position): Pagi lalu Siang.
    await expect(page.locator('thead th[data-testid^="shift-head-"]')).toHaveText([/^Pagi/, /^Siang/])

    await page.locator('[data-testid="jd-view-calendar"]').click()
    await expect(page.locator('[data-testid="jd-calendar"]')).toBeVisible()
    await expect(summary).toHaveText('2 dari 62 sel sudah terisi')
    await expect(page.locator(`[data-testid="jd-line-${MONTH}-05-${a.shifts.Pagi}"]`)).toContainText(a.u1.name)

    const after = await gridOf(page, a.lineId, MONTH)
    expect(after.cells, 'berpindah tampilan tidak boleh mengubah data').toEqual(before.cells)
    expect(after.summary).toEqual(before.summary)

    expect(problems).toEqual([])
  })

  // ── Skenario 30 ───────────────────────────────────────────────────────

  test('skenario 30 — mengeklik sel tabel membuka kartu tanggal di samping grid; menyimpan menampilkan nama di sel', async ({ page }) => {
    const problems = watchProblems(page)
    const a = await arrange(page, [PAGI, SIANG])
    const date = `${MONTH}-05`
    await gotoGrid(page, a)

    // Tanpa kontrol "Atur" di tabel mana pun.
    await expect(page.getByRole('button', { name: 'Atur', exact: true })).toHaveCount(0)
    await expect(page.locator('[data-testid^="edit-"]')).toHaveCount(0)
    await expect(dayCard(page)).toHaveCount(0)

    await page.locator(`[data-testid="jd-cell-btn-${a.shifts.Pagi}-${date}"]`).click()
    await expect(dayCard(page)).toHaveAttribute('data-date', date)
    await expectBeside(page)
    await expect(page.locator(`[data-testid="jd-card-shift-toggle-${a.shifts.Pagi}"]`)).toHaveAttribute('aria-expanded', 'true')
    await expect(page.locator(`[data-testid="jd-card-shift-toggle-${a.shifts.Siang}"]`)).toHaveAttribute('aria-expanded', 'false')

    await setPerson(page, a.u1.id, true)
    await savePanel(page)

    await expect(chips(page, a.shifts.Pagi, date)).toHaveText([a.u1.name])
    await expect(emptyText(page, a.shifts.Siang, date)).toHaveText('belum ada petugas')     // shift lain tak tersentuh
    const grid = await gridOf(page, a.lineId, MONTH)
    expect(cellOf(grid, a.shifts.Siang, date)?.assignees).toEqual([])

    expect(problems).toEqual([])
  })

  // ── Skenario 31 ───────────────────────────────────────────────────────

  test('skenario 31 — mengeklik kotak kalender membuka kartu di samping dan menandai kotak "Dibuka"', async ({ page }) => {
    const problems = watchProblems(page)
    const a = await arrange(page, [PAGI, SIANG])
    const date = `${MONTH}-05`
    await gotoCalendar(page, a)
    await expect(page.locator('[data-testid^="jd-opened-"]')).toHaveCount(0)

    await calBox(page, date).click()

    await expect(dayCard(page)).toHaveAttribute('data-date', date)
    await expectBeside(page)
    await expect(page.locator('[data-testid^="jd-opened-"]')).toHaveText(['Dibuka'])
    await expect(page.locator(`[data-testid="jd-opened-${date}"]`)).toBeVisible()
    await expect(page.locator(`[data-testid="jd-card-shift-toggle-${a.shifts.Pagi}"]`)).toBeVisible()
    await expect(page.locator(`[data-testid="jd-card-shift-toggle-${a.shifts.Siang}"]`)).toBeVisible()

    // Dapat dibuka juga lewat papan ketik (kotak adalah tombol yang bisa difokus).
    await calBox(page, `${MONTH}-06`).focus()
    await page.keyboard.press('Enter')
    await expect(dayCard(page)).toHaveAttribute('data-date', `${MONTH}-06`)
    await expect(page.locator('[data-testid^="jd-opened-"]')).toHaveText(['Dibuka'])

    expect(problems).toEqual([])
  })

  // ── Skenario 32 ───────────────────────────────────────────────────────

  test('skenario 32 — sel shift tertutup hanya menampilkan histori: tanpa checkbox dan tanpa tombol simpan', async ({ page }) => {
    const problems = watchProblems(page)
    const a = await arrange(page, [PAGI, SIANG])
    const month = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta' }).format(new Date()).slice(0, 7)
    const history = `${month}-01`

    await seedCell(page, a.shifts.Siang, history, [a.u1.id])
    const closed = await closeShiftToday(page, a, 'Siang')
    test.skip(closed === null || closed.closedFrom === history, 'hari ini tanggal 1: shift tertutup tidak tampil di bulan ini (dibuktikan di unit test)')

    // Kalender: baris shift tertutup hanya tampil di kotak yang berisi nama, dengan lencana.
    await gotoCalendar(page, a, month)
    await expect(page.locator(`[data-testid="jd-line-${history}-${a.shifts.Siang}"]`)).toContainText('shift sudah dihapus')
    await expect(page.locator(`[data-testid="jd-line-${closed!.closedFrom}-${a.shifts.Siang}"]`)).toHaveCount(0)

    await calBox(page, history).click()
    await page.locator(`[data-testid="jd-card-shift-toggle-${a.shifts.Siang}"]`).click()
    await expect(page.locator(`[data-testid="jd-card-history-${a.shifts.Siang}"]`)).toContainText(a.u1.name)
    await expect(page.locator(`[data-testid="jd-card-save-${a.shifts.Siang}"]`)).toHaveCount(0)
    await expect(page.locator(`[data-testid="jd-card-clear-${a.shifts.Siang}"]`)).toHaveCount(0)
    await expect(page.locator(`[data-testid="jd-card-shift-${a.shifts.Siang}"] input`)).toHaveCount(0)

    const grid = await gridOf(page, a.lineId, month)
    expect(cellOf(grid, a.shifts.Siang, history)?.assignees.map((x) => x.user_id)).toEqual([a.u1.id])

    expect(problems).toEqual([])
  })

  // ── Skenario 33 ───────────────────────────────────────────────────────

  test('skenario 33 — kotak kalender di luar bulan tidak dapat diklik: tepat tiga kotak kosong di depan Oktober 2026', async ({ page }) => {
    const problems = watchProblems(page)
    const a = await arrange(page, [PAGI])
    let updates = 0
    page.on('request', (request) => { if (request.url().includes('/livewire/update')) updates += 1 })
    await gotoCalendar(page, a, OCT_2026)

    await expect(page.locator('[data-testid^="jd-blank-lead-"]')).toHaveCount(3)
    await expect(page.locator(`[data-testid="jd-blank-lead-2"] + [data-testid="jd-day-${OCT_2026}-01"]`)).toHaveCount(1)

    const before = updates
    for (let i = 0; i < 3; i += 1) {
      await page.locator(`[data-testid="jd-blank-lead-${i}"]`).click({ force: true })
    }
    await page.waitForTimeout(600)      // pemeriksaan ketiadaan: beri waktu bila ada permintaan yang akan terkirim
    expect(updates, 'mengeklik kotak kosong tidak boleh mengirim permintaan').toBe(before)
    await expect(dayCard(page)).toHaveCount(0)

    // Kontrol: kotak nyata merespons, jadi ketiadaan di atas bukan karena halaman mati.
    await calBox(page, `${OCT_2026}-01`).click()
    await expect(dayCard(page)).toHaveAttribute('data-date', `${OCT_2026}-01`)

    expect(problems).toEqual([])
  })

  // ── Skenario 34 ───────────────────────────────────────────────────────

  test('skenario 34 — kotak kalender padat memakai "+N lagi": N tepat, font >= 14px, tidak terpotong, tidak ada nama hilang', async ({ page }) => {
    const problems = watchProblems(page)
    const a = await arrange(page, [PAGI])
    const h1 = await ensureHelperUser(page, { username: 'jdtest-operator01', name: 'Jdtest Operator Satu', role: 'operator', businessUnitId: a.businessUnitId })
    const h2 = await ensureHelperUser(page, { username: 'jdtest-operator02', name: 'Jdtest Operator Dua', role: 'operator', businessUnitId: a.businessUnitId })
    const date = `${MONTH}-05`
    const everyone = [a.u1, a.u2, h1, h2]
    await seedCell(page, a.shifts.Pagi, date, everyone.map((u) => u.id))
    await gotoCalendar(page, a)

    const more = page.locator(`[data-testid="jd-more-${date}-${a.shifts.Pagi}"]`)
    await expect(more).toHaveText('+2 lagi')            // 4 orang - 2 tampil

    const names = page.locator(`[data-testid="jd-line-${date}-${a.shifts.Pagi}"] > span`).first()
    expect((await names.innerText()).split(',').map((n) => n.trim()).filter(Boolean)).toHaveLength(2)

    const geometry = await page.evaluate(({ moreId, boxId }) => {
      const btn = document.querySelector(`[data-testid="${moreId}"]`) as HTMLElement
      const box = document.querySelector(`[data-testid="${boxId}"]`) as HTMLElement
      const b = btn.getBoundingClientRect()
      const x = box.getBoundingClientRect()
      return {
        font: parseFloat(getComputedStyle(btn).fontSize),
        clipped: b.right > x.right + 1 || b.bottom > x.bottom + 1 || b.left < x.left - 1,
        boxOverflows: box.scrollHeight > box.clientHeight + 1,
      }
    }, { moreId: `jd-more-${date}-${a.shifts.Pagi}`, boxId: `jd-day-${date}` })
    expect(geometry.font, '"+N lagi" di bawah 14px').toBeGreaterThanOrEqual(14)
    expect(geometry.clipped, '"+N lagi" terpotong kotaknya').toBe(false)

    await more.click()
    await expect(more).toHaveText('sembunyikan')
    const all = (await names.innerText()).split(',').map((n) => n.trim()).filter(Boolean)
    expect(all.sort()).toEqual(everyone.map((u) => u.name).sort())      // tak ada nama hilang
    const afterGeometry = await page.evaluate((boxId) => {
      const box = document.querySelector(`[data-testid="${boxId}"]`) as HTMLElement
      return box.scrollHeight > box.clientHeight + 1
    }, `jd-day-${date}`)
    expect(afterGeometry, 'daftar yang dibuka meluap dari kotaknya (terpotong)').toBe(false)
    expect(geometry.boxOverflows).toBe(false)

    expect(problems).toEqual([])
  })

  // ── Skenario 35 ───────────────────────────────────────────────────────

  test('skenario 35 — pindah bulan menutup kartu tanggal', async ({ page }) => {
    const problems = watchProblems(page)
    const a = await arrange(page, [PAGI])
    await gotoCalendar(page, a)

    await calBox(page, `${MONTH}-05`).click()
    await expect(dayCard(page)).toBeVisible()

    await page.locator('[data-testid="next-month"]').click()
    await expect(page.locator('[data-testid="month-name"]')).toHaveText('April 2027')
    await expect(dayCard(page)).toHaveCount(0)
    await expect(page.locator('[data-testid="jd-card-placeholder"]')).toBeVisible()
    await expect(page.locator('[data-testid^="jd-opened-"]')).toHaveCount(0)

    expect(problems).toEqual([])
  })

  // ── Skenario 36 ───────────────────────────────────────────────────────

  test('skenario 36 — mengeklik tanggal lain dengan perubahan belum disimpan: konfirmasi inline, kartu tidak berpindah diam-diam', async ({ page }) => {
    const problems = watchProblems(page)
    const a = await arrange(page, [PAGI])
    await gotoCalendar(page, a)

    await calBox(page, `${MONTH}-05`).click()
    await expect(dayCard(page)).toHaveAttribute('data-date', `${MONTH}-05`)
    await setPerson(page, a.u1.id, true)
    await expect(page.locator(`[data-testid="jd-card-dirty-${a.shifts.Pagi}"]`)).toHaveText('belum disimpan')

    await calBox(page, `${MONTH}-06`).click()
    await expect(page.locator('[data-testid="jd-discard-confirm"]')).toBeVisible()
    await expect(dayCard(page)).toHaveAttribute('data-date', `${MONTH}-05`)            // tidak berpindah diam-diam

    await page.locator('[data-testid="jd-discard-no"]').click()
    await expect(page.locator('[data-testid="jd-discard-confirm"]')).toHaveCount(0)
    await expect(dayCard(page)).toHaveAttribute('data-date', `${MONTH}-05`)
    await expect(page.locator(`[data-testid="candidate-${a.u1.id}"]`)).toBeChecked()   // pilihan dipertahankan

    await calBox(page, `${MONTH}-06`).click()
    await page.locator('[data-testid="jd-discard-yes"]').click()
    await expect(dayCard(page)).toHaveAttribute('data-date', `${MONTH}-06`)
    await expect(page.locator(`[data-testid="candidate-${a.u1.id}"]`)).not.toBeChecked()   // perubahan dibuang

    const grid = await gridOf(page, a.lineId, MONTH)
    expect(grid.summary.filled_cells, 'tidak ada yang tertulis').toBe(0)

    expect(problems).toEqual([])
  })

  // ── Skenario 37 ───────────────────────────────────────────────────────

  test('skenario 37 — kalender pada 375px menjadi daftar per tanggal: tanpa gulir mendatar, font >= 14px, kotak tetap membuka kartu', async ({ page }) => {
    const problems = watchProblems(page)
    await page.setViewportSize({ width: 375, height: 800 })
    const a = await arrange(page, [PAGI, SIANG])
    await seedCell(page, a.shifts.Pagi, `${MONTH}-02`, [a.u1.id, a.u2.id])
    await gotoCalendar(page, a)

    const columns = await page.locator('[data-testid="jd-calendar"]').evaluate((el) => getComputedStyle(el).gridTemplateColumns.split(' ').length)
    expect(columns, 'pada 375px kalender harus satu kolom (daftar), bukan tujuh').toBe(1)
    await expect(page.locator('[data-testid="jd-cal-head-Sen"]')).toBeHidden()
    await pageProblems(page)                                       // tidak ada gulir mendatar pada halaman

    const small = await page.evaluate(() => {
      const offenders: string[] = []
      document.querySelectorAll('.jd-page *').forEach((el) => {
        const hasText = Array.from(el.childNodes).some((n) => n.nodeType === Node.TEXT_NODE && (n.textContent ?? '').trim() !== '')
        if (!hasText || (el as HTMLElement).offsetParent === null) return
        const size = parseFloat(getComputedStyle(el).fontSize)
        if (size < 14) offenders.push(`${el.tagName.toLowerCase()}.${(el as HTMLElement).className}: ${size}px`)
      })
      return offenders
    })
    expect(small, 'teks di bawah 14px').toEqual([])

    const clipped = await page.evaluate(() => {
      const bad: string[] = []
      document.querySelectorAll('[data-testid^="jd-line-"]').forEach((line) => {
        const box = line.closest('[data-testid^="jd-day-"]') as HTMLElement
        const l = line.getBoundingClientRect()
        const b = box.getBoundingClientRect()
        if (l.right > b.right + 1 || l.bottom > b.bottom + 1) bad.push(line.getAttribute('data-testid') ?? '')
      })
      return bad
    })
    expect(clipped, 'baris shift terpotong kotaknya').toEqual([])

    await calBox(page, `${MONTH}-02`).click()
    await expect(dayCard(page)).toHaveAttribute('data-date', `${MONTH}-02`)
    await expect(dayCard(page)).toBeVisible()
    await pageProblems(page)

    expect(problems).toEqual([])
  })

  // ── Skenario 38 ───────────────────────────────────────────────────────

  test('skenario 38 — localStorage gagal tidak merusak tampilan: kalender tetap tampil dan pemilih tetap bekerja', async ({ page }) => {
    const problems = watchProblems(page)
    // Hanya kunci milik layar ini yang dibuat melempar (seperti mode privat / kuota penuh).
    await page.addInitScript(() => {
      const fail = (name: string) => function (this: Storage, key: string, ...rest: unknown[]) {
        if (key === 'jd-view-mode') throw new DOMException('localStorage tidak tersedia', 'SecurityError')
        return (Storage.prototype as any)[`__orig_${name}`].call(this, key, ...rest)
      }
      ;(Storage.prototype as any).__orig_getItem = Storage.prototype.getItem
      ;(Storage.prototype as any).__orig_setItem = Storage.prototype.setItem
      Storage.prototype.getItem = fail('getItem') as typeof Storage.prototype.getItem
      Storage.prototype.setItem = fail('setItem') as typeof Storage.prototype.setItem
    })
    const a = await arrange(page, [PAGI])
    await seedCell(page, a.shifts.Pagi, `${MONTH}-05`, [a.u1.id])
    await gotoCalendar(page, a)

    await expect(page.locator(`[data-testid="jd-line-${MONTH}-05-${a.shifts.Pagi}"]`)).toContainText(a.u1.name)
    await page.locator('[data-testid="jd-view-table"]').click()
    await expect(page.locator('[data-testid="schedule-grid"]')).toBeVisible()
    await page.locator('[data-testid="jd-view-calendar"]').click()
    await expect(page.locator('[data-testid="jd-calendar"]')).toBeVisible()

    expect(problems).toEqual([])
  })
})
