<script setup lang="ts">
/**
 * LaporanKernelDispatchView — screen-165--laporan-kernel-dispatch-mobile /
 * usecase-171--laporan-kernel-dispatch-mobile "Lihat Laporan Periode Kernel
 * Dispatch (Mobile)" (dipasang di /reports/kernel-dispatch, nama rute
 * `report-kernel-dispatch`, meta.public = false).
 * Aktor: Station Operator, Supervisor, Mill Management, Admin.
 *
 * Kembaran mobile KEENAM BELAS, dan BACAAN SAJA: layar ini tidak punya satu
 * pun jalur yang mengubah data stasiun, dan tidak menulis ke localDb.
 *
 * ────────────────────────────────────────────────────────────────────────
 * NOL PERUBAHAN BACKEND — DIBAYAR LEBIH DULU, DENGAN SENGAJA
 * ────────────────────────────────────────────────────────────────────────
 * Ketiga rute data /api/kernel-dispatch-reports/* SUDAH menerima Operator
 * sejak commit pertama screen-164 — justru supaya layar ini tidak perlu
 * melebarkan akses apa pun — dan /api/production-lines/options-for-report
 * dipakai ulang VERBATIM dari screen-135. Tidak satu berkas di backend/
 * disentuh untuk layar ini, dan tidak ada entri api-index baru. Bila sebuah
 * test backend memerah, sebabnya ada di tempat lain. Konsekuensi lain yang
 * disengaja: TIDAK ADA integration test untuk layar ini — bukan karena
 * dilewatkan, melainkan karena tidak ada kontrak baru untuk diuji.
 *
 * ────────────────────────────────────────────────────────────────────────
 * HIBRIDA: TRANSAKSI DAN MUTU DALAM SATU LAYAR — PEMBEDA DARI screen-163
 * ────────────────────────────────────────────────────────────────────────
 * Satu baris detail adalah SATU PENGIRIMAN: satu kendaraan yang berangkat
 * membawa inti ke pembeli. Sampai di situ bentuknya sama dengan Solid Waste
 * Disposal (screen-163). Yang BARU di sini adalah KARTU MUTU: empat ukuran
 * min/avg/max, MASING-MASING DENGAN PENYEBUTNYA SENDIRI — bentuk yang datang
 * dari Process Water (screen-161), tempat ukurannya per slot jam; di sini per
 * pengiriman. Rumusnya sama, satuannya berbeda, dan menyalin dari SATU
 * preseden saja akan kehilangan separuh layar ini.
 *
 * KARTU TONASE DAN KARTU MUTU BERURUTAN DI DOM, TANPA BAGIAN LAIN DI ANTARANYA,
 * DAN KEDUANYA TIDAK DAPAT DILIPAT. Di web keduanya berada di dalam SATU kotak
 * berdampingan; pada lebar 390px itu tidak mungkin, jadi yang dipertahankan
 * adalah MAKSUDNYA: tonase tinggi dengan kadar air tinggi adalah kabar buruk
 * yang menyamar sebagai kabar baik, sehingga keduanya harus terbaca dalam satu
 * tarikan tanpa tindakan pengguna. Rekap boleh dilipat; angka utama tidak.
 *
 * KONSEKUENSI TATA LETAK YANG PERLU DINYATAKAN: kartu "Penimbangan Belum
 * Selesai" di web duduk sebagai kartu KEEMPAT di antara tonase dan mutu. Di
 * sini ia dipindah ke SESUDAH kartu mutu, karena kedua aturan di atas tidak
 * dapat dipenuhi sekaligus dengan menempatkannya di tengah — dan yang
 * diprioritaskan adalah kedekatan tonase–mutu, yang merupakan kontrak layar
 * ini. Isinya, angkanya, dan kalimatnya tetap sama persis dengan web;
 * pembacanya menemukan caveat itu tepat sesudah kedua angka yang ia bantah.
 *
 * ────────────────────────────────────────────────────────────────────────
 * LAYAR INI TIDAK MENGHITUNG SATU AGREGAT PUN, DAN ITU BENTUKNYA
 * ────────────────────────────────────────────────────────────────────────
 * Angka mobile WAJIB sama persis dengan angka web (screen-164), dan yang
 * menjaminnya BUKAN disiplin melainkan BENTUK: layar ini membaca /summary dan
 * MEMILIH field untuk ditampilkan. Tidak ada penjumlahan, tidak ada
 * perata-rataan, tidak ada pembagian, tidak ada min/max, tidak ada pengurutan,
 * tidak ada pengelompokan, dan tidak ada pembulatan ulang di berkas ini.
 *
 * DI LAYAR INI BAHAYANYA LEBIH BESAR DARIPADA DI MANA PUN: min/avg/max tampak
 * mudah dihitung ulang karena ketiganya terasa sudah ada di tangan. Setiap
 * kali klien menghitung sendiri, ia menjadi rumus KEDUA yang dapat menyimpang
 * tanpa ada yang membandingkannya.
 *
 * Konsekuensi yang terlihat di kode: tidak ada satu pun `reduce`, `sort`,
 * `Math.min`, `Math.max`, atau operator `/` yang menyentuh angka laporan di
 * seluruh berkas ini. `daily_total` dibaca dari payload — BUKAN dijumlahkan
 * dari `daily[]`; `missing_net_weight_dispatch_count` dibaca — BUKAN
 * diturunkan dari `dispatch_count − net_weight_dispatch_count`;
 * `completeness.percent` dibaca — BUKAN dihitung dari
 * `days_with_dispatch / days_counted`; `not_released_count` dibaca — BUKAN
 * dihitung ulang dari `by_gate_status`; dan keempat `measured_count` dibaca
 * apa adanya, tidak satu pun diturunkan dari yang lain.
 *
 * SATU ANGKA WEB YANG SENGAJA TIDAK DISALIN: blade screen-164 punya kartu
 * "Status Gerbang Tercatat" yang nilainya ia hitung sendiri
 * (dispatch_count − baris null). Itu aritmetika klien, dan di sini ia TIDAK
 * dirender sama sekali — angkanya dapat dibaca langsung dari baris 'tidak
 * tercatat' pada rekapnya, yang memang ditampilkan.
 *
 * ────────────────────────────────────────────────────────────────────────
 * EMPAT UKURAN, EMPAT PENYEBUT, DAN PENJAGA '—' PER UKURAN
 * ────────────────────────────────────────────────────────────────────────
 * Keempat kolom mutu nullable dan INDEPENDEN: satu pengiriman boleh punya
 * hasil uji kadar air tanpa ALB. Karena itu setiap kartu mencetak
 * `measured_count`-NYA SENDIRI, dan `v-if` penjaga '—' ADA DI TINGKAT UKURAN,
 * BUKAN DI TINGKAT KARTU. Satu `v-if="adaDataMutu"` di pembungkus kartu terasa
 * rapi dan akan MENYEMBUNYIKAN TIGA UKURAN YANG PUNYA DATA ketika satu ukuran
 * kosong — kesalahan yang sama yang diperingatkan di screen-164, dan di klien
 * ia lebih mudah dibuat. Daftar ukurannya karena itu di-LOOP dari satu
 * konstanta, bukan ditulis empat kali: empat salinan adalah empat kesempatan
 * kehilangan penyebut atau mewarisi penyebut kartu lain.
 *
 * ────────────────────────────────────────────────────────────────────────
 * LABEL STATUS GERBANG DIDELEGASIKAN, TIDAK DISALIN
 * ────────────────────────────────────────────────────────────────────────
 * `by_gate_status` adalah SATU-SATUNYA kolom berpilihan TERTUTUP di stasiun
 * ini, dan payload membawa KODE MENTAHNYA. Labelnya diturunkan lewat
 * `optionLabel()` dari '@/utils/optionLabel' — cermin mobile dari
 * App\Support\Display::OPTION_LABELS, yang dijaga tests/optionLabel.spec.ts
 * dengan MEMBACA Display.php dari disk lalu menuntut kedua peta identik.
 * Berkas ini karena itu TIDAK punya peta nilai→label sendiri: peta kedua di
 * dalam sebuah komponen dapat menyimpang dari backend tanpa ada yang
 * membandingkannya, sebab penjaga itu hanya melihat `OPTION_LABELS`. Aturan
 * rumah 'delegasikan, jangan salin' berlaku di sisi mobile juga.
 *
 * Nilai yang TIDAK dikenal dirender APA ADANYA — `optionLabel()` jatuh ke
 * nilai mentah, persis seperti `Display::option()` di server. Dan nilai
 * semacam itu TIDAK DIBUANG, TIDAK DIPAKSA MASUK PILIHAN, dan TIDAK DILIPAT ke
 * baris 'tidak tercatat': ia TERCATAT, hanya tidak dikenal. Membuangnya akan
 * memecah penjumlahan dispatch_count rekap itu terhadap dispatch_count di
 * atasnya.
 *
 * 'TIDAK TERCATAT' BUKAN 'TIDAK DILEPAS'. `not_released_count` menghitung
 * HANYA 'not_released' persis dan datang dari server; baris status yang kosong
 * punya barisnya sendiri di rekap dan TIDAK ikut angka itu. Menggabungkannya
 * berarti MENUDUH pengiriman yang sekadar belum diisi statusnya, dan tuduhan
 * itu sampai ke orang sungguhan.
 *
 * ────────────────────────────────────────────────────────────────────────
 * URUTAN BARIS MILIK SERVER
 * ────────────────────────────────────────────────────────────────────────
 * Keempat rekap sudah diurutkan server (total netto DESC → jumlah pengiriman
 * DESC → nilai ASC) dan rekap harian urut tanggal naik. KLIEN TIDAK
 * MENGURUTKAN ULANG: mengurutkan ulang akan membuat baris yang sama tampil
 * berbeda di dua platform untuk periode yang sama. Akibat yang paling mudah
 * disalahpahami: baris 'tidak tercatat' yang bertonase besar MUNCUL DI ATAS,
 * bukan terkubur di baris terakhir — dan itu benar, karena 300 MT yang tidak
 * diketahui pembelinya adalah justru baris yang paling perlu dibaca. Pemetaan
 * null → 'tidak tercatat' dan pemetaan kode gate status → labelnya adalah DUA
 * SATU-SATUNYA transformasi tampilan yang diizinkan pada rekap, dan keduanya
 * TIDAK memindahkan barisnya.
 *
 * ────────────────────────────────────────────────────────────────────────
 * NULL DICETAK '—' PER MEDAN, BUKAN LEWAT SATU BAWAAN
 * ────────────────────────────────────────────────────────────────────────
 * Diperiksa PER FIELD lewat formatWeight()/formatCount()/formatPercent()/
 * formatQuality(), bukan lewat sebuah default `?? 0` di satu tempat — default
 * seperti itu akan mengubah "tidak pernah diuji" menjadi "hasilnya nol" di
 * SELURUH layar sekaligus. Nol yang sungguhan tetap dicetak 0 (ketiga
 * penghitung kepatuhan, penyebut netto yang nol, dan ukuran mutu yang
 * benar-benar terukur nol adalah contohnya), dan penjaganya memeriksa `null` —
 * BUKAN kebenaran nilai, karena falsiness JavaScript menukar 0 dengan 'tidak
 * ada'.
 *
 * ANGKA DIRENDER TANPA SATUAN DI DALAM ELEMEN BER-TESTID-NYA: satuan 'MT' dan
 * tanda '%' hidup di elemen SEBELAHNYA. Dengan begitu elemen angka dapat
 * diasersi dengan kesetaraan PENUH ('37,5', '—') tanpa ikut memindai label di
 * sekitarnya — dan '—' tidak pernah berdiri di samping satuan yang tidak ada
 * angkanya.
 *
 * DESIMAL MUTU TIGA, BUKAN DUA, mengikuti pembulatan server
 * (KernelDispatchReportService::QUALITY_DECIMALS): kadar kotoran bergerak di
 * orde perseratus persen, dan dua desimal akan melipat dua nilai yang berbeda
 * menjadi satu angka yang sama. Pembulatannya SUDAH dikerjakan server; layar
 * ini hanya memilih berapa angka di belakang koma yang dicetak, dan tidak
 * pernah membulatkan ulang.
 *
 * ────────────────────────────────────────────────────────────────────────
 * PROSA SERVER DIRENDER UTUH
 * ────────────────────────────────────────────────────────────────────────
 * `quality._note`, `compliance._note`, `_note_no_hourly`,
 * `_note_no_quality_thresholds` dan `_note_shift_not_published` dirender
 * VERBATIM di elemen TERSENDIRI, tanpa satu kata tambahan di dalamnya, tanpa
 * elipsis, tanpa line-clamp, dan tanpa 'baca selengkapnya'. Kalimat-kalimat
 * itu disusun SERVER justru supaya penjelasan yang sama muncul di web dan di
 * mobile; memparafrasekannya di klien berarti membuat dua penjelasan untuk
 * satu keadaan.
 *
 * KONSEKUENSI UJI: `_note_no_hourly` MEMUAT kata 'jam' DAN menyebut CPO
 * Dispatch, dan `_note_no_quality_thresholds` memuat kata 'batas' — keduanya
 * dirender utuh. Jadi ketiadaan blok per jam, ketiadaan waktu putar kendaraan,
 * dan ketiadaan penandaan di luar batas HANYA dapat diasersikan lewat TESTID,
 * NAMA KUNCI PAYLOAD dan ROLE, tidak pernah lewat sapuan teks halaman. Tidak
 * ada satu pun testid `kd-hourly`, `kd-hourly-distribution`, `kd-peak-hour`,
 * `kd-turnaround`, `kd-quality-threshold-badge`, maupun `kd-shift` di berkas
 * ini, dan tidak ada JUDUL yang memuat 'per jam'.
 *
 * SATU DAFTAR TEKS, BUKAN DUA: stasiun ini TIDAK punya kolom `remarks`.
 * Berbeda dari screen-163, tidak ada bagian `kd-remarks` di sini, dan
 * ketiadaannya dinyatakan di dalam bagian Rekap Temuan supaya tidak terbaca
 * sebagai bagian yang lupa dibuat.
 *
 * ────────────────────────────────────────────────────────────────────────
 * YANG DIWARISI DARI SERI, DINYATAKAN KARENA MENANGGUNG BEBAN
 * ────────────────────────────────────────────────────────────────────────
 * PRODUCTION LINE WAJIB, DAN PENJAGAANNYA ADA DI FUNGSI loadSummary() — bukan
 * di template. Template yang hanya menyembunyikan angka tetap membiarkan
 * permintaannya terkirim; KETIGA jalur (muat awal, ganti periode, Coba Lagi)
 * memanggil fungsi yang SAMA, jadi satu penjagaan melindungi ketiganya dan
 * tidak ada jalur yang dapat melewatinya.
 *
 * URUTAN PENENTUAN LINE DUA LANGKAH: rute → ingatan perangkat → minta pengguna
 * memilih. TIDAK ADA "pilih line pertama" dan TIDAK ADA "pilih satu-satunya
 * line": memilihkan line secara diam-diam menerbitkan angka yang pengguna
 * tidak pernah minta, dan ketika line kedua ditambahkan kemudian, kebiasaan
 * itu berubah tanpa pemberitahuan. Pemilih line karena itu DIRENDER walau
 * daftarnya hanya berisi satu entri.
 *
 * Ingatan line dibaca dari localStorage dengan kunci `msl_production_line_
 * {userId}` — KUNCI YANG SAMA PERSIS yang ditulis StationListView.vue dan yang
 * sudah dibaca ReportingPilihStasiunView.vue. Kunci yang berbeda akan
 * terkompilasi, terlihat benar, dan tidak pernah cocok. Pembacaannya dibungkus
 * try/catch mengikuti pola floatingClock.ts: pada mode privat penyimpanan
 * dapat MELEMPAR (bukan memulangkan null), dan itu tidak boleh mematahkan
 * layar — cukup jatuh ke keadaan "belum ada line terpilih", tanpa satu pesan
 * galat pun.
 *
 * KLIEN TIDAK MEMVALIDASI KEPEMILIKAN LINE. Line basi yang masih diingat
 * perangkat tetap dikirim, dan 403 dari server diperlakukan sebagai "pilih
 * line lagi" — bukan galat jaringan — dan angka yang sudah ada DIBERSIHKAN,
 * sehingga tidak satu angka pun dari mill lain pernah dirender.
 *
 * ADMIN ADALAH SATU-SATUNYA PERAN YANG MELIHAT PEMILIH MILL. Ketiga peran lain
 * terikat business_unit_id akunnya, `business_unit_id` tidak pernah masuk
 * query string untuk mereka, dan /business-units/options TIDAK PERNAH
 * dipanggil atas nama mereka — selain percuma (server menjawab 403),
 * permintaan itu akan membentuk daftar SELURUH mill di perangkat orang yang
 * tidak berhak melihat satu pun di antaranya.
 *
 * 401 MENGARAHKAN KE LOGIN TANPA PESAN JARINGAN; 403 meminta memilih line
 * lagi; 422 karena akun tanpa mill menampilkan arahan menghubungi Admin; galat
 * lain menampilkan keadaan galat dengan tombol Coba Lagi. Menyeragamkan
 * keempatnya menjadi satu "galat jaringan" akan mengirim pengguna mencari
 * masalah di tempat yang salah.
 *
 * EKSPOR HANYA CSV, dan tombol Excel TIDAK DIRENDER SAMA SEKALI — bukan karena
 * endpoint tidak mendukungnya (ia mendukung {csv, xlsx}), melainkan karena
 * berkas .xlsx di perangkat lapangan tidak punya pembaca yang dapat
 * diandalkan, sementara CSV dapat dibuka di mana pun dan dikirim lewat pesan.
 * `format=csv` dikirim EKSPLISIT, tidak ada jalur kode di layar ini yang dapat
 * menyusun format lain, dan tombolnya dinonaktifkan selama permintaan berjalan
 * supaya ketukan ganda mengirim SATU permintaan.
 *
 * LAYAR INI PERLU JARINGAN. Ia tidak menyimpan hasil laporan ke perangkat dan
 * tidak punya mode luring: keadaan luring DINYATAKAN apa adanya dan NOL angka
 * dirender bersamanya — angka basi tanpa penanda adalah kegagalan yang lebih
 * buruk daripada layar yang berterus terang kosong. Bootstrap BERHENTI SEBELUM
 * PERMINTAAN PERTAMA ketika `navigator.onLine === false`, dan kedua listener
 * `online`/`offline` DILEPAS pada onUnmounted — wrapper yang bocor menghitung
 * ganda permintaan bootstrap pada test berikutnya, dan kegagalannya akan
 * tampak seperti bug di layar.
 *
 * HALAMAN TIDAK PERNAH MENGGULIR MENDATAR PADA 390px. Kelima tabel rekap
 * BERTUMPUK menjadi kartu lewat v-stacked-table + src/assets/stacked-table.css
 * (temuan QA LP-M-02/LP-M-03), jadi tidak ada satu pun isi yang melebihi
 * induknya di lebar perangkat; di jendela lebar (>640px) tabelnya kembali
 * menjadi tabel sungguhan yang menggulir mendatar DI DALAM .detail-table-wrap
 * miliknya sendiri. Kartu angka BERTUMPUK satu kolom, dan keempat ukuran mutu
 * bertumpuk DI DALAM kartunya dengan tiga angka serta penyebutnya tetap
 * terbaca.
 *
 * KONTROL LIPAT DITULIS DI SINI, BUKAN MEMAKAI CollapsibleSection: komponen
 * bersama itu menyembunyikan isinya dengan `v-show` (isinya tetap di DOM) dan
 * memberi tombolnya satu testid generik, sementara layar ini menuntut dua hal
 * yang tidak dapat dipenuhinya — tiap rekap punya kontrol lipat ber-TESTID
 * SENDIRI, dan melipatnya benar-benar MENGELUARKAN tabelnya dari DOM. Yang
 * dilipat tetap MURNI PENYINGKAPAN: datanya sudah ada di memori, tidak satu
 * permintaan pun dipicu, dan tidak satu angka pun berubah. Bawaannya TERBUKA,
 * sama dengan `<details open>` di web.
 *
 * DISIPLIN KELAS CSS: tiap kelas yang dipakai markup punya definisi di blok
 * <style> di bawah atau di stylesheet bersama yang dirujuknya
 * (src/assets/stacked-table.css untuk .detail-table / .num). Kelas tanpa
 * definisi tidak memerahkan satu test perilaku pun — ia hanya menjawab
 * 'visible' ketika gaya yang diharapkan ditanyakan, lalu muncul sebagai
 * tampilan yang rusak.
 */
