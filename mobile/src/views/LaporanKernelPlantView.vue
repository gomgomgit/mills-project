<script setup lang="ts">
/**
 * LaporanKernelPlantView — screen-155--laporan-kernel-plant-mobile /
 * usecase-161--laporan-kernel-plant-mobile "Lihat Laporan Periode Kernel
 * Plant (Mobile)".
 *
 * Kembaran mobile KESEBELAS, dan BACAAN SAJA: layar ini tidak punya satu pun
 * jalur yang mengubah data stasiun.
 *
 * NOL PERUBAHAN BACKEND. Endpoint /api/kernel-plant-reports/* milik
 * screen-154 dipakai ulang apa adanya — ketiga rute data sudah menerima peran
 * mobile (termasuk Operator) sejak endpoint itu dibangun, karena kedua layar
 * direncanakan dalam satu seri. Isi laporannya SAMA PERSIS dengan versi web;
 * yang berubah hanya bentuknya pada layar sempit, dan apa yang TIDAK berubah
 * justru yang perlu dinyatakan.
 *
 * ────────────────────────────────────────────────────────────────────────
 * TANPA PEMILIH MILL, DAN ITU BUKAN PEMANGKASAN
 * ────────────────────────────────────────────────────────────────────────
 * Kedua aktor layar ini (Operator dan Supervisor) TERIKAT SATU MILL, jadi
 * business_unit_id tidak pernah dikirim: server menyelesaikan mill dari akun.
 * Kesepuluh layar laporan mobile sebelumnya masih membawa cabang Admin —
 * warisan layar laporan mobile pertama yang diport dari layar berpemilih mill
 * — dan cabang itu TIDAK ada di sini, sehingga mustahil ada jalur yang
 * mengirim parameter itu: gagal-tertutup secara KONSTRUKSI, bukan secara
 * penjagaan. Konsekuensinya disengaja pula:
 * /api/kernel-plant-reports/business-units/options tidak dipanggil dari mana
 * pun, karena rute itu memang Admin saja (403 untuk peran terikat mill) dan
 * menyerahkan daftar seluruh mill kepada Operator adalah justru kebocoran
 * yang dihindari. Admin dan Mill Management memakai layar WEB screen-154,
 * yang satu-satunya punya pemilih mill. Nama mill tetap DITAMPILKAN sebagai
 * keterangan — yang tidak ada hanyalah kemampuan MENGGANTINYA.
 *
 * ────────────────────────────────────────────────────────────────────────
 * SATU PERUBAHAN BENTUK, DAN DAFTAR ISINYA TIDAK IKUT BERUBAH
 * ────────────────────────────────────────────────────────────────────────
 * Tabel TUJUH kolom versi web menjadi kartu bertumpuk: pada 390px tabel itu
 * tidak muat tanpa menggulir mendatar. Tiap parameter menjadi satu kartu —
 * min / rata-rata / maks berdampingan DI DALAM kartu itu (bukan tiga kartu;
 * ketiganya satu pengukuran dilihat dari tiga sisi), lalu nama parameter
 * masternya, lalu penyebutnya, lalu kedua kolom target.
 *
 * YANG TIDAK IKUT BERUBAH ADALAH DAFTAR ISINYA. Memotong kolom penyebut atau
 * salah satu kolom target demi ruang adalah kehilangan yang paling mudah
 * terjadi dan paling sulit terlihat — dan di stasiun ini yang paling berisiko
 * dibuang adalah RENCANA TINDAKAN KOREKSI, karena teksnya paling panjang.
 * Tanpa kolom itu dua parameter yang sama-sama melewati targetnya tampak
 * menuntut tindakan yang sama, padahal tidak. Ia dirender PENUH, tidak
 * dilipat: melipatnya berarti pembaca harus tahu dulu bahwa ada yang perlu
 * dibuka.
 *
 * DUA KOLOM TARGET, BUKAN TIGA. Master Kernel Plant berbentuk tiga kolom
 * (equipment_parameter / target_benchmark / corrective_action_plan), BUKAN
 * bentuk empat kolom milik Depricarping: di sini tidak ada batas kritis dan
 * tidak ada akibat operasional, jadi tidak satu sel pun dirender untuk kolom
 * yang masternya tidak punya. Menyalin kartu Depricarping mentah-mentah akan
 * menghasilkan dua baris "belum terisi" yang tidak mungkin pernah terisi
 * siapa pun.
 *
 * TUJUH KARTU. Ketujuhnya tetap dirender termasuk yang kolomnya tidak pernah
 * terisi (angka "tidak tersedia", penyebut 0 slot): kartu yang hilang terbaca
 * sebagai "tidak ada parameter ini", padahal yang benar adalah "tidak ada
 * yang mengukurnya".
 *
 * ────────────────────────────────────────────────────────────────────────
 * EMPAT HAL YANG PALING MUDAH HILANG DI LAYAR SEMPIT, DAN KARENA ITU DIUJI
 * ────────────────────────────────────────────────────────────────────────
 * 1. DUA PASANGAN BERBAGI STANDAR, BUKAN SATU — inilah yang membedakan layar
 *    ini dari seluruh laporan mobile sebelumnya. Master memuat satu baris
 *    'Ripple Mill (Cracker)' untuk ripple_mill_1_amps dan ripple_mill_2_amps,
 *    dan satu baris 'Kernel Silo 1 & 2' untuk kernel_silo_1_temp_c dan
 *    kernel_silo_2_temp_c. Keempatnya tetap empat kartu dengan penyebut
 *    masing-masing — empat mesin fisik, dan merata-ratakan satu pasangan akan
 *    menyembunyikan ketidakseimbangan beban yang justru menjadi alasan
 *    parameter itu diukur. Di layar sempit keempat kartu itu bertumpuk
 *    berurutan, sehingga target yang identik muncul dua kali beruntun lalu
 *    dua kali lagi — terbaca sebagai data terduplikasi LEBIH KUAT LAGI
 *    daripada di tabel web, dan seseorang akan "membersihkannya". Karena itu
 *    layar MENYATAKAN bahwa angka pasangan TIDAK dirata-ratakan menjadi satu,
 *    beserta sebabnya. Daftar pasangannya DITURUNKAN dari
 *    target.shares_standard_with, tidak dipaku: markup yang mengistimewakan
 *    SATU pasangan benar di Depricarping mobile dan salah di sini, dan ripple
 *    mill atau silo ketiga harus ikut tertandai tanpa menyentuh berkas ini.
 *    Label saudara kolomnya pun diambil dari metrics[].label pada payload,
 *    bukan dari peta yang ditulis ulang di sini.
 *
 * 2. PENJAGA NULL PADA KETERANGAN ITU. Ketika nama parameter pada master
 *    disunting tangan sehingga tak lagi cocok dengan peta kolom,
 *    target.equipment_parameter bernilai null — mencetaknya apa adanya
 *    menghasilkan keterangan "master hanya memuat satu baris “” untuk
 *    keduanya": tanda kutip kosong yang MENGKLAIM sebuah baris master yang
 *    justru baru terlepas, tepat pada keadaan layar yang aturan "suntingan
 *    master harus terlihat" dibangun untuk itu. Nilai cadangan saja TIDAK
 *    CUKUP di sini: kalimatnya SENDIRI menjadi tidak benar, jadi yang
 *    berganti adalah PERNYATAANNYA, bukan hanya nilainya. Cacat persis itu
 *    ditemukan dan ditutup pada layar web screen-154; ia tidak dibawa masuk
 *    ke sini.
 *
 * 3. BLOK DOWNTIME BERUPA ANGKA, karena kernel_plant_details.downtime_minutes
 *    adalah kolom INTEGER (seperti Depricarping, dan tidak seperti
 *    Threshing/Pressing yang hanya punya teks). Total menit, jumlah slot yang
 *    mencatatnya, dan rata-rata per slot PENCATAT. Layar menyatakan bahwa
 *    slot tanpa catatan BUKAN nol menit, bahwa nol yang BENAR-BENAR tercatat
 *    IKUT dihitung, dan bahwa parameter ini tidak punya standar pada master
 *    sama sekali — tanpa itu, sel target yang kosong terbaca sebagai master
 *    yang belum diisi. Bila tidak ada satu pun slot mencatat, blok itu
 *    MENYATAKANNYA alih-alih mencetak 0 menit, yang akan terbaca seperti
 *    "stasiun tidak pernah berhenti". Teks bebasnya ada di bagian TERPISAH
 *    (Temuan): satu menjawab "berapa lama", satu "apa yang terlihat", dan
 *    menggabungkannya membuat slot yang punya keduanya terhitung dua kali
 *    pada satu pengertian.
 *
 * 4. SATU STANDAR MASTER MEMANG TIDAK PUNYA PENGUKURAN, DAN ITU KEADAAN
 *    TENANGNYA. Bagian "Standar Tanpa Pengukuran" di layar ini normalnya
 *    BERISI — 'Final Kernel Dirt', alasan 'no_column': tidak ada kolom kadar
 *    kotoran DI MANA PUN pada skema ini, bukan sekadar tidak ada di
 *    kernel_plant_details, sehingga tidak ada pemetaan yang dapat menutup
 *    selisihnya. Berlawanan dengan Depricarping, yang daftarnya normalnya
 *    kosong; daftar yang berisi di sini BUKAN tanda ada yang rusak. Bagian
 *    itu tetap DIGAMBAR WALAU KOSONG (dibaca dari all_targets_measured),
 *    karena bagian yang hilang tidak dapat dibedakan dari bagian yang belum
 *    pernah dibuat — dan ia pula tempat nama parameter master yang disunting
 *    tangan menjadi terlihat. Layar membaca targets_master_empty LEBIH DULU:
 *    kedua kunci itu dapat sama-sama menunjuk daftar kosong untuk sebab yang
 *    berlawanan.
 *
 * ────────────────────────────────────────────────────────────────────────
 * YANG DIWARISI DARI SERI, DINYATAKAN KARENA MENANGGUNG BEBAN
 * ────────────────────────────────────────────────────────────────────────
 * CAKUPAN PENCATATAN DIRENDER PALING ATAS. Di layar sempit pembaca melihat
 * lebih sedikit sekaligus, jadi apa yang dilihat lebih dulu menentukan lebih
 * banyak — dan periode yang terisi seperlima pun menghasilkan rata-rata yang
 * terlihat rapi.
 *
 * URUTAN CABANG KETERANGAN CAKUPAN: coverage.days_counted === 0 DIPERIKSA
 * SEBELUM coverage.period_running. Server menandai periode yang BELUM MULAI
 * sebagai masih berjalan juga, sehingga memeriksa period_running lebih dulu
 * membuat keterangan "belum mulai" tidak pernah terender — cacat yang nyata
 * terjadi pada tiga layar dan diperbaiki pada commit screen-148.
 *
 * PERSEN CAKUPAN DIRENDER TANDA PISAH HANYA KETIKA PENYEBUTNYA TIDAK
 * TERBENTUK (expected_slots nol: tidak ada unit ber-record, atau tidak ada
 * hari terhitung). Periode yang PUNYA record tetapi tanpa satu slot terisi
 * menghasilkan 0,0% dan itu benar — penyebutnya terbentuk, dan yang terukur
 * memang nol slot. Draf pertama spec layar web menyatakannya terbalik dan
 * sudah dikoreksi; kesalahan itu tidak diwarisi ke sini.
 *
 * PENYEBUT TIAP KARTU BERFORMAT "N dari M slot": N milik kartu itu sendiri
 * (metrics[].filled_slot_count) dan berbeda-beda antar kartu, sementara M
 * SERAGAM pada ketujuh kartu (coverage.filled_slots). Skema ini tidak punya
 * cara mengetahui M per kolom — ketujuh kolom ukur ada pada SETIAP baris
 * detail, jadi "unit mana mengukur parameter mana" bukan fakta yang tersimpan
 * di mana pun, dan M per kolom hanya bisa lahir dari karangan.
 *
 * JUMLAH SLOT TERISI PADA CAKUPAN DAPAT LEBIH BESAR daripada penyebut kartu
 * mana pun: slot dihitung terisi bila salah satu dari SEMBILAN kolom bacaan
 * terisi, dan dua di antaranya (menit downtime, temuan) bukan kolom ukur.
 * Yang dilampaui adalah N, BUKAN M. Itu benar, bukan ketidaksesuaian.
 *
 * TIDAK ADA PENANDAAN DI LUAR BATAS, dan keterangannya dirender UTUH, tidak
 * diringkas demi ruang. Ketiadaan yang tidak dijelaskan terbaca sebagai fitur
 * yang belum selesai. Diasersi MENURUT NAMA KELAS (.md-threshold, .is-danger,
 * .is-warning, .md-chip--danger), BUKAN menurut frasa — kalimat penjelasnya
 * sendiri memuat frasa "di luar batas", jadi asersi atas frasa akan selalu
 * hijau.
 *
 * PRODUCTION LINE WAJIB, dijaga di FUNGSI loadSummary() bukan hanya di
 * template — supaya jalur coba-lagi, ganti periode, dan pemulihan dari ingatan
 * perangkat tidak melewatinya. Ingatan line (msl_production_line_{userId})
 * dibagi dengan StationListView; urutan penyelesaian rute -> ingatan ->
 * satu-satunya line -> tidak ada; dan kegagalan penyimpanan (mode privat)
 * diperlakukan sebagai "tidak ada ingatan", bukan galat.
 *
 * EKSPOR HANYA CSV. .xlsx tidak ditawarkan karena menyimpan dan membukanya di
 * WebView ponsel menuntut penanganan berkas native yang belum ada di aplikasi
 * ini — sama dengan kesepuluh laporan mobile lain, dan bukan karena
 * endpoint-nya tidak mendukungnya (ia mendukung).
 */
