import { test, expect, type Page } from '@playwright/test'
import { login, USERS } from './helpers'

// Audit 2026-10-05 — bubble Mills AI (dan jam mengambang) menutupi kontrol
// interaktif SAAT DIAM. Ruang aman sebelumnya (padding-bottom pada root
// layar, floatingSafeArea.ts) hanya menjamin ujung gulir; di posisi awal
// layar (belum digulir) bubble tetap menimpa apa pun yang kebetulan ada di
// pojok kanan bawah: Clear/Simpan Form Sterilizer di 390x844, Simpan
// Threshing/Pressing/…, "+ Tambah Baris" di 360x740, input header form.
//
// Sekarang bubble + jam duduk di dok bawah yang buram (App.vue,
// data-testid="floating-dock") setinggi ruang aman — konten tidak pernah
// tampak/terjangkau di bawahnya, sehingga di posisi diam mana pun (awal
// maupun ujung gulir) tidak ada kontrol yang tertutup.
//
// Pemeriksaan per layar, di posisi gulir paling atas DAN paling bawah:
//   1. setiap kontrol interaktif di <main> — bagian yang TAMPAK (dipotong
//      viewport, tepi atas dok, dan leluhur overflow) — tidak beririsan
//      dengan kotak bubble/jam;
//   2. dok ada dan buram: titik-titik di dalam dok jatuh ke dok/bubble/jam,
//      bukan ke konten di bawahnya;
//   3. bubble tetap bisa diketuk (titik tengahnya mengenai bubble).
// Ditambah: tidak ada pageerror, console.error, atau respons HTTP >= 400.

const STATIONS = [
  'weighbridge', 'grading', 'cages-track', 'threshing', 'pressing', 'depricarping',
  'kernel-plant', 'solid-waste-disposal', 'process-water', 'kernel-dispatch', 'cpo-dispatch',
  'effluent-plant', 'storage-tank', 'engine-room', 'boiler-room', 'clarification',
  'process-quality-control', 'sterilizer',
]
const REPORTS = ['sterilizer', 'cages-track', 'boiler-room', 'clarification', 'storage-tank']

const VIEWPORTS = [
  { width: 390, height: 844 },
  { width: 360, height: 740 },
]

type Mode = { bubble: boolean; clock: boolean }

function watchProblems(page: Page): string[] {
  const problems: string[] = []
  page.on('pageerror', (e) => problems.push(`pageerror: ${e.message}`))
  page.on('console', (m) => m.type() === 'error' && problems.push(`console.error: ${m.text()}`))
  page.on('response', (r) => r.status() >= 400 && problems.push(`HTTP ${r.status()} ${r.url()}`))
  return problems
}

async function setFloatingMode(page: Page, mode: Mode): Promise<void> {
  await page.addInitScript((m) => {
    localStorage.setItem('msl_ai_bubble_enabled', String(m.bubble))
    localStorage.setItem('msl_floating_clock_enabled', String(m.clock))
  }, mode)
}

/** Kontrol yang tertutup elemen mengambang pada posisi gulir saat ini. */
async function coveredControls(page: Page, mode: Mode): Promise<string[]> {
  return page.evaluate((m) => {
    const floatingEls = [
      document.querySelector('[data-testid="ai-assistant-bubble"]'),
      document.querySelector('[data-testid="floating-clock"]'),
    ].filter((el): el is Element => Boolean(el))
    const issues: string[] = []
    if (m.bubble && !document.querySelector('[data-testid="ai-assistant-bubble"]')) issues.push('bubble tidak tampil')
    if (m.clock && !document.querySelector('[data-testid="floating-clock"]')) issues.push('jam tidak tampil')
    if (floatingEls.length === 0) return issues

    const dock = document.querySelector('[data-testid="floating-dock"]')
    if (!dock) {
      issues.push('dok bawah tidak ada')
    }
    const visibleBottom = dock ? dock.getBoundingClientRect().top : window.innerHeight

    // (2) dok buram: titik di dalam dok tidak jatuh ke konten layar.
    if (dock) {
      const y = window.innerHeight - 4
      for (const x of [4, window.innerWidth / 2, window.innerWidth - 4]) {
        const hit = document.elementFromPoint(x, y)
        if (hit && hit.closest('main')) issues.push(`konten terjangkau di bawah dok @${Math.round(x)},${y}`)
      }
      for (const f of floatingEls) {
        const r = f.getBoundingClientRect()
        if (r.top < visibleBottom - 0.5) issues.push(`${f.getAttribute('data-testid')} keluar dari dok`)
      }
    }

    // (3) bubble bisa diketuk.
    const bubble = document.querySelector('[data-testid="ai-assistant-bubble"]')
    if (bubble) {
      const r = bubble.getBoundingClientRect()
      const hit = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2)
      if (!hit || !bubble.contains(hit)) issues.push('bubble tidak bisa diketuk')
    }

    // (1) bagian tampak setiap kontrol tidak beririsan dengan bubble/jam.
    const floatRects = floatingEls.map((f) => ({ id: f.getAttribute('data-testid'), r: f.getBoundingClientRect() }))
    const controls = document.querySelectorAll(
      'main button, main input, main select, main textarea, main a[href], main [role="button"], main [tabindex]:not([tabindex="-1"])',
    )
    controls.forEach((el) => {
      const raw = el.getBoundingClientRect()
      if (!raw.width || !raw.height) return
      let top = Math.max(raw.top, 0)
      let bottom = Math.min(raw.bottom, visibleBottom)
      let left = Math.max(raw.left, 0)
      let right = Math.min(raw.right, window.innerWidth)
      for (let p = el.parentElement; p && p !== document.body; p = p.parentElement) {
        const style = getComputedStyle(p)
        if (style.overflowX !== 'visible' || style.overflowY !== 'visible') {
          const pr = p.getBoundingClientRect()
          if (style.overflowX !== 'visible') { left = Math.max(left, pr.left); right = Math.min(right, pr.right) }
          if (style.overflowY !== 'visible') { top = Math.max(top, pr.top); bottom = Math.min(bottom, pr.bottom) }
        }
      }
      if (bottom <= top || right <= left) return
      for (const f of floatRects) {
        if (left < f.r.right && right > f.r.left && top < f.r.bottom && bottom > f.r.top) {
          const label = el.getAttribute('data-testid') || `${el.tagName.toLowerCase()}#${el.id || (el.textContent ?? '').trim().slice(0, 20)}`
          issues.push(`${label} tertutup ${f.id} (y=${Math.round(raw.top)})`)
        }
      }
    })
    return issues
  }, mode)
}