import { computed, onMounted, onUnmounted, ref } from 'vue'
import FilterPanel from '@/components/filters/FilterPanel.vue'
import LoadingState from '@/components/loading/LoadingState.vue'
import { createLatestRequestGuard } from '@/utils/latestRequest'
import BusyLabel from '@/components/loading/BusyLabel.vue'
import FilterSelectField from '@/components/filters/FilterSelectField.vue'
import FilterChip from '@/components/filters/FilterChip.vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { type ProductionLineOption } from '@/services/productionLineRepo'
import { useFloatingClockStore } from '@/stores/floatingClock'
import { useAiAssistantStore } from '@/stores/aiAssistant'
import StatusBadge from '@/components/StatusBadge.vue'
// KOSAKATA BERSAMA, BUKAN SALINAN. Lihat docblock berkas: peta label hidup di
// satu tempat dan dijaga terhadap backend; berkas ini hanya memanggilnya.
import { optionLabel } from '@/utils/optionLabel'
import kernelDispatchReportRepo, {
  type KernelDispatchReportBreakdownRow,
  type KernelDispatchReportBusinessUnitOption,
  type KernelDispatchReportCompleteness,
  type KernelDispatchReportCompliance,
  type KernelDispatchReportDailyRow,
  type KernelDispatchReportDailyTotal,
  type KernelDispatchReportDispatches,
  type KernelDispatchReportPeriodOption,
  type KernelDispatchReportQuality,
  type KernelDispatchReportQualityKey,
  type KernelDispatchReportQualityMeasure,
  type KernelDispatchReportSummary,
  type KernelDispatchReportTextEntry,
} from '@/services/kernelDispatchReportRepo'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const floatingClockStore = useFloatingClockStore()
const aiAssistantStore = useAiAssistantStore()

/* ------------------------------------------------------------------ */
/* Peran & mill                                                        */
/* ------------------------------------------------------------------ */

const role = computed(() => authStore.currentUser?.role ?? null)

/**
 * SATU-SATUNYA peran yang tidak terikat mill, dan satu-satunya yang melihat
 * pemilih Mill. Operator, Supervisor dan Mill Management ada di cabang
 * terikat-mill — persis seperti
 * KernelDispatchReportService::resolveBusinessUnit().
 */
const isAdmin = computed(() => role.value === 'admin')

/** Mill akun — hanya bermakna bagi peran yang terikat satu mill. */
const accountBusinessUnitId = computed(() => authStore.currentUser?.business_unit_id ?? null)

/** Mill pilihan Admin. Selalu null bagi ketiga peran lain. */
const selectedBusinessUnitId = ref<string | null>(null)

const businessUnits = ref<KernelDispatchReportBusinessUnitOption[]>([])

/**
 * Akun terikat mill tetapi business_unit_id-nya kosong — DUA jalur, SATU
 * keadaan, dan karena itu satu testid: (a) pra-penerbangan, layar berhenti
 * total tanpa satu pun permintaan HTTP; (b) 422 dari /periods atau /summary
 * dengan pesan server 'Akun Anda belum terhubung ke mill. Hubungi Admin.'
 * Keduanya menuntut tindakan yang sama — menghubungi Admin — dan tidak satu
 * pun di antaranya adalah galat jaringan atau permintaan hak akses. Daftar
 * seluruh mill TIDAK pernah ditawarkan sebagai jalan keluar.
 */
const noMillForAccount = ref(false)

/**
 * Endpoint options menjawab 403 — pemilih Mill tidak dirender sama sekali.
 * Keadaan NORMAL bagi ketiga peran terikat mill, yang memang tidak pernah
 * memanggil endpoint itu dari sini; penanda ini ada untuk Admin yang
 * kewenangannya berubah di tengah sesi.
 */
const millPickerForbidden = ref(false)

/** Admin yang belum memilih mill: tidak ada angka, hanya arahan memilih. */
const millRequired = computed(() => isAdmin.value && !selectedBusinessUnitId.value)

/* ------------------------------------------------------------------ */
/* Production Line — KONTEKS YANG DIPILIH, BUKAN IKATAN AKUN           */
/* ------------------------------------------------------------------ */

const productionLines = ref<ProductionLineOption[]>([])
const selectedProductionLineId = ref<string | null>(null)
const loadingProductionLines = ref(false)

/** Pengambilan daftar line gagal (jaringan/server) — bukan "mill tanpa line". */
const productionLineFetchFailed = ref(false)

const activeProductionLine = computed<ProductionLineOption | null>(
  () => productionLines.value.find((line) => line.id === selectedProductionLineId.value) ?? null,
)

/**
 * Nama line yang menyertai angka yang sedang tampil. Nilai dari SERVER
 * (`summary.production_line`) didahulukan: itulah line yang benar-benar
 * dipakai saat menghitung, sedangkan daftar lokal hanyalah cadangan untuk
 * keadaan sebelum ringkasan pertama tiba.
 */
const activeProductionLineName = computed(
  () => summary.value?.production_line?.name || activeProductionLine.value?.name || '',
)

/** Ada line yang dapat dipilih, tetapi belum ada yang dipilih. */
const productionLineRequired = computed(
  () =>
    !noMillForAccount.value &&
    !millRequired.value &&
    productionLines.value.length > 0 &&
    !selectedProductionLineId.value,
)

/** Tidak ada satu pun line yang dapat dipilih di layar ini. */
const productionLineBlocked = computed(
  () =>
    !noMillForAccount.value &&
    !millRequired.value &&
    !loadingProductionLines.value &&
    productionLines.value.length === 0,
)

/**
 * DUA KEADAAN YANG MENUNTUT TINDAKAN BERBEDA, jadi dua pesan berbeda — dan
 * hanya yang kedua punya tombol Coba Lagi. Menyatukan keduanya akan menyuruh
 * pengguna menelepon Admin untuk masalah jaringan, atau sebaliknya menyuruhnya
 * mencoba lagi atas sesuatu yang tidak akan pernah berubah dengan mencoba
 * lagi.
 */
const productionLineNotice = computed(() => {
  if (productionLineFetchFailed.value) {
    return (
      'Daftar Production Line tidak dapat dimuat, sehingga laporan belum dapat menampilkan angka. ' +
      'Periksa koneksi jaringan Anda, lalu coba lagi.'
    )
  }

  return 'Mill ini belum memiliki Production Line, sehingga belum ada angka yang dapat dilaporkan. Silakan hubungi Admin.'
})

/**
 * Kunci ingatan dimuati id PENGGUNA: sebuah Production Line milik satu mill,
 * jadi pilihan pengguna lain tidak boleh terbawa setelah ganti akun di
 * perangkat yang sama. SAMA PERSIS dengan StationListView.vue.
 *
 * Tanpa pengguna yang sedang masuk kuncinya tidak dapat dibentuk, dan
 * penyimpanan TIDAK dibaca sama sekali.
 */
function rememberedProductionLineKey(): string | null {
  const userId = authStore.currentUser?.id

  return userId ? `msl_production_line_${userId}` : null
}

/**
 * localStorage dibungkus try/catch mengikuti pola floatingClock.ts: pada mode
 * privat penyimpanan bisa MELEMPAR saat diakses — bukan memulangkan null — dan
 * gagal mengingat pilihan tidak boleh mematahkan layar. Kegagalannya
 * diperlakukan sebagai "tidak ada ingatan", dan TIDAK ADA satu pun pesan galat
 * yang dirender karenanya.
 */
function readRememberedProductionLineId(): string | null {
  const key = rememberedProductionLineKey()

  if (!key) {
    return null
  }

  try {
    return window.localStorage.getItem(key)
  } catch {
    return null
  }
}

function rememberProductionLineId(lineId: string): void {
  const key = rememberedProductionLineKey()

  if (!key) {
    return
  }

  try {
    window.localStorage.setItem(key, lineId)
  } catch {
    // Pilihan tetap berlaku untuk sesi ini, hanya tidak bertahan.
  }
}

function routeProductionLineId(): string | null {
  const raw = route.query.production_line_id

  const value = Array.isArray(raw) ? raw[0] : raw

  return typeof value === 'string' && value !== '' ? value : null
}

/**
 * DUA langkah, dan langkah ketiga adalah "tidak ada line terpilih" — bukan
 * sebuah pilihan. Nilai dari rute dan dari ingatan perangkat TIDAK PERNAH
 * dipercaya begitu saja: keduanya hanya dipakai bila masih ada di daftar line
 * yang baru diambil dari server, sehingga line yang sudah dihapus dibuang
 * alih-alih dipakai diam-diam. Kepemilikan line tidak pernah diperiksa di
 * klien — itu urusan server, yang menjawab 403.
 */
function resolveProductionLine(lines: ProductionLineOption[], useRemembered = true): void {
  const fromRoute = lines.find((line) => line.id === routeProductionLineId())
  const remembered = useRemembered
    ? lines.find((line) => line.id === readRememberedProductionLineId())
    : undefined

  // TIDAK ADA `?? lines[0]` DAN TIDAK ADA `lines.length === 1 ? lines[0]` di
  // sini, dan ketiadaannya disengaja — lihat docblock berkas.
  const resolved = fromRoute ?? remembered ?? null

  selectedProductionLineId.value = resolved?.id ?? null

  if (resolved) {
    // Ditulis kembali supaya pilihan yang dibawa screen-141 ikut menjadi
    // ingatan — layar Daftar Stasiun dan layar laporan berbagi satu konteks.
    rememberProductionLineId(resolved.id)
  }
}

/**
 * Daftar line diambil lewat kernelDispatchReportRepo.fetchLines(), yang
 * MENDELEGASIKAN ke productionLineRepo.fetchProductionLinesForReport() —
 * endpoint screen-135 yang dipakai ulang verbatim dan tidak diduplikasi di
 * bawah prefix kernel-dispatch-reports. business_unit_id ikut HANYA untuk
 * Admin.
 */
async function loadProductionLines(useRemembered = true): Promise<void> {
  loadingProductionLines.value = true
  productionLineFetchFailed.value = false

  try {
    const lines = (await kernelDispatchReportRepo.fetchLines(scope.value)) ?? []

    productionLines.value = lines
    resolveProductionLine(lines, useRemembered)
  } catch (error) {
    // 401 di sini tetap berarti sesi berakhir, jadi ia DITERUSKAN ke
    // handleError() yang mengarahkan ke Login TANPA pesan jaringan. Galat
    // lainnya BUKAN kegagalan laporan: ia tidak boleh menyamar sebagai galat
    // ringkasan, jadi ia mengisi state-nya sendiri dengan tombol Coba
    // Lagi-nya sendiri.
    productionLines.value = []
    selectedProductionLineId.value = null

    if (statusOf(error) === 401) {
      handleError(error, null)

      return
    }

    productionLineFetchFailed.value = true
  } finally {
    loadingProductionLines.value = false
  }
}

async function onProductionLineChange(): Promise<void> {
  clearErrors()
  resetSummary()

  if (!selectedProductionLineId.value) {
    return
  }

  rememberProductionLineId(selectedProductionLineId.value)

  await loadSummary()
}

/**
 * Cakupan yang dikirim repo. `isAdmin` dinyatakan EKSPLISIT: repo tidak
 * pernah menebak peran, dan business_unit_id karena itu tidak dapat terkirim
 * untuk peran yang terikat mill.
 */
const scope = computed(() => ({
  isAdmin: isAdmin.value,
  businessUnitId: isAdmin.value ? selectedBusinessUnitId.value : null,
  productionLineId: selectedProductionLineId.value,
}))

/**
 * Nama mill yang sedang berlaku, sebagai KETERANGAN bagi peran terikat mill.
 * Dibaca dari summary.business_unit.name; respons /periods TIDAK memuat nama
 * mill sama sekali, dan membacanya dari sana menghasilkan undefined yang ikut
 * dirender (koreksi yang sudah ditemukan di screen-155).
 */
const currentMillName = computed(
  () => summary.value?.business_unit?.name || authStore.businessUnit?.name || '',
)

/* ------------------------------------------------------------------ */
/* Periode & ringkasan                                                 */
/* ------------------------------------------------------------------ */

const periods = ref<KernelDispatchReportPeriodOption[]>([])
const selectedPeriodId = ref<string | null>(null)
const summary = ref<KernelDispatchReportSummary | null>(null)

const loadingPeriods = ref(false)
/**
 * Daftar periode sudah pernah selesai dimuat (audit loading state
 * 2026-10-05). Tanpa ini "Mill ini belum memiliki periode" tampil selama
 * daftar line/periode MASIH dimuat — daftar kosong saat memuat terbaca sebagai
 * "tidak ada periode".
 */
const periodsLoaded = ref(false)
const loadingSummary = ref(false)
/** Urutan permintaan ringkasan — lihat loadSummary(). */
const summaryRequests = createLatestRequestGuard()
const exporting = ref(false)

