import apiClient from '@/services/apiClient'

/**
 * clarificationReportRepo — screen-138--laporan-clarification-mobile /
 * usecase-138--laporan-clarification-mobile "Lihat Laporan Periode
 * Clarification (Mobile)".
 *
 * Kembaran keempat dari pola yang sama: sterilizerReportRepo.ts
 * (screen-135), cagesTrackReportRepo.ts (screen-136), dan
 * boilerRoomReportRepo.ts (screen-137). Satu berkas repo tipis di atas
 * apiClient, tanpa cache lokal. Layar laporan ini memang MEMBUTUHKAN
 * jaringan (business_rules: "Layar ini membutuhkan jaringan"), jadi tidak
 * ada jalur SQLite offline di sini sama sekali — kegagalan jaringan adalah
 * kondisi yang ditampilkan ke pengguna, bukan yang disembunyikan di balik
 * data basi.
 *
 * NOL ENDPOINT BARU. Keempat endpoint milik
 * screen-132--laporan-clarification-web dipakai ulang apa adanya
 * (App\Http\Controllers\Api\ClarificationReportController). Perubahan
 * backend untuk layar ini hanya soal SIAPA yang boleh memanggil — TEPAT
 * TIGA BARIS KODE:
 *   1. routes/api.php: grup rute clarification-reports mendapat peran
 *      `operator` (guard 'auth:web,sanctum' sudah ada sebelumnya, jadi ia
 *      TIDAK ikut berubah — berbeda dari catatan tech spec yang menyebut
 *      empat perubahan);
 *   2. ClarificationReportService::guardAccess() menerima
 *      UserRole::Operator;
 *   3. — dan yang paling mudah terlewat —
 *      ClarificationReportService::resolveBusinessUnit() memasukkan
 *      Operator ke cabang TERIKAT MILL, bukan membiarkannya jatuh ke
 *      cabang Admin yang tidak terikat, tempat business_unit_id kiriman
 *      klien DIHORMATI.
 *
 * NOL PERHITUNGAN ULANG DI KLIEN. Berkas ini tidak pernah menjumlah,
 * merata-ratakan, membulatkan, mengurutkan, menyaring, atau menurunkan satu
 * angka pun. Seluruh nilai diteruskan apa adanya dari respons server,
 * karena laporan web memakai sumber yang sama (ClarificationReportService)
 * — perhitungan kedua di sisi klien pasti akan menyimpang dari laporan web
 * tanpa ketahuan.
 *
 * PRODUKSI DITURUNKAN, BUKAN DICATAT — dan itulah godaan terbesar di sini.
 * `clarification_details` tidak punya kolom produksi sama sekali:
 * production.total_ton adalah SUM(pure_oil_production_rate_ton_hour) yang
 * dihitung SERVER, karena satu pembacaan laju berlaku untuk satu jam pada
 * kisi jam kanonis. Menjumlahkan `daily[].rate_avg` di sini akan
 * menghasilkan angka yang MIRIP tetapi tidak sama — rata-rata harian punya
 * penyebutnya sendiri — dan perbedaannya tidak akan pernah terlihat sampai
 * seseorang membandingkannya dengan laporan web.
 *
 * DOWNTIME TIDAK MENGURANGI PRODUKSI (diputuskan 2026-09-25). Laju yang
 * diinput Operator dibaca sebagai laju RATA-RATA sepanjang jam itu,
 * sehingga downtime sudah tercermin di dalamnya; mengurangkannya lagi
 * menghitung ganda. Keduanya diteruskan berdampingan sebagai dua angka
 * terpisah, dan repo ini tidak pernah menyentuh salah satunya dengan yang
 * lain.
 *
 * SETIAP METRIK PUNYA PENYEBUTNYA SENDIRI. `metrics` membawa enam entri,
 * masing-masing dengan reading_count-nya sendiri. Repo TIDAK PERNAH
 * menurunkan satu reading_count bersama dari total.reading_rows — satu
 * baris pengukuran dapat mengisi laju dan mengosongkan suhu sludge,
 * sehingga satu penyebut bersama akan mengempiskan metrik yang jarang
 * diisi sambil tetap menghasilkan angka yang terlihat masuk akal.
 *
 * NULL BUKAN NOL. production.total_ton, downtime.total_mins,
 * metrics[*].min/avg/max, dan seluruh kolom *_avg pada `daily` dan
 * `by_unit` diteruskan apa adanya. Mengoersinya menjadi 0 (atau '' / '-')
 * di sini akan mengubah "tidak pernah diukur" menjadi "nilainya nol" — dua
 * fakta yang berbeda, dan yang satu menyesatkan. Khusus downtime,
 * pasangan (total_mins, reading_count) adalah SATU-SATUNYA pembeda antara
 * "tidak pernah berhenti" (0 dengan reading_count > 0) dan "tidak pernah
 * tercatat" (null dengan reading_count 0). Penerjemahan null menjadi teks
 * yang terbaca adalah urusan view, bukan repo.
 *
 * GALAT DITERUSKAN APA ADANYA. Tidak ada try/catch dan tidak ada kelas
 * galat khusus di sini. Perlu dicatat bentuknya: interceptor apiClient
 * menolak lewat normalizeError yang mengembalikan objek DATAR
 * { message, errors?, status? } dan MEMBUANG `response` — jadi `.response`
 * tidak pernah ada pada galat yang sampai ke pemanggil. 401 diterjemahkan
 * menjadi "arahkan ke Login" oleh LaporanClarificationView (membaca
 * `error.status`), dan kegagalan transport (penolakan tanpa `status`)
 * menjadi pesan + tombol Coba Lagi di sana pula.
 *
 * GAGAL TERTUTUP PADA business_unit_id: parameter ini hanya dikirim bila
 * pemanggil menyatakan dirinya Admin (`isAdmin: true`). Bagi Operator,
 * Supervisor, dan Mill Management mill diambil dari akun di sisi server
 * (ClarificationReportService::resolveBusinessUnit), sehingga sebuah
 * business_unit_id yang dipaksakan pemanggil TIDAK PERNAH ikut terkirim —
 * dijaga di sini, bukan hanya di view, supaya tidak bergantung pada
 * kedisiplinan pemanggil.
 */

