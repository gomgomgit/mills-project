import apiClient from '@/services/apiClient'

/**
 * depricarpingReportRepo — screen-153--laporan-depricarping-mobile /
 * usecase-156--laporan-depricarping-mobile "Lihat Laporan Periode
 * Depricarping (Mobile)".
 *
 * Kembaran kesembilan dari pola yang sama: sterilizerReportRepo.ts
 * (screen-135), cagesTrackReportRepo.ts (screen-136), boilerRoomReportRepo.ts
 * (screen-137), clarificationReportRepo.ts (screen-138),
 * storageTankReportRepo.ts (screen-139), weighbridgeReportRepo.ts
 * (screen-144), gradingReportRepo.ts (screen-147), threshingReportRepo.ts
 * (screen-149), dan pressingReportRepo.ts (screen-151). Satu berkas repo
 * tipis di atas apiClient, tanpa cache lokal. Layar laporan ini memang
 * MEMBUTUHKAN jaringan, jadi tidak ada jalur SQLite offline di sini sama
 * sekali — kegagalan jaringan adalah kondisi yang ditampilkan ke pengguna,
 * bukan yang disembunyikan di balik data basi.
 *
 * NOL ENDPOINT BARU DAN NOL PERUBAHAN BACKEND. Keempat endpoint
 * /api/depricarping-reports/* sudah menerima peran mobile sejak
 * screen-152--laporan-depricarping-web dibangun — kedua layar direncanakan
 * dalam satu seri, pola yang sudah terbukti pada pasangan 146/147, 148/149,
 * dan 150/151. Pasangan Weighbridge (143 lalu 144) harus menambal tiga
 * perubahan akses menyusul justru karena layar web-nya dibangun sebelum
 * pasangan mobile-nya diketahui.
 *
 * Yang tetap berlaku, dan sudah dikunci test di sisi web: Operator berada di
 * cabang TERIKAT MILL pada DepricarpingReportService::resolveBusinessUnit()
 * sejak baris pertama, dan /business-units/options tetap menolaknya dengan
 * 403 — peran yang terikat satu mill tidak punya pemilih, dan menyerahkan
 * daftar seluruh mill kepadanya adalah justru kebocoran yang dihindari.
 *
 * NOL PERHITUNGAN ULANG DI KLIEN. Berkas ini tidak pernah menjumlah,
 * merata-ratakan, membulatkan, mengurutkan, menyaring, atau menurunkan satu
 * angka pun. Seluruh nilai diteruskan apa adanya dari respons server, karena
 * laporan web memakai sumber yang sama (DepricarpingReportService) —
 * perhitungan kedua di sisi klien pasti akan menyimpang dari laporan web
 * tanpa ketahuan. Fixture test berkas ini sengaja dibuat BERTENTANGAN dengan
 * sumbernya (avg di luar rentang min..max-nya sendiri, coverage_percent yang
 * bukan filled/total, downtime.avg yang bukan total/recorded) justru supaya
 * repo yang menghitung sendiri PASTI gagal.
 *
 * ────────────────────────────────────────────────────────────────────────
 * EMPAT HAL YANG MEMBEDAKAN LAPORAN INI DARI KEDELAPAN SEBELUMNYA
 * ────────────────────────────────────────────────────────────────────────
 * Yang diringkas adalah KONDISI OPERASI: satu record adalah satu hari kerja
 * satu presser, satu baris adalah satu slot waktu dengan TUJUH kolom ukur —
 * dua lebih banyak daripada Threshing dan Pressing.
 *
 * 1. SETIAP ENTRI `metrics` MENGANGKUT EMPAT KOLOM TARGET, dan nama kolomnya
 *    TIDAK SATU PUN sama dengan kedua master saudaranya:
 *      - Threshing:    parameter / standard_operational_target /
 *                      action_plan_on_deviation
 *      - Pressing:     parameter_metric / target_operating_range /
 *                      critical_trigger_action_limit
 *      - Depricarping: parameter_metric / target_range / critical_limit /
 *                      operational_consequence_justification
 *    Menyalin nama dari salah satu saudaranya menghasilkan blok target yang
 *    SELURUHNYA null tanpa satu pun galat TypeScript. Ada test yang
 *    mengasersi keempat nama ini secara eksplisit karena itu.
 *
 *    KOLOM KEEMPAT ITU BARU dan menjawab "apa yang dipertaruhkan" ('Direct
 *    operational revenue loss'). Tanpa itu tiga parameter yang sama-sama
 *    melewati batasnya tampak sama pentingnya — padahal tidak. Ia juga teks
 *    terpanjang dari ketiga kolom target, jadi ia yang paling berisiko
 *    dibuang demi ruang pada kartu 390px.
 *
 *    DAN TIDAK ADA PERBANDINGAN TERHADAP SATU PUN, DI MANA PUN. Di sini
 *    sebabnya harus paling tajam dari seluruh laporan yang ada, karena
 *    `critical_limit` master Depricarping-lah yang PALING rapi bentuknya:
 *    lima dari enam membawa pembanding numerik eksplisit, sebagian dua sisi
 *    sekaligus ('< 35 or > 55 mmH2O', '< 55C or > 75C'). Yang menahannya:
 *    kolomnya teks bebas dan TIDAK ADA APA PUN PADA SKEMA yang membatasi
 *    bentuknya — nilainya ditetapkan lewat seeder, dan satu seeder yang
 *    dijalankan atau satu suntingan langsung ke basis data dapat
 *    memperkenalkan bentuk baru tanpa satu pun test menangkapnya; pengurai
 *    yang lalu gagal akan BERHENTI MEMPERINGATKAN tanpa galat, dan peringatan
 *    yang hilang tidak dapat dibedakan dari 'semuanya aman'. Ditambah: bentuk
 *    dua sisi menuntut pengurai berbeda dari bentuk satu sisi dan satuannya
 *    ikut di dalam teks (delapan macam, dengan satu parameter menuliskan
 *    satuannya sendiri dua cara); satu nilai pada master Pressing SUDAH
 *    ambigu hari ini ('< 10% to 12%'); dan warna membawa makna melampaui
 *    statistik — angka merah pada laporan periode terbaca sebagai
 *    pelanggaran yang tidak pernah ditetapkan siapa pun. Repo ini tidak boleh
 *    membuat kunci penilaian semacam itu, dan ada test yang mengunci
 *    ketiadaannya.
 *
 * 2. `targets_without_metric[].reason` MENYATAKAN MENGAPA sebuah standar
 *    belum terukur, dan sejak 2026-10-06 daftarnya normalnya KOSONG.
 *
 *    Yang dulu ada di sana layak diingat: sampai tanggal itu kolom ketujuh
 *    bernama `kernel_recovery_in_fibre_percent` dan keempat layar
 *    input/detail Depricarping melabelinya PEROLEHAN, sementara standar
 *    masternya sendiri 'Kernel Loss in Fibre' dengan target '< 0.50%' —
 *    sebuah KEHILANGAN, makin kecil makin baik. Kedua pembacaan menuntut
 *    skala angka yang berbeda sekitar DUA RATUS kali (0-2% untuk kehilangan,
 *    90-100% untuk perolehan), jadi memasangkannya akan membuat laporan
 *    menilai dengan arah TERBALIK pada angka yang tetap terlihat masuk akal.
 *    Server menerbitkan ketimpangannya alih-alih menebak; user memutuskan
 *    memakai penamaan master, dan kolomnya di-rename (migrasi
 *    2026_10_06_000001). Nilai `reason` 'direction_unresolved' ikut dihapus
 *    karena tidak ada kolom yang bisa berada dalam keadaan itu lagi.
 *
 *    Yang tersisa: 'no_column' — sebuah parameter master yang namanya
 *    disunting, atau parameter baru yang belum punya kolom ukur, keduanya
 *    mendarat di sana. Layar TETAP menggambar bagiannya walau kosong, karena
 *    di situlah suntingan semacam itu akan terlihat.
 *
 * 3. `target.shares_standard_with` MENYATAKAN BAHWA SATU STANDAR MENGATUR DUA
 *    KOLOM. Master hanya memuat satu baris 'Nut Silo Temperature' sementara
 *    tabel detail punya nut_silo_1_temp_c dan nut_silo_2_temp_c. Keduanya
 *    tetap DUA metrik dengan penyebut masing-masing — dua silo fisik, dan
 *    merata-ratakannya akan menyembunyikan silo yang menyimpang di belakang
 *    silo yang normal. Hubungannya diteruskan karena layar HARUS
 *    menyatakannya: di layar sempit kedua kartu bertumpuk LANGSUNG berurutan,
 *    sehingga standar identik yang muncul dua kali beruntun terbaca sebagai
 *    data terduplikasi lebih kuat lagi daripada di tabel web.
 *
 * 4. `downtime` ADALAH OBJEK BERISI ANGKA, bukan daftar alasan teks. Ini yang
 *    pertama di seluruh laporan stasiun: depricarping_details.downtime_minutes
 *    adalah kolom INTEGER, sementara Threshing dan Pressing hanya punya
 *    downtime_reason berupa teks sehingga "berapa lama stasiun berhenti" tidak
 *    pernah dapat dijawab. Menyalin tipe DowntimeRow dari salah satu repo
 *    saudaranya ke sini menghasilkan undefined di mana-mana.
 *
 *    `total_minutes` null — BUKAN 0 — ketika tidak satu pun slot mencatat,
 *    karena total 0 menit terbaca seperti "stasiun tidak pernah berhenti".
 *    `recorded_slot_count` adalah penyebut rata-ratanya, dan ia mencakup
 *    nilai 0 yang benar-benar tercatat (pernyataan bahwa stasiun tidak
 *    berhenti). Teks bebasnya ada pada `findings` — kolom TERPISAH, dan
 *    bagian terpisah di layar, karena satu menjawab "berapa lama" dan satu
 *    "apa yang terlihat".
 *
 * SETIAP METRIK MEMBAWA PENYEBUTNYA SENDIRI. `metrics[].filled_slot_count`
 * adalah banyaknya slot yang BENAR-BENAR MENCATAT kolom itu — penyebut
 * min/avg/max-nya. Ketujuh kolom nullable dan terisi saling bebas, jadi satu
 * penyebut bersama akan salah untuk setidaknya enam di antaranya.
 *
 * SATU KEJANGGALAN YANG BENAR: coverage.filled_slots BISA LEBIH BESAR
 * daripada penyebut kolom ukur mana pun. Sebuah slot dihitung terisi bila
 * salah satu dari SEMBILAN kolom bacaan terisi, dan dua di antaranya
 * (downtime_minutes, findings) bukan kolom ukur — slot yang hanya memuat
 * "Belt kendur" jelas disentuh operator, jadi melaporkannya sebagai slot
 * kosong akan salah. Dicatat di sini supaya pembaca berikutnya tidak
 * "memperbaiki" selisih yang memang benar.
 *
 * NULL BUKAN NOL. min, avg, max tiap metrik, coverage_percent,
 * downtime.total_minutes, downtime_minutes per baris rekap, serta seluruh
 * nilai di dalam `averages` boleh null. Mengoersinya menjadi 0 (atau '' / '-')
 * di sini akan mengubah "tidak ada yang diukur" menjadi "hasilnya nol" — dua
 * fakta yang berbeda, dan yang satu menyesatkan. Penerjemahan null menjadi
 * teks terbaca adalah urusan view, bukan repo.
 *
 * TEMUAN DIKELOMPOKKAN SERVER, SECARA HARFIAH: tanpa penyeragaman ejaan dan
 * tanpa penyeragaman huruf besar-kecil. Repo meneruskan daftarnya apa adanya,
 * dalam urutan yang sama, dan TIDAK menormalkan apa pun — normalisasi di
 * klien akan menggabungkan hal yang penulisnya memang maksudkan berbeda, dan
 * sekaligus membuat layar ini berselisih dengan laporan web yang memakai
 * sumber yang sama.
 *
 * GALAT DITERUSKAN APA ADANYA. Tidak ada try/catch dan tidak ada kelas galat
 * khusus di sini. Interceptor apiClient menolak lewat normalizeError yang
 * mengembalikan objek DATAR { message, errors?, status? } dan MEMBUANG
 * `response` — jadi `.response` tidak pernah ada pada galat yang sampai ke
 * pemanggil. 401 diterjemahkan menjadi "arahkan ke Login" oleh
 * LaporanDepricarpingView (membaca `error.status`), dan kegagalan transport
 * (penolakan tanpa `status`) menjadi pesan + tombol Coba Lagi di sana pula.
 *
 * GAGAL TERTUTUP PADA business_unit_id: parameter ini hanya dikirim bila
 * pemanggil menyatakan dirinya Admin (`isAdmin: true`). Bagi Operator,
 * Supervisor, dan Mill Management mill diambil dari akun di sisi server,
 * sehingga business_unit_id yang dipaksakan pemanggil TIDAK PERNAH ikut
 * terkirim — dijaga di sini, bukan hanya di view.
 */

