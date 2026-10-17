import apiClient from '@/services/apiClient'
import { productionLineRepo, type ProductionLineOption } from '@/services/productionLineRepo'

/**
 * processWaterReportRepo — screen-161--laporan-process-water-mobile /
 * usecase-167--laporan-process-water-mobile "Lihat Laporan Periode Process
 * Water (Mobile)".
 *
 * Kembaran KEEMPAT BELAS dari pola yang sama: sterilizerReportRepo.ts
 * (screen-135) sampai engineRoomReportRepo.ts (screen-159). Satu berkas repo
 * tipis di atas apiClient, TANPA cache lokal. Layar laporan ini memang
 * MEMBUTUHKAN jaringan, jadi tidak ada jalur SQLite luring di sini sama
 * sekali — kegagalan jaringan adalah kondisi yang DITAMPILKAN ke pengguna,
 * bukan yang disembunyikan di balik data basi.
 *
 * ────────────────────────────────────────────────────────────────────────
 * NOL PERUBAHAN BACKEND, DAN ITU SUDAH DIBAYAR LEBIH DULU
 * ────────────────────────────────────────────────────────────────────────
 * Keempat endpoint SUDAH ADA: tiga rute data /api/process-water-reports/*
 * dibangun screen-160 dan SUDAH menerima Operator sejak commit pertamanya
 * (lihat docblock ProcessWaterReportController), dan
 * /api/production-lines/options-for-report dipakai ulang VERBATIM dari
 * screen-135. Akibatnya: NOL endpoint baru, NOL entri api-index baru, dan NOL
 * berkas di backend/ yang disentuh untuk layar ini. Bila sebuah test backend
 * memerah saat layar ini dibangun, sebabnya ada di tempat lain.
 *
 * TIDAK ADA business_unit_id DI BERKAS INI, SAMA SEKALI — sama seperti
 * engineRoomReportRepo.ts dan effluentPlantReportRepo.ts, dengan alasan yang
 * sama: kedua aktor layar ini (Operator dan Supervisor) TERIKAT SATU MILL,
 * server menyelesaikan mill dari akun, jadi parameter itu tidak punya
 * pekerjaan apa pun di sini. Cabang Admin (scopeParams + fetchBusinessUnits)
 * TIDAK ada, jadi mustahil ada jalur yang mengirimkannya — gagal-tertutup
 * secara KONSTRUKSI, bukan secara penjagaan. Konsekuensinya disengaja:
 * /api/process-water-reports/business-units/options tidak dipanggil dari mana
 * pun, dan endpoint itu memang tetap Admin saja (403 dilempar DI DALAM
 * service, bukan oleh middleware prefix-nya).
 *
 * PONSEL TIDAK MENGHITUNG APA PUN DI BERKAS INI. Tidak ada penjumlahan,
 * perata-rataan, pembulatan, pengurutan, penyaringan, pengelompokan, maupun
 * penurunan persentase — TERMASUK tidak memetakan satuan: nilai `unit`
 * diteruskan apa adanya bila server menerbitkannya. Laporan web memakai
 * sumber yang sama (ProcessWaterReportService), jadi perhitungan kedua di
 * sisi klien pasti akan menyimpang dari laporan web tanpa ketahuan.
 *
 * ────────────────────────────────────────────────────────────────────────
 * RESPONS /summary DITERUSKAN APA ADANYA — NOL KUNCI DIBUANG, NOL BAWAAN
 * DISUNTIKKAN
 * ────────────────────────────────────────────────────────────────────────
 * Berbeda dari kedua belas repo pertama yang menyalin blok-blok yang
 * dikenalnya satu per satu, fetchSummary() di sini MENERUSKAN BODY RESPONS
 * UTUH. Alasannya satu dan tegas: daftar kunci yang dipaku di repo akan
 * MEMBUANG kunci yang tidak ada di daftar itu, dan kunci yang hilang tidak
 * terlihat sampai seseorang membacanya di layar. Dengan meneruskan body utuh,
 * sebuah fixture lengkap tetap deep-equal dengan hasil parse, dan kunci baru
 * dari putaran server kelak sampai ke view tanpa menyunting berkas ini.
 *
 * Konsekuensi yang disengaja: BLOK YANG TIDAK ADA TETAP TIDAK ADA. Bagaimana
 * blok yang hilang dibaca di layar adalah keputusan VIEW (yang memang punya
 * kalimatnya), bukan keputusan repo yang akan menambal "tidak ada yang
 * dilaporkan" menjadi "nol" — dua fakta yang berbeda, dan yang satu
 * menyesatkan.
 *
 * ────────────────────────────────────────────────────────────────────────
 * DUA KOSAKATA PADA SATU PAYLOAD, DAN ITU DIKETIK — BUKAN DINORMALKAN DI SINI
 * ────────────────────────────────────────────────────────────────────────
 * ProcessWaterReportService menerbitkan `coverage.coverage_percent`,
 * `coverage.process_water_unit_count`, `metrics[k].reading_count`,
 * `inspector_recap.{entries,not_recorded,recorded_slots}`, `by_unit`, dan
 * `daily[].metrics_avg`. Tech spec layar ini menamai medan yang sama
 * `coverage.percent`, `coverage.unit_count`, `metrics[k].count`,
 * `inspector_recap` sebagai DAFTAR, `unit_recap`, dan
 * `daily[].raw_water_flow_avg`.
 *
 * KEDUANYA DITULIS PADA ANTARMUKA DI BAWAH sebagai medan opsional, dan
 * PEMILIHANNYA DIKERJAKAN VIEW, bukan di sini. Repo yang memilih salah satu
 * lalu membuang yang lain akan membuang kunci — persis yang dilarang alinea
 * di atas. Yang dijamin berkas ini: tidak satu pun kunci hilang, dan tidak
 * satu pun angka berubah di perjalanan.
 *
 * ────────────────────────────────────────────────────────────────────────
 * YANG TIDAK ADA DI PAYLOAD INI, DAN ITU DISENGAJA
 * ────────────────────────────────────────────────────────────────────────
 * TIDAK ADA satu pun kunci SEBARAN KATEGORIS. Stasiun ini tidak punya satu
 * kolom berenum pun: `action_taken_status` bertipe TEKS BEBAS meski namanya
 * menyebut 'status', jadi ia diterbitkan sebagai DAFTAR TEKS pada
 * `text_entries` — satu entri per slot — dan isi yang berulang sama persis
 * TIDAK dikelompokkan. Karena itu berkas ini TIDAK punya STATUS_VALUE_LABELS
 * maupun statusValueLabel() seperti engineRoomReportRepo.ts: menyalin
 * keduanya ke sini akan mengarang label untuk kolom teks bebas.
 *
 * TIDAK ADA blok `downtime` (process_water_details tidak punya satu pun
 * kolomnya), TIDAK ADA `target` / severity / penandaan di luar batas (stasiun
 * ini tidak punya tabel master acuan operasional — dan itu berlaku MESKI pH
 * dan TDS air umpan boiler punya batas yang dikenal sangat luas di luar
 * aplikasi ini), dan TIDAK ADA dimensi `shift` pada /summary. Kolom `shift`
 * TETAP ADA di berkas ekspor, karena ekspor adalah rincian MENTAH.
 *
 * NULL BUKAN NOL. min/avg/max tiap metrik, persen cakupan, dan seluruh nilai
 * di dalam `metrics_avg` boleh null. Mengoersinya menjadi 0 (atau '' / '-')
 * di sini akan mengubah "tidak ada yang diukur" menjadi "hasilnya nol".
 * Penerjemahan null menjadi teks terbaca adalah urusan view.
 *
 * GALAT DITERUSKAN APA ADANYA. Tidak ada try/catch dan tidak ada kelas galat
 * khusus di sini. Interceptor apiClient menolak lewat normalizeError yang
 * mengembalikan objek DATAR { message, errors?, status? } dan MEMBUANG
 * `response`. 401 diterjemahkan menjadi "arahkan ke Login TANPA pesan
 * jaringan" oleh LaporanProcessWaterView (membaca `error.status`), 403 pada
 * /summary menjadi "pilih line lagi", 404 menjadi "periode tidak ditemukan",
 * 422 akun-tanpa-mill menjadi arahan menghubungi Admin, dan kegagalan
 * transport (penolakan tanpa `status`) menjadi pesan + tombol Coba Lagi.
 *
 * LAYAR INI TIDAK MENULIS KE localDb. Tidak ada satu pun impor localDb di
 * berkas ini — ketiadaan mode luring disengaja dan dinyatakan di layar.
 */

