import apiClient from '@/services/apiClient'

/**
 * effluentPlantReportRepo — screen-157--laporan-effluent-plant-mobile /
 * usecase-163--laporan-effluent-plant-mobile "Lihat Laporan Periode Effluent
 * Plant (Mobile)".
 *
 * Kembaran KEDUA BELAS dari pola yang sama: sterilizerReportRepo.ts
 * (screen-135), cagesTrackReportRepo.ts (screen-136), boilerRoomReportRepo.ts
 * (screen-137), clarificationReportRepo.ts (screen-138),
 * storageTankReportRepo.ts (screen-139), weighbridgeReportRepo.ts
 * (screen-144), gradingReportRepo.ts (screen-147), threshingReportRepo.ts
 * (screen-149), pressingReportRepo.ts (screen-151),
 * depricarpingReportRepo.ts (screen-153), dan kernelPlantReportRepo.ts
 * (screen-155). Satu berkas repo tipis di atas apiClient, tanpa cache lokal.
 * Layar laporan ini memang MEMBUTUHKAN jaringan, jadi tidak ada jalur SQLite
 * offline di sini sama sekali — kegagalan jaringan adalah kondisi yang
 * ditampilkan ke pengguna, bukan yang disembunyikan di balik data basi.
 *
 * ────────────────────────────────────────────────────────────────────────
 * BERBEDA DARI screen-155: LAYAR INI MENUNTUT PERUBAHAN BACKEND
 * ────────────────────────────────────────────────────────────────────────
 * Kernel Plant mobile nol perubahan backend karena kedua layarnya
 * direncanakan dalam satu seri. Di sini screen-156 (web) mendarat lebih dulu
 * dan SENGAJA menolak Operator 403 pada keempat rute, karena belum ada
 * pemanggil Operator yang sah. Layar inilah pemanggil itu, jadi pelebaran
 * aksesnya dikerjakan BERSAMA layar ini: `operator` ditambahkan pada daftar
 * peran middleware di routes/api.php, pada
 * EffluentPlantReportService::guardAccess(), DAN pada cabang TERIKAT-MILL
 * resolveBusinessUnit(). Ketiganya satu perubahan; melakukan dua dari tiga
 * menjatuhkan Operator ke cabang Admin tempat business_unit_id kiriman klien
 * DIHORMATI — kebocoran lintas mill, bukan cacat tampilan.
 *
 * TIDAK ADA business_unit_id DI BERKAS INI, SAMA SEKALI — sama seperti
 * kernelPlantReportRepo.ts dan dengan alasan yang sama: kedua aktor layar ini
 * (Operator dan Supervisor) TERIKAT SATU MILL, server menyelesaikan mill dari
 * akun, jadi parameter itu tidak punya pekerjaan apa pun di sini. Cabang
 * Admin (scopeParams + fetchBusinessUnits) yang masih dibawa kesepuluh repo
 * pertama TIDAK ada, jadi mustahil ada jalur yang mengirimkannya —
 * gagal-tertutup secara KONSTRUKSI, bukan secara penjagaan. Konsekuensinya
 * disengaja: /api/effluent-plant-reports/business-units/options tidak
 * dipanggil dari mana pun, karena rute itu memang Admin saja (403 untuk
 * Operator/Supervisor/Mill Management, diangkat service bukan middleware) dan
 * menyerahkan daftar seluruh mill kepada peran terikat-mill adalah justru
 * kebocoran yang dihindari. Admin dan Mill Management memakai layar WEB
 * screen-156, yang satu-satunya punya pemilih mill.
 *
 * NOL PERHITUNGAN ULANG DI KLIEN. Berkas ini tidak pernah menjumlah,
 * merata-ratakan, membulatkan, mengurutkan, menyaring, atau menurunkan satu
 * angka pun — TERMASUK tidak memetakan satuan 'C' menjadi '°C'. Seluruh nilai
 * diteruskan apa adanya dari respons server, karena laporan web memakai
 * sumber yang sama (EffluentPlantReportService): perhitungan kedua di sisi
 * klien pasti akan menyimpang dari laporan web tanpa ketahuan, dan ponsel
 * yang berselisih dengan layar web tentang angka yang sama adalah bentuk
 * kegagalan yang paling sulit dibantah. Ia sekaligus memindahkan seluruh
 * perbedaan SQLite-versus-PostgreSQL ke satu tempat yang sudah diuji:
 * agregasi Effluent Plant seluruhnya dikerjakan di PHP, bukan di SQL, justru
 * karena perilaku agregat atas EMPAT BELAS kolom nullable (tiga di antaranya
 * hampir selalu NULL) berbeda di antara keduanya.
 *
 * ────────────────────────────────────────────────────────────────────────
 * TIGA HAL YANG MEMBUAT TIPE RESPONS INI BERBEDA DARI KERNEL PLANT
 * ────────────────────────────────────────────────────────────────────────
 * Menyalin tipe kernelPlantReportRepo.ts mentah-mentah akan membuat tipe ini
 * BERBOHONG — ketiganya perbedaan bentuk, bukan perbedaan nilai:
 *
 * 1. TIDAK ADA OBJEK `target` SAMA SEKALI. Tidak ada `equipment_parameter`,
 *    tidak ada `target_benchmark`, tidak ada `corrective_action_plan`, tidak
 *    ada `shares_standard_with` — dan karena itu seluruh logika keterangan
 *    standar-bersama beserta penjaga null-nya ikut hilang. Effluent Plant
 *    TIDAK PUNYA tabel master target operasional (hanya Threshing, Pressing,
 *    Depricarping, dan Kernel Plant punya), dan empat dari empat belas
 *    metriknya (pH/BOD/COD/TSS buangan akhir) adalah parameter KEPATUHAN
 *    LINGKUNGAN yang baku mutunya hidup DI LUAR aplikasi ini. Payload-nya
 *    karena itu tidak membawa satu pun kunci yang menyatakan patuh atau tidak
 *    patuh, dan tidak satu pun penanda di luar batas. Menerbitkan medan
 *    target yang selamanya null di sini hanya akan menyerahkan sel yang tidak
 *    mungkin diisi siapa pun ke layar.
 *
 * 2. TIDAK ADA BLOK `downtime`. Tidak ada `total_minutes` dan tidak ada
 *    `recorded_slot_count`: effluent_plant_details tidak punya satu pun kolom
 *    downtime. Menyalin KernelPlantReportDowntime ke sini menghasilkan
 *    undefined di mana-mana, dan layar yang menggambarnya akan memamerkan
 *    blok yang tidak pernah bisa terisi.
 *
 * 3. ADA TIGA BENTUK YANG TIDAK DIMILIKI SATU PUN LAPORAN MOBILE SEBELUMNYA:
 *    `status_distribution{}` (tiga kolom kategoris sebagai sebaran jumlah slot
 *    per nilai), DUA daftar teks bebas berupa daftar KEJADIAN
 *    (`maintenance_remarks[]` dan `findings[]`, masing-masing
 *    {date, time_slot, text} — BUKAN dikelompokkan menurut teksnya seperti
 *    Kernel Plant mobile), dan penanda `is_lab` per metrik.
 *
 * effluent_plant_id BERTIPE `string` BIASA — bukan uuid dan bukan kunci
 * asing. Tidak ada tabel master effluent_plants: nilainya kolom teks yang
 * DIKETIK di layar input, bentuk yang sama dengan boiler_room_id. Satu nama
 * yang sama pada dua tanggal adalah SATU unit dengan dua hari pencatatan.
 *
 * SETIAP METRIK MEMBAWA PENYEBUTNYA SENDIRI. `metrics[kolom].reading_count`
 * adalah banyaknya slot yang BENAR-BENAR MENCATAT kolom itu — penyebut
 * min/avg/max-nya. Keempat belas kolom nullable dan terisi saling bebas, jadi
 * satu penyebut bersama akan salah untuk setidaknya tiga belas di antaranya;
 * dan pada ketiga kolom LABORATORIUM (is_lab true) penyebut yang satu orde
 * lebih kecil adalah KEADAAN NORMAL, bukan data yang hilang.
 *
 * SATU KEJANGGALAN YANG BENAR: coverage.filled_slots BISA LEBIH BESAR
 * daripada penyebut kolom ukur mana pun. Sebuah slot dihitung terisi bila
 * salah satu dari SEMBILAN BELAS kolom bacaan terisi (definisinya DIPINJAM
 * dari EffluentPlantRecordService::isRowFilled(), bukan diturunkan ulang),
 * dan lima di antaranya (tiga kolom status, dua kolom teks) bukan kolom ukur
 * — slot yang hanya memuat temuan jelas disentuh operator, jadi
 * melaporkannya sebagai slot kosong akan salah. Yang dilampaui adalah
 * penyebut KARTU, bukan penyebut CAKUPAN. Dicatat di sini supaya pembaca
 * berikutnya tidak "memperbaiki" selisih yang memang benar.
 *
 * NULL BUKAN NOL. min, avg, max tiap metrik, coverage_percent, dan seluruh
 * nilai di dalam `metrics_avg` boleh null. Mengoersinya menjadi 0 (atau '' /
 * '-') di sini akan mengubah "tidak ada yang diukur" menjadi "hasilnya nol" —
 * dua fakta yang berbeda, dan yang satu menyesatkan. Penerjemahan null
 * menjadi teks terbaca adalah urusan view, bukan repo.
 *
 * SEBARAN STATUS DITERUSKAN UTUH, DAN VIEW MENGITERASI `known_values`.
 * Server sudah melengkapi nilai dikenal yang tidak pernah muncul dengan 0;
 * mengiterasi kunci `counts` yang ada akan menghilangkan baris bernilai 0,
 * dan baris yang hilang terbaca sebagai "nilai ini tidak ada" padahal yang
 * benar adalah "nilai ini tidak pernah terjadi". `not_recorded` adalah kunci
 * TERPISAH dan TIDAK PERNAH dilipat ke 'off'/'stop'. Penyebut porsinya
 * `recorded_slots`, BUKAN `expected_slots`.
 *
 * GALAT DITERUSKAN APA ADANYA. Tidak ada try/catch dan tidak ada kelas galat
 * khusus di sini. Interceptor apiClient menolak lewat normalizeError yang
 * mengembalikan objek DATAR { message, errors?, status? } dan MEMBUANG
 * `response` — jadi `.response` tidak pernah ada pada galat yang sampai ke
 * pemanggil. 401 diterjemahkan menjadi "arahkan ke Login TANPA pesan
 * jaringan" oleh LaporanEffluentPlantView (membaca `error.status`), dan
 * kegagalan transport (penolakan tanpa `status`) menjadi pesan + tombol Coba
 * Lagi di sana pula.
 *
 * DAFTAR PRODUCTION LINE TIDAK ADA DI BERKAS INI, dan itu bukan kelalaian:
 * GET /api/production-lines/options-for-report DIPAKAI ULANG VERBATIM dari
 * screen-135 lewat productionLineRepo.fetchProductionLinesForReport(null) —
 * endpoint itu tidak diduplikasi di bawah prefix effluent-plant-reports, dan
 * repo laporan ini pun tidak membungkusnya kedua kali.
 */

