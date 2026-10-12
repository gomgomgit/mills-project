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
const REMEMBERED_LINE_NAME_PREFIX = 'msl_production_line_name_'
const STATION_CACHE_FRESH_PREFIX = 'msl_station_cache_ok_'

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
 * NAMA Production Line yang sedang dipilih — bukan hanya id-nya.
 *
 * Temuan QA MB-HOME-03: layar Monitor tidak menyebut Production Line sama
 * sekali, jadi operator tidak punya cara tahu line mana yang sedang ia isi.
 * Daftar line TIDAK di-cache lokal (lihat productionLineRepo: ia master data
 * yang ditulis di web dan diambil langsung), jadi id saja tidak dapat
 * diterjemahkan menjadi nama saat perangkat offline — dan Monitor adalah
 * layar yang justru paling sering dipakai offline.
 *
 * Karena itu namanya disimpan berdampingan dengan id-nya, dan ditulis ulang
 * setiap kali Station List memilih line dari daftar yang BARU diambil dari
 * server. Dengan begitu nama yang berubah di web ikut terbarui pada kunjungan
 * Station List berikutnya — satu layar yang memang selalu dilewati sebelum
 * sampai ke Monitor.
 */
export function readRememberedProductionLineName(userId: string | null | undefined): string | null {
  if (!userId) {
    return null
  }

  try {
    if (typeof window === 'undefined' || !window.localStorage) {
      return null
    }

    const nama = window.localStorage.getItem(`${REMEMBERED_LINE_NAME_PREFIX}${userId}`)

    return nama && nama.trim() !== '' ? nama : null
  } catch {
    return null
  }
}

