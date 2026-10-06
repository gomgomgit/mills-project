import apiClient from '@/services/apiClient'

/**
 * threshingReportRepo — screen-149--laporan-threshing-mobile /
 * usecase-152--laporan-threshing-mobile "Lihat Laporan Periode Threshing
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
 * /api/threshing-reports/* sudah menerima peran mobile sejak
 * screen-148--laporan-threshing-web dibangun — kedua layar direncanakan dalam
 * satu seri, pola yang sudah terbukti pada pasangan screen-146/147. Pasangan
 * Weighbridge (143 lalu 144) harus menambal tiga perubahan akses menyusul
 * justru karena layar web-nya dibangun sebelum pasangan mobile-nya diketahui.
 *
 * Yang tetap berlaku, dan sudah dikunci test di sisi web: Operator berada di
 * cabang TERIKAT MILL pada ThreshingReportService::resolveBusinessUnit() sejak
 * baris pertama, dan /business-units/options tetap menolaknya dengan 403 —
 * peran yang terikat satu mill tidak punya pemilih, dan menyerahkan daftar
 * seluruh mill kepadanya adalah justru kebocoran yang dihindari.
 *
 * NOL PERHITUNGAN ULANG DI KLIEN. Berkas ini tidak pernah menjumlah,
 * merata-ratakan, membulatkan, mengurutkan, menyaring, atau menurunkan satu
 * angka pun. Seluruh nilai diteruskan apa adanya dari respons server, karena
 * laporan web memakai sumber yang sama (ThreshingReportService) — perhitungan
 * kedua di sisi klien pasti akan menyimpang dari laporan web tanpa ketahuan.
 *
 * ────────────────────────────────────────────────────────────────────────
 * YANG MEMBEDAKAN LAPORAN INI DARI TUJUH LAPORAN SEBELUMNYA
 * ────────────────────────────────────────────────────────────────────────
 * Yang diringkas di sini adalah KONDISI OPERASI: satu record adalah satu hari
 * kerja satu thresher, satu baris adalah satu slot waktu dengan lima kolom
 * ukur. Tiga akibatnya, dan ketiganya menentukan bentuk berkas ini:
 *
 * 1. INILAH LAPORAN PERTAMA YANG MEMBAWA STANDAR OPERASIONAL. Setiap entri
 *    `metrics` mengangkut blok `target` berisi standard_operational_target dan
 *    action_plan_on_deviation dari master threshing_operational_targets, dan
 *    keduanya diteruskan APA ADANYA — termasuk ketika null, yang berarti
 *    master belum punya baris untuk parameter itu.
 *
 *    DAN TIDAK ADA PERBANDINGAN TERHADAP STANDAR ITU, DI MANA PUN. Tidak ada
 *    severity, tidak ada penanda di luar batas, tidak ada penilaian. Sebabnya
 *    ada pada bentuk standarnya: ia TEKS BEBAS ('21 - 23 RPM (optimal for
 *    separation)', 'Within motor rated full-load current (FLC)'), dan
 *    menguraikannya menjadi pembanding berarti mengarang batas yang tidak
 *    pernah ditetapkan siapa pun. Repo ini tidak boleh membuat kunci semacam
 *    itu, dan ada test yang mengunci ketiadaannya.
 *
 * 2. `targets_without_metric` ADALAH TEMUAN, BUKAN SISA. Master memuat enam
 *    parameter sementara formulir mengukur lima, jadi daftar ini berisi
 *    standar yang tidak punya satu pun pengukuran di sistem — hari ini tepat
 *    satu, 'Bearing Temperature'. Diteruskan apa adanya karena standar yang
 *    tidak pernah diukur TERBACA SEPERTI TERPENUHI padahal ia sekadar tidak
 *    ada.
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
 * LaporanThreshingView (membaca `error.status`), dan kegagalan transport
 * (penolakan tanpa `status`) menjadi pesan + tombol Coba Lagi di sana pula.
 *
 * GAGAL TERTUTUP PADA business_unit_id: parameter ini hanya dikirim bila
 * pemanggil menyatakan dirinya Admin (`isAdmin: true`). Bagi Operator,
 * Supervisor, dan Mill Management mill diambil dari akun di sisi server,
 * sehingga business_unit_id yang dipaksakan pemanggil TIDAK PERNAH ikut
 * terkirim — dijaga di sini, bukan hanya di view.
 */

export interface ThreshingReportBusinessUnitOption {
  id: string
  name: string
}

export interface ThreshingReportPeriodOption {
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
  /** Selalu terisi 'threshing' — TIDAK pernah null. */
  station_type: string
  station_type_label: string
}

export interface ThreshingReportBusinessUnitRef {
  id: string
  name: string
}

export interface ThreshingReportProductionLineRef {
  id: string
  name: string
}

