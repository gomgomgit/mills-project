import apiClient from '@/services/apiClient'

/**
 * weighbridgeReportRepo — screen-144--laporan-weighbridge-mobile /
 * usecase-147--laporan-weighbridge-mobile "Lihat Laporan Periode Weighbridge
 * (Mobile)".
 *
 * Kembaran keenam dari pola yang sama: sterilizerReportRepo.ts (screen-135),
 * cagesTrackReportRepo.ts (screen-136), boilerRoomReportRepo.ts (screen-137),
 * clarificationReportRepo.ts (screen-138), dan storageTankReportRepo.ts
 * (screen-139). Satu berkas repo tipis di atas apiClient, tanpa cache lokal.
 * Layar laporan ini memang MEMBUTUHKAN jaringan, jadi tidak ada jalur SQLite
 * offline di sini sama sekali — kegagalan jaringan adalah kondisi yang
 * ditampilkan ke pengguna, bukan yang disembunyikan di balik data basi.
 *
 * NOL ENDPOINT BARU. Keempat endpoint milik
 * screen-143--laporan-weighbridge-web dipakai ulang apa adanya
 * (App\Http\Controllers\Api\WeighbridgeReportController). Perubahan backend
 * untuk layar ini hanya soal SIAPA yang boleh memanggil — TEPAT TIGA
 * PERUBAHAN KODE:
 *   1. routes/api.php: grup rute weighbridge-reports mendapat peran
 *      `operator` (guard 'auth:web,sanctum' sudah ada sebelumnya);
 *   2. WeighbridgeReportService::guardAccess() menerima UserRole::Operator;
 *   3. — dan yang paling mudah terlewat —
 *      WeighbridgeReportService::resolveBusinessUnit() memasukkan Operator ke
 *      cabang TERIKAT MILL, bukan membiarkannya jatuh ke cabang Admin yang
 *      tidak terikat, tempat business_unit_id kiriman klien DIHORMATI.
 *
 * NOL PERHITUNGAN ULANG DI KLIEN. Berkas ini tidak pernah menjumlah,
 * merata-ratakan, membulatkan, mengurutkan, menyaring, atau menurunkan satu
 * angka pun. Seluruh nilai diteruskan apa adanya dari respons server, karena
 * laporan web memakai sumber yang sama (WeighbridgeReportService) —
 * perhitungan kedua di sisi klien pasti akan menyimpang dari laporan web
 * tanpa ketahuan.
 *
 * ────────────────────────────────────────────────────────────────────────
 * YANG MEMBEDAKAN LAPORAN INI DARI LIMA LAPORAN SEBELUMNYA
 * ────────────────────────────────────────────────────────────────────────
 * Kelima laporan stasiun sebelumnya meringkas PEMBACAAN berkala dari satu
 * alat. Laporan ini meringkas TRANSAKSI: satu baris weighbridge_records
 * adalah satu kali kendaraan ditimbang, dan tabel ini TIDAK punya tabel
 * detail. Tiga akibatnya, dan ketiganya menentukan bentuk berkas ini:
 *
 * 1. SEGALANYA DIPISAH PER JENIS ARUS, dan keduanya TIDAK PERNAH DIJUMLAHKAN.
 *    `receive` adalah FFB yang datang dari estate/supplier; `dispatch` adalah
 *    kiriman yang meninggalkan pabrik. Server memulangkan dua kelompok metrik
 *    yang berdiri sendiri dan TIDAK SATU PUN kuncinya menjumlahkan keduanya.
 *    Menjumlahkannya di sini akan menghasilkan "berapa ton yang ditimbang
 *    hari ini" — bilangan yang tidak menjawab pertanyaan siapa pun, dan yang
 *    paling berbahaya justru karena terlihat masuk akal.
 *
 * 2. PRODUCTION LINE WAJIB, bukan opsional. Pada kelima repo sebelumnya
 *    production_line_id opsional di lapisan API, sehingga view-nya boleh
 *    memanggil /summary tanpa line dan mendapat angka seluruh mill. Di sini
 *    server menjawab 422 bila parameter itu absen, dan itu memang yang
 *    diinginkan: total lintas line bukan angka yang dapat ditindaklanjuti
 *    siapa pun. Penjagaannya ada di view (loadSummary berhenti lebih awal);
 *    repo tetap tidak mengirim parameter kosong sebagai parameter kosong.
 *
 * 3. TIDAK ADA LAMA KENDARAAN DI PABRIK, dan ketiadaannya disengaja. Migrasi
 *    2026_08_19_000010 menggabungkan dua kolom waktu lama menjadi satu
 *    `record_datetime`, jadi satu trip membawa TEPAT SATU penanda waktu dan
 *    durasi tidak dapat dihitung sama sekali. Penggantinya adalah sebaran
 *    trip per jam (`hourly`), yang memang dapat diturunkan dari satu penanda
 *    waktu. Jangan menambahkan medan durasi apa pun di sini: angka semacam
 *    itu akan terbaca sah padahal tidak punya dasar.
 *
 * NULL BUKAN NOL. net_weight_total, net_weight_avg, busiest_hour, seluruh
 * kolom *_net_weight_total pada `daily` dan `daily_total`, serta
 * net_weight_total pada tiap baris `by_origin`/`by_destination` boleh null.
 * Mengoersinya menjadi 0 (atau '' / '-') di sini akan mengubah "tidak pernah
 * ditimbang sampai selesai" menjadi "hasilnya nol" — dua fakta yang berbeda,
 * dan yang satu menyesatkan. Penerjemahan null menjadi teks yang terbaca
 * adalah urusan view, bukan repo.
 *
 * DUA BENTUK "BELUM DIISI" DALAM SATU PAYLOAD, dan keduanya harus lewat apa
 * adanya: server memanggil groupedBreakdownOf() dengan keepNull=false untuk
 * estate_supplier (asal yang belum diisi menjadi STRING KOSONG) dan
 * keepNull=true untuk destination (tujuan yang belum diisi tetap NULL).
 * Keduanya adalah KELOMPOK, bukan baris yang layak dibuang — jumlah trip
 * seluruh kelompok harus tetap menjumlah ke trip_count arusnya.
 *
 * GALAT DITERUSKAN APA ADANYA. Tidak ada try/catch dan tidak ada kelas galat
 * khusus di sini. Perlu dicatat bentuknya: interceptor apiClient menolak
 * lewat normalizeError yang mengembalikan objek DATAR
 * { message, errors?, status? } dan MEMBUANG `response` — jadi `.response`
 * tidak pernah ada pada galat yang sampai ke pemanggil. 401 diterjemahkan
 * menjadi "arahkan ke Login" oleh LaporanWeighbridgeView (membaca
 * `error.status`), dan kegagalan transport (penolakan tanpa `status`) menjadi
 * pesan + tombol Coba Lagi di sana pula.
 *
 * GAGAL TERTUTUP PADA business_unit_id: parameter ini hanya dikirim bila
 * pemanggil menyatakan dirinya Admin (`isAdmin: true`). Bagi Operator,
 * Supervisor, dan Mill Management mill diambil dari akun di sisi server
 * (WeighbridgeReportService::resolveBusinessUnit), sehingga sebuah
 * business_unit_id yang dipaksakan pemanggil TIDAK PERNAH ikut terkirim —
 * dijaga di sini, bukan hanya di view, supaya tidak bergantung pada
 * kedisiplinan pemanggil.
 */

