<script setup lang="ts">
/**
 * LaporanEffluentPlantView — screen-157--laporan-effluent-plant-mobile /
 * usecase-163--laporan-effluent-plant-mobile "Lihat Laporan Periode Effluent
 * Plant (Mobile)".
 *
 * Kembaran mobile KEDUA BELAS, dan BACAAN SAJA: layar ini tidak punya satu
 * pun jalur yang mengubah data stasiun.
 *
 * ────────────────────────────────────────────────────────────────────────
 * BERBEDA DARI screen-155: LAYAR INI MENUNTUT PERUBAHAN BACKEND
 * ────────────────────────────────────────────────────────────────────────
 * Kernel Plant mobile nol perubahan backend. Di sini screen-156 (web)
 * mendarat lebih dulu dan SENGAJA menolak Operator 403 pada keempat rute,
 * karena belum ada pemanggil Operator yang sah. Layar inilah pemanggil itu,
 * jadi pelebaran aksesnya dikerjakan BERSAMA layar ini — tanpa pelebaran itu
 * layar ini menerima 403 di keempat rute dan tampak rusak tanpa sebab yang
 * terbaca pengguna. Pelebarannya menyentuh TIGA tempat sekaligus: daftar
 * peran middleware di routes/api.php, EffluentPlantReportService::
 * guardAccess(), DAN cabang TERIKAT-MILL resolveBusinessUnit(). Dua dari tiga
 * menjatuhkan Operator ke cabang Admin tempat business_unit_id kiriman klien
 * DIHORMATI: kebocoran lintas mill, bukan cacat tampilan. Yang TIDAK terbuka
 * tetap tidak terbuka — /business-units/options Admin saja (403 diangkat
 * service, bukan middleware), dan rute WEB /reports/effluent-plant tanpa
 * Operator.
 *
 * ────────────────────────────────────────────────────────────────────────
 * TANPA PEMILIH MILL, DAN ITU BUKAN PEMANGKASAN
 * ────────────────────────────────────────────────────────────────────────
 * Kedua aktor layar ini (Operator dan Supervisor) TERIKAT SATU MILL, jadi
 * business_unit_id tidak pernah dikirim: server menyelesaikan mill dari akun,
 * dan repo laporan ini pun tidak punya jalur untuk mengirimkannya —
 * gagal-tertutup secara KONSTRUKSI. Konsekuensinya disengaja:
 * /api/effluent-plant-reports/business-units/options tidak dipanggil dari
 * mana pun. Admin dan Mill Management memakai layar WEB screen-156, yang
 * satu-satunya punya pemilih mill. Nama mill tetap DITAMPILKAN sebagai
 * keterangan — yang tidak ada hanyalah kemampuan MENGGANTINYA.
 *
 * ────────────────────────────────────────────────────────────────────────
 * EMPAT BELAS KARTU DALAM ENAM BLOK BERJUDUL
 * ────────────────────────────────────────────────────────────────────────
 * Tabel empat belas kolom versi web menjadi kartu bertumpuk: pada 390px tabel
 * itu tidak muat tanpa menggulir mendatar. MARKUP KARTUNYA DITULIS SEKALI DAN
 * DI-LOOP, bukan disalin per blok — pengulangan markup berarti sebuah blok
 * dapat diam-diam kehilangan penyebutnya atau MEWARISI penyebut blok lain,
 * pelajaran yang sudah terbukti pada blade screen-156. Pengelompokan enam
 * blok murni PENANDA TEMPAT bagi gulir yang panjang: tidak satu pun angka
 * dihitung per blok, dan TIDAK ADA TOTAL PER BLOK, karena satuan di dalam
 * satu blok pun berbeda (pH tanpa satuan, suhu °C).
 *
 * KEEMPAT BELAS KARTU SELALU DIRENDER, termasuk kolom yang tidak pernah
 * terisi: kartunya tetap ada dengan keterangan "tidak tersedia" dan penyebut
 * 0 pembacaan, karena kartu yang hilang terbaca sebagai "tidak ada metrik
 * ini" padahal yang benar adalah "tidak ada yang mengukurnya".
 *
 * PENANDA 'lab' DITURUNKAN DARI metrics[kolom].is_lab PADA RESPONS, bukan
 * dari daftar nama kolom yang dipaku di template — supaya kolom laboratorium
 * KEEMPAT kelak ditandai benar tanpa menyunting berkas ini. Dan ia dirender
 * DI DALAM kartunya masing-masing, bukan sebagai satu keterangan di atas
 * halaman: di ponsel pembaca melihat satu kartu pada satu waktu, jadi
 * keterangan di atas halaman tidak hadir saat angkanya dibaca.
 *
 * PERNYATAAN KEPATUHAN LINGKUNGAN DIRENDER DI DALAM BLOK BUANGAN AKHIR,
 * tepat di dekat keempat angkanya — bukan di catatan kaki halaman, yang di
 * ponsel berarti puluhan layar jauhnya dari angka yang dirujuknya.
 *
 * ────────────────────────────────────────────────────────────────────────
 * TIGA BENTUK YANG TIDAK DIMILIKI SATU PUN LAPORAN MOBILE SEBELUMNYA
 * ────────────────────────────────────────────────────────────────────────
 * 1. SEBARAN STATUS, DAN IA MENGITERASI known_values — BUKAN kunci counts
 *    yang ada. Mengiterasi counts menghilangkan baris bernilai 0, dan baris
 *    yang hilang terbaca sebagai "nilai ini tidak ada" padahal yang benar
 *    adalah "nilai ini tidak pernah terjadi". Baris `not_recorded` TERPISAH
 *    dan TIDAK PERNAH dilipat ke Off/Stop: kolom yang kosong berarti
 *    tidak ada yang mencatatnya, bukan flare yang mati atau pompa yang
 *    berhenti. Penyebut porsinya `recorded_slots`, BUKAN `expected_slots` —
 *    penyebut kedua itu akan mencampur slot tak tersentuh ke dalam porsi
 *    sebuah nilai yang justru tercatat.
 *
 * 2. DUA REKAP TEKS BEBAS, DAN KEDUANYA DAFTAR KEJADIAN {date, time_slot,
 *    text} — BUKAN dikelompokkan menurut teksnya seperti Kernel Plant mobile.
 *    Pengelompokan menurut teks menghilangkan tanggal dan slot waktu yang
 *    justru satu-satunya cara menemukan kembali kejadiannya. Keduanya DUA
 *    BAGIAN TERPISAH dan tidak pernah disatukan: satu slot yang mengisi kedua
 *    kolom muncul pada KEDUA daftar, dan itu BUKAN penghitungan ganda —
 *    menyatukannya justru akan menghitung slot itu dua kali di dalam satu
 *    daftar.
 *
 * 3. TIDAK ADA BLOK DOWNTIME DAN TIDAK ADA KOLOM TARGET, dan KETIADAAN
 *    KEDUANYA DINYATAKAN DI LAYAR, UTUH, tanpa diringkas demi ruang.
 *    effluent_plant_details tidak punya satu pun kolom downtime, dan Effluent
 *    Plant tidak punya tabel master target operasional — jadi tidak satu
 *    medan selalu-kosong pun dikarang demi sebangun dengan Kernel Plant atau
 *    Depricarping mobile. Ketiadaan yang tidak dijelaskan terbaca sebagai
 *    fitur yang belum selesai, dan di stasiun ini EMPAT dari empat belas
 *    metrik adalah parameter kepatuhan lingkungan — keterangan yang terpotong
 *    di sini berisiko lebih besar daripada di laporan mana pun sebelumnya.
 *
 * ────────────────────────────────────────────────────────────────────────
 * YANG DIWARISI DARI SERI, DINYATAKAN KARENA MENANGGUNG BEBAN
 * ────────────────────────────────────────────────────────────────────────
 * CAKUPAN PENCATATAN DIRENDER PALING ATAS. Di layar sempit pembaca melihat
 * lebih sedikit sekaligus, jadi apa yang dilihat lebih dulu menentukan lebih
 * banyak — dan periode yang terisi seperlima pun menghasilkan rata-rata yang
 * terlihat rapi atas empat belas kolom yang semuanya nullable.
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
 * menghasilkan 0,0% dan itu benar — penyebutnya terbentuk. Perhatikan:
 * payload Effluent Plant mengirim coverage_percent 0.0 (bukan null) pada
 * keadaan penyebut-nol, jadi cabangnya DIPUTUSKAN dari expected_slots === 0,
 * bukan dari null-nya medan itu. Memutuskannya dari null akan menukar '—'
 * dengan 0,0% pada stasiun ini.
 *
 * SLOT TERISI DIHITUNG ATAS SEMBILAN BELAS KOLOM — keempat belas kolom ukur,
 * ketiga kolom status, dan KEDUA kolom teks — sehingga coverage.filled_slots
 * DAPAT MELEBIHI reading_count setiap kartu metrik. Yang dilampaui adalah
 * penyebut KARTU, bukan penyebut CAKUPAN, dan keterangannya dirender supaya
 * pembaca berikutnya tidak "memperbaiki" selisih yang memang benar.
 *
 * PRODUCTION LINE WAJIB, dijaga di FUNGSI loadSummary() bukan hanya di
 * template — supaya jalur coba-lagi, ganti periode, dan pemulihan dari
 * ingatan perangkat tidak melewatinya. Ingatan line
 * (msl_production_line_{userId}) dibagi dengan StationListView; urutan
 * penyelesaian rute -> ingatan -> satu-satunya line -> tidak ada; dan
 * kegagalan penyimpanan (mode privat) diperlakukan sebagai "tidak ada
 * ingatan", bukan galat.
 *
 * NOL ARITMETIKA ATAS ANGKA LAPORAN, termasuk TIDAK memetakan satuan 'C'
 * menjadi '°C'. Yang dihitung di berkas ini hanya DUA hal, keduanya tampilan
 * dan keduanya ditiru BARIS DEMI BARIS dari blade screen-156 supaya ponsel
 * tidak mungkin berselisih dengan web: (a) porsi sebaran status — payload
 * tidak menerbitkan `share`, dan rumusnya sama persis dengan $porsi pada
 * blade, penyebutnya recorded_slots; (b) tinggi batang tren — skala tampilan
 * yang sama dengan $tinggiBatang, dan nilai terendah/tertinggi series-nya
 * DILABELI "dari rata-rata harian" seperti di web. Tidak ada angka lain yang
 * diturunkan, dibulatkan ulang, atau dinormalkan di sini.
 *
 * DUA PERMINTAAN /summary YANG SALING MENDAHULUI diselesaikan dengan penanda
 * permintaan terakhir, BUKAN dengan membatalkan yang lama.
 *
 * 401 MENGARAHKAN KE LOGIN TANPA PESAN JARINGAN — "periksa koneksi Anda" pada
 * sesi yang berakhir membuat pembaca memeriksa sinyal alih-alih masuk
 * kembali.
 *
 * EKSPOR HANYA CSV. .xlsx tidak ditawarkan bukan karena endpoint tidak
 * mendukungnya (ia mendukung), melainkan karena menyimpan dan membukanya di
 * WebView ponsel menuntut penanganan berkas native yang belum ada di aplikasi
 * ini.
 *
 * HALAMAN TIDAK PERNAH MENGGULIR MENDATAR PADA 390px. Rekap per unit dan
 * rekap harian membawa EMPAT BELAS kolom rata-rata — terlebar di laporan
 * mobile mana pun — dan keduanya, beserta kedua grafik tren, menggulir DI
 * DALAM kartunya sendiri.
 *
 * DISIPLIN KELAS CSS: tiap kelas yang dipakai markup punya definisi di blok
 * <style> di bawah atau di stylesheet bersama yang dirujuknya. Dua defek lama
 * SENGAJA TIDAK disalin: LaporanDepricarpingView.vue membungkus angka
 * downtime dengan kelas .metric-values yang tidak terdefinisi di satu berkas
 * pun di src/, dan keterangan cakupan Depricarping mobile menyebut jumlah
 * kolom bacaan yang salah. Di sini dipakai .metric-row/.metric-row-item yang
 * terdefinisi, seperti LaporanKernelPlantView.vue, dan jumlah kolom bacaannya
 * SEMBILAN BELAS — angka yang sama dengan
 * EffluentPlantRecordService::READING_FIELDS.
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
import effluentPlantReportRepo, {
  type EffluentPlantReportMetric,
  type EffluentPlantReportPeriodOption,
  type EffluentPlantReportSummary,
  type EffluentPlantReportTrendPoint,
} from '@/services/effluentPlantReportRepo'

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
 * Laporan memulangkan angka GABUNGAN satu periode pada satu line.
 * Menjumlahkan beberapa Production Line ke dalam satu angka menghasilkan
 * bilangan yang tidak dapat ditindaklanjuti siapa pun. Karena itu tidak ada
 * angka di layar ini sebelum ada satu line yang berlaku — dan server pun
 * menjawab 422 bila production_line_id absen, bukan memulangkan angka seluruh
 * mill.
 *
 * ── MEMAKAI ULANG INGATAN LINE MILIK LAYAR DAFTAR STASIUN ───────────────
 *
 * Kunci localStorage-nya SAMA PERSIS dengan yang ditulis StationListView.vue
 * (`msl_production_line_{userId}`) dan yang sudah dibaca
 * ReportingPilihStasiunView.vue. Line adalah konteks kerja satu orang pada
 * satu shift, bukan pengaturan per layar.
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
 * business_unit_id — endpoint screen-135 yang DIPAKAI ULANG VERBATIM, tidak
 * diduplikasi di bawah prefix effluent-plant-reports.
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
    !noMillForAccount.value && productionLines.value.length > 0 && !selectedProductionLineId.value,
)

/** Tidak ada satu pun line yang dapat dipilih di layar ini. */
const productionLineBlocked = computed(
  () =>
    !noMillForAccount.value &&
    !loadingProductionLines.value &&
    productionLines.value.length === 0,
)