/** Daftar periode kosong — arahan menghubungi Admin, bukan pesan galat. */
const noPeriods = computed(
  () =>
    periodsLoaded.value &&
    !loadingPeriods.value &&
    !noMillForAccount.value &&
    !millRequired.value &&
    !networkError.value &&
    !errorMessage.value &&
    periods.value.length === 0,
)

const selectedPeriod = computed(
  () => periods.value.find((item) => item.id === selectedPeriodId.value) ?? null,
)

/* ------------------------------------------------------------------ */
/* Luring — DINYATAKAN, bukan ditutupi angka basi                      */
/* ------------------------------------------------------------------ */

const isOffline = ref(false)

/** Pemuatan awal sudah pernah dijalankan — lihat onOnline(). */
const bootstrapped = ref(false)

function syncOnlineState(): void {
  isOffline.value = typeof navigator !== 'undefined' && navigator.onLine === false
}

/* ------------------------------------------------------------------ */
/* Galat                                                               */
/* ------------------------------------------------------------------ */

const networkError = ref<string | null>(null)
const errorMessage = ref<string | null>(null)

/**
 * Line (atau periode) yang diminta bukan milik mill yang berlaku (403) —
 * termasuk line basi yang masih diingat perangkat. Diperlakukan sebagai "pilih
 * line lagi", BUKAN galat jaringan, dan angka yang sudah ada DIBERSIHKAN.
 */
const productionLineRejected = ref(false)

/** Aksi terakhir yang gagal — itulah yang diulang tombol Coba Lagi. */
const retryAction = ref<(() => Promise<void>) | null>(null)
const canRetry = computed(() => retryAction.value !== null)

/**
 * BENTUK GALAT DATAR. apiClient.normalizeError menolak dengan objek DATAR
 * `{ message, errors?, status? }` dan sudah MEMBUANG `response` di sana — jadi
 * `.response` tidak pernah ada pada galat yang sampai ke sini. Medan itu karena
 * itu tidak ditulis pada antarmuka ini: yang tidak dapat ditulis tidak dapat
 * salah.
 */
interface ErrorLike {
  status?: number
  message?: string
}

/**
 * Cara sebuah 422 ditafsirkan, dan ia WAJIB diputuskan pemanggil karena satu
 * kode HTTP yang sama membawa dua arti yang berbeda di layar ini:
 *
 *   'mill'    -> /periods dan /summary. Satu-satunya 422 yang dapat dicapai
 *                dari kedua jalur itu adalah akun terikat mill yang
 *                users.business_unit_id-nya NULL (atau Admin tanpa mill, yang
 *                tidak pernah mengirim permintaannya karena penjagaan di
 *                fungsi pemuatan): period_id dan production_line_id tidak
 *                pernah dikirim kosong.
 *   'message' -> /export. Di sana 422 berarti parameter wajib absen atau batas
 *                50.000 baris pengiriman terlampaui, dan pesannya ditampilkan
 *                APA ADANYA — layar ini tidak mencoba memotong, memecah, atau
 *                mengulang permintaan dengan rentang lebih kecil.
 */
type Meaning422 = 'mill' | 'message'

function statusOf(error: unknown): number | undefined {
  const candidate = error as ErrorLike | null

  return candidate?.status
}

function messageOf(error: unknown, fallback: string): string {
  const candidate = error as ErrorLike | null

  return candidate?.message || fallback
}

/**
 * Penanganan galat terpusat. URUTAN CABANGNYA MENENTUKAN BENAR/SALAHNYA LAYAR:
 *
 *   401            -> Login, TANPA pesan jaringan dan tanpa tombol Coba Lagi.
 *                     "Periksa koneksi Anda" pada sesi yang berakhir mengirim
 *                     pembaca memeriksa sinyal alih-alih masuk kembali.
 *   tanpa status    -> penolakan tanpa respons = jaringan; periode dan line
 *                     yang sudah terpilih SENGAJA tidak disentuh.
 *   403            -> "pilih line lagi": pilihan line dikosongkan dan angka
 *                     dibersihkan, bukan ditampilkan sebagai galat jaringan.
 *                     Inilah jalur line basi dari ingatan perangkat.
 *   404            -> "periode tidak ditemukan", pilihan periode dikosongkan.
 *   422 tanpa mill -> arahan menghubungi Admin, BUKAN galat jaringan dan BUKAN
 *                     daftar seluruh mill.
 *   sisanya        -> pesan yang dapat dibaca pengguna.
 *
 * SETIAP cabang mengosongkan `summary` — nol angka boleh tertinggal di layar
 * bersama sebuah keadaan galat, pada keempat cabang tanpa kecuali. Di layar
 * ini itu berarti kartu tonase DAN kartu mutu sama-sama hilang dari DOM:
 * angka mill sendiri yang tertinggal di samping pesan 403 akan dibaca sebagai
 * angka yang baru.
 */
function handleError(
  error: unknown,
  retry: (() => Promise<void>) | null,
  meaning422: Meaning422 = 'message',
): void {
  const status = statusOf(error)

  if (status === 401) {
    networkError.value = null
    errorMessage.value = null
    retryAction.value = null
    summary.value = null
    router.push({ name: 'login' })

    return
  }

  if (status === undefined) {
    networkError.value = messageOf(
      error,
      'Gagal memuat laporan. Periksa koneksi jaringan Anda, lalu coba lagi.',
    )
    retryAction.value = retry
    summary.value = null

    return
  }

  if (status === 403) {
    productionLineRejected.value = true
    selectedProductionLineId.value = null
    networkError.value = null
    errorMessage.value = null
    retryAction.value = null
    summary.value = null

    return
  }

  if (status === 404) {
    errorMessage.value = messageOf(
      error,
      'Periode Pelaporan yang dipilih tidak ditemukan. Silakan pilih periode lain.',
    )
    selectedPeriodId.value = null
    retryAction.value = null
    summary.value = null

    return
  }

  if (status === 422 && meaning422 === 'mill') {
    noMillForAccount.value = true
    networkError.value = null
    errorMessage.value = null
    retryAction.value = null
    summary.value = null

    return
  }

  // SISANYA (500 dan kerabatnya) adalah "keadaan galat dengan tombol Coba
  // Lagi", bukan pesan mati: gagal 5xx memang dapat berhasil pada percobaan
  // berikutnya, dan pesan tanpa tombol memaksa pengguna keluar-masuk layar
  // untuk mencoba hal yang sama. Memakai keadaan yang SAMA dengan kegagalan
  // transport disengaja — keduanya menuntut tindakan yang sama.
  networkError.value = messageOf(error, 'Gagal memuat laporan. Silakan coba lagi.')
  retryAction.value = retry
  summary.value = null
}

function clearErrors(): void {
  networkError.value = null
  errorMessage.value = null
  productionLineRejected.value = false
  retryAction.value = null
}

async function onRetry(): Promise<void> {
  const action = retryAction.value

  if (!action) {
    return
  }

  clearErrors()
  await action()
}

/* ------------------------------------------------------------------ */
/* Pemuatan                                                            */
/* ------------------------------------------------------------------ */

/**
 * ADMIN SAJA. Dipanggil HANYA dari cabang admin pada bootstrap — ketiga peran
 * terikat mill tidak pernah menyentuh endpoint ini dari layar ini.
 */
async function loadBusinessUnits(): Promise<void> {
  try {
    businessUnits.value = await kernelDispatchReportRepo.fetchBusinessUnits()
  } catch (error) {
    if (statusOf(error) === 403) {
      // Pemilih mill memang bukan untuk peran ini — jangan dirender sama
      // sekali: menawarkan pemilih yang pasti gagal adalah kebohongan.
      millPickerForbidden.value = true
      businessUnits.value = []
      errorMessage.value = 'Pemilih Mill tidak tersedia untuk peran Anda.'

      return
    }

    handleError(error, loadBusinessUnits)
  }
}

async function loadPeriods(): Promise<void> {
  loadingPeriods.value = true

  try {
    // business_unit_id ikut HANYA untuk Admin; production_line_id TIDAK PERNAH
    // dikirim ke sini — periode milik MILL, bukan milik line.
    periods.value = await kernelDispatchReportRepo.fetchPeriods(scope.value)
  } catch (error) {
    periods.value = []
    handleError(error, retryPeriodsThenSummary, 'mill')
  } finally {
    loadingPeriods.value = false
    periodsLoaded.value = true
  }
}

/**
 * Aksi Coba Lagi untuk kegagalan daftar periode: memuat ulang daftarnya, lalu
 * MEMANGGIL loadSummary() — pemanggil ketiga dari satu pemuat yang sama.
 * Dengan begitu penjagaan Production Line diuji pada jalur ini juga, alih-alih
 * dilewati karena jalur ini "kebetulan" tidak pernah menyentuh ringkasan.
 */
async function retryPeriodsThenSummary(): Promise<void> {
  await loadPeriods()
  await loadSummary()
}

/**
 * PENJAGAAN PRODUCTION LINE ADA DI SINI — DI DALAM FUNGSI PEMUATAN — bukan
 * hanya di template, supaya tidak ada jalur pemanggilan yang bisa
 * melewatinya. KETIGA jalur (muat awal, ganti periode, Coba Lagi) memanggil
 * fungsi ini, jadi satu penjagaan melindungi ketiganya; penjaga yang hanya ada
 * di template dilewati ketiganya, dan melewatinya di layar ini berarti 422
 * dari server — bukan angka seluruh mill.
 *
 * Admin yang belum memilih mill juga berhenti di sini, dengan alasan yang
 * sama.
 */
async function loadSummary(): Promise<void> {
  const periodId = selectedPeriodId.value

  if (!periodId || !selectedProductionLineId.value) {
    return
  }

  if (isAdmin.value && !selectedBusinessUnitId.value) {
    return
  }

  // Hanya respons permintaan TERBARU yang berlaku: ganti periode/line dengan
  // cepat bisa membuat respons lama tiba belakangan — tanpa penjaga ini ia
  // menimpa ringkasan baru, memunculkan galat basi, dan mematikan indikator
  // muat milik permintaan baru. Diselesaikan dengan penanda permintaan
  // terakhir, BUKAN dengan membatalkan yang lama.
  const isLatest = summaryRequests.next()
  loadingSummary.value = true

  try {
    const result = await kernelDispatchReportRepo.fetchSummary(periodId, scope.value)

    if (isLatest()) {
      summary.value = result
    }
  } catch (error) {
    if (isLatest()) {
      handleError(error, loadSummary, 'mill')
    }
  } finally {
    if (isLatest()) {
      loadingSummary.value = false
    }
  }
}

/**
 * Batalkan ringkasan yang sedang dimuat tanpa memulai yang baru — dipanggil
 * setiap kali pilihan (mill/periode/line) berubah. Respons yang tiba kemudian
 * diabaikan.
 */
function resetSummary(): void {
  summaryRequests.invalidate()
  loadingSummary.value = false
  summary.value = null
}

async function onPeriodChange(): Promise<void> {
  clearErrors()
  resetSummary()

  if (!selectedPeriodId.value) {
    return
  }

  await loadSummary()
}

/**
 * Mengganti mill MEMBATALKAN pilihan line DAN pilihan periode: keduanya milik
 * satu mill, dan membiarkan salah satunya tertinggal berarti menampilkan angka
 * mill baru di bawah pilihan mill lama.
 */
async function onBusinessUnitChange(): Promise<void> {
  clearErrors()
  resetSummary()

  periods.value = []
  periodsLoaded.value = false
  selectedPeriodId.value = null
  productionLines.value = []
  selectedProductionLineId.value = null

  if (!selectedBusinessUnitId.value) {
    return
  }

  await loadPeriods()
  // `false`: ingatan perangkat TIDAK dipakai sesudah pengguna mengganti mill.
  // Ia pilihan terakhir pengguna di mill yang LAIN, dan memakainya di sini
  // akan menerbitkan angka untuk line yang tidak pernah ia pilih pada mill
  // yang baru ia pilih — bentuk kesalahan yang sama dengan memilihkan
  // satu-satunya line. Pada pemuatan awal ingatan itu justru dipakai, karena
  // di sana mill-nya memang mill yang sama.
  await loadProductionLines(false)
  await loadSummary()
}

/**
 * Pemuatan awal — URUTANNYA ADALAH KONTRAK: baca sesi → bila luring BERHENTI
 * sebelum permintaan pertama → bila Admin ambil opsi mill dan berhenti sampai
 * mill dipilih → ambil daftar periode → ambil opsi production line → pakai
 * line yang diingat perangkat bila masih sah → panggil loadSummary(), yang
 * MENJAGA SENDIRI bahwa periode DAN line keduanya terisi.
 *
 * loadSummary() dipanggil tanpa syarat di sini, dan itu disengaja: penjagaan
 * hidup di SATU tempat, sehingga ketiga jalur pemanggil tidak dapat punya
 * pemeriksaan sendiri-sendiri yang menyimpang.
 */
async function bootstrap(): Promise<void> {
  if (!isAdmin.value && !accountBusinessUnitId.value) {
    // Berhenti di sini. TIDAK ada permintaan apa pun, dan TIDAK ada pemilih
    // mill sebagai gantinya.
    noMillForAccount.value = true

    return
  }

  if (isOffline.value) {
    return
  }

  bootstrapped.value = true

  if (isAdmin.value) {
    // Admin tidak terikat mill: pilih mill dulu, baru daftar periode dan line
    // dimuat — keduanya oleh onBusinessUnitChange(). Sampai itu terjadi,
    // penghitung permintaan /periods, options-for-report dan /summary tetap 0.
    await loadBusinessUnits()

    return
  }

  await loadPeriods()
  await loadProductionLines()
  await loadSummary()
}

function onOffline(): void {
  syncOnlineState()
  // Luring MENUTUP angkanya, bukan sekadar menambah spanduk di atasnya: angka
  // yang tampil di sebelah pernyataan "perlu jaringan" adalah angka yang tidak
  // dapat dipertanggungjawabkan asalnya. Kartu tonase DAN kartu mutu keduanya
  // ikut tertutup — keduanya dirender dari `summary` yang sama, jadi tidak ada
  // separuh angka yang dapat tertinggal.
  resetSummary()
}

function onOnline(): void {
  syncOnlineState()

  if (!bootstrapped.value && !noMillForAccount.value) {
    void bootstrap()
  }
}

onMounted(async () => {
  syncOnlineState()
  window.addEventListener('online', onOnline)
  window.addEventListener('offline', onOffline)

  await bootstrap()
})

onUnmounted(() => {
  // WAJIB: wrapper yang bocor menghitung GANDA permintaan bootstrap pada test
  // berikutnya, dan kegagalannya akan tampak seperti bug di layar.
  window.removeEventListener('online', onOnline)
  window.removeEventListener('offline', onOffline)
})

/* ------------------------------------------------------------------ */
/* Ekspor — CSV SAJA                                                   */
/* ------------------------------------------------------------------ */

function exportFilename(): string {
  const slug = (selectedPeriod.value?.name ?? 'periode')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/(^-|-$)/g, '')

  const lineSlug = (activeProductionLineName.value || 'line')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/(^-|-$)/g, '')

  return `laporan-kernel-dispatch_${slug || 'periode'}_${lineSlug || 'line'}.csv`
}

async function onExport(): Promise<void> {
  const periodId = selectedPeriodId.value

  // Ekspor mengikuti cakupan yang sama dengan angka di layar: tanpa line,
  // tidak ada berkas — berkas yang mencampur line adalah bentuk kesalahan yang
  // paling sulit dibantah setelah terkirim. `exporting` mengunci tombol selama
  // berkas disiapkan, sehingga ketukan ganda hanya mengirim SATU permintaan.
  if (!periodId || !selectedProductionLineId.value || exporting.value) {
    return
  }

  exporting.value = true

  try {
    // Isi CSV dibentuk SERVER: satu baris per pengiriman, ENAM kolom konteks
    // diulang verbatim, lalu kedua puluh satu kolom detail. Layar ini tidak
    // pernah menyusun berkasnya dari angka yang tampil. Nama berkas diambil
    // SAAT ekspor diminta: bila pengguna mengganti periode selama berkas
    // diunduh, isinya tetap milik periode yang diekspor, jadi namanya pun
    // harus milik periode itu. format=csv SELALU dan EKSPLISIT.
    const filename = exportFilename()
    const blob = await kernelDispatchReportRepo.exportCsv(periodId, scope.value)
    kernelDispatchReportRepo.saveCsvFile(blob, filename)
  } catch (error) {
    // Kegagalan ekspor tidak dapat "diulang" secara bermakna oleh tombol Coba
    // Lagi yang sama, jadi retry-nya null. 401 tetap mengarah ke Login tanpa
    // pesan jaringan, dan tidak ada berkas yang disimpan.
    handleError(error, null)
  } finally {
    exporting.value = false
  }
}

