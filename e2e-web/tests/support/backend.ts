import { execFile } from 'node:child_process'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { promisify } from 'node:util'

const execFileAsync = promisify(execFile)

export const BACKEND_DIR = resolve(dirname(fileURLToPath(import.meta.url)), '../../../backend')

/**
 * Environment Laravel yang dilayani server e2e (`php artisan serve
 * --env=e2e --port=8001`). Setiap perintah artisan dari suite ini WAJIB
 * memakai environment yang sama — tanpa `--env`, artisan memuat .env biasa
 * dan menyentuh database DEV, bukan database yang sedang diuji.
 */
export const BACKEND_ENV = process.env.E2E_BACKEND_ENV ?? 'e2e'

/** Menjalankan `php artisan ...` di backend/ dengan --env suite ini. */
export async function artisan(args: string[]): Promise<string> {
  const { stdout } = await execFileAsync('php', ['artisan', ...args, `--env=${BACKEND_ENV}`], {
    cwd: BACKEND_DIR,
  })

  return stdout
}

/**
 * Menyapu record stasiun DAN Periode Pelaporan di rentang lajur
 * (tests/support/period-lanes.ts, 1970-2019) lewat `e2e:prune-records`.
 *
 * Dipanggil spec laporan-* di awal beforeAll dan di afterAll, dan sekali
 * lagi oleh globalTeardown. Kenapa tidak cukup deletePeriodsByPrefix():
 * periode yang sudah berisi record ditolak 409 PERIOD_HAS_RECORDS, dan
 * record stasiun memang tidak punya jalur hapus di aplikasi. Tanpa sapuan
 * per spec, periode lajur menumpuk sepanjang satu run dan mendorong periode
 * baru spec berikutnya ke halaman 2 daftar (20 baris, start_date DESC —
 * periode lajur yang di masa lalu selalu di paling bawah).
 *
 * Perintahnya sendiri menolak berjalan di luar environment e2e berdatabase
 * *_e2e. Kegagalan hanya dicatat: perapian yang gagal bukan kegagalan produk.
 */
export async function pruneLaneData(label: string): Promise<void> {
  try {
    const stdout = await artisan(['e2e:prune-records', '--force'])
    console.log('[cleanup] %s: %s', label, stdout.trim().split('\n').at(-1) ?? '')
  } catch (error) {
    console.warn('[cleanup] %s: e2e:prune-records gagal:', label, error)
  }
}
