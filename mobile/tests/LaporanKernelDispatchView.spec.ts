/**
 * LaporanKernelDispatchView.spec.ts — screen-165--laporan-kernel-dispatch-mobile /
 * usecase-171--laporan-kernel-dispatch-mobile "Lihat Laporan Periode Kernel
 * Dispatch (Mobile)".
 *
 * Meliput ke-59 `unit_test_cases` tech spec screen-165, SATU `it(...)` per
 * kasus dan dinomori `unit_test_case N:` sesuai urutan berkas case-nya.
 * Berbeda dari screen-163 — yang memecah kasus repo ke berkas repo-nya
 * sendiri — kasus 1, 2 dan 54 ikut DI SINI, dan untuk itu repo YANG
 * SUNGGUHAN diambil lewat `vi.importActual()` sementara yang di-mock hanya
 * apiClient-nya (lihat DIVERGENSI 1).
 *
 * Layar ini tidak menyentuh satu berkas backend pun: keempat endpoint
 * /api/kernel-dispatch-reports/* sudah ada sejak screen-164, dan kebenaran
 * ANGKA-nya sudah dibuktikan terhadap KernelDispatchReportService di sana.
 *
 * ────────────────────────────────────────────────────────────────────────
 * YANG MEMBUAT ANGKA PONSEL SAMA DENGAN ANGKA WEB
 * ────────────────────────────────────────────────────────────────────────
 * Layar ini TIDAK MENGHITUNG APA PUN. Ia membaca /summary dan MEMILIH medan.
 * Karena itu fixture bawaan di bawah SENGAJA BERTENTANGAN DENGAN DIRINYA
 * SENDIRI pada EMPAT tempat sekaligus (unit_test_case 14), dan tidak satu
 * angka pun di dalamnya dapat diturunkan dari angka lain:
 *
 *   - dispatches.net_weight_avg 777 padahal 1000 / 4 = 250
 *   - daily_total.net_weight_total 999 padahal kolom hariannya 10+20+30 = 60,
 *     dan daily_total.dispatch_count 88 padahal kolomnya berjumlah 6
 *   - completeness.percent 88.8 padahal 3 / 10 = 30
 *   - quality.kernel_moisture_percent { min 9, avg 3, max 4 } — avg DI LUAR
 *     rentang min..max, sehingga mustahil berasal dari penghitungan apa pun
 *
 * Klien yang menghitung PASTI gagal di sini. JANGAN "merapikan" angka-angka
 * ini. Di layar inilah bahayanya paling besar: min/avg/max terasa sudah ada
 * di tangan dan tampak mudah dihitung ulang.
 *
 * URUTAN BARIS MILIK SERVER. Urutan keempat rekap dan rekap harian di fixture
 * ini SENGAJA bukan alfabetis dan bukan urut jumlah, dan baris 'tidak
 * tercatat' SENGAJA tidak di posisi terakhir — satu-satunya cara membuktikan
 * klien tidak menyusun ulang.
 *
 * ────────────────────────────────────────────────────────────────────────
 * TUJUH JEBAKAN YANG SUDAH BERHARGA MAHAL DI PROYEK INI
 * ────────────────────────────────────────────────────────────────────────
 * 1. KETIADAAN DIASERSI ATAS TESTID, NAMA KUNCI PAYLOAD, DAN ROLE — TIDAK
 *    PERNAH ATAS SAPUAN TEKS. `_note_no_hourly` MEMUAT kata 'jam' dan
 *    menyebut CPO Dispatch; `_note_no_quality_thresholds` memuat kata
 *    'batas'. Layar merender keduanya UTUH, jadi
 *    `expect(text).not.toContain('jam')` dijamin SALAH pada layar yang BENAR.
 *    Terbukti pada screen-161.
 *
 * 2. SPY PENYIMPANAN PADA `Storage.prototype`, BUKAN pada `window.localStorage`.
 *    Di jsdom instance-nya dibungkus Proxy, sehingga spy instance menerima
 *    NOL panggilan dan test-nya hijau tanpa menguji apa pun. Setiap test yang
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
 *    'MT' / 'pengiriman' / '%' hidup di `.metric-unit-pill` SEBELAHNYA, yang
 *    tidak dirender ketika nilainya null — sehingga '—' tidak pernah
 *    berdampingan dengan satuan yatim.
 *
 * 6. `toContain(a, b)` MEMPERLAKUKAN ARGUMEN KEDUA SEBAGAI NEEDLE KEDUA, bukan
 *    sebagai pesan. Satu needle per pemanggilan; pesannya lewat argumen kedua
 *    `expect()`.
 *
 * 7. PROSA SERVER DIASERSI TERHADAP KONSTANTANYA, bukan terhadap potongan
 *    kata: kelima konstanta prosa DIBACA DARI
 *    backend/app/Services/KernelDispatchReportService.php pada saat test
 *    berjalan — pola yang sama dengan tests/optionLabel.spec.ts, yang membaca
 *    Display.php dari disk. Prosa karangan akan membuat asersi verbatim hijau
 *    sambil membuktikan nol hal tentang kalimat yang benar-benar dibaca
 *    pengguna, dan EXPORT_HEADER yang dikarang menghasilkan jumlah kolom yang
 *    salah (terjadi di screen-161).
 *
 * ────────────────────────────────────────────────────────────────────────
 * KOREKSI ATAS HURUF CASE-NYA, DICATAT DI TEMPATNYA MASING-MASING
 * ────────────────────────────────────────────────────────────────────────
 * P1 — unit_test_case 14 menuntut angka mutu '9'/'3'/'4' (tanpa desimal)
 * sementara unit_test_case 18 menuntut '0,00' untuk payload 0 (dua desimal).
 * Tidak ada satu Intl.NumberFormat pun yang memenuhi keduanya. Yang dimenangkan
 * adalah case 18, karena ia menguji perbedaan yang lebih mahal: nol TERUKUR
 * lawan nilai yang TIDAK ADA — penjaga '—' yang memakai falsiness JavaScript
 * menukar keduanya, dan itu cacat yang MENGUBAH ARTI, sementara jumlah desimal
 * tidak mengubah arti apa pun. formatQuality = min 2 / max 3 (QUALITY_DECIMALS
 * server), formatWeight = min 1 / max 2 (identik preseden screen-163). Case 14
 * karena itu mengasersi '9,00'/'3,00'/'4,00' dan '999,0'; MAKSUDNYA — payload
 * DIGEMAKAN, tidak pernah dihitung ulang — tetap teruji PENUH, karena `avg` di
 * LUAR rentang min..max mustahil berasal dari penghitungan apa pun berapa pun
 * desimalnya.
 *
 * P2 — unit_test_case 32 menyebut sel `kd-recap-gate-status-row-3-count`.
 * Nama itu akan membuat pencarian berprefiks `...-row-` pada unit_test_case 30
 * menemukan delapan elemen, bukan empat — memerah karena PENAMAAN, bukan
 * karena cacat. Konvensi repo (preseden screen-163) memberi baris dan sel
 * prefiks BERBEDA: baris `{testid}-row-{i}`, sel `{testid}-value-{i}` /
 * `{testid}-dispatch-count-{i}` / dst. Case 32 karena itu menyasar
 * `kd-recap-gate-status-dispatch-count-3`.
 *
 * P3 — unit_test_case 27 menuntut kontrol lipat ber-testid SENDIRI per rekap
 * DAN tabel yang benar-benar keluar dari DOM saat dilipat. `CollapsibleSection`
 * bersama tidak dapat memenuhi keduanya (satu testid generik, `v-show`, dan
 * bawaannya tertutup), jadi layar ini menulis kontrol lipatnya sendiri dengan
 * `v-if` dan bawaan TERBUKA — sama dengan `<details open>` di web, dan memang
 * diperlukan case 30/34/35/45 yang membaca baris langsung sesudah mount.
 *
 * ────────────────────────────────────────────────────────────────────────
 * DIVERGENSI SPEC-LAWAN-IMPLEMENTASI YANG DICATAT DI SINI
 * ────────────────────────────────────────────────────────────────────────
 * DIVERGENSI 1 — unit_test_case 1 menuntut DAFTAR URL yang terkumpul memuat
 * kelima jalur. Jalur kelima (/api/production-lines/options-for-report) tidak
 * dapat muncul di daftar itu dari berkas ini: `fetchLines()`
 * MENDELEGASIKAN ke productionLineRepo, dan repo itu WAJIB di-mock di sini
 * karena belasan test view mengasersi NOL pemanggilan langsung ke sana. Yang
 * dibuktikan sebagai gantinya ADA TIGA, dan ketiganya bersama lebih kuat
 * daripada satu baris URL: (a) delegasinya benar-benar terjadi, tepat sekali,
 * dengan business_unit_id yang benar; (b) konstanta LINES_PATH sama PENUH
 * dengan jalur screen-135 dan tidak diduplikasi di bawah prefix
 * kernel-dispatch-reports; (c) audit statis atas productionLineRepo.ts, tempat
 * literal jalur itu hidup SATU kali.
 *
 * DIVERGENSI 2 — unit_test_case 29 menyebut "peta ikon STATION_ICONS" pada
 * ReportingPilihStasiunView.vue (begitu pula unit_test_case 57). Peta itu
 * bernama `TYPE_ICONS` di berkasnya; nama yang disebut case tidak ada sama
 * sekali, dan asersi atasnya akan memerah pada kode yang justru benar.
 * Keduanya karena itu mengaudit `TYPE_ICONS` dan SEKALIGUS mengasersi bahwa
 * `STATION_ICONS` tidak ada.
 *
 * DIVERGENSI 3 — unit_test_case 59 menuntut pemeriksaan kelas CSS pada HASIL
 * COMPILE. Di jsdom tidak ada hasil compile: `document.styleSheets` kosong dan
 * `getComputedStyle` tidak memuat satu pun blok <style scoped>. Pemeriksaan
 * atas SUMBER ada di berkas ini; pemeriksaan atas HASIL COMPILE adalah milik
 * spec browser, tempat ia dibaca dari `document.styleSheets`. Hal yang sama
 * berlaku untuk setiap klaim geometri (scrollWidth lawan clientWidth,
 * penumpukan tabel menjadi kartu lewat direktif `v-stacked-table` yang di
 * jsdom tidak terdaftar sama sekali): klaim-klaim itu TIDAK DAPAT diuji di
 * sini dan tidak dipalsukan di sini.
 *
 * TANGGALNYA RELATIF, bukan kalender tetap — fixture bertanggal mati menjadi
 * basi tanpa satu pun test berubah warna.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import type { DOMWrapper, VueWrapper } from '@vue/test-utils'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import LaporanKernelDispatchView from '@/views/LaporanKernelDispatchView.vue'
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
 * apiClient di-mock dengan KELIMA metode, bukan `get` saja.
 *
 * `get` dipakai DUA ARAH: ia spy asersi ("layar hanya berbicara lewat repo",
 * jadi setiap pemanggilan langsung terhitung di sini) DAN sumber jawaban bagi
 * repo SUNGGUHAN pada unit_test_case 1, 2 dan 54. Keempat metode tulis ada di
 * sini justru supaya "nol permintaan bermetode tulis" (unit_test_case 4) dapat
 * DIHITUNG alih-alih disimpulkan dari ketiadaan tombol.
 */
const { apiClientMocks } = vi.hoisted(() => ({
  apiClientMocks: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}))

vi.mock('@/services/apiClient', () => ({ default: apiClientMocks }))

/** Anggota repo yang menyentuh jaringan + penyimpanan berkas. */
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

vi.mock('@/services/kernelDispatchReportRepo', async () => {
  const actual = await vi.importActual<typeof import('@/services/kernelDispatchReportRepo')>(
    '@/services/kernelDispatchReportRepo',
  )

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
 * stationRepo + localSchema di-mock HANYA demi unit_test_case 57, yang
 * memasang ReportingPilihStasiunView untuk mengasersi tile-nya lewat DOM.
 * LaporanKernelDispatchView tidak mengimpor keduanya.
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
 * (unit_test_case 4). Modul aslinya hanya punya open/query/run;
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
  type KernelDispatchReportScope,
  type KernelDispatchReportSummary,
} from '@/services/kernelDispatchReportRepo'

/* ------------------------------------------------------------------ */
/* Bentuk galat NYATA                                                  */
/* ------------------------------------------------------------------ */

const NETWORK_ERROR = { message: 'Tidak dapat terhubung ke server.' }
const UNAUTHENTICATED_ERROR = { message: 'Unauthenticated.', status: 401 }
const FORBIDDEN_ERROR = { message: 'Anda tidak memiliki akses untuk aksi ini.', status: 403 }
const SERVER_ERROR = { message: 'Terjadi kesalahan pada server.', status: 500 }
/** DISALIN dari KernelDispatchReportService — akun terikat mill tanpa mill. */
const NO_MILL_ERROR = {
  message: 'Akun Anda belum terhubung ke mill. Hubungi Admin.',
  status: 422,
  errors: { business_unit_id: ['Akun Anda belum terhubung ke mill. Hubungi Admin.'] },
}

/* ------------------------------------------------------------------ */
/* Prosa SERVER — DIBACA DARI BERKAS SERVICE-nya                       */
/* ------------------------------------------------------------------ */

const SERVICE_PATH = '../backend/app/Services/KernelDispatchReportService.php'

function readSource(relative: string): string {
  return readFileSync(resolve(process.cwd(), relative), 'utf-8')
}

/**
 * Sebuah konstanta prosa PHP, dirakit dari potongan-potongan ber-kutip
 * tunggalnya.
 *
 * Pembacanya TIDAK berhenti pada `;` pertama — dua di antara kelima prosa itu
 * MEMUAT titik koma di dalam kalimatnya ('…tanpa satu sel yang ditandai;
 * penilaian layak…'), dan pembaca yang berhenti di situ memulangkan prosa yang
 * TERPOTONG. Prosa terpotong tetap membuat asersi kesetaraan penuh di bawah
 * HIJAU — fixture dan asersinya sama-sama terpotong — sambil membuktikan nol
 * hal tentang kalimat yang dibaca pengguna. Karena itu yang dipakai adalah
 * regex LENGKET (`/y`) yang memakan segmen ber-kutip satu per satu dan
 * berhenti ketika segmen berikutnya bukan string lagi, ditambah penjaga
 * panjang minimum di bawah.
 */
function phpStringConst(source: string, name: string): string {
  const marker = `const ${name} = `
  const start = source.indexOf(marker)

  expect(start, `konstanta ${name} tidak ada di KernelDispatchReportService`).toBeGreaterThan(-1)

  const segments = /\s*\.?\s*'((?:[^'\\]|\\.)*)'/y
  const parts: string[] = []

  segments.lastIndex = start + marker.length

  let match = segments.exec(source)

  while (match !== null) {
    parts.push(match[1])
    match = segments.exec(source)
  }

  const prose = parts.join('').replace(/\\'/g, "'").replace(/\\\\/g, '\\')

  // PENJAGA PARSER: prosa yang gagal dirakit memulangkan string pendek atau
  // kosong, dan kesetaraan penuh atas string kosong adalah asersi yang hijau
  // tanpa menguji apa pun.
  expect(prose.length, `prosa ${name} terlalu pendek — parser gagal`).toBeGreaterThan(700)

  return prose
}

function phpArrayConst(source: string, name: string): string[] {
  const marker = `const ${name} = [`
  const start = source.indexOf(marker)

  expect(start, `konstanta ${name} tidak ada di KernelDispatchReportService`).toBeGreaterThan(-1)

  const end = source.indexOf('];', start)

  expect(end).toBeGreaterThan(start)

  return [...source.slice(start, end).matchAll(/'((?:[^'\\]|\\.)*)'/g)].map((row) =>
    row[1].replace(/\\'/g, "'"),
  )
}

const SERVICE_SOURCE = readSource(SERVICE_PATH)

/** Kelima prosa yang layar ini render UTUH, dari servernya sendiri. */
const NOTE_QUALITY_DENOMINATORS = phpStringConst(SERVICE_SOURCE, 'NOTE_QUALITY_DENOMINATORS')
const NOTE_NO_HOURLY = phpStringConst(SERVICE_SOURCE, 'NOTE_NO_HOURLY')
const NOTE_NO_QUALITY_THRESHOLDS = phpStringConst(SERVICE_SOURCE, 'NOTE_NO_QUALITY_THRESHOLDS')
const NOTE_SHIFT_NOT_PUBLISHED = phpStringConst(SERVICE_SOURCE, 'NOTE_SHIFT_NOT_PUBLISHED')
const NOTE_COMPLIANCE = phpStringConst(SERVICE_SOURCE, 'NOTE_COMPLIANCE')

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

/** SEMBILAN hari berpengiriman di dalam periode 30 hari. */
const DISPATCH_DAYS = [-25, -22, -20, -18, -15, -12, -9, -6, -3].map(isoDaysFromNow)

/* ------------------------------------------------------------------ */
/* Fixture — BENTUK RESPONS NYATA                                      */
/* ------------------------------------------------------------------ */

const PERIOD = {
  id: 'p-1',
  name: 'Periode Uji Kernel Dispatch',
  start_date: PERIOD_START,
  end_date: PERIOD_END,
  status: 'open',
  // DENGAN TANDA HUBUNG. Garis bawah di situ tidak cocok dengan satu baris
  // period_stations pun, dan server menjawab [] tanpa satu galat.
  station_type: 'kernel-dispatch',
  station_type_label: 'Kernel Dispatch',
}

const PERIOD_CLOSED = {
  id: 'p-2',
  name: 'Periode Sebelumnya',
  start_date: PREV_START,
  end_date: PREV_END,
  status: 'closed',
  station_type: 'kernel-dispatch',
  station_type_label: 'Kernel Dispatch',
}

const LINE_3 = { id: 'l-3', name: 'Line Tiga', code: 'L3' }
const LINE_4 = { id: 'l-4', name: 'Line Empat', code: null }

const BUSINESS_UNITS = [
  { id: 'bu-1', name: 'Mill Sungai Rambutan' },
  { id: 'bu-9', name: 'Mill Sungai Lilin' },
]

/** Kunci ingatan line — ditulis StationListView.vue, dibaca layar ini. */
const REMEMBERED_LINE_KEY = 'msl_production_line_u-7'

/** Satu baris rekap kategoris, bentuk server apa adanya. */
function row(
  value: string | null,
  dispatchCount: number,
  netWeightTotal: number | null,
  netWeightDispatchCount: number,
  netWeightAvg: number | null,
): Record<string, unknown> {
  return {
    value,
    dispatch_count: dispatchCount,
    net_weight_total: netWeightTotal,
    net_weight_dispatch_count: netWeightDispatchCount,
    net_weight_avg: netWeightAvg,
  }
}

/** Satu ukuran mutu, bentuk server apa adanya. */
function measure(
  min: number | null,
  avg: number | null,
  max: number | null,
  measuredCount: number,
): Record<string, unknown> {
  return { min, avg, max, measured_count: measuredCount }
}