export interface WeighbridgeReportBusinessUnitOption {
  id: string
  name: string
}

export interface WeighbridgeReportPeriodOption {
  id: string
  name: string
  start_date: string
  end_date: string
  /**
   * Status STASIUN LAYAR INI di dalam periode itu (baris period_stations),
   * BUKAN status periode: sejak 2026-09-25 periode tidak punya status sendiri
   * karena stasiun tidak ditutup serentak.
   */
  status: string
  /** Selalu terisi 'weighbridge' — TIDAK pernah null. */
  station_type: string
  station_type_label: string
}

export interface WeighbridgeReportPeriodHeader {
  id: string
  name: string
  start_date: string
  end_date: string
  status: string
}

export interface WeighbridgeReportBusinessUnitRef {
  id: string
  name: string
}

/**
 * Blok `production_line` pada respons /summary. BERBEDA dari lima laporan
 * sebelumnya ia tidak pernah null dalam praktik, karena production_line_id
 * WAJIB — jawaban tanpa line adalah 422, bukan 200 ber-null. Tipenya tetap
 * nullable agar view tidak pecah bila bentuk respons berubah.
 *
 * Ini SATU-SATUNYA sumber nama Production Line yang server akui untuk angka
 * yang sedang ditampilkan — layar boleh memakai nama dari daftarnya sendiri
 * sebagai cadangan, tetapi nilai inilah yang benar-benar menyertai angkanya.
 */