export interface ClarificationReportBusinessUnitOption {
  id: string
  name: string
}

export interface ClarificationReportPeriodOption {
  id: string
  name: string
  start_date: string
  end_date: string
  /**
   * Status STASIUN LAYAR INI di dalam periode itu (baris period_stations),
   * BUKAN status periode: sejak 2026-09-25 periode tidak punya status
   * sendiri karena stasiun tidak ditutup serentak.
   */
  status: string
  /**
   * Selalu terisi 'clarification' — TIDAK pernah null. Sejak 2026-09-25 cakupan
   * "semua stasiun" tidak lagi diwujudkan sebagai station_type NULL
   * melainkan sebagai satu baris period_stations per jenis stasiun, jadi
   * server hanya memulangkan periode yang punya baris untuk jenis ini.
   */
  station_type: string
  station_type_label: string
}

export interface ClarificationReportPeriodHeader {
  id: string
  name: string
  start_date: string
  end_date: string
  /**
   * Status STASIUN LAYAR INI di dalam periode itu, bukan status periode.
   * Bentuk datanya tidak berubah sejak 2026-09-25, hanya artinya.
   */
  status: string
  business_unit_name?: string
}

export interface ClarificationReportBusinessUnitRef {
  id: string
  name: string
}

/**
 * Kelengkapan pencatatan adalah ISI laporan, bukan metadata — dan di layar
 * ini ia menopang beban, bukan sekadar memberi keterangan: produksi
 * DITURUNKAN dari pembacaan yang ada, sehingga pencatatan yang bolong
 * langsung menurunkan angka produksinya. coverage_percent dibulatkan
 * SERVER ke dua desimal (9 dari 144 = 6,25%, bukan 6,3);
 * menghitungnya ulang di sini akan menghasilkan angka yang berbeda dari
 * laporan web.
 */
export interface ClarificationReportCoverage {
  filled_slots: number
  expected_slots: number
  coverage_percent: number
  unit_count: number
  slots_per_unit_per_day: number
  days_in_period: number
  /**
   * Hari yang dipakai penyebut expected_slots — sama dengan days_in_period
   * untuk periode yang sudah selesai, berhenti di HARI INI (WIB) untuk
   * periode yang masih berjalan, 0 untuk periode yang belum mulai (temuan
   * audit 2026-10-04 #3, App\Support\ReportPeriodDays).
   */
  days_counted: number
  /** true bila tanggal akhir periode masih setelah hari ini. */
  period_running: boolean
}

