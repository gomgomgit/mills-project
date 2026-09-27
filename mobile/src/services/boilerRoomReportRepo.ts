import apiClient from '@/services/apiClient'

/**
 * boilerRoomReportRepo — screen-137--laporan-boiler-room-mobile /
 * usecase-137--laporan-boiler-room-mobile "Lihat Laporan Periode Boiler
 * Room (Mobile)".
 *
 * Kembaran persis sterilizerReportRepo.ts (screen-135) dan
 * cagesTrackReportRepo.ts (screen-136): satu berkas repo tipis di atas
 * apiClient, tanpa cache lokal. Layar laporan ini memang MEMBUTUHKAN
 * jaringan (business_rules: "Layar ini membutuhkan jaringan"), jadi tidak
 * ada jalur SQLite offline di sini sama sekali — kegagalan jaringan adalah
 * kondisi yang ditampilkan ke pengguna, bukan yang disembunyikan di balik
 * data basi.
 *
 * NOL ENDPOINT BARU. Keempat endpoint milik
 * screen-131--laporan-boiler-room-web dipakai ulang apa adanya
 * (App\Http\Controllers\Api\BoilerRoomReportController). Perubahan backend
 * untuk layar ini hanya soal SIAPA yang boleh memanggil: guard rute
 * mendapat peran `operator` (guard 'auth:web,sanctum' sudah ada
 * sebelumnya), BoilerRoomReportService::guardAccess() menerima
 * UserRole::Operator, dan — yang paling mudah terlewat —
 * resolveBusinessUnit() memasukkan Operator ke cabang TERIKAT MILL, bukan
 * membiarkannya jatuh ke cabang Admin yang tidak terikat.
 *
 * NOL PERHITUNGAN ULANG DI KLIEN. Berkas ini tidak pernah menjumlah,
 * merata-ratakan, membulatkan, mengurutkan, menyaring, atau menurunkan satu
 * angka pun. Seluruh nilai diteruskan apa adanya dari respons server,
 * karena laporan web memakai sumber yang sama (BoilerRoomReportService) —
 * perhitungan kedua di sisi klien pasti akan menyimpang dari laporan web
 * tanpa ketahuan. Yang paling menggoda untuk "dirapikan" di sini justru
 * yang paling berbahaya: coverage_percent (pembulatan server adalah
 * satu-satunya sumber), has_data (penanda milik server, bukan turunan dari
 * panjang `daily`), dan jumlah blowdown/sootblowing.
 *
 * SETIAP METRIK PUNYA PENYEBUTNYA SENDIRI. `metrics` membawa sembilan
 * entri, masing-masing dengan reading_count-nya sendiri. Repo TIDAK PERNAH
 * menurunkan satu reading_count bersama dari total.reading_rows — sebuah
 * baris pengukuran dapat mengisi tekanan dan mengosongkan pH, sehingga satu
 * penyebut bersama akan mengempiskan metrik yang jarang diisi sambil tetap
 * menghasilkan angka yang terlihat masuk akal.
 *
 * NULL BUKAN NOL. metrics[*].min/avg/max, seluruh kolom *_avg pada `daily`
 * dan `by_unit` diteruskan apa adanya. Mengoersinya menjadi 0 (atau '' /
 * '-') di sini akan mengubah "tidak pernah diukur" menjadi "nilainya nol" —
 * dua fakta yang berbeda, dan yang satu menyesatkan. Penerjemahan null
 * menjadi teks yang terbaca adalah urusan view, bukan repo.
 *
 * PERAWATAN PUNYA TIGA KEADAAN. maintenance.*.not_recorded berdiri sendiri
 * dan TIDAK PERNAH ditambahkan ke not_executed di sini; `all_unrecorded`
 * pun datang dari server dan tidak diturunkan ulang. Kolom kosong bukan
 * berarti perawatan tidak dijalankan.
 *
 * GALAT DITERUSKAN APA ADANYA. Tidak ada try/catch dan tidak ada kelas
 * galat khusus di sini. Perlu dicatat bentuknya: interceptor apiClient
 * menolak lewat normalizeError yang mengembalikan objek DATAR
 * { message, errors?, status? } dan MEMBUANG `response` — jadi `.response`
 * tidak pernah ada pada galat yang sampai ke pemanggil. 401 diterjemahkan
 * menjadi "arahkan ke Login" oleh LaporanBoilerRoomView (membaca
 * `error.status`), dan kegagalan transport (penolakan tanpa `status`)
 * menjadi pesan + tombol Coba Lagi di sana pula. Repo yang menelan galat
 * akan memaksa view menebak dari bentuk nilai kembalian.
 *
 * GAGAL TERTUTUP PADA business_unit_id: parameter ini hanya dikirim bila
 * pemanggil menyatakan dirinya Admin (`isAdmin: true`). Bagi Operator,
 * Supervisor, dan Mill Management mill diambil dari akun di sisi server
 * (BoilerRoomReportService::resolveBusinessUnit), sehingga sebuah
 * business_unit_id yang dipaksakan pemanggil TIDAK PERNAH ikut terkirim —
 * dijaga di sini, bukan hanya di view, supaya tidak bergantung pada
 * kedisiplinan pemanggil.
 */