/* ------------------------------------------------------------------ */
/* Turunan tampilan — MEMBACA payload, tanpa satu hitungan pun         */
/* ------------------------------------------------------------------ */

const dispatches = computed<KernelDispatchReportDispatches | null>(
  () => summary.value?.dispatches ?? null,
)
const quality = computed<KernelDispatchReportQuality | null>(() => summary.value?.quality ?? null)
const compliance = computed<KernelDispatchReportCompliance | null>(
  () => summary.value?.compliance ?? null,
)
const completeness = computed<KernelDispatchReportCompleteness | null>(
  () => summary.value?.completeness ?? null,
)
const dailyRows = computed<KernelDispatchReportDailyRow[]>(() => summary.value?.daily ?? [])
const dailyTotal = computed<KernelDispatchReportDailyTotal | null>(
  () => summary.value?.daily_total ?? null,
)
const draftDispatchCount = computed<number | null>(() => summary.value?.draft_dispatch_count ?? null)

/**
 * DIBACA, TIDAK DIHITUNG ULANG dari by_gate_status. Menghitungnya ulang adalah
 * cara paling mudah memasukkan baris 'tidak tercatat' ke dalamnya tanpa
 * sengaja — dan angka yang keliru di sini MENUDUH pengiriman yang sekadar
 * belum diisi statusnya.
 */
const notReleasedCount = computed<number | null>(() => summary.value?.not_released_count ?? null)

/**
 * DIRENDER HANYA KETIKA > 0 — sama dengan web. Kolom `event_date` NOT NULL di
 * basis data, jadi angkanya selalu 0 dalam praktik; mencetak "nol pengiriman
 * tanpa tanggal" pada setiap laporan berarti mengarang kekhawatiran atas
 * keadaan yang dilarang skema, dan pembaca yang melihatnya akan mencari
 * masalah yang tidak ada. Blok ini karena itu TIDAK HADIR pada angka 0 — bukan
 * hadir bernilai 0.
 */
const undatedDispatchCount = computed<number>(() => summary.value?.undated_dispatch_count ?? 0)
const showUndated = computed(() => undatedDispatchCount.value > 0)

/**
 * SATU DAFTAR TEKS KEJADIAN, bukan dua: stasiun ini TIDAK punya kolom catatan
 * kedua, berbeda dari Solid Waste Disposal. Tidak ada `remarkEntries` di
 * berkas ini, dan ketiadaannya dinyatakan di layar alih-alih dibiarkan terbaca
 * sebagai bagian yang lupa dibuat.
 */
const findingEntries = computed<KernelDispatchReportTextEntry[]>(
  () => summary.value?.text_entries?.findings ?? [],
)

/** Prosa SERVER, dirender VERBATIM — klien tidak menyusun kalimatnya sendiri. */
const qualityNote = computed(() => quality.value?._note ?? '')
const complianceNote = computed(() => compliance.value?._note ?? '')
const noteNoHourly = computed(() => summary.value?._note_no_hourly ?? '')
const noteNoQualityThresholds = computed(() => summary.value?._note_no_quality_thresholds ?? '')
const noteShiftNotPublished = computed(() => summary.value?._note_shift_not_published ?? '')

/* ------------------------------------------------------------------ */
/* Keempat ukuran mutu — SATU DAFTAR, DI-LOOP                          */
/* ------------------------------------------------------------------ */

interface QualityBlock {
  /** Kunci payload, dieja PERSIS seperti QUALITY_MEASURES di service. */
  key: KernelDispatchReportQualityKey
  /** Potongan testid kartunya — turunan kunci, bukan nama bebas. */
  slug: string
  label: string
  /** Dipakai di kalimat penyebut dan kalimat 'belum ada hasil uji'. */
  short: string
}

/**
 * KEEMPAT UKURAN, DALAM URUTAN KONSTANTA SERVICE (kadar air → kotoran → ALB →
 * inti pecah), yaitu urutan kolom pada layar Detail stasiun ini dan urutan
 * yang sama yang dibaca manusia di log sheet-nya.
 *
 * Didaftar SEKALI lalu di-loop — bukan empat blok markup yang disalin: empat
 * salinan adalah empat kesempatan kehilangan sebuah penyebut atau mewarisi
 * penyebut kartu lain, dan kesalahan itu menghasilkan angka yang terlihat
 * sepenuhnya masuk akal.
 *
 * `dirt_impurities_percent` dieja PENUH. Nama yang dipendekkan akan
 * terkompilasi, terlihat benar, dan memulangkan undefined untuk setiap periode
 * tanpa satu galat pun.
 */
const QUALITY_BLOCKS: QualityBlock[] = [
  {
    key: 'kernel_moisture_percent',
    slug: 'kernel-moisture',
    label: 'Kadar Air Inti',
    short: 'kadar air inti',
  },
  {
    key: 'dirt_impurities_percent',
    slug: 'dirt-impurities',
    label: 'Kadar Kotoran',
    short: 'kadar kotoran',
  },
  {
    key: 'ffa_percent',
    slug: 'ffa',
    label: 'Kadar Asam Lemak Bebas (ALB)',
    short: 'kadar ALB',
  },
  {
    key: 'broken_kernel_percent',
    slug: 'broken-kernel',
    label: 'Kadar Inti Pecah',
    short: 'kadar inti pecah',
  },
]

/**
 * Angka SATU ukuran, apa adanya dari payload. Ukuran yang tidak terbit sama
 * sekali diperlakukan sebagai ukuran tanpa hasil uji — null/null/null/0 —
 * bukan sebagai nol, dan BUKAN sebagai alasan menyembunyikan ketiga ukuran
 * lain.
 */
function measureOf(block: QualityBlock): KernelDispatchReportQualityMeasure {
  return (
    quality.value?.[block.key] ?? {
      min: null,
      avg: null,
      max: null,
      measured_count: 0,
    }
  )
}

/**
 * Penjaga satuan '%' untuk SATU ukuran. Diperiksa PER UKURAN — sebuah penjaga
 * di tingkat kartu akan mencabut satuan dari tiga ukuran yang punya angka
 * hanya karena satu ukuran kosong.
 */
function hasMeasureValue(block: QualityBlock): boolean {
  return measureOf(block).avg !== null && measureOf(block).avg !== undefined
}

/**
 * KEEMPAT ukuran sama-sama tanpa hasil uji. Dipakai HANYA untuk memilih satu
 * kalimat tambahan di kepala kartu mutu — BUKAN sebagai `v-if` pembungkusnya:
 * kartunya TETAP dirender, dan keempat kartu ukurannya pun tetap dirender
 * dengan '—'. Kartu yang hilang dari DOM menghapus penjelasannya bersama
 * angkanya.
 */
const allQualityUnmeasured = computed(() =>
  QUALITY_BLOCKS.every((block) => measureOf(block).measured_count === 0),
)

/* ------------------------------------------------------------------ */
/* Keempat rekap kategoris — SATU MARKUP, DI-LOOP                      */
/* ------------------------------------------------------------------ */

type RecapKey = 'by_transporter' | 'by_silo_source' | 'by_destination_buyer' | 'by_gate_status'

interface RecapBlock {
  key: RecapKey
  testid: string
  title: string
  /** Judul kolom nilai — nama kolom DETAIL-nya disebut di keterangan, bukan di judul. */
  valueLabel: string
  /** Kalimat pelengkap keterangan: "kolom <…> kosong pada pengiriman itu". */
  emptyLabel: string
  hint: string
  /**
   * Rekap keempat BERBEDA SIFATNYA: nilainya pilihan TERTUTUP dan payload
   * membawa kode mentahnya, jadi labelnya didelegasikan ke `optionLabel()`.
   * Ketiga rekap lain teks bebas dan dirender APA ADANYA — nol pemetaan, nol
   * pencarian nama, nol penerjemahan.
   */
  closedChoice?: boolean
}

/**
 * Urutannya adalah urutan payload. Judul dan label kolomnya mengikuti versi
 * web (screen-164) supaya pembaca yang berpindah dari ponsel ke web tidak
 * perlu mempelajari ulang peta halamannya.
 */
const RECAPS: RecapBlock[] = [
  {
    key: 'by_transporter',
    testid: 'kd-recap-transporter',
    title: 'Rekap per Pengangkut/Kontraktor',
    valueLabel: 'Pengangkut/kontraktor',
    emptyLabel: 'pengangkut/kontraktor',
    hint: 'siapa yang mengangkutnya',
  },
  {
    key: 'by_silo_source',
    testid: 'kd-recap-silo-source',
    title: 'Rekap per Silo Sumber',
    valueLabel: 'Silo sumber',
    emptyLabel: 'silo sumber',
    hint: 'dari silo mana inti itu diambil',
  },
  {
    key: 'by_destination_buyer',
    testid: 'kd-recap-destination-buyer',
    title: 'Rekap per Pembeli Tujuan',
    valueLabel: 'Pembeli tujuan',
    emptyLabel: 'pembeli tujuan',
    hint: 'ke mana ia dikirim',
  },
  {
    key: 'by_gate_status',
    testid: 'kd-recap-gate-status',
    title: 'Rekap per Status Pelepasan Gerbang',
    valueLabel: 'Status pelepasan gerbang',
    emptyLabel: 'status pelepasan gerbang',
    hint: 'apakah kendaraannya dilepas dari gerbang',
    closedChoice: true,
  },
]

/**
 * Baris sebuah rekap, APA ADANYA dari payload: nol pengurutan, nol
 * penggabungan, nol pemangkasan, dan nol kelompok 'lainnya'. Nilai yang muncul
 * SEKALI tetap mendapat barisnya sendiri, dan dua nilai yang berbeda hanya
 * pada besar-kecil huruf atau spasi tepi tetap dua baris — teks bebas tidak
 * menjamin keduanya hal yang sama, dan normalisasi yang tidak terlihat justru
 * menyembunyikan data master yang perlu dirapikan.
 */
function recapRows(block: RecapBlock): KernelDispatchReportBreakdownRow[] {
  const payload = summary.value

  if (!payload) {
    return []
  }

  return payload[block.key] ?? []
}

/**
 * Teks sel nilai. DUA transformasi, dan tidak ada yang ketiga:
 *   - null → 'tidak tercatat' (berlaku untuk KEEMPAT rekap);
 *   - rekap berpilihan tertutup → `optionLabel()`, kosakata bersama yang
 *     dijaga terhadap backend. Nilai di luar pilihan yang dikenal jatuh ke
 *     NILAI MENTAHNYA — tidak dibuang, tidak dipaksa masuk salah satu pilihan,
 *     dan tidak dilipat ke baris 'tidak tercatat', karena ia TERCATAT, hanya
 *     tidak dikenal.
 *
 * Keduanya TIDAK memindahkan barisnya: posisi baris milik server.
 */
function recapValueLabel(value: string | null, block: RecapBlock): string {
  if (value === null) {
    return 'tidak tercatat'
  }

  return block.closedChoice ? optionLabel(value) : value
}

/** Label kode status gerbang untuk kalimat di luar tabel — sumber yang SAMA. */
function gateStatusLabel(value: string): string {
  return optionLabel(value)
}

/* ------------------------------------------------------------------ */
/* Lipat/buka — PENYINGKAPAN MURNI, bawaannya TERBUKA                  */
/* ------------------------------------------------------------------ */

/**
 * Kunci bagian yang sedang DILIPAT. Dicatat sebagai "yang tertutup", bukan
 * "yang terbuka", supaya bawaannya terbuka tanpa perlu menyemai daftar apa
 * pun — sama dengan `<details open>` pada versi web.
 *
 * Melipat TIDAK memicu satu permintaan pun dan TIDAK mengubah satu angka pun;
 * dan ia tidak menyentuh kartu tonase maupun kartu mutu, yang memang tidak
 * punya kontrol lipat sama sekali.
 */
const collapsed = ref<Set<string>>(new Set())

function isOpen(testid: string): boolean {
  return !collapsed.value.has(testid)
}

function toggleSection(testid: string): void {
  const next = new Set(collapsed.value)

  if (next.has(testid)) {
    next.delete(testid)
  } else {
    next.add(testid)
  }

  collapsed.value = next
}

/* ------------------------------------------------------------------ */
/* Keadaan layar                                                       */
/* ------------------------------------------------------------------ */

/**
 * LURING IKUT MENUTUP SELURUH ANGKA: layar menyatakan laporan perlu jaringan
 * DAN tidak merender satu angka pun bersamaan dengan pernyataan itu — nol
 * kartu tonase, nol kartu mutu, nol rekap.
 */
const hasReport = computed(
  () =>
    Boolean(summary.value) &&
    !isOffline.value &&
    !networkError.value &&
    !errorMessage.value &&
    !noMillForAccount.value &&
    !millRequired.value &&
    !productionLineRejected.value &&
    !productionLineRequired.value,
)

/**
 * Periode sah tetapi tanpa satu pengiriman pun pada line terpilih — keadaan
 * NYATA di sebagian besar line hari ini. KERANGKA LAPORAN TETAP DIRENDER
 * PENUH: justru itu yang menjelaskan kekosongannya, dan yang memperlihatkan
 * bahwa pertanyaannya ADA dan jawabannya belum tercatat.
 */
const emptyPeriod = computed(() => {
  const value = dispatches.value

  if (!value) {
    return false
  }

  return value.dispatch_count === 0
})

/**
 * KEHADIRAN NILAI, dipisah dari nilainya sendiri: `undefined` (blok tidak ada
 * pada respons) dan `null` (ada tetapi tidak diketahui) sama-sama berarti
 * "tidak ada angka untuk diberi satuan", sehingga pil satuan 'MT' / '%' tidak
 * pernah berdiri di samping tanda pisah. Diputuskan PER MEDAN — bukan lewat
 * satu bawaan untuk seluruh layar.
 */
const hasNetWeightTotal = computed(
  () =>
    dispatches.value?.net_weight_total !== null &&
    dispatches.value?.net_weight_total !== undefined,
)
const hasNetWeightAvg = computed(
  () =>
    dispatches.value?.net_weight_avg !== null && dispatches.value?.net_weight_avg !== undefined,
)
const hasCoveragePercent = computed(
  () => completeness.value?.percent !== null && completeness.value?.percent !== undefined,
)

const periodStatus = computed(
  () => summary.value?.period?.status ?? selectedPeriod.value?.status ?? null,
)

/** Periode belum dimulai — DIPERIKSA LEBIH DULU; lihat keterangan di template. */
const periodNotStarted = computed(() => (completeness.value?.days_counted ?? 0) === 0)

/**
 * Periode masih berjalan, sehingga penyebut hari BERHENTI DI HARI INI.
 * Diputuskan dari dua angka yang SAMA-SAMA DARI SERVER (days_counted dan
 * days_in_period); jam perangkat tidak pernah ikut memutuskan, karena perangkat
 * yang jamnya bergeser akan menerbitkan keterangan yang berselisih dengan
 * angkanya. Membandingkan dua angka terbitan untuk memilih KALIMAT adalah
 * presentasi, bukan aritmetika: tidak ada bilangan baru yang lahir darinya.
 *
 * URUTAN CABANGNYA PENTING: periodNotStarted diperiksa LEBIH DULU di template
 * — periode yang belum mulai juga memenuhi syarat "berjalan", dan memeriksa
 * yang kedua lebih dulu membuat keterangan "belum dimulai" tidak pernah
 * terender (cacat yang nyata terjadi pada tiga layar dan diperbaiki pada
 * commit screen-148).
 */
const periodRunning = computed(() => {
  const value = completeness.value

  if (!value) {
    return false
  }

  return value.days_counted < value.days_in_period || (periodStatus.value ?? '') === 'open'
})

/* ------------------------------------------------------------------ */
/* Format Indonesia                                                    */
/* ------------------------------------------------------------------ */

const MONTHS_SHORT = [
  'Jan',
  'Feb',
  'Mar',
  'Apr',
  'Mei',
  'Jun',
  'Jul',
  'Agu',
  'Sep',
  'Okt',
  'Nov',
  'Des',
]

const DASH = '—'

/**
 * Penghitung: titik ribuan, tanpa desimal. null menjadi '—' — tetapi NOL YANG
 * SUNGGUHAN tetap dicetak 0, dan itulah sebabnya pemeriksaan null ada di sini
 * alih-alih sebuah `?? 0` di pemanggil.
 */
function formatCount(value: number | null | undefined): string {
  if (value === null || value === undefined) {
    return DASH
  }

  return new Intl.NumberFormat('id-ID').format(value)
}

