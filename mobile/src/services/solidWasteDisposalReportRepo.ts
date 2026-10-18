import apiClient from '@/services/apiClient'
import { productionLineRepo, type ProductionLineOption } from '@/services/productionLineRepo'

/**
 * solidWasteDisposalReportRepo — screen-163--laporan-solid-waste-disposal-mobile /
 * usecase-169--laporan-solid-waste-disposal-mobile "Lihat Laporan Periode
 * Solid Waste Disposal (Mobile)".
 *
 * Kembaran mobile KELIMA BELAS dari pola yang sama: sterilizerReportRepo.ts
 * (screen-135) sampai processWaterReportRepo.ts (screen-161). Satu berkas repo
 * tipis di atas apiClient, TANPA cache lokal. Layar laporan ini memang
 * MEMBUTUHKAN jaringan, jadi tidak ada jalur SQLite luring di sini sama
 * sekali — kegagalan jaringan adalah kondisi yang DITAMPILKAN ke pengguna,
 * bukan yang disembunyikan di balik data basi.
 *
 * ────────────────────────────────────────────────────────────────────────
 * NOL PERUBAHAN BACKEND, DAN ITU SUDAH DIBAYAR LEBIH DULU
 * ────────────────────────────────────────────────────────────────────────
 * Keempat endpoint SUDAH ADA: ketiga rute data /api/solid-waste-disposal-
 * reports/* dibangun screen-162 dan SUDAH menerima Operator sejak commit
 * pertamanya (lihat docblock SolidWasteDisposalReportController), dan
 * /api/production-lines/options-for-report dipakai ulang VERBATIM dari
 * screen-135. Akibatnya: NOL endpoint baru, NOL entri api-index baru, dan NOL
 * berkas di backend/ yang disentuh untuk layar ini. Bila sebuah test backend
 * memerah saat layar ini dibangun, sebabnya ada di tempat lain.
 *
 * ────────────────────────────────────────────────────────────────────────
 * ADA CABANG ADMIN DI SINI — BERBEDA DARI processWaterReportRepo.ts
 * ────────────────────────────────────────────────────────────────────────
 * Layar ini punya EMPAT aktor, dan Admin salah satunya: ia satu-satunya peran
 * yang TIDAK terikat mill, jadi `business_unit_id` memang punya pekerjaan di
 * berkas ini. Pola `isAdmin` wajib-eksplisit disalin dari
 * weighbridgeReportRepo.ts: parameter itu hanya terkirim bila PEMANGGIL
 * menyatakan dirinya Admin, sehingga business_unit_id yang dipaksakan
 * pemanggil untuk Operator / Supervisor / Mill Management TIDAK PERNAH ikut
 * terkirim — gagal tertutup di klien SEKALIGUS di server
 * (SolidWasteDisposalReportService::resolveBusinessUnit() membuang nilai
 * kiriman klien untuk ketiga peran terikat mill, Operator termasuk).
 *
 * `/business-units/options` TETAP ADMIN SAJA dan menjawab 403 untuk ketiga
 * peran lain — penolakannya diangkat DI DALAM service, bukan oleh middleware
 * rute, karena middleware prefix itu menerima keempat peran demi ketiga rute
 * data. Konsekuensinya untuk view: ia TIDAK BOLEH memanggil endpoint ini untuk
 * peran yang terikat mill. Selain percuma, permintaan itu akan membentuk
 * daftar SELURUH mill di perangkat orang yang tidak berhak melihat satu pun di
 * antaranya.
 *
 * ────────────────────────────────────────────────────────────────────────
 * BODY /summary DITERUSKAN UTUH — NOL KUNCI DIBUANG, NOL ANGKA DIUBAH
 * ────────────────────────────────────────────────────────────────────────
 * Daftar kunci yang dipaku di repo akan MEMBUANG kunci yang tidak ada di
 * daftar itu, dan kunci yang hilang tidak terlihat sampai seseorang membacanya
 * di layar. Dengan meneruskan body utuh, sebuah fixture lengkap tetap
 * deep-equal dengan hasil parse, dan kunci baru dari putaran server kelak
 * sampai ke view tanpa menyunting berkas ini.
 *
 * SATU KOSAKATA, BUKAN DUA. Berbeda dari processWaterReportRepo.ts yang harus
 * menerima dua penamaan untuk medan yang sama, payload layar ini punya SATU
 * nama per medan: tech spec screen-163 menyebut medan dengan nama yang sama
 * persis seperti SolidWasteDisposalReportService::buildSummary()
 * menerbitkannya (`loads.*`, `by_waste_type`, `by_source_station`,
 * `by_disposal_site`, `by_purpose_end_use`, `compliance.*`,
 * `draft_load_count`, `undated_load_count`, `daily`, `daily_total`,
 * `completeness`, `text_entries`, `_note_no_hourly`,
 * `_note_shift_not_published`, `_note_absences`). Tidak ada normalisasi yang
 * perlu dikerjakan, jadi tidak ada normalisasi yang dikerjakan.
 *
 * NULL BUKAN NOL. `net_weight_total`, `net_weight_avg` pada blok `loads`, pada
 * setiap baris keempat rekap, pada setiap baris `daily`, pada `daily_total`,
 * dan `completeness.percent` SELURUHNYA boleh null. Mengoersinya menjadi 0
 * (atau '' / '-') di sini akan mengubah "tidak ada yang ditimbang" menjadi
 * "hasilnya nol" — dan itu diputuskan PER MEDAN oleh view, bukan oleh sebuah
 * bawaan tunggal di berkas ini.
 *
 * ────────────────────────────────────────────────────────────────────────
 * YANG TIDAK ADA DI PAYLOAD INI, DAN ITU DISENGAJA
 * ────────────────────────────────────────────────────────────────────────
 * TIDAK ADA `hourly`, `busiest_hour`, maupun `empty_hour_count`: baris detail
 * stasiun ini hanya membawa `event_date` — sebuah tanggal — tanpa satu pun
 * kolom waktu. Sebaran per jam karena itu tidak dapat dihitung dalam bentuk
 * apa pun, dan ketiadaannya dinyatakan payload lewat `_note_no_hourly`. Repo
 * ini karena itu TIDAK mengetik ketiga kunci itu: yang tidak dapat ditulis
 * tidak dapat salah dibaca.
 *
 * TIDAK ADA dimensi `shift` dan TIDAK ADA `downtime` / `target` / severity.
 * Kolom `shift` bertipe teks bebas dan TETAP ikut di berkas ekspor; kedua
 * ketiadaan itu dinyatakan `_note_shift_not_published` dan `_note_absences`.
 *
 * GALAT DITERUSKAN APA ADANYA. Tidak ada try/catch dan tidak ada kelas galat
 * khusus di sini. Interceptor apiClient menolak lewat normalizeError yang
 * mengembalikan objek DATAR { message, errors?, status? } dan MEMBUANG
 * `response`. 401 diterjemahkan menjadi "arahkan ke Login TANPA pesan
 * jaringan" oleh LaporanSolidWasteDisposalView (membaca `error.status`), 403
 * pada /summary menjadi "pilih line lagi", 404 menjadi "periode tidak
 * ditemukan", 422 akun-tanpa-mill menjadi arahan menghubungi Admin, dan
 * kegagalan transport (penolakan tanpa `status`) menjadi pesan + tombol Coba
 * Lagi.
 *
 * LAYAR INI TIDAK MENULIS KE localDb. Tidak ada satu pun impor localDb di
 * berkas ini, dan tidak ada satu pun permintaan bermetode tulis — layar
 * laporan ini baca saja.
 */

