<script setup lang="ts">
/**
 * LaporanSterilizerView — screen-135--laporan-sterilizer-mobile /
 * usecase-135--laporan-sterilizer-mobile "Lihat Laporan Periode Sterilizer
 * (Mobile)" (dipasang di /reports/sterilizer, meta.public = false).
 * Actors: operator, supervisor, mill_management, admin.
 *
 * Ujung dari rantai Full Cycle stasiun Sterilizer: tempat data yang
 * diinput Operator akhirnya terbaca sebagai angka. Operator sengaja
 * diberi akses — "orang yang menginput data berhak melihat hasilnya"
 * (business_rules).
 *
 * TIGA HAL YANG MENENTUKAN BENTUK BERKAS INI
 *
 * 1. NOL PERHITUNGAN ULANG DI KLIEN. Setiap angka yang tampil dipetakan
 *    apa adanya dari respons /api/sterilizer-reports/summary — endpoint
 *    yang sama persis dengan laporan versi web (screen-129), sehingga
 *    kedua layar mustahil berbeda. Tidak ada satu pun penjumlahan,
 *    perata-rataan, atau pembagian di berkas ini. Satu-satunya angka
 *    turunan adalah LEBAR BATANG tren harian (persentase terhadap nilai
 *    harian tertinggi) — itu murni skala visual, bukan angka yang dibaca
 *    pengguna; angka yang dibaca tetap `row.cycles` apa adanya.
 *
 * 2. GAGAL TERTUTUP PADA MILL. Akun terikat mill yang business_unit_id-nya
 *    kosong TIDAK memicu satu pun permintaan HTTP — termasuk tidak
 *    memanggil endpoint options. Memanggil options "sekadar supaya layar
 *    ada isinya" justru akan membentuk daftar SELURUH mill di perangkat
 *    orang yang tidak berhak melihat satu pun di antaranya. Yang benar
 *    adalah diam dan menyuruh menghubungi Admin.
 *
 * 3. 401 BUKAN KEGAGALAN JARINGAN. Sesi yang berakhir diarahkan ke Login,
 *    bukan ditampilkan sebagai "periksa koneksi Anda" dengan tombol Coba
 *    Lagi yang akan gagal selamanya. Sebaliknya, kegagalan jaringan yang
 *    sungguhan TIDAK PERNAH membuang periode yang sudah dipilih — pengguna
 *    di area tanpa sinyal tidak boleh dipaksa mengulang dari awal.
 *
 * Kosakata visual mengikuti layar mobile lain (DataPreviewSterilizerView
 * .detail-section/.filter-bar/.action-footer, StationListView header +
 * breadcrumb), BUKAN kosakata `md-*` laporan web — itu bahasa grid lebar
 * yang tidak punya arti pada satu kolom selebar 360px.
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
import sterilizerReportRepo, {
  type SterilizerReportBusinessUnitOption,
  type SterilizerReportPeriodOption,
  type SterilizerReportSummary,
} from '@/services/sterilizerReportRepo'

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

const businessUnits = ref<SterilizerReportBusinessUnitOption[]>([])

/**
 * Akun terikat mill tetapi business_unit_id-nya kosong — layar berhenti
 * total di sini, tanpa satu pun permintaan HTTP (lihat catatan 2 pada
 * docblock).
 */
const noMillForAccount = ref(false)

/** Endpoint options menjawab 403 — pemilih Mill tidak dirender sama sekali. */
const millPickerForbidden = ref(false)

/** Admin yang belum memilih mill: tidak ada angka, hanya arahan memilih. */
const millRequired = computed(() => isAdmin.value && !selectedBusinessUnitId.value)

/**
 * Cakupan yang dikirim ke repo. business_unit_id hanya ikut untuk Admin —
 * repo pun menolak mengirimkannya untuk peran lain (gagal tertutup di dua
 * tempat, sengaja).
 */
/* ------------------------------------------------------------------ */
/* Production Line — KONTEKS YANG DIPILIH, BUKAN IKATAN AKUN           */
/* ------------------------------------------------------------------ */