/** Ukuran TANPA satu hasil uji pun — null/null/null/0, BUKAN nol. */
function unmeasured(): Record<string, unknown> {
  return measure(null, null, null, 0)
}

/**
 * KEEMPAT rekap. Masing-masing berjumlah TEPAT 30 pengiriman dengan komposisi
 * BERBEDA, masing-masing memuat SATU baris `value: null` yang BUKAN di posisi
 * terakhir, dan urutannya bukan alfabetis serta bukan urut jumlah.
 */
function recapsOfThirty(): Record<string, Array<Record<string, unknown>>> {
  return {
    by_transporter: [
      row('Zulkifli Trans', 3, 120, 1, 120),
      row(null, 11, 300, 2, 150),
      row('Amanah Logistik', 7, 90, 1, 90),
      row('Bintang Mas', 9, null, 0, null),
    ],
    by_silo_source: [
      row('Silo 3 Utara', 12, 400, 2, 200),
      row(null, 5, 100, 1, 100),
      row('SL-02', 13, 250, 1, 250),
    ],
    by_destination_buyer: [
      row(null, 6, 210, 2, 105),
      row('PT Sawit Jaya', 15, 500, 2, 250),
      row('Kebun Blok C', 9, 60, 1, 60),
    ],
    by_gate_status: [
      row('released', 20, 700, 3, 233),
      row('not_released', 5, 120, 1, 120),
      row(null, 2, 40, 1, 40),
      row('held_by_security', 3, 90, 1, 90),
    ],
  }
}

/** Keempat testid rekap, dalam URUTAN PAYLOAD. */
const RECAP_TESTIDS = [
  'kd-recap-transporter',
  'kd-recap-silo-source',
  'kd-recap-destination-buyer',
  'kd-recap-gate-status',
] as const

const RECAP_KEYS = [
  'by_transporter',
  'by_silo_source',
  'by_destination_buyer',
  'by_gate_status',
] as const

/** Keempat slug kartu mutu, dalam urutan QUALITY_MEASURES service. */
const QUALITY_SLUGS = ['kernel-moisture', 'dirt-impurities', 'ffa', 'broken-kernel'] as const

const QUALITY_KEYS = [
  'kernel_moisture_percent',
  'dirt_impurities_percent',
  'ffa_percent',
  'broken_kernel_percent',
] as const

/**
 * URUTAN HARIANNYA SENGAJA bukan urut tanggal naik, dan kolomnya SENGAJA tidak
 * berjumlah ke `daily_total` — lihat docblock berkas (unit_test_case 14).
 */
function dailyRows(): Array<Record<string, unknown>> {
  return [
    {
      date: DISPATCH_DAYS[4],
      dispatch_count: 1,
      net_weight_total: 10,
      net_weight_dispatch_count: 1,
      net_weight_avg: 10,
    },
    {
      date: DISPATCH_DAYS[0],
      dispatch_count: 2,
      net_weight_total: 20,
      net_weight_dispatch_count: 1,
      net_weight_avg: 20,
    },
    {
      date: DISPATCH_DAYS[7],
      dispatch_count: 3,
      net_weight_total: 30,
      net_weight_dispatch_count: 1,
      net_weight_avg: 30,
    },
  ]
}

/** Payload /summary LENGKAP — bentuk KernelDispatchReportService::buildSummary(). */
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
    dispatches: {
      dispatch_count: 30,
      net_weight_total: 1000,
      // 1000 / 4 = 250 — angka ini SENGAJA 777.
      net_weight_avg: 777,
      net_weight_dispatch_count: 4,
      missing_net_weight_dispatch_count: 26,
    },
    quality: {
      // avg DI LUAR rentang min..max — mustahil hasil penghitungan apa pun.
      kernel_moisture_percent: measure(9, 3, 4, 5),
      dirt_impurities_percent: measure(0.2, 0.6, 1.8, 24),
      ffa_percent: measure(0.9, 1.7, 3.4, 19),
      broken_kernel_percent: measure(11, 14.5, 22, 7),
      _note: NOTE_QUALITY_DENOMINATORS,
    },
    ...recapsOfThirty(),
    not_released_count: 5,
    compliance: {
      missing_waybill_count: 4,
      missing_seal_top_count: 6,
      missing_seal_bottom_count: 2,
      _note: NOTE_COMPLIANCE,
    },
    draft_dispatch_count: 11,
    // event_date NOT NULL di skema: angkanya 0 dalam praktik, dan bloknya
    // karena itu TIDAK dirender (unit_test_case 42).
    undated_dispatch_count: 0,
    daily: dailyRows(),
    daily_total: {
      dispatch_count: 88,
      // BUKAN jumlah kolom di atasnya (10+20+30) — lihat docblock berkas.
      net_weight_total: 999,
      net_weight_dispatch_count: 3,
      net_weight_avg: 33,
    },
    completeness: {
      days_in_period: 30,
      days_with_dispatch: 3,
      days_counted: 10,
      // 3 / 10 = 30 — angka ini SENGAJA 88.8.
      percent: 88.8,
    },
    text_entries: {
      findings: [
        { date: DISPATCH_DAYS[0], value: 'Segel atas kendaraan BK 1234 XY terlepas di pos gerbang.' },
        { date: DISPATCH_DAYS[5], value: 'Inti basah pada muatan sore, dilaporkan ke laboratorium.' },
      ],
    },
    _note_no_hourly: NOTE_NO_HOURLY,
    _note_no_quality_thresholds: NOTE_NO_QUALITY_THRESHOLDS,
    _note_shift_not_published: NOTE_SHIFT_NOT_PUBLISHED,
    ...overrides,
  }
}

/** Periode SAH tetapi NOL pengiriman pada line terpilih. */
function emptyPeriodSummary(): Record<string, unknown> {
  return makeSummary({
    dispatches: {
      dispatch_count: 0,
      net_weight_total: null,
      net_weight_avg: null,
      net_weight_dispatch_count: 0,
      missing_net_weight_dispatch_count: 0,
    },
    quality: {
      kernel_moisture_percent: unmeasured(),
      dirt_impurities_percent: unmeasured(),
      ffa_percent: unmeasured(),
      broken_kernel_percent: unmeasured(),
      _note: NOTE_QUALITY_DENOMINATORS,
    },
    by_transporter: [],
    by_silo_source: [],
    by_destination_buyer: [],
    by_gate_status: [],
    not_released_count: 0,
    compliance: {
      missing_waybill_count: 0,
      missing_seal_top_count: 0,
      missing_seal_bottom_count: 0,
      _note: NOTE_COMPLIANCE,
    },
    draft_dispatch_count: 0,
    daily: [],
    daily_total: {
      dispatch_count: 0,
      net_weight_total: null,
      net_weight_dispatch_count: 0,
      net_weight_avg: null,
    },
    text_entries: { findings: [] },
  })
}

/* ------------------------------------------------------------------ */
/* Penghitung permintaan PER JALUR                                     */
/* ------------------------------------------------------------------ */

/**
 * SETIAP permintaan yang benar-benar dikirim layar ini, dicatat atas JALURNYA.
 *
 * Repo di-mock, jadi penghitungnya hidup di sini — dan pemetaan fungsi→jalur
 * itu sendiri dibuktikan unit_test_case 1 dengan repo YANG SUNGGUHAN. Dengan
 * begitu "penghitung permintaan ke /summary TEPAT 0" adalah angka yang DIUKUR,
 * bukan disimpulkan dari angka yang tidak tampil.
 */
const requests: string[] = []

function hits(path: string): number {
  return requests.filter((item) => item === path).length
}

const summaryCalls: Array<{ periodId: string; scope: KernelDispatchReportScope }> = []
const exportCalls: Array<{ periodId: string; scope: KernelDispatchReportScope }> = []
const periodsCalls: KernelDispatchReportScope[] = []
const linesCalls: KernelDispatchReportScope[] = []

/** KELIMA jalur layar ini — dipakai asersi "nol permintaan" per jalur. */
const EVERY_PATH = [BUSINESS_UNITS_PATH, PERIODS_PATH, LINES_PATH, SUMMARY_PATH, EXPORT_PATH]

/**
 * PARAMETER KAWAT sebuah pemanggilan /summary — nama kunci yang benar-benar
 * dikirim, bukan medan camelCase cakupan internal.
 *
 * Diturunkan dari `exportParams()` YANG SUNGGUHAN (ia bukan anggota yang
 * di-mock) dikurangi `format`: repo membangun parameter /summary dan /export
 * dari SATU pembentuk yang sama. Inilah yang membuat asersi "business_unit_id
 * tidak muncul sebagai KUNCI" berlaku atas KAWAT, bukan atas objek cakupan.
 */
