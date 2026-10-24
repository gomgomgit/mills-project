import apiClient from '@/services/apiClient'
import { productionLineRepo, type ProductionLineOption } from '@/services/productionLineRepo'

/**
 * cpoDispatchReportRepo — screen-167--laporan-cpo-dispatch-mobile /
 * usecase-173--laporan-cpo-dispatch-mobile "Lihat Laporan Periode CPO Dispatch
 * (Mobile)".
 *
 * Kembaran mobile ketujuh belas, pasangan screen-166. Satu berkas repo tipis di
 * atas apiClient, TANPA cache lokal: laporan ini MEMBUTUHKAN jaringan, dan
 * kegagalan jaringan adalah kondisi yang DITAMPILKAN, bukan disembunyikan di
 * balik data basi. Layar ini baca saja dan tidak menulis ke localDb.
 *
 * NOL PERUBAHAN BACKEND. Keempat endpoint /api/cpo-dispatch-reports/* sudah ada
 * dari screen-166 dan sudah menerima Operator sejak commit pertamanya;
 * /api/production-lines/options-for-report dipakai ulang verbatim dari
 * screen-135. Pola `isAdmin` wajib-eksplisit sama dengan kernelDispatchReportRepo:
 * business_unit_id hanya terkirim bila pemanggil menyatakan dirinya Admin.
 * `/business-units/options` ADMIN SAJA — view tidak boleh memanggilnya untuk
 * peran terikat mill.
 *
 * BODY /summary DITERUSKAN UTUH — nol kunci dibuang, nol angka diubah. Payload
 * ini satu-satunya yang membawa `turnaround` dan `hourly` pada laporan
 * per-transaksi dan satu-satunya yang menerbitkan EMPAT penyebut berbeda.
 * Repo ini TIDAK PERNAH menghitung waktu putar, sebaran jam, maupun
 * penyeberangan tengah malam — seluruhnya dihitung server; di sini hanya
 * diketik supaya yang tidak dapat ditulis tidak dapat salah dibaca.
 *
 * NULL BUKAN NOL: net_weight_*, min/avg/max keempat ukuran mutu, min/avg/max
 * waktu putar, busiest_hour, dan completeness.percent semuanya boleh null, dan
 * 0 adalah nilai SAH untuk waktu putar (jam masuk = jam keluar) maupun jam
 * tersibuk (pukul 00). Keputusan '—' ada di view, per medan.
 *
 * GALAT DITERUSKAN APA ADANYA (objek DATAR { message, errors?, status? } dari
 * apiClient.normalizeError); penerjemahannya ke keadaan layar ada di view.
 */

/* ------------------------------------------------------------------ */
/* Bentuk respons                                                      */
/* ------------------------------------------------------------------ */

/** Satu entri pemilih Mill — ADMIN SAJA (403 untuk ketiga peran lain). */
export interface CpoDispatchReportBusinessUnitOption {
  id: string
  name: string
}

export interface CpoDispatchReportPeriodOption {
  id: string
  name: string
  start_date: string
  end_date: string
  /** Status stasiun di dalam periode itu; TIDAK PERNAH menyaring daftar ini. */
  status: string
  station_type?: string
  station_type_label?: string
}

export interface CpoDispatchReportBusinessUnitRef {
  id: string
  name: string
}

export interface CpoDispatchReportProductionLineRef {
  id: string
  name: string
  code?: string | null
}

export interface CpoDispatchReportPeriodHeader {
  id: string
  name: string
  start_date: string
  end_date: string
  status: string
}

/**
 * Blok angka pokok — SATU PENGIRIMAN = SATU TANKER = SATU BARIS DETAIL.
 * dispatch_count (a) dan net_weight_dispatch_count (b) adalah dua penyebut yang
 * berbeda; tidak satu pun diturunkan di klien.
 */
export interface CpoDispatchReportDispatches {
  dispatch_count: number
  net_weight_total: number | null
  net_weight_avg: number | null
  net_weight_dispatch_count: number
  missing_net_weight_dispatch_count: number
}

/** SATU ukuran mutu DENGAN PENYEBUTNYA SENDIRI. null/null/null bila measured_count 0. */
export interface CpoDispatchReportQualityMeasure {
  min: number | null
  avg: number | null
  max: number | null
  measured_count: number
}