export interface EffluentPlantReportPeriodOption {
  id: string
  name: string
  start_date: string
  end_date: string
  /**
   * Status STASIUN LAYAR INI di dalam periode itu (baris period_stations),
   * BUKAN status periode: periode tidak punya status sendiri karena stasiun
   * tidak ditutup serentak. Tanpa baris untuk jenis ini nilainya 'draft',
   * yang artinya memang "stasiun ini belum dipakai di periode itu".
   */
  status: string
  /** Selalu terisi 'effluent-plant' — DENGAN TANDA HUBUNG, dan tidak pernah null. */
  station_type: string
  station_type_label: string
}

export interface EffluentPlantReportBusinessUnitRef {
  id: string
  name: string
}

export interface EffluentPlantReportProductionLineRef {
  id: string
  name: string
}

/**
 * Kepala periode pada respons /summary.
 *
 * Payload juga memuat `period.business_unit_name`, dan medan itu SENGAJA
 * tidak ditulis di sini: nama mill dibaca view dari blok `business_unit`
 * (lihat EffluentPlantReportBusinessUnitRef), satu sumber saja, supaya tidak
 * ada dua tempat yang dapat menyimpang satu dari yang lain. Respons /periods
 * TIDAK memuat nama mill sama sekali — koreksi itu sudah ditemukan saat
 * screen-155 diimplementasikan dan tidak diwarisi salah lagi ke sini.
 */