/* ------------------------------------------------------------------ */
/* Bentuk respons                                                      */
/* ------------------------------------------------------------------ */

export interface ProcessWaterReportPeriodOption {
  id: string
  name: string
  start_date: string
  end_date: string
  /**
   * Status STASIUN LAYAR INI di dalam periode itu (baris period_stations),
   * BUKAN status periode sebagai keseluruhan: stasiun tidak ditutup serentak.
   */
  status: string
  /** Selalu 'process-water' — DENGAN TANDA HUBUNG, dan tidak pernah null. */
  station_type?: string
  station_type_label?: string
}

export interface ProcessWaterReportBusinessUnitRef {
  id: string
  name: string
}

export interface ProcessWaterReportProductionLineRef {
  id: string
  name: string
  code?: string | null
}

export interface ProcessWaterReportPeriodHeader {
  id: string
  name: string
  start_date: string
  end_date: string
  status: string
  /**
   * Ikut diterbitkan server, tetapi nama mill DIBACA VIEW dari blok
   * `business_unit` — satu sumber saja, supaya tidak ada dua tempat yang
   * dapat menyimpang. Diketik di sini hanya supaya ia tidak terlihat sebagai
   * kunci yang hilang.
   */
  business_unit_name?: string
}

/**
 * Kelengkapan pencatatan — dirender PALING ATAS di layar, sebelum satu kartu
 * pun. Di layar sempit ia satu-satunya bagian yang pasti terlihat tanpa
 * menggulir, dan ia menentukan apakah sembilan angka di bawahnya layak
 * dipercaya: kesembilan kolom ukur nullable, jadi periode yang terisi kurang
 * dari separuh pun tetap menghasilkan rata-rata yang terlihat rapi.
 */