/**
 * MEMILIH LINE ITU WAJIB DI LAYAR INI, DAN TIDAK ADA OPSI "SEMUA LINE".
 *
 * Laporan memulangkan angka GABUNGAN satu periode. Menjumlahkan beberapa
 * Production Line ke dalam satu angka menghasilkan bilangan yang tidak
 * dapat ditindaklanjuti siapa pun: tidak ada satu orang pun yang
 * bertanggung jawab atasnya. Karena itu tidak ada angka di layar ini
 * sebelum ada satu line yang berlaku — bukan sekadar pilihan bawaan yang
 * "kebetulan" line pertama.
 *
 * (Data Browser versi web sengaja BERBEDA: di sana barisnya tetap terpisah
 * per record, jadi "Semua Line" di sana tetap dapat dibaca. Perbedaan itu
 * disengaja, bukan ketidakkonsistenan.)
 *
 * ────────────────────────────────────────────────────────────────────────
 * MEMAKAI ULANG INGATAN LINE MILIK LAYAR DAFTAR STASIUN
 * ────────────────────────────────────────────────────────────────────────
 * Kunci localStorage-nya SAMA PERSIS dengan yang ditulis StationListView.vue
 * (`msl_production_line_{userId}`) dan yang sudah dibaca
 * ReportingPilihStasiunView.vue. Alasannya: line adalah konteks kerja satu
 * orang pada satu shift, bukan pengaturan per layar. Pengguna yang sudah
 * memilih Line 2 di Daftar Stasiun tidak boleh diminta memilih lagi begitu
 * ia membuka laporan.
 *
 * Tetapi ingatan itu TIDAK CUKUP dijadikan satu-satunya sumber: laporan
 * dicapai lewat cabang navigasi yang lain (Home → Dashboard & Reporting →
 * Reporting → tile stasiun), sedangkan ingatannya ditulis di cabang
 * Home → Daftar Stasiun. Pengguna yang hanya memakai Reporting bisa saja
 * belum pernah punya ingatan itu sama sekali. Karena itu layar ini punya
 * PEMILIHNYA SENDIRI, dan ingatan hanyalah nilai awalnya.
 *
 * Urutan penentuan line yang berlaku (resolveProductionLine):
 *   1. `?production_line_id=` pada rute — dibawa screen-141 saat menekan
 *      tile stasiun, sejajar dengan `report_path` milik screen-140 web.
 *   2. Ingatan localStorage — pilihan terakhir pengguna ini di perangkat ini.
 *   3. Tepat satu line di mill ini — tidak ada yang perlu dipilih.
 *   4. Selain itu: pemilih ditampilkan, dan TIDAK ADA ANGKA.
 *
 * Nilai dari (1) dan (2) TIDAK PERNAH dipercaya begitu saja — keduanya
 * hanya dipakai bila masih ada di daftar line yang baru diambil dari
 * server, persis kehati-hatian yang sama yang dipakai StationListView.vue.
 * Line yang dihapus, atau pengguna yang dipindah mill, kembali memunculkan
 * pemilih seperti seharusnya.
 *
 * ADMIN: daftar line diambil dari GET /api/production-lines/options-for-report
 * ?business_unit_id=<mill terpilih>, BUKAN dari /api/production-lines/current.
 * Yang terakhir itu swa-cakup — ia memulangkan line milik mill AKUN
 * PEMANGGIL, sedangkan Admin tidak terikat mill sama sekali, sehingga
 * memanggilnya atas nama Admin akan memulangkan line dari mill yang BUKAN
 * mill terpilih: bentuk kesalahan paling berbahaya di layar laporan, yaitu
 * angka yang terlihat sah untuk line yang salah.
 *
 * Endpoint baru itu menerima business_unit_id, tetapi HANYA dari Admin:
 * server membuang nilai kiriman klien untuk peran yang terikat mill
 * (ScopesToActorMill::resolveReadMillId()), dan repo di sini pun tidak
 * mengirimkannya untuk mereka. Karena itu urutan bagi Admin adalah pilih
 * mill dahulu, baru daftar line-nya diminta — tanpa mill tidak ada satu pun
 * permintaan daftar line, dan tanpa line tidak ada satu pun angka.
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
 * Kunci ingatan dimuati id PENGGUNA: sebuah Production Line milik satu
 * mill, jadi pilihan pengguna lain tidak boleh terbawa setelah ganti akun
 * di perangkat yang sama. Sama persis dengan StationListView.vue.
 *
 * localStorage dibungkus try/catch mengikuti pola floatingClock.ts: pada
 * mode privat penyimpanan bisa melempar, dan gagal mengingat pilihan tidak
 * boleh mematahkan layar.
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

/** Nama mill yang sedang berlaku, sebagai keterangan. */
const currentMillName = computed(() => {
  if (isAdmin.value) {
    return businessUnits.value.find((item) => item.id === selectedBusinessUnitId.value)?.name ?? ''
  }

  return summary.value?.period?.business_unit_name || authStore.businessUnit?.name || ''
})