export interface EffluentPlantReportPeriodHeader {
  id: string
  name: string
  start_date: string
  end_date: string
  status: string
}

/**
 * Cakupan pencatatan — dibaca PALING ATAS di layar, bukan sebagai catatan
 * kaki: periode yang terisi seperlima pun menghasilkan rata-rata yang
 * terlihat rapi, dan di stasiun ini EMPAT dari empat belas metriknya adalah
 * parameter kepatuhan lingkungan.
 *
 * Ketiga angka pembentuk penyebut ikut dikirim (effluent_plant_unit_count,
 * days_counted, slots_per_unit_per_day) supaya layar dapat menampilkan dari
 * mana expected_slots berasal, alih-alih sebuah persen tanpa asal.
 */
export interface EffluentPlantReportCoverage {
  /**
   * Slot yang terisi atas SEMBILAN BELAS kolom — keempat belas kolom ukur,
   * ketiga kolom status, dan KEDUA kolom teks. Karena itu angka ini dapat
   * MELEBIHI `reading_count` setiap kartu metrik, dan itu benar.
   */
  filled_slots: number
  expected_slots: number
  /**
   * Server mengirim 0.0 — bukan null — ketika expected_slots nol, jadi VIEW
   * memutuskan tanda pisah dari `expected_slots === 0`, bukan dari null-nya
   * medan ini. null tetap ditulis pada tipe supaya respons yang kelak
   * mengirimnya null tidak memaksa perubahan tipe. Periode yang PUNYA record
   * tetapi tanpa satu slot terisi menghasilkan 0.0, dan 0,0% di situ benar:
   * penyebutnya terbentuk, dan yang terukur memang nol slot.
   */
  coverage_percent: number | null
  /**
   * Unit Effluent Plant yang BENAR-BENAR punya record pada periode dan line
   * ini, bukan jumlah stasiun terdaftar — penyebut dari stasiun terdaftar
   * akan menghukum mill yang sengaja tidak mengoperasikan satu unitnya.
   */
  effluent_plant_unit_count: number
  /** 24, dari kisi slot kanonis layar input itu sendiri (07:00–06:00). */
  slots_per_unit_per_day: number
  days_in_period: number
  /** Berhenti di hari ini untuk periode yang masih berjalan. */
  days_counted: number
  /**
   * BUKAN pengganti days_counted, melainkan pendampingnya: server menandai
   * periode yang BELUM MULAI sebagai masih berjalan juga, jadi layar wajib
   * memeriksa days_counted === 0 LEBIH DULU.
   */
  period_running: boolean
}