/* ------------------------------------------------------------------ */
/* Bentuk respons                                                      */
/* ------------------------------------------------------------------ */

/** Satu entri pemilih Mill — ADMIN SAJA (403 untuk ketiga peran lain). */
export interface SolidWasteDisposalReportBusinessUnitOption {
  id: string
  name: string
}

export interface SolidWasteDisposalReportPeriodOption {
  id: string
  name: string
  start_date: string
  end_date: string
  /**
   * Status STASIUN LAYAR INI di dalam periode itu (baris period_stations),
   * BUKAN status periode sebagai keseluruhan: stasiun tidak ditutup serentak.
   * Status TIDAK PERNAH menyaring daftar ini — periode tertutup tercantum dan
   * tetap dapat dibaca serta diekspor sepenuhnya.
   */
  status: string
  /** Selalu 'solid-waste-disposal' — DENGAN TANDA HUBUNG, tidak pernah null. */
  station_type?: string
  station_type_label?: string
}

export interface SolidWasteDisposalReportBusinessUnitRef {
  id: string
  name: string
}

export interface SolidWasteDisposalReportProductionLineRef {
  id: string
  name: string
  code?: string | null
}

export interface SolidWasteDisposalReportPeriodHeader {
  id: string
  name: string
  start_date: string
  end_date: string
  status: string
}

