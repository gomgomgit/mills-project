import apiClient from '@/services/apiClient'
import { productionLineRepo, type ProductionLineOption } from '@/services/productionLineRepo'

/**
 * kernelDispatchReportRepo — screen-165--laporan-kernel-dispatch-mobile /
 * usecase-171--laporan-kernel-dispatch-mobile "Lihat Laporan Periode Kernel
 * Dispatch (Mobile)".
 *
 * Kembaran mobile KEENAM BELAS dari pola yang sama: sterilizerReportRepo.ts
 * (screen-135) sampai solidWasteDisposalReportRepo.ts (screen-163). Satu
 * berkas repo tipis di atas apiClient, TANPA cache lokal. Layar laporan ini
 * memang MEMBUTUHKAN jaringan, jadi tidak ada jalur SQLite luring di sini sama
 * sekali — kegagalan jaringan adalah kondisi yang DITAMPILKAN ke pengguna,
 * bukan yang disembunyikan di balik data basi.
 *
 * ────────────────────────────────────────────────────────────────────────
 * NOL PERUBAHAN BACKEND, DAN ITU SUDAH DIBAYAR LEBIH DULU
 * ────────────────────────────────────────────────────────────────────────
 * Keempat endpoint SUDAH ADA: ketiga rute data /api/kernel-dispatch-reports/*
 * dibangun screen-164 dan SUDAH menerima Operator sejak commit pertamanya
 * (lihat KernelDispatchReportService::resolveBusinessUnit(), yang menempatkan
 * Operator di cabang TERIKAT-MILL justru demi layar ini), dan
 * /api/production-lines/options-for-report dipakai ulang VERBATIM dari
 * screen-135. Akibatnya: NOL endpoint baru, NOL entri api-index baru, dan NOL
 * berkas di backend/ yang disentuh untuk layar ini. Bila sebuah test backend
 * memerah saat layar ini dibangun, sebabnya ada di tempat lain.
 *
 * ────────────────────────────────────────────────────────────────────────
 * ADA CABANG ADMIN DI SINI — SAMA DENGAN screen-163
 * ────────────────────────────────────────────────────────────────────────
 * Layar ini punya EMPAT aktor, dan Admin salah satunya: ia satu-satunya peran
 * yang TIDAK terikat mill, jadi `business_unit_id` memang punya pekerjaan di
 * berkas ini. Pola `isAdmin` wajib-eksplisit disalin dari
 * solidWasteDisposalReportRepo.ts: parameter itu hanya terkirim bila PEMANGGIL
 * menyatakan dirinya Admin, sehingga business_unit_id yang dipaksakan
 * pemanggil untuk Operator / Supervisor / Mill Management TIDAK PERNAH ikut
 * terkirim — gagal tertutup di klien SEKALIGUS di server
 * (resolveBusinessUnit() membuang nilai kiriman klien untuk ketiga peran
 * terikat mill, Operator termasuk).
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
 * PAYLOAD HIBRIDA — DUA PRESEDEN DALAM SATU BENTUK, DAN ITU PEMBEDANYA
 * ────────────────────────────────────────────────────────────────────────
 * Berbeda dari solidWasteDisposalReportRepo.ts yang hanya per-transaksi,
 * payload layar ini membawa DUA sifat sekaligus:
 *
 *   - SEPARUH TRANSAKSI (preseden screen-162/163): blok `dispatches`, empat
 *     rekap kategoris berbaris 'tidak tercatat', penghitung kepatuhan sebagai
 *     blok tersendiri, `daily[]` satu entri per HARI BERPENGIRIMAN.
 *   - SEPARUH MUTU (preseden screen-160/161): blok `quality` berisi EMPAT
 *     ukuran min/avg/max, masing-masing dengan `measured_count`-NYA SENDIRI.
 *
 * Blok `quality` itulah yang TIDAK ADA di screen-163, dan ia bukan tambahan
 * kosmetik: keempat kolomnya nullable dan INDEPENDEN, sehingga keempat
 * penyebutnya boleh berbeda satu dengan lainnya DAN berbeda dari
 * `dispatch_count`. Antarmuka di bawah karena itu mengetik `measured_count`
 * pada SETIAP ukuran dan tidak menyediakan satu pun penyebut bersama — yang
 * tidak dapat ditulis tidak dapat salah dibaca.
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
 * SATU KOSAKATA, BUKAN DUA. Tech spec screen-165 menyebut medan dengan nama
 * yang sama persis seperti KernelDispatchReportService::buildSummary()
 * menerbitkannya (`dispatches.*`, `quality.*`, `by_transporter`,
 * `by_silo_source`, `by_destination_buyer`, `by_gate_status`,
 * `not_released_count`, `compliance.*`, `draft_dispatch_count`,
 * `undated_dispatch_count`, `daily`, `daily_total`, `completeness`,
 * `text_entries`, `_note_no_hourly`, `_note_no_quality_thresholds`,
 * `_note_shift_not_published`). Tidak ada normalisasi yang perlu dikerjakan,
 * jadi tidak ada normalisasi yang dikerjakan.
 *
 * NULL BUKAN NOL. `net_weight_total`, `net_weight_avg` pada blok `dispatches`,
 * min/avg/max pada KEEMPAT ukuran mutu, setiap baris keempat rekap, setiap
 * baris `daily`, `daily_total`, dan `completeness.percent` SELURUHNYA boleh
 * null. Mengoersinya menjadi 0 (atau '' / '-') di sini akan mengubah "tidak
 * pernah diuji" menjadi "hasilnya nol" — dan itu diputuskan PER MEDAN oleh
 * view, bukan oleh sebuah bawaan tunggal di berkas ini.
 *
 * PEMBULATAN SUDAH DIKERJAKAN SERVER dan TIDAK diulang di sini: berat dua
 * desimal, keempat ukuran mutu TIGA desimal
 * (KernelDispatchReportService::QUALITY_DECIMALS = 3, karena kadar kotoran
 * bergerak di orde perseratus persen sehingga dua desimal melipat dua nilai
 * yang berbeda menjadi satu angka yang sama).
 *
 * ────────────────────────────────────────────────────────────────────────
 * YANG TIDAK ADA DI PAYLOAD INI, DAN ITU DISENGAJA
 * ────────────────────────────────────────────────────────────────────────
 * TIDAK ADA `hourly`, `busiest_hour`, `empty_hour_count`, maupun waktu putar
 * kendaraan: baris detail stasiun ini hanya membawa `event_date` — sebuah
 * tanggal — tanpa satu pun kolom waktu (tidak ada jam masuk, tidak ada jam
 * keluar). Sebaran per jam karena itu tidak dapat dihitung dalam bentuk apa
 * pun, dan ketiadaannya dinyatakan payload lewat `_note_no_hourly`, yang
 * secara eksplisit menyebut bahwa di CPO Dispatch keduanya JUSTRU ADA karena
 * tabel itu punya time_in/time_out. Repo ini karena itu TIDAK mengetik satu
 * pun kunci jam.
 *
 * TIDAK ADA ambang mutu dan TIDAK ADA penandaan di luar batas
 * (`_note_no_quality_thresholds`), dan TIDAK ADA dimensi `shift` — kolom itu
 * teks bebas pada tabel DETAIL dan TETAP ikut di berkas ekspor
 * (`_note_shift_not_published`).
 *
 * SATU DAFTAR TEKS, BUKAN DUA. `text_entries` di sini hanya berisi `findings`:
 * stasiun ini TIDAK punya kolom `remarks`, berbeda dari Solid Waste Disposal.
 * Struktur dua-daftar dari sana SENGAJA tidak disalin — mengetik `remarks`
 * yang tidak pernah terbit akan mengundang bagian layar yang selamanya kosong.
 *
 * GALAT DITERUSKAN APA ADANYA. Tidak ada try/catch dan tidak ada kelas galat
 * khusus di sini. Interceptor apiClient menolak lewat normalizeError yang
 * mengembalikan objek DATAR { message, errors?, status? } dan MEMBUANG
 * `response`. 401 diterjemahkan menjadi "arahkan ke Login TANPA pesan
 * jaringan" oleh LaporanKernelDispatchView (membaca `error.status`), 403 pada
 * /summary menjadi "pilih line lagi", 404 menjadi "periode tidak ditemukan",
 * 422 akun-tanpa-mill menjadi arahan menghubungi Admin, dan kegagalan
 * transport (penolakan tanpa `status`) menjadi pesan + tombol Coba Lagi.
 *
 * LAYAR INI TIDAK MENULIS KE localDb. Tidak ada satu pun impor localDb di
 * berkas ini, dan tidak ada satu pun permintaan bermetode tulis — layar
 * laporan ini baca saja.
 */