/**
 * Satu kolom ukur, dengan PENYEBUTNYA SENDIRI.
 *
 * Perhatikan apa yang TIDAK ada di sini: tidak ada `target`, tidak ada
 * severity, tidak ada is_out_of_range, tidak ada flag — dan tidak ada
 * `label` maupun `unit`, karena payload Effluent Plant memang tidak
 * menerbitkannya (tidak seperti Kernel Plant). Keduanya tetap ditulis sebagai
 * medan OPSIONAL supaya nilai server, bila kelak diterbitkan, MENANG atas
 * tabel label view — dan supaya repo ini tidak pernah punya peta satuan yang
 * mengubah 'C' menjadi '°C'.
 */
export interface EffluentPlantReportMetric {
  min: number | null
  avg: number | null
  max: number | null
  /** PENYEBUT min/avg/max kolom ini — bukan coverage.filled_slots. */
  reading_count: number
  /**
   * true untuk ketiga nilai LABORATORIUM (BOD/COD/TSS buangan akhir), yang
   * disampel beberapa hari sekali dari hasil lab alih-alih dibaca dari
   * instrumen tiap slot. Penyebut yang satu orde lebih kecil pada ketiganya
   * adalah KEADAAN NORMAL, bukan data yang hilang.
   *
   * VIEW WAJIB MENURUNKAN PENANDA 'lab' DARI MEDAN INI, bukan dari daftar
   * nama kolom yang dipaku di template: kolom laboratorium keempat kelak
   * ditandai benar tanpa menyunting satu berkas klien pun. Pelajaran yang
   * sama dengan shares_standard_with di Kernel Plant.
   */
  is_lab: boolean
  /** Hanya bila server menerbitkannya; view punya tabel labelnya sendiri. */
  label?: string
  /** Satuan APA ADANYA dari server bila ada — tidak pernah dipercantik di sini. */
  unit?: string
}