export interface DepricarpingReportBusinessUnitOption {
  id: string
  name: string
}

export interface DepricarpingReportPeriodOption {
  id: string
  name: string
  start_date: string
  end_date: string
  /**
   * Status STASIUN LAYAR INI di dalam periode itu (baris period_stations),
   * BUKAN status periode: periode tidak punya status sendiri karena stasiun
   * tidak ditutup serentak.
   */
  status: string
  /** Selalu terisi 'depricarping' — TIDAK pernah null. */
  station_type: string
  station_type_label: string
}

export interface DepricarpingReportBusinessUnitRef {
  id: string
  name: string
}

export interface DepricarpingReportProductionLineRef {
  id: string
  name: string
}

export interface DepricarpingReportPeriodHeader {
  id: string
  name: string
  start_date: string
  end_date: string
  status: string
}

/**
 * Cakupan pencatatan — dibaca PALING ATAS di layar, bukan sebagai catatan
 * kaki: periode yang terisi seperlima pun menghasilkan rata-rata yang terlihat
 * rapi.
 *
 * Ketiga angka pembentuk penyebut ikut dikirim (presser_count,
 * days_counted, slots_per_presser_per_day) supaya layar dapat menampilkan
 * dari mana expected_slots berasal, alih-alih sebuah persen tanpa asal.
 */
