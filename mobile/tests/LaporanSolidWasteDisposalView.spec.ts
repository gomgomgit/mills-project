/**
 * LaporanSolidWasteDisposalView.spec.ts — screen-163--laporan-solid-waste-disposal-mobile /
 * usecase-169--laporan-solid-waste-disposal-mobile "Lihat Laporan Periode
 * Solid Waste Disposal (Mobile)".
 *
 * Meliput seluruh `unit_test_cases` tech spec screen-163 yang menyangkut
 * KEPUTUSAN LAYAR (kasus 4, 6-44, 46, 47, 48) dan seluruh `component_test`
 * pada ke-32 `test_scenarios`-nya. Bentuk PERMINTAAN dan kesetiaan terusan
 * repo ada di solidWasteDisposalReportRepo.spec.ts (kasus 1, 2, 3, 5, 45);
 * kebenaran ANGKA-nya sudah dibuktikan terhadap
 * SolidWasteDisposalReportService ketika screen-162 dibangun — layar ini tidak
 * menyentuh satu berkas backend pun.
 *
 * ────────────────────────────────────────────────────────────────────────
 * YANG MEMBUAT ANGKA PONSEL SAMA DENGAN ANGKA WEB
 * ────────────────────────────────────────────────────────────────────────
 * Layar ini TIDAK MENGHITUNG APA PUN. Ia membaca /summary dan MEMILIH medan.
 * Karena itu fixture di bawah SENGAJA BERTENTANGAN DENGAN DIRINYA SENDIRI,
 * dan tidak satu angka pun di dalamnya dapat diturunkan dari angka lain:
 *
 *   - loads.net_weight_avg 9.9 padahal 37.5 / 5 = 7,5
 *   - daily_total.net_weight_total 55.0 padahal kolom hariannya tidak
 *     berjumlah demikian
 *   - completeness.percent 41.3 padahal 4 / 11 = 36,36…
 *
 * Klien yang menghitung PASTI gagal di sini. JANGAN "merapikan" angka-angka
 * ini.
 *
 * URUTAN BARIS MILIK SERVER. Urutan keempat rekap dan rekap harian di fixture
 * ini SENGAJA bukan alfabetis dan bukan urut jumlah, dan baris 'tidak
 * tercatat' SENGAJA tidak di posisi terakhir — satu-satunya cara membuktikan
 * klien tidak menyusun ulang.
 *
 * ────────────────────────────────────────────────────────────────────────
 * SEMBILAN JEBAKAN YANG SUDAH BERHARGA MAHAL DI PROYEK INI
 * ────────────────────────────────────────────────────────────────────────
 * 1. KETIADAAN DIASERSI ATAS TESTID, NAMA KUNCI, DAN ROLE — TIDAK PERNAH ATAS
 *    PROSA. Payload membawa `_note_no_hourly` dan `_note_shift_not_published`,
 *    yang MEMUAT kata 'jam', 'shift', bahkan '.xlsx' — dan layar merendernya
 *    UTUH. `expect(text).not.toContain('jam')` karena itu dijamin salah pada
 *    layar yang BENAR. Terbukti pada screen-161.
 *
 * 2. SPY PENYIMPANAN PADA `Storage.prototype`, BUKAN pada `window.localStorage`.
 *    Di jsdom instance-nya dibungkus Proxy, sehingga spy instance menerima NOL
 *    panggilan dan test-nya hijau tanpa menguji apa pun. Setiap test yang
 *    bersandar pada spy itu MEMBUKTIKAN lebih dulu bahwa ia menerima ≥1
 *    panggilan.
 *
 * 3. SETIAP WRAPPER DI-UNMOUNT PADA afterEach. Layar ini mendaftarkan
 *    pendengar `online`/`offline`; wrapper yang bocor menghitung GANDA
 *    permintaan bootstrap pada test berikutnya, dan kegagalannya akan tampak
 *    seperti bug di layar.
 *
 * 4. PENYIMPANAN PERANGKAT DIBERSIHKAN PADA beforeEach. Layar ini MENULIS
 *    `msl_production_line_{userId}` setiap kali sebuah line berlaku, jadi
 *    tanpa pembersihan sebuah test mewarisi ingatan test sebelumnya — dan
 *    hasilnya tampak persis seperti cacat "line dipilihkan diam-diam".
 *
 * 5. ANGKANYA BARE. Setiap elemen angka ber-testid memuat ANGKANYA SAJA;
 *    'MT' / 'muatan' / '%' hidup di `.metric-unit-pill` SEBELAHNYA, yang tidak
 *    dirender ketika nilainya null — sehingga '—' tidak pernah berdampingan
 *    dengan satuan yatim. Asersi '0,00 MT' karena itu MUSTAHIL dipenuhi layar
 *    ini, dan yang diasersi adalah '—' sebagai KESAMAAN PENUH teks elemennya.
 *
 * 6. PERIODE TIDAK PERNAH DIPILIHKAN. Line yang diingat perangkat memang
 *    terpakai tanpa satu langkah pengguna, tetapi /summary baru terkirim
 *    sesudah PERIODE dipilih. Setiap test "tepat satu permintaan" karena itu
 *    memilih periode secara eksplisit — divergensi yang DICATAT, bukan
 *    kelonggaran.
 *
 * 7. KELIMA REKAP MEMAKAI CollapsibleSection ber-`v-show` — TERTUTUP secara
 *    bawaan. Seluruh testid-nya TETAP ADA di DOM saat tertutup, jadi asersi
 *    isi di berkas ini tidak perlu membukanya (berbeda dari spec browser,
 *    tempat `toBeVisible` memang menuntutnya dibuka).
 *
 * 8. `toContain(a, b)` MEMPERLAKUKAN ARGUMEN KEDUA SEBAGAI NEEDLE KEDUA, bukan
 *    sebagai pesan. Satu needle per pemanggilan; pesannya lewat argumen kedua
 *    `expect()`.
 *
 * 9. REKAP DITULIS SEKALI, DI-LOOP ATAS `RECAPS`. Konsekuensinya sumbernya
 *    hanya punya DUA kemunculan literal `v-stacked-table` sementara LIMA tabel
 *    dirender — asersi statis ala screen-161 (`toHaveLength(3)`) karena itu
 *    TIDAK disalin ke sini; yang dihitung adalah TABEL YANG TERENDER.
 *
 * ────────────────────────────────────────────────────────────────────────
 * DIVERGENSI SPEC-LAWAN-IMPLEMENTASI YANG DICATAT DI SINI
 * ────────────────────────────────────────────────────────────────────────
 * DIVERGENSI 1 — unit_test_case 47 / skenario 32 berbunyi "REPORT_ROUTES dan
 * STATION_ICONS diimpor dari ReportingPilihStasiunView.vue". Keduanya const
 * LOKAL di dalam <script setup> dan TIDAK diekspor, dan peta ikonnya bernama
 * `TYPE_ICONS` — bukan `STATION_ICONS`. Import semacam itu TIDAK DAPAT
 * DIKOMPILASI. Invariannya karena itu diasersi lewat DOM (tile tidak
 * ber-aria-disabled, tidak memuat 'Belum tersedia', dan klik memanggil
 * router.push dengan nama rute ini) DITAMBAH audit statis atas peta sumbernya.
 * Tidak ada ekspor baru yang ditambahkan ke view itu demi test ini.
 *
 * DIVERGENSI 2 — unit_test_case 9 berbunyi "fungsi pemuat ringkasan di-spy
 * (bukan di-mock)". Fungsi itu lokal di dalam <script setup> dan tidak dapat
 * di-spy dari luar. Yang dibuktikan sebagai gantinya LEBIH KUAT daripada
 * hitungan spy: ketiga jalur yang sama dijalankan DUA KALI — sekali tanpa line
 * (penghitung permintaan 0 pada ketiganya) dan sekali dengan line (TEPAT 3
 * permintaan). Penjagaan yang disebar di pemanggil akan membuat salah satu
 * dari enam angka itu menyimpang.
 *
 * DIVERGENSI 3 — unit_test_case 48 menuntut pemeriksaan kelas CSS pada HASIL
 * COMPILE. Di jsdom tidak ada hasil compile: `document.styleSheets` kosong dan
 * `getComputedStyle` tidak memuat satu pun blok <style scoped>. Pemeriksaan
 * atas SUMBER ada di berkas ini; pemeriksaan atas HASIL COMPILE ada di
 * tests/e2e/laporan-solid-waste-disposal.spec.ts, tempat ia dibaca dari
 * document.styleSheets — dan keduanya memang diperlukan.
 *
 * DIVERGENSI 4 — `daily_total._note` (NOTE_DAILY_TOTAL) ADA di payload nyata
 * tetapi TIDAK tercantum pada success_schema tech spec. Ia ikut di fixture
 * berkas ini dan diasersi dirender VERBATIM, karena yang diuji adalah layar
 * terhadap SERVER, bukan layar terhadap ringkasan skemanya.
 *
 * TANGGALNYA RELATIF, bukan kalender tetap — fixture bertanggal mati menjadi
 * basi tanpa satu pun test berubah warna.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import type { DOMWrapper, VueWrapper } from '@vue/test-utils'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import LaporanSolidWasteDisposalView from '@/views/LaporanSolidWasteDisposalView.vue'
import ReportingPilihStasiunView from '@/views/ReportingPilihStasiunView.vue'
import type { StationSlot, StationType } from '@/services/stationRepo'

/* ------------------------------------------------------------------ */
/* Mock modul                                                          */
/* ------------------------------------------------------------------ */

const { pushMock, routeQuery } = vi.hoisted(() => ({
  pushMock: vi.fn(),
  routeQuery: {} as Record<string, string>,
}))

vi.mock('vue-router', () => ({
  useRouter: () => ({ push: pushMock }),
  useRoute: () => ({ params: {}, query: routeQuery }),
}))

const { useAuthStoreMock, logoutMock } = vi.hoisted(() => ({
  useAuthStoreMock: vi.fn(),
  logoutMock: vi.fn().mockResolvedValue(undefined),
}))

vi.mock('@/stores/auth', () => ({ useAuthStore: useAuthStoreMock }))

vi.mock('@/stores/floatingClock', () => ({
  useFloatingClockStore: () => ({ enabled: false, toggle: vi.fn() }),
}))

vi.mock('@/stores/aiAssistant', () => ({
  useAiAssistantStore: () => ({
    isOpen: false,
    bubbleEnabled: true,
    open: vi.fn(),
    close: vi.fn(),
    toggleBubble: vi.fn(),
  }),
}))

/**
 * apiClient di-mock supaya modul ASLI solidWasteDisposalReportRepo (diimpor
 * lewat importActual di bawah, demi konstanta jalur dan exportParams yang
 * SUNGGUHAN) tidak membangun instance axios dan tidak pernah menyentuh
 * jaringan. Spy `get`-nya DIPAKAI sebagai asersi: layar ini hanya boleh
 * berbicara lewat repo, jadi setiap pemanggilan langsung akan terhitung di
 * sini.
 */
const { apiClientGetMock } = vi.hoisted(() => ({ apiClientGetMock: vi.fn() }))

vi.mock('@/services/apiClient', () => ({
  default: { get: apiClientGetMock },
}))

/** DELAPAN anggota repo; yang di-mock hanya yang menyentuh jaringan + simpan. */
const { repoMocks } = vi.hoisted(() => ({
  repoMocks: {
    fetchBusinessUnits: vi.fn(),
    fetchLines: vi.fn(),
    fetchPeriods: vi.fn(),
    fetchSummary: vi.fn(),
    exportCsv: vi.fn(),
    saveCsvFile: vi.fn(),
  },
}))

vi.mock('@/services/solidWasteDisposalReportRepo', async () => {
  const actual = await vi.importActual<
    typeof import('@/services/solidWasteDisposalReportRepo')
  >('@/services/solidWasteDisposalReportRepo')

  return {
    ...actual,
    default: { ...actual.default, ...repoMocks },
    ...repoMocks,
  }
})

/**
 * productionLineRepo di-mock DAN diasersi NOL PEMANGGILAN LANGSUNG dari view:
 * `fetchLines` repo laporan MENDELEGASIKAN ke sana, jadi view yang memanggil
 * repo line sendiri akan memintas satu-satunya tempat business_unit_id
 * ditentukan.
 */
const { productionLineMocks } = vi.hoisted(() => ({
  productionLineMocks: {
    fetchProductionLinesForReport: vi.fn(),
    fetchCurrentProductionLines: vi.fn(),
    fetchAndCacheStationsForProductionLine: vi.fn(),
  },
}))

vi.mock('@/services/productionLineRepo', () => ({
  default: productionLineMocks,
  productionLineRepo: productionLineMocks,
  ...productionLineMocks,
}))

/**
 * stationRepo + localSchema di-mock HANYA demi describe "pintu masuk"
 * (unit_test_case 47), yang memasang ReportingPilihStasiunView untuk
 * mengasersi tile-nya lewat DOM. LaporanSolidWasteDisposalView tidak mengimpor
 * keduanya.
 */
const { stationRepoMocks } = vi.hoisted(() => ({
  stationRepoMocks: {
    getActiveAndPlaceholderStations: vi.fn(),
    getActiveAndPlaceholderStationsForProductionLine: vi.fn(),
  },
}))

vi.mock('@/services/stationRepo', () => ({
  stationRepo: stationRepoMocks,
  ...stationRepoMocks,
}))

vi.mock('@/services/localSchema', () => ({
  seedDefaultStationsIfNeeded: vi.fn().mockResolvedValue(undefined),
}))

/**
 * localDb di-mock PENUH dengan spy tulis, lalu diasersi NOL pemanggilan
 * (unit_test_case 5). Modul aslinya hanya punya open/query/run;
 * put/add/bulkPut/transaction ditambahkan supaya cache yang kelak ditumbuhkan
 * lewat salah satu nama itu tertangkap tanpa berkas ini disunting.
 */
const { localDbMocks } = vi.hoisted(() => ({
  localDbMocks: {
    open: vi.fn(),
    query: vi.fn(),
    run: vi.fn(),
    put: vi.fn(),
    add: vi.fn(),
    bulkPut: vi.fn(),
    transaction: vi.fn(),
  },
}))

vi.mock('@/services/localDb', () => ({
  default: localDbMocks,
  localDb: localDbMocks,
  ...localDbMocks,
}))

import {
  BUSINESS_UNITS_PATH,
  buildExportUrl,
  EXPORT_PATH,
  exportParams,
  LINES_PATH,
  PERIODS_PATH,
  SUMMARY_PATH,
  type SolidWasteDisposalReportScope,
  type SolidWasteDisposalReportSummary,
} from '@/services/solidWasteDisposalReportRepo'

/* ------------------------------------------------------------------ */
/* Bentuk galat NYATA                                                  */
/* ------------------------------------------------------------------ */

const NETWORK_ERROR = { message: 'Tidak dapat terhubung ke server.' }
const UNAUTHENTICATED_ERROR = { message: 'Unauthenticated.', status: 401 }
const FORBIDDEN_ERROR = { message: 'Anda tidak memiliki akses untuk aksi ini.', status: 403 }
const NOT_FOUND_ERROR = {
  message: 'Periode Pelaporan yang dipilih tidak ditemukan.',
  status: 404,
}
const SERVER_ERROR = { message: 'Terjadi kesalahan pada server.', status: 500 }
/** DISALIN dari SolidWasteDisposalReportService ± baris 445. */
const NO_MILL_ERROR = {
  message: 'Akun Anda belum terhubung ke mill. Hubungi Admin.',
  status: 422,
  errors: { business_unit_id: ['Akun Anda belum terhubung ke mill. Hubungi Admin.'] },
}

/* ------------------------------------------------------------------ */
/* Tanggal relatif                                                     */
/* ------------------------------------------------------------------ */

function isoDaysFromNow(offsetDays: number): string {
  return new Date(Date.now() + offsetDays * 86_400_000).toISOString().slice(0, 10)
}

const PERIOD_START = isoDaysFromNow(-29)
const PERIOD_END = isoDaysFromNow(0)
const PREV_START = isoDaysFromNow(-70)
const PREV_END = isoDaysFromNow(-40)

/** SEMBILAN hari bermuatan di dalam periode 30 hari. */
const LOADED_DAYS = [-25, -22, -20, -18, -15, -12, -9, -6, -3].map(isoDaysFromNow)

/**
 * URUTAN PAYLOAD-nya SENGAJA bukan urut tanggal naik: klien yang mengurutkan
 * ulang rekap harian akan tertangkap di sini.
 */
const DAILY_ORDER = [4, 0, 7, 2, 8, 1, 6, 3, 5].map((index) => LOADED_DAYS[index])

/* ------------------------------------------------------------------ */
/* Prosa SERVER — DISALIN dari SolidWasteDisposalReportService         */
/* ------------------------------------------------------------------ */

/**
 * Keempat (ditambah NOTE_DAILY_TOTAL: kelima) konstanta prosa DISALIN
 * VERBATIM dari backend/app/Services/SolidWasteDisposalReportService.php —
 * NOTE_NO_HOURLY (± baris 342), NOTE_SHIFT_NOT_PUBLISHED (± 354),
 * NOTE_ABSENCES (± 365), NOTE_COMPLIANCE (± 379), NOTE_DAILY_TOTAL (± 391).
 *
 * BUKAN DIKARANG, dan itu bukan formalitas: layar ini merendernya UTUH dan
 * diasersi dengan KESAMAAN PENUH. Prosa karangan akan membuat asersi
 * verbatim hijau sambil membuktikan nol hal tentang kalimat yang benar-benar
 * dibaca pengguna.
 */
const NOTE_NO_HOURLY =
  'Sebaran muatan per jam TIDAK DAPAT dibuat untuk stasiun ini, dan ketiadaannya '
  + 'dinyatakan di sini alih-alih disembunyikan. Baris detail Solid Waste Disposal hanya '
  + 'membawa event_date — sebuah tanggal — dan tidak punya satu pun kolom waktu. Bukan '
  + 'kolom waktu yang kosong, bukan kolom yang belum diisi operator: kolomnya tidak ada. '
  + 'Karena itu pertanyaan tentang kapan dalam sehari limbah paling banyak keluar tidak '
  + 'dapat dihitung dari data yang ada, dalam bentuk apa pun — tidak sebagai grafik 24 '
  + 'kolom, tidak sebagai kartu jam tersibuk, tidak pula sebagai perkiraan. Ini BUKAN fitur '
  + 'yang ditunda. Satu-satunya sumbu waktu yang tersedia di layar ini adalah tanggal, '
  + 'yaitu rekap harian. Menurunkannya dari created_at berarti menebak dari waktu INPUT, '
  + 'bukan waktu kejadian, dan jawaban yang terlihat rinci padahal tidak pernah diukur '
  + 'lebih buruk daripada tidak ada jawaban.'