export interface ProcessWaterReportCoverage {
  /**
   * Slot terisi atas SEBELAS kolom pembacaan — BUKAN tiga belas: `shift` dan
   * `inspector_id` adalah kolom IDENTITAS dan DIKECUALIKAN dari
   * ProcessWaterRecordService::isRowFilled(), yang laporan ini PINJAM alih-alih
   * turunkan ulang. Karena kesebelas kolom itu mencakup kedua kolom TEKS,
   * angka ini dapat MELEBIHI `reading_count` setiap kartu metrik — dan itu
   * benar, bukan selisih yang perlu dikoreksi.
   */
  filled_slots: number
  expected_slots: number
  /** Nama server. 0.0 — bukan null — ketika expected_slots nol. */
  coverage_percent?: number | null
  /** Nama tech spec untuk medan yang sama; null ketika expected_slots nol. */
  percent?: number | null
  /** Nama server: unit yang BENAR-BENAR punya record pada periode+line ini. */
  process_water_unit_count?: number
  /** Nama tech spec untuk medan yang sama. */
  unit_count?: number
  /** 24, dari kisi slot kanonis layar input itu sendiri (07:00–06:00). */
  slots_per_unit_per_day: number
  days_in_period?: number
  /** Berhenti di HARI INI untuk periode yang masih berjalan. */
  days_counted: number
  /**
   * BUKAN pengganti days_counted, melainkan pendampingnya: server menandai
   * periode yang BELUM MULAI sebagai masih berjalan juga, jadi layar wajib
   * memeriksa days_counted === 0 LEBIH DULU.
   */
  period_running?: boolean
  /**
   * Prosa ATURAN SEBELAS KOLOM, disusun SERVER dan dirender VERBATIM. Ponsel
   * tidak menyusun kalimatnya sendiri: kalimat yang disusun dua kali di dua
   * platform adalah kalimat yang akan berselisih.
   */
  _note_filled_rule?: string
}