function wireParamsOf(call: {
  periodId: string
  scope: KernelDispatchReportScope
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
  const wrapper = mount(LaporanKernelDispatchView)

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
 * PERIODE. Periode TIDAK PERNAH dipilihkan oleh layar, jadi setiap test "tepat
 * satu permintaan" memilihnya secara eksplisit.
 */
async function mountFullReport(periodId = 'p-1'): Promise<VueWrapper> {
  window.localStorage.setItem(REMEMBERED_LINE_KEY, LINE_3.id)

  const wrapper = await mountView()

  await selectPeriod(wrapper, periodId)

  return wrapper
}

/** /summary memulangkan payload tertentu, tetap lewat penghitung jalur. */
function summaryReturns(payload: Record<string, unknown>): void {
  repoMocks.fetchSummary.mockImplementation(
    (periodId: string, scope?: KernelDispatchReportScope) => {
      requests.push(SUMMARY_PATH)
      summaryCalls.push({ periodId, scope: scope ?? {} })

      return Promise.resolve(payload as unknown as KernelDispatchReportSummary)
    },
  )
}

/** Mount jalur sukses dengan payload tertentu — pola terpakai di 30+ test. */
async function mountWith(payload: Record<string, unknown>): Promise<VueWrapper> {
  summaryReturns(payload)

  return mountFullReport()
}

function text(wrapper: VueWrapper, testid: string): string {
  return wrapper.get(`[data-testid="${testid}"]`).text()
}

/**
 * textContent MENTAH sebuah elemen, hanya di-trim lalu spasinya dinormalkan.
 *
 * Dipakai untuk asersi VERBATIM: setiap elemen angka dan setiap elemen prosa
 * server adalah SATU mustache tanpa satu kata tambahan di dalamnya, jadi
 * kesamaan penuh memang berlaku.
 */
function rawText(wrapper: VueWrapper, testid: string): string {
  const content = wrapper.get(`[data-testid="${testid}"]`).element.textContent ?? ''

  return content.replace(/\s+/g, ' ').trim()
}

/**
 * textContent APA ADANYA — TANPA trim dan TANPA normalisasi spasi.
 *
 * Hanya dipakai unit_test_case 36, yang justru menguji bahwa spasi tepi TIDAK
 * dirapikan: ' PT Sawit Jaya' dan 'PT Sawit Jaya' adalah dua nilai server yang
 * berbeda, dan asersi yang ikut mem-trim akan menyatakan keduanya sama.
 */
function verbatimText(wrapper: VueWrapper, testid: string): string {
  return wrapper.get(`[data-testid="${testid}"]`).element.textContent ?? ''
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

function count(wrapper: VueWrapper, testid: string): number {
  return wrapper.findAll(`[data-testid="${testid}"]`).length
}

/** Seluruh kartu angka ber-prefix kd-kpi- — dipakai asersi "nol angka". */
function kpiElements(wrapper: VueWrapper): DOMWrapper<Element>[] {
  return wrapper.findAll('[data-testid^="kd-kpi-"]')
}

/** Testid KETIGA wadah tingkat atas ber-prefix kd-card-, dalam urutan DOM. */
function cardTestids(wrapper: VueWrapper): string[] {
  return wrapper
    .findAll('[data-testid^="kd-card-"]')
    .map((el) => el.attributes('data-testid') ?? '')
}

/** Testid SELURUH bagian tingkat atas, sebagai ARRAY BERURUT dari DOM. */
function sectionTestids(wrapper: VueWrapper): string[] {
  return wrapper.findAll('section[data-testid]').map((el) => el.attributes('data-testid') ?? '')
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

function recapDispatchCounts(wrapper: VueWrapper, testid: string): number[] {
  return wrapper
    .findAll(`[data-testid^="${testid}-dispatch-count-"]`)
    .map((cell) => idNumber(cell.text()))
}

function recapRowCount(wrapper: VueWrapper, testid: string): number {
  return wrapper.findAll(`[data-testid^="${testid}-row-"]`).length
}

function dailyDates(wrapper: VueWrapper): string[] {
  return wrapper
    .findAll('[data-testid^="kd-daily-row-"]')
    .map((rowEl) => (rowEl.attributes('data-testid') ?? '').replace('kd-daily-row-', ''))
}

function buttonNames(wrapper: VueWrapper): string[] {
  return wrapper
    .findAll('button, [role="button"]')
    .map((button) => normalize(`${button.text()} ${button.attributes('aria-label') ?? ''}`))
}

/** Kontrol lipat sebuah bagian — testid SENDIRI per rekap (P3). */
async function toggleSection(wrapper: VueWrapper, testid: string): Promise<void> {
  await wrapper.get(`[data-testid="${testid}-toggle"]`).trigger('click')
  await flushPromises()
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

type Role = 'operator' | 'supervisor' | 'mill_management' | 'admin'

function asRole(role: Role, businessUnitId: string | null, userId = 'u-7'): void {
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

/** Sumber TANPA komentar — wajib pada audit yang menyasar literal. */
function stripComments(source: string): string {
  return source
    .replace(/\/\*[\s\S]*?\*\//g, '')
    .replace(/<!--[\s\S]*?-->/g, '')
    .replace(/^\s*\/\/.*$/gm, '')
}

const VIEW_PATH = 'src/views/LaporanKernelDispatchView.vue'
const REPO_PATH = 'src/services/kernelDispatchReportRepo.ts'
const PICKER_PATH = 'src/views/ReportingPilihStasiunView.vue'
const LINE_REPO_PATH = 'src/services/productionLineRepo.ts'

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

/** Peta ikon — bernama TYPE_ICONS, bukan STATION_ICONS (DIVERGENSI 2). */
function typeIconsSource(): string {
  const code = stripComments(readSource(PICKER_PATH))
  const start = code.indexOf('const TYPE_ICONS')

  expect(start).toBeGreaterThan(-1)

  return code.slice(start, start + code.slice(start).indexOf('\n}') + 1)
}

/* ------------------------------------------------------------------ */
/* Pemasangan mock per test                                            */
/* ------------------------------------------------------------------ */

beforeEach(() => {
  pushMock.mockReset()
  logoutMock.mockClear()
  useAuthStoreMock.mockReset()

  Object.keys(routeQuery).forEach((key) => delete routeQuery[key])

  requests.length = 0
  summaryCalls.length = 0
  exportCalls.length = 0
  periodsCalls.length = 0
  linesCalls.length = 0

  for (const spy of Object.values(apiClientMocks)) {
    spy.mockReset()
  }

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

  repoMocks.fetchPeriods.mockImplementation((scope?: KernelDispatchReportScope) => {
    requests.push(PERIODS_PATH)
    periodsCalls.push(scope ?? {})

    return Promise.resolve([PERIOD, PERIOD_CLOSED])
  })

  repoMocks.fetchLines.mockImplementation((scope?: KernelDispatchReportScope) => {
    requests.push(LINES_PATH)
    linesCalls.push(scope ?? {})

    return Promise.resolve([LINE_3, LINE_4])
  })

  summaryReturns(makeSummary())

  repoMocks.exportCsv.mockImplementation((periodId: string, scope?: KernelDispatchReportScope) => {
    requests.push(EXPORT_PATH)
    exportCalls.push({ periodId, scope: scope ?? {} })

    return Promise.resolve(new Blob(['a,b']))
  })

  repoMocks.saveCsvFile.mockImplementation(() => undefined)
})

afterEach(() => {
  // DILEPAS LEBIH DULU, sebelum mock dipulihkan: onUnmounted melepas pendengar
  // online/offline, dan itu harus terjadi selagi spy window masih yang
  // dipasang test ini. Jebakan #3 pada docblock.
  while (mountedWrappers.length > 0) {
    mountedWrappers.pop()?.unmount()
  }

  while (cleanups.length > 0) {
    cleanups.pop()?.()
  }

  vi.restoreAllMocks()
})

/* ================================================================== */
/* unit_test_case 1-2 — LIMA JALUR YANG SUDAH ADA                      */
/* ================================================================== */

describe('lima jalur yang sudah ada, nol endpoint baru', () => {
  /**
   * Repo YANG SUNGGUHAN. Keenam anggotanya yang menyentuh jaringan di-mock di
   * tingkat modul demi test view, jadi kasus 1, 2 dan 54 mengambil modul
   * aslinya lewat importActual — apiClient-nya tetap yang di-mock, sehingga
   * URL dan parameter yang terkumpul adalah yang BENAR-BENAR disusun repo.
   */
  async function actualRepo() {
    return vi.importActual<typeof import('@/services/kernelDispatchReportRepo')>(
      '@/services/kernelDispatchReportRepo',
    )
  }

  const ADMIN_SCOPE: KernelDispatchReportScope = {
    isAdmin: true,
    businessUnitId: 'bu-9',
    productionLineId: 'l-3',
  }

  /**
   * Cakupan peran terikat mill yang SENGAJA menyerahkan businessUnitId tanpa
   * menyatakan isAdmin. Di JavaScript yang sudah terkompilasi tipe tidak
   * berlaku, jadi inilah satu-satunya tempat maksud "gagal tertutup" itu dapat
   * dicatat.
   */
  const MILL_BOUND_SCOPE = {
    businessUnitId: 'bu-9',
    business_unit_id: 'bu-9',
    productionLineId: 'l-3',
  } as unknown as KernelDispatchReportScope

  function answerEveryPath(): void {
    apiClientMocks.get.mockImplementation((url: string) => {
      if (url === SUMMARY_PATH) {
        return Promise.resolve({ data: makeSummary() })
      }

      if (url === EXPORT_PATH) {
        return Promise.resolve({ data: new Blob(['a,b']) })
      }

      return Promise.resolve({ data: { data: [] } })
    })

    productionLineMocks.fetchProductionLinesForReport.mockResolvedValue([LINE_3])
  }

  // unit_test_case 1
  it('unit_test_case 1: repo memanggil TEPAT kelima jalur yang sudah ada, nol endpoint baru', async () => {
    const repo = await actualRepo()

    answerEveryPath()

    await repo.fetchBusinessUnits()
    await repo.fetchPeriods(ADMIN_SCOPE)
    await repo.fetchLines(ADMIN_SCOPE)
    await repo.fetchSummary('p-1', ADMIN_SCOPE)

    const exportUrl = repo.buildExportUrl('p-1', ADMIN_SCOPE)

    await repo.exportCsv('p-1', ADMIN_SCOPE)

    const urls = apiClientMocks.get.mock.calls.map((call) => String(call[0]))

    /**
     * DIVERGENSI 1 pada docblock berkas: jalur KELIMA tidak muncul di daftar
     * ini karena `fetchLines()` MENDELEGASIKAN ke productionLineRepo, yang
     * di-mock di berkas ini demi belasan test view yang mengasersi NOL
     * pemanggilan langsung ke sana. Ketiga asersi sesudah daftar ini
     * menggantikannya, dan bersama-sama lebih kuat daripada satu baris URL.
     */
    expect(urls).toEqual([
      '/api/kernel-dispatch-reports/business-units/options',
      '/api/kernel-dispatch-reports/periods',
      '/api/kernel-dispatch-reports/summary',
      '/api/kernel-dispatch-reports/export',
    ])
    expect(apiClientMocks.get).toHaveBeenCalledTimes(4)
    expect(new Set(urls).size).toBe(4)
    expect(exportUrl.split('?')[0]).toBe('/api/kernel-dispatch-reports/export')

    // (a) Delegasinya benar-benar terjadi, TEPAT sekali, dengan mill yang
    // diminta — bukan dengan null dan bukan dua kali.
    expect(productionLineMocks.fetchProductionLinesForReport).toHaveBeenCalledTimes(1)
    expect(productionLineMocks.fetchProductionLinesForReport).toHaveBeenCalledWith('bu-9')

    // (b) Jalur kelima itu DIPAKAI ULANG VERBATIM dari screen-135 — kesamaan
    // PENUH, dan TIDAK diduplikasi di bawah prefix kernel-dispatch-reports.
    expect(LINES_PATH).toBe('/api/production-lines/options-for-report')
    expect(LINES_PATH).not.toContain('kernel-dispatch-reports')

    // (c) Dan literalnya hidup SATU kali, di repo line — bukan dua.
    const lineRepoSource = readSource(LINE_REPO_PATH)

    expect(
      lineRepoSource.split("apiClient.get('/api/production-lines/options-for-report'").length - 1,
    ).toBe(1)

    // NOL JALUR KEENAM, diasersi atas SELURUH literal '/api/...' di repo ini —
    // sebuah endpoint baru yang ditambahkan kelak akan memerah di sini meski
    // tidak satu test lain memanggilnya.
    const repoLiterals = [...new Set(readSource(REPO_PATH).match(/'\/api\/[^']*'/g) ?? [])].sort()

    expect(repoLiterals).toEqual(
      [
        "'/api/kernel-dispatch-reports/business-units/options'",
        "'/api/kernel-dispatch-reports/export'",
        "'/api/kernel-dispatch-reports/periods'",
        "'/api/kernel-dispatch-reports/summary'",
        "'/api/production-lines/options-for-report'",
      ].sort(),
    )

    for (const url of [...urls, LINES_PATH]) {
      expect(
        url.startsWith('/api/kernel-dispatch-reports/') ||
          url === '/api/production-lines/options-for-report',
        `URL di luar kedua prefix layar ini: ${url}`,
      ).toBe(true)
    }
  })

  // unit_test_case 2
  it('unit_test_case 2: business_unit_id dikirim HANYA untuk admin — atas QUERY STRING pada kedua arah', async () => {
    const repo = await actualRepo()

    /** Query string yang DIPARSE dari parameter sebuah pemanggilan apiClient. */
    function searchOf(index: number): URLSearchParams {
      const config = apiClientMocks.get.mock.calls[index][1] as
        | { params?: Record<string, string> }
        | undefined

      return new URLSearchParams(config?.params ?? {})
    }

    for (const role of MILL_BOUND_ROLES) {
      answerEveryPath()
      apiClientMocks.get.mockClear()

      await repo.fetchPeriods(MILL_BOUND_SCOPE)
      await repo.fetchSummary('p-1', MILL_BOUND_SCOPE)

      // KEDUA ARAH, dan atas QUERY STRING YANG DIPARSE — bukan atas substring
      // URL, yang akan cocok pada nama parameter lain yang memuatnya.
      for (const index of [0, 1]) {
        const search = searchOf(index)

        expect(search.has('business_unit_id'), `${role} / panggilan ${index}`).toBe(false)
        // Dan BUKAN string kosong, 'null', maupun 'undefined' — ketiganya
        // adalah cara sebuah kunci tetap terkirim sambil lolos dari asersi
        // keberadaan nilai.
        expect(search.get('business_unit_id'), `${role} / panggilan ${index}`).toBeNull()
        expect(search.toString(), `${role} / panggilan ${index}`).not.toContain('business_unit_id')
      }

      // /summary tetap membawa kedua parameter lingkupnya sendiri.
      expect(searchOf(1).get('period_id')).toBe('p-1')
      expect(searchOf(1).get('production_line_id')).toBe('l-3')
    }

    answerEveryPath()
    apiClientMocks.get.mockClear()

    await repo.fetchPeriods(ADMIN_SCOPE)
    await repo.fetchSummary('p-1', ADMIN_SCOPE)

    // Admin: KESAMAAN PENUH dengan id mill terpilih, pada KEDUA jalur.
    expect(searchOf(0).get('business_unit_id')).toBe('bu-9')
    expect(searchOf(1).get('business_unit_id')).toBe('bu-9')
  })
})

/* ================================================================== */
/* unit_test_case 3-4 — PERAN DAN LAYAR BACA SAJA                      */
/* ================================================================== */

describe('peran, pemilih Mill, dan layar baca saja', () => {
  // unit_test_case 3
  it('unit_test_case 3: pemilih Mill hanya untuk Admin, dan /business-units/options nol kali untuk ketiga peran lain', async () => {
    for (const role of MILL_BOUND_ROLES) {
      requests.length = 0
      asRole(role, 'bu-1')

      const wrapper = await mountView()

      expect(count(wrapper, 'mill-select'), role).toBe(0)
      // DIHITUNG, bukan disimpulkan dari pemilih yang tidak tampil: daftar
      // seluruh mill di perangkat orang yang tidak berhak melihat satu pun di
      // antaranya adalah kebocoran, bukan sekadar permintaan yang percuma.
      expect(hits(BUSINESS_UNITS_PATH), role).toBe(0)
      // Dan chip mill-nya JUSTRU ADA — ikatan akunnya dinyatakan, bukan
      // disembunyikan.
      expect(exists(wrapper, 'mill-current'), role).toBe(true)

      wrapper.unmount()
      mountedWrappers.pop()
    }

    requests.length = 0
    asRole('admin', null)

    const adminWrapper = await mountView()

    expect(count(adminWrapper, 'mill-select')).toBe(1)
    expect(hits(BUSINESS_UNITS_PATH)).toBe(1)
    // Admin belum memilih mill: arahan memilih, dan NOL permintaan lain.
    expect(exists(adminWrapper, 'mill-required-hint')).toBe(true)
    expect(hits(PERIODS_PATH)).toBe(0)
    expect(hits(LINES_PATH)).toBe(0)
    expect(hits(SUMMARY_PATH)).toBe(0)
  })

  // unit_test_case 4
  it('unit_test_case 4: layar baca saja — nol permintaan bermetode tulis di SELURUH jalur', async () => {
    const wrapper = await mountFullReport()

    await selectLine(wrapper, LINE_4.id)
    await selectPeriod(wrapper, 'p-2')

    // Kelima bagian yang dapat dilipat: keempat rekap + rekap harian. Dilipat
    // lalu dibuka kembali — keduanya murni penyingkapan.
    for (const testid of [...RECAP_TESTIDS, 'kd-daily-recap']) {
      await toggleSection(wrapper, testid)
      await toggleSection(wrapper, testid)
    }

    await wrapper.get('[data-testid="kd-export-csv"]').trigger('click')
    await flushPromises()

    // PENGHITUNG METODE TULIS — DIHITUNG, tepat nol pada keempatnya.
    for (const method of ['post', 'put', 'patch', 'delete'] as const) {
      expect(apiClientMocks[method], `apiClient.${method} dipanggil`).not.toHaveBeenCalled()
    }

    // Layar berbicara HANYA lewat repo: pemanggilan langsung ke apiClient akan
    // terhitung di sini, termasuk sebuah GET yang memintas repo.
    expect(apiClientMocks.get).not.toHaveBeenCalled()

    for (const [name, spy] of Object.entries(localDbMocks)) {
      expect(spy, `localDb.${name} dipanggil`).not.toHaveBeenCalled()
    }

    // NOL KONTROL TULIS DI DOM: hanya kedua pemilih (Mill tidak dirender untuk
    // Operator) dan tombol-tombol baca/ekspor.
    expect(wrapper.findAll('input')).toHaveLength(0)
    expect(wrapper.findAll('textarea')).toHaveLength(0)
    expect(wrapper.findAll('form')).toHaveLength(0)
    expect(wrapper.findAll('select')).toHaveLength(2)

    for (const name of buttonNames(wrapper)) {
      expect(name, `tombol tulis: ${name}`).not.toMatch(/simpan|save|hapus|kirim|submit/i)
    }

    /**
     * Audit statis atas sumbernya: tidak ada satu metode tulis pun yang dapat
     * dipanggil dari jalur mana pun, termasuk jalur yang belum diuji.
     *
     * DISASAR KE `apiClient.<metode>(`, BUKAN ke `.delete(` telanjang: sapuan
     * telanjang itu cocok dengan `next.delete(testid)` pada kontrol lipat —
     * sebuah operasi Set — dan audit yang menuduh baris itu sebagai permintaan
     * tulis adalah audit yang memerah atas kode yang justru benar.
     */
    const sources = `${stripComments(scriptBlockOf(readSource(VIEW_PATH)))}\n${stripComments(readSource(REPO_PATH))}`

    for (const method of ['post', 'put', 'patch', 'delete'] as const) {
      expect(sources, `apiClient.${method} ada di sumber`).not.toContain(`apiClient.${method}(`)
      expect(sources, `metode ${method} disusun lewat config`).not.toMatch(
        new RegExp(`method\\s*:\\s*['\"]${method}['\"]`, 'i'),
      )
    }
  })
})

/* ================================================================== */
/* unit_test_case 5-8 — PENJAGAAN LINE DI SATU FUNGSI PEMUAT            */
/* ================================================================== */

describe('penjagaan Production Line ada di SATU fungsi pemuat', () => {
  /**
   * PERIODE TIDAK PERNAH DIPILIHKAN LAYAR INI, jadi setiap test di bawah
   * memilih periode SECARA EKSPLISIT sebelum mengukur penghitung /summary.
   *
   * Tanpa langkah itu yang menahan permintaan adalah penjaga PERIODE, bukan
   * penjaga LINE — dan test-nya akan hijau pada layar yang penjaga line-nya
   * dihapus seluruhnya. Divergensi yang DICATAT, bukan kelonggaran: huruf
   * case 5 berbunyi "periode pertama terpilih", dan tidak ada jalur kode di
   * layar ini yang memilihkannya.
   */
  // unit_test_case 5
  it('unit_test_case 5: line belum dipilih pada MUAT AWAL menahan /summary — dihitung tepat 0', async () => {
    const wrapper = await mountView()

    // Ingatan perangkat kosong (dibersihkan beforeEach) dan daftar line berisi
    // dua entri, jadi tidak ada line yang berlaku.
    expect(selectedOptionText(wrapper, 'production-line-select')).toBe(NO_LINE_SELECTED)

    await selectPeriod(wrapper, 'p-1')

    expect(hits(SUMMARY_PATH)).toBe(0)

    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)

    // Kalimatnya MENYATAKAN apa yang ditunggu, dan diasersi pada ELEMEN
    // CATATAN ITU — bukan pada halaman.
    const hint = normalize(text(wrapper, 'production-line-required-hint'))

    expect(hint).toContain('Pilih Production Line terlebih dahulu')
    expect(hint).toContain('tidak satu permintaan pun dikirim ke server')

    expect(count(wrapper, 'kd-card-tonnage')).toBe(0)
    expect(count(wrapper, 'kd-card-quality')).toBe(0)
  })

  // unit_test_case 6
  it('unit_test_case 6: line belum dipilih pada jalur GANTI PERIODE menahan /summary — dihitung tepat 0', async () => {
    const wrapper = await mountView()

    // GANTI PERIODE adalah pemanggil KEDUA dari fungsi pemuat yang sama, jadi
    // jalur ini diuji terpisah dari muat awal.
    for (const periodId of ['p-1', 'p-2', 'p-1']) {
      await selectPeriod(wrapper, periodId)

      expect(hits(SUMMARY_PATH), `sesudah ganti ke ${periodId}`).toBe(0)
    }

    expect(hits(SUMMARY_PATH)).toBe(0)
    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)
  })

  // unit_test_case 7
  it('unit_test_case 7: line belum dipilih pada jalur COBA LAGI menahan /summary — dihitung tepat 0', async () => {
    let periodsFailures = 4

    repoMocks.fetchPeriods.mockImplementation((scope?: KernelDispatchReportScope) => {
      requests.push(PERIODS_PATH)
      periodsCalls.push(scope ?? {})

      if (periodsFailures > 0) {
        periodsFailures -= 1

        return Promise.reject(SERVER_ERROR)
      }

      return Promise.resolve([PERIOD, PERIOD_CLOSED])
    })

    const wrapper = await mountView()

    expect(exists(wrapper, 'retry-button')).toBe(true)

    // COBA LAGI adalah pemanggil KETIGA dari fungsi pemuat yang sama
    // (retryPeriodsThenSummary memanggil loadSummary tanpa syarat).
    for (const attempt of [1, 2, 3]) {
      await wrapper.get('[data-testid="retry-button"]').trigger('click')
      await flushPromises()

      expect(hits(SUMMARY_PATH), `sesudah ketukan ${attempt}`).toBe(0)
    }

    // Dan sekali daftar periodenya berhasil, jalur Coba Lagi yang SAMA tetap
    // berhenti di penjaga LINE — bukan di penjaga periode: inilah yang
    // membuktikan penjaganya memang penjaga line.
    await wrapper.get('[data-testid="retry-button"]').trigger('click')
    await flushPromises()

    expect(hits(PERIODS_PATH)).toBe(5)
    expect(hits(SUMMARY_PATH)).toBe(0)

    await selectPeriod(wrapper, 'p-1')

    expect(hits(SUMMARY_PATH)).toBe(0)
    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)
  })

  // unit_test_case 8
  it('unit_test_case 8: penjagaan ada di SATU fungsi pemuat, bukan disebar di pemanggilnya', async () => {
    let periodsFailures = 4

    repoMocks.fetchPeriods.mockImplementation((scope?: KernelDispatchReportScope) => {
      requests.push(PERIODS_PATH)
      periodsCalls.push(scope ?? {})

      if (periodsFailures > 0) {
        periodsFailures -= 1

        return Promise.reject(SERVER_ERROR)
      }

      return Promise.resolve([PERIOD, PERIOD_CLOSED])
    })

    // KETIGA JALUR, BERURUTAN, DALAM SATU TEST, seluruhnya tanpa line
    // terpilih. Yang diuji BUKAN ketiga jalur secara terpisah melainkan bahwa
    // penjagaannya TUNGGAL: penjagaan yang disebar di pemanggil akan
    // meloloskan satu jalur yang terlewat, dan angka ini menjadi > 0.
    const wrapper = await mountView()
    const snapshots: number[] = [hits(SUMMARY_PATH)]

    for (const attempt of [1, 2, 3]) {
      await wrapper.get('[data-testid="retry-button"]').trigger('click')
      await flushPromises()
      snapshots.push(hits(SUMMARY_PATH))

      expect(attempt).toBeLessThan(4)
    }

    // Ketukan keempat memulangkan daftar periode, supaya ketiga penggantian
    // periode di bawah benar-benar dapat dilakukan.
    await wrapper.get('[data-testid="retry-button"]').trigger('click')
    await flushPromises()
    snapshots.push(hits(SUMMARY_PATH))

    for (const periodId of ['p-1', 'p-2', 'p-1']) {
      await selectPeriod(wrapper, periodId)
      snapshots.push(hits(SUMMARY_PATH))
    }

    expect(snapshots).toEqual([0, 0, 0, 0, 0, 0, 0, 0])
    expect(hits(SUMMARY_PATH)).toBe(0)

    // Dan sesudah sebuah line dipilih, permintaan itu memang terkirim — nol di
    // atas adalah penjagaan, bukan layar yang mati.
    await selectLine(wrapper, LINE_3.id)

    expect(hits(SUMMARY_PATH)).toBe(1)

    /**
     * AUDIT STATIS: penjaganya ada DI DALAM loadSummary, dan ketiga
     * PEMANGGILNYA tidak punya pemeriksaan sendiri.
     *
     * Yang TIDAK diasersi di sini: "kata penjaga itu muncul sekali di seluruh
     * berkas". Ia memang muncul empat kali, dan ketiga kemunculan lain BENAR —
     * `productionLineRequired` (memilih kalimat), `onProductionLineChange`
     * (line dikosongkan pengguna), dan `onExport` (ekspor mengikuti cakupan
     * yang sama). Asersi "tepat satu" akan memerah atas ketiganya tanpa
     * mengatakan apa pun tentang yang sesungguhnya diuji.
     */
    const code = stripComments(scriptBlockOf(readSource(VIEW_PATH)))

    function bodyOf(signature: string): string {
      const start = code.indexOf(signature)

      expect(start, `fungsi ${signature} tidak ada`).toBeGreaterThan(-1)

      return code.slice(start, start + code.slice(start).indexOf('\n}') + 1)
    }

    expect(bodyOf('async function loadSummary')).toContain(
      '!periodId || !selectedProductionLineId.value',
    )

    for (const caller of [
      'async function bootstrap',
      'async function onPeriodChange',
      'async function retryPeriodsThenSummary',
    ]) {
      const body = bodyOf(caller)

      expect(body, `${caller} memanggil pemuat`).toContain('loadSummary()')
      expect(body, `${caller} punya penjaga line sendiri`).not.toContain(
        'selectedProductionLineId.value',
      )
    }
  })
})

/* ================================================================== */
/* unit_test_case 9-13 — LINE ADALAH KONTEKS YANG DIPILIH               */
/* ================================================================== */

describe('line adalah konteks yang DIPILIH, bukan ikatan akun', () => {
  // unit_test_case 9
  it('unit_test_case 9: kunci penyimpanan perangkat sama PENUH dengan yang ditulis layar daftar stasiun', async () => {
    // Id pengguna MENGIKUTI HURUF CASE-nya ('user-1'), bukan 'u-7' yang
    // dipakai test lain di berkas ini: yang diuji di sini adalah bentuk
    // kuncinya, dan bentuk itu paling jelas terbaca pada id yang disebut
    // case-nya sendiri.
    asRole('operator', 'bu-1', 'user-1')

    const getItemSpy = vi.spyOn(Storage.prototype, 'getItem')

    window.localStorage.setItem('msl_production_line_user-1', LINE_3.id)
    getItemSpy.mockClear()

    await mountView()

    // SPY PADA PROTOTYPE menerima panggilan — DIBUKTIKAN lebih dulu. Spy pada
    // window.localStorage menerima NOL panggilan di jsdom (instance-nya
    // dibungkus Proxy) dan test-nya hijau tanpa menguji apa pun.
    expect(getItemSpy.mock.calls.length).toBeGreaterThanOrEqual(1)

    const keys = getItemSpy.mock.calls.flat().map(String)
    const lineKeys = [...new Set(keys.filter((key) => key.startsWith('msl_production_line')))]

    // KESAMAAN PENUH atas string lengkapnya — BUKAN toContain dan BUKAN
    // regex: kunci berprefiks lain akan terkompilasi, terlihat benar, dan
    // tidak pernah cocok dengan apa yang ditulis StationListView.vue.
    expect(lineKeys).toEqual(['msl_production_line_user-1'])
    expect(lineKeys[0]).toBe('msl_production_line_user-1')
  })

  // unit_test_case 10
  it('unit_test_case 10: line yang diingat perangkat dipakai tanpa diminta memilih, DAN terbawa ke query /summary', async () => {
    const LINE_PL7 = { id: 'pl-7', name: 'Line PL Tujuh', code: 'PL7' }

    repoMocks.fetchLines.mockImplementation((scope?: KernelDispatchReportScope) => {
      requests.push(LINES_PATH)
      linesCalls.push(scope ?? {})

      return Promise.resolve([LINE_3, LINE_PL7])
    })

    window.localStorage.setItem(REMEMBERED_LINE_KEY, 'pl-7')

    const wrapper = await mountView()

    expect(count(wrapper, 'production-line-required-hint')).toBe(0)
    // Pemilih line TETAP dirender dan nilainya 'pl-7' — dipakai tanpa diminta
    // memilih bukan berarti tidak dapat diganti.
    expect(count(wrapper, 'production-line-select')).toBe(1)
    expect(selectedOptionText(wrapper, 'production-line-select')).toBe(LINE_PL7.name)

    await selectPeriod(wrapper, 'p-1')

    expect(hits(SUMMARY_PATH)).toBe(1)
    expect(wireParamsOf(summaryCalls[0]).production_line_id).toBe('pl-7')
    expect(wireParamsOf(summaryCalls[0])).toEqual({
      period_id: 'p-1',
      production_line_id: 'pl-7',
    })

    // Dan ia memang dapat diganti.
    await selectLine(wrapper, LINE_3.id)

    expect(wireParamsOf(summaryCalls[1]).production_line_id).toBe('l-3')
  })

  // unit_test_case 11
  it('unit_test_case 11: line yang diingat sudah tidak sah DIBUANG, bukan dipakai diam-diam', async () => {
    window.localStorage.setItem(REMEMBERED_LINE_KEY, 'pl-lama')

    const wrapper = await mountView()

    await selectPeriod(wrapper, 'p-1')

    expect(hits(SUMMARY_PATH)).toBe(0)
    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)
    expect(kpiElements(wrapper)).toHaveLength(0)
    expect(cardTestids(wrapper)).toEqual([])

    // Dan 'pl-lama' tidak pernah muncul di satu query string pun — diasersi
    // atas SELURUH cakupan yang diserahkan ke repo, bukan atas satu
    // pemanggilan yang kebetulan diperiksa.
    const everyScope = JSON.stringify([
      ...periodsCalls,
      ...linesCalls,
      ...summaryCalls,
      ...exportCalls,
    ])

    expect(everyScope).not.toContain('pl-lama')
    expect(window.localStorage.getItem(REMEMBERED_LINE_KEY)).toBe('pl-lama')
  })

  // unit_test_case 12
  it('unit_test_case 12: penyimpanan perangkat MELEMPAR saat dibaca (mode privat) tidak merusak layar', async () => {
    const getItemSpy = vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
      throw new DOMException('storage disabled')
    })

    const wrapper = await mountView()

    // LANGKAH PERTAMA, sebelum satu asersi lain dipercaya: spy-nya menerima
    // minimal satu panggilan. WAJIB pada Storage.prototype — lihat jebakan #2.
    expect(getItemSpy.mock.calls.length).toBeGreaterThanOrEqual(1)

    expect(exists(wrapper, 'laporan-kernel-dispatch-mobile')).toBe(true)
    expect(exists(wrapper, 'production-line-required-hint')).toBe(true)
    // NOL elemen galat: penyimpanan yang tidak dapat dibaca BUKAN galat.
    expect(count(wrapper, 'error-message')).toBe(0)
    expect(count(wrapper, 'network-error')).toBe(0)

    await selectPeriod(wrapper, 'p-1')
    await selectLine(wrapper, LINE_3.id)

    expect(hits(SUMMARY_PATH)).toBe(1)
    expect(exists(wrapper, 'kd-card-tonnage')).toBe(true)
  })

  // unit_test_case 13
  it('unit_test_case 13: mill dengan SATU production line tetap TIDAK dipilihkan', async () => {
    repoMocks.fetchLines.mockImplementation((scope?: KernelDispatchReportScope) => {
      requests.push(LINES_PATH)
      linesCalls.push(scope ?? {})

      return Promise.resolve([LINE_3])
    })

    const wrapper = await mountView()

    // Pemilih TETAP dirender dengan satu opsi, dan nilainya KOSONG.
    expect(count(wrapper, 'production-line-select')).toBe(1)
    expect(
      wrapper
        .get('[data-testid="production-line-select"]')
        .findAll('option')
        .map((option) => normalize(option.text())),
    ).toEqual([NO_LINE_SELECTED, LINE_3.name])
    expect(selectedOptionText(wrapper, 'production-line-select')).toBe(NO_LINE_SELECTED)

    await selectPeriod(wrapper, 'p-1')

    expect(hits(SUMMARY_PATH)).toBe(0)

    // Sesudah pengguna memilih satu-satunya line itu SECARA SADAR.
    await selectLine(wrapper, LINE_3.id)

    expect(hits(SUMMARY_PATH)).toBe(1)
    expect(exists(wrapper, 'kd-card-tonnage')).toBe(true)
  })
})

/* ================================================================== */
/* unit_test_case 14-19 — ANGKA PAYLOAD DIGEMAKAN, NULL PER MEDAN       */
/* ================================================================== */

describe('klien tidak menghitung satu agregat pun', () => {
  // unit_test_case 14
  it('unit_test_case 14: KLIEN TIDAK MENGHITUNG SATU AGREGAT PUN — fixture yang aritmetikanya SENGAJA tidak konsisten', async () => {
    const wrapper = await mountFullReport()

    /**
     * EMPAT KETIDAKKONSISTENAN SEKALIGUS, dan tidak satu pun dari keempat
     * angka ini dapat diturunkan dari angka lain di payload yang sama —
     * lihat docblock berkas. Setiap penghitungan ulang di klien PASTI
     * memerahkan test ini.
     *
     * KOREKSI ATAS HURUF CASE-nya (P1 pada docblock berkas): case 14 menuntut
     * '9'/'3'/'4' dan '999', sementara case 18 menuntut '0,00' untuk payload 0.
     * Tidak ada satu Intl.NumberFormat pun yang memenuhi keduanya, dan yang
     * dimenangkan adalah case 18 karena ia menguji perbedaan yang mengubah
     * ARTI (nol terukur lawan nilai yang tidak ada). Maksud case ini — payload
     * DIGEMAKAN, tidak pernah dihitung ulang — tetap teruji PENUH: `avg` yang
     * berada DI LUAR rentang min..max mustahil berasal dari penghitungan apa
     * pun, berapa pun angka di belakang komanya.
     */
    const expected: Record<string, string> = {
      // 1000 / 4 = 250, bukan 777.
      'kd-kpi-net-weight-avg': '777,0',
      // 10 + 20 + 30 = 60, bukan 999.
      'kd-daily-total-net-weight-total': '999,0',
      // kolom hariannya berjumlah 6, bukan 88.
      'kd-daily-total-dispatch-count': '88',
      // 3 / 10 = 30, bukan 88,8.
      'kd-coverage-percent': '88,8',
      // avg DI LUAR rentang min..max.
      'kd-quality-kernel-moisture-min': '9,00',
      'kd-quality-kernel-moisture-avg': '3,00',
      'kd-quality-kernel-moisture-max': '4,00',
    }

    for (const [testid, value] of Object.entries(expected)) {
      expect(rawText(wrapper, testid), testid).toBe(value)
    }

    // ANGKANYA BARE — satuannya hidup di pil SEBELAHNYA (jebakan #5).
    expect(rawText(wrapper, 'kd-kpi-net-weight-avg')).not.toContain('MT')
    expect(rawText(wrapper, 'kd-coverage-percent')).not.toContain('%')

    // Dan audit statis: tidak ada satu operator aritmetika pun yang menyentuh
    // angka laporan di seluruh berkas view.
    const code = stripComments(scriptBlockOf(readSource(VIEW_PATH)))

    for (const forbidden of ['.reduce(', '.sort(', 'Math.min(', 'Math.max(']) {
      expect(code, `aritmetika klien: ${forbidden}`).not.toContain(forbidden)
    }
  })

  // unit_test_case 15
  it('unit_test_case 15: dispatch_count dicetak dari payload meski BERBEDA dari penjumlahan baris rekap', async () => {
    /**
     * Fixture ini dan fixture unit_test_case 34 ("penjumlahan rekap =
     * dispatch_count") SENGAJA BERBEDA, dengan maksud yang berbeda: yang ini
     * membuktikan klien MENAMPILKAN medan, yang itu membuktikan KONTRAK
     * server.
     */
    const wrapper = await mountWith(
      makeSummary({
        dispatches: {
          dispatch_count: 42,
          net_weight_total: 1000,
          net_weight_avg: 777,
          net_weight_dispatch_count: 4,
          missing_net_weight_dispatch_count: 38,
        },
        by_transporter: [
          row('Zulkifli Trans', 3, 120, 1, 120),
          row(null, 1, 30, 1, 30),
          row('Amanah Logistik', 3, 90, 1, 90),
        ],
      }),
    )

    expect(rawText(wrapper, 'kd-kpi-dispatch-count')).toBe('42')
    // Penjumlahan baris rekapnya 7 — dan layar TIDAK mencetaknya.
    expect(recapDispatchCounts(wrapper, 'kd-recap-transporter').reduce((a, b) => a + b, 0)).toBe(7)
  })

  // unit_test_case 16
  it('unit_test_case 16: min/avg/max keempat ukuran mutu dibaca dari payload — dua belas asersi angka terpisah', async () => {
    const wrapper = await mountWith(
      makeSummary({
        quality: {
          kernel_moisture_percent: measure(6.1, 7.4, 9.9, 5),
          dirt_impurities_percent: measure(0.2, 0.6, 1.8, 8),
          ffa_percent: measure(0.9, 1.7, 3.4, 11),
          broken_kernel_percent: measure(11, 14.5, 22, 13),
          _note: NOTE_QUALITY_DENOMINATORS,
        },
      }),
    )

    /**
     * DUA BELAS ASERSI TERPISAH atas dua belas testid, lewat peta
     * testid→nilai — BUKAN satu asersi atas teks kartu: satu kartu yang
     * kebetulan memuat semua angka akan meloloskan ukuran yang TERTUKAR
     * nilainya.
     */
    const expected: Record<string, string> = {
      'kd-quality-kernel-moisture-min': '6,10',
      'kd-quality-kernel-moisture-avg': '7,40',
      'kd-quality-kernel-moisture-max': '9,90',
      'kd-quality-dirt-impurities-min': '0,20',
      'kd-quality-dirt-impurities-avg': '0,60',
      'kd-quality-dirt-impurities-max': '1,80',
      'kd-quality-ffa-min': '0,90',
      'kd-quality-ffa-avg': '1,70',
      'kd-quality-ffa-max': '3,40',
      'kd-quality-broken-kernel-min': '11,00',
      'kd-quality-broken-kernel-avg': '14,50',
      'kd-quality-broken-kernel-max': '22,00',
    }

    for (const [testid, value] of Object.entries(expected)) {
      expect(rawText(wrapper, testid), testid).toBe(value)
    }

    // Keempat kartunya ADA, satu per ukuran, dan tidak ada yang kelima.
    expect(wrapper.findAll('[data-testid^="kd-quality-card-"]')).toHaveLength(4)

    for (const slug of QUALITY_SLUGS) {
      expect(exists(wrapper, `kd-quality-card-${slug}`), slug).toBe(true)
    }
  })

  // unit_test_case 17
  it("unit_test_case 17: null → '—' PER FIELD, bukan lewat default ?? 0 di satu tempat", async () => {
    const wrapper = await mountWith(
      makeSummary({
        dispatches: {
          dispatch_count: 30,
          net_weight_total: null,
          // Medan DI SEBELAHNYA tetap berangka — itulah yang membuktikan
          // penjaganya per field.
          net_weight_avg: 777,
          net_weight_dispatch_count: 4,
          missing_net_weight_dispatch_count: 26,
        },
        quality: {
          kernel_moisture_percent: measure(9, 3, 4, 5),
          dirt_impurities_percent: measure(0.2, 0.6, 1.8, 24),
          ffa_percent: unmeasured(),
          broken_kernel_percent: measure(11, 14.5, 22, 7),
          _note: NOTE_QUALITY_DENOMINATORS,
        },
        completeness: {
          days_in_period: 30,
          days_with_dispatch: 3,
          days_counted: 10,
          percent: null,
        },
      }),
    )

    const dashed = [
      'kd-kpi-net-weight-total',
      'kd-coverage-percent',
      'kd-quality-ffa-min',
      'kd-quality-ffa-avg',
      'kd-quality-ffa-max',
    ]

    for (const testid of dashed) {
      expect(rawText(wrapper, testid), testid).toBe('—')
      // TIDAK SATU PUN mencetak '0', '0,0' atau '0,00': satu default global
      // akan mengubah 'tidak diketahui' menjadi 'nol' di seluruh layar
      // sekaligus.
      for (const zero of ['0', '0,0', '0,00']) {
        expect(rawText(wrapper, testid), `${testid} / ${zero}`).not.toBe(zero)
      }
    }

    // Dan medan berangka DI SEKITARNYA TETAP berangka.
    expect(rawText(wrapper, 'kd-kpi-net-weight-avg')).toBe('777,0')
    expect(rawText(wrapper, 'kd-kpi-dispatch-count')).toBe('30')
    expect(rawText(wrapper, 'kd-coverage-denominator')).toBe('10')
    expect(rawText(wrapper, 'kd-quality-kernel-moisture-avg')).toBe('3,00')
    expect(rawText(wrapper, 'kd-quality-dirt-impurities-avg')).toBe('0,60')
  })

  // unit_test_case 18
  it("unit_test_case 18: angka 0 yang SUNGGUHAN tetap dicetak 0, bukan '—'", async () => {
    const wrapper = await mountWith(
      makeSummary({
        dispatches: {
          dispatch_count: 30,
          net_weight_total: 1000,
          net_weight_avg: 777,
          net_weight_dispatch_count: 4,
          missing_net_weight_dispatch_count: 0,
        },
        quality: {
          kernel_moisture_percent: measure(9, 3, 4, 5),
          // NOL YANG BENAR-BENAR TERUKUR, dengan penyebut 12.
          dirt_impurities_percent: measure(0, 0, 0, 12),
          ffa_percent: measure(0.9, 1.7, 3.4, 19),
          broken_kernel_percent: measure(11, 14.5, 22, 7),
          _note: NOTE_QUALITY_DENOMINATORS,
        },
        not_released_count: 0,
        compliance: {
          missing_waybill_count: 0,
          missing_seal_top_count: 0,
          missing_seal_bottom_count: 0,
          _note: NOTE_COMPLIANCE,
        },
        draft_dispatch_count: 0,
      }),
    )

    /**
     * ENAM elemen penghitung mencetak '0'; "ketujuh" pada huruf case-nya
     * menghitung blok kadar kotoran sebagai satu butir, dan ketiga angkanya
     * diasersi tersendiri di bawah sebagai '0,00'.
     */
    for (const testid of [
      'kd-kpi-not-released',
      'kd-draft-dispatch-count',
      'kd-kpi-missing-net-weight',
      'kd-compliance-missing-waybill',
      'kd-compliance-missing-seal-top',
      'kd-compliance-missing-seal-bottom',
    ]) {
      expect(rawText(wrapper, testid), testid).toBe('0')
      expect(rawText(wrapper, testid), testid).not.toBe('—')
    }

    for (const part of ['min', 'avg', 'max'] as const) {
      expect(rawText(wrapper, `kd-quality-dirt-impurities-${part}`), part).toBe('0,00')
      expect(rawText(wrapper, `kd-quality-dirt-impurities-${part}`), part).not.toBe('—')
    }

    // Nol yang TERUKUR punya penyebutnya, dan kartunya TIDAK menyatakan
    // 'belum ada hasil uji' — penjaganya memeriksa null, BUKAN kebenaran
    // nilai, karena falsiness JavaScript menukar 0 dengan 'tidak ada'.
    expect(rawText(wrapper, 'kd-quality-dirt-impurities-count')).toBe('12')
    expect(count(wrapper, 'kd-quality-unmeasured-dirt-impurities')).toBe(0)
  })

  // unit_test_case 19
  it('unit_test_case 19: seluruh pengiriman belum selesai ditimbang — berat "—", keempat ukuran mutu TIDAK terpengaruh', async () => {
    const wrapper = await mountWith(
      makeSummary({
        dispatches: {
          dispatch_count: 17,
          net_weight_total: null,
          net_weight_avg: null,
          net_weight_dispatch_count: 0,
          missing_net_weight_dispatch_count: 17,
        },
        quality: {
          kernel_moisture_percent: measure(6.1, 7.4, 9.9, 5),
          dirt_impurities_percent: measure(0.2, 0.6, 1.8, 8),
          ffa_percent: measure(0.9, 1.7, 3.4, 11),
          broken_kernel_percent: measure(11, 14.5, 22, 13),
          _note: NOTE_QUALITY_DENOMINATORS,
        },
      }),
    )

    expect(rawText(wrapper, 'kd-kpi-net-weight-total')).toBe('—')
    expect(rawText(wrapper, 'kd-kpi-net-weight-avg')).toBe('—')
    // PENYEBUT NOL tetap dicetak 0 — ia terukur, bukan tidak diketahui.
    expect(rawText(wrapper, 'kd-kpi-net-weight-dispatch-count')).toBe('0')
    expect(rawText(wrapper, 'kd-kpi-missing-net-weight')).toBe('17')

    // DUA BELAS angka mutu TETAP berangka: mutu dan berat adalah kolom yang
    // berbeda, dan penjaga berat yang mengosongkan kartu mutu adalah cacat.
    const quality: Record<string, string> = {
      'kd-quality-kernel-moisture-min': '6,10',
      'kd-quality-kernel-moisture-avg': '7,40',
      'kd-quality-kernel-moisture-max': '9,90',
      'kd-quality-dirt-impurities-min': '0,20',
      'kd-quality-dirt-impurities-avg': '0,60',
      'kd-quality-dirt-impurities-max': '1,80',
      'kd-quality-ffa-min': '0,90',
      'kd-quality-ffa-avg': '1,70',
      'kd-quality-ffa-max': '3,40',
      'kd-quality-broken-kernel-min': '11,00',
      'kd-quality-broken-kernel-avg': '14,50',
      'kd-quality-broken-kernel-max': '22,00',
    }

    for (const [testid, value] of Object.entries(quality)) {
      expect(rawText(wrapper, testid), testid).toBe(value)
      expect(rawText(wrapper, testid), testid).not.toBe('—')
    }
  })
})

/* ================================================================== */
/* unit_test_case 20-27 — KARTU TONASE DAN KARTU MUTU                   */
/* ================================================================== */

describe('kartu tonase dan kartu mutu', () => {
  // unit_test_case 20
  it('unit_test_case 20: net_weight_dispatch_count dicetak di KARTU YANG SAMA dengan rata-ratanya', async () => {
    const wrapper = await mountFullReport()

    const tonnage = wrapper.get('[data-testid="kd-card-tonnage"]')

    // ASERSI KETERKANDUNGAN di dalam wadah yang sama — bukan dua pencarian
    // terpisah di tingkat wrapper, yang akan hijau walau penyebutnya terpisah
    // dua kartu. Rata-rata tanpa penyebutnya di sebelahnya adalah angka yang
    // tidak dapat ditafsirkan.
    expect(tonnage.find('[data-testid="kd-kpi-net-weight-avg"]').exists()).toBe(true)
    expect(tonnage.find('[data-testid="kd-kpi-net-weight-dispatch-count"]').exists()).toBe(true)

    // Dan keduanya bahkan di dalam KARTU ANGKA yang sama.
    const avgCard = wrapper.get('[data-testid="kd-metric-net-weight-avg"]')

    expect(avgCard.find('[data-testid="kd-kpi-net-weight-dispatch-count"]').exists()).toBe(true)
    expect(rawText(wrapper, 'kd-kpi-net-weight-dispatch-count')).toBe('4')
  })

  // unit_test_case 21
  it("unit_test_case 21: missing_net_weight adalah kartu TERSENDIRI dengan kalimat 'penimbangan belum selesai'", async () => {
    const wrapper = await mountWith(
      makeSummary({
        dispatches: {
          dispatch_count: 30,
          net_weight_total: 1000,
          net_weight_avg: 777,
          net_weight_dispatch_count: 4,
          missing_net_weight_dispatch_count: 3,
        },
      }),
    )

    expect(count(wrapper, 'kd-card-missing-net-weight')).toBe(1)
    expect(rawText(wrapper, 'kd-kpi-missing-net-weight')).toBe('3')

    // KETERKANDUNGAN DIASERSI TERBALIK: kd-card-tonnage TIDAK memuatnya.
    expect(
      wrapper.get('[data-testid="kd-card-tonnage"]').find('[data-testid="kd-card-missing-net-weight"]').exists(),
    ).toBe(false)

    const card = normalize(text(wrapper, 'kd-card-missing-net-weight'))

    expect(card).toContain('penimbangan belum selesai')
    // Berat kosong berarti kendaraan belum kembali ditimbang kosong, BUKAN
    // catatan yang cacat — satu needle per pemanggilan (jebakan #6).
    expect(card).not.toContain('data tidak lengkap')
    expect(card).not.toContain('tidak valid')
  })

  // unit_test_case 22
  it('unit_test_case 22: v-if KARTU MUTU ADA DI TINGKAT UKURAN, BUKAN DI TINGKAT KARTU', async () => {
    const wrapper = await mountWith(
      makeSummary({
        quality: {
          kernel_moisture_percent: measure(6.1, 7.4, 9.9, 5),
          // TEPAT SATU ukuran kosong, tiga lainnya berangka.
          dirt_impurities_percent: unmeasured(),
          ffa_percent: measure(0.9, 1.7, 3.4, 8),
          broken_kernel_percent: measure(11, 14.5, 22, 11),
          _note: NOTE_QUALITY_DENOMINATORS,
        },
      }),
    )

    for (const part of ['min', 'avg', 'max'] as const) {
      expect(rawText(wrapper, `kd-quality-dirt-impurities-${part}`), part).toBe('—')
    }

    expect(rawText(wrapper, 'kd-quality-dirt-impurities-count')).toBe('0')

    /**
     * KESEMBILAN angka ketiga ukuran lain TETAP mencetak nilai payload-nya.
     * Satu `v-if="adaDataMutu"` di pembungkus [data-testid='kd-card-quality']
     * akan MEMERAHKAN test ini — itu kesalahan yang paling mudah dibuat di
     * klien, terasa rapi, dan menyembunyikan tiga ukuran yang punya data
     * karena satu ukuran kosong.
     */
    const stillNumeric: Record<string, string> = {
      'kd-quality-kernel-moisture-min': '6,10',
      'kd-quality-kernel-moisture-avg': '7,40',
      'kd-quality-kernel-moisture-max': '9,90',
      'kd-quality-ffa-min': '0,90',
      'kd-quality-ffa-avg': '1,70',
      'kd-quality-ffa-max': '3,40',
      'kd-quality-broken-kernel-min': '11,00',
      'kd-quality-broken-kernel-avg': '14,50',
      'kd-quality-broken-kernel-max': '22,00',
    }

    for (const [testid, value] of Object.entries(stillNumeric)) {
      expect(rawText(wrapper, testid), testid).toBe(value)
    }

    // Penanda 'belum ada hasil uji' HANYA pada ukuran yang kosong.
    expect(count(wrapper, 'kd-quality-unmeasured-dirt-impurities')).toBe(1)

    for (const slug of ['kernel-moisture', 'ffa', 'broken-kernel'] as const) {
      expect(count(wrapper, `kd-quality-unmeasured-${slug}`), slug).toBe(0)
    }

    // Dan kalimat 'keempatnya kosong' TIDAK dirender — hanya satu yang kosong.
    expect(count(wrapper, 'kd-quality-unmeasured-all')).toBe(0)
  })

  // unit_test_case 23
  it('unit_test_case 23: keempat ukuran mutu kosong tetapi tonase ada — kartu mutu TETAP dirender', async () => {
    const wrapper = await mountWith(
      makeSummary({
        quality: {
          kernel_moisture_percent: unmeasured(),
          dirt_impurities_percent: unmeasured(),
          ffa_percent: unmeasured(),
          broken_kernel_percent: unmeasured(),
          _note: NOTE_QUALITY_DENOMINATORS,
        },
      }),
    )

    // toHaveCount(1), BUKAN 0: kartu yang hilang dari DOM menghapus
    // penjelasannya bersama angkanya.
    expect(count(wrapper, 'kd-card-quality')).toBe(1)
    expect(count(wrapper, 'kd-quality-unmeasured-all')).toBe(1)
    expect(normalize(text(wrapper, 'kd-quality-unmeasured-all'))).toContain(
      'Belum ada satu hasil uji pun',
    )

    for (const slug of QUALITY_SLUGS) {
      for (const part of ['min', 'avg', 'max'] as const) {
        expect(rawText(wrapper, `kd-quality-${slug}-${part}`), `${slug}-${part}`).toBe('—')
      }

      expect(rawText(wrapper, `kd-quality-${slug}-count`), slug).toBe('0')
    }

    // Kartu tonase TETAP mencetak angkanya — kedua kartu independen.
    expect(count(wrapper, 'kd-card-tonnage')).toBe(1)
    expect(rawText(wrapper, 'kd-kpi-dispatch-count')).toBe('30')
    expect(rawText(wrapper, 'kd-kpi-net-weight-total')).toBe('1.000,0')
  })

  // unit_test_case 24
  it('unit_test_case 24: keempat measured_count DIRENDER dan keempatnya BERBEDA — lima asersi angka terpisah', async () => {
    const wrapper = await mountWith(
      makeSummary({
        dispatches: {
          dispatch_count: 40,
          net_weight_total: 1000,
          net_weight_avg: 777,
          net_weight_dispatch_count: 4,
          missing_net_weight_dispatch_count: 36,
        },
        quality: {
          kernel_moisture_percent: measure(6.1, 7.4, 9.9, 31),
          dirt_impurities_percent: measure(0.2, 0.6, 1.8, 24),
          ffa_percent: measure(0.9, 1.7, 3.4, 19),
          broken_kernel_percent: measure(11, 14.5, 22, 7),
          _note: NOTE_QUALITY_DENOMINATORS,
        },
      }),
    )

    /**
     * LIMA ANGKA, TIDAK ADA DUA PUN YANG SAMA, atas lima testid berbeda. Satu
     * penyebut tunggal yang dipakai berempat — atau penyebut yang jatuh ke
     * dispatch_count — akan memerahkan test ini. Inilah pembuktian paling
     * langsung bahwa tiap ukuran punya penyebutnya sendiri.
     */
    const expected: Record<string, string> = {
      'kd-kpi-dispatch-count': '40',
      'kd-quality-kernel-moisture-count': '31',
      'kd-quality-dirt-impurities-count': '24',
      'kd-quality-ffa-count': '19',
      'kd-quality-broken-kernel-count': '7',
    }

    for (const [testid, value] of Object.entries(expected)) {
      expect(rawText(wrapper, testid), testid).toBe(value)
    }

    expect(new Set(Object.values(expected)).size).toBe(5)
  })

  // unit_test_case 25
  it('unit_test_case 25: quality._note dirender UTUH — kesetaraan penuh, bukan contains', async () => {
    const wrapper = await mountFullReport()

    // KESAMAAN PENUH dengan konstanta SERVICE-nya, dibaca dari disk. BUKAN
    // toContain: prosa yang terpotong di tengah tetap meloloskan toContain
    // atas potongan awalnya, dan yang hilang justru bagian yang menjelaskan
    // sebabnya.
    expect(rawText(wrapper, 'kd-quality-note')).toBe(normalize(NOTE_QUALITY_DENOMINATORS))
    // TEPAT SATU elemen, supaya prosa yang terender dua kali juga tertangkap.
    expect(count(wrapper, 'kd-quality-note')).toBe(1)
    // Dan prosanya memang panjang: kesetaraan atas string kosong hijau tanpa
    // menguji apa pun.
    expect(rawText(wrapper, 'kd-quality-note').length).toBeGreaterThan(900)
  })

  // unit_test_case 26
  it('unit_test_case 26: kartu tonase dan kartu mutu BERURUTAN di DOM tanpa bagian lain di antaranya', async () => {
    const wrapper = await mountFullReport()

    const sections = sectionTestids(wrapper)

    /**
     * SELURUH bagian tingkat atas sebagai ARRAY BERURUT dari DOM.
     *
     * PENYIMPANGAN YANG DISENGAJA dari blade web (D1 pada brief): di web
     * "Penimbangan Belum Selesai" duduk sebagai kartu KEEMPAT di antara tonase
     * dan mutu. Di sini ia SESUDAH kartu mutu, karena kedekatan tonase–mutu
     * tidak dapat dipenuhi sekaligus dengan menempatkannya di tengah — dan
     * kedekatan itulah kontrak layar ini pada lebar ponsel. Angka, testid dan
     * kalimatnya sama persis dengan web.
     */
    expect(sections).toEqual([
      'kd-card-tonnage',
      'kd-card-quality',
      'kd-card-missing-net-weight',
      'kd-not-released',
      'kd-recap-transporter',
      'kd-recap-silo-source',
      'kd-recap-destination-buyer',
      'kd-recap-gate-status',
      'kd-compliance',
      'kd-daily-recap',
      'kd-coverage',
      'kd-findings',
      'kd-draft',
      'kd-absences',
    ])

    // NOL bagian di antaranya: indeksnya tepat satu lebih besar.
    expect(sections.indexOf('kd-card-quality')).toBe(sections.indexOf('kd-card-tonnage') + 1)

    // Dan pencarian berprefiks `kd-card` memulangkan TEPAT ketiga wadah
    // tingkat atas — kartu angka di dalamnya berprefiks `kd-metric-`, justru
    // supaya hijau atau merahnya asersi urutan di atas tidak bergantung pada
    // selektor yang dipakai penguji.
    expect(cardTestids(wrapper)).toEqual([
      'kd-card-tonnage',
      'kd-card-quality',
      'kd-card-missing-net-weight',
    ])
  })

  // unit_test_case 27
  it('unit_test_case 27: melipat sebuah rekap TIDAK ikut melipat kartu tonase maupun kartu mutu', async () => {
    const wrapper = await mountFullReport()

    // BAWAANNYA TERBUKA (P3 pada docblock berkas) — sama dengan <details open>
    // di web, dan memang diperlukan test yang membaca baris langsung sesudah
    // mount.
    expect(exists(wrapper, 'kd-recap-transporter-table')).toBe(true)
    expect(exists(wrapper, 'kd-daily-table')).toBe(true)

    await toggleSection(wrapper, 'kd-recap-transporter')
    await toggleSection(wrapper, 'kd-daily-recap')

    // Benar-benar KELUAR dari DOM, bukan sekadar tersembunyi.
    expect(count(wrapper, 'kd-recap-transporter-table')).toBe(0)
    expect(count(wrapper, 'kd-daily-table')).toBe(0)

    // SEMENTARA kedua kartu angka tetap hadir DAN angkanya tetap terbaca.
    expect(count(wrapper, 'kd-card-tonnage')).toBe(1)
    expect(count(wrapper, 'kd-card-quality')).toBe(1)
    expect(rawText(wrapper, 'kd-kpi-dispatch-count')).toBe('30')
    expect(rawText(wrapper, 'kd-quality-kernel-moisture-avg')).toBe('3,00')

    // DAN SEBALIKNYA: nol kontrol lipat DI DALAM kedua kartu itu. Rekap boleh
    // dilipat; angka utama tidak.
    for (const card of ['kd-card-tonnage', 'kd-card-quality'] as const) {
      expect(
        wrapper.get(`[data-testid="${card}"]`).findAll('[data-testid$="-toggle"]'),
        card,
      ).toHaveLength(0)
    }

    // Rekap yang TIDAK dilipat tidak ikut terpengaruh.
    expect(exists(wrapper, 'kd-recap-gate-status-table')).toBe(true)
  })
})

/* ================================================================== */
/* unit_test_case 28-32 — STATUS GERBANG                                */
/* ================================================================== */

describe('label status gerbang datang dari kosakata bersama', () => {
  // unit_test_case 28
  it('unit_test_case 28: label gate status dari optionLabel() — released, not_released, nilai tak dikenal, dan null', async () => {
    const wrapper = await mountWith(
      makeSummary({
        by_gate_status: [
          row('released', 12, 400, 2, 200),
          row('not_released', 5, 120, 1, 120),
          // DI LUAR kedua pilihan yang dikenal.
          row('HOLD_BY_SECURITY', 3, 90, 1, 90),
          row(null, 10, 390, 1, 390),
        ],
      }),
    )

    /**
     * KESETARAAN PENUH PER SEL, bukan contains.
     *
     * KOREKSI 2026-10-18 yang dicatat case-nya sendiri: versi pertamanya
     * mengandaikan view punya 'satu tabel pemetaan' sendiri. Mobile sudah
     * punya cermin kosakata bersama di src/utils/optionLabel.ts, dijaga
     * tests/optionLabel.spec.ts yang MEMBACA Display.php dari disk dan
     * menuntut kedua peta identik. View MENDELEGASIKAN ke sana — lihat
     * unit_test_case 29.
     */
    expect(rawText(wrapper, 'kd-recap-gate-status-value-0')).toBe('Released')
    expect(rawText(wrapper, 'kd-recap-gate-status-value-1')).toBe('Not Released')
    // Helper jatuh ke NILAI MENTAH untuk yang tak dikenal, persis seperti
    // Display::option() di server.
    expect(rawText(wrapper, 'kd-recap-gate-status-value-2')).toBe('HOLD_BY_SECURITY')
    expect(rawText(wrapper, 'kd-recap-gate-status-value-3')).toBe('tidak tercatat')

    // Dan kalimat di luar tabel memakai SUMBER YANG SAMA.
    expect(normalize(text(wrapper, 'kd-not-released'))).toContain('Not Released')
  })

  // unit_test_case 29
  it('unit_test_case 29: AUDIT SUMBER — view TIDAK punya peta label gate status sendiri', () => {
    /**
     * KOMENTARNYA DILUCUTI LEBIH DULU, dan pelucutan itu wajib: docblock layar
     * ini memang menjelaskan dari mana kedua label datang, dan audit tanpa
     * pelucutan akan memerah atas prosanya sendiri.
     */
    const code = stripComments(readSource(VIEW_PATH))

    /**
     * LITERAL LABELNYA NOL KALI — disasar sebagai literal BER-KUTIP, bukan
     * sebagai sapuan kata: `'Released'` telanjang juga cocok dengan
     * pengidentifikasi `notReleasedCount` (dua kemunculan, keduanya BENAR),
     * dan audit yang menuduh keduanya memerah atas kode yang justru
     * mendelegasikan.
     */
    expect(code).not.toMatch(/['"]Released['"]/)
    expect(code).not.toMatch(/['"]Not Released['"]/)
    // Label berspasi itu tidak mungkin lahir dari sebuah pengidentifikasi,
    // jadi di sini sapuan telanjang memang berlaku.
    expect(code).not.toContain('Not Released')

    // Dan tidak ada objek pemetaan nilai→label untuk gate status.
    expect(code).not.toMatch(/['"]?released['"]?\s*:/)
    expect(code).not.toMatch(/['"]?not_released['"]?\s*:/)

    // Sebaliknya: helper bersama DIIMPOR dan DIPANGGIL pada nilai gate status.
    expect(code).toContain("import { optionLabel } from '@/utils/optionLabel'")
    expect(code).toContain('optionLabel(value)')

    /**
     * ALASANNYA: aturan rumah 'delegasikan, jangan salin' berlaku di sisi
     * mobile juga. Peta kedua di dalam sebuah komponen dapat menyimpang dari
     * backend tanpa ada yang membandingkannya — penjaga yang membandingkan
     * hanya melihat `OPTION_LABELS`, bukan peta liar di dalam komponen. Dan
     * kedua nilai itu SUDAH ada di sana sejak 2026-10-18, jadi tidak ada entri
     * baru yang ditambahkan untuk layar ini.
     */
    const vocabulary = readSource('src/utils/optionLabel.ts')

    expect(vocabulary).toContain("released: 'Released'")
    expect(vocabulary).toContain("not_released: 'Not Released'")
  })

  // unit_test_case 30
  it('unit_test_case 30: nilai gate status tak dikenal punya BARISNYA SENDIRI dan penjumlahan rekap tetap = dispatch_count', async () => {
    const wrapper = await mountWith(
      makeSummary({
        by_gate_status: [
          row('released', 20, 700, 3, 233),
          row('not_released', 5, 120, 1, 120),
          row('held_by_security', 3, 90, 1, 90),
          row(null, 2, 40, 1, 40),
        ],
      }),
    )

    /**
     * TEPAT EMPAT BARIS — nilai tak dikenal tidak dibuang.
     *
     * P2 pada docblock berkas: baris berprefiks `-row-` dan sel berprefiks
     * `-value-` / `-dispatch-count-`, jadi pencarian baris ini memulangkan
     * empat, bukan delapan.
     */
    expect(recapRowCount(wrapper, 'kd-recap-gate-status')).toBe(4)

    // Nilai tak dikenal APA ADANYA — tidak dipaksa masuk salah satu pilihan.
    expect(rawText(wrapper, 'kd-recap-gate-status-value-2')).toBe('held_by_security')
    // Baris null TETAP baris TERSENDIRI: ia TERCATAT, hanya tidak dikenal
    // bukanlah hal yang sama dengan tidak tercatat.
    expect(rawText(wrapper, 'kd-recap-gate-status-value-3')).toBe('tidak tercatat')
    expect(rawText(wrapper, 'kd-recap-gate-status-dispatch-count-3')).toBe('2')

    // PENJUMLAHAN KEEMPAT SEL YANG DIBACA DARI DOM — klien yang 'merapikan'
    // dengan membuang nilai tak dikenal akan memecah penjumlahan ini, dan
    // penjumlahan inilah yang menangkapnya.
    const cells = recapDispatchCounts(wrapper, 'kd-recap-gate-status')

    expect(cells).toHaveLength(4)
    expect(cells.reduce((a, b) => a + b, 0)).toBe(30)
    expect(idNumber(rawText(wrapper, 'kd-kpi-dispatch-count'))).toBe(30)
  })

  // unit_test_case 31
  it('unit_test_case 31: not_released_count HANYA DITAMPILKAN, tidak dihitung ulang dari rekap', async () => {
    /**
     * Fixture ini SENGAJA tidak konsisten, dan baris null-nya SENGAJA
     * berjumlah 4 sehingga 5 + 4 = 9 juga: angka yang SAMA dari rumus yang
     * SALAH. Pembedanya ada di unit_test_case 32, yang memakai fixture
     * berbeda.
     */
    const wrapper = await mountWith(
      makeSummary({
        not_released_count: 9,
        by_gate_status: [
          row('released', 21, 700, 3, 233),
          row('not_released', 5, 120, 1, 120),
          row(null, 4, 80, 1, 80),
        ],
      }),
    )

    expect(rawText(wrapper, 'kd-kpi-not-released')).toBe('9')
    // Klien yang menghitungnya ulang dari rekap akan mencetak '5'.
    expect(rawText(wrapper, 'kd-kpi-not-released')).not.toBe('5')
  })

  // unit_test_case 32
  it("unit_test_case 32: 'tidak tercatat' TIDAK ikut not_released_count — fixture pembeda", async () => {
    /**
     * FIXTURE PEMBEDA: not_released_count = 5 sementara baris 'not_released'
     * berjumlah 5 DAN baris null berjumlah 4. Rumus yang salah (5 + 4)
     * mencetak '9'; yang benar mencetak '5'.
     *
     * P2 pada docblock berkas: huruf case-nya menyebut sel
     * `kd-recap-gate-status-row-3-count`, nama yang akan membuat pencarian
     * baris pada unit_test_case 30 menemukan delapan elemen. Yang disasar di
     * sini adalah `kd-recap-gate-status-dispatch-count-3` — konvensi repo —
     * dan baris null karena itu ditaruh di INDEKS 3, sesuai indeks yang
     * disebut case-nya; kedua baris yang ia namai ('not_released' 5 dan null
     * 4) tetap ada apa adanya.
     */
    const wrapper = await mountWith(
      makeSummary({
        dispatches: {
          dispatch_count: 32,
          net_weight_total: 1000,
          net_weight_avg: 777,
          net_weight_dispatch_count: 4,
          missing_net_weight_dispatch_count: 28,
        },
        not_released_count: 5,
        by_gate_status: [
          row('released', 20, 700, 3, 233),
          row('not_released', 5, 120, 1, 120),
          row('held_by_security', 3, 90, 1, 90),
          row(null, 4, 80, 1, 80),
        ],
      }),
    )

    expect(rawText(wrapper, 'kd-kpi-not-released')).toBe('5')
    expect(rawText(wrapper, 'kd-kpi-not-released')).not.toBe('9')

    // DUA TESTID BERBEDA, DIASERSI TERPISAH: menggabungkan keduanya akan
    // MENUDUH pengiriman yang sekadar belum diisi statusnya.
    expect(rawText(wrapper, 'kd-recap-gate-status-value-3')).toBe('tidak tercatat')
    expect(rawText(wrapper, 'kd-recap-gate-status-dispatch-count-3')).toBe('4')

    // Dan layar MENYATAKAN pembedaan itu di kartunya sendiri.
    expect(normalize(text(wrapper, 'kd-not-released'))).toContain('tidak tercatat')
  })
})

/* ================================================================== */
/* unit_test_case 33-37 — KEEMPAT REKAP KATEGORIS                       */
/* ================================================================== */

describe('keempat rekap kategoris', () => {
  // unit_test_case 33
  it("unit_test_case 33: baris value null dicetak 'tidak tercatat' pada KEEMPAT rekap", async () => {
    const wrapper = await mountFullReport()

    // EMPAT ASERSI atas empat testid rekap yang berbeda — bukan satu asersi
    // atas teks halaman.
    const nullIndex: Record<string, number> = {
      'kd-recap-transporter': 1,
      'kd-recap-silo-source': 1,
      'kd-recap-destination-buyer': 0,
      'kd-recap-gate-status': 2,
    }

    for (const [testid, index] of Object.entries(nullIndex)) {
      const cell = rawText(wrapper, `${testid}-value-${index}`)

      expect(cell, testid).toBe('tidak tercatat')

      for (const wrong of ['null', 'undefined', '-', '']) {
        expect(cell, `${testid} / ${wrong}`).not.toBe(wrong)
      }
    }

    // TEPAT SATU baris 'tidak tercatat' per rekap.
    for (const testid of RECAP_TESTIDS) {
      expect(
        recapValues(wrapper, testid).filter((value) => value === 'tidak tercatat'),
        testid,
      ).toHaveLength(1)
    }
  })

  // unit_test_case 34
  it('unit_test_case 34: penjumlahan dispatch_count tiap rekap = dispatches.dispatch_count — keempat rekap', async () => {
    // Fixture KONSISTEN — berbeda dari fixture unit_test_case 15. Yang diuji
    // di sini KONTRAK SERVER, bukan aritmetika klien, dan ia diuji DARI DOM
    // supaya baris yang tidak terender ikut tertangkap.
    const wrapper = await mountFullReport()

    for (const testid of RECAP_TESTIDS) {
      const cells = recapDispatchCounts(wrapper, testid)

      expect(cells.length, testid).toBeGreaterThan(0)
      expect(cells.reduce((a, b) => a + b, 0), testid).toBe(30)
    }

    expect(idNumber(rawText(wrapper, 'kd-kpi-dispatch-count'))).toBe(30)
  })

  // unit_test_case 35
  it('unit_test_case 35: URUTAN DOM keempat rekap SAMA PERSIS dengan urutan payload', async () => {
    const wrapper = await mountFullReport()

    /**
     * toEqual atas ARRAY BERURUT — bukan atas himpunan dan bukan atas
     * panjangnya. Klien yang menyortir alfabetis, menyortir turun menurut
     * jumlah, atau memindahkan baris null ke bawah akan memerahkannya.
     *
     * KONSEKUENSI YANG DISENGAJA: kelompok 'tidak tercatat' bertonase besar
     * MUNCUL DI ATAS, bukan terkubur di baris terakhir tempat tak seorang pun
     * membacanya.
     */
    expect(recapValues(wrapper, 'kd-recap-transporter')).toEqual([
      'Zulkifli Trans',
      'tidak tercatat',
      'Amanah Logistik',
      'Bintang Mas',
    ])
    expect(recapValues(wrapper, 'kd-recap-silo-source')).toEqual([
      'Silo 3 Utara',
      'tidak tercatat',
      'SL-02',
    ])
    expect(recapValues(wrapper, 'kd-recap-destination-buyer')).toEqual([
      'tidak tercatat',
      'PT Sawit Jaya',
      'Kebun Blok C',
    ])
    expect(recapValues(wrapper, 'kd-recap-gate-status')).toEqual([
      'Released',
      'Not Released',
      'tidak tercatat',
      'held_by_security',
    ])

    // Dan tidak satu pun urutan itu alfabetis atau urut jumlah — fixture yang
    // kebetulan terurut tidak dapat membuktikan apa pun.
    const transporter = recapValues(wrapper, 'kd-recap-transporter')

    expect(transporter).not.toEqual([...transporter].sort())
  })

  // unit_test_case 36
  it("unit_test_case 36: nilai yang hanya berbeda besar-kecil huruf atau spasi tepi tampil dua baris; nol 'lainnya'", async () => {
    const wrapper = await mountWith(
      makeSummary({
        by_destination_buyer: [
          row('PT Sawit Jaya', 12, 400, 2, 200),
          row('pt sawit jaya', 10, 300, 1, 300),
          row(' PT Sawit Jaya', 7, 200, 1, 200),
          // Nilai berfrekuensi SATU tetap punya barisnya sendiri.
          row('Koperasi Tani Maju', 1, 20, 1, 20),
        ],
      }),
    )

    expect(recapRowCount(wrapper, 'kd-recap-destination-buyer')).toBe(4)

    /**
     * DIBACA APA ADANYA, TANPA trim: ' PT Sawit Jaya' dan 'PT Sawit Jaya'
     * adalah dua nilai server yang berbeda, dan asersi yang ikut mem-trim
     * akan menyatakan keduanya sama — tepat kesalahan yang hendak dicegah.
     */
    expect([0, 1, 2, 3].map((index) => verbatimText(wrapper, `kd-recap-destination-buyer-value-${index}`))).toEqual([
      'PT Sawit Jaya',
      'pt sawit jaya',
      ' PT Sawit Jaya',
      'Koperasi Tani Maju',
    ])

    // NOL elemen 'lainnya' pada KEEMPAT rekap — klien tidak melipat ekor.
    for (const testid of RECAP_TESTIDS) {
      for (const value of recapValues(wrapper, testid)) {
        expect(value.toLowerCase(), `${testid} / ${value}`).not.toBe('lainnya')
        expect(value.toLowerCase(), `${testid} / ${value}`).not.toBe('others')
      }
    }
  })

  // unit_test_case 37
  it("unit_test_case 37: silo sumber diperlakukan sebagai TEKS — nol pencarian nama meski kuncinya berakhiran '_id'", async () => {
    const wrapper = await mountWith(
      makeSummary({
        by_silo_source: [
          row('Silo 3 Utara', 18, 600, 2, 300),
          // BERBENTUK seperti sebuah id, dan tetap teks.
          row('SL-02', 12, 400, 2, 200),
        ],
      }),
    )

    expect(rawText(wrapper, 'kd-recap-silo-source-value-0')).toBe('Silo 3 Utara')
    expect(rawText(wrapper, 'kd-recap-silo-source-value-1')).toBe('SL-02')

    /**
     * NOL PERMINTAAN SESUDAH /summary: nol permintaan master, nol pencarian
     * nama. Tidak ada daftar induk silo di sistem ini — nama kolomnya
     * menyesatkan, dan satu-satunya pencegahnya adalah test yang MENGHITUNG
     * permintaan.
     */
    expect(requests).toEqual([PERIODS_PATH, LINES_PATH, SUMMARY_PATH])
    expect(requests.slice(requests.lastIndexOf(SUMMARY_PATH) + 1)).toEqual([])
    expect(hits(SUMMARY_PATH)).toBe(1)
    expect(apiClientMocks.get).not.toHaveBeenCalled()

    // Dan layar MENYATAKAN sebabnya, supaya pembacanya tidak menyangka nama
    // silonya gagal dimuat.
    expect(normalize(text(wrapper, 'kd-recap-silo-source-note'))).toContain(
      'Nama kolomnya menyesatkan',
    )
  })
})

/* ================================================================== */
/* unit_test_case 38-41 — KEPATUHAN, PROSA SERVER, DAN KETIADAAN        */
/* ================================================================== */

describe('kepatuhan, prosa server, dan ketiadaan yang dinyatakan', () => {
  /** Teks TEPAT sebuah elemen, untuk asersi "nol elemen bernilai X". */
  function elementsPrinting(wrapper: VueWrapper, value: string): string[] {
    return wrapper
      .findAll('*')
      .map((el) => normalize(el.element.textContent ?? ''))
      .filter((content) => content === value)
  }

  // unit_test_case 38
  it("unit_test_case 38: TIGA penghitung kepatuhan sebagai TIGA angka, dan NOL elemen gabungan 'segel hilang'", async () => {
    // 6 + 2 = 8, dan 8 tidak sama dengan satu pun di antaranya — itulah yang
    // membuat asersi "nol elemen mencetak 8" punya arti.
    const wrapper = await mountFullReport()

    expect(rawText(wrapper, 'kd-compliance-missing-waybill')).toBe('4')
    expect(rawText(wrapper, 'kd-compliance-missing-seal-top')).toBe('6')
    expect(rawText(wrapper, 'kd-compliance-missing-seal-bottom')).toBe('2')

    // TESTID GABUNGAN yang dipakai screen-163 untuk stasiun lain TIDAK boleh
    // ada di sini.
    expect(count(wrapper, 'kd-compliance-missing-seal')).toBe(0)
    /**
     * Dan tidak satu elemen pun mencetak jumlah kedua segel. Satu kendaraan
     * dengan satu segel hilang berbeda keadaannya dari satu kendaraan tanpa
     * kedua segel, dan menjumlahkannya menghapus pembedaan itu.
     *
     * PEMINDAINYA DIBUKTIKAN BEKERJA LEBIH DULU: sebuah pemindai yang keliru
     * selektornya memulangkan daftar kosong untuk SETIAP nilai, dan asersi
     * "nol elemen mencetak 8" di atasnya hijau tanpa menguji apa pun.
     */
    expect(elementsPrinting(wrapper, '4').length).toBeGreaterThan(0)
    expect(elementsPrinting(wrapper, '6').length).toBeGreaterThan(0)
    expect(elementsPrinting(wrapper, '8')).toEqual([])

    // Prosa servernya MENYATAKAN hal yang sama, dan ia dirender utuh.
    expect(rawText(wrapper, 'kd-compliance-note')).toContain(
      'SEGEL ATAS DAN SEGEL BAWAH DIHITUNG TERPISAH',
    )
  })

  // unit_test_case 39
  it('unit_test_case 39: keempat prosa server dirender UTUH — kesetaraan penuh, bukan toContain', async () => {
    const wrapper = await mountFullReport()

    /**
     * KESETARAAN PENUH terhadap konstanta SERVICE-nya, dibaca dari
     * KernelDispatchReportService.php saat test berjalan. Kalimat-kalimat itu
     * disusun SERVER supaya penjelasan yang sama muncul di web dan mobile;
     * yang diringkas di klien memecah janji itu tanpa terlihat.
     */
    const prose: Record<string, string> = {
      'kd-compliance-note': NOTE_COMPLIANCE,
      'kd-note-no-hourly': NOTE_NO_HOURLY,
      'kd-note-no-quality-thresholds': NOTE_NO_QUALITY_THRESHOLDS,
      'kd-note-shift': NOTE_SHIFT_NOT_PUBLISHED,
    }

    for (const [testid, expected] of Object.entries(prose)) {
      expect(rawText(wrapper, testid), testid).toBe(normalize(expected))
      // TEPAT SATU elemen masing-masing, supaya prosa yang terender dua kali
      // juga tertangkap.
      expect(count(wrapper, testid), testid).toBe(1)
      expect(rawText(wrapper, testid).length, testid).toBeGreaterThan(700)
    }

    /**
     * DAN INILAH SEBAB SAPUAN TEKS DILARANG, dinyatakan sebagai asersi POSITIF
     * alih-alih diserahkan pada ingatan: prosa yang MENYATAKAN ketiadaan blok
     * per jam memuat kata 'jam' DAN menyebut CPO Dispatch, dan layar
     * merendernya utuh.
     */
    expect(rawText(wrapper, 'kd-note-no-hourly')).toContain('jam')
    expect(rawText(wrapper, 'kd-note-no-hourly')).toContain('CPO Dispatch')
    expect(rawText(wrapper, 'kd-note-no-quality-thresholds')).toContain('batas')
  })

  // unit_test_case 40
  it('unit_test_case 40: NOL elemen sebaran per jam dan waktu putar — lewat TESTID dan NAMA KUNCI', async () => {
    const payload = makeSummary()
    const wrapper = await mountWith(payload)

    for (const absent of ['kd-hourly', 'kd-hourly-distribution', 'kd-peak-hour', 'kd-turnaround']) {
      expect(count(wrapper, absent), absent).toBe(0)
    }

    /**
     * ASERSI ATAS NAMA KUNCI PAYLOAD, bukan atas nilainya.
     *
     * KOREKSI ATAS HURUF CASE-nya: ia menuntut TIDAK ADA kunci yang cocok
     * /hour|hourly|turnaround|peak/. Kunci `_note_no_hourly` MEMUAT 'hourly'
     * dan ia justru kunci yang MENYATAKAN ketiadaannya — asersi apa adanya
     * akan memerah pada payload yang benar. Yang diasersi karena itu: di luar
     * kelima kunci prosa, NOL kunci cocok; dan satu-satunya yang cocok adalah
     * `_note_no_hourly` itu sendiri.
     */
    const keys = Object.keys(payload)
    const matching = keys.filter((key) => /hour|turnaround|peak/i.test(key))

    expect(matching).toEqual(['_note_no_hourly'])
    expect(keys.filter((key) => !key.startsWith('_note') && /hour|turnaround|peak/i.test(key))).toEqual(
      [],
    )

    // SAPUAN TEKS DILARANG DI SINI, dan ini buktinya: prosa yang dirender
    // justru MEMUAT kata-kata itu.
    expect(rawText(wrapper, 'kd-note-no-hourly')).toContain('waktu putar kendaraan')
  })

  // unit_test_case 41
  it('unit_test_case 41: NOL kontrol Excel/xlsx — lewat TESTID dan ROLE', async () => {
    const wrapper = await mountFullReport()

    expect(count(wrapper, 'kd-export-xlsx')).toBe(0)
    expect(count(wrapper, 'kd-export-csv')).toBe(1)

    /**
     * DIPERIKSA PER ELEMEN BER-ROLE BUTTON, bukan atas teks halaman: prosa
     * lingkup ekspor menyebut format berkasnya, jadi sapuan halaman akan
     * memerah atas kalimat yang justru menerangkan ketiadaannya.
     */
    const names = buttonNames(wrapper)

    expect(names.length).toBeGreaterThan(0)

    for (const name of names) {
      expect(name, `tombol: ${name}`).not.toMatch(/excel|xlsx/i)
    }

    // Dan nilai 'xlsx' tidak dapat disusun dari jalur kode mana pun.
    const sources = `${stripComments(scriptBlockOf(readSource(VIEW_PATH)))}\n${stripComments(readSource(REPO_PATH))}`

    expect(sources).not.toContain("'xlsx'")
  })
})

/* ================================================================== */
/* unit_test_case 42-46 — UNDATED, DRAFT, HARIAN, TEMUAN                */
/* ================================================================== */

describe('undated, draft, rekap harian dan temuan', () => {
  // unit_test_case 42
  it('unit_test_case 42: undated_dispatch_count = 0 → blok TIDAK dirender', async () => {
    const wrapper = await mountFullReport()

    // BUKAN hadir bernilai '0'. Kolom tanggal kejadian NOT NULL di basis
    // data, jadi angkanya selalu 0 dalam praktik, dan blok yang selalu tampil
    // bernilai nol mengarang kekhawatiran atas keadaan yang dilarang skema.
    expect(count(wrapper, 'kd-undated-dispatch-count')).toBe(0)
    expect(count(wrapper, 'kd-undated-note')).toBe(0)
  })

  // unit_test_case 43
  it('unit_test_case 43: undated_dispatch_count > 0 → blok DIRENDER', async () => {
    // TEST TERPISAH, bukan satu test berparameter: v-if yang terbalik akan
    // meloloskan salah satu arah saja, dan arah yang tidak diuji adalah arah
    // yang rusak di produksi.
    const wrapper = await mountWith(makeSummary({ undated_dispatch_count: 2 }))

    expect(count(wrapper, 'kd-undated-dispatch-count')).toBe(1)
    expect(rawText(wrapper, 'kd-undated-dispatch-count')).toBe('2')
    expect(exists(wrapper, 'kd-undated-note')).toBe(true)
    expect(normalize(text(wrapper, 'kd-undated-note'))).toContain('tidak punya tanggal kejadian')
  })

  // unit_test_case 44
  it('unit_test_case 44: draft_dispatch_count dinyatakan tersendiri dan pengiriman draft IKUT terhitung', async () => {
    const wrapper = await mountFullReport()

    expect(rawText(wrapper, 'kd-kpi-dispatch-count')).toBe('30')
    expect(rawText(wrapper, 'kd-draft-dispatch-count')).toBe('11')

    // Pembaca DIBERI TAHU, bukan dilindungi dari mengetahuinya.
    expect(normalize(text(wrapper, 'kd-draft-note'))).toContain('IKUT terhitung')

    /**
     * 11 TERMASUK DI DALAM 30, bukan di luarnya — dan ketiadaan angka turunan
     * 30 − 11 DIASERSI LEWAT TESTID DAN NAMA KUNCI, BUKAN LEWAT SAPUAN '19'.
     *
     * Sebabnya konkret dan ada di fixture ini sendiri: '19' adalah penyebut
     * ukuran ALB (`ffa_percent.measured_count`), dan layar yang BENAR
     * mencetaknya. Sapuan teks atas '19' karena itu akan memerah atas angka
     * yang justru wajib ada — bentuk kegagalan yang sama dengan sapuan 'jam'
     * pada unit_test_case 40.
     *
     * Hal yang sama berlaku untuk kartu "Status Gerbang Tercatat" milik blade
     * web, yang nilainya ia HITUNG SENDIRI (dispatch_count − baris null): itu
     * aritmetika klien dan business_rule 1 melarangnya, jadi kartu itu TIDAK
     * diport sama sekali — angkanya tetap terbaca dari baris 'tidak tercatat'
     * pada rekap gate status, yang memang dirender.
     */
    expect(rawText(wrapper, 'kd-quality-ffa-count')).toBe('19')

    for (const derived of [
      'kd-non-draft-dispatch-count',
      'kd-final-dispatch-count',
      'kd-gate-status-recorded',
      'kd-metric-gate-status-recorded',
      'kd-recorded-gate-status-count',
    ]) {
      expect(count(wrapper, derived), derived).toBe(0)
    }

    const keys = Object.keys(makeSummary())

    expect(keys.filter((key) => /non_draft|final_|recorded|excluding/i.test(key))).toEqual([])

    // Dan tidak ada satu pengurangan pun atas angka laporan di sumber view.
    const code = stripComments(scriptBlockOf(readSource(VIEW_PATH)))

    expect(code).not.toMatch(/dispatch_count\s*-\s*/)
    expect(code).not.toMatch(/draftDispatchCount[^)]*[-+]/)
  })

  // unit_test_case 45
  it('unit_test_case 45: daily[] satu baris per hari BERPENGIRIMAN — nol baris nol, urutan DOM = urutan payload', async () => {
    /**
     * SEMBILAN entri di dalam periode 30 hari, urutannya SENGAJA bukan urut
     * tanggal naik: klien yang mengurutkan ulang rekap harian tertangkap di
     * sini.
     */
    const order = [4, 0, 7, 2, 8, 1, 6, 3, 5].map((index) => DISPATCH_DAYS[index])
    const nine = order.map((date, index) => ({
      date,
      dispatch_count: index + 1,
      net_weight_total: index % 3 === 1 ? null : 10 + index,
      net_weight_dispatch_count: index % 3 === 1 ? 0 : 1,
      net_weight_avg: index % 3 === 1 ? null : 10 + index,
    }))

    const wrapper = await mountWith(makeSummary({ daily: nine }))

    // NOL baris nol untuk 21 hari lainnya.
    expect(wrapper.findAll('[data-testid^="kd-daily-row-"]')).toHaveLength(9)
    // ARRAY BERURUT, bukan himpunan.
    expect(dailyDates(wrapper)).toEqual(order)

    // Tiap baris mencetak penyebut nettonya SENDIRI.
    for (const [index, entry] of nine.entries()) {
      expect(
        rawText(wrapper, `kd-daily-net-weight-dispatch-count-${entry.date}`),
        `${entry.date} / penyebut`,
      ).toBe(String(entry.net_weight_dispatch_count))
      expect(rawText(wrapper, `kd-daily-dispatch-count-${entry.date}`), entry.date).toBe(
        String(index + 1),
      )
    }

    // Baris total DIBACA dari payload — bukan hasil penjumlahan kolom (yang
    // berjumlah 45 pengiriman, bukan 88).
    expect(rawText(wrapper, 'kd-daily-total-dispatch-count')).toBe('88')
    expect(rawText(wrapper, 'kd-daily-total-net-weight-total')).toBe('999,0')
    expect(rawText(wrapper, 'kd-daily-total-net-weight-dispatch-count')).toBe('3')
    expect(rawText(wrapper, 'kd-daily-total-net-weight-avg')).toBe('33,0')
    expect(nine.reduce((sum, entry) => sum + entry.dispatch_count, 0)).toBe(45)
  })

  // unit_test_case 46
  it('unit_test_case 46: temuan adalah SATU-SATUNYA daftar kejadian, dan line tanpa pengiriman tetap merender kerangka', async () => {
    // (a) DUA entri bertanggal.
    const withFindings = await mountFullReport()

    expect(count(withFindings, 'kd-findings')).toBe(1)
    expect(withFindings.findAll('[data-testid^="kd-findings-row-"]')).toHaveLength(2)
    expect(withFindings.get('[data-testid="kd-findings-row-0"]').attributes('data-date')).toBe(
      DISPATCH_DAYS[0],
    )
    expect(normalize(text(withFindings, 'kd-findings-row-1'))).toContain('Inti basah pada muatan')

    // NOL BAGIAN KEDUA: stasiun ini hanya punya satu kolom teks bebas,
    // berbeda dari Solid Waste Disposal yang punya dua.
    expect(count(withFindings, 'kd-remarks')).toBe(0)
    expect(count(withFindings, 'kd-findings-empty')).toBe(0)

    withFindings.unmount()
    mountedWrappers.pop()

    // (b) Periode SAH tanpa satu pengiriman pun pada line terpilih.
    const empty = await mountWith(emptyPeriodSummary())

    for (const testid of [
      'kd-card-tonnage',
      'kd-card-quality',
      ...RECAP_TESTIDS,
      'kd-daily-recap',
      'kd-findings',
    ]) {
      expect(count(empty, testid), testid).toBe(1)
    }

    // Kerangkanya kosong, bukan hilang — dan daftar temuan menyatakan
    // ketiadaannya alih-alih menjadi daftar kosong tanpa penjelasan.
    expect(count(empty, 'kd-findings-empty')).toBe(1)
    expect(rawText(empty, 'kd-kpi-dispatch-count')).toBe('0')

    for (const testid of RECAP_TESTIDS) {
      expect(count(empty, `${testid}-empty`), testid).toBe(1)
    }

    expect(count(empty, 'kd-daily-empty')).toBe(1)

    // Catatan kosongnya MENYEBUT nama periode DAN nama line — diasersi pada
    // ELEMEN CATATAN ITU, bukan pada halaman.
    const note = normalize(text(empty, 'kd-empty-period-note'))

    expect(note).toContain(PERIOD.name)
    expect(note).toContain(LINE_3.name)
  })
})

/* ================================================================== */
/* unit_test_case 47-49 — LURING DINYATAKAN, BUKAN DITUTUPI ANGKA BASI  */
/* ================================================================== */

describe('luring DINYATAKAN, bukan ditutupi angka basi', () => {
  // unit_test_case 47
  it('unit_test_case 47: luring saat layar dibuka — bootstrap berhenti SEBELUM permintaan pertama', async () => {
    fakeOnLine(false)
    // Ingatan berisi line yang SAH, supaya bootstrap akan berlanjut
    // seandainya penjagaan luringnya tidak ada.
    window.localStorage.setItem(REMEMBERED_LINE_KEY, LINE_3.id)

    const wrapper = await mountView()

    // KELIMA JALUR, DIHITUNG SATU PER SATU — bukan diasumsikan.
    for (const path of EVERY_PATH) {
      expect(hits(path), path).toBe(0)
    }

    expect(requests).toEqual([])

    expect(exists(wrapper, 'offline-notice')).toBe(true)

    const notice = normalize(text(wrapper, 'offline-notice'))

    expect(notice).toContain('memerlukan jaringan')
    expect(notice).toContain('tidak menampilkan satu angka pun dari pemuatan sebelumnya')

    /**
     * KARTU MUTU DIASERSI TERPISAH dari kartu tonase: keduanya dirender oleh
     * cabang yang berbeda di template, dan satu penjaga luring yang hanya
     * menutup salah satunya akan meloloskan separuh angka.
     */
    for (const testid of [
      'kd-card-tonnage',
      'kd-card-quality',
      ...RECAP_TESTIDS,
      'kd-daily-recap',
    ]) {
      expect(count(wrapper, testid), testid).toBe(0)
    }

    expect(kpiElements(wrapper)).toHaveLength(0)
    // Layarnya tidak diam: kerangkanya tetap terender.
    expect(exists(wrapper, 'laporan-kernel-dispatch-mobile')).toBe(true)
  })

  // unit_test_case 48
  it("unit_test_case 48: event 'offline' SESUDAH angka termuat menutup angkanya", async () => {
    const wrapper = await mountFullReport()

    // DIBUKTIKAN DULU bahwa angkanya memang sudah tampil.
    expect(count(wrapper, 'kd-card-tonnage')).toBe(1)
    expect(count(wrapper, 'kd-card-quality')).toBe(1)

    const before = requests.length

    fakeOnLine(false)
    window.dispatchEvent(new Event('offline'))
    await flushPromises()

    // Angka yang sudah tampil DITUTUP, bukan dibiarkan basi dengan spanduk di
    // atasnya — angka basi tanpa penanda adalah angka yang akan dikutip di
    // rapat.
    for (const testid of [
      'kd-card-tonnage',
      'kd-card-quality',
      ...RECAP_TESTIDS,
      'kd-daily-recap',
    ]) {
      expect(count(wrapper, testid), testid).toBe(0)
    }

    expect(exists(wrapper, 'offline-notice')).toBe(true)
    // Dan NOL permintaan tambahan dipicu olehnya.
    expect(requests.length).toBe(before)
  })

  // unit_test_case 49
  it('unit_test_case 49: listener online/offline DILEPAS saat unmount, dengan referensi fungsi yang SAMA', async () => {
    const addSpy = vi.spyOn(window, 'addEventListener')
    const removeSpy = vi.spyOn(window, 'removeEventListener')

    const wrapper = mount(LaporanKernelDispatchView)

    await flushPromises()
    wrapper.unmount()

    for (const event of ['online', 'offline'] as const) {
      const added = addSpy.mock.calls.filter((call) => call[0] === event).map((call) => call[1])
      const removed = removeSpy.mock.calls
        .filter((call) => call[0] === event)
        .map((call) => call[1])

      expect(added, event).toHaveLength(1)
      expect(removed, event).toHaveLength(1)
      // IDENTITAS FUNGSINYA, bukan hanya jumlah pemanggilan: handler yang
      // dibuat ulang saat pelepasan akan meloloskan hitungan dan tetap
      // membocorkan listener.
      expect(removed[0], event).toBe(added[0])
    }

    // CATATAN BERKAS: setiap wrapper WAJIB di-unmount pada afterEach —
    // wrapper yang bocor menghitung GANDA permintaan bootstrap pada test
    // berikutnya, dan kegagalan itu akan tampak seperti bug di layar.
    expect(mountedWrappers).toHaveLength(0)
  })
})

/* ================================================================== */
/* unit_test_case 50-53 — GALAT, MASING-MASING DENGAN KEADAANNYA        */
/* ================================================================== */

describe('galat: empat kode, empat keadaan yang berbeda', () => {
  // unit_test_case 50
  it('unit_test_case 50: 401 pada /summary mengarahkan ke Login tanpa pesan jaringan', async () => {
    repoMocks.fetchSummary.mockImplementation(
      (periodId: string, scope?: KernelDispatchReportScope) => {
        requests.push(SUMMARY_PATH)
        summaryCalls.push({ periodId, scope: scope ?? {} })

        return Promise.reject(UNAUTHENTICATED_ERROR)
      },
    )

    const wrapper = await mountFullReport()

    expect(pushMock).toHaveBeenCalledTimes(1)
    expect(pushMock).toHaveBeenCalledWith({ name: 'login' })

    // Sesi berakhir BUKAN kegagalan jaringan, dan menyebutnya demikian
    // mengirim pengguna mencari masalah di tempat yang salah.
    expect(count(wrapper, 'network-error')).toBe(0)
    expect(count(wrapper, 'retry-button')).toBe(0)
    expect(normalize(wrapper.element.textContent ?? '')).not.toContain('jaringan')
    expect(kpiElements(wrapper)).toHaveLength(0)
  })

  // unit_test_case 51
  it('unit_test_case 51: 403 meminta memilih line lagi DAN MEMBERSIHKAN angka yang sudah ada', async () => {
    let calls = 0

    repoMocks.fetchSummary.mockImplementation(
      (periodId: string, scope?: KernelDispatchReportScope) => {
        requests.push(SUMMARY_PATH)
        summaryCalls.push({ periodId, scope: scope ?? {} })
        calls += 1

        if (calls > 1) {
          return Promise.reject(FORBIDDEN_ERROR)
        }

        return Promise.resolve(makeSummary() as unknown as KernelDispatchReportSummary)
      },
    )

    const wrapper = await mountFullReport()

    // DIBUKTIKAN DULU bahwa angkanya memang sudah terender.
    expect(count(wrapper, 'kd-card-tonnage')).toBe(1)
    expect(count(wrapper, 'kd-card-quality')).toBe(1)

    await selectPeriod(wrapper, 'p-2')

    expect(exists(wrapper, 'production-line-rejected')).toBe(true)
    expect(normalize(text(wrapper, 'production-line-rejected'))).toContain(
      'Silakan pilih Production Line kembali',
    )
    expect(count(wrapper, 'network-error')).toBe(0)

    /**
     * HILANG DARI DOM, bukan sekadar tertutup dan bukan berubah menjadi '—':
     * angka mill sendiri yang tertinggal di layar bersama pesan 403 akan
     * dibaca sebagai angka yang baru.
     */
    for (const testid of [
      'kd-card-tonnage',
      'kd-card-quality',
      ...RECAP_TESTIDS,
      'kd-daily-recap',
      'kd-kpi-not-released',
      'kd-compliance',
    ]) {
      expect(count(wrapper, testid), testid).toBe(0)
    }

    expect(kpiElements(wrapper)).toHaveLength(0)
    expect(selectedOptionText(wrapper, 'production-line-select')).toBe(NO_LINE_SELECTED)
  })

  // unit_test_case 52
  it('unit_test_case 52: 422 akun tanpa mill menampilkan arahan menghubungi Admin', async () => {
    repoMocks.fetchPeriods.mockImplementation((scope?: KernelDispatchReportScope) => {
      requests.push(PERIODS_PATH)
      periodsCalls.push(scope ?? {})

      return Promise.reject(NO_MILL_ERROR)
    })

    const wrapper = await mountView()

    expect(exists(wrapper, 'no-mill-for-account')).toBe(true)
    expect(normalize(text(wrapper, 'no-mill-for-account'))).toContain('hubungi Admin')

    // Penyebabnya BUKAN jaringan dan BUKAN hak akses, melainkan data akun yang
    // belum lengkap.
    expect(count(wrapper, 'network-error')).toBe(0)
    // Mencoba lagi tidak akan mengubah apa pun, jadi tombolnya tidak ada.
    expect(count(wrapper, 'retry-button')).toBe(0)
    // Dan ini BUKAN sesi berakhir.
    expect(pushMock).not.toHaveBeenCalled()
  })

  // unit_test_case 53
  it('unit_test_case 53: galat lain menampilkan keadaan galat + Coba Lagi, dengan jumlah permintaan DIHITUNG', async () => {
    let calls = 0

    repoMocks.fetchSummary.mockImplementation(
      (periodId: string, scope?: KernelDispatchReportScope) => {
        requests.push(SUMMARY_PATH)
        summaryCalls.push({ periodId, scope: scope ?? {} })
        calls += 1

        if (calls === 1) {
          return Promise.reject(SERVER_ERROR)
        }

        return Promise.resolve(makeSummary() as unknown as KernelDispatchReportSummary)
      },
    )

    const wrapper = await mountFullReport()

    expect(exists(wrapper, 'network-error')).toBe(true)
    expect(exists(wrapper, 'retry-button')).toBe(true)
    expect(hits(SUMMARY_PATH)).toBe(1)
    expect(kpiElements(wrapper)).toHaveLength(0)

    await wrapper.get('[data-testid="retry-button"]').trigger('click')
    await flushPromises()

    // TEPAT 2 — bukan 3 dan bukan 4, supaya 'coba lagi' yang diam-diam
    // memanggil dua kali tertangkap.
    expect(hits(SUMMARY_PATH)).toBe(2)
    expect(count(wrapper, 'network-error')).toBe(0)
    expect(exists(wrapper, 'kd-card-tonnage')).toBe(true)
    expect(rawText(wrapper, 'kd-kpi-dispatch-count')).toBe('30')
  })
})

/* ================================================================== */
/* unit_test_case 54-56 — EKSPOR CSV SAJA                               */
/* ================================================================== */

describe('ekspor CSV saja', () => {
  // unit_test_case 54
  it('unit_test_case 54: URL ekspor tepat — format=csv dan kedua parameter lingkup', () => {
    // buildExportUrl YANG SUNGGUHAN — ia bukan anggota repo yang di-mock.
    const url = buildExportUrl('p-1', { productionLineId: 'pl-7' })
    const parsed = new URL(url, 'http://localhost')

    expect(parsed.pathname).toBe('/api/kernel-dispatch-reports/export')
    expect(parsed.searchParams.get('format')).toBe('csv')
    expect(parsed.searchParams.get('period_id')).toBe('p-1')
    expect(parsed.searchParams.get('production_line_id')).toBe('pl-7')
    // Peran terikat mill: kuncinya ABSEN — bukan null, bukan string kosong.
    expect(parsed.searchParams.has('business_unit_id')).toBe(false)
    // Dan NOL nilai 'xlsx' di seluruh query string.
    expect(parsed.search).not.toContain('xlsx')

    // Admin: parameter mill-nya ikut, lewat pembentuk yang SAMA.
    const adminUrl = new URL(
      buildExportUrl('p-1', { isAdmin: true, businessUnitId: 'bu-9', productionLineId: 'pl-7' }),
      'http://localhost',
    )

    expect(adminUrl.searchParams.get('business_unit_id')).toBe('bu-9')
    expect(adminUrl.searchParams.get('format')).toBe('csv')
  })

  // unit_test_case 55
  it('unit_test_case 55: ketukan ganda cepat pada ekspor mengirim SATU permintaan dan SATU unduhan', async () => {
    let release!: (value: Blob) => void

    repoMocks.exportCsv.mockImplementation(
      (periodId: string, scope?: KernelDispatchReportScope) => {
        requests.push(EXPORT_PATH)
        exportCalls.push({ periodId, scope: scope ?? {} })

        return new Promise<Blob>((resolve) => {
          release = resolve
        })
      },
    )

    const wrapper = await mountFullReport()
    const button = wrapper.get('[data-testid="kd-export-csv"]')

    await button.trigger('click')

    expect(button.attributes('disabled')).toBeDefined()
    expect(button.attributes('aria-busy')).toBe('true')
    expect(normalize(button.text())).toContain('Mengekspor')

    /**
     * KETUKAN KEDUA DIKIRIM LANGSUNG KE DOM, bukan lewat wrapper.trigger().
     *
     * `trigger()` VTU MELEWATI dispatch pada elemen ber-atribut disabled, jadi
     * yang teruji dengan cara itu adalah penjagaan ALAT UJI — bukan penjagaan
     * APLIKASI. Dengan dispatch langsung, handler Vue benar-benar dipanggil
     * dan yang menahan permintaan kedua adalah `exporting` di dalam
     * onExport(). `_vts` disetel Date.now() + 1 dengan alasan yang sama
     * seperti VTU sendiri: invoker Vue membuang event yang timestamp-nya tidak
     * lebih baru daripada saat handler dipasang.
     */
    const forcedClick = new Event('click') as Event & { _vts?: number }

    forcedClick._vts = Date.now() + 1
    button.element.dispatchEvent(forcedClick)
    await flushPromises()

    expect(hits(EXPORT_PATH)).toBe(1)
    expect(exportCalls).toHaveLength(1)

    release(new Blob(['a,b']))
    await flushPromises()

    // SATU pemicu unduhan, dan tombolnya kembali aktif sesudahnya.
    expect(repoMocks.saveCsvFile).toHaveBeenCalledTimes(1)
    expect(button.attributes('disabled')).toBeUndefined()
    expect(button.attributes('aria-busy')).toBe('false')

    const filename = String(repoMocks.saveCsvFile.mock.calls[0][1])

    expect(filename.endsWith('.csv')).toBe(true)
    expect(filename).toContain('laporan-kernel-dispatch')
  })

  // unit_test_case 56
  it('unit_test_case 56: barisan kolom ekspor DISALIN dari EXPORT_HEADER servernya — 27 kolom', () => {
    /**
     * DUA PULUH TUJUH KOLOM: 6 konteks (EXPORT_CONTEXT_COLUMN_COUNT) + 21
     * kolom detail (DETAIL_EXPORT_FIELDS).
     *
     * Daftar di bawah DISALIN VERBATIM dari
     * KernelDispatchReportService::EXPORT_HEADER — nama, EJAAN dan URUTANNYA
     * — lalu DIBANDINGKAN dengan konstanta yang DIBACA DARI BERKAS SERVICE
     * saat test ini berjalan. Dengan begitu dua kesalahan yang berbeda
     * sama-sama tertangkap: daftar yang dikarang di sini (memerah sekarang),
     * dan header server yang berubah kelak (memerah nanti).
     *
     * Kenapa ini layak dicatat: pada screen-161 stub CSV DIKARANG dan
     * menghasilkan jumlah kolom yang salah. Seandainya angka asersinya yang
     * disesuaikan, berkasnya hijau sambil membuktikan NOL hal tentang berkas
     * yang sungguh diunduh pengguna — layar mengunduh dari endpoint ASLI.
     */
    const EXPORT_HEADER = [
      'Periode',
      'Rentang Periode',
      'Mill',
      'Production Line',
      'Tanggal Record',
      'Status',
      'Tanggal Kejadian',
      'Shift',
      'Weighbridge Ticket No',
      'Waybill Number',
      'Transporter/Contractor',
      'Vehicle Plate No',
      'Driver Name',
      'Silo Source ID',
      'Destination/Buyer',
      'Gross Weight (MT)',
      'Tare Weight (MT)',
      'Net Weight (MT)',
      'Kernel Moisture (%)',
      'Dirt/Impurities (%)',
      'FFA (%)',
      'Broken Kernel (%)',
      'Security Seal No (Top)',
      'Security Seal No (Bottom)',
      'Weighbridge Operator ID',
      'Remarks/Gate Status',
      'Findings',
    ]

    const fromService = phpArrayConst(SERVICE_SOURCE, 'EXPORT_HEADER')

    // toEqual atas ARRAY JUDUL, bukan atas panjangnya saja.
    expect(fromService).toEqual(EXPORT_HEADER)
    expect(fromService).toHaveLength(27)

    // ENAM kolom konteks yang diulang verbatim per baris — satu lebih banyak
    // daripada kelima belas laporan sebelumnya, karena Rentang Periode tidak
    // dapat diturunkan dari nama periode yang berupa teks bebas.
    expect(SERVICE_SOURCE).toContain('EXPORT_CONTEXT_COLUMN_COUNT = 6')
    expect(phpArrayConst(SERVICE_SOURCE, 'DETAIL_EXPORT_FIELDS')).toHaveLength(21)
    expect(6 + 21).toBe(fromService.length)
    expect(fromService.slice(0, 6)).toEqual([
      'Periode',
      'Rentang Periode',
      'Mill',
      'Production Line',
      'Tanggal Record',
      'Status',
    ])

    /**
     * KEENAM kolom yang TIDAK menyumbang satu angka pun di layar — justru itu
     * yang membuat asersi ini berarti.
     *
     * KOREKSI ATAS DESKRIPSI ENDPOINT DI TECH SPEC: ia menyebut `Waybill
     * Number` dan kedua kolom segel ikut di antara "yang tidak muncul di
     * layar". Itu KELIRU: ketiganya MENYUMBANG angka lewat blok `compliance`
     * (missing_waybill_count, missing_seal_top_count,
     * missing_seal_bottom_count). Yang tidak muncul adalah NILAINYA, bukan
     * keberadaannya — dan yang diasersi di sini adalah apa yang benar.
     */
    for (const column of [
      'Shift',
      'Weighbridge Ticket No',
      'Vehicle Plate No',
      'Driver Name',
      'Weighbridge Operator ID',
    ]) {
      // SATU NEEDLE PER PEMANGGILAN (jebakan #6).
      expect(fromService, column).toContain(column)
    }

    const compliance = Object.keys(
      (makeSummary().compliance ?? {}) as Record<string, unknown>,
    )

    expect(compliance).toContain('missing_waybill_count')
    expect(compliance).toContain('missing_seal_top_count')
    expect(compliance).toContain('missing_seal_bottom_count')
  })
})

/* ================================================================== */
/* unit_test_case 57-58 — PINTU MASUK DAN RIAKNYA                       */
/* ================================================================== */

describe('pintu masuk tile Reporting dan riak spec tetangga', () => {
  function makeStation(overrides: Partial<StationSlot> & { id: string }): StationSlot {
    return {
      businessUnitId: 'bu-1',
      name: `Stasiun ${overrides.id}`,
      type: 'other' as StationType,
      isActive: false,
      icon: null,
      ...overrides,
    } as StationSlot
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

  // unit_test_case 57
  it('unit_test_case 57: TEPAT SATU baris ditambahkan ke REPORT_ROUTES, ikon tidak disentuh', async () => {
    const map = reportRoutesSource()

    // KUNCINYA WAJIB BERKUTIP karena bertanda hubung — kunci bergaris-bawah
    // ('kernel_dispatch') akan terkompilasi, terlihat benar, dan tidak pernah
    // cocok dengan satu station.type pun.
    expect(map).toContain("'kernel-dispatch': 'report-kernel-dispatch'")
    expect(map).not.toContain('kernel_dispatch')

    /**
     * "Bertambah tepat satu" diasersi atas HIMPUNAN KUNCI LENGKAPNYA: kelima
     * belas entri sebelum pekerjaan ini, ditambah satu, menjadi ENAM BELAS.
     * Entri yang tertambah atau terhapus diam-diam akan memerah di sini.
     */
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

    // Nama rute dan path-nya SAMA PERSIS dengan yang terdaftar di router.
    const routerSource = readSource('src/router/index.ts')

    expect(routerSource).toContain("name: 'report-kernel-dispatch'")
    expect(routerSource).toContain("path: '/reports/kernel-dispatch'")

    /**
     * IKONNYA SUDAH ADA dan TIDAK ditambah ulang — entri ganda akan membuat
     * yang kedua menang diam-diam.
     *
     * DIVERGENSI 2 pada docblock berkas: peta itu bernama `TYPE_ICONS`, bukan
     * `STATION_ICONS` seperti yang ditulis case-nya; nama yang disebut case
     * tidak ada sama sekali di berkasnya.
     */
    const icons = typeIconsSource()
    const picker = stripComments(readSource(PICKER_PATH))

    expect(picker).toContain('const TYPE_ICONS')
    expect(picker).not.toContain('const STATION_ICONS')
    expect(icons.split("'kernel-dispatch'").length - 1).toBe(1)
    expect(icons).toContain("'kernel-dispatch': '<path d=\"M21 8 12 3 3 8v8l9 5 9-5V8z\"/>")

    /**
     * UNIT TEST PERILAKUNYA: satu baris itulah yang menentukan tile hidup atau
     * kelabu, dan tanpanya layar ini ada, rutenya ada, seluruh test-nya lulus,
     * dan tile-nya tetap kelabu.
     *
     * `isActive: false` di fixture ini SENGAJA tidak masuk akal: justru
     * ketidakwajarannya yang menjadi alat ujinya — ketersediaan laporan
     * ditentukan HANYA oleh keanggotaan di REPORT_ROUTES.
     */
    const wrapper = await mountPicker([
      makeStation({
        id: 'st-kd',
        name: 'Kernel Dispatch Lini A',
        type: 'kernel-dispatch' as StationType,
        isActive: false,
      }),
    ])

    const tile = wrapper.get('[data-testid="station-tile-kernel-dispatch"]')

    expect(tile.attributes('aria-disabled')).toBe('false')
    expect(tile.text()).not.toContain('Belum tersedia')

    await tile.trigger('click')

    expect(pushMock).toHaveBeenCalledTimes(1)
    expect(pushMock).toHaveBeenCalledWith({ name: 'report-kernel-dispatch', query: {} })
    expect(wrapper.find('[data-testid="info-message"]').exists()).toBe(false)
  })

  // unit_test_case 58
  it('unit_test_case 58: contoh "belum berlaporan" pada spec tetangga sudah berpindah ke kode FIKTIF', () => {
    /**
     * RIAK YANG MEMANG DIRANCANG. ReportingPilihStasiunView.spec.ts memakai
     * satu kode stasiun sebagai contoh 'belum berlaporan'. Begitu
     * 'kernel-dispatch' masuk ke REPORT_ROUTES, contoh itu berhenti menguji
     * apa pun — dan penggantinya BUKAN stasiun nyata lain melainkan kode
     * FIKTIF, karena spec itu MENYUNTIKKAN daftar stasiunnya sendiri lewat
     * makeStation(...). Memindahkannya ke 'cpo-dispatch' atau
     * 'process-quality-control' hanya menunda riak yang sama ke screen-167/169.
     */
    const neighbourSpec = readSource('tests/ReportingPilihStasiunView.spec.ts')
    const map = reportRoutesSource()

    expect(neighbourSpec).toContain("type: 'zz-stasiun-fiktif'")
    expect(map).not.toContain('zz-stasiun-fiktif')

    // Dan contoh yang PENSIUN tidak lagi dipakai di sana sama sekali.
    expect(neighbourSpec).not.toContain("type: 'kernel-dispatch'")
    expect(neighbourSpec).not.toContain('station-tile-kernel-dispatch')
    expect(neighbourSpec).not.toContain('Laporan Kernel Dispatch belum tersedia')

    // Spec screen-163 pun memaku himpunan kunci yang SAMA, dan ia sudah
    // diperbarui menjadi 16 — bukan dilonggarkan menjadi toContain.
    const siblingSpec = readSource('tests/LaporanSolidWasteDisposalView.spec.ts')

    expect(siblingSpec).toContain("'kernel-dispatch',")
    expect(siblingSpec).toContain('expect(keys).toHaveLength(16)')
  })
})

/* ================================================================== */
/* unit_test_case 59 — DISIPLIN KELAS CSS                               */
/* ================================================================== */

describe('disiplin kelas CSS', () => {
  /**
   * Tiap kelas yang dipakai markup WAJIB punya definisi. Kelas tanpa definisi
   * tidak menggagalkan satu asersi perilaku pun — ia hanya membuat bagian
   * layar kehilangan gayanya tanpa satu tanda, dan kartu mutu memperkenalkan
   * kelas tata letak BARU (.quality-card, .quality-range*) yang tidak ada di
   * layar mana pun sebelumnya.
   *
   * DIVERGENSI 3 pada docblock berkas: yang diperiksa DI SINI adalah
   * SUMBERNYA, karena jsdom tidak punya hasil compile sama sekali
   * (document.styleSheets kosong, dan tidak satu blok <style scoped> pun
   * dimuat). Pemeriksaan atas HASIL COMPILE — dibaca dari document.styleSheets
   * lewat atribut href dokumen itu sendiri — adalah milik spec browser.
   */
  const STYLE_SOURCES = [
    VIEW_PATH,
    'src/assets/stacked-table.css',
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

    /**
     * AUDIT INI DIBUKTIKAN DAPAT MEMERAH: sebuah glob sumber yang tidak cocok
     * memulangkan string kosong, dan setiap kelas lalu "punya definisi". Pada
     * audit sebelumnya di proyek ini pola kebalikannya yang terjadi — berkas
     * gaya bersama tidak pernah terbaca dan audit melaporkan 14 cacat palsu
     * dengan nada seyakin cacat nyata.
     */
    expect(sources.includes('.kelas-yang-sengaja-tidak-pernah-didefinisikan')).toBe(false)
    expect(sources.includes('.quality-card')).toBe(true)
    expect(sources.includes('.detail-table')).toBe(true)

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

  // unit_test_case 59
  it('unit_test_case 59: setiap kelas yang dipakai markup punya definisi — jalur sukses DAN keadaan lain', async () => {
    const sources = definitions()
    const used = new Set<string>()

    // (a) JALUR SUKSES, dengan kelima bagian dilipat lalu dibuka kembali
    // supaya kelas kedua keadaan kontrol lipat ikut terkumpul.
    const full = await mountFullReport()

    classesUsedIn(full).forEach((className) => used.add(className))

    for (const testid of [...RECAP_TESTIDS, 'kd-daily-recap']) {
      await toggleSection(full, testid)
    }

    classesUsedIn(full).forEach((className) => used.add(className))

    expect(used.size).toBeGreaterThan(20)

    // (b) tanpa line terpilih
    const plain = await mountView()

    classesUsedIn(plain).forEach((className) => used.add(className))

    // (c) galat jaringan + Coba Lagi
    repoMocks.fetchSummary.mockImplementation(
      (periodId: string, scope?: KernelDispatchReportScope) => {
        requests.push(SUMMARY_PATH)
        summaryCalls.push({ periodId, scope: scope ?? {} })

        return Promise.reject(NETWORK_ERROR)
      },
    )

    const failing = await mountFullReport()

    classesUsedIn(failing).forEach((className) => used.add(className))

    // (d) periode tanpa satu pengiriman pun — termasuk keadaan kosong keempat
    // rekap, rekap harian, dan keempat kartu mutu tanpa hasil uji.
    const empty = await mountWith(emptyPeriodSummary())

    classesUsedIn(empty).forEach((className) => used.add(className))

    // (e) luring
    fakeOnLine(false)

    const offline = await mountView()

    classesUsedIn(offline).forEach((className) => used.add(className))
    cleanups.pop()?.()

    // (f) akun tanpa mill
    asRole('operator', null)

    const noMill = await mountView()

    classesUsedIn(noMill).forEach((className) => used.add(className))

    // (g) Admin: pemilih Mill + arahan memilih mill
    asRole('admin', null)

    const admin = await mountView()

    classesUsedIn(admin).forEach((className) => used.add(className))

    const undefinedClasses = [...used].filter((className) => !sources.includes(`.${className}`))

    expect(undefinedClasses, `kelas tanpa definisi: ${undefinedClasses.join(', ')}`).toEqual([])
  })
})