export interface DepricarpingReportCoverage {
  filled_slots: number
  expected_slots: number
  /**
   * null ketika expected_slots nol (periode belum mulai). BUKAN 0 — 0%
   * mengklaim ada yang diukur dan hasilnya nol.
   */
  coverage_percent: number | null
  /** Presser yang BENAR-BENAR beroperasi, bukan jumlah stasiun terdaftar. */
  presser_count: number
  /** 24, dari grid slot kanonis layar input itu sendiri. */
  slots_per_presser_per_day: number
  days_in_period: number
  /** Berhenti di hari ini untuk periode yang masih berjalan. */
  days_counted: number
  period_running: boolean
}

/**
 * KEEMPAT kolom target satu parameter, dan keempatnya diteruskan karena
 * masing-masing menjawab pertanyaan yang BERBEDA: rentang target adalah "ke
 * mana seharusnya", batas kritis adalah "kapan sudah terlalu jauh", dan
 * akibat operasional adalah "apa yang dipertaruhkan". Yang terakhir TIDAK ADA
 * pada master Threshing maupun Pressing, dan tanpa itu tiga parameter yang
 * sama-sama melewati batasnya tampak sama pentingnya — padahal tidak.
 *
 * PERHATIKAN NAMA MEDANNYA. KETIGA master laporan kondisi TIDAK BERBAGI SATU
 * PUN NAMA KOLOM:
 *   - Threshing:   parameter / standard_operational_target / action_plan_on_deviation
 *   - Pressing:    parameter_metric / target_operating_range / critical_trigger_action_limit
 *   - Depricarping: parameter_metric / target_range / critical_limit /
 *                   operational_consequence_justification
 * Menyalin nama dari salah satu saudaranya ke antarmuka ini menghasilkan blok
 * target yang SELURUHNYA null tanpa satu pun galat TypeScript — karena
 * nama-nama itu memang bukan properti yang ada, dan optional chaining akan
 * menelannya. Layar akan tampil normal dengan setiap standar hilang. Ada test
 * yang mengasersi keempat nama ini secara eksplisit justru karena itu.
 *
 * Medannya null ketika master belum punya baris untuk parameter itu — dan
 * ketiadaan itu diteruskan, bukan ditambal, supaya layar dapat menyatakan
 * "belum terisi" alih-alih menampilkan sel kosong tanpa keterangan.
 */