/**
 * DUA KEADAAN YANG MENUNTUT TINDAKAN BERBEDA, jadi dua pesan berbeda — dan
 * hanya yang kedua punya tombol Coba Lagi. Menyatukan keduanya akan menyuruh
 * pengguna menelepon Admin untuk masalah jaringan, atau sebaliknya
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
 * bukan sebagai galat, dan TIDAK ADA pesan galat yang dirender karenanya.
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
  } catch (error) {
    // 401 di sini tetap berarti sesi berakhir, jadi ia DITERUSKAN ke
    // handleError() yang mengarahkan ke Login TANPA pesan jaringan — pesan
    // "daftar line gagal dimuat" pada sesi yang berakhir mengirim pembaca
    // memeriksa sinyal alih-alih masuk kembali. Galat lainnya BUKAN kegagalan
    // laporan: ia tidak boleh menyamar sebagai galat ringkasan, jadi ia
    // mengisi state-nya sendiri dengan tombol Coba Lagi-nya sendiri.
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
 * Cakupan yang dikirim repo — SATU medan, dan tidak ada medan mill di
 * dalamnya untuk dapat salah terkirim.
 */
const scope = computed(() => ({
  productionLineId: selectedProductionLineId.value,
}))

/**
 * Nama mill yang sedang berlaku, sebagai keterangan.
 *
 * DIBACA DARI summary.business_unit.name SAJA. Respons /periods layar ini
 * TIDAK memuat nama mill sama sekali — membacanya dari sana menghasilkan
 * undefined yang ikut dirender, dan koreksi itu sudah ditemukan saat
 * screen-155 diimplementasikan. Cadangannya mill akun, untuk keadaan sebelum
 * ringkasan pertama tiba.
 */
const currentMillName = computed(
  () => summary.value?.business_unit?.name || authStore.businessUnit?.name || '',
)

/* ------------------------------------------------------------------ */
/* Periode & ringkasan                                                 */
/* ------------------------------------------------------------------ */

