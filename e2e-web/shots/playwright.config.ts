/**
 * Konfigurasi TERSENDIRI untuk tangkapan layar bahan sales deck.
 *
 * Sengaja TIDAK memakai e2e-web/playwright.config.ts: konfigurasi itu punya
 * globalSetup yang menjalankan BrowserTestFixtureSeeder, dan fixture itu
 * menyuntikkan entitas bernama "Mill Setting Uji" / "Mill Kosong" / akun
 * brtest-* ke dalam basis data. Untuk bahan jualan, entitas seperti itu
 * TIDAK boleh muncul di pemilih mill maupun di papan master data — jadi di
 * sini tidak ada setup, tidak ada teardown, dan tidak ada seeder.
 */
import { defineConfig } from '@playwright/test'

export default defineConfig({
  testDir: '.',
  timeout: 90_000,
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: 'line',
  use: {
    baseURL: process.env.APP_ORIGIN ?? 'http://localhost:8001',
    viewport: { width: 1440, height: 900 },
    deviceScaleFactor: 2,
    ignoreHTTPSErrors: true,
  },
})
