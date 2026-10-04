import apiClient from '@/services/apiClient'

/**
 * storageTankReportRepo — screen-139--laporan-storage-tank-mobile /
 * usecase-139--laporan-storage-tank-mobile "Lihat Laporan Periode Storage
 * Tank (Mobile)".
 *
 * Kembaran kelima dan terakhir dari pola yang sama: sterilizerReportRepo.ts
 * (screen-135), cagesTrackReportRepo.ts (screen-136),
 * boilerRoomReportRepo.ts (screen-137), dan clarificationReportRepo.ts
 * (screen-138). Satu berkas repo tipis di atas apiClient, tanpa cache lokal.
 * Layar laporan ini memang MEMBUTUHKAN jaringan, jadi tidak ada jalur SQLite
 * offline di sini sama sekali — kegagalan jaringan adalah kondisi yang
 * ditampilkan ke pengguna, bukan yang disembunyikan di balik data basi.
 *
 * NOL ENDPOINT BARU. Keempat endpoint milik
 * screen-133--laporan-storage-tank-web dipakai ulang apa adanya
 * (App\Http\Controllers\Api\StorageTankReportController). Perubahan backend
 * untuk layar ini hanya soal SIAPA yang boleh memanggil — TEPAT TIGA BARIS
 * KODE:
 *   1. routes/api.php: grup rute storage-tank-reports mendapat peran
 *      `operator` (guard 'auth:web,sanctum' sudah ada sebelumnya, jadi ia
 *      TIDAK ikut berubah — berbeda dari catatan tech spec yang menyebut
 *      empat perubahan);
 *   2. StorageTankReportService::guardAccess() menerima UserRole::Operator;
 *   3. — dan yang paling mudah terlewat —
 *      StorageTankReportService::resolveBusinessUnit() memasukkan Operator
 *      ke cabang TERIKAT MILL, bukan membiarkannya jatuh ke cabang Admin
 *      yang tidak terikat, tempat business_unit_id kiriman klien DIHORMATI.
 *
 * NOL PERHITUNGAN ULANG DI KLIEN. Berkas ini tidak pernah menjumlah,
 * merata-ratakan, membulatkan, mengurutkan, menyaring, atau menurunkan satu
 * angka pun. Seluruh nilai diteruskan apa adanya dari respons server, karena
 * laporan web memakai sumber yang sama (StorageTankReportService) —
 * perhitungan kedua di sisi klien pasti akan menyimpang dari laporan web
 * tanpa ketahuan.
 *
 * ────────────────────────────────────────────────────────────────────────
 * YANG MEMBEDAKAN LAPORAN INI DARI EMPAT LAPORAN SEBELUMNYA
 * ────────────────────────────────────────────────────────────────────────
 * Keempat laporan stasiun sebelumnya MERINGKAS PERISTIWA: mereka menghitung,
 * menjumlah, dan merata-ratakan. Laporan ini menjawab pertanyaan tentang
 * KEADAAN DAN PERUBAHANNYA, dan jawaban itu adalah PERBANDINGAN DUA
 * PEMBACAAN. Tiga godaan berhitung yang lahir dari perbedaan itu, dan
 * ketiganya dilarang di sini:
 *
 * 1. movement_mt BUKAN closing_mt − opening_mt. Server menghitung pergerakan
 *    PER TANGKI lalu menjumlahkannya; selisih stok GABUNGAN adalah angka yang
 *    berbeda, dan berbeda paling jauh persis ketika jumlah tangki
 *    bepembacaan berubah di tengah periode — yaitu saat laporan paling
 *    dibutuhkan. Mengurangkan keduanya di sini menghasilkan angka yang
 *    TERLIHAT masuk akal dan tidak akan pernah dibandingkan dengan laporan
 *    web sampai seseorang curiga.
 *
 * 2. average_temperature_c DIBACA, BUKAN DITURUNKAN. Ia kolom yang diisi
 *    Operator sendiri, bukan rata-rata oil_temperature_top/middle/bottom_c.
 *    Ketiganya dapat terisi sementara suhu rata-rata kosong, dan mengisinya
 *    dari ketiga suhu posisi akan menerbitkan angka yang tidak pernah
 *    diukur.
 *
 * 3. STOK BERASAL DARI BERAT (MT), BUKAN VOLUME — diputuskan 2026-09-25 dan
 *    sudah tertanam di StorageTankReportService::STOCK_COLUMN
 *    (calculated_weight_mt). calculated_volume_m3 dan ketiga kedalaman
 *    sounding diterbitkan sebagai metrik tersendiri dan TIDAK PERNAH ikut
 *    membentuk opening/closing/movement. Ketiga keluarga angka itu dapat
 *    terisi sendiri-sendiri dan dapat saling bertentangan; memilih satu dan
 *    menyatakannya adalah seluruh pokoknya.
 *
 * SATU PEMBACAAN BUKAN PERGERAKAN NOL. Tangki dengan satu pembacaan stok
 * mengirim movement_mt null dan movement_computable false. Menurunkan 0 dari
 * closing_mt − opening_mt untuk tangki itu akan MENGAKU bahwa stoknya tidak
 * berubah — klaim yang tidak pernah diukur. movement_computable adalah
 * medan yang menyatakan perbedaan itu, dan ia diteruskan apa adanya.
 *
 * SETIAP METRIK PUNYA PENYEBUTNYA SENDIRI. `metrics` membawa SEPULUH entri,
 * masing-masing dengan reading_count-nya sendiri. Repo TIDAK PERNAH
 * menurunkan satu reading_count bersama dari total.reading_rows — DOBI
 * khususnya tidak diukur setiap slot, sehingga satu penyebut bersama akan
 * mengempiskan metrik yang jarang diisi sambil tetap menghasilkan angka yang
 * terlihat masuk akal.
 *
 * NULL BUKAN NOL. stock.*, metrics[*].min/avg/max, seluruh kolom *_avg pada
 * `daily`, dan seluruh kolom stok pada `by_tank` diteruskan apa adanya.
 * Mengoersinya menjadi 0 (atau '' / '-') di sini akan mengubah "tidak pernah
 * diukur" menjadi "nilainya nol" — dua fakta yang berbeda, dan yang satu
 * menyesatkan. Penerjemahan null menjadi teks yang terbaca adalah urusan
 * view, bukan repo.
 *
 * GALAT DITERUSKAN APA ADANYA. Tidak ada try/catch dan tidak ada kelas galat
 * khusus di sini. Perlu dicatat bentuknya: interceptor apiClient menolak
 * lewat normalizeError yang mengembalikan objek DATAR
 * { message, errors?, status? } dan MEMBUANG `response` — jadi `.response`
 * tidak pernah ada pada galat yang sampai ke pemanggil. 401 diterjemahkan
 * menjadi "arahkan ke Login" oleh LaporanStorageTankView (membaca
 * `error.status`), dan kegagalan transport (penolakan tanpa `status`)
 * menjadi pesan + tombol Coba Lagi di sana pula.
 *
 * GAGAL TERTUTUP PADA business_unit_id: parameter ini hanya dikirim bila
 * pemanggil menyatakan dirinya Admin (`isAdmin: true`). Bagi Operator,
 * Supervisor, dan Mill Management mill diambil dari akun di sisi server
 * (StorageTankReportService::resolveBusinessUnit), sehingga sebuah
 * business_unit_id yang dipaksakan pemanggil TIDAK PERNAH ikut terkirim —
 * dijaga di sini, bukan hanya di view, supaya tidak bergantung pada
 * kedisiplinan pemanggil.
 */