const periods = ref<EffluentPlantReportPeriodOption[]>([])
const selectedPeriodId = ref<string | null>(null)
const summary = ref<EffluentPlantReportSummary | null>(null)

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
 *   tanpa status -> penolakan tanpa respons = jaringan; periode dan line yang
 *                   sudah terpilih SENGAJA tidak disentuh
 *   sisanya -> pesan yang dapat dibaca pengguna (404 periode tidak ditemukan,
 *              422 validasi — jalur pertahanan kedua di belakang penjaga line)
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
    // memuat ulang periode dan line yang SAMA.
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
    // TANPA business_unit_id — tidak ada jalur kode untuk mengirimkannya.
    periods.value = await effluentPlantReportRepo.fetchPeriods()
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

  // TIDAK ADA SATU ANGKA PUN SEBELUM ADA LINE YANG BERLAKU, dan penjagaan ini
  // ada DI SINI — di dalam fungsi pemuatan — bukan hanya di template, supaya
  // tidak ada jalur pemanggilan (coba lagi, ganti periode, pemulihan dari
  // ingatan perangkat) yang bisa melewatinya. Penjaga yang hanya ada di
  // template dilewati oleh ketiganya, dan di layar ini melewatinya berarti
  // 422 dari server, bukan angka seluruh mill.
  if (!periodId || !selectedProductionLineId.value) {
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
    const result = await effluentPlantReportRepo.fetchSummary(periodId, scope.value)
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

  return `laporan-effluent-plant_${slug || 'periode'}_${lineSlug || 'line'}.csv`
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
    // Isi CSV dibentuk SERVER (satu baris per slot waktu, 19 kolom bacaan).
    // Layar ini tidak pernah menyusun berkasnya dari angka yang tampil. Nama
    // berkas diambil SAAT ekspor diminta: bila pengguna mengganti periode
    // selama berkas diunduh, isinya tetap milik periode yang diekspor, jadi
    // namanya pun harus milik periode itu. format=csv SELALU — tidak ada
    // jalur kode yang dapat menyusun format lain.
    const filename = exportFilename()
    const blob = await effluentPlantReportRepo.exportCsv(periodId, scope.value)
    effluentPlantReportRepo.saveCsvFile(blob, filename)
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
/* Turunan tampilan (tanpa perhitungan angka laporan)                  */
/* ------------------------------------------------------------------ */

const coverage = computed(() => summary.value?.coverage ?? null)
const metrics = computed(() => summary.value?.metrics ?? {})
const statusDistribution = computed(() => summary.value?.status_distribution ?? {})
const maintenanceRemarks = computed(() => summary.value?.maintenance_remarks ?? [])
const findings = computed(() => summary.value?.findings ?? [])
const byUnit = computed(() => summary.value?.by_unit ?? [])
const daily = computed(() => summary.value?.daily ?? [])
const dailyTotal = computed(() => summary.value?.daily_total ?? null)
const totals = computed(() => summary.value?.total ?? null)

/**
 * Label, satuan, dan jumlah desimal tiap kolom ukur — SATU SUMBER, dipakai
 * kartu metrik, rekap per unit, dan rekap harian, sehingga satu perubahan
 * label tidak mungkin meninggalkan dua ejaan di layar.
 *
 * KENAPA TABEL INI ADA DI VIEW DAN BUKAN DIBACA DARI RESPONS: payload
 * Effluent Plant memang TIDAK menerbitkan label maupun satuan per metrik —
 * berbeda dari Kernel Plant, yang punya METRIC_LABELS di service-nya. Isinya
 * karena itu disalin PERSIS dari $metrikMeta pada blade screen-156 supaya
 * ponsel dan web menuliskan satuan yang sama untuk angka yang sama.
 *
 * NILAI SERVER TETAP MENANG bila kelak diterbitkan (lihat metricCardsOf):
 * tabel ini cadangan, bukan penimpa — dan TIDAK ADA PETA SATUAN DI SINI yang
 * mengubah 'C' menjadi '°C'. Bila ejaannya perlu berubah, yang berubah adalah
 * tabel label di service backend: satu tempat, dua layar.
 *
 * Ketiga kolom status dan kedua kolom teks bebas TIDAK ADA di daftar ini, dan
 * memang tidak boleh ada: terendah/rata-rata/tertinggi tidak punya arti
 * untuknya.
 */
interface MetricMeta {
  /** Judul kartu — bentuk panjang, karena kartunya satu per baris. */
  label: string
  /** Judul kolom tabel rekap — bentuk pendek, karena kolomnya empat belas. */
  short: string
  unit: string
  digits: number
}

const METRIC_META: Record<string, MetricMeta> = {
  anaerobic_pond_1_ph: { label: 'pH Kolam Anaerobik 1', short: 'pH AN-1', unit: '', digits: 2 },
  anaerobic_pond_1_temp_c: {
    label: 'Suhu Kolam Anaerobik 1',
    short: 'Suhu AN-1 (°C)',
    unit: '°C',
    digits: 1,
  },
  anaerobic_pond_2_ph: { label: 'pH Kolam Anaerobik 2', short: 'pH AN-2', unit: '', digits: 2 },
  anaerobic_pond_2_temp_c: {
    label: 'Suhu Kolam Anaerobik 2',
    short: 'Suhu AN-2 (°C)',
    unit: '°C',
    digits: 1,
  },
  cooling_pond_ph: { label: 'pH Cooling Pond', short: 'pH CP', unit: '', digits: 2 },
  cooling_pond_temp_c: { label: 'Suhu Cooling Pond', short: 'Suhu CP (°C)', unit: '°C', digits: 1 },
  biogas_flow_rate_m3h: {
    label: 'Laju Biogas',
    short: 'Biogas (m³/jam)',
    unit: 'm³/jam',
    digits: 0,
  },
  raw_pome_feed_rate_m3h: {
    label: 'Laju POME Masuk',
    short: 'POME Masuk (m³/jam)',
    unit: 'm³/jam',
    digits: 1,
  },
  effluent_discharge_flow_rate_m3h: {
    label: 'Laju Buangan Keluar',
    short: 'Buangan Keluar (m³/jam)',
    unit: 'm³/jam',
    digits: 1,
  },
  final_discharge_ph: { label: 'pH Buangan Akhir', short: 'pH Buangan', unit: '', digits: 2 },
  final_discharge_bod_mgl_lab: {
    label: 'BOD Buangan Akhir',
    short: 'BOD (mg/L)',
    unit: 'mg/L',
    digits: 0,
  },
  final_discharge_cod_mgl_lab: {
    label: 'COD Buangan Akhir',
    short: 'COD (mg/L)',
    unit: 'mg/L',
    digits: 0,
  },
  final_discharge_tss_mgl_lab: {
    label: 'TSS Buangan Akhir',
    short: 'TSS (mg/L)',
    unit: 'mg/L',
    digits: 0,
  },
  chemical_consumed_kgl: {
    label: 'Konsumsi Bahan Kimia',
    short: 'Bahan Kimia (kg/L)',
    unit: 'kg/L',
    digits: 1,
  },
}

/**
 * ENAM BLOK BERJUDUL, dan urutan kolom di dalamnya adalah urutan
 * EffluentPlantReportService::NUMERIC_METRICS — yang juga urutan keenam blok
 * tata letak pada layar web.
 *
 * PENGELOMPOKANNYA MURNI PENANDA TEMPAT bagi gulir yang panjang: tidak satu
 * pun angka dihitung per blok, dan TIDAK ADA TOTAL PER BLOK — satuan di dalam
 * satu blok pun berbeda (pH tanpa satuan, suhu °C). `compliance` menandai
 * satu-satunya blok yang memuat pernyataan kepatuhan lingkungan DI DALAM
 * dirinya.
 */
interface MetricBlock {
  testid: string
  title: string
  hint: string
  columns: string[]
  compliance: boolean
}

const METRIC_BLOCKS: MetricBlock[] = [
  {
    testid: 'metric-block-anaerobic-pond-1',
    title: 'Kolam Anaerobik 1',
    hint: 'Kolam pertama rangkaian anaerobik · 2 metrik',
    columns: ['anaerobic_pond_1_ph', 'anaerobic_pond_1_temp_c'],
    compliance: false,
  },
  {
    testid: 'metric-block-anaerobic-pond-2',
    title: 'Kolam Anaerobik 2',
    hint: 'Kolam kedua rangkaian anaerobik · 2 metrik',
    columns: ['anaerobic_pond_2_ph', 'anaerobic_pond_2_temp_c'],
    compliance: false,
  },
  {
    testid: 'metric-block-cooling-pond',
    title: 'Cooling Pond',
    hint: 'Kolam pendingin sebelum buangan akhir · 2 metrik',
    columns: ['cooling_pond_ph', 'cooling_pond_temp_c'],
    compliance: false,
  },
  {
    testid: 'metric-block-flow',
    title: 'Aliran',
    hint: 'Laju sepanjang periode — bukan jumlah produksi · 3 metrik',
    columns: [
      'biogas_flow_rate_m3h',
      'raw_pome_feed_rate_m3h',
      'effluent_discharge_flow_rate_m3h',
    ],
    compliance: false,
  },
  {
    testid: 'metric-block-final-discharge',
    title: 'Buangan Akhir',
    hint: 'pH per slot waktu · BOD / COD / TSS dari hasil laboratorium · 4 metrik',
    columns: [
      'final_discharge_ph',
      'final_discharge_bod_mgl_lab',
      'final_discharge_cod_mgl_lab',
      'final_discharge_tss_mgl_lab',
    ],
    compliance: true,
  },
  {
    testid: 'metric-block-chemical',
    title: 'Bahan Kimia',
    hint: 'Konsumsi per slot waktu · 1 metrik',
    columns: ['chemical_consumed_kgl'],
    compliance: false,
  },
]

/** Keempat belas kolom dalam urutan kanonis — judul kolom kedua tabel rekap. */
const METRIC_COLUMNS: string[] = METRIC_BLOCKS.flatMap((block) => block.columns)

/**
 * Satu kartu metrik siap render.
 *
 * Tidak ada satu pun angka yang diturunkan di sini: min/avg/max dan
 * reading_count diteruskan apa adanya dari `metrics[kolom]`, dan `isLab`
 * dibaca dari `metrics[kolom].is_lab` pada RESPONS — bukan dari daftar nama
 * kolom yang dipaku, supaya kolom laboratorium keempat kelak ditandai benar
 * tanpa menyunting berkas ini.
 *
 * Kolom yang tidak ada pada respons tetap menghasilkan kartu (nilai null,
 * penyebut 0): kartu yang hilang terbaca sebagai "tidak ada metrik ini".
 */
interface MetricCard {
  column: string
  label: string
  unit: string
  digits: number
  isLab: boolean
  min: number | null
  avg: number | null
  max: number | null
  readingCount: number
}

function metricMetaOf(column: string): MetricMeta {
  return METRIC_META[column] ?? { label: column, short: column, unit: '', digits: 2 }
}

function metricCardOf(column: string): MetricCard {
  const meta = metricMetaOf(column)
  const metric: EffluentPlantReportMetric | undefined = metrics.value[column]

  return {
    column,
    // Nilai SERVER menang bila ada; tabel view hanya cadangan, dan tidak ada
    // pemetaan satuan di antara keduanya.
    label: metric?.label || meta.label,
    unit: metric?.unit ?? meta.unit,
    digits: meta.digits,
    isLab: metric?.is_lab ?? false,
    min: metric?.min ?? null,
    avg: metric?.avg ?? null,
    max: metric?.max ?? null,
    readingCount: metric?.reading_count ?? 0,
  }
}

/** Dipakai sekali per blok oleh markup kartu yang ditulis SEKALI dan di-loop. */
function metricCardsOf(block: MetricBlock): MetricCard[] {
  return block.columns.map((column) => metricCardOf(column))
}

/** Judul kolom pendek untuk kedua tabel rekap. */
function metricShortLabel(column: string): string {
  return metricMetaOf(column).short
}

/** Jumlah desimal kolom itu, dipakai sel kedua tabel rekap. */
function metricDigits(column: string): number {
  return metricMetaOf(column).digits
}

/** Penanda lab pada judul kolom tabel rekap — diturunkan dari respons. */
function metricIsLab(column: string): boolean {
  return metrics.value[column]?.is_lab ?? false
}

/**
 * TIGA SEBARAN STATUS. Judulnya dan urutannya ditetapkan di sini; DAFTAR
 * NILAInya TIDAK — ia diiterasi dari `known_values` pada respons.
 */
interface StatusBlock {
  column: string
  testid: string
  title: string
}

const STATUS_BLOCKS: StatusBlock[] = [
  {
    column: 'biogas_flare_status',
    testid: 'status-distribution-biogas-flare',
    title: 'Sebaran Biogas Flare',
  },
  {
    column: 'dosing_pump_1_status',
    testid: 'status-distribution-dosing-pump-1',
    title: 'Sebaran Pompa Dosing 1',
  },
  {
    column: 'sludge_dewatering_status',
    testid: 'status-distribution-sludge-dewatering',
    title: 'Sebaran Sludge Dewatering',
  },
]

/**
 * Label manusiawi tiap nilai status. Nilai DI LUAR peta ini dicetak APA
 * ADANYA, bukan disembunyikan: nilai baru dari migrasi kelak harus terlihat,
 * bukan hilang.
 *
 * NILAINYA WAJIB SAMA DENGAN Display::OPTION_LABELS DI BACKEND
 * (backend/app/Support/Display.php). Layar Detail (screen-089), formulir,
 * ekspor Data Browser, dan ekspor laporan semuanya memakai peta itu, jadi
 * satu nilai hanya boleh punya satu label di seluruh produk. Layar laporan
 * web dan mobile sempat mencetak Nyala/Mati/Gangguan & Jalan/Berhenti —
 * satu-satunya tempat berbahasa Indonesia untuk kolom-kolom ini — dan
 * keduanya diselaraskan ke On/Off/Fault & Run/Stop pada 2026-10-14 atas
 * keputusan pemilik produk.
 *
 * Berbeda dari sisi web yang dapat MENDELEGASIKAN ke Display::option(),
 * klien tidak dapat memanggil PHP, jadi peta ini salinan yang harus dijaga
 * selaras. Menambah nilai baru di Display::OPTION_LABELS berarti menambahnya
 * di sini juga; sampai itu dilakukan, nilainya tetap tercetak apa adanya
 * (lihat fallback di bawah) alih-alih hilang.
 */
const STATUS_VALUE_LABELS: Record<string, string> = {
  on: 'On',
  off: 'Off',
  fault: 'Fault',
  run: 'Run',
  stop: 'Stop',
}

function statusValueLabel(value: string): string {
  return STATUS_VALUE_LABELS[value] ?? value
}

const EMPTY_DISTRIBUTION = {
  known_values: [] as string[],
  counts: {} as Record<string, number>,
  not_recorded: 0,
  recorded_slots: 0,
}

function distributionOf(column: string) {
  return statusDistribution.value[column] ?? EMPTY_DISTRIBUTION
}

function statusCountOf(column: string, value: string): number {
  return distributionOf(column).counts?.[value] ?? 0
}

/**
 * Jumlah slot bergangguan pada sebaran biogas flare — DIBACA dari counts,
 * tidak dihitung. Ini MENGHITUNG SEBUAH NILAI YANG MEMANG TERCATAT, bukan
 * penandaan di luar batas: ia tidak menilai angka ukur terhadap sebuah
 * ambang. Ditonjolkan tersendiri karena pada slot-slot itu gas tidak terbakar
 * sebagaimana mestinya, dan tetap dirender walau nilainya 0.
 */
const biogasFlareFaultCount = computed(() => statusCountOf('biogas_flare_status', 'fault'))

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
 * Ada tidaknya rekap dan grafik yang pantas digambar. Tabel kosong akan
 * terbaca sebagai hasil pengukuran bernilai nol dan grafik kosong sebagai
 * garis datar hasil pengukuran, padahal tidak ada yang diukur — sementara
 * blok CAKUPAN, KEEMPAT BELAS KARTU, dan KETIGA SEBARAN tetap digambar: yang
 * pertama menjelaskan kekosongannya, dan yang lain memperlihatkan bahwa
 * parameternya ADA dan tidak terukur.
 */
const showRecaps = computed(() => Boolean(summary.value?.has_data))

/**
 * PENYEBUT CAKUPAN TIDAK TERBENTUK — tidak ada unit ber-record, atau tidak
 * ada hari terhitung. INILAH satu-satunya keadaan yang membuat persen cakupan
 * dirender '—'.
 *
 * Diputuskan dari expected_slots === 0 dan BUKAN dari coverage_percent ===
 * null: payload Effluent Plant mengirim 0.0 pada keadaan ini (lihat
 * EffluentPlantReportService::buildSummary), jadi memutuskannya dari null
 * akan menampilkan '0,0%' justru di tempat '—' seharusnya. Sebaliknya,
 * periode yang PUNYA record tetapi tanpa satu slot terisi menghasilkan 0,0%,
 * dan itu benar — penyebutnya ada.
 */
const coverageDenominatorMissing = computed(() => (coverage.value?.expected_slots ?? 0) === 0)

/* ------------------------------------------------------------------ */
/* Tren harian — DUA, dan tidak satu pun atas kolom status             */
/* ------------------------------------------------------------------ */

interface TrendBlock {
  column: 'raw_pome_feed_rate_m3h' | 'final_discharge_ph'
  testid: string
  title: string
}

const TREND_BLOCKS: TrendBlock[] = [
  {
    column: 'raw_pome_feed_rate_m3h',
    testid: 'daily-trend-raw-pome-feed',
    title: 'Tren Harian Laju POME Masuk',
  },
  {
    column: 'final_discharge_ph',
    testid: 'daily-trend-final-discharge-ph',
    title: 'Tren Harian pH Buangan Akhir',
  },
]

function trendPointsOf(column: TrendBlock['column']): EffluentPlantReportTrendPoint[] {
  return summary.value?.daily_trend?.[column] ?? []
}

/**
 * Nilai terendah/tertinggi SERIES untuk SKALA TAMPILAN batang, ditiru baris
 * demi baris dari blade screen-156 (yang menurunkan $minTren/$maksTren dengan
 * cara yang sama persis, sehingga kedua layar menggambar batang yang sama).
 *
 * Keduanya DILABELI "dari rata-rata harian" di layar — bukan dipamerkan
 * sebagai terendah/tertinggi periode, yang punya angkanya sendiri di
 * metrics[kolom].min/max atas pembacaan mentah. Tidak ada pewarnaan
 * aman/bahaya yang diturunkan darinya.
 */
function trendSeriesOf(column: TrendBlock['column']): number[] {
  const values: number[] = []

  for (const point of trendPointsOf(column)) {
    if (point.avg !== null && point.avg !== undefined) {
      values.push(point.avg)
    }
  }

  return values
}

function trendLowest(column: TrendBlock['column']): number | null {
  const values = trendSeriesOf(column)

  return values.length === 0 ? null : Math.min(...values)
}

function trendHighest(column: TrendBlock['column']): number | null {
  const values = trendSeriesOf(column)

  return values.length === 0 ? null : Math.max(...values)
}

/**
 * Tinggi batang = 28% + 72% × (nilai − terendah) / (tertinggi − terendah) —
 * rumus yang sama dengan $tinggiBatang pada blade screen-156.
 *
 * SUMBU TEGAK SENGAJA TIDAK DIMULAI DARI NOL, dan hal itu DINYATAKAN di
 * kartunya: batang membandingkan tanggal satu sama lain, bukan besaran
 * mutlak. Ini semata skala tampilan — tidak ada nilai yang ditandai
 * aman/bahaya karenanya, dan titik tanpa pembacaan menghasilkan batang KOSONG
 * (0%), bukan batang nol yang terbaca sebagai hasil ukur.
 */
function trendBarHeight(value: number | null, column: TrendBlock['column']): string {
  const lowest = trendLowest(column)
  const highest = trendHighest(column)

  if (value === null || value === undefined || lowest === null || highest === null) {
    return '0%'
  }

  if (highest <= lowest) {
    return '100%'
  }

  return `${28 + (72 * (value - lowest)) / (highest - lowest)}%`
}

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

/**
 * Varian untuk sel tabel, yang ruangnya sempit. Sel kosong dirender '—' dan
 * BUKAN 0: nol adalah pembacaan, kosong bukan.
 */
function formatCell(value: number | null | undefined, digits = 2): string {
  if (value === null || value === undefined) {
    return '—'
  }

  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
  }).format(value)
}

