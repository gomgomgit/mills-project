import { artisan, BACKEND_ENV } from './backend'
import { APP_ORIGIN } from './base-url'

/**
 * Dua hal, sekali sebelum seluruh run (2026-10-04).
 *
 * 1. PENJAGA: server yang diuji HARUS server environment e2e. Suite ini
 *    menulis ke database yang dilayani servernya (master data faker, record
 *    stasiun, periode, akun fixture), jadi menjalankannya terhadap server dev
 *    :8000 mengotori database dev — persis yang terjadi sampai hari ini
 *    (282 baris Weighbridge bertahun 7278 di mill_smart_log). APP_URL dari
 *    backend/.env.e2e dibandingkan dengan baseURL suite; berbeda = berhenti
 *    sebelum satu test pun jalan.
 *
 * 2. FIXTURE DIPULIHKAN setiap run: `db:seed --class=BrowserTestFixtureSeeder`
 *    (idempoten). Banyak skenario MENGUBAH fixture-nya sendiri — mengganti
 *    nama record *-BROWSER-EDIT, menonaktifkan akun, menutup stasiun periode
 *    fixture — dan seeder itulah yang mengembalikannya. Dengan ini run kedua
 *    tanpa reset database (tanpa scripts/prepare-db.sh) tetap berjalan dari
 *    keadaan fixture yang sama. Reset penuh tetap lewat prepare-db.sh.
 *
 * Set E2E_SKIP_SEED=1 untuk melewati langkah 2 saat menjalankan satu spec
 * berulang-ulang.
 */
export default async function globalSetup(): Promise<void> {
  const config = (await artisan([
    'tinker',
    '--no-interaction',
    "--execute=echo json_encode(['env' => app()->environment(), 'url' => config('app.url'), 'db' => config('database.connections.'.config('database.default').'.database')]).PHP_EOL;",
  ])).trim().split('\n').at(-1) ?? '{}'

  const backend = JSON.parse(config) as { env: string; url: string; db: string }
  const backendOrigin = new URL(backend.url).origin

  if (backend.env !== BACKEND_ENV || !String(backend.db).endsWith('_e2e')) {
    throw new Error(
      `[setup] --env=${BACKEND_ENV} menunjuk env="${backend.env}" database="${backend.db}". `
      + 'Suite ini hanya berjalan terhadap database *_e2e — buat backend/.env.e2e dari .env.e2e.example.',
    )
  }

  if (backendOrigin !== APP_ORIGIN) {
    throw new Error(
      `[setup] baseURL suite ${APP_ORIGIN} bukan server e2e (APP_URL backend/.env.e2e = ${backendOrigin}). `
      + 'Jalankan `npm run serve` (php artisan serve --env=e2e --port=8001) dan jangan arahkan '
      + 'E2E_WEB_BASE_URL ke server dev :8000.',
    )
  }

  // Server di APP_ORIGIN harus benar-benar hidup — kalau tidak, setiap spec
  // gagal di navigasi pertama dengan pesan yang tidak menyebut sebabnya.
  try {
    await fetch(`${APP_ORIGIN}/api/health`)
  } catch {
    throw new Error(`[setup] tidak ada server di ${APP_ORIGIN}. Jalankan \`npm run serve\` dulu.`)
  }

  console.log('[setup] backend env=%s database=%s url=%s', backend.env, backend.db, backendOrigin)

  if (process.env.E2E_SKIP_SEED !== '1') {
    await artisan(['db:seed', '--force', '--class=BrowserTestFixtureSeeder'])
    console.log('[setup] BrowserTestFixtureSeeder dijalankan ulang')
  }
}
