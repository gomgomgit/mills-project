/**
 * Config pelaksana kasus uji QA mobile, terpisah dari suite regresi
 * (../../playwright.config.ts memakai testDir './tests/e2e', jadi berkas di
 * sini TIDAK ikut berjalan di sana).
 *
 * PENTING — VITE_API_BASE_URL: aplikasi mobile menulis ke backend yang
 * ditunjuk variabel itu, dan .env repo ini menunjuk :8000 yaitu basis data
 * DEV. Pelaksana QA ini membuat dan mengubah data, jadi servernya harus
 * dijalankan dengan VITE_API_BASE_URL=http://localhost:8001 (backend e2e)
 * sebelum suite ini dipakai:
 *
 *     cd mobile && VITE_API_BASE_URL=http://localhost:8001 npm run dev
 */
import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: '.',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  reporter: [['list']],
  // Satu kasus QA menjalankan SELURUH langkah yang tertulis — berganti peran,
  // membuat lalu menghapus draft, menelusuri daftar. 30 detik bawaan terlalu
  // pendek, dan batas waktu yang kena di tengah langkah terbaca seperti cacat
  // aplikasi.
  timeout: 180_000,
  use: {
    baseURL: 'http://localhost:5174',
    // Lebar acuan mobile menurut §1.1 dokumen QA.
    viewport: { width: 390, height: 844 },
    trace: 'retain-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
})