/** Periksa layar yang sedang terbuka pada posisi gulir atas dan bawah. */
async function checkAtRest(page: Page, name: string, mode: Mode, out: string[]): Promise<void> {
  await page.evaluate(() => window.scrollTo(0, 0))
  await page.waitForTimeout(150)
  for (const issue of await coveredControls(page, mode)) out.push(`${name} [atas] ${issue}`)
  await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight))
  await page.waitForTimeout(150)
  for (const issue of await coveredControls(page, mode)) out.push(`${name} [bawah] ${issue}`)
}

async function settle(page: Page): Promise<void> {
  await page.waitForLoadState('networkidle')
  await page.waitForTimeout(300)
}

const BOTH: Mode = { bubble: true, clock: true }

for (const vp of VIEWPORTS) {
  test.describe(`${vp.width}x${vp.height} — elemen mengambang tidak menutupi kontrol saat diam`, () => {
    test.use({ viewport: vp })

    test('18 form, 18 monitor, 18 preview (daftar + detail draf), beranda & pengaturan — Operator', async ({ page }) => {
      test.setTimeout(240_000)
      const problems = watchProblems(page)
      const covered: string[] = []
      await setFloatingMode(page, BOTH)
      await login(page, USERS.operator)
      // Simpan/Write-through tidak dipicu; cegah sinkron latar belakang
      // menulis ke backend dev.
      await page.route('**/api/*-records**', (route) => route.abort())

      for (const path of ['/home', '/stations', '/settings/password']) {
        await page.goto(path)
        await settle(page)
        await checkAtRest(page, path, BOTH, covered)
      }

      for (const s of STATIONS) {
        await page.goto(`/stations/${s}/monitor`)
        await settle(page)
        await checkAtRest(page, `monitor ${s}`, BOTH, covered)

        await page.getByTestId('new-data-button').click()
        await page.waitForURL(new RegExp(`/stations/${s}/form/[^/]+$`))
        await settle(page)
        const draftId = page.url().split('/').pop() as string
        await checkAtRest(page, `form ${s}`, BOTH, covered)

        await page.goto(`/stations/${s}/preview`)
        await settle(page)
        await checkAtRest(page, `preview ${s}`, BOTH, covered)

        await page.goto(`/stations/${s}/preview/${draftId}`)
        await settle(page)
        await checkAtRest(page, `preview-detail ${s}`, BOTH, covered)
      }

      expect(covered, covered.join('\n')).toEqual([])
      expect(problems.filter((p) => !p.includes('net::ERR_FAILED')), problems.join('\n')).toEqual([])
    })

    test('Dashboard Reporting, Pilih Stasiun Laporan & 5 laporan — Supervisor', async ({ page }) => {
      test.setTimeout(120_000)
      const problems = watchProblems(page)
      const covered: string[] = []
      await setFloatingMode(page, BOTH)
      await login(page, USERS.supervisor)

      for (const path of ['/dashboard-reporting', '/reports', ...REPORTS.map((r) => `/reports/${r}`)]) {
        await page.goto(path)
        await settle(page)
        await checkAtRest(page, path, BOTH, covered)
      }

      expect(covered, covered.join('\n')).toEqual([])
      expect(problems, problems.join('\n')).toEqual([])
    })

    for (const mode of [{ bubble: true, clock: false }, { bubble: false, clock: true }] as Mode[]) {
      test(`18 form — hanya ${mode.bubble ? 'bubble' : 'jam'} aktif`, async ({ page }) => {
        test.setTimeout(120_000)
        const problems = watchProblems(page)
        const covered: string[] = []
        await setFloatingMode(page, mode)
        await login(page, USERS.operator)
        await page.route('**/api/*-records**', (route) => route.abort())

        for (const s of STATIONS) {
          await page.goto(`/stations/${s}/monitor`)
          await settle(page)
          await page.getByTestId('new-data-button').click()
          await page.waitForURL(new RegExp(`/stations/${s}/form/[^/]+$`))
          await settle(page)
          await checkAtRest(page, `form ${s}`, mode, covered)
        }

        expect(covered, covered.join('\n')).toEqual([])
        expect(problems.filter((p) => !p.includes('net::ERR_FAILED')), problems.join('\n')).toEqual([])
      })
    }
  })
}