const NOTE_SHIFT_NOT_PUBLISHED =
  'Kolom shift pada baris detail bertipe TEKS BEBAS, bukan daftar nilai tertutup dan '
  + 'bukan relasi ke master. Menyebarkan teks bebas menghasilkan satu nilai per ejaan yang '
  + 'berbeda, sehingga empat penulisan dari satu giliran kerja yang sama akan terbaca '
  + 'sebagai empat kategori. Kolom itu karena itu TIDAK diterbitkan di payload maupun di '
  + 'layar ini — bukan sebagai rekap, bukan sebagai kolom tabel, bukan pula sebagai tempat '
  + 'kosong. Ia TETAP ikut di berkas ekspor pada setiap baris muatan, bersama nomor gate '
  + 'pass, nomor segel dan id operator, karena ekspor adalah rincian mentah. Pengelompokan '
  + 'per giliran kerja yang sungguhan datang dari kolom shift_id pada tabel INDUK dan '
  + 'berlaku untuk 18 stasiun sekaligus — pekerjaan tersendiri, bukan sesuatu yang '
  + 'ditempelkan pada satu laporan.'

const NOTE_ABSENCES =
  'Layar ini tidak memuat blok downtime, dan ketiadaannya disengaja: stasiun Solid Waste '
  + 'Disposal tidak mencatat downtime sama sekali — tidak ada kolom durasi berhenti, tidak '
  + 'ada kolom penyebab berhenti, dan tidak ada tabel downtime pada baris detailnya. Blok '
  + 'kosong sebagai tempat yang menunggu diisi akan terbaca sebagai belum ada datanya, '
  + 'padahal yang benar adalah stasiun ini memang tidak mengukurnya. Tidak ada pula satu '
  + 'nilai pun di layar ini yang ditandai di luar batas: stasiun ini tidak punya tabel '
  + 'master target, sehingga tidak ada satu baris acuan yang dapat dibandingkan dengan '
  + 'berat bersih sebuah muatan, dengan rata-rata netto per muatan, maupun dengan jumlah '
  + 'muatan per hari. Menurunkan ambang dari data periode ini sendiri akan menghasilkan '
  + 'garis yang terlihat berwibawa lalu dibaca sebagai batas resmi padahal bukan, dan garis '
  + 'itu ikut bergeser setiap kali kelengkapan pencatatan berubah. Seluruh angka karena itu '
  + 'ditampilkan apa adanya, tanpa warna peringatan dan tanpa satu sel yang ditandai; '
  + 'penilaian layak atau tidak layak tetap di tangan pembacanya.'

const NOTE_COMPLIANCE =
  'Nomor gate pass dan nomor segel keamanan adalah JEJAK KEPATUHAN, bukan ukuran '
  + 'produksi: keduanya menyatakan dokumen angkut yang tidak terbit. Karena itu keduanya '
  + 'diterbitkan HANYA sebagai penghitung kehadiran — berapa muatan tanpa gate pass dan '
  + 'berapa muatan tanpa segel — dan tidak pernah dirata-rata, tidak pernah diurutkan, dan '
  + 'tidak pernah digrafikkan. Sebuah nomor segel tidak punya besaran: segel bernomor 90021 '
  + 'bukan lebih besar daripada segel bernomor 90018, selisih keduanya tidak berarti apa '
  + 'pun, dan rata-rata nomor segel adalah angka yang tidak mengukur apa-apa. Bila seluruh '
  + 'muatan periode lengkap, kedua penghitung menunjukkan nol dan nol itu TETAP diterbitkan '
  + '— nol itulah yang memberi tahu pembaca bahwa pemeriksaannya memang berjalan. '
  + 'Nomor-nomornya sendiri tidak diterbitkan di payload layar; ia hanya ada di ekspor.'

const NOTE_DAILY_TOTAL =
  'Baris total ini DIJUMLAHKAN dari daily[], bukan disalin dari blok loads. Keduanya '
  + 'memang dapat berbeda, tepatnya sebesar undated_load_count — muatan yang event_date-nya '
  + 'kosong ikut di loads dan di keempat rekap kategoris tetapi tidak dapat ditempatkan '
  + 'pada hari mana pun. Perbedaan itu adalah informasi; menyalin akan menyembunyikannya, '
  + 'dan selisih yang tak dijelaskan tidak dapat dibedakan dari cacat.'

/* ------------------------------------------------------------------ */
/* Fixture — BENTUK RESPONS NYATA                                      */
/* ------------------------------------------------------------------ */

const PERIOD = {
  id: 'p-1',
  name: 'Periode Uji Solid Waste Disposal',
  start_date: PERIOD_START,
  end_date: PERIOD_END,
  status: 'open',
  // DENGAN TANDA HUBUNG. Garis bawah di situ tidak cocok dengan satu baris
  // period_stations pun, dan server menjawab [] tanpa satu galat.
  station_type: 'solid-waste-disposal',
  station_type_label: 'Solid Waste Disposal',
}

const PERIOD_CLOSED = {
  id: 'p-2',
  name: 'Periode Sebelumnya',
  start_date: PREV_START,
  end_date: PREV_END,
  status: 'closed',
  station_type: 'solid-waste-disposal',
  station_type_label: 'Solid Waste Disposal',
}

const LINE_3 = { id: 'l-3', name: 'Line Tiga', code: 'L3' }
const LINE_4 = { id: 'l-4', name: 'Line Empat', code: null }

const BUSINESS_UNITS = [
  { id: 'bu-1', name: 'Mill Sungai Rambutan' },
  { id: 'bu-9', name: 'Mill Sungai Lilin' },
]

/** Kunci ingatan line — ditulis StationListView.vue, dibaca layar ini. */
const REMEMBERED_LINE_KEY = 'msl_production_line_u-7'

function row(
  value: string | null,
  loadCount: number,
  netWeightTotal: number | null,
  netWeightLoadCount: number,
  netWeightAvg: number | null,
): Record<string, unknown> {
  return {
    value,
    load_count: loadCount,
    net_weight_total: netWeightTotal,
    net_weight_load_count: netWeightLoadCount,
    net_weight_avg: netWeightAvg,
  }
}

/**
 * KEEMPAT rekap, masing-masing berjumlah TEPAT 12 muatan dengan komposisi
 * BERBEDA, dan masing-masing memuat SATU baris `value: null` yang BUKAN di
 * posisi terakhir. Urutannya bukan alfabetis dan bukan urut jumlah.
 */
function recapsOfTwelve(): Record<string, Array<Record<string, unknown>>> {
  return {
    by_waste_type: [
      row('Zpalm shell', 1, 4.5, 1, 4.5),
      row(null, 5, 21.25, 3, 7.08),
      row('Abu boiler', 4, null, 0, null),
      row('Mesocarp fibre', 2, 11.75, 1, 11.75),
    ],
    by_source_station: [
      row('Boiler', 6, 18.5, 2, 9.25),
      row(null, 5, 12.5, 2, 6.25),
      row('Pressing', 1, 6.5, 1, 6.5),
    ],
    by_disposal_site: [
      row(null, 5, 20.5, 3, 6.83),
      row('Landfill internal', 3, 11.5, 1, 11.5),
      row('Kebun blok C', 4, 5.5, 1, 5.5),
    ],
    by_purpose_end_use: [
      row('Pupuk organik', 2, 9.5, 1, 9.5),
      row(null, 5, 15.5, 2, 7.75),
      row('Penimbunan', 4, 12.5, 1, 12.5),
      row('Bahan bakar boiler', 1, 0.5, 1, 0.5),
    ],
  }
}

/** Keempat testid rekap, dalam URUTAN PAYLOAD. */
const RECAP_TESTIDS = [
  'swd-recap-waste-type',
  'swd-recap-source-station',
  'swd-recap-disposal-site',
  'swd-recap-purpose-end-use',
] as const

const RECAP_KEYS = [
  'by_waste_type',
  'by_source_station',
  'by_disposal_site',
  'by_purpose_end_use',
] as const

function dailyRows(): Array<Record<string, unknown>> {
  const counts: Record<string, number> = {}

  LOADED_DAYS.forEach((date, index) => {
    counts[date] = [3, 1, 2, 1, 1, 1, 1, 1, 1][index]
  })

  return DAILY_ORDER.map((date, index) => ({
    date,
    load_count: counts[date],
    net_weight_total: index % 3 === 1 ? null : 3.5 + index,
    net_weight_load_count: index % 3 === 1 ? 0 : 1,
    net_weight_avg: index % 3 === 1 ? null : 3.5 + index,
  }))
}

/**
 * Payload /summary LENGKAP — bentuk SolidWasteDisposalReportService::
 * buildSummary(), termasuk `daily_total._note` (DIVERGENSI 4).
 */
function makeSummary(overrides: Record<string, unknown> = {}): Record<string, unknown> {
  return {
    business_unit: { id: 'bu-1', name: 'Mill Sungai Rambutan' },
    production_line: { id: LINE_3.id, name: LINE_3.name, code: LINE_3.code },
    period: {
      id: PERIOD.id,
      name: PERIOD.name,
      start_date: PERIOD.start_date,
      end_date: PERIOD.end_date,
      status: PERIOD.status,
    },
    loads: {
      load_count: 12,
      net_weight_total: 37.5,
      // 37.5 / 5 = 7,5 — angka ini SENGAJA 9.9.
      net_weight_avg: 9.9,
      net_weight_load_count: 5,
      missing_net_weight_load_count: 7,
    },
    ...recapsOfTwelve(),
    compliance: {
      missing_gate_pass_count: 0,
      missing_seal_count: 3,
      _note: NOTE_COMPLIANCE,
    },
    draft_load_count: 4,
    undated_load_count: 0,
    daily: dailyRows(),
    daily_total: {
      load_count: 12,
      // BUKAN jumlah kolom di atasnya — lihat docblock berkas.
      net_weight_total: 55.0,
      net_weight_load_count: 3,
      net_weight_avg: 7.25,
      _note: NOTE_DAILY_TOTAL,
    },
    completeness: {
      days_in_period: 30,
      days_with_load: 4,
      days_counted: 11,
      // 4 / 11 = 36,36… — angka ini SENGAJA 41.3.
      percent: 41.3,
    },
    text_entries: {
      findings: [{ date: LOADED_DAYS[0], value: 'Ceceran limbah di jalur keluar gerbang timbang.' }],
      remarks: [{ date: LOADED_DAYS[0], value: 'Kendaraan menunggu 40 menit di pos keamanan.' }],
    },
    _note_no_hourly: NOTE_NO_HOURLY,
    _note_shift_not_published: NOTE_SHIFT_NOT_PUBLISHED,
    _note_absences: NOTE_ABSENCES,
    ...overrides,
  }
}

/** Periode SAH tetapi NOL muatan pada line terpilih. */
function emptyPeriodSummary(): Record<string, unknown> {
  return makeSummary({
    loads: {
      load_count: 0,
      net_weight_total: null,
      net_weight_avg: null,
      net_weight_load_count: 0,
      missing_net_weight_load_count: 0,
    },
    by_waste_type: [],
    by_source_station: [],
    by_disposal_site: [],
    by_purpose_end_use: [],
    compliance: { missing_gate_pass_count: 0, missing_seal_count: 0, _note: NOTE_COMPLIANCE },
    draft_load_count: 0,
    daily: [],
    daily_total: {
      load_count: 0,
      net_weight_total: null,
      net_weight_load_count: 0,
      net_weight_avg: null,
      _note: NOTE_DAILY_TOTAL,
    },
    text_entries: { findings: [], remarks: [] },
  })
}

/* ------------------------------------------------------------------ */
/* Penghitung permintaan PER JALUR                                     */
/* ------------------------------------------------------------------ */

/**
 * SETIAP permintaan yang benar-benar dikirim layar ini, dicatat atas JALURNYA.
 *
 * Repo di-mock, jadi penghitungnya hidup di sini — dan pemetaan fungsi→jalur
 * itu sendiri sudah dibuktikan di solidWasteDisposalReportRepo.spec.ts
 * ("kelima fungsi meminta TEPAT kelima URL yang sudah ada"). Dengan begitu
 * "penghitung permintaan ke /summary TEPAT 0" adalah angka yang DIUKUR, bukan
 * disimpulkan dari angka yang tidak tampil.
 */
const requests: string[] = []

function hits(path: string): number {
  return requests.filter((item) => item === path).length
}

/** Cakupan yang diserahkan view ke repo, per pemanggilan /summary. */
const summaryCalls: Array<{ periodId: string; scope: SolidWasteDisposalReportScope }> = []
const exportCalls: Array<{ periodId: string; scope: SolidWasteDisposalReportScope }> = []
const periodsCalls: SolidWasteDisposalReportScope[] = []
const linesCalls: SolidWasteDisposalReportScope[] = []

/**
 * PARAMETER KAWAT sebuah pemanggilan /summary — nama kunci yang benar-benar
 * dikirim, bukan medan camelCase cakupan internal.
 *
 * Diturunkan dari `exportParams()` YANG SUNGGUHAN (repo diimpor lewat
 * importActual) dikurangi `format`: repo membangun parameter /summary dan
 * /export dari SATU pembentuk yang sama, dan solidWasteDisposalReportRepo.spec.ts
 * mengasersi tepat itu. Inilah yang membuat asersi "business_unit_id tidak
 * muncul sebagai KUNCI" berlaku atas KAWAT, bukan atas objek cakupan.
 */
function wireParamsOf(call: {
  periodId: string
  scope: SolidWasteDisposalReportScope
}): Record<string, string> {
  const params = { ...exportParams(call.periodId, call.scope) }

  delete params.format

  return params
}

/* ------------------------------------------------------------------ */
/* Bantuan mount / interaksi                                           */
/* ------------------------------------------------------------------ */

const mountedWrappers: VueWrapper[] = []
const cleanups: Array<() => void> = []

async function mountView(): Promise<VueWrapper> {
  const wrapper = mount(LaporanSolidWasteDisposalView)

  mountedWrappers.push(wrapper)
  await flushPromises()

  return wrapper
}

async function selectMill(wrapper: VueWrapper, millId: string): Promise<void> {
  await wrapper.get('[data-testid="mill-select"]').setValue(millId)
  await flushPromises()
}

async function selectLine(wrapper: VueWrapper, lineId: string): Promise<void> {
  await wrapper.get('[data-testid="production-line-select"]').setValue(lineId)
  await flushPromises()
}

async function selectPeriod(wrapper: VueWrapper, periodId: string): Promise<void> {
  await wrapper.get('[data-testid="period-select"]').setValue(periodId)
  await flushPromises()
}

/**
 * Alur baku jalur sukses: ingatan perangkat menyediakan line, pengguna memilih
 * PERIODE. Periode TIDAK PERNAH dipilihkan — lihat jebakan #6 pada docblock.
 */
async function mountFullReport(periodId = 'p-1'): Promise<VueWrapper> {
  window.localStorage.setItem(REMEMBERED_LINE_KEY, LINE_3.id)

  const wrapper = await mountView()

  await selectPeriod(wrapper, periodId)

  return wrapper
}

function text(wrapper: VueWrapper, testid: string): string {
  return wrapper.get(`[data-testid="${testid}"]`).text()
}

/**
 * textContent MENTAH sebuah elemen, hanya di-trim lalu spasinya dinormalkan.
 *
 * Dipakai untuk asersi VERBATIM: setiap elemen prosa server adalah SATU
 * mustache tanpa satu kata tambahan di dalamnya, jadi kesamaan penuh memang
 * berlaku.
 */
function rawText(wrapper: VueWrapper, testid: string): string {
  const content = wrapper.get(`[data-testid="${testid}"]`).element.textContent ?? ''

  return content.replace(/\s+/g, ' ').trim()
}

function normalize(value: string): string {
  return value.replace(/\s+/g, ' ').trim()
}

function exists(wrapper: VueWrapper, testid: string): boolean {
  return wrapper.find(`[data-testid="${testid}"]`).exists()
}

function all(wrapper: VueWrapper, selector: string): DOMWrapper<Element>[] {
  return wrapper.findAll(selector)
}

/** KELIMA kartu angka ber-prefix swd-kpi- — dipakai asersi "nol angka". */
function kpiElements(wrapper: VueWrapper): DOMWrapper<Element>[] {
  return wrapper.findAll('[data-testid^="swd-kpi-"]')
}

function element(wrapper: VueWrapper, testid: string): Element {
  return wrapper.get(`[data-testid="${testid}"]`).element
}

function recapValues(wrapper: VueWrapper, testid: string): string[] {
  return wrapper
    .findAll(`[data-testid^="${testid}-value-"]`)
    .map((cell) => normalize(cell.text()))
}

function idNumber(value: string): number {
  // Format id-ID: '1.234,5' → 1234.5.
  return Number(normalize(value).replace(/\./g, '').replace(',', '.'))
}

function recapLoadCounts(wrapper: VueWrapper, testid: string): number[] {
  return wrapper
    .findAll(`[data-testid^="${testid}-load-count-"]`)
    .map((cell) => idNumber(cell.text()))
}

function dailyDates(wrapper: VueWrapper): string[] {
  return wrapper
    .findAll('[data-testid^="swd-daily-row-"]')
    .map((rowEl) => (rowEl.attributes('data-testid') ?? '').replace('swd-daily-row-', ''))
}

function headingTexts(wrapper: VueWrapper): string[] {
  return wrapper
    .findAll('h1, h2, h3, h4, h5, h6, [role="heading"]')
    .map((heading) => normalize(heading.text()))
}

function columnHeaderTexts(wrapper: VueWrapper): string[] {
  return wrapper.findAll('th, [role="columnheader"]').map((header) => normalize(header.text()))
}

function buttonNames(wrapper: VueWrapper): string[] {
  return wrapper
    .findAll('button, [role="button"]')
    .map((button) => normalize(`${button.text()} ${button.attributes('aria-label') ?? ''}`))
}

function collapsibleToggles(wrapper: VueWrapper): DOMWrapper<Element>[] {
  return wrapper.findAll('[data-testid="collapsible-section-toggle"]')
}

/**
 * LABEL OPSI YANG SEDANG TERPILIH pada sebuah <select>.
 *
 * TIDAK membaca `element.value`: Vue mengikat `:value="null"` pada opsi
 * placeholder lewat properti internalnya dan TIDAK menuliskan atribut
 * `value`, sehingga `select.value` di jsdom jatuh ke TEKS opsi itu — asersi
 * `value === ''` karena itu memerah pada keadaan yang justru benar.
 */
function selectedOptionText(wrapper: VueWrapper, testid: string): string {
  const select = wrapper.get(`[data-testid="${testid}"]`).element as HTMLSelectElement

  return select.selectedOptions[0]?.textContent?.trim() ?? ''
}

const NO_LINE_SELECTED = 'Pilih Production Line'
const NO_PERIOD_SELECTED = 'Pilih Periode'
const NO_MILL_SELECTED = 'Pilih Mill'

function asRole(
  role: 'operator' | 'supervisor' | 'mill_management' | 'admin',
  businessUnitId: string | null,
  userId = 'u-7',
): void {
  useAuthStoreMock.mockReturnValue({
    currentUser: {
      id: userId,
      username: `${role}01`,
      name: 'Pengguna Uji',
      role,
      business_unit_id: businessUnitId,
    },
    businessUnit: businessUnitId ? { id: businessUnitId, name: 'Mill Sungai Rambutan' } : null,
    logout: logoutMock,
  })
}

const MILL_BOUND_ROLES = ['operator', 'supervisor', 'mill_management'] as const

/**
 * `navigator.onLine` dipalsukan lewat defineProperty — sinyal yang
 * BENAR-BENAR dibaca layar ini (syncOnlineState) — dan dipulihkan di
 * afterEach. Bukan dengan mematikan jaringan: di jsdom tidak ada jaringan
 * untuk dimatikan, dan di browser cara itu justru mencegah aplikasinya boot.
 */