export interface DepricarpingReportTarget {
  parameter_metric: string | null
  /** Rentang yang DITUJU — '40 - 50 mmH2O', '20 - 24 RPM'. */
  target_range: string | null
  /**
   * Batas KRITIS — '< 35 or > 55 mmH2O', '> 1.00%'. Perhatikan bentuk DUA
   * SISI pada sebagian nilai: ia menuntut pengurai yang berbeda dari bentuk
   * satu sisi, dan satuannya ikut di dalam teks. Salah satu dari tiga alasan
   * laporan ini tidak menandai apa pun secara otomatis.
   */
  critical_limit: string | null
  /**
   * APA YANG DIPERTARUHKAN bila batas dilewati — 'Direct operational revenue
   * loss', 'Low pressure drops fibre early (heavy losses)'. Kolom KEEMPAT,
   * yang tidak ada pada kedua master saudaranya. Teksnya paling panjang dari
   * ketiga kolom target, jadi ia yang paling berisiko dibuang demi ruang di
   * layar sempit — dan yang paling merugikan bila dibuang.
   */
  operational_consequence_justification: string | null
  /**
   * Nama kolom LAIN yang diatur baris master yang SAMA. Kosong untuk hampir
   * semua metrik; berisi satu entri pada KEDUA kolom nut silo, karena master
   * hanya memuat satu baris 'Nut Silo Temperature' untuk nut_silo_1_temp_c
   * dan nut_silo_2_temp_c.
   *
   * Diteruskan karena layar HARUS menyatakannya. Di layar sempit kedua kartu
   * itu bertumpuk LANGSUNG berurutan, sehingga standar yang identik muncul dua
   * kali tepat beruntun — tanpa keterangannya itu terbaca seperti data yang
   * terduplikasi, lebih kuat lagi daripada di tabel web, dan seseorang akan
   * "membersihkannya". Angkanya tetap DIPISAH: dua silo fisik, dan
   * merata-ratakannya akan menyembunyikan silo yang menyimpang di belakang
   * silo yang normal.
   */
  shares_standard_with: string[]
}