/**
 * Satu kolom ukur, dengan PENYEBUTNYA SENDIRI dan BLOK TATA LETAKNYA SENDIRI.
 *
 * `group` ADALAH YANG MEMBUAT KESEMBILAN KARTU SATU MARKUP. View
 * mengelompokkan `metrics` menurut medan ini — bukan menurut daftar nama
 * kolom yang dipaku di template — sehingga kolom baru dari migrasi kelak
 * masuk ke blok yang benar tanpa menyunting satu berkas klien pun.
 *
 * Perhatikan apa yang TIDAK ada di sini: tidak ada `target`, tidak ada
 * severity, tidak ada is_out_of_range, dan tidak ada flag apa pun — payload
 * Process Water tidak menerbitkan satu pun di antaranya, dan stasiun ini
 * tidak punya tabel master acuan sama sekali.
 *
 * `label` dan `unit` ditulis OPSIONAL supaya nilai server, bila kelak
 * diterbitkan, MENANG atas tabel label view — dan supaya repo ini tidak
 * pernah punya peta satuan yang mengubah 'C' menjadi '°C'. `unit` BOLEH null,
 * dan null berarti kartunya TIDAK MENCETAK SATUAN APA PUN: kedua kartu pH
 * justru yang null, dan itu benar — pH besaran tanpa satuan.
 */
export interface ProcessWaterReportMetric {
  min: number | null
  avg: number | null
  max: number | null
  /** Nama server — PENYEBUT min/avg/max kolom INI, bukan coverage.filled_slots. */
  reading_count?: number
  /** Nama tech spec untuk penyebut yang sama. */
  count?: number
  /** Blok tata letak kolom ini, dari RESPONS. View membangun bloknya dari sini. */
  group: string
  label?: string
  /** Satuan APA ADANYA; null berarti kolom ini memang tanpa satuan. */
  unit?: string | null
}

/**
 * Satu KEJADIAN teks bebas: satu entri = satu SLOT yang mengisinya, membawa
 * tanggal dan slot waktunya.
 *
 * `text` adalah nama server; `value` nama tech spec. Keduanya diketik dan
 * VIEW yang memilih — lihat docblock berkas.
 */
export interface ProcessWaterReportTextEntry {
  date: string
  time_slot: string
  text?: string
  value?: string
}

/**
 * TEPAT DUA kunci, dan keduanya DAFTAR TEKS — BUKAN sebaran.
 *
 * `action_taken_status` ada di sini MESKI namanya menyebut 'status': kolomnya
 * string polos tanpa himpunan nilai tertutup (tiga verifikasi terpisah di
 * docblock ProcessWaterReportService), jadi isi yang berulang sama persis
 * tetap menjadi entri yang berulang. Dua puluh slot berbunyi 'Sudah
 * ditangani' dirender DUA PULUH baris, bukan satu baris berjumlah dua puluh.
 */
export interface ProcessWaterReportTextEntries {
  action_taken_status: ProcessWaterReportTextEntry[]
  findings: ProcessWaterReportTextEntry[]
}

/**
 * Satu baris rekap per inspector. `inspector_id` dipulangkan MENTAH apa
 * adanya — kolomnya tidak punya relasi ke `users`, jadi tidak ada nama orang
 * untuk dipulangkan dan tidak ada pemetaan untuk ditebak.
 *
 * `filled_slots` nama server, `slots` nama tech spec. `share` hanya ada pada
 * kosakata tech spec; view TIDAK membacanya sebagai persen siap cetak.
 */
export interface ProcessWaterReportInspectorEntry {
  inspector_id: string | null
  filled_slots?: number
  slots?: number
  share?: number
}

/**
 * Bentuk server rekap per inspector: `not_recorded` adalah KUNCI TERPISAH dan
 * tidak pernah dilipat ke salah satu entri — slot terisi yang kolom
 * inspector-nya kosong adalah fakta yang berbeda dari slot yang diatribusikan
 * kepada seseorang. Slot itu TETAP terhitung pada cakupan, karena
 * `inspector_id` kolom identitas dan tidak ikut menentukan keterisian slot.
 *
 * `recorded_slots` adalah PENYEBUT porsi tiap baris — bukan expected_slots,
 * yang akan mencampur slot tak tersentuh ke dalam porsi baris yang justru
 * tercatat. Invariannya: sum(entries) + not_recorded === recorded_slots.
 */
