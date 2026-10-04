import { pruneLaneData } from './backend'

/**
 * Menyapu record stasiun dan periode lajur yang ditanam suite ini, sekali
 * setelah seluruh run selesai — lewat `php artisan e2e:prune-records
 * --force --env=e2e` (lihat pruneLaneData() di ./backend.ts).
 *
 * KENAPA LEWAT ARTISAN, BUKAN LEWAT UI seperti deletePeriodsByPrefix().
 * Aplikasi ini sengaja tidak punya jalur hapus untuk record stasiun, dan
 * sejak 2026-10-04 periode yang sudah berisi record juga tidak bisa dihapus
 * (409 PERIOD_HAS_RECORDS). Itu keputusan produk, bukan kelalaian.
 *
 * Spec laporan-* sudah menyapu lajurnya sendiri di beforeAll/afterAll;
 * sapuan di sini menutup run yang terhenti di tengah spec.
 *
 * APA YANG DIHAPUS. Hanya record dan periode bertanggal 1970-01-01 s.d.
 * 2019-12-31 (rentang lajur, tests/support/period-lanes.ts), dan hanya di
 * database e2e — perintahnya menolak environment selain e2e dan database
 * yang namanya tidak berakhiran _e2e (app/Console/Commands/PruneE2eRecords.php).
 * Sisa lain suite ini (akun fixture, master data faker, record form-*)
 * hidup di database e2e yang direset utuh oleh e2e-web/scripts/prepare-db.sh.
 */
export default async function globalTeardown(): Promise<void> {
  await pruneLaneData('teardown')
}
