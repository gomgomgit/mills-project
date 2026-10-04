/**
 * Pembagian "lajur" ruang tanggal untuk spec yang menanam Periode Pelaporan.
 *
 * MASALAHNYA, DAN MENGAPA IA BARU ADA SEJAK 2026-09-26.
 *
 * Aturan tumpang tindih periode sekarang PER MILL dan tidak melihat jenis
 * stasiun sama sekali: satu tanggal hanya boleh dimiliki satu periode pada
 * satu mill (PeriodService::findOverlapping()). Sebelum itu ruangnya
 * (mill, station_type), sehingga empat spec laporan yang menanam periode di
 * mill yang SAMA — laporan-boiler-room, laporan-cages-track,
 * laporan-clarification, laporan-storage-tank, semuanya "Business Unit A" —
 * bebas memakai rentang tanggal yang berpotongan: jenis stasiunnya berbeda,
 * jadi tidak pernah bertabrakan. Sekarang bertabrakan, dan tabrakannya
 * muncul sebagai PERIOD_OVERLAP di beforeAll — seluruh spec gagal sebelum
 * satu pun asersi jalan. Sejak semua spec juga menanam satu periode
 * PERIOD_OTHER_MILL di "Mill Kode Duplikat", laporan-sterilizer ikut berbagi
 * satu mill dengan keempatnya walau mill utamanya lain.
 *
 * MENGAPA "LAJUR ABAD" YANG LAMA TIDAK BEKERJA. laporan-storage-tank dan
 * laporan-clarification mendokumentasikan pemisahan lewat epoch berbeda
 * (tahun 2800 dan 2700 lawan 2600 yang lain). Pemisahan itu semu: offset
 * hariannya sendiri sampai ~2,4 JUTA hari (~6.500 tahun), jadi ketiga
 * "abad" itu benar-benar saling menimpa sepanjang rentang tahun 2600-9400.
 * Selisih 100 tahun tidak memisahkan apa pun terhadap offset sebesar itu.
 * Diukur atas seluruh siklus detiknya, keempat spec Business Unit A
 * bertabrakan pada 0,024% detik — sekitar satu dari 4.000 run. Jarang, tapi
 * kegagalannya total dan tidak dapat direproduksi, yang justru jenis
 * kegagalan termahal.
 *
 * CARA KERJA LAJUR INI.
 *
 * Setiap spec tetap menghitung offset acaknya sendiri (RUN_OFFSET mentah,
 * dengan stride khasnya masing-masing), lalu offset itu DIBULATKAN KE BAWAH
 * ke kelipatan LANE_CYCLE dan digeser ke lajurnya sendiri. Akibatnya SETIAP
 * jendela spec manapun selalu jatuh di dalam
 *
 *     [blok + lajur*LANE_WIDTH, blok + (lajur+1)*LANE_WIDTH)
 *
 * dan karena lajur-lajur itu sub-rentang yang saling lepas dari blok
 * kelipatan LANE_CYCLE, dua spec dengan lajur berbeda TIDAK MUNGKIN
 * bertumpang tindih — apa pun detik jalannya, apa pun selisih waktu antar
 * run. Bukan "kecil kemungkinannya": mustahil secara aritmetika.
 *
 * Yang dikorbankan hanya resolusi: dua run yang offset mentahnya jatuh di
 * blok yang sama mendapat jendela yang sama. Itu tidak berbahaya — record
 * stasiun DAN periode di jendela itu dihapus oleh globalTeardown
 * (php artisan e2e:prune-records), jadi run berikutnya tidak mewarisi apa
 * pun di jendela itu. (Sejak 2026-10-04 afterAll tiap spec tidak lagi BISA
 * menghapus periodenya sendiri: periode yang sudah berisi record ditolak
 * 409 PERIOD_HAS_RECORDS. Teardown-lah yang menyapunya.)
 *
 * EPOCH: 1 JANUARI 1970, DI MASA LALU (sejak 2026-10-04). Semula tahun
 * 2600: tanggal sejauh itu dijamin tidak pernah dipakai pabrik. Aturan
 * tanggal kejadian (App\Support\AppTime::latestEventDate(), paling jauh
 * hari ini + EVENT_DATE_MAX_DAYS_AHEAD) kini menolaknya — dan aturan itu
 * SENGAJA tetap aktif di environment e2e, karena mematikannya berarti suite
 * tidak lagi menguji server yang sama dengan produksi. Masa lalu tidak
 * dibatasi aturan apa pun. Tahun 1970-2019 dipilih karena (a) suite berjalan
 * di database-nya sendiri (mill_smart_log_e2e) yang hanya berisi data demo
 * 2026 dan fixture 2020 ke atas (lihat FIXTURE_START di period-fixture.ts),
 * (b) di bawah 1964 zona Asia/Jakarta punya offset historis yang ganjil
 * (+07:07:12, +07:20, +09:00 ...), yang bisa menggeser jam Weighbridge.
 *
 * Supaya seluruh jendela tetap di dalam rentang itu, nomor blok dibungkus
 * modulo LANE_BLOCKS — offset mentah spec boleh tetap sebesar apa pun.
 * Rentangnya [LANE_RANGE_START, LANE_RANGE_END) adalah juga rentang yang
 * disapu e2e:prune-records; keduanya WAJIB sama.
 *
 * SEMUA SPEC WAJIB MEMAKAI EPOCH YANG SAMA — laneIsoDate() di bawah, bukan
 * Date.UTC sendiri. Aritmetika di atas berbicara tentang nomor hari
 * absolut; epoch yang berbeda-beda membuat nomor lajur tidak sebanding, dan
 * itulah tepatnya kekeliruan "lajur abad" yang lama.
 *
 * MENAMBAH SPEC BARU YANG MENANAM PERIODE: tambahkan satu entri di
 * PERIOD_LANES, jangan memakai ulang lajur yang sudah dipakai, dan pastikan
 * seluruh jendelanya berada di dalam LANE_WIDTH hari dari RUN_OFFSET-nya
 * (paling lebar saat ini laporan-storage-tank: -1 sampai +147 hari).
 */