/* ------------------------------------------------------------------ */
/* Bentuk respons                                                      */
/* ------------------------------------------------------------------ */

/** Satu entri pemilih Mill — ADMIN SAJA (403 untuk ketiga peran lain). */
export interface KernelDispatchReportBusinessUnitOption {
  id: string
  name: string
}

export interface KernelDispatchReportPeriodOption {
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
  /** Selalu 'kernel-dispatch' — DENGAN TANDA HUBUNG, tidak pernah null. */
  station_type?: string
  station_type_label?: string
}

export interface KernelDispatchReportBusinessUnitRef {
  id: string
  name: string
}

export interface KernelDispatchReportProductionLineRef {
  id: string
  name: string
  code?: string | null
}

export interface KernelDispatchReportPeriodHeader {
  id: string
  name: string
  start_date: string
  end_date: string
  status: string
}

/**
 * Blok angka pokok — SATU PENGIRIMAN = SATU BARIS DETAIL, bukan satu record
 * induk: satu record induk membawa banyak pengiriman.
 *
 * Tiga angka yang WAJIB dapat direkonsiliasi pembaca diterbitkan sebagai
 * kunci tersendiri, dan karena itu tidak satu pun di antaranya boleh
 * diturunkan di klien:
 *   dispatch_count = net_weight_dispatch_count + missing_net_weight_dispatch_count.
 */