export interface ProcessWaterReportInspectorRecapObject {
  entries: ProcessWaterReportInspectorEntry[]
  not_recorded: number
  recorded_slots: number
}

/**
 * Bentuk server (objek) ATAU bentuk tech spec (daftar, dengan baris
 * ber-inspector_id null sebagai baris 'tidak tercatat'-nya sendiri). View
 * menormalkan keduanya menjadi daftar baris dan TIDAK PERNAH melipat baris
 * null ke baris berkode mana pun.
 */
export type ProcessWaterReportInspectorRecap =
  | ProcessWaterReportInspectorRecapObject
  | ProcessWaterReportInspectorEntry[]

/** Satu nilai rata-rata di dalam rekap, dengan penyebutnya sendiri. */
export interface ProcessWaterReportAverage {
  avg: number | null
  reading_count?: number
  count?: number
}

/** Satu titik tren harian: RATA-RATA tanggal itu, dengan penyebutnya sendiri. */
export interface ProcessWaterReportTrendPoint {
  date: string
  /** null — BUKAN 0 — bila tanggal itu tidak sekali pun mencatat kolomnya. */
  avg: number | null
  reading_count?: number
}

/**
 * SATU-SATUNYA tren harian: rata-rata aliran air baku per tanggal.
 *
 * Sah dirata-ratakan karena aliran adalah besaran SESAAT per slot, bukan
 * angka meter kumulatif. Aliran air baku yang digrafikkan karena ia HULU
 * seluruh rantai dan punya penyebut terbesar, jadi bentuk grafiknya paling
 * sedikit terdistorsi slot yang tidak terisi. Diketik sebagai peta supaya
 * nama kolomnya datang dari RESPONS, bukan dipaku di klien.
 */
export type ProcessWaterReportDailyTrend = Record<string, ProcessWaterReportTrendPoint[]>

/**
 * Satu baris rekap per UNIT Process Water.
 *
 * `process_water_id` bertipe `string` biasa — bukan uuid dan bukan kunci
 * asing: tidak ada tabel master process_waters, nilainya kolom teks yang
 * DIKETIK di layar input. Satu nama yang sama pada dua tanggal adalah SATU
 * unit dengan dua hari pencatatan.
 *
 * Unit yang punya record tetapi tidak satu pun pembacaan TETAP muncul dengan
 * nol — membuangnya justru akan menyembunyikan unit yang tidak pernah
 * dituliskan.
 */
export interface ProcessWaterReportUnitRow {
  process_water_id: string
  /** Nama server (diterbitkan berdampingan bernilai sama). */
  reading_rows?: number
  reading_count?: number
  /** Nama tech spec untuk angka yang sama. */
  filled_slots?: number
  days_with_rows?: number
  /** Bentuk yang SAMA dengan `metrics` tingkat periode, dilingkupi unit ini. */
  metrics?: Record<string, ProcessWaterReportMetric>
}

/**
 * Satu baris rekap per HARI yang punya minimal satu slot terisi. Tanggal
 * tanpa satu pun slot terisi TIDAK mendapat baris: baris nol akan terbaca
 * sebagai "terukur nol", padahal tidak ada yang diukur.
 */
export interface ProcessWaterReportDailyRow {
  date: string
  /** Nama server. */
  reading_rows?: number
  /** Nama tech spec untuk angka yang sama. */
  filled_slots?: number
  metrics_avg?: Record<string, ProcessWaterReportAverage>
  /** Jalur ringkas kosakata tech spec untuk tren aliran air baku. */
  raw_water_flow_avg?: number | null
}

export interface ProcessWaterReportDailyTotal {
  reading_rows: number
}

export interface ProcessWaterReportTotals {
  days_with_records: number
  reading_rows: number
}

/**
 * Seluruh angka layar untuk satu periode pada satu Production Line.
 *
 * TIDAK ADA `target`, TIDAK ADA `downtime`, TIDAK ADA satu pun kunci sebaran
 * kategoris, dan TIDAK ADA dimensi `shift` — keempat ketiadaan itu disengaja,
 * masing-masing punya alasannya sendiri, dan keempatnya DINYATAKAN di layar
 * supaya tidak satu pun terbaca sebagai bagian yang gagal dimuat.
 */