/**
 * Produksi minyak murni — DITURUNKAN server dari laju per jam, tidak
 * pernah dicatat. total_ton null berarti laju tidak pernah tercatat sama
 * sekali; itu BUKAN produksi nol. reading_count adalah jumlah pembacaan
 * laju yang mendasarinya dan wajib tampil berdampingan dengan angkanya —
 * produksi dari 40 pembacaan dan dari 400 pembacaan tidak boleh terlihat
 * sama meyakinkan.
 *
 * avg_production_per_day_ton adalah ALIAS server untuk avg_per_day_ton
 * (satu nilai, dua nama). Repo meneruskan keduanya apa adanya dan tidak
 * menurunkan yang satu dari yang lain.
 */
export interface ClarificationReportProduction {
  total_ton: number | null
  avg_per_day_ton: number | null
  avg_production_per_day_ton: number | null
  reading_count: number
  avg_rate_ton_hour: number | null
  min_rate_ton_hour: number | null
  max_rate_ton_hour: number | null
}

/**
 * Downtime — KONTEKS di samping produksi, bukan pengurangnya.
 *
 * total_mins null (dengan reading_count 0) berarti tidak pernah tercatat;
 * 0 (dengan reading_count > 0) berarti stasiun tidak pernah berhenti.
 * Menyatukan keduanya akan menerbitkan angka keandalan yang tidak pernah
 * diukur. hours_with_downtime menghitung jam yang downtime-nya positif,
 * terpisah dari BESARNYA downtime.
 */
export interface ClarificationReportDowntime {
  total_mins: number | null
  avg_per_day_mins: number | null
  avg_downtime_per_day_mins: number | null
  hours_with_downtime: number
  reading_count: number
}

/**
 * min dan max berasal dari PEMBACAAN MENTAH per slot waktu, sementara
 * kolom *_avg pada `daily` adalah rata-rata HARIAN. Keduanya sengaja
 * berbeda dan TIDAK dapat direkonsiliasi. Repo tidak menyentuh keduanya.
 *
 * reading_count adalah penyebut metrik INI SENDIRI. null/null/null dengan
 * reading_count 0 berarti metrik itu tidak pernah diisi — bukan 0/0/0.
 */
export interface ClarificationReportMetric {
  min: number | null
  avg: number | null
  max: number | null
  reading_count: number
}

/** Keenam kolom numerik Clarification, urutan mengikuti NUMERIC_METRICS di server. */
export type ClarificationReportMetricKey =
  | 'pure_oil_production_rate_ton_hour'
  | 'clarification_tank_temp_c'
  | 'oil_tank_temperature_c'
  | 'sludge_tank_temp_c'
  | 'buffer_tank_level_percent'
  | 'downtime_mins'

export type ClarificationReportMetrics = Record<
  ClarificationReportMetricKey,
  ClarificationReportMetric
>

/**
 * Satu entri per TANGGAL yang punya minimal satu record. Kolom *_avg di
 * sini adalah rata-rata HARIAN dengan penyebut hariannya sendiri, dan null
 * berarti metrik itu kosong sepanjang tanggal itu — tanggalnya TETAP
 * terhitung sebagai tanggal ber-record. Repo tidak membuang baris bernilai
 * nol maupun baris ber-null: justru baris itu yang harus terbaca.
 *
 * KETIGA SUHU TANGKI DIKIRIM PADA SATU BARIS per tanggal, dengan sengaja:
 * selisih antar tangki itulah yang menunjukkan apakah pemisahan berjalan
 * sebagaimana mestinya, dan itu hanya terbaca bila ketiganya digambar pada
 * satu sumbu.
 */
export interface ClarificationReportDailyRow {
  date: string
  filled_slots: number
  production_ton: number | null
  rate_avg: number | null
  rate_reading_count: number
  clarification_tank_temp_avg: number | null
  oil_tank_temperature_avg: number | null
  sludge_tank_temp_avg: number | null
  buffer_tank_level_avg: number | null
  downtime_mins: number | null
}

