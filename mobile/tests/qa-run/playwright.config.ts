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
    trace: 'retain-on-failure',
  },
  projects: [{
    name: 'chromium',
    use: {
      ...devices['Desktop Chrome'],
      // Lebar acuan mobile §1.1 dokumen QA. HARUS di dalam `use` project ini,
      // SESUDAH sebaran devices[...]: setelan project menimpa setelan
      // tingkat-atas, jadi viewport yang ditaruh di atas akan dikalahkan oleh
      // 1280px milik Desktop Chrome — dan seluruh kasus tata letak mobile akan
      // diuji pada lebar desktop tanpa ada yang terlihat salah.
      viewport: { width: 390, height: 844 },
      isMobile: false,
    },
  }],
})