export interface StorageTankReportBusinessUnitOption {
  id: string
  name: string
}

export interface StorageTankReportPeriodOption {
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
   * Selalu terisi 'storage-tank' — TIDAK pernah null. Sejak 2026-09-25 cakupan
   * "semua stasiun" tidak lagi diwujudkan sebagai station_type NULL
   * melainkan sebagai satu baris period_stations per jenis stasiun, jadi
   * server hanya memulangkan periode yang punya baris untuk jenis ini.
   */
  station_type: string
  station_type_label: string
}

export interface StorageTankReportPeriodHeader {
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

export interface StorageTankReportBusinessUnitRef {
  id: string
  name: string
}

/**
 * Kelengkapan pencatatan adalah ISI laporan, bukan metadata — dan di layar
 * ini ia menopang beban dengan caranya sendiri: ia menyatakan seberapa jauh
 * dua pembacaan yang dibandingkan itu benar-benar duduk di ujung-ujung
 * periodenya. coverage_percent dibulatkan SERVER ke dua desimal (3 dari 240 =
 * 1,25%, bukan 1,3); menghitungnya ulang di sini akan menghasilkan angka yang
 * berbeda dari laporan web.
 */
export interface StorageTankReportCoverage {
  filled_slots: number
  expected_slots: number
  coverage_percent: number
  tank_count: number
  slots_per_tank_per_day: number
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
 * Stok periode — HASIL PERBANDINGAN DUA PEMBACAAN, bukan agregat.
 *
 * opening_mt / closing_mt adalah JUMLAH stok awal/akhir seluruh tangki,
 * masing-masing diambil dari pembacaan berat PERTAMA/TERAKHIR tangki itu
 * menurut (tanggal, slot waktu) — bukan nilai terendah/tertinggi, dan bukan
 * urutan penyimpanan baris.
 *
 * opening_at / closing_at adalah tanggal+slot paling awal/akhir di antara
 * seluruh tangki, dan wajib tampil berdampingan dengan angkanya: jarak
 * keduanya terhadap ujung periode adalah satu-satunya cara pembaca tahu
 * seberapa mewakili angka itu.
 *
 * movement_mt adalah JUMLAH pergerakan PER TANGKI — BUKAN
 * closing_mt − opening_mt. Lihat catatan 1 pada docblock berkas.
 *
 * tanks_without_movement mencakup tangki berpembacaan tunggal DAN tangki
 * tanpa satu pun pembacaan stok. Keduanya "tidak dapat dihitung", dan
 * keduanya bukan nol.
 */
export interface StorageTankReportStock {
  opening_mt: number | null
  opening_at: string | null
  closing_mt: number | null
  closing_at: string | null
  movement_mt: number | null
  tanks_with_movement: number
  tanks_without_movement: number
}

/**
 * min dan max berasal dari PEMBACAAN MENTAH per slot waktu, sementara kolom
 * *_avg pada `daily` adalah rata-rata HARIAN. Keduanya sengaja berbeda dan
 * TIDAK dapat direkonsiliasi. Repo tidak menyentuh keduanya.
 *
 * reading_count adalah penyebut metrik INI SENDIRI. null/null/null dengan
 * reading_count 0 berarti metrik itu tidak pernah diisi — bukan 0/0/0.
 */
export interface StorageTankReportMetric {
  min: number | null
  avg: number | null
  max: number | null
  reading_count: number
}

/**
 * Kesepuluh kolom numerik Storage Tank, urutan mengikuti NUMERIC_METRICS di
 * server: empat angka mutu minyak lebih dulu (ketiganya yang pertama berbagi
 * grafik tren mutu), lalu suhu rata-rata yang dicatat Operator, lalu dua
 * angka stok terhitung, lalu tiga suhu posisi.
 *
 * SENGAJA TIDAK ADA DI SINI, dan ketiadaannya dijaga: steam_heating_valve_status
 * (enum) beserta tank_structural_condition / inspector_name / findings tidak
 * pernah diagregasi menjadi min/avg/max — mereka hanya muncul pada ekspor.
 * Ketiga kedalaman sounding juga bukan angka laporan.
 */
export type StorageTankReportMetricKey =
  | 'ffa_percent'
  | 'moisture_content_percent'
  | 'impurities_dirt_percent'
  | 'dobi_index'
  | 'average_temperature_c'
  | 'calculated_weight_mt'
  | 'calculated_volume_m3'
  | 'oil_temperature_top_c'
  | 'oil_temperature_middle_c'
  | 'oil_temperature_bottom_c'

export type StorageTankReportMetrics = Record<
  StorageTankReportMetricKey,
  StorageTankReportMetric
>

/**
 * Satu entri per TANGGAL yang punya minimal satu record. Kolom *_avg di sini
 * adalah rata-rata HARIAN dengan penyebut hariannya sendiri, dan null berarti
 * metrik itu kosong sepanjang tanggal itu — tanggalnya TETAP terhitung
 * sebagai tanggal ber-record. Repo tidak membuang baris bernilai nol maupun
 * baris ber-null: justru baris itu yang harus terbaca.
 *
 * FFA, KADAR AIR DAN DOBI DIKIRIM PADA SATU BARIS per tanggal, dengan
 * sengaja: yang menyatakan minyaknya memburuk adalah ketiganya bergerak
 * BERSAMAAN, bukan salah satunya sendirian. Skalanya tidak sebanding (DOBI
 * ~2-4 tanpa satuan, FFA ~3-5 %, kadar air ~0,1-0,3 %), sehingga layar WAJIB
 * menormalkannya dan MENYATAKANNYA — keputusan tampilan itu milik view,
 * payloadnya tetap mentah.
 */
export interface StorageTankReportDailyRow {
  date: string
  filled_slots: number
  stock_total_mt: number | null
  ffa_avg: number | null
  moisture_avg: number | null
  impurities_avg: number | null
  dobi_avg: number | null
  temperature_avg: number | null
}

/**
 * Rekap per tangki. Tangki tanpa pembacaan tetap hadir dengan reading_count
 * 0 dan seluruh kolom stok null — tangki yang tidak pernah tercatat adalah
 * temuan, bukan baris yang layak dibuang.
 *
 * movement_computable, bukan nilai movement_mt, adalah medan yang memisahkan
 * "tidak bergerak" dari "tidak dapat dihitung".
 */
export interface StorageTankReportTankRow {
  storage_tank_id: string
  reading_count: number
  opening_mt: number | null
  opening_at: string | null
  closing_mt: number | null
  closing_at: string | null
  movement_mt: number | null
  movement_computable: boolean
  ffa_avg: number | null
  average_temperature_avg: number | null
}

export interface StorageTankReportTotal {
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
export interface StorageTankReportProductionLineRef {
  id: string
  name: string
}

export interface StorageTankReportSummary {
  period: StorageTankReportPeriodHeader | null
  production_line: StorageTankReportProductionLineRef | null
  business_unit: StorageTankReportBusinessUnitRef | null
  /**
   * Penanda milik SERVER (filled_slots > 0). Membedakan "tidak ada yang bisa
   * dilaporkan" dari "angkanya kebetulan nol", dan dipakai layar untuk
   * menolak menggambar grafik kosong yang akan terbaca sebagai garis datar
   * hasil pengukuran. TIDAK diturunkan di sini dari panjang `daily` maupun
   * dari `total`.
   */
  has_data: boolean
  coverage: StorageTankReportCoverage
  stock: StorageTankReportStock
  metrics: StorageTankReportMetrics
  by_tank: StorageTankReportTankRow[]
  daily: StorageTankReportDailyRow[]
  total: StorageTankReportTotal
}

/**
 * Parameter opsional pemilih mill. `isAdmin` sengaja WAJIB dinyatakan
 * eksplisit oleh pemanggil yang hendak mengirim business_unit_id — lihat
 * catatan "gagal tertutup" pada docblock berkas.
 */
export interface StorageTankReportScope {
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
const EMPTY_METRIC: StorageTankReportMetric = {
  min: null,
  avg: null,
  max: null,
  reading_count: 0,
}

const EMPTY_METRICS: StorageTankReportMetrics = {
  ffa_percent: { ...EMPTY_METRIC },
  moisture_content_percent: { ...EMPTY_METRIC },
  impurities_dirt_percent: { ...EMPTY_METRIC },
  dobi_index: { ...EMPTY_METRIC },
  average_temperature_c: { ...EMPTY_METRIC },
  calculated_weight_mt: { ...EMPTY_METRIC },
  calculated_volume_m3: { ...EMPTY_METRIC },
  oil_temperature_top_c: { ...EMPTY_METRIC },
  oil_temperature_middle_c: { ...EMPTY_METRIC },
  oil_temperature_bottom_c: { ...EMPTY_METRIC },
}

/**
 * Bawaan stok memakai null, BUKAN 0 — blok yang hilang sama sekali berarti
 * tidak ada yang dapat dilaporkan, dan itu bukan stok nol.
 */
const EMPTY_STOCK: StorageTankReportStock = {
  opening_mt: null,
  opening_at: null,
  closing_mt: null,
  closing_at: null,
  movement_mt: null,
  tanks_with_movement: 0,
  tanks_without_movement: 0,
}

const EMPTY_COVERAGE: StorageTankReportCoverage = {
  filled_slots: 0,
  expected_slots: 0,
  coverage_percent: 0,
  tank_count: 0,
  slots_per_tank_per_day: 0,
  days_in_period: 0,
  days_counted: 0,
  period_running: false,
}

const EMPTY_TOTAL: StorageTankReportTotal = {
  days_with_records: 0,
  reading_rows: 0,
}

/**
 * business_unit_id HANYA dikirim untuk Admin. Untuk peran lain fungsi ini
 * mengembalikan objek kosong, apa pun yang dikirimkan pemanggil — kuncinya
 * adalah medan itu ABSEN, bukan null dan bukan string kosong. Operator ada di
 * cabang terikat-mill ini, sama seperti Supervisor dan Mill Management.
 */
function scopeParams(scope?: StorageTankReportScope): Record<string, string> {
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
function productionLineParams(scope?: StorageTankReportScope): Record<string, string> {
  const productionLineId = scope?.productionLineId

  if (!productionLineId) {
    return {}
  }

  return { production_line_id: productionLineId }
}

/**
 * Respons /summary dikirim controller TANPA pembungkus `data`
 * (`response()->json($this->service->summary($period))`), sementara /periods
 * dan /business-units/options MEMAKAI pembungkus itu. Kedua bentuk diterima
 * di sini supaya repo ini tidak pecah bila pembungkusnya kelak diseragamkan
 * di sisi server.
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
 * GET /api/storage-tank-reports/business-units/options — pemilih Mill, ADMIN
 * SAJA (server menjawab 403 untuk peran lain, termasuk Operator: pelebaran
 * screen-139 sengaja TIDAK menyentuh endpoint ini). View TIDAK BOLEH
 * memanggilnya untuk peran yang terikat mill: selain percuma, permintaan itu
 * akan membentuk daftar seluruh mill yang memang tidak berhak dilihat.
 */
export async function fetchBusinessUnits(): Promise<StorageTankReportBusinessUnitOption[]> {
  const response = await apiClient.get('/api/storage-tank-reports/business-units/options')

  return (response.data?.data ?? []) as StorageTankReportBusinessUnitOption[]
}

/**
 * GET /api/storage-tank-reports/periods — periode yang mencakup stasiun
 * Storage Tank, yakni periode yang punya baris period_stations berjenis
 * 'storage-tank'. Penyaringannya dikerjakan SERVER; repo meneruskan daftar
 * apa adanya, dalam urutan yang sama — menyaringnya kedua kali di sini
 * akan menciptakan definisi cakupan yang kedua. Daftar kosong adalah jawaban yang sah
 * (HTTP 200 + []), bukan galat: mill itu memang belum punya periode, dan
 * layar menampilkannya sebagai arahan menghubungi Admin.
 */
export async function fetchPeriods(
  scope?: StorageTankReportScope,
): Promise<StorageTankReportPeriodOption[]> {
  const params = scopeParams(scope)

  const response = await apiClient.get('/api/storage-tank-reports/periods', { params })

  return (response.data?.data ?? []) as StorageTankReportPeriodOption[]
}

/**
 * GET /api/storage-tank-reports/summary — seluruh angka layar untuk satu
 * periode.
 *
 * Setiap blok diteruskan APA ADANYA, tanpa satu operasi aritmetika pun:
 * tidak ada pengurangan closing_mt − opening_mt menjadi pergerakan, tidak ada
 * perataan ketiga suhu posisi menjadi suhu rata-rata, tidak ada volume atau
 * kedalaman sounding yang ikut membentuk stok, tidak ada pembulatan ulang
 * coverage_percent, tidak ada penurunan has_data, dan tidak ada pengurutan
 * atau penyaringan `daily` / `by_tank` (termasuk baris bernilai nol dan
 * tangki ber-reading_count 0, yang justru harus terbaca). Nilai bawaan di
 * atas hanya berlaku bila BLOK-nya tidak ada sama sekali pada respons.
 */
export async function fetchSummary(
  periodId: string,
  scope?: StorageTankReportScope,
): Promise<StorageTankReportSummary> {
  const response = await apiClient.get('/api/storage-tank-reports/summary', {
    params: { period_id: periodId, ...scopeParams(scope), ...productionLineParams(scope) },
  })

  const body = (unwrap<Record<string, unknown>>(response.data) ?? {}) as Record<string, unknown>

  return {
    period: (body.period ?? null) as StorageTankReportPeriodHeader | null,
    production_line: (body.production_line ?? null) as StorageTankReportProductionLineRef | null,
    business_unit: (body.business_unit ?? null) as StorageTankReportBusinessUnitRef | null,
    has_data: (body.has_data ?? false) as boolean,
    coverage: (body.coverage ?? { ...EMPTY_COVERAGE }) as StorageTankReportCoverage,
    stock: (body.stock ?? { ...EMPTY_STOCK }) as StorageTankReportStock,
    metrics: (body.metrics ?? { ...EMPTY_METRICS }) as StorageTankReportMetrics,
    by_tank: (body.by_tank ?? []) as StorageTankReportTankRow[],
    daily: (body.daily ?? []) as StorageTankReportDailyRow[],
    total: (body.total ?? { ...EMPTY_TOTAL }) as StorageTankReportTotal,
  }
}

/**
 * GET /api/storage-tank-reports/export?period_id=...&format=csv — respons
 * streaming text/csv, BUKAN JSON, jadi responseType-nya blob. Isinya (satu
 * baris per slot waktu, dengan EMPAT kolom konteks record diulang dan ketujuh
 * belas kolom pengukuran termasuk enum katup uap dan kolom teks verbatim)
 * dibentuk SERVER dan sama persis dengan ekspor laporan versi web, karena
 * endpoint-nya memang sama. Repo tidak pernah menyusun CSV dari angka yang
 * sedang tampil di layar.
 */
export async function exportCsv(periodId: string, scope?: StorageTankReportScope): Promise<Blob> {
  const response = await apiClient.get('/api/storage-tank-reports/export', {
    params: { period_id: periodId, format: 'csv', ...scopeParams(scope), ...productionLineParams(scope) },
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

export const storageTankReportRepo = {
  fetchBusinessUnits,
  fetchPeriods,
  fetchSummary,
  exportCsv,
  saveCsvFile,
}

export default storageTankReportRepo