/**
 * Blok angka pokok — SATU MUATAN KELUAR = SATU BARIS DETAIL, bukan satu
 * record induk.
 *
 * Tiga angka yang WAJIB dapat direkonsiliasi pembaca diterbitkan sebagai
 * kunci tersendiri, dan karena itu tidak satu pun di antaranya boleh
 * diturunkan di klien:
 *   load_count = net_weight_load_count + missing_net_weight_load_count.
 */
export interface SolidWasteDisposalReportLoads {
  load_count: number
  /** null — BUKAN 0 — ketika tak satu muatan pun selesai ditimbang. */
  net_weight_total: number | null
  /** null pada keadaan yang sama. Dibaca, TIDAK dibagi ulang di klien. */
  net_weight_avg: number | null
  /** PENYEBUT kedua angka di atas; dicetak di kartu yang sama dengan rata-ratanya. */
  net_weight_load_count: number
  /** Penimbangan BELUM SELESAI — angka tersendiri, bukan turunan. */
  missing_net_weight_load_count: number
}

/**
 * Satu baris rekap kategoris — bentuk IDENTIK untuk keempat rekap.
 *
 * `value` null adalah KELOMPOKNYA SENDIRI dan tidak pernah dibuang: itulah
 * yang membuat penjumlahan load_count tiap rekap TEPAT sama dengan
 * loads.load_count. Kuncinya null HARFIAH — bukan string kosong dan bukan
 * label karangan — supaya layar dapat menyebutnya 'tidak tercatat' tanpa
 * server mengarang nilai yang tidak pernah dicatat.
 *
 * URUTAN BARIS MILIK SERVER (netto DESC → jumlah DESC → nilai ASC). Tidak ada
 * pengurutan di berkas ini dan tidak boleh ada di view.
 */
export interface SolidWasteDisposalReportBreakdownRow {
  value: string | null
  load_count: number
  net_weight_total: number | null
  net_weight_load_count: number
  net_weight_avg: number | null
}

/**
 * JEJAK KEPATUHAN, blok tersendiri — sengaja TIDAK di dalam `loads`, karena
 * ini bukan ukuran produksi. Keduanya penghitung KEHADIRAN; nol pun tetap
 * diterbitkan, dan nol itulah yang memberi tahu pemeriksaannya berjalan.
 */
export interface SolidWasteDisposalReportCompliance {
  missing_gate_pass_count: number
  missing_seal_count: number
  /** Prosa SERVER, dirender UTUH di layar. */
  _note?: string
}

/** Satu baris rekap harian — satu entri per HARI BERMUATAN, bukan per hari kalender. */
export interface SolidWasteDisposalReportDailyRow {
  date: string
  load_count: number
  net_weight_total: number | null
  net_weight_load_count: number
  net_weight_avg: number | null
}

/**
 * Kaki rekap harian: DIJUMLAHKAN SERVER dari daily[], BUKAN disalin dari blok
 * `loads`. Keduanya memang dapat berbeda, tepatnya sebesar
 * undated_load_count — dan perbedaan itu adalah INFORMASI, jadi klien
 * membacanya apa adanya alih-alih menjumlahkan kolomnya sendiri.
 */
export interface SolidWasteDisposalReportDailyTotal {
  load_count: number
  net_weight_total: number | null
  net_weight_load_count: number
  net_weight_avg: number | null
  /** Prosa SERVER yang menerangkan selisih itu; dirender UTUH. */
  _note?: string
}

/**
 * Kelengkapan pencatatan — mengukur HARI, bukan kolom.
 *
 * `percent` NULL — bukan 0.0 — ketika days_counted 0 (periode belum mulai),
 * dan layar mencetak '—': nol persen berarti ada hari yang diharapkan dan tak
 * satu pun terisi, sedangkan '—' berarti belum ada hari yang diharapkan sama
 * sekali.
 */