export interface KernelDispatchReportDispatches {
  dispatch_count: number
  /** null — BUKAN 0 — ketika tak satu pengiriman pun selesai ditimbang. */
  net_weight_total: number | null
  /** null pada keadaan yang sama. Dibaca, TIDAK dibagi ulang di klien. */
  net_weight_avg: number | null
  /** PENYEBUT kedua angka di atas; dicetak di kartu yang sama dengan rata-ratanya. */
  net_weight_dispatch_count: number
  /**
   * PENIMBANGAN BELUM SELESAI — angka tersendiri, bukan turunan. `net_weight_mt`
   * adalah kolom TURUNAN service (bruto − tara), jadi kosongnya berarti
   * kendaraan sudah ditimbang kotor tetapi belum kembali ditimbang kosong,
   * bukan 'lupa diisi'.
   */
  missing_net_weight_dispatch_count: number
}

/**
 * SATU ukuran mutu, DENGAN PENYEBUTNYA SENDIRI — dan inilah bentuk yang
 * menentukan benar-salahnya layar ini.
 *
 * `measured_count` ADA PADA SETIAP UKURAN dan tidak pernah dipinjam dari
 * ukuran lain: keempat kolom mutu nullable dan INDEPENDEN, sehingga satu
 * pengiriman boleh punya hasil uji kadar air tanpa ALB. Satu penyebut tunggal
 * untuk keempatnya akan tampak rapi, lolos review, dan SALAH SECARA SISTEMATIS
 * — ia mengempiskan ukuran yang jarang diuji sambil tetap menghasilkan angka
 * yang terlihat sah.
 *
 * min/avg/max NULL — bukan 0 — ketika measured_count 0. Nol berarti 'diuji dan
 * hasilnya nol', null berarti 'tidak pernah diuji', dan view mencetak '—'
 * untuk yang kedua. PENJAGANYA PER UKURAN: ukuran yang kosong TIDAK
 * mengosongkan ketiga ukuran lain.
 */