/* ------------------------------------------------------------------ */
/* Periode & ringkasan                                                 */
/* ------------------------------------------------------------------ */

const periods = ref<SterilizerReportPeriodOption[]>([])
const selectedPeriodId = ref<string | null>(null)
const summary = ref<SterilizerReportSummary | null>(null)

const loadingPeriods = ref(false)
/**
 * Daftar periode sudah pernah selesai dimuat untuk mill ini (audit loading
 * state 2026-10-05). Tanpa ini "Mill ini belum memiliki periode" tampil
 * selama daftar line/periode MASIH dimuat — daftar kosong saat memuat
 * terbaca sebagai "tidak ada periode".
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
 * BENTUK GALAT DATAR — diperbaiki 2026-09-28.
 *
 * apiClient.normalizeError (src/services/apiClient.ts) menolak dengan objek
 * DATAR `{ message, errors?, status? }` dan sudah MEMBUANG `response` di
 * sana. Berkas ini sebelumnya tetap membaca `candidate?.response?.status`
 * "untuk berjaga-jaga" — cabang yang tidak pernah dapat dieksekusi di
 * produksi, tetapi mengajarkan pembaca berikutnya sebuah bentuk galat yang
 * tidak ada, dan mengundang penanganan galat yang salah di layar baru.
 * `response` karena itu dihapus dari antarmuka ini, bukan sekadar tidak
 * dipakai: yang tidak dapat ditulis tidak dapat salah.
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
 * Penanganan galat terpusat. Urutan cabangnya menentukan benar/salahnya
 * layar ini:
 *   401 -> Login (bukan pesan jaringan, bukan tombol Coba Lagi)
 *   tanpa status -> penolakan tanpa respons = jaringan; periode terpilih
 *                   SENGAJA tidak disentuh
 *   sisanya -> pesan yang dapat dibaca pengguna
 */