export interface SolidWasteDisposalReportCompleteness {
  days_in_period: number
  days_with_load: number
  /** Berhenti di HARI INI untuk periode yang masih berjalan; 0 bila belum mulai. */
  days_counted: number
  percent: number | null
}

/**
 * Satu kejadian teks bebas. `date` boleh null (muatan tanpa tanggal kejadian)
 * dan server mengurutkannya PALING BELAKANG — ia tetap sebuah catatan yang
 * ditulis seseorang.
 */
export interface SolidWasteDisposalReportTextEntry {
  date: string | null
  value: string
}

/**
 * DUA DAFTAR TERPISAH, dan keduanya tidak pernah disatukan: satu muatan yang
 * mengisi kedua kolom muncul di KEDUA daftar, dan itu benar — menyatukannya
 * justru akan menampilkan muatan itu dua kali di dalam satu daftar.
 */
export interface SolidWasteDisposalReportTextEntries {
  findings: SolidWasteDisposalReportTextEntry[]
  remarks: SolidWasteDisposalReportTextEntry[]
}

/**
 * Seluruh angka layar untuk satu periode pada satu Production Line.
 *
 * TIDAK ADA satu pun kunci per jam, TIDAK ADA dimensi shift, TIDAK ADA
 * downtime, dan TIDAK ADA target/penandaan di luar batas — keempat ketiadaan
 * itu disengaja, masing-masing punya alasannya sendiri, dan ketiganya
 * DINYATAKAN payload lewat ketiga prosa `_note_*` supaya tidak satu pun
 * terbaca sebagai bagian yang gagal dimuat.
 */
export interface SolidWasteDisposalReportSummary {
  business_unit?: SolidWasteDisposalReportBusinessUnitRef | null
  production_line?: SolidWasteDisposalReportProductionLineRef | null
  period?: SolidWasteDisposalReportPeriodHeader | null
  loads?: SolidWasteDisposalReportLoads
  by_waste_type?: SolidWasteDisposalReportBreakdownRow[]
  by_source_station?: SolidWasteDisposalReportBreakdownRow[]
  by_disposal_site?: SolidWasteDisposalReportBreakdownRow[]
  by_purpose_end_use?: SolidWasteDisposalReportBreakdownRow[]
  compliance?: SolidWasteDisposalReportCompliance
  /** Muatan draft IKUT di seluruh angka di atas; ini pernyataannya, bukan penyaringnya. */
  draft_load_count?: number
  /**
   * Muatan tanpa tanggal kejadian: ikut di load_count dan di keempat rekap,
   * TIDAK ikut di daily[]. Kolom `event_date` NOT NULL di basis data, jadi
   * angkanya selalu 0 dalam praktik — dan layar karena itu MERENDERNYA HANYA
   * ketika > 0.
   */
  undated_load_count?: number
  daily?: SolidWasteDisposalReportDailyRow[]
  daily_total?: SolidWasteDisposalReportDailyTotal
  completeness?: SolidWasteDisposalReportCompleteness
  text_entries?: SolidWasteDisposalReportTextEntries
  /** Prosa SERVER, dirender UTUH — tidak diringkas dan tidak diparafrasekan. */
  _note_no_hourly?: string
  _note_shift_not_published?: string
  _note_absences?: string
}

/**
 * Cakupan angka yang diminta pemanggil.
 *
 * `isAdmin` sengaja WAJIB dinyatakan EKSPLISIT oleh pemanggil yang hendak
 * mengirim business_unit_id — lihat catatan "gagal tertutup" pada docblock
 * berkas. Tanpa pernyataan itu, parameter mill tidak pernah terkirim.
 */
