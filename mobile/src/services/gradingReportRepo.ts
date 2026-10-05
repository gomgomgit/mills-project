import apiClient from '@/services/apiClient'

/**
 * gradingReportRepo — screen-147--laporan-grading-mobile /
 * usecase-150--laporan-grading-mobile "Lihat Laporan Periode Grading
 * (Mobile)".
 *
 * Kembaran ketujuh dari pola yang sama: sterilizerReportRepo.ts (screen-135),
 * cagesTrackReportRepo.ts (screen-136), boilerRoomReportRepo.ts (screen-137),
 * clarificationReportRepo.ts (screen-138), storageTankReportRepo.ts
 * (screen-139), dan weighbridgeReportRepo.ts (screen-144). Satu berkas repo
 * tipis di atas apiClient, tanpa cache lokal. Layar laporan ini memang
 * MEMBUTUHKAN jaringan, jadi tidak ada jalur SQLite offline di sini sama
 * sekali — kegagalan jaringan adalah kondisi yang ditampilkan ke pengguna,
 * bukan yang disembunyikan di balik data basi.
 *
 * NOL ENDPOINT BARU DAN NOL PERUBAHAN BACKEND, dan yang kedua itu membedakan
 * layar ini dari screen-144. Laporan Weighbridge mobile harus melebarkan rute
 * /api/weighbridge-reports/* dengan tiga perubahan berpasangan, karena
 * kembarannya datang berbulan setelah layar web-nya. Di sini keempat endpoint
 * /api/grading-reports/* sudah menerima peran mobile sejak
 * screen-146--laporan-grading-web dibangun — kedua layar direncanakan dalam
 * satu seri, jadi mengirim daftar peran yang pasti harus dilebarkan satu jam
 * kemudian hanya akan menjadi pertunjukan.
 *
 * Yang tetap berlaku, dan sudah dikunci test di sisi web: Operator berada di
 * cabang TERIKAT MILL pada GradingReportService::resolveBusinessUnit() sejak
 * baris pertama, dan /business-units/options tetap menolaknya dengan 403 —
 * peran yang terikat satu mill tidak punya pemilih, dan menyerahkan daftar
 * seluruh mill kepadanya adalah justru kebocoran yang dihindari.
 *
 * NOL PERHITUNGAN ULANG DI KLIEN. Berkas ini tidak pernah menjumlah,
 * merata-ratakan, membulatkan, mengurutkan, menyaring, atau menurunkan satu
 * angka pun. Seluruh nilai diteruskan apa adanya dari respons server, karena
 * laporan web memakai sumber yang sama (GradingReportService) — perhitungan
 * kedua di sisi klien pasti akan menyimpang dari laporan web tanpa ketahuan.
 *
 * ────────────────────────────────────────────────────────────────────────
 * YANG MEMBEDAKAN LAPORAN INI DARI ENAM LAPORAN SEBELUMNYA
 * ────────────────────────────────────────────────────────────────────────
 * Enam laporan stasiun sebelumnya meringkas THROUGHPUT atau PEMBACAAN alat.
 * Yang ini meringkas KOMPOSISI: satu baris grading_records adalah satu muatan
 * truk yang disortir, dan yang dicari pembaca adalah komposisi kematangan FFB
 * yang masuk. Tiga akibatnya, dan ketiganya menentukan bentuk berkas ini:
 *
 * 1. DUA BLOK SATUAN YANG TIDAK PERNAH DIJUMLAHKAN. Tiga belas parameter
 *    dihitung dalam JANJANG; tiga parameter brondolan ditimbang dalam
 *    KILOGRAM. "Total kuantitas" atas keduanya bukan bilangan apa pun. Server
 *    memulangkan dua blok — `bunch` dan `kg` — yang masing-masing membawa
 *    quantity_total yang sekaligus menjadi penyebut share_percent-nya sendiri.
 *    TIDAK ADA satu pun kunci yang menjumlahkan keduanya, dan repo ini tidak
 *    boleh membuatnya.
 *
 * 2. DUA ANGKA PER PARAMETER, DAN KEDUANYA DITERUSKAN. `share_percent` adalah
 *    pangsa terhadap total periode — BERBOBOT menurut besar muatan.
 *    `avg_percentage` adalah rata-rata persentase per muatan — tiap muatan
 *    berbobot sama. Keduanya menjawab pertanyaan berbeda dan BERBEDA JAUH
 *    begitu ukuran muatan tidak seragam: satu muatan raksasa yang buruk
 *    mendominasi pangsa tanpa menggerakkan rata-rata. Meneruskan hanya salah
 *    satunya akan menyembunyikan separuh kenyataan.
 *
 * 3. PENYEBUT RATA-RATA IKUT DITERUSKAN, dan itu bukan kemewahan.
 *    `rows[].load_count` adalah banyaknya muatan yang BENAR-BENAR MENCATAT
 *    parameter itu — penyebut avg_percentage, dan satu-satunya hal yang
 *    membedakannya dari angka periode. Muatan yang tidak mencantumkan sebuah
 *    parameter TIDAK menilainya nol persen; ia tidak menilainya sama sekali.
 *
 * NULL BUKAN NOL. netto_total, netto_avg, bunch_total, bunch_avg,
 * quantity_total tiap blok, serta share_percent dan avg_percentage tiap baris
 * boleh null. Mengoersinya menjadi 0 (atau '' / '-') di sini akan mengubah
 * "tidak ada yang diukur" menjadi "hasilnya nol" — dua fakta yang berbeda, dan
 * yang satu menyesatkan. Penerjemahan null menjadi teks terbaca adalah urusan
 * view, bukan repo.
 *
 * ASAL YANG BELUM DIISI DATANG SEBAGAI STRING KOSONG, bukan null: server
 * menyatukan NULL dan '' menjadi satu kelompok '' (kolomnya sendiri NOT NULL).
 * Repo meneruskannya apa adanya; label "Belum diisi" adalah keputusan view.
 *
 * TIDAK ADA PENGHITUNG "TANPA TANGGAL" pada payload ini, dan ketiadaannya
 * disengaja: grading_records.date adalah kolom NOT NULL, jadi setiap muatan
 * selalu dapat ditempatkan pada sebuah periode. weighbridgeReportRepo punya
 * undated_trip_count hanya karena penanda waktunya nullable. Dicatat supaya
 * pembaca berikutnya tidak mengira medannya lupa dipetakan.
 *
 * GALAT DITERUSKAN APA ADANYA. Tidak ada try/catch dan tidak ada kelas galat
 * khusus di sini. Interceptor apiClient menolak lewat normalizeError yang
 * mengembalikan objek DATAR { message, errors?, status? } dan MEMBUANG
 * `response` — jadi `.response` tidak pernah ada pada galat yang sampai ke
 * pemanggil. 401 diterjemahkan menjadi "arahkan ke Login" oleh
 * LaporanGradingView (membaca `error.status`), dan kegagalan transport
 * (penolakan tanpa `status`) menjadi pesan + tombol Coba Lagi di sana pula.
 *
 * GAGAL TERTUTUP PADA business_unit_id: parameter ini hanya dikirim bila
 * pemanggil menyatakan dirinya Admin (`isAdmin: true`). Bagi Operator,
 * Supervisor, dan Mill Management mill diambil dari akun di sisi server,
 * sehingga business_unit_id yang dipaksakan pemanggil TIDAK PERNAH ikut
 * terkirim — dijaga di sini, bukan hanya di view.
 */

