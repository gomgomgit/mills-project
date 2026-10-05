import { test, expect } from '@playwright/test'
import { login, USERS } from './helpers'

// Audit keamanan 2026-10-05 — Logout mobile tidak pernah mencabut token
// Sanctum: authStore.logout() memanggil POST /api/logout, tetapi route itu
// tidak ada di backend (404, ditelan best-effort), sehingga token yang
// tersimpan tetap berlaku setelah Logout dan bisa dipakai ulang.
//
// Bukti di browser sungguhan (Vite :5174 → backend dev :8000): login lewat
// LoginForm, token dipakai sukses, Logout dari menu, POST /api/logout harus
// dijawab 200, lalu token lama yang diputar ulang di luar aplikasi (request
// Playwright, setara curl) harus ditolak 401.

const API = 'http://localhost:8000'

test('Logout mencabut token Sanctum perangkat — token lama yang diputar ulang ditolak 401', async ({ page, request }) => {
  const problems: string[] = []
  page.on('pageerror', (e) => problems.push(`pageerror: ${e.message}`))
  page.on('console', (m) => m.type() === 'error' && problems.push(`console.error: ${m.text()}`))
  page.on('response', (r) => r.status() >= 400 && problems.push(`HTTP ${r.status()} ${r.url()}`))

  await login(page, USERS.operator)
  const token = await page.evaluate(() => localStorage.getItem('msl_auth_token'))
  expect(token).toBeTruthy()

  const headers = { Authorization: `Bearer ${token}`, Accept: 'application/json' }
  expect((await request.get(`${API}/api/grading-parameters`, { headers })).status()).toBe(200)

  const logoutResponse = page.waitForResponse((r) => r.url().endsWith('/api/logout') && r.request().method() === 'POST')
  await page.getByTestId('hamburger-button').click()
  await page.getByTestId('nav-menu-logout').click()
  expect((await logoutResponse).status()).toBe(200)
  await page.waitForURL('**/login')
  expect(await page.evaluate(() => localStorage.getItem('msl_auth_token'))).toBeNull()

  const replay = await request.get(`${API}/api/grading-parameters`, { headers })
  expect(replay.status()).toBe(401)
  expect(problems, problems.join('\n')).toEqual([])
})
