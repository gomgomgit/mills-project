import apiClient from '@/services/apiClient'

/**
 * engineRoomReportRepo — screen-159--laporan-engine-room-mobile /
 * usecase-165--laporan-engine-room-mobile "Lihat Laporan Periode Engine Room
 * (Mobile)".
 *
 * Kembaran KETIGA BELAS dari pola yang sama: sterilizerReportRepo.ts
 * (screen-135), cagesTrackReportRepo.ts (screen-136), boilerRoomReportRepo.ts
 * (screen-137), clarificationReportRepo.ts (screen-138),
 * storageTankReportRepo.ts (screen-139), weighbridgeReportRepo.ts
 * (screen-144), gradingReportRepo.ts (screen-147), threshingReportRepo.ts
 * (screen-149), pressingReportRepo.ts (screen-151),
 * depricarpingReportRepo.ts (screen-153), kernelPlantReportRepo.ts
 * (screen-155), dan effluentPlantReportRepo.ts (screen-157). Satu berkas repo
 * tipis di atas apiClient, tanpa cache lokal. Layar laporan ini memang
 * MEMBUTUHKAN jaringan, jadi tidak ada jalur SQLite offline di sini sama
 * sekali — kegagalan jaringan adalah kondisi yang ditampilkan ke pengguna,
 * bukan yang disembunyikan di balik data basi.
 *
 * ────────────────────────────────────────────────────────────────────────
 * NOL PERUBAHAN BACKEND, DAN ITU DIBAYAR LEBIH DULU DENGAN SENGAJA
 * ────────────────────────────────────────────────────────────────────────
 * BERBEDA DARI screen-157, yang pelebaran akses Operator-nya harus dikerjakan
 * di putaran mobile-nya sendiri. Di sini ketiga rute data
 * /api/engine-room-reports/* SUDAH menerima Operator sejak commit pertama
 * screen-158 — justru supaya layar ini tidak perlu melebarkan akses apa pun —
 * dan /api/production-lines/options-for-report dipakai ulang VERBATIM dari
 * screen-135. Akibatnya: NOL endpoint baru, NOL entri api-index baru, dan NOL
 * berkas backend yang disentuh untuk layar ini. Bila sebuah test backend
 * memerah saat layar ini dibangun, sebabnya ada di tempat lain.
 *
 * TIDAK ADA business_unit_id DI BERKAS INI, SAMA SEKALI — sama seperti
 * effluentPlantReportRepo.ts dan kernelPlantReportRepo.ts, dengan alasan yang
 * sama: kedua aktor layar ini (Operator dan Supervisor) TERIKAT SATU MILL,
 * server menyelesaikan mill dari akun, jadi parameter itu tidak punya
 * pekerjaan apa pun di sini. Cabang Admin (scopeParams + fetchBusinessUnits)
 * yang masih dibawa kesepuluh repo pertama TIDAK ada, jadi mustahil ada jalur
 * yang mengirimkannya — gagal-tertutup secara KONSTRUKSI, bukan secara
 * penjagaan. Konsekuensinya disengaja:
 * /api/engine-room-reports/business-units/options tidak dipanggil dari mana
 * pun. Admin dan Mill Management memakai layar WEB screen-158, yang
 * satu-satunya punya pemilih mill.
 *
 * NOL PERHITUNGAN ULANG DI KLIEN. Berkas ini tidak pernah menjumlah,
 * merata-ratakan, membulatkan, mengurutkan, menyaring, atau menurunkan satu
 * angka pun — TERMASUK tidak memetakan satuan 'C' menjadi '°C'. Seluruh nilai
 * diteruskan apa adanya dari respons server, karena laporan web memakai
 * sumber yang sama (EngineRoomReportService): perhitungan kedua di sisi klien
 * pasti akan menyimpang dari laporan web tanpa ketahuan, dan ponsel yang
 * berselisih dengan layar web tentang angka yang sama adalah bentuk kegagalan
 * yang paling sulit dibantah.
 *
 * MENGETIKKAN SELURUH BENTUK RESPONS ADALAH YANG MEMBUAT ATURAN ITU DAPAT
 * DITEGAKKAN. Field yang tidak diketik mengundang perhitungan ad hoc di view;
 * karena itu `daily_energy_export` diketik sebagai kunci TERPISAH dari
 * `metrics`, lengkap dengan `last_value` dan `time_slot`-nya, alih-alih
 * dibiarkan `unknown` dan diurai ulang di template.
 *
 * ────────────────────────────────────────────────────────────────────────
 * EMPAT HAL YANG MEMBUAT TIPE RESPONS INI BERBEDA DARI EFFLUENT PLANT
 * ────────────────────────────────────────────────────────────────────────
 * Menyalin tipe effluentPlantReportRepo.ts mentah-mentah akan membuat tipe
 * ini BERBOHONG — keempatnya perbedaan BENTUK, bukan perbedaan nilai:
 *
 * 1. DUA PULUH DUA ENTRI `metrics`, BUKAN EMPAT BELAS, dan masing-masing
 *    membawa `group` — penanda blok tata letaknya. VIEW MEMBANGUN KEENAM
 *    BLOKNYA DARI FIELD ITU, bukan dari daftar nama kolom yang dipaku di
 *    template: dua puluh tiga kartu yang ditulis manual adalah dua puluh tiga
 *    kesempatan kehilangan penyebut, dan kolom baru dari migrasi kelak masuk
 *    ke blok yang benar tanpa menyunting satu berkas klien pun.
 *
 * 2. KOLOM UKUR KE-23 TIDAK ADA DI `metrics` SAMA SEKALI.
 *    `daily_energy_export_kwh` diterbitkan pada kunci TERPISAH
 *    `daily_energy_export` sebagai seri nilai TERAKHIR per tanggal beserta
 *    `time_slot` asalnya — tanpa rata-rata periode di mana pun pada payload.
 *    Sebabnya: nama kolomnya menyebut 'daily' sementara ia disimpan per slot
 *    waktu, dan semantiknya belum terjelaskan (pertanyaan terbuka screen-158).
 *
 *    KLIEN TIDAK MENENTUKAN SLOT MANA YANG 'TERAKHIR'. Server menentukannya
 *    menurut POSISI pada kisi 24 slot kanonis (07:00 .. 06:00 keesokan hari).
 *    Menurunkannya ulang di klien dengan mengurutkan slot atau membandingkan
 *    string jam akan mengulang PERSIS cacat yang sudah diperbaiki di
 *    screen-158: 06:00 ada di posisi TERAKHIR pada kisi itu, sementara urutan
 *    leksikal memilihnya sebagai yang paling AWAL — dan hasilnya terlihat
 *    sepenuhnya masuk akal. Karena itu tidak ada satu pun sort, max, atau
 *    perbandingan jam di berkas ini.
 *
 * 3. `status_distribution` memuat DUA kolom (bukan tiga), dan nilai dikenal
 *    tiap kolomnya TIGA: run / standby / off. 'STANDBY' BUKAN 'OFF' — genset
 *    siaga siap menanggung beban, genset mati tidak — dan `not_recorded` tidak
 *    pernah dilipat ke salah satu dari ketiganya.
 *
 * 4. TIDAK ADA `is_lab` pada metrik, TIDAK ADA `target`, TIDAK ADA
 *    `shares_standard_with`, dan TIDAK ADA blok `downtime`. Engine Room tidak
 *    punya tabel master target sama sekali — dan itu berlaku MESKI frekuensi
 *    sistem, power factor, serta tegangan busbar punya batas yang dikenal
 *    sangat luas di luar aplikasi — serta tidak punya satu pun kolom downtime
 *    di engine_room_details. Menyalin medan yang selamanya null ke sini hanya
 *    menyerahkan sel yang tidak mungkin diisi siapa pun ke layar.
 *
 * engine_room_id BERTIPE `string` BIASA — bukan uuid dan bukan kunci asing.
 * Tidak ada tabel master engine_rooms: nilainya kolom teks yang DIKETIK di
 * layar input (screen-077), bentuk yang sama dengan boiler_room_id dan
 * effluent_plant_id. Satu nama yang sama pada dua tanggal adalah SATU unit
 * dengan dua hari pencatatan.
 *
 * SETIAP METRIK MEMBAWA PENYEBUTNYA SENDIRI. `metrics[kolom].reading_count`
 * adalah banyaknya slot yang BENAR-BENAR MENCATAT kolom itu — penyebut
 * min/avg/max-nya. Kedua puluh tiga kolom nullable dan terisi saling bebas,
 * jadi satu penyebut bersama akan salah untuk setidaknya dua puluh dua di
 * antaranya — dan di ponsel cacat itu LEBIH sulit terlihat daripada di web,
 * karena kartunya tidak pernah berdampingan.
 *
 * SATU KEJANGGALAN YANG BENAR: coverage.filled_slots BISA LEBIH BESAR
 * daripada penyebut kolom ukur mana pun. Sebuah slot dihitung terisi bila
 * salah satu dari DUA PULUH TUJUH kolom bacaan terisi (definisinya DIPINJAM
 * dari EngineRoomRecordService::READING_FIELDS, bukan diturunkan ulang), dan
 * empat di antaranya (dua kolom status, dua kolom teks) bukan kolom ukur —
 * slot yang hanya memuat temuan jelas disentuh operator, jadi melaporkannya
 * sebagai slot kosong akan salah. Yang dilampaui adalah penyebut KARTU, bukan
 * penyebut CAKUPAN. Dicatat di sini supaya pembaca berikutnya tidak
 * "memperbaiki" selisih yang memang benar.
 *
 * NULL BUKAN NOL. min, avg, max tiap metrik, coverage_percent, last_value seri
 * energi, dan seluruh nilai di dalam `metrics_avg` boleh null. Mengoersinya
 * menjadi 0 (atau '' / '-') di sini akan mengubah "tidak ada yang diukur"
 * menjadi "hasilnya nol" — dua fakta yang berbeda, dan yang satu menyesatkan.
 * Penerjemahan null menjadi teks terbaca adalah urusan view, bukan repo.
 *
 * SEBARAN STATUS DITERUSKAN UTUH, DAN VIEW MENGITERASI `known_values`.
 * Server sudah melengkapi nilai dikenal yang tidak pernah muncul dengan 0;
 * mengiterasi kunci `counts` yang ada akan menghilangkan baris bernilai 0, dan
 * baris yang hilang terbaca sebagai "nilai ini tidak ada" padahal yang benar
 * adalah "nilai ini tidak pernah terjadi". `not_recorded` adalah kunci
 * TERPISAH dan TIDAK PERNAH dilipat ke 'off'. Penyebut porsinya
 * `recorded_slots`, BUKAN `expected_slots`.
 *
 * GALAT DITERUSKAN APA ADANYA. Tidak ada try/catch dan tidak ada kelas galat
 * khusus di sini. Interceptor apiClient menolak lewat normalizeError yang
 * mengembalikan objek DATAR { message, errors?, status? } dan MEMBUANG
 * `response` — jadi `.response` tidak pernah ada pada galat yang sampai ke
 * pemanggil. 401 diterjemahkan menjadi "arahkan ke Login TANPA pesan
 * jaringan" oleh LaporanEngineRoomView (membaca `error.status`), 403 pada
 * /summary menjadi "pilih line lagi", 404 menjadi "periode tidak ditemukan",
 * 422 akun-tanpa-mill menjadi arahan menghubungi Admin, dan kegagalan
 * transport (penolakan tanpa `status`) menjadi pesan + tombol Coba Lagi di
 * sana pula.
 *
 * DAFTAR PRODUCTION LINE TIDAK ADA DI BERKAS INI, dan itu bukan kelalaian:
 * GET /api/production-lines/options-for-report DIPAKAI ULANG VERBATIM dari
 * screen-135 lewat productionLineRepo.fetchProductionLinesForReport(null) —
 * endpoint itu tidak diduplikasi di bawah prefix engine-room-reports, dan repo
 * laporan ini pun tidak membungkusnya kedua kali.
 */

