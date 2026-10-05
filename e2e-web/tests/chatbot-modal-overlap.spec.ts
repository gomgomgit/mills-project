import { test, expect, type Page } from '@playwright/test'
import { login, PASSWORD } from './support/auth'
import { watchProblems } from './support/page-health'

/**
 * Audit 2026-10-05 — di 390px tombol mengambang chatbot Mills AI
 * (components/chatbot-widget, z-index 1000, kanan-bawah) menimpa tepi kanan
 * tombol Simpan modal Kelola (x-modal, backdrop z-index 50): tombol yang
 * paling penting di modal sebagian tidak bisa diketuk.
 *
 * Perbaikan: selama ada modal (.kcm-modal-backdrop) di halaman, peluncur
 * chatbot disembunyikan (modal ber-aria-modal — halaman di belakangnya
 * memang tidak boleh dioperasikan). Setelah modal ditutup, peluncur kembali.
 *
 * Pemeriksaan per modal di 390x844: setiap kontrol di dalam modal adalah
 * elemen teratas di titik tengahnya DAN tidak beririsan dengan peluncur
 * yang tampak; ditambah tanpa pageerror / console.error / HTTP >= 400.
 */

const ADMIN = 'brtest-admin01'

async function coveredInModal(page: Page): Promise<string[]> {
  return page.evaluate(() => {
    const issues: string[] = []
    const modal = document.querySelector('.kcm-modal')
    if (!modal) return ['modal tidak terbuka']
    const launcher = document.querySelector('.chatbot-widget')
    const lr = launcher && getComputedStyle(launcher).display !== 'none' ? launcher.getBoundingClientRect() : null
    modal.querySelectorAll('button, input, select, textarea, a[href], [role="combobox"]').forEach((el) => {
      const r = el.getBoundingClientRect()
      if (!r.width || !r.height) return
      if (r.bottom <= 0 || r.top >= window.innerHeight) return // di luar layar (body modal menggulir)
      const label = el.getAttribute('data-testid') || `${el.tagName.toLowerCase()}:${(el.textContent ?? '').trim().slice(0, 20) || el.getAttribute('name') || el.id}`
      if (lr && lr.width && r.left < lr.right && r.right > lr.left && r.top < lr.bottom && r.bottom > lr.top) {
        issues.push(`${label} beririsan dengan peluncur chatbot`)
      }
      const cx = Math.min(Math.max(r.left + r.width / 2, 0), window.innerWidth - 1)
      const cy = Math.min(Math.max(r.top + r.height / 2, 0), window.innerHeight - 1)
      const top = document.elementFromPoint(cx, cy)
      if (top && !el.contains(top) && !top.contains(el) && top.closest('.chatbot-widget')) {
        issues.push(`${label} tertutup chatbot`)
      }
    })
    return issues
  })
}

async function checkModal(page: Page, name: string, out: string[]): Promise<void> {
  await expect(page.locator('.kcm-modal')).toBeVisible()
  // Gulir body modal ke bawah: tombol aksi selalu di footer, tapi pastikan
  // posisi diam awal maupun akhir sama-sama diperiksa.
  for (const issue of await coveredInModal(page)) out.push(`${name}: ${issue}`)
  await page.locator('.kcm-modal__body').evaluate((el) => el.scrollTo(0, el.scrollHeight))
  for (const issue of await coveredInModal(page)) out.push(`${name} (body digulir): ${issue}`)
  if (await page.locator('.chatbot-widget').isVisible()) out.push(`${name}: peluncur chatbot tetap tampil di atas modal`)
}

async function closeModal(page: Page): Promise<void> {
  await page.locator('.kcm-modal__actions').getByRole('button', { name: /Batal|Tutup/ }).first().click()
  await expect(page.locator('.kcm-modal')).toHaveCount(0)
  // Peluncur kembali setelah modal ditutup.
  await expect(page.locator('[data-testid="chatbot-widget-bubble"]')).toBeVisible()
}