import { computed, onMounted, ref } from 'vue'
import FilterPanel from '@/components/filters/FilterPanel.vue'
import LoadingState from '@/components/loading/LoadingState.vue'
import { createLatestRequestGuard } from '@/utils/latestRequest'
import BusyLabel from '@/components/loading/BusyLabel.vue'
import FilterSelectField from '@/components/filters/FilterSelectField.vue'
import FilterChip from '@/components/filters/FilterChip.vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { productionLineRepo, type ProductionLineOption } from '@/services/productionLineRepo'
import { useFloatingClockStore } from '@/stores/floatingClock'
import { useAiAssistantStore } from '@/stores/aiAssistant'
import CollapsibleSection from '@/components/CollapsibleSection.vue'
import StatusBadge from '@/components/StatusBadge.vue'
import kernelPlantReportRepo, {
  type KernelPlantReportPeriodOption,
  type KernelPlantReportSummary,
} from '@/services/kernelPlantReportRepo'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const floatingClockStore = useFloatingClockStore()
const aiAssistantStore = useAiAssistantStore()

/* ------------------------------------------------------------------ */
/* Mill — DITAMPILKAN, TIDAK DAPAT DIGANTI                             */
/* ------------------------------------------------------------------ */

/**
 * Akun terikat mill tetapi business_unit_id-nya kosong — layar berhenti total
 * di sini, tanpa satu pun permintaan HTTP, dan TIDAK menawarkan pemilih mill
 * sebagai gantinya. Keadaannya disebutkan sebabnya: hasil kosong tanpa
 * keterangan akan terbaca sebagai "mill ini tidak punya data".
 */
const noMillForAccount = ref(false)

const accountBusinessUnitId = computed(() => authStore.currentUser?.business_unit_id ?? null)

/* ------------------------------------------------------------------ */
/* Production Line — KONTEKS YANG DIPILIH, BUKAN IKATAN AKUN           */
/* ------------------------------------------------------------------ */

/**
 * MEMILIH LINE ITU WAJIB DI LAYAR INI, DAN TIDAK ADA OPSI "SEMUA LINE".
 *
 * Laporan memulangkan angka GABUNGAN satu periode. Menjumlahkan beberapa
 * Production Line ke dalam satu angka menghasilkan bilangan yang tidak dapat
 * ditindaklanjuti siapa pun. Karena itu tidak ada angka di layar ini sebelum
 * ada satu line yang berlaku — dan server pun menjawab 422 bila
 * production_line_id absen, bukan memulangkan angka seluruh mill.
 *
 * ── MEMAKAI ULANG INGATAN LINE MILIK LAYAR DAFTAR STASIUN ───────────────
 *
 * Kunci localStorage-nya SAMA PERSIS dengan yang ditulis StationListView.vue
 * (`msl_production_line_{userId}`) dan yang sudah dibaca
 * ReportingPilihStasiunView.vue. Line adalah konteks kerja satu orang pada
 * satu shift, bukan pengaturan per layar.
 *
 * Tetapi ingatan itu TIDAK CUKUP dijadikan satu-satunya sumber: laporan
 * dicapai lewat cabang navigasi yang lain (Home → Dashboard & Reporting →
 * Reporting → tile stasiun), sedangkan ingatannya ditulis di cabang
 * Home → Daftar Stasiun. Karena itu layar ini punya PEMILIHNYA SENDIRI, dan
 * ingatan hanyalah nilai awalnya.
 *
 * Urutan penentuan line yang berlaku (resolveProductionLine):
 *   1. `?production_line_id=` pada rute — dibawa screen-141 saat menekan tile.
 *   2. Ingatan localStorage — pilihan terakhir pengguna ini di perangkat ini.
 *   3. Tepat satu line di mill ini — tidak ada yang perlu dipilih.
 *   4. Selain itu: pemilih ditampilkan, dan TIDAK ADA ANGKA.
 *
 * Nilai dari (1) dan (2) TIDAK PERNAH dipercaya begitu saja — keduanya hanya
 * dipakai bila masih ada di daftar line yang baru diambil dari server. Line
 * yang dihapus, atau pengguna yang dipindah mill, kembali memunculkan pemilih
 * dan TIDAK ada line lain yang ditebak sebagai penggantinya.
 *
 * Daftar line diambil dari GET /api/production-lines/options-for-report TANPA
 * business_unit_id: endpoint itu menyelesaikan mill dari akun pemanggil, dan
 * kedua aktor layar ini memang terikat satu mill.
 */
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
    productionLines.value.length > 0 &&
    !selectedProductionLineId.value,
)

/** Tidak ada satu pun line yang dapat dipilih di layar ini. */
const productionLineBlocked = computed(
  () =>
    !noMillForAccount.value &&
    !loadingProductionLines.value &&
    productionLines.value.length === 0,
)

/**
 * DUA KEADAAN YANG MENUNTUT TINDAKAN BERBEDA, jadi dua pesan berbeda —
 * dan hanya yang kedua punya tombol Coba Lagi. Menyatukan keduanya akan
 * menyuruh pengguna menelepon Admin untuk masalah jaringan, atau sebaliknya
 * menyuruhnya mencoba lagi atas sesuatu yang tidak akan pernah berubah dengan
 * mencoba lagi.
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
 * perangkat yang sama. Sama persis dengan StationListView.vue.
 *
 * localStorage dibungkus try/catch mengikuti pola floatingClock.ts: pada mode
 * privat penyimpanan bisa melempar, dan gagal mengingat pilihan tidak boleh
 * mematahkan layar — kegagalannya diperlakukan sebagai "tidak ada ingatan",
 * bukan sebagai galat.
 */
function rememberedProductionLineKey(): string | null {
  const userId = authStore.currentUser?.id

  return userId ? `msl_production_line_${userId}` : null
}

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