/**
 * Sebaran satu kolom status kategoris: jumlah slot per nilai.
 *
 * TIDAK PERNAH min/avg/max dan TIDAK PERNAH muncul pada tren — terendah,
 * rata-rata, dan tertinggi tidak punya arti untuk kolom kategoris.
 */
export interface EffluentPlantReportStatusDistribution {
  /**
   * URUTAN DAN KELENGKAPAN SEBARAN ADA DI SINI, dan VIEW MENGITERASI MEDAN
   * INI — bukan kunci `counts`. Server mengelompokkan nilai yang benar-benar
   * ada secara harfiah lebih dulu, lalu melengkapi nilai dikenal yang tidak
   * pernah muncul dengan 0, lalu menambahkan nilai di luar himpunan dikenal
   * di belakang (supaya nilai baru dari migrasi tidak hilang). Mengiterasi
   * `counts` akan menghilangkan baris bernilai 0, dan baris yang hilang
   * terbaca sebagai "nilai ini tidak ada" padahal yang benar adalah "nilai
   * ini tidak pernah terjadi".
   */
  known_values: string[]
  counts: Record<string, number>
  /**
   * Slot TERISI yang kolom status ini-nya NULL. Kunci TERPISAH, dan TIDAK
   * PERNAH dilipat ke 'off'/'stop': itu akan melaporkan flare mati atau pompa
   * berhenti yang tidak pernah terjadi, dan di stasiun ini "tidak ada yang
   * menuliskannya" dan "ia tidak berjalan" punya akibat yang sama sekali
   * berbeda.
   */
  not_recorded: number
  /**
   * PENYEBUT porsi tiap nilai — jumlah slot TERISI. BUKAN expected_slots,
   * yang akan mencampur slot tak tersentuh ke dalam porsi sebuah nilai yang
   * justru tercatat. Invariannya: sum(counts) + not_recorded === recorded_slots.
   */
  recorded_slots: number
}

/**
 * Satu KEJADIAN teks bebas — dan bentuk inilah yang membedakannya dari
 * Kernel Plant mobile, yang mengelompokkan temuan menurut TEKS-nya.
 *
 * Di sini satu entri = satu SLOT yang mengisinya, membawa tanggal dan slot
 * waktunya. Pengelompokan menurut teks akan menghilangkan tanggal dan slot
 * waktu yang justru satu-satunya cara menemukan kembali kejadiannya.
 *
 * Dipakai DUA KALI pada dua daftar TERPISAH (maintenance_remarks dan
 * findings), dan keduanya TIDAK PERNAH disatukan menjadi daftar ketiga: satu
 * slot yang mengisi kedua kolom muncul pada KEDUA daftar, dan itu benar —
 * menyatukannya akan menghitung slot itu dua kali di dalam satu daftar dan
 * mencampur dua pertanyaan yang berbeda (apa yang dikerjakan vs apa yang
 * ditemukan).
 */
export interface EffluentPlantReportTextEntry {
  date: string
  time_slot: string
  text: string
}

/** Satu titik tren harian: rata-rata tanggal itu, dengan penyebutnya sendiri. */
export interface EffluentPlantReportTrendPoint {
  date: string
  /** null — BUKAN 0 — bila tanggal itu tidak sekali pun mencatat kolomnya. */
  avg: number | null
  reading_count: number
}

/**
 * KEDUA tren harian, dan hanya dua: laju POME masuk dan pH buangan akhir.
 * Ketiga nilai laboratorium SENGAJA tidak digrafikkan harian — dengan
 * penyebut sekecil itu garisnya akan lebih banyak lubang daripada data, dan
 * segmen yang menyambung dua titik berjauhan menyiratkan pengukuran yang
 * tidak pernah dilakukan. Ketiga kolom status pun tidak pernah muncul di sini.
 */
export interface EffluentPlantReportDailyTrend {
  raw_pome_feed_rate_m3h: EffluentPlantReportTrendPoint[]
  final_discharge_ph: EffluentPlantReportTrendPoint[]
}

/** Satu nilai rata-rata di dalam rekap, dengan penyebutnya sendiri. */
export interface EffluentPlantReportAverage {
  avg: number | null
  reading_count: number
}