/** Rekap per unit Clarification. Unit tanpa pembacaan tetap hadir dengan reading_count 0. */
export interface ClarificationReportUnitRow {
  clarification_id: string
  reading_count: number
  production_ton: number | null
  rate_avg: number | null
  rate_reading_count: number
  clarification_tank_temp_avg: number | null
  oil_tank_temperature_avg: number | null
  sludge_tank_temp_avg: number | null
  downtime_mins: number | null
}

export interface ClarificationReportTotal {
  days_with_records: number
  reading_rows: number
}

/**
 * Blok `production_line` pada respons /summary — KUNCI BARU (2026-09-28),
 * ADITIF. Bernilai null ketika permintaan tidak membawa production_line_id,
 * atau ketika line yang dikirim bukan milik mill yang berlaku. Tidak satu
 * pun kunci lama berubah nama, bentuk, maupun urutan karena kunci ini ada.
 *
 * Ini SATU-SATUNYA sumber nama Production Line yang server akui untuk
 * angka yang sedang ditampilkan — layar boleh menampilkan nama dari
 * daftarnya sendiri sebagai cadangan, tetapi nilai inilah yang benar-benar
 * menyertai angkanya.
 */
export interface ClarificationReportProductionLineRef {
  id: string
  name: string
}

export interface ClarificationReportSummary {
  period: ClarificationReportPeriodHeader | null
  production_line: ClarificationReportProductionLineRef | null
  business_unit: ClarificationReportBusinessUnitRef | null
  /**
   * Penanda milik SERVER (filled_slots > 0). Membedakan "tidak ada yang
   * bisa dilaporkan" dari "angkanya kebetulan nol", dan dipakai layar untuk
   * menolak menggambar grafik kosong yang akan terbaca sebagai garis datar
   * hasil pengukuran. TIDAK diturunkan di sini dari panjang `daily` maupun
   * dari `total`.
   */
  has_data: boolean
  coverage: ClarificationReportCoverage
  production: ClarificationReportProduction
  downtime: ClarificationReportDowntime
  metrics: ClarificationReportMetrics
  daily: ClarificationReportDailyRow[]
  by_unit: ClarificationReportUnitRow[]
  total: ClarificationReportTotal
}

/**
 * Parameter opsional pemilih mill. `isAdmin` sengaja WAJIB dinyatakan
 * eksplisit oleh pemanggil yang hendak mengirim business_unit_id — lihat
 * catatan "gagal tertutup" pada docblock berkas.
 */
export interface ClarificationReportScope {
  isAdmin?: boolean
  businessUnitId?: string | null
  /**
   * Production Line yang angkanya diminta. Dikirim ke /summary dan /export
   * saja — lihat productionLineParams(). Tanpa nilai ini repo tidak
   * mengirim parameternya sama sekali, dan jawaban server identik dengan
   * sebelum fitur Production Line ada.
   */
  productionLineId?: string | null
}

/**
 * Nilai bawaan HANYA dipakai ketika seluruh BLOK tidak ada pada respons
 * (mis. bentuk respons berubah di server), bukan untuk menambal medan yang
 * dikirim null. Periode tanpa data tetap mengirim blok lengkap berisi
 * null/nol, dan blok itulah yang diteruskan.
 *
 * Ditulis sebagai literal — bukan dibangun dari daftar kunci lewat
 * reduce()/map() — supaya berkas ini tetap bebas dari operasi apa pun atas
 * angka laporan, termasuk yang sekadar terlihat seperti perhitungan.
 */
const EMPTY_METRIC: ClarificationReportMetric = {
  min: null,
  avg: null,
  max: null,
  reading_count: 0,
}

const EMPTY_METRICS: ClarificationReportMetrics = {
  pure_oil_production_rate_ton_hour: { ...EMPTY_METRIC },
  clarification_tank_temp_c: { ...EMPTY_METRIC },
  oil_tank_temperature_c: { ...EMPTY_METRIC },
  sludge_tank_temp_c: { ...EMPTY_METRIC },
  buffer_tank_level_percent: { ...EMPTY_METRIC },
  downtime_mins: { ...EMPTY_METRIC },
}