export interface WeighbridgeReportProductionLineRef {
  id: string
  name: string
}

/** Satu ember jam dalam sehari. Selalu 24 entri, termasuk yang bernilai 0. */
export interface WeighbridgeReportHourlyRow {
  hour: number
  trip_count: number
}

/**
 * Rekap arus MASUK per asal. `estate_supplier` adalah STRING KOSONG untuk
 * asal yang belum diisi (server memakai keepNull=false di sini) — kelompok,
 * bukan baris yang dibuang.
 */
export interface WeighbridgeReportOriginRow {
  estate_supplier: string
  trip_count: number
  net_weight_total: number | null
  net_weight_trip_count: number
}

/**
 * Rekap arus KELUAR per tujuan. `destination` bernilai NULL untuk tujuan yang
 * belum diisi (server memakai keepNull=true di sini) — berbeda bentuk dari
 * by_origin di atas, dan perbedaan itu nyata pada payload, bukan kelalaian.
 */
export interface WeighbridgeReportDestinationRow {
  destination: string | null
  trip_count: number
  net_weight_total: number | null
  net_weight_trip_count: number
}

/**
 * Satu kelompok arus. SETIAP metrik punya penyebutnya sendiri:
 * net_weight_trip_count adalah penyebut net_weight_avg, dan ia diterbitkan
 * server berdampingan dengan rata-ratanya justru supaya tidak ada yang
 * menurunkannya sendiri dari trip_count.
 *
 * missing_net_weight_trip_count sudah dihitung server. JANGAN menurunkannya
 * dari trip_count − net_weight_trip_count di klien: hari ini hasilnya sama,
 * dan begitu definisinya berubah di server, versi klien akan menyimpang tanpa
 * satu pun kegagalan test.
 */
export interface WeighbridgeReportFlow {
  trip_count: number
  net_weight_total: number | null
  net_weight_avg: number | null
  net_weight_trip_count: number
  missing_net_weight_trip_count: number
  hourly: WeighbridgeReportHourlyRow[]
  busiest_hour: number | null
  busiest_hour_trip_count: number
  empty_hour_count: number
  /** Hanya pada arus masuk. */
  by_origin?: WeighbridgeReportOriginRow[]
  /** Hanya pada arus keluar. */
  by_destination?: WeighbridgeReportDestinationRow[]
}

/**
 * Satu baris rekap harian. Kedua arus punya KOLOMNYA SENDIRI dan tidak
 * pernah bertemu dalam satu angka, termasuk di sini.
 */
export interface WeighbridgeReportDailyRow {
  date: string
  receive_trip_count: number
  receive_net_weight_total: number | null
  dispatch_trip_count: number
  dispatch_net_weight_total: number | null
}

export interface WeighbridgeReportDailyTotal {
  receive_trip_count: number
  receive_net_weight_total: number | null
  dispatch_trip_count: number
  dispatch_net_weight_total: number | null
}

/**
 * Kelengkapan pencatatan adalah ISI laporan, bukan metadata.
 *
 * days_counted adalah penyebut persen hari bertrip: sama dengan
 * days_in_period untuk periode yang sudah selesai, berhenti di HARI INI (WIB)
 * untuk periode yang masih berjalan, dan 0 untuk periode yang belum mulai
 * (temuan audit 2026-10-04, App\Support\ReportPeriodDays).
 */
export interface WeighbridgeReportCompleteness {
  days_in_period: number
  days_with_trip: number
  days_counted: number
  period_running: boolean
}

export interface WeighbridgeReportSummary {
  business_unit: WeighbridgeReportBusinessUnitRef | null
  production_line: WeighbridgeReportProductionLineRef | null
  period: WeighbridgeReportPeriodHeader | null
  receive: WeighbridgeReportFlow
  dispatch: WeighbridgeReportFlow
  /**
   * Melintasi kedua arus — dan itu bukan pengecualian terhadap "arus tidak
   * pernah dijumlahkan": keduanya adalah penghitung KELENGKAPAN, bukan
   * metrik. draft_trip_count menyatakan seberapa besar laporan ini berdiri di
   * atas data yang belum selesai; undated_trip_count menyatakan berapa baris
   * yang TIDAK dapat ditempatkan pada periode mana pun dan karena itu tidak
   * ikut terhitung di angka mana pun.
   */
  draft_trip_count: number
  undated_trip_count: number
  daily: WeighbridgeReportDailyRow[]
  daily_total: WeighbridgeReportDailyTotal
  completeness: WeighbridgeReportCompleteness
}

