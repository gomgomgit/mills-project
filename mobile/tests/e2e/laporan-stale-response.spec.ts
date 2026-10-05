import { test, expect, type Page } from '@playwright/test'
import { login, USERS } from './helpers'

// Audit 2026-10-05 — Laporan mobile: mengganti periode/line dengan cepat
// membuat respons ringkasan LAMA yang tiba belakangan menimpa ringkasan
// BARU. Bukti di browser sungguhan terhadap backend dev (:8000): permintaan
// ringkasan PERTAMA ditahan (throttle) sampai permintaan kedua selesai,
// lalu dilepas. Yang tampil harus tetap ringkasan periode yang terakhir
// dipilih. Tanpa pageerror, console.error, atau respons HTTP >= 400.

const REPORTS = [
  { path: '/reports/sterilizer', api: 'sterilizer-reports' },
  { path: '/reports/cages-track', api: 'cages-track-reports' },
  { path: '/reports/boiler-room', api: 'boiler-room-reports' },
  { path: '/reports/clarification', api: 'clarification-reports' },
  { path: '/reports/storage-tank', api: 'storage-tank-reports' },
]

function watchProblems(page: Page): string[] {
  const problems: string[] = []
  page.on('pageerror', (e) => problems.push(`pageerror: ${e.message}`))
  page.on('console', (m) => m.type() === 'error' && problems.push(`console.error: ${m.text()}`))
  page.on('response', (r) => r.status() >= 400 && problems.push(`HTTP ${r.status()} ${r.url()}`))
  return problems
}

for (const report of REPORTS) {
  test(`${report.path} — respons ringkasan lama yang tertahan tidak menimpa pilihan periode terbaru`, async ({ page }) => {
    const problems = watchProblems(page)
    await login(page, USERS.supervisor)

    // Permintaan ringkasan pertama ditahan sampai yang kedua sudah dijawab.
    let calls = 0
    let releaseFirst!: () => void
    const firstReleased = new Promise<void>((resolve) => (releaseFirst = resolve))
    const finishedSecond = new Promise<void>((resolve) => {
      page.on('requestfinished', (req) => {
        if (req.url().includes(`/api/${report.api}/summary`) && new URL(req.url()).searchParams.get('_n') === null && calls >= 2) resolve()
      })
    })
    await page.route(`**/api/${report.api}/summary**`, async (route) => {
      calls += 1
      if (calls === 1) {
        await firstReleased
      }
      await route.continue()
    })

    await page.goto(report.path)
    const lineSelect = page.getByTestId('production-line-select')
    const lineOptions = lineSelect.locator('option[value]:not([value=""])')
    await expect.poll(() => lineOptions.count()).toBeGreaterThanOrEqual(1)
    await lineSelect.selectOption((await lineOptions.first().getAttribute('value')) as string)
    const periodSelect = page.getByTestId('period-select')
    const options = periodSelect.locator('option[value]:not([value=""])')
    await expect.poll(() => options.count()).toBeGreaterThanOrEqual(2)
    const [first, second] = [await options.nth(0), await options.nth(1)]
    const firstValue = (await first.getAttribute('value')) as string
    const secondValue = (await second.getAttribute('value')) as string
    const secondName = ((await second.textContent()) ?? '').trim()

    await periodSelect.selectOption(firstValue)
    await expect.poll(() => calls).toBe(1)
    await periodSelect.selectOption(secondValue)
    await finishedSecond
    await expect(page.locator('.period-meta-name')).toBeVisible()
    const shownAfterSecond = (await page.locator('.period-meta-name').textContent())?.trim() ?? ''
    expect(secondName).toContain(shownAfterSecond)

    // Lepaskan respons lama; tunggu ia benar-benar tiba di halaman.
    const firstDone = page.waitForEvent('requestfinished', (req) => req.url().includes(`/api/${report.api}/summary`) && req.url().includes(`period_id=${firstValue}`))
    releaseFirst()
    await firstDone
    await page.waitForTimeout(300)

    await expect(page.locator('.period-meta-name')).toHaveText(shownAfterSecond)
    await expect(periodSelect).toHaveValue(secondValue)
    await expect(page.getByTestId('summary-loading')).toHaveCount(0)
    expect(problems, problems.join('\n')).toEqual([])
  })
}
