<script setup lang="ts">
/**
 * LaporanPressingView — screen-151--laporan-pressing-mobile /
 * usecase-152--laporan-pressing-mobile "Lihat Laporan Periode Pressing
 * (Mobile)".
 *
 * Kembaran mobile kedelapan, dan BACAAN SAJA: layar ini tidak punya satu pun
 * jalur yang mengubah data stasiun.
 *
 * NOL PERUBAHAN BACKEND. Keempat endpoint /api/pressing-reports/* milik
 * screen-150 dipakai ulang apa adanya — ketiga rute data sudah menerima peran
 * mobile sejak endpoint itu dibangun, karena kedua layar direncanakan dalam
 * satu seri. Pola yang sama sudah terbukti pada pasangan screen-146/147;
 * pasangan Weighbridge (143 lalu 144) harus menambal tiga perubahan akses
 * menyusul justru karena layar web-nya dibangun lebih dulu.
 *
 * ────────────────────────────────────────────────────────────────────────
 * ENAM HAL YANG MENENTUKAN BENTUK LAYAR INI
 * ────────────────────────────────────────────────────────────────────────
 *
 * 1. TABEL TUJUH KOLOM MENJADI KARTU, DAN ITU SATU-SATUNYA PERUBAHAN BENTUK
 *    YANG DISENGAJA. Pada lebar 390px tabel parameter versi web tidak muat
 *    tanpa menggulir mendatar, jadi tiap parameter menjadi kartu bertumpuk.
 *    Yang TIDAK ikut berubah adalah daftar isinya: min, rata-rata, maks,
 *    PENYEBUT, standar operasional, dan rencana tindakan. Memotong salah
 *    satunya demi ruang adalah kehilangan yang paling mudah terjadi dan
 *    paling sulit terlihat.
 *
 * 2. PENYEBUT TIAP RATA-RATA TERCETAK APA ADANYA. `filled_slot_count` adalah
 *    banyaknya slot yang BENAR-BENAR MENCATAT kolom itu. Kelima kolom ukur
 *    nullable dan terisi saling bebas, jadi penyebutnya memang berbeda-beda —
 *    dan tanpa penyebut tercetak, rata-rata sebuah parameter terbaca sebagai
 *    angka seluruh periode.
 *
 * 3. CAKUPAN PENCATATAN TETAP PALING ATAS, berikut ketiga angka pembentuk
 *    penyebutnya. Di layar sempit pembaca melihat lebih sedikit sekaligus,
 *    jadi apa yang dilihat lebih dulu menentukan lebih banyak — urutannya di
 *    sini lebih menentukan, bukan kurang.
 *
 * 4. KEDUA KOLOM TARGET BERADA DI KARTU YANG SAMA dengan angkanya, dan
 *    KEDUANYA — bukan salah satunya. target_operating_range menjawab "ke mana
 *    seharusnya" ('90C - 95C', '35 - 45 Amperes'); critical_trigger_action_limit
 *    menjawab "kapan harus bertindak, dan apa akibatnya kalau tidak"
 *    ('< 85C (Leads to poor oil liberation)'). Di layar sempit godaan
 *    memangkas satu kolom paling besar justru di sini, dan yang dipangkas akan
 *    selalu batas tindakan — padahal keterangan di dalam tanda kurung itulah
 *    satu-satunya tempat akibat penyimpangan tertulis. Karena itu kartu
 *    parameter di sini memuat ENAM baris informasi, satu lebih banyak
 *    daripada kartu Threshing.
 *
 *    DAN TIDAK ADA PENANDAAN DI LUAR BATAS, dengan sebab yang harus lebih
 *    tajam daripada pada Threshing: critical_trigger_action_limit JUSTRU
 *    membawa pembanding yang teratur pada lima dari tujuh parameter
 *    ('< 85C', '> 50 Amps', '> 60 Bar'), jadi penguraiannya secara teknis
 *    mungkin. Yang menahannya: kedua kolom itu teks bebas dan TIDAK ADA APA
 *    PUN PADA SKEMA yang membatasi bentuknya, sehingga himpunan masukan
 *    pengurai tidak tetap pada waktu build — satu seeder yang dijalankan atau
 *    satu suntingan langsung ke basis data dapat memperkenalkan bentuk baru
 *    tanpa satu pun test menangkapnya. Pengurai yang lalu gagal akan BERHENTI
 *    MEMPERINGATKAN tanpa satu pun galat, dan peringatan yang hilang terbaca
 *    sebagai "semuanya aman". Satu nilai pada master pun sudah tidak dapat
 *    diurai tanpa menebak ('< 10% to 12%').
 *    Layar MENYATAKAN hal ini, karena ketiadaan yang tidak dijelaskan terbaca
 *    sebagai fitur yang belum selesai.
 *
 * 5. DUA STANDAR YANG BELUM DIUKUR SISTEM TETAP DITAMPILKAN — dua, bukan satu
 *    seperti pada Threshing. Master memuat TUJUH parameter untuk lima kolom
 *    ukur, dan 'Nut Breakage Rate' serta 'Press Cake Moisture' tidak punya
 *    kolom pengukuran DI MANA PUN pada skema ini. Keduanya pun justru
 *    parameter yang paling menentukan mutu pengepresan. Membuang baris yang
 *    tak terukur di layar sempit akan membuat standar yang tidak pernah
 *    diukur tampak terpenuhi padahal ia sekadar tidak ada.
 *
 * 6. NULL BUKAN NOL, DAN TERLIHAT, lewat kelas .metric-value--na supaya tidak
 *    pernah terbaca sekilas sebagai bilangan. Khususnya persen cakupan: null
 *    berarti belum ada hari yang dapat dijadikan pembagi, dan 0% mengklaim ada
 *    yang diukur dan hasilnya nol.
 *
 * NOL PERHITUNGAN DI KLIEN. Tidak satu pun angka dihitung di sini — min,
 * rata-rata, maks, penyebut, persen cakupan, dan seluruh `averages` dipakai
 * apa adanya dari ringkasan. Laporan web memakai sumber yang sama, jadi
 * perhitungan kedua di sisi klien pasti akan menyimpang tanpa ketahuan.
 *
 * SATU KEJANGGALAN YANG BENAR: coverage.filled_slots bisa LEBIH BESAR
 * daripada penyebut kolom ukur mana pun. Sebuah slot dihitung terisi bila
 * salah satu dari ENAM kolom bacaan terisi, dan kolom keenam adalah alasan
 * downtime — slot yang hanya memuat "Belt kendur" jelas disentuh operator.
 *
 * ALASAN DOWNTIME DIKELOMPOKKAN SERVER SECARA HARFIAH, dan layar menyatakan
 * hal itu: tanpa keterangannya, dua baris berejaan mirip terbaca sebagai
 * cacat laporan alih-alih sebagai bentuk datanya.
 *
 * Kosakata CSS-nya adalah kosakata layar mobile lain
 * (.detail-section/.filter-bar/.action-footer), BUKAN kosakata `md-*` laporan
 * web — itu bahasa grid lebar yang tidak punya arti pada satu kolom selebar
 * 360px.
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
import pressingReportRepo, {
  type PressingReportBusinessUnitOption,
  type PressingReportPeriodOption,
  type PressingReportSummary,
} from '@/services/pressingReportRepo'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const floatingClockStore = useFloatingClockStore()
const aiAssistantStore = useAiAssistantStore()

/* ------------------------------------------------------------------ */
/* Peran & mill                                                        */
/* ------------------------------------------------------------------ */

