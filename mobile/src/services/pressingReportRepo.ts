import apiClient from '@/services/apiClient'

/**
 * pressingReportRepo — screen-151--laporan-pressing-mobile /
 * usecase-154--laporan-pressing-mobile "Lihat Laporan Periode Pressing
 * (Mobile)".
 *
 * Kembaran kedelapan dari pola yang sama: sterilizerReportRepo.ts
 * (screen-135), cagesTrackReportRepo.ts (screen-136), boilerRoomReportRepo.ts
 * (screen-137), clarificationReportRepo.ts (screen-138),
 * storageTankReportRepo.ts (screen-139), weighbridgeReportRepo.ts
 * (screen-144), dan gradingReportRepo.ts (screen-147). Satu berkas repo tipis
 * di atas apiClient, tanpa cache lokal. Layar laporan ini memang MEMBUTUHKAN
 * jaringan, jadi tidak ada jalur SQLite offline di sini sama sekali —
 * kegagalan jaringan adalah kondisi yang ditampilkan ke pengguna, bukan yang
 * disembunyikan di balik data basi.
 *
 * NOL ENDPOINT BARU DAN NOL PERUBAHAN BACKEND. Keempat endpoint
 * /api/pressing-reports/* sudah menerima peran mobile sejak
 * screen-150--laporan-pressing-web dibangun — kedua layar direncanakan dalam
 * satu seri, pola yang sudah terbukti pada pasangan screen-146/147. Pasangan
 * Weighbridge (143 lalu 144) harus menambal tiga perubahan akses menyusul
 * justru karena layar web-nya dibangun sebelum pasangan mobile-nya diketahui.
 *
 * Yang tetap berlaku, dan sudah dikunci test di sisi web: Operator berada di
 * cabang TERIKAT MILL pada PressingReportService::resolveBusinessUnit() sejak
 * baris pertama, dan /business-units/options tetap menolaknya dengan 403 —
 * peran yang terikat satu mill tidak punya pemilih, dan menyerahkan daftar
 * seluruh mill kepadanya adalah justru kebocoran yang dihindari.
 *
 * NOL PERHITUNGAN ULANG DI KLIEN. Berkas ini tidak pernah menjumlah,
 * merata-ratakan, membulatkan, mengurutkan, menyaring, atau menurunkan satu
 * angka pun. Seluruh nilai diteruskan apa adanya dari respons server, karena
 * laporan web memakai sumber yang sama (PressingReportService) — perhitungan
 * kedua di sisi klien pasti akan menyimpang dari laporan web tanpa ketahuan.
 *
 * ────────────────────────────────────────────────────────────────────────
 * YANG MEMBEDAKAN LAPORAN INI DARI TUJUH LAPORAN SEBELUMNYA
 * ────────────────────────────────────────────────────────────────────────
 * Yang diringkas di sini adalah KONDISI OPERASI: satu record adalah satu hari
 * kerja satu presser, satu baris adalah satu slot waktu dengan lima kolom
 * ukur. Tiga akibatnya, dan ketiganya menentukan bentuk berkas ini:
 *
 * 1. SETIAP ENTRI `metrics` MENGANGKUT DUA KOLOM TARGET, BUKAN SATU, dan
 *    nama kolomnya BERBEDA dari master Threshing: parameter_metric,
 *    target_operating_range, dan critical_trigger_action_limit. Keduanya
 *    diteruskan APA ADANYA — termasuk tanda kurung penjelas pada batas
 *    tindakannya, yang merupakan satu-satunya tempat akibat penyimpangan
 *    tertulis, dan termasuk ketika null (master belum punya barisnya).
 *
 *    KEDUANYA MENJAWAB PERTANYAAN YANG BERBEDA: rentang kerja adalah "ke mana
 *    seharusnya", batas tindakan adalah "kapan harus bertindak dan apa
 *    akibatnya". Meneruskan salah satunya saja menghapus separuh informasi
 *    yang dipakai pembaca untuk memutuskan.
 *
 *    DAN TIDAK ADA PERBANDINGAN TERHADAP KEDUANYA, DI MANA PUN. Tidak ada
 *    severity, tidak ada penanda di luar batas, tidak ada penilaian — dan di
 *    sini sebabnya harus lebih tajam daripada pada Threshing, karena
 *    critical_trigger_action_limit JUSTRU membawa pembanding yang teratur
 *    pada lima dari tujuh parameter ('< 85C', '> 50 Amps', '> 60 Bar').
 *    Yang menahannya: kedua kolom itu teks bebas dan TIDAK ADA APA PUN PADA
 *    SKEMA yang membatasi bentuknya, sehingga himpunan masukan pengurai tidak
 *    tetap pada waktu build — satu seeder yang dijalankan atau satu suntingan
 *    langsung ke basis data dapat memperkenalkan bentuk baru tanpa satu pun
 *    test menangkapnya. Pengurai yang lalu gagal akan BERHENTI MEMPERINGATKAN
 *    tanpa satu pun galat, dan peringatan yang hilang terbaca sebagai
 *    "semuanya aman". Satu nilai pada master pun sudah tidak dapat diurai
 *    tanpa menebak ('< 10% to 12%').
 *    Repo ini tidak boleh membuat kunci penilaian semacam itu, dan ada test
 *    yang mengunci ketiadaannya.
 *
 * 2. `targets_without_metric` ADALAH TEMUAN, BUKAN SISA. Master memuat TUJUH
 *    parameter sementara formulir mengukur lima, jadi daftar ini berisi DUA
 *    entri: 'Nut Breakage Rate' dan 'Press Cake Moisture'. Keduanya lebih
 *    tajam daripada satu entri milik Threshing: tidak satu pun punya kolom
 *    pengukuran DI MANA PUN pada skema ini, bukan hanya di pressing_details —
 *    dan keduanya justru parameter yang paling menentukan mutu pengepresan.
 *    Diteruskan apa adanya karena standar yang tidak pernah diukur TERBACA
 *    SEPERTI TERPENUHI padahal ia sekadar tidak ada.
 *
 * 3. SETIAP METRIK MEMBAWA PENYEBUTNYA SENDIRI. `metrics[].filled_slot_count`
 *    adalah banyaknya slot yang BENAR-BENAR MENCATAT kolom itu — penyebut
 *    min/avg/max-nya, dan satu-satunya hal yang membedakannya dari jumlah slot
 *    terisi periode. Kelima kolom nullable dan terisi saling bebas, jadi satu
 *    penyebut bersama akan salah untuk setidaknya empat di antaranya.
 *
 * SATU KEJANGGALAN YANG BENAR: coverage.filled_slots BISA LEBIH BESAR
 * daripada penyebut kolom ukur mana pun. Sebuah slot dihitung terisi bila
 * salah satu dari ENAM kolom bacaan terisi, dan kolom keenam adalah
 * downtime_reason — slot yang hanya memuat "Belt kendur" jelas disentuh
 * operator, jadi melaporkannya sebagai slot kosong akan salah. Dicatat di sini
 * supaya pembaca berikutnya tidak "memperbaiki" selisih yang memang benar.
 *
 * NULL BUKAN NOL. min, avg, max tiap metrik, coverage_percent, serta seluruh
 * nilai di dalam `averages` boleh null. Mengoersinya menjadi 0 (atau '' / '-')
 * di sini akan mengubah "tidak ada yang diukur" menjadi "hasilnya nol" — dua
 * fakta yang berbeda, dan yang satu menyesatkan. Khususnya coverage_percent:
 * null berarti belum ada hari yang dapat dijadikan pembagi, dan 0% mengklaim
 * ada yang diukur dan hasilnya nol. Penerjemahan null menjadi teks terbaca
 * adalah urusan view, bukan repo.
 *
 * ALASAN DOWNTIME DIKELOMPOKKAN SERVER, SECARA HARFIAH: tanpa penyeragaman
 * ejaan dan tanpa penyeragaman huruf besar-kecil. Repo meneruskan daftarnya
 * apa adanya, dalam urutan yang sama, dan TIDAK menormalkan apa pun —
 * normalisasi di klien akan menggabungkan sebab yang penulisnya memang
 * maksudkan berbeda, dan sekaligus membuat layar ini berselisih dengan laporan
 * web yang memakai sumber yang sama.
 *
 * GALAT DITERUSKAN APA ADANYA. Tidak ada try/catch dan tidak ada kelas galat
 * khusus di sini. Interceptor apiClient menolak lewat normalizeError yang
 * mengembalikan objek DATAR { message, errors?, status? } dan MEMBUANG
 * `response` — jadi `.response` tidak pernah ada pada galat yang sampai ke
 * pemanggil. 401 diterjemahkan menjadi "arahkan ke Login" oleh
 * LaporanPressingView (membaca `error.status`), dan kegagalan transport
 * (penolakan tanpa `status`) menjadi pesan + tombol Coba Lagi di sana pula.
 *
 * GAGAL TERTUTUP PADA business_unit_id: parameter ini hanya dikirim bila
 * pemanggil menyatakan dirinya Admin (`isAdmin: true`). Bagi Operator,
 * Supervisor, dan Mill Management mill diambil dari akun di sisi server,
 * sehingga business_unit_id yang dipaksakan pemanggil TIDAK PERNAH ikut
 * terkirim — dijaga di sini, bukan hanya di view.
 */