export interface BoilerRoomReportBusinessUnitOption {
  id: string
  name: string
}

export interface BoilerRoomReportPeriodOption {
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
   * Selalu terisi 'boiler-room' — TIDAK pernah null. Sejak 2026-09-25 cakupan
   * "semua stasiun" tidak lagi diwujudkan sebagai station_type NULL
   * melainkan sebagai satu baris period_stations per jenis stasiun, jadi
   * server hanya memulangkan periode yang punya baris untuk jenis ini.
   */
  station_type: string
  station_type_label: string
}

export interface BoilerRoomReportPeriodHeader {
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

export interface BoilerRoomReportBusinessUnitRef {
  id: string
  name: string
}

/**
 * Kelengkapan pencatatan adalah ISI laporan, bukan metadata — periode yang
 * terisi 20% tetap menghasilkan rata-rata yang rapi, dan pembaca harus
 * melihat itu sebelum mempercayai angkanya. coverage_percent dibulatkan
 * SERVER; menghitungnya ulang di sini akan menghasilkan angka yang berbeda
 * dari laporan web.
 */
export interface BoilerRoomReportCoverage {
  filled_slots: number
  expected_slots: number
  coverage_percent: number
  boiler_unit_count: number
  slots_per_unit_per_day: number
  days_in_period: number
}

/**
 * min dan max berasal dari PEMBACAAN MENTAH per slot waktu, sementara
 * kolom *_avg pada `daily` adalah rata-rata HARIAN. Keduanya sengaja
 * berbeda dan TIDAK dapat direkonsiliasi — layar wajib mengatakannya
 * (BoilerRoomReportService, docblock kelas). Repo tidak menyentuh keduanya.
 *
 * reading_count adalah penyebut metrik INI SENDIRI. null/null/null dengan
 * reading_count 0 berarti metrik itu tidak pernah diisi — bukan 0/0/0.
 */
export interface BoilerRoomReportMetric {
  min: number | null
  avg: number | null
  max: number | null
  reading_count: number
}

/** Kesembilan kolom numerik Boiler Room, urutan mengikuti NUMERIC_METRICS di server. */
export type BoilerRoomReportMetricKey =
  | 'steam_pressure_bar'
  | 'steam_temp_c'
  | 'water_tds_ppm'
  | 'water_ph'
  | 'exhaust_gas_temp_c'
  | 'feed_water_temp_c'
  | 'feed_water_tank_level_percent'
  | 'boiler_water_level_percent'
  | 'dust_collector_differential_pressure_mmh2o'

export type BoilerRoomReportMetrics = Record<BoilerRoomReportMetricKey, BoilerRoomReportMetric>

/**
 * TIGA KEADAAN, bukan dua: executed + not_executed + not_recorded selalu
 * berjumlah sama dengan jumlah baris pembacaan. `all_unrecorded` adalah
 * yang membedakan nol yang berarti "tidak pernah dilakukan" dari nol yang
 * berarti "tidak pernah dicatat" — tanpanya keduanya tampil sebagai 0 yang
 * sama.
 */
export interface BoilerRoomReportMaintenanceItem {
  executed: number
  not_executed: number
  not_recorded: number
  avg_per_day: number
  all_unrecorded: boolean
}

export interface BoilerRoomReportMaintenance {
  blowdown: BoilerRoomReportMaintenanceItem
  sootblowing: BoilerRoomReportMaintenanceItem
}

/**
 * Satu entri per TANGGAL yang punya minimal satu record. Kolom *_avg di
 * sini adalah rata-rata HARIAN dengan penyebut hariannya sendiri, dan null
 * berarti metrik itu kosong sepanjang tanggal itu — tanggalnya TETAP
 * terhitung sebagai tanggal ber-record. Repo tidak membuang baris bernilai
 * nol maupun baris ber-null: justru baris itu yang harus terbaca.
 */
export interface BoilerRoomReportDailyRow {
  date: string
  filled_slots: number
  steam_pressure_avg: number | null
  steam_temp_avg: number | null
  water_tds_avg: number | null
  water_ph_avg: number | null
  exhaust_gas_temp_avg: number | null
  blowdown_executed: number
  sootblowing_executed: number
}

/** Rekap per unit boiler. Unit tanpa pembacaan tetap hadir dengan reading_count 0. */
export interface BoilerRoomReportUnitRow {
  boiler_room_id: string
  reading_count: number
  steam_pressure_avg: number | null
  steam_temp_avg: number | null
  water_tds_avg: number | null
  water_ph_avg: number | null
  blowdown_executed: number
  sootblowing_executed: number
}

export interface BoilerRoomReportTotal {
  days_with_records: number
  reading_rows: number
}

export interface BoilerRoomReportSummary {
  period: BoilerRoomReportPeriodHeader | null
  business_unit: BoilerRoomReportBusinessUnitRef | null
  /**
   * Penanda milik SERVER. Membedakan "tidak ada yang bisa dilaporkan" dari
   * "angkanya kebetulan nol", dan dipakai layar untuk menolak menggambar
   * grafik kosong yang akan terbaca sebagai garis datar hasil pengukuran.
   * TIDAK diturunkan di sini dari panjang `daily` maupun dari `total`.
   */
  has_data: boolean
  coverage: BoilerRoomReportCoverage
  metrics: BoilerRoomReportMetrics
  maintenance: BoilerRoomReportMaintenance
  daily: BoilerRoomReportDailyRow[]
  by_unit: BoilerRoomReportUnitRow[]
  total: BoilerRoomReportTotal
}

/**
 * Parameter opsional pemilih mill. `isAdmin` sengaja WAJIB dinyatakan
 * eksplisit oleh pemanggil yang hendak mengirim business_unit_id — lihat
 * catatan "gagal tertutup" pada docblock berkas.
 */
export interface BoilerRoomReportScope {
  isAdmin?: boolean
  businessUnitId?: string | null
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
const EMPTY_METRIC: BoilerRoomReportMetric = {
  min: null,
  avg: null,
  max: null,
  reading_count: 0,
}

const EMPTY_METRICS: BoilerRoomReportMetrics = {
  steam_pressure_bar: { ...EMPTY_METRIC },
  steam_temp_c: { ...EMPTY_METRIC },
  water_tds_ppm: { ...EMPTY_METRIC },
  water_ph: { ...EMPTY_METRIC },
  exhaust_gas_temp_c: { ...EMPTY_METRIC },
  feed_water_temp_c: { ...EMPTY_METRIC },
  feed_water_tank_level_percent: { ...EMPTY_METRIC },
  boiler_water_level_percent: { ...EMPTY_METRIC },
  dust_collector_differential_pressure_mmh2o: { ...EMPTY_METRIC },
}

const EMPTY_MAINTENANCE_ITEM: BoilerRoomReportMaintenanceItem = {
  executed: 0,
  not_executed: 0,
  not_recorded: 0,
  avg_per_day: 0,
  all_unrecorded: false,
}

const EMPTY_MAINTENANCE: BoilerRoomReportMaintenance = {
  blowdown: { ...EMPTY_MAINTENANCE_ITEM },
  sootblowing: { ...EMPTY_MAINTENANCE_ITEM },
}

const EMPTY_COVERAGE: BoilerRoomReportCoverage = {
  filled_slots: 0,
  expected_slots: 0,
  coverage_percent: 0,
  boiler_unit_count: 0,
  slots_per_unit_per_day: 0,
  days_in_period: 0,
}

const EMPTY_TOTAL: BoilerRoomReportTotal = {
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
function scopeParams(scope?: BoilerRoomReportScope): Record<string, string> {
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
 * GET /api/boiler-room-reports/business-units/options — pemilih Mill,
 * ADMIN SAJA (server menjawab 403 untuk peran lain, termasuk Operator:
 * pelebaran screen-137 tidak menyentuh endpoint ini). View TIDAK BOLEH
 * memanggilnya untuk peran yang terikat mill: selain percuma, permintaan
 * itu akan membentuk daftar seluruh mill yang memang tidak berhak dilihat.
 */
export async function fetchBusinessUnits(): Promise<BoilerRoomReportBusinessUnitOption[]> {
  const response = await apiClient.get('/api/boiler-room-reports/business-units/options')

  return (response.data?.data ?? []) as BoilerRoomReportBusinessUnitOption[]
}

/**
 * GET /api/boiler-room-reports/periods — periode yang mencakup stasiun
 * Boiler Room, yakni periode yang punya baris period_stations berjenis
 * 'boiler-room'. Penyaringannya dikerjakan SERVER; repo meneruskan daftar
 * apa adanya, dalam urutan yang sama — menyaringnya kedua kali di sini
 * akan menciptakan definisi cakupan yang kedua. Daftar kosong adalah jawaban yang sah
 * (HTTP 200 + []), bukan galat: mill itu memang belum punya periode, dan
 * layar menampilkannya sebagai arahan menghubungi Admin.
 */
export async function fetchPeriods(
  scope?: BoilerRoomReportScope,
): Promise<BoilerRoomReportPeriodOption[]> {
  const params = scopeParams(scope)

  const response = await apiClient.get('/api/boiler-room-reports/periods', { params })

  return (response.data?.data ?? []) as BoilerRoomReportPeriodOption[]
}

/**
 * GET /api/boiler-room-reports/summary — seluruh angka layar untuk satu
 * periode.
 *
 * Setiap blok diteruskan APA ADANYA, tanpa satu operasi aritmetika pun:
 * tidak ada pembulatan ulang coverage_percent atau avg_per_day, tidak ada
 * penjumlahan blowdown/sootblowing, tidak ada perataan tekanan maupun suhu,
 * tidak ada penurunan has_data, dan tidak ada pengurutan atau penyaringan
 * `daily` / `by_unit` (termasuk baris bernilai nol dan unit ber-reading_count
 * 0, yang justru harus terbaca). Nilai bawaan di bawah hanya berlaku bila
 * BLOK-nya tidak ada sama sekali pada respons.
 */
export async function fetchSummary(
  periodId: string,
  scope?: BoilerRoomReportScope,
): Promise<BoilerRoomReportSummary> {
  const response = await apiClient.get('/api/boiler-room-reports/summary', {
    params: { period_id: periodId, ...scopeParams(scope) },
  })

  const body = (unwrap<Record<string, unknown>>(response.data) ?? {}) as Record<string, unknown>

  return {
    period: (body.period ?? null) as BoilerRoomReportPeriodHeader | null,
    business_unit: (body.business_unit ?? null) as BoilerRoomReportBusinessUnitRef | null,
    has_data: (body.has_data ?? false) as boolean,
    coverage: (body.coverage ?? { ...EMPTY_COVERAGE }) as BoilerRoomReportCoverage,
    metrics: (body.metrics ?? { ...EMPTY_METRICS }) as BoilerRoomReportMetrics,
    maintenance: (body.maintenance ?? { ...EMPTY_MAINTENANCE }) as BoilerRoomReportMaintenance,
    daily: (body.daily ?? []) as BoilerRoomReportDailyRow[],
    by_unit: (body.by_unit ?? []) as BoilerRoomReportUnitRow[],
    total: (body.total ?? { ...EMPTY_TOTAL }) as BoilerRoomReportTotal,
  }
}

/**
 * GET /api/boiler-room-reports/export?period_id=...&format=csv — respons
 * streaming text/csv, BUKAN JSON, jadi responseType-nya blob. Isinya (satu
 * baris per slot waktu, dengan kolom konteks record diulang dan ketiga
 * kolom teks bebas — laju bahan bakar, beban ID fan, beban SA fan — muncul
 * verbatim) dibentuk SERVER dan sama persis dengan ekspor laporan versi
 * web, karena endpoint-nya memang sama. Repo tidak pernah menyusun CSV dari
 * angka yang sedang tampil di layar.
 */
export async function exportCsv(periodId: string): Promise<Blob> {
  const response = await apiClient.get('/api/boiler-room-reports/export', {
    params: { period_id: periodId, format: 'csv' },
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

export const boilerRoomReportRepo = {
  fetchBusinessUnits,
  fetchPeriods,
  fetchSummary,
  exportCsv,
  saveCsvFile,
}

export default boilerRoomReportRepo