function handleError(error: unknown, retry: (() => Promise<void>) | null): void {
  const status = statusOf(error)

  if (status === 401) {
    // Sesi berakhir. Diteruskan ke penjagaan sesi; layar ini tidak
    // mengarang pesan jaringan atas sesuatu yang bukan masalah jaringan.
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
    // selectedPeriodId SENGAJA tidak direset di sini.
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
    businessUnits.value = await sterilizerReportRepo.fetchBusinessUnits()
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
    periods.value = await sterilizerReportRepo.fetchPeriods(scope.value)
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

  // Tidak ada satu angka pun sebelum ada line yang berlaku. Penjagaan
  // ini ada DI SINI, bukan hanya di template, supaya tidak ada jalur
  // pemanggilan (retry, ganti periode, ganti mill) yang bisa melewatinya.
  if (!periodId || !selectedProductionLineId.value) {
    return
  }

  // Hanya respons permintaan TERBARU yang berlaku (audit 2026-10-05):
  // ganti periode/line dengan cepat bisa membuat respons lama tiba
  // belakangan — tanpa penjaga ini ia menimpa ringkasan baru, memunculkan
  // galat basi, dan mematikan indikator muat milik permintaan baru.
  const isLatest = summaryRequests.next()
  loadingSummary.value = true

  try {
    const result = await sterilizerReportRepo.fetchSummary(periodId, scope.value)
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
 * setiap kali pilihan (periode/line/mill) berubah, sebelum loadSummary()
 * berikutnya (bila ada). Respons yang tiba kemudian diabaikan.
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
  // Line milik mill LAMA tidak boleh tertinggal: sebuah Production Line
  // milik satu mill, jadi mengganti mill selalu membatalkan pilihan line.
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
    // Admin tidak terikat mill: pilih mill dulu, baru daftar line dan
    // periode dimuat — keduanya oleh onBusinessUnitChange().
    await loadBusinessUnits()

    return
  }

  if (!accountBusinessUnitId.value) {
    // Berhenti di sini. TIDAK ada permintaan apa pun — lihat catatan 2.
    noMillForAccount.value = true

    return
  }

  // Daftar line lebih dulu: pemilih periode boleh terisi tanpa line,
  // tetapi angkanya tidak — dan /periods memang TIDAK tersaring line.
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

  return `laporan-sterilizer_${slug || 'periode'}.csv`
}

async function onExport(): Promise<void> {
  const periodId = selectedPeriodId.value

  // Ekspor mengikuti cakupan yang sama dengan angka di layar: tanpa
  // line, tidak ada berkas — berkas yang mencampur line adalah bentuk
  // kesalahan yang paling sulit dibantah setelah terkirim.
  if (!periodId || !selectedProductionLineId.value || exporting.value) {
    return
  }

  exporting.value = true

  try {
    // Nama berkas diambil SAAT ekspor diminta: bila pengguna mengganti
    // periode selama berkas diunduh, isinya tetap milik periode yang
    // diekspor, jadi namanya pun harus milik periode itu (audit 2026-10-05).
    const filename = exportFilename()
    const blob = await sterilizerReportRepo.exportCsv(periodId, scope.value)
    sterilizerReportRepo.saveCsvFile(blob, filename)
  } catch (error) {
    handleError(error, null)
  } finally {
    exporting.value = false
  }
}

/* ------------------------------------------------------------------ */
/* Turunan tampilan (tanpa perhitungan angka laporan)                  */
/* ------------------------------------------------------------------ */

const kpi = computed(() => summary.value?.kpi ?? null)
const daily = computed(() => summary.value?.daily ?? [])
const byUnit = computed(() => summary.value?.by_unit ?? [])
const outliers = computed(() => summary.value?.outliers ?? null)
const total = computed(() => summary.value?.total ?? null)

/** Periode tanpa satu pun siklus — grafik TIDAK dirender sebagai kotak kosong. */
const emptyPeriod = computed(() => Boolean(summary.value) && (kpi.value?.total_cycles ?? 0) === 0)

const hasReport = computed(() => Boolean(summary.value) && !networkError.value && !errorMessage.value)
const showCharts = computed(() => hasReport.value && !emptyPeriod.value && daily.value.length > 0)

const insufficientData = computed(() => Boolean(outliers.value?.insufficient_data))
const noOutliers = computed(
  () => Boolean(outliers.value) && !insufficientData.value && (outliers.value?.items.length ?? 0) === 0,
)
/** Ambang adalah informasi, bukan sekadar penanda: tetap tampil walau nihil pencilan. */
const showThreshold = computed(() => Boolean(outliers.value) && !insufficientData.value)

/**
 * Nilai harian tertinggi — HANYA untuk lebar batang tren (skala visual).
 * Tidak pernah ditampilkan sebagai angka kepada pengguna.
 */
const maxDailyCycles = computed(() =>
  daily.value.reduce((max, row) => (row.cycles > max ? row.cycles : max), 0),
)

function trendBarWidth(cycles: number): string {
  if (maxDailyCycles.value <= 0) {
    return '0%'
  }

  return `${Math.round((cycles / maxDailyCycles.value) * 100)}%`
}

/* ------------------------------------------------------------------ */
/* Format Indonesia                                                    */
/* ------------------------------------------------------------------ */

const MONTHS_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des']

/** Titik ribuan, tanpa desimal. */
function formatCount(value: number | null | undefined): string {
  if (value === null || value === undefined) {
    return '-'
  }

  return new Intl.NumberFormat('id-ID').format(value)
}

/**
 * Durasi/menit. null WAJIB menjadi "tidak tersedia", TIDAK PERNAH 0 —
 * menampilkan 0 akan terbaca sebagai fakta yang salah (siklus nol menit),
 * padahal yang benar adalah durasinya tidak tercatat.
 */
function formatMinutes(value: number | null | undefined): string {
  if (value === null || value === undefined) {
    return 'tidak tersedia'
  }

  const digits = Number.isInteger(value) ? 0 : 1

  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
  }).format(value)
}

function formatPercent(value: number | null | undefined): string {
  if (value === null || value === undefined) {
    return 'tidak tersedia'
  }

  const digits = Number.isInteger(value) ? 0 : 1

  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
  }).format(value)
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

function periodOptionLabel(period: SterilizerReportPeriodOption): string {
  return period.station_type_label ? `${period.station_type_label} — ${period.name}` : period.name
}