/**
 * Satu baris rekap per UNIT Effluent Plant.
 *
 * effluent_plant_id adalah `string` biasa — bukan uuid dan bukan kunci asing.
 * `metrics` membawa BENTUK YANG SAMA dengan `metrics` tingkat periode (empat
 * belas entri, masing-masing dengan penyebutnya sendiri dan penanda is_lab
 * -nya sendiri), tidak bentuk {avg, reading_count} milik `daily`.
 *
 * `reading_count` diterbitkan BERDAMPINGAN dengan `reading_rows` bernilai
 * sama: kedua nama dipakai bergantian di sebelas laporan saudaranya, dan
 * menerbitkan keduanya membuat setiap pemanggil tetap membaca kunci yang
 * sudah diharapkannya. Unit yang punya record tetapi tidak satu pun
 * pembacaan TETAP muncul dengan reading_rows 0 — membuangnya justru akan
 * menyembunyikan unit yang tidak pernah dituliskan.
 */
export interface EffluentPlantReportUnitRow {
  effluent_plant_id: string
  reading_rows: number
  reading_count: number
  metrics: Record<string, EffluentPlantReportMetric>
}

/**
 * Satu baris rekap per HARI yang punya minimal satu slot terisi.
 *
 * Tanggal tanpa satu pun slot terisi TIDAK mendapat baris: baris nol akan
 * terbaca sebagai "terukur nol", padahal tidak ada yang diukur. Perhatikan
 * kuncinya `metrics_avg` dengan bentuk {avg, reading_count} — BUKAN
 * `averages: Record<string, number | null>` milik repo Kernel Plant.
 */
export interface EffluentPlantReportDailyRow {
  date: string
  reading_rows: number
  metrics_avg: Record<string, EffluentPlantReportAverage>
}

/**
 * Total baris rekap harian — HANYA jumlah slot, tanpa rata-rata.
 *
 * Rata-rata tingkat periode ada di `metrics` dan DIHITUNG ULANG server atas
 * seluruh slot terisi, bukan dirata-ratakan dari rata-rata harian:
 * merata-ratakan rata-rata memberi bobot sama pada hari berisi dua slot dan
 * hari berisi dua puluh empat. View memakai `metrics` untuk baris total,
 * bukan menurunkannya dari `daily`.
 */
export interface EffluentPlantReportDailyTotal {
  reading_rows: number
}

export interface EffluentPlantReportTotals {
  days_with_records: number
  reading_rows: number
}

/**
 * Seluruh angka layar untuk satu periode pada satu Production Line.
 *
 * TIDAK ADA `target`, TIDAK ADA `downtime`, dan TIDAK ADA satu pun kunci
 * kepatuhan/penandaan di luar batas — lihat ketiga catatan pada docblock
 * berkas. Yang ADA dan tidak dimiliki laporan mobile mana pun sebelumnya:
 * `status_distribution`, dua daftar kejadian teks bebas, dan `is_lab`.
 */
export interface EffluentPlantReportSummary {
  business_unit: EffluentPlantReportBusinessUnitRef | null
  production_line: EffluentPlantReportProductionLineRef | null
  period: EffluentPlantReportPeriodHeader | null
  /** Membedakan "tidak ada yang dilaporkan" dari "angkanya nol". */
  has_data: boolean
  coverage: EffluentPlantReportCoverage
  /** Selalu EMPAT BELAS entri, juga ketika sebuah kolom tidak pernah terisi. */
  metrics: Record<string, EffluentPlantReportMetric>
  /** Tiga kolom status kategoris sebagai sebaran jumlah slot per nilai. */
  status_distribution: Record<string, EffluentPlantReportStatusDistribution>
  /** "Apa yang dikerjakan" — daftar KEJADIAN, satu entri per slot. */
  maintenance_remarks: EffluentPlantReportTextEntry[]
  /** "Apa yang ditemukan" — daftar TERPISAH, satu entri per slot. */
  findings: EffluentPlantReportTextEntry[]
  daily_trend: EffluentPlantReportDailyTrend
  by_unit: EffluentPlantReportUnitRow[]
  daily: EffluentPlantReportDailyRow[]
  daily_total: EffluentPlantReportDailyTotal
  total: EffluentPlantReportTotals
}

/**
 * Cakupan angka yang diminta pemanggil — SATU medan saja, dan itu disengaja.
 *
 * Tidak ada `isAdmin` dan tidak ada `businessUnitId` di sini, sama seperti
 * repo Kernel Plant: mill diselesaikan server dari akun bagi kedua aktor
 * layar ini, jadi medan itu tidak punya pekerjaan dan medan yang tidak ada
 * tidak dapat salah terkirim.
 */