export function rememberProductionLineName(userId: string | null | undefined, name: string): void {
  if (!userId || name.trim() === '') {
    return
  }

  try {
    if (typeof window === 'undefined' || !window.localStorage) {
      return
    }

    window.localStorage.setItem(`${REMEMBERED_LINE_NAME_PREFIX}${userId}`, name)
  } catch {
    // Sama seperti pembacaan di atas: gagal mengingat nama tidak boleh
    // mematahkan apa pun — label Monitor hanya tidak tampil.
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

/**
 * Menandai bahwa daftar stasiun untuk satu Production Line BERHASIL diambil
 * dari server dan tersimpan di cache lokal.
 *
 * Inilah yang membuat "line ini tidak punya stasiun jenis itu" bisa dibedakan
 * dari "kami belum tahu" (lihat activeStationStatus()). Tanpa penanda ini,
 * line yang memang kosong dan perangkat yang belum pernah menyinkronkan
 * stasiun terlihat sama persis: nol baris. Dan karena dua keadaan itu
 * menuntut jawaban yang berlawanan — tolak vs izinkan menyimpan — menebak di
 * antara keduanya berarti memilih satu cacat.
 */
export function tandaiCacheStasiunSegar(productionLineId: string): void {
  try {
    if (typeof window === 'undefined' || !window.localStorage) {
      return
    }

    window.localStorage.setItem(`${STATION_CACHE_FRESH_PREFIX}${productionLineId}`, '1')
  } catch {
    // Gagal menandai hanya berarti penjaga stasiun aktif tetap memilih
    // "tidak diketahui", yaitu sisi yang aman: menyimpan tetap boleh.
  }
}

export function cacheStasiunSegar(productionLineId: string): boolean {
  try {
    if (typeof window === 'undefined' || !window.localStorage) {
      return false
    }

    return window.localStorage.getItem(`${STATION_CACHE_FRESH_PREFIX}${productionLineId}`) === '1'
  } catch {
    return false
  }
}

/**
 * Keadaan stasiun aktif pada Production Line yang sedang dipilih — TIGA
 * keadaan, bukan dua, dan itulah inti perbaikan temuan QA MB-FORM-15.
 *
 * `resolveActiveStationId()` mengembalikan null untuk tiga sebab yang sangat
 * berbeda: belum ada line yang dipilih, cache stasiun masih kosong (perangkat
 * baru / offline), dan line itu memang TIDAK punya stasiun jenis ini yang
 * aktif. Menolak penyimpanan setiap kali hasilnya null akan mematahkan hal
 * yang paling penting dari aplikasi ini — operator harus tetap bisa mencatat
 * tanpa koneksi, bahkan di perangkat yang baru dibersihkan.
 *
 * Karena itu hanya keadaan KETIGA yang ditolak, dan ia dikenali dengan
 * memeriksa apakah line itu punya baris stasiun lain di cache: kalau ada
 * baris tetapi tidak satu pun berjenis ini dalam keadaan aktif, maka
 * jawabannya diketahui dan jawabannya "tidak ada". Kalau line itu tidak punya
 * baris sama sekali, kita memang tidak tahu — dan tidak tahu bukan alasan
 * untuk menghalangi operator.
 */
export type StatusStasiunAktif = 'ada' | 'tidak-aktif' | 'tidak-diketahui'

export async function activeStationStatus(
  stationType: string,
  userId: string | null | undefined,
): Promise<StatusStasiunAktif> {
  const productionLineId = readRememberedProductionLineId(userId)

  if (!productionLineId) {
    return 'tidak-diketahui'
  }

  try {
    const aktif = await query<{ id: string }>(
      `SELECT id FROM station
       WHERE production_line_id = ? AND type = ? AND is_active = 1
       LIMIT 1`,
      [productionLineId, stationType],
    )

    if ((aktif ?? [])[0]) {
      return 'ada'
    }

    // DUA tanda bahwa jawabannya memang DIKETAHUI, salah satu cukup:
    //
    //   1. daftar stasiun line ini pernah berhasil diambil dari server
    //      (ditandai Station List saat line dipilih) — termasuk ketika
    //      jawabannya nol stasiun, yang justru keadaan yang diuji di sini;
    //   2. line ini punya baris stasiun lain di cache, sehingga ketiadaan
    //      stasiun jenis INI bukan soal cache yang masih kosong.
    //
    // Tanda pertama ada karena tanda kedua tidak cukup: line yang benar-benar
    // tanpa stasiun tidak punya baris apa pun, persis seperti perangkat yang
    // belum pernah menyinkronkan — dan keduanya menuntut jawaban berlawanan.
    if (cacheStasiunSegar(productionLineId)) {
      return 'tidak-aktif'
    }

    const adaBarisLain = await query<{ id: string }>(
      'SELECT id FROM station WHERE production_line_id = ? LIMIT 1',
      [productionLineId],
    )

    return (adaBarisLain ?? [])[0] ? 'tidak-aktif' : 'tidak-diketahui'
  } catch {
    return 'tidak-diketahui'
  }
}

/**
 * Jenis stasiun dari NAMA RUTE layar Form (`<slug>-form`).
 *
 * Diturunkan dari rute, bukan dituliskan di masing-masing dari 18 view: satu
 * daftar literal yang disalin 18 kali adalah daftar yang akan menyimpang,
 * dan penjaga yang menyebut jenis stasiun yang salah lebih buruk daripada
 * tidak ada penjaga.
 */
export function stationTypeFromFormRoute(routeName: unknown): string | null {
  if (typeof routeName !== 'string' || !routeName.endsWith('-form')) {
    return null
  }

  const slug = routeName.slice(0, -'-form'.length)

  return slug === '' ? null : slug
}

/** "process-quality-control" -> "Process Quality Control" */
export function stationLabelFromType(stationType: string): string {
  return stationType
    .split('-')
    .filter((kata) => kata !== '')
    .map((kata) => kata.charAt(0).toUpperCase() + kata.slice(1))
    .join(' ')
}

/**
 * Pesan penolakan untuk layar Form, atau null bila penyimpanan boleh lanjut.
 *
 * Kalimatnya SAMA dengan yang dipakai layar web pada keadaan yang sama
 * (WB-FRM-07). Dua aplikasi yang menolak hal yang sama dengan dua kalimat
 * berbeda membuat operator dan supervisor mengira mereka menghadapi dua
 * masalah.
 */
export async function galatStasiunTidakAktif(
  routeName: unknown,
  userId: string | null | undefined,
): Promise<string | null> {
  const stationType = stationTypeFromFormRoute(routeName)

  if (stationType === null) {
    return null
  }

  const status = await activeStationStatus(stationType, userId)

  if (status !== 'tidak-aktif') {
    return null
  }

  return `Production Line yang dipilih belum memiliki station ${stationLabelFromType(stationType)} yang aktif.`
}

export default {
  readRememberedProductionLineId,
  resolveActiveStationId,
  activeStationStatus,
  galatStasiunTidakAktif,
}
