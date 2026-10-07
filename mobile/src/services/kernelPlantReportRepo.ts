import apiClient from '@/services/apiClient'

/**
 * kernelPlantReportRepo — screen-155--laporan-kernel-plant-mobile /
 * usecase-161--laporan-kernel-plant-mobile "Lihat Laporan Periode Kernel
 * Plant (Mobile)".
 *
 * Kembaran KESEBELAS dari pola yang sama: sterilizerReportRepo.ts
 * (screen-135), cagesTrackReportRepo.ts (screen-136), boilerRoomReportRepo.ts
 * (screen-137), clarificationReportRepo.ts (screen-138),
 * storageTankReportRepo.ts (screen-139), weighbridgeReportRepo.ts
 * (screen-144), gradingReportRepo.ts (screen-147), threshingReportRepo.ts
 * (screen-149), pressingReportRepo.ts (screen-151), dan
 * depricarpingReportRepo.ts (screen-153). Satu berkas repo tipis di atas
 * apiClient, tanpa cache lokal. Layar laporan ini memang MEMBUTUHKAN
 * jaringan, jadi tidak ada jalur SQLite offline di sini sama sekali —
 * kegagalan jaringan adalah kondisi yang ditampilkan ke pengguna, bukan yang
 * disembunyikan di balik data basi.
 *
 * NOL ENDPOINT BARU DAN NOL PERUBAHAN BACKEND. Rute /api/kernel-plant-reports/*
 * sudah menerima peran mobile (termasuk Operator) sejak
 * screen-154--laporan-kernel-plant-web dibangun — kedua layar direncanakan
 * dalam satu seri, pola yang sudah terbukti pada pasangan 146/147, 148/149,
 * 150/151, dan 152/153. Pasangan Weighbridge (143 lalu 144) harus menambal
 * tiga perubahan akses menyusul justru karena layar web-nya dibangun sebelum
 * pasangan mobile-nya diketahui.
 *
 * TIDAK ADA business_unit_id DI BERKAS INI, SAMA SEKALI — dan itu bukan
 * kelalaian. Kedua aktor layar ini (Operator dan Supervisor) TERIKAT SATU
 * MILL: server menyelesaikan mill dari akun di
 * KernelPlantReportService::resolveBusinessUnit(), sehingga parameter itu
 * tidak punya pekerjaan apa pun di sini. Repo kesepuluh sebelumnya masih
 * membawa cabang Admin (scopeParams + fetchBusinessUnits) karena layar
 * laporan mobile pertama diport dari layar yang punya pemilih mill; di sini
 * cabang itu TIDAK ada, jadi mustahil ada jalur yang mengirimkannya —
 * gagal-tertutup secara KONSTRUKSI, bukan secara penjagaan. Konsekuensinya
 * disengaja pula: /api/kernel-plant-reports/business-units/options tidak
 * dipanggil dari mana pun, karena rute itu memang Admin saja (403 untuk
 * Operator/Supervisor/Mill Management, diangkat service bukan middleware) dan
 * menyerahkan daftar seluruh mill kepada peran terikat-mill adalah justru
 * kebocoran yang dihindari. Admin dan Mill Management memakai layar WEB
 * screen-154, yang satu-satunya punya pemilih mill.
 *
 * NOL PERHITUNGAN ULANG DI KLIEN. Berkas ini tidak pernah menjumlah,
 * merata-ratakan, membulatkan, mengurutkan, menyaring, atau menurunkan satu
 * angka pun. Seluruh nilai diteruskan apa adanya dari respons server, karena
 * laporan web memakai sumber yang sama (KernelPlantReportService) —
 * perhitungan kedua di sisi klien pasti akan menyimpang dari laporan web
 * tanpa ketahuan, dan ponsel yang berselisih dengan layar web tentang angka
 * yang sama adalah bentuk kegagalan yang paling sulit dibantah. Ia sekaligus
 * memindahkan seluruh perbedaan SQLite-versus-PostgreSQL ke satu tempat yang
 * sudah diuji: agregasi Kernel Plant seluruhnya dikerjakan di PHP, bukan di
 * SQL, justru karena perilaku agregat atas kolom nullable berbeda di antara
 * keduanya.
 *
 * ────────────────────────────────────────────────────────────────────────
 * EMPAT HAL YANG MEMBEDAKAN LAPORAN INI DARI KESEPULUH SEBELUMNYA
 * ────────────────────────────────────────────────────────────────────────
 * Yang diringkas adalah KONDISI OPERASI: satu record adalah satu hari kerja
 * satu unit kernel plant, satu baris adalah satu slot waktu dengan TUJUH
 * kolom ukur — sama banyak dengan Depricarping, dua lebih banyak daripada
 * Threshing dan Pressing.
 *
 * 1. `target` HANYA MEMBAWA TIGA MEDAN ISI, bukan empat seperti Depricarping:
 *    equipment_parameter / target_benchmark / corrective_action_plan. TIDAK
 *    ADA `target_range`, TIDAK ADA `critical_limit`, dan TIDAK ADA
 *    `operational_consequence_justification` — kolomnya memang tidak ada pada
 *    kernel_plant_operational_targets, dan menerbitkannya sebagai medan yang
 *    SELAMANYA null hanya akan menyerahkan dua sel yang tidak mungkin diisi
 *    siapa pun ke layar.
 *
 *    DAN TIDAK SATU PUN NAMANYA SAMA dengan ketiga master saudaranya:
 *      - Threshing:    parameter / standard_operational_target /
 *                      action_plan_on_deviation
 *      - Pressing:     parameter_metric / target_operating_range /
 *                      critical_trigger_action_limit
 *      - Depricarping: parameter_metric / target_range / critical_limit /
 *                      operational_consequence_justification
 *      - Kernel Plant: equipment_parameter / target_benchmark /
 *                      corrective_action_plan
 *    Menyalin nama dari salah satu saudaranya menghasilkan blok target yang
 *    SELURUHNYA null tanpa satu pun galat TypeScript — nama-nama itu memang
 *    bukan properti yang ada, dan optional chaining akan menelannya. Layar
 *    akan tampil normal dengan setiap standar hilang.
 *
 *    DAN TIDAK ADA PERBANDINGAN TERHADAP SATU PUN, DI MANA PUN. Di sini
 *    sebabnya paling mudah dibantah dari seluruh laporan yang ada: master
 *    Kernel Plant tidak punya kolom batas kritis sama sekali, jadi satu-
 *    satunya angka yang tersedia duduk DI DALAM `target_benchmark` — dan
 *    keenam barisnya membawa sesuatu yang lain di dalam string yang sama:
 *    keterangan dalam tanda kurung tentang kuantitas yang BERBEDA ('20 - 25
 *    Amps (Nut Breakage >95%)' — dua angka, dua satuan, dan arah pembanding
 *    yang berlawanan), satuan yang ditulis sebagai frasa di depan ('Specific
 *    Gravity 1.18 - 1.24'), penyebutan ZONA yang tidak punya kolom sama
 *    sekali ('70°C - 80°C (Top/Middle zones)'), atau alasan keberadaan
 *    batasnya ('≤ 7.0% (Prevents mold growth)'). Tidak ada apa pun pada skema
 *    yang membatasi bentuknya, nilainya ditetapkan lewat seeder, dan satu
 *    seeder yang dijalankan atau satu suntingan langsung ke basis data dapat
 *    memperkenalkan bentuk baru tanpa satu pun test menangkapnya; pengurai
 *    yang lalu gagal akan BERHENTI MEMPERINGATKAN tanpa galat, dan peringatan
 *    yang hilang tidak dapat dibedakan dari 'semuanya aman' — arah kegagalan
 *    terburuk untuk indikator mutu. Ditambah: warna membawa makna melampaui
 *    statistik, sehingga angka merah pada laporan periode terbaca sebagai
 *    pelanggaran yang tidak pernah ditetapkan siapa pun. Repo ini tidak boleh
 *    membuat kunci penilaian semacam itu.
 *
 * 2. BARIS UNITNYA ADALAH KERNEL PLANT, BUKAN PRESSER: `by_kernel_plant[]`
 *    dengan kernel_plant_id dan kernel_plant_name. KEDUANYA `string` biasa —
 *    BUKAN uuid dan BUKAN kunci asing: tidak ada tabel master kernel_plants,
 *    kernel_plant_id adalah kolom teks yang DIKETIK di layar input, dan
 *    kernel_plant_name bernilai sama dengan kernel_plant_id (server
 *    menurunkannya dari id yang sama justru supaya bentuk payload tidak
 *    berubah bila kelak ada masternya). Menamainya presser_id, atau
 *    memperlakukannya sebagai id yang dapat ditelusuri ke sebuah baris,
 *    keduanya salah.
 *
 * 3. DUA PASANGAN BERBAGI STANDAR, BUKAN SATU. `target.shares_standard_with`
 *    menyatakan bahwa satu baris master mengatur dua kolom ukur, dan di
 *    stasiun ini itu terjadi DUA KALI: 'Ripple Mill (Cracker)' mengatur
 *    ripple_mill_1_amps dan ripple_mill_2_amps, 'Kernel Silo 1 & 2' mengatur
 *    kernel_silo_1_temp_c dan kernel_silo_2_temp_c. Depricarping hanya punya
 *    SATU pasangan seperti ini (nut silo), jadi kode yang mengistimewakan
 *    satu pasangan lolos di sana dan SALAH di sini. Keempat kolom tetap EMPAT
 *    metrik dengan penyebut masing-masing — empat mesin fisik, dan
 *    merata-ratakan satu pasangan akan menyembunyikan ketidakseimbangan beban
 *    yang justru menjadi alasan parameter itu diukur. Hubungannya diteruskan
 *    karena layar HARUS menyatakannya, dan harus MENURUNKANNYA dari medan ini
 *    alih-alih memaku keanggotaan pasangan: ripple mill atau silo ketiga
 *    tetap ditandai benar tanpa menyentuh satu berkas klien pun.
 *
 * 4. `targets_without_metric` NORMALNYA BERISI TEPAT SATU BARIS, dan itulah
 *    keadaan tenangnya — berlawanan dengan Depricarping, yang daftarnya
 *    normalnya kosong. Penghuninya 'Final Kernel Dirt' dengan alasan
 *    'no_column': tidak ada kolom kadar kotoran DI MANA PUN pada skema ini —
 *    bukan sekadar tidak ada di kernel_plant_details — sehingga tidak ada
 *    pemetaan yang dapat menutup selisihnya. Diterbitkan alih-alih dibuang,
 *    karena standar yang tidak pernah diukur terbaca seperti TERPENUHI
 *    padahal ia sekadar tidak ada; dan yang satu ini menyebut premi mutu yang
 *    menjadi dasar mill dibayar. Baris di sini juga muncul bila nama
 *    parameter pada master disunting sehingga tak lagi cocok dengan peta
 *    kolom — dan di situlah suntingan semacam itu menjadi terlihat.
 *
 * `downtime` ADALAH OBJEK BERISI ANGKA, bukan daftar alasan teks:
 * kernel_plant_details.downtime_minutes adalah kolom INTEGER, seperti
 * Depricarping dan tidak seperti Threshing/Pressing yang hanya punya
 * downtime_reason berupa teks sehingga "berapa lama stasiun berhenti" tidak
 * pernah dapat dijawab. Menyalin tipe DowntimeRow dari repo Threshing/
 * Pressing ke sini menghasilkan undefined di mana-mana.
 *
 * SETIAP METRIK MEMBAWA PENYEBUTNYA SENDIRI. `metrics[].filled_slot_count`
 * adalah banyaknya slot yang BENAR-BENAR MENCATAT kolom itu — penyebut
 * min/avg/max-nya. Ketujuh kolom nullable dan terisi saling bebas, jadi satu
 * penyebut bersama akan salah untuk setidaknya enam di antaranya.
 *
 * SATU KEJANGGALAN YANG BENAR: coverage.filled_slots BISA LEBIH BESAR
 * daripada penyebut kolom ukur mana pun. Sebuah slot dihitung terisi bila
 * salah satu dari SEMBILAN kolom bacaan terisi (definisinya DIPINJAM dari
 * KernelPlantRecordService::isRowFilled(), bukan diturunkan ulang), dan dua
 * di antaranya (downtime_minutes, findings) bukan kolom ukur — slot yang
 * hanya memuat "Ripple mill bergetar" jelas disentuh operator, jadi
 * melaporkannya sebagai slot kosong akan salah. Yang dilampaui adalah N
 * (penyebut satu kartu), BUKAN M. Dicatat di sini supaya pembaca berikutnya
 * tidak "memperbaiki" selisih yang memang benar.
 *
 * NULL BUKAN NOL. min, avg, max tiap metrik, coverage_percent,
 * downtime.total_minutes, downtime_minutes per baris rekap, serta seluruh
 * nilai di dalam `averages` boleh null. Mengoersinya menjadi 0 (atau '' / '-')
 * di sini akan mengubah "tidak ada yang diukur" menjadi "hasilnya nol" — dua
 * fakta yang berbeda, dan yang satu menyesatkan. Penerjemahan null menjadi
 * teks terbaca adalah urusan view, bukan repo.
 *
 * TEMUAN DIKELOMPOKKAN SERVER, SECARA HARFIAH: tanpa penyeragaman ejaan,
 * huruf besar-kecil, maupun spasi. Repo meneruskan daftarnya apa adanya,
 * dalam urutan yang sama (jumlah terbanyak lebih dulu, lalu teksnya menaik
 * sebagai pemutus seri), dan TIDAK menormalkan apa pun — normalisasi di
 * klien akan menggabungkan hal yang penulisnya memang maksudkan berbeda, dan
 * sekaligus membuat layar ini berselisih dengan laporan web yang memakai
 * sumber yang sama.
 *
 * GALAT DITERUSKAN APA ADANYA. Tidak ada try/catch dan tidak ada kelas galat
 * khusus di sini. Interceptor apiClient menolak lewat normalizeError yang
 * mengembalikan objek DATAR { message, errors?, status? } dan MEMBUANG
 * `response` — jadi `.response` tidak pernah ada pada galat yang sampai ke
 * pemanggil. 401 diterjemahkan menjadi "arahkan ke Login" oleh
 * LaporanKernelPlantView (membaca `error.status`), dan kegagalan transport
 * (penolakan tanpa `status`) menjadi pesan + tombol Coba Lagi di sana pula.
 */