/**
 * Satu kolom ukur, dengan PENYEBUTNYA SENDIRI dan standar operasionalnya.
 *
 * Perhatikan apa yang TIDAK ada di sini: tidak ada severity, tidak ada
 * is_out_of_range, tidak ada flag. Lihat catatan nomor 1 pada docblock berkas.
 */
export interface DepricarpingReportMetric {
  /** Nama kolom pada depricarping_details — kunci yang dipakai `averages`. */
  column: string
  label: string
  unit: string
  min: number | null
  avg: number | null
  max: number | null
  /** PENYEBUT min/avg/max baris ini — bukan coverage.filled_slots. */
  filled_slot_count: number
  target: DepricarpingReportTarget
}

/**
 * Standar yang pengukurannya belum dapat dipercaya — BESERTA ALASANNYA.
 *
 * `reason` hanya punya satu nilai sejak 2026-10-06: 'no_column' — tidak ada
 * kolom ukurnya di skema. Nilai kedua, 'direction_unresolved', dihapus
 * bersama rename kolom kernel loss (lihat butir 2 pada docblock berkas);
 * mempertahankannya berarti menyimpan cabang yang tidak dapat dicapai apa
 * pun. Medannya sendiri TETAP diteruskan supaya alasan baru dapat ditambahkan
 * tanpa mengubah bentuk payload.
 */
export interface DepricarpingReportTargetWithoutMetric {
  parameter_metric: string
  target_range: string
  critical_limit: string
  operational_consequence_justification: string
  reason: string
}

