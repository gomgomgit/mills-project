import apiClient from '@/services/apiClient'

/**
 * sterilizerReportRepo — screen-135--laporan-sterilizer-mobile /
 * usecase-135--laporan-sterilizer-mobile "Lihat Laporan Periode Sterilizer
 * (Mobile)".
 *
 * Mengikuti pola productionLineRepo.ts: satu berkas repo tipis di atas
 * apiClient, tanpa cache lokal. Layar laporan ini memang MEMBUTUHKAN
 * jaringan (business_rules: "Layar ini membutuhkan jaringan"), jadi tidak
 * ada jalur SQLite offline di sini sama sekali — kegagalan jaringan adalah
 * kondisi yang ditampilkan ke pengguna, bukan yang disembunyikan di balik
 * data basi.
 *
 * NOL ENDPOINT BARU. Keempat endpoint milik screen-129--laporan-sterilizer-web
 * dipakai ulang apa adanya (App\Http\Controllers\Api\SterilizerReportController).
 * Satu-satunya perubahan backend untuk layar ini adalah pelebaran daftar
 * peran pada routes/api.php agar memuat `operator`.
 *
 * NOL PERHITUNGAN ULANG DI KLIEN. Berkas ini tidak pernah menjumlah,
 * merata-rata, atau menurunkan satu angka pun. Seluruh nilai dipetakan apa
 * adanya dari respons server, karena laporan web memakai sumber yang sama
 * (SterilizerReportService) — perhitungan kedua di sisi klien pasti akan
 * menyimpang dari laporan web tanpa ketahuan. Satu-satunya "pemetaan" yang
 * dilakukan adalah penyeragaman NAMA field durasi KPI (lihat normalizeKpi
 * di bawah), bukan nilainya.
 *
 * GAGAL TERTUTUP PADA business_unit_id: parameter ini hanya dikirim bila
 * pemanggil menyatakan dirinya Admin (`isAdmin: true`). Bagi Operator,
 * Supervisor, dan Mill Management mill diambil dari akun di sisi server
 * (SterilizerReportService::resolveBusinessUnit), sehingga sebuah
 * business_unit_id yang dipaksakan pemanggil TIDAK PERNAH ikut terkirim —
 * dijaga di sini, bukan hanya di view, supaya tidak bergantung pada
 * kedisiplinan pemanggil.
 */

export interface SterilizerReportBusinessUnitOption {
  id: string
  name: string
}

export interface SterilizerReportPeriodOption {
  id: string
  name: string
  start_date: string
  end_date: string
  status: string
  station_type: string | null
  station_type_label: string
}

export interface SterilizerReportPeriodHeader {
  id: string
  name: string
  start_date: string
  end_date: string
  status: string
  business_unit_name?: string
}

export interface SterilizerReportKpi {
  total_cycles: number
  total_cages: number
  avg_duration: number | null
  min_duration: number | null
  max_duration: number | null
  cycles_without_duration: number
  triple_peak_compliance_percent: number
}

export interface SterilizerReportDailyRow {
  date: string
  cycles: number
  cages: number
  avg_duration: number | null
  min_duration: number | null
  max_duration: number | null
  triple_peak_complete: number
  cycles_without_duration: number
}

export interface SterilizerReportUnitRow {
  sterilizer_no: string
  cycles: number
  cages: number
  avg_duration: number | null
  triple_peak_complete: number
}

export interface SterilizerReportOutlierItem {
  date: string
  sterilizer_no: string
  duration_minutes: number | null
  number_of_cages: number | null
  cages_status: string | null
  close_door_time: string | null
  open_door_time: string | null
}

export interface SterilizerReportOutliers {
  method: string
  q1: number | null
  q3: number | null
  iqr: number | null
  lower_bound: number | null
  upper_bound: number | null
  sample_size: number
  min_sample_size: number
  insufficient_data: boolean
  items: SterilizerReportOutlierItem[]
}