/**
 * Lebar satu lajur, dalam hari. Harus lebih besar dari rentang total jendela
 * spec terlebar: laporan-storage-tank memakai RUN_OFFSET+2 sampai
 * RUN_OFFSET+147, ditambah satu hari sebelum jendela INCLUSIVE.
 */
export const LANE_WIDTH = 200

/**
 * Pergeseran di dalam lajur. Cukup besar agar jendela yang mulai satu hari
 * SEBELUM RUN_OFFSET (OUTSIDE_DATE di empat spec) tetap berada di dalam
 * lajurnya, dan cukup kecil agar 25 + 147 masih di bawah LANE_WIDTH.
 */
const LANE_MARGIN = 25

/** Satu lajur per spec yang menanam periode. Jangan pernah dipakai ulang. */
export const PERIOD_LANES = {
  'boiler-room': 0,
  'cages-track': 1,
  clarification: 2,
  'storage-tank': 3,
  sterilizer: 4,
  // screen-143--laporan-weighbridge-web (2026-10-01). Lajur baru, bukan
  // memakai ulang lajur yang sudah ada — spec itu menanam periodenya di
  // "Business Unit A" juga, mill yang sama dengan empat lajur pertama, dan
  // aturan tumpang tindih periode kini PER MILL.
  weighbridge: 5,
} as const

export type PeriodLane = keyof typeof PERIOD_LANES

/** Total siklus — sekali sebesar jumlah lajur yang ada. */
export const LANE_CYCLE = LANE_WIDTH * Object.keys(PERIOD_LANES).length

/**
 * Membulatkan `rawOffset` ke blok LANE_CYCLE lalu menggesernya ke lajur
 * `lane`. Nilai kembalinya dipakai sebagai RUN_OFFSET spec itu.
 */
export function laneOffset(rawOffset: number, lane: PeriodLane): number {
  const block = (Math.floor(rawOffset / LANE_CYCLE) % LANE_BLOCKS) * LANE_CYCLE

  return block + PERIOD_LANES[lane] * LANE_WIDTH + LANE_MARGIN
}

/** Hari ke-0 seluruh lajur. Lihat "EPOCH" di kepala berkas. */
export const LANE_RANGE_START = '1970-01-01'

/**
 * Batas atas (eksklusif) rentang lajur. e2e:prune-records menyapu tepat
 * [LANE_RANGE_START, LANE_RANGE_END) — ubah keduanya bersama-sama.
 */
export const LANE_RANGE_END = '2020-01-01'

const DAY_MS = 86400000

const LANE_EPOCH_MS = Date.parse(`${LANE_RANGE_START}T00:00:00Z`)

/**
 * Banyaknya blok LANE_CYCLE yang muat utuh di rentang, termasuk jendela
 * terlebar (LANE_WIDTH) di lajur terakhir blok terakhir.
 */
export const LANE_BLOCKS = Math.floor(
  (Date.parse(`${LANE_RANGE_END}T00:00:00Z`) - LANE_EPOCH_MS) / DAY_MS / LANE_CYCLE,
)

/** YYYY-MM-DD untuk hari ke-`dayOffset` sejak LANE_RANGE_START. */
export function laneIsoDate(dayOffset: number): string {
  return new Date(LANE_EPOCH_MS + dayOffset * DAY_MS).toISOString().slice(0, 10)
}
