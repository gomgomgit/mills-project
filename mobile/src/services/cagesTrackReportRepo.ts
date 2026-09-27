import apiClient from '@/services/apiClient'

/**
 * cagesTrackReportRepo — screen-136--laporan-cages-track-mobile /
 * usecase-136--laporan-cages-track-mobile "Lihat Laporan Periode Cages &
 * Tracks (Mobile)".
 *
 * Kembaran persis sterilizerReportRepo.ts (screen-135): satu berkas repo
 * tipis di atas apiClient, tanpa cache lokal. Layar laporan ini memang
 * MEMBUTUHKAN jaringan (business_rules: "Layar ini membutuhkan jaringan"),
 * jadi tidak ada jalur SQLite offline di sini sama sekali — kegagalan
 * jaringan adalah kondisi yang ditampilkan ke pengguna, bukan yang
 * disembunyikan di balik data basi.
 *
 * NOL ENDPOINT BARU. Keempat endpoint milik
 * screen-130--laporan-cages-track-web dipakai ulang apa adanya
 * (App\Http\Controllers\Api\CagesTrackReportController). Perubahan backend
 * untuk layar ini hanya soal SIAPA yang boleh memanggil: guard rute
 * menjadi 'auth:web,sanctum' + peran `operator`, guardAccess() menerima
 * UserRole::Operator, dan — yang paling mudah terlewat —
 * resolveBusinessUnit() memasukkan Operator ke cabang TERIKAT MILL, bukan
 * membiarkannya jatuh ke cabang Admin yang tidak terikat.
 *
 * NOL PERHITUNGAN ULANG DI KLIEN. Berkas ini tidak pernah menjumlah,
 * merata-ratakan, mengurutkan, menyaring, atau menurunkan satu angka pun.
 * Seluruh nilai diteruskan apa adanya dari respons server, karena laporan
 * web memakai sumber yang sama (CagesTrackReportService) — perhitungan
 * kedua di sisi klien pasti akan menyimpang dari laporan web tanpa
 * ketahuan. Berbeda dari sterilizerReportRepo, di sini bahkan TIDAK ADA
 * normalizeKpi(): respons Cages & Tracks tidak punya penamaan ganda
 * (`*_minutes` vs polos) yang perlu diseragamkan, jadi menambahkan
 * pemetaan medan justru akan menciptakan tempat baru untuk salah.
 *
 * NULL BUKAN NOL. `avg_tippler_duration_hours`, `longest_gap_hours`,
 * `longest_gap_date`, `peak_hour`, dan seluruh medan nullable pada `daily`
 * / `queue` diteruskan apa adanya. Mengoersinya menjadi 0 (atau '' / '-')
 * di sini akan mengubah "tidak dapat dihitung" menjadi "tidak ada" — dua
 * fakta yang berbeda. Penerjemahan null menjadi teks yang terbaca adalah
 * urusan view, bukan repo.
 *
 * GALAT DITERUSKAN APA ADANYA. Tidak ada try/catch dan tidak ada kelas
 * galat khusus di sini: 401 diterjemahkan menjadi "arahkan ke Login" oleh
 * LaporanCagesTrackView, dan kegagalan transport (penolakan tanpa status)
 * menjadi pesan + tombol Coba Lagi di sana pula. Repo yang menelan galat
 * akan memaksa view menebak dari bentuk nilai kembalian.
 *
 * GAGAL TERTUTUP PADA business_unit_id: parameter ini hanya dikirim bila
 * pemanggil menyatakan dirinya Admin (`isAdmin: true`). Bagi Operator,
 * Supervisor, dan Mill Management mill diambil dari akun di sisi server
 * (CagesTrackReportService::resolveBusinessUnit), sehingga sebuah
 * business_unit_id yang dipaksakan pemanggil TIDAK PERNAH ikut terkirim —
 * dijaga di sini, bukan hanya di view, supaya tidak bergantung pada
 * kedisiplinan pemanggil.
 */

export interface CagesTrackReportBusinessUnitOption {
  id: string
  name: string
}

export interface CagesTrackReportPeriodOption {
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
   * Selalu terisi 'cages-track' — TIDAK pernah null. Sejak 2026-09-25 cakupan
   * "semua stasiun" tidak lagi diwujudkan sebagai station_type NULL
   * melainkan sebagai satu baris period_stations per jenis stasiun, jadi
   * server hanya memulangkan periode yang punya baris untuk jenis ini.
   */
  station_type: string
  station_type_label: string
}