export interface KernelDispatchReportQualityMeasure {
  min: number | null
  avg: number | null
  max: number | null
  /** PENYEBUT UKURAN INI SENDIRI — tidak pernah dipinjamkan, tidak pernah dipinjam. */
  measured_count: number
}

/**
 * KEEMPAT UKURAN MUTU, dinamai PERSIS seperti
 * KernelDispatchReportService::QUALITY_MEASURES menerbitkannya.
 *
 * `dirt_impurities_percent` — BUKAN `dirt_percent`. Nama yang dipendekkan akan
 * terkompilasi, terlihat benar, dan memulangkan undefined untuk setiap
 * pengiriman di setiap periode tanpa satu galat pun.
 */
export interface KernelDispatchReportQuality {
  kernel_moisture_percent: KernelDispatchReportQualityMeasure
  dirt_impurities_percent: KernelDispatchReportQualityMeasure
  ffa_percent: KernelDispatchReportQualityMeasure
  broken_kernel_percent: KernelDispatchReportQualityMeasure
  /** Prosa SERVER yang menyatakan keempat penyebut boleh berbeda; dirender UTUH. */
  _note?: string
}

/** Kunci keempat ukuran mutu — dipakai view untuk me-loop, bukan menyalin empat blok. */
export type KernelDispatchReportQualityKey = Exclude<keyof KernelDispatchReportQuality, '_note'>

/**
 * Satu baris rekap kategoris — bentuk IDENTIK untuk keempat rekap, termasuk
 * by_gate_status.
 *
 * `value` null adalah KELOMPOKNYA SENDIRI dan tidak pernah dibuang: itulah
 * yang membuat penjumlahan dispatch_count tiap rekap TEPAT sama dengan
 * dispatches.dispatch_count. Kuncinya null HARFIAH — bukan string kosong dan
 * bukan label karangan — supaya layar dapat menyebutnya 'tidak tercatat' tanpa
 * server mengarang nilai yang tidak pernah dicatat.
 *
 * PADA by_gate_status `value` ADALAH KODE MENTAH ('released' / 'not_released',
 * dan apa pun yang sempat tersimpan lewat jalur selain form web). Labelnya
 * diturunkan DI KLIEN lewat helper bersama `optionLabel()`; repo ini tidak
 * memetakan apa pun.
 *
 * URUTAN BARIS MILIK SERVER (netto DESC → jumlah DESC → nilai ASC). Tidak ada
 * pengurutan di berkas ini dan tidak boleh ada di view.
 */
export interface KernelDispatchReportBreakdownRow {
  value: string | null
  dispatch_count: number
  net_weight_total: number | null
  net_weight_dispatch_count: number
  net_weight_avg: number | null
}

/**
 * JEJAK KEPATUHAN, blok tersendiri — sengaja TIDAK di dalam `dispatches`,
 * karena ini bukan ukuran produksi melainkan dokumen angkut yang tidak terbit.
 * Ketiganya penghitung KEHADIRAN; nol pun tetap diterbitkan, dan nol itulah
 * yang memberi tahu pemeriksaannya berjalan.
 *
 * SEGEL ATAS DAN SEGEL BAWAH ADALAH DUA PENGHITUNG DAN TIDAK PERNAH SATU. Satu
 * pengiriman yang kehilangan KEDUA segelnya terhitung di KEDUA penghitung —
 * dua fakta, bukan penghitungan ganda — dan menjumlahkannya menghapus
 * perbedaan antara satu kendaraan tanpa dua segel dan dua kendaraan yang
 * masing-masing kehilangan satu.
 */
export interface KernelDispatchReportCompliance {
  missing_waybill_count: number
  missing_seal_top_count: number
  missing_seal_bottom_count: number
  /** Prosa SERVER, dirender UTUH di layar. */
  _note?: string
}

