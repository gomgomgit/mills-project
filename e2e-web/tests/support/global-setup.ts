import { artisan, BACKEND_ENV } from './backend'
import { APP_ORIGIN } from './base-url'

/**
 * Dua hal, sekali sebelum seluruh run (2026-10-04).
 *
 * 1. PENJAGA: server yang diuji HARUS server environment e2e. Suite ini
 *    menulis ke database yang dilayani servernya (master data faker, record
 *    stasiun, periode, akun fixture), jadi menjalankannya terhadap server dev
 *    :8000 mengotori database dev — persis yang terjadi sampai hari ini
 *    (282 baris Weighbridge bertahun 7278 di mill_smart_log).
 *
 *    PENJAGANYA KINI MENANYAI SERVERNYA, BUKAN BERKAS KONFIGURASINYA
 *    (2026-10-06). Versi sebelumnya hanya membandingkan APP_URL dari
 *    backend/.env.e2e dengan baseURL suite. Keduanya bisa cocok sementara yang
 *    MENJAWAB di port itu adalah server lain, dan itu terjadi: `php artisan
 *    serve` tanpa `--env` menaikkan portnya sendiri ketika 8000 terpakai,
 *    sehingga server environment `local` (database dev) menempati :8001 — port
 *    milik suite. Penjaganya lolos, satu run 16,7 menit mengarah ke server
 *    dev, dan ke-24 test-nya gagal sebagai TIMEOUT di login tanpa satu pun
 *    pesan yang menyebut sebabnya. Sejak hari ini identitas server dibaca
 *    LEWAT HTTP dari GET /api/e2e/identity — rute yang HANYA terdaftar di
 *    environment e2e (routes/api.php), jadi server yang salah menjawab 404
 *    dan keberadaan rute itu sendirilah buktinya.
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

  // Pemeriksaan SEKUNDER, dan sengaja tetap ada meski bukan lagi penjaga
  // utamanya: SANCTUM_STATEFUL_DOMAINS diturunkan dari APP_URL, jadi APP_URL
  // yang tidak sama dengan baseURL membuat setiap permintaan /api/* stateful
  // dijawab 401 — gejala yang sama sekali tidak menyebut port.
  if (backendOrigin !== APP_ORIGIN) {
    throw new Error(
      `[setup] baseURL suite ${APP_ORIGIN} tidak sama dengan APP_URL backend/.env.e2e (${backendOrigin}). `
      + 'Jalankan `npm run serve` (php artisan serve --env=e2e --port=8001), atau kalau port itu '
      + 'sedang dipakai proses lain, jalankan server e2e di port lain dan sertakan APP_URL + '
      + 'SANCTUM_STATEFUL_DOMAINS port itu bersama E2E_WEB_BASE_URL.',
    )
  }

  // PENJAGA UTAMA: siapa yang sebenarnya MENJAWAB di APP_ORIGIN. Dibaca lewat
  // HTTP, bukan dari konfigurasi, karena justru di situ kedua hal itu bisa
  // berbeda — lihat catatan (1) di atas.
  let identity: Response

  try {
    identity = await fetch(`${APP_ORIGIN}/api/e2e/identity`)
  } catch {
    throw new Error(`[setup] tidak ada server di ${APP_ORIGIN}. Jalankan \`npm run serve\` dulu.`)
  }

  if (!identity.ok) {
    throw new Error(
      `[setup] server yang menjawab di ${APP_ORIGIN} BUKAN server e2e: GET /api/e2e/identity `
      + `menjawab ${identity.status}. Rute itu hanya terdaftar di environment e2e, jadi port ini `
      + 'sedang dipegang server lain — paling sering `php artisan serve` tanpa `--env` yang '
      + 'menaikkan portnya sendiri ketika 8000 terpakai, dan server itu memakai database DEV. '
      + 'Matikan proses itu atau jalankan server e2e di port lain (lihat README.md "Running them").',
    )
  }

  const served = await identity.json() as { env: string; database: string }

  if (served.env !== BACKEND_ENV || !String(served.database).endsWith('_e2e')) {
    throw new Error(
      `[setup] server di ${APP_ORIGIN} melayani env="${served.env}" database="${served.database}". `
      + 'Suite ini hanya berjalan terhadap database *_e2e.',
    )
  }

  // Fixture ditanam lewat CLI (artisan db:seed di bawah) sementara test
  // membacanya lewat HTTP. Dua jalur itu wajib menunjuk database yang sama,
  // atau seeding berhasil di satu tempat dan test membaca tempat lain.
  if (served.database !== backend.db) {
    throw new Error(
      `[setup] jalur CLI dan jalur HTTP menunjuk database berbeda: artisan --env=${BACKEND_ENV} `
      + `memakai "${backend.db}", sedangkan server di ${APP_ORIGIN} melayani "${served.database}". `
      + 'Seeder akan menanam fixture di database yang tidak dibaca test.',
    )
  }

  console.log('[setup] backend env=%s database=%s url=%s (identitas dibaca dari servernya)',
    served.env, served.database, backendOrigin)

  if (process.env.E2E_SKIP_SEED !== '1') {
    await artisan(['db:seed', '--force', '--class=BrowserTestFixtureSeeder'])
    console.log('[setup] BrowserTestFixtureSeeder dijalankan ulang')
  }
}