export interface ProcessWaterReportSummary {
  business_unit?: ProcessWaterReportBusinessUnitRef | null
  production_line?: ProcessWaterReportProductionLineRef | null
  period?: ProcessWaterReportPeriodHeader | null
  /** Membedakan "tidak ada yang dilaporkan" dari "angkanya nol". */
  has_data?: boolean
  coverage?: ProcessWaterReportCoverage
  /** TEPAT sembilan entri, juga ketika sebuah kolom tidak pernah terisi. */
  metrics?: Record<string, ProcessWaterReportMetric>
  /** TEPAT dua kunci, keduanya daftar teks. BUKAN sebaran. */
  text_entries?: ProcessWaterReportTextEntries
  inspector_recap?: ProcessWaterReportInspectorRecap
  daily_trend?: ProcessWaterReportDailyTrend
  /** Nama server untuk rekap per unit. */
  by_unit?: ProcessWaterReportUnitRow[]
  /** Nama tech spec untuk rekap yang sama. */
  unit_recap?: ProcessWaterReportUnitRow[]
  daily?: ProcessWaterReportDailyRow[]
  daily_total?: ProcessWaterReportDailyTotal
  total?: ProcessWaterReportTotals
  /**
   * Prosa KETIADAAN — tanpa downtime, tanpa penandaan di luar batas, tanpa
   * kolom shift di layar. Disusun SERVER dan dirender UTUH.
   */
  _note_absences?: string
}

/**
 * Cakupan angka yang diminta pemanggil — SATU medan saja, dan itu disengaja.
 *
 * Tidak ada `isAdmin` dan tidak ada `businessUnitId` di sini: mill
 * diselesaikan server dari akun bagi kedua aktor layar ini, jadi medan itu
 * tidak punya pekerjaan — dan medan yang tidak ada tidak dapat salah
 * terkirim.
 */
export interface ProcessWaterReportScope {
  /**
   * Production Line yang angkanya diminta. Dikirim ke /summary dan /export
   * saja. WAJIB di layar ini: tanpa nilai ini server menjawab 422 (dan
   * SENGAJA tidak memulangkan angka seluruh mill), dan view memang tidak
   * memanggil /summary sebelum sebuah line berlaku — penjagaannya ada di
   * FUNGSI pemuatan, bukan hanya di template.
   */
  productionLineId?: string | null
}

/**
 * Bentuk pemanggilan OBJEK TUNGGAL, yang dipakai test spec layar ini
 * (`fetchSummary({ periodId, productionLineId })`).
 *
 * Kedua bentuk diterima oleh ketiga fungsi di bawah — bentuk ini dan bentuk
 * `(periodId, scope)` yang dipakai ketiga belas repo saudaranya. Satu
 * implementasi, dua cara memanggil; bukan dua implementasi.
 */
export interface ProcessWaterReportQuery {
  periodId: string
  productionLineId?: string | null
}

/* ------------------------------------------------------------------ */
/* Jalur URL — SATU definisi masing-masing                             */
/* ------------------------------------------------------------------ */

/**
 * KEEMPAT jalur yang layar ini sentuh, dan tidak ada yang kelima.
 *
 * LINES_PATH TIDAK DIDUPLIKASI DI BAWAH PREFIX process-water-reports: ia
 * endpoint screen-135 yang dipakai ulang verbatim, dan permintaannya pun
 * tetap dikerjakan productionLineRepo (lihat fetchLines).
 */
export const PERIODS_PATH = '/api/process-water-reports/periods'
export const SUMMARY_PATH = '/api/process-water-reports/summary'
export const EXPORT_PATH = '/api/process-water-reports/export'
export const LINES_PATH = '/api/production-lines/options-for-report'

/* ------------------------------------------------------------------ */
/* Pembentuk parameter                                                 */
/* ------------------------------------------------------------------ */