export interface EngineRoomReportPeriodOption {
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
  /** Selalu terisi 'engine-room' — DENGAN TANDA HUBUNG, dan tidak pernah null. */
  station_type: string
  station_type_label: string
}

export interface EngineRoomReportBusinessUnitRef {
  id: string
  name: string
}

export interface EngineRoomReportProductionLineRef {
  id: string
  name: string
}

/**
 * Kepala periode pada respons /summary.
 *
 * Payload juga memuat `period.business_unit_name`, dan medan itu SENGAJA tidak
 * ditulis di sini: nama mill dibaca view dari blok `business_unit` (lihat
 * EngineRoomReportBusinessUnitRef), satu sumber saja, supaya tidak ada dua
 * tempat yang dapat menyimpang satu dari yang lain. Respons /periods TIDAK
 * memuat nama mill sama sekali — koreksi itu sudah ditemukan saat screen-155
 * diimplementasikan dan tidak diwarisi salah lagi ke sini.
 */
export interface EngineRoomReportPeriodHeader {
  id: string
  name: string
  start_date: string
  end_date: string
  status: string
}

/**
 * Cakupan pencatatan — dirender PALING ATAS di layar, sebelum satu kartu pun.
 * Di layar sempit ia satu-satunya bagian yang pasti terlihat tanpa menggulir,
 * dan ia menentukan apakah dua puluh tiga angka di bawahnya layak dipercaya:
 * periode yang terisi seperlima pun menghasilkan rata-rata yang terlihat rapi
 * atas dua puluh tiga kolom yang semuanya nullable.
 *
 * Ketiga angka pembentuk penyebut ikut dikirim (engine_room_unit_count,
 * days_counted, slots_per_unit_per_day) supaya layar dapat menampilkan dari
 * mana expected_slots berasal, alih-alih sebuah persen tanpa asal.
 */