/** Lihat urutan penentuan pada docblock blok ini. */
function resolveProductionLine(lines: ProductionLineOption[]): void {
  const fromRoute = lines.find((line) => line.id === routeProductionLineId())
  const remembered = lines.find((line) => line.id === readRememberedProductionLineId())
  const onlyOne = lines.length === 1 ? lines[0] : null

  const resolved = fromRoute ?? remembered ?? onlyOne ?? null

  selectedProductionLineId.value = resolved?.id ?? null

  if (resolved) {
    // Ditulis kembali supaya pilihan yang dibawa screen-141 ikut menjadi
    // ingatan — layar Daftar Stasiun dan layar laporan berbagi satu konteks.
    rememberProductionLineId(resolved.id)
  }
}

async function loadProductionLines(): Promise<void> {
  loadingProductionLines.value = true
  productionLineFetchFailed.value = false

  try {
    // TANPA business_unit_id: mill diselesaikan server dari akun, dan repo
    // laporan ini pun tidak punya jalur untuk mengirimkannya.
    const lines = await productionLineRepo.fetchProductionLinesForReport(null)

    productionLines.value = lines
    resolveProductionLine(lines)
  } catch {
    // Kegagalan di sini BUKAN kegagalan laporan: tidak memakai handleError()
    // supaya tidak menyamar sebagai galat ringkasan dan tidak menimpa
    // penanganan 401/jaringan milik laporan itu sendiri.
    productionLines.value = []
    selectedProductionLineId.value = null
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
 * Cakupan yang dikirim repo — SATU medan, dan tidak ada medan mill di
 * dalamnya untuk dapat salah terkirim.
 */
const scope = computed(() => ({
  productionLineId: selectedProductionLineId.value,
}))

/**
 * Nama mill yang sedang berlaku, sebagai keterangan.
 *
 * DIBACA DARI BLOK business_unit, bukan dari period.business_unit_name —
 * payload Kernel Plant mengirim KEDUANYA, dan memilih satu saja membuat
 * mustahil ada dua tempat yang menyimpang. Cadangannya mill akun, untuk
 * keadaan sebelum ringkasan pertama tiba.
 */
const currentMillName = computed(
  () => summary.value?.business_unit?.name || authStore.businessUnit?.name || '',
)

/* ------------------------------------------------------------------ */
/* Periode & ringkasan                                                 */
/* ------------------------------------------------------------------ */

const periods = ref<KernelPlantReportPeriodOption[]>([])
const selectedPeriodId = ref<string | null>(null)
const summary = ref<KernelPlantReportSummary | null>(null)

const loadingPeriods = ref(false)
/**
 * Daftar periode sudah pernah selesai dimuat (audit loading state
 * 2026-10-05). Tanpa ini "Mill ini belum memiliki periode" tampil selama
 * daftar line/periode MASIH dimuat — daftar kosong saat memuat terbaca
 * sebagai "tidak ada periode".
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
    !networkError.value &&
    !errorMessage.value &&
    periods.value.length === 0,
)

const selectedPeriod = computed(
  () => periods.value.find((item) => item.id === selectedPeriodId.value) ?? null,
)

/* ------------------------------------------------------------------ */
/* Galat                                                               */
/* ------------------------------------------------------------------ */

const networkError = ref<string | null>(null)
const errorMessage = ref<string | null>(null)

/** Aksi terakhir yang gagal — itulah yang diulang tombol Coba Lagi. */
const retryAction = ref<(() => Promise<void>) | null>(null)
const canRetry = computed(() => retryAction.value !== null)

/**
 * BENTUK GALAT DATAR. apiClient.normalizeError menolak dengan objek DATAR
 * `{ message, errors?, status? }` dan sudah MEMBUANG `response` di sana —
 * jadi `.response` tidak pernah ada pada galat yang sampai ke sini. Medan itu
 * karena itu tidak ditulis pada antarmuka ini: yang tidak dapat ditulis tidak
 * dapat salah.
 */
interface ErrorLike {
  status?: number
  message?: string
}

function statusOf(error: unknown): number | undefined {
  const candidate = error as ErrorLike | null

  return candidate?.status
}

function messageOf(error: unknown, fallback: string): string {
  const candidate = error as ErrorLike | null

  return candidate?.message || fallback
}

/**
 * Penanganan galat terpusat. Urutan cabangnya menentukan benar/salahnya layar:
 *   401 -> Login (bukan pesan jaringan, bukan tombol Coba Lagi)
 *   tanpa status -> penolakan tanpa respons = jaringan; periode terpilih
 *                   SENGAJA tidak disentuh
 *   sisanya -> pesan yang dapat dibaca pengguna
 */
function handleError(error: unknown, retry: (() => Promise<void>) | null): void {
  const status = statusOf(error)

  if (status === 401) {
    // Sesi berakhir. Diteruskan ke penjagaan sesi; layar ini TIDAK mengarang
    // pesan jaringan atas sesuatu yang bukan masalah jaringan — "periksa
    // koneksi Anda" pada sesi yang berakhir mengirim pembaca memeriksa
    // sinyalnya alih-alih masuk kembali.
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
    // selectedPeriodId dan line SENGAJA tidak direset: pengguna di area tanpa
    // sinyal tidak boleh dipaksa memilih dari awal, dan Coba Lagi harus
    // memuat ulang periode yang SAMA.
    return
  }

  errorMessage.value = messageOf(error, 'Gagal memuat laporan.')
  retryAction.value = retry
  summary.value = null
}

function clearErrors(): void {
  networkError.value = null
  errorMessage.value = null
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

async function loadPeriods(): Promise<void> {
  loadingPeriods.value = true

  try {
    periods.value = await kernelPlantReportRepo.fetchPeriods()
  } catch (error) {
    periods.value = []
    handleError(error, loadPeriods)
  } finally {
    loadingPeriods.value = false
    periodsLoaded.value = true
  }
}

async function loadSummary(): Promise<void> {
  const periodId = selectedPeriodId.value

  // Tidak ada satu angka pun sebelum ada line yang berlaku. Penjagaan ini ada
  // DI SINI, bukan hanya di template, supaya tidak ada jalur pemanggilan
  // (coba lagi, ganti periode, pemulihan dari ingatan perangkat) yang bisa
  // melewatinya — dan di layar ini melewatinya berarti 422 dari server, bukan
  // angka seluruh mill.
  if (!periodId || !selectedProductionLineId.value) {
    return
  }

  // Hanya respons permintaan TERBARU yang berlaku (audit 2026-10-05): ganti
  // periode/line dengan cepat bisa membuat respons lama tiba belakangan —
  // tanpa penjaga ini ia menimpa ringkasan baru, memunculkan galat basi, dan
  // mematikan indikator muat milik permintaan baru. Diselesaikan dengan
  // penanda permintaan terakhir, BUKAN dengan membatalkan yang lama.
  const isLatest = summaryRequests.next()
  loadingSummary.value = true

  try {
    const result = await kernelPlantReportRepo.fetchSummary(periodId, scope.value)
    if (isLatest()) {
      summary.value = result
    }
  } catch (error) {
    if (isLatest()) {
      handleError(error, loadSummary)
    }
  } finally {
    if (isLatest()) {
      loadingSummary.value = false
    }
  }
}

/**
 * Batalkan ringkasan yang sedang dimuat tanpa memulai yang baru — dipanggil
 * setiap kali pilihan (periode/line) berubah. Respons yang tiba kemudian
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

onMounted(async () => {
  if (!accountBusinessUnitId.value) {
    // Berhenti di sini. TIDAK ada permintaan apa pun, dan TIDAK ada pemilih
    // mill sebagai gantinya.
    noMillForAccount.value = true

    return
  }

  // Daftar line lebih dulu, periode menyusul — /periods memang TIDAK tersaring
  // line, tetapi ringkasan tidak dapat dimuat sebelum sebuah line berlaku.
  await loadProductionLines()
  await loadPeriods()
})

/* ------------------------------------------------------------------ */
/* Ekspor                                                              */
/* ------------------------------------------------------------------ */

function exportFilename(): string {
  const slug = (selectedPeriod.value?.name ?? 'periode')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/(^-|-$)/g, '')

  return `laporan-kernel-plant_${slug || 'periode'}.csv`
}

async function onExport(): Promise<void> {
  const periodId = selectedPeriodId.value

  // Ekspor mengikuti cakupan yang sama dengan angka di layar: tanpa line,
  // tidak ada berkas — berkas yang mencampur line adalah bentuk kesalahan
  // yang paling sulit dibantah setelah terkirim.
  if (!periodId || !selectedProductionLineId.value || exporting.value) {
    return
  }

  exporting.value = true

  try {
    // Isi CSV dibentuk SERVER (satu baris per slot waktu). Layar ini tidak
    // pernah menyusun berkasnya dari angka yang tampil. Nama berkas diambil
    // SAAT ekspor diminta: bila pengguna mengganti periode selama berkas
    // diunduh, isinya tetap milik periode yang diekspor, jadi namanya pun
    // harus milik periode itu (audit 2026-10-05).
    const filename = exportFilename()
    const blob = await kernelPlantReportRepo.exportCsv(periodId, scope.value)
    kernelPlantReportRepo.saveCsvFile(blob, filename)
  } catch (error) {
    // Kegagalan ekspor tidak dapat "diulang" secara bermakna oleh tombol Coba
    // Lagi yang sama, jadi retry-nya null.
    handleError(error, null)
  } finally {
    exporting.value = false
  }
}

/* ------------------------------------------------------------------ */
/* Turunan tampilan (tanpa perhitungan angka laporan)                  */
/* ------------------------------------------------------------------ */

const coverage = computed(() => summary.value?.coverage ?? null)
const metrics = computed(() => summary.value?.metrics ?? [])
const targetsWithoutMetric = computed(() => summary.value?.targets_without_metric ?? [])
const targetsMasterEmpty = computed(() => summary.value?.targets_master_empty ?? false)
const byKernelPlant = computed(() => summary.value?.by_kernel_plant ?? [])
const daily = computed(() => summary.value?.daily ?? [])
const dailyTotal = computed(() => summary.value?.daily_total ?? null)
const allTargetsMeasured = computed(() => summary.value?.all_targets_measured ?? false)
const downtime = computed(() => summary.value?.downtime ?? null)
const findings = computed(() => summary.value?.findings ?? [])
const totals = computed(() => summary.value?.total ?? null)

/**
 * Label manusia untuk sebuah nama kolom, dipakai keterangan standar-bersama
 * pada KEDUA pasangan (ripple mill dan kernel silo).
 *
 * DIAMBIL DARI PAYLOAD, bukan dari peta yang ditulis ulang di sini: label
 * sudah ada pada metrics[].label, jadi satu perubahan label di server tidak
 * akan meninggalkan dua ejaan di layar ini — dan pasangan ketiga mana pun
 * ikut terlabeli benar tanpa menyentuh berkas ini.
 */
const labelForColumn = (column: string): string =>
  metrics.value.find((metric) => metric.column === column)?.label ?? column

const hasReport = computed(
  () =>
    Boolean(summary.value) &&
    !networkError.value &&
    !errorMessage.value &&
    !productionLineRequired.value &&
    !noMillForAccount.value,
)

/**
 * Periode yang sah tetapi tanpa satu slot terisi. Dibaca dari has_data yang
 * dikirim server, BUKAN diturunkan dari panjang `daily`: sebuah periode bisa
 * punya record yang seluruh slotnya kosong, dan record semacam itu tetap
 * mendapat baris harian — yang berarti panjang `daily` tidak membedakan "ada
 * data" dari "tidak ada".
 */
const emptyPeriod = computed(() => Boolean(summary.value) && !(summary.value?.has_data ?? false))

/**
 * Ada tidaknya rekap yang pantas digambar. Tabel kosong akan terbaca sebagai
 * hasil pengukuran bernilai nol, padahal tidak ada yang diukur — sementara
 * blok CAKUPAN dan ketujuh KARTU PARAMETER tetap digambar: yang pertama
 * menjelaskan kekosongannya, dan yang kedua memperlihatkan bahwa
 * parameternya ADA dan tidak terukur.
 */
const showRecaps = computed(() => Boolean(summary.value?.has_data))

const MONTHS_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']

const NOT_AVAILABLE = 'tidak tersedia'

/** Titik ribuan, tanpa desimal. */
function formatCount(value: number | null | undefined): string {
  if (value === null || value === undefined) {
    return '-'
  }

  return new Intl.NumberFormat('id-ID').format(value)
}

/**
 * Bilangan berdesimal. null WAJIB menjadi "tidak tersedia", TIDAK PERNAH 0 —
 * nol berarti "terukur dan hasilnya nol", sedangkan tidak tersedia berarti
 * "tidak ada yang diukur".
 */
function formatDecimal(value: number | null | undefined, digits = 2): string {
  if (value === null || value === undefined) {
    return NOT_AVAILABLE
  }

  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
  }).format(value)
}