export interface ThreshingReportPeriodHeader {
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
 * Ketiga angka pembentuk penyebut ikut dikirim (thresher_count,
 * days_counted, slots_per_thresher_per_day) supaya layar dapat menampilkan
 * dari mana expected_slots berasal, alih-alih sebuah persen tanpa asal.
 */
export interface ThreshingReportCoverage {
  filled_slots: number
  expected_slots: number
  /**
   * null ketika expected_slots nol (periode belum mulai). BUKAN 0 — 0%
   * mengklaim ada yang diukur dan hasilnya nol.
   */
  coverage_percent: number | null
  /** Thresher yang BENAR-BENAR beroperasi, bukan jumlah stasiun terdaftar. */
  thresher_count: number
  /** 24, dari grid slot kanonis layar input itu sendiri. */
  slots_per_thresher_per_day: number
  days_in_period: number
  /** Berhenti di hari ini untuk periode yang masih berjalan. */
  days_counted: number
  period_running: boolean
}

/**
 * Standar operasional satu parameter. Ketiga medannya null ketika master
 * belum punya baris untuk parameter itu — dan ketiadaan itu diteruskan, bukan
 * ditambal, supaya layar dapat menyatakan "standar belum terisi" alih-alih
 * menampilkan sel kosong tanpa keterangan.
 */
export interface ThreshingReportTarget {
  parameter: string | null
  standard_operational_target: string | null
  action_plan_on_deviation: string | null
}

/**
 * Satu kolom ukur, dengan PENYEBUTNYA SENDIRI dan standar operasionalnya.
 *
 * Perhatikan apa yang TIDAK ada di sini: tidak ada severity, tidak ada
 * is_out_of_range, tidak ada flag. Lihat catatan nomor 1 pada docblock berkas.
 */
export interface ThreshingReportMetric {
  /** Nama kolom pada threshing_details — kunci yang dipakai `averages`. */
  column: string
  label: string
  unit: string
  min: number | null
  avg: number | null
  max: number | null
  /** PENYEBUT min/avg/max baris ini — bukan coverage.filled_slots. */
  filled_slot_count: number
  target: ThreshingReportTarget
}

/** Standar yang tidak punya satu pun kolom pengukuran pada formulir. */
export interface ThreshingReportTargetWithoutMetric {
  parameter: string
  standard_operational_target: string
  action_plan_on_deviation: string
}

export interface ThreshingReportThresherRow {
  /** Penamaan unit, bukan kunci baris: id yang sama pada dua tanggal = satu unit. */
  thresher_id: string
  day_count: number
  filled_slot_count: number
  /** Rata-rata per kolom ukur, masing-masing berpenyebut sendiri. null bila kosong. */
  averages: Record<string, number | null>
}

export interface ThreshingReportDailyRow {
  date: string
  filled_slot_count: number
  averages: Record<string, number | null>
}

/**
 * Total periode — DIHITUNG ULANG server atas seluruh slot, bukan rata-rata
 * dari rata-rata harian. Repo meneruskannya apa adanya; menurunkannya dari
 * `daily` di sini akan menghasilkan angka yang berbeda dari laporan web.
 */
export interface ThreshingReportDailyTotal {
  filled_slot_count: number
  averages: Record<string, number | null>
}

export interface ThreshingReportDowntimeRow {
  /** Teks apa adanya. Dua ejaan untuk satu sebab = dua baris, dan itu benar. */
  reason: string
  slot_count: number
}

export interface ThreshingReportTotals {
  record_count: number
  days_with_records: number
  /** Record draft IKUT seluruh angka; jumlahnya hanya dinyatakan. */
  draft_record_count: number
  records_not_checked: number
  records_not_acknowledged: number
}

export interface ThreshingReportSummary {
  business_unit: ThreshingReportBusinessUnitRef | null
  production_line: ThreshingReportProductionLineRef | null
  period: ThreshingReportPeriodHeader | null
  /** Membedakan "tidak ada yang dilaporkan" dari "angkanya nol". */
  has_data: boolean
  coverage: ThreshingReportCoverage
  metrics: ThreshingReportMetric[]
  targets_without_metric: ThreshingReportTargetWithoutMetric[]
  targets_master_empty: boolean
  by_thresher: ThreshingReportThresherRow[]
  daily: ThreshingReportDailyRow[]
  daily_total: ThreshingReportDailyTotal
  downtime_reasons: ThreshingReportDowntimeRow[]
  total: ThreshingReportTotals
}

/**
 * Parameter opsional pemilih mill dan line. `isAdmin` sengaja WAJIB dinyatakan
 * eksplisit oleh pemanggil yang hendak mengirim business_unit_id — lihat
 * catatan "gagal tertutup" pada docblock berkas.
 */
export interface ThreshingReportScope {
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
const EMPTY_COVERAGE: ThreshingReportCoverage = {
  filled_slots: 0,
  expected_slots: 0,
  coverage_percent: null,
  thresher_count: 0,
  slots_per_thresher_per_day: 0,
  days_in_period: 0,
  days_counted: 0,
  period_running: false,
}

const EMPTY_DAILY_TOTAL: ThreshingReportDailyTotal = {
  filled_slot_count: 0,
  averages: {},
}

const EMPTY_TOTALS: ThreshingReportTotals = {
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
function scopeParams(scope?: ThreshingReportScope): Record<string, string> {
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
function productionLineParams(scope?: ThreshingReportScope): Record<string, string> {
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
 * GET /api/threshing-reports/business-units/options — pemilih Mill, ADMIN
 * SAJA. Server menjawab 403 untuk peran lain, TERMASUK Operator: ini
 * satu-satunya rute pada prefix ini yang tidak terbuka baginya, dan itu
 * disengaja. View TIDAK BOLEH memanggilnya untuk peran yang terikat mill —
 * selain percuma, permintaan itu akan membentuk daftar seluruh mill yang
 * memang tidak berhak dilihat.
 */
export async function fetchBusinessUnits(): Promise<ThreshingReportBusinessUnitOption[]> {
  const response = await apiClient.get('/api/threshing-reports/business-units/options')

  return (response.data?.data ?? []) as ThreshingReportBusinessUnitOption[]
}

/**
 * GET /api/threshing-reports/periods — periode yang mencakup stasiun
 * Threshing, yakni periode yang punya baris period_stations berjenis
 * 'threshing'. Penyaringannya dikerjakan SERVER; repo meneruskan daftar apa
 * adanya, dalam urutan yang sama — menyaringnya kedua kali di sini akan
 * menciptakan definisi cakupan yang kedua. Daftar kosong adalah jawaban yang
 * sah (HTTP 200 + []), bukan galat.
 *
 * Periode TERTUTUP tetap terdaftar: status mengatur penulisan data, bukan
 * pembacaan laporan.
 */
export async function fetchPeriods(
  scope?: ThreshingReportScope,
): Promise<ThreshingReportPeriodOption[]> {
  const params = scopeParams(scope)

  const response = await apiClient.get('/api/threshing-reports/periods', { params })

  return (response.data?.data ?? []) as ThreshingReportPeriodOption[]
}

/**
 * GET /api/threshing-reports/summary — seluruh angka layar untuk satu periode
 * pada satu Production Line.
 *
 * Setiap blok diteruskan APA ADANYA, tanpa satu operasi aritmetika pun: tidak
 * ada penurunan coverage_percent dari filled_slots/expected_slots, tidak ada
 * perata-rataan ulang min/avg/max, tidak ada penurunan daily_total dari
 * `daily`, tidak ada pengurutan ulang metrics / by_thresher / daily /
 * downtime_reasons, dan TIDAK ADA perbandingan satu nilai pun terhadap
 * `target`. Nilai bawaan di atas hanya berlaku bila BLOK-nya tidak ada sama
 * sekali pada respons.
 */
export async function fetchSummary(
  periodId: string,
  scope?: ThreshingReportScope,
): Promise<ThreshingReportSummary> {
  const response = await apiClient.get('/api/threshing-reports/summary', {
    params: { period_id: periodId, ...scopeParams(scope), ...productionLineParams(scope) },
  })

  const body = (unwrap<Record<string, unknown>>(response.data) ?? {}) as Record<string, unknown>

  return {
    business_unit: (body.business_unit ?? null) as ThreshingReportBusinessUnitRef | null,
    production_line: (body.production_line ?? null) as ThreshingReportProductionLineRef | null,
    period: (body.period ?? null) as ThreshingReportPeriodHeader | null,
    has_data: (body.has_data ?? false) as boolean,
    coverage: (body.coverage ?? { ...EMPTY_COVERAGE }) as ThreshingReportCoverage,
    metrics: (body.metrics ?? []) as ThreshingReportMetric[],
    targets_without_metric: (body.targets_without_metric ?? []) as ThreshingReportTargetWithoutMetric[],
    targets_master_empty: (body.targets_master_empty ?? false) as boolean,
    by_thresher: (body.by_thresher ?? []) as ThreshingReportThresherRow[],
    daily: (body.daily ?? []) as ThreshingReportDailyRow[],
    daily_total: (body.daily_total ?? { ...EMPTY_DAILY_TOTAL }) as ThreshingReportDailyTotal,
    downtime_reasons: (body.downtime_reasons ?? []) as ThreshingReportDowntimeRow[],
    total: (body.total ?? { ...EMPTY_TOTALS }) as ThreshingReportTotals,
  }
}

/**
 * GET /api/threshing-reports/export?period_id=...&production_line_id=...&format=csv
 * — respons streaming text/csv, BUKAN JSON, jadi responseType-nya blob.
 *
 * Isinya (SATU BARIS PER SLOT WAKTU, dengan konteks Periode/Mill/Production
 * Line, tanggal, thresher, status, dan catatan diulang verbatim di setiap
 * baris) dibentuk SERVER dan sama persis dengan ekspor laporan versi web,
 * karena endpoint-nya memang sama. Slot yang seluruh kolom ukurnya kosong
 * TETAP satu baris, dengan sel kosong.
 *
 * Format 'csv' saja: ekspor .xlsx hanya ada di layar web.
 */
export async function exportCsv(periodId: string, scope?: ThreshingReportScope): Promise<Blob> {
  const response = await apiClient.get('/api/threshing-reports/export', {
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

export const threshingReportRepo = {
  fetchBusinessUnits,
  fetchPeriods,
  fetchSummary,
  exportCsv,
  saveCsvFile,
}

export default threshingReportRepo