/** Satu baris rekap harian — satu entri per HARI BERPENGIRIMAN, bukan per hari kalender. */
export interface KernelDispatchReportDailyRow {
  date: string
  dispatch_count: number
  net_weight_total: number | null
  net_weight_dispatch_count: number
  net_weight_avg: number | null
}

/**
 * Kaki rekap harian: DIJUMLAHKAN SERVER dari daily[], BUKAN disalin dari blok
 * `dispatches`. Keduanya memang dapat berbeda, tepatnya sebesar
 * undated_dispatch_count — dan perbedaan itu adalah INFORMASI, jadi klien
 * membacanya apa adanya alih-alih menjumlahkan kolomnya sendiri.
 */
export interface KernelDispatchReportDailyTotal {
  dispatch_count: number
  net_weight_total: number | null
  net_weight_dispatch_count: number
  net_weight_avg: number | null
}

/**
 * Kelengkapan pencatatan — mengukur HARI, bukan kolom.
 *
 * `percent` NULL — bukan 0.0 — ketika days_counted 0 (periode belum mulai),
 * dan layar mencetak '—': nol persen berarti ada hari yang diharapkan dan tak
 * satu pun terisi, sedangkan '—' berarti belum ada hari yang diharapkan sama
 * sekali.
 */
export interface KernelDispatchReportCompleteness {
  days_in_period: number
  days_with_dispatch: number
  /** Berhenti di HARI INI untuk periode yang masih berjalan; 0 bila belum mulai. */
  days_counted: number
  percent: number | null
}

/**
 * Satu kejadian teks bebas. `date` boleh null (pengiriman tanpa tanggal
 * kejadian) dan server mengurutkannya PALING BELAKANG — ia tetap sebuah
 * catatan yang ditulis seseorang.
 */
export interface KernelDispatchReportTextEntry {
  date: string | null
  value: string
}

/**
 * SATU DAFTAR, BUKAN DUA. Stasiun ini TIDAK punya kolom `remarks` — berbeda
 * dari Solid Waste Disposal — jadi struktur dua-daftar dari sana SENGAJA tidak
 * disalin. Kunci `findings` tetap terbit meski daftarnya kosong, supaya layar
 * dapat mencetak pernyataan 'tidak ada' alih-alih menghilangkan bagiannya.
 */
export interface KernelDispatchReportTextEntries {
  findings: KernelDispatchReportTextEntry[]
}

/**
 * Seluruh angka layar untuk satu periode pada satu Production Line.
 *
 * TIDAK ADA satu pun kunci per jam, TIDAK ADA waktu putar kendaraan, TIDAK ADA
 * ambang mutu, dan TIDAK ADA dimensi shift — keempat ketiadaan itu disengaja,
 * masing-masing punya alasannya sendiri, dan ketiganya DINYATAKAN payload
 * lewat ketiga prosa `_note_*` supaya tidak satu pun terbaca sebagai bagian
 * yang gagal dimuat.
 */
