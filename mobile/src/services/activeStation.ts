import { query } from '@/services/localDb'

/**
 * activeStation — mencari stasiun yang sedang dipakai operator, supaya sebuah
 * draft tahu DI MANA ia dibuat.
 *
 * ── CACAT YANG DITUTUP BERKAS INI (2026-10-02) ──────────────────────────
 *
 * Sampai hari ini `station_id` pada record lokal SELALU null: tidak satu pun
 * dari 18 `*RecordRepo.createDraft()` mengisinya, dan tidak satu pun
 * `saveDraft()` menyentuhnya. Kolomnya ada di interface; nol yang menulisnya.
 * Itu sudah tercatat sebagai known issue di FormCagesTrackView
 * ("that plumbing does not exist yet ... wiring it up belongs to the Monitor
 * screen's creation flow").
 *
 * Akibatnya Production Line baru menempel SAAT SYNC, dari pilihan yang sedang
 * aktif di Station List (`syncAllRecords(selectedProductionLineId)` meneruskan
 * satu line yang sama untuk SETIAP record pending). Jadi:
 *
 *   - draft yang di-pause di Line 1 lalu disinkronkan sementara pilihan sudah
 *     berpindah ke Line 2 akan tercatat sebagai data LINE 2, dan laporan Line 2
 *     menghitungnya;
 *   - operator yang bekerja di dua line lalu sync sekali menumpuk semuanya ke
 *     satu line;
 *   - dan tidak ada apa pun di data yang merekam di mana ia sebenarnya
 *     dihasilkan.
 *
 * Akibat kedua, yang mati diam-diam: `writeThroughSync.syncAfterSave()` tidak
 * meneruskan line, sehingga ia bersandar pada cadangan
 * `resolveProductionLineId(row.station_id)`. Dengan `station_id` null, cadangan
 * itu mengembalikan null dan push dibatalkan — fitur `immediate_sync_enabled`
 * tidak pernah benar-benar mengirim apa pun, dan kegagalannya memang dirancang
 * SENYAP ("Every failure here is therefore SILENT to the caller"), jadi tidak
 * ada yang akan menyadarinya.
 *
 * ── MENGAPA DI-RESOLVE DI SINI, BUKAN DIOPER DARI 18 VIEW ───────────────
 *
 * Jenis stasiun sudah implisit pada tiap repo, dan line yang dipilih operator
 * sudah dipersistenkan Station List ke localStorage (`msl_production_line_<id>`)
 * — jadi `createDraft()` bisa menurunkan stasiunnya sendiri tanpa mengubah
 * tanda tangan satu pun fungsi dan tanpa menyentuh 18 Monitor view.
 *
 * ── MENGAPA NULL TETAP JAWABAN YANG SAH ─────────────────────────────────
 *
 * Belum ada line yang dipilih, cache stasiun masih kosong (perangkat baru,
 * offline), atau stasiunnya nonaktif → kembalikan null, JANGAN menebak. Null
 * membuat perilakunya persis seperti sebelum berkas ini ada: sync manual tetap
 * memakai line yang dipilih di Station List. Jadi perubahan ini hanya menambah
 * ketepatan, tidak pernah menukar satu kegagalan dengan kegagalan lain —
 * menebak stasiun justru akan menciptakan versi lebih buruk dari cacat yang
 * sedang ditutup.
 */

const REMEMBERED_LINE_PREFIX = 'msl_production_line_'

/**
 * Production Line yang terakhir dipilih operator di Station List.
 *
 * localStorage dibungkus try/catch mengikuti pola floatingClock.ts /
 * aiAssistant.ts / StationListView: pada mode privat penyimpanan bisa melempar,
 * dan gagal mengingat pilihan tidak boleh mematahkan pembuatan draft.
 */
export function readRememberedProductionLineId(userId: string | null | undefined): string | null {
  if (!userId) {
    return null
  }

  try {
    if (typeof window === 'undefined' || !window.localStorage) {
      return null
    }

    return window.localStorage.getItem(`${REMEMBERED_LINE_PREFIX}${userId}`)
  } catch {
    return null
  }
}

/**
 * Stasiun AKTIF berjenis `stationType` pada Production Line yang sedang dipilih
 * operator, dibaca dari cache `station` lokal. Null bila salah satu bagiannya
 * belum ada — lihat "MENGAPA NULL TETAP JAWABAN YANG SAH" di atas.
 *
 * Tidak melempar: pembuatan draft harus tetap berhasil offline, dan sebuah
 * draft tanpa stasiun masih jauh lebih baik daripada operator yang tidak bisa
 * mulai mencatat.
 */
export async function resolveActiveStationId(
  stationType: string,
  userId: string | null | undefined,
): Promise<string | null> {
  const productionLineId = readRememberedProductionLineId(userId)

  if (!productionLineId) {
    return null
  }

  try {
    const rows = await query<{ id: string }>(
      `SELECT id FROM station
       WHERE production_line_id = ? AND type = ? AND is_active = 1
       LIMIT 1`,
      [productionLineId, stationType],
    )

    return (rows ?? [])[0]?.id ?? null
  } catch {
    return null
  }
}

export default { readRememberedProductionLineId, resolveActiveStationId }