/**
 * Persen kelengkapan cakupan, satu desimal. '—' HANYA bila penyebutnya tidak
 * terbentuk — keputusannya diambil pemanggil lewat
 * coverageDenominatorMissing, bukan dari null-nya nilai ini. 0 tetap dirender
 * '0,0%': penyebutnya ada, dan yang terukur memang nol slot.
 */
function formatCompletenessPercent(value: number | null | undefined): string {
  if (value === null || value === undefined) {
    return '—'
  }

  return `${new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
  }).format(value)}%`
}

/**
 * PORSI SEBUAH NILAI STATUS — satu-satunya pembagian atas angka laporan di
 * berkas ini, dan ia ada karena payload TIDAK menerbitkan `share`.
 *
 * Rumus, pembulatan, dan pemisah desimalnya DISALIN PERSIS dari helper $porsi
 * pada blade screen-156 (number_format(100 * count / recordedSlots, 1)),
 * supaya ponsel tidak mungkin mencetak porsi yang berbeda dari web untuk
 * payload yang sama.
 *
 * PENYEBUTNYA recorded_slots — slot TERCATAT — BUKAN expected_slots: penyebut
 * kedua itu akan mencampur slot tak tersentuh ke dalam porsi sebuah nilai
 * yang justru tercatat. Penyebut nol menghasilkan '—', bukan 0,0%.
 */
