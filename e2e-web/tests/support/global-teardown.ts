import { execFile } from 'node:child_process'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { promisify } from 'node:util'

const execFileAsync = promisify(execFile)

const BACKEND_DIR = resolve(dirname(fileURLToPath(import.meta.url)), '../../../backend')

/**
 * Menghapus record stasiun yang ditanam suite ini, sekali setelah seluruh
 * run selesai.
 *
 * KENAPA LEWAT ARTISAN, BUKAN LEWAT UI seperti deletePeriodsByPrefix().
 * Aplikasi ini sengaja tidak punya jalur hapus untuk record stasiun — tidak
 * di data browser, tidak di layar detail, tidak di komponen Livewire mana
 * pun. Sebuah log sheet yang sudah masuk tidak boleh lenyap, dan itu
 * keputusan produk, bukan kelalaian. Jadi pembersihan test tidak dapat
 * meniru pola periode, yang bekerja dengan mengklik tombol hapus sungguhan.
 *
 * KENAPA TEARDOWN GLOBAL, BUKAN afterAll PER SPEC. Perintahnya menyapu
 * seluruh tabel record sekaligus, jadi memanggilnya dari lima afterAll
 * berarti lima proses PHP yang empat di antaranya tidak menemukan apa-apa.
 * Sekali di akhir run sudah cukup, dan tetap menutup spec mana pun yang
 * kelak ikut menanam record tanpa perlu diingat untuk menambah pemanggilan.
 *
 * APA YANG DIHAPUS. Hanya record bertanggal tahun 2600 ke atas. Spec laporan
 * menanam datanya di tahun 2600/2700/2800 (lihat RUN_OFFSET di
 * laporan-*.spec.ts) justru supaya tidak bertabrakan dengan data pabrik,
 * dan jarak ratusan tahun itulah yang membuat penghapusan ini mustahil
 * salah sasaran. Data dev yang sungguhan ada di tahun berjalan dan tidak
 * pernah tersentuh. Perintahnya sendiri menolak ambang tahun di bawah 2400
 * dan menolak berjalan di production — lihat app/Console/Commands/
 * PruneE2eRecords.php.
 *
 * KEGAGALAN MEMBERSIHKAN BUKAN KEGAGALAN PRODUK. Sama seperti afterAll
 * periode, kesalahan di sini hanya dicatat. Membuat run merah karena
 * perapian gagal akan menyembunyikan hasil test yang sebenarnya.
 */
export default async function globalTeardown(): Promise<void> {
  try {
    const { stdout } = await execFileAsync(
      'php',
      ['artisan', 'e2e:prune-records', '--force'],
      { cwd: BACKEND_DIR },
    )

    const summary = stdout.trim().split('\n').at(-1) ?? ''
    console.log('[cleanup] record stasiun: %s', summary)
  } catch (error) {
    console.warn('[cleanup] record stasiun: pembersihan gagal:', error)
  }
}