/**
 * Menyatukan kedua bentuk pemanggilan menjadi satu objek pertanyaan.
 *
 * Tidak ada medan mill di sini untuk dapat salah terkirim, dan nilai line
 * kosong TIDAK pernah menjadi parameter kosong: server akan menjawab 422
 * tentang parameter yang HILANG, dan itu pesan yang benar.
 */
function queryOf(
  period: string | ProcessWaterReportQuery,
  scope?: ProcessWaterReportScope,
): ProcessWaterReportQuery {
  if (typeof period === 'string') {
    return { periodId: period, productionLineId: scope?.productionLineId ?? null }
  }

  return {
    periodId: period.periodId,
    productionLineId: period.productionLineId ?? scope?.productionLineId ?? null,
  }
}

/**
 * Parameter /summary — TEPAT dua kunci, dan tidak ada yang ketiga.
 *
 * `business_unit_id` tidak muncul di sini dan tidak ada jalur kode yang dapat
 * menambahkannya: peran layar ini terikat mill, dan server membuang nilai
 * kiriman klien tanpa memvalidasinya — jadi mengirimnya hanya menambah
 * permukaan salah tanpa menambah kemampuan.
 */
function summaryParams(query: ProcessWaterReportQuery): Record<string, string> {
  const params: Record<string, string> = { period_id: query.periodId }

  if (query.productionLineId) {
    params.production_line_id = query.productionLineId
  }

  return params
}

/**
 * Parameter /export — parameter /summary ditambah `format` yang SELALU 'csv',
 * dikirim EKSPLISIT alih-alih bersandar pada bawaan server.
 *
 * TIDAK ADA JALUR KODE YANG DAPAT MENYUSUN FORMAT LAIN. Endpoint-nya memang
 * mendukung 'excel', tetapi WebView ponsel tidak membuka .xlsx —
 * menyediakan tombol yang menghasilkan berkas yang tidak dapat dibuka lebih
 * buruk daripada tidak menyediakannya, jadi tombolnya pun tidak dirender dan
 * nilai 'excel' tidak muncul di berkas ini maupun di view.
 */
export function exportParams(
  period: string | ProcessWaterReportQuery,
  scope?: ProcessWaterReportScope,
): Record<string, string> {
  return { ...summaryParams(queryOf(period, scope)), format: 'csv' }
}

/* ------------------------------------------------------------------ */
/* Pengambilan                                                         */
/* ------------------------------------------------------------------ */

/**
 * GET /api/production-lines/options-for-report — daftar Production Line untuk
 * pemilih WAJIB di layar ini.
 *
 * MENDELEGASIKAN ke productionLineRepo.fetchProductionLinesForReport(null):
 * URL-nya tetap DIDEFINISIKAN SATU KALI di repo itu, endpoint screen-135 ini
 * tidak diduplikasi di bawah prefix process-water-reports, dan tidak ada
 * pemeta baris kedua di sini (bentuk {id, name, code} adalah keputusan sisi
 * server).
 *
 * Yang ditambahkan pembungkus ini hanyalah PERMUKAAN: view layar ini memanggil
 * satu repo untuk keempat permintaannya, sehingga penjagaan "nol permintaan
 * sebelum line berlaku" dapat diamati pada satu tempat.
 *
 * TANPA business_unit_id — `null` dikirim eksplisit supaya tidak ada jalur
 * yang menuliskannya.
 */
export async function fetchLines(): Promise<ProductionLineOption[]> {
  return productionLineRepo.fetchProductionLinesForReport(null)
}

/**
 * GET /api/process-water-reports/periods — periode yang mencakup stasiun
 * Process Water, yakni periode yang punya baris period_stations berjenis
 * 'process-water' (DENGAN TANDA HUBUNG; server membaca nilainya dari
 * App\Enums\StationType alih-alih menuliskannya, karena garis bawah di situ
 * tidak akan cocok dengan satu baris pun dan menjawab [] untuk setiap mill
 * tanpa satu galat).
 *
 * TANPA business_unit_id dan TANPA production_line_id — tidak ada jalur kode
 * untuk mengirimkan keduanya. Periode milik MILL, bukan milik line; yang
 * disaring sebuah line adalah DATA-nya.
 *
 * Penyaringannya dikerjakan SERVER; repo meneruskan daftar apa adanya, dalam
 * urutan yang sama. Daftar kosong adalah jawaban yang sah (HTTP 200 + []),
 * bukan galat. Periode TERTUTUP tetap terdaftar — status mengatur penulisan
 * data, bukan pembacaan laporan.
 */