const role = computed(() => authStore.currentUser?.role ?? null)
const isAdmin = computed(() => role.value === 'admin')

/** Mill akun — hanya bermakna bagi peran yang terikat satu mill. */
const accountBusinessUnitId = computed(() => authStore.currentUser?.business_unit_id ?? null)

/** Mill pilihan Admin. Selalu null bagi peran lain. */
const selectedBusinessUnitId = ref<string | null>(null)

const businessUnits = ref<PressingReportBusinessUnitOption[]>([])

/**
 * Akun terikat mill tetapi business_unit_id-nya kosong — layar berhenti total
 * di sini, tanpa satu pun permintaan HTTP (lihat catatan 5 pada docblock).
 */
const noMillForAccount = ref(false)

/**
 * Endpoint options menjawab 403 — pemilih Mill tidak dirender sama sekali.
 *
 * Keadaan NORMAL bagi Operator, Supervisor, dan Mill Management: endpoint itu
 * adalah satu-satunya rute pada prefix pressing-reports yang tidak terbuka bagi
 * peran terikat mill, dan itu disengaja. Layar ini memang tidak memanggilnya
 * untuk mereka; penanda ini ada untuk Admin yang kewenangannya berubah di
 * tengah sesi.
 */
const millPickerForbidden = ref(false)