export interface DepricarpingReportPresserRow {
  /** Penamaan unit, bukan kunci baris: id yang sama pada dua tanggal = satu unit. */
  presser_id: string
  day_count: number
  filled_slot_count: number
  /**
   * Total menit berhenti presser ini. null — BUKAN 0 — ketika tidak satu pun
   * slotnya mencatat downtime: presser yang tidak dicatat bukan presser yang
   * tidak pernah berhenti.
   */
  downtime_minutes: number | null
  /** Rata-rata per kolom ukur, masing-masing berpenyebut sendiri. null bila kosong. */
  averages: Record<string, number | null>
}

export interface DepricarpingReportDailyRow {
  date: string
  filled_slot_count: number
  /** null, bukan 0, ketika tidak satu pun slot hari itu mencatat downtime. */
  downtime_minutes: number | null
  averages: Record<string, number | null>
}

/**
 * Total periode — DIHITUNG ULANG server atas seluruh slot, bukan rata-rata
 * dari rata-rata harian. Repo meneruskannya apa adanya; menurunkannya dari
 * `daily` di sini akan menghasilkan angka yang berbeda dari laporan web.
 */
export interface DepricarpingReportDailyTotal {
  filled_slot_count: number
  downtime_minutes: number | null
  averages: Record<string, number | null>
}

/**
 * BLOK DOWNTIME SEBAGAI ANGKA — bentuk yang TIDAK ADA pada repo Threshing
 * maupun Pressing, di mana downtime hanya berupa daftar alasan teks dan
 * "berapa lama stasiun berhenti" tidak dapat dijawab sama sekali.
 * depricarping_details.downtime_minutes adalah kolom INTEGER.
 *
 * Menyalin tipe DowntimeRow dari salah satu repo saudaranya ke sini akan
 * menghasilkan undefined di mana-mana: bentuknya objek, bukan daftar.
 */
export interface DepricarpingReportDowntime {
  /**
   * Jumlah menit atas slot yang MENCATATNYA saja. null — BUKAN 0 — ketika
   * tidak satu pun slot mencatat: total 0 menit terbaca seperti "stasiun
   * tidak pernah berhenti", padahal yang benar adalah "tidak ada yang
   * mencatatnya". Layar membedakan keduanya, jadi 0 di sini akan membuatnya
   * berbohong.
   */
  total_minutes: number | null
  /**
   * PENYEBUT rata-rata di bawah — jumlah slot yang downtime_minutes-nya
   * terisi, TERMASUK yang bernilai 0. Nol yang tercatat adalah pernyataan
   * seseorang bahwa stasiun tidak berhenti pada slot itu.
   */
  recorded_slot_count: number
  /** total_minutes / recorded_slot_count. null bila penyebutnya nol. */
  avg_minutes_per_recorded_slot: number | null
  /**
   * Selalu false — downtime_minutes tidak punya baris pada master target.
   * Diteruskan sebagai medan supaya layar dapat MENYATAKAN ketiadaannya,
   * alih-alih menampilkan sel target kosong yang terbaca seperti master yang
   * belum terisi.
   */
  has_standard: boolean
}

/**
 * Satu baris rekap temuan — paruh TEKS BEBAS dari apa yang dua laporan
 * sebelumnya terbitkan sebagai alasan downtime, dan di sini ia kolom yang
 * terpisah (depricarping_details.findings).
 *
 * Kuncinya `finding`, BUKAN `reason` seperti pada repo Threshing/Pressing.
 */
export interface DepricarpingReportFindingRow {
  /** Teks apa adanya. Dua ejaan untuk satu hal = dua baris, dan itu benar. */
  finding: string
  slot_count: number
}

export interface DepricarpingReportTotals {
  record_count: number
  days_with_records: number
  /** Record draft IKUT seluruh angka; jumlahnya hanya dinyatakan. */
  draft_record_count: number
  records_not_checked: number
  records_not_acknowledged: number
}