/** Varian untuk sel tabel, yang ruangnya sempit. */
function formatCell(value: number | null | undefined, digits = 2): string {
  if (value === null || value === undefined) {
    return 'tidak tercatat'
  }

  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
  }).format(value)
}

/**
 * Persen kelengkapan, satu desimal — '—' HANYA bila penyebutnya tidak
 * terbentuk (coverage_percent null). 0 tetap dirender '0,0%': penyebutnya
 * ada, dan yang terukur memang nol slot.
 */
function formatCompletenessPercent(value: number | null): string {
  if (value === null) {
    return '—'
  }

  return `${new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
  }).format(value)}%`
}

function formatDate(value: string | null | undefined): string {
  if (!value) {
    return '-'
  }

  const [year, month, day] = value.slice(0, 10).split('-')

  if (!year || !month || !day) {
    return value
  }

  return `${day} ${MONTHS_SHORT[Number(month) - 1] ?? month} ${year}`
}

/**
 * Penamaan unit kernel plant yang kosong. kernel_plant_id adalah kolom TEKS
 * yang diketik di layar input dan bukan kunci asing, jadi nilai kosong mungkin
 * saja sampai ke sini — barisnya tetap diberi label alih-alih dirender kosong,
 * karena sel tanpa label membuat seluruh baris terbaca sebagai cacat
 * tampilan. Label ini keputusan view, bukan sesuatu yang repo seragamkan.
 */