function formatShare(count: number, recordedSlots: number): string {
  if (!recordedSlots) {
    return '—'
  }

  return `${new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
  }).format((100 * count) / recordedSlots)}%`
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

/** Tanggal pendek untuk label sumbu tren dan judul kejadian teks bebas. */
function formatShortDate(value: string | null | undefined): string {
  if (!value) {
    return '-'
  }

  const [, month, day] = value.slice(0, 10).split('-')

  if (!month || !day) {
    return value
  }

  return `${day} ${MONTHS_SHORT[Number(month) - 1] ?? month}`
}

/** Label sumbu tren: tanggalnya saja, supaya label tidak bertumpuk. */
function formatDayAxis(value: string | null | undefined): string {
  if (!value) {
    return '-'
  }

  return value.slice(8, 10) || value
}

/**
 * Slot waktu APA ADANYA dari server (sudah berbentuk 'HH:MM' di payload).
 * Tidak ada penormalan di sini — penormalan jam adalah tempat lahirnya
 * selisih antara ponsel dan web.
 */
function formatTimeSlot(value: string | null | undefined): string {
  return value ? value : '-'
}

/**
 * Penamaan unit Effluent Plant yang kosong. effluent_plant_id adalah kolom
 * TEKS yang diketik di layar input dan bukan kunci asing, jadi nilai kosong
 * mungkin saja sampai ke sini — barisnya tetap diberi label alih-alih
 * dirender kosong, karena sel tanpa label membuat seluruh baris terbaca
 * sebagai cacat tampilan. Label ini keputusan view, bukan sesuatu yang repo
 * seragamkan.
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

function periodOptionLabel(period: EffluentPlantReportPeriodOption): string {
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
  <main class="laporan-pr-view" data-testid="laporan-effluent-plant-mobile">
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
          {{ aiAssistantStore.bubbleEnabled ? 'Nonaktifkan Bubble Chat AI' : 'Aktifkan Bubble Chat AI' }}
        </button>
        <button type="button" class="nav-menu-item" @click="openAiAssistant">Bantuan AI</button>
        <button
          type="button"
          class="nav-menu-item"
          data-testid="nav-menu-logout"
          @click="onLogout"
        >
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
        <span aria-current="page">Laporan Effluent Plant</span>
      </nav>
      <h1 class="screen-title">Laporan Effluent Plant</h1>
    </div>

    <!-- Akun terikat mill tetapi mill-nya kosong: berhenti total, tanpa satu
         pun permintaan HTTP dan tanpa pemilih mill sebagai pengganti. -->
    <p
      v-if="noMillForAccount"
      class="notice notice--warning"
      role="alert"
      data-testid="no-mill-for-account"
    >
      Akun Anda belum terhubung ke mill mana pun, sehingga laporan tidak dapat ditampilkan.
      Silakan hubungi Admin untuk menghubungkan akun Anda ke sebuah mill.
    </p>

    <template v-else>
      <FilterPanel aria-label="Filter laporan">
        <!-- TIDAK ADA pemilih Mill di layar ini: kedua aktornya terikat satu
             mill, dan mill-nya diselesaikan server dari akun. Namanya tetap
             tampil sebagai chip di bawah — yang tidak ada hanyalah kemampuan
             menggantinya, dan daftar seluruh mill tidak pernah dipanggil dari
             layar ini. -->

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
            <option v-for="line in productionLines" :key="line.id" :value="line.id">
              {{ line.name }}
            </option>
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
        Production Line, sehingga tidak ada pilihan gabungan lintas line — dan selama belum ada
        line yang berlaku, tidak satu angka pun diminta ke server.
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
        Mill ini belum memiliki periode pelaporan yang mencakup stasiun Effluent Plant.
        Silakan hubungi Admin untuk membuat periode pelaporan terlebih dahulu.
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

      <p v-else-if="errorMessage" class="notice notice--error" role="alert" data-testid="error-message">
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
            ditutup tetap dapat dilihat dan diekspor.
          </span>
        </div>

        <p v-if="emptyPeriod" class="notice" data-testid="empty-period">
          Belum ada slot Effluent Plant yang terisi pada periode ini untuk Production Line yang
          dipilih. Keempat belas kartu metrik di bawah tetap ditampilkan dengan penyebut nol,
          supaya terlihat bahwa parameternya ada dan tidak terukur — bukan bahwa hasilnya nol.
        </p>

        <!-- ===== Cakupan pencatatan — PALING ATAS =====
             Bukan catatan kaki: empat belas kolom ukur seluruhnya nullable,
             jadi periode yang terisi seperlima pun menghasilkan rata-rata yang
             terlihat rapi. TETAP digambar meski periode kosong — justru itu
             yang menjelaskan kekosongannya. -->
        <section class="detail-section" data-testid="coverage">
          <h2 class="section-title">Cakupan Pencatatan</h2>
          <p class="section-note">
            Baca ini lebih dulu — seluruh angka di bawah bersandar padanya.
          </p>

          <div class="metric-stack">
            <div class="metric-card">
              <span class="metric-label">Slot Waktu Terisi Sepanjang Periode</span>
              <span class="metric-value" data-testid="coverage-slots">
                {{ formatCount(coverage?.filled_slots) }} dari
                {{ formatCount(coverage?.expected_slots) }} slot
              </span>
              <!-- Tanda pisah HANYA ketika penyebutnya tidak terbentuk
                   (expected_slots nol). Periode yang punya record tetapi tanpa
                   satu slot terisi menghasilkan 0,0% — dan itu benar, karena
                   penyebutnya ada. -->
              <span
                class="metric-note"
                :class="{ 'metric-value--na': coverageDenominatorMissing }"
                data-testid="coverage-percent"
              >
                {{
                  coverageDenominatorMissing
                    ? '—'
                    : formatCompletenessPercent(coverage?.coverage_percent)
                }}
              </span>
              <span
                v-if="coverageDenominatorMissing"
                class="metric-note"
                data-testid="coverage-no-expected-note"
              >
                Belum ada slot yang diharapkan: tidak ada unit Effluent Plant ber-record pada
                periode dan line ini, atau belum ada satu hari pun yang dapat dihitung. Karena
                penyebutnya belum terbentuk, persennya &ldquo;&mdash;&rdquo; dan BUKAN 0,0%.
              </span>
            </div>

            <div class="metric-card">
              <span class="metric-label">Pembentuk Penyebut</span>
              <span class="metric-value" data-testid="coverage-denominator">
                {{ formatCount(coverage?.effluent_plant_unit_count) }} unit ×
                {{ formatCount(coverage?.days_counted) }} hari ×
                {{ formatCount(coverage?.slots_per_unit_per_day) }} slot
              </span>
              <span class="metric-note" data-testid="coverage-formula">
                Slot yang diharapkan = {{ formatCount(coverage?.effluent_plant_unit_count) }} unit
                Effluent Plant × {{ formatCount(coverage?.days_counted) }} hari ×
                {{ formatCount(coverage?.slots_per_unit_per_day) }} slot =
                {{ formatCount(coverage?.expected_slots) }}. Jumlah unit adalah unit yang
                BENAR-BENAR punya record pada periode dan line ini, bukan jumlah stasiun terdaftar;
                dan 24 slot kanonis (07:00&ndash;06:00) diambil dari kisi slot layar input itu
                sendiri, bukan dikarang di sini.
              </span>
            </div>

            <div class="metric-card">
              <span class="metric-label">Hari Dihitung</span>
              <span class="metric-value" data-testid="coverage-days">
                {{ formatCount(coverage?.days_counted) }} dari
                {{ formatCount(coverage?.days_in_period) }} hari
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
                Periode belum mulai, sehingga belum ada hari yang dapat dijadikan pembagi —
                persennya &ldquo;&mdash;&rdquo;, bukan 0%.
              </span>
              <span
                v-else-if="coverage?.period_running"
                class="metric-note"
                data-testid="coverage-running-note"
              >
                Dihitung sampai hari ini, periode masih berjalan
                ({{ formatCount(coverage?.days_counted) }} dari
                {{ formatCount(coverage?.days_in_period) }} hari periode sudah lewat). Hari yang
                belum terjadi tidak mungkin tercatat, jadi tidak ikut menjadi penyebut.
              </span>
              <span class="metric-note" data-testid="draft-note">
                Record berstatus draft IKUT terhitung pada seluruh angka laporan ini.
              </span>
            </div>
          </div>

          <!-- SEMBILAN BELAS KOLOM, dan karena itu slot terisi dapat MELEBIHI
               penyebut setiap kartu metrik. Dinyatakan di sini supaya pembaca
               berikutnya tidak "memperbaiki" selisih yang memang benar. -->
          <p class="section-note" data-testid="coverage-nineteen-columns-note">
            Satu slot dianggap <strong>terisi</strong> bila
            <strong>salah satu dari 19 kolomnya</strong> terisi — keempat belas kolom ukur, ketiga
            kolom status, atau <strong>kedua kolom teks</strong> (catatan perawatan dan temuan).
            Definisinya dipinjam dari layar input itu sendiri, bukan diturunkan ulang di sini.
            Karena itu jumlah slot terisi di atas dapat
            <strong>MELEBIHI penyebut setiap kartu metrik</strong>: sebuah slot yang hanya mengisi
            temuan tetap terhitung pada cakupan tanpa menambah penyebut kartu mana pun. Yang
            dilampaui adalah penyebut <strong>kartu</strong>, bukan penyebut cakupan — itu benar,
            bukan selisih yang perlu dicocokkan. Seluruh angka di bawah dihitung dari
            <strong>pembacaan yang ada</strong>, bukan dari periode penuh: slot yang tidak terisi
            tidak diperlakukan sebagai nol, melainkan tidak ikut dihitung sama sekali.
          </p>
        </section>

        <!-- ===== EMPAT BELAS KARTU DALAM ENAM BLOK BERJUDUL =====
             Markup kartunya ditulis SEKALI dan di-loop, bukan disalin per blok:
             pengulangan markup berarti sebuah blok dapat diam-diam kehilangan
             penyebutnya atau MEWARISI penyebut blok lain. Pengelompokan enam
             blok murni penanda tempat — tidak ada total per blok. -->
        <section
          v-for="(block, blockIndex) in METRIC_BLOCKS"
          :key="block.testid"
          class="detail-section"
          :data-testid="block.testid"
        >
          <h2 class="section-title">{{ block.title }}</h2>
          <p class="section-note">
            Blok {{ blockIndex + 1 }} dari {{ METRIC_BLOCKS.length }} &middot; {{ block.hint }}
          </p>

          <div class="metric-stack">
            <article
              v-for="card in metricCardsOf(block)"
              :key="card.column"
              class="metric-card"
              data-testid="metric-card"
              :data-metric="card.column"
            >
              <span class="metric-label">
                {{ card.label }}<small v-if="card.unit"> &middot; {{ card.unit }}</small>
                <!-- PENANDA 'lab' DI DALAM KARTUNYA, diturunkan dari
                     metrics[kolom].is_lab pada respons — bukan dari daftar nama
                     kolom yang dipaku di sini, supaya kolom laboratorium
                     keempat kelak ditandai benar tanpa menyunting berkas ini.
                     Di ponsel pembaca melihat satu kartu pada satu waktu, jadi
                     keterangan di atas halaman tidak hadir saat angkanya
                     dibaca. -->
                <span v-if="card.isLab" class="metric-pill" data-testid="metric-lab-badge">lab</span>
              </span>

              <div class="metric-row">
                <span class="metric-row-item">
                  <small>terendah</small>
                  <b :class="{ 'metric-value--na': card.min === null }">
                    {{ formatDecimal(card.min, card.digits) }}
                  </b>
                </span>
                <span class="metric-row-item">
                  <small>rata-rata</small>
                  <b :class="{ 'metric-value--na': card.avg === null }" data-testid="metric-avg">
                    {{ formatDecimal(card.avg, card.digits) }}
                  </b>
                </span>
                <span class="metric-row-item">
                  <small>tertinggi</small>
                  <b :class="{ 'metric-value--na': card.max === null }">
                    {{ formatDecimal(card.max, card.digits) }}
                  </b>
                </span>
              </div>

              <!-- PENYEBUT MILIK KARTU INI SENDIRI. Empat belas kolom nullable
                   dan terisi saling bebas, jadi satu penyebut bersama akan
                   salah untuk setidaknya tiga belas di antaranya. Kartu tanpa
                   satu pun pembacaan TETAP dirender dengan penyebut 0. -->
              <span class="metric-note" data-testid="metric-denominator">
                dari {{ formatCount(card.readingCount) }} pembacaan
              </span>

              <span v-if="card.isLab" class="metric-note" data-testid="metric-lab-note">
                Nilainya datang dari <strong>laboratorium</strong>, diperiksa beberapa hari sekali
                dan bukan per slot waktu — jadi penyebut
                {{ formatCount(card.readingCount) }} pembacaan di atas adalah
                <strong>keadaan normal</strong>, bukan data yang hilang. Rata-rata dari belasan
                pembacaan tidak boleh terlihat sama meyakinkan dengan rata-rata dari ratusan
                pembacaan, dan itulah sebabnya tiap kartu mencetak penyebutnya sendiri alih-alih
                berbagi satu penyebut.
              </span>
            </article>
          </div>

          <!-- PERNYATAAN KEPATUHAN LINGKUNGAN DI DALAM BLOK BUANGAN AKHIR,
               tepat di dekat keempat angkanya. Bukan catatan kaki halaman: di
               ponsel itu berarti puluhan layar jauhnya dari angka yang
               dirujuknya, dan angka lingkungan tanpa batas hukumnya mengundang
               pembaca memasok batas itu dari ingatan. -->
          <p
            v-if="block.compliance"
            class="section-note"
            data-testid="environmental-compliance-note"
          >
            <strong>Laporan ini tidak menyatakan kepatuhan lingkungan.</strong> pH, BOD, COD, dan
            TSS buangan akhir adalah parameter yang
            <strong>batas regulasinya ditetapkan di luar aplikasi ini</strong>, dan Effluent Plant
            tidak punya tabel master target operasional. Keempat angka di atas ditampilkan apa
            adanya — laporan ini tidak menyatakan patuh maupun tidak patuh, dan tidak menandai satu
            pun nilai sebagai di luar batas. Ambang yang diturunkan dari data periode itu sendiri
            berisiko terbaca sebagai batas aman padahal ia hanya menggambarkan periode ini;
            penilaiannya ada pada manusia yang memegang baku mutunya.
          </p>
        </section>

        <!-- Keterangan yang membuat pengelompokan enam blok, kartu yang selalu
             ada, dan KETIADAAN target serta downtime dapat dibaca. Ketiadaan
             yang tidak dijelaskan terbaca sebagai fitur yang belum selesai. -->
        <section class="detail-section" data-testid="metric-blocks-note">
          <h2 class="section-title">Cara Membaca Keempat Belas Kartu</h2>
          <p class="section-note">
            Keempat belas kartu di atas dikelompokkan menjadi
            <strong>enam blok berjudul</strong> yang sama dengan versi web. Pengelompokan itu
            <strong>murni penanda tempat</strong> bagi gulir yang panjang — tidak satu pun angka
            dihitung per blok, dan <strong>tidak ada total per blok</strong>, karena satuan di dalam
            satu blok pun berbeda. Keempat belas kartu selalu dirender, termasuk kolom yang tidak
            pernah terisi: kartunya tetap ada dengan keterangan &lsquo;tidak tersedia&rsquo; dan
            penyebut 0 pembacaan, karena kartu yang hilang terbaca sebagai &lsquo;tidak ada metrik
            ini&rsquo; padahal yang benar adalah &lsquo;tidak ada yang mengukurnya&rsquo;.
          </p>
          <p class="section-note" data-testid="no-target-note">
            <strong>Tidak ada kolom target maupun rencana tindakan koreksi</strong> pada kartu mana
            pun di layar ini, karena stasiun ini
            <strong>tidak punya tabel master target</strong>. Kernel Plant dan Depricarping mobile
            membawa kolom target di tiap kartunya; di sini tidak ada medan yang dikarang sebagai
            keterangan selalu-kosong hanya demi sebangun dengan kedua laporan itu.
          </p>
          <p class="section-note" data-testid="no-downtime-note">
            <strong>Tidak ada pula blok downtime</strong>, karena stasiun ini
            <strong>tidak mencatat downtime sama sekali</strong> — effluent_plant_details tidak
            punya satu pun kolomnya. Ketiadaannya dinyatakan di sini supaya tidak terbaca sebagai
            bagian yang gagal dimuat, dan supaya tidak ditambahkan kelak hanya karena
            &lsquo;laporan mobile lain punya&rsquo;.
          </p>
          <p class="section-note" data-testid="no-flagging-note">
            <strong>Laporan ini tidak menandai satu nilai pun sebagai di luar batas, dan sebabnya
            dinyatakan utuh di sini</strong> — tidak diringkas demi ruang layar. Empat di antara
            keempat belas metrik (pH, BOD, COD, dan TSS buangan akhir) adalah parameter kepatuhan
            lingkungan yang baku mutunya hidup <strong>di luar aplikasi ini</strong>, dan stasiun
            ini tidak punya master target untuk sebelas metrik lainnya. Satu-satunya ambang yang
            dapat dihitung di sini adalah ambang yang diturunkan dari data periode itu sendiri — dan
            ambang semacam itu berisiko terbaca sebagai batas aman padahal ia hanya menggambarkan
            periode ini. Karena itu tidak ada pewarnaan aman/bahaya, tidak ada ikon peringatan, dan
            tidak ada nilai yang ditebalkan sebagai pelanggaran, di seluruh layar ini.
          </p>
        </section>

        <!-- ===== TIGA SEBARAN STATUS =====
             Kategoris: terendah/rata-rata/tertinggi tidak punya arti, jadi
             ketiganya diterbitkan sebagai sebaran jumlah slot per nilai, dan
             tidak satu pun muncul pada kedua grafik tren. ITERASINYA ATAS
             known_values, bukan atas kunci counts yang ada. -->
        <section
          v-for="block in STATUS_BLOCKS"
          :key="block.testid"
          class="detail-section"
          :data-testid="block.testid"
        >
          <h2 class="section-title">{{ block.title }}</h2>
          <p class="section-note">
            {{ formatCount(distributionOf(block.column).known_values.length) }} nilai dikenal
            &middot; dasar
            <strong>{{ formatCount(distributionOf(block.column).recorded_slots) }} slot tercatat</strong>
          </p>

          <div class="detail-table-wrap">
            <table v-stacked-table class="detail-table" :data-testid="`${block.testid}-table`">
              <thead>
                <tr>
                  <th scope="col">Nilai</th>
                  <th scope="col" class="num">Slot</th>
                  <th scope="col" class="num">Porsi</th>
                </tr>
              </thead>
              <tbody>
                <!-- ITERASI known_values, BUKAN kunci counts: nilai dikenal
                     yang jumlahnya 0 TETAP tercetak. Baris yang hilang terbaca
                     sebagai "nilai ini tidak ada" padahal yang benar adalah
                     "nilai ini tidak pernah terjadi". -->
                <tr
                  v-for="value in distributionOf(block.column).known_values"
                  :key="value"
                  data-testid="status-row"
                  :data-status-value="value"
                >
                  <td>{{ statusValueLabel(value) }}</td>
                  <td class="num">{{ formatCount(statusCountOf(block.column, value)) }}</td>
                  <td class="num">
                    {{
                      formatShare(
                        statusCountOf(block.column, value),
                        distributionOf(block.column).recorded_slots,
                      )
                    }}
                  </td>
                </tr>
                <!-- BARIS TERPISAH, dan TIDAK PERNAH dilipat ke Mati/Berhenti:
                     kolom yang kosong berarti tidak ada yang mencatatnya, bukan
                     flare yang mati atau pompa yang berhenti. -->
                <tr data-testid="status-not-recorded">
                  <td>Tidak tercatat</td>
                  <td class="num">{{ formatCount(distributionOf(block.column).not_recorded) }}</td>
                  <td class="num">
                    {{
                      formatShare(
                        distributionOf(block.column).not_recorded,
                        distributionOf(block.column).recorded_slots,
                      )
                    }}
                  </td>
                </tr>
              </tbody>
              <tfoot>
                <tr data-testid="status-recorded-total">
                  <td>Seluruh slot tercatat</td>
                  <td class="num">
                    {{ formatCount(distributionOf(block.column).recorded_slots) }}
                  </td>
                  <td class="num">
                    {{ distributionOf(block.column).recorded_slots === 0 ? '—' : '100,0%' }}
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>

          <!-- MENGHITUNG SEBUAH NILAI YANG MEMANG TERCATAT, bukan penandaan di
               luar batas: ia tidak menilai angka ukur terhadap sebuah ambang.
               Tetap dirender walau jumlahnya 0. -->
          <p
            v-if="block.column === 'biogas_flare_status'"
            class="section-note"
            data-testid="biogas-flare-fault-count"
          >
            <strong>{{ formatCount(biogasFlareFaultCount) }} slot bergangguan</strong> — pada
            slot-slot itu gas tidak terbakar sebagaimana mestinya, jadi jumlahnya ditonjolkan
            tersendiri di sini. <strong>Nilai dikenal yang tidak pernah muncul tampil 0, bukan
            hilang dari daftar:</strong> baris nol itulah yang membedakan &lsquo;tidak pernah
            terjadi&rsquo; dari &lsquo;nilai ini tidak ada&rsquo;. Baris yang hilang akan terbaca
            sebagai yang kedua.
          </p>
        </section>

        <section class="detail-section" data-testid="status-distribution-note">
          <h2 class="section-title">Mengapa Ketiga Kolom Itu Sebaran</h2>
          <p class="section-note">
            Ketiga kolom di atas <strong>kategoris</strong>: terendah, rata-rata, dan tertinggi
            tidak punya arti untuknya, jadi ketiganya diterbitkan sebagai
            <strong>sebaran jumlah slot per nilai</strong> — dan tidak satu pun dari ketiganya
            muncul pada kedua grafik tren.
            <strong>Porsi memakai slot TERCATAT sebagai penyebut, bukan slot yang
            diharapkan</strong>; penyebut kedua itu akan mencampur &lsquo;tidak tercatat&rsquo; ke
            dalam porsi tiap nilai. Sebaran disusun dengan mengelompokkan nilai yang benar-benar
            ada, lalu <strong>melengkapi nilai yang dikenal tetapi tidak muncul dengan jumlah
            nol</strong>. Slot yang tidak mencatat sebuah kolom status dilaporkan
            <strong>terpisah</strong> dan <strong>tidak pernah</strong> dilipat menjadi
            &lsquo;Mati&rsquo; atau &lsquo;Berhenti&rsquo; — kolom yang kosong berarti tidak ada
            yang mencatatnya, bukan alat yang mati atau berhenti.
          </p>
        </section>

        <!-- ===== DUA REKAP TEKS BEBAS, DUA BAGIAN TERPISAH =====
             Masing-masing daftar KEJADIAN {tanggal, slot waktu, teks} — BUKAN
             dikelompokkan menurut teksnya seperti Kernel Plant mobile, karena
             pengelompokan itu menghilangkan tanggal dan slot waktu yang justru
             satu-satunya cara menemukan kembali kejadiannya. -->
        <section class="detail-section" data-testid="maintenance-remarks">
          <h2 class="section-title">
            Rekap Catatan Perawatan ({{ formatCount(maintenanceRemarks.length) }} slot)
          </h2>
          <p class="section-note">
            Satu entri <strong>per slot</strong> yang mengisinya, membawa tanggal dan slot waktunya.
            Daftar ini menjawab <strong>apa yang dikerjakan</strong>.
          </p>

          <p
            v-if="maintenanceRemarks.length === 0"
            class="section-note"
            data-testid="maintenance-remarks-empty"
          >
            Tidak ada satu pun catatan perawatan tercatat pada periode dan line ini. Ini dinyatakan
            sebagai keterangan pada bagian ini sendiri, bukan tabel kosong tanpa penjelasan — tabel
            kosong tidak membedakan &lsquo;tidak ada catatan&rsquo; dari &lsquo;tidak ada yang
            mencatatnya&rsquo;.
          </p>

          <div v-else class="note-list">
            <article
              v-for="(entry, index) in maintenanceRemarks"
              :key="`mr-${entry.date}-${entry.time_slot}-${index}`"
              class="note-item"
              data-testid="maintenance-remark-row"
            >
              <span class="note-meta">
                {{ formatShortDate(entry.date) }} &middot; {{ formatTimeSlot(entry.time_slot) }}
              </span>
              <p class="note-text">{{ entry.text }}</p>
            </article>
          </div>

          <p class="section-note" data-testid="maintenance-remarks-note">
            Isinya ditampilkan apa adanya beserta tanggal dan slot waktunya,
            <strong>dibungkus utuh tanpa pemotongan</strong> — tidak pernah dirata-rata, tidak
            dijadikan terendah atau tertinggi, dan tidak muncul pada grafik tren mana pun.
          </p>
        </section>

        <section class="detail-section" data-testid="findings">
          <h2 class="section-title">Rekap Temuan ({{ formatCount(findings.length) }} slot)</h2>
          <p class="section-note">
            Bentuk yang sama — satu entri per slot dengan tanggal dan slot waktunya — pada bagian
            yang <strong>TERPISAH</strong> dari rekap catatan perawatan. Daftar ini menjawab
            <strong>apa yang ditemukan</strong>.
          </p>

          <p v-if="findings.length === 0" class="section-note" data-testid="findings-empty">
            Tidak ada satu pun temuan tercatat pada periode dan line ini. Ini dinyatakan sebagai
            keterangan pada bagian ini sendiri, bukan tabel kosong tanpa penjelasan.
          </p>

          <div v-else class="note-list">
            <article
              v-for="(entry, index) in findings"
              :key="`fd-${entry.date}-${entry.time_slot}-${index}`"
              class="note-item"
              data-testid="finding-row"
            >
              <span class="note-meta">
                {{ formatShortDate(entry.date) }} &middot; {{ formatTimeSlot(entry.time_slot) }}
              </span>
              <p class="note-text">{{ entry.text }}</p>
            </article>
          </div>

          <p class="section-note" data-testid="findings-note">
            Pertanyaannya <strong>berbeda</strong> dari Rekap Catatan Perawatan, karena itu keduanya
            dua bagian terpisah dan tidak pernah disatukan. Satu slot yang mengisi kedua kolom
            sekaligus <strong>muncul pada kedua rekap</strong>; itu benar,
            <strong>bukan penghitungan ganda</strong> — menyatukan keduanya justru akan menghitung
            slot itu dua kali di dalam satu daftar dan mencampur dua pertanyaan yang berbeda. Jumlah
            pada kedua judul karena itu tidak boleh dijumlahkan: slot yang mengisi keduanya terhitung
            sekali pada masing-masing daftar.
          </p>
        </section>

        <template v-if="showRecaps">
          <!-- ===== DUA TREN HARIAN =====
               Hanya dua: laju POME masuk dan pH buangan akhir. Ketiga nilai
               laboratorium SENGAJA tidak digrafikkan harian — dengan penyebut
               sekecil itu garisnya akan lebih banyak lubang daripada data.
               Keduanya menggulir mendatar DI DALAM kartunya. -->
          <section
            v-for="trend in TREND_BLOCKS"
            :key="trend.testid"
            class="detail-section"
            :data-testid="trend.testid"
          >
            <h2 class="section-title">{{ trend.title }}</h2>
            <p class="section-note">
              Rata-rata per tanggal{{
                metricCardOf(trend.column).unit ? `, dalam ${metricCardOf(trend.column).unit}` : ''
              }}
              &middot; {{ formatCount(trendPointsOf(trend.column).length) }} tanggal berdata
              &middot; {{ formatCount(metricCardOf(trend.column).readingCount) }} pembacaan
            </p>

            <div class="chart-scroll">
              <div class="chart-bars">
                <!-- Tanpa modifier warna apa pun: tidak ada batang yang
                     ditandai rendah, tinggi, maupun di luar batas. Titik tanpa
                     pembacaan bernilai tidak tersedia, BUKAN nol — batangnya
                     karena itu kosong. -->
                <div
                  v-for="point in trendPointsOf(trend.column)"
                  :key="`${trend.testid}-${point.date}`"
                  class="chart-col"
                  :data-testid="`${trend.testid}-col-${point.date}`"
                >
                  <span class="chart-value">
                    {{ formatDecimal(point.avg, metricCardOf(trend.column).digits) }}
                  </span>
                  <div class="chart-track">
                    <div
                      class="chart-fill"
                      :style="{ height: trendBarHeight(point.avg, trend.column) }"
                    ></div>
                  </div>
                  <span class="chart-axis">{{ formatDayAxis(point.date) }}</span>
                </div>
              </div>
            </div>

            <p class="section-note" :data-testid="`${trend.testid}-scroll-hint`">
              Geser mendatar <strong>di dalam kartu ini</strong> untuk melihat seluruh tanggal;
              halaman sendiri tidak menggulir mendatar.
            </p>
            <p class="section-note">
              Terendah
              {{ formatDecimal(trendLowest(trend.column), metricCardOf(trend.column).digits) }}
              &middot; tertinggi
              {{ formatDecimal(trendHighest(trend.column), metricCardOf(trend.column).digits) }}
              <small>(dari rata-rata harian)</small>
            </p>
            <p class="section-note">
              Rata-rata periode
              {{ formatDecimal(metricCardOf(trend.column).avg, metricCardOf(trend.column).digits) }}
              <small>
                (berbobot {{ formatCount(metricCardOf(trend.column).readingCount) }} pembacaan,
                dihitung ulang server atas seluruh slot — bukan rata-rata dari rata-rata harian)
              </small>
            </p>
            <p class="section-note">
              <small>
                Sumbu tegak tidak dimulai dari nol — batang membandingkan tanggal, bukan besaran
                mutlak. Tidak ada rentang &lsquo;aman&rsquo; yang digambar di sini: batas
                regulasinya hidup di luar aplikasi.
              </small>
            </p>
          </section>

          <!-- ===== Rekap per unit Effluent Plant =====
               EMPAT BELAS kolom rata-rata — menggulir DI DALAM kartu ini. -->
          <section class="detail-section" data-testid="by-unit">
            <h2 class="section-title">
              Rekap per Unit Effluent Plant ({{ formatCount(byUnit.length) }} unit)
            </h2>
            <p class="section-note">
              Angka periode di atas menggabungkan seluruh unit — tabel ini menunjukkan sebarannya,
              dengan <strong>empat belas kolom rata-rata</strong>. effluent_plant_id adalah NAMA
              UNIT yang diketik di layar input, bukan kunci baris. Geser mendatar
              <strong>di dalam kartu ini</strong>; halaman sendiri tetap tidak menggulir mendatar.
            </p>
            <div class="detail-table-wrap">
              <table v-stacked-table class="detail-table" data-testid="by-unit-table">
                <thead>
                  <tr>
                    <th scope="col">Unit</th>
                    <th scope="col" class="num">Slot terisi</th>
                    <th v-for="column in METRIC_COLUMNS" :key="column" scope="col" class="num">
                      {{ metricShortLabel(column) }}<template v-if="metricIsLab(column)"> &middot; lab</template>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Unit yang punya record tetapi tidak satu pun pembacaan
                       TETAP muncul dengan slot terisi 0 — membuangnya justru
                       akan menyembunyikan unit yang tidak pernah dituliskan. -->
                  <tr
                    v-for="row in byUnit"
                    :key="row.effluent_plant_id"
                    data-testid="by-unit-row"
                  >
                    <td>{{ formatGroupLabel(row.effluent_plant_id) }}</td>
                    <td class="num" data-testid="by-unit-reading-rows">
                      {{ formatCount(row.reading_rows) }}
                    </td>
                    <td v-for="column in METRIC_COLUMNS" :key="column" class="num">
                      {{ formatCell(row.metrics?.[column]?.avg ?? null, metricDigits(column)) }}
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <!-- Angka gabungan DIAMBIL dari `metrics` tingkat periode,
                       yang DIHITUNG ULANG server atas seluruh slot terisi —
                       bukan dirata-ratakan dari baris per unit di atasnya. -->
                  <tr data-testid="by-unit-row-total">
                    <td>Gabungan seluruh unit</td>
                    <td class="num">{{ formatCount(coverage?.filled_slots) }}</td>
                    <td v-for="column in METRIC_COLUMNS" :key="column" class="num">
                      {{ formatCell(metricCardOf(column).avg, metricDigits(column)) }}
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
            <p class="section-note">
              Tiap unit membawa keempat belas rata-rata dengan
              <strong>penyebutnya sendiri</strong>, persis seperti kartu periode.
            </p>
          </section>

          <!-- ===== Rekap per hari =====
               Panjang pada periode sebulan, jadi dibungkus CollapsibleSection
               dan TERTUTUP secara bawaan. Membuka atau menutupnya tidak
               memicu permintaan apa pun — datanya sudah ada di memori — dan
               tidak mengubah satu angka pun, termasuk baris total periode. -->
          <CollapsibleSection
            title="Rekap per Hari"
            :subtitle="`${daily.length} hari`"
            data-testid="daily-recap"
          >
            <div class="detail-table-wrap">
              <table v-stacked-table class="detail-table" data-testid="daily-table">
                <thead>
                  <tr>
                    <th scope="col">Tanggal</th>
                    <th scope="col" class="num">Slot Terisi</th>
                    <th v-for="column in METRIC_COLUMNS" :key="column" scope="col" class="num">
                      {{ metricShortLabel(column) }}<template v-if="metricIsLab(column)"> &middot; lab</template>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <!-- Tanggal tanpa satu pun slot terisi TIDAK mendapat baris:
                       baris nol akan terbaca sebagai "terukur nol". Sel kosong
                       dirender '—', BUKAN 0 — nol adalah pembacaan, kosong
                       bukan. -->
                  <tr v-for="row in daily" :key="row.date" :data-testid="`daily-row-${row.date}`">
                    <td>{{ formatDate(row.date) }}</td>
                    <td class="num">{{ formatCount(row.reading_rows) }}</td>
                    <td v-for="column in METRIC_COLUMNS" :key="column" class="num">
                      {{ formatCell(row.metrics_avg?.[column]?.avg ?? null, metricDigits(column)) }}
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <!-- TOTAL PERIODE DIHITUNG ULANG SERVER atas seluruh slot
                       terisi, bukan merata-ratakan rata-rata harian:
                       rata-rata dari rata-rata memberi bobot sama pada hari
                       berisi dua slot dan hari berisi dua puluh empat. Karena
                       itu pula ia tidak berubah ketika rekap harian ditutup. -->
                  <tr data-testid="daily-row-total">
                    <td>Total periode</td>
                    <td class="num">{{ formatCount(dailyTotal?.reading_rows) }}</td>
                    <td v-for="column in METRIC_COLUMNS" :key="column" class="num">
                      {{ formatCell(metricCardOf(column).avg, metricDigits(column)) }}
                      <small>(n={{ formatCount(metricCardOf(column).readingCount) }})</small>
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
            <p class="section-note">
              Kolom BOD, COD, dan TSS kosong pada hari-hari tanpa sampel laboratorium, dan sel
              kosong dirender &lsquo;&mdash;&rsquo; — <strong>bukan 0</strong>, karena nol adalah
              pembacaan dan kosong bukan.
            </p>
          </CollapsibleSection>

          <!-- ===== Kelengkapan record ===== -->
          <section class="detail-section" data-testid="completeness">
            <h2 class="section-title">Kelengkapan Record</h2>
            <p class="section-note">
              Seluruh angka di bawah IKUT terhitung pada laporan — tidak satu pun menjadi penyaring.
            </p>
            <div class="queue-figures">
              <div class="queue-figure">
                <span class="queue-label">Hari yang punya record</span>
                <span class="queue-value" data-testid="days-with-records">
                  {{ formatCount(totals?.days_with_records) }}
                </span>
                <span class="queue-note">
                  hari ber-record, bukan hari ber-slot-terisi: sebuah hari yang punya record tetapi
                  seluruh barisnya kosong tetap sebuah hari yang dicatat orang
                </span>
              </div>
              <div class="queue-figure">
                <span class="queue-label">Slot terisi</span>
                <span class="queue-value" data-testid="total-reading-rows">
                  {{ formatCount(totals?.reading_rows) }}
                </span>
                <span class="queue-note">
                  satu definisi, satu jawaban — angka ini sama dengan jumlah slot terisi pada
                  cakupan di atas
                </span>
              </div>
            </div>
            <p class="section-note">
              Record berstatus draft <strong>IKUT terhitung</strong> pada seluruh angka laporan ini,
              dan hal itu dinyatakan supaya pembaca tahu bagian mana yang masih dapat berubah.
              Status verifikasi juga kelengkapan, bukan penyaring — record yang belum diperiksa
              tetap terhitung penuh.
            </p>
          </section>
        </template>
      </template>

      <footer class="action-footer">
        <!-- Ekspor TIDAK PERNAH dinonaktifkan oleh status periode: kunci
             periode mengatur penulisan data, bukan pembacaan laporan. CSV
             SAJA — tidak ada pilihan .xlsx di layar ini, dan tidak ada jalur
             kode yang dapat menyusun format lain. -->
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
     tabel dan kartu grafik di dalamnya. Di stasiun ini rekap per unit dan
     rekap harian membawa empat belas kolom rata-rata — terlebar di laporan
     mobile mana pun. */
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

.notice { margin: 0; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; color: #6b7280; font-size: 14px; line-height: 1.5; }
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
.metric-note { font-size: 12px; color: #6b7280; line-height: 1.5; }

/* Penanda 'lab' DI DALAM judul kartu. Netral secara warna: ia menyatakan dari
   mana angkanya datang, BUKAN menilai angkanya — tidak ada satu pun warna
   peringatan di layar ini. */
.metric-pill {
  display: inline-block;
  margin-left: 6px;
  padding: 1px 6px;
  border: 1px solid #e5e7eb;
  border-radius: 999px;
  background: #f9fafb;
  color: #4b5563;
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: lowercase;
}

.detail-section { display: flex; flex-direction: column; gap: 10px; padding: 14px; border: 1px solid #e5e7eb; border-radius: 8px; }
.section-title { margin: 0; font-size: 16px; font-weight: 700; color: #1f2937; }
.section-note { margin: 0; font-size: 12px; color: #6b7280; line-height: 1.5; }

.queue-figures { display: flex; gap: 20px; flex-wrap: wrap; }
.queue-figure { display: flex; flex-direction: column; gap: 2px; min-width: 96px; }
.queue-label { font-size: 11px; color: #6b7280; }
.queue-value { font-size: 20px; font-weight: 700; color: #1f2937; }
.queue-note { font-size: 12px; color: #6b7280; line-height: 1.5; }

/* Tiga angka berdampingan DI DALAM satu kartu metrik — ini bukan tiga kartu
   bersebelahan: terendah/rata-rata/tertinggi adalah satu pengukuran yang sama
   dilihat dari tiga sisi, jadi memisahkannya menjadi tiga kartu akan
   mengundang pembacaan bahwa ketiganya tiga hal berbeda.
   Dipakai .metric-row/.metric-row-item yang TERDEFINISI di sini — bukan
   .metric-values, yang dipakai LaporanDepricarpingView.vue dan tidak pernah
   didefinisikan di satu berkas pun di src/, sehingga pembungkusnya di sana
   tidak tertata. */
.metric-row { display: flex; gap: 16px; flex-wrap: wrap; }
.metric-row-item { display: flex; flex-direction: column; gap: 2px; min-width: 72px; }
.metric-row-item small { font-size: 11px; color: #6b7280; }
.metric-row-item b { font-size: 18px; font-weight: 700; color: #1f2937; }

/* Kedua rekap teks bebas: daftar KEJADIAN, bukan tabel. Teksnya dibungkus
   UTUH — pemotongan akan menghilangkan isi catatan itu sendiri, dan kartunya
   tidak boleh membuat halaman menggulir mendatar. */
.note-list { display: flex; flex-direction: column; gap: 10px; }
.note-item { display: flex; flex-direction: column; gap: 2px; padding: 10px 12px; border: 1px solid #e5e7eb; border-radius: 8px; }
.note-meta { font-size: 11px; font-weight: 600; color: #6b7280; }
.note-text { margin: 0; font-size: 13px; color: #1f2937; line-height: 1.5; white-space: normal; overflow-wrap: anywhere; }

/* Tabel: guliran mendatar DI DALAM kartu, bukan pada halaman. */
/* Bentuk dasar + kartu bertumpuk pada lebar ponsel: src/assets/stacked-table.css
   (global, dipakai bersama seluruh tabel laporan mobile — QA LP-M-02/LP-M-03).
   Aturan `.detail-table` TIDAK ditulis ulang di sini dengan sengaja: style
   scoped mengompilasi selektor dengan [data-v-hash] dan akan selalu menang
   atas stylesheet bersama itu. */
.detail-table tfoot th, .detail-table tfoot td { font-weight: 700; color: #1f2937; border-bottom: none; }
.detail-table tfoot td small { font-weight: 500; color: #6b7280; }

/* Grafik tren: menggulir mendatar DI DALAM kartunya sendiri. Sumbu tegak
   sengaja tidak dimulai dari nol, dan hal itu dinyatakan di kartunya. Tidak
   ada modifier warna peringatan di sini — nol kelas peringatan di seluruh
   layar ini. */
.chart-scroll { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
.chart-bars { display: flex; align-items: flex-end; gap: 6px; min-width: min-content; padding-bottom: 4px; }
.chart-col { display: flex; flex-direction: column; align-items: center; gap: 4px; flex: none; width: 40px; }
.chart-value { font-size: 10px; color: #6b7280; }
.chart-track { display: flex; align-items: flex-end; width: 100%; height: 96px; border-radius: 4px; background: #f3f4f6; overflow: hidden; }
.chart-fill { width: 100%; border-radius: 4px 4px 0 0; background: #249360; }
.chart-axis { font-size: 10px; color: #6b7280; }

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
</style>