/**
 * Berat (MT). null WAJIB menjadi '—', TIDAK PERNAH 0 dan tidak pernah '0,00':
 * nol berarti "terangkut dan hasilnya nol", sedangkan '—' berarti "belum
 * selesai ditimbang, beratnya tidak diketahui".
 *
 * Satu desimal minimal, dua maksimal — 37,5 / 9,9 / 7,25 / 55,0. SATUANNYA
 * TIDAK DICETAK DI SINI: 'MT' hidup di elemen sebelahnya, supaya elemen angka
 * dapat diasersi dengan kesetaraan penuh dan '—' tidak pernah berdiri di
 * samping satuan yang tidak ada angkanya.
 */
function formatWeight(value: number | null | undefined): string {
  if (value === null || value === undefined) {
    return DASH
  }

  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 1,
    maximumFractionDigits: 2,
  }).format(value)
}

/**
 * Nilai mutu (persen). DUA desimal minimal dan TIGA maksimal, dan kedua batas
 * itu punya alasannya sendiri:
 *   - tiga maksimal mengikuti pembulatan server
 *     (KernelDispatchReportService::QUALITY_DECIMALS = 3), sehingga kadar
 *     kotoran yang bergerak di orde perseratus persen tidak dilipat menjadi
 *     satu angka yang sama dengan nilai tetangganya;
 *   - dua minimal supaya sebuah nilai yang benar-benar terukur NOL tetap
 *     terbaca sebagai pengukuran ('0,00'), bukan sebagai bilangan bulat yang
 *     mudah disangka penanda kosong.
 * Pembulatannya SUDAH dikerjakan server; fungsi ini hanya memilih berapa angka
 * di belakang koma yang dicetak dan tidak pernah membulatkan ulang.
 *
 * null menjadi '—' — diperiksa PER UKURAN oleh pemanggilnya, bukan sekali
 * untuk seluruh kartu.
 */
function formatQuality(value: number | null | undefined): string {
  if (value === null || value === undefined) {
    return DASH
  }

  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 3,
  }).format(value)
}

/**
 * Persen kelengkapan. null (periode belum mulai) menjadi '—', BUKAN 0,0% —
 * nol persen berarti ada hari yang diharapkan dan tak satu pun terisi,
 * sedangkan '—' berarti belum ada hari yang diharapkan sama sekali. Tanda '%'
 * dirender di elemen sebelahnya dan HANYA ketika angkanya ada.
 */
function formatPercent(value: number | null | undefined): string {
  if (value === null || value === undefined) {
    return DASH
  }

  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 1,
    maximumFractionDigits: 2,
  }).format(value)
}

function formatDate(value: string | null | undefined): string {
  if (!value) {
    // Pengiriman tanpa tanggal kejadian — kalimat yang sama dengan versi web.
    return 'tanpa tanggal kejadian'
  }

  const [year, month, day] = value.slice(0, 10).split('-')

  if (!year || !month || !day) {
    return value
  }

  return `${day} ${MONTHS_SHORT[Number(month) - 1] ?? month} ${year}`
}

const PERIOD_STATUS_LABELS: Record<string, string> = {
  draft: 'Draft',
  open: 'Terbuka',
  closed: 'Tertutup',
}

function periodStatusLabel(status: string | null | undefined): string {
  if (!status) {
    return '-'
  }

  return PERIOD_STATUS_LABELS[status] ?? status
}

function periodOptionLabel(period: KernelDispatchReportPeriodOption): string {
  return period.station_type_label ? `${period.station_type_label} — ${period.name}` : period.name
}

/* ------------------------------------------------------------------ */
/* Navigasi & menu (konvensi bersama layar mobile lain)                */
/* ------------------------------------------------------------------ */

const isNavMenuOpen = ref(false)

function toggleNavMenu(): void {
  isNavMenuOpen.value = !isNavMenuOpen.value
}

function closeNavMenu(): void {
  isNavMenuOpen.value = false
}

function openAiAssistant(): void {
  closeNavMenu()
  aiAssistantStore.open()
}

function goToChangePassword(): void {
  closeNavMenu()
  router.push({ name: 'change-password' })
}

async function onLogout(): Promise<void> {
  closeNavMenu()
  await authStore.logout()
  router.push({ name: 'login' })
}

function goToHome(): void {
  router.push({ name: 'home' })
}

function goToDashboardReporting(): void {
  router.push({ name: 'dashboard-reporting' })
}

function onBack(): void {
  router.push({ name: 'dashboard-reporting' })
}
</script>