export interface SterilizerReportTotal {
  cycles: number
  cages: number
  avg_duration: number | null
  min_duration: number | null
  max_duration: number | null
  triple_peak_complete: number
  cycles_without_duration: number
}

export interface SterilizerReportSummary {
  period: SterilizerReportPeriodHeader | null
  kpi: SterilizerReportKpi
  daily: SterilizerReportDailyRow[]
  by_unit: SterilizerReportUnitRow[]
  outliers: SterilizerReportOutliers
  total: SterilizerReportTotal
}

/**
 * Parameter opsional pemilih mill. `isAdmin` sengaja WAJIB dinyatakan
 * eksplisit oleh pemanggil yang hendak mengirim business_unit_id — lihat
 * catatan "gagal tertutup" pada docblock berkas.
 */
export interface SterilizerReportScope {
  isAdmin?: boolean
  businessUnitId?: string | null
}

const EMPTY_KPI: SterilizerReportKpi = {
  total_cycles: 0,
  total_cages: 0,
  avg_duration: null,
  min_duration: null,
  max_duration: null,
  cycles_without_duration: 0,
  triple_peak_compliance_percent: 0,
}

const EMPTY_OUTLIERS: SterilizerReportOutliers = {
  method: 'iqr',
  q1: null,
  q3: null,
  iqr: null,
  lower_bound: null,
  upper_bound: null,
  sample_size: 0,
  min_sample_size: 0,
  insufficient_data: true,
  items: [],
}

const EMPTY_TOTAL: SterilizerReportTotal = {
  cycles: 0,
  cages: 0,
  avg_duration: null,
  min_duration: null,
  max_duration: null,
  triple_peak_complete: 0,
  cycles_without_duration: 0,
}

/**
 * business_unit_id HANYA dikirim untuk Admin. Untuk peran lain fungsi ini
 * mengembalikan objek kosong, apa pun yang dikirimkan pemanggil.
 */
function scopeParams(scope?: SterilizerReportScope): Record<string, string> {
  if (!scope?.isAdmin) {
    return {}
  }

  const businessUnitId = scope.businessUnitId

  if (!businessUnitId) {
    return {}
  }

  return { business_unit_id: businessUnitId }
}

function toNullableNumber(value: unknown): number | null {
  if (value === null || value === undefined || value === '') {
    return null
  }

  const parsed = Number(value)

  return Number.isFinite(parsed) ? parsed : null
}

function toNumber(value: unknown, fallback = 0): number {
  const parsed = toNullableNumber(value)

  return parsed === null ? fallback : parsed
}

/**
 * Menyeragamkan NAMA field durasi KPI, bukan nilainya.
 *
 * SterilizerReportService::kpiOf() mengirim `avg_duration_minutes` /
 * `min_duration_minutes` / `max_duration_minutes`, sementara blok `daily`,
 * `by_unit`, dan `total` pada respons yang sama memakai `avg_duration` /
 * `min_duration` / `max_duration`. Satu bentuk dipakai di seluruh view
 * model agar template tidak perlu mengingat dua konvensi penamaan untuk
 * besaran yang sama. Nilainya diteruskan apa adanya — null tetap null, dan
 * TIDAK PERNAH diganti 0 (0 akan terbaca sebagai fakta yang salah).
 */
function normalizeKpi(raw: Record<string, unknown> | null | undefined): SterilizerReportKpi {
  if (!raw) {
    return { ...EMPTY_KPI }
  }

  return {
    total_cycles: toNumber(raw.total_cycles),
    total_cages: toNumber(raw.total_cages),
    avg_duration: toNullableNumber(raw.avg_duration_minutes ?? raw.avg_duration),
    min_duration: toNullableNumber(raw.min_duration_minutes ?? raw.min_duration),
    max_duration: toNullableNumber(raw.max_duration_minutes ?? raw.max_duration),
    cycles_without_duration: toNumber(raw.cycles_without_duration),
    triple_peak_compliance_percent: toNumber(raw.triple_peak_compliance_percent),
  }
}