export interface KernelPlantReportPeriodOption {
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
  /** Selalu terisi 'kernel-plant' — DENGAN TANDA HUBUNG, dan tidak pernah null. */
  station_type: string
  station_type_label: string
}

export interface KernelPlantReportBusinessUnitRef {
  id: string
  name: string
}

export interface KernelPlantReportProductionLineRef {
  id: string
  name: string
}

/**
 * Kepala periode pada respons /summary.
 *
 * Payload juga memuat `period.business_unit_name`, dan medan itu SENGAJA
 * tidak ditulis di sini: nama mill dibaca view dari blok `business_unit`
 * (lihat KernelPlantReportBusinessUnitRef), satu sumber saja, supaya tidak
 * ada dua tempat yang dapat menyimpang satu dari yang lain.
 */
export interface KernelPlantReportPeriodHeader {
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
 * Ketiga angka pembentuk penyebut ikut dikirim (kernel_plant_count,
 * days_counted, slots_per_kernel_plant_per_day) supaya layar dapat
 * menampilkan dari mana expected_slots berasal, alih-alih sebuah persen tanpa
 * asal.
 */
export interface KernelPlantReportCoverage {
  filled_slots: number
  expected_slots: number
  /**
   * null HANYA ketika penyebutnya tidak dapat dibentuk — expected_slots nol,
   * yakni tidak ada satu unit pun ber-record atau tidak ada satu hari pun
   * terhitung. Periode yang PUNYA record tetapi tanpa satu slot terisi
   * menghasilkan 0.0, dan 0,0% di situ benar: penyebutnya terbentuk, dan yang
   * terukur memang nol slot. Draf pertama spec layar web menyatakan ini
   * terbalik dan sudah dikoreksi; jangan mewarisi kesalahan itu.
   */
  coverage_percent: number | null
  /**
   * Unit kernel plant yang BENAR-BENAR punya record pada periode ini, bukan
   * jumlah stasiun terdaftar — penyebut dari stasiun terdaftar akan menghukum
   * mill yang sengaja tidak mengoperasikan satu unitnya.
   */
  kernel_plant_count: number
  /** 24, dari grid slot kanonis layar input itu sendiri. */
  slots_per_kernel_plant_per_day: number
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
 * KETIGA kolom target satu parameter — dan hanya tiga, karena master Kernel
 * Plant memang hanya punya tiga. Masing-masing menjawab pertanyaan yang
 * BERBEDA: `equipment_parameter` adalah apa yang dinilai, `target_benchmark`
 * adalah ke mana angkanya seharusnya, dan `corrective_action_plan` adalah apa
 * yang dilakukan ketika angkanya tidak di sana — jawaban master ini sebagai
 * ganti batas kritis yang tidak dimilikinya.
 *
 * TIDAK ADA `critical_limit` DAN TIDAK ADA
 * `operational_consequence_justification` di sini, tidak seperti blok empat
 * medan milik Depricarping. kernel_plant_operational_targets tidak punya
 * kolom itu, dan menuliskannya sebagai medan yang selamanya null hanya akan
 * membuat tipe ini berbohong dan menyerahkan dua sel yang tidak mungkin diisi
 * siapa pun ke layar.
 *
 * Medannya null ketika master belum punya baris untuk parameter itu — dan
 * ketiadaan itu diteruskan, bukan ditambal, supaya layar dapat menyatakan
 * "belum terisi pada master" alih-alih menampilkan sel kosong tanpa
 * keterangan.
 */
export interface KernelPlantReportTarget {
  /**
   * Nama parameter pada master — 'Ripple Mill (Cracker)', 'Kernel Silo 1 &
   * 2'. Ialah yang menjelaskan mengapa dua kartu berbeda dapat membawa target
   * yang sama persis.
   *
   * null ketika nama parameter pada master disunting sehingga tak lagi cocok
   * dengan peta kolom. Keadaan itu BUKAN teoretis, dan layar harus
   * memperlakukannya sebagai percabangan PERNYATAAN, bukan sekadar nilai
   * cadangan — lihat catatan pada shares_standard_with di bawah.
   */
  equipment_parameter: string | null
  /**
   * Ke mana angkanya SEHARUSNYA — '20 - 25 Amps (Nut Breakage >95%)',
   * 'Specific Gravity 1.18 - 1.24'. Dirender UTUH, keterangan dalam tanda
   * kurung dan semuanya: keterangan itu bagian dari standarnya, bukan hiasan,
   * dan sekaligus salah satu alasan laporan ini tidak menguraikannya menjadi
   * angka.
   */
  target_benchmark: string | null
  /**
   * APA YANG DILAKUKAN bila angkanya tidak di sana — 'Check heater
   * elements/steam valves if temperature drops below 65°C.' Teksnya PALING
   * PANJANG dari ketiga medan, jadi ia yang paling berisiko dibuang demi
   * ruang pada kartu 390px — dan tanpanya dua parameter yang sama-sama
   * melewati targetnya tampak menuntut tindakan yang sama.
   */
  corrective_action_plan: string | null
  /**
   * Nama kolom LAIN yang diatur baris master yang SAMA. Kosong untuk tiga
   * metrik; berisi satu entri pada KEEMPAT kolom pasangan, karena di stasiun
   * ini satu standar mengatur dua kolom DUA KALI: 'Ripple Mill (Cracker)'
   * untuk ripple_mill_1_amps + ripple_mill_2_amps, dan 'Kernel Silo 1 & 2'
   * untuk kernel_silo_1_temp_c + kernel_silo_2_temp_c.
   *
   * Diteruskan karena layar HARUS menyatakannya. Di layar sempit keempat
   * kartu itu bertumpuk berurutan, sehingga standar yang identik muncul dua
   * kali beruntun, lalu dua kali lagi — tanpa keterangannya itu terbaca
   * seperti data yang terduplikasi, lebih kuat lagi daripada di tabel web,
   * dan seseorang akan "membersihkannya". Angkanya tetap DIPISAH: empat mesin
   * fisik, dan merata-ratakan satu pasangan akan menyembunyikan
   * ketidakseimbangan beban yang justru menjadi alasan parameter itu diukur.
   *
   * View WAJIB menurunkan keterangan itu dari medan ini, bukan memaku
   * keanggotaan pasangan: markup yang mengistimewakan SATU pasangan benar di
   * Depricarping mobile dan salah di sini, dan ripple mill atau silo ketiga
   * harus ikut tertandai tanpa menyentuh satu berkas klien pun. View juga
   * WAJIB mencabangkan PERNYATAANNYA ketika equipment_parameter null:
   * mencetaknya apa adanya menghasilkan keterangan yang mengklaim sebuah
   * baris master yang justru baru terlepas — tepat pada keadaan yang aturan
   * "suntingan master harus terlihat" dibangun untuk itu.
   */
  shares_standard_with: string[]
}

/**
 * Satu kolom ukur, dengan PENYEBUTNYA SENDIRI dan standar operasionalnya.
 *
 * Perhatikan apa yang TIDAK ada di sini: tidak ada severity, tidak ada
 * is_out_of_range, tidak ada flag. Lihat catatan nomor 1 pada docblock berkas.
 */
export interface KernelPlantReportMetric {
  /** Nama kolom pada kernel_plant_details — kunci yang dipakai `averages`. */
  column: string
  /** Label Indonesia dari server: 'Arus Ripple Mill 1', 'Suhu Kernel Silo 1'. */
  label: string
  /**
   * Satuan APA ADANYA dari server: 'Amps', 'SG', 'C', '%'. Tidak dipercantik
   * di klien — 'C' tidak diubah menjadi '°C' di sini maupun di view, karena
   * laporan web mencetak medan yang sama apa adanya dan dua layar yang
   * menuliskan satuan berbeda untuk angka yang sama adalah selisih yang
   * nyata. Bila ejaannya perlu berubah, yang berubah adalah METRIC_LABELS di
   * KernelPlantReportService, satu tempat untuk kedua layar.
   */
  unit: string
  min: number | null
  avg: number | null
  max: number | null
  /** PENYEBUT min/avg/max baris ini — bukan coverage.filled_slots. */
  filled_slot_count: number
  target: KernelPlantReportTarget
}

/**
 * Standar yang belum punya pengukuran — BESERTA ALASANNYA.
 *
 * NORMALNYA TEPAT SATU BARIS di layar ini: 'Final Kernel Dirt', alasan
 * 'no_column'. Itu keadaan TENANGNYA, berlawanan dengan Depricarping yang
 * daftarnya normalnya kosong — jadi daftar yang berisi di sini bukan tanda
 * ada yang rusak. Medan `reason` tetap diteruskan sebagai medan supaya alasan
 * baru dapat ditambahkan tanpa mengubah bentuk payload.
 *
 * Perhatikan nama medannya: equipment_parameter / target_benchmark /
 * corrective_action_plan — sama dengan blok target, dan TIDAK satu pun sama
 * dengan daftar serupa pada repo Depricarping.
 */
export interface KernelPlantReportTargetWithoutMetric {
  equipment_parameter: string
  target_benchmark: string
  corrective_action_plan: string
  reason: string
}

/**
 * Satu baris rekap per unit kernel plant.
 *
 * kernel_plant_id DAN kernel_plant_name KEDUANYA `string` biasa — bukan uuid
 * dan bukan kunci asing. Tidak ada tabel master kernel_plants: id itu teks
 * yang DIKETIK operator di layar input, dan kernel_plant_name bernilai sama
 * dengan kernel_plant_id. Keduanya tetap diteruskan apa adanya supaya bentuk
 * payload tidak berubah bila kelak ada masternya.
 *
 * Penamaan unit, bukan kunci baris: nama yang sama pada dua tanggal adalah
 * SATU unit dengan dua hari pencatatan.
 */
export interface KernelPlantReportKernelPlantRow {
  kernel_plant_id: string
  kernel_plant_name: string
  day_count: number
  filled_slot_count: number
  /**
   * Total menit berhenti unit ini. null — BUKAN 0 — ketika tidak satu pun
   * slotnya mencatat downtime: unit yang tidak dicatat bukan unit yang tidak
   * pernah berhenti.
   */
  downtime_minutes: number | null
  /** Rata-rata per kolom ukur, masing-masing berpenyebut sendiri. null bila kosong. */
  averages: Record<string, number | null>
}

export interface KernelPlantReportDailyRow {
  date: string
  filled_slot_count: number
  /** null, bukan 0, ketika tidak satu pun slot hari itu mencatat downtime. */
  downtime_minutes: number | null
  averages: Record<string, number | null>
}

/**
 * Total periode — DIHITUNG ULANG server atas seluruh slot terisi, bukan
 * rata-rata dari rata-rata harian: merata-ratakan rata-rata memberi bobot
 * sama pada hari berisi dua slot dan hari berisi dua puluh empat. Repo
 * meneruskannya apa adanya; menurunkannya dari `daily` di sini akan
 * menghasilkan angka yang berbeda dari laporan web.
 */
export interface KernelPlantReportDailyTotal {
  filled_slot_count: number
  downtime_minutes: number | null
  averages: Record<string, number | null>
}

/**
 * BLOK DOWNTIME SEBAGAI ANGKA — bentuk yang TIDAK ADA pada repo Threshing
 * maupun Pressing, di mana downtime hanya berupa daftar alasan teks dan
 * "berapa lama stasiun berhenti" tidak dapat dijawab sama sekali.
 * kernel_plant_details.downtime_minutes adalah kolom INTEGER.
 *
 * Menyalin tipe DowntimeRow dari salah satu repo saudaranya ke sini akan
 * menghasilkan undefined di mana-mana: bentuknya objek, bukan daftar.
 */
export interface KernelPlantReportDowntime {
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
   * seseorang bahwa stasiun tidak berhenti pada slot itu, dan
   * KernelPlantRecordService::isRowFilled() pun memperlakukannya demikian.
   */
  recorded_slot_count: number
  /** total_minutes / recorded_slot_count. null bila penyebutnya nol. */
  avg_minutes_per_recorded_slot: number | null
  /**
   * Selalu false — downtime_minutes tidak punya baris pada master target,
   * yang keenam parameternya semuanya pengukuran. Diteruskan sebagai medan
   * supaya layar dapat MENYATAKAN ketiadaannya, alih-alih menampilkan sel
   * target kosong yang terbaca seperti master yang belum terisi.
   */
  has_standard: boolean
}

/**
 * Satu baris rekap temuan — paruh TEKS BEBAS dari apa yang dijawab blok
 * downtime dengan angka (kernel_plant_details.findings, kolom TERPISAH).
 *
 * Kuncinya `finding`, BUKAN `reason` seperti pada repo Threshing/Pressing.
 */
export interface KernelPlantReportFindingRow {
  /** Teks apa adanya. Dua ejaan untuk satu hal = dua baris, dan itu benar. */
  finding: string
  slot_count: number
}

export interface KernelPlantReportTotals {
  record_count: number
  days_with_records: number
  /** Record draft IKUT seluruh angka; jumlahnya hanya dinyatakan. */
  draft_record_count: number
  records_not_checked: number
  records_not_acknowledged: number
}

export interface KernelPlantReportSummary {
  business_unit: KernelPlantReportBusinessUnitRef | null
  production_line: KernelPlantReportProductionLineRef | null
  period: KernelPlantReportPeriodHeader | null
  /** Membedakan "tidak ada yang dilaporkan" dari "angkanya nol". */
  has_data: boolean
  coverage: KernelPlantReportCoverage
  /** Selalu TUJUH entri, juga ketika sebuah kolom tidak pernah terisi. */
  metrics: KernelPlantReportMetric[]
  targets_without_metric: KernelPlantReportTargetWithoutMetric[]
  targets_master_empty: boolean
  /**
   * true bila targets_without_metric kosong. DIBACA TERPISAH dari
   * targets_master_empty, karena keduanya dapat sama-sama menunjuk daftar
   * kosong untuk sebab yang BERLAWANAN: master yang belum terisi versus
   * seluruh standar yang sudah punya pengukuran. Layar memeriksa
   * targets_master_empty lebih dulu.
   */
  all_targets_measured: boolean
  by_kernel_plant: KernelPlantReportKernelPlantRow[]
  daily: KernelPlantReportDailyRow[]
  daily_total: KernelPlantReportDailyTotal
  /** Objek, bukan daftar — lihat KernelPlantReportDowntime. */
  downtime: KernelPlantReportDowntime
  /** Bagian TERPISAH dari downtime: satu "berapa lama", satu "apa yang terlihat". */
  findings: KernelPlantReportFindingRow[]
  total: KernelPlantReportTotals
}

/**
 * Cakupan angka yang diminta pemanggil — SATU medan saja, dan itu disengaja.
 *
 * Tidak ada `isAdmin` dan tidak ada `businessUnitId` di sini, tidak seperti
 * kesepuluh repo laporan mobile sebelumnya: mill diselesaikan server dari
 * akun bagi kedua aktor layar ini, jadi medan itu tidak punya pekerjaan dan
 * medan yang tidak ada tidak dapat salah terkirim.
 */
export interface KernelPlantReportScope {
  /**
   * Production Line yang angkanya diminta. Dikirim ke /summary dan /export
   * saja. WAJIB di layar ini: tanpa nilai ini server menjawab 422 (dan
   * SENGAJA tidak memulangkan angka seluruh mill), dan view memang tidak
   * memanggil /summary sebelum sebuah line berlaku.
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
const EMPTY_COVERAGE: KernelPlantReportCoverage = {
  filled_slots: 0,
  expected_slots: 0,
  coverage_percent: null,
  kernel_plant_count: 0,
  slots_per_kernel_plant_per_day: 0,
  days_in_period: 0,
  days_counted: 0,
  period_running: false,
}

const EMPTY_DAILY_TOTAL: KernelPlantReportDailyTotal = {
  filled_slot_count: 0,
  downtime_minutes: null,
  averages: {},
}

/**
 * Bawaan blok downtime ketika payload tidak memuatnya. total_minutes null,
 * BUKAN 0 — bentuk bawaan yang berbohong lebih buruk daripada bentuk bawaan
 * yang kosong.
 */
const EMPTY_DOWNTIME: KernelPlantReportDowntime = {
  total_minutes: null,
  recorded_slot_count: 0,
  avg_minutes_per_recorded_slot: null,
  has_standard: false,
}

const EMPTY_TOTALS: KernelPlantReportTotals = {
  record_count: 0,
  days_with_records: 0,
  draft_record_count: 0,
  records_not_checked: 0,
  records_not_acknowledged: 0,
}

/**
 * production_line_id — dikirim HANYA ke /summary dan /export.
 *
 * TIDAK PERNAH ke /periods. Periode adalah milik MILL, bukan milik Production
 * Line, dan backend pun tidak menerima parameter ini di sana. Menyaring daftar
 * periode per line akan mengarang penyempitan yang tidak ada di data; yang
 * disaring sebuah line adalah DATA-nya, bukan daftar periodenya.
 *
 * Yang dijaga di sini hanya satu: nilai kosong tidak pernah dikirim sebagai
 * parameter kosong — server akan menjawab 422 tentang parameter yang HILANG,
 * dan itu pesan yang benar.
 */
function productionLineParams(scope?: KernelPlantReportScope): Record<string, string> {
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
 * GET /api/kernel-plant-reports/periods — periode yang mencakup stasiun
 * Kernel Plant, yakni periode yang punya baris period_stations berjenis
 * 'kernel-plant' (DENGAN TANDA HUBUNG; server membaca nilainya dari
 * App\Enums\StationType alih-alih menuliskannya, karena garis bawah di situ
 * tidak akan cocok dengan satu baris pun dan menjawab [] tanpa galat).
 *
 * Penyaringannya dikerjakan SERVER; repo meneruskan daftar apa adanya, dalam
 * urutan yang sama — menyaringnya kedua kali di sini akan menciptakan
 * definisi cakupan yang kedua. Daftar kosong adalah jawaban yang sah (HTTP
 * 200 + []), bukan galat.
 *
 * Mill tidak ikut sebagai parameter: server menyelesaikannya dari akun.
 * Periode TERTUTUP tetap terdaftar — status mengatur penulisan data, bukan
 * pembacaan laporan.
 */
export async function fetchPeriods(): Promise<KernelPlantReportPeriodOption[]> {
  const response = await apiClient.get('/api/kernel-plant-reports/periods')

  return (response.data?.data ?? []) as KernelPlantReportPeriodOption[]
}

/**
 * GET /api/kernel-plant-reports/summary — seluruh angka layar untuk satu
 * periode pada satu Production Line.
 *
 * Setiap blok diteruskan APA ADANYA, tanpa satu operasi aritmetika pun: tidak
 * ada penurunan coverage_percent dari filled_slots/expected_slots, tidak ada
 * perata-rataan ulang min/avg/max, tidak ada penurunan daily_total dari
 * `daily`, tidak ada pengurutan ulang metrics / by_kernel_plant / daily /
 * findings, dan TIDAK ADA perbandingan satu nilai pun terhadap `target`.
 * Nilai bawaan di atas hanya berlaku bila BLOK-nya tidak ada sama sekali pada
 * respons.
 */
export async function fetchSummary(
  periodId: string,
  scope?: KernelPlantReportScope,
): Promise<KernelPlantReportSummary> {
  const response = await apiClient.get('/api/kernel-plant-reports/summary', {
    params: { period_id: periodId, ...productionLineParams(scope) },
  })

  const body = (unwrap<Record<string, unknown>>(response.data) ?? {}) as Record<string, unknown>

  return {
    business_unit: (body.business_unit ?? null) as KernelPlantReportBusinessUnitRef | null,
    production_line: (body.production_line ?? null) as KernelPlantReportProductionLineRef | null,
    period: (body.period ?? null) as KernelPlantReportPeriodHeader | null,
    has_data: (body.has_data ?? false) as boolean,
    coverage: (body.coverage ?? { ...EMPTY_COVERAGE }) as KernelPlantReportCoverage,
    metrics: (body.metrics ?? []) as KernelPlantReportMetric[],
    targets_without_metric: (body.targets_without_metric ??
      []) as KernelPlantReportTargetWithoutMetric[],
    targets_master_empty: (body.targets_master_empty ?? false) as boolean,
    all_targets_measured: (body.all_targets_measured ?? false) as boolean,
    by_kernel_plant: (body.by_kernel_plant ?? []) as KernelPlantReportKernelPlantRow[],
    daily: (body.daily ?? []) as KernelPlantReportDailyRow[],
    daily_total: (body.daily_total ?? { ...EMPTY_DAILY_TOTAL }) as KernelPlantReportDailyTotal,
    downtime: (body.downtime ?? { ...EMPTY_DOWNTIME }) as KernelPlantReportDowntime,
    findings: (body.findings ?? []) as KernelPlantReportFindingRow[],
    total: (body.total ?? { ...EMPTY_TOTALS }) as KernelPlantReportTotals,
  }
}

/**
 * GET /api/kernel-plant-reports/export?period_id=...&production_line_id=...&format=csv
 * — respons streaming text/csv, BUKAN JSON, jadi responseType-nya blob.
 *
 * Isinya (SATU BARIS PER SLOT WAKTU, dengan konteks Periode/Mill/Production
 * Line, tanggal, unit kernel plant, status, dan catatan diulang verbatim di
 * setiap baris) dibentuk SERVER dan sama persis dengan ekspor laporan versi
 * web, karena endpoint-nya memang sama. Slot yang seluruh kolom ukurnya
 * kosong TETAP satu baris, dengan sel kosong — membuangnya akan membuat
 * berkasnya berselisih dengan angka cakupan yang diterbitkan laporan yang
 * sama. Kesembilan kolom bacaan ikut, menit downtime dan temuan termasuk.
 *
 * Format 'csv' saja: ekspor .xlsx hanya ada di layar web — bukan karena
 * endpoint-nya tidak mendukungnya (ia mendukung), melainkan karena menyimpan
 * dan membuka berkas itu di WebView ponsel menuntut penanganan berkas native
 * yang belum ada di aplikasi ini.
 */
export async function exportCsv(
  periodId: string,
  scope?: KernelPlantReportScope,
): Promise<Blob> {
  const response = await apiClient.get('/api/kernel-plant-reports/export', {
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

export const kernelPlantReportRepo = {
  fetchPeriods,
  fetchSummary,
  exportCsv,
  saveCsvFile,
}

export default kernelPlantReportRepo