export interface PressingReportBusinessUnitOption {
  id: string
  name: string
}

export interface PressingReportPeriodOption {
  id: string
  name: string
  start_date: string
  end_date: string
  /**
   * Status STASIUN LAYAR INI di dalam periode itu (baris period_stations),
   * BUKAN status periode: periode tidak punya status sendiri karena stasiun
   * tidak ditutup serentak.
   */
  status: string
  /** Selalu terisi 'pressing' — TIDAK pernah null. */
  station_type: string
  station_type_label: string
}

export interface PressingReportBusinessUnitRef {
  id: string
  name: string
}

export interface PressingReportProductionLineRef {
  id: string
  name: string
}

export interface PressingReportPeriodHeader {
  id: string
  name: string
  start_date: string
  end_date: string
  status: string
}

/**
 * Cakupan pencatatan — dibaca PALING ATAS di layar, bukan sebagai catatan
 * kaki: periode yang terisi seperlima pun menghasilkan rata-rata yang terlihat
 * rapi.
 *
 * Ketiga angka pembentuk penyebut ikut dikirim (presser_count,
 * days_counted, slots_per_presser_per_day) supaya layar dapat menampilkan
 * dari mana expected_slots berasal, alih-alih sebuah persen tanpa asal.
 */
export interface PressingReportCoverage {
  filled_slots: number
  expected_slots: number
  /**
   * null ketika expected_slots nol (periode belum mulai). BUKAN 0 — 0%
   * mengklaim ada yang diukur dan hasilnya nol.
   */
  coverage_percent: number | null
  /** Presser yang BENAR-BENAR beroperasi, bukan jumlah stasiun terdaftar. */
  presser_count: number
  /** 24, dari grid slot kanonis layar input itu sendiri. */
  slots_per_presser_per_day: number
  days_in_period: number
  /** Berhenti di hari ini untuk periode yang masih berjalan. */
  days_counted: number
  period_running: boolean
}