export interface EffluentPlantReportScope {
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
 * Nilai bawaan HANYA dipakai ketika seluruh BLOK tidak ada pada respons
 * (mis. bentuk respons berubah di server), bukan untuk menambal medan yang
 * dikirim null. Periode tanpa data tetap mengirim blok lengkap berisi
 * null/nol, dan blok itulah yang diteruskan.
 *
 * Ditulis sebagai literal — bukan dibangun lewat reduce()/map() — supaya
 * berkas ini tetap bebas dari operasi apa pun atas angka laporan, termasuk
 * yang sekadar terlihat seperti perhitungan.
 */
const EMPTY_COVERAGE: EffluentPlantReportCoverage = {
  filled_slots: 0,
  expected_slots: 0,
  coverage_percent: null,
  effluent_plant_unit_count: 0,
  slots_per_unit_per_day: 0,
  days_in_period: 0,
  days_counted: 0,
  period_running: false,
}

const EMPTY_DAILY_TREND: EffluentPlantReportDailyTrend = {
  raw_pome_feed_rate_m3h: [],
  final_discharge_ph: [],
}

const EMPTY_DAILY_TOTAL: EffluentPlantReportDailyTotal = {
  reading_rows: 0,
}

const EMPTY_TOTALS: EffluentPlantReportTotals = {
  days_with_records: 0,
  reading_rows: 0,
}

/**
 * production_line_id — dikirim HANYA ke /summary dan /export.
 *
 * TIDAK PERNAH ke /periods. Periode adalah milik MILL, bukan milik Production
 * Line, dan backend pun tidak menerima parameter ini di sana. Menyaring
 * daftar periode per line akan mengarang penyempitan yang tidak ada di data;
 * yang disaring sebuah line adalah DATA-nya, bukan daftar periodenya.
 *
 * Yang dijaga di sini hanya satu: nilai kosong tidak pernah dikirim sebagai
 * parameter kosong — server akan menjawab 422 tentang parameter yang HILANG,
 * dan itu pesan yang benar.
 */
function productionLineParams(scope?: EffluentPlantReportScope): Record<string, string> {
  const productionLineId = scope?.productionLineId

  if (!productionLineId) {
    return {}
  }

  return { production_line_id: productionLineId }
}

/**
 * Respons /summary dikirim controller TANPA pembungkus `data`, sementara
 * /periods MEMAKAI pembungkus itu. Kedua bentuk diterima di sini supaya repo
 * ini tidak pecah bila pembungkusnya kelak diseragamkan di sisi server.
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
 * GET /api/effluent-plant-reports/periods — periode yang mencakup stasiun
 * Effluent Plant, yakni periode yang punya baris period_stations berjenis
 * 'effluent-plant' (DENGAN TANDA HUBUNG; server membaca nilainya dari
 * App\Enums\StationType alih-alih menuliskannya, karena garis bawah di situ
 * tidak akan cocok dengan satu baris pun dan menjawab [] untuk setiap mill
 * tanpa satu galat).
 *
 * TANPA business_unit_id — tidak ada jalur kode untuk mengirimkannya. Server
 * menyelesaikan mill dari akun; akun terikat mill yang business_unit_id-nya
 * NULL dijawab 422 dan daftar seluruh mill tidak pernah dibaca.
 *
 * Penyaringannya dikerjakan SERVER; repo meneruskan daftar apa adanya, dalam
 * urutan yang sama — menyaringnya kedua kali di sini akan menciptakan
 * definisi cakupan yang kedua. Daftar kosong adalah jawaban yang sah (HTTP
 * 200 + []), bukan galat. Periode TERTUTUP tetap terdaftar — status mengatur
 * penulisan data, bukan pembacaan laporan.
 *
 * Respons ini TIDAK memuat nama mill: nama mill hanya ada pada
 * summary.business_unit.name.
 */
export async function fetchPeriods(): Promise<EffluentPlantReportPeriodOption[]> {
  const response = await apiClient.get('/api/effluent-plant-reports/periods')

  return (response.data?.data ?? []) as EffluentPlantReportPeriodOption[]
}

/**
 * GET /api/effluent-plant-reports/summary — seluruh angka layar untuk satu
 * periode pada satu Production Line.
 *
 * Setiap blok diteruskan APA ADANYA, tanpa satu operasi aritmetika pun: tidak
 * ada penurunan coverage_percent dari filled_slots/expected_slots, tidak ada
 * perata-rataan ulang min/avg/max, tidak ada penurunan expected_slots dari
 * unit × hari × 24 meski ketiga angkanya tersedia, tidak ada penurunan porsi
 * sebaran dari counts/recorded_slots, tidak ada pengelompokan kejadian teks
 * bebas menurut teksnya, tidak ada pengurutan ulang metrics / by_unit / daily
 * / kedua daftar teks, dan TIDAK ADA peta satuan yang mengubah 'C' menjadi
 * '°C'. Nilai bawaan di atas hanya berlaku bila BLOK-nya tidak ada sama
 * sekali pada respons.
 */
export async function fetchSummary(
  periodId: string,
  scope?: EffluentPlantReportScope,
): Promise<EffluentPlantReportSummary> {
  const response = await apiClient.get('/api/effluent-plant-reports/summary', {
    params: { period_id: periodId, ...productionLineParams(scope) },
  })

  const body = (unwrap<Record<string, unknown>>(response.data) ?? {}) as Record<string, unknown>

  return {
    business_unit: (body.business_unit ?? null) as EffluentPlantReportBusinessUnitRef | null,
    production_line: (body.production_line ?? null) as EffluentPlantReportProductionLineRef | null,
    period: (body.period ?? null) as EffluentPlantReportPeriodHeader | null,
    has_data: (body.has_data ?? false) as boolean,
    coverage: (body.coverage ?? { ...EMPTY_COVERAGE }) as EffluentPlantReportCoverage,
    metrics: (body.metrics ?? {}) as Record<string, EffluentPlantReportMetric>,
    status_distribution: (body.status_distribution ?? {}) as Record<
      string,
      EffluentPlantReportStatusDistribution
    >,
    maintenance_remarks: (body.maintenance_remarks ?? []) as EffluentPlantReportTextEntry[],
    findings: (body.findings ?? []) as EffluentPlantReportTextEntry[],
    daily_trend: (body.daily_trend ?? { ...EMPTY_DAILY_TREND }) as EffluentPlantReportDailyTrend,
    by_unit: (body.by_unit ?? []) as EffluentPlantReportUnitRow[],
    daily: (body.daily ?? []) as EffluentPlantReportDailyRow[],
    daily_total: (body.daily_total ?? { ...EMPTY_DAILY_TOTAL }) as EffluentPlantReportDailyTotal,
    total: (body.total ?? { ...EMPTY_TOTALS }) as EffluentPlantReportTotals,
  }
}

/**
 * GET /api/effluent-plant-reports/export?period_id=...&production_line_id=...&format=csv
 * — respons streaming text/csv, BUKAN JSON, jadi responseType-nya blob.
 *
 * Isinya (SATU BARIS PER SLOT WAKTU, dengan konteks Periode/Mill/Production
 * Line, tanggal, unit Effluent Plant, status, dan catatan diulang verbatim di
 * setiap baris) dibentuk SERVER dan sama persis dengan ekspor laporan versi
 * web, karena endpoint-nya memang sama. Slot yang seluruh kolom ukurnya
 * kosong TETAP satu baris, dengan sel kosong — membuangnya akan membuat
 * berkasnya berselisih dengan angka cakupan yang diterbitkan laporan yang
 * sama. Kesembilan belas kolom bacaan ikut, ketiga kolom status dan kedua
 * kolom teks termasuk.
 *
 * Format 'csv' SAJA, dan tidak ada jalur kode yang dapat menyusun format
 * lain: ekspor .xlsx hanya ada di layar web — bukan karena endpoint-nya tidak
 * mendukungnya (ia mendukung), melainkan karena menyimpan dan membuka berkas
 * itu di WebView ponsel menuntut penanganan berkas native yang belum ada di
 * aplikasi ini.
 */
export async function exportCsv(
  periodId: string,
  scope?: EffluentPlantReportScope,
): Promise<Blob> {
  const response = await apiClient.get('/api/effluent-plant-reports/export', {
    params: {
      period_id: periodId,
      format: 'csv',
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

export const effluentPlantReportRepo = {
  fetchPeriods,
  fetchSummary,
  exportCsv,
  saveCsvFile,
}

export default effluentPlantReportRepo
