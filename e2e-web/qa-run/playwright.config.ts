/**
 * Config untuk PELAKSANA kasus uji manual, terpisah dari suite regresi.
 *
 * Suite regresi (../playwright.config.ts) memakai testDir './tests', jadi
 * berkas di qa-run/ TIDAK ikut berjalan di sana — dan itu disengaja: ini
 * bukan uji regresi, ini satu putaran pengujian yang hasilnya dicatat.
 *
 * baseURL dan alasan port-nya sama dengan suite regresi; keduanya membaca
 * E2E_WEB_BASE_URL supaya tidak ada port yang dipaku di dua tempat.
 */
import { defineConfig, devices } from '@playwright/test'
import { APP_ORIGIN } from '../tests/support/base-url'

export default defineConfig({
  testDir: '.',
  fullyParallel: false,
  // Satu worker: pelaksana ini LOGIN BERGANTI PERAN dalam satu test dan
  // membuat data (MD-KEL-04). Dua worker akan saling menimpa sesi dan baris.
  workers: 1,
  retries: 0,
  reporter: [['list']],
  use: {
    baseURL: APP_ORIGIN,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
})
