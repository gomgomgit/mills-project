<script setup lang="ts">
/**
 * LaporanSolidWasteDisposalView — screen-163--laporan-solid-waste-disposal-mobile /
 * usecase-169--laporan-solid-waste-disposal-mobile "Lihat Laporan Periode
 * Solid Waste Disposal (Mobile)" (dipasang di /reports/solid-waste-disposal,
 * nama rute `report-solid-waste-disposal`, meta.public = false).
 * Aktor: Station Operator, Supervisor, Mill Management, Admin.
 *
 * Kembaran mobile KELIMA BELAS, dan BACAAN SAJA: layar ini tidak punya satu
 * pun jalur yang mengubah data stasiun, dan tidak menulis ke localDb.
 *
 * ────────────────────────────────────────────────────────────────────────
 * NOL PERUBAHAN BACKEND — DIBAYAR LEBIH DULU, DENGAN SENGAJA
 * ────────────────────────────────────────────────────────────────────────
 * Ketiga rute data /api/solid-waste-disposal-reports/* SUDAH menerima Operator
 * sejak commit pertama screen-162 — justru supaya layar ini tidak perlu
 * melebarkan akses apa pun — dan /api/production-lines/options-for-report
 * dipakai ulang VERBATIM dari screen-135. Tidak satu berkas di backend/
 * disentuh untuk layar ini, dan tidak ada entri api-index baru. Bila sebuah
 * test backend memerah, sebabnya ada di tempat lain. Konsekuensi lain yang
 * disengaja: TIDAK ADA integration test untuk layar ini — bukan karena
 * dilewatkan, melainkan karena tidak ada kontrak baru untuk diuji.
 *
 * ────────────────────────────────────────────────────────────────────────
 * PER-TRANSAKSI, BUKAN BERGRID — PEMBEDA TERPENTING DARI LAYAR SEBELUMNYA
 * ────────────────────────────────────────────────────────────────────────
 * Satu baris detail adalah SATU MUATAN KELUAR: satu kendaraan limbah padat
 * yang meninggalkan pabrik. Stasiun ini TIDAK punya grid slot waktu, jadi
 * TIDAK ADA satu pun kartu minimum/rata-rata/maksimum per slot di sini —
 * berbeda dari screen-161 (Process Water mobile), yang bergrid 24 slot dengan
 * sembilan metrik min/avg/max. Bentuk layar itu adalah tetangga terdekat dan
 * karena itu paling mudah tersalin; salinan itu akan salah. Yang ada di sini:
 * lima kartu angka pokok, EMPAT rekap kategoris, dua penghitung jejak
 * kepatuhan, rekap harian per HARI BERMUATAN, dan tiga prosa ketiadaan.
 *
 * ────────────────────────────────────────────────────────────────────────
 * LAYAR INI TIDAK MENGHITUNG SATU AGREGAT PUN, DAN ITU BENTUKNYA
 * ────────────────────────────────────────────────────────────────────────
 * Angka mobile WAJIB sama persis dengan angka web (screen-162), dan yang
 * menjaminnya BUKAN disiplin melainkan BENTUK: layar ini membaca /summary dan
 * MEMILIH field untuk ditampilkan. Tidak ada penjumlahan, tidak ada
 * perata-rataan, tidak ada pembagian, tidak ada pengurutan, tidak ada
 * pengelompokan, dan tidak ada pembulatan ulang di berkas ini. Setiap kali
 * klien menghitung sendiri, ia menjadi rumus KEDUA yang dapat menyimpang tanpa
 * ada yang membandingkannya.
 *
 * Konsekuensi yang terlihat di kode: tidak ada satu pun `reduce`, `sort`,
 * `filter`, atau operator `/` yang menyentuh angka laporan di seluruh berkas
 * ini. `daily_total` dibaca dari payload — BUKAN dijumlahkan dari `daily[]`;
 * `missing_net_weight_load_count` dibaca — BUKAN diturunkan dari
 * `load_count − net_weight_load_count`; `completeness.percent` dibaca — BUKAN
 * dihitung dari `days_with_load / days_counted`; dan jumlah muatan tiap rekap
 * tidak pernah dijumlahkan ulang untuk "diperiksa".
 *
 * ────────────────────────────────────────────────────────────────────────
 * URUTAN BARIS MILIK SERVER
 * ────────────────────────────────────────────────────────────────────────
 * Keempat rekap sudah diurutkan server (total netto DESC → jumlah muatan DESC
 * → nilai ASC) dan rekap harian urut tanggal naik. KLIEN TIDAK MENGURUTKAN
 * ULANG: mengurutkan ulang akan membuat baris yang sama tampil berbeda di dua
 * platform untuk periode yang sama. Akibat yang paling mudah disalahpahami:
 * baris 'tidak tercatat' yang bertonase besar MUNCUL DI ATAS, bukan terkubur
 * di baris terakhir — dan itu benar, karena 300 MT yang tidak diketahui
 * tujuannya adalah justru baris yang paling perlu dibaca. Pemetaan null →
 * 'tidak tercatat' adalah SATU-SATUNYA transformasi tampilan yang diizinkan
 * pada rekap, dan ia TIDAK memindahkan barisnya.
 *
 * ────────────────────────────────────────────────────────────────────────
 * NULL DICETAK '—' PER MEDAN, BUKAN LEWAT SATU BAWAAN
 * ────────────────────────────────────────────────────────────────────────
 * Diperiksa PER FIELD lewat formatWeight()/formatCount()/formatPercent(),
 * bukan lewat sebuah default `?? 0` di satu tempat — default seperti itu akan
 * mengubah "tidak diketahui" menjadi "tidak ada beban" di SELURUH layar
 * sekaligus. Nol yang sungguhan tetap dicetak 0 (dua penghitung kepatuhan dan
 * penyebut netto yang nol adalah contohnya), dan '0,00 MT' tidak pernah muncul
 * di blok berat.
 *
 * ANGKA DIRENDER TANPA SATUAN DI DALAM ELEMEN BER-TESTID-NYA: satuan 'MT' dan
 * tanda '%' hidup di elemen SEBELAHNYA. Dengan begitu elemen angka dapat
 * diasersi dengan kesetaraan PENUH ('37,5', '—') tanpa ikut memindai label di
 * sekitarnya — dan '—' tidak pernah berdiri di samping satuan yang tidak ada
 * angkanya.
 *
 * Desimalnya minimal satu dan maksimal dua (37,5 / 9,9 / 7,25 / 55,0),
 * sengaja BUKAN jumlah desimal tetap seperti blade screen-162 yang mencetak
 * total 1 desimal dan rata-rata 2: nilainya sama, yang berbeda hanya nol di
 * belakang yang tidak membawa informasi di layar selebar 390px.
 *
 * ────────────────────────────────────────────────────────────────────────
 * PROSA SERVER DIRENDER UTUH
 * ────────────────────────────────────────────────────────────────────────
 * `_note_no_hourly`, `_note_shift_not_published`, `_note_absences`,
 * `compliance._note` dan `daily_total._note` dirender VERBATIM di elemen
 * TERSENDIRI, tanpa satu kata tambahan di dalamnya, tanpa elipsis, tanpa
 * line-clamp, dan tanpa 'baca selengkapnya'. Kalimat-kalimat itu disusun
 * SERVER justru supaya penjelasan yang sama muncul di web dan di mobile;
 * memparafrasekannya di klien berarti membuat dua penjelasan untuk satu
 * keadaan, dan keterangan yang dipotong di tengah berhenti menerangkan apa
 * pun.
 *
 * KONSEKUENSI UJI: `_note_no_hourly` MEMUAT kata 'jam' dan
 * `_note_shift_not_published` memuat kata 'shift', keduanya dirender utuh —
 * jadi ketiadaan blok per jam dan ketiadaan kolom shift HANYA dapat
 * diasersikan lewat TESTID, NAMA KUNCI PAYLOAD dan ROLE, tidak pernah lewat
 * sapuan teks halaman. Tidak ada satu pun testid berawalan `swd-hourly`, tidak
 * ada `swd-busiest-hour`, tidak ada `swd-empty-hour-count`, tidak ada
 * `swd-recap-shift`/`swd-shift-column`/`swd-shift-filter`, dan tidak ada
 * JUDUL apa pun yang memuat 'per jam' di berkas ini.
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
 * ADMIN ADALAH SATU-SATUNYA PERAN YANG MELIHAT PEMILIH MILL, dan ini pembeda
 * dari screen-161 yang tidak punya pemilih mill sama sekali. Ketiga peran lain
 * terikat business_unit_id akunnya, `business_unit_id` tidak pernah masuk query
 * string untuk mereka, dan /business-units/options TIDAK PERNAH dipanggil atas
 * nama mereka — selain percuma (server menjawab 403), permintaan itu akan
 * membentuk daftar SELURUH mill di perangkat orang yang tidak berhak melihat
 * satu pun di antaranya.
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
 * miliknya sendiri. Kartu angka BERTUMPUK satu kolom, dan keempat rekap
 * kategoris serta rekap harian dibungkus CollapsibleSection — melipatnya MURNI
 * PENYINGKAPAN: datanya sudah ada di memori, tidak satu permintaan pun dipicu,
 * dan tidak satu angka pun berubah.
 *
 * DISIPLIN KELAS CSS: tiap kelas yang dipakai markup punya definisi di blok
 * <style> di bawah atau di stylesheet bersama yang dirujuknya
 * (src/assets/stacked-table.css untuk .detail-table / .num). Defek lama
 * SENGAJA tidak disalin: LaporanDepricarpingView.vue membungkus angka dengan
 * kelas .metric-values yang tidak terdefinisi di satu berkas pun di src/, dan
 * kelas tanpa definisi tidak memerahkan satu test pun — ia hanya menjawab
 * 'visible' ketika gaya yang diharapkan ditanyakan.
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
import CollapsibleSection from '@/components/CollapsibleSection.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import solidWasteDisposalReportRepo, {
  type SolidWasteDisposalReportBreakdownRow,
  type SolidWasteDisposalReportBusinessUnitOption,
  type SolidWasteDisposalReportCompleteness,
  type SolidWasteDisposalReportCompliance,
  type SolidWasteDisposalReportDailyRow,
  type SolidWasteDisposalReportDailyTotal,
  type SolidWasteDisposalReportLoads,
  type SolidWasteDisposalReportPeriodOption,
  type SolidWasteDisposalReportSummary,
  type SolidWasteDisposalReportTextEntry,
} from '@/services/solidWasteDisposalReportRepo'

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
 * SolidWasteDisposalReportService::resolveBusinessUnit().
 */