/**
 * KEDUA kolom target satu parameter, dan keduanya diteruskan karena keduanya
 * menjawab pertanyaan yang BERBEDA: rentang kerja adalah "ke mana
 * seharusnya", batas tindakan adalah "kapan harus bertindak dan apa
 * akibatnya". Meneruskan salah satunya saja menghapus separuh informasi yang
 * dipakai pembaca untuk memutuskan.
 *
 * PERHATIKAN NAMA MEDANNYA. Master Pressing TIDAK memakai nama kolom master
 * Threshing: di sini parameter_metric / target_operating_range /
 * critical_trigger_action_limit, bukan parameter /
 * standard_operational_target / action_plan_on_deviation. Memakai nama
 * Threshing menghasilkan blok target yang seluruhnya null tanpa satu pun
 * galat.
 *
 * Ketiga medan null ketika master belum punya baris untuk parameter itu —
 * dan ketiadaan itu diteruskan, bukan ditambal, supaya layar dapat menyatakan
 * "belum terisi" alih-alih menampilkan sel kosong tanpa keterangan.
 */
export interface PressingReportTarget {
  parameter_metric: string | null
  /** Rentang kerja yang DITUJU — '90C - 95C', '35 - 45 Amperes'. */
  target_operating_range: string | null
  /**
   * Batas yang MENUNTUT TINDAKAN, beserta akibatnya bila tidak —
   * '< 85C (Leads to poor oil liberation)'. Tanda kurung itu bagian dari
   * isinya, bukan hiasan: ia satu-satunya tempat akibat penyimpangan
   * tertulis, jadi tidak boleh dipotong.
   */
  critical_trigger_action_limit: string | null
}