/** DOBI membawa prosanya DI DALAM bloknya sendiri — indeks tanpa satuan. */
export interface CpoDispatchReportDobiMeasure extends CpoDispatchReportQualityMeasure {
  _note?: string
}

/**
 * KEEMPAT UKURAN MUTU, dinamai PERSIS seperti CpoDispatchReportService::QUALITY_MEASURES.
 * `impurities_percent` dan `moisture_percent` — bukan nama yang dipendekkan.
 */
export interface CpoDispatchReportQuality {
  ffa_percent: CpoDispatchReportQualityMeasure
  moisture_percent: CpoDispatchReportQualityMeasure
  impurities_percent: CpoDispatchReportQualityMeasure
  dobi: CpoDispatchReportDobiMeasure
  /** Prosa SERVER yang menyatakan keempat penyebut boleh berbeda; dirender UTUH. */
  _note?: string
}

/** Kunci keempat ukuran mutu — dipakai view untuk me-loop. */
export type CpoDispatchReportQualityKey = Exclude<keyof CpoDispatchReportQuality, '_note'>

/**
 * WAKTU PUTAR TANKER — seluruhnya dihitung SERVER. Jam dicatat tanpa tanggal;
 * jam keluar < jam masuk dibaca hari berikutnya, dan jumlahnya diterbitkan di
 * crossed_midnight_count. 0 menit adalah nilai SAH (bukan ketiadaan).
 * measured_count adalah PENYEBUT (d); measured_count + uncomputable_count =
 * dispatch_count.
 */
export interface CpoDispatchReportTurnaround {
  min_minutes: number | null
  avg_minutes: number | null
  max_minutes: number | null
  measured_count: number
  crossed_midnight_count: number
  uncomputable_count: number
  _note?: string
}

/** Satu kelompok jam MASUK. hour 0..23; urutan milik server. */
export interface CpoDispatchReportHourlyBucket {
  hour: number
  dispatch_count: number
  net_weight_total: number | null
}

/**
 * SEBARAN menurut JAM MASUK. busiest_hour NULL (bukan 0) bila tak satu jam pun
 * tercatat — jam 0 adalah jam yang SAH. recorded_count adalah PENYEBUT (c);
 * penjumlahan buckets + not_recorded_count = dispatch_count.
 */
export interface CpoDispatchReportHourly {
  buckets: CpoDispatchReportHourlyBucket[]
  not_recorded_count: number
  recorded_count: number
  busiest_hour: number | null
  empty_hour_count: number
  _note?: string
}

/**
 * Satu baris rekap kategoris teks bebas. `value` null adalah KELOMPOKNYA SENDIRI
 * ('tidak tercatat') dan tidak pernah dibuang. Urutan baris milik server.
 */
export interface CpoDispatchReportBreakdownRow {
  value: string | null
  dispatch_count: number
  net_weight_total: number | null
  net_weight_dispatch_count: number
  net_weight_avg: number | null
}

/** TIGA penghitung kehadiran; segel atas dan bawah TIDAK PERNAH dijumlahkan. */
export interface CpoDispatchReportCompliance {
  missing_waybill_count: number
  missing_seal_top_count: number
  missing_seal_bottom_count: number
  _note?: string
}

export interface CpoDispatchReportDailyRow {
  date: string
  dispatch_count: number
  net_weight_total: number | null
  net_weight_dispatch_count: number
  net_weight_avg: number | null
}

/** Kaki rekap harian: dijumlahkan SERVER, dibaca apa adanya oleh klien. */
export interface CpoDispatchReportDailyTotal {
  dispatch_count: number
  net_weight_total: number | null
  net_weight_dispatch_count: number
  net_weight_avg: number | null
}

/** Kelengkapan dalam HARI; percent null (bukan 0) bila days_counted 0. */
export interface CpoDispatchReportCompleteness {
  days_in_period: number
  days_with_dispatch: number
  days_counted: number
  percent: number | null
}

export interface CpoDispatchReportTextEntry {
  date: string | null
  value: string
}

/** SATU DAFTAR, BUKAN DUA — stasiun ini hanya punya `findings`. */
export interface CpoDispatchReportTextEntries {
  findings: CpoDispatchReportTextEntry[]
}

