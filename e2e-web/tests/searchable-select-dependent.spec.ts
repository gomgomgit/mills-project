/**
 * Regresi x-searchable-select pada pemilih BERGANTUNG (temuan audit
 * 2026-10-04 #8).
 *
 * Gejala yang dikunci di sini: setelah Business Unit diganti, combobox
 * Production Line di Kelola Station melempar ~15 pageerror Alpine
 * ("index is not defined", "option is not defined"), menampilkan opsi
 * hantu kosong, dan listbox-nya tetap terbuka menutupi kolom Type. Detail
 * Periode → Edit menampilkan combobox Business Unit KOSONG dengan listbox
 * kosong di atas teks bantuan.
 *
 * Penyebab: morph Livewire ikut menyentuh <template x-for> milik Alpine di
 * dalam listbox (server mengirim <template> tanpa <li>, DOM klien berisi
 * <li> hasil render Alpine) — keduanya berebut subtree yang sama.
 *
 * Setiap test memasang pendengar pageerror, console.error dan respons
 * HTTP >= 400 — tanpa itu, regresi Alpine ini lolos diam-diam (pelajaran
 * audit: spec lama tidak mendengarkan pageerror).
 */
import { test, expect, type Page } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { APP_ORIGIN } from './support/base-url'


function watchProblems(page: Page): string[] {
  const problems: string[] = []
  page.on('pageerror', (error) => problems.push(`pageerror: ${error.message}`))
  page.on('console', (message) => {
    if (message.type() === 'error') problems.push(`console.error: ${message.text()}`)
  })
  page.on('response', (response) => {
    const url = response.url()
    // Hanya respons aplikasi ini — widget chatbot memanggil layanan eksternal.
    if (response.status() >= 400 && url.startsWith(APP_ORIGIN)) {
      problems.push(`HTTP ${response.status()}: ${url}`)
    }
  })
  return problems
}

async function pickOption(page: Page, id: string, nth: number): Promise<string> {
  await page.locator(`#${id}`).click()
  const option = page.locator(`#${id}-listbox`).getByRole('option').nth(nth)
  const label = (await option.textContent())?.trim() ?? ''
  await option.click()
  return label
}

// Business Unit fixture yang PASTI punya Production Line
// (BrowserTestFixtureSeeder). Dulu BU dipilih menurut posisi (opsi ke-1/ke-2),
// dan BU di posisi itu bergantung isi database — bila tanpa line, listbox
// Production Line kosong dan klik opsinya menunggu sampai timeout.
const BU_WITH_LINE_A = 'Mill Station Baru'
const BU_WITH_LINE_B = 'Mill Station Tujuan Edit'

async function pickByLabel(page: Page, id: string, label: string): Promise<void> {
  await page.locator(`#${id}`).click()
  await page.locator(`#${id}`).fill(label)
  await page.locator(`#${id}-listbox`).getByRole('option', { name: label, exact: true }).click()
}

async function expectHealthyCombobox(page: Page, id: string) {
  const listbox = page.locator(`#${id}-listbox`)
  // Tertutup setelah dipilih — tidak menutupi kolom di bawahnya.
  await expect(listbox).toBeHidden()
  // Dibuka: setiap opsi punya label (tidak ada opsi hantu kosong).
  await page.locator(`#${id}`).click()
  await expect(listbox).toBeVisible()
  const labels = await listbox.getByRole('option').allTextContents()
  expect(labels.length).toBeGreaterThan(1)
  for (const label of labels) expect(label.trim()).not.toBe('')
  await page.keyboard.press('Escape')
  await expect(listbox).toBeHidden()
}

test.describe('x-searchable-select bergantung (BU → Production Line)', () => {
  test('Kelola Station: ganti Business Unit dua kali, Production Line tetap sehat', async ({ page }) => {
    const problems = watchProblems(page)
    await login(page, 'stest-admin01', PASSWORD)
    await page.goto('/master-data/stations')

    await page.locator('button', { hasText: 'Tambah Station' }).click()
    await pickByLabel(page, 'business_unit_id', BU_WITH_LINE_A)
    await pickOption(page, 'production_line_id', 1)
    await expectHealthyCombobox(page, 'production_line_id')

    // Ganti BU lagi — jalur yang dulu memicu pageerror.
    await pickByLabel(page, 'business_unit_id', BU_WITH_LINE_B)
    await expect(page.locator('#production_line_id')).toHaveValue('-- Pilih Production Line --')
    await pickOption(page, 'production_line_id', 1)
    await expectHealthyCombobox(page, 'production_line_id')

    // Kolom Type di bawahnya tetap bisa diklik (tidak tertutup listbox).
    await page.locator('#type').click()
    await expect(page.locator('#type-listbox')).toBeVisible()
    const typeLabels = await page.locator('#type-listbox').getByRole('option').allTextContents()
    expect(typeLabels.map((l) => l.trim())).toContain('Boiler Room')

    expect(problems).toEqual([])
  })

  test('Data Browser (Admin): ganti Business Unit, Production Line tetap sehat', async ({ page }) => {
    const problems = watchProblems(page)
    await login(page, 'stest-admin01', PASSWORD)
    await page.goto('/data/weighbridge')

    await pickByLabel(page, 'business_unit_id', BU_WITH_LINE_A)
    await pickOption(page, 'production_line_id', 1)
    await pickByLabel(page, 'business_unit_id', BU_WITH_LINE_B)
    await expect(page.locator('#production_line_id-listbox')).toBeHidden()
    await page.locator('#production_line_id').click()
    const labels = await page.locator('#production_line_id-listbox').getByRole('option').allTextContents()
    for (const label of labels) expect(label.trim()).not.toBe('')
    await page.keyboard.press('Escape')
    await expect(page.locator('#production_line_id-listbox')).toBeHidden()

    expect(problems).toEqual([])
  })

  test('Detail Periode → Edit: combobox Business Unit terisi dan listbox tertutup', async ({ page }) => {
    const problems = watchProblems(page)
    await login(page, 'butest-admin01', PASSWORD)
    await page.goto('/master-data/periods')

    // Buka URL detail LANGSUNG (muat penuh), bukan lewat klik dari daftar:
    // halaman daftar punya combobox sendiri yang sudah mendefinisikan
    // ssSelect() lebih dulu, sehingga menutupi kasus "combobox pertama di
    // halaman baru muncul di dalam modal".
    const href = await page.locator('[data-testid^="period-link-"]').first().getAttribute('href')
    expect(href).toBeTruthy()
    await page.goto(href!)

    await page.locator('[data-testid^="edit-button-"]').first().click()
    const input = page.locator('#business_unit_id')
    await expect(input).toBeVisible()
    await expect(input).not.toHaveValue('')
    await expect(page.locator('#business_unit_id-listbox')).toBeHidden()

    expect(problems).toEqual([])
  })
})