export interface DepricarpingReportSummary {
  business_unit: DepricarpingReportBusinessUnitRef | null
  production_line: DepricarpingReportProductionLineRef | null
  period: DepricarpingReportPeriodHeader | null
  /** Membedakan "tidak ada yang dilaporkan" dari "angkanya nol". */
  has_data: boolean
  coverage: DepricarpingReportCoverage
  metrics: DepricarpingReportMetric[]
  targets_without_metric: DepricarpingReportTargetWithoutMetric[]
  targets_master_empty: boolean
  /**
   * true bila targets_without_metric kosong. DIBACA TERPISAH dari
   * targets_master_empty, karena keduanya dapat sama-sama menunjuk daftar
   * kosong untuk sebab yang BERLAWANAN: master yang belum terisi versus
   * seluruh standar yang sudah punya pengukuran. Layar memeriksa
   * targets_master_empty lebih dulu.
   */
  all_targets_measured: boolean
  by_presser: DepricarpingReportPresserRow[]
  daily: DepricarpingReportDailyRow[]
  daily_total: DepricarpingReportDailyTotal
  /** Objek, bukan daftar — lihat DepricarpingReportDowntime. */
  downtime: DepricarpingReportDowntime
  /** Bagian TERPISAH dari downtime: satu "berapa lama", satu "apa yang terlihat". */
  findings: DepricarpingReportFindingRow[]
  total: DepricarpingReportTotals
}

/**
 * Parameter opsional pemilih mill dan line. `isAdmin` sengaja WAJIB dinyatakan
 * eksplisit oleh pemanggil yang hendak mengirim business_unit_id — lihat
 * catatan "gagal tertutup" pada docblock berkas.
 */