/**
 * Bawaan produksi memakai null, BUKAN 0 — blok yang hilang sama sekali
 * berarti tidak ada yang dapat dilaporkan, dan itu bukan produksi nol.
 */
const EMPTY_PRODUCTION: ClarificationReportProduction = {
  total_ton: null,
  avg_per_day_ton: null,
  avg_production_per_day_ton: null,
  reading_count: 0,
  avg_rate_ton_hour: null,
  min_rate_ton_hour: null,
  max_rate_ton_hour: null,
}

/** Sama seperti produksi: total_mins bawaan null, bukan 0. */
const EMPTY_DOWNTIME: ClarificationReportDowntime = {
  total_mins: null,
  avg_per_day_mins: null,
  avg_downtime_per_day_mins: null,
  hours_with_downtime: 0,
  reading_count: 0,
}

const EMPTY_COVERAGE: ClarificationReportCoverage = {
  filled_slots: 0,
  expected_slots: 0,
  coverage_percent: 0,
  unit_count: 0,
  slots_per_unit_per_day: 0,
  days_in_period: 0,
  days_counted: 0,
  period_running: false,
}

const EMPTY_TOTAL: ClarificationReportTotal = {
  days_with_records: 0,
  reading_rows: 0,
}

/**
 * business_unit_id HANYA dikirim untuk Admin. Untuk peran lain fungsi ini
 * mengembalikan objek kosong, apa pun yang dikirimkan pemanggil — kunci
 * kuncinya adalah medan itu ABSEN, bukan null dan bukan string kosong.
 * Operator ada di cabang terikat-mill ini, sama seperti Supervisor dan Mill
 * Management.
 */