/** Seluruh angka layar untuk satu periode pada satu Production Line. */
export interface CpoDispatchReportSummary {
  business_unit?: CpoDispatchReportBusinessUnitRef | null
  production_line?: CpoDispatchReportProductionLineRef | null
  period?: CpoDispatchReportPeriodHeader | null
  dispatches?: CpoDispatchReportDispatches
  quality?: CpoDispatchReportQuality
  turnaround?: CpoDispatchReportTurnaround
  hourly?: CpoDispatchReportHourly
  _note_four_denominators?: string
  by_transport_company?: CpoDispatchReportBreakdownRow[]
  by_storage_tank_source?: CpoDispatchReportBreakdownRow[]
  by_destination_buyer?: CpoDispatchReportBreakdownRow[]
  compliance?: CpoDispatchReportCompliance
  draft_dispatch_count?: number
  daily?: CpoDispatchReportDailyRow[]
  daily_total?: CpoDispatchReportDailyTotal
  completeness?: CpoDispatchReportCompleteness
  text_entries?: CpoDispatchReportTextEntries
  _note_no_labeled_recap?: string
  _note_no_quality_thresholds?: string
  _note_shift_not_published?: string
}

export interface CpoDispatchReportScope {
  isAdmin?: boolean
  businessUnitId?: string | null
  /**
   * Production Line yang angkanya diminta. Dikirim ke /summary dan /export
   * saja. WAJIB di layar ini: tanpa nilai ini server menjawab 422 (dan SENGAJA
   * tidak memulangkan angka seluruh mill), dan view memang tidak memanggil
   * /summary sebelum sebuah line berlaku — penjagaannya ada di FUNGSI
   * pemuatan, bukan hanya di template.
   */
  productionLineId?: string | null
}

/**
 * Bentuk pemanggilan OBJEK TUNGGAL (`fetchSummary({ periodId,
 * productionLineId })`) di samping bentuk `(periodId, scope)` yang dipakai
 * kelima belas repo saudaranya. Satu implementasi, dua cara memanggil; bukan
 * dua implementasi.
 */
export interface CpoDispatchReportQuery {
  periodId: string
  productionLineId?: string | null
  isAdmin?: boolean
  businessUnitId?: string | null
}

/* ------------------------------------------------------------------ */
/* Jalur URL — SATU definisi masing-masing                             */
/* ------------------------------------------------------------------ */

/**
 * KELIMA jalur yang layar ini sentuh, dan tidak ada yang keenam.
 *
 * LINES_PATH TIDAK DIDUPLIKASI DI BAWAH PREFIX cpo-dispatch-reports: ia
 * endpoint screen-135 yang dipakai ulang verbatim, dan permintaannya pun tetap
 * dikerjakan productionLineRepo (lihat fetchLines).
 */
export const BUSINESS_UNITS_PATH = '/api/cpo-dispatch-reports/business-units/options'
export const PERIODS_PATH = '/api/cpo-dispatch-reports/periods'
export const SUMMARY_PATH = '/api/cpo-dispatch-reports/summary'
export const EXPORT_PATH = '/api/cpo-dispatch-reports/export'
export const LINES_PATH = '/api/production-lines/options-for-report'

/* ------------------------------------------------------------------ */
/* Pembentuk parameter                                                 */
/* ------------------------------------------------------------------ */

/** Menyatukan kedua bentuk pemanggilan menjadi satu objek pertanyaan. */
function queryOf(
  period: string | CpoDispatchReportQuery,
  scope?: CpoDispatchReportScope,
): CpoDispatchReportQuery {
  if (typeof period === 'string') {
    return {
      periodId: period,
      productionLineId: scope?.productionLineId ?? null,
      isAdmin: scope?.isAdmin ?? false,
      businessUnitId: scope?.businessUnitId ?? null,
    }
  }

  return {
    periodId: period.periodId,
    productionLineId: period.productionLineId ?? scope?.productionLineId ?? null,
    isAdmin: period.isAdmin ?? scope?.isAdmin ?? false,
    businessUnitId: period.businessUnitId ?? scope?.businessUnitId ?? null,
  }
}

/**
 * business_unit_id HANYA untuk Admin. Untuk ketiga peran terikat mill fungsi
 * ini mengembalikan objek KOSONG apa pun yang dikirimkan pemanggil — kuncinya
 * ABSEN, bukan null dan bukan string kosong. Operator ada di cabang
 * terikat-mill ini, sama seperti Supervisor dan Mill Management.
 */