export interface DepricarpingReportScope {
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
const EMPTY_COVERAGE: DepricarpingReportCoverage = {
  filled_slots: 0,
  expected_slots: 0,
  coverage_percent: null,
  presser_count: 0,
  slots_per_presser_per_day: 0,
  days_in_period: 0,
  days_counted: 0,
  period_running: false,
}

const EMPTY_DAILY_TOTAL: DepricarpingReportDailyTotal = {
  filled_slot_count: 0,
  downtime_minutes: null,
  averages: {},
}

/**
 * Bawaan blok downtime ketika payload tidak memuatnya. total_minutes null,
 * BUKAN 0 — bentuk bawaan yang berbohong lebih buruk daripada bentuk bawaan
 * yang kosong.
 */
const EMPTY_DOWNTIME: DepricarpingReportDowntime = {
  total_minutes: null,
  recorded_slot_count: 0,
  avg_minutes_per_recorded_slot: null,
  has_standard: false,
}

const EMPTY_TOTALS: DepricarpingReportTotals = {
  record_count: 0,
  days_with_records: 0,
  draft_record_count: 0,
  records_not_checked: 0,
  records_not_acknowledged: 0,
}

/**
 * business_unit_id HANYA dikirim untuk Admin. Untuk peran lain fungsi ini
 * mengembalikan objek kosong, apa pun yang dikirimkan pemanggil — kuncinya
 * adalah medan itu ABSEN, bukan null dan bukan string kosong. Operator ada di
 * cabang terikat-mill ini, sama seperti Supervisor dan Mill Management.
 */
function scopeParams(scope?: DepricarpingReportScope): Record<string, string> {
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
function productionLineParams(scope?: DepricarpingReportScope): Record<string, string> {
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
 * GET /api/depricarping-reports/business-units/options — pemilih Mill, ADMIN
 * SAJA. Server menjawab 403 untuk peran lain, TERMASUK Operator: ini
 * satu-satunya rute pada prefix ini yang tidak terbuka baginya, dan itu
 * disengaja. View TIDAK BOLEH memanggilnya untuk peran yang terikat mill —
 * selain percuma, permintaan itu akan membentuk daftar seluruh mill yang
 * memang tidak berhak dilihat.
 */
export async function fetchBusinessUnits(): Promise<DepricarpingReportBusinessUnitOption[]> {
  const response = await apiClient.get('/api/depricarping-reports/business-units/options')

  return (response.data?.data ?? []) as DepricarpingReportBusinessUnitOption[]
}

/**
 * GET /api/depricarping-reports/periods — periode yang mencakup stasiun
 * Depricarping, yakni periode yang punya baris period_stations berjenis
 * 'depricarping'. Penyaringannya dikerjakan SERVER; repo meneruskan daftar apa
 * adanya, dalam urutan yang sama — menyaringnya kedua kali di sini akan
 * menciptakan definisi cakupan yang kedua. Daftar kosong adalah jawaban yang
 * sah (HTTP 200 + []), bukan galat.
 *
 * Periode TERTUTUP tetap terdaftar: status mengatur penulisan data, bukan
 * pembacaan laporan.
 */
export async function fetchPeriods(
  scope?: DepricarpingReportScope,
): Promise<DepricarpingReportPeriodOption[]> {
  const params = scopeParams(scope)

  const response = await apiClient.get('/api/depricarping-reports/periods', { params })

  return (response.data?.data ?? []) as DepricarpingReportPeriodOption[]
}

/**
 * GET /api/depricarping-reports/summary — seluruh angka layar untuk satu periode
 * pada satu Production Line.
 *
 * Setiap blok diteruskan APA ADANYA, tanpa satu operasi aritmetika pun: tidak
 * ada penurunan coverage_percent dari filled_slots/expected_slots, tidak ada
 * perata-rataan ulang min/avg/max, tidak ada penurunan daily_total dari
 * `daily`, tidak ada pengurutan ulang metrics / by_presser / daily /
 * findings, dan TIDAK ADA perbandingan satu nilai pun terhadap
 * `target`. Nilai bawaan di atas hanya berlaku bila BLOK-nya tidak ada sama
 * sekali pada respons.
 */
export async function fetchSummary(
  periodId: string,
  scope?: DepricarpingReportScope,
): Promise<DepricarpingReportSummary> {
  const response = await apiClient.get('/api/depricarping-reports/summary', {
    params: { period_id: periodId, ...scopeParams(scope), ...productionLineParams(scope) },
  })

  const body = (unwrap<Record<string, unknown>>(response.data) ?? {}) as Record<string, unknown>

  return {
    business_unit: (body.business_unit ?? null) as DepricarpingReportBusinessUnitRef | null,
    production_line: (body.production_line ?? null) as DepricarpingReportProductionLineRef | null,
    period: (body.period ?? null) as DepricarpingReportPeriodHeader | null,
    has_data: (body.has_data ?? false) as boolean,
    coverage: (body.coverage ?? { ...EMPTY_COVERAGE }) as DepricarpingReportCoverage,
    metrics: (body.metrics ?? []) as DepricarpingReportMetric[],
    targets_without_metric: (body.targets_without_metric ?? []) as DepricarpingReportTargetWithoutMetric[],
    targets_master_empty: (body.targets_master_empty ?? false) as boolean,
    all_targets_measured: (body.all_targets_measured ?? false) as boolean,
    by_presser: (body.by_presser ?? []) as DepricarpingReportPresserRow[],
    daily: (body.daily ?? []) as DepricarpingReportDailyRow[],
    daily_total: (body.daily_total ?? { ...EMPTY_DAILY_TOTAL }) as DepricarpingReportDailyTotal,
    downtime: (body.downtime ?? { ...EMPTY_DOWNTIME }) as DepricarpingReportDowntime,
    findings: (body.findings ?? []) as DepricarpingReportFindingRow[],
    total: (body.total ?? { ...EMPTY_TOTALS }) as DepricarpingReportTotals,
  }
}

/**
 * GET /api/depricarping-reports/export?period_id=...&production_line_id=...&format=csv
 * — respons streaming text/csv, BUKAN JSON, jadi responseType-nya blob.
 *
 * Isinya (SATU BARIS PER SLOT WAKTU, dengan konteks Periode/Mill/Production
 * Line, tanggal, presser, status, dan catatan diulang verbatim di setiap
 * baris) dibentuk SERVER dan sama persis dengan ekspor laporan versi web,
 * karena endpoint-nya memang sama. Slot yang seluruh kolom ukurnya kosong
 * TETAP satu baris, dengan sel kosong.
 *
 * Format 'csv' saja: ekspor .xlsx hanya ada di layar web.
 */
export async function exportCsv(periodId: string, scope?: DepricarpingReportScope): Promise<Blob> {
  const response = await apiClient.get('/api/depricarping-reports/export', {
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

export const depricarpingReportRepo = {
  fetchBusinessUnits,
  fetchPeriods,
  fetchSummary,
  exportCsv,
  saveCsvFile,
}

export default depricarpingReportRepo
