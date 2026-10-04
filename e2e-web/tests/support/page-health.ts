import { expect, type Locator, type Page } from '@playwright/test'
import { APP_ORIGIN } from './base-url'

/**
 * Kesehatan halaman — dipasang di awal test, diperiksa di akhir. Tanpa
 * pendengar ini regresi Alpine (pageerror) dan respons 4xx/5xx lolos diam-
 * diam selama elemen yang diasersi kebetulan tetap tampil (pelajaran audit
 * 2026-10-04: spec lama tidak mendengarkan pageerror).
 *
 * Hanya respons dari aplikasi ini yang dihitung — widget chatbot memanggil
 * layanan eksternal yang bukan bagian alur yang diuji.
 */

export function watchProblems(page: Page, options: { allowStatus?: number[] } = {}): string[] {
  const problems: string[] = []
  const allow = new Set(options.allowStatus ?? [])
  page.on('pageerror', (error) => problems.push(`pageerror: ${error.message}`))
  page.on('console', (message) => {
    if (message.type() !== 'error') return
    const text = message.text()
    // Status yang memang diharapkan (mis. 403 halaman akses ditolak) juga
    // dilaporkan Chromium sebagai console error "Failed to load resource".
    if ([...allow].some((status) => text.includes(`status of ${status}`))) return
    problems.push(`console.error: ${text}`)
  })
  page.on('response', (response) => {
    if (response.url().startsWith(APP_ORIGIN) && response.status() >= 400 && !allow.has(response.status())) {
      problems.push(`HTTP ${response.status()}: ${response.url()}`)
    }
  })
  return problems
}

/** Memilih opsi x-searchable-select berdasarkan label persis. */
export async function pickCombobox(scope: Page | Locator, input: Locator, label: string): Promise<void> {
  await input.click()
  await input.fill(label)
  const listboxId = await input.getAttribute('aria-controls')
  const page = 'page' in scope ? scope.page() : scope
  await page.locator(`#${listboxId}`).getByRole('option', { name: label, exact: true }).click()
}