export interface KernelDispatchReportSummary {
  business_unit?: KernelDispatchReportBusinessUnitRef | null
  production_line?: KernelDispatchReportProductionLineRef | null
  period?: KernelDispatchReportPeriodHeader | null
  dispatches?: KernelDispatchReportDispatches
  quality?: KernelDispatchReportQuality
  by_transporter?: KernelDispatchReportBreakdownRow[]
  /**
   * `silo_source_id` ADALAH TEKS BEBAS meski namanya berakhiran `_id`:
   * migrasinya menuliskannya `string()->nullable()`, tidak ada tabel silo di
   * satu migrasi pun, dan form merendernya `<input type="text">`. Nol join,
   * nol pencarian nama, nol pemetaan — baik di sini maupun di view.
   */
  by_silo_source?: KernelDispatchReportBreakdownRow[]
  by_destination_buyer?: KernelDispatchReportBreakdownRow[]
  by_gate_status?: KernelDispatchReportBreakdownRow[]
  /**
   * HANYA `value === 'not_released'` PERSIS — baris null TIDAK termasuk.
   * 'Tidak tercatat' dan 'tidak dilepas' adalah dua keadaan berbeda, dan
   * menggabungkannya MENUDUH pengiriman yang sekadar belum diisi statusnya.
   * Angka ini DITAMPILKAN, tidak pernah dihitung ulang dari rekapnya — itu
   * cara paling mudah memasukkan baris null ke dalamnya tanpa sengaja.
   */
  not_released_count?: number
  compliance?: KernelDispatchReportCompliance
  /** Pengiriman draft IKUT di seluruh angka di atas; ini pernyataannya, bukan penyaringnya. */
  draft_dispatch_count?: number
  /**
   * Pengiriman tanpa tanggal kejadian: ikut di dispatch_count, di keempat
   * penyebut mutu dan di keempat rekap, TIDAK ikut di daily[]. Kolom
   * `event_date` NOT NULL di basis data, jadi angkanya selalu 0 dalam praktik
   * — dan layar karena itu MERENDERNYA HANYA ketika > 0.
   */
  undated_dispatch_count?: number
  daily?: KernelDispatchReportDailyRow[]
  daily_total?: KernelDispatchReportDailyTotal
  completeness?: KernelDispatchReportCompleteness
  text_entries?: KernelDispatchReportTextEntries
  /** Prosa SERVER, dirender UTUH — tidak diringkas dan tidak diparafrasekan. */
  _note_no_hourly?: string
  _note_no_quality_thresholds?: string
  _note_shift_not_published?: string
}

/**
 * Cakupan angka yang diminta pemanggil.
 *
 * `isAdmin` sengaja WAJIB dinyatakan EKSPLISIT oleh pemanggil yang hendak
 * mengirim business_unit_id — lihat catatan "gagal tertutup" pada docblock
 * berkas. Tanpa pernyataan itu, parameter mill tidak pernah terkirim.
 */
