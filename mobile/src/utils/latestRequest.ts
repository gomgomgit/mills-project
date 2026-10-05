/**
 * Penjaga "hanya respons terbaru yang berlaku" (audit 2026-10-05).
 *
 * Layar Laporan mobile memuat ringkasan setiap kali periode/line/mill
 * berganti. Tanpa urutan, permintaan LAMA yang tiba belakangan menimpa
 * ringkasan BARU — angka periode A tampil di bawah pilihan periode B — dan
 * `finally`-nya mematikan indikator muat milik permintaan baru.
 *
 * Pemakaian:
 *   const isLatest = summaryRequests.next()   // sebelum await
 *   const result = await fetch...
 *   if (!isLatest()) return                   // respons basi: abaikan
 *
 * `invalidate()` membatalkan permintaan yang sedang berjalan tanpa memulai
 * yang baru (mis. pilihan periode dikosongkan).
 */
export interface LatestRequestGuard {
  next(): () => boolean
  invalidate(): void
}

export function createLatestRequestGuard(): LatestRequestGuard {
  let current = 0

  return {
    next() {
      const id = ++current
      return () => id === current
    },
    invalidate() {
      current++
    },
  }
}