export async function fetchPeriods(): Promise<ProcessWaterReportPeriodOption[]> {
  const response = await apiClient.get(PERIODS_PATH)

  return (response.data?.data ?? []) as ProcessWaterReportPeriodOption[]
}

/**
 * Respons /summary dikirim controller TANPA pembungkus `data`, sementara
 * /periods MEMAKAI pembungkus itu. Kedua bentuk diterima supaya repo ini
 * tidak pecah bila pembungkusnya kelak diseragamkan di sisi server.
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
 * GET /api/process-water-reports/summary — seluruh angka layar untuk satu
 * periode pada satu Production Line.
 *
 * BODY-NYA DITERUSKAN UTUH: nol kunci dibuang, nol bawaan disuntikkan, nol
 * operasi aritmetika. Tidak ada penurunan persen cakupan dari
 * filled_slots/expected_slots, tidak ada perata-rataan ulang min/avg/max,
 * tidak ada penurunan expected_slots dari unit × hari × 24 meski ketiga
 * angkanya tersedia, tidak ada pengelompokan kejadian teks bebas menurut
 * teksnya, tidak ada pengurutan ulang metrics / by_unit / daily / kedua
 * daftar teks, dan TIDAK ADA peta satuan apa pun. Lihat docblock berkas untuk
 * alasan lengkap bentuk pass-through ini.
 *
 * Dapat dipanggil `fetchSummary('p-1', { productionLineId: 'l-1' })` maupun
 * `fetchSummary({ periodId: 'p-1', productionLineId: 'l-1' })`.
 */
export async function fetchSummary(
  period: string | ProcessWaterReportQuery,
  scope?: ProcessWaterReportScope,
): Promise<ProcessWaterReportSummary> {
  const response = await apiClient.get(SUMMARY_PATH, {
    params: summaryParams(queryOf(period, scope)),
  })

  return (unwrap<ProcessWaterReportSummary>(response.data) ?? {}) as ProcessWaterReportSummary
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
  period: string | ProcessWaterReportQuery,
  scope?: ProcessWaterReportScope,
): string {
  return `${EXPORT_PATH}?${new URLSearchParams(exportParams(period, scope)).toString()}`
}

/**
 * GET /api/process-water-reports/export?period_id=…&production_line_id=…&format=csv
 * — respons streaming text/csv, BUKAN JSON, jadi responseType-nya blob.
 *
 * Isinya (SATU BARIS PER SLOT WAKTU, dengan konteks Periode/Mill/Production
 * Line, tanggal, unit Process Water, status, dan catatan diulang verbatim di
 * setiap baris, lalu KE-13 kolom detail) dibentuk SERVER dan sama persis
 * dengan ekspor laporan versi web, karena endpoint-nya memang sama. Kolom
 * `shift` dan `inspector_id` IKUT di berkas: pengecualian keduanya dari
 * pemeriksaan slot terisi bukan alasan membuangnya dari ekspor, karena ekspor
 * adalah rincian MENTAH. Slot yang seluruh kolom ukurnya kosong TETAP satu
 * baris, dengan sel kosong — bukan dibuang dan bukan ditulis 0.
 *
 * 422 (parameter wajib absen, atau batas 50.000 baris slot terlampaui)
 * diteruskan apa adanya ke view, yang menampilkan pesan server tanpa mencoba
 * memotong, memecah, atau mengulang permintaan dengan rentang lebih kecil.
 */
export async function exportCsv(
  period: string | ProcessWaterReportQuery,
  scope?: ProcessWaterReportScope,
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
 * POLA YANG SAMA dengan ketiga belas kembaran mobile sebelumnya; tidak ada
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

export const processWaterReportRepo = {
  fetchLines,
  fetchPeriods,
  fetchSummary,
  buildExportUrl,
  exportParams,
  exportCsv,
  saveCsvFile,
}

export default processWaterReportRepo