<template>
  <main class="laporan-kd-view" data-testid="laporan-kernel-dispatch-mobile">
    <header class="app-header">
      <div class="app-header-brand">
        <span class="brand-name">Mills Smart Log</span>
      </div>
      <button
        type="button"
        class="hamburger-button"
        data-testid="hamburger-button"
        @click="toggleNavMenu"
      >
        &#9776;
      </button>
      <div v-if="isNavMenuOpen" class="nav-menu" data-testid="nav-menu">
        <button type="button" class="nav-menu-item" @click="goToChangePassword">
          Ganti Password
        </button>
        <button type="button" class="nav-menu-item" @click="floatingClockStore.toggle()">
          {{ floatingClockStore.enabled ? 'Nonaktifkan Jam Mengambang' : 'Aktifkan Jam Mengambang' }}
        </button>
        <button type="button" class="nav-menu-item" @click="aiAssistantStore.toggleBubble()">
          {{
            aiAssistantStore.bubbleEnabled ? 'Nonaktifkan Bubble Chat AI' : 'Aktifkan Bubble Chat AI'
          }}
        </button>
        <button type="button" class="nav-menu-item" @click="openAiAssistant">Bantuan AI</button>
        <button type="button" class="nav-menu-item" data-testid="nav-menu-logout" @click="onLogout">
          Logout
        </button>
      </div>
    </header>

    <div class="screen-header">
      <nav class="breadcrumb" aria-label="Breadcrumb">
        <button
          type="button"
          class="breadcrumb-link"
          data-testid="breadcrumb-home"
          @click="goToHome"
        >
          Home
        </button>
        <span aria-hidden="true">/</span>
        <button type="button" class="breadcrumb-link" @click="goToDashboardReporting">
          Dashboard &amp; Reporting
        </button>
        <span aria-hidden="true">/</span>
        <span aria-current="page">Laporan Kernel Dispatch</span>
      </nav>
      <h1 class="screen-title">Laporan Kernel Dispatch</h1>
    </div>

    <!-- Akun terikat mill tetapi mill-nya kosong — DUA jalur, SATU keadaan:
         pra-penerbangan (berhenti total tanpa satu pun permintaan HTTP) dan 422
         dari server. Keduanya menuntut tindakan yang sama, dan daftar seluruh
         mill TIDAK ditawarkan sebagai jalan keluar. -->
    <p
      v-if="noMillForAccount"
      class="notice notice--warning"
      role="alert"
      data-testid="no-mill-for-account"
    >
      Akun Anda belum terhubung ke mill mana pun, sehingga laporan tidak dapat ditampilkan.
      Silakan hubungi Admin untuk menghubungkan akun Anda ke sebuah mill. Ini bukan masalah
      jaringan, jadi mencoba lagi tidak akan mengubah apa pun.
    </p>

    <template v-else>
      <FilterPanel aria-label="Filter laporan">
        <!-- Pemilih Mill: ADMIN SAJA. Ketiga peran lain terikat mill akunnya,
             tidak melihat pemilih ini sama sekali, dan daftar seluruh mill
             TIDAK PERNAH dipanggil atas nama mereka. -->
        <FilterSelectField v-if="isAdmin && !millPickerForbidden" label="Mill" icon="mill">
          <select
            v-model="selectedBusinessUnitId"
            data-testid="mill-select"
            @change="onBusinessUnitChange"
          >
            <option :value="null">Pilih Mill</option>
            <option v-for="unit in businessUnits" :key="unit.id" :value="unit.id">
              {{ unit.name }}
            </option>
          </select>
        </FilterSelectField>

        <!-- Pemilih Production Line — WAJIB, dan SENGAJA tanpa opsi "semua
             line". DIRENDER WALAU DAFTARNYA HANYA BERISI SATU ENTRI: layar ini
             tidak memilihkan line secara diam-diam, bahkan ketika mill hanya
             punya satu. -->
        <FilterSelectField v-if="productionLines.length > 0" label="Production Line" icon="line">
          <select
            v-model="selectedProductionLineId"
            data-testid="production-line-select"
            @change="onProductionLineChange"
          >
            <option :value="null">Pilih Production Line</option>
            <option v-for="line in productionLines" :key="line.id" :value="line.id">
              {{ line.name }}
            </option>
          </select>
        </FilterSelectField>

        <!-- Pemilih Periode selalu dirender: bila mill belum punya periode ia
             tampil KOSONG, bukan hilang. Status periode TIDAK menyaring
             daftarnya — periode tertutup tercantum dan tetap dapat dibaca. -->
        <FilterSelectField label="Periode Pelaporan" icon="period">
          <select v-model="selectedPeriodId" data-testid="period-select" @change="onPeriodChange">
            <option :value="null">Pilih Periode</option>
            <option v-for="period in periods" :key="period.id" :value="period.id">
              {{ periodOptionLabel(period) }}
            </option>
          </select>
        </FilterSelectField>

        <template #chips>
          <FilterChip
            v-if="!isAdmin"
            label="Mill"
            :value="currentMillName || '-'"
            data-testid="mill-current"
          />
          <FilterChip
            v-if="activeProductionLineName"
            label="Production Line"
            :value="activeProductionLineName"
            data-testid="production-line-current"
          />
        </template>
      </FilterPanel>

      <!-- Laporan ini PERLU JARINGAN: tidak ada hasil yang disimpan ke
           perangkat dan tidak ada mode luring, jadi keadaannya dinyatakan
           alih-alih ditutupi angka basi tanpa penanda — dan selama ia berlaku
           NOL angka dirender, termasuk nol kartu mutu. -->
      <p v-if="isOffline" class="notice notice--warning" role="alert" data-testid="offline-notice">
        Perangkat sedang tidak terhubung jaringan. Laporan periode
        <strong>memerlukan jaringan</strong> — angkanya dihitung di server setiap kali dibaca dan
        tidak disimpan di perangkat ini, jadi layar ini tidak menampilkan satu angka pun dari
        pemuatan sebelumnya. Sambungkan kembali jaringan, lalu coba lagi.
      </p>

      <p v-if="millRequired" class="notice" data-testid="mill-required-hint">
        Pilih Mill terlebih dahulu. Admin tidak terikat pada satu mill, jadi laporan ini belum tahu
        mill mana yang angkanya diminta — dan selama belum ada mill yang berlaku,
        <strong>tidak satu permintaan pun dikirim ke server</strong>: daftar periode dan daftar
        Production Line pun belum dimuat.
      </p>

      <!-- 403: line (atau periode) milik mill lain — termasuk line basi yang
           masih diingat perangkat. Diperlakukan sebagai "pilih line lagi",
           BUKAN galat jaringan. Klien tidak memeriksa kepemilikan sendiri. -->
      <p
        v-if="productionLineRejected"
        class="notice notice--warning"
        role="alert"
        data-testid="production-line-rejected"
      >
        Production Line yang terakhir dipakai perangkat ini tidak lagi tersedia untuk akun Anda —
        mungkin sudah dihapus atau milik mill lain. Silakan pilih Production Line kembali pada
        pemilih di atas. Tidak satu angka pun dari mill lain ditampilkan, dan angka yang sebelumnya
        tampil sudah dibersihkan.
      </p>

      <p v-if="productionLineRequired" class="notice" data-testid="production-line-required-hint">
        Pilih Production Line terlebih dahulu untuk menampilkan laporan. Angka laporan dihitung per
        Production Line, sehingga tidak ada pilihan gabungan lintas line — dan selama belum ada
        line yang berlaku, <strong>tidak satu permintaan pun dikirim ke server</strong>, karena
        penjagaannya ada di fungsi pemuatan dan bukan hanya di tampilan. Pilihan itu tidak pernah
        dibuat untuk Anda, bahkan ketika mill ini hanya punya satu line.
      </p>

      <!-- Pesannya membedakan "mill belum punya line" dari "daftarnya gagal
           dimuat": dua keadaan yang menuntut tindakan berbeda, dan hanya yang
           kedua punya tombol Coba Lagi. -->
      <div
        v-else-if="productionLineBlocked && !millRequired"
        class="notice notice--warning notice--stack"
        role="alert"
        data-testid="production-line-unavailable"
      >
        <p class="notice-text">{{ productionLineNotice }}</p>
        <button
          v-if="productionLineFetchFailed"
          type="button"
          class="action-button action-button--secondary"
          data-testid="production-line-retry"
          @click="() => loadProductionLines()"
        >
          Coba Lagi
        </button>
      </div>

      <p v-else-if="noPeriods" class="notice" data-testid="no-periods">
        Mill ini belum memiliki periode pelaporan yang mencakup stasiun Kernel Dispatch. Silakan
        hubungi Admin untuk membuat periode pelaporan terlebih dahulu. Ini bukan galat: periodenya
        memang belum pernah dibuat.
      </p>

      <LoadingState v-if="loadingProductionLines" test-id="production-lines-loading">
        Memuat daftar Production Line…
      </LoadingState>
      <LoadingState v-if="loadingPeriods" test-id="periods-loading">
        Memuat daftar periode…
      </LoadingState>

      <div v-if="networkError" class="notice notice--error" role="alert" data-testid="network-error">
        <p class="notice-text">{{ networkError }}</p>
        <button
          v-if="canRetry"
          type="button"
          class="action-button action-button--secondary"
          data-testid="retry-button"
          @click="onRetry"
        >
          Coba Lagi
        </button>
      </div>

      <p
        v-else-if="errorMessage"
        class="notice notice--error"
        role="alert"
        data-testid="error-message"
      >
        {{ errorMessage }}
      </p>

      <LoadingState v-if="loadingSummary" variant="card" test-id="summary-loading">
        Memuat ringkasan periode…
      </LoadingState>

      <template v-if="hasReport">
        <div class="period-meta" data-testid="period-meta">
          <span class="period-meta-name">{{ summary?.period?.name }}</span>
          <div class="period-meta-line">
            <span>
              {{ formatDate(summary?.period?.start_date) }} &ndash;
              {{ formatDate(summary?.period?.end_date) }}
            </span>
            <StatusBadge
              status="none"
              :label="periodStatusLabel(periodStatus)"
              data-testid="period-status-badge"
            />
          </div>
          <span class="period-meta-note">
            Status periode mengatur penulisan data, bukan pembacaan laporan — periode yang sudah
            ditutup tetap dapat dilihat dan diekspor sepenuhnya.
          </span>
        </div>

        <!-- Periode sah tetapi NOL pengiriman pada line terpilih. Keadaan NYATA
             di sebagian besar line hari ini, jadi kerangka laporannya tetap
             dirender penuh — bukan layar putih. Kalimatnya MENYEBUT periode dan
             line, supaya pembaca tahu kekosongan ini milik cakupan mana. -->
        <p v-if="emptyPeriod" class="notice" data-testid="kd-empty-period-note">
          Belum ada satu pengiriman inti pun yang tercatat pada periode
          <strong>{{ summary?.period?.name }}</strong> di line
          <strong>{{ summary?.production_line?.name }}</strong
          >. Seluruh bagian laporan di bawah tetap ditampilkan — termasuk kartu mutunya — justru
          itu yang memperlihatkan bahwa pertanyaannya ada dan jawabannya belum tercatat, bukan
          bahwa jawabannya nol.
        </p>

        <!-- ===== TONASE =====
             BAGIAN INI DAN BAGIAN MUTU DI BAWAHNYA ADALAH DUA SECTION YANG
             BERURUTAN TANPA APA PUN DI ANTARANYA, dan keduanya TIDAK punya
             kontrol lipat: tonase tinggi dengan kadar air tinggi adalah kabar
             buruk yang menyamar sebagai kabar baik, jadi keduanya harus
             terbaca dalam satu tarikan tanpa tindakan pengguna. -->
        <section class="detail-section" data-testid="kd-card-tonnage">
          <h2 class="section-title">Pengiriman Inti Periode Ini</h2>
          <p class="section-note" data-testid="kd-hybrid-note">
            Laporan ini menjawab <strong>tiga pertanyaan yang berbeda sifatnya</strong>: berapa
            banyak inti terkirim, <strong>sebaik apa mutunya</strong>, dan apakah dokumen angkutnya
            lengkap. Satuan hitungnya <strong>satu pengiriman</strong> — satu baris catatan, satu
            kendaraan yang berangkat membawa inti ke pembeli — bukan satu pembacaan per slot waktu;
            stasiun ini tidak punya grid slot waktu.
            <small>
              Berat bersih <strong>diturunkan server</strong> sebagai bruto dikurangi tara; ia tidak
              pernah diisi manusia, dan layar ini membacanya apa adanya tanpa menghitungnya ulang.
              Keempat nilai mutu sebaliknya <strong>diisi dari hasil uji laboratorium</strong>, dan
              tidak satu pun dari keempatnya wajib — itulah sebab tiap ukuran punya penyebutnya
              sendiri.
            </small>
          </p>

          <!-- PREFIKS `kd-card-` DISIMPAN UNTUK TIGA WADAH TINGKAT ATAS SAJA
               (kd-card-tonnage, kd-card-quality, kd-card-missing-net-weight);
               kartu angka di dalamnya berprefiks `kd-metric-`. Sebabnya bukan
               selera: kedekatan tonase–mutu diasersi sebagai URUTAN daftar
               wadah tingkat atas, dan kartu dalam yang ikut berprefiks
               `kd-card-` akan menyelip di antara keduanya pada pencarian
               berprefiks itu — hijau atau merahnya lalu bergantung pada selektor
               yang dipakai penguji, bukan pada susunan layarnya. -->
          <div class="metric-stack">
            <article class="metric-card" data-testid="kd-metric-dispatch-count">
              <div class="metric-label-row">
                <span class="metric-label">Jumlah Pengiriman</span>
                <span class="metric-unit-pill">pengiriman</span>
              </div>
              <span class="metric-value" data-testid="kd-kpi-dispatch-count">{{
                formatCount(dispatches?.dispatch_count)
              }}</span>
              <span class="metric-note">
                sepanjang {{ formatDate(summary?.period?.start_date) }} &ndash;
                {{ formatDate(summary?.period?.end_date) }} di line
                {{ summary?.production_line?.name }}
              </span>
              <span class="metric-note">
                <small>
                  Satu pengiriman = satu baris detail, bukan satu record. Angka ini menghitung
                  <strong>seluruh</strong> pengiriman, termasuk yang penimbangannya belum selesai
                  dan termasuk yang belum punya satu hasil uji pun.
                </small>
              </span>
            </article>

            <article class="metric-card" data-testid="kd-metric-net-weight-total">
              <div class="metric-label-row">
                <span class="metric-label">Total Berat Bersih Inti</span>
                <span v-if="hasNetWeightTotal" class="metric-unit-pill">MT</span>
              </div>
              <!-- '—' KETIKA null, dan tidak pernah 0,00: nol berarti tidak ada
                   inti terangkut, sedangkan tanda pisah berarti tidak
                   diketahui. Elemen ini memuat ANGKANYA SAJA — satuannya ada di
                   pil di atas, dan pil itu tidak dirender ketika angkanya tidak
                   ada. -->
              <span
                class="metric-value"
                :class="{ 'metric-value--na': dispatches?.net_weight_total === null }"
                data-testid="kd-kpi-net-weight-total"
                >{{ formatWeight(dispatches?.net_weight_total) }}</span
              >
              <span class="metric-note">
                dijumlahkan dari {{ formatCount(dispatches?.net_weight_dispatch_count) }} pengiriman
                yang nettonya benar-benar terisi —
                {{ formatCount(dispatches?.missing_net_weight_dispatch_count) }} pengiriman
                <strong>tidak ikut dijumlahkan</strong> karena nettonya belum ada
              </span>
            </article>

            <!-- PENYEBUT RATA-RATA DICETAK DI DALAM KARTU INI, bukan di tempat
                 lain di layar: rata-rata dari 6 pengiriman tidak boleh terlihat
                 sama meyakinkan dengan rata-rata dari 600, dan di ponsel
                 pembaca melihat SATU KARTU PADA SATU WAKTU sehingga kedekatan
                 itu tidak dapat diwariskan dari kartu sebelahnya. -->
            <article class="metric-card" data-testid="kd-metric-net-weight-avg">
              <div class="metric-label-row">
                <span class="metric-label">Rata-rata Netto per Pengiriman</span>
                <span v-if="hasNetWeightAvg" class="metric-unit-pill">MT</span>
              </div>
              <span
                class="metric-value"
                :class="{ 'metric-value--na': dispatches?.net_weight_avg === null }"
                data-testid="kd-kpi-net-weight-avg"
                >{{ formatWeight(dispatches?.net_weight_avg) }}</span
              >
              <span class="metric-note">
                penyebutnya
                <b data-testid="kd-kpi-net-weight-dispatch-count">{{
                  formatCount(dispatches?.net_weight_dispatch_count)
                }}</b>
                pengiriman yang nettonya terisi — <strong>bukan</strong>
                {{ formatCount(dispatches?.dispatch_count) }} pengiriman periode ini
              </span>
              <span class="metric-note">
                <small>
                  Membaginya dengan seluruh pengiriman akan menurunkan rata-rata secara palsu hanya
                  karena ada penimbangan yang belum selesai. Pengiriman yang nettonya kosong
                  <strong>tidak menurunkan rata-rata, ia hilang darinya</strong>. Angka ini dibaca
                  apa adanya dari server dan tidak pernah dibagi ulang di perangkat.
                </small>
              </span>
            </article>
          </div>

          <!-- DIRENDER HANYA KETIKA > 0. Kolom tanggal kejadian NOT NULL di
               basis data, jadi angkanya selalu 0 dalam praktik; mencetak "0 di
               antaranya tanpa tanggal" pada setiap laporan berarti mengarang
               kekhawatiran atas keadaan yang dilarang skema. -->
          <p v-if="showUndated" class="section-note" data-testid="kd-undated-note">
            Dari {{ formatCount(dispatches?.dispatch_count) }} pengiriman periode ini,
            <b data-testid="kd-undated-dispatch-count">{{ formatCount(undatedDispatchCount) }}</b>
            di antaranya <strong>tidak punya tanggal kejadian</strong> sehingga tidak dapat
            ditempatkan pada hari mana pun. Pengiriman seperti itu <strong>tetap ikut</strong> di
            jumlah pengiriman, di total serta rata-rata netto, di keempat penyebut mutu, dan di
            keempat rekap — tetapi <strong>tidak ikut</strong> di rekap harian. Itulah
            satu-satunya sebab penjumlahan rekap harian boleh lebih kecil daripada jumlah
            pengiriman periode.
          </p>
        </section>

        <!-- ===== MUTU: EMPAT UKURAN, EMPAT PENYEBUT =====
             SECTION INI BERADA TEPAT SESUDAH SECTION TONASE, tanpa bagian lain
             di antaranya — lihat docblock berkas.

             `v-if` PENJAGA '—' ADA DI TINGKAT UKURAN, BUKAN DI SINI. Section
             ini TIDAK PERNAH dibungkus `v-if="adaDataMutu"`: pembungkus seperti
             itu terasa rapi dan akan menyembunyikan tiga ukuran yang punya data
             ketika satu ukuran kosong, sekaligus menghapus penjelasannya
             bersama angkanya. -->
        <section class="detail-section" data-testid="kd-card-quality">
          <h2 class="section-title">Mutu per Pengiriman</h2>
          <p class="section-note">
            <strong>Empat ukuran, empat penyebut</strong>, dibaca berdampingan dengan tonase di
            atas. Setiap kartu di bawah menyebut <strong>penyebutnya sendiri</strong>: berapa
            pengiriman yang kolom ITU-nya benar-benar diuji.
          </p>

          <!-- Keempat ukuran kosong sekaligus adalah KEADAAN, bukan alasan
               menghilangkan kartunya: kartunya tetap dirender, keempat
               ukurannya tetap dirender dengan '—', dan kalimat ini
               menerangkannya. Kartu tonase di atas TIDAK terpengaruh — mutu dan
               berat adalah kolom yang berbeda. -->
          <p v-if="allQualityUnmeasured" class="section-note" data-testid="kd-quality-unmeasured-all">
            <strong>Belum ada satu hasil uji pun</strong> pada periode dan line ini untuk keempat
            ukuran mutu, jadi kedua belas angkanya ditulis sebagai tanda pisah. Angka tonase di
            atas <strong>tidak terpengaruh</strong>: berat dan mutu adalah kolom yang berbeda, dan
            satu pengiriman boleh punya salah satunya saja.
          </p>

          <div class="metric-stack">
            <article
              v-for="block in QUALITY_BLOCKS"
              :key="block.key"
              class="metric-card quality-card"
              :data-testid="`kd-quality-card-${block.slug}`"
              :data-measure="block.key"
            >
              <div class="metric-label-row">
                <span class="metric-label">{{ block.label }}</span>
                <span v-if="hasMeasureValue(block)" class="metric-unit-pill">%</span>
              </div>

              <!-- Rata-rata ukuran INI. null SELALU '—', TIDAK PERNAH 0: nol
                   berarti diuji dan hasilnya nol, '—' berarti tidak pernah
                   diuji. -->
              <span
                class="metric-value"
                :class="{ 'metric-value--na': measureOf(block).avg === null }"
                :data-testid="`kd-quality-${block.slug}-avg`"
                >{{ formatQuality(measureOf(block).avg) }}</span
              >
              <span class="metric-note">rata-rata ukuran ini</span>

              <!-- Terendah dan tertinggi BERTUMPUK di layar sempit, masing-
                   masing dengan elemen angkanya sendiri supaya nilai yang
                   tertukar antar-ukuran tertangkap. -->
              <div class="quality-range">
                <span class="quality-range-item">
                  <span class="quality-range-label">terendah</span>
                  <span
                    class="quality-range-value"
                    :class="{ 'metric-value--na': measureOf(block).min === null }"
                    :data-testid="`kd-quality-${block.slug}-min`"
                    >{{ formatQuality(measureOf(block).min) }}</span
                  >
                </span>
                <span class="quality-range-item">
                  <span class="quality-range-label">tertinggi</span>
                  <span
                    class="quality-range-value"
                    :class="{ 'metric-value--na': measureOf(block).max === null }"
                    :data-testid="`kd-quality-${block.slug}-max`"
                    >{{ formatQuality(measureOf(block).max) }}</span
                  >
                </span>
              </div>

              <!-- PENYEBUT MILIK KARTU INI SENDIRI, bukan penyebut bersama.
                   Satu penyebut bersama untuk keempat ukuran akan mengempiskan
                   ukuran yang jarang diuji dan tetap menghasilkan angka yang
                   terlihat sah. -->
              <span class="metric-note">
                dari
                <b :data-testid="`kd-quality-${block.slug}-count`">{{
                  formatCount(measureOf(block).measured_count)
                }}</b>
                pengiriman yang {{ block.short }}-nya diuji
                <small>
                  (penyebut ukuran ini sendiri — pengiriman yang tidak diujikan kolom ini
                  <strong>tidak mengukurnya nol</strong>, ia tidak mengukurnya sama sekali)
                </small>
              </span>

              <!-- PENJAGA PER UKURAN. Ukuran ini kosong TIDAK mengosongkan
                   ketiga ukuran lain, dan sebaliknya. -->
              <span
                v-if="measureOf(block).measured_count === 0"
                class="metric-note"
                :data-testid="`kd-quality-unmeasured-${block.slug}`"
              >
                <strong>Belum ada satu hasil uji pun</strong> untuk {{ block.short }} pada periode
                dan line ini, jadi ketiga angkanya ditulis sebagai tanda pisah. Ukuran mutu yang
                lain <strong>tetap menampilkan angkanya</strong> — yang kosong adalah satu kolom,
                bukan seluruh mutu periode ini.
              </span>
            </article>
          </div>

          <!-- Prosa milik payload, dirender UTUH: elemen ini memuat KALIMAT
               SERVER SAJA, tanpa satu kata tambahan di dalamnya. -->
          <p v-if="qualityNote" class="section-note" data-testid="kd-quality-note">{{
            qualityNote
          }}</p>
        </section>

        <!-- ===== PENIMBANGAN BELUM SELESAI — KARTUNYA SENDIRI =====
             Bagiannya SENDIRI, bukan catatan kaki dan bukan kartu di dalam blok
             tonase, dan kalimatnya berbunyi 'penimbangan belum selesai' — bukan
             'data tidak lengkap'. Netto adalah kolom turunan server, jadi
             kosongnya berarti pekerjaan yang menggantung.

             Letaknya SESUDAH kartu mutu dan bukan di antara tonase dan mutu:
             kedua kartu itu wajib bersebelahan (lihat docblock berkas), dan
             caveat ini tetap bertemu pembacanya tepat sesudah angka yang ia
             bantah. -->
        <section class="detail-section" data-testid="kd-card-missing-net-weight">
          <h2 class="section-title">Penimbangan Belum Selesai</h2>
          <div class="metric-stack">
            <article class="metric-card">
              <div class="metric-label-row">
                <span class="metric-label">Netto Belum Terisi</span>
                <span class="metric-unit-pill">pengiriman</span>
              </div>
              <span class="metric-value" data-testid="kd-kpi-missing-net-weight">{{
                formatCount(dispatches?.missing_net_weight_dispatch_count)
              }}</span>
              <span class="metric-note">
                netto kosong karena <strong>bruto atau tara belum terisi</strong> —
                <strong>penimbangan belum selesai</strong>, bukan nol
              </span>
              <span class="metric-note">
                ikut jumlah pengiriman — <strong>tidak</strong> ikut total maupun rata-rata netto
              </span>
              <span class="metric-note">
                <small>
                  Berat bersih adalah kolom <strong>turunan server</strong> (bruto dikurangi tara),
                  jadi kosongnya berarti kendaraan yang sudah ditimbang kotor tetapi belum kembali
                  ditimbang kosong, atau sebaliknya. Ia <strong>tidak berhubungan</strong> dengan
                  kekosongan hasil uji mutu: berat dan mutu adalah kolom yang berbeda.
                </small>
              </span>
            </article>
          </div>
        </section>

        <!-- ===== PENGIRIMAN TIDAK DILEPAS — DITONJOLKAN TERSENDIRI =====
             Bagiannya sendiri dan DI LUAR rekap yang dapat dilipat: inti yang
             berangkat tanpa pelepasan gerbang adalah kejadian yang perlu
             DILIHAT, bukan baris yang perlu dicari. Angkanya DARI SERVER dan
             menghitung HANYA 'not_released' persis. -->
        <section class="detail-section" data-testid="kd-not-released">
          <h2 class="section-title">Pengiriman Tidak Dilepas</h2>
          <div class="metric-stack">
            <article class="metric-card">
              <div class="metric-label-row">
                <span class="metric-label">Tidak Dilepas dari Gerbang</span>
                <span class="metric-unit-pill">pengiriman</span>
              </div>
              <span class="metric-value" data-testid="kd-kpi-not-released">{{
                formatCount(notReleasedCount)
              }}</span>
              <span class="metric-note">
                dari {{ formatCount(dispatches?.dispatch_count) }} pengiriman periode ini,
                berstatus <strong>{{ gateStatusLabel('not_released') }}</strong> pada kolom status
                pelepasan gerbang
              </span>
              <span class="metric-note">
                angka ini <strong>tidak</strong> memuat pengiriman yang statusnya
                <strong>tidak tercatat</strong>
              </span>
              <span class="metric-note">
                <small>
                  &lsquo;Tidak tercatat&rsquo; dan &lsquo;tidak dilepas&rsquo; adalah
                  <strong>dua keadaan yang berbeda</strong>, dan layar ini menolak
                  menggabungkannya: menggabungkannya berarti <strong>menuduh</strong> pengiriman
                  yang sekadar belum diisi statusnya, dan tuduhan itu sampai ke orang. Pengiriman
                  tanpa status tercatat punya barisnya sendiri di rekap status pelepasan gerbang.
                </small>
              </span>
            </article>
          </div>
        </section>

        <!-- ===== EMPAT REKAP KATEGORIS — SATU MARKUP, DI-LOOP =====
             Nilai APA ADANYA pada ketiga rekap teks bebas: nol join, nol
             pemetaan ke master, nol penerjemahan. Rekap KEEMPAT berbeda
             sifatnya — nilainya pilihan tertutup dan labelnya DIDELEGASIKAN ke
             kosakata bersama; nilai di luar pilihan yang dikenal tetap dirender
             apa adanya dengan barisnya sendiri.

             DAPAT DILIPAT, dan melipatnya MURNI PENYINGKAPAN: datanya sudah ada
             di memori, tidak satu permintaan pun dipicu, dan tidak satu angka
             pun berubah. Melipat sebuah rekap TIDAK menyentuh kartu tonase
             maupun kartu mutu — keduanya tidak punya kontrol lipat sama sekali.
             Tabelnya MENUMPUK MENJADI KARTU di lebar ponsel lewat
             v-stacked-table, sehingga halaman tidak pernah menggulir
             mendatar. -->
        <section
          v-for="block in RECAPS"
          :key="block.testid"
          class="recap-section"
          :data-testid="block.testid"
        >
          <button
            type="button"
            class="recap-toggle"
            :aria-expanded="isOpen(block.testid)"
            :data-testid="`${block.testid}-toggle`"
            @click="toggleSection(block.testid)"
          >
            <span class="recap-toggle-title"
              >{{ block.title }} ({{ recapRows(block).length }} baris)</span
            >
            <span class="recap-toggle-chevron" aria-hidden="true">{{
              isOpen(block.testid) ? '▾' : '▸'
            }}</span>
          </button>

          <div v-if="isOpen(block.testid)" class="recap-body">
            <p class="section-note">
              {{ block.hint }} &middot; urut dari <strong>total netto terbesar</strong>, lalu
              jumlah pengiriman, lalu nilainya — <strong>urutan itu milik server</strong> dan tidak
              disusun ulang di perangkat.
            </p>

            <div class="detail-table-wrap">
              <table v-stacked-table class="detail-table" :data-testid="`${block.testid}-table`">
                <thead>
                  <tr>
                    <th scope="col">{{ block.valueLabel }}</th>
                    <th scope="col" class="num">Pengiriman</th>
                    <th scope="col" class="num">Total netto (MT)</th>
                    <th scope="col" class="num">Pengiriman netto terisi</th>
                    <th scope="col" class="num">Rata-rata/pengiriman (MT)</th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Baris value NULL TIDAK PERNAH DIBUANG dan TIDAK PERNAH
                       DIPINDAH: posisinya ditentukan tonasenya oleh server.
                       Membuangnya membuat kolom Pengiriman berhenti menjumlah
                       ke jumlah pengiriman periode, dan memindahkannya ke baris
                       terakhir mengubur kelompok bertonase besar yang justru
                       paling perlu dibaca. -->
                  <tr
                    v-for="(row, index) in recapRows(block)"
                    :key="`${block.testid}-${index}-${row.value ?? 'null'}`"
                    :data-testid="`${block.testid}-row-${index}`"
                  >
                    <td>
                      <span
                        :class="{ 'is-muted': row.value === null }"
                        :data-testid="`${block.testid}-value-${index}`"
                        >{{ recapValueLabel(row.value, block) }}</span
                      >
                    </td>
                    <td class="num" :data-testid="`${block.testid}-dispatch-count-${index}`">
                      {{ formatCount(row.dispatch_count) }}
                    </td>
                    <td
                      class="num"
                      :class="{ 'is-muted': row.net_weight_total === null }"
                      :data-testid="`${block.testid}-net-weight-total-${index}`"
                    >
                      {{ formatWeight(row.net_weight_total) }}
                    </td>
                    <td
                      class="num is-muted"
                      :data-testid="`${block.testid}-net-weight-dispatch-count-${index}`"
                    >
                      {{ formatCount(row.net_weight_dispatch_count) }}
                    </td>
                    <td
                      class="num"
                      :class="{ 'is-muted': row.net_weight_avg === null }"
                      :data-testid="`${block.testid}-net-weight-avg-${index}`"
                    >
                      {{ formatWeight(row.net_weight_avg) }}
                    </td>
                  </tr>
                  <tr v-if="recapRows(block).length === 0" :data-testid="`${block.testid}-empty`">
                    <td colspan="5" class="is-muted">
                      Tidak ada satu pengiriman pun pada periode {{ summary?.period?.name }} di
                      line {{ summary?.production_line?.name }}, jadi rekap ini belum punya baris.
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <!-- Baris total DIBACA dari blok angka pokok, BUKAN
                       dijumlahkan dari baris-baris di atasnya. -->
                  <tr :data-testid="`${block.testid}-total`">
                    <td>Total periode</td>
                    <td class="num">{{ formatCount(dispatches?.dispatch_count) }}</td>
                    <td class="num">{{ formatWeight(dispatches?.net_weight_total) }}</td>
                    <td class="num">{{ formatCount(dispatches?.net_weight_dispatch_count) }}</td>
                    <td class="num">{{ formatWeight(dispatches?.net_weight_avg) }}</td>
                  </tr>
                </tfoot>
              </table>
            </div>

            <p class="section-note" :data-testid="`${block.testid}-note`">
              Kolom Pengiriman menjumlah tepat ke
              {{ formatCount(dispatches?.dispatch_count) }} pengiriman, termasuk baris
              <strong>tidak tercatat</strong> yang <strong>berdiri sendiri</strong> dan tidak
              pernah dilipat ke baris mana pun — pengiriman yang kolom {{ block.emptyLabel }}-nya
              kosong adalah fakta yang berbeda dari pengiriman yang diatribusikan ke sebuah nilai.
              <template v-if="block.closedChoice">
                Ini <strong>satu-satunya rekap di layar ini</strong> yang nilainya berasal dari
                <strong>pilihan tertutup</strong>, jadi labelnya diambil dari
                <strong>kosakata bersama aplikasi</strong> dan tidak diketik ulang di sini — satu
                nilai, satu label, di layar Detail, di laporan ini, dan di berkas ekspor.
                Penyimpanan kolom ini <strong>tidak membatasi</strong> isinya pada kedua pilihan
                itu, jadi nilai dari jalur selain form web ditampilkan
                <strong>apa adanya</strong> dengan barisnya sendiri — tidak dibuang dan tidak
                dipaksa masuk salah satu pilihan, justru supaya kolom Pengiriman tetap menjumlah.
              </template>
              <template v-else>
                Nilainya ditampilkan <strong>apa adanya</strong>, tanpa penerjemahan ke label dan
                tanpa penyatuan dua ejaan yang hanya berbeda besar-kecil huruf.
              </template>
              <template v-if="block.key === 'by_silo_source'">
                <strong>Nama kolomnya menyesatkan</strong>: meski berakhiran
                <code>_id</code>, silo sumber adalah teks yang <strong>diketik</strong> — tidak ada
                tabel master silo di basis data ini, dan form input merendernya sebagai kotak teks.
                Nilainya karena itu tidak pernah dicari namanya, bahkan ketika ia berbentuk seperti
                sebuah id.
              </template>
            </p>
          </div>
        </section>

        <!-- ===== JEJAK KEPATUHAN — BLOK TERSENDIRI =====
             Sengaja BUKAN di dalam blok angka pokok: ini bukan ukuran produksi
             melainkan penghitung KEHADIRAN dokumen angkut. Tidak pernah
             dirata-rata, tidak pernah diurutkan, dan tidak pernah digrafikkan —
             sebuah nomor segel tidak punya besaran.

             TIGA PENGHITUNG, DAN KEDUA PENGHITUNG SEGEL TIDAK PERNAH
             DIJUMLAHKAN menjadi satu angka: satu kendaraan yang kehilangan satu
             segel adalah keadaan yang berbeda dari dua kendaraan yang
             masing-masing kehilangan satu. -->
        <section class="detail-section" data-testid="kd-compliance">
          <h2 class="section-title">Jejak Kepatuhan Dokumen Angkut</h2>
          <p class="section-note">
            Penghitung <strong>kehadiran</strong>, bukan ukuran. Nol pun
            <strong>tetap dicetak</strong> — nol itulah yang memberi tahu pembaca bahwa
            pemeriksaannya memang berjalan.
          </p>

          <div class="metric-stack">
            <article class="metric-card">
              <div class="metric-label-row">
                <span class="metric-label">Tanpa Nomor Surat Jalan</span>
                <span class="metric-unit-pill">pengiriman</span>
              </div>
              <span class="metric-value" data-testid="kd-compliance-missing-waybill">{{
                formatCount(compliance?.missing_waybill_count)
              }}</span>
              <span class="metric-note">
                dari {{ formatCount(dispatches?.dispatch_count) }} pengiriman periode ini
              </span>
            </article>

            <article class="metric-card">
              <div class="metric-label-row">
                <span class="metric-label">Tanpa Nomor Segel Atas</span>
                <span class="metric-unit-pill">pengiriman</span>
              </div>
              <span class="metric-value" data-testid="kd-compliance-missing-seal-top">{{
                formatCount(compliance?.missing_seal_top_count)
              }}</span>
              <span class="metric-note">
                dihitung <strong>terpisah</strong> dari segel bawah — dua angka, tidak pernah satu
              </span>
            </article>

            <article class="metric-card">
              <div class="metric-label-row">
                <span class="metric-label">Tanpa Nomor Segel Bawah</span>
                <span class="metric-unit-pill">pengiriman</span>
              </div>
              <span class="metric-value" data-testid="kd-compliance-missing-seal-bottom">{{
                formatCount(compliance?.missing_seal_bottom_count)
              }}</span>
              <span class="metric-note">
                satu pengiriman yang kehilangan <strong>kedua</strong> segel terhitung di
                <strong>kedua</strong> kartu — dua fakta, bukan penghitungan ganda
              </span>
            </article>
          </div>

          <!-- Prosa milik payload, dirender UTUH. -->
          <p v-if="complianceNote" class="section-note" data-testid="kd-compliance-note">{{
            complianceNote
          }}</p>
        </section>

        <!-- ===== REKAP HARIAN =====
             Satu baris per HARI BERPENGIRIMAN, bukan per hari kalender. Hari
             tanpa pengiriman sengaja TIDAK dibuatkan baris nol: laporan ini
             per-transaksi, dan hari tanpa pengiriman adalah keadaan normal —
             mencetak 22 baris nol akan mengubur 9 baris yang berarti. -->
        <section class="recap-section" data-testid="kd-daily-recap">
          <button
            type="button"
            class="recap-toggle"
            :aria-expanded="isOpen('kd-daily-recap')"
            data-testid="kd-daily-recap-toggle"
            @click="toggleSection('kd-daily-recap')"
          >
            <span class="recap-toggle-title"
              >Rekap Harian ({{ dailyRows.length }} tanggal berpengiriman)</span
            >
            <span class="recap-toggle-chevron" aria-hidden="true">{{
              isOpen('kd-daily-recap') ? '▾' : '▸'
            }}</span>
          </button>

          <div v-if="isOpen('kd-daily-recap')" class="recap-body">
            <p class="section-note">
              Satu kartu per <strong>hari berpengiriman</strong> beserta kartu
              <strong>Total harian</strong>. Sel kosong dirender &lsquo;&mdash;&rsquo; —
              <strong>bukan 0</strong>, karena nol adalah hasil timbang dan kosong bukan.
            </p>

            <div class="detail-table-wrap">
              <table v-stacked-table class="detail-table" data-testid="kd-daily-table">
                <thead>
                  <tr>
                    <th scope="col">Tanggal</th>
                    <th scope="col" class="num">Pengiriman</th>
                    <th scope="col" class="num">Total netto (MT)</th>
                    <th scope="col" class="num">Pengiriman netto terisi</th>
                    <th scope="col" class="num">Rata-rata/pengiriman (MT)</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="row in dailyRows"
                    :key="`daily-${row.date}`"
                    :data-testid="`kd-daily-row-${row.date}`"
                  >
                    <td>{{ formatDate(row.date) }}</td>
                    <td class="num" :data-testid="`kd-daily-dispatch-count-${row.date}`">
                      {{ formatCount(row.dispatch_count) }}
                    </td>
                    <td class="num" :class="{ 'is-muted': row.net_weight_total === null }">
                      {{ formatWeight(row.net_weight_total) }}
                    </td>
                    <td
                      class="num is-muted"
                      :data-testid="`kd-daily-net-weight-dispatch-count-${row.date}`"
                    >
                      {{ formatCount(row.net_weight_dispatch_count) }}
                    </td>
                    <td class="num" :class="{ 'is-muted': row.net_weight_avg === null }">
                      {{ formatWeight(row.net_weight_avg) }}
                    </td>
                  </tr>
                  <tr v-if="dailyRows.length === 0" data-testid="kd-daily-empty">
                    <td colspan="5" class="is-muted">
                      Tidak ada satu pengiriman bertanggal pun pada periode
                      {{ summary?.period?.name }} di line {{ summary?.production_line?.name }}.
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <!-- DIBACA dari payload (`daily_total`), BUKAN dijumlahkan
                       dari kolom di atasnya: keduanya memang dapat berbeda,
                       tepatnya sebesar pengiriman tanpa tanggal kejadian, dan
                       perbedaan itu adalah informasi. -->
                  <tr data-testid="kd-daily-total">
                    <td>Total harian</td>
                    <td class="num" data-testid="kd-daily-total-dispatch-count">
                      {{ formatCount(dailyTotal?.dispatch_count) }}
                    </td>
                    <td class="num" data-testid="kd-daily-total-net-weight-total">
                      {{ formatWeight(dailyTotal?.net_weight_total) }}
                    </td>
                    <td class="num" data-testid="kd-daily-total-net-weight-dispatch-count">
                      {{ formatCount(dailyTotal?.net_weight_dispatch_count) }}
                    </td>
                    <td class="num" data-testid="kd-daily-total-net-weight-avg">
                      {{ formatWeight(dailyTotal?.net_weight_avg) }}
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>

            <p class="section-note" data-testid="kd-daily-total-note">
              Baris total di kaki tabel ini <strong>dibaca dari payload</strong>, bukan dijumlahkan
              dari baris-baris harian di atasnya dan bukan disalin dari kartu tonase. Ketiganya
              memang dapat berbeda, tepatnya sebesar jumlah pengiriman yang tidak punya tanggal
              kejadian — pengiriman semacam itu ikut di jumlah pengiriman tetapi tidak dapat
              ditempatkan pada hari mana pun. Perbedaan itu adalah <strong>informasi</strong>;
              menjumlahkannya sendiri akan menyembunyikannya, dan selisih yang tak dijelaskan tidak
              dapat dibedakan dari cacat.
              <small>
                Kolom <strong>Pengiriman netto terisi</strong> ikut dicetak pada setiap baris
                karena rata-rata harian berdiri di atas penyebutnya sendiri, bukan di atas jumlah
                pengiriman hari itu. Hari yang punya pengiriman tetapi tak satu pun nettonya terisi
                mencetak tanda pisah, bukan nol.
              </small>
            </p>
          </div>
        </section>

        <!-- ===== KELENGKAPAN PENCATATAN =====
             BAGIAN DARI LAPORAN, BUKAN CATATAN KAKI: pembaca perlu tahu
             seberapa jauh angka di atas dapat diandalkan sebelum
             menindaklanjutinya. Diukur dalam HARI, bukan kolom. -->
        <section class="detail-section" data-testid="kd-coverage">
          <h2 class="section-title">Kelengkapan Pencatatan</h2>
          <p class="section-note">
            Penyebutnya <strong>hari periode yang sudah lewat</strong>, bukan seluruh panjang
            periode — periode yang masih berjalan tidak dihukum atas hari yang belum datang.
          </p>

          <div class="metric-stack">
            <div class="metric-card">
              <div class="metric-label-row">
                <span class="metric-label">Hari dengan Minimal Satu Pengiriman</span>
                <span v-if="hasCoveragePercent" class="metric-unit-pill">%</span>
              </div>
              <!-- Elemen ini memuat PERSENNYA SAJA supaya ia dapat diasersi
                   sendiri tanpa ikut memindai keterangan di sekitarnya; tanda
                   '%' ada di pil di atas dan tidak dirender ketika persennya
                   '—'. Nilainya DARI PAYLOAD — tidak diturunkan dari
                   days_with_dispatch/days_counted di perangkat. -->
              <span
                class="metric-value"
                :class="{ 'metric-value--na': completeness?.percent === null }"
                data-testid="kd-coverage-percent"
                >{{ formatPercent(completeness?.percent) }}</span
              >
              <span class="metric-note">
                <b data-testid="kd-coverage-days-with-dispatch">{{
                  formatCount(completeness?.days_with_dispatch)
                }}</b>
                hari berpengiriman dari
                <b data-testid="kd-coverage-denominator">{{
                  formatCount(completeness?.days_counted)
                }}</b>
                hari yang sudah lewat
                <template v-if="completeness?.days_in_period">
                  — panjang periodenya
                  <span data-testid="kd-coverage-days-in-period">{{
                    formatCount(completeness?.days_in_period)
                  }}</span>
                  hari</template
                >
              </span>
              <div
                v-if="hasCoveragePercent"
                class="completeness-track"
                role="img"
                :aria-label="`Kelengkapan pencatatan ${formatPercent(completeness?.percent)} persen`"
              >
                <div
                  class="completeness-fill"
                  :style="{ width: `${completeness?.percent ?? 0}%` }"
                ></div>
              </div>

              <!-- URUTANNYA PENTING: days_counted === 0 DIPERIKSA LEBIH DULU.
                   Periode yang belum mulai juga memenuhi syarat 'masih
                   berjalan', jadi memeriksa yang kedua lebih dulu membuat
                   keterangan 'belum dimulai' tidak pernah terender. -->
              <span
                v-if="periodNotStarted"
                class="metric-note"
                data-testid="kd-coverage-not-started-note"
              >
                Periode ini <strong>belum dimulai</strong>, jadi belum ada satu hari pun yang
                diharapkan terisi dan kelengkapannya ditulis sebagai tanda pisah —
                <strong>bukan sebagai nol persen</strong>, yang akan terbaca sebagai kelalaian
                pencatatan.
              </span>
              <span
                v-else-if="periodRunning"
                class="metric-note"
                data-testid="kd-coverage-running-note"
              >
                <strong>Dihitung sampai hari ini, karena periode masih berjalan</strong> —
                {{ formatCount(completeness?.days_counted) }} hari periode sudah lewat. Hari yang
                belum terjadi tidak mungkin tercatat, jadi tidak ikut menjadi penyebut.
              </span>
            </div>
          </div>

          <p class="section-note" data-testid="kd-coverage-note">
            Kelengkapan mengukur <strong>hari</strong>, bukan kolom: berapa hari periode yang sudah
            lewat punya minimal satu pengiriman. Rentangnya
            <strong>inklusif di kedua ujung</strong>, jadi hari pertama dan hari terakhir ikut
            dihitung. Hari tanpa satu pengiriman pun <strong>bukan hal yang sama</strong> dengan
            hari berpengiriman nol MT: hari semacam itu tidak mendapat baris pada rekap harian.
            <small>
              Angka ini <strong>tidak</strong> mengukur kelengkapan hasil uji mutu — itu diukur
              oleh keempat penyebut mutu, masing-masing untuk kolomnya sendiri. Keanggotaan periode
              ditentukan oleh <strong>tanggal record induk</strong>, bukan tanggal kejadian tiap
              pengiriman — keduanya dapat berbeda, dan konsekuensinya rekap harian dapat memuat
              tanggal di luar rentang periode. Itu dibiarkan apa adanya: menyembunyikannya akan
              membuat total tidak cocok dengan rincian hariannya.
            </small>
          </p>
        </section>

        <!-- ===== REKAP TEMUAN — SATU DAFTAR, BUKAN DUA =====
             Stasiun ini TIDAK punya kolom catatan kedua, berbeda dari Solid
             Waste Disposal. Ketiadaan bagian kedua DINYATAKAN di sini supaya
             tidak terbaca sebagai bagian yang lupa dibuat. -->
        <section class="detail-section" data-testid="kd-findings">
          <h2 class="section-title">Rekap Temuan ({{ formatCount(findingEntries.length) }} pengiriman)</h2>
          <p class="section-note">
            Bagian ini menjawab <strong>apa yang ditemukan</strong>. Isinya diterbitkan
            <strong>sebagai teks beserta tanggalnya</strong>, dirender utuh tanpa pemotongan —
            tidak pernah disebar menjadi kategori dan tidak pernah dihitung sebagai metrik, karena
            kolomnya teks bebas dan menyebarnya akan memperlakukan prosa sebagai enum.
            <small>
              Stasiun ini punya <strong>satu</strong> kolom teks kejadian, bukan dua: tidak ada
              kolom catatan terpisah pada baris detailnya, jadi tidak ada bagian kedua yang hilang
              dari layar ini.
            </small>
          </p>

          <p v-if="findingEntries.length === 0" class="section-note" data-testid="kd-findings-empty">
            Tidak ada satu temuan pun yang dicatat pada periode dan line ini. Bagian ini tetap
            ditampilkan dengan keterangan ini — bukan dihilangkan dari layar, dan bukan daftar
            kosong tanpa penjelasan.
          </p>

          <div v-else class="note-list">
            <article
              v-for="(entry, index) in findingEntries"
              :key="`finding-${index}-${entry.date ?? 'null'}`"
              class="note-item"
              :data-testid="`kd-findings-row-${index}`"
              :data-date="entry.date ?? ''"
            >
              <span class="note-meta">{{ formatDate(entry.date) }}</span>
              <p class="note-text">{{ entry.value }}</p>
            </article>
          </div>
        </section>

        <!-- ===== PENGIRIMAN DRAFT ===== -->
        <section class="detail-section" data-testid="kd-draft">
          <h2 class="section-title">Pengiriman Berstatus Draft</h2>
          <div class="metric-stack">
            <article class="metric-card">
              <div class="metric-label-row">
                <span class="metric-label">Pengiriman Draft</span>
                <span class="metric-unit-pill">pengiriman</span>
              </div>
              <span class="metric-value" data-testid="kd-draft-dispatch-count">{{
                formatCount(draftDispatchCount)
              }}</span>
              <span class="metric-note" data-testid="kd-draft-note">
                Pengiriman milik catatan berstatus draft <strong>IKUT terhitung</strong> pada
                seluruh angka laporan ini — jumlah pengiriman, total dan rata-rata netto, keempat
                ukuran mutu beserta penyebutnya, keempat rekap, ketiga penghitung kepatuhan, dan
                rekap harian. Angka ini menyatakan berapa banyak di antaranya yang belum final;
                pembaca diberi tahu, bukan dilindungi dari mengetahuinya.
              </span>
            </article>
          </div>
        </section>

        <!-- ===== YANG TIDAK DITERBITKAN DI LAYAR INI =====
             Ketiga prosa SERVER dirender VERBATIM, masing-masing di elemennya
             sendiri tanpa satu kata tambahan di dalamnya. Ketiadaan yang tidak
             dijelaskan selalu dibaca sebagai bagian yang lupa dibuat; dan
             kalimat yang disusun dua kali di dua platform adalah kalimat yang
             akan berselisih. -->
        <section class="detail-section" data-testid="kd-absences">
          <h2 class="section-title">Yang Tidak Diterbitkan di Layar Ini</h2>

          <p v-if="noteNoHourly" class="section-note" data-testid="kd-note-no-hourly">{{
            noteNoHourly
          }}</p>

          <p
            v-if="noteNoQualityThresholds"
            class="section-note"
            data-testid="kd-note-no-quality-thresholds"
          >{{ noteNoQualityThresholds }}</p>

          <p v-if="noteShiftNotPublished" class="section-note" data-testid="kd-note-shift">{{
            noteShiftNotPublished
          }}</p>

          <p class="section-note" data-testid="kd-export-scope-note">
            Kolom-kolom yang tidak muncul di satu angka pun di layar ini —
            <strong>giliran kerja, nomor tiket timbang, nomor surat jalan, nomor kendaraan, nama
            pengemudi dan kode operator timbang</strong> — <strong>tetap ikut</strong> pada setiap
            baris berkas ekspor, karena ekspor adalah rincian <strong>mentah</strong> sedangkan
            layar ini ringkasannya.
            <small>
              Ekspornya <strong>CSV saja</strong>: tidak ada tombol format lain di layar ini dan
              tidak ada jalur kode yang dapat menyusunnya, karena berkas lembar kerja biner di
              perangkat lapangan tidak punya pembaca yang dapat diandalkan sementara CSV dapat
              dibuka di mana pun dan dikirim lewat pesan.
            </small>
          </p>
        </section>
      </template>

      <footer class="action-footer">
        <!-- Ekspor TIDAK PERNAH dinonaktifkan oleh status periode: kunci
             periode mengatur penulisan data, bukan pembacaan laporan. CSV
             SAJA. Tombolnya tidak dirender selama belum ada periode DAN line
             yang berlaku, karena berkas yang mencampur line adalah bentuk
             kesalahan yang paling sulit dibantah setelah terkirim. -->
        <button
          v-if="selectedPeriodId && selectedProductionLineId"
          type="button"
          class="action-button action-button--primary"
          data-testid="kd-export-csv"
          :disabled="exporting"
          :aria-busy="exporting"
          @click="onExport"
        >
          <BusyLabel :busy="exporting" label="Ekspor CSV" busy-label="Mengekspor…" />
        </button>
        <button
          type="button"
          class="action-button action-button--secondary"
          data-testid="back-button"
          @click="onBack"
        >
          Back
        </button>
      </footer>
    </template>
  </main>