/**
 * Respons summary dikirim controller TANPA pembungkus `data`
 * (`response()->json($this->service->summary($period))`), sementara
 * `periods` dan `business-units/options` MEMAKAI pembungkus itu. Kedua
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
 * GET /api/sterilizer-reports/business-units/options — pemilih Mill,
 * ADMIN SAJA (server menjawab 403 untuk peran lain). View TIDAK BOLEH
 * memanggil ini untuk peran yang terikat mill: selain percuma, permintaan
 * itu akan membentuk daftar seluruh mill yang memang tidak berhak dilihat.
 */
export async function fetchBusinessUnits(): Promise<SterilizerReportBusinessUnitOption[]> {
  const response = await apiClient.get('/api/sterilizer-reports/business-units/options')

  return (response.data?.data ?? []) as SterilizerReportBusinessUnitOption[]
}

/**
 * GET /api/sterilizer-reports/periods — periode yang mencakup stasiun
 * Sterilizer (station_type 'sterilizer' ATAU null). Daftar kosong adalah
 * jawaban yang sah (HTTP 200 + []), bukan galat: mill itu memang belum
 * punya periode, dan layar menampilkannya sebagai arahan menghubungi
 * Admin.
 */
export async function fetchPeriods(scope?: SterilizerReportScope): Promise<SterilizerReportPeriodOption[]> {
  const params = scopeParams(scope)

  const response = await apiClient.get('/api/sterilizer-reports/periods', { params })

  return (response.data?.data ?? []) as SterilizerReportPeriodOption[]
}

/**
 * GET /api/sterilizer-reports/summary — seluruh angka layar untuk satu
 * periode. Dipetakan apa adanya; tidak ada satu operasi aritmetika pun di
 * sini selain penyeragaman nama field pada normalizeKpi().
 */
export async function fetchSummary(
  periodId: string,
  scope?: SterilizerReportScope,
): Promise<SterilizerReportSummary> {
  const response = await apiClient.get('/api/sterilizer-reports/summary', {
    params: { period_id: periodId, ...scopeParams(scope) },
  })

  const body = (unwrap<Record<string, unknown>>(response.data) ?? {}) as Record<string, unknown>

  const outliers = (body.outliers ?? null) as Record<string, unknown> | null

  return {
    period: (body.period ?? null) as SterilizerReportPeriodHeader | null,
    kpi: normalizeKpi(body.kpi as Record<string, unknown> | null),
    daily: (body.daily ?? []) as SterilizerReportDailyRow[],
    by_unit: (body.by_unit ?? []) as SterilizerReportUnitRow[],
    outliers: outliers
      ? {
          method: String(outliers.method ?? 'iqr'),
          q1: toNullableNumber(outliers.q1),
          q3: toNullableNumber(outliers.q3),
          iqr: toNullableNumber(outliers.iqr),
          lower_bound: toNullableNumber(outliers.lower_bound),
          upper_bound: toNullableNumber(outliers.upper_bound),
          sample_size: toNumber(outliers.sample_size),
          min_sample_size: toNumber(outliers.min_sample_size),
          insufficient_data: Boolean(outliers.insufficient_data),
          items: (outliers.items ?? []) as SterilizerReportOutlierItem[],
        }
      : { ...EMPTY_OUTLIERS },
    total: ((body.total ?? { ...EMPTY_TOTAL }) as SterilizerReportTotal),
  }
}

/**
 * GET /api/sterilizer-reports/export?period_id=...&format=csv — respons
 * streaming text/csv, BUKAN JSON, jadi responseType-nya blob. Isinya sama
 * persis dengan ekspor laporan versi web karena endpoint-nya memang sama.
 */
export async function exportCsv(periodId: string): Promise<Blob> {
  const response = await apiClient.get('/api/sterilizer-reports/export', {
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

export const sterilizerReportRepo = {
  fetchBusinessUnits,
  fetchPeriods,
  fetchSummary,
  exportCsv,
  saveCsvFile,
}

export default sterilizerReportRepo