function millParams(scope?: {
  isAdmin?: boolean
  businessUnitId?: string | null
}): Record<string, string> {
  if (!scope?.isAdmin) {
    return {}
  }

  const businessUnitId = scope.businessUnitId

  if (!businessUnitId) {
    // Admin yang belum memilih mill: repo tidak mengarang nilai dan tidak
    // melempar galat sendiri — server menjawab 422 "Pilih mill terlebih
    // dahulu", dan satu sumber kebenaran itulah yang ditampilkan. (View pun
    // tidak memanggil /summary pada keadaan ini.)
    return {}
  }

  return { business_unit_id: businessUnitId }
}

/**
 * Parameter /summary: period_id + production_line_id, ditambah business_unit_id
 * HANYA untuk Admin.
 *
 * Nilai line kosong TIDAK pernah menjadi parameter kosong: server akan
 * menjawab 422 tentang parameter yang HILANG, dan itu pesan yang benar,
 * sedangkan parameter kosong adalah pesan yang membingungkan.
 */
function summaryParams(query: CpoDispatchReportQuery): Record<string, string> {
  const params: Record<string, string> = { period_id: query.periodId }

  if (query.productionLineId) {
    params.production_line_id = query.productionLineId
  }

  return { ...params, ...millParams(query) }
}

/**
 * Parameter /export — parameter /summary ditambah `format` yang SELALU 'csv',
 * dikirim EKSPLISIT alih-alih bersandar pada bawaan server.
 *
 * TIDAK ADA JALUR KODE YANG DAPAT MENYUSUN FORMAT LAIN. Endpoint-nya memang
 * mendukung 'xlsx' (SUPPORTED_FORMATS = ['csv', 'xlsx']), tetapi perangkat
 * lapangan tidak punya pembaca .xlsx yang dapat diandalkan sementara CSV dapat
 * dibuka di mana pun dan dikirim lewat pesan — jadi tombolnya pun tidak
 * dirender, dan nilai 'xlsx' tidak muncul di berkas ini maupun di view.
 */
export function exportParams(
  period: string | CpoDispatchReportQuery,
  scope?: CpoDispatchReportScope,
): Record<string, string> {
  return { ...summaryParams(queryOf(period, scope)), format: 'csv' }
}

/* ------------------------------------------------------------------ */
/* Pengambilan                                                         */
/* ------------------------------------------------------------------ */

/**
 * GET /api/cpo-dispatch-reports/business-units/options — pemilih Mill,
 * ADMIN SAJA.
 *
 * Server menjawab 403 untuk ketiga peran lain, Operator TERMASUK; penolakannya
 * diangkat di dalam CpoDispatchReportService::businessUnitOptions(), bukan
 * oleh middleware rute. View TIDAK BOLEH memanggilnya untuk peran yang terikat
 * mill — lihat docblock berkas.
 *
 * Master kosong adalah jawaban SAH: { data: [] } dengan HTTP 200, bukan 404.
 */
export async function fetchBusinessUnits(): Promise<CpoDispatchReportBusinessUnitOption[]> {
  const response = await apiClient.get(BUSINESS_UNITS_PATH)

  return (response.data?.data ?? []) as CpoDispatchReportBusinessUnitOption[]
}

/**
 * GET /api/production-lines/options-for-report — daftar Production Line untuk
 * pemilih WAJIB di layar ini.
 *
 * MENDELEGASIKAN ke productionLineRepo.fetchProductionLinesForReport(): URL-nya
 * tetap DIDEFINISIKAN SATU KALI di repo itu, endpoint screen-135 ini tidak
 * diduplikasi di bawah prefix cpo-dispatch-reports, dan tidak ada pemeta
 * baris kedua di sini (bentuk {id, name, code} adalah keputusan sisi server).
 *
 * business_unit_id diteruskan HANYA untuk Admin — untuk peran lain `null`
 * dikirim eksplisit, dan repo itu pun tidak menuliskan parameternya. Memanggil
 * /api/production-lines/current atas nama Admin akan memulangkan line milik
 * mill AKUN PEMANGGIL (Admin tidak terikat mill), yaitu bentuk kesalahan
 * paling berbahaya di layar laporan: angka yang terlihat sah untuk line yang
 * salah.
 */
