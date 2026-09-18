/**
 * Dashboard (screen-025--dashboard-web) — browser spec.
 *
 * The dashboard shows only the daily mill report on dummy figures; the
 * filterable station KPI block was removed 2026-09-17.
 */

import { test, expect } from '@playwright/test'
import { login } from './support/auth'

test('berhasil: /dashboard shows the daily mill report', async ({ page }) => {
  await login(page, 'stest-supervisor01')
  await page.goto('/dashboard', { waitUntil: 'domcontentloaded' })

  await expect(page.getByTestId('daily-mill-report')).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Dashboard Operasional Mill' })).toBeVisible()
  await expect(page.locator('[data-testid="dash-card-weighbridge"]')).toHaveCount(0)
})

test('tabel lengkap: the full report tables expand on click', async ({ page }) => {
  await login(page, 'stest-supervisor01')
  await page.goto('/dashboard', { waitUntil: 'domcontentloaded' })

  const details = page.locator('details.md-details')
  await expect(details.getByRole('heading', { name: 'Product Arrival' })).toBeHidden()
  await details.locator('summary').click()
  await expect(details.getByRole('heading', { name: 'Product Arrival' })).toBeVisible()
})

test('phone width: no horizontal page scroll', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await login(page, 'stest-supervisor01')
  await page.goto('/dashboard', { waitUntil: 'domcontentloaded' })

  await expect(page.getByTestId('daily-mill-report')).toBeVisible()
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)
  expect(overflow).toBeLessThanOrEqual(0)
})