function fakeOnLine(value: boolean): void {
  const original = Object.getOwnPropertyDescriptor(window.navigator, 'onLine')

  Object.defineProperty(window.navigator, 'onLine', { configurable: true, get: () => value })

  cleanups.push(() => {
    if (original) {
      Object.defineProperty(window.navigator, 'onLine', original)
    } else {
      // jsdom mendefinisikannya di Navigator.prototype; menghapus milik
      // instance memulangkan getter prototipenya.
      delete (window.navigator as unknown as Record<string, unknown>).onLine
    }
  })
}

/**
 * Payload yang MENCATAT setiap pembacaan kunci tingkat atas.
 *
 * Dipakai unit_test_case 11, 31 dan 35: "nol kunci hourly yang DIBACA klien"
 * tidak dapat dibuktikan dengan memindai teks halaman — payload-nya memuat
 * prosa yang justru MENYEBUT kata 'jam'. Yang dapat membuktikannya adalah
 * daftar NAMA KUNCI yang benar-benar diakses.
 */
function recordingPayload(
  payload: Record<string, unknown>,
): { proxy: Record<string, unknown>; readKeys: string[] } {
  const readKeys: string[] = []

  const proxy = new Proxy(payload, {
    get(target, key, receiver) {
      if (typeof key === 'string') {
        readKeys.push(key)
      }

      return Reflect.get(target, key, receiver)
    },
  })

  return { proxy, readKeys }
}

/** Sumber TANPA komentar — lihat solidWasteDisposalReportRepo.spec.ts. */
function stripComments(source: string): string {
  return source
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .replace(/<!--[\s\S]*?-->/g, '')
    .replace(/^\s*\/\/.*$/gm, '')
}

function readSource(relative: string): string {
  return readFileSync(resolve(process.cwd(), relative), 'utf-8')
}

const VIEW_PATH = 'src/views/LaporanSolidWasteDisposalView.vue'
const PICKER_PATH = 'src/views/ReportingPilihStasiunView.vue'

function scriptBlockOf(sfcSource: string): string {
  const start = sfcSource.indexOf('<script setup')
  const end = sfcSource.indexOf('</script>')

  expect(start).toBeGreaterThan(-1)
  expect(end).toBeGreaterThan(start)

  return sfcSource.slice(start, end)
}

/** Peta REPORT_ROUTES sebagai TEKS SUMBER — ia const lokal, tidak diekspor. */
function reportRoutesSource(): string {
  const code = stripComments(readSource(PICKER_PATH))
  const start = code.indexOf('const REPORT_ROUTES')

  expect(start).toBeGreaterThan(-1)

  return code.slice(start, start + code.slice(start).indexOf('}') + 1)
}

/* ------------------------------------------------------------------ */
/* Pemasangan mock per test                                            */
/* ------------------------------------------------------------------ */

beforeEach(() => {
  pushMock.mockReset()
  logoutMock.mockClear()
  useAuthStoreMock.mockReset()
  apiClientGetMock.mockReset()

  Object.keys(routeQuery).forEach((key) => delete routeQuery[key])

  requests.length = 0
  summaryCalls.length = 0
  exportCalls.length = 0
  periodsCalls.length = 0
  linesCalls.length = 0

  for (const spy of Object.values(repoMocks)) {
    spy.mockReset()
  }

  for (const spy of Object.values(productionLineMocks)) {
    spy.mockReset()
  }

  for (const spy of Object.values(stationRepoMocks)) {
    spy.mockReset()
  }

  for (const spy of Object.values(localDbMocks)) {
    spy.mockReset()
  }

  /**
   * PENYIMPANAN PERANGKAT DIBERSIHKAN — jebakan #4 pada docblock. Layar ini
   * MENULIS `msl_production_line_{userId}` setiap kali sebuah line berlaku.
   */
  window.localStorage.clear()

  asRole('operator', 'bu-1')

  repoMocks.fetchBusinessUnits.mockImplementation(() => {
    requests.push(BUSINESS_UNITS_PATH)

    return Promise.resolve(BUSINESS_UNITS)
  })

  repoMocks.fetchPeriods.mockImplementation((scope?: SolidWasteDisposalReportScope) => {
    requests.push(PERIODS_PATH)
    periodsCalls.push(scope ?? {})

    return Promise.resolve([PERIOD, PERIOD_CLOSED])
  })

  repoMocks.fetchLines.mockImplementation((scope?: SolidWasteDisposalReportScope) => {
    requests.push(LINES_PATH)
    linesCalls.push(scope ?? {})

    return Promise.resolve([LINE_3, LINE_4])
  })

  repoMocks.fetchSummary.mockImplementation(
    (periodId: string, scope?: SolidWasteDisposalReportScope) => {
      requests.push(SUMMARY_PATH)
      summaryCalls.push({ periodId, scope: scope ?? {} })

      return Promise.resolve(makeSummary() as SolidWasteDisposalReportSummary)
    },
  )

  repoMocks.exportCsv.mockImplementation(
    (periodId: string, scope?: SolidWasteDisposalReportScope) => {
      requests.push(EXPORT_PATH)
      exportCalls.push({ periodId, scope: scope ?? {} })

      return Promise.resolve(new Blob(['a,b']))
    },
  )

  repoMocks.saveCsvFile.mockImplementation(() => undefined)
})

afterEach(() => {
  // DILEPAS LEBIH DULU, sebelum mock dipulihkan: onUnmounted melepas pendengar
  // online/offline, dan itu harus terjadi selagi spy window masih yang
  // dipasang test ini.
  while (mountedWrappers.length > 0) {
    mountedWrappers.pop()?.unmount()
  }

  while (cleanups.length > 0) {
    cleanups.pop()?.()
  }

  vi.restoreAllMocks()
})

/* ================================================================== */
/* Skenario 1 — JALUR SUKSES, keempat aktor                            */
/* ================================================================== */

describe('jalur sukses', () => {
  // skenario 1 + unit_test_case 17
  it.each(MILL_BOUND_ROLES)(
    'skenario 1: %s melihat kerangka laporan penuh, dan tiap angka sama dengan medan payload-nya',
    async (role) => {
      asRole(role, 'bu-1')

      const wrapper = await mountFullReport()

      expect(exists(wrapper, 'laporan-solid-waste-disposal-mobile')).toBe(true)
      expect(exists(wrapper, 'period-meta')).toBe(true)
      expect(text(wrapper, 'period-meta')).toContain(PERIOD.name)
      expect(exists(wrapper, 'period-status-badge')).toBe(true)
      expect(text(wrapper, 'period-status-badge')).toBe('Terbuka')

      // PETA TESTID → MEDAN PAYLOAD, diperiksa satu per satu dengan toBe.
      // Tidak satu pun di antaranya dapat diturunkan dari yang lain.
      const expected: Record<string, string> = {
        'swd-kpi-load-count': '12',
        'swd-kpi-net-weight-total': '37,5',
        'swd-kpi-net-weight-avg': '9,9',
        'swd-kpi-net-weight-load-count': '5',
        'swd-kpi-missing-net-weight': '7',
        'swd-draft-load-count': '4',
        'swd-daily-total-load-count': '12',
        'swd-daily-total-net-weight-total': '55,0',
        'swd-coverage-percent': '41,3',
        'swd-coverage-denominator': '11',
        'swd-coverage-days-with-load': '4',
        'swd-coverage-days-in-period': '30',
        'swd-compliance-missing-gate-pass': '0',
        'swd-compliance-missing-seal': '3',
      }

      for (const [testid, value] of Object.entries(expected)) {
        expect(rawText(wrapper, testid), `${role} / ${testid}`).toBe(value)
      }

      // KEEMPAT rekap, kedua penghitung kepatuhan, rekap harian + barisan
      // totalnya, blok kelengkapan, dan kedua daftar kejadian HADIR.
      for (const testid of RECAP_TESTIDS) {
        expect(exists(wrapper, testid), testid).toBe(true)
        expect(exists(wrapper, `${testid}-table`), testid).toBe(true)
        expect(exists(wrapper, `${testid}-total`), testid).toBe(true)
      }

      for (const testid of [
        'swd-loads',
        'swd-per-transaction-note',
        'swd-compliance',
        'swd-compliance-note',
        'swd-daily-recap',
        'swd-daily-table',
        'swd-daily-total',
        'swd-daily-total-note',
        'swd-coverage',
        'swd-coverage-note',
        'swd-findings',
        'swd-remarks',
        'swd-absences',
        'swd-export-scope-note',
        'swd-export-csv',
        'back-button',
      ]) {
        expect(exists(wrapper, testid), `${role} / ${testid}`).toBe(true)
      }

      // ANGKANYA BARE — jebakan #5. Satuannya hidup di pil SEBELAHNYA.
      expect(rawText(wrapper, 'swd-kpi-net-weight-total')).not.toContain('MT')
      expect(rawText(wrapper, 'swd-kpi-load-count')).not.toContain('muatan')
      expect(rawText(wrapper, 'swd-coverage-percent')).not.toContain('%')

      // Pemilih Mill TIDAK dirender untuk ketiga peran terikat mill, dan
      // chip mill-nya justru ADA.
      expect(all(wrapper, '[data-testid="mill-select"]'), role).toHaveLength(0)
      expect(exists(wrapper, 'mill-current'), role).toBe(true)
      expect(exists(wrapper, 'production-line-current'), role).toBe(true)
    },
  )

  it('kelima angka pokok dan kelima kartunya hadir — nol kartu min/avg/max per slot waktu', async () => {
    const wrapper = await mountFullReport()

    expect(kpiElements(wrapper)).toHaveLength(5)

    for (const card of [
      'swd-card-load-count',
      'swd-card-net-weight-total',
      'swd-card-net-weight-avg',
      'swd-card-missing-net-weight',
      'swd-card-draft',
    ]) {
      expect(exists(wrapper, card), card).toBe(true)
    }

    // Stasiun ini TIDAK punya grid slot waktu, jadi tidak ada satu pun kartu
    // minimum/rata-rata/maksimum per slot — diasersi atas TESTID.
    for (const absent of ['swd-metric-min', 'swd-metric-max', 'swd-kpi-min', 'swd-kpi-max']) {
      expect(exists(wrapper, absent), absent).toBe(false)
    }
  })

  // unit_test_case 18
  it('unit_test_case 18: loads.load_count dicetak dari payload meski BERBEDA dari penjumlahan rekap', async () => {
    const eleven = recapsOfTwelve()

    // by_waste_type sengaja berjumlah 11, bukan 12.
    eleven.by_waste_type = [
      row('Zpalm shell', 1, 4.5, 1, 4.5),
      row(null, 4, 21.25, 3, 7.08),
      row('Abu boiler', 4, null, 0, null),
      row('Mesocarp fibre', 2, 11.75, 1, 11.75),
    ]

    repoMocks.fetchSummary.mockImplementation((periodId: string) => {
      requests.push(SUMMARY_PATH)
      summaryCalls.push({ periodId, scope: {} })

      return Promise.resolve(makeSummary(eleven) as SolidWasteDisposalReportSummary)
    })

    const wrapper = await mountFullReport()

    expect(rawText(wrapper, 'swd-kpi-load-count')).toBe('12')

    const sum = recapLoadCounts(wrapper, 'swd-recap-waste-type').reduce(
      (total, value) => total + value,
      0,
    )

    expect(sum).toBe(11)
    // Angka payload MENANG atas penjumlahan baris.
    expect(rawText(wrapper, 'swd-kpi-load-count')).not.toBe('11')
  })

  // unit_test_case 19 + skenario 11
  it('unit_test_case 19: net_weight_avg dibaca dari payload, bukan total dibagi penyebut', async () => {
    repoMocks.fetchSummary.mockImplementation((periodId: string) => {
      requests.push(SUMMARY_PATH)
      summaryCalls.push({ periodId, scope: {} })

      return Promise.resolve(
        makeSummary({
          loads: {
            load_count: 12,
            net_weight_total: 100,
            net_weight_avg: 7.25,
            net_weight_load_count: 4,
            missing_net_weight_load_count: 8,
          },
        }) as SolidWasteDisposalReportSummary,
      )
    })

    const wrapper = await mountFullReport()

    // 100 / 4 = 25. Nilai payload 7,25 MENANG.
    expect(rawText(wrapper, 'swd-kpi-net-weight-avg')).toBe('7,25')
    expect(rawText(wrapper, 'swd-kpi-net-weight-avg')).not.toBe('25')

    // Dan tidak ada operator pembagian di jalur render yang menyentuh kedua
    // medan itu — audit statis atas blok skrip, komentar dibuang.
    const code = stripComments(scriptBlockOf(readSource(VIEW_PATH)))

    expect(code).not.toMatch(/net_weight_total\s*\/\s*/)
    expect(code).not.toMatch(/\/\s*net_weight_load_count/)
  })

  // unit_test_case 20 + skenario 21
  it('unit_test_case 20: daily_total dibaca dari payload, bukan dijumlahkan dari daily[]', async () => {
    const threeDays = [
      { date: LOADED_DAYS[0], load_count: 3, net_weight_total: 2.5, net_weight_load_count: 1, net_weight_avg: 2.5 },
      { date: LOADED_DAYS[1], load_count: 4, net_weight_total: 3.5, net_weight_load_count: 1, net_weight_avg: 3.5 },
      { date: LOADED_DAYS[2], load_count: 2, net_weight_total: 1.5, net_weight_load_count: 1, net_weight_avg: 1.5 },
    ]

    repoMocks.fetchSummary.mockImplementation((periodId: string) => {
      requests.push(SUMMARY_PATH)
      summaryCalls.push({ periodId, scope: {} })

      return Promise.resolve(
        makeSummary({
          daily: threeDays,
          daily_total: {
            load_count: 9,
            // 2.5 + 3.5 + 1.5 = 7,5 — angka ini SENGAJA 55.0.
            net_weight_total: 55.0,
            net_weight_load_count: 3,
            net_weight_avg: 7.25,
            _note: NOTE_DAILY_TOTAL,
          },
        }) as SolidWasteDisposalReportSummary,
      )
    })

    const wrapper = await mountFullReport()

    expect(rawText(wrapper, 'swd-daily-total-net-weight-total')).toBe('55,0')
    expect(rawText(wrapper, 'swd-daily-total-load-count')).toBe('9')
    expect(rawText(wrapper, 'swd-daily-total-net-weight-avg')).toBe('7,25')
    // BUKAN hasil penjumlahan kolomnya.
    expect(rawText(wrapper, 'swd-daily-total-net-weight-total')).not.toBe('7,5')

    const code = stripComments(scriptBlockOf(readSource(VIEW_PATH)))

    // Nol reduce/sum di jalur render baris total.
    expect(code).not.toContain('.reduce(')
    expect(code).not.toContain('sum(')
  })

  // unit_test_case 30 + skenario 23
  it('unit_test_case 30: keempat prosa server dirender UTUH — kesetaraan penuh, bukan contains', async () => {
    const wrapper = await mountFullReport()

    const verbatim: Record<string, string> = {
      'swd-note-no-hourly': NOTE_NO_HOURLY,
      'swd-note-shift': NOTE_SHIFT_NOT_PUBLISHED,
      'swd-note-absences': NOTE_ABSENCES,
      'swd-compliance-note': NOTE_COMPLIANCE,
      // DIVERGENSI 4 — ada di payload nyata, tidak ada di success_schema.
      'swd-daily-total-note': NOTE_DAILY_TOTAL,
    }

    for (const [testid, note] of Object.entries(verbatim)) {
      expect(rawText(wrapper, testid), testid).toBe(normalize(note))
      // Nol elipsis, nol pemotongan.
      expect(rawText(wrapper, testid), testid).not.toContain('…')
    }
  })
})

/* ================================================================== */
/* unit_test_case 21-25 + skenario 9/12/13 — null PER MEDAN             */
/* ================================================================== */