export interface GradingReportBusinessUnitOption {
  id: string
  name: string
}

export interface GradingReportPeriodOption {
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
  /** Selalu terisi 'grading' — TIDAK pernah null. */
  station_type: string
  station_type_label: string
}

export interface GradingReportPeriodHeader {
  id: string
  name: string
  start_date: string
  end_date: string
  status: string
}

export interface GradingReportBusinessUnitRef {
  id: string
  name: string
}

export interface GradingReportProductionLineRef {
  id: string
  name: string
}

/**
 * Satu baris parameter mutu di dalam SATU blok satuan.
 *
 * share_percent dan avg_percentage adalah DUA ANGKA YANG BERBEDA dan dapat
 * bertentangan — lihat catatan 2 pada docblock berkas. load_count adalah
 * PENYEBUT avg_percentage, bukan jumlah muatan periode, dan ia diterbitkan
 * server justru supaya tidak ada yang menurunkannya sendiri.
 */
export interface GradingReportParameterRow {
  grading_parameter_id: string
  name: string
  quantity_total: number
  /** Pangsa terhadap total blok SATUANNYA SENDIRI. null bila penyebutnya 0. */
  share_percent: number | null
  /** Rata-rata kolom percentage baris parameter ini, bukan turunan apa pun. */
  avg_percentage: number | null
  /** Banyaknya muatan yang MENCATAT parameter ini — penyebut avg_percentage. */
  load_count: number
}

/**
 * Satu blok satuan, berdiri sendiri. quantity_total-nya adalah penyebut
 * share_percent seluruh barisnya, dan ia TIDAK PERNAH dijumlahkan dengan
 * quantity_total blok satuan yang lain.
 *
 * quantity_total null berarti satuan ini tidak pernah muncul pada periode itu.
 * Blok-nya TETAP dikirim server supaya layar dapat menampilkannya sebagai
 * "tidak tersedia" alih-alih menyembunyikannya — bagian yang hilang terbaca
 * sebagai "tidak ada bagian ini", sedangkan bagian berisi "tidak tersedia"
 * terbaca sebagai "tidak ada isinya", dan hanya yang kedua benar.
 */
