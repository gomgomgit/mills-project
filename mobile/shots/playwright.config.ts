/**
 * Konfigurasi tersendiri untuk tangkapan layar bahan sales deck — sisi MOBILE.
 * Tanpa setup/teardown dan tanpa `catat()`: ini bukan putaran QA, jadi tidak
 * boleh menulis apa pun ke hasil-mobile.jsonl.
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
    baseURL: 'http://localhost:5174',
    viewport: { width: 390, height: 844 },   // lebar ponsel sebenarnya
    deviceScaleFactor: 3,                    // tajam untuk slide
    isMobile: true,
    hasTouch: true,
    ignoreHTTPSErrors: true,
  },
})