function formatGroupLabel(value: string | null | undefined): string {
  if (value === null || value === undefined || value.trim() === '') {
    return 'Belum diisi'
  }

  return value
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

function periodOptionLabel(period: KernelPlantReportPeriodOption): string {
  return period.station_type_label ? `${period.station_type_label} — ${period.name}` : period.name
}

/** Status periode adalah KETERANGAN, bukan gerbang: ekspor tetap aktif. */
const periodStatus = computed(
  () => summary.value?.period?.status ?? selectedPeriod.value?.status ?? null,
)

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
  <main class="laporan-pr-view" data-testid="laporan-kernel-plant-mobile">
    <header class="app-header">
      <div class="app-header-brand">
        <span class="brand-name">Mills Smart Log</span>
      </div>
      <button type="button" class="hamburger-button" data-testid="hamburger-button" @click="toggleNavMenu">
        &#9776;
      </button>
      <div v-if="isNavMenuOpen" class="nav-menu" data-testid="nav-menu">
        <button type="button" class="nav-menu-item" @click="goToChangePassword">Ganti Password</button>
        <button type="button" class="nav-menu-item" @click="floatingClockStore.toggle()">
          {{ floatingClockStore.enabled ? 'Nonaktifkan Jam Mengambang' : 'Aktifkan Jam Mengambang' }}
        </button>
        <button type="button" class="nav-menu-item" @click="aiAssistantStore.toggleBubble()">
          {{ aiAssistantStore.bubbleEnabled ? 'Nonaktifkan Bubble Chat AI' : 'Aktifkan Bubble Chat AI' }}
        </button>
        <button type="button" class="nav-menu-item" @click="openAiAssistant">Bantuan AI</button>
        <button type="button" class="nav-menu-item" data-testid="nav-menu-logout" @click="onLogout">Logout</button>
      </div>
    </header>

    <div class="screen-header">
      <nav class="breadcrumb" aria-label="Breadcrumb">
        <button type="button" class="breadcrumb-link" data-testid="breadcrumb-home" @click="goToHome">Home</button>
        <span aria-hidden="true">/</span>
        <button type="button" class="breadcrumb-link" @click="goToDashboardReporting">Dashboard &amp; Reporting</button>
        <span aria-hidden="true">/</span>
        <span aria-current="page">Laporan Kernel Plant</span>
      </nav>
      <h1 class="screen-title">Laporan Kernel Plant</h1>
    </div>

    <!-- Akun terikat mill tetapi mill-nya kosong: berhenti total, tanpa satu
         pun permintaan HTTP dan tanpa pemilih mill sebagai pengganti. -->
    <p v-if="noMillForAccount" class="notice notice--warning" role="alert" data-testid="no-mill-for-account">
      Akun Anda belum terhubung ke mill mana pun, sehingga laporan tidak dapat ditampilkan.
      Silakan hubungi Admin untuk menghubungkan akun Anda ke sebuah mill.
    </p>

    <template v-else>
      <FilterPanel aria-label="Filter laporan">
        <!-- TIDAK ADA pemilih Mill di layar ini: kedua aktornya terikat satu
             mill, dan mill-nya diselesaikan server dari akun. Namanya tetap
             tampil sebagai chip di bawah — yang tidak ada hanyalah kemampuan
             menggantinya. -->

        <!-- Pemilih Production Line — WAJIB, dan SENGAJA tanpa opsi "semua
             line". Hanya dirender ketika mill punya lebih dari satu line;
             namanya tetap tampil sebagai chip di bawah. -->
        <FilterSelectField v-if="productionLines.length > 1" label="Production Line" icon="line">
          <select
            v-model="selectedProductionLineId"
            data-testid="production-line-select"
            @change="onProductionLineChange"
          >
            <option :value="null">Pilih Production Line</option>
            <option v-for="line in productionLines" :key="line.id" :value="line.id">{{ line.name }}</option>
          </select>
        </FilterSelectField>

        <!-- Pemilih Periode selalu dirender: bila mill belum punya periode ia
             tampil KOSONG, bukan hilang. -->
        <FilterSelectField label="Periode Pelaporan" icon="period">
          <select v-model="selectedPeriodId" data-testid="period-select" @change="onPeriodChange">
            <option :value="null">Pilih Periode</option>
            <option v-for="period in periods" :key="period.id" :value="period.id">
              {{ periodOptionLabel(period) }}
            </option>
          </select>
        </FilterSelectField>

        <template #chips>
          <FilterChip label="Mill" :value="currentMillName || '-'" data-testid="mill-current" />
          <FilterChip
            v-if="activeProductionLineName"
            label="Production Line"
            :value="activeProductionLineName"
            data-testid="production-line-current"
          />
        </template>
      </FilterPanel>

      <p v-if="productionLineRequired" class="notice" data-testid="production-line-required-hint">
        Pilih Production Line terlebih dahulu untuk menampilkan laporan. Angka laporan dihitung per
        Production Line, sehingga tidak ada pilihan gabungan lintas line.
      </p>

      <!-- Pesannya membedakan "mill belum punya line" dari "daftarnya gagal
           dimuat": dua keadaan yang menuntut tindakan berbeda, dan hanya yang
           kedua punya tombol Coba Lagi. -->
      <div
        v-else-if="productionLineBlocked"
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
          @click="loadProductionLines"
        >
          Coba Lagi
        </button>
      </div>

      <p v-else-if="noPeriods" class="notice" data-testid="no-periods">
        Mill ini belum memiliki periode pelaporan yang mencakup stasiun Kernel Plant.
        Silakan hubungi Admin untuk membuat periode pelaporan terlebih dahulu.
      </p>

      <LoadingState v-if="loadingProductionLines" test-id="production-lines-loading">Memuat daftar Production Line…</LoadingState>
      <LoadingState v-if="loadingPeriods" test-id="periods-loading">Memuat daftar periode…</LoadingState>

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

      <p v-else-if="errorMessage" class="notice notice--error" role="alert" data-testid="error-message">
        {{ errorMessage }}
      </p>

      <LoadingState v-if="loadingSummary" variant="card" test-id="summary-loading">Memuat ringkasan periode…</LoadingState>

      <template v-if="hasReport">
        <div class="period-meta" data-testid="period-meta">
          <span class="period-meta-name">{{ summary?.period?.name }}</span>
          <div class="period-meta-line">
            <span>
              {{ formatDate(summary?.period?.start_date) }} &ndash; {{ formatDate(summary?.period?.end_date) }}
            </span>
            <StatusBadge status="none" :label="periodStatusLabel(periodStatus)" data-testid="period-status-badge" />
          </div>
          <span class="period-meta-note">
            Status periode mengatur penulisan data, bukan pembacaan laporan — periode yang sudah ditutup tetap
            dapat dilihat dan diekspor.
          </span>
        </div>

        <p v-if="emptyPeriod" class="notice" data-testid="empty-period">
          Belum ada slot Kernel Plant yang terisi pada periode ini untuk Production Line yang dipilih.
          Ketujuh kartu parameter di bawah tetap ditampilkan dengan penyebut nol, supaya terlihat
          bahwa parameternya ada dan tidak terukur — bukan bahwa hasilnya nol.
        </p>

        <!-- ===== Cakupan pencatatan — PALING ATAS =====
             Bukan catatan kaki: periode yang terisi seperlima pun
             menghasilkan rata-rata yang terlihat rapi, jadi pembaca harus
             melihat cakupannya lebih dulu. TETAP digambar meski periode
             kosong — justru itu yang menjelaskan kekosongannya. -->
        <section class="detail-section" data-testid="coverage">
          <h2 class="section-title">Cakupan Pencatatan</h2>
          <p class="section-note">Dibaca lebih dulu, sebelum satu angka ukur pun dipercaya.</p>

          <div class="metric-stack">
            <div class="metric-card">
              <span class="metric-label">Slot Terisi</span>
              <span class="metric-value" data-testid="coverage-slots">
                {{ formatCount(coverage?.filled_slots) }} dari {{ formatCount(coverage?.expected_slots) }} slot
              </span>
              <!-- Tanda pisah HANYA ketika penyebutnya tidak terbentuk
                   (expected_slots nol). Periode yang punya record tetapi tanpa
                   satu slot terisi menghasilkan 0,0% — dan itu benar, karena
                   penyebutnya ada. -->
              <span
                class="metric-note"
                :class="{ 'metric-value--na': (coverage?.coverage_percent ?? null) === null }"
                data-testid="coverage-percent"
              >
                {{ formatCompletenessPercent(coverage?.coverage_percent ?? null) }}
              </span>
              <span class="metric-note">
                Slot dihitung terisi bila minimal satu dari sembilan kolom bacaan diisi — ketujuh
                kolom ukur, menit downtime, atau temuan; definisinya dipinjam dari layar input itu
                sendiri, bukan diturunkan ulang di sini.
              </span>
            </div>

            <div class="metric-card">
              <span class="metric-label">Pembentuk Penyebut</span>
              <span class="metric-value" data-testid="coverage-denominator">
                {{ formatCount(coverage?.kernel_plant_count) }} unit ×
                {{ formatCount(coverage?.days_counted) }} hari ×
                {{ formatCount(coverage?.slots_per_kernel_plant_per_day) }} slot
              </span>
              <span class="metric-note">
                Jumlah unit adalah unit kernel plant yang BENAR-BENAR punya record pada periode ini,
                bukan jumlah stasiun terdaftar; 24 slot kanonis diambil dari grid layar input itu
                sendiri.
              </span>
            </div>

            <div class="metric-card">
              <span class="metric-label">Hari Dihitung</span>
              <span class="metric-value" data-testid="coverage-days">
                {{ formatCount(coverage?.days_counted) }} dari {{ formatCount(coverage?.days_in_period) }} hari
              </span>
              <!-- URUTANNYA PENTING: days_counted DIPERIKSA LEBIH DULU.
                   ReportPeriodDays::isRunning() di server juga true untuk
                   periode yang BELUM MULAI, jadi memeriksa period_running
                   lebih dulu membuat keterangan "belum mulai" tidak pernah
                   terender. -->
              <span
                v-if="!coverage?.days_counted"
                class="metric-note"
                data-testid="coverage-not-started-note"
              >
                Periode belum mulai, sehingga belum ada hari yang dapat dijadikan pembagi — persennya
                &ldquo;&mdash;&rdquo;, bukan 0%.
              </span>
              <span
                v-else-if="coverage?.period_running"
                class="metric-note"
                data-testid="coverage-running-note"
              >
                Dihitung sampai hari ini, periode masih berjalan — hari yang belum terjadi tidak
                mungkin tercatat.
              </span>
              <span class="metric-note">
                Record berstatus draft IKUT terhitung pada seluruh angka laporan ini.
              </span>
            </div>
          </div>
        </section>

        <!-- ===== Kartu per parameter =====
             Satu kartu memuat angka DAN penyebutnya DAN standarnya, karena di
             situlah penilaian manusia benar-benar terjadi. Kartu tanpa satu
             pun pembacaan TETAP dirender: kartu yang hilang terbaca sebagai
             "tidak ada parameter ini", padahal yang benar adalah "tidak ada
             yang mengukurnya". -->
        <section class="detail-section" data-testid="metrics">
          <h2 class="section-title">Parameter Operasi &amp; Standarnya</h2>
          <p class="section-note">
            Min / rata-rata / maks per kolom ukur. Tiap angka membawa penyebutnya sendiri, dan tidak
            ada nilai yang ditandai di luar batas.
          </p>

          <div class="metric-stack">
            <article
              v-for="metric in metrics"
              :key="metric.column"
              class="metric-card"
              data-testid="metric-card"
            >
              <span class="metric-label">{{ metric.label }} <small>({{ metric.unit }})</small></span>

              <div class="metric-row">
                <span class="metric-row-item">
                  <small>Min</small>
                  <b :class="{ 'metric-value--na': metric.min === null }">{{ formatDecimal(metric.min) }}</b>
                </span>
                <span class="metric-row-item">
                  <small>Rata-rata</small>
                  <b :class="{ 'metric-value--na': metric.avg === null }">{{ formatDecimal(metric.avg) }}</b>
                </span>
                <span class="metric-row-item">
                  <small>Maks</small>
                  <b :class="{ 'metric-value--na': metric.max === null }">{{ formatDecimal(metric.max) }}</b>
                </span>
              </div>

              <!-- Nama parameter pada master: ialah yang menjelaskan mengapa
                   dua kartu yang berbeda dapat membawa target yang sama
                   persis. null dijaga di sini dengan cara yang sama seperti
                   pada keterangan standar-bersama di bawah. -->
              <span class="metric-note" data-testid="metric-master-parameter">
                Standar master:
                <span :class="{ 'metric-value--na': metric.target.equipment_parameter === null }">
                  {{ metric.target.equipment_parameter ?? 'tidak terpeta ke satu pun baris master' }}
                </span>
              </span>

              <!-- PENYEBUT, dicetak apa adanya, berformat "N dari M slot". N
                   milik kartu ini sendiri; M SERAGAM pada ketujuh kartu —
                   skema tidak punya cara mengetahui M per kolom, karena
                   ketujuh kolom ukur ada pada SETIAP baris detail, jadi M per
                   kolom hanya bisa dikarang. Pada layar sempit kolom inilah
                   yang paling mudah dikorbankan demi ruang, dan tanpanya
                   rata-rata terbaca sebagai angka seluruh periode. -->
              <span class="metric-note" data-testid="metric-denominator">
                {{ formatCount(metric.filled_slot_count) }} dari
                {{ formatCount(coverage?.filled_slots) }} slot mencatat kolom ini
              </span>

              <!-- KEDUA kolom target, pada kartu yang SAMA dengan angkanya —
                   dua, bukan tiga: master Kernel Plant tidak punya batas
                   kritis maupun akibat operasional, jadi tidak ada sel yang
                   dirender untuk kolom yang tidak ada. Keduanya VERBATIM dan
                   PENUH, termasuk keterangan dalam tanda kurung yang membawa
                   separuh maknanya. Di layar sempit godaan memangkas rencana
                   tindakan paling besar karena teksnya paling panjang —
                   padahal itulah yang membedakan tindakan apa yang
                   diperlukan. -->
              <span class="metric-standard" data-testid="metric-target-benchmark">
                <small>Target / benchmark</small>
                <span :class="{ 'metric-value--na': metric.target.target_benchmark === null }">
                  {{ metric.target.target_benchmark ?? 'target belum terisi pada master' }}
                </span>
              </span>
              <span class="metric-standard" data-testid="metric-corrective-action">
                <small>Rencana tindakan koreksi</small>
                <span :class="{ 'metric-value--na': metric.target.corrective_action_plan === null }">
                  {{ metric.target.corrective_action_plan ?? 'belum terisi' }}
                </span>
              </span>

              <!-- SATU STANDAR, DUA KOLOM — dan di stasiun ini DUA KALI, pada
                   pasangan ripple mill dan pada pasangan kernel silo. Di layar
                   sempit keempat kartu itu bertumpuk berurutan, sehingga target
                   yang identik muncul dua kali beruntun lalu dua kali lagi —
                   terbaca sebagai data terduplikasi lebih kuat lagi daripada di
                   tabel web, dan seseorang akan "membersihkannya". Daftar
                   pasangannya DITURUNKAN dari respons, tidak dipaku, jadi mesin
                   ketiga pun ditandai benar tanpa menyentuh berkas ini. -->
              <span
                v-if="metric.target.shares_standard_with.length > 0"
                class="metric-note"
                data-testid="metric-shared-standard"
              >
                Standar kartu ini sama dengan
                <strong
                  v-for="(sibling, index) in metric.target.shares_standard_with"
                  :key="sibling"
                  >{{ labelForColumn(sibling)
                  }}{{ index < metric.target.shares_standard_with.length - 1 ? ', ' : '' }}</strong
                >
                <!-- DUA KALIMAT, DAN PERCABANGANNYA PERLU. Ketika nama
                     parameter pada master disunting sehingga tak lagi cocok
                     dengan peta kolom, equipment_parameter bernilai null;
                     mencetaknya apa adanya menghasilkan "master hanya memuat
                     satu baris “” untuk keduanya" — tanda kutip kosong yang
                     mengklaim sebuah baris master yang justru sudah terlepas,
                     tepat pada keadaan layar yang aturan "suntingan harus
                     terlihat" dibangun untuk itu. Nilai cadangan saja tidak
                     cukup: kalimatnya SENDIRI menjadi tidak benar, jadi yang
                     berganti adalah pernyataannya, bukan hanya nilainya. Cacat
                     persis ini ditemukan dan ditutup pada layar web
                     screen-154. -->
                <template v-if="metric.target.equipment_parameter !== null">
                  &mdash; master hanya memuat satu baris
                  &ldquo;{{ metric.target.equipment_parameter }}&rdquo; untuk keduanya.
                </template>
                <template v-else>
                  &mdash; dan keduanya kini
                  <strong>tidak terpeta ke satu pun baris master</strong>, jadi standarnya tidak lagi
                  tercetak di kartu ini. Standar yang terlepas itu muncul pada bagian
                  &ldquo;Standar Tanpa Pengukuran&rdquo; di bawah.
                </template>
                <strong>Angkanya TIDAK dirata-ratakan menjadi satu</strong>: keduanya mesin fisik yang
                berbeda, dan rata-rata gabungan akan menyembunyikan ketidakseimbangan beban yang
                justru menjadi alasan parameter ini diukur.
              </span>
            </article>
          </div>
        </section>

        <!-- Keterangan yang membuat kehadiran standar TANPA penandaan dapat
             dibaca. Tanpa ini, ketiadaan penandaan terbaca sebagai fitur yang
             belum selesai. -->
        <section class="detail-section" data-testid="no-flagging-note">
          <h2 class="section-title">Mengapa Tidak Ada Nilai yang Ditandai</h2>
          <p class="section-note">
            <strong>Target dan rencana tindakan ditampilkan, tetapi laporan ini tidak menilai satu
            angka pun.</strong> Tidak ada warna peringatan, tidak ada severity, tidak ada deteksi
            pencilan &mdash; penilaiannya milik Anda, bukan milik layar ini. Keduanya menjawab
            pertanyaan yang berbeda: <strong>target / benchmark</strong> adalah ke mana angkanya
            seharusnya, dan <strong>rencana tindakan koreksi</strong> adalah apa yang dilakukan
            ketika angkanya tidak di sana. Master Kernel Plant hanya punya kedua kolom itu &mdash;
            tidak ada batas kritis terpisah seperti pada master Depricarping &mdash; jadi kartu di
            atas sengaja tidak memuat baris untuk kolom yang tidak ada.
          </p>
          <p class="section-note">
            <strong>Mengapa tidak ditandai otomatis.</strong> Kedua kolom itu
            <strong>teks bebas</strong>, dan tidak ada apa pun pada skema yang membatasi bentuknya.
            Di stasiun ini satu sel saja sudah mematahkan pengurai apa pun: standar arus ripple mill
            memuat <strong>dua angka dengan satuan berbeda dan arah pembanding berlawanan</strong>
            di dalam satu sel &mdash; sebuah rentang yang harus dimasuki, dan sebuah persentase
            keutuhan yang harus dilampaui. Standar suhu silo menyebut <strong>zona</strong> yang
            tidak punya kolom sama sekali, standar claybath menuliskan satuannya sebagai frasa di
            depan angkanya, dan standar kadar air akhir mencampur <strong>batas dengan alasan batas
            itu ada</strong> di dalam sel yang sama. Nilainya ditetapkan lewat seeder, dan satu
            seeder yang dijalankan atau satu suntingan langsung ke basis data dapat memperkenalkan
            bentuk baru tanpa satu pun uji menangkapnya; pengurai yang lalu gagal akan
            <strong>berhenti memperingatkan tanpa satu pun galat</strong> &mdash; dan peringatan
            yang hilang tidak dapat dibedakan dari &ldquo;semuanya aman&rdquo;, arah kegagalan
            terburuk untuk indikator mutu. Di atas itu, <strong>warna membawa makna melampaui
            statistik</strong>: angka merah pada laporan periode terbaca sebagai pelanggaran &mdash;
            bahan audit &mdash; sehingga menurunkannya dari prosa berarti sistem menyatakan
            pelanggaran yang tidak pernah ditetapkan siapa pun. Bila penandaan diinginkan, cara yang
            jujur adalah memecah master menjadi kolom batas numerik (minimum, maksimum, arah
            pembanding, satuan) di samping teksnya &mdash; bukan menguraikan teksnya saat render.
          </p>
          <p class="section-note">
            <strong>Kedua kolom target berlaku umum untuk seluruh mill</strong> &mdash; master target
            Kernel Plant tidak dipecah per mill. <strong>Penyebut</strong> tiap kartu berformat
            &ldquo;N dari M slot&rdquo;: N adalah jumlah slot yang benar-benar mencatat kolom itu dan
            sengaja berbeda antar kartu, karena ketujuh kolom nullable dan terisi saling bebas.
            <strong>M sama untuk ketujuh kartu</strong> &mdash; jumlah slot terisi pada cakupan
            &mdash; karena ketujuh kolom ukur ada pada <strong>setiap</strong> baris detail, sehingga
            skema ini tidak punya cara mengetahui unit mana mengukur parameter mana; M yang berbeda
            per kolom hanya bisa dikarang. Jumlah slot terisi pada cakupan pun dapat
            <strong>lebih besar</strong> daripada N kartu mana pun, karena slot dihitung terisi bila
            salah satu dari <strong>sembilan</strong> kolom bacaan terisi &mdash; termasuk bila yang
            terisi hanya temuan. Yang dilampaui adalah <strong>N, bukan M</strong>, dan itu benar
            &mdash; bukan ketidaksesuaian yang perlu &ldquo;diperbaiki&rdquo;.
          </p>
        </section>

        <!-- ===== Standar yang belum diukur sistem =====
             Dipublikasikan, bukan dibuang: standar yang tidak pernah diukur
             terbaca seperti terpenuhi padahal ia sekadar tidak ada. -->
        <section v-if="targetsMasterEmpty" class="detail-section" data-testid="targets-master-empty">
          <h2 class="section-title">Master Target Belum Terisi</h2>
          <p class="section-note">
            Master target operasional Kernel Plant belum terisi, jadi kedua kolom target pada kartu
            di atas kosong. Seluruh angka hasil ukur tetap ditampilkan apa adanya &mdash; master yang
            belum diisi tidak menghapus pengukuran yang sudah terjadi.
            <br />
            Bagian &ldquo;Standar Tanpa Pengukuran&rdquo; sengaja TIDAK digambar dalam keadaan ini:
            daftar kosong karena master belum terisi adalah hal yang BERBEDA dari daftar kosong
            karena seluruh standar sudah terukur, dan mencampurnya akan membuat master yang kosong
            terbaca sebagai &ldquo;tidak ada yang tertinggal&rdquo;.
          </p>
        </section>

        <!-- v-else, BUKAN v-else-if="length > 0": bagian ini harus tetap
             digambar ketika daftarnya kosong, karena bagian yang hilang tidak
             dapat dibedakan dari bagian yang belum pernah dibuat. Keadaan
             kosongnya dibedakan di DALAM lewat all_targets_measured. Yang
             dikecualikan hanyalah master yang belum terisi (cabang v-if di
             atas), karena daftar kosong di sana berarti hal yang berlawanan. -->
        <section v-else class="detail-section" data-testid="targets-without-metric">
          <h2 class="section-title">Standar Tanpa Pengukuran</h2>
          <p class="section-note">
            Ada standarnya, tidak ada kolom pengukurannya. Ditampilkan sebagai temuan, bukan
            disembunyikan.
          </p>

          <!-- BAGIAN INI DIGAMBAR WALAU ISINYA KOSONG. Bagian yang hilang
               ketika kosong tidak dapat dibedakan dari bagian yang belum
               pernah dibuat — dan di layar ini justru bagian inilah yang
               normalnya BERISI, serta tempat nama parameter master yang
               disunting tangan menjadi terlihat. -->
          <p v-if="allTargetsMeasured" class="section-note" data-testid="targets-all-measured">
            Seluruh standar pada master target Kernel Plant sudah punya kolom pengukuran yang
            dipasangkan dengannya. Bagian ini tetap digambar supaya keadaan &ldquo;tidak ada yang
            tertinggal&rdquo; dapat dibedakan dari bagian yang belum pernah dibuat.
          </p>

          <template v-else>
            <div class="metric-stack">
              <article
                v-for="target in targetsWithoutMetric"
                :key="target.equipment_parameter"
                class="metric-card"
                data-testid="targets-without-metric-row"
              >
                <span class="metric-label">{{ target.equipment_parameter }}</span>
                <span class="metric-standard">
                  <small>Target / benchmark</small>
                  <span>{{ target.target_benchmark }}</span>
                </span>
                <span class="metric-standard">
                  <small>Rencana tindakan koreksi</small>
                  <span>{{ target.corrective_action_plan }}</span>
                </span>
                <!-- ALASANNYA IKUT DICETAK, bukan hanya nama parameternya:
                     "tidak ada kolomnya" menuntut kolom baru, sementara nama
                     master yang tidak lagi cocok menuntut keputusan penamaan.
                     Dua tindakan yang berbeda, dan keduanya sampai di sini
                     lewat alasan yang sama dari API. -->
                <span class="metric-note" data-testid="targets-without-metric-reason">
                  <strong>Tidak ada kolom pengukurannya</strong> di skema ini
                </span>
              </article>
            </div>

            <p class="section-note" data-testid="targets-without-metric-note">
              <strong
                >Parameter di atas punya standar pada master tetapi tidak punya kolom pengukuran
                pada formulir Kernel Plant.</strong
              >
              Ditampilkan justru karena itu: <strong>standar yang tidak pernah diukur terbaca
              seperti terpenuhi</strong>, padahal ia sekadar tidak ada.
              <strong>&ldquo;Final Kernel Dirt&rdquo; adalah penghuni normal daftar ini</strong>
              &mdash; satu-satunya baris master yang tidak punya kolom pengukuran di mana pun pada
              skema ini: tidak di kernel_plant_details, tidak di tabel lain. Daftar yang berisi di
              sini karena itu BUKAN tanda ada yang rusak, berbeda dari laporan Depricarping yang
              daftarnya normalnya kosong &mdash; dan yang disebut standar ini adalah premi mutu yang
              menjadi dasar mill dibayar. Baris di sini juga muncul bila <strong>nama parameter pada
              master diubah ejaannya</strong> &mdash; pemetaan kolom ke parameter bersifat tetap dan
              tidak mencocokkan teks, jadi ejaan yang berubah membuat pasangannya terlepas. Angka
              hasil ukurnya tetap utuh, dan keterlepasan itu sengaja terlihat di sini alih-alih
              diam-diam menghapus standar dari kartu di atas.
            </p>
          </template>
        </section>

        <template v-if="showRecaps">
          <!-- ===== Rekap per unit kernel plant ===== -->
          <section class="detail-section" data-testid="by-kernel-plant">
            <h2 class="section-title">Rekap per Unit Kernel Plant</h2>
            <p class="section-note">
              kernel_plant_id adalah NAMA UNIT yang diketik di layar input, bukan kunci baris — nama
              yang sama pada dua tanggal adalah satu unit dengan dua hari pencatatan.
            </p>
            <div class="detail-table-wrap">
              <table class="detail-table" data-testid="by-kernel-plant-table">
                <thead>
                  <tr>
                    <th scope="col">Unit</th>
                    <th scope="col" class="num">Hari</th>
                    <th scope="col" class="num">Slot</th>
                    <th scope="col" class="num">Downtime (mnt)</th>
                    <th v-for="metric in metrics" :key="metric.column" scope="col" class="num">
                      {{ metric.label }}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="row in byKernelPlant"
                    :key="row.kernel_plant_id"
                    data-testid="by-kernel-plant-row"
                  >
                    <td>{{ formatGroupLabel(row.kernel_plant_name || row.kernel_plant_id) }}</td>
                    <td class="num">{{ formatCount(row.day_count) }}</td>
                    <td class="num">{{ formatCount(row.filled_slot_count) }}</td>
                    <!-- null, BUKAN 0: unit yang tidak satu pun slotnya
                         mencatat downtime bukan unit yang tidak pernah
                         berhenti. -->
                    <td class="num" data-testid="by-kernel-plant-downtime">
                      {{ row.downtime_minutes === null ? 'belum dicatat' : formatCount(row.downtime_minutes) }}
                    </td>
                    <td v-for="metric in metrics" :key="metric.column" class="num">
                      {{ formatCell(row.averages?.[metric.column] ?? null) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

          <!-- ===== Downtime sebagai ANGKA =====
               kernel_plant_details.downtime_minutes adalah kolom INTEGER,
               jadi "berapa lama stasiun berhenti" dapat dijawab di sini —
               tidak seperti laporan Threshing maupun Pressing mobile, di mana
               downtime hanya ada sebagai teks. -->
          <section class="detail-section" data-testid="downtime">
            <h2 class="section-title">Downtime Periode</h2>
            <p class="section-note">
              Dijumlahkan atas slot yang <strong>mencatatnya</strong> saja. Slot tanpa catatan bukan
              nol menit. Parameter ini tidak punya standar pada master.
            </p>

            <p
              v-if="downtime === null || downtime.total_minutes === null"
              class="section-note"
              data-testid="downtime-empty"
            >
              <strong>Belum ada satu slot pun yang mencatat menit downtime</strong> pada periode dan
              line ini. Dinyatakan begini, bukan sebagai total 0 menit &mdash; total 0 menit terbaca
              seperti &ldquo;stasiun tidak pernah berhenti&rdquo;, padahal yang benar adalah
              &ldquo;tidak ada yang mencatatnya&rdquo;.
            </p>

            <div v-else class="metric-stack">
              <article class="metric-card" data-testid="downtime-total">
                <span class="metric-label">Total Berhenti</span>
                <div class="metric-row">
                  <span class="metric-row-item">
                    <small>Menit</small>
                    <b>{{ formatCount(downtime.total_minutes) }}</b>
                  </span>
                </div>
                <span class="metric-note">
                  dijumlahkan hanya atas slot yang mencatat menit downtime
                </span>
              </article>

              <article class="metric-card" data-testid="downtime-recorded-slots">
                <span class="metric-label">Slot yang Mencatat</span>
                <div class="metric-row">
                  <span class="metric-row-item">
                    <small>Slot</small>
                    <b>{{ formatCount(downtime.recorded_slot_count) }}</b>
                  </span>
                </div>
                <span class="metric-note">
                  dari {{ formatCount(coverage?.filled_slots) }} slot terisi &mdash; menghitung juga
                  slot yang mencatat nol menit, dan inilah <strong>penyebut</strong> rata-rata di
                  bawah
                </span>
              </article>

              <article class="metric-card" data-testid="downtime-average">
                <span class="metric-label">Rata-rata per Slot Pencatat</span>
                <div class="metric-row">
                  <span class="metric-row-item">
                    <small>Menit</small>
                    <b :class="{ 'metric-value--na': downtime.avg_minutes_per_recorded_slot === null }">
                      {{ formatDecimal(downtime.avg_minutes_per_recorded_slot, 1) }}
                    </b>
                  </span>
                </div>
                <span class="metric-note">
                  penyebutnya slot yang <strong>mencatat</strong>, bukan seluruh slot terisi
                </span>
              </article>
            </div>

            <!-- Keterangan ini dirender di KEDUA keadaan: ia menjelaskan cara
                 hitungnya, bukan hasilnya. -->
            <p class="section-note" data-testid="downtime-note">
              <strong>Slot yang tidak mencatat menit downtime tidak dihitung sebagai nol
              menit.</strong>
              Memperlakukannya nol akan membuat rata-ratanya mengecil justru seiring bertambahnya
              slot yang <em>tidak</em> dicatat &mdash; laporan akan tampak lebih baik karena lebih
              sedikit yang ditulis. Sebaliknya, nilai <strong>0 yang benar-benar tercatat IKUT
              dihitung</strong>, baik pada totalnya maupun pada jumlah slot pencatatnya, karena nol
              di situ adalah pernyataan seseorang bahwa stasiun tidak berhenti pada slot itu.
              <strong>Parameter ini tidak punya baris pada master target</strong>, jadi tidak ada
              target maupun rencana tindakan yang dapat ditampilkan bersamanya &mdash; dinyatakan
              agar tidak terbaca sebagai master yang belum terisi. Total menit baru bermakna
              dibandingkan antar periode bila proporsi slot yang mencatatnya serupa; itulah sebabnya
              jumlah slot pencatat ikut ditampilkan. Slot yang mencatat downtime <strong>tetap
              ikut</strong> pada min, rata-rata dan maks bila kolom ukurnya juga terisi &mdash;
              downtime adalah keterangan tambahan pada slot itu, bukan penyaring.
            </p>
          </section>

          <!-- ===== Rekap temuan =====
               SENGAJA TERPISAH dari blok di atas: satu menjawab "berapa
               lama", satu menjawab "apa yang terlihat". Satu slot dapat
               memuat keduanya, dan menggabungkannya membuat slot itu
               terhitung dua kali pada satu pengertian. -->
          <section class="detail-section" data-testid="findings">
            <h2 class="section-title">Temuan Lapangan</h2>
            <p class="section-note">
              Diurutkan dari yang terbanyak. Dikelompokkan <strong>harfiah</strong>, tanpa
              penyeragaman ejaan, huruf besar-kecil, maupun spasi.
            </p>

            <p v-if="findings.length === 0" class="section-note" data-testid="findings-empty">
              Tidak ada satu pun temuan tercatat pada periode dan line ini. Ini dinyatakan sebagai
              keterangan, bukan tabel kosong tanpa penjelasan &mdash; tabel kosong tidak membedakan
              &ldquo;tidak ada temuan&rdquo; dari &ldquo;tidak ada yang mencatatnya&rdquo;.
            </p>

            <template v-else>
              <div class="detail-table-wrap">
                <table class="detail-table" data-testid="findings-table">
                  <thead>
                    <tr>
                      <th scope="col">Temuan (apa adanya)</th>
                      <th scope="col" class="num">Slot</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in findings" :key="row.finding" data-testid="findings-row">
                      <td class="wrap">{{ row.finding }}</td>
                      <td class="num">{{ formatCount(row.slot_count) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <p class="section-note" data-testid="findings-note">
                Temuan adalah <strong>teks bebas</strong>, dan dikelompokkan
                <strong>harfiah</strong>. Dua ejaan untuk hal yang sama karena itu muncul sebagai
                <strong>dua baris</strong> &mdash; itu bentuk datanya, bukan cacat laporan ini.
                Menyeragamkannya justru akan menggabungkan hal yang penulisnya memang maksudkan
                berbeda. Urutannya jumlah slot terbanyak lebih dulu, lalu teksnya menaik sebagai
                pemutus seri, supaya urutan yang sama keluar pada setiap render. Temuan
                <strong>tidak digabungkan</strong> dengan menit downtime di atas: satu menjawab
                berapa lama stasiun berhenti, satu menjawab apa yang terlihat. Satu slot dapat
                memuat keduanya, dan menggabungkannya akan membuat slot itu terhitung dua kali pada
                satu pengertian.
              </p>
            </template>
          </section>

          <!-- ===== Kelengkapan record ===== -->
          <section class="detail-section" data-testid="completeness">
            <h2 class="section-title">Kelengkapan Record</h2>
            <p class="section-note">
              Seluruh angka di bawah IKUT terhitung pada laporan — tidak satu pun menjadi penyaring.
            </p>
            <div class="queue-figures">
              <div class="queue-figure">
                <span class="queue-label">Jumlah record</span>
                <span class="queue-value" data-testid="record-count">{{ formatCount(totals?.record_count) }}</span>
                <span class="queue-note">satu record = satu hari kerja satu unit kernel plant</span>
              </div>
              <div class="queue-figure">
                <span class="queue-label">Hari bercatatan</span>
                <span class="queue-value" data-testid="days-with-records">{{ formatCount(totals?.days_with_records) }}</span>
              </div>
              <div class="queue-figure">
                <span class="queue-label">Record draft</span>
                <span class="queue-value" data-testid="draft-record-count">{{ formatCount(totals?.draft_record_count) }}</span>
              </div>
              <div class="queue-figure">
                <span class="queue-label">Belum diperiksa</span>
                <span class="queue-value" data-testid="records-not-checked">{{ formatCount(totals?.records_not_checked) }}</span>
              </div>
              <div class="queue-figure">
                <span class="queue-label">Belum disahkan</span>
                <span class="queue-value" data-testid="records-not-acknowledged">{{ formatCount(totals?.records_not_acknowledged) }}</span>
              </div>
            </div>
            <p class="section-note" data-testid="draft-note">
              Record draft <strong>IKUT terhitung</strong> pada seluruh angka di atas; jumlahnya
              dinyatakan supaya terlihat seberapa besar laporan ini berdiri di atas data yang belum
              selesai. Status verifikasi juga kelengkapan, bukan penyaring — record yang belum
              diperiksa tetap terhitung penuh.
            </p>
          </section>

          <!-- ===== Rekap harian =====
               Panjang pada periode sebulan, jadi dibungkus CollapsibleSection
               dan TERTUTUP secara bawaan. Membuka atau menutupnya tidak
               memicu permintaan apa pun — datanya sudah ada di memori — dan
               tidak mengubah satu angka pun, termasuk baris total periode. -->
          <CollapsibleSection
            title="Rekap Harian"
            :subtitle="`${daily.length} hari`"
            data-testid="daily-recap"
          >
            <div class="detail-table-wrap">
              <table class="detail-table" data-testid="daily-table">
                <thead>
                  <tr>
                    <th scope="col">Tanggal</th>
                    <th scope="col" class="num">Slot</th>
                    <th scope="col" class="num">Downtime (mnt)</th>
                    <th v-for="metric in metrics" :key="metric.column" scope="col" class="num">
                      {{ metric.label }}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Tanggal tanpa record sama sekali TIDAK mendapat baris:
                       baris nol akan terbaca sebagai "terukur nol". -->
                  <tr v-for="row in daily" :key="row.date" data-testid="daily-row">
                    <td>{{ formatDate(row.date) }}</td>
                    <td class="num">{{ formatCount(row.filled_slot_count) }}</td>
                    <td class="num">
                      {{ row.downtime_minutes === null ? 'belum dicatat' : formatCount(row.downtime_minutes) }}
                    </td>
                    <td v-for="metric in metrics" :key="metric.column" class="num">
                      {{ formatCell(row.averages?.[metric.column] ?? null) }}
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <!-- TOTAL PERIODE DIHITUNG ULANG SERVER atas seluruh slot
                       terisi, bukan merata-ratakan rata-rata harian:
                       rata-rata dari rata-rata memberi bobot sama pada hari
                       berisi dua slot dan hari berisi dua puluh empat. Karena
                       itu pula ia tidak berubah ketika rekap harian ditutup. -->
                  <tr data-testid="daily-recap-total">
                    <td>Total periode</td>
                    <td class="num">{{ formatCount(dailyTotal?.filled_slot_count) }}</td>
                    <td class="num">
                      {{
                        (dailyTotal?.downtime_minutes ?? null) === null
                          ? 'belum dicatat'
                          : formatCount(dailyTotal?.downtime_minutes)
                      }}
                    </td>
                    <td v-for="metric in metrics" :key="metric.column" class="num">
                      {{ formatCell(dailyTotal?.averages?.[metric.column] ?? null) }}
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </CollapsibleSection>
        </template>
      </template>

      <footer class="action-footer">
        <!-- Ekspor TIDAK PERNAH dinonaktifkan oleh status periode: kunci
             periode mengatur penulisan data, bukan pembacaan laporan. CSV
             saja — lihat catatan ekspor pada docblock. -->
        <button
          v-if="selectedPeriodId && selectedProductionLineId"
          type="button"
          class="action-button action-button--primary"
          data-testid="export-button"
          :disabled="exporting"
          :aria-busy="exporting"
          @click="onExport"
        >
          <BusyLabel :busy="exporting" label="Ekspor CSV" busy-label="Mengekspor…" />
        </button>
        <button type="button" class="action-button action-button--secondary" data-testid="back-button" @click="onBack">
          Back
        </button>
      </footer>
    </template>
  </main>
</template>

<style scoped>
.laporan-pr-view {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 0 16px 20px;
  background: #ffffff;
  font-family: 'Inter', sans-serif;
  box-sizing: border-box;
  /* Halaman TIDAK PERNAH menggulir mendatar; yang menggulir adalah isi kartu
     tabel di dalamnya. */
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
.period-meta-note { font-size: 12px; color: #6b7280; }

.notice { margin: 0; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; color: #6b7280; font-size: 14px; }
.notice--warning { border-color: #fcd34d; background: #fffbeb; color: #92400e; }
.notice--error { display: flex; flex-direction: column; gap: 10px; border-color: #fecaca; background: #fef2f2; color: #b91c1c; }
.notice-text { margin: 0; }
.notice--stack { display: flex; flex-direction: column; align-items: flex-start; gap: 10px; }

.metric-stack { display: flex; flex-direction: column; gap: 10px; }
.metric-card { display: flex; flex-direction: column; gap: 4px; padding: 14px; border: 1px solid #e5e7eb; border-radius: 8px; }
.metric-label { font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }
.metric-value { font-size: 26px; font-weight: 700; color: #1f2937; }
/* Nilai yang tidak dapat dihitung tampil sebagai keterangan, bukan angka
   besar — supaya tidak pernah terbaca sekilas sebagai bilangan. */
.metric-value--na { font-size: 15px; font-weight: 600; color: #6b7280; }
.metric-note { font-size: 12px; color: #6b7280; }

.detail-section { display: flex; flex-direction: column; gap: 10px; padding: 14px; border: 1px solid #e5e7eb; border-radius: 8px; }
.section-title { margin: 0; font-size: 16px; font-weight: 700; color: #1f2937; }
.section-note { margin: 0; font-size: 12px; color: #6b7280; }

.queue-figures { display: flex; gap: 20px; flex-wrap: wrap; }
.queue-figure { display: flex; flex-direction: column; gap: 2px; min-width: 96px; }
.queue-label { font-size: 11px; color: #6b7280; }
.queue-value { font-size: 20px; font-weight: 700; color: #1f2937; }
.queue-note { font-size: 12px; color: #6b7280; }

/* Tabel: guliran mendatar DI DALAM kartu, bukan pada halaman. */
.detail-table-wrap { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
.detail-table { border-collapse: collapse; width: 100%; font-size: 13px; }
.detail-table th, .detail-table td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }
.detail-table th.num, .detail-table td.num { text-align: right; }
/* Temuan adalah teks bebas yang bisa sangat panjang: sel ini DIBUNGKUS utuh,
   tidak dipotong — pemotongan akan menghilangkan isi temuan itu sendiri. */
.detail-table td.wrap { white-space: normal; min-width: 180px; overflow-wrap: anywhere; }
.detail-table tfoot th, .detail-table tfoot td { font-weight: 700; color: #1f2937; border-bottom: none; }

.action-footer { display: flex; gap: 10px; margin-top: auto; }
.action-button { min-height: 44px; padding: 0 16px; border-radius: 8px; font-size: 14px; font-weight: 600; font-family: inherit; cursor: pointer; box-sizing: border-box; }
.action-button--primary { border: 1px solid #249360; background: #249360; color: #ffffff; }
.action-button:disabled { opacity: 0.5; cursor: not-allowed; }

/* Tombol yang sedang bekerja tetap berwarna penuh (spinner terbaca). */
.action-button[aria-busy='true']:disabled {
  opacity: 1;
  cursor: progress;
}
.action-button--secondary { border: 1px solid #e5e7eb; background: #ffffff; color: #1f2937; }

/* Tiga angka berdampingan DI DALAM satu kartu parameter — ini bukan tiga
   kartu bersebelahan: min/rata-rata/maks adalah satu pengukuran yang sama
   dilihat dari tiga sisi, jadi memisahkannya menjadi tiga kartu akan
   mengundang pembacaan bahwa ketiganya tiga hal berbeda. Blok downtime
   memakai struktur yang SAMA, sengaja: di LaporanDepricarpingView ketiga
   kartu downtime memakai kelas .metric-values yang tidak pernah
   didefinisikan di mana pun, sehingga pembungkusnya tidak tertata. */
.metric-row { display: flex; gap: 16px; flex-wrap: wrap; }
.metric-row-item { display: flex; flex-direction: column; gap: 2px; min-width: 72px; }
.metric-row-item small { font-size: 11px; color: #6b7280; }
.metric-row-item b { font-size: 18px; font-weight: 700; color: #1f2937; }

/* Standar operasional dan rencana tindakannya, DI DALAM kartu angkanya. */
.metric-standard { display: flex; flex-direction: column; gap: 2px; font-size: 13px; color: #1f2937; }
.metric-standard small { font-size: 11px; color: #6b7280; }
</style>