/**
 * Satu kolom ukur, dengan PENYEBUTNYA SENDIRI dan standar operasionalnya.
 *
 * Perhatikan apa yang TIDAK ada di sini: tidak ada severity, tidak ada
 * is_out_of_range, tidak ada flag. Lihat catatan nomor 1 pada docblock berkas.
 */
export interface PressingReportMetric {
  /** Nama kolom pada pressing_details — kunci yang dipakai `averages`. */
  column: string
  label: string
  unit: string
  min: number | null
  avg: number | null
  max: number | null
  /** PENYEBUT min/avg/max baris ini — bukan coverage.filled_slots. */
  filled_slot_count: number
  target: PressingReportTarget
}

/** Standar yang tidak punya satu pun kolom pengukuran pada formulir. */
export interface PressingReportTargetWithoutMetric {
  parameter_metric: string
  target_operating_range: string
  critical_trigger_action_limit: string
}

export interface PressingReportPresserRow {
  /** Penamaan unit, bukan kunci baris: id yang sama pada dua tanggal = satu unit. */
  presser_id: string
  day_count: number
  filled_slot_count: number
  /** Rata-rata per kolom ukur, masing-masing berpenyebut sendiri. null bila kosong. */
  averages: Record<string, number | null>
}

export interface PressingReportDailyRow {
  date: string
  filled_slot_count: number
  averages: Record<string, number | null>
}

/**
 * Total periode — DIHITUNG ULANG server atas seluruh slot, bukan rata-rata
 * dari rata-rata harian. Repo meneruskannya apa adanya; menurunkannya dari
 * `daily` di sini akan menghasilkan angka yang berbeda dari laporan web.
 */
export interface PressingReportDailyTotal {
  filled_slot_count: number
  averages: Record<string, number | null>
}

export interface PressingReportDowntimeRow {
  /** Teks apa adanya. Dua ejaan untuk satu sebab = dua baris, dan itu benar. */
  reason: string
  slot_count: number
}

export interface PressingReportTotals {
  record_count: number
  days_with_records: number
  /** Record draft IKUT seluruh angka; jumlahnya hanya dinyatakan. */
  draft_record_count: number
  records_not_checked: number
  records_not_acknowledged: number
}

export interface PressingReportSummary {
  business_unit: PressingReportBusinessUnitRef | null
  production_line: PressingReportProductionLineRef | null
  period: PressingReportPeriodHeader | null
  /** Membedakan "tidak ada yang dilaporkan" dari "angkanya nol". */
  has_data: boolean
  coverage: PressingReportCoverage
  metrics: PressingReportMetric[]
  targets_without_metric: PressingReportTargetWithoutMetric[]
  targets_master_empty: boolean
  by_presser: PressingReportPresserRow[]
  daily: PressingReportDailyRow[]
  daily_total: PressingReportDailyTotal
  downtime_reasons: PressingReportDowntimeRow[]
  total: PressingReportTotals
}

/**
 * Parameter opsional pemilih mill dan line. `isAdmin` sengaja WAJIB dinyatakan
 * eksplisit oleh pemanggil yang hendak mengirim business_unit_id — lihat
 * catatan "gagal tertutup" pada docblock berkas.
 */
export interface PressingReportScope {
  isAdmin?: boolean
  businessUnitId?: string | null
  /**
   * Production Line yang angkanya diminta. Dikirim ke /summary dan /export
   * saja. WAJIB di layar ini: tanpa nilai ini server menjawab 422, dan view
   * memang tidak memanggil /summary sebelum sebuah line berlaku.
   */
  productionLineId?: string | null
}

/**
 * Nilai bawaan HANYA dipakai ketika seluruh BLOK tidak ada pada respons
 * (mis. bentuk respons berubah di server), bukan untuk menambal medan yang
 * dikirim null. Periode tanpa data tetap mengirim blok lengkap berisi
 * null/nol, dan blok itulah yang diteruskan.
 *
 * Ditulis sebagai literal — bukan dibangun lewat reduce()/map() — supaya
 * berkas ini tetap bebas dari operasi apa pun atas angka laporan, termasuk
 * yang sekadar terlihat seperti perhitungan.
 */
const EMPTY_COVERAGE: PressingReportCoverage = {
  filled_slots: 0,
  expected_slots: 0,
  coverage_percent: null,
  presser_count: 0,
  slots_per_presser_per_day: 0,
  days_in_period: 0,
  days_counted: 0,
  period_running: false,
}