export interface KernelDispatchReportScope {
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
export interface KernelDispatchReportQuery {
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
 * LINES_PATH TIDAK DIDUPLIKASI DI BAWAH PREFIX kernel-dispatch-reports: ia
 * endpoint screen-135 yang dipakai ulang verbatim, dan permintaannya pun tetap
 * dikerjakan productionLineRepo (lihat fetchLines).
 */
export const BUSINESS_UNITS_PATH = '/api/kernel-dispatch-reports/business-units/options'
export const PERIODS_PATH = '/api/kernel-dispatch-reports/periods'
export const SUMMARY_PATH = '/api/kernel-dispatch-reports/summary'
export const EXPORT_PATH = '/api/kernel-dispatch-reports/export'
export const LINES_PATH = '/api/production-lines/options-for-report'

/* ------------------------------------------------------------------ */
/* Pembentuk parameter                                                 */
/* ------------------------------------------------------------------ */

/** Menyatukan kedua bentuk pemanggilan menjadi satu objek pertanyaan. */
function queryOf(
  period: string | KernelDispatchReportQuery,
  scope?: KernelDispatchReportScope,
): KernelDispatchReportQuery {
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
function summaryParams(query: KernelDispatchReportQuery): Record<string, string> {
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
  period: string | KernelDispatchReportQuery,
  scope?: KernelDispatchReportScope,
): Record<string, string> {
  return { ...summaryParams(queryOf(period, scope)), format: 'csv' }
}

/* ------------------------------------------------------------------ */
/* Pengambilan                                                         */
/* ------------------------------------------------------------------ */

/**
 * GET /api/kernel-dispatch-reports/business-units/options — pemilih Mill,
 * ADMIN SAJA.
 *
 * Server menjawab 403 untuk ketiga peran lain, Operator TERMASUK; penolakannya
 * diangkat di dalam KernelDispatchReportService::businessUnitOptions(), bukan
 * oleh middleware rute. View TIDAK BOLEH memanggilnya untuk peran yang terikat
 * mill — lihat docblock berkas.
 *
 * Master kosong adalah jawaban SAH: { data: [] } dengan HTTP 200, bukan 404.
 */
export async function fetchBusinessUnits(): Promise<KernelDispatchReportBusinessUnitOption[]> {
  const response = await apiClient.get(BUSINESS_UNITS_PATH)

  return (response.data?.data ?? []) as KernelDispatchReportBusinessUnitOption[]
}

/**
 * GET /api/production-lines/options-for-report — daftar Production Line untuk
 * pemilih WAJIB di layar ini.
 *
 * MENDELEGASIKAN ke productionLineRepo.fetchProductionLinesForReport(): URL-nya
 * tetap DIDEFINISIKAN SATU KALI di repo itu, endpoint screen-135 ini tidak
 * diduplikasi di bawah prefix kernel-dispatch-reports, dan tidak ada pemeta
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
  scope?: KernelDispatchReportScope,
): Promise<ProductionLineOption[]> {
  const params = millParams(scope)

  return productionLineRepo.fetchProductionLinesForReport(params.business_unit_id ?? null)
}

/**
 * GET /api/kernel-dispatch-reports/periods — periode yang mencakup stasiun
 * Kernel Dispatch, yakni periode yang punya baris period_stations berjenis
 * 'kernel-dispatch' (DENGAN TANDA HUBUNG; server membaca nilainya dari
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
  scope?: KernelDispatchReportScope,
): Promise<KernelDispatchReportPeriodOption[]> {
  const response = await apiClient.get(PERIODS_PATH, { params: millParams(scope) })

  return (response.data?.data ?? []) as KernelDispatchReportPeriodOption[]
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
 * GET /api/kernel-dispatch-reports/summary — SELURUH angka layar untuk satu
 * periode pada satu Production Line, dan satu-satunya sumbernya.
 *
 * BODY-NYA DITERUSKAN UTUH: nol kunci dibuang, nol bawaan disuntikkan, nol
 * operasi aritmetika. Tidak ada penjumlahan baris rekap, tidak ada penurunan
 * missing_net_weight_dispatch_count dari dispatch_count −
 * net_weight_dispatch_count, tidak ada perata-rataan ulang netto, tidak ada
 * min/avg/max yang dihitung ulang, tidak ada penjumlahan daily[] menjadi
 * daily_total, tidak ada penurunan persen kelengkapan dari
 * days_with_dispatch/days_counted, tidak ada not_released_count yang dihitung
 * ulang dari by_gate_status, tidak ada pengelompokan ulang nilai kategoris
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
  period: string | KernelDispatchReportQuery,
  scope?: KernelDispatchReportScope,
): Promise<KernelDispatchReportSummary> {
  const response = await apiClient.get(SUMMARY_PATH, {
    params: summaryParams(queryOf(period, scope)),
  })

  return (unwrap<KernelDispatchReportSummary>(response.data) ??
    {}) as KernelDispatchReportSummary
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
  period: string | KernelDispatchReportQuery,
  scope?: KernelDispatchReportScope,
): string {
  return `${EXPORT_PATH}?${new URLSearchParams(exportParams(period, scope)).toString()}`
}

/**
 * GET /api/kernel-dispatch-reports/export?period_id=…&production_line_id=…&format=csv
 * — respons streaming text/csv, BUKAN JSON, jadi responseType-nya blob.
 *
 * Isinya dibentuk SERVER dan sama persis dengan ekspor laporan versi web,
 * karena endpoint-nya memang sama: SATU BARIS PER PENGIRIMAN, dengan ENAM
 * kolom konteks (Periode, Rentang Periode, Mill, Production Line, Tanggal
 * Record, Status) diulang verbatim di setiap baris, disusul KEDUA PULUH SATU
 * kolom detail — dua puluh tujuh kolom seluruhnya, lihat
 * KernelDispatchReportService::EXPORT_HEADER. Enam di antaranya (`Shift`,
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
  period: string | KernelDispatchReportQuery,
  scope?: KernelDispatchReportScope,
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

export const kernelDispatchReportRepo = {
  fetchBusinessUnits,
  fetchLines,
  fetchPeriods,
  fetchSummary,
  buildExportUrl,
  exportParams,
  exportCsv,
  saveCsvFile,
}

export default kernelDispatchReportRepo