describe('null diputuskan PER MEDAN', () => {
  function summaryWith(overrides: Record<string, unknown>): void {
    repoMocks.fetchSummary.mockImplementation((periodId: string) => {
      requests.push(SUMMARY_PATH)
      summaryCalls.push({ periodId, scope: {} })

      return Promise.resolve(makeSummary(overrides) as SolidWasteDisposalReportSummary)
    })
  }

  // unit_test_case 21
  it('unit_test_case 21: net_weight_total null dicetak "—", BUKAN 0, sementara load_count tetap tercetak', async () => {
    summaryWith({
      loads: {
        load_count: 10,
        net_weight_total: null,
        net_weight_avg: 4.5,
        net_weight_load_count: 3,
        missing_net_weight_load_count: 7,
      },
    })

    const wrapper = await mountFullReport()

    expect(rawText(wrapper, 'swd-kpi-net-weight-total')).toBe('—')

    for (const notEqual of ['0', '0,00', '0,0', '0,00 MT']) {
      expect(rawText(wrapper, 'swd-kpi-net-weight-total'), notEqual).not.toBe(notEqual)
    }

    // PER MEDAN, bukan lewat satu bawaan di satu tempat: medan sebelahnya
    // tetap mencetak angkanya.
    expect(rawText(wrapper, 'swd-kpi-load-count')).toBe('10')
    expect(rawText(wrapper, 'swd-kpi-net-weight-avg')).toBe('4,5')
  })

  // unit_test_case 22 + skenario 22 (mount kedua)
  it('unit_test_case 22: completeness.percent null dicetak "—" dengan kalimat periode belum dimulai', async () => {
    summaryWith({
      completeness: { days_in_period: 30, days_with_load: 0, days_counted: 0, percent: null },
    })

    const wrapper = await mountFullReport()

    expect(rawText(wrapper, 'swd-coverage-percent')).toBe('—')
    expect(exists(wrapper, 'swd-coverage-not-started-note')).toBe(true)
    expect(text(wrapper, 'swd-coverage-not-started-note')).toContain('belum dimulai')
    // URUTAN PEMERIKSAAN: days_counted === 0 dulu, jadi keterangan "masih
    // berjalan" TIDAK ikut terender.
    expect(exists(wrapper, 'swd-coverage-running-note')).toBe(false)

    // Nol '0,0%' / '0%' DI DALAM blok kelengkapan — diperiksa pada elemen
    // blok itu, bukan pada halaman.
    const coverage = normalize(text(wrapper, 'swd-coverage'))

    expect(coverage).not.toContain('0,0%')
    expect(coverage).not.toContain('0%')
  })

  // unit_test_case 23 + skenario 12
  it('unit_test_case 23: seluruh muatan belum ditimbang — kedua angka berat "—" dengan penyebut nol', async () => {
    summaryWith({
      loads: {
        load_count: 8,
        net_weight_total: null,
        net_weight_avg: null,
        net_weight_load_count: 0,
        missing_net_weight_load_count: 8,
      },
    })

    const wrapper = await mountFullReport()

    expect(rawText(wrapper, 'swd-kpi-net-weight-total')).toBe('—')
    expect(rawText(wrapper, 'swd-kpi-net-weight-avg')).toBe('—')
    expect(rawText(wrapper, 'swd-kpi-net-weight-load-count')).toBe('0')
    expect(rawText(wrapper, 'swd-kpi-missing-net-weight')).toBe('8')
    expect(rawText(wrapper, 'swd-kpi-load-count')).toBe('8')

    // Nol elemen bertekst '0,00 MT' DI BLOK BERAT — dan bentuk itu memang
    // mustahil dihasilkan layar ini (jebakan #5): satuannya di pil
    // sebelahnya, dan pil itu tidak dirender ketika nilainya null.
    for (const card of ['swd-card-net-weight-total', 'swd-card-net-weight-avg']) {
      expect(normalize(text(wrapper, card)), card).not.toContain('0,00 MT')
      expect(
        all(wrapper, `[data-testid="${card}"] .metric-unit-pill`),
        `${card} tetap merender satuan di samping tanda pisah`,
      ).toHaveLength(0)
    }
  })

  // unit_test_case 24 + skenario 13
  it('unit_test_case 24: penyebut rata-rata ada DI DALAM kartu rata-rata, diasersi lewat hubungan DOM', async () => {
    summaryWith({
      loads: {
        load_count: 10,
        net_weight_total: 60,
        net_weight_avg: 9.9,
        net_weight_load_count: 6,
        missing_net_weight_load_count: 4,
      },
    })

    const wrapper = await mountFullReport()

    const avgCard = element(wrapper, 'swd-card-net-weight-avg')

    expect(avgCard.contains(element(wrapper, 'swd-kpi-net-weight-avg'))).toBe(true)
    expect(avgCard.contains(element(wrapper, 'swd-kpi-net-weight-load-count'))).toBe(true)
    // Penyebutnya 6, BUKAN 10.
    expect(rawText(wrapper, 'swd-kpi-net-weight-load-count')).toBe('6')
    expect(rawText(wrapper, 'swd-kpi-load-count')).toBe('10')
    expect(normalize(text(wrapper, 'swd-card-net-weight-avg'))).toContain('penyebutnya')

    // Dan kartu penimbangan-belum-selesai BUKAN keturunan maupun leluhurnya.
    const missingCard = element(wrapper, 'swd-card-missing-net-weight')

    expect(avgCard.contains(missingCard)).toBe(false)
    expect(missingCard.contains(avgCard)).toBe(false)
    expect(rawText(wrapper, 'swd-kpi-missing-net-weight')).toBe('4')

    // Setiap baris keempat rekap DAN setiap baris daily juga mencetak
    // net_weight_load_count-nya sendiri.
    for (const testid of RECAP_TESTIDS) {
      const denominators = all(wrapper, `[data-testid^="${testid}-net-weight-load-count-"]`)
      const rows = all(wrapper, `[data-testid^="${testid}-row-"]`)

      expect(denominators.length, testid).toBe(rows.length)
      expect(denominators.length, testid).toBeGreaterThan(0)
    }

    expect(all(wrapper, '[data-testid^="swd-daily-net-weight-load-count-"]').length).toBe(
      all(wrapper, '[data-testid^="swd-daily-row-"]').length,
    )
  })

  // unit_test_case 25
  it('unit_test_case 25: penimbangan belum selesai adalah kartu TERSENDIRI dengan kalimatnya sendiri', async () => {
    const wrapper = await mountFullReport()

    expect(exists(wrapper, 'swd-card-missing-net-weight')).toBe(true)

    const cardText = normalize(text(wrapper, 'swd-card-missing-net-weight')).toLowerCase()

    expect(cardText).toContain('penimbangan belum selesai')
    expect(cardText).not.toContain('data tidak lengkap')
    expect(rawText(wrapper, 'swd-kpi-missing-net-weight')).toBe('7')
  })

  // skenario 9
  it('skenario 9: periode tanpa muatan — kerangka penuh dengan keadaan kosong yang menyebut periode dan line', async () => {
    repoMocks.fetchSummary.mockImplementation((periodId: string) => {
      requests.push(SUMMARY_PATH)
      summaryCalls.push({ periodId, scope: {} })

      return Promise.resolve(emptyPeriodSummary() as SolidWasteDisposalReportSummary)
    })

    const wrapper = await mountFullReport()

    expect(exists(wrapper, 'swd-empty-period-note')).toBe(true)

    const note = normalize(text(wrapper, 'swd-empty-period-note'))

    // SATU NEEDLE PER PEMANGGILAN — jebakan #8.
    expect(note).toContain(PERIOD.name)
    expect(note).toContain(LINE_3.name)

    expect(rawText(wrapper, 'swd-kpi-net-weight-total')).toBe('—')
    expect(rawText(wrapper, 'swd-kpi-net-weight-avg')).toBe('—')
    expect(rawText(wrapper, 'swd-kpi-load-count')).toBe('0')

    // Blok kelengkapan TETAP dirender dengan days_in_period-nya, dan
    // kerangka laporannya utuh — bukan layar putih.
    expect(exists(wrapper, 'swd-coverage')).toBe(true)
    expect(rawText(wrapper, 'swd-coverage-days-in-period')).toBe('30')
    expect(exists(wrapper, 'swd-daily-table')).toBe(true)
    expect(exists(wrapper, 'swd-daily-empty')).toBe(true)
    expect(exists(wrapper, 'swd-findings-empty')).toBe(true)
    expect(exists(wrapper, 'swd-remarks-empty')).toBe(true)

    for (const testid of RECAP_TESTIDS) {
      expect(exists(wrapper, `${testid}-empty`), testid).toBe(true)
    }
  })
})

/* ================================================================== */
/* unit_test_case 26-29 + skenario 10/14/15/16/17 — KEEMPAT REKAP      */
/* ================================================================== */

describe('keempat rekap kategoris', () => {
  function summaryWith(overrides: Record<string, unknown>): void {
    repoMocks.fetchSummary.mockImplementation((periodId: string) => {
      requests.push(SUMMARY_PATH)
      summaryCalls.push({ periodId, scope: {} })

      return Promise.resolve(makeSummary(overrides) as SolidWasteDisposalReportSummary)
    })
  }

  // unit_test_case 26 + 15 (skenario)
  it('unit_test_case 26: baris value null dicetak "tidak tercatat" pada KEEMPAT rekap, tepat satu baris', async () => {
    const wrapper = await mountFullReport()

    for (const testid of RECAP_TESTIDS) {
      const values = recapValues(wrapper, testid)
      const notRecorded = values.filter((value) => value === 'tidak tercatat')

      expect(notRecorded, testid).toHaveLength(1)
      // Nol baris bertekst 'null', 'undefined' atau kosong.
      for (const value of values) {
        expect(value, testid).not.toBe('null')
        expect(value, testid).not.toBe('undefined')
        expect(value, testid).not.toBe('')
      }
    }
  })

  // unit_test_case 27 + skenario 16
  it('unit_test_case 27: URUTAN DOM keempat rekap SAMA DENGAN urutan payload — klien tidak menyusun ulang', async () => {
    // Urutan tiap rekap SENGAJA berbeda-beda, bukan alfabetis dan bukan urut
    // jumlah; baris 'tidak tercatat' bertonase besar ada di POSISI KEDUA pada
    // rekap pertama dan di posisi lain pada ketiga rekap sisanya.
    const ordered = {
      by_waste_type: [
        row('Zpalm shell', 1, 4.5, 1, 4.5),
        row(null, 9, 61.25, 3, 20.41),
        row('Abu boiler', 4, null, 0, null),
        row('Mesocarp fibre', 2, 11.75, 1, 11.75),
      ],
      by_source_station: [
        row(null, 9, 61.25, 3, 20.41),
        row('Zpalm shell', 1, 4.5, 1, 4.5),
        row('Mesocarp fibre', 2, 11.75, 1, 11.75),
        row('Abu boiler', 4, null, 0, null),
      ],
      by_disposal_site: [
        row('Mesocarp fibre', 2, 11.75, 1, 11.75),
        row('Abu boiler', 4, null, 0, null),
        row(null, 9, 61.25, 3, 20.41),
        row('Zpalm shell', 1, 4.5, 1, 4.5),
      ],
      by_purpose_end_use: [
        row('Abu boiler', 4, null, 0, null),
        row('Mesocarp fibre', 2, 11.75, 1, 11.75),
        row('Zpalm shell', 1, 4.5, 1, 4.5),
        row(null, 9, 61.25, 3, 20.41),
      ],
    }

    summaryWith(ordered)

    const wrapper = await mountFullReport()

    const expectedOrder: Record<string, string[]> = {
      'swd-recap-waste-type': ['Zpalm shell', 'tidak tercatat', 'Abu boiler', 'Mesocarp fibre'],
      'swd-recap-source-station': [
        'tidak tercatat',
        'Zpalm shell',
        'Mesocarp fibre',
        'Abu boiler',
      ],
      'swd-recap-disposal-site': [
        'Mesocarp fibre',
        'Abu boiler',
        'tidak tercatat',
        'Zpalm shell',
      ],
      'swd-recap-purpose-end-use': [
        'Abu boiler',
        'Mesocarp fibre',
        'Zpalm shell',
        'tidak tercatat',
      ],
    }

    for (const [testid, order] of Object.entries(expectedOrder)) {
      // ARRAY BERURUT, bukan himpunan: pengurutan ulang apa pun gagal.
      expect(recapValues(wrapper, testid), testid).toEqual(order)
    }

    // Baris 'tidak tercatat' bertonase besar MUNCUL DI ATAS pada rekap
    // pertama — tidak dipindah ke akhir.
    expect(recapValues(wrapper, 'swd-recap-waste-type')[1]).toBe('tidak tercatat')

    // Nol pemanggilan sort/toSorted di jalur render rekap.
    const code = stripComments(scriptBlockOf(readSource(VIEW_PATH)))

    expect(code).not.toContain('.sort(')
    expect(code).not.toContain('.toSorted(')
  })

  // unit_test_case 28 + skenario 10/15
  it('unit_test_case 28: penjumlahan load_count TIAP rekap sama dengan loads.load_count', async () => {
    const wrapper = await mountFullReport()

    expect(rawText(wrapper, 'swd-kpi-load-count')).toBe('12')

    // EMPAT ASERSI TERPISAH, satu per rekap. Baris 'tidak tercatat' IKUT
    // dijumlahkan dan tidak dibuang.
    for (const testid of RECAP_TESTIDS) {
      const counts = recapLoadCounts(wrapper, testid)

      expect(counts.length, testid).toBeGreaterThan(0)
      expect(
        counts.reduce((total, value) => total + value, 0),
        `${testid} tidak menjumlah ke 12`,
      ).toBe(12)
    }

    // Dan nol elemen mencetak 3 sebagai jumlah muatan periode (jumlah
    // DOKUMEN catatan harian, bukan jumlah muatan).
    expect(rawText(wrapper, 'swd-kpi-load-count')).not.toBe('3')
  })

  // unit_test_case 29 + skenario 15
  it('unit_test_case 29: dua ejaan yang hanya berbeda besar-kecil huruf tetap dua baris; nol "lainnya"', async () => {
    const caseSensitive = [
      row('Abu boiler', 4, 10.5, 2, 5.25),
      row('abu boiler', 3, 7.5, 1, 7.5),
      row(null, 4, 6.5, 1, 6.5),
      row('Serat tunggal', 1, 1.5, 1, 1.5),
    ]

    summaryWith({
      by_waste_type: caseSensitive,
      by_source_station: caseSensitive,
      by_disposal_site: caseSensitive,
      by_purpose_end_use: caseSensitive,
    })

    const wrapper = await mountFullReport()

    for (const testid of RECAP_TESTIDS) {
      const values = recapValues(wrapper, testid)

      // DUA BARIS BERBEDA, bukan satu baris gabungan.
      expect(values, testid).toContain('Abu boiler')
      expect(values, testid).toContain('abu boiler')
      // Baris berfrekuensi satu tetap punya barisnya sendiri.
      expect(values, testid).toContain('Serat tunggal')
      // Jumlah baris DOM sama dengan panjang array payload.
      expect(values.length, testid).toBe(caseSensitive.length)

      for (const value of values) {
        expect(value, testid).not.toMatch(/^lainnya$/i)
      }
    }
  })

  // unit_test_case 29 (ekor panjang) + skenario 17
  it('skenario 17: rekap berekor panjang — lima belas baris, delapan di antaranya berjumlah satu, nol pelipatan', async () => {
    const longTail = [
      row('Abu boiler', 20, 40.5, 10, 4.05),
      row(null, 9, 18.5, 4, 4.63),
      row('Mesocarp fibre', 7, 14.5, 3, 4.83),
      row('Zpalm shell', 5, 10.5, 2, 5.25),
      row('Serat A', 3, 6.5, 1, 6.5),
      row('Serat B', 2, 4.5, 1, 4.5),
      row('Tunggal 1', 1, 1.5, 1, 1.5),
      row('Tunggal 2', 1, 1.4, 1, 1.4),
      row('Tunggal 3', 1, 1.3, 1, 1.3),
      row('Tunggal 4', 1, 1.2, 1, 1.2),
      row('Tunggal 5', 1, 1.1, 1, 1.1),
      row('Tunggal 6', 1, null, 0, null),
      row('Tunggal 7', 1, 0.9, 1, 0.9),
      row('Tunggal 8', 1, 0.8, 1, 0.8),
      row('Serat C', 2, 3.5, 1, 3.5),
    ]

    summaryWith({ by_waste_type: longTail })

    const wrapper = await mountFullReport()

    const values = recapValues(wrapper, 'swd-recap-waste-type')

    expect(values).toHaveLength(15)
    expect(recapLoadCounts(wrapper, 'swd-recap-waste-type').filter((count) => count === 1)).toHaveLength(8)

    for (const value of values) {
      expect(value).not.toMatch(/^lainnya$/i)
    }

    // Nol pemotongan daftar: tidak ada kontrol yang menyembunyikan baris dari
    // DOM.
    expect(buttonNames(wrapper).join(' | ')).not.toMatch(/tampilkan lebih banyak|lihat semua/i)
  })

  // skenario 14
  it('skenario 14: nilai teks bebas tampil APA ADANYA — nol ember dibuang/dimanfaatkan, nol tabel pemetaan', async () => {
    const freeText = [
      row('Dibakar di insinerator pihak ketiga', 3, 8.5, 2, 4.25),
      row('Dijual ke pengepul', 4, 12.5, 2, 6.25),
      row('Ditimbun di lahan sendiri', 4, 6.5, 1, 6.5),
      row('Dipakai ulang sebagai bahan bakar', 1, 2.5, 1, 2.5),
    ]

    summaryWith({ by_purpose_end_use: freeText })

    const wrapper = await mountFullReport()

    const values = recapValues(wrapper, 'swd-recap-purpose-end-use')

    expect(all(wrapper, '[data-testid^="swd-recap-purpose-end-use-row-"]')).toHaveLength(
      freeText.length,
    )
    // KESAMAAN PENUH per sel — nol penerjemahan ke label.
    expect(values).toEqual(freeText.map((item) => item.value))

    // Nol baris ber-nilai 'dibuang' / 'dimanfaatkan' / 'lainnya' DI DALAM
    // rekap itu — diasersi atas SEL NILAI, bukan atas sapuan teks: prosa
    // kartunya memang menyebut 'pembuangan/pemanfaatan' sebagai nama kolom.
    for (const value of values) {
      expect(value.toLowerCase()).not.toBe('dibuang')
      expect(value.toLowerCase()).not.toBe('dimanfaatkan')
      expect(value.toLowerCase()).not.toBe('lainnya')
    }

    // Dan nol tabel pemetaan nilai→ember di kode view.
    const code = stripComments(scriptBlockOf(readSource(VIEW_PATH)))

    for (const forbidden of ['dibuang', 'dimanfaatkan', 'lainnya', 'BUCKET', 'bucketOf']) {
      expect(code, `view memetakan nilai ke ember lewat ${forbidden}`).not.toContain(forbidden)
    }
  })

  it('baris total tiap rekap DIBACA dari blok angka pokok, bukan dijumlahkan dari barisnya', async () => {
    const wrapper = await mountFullReport()

    for (const testid of RECAP_TESTIDS) {
      const total = normalize(text(wrapper, `${testid}-total`))

      // 37,5 adalah loads.net_weight_total — bukan jumlah kolom netto baris
      // rekap itu (yang berbeda di tiap rekap).
      expect(total, testid).toContain('37,5')
      expect(total, testid).toContain('9,9')
    }
  })

  it('keterangan tiap rekap menyebut jumlah muatan periode dan nama kolom detailnya', async () => {
    const wrapper = await mountFullReport()

    const hints: Record<string, string> = {
      'swd-recap-waste-type': 'jenis limbah padat',
      'swd-recap-source-station': 'stasiun asal',
      'swd-recap-disposal-site': 'lokasi pembuangan/pemanfaatan',
      'swd-recap-purpose-end-use': 'tujuan penggunaan akhir',
    }

    for (const [testid, label] of Object.entries(hints)) {
      const note = normalize(text(wrapper, `${testid}-note`))

      expect(note, testid).toContain(label)
      expect(note, testid).toContain('tidak tercatat')
    }
  })
})

/* ================================================================== */
/* unit_test_case 36 + skenario 21 — REKAP HARIAN                      */
/* ================================================================== */

describe('rekap harian', () => {
  // unit_test_case 36 + skenario 21
  it('unit_test_case 36: satu baris per hari BERMUATAN — tepat sembilan baris, nol baris nol', async () => {
    const wrapper = await mountFullReport()

    expect(all(wrapper, '[data-testid^="swd-daily-row-"]')).toHaveLength(9)

    // HIMPUNAN TANGGAL DOM = himpunan tanggal payload, bukan hanya
    // panjangnya; dan URUTANNYA sama persis dengan urutan payload.
    expect([...dailyDates(wrapper)].sort()).toEqual([...LOADED_DAYS].sort())
    expect(dailyDates(wrapper)).toEqual(DAILY_ORDER)

    // Nol baris untuk 21 tanggal lain, dan nol baris bertekst jumlah muatan 0.
    const counts = all(wrapper, '[data-testid^="swd-daily-load-count-"]').map((cell) =>
      normalize(cell.text()),
    )

    expect(counts).toHaveLength(9)
    expect(counts).not.toContain('0')

    expect(exists(wrapper, 'swd-daily-empty')).toBe(false)
  })

  it('setiap baris harian mencetak penyebut nettonya sendiri, dan sel kosong dirender "—"', async () => {
    const wrapper = await mountFullReport()

    const denominators = all(wrapper, '[data-testid^="swd-daily-net-weight-load-count-"]')

    expect(denominators).toHaveLength(9)

    // Baris yang nettonya null dirender '—' pada kolom nettonya, dan
    // penyebutnya 0 — bukan 0 pada keduanya.
    const nullDate = DAILY_ORDER[1]

    expect(rawText(wrapper, `swd-daily-net-weight-load-count-${nullDate}`)).toBe('0')
    expect(normalize(text(wrapper, `swd-daily-row-${nullDate}`))).toContain('—')
  })
})

/* ================================================================== */
/* unit_test_case 37 + skenario 18 — JEJAK KEPATUHAN                   */
/* ================================================================== */

