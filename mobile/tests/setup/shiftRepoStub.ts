import { vi } from 'vitest'

/**
 * Stub global untuk `@/services/shiftRepo`.
 *
 * ── MASALAH YANG DITUTUPNYA ─────────────────────────────────────────────
 *
 * Sejak 2026-10-16 ke-18 `*RecordRepo.saveDraft()` menyetempel `shift_id`,
 * dan stempel itu membaca dua kali dari basis data lokal (stasiun record,
 * lalu jadwal line-nya). Dua puluh berkas spec repo men-stub
 * `@/services/localDb` dengan antrian `mockResolvedValueOnce` — artinya
 * jawabannya ditentukan oleh URUTAN PANGGILAN, bukan oleh SQL-nya. Dua
 * pembacaan baru di tengah alur menggeser seluruh antrian itu, dan 164 test
 * gagal bukan karena ada yang rusak melainkan karena nomor antriannya
 * berubah.
 *
 * ── MENGAPA DI-STUB, BUKAN DIPERBAIKI DI 20 BERKAS ──────────────────────
 *
 * Menyesuaikan antrian di 20 berkas berarti MEMPERDALAM ketergantungan pada
 * urutan panggilan — tepat sifat yang membuat perubahan sekecil ini merusak
 * 164 test. Stub ini memisahkan kepentingannya: spec repo menguji repo-nya,
 * dan aturan shift diuji di tempat aturan itu tinggal.
 *
 * ── APA YANG HILANG KARENANYA, DAN DI MANA IA DIGANTI ───────────────────
 *
 * Dengan stub ini tak satu pun spec repo mengeksekusi stempel yang
 * sebenarnya, jadi stub ini TIDAK membuktikan apa pun tentang shift — dan
 * tidak boleh dikira begitu. Penggantinya dua, dan keduanya ada:
 *
 *   1. tests/shiftRepo.spec.ts menguji aturannya SUNGGUHAN lewat
 *      `vi.importActual`, terhadap jadwal yang ditanam eksplisit.
 *   2. tests/shiftStampWiring.spec.ts membaca SUMBER ke-18 repo dan
 *      menuntut masing-masing memanggil stempelnya dengan nama tabelnya
 *      sendiri — sehingga menghapus satu panggilan, atau menyalin nama tabel
 *      yang keliru dari repo tetangga, tetap memerahkan suite.
 */
vi.mock('@/services/shiftRepo', () => {
  const stampShiftOnRecord = vi.fn(async () => null)
  const resolveShiftIdForLine = vi.fn(async () => null)
  const resolveShiftIdForStation = vi.fn(async () => null)
  const fetchAndCacheShiftsForProductionLine = vi.fn(async () => undefined)
  const getCachedShifts = vi.fn(async () => [])
  const shiftStampMoment = vi.fn(() => '2026-10-16T08:00:00')

  return {
    stampShiftOnRecord,
    resolveShiftIdForLine,
    resolveShiftIdForStation,
    fetchAndCacheShiftsForProductionLine,
    getCachedShifts,
    shiftStampMoment,
    default: {
      stampShiftOnRecord,
      resolveShiftIdForLine,
      resolveShiftIdForStation,
      fetchAndCacheShiftsForProductionLine,
      getCachedShifts,
      shiftStampMoment,
    },
  }
})