const isAdmin = computed(() => role.value === 'admin')

/** Mill akun — hanya bermakna bagi peran yang terikat satu mill. */
const accountBusinessUnitId = computed(() => authStore.currentUser?.business_unit_id ?? null)

/** Mill pilihan Admin. Selalu null bagi ketiga peran lain. */
const selectedBusinessUnitId = ref<string | null>(null)

const businessUnits = ref<SolidWasteDisposalReportBusinessUnitOption[]>([])

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
 * Daftar line diambil lewat solidWasteDisposalReportRepo.fetchLines(), yang
 * MENDELEGASIKAN ke productionLineRepo.fetchProductionLinesForReport() —
 * endpoint screen-135 yang dipakai ulang verbatim dan tidak diduplikasi di
 * bawah prefix solid-waste-disposal-reports. business_unit_id ikut HANYA untuk
 * Admin.
 */
async function loadProductionLines(useRemembered = true): Promise<void> {
  loadingProductionLines.value = true
  productionLineFetchFailed.value = false

  try {
    const lines = (await solidWasteDisposalReportRepo.fetchLines(scope.value)) ?? []

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

const periods = ref<SolidWasteDisposalReportPeriodOption[]>([])
const selectedPeriodId = ref<string | null>(null)
const summary = ref<SolidWasteDisposalReportSummary | null>(null)

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
 *                50.000 baris muatan terlampaui, dan pesannya ditampilkan APA
 *                ADANYA — layar ini tidak mencoba memotong, memecah, atau
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
 * bersama sebuah keadaan galat, pada keempat cabang tanpa kecuali.
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
    businessUnits.value = await solidWasteDisposalReportRepo.fetchBusinessUnits()
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
    periods.value = await solidWasteDisposalReportRepo.fetchPeriods(scope.value)
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
    const result = await solidWasteDisposalReportRepo.fetchSummary(periodId, scope.value)

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
  // dapat dipertanggungjawabkan asalnya.
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

  return `laporan-solid-waste-disposal_${slug || 'periode'}_${lineSlug || 'line'}.csv`
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
    // Isi CSV dibentuk SERVER: satu baris per muatan, lima kolom konteks
    // diulang verbatim, lalu ketujuh belas kolom detail. Layar ini tidak pernah
    // menyusun berkasnya dari angka yang tampil. Nama berkas diambil SAAT
    // ekspor diminta: bila pengguna mengganti periode selama berkas diunduh,
    // isinya tetap milik periode yang diekspor, jadi namanya pun harus milik
    // periode itu. format=csv SELALU dan EKSPLISIT.
    const filename = exportFilename()
    const blob = await solidWasteDisposalReportRepo.exportCsv(periodId, scope.value)
    solidWasteDisposalReportRepo.saveCsvFile(blob, filename)
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

const loads = computed<SolidWasteDisposalReportLoads | null>(() => summary.value?.loads ?? null)
const compliance = computed<SolidWasteDisposalReportCompliance | null>(
  () => summary.value?.compliance ?? null,
)
const completeness = computed<SolidWasteDisposalReportCompleteness | null>(
  () => summary.value?.completeness ?? null,
)
const dailyRows = computed<SolidWasteDisposalReportDailyRow[]>(() => summary.value?.daily ?? [])
const dailyTotal = computed<SolidWasteDisposalReportDailyTotal | null>(
  () => summary.value?.daily_total ?? null,
)
const draftLoadCount = computed<number | null>(() => summary.value?.draft_load_count ?? null)

/**
 * DIRENDER HANYA KETIKA > 0 — sama dengan web. Kolom `event_date` NOT NULL di
 * basis data, jadi angkanya selalu 0 dalam praktik; mencetak "nol muatan tanpa
 * tanggal" pada setiap laporan berarti mengarang kekhawatiran atas keadaan yang
 * dilarang skema, dan pembaca yang melihatnya akan mencari masalah yang tidak
 * ada. Blok ini karena itu TIDAK HADIR pada angka 0 — bukan hadir bernilai 0.
 */
const undatedLoadCount = computed<number>(() => summary.value?.undated_load_count ?? 0)
const showUndated = computed(() => undatedLoadCount.value > 0)

/**
 * KEDUA DAFTAR TEKS dibaca sebagai DUA variabel TERPISAH dan TIDAK PERNAH
 * disatukan: satu muatan yang mengisi kedua kolom muncul di KEDUA daftar, dan
 * itu benar — menyatukannya akan menampilkan muatan itu dua kali di dalam satu
 * daftar dan mencampur dua pertanyaan yang berbeda (apa yang ditemukan vs apa
 * yang dicatat petugas).
 */
const findingEntries = computed<SolidWasteDisposalReportTextEntry[]>(
  () => summary.value?.text_entries?.findings ?? [],
)
const remarkEntries = computed<SolidWasteDisposalReportTextEntry[]>(
  () => summary.value?.text_entries?.remarks ?? [],
)

/** Prosa SERVER, dirender VERBATIM — klien tidak menyusun kalimatnya sendiri. */
const complianceNote = computed(() => compliance.value?._note ?? '')
const dailyTotalNote = computed(() => dailyTotal.value?._note ?? '')
const noteNoHourly = computed(() => summary.value?._note_no_hourly ?? '')
const noteShiftNotPublished = computed(() => summary.value?._note_shift_not_published ?? '')
const noteAbsences = computed(() => summary.value?._note_absences ?? '')

/**
 * KEEMPAT REKAP KATEGORIS — markup tabelnya ditulis SEKALI lalu di-loop atas
 * daftar ini, bukan disalin empat kali: empat tabel hasil salin-tempel adalah
 * tempat paling mudah sebuah kolom (biasanya PENYEBUT netto) tertinggal pada
 * salah satunya saja.
 *
 * Urutannya adalah urutan payload. Judul dan label kolomnya mengikuti versi
 * web (screen-162) supaya pembaca yang berpindah dari ponsel ke web tidak
 * perlu mempelajari ulang peta halamannya.
 */
type RecapKey = 'by_waste_type' | 'by_source_station' | 'by_disposal_site' | 'by_purpose_end_use'

interface RecapBlock {
  key: RecapKey
  testid: string
  title: string
  /** Judul kolom nilai — nama kolom DETAIL-nya disebut di keterangan, bukan di judul. */
  valueLabel: string
  /** Kalimat pelengkap keterangan: "kolom <…> kosong pada muatan itu". */
  emptyLabel: string
  hint: string
}

const RECAPS: RecapBlock[] = [
  {
    key: 'by_waste_type',
    testid: 'swd-recap-waste-type',
    title: 'Rekap per Jenis Limbah Padat',
    valueLabel: 'Jenis limbah padat',
    emptyLabel: 'jenis limbah padat',
    hint: 'limbah apa yang keluar',
  },
  {
    key: 'by_source_station',
    testid: 'swd-recap-source-station',
    title: 'Rekap per Stasiun Asal',
    valueLabel: 'Stasiun asal',
    emptyLabel: 'stasiun asal',
    hint: 'dari bagian pabrik mana ia datang',
  },
  {
    key: 'by_disposal_site',
    testid: 'swd-recap-disposal-site',
    title: 'Rekap per Lokasi Pembuangan/Pemanfaatan',
    valueLabel: 'Lokasi pembuangan/pemanfaatan',
    emptyLabel: 'lokasi pembuangan/pemanfaatan',
    hint: 'ke mana ia pergi',
  },
  {
    key: 'by_purpose_end_use',
    testid: 'swd-recap-purpose-end-use',
    title: 'Rekap per Tujuan Penggunaan Akhir',
    valueLabel: 'Tujuan penggunaan akhir',
    emptyLabel: 'tujuan penggunaan akhir',
    hint: 'untuk apa ia dipakai di sana',
  },
]

/**
 * Baris sebuah rekap, APA ADANYA dari payload: nol pengurutan, nol
 * penggabungan, nol pemangkasan, dan nol kelompok 'lainnya'. Nilai yang muncul
 * SEKALI tetap mendapat barisnya sendiri, dan dua nilai yang berbeda hanya pada
 * besar-kecil huruf tetap dua baris — teks bebas tidak menjamin keduanya hal
 * yang sama, dan normalisasi yang tidak terlihat justru menyembunyikan data
 * master yang perlu dirapikan.
 */
function recapRows(block: RecapBlock): SolidWasteDisposalReportBreakdownRow[] {
  const payload = summary.value

  if (!payload) {
    return []
  }

  return payload[block.key] ?? []
}

/* ------------------------------------------------------------------ */
/* Keadaan layar                                                       */
/* ------------------------------------------------------------------ */

/**
 * LURING IKUT MENUTUP SELURUH ANGKA: layar menyatakan laporan perlu jaringan
 * DAN tidak merender satu angka pun bersamaan dengan pernyataan itu.
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
 * Periode sah tetapi tanpa satu muatan pun pada line terpilih — keadaan NYATA
 * di sebagian besar line hari ini. KERANGKA LAPORAN TETAP DIRENDER PENUH:
 * justru itu yang menjelaskan kekosongannya, dan yang memperlihatkan bahwa
 * pertanyaannya ADA dan jawabannya belum tercatat.
 */
const emptyPeriod = computed(() => {
  const value = loads.value

  if (!value) {
    return false
  }

  return value.load_count === 0
})

/**
 * KEHADIRAN NILAI, dipisah dari nilainya sendiri: `undefined` (blok tidak ada
 * pada respons) dan `null` (ada tetapi tidak diketahui) sama-sama berarti
 * "tidak ada angka untuk diberi satuan", sehingga pil satuan 'MT' / '%' tidak
 * pernah berdiri di samping tanda pisah. Diputuskan PER MEDAN — bukan lewat
 * satu bawaan untuk seluruh layar.
 */
const hasNetWeightTotal = computed(
  () => loads.value?.net_weight_total !== null && loads.value?.net_weight_total !== undefined,
)
const hasNetWeightAvg = computed(
  () => loads.value?.net_weight_avg !== null && loads.value?.net_weight_avg !== undefined,
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
 * Berat (MT) dan angka berdesimal lain. null WAJIB menjadi '—', TIDAK PERNAH 0
 * dan tidak pernah '0,00': nol berarti "terangkut dan hasilnya nol", sedangkan
 * '—' berarti "belum selesai ditimbang, beratnya tidak diketahui".
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
    // Muatan tanpa tanggal kejadian — kalimat yang sama dengan versi web.
    return 'tanpa tanggal kejadian'
  }

  const [year, month, day] = value.slice(0, 10).split('-')

  if (!year || !month || !day) {
    return value
  }

  return `${day} ${MONTHS_SHORT[Number(month) - 1] ?? month} ${year}`
}

/**
 * Nilai sebuah baris rekap. null → 'tidak tercatat', dan itu SATU-SATUNYA
 * transformasi tampilan yang diizinkan pada rekap — ia TIDAK memindahkan
 * barisnya, karena posisi baris itu milik server.
 */
function recapValueLabel(value: string | null): string {
  return value === null ? 'tidak tercatat' : value
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

function periodOptionLabel(period: SolidWasteDisposalReportPeriodOption): string {
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
  <main class="laporan-swd-view" data-testid="laporan-solid-waste-disposal-mobile">
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
        <span aria-current="page">Laporan Solid Waste Disposal</span>
      </nav>
      <h1 class="screen-title">Laporan Solid Waste Disposal</h1>
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
           NOL angka dirender. -->
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
        Mill ini belum memiliki periode pelaporan yang mencakup stasiun Solid Waste Disposal.
        Silakan hubungi Admin untuk membuat periode pelaporan terlebih dahulu. Ini bukan galat:
        periodenya memang belum pernah dibuat.
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

        <!-- Periode sah tetapi NOL muatan pada line terpilih. Keadaan NYATA di
             sebagian besar line hari ini, jadi kerangka laporannya tetap
             dirender penuh — bukan layar putih. Kalimatnya MENYEBUT periode dan
             line, supaya pembaca tahu kekosongan ini milik cakupan mana. -->
        <p v-if="emptyPeriod" class="notice" data-testid="swd-empty-period-note">
          Belum ada satu muatan keluar pun yang tercatat pada periode
          <strong>{{ summary?.period?.name }}</strong> di line
          <strong>{{ summary?.production_line?.name }}</strong
          >. Seluruh bagian laporan di bawah tetap ditampilkan — justru itu yang memperlihatkan
          bahwa pertanyaannya ada dan jawabannya belum tercatat, bukan bahwa jawabannya nol.
        </p>

        <!-- ===== ANGKA POKOK: LIMA KARTU, SATU KOLOM, BERTUMPUK =====
             Satu baris detail = SATU MUATAN KELUAR. Tidak ada satu pun kartu
             minimum/rata-rata/maksimum per slot waktu di layar ini: stasiun ini
             tidak punya grid slot waktu sama sekali. -->
        <section class="detail-section" data-testid="swd-loads">
          <h2 class="section-title">Limbah Keluar Periode Ini</h2>
          <p class="section-note" data-testid="swd-per-transaction-note">
            Laporan ini meringkas <strong>transaksi</strong>, bukan pembacaan berkala:
            <strong>satu baris detail adalah satu muatan keluar</strong> — satu kendaraan limbah
            padat yang meninggalkan pabrik. Jumlah muatan karena itu menghitung
            <strong>baris muatan</strong>, bukan dokumen catatan hariannya.
            <small>
              Berat bersih <strong>diturunkan server</strong> sebagai bruto dikurangi tara; ia tidak
              pernah diisi manusia, dan layar ini membacanya apa adanya tanpa menghitungnya ulang.
            </small>
          </p>

          <div class="metric-stack">
            <article class="metric-card" data-testid="swd-card-load-count">
              <div class="metric-label-row">
                <span class="metric-label">Jumlah Muatan Keluar</span>
                <span class="metric-unit-pill">muatan</span>
              </div>
              <span class="metric-value" data-testid="swd-kpi-load-count">{{
                formatCount(loads?.load_count)
              }}</span>
              <span class="metric-note">
                sepanjang {{ formatDate(summary?.period?.start_date) }} &ndash;
                {{ formatDate(summary?.period?.end_date) }} di line
                {{ summary?.production_line?.name }}
              </span>
              <span class="metric-note">
                <small>
                  Satu muatan = satu baris detail, bukan satu record. Angka ini menghitung
                  <strong>seluruh</strong> muatan, termasuk yang penimbangannya belum selesai.
                </small>
              </span>
            </article>

            <article class="metric-card" data-testid="swd-card-net-weight-total">
              <div class="metric-label-row">
                <span class="metric-label">Total Berat Bersih</span>
                <span v-if="hasNetWeightTotal" class="metric-unit-pill">MT</span>
              </div>
              <!-- '—' KETIKA null, dan tidak pernah 0,00: nol berarti tidak ada
                   beban terangkut, sedangkan tanda pisah berarti tidak
                   diketahui. Elemen ini memuat ANGKANYA SAJA — satuannya ada di
                   pil di atas, dan pil itu tidak dirender ketika angkanya tidak
                   ada. -->
              <span
                class="metric-value"
                :class="{ 'metric-value--na': loads?.net_weight_total === null }"
                data-testid="swd-kpi-net-weight-total"
                >{{ formatWeight(loads?.net_weight_total) }}</span
              >
              <span class="metric-note">
                dijumlahkan dari {{ formatCount(loads?.net_weight_load_count) }} muatan yang
                nettonya benar-benar terisi — {{ formatCount(loads?.missing_net_weight_load_count) }}
                muatan <strong>tidak ikut dijumlahkan</strong> karena nettonya belum ada
              </span>
            </article>

            <!-- PENYEBUT RATA-RATA DICETAK DI DALAM KARTU INI, bukan di tempat
                 lain di layar: rata-rata dari 6 muatan tidak boleh terlihat
                 sama meyakinkan dengan rata-rata dari 600, dan di ponsel
                 pembaca melihat SATU KARTU PADA SATU WAKTU sehingga kedekatan
                 itu tidak dapat diwariskan dari kartu sebelahnya. -->
            <article class="metric-card" data-testid="swd-card-net-weight-avg">
              <div class="metric-label-row">
                <span class="metric-label">Rata-rata Netto per Muatan</span>
                <span v-if="hasNetWeightAvg" class="metric-unit-pill">MT</span>
              </div>
              <span
                class="metric-value"
                :class="{ 'metric-value--na': loads?.net_weight_avg === null }"
                data-testid="swd-kpi-net-weight-avg"
                >{{ formatWeight(loads?.net_weight_avg) }}</span
              >
              <span class="metric-note">
                penyebutnya
                <b data-testid="swd-kpi-net-weight-load-count">{{
                  formatCount(loads?.net_weight_load_count)
                }}</b>
                muatan yang nettonya terisi — <strong>bukan</strong>
                {{ formatCount(loads?.load_count) }} muatan periode ini
              </span>
              <span class="metric-note">
                <small>
                  Membaginya dengan seluruh muatan akan menurunkan rata-rata secara palsu hanya
                  karena ada penimbangan yang belum selesai. Muatan yang nettonya kosong
                  <strong>tidak menurunkan rata-rata, ia hilang darinya</strong>. Angka ini dibaca
                  apa adanya dari server dan tidak pernah dibagi ulang di perangkat.
                </small>
              </span>
            </article>

            <!-- KARTUNYA SENDIRI, BUKAN CATATAN KAKI, dan kalimatnya berbunyi
                 'penimbangan belum selesai'. Netto adalah kolom turunan server,
                 jadi kosongnya berarti pekerjaan yang menggantung: kendaraan
                 sudah ditimbang kotor tetapi belum kembali ditimbang kosong,
                 atau sebaliknya. -->
            <article class="metric-card" data-testid="swd-card-missing-net-weight">
              <div class="metric-label-row">
                <span class="metric-label">Penimbangan Belum Selesai</span>
                <span class="metric-unit-pill">muatan</span>
              </div>
              <span class="metric-value" data-testid="swd-kpi-missing-net-weight">{{
                formatCount(loads?.missing_net_weight_load_count)
              }}</span>
              <span class="metric-note">
                netto kosong karena <strong>bruto atau tara belum terisi</strong> —
                <strong>penimbangan belum selesai</strong>, bukan nol
              </span>
              <span class="metric-note">
                ikut jumlah muatan — <strong>tidak</strong> ikut total maupun rata-rata netto
              </span>
            </article>

            <article class="metric-card" data-testid="swd-card-draft">
              <div class="metric-label-row">
                <span class="metric-label">Muatan Berstatus Draft</span>
                <span class="metric-unit-pill">muatan</span>
              </div>
              <span class="metric-value" data-testid="swd-draft-load-count">{{
                formatCount(draftLoadCount)
              }}</span>
              <span class="metric-note" data-testid="swd-draft-note">
                Muatan milik catatan berstatus draft <strong>IKUT terhitung</strong> pada seluruh
                angka laporan ini — jumlah muatan, total dan rata-rata netto, keempat rekap, kedua
                penghitung kepatuhan, dan rekap harian. Angka ini menyatakan berapa banyak di
                antaranya yang belum final; pembaca diberi tahu, bukan dilindungi dari
                mengetahuinya.
              </span>
            </article>
          </div>

          <!-- DIRENDER HANYA KETIKA > 0. Kolom tanggal kejadian NOT NULL di
               basis data, jadi angkanya selalu 0 dalam praktik; mencetak "0 di
               antaranya tanpa tanggal" pada setiap laporan berarti mengarang
               kekhawatiran atas keadaan yang dilarang skema. -->
          <p v-if="showUndated" class="section-note" data-testid="swd-undated-note">
            Dari {{ formatCount(loads?.load_count) }} muatan periode ini,
            <b data-testid="swd-undated-load-count">{{ formatCount(undatedLoadCount) }}</b>
            di antaranya <strong>tidak punya tanggal kejadian</strong> sehingga tidak dapat
            ditempatkan pada hari mana pun. Muatan seperti itu <strong>tetap ikut</strong> di jumlah
            muatan, di total serta rata-rata netto, dan di keempat rekap kategoris — tetapi
            <strong>tidak ikut</strong> di rekap harian. Itulah satu-satunya sebab penjumlahan rekap
            harian boleh lebih kecil daripada jumlah muatan periode.
          </p>
        </section>

        <!-- ===== JEJAK KEPATUHAN — BLOK TERSENDIRI =====
             Sengaja BUKAN di dalam blok angka pokok: ini bukan ukuran produksi
             melainkan penghitung KEHADIRAN dokumen angkut. Tidak pernah
             dirata-rata, tidak pernah diurutkan, dan tidak pernah digrafikkan —
             sebuah nomor segel tidak punya besaran. -->
        <section class="detail-section" data-testid="swd-compliance">
          <h2 class="section-title">Jejak Kepatuhan Dokumen Angkut</h2>
          <p class="section-note">
            Penghitung <strong>kehadiran</strong>, bukan ukuran. Nol pun
            <strong>tetap dicetak</strong> — nol itulah yang memberi tahu pembaca bahwa
            pemeriksaannya memang berjalan.
          </p>

          <div class="metric-stack">
            <article class="metric-card">
              <div class="metric-label-row">
                <span class="metric-label">Muatan Tanpa Nomor Gate Pass</span>
                <span class="metric-unit-pill">muatan</span>
              </div>
              <span class="metric-value" data-testid="swd-compliance-missing-gate-pass">{{
                formatCount(compliance?.missing_gate_pass_count)
              }}</span>
              <span class="metric-note">
                dari {{ formatCount(loads?.load_count) }} muatan periode ini
              </span>
            </article>

            <article class="metric-card">
              <div class="metric-label-row">
                <span class="metric-label">Muatan Tanpa Nomor Segel Keamanan</span>
                <span class="metric-unit-pill">muatan</span>
              </div>
              <span class="metric-value" data-testid="swd-compliance-missing-seal">{{
                formatCount(compliance?.missing_seal_count)
              }}</span>
              <span class="metric-note">
                dari {{ formatCount(loads?.load_count) }} muatan periode ini
              </span>
            </article>
          </div>

          <!-- Prosa milik payload, dirender UTUH: elemen ini memuat KALIMAT
               SERVER SAJA, tanpa satu kata tambahan di dalamnya. -->
          <p
            v-if="complianceNote"
            class="section-note"
            data-testid="swd-compliance-note"
          >{{ complianceNote }}</p>
        </section>

        <!-- ===== EMPAT REKAP KATEGORIS — SATU MARKUP, DI-LOOP =====
             Nilai APA ADANYA: nol join, nol pemetaan ke master, nol
             penerjemahan ke label. Keempat kolomnya teks bebas pada tabel
             detail, jadi nilai yang berbeda hanya pada besar-kecil huruf TETAP
             dua baris, dan nilai berfrekuensi satu tetap mendapat barisnya
             sendiri — tidak ada kelompok 'lainnya' di mana pun.

             DAPAT DILIPAT, dan melipatnya MURNI PENYINGKAPAN: datanya sudah ada
             di memori, tidak satu permintaan pun dipicu, dan tidak satu angka
             pun berubah. Tabelnya MENUMPUK MENJADI KARTU di lebar ponsel lewat
             v-stacked-table, sehingga halaman tidak pernah menggulir
             mendatar. -->
        <CollapsibleSection
          v-for="block in RECAPS"
          :key="block.testid"
          :title="`${block.title} (${recapRows(block).length} baris)`"
          :data-testid="block.testid"
        >
          <p class="section-note">
            {{ block.hint }} &middot; urut dari <strong>total netto terbesar</strong>, lalu jumlah
            muatan, lalu nilainya — <strong>urutan itu milik server</strong> dan tidak disusun ulang
            di perangkat.
          </p>

          <div class="detail-table-wrap">
            <table v-stacked-table class="detail-table" :data-testid="`${block.testid}-table`">
              <thead>
                <tr>
                  <th scope="col">{{ block.valueLabel }}</th>
                  <th scope="col" class="num">Muatan</th>
                  <th scope="col" class="num">Total netto (MT)</th>
                  <th scope="col" class="num">Muatan netto terisi</th>
                  <th scope="col" class="num">Rata-rata/muatan (MT)</th>
                </tr>
              </thead>
              <tbody>
                <!-- Baris value NULL TIDAK PERNAH DIBUANG dan TIDAK PERNAH
                     DIPINDAH: posisinya ditentukan tonasenya oleh server.
                     Membuangnya membuat kolom Muatan berhenti menjumlah ke
                     jumlah muatan periode, dan memindahkannya ke baris terakhir
                     mengubur kelompok bertonase besar yang justru paling perlu
                     dibaca. -->
                <tr
                  v-for="(row, index) in recapRows(block)"
                  :key="`${block.testid}-${index}-${row.value ?? 'null'}`"
                  :data-testid="`${block.testid}-row-${index}`"
                >
                  <td>
                    <span
                      :class="{ 'is-muted': row.value === null }"
                      :data-testid="`${block.testid}-value-${index}`"
                      >{{ recapValueLabel(row.value) }}</span
                    >
                  </td>
                  <td class="num" :data-testid="`${block.testid}-load-count-${index}`">
                    {{ formatCount(row.load_count) }}
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
                    :data-testid="`${block.testid}-net-weight-load-count-${index}`"
                  >
                    {{ formatCount(row.net_weight_load_count) }}
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
                    Tidak ada satu muatan pun pada periode {{ summary?.period?.name }} di line
                    {{ summary?.production_line?.name }}, jadi rekap ini belum punya baris.
                  </td>
                </tr>
              </tbody>
              <tfoot>
                <!-- Baris total DIBACA dari blok angka pokok, BUKAN dijumlahkan
                     dari baris-baris di atasnya. -->
                <tr :data-testid="`${block.testid}-total`">
                  <td>Total periode</td>
                  <td class="num">{{ formatCount(loads?.load_count) }}</td>
                  <td class="num">{{ formatWeight(loads?.net_weight_total) }}</td>
                  <td class="num">{{ formatCount(loads?.net_weight_load_count) }}</td>
                  <td class="num">{{ formatWeight(loads?.net_weight_avg) }}</td>
                </tr>
              </tfoot>
            </table>
          </div>

          <p class="section-note" :data-testid="`${block.testid}-note`">
            Kolom Muatan menjumlah tepat ke {{ formatCount(loads?.load_count) }} muatan, termasuk
            baris <strong>tidak tercatat</strong> yang <strong>berdiri sendiri</strong> dan tidak
            pernah dilipat ke baris mana pun — muatan yang kolom {{ block.emptyLabel }}-nya kosong
            adalah fakta yang berbeda dari muatan yang diatribusikan ke sebuah nilai. Nilainya
            ditampilkan <strong>apa adanya</strong>, tanpa penerjemahan ke label dan tanpa
            penyatuan dua ejaan yang hanya berbeda besar-kecil huruf.
          </p>
        </CollapsibleSection>

        <!-- ===== REKAP HARIAN =====
             Satu baris per HARI BERMUATAN, bukan per hari kalender. Hari tanpa
             pengangkutan sengaja TIDAK dibuatkan baris nol: laporan ini
             per-transaksi, dan hari tanpa pengangkutan adalah keadaan normal —
             mencetak 22 baris nol akan mengubur 9 baris yang berarti. -->
        <CollapsibleSection
          :title="`Rekap Harian (${dailyRows.length} tanggal bermuatan)`"
          data-testid="swd-daily-recap"
        >
          <p class="section-note">
            Satu kartu per <strong>hari bermuatan</strong> beserta kartu
            <strong>Total harian</strong>. Sel kosong dirender &lsquo;&mdash;&rsquo; —
            <strong>bukan 0</strong>, karena nol adalah hasil timbang dan kosong bukan.
          </p>

          <div class="detail-table-wrap">
            <table v-stacked-table class="detail-table" data-testid="swd-daily-table">
              <thead>
                <tr>
                  <th scope="col">Tanggal</th>
                  <th scope="col" class="num">Muatan</th>
                  <th scope="col" class="num">Total netto (MT)</th>
                  <th scope="col" class="num">Muatan netto terisi</th>
                  <th scope="col" class="num">Rata-rata/muatan (MT)</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="row in dailyRows"
                  :key="`daily-${row.date}`"
                  :data-testid="`swd-daily-row-${row.date}`"
                >
                  <td>{{ formatDate(row.date) }}</td>
                  <td class="num" :data-testid="`swd-daily-load-count-${row.date}`">
                    {{ formatCount(row.load_count) }}
                  </td>
                  <td class="num" :class="{ 'is-muted': row.net_weight_total === null }">
                    {{ formatWeight(row.net_weight_total) }}
                  </td>
                  <td class="num is-muted" :data-testid="`swd-daily-net-weight-load-count-${row.date}`">
                    {{ formatCount(row.net_weight_load_count) }}
                  </td>
                  <td class="num" :class="{ 'is-muted': row.net_weight_avg === null }">
                    {{ formatWeight(row.net_weight_avg) }}
                  </td>
                </tr>
                <tr v-if="dailyRows.length === 0" data-testid="swd-daily-empty">
                  <td colspan="5" class="is-muted">
                    Tidak ada satu muatan bertanggal pun pada periode
                    {{ summary?.period?.name }} di line {{ summary?.production_line?.name }}.
                  </td>
                </tr>
              </tbody>
              <tfoot>
                <!-- DIBACA dari payload (`daily_total`), BUKAN dijumlahkan dari
                     kolom di atasnya: keduanya memang dapat berbeda, tepatnya
                     sebesar muatan tanpa tanggal kejadian, dan perbedaan itu
                     adalah informasi. -->
                <tr data-testid="swd-daily-total">
                  <td>Total harian</td>
                  <td class="num" data-testid="swd-daily-total-load-count">
                    {{ formatCount(dailyTotal?.load_count) }}
                  </td>
                  <td class="num" data-testid="swd-daily-total-net-weight-total">
                    {{ formatWeight(dailyTotal?.net_weight_total) }}
                  </td>
                  <td class="num" data-testid="swd-daily-total-net-weight-load-count">
                    {{ formatCount(dailyTotal?.net_weight_load_count) }}
                  </td>
                  <td class="num" data-testid="swd-daily-total-net-weight-avg">
                    {{ formatWeight(dailyTotal?.net_weight_avg) }}
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>

          <p
            v-if="dailyTotalNote"
            class="section-note"
            data-testid="swd-daily-total-note"
          >{{ dailyTotalNote }}</p>
        </CollapsibleSection>

        <!-- ===== KELENGKAPAN PENCATATAN =====
             BAGIAN DARI LAPORAN, BUKAN CATATAN KAKI: pembaca perlu tahu
             seberapa jauh angka di atas dapat diandalkan sebelum
             menindaklanjutinya. Diukur dalam HARI, bukan kolom. -->
        <section class="detail-section" data-testid="swd-coverage">
          <h2 class="section-title">Kelengkapan Pencatatan</h2>
          <p class="section-note">
            Penyebutnya <strong>hari periode yang sudah lewat</strong>, bukan seluruh panjang
            periode — periode yang masih berjalan tidak dihukum atas hari yang belum datang.
          </p>

          <div class="metric-stack">
            <div class="metric-card">
              <div class="metric-label-row">
                <span class="metric-label">Hari dengan Minimal Satu Muatan Keluar</span>
                <span v-if="hasCoveragePercent" class="metric-unit-pill">%</span>
              </div>
              <!-- Elemen ini memuat PERSENNYA SAJA supaya ia dapat diasersi
                   sendiri tanpa ikut memindai keterangan di sekitarnya; tanda
                   '%' ada di pil di atas dan tidak dirender ketika persennya
                   '—'. Nilainya DARI PAYLOAD — tidak diturunkan dari
                   days_with_load/days_counted di perangkat. -->
              <span
                class="metric-value"
                :class="{ 'metric-value--na': completeness?.percent === null }"
                data-testid="swd-coverage-percent"
                >{{ formatPercent(completeness?.percent) }}</span
              >
              <span class="metric-note">
                <b data-testid="swd-coverage-days-with-load">{{
                  formatCount(completeness?.days_with_load)
                }}</b>
                hari bermuatan dari
                <b data-testid="swd-coverage-denominator">{{
                  formatCount(completeness?.days_counted)
                }}</b>
                hari yang sudah lewat
                <template v-if="completeness?.days_in_period">
                  — panjang periodenya
                  <span data-testid="swd-coverage-days-in-period">{{
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
                data-testid="swd-coverage-not-started-note"
              >
                Periode ini <strong>belum dimulai</strong>, jadi belum ada satu hari pun yang
                diharapkan terisi dan kelengkapannya ditulis sebagai tanda pisah —
                <strong>bukan sebagai nol persen</strong>, yang akan terbaca sebagai kelalaian
                pencatatan.
              </span>
              <span
                v-else-if="periodRunning"
                class="metric-note"
                data-testid="swd-coverage-running-note"
              >
                <strong>Dihitung sampai hari ini, karena periode masih berjalan</strong> —
                {{ formatCount(completeness?.days_counted) }} hari periode sudah lewat. Hari yang
                belum terjadi tidak mungkin tercatat, jadi tidak ikut menjadi penyebut.
              </span>
            </div>
          </div>

          <p class="section-note" data-testid="swd-coverage-note">
            Kelengkapan mengukur <strong>hari</strong>, bukan kolom: berapa hari periode yang sudah
            lewat punya minimal satu muatan keluar. Rentangnya
            <strong>inklusif di kedua ujung</strong>, jadi hari pertama dan hari terakhir ikut
            dihitung. Hari tanpa satu muatan pun <strong>bukan hal yang sama</strong> dengan hari
            bermuatan nol MT: hari semacam itu tidak mendapat baris pada rekap harian.
            <small>
              Keanggotaan periode ditentukan oleh <strong>tanggal record induk</strong>, bukan
              tanggal kejadian tiap muatan — keduanya dapat berbeda, dan konsekuensinya rekap harian
              dapat memuat tanggal di luar rentang periode. Itu dibiarkan apa adanya: menyembunyikannya
              akan membuat total tidak cocok dengan rincian hariannya.
            </small>
          </p>
        </section>

        <!-- ===== DUA DAFTAR KEJADIAN TEKS BEBAS, DUA BAGIAN TERPISAH =====
             Pertanyaannya BERBEDA — apa yang ditemukan vs apa yang dicatat
             petugas — jadi keduanya tidak pernah disatukan dan tidak pernah
             bersarang satu di dalam yang lain. -->
        <section class="detail-section" data-testid="swd-findings">
          <h2 class="section-title">Rekap Temuan ({{ formatCount(findingEntries.length) }} muatan)</h2>
          <p class="section-note">
            Bagian ini menjawab <strong>apa yang ditemukan</strong>. Isinya diterbitkan
            <strong>sebagai teks beserta tanggalnya</strong>, dirender utuh tanpa pemotongan — tidak
            pernah disebar menjadi kategori dan tidak pernah dihitung sebagai metrik, karena kolomnya
            teks bebas dan menyebarnya akan memperlakukan prosa sebagai enum.
          </p>

          <p
            v-if="findingEntries.length === 0"
            class="section-note"
            data-testid="swd-findings-empty"
          >
            Tidak ada satu temuan pun yang dicatat pada periode dan line ini. Bagian ini tetap
            ditampilkan dengan keterangan ini — bukan dihilangkan dari layar, dan bukan daftar
            kosong tanpa penjelasan.
          </p>

          <div v-else class="note-list">
            <article
              v-for="(entry, index) in findingEntries"
              :key="`finding-${index}-${entry.date ?? 'null'}`"
              class="note-item"
              :data-testid="`swd-findings-row-${index}`"
              :data-date="entry.date ?? ''"
            >
              <span class="note-meta">{{ formatDate(entry.date) }}</span>
              <p class="note-text">{{ entry.value }}</p>
            </article>
          </div>
        </section>

        <section class="detail-section" data-testid="swd-remarks">
          <h2 class="section-title">Rekap Catatan ({{ formatCount(remarkEntries.length) }} muatan)</h2>
          <p class="section-note">
            Bentuk yang sama pada bagian yang <strong>TERPISAH</strong>: bagian ini menjawab
            <strong>apa yang dicatat petugas</strong>. Satu muatan yang mengisi kedua kolom sekaligus
            <strong>muncul pada kedua rekap</strong>; itu benar dan
            <strong>bukan penghitungan ganda</strong> — menyatukan keduanya justru akan menampilkan
            muatan itu dua kali di dalam satu daftar. Jumlah pada kedua judul karena itu
            <strong>tidak boleh dijumlahkan</strong>.
          </p>

          <p
            v-if="remarkEntries.length === 0"
            class="section-note"
            data-testid="swd-remarks-empty"
          >
            Tidak ada satu catatan pun yang dicatat pada periode dan line ini. Bagian ini tetap
            ditampilkan dengan keterangan ini, bukan dihilangkan dari layar.
          </p>

          <div v-else class="note-list">
            <article
              v-for="(entry, index) in remarkEntries"
              :key="`remark-${index}-${entry.date ?? 'null'}`"
              class="note-item"
              :data-testid="`swd-remarks-row-${index}`"
              :data-date="entry.date ?? ''"
            >
              <span class="note-meta">{{ formatDate(entry.date) }}</span>
              <p class="note-text">{{ entry.value }}</p>
            </article>
          </div>
        </section>

        <!-- ===== YANG TIDAK DITERBITKAN DI LAYAR INI =====
             Ketiga prosa SERVER dirender VERBATIM, masing-masing di elemennya
             sendiri tanpa satu kata tambahan di dalamnya. Ketiadaan yang tidak
             dijelaskan selalu dibaca sebagai bagian yang lupa dibuat; dan
             kalimat yang disusun dua kali di dua platform adalah kalimat yang
             akan berselisih. -->
        <section class="detail-section" data-testid="swd-absences">
          <h2 class="section-title">Yang Tidak Diterbitkan di Layar Ini</h2>

          <p
            v-if="noteNoHourly"
            class="section-note"
            data-testid="swd-note-no-hourly"
          >{{ noteNoHourly }}</p>

          <p
            v-if="noteShiftNotPublished"
            class="section-note"
            data-testid="swd-note-shift"
          >{{ noteShiftNotPublished }}</p>

          <p
            v-if="noteAbsences"
            class="section-note"
            data-testid="swd-note-absences"
          >{{ noteAbsences }}</p>

          <p class="section-note" data-testid="swd-export-scope-note">
            Kolom-kolom yang tidak muncul di satu angka pun di layar ini —
            <strong>giliran kerja, nomor tiket timbang, nomor kendaraan, nama pengemudi, nomor gate
            pass, nomor segel keamanan dan id operator</strong> — <strong>tetap ikut</strong> pada
            setiap baris berkas ekspor, karena ekspor adalah rincian
            <strong>mentah</strong> sedangkan layar ini ringkasannya.
            <small>
              Ekspornya <strong>CSV saja</strong>: tidak ada tombol .xlsx di layar ini dan tidak ada
              jalur kode yang dapat menyusun format lain, karena berkas .xlsx di perangkat lapangan
              tidak punya pembaca yang dapat diandalkan sementara CSV dapat dibuka di mana pun dan
              dikirim lewat pesan.
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
          data-testid="swd-export-csv"
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
   definisi tidak memerahkan satu test pun — ia hanya menjawab 'visible' ketika
   gaya yang diharapkan ditanyakan, dan defek itu nyaris terkirim pada
   pekerjaan sebelumnya di proyek ini. */

.laporan-swd-view {
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
   yang memaksa angka panjang keluar dari kartunya. */
.metric-card { display: flex; flex-direction: column; gap: 4px; padding: 14px; border: 1px solid #e5e7eb; border-radius: 8px; min-width: 0; }
.metric-label-row { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; flex-wrap: wrap; }
.metric-label { font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }

/* Pil satuan ('MT', 'muatan', '%') — satuan hidup DI SINI, di luar elemen
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

/* Angka panjang (jutaan MT) tetap membungkus di dalam kartunya, bukan
   melebarkan halaman — lihat overflow-wrap. */
.metric-value { font-size: 22px; font-weight: 700; color: #1f2937; overflow-wrap: anywhere; }
.metric-value--na { font-size: 18px; font-weight: 600; color: #6b7280; }
.metric-note { font-size: 12px; color: #6b7280; line-height: 1.5; overflow-wrap: anywhere; }
.metric-note b { color: #1f2937; }
.metric-note small { font-size: 11px; }

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