export interface GradingReportParameterBlock {
  quantity_total: number | null
  parameter_count: number
  rows: GradingReportParameterRow[]
}

export interface GradingReportEstateSupplierRow {
  /** '' untuk asal yang belum diisi — sebuah KELOMPOK, bukan baris yang dibuang. */
  estate_supplier: string
  load_count: number
  netto_total: number | null
  bunch_total: number | null
}

export interface GradingReportDailyRow {
  date: string
  load_count: number
  netto_total: number | null
  bunch_total: number | null
}

export interface GradingReportDailyTotal {
  load_count: number
  netto_total: number | null
  bunch_total: number | null
}

/**
 * Kelengkapan pencatatan adalah ISI laporan, bukan metadata.
 *
 * days_counted adalah penyebut persen hari ber-muatan: sama dengan
 * days_in_period untuk periode yang sudah selesai, berhenti di HARI INI (WIB)
 * untuk periode yang masih berjalan, dan 0 untuk periode yang belum mulai
 * (App\Support\ReportPeriodDays).
 */
export interface GradingReportCompleteness {
  days_in_period: number
  days_with_load: number
  days_counted: number
  period_running: boolean
}

export interface GradingReportSummary {
  business_unit: GradingReportBusinessUnitRef | null
  production_line: GradingReportProductionLineRef | null
  period: GradingReportPeriodHeader | null
  load_count: number
  netto_total: number | null
  netto_avg: number | null
  bunch_total: number | null
  bunch_avg: number | null
  /** Parameter ber-satuan JANJANG. Tidak pernah bertemu `kg` dalam satu angka. */
  bunch: GradingReportParameterBlock
  /** Parameter ber-satuan KILOGRAM (ketiga brondolan). */
  kg: GradingReportParameterBlock
  by_estate_supplier: GradingReportEstateSupplierRow[]
  /**
   * Muatan yang tercatat dan ditimbang tetapi belum punya satu pun baris
   * parameter. Ia ADA di dalam load_count/netto_total/bunch_total dan TIDAK
   * ADA di kedua blok parameter — angka ini satu-satunya cara pembaca
   * menjelaskan selisih itu.
   */
  loads_without_detail: number
  draft_load_count: number
  loads_without_division: number
  loads_not_checked: number
  loads_not_acknowledged: number
  daily: GradingReportDailyRow[]
  daily_total: GradingReportDailyTotal
  completeness: GradingReportCompleteness
}

/**
 * Parameter opsional pemilih mill dan line. `isAdmin` sengaja WAJIB dinyatakan
 * eksplisit oleh pemanggil yang hendak mengirim business_unit_id — lihat
 * catatan "gagal tertutup" pada docblock berkas.
 */