export interface EngineRoomReportCoverage {
  /**
   * Slot yang terisi atas DUA PULUH TUJUH kolom — kedua puluh tiga kolom ukur,
   * kedua kolom status genset, dan KEDUA kolom teks bebas. Karena itu angka
   * ini dapat MELEBIHI `reading_count` setiap kartu metrik, dan itu benar.
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
   * Unit Engine Room yang BENAR-BENAR punya record pada periode dan line ini,
   * bukan jumlah stasiun terdaftar — penyebut dari stasiun terdaftar akan
   * menghukum mill yang sengaja tidak mengoperasikan satu unitnya.
   */
  engine_room_unit_count: number
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
 * Satu kolom ukur, dengan PENYEBUTNYA SENDIRI dan BLOK TATA LETAKNYA SENDIRI.
 *
 * `group` ADALAH YANG MEMBUAT KEDUA PULUH DUA KARTU SATU MARKUP. View
 * mengelompokkan `metrics` menurut medan ini — bukan menurut daftar nama kolom
 * yang dipaku di template — sehingga kolom baru dari migrasi kelak masuk ke
 * blok yang benar tanpa menyunting satu berkas klien pun. Nilainya salah satu
 * dari: steam_turbine, diesel_gen_1, diesel_gen_2, electrical_sync,
 * compressor, auxiliary.
 *
 * Perhatikan apa yang TIDAK ada di sini: tidak ada `target`, tidak ada
 * severity, tidak ada is_out_of_range, tidak ada flag, dan tidak ada `is_lab`
 * — payload Engine Room tidak menerbitkan satu pun di antaranya, dan stasiun
 * ini tidak punya tabel master target sama sekali. `label` dan `unit` tetap
 * ditulis sebagai medan OPSIONAL supaya nilai server, bila kelak diterbitkan,
 * MENANG atas tabel label view — dan supaya repo ini tidak pernah punya peta
 * satuan yang mengubah 'C' menjadi '°C'.
 */
export interface EngineRoomReportMetric {
  min: number | null
  avg: number | null
  max: number | null
  /** PENYEBUT min/avg/max kolom ini — bukan coverage.filled_slots. */
  reading_count: number
  /** Blok tata letak kolom ini, dari RESPONS. View membangun bloknya dari sini. */
  group: string
  /** Hanya bila server menerbitkannya; view punya tabel labelnya sendiri. */
  label?: string
  /** Satuan APA ADANYA dari server bila ada — tidak pernah dipercantik di sini. */
  unit?: string
}

/**
 * Satu titik seri ekspor energi harian: nilai TERAKHIR tanggal itu beserta
 * SLOT WAKTU asalnya.
 *
 * `last_value` SUDAH DIPILIH SERVER menurut posisi slot pada kisi 24 slot
 * kanonis. Klien merendernya beserta `time_slot`-nya dan tidak pernah
 * menurunkan sendiri slot mana yang terakhir — lihat catatan #2 pada docblock
 * berkas ini. `time_slot` ikut diterbitkan justru supaya pembaca dapat
 * MEMERIKSA nilai mana yang dipakai.
 */
export interface EngineRoomReportEnergyPoint {
  date: string
  /** null tidak pernah muncul di `series` — titik tanpa nilai tidak diterbitkan. */
  last_value: number | null
  time_slot: string
  reading_count: number
}

/**
 * Kolom ukur ke-23, dan satu-satunya yang TIDAK punya rata-rata periode di
 * mana pun pada payload.
 *
 * `has_data` false dengan `series` kosong berarti kolomnya tidak pernah terisi
 * sepanjang periode — kartunya menyatakan tidak ada data dan TIDAK menggambar
 * nol, karena nol akan terbaca sebagai hasil pengukuran.
 */
export interface EngineRoomReportEnergyExport {
  series: EngineRoomReportEnergyPoint[]
  has_data: boolean
}

/**
 * Nilai energi satu bucket (satu unit pada `by_unit`, satu tanggal pada
 * `daily`): nilai TERAKHIR bucket itu, bukan rata-ratanya. Kolom ini tidak
 * pernah dirata-rata, di bucket mana pun.
 */
export interface EngineRoomReportEnergyValue {
  last_value: number | null
  date: string | null
  time_slot: string | null
  reading_count: number
}

/**
 * Sebaran satu kolom status kategoris: jumlah slot per nilai.
 *
 * TIDAK PERNAH min/avg/max dan TIDAK PERNAH muncul pada `metrics` maupun
 * `daily_trend` — terendah, rata-rata, dan tertinggi tidak punya arti untuk
 * kolom kategoris.
 */
export interface EngineRoomReportStatusDistribution {
  /**
   * URUTAN DAN KELENGKAPAN SEBARAN ADA DI SINI, dan VIEW MENGITERASI MEDAN
   * INI — bukan kunci `counts`. Server mengelompokkan nilai yang benar-benar
   * ada secara harfiah lebih dulu, lalu melengkapi nilai dikenal yang tidak
   * pernah muncul dengan 0, lalu menambahkan nilai di luar himpunan dikenal di
   * belakang (supaya nilai baru dari migrasi tidak hilang). Mengiterasi
   * `counts` akan menghilangkan baris bernilai 0, dan baris yang hilang
   * terbaca sebagai "nilai ini tidak ada" padahal yang benar adalah "nilai ini
   * tidak pernah terjadi".
   *
   * Pada layar ini isinya TIGA nilai: run, standby, off.
   */
  known_values: string[]
  counts: Record<string, number>
  /**
   * Slot TERISI yang kolom status ini-nya NULL. Kunci TERPISAH, dan TIDAK
   * PERNAH dilipat ke 'off': itu akan melaporkan pembangkit mati yang tidak
   * pernah terjadi, dan itu kesimpulan yang salah tentang kesiapan pabrik.
   */
  not_recorded: number
  /**
   * PENYEBUT porsi tiap nilai — jumlah slot TERISI. BUKAN expected_slots, yang
   * akan mencampur slot tak tersentuh ke dalam porsi sebuah nilai yang justru
   * tercatat. Invariannya: sum(counts) + not_recorded === recorded_slots.
   */
  recorded_slots: number
}

/**
 * Satu KEJADIAN teks bebas: satu entri = satu SLOT yang mengisinya, membawa
 * tanggal dan slot waktunya.
 *
 * Dipakai DUA KALI pada dua daftar TERPISAH (maintenance_remarks dan
 * findings), dan keduanya TIDAK PERNAH disatukan menjadi daftar ketiga: satu
 * slot yang mengisi kedua kolom muncul pada KEDUA daftar, dan itu benar —
 * menyatukannya akan menghitung slot itu dua kali di dalam satu daftar dan
 * mencampur dua pertanyaan yang berbeda (apa yang dikerjakan vs apa yang
 * ditemukan).
 */
export interface EngineRoomReportTextEntry {
  date: string
  time_slot: string
  text: string
}

/** Satu titik tren harian: RATA-RATA tanggal itu, dengan penyebutnya sendiri. */
export interface EngineRoomReportTrendPoint {
  date: string
  /** null — BUKAN 0 — bila tanggal itu tidak sekali pun mencatat kolomnya. */
  avg: number | null
  reading_count: number
}

/**
 * SATU-SATUNYA tren harian: beban total pabrik.
 *
 * AGREGASINYA RATA-RATA, dan itu SENGAJA BERBEDA dari
 * `daily_energy_export` yang memuat nilai TERAKHIR hari itu. Perbedaan itu
 * dinyatakan di layar, di dekat tren itu sendiri, supaya kedua seri tidak
 * terbaca sejenis. Rata-rata sah di sini karena beban total pabrik memang
 * besaran sesaat per slot, bukan angka meter kumulatif.
 *
 * Kedua kolom status tidak pernah muncul di sini — keduanya kategoris.
 */
export interface EngineRoomReportDailyTrend {
  electrical_sync_total_factory_load_kw: EngineRoomReportTrendPoint[]
}

/** Satu nilai rata-rata di dalam rekap, dengan penyebutnya sendiri. */
export interface EngineRoomReportAverage {
  avg: number | null
  reading_count: number
}

/**
 * Satu baris rekap per UNIT Engine Room.
 *
 * engine_room_id adalah `string` biasa — bukan uuid dan bukan kunci asing.
 * `metrics` membawa BENTUK YANG SAMA dengan `metrics` tingkat periode (dua
 * puluh dua entri, masing-masing dengan penyebutnya sendiri dan `group`-nya
 * sendiri), dan `energy_export` berdampingan dengannya karena kolom ke-23
 * tidak pernah dirata-rata.
 *
 * `reading_count` diterbitkan BERDAMPINGAN dengan `reading_rows` bernilai
 * sama: kedua nama dipakai bergantian di dua belas laporan saudaranya, dan
 * menerbitkan keduanya membuat setiap pemanggil tetap membaca kunci yang sudah
 * diharapkannya. Unit yang punya record tetapi tidak satu pun pembacaan TETAP
 * muncul dengan reading_rows 0 — membuangnya justru akan menyembunyikan unit
 * yang tidak pernah dituliskan.
 */
export interface EngineRoomReportUnitRow {
  engine_room_id: string
  reading_rows: number
  reading_count: number
  days_with_rows: number
  metrics: Record<string, EngineRoomReportMetric>
  energy_export: EngineRoomReportEnergyValue
}

/**
 * Satu baris rekap per HARI yang punya minimal satu slot terisi.
 *
 * Tanggal tanpa satu pun slot terisi TIDAK mendapat baris: baris nol akan
 * terbaca sebagai "terukur nol", padahal tidak ada yang diukur.
 * `energy_export` ada DI SAMPING `metrics_avg`, bukan di dalamnya: sel energi
 * baris ini memuat nilai TERAKHIR tanggal itu sementara setiap sel lain pada
 * baris yang sama memuat rata-rata. Dua agregasi, dua kunci, sehingga tidak
 * satu pun dapat terbaca sebagai yang lain.
 */
export interface EngineRoomReportDailyRow {
  date: string
  reading_rows: number
  metrics_avg: Record<string, EngineRoomReportAverage>
  energy_export: EngineRoomReportEnergyValue
}

/**
 * Total baris rekap harian — HANYA jumlah slot, tanpa rata-rata.
 *
 * Rata-rata tingkat periode ada di `metrics` dan DIHITUNG ULANG server atas
 * seluruh slot terisi, bukan dirata-ratakan dari rata-rata harian:
 * merata-ratakan rata-rata memberi bobot sama pada hari berisi dua slot dan
 * hari berisi dua puluh empat. View memakai `metrics` untuk baris total, bukan
 * menurunkannya dari `daily`.
 */
export interface EngineRoomReportDailyTotal {
  reading_rows: number
}

export interface EngineRoomReportTotals {
  days_with_records: number
  reading_rows: number
}

/**
 * Seluruh angka layar untuk satu periode pada satu Production Line.
 *
 * TIDAK ADA `target`, TIDAK ADA `downtime`, dan TIDAK ADA satu pun kunci
 * penandaan di luar batas — lihat keempat catatan pada docblock berkas.
 */
export interface EngineRoomReportSummary {
  business_unit: EngineRoomReportBusinessUnitRef | null
  production_line: EngineRoomReportProductionLineRef | null
  period: EngineRoomReportPeriodHeader | null
  /** Membedakan "tidak ada yang dilaporkan" dari "angkanya nol". */
  has_data: boolean
  coverage: EngineRoomReportCoverage
  /**
   * DUA PULUH DUA entri, juga ketika sebuah kolom tidak pernah terisi.
   * `daily_energy_export_kwh` TIDAK ADA DI SINI — lihat `daily_energy_export`.
   */
  metrics: Record<string, EngineRoomReportMetric>
  /** Kolom ukur ke-23, pada kunci TERPISAH dan tanpa rata-rata periode. */
  daily_energy_export: EngineRoomReportEnergyExport
  /** Dua kolom status kategoris sebagai sebaran jumlah slot per nilai. */
  status_distribution: Record<string, EngineRoomReportStatusDistribution>
  /** "Apa yang dikerjakan" — daftar KEJADIAN, satu entri per slot. */
  maintenance_remarks: EngineRoomReportTextEntry[]
  /** "Apa yang ditemukan" — daftar TERPISAH, satu entri per slot. */
  findings: EngineRoomReportTextEntry[]
  daily_trend: EngineRoomReportDailyTrend
  by_unit: EngineRoomReportUnitRow[]
  daily: EngineRoomReportDailyRow[]
  daily_total: EngineRoomReportDailyTotal
  total: EngineRoomReportTotals
}

/**
 * Cakupan angka yang diminta pemanggil — SATU medan saja, dan itu disengaja.
 *
 * Tidak ada `isAdmin` dan tidak ada `businessUnitId` di sini, sama seperti
 * repo Effluent Plant dan Kernel Plant: mill diselesaikan server dari akun
 * bagi kedua aktor layar ini, jadi medan itu tidak punya pekerjaan dan medan
 * yang tidak ada tidak dapat salah terkirim.
 */
export interface EngineRoomReportScope {
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
 * SALINAN PETA LABEL NILAI ENUM DARI SERVER, DAN IA WAJIB SELARAS DENGANNYA.
 *
 * NILAINYA WAJIB SAMA DENGAN `Display::OPTION_LABELS` di backend
 * (backend/app/Support/Display.php). Layar Detail Engine Room (screen-107),
 * formulir (screen-077), ekspor Data Browser, ekspor laporan web, dan ekspor
 * CSV layar ini SEMUANYA bersandar pada peta itu, jadi satu nilai hanya boleh
 * punya satu label di seluruh produk.
 *
 * Berbeda dari sisi web yang dapat MENDELEGASIKAN ke `Display::option()`,
 * klien tidak dapat memanggil PHP — jadi peta ini salinan yang harus dijaga
 * selaras. Menambah nilai baru di `Display::OPTION_LABELS` berarti
 * menambahnya di sini juga.
 *
 * TIDAK DITERJEMAHKAN KE BAHASA INDONESIA. 'standby' adalah 'Standby', bukan
 * 'Siaga'; 'run' adalah 'Run', bukan 'Jalan'. Layar laporan web dan mobile
 * sempat mencetak padanan Indonesia untuk kolom-kolom semacam ini — satu-satunya
 * tempat di produk yang melakukannya — dan seluruhnya diselaraskan kembali ke
 * label server pada 2026-10-14 atas keputusan pemilik produk.
 *
 * Isinya ketiga nilai yang DAPAT disimpan kedua kolom enum stasiun ini
 * (diesel_gen_1_status dan diesel_gen_2_status, keduanya
 * enum['run','standby','off']). Nilai di luar itu dipulangkan APA ADANYA lewat
 * jalur mundur di statusValueLabel() — lihat alasannya di sana.
 */
export const STATUS_VALUE_LABELS: Record<string, string> = {
  run: 'Run',
  standby: 'Standby',
  off: 'Off',
}

/**
 * Label manusiawi satu nilai status, dengan JALUR MUNDUR `map[v] ?? v`.
 *
 * Jalur mundurnya bukan kehati-hatian berlebihan: tanpanya sebuah nilai enum
 * baru dari migrasi kelak akan HILANG dari sebaran tanpa satu test pun
 * memerah, karena view mengiterasi `known_values` dari respons dan server
 * memang menerbitkan nilai di luar himpunan dikenal di belakang daftar itu.
 * Nilai mentah yang tercetak apa adanya terlihat janggal — dan terlihat
 * janggal itulah tujuannya; baris yang hilang tidak terlihat sama sekali.
 *
 * Perilakunya sama persis dengan `Display::option()` di server
 * (`OPTION_LABELS[$raw] ?? $raw`).
 */
export function statusValueLabel(value: string): string {
  return STATUS_VALUE_LABELS[value] ?? value
}

/**
 * Nilai bawaan HANYA dipakai ketika seluruh BLOK tidak ada pada respons (mis.
 * bentuk respons berubah di server), bukan untuk menambal medan yang dikirim
 * null. Periode tanpa data tetap mengirim blok lengkap berisi null/nol, dan
 * blok itulah yang diteruskan.
 *
 * Ditulis sebagai literal — bukan dibangun lewat reduce()/map() — supaya
 * berkas ini tetap bebas dari operasi apa pun atas angka laporan, termasuk
 * yang sekadar terlihat seperti perhitungan.
 */
const EMPTY_COVERAGE: EngineRoomReportCoverage = {
  filled_slots: 0,
  expected_slots: 0,
  coverage_percent: null,
  engine_room_unit_count: 0,
  slots_per_unit_per_day: 0,
  days_in_period: 0,
  days_counted: 0,
  period_running: false,
}

const EMPTY_ENERGY_EXPORT: EngineRoomReportEnergyExport = {
  series: [],
  has_data: false,
}

const EMPTY_DAILY_TREND: EngineRoomReportDailyTrend = {
  electrical_sync_total_factory_load_kw: [],
}

const EMPTY_DAILY_TOTAL: EngineRoomReportDailyTotal = {
  reading_rows: 0,
}

const EMPTY_TOTALS: EngineRoomReportTotals = {
  days_with_records: 0,
  reading_rows: 0,
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
function productionLineParams(scope?: EngineRoomReportScope): Record<string, string> {
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
 * GET /api/engine-room-reports/periods — periode yang mencakup stasiun Engine
 * Room, yakni periode yang punya baris period_stations berjenis 'engine-room'
 * (DENGAN TANDA HUBUNG; server membaca nilainya dari App\Enums\StationType
 * alih-alih menuliskannya, karena garis bawah di situ tidak akan cocok dengan
 * satu baris pun dan menjawab [] untuk setiap mill tanpa satu galat).
 *
 * TANPA business_unit_id — tidak ada jalur kode untuk mengirimkannya. Server
 * menyelesaikan mill dari akun; akun terikat mill yang business_unit_id-nya
 * NULL dijawab 422 dan daftar seluruh mill tidak pernah dibaca.
 *
 * TANPA production_line_id pula — periode milik MILL, bukan milik line.
 *
 * Penyaringannya dikerjakan SERVER; repo meneruskan daftar apa adanya, dalam
 * urutan yang sama — menyaringnya kedua kali di sini akan menciptakan definisi
 * cakupan yang kedua. Daftar kosong adalah jawaban yang sah (HTTP 200 + []),
 * bukan galat. Periode TERTUTUP tetap terdaftar — status mengatur penulisan
 * data, bukan pembacaan laporan.
 */
export async function fetchPeriods(): Promise<EngineRoomReportPeriodOption[]> {
  const response = await apiClient.get('/api/engine-room-reports/periods')

  return (response.data?.data ?? []) as EngineRoomReportPeriodOption[]
}

/**
 * GET /api/engine-room-reports/summary — seluruh angka layar untuk satu
 * periode pada satu Production Line.
 *
 * Setiap blok diteruskan APA ADANYA, tanpa satu operasi aritmetika pun: tidak
 * ada penurunan coverage_percent dari filled_slots/expected_slots, tidak ada
 * perata-rataan ulang min/avg/max, tidak ada penurunan expected_slots dari
 * unit × hari × 24 meski ketiga angkanya tersedia, tidak ada penurunan porsi
 * sebaran dari counts/recorded_slots, tidak ada pemilihan ulang nilai TERAKHIR
 * pada seri energi (tidak ada sort, tidak ada max, tidak ada perbandingan jam),
 * tidak ada pengelompokan kejadian teks bebas menurut teksnya, tidak ada
 * pengurutan ulang metrics / by_unit / daily / kedua daftar teks, dan TIDAK ADA
 * peta satuan yang mengubah 'C' menjadi '°C'. Nilai bawaan di atas hanya
 * berlaku bila BLOK-nya tidak ada sama sekali pada respons.
 */
export async function fetchSummary(
  periodId: string,
  scope?: EngineRoomReportScope,
): Promise<EngineRoomReportSummary> {
  const response = await apiClient.get('/api/engine-room-reports/summary', {
    params: { period_id: periodId, ...productionLineParams(scope) },
  })

  const body = (unwrap<Record<string, unknown>>(response.data) ?? {}) as Record<string, unknown>

  return {
    business_unit: (body.business_unit ?? null) as EngineRoomReportBusinessUnitRef | null,
    production_line: (body.production_line ?? null) as EngineRoomReportProductionLineRef | null,
    period: (body.period ?? null) as EngineRoomReportPeriodHeader | null,
    has_data: (body.has_data ?? false) as boolean,
    coverage: (body.coverage ?? { ...EMPTY_COVERAGE }) as EngineRoomReportCoverage,
    metrics: (body.metrics ?? {}) as Record<string, EngineRoomReportMetric>,
    daily_energy_export: (body.daily_energy_export ??
      { ...EMPTY_ENERGY_EXPORT }) as EngineRoomReportEnergyExport,
    status_distribution: (body.status_distribution ?? {}) as Record<
      string,
      EngineRoomReportStatusDistribution
    >,
    maintenance_remarks: (body.maintenance_remarks ?? []) as EngineRoomReportTextEntry[],
    findings: (body.findings ?? []) as EngineRoomReportTextEntry[],
    daily_trend: (body.daily_trend ?? { ...EMPTY_DAILY_TREND }) as EngineRoomReportDailyTrend,
    by_unit: (body.by_unit ?? []) as EngineRoomReportUnitRow[],
    daily: (body.daily ?? []) as EngineRoomReportDailyRow[],
    daily_total: (body.daily_total ?? { ...EMPTY_DAILY_TOTAL }) as EngineRoomReportDailyTotal,
    total: (body.total ?? { ...EMPTY_TOTALS }) as EngineRoomReportTotals,
  }
}

/**
 * GET /api/engine-room-reports/export?period_id=...&production_line_id=...&format=csv
 * — respons streaming text/csv, BUKAN JSON, jadi responseType-nya blob.
 *
 * Isinya (SATU BARIS PER SLOT WAKTU, dengan konteks Periode/Mill/Production
 * Line, tanggal, unit Engine Room, status, dan catatan diulang verbatim di
 * setiap baris, lalu ke-27 kolom bacaan) dibentuk SERVER dan sama persis
 * dengan ekspor laporan versi web, karena endpoint-nya memang sama. Kedua
 * kolom status genset ditulis memakai ExportValue::option() — Run / Standby /
 * Off — sehingga identik dengan layar Detail dan dengan berkas ekspor web.
 * Slot yang seluruh kolom ukurnya kosong TETAP satu baris, dengan sel kosong.
 *
 * FORMAT 'csv' SAJA, DAN TIDAK ADA JALUR KODE YANG DAPAT MENYUSUN FORMAT LAIN.
 * Endpoint-nya memang mendukung 'excel', tetapi WebView ponsel tidak membuka
 * .xlsx — menyediakan tombol yang menghasilkan berkas yang tidak dapat dibuka
 * lebih buruk daripada tidak menyediakannya, jadi tombolnya pun tidak dirender
 * dan nilai 'excel' tidak muncul di berkas ini maupun di view.
 *
 * 422 EXPORT_FAILED (lebih dari 50.000 baris slot) diteruskan apa adanya ke
 * view, yang menampilkan pesan server tanpa mencoba memotong, memecah, atau
 * mengulang permintaan dengan rentang lebih kecil.
 */
export async function exportCsv(
  periodId: string,
  scope?: EngineRoomReportScope,
): Promise<Blob> {
  const response = await apiClient.get('/api/engine-room-reports/export', {
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
 * object URL, konvensi web standar yang juga berlaku di WebView Capacitor —
 * pola yang sama dengan dua belas kembaran mobile sebelumnya, tidak ada
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

export const engineRoomReportRepo = {
  fetchPeriods,
  fetchSummary,
  exportCsv,
  saveCsvFile,
  statusValueLabel,
  STATUS_VALUE_LABELS,
}

export default engineRoomReportRepo