</template>

<style scoped>
/* SETIAP KELAS YANG DIPAKAI MARKUP DI ATAS PUNYA DEFINISI — di blok ini, atau
   di stylesheet bersama yang dirujuknya (src/assets/stacked-table.css untuk
   .detail-table dan .num, dimuat global oleh src/main.ts). Kelas tanpa
   definisi tidak memerahkan satu test perilaku pun — ia hanya menjawab
   'visible' ketika gaya yang diharapkan ditanyakan, dan defek itu nyaris
   terkirim pada pekerjaan sebelumnya di proyek ini. Kartu mutu memperkenalkan
   kelas tata letak BARU (.quality-card, .quality-range*), jadi layar inilah
   yang paling mungkin memakai kelas yang tidak pernah didefinisikan. */

.laporan-kd-view {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 0 16px 20px;
  background: #ffffff;
  font-family: 'Inter', sans-serif;
  box-sizing: border-box;
  /* HALAMAN TIDAK PERNAH MENGGULIR MENDATAR pada 390px. Kelima tabel rekap
     BERTUMPUK menjadi kartu lewat v-stacked-table + stacked-table.css (temuan
     QA LP-M-02/LP-M-03) alih-alih digulir mendatar. */
  overflow-x: hidden;
}

.app-header {
  position: relative;
  display: flex;
  align-items: center;
  justify-content: space-between;
  min-height: 64px;
  margin: 0 -16px;
  padding: 0 16px;
  background: #ffffff;
}
.app-header-brand { display: flex; align-items: center; gap: 10px; }
.brand-name { font-size: 16px; font-weight: 700; color: #1f2937; }
.hamburger-button { min-height: 44px; min-width: 44px; border: none; background: transparent; font-size: 20px; cursor: pointer; }
.nav-menu { position: absolute; top: 64px; right: 0; z-index: 10; display: flex; flex-direction: column; min-width: 180px; padding: 6px; border-radius: 12px; background: #ffffff; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.06); border: 1px solid #f7f7f7; }
.nav-menu-item { min-height: 44px; padding: 0 12px; border: none; border-radius: 8px; background: transparent; font-size: 14px; font-weight: 500; text-align: left; cursor: pointer; }

.screen-header { display: flex; flex-direction: column; gap: 6px; }
.breadcrumb { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; font-size: 12px; color: #6b7280; }
.breadcrumb-link { border: none; background: transparent; color: #6b7280; font-size: 12px; cursor: pointer; padding: 0; }
.screen-title { margin: 0; font-size: 20px; font-weight: 600; color: #1f2937; }

.period-meta { display: flex; flex-direction: column; gap: 6px; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; }
.period-meta-name { font-size: 14px; font-weight: 600; color: #1f2937; }
.period-meta-line { display: flex; align-items: center; justify-content: space-between; gap: 8px; flex-wrap: wrap; font-size: 13px; color: #6b7280; }
.period-meta-note { font-size: 12px; color: #6b7280; line-height: 1.5; }

.notice { margin: 0; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; color: #6b7280; font-size: 14px; line-height: 1.5; overflow-wrap: anywhere; }
.notice--warning { border-color: #fcd34d; background: #fffbeb; color: #92400e; }
.notice--error { display: flex; flex-direction: column; gap: 10px; border-color: #fecaca; background: #fef2f2; color: #b91c1c; }
.notice-text { margin: 0; }
.notice--stack { display: flex; flex-direction: column; align-items: flex-start; gap: 10px; }

.metric-stack { display: flex; flex-direction: column; gap: 10px; }
/* Kartu angka BERTUMPUK SATU KOLOM di layar sempit — tidak ada grid dua kolom
   yang memaksa angka panjang keluar dari kartunya. Keempat kartu mutu memakai
   tumpukan yang SAMA, sehingga ketiga angka dan penyebutnya tetap terbaca pada
   390px tanpa satu pun isi yang terpotong induknya. */
.metric-card { display: flex; flex-direction: column; gap: 4px; padding: 14px; border: 1px solid #e5e7eb; border-radius: 8px; min-width: 0; }
.metric-label-row { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
.metric-label { font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }

/* Pil satuan ('MT', 'pengiriman', '%') — satuan hidup DI SINI, di luar elemen
   angkanya, sehingga elemen angka memuat angka saja. */
.metric-unit-pill {
  flex: none;
  padding: 2px 8px;
  border: 1px solid #e5e7eb;
  border-radius: 999px;
  background: #f9fafb;
  font-size: 11px;
  font-weight: 600;
  color: #4b5563;
  white-space: nowrap;
}

/* Angka panjang (ribuan MT) tetap membungkus di dalam kartunya, bukan
   melebarkan halaman — lihat overflow-wrap. */
.metric-value { font-size: 22px; font-weight: 700; color: #1f2937; overflow-wrap: anywhere; }
.metric-value--na { font-size: 18px; font-weight: 600; color: #6b7280; }
.metric-note { font-size: 12px; color: #6b7280; line-height: 1.5; overflow-wrap: anywhere; }
.metric-note b { color: #1f2937; }
.metric-note small { font-size: 11px; }

/* Kartu mutu: tumpukan yang sama dengan kartu angka lain, dengan garis tepi
   kiri sebagai penanda bahwa keempatnya satu kelompok. */
.quality-card { border-left: 3px solid #249360; }

/* Terendah/tertinggi BERTUMPUK, bukan berdampingan: pada 390px dua kolom
   angka berdesimal tiga akan saling mendorong keluar dari kartunya. */
.quality-range { display: flex; flex-direction: column; gap: 2px; margin: 2px 0; min-width: 0; }
.quality-range-item { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; min-width: 0; }
.quality-range-label { font-size: 12px; color: #6b7280; }
.quality-range-value { font-size: 14px; font-weight: 600; color: #1f2937; overflow-wrap: anywhere; }

.completeness-track { width: 100%; height: 8px; border-radius: 999px; background: #f3f4f6; overflow: hidden; }
.completeness-fill { height: 100%; border-radius: 999px; background: #249360; }

.detail-section { display: flex; flex-direction: column; gap: 10px; padding: 14px; border: 1px solid #e5e7eb; border-radius: 8px; min-width: 0; }
.section-title { margin: 0; font-size: 16px; font-weight: 700; color: #1f2937; }
/* Prosa server dirender UTUH: tanpa text-overflow, tanpa line-clamp, dan tanpa
   'baca selengkapnya'. overflow-wrap menjaga kalimat panjang tetap di dalam
   kartunya pada 390px alih-alih melebarkan halaman. */
.section-note { margin: 0; font-size: 14px; color: #6b7280; line-height: 1.6; overflow-wrap: anywhere; }
.section-note small { font-size: 13px; }
.section-note b { color: #1f2937; }
.section-note code { font-size: 13px; }

/* Bagian yang dapat dilipat — ditulis di sini, bukan memakai
   CollapsibleSection: tiap bagian butuh kontrol lipat ber-TESTID SENDIRI dan
   melipatnya harus MENGELUARKAN tabelnya dari DOM. Bawaannya TERBUKA. */
.recap-section { display: flex; flex-direction: column; gap: 10px; padding: 14px; border: 1px solid #e5e7eb; border-radius: 8px; min-width: 0; }
.recap-toggle {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  min-height: 44px;
  padding: 0;
  border: none;
  background: transparent;
  font-family: inherit;
  text-align: left;
  cursor: pointer;
}
.recap-toggle-title { font-size: 13px; font-weight: 700; color: #249360; text-transform: uppercase; letter-spacing: 0.04em; overflow-wrap: anywhere; }
.recap-toggle-chevron { flex: none; color: #249360; font-size: 14px; }
.recap-body { display: flex; flex-direction: column; gap: 10px; min-width: 0; }

.note-list { display: flex; flex-direction: column; gap: 10px; }
.note-item { display: flex; flex-direction: column; gap: 2px; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; min-width: 0; }
.note-meta { font-size: 12px; font-weight: 600; color: #6b7280; }
.note-text { margin: 0; font-size: 14px; color: #1f2937; line-height: 1.5; white-space: normal; overflow-wrap: anywhere; }

/* Tambahan di atas .detail-table / .num milik stacked-table.css — bukan
   penggantinya. */
.detail-table tfoot th, .detail-table tfoot td { font-weight: 700; color: #1f2937; border-bottom: none; }
.detail-table td small { font-weight: 500; color: #6b7280; }
.detail-table td { overflow-wrap: anywhere; }

.is-muted { color: #6b7280; }

.action-footer { display: flex; gap: 10px; margin-top: auto; flex-wrap: wrap; }
.action-button { min-height: 44px; padding: 0 16px; border-radius: 8px; font-size: 14px; font-weight: 600; font-family: inherit; cursor: pointer; box-sizing: border-box; }
.action-button--primary { border: 1px solid #249360; background: #249360; color: #ffffff; }
.action-button--secondary { border: 1px solid #e5e7eb; background: #ffffff; color: #1f2937; }
.action-button:disabled { opacity: 0.5; cursor: not-allowed; }
.action-button[aria-busy='true']:disabled {
  /* Tombol yang sedang mengekspor tetap terbaca sebagai tombol, bukan sebagai
     kendali yang mati. */
  opacity: 0.75;
}
</style>