export async function fetchLines(
  scope?: CpoDispatchReportScope,
): Promise<ProductionLineOption[]> {
  const params = millParams(scope)

  return productionLineRepo.fetchProductionLinesForReport(params.business_unit_id ?? null)
}

/**
 * GET /api/cpo-dispatch-reports/periods — periode yang mencakup stasiun
 * CPO Dispatch, yakni periode yang punya baris period_stations berjenis
 * 'cpo-dispatch' (DENGAN TANDA HUBUNG; server membaca nilainya dari
 * App\Enums\StationType alih-alih menuliskannya, karena garis bawah di situ
 * tidak akan cocok dengan satu baris pun dan menjawab [] untuk setiap mill
 * tanpa satu galat).
 *
 * `business_unit_id` WAJIB bagi Admin dan DIABAIKAN bagi ketiga peran terikat
 * mill — jadi klien tidak mengirimkannya untuk mereka. `production_line_id`
 * sengaja BUKAN parameter di sini: periode milik MILL, bukan milik line; yang
 * disaring sebuah line adalah DATANYA.
 *
 * Penyaringannya dikerjakan SERVER; repo meneruskan daftar apa adanya, dalam
 * urutan yang sama. Daftar kosong adalah jawaban yang sah (HTTP 200 + []),
 * bukan galat. Periode TERTUTUP tetap terdaftar — status mengatur penulisan
 * data, bukan pembacaan laporan.
 */
export async function fetchPeriods(
  scope?: CpoDispatchReportScope,
): Promise<CpoDispatchReportPeriodOption[]> {
  const response = await apiClient.get(PERIODS_PATH, { params: millParams(scope) })

  return (response.data?.data ?? []) as CpoDispatchReportPeriodOption[]
}

/**
 * Respons /summary dikirim controller TANPA pembungkus `data`
 * (`response()->json($this->service->buildSummary(...))`), sementara /periods
 * dan /business-units/options MEMAKAI pembungkus itu. Kedua bentuk diterima di
 * sini supaya repo ini tidak pecah bila pembungkusnya kelak diseragamkan di
 * sisi server.
 *
 * Payload yang tidak dikenali (string, angka) memulangkan null alih-alih
 * melempar, sehingga view dapat menampilkan keadaan kosong.
 */
function unwrap<T>(payload: unknown): T | null {
  if (payload === null || payload === undefined) {
    return null
  }

  if (typeof payload !== 'object') {
    return null
  }

  const body = payload as Record<string, unknown>

  if (body.data !== undefined && body.data !== null) {
    return body.data as T
  }

  return payload as T
}

/**
 * GET /api/cpo-dispatch-reports/summary — SELURUH angka layar untuk satu
 * periode pada satu Production Line, dan satu-satunya sumbernya.
 *
 * BODY-NYA DITERUSKAN UTUH: nol kunci dibuang, nol bawaan disuntikkan, nol
 * operasi aritmetika. Tidak ada penjumlahan baris rekap, tidak ada penurunan
 * missing_net_weight_dispatch_count dari dispatch_count −
 * net_weight_dispatch_count, tidak ada perata-rataan ulang netto, tidak ada
 * min/avg/max yang dihitung ulang, tidak ada penjumlahan daily[] menjadi
 * daily_total, tidak ada penurunan persen kelengkapan dari
 * days_with_dispatch/days_counted, tidak ada waktu putar, sebaran jam, maupun crossed_midnight_count yang
 * dihitung ulang dari jam-jam yang terlihat, tidak ada pengelompokan ulang nilai kategoris
 * (termasuk tidak menyatukan dua ejaan yang hanya berbeda besar-kecil huruf),
 * dan tidak ada pengurutan ulang rekap mana pun. Inilah yang menjamin angka
 * mobile SAMA PERSIS dengan angka web: keduanya membaca satu endpoint yang
 * sama, bukan menghitung dengan dua rumus — dan di layar ini bahaya itu LEBIH
 * BESAR daripada di saudara-saudaranya, karena min/avg/max tampak mudah
 * dihitung ulang dari angka yang sudah ada di tangan.
 *
 * Dapat dipanggil `fetchSummary('p-1', { productionLineId: 'l-1' })` maupun
 * `fetchSummary({ periodId: 'p-1', productionLineId: 'l-1' })`.
 */