describe('jejak kepatuhan', () => {
  // unit_test_case 37 + skenario 18
  it('unit_test_case 37: penghitung NOL tetap dirender, dan prosanya tampil pada KEDUA keadaan', async () => {
    const fixtures = [
      { label: 'A — penuh', gatePass: 12, seal: 12 },
      { label: 'B — nol', gatePass: 0, seal: 0 },
    ]

    for (const fixture of fixtures) {
      repoMocks.fetchSummary.mockImplementation((periodId: string) => {
        requests.push(SUMMARY_PATH)
        summaryCalls.push({ periodId, scope: {} })

        return Promise.resolve(
          makeSummary({
            compliance: {
              missing_gate_pass_count: fixture.gatePass,
              missing_seal_count: fixture.seal,
              _note: NOTE_COMPLIANCE,
            },
          }) as SolidWasteDisposalReportSummary,
        )
      })

      const wrapper = await mountFullReport()

      expect(exists(wrapper, 'swd-compliance-missing-gate-pass'), fixture.label).toBe(true)
      expect(exists(wrapper, 'swd-compliance-missing-seal'), fixture.label).toBe(true)
      expect(rawText(wrapper, 'swd-compliance-missing-gate-pass'), fixture.label).toBe(
        String(fixture.gatePass),
      )
      expect(rawText(wrapper, 'swd-compliance-missing-seal'), fixture.label).toBe(
        String(fixture.seal),
      )
      // Prosa yang SAMA PENUH pada kedua keadaan.
      expect(rawText(wrapper, 'swd-compliance-note'), fixture.label).toBe(normalize(NOTE_COMPLIANCE))

      // Nol rata-rata, nol pengurutan, nol grafik di dalam blok kepatuhan.
      expect(all(wrapper, '[data-testid^="swd-compliance-chart"]'), fixture.label).toHaveLength(0)
      expect(all(wrapper, '[data-testid^="swd-compliance-avg"]'), fixture.label).toHaveLength(0)
      expect(all(wrapper, '[data-testid="swd-compliance"] canvas'), fixture.label).toHaveLength(0)
      expect(all(wrapper, '[data-testid="swd-compliance"] svg'), fixture.label).toHaveLength(0)

      // Blok kepatuhan BUKAN keturunan kartu loads — ia section-nya sendiri.
      expect(element(wrapper, 'swd-loads').contains(element(wrapper, 'swd-compliance'))).toBe(false)

      wrapper.unmount()
      mountedWrappers.pop()
    }
  })

  it('kartu kepatuhan menyebut penyebutnya tanpa satu pun pengurangan di klien', async () => {
    const wrapper = await mountFullReport()

    // "dari 12 muatan periode ini" — penghitung yang diterbitkan PLUS
    // penyebutnya. Pengurangan load_count − missing_gate_pass_count milik
    // blade web TIDAK disalin ke sini.
    expect(normalize(text(wrapper, 'swd-compliance'))).toContain('dari 12 muatan')

    const code = stripComments(scriptBlockOf(readSource(VIEW_PATH)))

    expect(code).not.toMatch(/load_count\s*-\s*/)
    expect(code).not.toMatch(/missing_gate_pass_count\s*[-+*/]/)
  })
})

/* ================================================================== */
/* unit_test_case 38 + skenario 19 — TEMUAN dan CATATAN                */
/* ================================================================== */

describe('temuan dan catatan', () => {
  // unit_test_case 38 + skenario 19
  it('unit_test_case 38: dua bagian TERPISAH, tidak saling bersarang, satu muatan dapat muncul di keduanya', async () => {
    const wrapper = await mountFullReport()

    const findings = element(wrapper, 'swd-findings')
    const remarks = element(wrapper, 'swd-remarks')

    // CONTAINS DUA ARAH, keduanya false.
    expect(findings.contains(remarks)).toBe(false)
    expect(remarks.contains(findings)).toBe(false)

    expect(all(wrapper, '[data-testid^="swd-findings-row-"]')).toHaveLength(1)
    expect(all(wrapper, '[data-testid^="swd-remarks-row-"]')).toHaveLength(1)

    // TANGGAL YANG SAMA, teks BERBEDA — satu muatan yang mengisi kedua kolom.
    expect(wrapper.get('[data-testid="swd-findings-row-0"]').attributes('data-date')).toBe(
      LOADED_DAYS[0],
    )
    expect(wrapper.get('[data-testid="swd-remarks-row-0"]').attributes('data-date')).toBe(
      LOADED_DAYS[0],
    )

    // Teks dirender PENUH — nol pemendekan, nol elipsis.
    expect(normalize(text(wrapper, 'swd-findings-row-0'))).toContain(
      'Ceceran limbah di jalur keluar gerbang timbang.',
    )
    expect(normalize(text(wrapper, 'swd-remarks-row-0'))).toContain(
      'Kendaraan menunggu 40 menit di pos keamanan.',
    )
    expect(normalize(text(wrapper, 'swd-findings-row-0'))).not.toContain('…')
  })

  it('unit_test_case 38 (mount kedua): kedua daftar kosong menampilkan keterangannya, bukan daftar kosong tanpa penjelasan', async () => {
    repoMocks.fetchSummary.mockImplementation((periodId: string) => {
      requests.push(SUMMARY_PATH)
      summaryCalls.push({ periodId, scope: {} })

      return Promise.resolve(
        makeSummary({ text_entries: { findings: [], remarks: [] } }) as SolidWasteDisposalReportSummary,
      )
    })

    const wrapper = await mountFullReport()

    expect(exists(wrapper, 'swd-findings')).toBe(true)
    expect(exists(wrapper, 'swd-remarks')).toBe(true)
    expect(exists(wrapper, 'swd-findings-empty')).toBe(true)
    expect(exists(wrapper, 'swd-remarks-empty')).toBe(true)
    expect(all(wrapper, '[data-testid^="swd-findings-row-"]')).toHaveLength(0)
    expect(all(wrapper, '[data-testid^="swd-remarks-row-"]')).toHaveLength(0)
  })

  it('entri tanpa tanggal kejadian tetap sebuah catatan, dengan kalimat tanggalnya sendiri', async () => {
    repoMocks.fetchSummary.mockImplementation((periodId: string) => {
      requests.push(SUMMARY_PATH)
      summaryCalls.push({ periodId, scope: {} })

      return Promise.resolve(
        makeSummary({
          text_entries: {
            findings: [{ date: null, value: 'Temuan tanpa tanggal kejadian.' }],
            remarks: [],
          },
        }) as SolidWasteDisposalReportSummary,
      )
    })

    const wrapper = await mountFullReport()

    expect(wrapper.get('[data-testid="swd-findings-row-0"]').attributes('data-date')).toBe('')
    expect(normalize(text(wrapper, 'swd-findings-row-0'))).toContain('tanpa tanggal kejadian')
  })
})

/* ================================================================== */
/* unit_test_case 33-35 + skenario 20/24 — DRAFT dan TANPA TANGGAL     */
/* ================================================================== */

describe('draft dan muatan tanpa tanggal', () => {
  // unit_test_case 35 + skenario 20
  it('unit_test_case 35: draft dinyatakan tersendiri dan muatan draft IKUT terhitung', async () => {
    const wrapper = await mountFullReport()

    expect(rawText(wrapper, 'swd-draft-load-count')).toBe('4')
    expect(normalize(text(wrapper, 'swd-draft-note')).toLowerCase()).toContain('ikut terhitung')
    // 12, BUKAN 8 — draft tidak disaring keluar.
    expect(rawText(wrapper, 'swd-kpi-load-count')).toBe('12')
    expect(rawText(wrapper, 'swd-kpi-load-count')).not.toBe('8')

    // Penjumlahan tiap rekap tetap 12, dan daily_total juga dihitung atas 12.
    for (const testid of RECAP_TESTIDS) {
      expect(
        recapLoadCounts(wrapper, testid).reduce((total, value) => total + value, 0),
        testid,
      ).toBe(12)
    }

    expect(rawText(wrapper, 'swd-daily-total-load-count')).toBe('12')

    // NOL penyaring status di jalur render.
    const code = stripComments(scriptBlockOf(readSource(VIEW_PATH)))

    expect(code).not.toMatch(/\.filter\([^)]*draft/i)
    expect(code).not.toContain("'final'")
    expect(code).not.toContain('=== \'draft\'')
  })

  // unit_test_case 33 + skenario 24 (a)
  it('unit_test_case 33: undated_load_count = 0 → blok TIDAK dirender sama sekali', async () => {
    const wrapper = await mountFullReport()

    expect(wrapper.find('[data-testid="swd-undated-note"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="swd-undated-load-count"]').exists()).toBe(false)

    // Dan nol teks 'tanpa tanggal' di dalam kerangka laporan — pada fixture
    // ini kedua daftar kejadian pun bertanggal.
    expect(normalize(text(wrapper, 'swd-loads'))).not.toContain('tanpa tanggal')
  })

  // unit_test_case 34 + skenario 24 (b) — TEST TERPISAH, bukan satu test bercabang
  it('unit_test_case 34: undated_load_count > 0 → blok DIRENDER dengan angkanya', async () => {
    repoMocks.fetchSummary.mockImplementation((periodId: string) => {
      requests.push(SUMMARY_PATH)
      summaryCalls.push({ periodId, scope: {} })

      return Promise.resolve(
        makeSummary({ undated_load_count: 3 }) as SolidWasteDisposalReportSummary,
      )
    })

    const wrapper = await mountFullReport()

    expect(exists(wrapper, 'swd-undated-note')).toBe(true)
    expect(exists(wrapper, 'swd-undated-load-count')).toBe(true)
    expect(rawText(wrapper, 'swd-undated-load-count')).toBe('3')
  })
})

/* ================================================================== */
/* unit_test_case 31-32 + skenario 23/25 — KETIADAAN YANG DISENGAJA    */
/* ================================================================== */

describe('ketiadaan yang disengaja, dinyatakan', () => {
  /**
   * TESTID SEBARAN YANG DISALIN dari laporan yang MEMANG punya blok itu
   * (laporan per-transaksi sebelumnya), ditambah nama yang disebut tech spec
   * layar ini. Mengasersi ketiadaan atas nama yang TIDAK pernah dipakai
   * siapa pun adalah asersi yang tidak dapat gagal; inilah sebabnya
   * daftarnya disalin, bukan dikarang.
   */
  const HOURLY_TESTIDS = [
    'swd-busiest-hour',
    'swd-empty-hour-count',
    'hourly-dispatch',
    'hourly-receive',
    'wb-hourly-table',
    'swd-hourly-table',
  ]

  const SHIFT_TESTIDS = ['swd-shift-column', 'swd-shift-filter', 'swd-recap-shift']

  // unit_test_case 31 + skenario 23
  it('unit_test_case 31: NOL kunci hourly dibaca klien dan NOL elemen sebaran per jam di DOM', async () => {
    const { proxy, readKeys } = recordingPayload(
      makeSummary({
        // KUNCI TAMBAHAN yang tidak pernah diterbitkan server.
        hourly: [{ hour: 7, load_count: 2 }],
        busiest_hour: 7,
        empty_hour_count: 22,
      }),
    )

    repoMocks.fetchSummary.mockImplementation((periodId: string) => {
      requests.push(SUMMARY_PATH)
      summaryCalls.push({ periodId, scope: {} })

      return Promise.resolve(proxy as SolidWasteDisposalReportSummary)
    })

    const wrapper = await mountFullReport()

    // Layarnya memang membaca payload-nya — kalau tidak, asersi di bawah
    // hampa.
    expect(readKeys).toContain('loads')
    expect(readKeys).toContain('_note_no_hourly')

    for (const key of ['hourly', 'busiest_hour', 'empty_hour_count']) {
      expect(readKeys, `klien membaca kunci ${key}`).not.toContain(key)
    }

    // ASERSI LEWAT TESTID DAN ROLE — BUKAN sapuan teks halaman: prosa
    // _note_no_hourly MEMUAT kata 'jam', dan layar merendernya UTUH.
    expect(all(wrapper, '[data-testid^="swd-hourly"]')).toHaveLength(0)

    for (const testid of HOURLY_TESTIDS) {
      expect(exists(wrapper, testid), testid).toBe(false)
    }

    expect(headingTexts(wrapper).filter((heading) => /per jam/i.test(heading))).toHaveLength(0)

    // Dan prosa yang MENYATAKAN ketiadaannya memang terender utuh.
    expect(rawText(wrapper, 'swd-note-no-hourly')).toBe(normalize(NOTE_NO_HOURLY))
    expect(rawText(wrapper, 'swd-note-absences')).toBe(normalize(NOTE_ABSENCES))
  })

  // unit_test_case 11 (bagian Proxy)
  it('nol kunci gross_weight/tare_weight dibaca — netto adalah kolom TURUNAN SERVER', async () => {
    const { proxy, readKeys } = recordingPayload(makeSummary())

    repoMocks.fetchSummary.mockImplementation((periodId: string) => {
      requests.push(SUMMARY_PATH)
      summaryCalls.push({ periodId, scope: {} })

      return Promise.resolve(proxy as SolidWasteDisposalReportSummary)
    })

    await mountFullReport()

    expect(readKeys).toContain('loads')

    for (const key of ['gross_weight', 'tare_weight', 'gross_weight_mt', 'tare_weight_mt']) {
      expect(readKeys, `klien membaca kunci ${key}`).not.toContain(key)
    }
  })

  // unit_test_case 32 + skenario 25
  it('unit_test_case 32: nol kolom Shift di layar dan nol kontrol xlsx — lewat TESTID dan ROLE', async () => {
    const wrapper = await mountFullReport()

    expect(all(wrapper, '[data-testid^="swd-recap-shift"]')).toHaveLength(0)

    for (const testid of SHIFT_TESTIDS) {
      expect(exists(wrapper, testid), testid).toBe(false)
    }

    // Nol <th>Shift</th> di seluruh layar.
    expect(columnHeaderTexts(wrapper).filter((header) => /^shift$/i.test(header))).toHaveLength(0)

    // Nol tombol xlsx/excel — atas TESTID dan atas NAMA TOMBOL, bukan atas
    // sapuan halaman: [data-testid='swd-note-shift'] dan
    // [data-testid='swd-export-scope-note'] JUSTRU menyebut kata itu untuk
    // menerangkan bahwa tombolnya tidak ada.
    expect(exists(wrapper, 'swd-export-xlsx')).toBe(false)
    expect(buttonNames(wrapper).filter((name) => /xlsx|excel/i.test(name))).toHaveLength(0)

    // Dan prosa giliran kerja terender UTUH.
    expect(rawText(wrapper, 'swd-note-shift')).toBe(normalize(NOTE_SHIFT_NOT_PUBLISHED))
  })

  // skenario 25 (bagian ekspor)
  it('skenario 25: header CSV DISALIN dari EXPORT_HEADER — 22 kolom, dan Shift ADA di dalamnya', () => {
    /**
     * DUA PULUH DUA KOLOM: 5 konteks (EXPORT_CONTEXT_COLUMN_COUNT) + 17 kolom
     * detail (DETAIL_EXPORT_FIELDS). DISALIN VERBATIM dari
     * SolidWasteDisposalReportService::EXPORT_HEADER (± baris 316) — nama,
     * EJAAN dan URUTANNYA — dan BUKAN dikarang di sini.
     *
     * Kenapa ini layak dicatat: pada screen-161 stub CSV dikarang dan
     * menghasilkan 22 kolom sementara header nyata punya 21. Seandainya angka
     * asersinya yang disesuaikan, seluruh berkas akan hijau sambil
     * membuktikan NOL hal tentang berkas yang sungguh diunduh pengguna —
     * layar mengunduh dari endpoint ASLI, bukan dari stub.
     */
    const EXPORT_HEADER = [
      'Periode',
      'Mill',
      'Production Line',
      'Tanggal Record',
      'Status',
      'Tanggal Kejadian',
      'Shift',
      'Weighbridge Ticket No',
      'Vehicle No',
      'Driver Name',
      'Solid Waste Type',
      'Source Station',
      'Gross Weight (MT)',
      'Tare Weight (MT)',
      'Net Weight (MT)',
      'Disposal/Utilization Site',
      'Purpose/End Use',
      'Gate Pass No',
      'Security Seal No',
      'Operator ID',
      'Remarks',
      'Findings',
    ]

    expect(EXPORT_HEADER).toHaveLength(22)
    expect(EXPORT_HEADER.slice(0, 5)).toEqual([
      'Periode',
      'Mill',
      'Production Line',
      'Tanggal Record',
      'Status',
    ])

    const headerLine = EXPORT_HEADER.join(',')

    // SATU NEEDLE PER PEMANGGILAN — jebakan #8.
    for (const column of [
      'Shift',
      'Weighbridge Ticket No',
      'Vehicle No',
      'Driver Name',
      'Gate Pass No',
      'Security Seal No',
      'Operator ID',
    ]) {
      expect(headerLine, column).toContain(column)
    }

    expect(headerLine.split(',')).toHaveLength(22)
  })
})

/* ================================================================== */
/* skenario 22 — KELENGKAPAN PENCATATAN                                */
/* ================================================================== */

describe('kelengkapan pencatatan', () => {
  it('skenario 22: periode berjalan — penyebutnya hari yang sudah lewat, dan hal itu dinyatakan', async () => {
    repoMocks.fetchSummary.mockImplementation((periodId: string) => {
      requests.push(SUMMARY_PATH)
      summaryCalls.push({ periodId, scope: {} })

      return Promise.resolve(
        makeSummary({
          completeness: { days_in_period: 30, days_with_load: 4, days_counted: 11, percent: 36.4 },
        }) as SolidWasteDisposalReportSummary,
      )
    })

    const wrapper = await mountFullReport()

    expect(rawText(wrapper, 'swd-coverage-percent')).toBe('36,4')
    // Penyebutnya 11, BUKAN 30.
    expect(rawText(wrapper, 'swd-coverage-denominator')).toBe('11')
    expect(rawText(wrapper, 'swd-coverage-days-in-period')).toBe('30')
    expect(exists(wrapper, 'swd-coverage-running-note')).toBe(true)
    expect(normalize(text(wrapper, 'swd-coverage-running-note'))).toContain('sampai hari ini')
    expect(exists(wrapper, 'swd-coverage-not-started-note')).toBe(false)
  })

  it('periode berjalan karena STATUS terbuka pun menyatakan hal yang sama, meski seluruh harinya sudah lewat', async () => {
    repoMocks.fetchSummary.mockImplementation((periodId: string) => {
      requests.push(SUMMARY_PATH)
      summaryCalls.push({ periodId, scope: {} })

      return Promise.resolve(
        makeSummary({
          // days_counted === days_in_period, tetapi statusnya masih 'open'.
          completeness: { days_in_period: 30, days_with_load: 9, days_counted: 30, percent: 30.0 },
        }) as SolidWasteDisposalReportSummary,
      )
    })

    const wrapper = await mountFullReport()

    expect(exists(wrapper, 'swd-coverage-running-note')).toBe(true)
    expect(rawText(wrapper, 'swd-coverage-percent')).toBe('30,0')
  })
})

/* ================================================================== */
/* unit_test_case 6-9 + skenario 3 — PENJAGAAN PRODUCTION LINE          */
/* ================================================================== */