const EMPTY_DAILY_TOTAL: PressingReportDailyTotal = {
  filled_slot_count: 0,
  averages: {},
}

const EMPTY_TOTALS: PressingReportTotals = {
  record_count: 0,
  days_with_records: 0,
  draft_record_count: 0,
  records_not_checked: 0,
  records_not_acknowledged: 0,
}

/**
 * business_unit_id HANYA dikirim untuk Admin. Untuk peran lain fungsi ini
 * mengembalikan objek kosong, apa pun yang dikirimkan pemanggil — kuncinya
 * adalah medan itu ABSEN, bukan null dan bukan string kosong. Operator ada di
 * cabang terikat-mill ini, sama seperti Supervisor dan Mill Management.
 */
function scopeParams(scope?: PressingReportScope): Record<string, string> {
  if (!scope?.isAdmin) {
    return {}
  }

  const businessUnitId = scope.businessUnitId

  if (!businessUnitId) {
    // Admin yang belum memilih mill: repo tidak mengarang nilai dan tidak
    // melempar galat sendiri — server menjawab 422 "Pilih mill terlebih
    // dahulu", dan satu sumber kebenaran itulah yang ditampilkan.
    return {}
  }

  return { business_unit_id: businessUnitId }
}

/**
 * production_line_id — dikirim HANYA ke /summary dan /export.
 *
 * TIDAK PERNAH ke /periods. Periode adalah milik MILL, bukan milik Production
 * Line, dan backend pun tidak menerima parameter ini di sana. Menyaring daftar
 * periode per line akan mengarang penyempitan yang tidak ada di data.
 *
 * Tidak bercabang berdasarkan peran: production_line_id bukan kewenangan
 * melainkan konteks angka. Yang dijaga di sini hanya satu: nilai kosong tidak
 * pernah dikirim sebagai parameter kosong — server akan menjawab 422 tentang
 * parameter yang HILANG, dan itu pesan yang benar.
 */
function productionLineParams(scope?: PressingReportScope): Record<string, string> {
  const productionLineId = scope?.productionLineId

  if (!productionLineId) {
    return {}
  }

  return { production_line_id: productionLineId }
}

/**
 * Respons /summary dikirim controller TANPA pembungkus `data`, sementara
 * /periods dan /business-units/options MEMAKAI pembungkus itu. Kedua bentuk
 * diterima di sini supaya repo ini tidak pecah bila pembungkusnya kelak
 * diseragamkan di sisi server.
 */
function unwrap<T>(payload: unknown): T | null {
  if (payload === null || payload === undefined) {
    return null
  }

  const body = payload as Record<string, unknown>

  if (body.data !== undefined && body.data !== null) {
    return body.data as T
  }

  return payload as T
}

/**
 * GET /api/pressing-reports/business-units/options — pemilih Mill, ADMIN
 * SAJA. Server menjawab 403 untuk peran lain, TERMASUK Operator: ini
 * satu-satunya rute pada prefix ini yang tidak terbuka baginya, dan itu
 * disengaja. View TIDAK BOLEH memanggilnya untuk peran yang terikat mill —
 * selain percuma, permintaan itu akan membentuk daftar seluruh mill yang
 * memang tidak berhak dilihat.
 */
export async function fetchBusinessUnits(): Promise<PressingReportBusinessUnitOption[]> {
  const response = await apiClient.get('/api/pressing-reports/business-units/options')

  return (response.data?.data ?? []) as PressingReportBusinessUnitOption[]
}

/**
 * GET /api/pressing-reports/periods — periode yang mencakup stasiun
 * Pressing, yakni periode yang punya baris period_stations berjenis
 * 'pressing'. Penyaringannya dikerjakan SERVER; repo meneruskan daftar apa
 * adanya, dalam urutan yang sama — menyaringnya kedua kali di sini akan
 * menciptakan definisi cakupan yang kedua. Daftar kosong adalah jawaban yang
 * sah (HTTP 200 + []), bukan galat.
 *
 * Periode TERTUTUP tetap terdaftar: status mengatur penulisan data, bukan
 * pembacaan laporan.
 */