function scopeParams(scope?: ClarificationReportScope): Record<string, string> {
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
 * production_line_id — PARAMETER BARU (2026-09-28), dikirim HANYA ke
 * /summary dan /export.
 *
 * TIDAK PERNAH ke /periods. Periode adalah milik MILL, bukan milik
 * Production Line (periods.business_unit_id, tanpa production_line_id), dan
 * backend pun tidak menerima parameter ini di sana. Menyaring daftar
 * periode per line akan mengarang penyempitan yang tidak ada di data.
 *
 * Berbeda dari scopeParams(), fungsi ini TIDAK bercabang berdasarkan peran:
 * production_line_id bukan kewenangan melainkan konteks angka, dan server
 * sudah mengabaikan line milik mill lain (laporan kosong, bukan 403). Yang
 * dijaga di sini hanya satu: nilai kosong tidak pernah dikirim sebagai
 * parameter kosong.
 */
function productionLineParams(scope?: ClarificationReportScope): Record<string, string> {
  const productionLineId = scope?.productionLineId

  if (!productionLineId) {
    return {}
  }

  return { production_line_id: productionLineId }
}

/**
 * Respons /summary dikirim controller TANPA pembungkus `data`
 * (`response()->json($this->service->summary($period))`), sementara
 * /periods dan /business-units/options MEMAKAI pembungkus itu. Kedua
 * bentuk diterima di sini supaya repo ini tidak pecah bila pembungkusnya
 * kelak diseragamkan di sisi server.
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
 * GET /api/clarification-reports/business-units/options — pemilih Mill,
 * ADMIN SAJA (server menjawab 403 untuk peran lain, termasuk Operator:
 * pelebaran screen-138 sengaja TIDAK menyentuh endpoint ini). View TIDAK
 * BOLEH memanggilnya untuk peran yang terikat mill: selain percuma,
 * permintaan itu akan membentuk daftar seluruh mill yang memang tidak
 * berhak dilihat.
 */
export async function fetchBusinessUnits(): Promise<ClarificationReportBusinessUnitOption[]> {
  const response = await apiClient.get('/api/clarification-reports/business-units/options')

  return (response.data?.data ?? []) as ClarificationReportBusinessUnitOption[]
}

/**
 * GET /api/clarification-reports/periods — periode yang mencakup stasiun
 * Clarification, yakni periode yang punya baris period_stations berjenis
 * 'clarification'. Penyaringannya dikerjakan SERVER; repo meneruskan
 * daftar apa adanya, dalam urutan yang sama — menyaringnya kedua kali di
 * sini akan menciptakan definisi cakupan yang kedua. Daftar kosong adalah jawaban yang sah
 * (HTTP 200 + []), bukan galat: mill itu memang belum punya periode, dan
 * layar menampilkannya sebagai arahan menghubungi Admin.
 */
export async function fetchPeriods(
  scope?: ClarificationReportScope,
): Promise<ClarificationReportPeriodOption[]> {
  const params = scopeParams(scope)

  const response = await apiClient.get('/api/clarification-reports/periods', { params })

  return (response.data?.data ?? []) as ClarificationReportPeriodOption[]
}

/**
 * GET /api/clarification-reports/summary — seluruh angka layar untuk satu
 * periode.
 *
 * Setiap blok diteruskan APA ADANYA, tanpa satu operasi aritmetika pun:
 * tidak ada penjumlahan laju per jam menjadi produksi, tidak ada
 * pengurangan downtime dari produksi, tidak ada pembulatan ulang
 * coverage_percent atau avg_per_day, tidak ada perataan suhu, tidak ada
 * penurunan has_data, dan tidak ada pengurutan atau penyaringan `daily` /
 * `by_unit` (termasuk baris bernilai nol dan unit ber-reading_count 0, yang
 * justru harus terbaca). Nilai bawaan di atas hanya berlaku bila BLOK-nya
 * tidak ada sama sekali pada respons.
 */
export async function fetchSummary(
  periodId: string,
  scope?: ClarificationReportScope,
): Promise<ClarificationReportSummary> {
  const response = await apiClient.get('/api/clarification-reports/summary', {
    params: { period_id: periodId, ...scopeParams(scope), ...productionLineParams(scope) },
  })

  const body = (unwrap<Record<string, unknown>>(response.data) ?? {}) as Record<string, unknown>

  return {
    period: (body.period ?? null) as ClarificationReportPeriodHeader | null,
    production_line: (body.production_line ?? null) as ClarificationReportProductionLineRef | null,
    business_unit: (body.business_unit ?? null) as ClarificationReportBusinessUnitRef | null,
    has_data: (body.has_data ?? false) as boolean,
    coverage: (body.coverage ?? { ...EMPTY_COVERAGE }) as ClarificationReportCoverage,
    production: (body.production ?? { ...EMPTY_PRODUCTION }) as ClarificationReportProduction,
    downtime: (body.downtime ?? { ...EMPTY_DOWNTIME }) as ClarificationReportDowntime,
    metrics: (body.metrics ?? { ...EMPTY_METRICS }) as ClarificationReportMetrics,
    daily: (body.daily ?? []) as ClarificationReportDailyRow[],
    by_unit: (body.by_unit ?? []) as ClarificationReportUnitRow[],
    total: (body.total ?? { ...EMPTY_TOTAL }) as ClarificationReportTotal,
  }
}

/**
 * GET /api/clarification-reports/export?period_id=...&format=csv — respons
 * streaming text/csv, BUKAN JSON, jadi responseType-nya blob. Isinya (satu
 * baris per slot waktu, dengan kolom konteks record diulang dan kolom
 * findings muncul verbatim) dibentuk SERVER dan sama persis dengan ekspor
 * laporan versi web, karena endpoint-nya memang sama. Repo tidak pernah
 * menyusun CSV dari angka yang sedang tampil di layar.
 */
export async function exportCsv(periodId: string, scope?: ClarificationReportScope): Promise<Blob> {
  const response = await apiClient.get('/api/clarification-reports/export', {
    params: { period_id: periodId, format: 'csv', ...scopeParams(scope), ...productionLineParams(scope) },
    responseType: 'blob',
  })

  return response.data as Blob
}

/**
 * Mekanisme penyimpanan berkas — dipisah dari exportCsv() supaya
 * pengambilan data (yang perlu diuji atas query-nya) dan penyimpanan berkas
 * (yang perlu diuji atas pemanggilannya) dapat diamati sendiri-sendiri.
 * Memakai anchor + object URL, konvensi web standar yang juga berlaku di
 * WebView Capacitor.
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

export const clarificationReportRepo = {
  fetchBusinessUnits,
  fetchPeriods,
  fetchSummary,
  exportCsv,
  saveCsvFile,
}

export default clarificationReportRepo