describe('penjagaan Production Line di SATU fungsi pemuat', () => {
  // unit_test_case 6 + skenario 3 (jalur MUAT AWAL)
  it('unit_test_case 6: line belum dipilih pada MUAT AWAL menahan /summary — DIHITUNG tepat 0', async () => {
    const wrapper = await mountView()

    // DIUKUR, bukan disimpulkan dari angka yang tidak tampil.
    expect(hits(SUMMARY_PATH)).toBe(0)
    expect(hits(PERIODS_PATH)).toBe(1)
    expect(hits(LINES_PATH)).toBe(1)

    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)
    expect(normalize(text(wrapper, 'production-line-required-hint'))).toContain(
      'tidak satu permintaan pun dikirim ke server',
    )
    expect(selectedOptionText(wrapper, 'production-line-select')).toBe(NO_LINE_SELECTED)
    // Tombol ekspor TIDAK ADA di DOM selama belum ada periode DAN line.
    expect(wrapper.find('[data-testid="swd-export-csv"]').exists()).toBe(false)
    expect(kpiElements(wrapper)).toHaveLength(0)
  })

  // unit_test_case 7 + skenario 3 (jalur GANTI PERIODE) — TERPISAH
  it('unit_test_case 7: line belum dipilih pada jalur GANTI PERIODE menahan /summary — tetap 0 sesudah dua pergantian', async () => {
    const wrapper = await mountView()

    await selectPeriod(wrapper, 'p-1')

    expect(hits(SUMMARY_PATH), 'pergantian periode pertama').toBe(0)

    await selectPeriod(wrapper, 'p-2')

    expect(hits(SUMMARY_PATH), 'pergantian periode kedua').toBe(0)
    expect(kpiElements(wrapper)).toHaveLength(0)
  })

  // unit_test_case 8 + skenario 31 (mount b) — TERPISAH
  it('unit_test_case 8: line belum dipilih pada jalur COBA LAGI menahan /summary — tetap 0 sesudah tiga ketukan', async () => {
    repoMocks.fetchPeriods.mockImplementation(() => {
      requests.push(PERIODS_PATH)

      return Promise.reject(NETWORK_ERROR)
    })

    const wrapper = await mountView()

    expect(exists(wrapper, 'network-error')).toBe(true)
    expect(exists(wrapper, 'retry-button')).toBe(true)

    for (let tap = 1; tap <= 3; tap += 1) {
      await wrapper.get('[data-testid="retry-button"]').trigger('click')
      await flushPromises()

      expect(hits(SUMMARY_PATH), `sesudah ketukan ${tap}`).toBe(0)
    }

    // Jalur Coba Lagi adalah pemanggil KETIGA pemuat yang sama, dan ia pun
    // tidak dapat memintas penjagaannya.
    expect(hits(PERIODS_PATH)).toBe(4)
    expect(kpiElements(wrapper)).toHaveLength(0)
  })

  /**
   * unit_test_case 9 — DIVERGENSI 2 pada docblock berkas.
   *
   * Pemuat ringkasan adalah fungsi LOKAL di dalam <script setup> dan tidak
   * dapat di-spy dari luar. Yang dibuktikan sebagai gantinya: KEEMPAT
   * pemanggil pemuat yang sama menghasilkan TEPAT SATU permintaan
   * masing-masing begitu penjagaannya terpenuhi — dan NOL ketika tidak,
   * seperti ketiga test di atas. Penjagaan yang disebar di pemanggil akan
   * membuat salah satu dari delapan angka itu menyimpang.
   */
  it('unit_test_case 9: keempat pemanggil pemuat menghasilkan TEPAT satu permintaan masing-masing', async () => {
    window.localStorage.setItem(REMEMBERED_LINE_KEY, LINE_3.id)

    const wrapper = await mountView()

    // Pemanggil 1 — bootstrap(): TIDAK dapat menghasilkan permintaan, karena
    // periode TIDAK PERNAH dipilihkan. Itu pun penjagaan yang sama.
    expect(hits(SUMMARY_PATH)).toBe(0)

    // Pemanggil 2 — onPeriodChange()
    await selectPeriod(wrapper, 'p-1')
    expect(hits(SUMMARY_PATH), 'onPeriodChange').toBe(1)

    // Pemanggil 3 — onProductionLineChange()
    await selectLine(wrapper, LINE_4.id)
    expect(hits(SUMMARY_PATH), 'onProductionLineChange').toBe(2)

    // Pemanggil 4 — onRetry() → loadSummary(), sesudah satu kegagalan.
    repoMocks.fetchSummary.mockImplementationOnce((periodId: string) => {
      requests.push(SUMMARY_PATH)
      summaryCalls.push({ periodId, scope: {} })

      return Promise.reject(NETWORK_ERROR)
    })

    await selectPeriod(wrapper, 'p-2')
    expect(hits(SUMMARY_PATH), 'onPeriodChange yang gagal').toBe(3)
    expect(exists(wrapper, 'retry-button')).toBe(true)

    await wrapper.get('[data-testid="retry-button"]').trigger('click')
    await flushPromises()

    expect(hits(SUMMARY_PATH), 'onRetry').toBe(4)
    expect(kpiElements(wrapper)).toHaveLength(5)
  })

  it('AUDIT STATIS: penjagaan period_id + production_line_id ada SEKALI, di dalam loadSummary', async () => {
    const code = stripComments(scriptBlockOf(readSource(VIEW_PATH)))
    const start = code.indexOf('async function loadSummary')

    expect(start).toBeGreaterThan(-1)

    const body = code.slice(start, start + 700)

    expect(body).toContain('!periodId || !selectedProductionLineId.value')
    // Dan kelima pemanggilnya memanggil pemuat yang SAMA, bukan masing-masing
    // memeriksa sendiri.
    expect(code.match(/loadSummary\(\)/g)?.length).toBeGreaterThanOrEqual(5)
  })
})

/* ================================================================== */
/* unit_test_case 10-14 + skenario 2/4/5 — INGATAN PERANGKAT            */
/* ================================================================== */

describe('line adalah konteks yang DIPILIH, bukan ikatan akun', () => {
  // unit_test_case 10
  it('unit_test_case 10: kunci penyimpanan sama PENUH dengan yang ditulis layar Daftar Stasiun', async () => {
    const getItemSpy = vi.spyOn(Storage.prototype, 'getItem')

    window.localStorage.setItem(REMEMBERED_LINE_KEY, LINE_3.id)
    getItemSpy.mockClear()

    await mountView()

    // SPY PADA PROTOTYPE menerima panggilan — dibuktikan lebih dulu.
    expect(getItemSpy.mock.calls.length).toBeGreaterThanOrEqual(1)

    // Diasersi atas DAFTAR ARGUMEN YANG DIRATAKAN: sebuah store yang membaca
    // penyimpanan saat mount akan menggeser "panggilan pertama", dan asersi
    // yang bersandar pada urutan itu rapuh tanpa menjadi lebih kuat.
    const keys = getItemSpy.mock.calls.flat().map(String)
    const lineKeys = [...new Set(keys.filter((key) => key.startsWith('msl_production_line')))]

    // KESAMAAN PENUH atas string lengkap — bukan toContain('msl_production_line')
    // dan bukan regex berakhir bebas.
    expect(lineKeys).toEqual(['msl_production_line_u-7'])
    expect(lineKeys[0]).toBe(`msl_production_line_${'u-7'}`)
  })

  // unit_test_case 11 + skenario 2
  it('unit_test_case 11: line yang diingat dipakai tanpa diminta memilih, DAN terbawa ke query /summary', async () => {
    window.localStorage.setItem(REMEMBERED_LINE_KEY, LINE_3.id)

    const wrapper = await mountView()

    // Terikat ke 'l-3' tanpa satu langkah pengguna.
    expect(selectedOptionText(wrapper, 'production-line-select')).toBe(LINE_3.name)
    expect(wrapper.find('[data-testid="production-line-required-hint"]').exists()).toBe(false)

    // PERIODE TIDAK PERNAH DIPILIHKAN (jebakan #6): sampai pengguna
    // memilihnya, penghitung /summary tetap 0 — dan itulah sebabnya "tepat
    // satu permintaan" di bawah didahului satu pemilihan periode.
    expect(hits(SUMMARY_PATH)).toBe(0)

    await selectPeriod(wrapper, 'p-1')

    expect(hits(SUMMARY_PATH)).toBe(1)
    // KESETARAAN ATAS NILAI PARAM, bukan substring URL.
    expect(wireParamsOf(summaryCalls[0])).toEqual({ period_id: 'p-1', production_line_id: 'l-3' })

    // skenario 2 — dan tetap dapat diganti.
    await selectLine(wrapper, LINE_4.id)

    expect(hits(SUMMARY_PATH)).toBe(2)
    expect(wireParamsOf(summaryCalls[1]).production_line_id).toBe('l-4')
    expect(selectedOptionText(wrapper, 'production-line-select')).toBe(LINE_4.name)
  })

  // unit_test_case 12 + skenario 5
  it('unit_test_case 12: line yang diingat sudah tidak sah DIBUANG, bukan dipakai diam-diam', async () => {
    const LINE_STALE = { id: 'l-99', name: 'Line Mill Lain', code: 'L99' }

    // Daftar line SENGAJA memuat 'l-99': penolakan datang dari SERVER, dan
    // klien tidak memeriksa kepemilikan sendiri. Tanpa kemunculan itu,
    // resolusi line akan membuang 'l-99' tanpa pernah menanyai server, dan
    // test ini tidak akan menguji cabang 403 sama sekali.
    repoMocks.fetchLines.mockImplementation(() => {
      requests.push(LINES_PATH)

      return Promise.resolve([LINE_STALE, LINE_3, LINE_4])
    })

    repoMocks.fetchSummary.mockImplementation(
      (periodId: string, scope?: SolidWasteDisposalReportScope) => {
        requests.push(SUMMARY_PATH)
        summaryCalls.push({ periodId, scope: scope ?? {} })

        if (scope?.productionLineId === LINE_STALE.id) {
          return Promise.reject(FORBIDDEN_ERROR)
        }

        return Promise.resolve(makeSummary() as SolidWasteDisposalReportSummary)
      },
    )

    window.localStorage.setItem(REMEMBERED_LINE_KEY, LINE_STALE.id)

    const wrapper = await mountView()
    const snapshots: number[] = [kpiElements(wrapper).length]

    await wrapper.vm.$nextTick()
    snapshots.push(kpiElements(wrapper).length)

    await selectPeriod(wrapper, 'p-1')
    snapshots.push(kpiElements(wrapper).length)

    await wrapper.vm.$nextTick()
    snapshots.push(kpiElements(wrapper).length)

    expect(exists(wrapper, 'production-line-rejected')).toBe(true)
    expect(selectedOptionText(wrapper, 'production-line-select')).toBe(NO_LINE_SELECTED)
    // NOL KARTU ANGKA PADA SETIAP SNAPSHOT — angka mill lain tidak pernah
    // terender, bahkan sekejap.
    expect(snapshots).toEqual([0, 0, 0, 0])
    expect(exists(wrapper, 'network-error')).toBe(false)

    // Dan ingatan yang tidak sah TIDAK dikirim ulang pada permintaan
    // berikutnya.
    await selectLine(wrapper, LINE_3.id)

    expect(wireParamsOf(summaryCalls[summaryCalls.length - 1]).production_line_id).toBe('l-3')
    expect(
      summaryCalls.slice(1).map((call) => wireParamsOf(call).production_line_id),
    ).not.toContain('l-99')
  })

  // unit_test_case 13 + skenario 4
  it('unit_test_case 13: penyimpanan perangkat MELEMPAR saat dibaca tidak merusak layar', async () => {
    const getItemSpy = vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new DOMException('denied')
    })

    const wrapper = await mountView()

    // SPY PROTOTYPE menerima minimal satu panggilan — DIBUKTIKAN, bukan
    // diasumsikan. Spy pada window.localStorage akan menerima NOL panggilan
    // di jsdom (instance-nya dibungkus Proxy) dan test ini akan hijau tanpa
    // menguji apa pun.
    expect(getItemSpy.mock.calls.length).toBeGreaterThanOrEqual(1)

    expect(exists(wrapper, 'laporan-solid-waste-disposal-mobile')).toBe(true)
    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)
    expect(all(wrapper, '[data-testid="error-message"]')).toHaveLength(0)
    expect(all(wrapper, '[data-testid="network-error"]')).toHaveLength(0)

    await selectPeriod(wrapper, 'p-1')
    await selectLine(wrapper, LINE_3.id)

    expect(hits(SUMMARY_PATH)).toBe(1)
    expect(kpiElements(wrapper)).toHaveLength(5)
  })

  // unit_test_case 14
  it('unit_test_case 14: mill dengan SATU production line tetap TIDAK dipilihkan', async () => {
    repoMocks.fetchLines.mockImplementation(() => {
      requests.push(LINES_PATH)

      return Promise.resolve([LINE_3])
    })

    const wrapper = await mountView()

    expect(selectedOptionText(wrapper, 'production-line-select')).toBe(NO_LINE_SELECTED)
    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)
    expect(hits(SUMMARY_PATH)).toBe(0)

    // Sesudah pengguna memilih satu-satunya line itu SECARA SADAR.
    await selectPeriod(wrapper, 'p-1')
    await selectLine(wrapper, LINE_3.id)

    expect(hits(SUMMARY_PATH)).toBe(1)
    expect(kpiElements(wrapper)).toHaveLength(5)
  })

  it('line yang berlaku DITULIS KEMBALI ke penyimpanan perangkat — satu konteks, dua layar', async () => {
    const setItemSpy = vi.spyOn(Storage.prototype, 'setItem')

    const wrapper = await mountView()

    await selectLine(wrapper, LINE_3.id)

    expect(setItemSpy.mock.calls.length).toBeGreaterThanOrEqual(1)
    expect(setItemSpy.mock.calls.flat().map(String)).toContain(REMEMBERED_LINE_KEY)
    expect(window.localStorage.getItem(REMEMBERED_LINE_KEY)).toBe(LINE_3.id)
  })

  it('line dari query rute MENANG atas ingatan perangkat — pintu masuk screen-141', async () => {
    window.localStorage.setItem(REMEMBERED_LINE_KEY, LINE_3.id)
    routeQuery.production_line_id = LINE_4.id

    const wrapper = await mountView()

    expect(selectedOptionText(wrapper, 'production-line-select')).toBe(LINE_4.name)

    await selectPeriod(wrapper, 'p-1')

    expect(wireParamsOf(summaryCalls[0]).production_line_id).toBe('l-4')
  })

  it('view TIDAK memanggil productionLineRepo langsung — ia lewat repo laporan', async () => {
    const wrapper = await mountFullReport()

    expect(kpiElements(wrapper)).toHaveLength(5)
    expect(hits(LINES_PATH)).toBe(1)

    for (const [name, spy] of Object.entries(productionLineMocks)) {
      expect(spy, `productionLineRepo.${name} dipanggil langsung oleh view`).not.toHaveBeenCalled()
    }
  })
})

/* ================================================================== */
/* unit_test_case 4 + skenario 6 — ADMIN dan PEMILIH MILL               */
/* ================================================================== */

describe('Admin dan pemilih Mill', () => {
  // unit_test_case 4
  it('unit_test_case 4: /business-units/options dipanggil NOL kali untuk ketiga peran non-admin, dan SATU kali untuk admin', async () => {
    for (const role of MILL_BOUND_ROLES) {
      asRole(role, 'bu-1')

      const wrapper = await mountView()

      expect(hits(BUSINESS_UNITS_PATH), role).toBe(0)
      expect(all(wrapper, '[data-testid="mill-select"]'), role).toHaveLength(0)

      wrapper.unmount()
      mountedWrappers.pop()
      requests.length = 0
    }

    asRole('admin', null)

    const wrapper = await mountView()

    expect(hits(BUSINESS_UNITS_PATH)).toBe(1)
    expect(exists(wrapper, 'mill-select')).toBe(true)
  })

  // skenario 6
  it('skenario 6: Admin memilih mill lebih dulu — sebelum itu nol permintaan periode/line/ringkasan', async () => {
    asRole('admin', null)

    const wrapper = await mountView()

    expect(exists(wrapper, 'mill-required-hint')).toBe(true)
    expect(hits(PERIODS_PATH)).toBe(0)
    expect(hits(LINES_PATH)).toBe(0)
    expect(hits(SUMMARY_PATH)).toBe(0)
    expect(kpiElements(wrapper)).toHaveLength(0)

    await selectMill(wrapper, 'bu-9')

    expect(hits(PERIODS_PATH)).toBe(1)
    expect(hits(LINES_PATH)).toBe(1)
    // Line belum dipilih (ingatan perangkat TIDAK dipakai sesudah pergantian
    // mill) — jadi ringkasan tetap tertahan.
    expect(hits(SUMMARY_PATH)).toBe(0)

    await selectLine(wrapper, LINE_3.id)
    await selectPeriod(wrapper, 'p-1')

    expect(hits(SUMMARY_PATH)).toBe(1)

    // business_unit_id ADA sebagai KUNCI KAWAT pada /periods dan /summary.
    expect(wireParamsOf(summaryCalls[0])).toEqual({
      period_id: 'p-1',
      production_line_id: 'l-3',
      business_unit_id: 'bu-9',
    })
    expect(periodsCalls[0].isAdmin).toBe(true)
    expect(periodsCalls[0].businessUnitId).toBe('bu-9')
    expect(linesCalls[0].businessUnitId).toBe('bu-9')
    expect(kpiElements(wrapper)).toHaveLength(5)
  })

  it('skenario 6: mengganti mill mengosongkan pemilih line DAN pemilih periode, serta membersihkan angka', async () => {
    asRole('admin', null)

    const wrapper = await mountView()

    await selectMill(wrapper, 'bu-9')
    await selectLine(wrapper, LINE_3.id)
    await selectPeriod(wrapper, 'p-1')

    expect(kpiElements(wrapper)).toHaveLength(5)

    await selectMill(wrapper, 'bu-1')

    // KEDUA pemilih kosong — ingatan perangkat SENGAJA tidak dipakai sesudah
    // pergantian mill: ia pilihan terakhir pengguna di mill yang LAIN.
    expect(selectedOptionText(wrapper, 'production-line-select')).toBe(NO_LINE_SELECTED)
    expect(selectedOptionText(wrapper, 'period-select')).toBe(NO_PERIOD_SELECTED)
    expect(kpiElements(wrapper)).toHaveLength(0)
    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)
  })

  it('skenario 6: business_unit_id tidak muncul sebagai KUNCI KAWAT pada ketiga peran terikat mill', async () => {
    for (const role of MILL_BOUND_ROLES) {
      asRole(role, 'bu-1')

      const wrapper = await mountFullReport()

      expect(kpiElements(wrapper).length, role).toBe(5)

      for (const call of summaryCalls) {
        expect(Object.keys(wireParamsOf(call)), role).not.toContain('business_unit_id')
      }

      for (const scope of [...periodsCalls, ...linesCalls]) {
        expect(scope.isAdmin, role).toBe(false)
        expect(scope.businessUnitId, role).toBeNull()
      }

      wrapper.unmount()
      mountedWrappers.pop()
      requests.length = 0
      summaryCalls.length = 0
      periodsCalls.length = 0
      linesCalls.length = 0
      window.localStorage.clear()
    }
  })

  it('403 pada pemilih mill tidak merender pemilih yang pasti gagal', async () => {
    asRole('admin', null)
    repoMocks.fetchBusinessUnits.mockImplementation(() => {
      requests.push(BUSINESS_UNITS_PATH)

      return Promise.reject(FORBIDDEN_ERROR)
    })

    const wrapper = await mountView()

    expect(all(wrapper, '[data-testid="mill-select"]')).toHaveLength(0)
    expect(exists(wrapper, 'error-message')).toBe(true)
    expect(exists(wrapper, 'network-error')).toBe(false)
  })
})