export async function fetchPeriods(
  scope?: PressingReportScope,
): Promise<PressingReportPeriodOption[]> {
  const params = scopeParams(scope)

  const response = await apiClient.get('/api/pressing-reports/periods', { params })

  return (response.data?.data ?? []) as PressingReportPeriodOption[]
}

/**
 * GET /api/pressing-reports/summary — seluruh angka layar untuk satu periode
 * pada satu Production Line.
 *
 * Setiap blok diteruskan APA ADANYA, tanpa satu operasi aritmetika pun: tidak
 * ada penurunan coverage_percent dari filled_slots/expected_slots, tidak ada
 * perata-rataan ulang min/avg/max, tidak ada penurunan daily_total dari
 * `daily`, tidak ada pengurutan ulang metrics / by_presser / daily /
 * downtime_reasons, dan TIDAK ADA perbandingan satu nilai pun terhadap
 * `target`. Nilai bawaan di atas hanya berlaku bila BLOK-nya tidak ada sama
 * sekali pada respons.
 */
export async function fetchSummary(
  periodId: string,
  scope?: PressingReportScope,
): Promise<PressingReportSummary> {
  const response = await apiClient.get('/api/pressing-reports/summary', {
    params: { period_id: periodId, ...scopeParams(scope), ...productionLineParams(scope) },
  })

  const body = (unwrap<Record<string, unknown>>(response.data) ?? {}) as Record<string, unknown>

  return {
    business_unit: (body.business_unit ?? null) as PressingReportBusinessUnitRef | null,
    production_line: (body.production_line ?? null) as PressingReportProductionLineRef | null,
    period: (body.period ?? null) as PressingReportPeriodHeader | null,
    has_data: (body.has_data ?? false) as boolean,
    coverage: (body.coverage ?? { ...EMPTY_COVERAGE }) as PressingReportCoverage,
    metrics: (body.metrics ?? []) as PressingReportMetric[],
    targets_without_metric: (body.targets_without_metric ?? []) as PressingReportTargetWithoutMetric[],
    targets_master_empty: (body.targets_master_empty ?? false) as boolean,
    by_presser: (body.by_presser ?? []) as PressingReportPresserRow[],
    daily: (body.daily ?? []) as PressingReportDailyRow[],
    daily_total: (body.daily_total ?? { ...EMPTY_DAILY_TOTAL }) as PressingReportDailyTotal,
    downtime_reasons: (body.downtime_reasons ?? []) as PressingReportDowntimeRow[],
    total: (body.total ?? { ...EMPTY_TOTALS }) as PressingReportTotals,
  }
}

/**
 * GET /api/pressing-reports/export?period_id=...&production_line_id=...&format=csv
 * — respons streaming text/csv, BUKAN JSON, jadi responseType-nya blob.
 *
 * Isinya (SATU BARIS PER SLOT WAKTU, dengan konteks Periode/Mill/Production
 * Line, tanggal, presser, status, dan catatan diulang verbatim di setiap
 * baris) dibentuk SERVER dan sama persis dengan ekspor laporan versi web,
 * karena endpoint-nya memang sama. Slot yang seluruh kolom ukurnya kosong
 * TETAP satu baris, dengan sel kosong.
 *
 * Format 'csv' saja: ekspor .xlsx hanya ada di layar web.
 */
export async function exportCsv(periodId: string, scope?: PressingReportScope): Promise<Blob> {
  const response = await apiClient.get('/api/pressing-reports/export', {
    params: {
      period_id: periodId,
      format: 'csv',
      ...scopeParams(scope),
      ...productionLineParams(scope),
    },
    responseType: 'blob',
  })

  return response.data as Blob
}

/**
 * Mekanisme penyimpanan berkas — dipisah dari exportCsv() supaya pengambilan
 * data (yang perlu diuji atas query-nya) dan penyimpanan berkas (yang perlu
 * diuji atas pemanggilannya) dapat diamati sendiri-sendiri. Memakai anchor +
 * object URL, konvensi web standar yang juga berlaku di WebView Capacitor.
 */
export function saveCsvFile(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob)
  const anchor = document.createElement('a')

  anchor.href = url
  anchor.download = filename
  document.body.appendChild(anchor)
  anchor.click()
  document.body.removeChild(anchor)

  URL.revokeObjectURL(url)
}

export const pressingReportRepo = {
  fetchBusinessUnits,
  fetchPeriods,
  fetchSummary,
  exportCsv,
  saveCsvFile,
}

export default pressingReportRepo