const PAGES: Array<{ path: string; title: string; create: string; edit?: string }> = [
  { path: '/master-data/corporates', title: 'Kelola Corporate', create: 'button[wire\\:click="openCreateForm"]', edit: 'button[wire\\:click^="openEditForm("]' },
  { path: '/master-data/companies', title: 'Kelola Company', create: 'button[wire\\:click="openCreateForm"]', edit: 'button[wire\\:click^="openEditForm("]' },
  { path: '/master-data/business-units', title: 'Kelola Business Unit', create: 'button[wire\\:click="openCreateForm"]', edit: 'button[wire\\:click^="openEditForm("]' },
  { path: '/master-data/production-lines', title: 'Kelola Production Line', create: '[data-testid="add-production-line-button"]', edit: 'button[wire\\:click^="openEditForm("]' },
  { path: '/master-data/stations', title: 'Kelola Station', create: 'button[wire\\:click="openCreateForm"]', edit: 'button[wire\\:click^="openEditForm("]' },
  { path: '/master-data/machinery', title: 'Kelola Machinery', create: '[data-testid="add-machinery"]', edit: 'button[wire\\:click^="openEditForm("]' },
  { path: '/master-data/machinery', title: 'Kelola Machinery Group', create: '[data-testid="add-group"]', edit: 'button[wire\\:click^="openEditGroupForm("]' },
  { path: '/master-data/periods', title: 'Kelola Periode', create: '[data-testid="add-period-button"]', edit: 'button[wire\\:click^="openEditForm("]' },
  { path: '/users', title: 'Kelola User', create: 'button[wire\\:click="openCreateForm"]', edit: 'button[wire\\:click^="openEditForm("]' },
]

test.describe('390x844 — peluncur chatbot tidak menutupi tombol modal Kelola', () => {
  test.use({ viewport: { width: 390, height: 844 } })

  for (const p of PAGES) {
    test(`${p.title} — modal Tambah${p.edit ? ' & Edit' : ''}`, async ({ page }) => {
      const problems = watchProblems(page)
      const covered: string[] = []
      await login(page, ADMIN, PASSWORD)
      await page.goto(p.path)
      await expect(page.locator('[data-testid="chatbot-widget-bubble"]')).toBeVisible()

      await page.locator(p.create).first().click()
      await checkModal(page, `${p.title} Tambah`, covered)
      await closeModal(page)

      if (p.edit) {
        const edit = page.locator(p.edit).first()
        if (await edit.count()) {
          await edit.scrollIntoViewIfNeeded()
          await edit.click()
          await checkModal(page, `${p.title} Edit`, covered)
          await closeModal(page)
        } else {
          covered.push(`${p.title}: tidak ada baris untuk modal Edit (fixture)`)
        }
      }

      expect(covered, covered.join('\n')).toEqual([])
      expect(problems, problems.join('\n')).toEqual([])
    })
  }

  test('Detail Periode — modal Edit Periode, Tutup Stasiun & Buka Stasiun', async ({ page }) => {
    const problems = watchProblems(page)
    const covered: string[] = []
    await login(page, ADMIN, PASSWORD)
    await page.goto('/master-data/periods')
    const detailLink = page.locator('a[href*="/master-data/periods/"]').first()
    await detailLink.scrollIntoViewIfNeeded()
    await detailLink.click()
    await page.waitForURL(/\/master-data\/periods\/[^/]+$/)

    await page.locator('button[wire\\:click="openEditForm"]').click()
    await checkModal(page, 'Edit Periode', covered)
    await closeModal(page)

    const askClose = page.locator('button[wire\\:click^="askClose("]').first()
    if (await askClose.count()) {
      await askClose.scrollIntoViewIfNeeded()
      await askClose.click()
      await checkModal(page, 'Tutup Stasiun', covered)
      await page.getByTestId('cancel-close-button').click()
      await expect(page.locator('.kcm-modal')).toHaveCount(0)
    }
    const askOpen = page.locator('button[wire\\:click^="askOpen("]').first()
    if (await askOpen.count()) {
      await askOpen.scrollIntoViewIfNeeded()
      await askOpen.click()
      await checkModal(page, 'Buka Stasiun', covered)
      await page.getByTestId('cancel-open-period').click()
      await expect(page.locator('.kcm-modal')).toHaveCount(0)
    }
    expect(await askClose.count() + await askOpen.count(), 'periode fixture punya stasiun untuk ditutup/dibuka').toBeGreaterThan(0)
    await expect(page.locator('[data-testid="chatbot-widget-bubble"]')).toBeVisible()

    expect(covered, covered.join('\n')).toEqual([])
    expect(problems, problems.join('\n')).toEqual([])
  })
})