/* ================================================================== */
/* unit_test_case 43 + skenario 7/8 — AKUN TANPA MILL, TANPA PERIODE   */
/* ================================================================== */

describe('akun tanpa mill dan mill tanpa periode', () => {
  // unit_test_case 43 + skenario 7 (jalur SERVER 422)
  it('unit_test_case 43: 422 akun tanpa mill menampilkan arahan menghubungi Admin, bukan galat jaringan', async () => {
    repoMocks.fetchPeriods.mockImplementation(() => {
      requests.push(PERIODS_PATH)

      return Promise.reject(NO_MILL_ERROR)
    })

    const wrapper = await mountView()

    expect(exists(wrapper, 'no-mill-for-account')).toBe(true)
    expect(normalize(text(wrapper, 'no-mill-for-account'))).toContain('hubungi Admin')
    expect(exists(wrapper, 'network-error')).toBe(false)
    // NOL daftar seluruh mill ditawarkan sebagai jalan keluar.
    expect(wrapper.find('[data-testid="mill-select"]').exists()).toBe(false)
    expect(kpiElements(wrapper)).toHaveLength(0)
  })

  // skenario 7 (jalur PRA-PENERBANGAN) — SATU testid untuk KEDUA jalur
  it('skenario 7: akun tanpa mill berhenti SEBELUM permintaan pertama, dengan testid yang SAMA', async () => {
    asRole('operator', null)

    const wrapper = await mountView()

    expect(exists(wrapper, 'no-mill-for-account')).toBe(true)
    // NOL permintaan ke kelima jalur — bootstrap berhenti sebelum yang
    // pertama.
    expect(requests).toEqual([])
    // Dan TIDAK ADA testid kedua untuk keadaan yang sama: kedua jalur
    // menuntut tindakan yang sama, jadi keduanya memakai satu keadaan.
    expect(wrapper.find('[data-testid="mill-not-linked"]').exists()).toBe(false)
  })

  // skenario 8
  it('skenario 8: mill tanpa periode yang mencakup stasiun ini — arahan, bukan galat', async () => {
    repoMocks.fetchPeriods.mockImplementation(() => {
      requests.push(PERIODS_PATH)

      return Promise.resolve([])
    })

    window.localStorage.setItem(REMEMBERED_LINE_KEY, LINE_3.id)

    const wrapper = await mountView()

    expect(exists(wrapper, 'no-periods')).toBe(true)

    const note = normalize(text(wrapper, 'no-periods'))

    expect(note).toContain('Solid Waste Disposal')
    expect(note).toContain('hubungi Admin')
    // INI BUKAN GALAT.
    expect(exists(wrapper, 'network-error')).toBe(false)
    expect(exists(wrapper, 'error-message')).toBe(false)
    expect(kpiElements(wrapper)).toHaveLength(0)
    expect(hits(SUMMARY_PATH)).toBe(0)
  })
})

/* ================================================================== */
/* unit_test_case 15/16/39/40 + skenario 29 — LURING                    */
/* ================================================================== */

describe('luring DINYATAKAN, bukan ditutupi angka basi', () => {
  const EVERY_PATH = [BUSINESS_UNITS_PATH, PERIODS_PATH, LINES_PATH, SUMMARY_PATH, EXPORT_PATH]

  // unit_test_case 15 + skenario 29
  it('unit_test_case 15: luring saat layar dibuka — NOL permintaan ke KELIMA jalur, diperiksa satu per satu', async () => {
    fakeOnLine(false)
    window.localStorage.setItem(REMEMBERED_LINE_KEY, LINE_3.id)

    await mountView()

    for (const path of EVERY_PATH) {
      expect(hits(path), path).toBe(0)
    }

    expect(requests).toEqual([])
  })

  // unit_test_case 16
  it('unit_test_case 16: pernyataan luring berdiri bersama NOL kartu angka', async () => {
    fakeOnLine(false)

    const wrapper = await mountView()

    expect(exists(wrapper, 'offline-notice')).toBe(true)

    // KEDUA kalimat diasersikan pada ELEMEN NOTICE ITU, bukan pada seluruh
    // halaman.
    const notice = normalize(text(wrapper, 'offline-notice'))

    expect(notice).toContain('memerlukan jaringan')
    expect(notice).toContain('tidak menampilkan satu angka pun dari pemuatan sebelumnya')

    expect(all(wrapper, '[data-testid^="swd-kpi-"]')).toHaveLength(0)
    expect(wrapper.find('[data-testid="swd-daily-table"]').exists()).toBe(false)
    // Layarnya tidak diam: kerangkanya tetap terender.
    expect(exists(wrapper, 'laporan-solid-waste-disposal-mobile')).toBe(true)
  })

  // unit_test_case 39
  it('unit_test_case 39: event offline SESUDAH angka termuat MENUTUP angkanya', async () => {
    const wrapper = await mountFullReport()

    expect(kpiElements(wrapper)).toHaveLength(5)

    const before = requests.length

    fakeOnLine(false)
    window.dispatchEvent(new Event('offline'))
    await flushPromises()

    expect(kpiElements(wrapper)).toHaveLength(0)
    expect(exists(wrapper, 'offline-notice')).toBe(true)
    // NOL permintaan tambahan sesudah event itu.
    expect(requests.length).toBe(before)
  })

  it('event online memulai bootstrap yang sebelumnya tertahan luring — sekali, bukan dua kali', async () => {
    fakeOnLine(false)

    const wrapper = await mountView()

    expect(requests).toEqual([])

    cleanups.pop()?.()
    fakeOnLine(true)
    window.dispatchEvent(new Event('online'))
    await flushPromises()

    expect(hits(PERIODS_PATH)).toBe(1)
    expect(hits(LINES_PATH)).toBe(1)
    expect(exists(wrapper, 'offline-notice')).toBe(false)

    // Dan event kedua TIDAK mem-bootstrap ulang.
    window.dispatchEvent(new Event('online'))
    await flushPromises()

    expect(hits(PERIODS_PATH)).toBe(1)
  })

  // unit_test_case 40
  it('unit_test_case 40: pendengar online/offline DILEPAS saat unmount, dengan referensi handler yang SAMA', async () => {
    const addSpy = vi.spyOn(window, 'addEventListener')
    const removeSpy = vi.spyOn(window, 'removeEventListener')

    const wrapper = mount(LaporanSolidWasteDisposalView)

    await flushPromises()
    wrapper.unmount()

    for (const event of ['online', 'offline'] as const) {
      const added = addSpy.mock.calls.filter((call) => call[0] === event).map((call) => call[1])
      const removed = removeSpy.mock.calls
        .filter((call) => call[0] === event)
        .map((call) => call[1])

      expect(added, event).toHaveLength(1)
      expect(removed, event).toHaveLength(1)
      // IDENTITAS FUNGSINYA, bukan hanya jumlah pemanggilan: remove dengan
      // handler lain tidak melepas apa pun, dan jumlahnya tetap cocok.
      expect(removed[0], event).toBe(added[0])
    }
  })
})

/* ================================================================== */
/* unit_test_case 41/42/44 + skenario 30/31 — GALAT                     */
/* ================================================================== */

describe('galat dan keadaan', () => {
  // unit_test_case 41 + skenario 30 (a)
  it('unit_test_case 41: 401 pada /summary mengarahkan ke Login TANPA pesan jaringan', async () => {
    repoMocks.fetchSummary.mockImplementation((periodId: string) => {
      requests.push(SUMMARY_PATH)
      summaryCalls.push({ periodId, scope: {} })

      return Promise.reject(UNAUTHENTICATED_ERROR)
    })

    const wrapper = await mountFullReport()

    expect(pushMock).toHaveBeenCalledTimes(1)
    expect(pushMock).toHaveBeenCalledWith({ name: 'login' })
    expect(wrapper.find('[data-testid="network-error"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="error-message"]').exists()).toBe(false)
    expect(all(wrapper, '[data-testid^="swd-kpi-"]')).toHaveLength(0)
  })

  // skenario 30 (b)
  it('skenario 30: 401 pada /export mengarahkan ke Login, dan nol angka tertinggal di layar', async () => {
    const wrapper = await mountFullReport()

    expect(kpiElements(wrapper)).toHaveLength(5)

    repoMocks.exportCsv.mockImplementation((periodId: string) => {
      requests.push(EXPORT_PATH)
      exportCalls.push({ periodId, scope: {} })

      return Promise.reject(UNAUTHENTICATED_ERROR)
    })

    await wrapper.get('[data-testid="swd-export-csv"]').trigger('click')
    await flushPromises()

    expect(pushMock).toHaveBeenCalledWith({ name: 'login' })
    expect(wrapper.find('[data-testid="network-error"]').exists()).toBe(false)
    expect(all(wrapper, '[data-testid^="swd-kpi-"]')).toHaveLength(0)
    expect(repoMocks.saveCsvFile).not.toHaveBeenCalled()
  })

  // unit_test_case 42
  it('unit_test_case 42: 403 meminta memilih line lagi DAN membersihkan angka yang sudah ada', async () => {
    const wrapper = await mountFullReport()

    expect(kpiElements(wrapper)).toHaveLength(5)

    repoMocks.fetchSummary.mockImplementation(
      (periodId: string, scope?: SolidWasteDisposalReportScope) => {
        requests.push(SUMMARY_PATH)
        summaryCalls.push({ periodId, scope: scope ?? {} })

        return Promise.reject(FORBIDDEN_ERROR)
      },
    )

    await selectLine(wrapper, LINE_4.id)

    expect(exists(wrapper, 'production-line-rejected')).toBe(true)
    // ANGKA PEMUATAN SEBELUMNYA DIBERSIHKAN, bukan ditinggalkan di layar.
    expect(all(wrapper, '[data-testid^="swd-kpi-"]')).toHaveLength(0)
    expect(wrapper.find('[data-testid="network-error"]').exists()).toBe(false)
    expect(selectedOptionText(wrapper, 'production-line-select')).toBe(NO_LINE_SELECTED)
  })

  // unit_test_case 44 + skenario 31 (a)
  it('unit_test_case 44: 500 menampilkan keadaan galat + Coba Lagi, dan satu ketukan mengirim TEPAT satu permintaan', async () => {
    repoMocks.fetchSummary.mockImplementationOnce((periodId: string) => {
      requests.push(SUMMARY_PATH)
      summaryCalls.push({ periodId, scope: {} })

      return Promise.reject(SERVER_ERROR)
    })

    const wrapper = await mountFullReport()

    expect(exists(wrapper, 'network-error')).toBe(true)
    expect(exists(wrapper, 'retry-button')).toBe(true)
    // 500 memakai keadaan network-error, BUKAN error-message — keputusan
    // yang disengaja: 5xx dapat berhasil pada percobaan berikutnya.
    expect(wrapper.find('[data-testid="error-message"]').exists()).toBe(false)
    expect(all(wrapper, '[data-testid^="swd-kpi-"]')).toHaveLength(0)
    expect(hits(SUMMARY_PATH)).toBe(1)

    await wrapper.get('[data-testid="retry-button"]').trigger('click')
    await flushPromises()

    // TEPAT 2 — bukan 3: Coba Lagi tidak memanggil dua kali.
    expect(hits(SUMMARY_PATH)).toBe(2)
    expect(kpiElements(wrapper)).toHaveLength(5)
    expect(wrapper.find('[data-testid="network-error"]').exists()).toBe(false)
  })

  it('kegagalan transport TANPA status memakai keadaan yang sama dengan 500, dan keduanya dapat dicoba lagi', async () => {
    repoMocks.fetchSummary.mockImplementationOnce((periodId: string) => {
      requests.push(SUMMARY_PATH)
      summaryCalls.push({ periodId, scope: {} })

      return Promise.reject(NETWORK_ERROR)
    })

    const wrapper = await mountFullReport()

    expect(exists(wrapper, 'network-error')).toBe(true)
    expect(normalize(text(wrapper, 'network-error'))).toContain(NETWORK_ERROR.message)
    expect(exists(wrapper, 'retry-button')).toBe(true)
  })

  it('404 periode tidak ditemukan memakai error-message — satu-satunya penghuni keadaan itu bersama 403 pemilih mill', async () => {
    repoMocks.fetchSummary.mockImplementation((periodId: string) => {
      requests.push(SUMMARY_PATH)
      summaryCalls.push({ periodId, scope: {} })

      return Promise.reject(NOT_FOUND_ERROR)
    })

    const wrapper = await mountFullReport()

    expect(exists(wrapper, 'error-message')).toBe(true)
    expect(normalize(text(wrapper, 'error-message'))).toContain('tidak ditemukan')
    expect(wrapper.find('[data-testid="network-error"]').exists()).toBe(false)
    // Pemilih periode dikosongkan supaya pengguna memilih periode lain.
    expect(selectedOptionText(wrapper, 'period-select')).toBe(NO_PERIOD_SELECTED)
  })

  it('daftar line gagal dimuat punya keadaan dan tombol Coba Lagi-nya SENDIRI, bukan menyamar sebagai galat ringkasan', async () => {
    repoMocks.fetchLines.mockImplementation(() => {
      requests.push(LINES_PATH)

      return Promise.reject(NETWORK_ERROR)
    })

    const wrapper = await mountView()

    expect(exists(wrapper, 'production-line-unavailable')).toBe(true)
    expect(exists(wrapper, 'production-line-retry')).toBe(true)
    expect(normalize(text(wrapper, 'production-line-unavailable'))).toContain(
      'tidak dapat dimuat',
    )

    await wrapper.get('[data-testid="production-line-retry"]').trigger('click')
    await flushPromises()

    expect(hits(LINES_PATH)).toBe(2)
    expect(hits(SUMMARY_PATH)).toBe(0)
  })

  it('mill tanpa satu pun line dibedakan dari daftar yang gagal dimuat', async () => {
    repoMocks.fetchLines.mockImplementation(() => {
      requests.push(LINES_PATH)

      return Promise.resolve([])
    })

    const wrapper = await mountView()

    expect(exists(wrapper, 'production-line-unavailable')).toBe(true)
    expect(normalize(text(wrapper, 'production-line-unavailable'))).toContain(
      'belum memiliki Production Line',
    )
    // Keadaan ini TIDAK punya tombol Coba Lagi: mencoba lagi tidak akan
    // mengubah apa pun.
    expect(wrapper.find('[data-testid="production-line-retry"]').exists()).toBe(false)
  })
})

/* ================================================================== */
/* unit_test_case 46 + skenario 26/28 — EKSPOR CSV SAJA                 */
/* ================================================================== */

describe('ekspor CSV saja', () => {
  it('tombol ekspor TIDAK dirender sebelum ada periode DAN line yang berlaku', async () => {
    const wrapper = await mountView()

    expect(wrapper.find('[data-testid="swd-export-csv"]').exists()).toBe(false)

    await selectPeriod(wrapper, 'p-1')

    // Periode saja belum cukup — berkas yang mencampur line adalah bentuk
    // kesalahan yang paling sulit dibantah setelah terkirim.
    expect(wrapper.find('[data-testid="swd-export-csv"]').exists()).toBe(false)

    await selectLine(wrapper, LINE_3.id)

    expect(exists(wrapper, 'swd-export-csv')).toBe(true)
  })

  // unit_test_case 45 (bagian view) + skenario 26
  it('skenario 26: URL ekspor yang dibangun view memuat period_id, production_line_id dan format=csv', async () => {
    const wrapper = await mountFullReport()

    await wrapper.get('[data-testid="swd-export-csv"]').trigger('click')
    await flushPromises()

    expect(exportCalls).toHaveLength(1)

    // buildExportUrl YANG SUNGGUHAN (repo diimpor lewat importActual) dipakai
    // atas argumen yang BENAR-BENAR diserahkan view.
    const url = buildExportUrl(exportCalls[0].periodId, exportCalls[0].scope)
    const [pathname, query] = url.split('?')
    const search = new URLSearchParams(query)

    expect(pathname).toBe('/api/solid-waste-disposal-reports/export')
    expect(search.get('period_id')).toBe('p-1')
    expect(search.get('production_line_id')).toBe('l-3')
    expect(search.get('format')).toBe('csv')
    expect(search.get('format')).not.toBe('xlsx')

    // Berkasnya disimpan dengan nama yang menyebut periode dan line.
    expect(repoMocks.saveCsvFile).toHaveBeenCalledTimes(1)

    const filename = String(repoMocks.saveCsvFile.mock.calls[0][1])

    expect(filename.endsWith('.csv')).toBe(true)
    expect(filename).toContain('laporan-solid-waste-disposal')
  })

  // unit_test_case 46 + skenario 26
  it('unit_test_case 46: ketukan ganda cepat pada ekspor mengirim SATU permintaan', async () => {
    let release!: (value: Blob) => void

    repoMocks.exportCsv.mockImplementation(
      (periodId: string, scope?: SolidWasteDisposalReportScope) => {
        requests.push(EXPORT_PATH)
        exportCalls.push({ periodId, scope: scope ?? {} })

        return new Promise<Blob>((resolve) => {
          release = resolve
        })
      },
    )

    const wrapper = await mountFullReport()
    const button = wrapper.get('[data-testid="swd-export-csv"]')

    await button.trigger('click')

    expect(button.attributes('disabled')).toBeDefined()
    expect(button.attributes('aria-busy')).toBe('true')
    expect(normalize(button.text())).toContain('Mengekspor')

    /**
     * KETUKAN KEDUA DIKIRIM LANGSUNG KE DOM, bukan lewat wrapper.trigger().
     *
     * `trigger()` VTU MELEWATI dispatch pada elemen ber-atribut disabled
     * (`if (this.element && !this.isDisabled())`), jadi yang teruji dengan
     * cara itu adalah penjagaan ALAT UJI — bukan penjagaan APLIKASI. Dengan
     * dispatch langsung, handler Vue benar-benar dipanggil dan yang menahan
     * permintaan kedua adalah `exporting` di dalam onExport().
     *
     * `_vts` disetel Date.now() + 1 dengan alasan yang sama seperti VTU
     * sendiri: invoker Vue membuang event yang timestamp-nya tidak lebih baru
     * daripada saat handler dipasang.
     */
    const forcedClick = new Event('click') as Event & { _vts?: number }

    forcedClick._vts = Date.now() + 1
    button.element.dispatchEvent(forcedClick)
    await flushPromises()

    expect(hits(EXPORT_PATH)).toBe(1)
    expect(exportCalls).toHaveLength(1)

    release(new Blob(['a,b']))
    await flushPromises()

    expect(button.attributes('disabled')).toBeUndefined()
    expect(button.attributes('aria-busy')).toBe('false')
    expect(repoMocks.saveCsvFile).toHaveBeenCalledTimes(1)
  })

  // skenario 28
  it('skenario 28: periode TERTUTUP tetap dapat dibaca dan diekspor sepenuhnya', async () => {
    repoMocks.fetchSummary.mockImplementation(
      (periodId: string, scope?: SolidWasteDisposalReportScope) => {
        requests.push(SUMMARY_PATH)
        summaryCalls.push({ periodId, scope: scope ?? {} })

        return Promise.resolve(
          makeSummary({
            period: {
              id: PERIOD_CLOSED.id,
              name: PERIOD_CLOSED.name,
              start_date: PERIOD_CLOSED.start_date,
              end_date: PERIOD_CLOSED.end_date,
              status: 'closed',
            },
          }) as SolidWasteDisposalReportSummary,
        )
      },
    )

    const wrapper = await mountFullReport('p-2')

    // Seluruh kerangka dirender SAMA seperti periode terbuka.
    expect(kpiElements(wrapper)).toHaveLength(5)
    expect(rawText(wrapper, 'swd-kpi-load-count')).toBe('12')
    expect(rawText(wrapper, 'swd-daily-total-load-count')).toBe('12')
    expect(rawText(wrapper, 'swd-coverage-percent')).toBe('41,3')
    expect(rawText(wrapper, 'swd-compliance-missing-seal')).toBe('3')

    for (const testid of RECAP_TESTIDS) {
      expect(exists(wrapper, `${testid}-table`), testid).toBe(true)
    }

    expect(text(wrapper, 'period-status-badge')).toBe('Tertutup')
    // EKSPOR TIDAK PERNAH dinonaktifkan oleh status periode.
    expect(exists(wrapper, 'swd-export-csv')).toBe(true)
    expect(wrapper.get('[data-testid="swd-export-csv"]').attributes('disabled')).toBeUndefined()

    // Dan periode tertutup HADIR di opsi pemilihnya — nol penyaring status.
    const options = wrapper
      .get('[data-testid="period-select"]')
      .findAll('option')
      .map((option) => normalize(option.text()))

    expect(options).toContain(`${PERIOD_CLOSED.station_type_label} — ${PERIOD_CLOSED.name}`)
  })
})