/** Status periode adalah KETERANGAN, bukan gerbang: ekspor tetap aktif. */
const periodStatus = computed(() => summary.value?.period?.status ?? selectedPeriod.value?.status ?? null)

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
  <main class="laporan-ster-view" data-testid="laporan-sterilizer-mobile">
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
        <span aria-current="page">Laporan Sterilizer</span>
      </nav>
      <h1 class="screen-title">Laporan Sterilizer</h1>
    </div>

    <!-- Akun terikat mill tetapi mill-nya kosong: berhenti total, tanpa
         satu pun permintaan HTTP dan tanpa pemilih mill. -->
    <p v-if="noMillForAccount" class="notice notice--warning" role="alert" data-testid="no-mill-for-account">
      Akun Anda belum terhubung ke mill mana pun, sehingga laporan tidak dapat ditampilkan.
      Silakan hubungi Admin untuk menghubungkan akun Anda ke sebuah mill.
    </p>

    <template v-else>
      <FilterPanel aria-label="Filter laporan">
        <!-- Pemilih Mill — hanya Admin, dan tidak dirender sama sekali
             bila server menolak endpoint options dengan 403. -->
        <FilterSelectField v-if="isAdmin && !millPickerForbidden" label="Mill" icon="mill">
          <select v-model="selectedBusinessUnitId" data-testid="mill-select" @change="onBusinessUnitChange">
            <option :value="null">Pilih Mill</option>
            <option v-for="unit in businessUnits" :key="unit.id" :value="unit.id">{{ unit.name }}</option>
          </select>
        </FilterSelectField>

        <!-- Pemilih Production Line — WAJIB, dan SENGAJA tanpa opsi
             "semua line". Hanya dirender ketika mill ini memang punya
             lebih dari satu line: dengan satu line tidak ada keputusan
             yang perlu diminta. -->
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

        <!-- Pemilih Periode selalu dirender (bukan v-if millRequired):
             bagi Admin yang belum memilih mill ia tampil KOSONG, bukan
             hilang — pemilih yang lenyap dan pemilih yang kosong
             menceritakan hal berbeda kepada pengguna. -->
        <FilterSelectField label="Periode Pelaporan" icon="period">
          <select v-model="selectedPeriodId" data-testid="period-select" @change="onPeriodChange">
            <option :value="null">Pilih Periode</option>
            <option v-for="period in periods" :key="period.id" :value="period.id">
              {{ periodOptionLabel(period) }}
            </option>
          </select>
        </FilterSelectField>

        <template #chips>
          <!-- Keterangan mill bagi pengguna yang terikat satu mill (bukan
               Admin — Admin memilih mill lewat pemilih di atas). -->
          <FilterChip v-if="!isAdmin" label="Mill" :value="currentMillName || '-'" data-testid="mill-current" />
          <!-- Nama line yang menyertai angka yang sedang tampil — dirender
               di kedua cabang (satu line maupun banyak), supaya tidak pernah
               ada angka di layar ini yang tidak dapat ditelusuri ke satu
               line tertentu. -->
          <FilterChip
            v-if="activeProductionLineName"
            label="Production Line"
            :value="activeProductionLineName"
            data-testid="production-line-current"
          />
        </template>
      </FilterPanel>

      <!-- Admin belum memilih mill: tidak ada angka, tidak ada permintaan. -->
      <p v-if="millRequired" class="notice" data-testid="mill-required-hint">
        Pilih mill terlebih dahulu untuk menampilkan laporan.
      </p>

      <!-- Line belum dipilih: tidak ada angka sama sekali. -->
      <p v-else-if="productionLineRequired" class="notice" data-testid="production-line-required-hint">
        Pilih Production Line terlebih dahulu untuk menampilkan laporan. Angka laporan dihitung per
        Production Line, sehingga tidak ada pilihan gabungan lintas line.
      </p>

      <!-- Tidak ada satu pun line yang dapat dipilih di layar ini. -->
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

      <!-- Mill belum punya satu pun periode: arahan, bukan pesan teknis. -->
      <p v-else-if="noPeriods" class="notice" data-testid="no-periods">
        Mill ini belum memiliki periode pelaporan yang mencakup stasiun Sterilizer.
        Silakan hubungi Admin untuk membuat periode pelaporan terlebih dahulu.
      </p>

      <LoadingState v-if="loadingProductionLines" test-id="production-lines-loading">Memuat daftar Production Line…</LoadingState>
      <LoadingState v-if="loadingPeriods" test-id="periods-loading">Memuat daftar periode…</LoadingState>

      <!-- Kegagalan jaringan: dikatakan terus terang, dengan cara mencoba
           lagi. Periode yang sudah dipilih tetap terpilih. -->
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
        <!-- Keterangan periode: rentang tanggal + status sebagai catatan. -->
        <div class="period-meta" data-testid="period-meta">
          <span class="period-meta-name">{{ summary?.period?.name }}</span>
          <div class="period-meta-line">
            <span>{{ formatDate(summary?.period?.start_date) }} &ndash; {{ formatDate(summary?.period?.end_date) }}</span>
            <StatusBadge status="none" :label="periodStatusLabel(periodStatus)" data-testid="period-status-badge" />
          </div>
          <span class="period-meta-note">
            Status periode mengatur penulisan data, bukan pembacaan laporan — periode yang sudah ditutup tetap
            dapat dilihat dan diekspor.
          </span>
        </div>

        <p v-if="emptyPeriod" class="notice" data-testid="empty-period">
          Belum ada data siklus rebus pada periode ini.
        </p>

        <!-- Kartu angka utama — satu kolom bertumpuk. -->
        <div class="metric-stack">
          <div class="metric-card">
            <span class="metric-label">Total Siklus Rebus</span>
            <div class="metric-figure">
              <span class="metric-value" data-testid="kpi-total-cycles">{{ formatCount(kpi?.total_cycles) }}</span>
              <span class="metric-unit">siklus</span>
            </div>
          </div>

          <div class="metric-card">
            <span class="metric-label">Total Lori Direbus</span>
            <div class="metric-figure">
              <span class="metric-value" data-testid="kpi-total-cages">{{ formatCount(kpi?.total_cages) }}</span>
              <span class="metric-unit">lori</span>
            </div>
          </div>

          <div class="metric-card">
            <span class="metric-label">Durasi Rata-rata</span>
            <div class="metric-figure">
              <span class="metric-value" data-testid="kpi-avg-duration">{{ formatMinutes(kpi?.avg_duration) }}</span>
              <span v-if="kpi?.avg_duration !== null && kpi?.avg_duration !== undefined" class="metric-unit">menit</span>
            </div>
            <span class="metric-note">
              Terpendek {{ formatMinutes(kpi?.min_duration) }} &middot; Terlama {{ formatMinutes(kpi?.max_duration) }}
            </span>
            <!-- Rata-rata tidak boleh terbaca tanpa tahu berapa siklus
                 yang dikeluarkan dari perhitungannya. -->
            <span class="metric-note" data-testid="cycles-without-duration">
              {{ formatCount(kpi?.cycles_without_duration) }} siklus tanpa durasi tercatat, tidak ikut dihitung
            </span>
          </div>

          <div class="metric-card">
            <span class="metric-label">Kepatuhan Triple-Peak</span>
            <div class="metric-figure">
              <span class="metric-value" data-testid="kpi-triple-peak">
                {{ formatPercent(kpi?.triple_peak_compliance_percent) }}
              </span>
              <span class="metric-unit">%</span>
            </div>
            <span v-if="total" class="metric-note">
              {{ formatCount(total.triple_peak_complete) }} dari {{ formatCount(kpi?.total_cycles) }} siklus memenuhi
              pola tiga puncak tekanan
            </span>
          </div>
        </div>

        <!-- Tren harian — TIDAK dirender pada periode tanpa data, karena
             grafik kosong menyesatkan. -->
        <section v-if="showCharts" class="detail-section" data-testid="daily-trend">
          <h2 class="section-title">Tren Harian</h2>
          <p class="section-note">Jumlah siklus rebus per tanggal.</p>
          <div class="trend-list">
            <div v-for="row in daily" :key="`trend-${row.date}`" class="trend-row">
              <span class="trend-date">{{ formatShortDate(row.date) }}</span>
              <div class="trend-bar-track">
                <div class="trend-bar-fill" :style="{ width: trendBarWidth(row.cycles) }"></div>
              </div>
              <span class="trend-value">{{ formatCount(row.cycles) }}</span>
            </div>
          </div>
        </section>

        <!-- Sebaran durasi per hari. -->
        <section v-if="showCharts" class="detail-section" data-testid="duration-distribution">
          <h2 class="section-title">Sebaran Durasi</h2>
          <p class="section-note">
            Durasi rebus per hari dalam menit. Siklus tanpa durasi tercatat tidak ikut dihitung.
          </p>
          <div class="dist-list">
            <div v-for="row in daily" :key="`dist-${row.date}`" class="dist-row">
              <span class="dist-date">{{ formatDate(row.date) }}</span>
              <div class="dist-figures">
                <div class="dist-figure">
                  <span class="dist-figure-label">Terpendek</span>
                  <span class="dist-figure-value">{{ formatMinutes(row.min_duration) }}</span>
                </div>
                <div class="dist-figure">
                  <span class="dist-figure-label">Rata-rata</span>
                  <span class="dist-figure-value">{{ formatMinutes(row.avg_duration) }}</span>
                </div>
                <div class="dist-figure">
                  <span class="dist-figure-label">Terlama</span>
                  <span class="dist-figure-value">{{ formatMinutes(row.max_duration) }}</span>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- Rekap per unit sterilizer. -->
        <section v-if="byUnit.length > 0" class="detail-section" data-testid="by-unit">
          <h2 class="section-title">Per Unit Sterilizer</h2>
          <div class="unit-list">
            <div v-for="unit in byUnit" :key="unit.sterilizer_no" class="unit-item">
              <span class="unit-name">{{ unit.sterilizer_no || '-' }}</span>
              <div class="unit-stats">
                <div class="unit-stat">
                  <span class="unit-stat-label">Siklus</span>
                  <span class="unit-stat-value">{{ formatCount(unit.cycles) }}</span>
                </div>
                <div class="unit-stat">
                  <span class="unit-stat-label">Lori</span>
                  <span class="unit-stat-value">{{ formatCount(unit.cages) }}</span>
                </div>
                <div class="unit-stat">
                  <span class="unit-stat-label">Rata-rata</span>
                  <span class="unit-stat-value">{{ formatMinutes(unit.avg_duration) }}</span>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- Siklus di luar kebiasaan. -->
        <section v-if="outliers" class="detail-section">
          <h2 class="section-title">Siklus di Luar Kebiasaan</h2>

          <!-- Data belum cukup: ambang TIDAK dihitung dan TIDAK ada siklus
               yang ditandai — menandai pencilan dari segelintir data lebih
               menyesatkan daripada tidak menandai apa pun. -->
          <p v-if="insufficientData" class="notice" data-testid="insufficient-data">
            Data belum cukup untuk menentukan ambang kebiasaan
            ({{ formatCount(outliers.sample_size) }} dari minimal
            {{ formatCount(outliers.min_sample_size) }} siklus berdurasi), sehingga tidak ada siklus yang ditandai.
          </p>

          <template v-else>
            <!-- Ambang adalah informasi, bukan sekadar penanda: tetap
                 ditampilkan walau tidak ada satu pun pencilan. -->
            <div v-if="showThreshold" class="threshold-box" data-testid="outlier-threshold">
              <span class="threshold-method">
                Ambang dihitung dengan metode kuartil (IQR), bukan simpangan baku.
              </span>
              <div class="threshold-grid">
                <span class="threshold-item">Q1 <b>{{ formatMinutes(outliers.q1) }}</b></span>
                <span class="threshold-item">Q3 <b>{{ formatMinutes(outliers.q3) }}</b></span>
                <span class="threshold-item">IQR <b>{{ formatMinutes(outliers.iqr) }}</b></span>
              </div>
              <span class="threshold-range">
                Ambang bawah {{ formatMinutes(outliers.lower_bound) }} &ndash; ambang atas
                {{ formatMinutes(outliers.upper_bound) }} menit
              </span>
            </div>

            <p v-if="noOutliers" class="notice" data-testid="no-outliers">
              Tidak ada siklus di luar kebiasaan pada periode ini.
            </p>

            <div v-else class="outlier-list" data-testid="outlier-list">
              <div
                v-for="(item, index) in outliers.items"
                :key="`${item.date}-${item.sterilizer_no}-${index}`"
                class="outlier-item"
              >
                <div class="outlier-head">
                  <span class="outlier-unit">{{ item.sterilizer_no || '-' }}</span>
                  <span class="outlier-duration">{{ formatMinutes(item.duration_minutes) }} mnt</span>
                </div>
                <span class="outlier-meta">
                  {{ formatDate(item.date) }} &middot; {{ item.close_door_time || '-' }} &rarr;
                  {{ item.open_door_time || '-' }} &middot; {{ formatCount(item.number_of_cages) }} lori
                </span>
              </div>
            </div>
          </template>
        </section>

        <!-- Rekap harian — panjang pada periode sebulan, jadi dibungkus
             CollapsibleSection dan TERTUTUP secara bawaan. -->
        <CollapsibleSection
          v-if="daily.length > 0"
          :title="`Rekap Harian (${formatCount(daily.length)} hari)`"
          data-testid="daily-recap"
        >
          <div class="detail-table-wrap">
            <table class="detail-table">
              <thead>
                <tr>
                  <th>Tanggal</th>
                  <th class="num">Siklus</th>
                  <th class="num">Lori</th>
                  <th class="num">Rata-rata</th>
                  <th class="num">Patuh</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in daily" :key="`recap-${row.date}`">
                  <td>{{ formatDate(row.date) }}</td>
                  <td class="num">{{ formatCount(row.cycles) }}</td>
                  <td class="num">{{ formatCount(row.cages) }}</td>
                  <td class="num">{{ formatMinutes(row.avg_duration) }}</td>
                  <td class="num">{{ formatCount(row.triple_peak_complete) }}/{{ formatCount(row.cycles) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-if="total" class="section-note">
            Total {{ formatCount(total.cycles) }} siklus &middot; {{ formatCount(total.cages) }} lori &middot;
            {{ formatCount(total.triple_peak_complete) }} siklus patuh triple-peak.
          </p>
        </CollapsibleSection>
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
.laporan-ster-view {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 0 16px 20px;
  background: #ffffff;
  font-family: 'Inter', sans-serif;
  box-sizing: border-box;
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

.status-text { margin: 0; font-size: 14px; color: #6b7280; }
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
.metric-unit { font-size: 13px; color: #6b7280; }
.metric-note { font-size: 12px; color: #6b7280; }

.detail-section { display: flex; flex-direction: column; gap: 10px; padding: 14px; border: 1px solid #e5e7eb; border-radius: 8px; }
.section-title { margin: 0; font-size: 16px; font-weight: 700; color: #1f2937; }
.section-note { margin: 0; font-size: 12px; color: #6b7280; }

.trend-list { display: flex; flex-direction: column; gap: 6px; }
.trend-row { display: flex; align-items: center; gap: 8px; }
.trend-date { flex: none; width: 52px; font-size: 12px; color: #6b7280; }
.trend-bar-track { flex: 1; height: 10px; border-radius: 999px; background: #f3f4f6; overflow: hidden; }
.trend-bar-fill { height: 100%; border-radius: 999px; background: #249360; }
.trend-value { flex: none; width: 32px; text-align: right; font-size: 13px; font-weight: 600; color: #1f2937; }

.dist-list { display: flex; flex-direction: column; gap: 10px; }
.dist-row { display: flex; flex-direction: column; gap: 4px; padding-bottom: 8px; border-bottom: 1px solid #f3f4f6; }
.dist-date { font-size: 12px; font-weight: 600; color: #1f2937; }
.dist-figures { display: flex; gap: 12px; flex-wrap: wrap; }
.dist-figure { display: flex; flex-direction: column; }
.dist-figure-label { font-size: 11px; color: #6b7280; }
.dist-figure-value { font-size: 13px; font-weight: 600; color: #1f2937; }

.unit-list { display: flex; flex-direction: column; gap: 10px; }
.unit-item { display: flex; flex-direction: column; gap: 6px; padding-bottom: 8px; border-bottom: 1px solid #f3f4f6; }
.unit-name { font-size: 14px; font-weight: 700; color: #1f2937; }
.unit-stats { display: flex; gap: 16px; flex-wrap: wrap; }
.unit-stat { display: flex; flex-direction: column; }
.unit-stat-label { font-size: 11px; color: #6b7280; }
.unit-stat-value { font-size: 13px; font-weight: 600; color: #1f2937; }

.threshold-box { display: flex; flex-direction: column; gap: 6px; padding: 10px; border-radius: 8px; background: #f9fafb; }
.threshold-method { font-size: 12px; color: #6b7280; }
.threshold-grid { display: flex; gap: 12px; flex-wrap: wrap; }
.threshold-item { font-size: 12px; color: #6b7280; }
.threshold-item b { color: #1f2937; }
.threshold-range { font-size: 13px; font-weight: 600; color: #1f2937; }

.outlier-list { display: flex; flex-direction: column; gap: 10px; }
.outlier-item { display: flex; flex-direction: column; gap: 4px; padding: 10px; border: 1px solid #e5e7eb; border-radius: 8px; }
.outlier-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.outlier-unit { font-size: 14px; font-weight: 700; color: #1f2937; }
.outlier-duration { font-size: 14px; font-weight: 600; color: #b45309; }
.outlier-meta { font-size: 12px; color: #6b7280; }

.detail-table-wrap { width: 100%; overflow-x: auto; }
.detail-table { border-collapse: collapse; width: 100%; font-size: 13px; }
.detail-table th, .detail-table td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #e5e7eb; white-space: nowrap; }
.detail-table th.num, .detail-table td.num { text-align: right; }

.action-footer { display: flex; gap: 10px; margin-top: auto; }
.action-button { min-height: 44px; padding: 0 16px; border-radius: 8px; font-size: 14px; font-weight: 600; font-family: inherit; cursor: pointer; box-sizing: border-box; }
.action-button--primary { border: 1px solid #249360; background: #249360; color: #ffffff; }
.action-button:disabled { opacity: 0.5; cursor: not-allowed; }

/* Tombol yang sedang bekerja tetap berwarna penuh (spinner terbaca);
   tombol lain yang ikut terkunci tetap redup. */
.action-button[aria-busy='true']:disabled {
  opacity: 1;
  cursor: progress;
}
.action-button--secondary { border: 1px solid #e5e7eb; background: #ffffff; color: #1f2937; }
</style>