export async function fetchSummary(
  period: string | CpoDispatchReportQuery,
  scope?: CpoDispatchReportScope,
): Promise<CpoDispatchReportSummary> {
  const response = await apiClient.get(SUMMARY_PATH, {
    params: summaryParams(queryOf(period, scope)),
  })

  return (unwrap<CpoDispatchReportSummary>(response.data) ??
    {}) as CpoDispatchReportSummary
}

/**
 * URL ekspor lengkap beserta query string-nya, dengan `format=csv` EKSPLISIT.
 *
 * Dibentuk dari exportParams() yang SAMA dengan yang dikirim exportCsv(),
 * sehingga tidak mungkin ada dua susunan parameter yang berbeda untuk satu
 * unduhan yang sama. Dipakai untuk memeriksa bentuk permintaan tanpa harus
 * mengambil berkasnya.
 */
export function buildExportUrl(
  period: string | CpoDispatchReportQuery,
  scope?: CpoDispatchReportScope,
): string {
  return `${EXPORT_PATH}?${new URLSearchParams(exportParams(period, scope)).toString()}`
}

/**
 * GET /api/cpo-dispatch-reports/export?period_id=…&production_line_id=…&format=csv
 * — respons streaming text/csv, BUKAN JSON, jadi responseType-nya blob.
 *
 * Isinya dibentuk SERVER dan sama persis dengan ekspor laporan versi web,
 * karena endpoint-nya memang sama: SATU BARIS PER PENGIRIMAN, dengan ENAM
 * kolom konteks (Periode, Rentang Periode, Mill, Production Line, Tanggal
 * Record, Status) diulang verbatim di setiap baris, disusul KEDUA PULUH SATU
 * kolom detail — dua puluh tujuh kolom seluruhnya, lihat
 * CpoDispatchReportService::EXPORT_HEADER. Enam di antaranya (`Shift`,
 * `Weighbridge Ticket No`, `Waybill Number`, `Vehicle Plate No`, `Driver Name`
 * dan `Weighbridge Operator ID`) IKUT di berkas meski tidak muncul di satu
 * angka pun di layar: ekspor adalah rincian MENTAH, jadi pengecualian sebuah
 * kolom dari layar tidak berarti kolom itu dibuang dari berkas. Pengiriman
 * ber-netto NULL tetap sebuah baris dengan sel KOSONG — tidak pernah dibuang
 * dan tidak pernah ditulis 0.
 *
 * ENAM KOLOM KONTEKS, BUKAN LIMA seperti kelima belas laporan sebelumnya:
 * layar ini menuntut Rentang Periode ikut sebagai kolom konteks, dan rentang
 * itu tidak dapat diturunkan dari nama periode (nama periode teks bebas yang
 * tidak dijamin memuat tanggalnya). Stub uji yang menyalin bentuk ekspor
 * laporan LAIN karena itu akan berjumlah kolom salah.
 *
 * 422 (parameter wajib absen, atau batas 50.000 baris pengiriman terlampaui)
 * diteruskan apa adanya ke view, yang menampilkan pesan server tanpa mencoba
 * memotong, memecah, atau mengulang permintaan dengan rentang lebih kecil.
 */
export async function exportCsv(
  period: string | CpoDispatchReportQuery,
  scope?: CpoDispatchReportScope,
): Promise<Blob> {
  const response = await apiClient.get(EXPORT_PATH, {
    params: exportParams(period, scope),
    responseType: 'blob',
  })

  return response.data as Blob
}

/**
 * Mekanisme penyimpanan berkas — dipisah dari exportCsv() supaya pengambilan
 * data (yang perlu diuji atas query-nya) dan penyimpanan berkas (yang perlu
 * diuji atas pemanggilannya) dapat diamati sendiri-sendiri. Memakai anchor +
 * object URL, konvensi web standar yang juga berlaku di WebView Capacitor —
 * POLA YANG SAMA dengan kelima belas kembaran mobile sebelumnya; tidak ada
 * mekanisme unduhan baru yang dikarang di sini.
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

export const cpoDispatchReportRepo = {
  fetchBusinessUnits,
  fetchLines,
  fetchPeriods,
  fetchSummary,
  buildExportUrl,
  exportParams,
  exportCsv,
  saveCsvFile,
}

export default cpoDispatchReportRepo