/**
 * Parameter opsional pemilih mill dan line. `isAdmin` sengaja WAJIB dinyatakan
 * eksplisit oleh pemanggil yang hendak mengirim business_unit_id — lihat
 * catatan "gagal tertutup" pada docblock berkas.
 */
export interface WeighbridgeReportScope {
  isAdmin?: boolean
  businessUnitId?: string | null
  /**
   * Production Line yang angkanya diminta. Dikirim ke /summary dan /export
   * saja — lihat productionLineParams(). WAJIB di layar ini: tanpa nilai ini
   * server menjawab 422, dan view memang tidak memanggil /summary sebelum
   * sebuah line berlaku.
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
const EMPTY_RECEIVE: WeighbridgeReportFlow = {
  trip_count: 0,
  net_weight_total: null,
  net_weight_avg: null,
  net_weight_trip_count: 0,
  missing_net_weight_trip_count: 0,
  hourly: [],
  busiest_hour: null,
  busiest_hour_trip_count: 0,
  empty_hour_count: 0,
  by_origin: [],
}

const EMPTY_DISPATCH: WeighbridgeReportFlow = {
  trip_count: 0,
  net_weight_total: null,
  net_weight_avg: null,
  net_weight_trip_count: 0,
  missing_net_weight_trip_count: 0,
  hourly: [],
  busiest_hour: null,
  busiest_hour_trip_count: 0,
  empty_hour_count: 0,
  by_destination: [],
}

const EMPTY_DAILY_TOTAL: WeighbridgeReportDailyTotal = {
  receive_trip_count: 0,
  receive_net_weight_total: null,
  dispatch_trip_count: 0,
  dispatch_net_weight_total: null,
}

const EMPTY_COMPLETENESS: WeighbridgeReportCompleteness = {
  days_in_period: 0,
  days_with_trip: 0,
  days_counted: 0,
  period_running: false,
}

/**
 * business_unit_id HANYA dikirim untuk Admin. Untuk peran lain fungsi ini
 * mengembalikan objek kosong, apa pun yang dikirimkan pemanggil — kuncinya
 * adalah medan itu ABSEN, bukan null dan bukan string kosong. Operator ada di
 * cabang terikat-mill ini, sama seperti Supervisor dan Mill Management.
 */
function scopeParams(scope?: WeighbridgeReportScope): Record<string, string> {
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
 * Line (periods.business_unit_id, tanpa production_line_id), dan backend pun
 * tidak menerima parameter ini di sana. Menyaring daftar periode per line
 * akan mengarang penyempitan yang tidak ada di data.
 *
 * Berbeda dari scopeParams(), fungsi ini TIDAK bercabang berdasarkan peran:
 * production_line_id bukan kewenangan melainkan konteks angka. Yang dijaga di
 * sini hanya satu: nilai kosong tidak pernah dikirim sebagai parameter kosong
 * — server akan menjawab 422 tentang parameter yang hilang, dan itu pesan
 * yang benar, sedangkan parameter kosong adalah pesan yang membingungkan.
 */
function productionLineParams(scope?: WeighbridgeReportScope): Record<string, string> {
  const productionLineId = scope?.productionLineId

  if (!productionLineId) {
    return {}
  }

  return { production_line_id: productionLineId }
}

/**
 * Respons /summary dikirim controller TANPA pembungkus `data`
 * (`response()->json($this->service->buildSummary(...))`), sementara /periods
 * dan /business-units/options MEMAKAI pembungkus itu. Kedua bentuk diterima di
 * sini supaya repo ini tidak pecah bila pembungkusnya kelak diseragamkan di
 * sisi server.
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
 * GET /api/weighbridge-reports/business-units/options — pemilih Mill, ADMIN
 * SAJA. Server menjawab 403 untuk peran lain, TERMASUK Operator: perluasan
 * screen-144 sengaja TIDAK menyentuh endpoint ini. View TIDAK BOLEH
 * memanggilnya untuk peran yang terikat mill — selain percuma, permintaan itu
 * akan membentuk daftar seluruh mill yang memang tidak berhak dilihat.
 */
export async function fetchBusinessUnits(): Promise<WeighbridgeReportBusinessUnitOption[]> {
  const response = await apiClient.get('/api/weighbridge-reports/business-units/options')

  return (response.data?.data ?? []) as WeighbridgeReportBusinessUnitOption[]
}

/**
 * GET /api/weighbridge-reports/periods — periode yang mencakup stasiun
 * Weighbridge, yakni periode yang punya baris period_stations berjenis
 * 'weighbridge'. Penyaringannya dikerjakan SERVER; repo meneruskan daftar apa
 * adanya, dalam urutan yang sama — menyaringnya kedua kali di sini akan
 * menciptakan definisi cakupan yang kedua. Daftar kosong adalah jawaban yang
 * sah (HTTP 200 + []), bukan galat: mill itu memang belum punya periode, dan
 * layar menampilkannya sebagai arahan menghubungi Admin.
 *
 * production_line_id TIDAK dikirim ke sini, dengan sengaja — lihat
 * productionLineParams().
 */
export async function fetchPeriods(
  scope?: WeighbridgeReportScope,
): Promise<WeighbridgeReportPeriodOption[]> {
  const params = scopeParams(scope)

  const response = await apiClient.get('/api/weighbridge-reports/periods', { params })

  return (response.data?.data ?? []) as WeighbridgeReportPeriodOption[]
}

/**
 * GET /api/weighbridge-reports/summary — seluruh angka layar untuk satu
 * periode pada satu Production Line.
 *
 * Setiap blok diteruskan APA ADANYA, tanpa satu operasi aritmetika pun: tidak
 * ada penjumlahan `hourly`, tidak ada perata-rataan, tidak ada pembulatan,
 * tidak ada pengurutan ulang by_origin/by_destination, tidak ada penurunan
 * missing_net_weight_trip_count dari trip_count − net_weight_trip_count, dan
 * TIDAK ADA satu pun angka yang menggabungkan arus masuk dengan arus keluar.
 * Nilai bawaan di atas hanya berlaku bila BLOK-nya tidak ada sama sekali pada
 * respons.
 */
export async function fetchSummary(
  periodId: string,
  scope?: WeighbridgeReportScope,
): Promise<WeighbridgeReportSummary> {
  const response = await apiClient.get('/api/weighbridge-reports/summary', {
    params: { period_id: periodId, ...scopeParams(scope), ...productionLineParams(scope) },
  })

  const body = (unwrap<Record<string, unknown>>(response.data) ?? {}) as Record<string, unknown>

  return {
    business_unit: (body.business_unit ?? null) as WeighbridgeReportBusinessUnitRef | null,
    production_line: (body.production_line ?? null) as WeighbridgeReportProductionLineRef | null,
    period: (body.period ?? null) as WeighbridgeReportPeriodHeader | null,
    receive: (body.receive ?? { ...EMPTY_RECEIVE }) as WeighbridgeReportFlow,
    dispatch: (body.dispatch ?? { ...EMPTY_DISPATCH }) as WeighbridgeReportFlow,
    draft_trip_count: (body.draft_trip_count ?? 0) as number,
    undated_trip_count: (body.undated_trip_count ?? 0) as number,
    daily: (body.daily ?? []) as WeighbridgeReportDailyRow[],
    daily_total: (body.daily_total ?? { ...EMPTY_DAILY_TOTAL }) as WeighbridgeReportDailyTotal,
    completeness: (body.completeness ??
      { ...EMPTY_COMPLETENESS }) as WeighbridgeReportCompleteness,
  }
}

/**
 * GET /api/weighbridge-reports/export?period_id=...&production_line_id=...&format=csv
 * — respons streaming text/csv, BUKAN JSON, jadi responseType-nya blob.
 *
 * Isinya (SATU BARIS PER TRIP, dengan kolom konteks Periode/Mill/Production
 * Line/jenis arus diulang verbatim di setiap baris, TEPAT SATU kolom penanda
 * waktu, dan NOL kolom durasi) dibentuk SERVER dan sama persis dengan ekspor
 * laporan versi web, karena endpoint-nya memang sama. Repo tidak pernah
 * menyusun CSV dari angka yang sedang tampil di layar.
 *
 * Format 'csv' saja: ekspor .xlsx hanya ada di layar web. Trip yang
 * net_weight-nya kosong tetap satu baris dengan sel KOSONG — tidak dibuang,
 * dan tidak ditulis 0.
 */
export async function exportCsv(
  periodId: string,
  scope?: WeighbridgeReportScope,
): Promise<Blob> {
  const response = await apiClient.get('/api/weighbridge-reports/export', {
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

export const weighbridgeReportRepo = {
  fetchBusinessUnits,
  fetchPeriods,
  fetchSummary,
  exportCsv,
  saveCsvFile,
}

export default weighbridgeReportRepo