export interface CagesTrackReportPeriodHeader {
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

/**
 * Bentuk KPI mengikuti CagesTrackReportService::summary() medan per medan.
 * Yang nullable di server nullable pula di sini — itulah seluruh maksud
 * dari deklarasi ini.
 */
export interface CagesTrackReportKpi {
  total_cages_tipped: number
  total_cages_out: number
  avg_cages_per_day: number
  /** null = tidak ada penumpahan sama sekali. 0 akan terbaca "puncak pukul 00.00". */
  peak_hour: number | null
  peak_hour_cages: number
  idle_operating_hours: number
  /** null = kurang dari 2 jam penumpahan berbeda, jadi jeda tidak terdefinisi. */
  longest_gap_hours: number | null
  longest_gap_date: string | null
  /** null = tidak satu tanggal pun punya jendela operasi yang dapat dihitung. */
  avg_tippler_duration_hours: number | null
  days_with_records: number
  days_without_valid_window: number
}

export interface CagesTrackReportHourlyRow {
  hour: number
  cages: number
  within_operating_window: boolean
}

export interface CagesTrackReportDailyRow {
  date: string
  cages_tipped: number
  cages_out: number
  operating_hours: number | null
  idle_operating_hours: number | null
  longest_gap_hours: number | null
  min_remaining: number | null
}

/**
 * Antrean lori tersisa adalah POTRET PER JAM, jadi hanya terendah dan
 * rata-rata yang bermakna. Sengaja tidak ada medan total/jumlah — antrean
 * per jam yang dijumlahkan lintas jam adalah angka yang tidak berarti apa
 * pun, dan menyediakan tempatnya saja sudah mengundang kekeliruan itu.
 */
export interface CagesTrackReportQueue {
  min_remaining: number | null
  avg_remaining: number | null
}

export interface CagesTrackReportTotal {
  cages_tipped: number
  cages_out: number
  days: number
}

export interface CagesTrackReportSummary {
  period: CagesTrackReportPeriodHeader | null
  kpi: CagesTrackReportKpi
  hourly: CagesTrackReportHourlyRow[]
  daily: CagesTrackReportDailyRow[]
  queue: CagesTrackReportQueue
  total: CagesTrackReportTotal
}

/**
 * Parameter opsional pemilih mill. `isAdmin` sengaja WAJIB dinyatakan
 * eksplisit oleh pemanggil yang hendak mengirim business_unit_id — lihat
 * catatan "gagal tertutup" pada docblock berkas.
 */
export interface CagesTrackReportScope {
  isAdmin?: boolean
  businessUnitId?: string | null
}

/**
 * Nilai bawaan HANYA dipakai ketika seluruh blok tidak ada pada respons
 * (mis. bentuk respons berubah di server), bukan untuk menambal medan yang
 * dikirim null. Periode tanpa data tetap mengirim blok lengkap berisi nol,
 * dan blok itulah yang diteruskan.
 */
const EMPTY_KPI: CagesTrackReportKpi = {
  total_cages_tipped: 0,
  total_cages_out: 0,
  avg_cages_per_day: 0,
  peak_hour: null,
  peak_hour_cages: 0,
  idle_operating_hours: 0,
  longest_gap_hours: null,
  longest_gap_date: null,
  avg_tippler_duration_hours: null,
  days_with_records: 0,
  days_without_valid_window: 0,
}

const EMPTY_QUEUE: CagesTrackReportQueue = {
  min_remaining: null,
  avg_remaining: null,
}

const EMPTY_TOTAL: CagesTrackReportTotal = {
  cages_tipped: 0,
  cages_out: 0,
  days: 0,
}

/**
 * business_unit_id HANYA dikirim untuk Admin. Untuk peran lain fungsi ini
 * mengembalikan objek kosong, apa pun yang dikirimkan pemanggil — kunci
 * kuncinya adalah medan itu ABSEN, bukan null dan bukan string kosong.
 */
function scopeParams(scope?: CagesTrackReportScope): Record<string, string> {
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
 * GET /api/cages-track-reports/business-units/options — pemilih Mill,
 * ADMIN SAJA (server menjawab 403 untuk peran lain, termasuk Operator:
 * pelebaran screen-136 tidak menyentuh endpoint ini). View TIDAK BOLEH
 * memanggilnya untuk peran yang terikat mill: selain percuma, permintaan
 * itu akan membentuk daftar seluruh mill yang memang tidak berhak dilihat.
 */
export async function fetchBusinessUnits(): Promise<CagesTrackReportBusinessUnitOption[]> {
  const response = await apiClient.get('/api/cages-track-reports/business-units/options')

  return (response.data?.data ?? []) as CagesTrackReportBusinessUnitOption[]
}

/**
 * GET /api/cages-track-reports/periods — periode yang mencakup stasiun
 * Cages & Tracks, yakni periode yang punya baris period_stations berjenis
 * 'cages-track'. Penyaringannya dikerjakan SERVER; repo meneruskan daftar
 * apa adanya, dalam urutan yang sama.
 * Daftar kosong adalah jawaban yang sah (HTTP 200 + []),
 * bukan galat: mill itu memang belum punya periode, dan layar
 * menampilkannya sebagai arahan menghubungi Admin.
 */
export async function fetchPeriods(
  scope?: CagesTrackReportScope,
): Promise<CagesTrackReportPeriodOption[]> {
  const params = scopeParams(scope)

  const response = await apiClient.get('/api/cages-track-reports/periods', { params })

  return (response.data?.data ?? []) as CagesTrackReportPeriodOption[]
}

/**
 * GET /api/cages-track-reports/summary — seluruh angka layar untuk satu
 * periode.
 *
 * Setiap blok diteruskan APA ADANYA, tanpa satu operasi aritmetika pun:
 * tidak ada pembulatan ulang pada avg_cages_per_day, tidak ada penjumlahan
 * hourly, tidak ada pengurutan atau penyaringan daily (termasuk baris
 * bernilai nol, yang justru harus terbaca), dan tidak ada penjumlahan
 * queue. Nilai bawaan di bawah hanya berlaku bila BLOK-nya tidak ada sama
 * sekali pada respons.
 */
export async function fetchSummary(
  periodId: string,
  scope?: CagesTrackReportScope,
): Promise<CagesTrackReportSummary> {
  const response = await apiClient.get('/api/cages-track-reports/summary', {
    params: { period_id: periodId, ...scopeParams(scope) },
  })

  const body = (unwrap<Record<string, unknown>>(response.data) ?? {}) as Record<string, unknown>

  return {
    period: (body.period ?? null) as CagesTrackReportPeriodHeader | null,
    kpi: (body.kpi ?? { ...EMPTY_KPI }) as CagesTrackReportKpi,
    hourly: (body.hourly ?? []) as CagesTrackReportHourlyRow[],
    daily: (body.daily ?? []) as CagesTrackReportDailyRow[],
    queue: (body.queue ?? { ...EMPTY_QUEUE }) as CagesTrackReportQueue,
    total: (body.total ?? { ...EMPTY_TOTAL }) as CagesTrackReportTotal,
  }
}

/**
 * GET /api/cages-track-reports/export?period_id=...&format=csv — respons
 * streaming text/csv, BUKAN JSON, jadi responseType-nya blob. Isinya
 * (satu baris per rincian penumpahan per jam, dengan kolom konteks record
 * diulang) dibentuk SERVER dan sama persis dengan ekspor laporan versi
 * web, karena endpoint-nya memang sama. Repo tidak pernah menyusun CSV
 * dari angka yang sedang tampil di layar.
 */
export async function exportCsv(periodId: string): Promise<Blob> {
  const response = await apiClient.get('/api/cages-track-reports/export', {
    params: { period_id: periodId, format: 'csv' },
    responseType: 'blob',
  })

  return response.data as Blob
}

/**
 * Mekanisme penyimpanan berkas — dipisah dari exportCsv() supaya
 * pengambilan data (yang perlu diuji atas query-nya) dan penyimpanan
 * berkas (yang perlu diuji atas pemanggilannya) dapat diamati sendiri-
 * sendiri. Memakai anchor + object URL, konvensi web standar yang juga
 * berlaku di WebView Capacitor.
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

export const cagesTrackReportRepo = {
  fetchBusinessUnits,
  fetchPeriods,
  fetchSummary,
  exportCsv,
  saveCsvFile,
}

export default cagesTrackReportRepo