export interface SolidWasteDisposalReportScope {
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
 * keempat belas repo saudaranya. Satu implementasi, dua cara memanggil; bukan
 * dua implementasi.
 */
export interface SolidWasteDisposalReportQuery {
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
 * LINES_PATH TIDAK DIDUPLIKASI DI BAWAH PREFIX solid-waste-disposal-reports:
 * ia endpoint screen-135 yang dipakai ulang verbatim (lihat docblock
 * SolidWasteDisposalReportController), dan permintaannya pun tetap dikerjakan
 * productionLineRepo (lihat fetchLines).
 */
export const BUSINESS_UNITS_PATH = '/api/solid-waste-disposal-reports/business-units/options'
export const PERIODS_PATH = '/api/solid-waste-disposal-reports/periods'
export const SUMMARY_PATH = '/api/solid-waste-disposal-reports/summary'
export const EXPORT_PATH = '/api/solid-waste-disposal-reports/export'
export const LINES_PATH = '/api/production-lines/options-for-report'

/* ------------------------------------------------------------------ */
/* Pembentuk parameter                                                 */
/* ------------------------------------------------------------------ */

/** Menyatukan kedua bentuk pemanggilan menjadi satu objek pertanyaan. */
function queryOf(
  period: string | SolidWasteDisposalReportQuery,
  scope?: SolidWasteDisposalReportScope,
): SolidWasteDisposalReportQuery {
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
function summaryParams(query: SolidWasteDisposalReportQuery): Record<string, string> {
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
  period: string | SolidWasteDisposalReportQuery,
  scope?: SolidWasteDisposalReportScope,
): Record<string, string> {
  return { ...summaryParams(queryOf(period, scope)), format: 'csv' }
}

/* ------------------------------------------------------------------ */
/* Pengambilan                                                         */
/* ------------------------------------------------------------------ */

/**
 * GET /api/solid-waste-disposal-reports/business-units/options — pemilih Mill,
 * ADMIN SAJA.
 *
 * Server menjawab 403 untuk ketiga peran lain, Operator TERMASUK; penolakannya
 * diangkat di dalam SolidWasteDisposalReportService::businessUnitOptions(),
 * bukan oleh middleware rute. View TIDAK BOLEH memanggilnya untuk peran yang
 * terikat mill — lihat docblock berkas.
 *
 * Master kosong adalah jawaban SAH: { data: [] } dengan HTTP 200, bukan 404.
 */
export async function fetchBusinessUnits(): Promise<SolidWasteDisposalReportBusinessUnitOption[]> {
  const response = await apiClient.get(BUSINESS_UNITS_PATH)

  return (response.data?.data ?? []) as SolidWasteDisposalReportBusinessUnitOption[]
}

/**
 * GET /api/production-lines/options-for-report — daftar Production Line untuk
 * pemilih WAJIB di layar ini.
 *
 * MENDELEGASIKAN ke productionLineRepo.fetchProductionLinesForReport(): URL-nya
 * tetap DIDEFINISIKAN SATU KALI di repo itu, endpoint screen-135 ini tidak
 * diduplikasi di bawah prefix solid-waste-disposal-reports, dan tidak ada
 * pemeta baris kedua di sini (bentuk {id, name, code} adalah keputusan sisi
 * server).
 *
 * business_unit_id diteruskan HANYA untuk Admin — untuk peran lain `null`
 * dikirim eksplisit, dan repo itu pun tidak menuliskan parameternya. Memanggil
 * /api/production-lines/current atas nama Admin akan memulangkan line milik
 * mill AKUN PEMANGGIL (Admin tidak terikat mill), yaitu bentuk kesalahan
 * paling berbahaya di layar laporan: angka yang terlihat sah untuk line yang
 * salah.
 */
export async function fetchLines(
  scope?: SolidWasteDisposalReportScope,
): Promise<ProductionLineOption[]> {
  const params = millParams(scope)

  return productionLineRepo.fetchProductionLinesForReport(params.business_unit_id ?? null)
}

/**
 * GET /api/solid-waste-disposal-reports/periods — periode yang mencakup
 * stasiun Solid Waste Disposal, yakni periode yang punya baris period_stations
 * berjenis 'solid-waste-disposal' (DENGAN TANDA HUBUNG; server membaca
 * nilainya dari App\Enums\StationType alih-alih menuliskannya, karena garis
 * bawah di situ tidak akan cocok dengan satu baris pun dan menjawab [] untuk
 * setiap mill tanpa satu galat).
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
  scope?: SolidWasteDisposalReportScope,
): Promise<SolidWasteDisposalReportPeriodOption[]> {
  const response = await apiClient.get(PERIODS_PATH, { params: millParams(scope) })

  return (response.data?.data ?? []) as SolidWasteDisposalReportPeriodOption[]
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
 * GET /api/solid-waste-disposal-reports/summary — SELURUH angka layar untuk
 * satu periode pada satu Production Line, dan satu-satunya sumbernya.
 *
 * BODY-NYA DITERUSKAN UTUH: nol kunci dibuang, nol bawaan disuntikkan, nol
 * operasi aritmetika. Tidak ada penjumlahan baris rekap, tidak ada penurunan
 * missing_net_weight_load_count dari load_count − net_weight_load_count, tidak
 * ada perata-rataan ulang netto, tidak ada penjumlahan daily[] menjadi
 * daily_total, tidak ada penurunan persen kelengkapan dari
 * days_with_load/days_counted, tidak ada pengelompokan ulang nilai kategoris
 * (termasuk tidak menyatukan dua ejaan yang hanya berbeda besar-kecil huruf),
 * dan tidak ada pengurutan ulang rekap mana pun. Inilah yang menjamin angka
 * mobile SAMA PERSIS dengan angka web: keduanya membaca satu endpoint yang
 * sama, bukan menghitung dengan dua rumus.
 *
 * Dapat dipanggil `fetchSummary('p-1', { productionLineId: 'l-1' })` maupun
 * `fetchSummary({ periodId: 'p-1', productionLineId: 'l-1' })`.
 */
export async function fetchSummary(
  period: string | SolidWasteDisposalReportQuery,
  scope?: SolidWasteDisposalReportScope,
): Promise<SolidWasteDisposalReportSummary> {
  const response = await apiClient.get(SUMMARY_PATH, {
    params: summaryParams(queryOf(period, scope)),
  })

  return (unwrap<SolidWasteDisposalReportSummary>(response.data) ??
    {}) as SolidWasteDisposalReportSummary
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
  period: string | SolidWasteDisposalReportQuery,
  scope?: SolidWasteDisposalReportScope,
): string {
  return `${EXPORT_PATH}?${new URLSearchParams(exportParams(period, scope)).toString()}`
}

/**
 * GET /api/solid-waste-disposal-reports/export?period_id=…&production_line_id=…&format=csv
 * — respons streaming text/csv, BUKAN JSON, jadi responseType-nya blob.
 *
 * Isinya dibentuk SERVER dan sama persis dengan ekspor laporan versi web,
 * karena endpoint-nya memang sama: SATU BARIS PER MUATAN, dengan lima kolom
 * konteks (Periode, Mill, Production Line, Tanggal Record, Status) diulang
 * verbatim di setiap baris, disusul KETUJUH BELAS kolom detail — dua puluh dua
 * kolom seluruhnya, lihat SolidWasteDisposalReportService::EXPORT_HEADER.
 * `Shift`, `Weighbridge Ticket No`, `Vehicle No`, `Driver Name`, `Gate Pass No`,
 * `Security Seal No` dan `Operator ID` IKUT di berkas meski tidak muncul di
 * satu angka pun di layar: ekspor adalah rincian MENTAH, jadi pengecualian
 * sebuah kolom dari layar tidak berarti kolom itu dibuang dari berkas. Muatan
 * ber-netto NULL tetap sebuah baris dengan sel KOSONG — tidak pernah dibuang
 * dan tidak pernah ditulis 0.
 *
 * 422 (parameter wajib absen, atau batas 50.000 baris muatan terlampaui)
 * diteruskan apa adanya ke view, yang menampilkan pesan server tanpa mencoba
 * memotong, memecah, atau mengulang permintaan dengan rentang lebih kecil.
 */
export async function exportCsv(
  period: string | SolidWasteDisposalReportQuery,
  scope?: SolidWasteDisposalReportScope,
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
 * POLA YANG SAMA dengan keempat belas kembaran mobile sebelumnya; tidak ada
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

export const solidWasteDisposalReportRepo = {
  fetchBusinessUnits,
  fetchLines,
  fetchPeriods,
  fetchSummary,
  buildExportUrl,
  exportParams,
  exportCsv,
  saveCsvFile,
}

export default solidWasteDisposalReportRepo