/* ================================================================== */
/* skenario 27 — MELIPAT itu MURNI PENYINGKAPAN                        */
/* ================================================================== */

describe('rekap dapat dilipat tanpa memuat ulang apa pun', () => {
  // skenario 27
  it('skenario 27: melipat dan membuka TIDAK memicu permintaan dan TIDAK mengubah satu angka pun', async () => {
    const wrapper = await mountFullReport()

    const toggles = collapsibleToggles(wrapper)

    // LIMA kontrol lipat: keempat rekap kategoris + rekap harian.
    expect(toggles).toHaveLength(5)

    for (const toggle of toggles) {
      expect(toggle.element.tagName).toBe('BUTTON')
      expect(toggle.attributes('aria-expanded')).toBe('false')
    }

    const numbersBefore = Object.fromEntries(
      wrapper
        .findAll('[data-testid^="swd-kpi-"], [data-testid="swd-daily-total-load-count"]')
        .map((el) => [el.attributes('data-testid') ?? '', normalize(el.text())]),
    )
    const requestsBefore = requests.length

    for (const toggle of collapsibleToggles(wrapper)) {
      await toggle.trigger('click')
    }

    for (const toggle of collapsibleToggles(wrapper)) {
      expect(toggle.attributes('aria-expanded')).toBe('true')
    }

    for (const toggle of collapsibleToggles(wrapper)) {
      await toggle.trigger('click')
      await toggle.trigger('click')
    }

    const numbersAfter = Object.fromEntries(
      wrapper
        .findAll('[data-testid^="swd-kpi-"], [data-testid="swd-daily-total-load-count"]')
        .map((el) => [el.attributes('data-testid') ?? '', normalize(el.text())]),
    )

    // PETA testid→teks, dibandingkan sebagai objek.
    expect(numbersAfter).toEqual(numbersBefore)
    expect(requests.length).toBe(requestsBefore)
  })

  it('LIMA tabel dirender dari DUA kemunculan literal v-stacked-table — rekapnya ditulis sekali, di-loop', async () => {
    const wrapper = await mountFullReport()

    // YANG DIHITUNG adalah TABEL YANG TERENDER, bukan kemunculan literalnya
    // di sumber (jebakan #9): markup rekap ditulis SEKALI dan di-loop atas
    // RECAPS, jadi asersi statis ala screen-161 (`toHaveLength(3)`) akan
    // memerah pada implementasi yang justru lebih baik.
    expect(wrapper.findAll('table')).toHaveLength(5)

    for (const testid of RECAP_TESTIDS) {
      expect(exists(wrapper, `${testid}-table`), testid).toBe(true)
    }

    expect(exists(wrapper, 'swd-daily-table')).toBe(true)

    const template = readSource(VIEW_PATH)

    expect(stripComments(template).match(/v-stacked-table/g)).toHaveLength(2)
    // Dan setiap tabel berada di dalam pembungkusnya sendiri.
    expect(wrapper.findAll('.detail-table-wrap')).toHaveLength(5)
  })
})

/* ================================================================== */
/* unit_test_case 5 — LAYAR BACA SAJA                                   */
/* ================================================================== */

describe('layar baca saja', () => {
  // unit_test_case 5
  it('unit_test_case 5: seluruh alur layar tidak menyentuh apiClient langsung, localDb, maupun kontrol tulis', async () => {
    const wrapper = await mountFullReport()

    await selectLine(wrapper, LINE_4.id)
    await selectPeriod(wrapper, 'p-2')

    for (const toggle of collapsibleToggles(wrapper)) {
      await toggle.trigger('click')
    }

    await wrapper.get('[data-testid="swd-export-csv"]').trigger('click')
    await flushPromises()

    // Layar berbicara HANYA lewat repo: pemanggilan langsung ke apiClient
    // akan terhitung di sini.
    expect(apiClientGetMock).not.toHaveBeenCalled()

    for (const [name, spy] of Object.entries(localDbMocks)) {
      expect(spy, `localDb.${name} dipanggil`).not.toHaveBeenCalled()
    }

    // NOL kontrol tulis: hanya tiga pemilih dan tombol-tombol baca/ekspor.
    expect(wrapper.findAll('input')).toHaveLength(0)
    expect(wrapper.findAll('textarea')).toHaveLength(0)
    expect(wrapper.findAll('form')).toHaveLength(0)
    expect(wrapper.findAll('select')).toHaveLength(2)
  })

  it('AUDIT STATIS: view tidak mengimpor apiClient, localDb, maupun localSchema', () => {
    const code = stripComments(scriptBlockOf(readSource(VIEW_PATH)))

    for (const forbidden of ['apiClient', 'localDb', 'localSchema', 'sqlite']) {
      expect(code, `view mengimpor ${forbidden}`).not.toContain(forbidden)
    }
  })
})

/* ================================================================== */
/* unit_test_case 47 + skenario 32 — PINTU MASUK DARI REPORTING         */
/* ================================================================== */

describe('pintu masuk tile Reporting', () => {
  function makeStation(overrides: Partial<StationSlot> & { id: string }): StationSlot {
    return {
      businessUnitId: 'bu-1',
      name: `Stasiun ${overrides.id}`,
      type: 'other',
      isActive: false,
      icon: null,
      ...overrides,
    }
  }

  async function mountPicker(stations: StationSlot[]) {
    productionLineMocks.fetchCurrentProductionLines.mockResolvedValue([])
    stationRepoMocks.getActiveAndPlaceholderStations.mockResolvedValue(stations)
    stationRepoMocks.getActiveAndPlaceholderStationsForProductionLine.mockResolvedValue(stations)

    const wrapper = mount(ReportingPilihStasiunView)

    mountedWrappers.push(wrapper)
    await flushPromises()

    return wrapper
  }

  /**
   * unit_test_case 47 / skenario 32 — DIVERGENSI 1 pada docblock berkas.
   *
   * Invarian yang diuji: tile Solid Waste Disposal MENYALA, dan satu-satunya
   * yang menentukannya adalah keanggotaan di REPORT_ROUTES — bukan
   * StationSlot.isActive. `isActive: false` di fixture ini SENGAJA tidak
   * masuk akal: justru ketidakwajarannya yang menjadi alat ujinya.
   */
  it('unit_test_case 47: tile solid-waste-disposal AKTIF dan bernavigasi ke rute laporan ini', async () => {
    const wrapper = await mountPicker([
      makeStation({
        id: 'st-swd',
        name: 'Solid Waste Disposal Lini A',
        type: 'solid-waste-disposal',
        isActive: false,
      }),
    ])

    const tile = wrapper.get('[data-testid="station-tile-solid-waste-disposal"]')

    expect(tile.attributes('aria-disabled')).toBe('false')
    expect(tile.text()).not.toContain('Belum tersedia')
    expect(tile.classes()).toContain('station-tile--available')

    await tile.trigger('click')

    expect(pushMock).toHaveBeenCalledTimes(1)
    expect(pushMock).toHaveBeenCalledWith({ name: 'report-solid-waste-disposal', query: {} })
    expect(wrapper.find('[data-testid="info-message"]').exists()).toBe(false)
  })

  it('unit_test_case 47: entri REPORT_ROUTES ADA, berkutip, dan jumlah kuncinya BERTAMBAH TEPAT SATU', () => {
    const map = reportRoutesSource()

    // KUNCINYA WAJIB BERKUTIP karena bertanda hubung — kunci bergaris-bawah
    // ('solid_waste_disposal') akan terkompilasi, terlihat benar, dan tidak
    // pernah cocok dengan satu station.type pun.
    expect(map).toContain("'solid-waste-disposal': 'report-solid-waste-disposal'")
    expect(map).not.toContain('solid_waste_disposal')

    // "Bertambah tepat satu" diasersi atas HIMPUNAN KUNCI LENGKAPNYA: keempat
    // belas entri sebelum pekerjaan ini, ditambah satu. Entri yang
    // tertambah/terhapus diam-diam akan memerah di sini.
    //
    // RIAK screen-165 (2026-10-19): laporan Kernel Dispatch mobile menambahkan
    // entri 'kernel-dispatch' pada peta yang SAMA, jadi himpunannya kini
    // berisi ENAM BELAS kunci. Yang diperbarui hanya DAFTAR PEMBANDINGNYA —
    // sifat "bertambah tepat satu per layar" sengaja DIPERTAHANKAN sebagai
    // kesetaraan penuh atas himpunan lengkap; melonggarkannya menjadi
    // toContain atau toBeGreaterThan akan menghapus justru yang diuji.
    const keys = [...map.matchAll(/^\s*'?([a-z-]+)'?\s*:\s*'report-[a-z-]+'/gm)].map(
      (match) => match[1],
    )

    expect([...keys].sort()).toEqual(
      [
        'boiler-room',
        'cages-track',
        'clarification',
        'depricarping',
        'effluent-plant',
        'engine-room',
        'grading',
        'kernel-dispatch',
        'kernel-plant',
        'pressing',
        'process-water',
        'solid-waste-disposal',
        'sterilizer',
        'storage-tank',
        'threshing',
        'weighbridge',
      ].sort(),
    )
    expect(keys).toHaveLength(16)

    // Dan nama rutenya SAMA PERSIS dengan yang terdaftar di router.
    const routerSource = readSource('src/router/index.ts')

    expect(routerSource).toContain("name: 'report-solid-waste-disposal'")
    expect(routerSource).toContain("path: '/reports/solid-waste-disposal'")
  })

  it('unit_test_case 47: ikon solid-waste-disposal SUDAH ADA dan TIDAK berubah oleh pekerjaan ini', () => {
    const code = stripComments(readSource(PICKER_PATH))

    // Peta ikonnya bernama TYPE_ICONS — bukan STATION_ICONS seperti yang
    // ditulis unit_test_case 47 (DIVERGENSI 1).
    expect(code).toContain('const TYPE_ICONS')
    expect(code).not.toContain('const STATION_ICONS')

    // NILAINYA DICATAT DI SINI supaya perubahan apa pun atasnya terlihat
    // sebagai perubahan, bukan sebagai kebetulan.
    expect(code).toContain(
      "'solid-waste-disposal':\n    '<path d=\"M4 7h16\"/><path d=\"M6 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2\"/><path d=\"M6 7l1 13a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-13\"/><line x1=\"10\" y1=\"11\" x2=\"10\" y2=\"17\"/><line x1=\"14\" y1=\"11\" x2=\"14\" y2=\"17\"/>',",
    )
  })

  /**
   * RIAK TEST YANG MEMANG DIRANCANG, DAN INI YANG KESEMBILAN.
   *
   * mobile/tests/ReportingPilihStasiunView.spec.ts memakai satu kode stasiun
   * sebagai contoh 'belum berlaporan'. Begitu 'solid-waste-disposal' masuk ke
   * REPORT_ROUTES, contoh itu berhenti menguji apa pun — dan kali ini
   * contohnya BUKAN dipindahkan ke stasiun nyata lain melainkan diganti KODE
   * FIKTIF, karena spec itu MENYUNTIKKAN daftar stasiunnya sendiri.
   */
  it('contoh "belum berlaporan" pada spec tetangga adalah kode FIKTIF, dan tetap benar-benar belum berlaporan', () => {
    const neighbourSpec = readSource('tests/ReportingPilihStasiunView.spec.ts')
    const map = reportRoutesSource()

    expect(neighbourSpec).toContain("type: 'zz-stasiun-fiktif'")
    expect(map).not.toContain("'zz-stasiun-fiktif'")

    // Dan contoh yang PENSIUN tidak lagi dipakai di sana sama sekali.
    expect(neighbourSpec).not.toContain("type: 'solid-waste-disposal'")
    expect(neighbourSpec).not.toContain('station-tile-solid-waste-disposal')
    expect(neighbourSpec).not.toContain('Laporan Solid Waste Disposal belum tersedia')
  })

  it('tile berkode fiktif tetap nonaktif MESKIPUN isActive true — aturannya REPORT_ROUTES, bukan isActive', async () => {
    const wrapper = await mountPicker([
      makeStation({
        id: 'st-zz',
        name: 'Stasiun Fiktif',
        type: 'zz-stasiun-fiktif' as StationType,
        isActive: true,
      }),
    ])

    const tile = wrapper.get('[data-testid="station-tile-zz-stasiun-fiktif"]')

    expect(tile.attributes('aria-disabled')).toBe('true')
    expect(tile.text()).toContain('Belum tersedia')

    await tile.trigger('click')

    expect(pushMock).not.toHaveBeenCalled()
    expect(wrapper.get('[data-testid="info-message"]').text()).toBe(
      'Laporan Stasiun Fiktif belum tersedia di aplikasi mobile.',
    )
  })
})

/* ================================================================== */
/* unit_test_case 48 — DISIPLIN KELAS CSS                               */
/* ================================================================== */

describe('disiplin kelas CSS', () => {
  /**
   * Tiap kelas yang dipakai markup WAJIB punya definisi. Kelas tanpa definisi
   * tidak menggagalkan satu asersi pun — ia hanya membuat bagian layar
   * kehilangan gayanya tanpa satu tanda.
   *
   * DIVERGENSI 3 pada docblock berkas: yang diperiksa DI SINI adalah
   * SUMBERNYA, karena jsdom tidak punya hasil compile sama sekali
   * (document.styleSheets kosong). Pemeriksaan atas HASIL COMPILE ada di spec
   * browser, tempat ia dibaca dari document.styleSheets.
   */
  const STYLE_SOURCES = [
    VIEW_PATH,
    'src/assets/stacked-table.css',
    'src/components/CollapsibleSection.vue',
    'src/components/StatusBadge.vue',
    'src/components/filters/FilterPanel.vue',
    'src/components/filters/FilterSelectField.vue',
    'src/components/filters/FilterChip.vue',
    'src/components/loading/LoadingState.vue',
    'src/components/loading/BusyLabel.vue',
  ]

  function definitions(): string {
    const sources = STYLE_SOURCES.map((relative) => readSource(relative)).join('\n')

    expect(sources.length).toBeGreaterThan(10_000)

    return sources
  }

  function classesUsedIn(wrapper: VueWrapper): string[] {
    const used = new Set<string>()

    for (const el of wrapper.findAll('[class]')) {
      for (const className of (el.attributes('class') ?? '').split(/\s+/)) {
        if (className !== '') {
          used.add(className)
        }
      }
    }

    return [...used]
  }

  it('unit_test_case 48: setiap kelas yang dipakai pada JALUR SUKSES punya definisi', async () => {
    const wrapper = await mountFullReport()

    for (const toggle of collapsibleToggles(wrapper)) {
      await toggle.trigger('click')
    }

    const sources = definitions()
    const used = classesUsedIn(wrapper)

    expect(used.length).toBeGreaterThan(20)

    const undefinedClasses = used.filter((className) => !sources.includes(`.${className}`))

    expect(undefinedClasses, `kelas tanpa definisi: ${undefinedClasses.join(', ')}`).toEqual([])
  })

  it('unit_test_case 48: kelas pada KEADAAN LAIN pun punya definisi (luring, galat, kosong, tanpa line, tanpa mill)', async () => {
    const sources = definitions()
    const used = new Set<string>()

    // (a) tanpa line
    const plain = await mountView()

    classesUsedIn(plain).forEach((className) => used.add(className))

    // (b) galat jaringan + Coba Lagi
    repoMocks.fetchSummary.mockImplementation((periodId: string) => {
      requests.push(SUMMARY_PATH)
      summaryCalls.push({ periodId, scope: {} })

      return Promise.reject(NETWORK_ERROR)
    })

    const failing = await mountFullReport()

    classesUsedIn(failing).forEach((className) => used.add(className))

    // (c) periode kosong
    repoMocks.fetchSummary.mockImplementation((periodId: string) => {
      requests.push(SUMMARY_PATH)
      summaryCalls.push({ periodId, scope: {} })

      return Promise.resolve(emptyPeriodSummary() as SolidWasteDisposalReportSummary)
    })

    const empty = await mountFullReport()

    classesUsedIn(empty).forEach((className) => used.add(className))

    // (d) luring
    fakeOnLine(false)

    const offline = await mountView()

    classesUsedIn(offline).forEach((className) => used.add(className))
    cleanups.pop()?.()

    // (e) akun tanpa mill
    asRole('operator', null)

    const noMill = await mountView()

    classesUsedIn(noMill).forEach((className) => used.add(className))

    const undefinedClasses = [...used].filter((className) => !sources.includes(`.${className}`))

    expect(undefinedClasses, `kelas tanpa definisi: ${undefinedClasses.join(', ')}`).toEqual([])
  })
})

/* ================================================================== */
/* Menu dan navigasi (konvensi bersama layar mobile lain)              */
/* ================================================================== */

describe('menu dan navigasi', () => {
  it('menu hamburger terbuka dan Logout mengarahkan ke Login', async () => {
    const wrapper = await mountView()

    expect(exists(wrapper, 'nav-menu')).toBe(false)

    await wrapper.get('[data-testid="hamburger-button"]').trigger('click')

    expect(exists(wrapper, 'nav-menu')).toBe(true)

    await wrapper.get('[data-testid="nav-menu-logout"]').trigger('click')
    await flushPromises()

    expect(logoutMock).toHaveBeenCalledTimes(1)
    expect(pushMock).toHaveBeenCalledWith({ name: 'login' })
  })

  it('breadcrumb Home dan tombol Back bernavigasi ke tujuan masing-masing', async () => {
    const wrapper = await mountView()

    await wrapper.get('[data-testid="breadcrumb-home"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'home' })

    await wrapper.get('[data-testid="back-button"]').trigger('click')

    expect(pushMock).toHaveBeenCalledWith({ name: 'dashboard-reporting' })
  })
})