export interface GradingReportScope {
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
const EMPTY_PARAMETER_BLOCK: GradingReportParameterBlock = {
  quantity_total: null,
  parameter_count: 0,
  rows: [],
}

const EMPTY_DAILY_TOTAL: GradingReportDailyTotal = {
  load_count: 0,
  netto_total: null,
  bunch_total: null,
}

const EMPTY_COMPLETENESS: GradingReportCompleteness = {
  days_in_period: 0,
  days_with_load: 0,
  days_counted: 0,
  period_running: false,
}

/**
 * business_unit_id HANYA dikirim untuk Admin. Untuk peran lain fungsi ini
 * mengembalikan objek kosong, apa pun yang dikirimkan pemanggil — kuncinya
 * adalah medan itu ABSEN, bukan null dan bukan string kosong. Operator ada di
 * cabang terikat-mill ini, sama seperti Supervisor dan Mill Management.
 */
function scopeParams(scope?: GradingReportScope): Record<string, string> {
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
function productionLineParams(scope?: GradingReportScope): Record<string, string> {
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
 * GET /api/grading-reports/business-units/options — pemilih Mill, ADMIN SAJA.
 * Server menjawab 403 untuk peran lain, TERMASUK Operator: ini satu-satunya
 * rute pada prefix ini yang tidak terbuka baginya, dan itu disengaja. View
 * TIDAK BOLEH memanggilnya untuk peran yang terikat mill — selain percuma,
 * permintaan itu akan membentuk daftar seluruh mill yang memang tidak berhak
 * dilihat.
 */
export async function fetchBusinessUnits(): Promise<GradingReportBusinessUnitOption[]> {
  const response = await apiClient.get('/api/grading-reports/business-units/options')

  return (response.data?.data ?? []) as GradingReportBusinessUnitOption[]
}

/**
 * GET /api/grading-reports/periods — periode yang mencakup stasiun Grading,
 * yakni periode yang punya baris period_stations berjenis 'grading'.
 * Penyaringannya dikerjakan SERVER; repo meneruskan daftar apa adanya, dalam
 * urutan yang sama — menyaringnya kedua kali di sini akan menciptakan
 * definisi cakupan yang kedua. Daftar kosong adalah jawaban yang sah
 * (HTTP 200 + []), bukan galat.
 *
 * Periode TERTUTUP tetap terdaftar: status mengatur penulisan data, bukan
 * pembacaan laporan.
 */
export async function fetchPeriods(
  scope?: GradingReportScope,
): Promise<GradingReportPeriodOption[]> {
  const params = scopeParams(scope)

  const response = await apiClient.get('/api/grading-reports/periods', { params })

  return (response.data?.data ?? []) as GradingReportPeriodOption[]
}

/**
 * GET /api/grading-reports/summary — seluruh angka layar untuk satu periode
 * pada satu Production Line.
 *
 * Setiap blok diteruskan APA ADANYA, tanpa satu operasi aritmetika pun: tidak
 * ada penjumlahan quantity_total kedua satuan, tidak ada penurunan
 * share_percent dari kuantitas, tidak ada perata-rataan percentage, tidak ada
 * pengurutan ulang rows maupun by_estate_supplier, dan tidak ada penurunan
 * loads_without_detail dari selisih apa pun. Nilai bawaan di atas hanya
 * berlaku bila BLOK-nya tidak ada sama sekali pada respons.
 */
export async function fetchSummary(
  periodId: string,
  scope?: GradingReportScope,
): Promise<GradingReportSummary> {
  const response = await apiClient.get('/api/grading-reports/summary', {
    params: { period_id: periodId, ...scopeParams(scope), ...productionLineParams(scope) },
  })

  const body = (unwrap<Record<string, unknown>>(response.data) ?? {}) as Record<string, unknown>

  return {
    business_unit: (body.business_unit ?? null) as GradingReportBusinessUnitRef | null,
    production_line: (body.production_line ?? null) as GradingReportProductionLineRef | null,
    period: (body.period ?? null) as GradingReportPeriodHeader | null,
    load_count: (body.load_count ?? 0) as number,
    netto_total: (body.netto_total ?? null) as number | null,
    netto_avg: (body.netto_avg ?? null) as number | null,
    bunch_total: (body.bunch_total ?? null) as number | null,
    bunch_avg: (body.bunch_avg ?? null) as number | null,
    bunch: (body.bunch ?? { ...EMPTY_PARAMETER_BLOCK }) as GradingReportParameterBlock,
    kg: (body.kg ?? { ...EMPTY_PARAMETER_BLOCK }) as GradingReportParameterBlock,
    by_estate_supplier: (body.by_estate_supplier ?? []) as GradingReportEstateSupplierRow[],
    loads_without_detail: (body.loads_without_detail ?? 0) as number,
    draft_load_count: (body.draft_load_count ?? 0) as number,
    loads_without_division: (body.loads_without_division ?? 0) as number,
    loads_not_checked: (body.loads_not_checked ?? 0) as number,
    loads_not_acknowledged: (body.loads_not_acknowledged ?? 0) as number,
    daily: (body.daily ?? []) as GradingReportDailyRow[],
    daily_total: (body.daily_total ?? { ...EMPTY_DAILY_TOTAL }) as GradingReportDailyTotal,
    completeness: (body.completeness ?? { ...EMPTY_COMPLETENESS }) as GradingReportCompleteness,
  }
}

/**
 * GET /api/grading-reports/export?period_id=...&production_line_id=...&format=csv
 * — respons streaming text/csv, BUKAN JSON, jadi responseType-nya blob.
 *
 * Isinya (SATU BARIS PER PARAMETER PER MUATAN, dengan konteks Periode/Mill/
 * Production Line dan identitas muatan diulang verbatim di setiap baris, dan
 * kolom Satuan pada setiap baris sehingga janjang dan kilogram tetap dapat
 * dibedakan tanpa pernah dijumlahkan) dibentuk SERVER dan sama persis dengan
 * ekspor laporan versi web, karena endpoint-nya memang sama. Muatan tanpa
 * satu pun baris parameter TETAP satu baris, dengan kolom parameter kosong.
 *
 * Format 'csv' saja: ekspor .xlsx hanya ada di layar web.
 */
export async function exportCsv(periodId: string, scope?: GradingReportScope): Promise<Blob> {
  const response = await apiClient.get('/api/grading-reports/export', {
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

export const gradingReportRepo = {
  fetchBusinessUnits,
  fetchPeriods,
  fetchSummary,
  exportCsv,
  saveCsvFile,
}

export default gradingReportRepo
