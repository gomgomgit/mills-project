# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: data-preview-cages-track.spec.ts >> Data Preview Cages Track (screen-015) >> Lihat Data Cages Track Tersimpan — success
- Location: tests/e2e/data-preview-cages-track.spec.ts:6:3

# Error details

```
Test timeout of 30000ms exceeded.
```

```
Error: locator.click: Test timeout of 30000ms exceeded.
Call log:
  - waiting for getByRole('button', { name: 'Mulai Input Baru' })

```

# Page snapshot

```yaml
- main [ref=f1e3]:
  - generic [ref=f1e4]:
    - generic [ref=f1e5]: Mill Smart Log
    - button "Buka menu navigasi" [ref=f1e10] [cursor=pointer]
  - generic [ref=f1e12]:
    - navigation "Breadcrumb" [ref=f1e13]:
      - button "Home" [ref=f1e14] [cursor=pointer]
      - generic [ref=f1e15]: /
      - button "Production Process Activity" [ref=f1e16] [cursor=pointer]
      - generic [ref=f1e17]: /
      - generic [ref=f1e18]: Cages Track
    - heading "Monitor Cages Track" [level=1] [ref=f1e19]
  - region "Hari Ini" [ref=f1e20]:
    - heading "Hari Ini" [level=2] [ref=f1e21]
    - generic [ref=f1e22]:
      - generic [ref=f1e23]:
        - generic [ref=f1e24]: Jumlah Cages Track
        - generic [ref=f1e25]: "0"
      - generic [ref=f1e26]:
        - generic [ref=f1e27]: Jumlah Cage/Lori Tercatat
        - generic [ref=f1e28]: "0"
  - region "Daftar Draft Cages Track" [ref=f1e29]:
    - paragraph [ref=f1e30]: Belum ada draft cages track tersimpan.
  - generic [ref=f1e31]:
    - generic [ref=f1e32]:
      - button "Back" [ref=f1e33] [cursor=pointer]
      - button "Load Data" [ref=f1e34] [cursor=pointer]
    - button "New Data" [ref=f1e35] [cursor=pointer]
```

# Test source

```ts
  1  | import { test, expect } from '@playwright/test'
  2  | import { login } from './helpers'
  3  | 
  4  | // screen-015--data-preview-cages-track / usecase-015--data-preview-cages-track
  5  | test.describe('Data Preview Cages Track (screen-015)', () => {
  6  |   test('Lihat Data Cages Track Tersimpan — success', async ({ page }) => {
  7  |     await login(page)
  8  |     await page.goto('/stations/cages-track/monitor')
> 9  |     await page.getByRole('button', { name: 'Mulai Input Baru' }).click()
     |                                                                  ^ Error: locator.click: Test timeout of 30000ms exceeded.
  10 |     await page.waitForURL(/\/stations\/cages-track\/form\/(.+)/)
  11 |     const recordId = page.url().split('/').pop()
  12 | 
  13 |     await page.getByLabel('No. Cages Track').fill('CT-PREVIEW-01')
  14 |     await page.getByLabel('Tanggal').fill('2026-08-18')
  15 |     await page.getByTestId('cages-tipped-time-add').click()
  16 |     await page.getByTestId('cages-tipped-time-row').last().locator('input[type="text"]').fill('C-01')
  17 |     await page.getByTestId('cages-tipped-time-row').last().locator('input[type="time"]').fill('08:00')
  18 |     await page.getByRole('button', { name: 'Simpan' }).click()
  19 |     await page.waitForURL('**/stations/cages-track/monitor')
  20 | 
  21 |     await page.goto(`/stations/cages-track/preview/${recordId}`)
  22 |     await expect(page.getByLabel('No. Cages Track')).toHaveValue('CT-PREVIEW-01')
  23 |     await expect(page.getByLabel('No. Cages Track')).toBeDisabled()
  24 |     await expect(page.getByTestId('tipped-time-rows-list')).toBeVisible()
  25 | 
  26 |     await page.getByRole('button', { name: 'Back' }).click()
  27 |     await page.waitForURL('**/stations/cages-track/monitor')
  28 |   })
  29 | 
  30 |   test('Lihat Data Cages Track Tersimpan — Record Tidak Ditemukan', async ({ page }) => {
  31 |     await login(page)
  32 |     await page.goto('/stations/cages-track/preview/does-not-exist')
  33 | 
  34 |     await expect(page.getByTestId('record-not-found')).toBeVisible()
  35 |     await page.getByRole('button', { name: 'Back' }).click()
  36 |     await page.waitForURL('**/stations/cages-track/monitor')
  37 |   })
  38 | })
  39 | 
```