/** Admin yang belum memilih mill: tidak ada angka, hanya arahan memilih. */
const millRequired = computed(() => isAdmin.value && !selectedBusinessUnitId.value)

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
 * yang dihapus, atau pengguna yang dipindah mill, kembali memunculkan pemilih.
 *
 * ADMIN: daftar line diambil dari GET /api/production-lines/options-for-report
 * ?business_unit_id=<mill terpilih>, BUKAN dari /api/production-lines/current.
 * Yang terakhir itu swa-cakup — ia memulangkan line milik mill AKUN
 * PEMANGGIL, sedangkan Admin tidak terikat mill, sehingga memanggilnya atas
 * nama Admin akan memulangkan line dari mill yang BUKAN mill terpilih: bentuk
 * kesalahan paling berbahaya di layar laporan, yaitu angka yang terlihat sah
 * untuk line yang salah.
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
 * mematahkan layar.
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
  if (isAdmin.value && !selectedBusinessUnitId.value) {
    // Admin memilih mill lebih dulu. Tanpa mill tidak ada daftar line yang
    // dapat diminta — dan tidak ada permintaan yang dikirim.
    productionLines.value = []
    selectedProductionLineId.value = null
    productionLineFetchFailed.value = false

    return
  }

  loadingProductionLines.value = true
  productionLineFetchFailed.value = false

  try {
    // business_unit_id hanya ikut untuk Admin — peran lain dipaksa ke mill
    // akunnya oleh server, dan repo pun menolak mengirimkannya.
    const lines = await productionLineRepo.fetchProductionLinesForReport(
      isAdmin.value ? selectedBusinessUnitId.value : null,
    )

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

const scope = computed(() => ({
  isAdmin: isAdmin.value,
  businessUnitId: selectedBusinessUnitId.value,
  productionLineId: selectedProductionLineId.value,
}))

/**
 * Nama mill yang sedang berlaku, sebagai keterangan.
 *
 * DIBACA DARI BLOK business_unit, bukan dari period.business_unit_name —
 * payload Pressing (seperti Pressing dan Weighbridge) menaruhnya di sana. Menyalin pola
 * lima laporan pertama menghasilkan keterangan mill yang kosong tanpa satu
 * pun galat.
 */
const currentMillName = computed(() => {
  if (isAdmin.value) {
    return businessUnits.value.find((item) => item.id === selectedBusinessUnitId.value)?.name ?? ''
  }

  return summary.value?.business_unit?.name || authStore.businessUnit?.name || ''
})

/* ------------------------------------------------------------------ */
/* Periode & ringkasan                                                 */
/* ------------------------------------------------------------------ */

const periods = ref<PressingReportPeriodOption[]>([])
const selectedPeriodId = ref<string | null>(null)
const summary = ref<PressingReportSummary | null>(null)

const loadingPeriods = ref(false)
/**
 * Daftar periode sudah pernah selesai dimuat untuk mill ini (audit loading
 * state 2026-10-05). Tanpa ini "Mill ini belum memiliki periode" tampil
 * selama daftar line/periode MASIH dimuat — daftar kosong saat memuat terbaca
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
    !millRequired.value &&
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
    // Sesi berakhir. Diteruskan ke penjagaan sesi; layar ini tidak mengarang
    // pesan jaringan atas sesuatu yang bukan masalah jaringan.
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
    // selectedPeriodId SENGAJA tidak direset: pengguna di area tanpa sinyal
    // tidak boleh dipaksa memilih periodenya dari awal, dan Coba Lagi harus
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

async function loadBusinessUnits(): Promise<void> {
  try {
    businessUnits.value = await pressingReportRepo.fetchBusinessUnits()
  } catch (error) {
    if (statusOf(error) === 403) {
      // Pemilih mill memang bukan untuk peran ini — jangan dirender sama
      // sekali, menawarkan pemilih yang pasti gagal adalah kebohongan.
      millPickerForbidden.value = true
      businessUnits.value = []
      errorMessage.value = 'Pemilih mill tidak tersedia untuk peran Anda.'

      return
    }

    handleError(error, loadBusinessUnits)
  }
}

async function loadPeriods(): Promise<void> {
  loadingPeriods.value = true

  try {
    periods.value = await pressingReportRepo.fetchPeriods(scope.value)
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
  // (retry, ganti periode, ganti mill) yang bisa melewatinya — dan di layar
  // ini melewatinya berarti 422 dari server, bukan angka seluruh mill.
  if (!periodId || !selectedProductionLineId.value) {
    return
  }

  // Hanya respons permintaan TERBARU yang berlaku (audit 2026-10-05): ganti
  // periode/line dengan cepat bisa membuat respons lama tiba belakangan —
  // tanpa penjaga ini ia menimpa ringkasan baru, memunculkan galat basi, dan
  // mematikan indikator muat milik permintaan baru.
  const isLatest = summaryRequests.next()
  loadingSummary.value = true

  try {
    const result = await pressingReportRepo.fetchSummary(periodId, scope.value)
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
 * setiap kali pilihan (periode/line/mill) berubah. Respons yang tiba kemudian
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

async function onBusinessUnitChange(): Promise<void> {
  clearErrors()
  periods.value = []
  periodsLoaded.value = false
  selectedPeriodId.value = null
  resetSummary()
  // Line milik mill LAMA tidak boleh tertinggal: sebuah Production Line milik
  // satu mill, jadi mengganti mill selalu membatalkan pilihan line.
  productionLines.value = []
  selectedProductionLineId.value = null

  if (!selectedBusinessUnitId.value) {
    return
  }

  // Urutan yang sama dengan onMounted bagi peran terikat mill: daftar line
  // lebih dulu, periode menyusul — /periods memang TIDAK tersaring line.
  await loadProductionLines()
  await loadPeriods()
}

onMounted(async () => {
  if (isAdmin.value) {
    // Admin tidak terikat mill: pilih mill dulu, baru daftar line dan periode
    // dimuat — keduanya oleh onBusinessUnitChange().
    await loadBusinessUnits()

    return
  }

  if (!accountBusinessUnitId.value) {
    // Berhenti di sini. TIDAK ada permintaan apa pun — lihat catatan 5.
    noMillForAccount.value = true

    return
  }

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

  return `laporan-pressing_${slug || 'periode'}.csv`
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
    // Isi CSV dibentuk SERVER (satu baris per slot waktu). Layar ini
    // tidak pernah menyusun berkasnya dari angka yang tampil. Nama berkas
    // diambil SAAT ekspor diminta: bila pengguna mengganti periode selama
    // berkas diunduh, isinya tetap milik periode yang diekspor, jadi namanya
    // pun harus milik periode itu (audit 2026-10-05).
    const filename = exportFilename()
    const blob = await pressingReportRepo.exportCsv(periodId, scope.value)
    pressingReportRepo.saveCsvFile(blob, filename)
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
const byPresser = computed(() => summary.value?.by_presser ?? [])
const daily = computed(() => summary.value?.daily ?? [])
const dailyTotal = computed(() => summary.value?.daily_total ?? null)
const downtimeReasons = computed(() => summary.value?.downtime_reasons ?? [])
const totals = computed(() => summary.value?.total ?? null)

const hasReport = computed(
  () =>
    Boolean(summary.value) &&
    !networkError.value &&
    !errorMessage.value &&
    !millRequired.value &&
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
 * blok CAKUPAN tetap digambar, karena justru itu yang menjelaskan
 * kekosongannya.
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

function formatPercent(value: number | null | undefined): string {
  if (value === null || value === undefined) {
    return NOT_AVAILABLE
  }

  return `${new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value)}%`
}

/** Persen kelengkapan, satu desimal — '—' bila penyebutnya 0. */
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
 * Penamaan presser yang kosong. presser_id adalah kolom NOT NULL, jadi
 * bentuk ini praktis tidak muncul — tetapi barisnya tetap diberi label alih-
 * alih dirender kosong, karena sel tanpa label membuat seluruh baris terbaca
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

function periodOptionLabel(period: PressingReportPeriodOption): string {
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
  <main class="laporan-pr-view" data-testid="laporan-pressing-mobile">
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
        <span aria-current="page">Laporan Pressing</span>
      </nav>
      <h1 class="screen-title">Laporan Pressing</h1>
    </div>

    <!-- Akun terikat mill tetapi mill-nya kosong: berhenti total, tanpa satu
         pun permintaan HTTP dan tanpa pemilih mill sebagai pengganti. -->
    <p v-if="noMillForAccount" class="notice notice--warning" role="alert" data-testid="no-mill-for-account">
      Akun Anda belum terhubung ke mill mana pun, sehingga laporan tidak dapat ditampilkan.
      Silakan hubungi Admin untuk menghubungkan akun Anda ke sebuah mill.
    </p>

    <template v-else>
      <FilterPanel aria-label="Filter laporan">
        <!-- Pemilih Mill — hanya Admin, dan tidak dirender sama sekali bila
             server menolak endpoint options dengan 403. -->
        <FilterSelectField v-if="isAdmin && !millPickerForbidden" label="Mill" icon="mill">
          <select v-model="selectedBusinessUnitId" data-testid="mill-select" @change="onBusinessUnitChange">
            <option :value="null">Pilih Mill</option>
            <option v-for="unit in businessUnits" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
          </select>
        </FilterSelectField>

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

        <!-- Pemilih Periode selalu dirender: bagi Admin yang belum memilih
             mill ia tampil KOSONG, bukan hilang. -->
        <FilterSelectField label="Periode Pelaporan" icon="period">
          <select v-model="selectedPeriodId" data-testid="period-select" @change="onPeriodChange">
            <option :value="null">Pilih Periode</option>
            <option v-for="period in periods" :key="period.id" :value="period.id">
              {{ periodOptionLabel(period) }}
            </option>
          </select>
        </FilterSelectField>

        <template #chips>
          <FilterChip v-if="!isAdmin" label="Mill" :value="currentMillName || '-'" data-testid="mill-current" />
          <FilterChip
            v-if="activeProductionLineName"
            label="Production Line"
            :value="activeProductionLineName"
            data-testid="production-line-current"
          />
        </template>
      </FilterPanel>

      <p v-if="millRequired" class="notice" data-testid="mill-required-hint">
        Pilih mill terlebih dahulu untuk menampilkan laporan.
      </p>

      <p v-else-if="productionLineRequired" class="notice" data-testid="production-line-required-hint">
        Pilih Production Line terlebih dahulu untuk menampilkan laporan. Angka laporan dihitung per
        Production Line, sehingga tidak ada pilihan gabungan lintas line.
      </p>

      <!-- Pesannya membedakan "mill belum punya line" dari "daftarnya gagal
           dimuat": dua keadaan yang menuntut tindakan berbeda. -->
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
        Mill ini belum memiliki periode pelaporan yang mencakup stasiun Pressing.
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
          Belum ada slot Pressing yang terisi pada periode ini untuk Production Line yang dipilih.
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
              <!-- Persen TANPA penyebut dirender '—', bukan 0,0%: 0%
                   mengklaim ada yang diukur dan hasilnya nol. -->
              <span
                class="metric-note"
                :class="{ 'metric-value--na': (coverage?.coverage_percent ?? null) === null }"
                data-testid="coverage-percent"
              >
                {{ formatCompletenessPercent(coverage?.coverage_percent ?? null) }}
              </span>
              <span class="metric-note">
                Slot dihitung terisi bila minimal satu dari enam kolom bacaan diisi — termasuk bila
                yang diisi hanya alasan downtime.
              </span>
            </div>

            <div class="metric-card">
              <span class="metric-label">Pembentuk Penyebut</span>
              <span class="metric-value" data-testid="coverage-denominator">
                {{ formatCount(coverage?.presser_count) }} presser ×
                {{ formatCount(coverage?.days_counted) }} hari ×
                {{ formatCount(coverage?.slots_per_presser_per_day) }} slot
              </span>
              <span class="metric-note">
                Jumlah presser adalah yang BENAR-BENAR beroperasi pada periode ini, bukan jumlah
                stasiun terdaftar; 24 slot kanonis diambil dari grid layar input itu sendiri.
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

              <!-- PENYEBUT, dicetak apa adanya. Pada layar sempit kolom
                   inilah yang paling mudah dikorbankan demi ruang, dan
                   tanpanya rata-rata terbaca sebagai angka seluruh periode. -->
              <span class="metric-note" data-testid="metric-denominator">
                {{ formatCount(metric.filled_slot_count) }} dari
                {{ formatCount(coverage?.filled_slots) }} slot mencatat kolom ini
              </span>

              <!-- KEDUA kolom target, pada kartu yang SAMA dengan angkanya.
                   Di layar sempit godaan memangkas salah satunya paling
                   besar, dan yang dipangkas akan selalu "batas tindakan" —
                   padahal itu satu-satunya tempat akibat penyimpangan
                   tertulis. Keduanya dirender verbatim, tanda kurung dan
                   semuanya. -->
              <span class="metric-standard" data-testid="metric-target-range">
                <small>Rentang kerja</small>
                <span :class="{ 'metric-value--na': metric.target.target_operating_range === null }">
                  {{ metric.target.target_operating_range ?? 'rentang kerja belum terisi pada master' }}
                </span>
              </span>
              <span class="metric-standard" data-testid="metric-action-limit">
                <small>Batas tindakan</small>
                <span :class="{ 'metric-value--na': metric.target.critical_trigger_action_limit === null }">
                  {{ metric.target.critical_trigger_action_limit ?? 'belum terisi' }}
                </span>
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
            <strong>Rentang kerja dan batas tindakan ditampilkan, tetapi laporan ini tidak menilai satu
            angka pun.</strong> Tidak ada warna peringatan, tidak ada severity &mdash; penilaiannya milik
            Anda, bukan milik layar ini. Keduanya menjawab pertanyaan yang berbeda:
            <strong>rentang kerja</strong> adalah ke mana angkanya seharusnya,
            <strong>batas tindakan</strong> adalah kapan seseorang harus bertindak dan apa akibatnya bila
            tidak &mdash; keterangan di dalam tanda kurung itu bagian dari isinya, bukan hiasan.
          </p>
          <p class="section-note">
            <strong>Mengapa tidak ditandai otomatis padahal batas tindakannya terlihat berupa angka.</strong>
            Keduanya <strong>teks bebas</strong>, dan tidak ada apa pun pada skema yang membatasi
            bentuknya &mdash; nilainya hari ini ditetapkan lewat seeder, dan satu seeder yang
            dijalankan atau satu suntingan langsung ke basis data dapat memperkenalkan bentuk baru
            tanpa satu pun uji menangkapnya. Pengurai yang lalu gagal akan <strong>berhenti
            memperingatkan tanpa satu pun galat</strong> &mdash; dan peringatan yang hilang terbaca
            sebagai &ldquo;semuanya aman&rdquo;. Satu nilai pada master pun sudah tidak dapat diurai
            tanpa menebak hari ini (&ldquo;&lt; 10% to 12%&rdquo;). Dan <strong>warna membawa makna
            melampaui statistik</strong>: angka merah pada laporan periode terbaca sebagai
            pelanggaran yang tidak pernah ditetapkan siapa pun.
          </p>
          <p class="section-note">
            <strong>Kedua kolom target berlaku umum untuk seluruh mill</strong> &mdash; master target
            Pressing tidak dipecah per mill. <strong>Penyebut</strong> tiap kartu adalah jumlah slot yang
            benar-benar mencatat kolom itu, dan sengaja dapat berbeda antar kartu: kelima kolom nullable
            dan terisi saling bebas, jadi satu penyebut bersama akan salah untuk setidaknya empat di
            antaranya.
          </p>
        </section>

        <!-- ===== Standar yang belum diukur sistem =====
             Dipublikasikan, bukan dibuang: standar yang tidak pernah diukur
             terbaca seperti terpenuhi padahal ia sekadar tidak ada. -->
        <section v-if="targetsMasterEmpty" class="detail-section" data-testid="targets-master-empty">
          <h2 class="section-title">Master Target Belum Terisi</h2>
          <p class="section-note">
            Master target operasional Pressing belum terisi, jadi kolom rentang kerja dan batas
            tindakan pada kartu di atas kosong. Seluruh angka hasil ukur tetap ditampilkan apa adanya
            &mdash; master yang belum diisi tidak menghapus pengukuran yang sudah terjadi.
          </p>
        </section>

        <section
          v-else-if="targetsWithoutMetric.length > 0"
          class="detail-section"
          data-testid="targets-without-metric"
        >
          <h2 class="section-title">Standar yang Belum Diukur Sistem</h2>
          <p class="section-note">
            Ada standarnya, tidak ada kolom pengukurannya. Ditampilkan sebagai temuan, bukan
            disembunyikan.
          </p>

          <div class="metric-stack">
            <article
              v-for="target in targetsWithoutMetric"
              :key="target.parameter_metric"
              class="metric-card"
              data-testid="targets-without-metric-row"
            >
              <span class="metric-label">{{ target.parameter_metric }}</span>
              <span class="metric-standard">
                <small>Rentang kerja</small>
                <span>{{ target.target_operating_range }}</span>
              </span>
              <span class="metric-standard">
                <small>Batas tindakan</small>
                <span>{{ target.critical_trigger_action_limit }}</span>
              </span>
            </article>
          </div>

          <p class="section-note" data-testid="targets-without-metric-note">
            Parameter di atas punya rentang kerja dan batas tindakan pada master target, tetapi
            <strong>tidak punya kolom pengukuran di mana pun pada sistem ini</strong> &mdash; bukan hanya
            tidak ada di formulir Pressing. Keduanya pun justru <strong>parameter yang paling menentukan
            mutu pengepresan</strong>. Ditampilkan justru karena itu: <strong>standar yang tidak pernah
            diukur terbaca seperti terpenuhi</strong>, padahal ia sekadar tidak ada.
          </p>
        </section>

        <template v-if="showRecaps">
          <!-- ===== Rekap per presser ===== -->
          <section class="detail-section" data-testid="by-presser">
            <h2 class="section-title">Rekap per Presser</h2>
            <p class="section-note">
              presser_id adalah NAMA UNIT, bukan kunci baris — id yang sama pada dua tanggal adalah
              satu presser dengan dua hari pencatatan.
            </p>
            <div class="detail-table-wrap">
              <table class="detail-table" data-testid="by-presser-table">
                <thead>
                  <tr>
                    <th scope="col">Presser</th>
                    <th scope="col" class="num">Hari</th>
                    <th scope="col" class="num">Slot</th>
                    <th v-for="metric in metrics" :key="metric.column" scope="col" class="num">
                      {{ metric.label }}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="row in byPresser"
                    :key="row.presser_id"
                    data-testid="by-presser-row"
                  >
                    <td>{{ formatGroupLabel(row.presser_id) }}</td>
                    <td class="num">{{ formatCount(row.day_count) }}</td>
                    <td class="num">{{ formatCount(row.filled_slot_count) }}</td>
                    <td v-for="metric in metrics" :key="metric.column" class="num">
                      {{ formatCell(row.averages?.[metric.column] ?? null) }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

          <!-- ===== Alasan downtime ===== -->
          <section class="detail-section" data-testid="downtime">
            <h2 class="section-title">Alasan Downtime</h2>
            <p class="section-note">
              Diurutkan dari yang terbanyak. Dikelompokkan <strong>harfiah</strong>, tanpa penyeragaman
              ejaan maupun huruf besar-kecil.
            </p>

            <p v-if="downtimeReasons.length === 0" class="section-note" data-testid="downtime-empty">
              Tidak ada satu pun alasan downtime tercatat pada periode dan line ini. Ini dinyatakan
              sebagai keterangan, bukan tabel kosong tanpa penjelasan — tabel kosong tidak membedakan
              &ldquo;tidak ada downtime&rdquo; dari &ldquo;tidak ada yang mencatatnya&rdquo;.
            </p>

            <template v-else>
              <div class="detail-table-wrap">
                <table class="detail-table" data-testid="downtime-table">
                  <thead>
                    <tr>
                      <th scope="col">Alasan (apa adanya)</th>
                      <th scope="col" class="num">Slot</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="row in downtimeReasons" :key="row.reason" data-testid="downtime-row">
                      <td>{{ row.reason }}</td>
                      <td class="num">{{ formatCount(row.slot_count) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <p class="section-note" data-testid="downtime-note">
                Alasan downtime adalah <strong>teks bebas</strong>, dan dikelompokkan
                <strong>harfiah</strong>. Dua ejaan untuk sebab yang sama karena itu muncul sebagai
                <strong>dua baris</strong> — itu bentuk datanya, bukan cacat laporan ini.
                Menyeragamkannya justru akan menggabungkan sebab yang penulisnya memang maksudkan
                berbeda. Slot yang mencatat alasan downtime <strong>tetap ikut</strong> pada min,
                rata-rata dan maks bila kolom ukurnya juga terisi — downtime adalah keterangan
                tambahan, bukan penyaring.
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
                <span class="queue-note">satu record = satu hari kerja satu presser</span>
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
               memicu permintaan apa pun — datanya sudah ada di memori. -->
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
                    <th v-for="metric in metrics" :key="metric.column" scope="col" class="num">
                      {{ metric.label }}
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="row in daily" :key="row.date" data-testid="daily-row">
                    <td>{{ formatDate(row.date) }}</td>
                    <td class="num">{{ formatCount(row.filled_slot_count) }}</td>
                    <td v-for="metric in metrics" :key="metric.column" class="num">
                      {{ formatCell(row.averages?.[metric.column] ?? null) }}
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <!-- TOTAL PERIODE DIHITUNG ULANG SERVER atas seluruh slot,
                       bukan merata-ratakan rata-rata harian: rata-rata dari
                       rata-rata memberi bobot sama pada hari yang jumlah
                       slotnya berbeda. -->
                  <tr data-testid="daily-recap-total">
                    <td>Total periode</td>
                    <td class="num">{{ formatCount(dailyTotal?.filled_slot_count) }}</td>
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
             periode mengatur penulisan data, bukan pembacaan laporan. -->
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
.metric-figure { display: flex; align-items: baseline; gap: 6px; flex-wrap: wrap; }
.metric-value { font-size: 26px; font-weight: 700; color: #1f2937; }
/* Nilai yang tidak dapat dihitung tampil sebagai keterangan, bukan angka
   besar — supaya tidak pernah terbaca sekilas sebagai bilangan. */
.metric-value--na { font-size: 15px; font-weight: 600; color: #6b7280; }
.metric-unit { font-size: 13px; color: #6b7280; }
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
   mengundang pembacaan bahwa ketiganya tiga hal berbeda. */
.metric-row { display: flex; gap: 16px; flex-wrap: wrap; }
.metric-row-item { display: flex; flex-direction: column; gap: 2px; min-width: 72px; }
.metric-row-item small { font-size: 11px; color: #6b7280; }
.metric-row-item b { font-size: 18px; font-weight: 700; color: #1f2937; }

/* Standar operasional dan rencana tindakannya, DI DALAM kartu angkanya. */
.metric-standard { display: flex; flex-direction: column; gap: 2px; font-size: 13px; color: #1f2937; }
.metric-standard small { font-size: 11px; color: #6b7280; }
</style>
